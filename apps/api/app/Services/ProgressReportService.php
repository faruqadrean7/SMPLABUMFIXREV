<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\CharacterNote;
use App\Models\ClassStudent;
use App\Models\Grade;
use App\Models\GradePeriod;
use App\Models\Guardian;
use App\Models\ProgressReport;
use App\Models\ReportCard;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ProgressReportService
{
    /**
     * BE-GRD-03: Generate snapshot E-Progress Report bulanan per siswa atau per kelas.
     */
    public function generateProgressReports(array $data, Request $request): array
    {
        /** @var User $currentUser */
        $currentUser = $request->user();

        if (! $this->canGenerate($currentUser, $data)) {
            throw new HttpException(403, 'Anda tidak memiliki hak akses untuk men-generate e-progress report.');
        }

        $period = GradePeriod::with('semester.academicYear')->findOrFail($data['period_id']);

        // Dapatkan daftar siswa target
        $students = collect();
        if (! empty($data['student_id'])) {
            $student = Student::with('classes')->findOrFail($data['student_id']);
            $students->push($student);
        } elseif (! empty($data['class_id'])) {
            $studentIds = ClassStudent::where('class_id', $data['class_id'])
                ->whereNull('left_at')
                ->pluck('student_id');

            $students = Student::with('classes')
                ->whereIn('id', $studentIds)
                ->get();
        }

        if ($students->isEmpty()) {
            throw new HttpException(422, 'Tidak ada siswa yang ditemukan untuk diproses.');
        }

        $statusToSet = $data['status'] ?? 'menunggu_acc';
        $results = [];

        DB::transaction(function () use ($students, $period, $statusToSet, &$results, $request, $currentUser) {
            foreach ($students as $student) {
                // Cek apakah progress report sudah ada
                $existingReport = ProgressReport::where('student_id', $student->id)
                    ->where('period_id', $period->id)
                    ->first();

                if ($existingReport && in_array($existingReport->status, ['disetujui', 'terbit'])) {
                    // Jika siswa tunggal diminta eksplisit, lemparkan pesan validasi
                    if ($students->count() === 1) {
                        throw new HttpException(422, "E-Progress report siswa {$student->full_name} untuk periode ini sudah disetujui/terbit dan tidak dapat diperbarui.");
                    }
                    $results[] = $existingReport;

                    continue;
                }

                // Kompilasi data snapshot
                $snapshot = $this->compileSnapshot($student, $period);

                $report = $existingReport ?? new ProgressReport;
                $isNew = ! $report->exists;

                $report->student_id = $student->id;
                $report->period_id = $period->id;
                $report->data_snapshot = $snapshot;
                $report->status = $statusToSet;

                if (! $report->verify_token) {
                    $report->verify_token = hash('sha256', Str::uuid()->toString().microtime(true));
                }

                $report->save();

                $this->logAudit(
                    $currentUser->id,
                    $isNew ? 'progress_report.create' : 'progress_report.regenerate',
                    'progress_reports',
                    $report->id,
                    $isNew ? null : ['status' => $existingReport->status],
                    ['status' => $report->status, 'student_id' => $student->id, 'period_id' => $period->id],
                    $request
                );

                $results[] = $report->fresh(['student', 'period.semester', 'approver']);
            }
        });

        return $results;
    }

    /**
     * BE-GRD-03: Kompilasi data snapshot statis rapor bulanan.
     */
    public function compileSnapshot(Student $student, GradePeriod $period): array
    {
        // 1. Metadata Siswa & Rombel Aktif
        $activeClass = $student->classes()
            ->whereNull('class_students.left_at')
            ->first();

        $studentData = [
            'id' => $student->id,
            'nis' => $student->nis,
            'nisn' => $student->nisn,
            'full_name' => $student->full_name,
            'gender' => $student->gender,
            'class_id' => $activeClass?->id,
            'class_name' => $activeClass?->name ?? 'Belum ada kelas',
        ];

        // 2. Metadata Periode
        $periodData = [
            'id' => $period->id,
            'month' => (int) $period->month,
            'semester_id' => $period->semester_id,
            'semester_name' => $period->semester?->name,
            'academic_year' => $period->semester?->academicYear?->name,
        ];

        // 3. Nilai Per Mata Pelajaran
        $grades = Grade::with(['component.subject'])
            ->where('student_id', $student->id)
            ->where('period_id', $period->id)
            ->get();

        $groupedBySubject = $grades->groupBy(fn ($g) => $g->component?->subject_id ?? 'no_subject');
        $gradesSnapshot = [];

        foreach ($groupedBySubject as $subjectId => $subjectGrades) {
            $firstGrade = $subjectGrades->first();
            $subject = $firstGrade->component?->subject;

            $componentsList = [];
            $totalScoreWeighted = 0;
            $totalWeight = 0;

            foreach ($subjectGrades as $gradeItem) {
                $comp = $gradeItem->component;
                $weight = (float) ($comp?->weight ?? 1.0);
                $value = (float) $gradeItem->value;

                $componentsList[] = [
                    'component_id' => $comp?->id,
                    'name' => $comp?->name ?? 'Komponen',
                    'weight' => $weight,
                    'value' => $value,
                ];

                $totalScoreWeighted += ($value * $weight);
                $totalWeight += $weight;
            }

            $finalScore = $totalWeight > 0
                ? round($totalScoreWeighted / $totalWeight, 2)
                : round($subjectGrades->avg('value'), 2);

            $gradesSnapshot[] = [
                'subject_id' => $subjectId,
                'subject_name' => $subject?->name ?? 'Mata Pelajaran',
                'subject_code' => $subject?->code ?? '-',
                'components' => $componentsList,
                'final_score' => $finalScore,
            ];
        }

        // 4. Rekap Kehadiran Siswa
        $attendanceQuery = Attendance::where('student_id', $student->id);
        if ($period->month) {
            $attendanceQuery->whereMonth('att_date', $period->month);
        }

        $attendances = $attendanceQuery->get();
        $hadir = $attendances->where('status', 'hadir')->count();
        $izin = $attendances->where('status', 'izin')->count();
        $sakit = $attendances->where('status', 'sakit')->count();
        $alpa = $attendances->where('status', 'alpa')->count();
        $totalDays = $hadir + $izin + $sakit + $alpa;
        $attendancePercentage = $totalDays > 0 ? round(($hadir / $totalDays) * 100, 2) : 100.0;

        $attendanceSnapshot = [
            'hadir' => $hadir,
            'izin' => $izin,
            'sakit' => $sakit,
            'alpa' => $alpa,
            'total_recorded' => $totalDays,
            'percentage' => $attendancePercentage,
        ];

        // 5. Catatan Karakter & Catatan Wali Kelas
        $charNote = CharacterNote::with('author')
            ->where('student_id', $student->id)
            ->where('period_id', $period->id)
            ->first();

        $characterSnapshot = [
            'scale_data' => $charNote?->scale_data ?? [],
            'homeroom_note' => $charNote?->homeroom_note ?? '-',
            'author_name' => $charNote?->author?->name ?? 'Wali Kelas',
        ];

        return [
            'student' => $studentData,
            'period' => $periodData,
            'grades' => $gradesSnapshot,
            'attendance' => $attendanceSnapshot,
            'character' => $characterSnapshot,
            'school_name' => 'Portal Sekolah Satu Pintu SISTLABUM',
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * BE-GRD-03: Daftar E-Progress Report dengan filter & otorisasi IDOR.
     */
    public function listProgressReports(array $filters, Request $request): LengthAwarePaginator
    {
        /** @var User $currentUser */
        $currentUser = $request->user();
        $student = Student::where('user_id', $currentUser->id)->first();
        $guardian = Guardian::where('user_id', $currentUser->id)->first();
        $teacher = Teacher::where('user_id', $currentUser->id)->first();
        $isAdmin = $this->checkIsAdmin($currentUser);

        $query = ProgressReport::with(['student.classes', 'period.semester.academicYear', 'approver'])
            ->orderBy('created_at', 'desc');

        if (! $isAdmin) {
            if ($student) {
                // Siswa hanya melihat miliknya sendiri dan yang berstatus terbit / disetujui
                $query->where('student_id', $student->id)
                    ->whereIn('status', ['disetujui', 'terbit']);
            } elseif ($guardian) {
                // Wali murid hanya melihat anaknya yang berstatus terbit / disetujui
                $childIds = $guardian->students()->pluck('students.id');
                $query->whereIn('student_id', $childIds)
                    ->whereIn('status', ['disetujui', 'terbit']);
            } elseif ($teacher) {
                // Walas melihat siswa di kelas perwaliannya
                $homeroomClassIds = SchoolClass::where('homeroom_teacher_id', $teacher->id)->pluck('id');
                $query->whereHas('student.classes', fn ($q) => $q->whereIn('classes.id', $homeroomClassIds));
            } else {
                throw new HttpException(403, 'Akses ditolak.');
            }
        }

        if (! empty($filters['student_id'])) {
            $query->where('student_id', $filters['student_id']);
        }

        if (! empty($filters['period_id'])) {
            $query->where('period_id', $filters['period_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['class_id'])) {
            $classId = $filters['class_id'];
            $query->whereHas('student.classes', fn ($q) => $q->where('classes.id', $classId));
        }

        $perPage = (int) ($filters['per_page'] ?? 15);

        return $query->paginate($perPage);
    }

    /**
     * BE-GRD-03: Detail E-Progress Report dengan otorisasi IDOR.
     */
    public function getProgressReport(string $id, Request $request): ProgressReport
    {
        $report = ProgressReport::with(['student.classes', 'period.semester.academicYear', 'approver'])->findOrFail($id);
        $this->authorizeAccess($report, $request);

        return $report;
    }

    /**
     * BE-GRD-03: Pengesahan / ACC Kepala Sekolah atas E-Progress Report.
     */
    public function approveProgressReport(string $id, array $data, Request $request): ProgressReport
    {
        /** @var User $currentUser */
        $currentUser = $request->user();

        $newStatus = $data['status'];

        if ($newStatus !== 'menunggu_acc' && ! $this->canApprove($currentUser)) {
            throw new HttpException(403, 'Hanya Kepala Sekolah atau Administrator yang berwenang menyetujui e-progress report.');
        }

        $report = ProgressReport::findOrFail($id);

        if ($newStatus === 'menunggu_acc' && ! $this->checkIsAdmin($currentUser)) {
            $this->authorizeAccess($report, $request);
        }

        $oldStatus = $report->status;

        $report->status = $newStatus;
        if (in_array($newStatus, ['disetujui', 'terbit'])) {
            $report->approved_by = $currentUser->id;
        } else {
            // Jika dikembalikan ke draft untuk revisi atau diajukan ke menunggu_acc
            $report->approved_by = null;
        }

        $report->save();

        $this->logAudit(
            $currentUser->id,
            'progress_report.approve',
            'progress_reports',
            $report->id,
            ['status' => $oldStatus],
            ['status' => $newStatus, 'approved_by' => $report->approved_by, 'note' => $data['note'] ?? null],
            $request
        );

        return $report->fresh(['student', 'period.semester.academicYear', 'approver']);
    }

    /**
     * BE-GRD-03: Pengesahan massal / ACC Kepala Sekolah atas E-Progress Report.
     */
    public function bulkApprove(array $data, Request $request): array
    {
        /** @var User $currentUser */
        $currentUser = $request->user();

        $newStatus = $data['status'];

        if ($newStatus !== 'menunggu_acc' && ! $this->canApprove($currentUser)) {
            throw new HttpException(403, 'Hanya Kepala Sekolah atau Administrator yang berwenang menyetujui e-progress report.');
        }
        $query = ProgressReport::query();

        if (! empty($data['report_ids'])) {
            $query->whereIn('id', $data['report_ids']);
        } elseif (! empty($data['class_id']) && ! empty($data['period_id'])) {
            $classId = $data['class_id'];
            $periodId = $data['period_id'];
            $query->where('period_id', $periodId)
                ->whereHas('student.classes', fn ($q) => $q->where('classes.id', $classId)->whereNull('class_students.left_at'));
        } else {
            throw new HttpException(422, 'Wajib menyertakan report_ids atau kombinasi class_id dan period_id.');
        }

        $reports = $query->get();

        if ($reports->isEmpty()) {
            return [
                'count' => 0,
                'status' => $newStatus,
                'items' => [],
            ];
        }

        $updatedCount = 0;
        $updatedItems = [];

        DB::transaction(function () use ($reports, $newStatus, $currentUser, $request, $data, &$updatedCount, &$updatedItems) {
            foreach ($reports as $report) {
                $oldStatus = $report->status;
                $report->status = $newStatus;

                if (in_array($newStatus, ['disetujui', 'terbit'])) {
                    $report->approved_by = $currentUser->id;
                } else {
                    $report->approved_by = null;
                }

                $report->save();
                $updatedCount++;

                $this->logAudit(
                    $currentUser->id,
                    'progress_report.bulk_approve',
                    'progress_reports',
                    $report->id,
                    ['status' => $oldStatus],
                    ['status' => $newStatus, 'approved_by' => $report->approved_by, 'note' => $data['note'] ?? null],
                    $request
                );

                $updatedItems[] = $report->fresh(['student', 'period.semester', 'approver']);
            }
        });

        return [
            'count' => $updatedCount,
            'status' => $newStatus,
            'items' => $updatedItems,
        ];
    }

    /**
     * BE-GRD-03: Tinjauan status & kesiapan data rapor satu kelas/rombel.
     */
    public function getClassProgressReportsStatus(array $filters, Request $request): array
    {
        /** @var User $currentUser */
        $currentUser = $request->user();

        if (empty($filters['class_id']) || empty($filters['period_id'])) {
            throw new HttpException(422, 'Parameter class_id dan period_id wajib disertakan.');
        }

        $class = SchoolClass::with('homeroomTeacher')->findOrFail($filters['class_id']);
        $period = GradePeriod::with('semester.academicYear')->findOrFail($filters['period_id']);

        // Otorisasi: Walas kelas bersangkutan atau Admin/Kepsek
        if (! $this->checkIsAdmin($currentUser)) {
            $teacher = Teacher::where('user_id', $currentUser->id)->first();
            if (! $teacher || $class->homeroom_teacher_id !== $teacher->id) {
                throw new HttpException(403, 'Anda bukan wali kelas dari rombel ini.');
            }
        }

        // Siswa aktif di rombel
        $students = Student::whereHas('classes', function ($q) use ($class) {
            $q->where('classes.id', $class->id)->whereNull('class_students.left_at');
        })->orderBy('full_name', 'asc')->get();

        $studentIds = $students->pluck('id');

        // Existing reports
        $existingReports = ProgressReport::with('approver')
            ->whereIn('student_id', $studentIds)
            ->where('period_id', $period->id)
            ->get()
            ->keyBy('student_id');

        // Data nilai per siswa di periode ini
        $gradesCount = Grade::whereIn('student_id', $studentIds)
            ->where('period_id', $period->id)
            ->select('student_id', DB::raw('count(distinct component_id) as comp_count'))
            ->groupBy('student_id')
            ->pluck('comp_count', 'student_id');

        // Catatan karakter per siswa di periode ini
        $charNotes = CharacterNote::whereIn('student_id', $studentIds)
            ->where('period_id', $period->id)
            ->get()
            ->keyBy('student_id');

        // Kehadiran per siswa di bulan ini
        $attendanceQuery = Attendance::whereIn('student_id', $studentIds);
        if ($period->month) {
            $attendanceQuery->whereMonth('att_date', $period->month);
        }
        $attendanceCount = $attendanceQuery
            ->select('student_id', DB::raw('count(*) as att_count'))
            ->groupBy('student_id')
            ->pluck('att_count', 'student_id');

        $studentRows = [];
        $counts = [
            'total_students' => $students->count(),
            'generated_count' => 0,
            'draft_count' => 0,
            'menunggu_acc_count' => 0,
            'disetujui_count' => 0,
            'terbit_count' => 0,
            'belum_dibuat_count' => 0,
            'ready_to_generate_count' => 0,
        ];

        foreach ($students as $stu) {
            /** @var ProgressReport|null $rep */
            $rep = $existingReports->get($stu->id);
            $hasGrades = ($gradesCount->get($stu->id, 0) > 0);
            $cNote = $charNotes->get($stu->id);
            $hasCharNote = ($cNote !== null);
            $scaleData = is_array($cNote?->scale_data)
                ? $cNote->scale_data
                : (is_string($cNote?->scale_data) ? json_decode($cNote->scale_data, true) : []);
            $charNoteComplete = $cNote ? (is_array($scaleData) && count($scaleData) > 0 && ! empty($cNote->homeroom_note)) : false;
            $attDays = $attendanceCount->get($stu->id, 0);

            $isReady = $hasGrades && $hasCharNote;
            if ($isReady) {
                $counts['ready_to_generate_count']++;
            }

            if ($rep) {
                $counts['generated_count']++;
                match ($rep->status) {
                    'draft' => $counts['draft_count']++,
                    'menunggu_acc' => $counts['menunggu_acc_count']++,
                    'disetujui' => $counts['disetujui_count']++,
                    'terbit' => $counts['terbit_count']++,
                    default => null,
                };
            } else {
                $counts['belum_dibuat_count']++;
            }

            $studentRows[] = [
                'student' => [
                    'id' => $stu->id,
                    'nis' => $stu->nis,
                    'nisn' => $stu->nisn,
                    'full_name' => $stu->full_name,
                    'gender' => $stu->gender,
                ],
                'report' => $rep ? [
                    'id' => $rep->id,
                    'status' => $rep->status,
                    'verify_token' => $rep->verify_token,
                    'created_at' => $rep->created_at?->toIso8601String(),
                    'updated_at' => $rep->updated_at?->toIso8601String(),
                    'approved_by' => $rep->approved_by,
                    'approver_name' => $rep->approver?->name ?? $rep->approver?->username,
                ] : null,
                'data_readiness' => [
                    'has_grades' => $hasGrades,
                    'graded_subjects_count' => (int) $gradesCount->get($stu->id, 0),
                    'has_character_note' => $hasCharNote,
                    'character_note_complete' => $charNoteComplete,
                    'attendance_days_count' => (int) $attDays,
                    'is_ready_to_generate' => $isReady,
                ],
            ];
        }

        return [
            'class' => [
                'id' => $class->id,
                'name' => $class->name,
                'homeroom_teacher' => $class->homeroomTeacher?->full_name,
            ],
            'period' => [
                'id' => $period->id,
                'month' => (int) $period->month,
                'semester_name' => $period->semester?->name,
                'academic_year' => $period->semester?->academicYear?->name,
                'is_locked' => (bool) $period->is_locked,
            ],
            'students' => $studentRows,
            'summary' => $counts,
        ];
    }

    /**
     * BE-GRD-03: Pratinjau data snapshot rapor siswa sebelum disimpan.
     */
    public function previewProgressReport(array $data, Request $request): array
    {
        /** @var User $currentUser */
        $currentUser = $request->user();

        if (empty($data['student_id']) || empty($data['period_id'])) {
            throw new HttpException(422, 'Parameter student_id dan period_id wajib disertakan.');
        }

        $student = Student::with('classes')->findOrFail($data['student_id']);
        $period = GradePeriod::with('semester.academicYear')->findOrFail($data['period_id']);

        // Cek otorisasi
        if (! $this->checkIsAdmin($currentUser)) {
            $teacher = Teacher::where('user_id', $currentUser->id)->first();
            if ($teacher) {
                $homeroomClassIds = SchoolClass::where('homeroom_teacher_id', $teacher->id)->pluck('id');
                $isWalas = $student->classes()->whereIn('classes.id', $homeroomClassIds)->whereNull('class_students.left_at')->exists();
                if (! $isWalas) {
                    throw new HttpException(403, 'Akses ditolak.');
                }
            } else {
                throw new HttpException(403, 'Akses ditolak.');
            }
        }

        return $this->compileSnapshot($student, $period);
    }

    /**
     * BE-GRD-03: Unduh PDF E-Progress Report 1 halaman bertanda tangan QR.
     */
    public function downloadPdf(string $id, Request $request): Response
    {
        $report = ProgressReport::with(['student.classes', 'period.semester.academicYear', 'approver'])->findOrFail($id);
        $this->authorizeAccess($report, $request);

        $pdfBinary = $this->buildPdfContent($report);

        return response($pdfBinary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="progress_report_'.$report->verify_token.'.pdf"',
            'Cache-Control' => 'no-cache, private',
        ]);
    }

    /**
     * BE-GRD-03: Verifikasi Dokumen Publik berdasarkan token QR (tanpa autentikasi).
     */
    public function verifyPublicToken(string $token): array
    {
        $report = ProgressReport::with(['student', 'period.semester.academicYear', 'approver'])
            ->where('verify_token', $token)
            ->first();

        if (! $report) {
            $reportCard = ReportCard::with(['student', 'semester.academicYear', 'approver'])
                ->where('verify_token', $token)
                ->first();

            if (! $reportCard) {
                throw new HttpException(404, 'Dokumen rapor tidak ditemukan atau token verifikasi tidak valid.');
            }

            $snapshot = $reportCard->data_snapshot ?? [];
            $studentSnapshot = $snapshot['student'] ?? [];
            $semSnapshot = $snapshot['semester'] ?? [];
            $isApprovedOrPublished = in_array($reportCard->status, ['disetujui', 'terbit']);

            return [
                'is_valid' => $isApprovedOrPublished,
                'status' => $reportCard->status,
                'verification_token' => $reportCard->verify_token,
                'document_title' => 'Laporan Hasil Belajar (E-Rapor Semester)',
                'school_name' => $snapshot['school_name'] ?? 'Portal Sekolah Satu Pintu SISTLABUM',
                'student' => [
                    'nis' => $studentSnapshot['nis'] ?? $reportCard->student?->nis,
                    'nisn' => $studentSnapshot['nisn'] ?? $reportCard->student?->nisn,
                    'name' => $studentSnapshot['full_name'] ?? $reportCard->student?->full_name,
                    'class' => $studentSnapshot['class_name'] ?? 'Rombel',
                ],
                'period' => [
                    'semester' => $semSnapshot['name'] ?? $reportCard->semester?->name,
                    'academic_year' => $semSnapshot['academic_year'] ?? $reportCard->semester?->academicYear?->name,
                ],
                'approval' => [
                    'approved_by' => $reportCard->approver?->teacher?->full_name ?? $reportCard->approver?->username ?? 'Kepala Sekolah',
                    'approved_at' => $reportCard->updated_at?->toIso8601String(),
                ],
                'message' => $isApprovedOrPublished
                    ? 'Dokumen E-Rapor Semester ini ASLI dan terverifikasi secara resmi oleh Kepala Sekolah.'
                    : 'Dokumen ini berstatus draf / belum disahkan oleh Kepala Sekolah.',
            ];
        }

        $snapshot = $report->data_snapshot ?? [];
        $studentSnapshot = $snapshot['student'] ?? [];
        $periodSnapshot = $snapshot['period'] ?? [];
        $isApprovedOrPublished = in_array($report->status, ['disetujui', 'terbit']);

        return [
            'is_valid' => $isApprovedOrPublished,
            'status' => $report->status,
            'verification_token' => $report->verify_token,
            'document_title' => 'E-Progress Report Bulanan',
            'school_name' => $snapshot['school_name'] ?? 'Portal Sekolah Satu Pintu SISTLABUM',
            'student' => [
                'nis' => $studentSnapshot['nis'] ?? $report->student?->nis,
                'nisn' => $studentSnapshot['nisn'] ?? $report->student?->nisn,
                'name' => $studentSnapshot['full_name'] ?? $report->student?->full_name,
                'class' => $studentSnapshot['class_name'] ?? 'Rombel',
            ],
            'period' => [
                'month' => $periodSnapshot['month'] ?? $report->period?->month,
                'semester' => $periodSnapshot['semester_name'] ?? $report->period?->semester?->name,
                'academic_year' => $periodSnapshot['academic_year'] ?? $report->period?->semester?->academicYear?->name,
            ],
            'approval' => [
                'approved_by' => $report->approver?->teacher?->full_name ?? $report->approver?->username ?? 'Kepala Sekolah',
                'approved_at' => $report->updated_at?->toIso8601String(),
            ],
            'message' => $isApprovedOrPublished
                ? 'Dokumen E-Progress Report ini ASLI dan terverifikasi secara resmi oleh Kepala Sekolah.'
                : 'Dokumen ini berstatus draf / belum disahkan oleh Kepala Sekolah.',
        ];
    }

    /**
     * Buat konten file PDF standar %PDF-1.4 1 halaman.
     */
    protected function buildPdfContent(ProgressReport $report): string
    {
        $snapshot = $report->data_snapshot ?? [];
        $student = $snapshot['student'] ?? [];
        $period = $snapshot['period'] ?? [];
        $att = $snapshot['attendance'] ?? [];
        $char = $snapshot['character'] ?? [];
        $grades = $snapshot['grades'] ?? [];

        $verifyUrl = url('/api/v1/public/verify/'.$report->verify_token);

        // Siapkan baris teks untuk PDF
        $lines = [
            'PORTAL SEKOLAH SATU PINTU SISTLABUM',
            '========================================================================',
            'E-PROGRESS REPORT BULANAN',
            '',
            'Nama Siswa    : '.($student['full_name'] ?? '-'),
            'NIS / NISN    : '.($student['nis'] ?? '-').' / '.($student['nisn'] ?? '-'),
            'Kelas         : '.($student['class_name'] ?? '-'),
            'Periode Bulan : '.($period['month'] ?? '-').' ('.($period['semester_name'] ?? '-').')',
            '------------------------------------------------------------------------',
            'RINGKASAN NILAI MATA PELAJARAN:',
        ];

        foreach ($grades as $g) {
            $lines[] = sprintf(' - %-30s : Skor Akhir = %s', ($g['subject_name'] ?? 'Mapel'), ($g['final_score'] ?? 0));
        }

        $lines[] = '------------------------------------------------------------------------';
        $lines[] = sprintf('PRESENSI BULANAN : Hadir %d | Sakit %d | Izin %d | Alpa %d (Kehadiran: %s%%)',
            $att['hadir'] ?? 0,
            $att['sakit'] ?? 0,
            $att['izin'] ?? 0,
            $att['alpa'] ?? 0,
            $att['percentage'] ?? 0
        );
        $lines[] = '------------------------------------------------------------------------';
        $lines[] = 'DIMENSI KARAKTER (PROFIL PELAJAR PANCASILA):';
        if (! empty($char['scale_data']) && is_array($char['scale_data'])) {
            $dimPairs = [];
            foreach ($char['scale_data'] as $dim => $val) {
                $dimLabel = ucwords(str_replace('_', ' ', $dim));
                $valLabel = is_string($val) ? ucwords(str_replace('_', ' ', $val)) : (string) $val;
                $dimPairs[] = "{$dimLabel}: {$valLabel}";
            }
            foreach (array_chunk($dimPairs, 2) as $pairChunk) {
                $lines[] = ' - '.implode(' | ', $pairChunk);
            }
        } else {
            $lines[] = ' - Belum ada catatan dimensi karakter';
        }
        $lines[] = '------------------------------------------------------------------------';
        $note = $char['homeroom_note'] ?? '-';
        $lines[] = 'CATATAN WALI KELAS:';
        $wrapped = wordwrap($note, 70, "\n");
        foreach (explode("\n", $wrapped) as $noteLine) {
            $lines[] = '   '.$noteLine;
        }
        $lines[] = '========================================================================';
        $lines[] = 'STATUS PENGESAHAN : '.strtoupper($report->status);
        $lines[] = 'KEPALA SEKOLAH    : '.($report->approver?->name ?? 'Kepala Sekolah');
        $lines[] = 'KODE VERIFIKASI QR: '.$report->verify_token;
        $lines[] = 'URL VERIFIKASI    : '.$verifyUrl;
        $lines[] = '========================================================================';

        $pagesLines = array_chunk($lines, 48);
        if (empty($pagesLines)) {
            $pagesLines = [[]];
        }
        $numPages = count($pagesLines);

        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $kids = [];
        for ($k = 0; $k < $numPages; $k++) {
            $kids[] = (4 + 2 * $k).' 0 R';
        }
        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.$numPages.' >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>';

        for ($k = 0; $k < $numPages; $k++) {
            $pageObjId = 4 + 2 * $k;
            $contentObjId = 5 + 2 * $k;

            $textStream = "BT\n/F1 10 Tf\n50 780 Td\n15 TL\n";
            foreach ($pagesLines[$k] as $line) {
                $sanitized = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line);
                $textStream .= "({$sanitized}) '\n";
            }
            $textStream .= "ET\n";
            $streamLen = strlen($textStream);

            $objects[$pageObjId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents '.$contentObjId.' 0 R /Resources << /Font << /F1 3 0 R >> >> >>';
            $objects[$contentObjId] = "<< /Length {$streamLen} >>\nstream\n{$textStream}endstream";
        }

        $pdf = "%PDF-1.4\n";
        $offsets = [0 => 0];
        $totalObjs = count($objects);
        for ($i = 1; $i <= $totalObjs; $i++) {
            $offsets[$i] = strlen($pdf);
            $pdf .= "{$i} 0 obj\n{$objects[$i]}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 ".($totalObjs + 1)."\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= $totalObjs; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size ".($totalObjs + 1)." /Root 1 0 R >>\n";
        $pdf .= "startxref\n".$xrefOffset."\n%%EOF\n";

        return $pdf;
    }

    /**
     * Cek otorisasi akses data (anti IDOR).
     */
    protected function authorizeAccess(ProgressReport $report, Request $request): void
    {
        /** @var User $currentUser */
        $currentUser = $request->user();

        if ($this->checkIsAdmin($currentUser)) {
            return;
        }

        $student = Student::where('user_id', $currentUser->id)->first();
        if ($student) {
            if ($report->student_id !== $student->id || ! in_array($report->status, ['disetujui', 'terbit'])) {
                throw new HttpException(403, 'Akses ditolak.');
            }

            return;
        }

        $guardian = Guardian::where('user_id', $currentUser->id)->first();
        if ($guardian) {
            $childIds = $guardian->students()->pluck('students.id')->all();
            if (! in_array($report->student_id, $childIds) || ! in_array($report->status, ['disetujui', 'terbit'])) {
                throw new HttpException(403, 'Akses ditolak.');
            }

            return;
        }

        $teacher = Teacher::where('user_id', $currentUser->id)->first();
        if ($teacher) {
            $homeroomClassIds = SchoolClass::where('homeroom_teacher_id', $teacher->id)->pluck('id');
            $isInClass = $report->student?->classes()->whereIn('classes.id', $homeroomClassIds)->exists();
            if ($isInClass) {
                return;
            }
        }

        throw new HttpException(403, 'Akses ditolak.');
    }

    /**
     * Cek apakah pengguna berwenang men-generate e-progress report.
     */
    protected function canGenerate(User $user, array $data): bool
    {
        if ($this->checkIsAdmin($user)) {
            return true;
        }

        $teacher = Teacher::where('user_id', $user->id)->first();
        if ($teacher) {
            // Walas harus merupakan wali kelas dari kelas / siswa target
            if (! empty($data['class_id'])) {
                return SchoolClass::where('id', $data['class_id'])
                    ->where('homeroom_teacher_id', $teacher->id)
                    ->exists();
            }

            if (! empty($data['student_id'])) {
                $homeroomClassIds = SchoolClass::where('homeroom_teacher_id', $teacher->id)->pluck('id');

                return Student::where('id', $data['student_id'])
                    ->whereHas('classes', fn ($q) => $q->whereIn('classes.id', $homeroomClassIds)->whereNull('class_students.left_at'))
                    ->exists();
            }

            return false;
        }

        return false;
    }

    /**
     * Cek apakah pengguna berwenang menyetujui e-progress report.
     */
    protected function canApprove(User $user): bool
    {
        return $user->hasRole('principal')
            || $user->hasRole('super_admin')
            || $user->hasPermission('academic.report.generate')
            || $user->roles()->where('code', 'like', 'admin%')->exists();
    }

    /**
     * Cek apakah pengguna berstatus admin / kepsek.
     */
    protected function checkIsAdmin(User $user): bool
    {
        return $user->hasRole('super_admin')
            || $user->hasRole('principal')
            || $user->hasPermission('academic.report.generate')
            || $user->roles()->where('code', 'like', 'admin%')->exists();
    }

    /**
     * Log audit trail.
     */
    protected function logAudit(
        ?string $actorId,
        string $action,
        string $entity,
        ?string $entityId,
        ?array $oldValue,
        ?array $newValue,
        Request $request
    ): void {
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
            report($e);
        }
    }
}
