<?php

namespace App\Http\Controllers\Api\V1\Std;

use App\Http\Controllers\Controller;
use App\Http\Requests\Std\ListAchievementRequest;
use App\Http\Requests\Std\StoreAchievementRequest;
use App\Http\Requests\Std\UpdateAchievementRequest;
use App\Http\Requests\Std\VerifyAchievementRequest;
use App\Services\Std\CompetitionAndAchievementService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class AchievementController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected CompetitionAndAchievementService $service
    ) {}

    /**
     * BE-STD-03: Galeri prestasi & piala siswa berpaginasi.
     * GET /api/v1/achievements
     */
    public function index(ListAchievementRequest $request): JsonResponse
    {
        try {
            $paginator = $this->service->listAchievements($request->validated(), $request->user());

            return $this->paginatedResponse($paginator);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat daftar prestasi.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-STD-03: Detail prestasi siswa beserta trofi/sertifikat.
     * GET /api/v1/achievements/{id}
     */
    public function show(string $id, Request $request): JsonResponse
    {
        try {
            $achievement = $this->service->getAchievement($id, $request->user());

            return $this->successResponse($achievement);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Data prestasi tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat detail prestasi.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-STD-03: Pencatatan prestasi & otomatisasi poin prestasi ke kedisiplinan.
     * POST /api/v1/achievements
     */
    public function store(StoreAchievementRequest $request): JsonResponse
    {
        try {
            $achievement = $this->service->createAchievement($request->validated(), $request);

            return $this->successResponse($achievement, 201);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Siswa atau kompetisi tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mencatat data prestasi.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-STD-03: Perbarui data prestasi.
     * PUT /api/v1/achievements/{id}
     */
    public function update(string $id, UpdateAchievementRequest $request): JsonResponse
    {
        try {
            $achievement = $this->service->updateAchievement($id, $request->validated(), $request);

            return $this->successResponse($achievement);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Data prestasi tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal memperbarui data prestasi.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-STD-03: Verifikasi bukti prestasi & piala oleh staf kesiswaan/admin.
     * PATCH /api/v1/achievements/{id}/verify
     */
    public function verify(string $id, VerifyAchievementRequest $request): JsonResponse
    {
        try {
            $achievement = $this->service->verifyAchievement($id, $request->validated(), $request);

            return $this->successResponse($achievement);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Data prestasi tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal memverifikasi data prestasi.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-STD-03: Hapus data prestasi.
     * DELETE /api/v1/achievements/{id}
     */
    public function destroy(string $id, Request $request): JsonResponse
    {
        try {
            $this->service->deleteAchievement($id, $request);

            return $this->successResponse(['message' => 'Data prestasi berhasil dihapus.']);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Data prestasi tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal menghapus data prestasi.', 'INTERNAL_ERROR', 500);
        }
    }
}
