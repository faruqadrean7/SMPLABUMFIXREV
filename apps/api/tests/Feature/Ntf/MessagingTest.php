<?php

namespace Tests\Feature\Ntf;

use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MessagingTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $studentUser;

    protected NotificationTemplate $waTemplate;

    protected NotificationTemplate $emailTemplate;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Admin', 'is_system' => true]);
        $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);

        $permBroadcast = Permission::firstOrCreate(
            ['code' => 'notification.broadcast'],
            ['description' => 'Kirim broadcast pengumuman dan notifikasi']
        );
        $roleAdmin->permissions()->syncWithoutDetaching([$permBroadcast->id]);

        $this->adminUser = User::create([
            'username' => 'admin_msg',
            'email' => 'admin_msg@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($roleAdmin->id);

        $this->studentUser = User::create([
            'username' => 'siswa_msg',
            'email' => 'siswa_msg@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->studentUser->roles()->attach($roleStudent->id);

        $this->waTemplate = NotificationTemplate::create([
            'code' => 'WA_WELCOME',
            'name' => 'Sambutan Siswa Baru',
            'channel' => 'whatsapp',
            'template_text' => 'Halo {name}, selamat datang di {school_name}!',
            'variables' => ['name', 'school_name'],
            'is_active' => true,
        ]);

        $this->emailTemplate = NotificationTemplate::create([
            'code' => 'EMAIL_REPORT',
            'name' => 'Penerbitan Rapor Siswa',
            'channel' => 'email',
            'subject' => 'Laporan Hasil Belajar - {semester}',
            'template_text' => 'Yth. Orang Tua dari {student_name}, rapor semester {semester} telah dapat diunduh.',
            'variables' => ['student_name', 'semester'],
            'is_active' => true,
        ]);
    }

    public function test_be_ntf_02_unauthenticated_request_returns_401(): void
    {
        $res1 = $this->getJson('/api/v1/notification-templates');
        $res1->assertStatus(401);

        $res2 = $this->getJson('/api/v1/notification-logs');
        $res2->assertStatus(401);
    }

    public function test_be_ntf_02_staff_can_create_update_and_delete_template(): void
    {
        Sanctum::actingAs($this->adminUser);

        // 1. Create
        $payload = [
            'code' => 'wa_payment_reminder',
            'name' => 'Pengingat Pembayaran SPP',
            'channel' => 'whatsapp',
            'template_text' => 'Yth {parent_name}, tagihan SPP bulan {month} sebesar {amount} telah terbit.',
            'variables' => ['parent_name', 'month', 'amount'],
            'is_active' => true,
        ];

        $createRes = $this->postJson('/api/v1/notification-templates', $payload);
        $createRes->assertStatus(201)
            ->assertJsonPath('data.code', 'WA_PAYMENT_REMINDER')
            ->assertJsonPath('data.name', 'Pengingat Pembayaran SPP')
            ->assertJsonPath('data.channel', 'whatsapp');

        $templateId = $createRes->json('data.id');
        $this->assertDatabaseHas('notification_templates', [
            'id' => $templateId,
            'code' => 'WA_PAYMENT_REMINDER',
        ]);

        // 2. Show Detail
        $showRes = $this->getJson('/api/v1/notification-templates/'.$templateId);
        $showRes->assertStatus(200)
            ->assertJsonPath('data.id', $templateId);

        // 3. Update
        $updateRes = $this->putJson('/api/v1/notification-templates/'.$templateId, [
            'name' => 'Pengingat Tagihan SPP Bulanan',
            'is_active' => false,
        ]);
        $updateRes->assertStatus(200)
            ->assertJsonPath('data.name', 'Pengingat Tagihan SPP Bulanan')
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('notification_templates', [
            'id' => $templateId,
            'name' => 'Pengingat Tagihan SPP Bulanan',
            'is_active' => 0,
        ]);

        // 4. Delete
        $delRes = $this->deleteJson('/api/v1/notification-templates/'.$templateId);
        $delRes->assertStatus(200);

        $this->assertDatabaseMissing('notification_templates', [
            'id' => $templateId,
        ]);
    }

    public function test_be_ntf_02_regular_student_cannot_manage_templates(): void
    {
        Sanctum::actingAs($this->studentUser);

        // Attempt Create
        $resCreate = $this->postJson('/api/v1/notification-templates', [
            'code' => 'UNAUTHORIZED_CODE',
            'name' => 'Template Siswa',
            'channel' => 'whatsapp',
            'template_text' => 'Halo {name}',
        ]);
        $resCreate->assertStatus(403);

        // Attempt Update
        $resUpdate = $this->putJson('/api/v1/notification-templates/'.$this->waTemplate->id, [
            'name' => 'Hacked Name',
        ]);
        $resUpdate->assertStatus(403);

        // Attempt Delete
        $resDelete = $this->deleteJson('/api/v1/notification-templates/'.$this->waTemplate->id);
        $resDelete->assertStatus(403);

        // Attempt Send
        $resSend = $this->postJson('/api/v1/notification-templates/send', [
            'template_id' => $this->waTemplate->id,
            'recipient' => '081234567890',
        ]);
        $resSend->assertStatus(403);
    }

    public function test_be_ntf_02_filter_templates_by_channel_and_search(): void
    {
        Sanctum::actingAs($this->adminUser);

        // Filter WhatsApp channel
        $resWa = $this->getJson('/api/v1/notification-templates?channel=whatsapp');
        $resWa->assertStatus(200);
        $this->assertTrue(collect($resWa->json('data'))->every(fn ($item) => $item['channel'] === 'whatsapp'));

        // Filter Email channel
        $resEmail = $this->getJson('/api/v1/notification-templates?channel=email');
        $resEmail->assertStatus(200);
        $this->assertTrue(collect($resEmail->json('data'))->every(fn ($item) => $item['channel'] === 'email'));

        // Search by keyword
        $resSearch = $this->getJson('/api/v1/notification-templates?search=rapor');
        $resSearch->assertStatus(200);
        $data = $resSearch->json('data');
        $this->assertNotEmpty($data);
        $this->assertEquals('EMAIL_REPORT', $data[0]['code']);
    }

    public function test_be_ntf_02_send_whatsapp_notification_with_template_substitution(): void
    {
        Sanctum::actingAs($this->adminUser);

        $payload = [
            'template_id' => $this->waTemplate->id,
            'recipient' => '08123456789',
            'params' => [
                'name' => 'Budi Santoso',
                'school_name' => 'SMA Laboratorium UM',
            ],
        ];

        $res = $this->postJson('/api/v1/notification-templates/send', $payload);
        $res->assertStatus(201)
            ->assertJsonPath('data.channel', 'whatsapp')
            ->assertJsonPath('data.recipient', '08123456789')
            ->assertJsonPath('data.status', 'sent')
            ->assertJsonPath('data.body', 'Halo Budi Santoso, selamat datang di SMA Laboratorium UM!');

        $logId = $res->json('data.id');
        $this->assertDatabaseHas('notification_logs', [
            'id' => $logId,
            'status' => 'sent',
            'recipient' => '08123456789',
            'body' => 'Halo Budi Santoso, selamat datang di SMA Laboratorium UM!',
        ]);
    }

    public function test_be_ntf_02_send_email_notification_with_template_substitution(): void
    {
        Sanctum::actingAs($this->adminUser);

        $payload = [
            'template_id' => $this->emailTemplate->id,
            'recipient' => 'orangtua.siswa@gmail.com',
            'params' => [
                'student_name' => 'Ahmad Fauzi',
                'semester' => 'Ganjil 2026/2027',
            ],
        ];

        $res = $this->postJson('/api/v1/notification-templates/send', $payload);
        $res->assertStatus(201)
            ->assertJsonPath('data.channel', 'email')
            ->assertJsonPath('data.recipient', 'orangtua.siswa@gmail.com')
            ->assertJsonPath('data.subject', 'Laporan Hasil Belajar - Ganjil 2026/2027')
            ->assertJsonPath('data.body', 'Yth. Orang Tua dari Ahmad Fauzi, rapor semester Ganjil 2026/2027 telah dapat diunduh.')
            ->assertJsonPath('data.status', 'sent');

        $this->assertDatabaseHas('notification_logs', [
            'id' => $res->json('data.id'),
            'status' => 'sent',
            'subject' => 'Laporan Hasil Belajar - Ganjil 2026/2027',
        ]);
    }

    public function test_be_ntf_02_cannot_send_notification_using_inactive_template(): void
    {
        Sanctum::actingAs($this->adminUser);

        $this->waTemplate->update(['is_active' => false]);

        $res = $this->postJson('/api/v1/notification-templates/send', [
            'template_id' => $this->waTemplate->id,
            'recipient' => '08123456789',
            'params' => ['name' => 'Budi'],
        ]);

        $res->assertStatus(422)
            ->assertJsonPath('error.message', 'Template notifikasi sedang tidak aktif.');
    }

    public function test_be_ntf_02_failed_notification_is_logged_with_error(): void
    {
        Sanctum::actingAs($this->adminUser);

        // Recipient containing 'fail' triggers simulated gateway error in MessagingService
        $payload = [
            'template_id' => $this->waTemplate->id,
            'recipient' => '0812fail99999',
            'params' => [
                'name' => 'Simulasi Gagal',
                'school_name' => 'SMA Lab UM',
            ],
        ];

        $res = $this->postJson('/api/v1/notification-templates/send', $payload);
        $res->assertStatus(201)
            ->assertJsonPath('data.status', 'failed')
            ->assertJsonPath('data.error', 'Provider error: Invalid recipient destination or network gateway timeout.');

        $this->assertDatabaseHas('notification_logs', [
            'id' => $res->json('data.id'),
            'status' => 'failed',
            'recipient' => '0812fail99999',
        ]);
    }

    public function test_be_ntf_02_staff_can_retry_failed_notification(): void
    {
        Sanctum::actingAs($this->adminUser);

        // Create a failed notification log with an initially failed recipient
        $log = NotificationLog::create([
            'template_id' => $this->waTemplate->id,
            'channel' => 'whatsapp',
            'recipient' => '0812fail99999',
            'subject' => null,
            'body' => 'Pesan percobaan retry',
            'status' => 'failed',
            'retry_count' => 0,
            'error' => 'Simulated gateway timeout',
        ]);

        // Change recipient to valid phone to succeed on retry
        $log->recipient = '081299988877';
        $log->save();

        $res = $this->postJson('/api/v1/notification-logs/'.$log->id.'/retry');
        $res->assertStatus(200)
            ->assertJsonPath('data.status', 'sent')
            ->assertJsonPath('data.retry_count', 1);

        $this->assertDatabaseHas('notification_logs', [
            'id' => $log->id,
            'status' => 'sent',
            'retry_count' => 1,
            'error' => null,
        ]);
    }

    public function test_be_ntf_02_cannot_retry_already_sent_notification(): void
    {
        Sanctum::actingAs($this->adminUser);

        $log = NotificationLog::create([
            'template_id' => $this->waTemplate->id,
            'channel' => 'whatsapp',
            'recipient' => '08123456789',
            'subject' => null,
            'body' => 'Sudah terkirim sebelumnya',
            'status' => 'sent',
            'retry_count' => 0,
        ]);

        $res = $this->postJson('/api/v1/notification-logs/'.$log->id.'/retry');
        $res->assertStatus(422)
            ->assertJsonPath('error.message', "Hanya notifikasi dengan status 'failed' yang dapat dikirim ulang.");
    }

    public function test_be_ntf_02_filter_logs_by_channel_and_status(): void
    {
        Sanctum::actingAs($this->adminUser);

        NotificationLog::create([
            'channel' => 'whatsapp',
            'recipient' => '0811111111',
            'status' => 'sent',
            'body' => 'WA Sent',
        ]);

        NotificationLog::create([
            'channel' => 'email',
            'recipient' => 'user@domain.com',
            'status' => 'failed',
            'body' => 'Email Failed',
            'error' => 'Connection refused',
        ]);

        // Filter WA
        $resWa = $this->getJson('/api/v1/notification-logs?channel=whatsapp');
        $resWa->assertStatus(200);
        $this->assertTrue(collect($resWa->json('data'))->every(fn ($item) => $item['channel'] === 'whatsapp'));

        // Filter status=failed
        $resFailed = $this->getJson('/api/v1/notification-logs?status=failed');
        $resFailed->assertStatus(200);
        $this->assertTrue(collect($resFailed->json('data'))->every(fn ($item) => $item['status'] === 'failed'));

        // Show single log
        $firstLogId = $resFailed->json('data.0.id');
        $resShow = $this->getJson('/api/v1/notification-logs/'.$firstLogId);
        $resShow->assertStatus(200)
            ->assertJsonPath('data.id', $firstLogId);
    }

    public function test_be_ntf_02_validation_errors_on_invalid_recipient_format(): void
    {
        Sanctum::actingAs($this->adminUser);

        // Invalid WA number (< 8 digits)
        $resWa = $this->postJson('/api/v1/notification-templates/send', [
            'template_id' => $this->waTemplate->id,
            'recipient' => '123',
            'params' => ['name' => 'Test'],
        ]);
        $resWa->assertStatus(422);

        // Invalid Email format
        $resEmail = $this->postJson('/api/v1/notification-templates/send', [
            'template_id' => $this->emailTemplate->id,
            'recipient' => 'not-an-email',
            'params' => ['student_name' => 'Test', 'semester' => '1'],
        ]);
        $resEmail->assertStatus(422);
    }

    public function test_be_ntf_02_cannot_create_template_with_duplicate_code(): void
    {
        Sanctum::actingAs($this->adminUser);

        $res = $this->postJson('/api/v1/notification-templates', [
            'code' => 'wa_welcome', // will be converted to uppercase WA_WELCOME, which already exists
            'name' => 'Duplikat WA Welcome',
            'channel' => 'whatsapp',
            'template_text' => 'Halo {name}',
        ]);

        $res->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }
}
