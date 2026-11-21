<?php

namespace App\Http\Controllers\Api\V1\Hum;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hum\ListAlumniRequest;
use App\Http\Requests\Hum\StoreAlumniRequest;
use App\Http\Requests\Hum\UpdateAlumniRequest;
use App\Services\Hum\AlumniService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class AlumniController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected AlumniService $service
    ) {}

    /**
     * BE-HUM-02: Direktori alumni terbatas.
     * GET /api/v1/alumni
     */
    public function index(ListAlumniRequest $request): JsonResponse
    {
        try {
            $paginator = $this->service->listAlumni($request->validated(), $request->user());

            return $this->paginatedResponse($paginator);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat direktori alumni.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-HUM-02: Detail data alumni dan rekam jejak studi/kerja.
     * GET /api/v1/alumni/{id}
     */
    public function show(string $id, Request $request): JsonResponse
    {
        try {
            $alumni = $this->service->getAlumni($id, $request->user());

            return $this->successResponse($alumni);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Data alumni tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat detail alumni.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-HUM-02: Registrasi data lulusan ke direktori alumni.
     * POST /api/v1/alumni
     */
    public function store(StoreAlumniRequest $request): JsonResponse
    {
        try {
            $alumni = $this->service->createAlumni($request->validated(), $request);

            return $this->successResponse($alumni, 201);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal menambahkan data alumni baru.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-HUM-02: Perbarui data alumni / kelanjutan studi / status kerja.
     * PUT /api/v1/alumni/{id}
     */
    public function update(string $id, UpdateAlumniRequest $request): JsonResponse
    {
        try {
            $alumni = $this->service->updateAlumni($id, $request->validated(), $request);

            return $this->successResponse($alumni);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Data alumni tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal memperbarui data alumni.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-HUM-02: Hapus data alumni.
     * DELETE /api/v1/alumni/{id}
     */
    public function destroy(string $id, Request $request): JsonResponse
    {
        try {
            $this->service->deleteAlumni($id, $request);

            return $this->successResponse(['message' => 'Data alumni berhasil dihapus.']);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Data alumni tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal menghapus data alumni.', 'INTERNAL_ERROR', 500);
        }
    }
}
