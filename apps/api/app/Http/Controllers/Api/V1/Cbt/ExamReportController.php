<?php

namespace App\Http\Controllers\Api\V1\Cbt;

use App\Http\Controllers\Controller;
use App\Services\Cbt\ExamReportService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class ExamReportController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ExamReportService $service
    ) {}

    /**
     * BE-CBT-10: Rekapitulasi Lengkap Pelaksanaan & Statistik Hasil Ujian.
     * GET /api/v1/exams/{id}/report
     */
    public function report(string $id, Request $request): JsonResponse
    {
        try {
            $filters = $request->validate([
                'room_id' => ['nullable', 'uuid'],
            ]);

            $data = $this->service->getExamReport($id, $filters, $request);

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

            return $this->errorResponse('Terjadi kesalahan saat memuat rekapitulasi ujian.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CBT-10: Cetak Dokumen Berita Acara Pelaksanaan Ujian (PDF/HTML Stream).
     * GET /api/v1/exams/{id}/minutes/pdf
     */
    public function minutesPdf(string $id, Request $request): Response|JsonResponse
    {
        try {
            $filters = $request->validate([
                'room_id' => ['nullable', 'uuid'],
                'format' => ['nullable', 'string', 'in:html,pdf,json'],
            ]);

            $result = $this->service->generateMinutesDocument($id, $filters, $request);

            if (is_array($result)) {
                return $this->successResponse($result, 200);
            }

            return $result;
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

            return $this->errorResponse('Terjadi kesalahan saat mencetak berita acara ujian.', 'INTERNAL_ERROR', 500);
        }
    }
}
