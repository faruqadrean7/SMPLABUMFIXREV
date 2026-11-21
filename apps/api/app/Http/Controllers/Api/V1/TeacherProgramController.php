<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Program\GradeProgramSubmissionRequest;
use App\Http\Requests\Program\StoreProgramMaterialRequest;
use App\Http\Requests\Program\StoreProgramTargetRequest;
use App\Http\Requests\Program\StoreProgramTaskRequest;
use App\Http\Requests\Program\UpdateProgramMaterialRequest;
use App\Http\Requests\Program\UpdateProgramTargetRequest;
use App\Http\Requests\Program\UpdateProgramTaskRequest;
use App\Services\ProgramKhususService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class TeacherProgramController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ProgramKhususService $service
    ) {}

    // =========================================================================
    // MATERI PROGRAM KHUSUS
    // =========================================================================

    /**
     * BE-PROG-02: GET /api/v1/program/materials
     */
    public function indexMaterials(Request $request): JsonResponse
    {
        try {
            $paginator = $this->service->listMaterialsForTeacher($request->user(), $request->query());

            return $this->paginatedResponse($paginator);
        } catch (HttpException $e) {
            return $this->handleHttpException($e);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat mengambil daftar materi.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-PROG-02: POST /api/v1/program/materials
     */
    public function storeMaterial(StoreProgramMaterialRequest $request): JsonResponse
    {
        try {
            $material = $this->service->createMaterial($request->user(), $request->validated(), $request);

            return $this->successResponse($material, 201, ['message' => 'Materi program berhasil dibuat.']);
        } catch (HttpException $e) {
            return $this->handleHttpException($e);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat menambahkan materi program.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-PROG-02: PATCH /api/v1/program/materials/{id}
     */
    public function updateMaterial(UpdateProgramMaterialRequest $request, string $id): JsonResponse
    {
        try {
            $material = $this->service->updateMaterial($request->user(), $id, $request->validated(), $request);

            return $this->successResponse($material, 200, ['message' => 'Materi program berhasil diperbarui.']);
        } catch (HttpException $e) {
            return $this->handleHttpException($e);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memperbarui materi program.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-PROG-02: DELETE /api/v1/program/materials/{id}
     */
    public function destroyMaterial(Request $request, string $id): JsonResponse
    {
        try {
            $this->service->deleteMaterial($request->user(), $id, $request);

            return $this->successResponse(['id' => $id], 200, ['message' => 'Materi program berhasil dihapus.']);
        } catch (HttpException $e) {
            return $this->handleHttpException($e);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat menghapus materi program.', 'INTERNAL_ERROR', 500);
        }
    }

    // =========================================================================
    // TARGET CAPAIAN BELAJAR SISWA
    // =========================================================================

    /**
     * BE-PROG-02: GET /api/v1/program/targets
     */
    public function indexTargets(Request $request): JsonResponse
    {
        try {
            $paginator = $this->service->listTargetsForTeacher($request->user(), $request->query());

            return $this->paginatedResponse($paginator);
        } catch (HttpException $e) {
            return $this->handleHttpException($e);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat mengambil daftar target capaian.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-PROG-02: POST /api/v1/program/targets
     */
    public function storeTarget(StoreProgramTargetRequest $request): JsonResponse
    {
        try {
            $target = $this->service->createTarget($request->user(), $request->validated(), $request);

            return $this->successResponse($target, 201, ['message' => 'Target capaian program berhasil dibuat.']);
        } catch (HttpException $e) {
            return $this->handleHttpException($e);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat menambahkan target capaian program.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-PROG-02: PATCH /api/v1/program/targets/{id}
     */
    public function updateTarget(UpdateProgramTargetRequest $request, string $id): JsonResponse
    {
        try {
            $target = $this->service->updateTarget($request->user(), $id, $request->validated(), $request);

            return $this->successResponse($target, 200, ['message' => 'Target capaian program berhasil diperbarui.']);
        } catch (HttpException $e) {
            return $this->handleHttpException($e);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memperbarui target capaian program.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-PROG-02: DELETE /api/v1/program/targets/{id}
     */
    public function destroyTarget(Request $request, string $id): JsonResponse
    {
        try {
            $this->service->deleteTarget($request->user(), $id, $request);

            return $this->successResponse(['id' => $id], 200, ['message' => 'Target capaian berhasil dihapus.']);
        } catch (HttpException $e) {
            return $this->handleHttpException($e);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat menghapus target capaian program.', 'INTERNAL_ERROR', 500);
        }
    }

    // =========================================================================
    // TUGAS & PENUGASAN PROGRAM KHUSUS
    // =========================================================================

    /**
     * BE-PROG-02: GET /api/v1/program/tasks
     */
    public function indexTasks(Request $request): JsonResponse
    {
        try {
            $paginator = $this->service->listTasksForTeacher($request->user(), $request->query());

            return $this->paginatedResponse($paginator);
        } catch (HttpException $e) {
            return $this->handleHttpException($e);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat mengambil daftar tugas.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-PROG-02: POST /api/v1/program/tasks
     */
    public function storeTask(StoreProgramTaskRequest $request): JsonResponse
    {
        try {
            $task = $this->service->createTask($request->user(), $request->validated(), $request);

            return $this->successResponse($task, 201, ['message' => 'Tugas program berhasil dibuat.']);
        } catch (HttpException $e) {
            return $this->handleHttpException($e);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat membuat tugas program.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-PROG-02: PATCH /api/v1/program/tasks/{id}
     */
    public function updateTask(UpdateProgramTaskRequest $request, string $id): JsonResponse
    {
        try {
            $task = $this->service->updateTask($request->user(), $id, $request->validated(), $request);

            return $this->successResponse($task, 200, ['message' => 'Tugas program berhasil diperbarui.']);
        } catch (HttpException $e) {
            return $this->handleHttpException($e);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memperbarui tugas program.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-PROG-02: DELETE /api/v1/program/tasks/{id}
     */
    public function destroyTask(Request $request, string $id): JsonResponse
    {
        try {
            $this->service->deleteTask($request->user(), $id, $request);

            return $this->successResponse(['id' => $id], 200, ['message' => 'Tugas program berhasil dihapus.']);
        } catch (HttpException $e) {
            return $this->handleHttpException($e);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat menghapus tugas program.', 'INTERNAL_ERROR', 500);
        }
    }

    // =========================================================================
    // SUBMISSIONS & PENILAIAN
    // =========================================================================

    /**
     * BE-PROG-02: GET /api/v1/program/tasks/{id}/submissions
     */
    public function taskSubmissions(Request $request, string $id): JsonResponse
    {
        try {
            $paginator = $this->service->listSubmissionsForTask($request->user(), $id, $request->query());

            return $this->paginatedResponse($paginator);
        } catch (HttpException $e) {
            return $this->handleHttpException($e);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat submission tugas.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-PROG-02: PATCH /api/v1/program/submissions/{id}
     */
    public function gradeSubmission(GradeProgramSubmissionRequest $request, string $id): JsonResponse
    {
        try {
            $submission = $this->service->gradeSubmission($request->user(), $id, $request->validated(), $request);

            return $this->successResponse($submission, 200, ['message' => 'Penilaian tugas berhasil disimpan.']);
        } catch (HttpException $e) {
            return $this->handleHttpException($e);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat menyimpan penilaian tugas.', 'INTERNAL_ERROR', 500);
        }
    }

    protected function handleHttpException(HttpException $e): JsonResponse
    {
        $code = match ($e->getStatusCode()) {
            400 => 'BAD_REQUEST',
            401 => 'UNAUTHORIZED',
            403 => 'FORBIDDEN',
            404 => 'NOT_FOUND',
            422 => 'VALIDATION_FAILED',
            default => 'BUSINESS_RULE_VIOLATION',
        };

        return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
    }
}
