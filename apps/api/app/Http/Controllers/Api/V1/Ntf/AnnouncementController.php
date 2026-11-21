<?php

namespace App\Http\Controllers\Api\V1\Ntf;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ntf\ListAnnouncementRequest;
use App\Http\Requests\Ntf\StoreAnnouncementRequest;
use App\Http\Requests\Ntf\UpdateAnnouncementRequest;
use App\Services\Ntf\AnnouncementService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class AnnouncementController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected AnnouncementService $service
    ) {}

    /**
     * BE-NTF-03: Daftar pengumuman terarah.
     * GET /api/v1/announcements
     */
    public function index(ListAnnouncementRequest $request): JsonResponse
    {
        try {
            $paginator = $this->service->listAnnouncements($request->validated(), $request);

            return $this->paginatedResponse($paginator);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat daftar pengumuman.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-NTF-03: Detail pengumuman.
     * GET /api/v1/announcements/{id}
     */
    public function show(string $id, Request $request): JsonResponse
    {
        try {
            $announcement = $this->service->getAnnouncement($id, $request);

            return $this->successResponse($announcement);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Pengumuman tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat detail pengumuman.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-NTF-03: Buat pengumuman baru (staf/guru/admin).
     * POST /api/v1/announcements
     */
    public function store(StoreAnnouncementRequest $request): JsonResponse
    {
        try {
            $announcement = $this->service->createAnnouncement($request->validated(), $request);

            return $this->successResponse($announcement, 201);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal membuat pengumuman baru.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-NTF-03: Perbarui pengumuman.
     * PUT /api/v1/announcements/{id}
     */
    public function update(string $id, UpdateAnnouncementRequest $request): JsonResponse
    {
        try {
            $announcement = $this->service->updateAnnouncement($id, $request->validated(), $request);

            return $this->successResponse($announcement);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Pengumuman tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal memperbarui pengumuman.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-NTF-03: Hapus pengumuman.
     * DELETE /api/v1/announcements/{id}
     */
    public function destroy(string $id, Request $request): JsonResponse
    {
        try {
            $this->service->deleteAnnouncement($id, $request);

            return $this->successResponse(['message' => 'Pengumuman berhasil dihapus.']);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Pengumuman tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal menghapus pengumuman.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-NTF-03: Tandai pengumuman telah dibaca pengguna.
     * POST /api/v1/announcements/{id}/read
     */
    public function markAsRead(string $id, Request $request): JsonResponse
    {
        try {
            $result = $this->service->markAsRead($id, $request);

            return $this->successResponse($result);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Pengumuman tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal menandai pengumuman telah dibaca.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-NTF-03: Pelacakan pembaca pengumuman (audit & readers list).
     * GET /api/v1/announcements/{id}/readers
     */
    public function readers(string $id, Request $request): JsonResponse
    {
        try {
            $result = $this->service->listReaders($id, $request);

            return $this->successResponse($result);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Pengumuman tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal memuat data pembaca pengumuman.', 'INTERNAL_ERROR', 500);
        }
    }
}
