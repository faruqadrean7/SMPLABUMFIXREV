<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ProgressReportService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class PublicVerificationController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ProgressReportService $service
    ) {}

    /**
     * BE-GRD-03: Halaman verifikasi publik keaslian dokumen via token QR.
     * GET /api/v1/public/verify/{token}
     */
    public function verify(string $token): JsonResponse
    {
        try {
            $data = $this->service->verifyPublicToken($token);

            return $this->successResponse($data);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'NOT_FOUND', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memverifikasi dokumen.', 'INTERNAL_ERROR', 500);
        }
    }
}
