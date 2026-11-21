<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\ImportStaffRequest;
use App\Http\Requests\Master\StoreStaffRequest;
use App\Http\Requests\Master\UpdateStaffRequest;
use App\Services\TeacherStaffService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class StaffController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TeacherStaffService $teacherStaffService
    ) {}

    /**
     * BE-MST-03: List Staff
     * GET /api/v1/staff
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $staff = $this->teacherStaffService->listStaff($request);

            return $this->paginatedResponse($staff);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil daftar staf', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-MST-03: Import Staff from CSV
     * POST /api/v1/staff/import
     */
    public function import(ImportStaffRequest $request): JsonResponse
    {
        try {
            $result = $this->teacherStaffService->importStaff($request->file('file'), $request);

            if (! $result['success']) {
                return $this->errorResponse(
                    'Validasi baris import staf gagal. Harap perbaiki baris yang bermasalah.',
                    'VALIDATION_FAILED',
                    400,
                    $result['errors']
                );
            }

            return $this->successResponse($result, 201);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'BUSINESS_RULE_VIOLATION', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengimpor data staf', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-MST-03: Create Staff
     * POST /api/v1/staff
     */
    public function store(StoreStaffRequest $request): JsonResponse
    {
        try {
            $staff = $this->teacherStaffService->createStaff($request->validated(), $request);

            return $this->successResponse($staff, 201);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                400 => 'BUSINESS_RULE_VIOLATION',
                409 => 'CONFLICT',
                default => 'INTERNAL_ERROR',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal menambahkan staf baru', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-MST-03: Show Staff Detail
     * GET /api/v1/staff/{id}
     */
    public function show(string $id): JsonResponse
    {
        try {
            $staff = $this->teacherStaffService->getStaff($id);

            return $this->successResponse($staff);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Staf tidak ditemukan', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil data staf', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-MST-03: Update Staff
     * PUT /api/v1/staff/{id}
     */
    public function update(UpdateStaffRequest $request, string $id): JsonResponse
    {
        try {
            $staff = $this->teacherStaffService->updateStaff($id, $request->validated(), $request);

            return $this->successResponse($staff);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Staf tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                400 => 'BUSINESS_RULE_VIOLATION',
                409 => 'CONFLICT',
                default => 'INTERNAL_ERROR',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal memperbarui data staf', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-MST-03: Delete Staff
     * DELETE /api/v1/staff/{id}
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $this->teacherStaffService->deleteStaff($id, $request);

            return $this->successResponse(['message' => 'Data staf berhasil dihapus']);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Staf tidak ditemukan', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal menghapus data staf', 'INTERNAL_ERROR', 500);
        }
    }
}
