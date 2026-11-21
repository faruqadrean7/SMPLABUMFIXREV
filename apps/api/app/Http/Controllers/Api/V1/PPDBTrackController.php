<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ppdb\StorePPDBTrackRequest;
use App\Http\Requests\Ppdb\UpdatePPDBTrackRequest;
use App\Services\PPDBConfigurationService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class PPDBTrackController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected PPDBConfigurationService $service
    ) {}

    /**
     * BE-PPDB-01: List tracks (Admin)
     * GET /api/v1/ppdb/tracks
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $tracks = $this->service->listTracks($request);

            return $this->successResponse($tracks);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil daftar jalur PPDB', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-PPDB-01: List tracks (Public)
     * GET /api/v1/public/ppdb/tracks
     */
    public function publicIndex(Request $request): JsonResponse
    {
        try {
            $tracks = $this->service->listTracks($request);

            return $this->successResponse($tracks);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil daftar jalur PPDB publik', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-PPDB-01: Show track detail (Admin)
     * GET /api/v1/ppdb/tracks/{id}
     */
    public function show(string $id): JsonResponse
    {
        try {
            $track = $this->service->getTrack($id);

            return $this->successResponse($track);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Jalur PPDB tidak ditemukan', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil detail jalur PPDB', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-PPDB-01: Show track detail (Public)
     * GET /api/v1/public/ppdb/tracks/{id}
     */
    public function publicShow(string $id): JsonResponse
    {
        try {
            $track = $this->service->getTrack($id);

            return $this->successResponse($track);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Jalur PPDB tidak ditemukan', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil detail jalur PPDB', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-PPDB-01: Create track (Admin)
     * POST /api/v1/ppdb/tracks
     */
    public function store(StorePPDBTrackRequest $request): JsonResponse
    {
        try {
            $track = $this->service->createTrack($request->validated(), $request);

            return $this->successResponse($track, 201);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal membuat jalur PPDB baru', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-PPDB-01: Update track (Admin)
     * PUT /api/v1/ppdb/tracks/{id}
     */
    public function update(UpdatePPDBTrackRequest $request, string $id): JsonResponse
    {
        try {
            $track = $this->service->updateTrack($id, $request->validated(), $request);

            return $this->successResponse($track);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Jalur PPDB tidak ditemukan', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal memperbarui jalur PPDB', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-PPDB-01: Delete track (Admin)
     * DELETE /api/v1/ppdb/tracks/{id}
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $this->service->deleteTrack($id, $request);

            return $this->successResponse(['message' => 'Jalur PPDB berhasil dihapus']);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Jalur PPDB tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'CONFLICT', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal menghapus jalur PPDB', 'INTERNAL_ERROR', 500);
        }
    }
}
