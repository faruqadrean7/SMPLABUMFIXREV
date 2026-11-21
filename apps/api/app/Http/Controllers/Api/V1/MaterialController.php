<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\StoreMaterialRequest;
use App\Http\Requests\Academic\UpdateMaterialRequest;
use App\Services\LmsService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class MaterialController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected LmsService $service
    ) {}

    /**
     * BE-ACD-07: Daftar materi pembelajaran.
     * GET /api/v1/materials
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $paginator = $this->service->listMaterials($request->all(), $request);

            return $this->paginatedResponse($paginator);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat mengambil daftar materi pembelajaran', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-ACD-07: Unggah materi pembelajaran baru.
     * POST /api/v1/materials
     */
    public function store(StoreMaterialRequest $request): JsonResponse
    {
        try {
            $material = $this->service->createMaterial($request->validated(), $request);

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

            return $this->errorResponse('Terjadi kesalahan saat mengunggah materi pembelajaran', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-ACD-07: Detail materi pembelajaran.
     * GET /api/v1/materials/{id}
     */
    public function show(string $id, Request $request): JsonResponse
    {
        try {
            $material = $this->service->getMaterialDetail($id, $request);

            return $this->successResponse($material);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Materi pembelajaran tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat mengambil detail materi', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-ACD-07: Perbarui materi pembelajaran.
     * PUT /api/v1/materials/{id}
     */
    public function update(string $id, UpdateMaterialRequest $request): JsonResponse
    {
        try {
            $material = $this->service->updateMaterial($id, $request->validated(), $request);

            return $this->successResponse($material);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Materi pembelajaran tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                403 => 'FORBIDDEN',
                422 => 'BUSINESS_RULE_VIOLATION',
                default => 'BAD_REQUEST',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memperbarui materi pembelajaran', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-ACD-07: Hapus materi pembelajaran.
     * DELETE /api/v1/materials/{id}
     */
    public function destroy(string $id, Request $request): JsonResponse
    {
        try {
            $this->service->deleteMaterial($id, $request);

            return $this->successResponse(['message' => 'Materi pembelajaran berhasil dihapus']);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Materi pembelajaran tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat menghapus materi pembelajaran', 'INTERNAL_ERROR', 500);
        }
    }
}
