<?php

namespace App\Services;

use App\Models\ProgramMaterial;
use App\Models\ProgramTarget;
use App\Models\ProgramTask;
use App\Models\ProgramTaskSubmission;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ProgramKhususService
{
    // =========================================================================
    // SECTION 1: SISWA (STUDENT) - BE-PROG-01
    // =========================================================================

    /**
     * Resolusi program belajar efektif siswa.
     * Prioritas: student.program_type -> class.program_type -> 'reguler'.
     */
    public function resolveEffectiveProgram(Student $student): string
    {
        $student->loadMissing('classes');

        $prog = $student->program_type ?? $student->classes->first()?->program_type ?? 'reguler';

        return $prog;
    }

    /**
     * Verifikasi bahwa siswa terdaftar dalam program khusus.
     * Siswa 'reguler' atau program yang tidak sesuai akan memicu HTTP 403.
     */
    public function ensureStudentInProgram(Student $student, ?string $requestedProgram = null): string
    {
        $effective = $this->resolveEffectiveProgram($student);

        if ($effective === 'reguler') {
            throw new HttpException(403, 'Akses ditolak: Anda terdaftar sebagai siswa program Reguler.');
        }

        if ($requestedProgram !== null && $requestedProgram !== '' && $requestedProgram !== $effective) {
            throw new HttpException(403, "Akses ditolak: Anda tidak memiliki akses ke program {$requestedProgram}.");
        }

        return $effective;
    }

    /**
     * Mengambil daftar materi program siswa dengan filter dan paginasi (bebas N+1).
     */
    public function getMaterialsForStudent(Student $student, array $filters = []): LengthAwarePaginator
    {
        $requestedProgram = $filters['program_type'] ?? null;
        $effectiveProgram = $this->ensureStudentInProgram($student, $requestedProgram);

        $student->loadMissing('classes');
        $classId = $student->classes->first()?->id;

        $query = ProgramMaterial::with(['file', 'creator:id,username'])
            ->where('program_type', $effectiveProgram)
            ->where(function ($q) use ($student, $classId) {
                $q->where('target_scope', 'all')
                    ->orWhere(function ($sub) use ($student) {
                        $sub->where('target_scope', 'student')
                            ->where('assigned_student_id', $student->id);
                    });

                if ($classId) {
                    $q->orWhere(function ($sub) use ($classId) {
                        $sub->where('target_scope', 'class')
                            ->where('class_id', $classId);
                    });
                }
            });

        if (! empty($student->school_id)) {
            $query->where(function ($q) use ($student) {
                $q->whereNull('school_id')->orWhere('school_id', $student->school_id);
            });
        }

        if (! empty($filters['q'])) {
            $keyword = '%'.$filters['q'].'%';
            $query->where(function ($q) use ($keyword) {
                $q->where('title', 'ilike', $keyword)
                    ->orWhere('description', 'ilike', $keyword);
            });
        }

        if (! empty($filters['material_type']) && $filters['material_type'] !== 'all') {
            $query->where('material_type', $filters['material_type']);
        }

        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 100);

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Mengambil daftar target capaian program siswa dengan filter dan paginasi.
     */
    public function getTargetsForStudent(Student $student, array $filters = []): LengthAwarePaginator
    {
        $requestedProgram = $filters['program_type'] ?? null;
        $effectiveProgram = $this->ensureStudentInProgram($student, $requestedProgram);

        $query = ProgramTarget::with(['setter:id,username'])
            ->where('student_id', $student->id)
            ->where('program_type', $effectiveProgram);

        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['domain']) && $filters['domain'] !== 'all') {
            $query->where('domain', $filters['domain']);
        }

        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 100);

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Mengambil daftar tugas program khusus siswa beserta status pengumpulan (bebas N+1).
     */
    public function getTasksForStudent(Student $student, array $filters = []): LengthAwarePaginator
    {
        $requestedProgram = $filters['program_type'] ?? null;
        $effectiveProgram = $this->ensureStudentInProgram($student, $requestedProgram);

        $student->loadMissing('classes');
        $classId = $student->classes->first()?->id;

        $query = ProgramTask::with([
            'file',
            'creator:id,username',
            'submissions' => function ($q) use ($student) {
                $q->where('student_id', $student->id)->with('file');
            },
        ])
            ->where('program_type', $effectiveProgram)
            ->where(function ($q) use ($student, $classId) {
                $q->where('target_scope', 'all')
                    ->orWhere(function ($sub) use ($student) {
                        $sub->where('target_scope', 'student')
                            ->where('assigned_student_id', $student->id);
                    });

                if ($classId) {
                    $q->orWhere(function ($sub) use ($classId) {
                        $sub->where('target_scope', 'class')
                            ->where('class_id', $classId);
                    });
                }
            });

        if (! empty($student->school_id)) {
            $query->where(function ($q) use ($student) {
                $q->whereNull('school_id')->orWhere('school_id', $student->school_id);
            });
        }

        if (! empty($filters['status'])) {
            if ($filters['status'] === 'submitted') {
                $query->whereHas('submissions', function ($q) use ($student) {
                    $q->where('student_id', $student->id);
                });
            } elseif ($filters['status'] === 'pending') {
                $query->whereDoesntHave('submissions', function ($q) use ($student) {
                    $q->where('student_id', $student->id);
                });
            }
        }

        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 100);

        return $query->orderBy('due_at', 'asc')->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Submit tugas program khusus dengan dukungan Idempotency-Key dan pencatatan audit log.
     */
    public function submitTask(string $taskId, Student $student, array $data, ?string $idempotencyKey = null, ?Request $request = null): array
    {
        $task = ProgramTask::where('id', $taskId)->first();
        if (! $task) {
            throw new HttpException(404, 'Tugas program tidak ditemukan.');
        }

        $effectiveProgram = $this->ensureStudentInProgram($student, $task->program_type);

        // Normalize payload representation for idempotency mismatch detection
        $payloadHash = hash('sha256', json_encode([
            'task_id' => $task->id,
            'student_id' => $student->id,
            'file_id' => $data['file_id'] ?? null,
            'link' => $data['link'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]));

        if ($idempotencyKey) {
            $cacheKey = "idemp:prog_submit:{$student->id}:{$idempotencyKey}";
            $cached = Cache::get($cacheKey);

            if ($cached) {
                $cachedData = is_array($cached) ? $cached : ['submission_id' => $cached, 'payload_hash' => $payloadHash];
                if (isset($cachedData['payload_hash']) && $cachedData['payload_hash'] !== $payloadHash) {
                    throw new HttpException(422, 'Idempotency-Key telah digunakan untuk request dengan parameter yang berbeda.');
                }

                $cachedSubmission = ProgramTaskSubmission::with(['task', 'file'])->find($cachedData['submission_id']);
                if ($cachedSubmission) {
                    return [
                        'submission' => $cachedSubmission,
                        'is_replay' => true,
                    ];
                }
            }
        }

        $submission = ProgramTaskSubmission::updateOrCreate(
            [
                'task_id' => $task->id,
                'student_id' => $student->id,
            ],
            [
                'file_id' => $data['file_id'] ?? null,
                'link' => $data['link'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => 'dikumpulkan',
                'submitted_at' => now(),
            ]
        );

        if ($idempotencyKey) {
            $cacheKey = "idemp:prog_submit:{$student->id}:{$idempotencyKey}";
            Cache::put($cacheKey, [
                'submission_id' => $submission->id,
                'payload_hash' => $payloadHash,
            ], 86400); // 24 jam
        }

        // Audit Log
        app(AuditLogService::class)->record(
            action: 'portal.program.task.submit',
            entity: 'ProgramTaskSubmission',
            entityId: $submission->id,
            newValues: [
                'task_id' => $task->id,
                'task_title' => $task->title,
                'student_id' => $student->id,
                'status' => 'dikumpulkan',
            ],
            request: $request,
            isAuthoritative: true
        );

        return [
            'submission' => $submission->load(['task', 'file']),
            'is_replay' => false,
        ];
    }

    // =========================================================================
    // SECTION 2: GURU PEMBINA / ADMIN (TEACHER & ADMIN) - BE-PROG-02
    // =========================================================================

    /**
     * Memeriksa hak akses dan cakupan binaan guru/admin terhadap program, kelas, atau siswa (Anti-IDOR).
     */
    public function ensureUserCanManageProgram(
        User $user,
        string $programType,
        ?string $classId = null,
        ?string $studentId = null
    ): bool {
        $user->loadMissing(['roles', 'teacher']);

        $isPrivileged = $user->hasRole('super_admin')
            || $user->hasRole('admin_tu')
            || $user->can('program.manage');

        if ($isPrivileged) {
            return true;
        }

        $teacher = $user->teacher;
        if (! $teacher) {
            throw new HttpException(403, 'Akses ditolak: Hanya guru pembina atau administrator yang dapat mengelola program ini.');
        }

        // Ambil ID semua kelas yang dibina guru (wali kelas atau guru pengajar)
        $homeroomClassIds = SchoolClass::where('homeroom_teacher_id', $teacher->id)->pluck('id')->toArray();
        $teachingClassIds = TeachingAssignment::where('teacher_id', $teacher->id)->pluck('class_id')->toArray();
        $allTaughtClassIds = array_unique(array_merge($homeroomClassIds, $teachingClassIds));

        // 1. Jika classId ditentukan
        if ($classId !== null) {
            if (! in_array($classId, $allTaughtClassIds, true)) {
                throw new HttpException(403, 'Akses ditolak: Anda bukan pengajar atau wali kelas pada kelas ini.');
            }
        }

        // 2. Jika studentId ditentukan
        if ($studentId !== null) {
            $student = Student::with('classes')->find($studentId);
            if (! $student) {
                throw new HttpException(404, 'Data siswa sasaran tidak ditemukan.');
            }

            $studentClassIds = $student->classes->pluck('id')->filter()->toArray();
            if ($student->class_id) {
                $studentClassIds[] = $student->class_id;
            }

            $hasOverlap = ! empty(array_intersect($studentClassIds, $allTaughtClassIds));
            if (! $hasOverlap) {
                throw new HttpException(403, 'Akses ditolak: Siswa ini berada di luar cakupan kelas binaan Anda.');
            }
        }

        // 3. Jika classId & studentId tidak ditentukan (lingkup materi/tugas seluruh program)
        if ($classId === null && $studentId === null) {
            $teachesProgram = SchoolClass::whereIn('id', $allTaughtClassIds)
                ->where('program_type', $programType)
                ->exists();

            if (! $teachesProgram) {
                throw new HttpException(403, "Akses ditolak: Anda tidak terdaftar sebagai pengajar atau pembina pada program {$programType}.");
            }
        }

        return true;
    }

    /**
     * Guru/Admin: Daftar materi program khusus dengan filter dan paginasi (bebas N+1).
     */
    public function listMaterialsForTeacher(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = ProgramMaterial::with(['creator:id,username', 'file', 'schoolClass:id,name,program_type', 'assignedStudent:id,full_name,nis'])
            ->orderBy('created_at', 'desc');

        if (! empty($user->school_id)) {
            $query->where(function ($q) use ($user) {
                $q->whereNull('school_id')->orWhere('school_id', $user->school_id);
            });
        }

        if (! empty($filters['program_type'])) {
            $query->where('program_type', $filters['program_type']);
        }

        if (! empty($filters['class_id'])) {
            $query->where('class_id', $filters['class_id']);
        }

        if (! empty($filters['material_type']) && $filters['material_type'] !== 'all') {
            $query->where('material_type', $filters['material_type']);
        }

        if (! empty($filters['q'])) {
            $keyword = '%'.$filters['q'].'%';
            $query->where(function ($q) use ($keyword) {
                $q->where('title', 'ilike', $keyword)
                    ->orWhere('description', 'ilike', $keyword);
            });
        }

        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 100);

        return $query->paginate($perPage);
    }

    /**
     * Guru/Admin: Tambah materi baru untuk program khusus.
     */
    public function createMaterial(User $user, array $data, ?Request $request = null): ProgramMaterial
    {
        $this->ensureUserCanManageProgram(
            $user,
            $data['program_type'],
            $data['class_id'] ?? null,
            $data['assigned_student_id'] ?? null
        );

        $material = ProgramMaterial::create([
            'school_id' => $user->school_id,
            'program_type' => $data['program_type'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'material_type' => $data['material_type'],
            'external_url' => $data['external_url'] ?? null,
            'file_id' => $data['file_id'] ?? null,
            'created_by' => $user->id,
            'class_id' => $data['class_id'] ?? null,
            'target_scope' => $data['target_scope'] ?? 'all',
            'assigned_student_id' => $data['assigned_student_id'] ?? null,
            'meta' => $data['meta'] ?? null,
        ]);

        app(AuditLogService::class)->record(
            action: 'program.material.create',
            entity: 'ProgramMaterial',
            entityId: $material->id,
            newValues: $material->only(['program_type', 'title', 'material_type', 'class_id']),
            actor: $user,
            request: $request,
            isAuthoritative: true
        );

        return $material->load(['creator:id,username', 'file', 'schoolClass:id,name', 'assignedStudent:id,full_name']);
    }

    /**
     * Guru/Admin: Perbarui materi program khusus.
     */
    public function updateMaterial(User $user, string $id, array $data, ?Request $request = null): ProgramMaterial
    {
        $material = ProgramMaterial::findOrFail($id);

        $this->ensureUserCanManageProgram(
            $user,
            $material->program_type,
            $material->class_id,
            $material->assigned_student_id
        );

        if (isset($data['class_id']) && $data['class_id'] !== $material->class_id) {
            $this->ensureUserCanManageProgram($user, $material->program_type, $data['class_id'], null);
        }

        $oldValues = $material->only(['title', 'description', 'material_type', 'external_url', 'class_id']);
        $material->update($data);

        app(AuditLogService::class)->record(
            action: 'program.material.update',
            entity: 'ProgramMaterial',
            entityId: $material->id,
            oldValues: $oldValues,
            newValues: $material->only(['title', 'description', 'material_type', 'external_url', 'class_id']),
            actor: $user,
            request: $request,
            isAuthoritative: true
        );

        return $material->load(['creator:id,username', 'file', 'schoolClass:id,name', 'assignedStudent:id,full_name']);
    }

    /**
     * Guru/Admin: Hapus materi program khusus (Soft Delete).
     */
    public function deleteMaterial(User $user, string $id, ?Request $request = null): bool
    {
        $material = ProgramMaterial::findOrFail($id);

        $this->ensureUserCanManageProgram(
            $user,
            $material->program_type,
            $material->class_id,
            $material->assigned_student_id
        );

        $oldValues = $material->only(['id', 'program_type', 'title']);
        $material->delete();

        app(AuditLogService::class)->record(
            action: 'program.material.delete',
            entity: 'ProgramMaterial',
            entityId: $id,
            oldValues: $oldValues,
            actor: $user,
            request: $request,
            isAuthoritative: true
        );

        return true;
    }

    /**
     * Guru/Admin: Daftar target siswa program khusus dengan filter dan paginasi (bebas N+1).
     */
    public function listTargetsForTeacher(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = ProgramTarget::with(['student:id,full_name,nis,class_id', 'setter:id,username'])
            ->orderBy('created_at', 'desc');

        if (! empty($user->school_id)) {
            $query->where(function ($q) use ($user) {
                $q->whereNull('school_id')->orWhere('school_id', $user->school_id);
            });
        }

        if (! empty($filters['program_type'])) {
            $query->where('program_type', $filters['program_type']);
        }

        if (! empty($filters['student_id'])) {
            $query->where('student_id', $filters['student_id']);
        }

        if (! empty($filters['domain']) && $filters['domain'] !== 'all') {
            $query->where('domain', $filters['domain']);
        }

        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['q'])) {
            $keyword = '%'.$filters['q'].'%';
            $query->where(function ($q) use ($keyword) {
                $q->where('title', 'ilike', $keyword)
                    ->orWhereHas('student', function ($sq) use ($keyword) {
                        $sq->where('full_name', 'ilike', $keyword)
                            ->orWhere('nis', 'ilike', $keyword);
                    });
            });
        }

        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 100);

        return $query->paginate($perPage);
    }

    /**
     * Guru/Admin: Buat target capaian baru untuk siswa sasaran.
     */
    public function createTarget(User $user, array $data, ?Request $request = null): ProgramTarget
    {
        $this->ensureUserCanManageProgram(
            $user,
            $data['program_type'],
            null,
            $data['student_id']
        );

        $target = ProgramTarget::create([
            'school_id' => $user->school_id,
            'student_id' => $data['student_id'],
            'program_type' => $data['program_type'],
            'title' => $data['title'],
            'domain' => $data['domain'] ?? null,
            'target_value' => $data['target_value'] ?? null,
            'current_value' => $data['current_value'] ?? null,
            'period' => $data['period'] ?? null,
            'status' => $data['status'] ?? 'aktif',
            'set_by' => $user->id,
        ]);

        app(AuditLogService::class)->record(
            action: 'program.target.create',
            entity: 'ProgramTarget',
            entityId: $target->id,
            newValues: $target->only(['student_id', 'program_type', 'title', 'status']),
            actor: $user,
            request: $request,
            isAuthoritative: true
        );

        return $target->load(['student:id,full_name,nis', 'setter:id,username']);
    }

    /**
     * Guru/Admin: Perbarui target capaian siswa.
     */
    public function updateTarget(User $user, string $id, array $data, ?Request $request = null): ProgramTarget
    {
        $target = ProgramTarget::findOrFail($id);

        $this->ensureUserCanManageProgram(
            $user,
            $target->program_type,
            null,
            $target->student_id
        );

        $oldValues = $target->only(['title', 'domain', 'target_value', 'current_value', 'period', 'status']);
        $target->update($data);

        app(AuditLogService::class)->record(
            action: 'program.target.update',
            entity: 'ProgramTarget',
            entityId: $target->id,
            oldValues: $oldValues,
            newValues: $target->only(['title', 'domain', 'target_value', 'current_value', 'period', 'status']),
            actor: $user,
            request: $request,
            isAuthoritative: true
        );

        return $target->load(['student:id,full_name,nis', 'setter:id,username']);
    }

    /**
     * Guru/Admin: Hapus target capaian program (Soft Delete).
     */
    public function deleteTarget(User $user, string $id, ?Request $request = null): bool
    {
        $target = ProgramTarget::findOrFail($id);

        $this->ensureUserCanManageProgram(
            $user,
            $target->program_type,
            null,
            $target->student_id
        );

        $oldValues = $target->only(['id', 'program_type', 'title', 'student_id']);
        $target->delete();

        app(AuditLogService::class)->record(
            action: 'program.target.delete',
            entity: 'ProgramTarget',
            entityId: $id,
            oldValues: $oldValues,
            actor: $user,
            request: $request,
            isAuthoritative: true
        );

        return true;
    }

    /**
     * Guru/Admin: Daftar tugas program khusus dengan filter dan paginasi (bebas N+1).
     */
    public function listTasksForTeacher(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = ProgramTask::with(['creator:id,username', 'file', 'schoolClass:id,name,program_type'])
            ->withCount('submissions')
            ->orderBy('created_at', 'desc');

        if (! empty($user->school_id)) {
            $query->where(function ($q) use ($user) {
                $q->whereNull('school_id')->orWhere('school_id', $user->school_id);
            });
        }

        if (! empty($filters['program_type'])) {
            $query->where('program_type', $filters['program_type']);
        }

        if (! empty($filters['class_id'])) {
            $query->where('class_id', $filters['class_id']);
        }

        if (! empty($filters['q'])) {
            $keyword = '%'.$filters['q'].'%';
            $query->where(function ($q) use ($keyword) {
                $q->where('title', 'ilike', $keyword)
                    ->orWhere('instructions', 'ilike', $keyword);
            });
        }

        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 100);

        return $query->paginate($perPage);
    }

    /**
     * Guru/Admin: Buat penugasan baru pada program khusus.
     */
    public function createTask(User $user, array $data, ?Request $request = null): ProgramTask
    {
        $this->ensureUserCanManageProgram(
            $user,
            $data['program_type'],
            $data['class_id'] ?? null,
            $data['assigned_student_id'] ?? null
        );

        $task = ProgramTask::create([
            'school_id' => $user->school_id,
            'program_type' => $data['program_type'],
            'title' => $data['title'],
            'instructions' => $data['instructions'] ?? null,
            'due_at' => $data['due_at'] ?? null,
            'file_id' => $data['file_id'] ?? null,
            'created_by' => $user->id,
            'class_id' => $data['class_id'] ?? null,
            'target_scope' => $data['target_scope'] ?? 'all',
            'assigned_student_id' => $data['assigned_student_id'] ?? null,
            'meta' => $data['meta'] ?? null,
        ]);

        app(AuditLogService::class)->record(
            action: 'program.task.create',
            entity: 'ProgramTask',
            entityId: $task->id,
            newValues: $task->only(['program_type', 'title', 'class_id', 'due_at']),
            actor: $user,
            request: $request,
            isAuthoritative: true
        );

        return $task->load(['creator:id,username', 'file', 'schoolClass:id,name']);
    }

    /**
     * Guru/Admin: Perbarui tugas program khusus.
     */
    public function updateTask(User $user, string $id, array $data, ?Request $request = null): ProgramTask
    {
        $task = ProgramTask::findOrFail($id);

        $this->ensureUserCanManageProgram(
            $user,
            $task->program_type,
            $task->class_id,
            $task->assigned_student_id
        );

        if (isset($data['class_id']) && $data['class_id'] !== $task->class_id) {
            $this->ensureUserCanManageProgram($user, $task->program_type, $data['class_id'], null);
        }

        $oldValues = $task->only(['title', 'instructions', 'due_at', 'class_id']);
        $task->update($data);

        app(AuditLogService::class)->record(
            action: 'program.task.update',
            entity: 'ProgramTask',
            entityId: $task->id,
            oldValues: $oldValues,
            newValues: $task->only(['title', 'instructions', 'due_at', 'class_id']),
            actor: $user,
            request: $request,
            isAuthoritative: true
        );

        return $task->load(['creator:id,username', 'file', 'schoolClass:id,name']);
    }

    /**
     * Guru/Admin: Hapus tugas program khusus (Soft Delete).
     */
    public function deleteTask(User $user, string $id, ?Request $request = null): bool
    {
        $task = ProgramTask::findOrFail($id);

        $this->ensureUserCanManageProgram(
            $user,
            $task->program_type,
            $task->class_id,
            $task->assigned_student_id
        );

        $oldValues = $task->only(['id', 'program_type', 'title']);
        $task->delete();

        app(AuditLogService::class)->record(
            action: 'program.task.delete',
            entity: 'ProgramTask',
            entityId: $id,
            oldValues: $oldValues,
            actor: $user,
            request: $request,
            isAuthoritative: true
        );

        return true;
    }

    /**
     * Guru/Admin: Daftar submission siswa untuk satu tugas program khusus.
     */
    public function listSubmissionsForTask(User $user, string $taskId, array $filters = []): LengthAwarePaginator
    {
        $task = ProgramTask::findOrFail($taskId);

        $this->ensureUserCanManageProgram(
            $user,
            $task->program_type,
            $task->class_id,
            $task->assigned_student_id
        );

        $query = ProgramTaskSubmission::with(['student:id,full_name,nis,class_id', 'file', 'grader:id,username'])
            ->where('task_id', $taskId)
            ->orderBy('submitted_at', 'desc');

        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 100);

        return $query->paginate($perPage);
    }

    /**
     * Guru/Admin: Beri nilai dan catatan umpan balik submission tugas siswa.
     */
    public function gradeSubmission(User $user, string $submissionId, array $data, ?Request $request = null): ProgramTaskSubmission
    {
        $submission = ProgramTaskSubmission::with(['task'])->findOrFail($submissionId);

        $this->ensureUserCanManageProgram(
            $user,
            $submission->task->program_type,
            $submission->task->class_id,
            $submission->student_id
        );

        $oldValues = $submission->only(['status', 'score', 'feedback', 'graded_by', 'graded_at']);

        $submission->update([
            'score' => $data['score'],
            'feedback' => $data['feedback'] ?? null,
            'status' => $data['status'] ?? 'dinilai',
            'graded_by' => $user->id,
            'graded_at' => now(),
        ]);

        app(AuditLogService::class)->record(
            action: 'program.submission.grade',
            entity: 'ProgramTaskSubmission',
            entityId: $submission->id,
            oldValues: $oldValues,
            newValues: $submission->only(['status', 'score', 'feedback', 'graded_by', 'graded_at']),
            actor: $user,
            request: $request,
            isAuthoritative: true
        );

        return $submission->load(['student:id,full_name,nis', 'file', 'grader:id,username']);
    }
}
