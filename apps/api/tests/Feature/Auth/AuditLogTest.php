<?php

namespace Tests\Feature\Auth;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $regularUser;

    protected Role $adminRole;

    protected Role $teacherRole;

    protected Permission $permAuditRead;

    protected function setUp(): void
    {
        parent::setUp();

        $this->permAuditRead = Permission::create([
            'code' => 'audit.read',
            'description' => 'Melihat catatan log audit keamanan',
        ]);

        $this->adminRole = Role::create([
            'code' => 'super_admin',
            'name' => 'Super Administrator',
            'is_system' => true,
        ]);
        $this->adminRole->permissions()->attach([$this->permAuditRead->id]);

        $this->teacherRole = Role::create([
            'code' => 'teacher',
            'name' => 'Guru',
            'is_system' => true,
        ]);

        $this->adminUser = User::create([
            'username' => 'auditor_admin',
            'email' => 'auditor@labschool.sch.id',
            'password_hash' => Hash::make('AdminSecret123!'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($this->adminRole->id);

        $this->regularUser = User::create([
            'username' => 'regular_user',
            'email' => 'regular@labschool.sch.id',
            'password_hash' => Hash::make('RegularSecret123!'),
            'status' => 'active',
        ]);
        $this->regularUser->roles()->attach($this->teacherRole->id);
    }

    /**
     * BE-AUTH-05: List audit logs paginated
     */
    public function test_be_auth_05_list_audit_logs_paginated(): void
    {
        AuditLog::create([
            'actor_id' => $this->adminUser->id,
            'action' => 'auth.login',
            'entity' => 'User',
            'entity_id' => $this->adminUser->id,
            'ip' => '127.0.0.1',
        ]);

        AuditLog::create([
            'actor_id' => $this->adminUser->id,
            'action' => 'role.created',
            'entity' => 'Role',
            'ip' => '127.0.0.1',
        ]);

        $token = $this->adminUser->createToken('admin_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/audit-logs');

        $response->assertStatus(200)
            ->assertHeader('X-Request-Id')
            ->assertJsonPath('meta.page', 1)
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id', 'action', 'entity', 'entity_id', 'actor', 'created_at',
                    ],
                ],
                'meta' => ['page', 'limit', 'total', 'totalPages'],
            ]);
    }

    /**
     * BE-AUTH-05: Filter audit logs by action
     */
    public function test_be_auth_05_list_audit_logs_filtered_by_action(): void
    {
        AuditLog::create([
            'actor_id' => $this->adminUser->id,
            'action' => 'auth.login',
            'entity' => 'User',
        ]);

        AuditLog::create([
            'actor_id' => $this->adminUser->id,
            'action' => 'role.created',
            'entity' => 'Role',
        ]);

        $token = $this->adminUser->createToken('admin_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/audit-logs?action=auth.login');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.action', 'auth.login');
    }

    /**
     * BE-AUTH-05: Filter audit logs by entity and actor_id
     */
    public function test_be_auth_05_list_audit_logs_filtered_by_entity_and_actor(): void
    {
        AuditLog::create([
            'actor_id' => $this->adminUser->id,
            'action' => 'user.updated',
            'entity' => 'User',
            'entity_id' => $this->regularUser->id,
        ]);

        AuditLog::create([
            'actor_id' => $this->regularUser->id,
            'action' => 'grade.updated',
            'entity' => 'Grade',
        ]);

        $token = $this->adminUser->createToken('admin_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/audit-logs?actor_id='.$this->adminUser->id.'&entity=User');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.entity', 'User');
    }

    /**
     * BE-AUTH-05: Filter audit logs by date range
     */
    public function test_be_auth_05_list_audit_logs_filtered_by_date_range(): void
    {
        $logPast = AuditLog::create([
            'actor_id' => $this->adminUser->id,
            'action' => 'auth.login',
            'entity' => 'User',
            'created_at' => now()->subDays(5),
        ]);

        $logRecent = AuditLog::create([
            'actor_id' => $this->adminUser->id,
            'action' => 'auth.login',
            'entity' => 'User',
            'created_at' => now(),
        ]);

        $token = $this->adminUser->createToken('admin_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/audit-logs?start_date='.now()->subDays(2)->toIso8601String());

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $logRecent->id);
    }

    /**
     * BE-AUTH-05: Search audit logs with text query 'q'
     */
    public function test_be_auth_05_search_audit_logs_by_query(): void
    {
        AuditLog::create([
            'actor_id' => $this->adminUser->id,
            'action' => 'role.created',
            'entity' => 'Role',
        ]);

        AuditLog::create([
            'actor_id' => $this->regularUser->id,
            'action' => 'auth.login',
            'entity' => 'User',
        ]);

        $token = $this->adminUser->createToken('admin_token')->plainTextToken;

        // Search actor username
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/audit-logs?q=regular_user');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.actor.username', 'regular_user');
    }

    /**
     * BE-AUTH-05: Get single audit log detail
     */
    public function test_be_auth_05_get_single_audit_log_detail(): void
    {
        $log = AuditLog::create([
            'actor_id' => $this->adminUser->id,
            'action' => 'user.roles_assigned',
            'entity' => 'User',
            'entity_id' => $this->regularUser->id,
            'old_value' => ['roles' => ['teacher']],
            'new_value' => ['roles' => ['teacher', 'homeroom']],
            'ip' => '192.168.1.50',
            'user_agent' => 'Mozilla/5.0 SISTLABUM App',
        ]);

        $token = $this->adminUser->createToken('admin_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/audit-logs/'.$log->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $log->id)
            ->assertJsonPath('data.action', 'user.roles_assigned')
            ->assertJsonPath('data.old_value.roles', ['teacher'])
            ->assertJsonPath('data.new_value.roles', ['teacher', 'homeroom'])
            ->assertJsonPath('data.ip', '192.168.1.50');
    }

    /**
     * BE-AUTH-05: Get single audit log returns 404 if not found
     */
    public function test_be_auth_05_get_single_audit_log_not_found_returns_404(): void
    {
        $token = $this->adminUser->createToken('admin_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/audit-logs/01928374-abcd-7000-8000-000000000999');

        $response->assertStatus(404)
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }

    /**
     * BE-AUTH-05: Access denied with 403 when user lacks 'audit.read' permission
     */
    public function test_be_auth_05_access_denied_with_403_when_lacking_permission(): void
    {
        $teacherToken = $this->regularUser->createToken('teacher_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$teacherToken)
            ->getJson('/api/v1/audit-logs');

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN')
            ->assertJsonPath('error.message', 'Anda tidak memiliki izin untuk mengakses resource ini');
    }

    /**
     * BE-AUTH-05: Unauthenticated request rejected with 401
     */
    public function test_be_auth_05_unauthenticated_request_rejected_with_401(): void
    {
        $this->getJson('/api/v1/audit-logs')->assertStatus(401);
        $this->getJson('/api/v1/audit-logs/some-id')->assertStatus(401);
    }
}
