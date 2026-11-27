<?php

namespace Tests\Feature\Guest;

use App\Models\Complaint;
use App\Models\ComplaintEvent;
use App\Models\Notification;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Triase, SLA, dan eskalasi pengaduan — fokus penanganan perundungan.
 */
class ComplaintTriageTest extends TestCase
{
    use RefreshDatabase;

    protected User $principalUser;

    protected User $counselorUser;

    protected User $reporterUser;

    protected function setUp(): void
    {
        parent::setUp();

        $rolePrincipal = Role::firstOrCreate(['code' => 'principal'], ['name' => 'Kepala Sekolah', 'is_system' => true]);
        $roleCounselor = Role::firstOrCreate(['code' => 'counselor'], ['name' => 'Guru BK', 'is_system' => true]);

        $permRead = Permission::firstOrCreate(['code' => 'complaint.read'], ['description' => 'Melihat aduan']);
        $permManage = Permission::firstOrCreate(['code' => 'complaint.manage'], ['description' => 'Mengelola aduan']);

        $rolePrincipal->permissions()->syncWithoutDetaching([$permRead->id, $permManage->id]);
        $roleCounselor->permissions()->syncWithoutDetaching([$permRead->id, $permManage->id]);

        $this->principalUser = User::create([
            'username' => 'kepsek_triase',
            'email' => 'kepsek_triase@sekolah.sch.id',
            'password_hash' => bcrypt('password123'),
            'status' => 'active',
        ]);
        $this->principalUser->roles()->attach($rolePrincipal->id);

        $this->counselorUser = User::create([
            'username' => 'bk_triase',
            'email' => 'bk_triase@sekolah.sch.id',
            'password_hash' => bcrypt('password123'),
            'status' => 'active',
        ]);
        $this->counselorUser->roles()->attach($roleCounselor->id);

        $this->reporterUser = User::create([
            'username' => 'pelapor_triase',
            'email' => 'pelapor_triase@sekolah.sch.id',
            'password_hash' => bcrypt('password123'),
            'status' => 'active',
        ]);
    }

    private function submit(string $category, bool $anonymous = false, ?User $as = null): array
    {
        $payload = [
            'category' => $category,
            'content' => 'Kejadian di kelas saat jam istirahat perlu ditindaklanjuti segera oleh tim sekolah.',
            'is_anonymous' => $anonymous,
        ];

        $response = ($as ? $this->actingAs($as) : $this)
            ->postJson('/api/v1/public/complaints', $payload);

        $response->assertStatus(201);

        return $response->json('data');
    }

    /** Aduan bullying langsung masuk prioritas tinggi dengan tenggat 24 jam. */
    public function test_aduan_bullying_otomatis_prioritas_tinggi_dan_tenggat_24_jam(): void
    {
        Carbon::setTestNow('2026-11-27 08:00:00');

        $data = $this->submit('bullying', false, $this->reporterUser);

        $this->assertSame('tinggi', $data['severity']);

        $complaint = Complaint::where('ticket_code', $data['ticket_code'])->firstOrFail();
        $this->assertSame('tinggi', $complaint->severity);
        $this->assertEquals(
            '2026-11-28 08:00:00',
            $complaint->due_at->format('Y-m-d H:i:s'),
            'Tenggat aduan bullying harus 24 jam dari waktu lapor.'
        );

        Carbon::setTestNow();
    }

    /** Aduan fasilitas tidak boleh menyamai prioritas bullying. */
    public function test_aduan_fasilitas_prioritas_sedang_dan_tenggat_72_jam(): void
    {
        Carbon::setTestNow('2026-11-27 08:00:00');

        $data = $this->submit('fasilitas');

        $this->assertSame('sedang', $data['severity']);

        $complaint = Complaint::where('ticket_code', $data['ticket_code'])->firstOrFail();
        $this->assertEquals('2026-11-30 08:00:00', $complaint->due_at->format('Y-m-d H:i:s'));

        Carbon::setTestNow();
    }

    /** Klien tidak bisa menurunkan prioritas aduan bullying lewat payload. */
    public function test_klien_tidak_bisa_menentukan_severity_sendiri(): void
    {
        $response = $this->actingAs($this->reporterUser)->postJson('/api/v1/public/complaints', [
            'category' => 'bullying',
            'content' => 'Perundungan berulang terhadap siswa kelas tujuh di kantin sekolah.',
            'is_anonymous' => false,
            'severity' => 'rendah',
        ]);

        $response->assertStatus(201);
        $this->assertSame('tinggi', $response->json('data.severity'));
    }

