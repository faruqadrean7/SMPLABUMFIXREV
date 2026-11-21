<?php

namespace App\Http\Controllers\Api\V1\Std;

use App\Http\Controllers\Controller;
use App\Http\Requests\Std\ListPointTypeRequest;
use App\Http\Requests\Std\StorePointThresholdRequest;
use App\Http\Requests\Std\StorePointTypeRequest;
use App\Http\Requests\Std\UpdatePointThresholdRequest;
use App\Http\Requests\Std\UpdatePointTypeRequest;
use App\Services\Std\StudentPointService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class PointTypeController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected StudentPointService $service
    ) {}

    // =========================================================================
    // POINT TYPES
    // =========================================================================

    public function indexTypes(ListPointTypeRequest $request): JsonResponse
    {
        try {
            $paginator = $this->service->listPointTypes($request->validated());

            return $this->paginatedResponse($paginator);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat master jenis poin.', 'INTERNAL_ERROR', 500);
        }
    }

    public function showType(string $id): JsonResponse
    {
        try {
            $pointType = $this->service->getPointType($id);

            return $this->successResponse($pointType);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Jenis poin tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat detail jenis poin.', 'INTERNAL_ERROR', 500);
        }
    }

    public function storeType(StorePointTypeRequest $request): JsonResponse
    {
        try {
            $pointType = $this->service->createPointType($request->validated(), $request);

            return $this->successResponse($pointType, 201);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal menambahkan jenis poin baru.', 'INTERNAL_ERROR', 500);
        }
    }

    public function updateType(string $id, UpdatePointTypeRequest $request): JsonResponse
    {
        try {
            $pointType = $this->service->updatePointType($id, $request->validated(), $request);

            return $this->successResponse($pointType);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Jenis poin tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal memperbarui data jenis poin.', 'INTERNAL_ERROR', 500);
        }
    }

    public function destroyType(string $id, Request $request): JsonResponse
    {
        try {
            $this->service->deletePointType($id, $request);

            return $this->successResponse(['message' => 'Jenis poin berhasil dihapus.']);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Jenis poin tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal menghapus jenis poin.', 'INTERNAL_ERROR', 500);
        }
    }

    // =========================================================================
    // POINT THRESHOLDS
    // =========================================================================

    public function indexThresholds(): JsonResponse
    {
        try {
            $thresholds = $this->service->listThresholds();

            return $this->successResponse($thresholds);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat daftar ambang sanksi poin.', 'INTERNAL_ERROR', 500);
        }
    }

    public function showThreshold(string $id): JsonResponse
    {
        try {
            $threshold = $this->service->getThreshold($id);

            return $this->successResponse($threshold);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Ambang batas poin tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat detail ambang batas poin.', 'INTERNAL_ERROR', 500);
        }
    }

    public function storeThreshold(StorePointThresholdRequest $request): JsonResponse
    {
        try {
            $threshold = $this->service->createThreshold($request->validated(), $request);

            return $this->successResponse($threshold, 201);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal menambahkan ambang batas poin baru.', 'INTERNAL_ERROR', 500);
        }
    }

    public function updateThreshold(string $id, UpdatePointThresholdRequest $request): JsonResponse
    {
        try {
            $threshold = $this->service->updateThreshold($id, $request->validated(), $request);

            return $this->successResponse($threshold);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Ambang batas poin tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal memperbarui ambang batas poin.', 'INTERNAL_ERROR', 500);
        }
    }

    public function destroyThreshold(string $id, Request $request): JsonResponse
    {
        try {
            $this->service->deleteThreshold($id, $request);

            return $this->successResponse(['message' => 'Ambang batas poin berhasil dihapus.']);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Ambang batas poin tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal menghapus ambang batas poin.', 'INTERNAL_ERROR', 500);
        }
    }
}
