<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\LockGradePeriodRequest;
use App\Services\GradeService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class GradePeriodController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected GradeService $service
    ) {}

    /**
     * BE-GRD-01: Daftar periode penilaian bulanan.
     * GET /api/v1/grade-periods
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $periods = $this->service->listPeriods($request->all(), $request);

            return $this->successResponse($periods);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat mengambil daftar periode penilaian', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-GRD-01: Kunci / buka kunci periode penilaian.
     * PATCH /api/v1/grade-periods/{id}/lock
     */
    public function lock(string $id, LockGradePeriodRequest $request): JsonResponse
    {
        try {
            $period = $this->service->togglePeriodLock($id, $request->validated(), $request);

            return $this->successResponse($period);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Periode penilaian tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat mengubah status kunci periode penilaian', 'INTERNAL_ERROR', 500);
        }
    }
}
