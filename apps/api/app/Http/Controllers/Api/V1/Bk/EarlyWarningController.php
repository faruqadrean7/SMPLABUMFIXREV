<?php

namespace App\Http\Controllers\Api\V1\Bk;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bk\EvaluateEarlyWarningRequest;
use App\Http\Requests\Bk\HandleEarlyWarningRequest;
use App\Http\Requests\Bk\ListEarlyWarningRequest;
use App\Services\Bk\EarlyWarningService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class EarlyWarningController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected EarlyWarningService $service
    ) {}

    /**
     * BE-BK-02: Ambil ringkasan metrik peringatan dini.
     * GET /api/v1/early-warnings/summary
     */
    public function summary(Request $request): JsonResponse
    {
        try {
            $summary = $this->service->getSummary($request);

            return $this->successResponse($summary, 200);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat ringkasan peringatan dini.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-BK-02: Daftar peringatan dini.
     * GET /api/v1/early-warnings
     */
    public function index(ListEarlyWarningRequest $request): JsonResponse
    {
        try {
            $paginator = $this->service->listWarnings($request->validated(), $request);

            return $this->paginatedResponse($paginator);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat daftar peringatan dini.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-BK-02: Detail peringatan dini.
     * GET /api/v1/early-warnings/{id}
     */
    public function show(string $id): JsonResponse
    {
        try {
            $warning = $this->service->getWarning($id, request());

            return $this->successResponse($warning, 200);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Data peringatan dini tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat data peringatan dini.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-BK-02: Evaluasi otomatis aturan deteksi dini.
     * POST /api/v1/early-warnings/evaluate
     */
    public function evaluate(EvaluateEarlyWarningRequest $request): JsonResponse
    {
        try {
            $result = $this->service->evaluateRules($request->validated(), $request);

            return $this->successResponse($result, 200);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat mengevaluasi deteksi dini: '.$e->getMessage(), 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-BK-02: Tindak lanjuti / tangani peringatan dini.
     * PATCH /api/v1/early-warnings/{id}/handle
     */
    public function handle(string $id, HandleEarlyWarningRequest $request): JsonResponse
    {
        try {
            $warning = $this->service->handleWarning($id, $request->validated(), $request);

            return $this->successResponse($warning, 200);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Data peringatan dini tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $errorCode = $e->getStatusCode() === 422 ? 'VALIDATION_ERROR' : 'FORBIDDEN';

            return $this->errorResponse($e->getMessage(), $errorCode, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat menindaklanjuti peringatan dini.', 'INTERNAL_ERROR', 500);
        }
    }
}
