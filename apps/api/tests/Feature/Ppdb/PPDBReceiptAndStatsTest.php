<?php

namespace Tests\Feature\Ppdb;

use App\Models\Applicant;
use App\Models\Permission;
use App\Models\PPDBTrack;
use App\Models\PPDBWave;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PPDBReceiptAndStatsTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $candidateUser;

    protected User $otherCandidateUser;

    protected Role $adminRole;

    protected Role $guestRole;

    protected Permission $permReadApplicant;

    protected PPDBWave $wave1;

    protected PPDBWave $wave2;

    protected PPDBTrack $trackZonasi;

    protected PPDBTrack $trackPrestasi;

    protected Applicant $submittedApplicant;

    protected Applicant $draftApplicant;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Permissions & Roles
        $this->permReadApplicant = Permission::firstOrCreate(
            ['code' => 'ppdb.applicant.read'],
            ['description' => 'Melihat data formulir dan bukti pendaftaran PPDB']
        );

        $this->adminRole = Role::firstOrCreate(
            ['code' => 'ppdb_staff'],
            ['name' => 'Staf PPDB', 'is_system' => true]
        );
        $this->adminRole->permissions()->syncWithoutDetaching([$this->permReadApplicant->id]);

        $this->guestRole = Role::firstOrCreate(
            ['code' => 'guest'],
            ['name' => 'Tamu / Calon Siswa', 'is_system' => true]
        );

        // 2. Users
        $this->adminUser = User::create([
            'username' => 'staf_ppdb_09',
            'email' => 'staf09@labschool.sch.id',
            'phone' => '081300000001',
            'password_hash' => Hash::make('Secret123!'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($this->adminRole->id);

        $this->candidateUser = User::create([
            'username' => 'calon_siswa_09',
            'email' => 'calon09@example.com',
            'phone' => '081300000002',
            'password_hash' => Hash::make('Secret123!'),
            'status' => 'active',
        ]);
        $this->candidateUser->roles()->attach($this->guestRole->id);

        $this->otherCandidateUser = User::create([
            'username' => 'calon_lain_09',
            'email' => 'calon_lain09@example.com',
            'phone' => '081300000003',
            'password_hash' => Hash::make('Secret123!'),
            'status' => 'active',
        ]);
        $this->otherCandidateUser->roles()->attach($this->guestRole->id);

        // 3. Waves & Tracks
        $this->wave1 = PPDBWave::create([
            'name' => 'Gelombang 1 Reguler',
            'open_at' => Carbon::now()->subDays(15),
            'close_at' => Carbon::now()->addDays(10),
            'result_publish_at' => Carbon::now()->addDays(15),
            'status' => 'aktif',
        ]);

        $this->wave2 = PPDBWave::create([
            'name' => 'Gelombang 2 Prestasi',
            'open_at' => Carbon::now()->addDays(20),
            'close_at' => Carbon::now()->addDays(40),
            'result_publish_at' => Carbon::now()->addDays(45),
            'status' => 'nonaktif',
        ]);

        $this->trackZonasi = PPDBTrack::create([
            'wave_id' => $this->wave1->id,
            'code' => 'ZONASI',
            'name' => 'Jalur Zonasi Domisili',
            'quota' => 40,
        ]);

        $this->trackPrestasi = PPDBTrack::create([
            'wave_id' => $this->wave1->id,
            'code' => 'PRESTASI',
            'name' => 'Jalur Prestasi Akademik',
            'quota' => 20,
        ]);

        // 4. Applicants
        $this->submittedApplicant = Applicant::create([
            'wave_id' => $this->wave1->id,
            'track_id' => $this->trackZonasi->id,
            'user_id' => $this->candidateUser->id,
            'registration_no' => 'REG-2026-09001',
            'nik_enc' => '3201019999990001',
            'full_name' => 'Nadia Putri Maharani',
            'birth_date' => '2010-08-20',
            'status' => 'submitted',
            'step_data' => [
                'personal' => [
                    'full_name' => 'Nadia Putri Maharani',
                    'birth_date' => '2010-08-20',
                    'gender' => 'P',
                ],
            ],
        ]);

        $this->draftApplicant = Applicant::create([
            'wave_id' => $this->wave1->id,
            'track_id' => $this->trackPrestasi->id,
            'user_id' => $this->candidateUser->id,
            'registration_no' => 'REG-2026-DRAFT',
            'nik_enc' => '3201019999990002',
            'full_name' => 'Draf Siswa',
            'birth_date' => '2010-09-10',
            'status' => 'draft',
        ]);
    }

    /**
     * BE-PPDB-09: Candidate can get receipt data in JSON format.
     */
    public function test_be_ppdb_09_candidate_can_get_receipt_json(): void
    {
        $response = $this->actingAs($this->candidateUser)
            ->getJson("/api/v1/ppdb/applications/{$this->submittedApplicant->id}/receipt");

        $response->assertStatus(200)
            ->assertJsonPath('data.registration_no', 'REG-2026-09001')
            ->assertJsonPath('data.applicant.full_name', 'Nadia Putri Maharani')
            ->assertJsonPath('data.applicant.gender', 'P')
            ->assertJsonPath('data.applicant.status', 'submitted')
            ->assertJsonPath('data.track.code', 'ZONASI')
            ->assertJsonPath('data.wave.name', 'Gelombang 1 Reguler');

        $this->assertNotEmpty($response->json('data.qr_content'));
        $this->assertStringContainsString('PPDB-RECEIPT:REG-2026-09001:', $response->json('data.qr_content'));
        $this->assertNotEmpty($response->json('data.verification_url'));
        $this->assertNotEmpty($response->json('data.signature'));
    }

    /**
     * BE-PPDB-09: Candidate can download receipt document in PDF format.
     */
    public function test_be_ppdb_09_candidate_can_download_receipt_pdf(): void
    {
        $response = $this->actingAs($this->candidateUser)
            ->get("/api/v1/ppdb/applications/{$this->submittedApplicant->id}/receipt?format=pdf");

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringContainsString('attachment; filename="bukti-pendaftaran-REG-2026-09001.pdf"', $response->headers->get('Content-Disposition'));

        $content = $response->getContent();
        $this->assertStringStartsWith('%PDF-1.4', $content);
        $this->assertStringContainsString('%%EOF', $content);
        $this->assertStringContainsString('BUKTI PENDAFTARAN PPDB', $content);
        $this->assertStringContainsString('Nadia Putri Maharani', $content);
    }

    /**
     * BE-PPDB-09: Cannot get receipt for draft application.
     */
    public function test_be_ppdb_09_cannot_get_receipt_for_draft_application(): void
    {
        $response = $this->actingAs($this->candidateUser)
            ->getJson("/api/v1/ppdb/applications/{$this->draftApplicant->id}/receipt");

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'UNPROCESSABLE_ENTITY');
    }

    /**
     * BE-PPDB-09: Cannot access other candidate's receipt (IDOR protection).
     */
    public function test_be_ppdb_09_cannot_access_other_candidate_receipt_idor(): void
    {
        $response = $this->actingAs($this->otherCandidateUser)
            ->getJson("/api/v1/ppdb/applications/{$this->submittedApplicant->id}/receipt");

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    /**
     * BE-PPDB-09: Admin can access any applicant's receipt.
     */
    public function test_be_ppdb_09_admin_can_access_any_applicant_receipt(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/ppdb/applications/{$this->submittedApplicant->id}/receipt");

        $response->assertStatus(200)
            ->assertJsonPath('data.registration_no', 'REG-2026-09001');
    }

    /**
     * BE-PPDB-09: Unauthenticated guest cannot access receipt.
     */
    public function test_be_ppdb_09_unauthenticated_cannot_access_receipt(): void
    {
        $response = $this->getJson("/api/v1/ppdb/applications/{$this->submittedApplicant->id}/receipt");

        $response->assertStatus(401);
    }

    /**
     * BE-PPDB-09: Get PPDB statistics authenticated.
     */
    public function test_be_ppdb_09_get_ppdb_stats_authenticated(): void
    {
        // Add more applicants
        Applicant::create([
            'wave_id' => $this->wave1->id,
            'track_id' => $this->trackPrestasi->id,
            'user_id' => $this->otherCandidateUser->id,
            'registration_no' => 'REG-2026-09002',
            'nik_enc' => '3201019999990003',
            'full_name' => 'Budi Santoso',
            'birth_date' => '2010-01-15',
            'status' => 'selected',
            'decision' => 'diterima',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/ppdb/stats');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'summary' => ['total_applicants', 'submitted_applicants'],
                    'by_status',
                    'by_decision',
                    'by_track',
                    'by_day',
                    'active_wave',
                ],
            ]);

        $this->assertEquals(3, $response->json('data.summary.total_applicants'));
        $this->assertEquals(2, $response->json('data.summary.submitted_applicants'));
        $this->assertEquals(1, $response->json('data.by_decision.diterima'));
    }

    /**
     * BE-PPDB-09: Public stats endpoint accessible without PII leakage.
     */
    public function test_be_ppdb_09_get_ppdb_stats_public_without_pii(): void
    {
        $response = $this->getJson('/api/v1/public/ppdb/stats');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'summary',
                    'by_status',
                    'by_track',
                    'by_day',
                    'active_wave',
                ],
            ]);

        $content = json_encode($response->json());
        // Must NOT contain personal names, NIKs, or emails
        $this->assertStringNotContainsString('Nadia Putri Maharani', $content);
        $this->assertStringNotContainsString('3201019999990001', $content);
        $this->assertStringNotContainsString('calon09@example.com', $content);
    }

    /**
     * BE-PPDB-09: Filter statistics by wave_id.
     */
    public function test_be_ppdb_09_ppdb_stats_filtered_by_wave(): void
    {
        $trackWave2 = PPDBTrack::create([
            'wave_id' => $this->wave2->id,
            'code' => 'AFIRMASI',
            'name' => 'Jalur Afirmasi',
            'quota' => 15,
        ]);

        Applicant::create([
            'wave_id' => $this->wave2->id,
            'track_id' => $trackWave2->id,
            'registration_no' => 'REG-WAVE2-001',
            'nik_enc' => '3201019999990099',
            'full_name' => 'Siswa Wave 2',
            'birth_date' => '2010-02-10',
            'status' => 'submitted',
        ]);

        // Filter for wave 2 only
        $response = $this->getJson("/api/v1/public/ppdb/stats?wave_id={$this->wave2->id}");

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('data.summary.total_applicants'));
        $this->assertCount(1, $response->json('data.by_track'));
        $this->assertEquals('AFIRMASI', $response->json('data.by_track.0.code'));
    }
}
