<?php

namespace Tests\Feature\Auth;

use App\Models\AdminTrustedDevice;
use App\Models\Role;
use App\Models\User;
use App\Services\TwoFactorAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Fitur "Percayai Perangkat 30 Hari" untuk login admin (pengganti 2FA wajib tiap login).
 */
class AdminTrustedDeviceTest extends TestCase
{
    use RefreshDatabase;

    protected TwoFactorAuthService $twoFactor;

    protected User $admin;

    protected string $secret;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->twoFactor = app(TwoFactorAuthService::class);

        $role = Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Administrator', 'is_system' => true]);

        $this->secret = $this->twoFactor->generateSecretKey();
        $this->admin = User::create([
            'username' => 'tdt_admin',
            'email' => 'tdt_admin@labschool.sch.id',
            'password_hash' => Hash::make('AdminPass123!'),
            'status' => 'active',
            'must_change_password' => false,
            'two_factor_secret' => $this->secret,
            'two_factor_confirmed_at' => now(),
        ]);
        $this->admin->roles()->attach($role->id);
    }

    private function passwordStep(array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/v1/auth/admin/login', array_merge([
            'identifier' => 'tdt_admin',
            'password' => 'AdminPass123!',
        ], $extra));
    }

    public function test_remember_device_issues_trusted_token_on_successful_2fa(): void
    {
        $challenge = $this->passwordStep();
        $challenge->assertStatus(200)->assertJsonPath('mfa_required', true);
        $mfaToken = $challenge->json('mfa_token');

        $verify = $this->postJson('/api/v1/auth/admin/login', [
            'mfa_token' => $mfaToken,
            'totp_code' => $this->twoFactor->calculateCode($this->secret),
            'remember_device' => true,
        ]);

        $verify->assertStatus(200);
        $token = $verify->json('data.trusted_device_token');
        $this->assertNotEmpty($token, 'Server harus mengembalikan trusted_device_token saat remember_device=true');
        $this->assertNotNull($verify->json('data.trusted_device_expires_at'));

        // Tersimpan sebagai HASH, bukan plaintext
        $this->assertDatabaseHas('admin_trusted_devices', [
            'user_id' => $this->admin->id,
            'token_hash' => hash('sha256', $token),
        ]);
        $this->assertDatabaseMissing('admin_trusted_devices', [
            'token_hash' => $token,
        ]);
    }

    public function test_valid_trusted_device_token_skips_2fa(): void
    {
        $raw = Str::random(64);
        AdminTrustedDevice::create([
            'user_id' => $this->admin->id,
            'token_hash' => hash('sha256', $raw),
            'expires_at' => Carbon::now()->addDays(30),
        ]);

        $res = $this->passwordStep(['trusted_device_token' => $raw]);

        $res->assertStatus(200);
        // Tidak ada tantangan 2FA; langsung dapat token sesi
        $this->assertNull($res->json('mfa_required'));
        $this->assertNotEmpty($res->json('data.tokens'));
        $this->assertNotEmpty($res->json('data.user'));
    }

    public function test_invalid_trusted_device_token_still_requires_2fa(): void
    {
        $res = $this->passwordStep(['trusted_device_token' => str_repeat('x', 64)]);

        $res->assertStatus(200)->assertJsonPath('mfa_required', true);
        $this->assertNull($res->json('data.tokens'));
    }

    public function test_expired_trusted_device_token_requires_2fa(): void
    {
        $raw = Str::random(64);
        AdminTrustedDevice::create([
            'user_id' => $this->admin->id,
            'token_hash' => hash('sha256', $raw),
            'expires_at' => Carbon::now()->subDay(),
        ]);

        $res = $this->passwordStep(['trusted_device_token' => $raw]);

        $res->assertStatus(200)->assertJsonPath('mfa_required', true);
    }

    public function test_trusted_device_token_is_bound_to_specific_user(): void
    {
        $role = Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Administrator', 'is_system' => true]);
        $other = User::create([
            'username' => 'tdt_admin2',
            'email' => 'tdt_admin2@labschool.sch.id',
            'password_hash' => Hash::make('AdminPass123!'),
            'status' => 'active',
            'must_change_password' => false,
            'two_factor_secret' => $this->twoFactor->generateSecretKey(),
            'two_factor_confirmed_at' => now(),
        ]);
        $other->roles()->attach($role->id);

        // Token diterbitkan untuk $other, tetapi dipakai saat login sebagai $this->admin
        $raw = Str::random(64);
        AdminTrustedDevice::create([
            'user_id' => $other->id,
            'token_hash' => hash('sha256', $raw),
            'expires_at' => Carbon::now()->addDays(30),
        ]);

        $res = $this->passwordStep(['trusted_device_token' => $raw]);

        // Harus tetap diminta 2FA karena token bukan milik admin ini
        $res->assertStatus(200)->assertJsonPath('mfa_required', true);
    }
}
