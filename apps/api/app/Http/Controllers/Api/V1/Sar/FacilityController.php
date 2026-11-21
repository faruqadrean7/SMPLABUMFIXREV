<?php

namespace App\Http\Controllers\Api\V1\Sar;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sar\StoreFacilityRequest;
use App\Http\Requests\Sar\UpdateFacilityRequest;
use App\Services\Sar\FacilityService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class FacilityController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected FacilityService $service
    ) {}

    /**
     * BE-SAR-01: Daftar fasilitas & ruangan.
     * GET /api/v1/facilities
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $paginator = $this->service->listFacilities($request->all(), $request);

            return $this->paginatedResponse($paginator);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat daftar fasilitas.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-SAR-01: Detail fasilitas.
     * GET /api/v1/facilities/{id}
     */
    public function show(string $id, Request $request): JsonResponse
    {
        try {
            $facility = $this->service->getFacility($id, $request);

            return $this->successResponse($facility);
        } catch (ModelNotFoundException) {
            return $this->notFoundResponse('Fasilitas tidak ditemukan.');
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat detail fasilitas.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-SAR-01: Tambah fasilitas baru (Admin / Sarpras).
     * POST /api/v1/facilities
     */
    public function store(StoreFacilityRequest $request): JsonResponse
    {
        try {
            $facility = $this->service->createFacility($request->validated(), $request);

            return $this->successResponse($facility, 201);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat menyimpan fasilitas.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-SAR-01: Perbarui fasilitas (Admin / Sarpras).
     * PUT /api/v1/facilities/{id}
     */
    public function update(string $id, UpdateFacilityRequest $request): JsonResponse
    {
        try {
            $facility = $this->service->updateFacility($id, $request->validated(), $request);

            return $this->successResponse($facility);
        } catch (ModelNotFoundException) {
            return $this->notFoundResponse('Fasilitas tidak ditemukan.');
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memperbarui fasilitas.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-SAR-01: Hapus fasilitas (Admin / Sarpras).
     * DELETE /api/v1/facilities/{id}
     */
    public function destroy(string $id, Request $request): JsonResponse
    {
        try {
            $this->service->deleteFacility($id, $request);

            return $this->successResponse(['message' => 'Fasilitas berhasil dihapus.'], 200);
        } catch (ModelNotFoundException) {
            return $this->notFoundResponse('Fasilitas tidak ditemukan.');
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat menghapus fasilitas.', 'INTERNAL_ERROR', 500);
        }
    }
}
