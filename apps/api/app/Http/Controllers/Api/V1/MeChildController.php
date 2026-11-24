<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Achievement;
use App\Models\Attendance;
use App\Models\CharacterNote;
use App\Models\Grade;
use App\Models\ProgressReport;
use App\Models\ReportCard;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentPoint;
use App\Models\TeachingAssignment;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MeChildController extends Controller
{
    use ApiResponse;

    /**
     * Helper to verify guardian authorization to access a child.
     */
    protected function verifyAccess(Request $request, string $childId): ?JsonResponse
    {
        if (! Gate::allows('view-child', $childId)) {
            return $this->errorResponse(
                'Anda tidak memiliki hak akses perwalian sah terhadap siswa ini.',
                'FORBIDDEN',
                403
            );
        }

        return null;
    }

    /**
     * Format student model into PortalChildSummary format.
     */
    protected function formatChildSummary(Student $student): array
    {
        $currentClass = $student->classes->first();
        $gender = strtolower($student->gender) === 'p' || strtolower($student->gender) === 'female' ? 'female' : 'male';

        return [
            'id' => $student->id,
            'name' => $student->full_name,
            'nisn' => $student->nisn ?? '',
            'nis' => $student->nis ?? '',
            'class_id' => $currentClass?->id,
            'class_name' => $currentClass?->name ?? 'Belum ada kelas',
            'className' => $currentClass?->name ?? 'Belum ada kelas',
            'academic_year' => $currentClass?->academicYear?->name ?? $this->activeAcademicYearName(),
            'academicYear' => $currentClass?->academicYear?->name ?? $this->activeAcademicYearName(),
            'gender' => $gender,
            'avatar_url' => $student->photoFile?->url ?? null,
            'avatarUrl' => $student->photoFile?->url ?? null,
            'status' => $student->status ?? 'active',
            // Wali kelas dipakai portal orang tua untuk menampilkan kontak guru.
            'homeroom_teacher' => $currentClass?->homeroomTeacher
                ? [
                    'id' => $currentClass->homeroomTeacher->id,
                    'name' => $currentClass->homeroomTeacher->full_name,
                    'nip' => $currentClass->homeroomTeacher->nip,
                    'phone' => $currentClass->homeroomTeacher->user?->phone,
                ]
                : null,
        ];
    }

    /**
     * GET /api/v1/me/children
     * Mengambil daftar seluruh anak di bawah perwalian wali yang login.
     */
    /**
     * Nama tahun ajaran yang sedang berjalan (bukan hardcode '2024/2025').
     */
    private function activeAcademicYearName(): string
    {
        $semester = Semester::where('is_active', true)->with('academicYear')->first()
            ?? Semester::with('academicYear')->orderByDesc('start_date')->first();

        return $semester?->academicYear?->name ?? '-';
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $guardian = $user->guardian;

        if (! $guardian) {
            return $this->successResponse([]);
        }

        $students = $guardian->students()
            ->with(['classes.academicYear', 'classes.homeroomTeacher.user', 'photoFile'])
            ->get();

        $data = $students->map(fn (Student $s) => $this->formatChildSummary($s));

        return $this->successResponse($data);
    }

    /**
     * GET /api/v1/me/children/{child}
     * Detail profil dan ringkasan anak perwalian.
     */
    public function show(Request $request, string $child): JsonResponse
    {
        if ($err = $this->verifyAccess($request, $child)) {
            return $err;
        }

        $student = Student::with(['classes.academicYear', 'classes.homeroomTeacher.user', 'photoFile'])->find($child);
        if (! $student) {
            return $this->errorResponse('Data anak tidak ditemukan.', 'NOT_FOUND', 404);
        }

        return $this->successResponse($this->formatChildSummary($student));
    }

    /**
     * GET /api/v1/me/children/{child}/attendance
     * Rekap dan riwayat presensi harian anak.
     */
    public function attendance(Request $request, string $child): JsonResponse
    {
        if ($err = $this->verifyAccess($request, $child)) {
            return $err;
        }

        $attendances = Attendance::where('student_id', $child)
            ->orderBy('att_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        $presentCount = $attendances->where('status', 'hadir')->count();
        $sickCount = $attendances->where('status', 'sakit')->count();
        $leaveCount = $attendances->whereIn('status', ['izin', 'dispen'])->count();
        $unexcusedCount = $attendances->where('status', 'alpa')->count();
        $totalDays = $attendances->count();

        $percentage = $totalDays > 0
            ? round(($presentCount / $totalDays) * 100, 1)
            : 100.0;

        $history = $attendances->map(function ($att) {
            $mappedStatus = match ($att->status) {
                'hadir' => 'present',
                'sakit' => 'sick',
                'izin', 'dispen' => 'permission',
                default => 'unexcused',
            };

            return [
                'date' => $att->att_date?->format('Y-m-d') ?? (string) $att->att_date,
                'status' => $mappedStatus,
                'check_in_time' => $att->created_at?->format('H:i:s'),
                'checkInTime' => $att->created_at?->format('H:i:s'),
                'notes' => $att->note,
            ];
        })->values();

        return $this->successResponse([
            'summary' => [
                'present_days' => $presentCount,
                'presentDays' => $presentCount,
                'sick_days' => $sickCount,
                'sickDays' => $sickCount,
                'leave_days' => $leaveCount,
                'leaveDays' => $leaveCount,
                'unexcused_days' => $unexcusedCount,
                'unexcusedDays' => $unexcusedCount,
                'total_effective_days' => $totalDays,
                'totalDays' => $totalDays,
                'attendance_percentage' => $percentage,
                'percentage' => $percentage,
            ],
            'history' => $history,
        ]);
    }

    /**
     * GET /api/v1/me/children/{child}/grades
     * Rekap buku nilai mata pelajaran anak.
     */
    public function grades(Request $request, string $child): JsonResponse
    {
        if ($err = $this->verifyAccess($request, $child)) {
            return $err;
        }

        $student = Student::with('classes')->find($child);
        $classId = $student?->classes->first()?->id;

        $grades = Grade::with(['component.subject'])
            ->where('student_id', $child)
            ->get()
            ->groupBy(fn ($g) => $g->component?->subject_id ?? 'unknown');

        // Guru pengampu sebenarnya per (kelas, mapel) — dulu selalu "Guru Pengampu".
        $teacherBySubject = $classId
            ? TeachingAssignment::with('teacher')
                ->where('class_id', $classId)
                ->get()
                ->mapWithKeys(fn ($ta) => [$ta->subject_id => $ta->teacher?->full_name])
            : collect();

        $kktp = 75.0;

        $result = [];
        foreach ($grades as $subjectId => $items) {
            $first = $items->first();
            $subject = $first?->component?->subject;

            // Pecah per komponen: Formatif (TGS), Sumatif tengah (UTS), Sumatif akhir (UAS).
            // Dulu ketiga kolom diisi satu angka rata-rata yang sama.
            $byCode = [];
            $weighted = 0.0;
            $totalWeight = 0.0;
            foreach ($items as $grade) {
                $code = $grade->component?->code;
                $weight = (float) ($grade->component?->weight ?? 0);
                if ($code) {
                    $byCode[$code] = (float) $grade->value;
                }
                if ($weight > 0) {
                    $weighted += (float) $grade->value * $weight;
                    $totalWeight += $weight;
                }
            }

            $finalScore = $totalWeight > 0
                ? round($weighted / $totalWeight, 1)
                : round((float) ($items->avg('value') ?? 0), 1);

            $result[] = [
                'subjectId' => (string) $subjectId,
                'subject_id' => (string) $subjectId,
                'subjectName' => $subject?->name ?? 'Mata Pelajaran',
                'subject_name' => $subject?->name ?? 'Mata Pelajaran',
                'subjectCode' => $subject?->code ?? '',
                'subject_code' => $subject?->code ?? '',
                'teacherName' => $teacherBySubject[$subjectId] ?? '-',
                'dailyAverage' => $byCode['TGS'] ?? null,
                'midExam' => $byCode['UTS'] ?? null,
                'finalExam' => $byCode['UAS'] ?? null,
                'attitude' => $byCode['SIKAP'] ?? null,
                'components' => $byCode,
                'finalScore' => $finalScore,
                'letterGrade' => $finalScore >= 85 ? 'A' : ($finalScore >= 75 ? 'B' : ($finalScore >= 65 ? 'C' : 'D')),
                'passingGrade' => $kktp,
                'isPassed' => $finalScore >= $kktp,
            ];
        }

        usort($result, fn ($a, $b) => strcmp($a['subjectName'], $b['subjectName']));

        return $this->successResponse($result);
    }

    /**
     * GET /api/v1/me/children/{child}/report-cards
     * e-rapor semester resmi yang telah dikunci.
     */
    public function reportCards(Request $request, string $child): JsonResponse
    {
        if ($err = $this->verifyAccess($request, $child)) {
            return $err;
        }

        $report = ReportCard::with(['semester.academicYear'])
            ->where('student_id', $child)
            ->latest()
            ->first();

        if (! $report) {
            $semester = Semester::where('is_active', true)->with('academicYear')->first();

            return $this->successResponse([
                'periodId' => null,
                'periodName' => $semester?->name ?? 'Semester Berjalan',
                'academicYear' => $semester?->academicYear?->name ?? $this->activeAcademicYearName(),
                'semester' => $semester?->name ?? 'Ganjil',
                'totalScore' => 0,
                'averageScore' => 0,
                'grades' => [],
            ]);
        }

        $snapshot = $report->data_snapshot ?? [];

        // Snapshot rapor menyimpan nilai per mapel di kunci 'subjects', bukan 'grades'.
        // Dulu endpoint ini membaca 'grades' sehingga rapor orang tua SELALU kosong
        // padahal nilainya lengkap tersimpan di snapshot.
        $subjects = is_array($snapshot['subjects'] ?? null) ? $snapshot['subjects'] : [];
        $scores = collect($subjects)->pluck('final_score')->filter(fn ($v) => is_numeric($v));

        $total = $snapshot['total_score'] ?? round($scores->sum(), 2);
        $average = $snapshot['average_score'] ?? ($scores->isNotEmpty() ? round($scores->avg(), 2) : 0);

        // Nama guru pengampu per mapel untuk kelas anak saat ini.
        $childClassId = Student::with('classes')->find($child)?->classes->first()?->id;
        $teacherBySubject = $childClassId
            ? TeachingAssignment::with('teacher')
                ->where('class_id', $childClassId)
                ->get()
                ->mapWithKeys(fn ($ta) => [$ta->subject_id => $ta->teacher?->full_name])
            : collect();

        $grades = collect($subjects)->map(function ($s) use ($teacherBySubject) {
            $score = isset($s['final_score']) ? (float) $s['final_score'] : null;
            $letter = $score === null ? null
                : ($score >= 85 ? 'A' : ($score >= 75 ? 'B' : ($score >= 65 ? 'C' : 'D')));

            // Kirim snake_case DAN camelCase: halaman e-Rapor membaca camelCase,
            // sementara mapper nilai membaca snake_case.
            return [
                'subject_id' => $s['subject_id'] ?? null,
                'subjectId' => $s['subject_id'] ?? null,
                'subject_code' => $s['subject_code'] ?? null,
                'subjectCode' => $s['subject_code'] ?? null,
                'subject_name' => $s['subject_name'] ?? null,
                'subjectName' => $s['subject_name'] ?? null,
                'teacherName' => $teacherBySubject[$s['subject_id'] ?? ''] ?? '-',
                'final_score' => $score,
                'finalScore' => $score,
                'letterGrade' => $letter,
                'level' => $s['level'] ?? null,
                'isPassed' => ($s['level'] ?? '') === 'tuntas',
                'competency_description' => $s['competency_description'] ?? null,
            ];
        })->values();

        return $this->successResponse([
            'periodId' => $report->id,
            'periodName' => $report->semester?->name ?? 'Semester Ganjil',
            'academicYear' => $report->semester?->academicYear?->name ?? $this->activeAcademicYearName(),
            'semester' => $report->semester?->name ?? 'Ganjil',
            'totalScore' => $total,
            'averageScore' => $average,
            'classRank' => $snapshot['rank'] ?? null,
            'homeroomNote' => $snapshot['homeroom_note'] ?? null,
            'status' => $report->status,
            'grades' => $grades,
        ]);
    }

    /**
     * GET /api/v1/me/children/{child}/character-notes
     * Catatan adab dan penilaian karakter profil pelajar Pancasila.
     */
    public function characterNotes(Request $request, string $child): JsonResponse
    {
        if ($err = $this->verifyAccess($request, $child)) {
            return $err;
        }

        $note = CharacterNote::with('period')
            ->where('student_id', $child)
            ->latest()
            ->first();

        if (! $note) {
            return $this->successResponse(null);
        }

        return $this->successResponse([
            'periodId' => $note->period_id,
            'periodName' => $note->period?->name ?? 'Semester Berjalan',
            'academicYear' => $this->activeAcademicYearName(),
            // Jangan mengarang penilaian: dulu lima aspek selalu diisi "baik"
            // walau guru belum menilai apa pun.
            'ratings' => $note->scale_data ?? [],
            'homeroomNote' => $note->homeroom_note ?? '',
            'updatedAt' => $note->updated_at?->toISOString() ?? now()->toISOString(),
        ]);
    }

    /**
     * GET /api/v1/me/children/{child}/progress-reports
     * Catatan kemajuan akademik dan perkembangan anak.
     */
    public function progressReports(Request $request, string $child): JsonResponse
    {
        if ($err = $this->verifyAccess($request, $child)) {
            return $err;
        }

        $reports = ProgressReport::with(['period', 'approver'])
            ->where('student_id', $child)
            ->latest()
            ->get();

        $data = $reports->map(function ($r) {
            $snap = $r->data_snapshot ?? [];

            return [
                'id' => $r->id,
                'title' => $snap['title'] ?? 'Laporan Kemajuan Belajar',
                'periodName' => $r->period?->name ?? 'Semester Ganjil',
                'category' => $snap['category'] ?? 'Akademik',
                'narrative' => $snap['narrative'] ?? ($snap['notes'] ?? ''),
                'teacherName' => $r->approver?->name ?? 'Wali Kelas',
                'date' => $r->created_at?->format('Y-m-d') ?? now()->format('Y-m-d'),
            ];
        });

        return $this->successResponse($data);
    }

    /**
     * GET /api/v1/me/children/{child}/points
     * Rekapitulasi poin karakter, kedisiplinan, dan prestasi anak perwalian.
     */
    public function points(Request $request, string $child): JsonResponse
    {
        if ($err = $this->verifyAccess($request, $child)) {
            return $err;
        }

        $student = Student::find($child);
        if (! $student) {
            return $this->errorResponse('Data anak tidak ditemukan.', 'NOT_FOUND', 404);
        }

        $studentPoints = StudentPoint::with(['pointType', 'recorder'])
            ->where('student_id', $student->id)
            ->orderBy('point_date', 'desc')
            ->get();

        $achievements = Achievement::where('student_id', $student->id)
            ->orderBy('achieved_at', 'desc')
            ->get();

        $items = collect();

        foreach ($achievements as $ach) {
            $dateStr = $ach->achieved_at ? $ach->achieved_at->format('d M Y') : $ach->created_at->format('d M Y');
            $items->push([
                'id' => 'ach-' . $ach->id,
                'student_id' => $student->id,
                'kind' => 'prestasi',
                'title' => $ach->title,
                'category' => $ach->rank_level ?: 'Prestasi & Kompetisi',
                'point_date' => $dateStr,
                'points' => (int) ($ach->points_awarded ?: 25),
                'note' => $ach->notes ?: 'Diverifikasi oleh tim kesiswaan SMP Labschool UM.',
                'recorder_name' => 'Tim Kesiswaan',
            ]);
        }

        foreach ($studentPoints as $sp) {
            $isViolation = ($sp->pointType?->kind === 'pelanggaran');
            $dateStr = $sp->point_date ? $sp->point_date->format('d M Y') : $sp->created_at->format('d M Y');
            $items->push([
                'id' => 'pt-' . $sp->id,
                'student_id' => $student->id,
                'kind' => $isViolation ? 'pelanggaran' : 'prestasi',
                'title' => $sp->pointType?->name ?: ($isViolation ? 'Pelanggaran Kedisiplinan' : 'Apresiasi Karakter'),
                'category' => ucfirst($sp->pointType?->severity ?: 'Disiplin'),
                'point_date' => $dateStr,
                'points' => (int) ($sp->pointType?->points ?: 0),
                'note' => $sp->notes ?: 'Tercatat dalam buku pembiasaan siswa.',
                'recorder_name' => $sp->recorder?->name ?: 'Guru Piket / Kesiswaan',
            ]);
        }

        $totalPrestasi = $items->where('kind', 'prestasi')->sum('points');
        $totalPelanggaran = $items->where('kind', 'pelanggaran')->sum('points');
        $balancePoints = $totalPrestasi - $totalPelanggaran;

        $statusDisiplin = match (true) {
            $totalPelanggaran >= 75 => 'SP3 - Konferensi Kasus',
            $totalPelanggaran >= 50 => 'SP2 - Peringatan 2',
            $totalPelanggaran >= 25 => 'SP1 - Peringatan 1',
            default => 'AMAN',
        };

        return $this->successResponse([
            'totalPrestasi' => $totalPrestasi,
            'totalPelanggaran' => $totalPelanggaran,
            'balancePoints' => $balancePoints,
            'statusDisiplin' => $statusDisiplin,
            'achievementsCount' => $achievements->count(),
            'items' => $items->values()->all(),
        ]);
    }
}
