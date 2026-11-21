<?php

namespace App\Http\Controllers\Api\V1\Ntf;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ntf\ListNotificationLogRequest;
use App\Http\Requests\Ntf\ListNotificationTemplateRequest;
use App\Http\Requests\Ntf\SendNotificationRequest;
use App\Http\Requests\Ntf\StoreNotificationTemplateRequest;
use App\Http\Requests\Ntf\UpdateNotificationTemplateRequest;
use App\Services\Ntf\MessagingService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class MessagingController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected MessagingService $service
    ) {}

    /**
     * BE-NTF-02: Daftar template pesan notifikasi.
     * GET /api/v1/notification-templates
     */
    public function indexTemplates(ListNotificationTemplateRequest $request): JsonResponse
    {
        try {
            $paginator = $this->service->listTemplates($request->validated());

            return $this->paginatedResponse($paginator);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat daftar template notifikasi.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-NTF-02: Detail template notifikasi.
     * GET /api/v1/notification-templates/{id}
     */
    public function showTemplate(string $id): JsonResponse
    {
        try {
            $template = $this->service->getTemplate($id);

            return $this->successResponse($template);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Template notifikasi tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat detail template.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-NTF-02: Buat template pesan baru (WhatsApp / Email / Push).
     * POST /api/v1/notification-templates
     */
    public function storeTemplate(StoreNotificationTemplateRequest $request): JsonResponse
    {
        try {
            $template = $this->service->createTemplate($request->validated(), $request);

            return $this->successResponse($template, 201);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal membuat template notifikasi baru.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-NTF-02: Perbarui template pesan.
     * PUT /api/v1/notification-templates/{id}
     */
    public function updateTemplate(string $id, UpdateNotificationTemplateRequest $request): JsonResponse
    {
        try {
            $template = $this->service->updateTemplate($id, $request->validated(), $request);

            return $this->successResponse($template);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Template notifikasi tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal memperbarui template notifikasi.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-NTF-02: Hapus template pesan.
     * DELETE /api/v1/notification-templates/{id}
     */
    public function destroyTemplate(string $id, Request $request): JsonResponse
    {
        try {
            $this->service->deleteTemplate($id, $request);

            return $this->successResponse(['message' => 'Template notifikasi berhasil dihapus.']);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Template notifikasi tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal menghapus template notifikasi.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-NTF-02: Kirim pesan notifikasi (WhatsApp / Email) menggunakan template berparameter.
     * POST /api/v1/notification-templates/send
     */
    public function send(SendNotificationRequest $request): JsonResponse
    {
        try {
            $log = $this->service->sendNotification($request->validated(), $request);

            return $this->successResponse($log, 201);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengirimkan pesan notifikasi.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-NTF-02: Daftar riwayat & log pengiriman notifikasi.
     * GET /api/v1/notification-logs
     */
    public function indexLogs(ListNotificationLogRequest $request): JsonResponse
    {
        try {
            $paginator = $this->service->listLogs($request->validated());

            return $this->paginatedResponse($paginator);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat riwayat log notifikasi.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-NTF-02: Detail log pengiriman notifikasi.
     * GET /api/v1/notification-logs/{id}
     */
    public function showLog(string $id): JsonResponse
    {
        try {
            $log = $this->service->getLog($id);

            return $this->successResponse($log);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Log notifikasi tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat detail log notifikasi.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-NTF-02: Coba kirim ulang (retry) notifikasi yang gagal.
     * POST /api/v1/notification-logs/{id}/retry
     */
    public function retry(string $id, Request $request): JsonResponse
    {
        try {
            $log = $this->service->retryNotification($id, $request);

            return $this->successResponse($log);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Log notifikasi tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mencoba kirim ulang notifikasi.', 'INTERNAL_ERROR', 500);
        }
    }
}
