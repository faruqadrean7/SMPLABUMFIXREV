<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ppdb\VerifyApplicationRequest;
use App\Http\Requests\Ppdb\VerifyDocumentRequest;
use App\Services\PPDBVerificationService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class PPDBVerificationController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected PPDBVerificationService $service
    ) {}

    /**
     * BE-PPDB-05: Verify individual document.
     * PATCH /api/v1/ppdb/documents/{id}/verify
     */
    public function verifyDocument(string $id, VerifyDocumentRequest $request): JsonResponse
    {
        try {
            $document = $this->service->verifyDocument($id, $request->validated(), $request);

            return $this->successResponse($document);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Berkas dokumen tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                400 => 'VALIDATION_FAILED',
                403 => 'FORBIDDEN',
                default => 'BUSINESS_RULE_VIOLATION',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memverifikasi berkas dokumen', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-PPDB-05: Verify application.
     * PATCH /api/v1/ppdb/applications/{id}/verify
     */
    public function verifyApplication(string $id, VerifyApplicationRequest $request): JsonResponse
    {
        try {
            $application = $this->service->verifyApplication($id, $request->validated(), $request);

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

            return $this->errorResponse('Terjadi kesalahan saat memverifikasi status pendaftaran', 'INTERNAL_ERROR', 500);
        }
    }
}
