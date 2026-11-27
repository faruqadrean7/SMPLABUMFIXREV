<?php

namespace Tests\Feature\Auth;

use App\Models\Permission;
use App\Models\RefreshToken;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SessionRevocationSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function createCivitasUser(string $username = 'siswa_rev'): User
    {
        $role = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);

        $user = User::create([
            'username' => $username,
            'email' => "{$username}@test.local",
            'password_hash' => Hash::make('OldPassword123!'),
            'status' => 'active',
        ]);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function createAdminUser(): User
    {
        $role = Role::firstOrCreate(['code' => 'admin_tu'], ['name' => 'Admin TU', 'is_system' => true]);
        $perm = Permission::firstOrCreate(['code' => 'auth.user.write'], ['description' => 'Manage users']);
        $role->permissions()->syncWithoutDetaching([$perm->id]);

        $user = User::create([
            'username' => 'admin_rev_'.uniqid(),
            'email' => 'admin_rev_'.uniqid().'@test.local',
            'password_hash' => Hash::make('AdminPass123!'),
            'status' => 'active',
        ]);
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_p1_03_change_password_revokes_other_device_tokens_and_all_refresh_tokens(): void
    {
        $user = $this->createCivitasUser('siswa_multi_device');

        // Login Device A
        $loginA = $this->postJson('/api/v1/auth/login', [
            'identifier' => $user->username,
            'password' => 'OldPassword123!',
            'platform' => 'web',
        ])->assertStatus(200);

        // Login Device B
        $loginB = $this->postJson('/api/v1/auth/login', [
            'identifier' => $user->username,
            'password' => 'OldPassword123!',
            'platform' => 'android',
        ])->assertStatus(200);

        $tokenA = $loginA->json('data.tokens.accessToken');
        $refreshA = $loginA->json('data.tokens.refreshToken');
        $tokenB = $loginB->json('data.tokens.accessToken');
        $refreshB = $loginB->json('data.tokens.refreshToken');

        // Device A mengganti kata sandi
        $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->postJson('/api/v1/auth/change-password', [
                'current_password' => 'OldPassword123!',
                'new_password' => 'NewPassword456!',
                'new_password_confirmation' => 'NewPassword456!',
            ])
            ->assertStatus(200);

        // Access token Device B harus sudah dihapus dari personal_access_tokens
        $tokenBId = (int) explode('|', $tokenB)[0];
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenBId]);

        // Access token Device A tetap bertahan untuk sesi saat ini
        $tokenAId = (int) explode('|', $tokenA)[0];
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $tokenAId]);

        // Seluruh refresh token milik user harus sudah revoked
        $this->assertSame(
            0,
            RefreshToken::where('user_id', $user->id)->whereNull('revoked_at')->count()
        );

        // Upaya refresh menggunakan refresh token Device A & B ditolak 401
        $this->postJson('/api/v1/auth/refresh', ['refreshToken' => $refreshB])->assertStatus(401);
        $this->postJson('/api/v1/auth/refresh', ['refreshToken' => $refreshA])->assertStatus(401);
    }

    public function test_p1_03_locking_user_status_revokes_all_active_sessions_and_blocks_self_lock(): void
    {
        $admin = $this->createAdminUser();
        $target = $this->createCivitasUser('siswa_to_lock');

        $login = $this->postJson('/api/v1/auth/login', [
            'identifier' => $target->username,
            'password' => 'OldPassword123!',
            'platform' => 'android',
        ])->assertStatus(200);

        $targetTokenId = (int) explode('|', $login->json('data.tokens.accessToken'))[0];
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $targetTokenId]);

        // Admin mengunci akun target via PATCH /api/v1/users/{id}/status
        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/users/{$target->id}/status", ['status' => 'locked'])
            ->assertStatus(200);

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $targetTokenId]);
        $this->assertSame(
            0,
            RefreshToken::where('user_id', $target->id)->whereNull('revoked_at')->count()
        );

        // Admin tidak boleh mengunci akunnya sendiri
        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/users/{$admin->id}/status", ['status' => 'locked'])
            ->assertStatus(400);
    }
}
