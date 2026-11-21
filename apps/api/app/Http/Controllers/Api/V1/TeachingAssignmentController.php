<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\BatchTeachingAssignmentRequest;
use App\Http\Requests\Master\StoreTeachingAssignmentRequest;
use App\Http\Requests\Master\UpdateTeachingAssignmentRequest;
use App\Services\SubjectAssignmentService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class TeachingAssignmentController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected SubjectAssignmentService $service
    ) {}

    /**
     * BE-MST-06: List Teaching Assignments
     * GET /api/v1/teaching-assignments
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $assignments = $this->service->listTeachingAssignments($request);

            return $this->paginatedResponse($assignments);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil daftar penugasan mengajar', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-MST-06: Create Single Teaching Assignment
     * POST /api/v1/teaching-assignments
     */
    public function store(StoreTeachingAssignmentRequest $request): JsonResponse
    {
        try {
            $assignment = $this->service->createTeachingAssignment($request->validated(), $request);

            return $this->successResponse($assignment, 201);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                400 => 'BUSINESS_RULE_VIOLATION',
                409 => 'CONFLICT',
                default => 'INTERNAL_ERROR',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal membuat penugasan mengajar', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-MST-06: Batch Assign Teaching Assignments
     * POST /api/v1/teaching-assignments/batch
     */
    public function batch(BatchTeachingAssignmentRequest $request): JsonResponse
    {
        try {
            $result = $this->service->batchAssign($request->validated(), $request);

            return $this->successResponse($result, 201);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                400 => 'BUSINESS_RULE_VIOLATION',
                409 => 'CONFLICT',
                default => 'INTERNAL_ERROR',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal membuat penugasan mengajar massal', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-MST-06: Show Teaching Assignment Detail
     * GET /api/v1/teaching-assignments/{id}
     */
    public function show(string $id): JsonResponse
    {
        try {
            $assignment = $this->service->getTeachingAssignment($id);

            return $this->successResponse($assignment);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Penugasan mengajar tidak ditemukan', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil data penugasan mengajar', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-MST-06: Update Teaching Assignment
     * PUT /api/v1/teaching-assignments/{id}
     */
    public function update(UpdateTeachingAssignmentRequest $request, string $id): JsonResponse
    {
        try {
            $assignment = $this->service->updateTeachingAssignment($id, $request->validated(), $request);

            return $this->successResponse($assignment);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Penugasan mengajar tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                400 => 'BUSINESS_RULE_VIOLATION',
                409 => 'CONFLICT',
                default => 'INTERNAL_ERROR',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal memperbarui penugasan mengajar', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-MST-06: Delete Teaching Assignment
     * DELETE /api/v1/teaching-assignments/{id}
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $this->service->deleteTeachingAssignment($id, $request);

            return $this->successResponse(['message' => 'Penugasan mengajar berhasil dihapus']);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Penugasan mengajar tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                400 => 'BUSINESS_RULE_VIOLATION',
                409 => 'CONFLICT',
                default => 'INTERNAL_ERROR',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal menghapus penugasan mengajar', 'INTERNAL_ERROR', 500);
        }
    }
}
