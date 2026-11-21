<?php

namespace App\Http\Controllers\Api\V1\Rpt;

use App\Http\Controllers\Controller;
use App\Services\Rpt\DashboardService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class DashboardController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected DashboardService $service
    ) {}

    /**
     * BE-RPT-02: Dashboard Eksekutif untuk Pimpinan / Kepala Sekolah.
     * GET /api/v1/dashboard/executive
     */
    public function executive(Request $request): JsonResponse
    {
        try {
            $data = $this->service->getExecutiveDashboard($request);

            return $this->successResponse($data);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat dashboard eksekutif.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-RPT-02: Dashboard Guru.
     * GET /api/v1/dashboard/teacher
     */
    public function teacher(Request $request): JsonResponse
    {
        try {
            $data = $this->service->getTeacherDashboard($request);

            return $this->successResponse($data);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat dashboard guru.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-RPT-02: Dashboard Siswa.
     * GET /api/v1/dashboard/student
     */
    public function student(Request $request): JsonResponse
    {
        try {
            $data = $this->service->getStudentDashboard($request);

            return $this->successResponse($data);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat dashboard siswa.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-RPT-02: Dashboard Wali Murid / Orang Tua.
     * GET /api/v1/dashboard/guardian/{studentId?}
     */
    public function guardian(Request $request, ?string $studentId = null): JsonResponse
    {
        try {
            $data = $this->service->getGuardianDashboard($studentId, $request);

            return $this->successResponse($data);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Data siswa tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat dashboard wali murid.', 'INTERNAL_ERROR', 500);
        }
    }
}
