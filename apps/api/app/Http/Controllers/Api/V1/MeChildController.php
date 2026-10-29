<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\CharacterNote;
use App\Models\Grade;
use App\Models\ProgressReport;
use App\Models\ReportCard;
use App\Models\Student;
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
            'academic_year' => $currentClass?->academicYear?->name ?? '2024/2025',
            'academicYear' => $currentClass?->academicYear?->name ?? '2024/2025',
            'gender' => $gender,
            'avatar_url' => $student->photoFile?->url ?? null,
            'avatarUrl' => $student->photoFile?->url ?? null,
            'status' => $student->status ?? 'active',
        ];
    }

    /**
     * GET /api/v1/me/children
     * Mengambil daftar seluruh anak di bawah perwalian wali yang login.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $guardian = $user->guardian;

        if (! $guardian) {
            return $this->successResponse([]);
        }

        $students = $guardian->students()
            ->with(['classes.academicYear', 'photoFile'])
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

        $student = Student::with(['classes.academicYear', 'photoFile'])->find($child);
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

        $grades = Grade::with(['component.subject'])
            ->where('student_id', $child)
            ->get()
            ->groupBy(fn ($g) => $g->component?->subject_id ?? 'unknown');

        $result = [];
        foreach ($grades as $subjectId => $items) {
            $first = $items->first();
            $subject = $first?->component?->subject;
            $avgScore = round($items->avg('value') ?? 0, 1);

            $result[] = [
                'subjectId' => (string) $subjectId,
                'subject_id' => (string) $subjectId,
                'subjectName' => $subject?->name ?? 'Mata Pelajaran',
                'subject_name' => $subject?->name ?? 'Mata Pelajaran',
                'subjectCode' => $subject?->code ?? '',
                'subject_code' => $subject?->code ?? '',
                'teacherName' => 'Guru Pengampu',
                'dailyAverage' => $avgScore,
                'midExam' => $avgScore,
                'finalExam' => $avgScore,
                'finalScore' => $avgScore,
                'letterGrade' => $avgScore >= 85 ? 'A' : ($avgScore >= 75 ? 'B' : ($avgScore >= 65 ? 'C' : 'D')),
                'passingGrade' => 75,
                'isPassed' => $avgScore >= 75,
            ];
        }

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
            return $this->successResponse([
                'periodId' => 'none',
                'periodName' => 'Semester Berjalan',
                'academicYear' => '2024/2025',
                'semester' => 'Ganjil',
                'totalScore' => 0,
                'averageScore' => 0,
                'grades' => [],
            ]);
        }

        $snapshot = $report->data_snapshot ?? [];

        return $this->successResponse([
            'periodId' => $report->id,
            'periodName' => $report->semester?->name ?? 'Semester Ganjil',
            'academicYear' => $report->semester?->academicYear?->name ?? '2024/2025',
            'semester' => $report->semester?->name ?? 'Ganjil',
            'totalScore' => $snapshot['total_score'] ?? 0,
            'averageScore' => $snapshot['average_score'] ?? 0,
            'classRank' => $snapshot['rank'] ?? null,
            'homeroomNote' => $snapshot['homeroom_note'] ?? null,
            'grades' => $snapshot['grades'] ?? [],
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
            'academicYear' => '2024/2025',
            'ratings' => $note->scale_data ?? [
                'beriman_bertakwa' => 'baik',
                'gotong_royong' => 'baik',
                'mandiri' => 'baik',
                'bernalar_kritis' => 'baik',
                'kreatif' => 'baik',
            ],
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
}
