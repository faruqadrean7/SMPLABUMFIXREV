<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\TwoFactorAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class TwoFactorAuthController extends Controller
{
    public function __construct(
        protected TwoFactorAuthService $twoFactorService
    ) {}

    protected function resolveUser(Request $request): ?User
    {
        if ($user = $request->user('sanctum')) {
            return $user;
        }

        $enrollmentToken = $request->input('enrollment_token') ?? $request->header('X-Enrollment-Token');
        if ($enrollmentToken) {
            return $this->twoFactorService->validateEnrollmentToken((string) $enrollmentToken);
        }

        return null;
    }

    /**
     * Generate new secret and setup URL for 2FA.
     */
    public function setup(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);

        if (! $user) {
            return response()->json([
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'Sesi autentikasi atau token enrollment diperlukan untuk setup 2FA.',
                ],
            ], 401);
        }

        $secret = $this->twoFactorService->generateSecretKey();
        $user->two_factor_secret = $secret;
        $user->save();

        $otpauthUrl = $this->twoFactorService->getOtpAuthUrl(
            'SISTLABUM',
            $user->email ?: $user->username,
            $secret
        );

        return response()->json([
            'message' => 'Kunci 2FA berhasil dibuat.',
            'data' => [
                'secret' => $secret,
                'otpauth_url' => $otpauthUrl,
            ],
        ]);
    }

    /**
     * Verify initial TOTP code to confirm and activate 2FA.
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string',
            'enrollment_token' => 'nullable|string',
        ]);

        $user = $this->resolveUser($request);

        if (! $user) {
            return response()->json([
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'Sesi autentikasi atau token enrollment diperlukan untuk verifikasi 2FA.',
                ],
            ], 401);
        }

        if (! $user->two_factor_secret) {
            return response()->json([
                'error' => [
                    'code' => '2FA_NOT_INITIALIZED',
                    'message' => 'Silakan lakukan setup 2FA terlebih dahulu sebelum memverifikasi.',
                ],
            ], 400);
        }

        $isValid = $this->twoFactorService->verifyKeyWithUser($user, (string) $request->input('code'));

        if (! $isValid) {
            return response()->json([
                'error' => [
                    'code' => 'INVALID_2FA_CODE',
                    'message' => 'Kode autentikasi 2FA yang dimasukkan tidak valid.',
                ],
            ], 422);
        }

        // Generate 8 emergency recovery codes
        $plaintextRecoveryCodes = $this->twoFactorService->generateRecoveryCodes();
        // Store recovery codes as cryptographic hashes for one-time verification
        $hashedCodes = array_map(fn ($code) => Hash::make($code), $plaintextRecoveryCodes);

        $user->two_factor_confirmed_at = now();
        $user->two_factor_recovery_codes = $hashedCodes;
        $user->save();

        try {
            AuditLog::create([
                'actor_id' => $user->id,
                'action' => 'auth.2fa_enabled',
                'entity' => 'User',
                'entity_id' => $user->id,
                'old_value' => null,
                'new_value' => ['method' => 'totp_rfc6238', 'recovery_codes_count' => count($plaintextRecoveryCodes)],
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Throwable) {
            // Ignore audit log failure
        }

        return response()->json([
            'message' => 'Otentikasi dua faktor (2FA) berhasil diaktifkan.',
            'data' => [
                'confirmed' => true,
                'recovery_codes' => $plaintextRecoveryCodes,
            ],
        ]);
    }

    /**
     * Disable 2FA with current password confirmation.
     */
    public function disable(Request $request): JsonResponse
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        $user = $request->user();

        if (! $user) {
            return response()->json([
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'Sesi autentikasi diperlukan.',
                ],
            ], 401);
        }

        if (! Hash::check($request->input('password'), $user->password_hash)) {
            return response()->json([
                'error' => [
                    'code' => 'INVALID_PASSWORD',
                    'message' => 'Kata sandi saat ini tidak cocok.',
                ],
            ], 422);
        }

        $user->two_factor_secret = null;
        $user->two_factor_recovery_codes = null;
        $user->two_factor_confirmed_at = null;
        $user->two_factor_last_step = null;
        $user->save();

        try {
            AuditLog::create([
                'actor_id' => $user->id,
                'action' => 'auth.2fa_disabled',
                'entity' => 'User',
                'entity_id' => $user->id,
                'old_value' => null,
                'new_value' => ['action' => 'disabled_by_user'],
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Throwable) {
            // Ignore audit log failure
        }

        return response()->json([
            'message' => 'Otentikasi dua faktor (2FA) berhasil dinonaktifkan.',
        ]);
    }
}
