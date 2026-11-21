<?php

namespace App\Http\Controllers\Api\V1\Std;

use App\Http\Controllers\Controller;
use App\Http\Requests\Std\ListStudentPointRequest;
use App\Http\Requests\Std\StoreStudentPointRequest;
use App\Services\Std\StudentPointService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class StudentPointController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected StudentPointService $service
    ) {}

    /**
     * BE-STD-01: Daftar catatan poin siswa berpaginasi.
     * GET /api/v1/student-points
     */
    public function index(ListStudentPointRequest $request): JsonResponse
    {
        try {
            $paginator = $this->service->listStudentPoints($request->validated(), $request);

            return $this->paginatedResponse($paginator);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat daftar poin siswa.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-STD-01: Detail catatan poin siswa.
     * GET /api/v1/student-points/{id}
     */
    public function show(string $id, Request $request): JsonResponse
    {
        try {
            $point = $this->service->getStudentPoint($id, $request);

            return $this->successResponse($point);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Catatan poin tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat detail catatan poin.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-STD-01: Pencatatan poin pelanggaran/prestasi siswa oleh guru/BK/staf.
     * POST /api/v1/student-points
     */
    public function store(StoreStudentPointRequest $request): JsonResponse
    {
        try {
            $result = $this->service->recordStudentPoint($request->validated(), $request);

            return $this->successResponse($result, 201);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Siswa atau jenis poin tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mencatat poin siswa.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-STD-01: Batalkan/hapus catatan poin siswa.
     * DELETE /api/v1/student-points/{id}
     */
    public function destroy(string $id, Request $request): JsonResponse
    {
        try {
            $this->service->deleteStudentPoint($id, $request);

            return $this->successResponse(['message' => 'Catatan poin siswa berhasil dihapus.']);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Catatan poin tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal menghapus catatan poin siswa.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-STD-01: Ringkasan akumulasi poin pelanggaran & prestasi siswa.
     * GET /api/v1/students/{id}/points/summary
     */
    public function summary(string $id, Request $request): JsonResponse
    {
        try {
            $summary = $this->service->getStudentPointsSummary($id, $request);

            return $this->successResponse($summary);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Data siswa tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat ringkasan poin siswa.', 'INTERNAL_ERROR', 500);
        }
    }
}
