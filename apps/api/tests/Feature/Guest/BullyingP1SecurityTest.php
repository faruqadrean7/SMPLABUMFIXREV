<?php

namespace Tests\Feature\Guest;

use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\Notification;
use App\Models\Permission;
use App\Models\Role;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BullyingP1SecurityTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $principalUser;

    protected User $counselorUser;

    protected User $teacherUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create([
            'id' => (string) Str::uuid(),
            'name' => 'SMP Laboratorium Malang',
            'npsn' => '20533001',
            'is_active' => true,
        ]);

        $rolePrincipal = Role::firstOrCreate(['code' => 'principal'], ['name' => 'Kepala Sekolah', 'is_system' => true]);
        $roleCounselor = Role::firstOrCreate(['code' => 'counselor'], ['name' => 'Guru BK', 'is_system' => true]);
        $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru Pengajar', 'is_system' => true]);

        $permRead = Permission::firstOrCreate(['code' => 'complaint.read'], ['description' => 'Melihat aduan']);
        $permManage = Permission::firstOrCreate(['code' => 'complaint.manage'], ['description' => 'Mengelola aduan']);

        $rolePrincipal->permissions()->syncWithoutDetaching([$permRead->id, $permManage->id]);
        $roleCounselor->permissions()->syncWithoutDetaching([$permRead->id, $permManage->id]);

        $this->principalUser = User::create([
            'username' => 'kepsek_p1',
            'email' => 'kepsek.p1@sekolah.sch.id',
            'password_hash' => bcrypt('password123'),
            'school_id' => $this->school->id,
            'status' => 'active',
        ]);
        $this->principalUser->roles()->attach($rolePrincipal->id);

        $this->counselorUser = User::create([
            'username' => 'gurubk_p1',
            'email' => 'bk.p1@sekolah.sch.id',
            'password_hash' => bcrypt('password123'),
            'school_id' => $this->school->id,
            'status' => 'active',
        ]);
        $this->counselorUser->roles()->attach($roleCounselor->id);

        $this->teacherUser = User::create([
            'username' => 'guru_p1',
            'email' => 'guru.p1@sekolah.sch.id',
            'password_hash' => bcrypt('password123'),
            'school_id' => $this->school->id,
            'status' => 'active',
        ]);
        $this->teacherUser->roles()->attach($roleTeacher->id);
    }

    /**
     * BE-REPORT-02 (AC1): Staf berwenang (Guru BK & Kepsek) membaca detail aduan
     * harus mencatat audit log pembacaan (complaint.read_detail) demi akuntabilitas privasi.
     */
    public function test_be_report_02_ac1_authorized_staff_reading_complaint_detail_triggers_read_audit(): void
    {
        $complaint = Complaint::create([
            'school_id' => $this->school->id,
            'reporter_user_id' => null,
            'ticket_code' => 'ASP-20261103-BULL',
            'category' => 'bullying',
            'content' => 'Terjadi perundungan verbal di area kantin sekolah.',
            'is_anonymous' => true,
            'status' => 'baru',
        ]);

        $this->actingAs($this->counselorUser);

        $response = $this->getJson("/api/v1/complaints/{$complaint->id}", [
            'User-Agent' => 'CounselorBrowser/1.0',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.ticket_code', 'ASP-20261103-BULL');

        // Pastikan tercatat di audit_logs
        $audit = AuditLog::where('action', 'complaint.read_detail')
            ->where('entity', 'Complaint')
            ->where('entity_id', $complaint->id)
            ->where('actor_id', $this->counselorUser->id)
            ->first();

        $this->assertNotNull($audit, 'Audit log complaint.read_detail harus dibuat saat staf berwenang membuka detail');
        $this->assertEquals('ASP-20261103-BULL', $audit->new_value['ticket_code']);
        $this->assertEquals('bullying', $audit->new_value['category']);
        $this->assertTrue($audit->new_value['is_anonymous']);
    }

    /**
     * BE-REPORT-02 (AC1): User tanpa wewenang (Guru umum) ditolak (403)
     * dan tidak meninggalkan audit log read_detail.
     */
    public function test_be_report_02_ac1_unauthorized_user_cannot_read_complaint_detail_and_no_read_audit(): void
    {
        $complaint = Complaint::create([
            'school_id' => $this->school->id,
            'reporter_user_id' => null,
            'ticket_code' => 'ASP-20261103-PRIV',
            'category' => 'bullying',
            'content' => 'Laporan rahasia perundungan.',
            'is_anonymous' => true,
            'status' => 'baru',
        ]);

        $this->actingAs($this->teacherUser);

        $response = $this->getJson("/api/v1/complaints/{$complaint->id}");

        $response->assertStatus(403);

        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'complaint.read_detail',
            'entity_id' => $complaint->id,
        ]);
    }

    /**
     * BE-REPORT-02 (AC2): Saat laporan baru kategori bullying masuk,
     * sistem mengirimkan notifikasi ringan ke Guru BK dan Kepala Sekolah,
     * TANPA membocorkan isi laporan atau identitas pelapor dalam notifikasi (doc 09 §6.7).
     */
    public function test_be_report_02_ac2_bullying_submission_triggers_lightweight_notification_without_leaking_content(): void
    {
        $payload = [
            'school_id' => $this->school->id,
            'category' => 'bullying',
            'content' => 'Terjadi pemalakan dan intimidasi oleh senior di lorong lantai 2.',
            'is_anonymous' => true,
        ];

        $response = $this->postJson('/api/v1/public/complaints', $payload);

        $response->assertStatus(201);
        $ticketCode = $response->json('data.ticket_code');
        $this->assertNotEmpty($ticketCode);

        // Periksa notifikasi untuk Guru BK
        $notifCounselor = Notification::where('user_id', $this->counselorUser->id)
            ->where('notif_type', 'complaint.bullying_alert')
            ->first();

        $this->assertNotNull($notifCounselor, 'Notifikasi bullying_alert harus dikirim ke Guru BK');
        $this->assertStringContainsString('Perundungan', $notifCounselor->title);
        $this->assertStringContainsString($ticketCode, $notifCounselor->body);

        // KEPATUHAN PRIVASI & KEAMANAN (09_SECURITY_PRIVACY.md §6.7):
        // Body dan data notifikasi DILARANG memuat uraian isi sensitif atau data pribadi!
        $this->assertStringNotContainsString('pemalakan', $notifCounselor->body);
        $this->assertStringNotContainsString('intimidasi', $notifCounselor->body);
        $this->assertStringNotContainsString('lorong', $notifCounselor->body);

        $this->assertEquals($ticketCode, $notifCounselor->data['ticket_code']);
        $this->assertEquals('bullying', $notifCounselor->data['category']);
        $this->assertArrayNotHasKey('content', $notifCounselor->data);

        // Periksa notifikasi juga sampai ke Kepala Sekolah
        $notifPrincipal = Notification::where('user_id', $this->principalUser->id)
            ->where('notif_type', 'complaint.bullying_alert')
            ->first();
        $this->assertNotNull($notifPrincipal, 'Notifikasi bullying_alert juga harus sampai ke Kepala Sekolah');

        // Pastikan guru biasa TIDAK menerima notifikasi aduan bullying
        $notifTeacher = Notification::where('user_id', $this->teacherUser->id)->first();
        $this->assertNull($notifTeacher, 'Guru pengajar umum tidak boleh menerima notifikasi aduan bullying');
    }

    /**
     * BE-REPORT-02: Laporan kategori non-bullying tidak memicu alert darurat bullying.
     */
    public function test_be_report_02_non_bullying_submission_does_not_trigger_bullying_alert(): void
    {
        $payload = [
            'school_id' => $this->school->id,
            'category' => 'fasilitas',
            'content' => 'Keran wastafel kamar mandi gedung A rusak dan air merembes.',
            'is_anonymous' => true,
        ];

        $response = $this->postJson('/api/v1/public/complaints', $payload);

        $response->assertStatus(201);

        $notif = Notification::where('notif_type', 'complaint.bullying_alert')->first();
        $this->assertNull($notif, 'Aduan non-bullying tidak boleh memicu notif_type complaint.bullying_alert');
    }
}
