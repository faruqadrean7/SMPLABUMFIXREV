<?php

namespace Tests\Feature\Auth;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RbacPermissionCacheTest extends TestCase
{
    use RefreshDatabase;

    private function createUserWithPermissions(array $permissionCodes, string $roleCode = 'custom_tester'): array
    {
        $role = Role::firstOrCreate(
            ['code' => $roleCode],
            ['name' => ucfirst(str_replace('_', ' ', $roleCode)), 'is_system' => false]
        );

        $permIds = [];
        foreach ($permissionCodes as $code) {
            $perm = Permission::firstOrCreate(
                ['code' => $code],
                ['description' => "Permission {$code}"]
            );
            $permIds[] = $perm->id;
        }
        $role->permissions()->sync($permIds);

        $user = User::create([
            'username' => 'rbac_'.uniqid(),
            'email' => 'rbac_'.uniqid().'@test.local',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $user->roles()->attach($role->id);

        return [$user, $role];
    }

    public function test_p1_02_updating_role_permissions_immediately_invalidates_user_permission_cache(): void
    {
        [$admin] = $this->createUserWithPermissions(['auth.role.manage'], 'role_manager_admin');
        [$target, $targetRole] = $this->createUserWithPermissions(['audit.read'], 'auditor_temp');

        $targetToken = $target->createToken('access_token')->plainTextToken;

        // 1. Akses pertama memicu pengisian cache user_perm_cache:{id}
        $first = $this->withHeader('Authorization', 'Bearer '.$targetToken)
            ->getJson('/api/v1/audit-logs');
        $first->assertStatus(200);
        $this->assertTrue(Cache::has("user_perm_cache:{$target->id}"));

        // 2. Admin mencabut izin audit.read dari role target via PUT /api/v1/roles/{id}
        $update = $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/roles/{$targetRole->id}", [
                'name' => $targetRole->name,
                'permission_ids' => [],
            ]);
        $update->assertStatus(200);
        $this->assertFalse(Cache::has("user_perm_cache:{$target->id}"));

        // 3. Permintaan berikutnya langsung ditolak 403 tanpa menunggu TTL 5 menit
        $second = $this->withHeader('Authorization', 'Bearer '.$targetToken)
            ->getJson('/api/v1/audit-logs');
        $second->assertStatus(403);
    }

    public function test_p1_02_assigning_user_roles_immediately_invalidates_user_permission_cache(): void
    {
        [$admin] = $this->createUserWithPermissions(['auth.role.manage'], 'role_manager_admin_2');
        [$target] = $this->createUserWithPermissions(['audit.read'], 'auditor_temp_2');
        $emptyRole = Role::create(['code' => 'no_perm_role', 'name' => 'No Perm Role', 'is_system' => false]);

        $targetToken = $target->createToken('access_token')->plainTextToken;

        // 1. Isi cache permission
        $this->withHeader('Authorization', 'Bearer '.$targetToken)
            ->getJson('/api/v1/audit-logs')
            ->assertStatus(200);
        $this->assertTrue(Cache::has("user_perm_cache:{$target->id}"));

        // 2. Ubah role user melalui PUT /api/v1/users/{id}/roles
        $assign = $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/users/{$target->id}/roles", [
                'roles' => [
                    ['role_id' => $emptyRole->id],
                ],
            ]);
        $assign->assertStatus(200);
        $this->assertFalse(Cache::has("user_perm_cache:{$target->id}"));

        // 3. Permintaan berikutnya langsung ditolak 403
        $this->withHeader('Authorization', 'Bearer '.$targetToken)
            ->getJson('/api/v1/audit-logs')
            ->assertStatus(403);
    }

    public function test_p1_02_updating_user_roles_via_user_endpoint_invalidates_permission_cache(): void
    {
        [$admin] = $this->createUserWithPermissions(['auth.user.write', 'auth.role.manage'], 'user_role_admin_3');
        [$target] = $this->createUserWithPermissions(['audit.read'], 'auditor_temp_3');
        $emptyRole = Role::create(['code' => 'no_perm_role_3', 'name' => 'No Perm Role 3', 'is_system' => false]);

        $targetToken = $target->createToken('access_token')->plainTextToken;

        // 1. Isi cache permission
        $this->withHeader('Authorization', 'Bearer '.$targetToken)
            ->getJson('/api/v1/audit-logs')
            ->assertStatus(200);
        $this->assertTrue(Cache::has("user_perm_cache:{$target->id}"));

        // 2. Ubah role melalui PUT /api/v1/users/{id}
        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/users/{$target->id}", [
                'roles' => [$emptyRole->code],
            ])
            ->assertStatus(200);
        $this->assertFalse(Cache::has("user_perm_cache:{$target->id}"));

        // 3. Permintaan berikutnya langsung ditolak 403
        $this->withHeader('Authorization', 'Bearer '.$targetToken)
            ->getJson('/api/v1/audit-logs')
            ->assertStatus(403);
    }
}
