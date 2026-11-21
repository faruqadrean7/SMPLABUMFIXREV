<?php

namespace Tests\Feature\Auth;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RbacTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $regularUser;

    protected Role $adminRole;

    protected Role $teacherRole;

    protected Permission $permManageRole;

    protected Permission $permReadStudent;

    protected function setUp(): void
    {
        parent::setUp();

        // Permissions
        $this->permManageRole = Permission::create([
            'code' => 'auth.role.manage',
            'description' => 'Mengelola role dan hak akses',
        ]);
        $this->permReadStudent = Permission::create([
            'code' => 'master.student.read',
            'description' => 'Melihat data siswa',
        ]);

        // Roles
        $this->adminRole = Role::create([
            'code' => 'super_admin',
            'name' => 'Super Administrator',
            'is_system' => true,
        ]);
        $this->adminRole->permissions()->attach([$this->permManageRole->id]);

        $this->teacherRole = Role::create([
            'code' => 'teacher',
            'name' => 'Guru Mata Pelajaran',
            'is_system' => true,
        ]);

        // Users
        $this->adminUser = User::create([
            'username' => 'admin_user',
            'email' => 'admin@labschool.sch.id',
            'password_hash' => Hash::make('AdminSecret123!'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($this->adminRole->id);

        $this->regularUser = User::create([
            'username' => 'regular_teacher',
            'email' => 'teacher@labschool.sch.id',
            'password_hash' => Hash::make('TeacherSecret123!'),
            'status' => 'active',
        ]);
        $this->regularUser->roles()->attach($this->teacherRole->id);
    }

    /**
     * BE-AUTH-03: List Master Permissions
     */
    public function test_be_auth_03_list_permissions_success(): void
    {
        $token = $this->adminUser->createToken('admin_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/permissions');

        $response->assertStatus(200)
            ->assertHeader('X-Request-Id')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'code', 'description'],
                ],
            ]);
    }

    /**
     * BE-AUTH-03: List Roles with pagination and search
     */
    public function test_be_auth_03_list_roles_with_pagination_and_search(): void
    {
        $token = $this->adminUser->createToken('admin_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/roles?q=Guru');

        $response->assertStatus(200)
            ->assertJsonPath('meta.page', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'teacher');
    }

    /**
     * BE-AUTH-03: Create a custom role and assign permissions
     */
    public function test_be_auth_03_create_custom_role_with_permissions(): void
    {
        $token = $this->adminUser->createToken('admin_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/roles', [
                'code' => 'lab_coordinator',
                'name' => 'Koordinator Laboratorium Komputer',
                'permission_ids' => [$this->permReadStudent->id],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.code', 'lab_coordinator')
            ->assertJsonPath('data.name', 'Koordinator Laboratorium Komputer')
            ->assertJsonPath('data.is_system', false)
            ->assertJsonCount(1, 'data.permissions');

        $this->assertDatabaseHas('roles', [
            'code' => 'lab_coordinator',
            'is_system' => false,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'action' => 'role.created',
            'entity' => 'Role',
        ]);
    }

    /**
     * BE-AUTH-03: Create role fails with duplicate code
     */
    public function test_be_auth_03_create_role_fails_with_duplicate_code(): void
    {
        $token = $this->adminUser->createToken('admin_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/roles', [
                'code' => 'teacher', // existing
                'name' => 'Guru Duplikat',
            ]);

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    /**
     * BE-AUTH-03: Get role detail by ID
     */
    public function test_be_auth_03_get_role_detail_by_id(): void
    {
        $token = $this->adminUser->createToken('admin_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/roles/'.$this->adminRole->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.code', 'super_admin')
            ->assertJsonPath('data.name', 'Super Administrator');
    }

    /**
     * BE-AUTH-03: Update role name and permissions
     */
    public function test_be_auth_03_update_role_name_and_permissions(): void
    {
        $customRole = Role::create([
            'code' => 'curriculum_staff',
            'name' => 'Staf Kurikulum',
            'is_system' => false,
        ]);

        $token = $this->adminUser->createToken('admin_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/v1/roles/'.$customRole->id, [
                'name' => 'Tim Pengembang Kurikulum',
                'permission_ids' => [$this->permReadStudent->id],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Tim Pengembang Kurikulum')
            ->assertJsonCount(1, 'data.permissions');

        $this->assertDatabaseHas('roles', [
            'id' => $customRole->id,
            'name' => 'Tim Pengembang Kurikulum',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'action' => 'role.updated',
        ]);
    }

    /**
     * BE-AUTH-03: Delete a custom role
     */
    public function test_be_auth_03_delete_custom_role_success(): void
    {
        $customRole = Role::create([
            'code' => 'temporary_committee',
            'name' => 'Panitia Sementara',
            'is_system' => false,
        ]);

        $token = $this->adminUser->createToken('admin_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/v1/roles/'.$customRole->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.message', 'Role berhasil dihapus');

        $this->assertDatabaseMissing('roles', [
            'id' => $customRole->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'action' => 'role.deleted',
        ]);
    }

    /**
     * BE-AUTH-03: System role cannot be deleted (Business rule violation)
     */
    public function test_be_auth_03_delete_system_role_fails_with_business_rule_violation(): void
    {
        $token = $this->adminUser->createToken('admin_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/v1/roles/'.$this->teacherRole->id);

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'BUSINESS_RULE_VIOLATION')
            ->assertJsonPath('error.message', 'Role bawaan sistem tidak dapat dihapus demi menjaga integritas aplikasi.');

        $this->assertDatabaseHas('roles', [
            'id' => $this->teacherRole->id,
        ]);
    }

    /**
     * BE-AUTH-03: Assign multiple roles to a user (with optional scope)
     */
    public function test_be_auth_03_assign_multi_roles_to_user_with_scope(): void
    {
        $homeroomRole = Role::create([
            'code' => 'homeroom',
            'name' => 'Wali Kelas',
            'is_system' => true,
        ]);

        $dummyClassId = '01928374-abcd-7000-8000-000000000001';

        $token = $this->adminUser->createToken('admin_token')->plainTextToken;

        // Assign both teacher and homeroom roles
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/v1/users/'.$this->regularUser->id.'/roles', [
                'roles' => [
                    [
                        'role_id' => $this->teacherRole->id,
                        'scope_class_id' => null,
                    ],
                    [
                        'role_id' => $homeroomRole->id,
                        'scope_class_id' => $dummyClassId,
                    ],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.message', 'Peran pengguna berhasil diperbarui')
            ->assertJsonCount(2, 'data.user.roles');

        $this->assertDatabaseHas('user_roles', [
            'user_id' => $this->regularUser->id,
            'role_id' => $homeroomRole->id,
            'scope_class_id' => $dummyClassId,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'action' => 'user.roles_assigned',
        ]);

        // Verify GET /users/{id}/roles
        $getRes = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/users/'.$this->regularUser->id.'/roles');

        $getRes->assertStatus(200)
            ->assertJsonPath('data.username', 'regular_teacher')
            ->assertJsonCount(2, 'data.roles');
    }

    /**
     * BE-AUTH-03: Guard rejects with 403 Forbidden when user lacks permission
     */
    public function test_be_auth_03_guard_rejects_with_403_when_lacking_permission(): void
    {
        // regularUser only has 'teacher' role without 'auth.role.manage'
        $teacherToken = $this->regularUser->createToken('teacher_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$teacherToken)
            ->getJson('/api/v1/roles');

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN')
            ->assertJsonPath('error.message', 'Anda tidak memiliki izin untuk mengakses resource ini');
    }

    /**
     * BE-AUTH-03: Unauthenticated request rejected with 401
     */
    public function test_be_auth_03_unauthenticated_request_rejected_with_401(): void
    {
        $response = $this->getJson('/api/v1/roles');

        $response->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }
}
