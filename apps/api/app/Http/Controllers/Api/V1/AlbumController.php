<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\StoreAlbumRequest;
use App\Http\Requests\Cms\UpdateAlbumRequest;
use App\Services\GalleryService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class AlbumController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected GalleryService $service
    ) {}

    /**
     * BE-CMS-03: List albums (Admin)
     * GET /api/v1/albums
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $albums = $this->service->listAlbums($request);

            return $this->paginatedResponse($albums);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil daftar album', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CMS-03: List albums (Public)
     * GET /api/v1/public/albums
     */
    public function publicIndex(Request $request): JsonResponse
    {
        try {
            $albums = $this->service->listAlbums($request);

            return $this->paginatedResponse($albums);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil daftar album publik', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CMS-03: Show album detail with media items (Admin)
     * GET /api/v1/albums/{id}
     */
    public function show(string $id): JsonResponse
    {
        try {
            $album = $this->service->getAlbum($id);

            return $this->successResponse($album);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Album tidak ditemukan', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil detail album', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CMS-03: Show album detail with media items (Public)
     * GET /api/v1/public/albums/{id}
     */
    public function publicShow(string $id): JsonResponse
    {
        try {
            $album = $this->service->getAlbum($id);

            return $this->successResponse($album);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Album tidak ditemukan', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil detail album', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CMS-03: Create album (Admin)
     * POST /api/v1/albums
     */
    public function store(StoreAlbumRequest $request): JsonResponse
    {
        try {
            $album = $this->service->createAlbum($request->validated(), $request);

            return $this->successResponse($album, 201);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal membuat album baru', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CMS-03: Update album (Admin)
     * PUT /api/v1/albums/{id}
     */
    public function update(UpdateAlbumRequest $request, string $id): JsonResponse
    {
        try {
            $album = $this->service->updateAlbum($id, $request->validated(), $request);

            return $this->successResponse($album);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Album tidak ditemukan', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal memperbarui album', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CMS-03: Delete album (Admin)
     * DELETE /api/v1/albums/{id}
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $this->service->deleteAlbum($id, $request);

            return $this->successResponse(['message' => 'Album berhasil dihapus']);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Album tidak ditemukan', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal menghapus album', 'INTERNAL_ERROR', 500);
        }
    }
}
