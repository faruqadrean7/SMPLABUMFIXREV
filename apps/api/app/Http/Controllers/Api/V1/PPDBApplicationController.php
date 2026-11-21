<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ppdb\StorePPDBApplicationRequest;
use App\Http\Requests\Ppdb\UpdatePPDBApplicationStepRequest;
use App\Services\PPDBApplicationService;
use App\Traits\ApiResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class PPDBApplicationController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected PPDBApplicationService $service
    ) {}

    /**
     * BE-PPDB-03: List applications.
     * GET /api/v1/ppdb/applications
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $applications = $this->service->listApplications($request);

            if ($applications instanceof LengthAwarePaginator) {
                return $this->paginatedResponse($applications);
            }

            return $this->successResponse($applications);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil daftar pendaftaran PPDB', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-PPDB-03: Start a new application draft.
     * POST /api/v1/ppdb/applications
     */
    public function store(StorePPDBApplicationRequest $request): JsonResponse
    {
        try {
            $application = $this->service->createApplication($request->validated(), $request);

            return $this->successResponse($application, 201);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                400 => 'VALIDATION_FAILED',
                403 => 'FORBIDDEN',
                409 => 'CONFLICT',
                default => 'BUSINESS_RULE_VIOLATION',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memulai pendaftaran PPDB', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-PPDB-03: Get application detail.
     * GET /api/v1/ppdb/applications/{id}
     */
    public function show(string $id, Request $request): JsonResponse
    {
        try {
            $application = $this->service->getApplication($id, $request);

            return $this->successResponse($this->service->presentDetail($application));
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Data pendaftaran tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                403 => 'FORBIDDEN',
                default => 'BUSINESS_RULE_VIOLATION',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal memuat detail pendaftaran', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-PPDB-03: Autosave specific form step.
     * PATCH /api/v1/ppdb/applications/{id}/steps/{step}
     */
    public function updateStep(string $id, string $step, UpdatePPDBApplicationStepRequest $request): JsonResponse
    {
        try {
            $application = $this->service->updateStep($id, $step, $request->validated(), $request);

            return $this->successResponse($application);
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

            return $this->errorResponse('Gagal menyimpan langkah formulir pendaftaran', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-PPDB-03: Submit application.
     * POST /api/v1/ppdb/applications/{id}/submit
     */
    public function submit(string $id, Request $request): JsonResponse
    {
        try {
            $application = $this->service->submitApplication($id, $request);

            return $this->successResponse($application);
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

            return $this->errorResponse('Gagal mengirimkan pendaftaran PPDB', 'INTERNAL_ERROR', 500);
        }
    }
}
