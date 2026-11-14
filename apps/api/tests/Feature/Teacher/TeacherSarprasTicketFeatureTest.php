<?php

namespace Tests\Feature\Teacher;

use App\Models\AuditLog;
use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\User;
use Database\Seeders\TeacherDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherSarprasTicketFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected User $userMtk;

    protected User $userWalas;

    protected function setUp(): void
    {
        parent::setUp();

        (new TeacherDemoSeeder)->run();

        $this->userMtk = User::where('username', 'guru.mtk.staging')->firstOrFail();
        $this->userWalas = User::where('username', 'walas.7a.staging')->firstOrFail();
    }

    /**
     * FIX-GURU-12 AC1: Teacher can create sarpras ticket under 2 seconds and returns ticket_number.
     */
    public function test_fix_guru_12_teacher_can_create_sarpras_ticket_under_2_seconds_and_returns_ticket_number(): void
    {
        $startTime = microtime(true);

        $response = $this->actingAs($this->userMtk)
            ->postJson('/api/v1/me/teacher/tickets', [
                'title' => 'AC Ruang Lab Komputer 1 Rusak',
                'category' => 'Laboratorium Komputer',
                'priority' => 'tinggi',
                'location' => 'Lab Komputer 1 Lantai 2',
                'description' => 'Unit AC tidak dingin dan mengeluarkan air menetes di dekat meja komputer nomor 12.',
            ]);

        $elapsedSeconds = microtime(true) - $startTime;

        // AC1: Latensi harus < 2 detik pada kondisi normal
        $this->assertLessThan(2.0, $elapsedSeconds, "Pembuatan tiket sarpras melebihi batas 2 detik (latensi: {$elapsedSeconds}s)");

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.priority', 'tinggi')
            ->assertJsonPath('data.reporter_id', $this->userMtk->id);

        $ticketNumber = $response->json('data.ticket_number');
        $this->assertNotEmpty($ticketNumber);
        $this->assertStringStartsWith('TKT-', $ticketNumber);

        $ticketId = $response->json('data.id');

        // Pastikan tiket tersimpan di database
        $this->assertDatabaseHas('tickets', [
            'id' => $ticketId,
            'ticket_number' => $ticketNumber,
            'reporter_id' => $this->userMtk->id,
            'status' => 'pending',
            'priority' => 'tinggi',
        ]);

        // Pastikan timeline ticket_events tercatat
        $this->assertDatabaseHas('ticket_events', [
            'ticket_id' => $ticketId,
            'actor_id' => $this->userMtk->id,
            'event_type' => 'lapor',
        ]);

        // Pastikan audit_logs tercatat
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->userMtk->id,
            'action' => 'teacher.ticket.created',
            'entity' => 'Ticket',
            'entity_id' => $ticketId,
        ]);
    }

    /**
     * FIX-GURU-12 AC2: Ticket creation fails with 422 if description is missing.
     */
    public function test_fix_guru_12_ticket_creation_fails_when_description_is_empty(): void
    {
        $response = $this->actingAs($this->userMtk)
            ->postJson('/api/v1/me/teacher/tickets', [
                'title' => 'Laporan Tanpa Deskripsi',
                'category' => 'Laboratorium Komputer',
                'priority' => 'sedang',
                'description' => '',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['description']);
    }

    /**
     * FIX-GURU-12 AC4: Unauthenticated ticket creation returns 401.
     */
    public function test_fix_guru_12_ticket_creation_requires_authentication_401(): void
    {
        $response = $this->postJson('/api/v1/me/teacher/tickets', [
            'title' => 'Laporan Tanpa Sesi Login',
            'description' => 'Mencoba membuat tiket sarpras tanpa login.',
        ]);

        $response->assertStatus(401);
    }

    /**
     * FIX-GURU-12: Teacher can view own tickets list.
     */
    public function test_fix_guru_12_teacher_can_view_own_tickets_list(): void
    {
        // 1. Buat tiket milik userMtk
        $ticket1 = Ticket::create([
            'school_id' => $this->userMtk->school_id ?? '00000000-0000-0000-0000-000000000001',
            'ticket_number' => 'TKT-TEST-001',
            'title' => 'Proyektor Rusak 7-A',
            'reporter_id' => $this->userMtk->id,
            'description' => 'Lampu proyektor berkedip merah dan mati.',
            'priority' => 'tinggi',
            'status' => 'pending',
        ]);

        // 2. Buat tiket milik userWalas (guru lain)
        $ticket2 = Ticket::create([
            'school_id' => $this->userWalas->school_id ?? '00000000-0000-0000-0000-000000000001',
            'ticket_number' => 'TKT-TEST-002',
            'title' => 'Gagang Pintu Kelas Rusak',
            'reporter_id' => $this->userWalas->id,
            'description' => 'Gagang pintu kelas 7-A terlepas.',
            'priority' => 'sedang',
            'status' => 'pending',
        ]);

        // 3. Guru MTK memanggil GET /api/v1/me/teacher/tickets
        $response = $this->actingAs($this->userMtk)
            ->getJson('/api/v1/me/teacher/tickets');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'ticket_number',
                        'title',
                        'description',
                        'priority',
                        'status',
                    ],
                ],
            ]);

        $ticketIds = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($ticket1->id, $ticketIds);
        $this->assertNotContains($ticket2->id, $ticketIds, 'Tiket milik guru lain tidak boleh bocor ke daftar guru ini (IDOR prevention)');
    }
}
