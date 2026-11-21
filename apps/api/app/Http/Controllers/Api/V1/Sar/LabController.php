<?php

namespace App\Http\Controllers\Api\V1\Sar;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sar\ListLabEquipmentRequest;
use App\Http\Requests\Sar\ListLabLoanRequest;
use App\Http\Requests\Sar\ReturnLabLoanRequest;
use App\Http\Requests\Sar\StoreLabEquipmentRequest;
use App\Http\Requests\Sar\StoreLabLoanRequest;
use App\Http\Requests\Sar\UpdateLabEquipmentRequest;
use App\Services\Sar\LabService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class LabController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected LabService $service
    ) {}

    /**
     * BE-SAR-04: Ringkasan metrik inventaris alat laboratorium.
     * GET /api/v1/lab-equipment/summary
     */
    public function summary(Request $request): JsonResponse
    {
        try {
            $summary = $this->service->getSummary($request);

            return $this->successResponse($summary);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat ringkasan alat laboratorium.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-SAR-04: Daftar alat laboratorium.
     * GET /api/v1/lab-equipment
     */
    public function indexEquipment(ListLabEquipmentRequest $request): JsonResponse
    {
        try {
            $paginator = $this->service->listEquipment($request->validated(), $request);

            return $this->paginatedResponse($paginator);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat inventaris alat laboratorium.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-SAR-04: Detail alat laboratorium.
     * GET /api/v1/lab-equipment/{id}
     */
    public function showEquipment(string $id, Request $request): JsonResponse
    {
        try {
            $equipment = $this->service->getEquipment($id, $request);

            return $this->successResponse($equipment);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Alat laboratorium tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat detail alat laboratorium.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-SAR-04: Tambah alat laboratorium baru.
     * POST /api/v1/lab-equipment
     */
    public function storeEquipment(StoreLabEquipmentRequest $request): JsonResponse
    {
        try {
            $equipment = $this->service->createEquipment($request->validated(), $request);

            return $this->successResponse($equipment, 201);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal menambahkan alat laboratorium baru.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-SAR-04: Perbarui data alat laboratorium.
     * PUT /api/v1/lab-equipment/{id}
     */
    public function updateEquipment(string $id, UpdateLabEquipmentRequest $request): JsonResponse
    {
        try {
            $equipment = $this->service->updateEquipment($id, $request->validated(), $request);

            return $this->successResponse($equipment);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Alat laboratorium tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal memperbarui alat laboratorium.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-SAR-04: Hapus alat laboratorium.
     * DELETE /api/v1/lab-equipment/{id}
     */
    public function destroyEquipment(string $id, Request $request): JsonResponse
    {
        try {
            $this->service->deleteEquipment($id, $request);

            return $this->successResponse(['message' => 'Alat laboratorium berhasil dihapus.']);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Alat laboratorium tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal menghapus alat laboratorium.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-SAR-04: Daftar transaksi peminjaman alat lab.
     * GET /api/v1/lab-loans
     */
    public function indexLoans(ListLabLoanRequest $request): JsonResponse
    {
        try {
            $paginator = $this->service->listLoans($request->validated(), $request);

            return $this->paginatedResponse($paginator);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat daftar peminjaman alat lab.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-SAR-04: Detail peminjaman alat lab.
     * GET /api/v1/lab-loans/{id}
     */
    public function showLoan(string $id, Request $request): JsonResponse
    {
        try {
            $loan = $this->service->getLoan($id, $request);

            return $this->successResponse($loan);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Data peminjaman alat lab tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat detail peminjaman alat lab.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-SAR-04: Peminjaman alat lab.
     * POST /api/v1/lab-loans
     */
    public function storeLoan(StoreLabLoanRequest $request): JsonResponse
    {
        try {
            $loan = $this->service->createLoan($request->validated(), $request);

            return $this->successResponse($loan, 201);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Alat laboratorium tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengajukan peminjaman alat laboratorium.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-SAR-04: Pengembalian alat lab.
     * POST /api/v1/lab-loans/{id}/return
     */
    public function returnLoan(string $id, ReturnLabLoanRequest $request): JsonResponse
    {
        try {
            $loan = $this->service->returnLoan($id, $request->validated(), $request);

            return $this->successResponse($loan);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Data peminjaman alat lab tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal memproses pengembalian alat laboratorium.', 'INTERNAL_ERROR', 500);
        }
    }
}
