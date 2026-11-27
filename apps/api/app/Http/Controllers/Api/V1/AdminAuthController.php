<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ManagesRefreshCookies;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class AdminAuthController extends Controller
{
    use ManagesRefreshCookies;

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
        protected AuthService $authService
    ) {}

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'identifier' => 'required|string',
            'password' => 'required|string',
        ]);

        $ip = $request->ip();

        // Standard credentials verification with strict rate limiting
        $identifier = trim((string) $request->input('identifier'));
        $password = (string) $request->input('password');

        $rateLimitKey = 'admin_login_attempts:'.sha1(strtolower($identifier).'|'.$ip);
        $ipRateLimitKey = 'admin_login_ip_attempts:'.$ip;

        $maxIpAttempts = app()->environment('local', 'testing') ? 100 : 15;

        if (RateLimiter::tooManyAttempts($rateLimitKey, 5) || RateLimiter::tooManyAttempts($ipRateLimitKey, $maxIpAttempts)) {
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

        // 2FA dinonaktifkan (AGENTS.md §6.11): langsung terbitkan sesi & tulis 1 audit log otoritatif
        RateLimiter::clear($rateLimitKey);
        if (Hash::needsRehash($user->password_hash)) {
            $user->password_hash = Hash::make($password);
        }
        $user->last_login_at = now();
        $user->saveQuietly();

        $authData = $this->authService->issueTokensForUser($user, $request);
        [$payload, $plainRefreshToken] = $this->prepareAuthPayloadForTransport($authData, $request);

        return $this->attachRefreshCookie(
            response()->json([
                'message' => 'Login admin berhasil.',
                'data' => $payload,
            ], 200),
            $plainRefreshToken,
            $request
        );
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
