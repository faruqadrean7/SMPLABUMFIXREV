<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\StoreEventRequest;
use App\Http\Requests\Cms\UpdateEventRequest;
use App\Services\EventService;
use App\Traits\ApiResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class EventController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected EventService $service
    ) {}

    /**
     * BE-CMS-04: List events (Admin)
     * GET /api/v1/events
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $events = $this->service->listEvents($request, false);

            if ($events instanceof LengthAwarePaginator) {
                return $this->paginatedResponse($events);
            }

            return $this->successResponse($events);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil daftar agenda kegiatan', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CMS-04: List public events (Public Calendar)
     * GET /api/v1/public/events
     */
    public function publicIndex(Request $request): JsonResponse
    {
        try {
            $events = $this->service->listEvents($request, true);

            if ($events instanceof LengthAwarePaginator) {
                return $this->paginatedResponse($events);
            }

            return $this->successResponse($events);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil agenda kegiatan publik', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CMS-04: Show event detail (Admin)
     * GET /api/v1/events/{id}
     */
    public function show(string $id): JsonResponse
    {
        try {
            $event = $this->service->getEvent($id, false);

            return $this->successResponse($event);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Agenda kegiatan tidak ditemukan', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil detail agenda kegiatan', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CMS-04: Show public event detail (Public)
     * GET /api/v1/public/events/{id}
     */
    public function publicShow(string $id): JsonResponse
    {
        try {
            $event = $this->service->getEvent($id, true);

            return $this->successResponse($event);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Agenda kegiatan tidak ditemukan', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil detail agenda kegiatan', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CMS-04: Create event (Admin)
     * POST /api/v1/events
     */
    public function store(StoreEventRequest $request): JsonResponse
    {
        try {
            $event = $this->service->createEvent($request->validated(), $request);

            return $this->successResponse($event, 201);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'VALIDATION_FAILED', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal membuat agenda kegiatan baru', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CMS-04: Update event (Admin)
     * PUT /api/v1/events/{id}
     */
    public function update(UpdateEventRequest $request, string $id): JsonResponse
    {
        try {
            $event = $this->service->updateEvent($id, $request->validated(), $request);

            return $this->successResponse($event);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Agenda kegiatan tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'VALIDATION_FAILED', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal memperbarui agenda kegiatan', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CMS-04: Delete event (Admin)
     * DELETE /api/v1/events/{id}
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $this->service->deleteEvent($id, $request);

            return $this->successResponse(['message' => 'Agenda kegiatan berhasil dihapus']);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Agenda kegiatan tidak ditemukan', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal menghapus agenda kegiatan', 'INTERNAL_ERROR', 500);
        }
    }
}
