<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ppdb\StorePPDBRequirementRequest;
use App\Http\Requests\Ppdb\UpdatePPDBRequirementRequest;
use App\Services\PPDBConfigurationService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class PPDBRequirementController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected PPDBConfigurationService $service
    ) {}

    /**
     * BE-PPDB-01: List requirements
     * GET /api/v1/ppdb/requirements
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $requirements = $this->service->listRequirements($request);

            return $this->successResponse($requirements);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil daftar syarat dokumen PPDB', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-PPDB-01: Show requirement detail
     * GET /api/v1/ppdb/requirements/{id}
     */
    public function show(string $id): JsonResponse
    {
        try {
            $requirement = $this->service->getRequirement($id);

            return $this->successResponse($requirement);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Syarat dokumen PPDB tidak ditemukan', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil detail syarat dokumen PPDB', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-PPDB-01: Create requirement
     * POST /api/v1/ppdb/requirements
     */
    public function store(StorePPDBRequirementRequest $request): JsonResponse
    {
        try {
            $requirement = $this->service->createRequirement($request->validated(), $request);

            return $this->successResponse($requirement, 201);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal membuat syarat dokumen PPDB baru', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-PPDB-01: Update requirement
     * PUT /api/v1/ppdb/requirements/{id}
     */
    public function update(UpdatePPDBRequirementRequest $request, string $id): JsonResponse
    {
        try {
            $requirement = $this->service->updateRequirement($id, $request->validated(), $request);

            return $this->successResponse($requirement);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Syarat dokumen PPDB tidak ditemukan', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal memperbarui syarat dokumen PPDB', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-PPDB-01: Delete requirement
     * DELETE /api/v1/ppdb/requirements/{id}
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $this->service->deleteRequirement($id, $request);

            return $this->successResponse(['message' => 'Syarat dokumen PPDB berhasil dihapus']);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Syarat dokumen PPDB tidak ditemukan', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal menghapus syarat dokumen PPDB', 'INTERNAL_ERROR', 500);
        }
    }
}
