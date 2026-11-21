<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\StoreMediaItemRequest;
use App\Http\Requests\Cms\UpdateMediaItemRequest;
use App\Services\GalleryService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class MediaItemController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected GalleryService $service
    ) {}

    /**
     * BE-CMS-03: Add media item to album (Admin)
     * POST /api/v1/media
     */
    public function store(StoreMediaItemRequest $request): JsonResponse
    {
        try {
            $mediaItem = $this->service->createMediaItem($request->validated(), $request);

            return $this->successResponse($mediaItem, 201);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal menambahkan item media ke album', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CMS-03: Update media item (Admin)
     * PUT /api/v1/media/{id}
     */
    public function update(UpdateMediaItemRequest $request, string $id): JsonResponse
    {
        try {
            $mediaItem = $this->service->updateMediaItem($id, $request->validated(), $request);

            return $this->successResponse($mediaItem);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Item media tidak ditemukan', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal memperbarui item media', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CMS-03: Delete media item (Admin)
     * DELETE /api/v1/media/{id}
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $this->service->deleteMediaItem($id, $request);

            return $this->successResponse(['message' => 'Item media berhasil dihapus']);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Item media tidak ditemukan', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal menghapus item media', 'INTERNAL_ERROR', 500);
        }
    }
}
