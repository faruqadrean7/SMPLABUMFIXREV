<?php

namespace Tests\Feature\Ppdb;

use App\Models\Applicant;
use App\Models\ApplicantParent;
use App\Models\PPDBTrack;
use App\Models\PPDBWave;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class PPDBApplicationDeadlineTest extends TestCase
{
    use RefreshDatabase;

    protected User $applicantUser;

    protected PPDBWave $wave;

    protected PPDBTrack $track;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(
            ['code' => 'guest'],
            ['name' => 'Tamu / Calon Siswa', 'is_system' => true]
        );

        $this->applicantUser = User::create([
            'username' => 'test_applicant_deadline',
            'email' => 'applicant_deadline@example.com',
            'phone' => '081234567999',
            'password_hash' => '$2y$10$abcdefghijklmnopqrstuvwxyz123456',
            'status' => 'active',
        ]);
        $this->applicantUser->roles()->attach($role->id);

        $this->wave = PPDBWave::create([
            'school_id' => Str::uuid()->toString(),
            'name' => 'Gelombang 1 Reguler',
            'academic_year' => '2026/2027',
            'status' => 'aktif',
            'open_at' => Carbon::now()->subDays(5),
            'close_at' => Carbon::now()->addDays(5),
            'result_publish_at' => Carbon::now()->addDays(20),
        ]);

        $this->track = PPDBTrack::create([
            'wave_id' => $this->wave->id,
            'code' => 'ZONASI',
            'name' => 'Zonasi',
            'quota' => 50,
            'is_active' => true,
        ]);
    }

    private function createReadyDraftApplicant(array $overrides = []): Applicant
    {
        $applicant = Applicant::create(array_merge([
            'wave_id' => $this->wave->id,
            'track_id' => $this->track->id,
            'user_id' => $this->applicantUser->id,
            'registration_no' => 'DFT-'.Str::upper(Str::random(8)),
            'nik_enc' => '3579012345679999',
            'full_name' => 'Budi Santoso',
            'birth_date' => '2010-01-01',
            'status' => 'draft',
            'step_data' => [
                'current_step' => 4,
                'personal' => ['address' => 'Jl. Pendidikan No. 1'],
            ],
        ], $overrides));

        ApplicantParent::create([
            'applicant_id' => $applicant->id,
            'parent_type' => 'ayah',
            'name' => 'Ayah Budi',
            'phone' => '081234567890',
        ]);

        return $applicant;
    }

    public function test_p0_sec_bl_01_successful_submit_on_active_wave_before_deadline(): void
    {
        Carbon::setTestNow(Carbon::now());
        $applicant = $this->createReadyDraftApplicant();

        $response = $this->actingAs($this->applicantUser)
            ->postJson("/api/v1/ppdb/applications/{$applicant->id}/submit");

        $response->assertOk()
            ->assertJsonPath('data.status', 'submitted');

        $this->assertDatabaseHas('applicants', [
            'id' => $applicant->id,
            'status' => 'submitted',
        ]);
        Carbon::setTestNow();
    }

    public function test_p0_sec_bl_01_rejection_after_close_at_deadline(): void
    {
        $applicant = $this->createReadyDraftApplicant();

        // Time traveled past deadline
        Carbon::setTestNow($this->wave->close_at->copy()->addMinute());

        $response = $this->actingAs($this->applicantUser)
            ->postJson("/api/v1/ppdb/applications/{$applicant->id}/submit");

        $response->assertStatus(400)
            ->assertJsonPath('error.message', 'Gelombang pendaftaran telah ditutup atau sedang tidak aktif');

        // Verify no mutation occurred (rollback)
        $this->assertDatabaseHas('applicants', [
            'id' => $applicant->id,
            'status' => 'draft',
            'registration_no' => $applicant->registration_no,
        ]);
        Carbon::setTestNow();
    }

    public function test_p0_sec_bl_01_rejection_before_open_at_schedule(): void
    {
        $applicant = $this->createReadyDraftApplicant();

        // Time traveled before wave opens
        Carbon::setTestNow($this->wave->open_at->copy()->subMinute());

        $response = $this->actingAs($this->applicantUser)
            ->postJson("/api/v1/ppdb/applications/{$applicant->id}/submit");

        $response->assertStatus(400)
            ->assertJsonPath('error.message', 'Gelombang pendaftaran telah ditutup atau sedang tidak aktif');

        $this->assertDatabaseHas('applicants', [
            'id' => $applicant->id,
            'status' => 'draft',
        ]);
        Carbon::setTestNow();
    }

    public function test_p0_sec_bl_01_rejection_when_wave_status_is_ditutup(): void
    {
        $this->wave->update(['status' => 'ditutup']);
        $applicant = $this->createReadyDraftApplicant();

        $response = $this->actingAs($this->applicantUser)
            ->postJson("/api/v1/ppdb/applications/{$applicant->id}/submit");

        $response->assertStatus(400)
            ->assertJsonPath('error.message', 'Gelombang pendaftaran telah ditutup atau sedang tidak aktif');

        $this->assertDatabaseHas('applicants', [
            'id' => $applicant->id,
            'status' => 'draft',
        ]);
    }

    public function test_p0_sec_bl_01_rejection_when_wave_status_is_draft(): void
    {
        $this->wave->update(['status' => 'draft']);
        $applicant = $this->createReadyDraftApplicant();

        $response = $this->actingAs($this->applicantUser)
            ->postJson("/api/v1/ppdb/applications/{$applicant->id}/submit");

        $response->assertStatus(400)
            ->assertJsonPath('error.message', 'Gelombang pendaftaran telah ditutup atau sedang tidak aktif');

        $this->assertDatabaseHas('applicants', [
            'id' => $applicant->id,
            'status' => 'draft',
        ]);
    }

    public function test_p0_sec_bl_01_rejection_when_wave_status_is_selesai(): void
    {
        $this->wave->update(['status' => 'selesai']);
        $applicant = $this->createReadyDraftApplicant();

        $response = $this->actingAs($this->applicantUser)
            ->postJson("/api/v1/ppdb/applications/{$applicant->id}/submit");

        $response->assertStatus(400)
            ->assertJsonPath('error.message', 'Gelombang pendaftaran telah ditutup atau sedang tidak aktif');

        $this->assertDatabaseHas('applicants', [
            'id' => $applicant->id,
            'status' => 'draft',
        ]);
    }

    public function test_p0_sec_bl_01_boundary_condition_exact_close_at_is_allowed(): void
    {
        $applicant = $this->createReadyDraftApplicant();

        // Exact close_at boundary
        Carbon::setTestNow($this->wave->close_at->copy());

        $response = $this->actingAs($this->applicantUser)
            ->postJson("/api/v1/ppdb/applications/{$applicant->id}/submit");

        $response->assertOk()
            ->assertJsonPath('data.status', 'submitted');

        $this->assertDatabaseHas('applicants', [
            'id' => $applicant->id,
            'status' => 'submitted',
        ]);
        Carbon::setTestNow();
    }

    public function test_p0_sec_bl_01_old_draft_submitted_after_deadline_is_rejected_without_mutation(): void
    {
        $oldRegistrationNo = 'DFT-OLD-DRAFT-99';
        $applicant = $this->createReadyDraftApplicant([
            'registration_no' => $oldRegistrationNo,
            'created_at' => Carbon::now()->subMonths(2),
        ]);

        // Shift time forward by 10 days (deadline passed)
        Carbon::setTestNow(Carbon::now()->addDays(10));

        $response = $this->actingAs($this->applicantUser)
            ->postJson("/api/v1/ppdb/applications/{$applicant->id}/submit");

        $response->assertStatus(400)
            ->assertJsonPath('error.message', 'Gelombang pendaftaran telah ditutup atau sedang tidak aktif');

        // Assert applicant still in draft and registration number untouched
        $fresh = $applicant->fresh();
        $this->assertEquals('draft', $fresh->status);
        $this->assertEquals($oldRegistrationNo, $fresh->registration_no);
        $this->assertNull($fresh->submitted_at);
        Carbon::setTestNow();
    }
}
