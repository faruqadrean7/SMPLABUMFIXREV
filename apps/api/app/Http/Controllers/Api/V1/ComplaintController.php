<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Complaint\CreateComplaintRequest;
use App\Http\Requests\Complaint\ListComplaintRequest;
use App\Http\Requests\Complaint\UpdateComplaintStatusRequest;
use App\Services\ComplaintService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class ComplaintController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ComplaintService $service
    ) {}

    /**
     * BE-GUEST-02: Public submit complaint.
     * POST /api/v1/public/complaints
     */
    public function submit(CreateComplaintRequest $request): JsonResponse
    {
        try {
            $result = $this->service->submitComplaint($request->validated(), $request);

            return $this->successResponse($result, 201);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                400 => 'VALIDATION_FAILED',
                422 => 'UNPROCESSABLE_ENTITY',
                default => 'BUSINESS_RULE_VIOLATION',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat mengirimkan pengaduan', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-GUEST-02: Public track complaint by ticket code.
     * GET /api/v1/public/complaints/{code}
     */
    public function track(string $code): JsonResponse
    {
        try {
            $result = $this->service->trackComplaint($code);

            return $this->successResponse($result);
        } catch (HttpException $e) {
            $codeStr = match ($e->getStatusCode()) {
                404 => 'NOT_FOUND',
                default => 'BUSINESS_RULE_VIOLATION',
            };

            return $this->errorResponse($e->getMessage(), $codeStr, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memeriksa kode tiket pengaduan', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-GUEST-02: List complaints for authorized staff (Kepala Sekolah & Guru BK).
     * GET /api/v1/complaints
     */
    public function index(ListComplaintRequest $request): JsonResponse
    {
        try {
            $paginator = $this->service->listComplaints($request->validated(), $request);

            return $this->paginatedResponse($paginator);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat mengambil daftar pengaduan', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-GUEST-02: Get complaint detail.
     * GET /api/v1/complaints/{id}
     */
    public function show(string $id, Request $request): JsonResponse
    {
        try {
            $complaint = $this->service->getComplaintDetail($id, $request);

            return $this->successResponse($complaint);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Data pengaduan tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat mengambil detail pengaduan', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-GUEST-02: Update complaint status and log response event.
     * PATCH /api/v1/complaints/{id}/status
     */
    public function updateStatus(string $id, UpdateComplaintStatusRequest $request): JsonResponse
    {
        try {
            $complaint = $this->service->updateStatus($id, $request->validated(), $request);

            return $this->successResponse($complaint, 200);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Data pengaduan tidak ditemukan', 'NOT_FOUND', 404);
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

            return $this->errorResponse('Terjadi kesalahan saat memperbarui status pengaduan', 'INTERNAL_ERROR', 500);
        }
    }
}
