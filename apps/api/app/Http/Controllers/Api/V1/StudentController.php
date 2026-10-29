<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\ChangeStudentStatusRequest;
use App\Http\Requests\Master\ImportStudentsRequest;
use App\Http\Requests\Master\StoreStudentRequest;
use App\Http\Requests\Master\UpdateStudentRequest;
use App\Services\StudentService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class StudentController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected StudentService $studentService
    ) {}

    /**
     * BE-MST-02: List Students
     * GET /api/v1/students
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $students = $this->studentService->listStudents($request);

            return $this->paginatedResponse($students);
        } catch (HttpException $e) {
            return $this->handleHttpException($e);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil daftar siswa', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-MST-02: Export Students to CSV
     * GET /api/v1/students/export
     */
    public function export(Request $request): StreamedResponse|JsonResponse
    {
        try {
            return $this->studentService->exportStudents($request);
        } catch (HttpException $e) {
            return $this->handleHttpException($e);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengekspor data siswa', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-MST-02: Import Students from CSV
     * POST /api/v1/students/import
     */
    public function import(ImportStudentsRequest $request): JsonResponse
    {
        try {
            $result = $this->studentService->importStudents($request->file('file'), $request);

            if (! $result['success']) {
                return $this->errorResponse(
                    'Validasi baris import gagal. Harap perbaiki baris yang bermasalah.',
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

            return $this->errorResponse('Gagal mengimpor data siswa', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-MST-02: Create Student
     * POST /api/v1/students
     */
    public function store(StoreStudentRequest $request): JsonResponse
    {
        try {
            $student = $this->studentService->createStudent($request->validated(), $request);

            return $this->successResponse($student, 201);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                400 => 'BUSINESS_RULE_VIOLATION',
                409 => 'CONFLICT',
                default => 'INTERNAL_ERROR',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal menambahkan siswa baru', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-MST-02: Show Student Detail
     * GET /api/v1/students/{id}
     */
    public function show(Request $request, string $id): JsonResponse
    {
        try {
            $student = $this->studentService->getStudent($id, $request);

            return $this->successResponse($student);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Siswa tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                403 => 'FORBIDDEN',
                404 => 'NOT_FOUND',
                default => 'INTERNAL_ERROR',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil data siswa', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-MST-02: Update Student
     * PUT /api/v1/students/{id}
     */
    public function update(UpdateStudentRequest $request, string $id): JsonResponse
    {
        try {
            $student = $this->studentService->updateStudent($id, $request->validated(), $request);

            return $this->successResponse($student);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Siswa tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                400 => 'BUSINESS_RULE_VIOLATION',
                409 => 'CONFLICT',
                default => 'INTERNAL_ERROR',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal memperbarui data siswa', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-MST-02: Delete Student
     * DELETE /api/v1/students/{id}
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $this->studentService->deleteStudent($id, $request);

            return $this->successResponse(['message' => 'Data siswa berhasil dihapus']);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Siswa tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->handleHttpException($e);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal menghapus data siswa', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-MST-02: Change Student Status
     * POST /api/v1/students/{id}/status
     */
    public function changeStatus(ChangeStudentStatusRequest $request, string $id): JsonResponse
    {
        try {
            $student = $this->studentService->changeStudentStatus($id, $request->validated(), $request);

            return $this->successResponse($student);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Siswa tidak ditemukan', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengubah status siswa', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-MST-02: Get Student Status History
     * GET /api/v1/students/{id}/status-history
     */
    public function statusHistory(Request $request, string $id): JsonResponse
    {
        try {
            $history = $this->studentService->getStatusHistory($id, $request);

            return $this->successResponse($history);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Siswa tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                403 => 'FORBIDDEN',
                404 => 'NOT_FOUND',
                default => 'INTERNAL_ERROR',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil riwayat status siswa', 'INTERNAL_ERROR', 500);
        }
    }
}
