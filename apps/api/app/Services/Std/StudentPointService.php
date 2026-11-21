<?php

namespace App\Services\Std;

use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\PointThreshold;
use App\Models\PointType;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentPoint;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class StudentPointService
{
    // =========================================================================
    // 1. MASTER POINT TYPES
    // =========================================================================

    public function listPointTypes(array $filters): LengthAwarePaginator
    {
        $query = PointType::orderBy('kind')->orderBy('points');

        if (! empty($filters['kind'])) {
            $query->where('kind', $filters['kind']);
        }

        if (! empty($filters['severity'])) {
            $query->where('severity', $filters['severity']);
        }

        if (! empty($filters['search'])) {
            $kw = '%'.strtolower($filters['search']).'%';
            $query->whereRaw('LOWER(name) LIKE ?', [$kw]);
        }

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function getPointType(string $id): PointType
    {
        return PointType::findOrFail($id);
    }

    public function createPointType(array $data, Request $request): PointType
    {
        $user = $request->user();
        $this->authorizeManageTypes($user);

        $pointType = PointType::create([
            'school_id' => $data['school_id'] ?? null,
            'kind' => $data['kind'],
            'name' => $data['name'],
            'points' => (int) $data['points'],
            'severity' => $data['severity'] ?? 'rendah',
        ]);

        $this->logAudit($user?->id, 'point_type.create', 'point_types', $pointType->id, null, $pointType->toArray(), $request);

        return $pointType;
    }

    public function updatePointType(string $id, array $data, Request $request): PointType
    {
        $user = $request->user();
        $this->authorizeManageTypes($user);

        $pointType = PointType::findOrFail($id);
        $oldValues = $pointType->toArray();

        $pointType->update($data);

        $this->logAudit($user?->id, 'point_type.update', 'point_types', $pointType->id, $oldValues, $pointType->fresh()->toArray(), $request);

        return $pointType->fresh();
    }

    public function deletePointType(string $id, Request $request): bool
    {
        $user = $request->user();
        $this->authorizeManageTypes($user);

        $pointType = PointType::findOrFail($id);

        if ($pointType->studentPoints()->exists()) {
            throw new HttpException(422, 'Jenis poin tidak dapat dihapus karena sudah tercatat dalam riwayat poin siswa.');
        }

        $oldValues = $pointType->toArray();
        $pointType->delete();

        $this->logAudit($user?->id, 'point_type.delete', 'point_types', $id, $oldValues, null, $request);

        return true;
    }

    // =========================================================================
    // 2. POINT THRESHOLDS
    // =========================================================================

    public function listThresholds(): Collection
    {
        return PointThreshold::orderBy('min_points', 'asc')->get();
    }

    public function getThreshold(string $id): PointThreshold
    {
        return PointThreshold::findOrFail($id);
    }

    public function createThreshold(array $data, Request $request): PointThreshold
    {
        $user = $request->user();
        $this->authorizeManageTypes($user);

        $threshold = PointThreshold::create([
            'school_id' => $data['school_id'] ?? null,
            'min_points' => (int) $data['min_points'],
            'action' => $data['action'],
            'notify_roles' => $data['notify_roles'],
        ]);

        $this->logAudit($user?->id, 'point_threshold.create', 'point_thresholds', $threshold->id, null, $threshold->toArray(), $request);

        return $threshold;
    }

    public function updateThreshold(string $id, array $data, Request $request): PointThreshold
    {
        $user = $request->user();
        $this->authorizeManageTypes($user);

        $threshold = PointThreshold::findOrFail($id);
        $oldValues = $threshold->toArray();

        $threshold->update($data);

        $this->logAudit($user?->id, 'point_threshold.update', 'point_thresholds', $threshold->id, $oldValues, $threshold->fresh()->toArray(), $request);

        return $threshold->fresh();
    }

    public function deleteThreshold(string $id, Request $request): bool
    {
        $user = $request->user();
        $this->authorizeManageTypes($user);

        $threshold = PointThreshold::findOrFail($id);
        $oldValues = $threshold->toArray();

        $threshold->delete();

        $this->logAudit($user?->id, 'point_threshold.delete', 'point_thresholds', $id, $oldValues, null, $request);

        return true;
    }

    // =========================================================================
    // 3. STUDENT POINTS (PENCATATAN POIN SISWA)
    // =========================================================================

    public function listStudentPoints(array $filters, Request $request): LengthAwarePaginator
    {
        $user = $request->user();
        $query = StudentPoint::with(['student.classes', 'pointType', 'recorder', 'evidenceFile'])
            ->orderBy('point_date', 'desc')
            ->orderBy('created_at', 'desc');

        if ($this->isStudent($user)) {
            $student = Student::where('user_id', $user->id)->first();
            $query->where('student_id', $student?->id ?? '00000000-0000-0000-0000-000000000000');
        } elseif ($this->isParent($user)) {
            $studentIds = Student::whereHas('guardians', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })->pluck('id');
            $query->whereIn('student_id', $studentIds);
        } elseif (! empty($filters['student_id'])) {
            $query->where('student_id', $filters['student_id']);
        }

        if (! empty($filters['class_id'])) {
            $query->whereHas('student.classes', function ($q) use ($filters) {
                $q->where('classes.id', $filters['class_id']);
            });
        }

        if (! empty($filters['search'])) {
            $kw = '%'.strtolower($filters['search']).'%';
            $query->whereHas('student', function ($q) use ($kw) {
                $q->whereRaw('LOWER(full_name) LIKE ?', [$kw])
                    ->orWhereRaw('LOWER(nis) LIKE ?', [$kw]);
            });
        }

        if (! empty($filters['kind'])) {
            $query->whereHas('pointType', function ($q) use ($filters) {
                $q->where('kind', $filters['kind']);
            });
        }

        if (! empty($filters['start_date'])) {
            $query->where('point_date', '>=', $filters['start_date']);
        }

        if (! empty($filters['end_date'])) {
            $query->where('point_date', '<=', $filters['end_date']);
        }

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function getStudentPoint(string $id, Request $request): StudentPoint
    {
        $user = $request->user();
        $studentPoint = StudentPoint::with(['student', 'pointType', 'recorder', 'evidenceFile'])->findOrFail($id);

        $this->authorizeViewStudent($user, $studentPoint->student);

        return $studentPoint;
    }

    public function recordStudentPoint(array $data, Request $request): array
    {
        $user = $request->user();
        $this->authorizeRecordPoints($user);

        $student = Student::findOrFail($data['student_id']);
        $pointType = PointType::findOrFail($data['point_type_id']);

        $pointDate = $data['point_date'] ?? Carbon::now()->toDateString();

        $studentPoint = StudentPoint::create([
            'student_id' => $student->id,
            'point_type_id' => $pointType->id,
            'recorded_by' => $user->id,
            'evidence_file_id' => $data['evidence_file_id'] ?? null,
            'point_date' => $pointDate,
            'notes' => $data['notes'] ?? null,
        ]);

        $this->logAudit($user?->id, 'student_point.create', 'student_points', $studentPoint->id, null, $studentPoint->toArray(), $request);

        $result = [
            'point' => $studentPoint->fresh(['student', 'pointType', 'recorder', 'evidenceFile']),
            'triggered_threshold' => null,
            'cumulative_violation_points' => 0,
        ];

        // Jika jenis poin adalah pelanggaran, cek ambang batas sanksi
        if ($pointType->kind === 'pelanggaran') {
            $cumulativeViolationPoints = $this->calculateViolationPoints($student->id);
            $result['cumulative_violation_points'] = $cumulativeViolationPoints;

            // Cari ambang batas tertinggi yang terpenuhi
            $triggeredThreshold = PointThreshold::where('min_points', '<=', $cumulativeViolationPoints)
                ->orderBy('min_points', 'desc')
                ->first();

            if ($triggeredThreshold) {
                $result['triggered_threshold'] = $triggeredThreshold;
                $this->notifyThresholdTriggered($student, $triggeredThreshold, $cumulativeViolationPoints);
            }
        }

        return $result;
    }

    public function deleteStudentPoint(string $id, Request $request): bool
    {
        $user = $request->user();
        $studentPoint = StudentPoint::findOrFail($id);

        // Hanya perekam poin atau admin/kesiswaan yang berhak menghapus
        $isAdminOrKesiswaan = $user->hasRole('admin') ||
            $user->hasRole('super_admin') ||
            $user->hasRole('kesiswaan') ||
            $user->hasRole('student_affairs');

        $canDelete = $isAdminOrKesiswaan || $studentPoint->recorded_by === $user->id;
        if (! $canDelete) {
            throw new HttpException(403, 'Akses ditolak: Anda tidak memiliki wewenang membatalkan catatan poin ini.');
        }

        $oldValues = $studentPoint->toArray();
        $studentPoint->delete();

        $this->logAudit($user?->id, 'student_point.delete', 'student_points', $id, $oldValues, null, $request);

        return true;
    }

    // =========================================================================
    // 4. STUDENT POINTS SUMMARY (RINGKASAN POIN & AMBANG SANKSI)
    // =========================================================================

    public function getStudentPointsSummary(string $studentId, Request $request): array
    {
        $user = $request->user();
        $student = Student::findOrFail($studentId);

        $this->authorizeViewStudent($user, $student);

        $violationPoints = $this->calculateViolationPoints($studentId);
        $achievementPoints = $this->calculateAchievementPoints($studentId);

        $violationCount = StudentPoint::where('student_id', $studentId)
            ->whereHas('pointType', fn ($q) => $q->where('kind', 'pelanggaran'))
            ->count();

        $achievementCount = StudentPoint::where('student_id', $studentId)
            ->whereHas('pointType', fn ($q) => $q->where('kind', 'prestasi'))
            ->count();

        // Ambang batas sanksi yang aktif berdasarkan poin pelanggaran
        $activeThreshold = PointThreshold::where('min_points', '<=', $violationPoints)
            ->orderBy('min_points', 'desc')
            ->first();

        $allReachedThresholds = PointThreshold::where('min_points', '<=', $violationPoints)
            ->orderBy('min_points', 'asc')
            ->get();

        $recentPoints = StudentPoint::with('pointType')
            ->where('student_id', $studentId)
            ->orderBy('point_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return [
            'student' => [
                'id' => $student->id,
                'full_name' => $student->full_name,
                'nis' => $student->nis,
                'nisn' => $student->nisn,
            ],
            'total_violation_points' => $violationPoints,
            'total_achievement_points' => $achievementPoints,
            'net_points' => $achievementPoints - $violationPoints,
            'violation_count' => $violationCount,
            'achievement_count' => $achievementCount,
            'active_sanction' => $activeThreshold ? [
                'id' => $activeThreshold->id,
                'min_points' => $activeThreshold->min_points,
                'action' => $activeThreshold->action,
                'notify_roles' => $activeThreshold->notify_roles,
            ] : null,
            'reached_thresholds' => $allReachedThresholds,
            'recent_points' => $recentPoints,
        ];
    }

    // =========================================================================
    // HELPER METHODS: KALKULASI & NOTIFIKASI
    // =========================================================================

    public function calculateViolationPoints(string $studentId): int
    {
        return (int) StudentPoint::where('student_id', $studentId)
            ->join('point_types', 'student_points.point_type_id', '=', 'point_types.id')
            ->where('point_types.kind', 'pelanggaran')
            ->sum('point_types.points');
    }

    public function calculateAchievementPoints(string $studentId): int
    {
        return (int) StudentPoint::where('student_id', $studentId)
            ->join('point_types', 'student_points.point_type_id', '=', 'point_types.id')
            ->where('point_types.kind', 'prestasi')
            ->sum('point_types.points');
    }

    protected function notifyThresholdTriggered(Student $student, PointThreshold $threshold, int $cumulativePoints): void
    {
        $targetRoles = $threshold->notify_roles ?? [];
        if (empty($targetRoles)) {
            return;
        }

        $roles = Role::whereIn('code', $targetRoles)->pluck('id');
        $usersToNotify = User::whereHas('roles', function ($q) use ($roles) {
            $q->whereIn('roles.id', $roles);
        })->get();

        foreach ($usersToNotify as $recipient) {
            try {
                Notification::create([
                    'user_id' => $recipient->id,
                    'notif_type' => 'student_violation_threshold',
                    'title' => 'Ambang Peringatan Pelanggaran Siswa',
                    'body' => "Siswa {$student->full_name} telah mencapai {$cumulativePoints} poin pelanggaran. Tindak lanjut: {$threshold->action}.",
                    'data' => [
                        'student_id' => $student->id,
                        'threshold_id' => $threshold->id,
                        'min_points' => $threshold->min_points,
                        'action' => $threshold->action,
                        'cumulative_points' => $cumulativePoints,
                    ],
                ]);
            } catch (\Throwable $e) {
                // Jangan gagalkan flow bila notifikasi error
            }
        }
    }

    // =========================================================================
    // OTORISASI & AUDIT
    // =========================================================================

    public function isStudent(?User $user): bool
    {
        return $user?->hasRole('student') ?? false;
    }

    public function isParent(?User $user): bool
    {
        return $user?->hasRole('parent') || $user?->hasRole('guardian');
    }

    public function isAdminOrStaff(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->hasRole('admin') ||
            $user->hasRole('super_admin') ||
            $user->hasRole('kesiswaan') ||
            $user->hasRole('student_affairs') ||
            $user->hasRole('counselor') ||
            $user->hasRole('guru_bk');
    }

    protected function authorizeManageTypes(?User $user): void
    {
        if (! $user) {
            throw new HttpException(401, 'Unauthenticated');
        }

        if (! $this->isAdminOrStaff($user)) {
            throw new HttpException(403, 'Akses ditolak: Anda tidak memiliki wewenang mengelola master poin & ambang sanksi.');
        }
    }

    protected function authorizeRecordPoints(?User $user): void
    {
        if (! $user) {
            throw new HttpException(401, 'Unauthenticated');
        }

        $canRecord = $this->isAdminOrStaff($user) ||
            $user->hasRole('teacher') ||
            $user->hasRole('homeroom_teacher') ||
            $user->hasPermission('student.point.write');

        if (! $canRecord) {
            throw new HttpException(403, 'Akses ditolak: Anda tidak memiliki wewenang mencatat poin siswa.');
        }
    }

    protected function authorizeViewStudent(?User $user, Student $student): void
    {
        if (! $user) {
            throw new HttpException(401, 'Unauthenticated');
        }

        if ($this->isAdminOrStaff($user) || $user->hasRole('teacher') || $user->hasPermission('student.point.read')) {
            return;
        }

        if ($this->isStudent($user) && $student->user_id === $user->id) {
            return;
        }

        if ($this->isParent($user)) {
            $isGuardian = $student->guardians()->where('user_id', $user->id)->exists();
            if ($isGuardian) {
                return;
            }
        }

        throw new HttpException(403, 'Akses ditolak: Anda tidak memiliki akses ke data poin siswa ini.');
    }

    protected function logAudit(?string $actorId, string $action, string $entity, ?string $entityId, ?array $oldValue, ?array $newValue, Request $request): void
    {
        try {
            AuditLog::create([
                'actor_id' => $actorId,
                'action' => $action,
                'entity' => $entity,
                'entity_id' => $entityId,
                'old_value' => $oldValue,
                'new_value' => $newValue,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Throwable $e) {
            // Audit log failure must not fail the transaction
        }
    }
}
