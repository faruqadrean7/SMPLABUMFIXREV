<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Program\SubmitProgramTaskRequest;
use App\Models\Student;
use App\Services\ProgramKhususService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class MeStudentProgramController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ProgramKhususService $service
    ) {}

    protected function getStudent(Request $request): ?Student
    {
        $user = $request->user();
        if (! $user) {
            return null;
        }

        return $user->student;
    }

    /**
     * BE-PROG-01: GET /api/v1/me/program/materials
     * Mengambil daftar materi program khusus siswa (Tahfidz/ICP/Inklusi).
     */
    public function materials(Request $request): JsonResponse
    {
        try {
            $student = $this->getStudent($request);
            if (! $student) {
                return $this->errorResponse('Akses ditolak: Profil siswa tidak ditemukan.', 'FORBIDDEN', 403);
            }

            $paginator = $this->service->getMaterialsForStudent($student, $request->query());

            return $this->paginatedResponse($paginator);
        } catch (HttpException $e) {
            return $this->handleHttpException($e);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat materi program.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-PROG-01: GET /api/v1/me/program/targets
     * Mengambil daftar target capaian program khusus siswa.
     */
    public function targets(Request $request): JsonResponse
    {
        try {
            $student = $this->getStudent($request);
            if (! $student) {
                return $this->errorResponse('Akses ditolak: Profil siswa tidak ditemukan.', 'FORBIDDEN', 403);
            }

            $paginator = $this->service->getTargetsForStudent($student, $request->query());

            return $this->paginatedResponse($paginator);
        } catch (HttpException $e) {
            return $this->handleHttpException($e);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat target capaian program.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-PROG-01: GET /api/v1/me/program/tasks
     * Mengambil daftar tugas program khusus siswa beserta riwayat pengumpulan.
     */
    public function tasks(Request $request): JsonResponse
    {
        try {
            $student = $this->getStudent($request);
            if (! $student) {
                return $this->errorResponse('Akses ditolak: Profil siswa tidak ditemukan.', 'FORBIDDEN', 403);
            }

            $paginator = $this->service->getTasksForStudent($student, $request->query());

            return $this->paginatedResponse($paginator);
        } catch (HttpException $e) {
            return $this->handleHttpException($e);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat tugas program.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-PROG-01: POST /api/v1/me/program/tasks/{id}/submit
     * Mengumpulkan tugas program khusus dengan dukungan Idempotency-Key.
     */
    public function submitTask(string $id, SubmitProgramTaskRequest $request): JsonResponse
    {
        try {
            $student = $this->getStudent($request);
            if (! $student) {
                return $this->errorResponse('Akses ditolak: Profil siswa tidak ditemukan.', 'FORBIDDEN', 403);
            }

            $idempotencyKey = $request->header('X-Idempotency-Key')
                ?? $request->header('Idempotency-Key');

            $result = $this->service->submitTask(
                taskId: $id,
                student: $student,
                data: $request->validated(),
                idempotencyKey: $idempotencyKey,
                request: $request
            );

            $statusCode = $result['is_replay'] ? 200 : 201;

            return $this->successResponse(
                $result['submission'],
                $statusCode,
                ['is_replay' => $result['is_replay']]
            );
        } catch (HttpException $e) {
            return $this->handleHttpException($e);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat mengumpulkan tugas program.', 'INTERNAL_ERROR', 500);
        }
    }

    protected function handleHttpException(HttpException $e): JsonResponse
    {
        $code = match ($e->getStatusCode()) {
            400 => 'VALIDATION_FAILED',
            401 => 'UNAUTHORIZED',
            403 => 'FORBIDDEN',
            404 => 'NOT_FOUND',
            422 => 'IDEMPOTENCY_CONFLICT',
            default => 'BUSINESS_RULE_VIOLATION',
        };

        return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
    }
}
