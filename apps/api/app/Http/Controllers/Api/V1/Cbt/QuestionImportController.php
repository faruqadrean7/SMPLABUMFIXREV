<?php

namespace App\Http\Controllers\Api\V1\Cbt;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cbt\StartQuestionImportRequest;
use App\Http\Requests\Cbt\UpdateImportDraftRequest;
use App\Services\Cbt\QuestionImportService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * BE-CBT-13: Controller untuk alur impor naskah soal dari berkas DOCX.
 */
class QuestionImportController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected QuestionImportService $service
    ) {}

    /**
     * Unduh template berkas DOCX resmi.
     * GET /api/v1/question-imports/template?format=docx
     */
    public function template(Request $request): BinaryFileResponse
    {
        $filePath = $this->service->generateTemplateDocx();

        return response()->download($filePath, 'template_impor_soal_cbt.docx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Unggah berkas DOCX dan mulai antrean proses parsing.
     * POST /api/v1/question-imports
     */
    public function store(StartQuestionImportRequest $request): JsonResponse
    {
        try {
            $file = $request->file('file');
            $bankId = $request->input('bank_id');

            $job = $this->service->startImport($file, $bankId, $request->user(), $request);

            return $this->successResponse($job, 201);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                400 => 'VALIDATION_FAILED',
                403 => 'FORBIDDEN',
                404 => 'NOT_FOUND',
                default => 'BUSINESS_RULE_VIOLATION',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memulai proses impor soal: '.$e->getMessage(), 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * Lihat status dan pratinjau draf soal hasil parse.
     * GET /api/v1/question-imports/{id}
     */
    public function show(string $id, Request $request): JsonResponse
    {
        try {
            $job = $this->service->getImportJob($id, $request->user());

            return $this->successResponse($job);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Job impor soal tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat pratinjau draf impor.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * Koreksi butir-butir draf soal sebelum komit.
     * PATCH /api/v1/question-imports/{id}
     */
    public function update(string $id, UpdateImportDraftRequest $request): JsonResponse
    {
        try {
            $job = $this->service->updateDraft($id, $request->input('questions'), $request->user());

            return $this->successResponse($job);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Job impor soal tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                403 => 'FORBIDDEN',
                default => 'BUSINESS_RULE_VIOLATION',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal memperbarui draf impor soal.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * Konfirmasi komit draf soal ke bank soal resmi.
     * POST /api/v1/question-imports/{id}/commit
     */
    public function commit(string $id, Request $request): JsonResponse
    {
        try {
            $idempotencyKey = $request->header('Idempotency-Key');
            $result = $this->service->commitImport($id, $idempotencyKey, $request->user(), $request);

            return $this->successResponse($result, 200);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Job impor soal tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                403 => 'FORBIDDEN',
                default => 'BUSINESS_RULE_VIOLATION',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat mengomit butir soal ke bank soal: '.$e->getMessage(), 'INTERNAL_ERROR', 500);
        }
    }
}
