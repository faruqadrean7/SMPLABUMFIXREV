<?php

namespace Tests\Feature\Cms;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SchoolEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EventTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $regularUser;

    protected Role $adminRole;

    protected Role $guestRole;

    protected Permission $permManageEvent;

    protected function setUp(): void
    {
        parent::setUp();

        // Permissions
        $this->permManageEvent = Permission::create([
            'code' => 'cms.event.manage',
            'description' => 'Mengelola agenda dan kalender kegiatan CMS',
        ]);

        // Roles
        $this->adminRole = Role::create([
            'code' => 'super_admin',
            'name' => 'Super Administrator',
            'is_system' => true,
        ]);
        $this->adminRole->permissions()->attach([$this->permManageEvent->id]);

        $this->guestRole = Role::create([
            'code' => 'guest',
            'name' => 'Tamu',
            'is_system' => false,
        ]);

        // Users
        $this->adminUser = User::create([
            'username' => 'admin_agenda',
            'email' => 'admin.agenda@labschool.sch.id',
            'password_hash' => Hash::make('AdminSecret123!'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($this->adminRole->id);

        $this->regularUser = User::create([
            'username' => 'guest_user',
            'email' => 'guest@labschool.sch.id',
            'password_hash' => Hash::make('GuestSecret123!'),
            'status' => 'active',
        ]);
        $this->regularUser->roles()->attach($this->guestRole->id);
    }

    /**
     * BE-CMS-04: Unauthenticated requests cannot mutate events (401)
     */
    public function test_be_cms_04_unauthenticated_requests_cannot_mutate_events(): void
    {
        $dummyId = '00000000-0000-0000-0000-000000000000';

        $this->getJson('/api/v1/events')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->postJson('/api/v1/events', ['title' => 'Agenda Baru'])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->getJson("/api/v1/events/{$dummyId}")
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->putJson("/api/v1/events/{$dummyId}", ['title' => 'Revisi Agenda'])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->deleteJson("/api/v1/events/{$dummyId}")
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    /**
     * BE-CMS-04: User without permission is forbidden (403)
     */
    public function test_be_cms_04_user_without_permission_is_forbidden(): void
    {
        $this->actingAs($this->regularUser)
            ->getJson('/api/v1/events')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        $this->actingAs($this->regularUser)
            ->postJson('/api/v1/events', [
                'title' => 'Rapat Rahasia',
                'category' => 'event',
                'visibility' => 'internal',
                'start_at' => now()->toIso8601String(),
                'end_at' => now()->addHours(2)->toIso8601String(),
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    /**
     * BE-CMS-04: Validation fails when end date is before start date (422)
     */
    public function test_be_cms_04_validation_fails_when_end_date_is_before_start_date(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/events', [
                'title' => 'Asesmen Akhir Semester',
                'category' => 'ujian',
                'visibility' => 'publik',
                'start_at' => '2026-11-20 08:00:00',
                'end_at' => '2026-11-19 08:00:00',
            ]);

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    /**
     * BE-CMS-04: Admin can CRUD events with audit logs
     */
    public function test_be_cms_04_admin_can_crud_events_with_audit_logs(): void
    {
        // 1. Create Event
        $createResp = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/events', [
                'title' => 'Penilaian Tengah Semester Ganjil',
                'category' => 'ujian',
                'visibility' => 'publik',
                'start_at' => '2026-10-15 07:30:00',
                'end_at' => '2026-10-22 14:00:00',
            ]);

        $createResp->assertStatus(201)
            ->assertJsonPath('data.title', 'Penilaian Tengah Semester Ganjil')
            ->assertJsonPath('data.category', 'ujian')
            ->assertJsonPath('data.visibility', 'publik');

        $eventId = $createResp->json('data.id');

        $this->assertDatabaseHas('events', [
            'id' => $eventId,
            'title' => 'Penilaian Tengah Semester Ganjil',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'action' => 'event.created',
            'entity' => 'SchoolEvent',
            'entity_id' => $eventId,
        ]);

        // 2. Show Event Detail
        $this->actingAs($this->adminUser)
            ->getJson("/api/v1/events/{$eventId}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $eventId)
            ->assertJsonPath('data.title', 'Penilaian Tengah Semester Ganjil');

        // 3. Update Event
        $updateResp = $this->actingAs($this->adminUser)
            ->putJson("/api/v1/events/{$eventId}", [
                'title' => 'Asesmen Sumatif Tengah Semester (ASTS) Ganjil',
                'visibility' => 'internal',
            ]);

        $updateResp->assertStatus(200)
            ->assertJsonPath('data.title', 'Asesmen Sumatif Tengah Semester (ASTS) Ganjil')
            ->assertJsonPath('data.visibility', 'internal');

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'action' => 'event.updated',
            'entity' => 'SchoolEvent',
            'entity_id' => $eventId,
        ]);

        // 4. Delete Event
        $deleteResp = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/events/{$eventId}");

        $deleteResp->assertStatus(200)
            ->assertJsonPath('data.message', 'Agenda kegiatan berhasil dihapus');

        $this->assertDatabaseMissing('events', ['id' => $eventId]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'action' => 'event.deleted',
            'entity' => 'SchoolEvent',
            'entity_id' => $eventId,
        ]);
    }

    /**
     * BE-CMS-04: Admin can filter and search events by category, visibility, and keyword
     */
    public function test_be_cms_04_admin_can_filter_and_search_events(): void
    {
        SchoolEvent::create([
            'title' => 'Libur Bersama Hari Raya Idul Fitri',
            'category' => 'libur',
            'visibility' => 'publik',
            'start_at' => '2026-04-10 00:00:00',
            'end_at' => '2026-04-15 23:59:59',
        ]);

        SchoolEvent::create([
            'title' => 'Rapat Kerja Dewan Guru & Karyawan',
            'category' => 'event',
            'visibility' => 'internal',
            'start_at' => '2026-06-25 08:00:00',
            'end_at' => '2026-06-26 16:00:00',
        ]);

        SchoolEvent::create([
            'title' => 'Ujian Akhir Jenjang Kelas XII',
            'category' => 'ujian',
            'visibility' => 'publik',
            'start_at' => '2026-03-01 07:30:00',
            'end_at' => '2026-03-08 12:00:00',
        ]);

        // Filter by category = libur
        $respCategory = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/events?category=libur');

        $respCategory->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'Libur Bersama Hari Raya Idul Fitri');

        // Filter by visibility = internal
        $respVisibility = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/events?visibility=internal');

        $respVisibility->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'Rapat Kerja Dewan Guru & Karyawan');

        // Search by keyword
        $respSearch = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/events?search=Dewan');

        $respSearch->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'Rapat Kerja Dewan Guru & Karyawan');
    }

    /**
     * BE-CMS-04: Public can view calendar events and filter by month/year
     */
    public function test_be_cms_04_public_can_view_calendar_events_and_filters(): void
    {
        // October 2026 event (Public) -> Visible in October
        $octEvent = SchoolEvent::create([
            'title' => 'Peringatan Bulan Bahasa dan Sastra',
            'category' => 'event',
            'visibility' => 'publik',
            'start_at' => '2026-10-20 08:00:00',
            'end_at' => '2026-10-22 15:00:00',
        ]);

        // October 2026 event (Internal) -> HIDDEN from public
        $internalEvent = SchoolEvent::create([
            'title' => 'Rapat Evaluasi Triwulan Guru',
            'category' => 'event',
            'visibility' => 'internal',
            'start_at' => '2026-10-25 13:00:00',
            'end_at' => '2026-10-25 16:00:00',
        ]);

        // November 2026 event (Public) -> Visible in November
        $novEvent = SchoolEvent::create([
            'title' => 'Upacara Hari Pahlawan Nasional',
            'category' => 'event',
            'visibility' => 'publik',
            'start_at' => '2026-11-10 07:00:00',
            'end_at' => '2026-11-10 09:00:00',
        ]);

        // Public index for October 2026
        $respOct = $this->getJson('/api/v1/public/events?month=10&year=2026');

        $respOct->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'Peringatan Bulan Bahasa dan Sastra');

        // Public index for November 2026
        $respNov = $this->getJson('/api/v1/public/events?month=11&year=2026');

        $respNov->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'Upacara Hari Pahlawan Nasional');

        // Public show on public event -> 200
        $this->getJson("/api/v1/public/events/{$octEvent->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $octEvent->id);

        // Public show on internal event -> 404 NOT_FOUND
        $this->getJson("/api/v1/public/events/{$internalEvent->id}")
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }
}
