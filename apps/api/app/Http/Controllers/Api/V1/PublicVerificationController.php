<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\PPDBReceiptAndStatsService;
use App\Services\ProgressReportService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class PublicVerificationController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ProgressReportService $service,
        protected PPDBReceiptAndStatsService $ppdbReceiptService
    ) {}

    /**
     * BE-GRD-03 & P1-25: Halaman verifikasi publik keaslian dokumen (E-Rapor / Progress Report / Bukti PPDB) via token QR.
     * GET /api/v1/public/verify/{token}
     */
    public function verify(string $token): JsonResponse
    {
        try {
            try {
                $data = $this->service->verifyPublicToken($token);

                return $this->successResponse($data);
            } catch (HttpException $e) {
                if ($e->getStatusCode() === 404) {
                    $ppdbData = $this->ppdbReceiptService->verifyReceiptToken($token);
                    if ($ppdbData !== null) {
                        return $this->successResponse($ppdbData);
                    }
                }

                throw $e;
            }
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'NOT_FOUND', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memverifikasi dokumen.', 'INTERNAL_ERROR', 500);
        }
    }
}
