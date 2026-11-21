<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\StoreTimeSlotRequest;
use App\Http\Requests\Academic\UpdateTimeSlotRequest;
use App\Services\AcademicScheduleService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class TimeSlotController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected AcademicScheduleService $service
    ) {}

    /**
     * BE-ACD-01: List all time slots.
     * GET /api/v1/time-slots
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $slots = $this->service->listTimeSlots($request->query('day_type'));

            return $this->successResponse($slots);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat mengambil daftar slot jam pelajaran', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-ACD-01: Store a new time slot.
     * POST /api/v1/time-slots
     */
    public function store(StoreTimeSlotRequest $request): JsonResponse
    {
        try {
            $timeSlot = $this->service->createTimeSlot($request->validated(), $request);

            return $this->successResponse($timeSlot, 201);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'BUSINESS_RULE_VIOLATION', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat menambahkan slot jam pelajaran', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-ACD-01: Show a time slot detail.
     * GET /api/v1/time-slots/{id}
     */
    public function show(string $id): JsonResponse
    {
        try {
            $timeSlot = $this->service->getTimeSlot($id);

            return $this->successResponse($timeSlot);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Slot jam pelajaran tidak ditemukan', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat mengambil detail slot jam pelajaran', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-ACD-01: Update an existing time slot.
     * PUT /api/v1/time-slots/{id}
     */
    public function update(string $id, UpdateTimeSlotRequest $request): JsonResponse
    {
        try {
            $timeSlot = $this->service->updateTimeSlot($id, $request->validated(), $request);

            return $this->successResponse($timeSlot);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Slot jam pelajaran tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'BUSINESS_RULE_VIOLATION', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memperbarui slot jam pelajaran', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-ACD-01: Delete a time slot.
     * DELETE /api/v1/time-slots/{id}
     */
    public function destroy(string $id, Request $request): JsonResponse
    {
        try {
            $this->service->deleteTimeSlot($id, $request);

            return $this->successResponse(null, 200);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Slot jam pelajaran tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'BUSINESS_RULE_VIOLATION', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat menghapus slot jam pelajaran', 'INTERNAL_ERROR', 500);
        }
    }
}
