<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\ListJournalRequest;
use App\Http\Requests\Academic\StoreJournalRequest;
use App\Http\Requests\Academic\UpdateJournalRequest;
use App\Services\AcademicJournalService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class JournalController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected AcademicJournalService $service
    ) {}

    /**
     * BE-ACD-02: List journals with filters.
     * GET /api/v1/journals
     */
    public function index(ListJournalRequest $request): JsonResponse
    {
        try {
            $paginator = $this->service->listJournals($request->validated(), $request);

            return $this->paginatedResponse($paginator);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat mengambil daftar jurnal mengajar', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-ACD-02: Create journal entry.
     * POST /api/v1/journals
     */
    public function store(StoreJournalRequest $request): JsonResponse
    {
        try {
            $journal = $this->service->createJournal($request->validated(), $request);

            return $this->successResponse($journal, 201);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                403 => 'FORBIDDEN',
                422 => 'UNPROCESSABLE_ENTITY',
                default => 'BUSINESS_RULE_VIOLATION',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat membuat jurnal mengajar', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-ACD-02: Show journal detail with schedule & attendances.
     * GET /api/v1/journals/{id}
     */
    public function show(string $id, Request $request): JsonResponse
    {
        try {
            $journal = $this->service->getJournal($id, $request);

            return $this->successResponse($journal);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Jurnal mengajar tidak ditemukan', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat mengambil detail jurnal mengajar', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-ACD-02: Update journal entry (limited by N days).
     * PUT /api/v1/journals/{id}
     */
    public function update(string $id, UpdateJournalRequest $request): JsonResponse
    {
        try {
            $journal = $this->service->updateJournal($id, $request->validated(), $request);

            return $this->successResponse($journal);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Jurnal mengajar tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                403 => 'FORBIDDEN',
                422 => 'UNPROCESSABLE_ENTITY',
                default => 'BUSINESS_RULE_VIOLATION',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memperbarui jurnal mengajar', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-ACD-02: Delete journal entry (limited by N days).
     * DELETE /api/v1/journals/{id}
     */
    public function destroy(string $id, Request $request): JsonResponse
    {
        try {
            $this->service->deleteJournal($id, $request);

            return $this->successResponse(null, 200);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Jurnal mengajar tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                403 => 'FORBIDDEN',
                422 => 'UNPROCESSABLE_ENTITY',
                default => 'BUSINESS_RULE_VIOLATION',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat menghapus jurnal mengajar', 'INTERNAL_ERROR', 500);
        }
    }
}
