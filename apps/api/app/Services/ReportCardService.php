<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\CharacterNote;
use App\Models\ClassStudent;
use App\Models\ExtracurricularMember;
use App\Models\File;
use App\Models\Grade;
use App\Models\GradePeriod;
use App\Models\Guardian;
use App\Models\ReportCard;
use App\Models\ReportDescriptionTemplate;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use App\Services\Academic\AttendanceAggregator;
use App\Services\Academic\ReportPdfRenderer;
use App\Support\PaginationHelper;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ReportCardService
{
    /**
     * BE-GRD-04: Daftar template deskripsi capaian rapor.
     */
    public function listTemplates(array $filters): LengthAwarePaginator
    {
        $query = ReportDescriptionTemplate::with('subject')
            ->orderBy('created_at', 'desc');

        if (! empty($filters['subject_id'])) {
            $query->where('subject_id', $filters['subject_id']);
        }

        if (! empty($filters['level'])) {
            $query->where('level', $filters['level']);
        }

        return $query->paginate(PaginationHelper::clamp($filters['per_page'] ?? null, 15));
    }

    /**
     * BE-GRD-04: Simpan atau perbarui template deskripsi capaian.
     */
    public function storeTemplate(array $data, Request $request): ReportDescriptionTemplate
    {
        $template = ReportDescriptionTemplate::updateOrCreate(
            [
                'subject_id' => $data['subject_id'],
                'level' => $data['level'],
            ],
            [
                'kktp_threshold' => $data['kktp_threshold'] ?? null,
                'min_score' => $data['min_score'] ?? null,
                'max_score' => $data['max_score'] ?? null,
                'template_text' => $data['template_text'],
            ]
        );

        if (isset($data['kktp_threshold']) && is_numeric($data['kktp_threshold'])) {
            Subject::where('id', $data['subject_id'])->update([
                'kktp' => (float) $data['kktp_threshold'],
            ]);
        }

        $this->logAudit(
            $request->user()?->id,
            'report_template.save',
            'report_description_templates',
            $template->id,
            null,
            $data,
            $request
        );

        return $template->fresh('subject');
    }

    /**
     * BE-GRD-04: Hapus template deskripsi capaian.
     */
    public function deleteTemplate(string $id, Request $request): void
    {
        $template = ReportDescriptionTemplate::findOrFail($id);
        $template->delete();

        $this->logAudit(
            $request->user()?->id,
            'report_template.delete',
            'report_description_templates',
            $id,
            ['subject_id' => $template->subject_id, 'level' => $template->level],
            null,
            $request
        );
    }

    /**
     * BE-GRD-04: Generate E-Rapor semesteran per siswa atau rombel.
     */
    public function generateReportCards(array $data, Request $request): array
    {
        /** @var User $currentUser */
        $currentUser = $request->user();

        if (! $this->canGenerate($currentUser)) {
            throw new HttpException(403, 'Anda tidak memiliki hak akses untuk men-generate e-rapor semester.');
        }

        $semester = Semester::with('academicYear')->findOrFail($data['semester_id']);

        if (! $this->checkIsAdmin($currentUser)) {
            $teacher = Teacher::where('user_id', $currentUser->id)->first();
            if (! $teacher) {
                throw new HttpException(403, 'Akses ditolak.');
            }
            if (! empty($data['student_id'])) {
                $student = Student::with('classes')->findOrFail($data['student_id']);
                $homeroomClassIds = SchoolClass::where('homeroom_teacher_id', $teacher->id)->pluck('id');
                $isWalas = $student->classes()->whereIn('classes.id', $homeroomClassIds)->whereNull('class_students.left_at')->exists();
                if (! $isWalas) {
                    throw new HttpException(403, 'Anda bukan wali kelas dari siswa ini.');
                }
            } elseif (! empty($data['class_id'])) {
                $targetClass = SchoolClass::findOrFail($data['class_id']);
                if ($targetClass->homeroom_teacher_id !== $teacher->id) {
                    throw new HttpException(403, 'Anda bukan wali kelas dari rombel ini.');
                }
            }
        }

        $students = collect();
        if (! empty($data['student_id'])) {
            $student = Student::with('activeClasses')->findOrFail($data['student_id']);
            $students->push($student);
        } elseif (! empty($data['class_id'])) {
            $studentIds = ClassStudent::where('class_id', $data['class_id'])
                ->whereNull('left_at')
                ->pluck('student_id');

            $students = Student::with('activeClasses')
                ->whereIn('id', $studentIds)
                ->get();
        }

        if ($students->isEmpty()) {
            throw new HttpException(422, 'Tidak ada siswa yang ditemukan untuk diproses.');
        }

        $statusToSet = $data['status'] ?? 'draft';

        if ($statusToSet === 'terbit') {
            if (! $this->checkIsAdmin($currentUser)) {
                $hasOpenPeriods = GradePeriod::where('semester_id', $semester->id)
                    ->where('status', '!=', 'locked')
                    ->exists();

                if ($hasOpenPeriods) {
                    throw new HttpException(422, 'E-Rapor semester hanya dapat diterbitkan bila periode penilaian telah dikunci (locked).');
                }
            }
        }

        $results = [];
        $allStudentIds = $students->pluck('id')->all();

        $templatesBySubject = ReportDescriptionTemplate::all()->groupBy('subject_id');

        $gradesByStudent = Grade::with(['component.subject'])
            ->whereIn('student_id', $allStudentIds)
            ->where(function ($q) use ($semester) {
                $q->whereHas('period', fn ($p) => $p->where('semester_id', $semester->id))
                    ->orWhere(function ($sub) use ($semester) {
                        $sub->whereNull('period_id')
                            ->whereHas('component', fn ($c) => $c->where('semester_id', $semester->id));
                    });
            })
            ->get()
            ->groupBy('student_id');

        $attQueryAll = Attendance::whereIn('student_id', $allStudentIds);
        if ($semester->start_date && $semester->end_date) {
            $attQueryAll->whereBetween('att_date', [$semester->start_date, $semester->end_date]);
        }
        $attendancesByStudent = $attQueryAll->get()->groupBy('student_id');

        $extracurricularsByStudent = ExtracurricularMember::with('extracurricular')
            ->whereIn('student_id', $allStudentIds)
            ->whereIn('status', ['aktif', 'active'])
            ->when($semester->end_date, fn ($q) => $q->where(function ($sub) use ($semester) {
                $sub->whereNull('joined_at')->orWhereDate('joined_at', '<=', $semester->end_date);
            }))
            ->get()
            ->groupBy('student_id');

        $charNotesByStudent = CharacterNote::whereIn('student_id', $allStudentIds)
            ->whereHas('period', fn ($p) => $p->where('semester_id', $semester->id))
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('student_id')
            ->map(fn ($notes) => $notes->first());

        $attendanceAggregator = app(AttendanceAggregator::class);

        DB::transaction(function () use (
            $students,
            $allStudentIds,
            $semester,
            $statusToSet,
            $data,
            &$results,
            $request,
            $currentUser,
            $templatesBySubject,
            $gradesByStudent,
            $attendancesByStudent,
            $extracurricularsByStudent,
            $charNotesByStudent,
            $attendanceAggregator
        ) {
            $existingReports = ReportCard::whereIn('student_id', $allStudentIds)
                ->where('semester_id', $semester->id)
                ->lockForUpdate()
                ->get()
                ->keyBy('student_id');

            foreach ($students as $student) {
                $existingReport = $existingReports->get($student->id);

                if ($existingReport && in_array($existingReport->status, ['terkunci', 'disetujui', 'terbit'])) {
                    if ($students->count() === 1) {
                        throw new HttpException(422, "E-Rapor semester siswa {$student->full_name} sedang terkunci atau telah disahkan dan tidak dapat diperbarui secara langsung.");
                    }
                    $results[] = $existingReport;

                    continue;
                }

                $snapshot = $this->compileSemesterSnapshot($student, $semester, $data['homeroom_note'] ?? null, [
                    'templates_by_subject' => $templatesBySubject,
                    'grades' => $gradesByStudent->get($student->id, collect()),
                    'attendances' => $attendancesByStudent->get($student->id, collect()),
                    'extracurriculars' => $extracurricularsByStudent->get($student->id, collect()),
                    'latest_char_note' => $charNotesByStudent->get($student->id),
                    'attendance_aggregator' => $attendanceAggregator,
                ]);

                $report = $existingReport ?? new ReportCard;
                $isNew = ! $report->exists;

                $report->student_id = $student->id;
                $report->semester_id = $semester->id;
                $report->data_snapshot = $snapshot;
                $report->status = $statusToSet;
                $report->file_id = null;

                if (! $report->verify_token) {
                    $report->verify_token = hash('sha256', Str::uuid()->toString().microtime(true));
                }

                $report->save();

                $this->logAudit(
                    $currentUser->id,
                    $isNew ? 'report_card.create' : 'report_card.regenerate',
                    'report_cards',
                    $report->id,
                    $isNew ? null : ['status' => $existingReport->status],
                    ['status' => $report->status, 'student_id' => $student->id, 'semester_id' => $semester->id],
                    $request
                );

                $report->setRelation('student', $student);
                $report->setRelation('semester', $semester);
                if ($report->approved_by) {
                    $report->loadMissing('approver');
                } else {
                    $report->setRelation('approver', null);
                }

                $results[] = $report;
            }
        });

        return $results;
    }

    /**
     * BE-GRD-04: Kompilasi data snapshot semesteran resmi.
     */
    public function compileSemesterSnapshot(Student $student, Semester $semester, ?string $manualNote = null, array $preloaded = []): array
    {
        if ($student->relationLoaded('activeClasses')) {
            $activeClass = $student->activeClasses->first();
        } elseif ($student->relationLoaded('classes')) {
            $activeClass = $student->classes->first(fn ($c) => $c->pivot?->left_at === null);
        } else {
            $activeClass = $student->activeClasses()->first();
        }

        // 1. Identitas Siswa & Rombel
        $studentData = [
            'id' => $student->id,
            'nis' => $student->nis,
            'nisn' => $student->nisn,
            'full_name' => $student->full_name,
            'gender' => $student->gender,
            'class_id' => $activeClass?->id,
            'class_name' => $activeClass?->name ?? 'Belum ada kelas',
        ];

        // 2. Metadata Semester & Tahun Ajaran
        $semesterData = [
            'id' => $semester->id,
            'name' => $semester->name,
            'academic_year' => $semester->academicYear?->name ?? '2026/2027',
            'start_date' => $semester->start_date?->toDateString(),
            'end_date' => $semester->end_date?->toDateString(),
        ];

        // 3. Nilai Akhir & Deskripsi Capaian per Mapel
        // P0-14: Cegah kebocoran nilai lintas semester (hanya fallback ke component.semester_id bila period_id NULL)
        $grades = $preloaded['grades'] ?? Grade::with(['component.subject'])
            ->where('student_id', $student->id)
            ->where(function ($q) use ($semester) {
                $q->whereHas('period', fn ($p) => $p->where('semester_id', $semester->id))
                    ->orWhere(function ($sub) use ($semester) {
                        $sub->whereNull('period_id')
                            ->whereHas('component', fn ($c) => $c->where('semester_id', $semester->id));
                    });
            })
            ->get();

        $groupedGrades = $grades->groupBy(fn ($g) => $g->component?->subject_id ?? 'no_subject');
        $templatesBySubject = $preloaded['templates_by_subject'] ?? null;
        if ($templatesBySubject === null) {
            $subjectIds = $groupedGrades->keys()->filter(fn ($k) => $k !== 'no_subject')->values()->all();
            $templatesBySubject = empty($subjectIds)
                ? collect()
                : ReportDescriptionTemplate::whereIn('subject_id', $subjectIds)
                    ->get()
                    ->groupBy('subject_id');
        }

        $subjectsSummary = [];
        $predicateLabels = [
            'sangat_baik' => 'Sangat Baik',
            'baik' => 'Baik',
            'cukup' => 'Cukup',
            'perlu_bimbingan' => 'Perlu Bimbingan',
        ];

        foreach ($groupedGrades as $subjectId => $subjGrades) {
            $first = $subjGrades->first();
            $subject = $first->component?->subject;
            if (! $subject) {
                continue;
            }

            // P0-14: Rata-ratakan dulu nilai lintas bulan per component_id, lalu bobotkan setiap komponen TEPAT SATU KALI
            // (Tidak mengubah kebijakan GradeRecorder max(manual, auto) per sel periode!)
            $byComponent = $subjGrades->groupBy('component_id');
            $totalWeighted = 0.0;
            $totalWeight = 0.0;
            $componentStats = [];

            foreach ($byComponent as $compGrades) {
                $firstCompGrade = $compGrades->first();
                $comp = $firstCompGrade->component;
                $weight = (float) ($comp?->weight ?? 1.0);
                $componentAvg = (float) $compGrades->avg('value');
                $totalWeighted += ($componentAvg * $weight);
                $totalWeight += $weight;
                $componentStats[] = [
                    'name' => $comp?->name ?? $comp?->code ?? 'Kompetensi Dasar',
                    'avg' => $componentAvg,
                ];
            }

            usort($componentStats, fn ($a, $b) => $b['avg'] <=> $a['avg']);
            $tpTertinggi = $componentStats[0]['name'] ?? $subject->name;
            $tpTerendah = ! empty($componentStats)
                ? $componentStats[count($componentStats) - 1]['name']
                : $subject->name;

            $finalScore = $totalWeight > 0
                ? round($totalWeighted / $totalWeight, 2)
                : round((float) $subjGrades->avg('value'), 2);

            $subjectTemplates = $templatesBySubject->get($subject->id, collect());
            $kktpTemplateVal = $subjectTemplates->first(fn ($t) => $t->kktp_threshold !== null)?->kktp_threshold;
            $kktp = (float) ($subject->kktp ?? $kktpTemplateVal ?? 75.0);

            // P1-15: Klasifikasi 4 rentang Kurikulum Merdeka + tetap pertahankan 'level' biner tuntas/belum
            $predicateLevel = match (true) {
                $finalScore >= 90.0 => 'sangat_baik',
                $finalScore >= 80.0 => 'baik',
                $finalScore >= $kktp => 'cukup',
                default => 'perlu_bimbingan',
            };
            $predicateLabel = $predicateLabels[$predicateLevel];
            $binaryLevel = $finalScore >= $kktp ? 'tuntas' : 'belum';

            // Cari template berdasarkan rentang skor -> level 4 tingkat -> level biner tuntas/belum
            $template = $subjectTemplates->first(function ($t) use ($finalScore) {
                return $t->min_score !== null && $t->max_score !== null
                    && $finalScore >= (float) $t->min_score
                    && $finalScore <= (float) $t->max_score;
            })
                ?? $subjectTemplates->firstWhere('level', $predicateLevel)
                ?? $subjectTemplates->firstWhere('level', $binaryLevel);

            $descriptionText = $template?->template_text;
            if (! $descriptionText) {
                $descriptionText = match ($predicateLevel) {
                    'sangat_baik' => "Ananda {$student->full_name} menunjukkan penguasaan yang sangat baik pada {$subject->name}, khususnya dalam {$tpTertinggi}.",
                    'baik' => "Ananda {$student->full_name} menunjukkan penguasaan yang baik pada {$subject->name} dalam {$tpTertinggi}, serta terus kembangkan pemahaman pada {$tpTerendah}.",
                    'cukup' => "Ananda {$student->full_name} cukup menguasai kompetensi {$subject->name} pada {$tpTertinggi}, namun perlu peningkatan pada {$tpTerendah}.",
                    default => "Ananda {$student->full_name} perlu bimbingan dan pendampingan lebih lanjut dalam {$subject->name}, terutama pada {$tpTerendah}.",
                };
            } else {
                $descriptionText = strtr($descriptionText, [
                    '{nama_siswa}' => $student->full_name,
                    '{mata_pelajaran}' => $subject->name,
                    '{nilai_akhir}' => (string) $finalScore,
                    '{kktp}' => (string) $kktp,
                    '{predikat}' => $predicateLabel,
                    '{tp_tertinggi}' => $tpTertinggi,
                    '{tp_terendah}' => $tpTerendah,
                ]);
            }

            $subjectsSummary[] = [
                'subject_id' => $subject->id,
                'subject_code' => $subject->code,
                'subject_name' => $subject->name,
                'subject_group' => $subject->subject_group ?? 'Kelompok A',
                'final_score' => $finalScore,
                'kktp' => $kktp,
                'level' => $binaryLevel,
                'predicate_level' => $predicateLevel,
                'predicate_label' => $predicateLabel,
                'highest_topic' => $tpTertinggi,
                'lowest_topic' => $tpTerendah,
                'competency_description' => $descriptionText,
            ];
        }

        // 4. Rekap Absensi Semester (P0-26: agregasi harian tunggal gerbang + kelas, termasuk terlambat)
        if (array_key_exists('attendances', $preloaded)) {
            $attendances = $preloaded['attendances'];
        } else {
            $attQuery = Attendance::where('student_id', $student->id);
            if ($semester->start_date && $semester->end_date) {
                $attQuery->whereBetween('att_date', [$semester->start_date, $semester->end_date]);
            }
            $attendances = $attQuery->get();
        }
        $attendanceSnapshot = ($preloaded['attendance_aggregator'] ?? app(AttendanceAggregator::class))->summarizeDaily($attendances, 2);

        // 5. Ekstrakurikuler Siswa (P1-16: hanya keanggotaan aktif dalam rentang semester)
        $extracurricularModels = $preloaded['extracurriculars'] ?? ExtracurricularMember::with('extracurricular')
            ->where('student_id', $student->id)
            ->whereIn('status', ['aktif', 'active'])
            ->when($semester->end_date, fn ($q) => $q->where(function ($sub) use ($semester) {
                $sub->whereNull('joined_at')->orWhereDate('joined_at', '<=', $semester->end_date);
            }))
            ->get();
        $extracurriculars = $extracurricularModels
            ->map(fn ($em) => [
                'name' => $em->extracurricular?->name ?? 'Ekstrakurikuler',
                'predicate' => $em->final_grade ?? 'A',
                'status' => $em->status ?? 'aktif',
                'description' => 'Aktif berpartisipasi dalam kegiatan ekstrakurikuler.',
            ])
            ->values()
            ->all();

        // 6. Catatan Wali Kelas
        $latestCharNote = array_key_exists('latest_char_note', $preloaded)
            ? $preloaded['latest_char_note']
            : CharacterNote::where('student_id', $student->id)
                ->whereHas('period', fn ($p) => $p->where('semester_id', $semester->id))
                ->orderBy('created_at', 'desc')
                ->first();

        $homeroomNote = $manualNote ?: ($latestCharNote?->homeroom_note ?: 'Tingkatkan terus prestasi belajar dan pertahankan akhlak mulia.');

        $siteProfile = app(SiteSettingService::class)->getSiteProfile();
        $schoolName = $siteProfile['identity']['name'] ?? 'SMP Laboratorium UM';

        return [
            'school_name' => $schoolName,
            'student' => $studentData,
            'semester' => $semesterData,
            'subjects' => $subjectsSummary,
            'attendance' => $attendanceSnapshot,
            'extracurriculars' => $extracurriculars,
            'homeroom_note' => $homeroomNote,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * BE-GRD-04: Daftar E-Rapor semester dengan filter & otorisasi IDOR.
     */
    public function listReportCards(array $filters, Request $request): LengthAwarePaginator
    {
        /** @var User $currentUser */
        $currentUser = $request->user();
        $student = Student::where('user_id', $currentUser->id)->first();
        $guardian = Guardian::where('user_id', $currentUser->id)->first();
        $teacher = Teacher::where('user_id', $currentUser->id)->first();
        $isAdmin = $this->checkIsAdmin($currentUser);

        $query = ReportCard::with(['student.classes', 'semester.academicYear', 'approver'])
            ->orderBy('created_at', 'desc');

        if (! $isAdmin) {
            if ($student) {
                $query->where('student_id', $student->id)
                    ->whereIn('status', ['disetujui', 'terbit']);
            } elseif ($guardian) {
                $childIds = $guardian->students()->pluck('students.id');
                $query->whereIn('student_id', $childIds)
                    ->whereIn('status', ['disetujui', 'terbit']);
            } elseif ($teacher) {
                $homeroomClassIds = SchoolClass::where('homeroom_teacher_id', $teacher->id)->pluck('id');
                $query->whereHas('student.classes', fn ($q) => $q->whereIn('classes.id', $homeroomClassIds));
            } else {
                throw new HttpException(403, 'Akses ditolak.');
            }
        }

        if (! empty($filters['student_id'])) {
            $query->where('student_id', $filters['student_id']);
        }

        if (! empty($filters['semester_id'])) {
            $query->where('semester_id', $filters['semester_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['class_id'])) {
            $classId = $filters['class_id'];
            $query->whereHas('student.classes', fn ($q) => $q->where('classes.id', $classId));
        }

        return $query->paginate(PaginationHelper::clamp($filters['per_page'] ?? null, 15));
    }

    /**
     * BE-GRD-04: Detail E-Rapor semester dengan otorisasi IDOR.
     */
    public function getReportCard(string $id, Request $request): ReportCard
    {
        $report = ReportCard::with(['student.classes', 'semester.academicYear', 'approver'])->findOrFail($id);
        $this->authorizeAccess($report, $request);

        return $report;
    }

    /**
     * BE-GRD-04: Kunci / buka kunci E-Rapor semester oleh Admin Kurikulum.
     */
    public function lockReportCard(string $id, array $data, Request $request): ReportCard
    {
        /** @var User $currentUser */
        $currentUser = $request->user();

        if (! $this->checkIsAdmin($currentUser)) {
            throw new HttpException(403, 'Hanya Administrator atau Admin Kurikulum yang berwenang mengunci e-rapor.');
        }

        return DB::transaction(function () use ($id, $data, $request) {
            $report = ReportCard::lockForUpdate()->findOrFail($id);
            $oldStatus = $report->status;

            // P1-16: Lindungi status disetujui/terbit agar tidak turun ke terkunci/draft
            if (in_array($oldStatus, ['disetujui', 'terbit'], true)) {
                throw new HttpException(422, 'E-Rapor yang telah disetujui atau diterbitkan tidak dapat dikunci atau dikembalikan menjadi draf.');
            }

            $isLocked = (bool) $data['is_locked'];

            if ($isLocked) {
                $report->status = 'terkunci';
                $report->locked_at = now();
            } else {
                $report->status = 'draft';
                $report->locked_at = null;
            }

            $report->save();

            $action = $isLocked ? 'academic.report_card.lock' : 'academic.report_card.unlock';

            app(AuditLogService::class)->record(
                action: $action,
                entity: 'ReportCard',
                entityId: $report->id,
                oldValues: ['status' => $oldStatus],
                newValues: ['status' => $report->status, 'locked_at' => $report->locked_at],
                reason: $data['reason'] ?? null,
                request: $request,
                isAuthoritative: true
            );

            return $report->fresh(['student', 'semester.academicYear', 'approver']);
        });
    }

    /**
     * BE-GRD-04: Pengesahan Kepala Sekolah atas E-Rapor semester.
     */
    public function approveReportCard(string $id, array $data, Request $request): ReportCard
    {
        /** @var User $currentUser */
        $currentUser = $request->user();

        $report = ReportCard::with(['student.classes'])->findOrFail($id);

        if (! $this->canApprove($currentUser, $report)) {
            throw new HttpException(403, 'Hanya Kepala Sekolah, Administrator, atau Wali Kelas terkait yang berwenang mengesahkan atau menerbitkan e-rapor.');
        }

        $oldStatus = $report->status;
        $newStatus = $data['status'];

        if ($newStatus === 'terbit') {
            if ($oldStatus === 'terbit') {
                throw new HttpException(422, 'E-Rapor semester telah diterbitkan dan tidak dapat diterbitkan kembali.');
            }

            if (! $this->checkIsAdmin($currentUser)) {
                $hasOpenPeriods = GradePeriod::where('semester_id', $report->semester_id)
                    ->where('status', '!=', 'locked')
                    ->exists();

                if ($hasOpenPeriods) {
                    throw new HttpException(422, 'E-Rapor semester hanya dapat diterbitkan bila periode penilaian telah dikunci (locked).');
                }
            }
        }

        return DB::transaction(function () use ($report, $oldStatus, $newStatus, $data, $currentUser, $request) {
            $report->status = $newStatus;
            $report->file_id = null;
            if (in_array($newStatus, ['disetujui', 'terbit'])) {
                $report->approved_by = $currentUser->id;
            } else {
                $report->approved_by = null;
            }

            $report->save();

            $reason = $data['note'] ?? $data['reason'] ?? null;

            app(AuditLogService::class)->record(
                action: 'academic.report_card.approve',
                entity: 'ReportCard',
                entityId: $report->id,
                oldValues: ['status' => $oldStatus],
                newValues: ['status' => $newStatus, 'approved_by' => $report->approved_by, 'note' => $reason],
                reason: $reason,
                request: $request,
                isAuthoritative: true
            );

            return $report->fresh(['student', 'semester.academicYear', 'approver']);
        });
    }

    /**
     * BE-GRD-04: Pengesahan massal E-Rapor semester oleh Kepala Sekolah atau Admin.
     */
    public function bulkApprove(array $data, Request $request): array
    {
        /** @var User $currentUser */
        $currentUser = $request->user();

        if (! $this->canBulkApprove($currentUser)) {
            throw new HttpException(403, 'Hanya Kepala Sekolah atau Administrator yang berwenang mengesahkan e-rapor secara massal.');
        }

        $newStatus = $data['status'];
        $query = ReportCard::with(['student.classes']);

        if (! empty($data['report_ids'])) {
            $query->whereIn('id', $data['report_ids']);
        } elseif (! empty($data['class_id']) && ! empty($data['semester_id'])) {
            $classId = $data['class_id'];
            $semesterId = $data['semester_id'];
            $query->where('semester_id', $semesterId)
                ->whereHas('student.classes', fn ($q) => $q->where('classes.id', $classId)->whereNull('class_students.left_at'));
        } else {
            throw new HttpException(422, 'Wajib menyertakan report_ids atau kombinasi class_id dan semester_id.');
        }

        $reports = $query->get();

        if ($reports->isEmpty()) {
            return [
                'count' => 0,
                'status' => $newStatus,
                'items' => [],
            ];
        }

        foreach ($reports as $report) {
            if (! $this->canApprove($currentUser, $report)) {
                throw new HttpException(403, 'Anda tidak berwenang mengesahkan satu atau lebih e-rapor.');
            }

            if ($newStatus === 'terbit') {
                if ($report->status === 'terbit') {
                    throw new HttpException(422, "E-Rapor siswa {$report->student?->full_name} sudah diterbitkan sebelumnya.");
                }

                if (! $this->checkIsAdmin($currentUser)) {
                    $hasOpenPeriods = GradePeriod::where('semester_id', $report->semester_id)
                        ->where('status', '!=', 'locked')
                        ->exists();

                    if ($hasOpenPeriods) {
                        throw new HttpException(422, 'E-Rapor semester hanya dapat diterbitkan bila periode penilaian telah dikunci (locked).');
                    }
                }
            }
        }

        $updatedCount = 0;
        $updatedItems = [];

        DB::transaction(function () use ($reports, $newStatus, $currentUser, $request, $data, &$updatedCount, &$updatedItems) {
            foreach ($reports as $report) {
                $oldStatus = $report->status;
                $report->status = $newStatus;
                $report->file_id = null;

                if (in_array($newStatus, ['disetujui', 'terbit'])) {
                    $report->approved_by = $currentUser->id;
                } else {
                    $report->approved_by = null;
                }

                $report->save();
                $updatedCount++;

                $reason = $data['note'] ?? $data['reason'] ?? null;
                app(AuditLogService::class)->record(
                    action: 'academic.report_card.bulk_approve',
                    entity: 'ReportCard',
                    entityId: $report->id,
                    oldValues: ['status' => $oldStatus],
                    newValues: ['status' => $newStatus, 'approved_by' => $report->approved_by, 'note' => $reason],
                    reason: $reason,
                    request: $request,
                    isAuthoritative: true
                );

                $updatedItems[] = $report->fresh(['student', 'semester.academicYear', 'approver']);
            }
        });

        return [
            'count' => $updatedCount,
            'status' => $newStatus,
            'items' => $updatedItems,
        ];
    }

    /**
     * BE-GRD-04: Penguncian massal E-Rapor semester oleh Admin Kurikulum.
     */
    public function bulkLock(array $data, Request $request): array
    {
        /** @var User $currentUser */
        $currentUser = $request->user();

        if (! $this->checkIsAdmin($currentUser)) {
            throw new HttpException(403, 'Hanya Administrator atau Admin Kurikulum yang berwenang mengunci e-rapor.');
        }

        $isLocked = (bool) $data['is_locked'];
        $query = ReportCard::query();

        if (! empty($data['report_ids'])) {
            $query->whereIn('id', $data['report_ids']);
        } elseif (! empty($data['class_id']) && ! empty($data['semester_id'])) {
            $classId = $data['class_id'];
            $semesterId = $data['semester_id'];
            $query->where('semester_id', $semesterId)
                ->whereHas('student.classes', fn ($q) => $q->where('classes.id', $classId)->whereNull('class_students.left_at'));
        } else {
            throw new HttpException(422, 'Wajib menyertakan report_ids atau kombinasi class_id dan semester_id.');
        }

        $reports = $query->get();

        if ($reports->isEmpty()) {
            return [
                'count' => 0,
                'skipped_published_count' => 0,
                'skipped_ids' => [],
                'is_locked' => $isLocked,
                'items' => [],
            ];
        }

        $updatedCount = 0;
        $skippedPublishedCount = 0;
        $skippedIds = [];
        $updatedItems = [];

        DB::transaction(function () use ($reports, $isLocked, $currentUser, $request, $data, &$updatedCount, &$skippedPublishedCount, &$skippedIds, &$updatedItems) {
            foreach ($reports as $report) {
                $oldStatus = $report->status;
                if (in_array($oldStatus, ['disetujui', 'terbit'], true)) {
                    $skippedPublishedCount++;
                    $skippedIds[] = $report->id;

                    continue;
                }

                if ($isLocked) {
                    $report->status = 'terkunci';
                    $report->locked_at = now();
                } else {
                    $report->status = 'draft';
                    $report->locked_at = null;
                }

                $report->save();
                $updatedCount++;

                $this->logAudit(
                    $currentUser->id,
                    $isLocked ? 'report_card.bulk_lock' : 'report_card.bulk_unlock',
                    'report_cards',
                    $report->id,
                    ['status' => $oldStatus],
                    ['status' => $report->status, 'locked_at' => $report->locked_at, 'reason' => $data['reason'] ?? null],
                    $request
                );

                $updatedItems[] = $report->fresh(['student', 'semester.academicYear', 'approver']);
            }
        });

        return [
            'count' => $updatedCount,
            'skipped_published_count' => $skippedPublishedCount,
            'skipped_ids' => $skippedIds,
            'is_locked' => $isLocked,
            'items' => $updatedItems,
        ];
    }

    /**
     * BE-GRD-04: Tinjauan status & kesiapan data e-rapor semester satu kelas/rombel.
     */
    public function getClassReportCardsStatus(array $filters, Request $request): array
    {
        /** @var User $currentUser */
        $currentUser = $request->user();

        if (empty($filters['class_id']) || empty($filters['semester_id'])) {
            throw new HttpException(422, 'Parameter class_id dan semester_id wajib disertakan.');
        }

        $class = SchoolClass::with('homeroomTeacher')->findOrFail($filters['class_id']);
        $semester = Semester::with('academicYear')->findOrFail($filters['semester_id']);

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

        // Existing report cards
        $existingReports = ReportCard::with('approver')
            ->whereIn('student_id', $studentIds)
            ->where('semester_id', $semester->id)
            ->get()
            ->keyBy('student_id');

        // Nilai semester per siswa (P0-14: bebas kebocoran lintas semester)
        $gradesCount = Grade::whereIn('student_id', $studentIds)
            ->where(function ($q) use ($semester) {
                $q->whereHas('period', fn ($p) => $p->where('semester_id', $semester->id))
                    ->orWhere(function ($sub) use ($semester) {
                        $sub->whereNull('period_id')
                            ->whereHas('component', fn ($c) => $c->where('semester_id', $semester->id));
                    });
            })
            ->select('student_id', DB::raw('count(distinct component_id) as comp_count'))
            ->groupBy('student_id')
            ->pluck('comp_count', 'student_id');

        // Presensi semester per siswa (P0-26: hitung hari unik att_date)
        $attendanceQuery = Attendance::whereIn('student_id', $studentIds);
        if ($semester->start_date && $semester->end_date) {
            $attendanceQuery->whereBetween('att_date', [$semester->start_date, $semester->end_date]);
        }
        $attendanceCount = $attendanceQuery
            ->select('student_id', DB::raw('count(distinct att_date) as att_count'))
            ->groupBy('student_id')
            ->pluck('att_count', 'student_id');

        // Catatan karakter semester
        $charNotesCount = CharacterNote::whereIn('student_id', $studentIds)
            ->whereHas('period', fn ($p) => $p->where('semester_id', $semester->id))
            ->select('student_id', DB::raw('count(*) as note_count'))
            ->groupBy('student_id')
            ->pluck('note_count', 'student_id');

        // Ekstrakurikuler siswa
        $ekskulCount = ExtracurricularMember::whereIn('student_id', $studentIds)
            ->select('student_id', DB::raw('count(*) as ekskul_count'))
            ->groupBy('student_id')
            ->pluck('ekskul_count', 'student_id');

        $studentRows = [];
        $counts = [
            'total_students' => $students->count(),
            'generated_count' => 0,
            'draft_count' => 0,
            'terkunci_count' => 0,
            'disetujui_count' => 0,
            'terbit_count' => 0,
            'belum_dibuat_count' => 0,
            'ready_to_generate_count' => 0,
        ];

        foreach ($students as $stu) {
            /** @var ReportCard|null $rep */
            $rep = $existingReports->get($stu->id);
            $hasGrades = ($gradesCount->get($stu->id, 0) > 0);
            $attDays = (int) $attendanceCount->get($stu->id, 0);
            $hasAttendance = ($attDays > 0);
            $hasCharNote = ($charNotesCount->get($stu->id, 0) > 0);
            $hasEkskul = ($ekskulCount->get($stu->id, 0) > 0);

            $isReady = $hasGrades;
            if ($isReady) {
                $counts['ready_to_generate_count']++;
            }

            if ($rep) {
                $counts['generated_count']++;
                match ($rep->status) {
                    'draft' => $counts['draft_count']++,
                    'terkunci' => $counts['terkunci_count']++,
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
                    'locked_at' => $rep->locked_at?->toIso8601String(),
                    'created_at' => $rep->created_at?->toIso8601String(),
                    'updated_at' => $rep->updated_at?->toIso8601String(),
                    'approved_by' => $rep->approved_by,
                    'approver_name' => $rep->approver?->name ?? $rep->approver?->username,
                ] : null,
                'data_readiness' => [
                    'has_grades' => $hasGrades,
                    'graded_subjects_count' => (int) $gradesCount->get($stu->id, 0),
                    'has_attendance' => $hasAttendance,
                    'attendance_days_count' => $attDays,
                    'has_character_note' => $hasCharNote,
                    'has_extracurricular' => $hasEkskul,
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
            'semester' => [
                'id' => $semester->id,
                'name' => $semester->name,
                'academic_year' => $semester->academicYear?->name,
                'is_active' => (bool) $semester->is_active,
            ],
            'students' => $studentRows,
            'summary' => $counts,
        ];
    }

    /**
     * BE-GRD-04: Pratinjau data snapshot rapor semester siswa sebelum disimpan.
     */
    public function previewReportCard(array $data, Request $request): array
    {
        /** @var User $currentUser */
        $currentUser = $request->user();

        if (empty($data['student_id']) || empty($data['semester_id'])) {
            throw new HttpException(422, 'Parameter student_id dan semester_id wajib disertakan.');
        }

        $student = Student::with('classes')->findOrFail($data['student_id']);
        $semester = Semester::with('academicYear')->findOrFail($data['semester_id']);

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

        return $this->compileSemesterSnapshot($student, $semester, $data['homeroom_note'] ?? null);
    }

    /**
     * BE-GRD-04: Unduh PDF E-Rapor semester resmi bertanda tangan QR.
     */
    public function downloadPdf(string $id, Request $request): Response
    {
        $report = ReportCard::with(['student.classes', 'semester.academicYear', 'approver', 'file'])->findOrFail($id);
        $this->authorizeAccess($report, $request);

        $isImmutable = in_array($report->status, ['disetujui', 'terbit'], true);
        $pdfBinary = null;

        if ($isImmutable && $report->file && Storage::disk('local')->exists($report->file->object_key)) {
            $pdfBinary = Storage::disk('local')->get($report->file->object_key);
        }

        if (! is_string($pdfBinary) || $pdfBinary === '') {
            $pdfBinary = $this->buildPdfContent($report);

            if ($isImmutable) {
                $objectKey = "reports/report_cards/{$report->id}_{$report->status}.pdf";
                Storage::disk('local')->put($objectKey, $pdfBinary);

                $file = File::create([
                    'school_id' => $report->student?->school_id,
                    'owner_id' => $report->student_id,
                    'owner_type' => Student::class,
                    'bucket' => 'local',
                    'object_key' => $objectKey,
                    'mime_type' => 'application/pdf',
                    'size_bytes' => strlen($pdfBinary),
                    'visibility' => 'privat',
                    'checksum' => hash('sha256', $pdfBinary),
                ]);

                $report->forceFill(['file_id' => $file->id])->saveQuietly();
            }
        }

        return response($pdfBinary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="rapor_semester_'.$report->verify_token.'.pdf"',
            'Cache-Control' => 'no-cache, private',
        ]);
    }

    /**
     * Buat konten PDF resmi E-Rapor Semester menggunakan DomPDF & Blade A4.
     */
    protected function buildPdfContent(ReportCard $report): string
    {
        return app(ReportPdfRenderer::class)->renderReportCard($report);
    }

    /**
     * Otorisasi IDOR akses rapor semester.
     */
    protected function authorizeAccess(ReportCard $report, Request $request): void
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

    protected function canGenerate(User $user): bool
    {
        return $this->checkIsAdmin($user)
            || Teacher::where('user_id', $user->id)->exists();
    }

    protected function canApprove(User $user, ?ReportCard $report = null): bool
    {
        if ($this->checkIsAdmin($user)
            || $user->hasRole('principal')
            || $user->hasRole('super_admin')
            || $user->hasPermission('academic.report.generate')) {
            return true;
        }

        if ($report && $user->hasRole('homeroom')) {
            $teacher = Teacher::where('user_id', $user->id)->first();
            if ($teacher) {
                $homeroomClassIds = SchoolClass::where('homeroom_teacher_id', $teacher->id)->pluck('id');

                return $report->student?->classes()->whereIn('classes.id', $homeroomClassIds)->exists();
            }
        }

        return false;
    }

    protected function canBulkApprove(User $user): bool
    {
        return $this->checkIsAdmin($user);
    }

    protected function checkIsAdmin(User $user): bool
    {
        return $user->hasRole('super_admin')
            || $user->hasRole('principal')
            || $user->roles()->where('code', 'like', 'admin%')->exists();
    }

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
