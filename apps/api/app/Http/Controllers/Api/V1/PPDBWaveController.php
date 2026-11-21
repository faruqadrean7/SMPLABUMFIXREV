<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ppdb\StorePPDBWaveRequest;
use App\Http\Requests\Ppdb\UpdatePPDBWaveRequest;
use App\Services\PPDBConfigurationService;
use App\Traits\ApiResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class PPDBWaveController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected PPDBConfigurationService $service
    ) {}

    /**
     * BE-PPDB-01: List waves (Admin)
     * GET /api/v1/ppdb/waves
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $waves = $this->service->listWaves($request, false);

            if ($waves instanceof LengthAwarePaginator) {
                return $this->paginatedResponse($waves);
            }

            return $this->successResponse($waves);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil daftar gelombang PPDB', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-PPDB-01: List waves (Public)
     * GET /api/v1/public/ppdb/waves
     */
    public function publicIndex(Request $request): JsonResponse
    {
        try {
            $waves = $this->service->listWaves($request, true);

            if ($waves instanceof LengthAwarePaginator) {
                return $this->paginatedResponse($waves);
            }

            return $this->successResponse($waves);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil daftar gelombang PPDB publik', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-PPDB-01: Get active wave (Public)
     * GET /api/v1/public/ppdb/waves/active
     */
    public function active(): JsonResponse
    {
        try {
            $wave = $this->service->getActiveWave();

            if (! $wave) {
                return $this->successResponse(null, 200);
            }

            return $this->successResponse($wave);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil gelombang PPDB aktif', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-PPDB-01: Show wave detail (Admin)
     * GET /api/v1/ppdb/waves/{id}
     */
    public function show(string $id): JsonResponse
    {
        try {
            $wave = $this->service->getWave($id);

            return $this->successResponse($wave);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Gelombang PPDB tidak ditemukan', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil detail gelombang PPDB', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-PPDB-01: Show wave detail (Public)
     * GET /api/v1/public/ppdb/waves/{id}
     */
    public function publicShow(string $id): JsonResponse
    {
        try {
            $wave = $this->service->getWave($id);

            return $this->successResponse($wave);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Gelombang PPDB tidak ditemukan', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil detail gelombang PPDB', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-PPDB-01: Create wave (Admin)
     * POST /api/v1/ppdb/waves
     */
    public function store(StorePPDBWaveRequest $request): JsonResponse
    {
        try {
            $wave = $this->service->createWave($request->validated(), $request);

            return $this->successResponse($wave, 201);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'VALIDATION_FAILED', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal membuat gelombang PPDB baru', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-PPDB-01: Update wave (Admin)
     * PUT /api/v1/ppdb/waves/{id}
     */
    public function update(UpdatePPDBWaveRequest $request, string $id): JsonResponse
    {
        try {
            $wave = $this->service->updateWave($id, $request->validated(), $request);

            return $this->successResponse($wave);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Gelombang PPDB tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'VALIDATION_FAILED', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal memperbarui gelombang PPDB', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-PPDB-01: Delete wave (Admin)
     * DELETE /api/v1/ppdb/waves/{id}
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $this->service->deleteWave($id, $request);

            return $this->successResponse(['message' => 'Gelombang PPDB berhasil dihapus']);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Gelombang PPDB tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'CONFLICT', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal menghapus gelombang PPDB', 'INTERNAL_ERROR', 500);
        }
    }
}
