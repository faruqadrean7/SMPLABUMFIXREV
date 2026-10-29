<?php

namespace Tests\Feature\Auth;

use App\Models\Permission;
use App\Models\RefreshToken;
use App\Models\Role;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        // Create basic roles and permissions for portal user
        $this->adminRole = Role::create([
            'code' => 'teacher',
            'name' => 'Guru',
            'is_system' => true,
        ]);

        $perm1 = Permission::create(['code' => 'auth.user.read', 'description' => 'Melihat daftar pengguna']);
        $perm2 = Permission::create(['code' => 'master.student.read', 'description' => 'Melihat data siswa']);
        $this->adminRole->permissions()->attach([$perm1->id, $perm2->id]);

        $this->user = User::create([
            'username' => 'johndoe',
            'email' => 'john@labschool.sch.id',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
            'must_change_password' => false,
        ]);
        $this->user->roles()->attach($this->adminRole->id);
    }

    /**
     * BE-AUTH-01: Happy Path - Login with username
     */
    public function test_be_auth_01_successful_login_with_username(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'identifier' => 'johndoe',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(200)
            ->assertHeader('X-Request-Id')
            ->assertJsonPath('data.user.username', 'johndoe')
            ->assertJsonPath('data.user.email', 'john@labschool.sch.id')
            ->assertJsonPath('data.user.roles', ['teacher'])
            ->assertJsonStructure([
                'data' => [
                    'user' => [
                        'id', 'username', 'email', 'status', 'mustChangePassword', 'roles', 'permissions',
                    ],
                    'tokens' => [
                        'accessToken', 'tokenType', 'expiresIn', 'refreshToken',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->user->id,
            'action' => 'auth.login',
            'entity' => 'User',
            'entity_id' => $this->user->id,
        ]);
    }

    /**
     * BE-AUTH-01: Happy Path - Login with email
     */
    public function test_be_auth_01_successful_login_with_email(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'identifier' => 'john@labschool.sch.id',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.user.username', 'johndoe');
    }

    /**
     * BE-AUTH-01: Happy Path - Login with Student NIS
     */
    public function test_be_auth_01_successful_login_with_student_nis(): void
    {
        $studentUser = User::create([
            'username' => 'siswa123',
            'password_hash' => Hash::make('SiswaSecret123!'),
            'status' => 'active',
        ]);

        Student::create([
            'user_id' => $studentUser->id,
            'nis' => '10203040',
            'nisn' => '0055123456',
            'nik_enc' => 'enc_1234567890123456',
            'full_name' => 'Budi Santoso',
            'gender' => 'L',
            'birth_place' => 'Jakarta',
            'birth_date' => '2008-05-15',
            'status' => 'aktif',
            'entry_date' => '2024-07-15',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'identifier' => '10203040',
            'password' => 'SiswaSecret123!',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.user.profile.type', 'student')
            ->assertJsonPath('data.user.profile.name', 'Budi Santoso')
            ->assertJsonPath('data.user.profile.nis', '10203040');
    }

    /**
     * BE-AUTH-01: Happy Path - Login with Teacher NIP
     */
    public function test_be_auth_01_successful_login_with_teacher_nip(): void
    {
        $teacherUser = User::create([
            'username' => 'guru_math',
            'password_hash' => Hash::make('GuruSecret123!'),
            'status' => 'active',
        ]);

        Teacher::create([
            'user_id' => $teacherUser->id,
            'nip' => '198501012010011005',
            'full_name' => 'Dra. Siti Aminah, M.Pd.',
            'status' => 'tetap',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'identifier' => '198501012010011005',
            'password' => 'GuruSecret123!',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.user.profile.type', 'teacher')
            ->assertJsonPath('data.user.profile.name', 'Dra. Siti Aminah, M.Pd.');
    }

    /**
     * BE-AUTH-01: Validation Failed (400)
     */
    public function test_be_auth_01_login_validation_failure_returns_400_validation_failed(): void
    {
        $response = $this->postJson('/api/v1/auth/login', []);

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure([
                'error' => ['code', 'message', 'details', 'requestId'],
            ]);
    }

    /**
     * BE-AUTH-01: Security - Wrong password returns uniform 401 without leaking user existence
     */
    public function test_be_auth_01_login_fails_with_wrong_password_uniform_response_401(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'identifier' => 'johndoe',
            'password' => 'WrongPassword!',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED')
            ->assertJsonPath('error.message', 'Kredensial yang diberikan tidak cocok dengan data kami.');
    }

    /**
     * BE-AUTH-01: Security - Non-existent user returns exact same uniform 401 message
     */
    public function test_be_auth_01_login_fails_with_non_existent_user_uniform_response_401(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'identifier' => 'unknown_user_9999',
            'password' => 'RandomPassword123!',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED')
            ->assertJsonPath('error.message', 'Kredensial yang diberikan tidak cocok dengan data kami.');
    }

    /**
     * BE-AUTH-01: Security - Suspended or locked account returns 403 Forbidden
     */
    public function test_be_auth_01_login_fails_when_user_is_suspended_or_locked_403(): void
    {
        $this->user->update(['status' => 'suspended']);

        $response = $this->postJson('/api/v1/auth/login', [
            'identifier' => 'johndoe',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    /**
     * BE-AUTH-01: Security - Rate limiting lockout after 5 consecutive failed attempts (429)
     */
    public function test_be_auth_01_rate_limiting_locks_out_after_five_failed_attempts_429(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'identifier' => 'johndoe',
                'password' => 'WrongPassword!',
            ]);
        }

        // 6th attempt should be blocked by rate limiter with 429
        $response = $this->postJson('/api/v1/auth/login', [
            'identifier' => 'johndoe',
            'password' => 'WrongPassword!',
        ]);

        $response->assertStatus(429)
            ->assertJsonPath('error.code', 'RATE_LIMITED');
    }

    /**
     * BE-AUTH-01: Refresh Token Rotation - Success
     */
    public function test_be_auth_01_refresh_token_rotation_issues_new_token_pair(): void
    {
        $loginRes = $this->postJson('/api/v1/auth/login', [
            'identifier' => 'johndoe',
            'password' => 'Password123!',
        ]);

        $refreshToken = $loginRes->json('data.tokens.refreshToken');
        $this->assertNotEmpty($refreshToken);

        // Call refresh endpoint
        $refreshRes = $this->postJson('/api/v1/auth/refresh', [
            'refreshToken' => $refreshToken,
        ]);

        $refreshRes->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'user',
                    'tokens' => ['accessToken', 'tokenType', 'expiresIn', 'refreshToken'],
                ],
            ]);

        $newRefreshToken = $refreshRes->json('data.tokens.refreshToken');
        $this->assertNotEquals($refreshToken, $newRefreshToken);

        // Old refresh token must be revoked and linked to new token
        $oldTokenHash = hash('sha256', $refreshToken);
        $oldTokenModel = RefreshToken::where('token_hash', $oldTokenHash)->first();
        $this->assertNotNull($oldTokenModel->revoked_at);
        $this->assertNotNull($oldTokenModel->replaced_by);
    }

    /**
     * BE-AUTH-01: Security - Reuse of rotated refresh token revokes all sessions!
     */
    public function test_be_auth_01_refresh_token_reuse_detected_revokes_all_sessions(): void
    {
        $loginRes = $this->postJson('/api/v1/auth/login', [
            'identifier' => 'johndoe',
            'password' => 'Password123!',
        ]);

        $initialRefreshToken = $loginRes->json('data.tokens.refreshToken');

        // First rotation (valid)
        $this->postJson('/api/v1/auth/refresh', [
            'refreshToken' => $initialRefreshToken,
        ])->assertStatus(200);

        // Attacker / Replay trying to reuse the old initialRefreshToken
        $reuseRes = $this->postJson('/api/v1/auth/refresh', [
            'refreshToken' => $initialRefreshToken,
        ]);

        $reuseRes->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        // All refresh tokens for this user must now be revoked
        $activeTokensCount = RefreshToken::where('user_id', $this->user->id)
            ->whereNull('revoked_at')
            ->count();
        $this->assertEquals(0, $activeTokensCount);

        // Sanctum personal access tokens must also be deleted
        $this->assertEquals(0, $this->user->tokens()->count());

        // Security event must be logged in audit_logs
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->user->id,
            'action' => 'auth.refresh_token_reuse_detected',
        ]);
    }

    /**
     * BE-AUTH-01: Expired refresh token returns 401
     */
    public function test_be_auth_01_expired_refresh_token_returns_401(): void
    {
        $plainToken = bin2hex(random_bytes(32));
        RefreshToken::create([
            'user_id' => $this->user->id,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->subDay(), // expired yesterday
        ]);

        $response = $this->postJson('/api/v1/auth/refresh', [
            'refreshToken' => $plainToken,
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    /**
     * BE-AUTH-01: Logout revokes tokens
     */
    public function test_be_auth_01_logout_revokes_access_and_refresh_tokens(): void
    {
        $loginRes = $this->postJson('/api/v1/auth/login', [
            'identifier' => 'johndoe',
            'password' => 'Password123!',
        ]);

        $accessToken = $loginRes->json('data.tokens.accessToken');
        $refreshToken = $loginRes->json('data.tokens.refreshToken');

        $response = $this->withHeader('Authorization', 'Bearer '.$accessToken)
            ->postJson('/api/v1/auth/logout', [
                'refreshToken' => $refreshToken,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.message', 'Berhasil keluar dari sistem');

        // Refresh token must be revoked
        $tokenHash = hash('sha256', $refreshToken);
        $this->assertNotNull(RefreshToken::where('token_hash', $tokenHash)->first()->revoked_at);

        // Sanctum personal access token deleted from DB
        $this->assertEquals(0, $this->user->tokens()->count());

        // Access token is revoked, subsequent request with it must fail 401
        $this->app['auth']->forgetGuards();
        $meRes = $this->withHeader('Authorization', 'Bearer '.$accessToken)
            ->getJson('/api/v1/auth/me');
        $meRes->assertStatus(401);
    }

    /**
     * BE-AUTH-01: GET /auth/me returns current user profile and permissions
     */
    public function test_be_auth_01_get_me_returns_profile_and_permissions(): void
    {
        $loginRes = $this->postJson('/api/v1/auth/login', [
            'identifier' => 'johndoe',
            'password' => 'Password123!',
        ]);

        $accessToken = $loginRes->json('data.tokens.accessToken');

        $response = $this->withHeader('Authorization', 'Bearer '.$accessToken)
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJsonPath('data.user.username', 'johndoe')
            ->assertJsonPath('data.user.email', 'john@labschool.sch.id')
            ->assertJsonPath('data.user.roles', ['teacher'])
            ->assertJsonPath('data.user.permissions', ['auth.user.read', 'master.student.read']);
    }

    /**
     * BE-AUTH-01: Unauthenticated request to /auth/me returns 401
     */
    public function test_be_auth_01_unauthenticated_request_to_me_returns_401(): void
    {
        $response = $this->getJson('/api/v1/auth/me');
        $response->assertStatus(401);
    }
}
