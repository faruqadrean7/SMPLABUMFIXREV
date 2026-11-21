<?php

namespace App\Http\Controllers\Api\V1\Sar;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sar\BookingDecisionRequest;
use App\Http\Requests\Sar\CheckAvailabilityRequest;
use App\Http\Requests\Sar\ListBookingRequest;
use App\Http\Requests\Sar\StoreBookingRequest;
use App\Services\Sar\BookingService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class BookingController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected BookingService $service
    ) {}

    /**
     * BE-SAR-01: Cek ketersediaan fasilitas real-time.
     * GET /api/v1/bookings/availability
     */
    public function availability(CheckAvailabilityRequest $request): JsonResponse
    {
        try {
            $result = $this->service->checkAvailability($request->validated(), $request);

            return $this->successResponse($result);
        } catch (ModelNotFoundException) {
            return $this->notFoundResponse('Fasilitas tidak ditemukan.');
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memeriksa ketersediaan fasilitas.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-SAR-01: Ringkasan statistik operasional peminjaman fasilitas.
     * GET /api/v1/bookings/summary
     */
    public function summary(Request $request): JsonResponse
    {
        try {
            $summary = $this->service->getSummary($request);

            return $this->successResponse($summary);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat ringkasan peminjaman.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-SAR-01: Daftar peminjaman fasilitas.
     * GET /api/v1/bookings
     */
    public function index(ListBookingRequest $request): JsonResponse
    {
        try {
            $paginator = $this->service->listBookings($request->validated(), $request);

            return $this->paginatedResponse($paginator);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat daftar peminjaman.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-SAR-01: Pengajuan peminjaman fasilitas.
     * POST /api/v1/bookings
     */
    public function store(StoreBookingRequest $request): JsonResponse
    {
        try {
            $booking = $this->service->createBooking($request->validated(), $request);

            return $this->successResponse($booking, 201);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'CONFLICT', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat menyimpan pengajuan peminjaman.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-SAR-01: Detail peminjaman fasilitas.
     * GET /api/v1/bookings/{id}
     */
    public function show(string $id, Request $request): JsonResponse
    {
        try {
            $booking = $this->service->getBooking($id, $request);

            return $this->successResponse($booking);
        } catch (ModelNotFoundException) {
            return $this->notFoundResponse('Peminjaman tidak ditemukan.');
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat detail peminjaman.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-SAR-01: Persetujuan atau penolakan peminjaman oleh Admin/Sarpras.
     * PATCH /api/v1/bookings/{id}/decision
     */
    public function decision(string $id, BookingDecisionRequest $request): JsonResponse
    {
        try {
            $booking = $this->service->decideBooking($id, $request->validated(), $request);

            return $this->successResponse($booking);
        } catch (ModelNotFoundException) {
            return $this->notFoundResponse('Peminjaman tidak ditemukan.');
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'UNPROCESSABLE_ENTITY', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memproses keputusan peminjaman.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-SAR-01: Pembatalan peminjaman oleh pemohon.
     * DELETE /api/v1/bookings/{id}
     */
    public function cancel(string $id, Request $request): JsonResponse
    {
        try {
            $booking = $this->service->cancelBooking($id, $request);

            return $this->successResponse($booking);
        } catch (ModelNotFoundException) {
            return $this->notFoundResponse('Peminjaman tidak ditemukan.');
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'UNPROCESSABLE_ENTITY', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat membatalkan peminjaman.', 'INTERNAL_ERROR', 500);
        }
    }
}
