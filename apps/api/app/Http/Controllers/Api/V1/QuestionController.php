<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cbt\ImportQuestionsRequest;
use App\Http\Requests\Cbt\ListQuestionRequest;
use App\Http\Requests\Cbt\StoreQuestionRequest;
use App\Http\Requests\Cbt\UpdateQuestionRequest;
use App\Services\QuestionBankService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class QuestionController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected QuestionBankService $service
    ) {}

    /**
     * BE-CBT-01: Daftar butir soal.
     * GET /api/v1/questions
     */
    public function index(ListQuestionRequest $request): JsonResponse
    {
        try {
            $paginator = $this->service->listQuestions($request->validated());

            return $this->paginatedResponse($paginator);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat daftar butir soal.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CBT-01: Simpan butir soal baru.
     * POST /api/v1/questions
     */
    public function store(StoreQuestionRequest $request): JsonResponse
    {
        try {
            $question = $this->service->createQuestion($request->validated(), $request);

            return $this->successResponse($question, 201);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat menyimpan butir soal.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CBT-01: Detail butir soal.
     * GET /api/v1/questions/{id}
     */
    public function show(string $id): JsonResponse
    {
        try {
            $question = $this->service->getQuestion($id);

            return $this->successResponse($question);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Butir soal tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat detail butir soal.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CBT-01: Perbarui butir soal (versi baru jika sudah dipakai ujian).
     * PUT /api/v1/questions/{id}
     */
    public function update(string $id, UpdateQuestionRequest $request): JsonResponse
    {
        try {
            $question = $this->service->updateQuestion($id, $request->validated(), $request);

            return $this->successResponse($question);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Butir soal tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memperbarui butir soal.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CBT-01: Hapus butir soal.
     * DELETE /api/v1/questions/{id}
     */
    public function destroy(string $id, Request $request): JsonResponse
    {
        try {
            $this->service->deleteQuestion($id, $request);

            return $this->successResponse(['message' => 'Butir soal berhasil dihapus.']);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Butir soal tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'BUSINESS_RULE_VIOLATION', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat menghapus butir soal.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CBT-01: Import massal butir soal ke bank soal.
     * POST /api/v1/questions/import
     */
    public function import(ImportQuestionsRequest $request): JsonResponse
    {
        try {
            $result = $this->service->importQuestions($request->validated(), $request);

            return $this->successResponse($result, 201);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Bank soal tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat mengimport butir soal: '.$e->getMessage(), 'INTERNAL_ERROR', 500);
        }
    }
}
