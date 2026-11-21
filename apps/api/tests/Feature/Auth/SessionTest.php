<?php

namespace Tests\Feature\Auth;

use App\Models\Device;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SessionTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'username' => 'session_user',
            'email' => 'session@labschool.sch.id',
            'password_hash' => Hash::make('SecretPassword123!'),
            'status' => 'active',
        ]);

        $this->otherUser = User::create([
            'username' => 'other_user',
            'email' => 'other@labschool.sch.id',
            'password_hash' => Hash::make('SecretPassword123!'),
            'status' => 'active',
        ]);
    }

    /**
     * BE-AUTH-04: Get list of active sessions for current user
     */
    public function test_be_auth_04_get_active_sessions_for_current_user(): void
    {
        $device = Device::create([
            'user_id' => $this->user->id,
            'platform' => 'android',
            'fcm_token' => 'fcm_token_sample_123',
            'last_seen_at' => now(),
        ]);

        // 2 active sessions
        RefreshToken::create([
            'user_id' => $this->user->id,
            'device_id' => $device->id,
            'token_hash' => hash('sha256', 'token_1'),
            'expires_at' => now()->addDays(14),
        ]);

        RefreshToken::create([
            'user_id' => $this->user->id,
            'token_hash' => hash('sha256', 'token_2'),
            'expires_at' => now()->addDays(7),
        ]);

        $token = $this->user->createToken('test_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/sessions');

        $response->assertStatus(200)
            ->assertHeader('X-Request-Id')
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id', 'createdAt', 'expiresAt', 'device',
                    ],
                ],
            ]);
    }

    /**
     * BE-AUTH-04: Active sessions list excludes expired and revoked tokens
     */
    public function test_be_auth_04_get_sessions_excludes_expired_or_revoked_tokens(): void
    {
        // 1 active
        RefreshToken::create([
            'user_id' => $this->user->id,
            'token_hash' => hash('sha256', 'active_token'),
            'expires_at' => now()->addDays(14),
        ]);

        // 1 revoked
        RefreshToken::create([
            'user_id' => $this->user->id,
            'token_hash' => hash('sha256', 'revoked_token'),
            'expires_at' => now()->addDays(14),
            'revoked_at' => now()->subDay(),
        ]);

        // 1 expired
        RefreshToken::create([
            'user_id' => $this->user->id,
            'token_hash' => hash('sha256', 'expired_token'),
            'expires_at' => now()->subDay(),
        ]);

        $token = $this->user->createToken('test_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/sessions');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    /**
     * BE-AUTH-04: Revoke specific session successfully
     */
    public function test_be_auth_04_revoke_session_success(): void
    {
        $session = RefreshToken::create([
            'user_id' => $this->user->id,
            'token_hash' => hash('sha256', 'token_to_revoke'),
            'expires_at' => now()->addDays(14),
        ]);

        $token = $this->user->createToken('test_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/v1/auth/sessions/'.$session->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.message', 'Sesi perangkat berhasil dicabut');

        $session->refresh();
        $this->assertNotNull($session->revoked_at);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->user->id,
            'action' => 'auth.session_revoked',
            'entity' => 'RefreshToken',
            'entity_id' => $session->id,
        ]);
    }

    /**
     * BE-AUTH-04: Revoking another user's session returns 404 (IDOR protection)
     */
    public function test_be_auth_04_revoke_session_fails_when_session_belongs_to_another_user_404(): void
    {
        $otherSession = RefreshToken::create([
            'user_id' => $this->otherUser->id,
            'token_hash' => hash('sha256', 'other_token'),
            'expires_at' => now()->addDays(14),
        ]);

        $token = $this->user->createToken('test_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/v1/auth/sessions/'.$otherSession->id);

        $response->assertStatus(404)
            ->assertJsonPath('error.code', 'NOT_FOUND');

        $otherSession->refresh();
        $this->assertNull($otherSession->revoked_at);
    }

    /**
     * BE-AUTH-04: Register device FCM token
     */
    public function test_be_auth_04_register_device_token_fcm_success(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/devices', [
                'platform' => 'android',
                'fcm_token' => 'fcm_sample_token_xyz_1234567890',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.message', 'Perangkat berhasil didaftarkan')
            ->assertJsonPath('data.device.platform', 'android');

        $this->assertDatabaseHas('devices', [
            'user_id' => $this->user->id,
            'platform' => 'android',
            'fcm_token' => 'fcm_sample_token_xyz_1234567890',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->user->id,
            'action' => 'device.registered',
            'entity' => 'Device',
        ]);
    }

    /**
     * BE-AUTH-04: Register device updates last_seen_at for existing device
     */
    public function test_be_auth_04_register_device_updates_existing_token(): void
    {
        Device::create([
            'user_id' => $this->user->id,
            'platform' => 'ios',
            'fcm_token' => 'fcm_old_token',
            'last_seen_at' => now()->subDay(),
        ]);

        $token = $this->user->createToken('test_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/devices', [
                'platform' => 'ios',
                'fcm_token' => 'fcm_old_token',
            ]);

        $response->assertStatus(201);
        $this->assertEquals(1, Device::where('user_id', $this->user->id)->count());
    }

    /**
     * BE-AUTH-04: Register device validation failure (400)
     */
    public function test_be_auth_04_register_device_validation_failure_400(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/devices', [
                'platform' => 'unsupported_os',
                'fcm_token' => '',
            ]);

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    /**
     * BE-AUTH-04: Unauthenticated requests rejected with 401
     */
    public function test_be_auth_04_unauthenticated_requests_rejected_with_401(): void
    {
        $this->getJson('/api/v1/auth/sessions')->assertStatus(401);
        $this->deleteJson('/api/v1/auth/sessions/any-id')->assertStatus(401);
        $this->postJson('/api/v1/devices', [])->assertStatus(401);
    }
}
