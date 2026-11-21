<?php

namespace App\Http\Controllers\Api\V1\Bk;

use App\Http\Controllers\Controller;
use App\Services\Bk\CounselingService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class CounselingSessionController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected CounselingService $service
    ) {}

    /**
     * BE-BK-01: Detail sesi konseling (dengan audit baca).
     * GET /api/v1/counseling/sessions/{id}
     */
    public function show(string $id, Request $request): JsonResponse
    {
        try {
            $session = $this->service->getSession($id, $request);

            return $this->successResponse($session, 200);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Data sesi konseling tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat data sesi konseling.', 'INTERNAL_ERROR', 500);
        }
    }
}
