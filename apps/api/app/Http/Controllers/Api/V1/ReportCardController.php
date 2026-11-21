<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\ApproveReportCardRequest;
use App\Http\Requests\Academic\BulkApproveReportCardRequest;
use App\Http\Requests\Academic\BulkLockReportCardRequest;
use App\Http\Requests\Academic\GenerateReportCardRequest;
use App\Http\Requests\Academic\ListReportCardRequest;
use App\Http\Requests\Academic\LockReportCardRequest;
use App\Services\ReportCardService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class ReportCardController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ReportCardService $service
    ) {}

    /**
     * BE-GRD-04: Tinjauan status & kesiapan data rapor semester satu kelas.
     * GET /api/v1/report-cards/class-status
     */
    public function classStatus(Request $request): JsonResponse
    {
        try {
            $data = $this->service->getClassReportCardsStatus($request->query(), $request);

            return $this->successResponse($data);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                403 => 'FORBIDDEN',
                422 => 'VALIDATION_ERROR',
                default => 'BAD_REQUEST',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat status rapor kelas.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-GRD-04: Pratinjau data snapshot rapor semester siswa sebelum disimpan.
     * GET /api/v1/report-cards/preview
     */
    public function preview(Request $request): JsonResponse
    {
        try {
            $data = $this->service->previewReportCard($request->query(), $request);

            return $this->successResponse($data);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                403 => 'FORBIDDEN',
                422 => 'VALIDATION_ERROR',
                default => 'BAD_REQUEST',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat membuat pratinjau e-rapor semester.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-GRD-04: Pengesahan massal E-Rapor semester oleh Kepala Sekolah atau Admin.
     * POST /api/v1/report-cards/bulk-approve
     */
    public function bulkApprove(BulkApproveReportCardRequest $request): JsonResponse
    {
        try {
            $result = $this->service->bulkApprove($request->validated(), $request);

            return $this->successResponse($result);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                403 => 'FORBIDDEN',
                422 => 'BUSINESS_RULE_VIOLATION',
                default => 'BAD_REQUEST',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memproses pengesahan massal e-rapor semester.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-GRD-04: Penguncian massal E-Rapor semester oleh Admin Kurikulum.
     * POST /api/v1/report-cards/bulk-lock
     */
    public function bulkLock(BulkLockReportCardRequest $request): JsonResponse
    {
        try {
            $result = $this->service->bulkLock($request->validated(), $request);

            return $this->successResponse($result);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                403 => 'FORBIDDEN',
                422 => 'BUSINESS_RULE_VIOLATION',
                default => 'BAD_REQUEST',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memproses penguncian massal e-rapor semester.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-GRD-04: Daftar E-Rapor semester.
     * GET /api/v1/report-cards
     */
    public function index(ListReportCardRequest $request): JsonResponse
    {
        try {
            $paginator = $this->service->listReportCards($request->validated(), $request);

            return $this->paginatedResponse($paginator);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat daftar e-rapor.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-GRD-04: Detail E-Rapor semester.
     * GET /api/v1/report-cards/{id}
     */
    public function show(string $id, Request $request): JsonResponse
    {
        try {
            $report = $this->service->getReportCard($id, $request);

            return $this->successResponse($report);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Data e-rapor semester tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat detail e-rapor.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-GRD-04: Generate snapshot E-Rapor semester.
     * POST /api/v1/report-cards/generate
     */
    public function generate(GenerateReportCardRequest $request): JsonResponse
    {
        try {
            $reports = $this->service->generateReportCards($request->validated(), $request);

            $data = count($reports) === 1 ? $reports[0] : $reports;

            return $this->successResponse($data, 201);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                422 => 'BUSINESS_RULE_VIOLATION',
                403 => 'FORBIDDEN',
                default => 'BAD_REQUEST',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat membuat e-rapor semester: '.$e->getMessage(), 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-GRD-04: Kunci / buka kunci E-Rapor semester oleh Admin Kurikulum.
     * PATCH /api/v1/report-cards/{id}/lock
     */
    public function lock(string $id, LockReportCardRequest $request): JsonResponse
    {
        try {
            $report = $this->service->lockReportCard($id, $request->validated(), $request);

            return $this->successResponse($report);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Data e-rapor semester tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat mengubah status kunci e-rapor.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-GRD-04: Pengesahan / ACC Kepala Sekolah atas E-Rapor semester.
     * PATCH /api/v1/report-cards/{id}/approve
     */
    public function approve(string $id, ApproveReportCardRequest $request): JsonResponse
    {
        try {
            $report = $this->service->approveReportCard($id, $request->validated(), $request);

            return $this->successResponse($report);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Data e-rapor semester tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                403 => 'FORBIDDEN',
                422 => 'BUSINESS_RULE_VIOLATION',
                default => 'BAD_REQUEST',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat mengesahkan e-rapor semester.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-GRD-04: Unduh PDF resmi E-Rapor semester bertanda tangan QR.
     * GET /api/v1/report-cards/{id}/pdf
     */
    public function pdf(string $id, Request $request): Response|JsonResponse
    {
        try {
            return $this->service->downloadPdf($id, $request);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Data e-rapor semester tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat menghasilkan PDF e-rapor semester.', 'INTERNAL_ERROR', 500);
        }
    }
}
