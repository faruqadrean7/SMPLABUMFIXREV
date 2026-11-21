<?php

namespace App\Http\Controllers\Api\V1\Bk;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bk\StoreCounselingCaseRequest;
use App\Http\Requests\Bk\StoreCounselingSessionRequest;
use App\Http\Requests\Bk\UpdateCounselingCaseRequest;
use App\Services\Bk\CounselingService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class CounselingCaseController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected CounselingService $service
    ) {}

    /**
     * BE-BK-01: Daftar kasus konseling.
     * GET /api/v1/counseling/cases
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $paginator = $this->service->listCases($request->all(), $request);

            return $this->paginatedResponse($paginator);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat daftar kasus konseling.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-BK-01: Buat kasus konseling baru.
     * POST /api/v1/counseling/cases
     */
    public function store(StoreCounselingCaseRequest $request): JsonResponse
    {
        try {
            $case = $this->service->createCase($request->validated(), $request);

            return $this->successResponse($case, 201);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat menyimpan kasus konseling.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-BK-01: Detail kasus konseling (dengan audit baca).
     * GET /api/v1/counseling/cases/{id}
     */
    public function show(string $id, Request $request): JsonResponse
    {
        try {
            $case = $this->service->getCase($id, $request);

            return $this->successResponse($case, 200);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Data kasus konseling tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat data kasus konseling.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-BK-01: Update kasus konseling.
     * PUT /api/v1/counseling/cases/{id}
     */
    public function update(string $id, UpdateCounselingCaseRequest $request): JsonResponse
    {
        try {
            $case = $this->service->updateCase($id, $request->validated(), $request);

            return $this->successResponse($case, 200);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Data kasus konseling tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memperbarui kasus konseling.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-BK-01: Tambah sesi konseling ke dalam kasus.
     * POST /api/v1/counseling/cases/{id}/sessions
     */
    public function storeSession(string $id, StoreCounselingSessionRequest $request): JsonResponse
    {
        try {
            $session = $this->service->createSession($id, $request->validated(), $request);

            return $this->successResponse($session, 201);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Data kasus konseling tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat menyimpan sesi konseling.', 'INTERNAL_ERROR', 500);
        }
    }
}
