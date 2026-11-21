<?php

namespace App\Http\Controllers\Api\V1\Cbt;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cbt\CreateRemedialExamRequest;
use App\Http\Requests\Cbt\GradeEssayAnswerRequest;
use App\Http\Requests\Cbt\PublishExamToGradebookRequest;
use App\Http\Requests\Cbt\RunAutoGradingRequest;
use App\Services\Cbt\ExamGradingService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class ExamGradingController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ExamGradingService $service
    ) {}

    /**
     * BE-CBT-05: Jalankan koreksi otomatis untuk butir objektif.
     * POST /api/v1/exams/{id}/auto-grade
     */
    public function autoGrade(string $id, RunAutoGradingRequest $request): JsonResponse
    {
        try {
            $result = $this->service->autoGradeExam($id, $request->validated(), $request);

            return $this->successResponse($result, 200);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Data ujian tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                401 => 'UNAUTHENTICATED',
                403 => 'FORBIDDEN',
                422 => 'BUSINESS_RULE_VIOLATION',
                default => 'BAD_REQUEST',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat menjalankan koreksi otomatis.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CBT-05: Ambil lembar jawaban soal uraian/esai yang perlu dinilai manual.
     * GET /api/v1/exams/{id}/essay-submissions
     */
    public function essaySubmissions(string $id, Request $request): JsonResponse
    {
        try {
            $result = $this->service->getEssaySubmissions($id, $request->all(), $request);

            return $this->successResponse($result, 200);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Data ujian tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat lembar jawaban uraian.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CBT-05: Input skor manual soal uraian oleh guru.
     * POST /api/v1/exams/{id}/grade-essay
     */
    public function gradeEssay(string $id, GradeEssayAnswerRequest $request): JsonResponse
    {
        try {
            $result = $this->service->gradeEssayAnswer($id, $request->validated(), $request);

            return $this->successResponse($result, 200);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Data jawaban atau ujian tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                401 => 'UNAUTHENTICATED',
                403 => 'FORBIDDEN',
                422 => 'BUSINESS_RULE_VIOLATION',
                default => 'BAD_REQUEST',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat menyimpan penilaian soal uraian.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CBT-05: Rekapitulasi hasil nilai akhir ujian seluruh peserta.
     * GET /api/v1/exams/{id}/results
     */
    public function results(string $id, Request $request): JsonResponse
    {
        try {
            $result = $this->service->getExamResults($id, $request->all(), $request);

            return $this->successResponse($result, 200);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Data ujian tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat rekapitulasi hasil ujian.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CBT-05: Rekapitulasi Analisis Butir Soal (Item Analysis).
     * GET /api/v1/exams/{id}/item-analysis
     */
    public function itemAnalysis(string $id, Request $request): JsonResponse
    {
        try {
            $result = $this->service->getItemAnalysis($id, $request);

            return $this->successResponse($result, 200);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Data ujian tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat analisis butir soal.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CBT-09: Buat Ujian Remedial Otomatis untuk Siswa di Bawah KKTP.
     * POST /api/v1/exams/{id}/remedial
     */
    public function createRemedial(string $id, CreateRemedialExamRequest $request): JsonResponse
    {
        try {
            $remedialExam = $this->service->createRemedialExam($id, $request->validated(), $request);

            return $this->successResponse($remedialExam, 201);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Ujian CBT induk tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                401 => 'UNAUTHENTICATED',
                403 => 'FORBIDDEN',
                422 => 'BUSINESS_RULE_VIOLATION',
                default => 'BAD_REQUEST',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat membuat ujian remedial.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CBT-05 & ADM-GRD-01: Publikasikan rekapitulasi nilai ujian CBT ke Buku Nilai.
     * POST /api/v1/exams/{id}/publish-gradebook
     */
    public function publishGradebook(string $id, PublishExamToGradebookRequest $request): JsonResponse
    {
        try {
            $result = $this->service->publishToGradebook($id, $request->validated(), $request);

            return $this->successResponse($result, 200);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Data ujian atau komponen nilai tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                401 => 'UNAUTHENTICATED',
                403 => 'FORBIDDEN',
                422 => 'BUSINESS_RULE_VIOLATION',
                default => 'BAD_REQUEST',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat mempublikasikan nilai ujian ke Buku Nilai.', 'INTERNAL_ERROR', 500);
        }
    }
}
