<?php

namespace App\Http\Controllers\Api\V1\Rpt;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rpt\ExportReportRequest;
use App\Http\Requests\Rpt\GenerateReportRequest;
use App\Services\Rpt\ReportService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class ReportController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ReportService $service
    ) {}

    /**
     * BE-RPT-01: Laporan rekapitulasi data terstruktur.
     * GET /api/v1/reports/{type}
     */
    public function show(string $type, GenerateReportRequest $request): JsonResponse
    {
        try {
            $data = $this->service->generateReport($type, $request->validated(), $request);

            return $this->successResponse($data);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat membuat laporan rekapitulasi.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-RPT-01: Ekspor laporan rekapitulasi ke format berkas (CSV).
     * GET /api/v1/reports/{type}/export
     */
    public function export(string $type, ExportReportRequest $request): Response|JsonResponse
    {
        try {
            $export = $this->service->exportReport($type, $request->validated(), $request);

            return response($export['content'], 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="'.$export['filename'].'"',
                'X-Report-Type' => $type,
            ]);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengekspor berkas laporan.', 'INTERNAL_ERROR', 500);
        }
    }
}
