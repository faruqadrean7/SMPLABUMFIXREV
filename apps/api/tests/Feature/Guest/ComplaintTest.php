<?php

namespace Tests\Feature\Guest;

use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ComplaintEvent;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ComplaintTest extends TestCase
{
    use RefreshDatabase;

    protected User $principalUser;

    protected User $counselorUser;

    protected User $teacherUser;

    protected User $reporterUser;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Roles & Permissions setup
        $rolePrincipal = Role::firstOrCreate(['code' => 'principal'], ['name' => 'Kepala Sekolah', 'is_system' => true]);
        $roleCounselor = Role::firstOrCreate(['code' => 'counselor'], ['name' => 'Guru BK', 'is_system' => true]);
        $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);

        $permRead = Permission::firstOrCreate(['code' => 'complaint.read'], ['description' => 'Melihat aduan']);
        $permManage = Permission::firstOrCreate(['code' => 'complaint.manage'], ['description' => 'Mengelola aduan']);

        $rolePrincipal->permissions()->syncWithoutDetaching([$permRead->id, $permManage->id]);
        $roleCounselor->permissions()->syncWithoutDetaching([$permRead->id, $permManage->id]);

        // 2. Principal User
        $this->principalUser = User::create([
            'username' => 'kepsek',
            'email' => 'kepsek@sekolah.sch.id',
            'password_hash' => bcrypt('password123'),
            'status' => 'active',
        ]);
        $this->principalUser->roles()->attach($rolePrincipal->id);

        // 3. Counselor User (Guru BK)
        $this->counselorUser = User::create([
            'username' => 'gurubk',
            'email' => 'bk@sekolah.sch.id',
            'password_hash' => bcrypt('password123'),
            'status' => 'active',
        ]);
        $this->counselorUser->roles()->attach($roleCounselor->id);

        // 4. Regular Teacher User (Unauthorized for complaints)
        $this->teacherUser = User::create([
            'username' => 'guruumum',
            'email' => 'guru@sekolah.sch.id',
            'password_hash' => bcrypt('password123'),
            'status' => 'active',
        ]);
        $this->teacherUser->roles()->attach($roleTeacher->id);

        // 5. Reporter User (Logged in user submitting non-anonymous complaint)
        $this->reporterUser = User::create([
            'username' => 'siswapelapor',
            'email' => 'siswa@sekolah.sch.id',
            'password_hash' => bcrypt('password123'),
            'status' => 'active',
        ]);
    }

    /**
     * BE-GUEST-02: Public complaint submission generates ticket code and timeline event.
     */
    public function test_be_guest_02_public_complaint_submission_generates_ticket_code(): void
    {
        $payload = [
            'category' => 'fasilitas',
            'content' => 'Kerusakan pada proyektor ruang lab komputer 2 sehingga pembelajaran terhambat.',
            'is_anonymous' => false,
        ];

        $response = $this->actingAs($this->reporterUser)
            ->postJson('/api/v1/public/complaints', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.category', 'fasilitas')
            ->assertJsonPath('data.status', 'baru')
            ->assertJsonPath('data.is_anonymous', false);

        $ticketCode = $response->json('data.ticket_code');
        $this->assertNotNull($ticketCode);
        $this->assertStringStartsWith('ASP-', $ticketCode);

        // Database assertions
        $this->assertDatabaseHas('complaints', [
            'ticket_code' => $ticketCode,
            'category' => 'fasilitas',
            'reporter_user_id' => $this->reporterUser->id,
            'is_anonymous' => false,
        ]);

        $complaint = Complaint::where('ticket_code', $ticketCode)->first();
        $this->assertDatabaseHas('complaint_events', [
            'complaint_id' => $complaint->id,
            'event_type' => 'created',
        ]);
    }

    /**
     * BE-GUEST-02: Anonymous complaint does not store IP address or user ID in DB and audit logs.
     */
    public function test_be_guest_02_anonymous_complaint_does_not_store_ip_or_user_id(): void
    {
        $payload = [
            'category' => 'pungli',
            'content' => 'Laporan dugaan pungutan liar pada kegiatan ekstrakurikuler yang tidak resmi.',
            'is_anonymous' => true,
        ];

        $response = $this->postJson('/api/v1/public/complaints', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.is_anonymous', true);

        $ticketCode = $response->json('data.ticket_code');
        $complaint = Complaint::where('ticket_code', $ticketCode)->first();

        // Whistleblower protection: reporter_user_id must be null
        $this->assertNull($complaint->reporter_user_id);
        $this->assertTrue($complaint->is_anonymous);

        // Audit log must NOT contain IP address or user ID
        $auditLog = AuditLog::where('entity', 'Complaint')
            ->where('entity_id', $complaint->id)
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertNull($auditLog->actor_id);
        $this->assertNull($auditLog->ip_address);
        $this->assertNull($auditLog->user_agent);
    }

    /**
     * BE-GUEST-02: Public can track complaint status by ticket code without login.
     */
    public function test_be_guest_02_public_can_track_complaint_status_by_ticket_code(): void
    {
        $complaint = Complaint::create([
            'ticket_code' => 'ASP-20261011-TEST',
            'category' => 'akademik',
            'content' => 'Keluhan jadwal remedial matematika bertabrakan dengan jadwal upacara.',
            'is_anonymous' => false,
            'reporter_user_id' => $this->reporterUser->id,
            'status' => 'ditinjau',
        ]);

        ComplaintEvent::create([
            'complaint_id' => $complaint->id,
            'actor_id' => null,
            'event_type' => 'created',
            'response_text' => 'Laporan masuk.',
            'created_at' => Carbon::now()->subHours(3),
        ]);

        ComplaintEvent::create([
            'complaint_id' => $complaint->id,
            'actor_id' => $this->counselorUser->id,
            'event_type' => 'status_update',
            'response_text' => 'Laporan sedang ditinjau oleh tim kurikulum.',
            'created_at' => Carbon::now()->subHour(),
        ]);

        $response = $this->getJson("/api/v1/public/complaints/{$complaint->ticket_code}");

        $response->assertStatus(200)
            ->assertJsonPath('data.ticket_code', 'ASP-20261011-TEST')
            ->assertJsonPath('data.category', 'akademik')
            ->assertJsonPath('data.status', 'ditinjau')
            ->assertJsonPath('data.timeline.0.response_text', 'Laporan masuk.')
            ->assertJsonPath('data.timeline.1.response_text', 'Laporan sedang ditinjau oleh tim kurikulum.');

        // Reporter user ID is NOT leaked in response
        $this->assertArrayNotHasKey('reporter_user_id', $response->json('data'));
    }

    /**
     * BE-GUEST-02: Tracking fails on invalid ticket code.
     */
    public function test_be_guest_02_public_track_fails_on_invalid_ticket_code(): void
    {
        $response = $this->getJson('/api/v1/public/complaints/ASP-NONEXISTENT');

        $response->assertStatus(404)
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }

    /**
     * BE-GUEST-02 / Decision #9: Principal and counselor can access complaint list.
     */
    public function test_be_guest_02_principal_and_counselor_can_access_complaint_list(): void
    {
        Complaint::create([
            'ticket_code' => 'ASP-20261011-AAA1',
            'category' => 'bullying',
            'content' => 'Terjadi perundungan verbal di area kantin belakang.',
            'is_anonymous' => true,
            'status' => 'baru',
        ]);

        // Principal can access
        $principalResponse = $this->actingAs($this->principalUser)
            ->getJson('/api/v1/complaints');

        $principalResponse->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.ticket_code', 'ASP-20261011-AAA1');

        // Counselor (Guru BK) can access
        $counselorResponse = $this->actingAs($this->counselorUser)
            ->getJson('/api/v1/complaints');

        $counselorResponse->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.ticket_code', 'ASP-20261011-AAA1');
    }

    /**
     * BE-GUEST-02: Unauthorized regular teacher cannot access complaint list.
     */
    public function test_be_guest_02_unauthorized_staff_cannot_access_complaint_list(): void
    {
        $response = $this->actingAs($this->teacherUser)
            ->getJson('/api/v1/complaints');

        $response->assertStatus(403);
        $this->assertStringContainsString('Hanya Kepala Sekolah, Guru BK', $response->json('error.message'));
    }

    /**
     * BE-GUEST-02: Authorized staff can update status and record response event.
     */
    public function test_be_guest_02_authorized_staff_can_update_status_and_add_response_event(): void
    {
        $complaint = Complaint::create([
            'ticket_code' => 'ASP-20261011-UPD1',
            'category' => 'pelayanan',
            'content' => 'Antrian administrasi surat keterangan siswa terlalu lama.',
            'status' => 'baru',
        ]);

        $response = $this->actingAs($this->principalUser)
            ->patchJson("/api/v1/complaints/{$complaint->id}/status", [
                'status' => 'ditindaklanjuti',
                'assigned_to' => $this->principalUser->id,
                'response_text' => 'Telah dikoordinasikan dengan Kepala Tata Usaha untuk membuka loket tambahan.',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'ditindaklanjuti')
            ->assertJsonPath('data.assigned_to', $this->principalUser->id);

        $this->assertEquals('ditindaklanjuti', $complaint->fresh()->status);

        // Timeline event created
        $this->assertDatabaseHas('complaint_events', [
            'complaint_id' => $complaint->id,
            'actor_id' => $this->principalUser->id,
            'event_type' => 'status_update',
            'response_text' => 'Telah dikoordinasikan dengan Kepala Tata Usaha untuk membuka loket tambahan.',
        ]);

        // Audit log created
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'complaint.status_updated',
            'entity' => 'Complaint',
            'entity_id' => $complaint->id,
        ]);
    }

    /**
     * BE-GUEST-02: Invalid status transition rejected.
     */
    public function test_be_guest_02_invalid_status_transition_rejected(): void
    {
        $complaint = Complaint::create([
            'ticket_code' => 'ASP-20261011-DONE',
            'category' => 'fasilitas',
            'content' => 'Pintu toilet rusak.',
            'status' => 'selesai',
        ]);

        // Trying to regress from 'selesai' back to 'ditinjau'
        $response = $this->actingAs($this->principalUser)
            ->patchJson("/api/v1/complaints/{$complaint->id}/status", [
                'status' => 'ditinjau',
                'response_text' => 'Buka kembali pengaduan.',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'UNPROCESSABLE_ENTITY');

        $this->assertStringContainsString('tidak dapat dikembalikan ke status', $response->json('error.message'));
    }

    /**
     * BE-GUEST-02: Submission fails on short content or invalid category.
     */
    public function test_be_guest_02_submission_validation_fails_on_short_content(): void
    {
        $response = $this->postJson('/api/v1/public/complaints', [
            'category' => 'kategori_palsu',
            'content' => 'pendek',
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    /**
     * BE-GUEST-02 / SEC-PRIVACY: Reporter identity is masked for staff lacking complaint.view_reporter permission.
     */
    public function test_be_guest_02_reporter_identity_is_masked_for_handler_without_view_reporter_permission(): void
    {
        $complaint = Complaint::create([
            'ticket_code' => 'ASP-20261014-PRIV1',
            'category' => 'bullying',
            'content' => 'Laporan insiden kekerasan oleh oknum siswa kelas 12.',
            'is_anonymous' => false,
            'reporter_user_id' => $this->reporterUser->id,
            'status' => 'baru',
        ]);

        // Counselor (Guru BK) has complaint.read & complaint.manage, but NOT complaint.view_reporter
        $response = $this->actingAs($this->counselorUser)
            ->getJson("/api/v1/complaints/{$complaint->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.ticket_code', 'ASP-20261014-PRIV1')
            ->assertJsonPath('data.is_anonymous', false)
            ->assertJsonPath('data.reporter', null)
            ->assertJsonPath('data.reporter_user_id', null);

        // Also masked in list view
        $listResponse = $this->actingAs($this->counselorUser)
            ->getJson('/api/v1/complaints');

        $listResponse->assertStatus(200)
            ->assertJsonPath('data.0.reporter', null)
            ->assertJsonPath('data.0.reporter_user_id', null);
    }

    /**
     * BE-GUEST-02 / SEC-PRIVACY: Reporter identity is visible for principal or user with complaint.view_reporter.
     */
    public function test_be_guest_02_reporter_identity_is_visible_for_principal(): void
    {
        $complaint = Complaint::create([
            'ticket_code' => 'ASP-20261014-PRIV2',
            'category' => 'bullying',
            'content' => 'Laporan insiden kekerasan oleh oknum siswa kelas 12.',
            'is_anonymous' => false,
            'reporter_user_id' => $this->reporterUser->id,
            'status' => 'baru',
        ]);

        // Principal has role 'principal' and can view reporter
        $response = $this->actingAs($this->principalUser)
            ->getJson("/api/v1/complaints/{$complaint->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.ticket_code', 'ASP-20261014-PRIV2')
            ->assertJsonPath('data.reporter.id', $this->reporterUser->id)
            ->assertJsonPath('data.reporter.email', $this->reporterUser->email);
    }

    /**
     * BE-GUEST-02 / MULTI-TENANCY: School isolation prevents accessing complaints from other schools.
     */
    public function test_be_guest_02_tenant_isolation_prevents_accessing_other_school_complaint(): void
    {
        $schoolAId = '11111111-1111-1111-1111-111111111111';
        $schoolBId = '22222222-2222-2222-2222-222222222222';

        $this->principalUser->update(['school_id' => $schoolAId]);

        $complaintSchoolB = Complaint::create([
            'school_id' => $schoolBId,
            'ticket_code' => 'ASP-20261014-SCHB',
            'category' => 'fasilitas',
            'content' => 'AC ruang guru sekolah B rusak berat.',
            'is_anonymous' => true,
            'status' => 'baru',
        ]);

        $response = $this->actingAs($this->principalUser)
            ->getJson("/api/v1/complaints/{$complaintSchoolB->id}");

        $response->assertStatus(403);
        $this->assertStringContainsString('di luar sekolah penugasan', $response->json('error.message'));
    }

    /**
     * FE-REPORT-01 / BE-GUEST-02: Bullying category complaint submission, tracking, and counselor review flow.
     */
    public function test_fe_report_01_bullying_complaint_submission_and_tracking_flow(): void
    {
        $payload = [
            'category' => 'bullying',
            'content' => 'Terjadi perundungan verbal dan pemalakan di area kantin belakang sekolah.',
            'is_anonymous' => true,
        ];

        // 1. Public submission (FE-REPORT-01)
        $submitResponse = $this->postJson('/api/v1/public/complaints', $payload);
        $submitResponse->assertStatus(201)
            ->assertJsonPath('data.category', 'bullying')
            ->assertJsonPath('data.status', 'baru')
            ->assertJsonPath('data.is_anonymous', true);

        $ticketCode = $submitResponse->json('data.ticket_code');
        $this->assertNotNull($ticketCode);

        // 2. Public tracking (FE-REPORT-01)
        $trackResponse = $this->getJson("/api/v1/public/complaints/{$ticketCode}");
        $trackResponse->assertStatus(200)
            ->assertJsonPath('data.ticket_code', $ticketCode)
            ->assertJsonPath('data.category', 'bullying')
            ->assertJsonPath('data.status', 'baru')
            ->assertJsonPath('data.content', $payload['content']);

        $this->assertCount(1, $trackResponse->json('data.timeline'));

        // 3. Counselor review (ADM-GUEST-01)
        $counselorListResponse = $this->actingAs($this->counselorUser)
            ->getJson('/api/v1/complaints?category=bullying');

        $counselorListResponse->assertStatus(200);
        $items = $counselorListResponse->json('data');
        $this->assertTrue(collect($items)->contains('ticket_code', $ticketCode));
    }
}
