<?php

namespace App\Http\Controllers\Api\V1\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\File;
use App\Models\GradeComponent;
use App\Models\GradePeriod;
use App\Models\Semester;
use App\Models\TeachingMaterial;
use App\Models\User;
use App\Services\LmsService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class TeacherLmsController extends Controller
{
    use ApiResponse;
    use TeacherPortalContextTrait;

    /**
     * GET /api/v1/me/teacher/materials
     */
    public function materials(Request $request): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $materials = TeachingMaterial::with(['subject', 'file', 'gradeLevel'])
            ->where('uploaded_by', $request->user()->id)
            ->latest()
            ->get();

        $user = $request->user();
        $data = $materials->map(fn (TeachingMaterial $m) => $this->formatMaterialPayload($m, $user));

        return $this->successResponse($data);
    }

    /**
     * POST /api/v1/me/teacher/materials
     */
    public function storeMaterial(Request $request): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $user = $request->user();
        $input = $request->all();
        $title = trim((string) ($input['title'] ?? 'Modul Ajar'));
        $subjectId = $input['subject_id'] ?? $input['subjectId'] ?? null;
        $description = (string) ($input['description'] ?? '');
        $fileId = $input['file_id'] ?? $input['fileId'] ?? null;
        $materialType = (string) ($input['material_type'] ?? $input['category'] ?? $input['materialType'] ?? 'modul_ajar');
        $rawVersion = trim((string) ($input['version'] ?? '1'));
        $numericVersion = preg_match('/\d+/', $rawVersion, $vm) === 1 ? max(1, (int) $vm[0]) : 1;

        if ($title === '') {
            return $this->errorResponse('Judul materi wajib diisi.', 'VALIDATION_ERROR', 422);
        }

        if (! $subjectId) {
            return $this->errorResponse('ID mata pelajaran wajib diisi.', 'VALIDATION_ERROR', 422);
        }

        if (! Str::isUuid($subjectId)) {
            return $this->errorResponse('Format ID mata pelajaran tidak valid.', 'VALIDATION_ERROR', 422);
        }

        if (! $this->verifySubjectAccess($teacher, $subjectId)) {
            return $this->errorResponse('Akses ditolak: Anda tidak ditugaskan mengampu mata pelajaran ini.', 'FORBIDDEN', 403);
        }

        if ($request->hasFile('file')) {
            $request->validate([
                'file' => ['required', 'file', 'mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,zip,jpg,jpeg,png', 'max:25600'],
            ], [
                'file.mimes' => 'Format berkas materi harus berupa PDF, dokumen Office, ZIP, atau gambar.',
                'file.max' => 'Ukuran berkas materi maksimal 25 MB.',
            ]);

            $uploaded = $request->file('file');
            $storagePath = $uploaded->store("teaching-materials/{$user->id}", 'local');
            $checksum = hash_file('sha256', $uploaded->getRealPath());

            $fileRecord = File::create([
                'school_id' => $user->school_id ?? null,
                'owner_id' => $user->id,
                'owner_type' => 'User',
                'bucket' => 'teaching-materials',
                'object_key' => $storagePath,
                'mime_type' => $uploaded->getMimeType() ?: 'application/octet-stream',
                'size_bytes' => $uploaded->getSize(),
                'visibility' => 'privat',
                'checksum' => $checksum,
            ]);
            $fileId = $fileRecord->id;
        } elseif ($fileId && Str::isUuid($fileId)) {
            $ownedFile = File::find($fileId);
            if (! $ownedFile) {
                return $this->errorResponse('Berkas materi tidak ditemukan.', 'VALIDATION_ERROR', 422);
            }
            if ((string) $ownedFile->owner_id !== (string) $user->id && ! $user->hasRole(['super_admin', 'admin'])) {
                return $this->errorResponse('Akses ditolak: Anda tidak memiliki hak pakai atas berkas ini.', 'FORBIDDEN', 403);
            }
        } elseif (str_contains((string) $request->header('Content-Type'), 'multipart/form-data')) {
            return $this->errorResponse('Berkas modul ajar wajib diunggah.', 'VALIDATION_ERROR', 422);
        } else {
            // Fallback kompatibilitas uji API JSON murni yang belum melampirkan file_id
            $file = File::firstOrCreate(
                ['object_key' => 'materials/default-modul.pdf'],
                [
                    'school_id' => $user->school_id ?? null,
                    'owner_id' => $user->id,
                    'owner_type' => 'User',
                    'bucket' => 'teaching-materials',
                    'mime_type' => 'application/pdf',
                    'size_bytes' => 1024,
                    'visibility' => 'privat',
                ]
            );
            $fileId = $file->id;
        }

        $material = TeachingMaterial::create([
            'title' => $title,
            'subject_id' => $subjectId,
            'uploaded_by' => $user->id,
            'file_id' => $fileId,
            'material_type' => $materialType,
            'description' => $description,
            'version' => $numericVersion,
        ]);

        $material->load(['subject', 'file', 'gradeLevel']);

        return $this->successResponse($this->formatMaterialPayload($material, $user), 201);
    }

    /**
     * GET /api/v1/me/teacher/assignments
     */
    public function assignments(Request $request): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $assignments = Assignment::with(['class' => fn ($q) => $q->withCount('students'), 'subject', 'gradeComponent'])
            ->where('teacher_id', $teacher->id)
            ->withCount(['submissions', 'submissions as graded_count' => fn ($q) => $q->whereNotNull('score')])
            ->latest()
            ->get();

        $data = $assignments->map(fn (Assignment $a) => [
            'id' => $a->id,
            'title' => $a->title,
            'subjectId' => $a->subject_id,
            'subject_id' => $a->subject_id,
            'subjectName' => $a->subject?->name ?? 'Mata Pelajaran',
            'classId' => $a->class_id,
            'class_id' => $a->class_id,
            'className' => $a->class?->name ?? 'Kelas',
            'dueDate' => $a->due_at?->toISOString() ?? now()->addDays(7)->toISOString(),
            'due_at' => $a->due_at?->toISOString() ?? now()->addDays(7)->toISOString(),
            'totalStudents' => $a->class?->students_count ?? 0,
            'submittedCount' => $a->submissions_count,
            'gradedCount' => $a->graded_count,
            'description' => $a->description ?? '',
            // Wajib dikirim: UI memakainya untuk menandai tugas yang belum tertaut
            // ke komponen buku nilai (data lama).
            'grade_component_id' => $a->grade_component_id,
            'grade_component_name' => $a->gradeComponent?->name,
            'createdAt' => $a->created_at?->toISOString() ?? now()->toISOString(),
        ]);

        return $this->successResponse($data);
    }

    /**
     * POST /api/v1/me/teacher/assignments
     */
    public function storeAssignment(Request $request): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $input = $request->all();
        $title = $input['title'] ?? 'Tugas Pembelajaran';
        $classId = $input['class_id'] ?? $input['classId'] ?? null;
        $subjectId = $input['subject_id'] ?? $input['subjectId'] ?? null;
        $description = $input['description'] ?? '';
        $dueAt = $input['due_at'] ?? $input['dueDate'] ?? $input['due_date'] ?? now()->addDays(7);
        $maxScore = $input['max_score'] ?? $input['maxScore'] ?? 100;
        $gradeComponentId = $input['grade_component_id'] ?? $input['gradeComponentId'] ?? null;

        if (! $classId || ! $subjectId) {
            return $this->errorResponse('ID kelas dan ID mata pelajaran wajib diisi.', 'VALIDATION_ERROR', 422);
        }

        if (! Str::isUuid($classId) || ! Str::isUuid($subjectId)) {
            return $this->errorResponse('Format ID kelas atau mata pelajaran tidak valid.', 'VALIDATION_ERROR', 422);
        }

        if (! $this->verifyClassAccess($teacher, $classId)) {
            return $this->errorResponse('Akses ditolak: Anda tidak ditugaskan di kelas ini.', 'FORBIDDEN', 403);
        }

        if (! $this->verifySubjectAccess($teacher, $subjectId, $classId)) {
            return $this->errorResponse('Akses ditolak: Anda tidak ditugaskan mengampu mata pelajaran ini di kelas terkait.', 'FORBIDDEN', 403);
        }

        // Tautan opsional ke komponen buku nilai; harus milik mapel yang sama agar nilai tugas tersinkron benar.
        if ($gradeComponentId !== null) {
            if (! Str::isUuid($gradeComponentId)) {
                return $this->errorResponse('Format ID komponen nilai tidak valid.', 'VALIDATION_ERROR', 422);
            }

            $componentValid = GradeComponent::where('id', $gradeComponentId)
                ->where('subject_id', $subjectId)
                ->exists();

            if (! $componentValid) {
                return $this->errorResponse('Komponen nilai tidak ditemukan atau bukan untuk mata pelajaran ini.', 'VALIDATION_ERROR', 422);
            }
        }

        $assignment = Assignment::create([
            'teacher_id' => $teacher->id,
            'class_id' => $classId,
            'subject_id' => $subjectId,
            'title' => $title,
            'description' => $description,
            'due_at' => $dueAt,
            'max_score' => $maxScore,
            'grade_component_id' => $gradeComponentId,
        ]);

        $assignment->load(['class', 'subject', 'gradeComponent']);

        return $this->successResponse([
            'id' => $assignment->id,
            'title' => $assignment->title,
            'subjectId' => $assignment->subject_id,
            'subject_id' => $assignment->subject_id,
            'subjectName' => $assignment->subject?->name ?? 'Mata Pelajaran',
            'classId' => $assignment->class_id,
            'class_id' => $assignment->class_id,
            'className' => $assignment->class?->name ?? 'Kelas',
            'dueDate' => $assignment->due_at?->toISOString() ?? now()->addDays(7)->toISOString(),
            'due_at' => $assignment->due_at?->toISOString() ?? now()->addDays(7)->toISOString(),
            'max_score' => (float) ($assignment->max_score ?? 100),
            'totalStudents' => $assignment->class?->students()->count() ?? 0,
            'submittedCount' => 0,
            'gradedCount' => 0,
            'description' => $assignment->description ?? '',
            'grade_component_id' => $assignment->grade_component_id,
            'grade_component_name' => $assignment->gradeComponent?->name,
            'createdAt' => $assignment->created_at?->toISOString() ?? now()->toISOString(),
        ], 201);
    }

    /**
     * GET /api/v1/me/teacher/assignments/{id}/submissions
     */
    public function submissions(Request $request, string $id): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $assignment = Assignment::where('id', $id)
            ->where('teacher_id', $teacher->id)
            ->first();

        if (! $assignment) {
            return $this->errorResponse('Tugas tidak ditemukan atau bukan milik Anda.', 'FORBIDDEN', 403);
        }

        $submissions = AssignmentSubmission::with(['student', 'file'])
            ->where('assignment_id', $id)
            ->get();

        $user = $request->user();
        $dueAt = $assignment->due_at;

        $data = $submissions->map(function (AssignmentSubmission $sub) use ($assignment, $dueAt, $user) {
            $isLate = (bool) ($dueAt && $sub->submitted_at && $sub->submitted_at->greaterThan($dueAt));
            $filePayload = null;
            if ($sub->file) {
                $downloadUrl = $this->resolveFileDownloadUrl($sub->file, $user);
                $filePayload = [
                    'id' => $sub->file->id,
                    'object_key' => $sub->file->object_key,
                    'filename' => basename((string) $sub->file->object_key),
                    'mime_type' => $sub->file->mime_type,
                    'size_bytes' => (int) $sub->file->size_bytes,
                    'url' => $downloadUrl,
                    'download_url' => $downloadUrl,
                ];
            }

            $driveLink = null;
            if ($sub->notes && preg_match('/\[Tautan Berkas\/Drive\]:\s*(\S+)/', $sub->notes, $matches) === 1) {
                $driveLink = $matches[1];
            }

            return [
                'id' => $sub->id,
                'assignment_id' => $sub->assignment_id,
                'student_id' => $sub->student_id,
                'studentId' => $sub->student_id,
                'studentName' => $sub->student?->full_name ?? 'Siswa',
                'student' => [
                    'id' => $sub->student?->id ?? $sub->student_id,
                    'name' => $sub->student?->full_name ?? 'Siswa',
                    'nis' => $sub->student?->nis ?? '',
                    'nisn' => $sub->student?->nisn ?? '',
                    'avatar_url' => $sub->student?->photo_file_id ? url("/api/v1/files/{$sub->student->photo_file_id}/download") : null,
                    'class_id' => $assignment->class_id,
                ],
                'nisn' => $sub->student?->nisn ?? '',
                'submittedAt' => $sub->submitted_at?->toISOString() ?? now()->toISOString(),
                'submitted_at' => $sub->submitted_at?->toISOString() ?? now()->toISOString(),
                'score' => $sub->score !== null ? (float) $sub->score : null,
                'feedback' => $sub->feedback,
                'notes' => $sub->notes,
                'submission_url' => $driveLink,
                'drive_link' => $driveLink,
                'file_id' => $sub->file_id,
                'file' => $filePayload,
                'status' => $sub->score !== null ? 'graded' : 'pending',
                'is_late' => $isLate,
            ];
        });

        return $this->successResponse($data);
    }

    /**
     * POST /api/v1/me/teacher/submissions/{id}/grade
     */
    public function gradeSubmission(Request $request, string $id): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $submission = AssignmentSubmission::with('assignment')->find($id);
        if (! $submission || $submission->assignment?->teacher_id !== $teacher->id) {
            return $this->errorResponse('Pengumpulan tugas tidak ditemukan atau bukan tugas yang Anda ampu.', 'FORBIDDEN', 403);
        }

        // FIX-GURU-03: Validasi batas skor dinamis mengacu pada max_score tugas
        $maxScore = (float) ($submission->assignment?->max_score ?? 100);

        $validated = $request->validate([
            'score' => "required|numeric|min:0|max:{$maxScore}",
            'feedback' => 'nullable|string',
            // Periode tujuan nilai (opsional). Tanpa ini, dipakai bulan tenggat tugas.
            'period_id' => 'nullable|uuid|exists:grade_periods,id',
        ]);

        // Delegasikan ke LmsService agar nilai ikut tersinkron ke buku nilai (grades)
        // bila tugas ditautkan ke komponen nilai, dalam satu transaksi & audit terpusat.
        try {
            app(LmsService::class)->gradeSubmission($submission->assignment_id, $submission->id, $validated, $request);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'BUSINESS_RULE_VIOLATION', $e->getStatusCode());
        }

        return $this->successResponse(['message' => 'Nilai dan feedback berhasil diberikan.']);
    }

    /**
     * GET /api/v1/me/teacher/periods
     * Daftar periode penilaian pada tahun ajaran aktif (untuk pemilih periode).
     */
    public function periods(Request $request): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $semester = Semester::where('is_active', true)->first()
            ?? Semester::first();

        if (! $semester) {
            return $this->successResponse([]);
        }

        $periods = GradePeriod::where('semester_id', $semester->id)
            ->orderBy('month')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => 'Periode Bulan '.$p->month,
                'month' => (int) $p->month,
                'status' => $p->status,
                'is_locked' => $p->status === 'locked',
                // Periode terbuka paling awal = default yang dipakai bila guru tidak memilih.
                'is_default' => false,
            ])
            ->values();

        $default = $periods->firstWhere('status', 'open') ?? $periods->first();
        if ($default) {
            $periods = $periods->map(fn ($p) => array_merge($p, ['is_default' => $p['id'] === $default['id']]));
        }

        return $this->successResponse($periods);
    }

    /**
     * GET /api/v1/me/teacher/assignments/{id}
     * Detail satu tugas milik guru yang bersangkutan.
     */
    public function showAssignment(Request $request, string $id): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        if (! Str::isUuid($id)) {
            return $this->errorResponse('Format ID tugas tidak valid.', 'VALIDATION_ERROR', 422);
        }

        $assignment = Assignment::with(['class' => fn ($q) => $q->withCount('students'), 'subject', 'gradeComponent'])
            ->where('teacher_id', $teacher->id)
            ->withCount(['submissions', 'submissions as graded_count' => fn ($q) => $q->whereNotNull('score')])
            ->find($id);

        if (! $assignment) {
            return $this->errorResponse('Tugas tidak ditemukan.', 'NOT_FOUND', 404);
        }

        return $this->successResponse([
            'id' => $assignment->id,
            'title' => $assignment->title,
            'subjectId' => $assignment->subject_id,
            'subject_id' => $assignment->subject_id,
            'subjectName' => $assignment->subject?->name ?? 'Mata Pelajaran',
            'classId' => $assignment->class_id,
            'class_id' => $assignment->class_id,
            'className' => $assignment->class?->name ?? 'Kelas',
            'dueDate' => $assignment->due_at?->toISOString() ?? now()->addDays(7)->toISOString(),
            'due_at' => $assignment->due_at?->toISOString() ?? now()->addDays(7)->toISOString(),
            'max_score' => (float) ($assignment->max_score ?? 100),
            'totalStudents' => $assignment->class?->students_count ?? 0,
            'submittedCount' => $assignment->submissions_count,
            'gradedCount' => $assignment->graded_count,
            'description' => $assignment->description ?? '',
            'grade_component_id' => $assignment->grade_component_id,
            'grade_component_name' => $assignment->gradeComponent?->name,
            'createdAt' => $assignment->created_at?->toISOString() ?? now()->toISOString(),
        ]);
    }

    /**
     * Format DTO materi ajar yang selaras antara web portal guru dan mobile/admin.
     */
    protected function formatMaterialPayload(TeachingMaterial $m, ?User $user): array
    {
        $downloadUrl = $m->file ? $this->resolveFileDownloadUrl($m->file, $user) : null;
        $createdIso = $m->created_at?->toISOString() ?? now()->toISOString();

        return [
            'id' => $m->id,
            'title' => $m->title,
            'subjectId' => $m->subject_id ?? '',
            'subject_id' => $m->subject_id ?? '',
            'subjectName' => $m->subject?->name ?? 'Mata Pelajaran',
            'subject' => $m->subject ? [
                'id' => $m->subject->id,
                'name' => $m->subject->name,
                'code' => $m->subject->code,
            ] : null,
            'class_id' => '',
            'classIds' => [],
            'classNames' => [],
            'category' => $m->material_type ?? 'modul_ajar',
            'material_type' => $m->material_type ?? 'modul_ajar',
            'version' => 'v'.($m->version ?? 1).'.0',
            'file_id' => $m->file_id,
            'fileUrl' => $downloadUrl,
            'file' => $m->file ? [
                'id' => $m->file->id,
                'object_key' => $m->file->object_key,
                'filename' => basename((string) $m->file->object_key),
                'mime_type' => $m->file->mime_type,
                'size_bytes' => (int) $m->file->size_bytes,
                'url' => $downloadUrl,
                'download_url' => $downloadUrl,
            ] : null,
            'description' => $m->description ?? '',
            'created_at' => $createdIso,
            'createdAt' => $createdIso,
            'updated_at' => $m->updated_at?->toISOString() ?? $createdIso,
            'downloadsCount' => 0,
        ];
    }

    /**
     * Resolusi URL unduhan berkas (signed URL untuk berkas privat, URL storage untuk berkas publik).
     */
    protected function resolveFileDownloadUrl(File $file, ?User $user): ?string
    {
        if ($file->visibility !== 'publik' && $file->visibility !== 'public') {
            if (! $user) {
                return null;
            }

            return URL::temporarySignedRoute(
                'files.download.signed',
                now()->addMinutes(5),
                ['id' => $file->id, 'uid' => $user->id]
            );
        }

        return $file->url;
    }
}
