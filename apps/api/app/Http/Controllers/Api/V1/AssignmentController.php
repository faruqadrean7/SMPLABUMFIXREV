<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\GradeSubmissionRequest;
use App\Http\Requests\Academic\StoreAssignmentRequest;
use App\Http\Requests\Academic\SubmitAssignmentRequest;
use App\Http\Requests\Academic\UpdateAssignmentRequest;
use App\Services\LmsService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class AssignmentController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected LmsService $service
    ) {}

    /**
     * GET /api/v1/assignments/without-grade-component
     * Data lama: tugas yang belum ditautkan ke komponen buku nilai.
     * Tidak menghapus apa pun — hanya melaporkan agar bisa ditautkan.
     */
    public function withoutGradeComponent(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $query = \App\Models\Assignment::with(['class:id,name', 'subject:id,name,code'])
                ->whereNull('grade_component_id');

            // Guru hanya melihat tugasnya sendiri.
            $teacher = \App\Models\Teacher::where('user_id', $user?->id)->first();
            if ($teacher && ! $user->hasRole(['admin', 'super_admin'])) {
                $query->where('teacher_id', $teacher->id);
            }

            $rows = $query->orderByDesc('due_at')->limit(200)->get()->map(fn ($a) => [
                'id' => $a->id,
                'title' => $a->title,
                'class_id' => $a->class_id,
                'class_name' => $a->class?->name,
                'subject_id' => $a->subject_id,
                'subject_name' => $a->subject?->name,
                'due_at' => $a->due_at?->toISOString(),
            ])->values();

            return $this->successResponse([
                'count' => $rows->count(),
                'assignments' => $rows,
            ]);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal memuat tugas tanpa komponen nilai', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * POST /api/v1/assignments/{id}/link-grade-component
     * Menautkan tugas lama ke komponen buku nilai (tanpa menghapus data).
     */
    public function linkGradeComponent(string $id, Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'grade_component_id' => ['required', 'uuid', 'exists:grade_components,id'],
            ]);

            $assignment = \App\Models\Assignment::findOrFail($id);
            $component = \App\Models\GradeComponent::findOrFail($validated['grade_component_id']);

            // Komponen harus milik kelas & mapel yang sama dengan tugas.
            if ($component->class_id !== $assignment->class_id || $component->subject_id !== $assignment->subject_id) {
                return $this->errorResponse(
                    'Komponen nilai tidak sesuai dengan kelas dan mata pelajaran tugas ini.',
                    'VALIDATION_ERROR',
                    422
                );
            }

            $user = $request->user();
            $teacher = \App\Models\Teacher::where('user_id', $user?->id)->first();
            $isAdmin = $user && $user->hasRole(['admin', 'super_admin']);

            if (! $isAdmin && (! $teacher || $teacher->id !== $assignment->teacher_id)) {
                return $this->errorResponse(
                    'Hanya guru pemilik tugas atau admin yang dapat menautkan komponen nilai.',
                    'FORBIDDEN',
                    403
                );
            }

            $assignment->grade_component_id = $component->id;
            $assignment->save();

            return $this->successResponse([
                'assignment_id' => $assignment->id,
                'grade_component_id' => $component->id,
                'grade_component_name' => $component->name,
                'message' => 'Tugas berhasil ditautkan ke komponen buku nilai.',
            ]);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Tugas atau komponen nilai tidak ditemukan', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal menautkan komponen nilai', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-ACD-07: Daftar tugas kelas.
     * GET /api/v1/assignments
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $paginator = $this->service->listAssignments($request->all(), $request);

            return $this->paginatedResponse($paginator);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat mengambil daftar tugas', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-ACD-07: Buat tugas kelas baru.
     * POST /api/v1/assignments
     */
    public function store(StoreAssignmentRequest $request): JsonResponse
    {
        try {
            $assignment = $this->service->createAssignment($request->validated(), $request);

            return $this->successResponse($assignment, 201);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                403 => 'FORBIDDEN',
                422 => 'BUSINESS_RULE_VIOLATION',
                default => 'BAD_REQUEST',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat membuat tugas', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-ACD-07: Detail tugas kelas.
     * GET /api/v1/assignments/{id}
     */
    public function show(string $id, Request $request): JsonResponse
    {
        try {
            $assignment = $this->service->getAssignmentDetail($id, $request);

            return $this->successResponse($assignment);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Tugas tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat mengambil detail tugas', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-ACD-07: Perbarui tugas kelas.
     * PUT /api/v1/assignments/{id}
     */
    public function update(string $id, UpdateAssignmentRequest $request): JsonResponse
    {
        try {
            $assignment = $this->service->updateAssignment($id, $request->validated(), $request);

            return $this->successResponse($assignment);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Tugas tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                403 => 'FORBIDDEN',
                422 => 'BUSINESS_RULE_VIOLATION',
                default => 'BAD_REQUEST',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memperbarui tugas', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-ACD-07: Hapus tugas kelas.
     * DELETE /api/v1/assignments/{id}
     */
    public function destroy(string $id, Request $request): JsonResponse
    {
        try {
            $this->service->deleteAssignment($id, $request);

            return $this->successResponse(['message' => 'Tugas berhasil dihapus']);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Tugas tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat menghapus tugas', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-ACD-07: Daftar hasil pengumpulan tugas.
     * GET /api/v1/assignments/{id}/submissions
     */
    public function submissions(string $id, Request $request): JsonResponse
    {
        try {
            $submissions = $this->service->listSubmissions($id, $request);

            return $this->successResponse($submissions);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Tugas tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat mengambil daftar pengumpulan tugas', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-ACD-07: Siswa mengumpulkan tugas.
     * POST /api/v1/assignments/{id}/submissions
     */
    public function submit(string $id, SubmitAssignmentRequest $request): JsonResponse
    {
        try {
            $submission = $this->service->submitAssignment($id, $request->validated(), $request);

            return $this->successResponse($submission, 201);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Tugas tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                403 => 'FORBIDDEN',
                422 => 'BUSINESS_RULE_VIOLATION',
                default => 'BAD_REQUEST',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat mengumpulkan tugas', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-ACD-07: Guru memberi nilai & umpan balik tugas.
     * PATCH /api/v1/assignments/{assignment_id}/submissions/{submission_id}/grade
     */
    public function grade(string $assignmentId, string $submissionId, GradeSubmissionRequest $request): JsonResponse
    {
        try {
            $submission = $this->service->gradeSubmission($assignmentId, $submissionId, $request->validated(), $request);

            return $this->successResponse($submission);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Tugas atau pengumpulan tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memberikan nilai tugas', 'INTERNAL_ERROR', 500);
        }
    }
}
