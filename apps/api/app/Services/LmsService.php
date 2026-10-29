<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\AuditLog;
use App\Models\ClassStudent;
use App\Models\File;
use App\Models\Grade;
use App\Models\GradePeriod;
use App\Models\Guardian;
use App\Models\LearningMaterial;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class LmsService
{
    // =========================================================================
    // 1. Learning Materials (Materi Pembelajaran)
    // =========================================================================

    /**
     * BE-ACD-07: Daftar materi pembelajaran dengan filter & scoping peran.
     */
    public function listMaterials(array $filters, Request $request): LengthAwarePaginator
    {
        /** @var User $currentUser */
        $currentUser = $request->user();
        $student = Student::where('user_id', $currentUser->id)->first();
        $guardian = Guardian::where('user_id', $currentUser->id)->first();
        $isAdmin = $this->checkIsAdmin($currentUser);

        $query = LearningMaterial::with(['class', 'subject', 'teacher', 'file'])
            ->orderBy('created_at', 'desc');

        if (! $isAdmin) {
            if ($student) {
                // Siswa hanya melihat materi pada kelas-kelas yang diikutinya
                $studentClassIds = ClassStudent::where('student_id', $student->id)->pluck('class_id');
                $query->whereIn('class_id', $studentClassIds);
            } elseif ($guardian) {
                // Wali murid melihat materi kelas anak-anaknya
                $childIds = $guardian->students()->pluck('students.id');
                $childClassIds = ClassStudent::whereIn('student_id', $childIds)->pluck('class_id');
                $query->whereIn('class_id', $childClassIds);
            }
        }

        if (! empty($filters['class_id'])) {
            $query->where('class_id', $filters['class_id']);
        }

        if (! empty($filters['subject_id'])) {
            $query->where('subject_id', $filters['subject_id']);
        }

        if (! empty($filters['teacher_id'])) {
            $query->where('teacher_id', $filters['teacher_id']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $perPage = (int) ($filters['per_page'] ?? 15);

        return $query->paginate($perPage);
    }

    /**
     * BE-ACD-07: Unggah materi pembelajaran baru.
     */
    public function createMaterial(array $data, Request $request): LearningMaterial
    {
        /** @var User $currentUser */
        $currentUser = $request->user();
        $teacher = Teacher::where('user_id', $currentUser->id)->first();
        $isAdmin = $this->checkIsAdmin($currentUser);

        if (! $isAdmin && ! $teacher) {
            throw new HttpException(403, 'Hanya guru atau administrator yang dapat mengunggah materi pembelajaran.');
        }

        $teacherId = $teacher ? $teacher->id : Teacher::first()?->id;

        $fileId = $data['file_id'] ?? null;
        if ($request->hasFile('file')) {
            $uploaded = $request->file('file');
            $storagePath = $uploaded->store("learning-materials/{$currentUser->id}", 'local');
            $checksum = hash_file('sha256', $uploaded->getRealPath());

            $fileRecord = File::create([
                'owner_id' => $currentUser->id,
                'owner_type' => 'User',
                'bucket' => 'learning-materials',
                'object_key' => $storagePath,
                'mime_type' => $uploaded->getMimeType() ?: 'application/octet-stream',
                'size_bytes' => $uploaded->getSize(),
                'visibility' => 'privat',
                'checksum' => $checksum,
            ]);

            $fileId = $fileRecord->id;
        }

        return DB::transaction(function () use ($data, $teacherId, $fileId, $currentUser, $request) {
            $material = LearningMaterial::create([
                'class_id' => $data['class_id'],
                'subject_id' => $data['subject_id'],
                'teacher_id' => $teacherId,
                'title' => trim($data['title']),
                'description' => isset($data['description']) ? trim($data['description']) : null,
                'file_id' => $fileId,
            ]);

            $this->logAudit(
                $currentUser->id,
                'learning_material.created',
                'LearningMaterial',
                $material->id,
                null,
                $material->toArray(),
                $request
            );

            return $material->load(['class', 'subject', 'teacher', 'file']);
        });
    }

    /**
     * BE-ACD-07: Detail materi pembelajaran.
     */
    public function getMaterialDetail(string $id, Request $request): LearningMaterial
    {
        /** @var User $currentUser */
        $currentUser = $request->user();
        $student = Student::where('user_id', $currentUser->id)->first();
        $isAdmin = $this->checkIsAdmin($currentUser);

        $material = LearningMaterial::with(['class', 'subject', 'teacher', 'file'])->findOrFail($id);

        if (! $isAdmin && $student) {
            $isEnrolled = ClassStudent::where('class_id', $material->class_id)
                ->where('student_id', $student->id)
                ->exists();
            if (! $isEnrolled) {
                throw new HttpException(403, 'Anda tidak terdaftar pada kelas materi pembelajaran ini.');
            }
        }

        return $material;
    }

    /**
     * BE-ACD-07: Perbarui materi pembelajaran.
     */
    public function updateMaterial(string $id, array $data, Request $request): LearningMaterial
    {
        /** @var User $currentUser */
        $currentUser = $request->user();
        $material = LearningMaterial::findOrFail($id);
        $teacher = Teacher::where('user_id', $currentUser->id)->first();
        $isAdmin = $this->checkIsAdmin($currentUser);

        if (! $isAdmin && (! $teacher || $teacher->id !== $material->teacher_id)) {
            throw new HttpException(403, 'Anda tidak memiliki hak akses untuk mengubah materi pembelajaran ini.');
        }

        $fileId = $material->file_id;
        if ($request->hasFile('file')) {
            $uploaded = $request->file('file');
            $storagePath = $uploaded->store("learning-materials/{$currentUser->id}", 'local');
            $checksum = hash_file('sha256', $uploaded->getRealPath());

            $fileRecord = File::create([
                'owner_id' => $currentUser->id,
                'owner_type' => 'User',
                'bucket' => 'learning-materials',
                'object_key' => $storagePath,
                'mime_type' => $uploaded->getMimeType() ?: 'application/octet-stream',
                'size_bytes' => $uploaded->getSize(),
                'visibility' => 'privat',
                'checksum' => $checksum,
            ]);

            $fileId = $fileRecord->id;
        } elseif (array_key_exists('file_id', $data)) {
            $fileId = $data['file_id'];
        }

        return DB::transaction(function () use ($material, $data, $fileId, $currentUser, $request) {
            $oldData = $material->toArray();

            if (isset($data['title'])) {
                $material->title = trim($data['title']);
            }
            if (array_key_exists('description', $data)) {
                $material->description = $data['description'] !== null ? trim($data['description']) : null;
            }
            $material->file_id = $fileId;
            $material->save();

            $this->logAudit(
                $currentUser->id,
                'learning_material.updated',
                'LearningMaterial',
                $material->id,
                $oldData,
                $material->toArray(),
                $request
            );

            return $material->load(['class', 'subject', 'teacher', 'file']);
        });
    }

    /**
     * BE-ACD-07: Hapus materi pembelajaran.
     */
    public function deleteMaterial(string $id, Request $request): bool
    {
        /** @var User $currentUser */
        $currentUser = $request->user();
        $material = LearningMaterial::findOrFail($id);
        $teacher = Teacher::where('user_id', $currentUser->id)->first();
        $isAdmin = $this->checkIsAdmin($currentUser);

        if (! $isAdmin && (! $teacher || $teacher->id !== $material->teacher_id)) {
            throw new HttpException(403, 'Anda tidak memiliki hak akses untuk menghapus materi pembelajaran ini.');
        }

        return DB::transaction(function () use ($material, $currentUser, $request) {
            $oldData = $material->toArray();

            $material->delete();

            $this->logAudit(
                $currentUser->id,
                'learning_material.deleted',
                'LearningMaterial',
                $material->id,
                $oldData,
                null,
                $request
            );

            return true;
        });
    }

    // =========================================================================
    // 2. Assignments (Tugas Kelas)
    // =========================================================================

    /**
     * BE-ACD-07: Daftar tugas kelas dengan filter & status pengumpulan.
     */
    public function listAssignments(array $filters, Request $request): LengthAwarePaginator
    {
        /** @var User $currentUser */
        $currentUser = $request->user();
        $student = Student::where('user_id', $currentUser->id)->first();
        $guardian = Guardian::where('user_id', $currentUser->id)->first();
        $isAdmin = $this->checkIsAdmin($currentUser);

        $query = Assignment::with(['class', 'subject', 'teacher', 'file', 'gradeComponent'])
            ->withCount('submissions')
            ->orderBy('due_at', 'desc');

        if (! $isAdmin) {
            if ($student) {
                $studentClassIds = ClassStudent::where('student_id', $student->id)->pluck('class_id');
                $query->whereIn('class_id', $studentClassIds);
            } elseif ($guardian) {
                $childIds = $guardian->students()->pluck('students.id');
                $childClassIds = ClassStudent::whereIn('student_id', $childIds)->pluck('class_id');
                $query->whereIn('class_id', $childClassIds);
            }
        }

        if (! empty($filters['class_id'])) {
            $query->where('class_id', $filters['class_id']);
        }

        if (! empty($filters['subject_id'])) {
            $query->where('subject_id', $filters['subject_id']);
        }

        if (! empty($filters['teacher_id'])) {
            $query->where('teacher_id', $filters['teacher_id']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $perPage = (int) ($filters['per_page'] ?? 15);
        $paginator = $query->paginate($perPage);

        // Jika request dari siswa, lampirkan submission miliknya ke tiap item
        if ($student) {
            $assignmentIds = collect($paginator->items())->pluck('id');
            $mySubmissions = AssignmentSubmission::where('student_id', $student->id)
                ->whereIn('assignment_id', $assignmentIds)
                ->get()
                ->keyBy('assignment_id');

            foreach ($paginator->items() as $item) {
                $item->setRelation('my_submission', $mySubmissions->get($item->id));
            }
        }

        return $paginator;
    }

    /**
     * BE-ACD-07: Buat tugas kelas baru.
     */
    public function createAssignment(array $data, Request $request): Assignment
    {
        /** @var User $currentUser */
        $currentUser = $request->user();
        $teacher = Teacher::where('user_id', $currentUser->id)->first();
        $isAdmin = $this->checkIsAdmin($currentUser);

        if (! $isAdmin && ! $teacher) {
            throw new HttpException(403, 'Hanya guru atau administrator yang dapat membuat tugas pembelajaran.');
        }

        $teacherId = $teacher ? $teacher->id : Teacher::first()?->id;

        $fileId = $data['file_id'] ?? null;
        if ($request->hasFile('file')) {
            $uploaded = $request->file('file');
            $storagePath = $uploaded->store("assignments/{$currentUser->id}", 'local');
            $checksum = hash_file('sha256', $uploaded->getRealPath());

            $fileRecord = File::create([
                'owner_id' => $currentUser->id,
                'owner_type' => 'User',
                'bucket' => 'assignments',
                'object_key' => $storagePath,
                'mime_type' => $uploaded->getMimeType() ?: 'application/octet-stream',
                'size_bytes' => $uploaded->getSize(),
                'visibility' => 'privat',
                'checksum' => $checksum,
            ]);

            $fileId = $fileRecord->id;
        }

        return DB::transaction(function () use ($data, $teacherId, $fileId, $currentUser, $request) {
            $assignment = Assignment::create([
                'class_id' => $data['class_id'],
                'subject_id' => $data['subject_id'],
                'teacher_id' => $teacherId,
                'title' => trim($data['title']),
                'description' => isset($data['description']) ? trim($data['description']) : null,
                'file_id' => $fileId,
                'due_at' => $data['due_at'],
                'max_score' => $data['max_score'] ?? 100.00,
                'grade_component_id' => $data['grade_component_id'] ?? null,
            ]);

            $this->logAudit(
                $currentUser->id,
                'assignment.created',
                'Assignment',
                $assignment->id,
                null,
                $assignment->toArray(),
                $request
            );

            return $assignment->load(['class', 'subject', 'teacher', 'file', 'gradeComponent']);
        });
    }

    /**
     * BE-ACD-07: Detail tugas kelas.
     */
    public function getAssignmentDetail(string $id, Request $request): Assignment
    {
        /** @var User $currentUser */
        $currentUser = $request->user();
        $student = Student::where('user_id', $currentUser->id)->first();
        $isAdmin = $this->checkIsAdmin($currentUser);

        $assignment = Assignment::with(['class', 'subject', 'teacher', 'file', 'gradeComponent'])
            ->withCount('submissions')
            ->findOrFail($id);

        if (! $isAdmin && $student) {
            $isEnrolled = ClassStudent::where('class_id', $assignment->class_id)
                ->where('student_id', $student->id)
                ->exists();
            if (! $isEnrolled) {
                throw new HttpException(403, 'Anda tidak terdaftar pada kelas tugas ini.');
            }

            $mySubmission = AssignmentSubmission::with(['file'])
                ->where('assignment_id', $assignment->id)
                ->where('student_id', $student->id)
                ->first();

            $assignment->setRelation('my_submission', $mySubmission);
        }

        return $assignment;
    }

    /**
     * BE-ACD-07: Perbarui tugas kelas.
     */
    public function updateAssignment(string $id, array $data, Request $request): Assignment
    {
        /** @var User $currentUser */
        $currentUser = $request->user();
        $assignment = Assignment::findOrFail($id);
        $teacher = Teacher::where('user_id', $currentUser->id)->first();
        $isAdmin = $this->checkIsAdmin($currentUser);

        if (! $isAdmin && (! $teacher || $teacher->id !== $assignment->teacher_id)) {
            throw new HttpException(403, 'Anda tidak memiliki hak akses untuk mengubah tugas ini.');
        }

        $fileId = $assignment->file_id;
        if ($request->hasFile('file')) {
            $uploaded = $request->file('file');
            $storagePath = $uploaded->store("assignments/{$currentUser->id}", 'local');
            $checksum = hash_file('sha256', $uploaded->getRealPath());

            $fileRecord = File::create([
                'owner_id' => $currentUser->id,
                'owner_type' => 'User',
                'bucket' => 'assignments',
                'object_key' => $storagePath,
                'mime_type' => $uploaded->getMimeType() ?: 'application/octet-stream',
                'size_bytes' => $uploaded->getSize(),
                'visibility' => 'privat',
                'checksum' => $checksum,
            ]);

            $fileId = $fileRecord->id;
        } elseif (array_key_exists('file_id', $data)) {
            $fileId = $data['file_id'];
        }

        return DB::transaction(function () use ($assignment, $data, $fileId, $currentUser, $request) {
            $oldData = $assignment->toArray();

            if (isset($data['title'])) {
                $assignment->title = trim($data['title']);
            }
            if (array_key_exists('description', $data)) {
                $assignment->description = $data['description'] !== null ? trim($data['description']) : null;
            }
            if (isset($data['due_at'])) {
                $assignment->due_at = $data['due_at'];
            }
            if (isset($data['max_score'])) {
                $assignment->max_score = $data['max_score'];
            }
            if (array_key_exists('grade_component_id', $data)) {
                $assignment->grade_component_id = $data['grade_component_id'];
            }
            $assignment->file_id = $fileId;
            $assignment->save();

            $this->logAudit(
                $currentUser->id,
                'assignment.updated',
                'Assignment',
                $assignment->id,
                $oldData,
                $assignment->toArray(),
                $request
            );

            return $assignment->load(['class', 'subject', 'teacher', 'file', 'gradeComponent']);
        });
    }

    /**
     * BE-ACD-07: Hapus tugas kelas.
     */
    public function deleteAssignment(string $id, Request $request): bool
    {
        /** @var User $currentUser */
        $currentUser = $request->user();
        $assignment = Assignment::findOrFail($id);
        $teacher = Teacher::where('user_id', $currentUser->id)->first();
        $isAdmin = $this->checkIsAdmin($currentUser);

        if (! $isAdmin && (! $teacher || $teacher->id !== $assignment->teacher_id)) {
            throw new HttpException(403, 'Anda tidak memiliki hak akses untuk menghapus tugas ini.');
        }

        return DB::transaction(function () use ($assignment, $currentUser, $request) {
            $oldData = $assignment->toArray();

            $assignment->delete();

            $this->logAudit(
                $currentUser->id,
                'assignment.deleted',
                'Assignment',
                $assignment->id,
                $oldData,
                null,
                $request
            );

            return true;
        });
    }

    // =========================================================================
    // 3. Submissions & Grading (Pengumpulan & Penilaian Tugas)
    // =========================================================================

    /**
     * BE-ACD-07: Daftar pengumpulan tugas (Guru melihat seluruh siswa kelas, siswa hanya miliknya).
     */
    public function listSubmissions(string $assignmentId, Request $request): Collection
    {
        /** @var User $currentUser */
        $currentUser = $request->user();
        $assignment = Assignment::findOrFail($assignmentId);
        $student = Student::where('user_id', $currentUser->id)->first();
        $teacher = Teacher::where('user_id', $currentUser->id)->first();
        $isAdmin = $this->checkIsAdmin($currentUser);

        $query = AssignmentSubmission::with(['student', 'file'])
            ->where('assignment_id', $assignment->id)
            ->orderBy('submitted_at', 'desc');

        if (! $isAdmin) {
            if ($student) {
                // Siswa hanya melihat submission miliknya
                $query->where('student_id', $student->id);
            } elseif ($teacher) {
                // Guru pembuat tugas atau guru kelas
                if ($teacher->id !== $assignment->teacher_id) {
                    throw new HttpException(403, 'Anda bukan guru penugasan dari tugas ini.');
                }
            } else {
                throw new HttpException(403, 'Akses ditolak.');
            }
        }

        return $query->get();
    }

    /**
     * BE-ACD-07: Siswa mengumpulkan tugas.
     */
    public function submitAssignment(string $assignmentId, array $data, Request $request): AssignmentSubmission
    {
        /** @var User $currentUser */
        $currentUser = $request->user();
        $student = Student::where('user_id', $currentUser->id)->first();

        if (! $student) {
            throw new HttpException(403, 'Hanya siswa terdaftar yang dapat mengumpulkan tugas.');
        }

        $assignment = Assignment::findOrFail($assignmentId);

        // Verifikasi siswa benar-benar terdaftar di kelas tugas ini
        $isEnrolled = ClassStudent::where('class_id', $assignment->class_id)
            ->where('student_id', $student->id)
            ->exists();

        if (! $isEnrolled) {
            throw new HttpException(403, 'Anda tidak terdaftar di kelas tugas ini.');
        }

        // Simpan berkas pengumpulan
        $fileId = $data['file_id'] ?? null;
        if ($request->hasFile('file')) {
            $uploaded = $request->file('file');
            $storagePath = $uploaded->store("assignment-submissions/{$assignment->id}/{$student->id}", 'local');
            $checksum = hash_file('sha256', $uploaded->getRealPath());

            $fileRecord = File::create([
                'owner_id' => $student->id,
                'owner_type' => 'Student',
                'bucket' => 'assignment-submissions',
                'object_key' => $storagePath,
                'mime_type' => $uploaded->getMimeType() ?: 'application/octet-stream',
                'size_bytes' => $uploaded->getSize(),
                'visibility' => 'privat',
                'checksum' => $checksum,
            ]);

            $fileId = $fileRecord->id;
        }

        if (! $fileId && empty($data['drive_link']) && empty($data['notes']) && empty($data['file_name'])) {
            throw new HttpException(422, 'Berkas pengerjaan, tautan berkas/drive, atau catatan tugas wajib disertakan.');
        }

        return DB::transaction(function () use ($assignment, $student, $fileId, $data, $currentUser, $request) {
            $existing = AssignmentSubmission::where('assignment_id', $assignment->id)
                ->where('student_id', $student->id)
                ->first();

            if ($existing && $existing->score !== null) {
                throw new HttpException(422, 'Tugas ini sudah dinilai oleh guru dan tidak dapat diubah kembali.');
            }

            $submission = $existing ?? new AssignmentSubmission([
                'assignment_id' => $assignment->id,
                'student_id' => $student->id,
            ]);

            $submission->file_id = $fileId;
            $combinedNotes = $data['notes'] ?? '';
            if (! empty($data['drive_link'])) {
                $linkPrefix = '[Tautan Berkas/Drive]: '.$data['drive_link'];
                $combinedNotes = $combinedNotes ? ($combinedNotes."\n".$linkPrefix) : $linkPrefix;
            }
            if (! empty($data['file_name']) && ! empty($data['drive_link'])) {
                $namePrefix = '[Nama Berkas]: '.$data['file_name'];
                $combinedNotes = $namePrefix."\n".$combinedNotes;
            }
            if ($combinedNotes) {
                $submission->notes = trim($combinedNotes);
            }
            $submission->submitted_at = Carbon::now();
            $submission->save();

            $this->logAudit(
                $currentUser->id,
                'assignment.submitted',
                'AssignmentSubmission',
                $submission->id,
                null,
                $submission->toArray(),
                $request
            );

            return $submission->load(['assignment', 'student', 'file']);
        });
    }

    /**
     * BE-ACD-07: Guru memberi nilai & umpan balik tugas (sinkronisasi ke komponen nilai).
     */
    public function gradeSubmission(string $assignmentId, string $submissionId, array $data, Request $request): AssignmentSubmission
    {
        /** @var User $currentUser */
        $currentUser = $request->user();
        $assignment = Assignment::with(['gradeComponent'])->findOrFail($assignmentId);
        $teacher = Teacher::where('user_id', $currentUser->id)->first();
        $isAdmin = $this->checkIsAdmin($currentUser);

        if (! $isAdmin && (! $teacher || $teacher->id !== $assignment->teacher_id)) {
            throw new HttpException(403, 'Hanya guru penugasan yang dapat menilai hasil tugas ini.');
        }

        $submission = AssignmentSubmission::where('assignment_id', $assignment->id)->findOrFail($submissionId);

        return DB::transaction(function () use ($submission, $assignment, $data, $currentUser, $request) {
            $oldData = $submission->toArray();

            $submission->score = $data['score'];
            if (isset($data['feedback'])) {
                $submission->feedback = trim($data['feedback']);
            }
            $submission->save();

            // Masuk komponen nilai (tabel grades) bila assignment ditautkan ke grade_component_id
            if ($assignment->grade_component_id) {
                $semesterId = $assignment->gradeComponent?->semester_id;
                if ($semesterId) {
                    $currentMonth = (int) Carbon::now()->format('m');
                    $period = GradePeriod::where('semester_id', $semesterId)
                        ->where('month', $currentMonth)
                        ->first();

                    if (! $period) {
                        $period = GradePeriod::create([
                            'semester_id' => $semesterId,
                            'month' => $currentMonth,
                            'status' => 'open',
                        ]);
                    }

                    if ($period->status === 'locked') {
                        throw new HttpException(422, 'Periode penilaian telah dikunci. Penilaian tugas tidak dapat dimasukkan ke buku nilai tanpa pembukaan kunci.');
                    }

                    Grade::updateOrCreate(
                        [
                            'student_id' => $submission->student_id,
                            'component_id' => $assignment->grade_component_id,
                            'period_id' => $period->id,
                        ],
                        [
                            'value' => $data['score'],
                            'source' => 'manual',
                            'entered_by' => $currentUser->id,
                        ]
                    );
                }
            }

            $this->logAudit(
                $currentUser->id,
                'assignment.graded',
                'AssignmentSubmission',
                $submission->id,
                $oldData,
                $submission->toArray(),
                $request
            );

            return $submission->load(['assignment', 'student', 'file']);
        });
    }

    /**
     * Cek apakah user adalah administrator atau kepala sekolah.
     */
    protected function checkIsAdmin(User $user): bool
    {
        return $user->hasRole('super_admin')
            || $user->hasRole('admin_tu')
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