    /** Melompat dari 'baru' ke 'selesai' ditolak: harus melewati penanganan. */
    public function test_transisi_status_tidak_sah_ditolak(): void
    {
        $complaint = Complaint::create([
            'ticket_code' => 'ASP-20261127-TRI1',
            'category' => 'bullying',
            'severity' => 'tinggi',
            'content' => 'Aduan perundungan untuk uji transisi status.',
            'status' => 'baru',
        ]);

        $this->actingAs($this->principalUser)
            ->patchJson("/api/v1/complaints/{$complaint->id}/status", [
                'status' => 'selesai',
                'response_text' => 'Ditutup langsung tanpa penanganan.',
            ])
            ->assertStatus(422);

        $this->assertSame('baru', $complaint->fresh()->status);
    }

    /** Status akhir tidak bisa dibuka kembali. */
    public function test_status_selesai_tidak_bisa_dibuka_lagi(): void
    {
        $complaint = Complaint::create([
            'ticket_code' => 'ASP-20261127-TRI2',
            'category' => 'bullying',
            'severity' => 'tinggi',
            'content' => 'Aduan perundungan untuk uji status akhir.',
            'status' => 'selesai',
        ]);

        $this->actingAs($this->principalUser)
            ->patchJson("/api/v1/complaints/{$complaint->id}/status", [
                'status' => 'ditinjau',
                'response_text' => 'Mencoba membuka kembali aduan.',
            ])
            ->assertStatus(422);
    }

    /** Tanggapan pertama tercatat sebagai dasar perhitungan SLA. */
    public function test_tanggapan_pertama_mencatat_first_responded_at(): void
    {
        $complaint = Complaint::create([
            'ticket_code' => 'ASP-20261127-TRI3',
            'category' => 'bullying',
            'severity' => 'tinggi',
            'content' => 'Aduan untuk uji pencatatan tanggapan pertama.',
            'status' => 'baru',
            'due_at' => Carbon::now()->addHours(24),
        ]);

        $this->actingAs($this->counselorUser)
            ->patchJson("/api/v1/complaints/{$complaint->id}/status", [
                'status' => 'ditinjau',
                'response_text' => 'Laporan diterima, tim BK menjadwalkan wawancara saksi.',
            ])
            ->assertStatus(200);

        $this->assertNotNull($complaint->fresh()->first_responded_at);
    }

    /** Petugas boleh menaikkan keparahan; tenggat dihitung ulang. */
    public function test_petugas_dapat_menaikkan_severity_dan_tenggat_dihitung_ulang(): void
    {
        $complaint = Complaint::create([
            'ticket_code' => 'ASP-20261127-TRI4',
            'category' => 'pelayanan',
            'severity' => 'sedang',
            'content' => 'Aduan untuk uji eskalasi keparahan oleh petugas.',
            'status' => 'baru',
            'due_at' => Carbon::now()->addHours(72),
        ]);

        $this->actingAs($this->principalUser)
            ->patchJson("/api/v1/complaints/{$complaint->id}/status", [
                'status' => 'ditinjau',
                'severity' => 'kritis',
                'response_text' => 'Kasus ternyata melibatkan kekerasan fisik, dinaikkan ke kritis.',
            ])
            ->assertStatus(200);

        $fresh = $complaint->fresh();
        $this->assertSame('kritis', $fresh->severity);
        $this->assertLessThan(10, Carbon::now()->diffInHours($fresh->due_at, false));
    }

    /** Pelapor yang login mendapat notifikasi saat aduannya bergerak. */
    public function test_pelapor_mendapat_notifikasi_perubahan_status(): void
    {
        $data = $this->submit('bullying', false, $this->reporterUser);
        $complaint = Complaint::where('ticket_code', $data['ticket_code'])->firstOrFail();

        $this->actingAs($this->counselorUser)
            ->patchJson("/api/v1/complaints/{$complaint->id}/status", [
                'status' => 'ditindaklanjuti',
                'response_text' => 'Pemanggilan siswa dan orang tua sudah dijadwalkan.',
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->reporterUser->id,
            'notif_type' => 'complaint.status_changed',
        ]);
    }

