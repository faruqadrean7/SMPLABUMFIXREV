<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuthService;
use App\Services\TwoFactorAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class AdminAuthController extends Controller
{
    /**
     * Roles that are permitted to authenticate via admin back-office portal.
     * Menggunakan sumber kebenaran tunggal dari User::ADMIN_SHELL_ROLES.
     */
    public const ADMIN_ROLES = User::ADMIN_SHELL_ROLES;

    /**
     * High-privilege roles where 2FA / TOTP is strictly mandatory before accessing admin shell.
     * Menggunakan sumber kebenaran tunggal dari User::MANDATORY_2FA_ROLES.
     */
    public const MANDATORY_2FA_ROLES = User::MANDATORY_2FA_ROLES;

    public function __construct(
        protected AuthService $authService,
        protected TwoFactorAuthService $twoFactorService
    ) {}

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'identifier' => 'required_without:mfa_token|string',
            'password' => 'required_without:mfa_token|string',
            'totp_code' => 'nullable|string',
            'recovery_code' => 'nullable|string',
            'mfa_token' => 'nullable|string',
        ]);

        $ip = $request->ip();

        // 1. If MFA token is provided, verify 2FA step directly
        if ($request->filled('mfa_token')) {
            $user = $this->twoFactorService->validateMfaToken($request->input('mfa_token'));
            if (! $user) {
                return response()->json([
                    'error' => [
                        'code' => 'INVALID_MFA_TOKEN',
                        'message' => 'Token sesi 2FA telah kedaluwarsa atau tidak valid. Silakan masuk kembali.',
                    ],
                ], 401);
            }

            return $this->verifyTwoFactorAndLogin($user, $request);
        }

        // 2. Standard credentials verification with strict rate limiting
        $identifier = trim((string) $request->input('identifier'));
        $password = (string) $request->input('password');

        $rateLimitKey = 'admin_login_attempts:'.sha1(strtolower($identifier).'|'.$ip);
        $ipRateLimitKey = 'admin_login_ip_attempts:'.$ip;

        if (RateLimiter::tooManyAttempts($rateLimitKey, 5) || RateLimiter::tooManyAttempts($ipRateLimitKey, 15)) {
            return response()->json([
                'error' => [
                    'code' => 'RATE_LIMIT_EXCEEDED',
                    'message' => 'Terlalu banyak percobaan masuk. Silakan coba lagi nanti.',
                ],
            ], 429);
        }

        $user = $this->authService->findUserByIdentifier($identifier);

        // Uniform generic error response to avoid user or role enumeration
        $genericAuthError = response()->json([
            'error' => [
                'code' => 'INVALID_CREDENTIALS',
                'message' => 'Kredensial yang diberikan tidak cocok dengan data kami.',
            ],
        ], 401);

        if (! $user || ! Hash::check($password, $user->password_hash)) {
            RateLimiter::hit($rateLimitKey, 15 * 60);
            RateLimiter::hit($ipRateLimitKey, 15 * 60);

            $this->logAudit(
                $user?->id,
                'auth.admin_login_failed',
                'User',
                $user?->id,
                ['identifier' => $identifier, 'reason' => 'invalid_credentials', 'ip' => $ip],
                $request
            );

            return $genericAuthError;
        }

        // Check if user has at least one administrative or staff back-office role
        $userRoleCodes = $user->roles->pluck('code')->all();
        $hasAdminRole = ! empty(array_intersect($userRoleCodes, self::ADMIN_ROLES));

        if (! $hasAdminRole) {
            RateLimiter::hit($rateLimitKey, 15 * 60);
            RateLimiter::hit($ipRateLimitKey, 15 * 60);

            $this->logAudit(
                $user->id,
                'auth.admin_login_failed',
                'User',
                $user->id,
                ['identifier' => $identifier, 'reason' => 'forbidden_role_for_admin_shell', 'ip' => $ip],
                $request
            );

            return $genericAuthError; // Do not disclose that student/guardian/teacher account exists
        }

        // Check active status
        if ($user->status !== 'active') {
            return response()->json([
                'error' => [
                    'code' => 'ACCOUNT_INACTIVE',
                    'message' => 'Akun Anda tidak aktif. Silakan hubungi administrator.',
                ],
            ], 403);
        }

        // Check mandatory 2FA enrollment for all admin shell roles
        $isMandatory2Fa = ! empty(array_intersect($userRoleCodes, self::MANDATORY_2FA_ROLES));
        if ($isMandatory2Fa && $user->two_factor_confirmed_at === null) {
            RateLimiter::clear($rateLimitKey);
            $enrollmentToken = $this->twoFactorService->generateEnrollmentToken($user);

            return response()->json([
                'data' => [
                    'mfa_enrollment_required' => true,
                    'enrollment_token' => $enrollmentToken,
                ],
                'mfa_enrollment_required' => true,
                'enrollment_token' => $enrollmentToken,
                'message' => 'Otentikasi dua faktor wajib diaktifkan untuk peran administrator/staf sebelum dapat mengakses sistem.',
            ], 200);
        }

        // Check 2FA challenge
        if ($user->two_factor_confirmed_at !== null) {
            // Check if user provided totp_code or recovery_code in same request
            if ($request->filled('totp_code') || $request->filled('recovery_code')) {
                return $this->verifyTwoFactorAndLogin($user, $request);
            }

            // Return MFA challenge
            $mfaToken = $this->twoFactorService->generateMfaToken($user);

            return response()->json([
                'data' => [
                    'mfa_required' => true,
                    'mfa_token' => $mfaToken,
                ],
                'mfa_required' => true,
                'mfa_token' => $mfaToken,
                'message' => 'Otentikasi dua faktor diperlukan. Masukkan kode autentikasi 6 digit dari aplikasi authenticator Anda.',
            ], 200);
        }

        // 2FA not enabled (allowed only for non-mandatory admin roles): complete login
        RateLimiter::clear($rateLimitKey);
        $user->last_login_at = now();
        $user->saveQuietly();

        $this->logAudit(
            $user->id,
            'auth.admin_login',
            'User',
            $user->id,
            ['identifier' => $identifier, 'method' => 'password_direct'],
            $request
        );

        $authData = $this->authService->issueTokensForUser($user, $request);

        return response()->json([
            'message' => 'Login admin berhasil.',
            'data' => $authData,
        ], 200);
    }

    protected function verifyTwoFactorAndLogin(User $user, Request $request): JsonResponse
    {
        $rateLimitKey = 'admin_2fa_attempts:'.$user->id;
        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            return response()->json([
                'error' => [
                    'code' => 'RATE_LIMIT_EXCEEDED',
                    'message' => 'Terlalu banyak percobaan kode 2FA yang gagal. Akun dikunci sementara selama 15 menit.',
                ],
            ], 429);
        }

        $totpCode = $request->input('totp_code');
        $recoveryCode = $request->input('recovery_code');

        $isValid = false;
        $isRecoveryUsed = false;

        if ($totpCode && $user->two_factor_secret) {
            $isValid = $this->twoFactorService->verifyKeyWithUser($user, (string) $totpCode);
        } elseif ($recoveryCode) {
            $isValid = $this->twoFactorService->verifyAndConsumeRecoveryCode($user, (string) $recoveryCode);
            if ($isValid) {
                $isRecoveryUsed = true;
            }
        }

        if (! $isValid) {
            RateLimiter::hit($rateLimitKey, 15 * 60);

            $this->logAudit(
                $user->id,
                'auth.admin_2fa_failed',
                'User',
                $user->id,
                ['reason' => 'invalid_or_replayed_code'],
                $request
            );

            return response()->json([
                'error' => [
                    'code' => 'INVALID_2FA_CODE',
                    'message' => 'Kode autentikasi dua faktor atau kode pemulihan tidak valid.',
                ],
            ], 401);
        }

        RateLimiter::clear($rateLimitKey);
        $user->last_login_at = now();
        $user->saveQuietly();

        if ($isRecoveryUsed) {
            $this->logAudit(
                $user->id,
                'auth.2fa_recovery_code_used',
                'User',
                $user->id,
                ['remaining_codes_count' => count($user->two_factor_recovery_codes ?? [])],
                $request
            );
        }

        $this->logAudit(
            $user->id,
            'auth.admin_login',
            'User',
            $user->id,
            ['method' => $isRecoveryUsed ? 'recovery_code' : 'totp'],
            $request
        );

        $authData = $this->authService->issueTokensForUser($user, $request);

        return response()->json([
            'message' => 'Verifikasi 2FA berhasil.',
            'data' => $authData,
        ], 200);
    }

    protected function logAudit(?string $actorId, string $action, string $entity, ?string $entityId, ?array $details, Request $request): void
    {
        try {
            AuditLog::create([
                'actor_id' => $actorId,
                'action' => $action,
                'entity' => $entity,
                'entity_id' => $entityId,
                'old_value' => null,
                'new_value' => $details,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Throwable) {
            // Silently ignore logging failures to not disrupt auth flow
        }
    }
}
