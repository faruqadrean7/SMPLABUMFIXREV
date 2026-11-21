<?php

namespace App\Http\Controllers\Api\V1\Sar;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sar\StoreAssetAuditRequest;
use App\Http\Requests\Sar\UpdateAssetAuditRequest;
use App\Services\Sar\AssetAuditService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class AssetAuditController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected AssetAuditService $service
    ) {}

    /**
     * BE-SAR-02: Daftar sesi stok opname aset.
     * GET /api/v1/asset-audits
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $paginator = $this->service->listAudits($request->all(), $request);

            return $this->paginatedResponse($paginator);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat daftar sesi opname aset.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-SAR-02: Detail sesi stok opname aset.
     * GET /api/v1/asset-audits/{id}
     */
    public function show(string $id, Request $request): JsonResponse
    {
        try {
            $audit = $this->service->getAudit($id, $request);

            return $this->successResponse($audit);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Sesi opname aset tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat detail sesi opname aset.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-SAR-02: Buat sesi stok opname baru.
     * POST /api/v1/asset-audits
     */
    public function store(StoreAssetAuditRequest $request): JsonResponse
    {
        try {
            $audit = $this->service->createAudit($request->validated(), $request);

            return $this->successResponse($audit, 201);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat menyimpan sesi opname aset.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-SAR-02: Perbarui sesi stok opname dan item temuan.
     * PATCH /api/v1/asset-audits/{id}
     */
    public function update(string $id, UpdateAssetAuditRequest $request): JsonResponse
    {
        try {
            $audit = $this->service->updateAudit($id, $request->validated(), $request);

            return $this->successResponse($audit);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Sesi opname aset tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memperbarui sesi opname aset.', 'INTERNAL_ERROR', 500);
        }
    }
}
