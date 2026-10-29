<?php

namespace Tests\Feature\Sar;

use App\Models\Asset;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Room;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TicketTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $sarprasUser;

    protected User $technicianUser;

    protected User $teacherUser;

    protected User $otherTeacherUser;

    protected Room $room1;

    protected Asset $asset1;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Admin', 'is_system' => true]);
        $roleSarpras = Role::firstOrCreate(['code' => 'facility_staff'], ['name' => 'Staf Sarana & Prasarana', 'is_system' => true]);
        $roleTech = Role::firstOrCreate(['code' => 'technician'], ['name' => 'Teknisi', 'is_system' => true]);
        $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);

        $permAsset = Permission::firstOrCreate(['code' => 'facility.asset.manage'], ['description' => 'Mengelola inventaris & helpdesk sarpras']);
        $roleSarpras->permissions()->syncWithoutDetaching([$permAsset->id]);
        $roleAdmin->permissions()->syncWithoutDetaching([$permAsset->id]);

        $this->adminUser = User::create([
            'username' => 'admin_helpdesk',
            'email' => 'admin_helpdesk@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($roleAdmin->id);

        $this->sarprasUser = User::create([
            'username' => 'staf_helpdesk',
            'email' => 'helpdesk@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->sarprasUser->roles()->attach($roleSarpras->id);

        $this->technicianUser = User::create([
            'username' => 'teknisi_joko',
            'email' => 'teknisi@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->technicianUser->roles()->attach($roleTech->id);

        $this->teacherUser = User::create([
            'username' => 'guru_ahmad',
            'email' => 'ahmad@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->teacherUser->roles()->attach($roleTeacher->id);

        $this->otherTeacherUser = User::create([
            'username' => 'guru_dewi',
            'email' => 'dewi@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->otherTeacherUser->roles()->attach($roleTeacher->id);

        $this->room1 = Room::create([
            'name' => 'Ruang Kelas XI IPA 2',
            'code' => 'R-XI-IPA-2',
            'room_type' => 'kelas',
            'capacity' => 36,
            'is_active' => true,
        ]);

        $this->asset1 = Asset::create([
            'asset_code' => 'AST-PROJ-XI-01',
            'name' => 'Proyektor BenQ Kelas XI',
            'category' => 'Elektronik',
            'room_id' => $this->room1->id,
            'asset_condition' => 'rusak_ringan',
        ]);
    }

    /**
     * BE-SAR-03: Pelapor (guru/staf) dapat melaporkan tiket kerusakan sarpras dengan nomor otomatis.
     */
    public function test_be_sar_03_reporter_can_create_ticket_with_photo_and_auto_number(): void
    {
        $this->app['auth']->forgetGuards();

        $response = $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson('/api/v1/tickets', [
                'title' => 'Lampu Proyektor Mati Mendadak',
                'room_id' => $this->room1->id,
                'asset_id' => $this->asset1->id,
                'description' => 'Saat pembelajaran sesi 2, proyektor mengeluarkan bunyi berdengung lalu lampu mati.',
                'priority' => 'tinggi',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.title', 'Lampu Proyektor Mati Mendadak')
            ->assertJsonPath('data.priority', 'tinggi')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.reporter_id', $this->teacherUser->id);

        $ticketNumber = $response->json('data.ticket_number');
        $this->assertNotEmpty($ticketNumber);
        $this->assertStringStartsWith('TCK-', $ticketNumber);

        $ticketId = $response->json('data.id');

        $this->assertDatabaseHas('tickets', [
            'id' => $ticketId,
            'ticket_number' => $ticketNumber,
            'status' => 'pending',
        ]);

        // Verifikasi otomatis tercatat event pertama 'lapor'
        $this->assertDatabaseHas('ticket_events', [
            'ticket_id' => $ticketId,
            'actor_id' => $this->teacherUser->id,
            'event_type' => 'lapor',
        ]);

        // Verifikasi audit log tercatat
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->teacherUser->id,
            'action' => 'ticket.create',
            'entity' => 'tickets',
            'entity_id' => $ticketId,
        ]);
    }

    /**
     * BE-SAR-03: Staf Sarpras dapat mendisposisikan tiket kepada teknisi.
     */
    public function test_be_sar_03_sarpras_can_dispose_ticket_to_technician(): void
    {
        $ticket = Ticket::create([
            'ticket_number' => 'TCK-202610-0099',
            'title' => 'AC Bocor Menetes',
            'reporter_id' => $this->teacherUser->id,
            'room_id' => $this->room1->id,
            'description' => 'AC menetes membasahi meja siswa paling depan.',
            'priority' => 'sedang',
            'status' => 'pending',
        ]);

        $this->app['auth']->forgetGuards();

        $response = $this->actingAs($this->sarprasUser, 'sanctum')
            ->postJson("/api/v1/tickets/{$ticket->id}/dispose", [
                'assignee_id' => $this->technicianUser->id,
                'note' => 'Mohon dicek freon dan selang pembuangan airnya hari ini.',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'diproses')
            ->assertJsonPath('data.assignee_id', $this->technicianUser->id);

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'assignee_id' => $this->technicianUser->id,
            'status' => 'diproses',
        ]);

        $this->assertDatabaseHas('ticket_events', [
            'ticket_id' => $ticket->id,
            'actor_id' => $this->sarprasUser->id,
            'event_type' => 'disposisi',
            'note' => 'Mohon dicek freon dan selang pembuangan airnya hari ini.',
        ]);
    }

    /**
     * BE-SAR-03: Teknisi yang ditugaskan dapat memperbarui status tiket menjadi 'selesai'.
     */
    public function test_be_sar_03_technician_can_update_status_to_resolved(): void
    {
        $ticket = Ticket::create([
            'ticket_number' => 'TCK-202610-0100',
            'title' => 'Pintu Kamar Mandi Rusak',
            'reporter_id' => $this->teacherUser->id,
            'assignee_id' => $this->technicianUser->id,
            'description' => 'Engsel pintu lepas.',
            'priority' => 'sedang',
            'status' => 'diproses',
        ]);

        $this->app['auth']->forgetGuards();

        $response = $this->actingAs($this->technicianUser, 'sanctum')
            ->patchJson("/api/v1/tickets/{$ticket->id}/status", [
                'status' => 'selesai',
                'note' => 'Engsel telah diganti baru dan baut dikencangkan.',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'selesai');

        $ticket->refresh();
        $this->assertEquals('selesai', $ticket->status);
        $this->assertNotNull($ticket->resolved_at);

        $this->assertDatabaseHas('ticket_events', [
            'ticket_id' => $ticket->id,
            'actor_id' => $this->technicianUser->id,
            'event_type' => 'selesai',
            'note' => 'Engsel telah diganti baru dan baut dikencangkan.',
        ]);
    }

    /**
     * BE-SAR-03: Pelapor atau teknisi dapat menambahkan catatan/komentar pada tiket.
     */
    public function test_be_sar_03_reporter_or_assignee_can_add_comment(): void
    {
        $ticket = Ticket::create([
            'ticket_number' => 'TCK-202610-0101',
            'title' => 'Kipas Angin Berisik',
            'reporter_id' => $this->teacherUser->id,
            'assignee_id' => $this->technicianUser->id,
            'description' => 'Suara decit mengganggu KBM.',
            'priority' => 'rendah',
            'status' => 'diproses',
        ]);

        $this->app['auth']->forgetGuards();

        $response = $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson("/api/v1/tickets/{$ticket->id}/comments", [
                'note' => 'Terima kasih, teknisi sudah datang mengecek siang tadi.',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.event_type', 'komentar')
            ->assertJsonPath('data.note', 'Terima kasih, teknisi sudah datang mengecek siang tadi.');

        $this->assertDatabaseHas('ticket_events', [
            'ticket_id' => $ticket->id,
            'actor_id' => $this->teacherUser->id,
            'event_type' => 'komentar',
        ]);
    }

    /**
     * BE-SAR-03: Scoping daftar tiket:
     * Pelapor biasa hanya melihat tiket miliknya, staf sarpras dapat melihat seluruh tiket.
     */
    public function test_be_sar_03_list_tickets_scoping(): void
    {
        $ticketAhmad = Ticket::create([
            'ticket_number' => 'TCK-202610-0102',
            'title' => 'Tiket Pak Ahmad',
            'reporter_id' => $this->teacherUser->id,
            'description' => 'Kerusakan meja guru.',
            'status' => 'pending',
        ]);

        $ticketDewi = Ticket::create([
            'ticket_number' => 'TCK-202610-0103',
            'title' => 'Tiket Bu Dewi',
            'reporter_id' => $this->otherTeacherUser->id,
            'description' => 'Kran wastafel bocor.',
            'status' => 'pending',
        ]);

        $this->app['auth']->forgetGuards();

        // 1. Pak Ahmad hanya melihat tiket miliknya
        $respAhmad = $this->actingAs($this->teacherUser, 'sanctum')
            ->getJson('/api/v1/tickets');

        $respAhmad->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $ticketAhmad->id);

        $this->app['auth']->forgetGuards();

        // 2. Staf Sarpras melihat seluruh tiket
        $respSarpras = $this->actingAs($this->sarprasUser, 'sanctum')
            ->getJson('/api/v1/tickets');

        $respSarpras->assertStatus(200)
            ->assertJsonPath('meta.total', 2);
    }

    /**
     * BE-SAR-03: Pengguna tanpa wewenang dilarang mendisposisikan tiket (403 Forbidden).
     */
    public function test_be_sar_03_unauthorized_user_cannot_dispose_ticket(): void
    {
        $ticket = Ticket::create([
            'ticket_number' => 'TCK-202610-0104',
            'title' => 'Uji Wewenang',
            'reporter_id' => $this->teacherUser->id,
            'description' => 'Deskripsi',
            'status' => 'pending',
        ]);

        $this->app['auth']->forgetGuards();

        $response = $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson("/api/v1/tickets/{$ticket->id}/dispose", [
                'assignee_id' => $this->technicianUser->id,
            ]);

        $response->assertStatus(403);
    }

    /**
     * BE-SAR-03: Pengguna biasa dilarang melihat detail tiket milik orang lain (anti-IDOR 403).
     */
    public function test_be_sar_03_unauthorized_user_cannot_view_other_tickets(): void
    {
        $ticketDewi = Ticket::create([
            'ticket_number' => 'TCK-202610-0105',
            'title' => 'Tiket Bu Dewi Privat',
            'reporter_id' => $this->otherTeacherUser->id,
            'description' => 'Deskripsi',
            'status' => 'pending',
        ]);

        $this->app['auth']->forgetGuards();

        $response = $this->actingAs($this->teacherUser, 'sanctum')
            ->getJson("/api/v1/tickets/{$ticketDewi->id}");

        $response->assertStatus(403);
    }

    /**
     * BE-SAR-03: Ringkasan statistik tiket, SLA warning, dan metrik Kanban.
     */
    public function test_be_sar_03_get_ticket_summary_metrics_and_sla(): void
    {
        // 1. Tiket pending normal
        Ticket::create([
            'ticket_number' => 'TCK-202610-0201',
            'title' => 'Kran Bocor',
            'reporter_id' => $this->teacherUser->id,
            'priority' => 'sedang',
            'status' => 'pending',
            'description' => 'Bocor halus',
            'created_at' => now(),
        ]);

        // 2. Tiket darurat dan SLA warning (dibuat 30 jam lalu, belum selesai)
        $t2 = Ticket::create([
            'ticket_number' => 'TCK-202610-0202',
            'title' => 'Kabel Listrik Terbakar',
            'reporter_id' => $this->teacherUser->id,
            'priority' => 'darurat',
            'status' => 'diproses',
            'description' => 'Ada bau sangit dan percikan api',
        ]);
        DB::table('tickets')->where('id', $t2->id)->update(['created_at' => now()->subHours(30)]);

        // 3. Tiket selesai (resolusi 2 jam)
        $t3 = Ticket::create([
            'ticket_number' => 'TCK-202610-0203',
            'title' => 'Ganti Lampu',
            'reporter_id' => $this->teacherUser->id,
            'assignee_id' => $this->technicianUser->id,
            'priority' => 'rendah',
            'status' => 'selesai',
            'description' => 'Lampu neon redup',
            'resolved_at' => now()->subHours(2),
        ]);
        DB::table('tickets')->where('id', $t3->id)->update(['created_at' => now()->subHours(4)]);

        $this->app['auth']->forgetGuards();

        $response = $this->actingAs($this->sarprasUser, 'sanctum')
            ->getJson('/api/v1/tickets/summary');

        $response->assertStatus(200)
            ->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.pending', 1)
            ->assertJsonPath('data.diproses', 1)
            ->assertJsonPath('data.selesai', 1)
            ->assertJsonPath('data.darurat', 1)
            ->assertJsonPath('data.sla_warning', 1)
            ->assertJsonPath('data.avg_resolution_hours', 2);
    }

    /**
     * BE-SAR-03: Daftar teknisi dan beban kerja penugasan.
     */
    public function test_be_sar_03_get_technicians_list_and_workload(): void
    {
        // Berikan penugasan pada teknisi
        Ticket::create([
            'ticket_number' => 'TCK-202610-0301',
            'title' => 'Perbaikan AC',
            'reporter_id' => $this->teacherUser->id,
            'assignee_id' => $this->technicianUser->id,
            'priority' => 'tinggi',
            'status' => 'diproses',
            'description' => 'AC mati total',
        ]);

        $this->app['auth']->forgetGuards();

        $response = $this->actingAs($this->sarprasUser, 'sanctum')
            ->getJson('/api/v1/tickets/technicians');

        $response->assertStatus(200);
        $technicians = $response->json('data');

        $this->assertNotEmpty($technicians);
        $techJoko = collect($technicians)->firstWhere('id', $this->technicianUser->id);
        $this->assertNotNull($techJoko);
        $this->assertEquals(1, $techJoko['active_tickets_count']);
    }

    /**
     * BE-SAR-03: Multi-tenant scoping mencegah akses tiket antar-sekolah berbeda.
     */
    public function test_be_sar_03_multi_tenant_school_isolation_prevents_unauthorized_access(): void
    {
        $schoolA = '99999999-1111-2222-3333-444444444444';
        $schoolB = '99999999-5555-6666-7777-888888888888';

        $this->sarprasUser->school_id = $schoolA;
        $this->sarprasUser->save();

        $ticketSchoolB = Ticket::create([
            'school_id' => $schoolB,
            'ticket_number' => 'TCK-202610-0401',
            'title' => 'Tiket Sekolah Lain',
            'reporter_id' => $this->teacherUser->id,
            'description' => 'Tiket sekolah sebelah',
            'status' => 'pending',
        ]);

        $this->app['auth']->forgetGuards();

        $response = $this->actingAs($this->sarprasUser, 'sanctum')
            ->getJson("/api/v1/tickets/{$ticketSchoolB->id}");

        $response->assertStatus(403);
    }
}
