<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RefreshTokenRequest;
use App\Http\Requests\Auth\RegisterDeviceRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Services\AuthService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class AuthController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected AuthService $authService
    ) {}

    /**
     * BE-AUTH-01: Login & Token Issuance
     * POST /api/v1/auth/login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $data = $this->authService->login($request->validated(), $request);

            return $this->successResponse($data);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                401 => 'UNAUTHENTICATED',
                403 => 'FORBIDDEN',
                429 => 'RATE_LIMITED',
                default => 'BUSINESS_RULE_VIOLATION',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan pada sistem saat proses login', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-AUTH-01: Refresh Token & Rotation
     * POST /api/v1/auth/refresh
     */
    public function refresh(RefreshTokenRequest $request): JsonResponse
    {
        $refreshToken = $request->input('refreshToken') ?? $request->cookie('refreshToken');

        if (! $refreshToken) {
            return $this->errorResponse(
                'Refresh token wajib disertakan',
                'VALIDATION_FAILED',
                400,
                [['field' => 'refreshToken', 'message' => 'Refresh token wajib disertakan']]
            );
        }

        try {
            $data = $this->authService->refresh($refreshToken, $request);

            return $this->successResponse($data);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                401 => 'UNAUTHENTICATED',
                403 => 'FORBIDDEN',
                default => 'BUSINESS_RULE_VIOLATION',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memperbarui sesi', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-AUTH-01: Logout & Revocation
     * POST /api/v1/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $refreshToken = $request->input('refreshToken') ?? $request->cookie('refreshToken');
            $this->authService->logout($request->user(), $refreshToken, $request);

            return $this->successResponse(['message' => 'Berhasil keluar dari sistem']);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal melakukan proses logout', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-AUTH-01: Current Authenticated User Profile & Roles/Permissions
     * GET /api/v1/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $data = $this->authService->formatUserResponse($user);

            return $this->successResponse(['user' => $data]);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil data profil pengguna', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-AUTH-02: Forgot Password Request (Email/Link/OTP)
     * POST /api/v1/auth/forgot-password
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        try {
            $identifier = $request->validated('identifier') ?? $request->validated('email');
            $data = $this->authService->forgotPassword($identifier, $request);

            return $this->successResponse($data);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal memproses permohonan pemulihan kata sandi', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-AUTH-02: Reset Password with 15-min Token
     * POST /api/v1/auth/reset-password
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        try {
            $this->authService->resetPassword(
                $request->validated('token'),
                $request->validated('password'),
                $request
            );

            return $this->successResponse([
                'message' => 'Kata sandi berhasil diatur ulang. Silakan masuk kembali dengan kata sandi baru Anda.',
            ]);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                400 => 'BUSINESS_RULE_VIOLATION',
                404 => 'NOT_FOUND',
                default => 'INTERNAL_ERROR',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengatur ulang kata sandi', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-AUTH-02: Change Password for Authenticated User
     * POST /api/v1/auth/change-password
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        try {
            $this->authService->changePassword(
                $request->user(),
                $request->validated('current_password'),
                $request->validated('new_password'),
                $request
            );

            return $this->successResponse([
                'message' => 'Kata sandi berhasil diperbarui.',
            ]);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                400 => 'VALIDATION_FAILED',
                default => 'INTERNAL_ERROR',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal memperbarui kata sandi', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-AUTH-04: Get Active Sessions for Current User
     * GET /api/v1/auth/sessions
     */
    public function sessions(Request $request): JsonResponse
    {
        try {
            $sessions = $this->authService->getActiveSessions($request->user());

            return $this->successResponse($sessions);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil daftar sesi perangkat', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-AUTH-04: Revoke a Specific Active Session
     * DELETE /api/v1/auth/sessions/{id}
     */
    public function revokeSession(Request $request, string $id): JsonResponse
    {
        try {
            $this->authService->revokeSession($request->user(), $id, $request);

            return $this->successResponse(['message' => 'Sesi perangkat berhasil dicabut']);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'NOT_FOUND', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mencabut sesi perangkat', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-AUTH-04: Register or Update Device Token (FCM)
     * POST /api/v1/devices
     */
    public function registerDevice(RegisterDeviceRequest $request): JsonResponse
    {
        try {
            $device = $this->authService->registerDevice($request->user(), $request->validated(), $request);

            return $this->successResponse([
                'message' => 'Perangkat berhasil didaftarkan',
                'device' => [
                    'id' => $device->id,
                    'platform' => $device->platform,
                    'lastSeenAt' => $device->last_seen_at?->toIso8601String(),
                ],
            ], 201);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mendaftarkan perangkat', 'INTERNAL_ERROR', 500);
        }
    }
}