    /** Pelapor anonim tidak boleh dilacak lewat notifikasi. */
    public function test_pelapor_anonim_tidak_mendapat_notifikasi(): void
    {
        $data = $this->submit('bullying', true, $this->reporterUser);
        $complaint = Complaint::where('ticket_code', $data['ticket_code'])->firstOrFail();

        $this->actingAs($this->counselorUser)
            ->patchJson("/api/v1/complaints/{$complaint->id}/status", [
                'status' => 'ditindaklanjuti',
                'response_text' => 'Ditindaklanjuti tanpa menyentuh identitas pelapor.',
            ])
            ->assertStatus(200);

        $this->assertSame(0, Notification::where('notif_type', 'complaint.status_changed')->count());
        $this->assertNull($complaint->fresh()->reporter_user_id);
    }

    /** Aduan lewat tenggat dieskalasi otomatis ke Kepsek & BK. */
    public function test_aduan_lewat_tenggat_dieskalasi_otomatis(): void
    {
        $complaint = Complaint::create([
            'ticket_code' => 'ASP-20261127-LATE',
            'category' => 'bullying',
            'severity' => 'tinggi',
            'content' => 'Aduan perundungan yang terlewat tenggat penanganan.',
            'status' => 'baru',
            'due_at' => Carbon::now()->subHours(5),
        ]);

        $this->artisan('complaints:escalate-overdue')->assertSuccessful();

        $fresh = $complaint->fresh();
        $this->assertNotNull($fresh->escalated_at, 'Aduan terlambat harus ditandai eskalasi.');
        $this->assertSame('terlambat_berat', $fresh->sla_state);

        $this->assertDatabaseHas('complaint_events', [
            'complaint_id' => $complaint->id,
            'event_type' => 'escalated',
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->principalUser->id,
            'notif_type' => 'complaint.sla_breach',
        ]);
    }

    /** Aduan yang sudah ditutup tidak dieskalasi meski tenggat lewat. */
    public function test_aduan_selesai_tidak_dieskalasi(): void
    {
        $complaint = Complaint::create([
            'ticket_code' => 'ASP-20261127-DONE',
            'category' => 'bullying',
            'severity' => 'tinggi',
            'content' => 'Aduan yang sudah ditutup sebelum tenggat.',
            'status' => 'selesai',
            'due_at' => Carbon::now()->subDay(),
        ]);

        $this->artisan('complaints:escalate-overdue')->assertSuccessful();

        $this->assertNull($complaint->fresh()->escalated_at);
    }

    /** Halaman lacak menampilkan prioritas tanpa membocorkan isi aduan. */
    public function test_lacak_menampilkan_prioritas_tanpa_isi_aduan(): void
    {
        $data = $this->submit('bullying', false, $this->reporterUser);

        $response = $this->getJson('/api/v1/public/complaints/'.$data['ticket_code']);

        $response->assertStatus(200)
            ->assertJsonPath('data.severity', 'tinggi')
            ->assertJsonPath('data.content', null)
            ->assertJsonPath('data.content_redacted', true);

        $this->assertNotNull($response->json('data.due_at'));
    }

    /** Daftar aduan bisa disaring per tingkat keparahan. */
    public function test_daftar_aduan_dapat_disaring_per_severity(): void
    {
        Complaint::create([
            'ticket_code' => 'ASP-20261127-SEV1',
            'category' => 'bullying',
            'severity' => 'tinggi',
            'content' => 'Aduan prioritas tinggi untuk uji filter.',
            'status' => 'baru',
        ]);
        Complaint::create([
            'ticket_code' => 'ASP-20261127-SEV2',
            'category' => 'fasilitas',
            'severity' => 'sedang',
            'content' => 'Aduan prioritas sedang untuk uji filter.',
            'status' => 'baru',
        ]);

        $response = $this->actingAs($this->principalUser)
            ->getJson('/api/v1/complaints?severity=tinggi');

        $response->assertStatus(200);

        $codes = collect($response->json('data'))->pluck('ticket_code')->all();
        $this->assertContains('ASP-20261127-SEV1', $codes);
        $this->assertNotContains('ASP-20261127-SEV2', $codes);
    }
}
