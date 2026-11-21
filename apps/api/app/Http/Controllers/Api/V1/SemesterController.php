<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreSemesterRequest;
use App\Http\Requests\Master\UpdateSemesterRequest;
use App\Services\AcademicYearService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class SemesterController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected AcademicYearService $academicYearService
    ) {}

    /**
     * BE-MST-01: List Semesters
     * GET /api/v1/semesters
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $semesters = $this->academicYearService->listSemesters($request);

            return $this->successResponse($semesters);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil daftar semester', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-MST-01: Create Semester
     * POST /api/v1/semesters
     */
    public function store(StoreSemesterRequest $request): JsonResponse
    {
        try {
            $semester = $this->academicYearService->createSemester($request->validated(), $request);

            return $this->successResponse($semester, 201);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                400 => 'BUSINESS_RULE_VIOLATION',
                409 => 'CONFLICT',
                default => 'INTERNAL_ERROR',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal menambahkan semester', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-MST-01: Show Semester Detail
     * GET /api/v1/semesters/{id}
     */
    public function show(string $id): JsonResponse
    {
        try {
            $semester = $this->academicYearService->getSemester($id);

            return $this->successResponse($semester);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Semester tidak ditemukan', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil detail semester', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-MST-01: Update Semester
     * PUT /api/v1/semesters/{id}
     */
    public function update(UpdateSemesterRequest $request, string $id): JsonResponse
    {
        try {
            $semester = $this->academicYearService->updateSemester($id, $request->validated(), $request);

            return $this->successResponse($semester);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Semester tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                400 => 'BUSINESS_RULE_VIOLATION',
                409 => 'CONFLICT',
                default => 'INTERNAL_ERROR',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal memperbarui semester', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-MST-01: Activate Semester (and its parent Academic Year)
     * PATCH /api/v1/semesters/{id}/activate
     */
    public function activate(Request $request, string $id): JsonResponse
    {
        try {
            $semester = $this->academicYearService->activateSemester($id, $request);

            return $this->successResponse($semester);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Semester tidak ditemukan', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengaktifkan semester', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-MST-01: Delete Semester
     * DELETE /api/v1/semesters/{id}
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $this->academicYearService->deleteSemester($id, $request);

            return $this->successResponse(['message' => 'Semester berhasil dihapus']);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Semester tidak ditemukan', 'NOT_FOUND', 404);
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

            return $this->errorResponse('Gagal menghapus semester', 'INTERNAL_ERROR', 500);
        }
    }
}
