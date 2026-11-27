<?php

namespace Tests\Feature\Auth;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserPrivilegeEscalationTest extends TestCase
{
    use RefreshDatabase;

    protected Role $superAdminRole;

    protected Role $adminTuRole;

    protected Role $roleManagerRole;

    protected Role $teacherRole;

    protected User $superAdminUser;

    protected User $adminTuUser;

    protected User $roleManagerUser;

    protected User $teacherUser;

    protected function setUp(): void
    {
        parent::setUp();

        $permReadUser = Permission::firstOrCreate(['code' => 'auth.user.read'], ['description' => 'Read users']);
        $permWriteUser = Permission::firstOrCreate(['code' => 'auth.user.write'], ['description' => 'Write users']);
        $permManageRole = Permission::firstOrCreate(['code' => 'auth.role.manage'], ['description' => 'Manage roles']);

        $this->superAdminRole = Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Admin', 'is_system' => true]);
        $this->superAdminRole->permissions()->syncWithoutDetaching([$permReadUser->id, $permWriteUser->id, $permManageRole->id]);

        // Admin TU: punya auth.user.write tapi TIDAK punya auth.role.manage
        $this->adminTuRole = Role::firstOrCreate(['code' => 'admin_tu'], ['name' => 'Admin TU', 'is_system' => true]);
        $this->adminTuRole->permissions()->syncWithoutDetaching([$permReadUser->id, $permWriteUser->id]);

        // Role Manager non-super_admin: punya auth.user.write + auth.role.manage, tapi BUKAN super_admin
        $this->roleManagerRole = Role::firstOrCreate(['code' => 'role_manager_non_sa'], ['name' => 'Role Manager', 'is_system' => false]);
        $this->roleManagerRole->permissions()->syncWithoutDetaching([$permReadUser->id, $permWriteUser->id, $permManageRole->id]);

        $this->teacherRole = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);

        $this->superAdminUser = $this->makeUser('sa_privesc', $this->superAdminRole);
        $this->adminTuUser = $this->makeUser('tu_privesc', $this->adminTuRole);
        $this->roleManagerUser = $this->makeUser('rm_privesc', $this->roleManagerRole);
        $this->teacherUser = $this->makeUser('guru_privesc', $this->teacherRole);
    }

    private function makeUser(string $username, Role $role): User
    {
        $user = User::create([
            'username' => $username,
            'email' => "{$username}@sekolah.sch.id",
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_p0_07_user_with_only_user_write_cannot_assign_super_admin_or_modify_roles(): void
    {
        Sanctum::actingAs($this->adminTuUser);

        // 1. Gagal membuat akun baru dengan role super_admin (by code)
        $this->postJson('/api/v1/users', [
            'username' => 'rogue_sa',
            'email' => 'rogue_sa@sekolah.sch.id',
            'password' => 'Password123!',
            'roles' => ['super_admin'],
        ])->assertStatus(403);

        // 2. Gagal mengangkat dirinya sendiri menjadi super_admin (by UUID)
        $this->putJson("/api/v1/users/{$this->adminTuUser->id}", [
            'roles' => [$this->superAdminRole->id],
        ])->assertStatus(403);

        $this->assertFalse($this->adminTuUser->fresh()->hasRole('super_admin'));

        // 3. Gagal mengubah akun super_admin
        $this->putJson("/api/v1/users/{$this->superAdminUser->id}", [
            'phone' => '081200009999',
        ])->assertStatus(403);

        // 4. Masih diizinkan memperbarui profil non-role (mis. nomor HP) user biasa
        $this->putJson("/api/v1/users/{$this->teacherUser->id}", [
            'phone' => '081299990000',
        ])->assertStatus(200)
            ->assertJsonPath('data.phone', '081299990000');
    }

    public function test_p0_07_non_super_admin_with_role_manage_still_cannot_grant_super_admin(): void
    {
        Sanctum::actingAs($this->roleManagerUser);

        // Boleh menetapkan role non-super_admin (mis. teacher)
        $this->postJson('/api/v1/users', [
            'username' => 'guru_baru_ok',
            'email' => 'guru_baru_ok@sekolah.sch.id',
            'password' => 'Password123!',
            'roles' => ['teacher'],
        ])->assertStatus(201);

        // Ditolak jika mencoba menetapkan super_admin
        $this->postJson('/api/v1/users', [
            'username' => 'rogue_sa_2',
            'email' => 'rogue_sa_2@sekolah.sch.id',
            'password' => 'Password123!',
            'roles' => ['super_admin'],
        ])->assertStatus(403);

        $this->putJson("/api/v1/users/{$this->teacherUser->id}", [
            'roles' => ['teacher', 'super_admin'],
        ])->assertStatus(403);
    }

    public function test_p0_07_super_admin_can_still_assign_roles_normally(): void
    {
        Sanctum::actingAs($this->superAdminUser);

        $this->postJson('/api/v1/users', [
            'username' => 'sa_second',
            'email' => 'sa_second@sekolah.sch.id',
            'password' => 'Password123!',
            'roles' => ['super_admin'],
        ])->assertStatus(201)
            ->assertJsonPath('data.user.username', 'sa_second');
    }
}
