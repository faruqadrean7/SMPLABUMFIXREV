<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\StoreGradeComponentRequest;
use App\Http\Requests\Academic\UpdateGradeComponentRequest;
use App\Services\GradeService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class GradeComponentController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected GradeService $service
    ) {}

    /**
     * BE-GRD-01: Daftar komponen nilai.
     * GET /api/v1/grade-components
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $components = $this->service->listComponents($request->all());

            return $this->successResponse($components);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat mengambil daftar komponen nilai', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-GRD-01: Buat komponen nilai baru.
     * POST /api/v1/grade-components
     */
    public function store(StoreGradeComponentRequest $request): JsonResponse
    {
        try {
            $component = $this->service->createComponent($request->validated(), $request);

            return $this->successResponse($component, 201);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                403 => 'FORBIDDEN',
                default => 'BAD_REQUEST',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat membuat komponen nilai', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-GRD-01: Update komponen nilai.
     * PUT /api/v1/grade-components/{id}
     */
    public function update(string $id, UpdateGradeComponentRequest $request): JsonResponse
    {
        try {
            $component = $this->service->updateComponent($id, $request->validated(), $request);

            return $this->successResponse($component);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Komponen nilai tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memperbarui komponen nilai', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-GRD-01: Hapus komponen nilai.
     * DELETE /api/v1/grade-components/{id}
     */
    public function destroy(string $id, Request $request): JsonResponse
    {
        try {
            $this->service->deleteComponent($id, $request);

            return $this->successResponse(['message' => 'Komponen nilai berhasil dihapus']);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Komponen nilai tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                422 => 'BUSINESS_RULE_VIOLATION',
                403 => 'FORBIDDEN',
                default => 'BAD_REQUEST',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat menghapus komponen nilai', 'INTERNAL_ERROR', 500);
        }
    }
}
