<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\CharacterNote;
use App\Models\ClassStudent;
use App\Models\GradePeriod;
use App\Models\Guardian;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CharacterNoteService
{
    /**
     * BE-GRD-02: Daftar catatan karakter dengan filter dan otorisasi IDOR.
     */
    public function listCharacterNotes(array $filters, Request $request): LengthAwarePaginator
    {
        /** @var User $currentUser */
        $currentUser = $request->user();
        $student = Student::where('user_id', $currentUser->id)->first();
        $guardian = Guardian::where('user_id', $currentUser->id)->first();
        $teacher = Teacher::where('user_id', $currentUser->id)->first();
        $isAdmin = $this->checkIsAdmin($currentUser);

        $query = CharacterNote::with(['student', 'period.semester', 'author'])
            ->orderBy('created_at', 'desc');

        if (! $isAdmin) {
            if ($student) {
                // Siswa hanya melihat catatan karakternya sendiri
                $query->where('student_id', $student->id);
            } elseif ($guardian) {
                // Wali murid hanya melihat anak-anaknya
                $childIds = $guardian->students()->pluck('students.id');
                $query->whereIn('student_id', $childIds);
            } elseif ($teacher && ! $currentUser->hasRole('counselor')) {
                // Walas melihat kelas perwaliannya
                $homeroomClassIds = SchoolClass::where('homeroom_teacher_id', $teacher->id)->pluck('id');
                $query->whereHas('student.classes', fn ($q) => $q->whereIn('classes.id', $homeroomClassIds));
            }
        }

        if (! empty($filters['student_id'])) {
            $query->where('student_id', $filters['student_id']);
        }

        if (! empty($filters['period_id'])) {
            $query->where('period_id', $filters['period_id']);
        }

        if (! empty($filters['class_id'])) {
            $classId = $filters['class_id'];
            $studentIds = ClassStudent::where('class_id', $classId)->pluck('student_id');
            $query->whereIn('student_id', $studentIds);
        }

        $perPage = (int) ($filters['per_page'] ?? 20);

        return $query->paginate($perPage);
    }

    /**
     * BE-GRD-02: Simpan atau perbarui catatan karakter per siswa.
     */
    public function storeCharacterNote(array $data, Request $request): CharacterNote
    {
        /** @var User $currentUser */
        $currentUser = $request->user();

        $this->authorizeHomeroom($currentUser, $data['student_id']);

        $period = GradePeriod::findOrFail($data['period_id']);
        if ($period->status === 'locked') {
            throw new HttpException(422, 'Periode penilaian telah dikunci. Perubahan catatan karakter tidak diizinkan tanpa pembukaan kunci oleh admin.');
        }

        return DB::transaction(function () use ($data, $currentUser, $request) {
            $note = CharacterNote::updateOrCreate(
                [
                    'student_id' => $data['student_id'],
                    'period_id' => $data['period_id'],
                ],
                [
                    'scale_data' => $data['scale_data'],
                    'homeroom_note' => isset($data['homeroom_note']) ? trim($data['homeroom_note']) : null,
                    'author_id' => $currentUser->id,
                ]
            );

            $this->logAudit(
                $currentUser->id,
                'character_note.saved',
                'CharacterNote',
                $note->id,
                null,
                $note->toArray(),
                $request
            );

            return $note->load(['student', 'period', 'author']);
        });
    }

    /**
     * BE-GRD-02: Input massal catatan karakter rombel kelas.
     */
    public function bulkStoreCharacterNotes(array $data, Request $request): array
    {
        /** @var User $currentUser */
        $currentUser = $request->user();

        $period = GradePeriod::findOrFail($data['period_id']);
        if ($period->status === 'locked') {
            throw new HttpException(422, 'Periode penilaian telah dikunci. Perubahan catatan karakter tidak diizinkan tanpa pembukaan kunci oleh admin.');
        }

        return DB::transaction(function () use ($data, $currentUser, $request) {
            $savedNotes = [];

            foreach ($data['notes'] as $item) {
                $this->authorizeHomeroom($currentUser, $item['student_id']);

                $note = CharacterNote::updateOrCreate(
                    [
                        'student_id' => $item['student_id'],
                        'period_id' => $data['period_id'],
                    ],
                    [
                        'scale_data' => $item['scale_data'],
                        'homeroom_note' => isset($item['homeroom_note']) ? trim($item['homeroom_note']) : null,
                        'author_id' => $currentUser->id,
                    ]
                );

                $savedNotes[] = $note;
            }

            $this->logAudit(
                $currentUser->id,
                'character_note.bulk_saved',
                'CharacterNote',
                $data['period_id'],
                null,
                ['count' => count($savedNotes)],
                $request
            );

            return [
                'count' => count($savedNotes),
                'period_id' => $data['period_id'],
                'items' => $savedNotes,
            ];
        });
    }

    /**
     * BE-GRD-02 / ADM-GRD-02: Tampilan massal catatan karakter satu rombel kelas.
     */
    public function getClassCharacterNotes(array $filters, Request $request): array
    {
        /** @var User $currentUser */
        $currentUser = $request->user();

        if (empty($filters['class_id']) || empty($filters['period_id'])) {
            throw new HttpException(422, 'Parameter class_id dan period_id wajib disertakan.');
        }

        $classId = $filters['class_id'];
        $periodId = $filters['period_id'];

        $schoolClass = SchoolClass::with(['gradeLevel', 'academicYear', 'homeroomTeacher'])->findOrFail($classId);
        $period = GradePeriod::with('semester.academicYear')->findOrFail($periodId);

        // Otorisasi IDOR: Jika bukan admin, harus wali kelas dari rombel ini
        if (! $this->checkIsAdmin($currentUser)) {
            $teacher = Teacher::where('user_id', $currentUser->id)->first();
            if (! $teacher || $schoolClass->homeroom_teacher_id !== $teacher->id) {
                throw new HttpException(403, 'Anda bukan wali kelas dari rombel kelas ini.');
            }
        }

        // Ambil siswa aktif di rombel kelas
        $enrolledStudentIds = ClassStudent::where('class_id', $classId)->pluck('student_id');
        $students = Student::whereIn('id', $enrolledStudentIds)
            ->whereIn('status', ['aktif', 'active'])
            ->orderBy('nisn', 'asc')
            ->get(['id', 'nisn', 'nis', 'full_name', 'gender']);

        // Ambil catatan karakter untuk periode ini
        $notes = CharacterNote::with('author')
            ->where('period_id', $periodId)
            ->whereIn('student_id', $enrolledStudentIds)
            ->get()
            ->keyBy('student_id');

        $studentRows = [];
        $completeCount = 0;
        $incompleteCount = 0;

        foreach ($students as $stu) {
            /** @var CharacterNote|null $note */
            $note = $notes->get($stu->id);
            $hasNote = $note !== null;
            $scaleData = $hasNote ? ($note->scale_data ?? []) : [];
            $homeroomNote = $hasNote ? $note->homeroom_note : null;

            // Hitung kelengkapan 8 dimensi profil pelajar pancasila & catatan walas
            $nonEmptyDimensions = is_array($scaleData)
                ? array_filter($scaleData, fn ($v) => $v !== null && $v !== '')
                : [];
            $dimensionsCount = count($nonEmptyDimensions);
            $allDimensionsComplete = $dimensionsCount >= 8;
            $hasHomeroomNote = ! empty(trim((string) $homeroomNote));

            if (! $hasNote || $dimensionsCount === 0) {
                $completenessStatus = 'belum_dibuat';
            } elseif ($allDimensionsComplete && $hasHomeroomNote) {
                $completenessStatus = 'lengkap';
            } else {
                $completenessStatus = 'belum_lengkap';
            }

            $isComplete = $hasNote && ! empty($scaleData);
            if ($isComplete) {
                $completeCount++;
            } else {
                $incompleteCount++;
            }

            $studentRows[] = [
                'student' => [
                    'id' => $stu->id,
                    'nisn' => $stu->nisn,
                    'nis' => $stu->nis,
                    'name' => $stu->name,
                    'full_name' => $stu->full_name,
                    'gender' => $stu->gender,
                ],
                'note' => $note,
                'scale_data' => $scaleData,
                'homeroom_note' => $homeroomNote,
                'is_complete' => $isComplete,
                'completeness_status' => $completenessStatus,
                'all_dimensions_complete' => $allDimensionsComplete,
                'dimensions_count' => $dimensionsCount,
                'updated_at' => $note?->updated_at,
            ];
        }

        $totalStudents = $students->count();
        $completionRate = $totalStudents > 0 ? round(($completeCount / $totalStudents) * 100, 2) : 0;

        return [
            'class' => $schoolClass,
            'period' => $period,
            'is_locked' => $period->status === 'locked',
            'students' => $studentRows,
            'summary' => [
                'total_students' => $totalStudents,
                'complete_count' => $completeCount,
                'incomplete_count' => $incompleteCount,
                'completion_rate' => $completionRate,
            ],
        ];
    }

    /**
     * BE-GRD-02: Detail catatan karakter.
     */
    public function getCharacterNoteDetail(string $id, Request $request): CharacterNote
    {
        /** @var User $currentUser */
        $currentUser = $request->user();
        $student = Student::where('user_id', $currentUser->id)->first();
        $guardian = Guardian::where('user_id', $currentUser->id)->first();
        $isAdmin = $this->checkIsAdmin($currentUser);

        $note = CharacterNote::with(['student', 'period.semester', 'author'])->findOrFail($id);

        if (! $isAdmin) {
            if ($student && $student->id !== $note->student_id) {
                throw new HttpException(403, 'Anda tidak memiliki hak akses melihat catatan karakter siswa lain.');
            }
            if ($guardian) {
                $isChild = $guardian->students()->where('students.id', $note->student_id)->exists();
                if (! $isChild) {
                    throw new HttpException(403, 'Anda tidak memiliki hak akses melihat catatan karakter siswa ini.');
                }
            }
        }

        return $note;
    }

    /**
     * BE-GRD-02: Hapus catatan karakter.
     */
    public function deleteCharacterNote(string $id, Request $request): bool
    {
        /** @var User $currentUser */
        $currentUser = $request->user();
        $note = CharacterNote::findOrFail($id);
        $isAdmin = $this->checkIsAdmin($currentUser);

        if (! $isAdmin && $note->author_id !== $currentUser->id) {
            throw new HttpException(403, 'Anda tidak memiliki hak akses untuk menghapus catatan karakter ini.');
        }

        $period = $note->period;
        if ($period && $period->status === 'locked') {
            throw new HttpException(422, 'Periode penilaian telah dikunci. Catatan karakter tidak dapat dihapus.');
        }

        return DB::transaction(function () use ($note, $currentUser, $request) {
            $oldData = $note->toArray();
            $note->delete();

            $this->logAudit(
                $currentUser->id,
                'character_note.deleted',
                'CharacterNote',
                $note->id,
                $oldData,
                null,
                $request
            );

            return true;
        });
    }

    /**
     * Otorisasi apakah pengguna adalah wali kelas siswa atau admin.
     */
    protected function authorizeHomeroom(User $user, string $studentId): void
    {
        if ($this->checkIsAdmin($user)) {
            return;
        }

        $teacher = Teacher::where('user_id', $user->id)->first();
        if (! $teacher) {
            throw new HttpException(403, 'Hanya wali kelas atau administrator yang berhak menginput catatan karakter.');
        }

        $isWalas = SchoolClass::where('homeroom_teacher_id', $teacher->id)
            ->whereHas('students', fn ($q) => $q->where('students.id', $studentId))
            ->exists();

        if (! $isWalas) {
            throw new HttpException(403, 'Anda bukan wali kelas dari siswa bersangkutan.');
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
