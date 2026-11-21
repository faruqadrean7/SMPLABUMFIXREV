<?php

namespace App\Http\Controllers\Api\V1\Cbt;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cbt\DistributeParticipantsRequest;
use App\Http\Requests\Cbt\ListExamCardRequest;
use App\Services\Cbt\ExamTokenRoomService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class ExamParticipantCardController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ExamTokenRoomService $service
    ) {}

    /**
     * BE-CBT-03: Auto-distribusi peserta ujian ke ruang-ruang ujian.
     * POST /api/v1/exams/{id}/distribute-participants
     */
    public function distribute(string $id, DistributeParticipantsRequest $request): JsonResponse
    {
        try {
            $result = $this->service->distributeParticipants($id, $request->validated(), $request);

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

            return $this->errorResponse('Terjadi kesalahan saat mendistribusikan peserta ujian.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CBT-03: Daftar kartu ujian peserta.
     * GET /api/v1/exams/{id}/participant-cards
     */
    public function index(string $id, ListExamCardRequest $request): JsonResponse
    {
        try {
            $paginator = $this->service->listParticipantCards($id, $request->validated());

            return $this->paginatedResponse($paginator);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Data ujian tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat kartu ujian peserta.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CBT-03: Unduh PDF kartu ujian peserta.
     * GET /api/v1/exams/{id}/participants/{participantId}/card-pdf
     */
    public function downloadPdf(string $id, string $participantId, Request $request): Response|JsonResponse
    {
        try {
            return $this->service->downloadParticipantCardPdf($id, $participantId, $request);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Data kartu peserta ujian tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                401 => 'UNAUTHENTICATED',
                403 => 'FORBIDDEN',
                default => 'BAD_REQUEST',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat menghasilkan PDF kartu ujian.', 'INTERNAL_ERROR', 500);
        }
    }
}
