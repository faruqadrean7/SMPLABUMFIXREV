<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreAcademicYearRequest;
use App\Http\Requests\Master\UpdateAcademicYearRequest;
use App\Services\AcademicYearService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class AcademicYearController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected AcademicYearService $academicYearService
    ) {}

    /**
     * BE-MST-01: List Academic Years
     * GET /api/v1/academic-years
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $years = $this->academicYearService->listAcademicYears($request);

            return $this->paginatedResponse($years);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil daftar tahun ajaran', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-MST-01: Get Active Academic Year and Semester Context
     * GET /api/v1/academic-years/active
     */
    public function active(): JsonResponse
    {
        try {
            $period = $this->academicYearService->getActivePeriod();

            return $this->successResponse($period);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil periode aktif', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-MST-01: Create Academic Year
     * POST /api/v1/academic-years
     */
    public function store(StoreAcademicYearRequest $request): JsonResponse
    {
        try {
            $year = $this->academicYearService->createAcademicYear($request->validated(), $request);

            return $this->successResponse($year, 201);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                400 => 'BUSINESS_RULE_VIOLATION',
                409 => 'CONFLICT',
                default => 'INTERNAL_ERROR',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal menambahkan tahun ajaran', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-MST-01: Show Academic Year Detail
     * GET /api/v1/academic-years/{id}
     */
    public function show(string $id): JsonResponse
    {
        try {
            $year = $this->academicYearService->getAcademicYear($id);

            return $this->successResponse($year);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Tahun ajaran tidak ditemukan', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil detail tahun ajaran', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-MST-01: Update Academic Year
     * PUT /api/v1/academic-years/{id}
     */
    public function update(UpdateAcademicYearRequest $request, string $id): JsonResponse
    {
        try {
            $year = $this->academicYearService->updateAcademicYear($id, $request->validated(), $request);

            return $this->successResponse($year);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Tahun ajaran tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                400 => 'BUSINESS_RULE_VIOLATION',
                409 => 'CONFLICT',
                default => 'INTERNAL_ERROR',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal memperbarui tahun ajaran', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-MST-01: Activate Academic Year
     * PATCH /api/v1/academic-years/{id}/activate
     */
    public function activate(Request $request, string $id): JsonResponse
    {
        try {
            $year = $this->academicYearService->activateAcademicYear($id, $request);

            return $this->successResponse($year);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Tahun ajaran tidak ditemukan', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengaktifkan tahun ajaran', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-MST-01: Delete Academic Year
     * DELETE /api/v1/academic-years/{id}
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $this->academicYearService->deleteAcademicYear($id, $request);

            return $this->successResponse(['message' => 'Tahun ajaran berhasil dihapus']);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Tahun ajaran tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                400 => 'BUSINESS_RULE_VIOLATION',
                409 => 'CONFLICT',
                404 => 'NOT_FOUND',
                default => 'INTERNAL_ERROR',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal menghapus tahun ajaran', 'INTERNAL_ERROR', 500);
        }
    }
}
