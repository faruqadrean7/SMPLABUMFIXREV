<?php

namespace App\Http\Controllers\Api\V1\Storage;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storage\ConfirmFileRequest;
use App\Http\Requests\Storage\PresignFileRequest;
use App\Services\Storage\FileStorageService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class FileController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected FileStorageService $fileStorageService
    ) {}

    /**
     * BE-FILE-01: Presign Upload URL.
     */
    public function presign(PresignFileRequest $request): JsonResponse
    {
        try {
            $result = $this->fileStorageService->presignUpload(
                $request->validated(),
                $request->user()
            );

            return $this->successResponse($result);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (\Throwable $e) {
            return $this->errorResponse('Terjadi kesalahan saat memproses presign berkas.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-FILE-01: Direct upload via signed presign session.
     */
    public function uploadSigned(string $token, Request $request): JsonResponse
    {
        try {
            $result = $this->fileStorageService->uploadSigned($token, $request);

            return $this->successResponse($result);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (\Throwable $e) {
            return $this->errorResponse('Terjadi kesalahan saat mengunggah berkas.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-FILE-01: Konfirmasi Keberhasilan Upload Berkas.
     */
    public function confirm(ConfirmFileRequest $request): JsonResponse
    {
        try {
            $file = $this->fileStorageService->confirmUpload(
                $request->validated(),
                $request->user()
            );

            return $this->successResponse($file, 201);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Berkas tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (\Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat mengonfirmasi berkas.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-FILE-01: Ambil URL Berkas (Signed URL jika privat, URL publik jika publik).
     */
    public function url(string $id, Request $request): JsonResponse
    {
        try {
            $ttl = (int) $request->query('ttl', 300);
            $result = $this->fileStorageService->getFileUrl($id, $request->user(), $ttl);

            return $this->successResponse($result);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Berkas tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (\Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat mengambil tautan berkas.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-FILE-01: Download berkas via Signed URL.
     */
    public function downloadSigned(string $id, Request $request): StreamedResponse
    {
        return $this->fileStorageService->downloadSigned($id, $request);
    }
}
