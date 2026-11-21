<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ppdb\CheckPPDBResultRequest;
use App\Services\PPDBResultService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class PPDBResultController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected PPDBResultService $service
    ) {}

    /**
     * BE-PPDB-07: Public check PPDB selection result.
     * POST /api/v1/public/ppdb/results/check
     */
    public function check(CheckPPDBResultRequest $request): JsonResponse
    {
        try {
            $data = $this->service->checkPublicResult($request->validated(), $request);

            return $this->successResponse($data);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                400 => 'VALIDATION_FAILED',
                404 => 'NOT_FOUND',
                429 => 'RATE_LIMITED',
                default => 'BUSINESS_RULE_VIOLATION',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memeriksa pengumuman hasil seleksi', 'INTERNAL_ERROR', 500);
        }
    }
}
