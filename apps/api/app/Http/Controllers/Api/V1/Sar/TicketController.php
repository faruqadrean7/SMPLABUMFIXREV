<?php

namespace App\Http\Controllers\Api\V1\Sar;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sar\AddTicketCommentRequest;
use App\Http\Requests\Sar\DisposeTicketRequest;
use App\Http\Requests\Sar\ListTicketRequest;
use App\Http\Requests\Sar\StoreTicketRequest;
use App\Http\Requests\Sar\UpdateTicketStatusRequest;
use App\Services\Sar\TicketService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class TicketController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TicketService $service
    ) {}

    /**
     * BE-SAR-03: Ringkasan KPI & metrik tiket sarpras.
     * GET /api/v1/tickets/summary
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

            return $this->errorResponse('Terjadi kesalahan saat memuat ringkasan tiket.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-SAR-03: Daftar teknisi dan evaluasi beban kerja penugasan.
     * GET /api/v1/tickets/technicians
     */
    public function technicians(Request $request): JsonResponse
    {
        try {
            $technicians = $this->service->getTechnicians($request);

            return $this->successResponse($technicians);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat daftar teknisi.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-SAR-03: Daftar tiket perbaikan berpaginasi.
     * GET /api/v1/tickets
     */
    public function index(ListTicketRequest $request): JsonResponse
    {
        try {
            $paginator = $this->service->listTickets($request->validated(), $request);

            return $this->paginatedResponse($paginator);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat daftar tiket.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-SAR-03: Detail tiket beserta riwayat timeline.
     * GET /api/v1/tickets/{id}
     */
    public function show(string $id, Request $request): JsonResponse
    {
        try {
            $ticket = $this->service->getTicket($id, $request);

            return $this->successResponse($ticket);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Tiket perbaikan tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat detail tiket.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-SAR-03: Laporkan tiket kerusakan baru.
     * POST /api/v1/tickets
     */
    public function store(StoreTicketRequest $request): JsonResponse
    {
        try {
            $ticket = $this->service->createTicket($request->validated(), $request);

            return $this->successResponse($ticket, 201);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat menyimpan tiket perbaikan.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-SAR-03: Disposisi penugasan tiket ke teknisi.
     * POST /api/v1/tickets/{id}/dispose
     */
    public function dispose(string $id, DisposeTicketRequest $request): JsonResponse
    {
        try {
            $ticket = $this->service->disposeTicket($id, $request->validated(), $request);

            return $this->successResponse($ticket);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Tiket perbaikan tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat mendisposisikan tiket.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-SAR-03: Perbarui status tiket dan catatan hasil perbaikan.
     * PATCH /api/v1/tickets/{id}/status
     */
    public function status(string $id, UpdateTicketStatusRequest $request): JsonResponse
    {
        try {
            $ticket = $this->service->updateStatus($id, $request->validated(), $request);

            return $this->successResponse($ticket);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Tiket perbaikan tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memperbarui status tiket.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-SAR-03: Tambahkan komentar / pemantauan pada tiket.
     * POST /api/v1/tickets/{id}/comments
     */
    public function comment(string $id, AddTicketCommentRequest $request): JsonResponse
    {
        try {
            $event = $this->service->addComment($id, $request->validated(), $request);

            return $this->successResponse($event, 201);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Tiket perbaikan tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat menambahkan komentar tiket.', 'INTERNAL_ERROR', 500);
        }
    }
}
