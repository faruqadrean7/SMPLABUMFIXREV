<?php

namespace Tests\Feature\Auth;

use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\File;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuditLogHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function createAuditorAdmin(): User
    {
        $role = Role::firstOrCreate(['code' => 'principal'], ['name' => 'Kepala Sekolah', 'is_system' => true]);
        foreach (['audit.read', 'complaint.read', 'complaint.manage'] as $code) {
            $perm = Permission::firstOrCreate(['code' => $code], ['description' => $code]);
            $role->permissions()->syncWithoutDetaching([$perm->id]);
        }

        $user = User::create([
            'username' => 'auditor_p1_09_'.uniqid(),
            'email' => 'auditor_p1_09_'.uniqid().'@test.local',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_p1_09_audit_log_date_filter_parses_iso8601_with_carbon_accurately(): void
    {
        $admin = $this->createAuditorAdmin();

        $oldLog = AuditLog::create([
            'actor_id' => $admin->id,
            'action' => 'p109.old_event',
            'entity' => 'Test',
            'ip' => '127.0.0.1',
            'created_at' => Carbon::parse('2026-01-10 08:00:00', 'UTC'),
        ]);

        $inWindowLog = AuditLog::create([
            'actor_id' => $admin->id,
            'action' => 'p109.in_window_event',
            'entity' => 'Test',
            'ip' => '127.0.0.1',
            'created_at' => Carbon::parse('2026-06-15 10:00:00', 'UTC'),
        ]);

        $futureLog = AuditLog::create([
            'actor_id' => $admin->id,
            'action' => 'p109.future_event',
            'entity' => 'Test',
            'ip' => '127.0.0.1',
            'created_at' => Carbon::parse('2026-12-20 10:00:00', 'UTC'),
        ]);

        // Gunakan format ISO-8601 dengan offset zona waktu (+07:00) yang sebelumnya gagal tanpa import Carbon
        $res = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/audit-logs?entity=Test&start_date='.urlencode('2026-06-01T00:00:00+07:00').'&end_date='.urlencode('2026-06-30T23:59:59+07:00'));

        $res->assertStatus(200);
        $ids = collect($res->json('data'))->pluck('id')->all();

        $this->assertContains($inWindowLog->id, $ids);
        $this->assertNotContains($oldLog->id, $ids);
        $this->assertNotContains($futureLog->id, $ids);
    }

    public function test_p1_09_signed_complaint_attachment_download_records_non_null_actor_id(): void
    {
        Storage::fake('local');
        $principal = $this->createAuditorAdmin();

        $complaint = Complaint::create([
            'ticket_code' => 'ASP-20261127-AABBCCDDEE',
            'category' => 'bullying',
            'content' => 'Laporan pengaduan uji atribusi unduhan lampiran',
            'is_anonymous' => true,
            'status' => 'baru',
        ]);

        $objectKey = 'complaints/evidence_p109.png';
        Storage::disk('local')->put($objectKey, "\x89PNG\r\n\x1a\n".str_repeat('A', 32));

        $file = File::create([
            'owner_type' => 'complaint',
            'owner_id' => $complaint->id,
            'bucket' => 'complaints',
            'object_key' => $objectKey,
            'mime_type' => 'image/png',
            'size_bytes' => 40,
            'visibility' => 'privat',
        ]);

        // 1. Kepala sekolah meminta detail pengaduan untuk memperoleh signed_url
        $detail = $this->actingAs($principal, 'sanctum')
            ->getJson("/api/v1/complaints/{$complaint->id}")
            ->assertStatus(200);

        $signedUrl = $detail->json('data.attachments.0.signed_url');
        $this->assertNotEmpty($signedUrl);
        $this->assertStringContainsString('uid='.$principal->id, $signedUrl);

        // 2. Browser membuka signed_url secara langsung tanpa Bearer token (seperti tag <img>/<a href>)
        $this->app['auth']->forgetGuards();
        $download = $this->get($signedUrl);
        $download->assertStatus(200);

        // 3. Audit log complaint.attachment.downloaded wajib mencatat actor_id = $principal->id
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'complaint.attachment.downloaded',
            'entity' => 'File',
            'entity_id' => $file->id,
            'actor_id' => $principal->id,
        ]);
    }
}
