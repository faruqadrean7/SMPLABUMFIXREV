<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ppdb\CheckNikRequest;
use App\Http\Requests\Ppdb\RegisterApplicantRequest;
use App\Http\Requests\Ppdb\ResendOtpRequest;
use App\Http\Requests\Ppdb\VerifyOtpRequest;
use App\Models\User;
use App\Services\PPDBAccountService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class PPDBAccountController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected PPDBAccountService $service
    ) {}

    /**
     * BE-PPDB-02: Register applicant account & send OTP.
     * POST /api/v1/ppdb/register
     */
    public function register(RegisterApplicantRequest $request): JsonResponse
    {
        try {
            $data = $this->service->register($request->validated(), $request);

            return $this->successResponse($data, 201);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                400 => 'VALIDATION_FAILED',
                409 => 'CONFLICT',
                429 => 'RATE_LIMITED',
                default => 'BUSINESS_RULE_VIOLATION',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan pada sistem saat registrasi calon siswa', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-PPDB-02: Verify OTP and activate account.
     * POST /api/v1/ppdb/verify-otp
     */
    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $data = $this->service->verifyOtp($validated['email'], $validated['otp'], $request);

            return $this->successResponse($data);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                400 => 'INVALID_OTP',
                404 => 'NOT_FOUND',
                default => 'BUSINESS_RULE_VIOLATION',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan pada sistem saat verifikasi OTP', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-PPDB-02: Resend OTP code.
     * POST /api/v1/ppdb/resend-otp
     */
    public function resendOtp(ResendOtpRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $data = $this->service->resendOtp($validated['email'], $request);

            return $this->successResponse($data);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                400 => 'VALIDATION_FAILED',
                404 => 'NOT_FOUND',
                429 => 'RATE_LIMITED',
                default => 'BUSINESS_RULE_VIOLATION',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan pada sistem saat mengirim ulang OTP', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-PPDB-02: Check 1 NIK = 1 active registration.
     * POST /api/v1/ppdb/check-nik
     */
    public function checkNik(CheckNikRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $data = $this->service->checkNikAvailability($validated['nik'], $validated['wave_id'] ?? null);

            return $this->successResponse($data);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'VALIDATION_FAILED', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan pada sistem saat pengecekan NIK', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-PPDB-02: Applicant Login via Email/Phone/RegistrationNo + Password.
     * POST /api/v1/ppdb/login
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'identifier' => 'required|string',
            'password' => 'required|string',
        ]);

        try {
            $data = $this->service->login($validated['identifier'], $validated['password'], $request);

            return $this->successResponse($data);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                401 => 'INVALID_CREDENTIALS',
                403 => 'ACCOUNT_INACTIVE',
                429 => 'RATE_LIMITED',
                default => 'BUSINESS_RULE_VIOLATION',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat masuk ke akun pendaftar', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-PPDB-02: Get currently authenticated applicant profile & applications.
     * GET /api/v1/ppdb/me
     */
    public function me(Request $request): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $request->user();
            $data = $this->service->getApplicantProfile($user);

            return $this->successResponse($data);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil data profil calon siswa', 'INTERNAL_ERROR', 500);
        }
    }
}
