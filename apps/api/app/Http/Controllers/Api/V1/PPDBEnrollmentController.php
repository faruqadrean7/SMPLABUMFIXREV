<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ppdb\ConfirmReRegistrationRequest;
use App\Http\Requests\Ppdb\EnrollApplicantRequest;
use App\Services\PPDBEnrollmentService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class PPDBEnrollmentController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected PPDBEnrollmentService $service
    ) {}

    /**
     * BE-PPDB-08: Confirm candidate re-registration.
     * POST /api/v1/ppdb/applications/{id}/re-register
     */
    public function reRegister(string $id, ConfirmReRegistrationRequest $request): JsonResponse
    {
        try {
            $result = $this->service->confirmReRegistration($id, $request->validated(), $request);

            return $this->successResponse($result);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Data pendaftaran tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                400 => 'VALIDATION_FAILED',
                403 => 'FORBIDDEN',
                422 => 'UNPROCESSABLE_ENTITY',
                default => 'BUSINESS_RULE_VIOLATION',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memproses konfirmasi daftar ulang', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-PPDB-08: Validate and convert applicant into student (Idempotent).
     * POST /api/v1/ppdb/applications/{id}/enroll
     */
    public function enroll(string $id, EnrollApplicantRequest $request): JsonResponse
    {
        try {
            $result = $this->service->enrollApplicant($id, $request->validated(), $request);

            return $this->successResponse($result);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Data pendaftaran tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                400 => 'VALIDATION_FAILED',
                403 => 'FORBIDDEN',
                422 => 'UNPROCESSABLE_ENTITY',
                default => 'BUSINESS_RULE_VIOLATION',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat melakukan konversi pendaftar ke siswa', 'INTERNAL_ERROR', 500);
        }
    }
}
