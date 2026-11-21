<?php

namespace App\Http\Controllers\Api\V1\Bk;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bk\StoreCounselingReferralRequest;
use App\Http\Requests\Bk\UpdateCounselingReferralStatusRequest;
use App\Services\Bk\CounselingService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class CounselingReferralController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected CounselingService $service
    ) {}

    /**
     * BE-BK-01: Daftar rujukan konseling.
     * GET /api/v1/counseling/referrals
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $paginator = $this->service->listReferrals($request->all(), $request);

            return $this->paginatedResponse($paginator);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat daftar rujukan konseling.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-BK-01: Buat rujukan konseling dari guru ke BK.
     * POST /api/v1/counseling/referrals
     */
    public function store(StoreCounselingReferralRequest $request): JsonResponse
    {
        try {
            $referral = $this->service->createReferral($request->validated(), $request);

            return $this->successResponse($referral, 201);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat membuat rujukan konseling.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-BK-01: Perbarui status rujukan konseling oleh BK.
     * PATCH /api/v1/counseling/referrals/{id}/status
     */
    public function updateStatus(string $id, UpdateCounselingReferralStatusRequest $request): JsonResponse
    {
        try {
            $referral = $this->service->updateReferralStatus($id, $request->validated(), $request);

            return $this->successResponse($referral, 200);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Data rujukan konseling tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memperbarui status rujukan.', 'INTERNAL_ERROR', 500);
        }
    }
}
