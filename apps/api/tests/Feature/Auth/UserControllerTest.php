<?php

namespace Tests\Feature\Auth;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $regularUser;

    protected Role $adminRole;

    protected Role $teacherRole;

    protected Permission $permReadUser;

    protected Permission $permWriteUser;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Permissions
        $this->permReadUser = Permission::create([
            'code' => 'auth.user.read',
            'description' => 'Melihat data pengguna',
        ]);

        $this->permWriteUser = Permission::create([
            'code' => 'auth.user.write',
            'description' => 'Mengelola akun pengguna',
        ]);

        // 2. Roles
        $this->adminRole = Role::create([
            'code' => 'super_admin',
            'name' => 'Super Administrator',
            'is_system' => true,
        ]);
        $this->adminRole->permissions()->attach([
            $this->permReadUser->id,
            $this->permWriteUser->id,
        ]);

        $this->teacherRole = Role::create([
            'code' => 'teacher',
            'name' => 'Guru',
            'is_system' => true,
        ]);

        // 3. Users
        $this->adminUser = User::create([
            'username' => 'admin_sist',
            'email' => 'admin@labschool.sch.id',
            'password_hash' => Hash::make('AdminPass123!'),
            'status' => 'active',
            'must_change_password' => false,
        ]);
        $this->adminUser->roles()->attach($this->adminRole->id);

        $this->regularUser = User::create([
            'username' => 'guru_budi',
            'email' => 'budi@labschool.sch.id',
            'password_hash' => Hash::make('TeacherPass123!'),
            'status' => 'active',
            'must_change_password' => false,
        ]);
        $this->regularUser->roles()->attach($this->teacherRole->id);
    }

    /**
     * Test 1: Unauthenticated request rejected with 401
     */
    public function test_unauthenticated_request_rejected_with_401(): void
    {
        $response = $this->getJson('/api/v1/users');

        $response->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    /**
     * Test 2: User without permission rejected with 403
     */
    public function test_user_without_permission_rejected_with_403(): void
    {
        $token = $this->regularUser->createToken('token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/users');

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    /**
     * Test 3: Admin with permission can list users
     */
    public function test_admin_with_permission_can_list_users(): void
    {
        $token = $this->adminUser->createToken('admin_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/users');

        $response->assertStatus(200)
            ->assertHeader('X-Request-Id')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'username', 'email', 'status', 'roles'],
                ],
                'meta' => ['page', 'limit', 'total', 'totalPages'],
            ])
            ->assertJsonCount(2, 'data');
    }

    /**
     * Test 4: List users filters by role, status, and search query
     */
    public function test_list_users_filters_by_role_status_and_search(): void
    {
        $token = $this->adminUser->createToken('admin_token')->plainTextToken;

        // Filter by role
        $resRole = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/users?role=teacher');
        $resRole->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.username', 'guru_budi');

        // Filter by search query
        $resSearch = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/users?q=admin');
        $resSearch->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.username', 'admin_sist');
    }

    /**
     * Test 5: Get user detail returns full data
     */
    public function test_get_user_detail_success(): void
    {
        $token = $this->adminUser->createToken('admin_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/users/'.$this->regularUser->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $this->regularUser->id)
            ->assertJsonPath('data.username', 'guru_budi')
            ->assertJsonPath('data.email', 'budi@labschool.sch.id')
            ->assertJsonCount(1, 'data.roles');
    }

    /**
     * Test 6: Get user not found returns 404
     */
    public function test_get_user_not_found_returns_404(): void
    {
        $token = $this->adminUser->createToken('admin_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/users/00000000-0000-0000-0000-000000000000');

        $response->assertStatus(404)
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }

    /**
     * Test 7: Create user success with roles and audit log
     */
    public function test_create_user_success_with_roles_and_audit_log(): void
    {
        $token = $this->adminUser->createToken('admin_token')->plainTextToken;

        $payload = [
            'username' => 'staff_tu',
            'email' => 'tu@labschool.sch.id',
            'phone' => '081299998888',
            'password' => 'StaffPass123!',
            'status' => 'active',
            'roles' => ['teacher'],
        ];

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/users', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.user.username', 'staff_tu')
            ->assertJsonPath('data.user.email', 'tu@labschool.sch.id')
            ->assertJsonCount(1, 'data.user.roles');

        $this->assertDatabaseHas('users', [
            'username' => 'staff_tu',
            'email' => 'tu@labschool.sch.id',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'action' => 'user.created',
            'entity' => 'User',
        ]);
    }

    /**
     * Test 8: Create user fails with duplicate username or email
     */
    public function test_create_user_fails_with_duplicate_username_or_email(): void
    {
        $token = $this->adminUser->createToken('admin_token')->plainTextToken;

        // Duplicate username
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/users', [
                'username' => 'admin_sist',
                'email' => 'new_admin@labschool.sch.id',
            ]);

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    /**
     * Test 9: Update user updates fields and writes audit log
     */
    public function test_update_user_success_with_audit_log(): void
    {
        $token = $this->adminUser->createToken('admin_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/v1/users/'.$this->regularUser->id, [
                'phone' => '081122334455',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.phone', '081122334455');

        $this->assertDatabaseHas('users', [
            'id' => $this->regularUser->id,
            'phone' => '081122334455',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'action' => 'user.updated',
            'entity' => 'User',
            'entity_id' => $this->regularUser->id,
        ]);
    }

    /**
     * Test 10: Change status suspends user, writes audit log, and revokes tokens
     */
    public function test_change_status_suspends_user_and_revokes_tokens(): void
    {
        $adminToken = $this->adminUser->createToken('admin_token')->plainTextToken;
        $userSessionToken = $this->regularUser->createToken('session_token')->plainTextToken;

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $this->regularUser->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$adminToken)
            ->patchJson('/api/v1/users/'.$this->regularUser->id.'/status', [
                'status' => 'suspended',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'suspended');

        $this->assertDatabaseHas('users', [
            'id' => $this->regularUser->id,
            'status' => 'suspended',
        ]);

        // Verify tokens were deleted
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $this->regularUser->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'action' => 'user.status_changed',
            'entity' => 'User',
            'entity_id' => $this->regularUser->id,
        ]);
    }

    /**
     * Test 11: Change status prevents self-deactivation
     */
    public function test_change_status_prevents_self_deactivation(): void
    {
        $adminToken = $this->adminUser->createToken('admin_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$adminToken)
            ->patchJson('/api/v1/users/'.$this->adminUser->id.'/status', [
                'status' => 'suspended',
            ]);

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'BUSINESS_RULE_VIOLATION');
    }

    /**
     * Test 12: Admin reset password succeeds, revokes sessions, and sets must_change_password
     */
    public function test_admin_reset_password_success_and_revokes_sessions(): void
    {
        $adminToken = $this->adminUser->createToken('admin_token')->plainTextToken;
        $this->regularUser->createToken('existing_token');

        $response = $this->withHeader('Authorization', 'Bearer '.$adminToken)
            ->postJson('/api/v1/users/'.$this->regularUser->id.'/reset-password', [
                'must_change_password' => true,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.must_change_password', true)
            ->assertJsonStructure(['data' => ['message', 'temporary_password']]);

        // Tokens revoked
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $this->regularUser->id,
        ]);

        $this->regularUser->refresh();
        $this->assertTrue($this->regularUser->must_change_password);

        // Audit log created without leaking password
        $audit = AuditLog::where('action', 'user.password_reset')->first();
        $this->assertNotNull($audit);
        $this->assertArrayNotHasKey('password', (array) $audit->new_value);
    }
}
