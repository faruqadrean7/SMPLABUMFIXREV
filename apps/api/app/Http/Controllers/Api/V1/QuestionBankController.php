<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cbt\StoreQuestionBankRequest;
use App\Services\QuestionBankService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class QuestionBankController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected QuestionBankService $service
    ) {}

    /**
     * BE-CBT-01: Daftar paket bank soal.
     * GET /api/v1/question-banks
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $paginator = $this->service->listQuestionBanks($request->all(), $request);

            return $this->paginatedResponse($paginator);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat daftar bank soal.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CBT-01: Simpan paket bank soal baru.
     * POST /api/v1/question-banks
     */
    public function store(StoreQuestionBankRequest $request): JsonResponse
    {
        try {
            $bank = $this->service->createQuestionBank($request->validated(), $request);

            return $this->successResponse($bank, 201);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat membuat bank soal.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CBT-01: Detail paket bank soal.
     * GET /api/v1/question-banks/{id}
     */
    public function show(string $id): JsonResponse
    {
        try {
            $bank = $this->service->getQuestionBank($id);

            return $this->successResponse($bank);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Bank soal tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat detail bank soal.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CBT-01: Perbarui paket bank soal.
     * PUT /api/v1/question-banks/{id}
     */
    public function update(string $id, StoreQuestionBankRequest $request): JsonResponse
    {
        try {
            $bank = $this->service->updateQuestionBank($id, $request->validated(), $request);

            return $this->successResponse($bank);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Bank soal tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memperbarui bank soal.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CBT-01: Hapus paket bank soal.
     * DELETE /api/v1/question-banks/{id}
     */
    public function destroy(string $id, Request $request): JsonResponse
    {
        try {
            $this->service->deleteQuestionBank($id, $request);

            return $this->successResponse(['message' => 'Bank soal berhasil dihapus.']);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Bank soal tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'BUSINESS_RULE_VIOLATION', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat menghapus bank soal.', 'INTERNAL_ERROR', 500);
        }
    }
}
