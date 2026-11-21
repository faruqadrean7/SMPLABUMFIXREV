<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\ApproveProgressReportRequest;
use App\Http\Requests\Academic\BulkApproveProgressReportRequest;
use App\Http\Requests\Academic\GenerateProgressReportRequest;
use App\Http\Requests\Academic\ListProgressReportRequest;
use App\Services\ProgressReportService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class ProgressReportController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ProgressReportService $service
    ) {}

    /**
     * BE-GRD-03: Daftar E-Progress Report bulanan.
     * GET /api/v1/progress-reports
     */
    public function index(ListProgressReportRequest $request): JsonResponse
    {
        try {
            $paginator = $this->service->listProgressReports($request->validated(), $request);

            return $this->paginatedResponse($paginator);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat daftar e-progress report.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-GRD-03: Detail E-Progress Report bulanan.
     * GET /api/v1/progress-reports/{id}
     */
    public function show(string $id, Request $request): JsonResponse
    {
        try {
            $report = $this->service->getProgressReport($id, $request);

            return $this->successResponse($report);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Data e-progress report tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat detail e-progress report.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-GRD-03: Generate snapshot E-Progress Report bulanan.
     * POST /api/v1/progress-reports/generate
     */
    public function generate(GenerateProgressReportRequest $request): JsonResponse
    {
        try {
            $reports = $this->service->generateProgressReports($request->validated(), $request);

            // Jika satu siswa, kembalikan objek tunggal, jika banyak kembalikan list
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

            return $this->errorResponse('Terjadi kesalahan saat membuat snapshot e-progress report: '.$e->getMessage(), 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-GRD-03: Kepala Sekolah ACC / Persetujuan E-Progress Report.
     * PATCH /api/v1/progress-reports/{id}/approve
     */
    public function approve(string $id, ApproveProgressReportRequest $request): JsonResponse
    {
        try {
            $report = $this->service->approveProgressReport($id, $request->validated(), $request);

            return $this->successResponse($report);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Data e-progress report tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                403 => 'FORBIDDEN',
                422 => 'BUSINESS_RULE_VIOLATION',
                default => 'BAD_REQUEST',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat menyetujui e-progress report.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-GRD-03: Persetujuan massal Kepala Sekolah / Super Admin.
     * POST /api/v1/progress-reports/bulk-approve
     */
    public function bulkApprove(BulkApproveProgressReportRequest $request): JsonResponse
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

            return $this->errorResponse('Terjadi kesalahan saat memproses persetujuan massal e-progress report.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-GRD-03: Tinjauan status & kesiapan data rapor per rombel kelas.
     * GET /api/v1/progress-reports/class-status
     */
    public function classStatus(Request $request): JsonResponse
    {
        try {
            $data = $this->service->getClassProgressReportsStatus($request->query(), $request);

            return $this->successResponse($data);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Data kelas atau periode tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                403 => 'FORBIDDEN',
                422 => 'BUSINESS_RULE_VIOLATION',
                default => 'BAD_REQUEST',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat status rapor kelas.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-GRD-03: Pratinjau snapshot rapor siswa sebelum disimpan.
     * GET /api/v1/progress-reports/preview
     */
    public function preview(Request $request): JsonResponse
    {
        try {
            $preview = $this->service->previewProgressReport($request->query(), $request);

            return $this->successResponse($preview);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Data siswa atau periode tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                403 => 'FORBIDDEN',
                422 => 'BUSINESS_RULE_VIOLATION',
                default => 'BAD_REQUEST',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat menghasilkan pratinjau rapor.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-GRD-03: Unduh PDF E-Progress Report 1 halaman bertanda tangan QR.
     * GET /api/v1/progress-reports/{id}/pdf
     */
    public function pdf(string $id, Request $request): Response|JsonResponse
    {
        try {
            return $this->service->downloadPdf($id, $request);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Data e-progress report tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat menghasilkan PDF e-progress report.', 'INTERNAL_ERROR', 500);
        }
    }
}
