<?php

namespace Tests\Feature\Ppdb;

use App\Models\Applicant;
use App\Models\Notification;
use App\Models\Permission;
use App\Models\PPDBTrack;
use App\Models\PPDBWave;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class PPDBSelectionTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $applicantUser;

    protected Role $adminRole;

    protected Role $guestRole;

    protected Permission $permSelection;

    protected string $schoolId;

    protected PPDBWave $wave;

    protected PPDBTrack $track;

    protected function setUp(): void
    {
        parent::setUp();

        $this->schoolId = Str::uuid()->toString();

        // 1. Permissions & Roles
        $this->permSelection = Permission::create([
            'code' => 'ppdb.selection.run',
            'description' => 'Menjalankan proses seleksi dan perankingan PPDB',
        ]);

        $this->adminRole = Role::create([
            'code' => 'selection_committee',
            'name' => 'Panitia Seleksi PPDB',
            'is_system' => true,
        ]);
        $this->adminRole->permissions()->attach([$this->permSelection->id]);

        $this->guestRole = Role::firstOrCreate(
            ['code' => 'guest'],
            [
                'name' => 'Tamu / Calon Siswa',
                'is_system' => true,
            ]
        );

        // 2. Users
        $this->adminUser = User::create([
            'username' => 'panitia_seleksi',
            'email' => 'seleksi@labschool.sch.id',
            'phone' => '081234567700',
            'password_hash' => Hash::make('Secret123!'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($this->adminRole->id);

        $this->applicantUser = User::create([
            'username' => 'siswa_seleksi',
            'email' => 'siswa.seleksi@example.com',
            'phone' => '081234567701',
            'password_hash' => Hash::make('Secret123!'),
            'status' => 'active',
        ]);
        $this->applicantUser->roles()->attach($this->guestRole->id);

        // 3. Wave & Track (Quota: 2)
        $this->wave = PPDBWave::create([
            'school_id' => $this->schoolId,
            'name' => 'Gelombang 1 Reguler 2026',
            'open_at' => Carbon::now()->subDays(10),
            'close_at' => Carbon::now()->subDays(1),
            'result_publish_at' => Carbon::now()->addDays(5),
            'status' => 'aktif',
        ]);

        $this->track = PPDBTrack::create([
            'wave_id' => $this->wave->id,
            'code' => 'PRESTASI',
            'name' => 'Jalur Prestasi',
            'quota' => 2,
        ]);
    }

    public function test_be_ppdb_06_unauthenticated_cannot_access_selection(): void
    {
        $waveId = $this->wave->id;
        $appId = Str::uuid()->toString();

        $this->postJson("/api/v1/ppdb/waves/{$waveId}/rank")->assertUnauthorized();
        $this->getJson("/api/v1/ppdb/waves/{$waveId}/rankings")->assertUnauthorized();
        $this->patchJson("/api/v1/ppdb/applications/{$appId}/decision", [])->assertUnauthorized();
        $this->postJson("/api/v1/ppdb/waves/{$waveId}/publish-results")->assertUnauthorized();
    }

    public function test_be_ppdb_06_unauthorized_user_cannot_run_selection(): void
    {
        $waveId = $this->wave->id;
        $appId = Str::uuid()->toString();

        $this->actingAs($this->applicantUser)
            ->postJson("/api/v1/ppdb/waves/{$waveId}/rank")
            ->assertForbidden();

        $this->actingAs($this->applicantUser)
            ->patchJson("/api/v1/ppdb/applications/{$appId}/decision", ['decision' => 'diterima', 'reason' => 'Test'])
            ->assertForbidden();

        $this->actingAs($this->applicantUser)
            ->postJson("/api/v1/ppdb/waves/{$waveId}/publish-results")
            ->assertForbidden();
    }

    public function test_be_ppdb_06_auto_ranking_assigns_correct_ranks_and_decisions_by_quota(): void
    {
        // Create 5 verified applicants (Quota = 2, waitlist_quota = 1)
        $scores = [95.0, 90.0, 85.0, 80.0, 75.0];
        $applicants = [];

        foreach ($scores as $i => $score) {
            $user = User::create([
                'username' => "applicant_rank_{$i}",
                'email' => "rank_{$i}@example.com",
                'phone' => "08123450000{$i}",
                'password_hash' => Hash::make('Secret123!'),
                'status' => 'active',
            ]);

            $applicants[] = Applicant::create([
                'wave_id' => $this->wave->id,
                'track_id' => $this->track->id,
                'user_id' => $user->id,
                'registration_no' => "PPDB-2026-0000{$i}",
                'nik_enc' => "357901234567000{$i}",
                'full_name' => "Siswa Ranking {$i}",
                'birth_date' => '2010-05-15',
                'status' => 'verified',
                'score' => $score,
            ]);
        }

        // Run auto-ranking with waitlist_quota = 1
        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/v1/ppdb/waves/{$this->wave->id}/rank", [
                'track_id' => $this->track->id,
                'waitlist_quota' => 1,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.wave_id', $this->wave->id)
            ->assertJsonPath('data.tracks.0.quota', 2)
            ->assertJsonPath('data.tracks.0.selected', 2)
            ->assertJsonPath('data.tracks.0.waitlist', 1)
            ->assertJsonPath('data.tracks.0.rejected', 2);

        // Check applicant 0 (Score 95 -> Rank 1 -> diterima / selected)
        $this->assertDatabaseHas('applicants', [
            'id' => $applicants[0]->id,
            'rank' => 1,
            'decision' => 'diterima',
            'status' => 'selected',
        ]);

        // Check applicant 1 (Score 90 -> Rank 2 -> diterima / selected)
        $this->assertDatabaseHas('applicants', [
            'id' => $applicants[1]->id,
            'rank' => 2,
            'decision' => 'diterima',
            'status' => 'selected',
        ]);

        // Check applicant 2 (Score 85 -> Rank 3 -> cadangan / waitlist)
        $this->assertDatabaseHas('applicants', [
            'id' => $applicants[2]->id,
            'rank' => 3,
            'decision' => 'cadangan',
            'status' => 'waitlist',
        ]);

        // Check applicant 3 & 4 (Score 80 & 75 -> Rank 4 & 5 -> ditolak / rejected)
        $this->assertDatabaseHas('applicants', [
            'id' => $applicants[3]->id,
            'rank' => 4,
            'decision' => 'ditolak',
            'status' => 'rejected',
        ]);
        $this->assertDatabaseHas('applicants', [
            'id' => $applicants[4]->id,
            'rank' => 5,
            'decision' => 'ditolak',
            'status' => 'rejected',
        ]);

        // Verify status history created
        $this->assertDatabaseHas('applicant_status_history', [
            'applicant_id' => $applicants[0]->id,
            'from_status' => 'verified',
            'to_status' => 'selected',
        ]);

        // Verify audit log
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ppdb_selection.ranked',
            'entity' => 'PPDBWave',
            'entity_id' => $this->wave->id,
        ]);
    }

    public function test_be_ppdb_06_tie_breaker_orders_older_applicant_higher(): void
    {
        // 2 applicants with SAME score (88.00)
        // Older: 2009-03-10
        $olderApp = Applicant::create([
            'wave_id' => $this->wave->id,
            'track_id' => $this->track->id,
            'user_id' => $this->applicantUser->id,
            'registration_no' => 'PPDB-2026-TIE1',
            'nik_enc' => '3579012345671111',
            'full_name' => 'Siswa Lebih Tua',
            'birth_date' => '2009-03-10',
            'status' => 'verified',
            'score' => 88.00,
        ]);

        // Younger: 2010-09-20
        $youngerApp = Applicant::create([
            'wave_id' => $this->wave->id,
            'track_id' => $this->track->id,
            'user_id' => null,
            'registration_no' => 'PPDB-2026-TIE2',
            'nik_enc' => '3579012345672222',
            'full_name' => 'Siswa Lebih Muda',
            'birth_date' => '2010-09-20',
            'status' => 'verified',
            'score' => 88.00,
        ]);

        $this->actingAs($this->adminUser)
            ->postJson("/api/v1/ppdb/waves/{$this->wave->id}/rank")
            ->assertOk();

        // Older should get Rank 1, younger Rank 2
        $this->assertEquals(1, $olderApp->fresh()->rank);
        $this->assertEquals(2, $youngerApp->fresh()->rank);
    }

    public function test_be_ppdb_06_can_get_rankings_list(): void
    {
        Applicant::create([
            'wave_id' => $this->wave->id,
            'track_id' => $this->track->id,
            'registration_no' => 'PPDB-2026-R1',
            'nik_enc' => '3579012345673333',
            'full_name' => 'Juara Satu',
            'birth_date' => '2010-01-01',
            'status' => 'selected',
            'score' => 98.50,
            'rank' => 1,
            'decision' => 'diterima',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/ppdb/waves/{$this->wave->id}/rankings");

        $response->assertOk()
            ->assertJsonPath('data.wave_id', $this->wave->id)
            ->assertJsonPath('data.applicants.0.rank', 1)
            ->assertJsonPath('data.applicants.0.full_name', 'Juara Satu')
            ->assertJsonPath('data.applicants.0.decision', 'diterima');
    }

    public function test_be_ppdb_06_override_decision_records_reason_and_updates_status(): void
    {
        $applicant = Applicant::create([
            'wave_id' => $this->wave->id,
            'track_id' => $this->track->id,
            'user_id' => $this->applicantUser->id,
            'registration_no' => 'PPDB-2026-OVERRIDE',
            'nik_enc' => '3579012345674444',
            'full_name' => 'Siswa Cadangan',
            'birth_date' => '2010-06-01',
            'status' => 'waitlist',
            'score' => 84.00,
            'rank' => 3,
            'decision' => 'cadangan',
        ]);

        // Missing reason should fail validation
        $failRes = $this->actingAs($this->adminUser)
            ->patchJson("/api/v1/ppdb/applications/{$applicant->id}/decision", [
                'decision' => 'diterima',
            ]);
        $failRes->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        // Override decision with valid reason
        $response = $this->actingAs($this->adminUser)
            ->patchJson("/api/v1/ppdb/applications/{$applicant->id}/decision", [
                'decision' => 'diterima',
                'reason' => 'Pengalihan kuota afirmasi yang tidak terisi ke jalur prestasi',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.decision', 'diterima')
            ->assertJsonPath('data.status', 'selected');

        $this->assertDatabaseHas('applicants', [
            'id' => $applicant->id,
            'decision' => 'diterima',
            'status' => 'selected',
        ]);

        // Status history recorded
        $this->assertDatabaseHas('applicant_status_history', [
            'applicant_id' => $applicant->id,
            'from_status' => 'waitlist',
            'to_status' => 'selected',
            'note' => 'Penetapan keputusan manual menjadi diterima. Alasan: Pengalihan kuota afirmasi yang tidak terisi ke jalur prestasi',
        ]);

        // Audit log recorded
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ppdb_selection.decision_override',
            'entity' => 'Applicant',
            'entity_id' => $applicant->id,
        ]);
    }

    public function test_be_ppdb_06_publish_results_locks_time_and_notifies_applicants(): void
    {
        Applicant::create([
            'wave_id' => $this->wave->id,
            'track_id' => $this->track->id,
            'user_id' => $this->applicantUser->id,
            'registration_no' => 'PPDB-2026-PUBLISH',
            'nik_enc' => '3579012345675555',
            'full_name' => 'Siswa Diterima',
            'birth_date' => '2010-02-02',
            'status' => 'selected',
            'score' => 92.00,
            'rank' => 1,
            'decision' => 'diterima',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/v1/ppdb/waves/{$this->wave->id}/publish-results");

        $response->assertOk()
            ->assertJsonPath('data.wave_id', $this->wave->id)
            ->assertJsonPath('data.notified_applicants', 1);

        // Wave publish time updated
        $this->assertNotNull($this->wave->fresh()->result_publish_at);

        // Notification sent to applicant
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->applicantUser->id,
            'notif_type' => 'ppdb.result_published',
        ]);

        // Audit log created
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ppdb_selection.published',
            'entity' => 'PPDBWave',
            'entity_id' => $this->wave->id,
        ]);
    }

    /**
     * BE-PPDB-06 / Decision #1: Calculate scores with formula 60% rapor + 40% tes.
     */
    public function test_be_ppdb_06_rank_calculates_scores_with_formula_60_rapor_and_40_test(): void
    {
        $app = Applicant::create([
            'wave_id' => $this->wave->id,
            'track_id' => $this->track->id,
            'registration_no' => 'PPDB-2026-FORMULA',
            'nik_enc' => '3579012345676666',
            'full_name' => 'Siswa Uji Formula',
            'birth_date' => '2010-05-05',
            'status' => 'verified',
        ]);

        // 90 * 0.60 + 80 * 0.40 = 54 + 32 = 86.00
        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/v1/ppdb/waves/{$this->wave->id}/rank", [
                'scores' => [
                    [
                        'applicant_id' => $app->id,
                        'rapor_score' => 90,
                        'test_score' => 80,
                    ],
                ],
            ]);

        $response->assertOk();
        $this->assertEquals(86.00, (float) $app->fresh()->score);
    }

    /**
     * BE-PPDB-06 / Decision #3: Promote waitlist candidates after deadline with committee approval.
     */
    public function test_be_ppdb_06_escalate_waitlist_promotes_candidates_with_committee_approval(): void
    {
        $this->wave->update([
            'result_publish_at' => Carbon::now()->subWeekdays(5),
        ]);

        // Selected applicant without re-registration (expired)
        $selectedExpired = Applicant::create([
            'wave_id' => $this->wave->id,
            'track_id' => $this->track->id,
            'registration_no' => 'PPDB-2026-EXPIRED',
            'nik_enc' => '3579012345677777',
            'full_name' => 'Siswa Tidak Daftar Ulang',
            'birth_date' => '2010-01-01',
            'status' => 'selected',
            'score' => 95.00,
            'rank' => 1,
            'decision' => 'diterima',
        ]);

        // Waitlist candidate
        $waitlistCandidate = Applicant::create([
            'wave_id' => $this->wave->id,
            'track_id' => $this->track->id,
            'registration_no' => 'PPDB-2026-WAITLIST',
            'nik_enc' => '3579012345678888',
            'full_name' => 'Siswa Cadangan Nomor Satu',
            'birth_date' => '2010-02-02',
            'status' => 'waitlist',
            'score' => 85.00,
            'rank' => 2,
            'decision' => 'cadangan',
        ]);

        // Dry-run preview
        $previewResponse = $this->actingAs($this->adminUser)
            ->postJson("/api/v1/ppdb/waves/{$this->wave->id}/escalate-waitlist", [
                'confirmed' => false,
            ]);

        $previewResponse->assertOk()
            ->assertJsonPath('data.confirmed', false)
            ->assertJsonPath('data.tracks.0.vacant_slots', 1);

        // Status not changed on preview
        $this->assertEquals('selected', $selectedExpired->fresh()->status);
        $this->assertEquals('waitlist', $waitlistCandidate->fresh()->status);

        // Confirmed execution
        $confirmedResponse = $this->actingAs($this->adminUser)
            ->postJson("/api/v1/ppdb/waves/{$this->wave->id}/escalate-waitlist", [
                'confirmed' => true,
            ]);

        $confirmedResponse->assertOk()
            ->assertJsonPath('data.confirmed', true);

        // Expired applicant becomes rejected, waitlist candidate promoted to selected
        $this->assertEquals('rejected', $selectedExpired->fresh()->status);
        $this->assertEquals('ditolak', $selectedExpired->fresh()->decision);

        $this->assertEquals('selected', $waitlistCandidate->fresh()->status);
        $this->assertEquals('diterima', $waitlistCandidate->fresh()->decision);
    }

    /**
     * BE-PPDB-06 / Decision #3: Cannot escalate waitlist before deadline unless force is true.
     */
    public function test_be_ppdb_06_cannot_escalate_waitlist_before_deadline_unless_forced(): void
    {
        // Wave result_publish_at set to today (deadline is 3 business days ahead)
        $this->wave->update([
            'result_publish_at' => Carbon::now(),
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/v1/ppdb/waves/{$this->wave->id}/escalate-waitlist", [
                'confirmed' => true,
            ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Batas waktu konfirmasi daftar ulang belum berakhir', $response->json('error.message'));

        // Can bypass with force: true
        $forceResponse = $this->actingAs($this->adminUser)
            ->postJson("/api/v1/ppdb/waves/{$this->wave->id}/escalate-waitlist", [
                'confirmed' => true,
                'force' => true,
            ]);

        $forceResponse->assertOk();
    }
}
