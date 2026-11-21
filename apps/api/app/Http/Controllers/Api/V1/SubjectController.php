<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreSubjectRequest;
use App\Http\Requests\Master\UpdateSubjectRequest;
use App\Services\SubjectAssignmentService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class SubjectController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected SubjectAssignmentService $service
    ) {}

    /**
     * BE-MST-06: List Subjects
     * GET /api/v1/subjects
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $subjects = $this->service->listSubjects($request);

            return $this->paginatedResponse($subjects);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil daftar mata pelajaran', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-MST-06: Create Subject
     * POST /api/v1/subjects
     */
    public function store(StoreSubjectRequest $request): JsonResponse
    {
        try {
            $subject = $this->service->createSubject($request->validated(), $request);

            return $this->successResponse($subject, 201);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                400 => 'BUSINESS_RULE_VIOLATION',
                409 => 'CONFLICT',
                default => 'INTERNAL_ERROR',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal menambahkan mata pelajaran baru', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-MST-06: Show Subject Detail
     * GET /api/v1/subjects/{id}
     */
    public function show(string $id): JsonResponse
    {
        try {
            $subject = $this->service->getSubject($id);

            return $this->successResponse($subject);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Mata pelajaran tidak ditemukan', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil data mata pelajaran', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-MST-06: Update Subject
     * PUT /api/v1/subjects/{id}
     */
    public function update(UpdateSubjectRequest $request, string $id): JsonResponse
    {
        try {
            $subject = $this->service->updateSubject($id, $request->validated(), $request);

            return $this->successResponse($subject);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Mata pelajaran tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                400 => 'BUSINESS_RULE_VIOLATION',
                409 => 'CONFLICT',
                default => 'INTERNAL_ERROR',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal memperbarui data mata pelajaran', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-MST-06: Delete Subject (Soft delete)
     * DELETE /api/v1/subjects/{id}
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $this->service->deleteSubject($id, $request);

            return $this->successResponse(['message' => 'Mata pelajaran berhasil dihapus']);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Mata pelajaran tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                400 => 'BUSINESS_RULE_VIOLATION',
                409 => 'CONFLICT',
                default => 'INTERNAL_ERROR',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal menghapus mata pelajaran', 'INTERNAL_ERROR', 500);
        }
    }
}
