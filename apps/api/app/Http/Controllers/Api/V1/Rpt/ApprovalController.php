<?php

namespace App\Http\Controllers\Api\V1\Rpt;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rpt\ReviewApprovalRequest;
use App\Services\Rpt\ApprovalService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class ApprovalController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ApprovalService $service
    ) {}

    /**
     * ADM-DASH-02: Antrean pengajuan yang menunggu persetujuan eksekutif.
     * GET /api/v1/approvals/pending
     */
    public function pending(Request $request): JsonResponse
    {
        try {
            $items = $this->service->getPendingApprovals($request);

            return response()->json([
                'success' => true,
                'data' => $items,
            ]);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat antrean persetujuan.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * ADM-DASH-02: Tinjau dan putuskan pengajuan persetujuan eksekutif.
     * POST /api/v1/approvals/{id}/review
     */
    public function review(string $id, ReviewApprovalRequest $request): JsonResponse
    {
        try {
            $result = $this->service->reviewApproval($id, $request->validated(), $request);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'reviewedAt' => $result['reviewedAt'],
                'reviewed_at' => $result['reviewed_at'],
                'data' => $result,
            ]);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memproses keputusan persetujuan.', 'INTERNAL_ERROR', 500);
        }
    }
}
