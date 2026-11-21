<?php

namespace App\Http\Controllers\Api\V1\Bk;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bk\CompleteCounselingRequestRequest;
use App\Http\Requests\Bk\ListCounselingRequestRequest;
use App\Http\Requests\Bk\RejectCounselingRequestRequest;
use App\Http\Requests\Bk\ScheduleCounselingRequestRequest;
use App\Http\Requests\Bk\StoreCounselingRequestRequest;
use App\Services\Bk\CounselingRequestService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class CounselingRequestController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected CounselingRequestService $service
    ) {}

    /**
     * BE-BK-03: Daftar permintaan konseling.
     * GET /api/v1/counseling/requests
     */
    public function index(ListCounselingRequestRequest $request): JsonResponse
    {
        try {
            $paginator = $this->service->listRequests($request->validated(), $request);

            return $this->paginatedResponse($paginator);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat daftar permintaan konseling.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-BK-03: Buat permintaan konseling baru.
     * POST /api/v1/counseling/requests
     */
    public function store(StoreCounselingRequestRequest $request): JsonResponse
    {
        try {
            $counselingRequest = $this->service->createRequest($request->validated(), $request);

            return $this->successResponse($counselingRequest, 201);
        } catch (HttpException $e) {
            $code = $e->getStatusCode() === 422 ? 'VALIDATION_ERROR' : 'FORBIDDEN';

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat menyimpan permintaan konseling.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-BK-03: Detail permintaan konseling.
     * GET /api/v1/counseling/requests/{id}
     */
    public function show(string $id, Request $request): JsonResponse
    {
        try {
            $counselingRequest = $this->service->getRequest($id, $request);

            return $this->successResponse($counselingRequest);
        } catch (ModelNotFoundException) {
            return $this->notFoundResponse('Permintaan konseling tidak ditemukan.');
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat detail permintaan konseling.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-BK-03: Guru BK menjadwalkan permintaan konseling.
     * PATCH /api/v1/counseling/requests/{id}/schedule
     */
    public function schedule(string $id, ScheduleCounselingRequestRequest $request): JsonResponse
    {
        try {
            $counselingRequest = $this->service->scheduleRequest($id, $request->validated(), $request);

            return $this->successResponse($counselingRequest);
        } catch (ModelNotFoundException) {
            return $this->notFoundResponse('Permintaan konseling tidak ditemukan.');
        } catch (HttpException $e) {
            $code = $e->getStatusCode() === 422 ? 'VALIDATION_ERROR' : 'FORBIDDEN';

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat menjadwalkan permintaan konseling.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-BK-03: Guru BK menolak permintaan konseling.
     * PATCH /api/v1/counseling/requests/{id}/reject
     */
    public function reject(string $id, RejectCounselingRequestRequest $request): JsonResponse
    {
        try {
            $counselingRequest = $this->service->rejectRequest($id, $request->validated(), $request);

            return $this->successResponse($counselingRequest);
        } catch (ModelNotFoundException) {
            return $this->notFoundResponse('Permintaan konseling tidak ditemukan.');
        } catch (HttpException $e) {
            $code = $e->getStatusCode() === 422 ? 'VALIDATION_ERROR' : 'FORBIDDEN';

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat menolak permintaan konseling.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-BK-03: Guru BK menyelesaikan permintaan konseling dan opsional mengintegrasikannya ke Kasus BK.
     * PATCH /api/v1/counseling/requests/{id}/complete
     */
    public function complete(string $id, CompleteCounselingRequestRequest $request): JsonResponse
    {
        try {
            $counselingRequest = $this->service->completeRequest($id, $request->validated(), $request);

            return $this->successResponse($counselingRequest);
        } catch (ModelNotFoundException) {
            return $this->notFoundResponse('Permintaan konseling tidak ditemukan.');
        } catch (HttpException $e) {
            $code = $e->getStatusCode() === 422 ? 'VALIDATION_ERROR' : 'FORBIDDEN';

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat menyelesaikan permintaan konseling.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-BK-03: Batalkan permintaan konseling.
     * DELETE /api/v1/counseling/requests/{id}
     */
    public function cancel(string $id, Request $request): JsonResponse
    {
        try {
            $counselingRequest = $this->service->cancelRequest($id, $request);

            return $this->successResponse($counselingRequest);
        } catch (ModelNotFoundException) {
            return $this->notFoundResponse('Permintaan konseling tidak ditemukan.');
        } catch (HttpException $e) {
            $code = $e->getStatusCode() === 422 ? 'VALIDATION_ERROR' : 'FORBIDDEN';

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat membatalkan permintaan konseling.', 'INTERNAL_ERROR', 500);
        }
    }
}
