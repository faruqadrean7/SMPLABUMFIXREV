<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ppdb\OverrideDecisionRequest;
use App\Http\Requests\Ppdb\RankApplicantsRequest;
use App\Services\PPDBSelectionService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class PPDBSelectionController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected PPDBSelectionService $service
    ) {}

    /**
     * BE-PPDB-06: Automatically rank applicants in wave.
     * POST /api/v1/ppdb/waves/{id}/rank
     */
    public function rank(string $id, RankApplicantsRequest $request): JsonResponse
    {
        try {
            $data = $this->service->rankWaveApplicants($id, $request->validated(), $request);

            return $this->successResponse($data);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Gelombang PPDB tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                400 => 'VALIDATION_FAILED',
                403 => 'FORBIDDEN',
                default => 'BUSINESS_RULE_VIOLATION',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memproses perankingan seleksi PPDB', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-PPDB-06: Get ranked applicants for wave.
     * GET /api/v1/ppdb/waves/{id}/rankings
     */
    public function rankings(string $id, Request $request): JsonResponse
    {
        try {
            $data = $this->service->getRankings($id, $request);

            return $this->successResponse($data);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Gelombang PPDB tidak ditemukan', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil daftar peringkat seleksi', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-PPDB-06: Override decision for individual applicant.
     * PATCH /api/v1/ppdb/applications/{id}/decision
     */
    public function overrideDecision(string $id, OverrideDecisionRequest $request): JsonResponse
    {
        try {
            $applicant = $this->service->overrideDecision($id, $request->validated(), $request);

            return $this->successResponse($applicant);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Data pendaftaran tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                400 => 'VALIDATION_FAILED',
                403 => 'FORBIDDEN',
                default => 'BUSINESS_RULE_VIOLATION',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal menyesuaikan keputusan seleksi pendaftar', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-PPDB-06: Publish selection results for wave.
     * POST /api/v1/ppdb/waves/{id}/publish-results
     */
    public function publishResults(string $id, Request $request): JsonResponse
    {
        try {
            $data = $this->service->publishResults($id, $request);

            return $this->successResponse($data);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Gelombang PPDB tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                400 => 'VALIDATION_FAILED',
                403 => 'FORBIDDEN',
                default => 'BUSINESS_RULE_VIOLATION',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat mempublikasikan hasil seleksi PPDB', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-PPDB-06 / Decision #3: Promote waitlist candidates after deadline expiration with committee approval.
     * POST /api/v1/ppdb/waves/{id}/escalate-waitlist
     */
    public function escalateWaitlist(string $id, Request $request): JsonResponse
    {
        try {
            $data = $this->service->escalateWaitlist($id, $request->all(), $request);

            return $this->successResponse($data);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Gelombang PPDB tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                400 => 'VALIDATION_FAILED',
                403 => 'FORBIDDEN',
                default => 'BUSINESS_RULE_VIOLATION',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memproses eskalasi promosi cadangan', 'INTERNAL_ERROR', 500);
        }
    }
}
