<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\StoreBulletinRequest;
use App\Http\Requests\Cms\UpdateBulletinRequest;
use App\Services\GalleryService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class BulletinController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected GalleryService $service
    ) {}

    /**
     * BE-CMS-03: List bulletins (Admin)
     * GET /api/v1/bulletins
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $bulletins = $this->service->listBulletins($request);

            return $this->paginatedResponse($bulletins);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil daftar buletin', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CMS-03: List bulletins (Public)
     * GET /api/v1/public/bulletins
     */
    public function publicIndex(Request $request): JsonResponse
    {
        try {
            $bulletins = $this->service->listBulletins($request);

            return $this->paginatedResponse($bulletins);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil daftar buletin publik', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CMS-03: Show bulletin detail (Admin)
     * GET /api/v1/bulletins/{id}
     */
    public function show(string $id): JsonResponse
    {
        try {
            $bulletin = $this->service->getBulletin($id);

            return $this->successResponse($bulletin);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Buletin tidak ditemukan', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil detail buletin', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CMS-03: Show bulletin detail (Public)
     * GET /api/v1/public/bulletins/{id}
     */
    public function publicShow(string $id): JsonResponse
    {
        try {
            $bulletin = $this->service->getBulletin($id);

            return $this->successResponse($bulletin);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Buletin tidak ditemukan', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil detail buletin', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CMS-03: Create bulletin (Admin)
     * POST /api/v1/bulletins
     */
    public function store(StoreBulletinRequest $request): JsonResponse
    {
        try {
            $bulletin = $this->service->createBulletin($request->validated(), $request);

            return $this->successResponse($bulletin, 201);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal membuat buletin baru', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CMS-03: Update bulletin (Admin)
     * PUT /api/v1/bulletins/{id}
     */
    public function update(UpdateBulletinRequest $request, string $id): JsonResponse
    {
        try {
            $bulletin = $this->service->updateBulletin($id, $request->validated(), $request);

            return $this->successResponse($bulletin);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Buletin tidak ditemukan', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal memperbarui buletin', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CMS-03: Delete bulletin (Admin)
     * DELETE /api/v1/bulletins/{id}
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $this->service->deleteBulletin($id, $request);

            return $this->successResponse(['message' => 'Buletin berhasil dihapus']);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Buletin tidak ditemukan', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal menghapus buletin', 'INTERNAL_ERROR', 500);
        }
    }
}
