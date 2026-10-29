<?php

namespace Tests\Feature\Auth;

use App\Models\PasswordReset;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->user = User::create([
            'username' => 'testuser',
            'email' => 'test@labschool.sch.id',
            'password_hash' => Hash::make('OldPassword123!'),
            'status' => 'active',
            'must_change_password' => true,
        ]);
    }

    /**
     * BE-AUTH-02: Forgot password returns uniform response and generates 15-min token
     */
    public function test_be_auth_02_forgot_password_generates_token_and_uniform_response(): void
    {
        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'identifier' => 'test@labschool.sch.id',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.message', 'Jika akun dengan identitas tersebut terdaftar di sistem, instruksi pemulihan kata sandi telah dikirimkan.');

        $this->assertDatabaseHas('password_resets', [
            'user_id' => $this->user->id,
            'used_at' => null,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->user->id,
            'action' => 'auth.forgot_password_requested',
        ]);
    }

    /**
     * BE-AUTH-02: Forgot password with non-existent account returns exact same uniform response
     */
    public function test_be_auth_02_forgot_password_with_non_existent_account_returns_uniform_response(): void
    {
        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'identifier' => 'nonexistent@labschool.sch.id',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.message', 'Jika akun dengan identitas tersebut terdaftar di sistem, instruksi pemulihan kata sandi telah dikirimkan.');
    }

    /**
     * BE-AUTH-02: Reset password success updates password and clears must_change_password
     */
    public function test_be_auth_02_reset_password_success_updates_password_and_clears_must_change_password(): void
    {
        $plainToken = bin2hex(random_bytes(32));
        PasswordReset::create([
            'user_id' => $this->user->id,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addMinutes(15),
        ]);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token' => $plainToken,
            'password' => 'NewSecretPassword123!',
            'password_confirmation' => 'NewSecretPassword123!',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.message', 'Kata sandi berhasil diatur ulang. Silakan masuk kembali dengan kata sandi baru Anda.');

        $this->user->refresh();
        $this->assertTrue(Hash::check('NewSecretPassword123!', $this->user->password_hash));
        $this->assertFalse($this->user->must_change_password);

        // Token must be marked as used
        $resetRecord = PasswordReset::where('token_hash', hash('sha256', $plainToken))->first();
        $this->assertNotNull($resetRecord->used_at);

        // Audit log must record event
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->user->id,
            'action' => 'auth.password_reset_success',
        ]);
    }

    /**
     * BE-AUTH-02: Reset password fails when token is invalid or already used
     */
    public function test_be_auth_02_reset_password_fails_with_invalid_or_used_token(): void
    {
        // 1. Invalid token
        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token' => 'invalid-token-string',
            'password' => 'NewSecretPassword123!',
            'password_confirmation' => 'NewSecretPassword123!',
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'BUSINESS_RULE_VIOLATION');

        // 2. Already used token
        $plainToken = bin2hex(random_bytes(32));
        PasswordReset::create([
            'user_id' => $this->user->id,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addMinutes(15),
            'used_at' => now()->subMinute(),
        ]);

        $response2 = $this->postJson('/api/v1/auth/reset-password', [
            'token' => $plainToken,
            'password' => 'NewSecretPassword123!',
            'password_confirmation' => 'NewSecretPassword123!',
        ]);

        $response2->assertStatus(400)
            ->assertJsonPath('error.code', 'BUSINESS_RULE_VIOLATION');
    }

    /**
     * BE-AUTH-02: Reset password fails when token has expired (>15 min)
     */
    public function test_be_auth_02_reset_password_fails_with_expired_token(): void
    {
        $plainToken = bin2hex(random_bytes(32));
        PasswordReset::create([
            'user_id' => $this->user->id,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->subMinute(), // expired
        ]);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token' => $plainToken,
            'password' => 'NewSecretPassword123!',
            'password_confirmation' => 'NewSecretPassword123!',
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'BUSINESS_RULE_VIOLATION')
            ->assertJsonPath('error.message', 'Token pemulihan kata sandi telah kedaluwarsa. Silakan ajukan permohonan baru.');
    }

    /**
     * BE-AUTH-02: Reset password fails when validation fails (< 8 chars or confirmation mismatch)
     */
    public function test_be_auth_02_reset_password_fails_with_validation_errors(): void
    {
        // Too short (< 8 chars)
        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token' => 'some-token',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        // Confirmation mismatch
        $response2 = $this->postJson('/api/v1/auth/reset-password', [
            'token' => 'some-token',
            'password' => 'ValidPassword123!',
            'password_confirmation' => 'MismatchPassword123!',
        ]);

        $response2->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    /**
     * BE-AUTH-02: Security - Reset password revokes all active sessions and refresh tokens
     */
    public function test_be_auth_02_reset_password_revokes_all_active_sessions_and_tokens(): void
    {
        // Create an existing personal access token and refresh token
        $this->user->createToken('active_token');
        RefreshToken::create([
            'user_id' => $this->user->id,
            'token_hash' => hash('sha256', 'some_refresh_token'),
            'expires_at' => now()->addDays(14),
        ]);

        $this->assertEquals(1, $this->user->tokens()->count());
        $this->assertEquals(1, RefreshToken::where('user_id', $this->user->id)->whereNull('revoked_at')->count());

        $plainToken = bin2hex(random_bytes(32));
        PasswordReset::create([
            'user_id' => $this->user->id,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addMinutes(15),
        ]);

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => $plainToken,
            'password' => 'NewSecretPassword123!',
            'password_confirmation' => 'NewSecretPassword123!',
        ])->assertStatus(200);

        // All active tokens must be deleted / revoked
        $this->assertEquals(0, $this->user->tokens()->count());
        $this->assertEquals(0, RefreshToken::where('user_id', $this->user->id)->whereNull('revoked_at')->count());
    }

    /**
     * BE-AUTH-02: Change password success for authenticated user
     */
    public function test_be_auth_02_change_password_success_for_authenticated_user(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/change-password', [
                'current_password' => 'OldPassword123!',
                'new_password' => 'BrandNewPassword123!',
                'new_password_confirmation' => 'BrandNewPassword123!',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.message', 'Kata sandi berhasil diperbarui.');

        $this->user->refresh();
        $this->assertTrue(Hash::check('BrandNewPassword123!', $this->user->password_hash));
        $this->assertFalse($this->user->must_change_password);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->user->id,
            'action' => 'auth.password_changed',
        ]);
    }

    /**
     * BE-AUTH-02: Change password fails when current password does not match
     */
    public function test_be_auth_02_change_password_fails_with_incorrect_current_password(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/change-password', [
                'current_password' => 'WrongCurrentPassword!',
                'new_password' => 'BrandNewPassword123!',
                'new_password_confirmation' => 'BrandNewPassword123!',
            ]);

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath('error.message', 'Kata sandi saat ini tidak sesuai.');
    }

    /**
     * BE-AUTH-02: Change password fails when new password is same as current password
     */
    public function test_be_auth_02_change_password_fails_when_new_password_is_same(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/change-password', [
                'current_password' => 'OldPassword123!',
                'new_password' => 'OldPassword123!',
                'new_password_confirmation' => 'OldPassword123!',
            ]);

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath('error.message', 'Kata sandi baru tidak boleh sama dengan kata sandi saat ini.');
    }

    /**
     * BE-AUTH-02: Change password fails when unauthenticated
     */
    public function test_be_auth_02_change_password_fails_when_unauthenticated(): void
    {
        $response = $this->postJson('/api/v1/auth/change-password', [
            'current_password' => 'OldPassword123!',
            'new_password' => 'BrandNewPassword123!',
            'new_password_confirmation' => 'BrandNewPassword123!',
        ]);

        $response->assertStatus(401);
    }
}
