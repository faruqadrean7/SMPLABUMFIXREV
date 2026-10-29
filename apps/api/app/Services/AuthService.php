<?php

namespace App\Services;

use App\Http\Controllers\Api\V1\AdminAuthController;
use App\Models\Applicant;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\PasswordReset;
use App\Models\RefreshToken;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AuthService
{
    /**
     * Authenticate user with identifier (username, email, NIS, NIP) and password.
     * Enforces rate limiting (max 5 failed attempts -> 15 min lock).
     */
    public function login(array $credentials, Request $request): array
    {
        $identifier = trim($credentials['identifier']);
        $password = $credentials['password'];
        $platform = $credentials['platform'] ?? 'web';
        $fcmToken = $credentials['fcm_token'] ?? null;

        $rateLimitKey = 'login_attempts:'.sha1(strtolower($identifier).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            $minutes = ceil($seconds / 60);

            throw new HttpException(
                429,
                "Terlalu banyak percobaan login yang gagal. Akun dikunci sementara selama {$minutes} menit.",
                null,
                [],
                429
            );
        }

        $user = $this->findUserByIdentifier($identifier);

        if (! $user || ! Hash::check($password, $user->password_hash)) {
            RateLimiter::hit($rateLimitKey, 15 * 60); // lock window 15 min

            if ($user) {
                $this->logAudit($user->id, 'auth.login_failed', 'User', $user->id, null, [
                    'identifier' => $identifier,
                    'reason' => 'invalid_password',
                ], $request);
            }

            // Uniform message: do not leak whether account exists
            throw new HttpException(
                401,
                'Kredensial yang diberikan tidak cocok dengan data kami.',
                null,
                [],
                401
            );
        }

        // Account status check
        if ($user->status !== 'active') {
            throw new HttpException(
                403,
                "Akun Anda tidak aktif (status: {$user->status}). Silakan hubungi pihak sekolah.",
                null,
                [],
                403
            );
        }

        // BATCH 4B (GAP-SEC-02): Akun dengan akses shell admin DITOLAK di endpoint portal civitas
        $userRoleCodes = $user->roles->pluck('code')->all();
        $hasAdminShellRole = ! empty(array_intersect($userRoleCodes, AdminAuthController::ADMIN_ROLES));

        if ($hasAdminShellRole) {
            $this->logAudit($user->id, 'auth.login_failed', 'User', $user->id, null, [
                'identifier' => $identifier,
                'reason' => 'admin_account_prohibited_on_civitas_portal',
            ], $request);

            throw new HttpException(
                401,
                'Kredensial yang diberikan tidak cocok dengan data kami.',
                null,
                [],
                401
            );
        }

        // Login success: clear rate limiter
        RateLimiter::clear($rateLimitKey);

        // Transparently rehash legacy passwords (e.g. Bcrypt -> Argon2id)
        if (Hash::needsRehash($user->password_hash)) {
            $user->password_hash = Hash::make($password);
        }

        // Update last login
        $user->last_login_at = now();
        $user->saveQuietly();

        // Handle device registration if needed
        $device = null;
        if ($fcmToken || in_array($platform, ['android', 'ios'], true)) {
            $device = Device::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'platform' => $platform,
                ],
                [
                    'fcm_token' => $fcmToken,
                    'last_seen_at' => now(),
                ]
            );
        }

        // Issue tokens
        $tokens = $this->generateTokenPair($user, $device?->id);

        $this->logAudit($user->id, 'auth.login', 'User', $user->id, null, [
            'identifier' => $identifier,
            'platform' => $platform,
        ], $request);

        return [
            'user' => $this->formatUserResponse($user),
            'tokens' => $tokens,
        ];
    }

    /**
     * Rotate refresh token and issue a new access token.
     * Detects reuse of previously rotated tokens -> revokes all sessions!
     */
    public function refresh(string $plainRefreshToken, Request $request): array
    {
        $tokenHash = hash('sha256', $plainRefreshToken);

        $refreshToken = RefreshToken::with('user.roles.permissions')
            ->where('token_hash', $tokenHash)
            ->first();

        if (! $refreshToken) {
            throw new HttpException(401, 'Sesi tidak valid atau refresh token tidak ditemukan.', null, [], 401);
        }

        // Reuse detection!
        if ($refreshToken->revoked_at !== null || $refreshToken->replaced_by !== null) {
            // Security Incident: Revoke all tokens for this user
            RefreshToken::where('user_id', $refreshToken->user_id)->update(['revoked_at' => now()]);
            $refreshToken->user?->tokens()->delete();

            $this->logAudit($refreshToken->user_id, 'auth.refresh_token_reuse_detected', 'RefreshToken', $refreshToken->id, null, [
                'token_id' => $refreshToken->id,
                'ip' => $request->ip(),
            ], $request);

            throw new HttpException(
                401,
                'Terdeteksi pemakaian ulang token yang telah kedaluwarsa/dirotasi. Semua sesi aktif telah dicabut demi keamanan.',
                null,
                [],
                401
            );
        }

        // Check if token has expired
        if ($refreshToken->expires_at->isPast()) {
            $refreshToken->update(['revoked_at' => now()]);
            throw new HttpException(401, 'Sesi telah kedaluwarsa. Silakan masuk kembali.', null, [], 401);
        }

        $user = $refreshToken->user;

        if (! $user || $user->status !== 'active') {
            throw new HttpException(403, 'Akun tidak aktif.', null, [], 403);
        }

        // Generate new token pair and rotate
        $newPlainRefreshToken = bin2hex(random_bytes(32));
        $newTokenHash = hash('sha256', $newPlainRefreshToken);

        $newRefreshToken = RefreshToken::create([
            'user_id' => $user->id,
            'device_id' => $refreshToken->device_id,
            'token_hash' => $newTokenHash,
            'expires_at' => now()->addDays(14),
        ]);

        // Invalidate old refresh token
        $refreshToken->update([
            'revoked_at' => now(),
            'replaced_by' => $newRefreshToken->id,
        ]);

        // Create new Sanctum access token (15 mins)
        $tokenResult = $user->createToken('access_token', ['*'], now()->addMinutes(15));

        return [
            'user' => $this->formatUserResponse($user),
            'tokens' => [
                'accessToken' => $tokenResult->plainTextToken,
                'tokenType' => 'Bearer',
                'expiresIn' => 900,
                'refreshToken' => $newPlainRefreshToken,
            ],
        ];
    }

    /**
     * Invalidate current access token and optional refresh token.
     */
    public function logout(User $user, ?string $plainRefreshToken, Request $request): void
    {
        // Delete current Sanctum access token
        $user->currentAccessToken()?->delete();

        // If refresh token provided, revoke it
        if ($plainRefreshToken) {
            $tokenHash = hash('sha256', $plainRefreshToken);
            RefreshToken::where('user_id', $user->id)
                ->where('token_hash', $tokenHash)
                ->update(['revoked_at' => now()]);
        }

        $this->logAudit($user->id, 'auth.logout', 'User', $user->id, null, null, $request);
    }

    /**
     * BE-AUTH-02: Generate password reset token valid for 15 minutes.
     * Uniform response to avoid user enumeration.
     */
    public function forgotPassword(string $identifier, Request $request): array
    {
        $user = $this->findUserByIdentifier($identifier);

        $plainToken = null;
        if ($user) {
            // SEC-DOS-01: Cek apakah sudah ada token aktif yang belum digunakan dan masih dalam cooldown (< 2 menit)
            $recentReset = PasswordReset::where('user_id', $user->id)
                ->whereNull('used_at')
                ->where('created_at', '>=', now()->subMinutes(2))
                ->where('expires_at', '>', now())
                ->first();

            if (! $recentReset) {
                // Invalidate previous unused password resets outside cooldown
                PasswordReset::where('user_id', $user->id)
                    ->whereNull('used_at')
                    ->delete();

                $plainToken = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $plainToken);

                PasswordReset::create([
                    'user_id' => $user->id,
                    'token_hash' => $tokenHash,
                    'expires_at' => now()->addMinutes(15),
                ]);

                $this->logAudit($user->id, 'auth.forgot_password_requested', 'User', $user->id, null, [
                    'identifier' => $identifier,
                ], $request);
            }
        }

        $result = [
            'message' => 'Jika akun dengan identitas tersebut terdaftar di sistem, instruksi pemulihan kata sandi telah dikirimkan.',
        ];

        // If local or testing environment, include resetToken in response to facilitate testing/integration
        if (app()->environment('local', 'testing') && $plainToken) {
            $result['resetToken'] = $plainToken;
        }

        return $result;
    }

    /**
     * BE-AUTH-02: Reset password using single-use 15-minute token.
     * Revokes all active sessions upon successful reset.
     */
    public function resetPassword(string $plainToken, string $newPassword, Request $request): void
    {
        $tokenHash = hash('sha256', $plainToken);

        $reset = PasswordReset::with('user')
            ->where('token_hash', $tokenHash)
            ->first();

        if (! $reset || $reset->used_at !== null) {
            throw new HttpException(400, 'Token pemulihan kata sandi tidak valid atau telah digunakan.', null, [], 400);
        }

        if ($reset->expires_at->isPast()) {
            throw new HttpException(400, 'Token pemulihan kata sandi telah kedaluwarsa. Silakan ajukan permohonan baru.', null, [], 400);
        }

        $user = $reset->user;
        if (! $user) {
            throw new HttpException(404, 'Pengguna tidak ditemukan.', null, [], 404);
        }

        // Update password and clear must_change_password flag
        $user->password_hash = Hash::make($newPassword);
        $user->must_change_password = false;
        $user->save();

        // Mark token as used
        $reset->update(['used_at' => now()]);

        // Invalidate all tokens & sessions
        $user->tokens()->delete();
        RefreshToken::where('user_id', $user->id)->update(['revoked_at' => now()]);

        $this->logAudit($user->id, 'auth.password_reset_success', 'User', $user->id, null, null, $request);
    }

    /**
     * BE-AUTH-02: Change password for currently authenticated user.
     * Verifies current password, ensures new password is min 8 chars and different.
     */
    public function changePassword(User $user, string $currentPassword, string $newPassword, Request $request): void
    {
        if (! Hash::check($currentPassword, $user->password_hash)) {
            throw new HttpException(400, 'Kata sandi saat ini tidak sesuai.', null, [], 400);
        }

        if (Hash::check($newPassword, $user->password_hash)) {
            throw new HttpException(400, 'Kata sandi baru tidak boleh sama dengan kata sandi saat ini.', null, [], 400);
        }

        $user->password_hash = Hash::make($newPassword);
        $user->must_change_password = false;
        $user->save();

        $this->logAudit($user->id, 'auth.password_changed', 'User', $user->id, null, null, $request);
    }

    /**
     * BE-AUTH-04: Get list of active sessions for current user.
     */
    public function getActiveSessions(User $user): array
    {
        $sessions = RefreshToken::with('device')
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->orderBy('created_at', 'desc')
            ->get();

        return $sessions->map(function ($session) {
            return [
                'id' => $session->id,
                'createdAt' => $session->created_at?->toIso8601String(),
                'expiresAt' => $session->expires_at?->toIso8601String(),
                'device' => $session->device ? [
                    'id' => $session->device->id,
                    'platform' => $session->device->platform,
                    'lastSeenAt' => $session->device->last_seen_at?->toIso8601String(),
                ] : null,
            ];
        })->all();
    }

    /**
     * BE-AUTH-04: Revoke a specific active session.
     */
    public function revokeSession(User $user, string $sessionId, Request $request): void
    {
        $session = RefreshToken::where('user_id', $user->id)
            ->where('id', $sessionId)
            ->first();

        if (! $session) {
            throw new HttpException(404, 'Sesi tidak ditemukan atau bukan milik Anda.', null, [], 404);
        }

        if ($session->revoked_at === null) {
            $session->update(['revoked_at' => now()]);
        }

        $this->logAudit(
            $user->id,
            'auth.session_revoked',
            'RefreshToken',
            $session->id,
            null,
            ['sessionId' => $sessionId],
            $request
        );
    }

    /**
     * BE-AUTH-04: Register or update FCM device token.
     */
    public function registerDevice(User $user, array $data, Request $request): Device
    {
        $token = $data['fcm_token'] ?? $data['token'] ?? null;

        $device = Device::updateOrCreate(
            [
                'user_id' => $user->id,
                'fcm_token' => $token,
            ],
            [
                'platform' => $data['platform'],
                'last_seen_at' => now(),
            ]
        );

        $this->logAudit(
            $user->id,
            'device.registered',
            'Device',
            $device->id,
            null,
            [
                'platform' => $device->platform,
            ],
            $request
        );

        return $device;
    }

    /**
     * Find user by username, email, NIS/NISN, or NIP/NUPTK.
     */
    public function findUserByIdentifier(string $identifier): ?User
    {
        // 1. Direct match on username or email
        $user = User::with(['roles.permissions', 'student', 'teacher', 'guardian', 'staff'])
            ->where('username', $identifier)
            ->orWhere('email', $identifier)
            ->first();

        if ($user) {
            return $user;
        }

        // 2. Student NIS or NISN
        $student = Student::where('nis', $identifier)
            ->orWhere('nisn', $identifier)
            ->first();

        if ($student && $student->user_id) {
            return User::with(['roles.permissions', 'student', 'teacher', 'guardian', 'staff'])
                ->find($student->user_id);
        }

        // 3. Teacher NIP or NUPTK
        $teacher = Teacher::where('nip', $identifier)
            ->orWhere('nuptk', $identifier)
            ->first();

        if ($teacher && $teacher->user_id) {
            return User::with(['roles.permissions', 'student', 'teacher', 'guardian', 'staff', 'applicant'])
                ->find($teacher->user_id);
        }

        // 4. PPDB Applicant Registration Number (No. Registrasi PPDB)
        $applicant = Applicant::where('registration_no', $identifier)->first();
        if ($applicant && $applicant->user_id) {
            return User::with(['roles.permissions', 'student', 'teacher', 'guardian', 'staff', 'applicant'])
                ->find($applicant->user_id);
        }

        // 5. Phone number match
        $byPhone = User::with(['roles.permissions', 'student', 'teacher', 'guardian', 'staff', 'applicant'])
            ->where('phone', $identifier)
            ->first();
        if ($byPhone) {
            return $byPhone;
        }

        return null;
    }

    /**
     * Issue tokens directly for an authenticated user (e.g. after 2FA verification).
     */
    public function issueTokensForUser(User $user, Request $request, ?string $deviceId = null): array
    {
        $tokens = $this->generateTokenPair($user, $deviceId);

        $this->logAudit($user->id, 'auth.admin_login', 'User', $user->id, null, [
            'platform' => 'admin_web',
        ], $request);

        return [
            'user' => $this->formatUserResponse($user),
            'tokens' => $tokens,
        ];
    }

    /**
     * Generate pair of access token (15 mins) and refresh token (14 days).
     */
    public function generateTokenPair(User $user, ?string $deviceId = null): array
    {
        $tokenResult = $user->createToken('access_token', ['*'], now()->addMinutes(15));
        $plainRefreshToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $plainRefreshToken);

        RefreshToken::create([
            'user_id' => $user->id,
            'device_id' => $deviceId,
            'token_hash' => $tokenHash,
            'expires_at' => now()->addDays(14),
        ]);

        return [
            'accessToken' => $tokenResult->plainTextToken,
            'tokenType' => 'Bearer',
            'expiresIn' => 900,
            'refreshToken' => $plainRefreshToken,
        ];
    }

    /**
     * Format standardized User payload for auth responses.
     */
    public function formatUserResponse(User $user): array
    {
        $user->loadMissing(['roles.permissions', 'student', 'teacher', 'guardian', 'staff', 'applicant']);

        return [
            'id' => $user->id,
            'schoolId' => $user->school_id,
            'username' => $user->username,
            'email' => $user->email,
            'phone' => $user->phone,
            'status' => $user->status,
            'mustChangePassword' => (bool) $user->must_change_password,
            'lastLoginAt' => $user->last_login_at?->toIso8601String(),
            'roles' => $user->roles->pluck('code')->all(),
            'permissions' => $user->getAllPermissions(),
            'profile' => $user->getProfileSummary(),
        ];
    }

    /**
     * Helper to write an audit log entry.
     */
    protected function logAudit(
        ?string $actorId,
        string $action,
        string $entity,
        ?string $entityId,
        ?array $oldValue,
        ?array $newValue,
        Request $request
    ): void {
        try {
            AuditLog::create([
                'actor_id' => $actorId,
                'action' => $action,
                'entity' => $entity,
                'entity_id' => $entityId,
                'old_value' => $oldValue,
                'new_value' => $newValue,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
