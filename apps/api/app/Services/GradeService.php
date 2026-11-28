<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\AuditLog;
use App\Models\ClassStudent;
use App\Models\Grade;
use App\Models\GradeComponent;
use App\Models\GradePeriod;
use App\Models\Guardian;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use App\Support\PaginationHelper;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class GradeService
{
    // =========================================================================
    // 1. Grade Components (Komponen Nilai)
    // =========================================================================

    /**
     * BE-GRD-01: Daftar komponen nilai per mapel & semester.
     */
    public function listComponents(array $filters): Collection
    {
        $query = GradeComponent::with(['subject', 'semester', 'class'])
            ->orderBy('created_at', 'asc');

        if (! empty($filters['subject_id'])) {
            $query->where('subject_id', $filters['subject_id']);
        }

        if (! empty($filters['semester_id'])) {
            $query->where('semester_id', $filters['semester_id']);
        }

        if (array_key_exists('class_id', $filters)) {
            if ($filters['class_id'] === null) {
                $query->whereNull('class_id');
            } else {
                $query->where('class_id', $filters['class_id']);
            }
        }

        return $query->get();
    }

    /**
     * BE-GRD-01: Tambah komponen nilai baru.
     */
    public function createComponent(array $data, Request $request): GradeComponent
    {
        /** @var User $currentUser */
        $currentUser = $request->user();
        $this->authorizeComponentMutation($currentUser, $data['subject_id'], $data['class_id'] ?? null);

        return DB::transaction(function () use ($data, $currentUser, $request) {
            $component = GradeComponent::create([
                'subject_id' => $data['subject_id'],
                'semester_id' => $data['semester_id'],
                'class_id' => $data['class_id'] ?? null,
                'code' => strtoupper(trim($data['code'])),
                'name' => trim($data['name']),
                'weight' => $data['weight'],
            ]);

            $this->logAudit(
                $currentUser->id,
                'grade_component.created',
                'GradeComponent',
                $component->id,
                null,
                $component->toArray(),
                $request
            );

            return $component->load(['subject', 'semester', 'class']);
        });
    }

    /**
     * BE-GRD-01: Update komponen nilai.
     */
    public function updateComponent(string $id, array $data, Request $request): GradeComponent
    {
        /** @var User $currentUser */
        $currentUser = $request->user();
        $component = GradeComponent::with('subject')->findOrFail($id);
        $this->authorizeComponentMutation($currentUser, $component->subject_id, $component->class_id);

        return DB::transaction(function () use ($component, $data, $currentUser, $request) {
            $oldData = $component->toArray();

            if (isset($data['code'])) {
                $component->code = strtoupper(trim($data['code']));
            }
            if (isset($data['name'])) {
                $component->name = trim($data['name']);
            }
            if (isset($data['weight'])) {
                $component->weight = $data['weight'];
            }
            $component->save();

            $this->logAudit(
                $currentUser->id,
                'grade_component.updated',
                'GradeComponent',
                $component->id,
                $oldData,
                $component->toArray(),
                $request
            );

            return $component->load(['subject', 'semester', 'class']);
        });
    }

    /**
     * BE-GRD-01: Hapus komponen nilai.
     */
    public function deleteComponent(string $id, Request $request): bool
    {
        /** @var User $currentUser */
        $currentUser = $request->user();
        $component = GradeComponent::with('subject')->findOrFail($id);
        $this->authorizeComponentMutation($currentUser, $component->subject_id, $component->class_id);

        if ($component->grades()->exists()) {
            throw new HttpException(422, 'Komponen nilai tidak dapat dihapus karena sudah memiliki data nilai siswa.');
        }

        if (Assignment::where('grade_component_id', $component->id)->exists()) {
            throw new HttpException(422, 'Komponen nilai tidak dapat dihapus karena ditautkan pada tugas pembelajaran LMS.');
        }

        return DB::transaction(function () use ($component, $currentUser, $request) {
            $oldData = $component->toArray();
            $component->delete();

            $this->logAudit(
                $currentUser->id,
                'grade_component.deleted',
                'GradeComponent',
                $component->id,
                $oldData,
                null,
                $request
            );

            return true;
        });
    }

    // =========================================================================
    // 2. Grades (Buku Nilai & Input Massal)
    // =========================================================================

    /**
     * BE-GRD-01: Ambil daftar nilai dengan filter dan otorisasi IDOR.
     */
    public function listGrades(array $filters, Request $request): LengthAwarePaginator
    {
        /** @var User $currentUser */
        $currentUser = $request->user();
        $student = Student::where('user_id', $currentUser->id)->first();
        $guardian = Guardian::where('user_id', $currentUser->id)->first();
        $teacher = Teacher::where('user_id', $currentUser->id)->first();
        $isAdmin = $this->checkIsAdmin($currentUser);

        $query = Grade::with(['student', 'component.subject', 'period', 'enterer'])
            ->orderBy('created_at', 'desc');

        if (! $isAdmin) {
            if ($student) {
                // Siswa hanya melihat miliknya sendiri
                $query->where('student_id', $student->id);
            } elseif ($guardian) {
                // Wali murid melihat milik anak-anaknya
                $childIds = $guardian->students()->pluck('students.id');
                $query->whereIn('student_id', $childIds);
            } elseif ($teacher) {
                // P1-14: Guru hanya melihat nilai siswa di kelas ajar/perwalian aktifnya (bukan siswa kelas lain)
                $assignedClassIds = $teacher->teachingAssignments()->pluck('class_id');
                $homeroomClassIds = SchoolClass::where('homeroom_teacher_id', $teacher->id)->pluck('id');
                $allClassIds = $assignedClassIds->merge($homeroomClassIds)->unique();

                if ($allClassIds->isEmpty()) {
                    $query->whereRaw('1 = 0');
                } else {
                    $query->whereHas('student.activeClasses', function ($sub) use ($allClassIds) {
                        $sub->whereIn('classes.id', $allClassIds);
                    });
                }
            } else {
                // Deny-by-default untuk peran lain yang tidak memiliki otorisasi data nilai
                $query->whereRaw('1 = 0');
            }
        }

        if (! $currentUser->hasRole('super_admin') && ! empty($currentUser->school_id)) {
            $query->whereHas('student', function ($sq) use ($currentUser) {
                $sq->where('school_id', $currentUser->school_id)->orWhereNull('school_id');
            });
        }

        if (! empty($filters['student_id'])) {
            $query->where('student_id', $filters['student_id']);
        }

        if (! empty($filters['component_id'])) {
            $query->where('component_id', $filters['component_id']);
        }

        if (! empty($filters['period_id'])) {
            $query->where('period_id', $filters['period_id']);
        }

        if (! empty($filters['class_id'])) {
            $classId = $filters['class_id'];
            $studentIds = ClassStudent::where('class_id', $classId)->pluck('student_id');
            $query->whereIn('student_id', $studentIds);
        }

        if (! empty($filters['subject_id'])) {
            $subjectId = $filters['subject_id'];
            $query->whereHas('component', fn ($q) => $q->where('subject_id', $subjectId));
        }

        $perPage = PaginationHelper::clamp($filters['per_page'] ?? null, 30);

        return $query->paginate($perPage);
    }

    /**
     * BE-GRD-01: Input / update nilai satuan.
     */
    public function storeGrade(array $data, Request $request): Grade
    {
        /** @var User $currentUser */
        $currentUser = $request->user();
        $this->authorizeGradeEntry($currentUser, $data['component_id'], $data['student_id']);

        $component = GradeComponent::findOrFail($data['component_id']);
        $period = GradePeriod::findOrFail($data['period_id']);
        if ($component->semester_id && $period->semester_id && $component->semester_id !== $period->semester_id) {
            throw new HttpException(422, 'Komponen nilai tidak sesuai dengan semester pada periode penilaian yang dipilih.');
        }
        if ($period->status === 'locked') {
            throw new HttpException(422, 'Periode penilaian telah dikunci. Perubahan nilai tidak diizinkan tanpa persetujuan / pembukaan kunci.');
        }

        return DB::transaction(function () use ($data, $currentUser, $request) {
            $existing = Grade::where('student_id', $data['student_id'])
                ->where('component_id', $data['component_id'])
                ->where('period_id', $data['period_id'])
                ->first();

            if ($existing && $existing->locked_at !== null) {
                $canOverride = ! empty($data['force_overwrite']) &&
                    $currentUser &&
                    ($currentUser->hasRole(['admin', 'super_admin']) || $currentUser->hasPermission('grade.unlock'));

                if (! $canOverride) {
                    throw new HttpException(422, 'Nilai pada komponen ini telah dikunci (locked) dan tidak dapat diubah.');
                }
            }

            $isUpdate = $existing !== null;
            $oldValues = $isUpdate ? $existing->only(['value', 'source', 'entered_by']) : null;

            // Kebijakan "nilai tertinggi menang": nilai manual tidak menurunkan
            // nilai otomatis dan sebaliknya. Riwayat dicatat di grade_histories.
            $grade = app(GradeRecorder::class)->recordManual(
                studentId: $data['student_id'],
                componentId: $data['component_id'],
                periodId: $data['period_id'],
                value: (float) $data['value'],
                changedBy: $currentUser?->id,
                note: 'Input manual buku nilai',
            );

            $action = $isUpdate ? 'academic.grade.update' : 'grade.entered';
            $newValues = $grade->only(['value', 'source', 'entered_by']);

            app(AuditLogService::class)->record(
                action: $action,
                entity: 'Grade',
                entityId: $grade->id,
                oldValues: $oldValues,
                newValues: $newValues,
                reason: $data['reason'] ?? null,
                request: $request,
                isAuthoritative: true
            );

            return $grade->load(['student', 'component', 'period']);
        });
    }

    /**
     * BE-GRD-01: Input massal nilai satu kelas / komponen.
     */
    public function bulkStoreGrades(array $data, Request $request): array
    {
        /** @var User $currentUser */
        $currentUser = $request->user();
        $defaultComponentId = $data['component_id'] ?? null;
        if ($defaultComponentId) {
            $this->authorizeGradeEntry($currentUser, $defaultComponentId);
        }

        $period = GradePeriod::findOrFail($data['period_id']);
        if ($period->status === 'locked') {
            throw new HttpException(422, 'Periode penilaian telah dikunci. Perubahan nilai massal tidak diizinkan tanpa pembukaan kunci.');
        }

        // P1-14: Validasi otorisasi komponen, keanggotaan siswa aktif, dan kecocokan semester sebelum transaksi
        $componentCache = [];
        foreach ($data['grades'] as $item) {
            $componentId = $item['component_id'] ?? $defaultComponentId;
            if (! $componentId) {
                continue;
            }
            $this->authorizeGradeEntry($currentUser, $componentId, $item['student_id']);

            if (! isset($componentCache[$componentId])) {
                $componentCache[$componentId] = GradeComponent::findOrFail($componentId);
            }
            $comp = $componentCache[$componentId];
            if ($comp->semester_id && $period->semester_id && $comp->semester_id !== $period->semester_id) {
                throw new HttpException(422, 'Komponen nilai tidak sesuai dengan semester pada periode penilaian yang dipilih.');
            }
        }

        return DB::transaction(function () use ($data, $defaultComponentId, $currentUser, $request) {
            $canOverride = ! empty($data['force_overwrite']) &&
                $currentUser &&
                ($currentUser->hasRole(['admin', 'super_admin']) || $currentUser->hasPermission('grade.unlock'));

            if (! $canOverride) {
                foreach ($data['grades'] as $item) {
                    $componentId = $item['component_id'] ?? $defaultComponentId;
                    if (! $componentId) {
                        continue;
                    }

                    $isLocked = Grade::where('student_id', $item['student_id'])
                        ->where('component_id', $componentId)
                        ->where('period_id', $data['period_id'])
                        ->whereNotNull('locked_at')
                        ->exists();

                    if ($isLocked) {
                        throw new HttpException(422, 'Satu atau lebih nilai pada lingkup ini telah dikunci (locked) dan tidak dapat diubah.');
                    }
                }
            }

            $savedGrades = [];

            foreach ($data['grades'] as $item) {
                $componentId = $item['component_id'] ?? $defaultComponentId;
                if (! $componentId) {
                    continue;
                }
                if (! $defaultComponentId) {
                    $this->authorizeGradeEntry($currentUser, $componentId);
                }

                $grade = app(GradeRecorder::class)->recordManual(
                    studentId: $item['student_id'],
                    componentId: $componentId,
                    periodId: $data['period_id'],
                    value: (float) $item['value'],
                    changedBy: $currentUser?->id,
                    note: 'Input manual (massal)',
                );

                $savedGrades[] = $grade;
            }

            app(AuditLogService::class)->record(
                action: 'grade.bulk_entered',
                entity: 'Grade',
                entityId: $defaultComponentId ?? $data['period_id'],
                oldValues: null,
                newValues: ['count' => count($savedGrades)],
                reason: $data['reason'] ?? null,
                request: $request,
                isAuthoritative: true
            );

            return [
                'count' => count($savedGrades),
                'component_id' => $defaultComponentId,
                'period_id' => $data['period_id'],
                'items' => $savedGrades,
            ];
        });
    }

    // =========================================================================
    // 3. Grade Period Lock (Kunci Periode Penilaian)
    // =========================================================================

    /**
     * BE-GRD-01: Kunci / buka kunci periode penilaian bulanan.
     */
    public function togglePeriodLock(string $periodId, array $data, Request $request): GradePeriod
    {
        /** @var User $currentUser */
        $currentUser = $request->user();
        $isAdmin = $this->checkIsAdmin($currentUser);

        if (! $isAdmin) {
            throw new HttpException(403, 'Hanya administrator atau waka kurikulum yang berhak mengunci/membuka periode penilaian.');
        }

        $period = GradePeriod::findOrFail($periodId);

        return DB::transaction(function () use ($period, $data, $currentUser, $request) {
            $oldData = $period->toArray();

            if ($data['status'] === 'locked') {
                $period->update([
                    'status' => 'locked',
                    'locked_by' => $currentUser->id,
                    'locked_at' => Carbon::now(),
                ]);
            } else {
                $period->update([
                    'status' => 'open',
                    'locked_by' => null,
                    'locked_at' => null,
                ]);
            }

            $this->logAudit(
                $currentUser->id,
                'grade_period.lock_updated',
                'GradePeriod',
                $period->id,
                $oldData,
                $period->toArray(),
                $request
            );

            return $period->load(['semester', 'locker']);
        });
    }

    /**
     * BE-GRD-01: Daftar periode penilaian bulanan per semester.
     */
    public function listPeriods(array $filters, Request $request): Collection
    {
        $query = GradePeriod::with(['semester.academicYear', 'locker'])
            ->orderBy('month', 'asc');

        if (! empty($filters['semester_id'])) {
            $semesterId = $filters['semester_id'];
            $query->where('semester_id', $semesterId);

            // Jika belum ada periode terdaftar untuk semester ini, inisialisasi otomatis
            if (! GradePeriod::where('semester_id', $semesterId)->exists()) {
                $this->ensurePeriodsForSemester($semesterId);
                $query = GradePeriod::with(['semester.academicYear', 'locker'])
                    ->where('semester_id', $semesterId)
                    ->orderBy('month', 'asc');
            }
        }

        return $query->get();
    }

    /**
     * Pastikan periode 1-12 atau periode sesuai kalender semester dibuat.
     */
    public function ensurePeriodsForSemester(string $semesterId): Collection
    {
        $semester = Semester::findOrFail($semesterId);
        $months = [];

        if ($semester->start_date && $semester->end_date) {
            $start = Carbon::parse($semester->start_date);
            $end = Carbon::parse($semester->end_date);
            $curr = $start->copy()->startOfMonth();
            while ($curr->lessThanOrEqualTo($end)) {
                $months[] = (int) $curr->format('n');
                $curr->addMonth();
            }
        }

        if (empty($months)) {
            if (stripos($semester->name, 'ganjil') !== false || stripos($semester->name, '1') !== false) {
                $months = [7, 8, 9, 10, 11, 12];
            } elseif (stripos($semester->name, 'genap') !== false || stripos($semester->name, '2') !== false) {
                $months = [1, 2, 3, 4, 5, 6];
            } else {
                $months = range(1, 12);
            }
        }

        foreach (array_unique($months) as $m) {
            GradePeriod::firstOrCreate(
                [
                    'semester_id' => $semesterId,
                    'month' => $m,
                ],
                [
                    'status' => 'open',
                ]
            );
        }

        return GradePeriod::with(['semester.academicYear', 'locker'])
            ->where('semester_id', $semesterId)
            ->orderBy('month', 'asc')
            ->get();
    }

    /**
     * BE-GRD-01 / ADM-GRD-01: Grid spreadsheet nilai satu kelas (Siswa x Komponen).
     */
    public function getClassGradeMatrix(array $filters, Request $request): array
    {
        /** @var User $currentUser */
        $currentUser = $request->user();

        if (empty($filters['class_id']) || empty($filters['subject_id']) || empty($filters['period_id'])) {
            throw new HttpException(422, 'Parameter class_id, subject_id, dan period_id wajib disertakan.');
        }

        $classId = $filters['class_id'];
        $subjectId = $filters['subject_id'];
        $periodId = $filters['period_id'];

        $schoolClass = SchoolClass::with('academicYear')->findOrFail($classId);
        $period = GradePeriod::with('semester')->findOrFail($periodId);
        $semesterId = $period->semester_id;

        // Otorisasi IDOR: Jika bukan admin, harus guru pengampu kelas & mapel atau wali kelas
        if (! $this->checkIsAdmin($currentUser)) {
            $teacher = Teacher::where('user_id', $currentUser->id)->first();
            if (! $teacher) {
                throw new HttpException(403, 'Akses ditolak.');
            }
            $isAssigned = $teacher->teachingAssignments()
                ->where('class_id', $classId)
                ->where('subject_id', $subjectId)
                ->exists();
            $isHomeroom = $schoolClass->homeroom_teacher_id === $teacher->id;
            if (! $isAssigned && ! $isHomeroom) {
                throw new HttpException(403, 'Anda tidak memiliki hak akses penilaian untuk kelas dan mata pelajaran ini.');
            }
        }

        // Komponen penilaian untuk mapel & semester ini
        $components = GradeComponent::where('subject_id', $subjectId)
            ->where('semester_id', $semesterId)
            ->where(function ($q) use ($classId) {
                $q->whereNull('class_id')->orWhere('class_id', $classId);
            })
            ->orderBy('created_at', 'asc')
            ->get();

        // Siswa aktif di kelas
        $enrolledStudentIds = ClassStudent::where('class_id', $classId)->pluck('student_id');
        $students = Student::whereIn('id', $enrolledStudentIds)
            ->whereIn('status', ['aktif', 'active'])
            ->orderBy('nisn', 'asc')
            ->get();

        $componentIds = $components->pluck('id');
        $studentIds = $students->pluck('id');

        $grades = Grade::where('period_id', $periodId)
            ->whereIn('component_id', $componentIds)
            ->whereIn('student_id', $studentIds)
            ->get();

        $gradeMap = [];
        foreach ($grades as $g) {
            $gradeMap[$g->student_id][$g->component_id] = [
                'id' => $g->id,
                'value' => (float) $g->value,
                'source' => $g->source,
                'entered_by' => $g->entered_by,
                'updated_at' => $g->updated_at,
            ];
        }

        $totalWeight = (float) $components->sum('weight');
        $studentRows = [];
        $totalScores = [];
        $completeCount = 0;
        $incompleteCount = 0;

        foreach ($students as $stu) {
            $studentGrades = [];
            $weightedSum = 0;
            $studentWeightSum = 0;
            $filledComponentsCount = 0;

            foreach ($components as $comp) {
                $g = $gradeMap[$stu->id][$comp->id] ?? null;
                $studentGrades[$comp->id] = $g;
                if ($g !== null) {
                    $filledComponentsCount++;
                    $weightedSum += ($g['value'] * (float) $comp->weight);
                    $studentWeightSum += (float) $comp->weight;
                }
            }

            $isComplete = ($components->count() > 0) && ($filledComponentsCount === $components->count());
            if ($isComplete) {
                $completeCount++;
            } else {
                $incompleteCount++;
            }

            $finalScore = $studentWeightSum > 0 ? round($weightedSum / $studentWeightSum, 2) : null;
            if ($finalScore !== null) {
                $totalScores[] = $finalScore;
            }

            $studentRows[] = [
                'student' => $stu,
                'grades' => $studentGrades,
                'is_complete' => $isComplete,
                'filled_count' => $filledComponentsCount,
                'total_components' => $components->count(),
                'final_score' => $finalScore,
            ];
        }

        $classAverage = count($totalScores) > 0 ? round(array_sum($totalScores) / count($totalScores), 2) : 0;
        $highestScore = count($totalScores) > 0 ? max($totalScores) : 0;
        $lowestScore = count($totalScores) > 0 ? min($totalScores) : 0;

        return [
            'class' => $schoolClass,
            'period' => $period,
            'components' => $components,
            'total_weight' => $totalWeight,
            'students' => $studentRows,
            'summary' => [
                'total_students' => $students->count(),
                'complete_count' => $completeCount,
                'incomplete_count' => $incompleteCount,
                'average_score' => $classAverage,
                'highest_score' => $highestScore,
                'lowest_score' => $lowestScore,
            ],
        ];
    }

    /**
     * BE-GRD-01: Hapus nilai satuan siswa.
     */
    public function deleteGrade(string $id, Request $request): bool
    {
        /** @var User $currentUser */
        $currentUser = $request->user();
        $grade = Grade::with(['component', 'period'])->findOrFail($id);

        if ($grade->period && $grade->period->status === 'locked') {
            throw new HttpException(422, 'Periode penilaian telah dikunci. Nilai tidak dapat dihapus.');
        }

        $this->authorizeGradeEntry($currentUser, $grade->component_id, $grade->student_id);

        return DB::transaction(function () use ($grade, $currentUser, $request) {
            $oldData = $grade->toArray();
            $grade->delete();

            $this->logAudit(
                $currentUser->id,
                'grade.deleted',
                'Grade',
                $grade->id,
                $oldData,
                null,
                $request
            );

            return true;
        });
    }

    /**
     * Public wrapper for checkGradeEntryAuthorization
     */
    public function checkGradeEntryAuthorization(User $user, string $componentId, ?string $studentId = null): void
    {
        $this->authorizeGradeEntry($user, $componentId, $studentId);
    }

    /**
     * P1-14: Validasi otorisasi mutasi komponen nilai (create/update/delete) beserta isolasi sekolah & penugasan guru.
     */
    protected function authorizeComponentMutation(User $user, string $subjectId, ?string $classId = null): void
    {
        $subject = Subject::findOrFail($subjectId);
        if (! $user->hasRole('super_admin') && ! empty($user->school_id) && ! empty($subject->school_id) && $subject->school_id !== $user->school_id) {
            throw new HttpException(403, 'Akses ditolak: Mata pelajaran berada di luar sekolah penugasan Anda.');
        }

        if ($classId !== null) {
            $schoolClass = SchoolClass::findOrFail($classId);
            if (! $user->hasRole('super_admin') && ! empty($user->school_id) && ! empty($schoolClass->school_id) && $schoolClass->school_id !== $user->school_id) {
                throw new HttpException(403, 'Akses ditolak: Kelas berada di luar sekolah penugasan Anda.');
            }
        }

        if ($this->checkIsAdmin($user)) {
            return;
        }

        $teacher = Teacher::where('user_id', $user->id)->first();
        if (! $teacher) {
            throw new HttpException(403, 'Hanya guru atau administrator yang berhak mengelola komponen nilai.');
        }

        if ($classId !== null) {
            $isAssigned = $teacher->teachingAssignments()
                ->where('class_id', $classId)
                ->where('subject_id', $subjectId)
                ->exists();
            $isHomeroom = SchoolClass::where('id', $classId)
                ->where('homeroom_teacher_id', $teacher->id)
                ->exists();

            if (! $isAssigned && ! $isHomeroom) {
                throw new HttpException(403, 'Anda tidak ditugaskan mengelola komponen nilai untuk mata pelajaran dan kelas ini.');
            }

            return;
        }

        // Komponen global (class_id IS NULL): guru minimal harus mengampu mata pelajaran terkait
        $teachesSubject = $teacher->teachingAssignments()
            ->where('subject_id', $subjectId)
            ->exists();
        if (! $teachesSubject) {
            throw new HttpException(403, 'Anda tidak ditugaskan mengampu mata pelajaran pada komponen nilai global ini.');
        }
    }

    /**
     * Validasi otorisasi apakah pengguna berhak menginput/menghapus nilai komponen & siswa ini (P1-14 anti-IDOR).
     */
    protected function authorizeGradeEntry(User $user, string $componentId, ?string $studentId = null): void
    {
        $component = GradeComponent::with(['subject', 'class'])->findOrFail($componentId);

        // Isolasi sekolah lintas tenant (non-super_admin)
        if (! $user->hasRole('super_admin') && ! empty($user->school_id)) {
            if (! empty($component->subject?->school_id) && $component->subject->school_id !== $user->school_id) {
                throw new HttpException(403, 'Akses ditolak: Komponen nilai berada di luar sekolah penugasan Anda.');
            }
            if ($component->class && ! empty($component->class->school_id) && $component->class->school_id !== $user->school_id) {
                throw new HttpException(403, 'Akses ditolak: Kelas komponen nilai berada di luar sekolah penugasan Anda.');
            }
        }

        $student = null;
        if ($studentId !== null) {
            $student = Student::findOrFail($studentId);
            if (! $user->hasRole('super_admin') && ! empty($user->school_id) && ! empty($student->school_id) && $student->school_id !== $user->school_id) {
                throw new HttpException(403, 'Akses ditolak: Siswa berada di luar sekolah penugasan Anda.');
            }
        }

        if ($this->checkIsAdmin($user)) {
            return;
        }

        $teacher = Teacher::where('user_id', $user->id)->first();
        if (! $teacher) {
            throw new HttpException(403, 'Hanya guru atau administrator yang berhak menginput nilai.');
        }

        // Jika komponen terikat pada kelas tertentu, guru harus mengampu kelas+mapel atau menjadi walas
        if ($component->class_id) {
            $isAssigned = $teacher->teachingAssignments()
                ->where('class_id', $component->class_id)
                ->where('subject_id', $component->subject_id)
                ->exists();

            $isHomeroom = $component->class && $component->class->homeroom_teacher_id === $teacher->id;

            if (! $isAssigned && ! $isHomeroom) {
                throw new HttpException(403, 'Anda tidak ditugaskan untuk mengajar mata pelajaran ini pada kelas tersebut.');
            }

            // P1-14: Pastikan siswa benar-benar anggota aktif kelas komponen tersebut
            if ($student !== null) {
                $isActiveMember = ClassStudent::where('class_id', $component->class_id)
                    ->where('student_id', $student->id)
                    ->whereNull('left_at')
                    ->exists();
                if (! $isActiveMember) {
                    throw new HttpException(403, 'Akses ditolak: Siswa tidak terdaftar sebagai anggota aktif pada kelas komponen nilai ini.');
                }
            }

            return;
        }

        // P1-14: Komponen global (class_id IS NULL) — guru wajib mengampu mapel terkait dan hanya boleh menilai siswa di kelas aktifnya
        $taughtClassIds = $teacher->teachingAssignments()
            ->where('subject_id', $component->subject_id)
            ->pluck('class_id')
            ->all();
        $homeroomClassIds = SchoolClass::where('homeroom_teacher_id', $teacher->id)->pluck('id')->all();
        $allowedClassIds = array_values(array_unique(array_merge($taughtClassIds, $homeroomClassIds)));

        if (empty($taughtClassIds) && empty($homeroomClassIds)) {
            throw new HttpException(403, 'Anda tidak ditugaskan untuk mengajar mata pelajaran pada komponen nilai global ini.');
        }

        if ($student !== null) {
            $inAllowedActiveClass = ClassStudent::whereIn('class_id', $allowedClassIds)
                ->where('student_id', $student->id)
                ->whereNull('left_at')
                ->exists();
            if (! $inAllowedActiveClass) {
                throw new HttpException(403, 'Akses ditolak: Siswa tidak berada di kelas aktif yang Anda ampu untuk mata pelajaran ini.');
            }
        } elseif (empty($taughtClassIds)) {
            throw new HttpException(403, 'Anda tidak ditugaskan untuk mengajar mata pelajaran pada komponen nilai global ini.');
        }
    }

    /**
     * Cek apakah pengguna adalah admin kurikulum, TU, atau super admin.
     */
    protected function checkIsAdmin(User $user): bool
    {
        return $user->hasRole('super_admin')
            || $user->hasRole('admin_tu')
            || $user->hasRole('admin_kurikulum')
            || $user->hasRole('principal')
            || $user->hasPermission('academic.schedule.manage')
            || $user->roles()->where('code', 'like', 'admin%')->exists();
    }

    /**
     * Catat log audit keamanan.
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
