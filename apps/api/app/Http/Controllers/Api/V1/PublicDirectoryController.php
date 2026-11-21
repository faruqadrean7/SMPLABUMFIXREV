<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\PublicDirectoryService;
use App\Traits\ApiResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class PublicDirectoryController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected PublicDirectoryService $service
    ) {}

    /**
     * BE-CMS-05: List public teachers
     * GET /api/v1/public/teachers
     */
    public function teachers(Request $request): JsonResponse
    {
        try {
            $teachers = $this->service->listPublicTeachers($request);

            if ($teachers instanceof LengthAwarePaginator) {
                return $this->paginatedResponse($teachers);
            }

            return $this->successResponse($teachers);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil direktori guru publik', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CMS-05: Show public teacher detail
     * GET /api/v1/public/teachers/{id}
     */
    public function showTeacher(string $id): JsonResponse
    {
        try {
            $teacher = $this->service->getPublicTeacher($id);

            return $this->successResponse($teacher);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Data guru tidak ditemukan atau tidak aktif', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil detail guru publik', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CMS-05: List public staff
     * GET /api/v1/public/staff
     */
    public function staff(Request $request): JsonResponse
    {
        try {
            $staff = $this->service->listPublicStaff($request);

            if ($staff instanceof LengthAwarePaginator) {
                return $this->paginatedResponse($staff);
            }

            return $this->successResponse($staff);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil direktori staf publik', 'INTERNAL_ERROR', 500);
        }
    }
}
