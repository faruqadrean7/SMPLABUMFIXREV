<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\PPDBReceiptAndStatsService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class PPDBReceiptAndStatsController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected PPDBReceiptAndStatsService $service
    ) {}

    /**
     * BE-PPDB-09: Get or download application receipt.
     * GET /api/v1/ppdb/applications/{id}/receipt
     */
    public function receipt(string $id, Request $request): Response|JsonResponse
    {
        try {
            $format = strtolower((string) $request->query('format', ''));
            $accept = strtolower((string) $request->header('Accept', ''));

            if ($format === 'pdf' || str_contains($accept, 'application/pdf')) {
                return $this->service->renderReceiptPdf($id, $request);
            }

            $data = $this->service->getReceiptData($id, $request);

            return $this->successResponse($data);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Data pendaftaran tidak ditemukan', 'NOT_FOUND', 404);
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

            return $this->errorResponse('Terjadi kesalahan saat mencetak bukti pendaftaran', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-PPDB-09: Get PPDB statistics.
     * GET /api/v1/ppdb/stats & GET /api/v1/public/ppdb/stats
     */
    public function stats(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['wave_id', 'start_date', 'end_date']);
            $data = $this->service->getStats($filters);

            return $this->successResponse($data);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat data statistik PPDB', 'INTERNAL_ERROR', 500);
        }
    }
}
