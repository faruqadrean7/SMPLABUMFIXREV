<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\AddClassStudentRequest;
use App\Http\Requests\Master\StoreClassRequest;
use App\Http\Requests\Master\TransferClassStudentRequest;
use App\Http\Requests\Master\UpdateClassRequest;
use App\Services\ClassService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class ClassController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ClassService $classService
    ) {}

    /**
     * BE-MST-05: List Classes
     * GET /api/v1/classes
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $classes = $this->classService->listClasses($request);

            return $this->paginatedResponse($classes);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil daftar kelas', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-MST-05: Create Class
     * POST /api/v1/classes
     */
    public function store(StoreClassRequest $request): JsonResponse
    {
        try {
            $class = $this->classService->createClass($request->validated(), $request);

            return $this->successResponse($class, 201);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                400 => 'BUSINESS_RULE_VIOLATION',
                409 => 'CONFLICT',
                default => 'INTERNAL_ERROR',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal menambahkan rombel kelas baru', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-MST-05: Show Class Detail
     * GET /api/v1/classes/{id}
     */
    public function show(string $id): JsonResponse
    {
        try {
            $class = $this->classService->getClass($id);

            return $this->successResponse($class);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Kelas tidak ditemukan', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil data kelas', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-MST-05: Update Class
     * PUT /api/v1/classes/{id}
     */
    public function update(UpdateClassRequest $request, string $id): JsonResponse
    {
        try {
            $class = $this->classService->updateClass($id, $request->validated(), $request);

            return $this->successResponse($class);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Kelas tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                400 => 'BUSINESS_RULE_VIOLATION',
                409 => 'CONFLICT',
                default => 'INTERNAL_ERROR',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal memperbarui data kelas', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-MST-05: Delete Class (Soft delete)
     * DELETE /api/v1/classes/{id}
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $this->classService->deleteClass($id, $request);

            return $this->successResponse(['message' => 'Kelas berhasil dihapus']);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Kelas tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                400 => 'BUSINESS_RULE_VIOLATION',
                409 => 'CONFLICT',
                default => 'INTERNAL_ERROR',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal menghapus kelas', 'INTERNAL_ERROR', 500);
        }
    }

    // =========================================================================
    // CLASS ROSTER & TRANSFERS
    // =========================================================================

    /**
     * BE-MST-05: List Students in Class
     * GET /api/v1/classes/{id}/students
     */
    public function students(Request $request, string $id): JsonResponse
    {
        try {
            $students = $this->classService->listClassStudents($id, $request);

            return $this->successResponse($students);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Kelas tidak ditemukan', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil daftar siswa dalam kelas', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-MST-05: Enroll Students into Class
     * POST /api/v1/classes/{id}/students
     */
    public function addStudents(AddClassStudentRequest $request, string $id): JsonResponse
    {
        try {
            $this->classService->addClassStudents($id, $request->validated(), $request);

            return $this->successResponse(['message' => 'Siswa berhasil ditambahkan ke dalam kelas'], 201);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Kelas tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                400 => 'BUSINESS_RULE_VIOLATION',
                409 => 'CONFLICT',
                default => 'INTERNAL_ERROR',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal menambahkan siswa ke dalam kelas', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-MST-05: Transfer Student Between Classes
     * POST /api/v1/classes/{id}/students/transfer
     */
    public function transferStudent(TransferClassStudentRequest $request, string $id): JsonResponse
    {
        try {
            $this->classService->transferClassStudent($id, $request->validated(), $request);

            return $this->successResponse(['message' => 'Siswa berhasil dipindahkan ke kelas tujuan']);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Kelas atau siswa tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                400 => 'BUSINESS_RULE_VIOLATION',
                404 => 'NOT_FOUND',
                default => 'INTERNAL_ERROR',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal memindahkan siswa', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-MST-05: Remove Student from Class
     * DELETE /api/v1/classes/{id}/students/{studentId}
     */
    public function removeStudent(Request $request, string $id, string $studentId): JsonResponse
    {
        try {
            $this->classService->removeClassStudent($id, $studentId, $request);

            return $this->successResponse(['message' => 'Siswa berhasil dikeluarkan dari kelas']);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Kelas tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                404 => 'NOT_FOUND',
                default => 'INTERNAL_ERROR',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengeluarkan siswa dari kelas', 'INTERNAL_ERROR', 500);
        }
    }

    // =========================================================================
    // REFERENCE LOOKUPS
    // =========================================================================

    /**
     * GET /api/v1/grade-levels
     */
    public function gradeLevels(): JsonResponse
    {
        try {
            $grades = $this->classService->listGradeLevels();

            return $this->successResponse($grades);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil daftar tingkat kelas', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * GET /api/v1/majors
     */
    public function majors(): JsonResponse
    {
        try {
            $majors = $this->classService->listMajors();

            return $this->successResponse($majors);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil daftar jurusan', 'INTERNAL_ERROR', 500);
        }
    }
}
