<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\CheckScheduleConflictRequest;
use App\Http\Requests\Academic\ListScheduleRequest;
use App\Http\Requests\Academic\StoreScheduleRequest;
use App\Http\Requests\Academic\UpdateScheduleRequest;
use App\Services\AcademicScheduleService;
use App\Traits\ApiResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class ScheduleController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected AcademicScheduleService $service
    ) {}

    /**
     * BE-ACD-01: List schedules with filters.
     * GET /api/v1/schedules
     */
    public function index(ListScheduleRequest $request): JsonResponse
    {
        try {
            $result = $this->service->listSchedules($request->validated(), $request);

            if ($result instanceof LengthAwarePaginator) {
                return $this->paginatedResponse($result);
            }

            return $this->successResponse($result);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat mengambil jadwal pelajaran', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-ACD-01: Check schedule conflict.
     * POST /api/v1/schedules/conflicts/check
     */
    public function checkConflicts(CheckScheduleConflictRequest $request): JsonResponse
    {
        try {
            $conflicts = $this->service->checkConflicts($request->validated());

            return $this->successResponse($conflicts);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memeriksa bentrok jadwal', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-ACD-01: Store a new schedule with conflict prevention (409).
     * POST /api/v1/schedules
     */
    public function store(StoreScheduleRequest $request): JsonResponse
    {
        try {
            $schedule = $this->service->createSchedule($request->validated(), $request);

            return $this->successResponse($schedule, 201);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                409 => 'SCHEDULE_CONFLICT',
                default => 'BUSINESS_RULE_VIOLATION',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat membuat jadwal pelajaran', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-ACD-01: Show schedule detail.
     * GET /api/v1/schedules/{id}
     */
    public function show(string $id): JsonResponse
    {
        try {
            $schedule = $this->service->getSchedule($id);

            return $this->successResponse($schedule);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Jadwal pelajaran tidak ditemukan', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat mengambil detail jadwal pelajaran', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-ACD-01: Update an existing schedule with conflict prevention (409).
     * PUT /api/v1/schedules/{id}
     */
    public function update(string $id, UpdateScheduleRequest $request): JsonResponse
    {
        try {
            $schedule = $this->service->updateSchedule($id, $request->validated(), $request);

            return $this->successResponse($schedule);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Jadwal pelajaran tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                409 => 'SCHEDULE_CONFLICT',
                default => 'BUSINESS_RULE_VIOLATION',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memperbarui jadwal pelajaran', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-ACD-01: Delete schedule.
     * DELETE /api/v1/schedules/{id}
     */
    public function destroy(string $id, Request $request): JsonResponse
    {
        try {
            $this->service->deleteSchedule($id, $request);

            return $this->successResponse(null, 200);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Jadwal pelajaran tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'BUSINESS_RULE_VIOLATION', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat menghapus jadwal pelajaran', 'INTERNAL_ERROR', 500);
        }
    }
}
