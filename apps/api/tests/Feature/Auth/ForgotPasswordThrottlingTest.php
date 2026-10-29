<?php

namespace Tests\Feature\Auth;

use App\Models\PasswordReset;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ForgotPasswordThrottlingTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->user = User::create([
            'username' => 'user_pwd_throttle',
            'email' => 'user_throttle@labschool.sch.id',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
    }

    public function test_p1_sec_dos_01_forgot_password_throttles_after_limit(): void
    {
        // 5 requests allowed per 15 minutes
        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson('/api/v1/auth/forgot-password', [
                'identifier' => 'user_throttle@labschool.sch.id',
            ]);
            $response->assertStatus(200);
        }

        // 6th request must be throttled with 429
        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'identifier' => 'user_throttle@labschool.sch.id',
        ]);
        $response->assertStatus(429);
    }

    public function test_p1_sec_dos_01_active_token_not_deleted_within_cooldown_window(): void
    {
        // Request 1: creates token
        $res1 = $this->postJson('/api/v1/auth/forgot-password', [
            'identifier' => 'user_throttle@labschool.sch.id',
        ]);
        $res1->assertStatus(200);
        $firstToken = $res1->json('data.resetToken');
        $this->assertNotEmpty($firstToken);

        $initialReset = PasswordReset::where('user_id', $this->user->id)->first();
        $this->assertNotNull($initialReset);

        // Immediate Request 2: within 2 minutes cooldown
        $res2 = $this->postJson('/api/v1/auth/forgot-password', [
            'identifier' => 'user_throttle@labschool.sch.id',
        ]);
        $res2->assertStatus(200);

        // Verify the original token in DB was NOT deleted
        $currentReset = PasswordReset::where('user_id', $this->user->id)->first();
        $this->assertEquals($initialReset->id, $currentReset->id);
        $this->assertEquals($initialReset->token_hash, $currentReset->token_hash);

        // Verify the original token can still be used to reset password
        $resetResponse = $this->postJson('/api/v1/auth/reset-password', [
            'token' => $firstToken,
            'password' => 'NewSecurePassword123!',
            'password_confirmation' => 'NewSecurePassword123!',
        ]);
        $resetResponse->assertStatus(200);
    }

    public function test_p1_sec_dos_01_token_regenerated_after_cooldown_window(): void
    {
        // Request 1
        $res1 = $this->postJson('/api/v1/auth/forgot-password', [
            'identifier' => 'user_throttle@labschool.sch.id',
        ]);
        $res1->assertStatus(200);
        $initialReset = PasswordReset::where('user_id', $this->user->id)->first();

        // Advance time by 3 minutes (past the 2-minute cooldown)
        Carbon::setTestNow(now()->addMinutes(3));

        // Request 2
        $res2 = $this->postJson('/api/v1/auth/forgot-password', [
            'identifier' => 'user_throttle@labschool.sch.id',
        ]);
        $res2->assertStatus(200);

        $newReset = PasswordReset::where('user_id', $this->user->id)->first();
        $this->assertNotEquals($initialReset->token_hash, $newReset->token_hash);
        Carbon::setTestNow();
    }
}
