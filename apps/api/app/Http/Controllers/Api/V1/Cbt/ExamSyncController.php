<?php

namespace App\Http\Controllers\Api\V1\Cbt;

use App\Http\Controllers\Controller;
use App\Services\Cbt\ExamSyncService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class ExamSyncController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ExamSyncService $service
    ) {}

    /**
     * BE-CBT-11: Ekspor Paket Ujian untuk Server Lokal Sekolah.
     * POST /api/v1/cbt/sync/pull
     */
    public function pull(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'exam_id' => ['required', 'uuid'],
            'node_id' => ['required', 'string', 'max:100'],
            'sync_token' => ['nullable', 'string', 'max:100'],
            'room_id' => ['nullable', 'uuid'],
        ]);

        try {
            $data = $this->service->pullPackage($validated, $request);

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

            return $this->errorResponse('Terjadi kesalahan saat mengekspor paket sinkronisasi ujian.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CBT-11: Sinkronisasi Hasil Pengerjaan Offline ke Server Pusat.
     * POST /api/v1/cbt/sync/push
     */
    public function push(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'exam_id' => ['required', 'uuid'],
            'node_id' => ['required', 'string', 'max:100'],
            'sync_token' => ['nullable', 'string', 'max:100'],
            'sessions' => ['required', 'array', 'min:1'],
            'sessions.*.student_id' => ['required', 'uuid'],
            'sessions.*.started_at' => ['nullable', 'date'],
            'sessions.*.submitted_at' => ['nullable', 'date'],
            'sessions.*.answers' => ['nullable', 'array'],
            'sessions.*.answers.*.question_id' => ['required', 'uuid'],
            'sessions.*.answers.*.answer' => ['nullable'],
            'sessions.*.violations' => ['nullable', 'array'],
        ]);

        try {
            $result = $this->service->pushResults($validated, $request);

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

            return $this->errorResponse('Terjadi kesalahan saat memproses sinkronisasi hasil ujian.', 'INTERNAL_ERROR', 500);
        }
    }
}
