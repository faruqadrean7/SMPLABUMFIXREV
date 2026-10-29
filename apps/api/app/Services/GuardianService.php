<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class GuardianService
{
    /**
     * BE-MST-04: List guardians with search and counts.
     */
    public function listGuardians(Request $request): LengthAwarePaginator
    {
        $user = $request->user();
        $limit = min($request->integer('limit', 20), 100);
        $q = trim((string) $request->query('q', ''));

        $query = Guardian::withCount('students')->with(['user', 'students']);

        // Scoping: If user is a guardian without master.student.read, they can only see themselves
        if ($user && $user->hasRole('guardian') && ! $user->hasPermission('master.student.read')) {
            $query->where('user_id', $user->id);
        }

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('full_name', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%")
                    ->orWhere('occupation', 'like', "%{$q}%");
            });
        }

        return $query->orderBy('full_name', 'asc')->paginate($limit);
    }

    /**
     * BE-MST-04: Get single guardian with linked children.
     */
    public function getGuardian(string $id, Request $request): Guardian
    {
        $guardian = Guardian::with([
            'students.classes' => function ($q) {
                $q->whereNull('class_students.left_at');
            },
            'user',
        ])->findOrFail($id);

        if ($request->user()) {
            $this->verifyGuardianAccess($guardian, $request->user());
        }

        return $guardian;
    }

    /**
     * BE-MST-04: Create guardian.
     */
    public function createGuardian(array $data, Request $request): Guardian
    {
        return DB::transaction(function () use ($data, $request) {
            $guardian = Guardian::create([
                'school_id' => $data['school_id'] ?? $request->user()?->school_id,
                'user_id' => $data['user_id'] ?? null,
                'full_name' => $data['full_name'],
                'phone' => $data['phone'],
                'occupation' => $data['occupation'] ?? null,
                'address' => $data['address'] ?? null,
            ]);

            $this->logAudit(
                $request->user()?->id,
                'guardian.created',
                'Guardian',
                $guardian->id,
                null,
                $guardian->toArray(),
                $request
            );

            return $guardian;
        });
    }

    /**
     * BE-MST-04: Update guardian.
     */
    public function updateGuardian(string $id, array $data, Request $request): Guardian
    {
        return DB::transaction(function () use ($id, $data, $request) {
            $guardian = Guardian::findOrFail($id);
            $oldValue = $guardian->toArray();

            $fields = ['full_name', 'phone', 'occupation', 'address', 'user_id'];
            foreach ($fields as $field) {
                if (array_key_exists($field, $data)) {
                    $guardian->{$field} = $data[$field];
                }
            }

            $guardian->save();

            $this->logAudit(
                $request->user()?->id,
                'guardian.updated',
                'Guardian',
                $guardian->id,
                $oldValue,
                $guardian->toArray(),
                $request
            );

            return $guardian;
        });
    }

    /**
     * BE-MST-04: Delete guardian (soft delete).
     */
    public function deleteGuardian(string $id, Request $request): void
    {
        $guardian = Guardian::findOrFail($id);
        $oldValue = $guardian->toArray();

        $guardian->delete();

        $this->logAudit(
            $request->user()?->id,
            'guardian.deleted',
            'Guardian',
            $guardian->id,
            $oldValue,
            null,
            $request
        );
    }

    // =========================================================================
    // STUDENT - GUARDIAN RELATIONSHIP METHODS
    // =========================================================================

    /**
     * BE-MST-04: List guardians of a specific student.
     */
    public function listStudentGuardians(string $studentId, Request $request): Collection
    {
        $student = Student::findOrFail($studentId);

        if ($request->user()) {
            $this->verifyStudentAccess($student, $request->user());
        }

        return $student->guardians()
            ->orderByPivot('is_primary', 'desc')
            ->get();
    }

    /**
     * BE-MST-04: Attach a guardian to a student with relationship and primary flag.
     * Enforces single is_primary = true per student.
     */
    public function attachStudentGuardian(string $studentId, array $data, Request $request): void
    {
        DB::transaction(function () use ($studentId, $data, $request) {
            $student = Student::findOrFail($studentId);
            $guardianId = $data['guardian_id'];
            $isPrimary = ! empty($data['is_primary']);

            $exists = DB::table('student_guardians')
                ->where('student_id', $studentId)
                ->where('guardian_id', $guardianId)
                ->exists();

            if ($exists) {
                throw new HttpException(
                    409,
                    'Wali ini sudah tertaut dengan siswa yang bersangkutan.',
                    null,
                    [],
                    409
                );
            }

            // Single primary guardian rule: deactivate previous primary if this is primary
            if ($isPrimary) {
                DB::table('student_guardians')
                    ->where('student_id', $studentId)
                    ->update(['is_primary' => false]);
            }

            $student->guardians()->attach($guardianId, [
                'relationship' => $data['relationship'],
                'is_primary' => $isPrimary,
            ]);

            $this->logAudit(
                $request->user()?->id,
                'student_guardian.linked',
                'StudentGuardian',
                $studentId,
                null,
                [
                    'student_id' => $studentId,
                    'guardian_id' => $guardianId,
                    'relationship' => $data['relationship'],
                    'is_primary' => $isPrimary,
                ],
                $request
            );
        });
    }

    /**
     * BE-MST-04: Update relationship or primary status between student and guardian.
     */
    public function updateStudentGuardian(string $studentId, string $guardianId, array $data, Request $request): void
    {
        DB::transaction(function () use ($studentId, $guardianId, $data, $request) {
            $student = Student::findOrFail($studentId);

            $existing = DB::table('student_guardians')
                ->where('student_id', $studentId)
                ->where('guardian_id', $guardianId)
                ->first();

            if (! $existing) {
                throw new HttpException(
                    404,
                    'Hubungan perwalian siswa tidak ditemukan.',
                    null,
                    [],
                    404
                );
            }

            $pivotUpdate = [];

            if (isset($data['relationship'])) {
                $pivotUpdate['relationship'] = $data['relationship'];
            }

            if (isset($data['is_primary'])) {
                $isPrimary = (bool) $data['is_primary'];
                $pivotUpdate['is_primary'] = $isPrimary;

                if ($isPrimary) {
                    DB::table('student_guardians')
                        ->where('student_id', $studentId)
                        ->where('guardian_id', '!=', $guardianId)
                        ->update(['is_primary' => false]);
                }
            }

            if (! empty($pivotUpdate)) {
                $student->guardians()->updateExistingPivot($guardianId, $pivotUpdate);
            }

            $this->logAudit(
                $request->user()?->id,
                'student_guardian.updated',
                'StudentGuardian',
                $studentId,
                (array) $existing,
                $pivotUpdate,
                $request
            );
        });
    }

    /**
     * BE-MST-04: Detach a guardian from a student.
     */
    public function detachStudentGuardian(string $studentId, string $guardianId, Request $request): void
    {
        DB::transaction(function () use ($studentId, $guardianId, $request) {
            $student = Student::findOrFail($studentId);

            $existing = DB::table('student_guardians')
                ->where('student_id', $studentId)
                ->where('guardian_id', $guardianId)
                ->first();

            if (! $existing) {
                throw new HttpException(
                    404,
                    'Hubungan perwalian siswa tidak ditemukan.',
                    null,
                    [],
                    404
                );
            }

            $student->guardians()->detach($guardianId);

            $this->logAudit(
                $request->user()?->id,
                'student_guardian.unlinked',
                'StudentGuardian',
                $studentId,
                (array) $existing,
                null,
                $request
            );
        });
    }

    /**
     * Verify guardian IDOR access.
     */
    protected function verifyGuardianAccess(Guardian $guardian, User $user): void
    {
        if ($user->hasRole('super_admin') || $user->hasPermission('master.student.read')) {
            return;
        }

        if ($guardian->user_id === $user->id) {
            return;
        }

        throw new HttpException(403, 'Anda tidak memiliki hak akses untuk melihat data wali ini.', null, [], 403);
    }

    /**
     * Verify student IDOR access.
     */
    protected function verifyStudentAccess(Student $student, User $user): void
    {
        if ($user->hasRole('super_admin') || $user->hasPermission('master.student.read')) {
            return;
        }

        if ($student->user_id === $user->id) {
            return;
        }

        if ($user->hasRole('guardian')) {
            $isLinked = DB::table('student_guardians')
                ->join('guardians', 'guardians.id', '=', 'student_guardians.guardian_id')
                ->where('guardians.user_id', $user->id)
                ->where('student_guardians.student_id', $student->id)
                ->exists();

            if ($isLinked) {
                return;
            }
        }

        if ($user->hasRole('homeroom_teacher') || $user->hasRole('teacher')) {
            $isHomeroom = DB::table('class_students')
                ->join('classes', 'classes.id', '=', 'class_students.class_id')
                ->join('teachers', 'teachers.id', '=', 'classes.homeroom_teacher_id')
                ->where('teachers.user_id', $user->id)
                ->where('class_students.student_id', $student->id)
                ->whereNull('class_students.left_at')
                ->exists();

            if ($isHomeroom) {
                return;
            }
        }

        throw new HttpException(403, 'Anda tidak memiliki hak akses untuk melihat data wali siswa ini.', null, [], 403);
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
                'entity_id' => ($entityId && \Illuminate\Support\Str::isUuid($entityId)) ? $entityId : null,
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
