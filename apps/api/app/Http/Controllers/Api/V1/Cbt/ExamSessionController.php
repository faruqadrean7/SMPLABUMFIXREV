<?php

namespace App\Http\Controllers\Api\V1\Cbt;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cbt\HeartbeatRequest;
use App\Http\Requests\Cbt\LogExamViolationRequest;
use App\Http\Requests\Cbt\MonitorExamRequest;
use App\Http\Requests\Cbt\SaveExamAnswerRequest;
use App\Http\Requests\Cbt\StartExamSessionRequest;
use App\Http\Requests\Cbt\UnlockExamSessionRequest;
use App\Services\Cbt\ExamSessionService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class ExamSessionController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ExamSessionService $service
    ) {}

    /**
     * BE-CBT-04: Siswa mulai / lanjutkan sesi ujian dengan token.
     * POST /api/v1/exams/{id}/start-session
     */
    public function start(string $id, StartExamSessionRequest $request): JsonResponse
    {
        try {
            $session = $this->service->startOrResumeSession($id, $request->validated(), $request);

            return $this->successResponse($session, 200);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Data ujian tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $code = $e->getHeaders()['X-Error-Code'] ?? match ($e->getStatusCode()) {
                401 => 'UNAUTHENTICATED',
                403 => 'FORBIDDEN',
                422 => 'BUSINESS_RULE_VIOLATION',
                default => 'BAD_REQUEST',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memulai sesi ujian.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CBT-04: Ambil daftar butir soal ujian siswa (aman tanpa kunci jawaban).
     * GET /api/v1/exams/{id}/session-questions
     */
    public function questions(string $id, Request $request): JsonResponse
    {
        try {
            $questions = $this->service->getSessionQuestions($id, $request);

            return $this->successResponse($questions, 200);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Sesi ujian tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $code = $e->getHeaders()['X-Error-Code'] ?? match ($e->getStatusCode()) {
                401 => 'UNAUTHENTICATED',
                403 => 'FORBIDDEN',
                422 => 'BUSINESS_RULE_VIOLATION',
                default => 'BAD_REQUEST',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat butir soal ujian.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CBT-04: Autosave jawaban siswa per butir soal.
     * POST /api/v1/exams/{id}/save-answer
     */
    public function saveAnswer(string $id, SaveExamAnswerRequest $request): JsonResponse
    {
        try {
            $result = $this->service->saveAnswer($id, $request->validated(), $request);

            return $this->successResponse($result, 200);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Sesi ujian tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $code = $e->getHeaders()['X-Error-Code'] ?? match ($e->getStatusCode()) {
                401 => 'UNAUTHENTICATED',
                403 => 'FORBIDDEN',
                422 => 'BUSINESS_RULE_VIOLATION',
                default => 'BAD_REQUEST',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat menyimpan jawaban.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CBT-04: Log kejadian anti-cheat pelanggaran layar.
     * POST /api/v1/exams/{id}/log-violation
     */
    public function logViolation(string $id, LogExamViolationRequest $request): JsonResponse
    {
        try {
            $result = $this->service->logViolation($id, $request->validated(), $request);

            return $this->successResponse($result, 200);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Sesi ujian tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $code = $e->getHeaders()['X-Error-Code'] ?? 'BAD_REQUEST';

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat mencatat pelanggaran.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CBT-04: Submit akhir / selesaikan sesi ujian.
     * POST /api/v1/exams/{id}/finish-session
     */
    public function finish(string $id, Request $request): JsonResponse
    {
        try {
            $result = $this->service->finishSession($id, $request);

            return $this->successResponse($result, 200);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Sesi ujian tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $code = $e->getHeaders()['X-Error-Code'] ?? match ($e->getStatusCode()) {
                401 => 'UNAUTHENTICATED',
                403 => 'FORBIDDEN',
                422 => 'BUSINESS_RULE_VIOLATION',
                default => 'BAD_REQUEST',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat menyelesaikan ujian.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CBT-04: Pengawas atau admin membuka kunci sesi dan/atau perpanjang waktu.
     * POST /api/v1/exam-sessions/{sessionId}/manage
     */
    public function manage(string $sessionId, UnlockExamSessionRequest $request): JsonResponse
    {
        try {
            $result = $this->service->unlockOrManageSession($sessionId, $request->validated(), $request);

            return $this->successResponse($result, 200);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Sesi ujian tidak ditemukan.', 'NOT_FOUND', 404);
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

            return $this->errorResponse('Terjadi kesalahan saat mengelola sesi ujian.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CBT-06: Monitoring status seluruh peserta ujian secara real-time.
     * GET /api/v1/exams/{id}/monitor
     */
    public function monitor(string $id, MonitorExamRequest $request): JsonResponse
    {
        try {
            $data = $this->service->getLiveMonitoring($id, $request->validated(), $request);

            return $this->successResponse($data, 200);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Ujian CBT tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                401 => 'UNAUTHENTICATED',
                403 => 'FORBIDDEN',
                default => 'BAD_REQUEST',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat monitoring ujian.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CBT-12: Heartbeat status pengerjaan peserta dan deteksi anomali.
     * POST /api/v1/exam-sessions/{sessionId}/heartbeat
     */
    public function heartbeat(string $sessionId, HeartbeatRequest $request): JsonResponse
    {
        try {
            $result = $this->service->recordHeartbeat($sessionId, $request->validated(), $request);

            return $this->successResponse($result, 200);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Sesi ujian tidak ditemukan.', 'NOT_FOUND', 404);
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

            return $this->errorResponse('Terjadi kesalahan saat mencatat heartbeat sesi ujian.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CBT-12: Daftar anomali sesi ujian untuk ditinjau pengawas.
     * GET /api/v1/exams/{id}/anomalies
     */
    public function anomalies(string $id, Request $request): JsonResponse
    {
        try {
            $filters = $request->validate([
                'type' => ['nullable', 'string'],
            ]);

            $result = $this->service->getExamAnomalies($id, $filters, $request);

            return $this->successResponse($result, 200);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Ujian CBT tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                401 => 'UNAUTHENTICATED',
                403 => 'FORBIDDEN',
                default => 'BAD_REQUEST',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat daftar anomali ujian.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CBT-04 / GAP-B: Siswa melihat hasil dan pembahasan mandiri ujian.
     * GET /api/v1/exams/{id}/review
     */
    public function review(string $id, Request $request): JsonResponse
    {
        try {
            $result = $this->service->getExamReview($id, $request);

            return $this->successResponse($result, 200);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Data ujian tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                401 => 'UNAUTHENTICATED',
                403 => 'FORBIDDEN',
                404 => 'NOT_FOUND',
                422 => 'BUSINESS_RULE_VIOLATION',
                default => 'BAD_REQUEST',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat pembahasan ujian.', 'INTERNAL_ERROR', 500);
        }
    }
}
