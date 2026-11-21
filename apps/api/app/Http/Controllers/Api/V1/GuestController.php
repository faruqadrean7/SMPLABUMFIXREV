<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Guest\ListGuestRequest;
use App\Http\Requests\Guest\RegisterGuestRequest;
use App\Http\Requests\Guest\UploadGuestSignatureRequest;
use App\Services\GuestService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class GuestController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected GuestService $service
    ) {}

    /**
     * BE-GUEST-01: Public register guest entry.
     * POST /api/v1/public/guests
     */
    public function register(RegisterGuestRequest $request): JsonResponse
    {
        try {
            $guest = $this->service->registerGuest($request->validated(), $request);

            return $this->successResponse($guest, 201);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                400 => 'VALIDATION_FAILED',
                422 => 'UNPROCESSABLE_ENTITY',
                default => 'BUSINESS_RULE_VIOLATION',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat mendaftarkan kunjungan tamu', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-GUEST-01: List guests with filters.
     * GET /api/v1/guests
     */
    public function index(ListGuestRequest $request): JsonResponse
    {
        try {
            $paginator = $this->service->listGuests($request->validated(), $request);

            return $this->paginatedResponse($paginator);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                401 => 'UNAUTHENTICATED',
                403 => 'FORBIDDEN',
                default => 'BUSINESS_RULE_VIOLATION',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat mengambil daftar buku tamu', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-GUEST-01: Get guest visit detail.
     * GET /api/v1/guests/{id}
     */
    public function show(string $id, Request $request): JsonResponse
    {
        try {
            $guest = $this->service->getGuestDetail($id, $request);

            return $this->successResponse($guest);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Data kunjungan tamu tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat mengambil detail kunjungan tamu', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-GUEST-01: Checkout guest.
     * PATCH /api/v1/guests/{id}/checkout
     */
    public function checkout(string $id, Request $request): JsonResponse
    {
        try {
            $guest = $this->service->checkoutGuest($id, $request);

            return $this->successResponse($guest, 200);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Data kunjungan tamu tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                400 => 'VALIDATION_FAILED',
                403 => 'FORBIDDEN',
                422 => 'UNPROCESSABLE_ENTITY',
                default => 'BUSINESS_RULE_VIOLATION',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memproses checkout tamu', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-GUEST-03: Upload canvas/file digital signature for guest.
     * POST /api/v1/public/guests/{id}/signature
     */
    public function uploadSignature(string $id, UploadGuestSignatureRequest $request): JsonResponse
    {
        try {
            $data = $this->service->attachSignature($id, $request->validated(), $request);

            return $this->successResponse($data, 201);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Data kunjungan tamu tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                400 => 'VALIDATION_FAILED',
                403 => 'FORBIDDEN',
                422 => 'UNPROCESSABLE_ENTITY',
                default => 'BUSINESS_RULE_VIOLATION',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat menyimpan tanda tangan tamu', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-GUEST-03: Get guest digital signature metadata and integrity check.
     * GET /api/v1/guests/{id}/signature
     */
    public function getSignature(string $id, Request $request): JsonResponse
    {
        try {
            $data = $this->service->getGuestSignature($id, $request);

            return $this->successResponse($data);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Data kunjungan tamu tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                403 => 'FORBIDDEN',
                404 => 'NOT_FOUND',
                default => 'BUSINESS_RULE_VIOLATION',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat mengambil tanda tangan tamu', 'INTERNAL_ERROR', 500);
        }
    }
}
