<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Complaint\ConfirmComplaintAttachmentRequest;
use App\Http\Requests\Complaint\PresignComplaintAttachmentRequest;
use App\Services\ComplaintAttachmentService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class ComplaintAttachmentController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ComplaintAttachmentService $service
    ) {}

    /**
     * BE-GUEST-04: Presign URL unggah lampiran foto pengaduan publik.
     * POST /api/v1/public/complaints/attachments/presign
     */
    public function presign(PresignComplaintAttachmentRequest $request): JsonResponse
    {
        try {
            $result = $this->service->presignAttachment($request->validated(), $request);

            return $this->successResponse($result, 200);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                400 => 'VALIDATION_FAILED',
                422 => 'UNPROCESSABLE_ENTITY',
                default => 'HTTP_ERROR',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memproses presign lampiran.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-GUEST-04: Konfirmasi unggahan lampiran foto pengaduan publik.
     * POST /api/v1/public/complaints/attachments/{token}/confirm
     * POST /api/v1/public/complaints/attachments/confirm
     */
    public function confirm(ConfirmComplaintAttachmentRequest $request, ?string $token = null): JsonResponse
    {
        try {
            $effectiveToken = $token ?: $request->input('token');

            if (empty($effectiveToken)) {
                return $this->errorResponse('Token presign wajib disertakan.', 'VALIDATION_FAILED', 400);
            }

            $file = $this->service->confirmAttachment($effectiveToken, $request);

            return $this->successResponse([
                'file_id' => $file->id,
                'mime_type' => $file->mime_type,
                'size_bytes' => $file->size_bytes,
                'message' => 'Lampiran foto berhasil diproses dan dibersihkan dari metadata.',
            ], 201);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                400 => 'VALIDATION_FAILED',
                422 => 'UNPROCESSABLE_ENTITY',
                default => 'HTTP_ERROR',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat mengonfirmasi lampiran.', 'INTERNAL_ERROR', 500);
        }
    }
}
