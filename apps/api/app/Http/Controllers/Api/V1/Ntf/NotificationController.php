<?php

namespace App\Http\Controllers\Api\V1\Ntf;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ntf\BroadcastNotificationRequest;
use App\Http\Requests\Ntf\ListNotificationRequest;
use App\Http\Requests\Ntf\RegisterDeviceRequest;
use App\Services\Ntf\NotificationService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class NotificationController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected NotificationService $service
    ) {}

    /**
     * BE-NTF-01: Registrasi token perangkat (FCM / APNS) untuk push notifikasi.
     * POST /api/v1/devices
     */
    public function registerDevice(RegisterDeviceRequest $request): JsonResponse
    {
        try {
            $device = $this->service->registerDevice($request->user(), $request->validated());

            return $this->successResponse($device, 201);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mendaftarkan perangkat.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-NTF-01: Hapus token perangkat saat logout.
     * DELETE /api/v1/devices/{token}
     */
    public function destroyDevice(string $token, Request $request): JsonResponse
    {
        try {
            $this->service->removeDevice($request->user(), $token);

            return $this->successResponse(['message' => 'Perangkat berhasil dihapus.']);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal menghapus perangkat.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-NTF-01: Inbox notifikasi in-app pengguna.
     * GET /api/v1/notifications
     */
    public function index(ListNotificationRequest $request): JsonResponse
    {
        try {
            $result = $this->service->getUserNotifications($request->user(), $request->validated());
            $paginator = $result['paginator'];
            $unreadCount = $result['unread_count'];

            return response()->json([
                'data' => $paginator->items(),
                'meta' => [
                    'page' => $paginator->currentPage(),
                    'limit' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'totalPages' => $paginator->lastPage(),
                    'unread_count' => $unreadCount,
                ],
            ]);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat inbox notifikasi.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-NTF-01: Detail notifikasi beserta deep link payload.
     * GET /api/v1/notifications/{id}
     */
    public function show(string $id, Request $request): JsonResponse
    {
        try {
            $notification = $this->service->getNotification($request->user(), $id);

            return $this->successResponse($notification);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Notifikasi tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat detail notifikasi.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-NTF-01: Tandai notifikasi sebagai sudah dibaca.
     * PATCH /api/v1/notifications/{id}/read
     */
    public function markAsRead(string $id, Request $request): JsonResponse
    {
        try {
            $notification = $this->service->markAsRead($request->user(), $id);

            return $this->successResponse($notification);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Notifikasi tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal memperbarui status baca notifikasi.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-NTF-01: Tandai semua notifikasi pengguna sebagai sudah dibaca.
     * PATCH /api/v1/notifications/read-all
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        try {
            $count = $this->service->markAllAsRead($request->user());

            return $this->successResponse([
                'message' => 'Semua notifikasi berhasil ditandai telah dibaca.',
                'updated_count' => $count,
            ]);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal memperbarui status notifikasi.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-NTF-01: Hapus notifikasi dari inbox pengguna.
     * DELETE /api/v1/notifications/{id}
     */
    public function destroy(string $id, Request $request): JsonResponse
    {
        try {
            $this->service->deleteNotification($request->user(), $id);

            return $this->successResponse(['message' => 'Notifikasi berhasil dihapus.']);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Notifikasi tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal menghapus notifikasi.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-NTF-01: Kirim broadcast push/in-app notifikasi tersegmentasi (role/kelas/user).
     * POST /api/v1/notifications/broadcast
     */
    public function broadcast(BroadcastNotificationRequest $request): JsonResponse
    {
        try {
            $result = $this->service->broadcast($request->validated(), $request);

            return $this->successResponse($result, 201);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengirim broadcast notifikasi.', 'INTERNAL_ERROR', 500);
        }
    }
}
