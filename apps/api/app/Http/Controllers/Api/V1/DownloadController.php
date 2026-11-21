<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\StoreDownloadRequest;
use App\Http\Requests\Cms\UpdateDownloadRequest;
use App\Services\DownloadService;
use App\Traits\ApiResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class DownloadController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected DownloadService $service
    ) {}

    /**
     * BE-CMS-06: List downloads (Admin)
     * GET /api/v1/downloads
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $downloads = $this->service->listDownloads($request, false);

            if ($downloads instanceof LengthAwarePaginator) {
                return $this->paginatedResponse($downloads);
            }

            return $this->successResponse($downloads);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil daftar dokumen unduhan', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CMS-06: List public downloads (Public)
     * GET /api/v1/public/downloads
     */
    public function publicIndex(Request $request): JsonResponse
    {
        try {
            $downloads = $this->service->listDownloads($request, true);

            if ($downloads instanceof LengthAwarePaginator) {
                return $this->paginatedResponse($downloads);
            }

            return $this->successResponse($downloads);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil daftar dokumen unduhan publik', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CMS-06: Show download detail (Admin)
     * GET /api/v1/downloads/{id}
     */
    public function show(string $id): JsonResponse
    {
        try {
            $download = $this->service->getDownload($id, false);

            return $this->successResponse($download);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Dokumen unduhan tidak ditemukan', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil detail dokumen unduhan', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CMS-06: Show public download detail (Public)
     * GET /api/v1/public/downloads/{id}
     */
    public function publicShow(string $id): JsonResponse
    {
        try {
            $download = $this->service->getDownload($id, true);

            return $this->successResponse($download);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Dokumen unduhan tidak ditemukan', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil detail dokumen unduhan', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CMS-06: Create download item (Admin)
     * POST /api/v1/downloads
     */
    public function store(StoreDownloadRequest $request): JsonResponse
    {
        try {
            $download = $this->service->createDownload($request->validated(), $request);

            return $this->successResponse($download, 201);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal membuat dokumen unduhan baru', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CMS-06: Update download item (Admin)
     * PUT /api/v1/downloads/{id}
     */
    public function update(UpdateDownloadRequest $request, string $id): JsonResponse
    {
        try {
            $download = $this->service->updateDownload($id, $request->validated(), $request);

            return $this->successResponse($download);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Dokumen unduhan tidak ditemukan', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal memperbarui dokumen unduhan', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CMS-06: Delete download item (Admin)
     * DELETE /api/v1/downloads/{id}
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $this->service->deleteDownload($id, $request);

            return $this->successResponse(['message' => 'Dokumen unduhan berhasil dihapus']);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Dokumen unduhan tidak ditemukan', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal menghapus dokumen unduhan', 'INTERNAL_ERROR', 500);
        }
    }
}
