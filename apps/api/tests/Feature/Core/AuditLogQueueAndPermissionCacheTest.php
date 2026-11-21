<?php

namespace Tests\Feature\Core;

use App\Jobs\RecordAuditLogJob;
use App\Models\Permission;
use App\Models\Role;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AuditLogQueueAndPermissionCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_be_perf_01_audit_log_is_dispatched_to_queue_job(): void
    {
        config(['audit.async' => true]);
        Queue::fake([RecordAuditLogJob::class]);

        $school = School::create([
            'name' => 'SMP Lab UM Queue Test',
            'is_active' => true,
        ]);

        Queue::assertPushed(RecordAuditLogJob::class, function ($job) use ($school) {
            return $job->data['entity'] === 'School'
                && $job->data['action'] === 'created'
                && $job->data['entity_id'] === $school->id;
        });

        // Verifikasi handler job menulis ke database audit_logs
        $job = new RecordAuditLogJob([
            'action' => 'created',
            'entity' => 'School',
            'entity_id' => $school->id,
            'new_value' => ['name' => 'SMP Lab UM Queue Test'],
        ]);
        $job->handle();

        $this->assertDatabaseHas('audit_logs', [
            'entity' => 'School',
            'action' => 'created',
            'entity_id' => $school->id,
        ]);
    }

    public function test_be_perf_02_require_permission_caches_user_permissions(): void
    {
        Cache::flush();

        $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);
        $permPoints = Permission::firstOrCreate(['code' => 'student.point.read'], ['name' => 'Lihat Poin Siswa']);
        $roleTeacher->permissions()->syncWithoutDetaching([$permPoints->id]);

        $user = User::create([
            'username' => 'guru_cached',
            'email' => 'guru_cached@labum.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $user->roles()->attach($roleTeacher->id);

        $token = $user->createToken('test_cache_token')->plainTextToken;

        $cacheKey = "user_perm_cache:{$user->id}";
        $this->assertFalse(Cache::has($cacheKey));

        // Panggil endpoint yang dilindungi RequirePermission
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/student-points');

        // Pastikan izin tersimpan di cache
        $this->assertTrue(Cache::has($cacheKey));
        $cached = Cache::get($cacheKey);
        $this->assertContains('teacher', $cached['role_codes']);
        $this->assertContains('student.point.read', $cached['permission_codes']);

        // Verifikasi flushPermissionCache() menghapus entri cache
        $user->flushPermissionCache();
        $this->assertFalse(Cache::has($cacheKey));
    }
}
