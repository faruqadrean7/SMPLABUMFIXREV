<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\AttachStudentGuardianRequest;
use App\Http\Requests\Master\StoreGuardianRequest;
use App\Http\Requests\Master\UpdateGuardianRequest;
use App\Http\Requests\Master\UpdateStudentGuardianRequest;
use App\Services\GuardianService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class GuardianController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected GuardianService $guardianService
    ) {}

    /**
     * BE-MST-04: List Guardians
     * GET /api/v1/guardians
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $guardians = $this->guardianService->listGuardians($request);

            return $this->paginatedResponse($guardians);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil daftar orang tua/wali', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-MST-04: Create Guardian
     * POST /api/v1/guardians
     */
    public function store(StoreGuardianRequest $request): JsonResponse
    {
        try {
            $guardian = $this->guardianService->createGuardian($request->validated(), $request);

            return $this->successResponse($guardian, 201);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                400 => 'BUSINESS_RULE_VIOLATION',
                409 => 'CONFLICT',
                default => 'INTERNAL_ERROR',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal menambahkan orang tua/wali baru', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-MST-04: Show Guardian Detail
     * GET /api/v1/guardians/{id}
     */
    public function show(Request $request, string $id): JsonResponse
    {
        try {
            $guardian = $this->guardianService->getGuardian($id, $request);

            return $this->successResponse($guardian);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Data orang tua/wali tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                403 => 'FORBIDDEN',
                404 => 'NOT_FOUND',
                default => 'INTERNAL_ERROR',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil data orang tua/wali', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-MST-04: Update Guardian
     * PUT /api/v1/guardians/{id}
     */
    public function update(UpdateGuardianRequest $request, string $id): JsonResponse
    {
        try {
            $guardian = $this->guardianService->updateGuardian($id, $request->validated(), $request);

            return $this->successResponse($guardian);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Data orang tua/wali tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                400 => 'BUSINESS_RULE_VIOLATION',
                409 => 'CONFLICT',
                default => 'INTERNAL_ERROR',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal memperbarui data orang tua/wali', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-MST-04: Delete Guardian (Soft delete)
     * DELETE /api/v1/guardians/{id}
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $this->guardianService->deleteGuardian($id, $request);

            return $this->successResponse(['message' => 'Data orang tua/wali berhasil dihapus']);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Data orang tua/wali tidak ditemukan', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal menghapus data orang tua/wali', 'INTERNAL_ERROR', 500);
        }
    }

    // =========================================================================
    // STUDENT - GUARDIAN RELATIONSHIP ENDPOINTS
    // =========================================================================

    /**
     * BE-MST-04: Get Guardians of a Student
     * GET /api/v1/students/{id}/guardians
     */
    public function studentGuardians(Request $request, string $id): JsonResponse
    {
        try {
            $guardians = $this->guardianService->listStudentGuardians($id, $request);

            return $this->successResponse($guardians);
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

            return $this->errorResponse('Gagal mengambil daftar wali siswa', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-MST-04: Attach Guardian to Student
     * POST /api/v1/students/{id}/guardians
     */
    public function attachStudentGuardian(AttachStudentGuardianRequest $request, string $id): JsonResponse
    {
        try {
            $this->guardianService->attachStudentGuardian($id, $request->validated(), $request);

            return $this->successResponse(['message' => 'Orang tua/wali berhasil ditautkan dengan siswa'], 201);
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

            return $this->errorResponse('Gagal menautkan orang tua/wali dengan siswa', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-MST-04: Update Student Guardian Relationship / Primary Status
     * PUT /api/v1/students/{id}/guardians/{guardianId}
     */
    public function updateStudentGuardian(UpdateStudentGuardianRequest $request, string $id, string $guardianId): JsonResponse
    {
        try {
            $this->guardianService->updateStudentGuardian($id, $guardianId, $request->validated(), $request);

            return $this->successResponse(['message' => 'Data relasi perwalian berhasil diperbarui']);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Siswa tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                404 => 'NOT_FOUND',
                409 => 'CONFLICT',
                default => 'INTERNAL_ERROR',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal memperbarui relasi perwalian siswa', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-MST-04: Detach Guardian from Student
     * DELETE /api/v1/students/{id}/guardians/{guardianId}
     */
    public function detachStudentGuardian(Request $request, string $id, string $guardianId): JsonResponse
    {
        try {
            $this->guardianService->detachStudentGuardian($id, $guardianId, $request);

            return $this->successResponse(['message' => 'Relasi orang tua/wali berhasil dilepas dari siswa']);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Siswa tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                404 => 'NOT_FOUND',
                default => 'INTERNAL_ERROR',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal melepas relasi perwalian siswa', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-MST-04: Provision or Reset Guardian Portal Account
     * POST /api/v1/guardians/{id}/account
     */
    public function provisionAccount(Request $request, string $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'username' => ['nullable', 'string', 'max:50'],
                'password' => ['nullable', 'string', 'min:6'],
                'email' => ['nullable', 'email', 'max:100'],
            ]);

            $guardian = $this->guardianService->createOrResetAccount($id, $validated, $request);

            return $this->successResponse([
                'message' => 'Akun login portal orang tua berhasil diaktifkan / diperbarui.',
                'guardian' => $guardian,
                'user' => [
                    'id' => $guardian->user?->id,
                    'username' => $guardian->user?->username,
                    'email' => $guardian->user?->email,
                ],
            ], 200);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Data orang tua/wali tidak ditemukan', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal membuat akun login orang tua: '.$e->getMessage(), 'INTERNAL_ERROR', 500);
        }
    }
}
