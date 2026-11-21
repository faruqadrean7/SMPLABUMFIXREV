<?php

namespace App\Http\Controllers\Api\V1\Hum;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hum\ListPartnershipRequest;
use App\Http\Requests\Hum\StorePartnershipRequest;
use App\Http\Requests\Hum\UpdatePartnershipRequest;
use App\Services\Hum\PartnershipService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class PartnershipController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected PartnershipService $service
    ) {}

    /**
     * BE-HUM-01: Daftar kemitraan dan dokumen MoU.
     * GET /api/v1/partnerships
     */
    public function index(ListPartnershipRequest $request): JsonResponse
    {
        try {
            $paginator = $this->service->listPartnerships($request->validated(), $request);

            return $this->paginatedResponse($paginator);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat daftar kemitraan.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-HUM-01: Detail kemitraan, berkas MoU, dan riwayat program pertukaran.
     * GET /api/v1/partnerships/{id}
     */
    public function show(string $id, Request $request): JsonResponse
    {
        try {
            $partnership = $this->service->getPartnership($id, $request);

            return $this->successResponse($partnership);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Data kemitraan tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat detail kemitraan.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-HUM-01: Tambah kemitraan / dokumen MoU baru.
     * POST /api/v1/partnerships
     */
    public function store(StorePartnershipRequest $request): JsonResponse
    {
        try {
            $partnership = $this->service->createPartnership($request->validated(), $request);

            return $this->successResponse($partnership, 201);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal menambahkan data kemitraan baru.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-HUM-01: Perbarui data kemitraan / perpanjang MoU.
     * PUT /api/v1/partnerships/{id}
     */
    public function update(string $id, UpdatePartnershipRequest $request): JsonResponse
    {
        try {
            $partnership = $this->service->updatePartnership($id, $request->validated(), $request);

            return $this->successResponse($partnership);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Data kemitraan tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal memperbarui data kemitraan.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-HUM-01: Hapus data kemitraan.
     * DELETE /api/v1/partnerships/{id}
     */
    public function destroy(string $id, Request $request): JsonResponse
    {
        try {
            $this->service->deletePartnership($id, $request);

            return $this->successResponse(['message' => 'Kemitraan berhasil dihapus.']);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Data kemitraan tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal menghapus data kemitraan.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-HUM-01: Pengingat MoU yang akan kedaluwarsa.
     * GET /api/v1/partnerships/expiring
     */
    public function expiring(Request $request): JsonResponse
    {
        try {
            $days = (int) ($request->query('days', 30));
            $partnerships = $this->service->getExpiringMoUs($days);

            return $this->successResponse($partnerships);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memeriksa masa berlaku MoU.', 'INTERNAL_ERROR', 500);
        }
    }
}
