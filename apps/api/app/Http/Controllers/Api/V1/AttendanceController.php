<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\BatchSessionAttendanceRequest;
use App\Http\Requests\Academic\DailyGateAttendanceRequest;
use App\Http\Requests\Academic\ListAttendanceRequest;
use App\Http\Requests\Academic\RecapAttendanceRequest;
use App\Services\AcademicAttendanceService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class AttendanceController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected AcademicAttendanceService $service
    ) {}

    /**
     * BE-ACD-03: List attendance history.
     * GET /api/v1/attendances
     */
    public function index(ListAttendanceRequest $request): JsonResponse
    {
        try {
            $paginator = $this->service->listAttendances($request->validated(), $request);

            return $this->paginatedResponse($paginator);
        } catch (HttpException $e) {
            return $this->handleHttpException($e);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat mengambil riwayat presensi', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-ACD-03: Batch record session attendance from class journal.
     * POST /api/v1/attendances
     */
    public function storeSession(BatchSessionAttendanceRequest $request): JsonResponse
    {
        try {
            $result = $this->service->recordSessionAttendance($request->validated(), $request);

            return $this->successResponse($result, 201);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                403 => 'FORBIDDEN',
                422 => 'UNPROCESSABLE_ENTITY',
                default => 'BUSINESS_RULE_VIOLATION',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat mencatat presensi sesi kelas', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-ACD-03: Record daily gate attendance.
     * POST /api/v1/attendances/daily
     */
    public function storeDaily(DailyGateAttendanceRequest $request): JsonResponse
    {
        try {
            $record = $this->service->recordDailyAttendance($request->validated(), $request);

            return $this->successResponse($record, 201);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'BUSINESS_RULE_VIOLATION', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat mencatat presensi gerbang harian', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-ACD-03: Get attendance recap statistics.
     * GET /api/v1/attendances/recap
     */
    public function recap(RecapAttendanceRequest $request): JsonResponse
    {
        try {
            $recap = $this->service->getAttendanceRecap($request->validated(), $request);

            return $this->successResponse($recap);
        } catch (HttpException $e) {
            return $this->handleHttpException($e);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat menghitung rekapitulasi presensi', 'INTERNAL_ERROR', 500);
        }
    }
}
