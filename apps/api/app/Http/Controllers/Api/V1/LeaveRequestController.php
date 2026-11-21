<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\DecideLeaveRequest;
use App\Http\Requests\Academic\ListLeaveRequestRequest;
use App\Http\Requests\Academic\StoreLeaveRequest;
use App\Http\Requests\Academic\UpdateLeaveRequestStatusRequest;
use App\Services\AcademicAttendanceService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class LeaveRequestController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected AcademicAttendanceService $service
    ) {}

    /**
     * BE-ACD-03: List leave requests.
     * GET /api/v1/leave-requests
     */
    public function index(ListLeaveRequestRequest $request): JsonResponse
    {
        try {
            $paginator = $this->service->listLeaveRequests($request->validated(), $request);

            return $this->paginatedResponse($paginator);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat mengambil daftar permohonan izin', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-ACD-03: Submit leave request by guardian.
     * POST /api/v1/leave-requests
     */
    public function store(StoreLeaveRequest $request): JsonResponse
    {
        try {
            $leave = $this->service->createLeaveRequest($request->validated(), $request);

            return $this->successResponse($leave, 201);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                403 => 'FORBIDDEN',
                default => 'BUSINESS_RULE_VIOLATION',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat mengajukan permohonan izin', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-ACD-03: Update leave request status (Homeroom Teacher or Admin).
     * PATCH /api/v1/leave-requests/{id}/status
     */
    public function updateStatus(string $id, UpdateLeaveRequestStatusRequest $request): JsonResponse
    {
        try {
            $leave = $this->service->updateLeaveRequestStatus($id, $request->validated(), $request);

            return $this->successResponse($leave);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Data permohonan izin tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                403 => 'FORBIDDEN',
                default => 'BUSINESS_RULE_VIOLATION',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memperbarui status permohonan izin', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-ACD-04: Show leave request detail.
     * GET /api/v1/leave-requests/{id}
     */
    public function show(string $id, Request $request): JsonResponse
    {
        try {
            $leave = $this->service->getLeaveRequestDetail($id, $request);

            return $this->successResponse($leave);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Data permohonan izin tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                403 => 'FORBIDDEN',
                default => 'BUSINESS_RULE_VIOLATION',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat mengambil detail permohonan izin', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-ACD-04: Decide leave request status by Walas / Admin.
     * PATCH /api/v1/leave-requests/{id}/decision
     */
    public function decision(string $id, DecideLeaveRequest $request): JsonResponse
    {
        try {
            $leave = $this->service->decideLeaveRequest($id, $request->validated(), $request);

            return $this->successResponse($leave);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Data permohonan izin tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                403 => 'FORBIDDEN',
                default => 'BUSINESS_RULE_VIOLATION',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memutuskan permohonan izin', 'INTERNAL_ERROR', 500);
        }
    }
}
