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
