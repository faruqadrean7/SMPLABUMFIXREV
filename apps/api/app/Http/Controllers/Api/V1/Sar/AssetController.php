<?php

namespace App\Http\Controllers\Api\V1\Sar;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sar\ListAssetRequest;
use App\Http\Requests\Sar\StoreAssetRequest;
use App\Http\Requests\Sar\UpdateAssetRequest;
use App\Services\Sar\AssetService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class AssetController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected AssetService $service
    ) {}

    /**
     * BE-SAR-02: Ringkasan metrik inventaris dan kondisi aset.
     * GET /api/v1/assets/summary
     */
    public function summary(Request $request): JsonResponse
    {
        try {
            $summary = $this->service->getSummary($request);

            return $this->successResponse($summary);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat ringkasan aset.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-SAR-02: Daftar aset berpaginasi.
     * GET /api/v1/assets
     */
    public function index(ListAssetRequest $request): JsonResponse
    {
        try {
            $paginator = $this->service->listAssets($request->validated(), $request);

            return $this->paginatedResponse($paginator);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat daftar aset.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-SAR-02: Detail aset berdasarkan ID.
     * GET /api/v1/assets/{id}
     */
    public function show(string $id, Request $request): JsonResponse
    {
        try {
            $asset = $this->service->getAsset($id, $request);

            return $this->successResponse($asset);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Aset tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat detail aset.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-SAR-02: Scan / lookup aset berdasarkan kode QR/barcode.
     * GET /api/v1/assets/by-code/{code}
     */
    public function byCode(string $code, Request $request): JsonResponse
    {
        try {
            $asset = $this->service->getAssetByCode($code, $request);

            return $this->successResponse($asset);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Aset dengan kode tersebut tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memindai kode aset.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-SAR-02: Data label fisik QR Code untuk pencetakan.
     * GET /api/v1/assets/{id}/qr
     */
    public function qr(string $id, Request $request): JsonResponse
    {
        try {
            $qrData = $this->service->getAssetQr($id, $request);

            return $this->successResponse($qrData);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Aset tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat menyiapkan label QR aset.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-SAR-02: Registrasi aset baru (Admin / Sarpras).
     * POST /api/v1/assets
     */
    public function store(StoreAssetRequest $request): JsonResponse
    {
        try {
            $asset = $this->service->createAsset($request->validated(), $request);

            return $this->successResponse($asset, 201);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat menyimpan aset.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-SAR-02: Perbarui aset (Admin / Sarpras).
     * PUT /api/v1/assets/{id}
     */
    public function update(string $id, UpdateAssetRequest $request): JsonResponse
    {
        try {
            $asset = $this->service->updateAsset($id, $request->validated(), $request);

            return $this->successResponse($asset);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Aset tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memperbarui aset.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-SAR-02: Hapus aset (Admin / Sarpras).
     * DELETE /api/v1/assets/{id}
     */
    public function destroy(string $id, Request $request): JsonResponse
    {
        try {
            $this->service->deleteAsset($id, $request);

            return $this->successResponse(['message' => 'Aset berhasil dihapus.'], 200);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Aset tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat menghapus aset.', 'INTERNAL_ERROR', 500);
        }
    }
}
