<?php

namespace App\Http\Controllers\Api\V1\Std;

use App\Http\Controllers\Controller;
use App\Http\Requests\Std\ListCompetitionRequest;
use App\Http\Requests\Std\StoreCompetitionEntryRequest;
use App\Http\Requests\Std\StoreCompetitionRequest;
use App\Http\Requests\Std\UpdateCompetitionEntryRequest;
use App\Http\Requests\Std\UpdateCompetitionRequest;
use App\Services\Std\CompetitionAndAchievementService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class CompetitionController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected CompetitionAndAchievementService $service
    ) {}

    /**
     * BE-STD-03: Ringkasan statistik lomba & prestasi.
     * GET /api/v1/competitions/summary
     */
    public function summary(Request $request): JsonResponse
    {
        try {
            $summary = $this->service->getSummary($request->user());

            return $this->successResponse($summary);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat ringkasan kompetisi.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-STD-03: Daftar kompetisi/lomba.
     * GET /api/v1/competitions
     */
    public function index(ListCompetitionRequest $request): JsonResponse
    {
        try {
            $paginator = $this->service->listCompetitions($request->validated(), $request->user());

            return $this->paginatedResponse($paginator);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat daftar kompetisi.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-STD-03: Detail kompetisi beserta delegasi & prestasi.
     * GET /api/v1/competitions/{id}
     */
    public function show(string $id, Request $request): JsonResponse
    {
        try {
            $competition = $this->service->getCompetition($id, $request->user());

            return $this->successResponse($competition);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Kompetisi tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat detail kompetisi.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-STD-03: Tambah kompetisi/lomba baru.
     * POST /api/v1/competitions
     */
    public function store(StoreCompetitionRequest $request): JsonResponse
    {
        try {
            $competition = $this->service->createCompetition($request->validated(), $request);

            return $this->successResponse($competition, 201);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal menambahkan kompetisi baru.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-STD-03: Perbarui data kompetisi.
     * PUT /api/v1/competitions/{id}
     */
    public function update(string $id, UpdateCompetitionRequest $request): JsonResponse
    {
        try {
            $competition = $this->service->updateCompetition($id, $request->validated(), $request);

            return $this->successResponse($competition);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Kompetisi tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal memperbarui data kompetisi.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-STD-03: Hapus data kompetisi.
     * DELETE /api/v1/competitions/{id}
     */
    public function destroy(string $id, Request $request): JsonResponse
    {
        try {
            $this->service->deleteCompetition($id, $request);

            return $this->successResponse(['message' => 'Kompetisi berhasil dihapus.']);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Kompetisi tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal menghapus kompetisi.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-STD-03: Daftar delegasi peserta kompetisi.
     * GET /api/v1/competitions/{id}/entries
     */
    public function indexEntries(string $id, Request $request): JsonResponse
    {
        try {
            $paginator = $this->service->listEntries($id, $request->user());

            return $this->paginatedResponse($paginator);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat daftar delegasi kompetisi.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-STD-03: Daftarkan siswa delegasi peserta lomba.
     * POST /api/v1/competitions/{id}/entries
     */
    public function storeEntry(string $id, StoreCompetitionEntryRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $data['competition_id'] = $id;

            $entry = $this->service->registerEntry($data, $request);

            return $this->successResponse($entry, 201);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Kompetisi atau data siswa tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mendaftarkan delegasi kompetisi.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-STD-03: Perbarui data/hasil delegasi kompetisi.
     * PUT /api/v1/competition-entries/{id}
     */
    public function updateEntry(string $id, UpdateCompetitionEntryRequest $request): JsonResponse
    {
        try {
            $entry = $this->service->updateEntry($id, $request->validated(), $request);

            return $this->successResponse($entry);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Data delegasi tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal memperbarui data delegasi.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-STD-03: Hapus data delegasi kompetisi.
     * DELETE /api/v1/competition-entries/{id}
     */
    public function destroyEntry(string $id, Request $request): JsonResponse
    {
        try {
            $this->service->deleteEntry($id, $request);

            return $this->successResponse(['message' => 'Delegasi kompetisi berhasil dihapus.']);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Data delegasi tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal menghapus data delegasi.', 'INTERNAL_ERROR', 500);
        }
    }
}
