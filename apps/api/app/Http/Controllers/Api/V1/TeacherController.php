<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\ImportTeachersRequest;
use App\Http\Requests\Master\StoreSalarySlipRequest;
use App\Http\Requests\Master\StoreTeacherRequest;
use App\Http\Requests\Master\UpdateTeacherRequest;
use App\Models\Teacher;
use App\Services\TeacherStaffService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class TeacherController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TeacherStaffService $teacherStaffService
    ) {}

    /**
     * BE-MST-03: List Teachers
     * GET /api/v1/teachers
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $teachers = $this->teacherStaffService->listTeachers($request);

            return $this->paginatedResponse($teachers);
        } catch (HttpException $e) {
            return $this->handleHttpException($e);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil daftar guru', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-MST-03: Import Teachers from CSV
     * POST /api/v1/teachers/import
     */
    public function import(ImportTeachersRequest $request): JsonResponse
    {
        try {
            $result = $this->teacherStaffService->importTeachers($request->file('file'), $request);

            if (! $result['success']) {
                return $this->errorResponse(
                    'Validasi baris import guru gagal. Harap perbaiki baris yang bermasalah.',
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

            return $this->errorResponse('Gagal mengimpor data guru', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-MST-03: Create Teacher
     * POST /api/v1/teachers
     */
    public function store(StoreTeacherRequest $request): JsonResponse
    {
        try {
            $teacher = $this->teacherStaffService->createTeacher($request->validated(), $request);

            return $this->successResponse($teacher, 201);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                400 => 'BUSINESS_RULE_VIOLATION',
                409 => 'CONFLICT',
                default => 'INTERNAL_ERROR',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal menambahkan guru baru', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-MST-03: Show Teacher Detail
     * GET /api/v1/teachers/{id}
     */
    public function show(string $id): JsonResponse
    {
        try {
            $teacher = $this->teacherStaffService->getTeacher($id);

            return $this->successResponse($teacher);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Guru tidak ditemukan', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil data guru', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-MST-03: Update Teacher
     * PUT /api/v1/teachers/{id}
     */
    public function update(UpdateTeacherRequest $request, string $id): JsonResponse
    {
        try {
            $teacher = $this->teacherStaffService->updateTeacher($id, $request->validated(), $request);

            return $this->successResponse($teacher);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Guru tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                400 => 'BUSINESS_RULE_VIOLATION',
                409 => 'CONFLICT',
                default => 'INTERNAL_ERROR',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal memperbarui data guru', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-MST-03: Delete Teacher
     * DELETE /api/v1/teachers/{id}
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $this->teacherStaffService->deleteTeacher($id, $request);

            return $this->successResponse(['message' => 'Data guru berhasil dihapus']);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Guru tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                400 => 'BUSINESS_RULE_VIOLATION',
                409 => 'CONFLICT',
                default => 'INTERNAL_ERROR',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal menghapus data guru', 'INTERNAL_ERROR', 500);
        }
    }

    // =========================================================================
    // SLIP GAJI & REKAP JAM MENGAJAR
    // =========================================================================

    /**
     * BE-MST-03 / TCH-SAL-01: List salary slips of a teacher
     * GET /api/v1/teachers/{id}/salary-slips
     */
    public function listSalarySlips(Request $request, string $id): JsonResponse
    {
        try {
            $slips = $this->teacherStaffService->listSalarySlips($id, $request);

            return $this->successResponse($slips);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Guru tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil daftar slip gaji', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-MST-03 / TCH-SAL-01: Upload & create salary slip
     * POST /api/v1/teachers/{id}/salary-slips
     */
    public function storeSalarySlip(StoreSalarySlipRequest $request, string $id): JsonResponse
    {
        try {
            $slip = $this->teacherStaffService->storeSalarySlip($id, $request->validated(), $request);

            return $this->successResponse($slip, 201);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Guru tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                403 => 'FORBIDDEN',
                422 => 'VALIDATION_FAILED',
                default => 'BUSINESS_RULE_VIOLATION',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal menyimpan slip gaji', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-MST-03 / TCH-SAL-01: Get detail of a salary slip
     * GET /api/v1/teachers/{id}/salary-slips/{slipId}
     */
    public function showSalarySlip(Request $request, string $id, string $slipId): JsonResponse
    {
        try {
            $slip = $this->teacherStaffService->getSalarySlip($id, $slipId, $request);

            return $this->successResponse($slip);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Slip gaji atau guru tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil data slip gaji', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-MST-03 / TCH-SAL-01: Download salary slip file
     * GET /api/v1/teachers/{id}/salary-slips/{slipId}/download
     */
    public function downloadSalarySlip(Request $request, string $id, string $slipId): StreamedResponse|JsonResponse
    {
        try {
            return $this->teacherStaffService->downloadSalarySlip($id, $slipId, $request);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Slip gaji atau guru tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengunduh berkas slip gaji', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-MST-03 / TCH-SAL-01: Delete salary slip
     * DELETE /api/v1/teachers/{id}/salary-slips/{slipId}
     */
    public function destroySalarySlip(Request $request, string $id, string $slipId): JsonResponse
    {
        try {
            $this->teacherStaffService->deleteSalarySlip($id, $slipId, $request);

            return $this->successResponse(['message' => 'Slip gaji berhasil dihapus']);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Slip gaji atau guru tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal menghapus slip gaji', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * TCH-JJM-01: Get teaching hours recap (JJM) of a teacher
     * GET /api/v1/teachers/{id}/teaching-recap
     */
    public function teachingRecap(string $id): JsonResponse
    {
        try {
            $recap = $this->teacherStaffService->getTeachingHoursRecap($id);

            return $this->successResponse($recap);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Guru tidak ditemukan', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil rekap jam mengajar guru', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * TCH-SAL-02: Authenticated teacher views their own salary slips
     * GET /api/v1/teacher/me/salary-slips
     */
    public function mySalarySlips(Request $request): JsonResponse
    {
        try {
            $slips = $this->teacherStaffService->getMySalarySlips($request);

            return $this->successResponse($slips);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil daftar slip gaji pribadi', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * TCH-SAL-02: Authenticated teacher downloads their own salary slip
     * GET /api/v1/teacher/me/salary-slips/{slipId}/download
     */
    public function downloadMySalarySlip(Request $request, string $slipId): StreamedResponse|JsonResponse
    {
        try {
            return $this->teacherStaffService->downloadMySalarySlip($slipId, $request);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengunduh berkas slip gaji', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * TCH-JJM-02: Authenticated teacher views their own teaching hours recap
     * GET /api/v1/teacher/me/teaching-recap
     */
    public function myTeachingRecap(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $teacher = Teacher::where('user_id', $user?->id)->first();
            if (! $teacher) {
                return $this->errorResponse('Profil pendidik guru Anda belum terdaftar.', 'NOT_FOUND', 404);
            }

            $recap = $this->teacherStaffService->getTeachingHoursRecap($teacher->id);

            return $this->successResponse($recap);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil rekap jam mengajar pribadi', 'INTERNAL_ERROR', 500);
        }
    }
}
