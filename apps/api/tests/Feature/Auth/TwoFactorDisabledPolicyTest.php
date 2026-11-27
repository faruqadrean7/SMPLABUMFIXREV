<?php

namespace Tests\Feature\Auth;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Services\TwoFactorAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TwoFactorDisabledPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        $role = Role::firstOrCreate(['code' => 'admin_tu'], ['name' => 'Admin TU', 'is_system' => true]);
        $user = User::create([
            'username' => 'admin_p1_06_'.uniqid(),
            'email' => 'admin_p1_06_'.uniqid().'@test.local',
            'password_hash' => Hash::make('AdminPass123!'),
            'status' => 'active',
        ]);
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_p1_06_all_two_factor_endpoints_return_410_gone(): void
    {
        $admin = $this->createAdmin();

        $this->postJson('/api/v1/auth/2fa/setup')
            ->assertStatus(410)
            ->assertJsonPath('error.code', 'FEATURE_DISABLED');

        $this->postJson('/api/v1/auth/2fa/verify', ['code' => '123456'])
            ->assertStatus(410)
            ->assertJsonPath('error.code', 'FEATURE_DISABLED');

        $this->postJson('/api/v1/auth/2fa/challenge', ['mfa_token' => 'fake'])
            ->assertStatus(410)
            ->assertJsonPath('error.code', 'FEATURE_DISABLED');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/auth/2fa/disable', ['password' => 'AdminPass123!'])
            ->assertStatus(410)
            ->assertJsonPath('error.code', 'FEATURE_DISABLED');
    }

    public function test_p1_06_admin_login_succeeds_directly_without_2fa_and_writes_single_audit_entry(): void
    {
        $admin = $this->createAdmin();

        $res = $this->postJson('/api/v1/auth/admin/login', [
            'identifier' => $admin->username,
            'password' => 'AdminPass123!',
        ]);

        $res->assertStatus(200)
            ->assertJsonPath('data.user.id', $admin->id);
        $this->assertNotEmpty($res->json('data.tokens.accessToken'));

        // Pastikan tidak ada duplikasi entri audit auth.admin_login
        $this->assertSame(
            1,
            AuditLog::where('actor_id', $admin->id)->where('action', 'auth.admin_login')->count()
        );
    }

    public function test_p1_06_admin_login_rejects_mfa_token_without_credentials(): void
    {
        $admin = $this->createAdmin();
        $service = app(TwoFactorAuthService::class);

        // Enrollment token tidak boleh lolos validateMfaToken (defense-in-depth type check)
        $enrollmentToken = $service->generateEnrollmentToken($admin);
        $this->assertNull($service->validateMfaToken($enrollmentToken));

        // Login admin tanpa identifier/password ditolak validasi
        $this->postJson('/api/v1/auth/admin/login', [
            'mfa_token' => $service->generateMfaToken($admin),
            'totp_code' => '123456',
        ])->assertStatus(422);
    }
}
