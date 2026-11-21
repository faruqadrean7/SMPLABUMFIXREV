<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\CreateTeachingMaterialVersionRequest;
use App\Http\Requests\Academic\ListTeachingMaterialRequest;
use App\Http\Requests\Academic\StoreTeachingMaterialRequest;
use App\Services\TeachingMaterialService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class TeachingMaterialController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TeachingMaterialService $service
    ) {}

    /**
     * BE-ACD-06: Daftar perangkat ajar.
     * GET /api/v1/teaching-materials
     */
    public function index(ListTeachingMaterialRequest $request): JsonResponse
    {
        try {
            $paginator = $this->service->listTeachingMaterials($request->validated(), $request);

            return $this->paginatedResponse($paginator);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat mengambil daftar perangkat ajar', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-ACD-06: Unggah perangkat ajar baru.
     * POST /api/v1/teaching-materials
     */
    public function store(StoreTeachingMaterialRequest $request): JsonResponse
    {
        try {
            $material = $this->service->createTeachingMaterial($request->validated(), $request);

            return $this->successResponse($material, 201);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                403 => 'FORBIDDEN',
                422 => 'BUSINESS_RULE_VIOLATION',
                default => 'BAD_REQUEST',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat mengunggah perangkat ajar', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-ACD-06: Detail perangkat ajar beserta versi.
     * GET /api/v1/teaching-materials/{id}
     */
    public function show(string $id): JsonResponse
    {
        try {
            $material = $this->service->getTeachingMaterialDetail($id);

            return $this->successResponse($material);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Perangkat ajar tidak ditemukan', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat mengambil detail perangkat ajar', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-ACD-06: Unggah versi revisi perangkat ajar.
     * POST /api/v1/teaching-materials/{id}/versions
     */
    public function createVersion(string $id, CreateTeachingMaterialVersionRequest $request): JsonResponse
    {
        try {
            $version = $this->service->createVersion($id, $request->validated(), $request);

            return $this->successResponse($version, 201);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Perangkat ajar asal tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                403 => 'FORBIDDEN',
                422 => 'BUSINESS_RULE_VIOLATION',
                default => 'BAD_REQUEST',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat mengunggah revisi perangkat ajar', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-ACD-06: Hapus perangkat ajar.
     * DELETE /api/v1/teaching-materials/{id}
     */
    public function destroy(string $id, Request $request): JsonResponse
    {
        try {
            $this->service->deleteTeachingMaterial($id, $request);

            return $this->successResponse(['message' => 'Perangkat ajar berhasil dihapus']);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Perangkat ajar tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                403 => 'FORBIDDEN',
                default => 'BAD_REQUEST',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat menghapus perangkat ajar', 'INTERNAL_ERROR', 500);
        }
    }
}
