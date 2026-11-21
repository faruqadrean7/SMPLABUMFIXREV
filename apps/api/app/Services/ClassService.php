<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\GradeLevel;
use App\Models\Major;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ClassService
{
    /**
     * BE-MST-05: List classes with pagination, filters, and student counts.
     */
    public function listClasses(Request $request): LengthAwarePaginator
    {
        $limit = min($request->integer('limit', 20), 100);
        $academicYearId = $request->query('academic_year_id');
        $gradeLevelId = $request->query('grade_level_id');
        $majorId = $request->query('major_id');
        $q = trim((string) $request->query('q', ''));

        $query = SchoolClass::with([
            'academicYear',
            'gradeLevel',
            'major',
            'homeroomTeacher',
        ])->withCount(['students as active_students_count' => function ($q) {
            $q->whereNull('class_students.left_at');
        }]);

        if ($academicYearId) {
            $query->where('academic_year_id', $academicYearId);
        }

        if ($gradeLevelId) {
            $query->where('grade_level_id', $gradeLevelId);
        }

        if ($majorId) {
            $query->where('major_id', $majorId);
        }

        if ($q !== '') {
            $query->where('name', 'like', "%{$q}%");
        }

        return $query->orderBy('name', 'asc')->paginate($limit);
    }

    /**
     * BE-MST-05: Get single class detail with student count and relations.
     */
    public function getClass(string $id): SchoolClass
    {
        return SchoolClass::with([
            'academicYear',
            'gradeLevel',
            'major',
            'homeroomTeacher',
        ])->withCount(['students as active_students_count' => function ($q) {
            $q->whereNull('class_students.left_at');
        }])->findOrFail($id);
    }

    /**
     * BE-MST-05: Create class.
     */
    public function createClass(array $data, Request $request): SchoolClass
    {
        return DB::transaction(function () use ($data, $request) {
            $class = SchoolClass::create([
                'school_id' => $data['school_id'] ?? $request->user()?->school_id,
                'academic_year_id' => $data['academic_year_id'],
                'grade_level_id' => $data['grade_level_id'],
                'major_id' => $data['major_id'] ?? null,
                'homeroom_teacher_id' => $data['homeroom_teacher_id'] ?? null,
                'name' => $data['name'],
                'capacity' => $data['capacity'] ?? 36,
                'program_type' => $data['program_type'] ?? 'reguler',
            ]);

            $this->logAudit(
                $request->user()?->id,
                'class.created',
                'SchoolClass',
                $class->id,
                null,
                $class->toArray(),
                $request
            );

            return $class;
        });
    }

    /**
     * BE-MST-05: Update class.
     */
    public function updateClass(string $id, array $data, Request $request): SchoolClass
    {
        return DB::transaction(function () use ($id, $data, $request) {
            $class = SchoolClass::findOrFail($id);
            $oldValue = $class->toArray();

            // Validate capacity change
            if (isset($data['capacity'])) {
                $activeCount = $class->students()->whereNull('class_students.left_at')->count();
                if ($data['capacity'] < $activeCount) {
                    throw new HttpException(
                        400,
                        "Kapasitas baru ({$data['capacity']}) lebih kecil dari jumlah siswa aktif saat ini ({$activeCount}).",
                        null,
                        [],
                        400
                    );
                }
            }

            $fields = ['grade_level_id', 'major_id', 'homeroom_teacher_id', 'name', 'capacity', 'program_type'];
            foreach ($fields as $field) {
                if (array_key_exists($field, $data)) {
                    $class->{$field} = $data[$field];
                }
            }

            $class->save();

            $this->logAudit(
                $request->user()?->id,
                'class.updated',
                'SchoolClass',
                $class->id,
                $oldValue,
                $class->toArray(),
                $request
            );

            return $class;
        });
    }

    /**
     * BE-MST-05: Delete class (Soft delete).
     */
    public function deleteClass(string $id, Request $request): void
    {
        $class = SchoolClass::findOrFail($id);

        if ($class->students()->whereNull('class_students.left_at')->exists()) {
            throw new HttpException(
                409,
                'Kelas tidak dapat dihapus karena masih memiliki siswa aktif terdaftar.',
                null,
                [],
                409
            );
        }

        if ($class->teachingAssignments()->exists()) {
            throw new HttpException(
                409,
                'Kelas tidak dapat dihapus karena masih memiliki penugasan mengajar terikat.',
                null,
                [],
                409
            );
        }

        $oldValue = $class->toArray();
        $class->delete();

        $this->logAudit(
            $request->user()?->id,
            'class.deleted',
            'SchoolClass',
            $class->id,
            $oldValue,
            null,
            $request
        );
    }

    // =========================================================================
    // CLASS STUDENTS & ROSTERS
    // =========================================================================

    /**
     * BE-MST-05: List students in class.
     */
    public function listClassStudents(string $classId, Request $request): Collection
    {
        $class = SchoolClass::findOrFail($classId);
        $activeOnly = filter_var($request->query('active_only', true), FILTER_VALIDATE_BOOLEAN);

        $query = $class->students()->withPivot(['id', 'joined_at', 'left_at']);

        if ($activeOnly) {
            $query->whereNull('class_students.left_at');
        }

        return $query->orderBy('full_name', 'asc')->get();
    }

    /**
     * BE-MST-05: Enroll students into class.
     * Enforces class capacity and single active class per student per academic year.
     */
    public function addClassStudents(string $classId, array $data, Request $request): void
    {
        DB::transaction(function () use ($classId, $data, $request) {
            $class = SchoolClass::findOrFail($classId);
            $studentIds = $data['student_ids'] ?? [$data['student_id']];
            $joinedAt = $data['joined_at'] ?? now()->toDateString();

            // 1. Capacity check
            $currentActive = $class->students()->whereNull('class_students.left_at')->count();
            $availableSlots = $class->capacity - $currentActive;

            if (count($studentIds) > $availableSlots) {
                throw new HttpException(
                    400,
                    "Kapasitas kelas tidak mencukupi (Tersisa {$availableSlots} kursi, diminta mendaftarkan ".count($studentIds).' siswa).',
                    null,
                    [],
                    400
                );
            }

            // 2. Single active class per student in academic year check
            $conflicts = DB::table('class_students')
                ->join('classes', 'classes.id', '=', 'class_students.class_id')
                ->join('students', 'students.id', '=', 'class_students.student_id')
                ->where('classes.academic_year_id', $class->academic_year_id)
                ->whereIn('class_students.student_id', $studentIds)
                ->whereNull('class_students.left_at')
                ->select('students.full_name', 'classes.name as class_name')
                ->get();

            if ($conflicts->isNotEmpty()) {
                $first = $conflicts->first();
                throw new HttpException(
                    400,
                    "Siswa '{$first->full_name}' sudah terdaftar aktif di rombel '{$first->class_name}' pada tahun ajaran ini.",
                    null,
                    [],
                    400
                );
            }

            // 3. Attach or re-activate
            foreach ($studentIds as $sId) {
                $existing = DB::table('class_students')
                    ->where('class_id', $classId)
                    ->where('student_id', $sId)
                    ->first();

                if ($existing) {
                    DB::table('class_students')
                        ->where('id', $existing->id)
                        ->update([
                            'joined_at' => $joinedAt,
                            'left_at' => null,
                            'updated_at' => now(),
                        ]);
                } else {
                    $class->students()->attach($sId, [
                        'id' => (string) Str::uuid(),
                        'joined_at' => $joinedAt,
                        'left_at' => null,
                    ]);
                }
            }

            $this->logAudit(
                $request->user()?->id,
                'class.students_enrolled',
                'SchoolClass',
                $class->id,
                null,
                ['count' => count($studentIds), 'student_ids' => $studentIds],
                $request
            );
        });
    }

    /**
     * BE-MST-05: Transfer student from current class to target class.
     * Records departure on source class and entrance on destination class.
     */
    public function transferClassStudent(string $sourceClassId, array $data, Request $request): void
    {
        DB::transaction(function () use ($sourceClassId, $data, $request) {
            $sourceClass = SchoolClass::findOrFail($sourceClassId);
            $targetClassId = $data['target_class_id'];
            $studentId = $data['student_id'];
            $transferDate = $data['transfer_date'];

            if ($sourceClassId === $targetClassId) {
                throw new HttpException(400, 'Kelas tujuan tidak boleh sama dengan kelas asal.', null, [], 400);
            }

            $targetClass = SchoolClass::findOrFail($targetClassId);

            // Verify active enrollment in source class
            $currentEnrollment = DB::table('class_students')
                ->where('class_id', $sourceClassId)
                ->where('student_id', $studentId)
                ->whereNull('left_at')
                ->first();

            if (! $currentEnrollment) {
                throw new HttpException(404, 'Siswa tidak ditemukan aktif di kelas asal ini.', null, [], 404);
            }

            // Check target class capacity
            $targetActiveCount = $targetClass->students()->whereNull('class_students.left_at')->count();
            if ($targetActiveCount >= $targetClass->capacity) {
                throw new HttpException(
                    400,
                    "Kapasitas kelas tujuan '{$targetClass->name}' sudah penuh (Maksimal {$targetClass->capacity} siswa).",
                    null,
                    [],
                    400
                );
            }

            // Record departure in source class
            DB::table('class_students')
                ->where('id', $currentEnrollment->id)
                ->update([
                    'left_at' => $transferDate,
                    'updated_at' => now(),
                ]);

            // Enroll into target class
            $existingTarget = DB::table('class_students')
                ->where('class_id', $targetClassId)
                ->where('student_id', $studentId)
                ->first();

            if ($existingTarget) {
                DB::table('class_students')
                    ->where('id', $existingTarget->id)
                    ->update([
                        'joined_at' => $transferDate,
                        'left_at' => null,
                        'updated_at' => now(),
                    ]);
            } else {
                $targetClass->students()->attach($studentId, [
                    'id' => (string) Str::uuid(),
                    'joined_at' => $transferDate,
                    'left_at' => null,
                ]);
            }

            $this->logAudit(
                $request->user()?->id,
                'class.student_transferred',
                'SchoolClass',
                $sourceClassId,
                ['class_id' => $sourceClassId, 'student_id' => $studentId],
                ['target_class_id' => $targetClassId, 'transfer_date' => $transferDate, 'reason' => $data['reason'] ?? null],
                $request
            );
        });
    }

    /**
     * BE-MST-05: Remove/unenroll student from class (marks left_at).
     */
    public function removeClassStudent(string $classId, string $studentId, Request $request): void
    {
        DB::transaction(function () use ($classId, $studentId, $request) {
            $class = SchoolClass::findOrFail($classId);

            $enrollment = DB::table('class_students')
                ->where('class_id', $classId)
                ->where('student_id', $studentId)
                ->whereNull('left_at')
                ->first();

            if (! $enrollment) {
                throw new HttpException(404, 'Siswa tidak ditemukan aktif di kelas ini.', null, [], 404);
            }

            DB::table('class_students')
                ->where('id', $enrollment->id)
                ->update([
                    'left_at' => now()->toDateString(),
                    'updated_at' => now(),
                ]);

            $this->logAudit(
                $request->user()?->id,
                'class.student_removed',
                'SchoolClass',
                $classId,
                (array) $enrollment,
                ['left_at' => now()->toDateString()],
                $request
            );
        });
    }

    /**
     * BE-MST-05: Reference master list for grade levels.
     */
    public function listGradeLevels(): Collection
    {
        return GradeLevel::orderBy('sort_order', 'asc')->get();
    }

    /**
     * BE-MST-05: Reference master list for majors.
     */
    public function listMajors(): Collection
    {
        return Major::orderBy('name', 'asc')->get();
    }

    /**
     * Helper to write an audit log entry.
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
