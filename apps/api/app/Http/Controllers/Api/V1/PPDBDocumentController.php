<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ppdb\UploadApplicantDocumentRequest;
use App\Services\PPDBDocumentService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class PPDBDocumentController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected PPDBDocumentService $service
    ) {}

    /**
     * BE-PPDB-04: List documents for applicant.
     * GET /api/v1/ppdb/applications/{id}/documents
     */
    public function index(string $id, Request $request): JsonResponse
    {
        try {
            $documents = $this->service->listDocuments($id, $request);

            return $this->successResponse($documents);
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

            return $this->errorResponse('Gagal mengambil daftar berkas pendaftaran', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-PPDB-04: Upload document.
     * POST /api/v1/ppdb/applications/{id}/documents
     */
    public function store(string $id, UploadApplicantDocumentRequest $request): JsonResponse
    {
        try {
            $document = $this->service->uploadDocument($id, $request->validated(), $request);

            return $this->successResponse($document, 201);
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

            return $this->errorResponse('Terjadi kesalahan saat mengunggah berkas dokumen', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-PPDB-04: Delete document.
     * DELETE /api/v1/ppdb/applications/{id}/documents/{documentId}
     */
    public function destroy(string $id, string $documentId, Request $request): JsonResponse
    {
        try {
            $this->service->deleteDocument($id, $documentId, $request);

            return $this->successResponse(['message' => 'Berkas pendaftaran berhasil dihapus']);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Data berkas pendaftaran tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                400 => 'VALIDATION_FAILED',
                403 => 'FORBIDDEN',
                default => 'BUSINESS_RULE_VIOLATION',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal menghapus berkas pendaftaran', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-PPDB-04: Download/view private document.
     * GET /api/v1/ppdb/applications/{id}/documents/{documentId}/download
     */
    public function download(string $id, string $documentId, Request $request): BinaryFileResponse|JsonResponse
    {
        try {
            return $this->service->downloadDocument($id, $documentId, $request);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Data berkas pendaftaran tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                403 => 'FORBIDDEN',
                404 => 'NOT_FOUND',
                default => 'BUSINESS_RULE_VIOLATION',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengunduh berkas pendaftaran', 'INTERNAL_ERROR', 500);
        }
    }
}
