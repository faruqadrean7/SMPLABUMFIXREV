<?php

namespace App\Http\Controllers\Api\V1\Ntf;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ntf\UpdateNotificationPreferenceRequest;
use App\Services\Ntf\NotificationPreferenceService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class NotificationPreferenceController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected NotificationPreferenceService $service
    ) {}

    /**
     * BE-NTF-04: Ambil seluruh preferensi notifikasi pengguna yang login.
     * GET /api/v1/notification-preferences
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (! $user) {
                return $this->errorResponse('Unauthenticated', 'UNAUTHORIZED', 401);
            }

            $preferences = $this->service->getUserPreferences($user);

            return $this->successResponse($preferences);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat preferensi notifikasi.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-NTF-04: Perbarui preferensi notifikasi pengguna (single atau batch).
     * PUT /api/v1/notification-preferences
     */
    public function update(UpdateNotificationPreferenceRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (! $user) {
                return $this->errorResponse('Unauthenticated', 'UNAUTHORIZED', 401);
            }

            $preferences = $this->service->updateUserPreferences($user, $request->validated(), $request);

            return $this->successResponse($preferences);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal memperbarui preferensi notifikasi.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-NTF-04: Ambil katalog jenis notifikasi dan kanal yang didukung.
     * GET /api/v1/notification-preferences/types
     */
    public function types(): JsonResponse
    {
        try {
            $types = $this->service->getCatalog();

            return $this->successResponse($types);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat daftar jenis notifikasi.', 'INTERNAL_ERROR', 500);
        }
    }
}
