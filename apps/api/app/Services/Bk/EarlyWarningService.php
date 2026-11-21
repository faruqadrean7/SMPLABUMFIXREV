<?php

namespace App\Services\Bk;

use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\ClassStudent;
use App\Models\EarlyWarning;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentPoint;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class EarlyWarningService
{
    /**
     * BE-BK-02: Ambil daftar peringatan dini siswa (Early Warnings).
     */
    public function listWarnings(array $filters, Request $request): LengthAwarePaginator
    {
        $user = $request->user();
        $this->authorizeStaffOrAdmin($user);

        $query = EarlyWarning::with(['student.classes', 'handler'])
            ->orderByRaw("CASE severity WHEN 'tinggi' THEN 1 WHEN 'sedang' THEN 2 ELSE 3 END")
            ->orderBy('created_at', 'desc');

        if (! empty($user->school_id)) {
            $query->whereHas('student', fn ($sq) => $sq->where('school_id', $user->school_id));
        }

        // Scoping jika wali kelas biasa (bukan admin dan bukan guru BK)
        if (! $this->isCounselorOrAdminOrPrincipal($user)) {
            $managedClassIds = SchoolClass::whereHas('homeroomTeacher', function ($hq) use ($user) {
                $hq->where('user_id', $user->id);
            })->pluck('id');

            $query->whereHas('student.classes', function ($cq) use ($managedClassIds) {
                $cq->whereIn('classes.id', $managedClassIds);
            });
        }

        if (! empty($filters['status'])) {
            if ($filters['status'] === 'open' || $filters['status'] === 'aktif') {
                $query->whereIn('status', ['open', 'aktif']);
            } else {
                $query->where('status', $filters['status']);
            }
        }

        if (! empty($filters['severity'])) {
            $query->where('severity', $filters['severity']);
        }

        if (! empty($filters['rule_code'])) {
            if (in_array($filters['rule_code'], ['ATTENDANCE_LOW', 'ATT_LOW'])) {
                $query->whereIn('rule_code', ['ATTENDANCE_LOW', 'ATT_LOW']);
            } else {
                $query->where('rule_code', $filters['rule_code']);
            }
        }

        if (! empty($filters['student_id'])) {
            $query->where('student_id', $filters['student_id']);
        }

        if (! empty($filters['class_id'])) {
            $query->whereHas('student.classes', function ($cq) use ($filters) {
                $cq->where('classes.id', $filters['class_id']);
            });
        }

        if (! empty($filters['search'])) {
            $kw = '%'.strtolower($filters['search']).'%';
            $query->whereHas('student', function ($sq) use ($kw) {
                $sq->whereRaw('LOWER(full_name) LIKE ?', [$kw])
                    ->orWhereRaw('LOWER(nis) LIKE ?', [$kw]);
            });
        }

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }

    /**
     * BE-BK-02: Ambil ringkasan metrik peringatan dini untuk dashboard/tabs.
     */
    public function getSummary(Request $request): array
    {
        $user = $request->user();
        $this->authorizeStaffOrAdmin($user);

        $query = EarlyWarning::query();

        if (! empty($user->school_id)) {
            $query->whereHas('student', fn ($sq) => $sq->where('school_id', $user->school_id));
        }

        if (! $this->isCounselorOrAdminOrPrincipal($user)) {
            $managedClassIds = SchoolClass::whereHas('homeroomTeacher', function ($hq) use ($user) {
                $hq->where('user_id', $user->id);
            })->pluck('id');

            $query->whereHas('student.classes', function ($cq) use ($managedClassIds) {
                $cq->whereIn('classes.id', $managedClassIds);
            });
        }

        $openCount = (clone $query)->whereIn('status', ['open', 'aktif'])->count();
        $inProgressCount = (clone $query)->where('status', 'in_progress')->count();
        $resolvedCount = (clone $query)->where('status', 'resolved')->count();
        $dismissedCount = (clone $query)->where('status', 'dismissed')->count();
        $highSeverityCount = (clone $query)->whereIn('status', ['open', 'aktif', 'in_progress'])->where('severity', 'tinggi')->count();

        return [
            'open_count' => $openCount,
            'in_progress_count' => $inProgressCount,
            'resolved_count' => $resolvedCount,
            'dismissed_count' => $dismissedCount,
            'high_severity_count' => $highSeverityCount,
            'total_active' => $openCount + $inProgressCount,
        ];
    }

    /**
     * BE-BK-02: Ambil detail alert peringatan dini.
     */
    public function getWarning(string $id, Request $request): EarlyWarning
    {
        $user = $request->user();
        $this->authorizeStaffOrAdmin($user);

        $warning = EarlyWarning::with(['student.classes', 'handler'])->findOrFail($id);

        if (! empty($user->school_id) && ! empty($warning->student?->school_id) && $warning->student->school_id !== $user->school_id) {
            throw new HttpException(403, 'Akses ditolak: Peringatan dini berada di luar sekolah penugasan Anda.');
        }

        if (! $this->isCounselorOrAdminOrPrincipal($user)) {
            $isHomeroom = SchoolClass::whereHas('homeroomTeacher', fn ($hq) => $hq->where('user_id', $user->id))
                ->whereHas('students', fn ($sq) => $sq->where('students.id', $warning->student_id))
                ->exists();

            if (! $isHomeroom) {
                throw new HttpException(403, 'Anda hanya dapat melihat peringatan dini siswa di kelas bimbingan Anda.');
            }
        }

        $this->logAudit(
            $user->id,
            'early_warning.read',
            'early_warnings',
            $warning->id,
            null,
            ['student_id' => $warning->student_id, 'rule_code' => $warning->rule_code],
            $request
        );

        return $warning;
    }

    /**
     * BE-BK-02: Evaluasi otomatis aturan deteksi dini (kehadiran, penurunan nilai, poin pelanggaran).
     */
    public function evaluateRules(array $options, ?Request $request = null): array
    {
        $user = $request?->user();
        if ($user) {
            $this->authorizeCounselorOrAdmin($user);
        }

        $studentQuery = Student::query()->whereIn('status', ['active', 'aktif']);

        if (! empty($user?->school_id)) {
            $studentQuery->where('school_id', $user->school_id);
        } elseif (! empty($options['school_id'])) {
            $studentQuery->where('school_id', $options['school_id']);
        }

        if (! empty($options['student_ids'])) {
            $studentQuery->whereIn('id', $options['student_ids']);
        } elseif (! empty($options['class_id'])) {
            $studentIds = ClassStudent::where('class_id', $options['class_id'])->pluck('student_id');
            $studentQuery->whereIn('id', $studentIds);
        }

        $students = $studentQuery->get();

        $attThreshold = (float) ($options['attendance_threshold_percent'] ?? 75.0);
        $minAbsentDays = (int) ($options['min_absent_days'] ?? 3);
        $gradeDropThreshold = (float) ($options['grade_drop_threshold'] ?? 15.0);
        $violationPointsThreshold = (int) ($options['violation_points_threshold'] ?? 20);

        $createdCount = 0;
        $updatedCount = 0;

        DB::transaction(function () use (
            $students,
            $attThreshold,
            $minAbsentDays,
            $gradeDropThreshold,
            $violationPointsThreshold,
            &$createdCount,
            &$updatedCount
        ) {
            foreach ($students as $student) {
                // 1. Evaluasi Kehadiran (30 hari terakhir)
                $attAlert = $this->evaluateAttendance($student, $attThreshold, $minAbsentDays);
                if ($attAlert) {
                    $res = $this->upsertWarning($student->id, 'ATTENDANCE_LOW', $attAlert['severity'], $attAlert['data']);
                    $res['is_new'] ? $createdCount++ : $updatedCount++;
                }

                // 2. Evaluasi Penurunan Nilai Akademik
                $gradeAlert = $this->evaluateGradeDrop($student, $gradeDropThreshold);
                if ($gradeAlert) {
                    $res = $this->upsertWarning($student->id, 'GRADE_DROP', $gradeAlert['severity'], $gradeAlert['data']);
                    $res['is_new'] ? $createdCount++ : $updatedCount++;
                }

                // 3. Evaluasi Poin Pelanggaran Disiplin
                $pointAlert = $this->evaluateViolationPoints($student, $violationPointsThreshold);
                if ($pointAlert) {
                    $res = $this->upsertWarning($student->id, 'VIOLATION_POINTS', $pointAlert['severity'], $pointAlert['data']);
                    $res['is_new'] ? $createdCount++ : $updatedCount++;
                }
            }
        });

        $this->logAudit(
            $user?->id,
            'early_warning.evaluate',
            'early_warnings',
            null,
            null,
            [
                'students_evaluated' => $students->count(),
                'created_alerts' => $createdCount,
                'updated_alerts' => $updatedCount,
            ],
            $request
        );

        return [
            'students_evaluated' => $students->count(),
            'created_alerts' => $createdCount,
            'updated_alerts' => $updatedCount,
            'message' => "Evaluasi deteksi dini selesai. {$createdCount} peringatan baru dibuat, {$updatedCount} diperbarui.",
        ];
    }

    /**
     * BE-BK-02: Tangani / tindak lanjuti peringatan dini.
     */
    public function handleWarning(string $id, array $data, Request $request): EarlyWarning
    {
        $user = $request->user();
        $this->authorizeStaffOrAdmin($user);

        $warning = EarlyWarning::with(['student.classes', 'handler'])->findOrFail($id);

        if (! empty($user->school_id) && ! empty($warning->student?->school_id) && $warning->student->school_id !== $user->school_id) {
            throw new HttpException(403, 'Akses ditolak: Peringatan dini berada di luar sekolah penugasan Anda.');
        }

        if (! $this->isCounselorOrAdminOrPrincipal($user)) {
            $isHomeroom = SchoolClass::whereHas('homeroomTeacher', fn ($hq) => $hq->where('user_id', $user->id))
                ->whereHas('students', fn ($sq) => $sq->where('students.id', $warning->student_id))
                ->exists();

            if (! $isHomeroom) {
                throw new HttpException(403, 'Anda tidak berwenang menindaklanjuti peringatan dini siswa di luar kelas bimbingan.');
            }
        }

        if ($warning->status === 'resolved' && ($data['status'] ?? '') === 'open') {
            throw new HttpException(422, 'Status peringatan yang telah diselesaikan (resolved) tidak dapat dikembalikan langsung ke open.');
        }

        $oldStatus = $warning->status;
        $warning->status = $data['status'];
        $warning->handled_by = $user->id;

        $existingData = $warning->data ?? [];
        if (! empty($data['notes'])) {
            $existingData['handling_notes'] = $data['notes'];
            $existingData['handled_at'] = Carbon::now()->toIso8601String();
        }
        $warning->data = $existingData;
        $warning->save();

        $this->logAudit(
            $user->id,
            'early_warning.handle',
            'early_warnings',
            $warning->id,
            ['status' => $oldStatus],
            ['status' => $warning->status, 'handled_by' => $user->id],
            $request
        );

        return $warning->fresh(['student.classes', 'handler']);
    }

    /**
     * Logika evaluasi kehadiran siswa.
     */
    protected function evaluateAttendance(Student $student, float $attThreshold, int $minAbsentDays): ?array
    {
        $oneMonthAgo = Carbon::now()->subDays(30);

        $attendances = Attendance::where('student_id', $student->id)
            ->where('att_date', '>=', $oneMonthAgo->toDateString())
            ->get();

        $totalRecords = $attendances->count();
        if ($totalRecords === 0) {
            return null;
        }

        $hadirCount = $attendances->where('status', 'hadir')->count();
        $alpaCount = $attendances->where('status', 'alpa')->count();
        $izinCount = $attendances->where('status', 'izin')->count();
        $sakitCount = $attendances->where('status', 'sakit')->count();

        $attendancePercent = round(($hadirCount / $totalRecords) * 100, 1);

        if ($attendancePercent < $attThreshold || $alpaCount >= $minAbsentDays) {
            $severity = ($attendancePercent < 50.0 || $alpaCount >= 5) ? 'tinggi' : 'sedang';

            return [
                'severity' => $severity,
                'data' => [
                    'rule' => 'Persentase kehadiran di bawah batas minimum atau akumulasi alpa tinggi',
                    'attendance_percent' => $attendancePercent,
                    'threshold_percent' => $attThreshold,
                    'alpa_count' => $alpaCount,
                    'izin_count' => $izinCount,
                    'sakit_count' => $sakitCount,
                    'total_days_evaluated' => $totalRecords,
                ],
            ];
        }

        return null;
    }

    /**
     * Logika evaluasi penurunan nilai akademik (grade drop).
     */
    protected function evaluateGradeDrop(Student $student, float $gradeDropThreshold): ?array
    {
        // Ambil riwayat nilai siswa terurut dari yang terbaru
        $grades = Grade::where('student_id', $student->id)
            ->orderBy('created_at', 'desc')
            ->take(6)
            ->get();

        if ($grades->count() < 2) {
            return null;
        }

        $totalCount = $grades->count();
        $half = (int) floor($totalCount / 2);

        $recentGrades = $grades->slice(0, $half)->pluck('value');
        $previousGrades = $grades->slice($half)->pluck('value');

        $recentAvg = round((float) $recentGrades->avg(), 2);
        $prevAvg = round((float) $previousGrades->avg(), 2);

        $drop = $prevAvg - $recentAvg;

        if ($drop >= $gradeDropThreshold) {
            $severity = $drop >= 25.0 ? 'tinggi' : 'sedang';

            return [
                'severity' => $severity,
                'data' => [
                    'rule' => 'Penurunan nilai akademik drastis',
                    'previous_average' => $prevAvg,
                    'recent_average' => $recentAvg,
                    'drop_points' => round($drop, 2),
                    'threshold' => $gradeDropThreshold,
                ],
            ];
        }

        return null;
    }

    /**
     * Logika evaluasi poin pelanggaran disiplin siswa.
     */
    protected function evaluateViolationPoints(Student $student, int $pointsThreshold): ?array
    {
        $totalViolationPoints = StudentPoint::where('student_id', $student->id)
            ->whereHas('pointType', function ($pq) {
                $pq->where('kind', 'pelanggaran');
            })
            ->with('pointType')
            ->get()
            ->sum(function ($sp) {
                return (int) ($sp->pointType?->points ?? 0);
            });

        if ($totalViolationPoints >= $pointsThreshold) {
            $severity = $totalViolationPoints >= ($pointsThreshold * 2) ? 'tinggi' : 'sedang';

            return [
                'severity' => $severity,
                'data' => [
                    'rule' => 'Akumulasi poin pelanggaran disiplin melampaui batas ambang',
                    'total_points' => $totalViolationPoints,
                    'threshold' => $pointsThreshold,
                ],
            ];
        }

        return null;
    }

    /**
     * Simpan atau perbarui peringatan secara idempoten (cegah duplikasi status 'open').
     */
    protected function upsertWarning(string $studentId, string $ruleCode, string $severity, array $data): array
    {
        $existing = EarlyWarning::where('student_id', $studentId)
            ->where('rule_code', $ruleCode)
            ->whereIn('status', ['open', 'in_progress'])
            ->lockForUpdate()
            ->first();

        if ($existing) {
            $existing->severity = $severity;
            $existing->data = $data;
            $existing->save();

            return ['warning' => $existing, 'is_new' => false];
        }

        $newWarning = EarlyWarning::create([
            'student_id' => $studentId,
            'rule_code' => $ruleCode,
            'severity' => $severity,
            'data' => $data,
            'status' => 'open',
        ]);

        return ['warning' => $newWarning, 'is_new' => true];
    }

    protected function isCounselorOrAdminOrPrincipal(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->hasRole('admin') || $user->hasRole('super_admin') || $user->hasRole('principal') || $user->hasRole('kepala_sekolah')) {
            return true;
        }

        if ($user->hasRole('counselor') || $user->hasRole('guru_bk') || $user->hasRole('bk')) {
            return true;
        }

        return $user->hasPermission('bk.counseling.manage') || $user->hasPermission('counseling.case.write');
    }

    protected function authorizeCounselorOrAdmin(?User $user): void
    {
        if (! $user) {
            throw new HttpException(401, 'Unauthenticated');
        }

        if ($user->hasRole('admin') || $user->hasRole('super_admin') || $user->hasRole('counselor') || $user->hasRole('guru_bk') || $user->hasPermission('bk.counseling.manage') || $user->hasPermission('counseling.case.write')) {
            return;
        }

        throw new HttpException(403, 'Akses ditolak: Hanya Guru BK atau Administrator yang berwenang mengevaluasi aturan deteksi dini.');
    }

    protected function authorizeStaffOrAdmin(?User $user): void
    {
        if (! $user) {
            throw new HttpException(401, 'Unauthenticated');
        }

        if ($user->hasRole('siswa') || $user->hasRole('student')) {
            throw new HttpException(403, 'Siswa dilarang keras mengakses sistem deteksi dini.');
        }

        if ($user->hasRole('guardian') || $user->hasRole('wali_murid') || $user->hasRole('ortu') || $user->hasRole('parent')) {
            throw new HttpException(403, 'Wali murid tidak memiliki akses ke sistem deteksi dini.');
        }
    }

    protected function logAudit(
        ?string $actorId,
        string $action,
        string $entity,
        ?string $entityId,
        ?array $oldValue,
        ?array $newValue,
        ?Request $request = null
    ): void {
        try {
            AuditLog::create([
                'actor_id' => $actorId,
                'action' => $action,
                'entity' => $entity,
                'entity_id' => $entityId,
                'old_value' => $oldValue,
                'new_value' => $newValue,
                'ip' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
