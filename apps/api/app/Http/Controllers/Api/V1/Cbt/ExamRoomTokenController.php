<?php

namespace App\Http\Controllers\Api\V1\Cbt;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cbt\RegenerateExamTokenRequest;
use App\Services\Cbt\ExamTokenRoomService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class ExamRoomTokenController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ExamTokenRoomService $service
    ) {}

    /**
     * BE-CBT-03: Regenerasi token ujian untuk ruang dan sesi tertentu.
     * POST /api/v1/exam-rooms/{id}/regenerate-token
     */
    public function regenerate(string $id, RegenerateExamTokenRequest $request): JsonResponse
    {
        try {
            $room = $this->service->regenerateToken($id, $request->validated(), $request);

            return $this->successResponse($room, 200);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Data ruang ujian tidak ditemukan.', 'NOT_FOUND', 404);
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

            return $this->errorResponse('Terjadi kesalahan saat membuat token ruang ujian.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CBT-03: Cek status token ujian saat ini (aktif vs kedaluwarsa).
     * GET /api/v1/exam-rooms/{id}/token-status
     */
    public function status(string $id, Request $request): JsonResponse
    {
        try {
            $status = $this->service->getTokenStatus($id, $request);

            return $this->successResponse($status);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Data ruang ujian tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                401 => 'UNAUTHENTICATED',
                403 => 'FORBIDDEN',
                default => 'BAD_REQUEST',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memeriksa status token.', 'INTERNAL_ERROR', 500);
        }
    }
}
