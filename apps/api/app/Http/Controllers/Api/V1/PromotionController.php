<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\ExecutePromotionRequest;
use App\Http\Requests\Master\PreviewPromotionRequest;
use App\Http\Requests\Master\RollbackPromotionRequest;
use App\Services\PromotionService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class PromotionController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected PromotionService $service
    ) {}

    /**
     * BE-MST-08: Preview student promotions and graduations
     * POST /api/v1/promotions/preview
     */
    public function preview(PreviewPromotionRequest $request): JsonResponse
    {
        try {
            $result = $this->service->previewPromotions($request->validated());

            return $this->successResponse($result);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                400 => 'BUSINESS_RULE_VIOLATION',
                404 => 'NOT_FOUND',
                default => 'INTERNAL_ERROR',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal menghasilkan pratinjau kenaikan kelas', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-MST-08: Execute student promotions and graduations
     * POST /api/v1/promotions/execute
     */
    public function execute(ExecutePromotionRequest $request): JsonResponse
    {
        try {
            $result = $this->service->executePromotions($request->validated(), $request);

            return $this->successResponse($result, 201);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                400 => 'BUSINESS_RULE_VIOLATION',
                404 => 'NOT_FOUND',
                409 => 'CONFLICT',
                default => 'INTERNAL_ERROR',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengeksekusi kenaikan kelas dan kelulusan', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-MST-08: Rollback executed promotions and graduations
     * POST /api/v1/promotions/rollback
     */
    public function rollback(RollbackPromotionRequest $request): JsonResponse
    {
        try {
            $result = $this->service->rollbackPromotions($request->validated(), $request);

            return $this->successResponse($result);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                400 => 'BUSINESS_RULE_VIOLATION',
                404 => 'NOT_FOUND',
                default => 'INTERNAL_ERROR',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal membatalkan proses kenaikan kelas', 'INTERNAL_ERROR', 500);
        }
    }
}
