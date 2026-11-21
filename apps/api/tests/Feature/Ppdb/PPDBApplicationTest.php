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
use Illuminate\Support\Str;
use Tests\TestCase;

class PPDBApplicationTest extends TestCase
{
    use RefreshDatabase;

    protected User $applicantUser1;

    protected User $applicantUser2;

    protected User $adminUser;

    protected Role $guestRole;

    protected Role $adminRole;

    protected Permission $permReadApplicant;

    protected string $schoolId;

    protected PPDBWave $activeWave;

    protected PPDBTrack $trackZonasi;

    protected PPDBTrack $trackPrestasi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->schoolId = Str::uuid()->toString();

        // 1. Roles & Permissions
        $this->permReadApplicant = Permission::create([
            'code' => 'ppdb.applicant.read',
            'description' => 'Melihat daftar seluruh pendaftar PPDB',
        ]);

        $this->adminRole = Role::create([
            'code' => 'ppdb_committee',
            'name' => 'Panitia PPDB',
            'is_system' => true,
        ]);
        $this->adminRole->permissions()->attach([$this->permReadApplicant->id]);

        $this->guestRole = Role::firstOrCreate(
            ['code' => 'guest'],
            [
                'name' => 'Tamu / Calon Siswa',
                'is_system' => true,
            ]
        );

        // 2. Users
        $this->applicantUser1 = User::create([
            'username' => 'calon_siswa_1',
            'email' => 'calon1@example.com',
            'phone' => '081234567001',
            'password_hash' => Hash::make('Secret123!'),
            'status' => 'active',
        ]);
        $this->applicantUser1->roles()->attach($this->guestRole->id);

        $this->applicantUser2 = User::create([
            'username' => 'calon_siswa_2',
            'email' => 'calon2@example.com',
            'phone' => '081234567002',
            'password_hash' => Hash::make('Secret123!'),
            'status' => 'active',
        ]);
        $this->applicantUser2->roles()->attach($this->guestRole->id);

        $this->adminUser = User::create([
            'username' => 'panitia_ppdb',
            'email' => 'panitia@labschool.sch.id',
            'phone' => '081234567009',
            'password_hash' => Hash::make('Secret123!'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($this->adminRole->id);

        // 3. Active Wave & Tracks
        $this->activeWave = PPDBWave::create([
            'school_id' => $this->schoolId,
            'name' => 'Gelombang 1 Reguler 2026',
            'open_at' => Carbon::now()->subDays(2),
            'close_at' => Carbon::now()->addDays(20),
            'result_publish_at' => Carbon::now()->addDays(25),
            'status' => 'aktif',
        ]);

        $this->trackZonasi = PPDBTrack::create([
            'wave_id' => $this->activeWave->id,
            'code' => 'ZONASI',
            'name' => 'Jalur Zonasi',
            'quota' => 60,
        ]);

        $this->trackPrestasi = PPDBTrack::create([
            'wave_id' => $this->activeWave->id,
            'code' => 'PRESTASI',
            'name' => 'Jalur Prestasi',
            'quota' => 40,
        ]);
    }

    public function test_be_ppdb_03_unauthenticated_cannot_access_applications(): void
    {
        $this->getJson('/api/v1/ppdb/applications')->assertUnauthorized();
        $this->postJson('/api/v1/ppdb/applications', [])->assertUnauthorized();
        $this->getJson('/api/v1/ppdb/applications/'.Str::uuid())->assertUnauthorized();
        $this->patchJson('/api/v1/ppdb/applications/'.Str::uuid().'/steps/personal', [])->assertUnauthorized();
        $this->postJson('/api/v1/ppdb/applications/'.Str::uuid().'/submit')->assertUnauthorized();
    }

    public function test_be_ppdb_03_applicant_can_create_initial_draft_application(): void
    {
        $payload = [
            'wave_id' => $this->activeWave->id,
            'track_id' => $this->trackZonasi->id,
            'nik' => '3579012345671111',
            'full_name' => 'Ahmad Rasyid',
            'birth_date' => '2010-06-15',
        ];

        $response = $this->actingAs($this->applicantUser1)
            ->postJson('/api/v1/ppdb/applications', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.full_name', 'Ahmad Rasyid');

        $applicantId = $response->json('data.id');
        $regNo = $response->json('data.registration_no');

        $this->assertStringStartsWith('DFT-', $regNo);

        $this->assertDatabaseHas('applicants', [
            'id' => $applicantId,
            'user_id' => $this->applicantUser1->id,
            'status' => 'draft',
            'full_name' => 'Ahmad Rasyid',
        ]);

        $this->assertDatabaseHas('applicant_status_history', [
            'applicant_id' => $applicantId,
            'from_status' => 'new',
            'to_status' => 'draft',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ppdb_application.created',
            'entity' => 'Applicant',
            'entity_id' => $applicantId,
        ]);
    }

    public function test_be_ppdb_03_cannot_create_application_if_wave_closed_or_nik_duplicate(): void
    {
        // 1. Closed wave
        $closedWave = PPDBWave::create([
            'school_id' => $this->schoolId,
            'name' => 'Gelombang Sudah Ditutup',
            'open_at' => Carbon::now()->subDays(30),
            'close_at' => Carbon::now()->subDays(5),
            'result_publish_at' => Carbon::now()->subDays(1),
            'status' => 'ditutup',
        ]);

        $trackClosed = PPDBTrack::create([
            'wave_id' => $closedWave->id,
            'code' => 'ZONASI-CLOSED',
            'name' => 'Zonasi Ditutup',
            'quota' => 10,
        ]);

        $resClosed = $this->actingAs($this->applicantUser1)->postJson('/api/v1/ppdb/applications', [
            'wave_id' => $closedWave->id,
            'track_id' => $trackClosed->id,
            'nik' => '3579012345672222',
            'full_name' => 'Anak Ditutup',
            'birth_date' => '2010-01-01',
        ]);
        $resClosed->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        // 2. Existing active applicant with same NIK
        Applicant::create([
            'wave_id' => $this->activeWave->id,
            'track_id' => $this->trackZonasi->id,
            'user_id' => $this->applicantUser1->id,
            'registration_no' => 'DFT-TEST-0001',
            'nik_enc' => '3579012345673333',
            'full_name' => 'Anak Pertama',
            'birth_date' => '2010-02-02',
            'status' => 'draft',
        ]);

        // Second user tries same NIK
        $resDuplicateNik = $this->actingAs($this->applicantUser2)->postJson('/api/v1/ppdb/applications', [
            'wave_id' => $this->activeWave->id,
            'track_id' => $this->trackZonasi->id,
            'nik' => '3579012345673333',
            'full_name' => 'Anak Kedua',
            'birth_date' => '2010-02-02',
        ]);
        $resDuplicateNik->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath('error.message', 'NIK ini sudah terdaftar dalam pendaftaran PPDB yang aktif');
    }

    public function test_be_ppdb_03_applicant_can_autosave_steps(): void
    {
        $applicant = Applicant::create([
            'wave_id' => $this->activeWave->id,
            'track_id' => $this->trackZonasi->id,
            'user_id' => $this->applicantUser1->id,
            'registration_no' => 'DFT-2026-STEP1',
            'nik_enc' => '3579012345674444',
            'full_name' => 'Faris Pratama',
            'birth_date' => '2010-05-12',
            'status' => 'draft',
            'step_data' => ['current_step' => 1],
        ]);

        // Step 1: Personal Data
        $resStep1 = $this->actingAs($this->applicantUser1)
            ->patchJson('/api/v1/ppdb/applications/'.$applicant->id.'/steps/personal', [
                'full_name' => 'Faris Pratama Al-Ghifari',
                'gender' => 'L',
                'birth_place' => 'Malang',
                'religion' => 'Islam',
                'address' => 'Jl. Veteran No. 10 Malang',
                'phone' => '081234567888',
            ]);

        $resStep1->assertOk()
            ->assertJsonPath('data.full_name', 'Faris Pratama Al-Ghifari')
            ->assertJsonPath('data.step_data.personal.birth_place', 'Malang');

        // Step 2: Parent Data
        $resStep2 = $this->actingAs($this->applicantUser1)
            ->patchJson('/api/v1/ppdb/applications/'.$applicant->id.'/steps/parent', [
                'parents' => [
                    [
                        'parent_type' => 'ayah',
                        'name' => 'Bambang Pratama',
                        'occupation' => 'PNS',
                        'income_range' => '5-10 juta',
                        'phone' => '081234567889',
                    ],
                    [
                        'parent_type' => 'ibu',
                        'name' => 'Sri Wahyuni',
                        'occupation' => 'Wiraswasta',
                        'income_range' => '3-5 juta',
                        'phone' => '081234567880',
                    ],
                ],
            ]);

        $resStep2->assertOk()
            ->assertJsonPath('data.parents.0.name', 'Bambang Pratama')
            ->assertJsonPath('data.parents.1.name', 'Sri Wahyuni');

        $this->assertDatabaseHas('applicant_parents', [
            'applicant_id' => $applicant->id,
            'name' => 'Bambang Pratama',
            'parent_type' => 'ayah',
        ]);

        // Step 3: School Data
        $resStep3 = $this->actingAs($this->applicantUser1)
            ->patchJson('/api/v1/ppdb/applications/'.$applicant->id.'/steps/school', [
                'school' => [
                    'name' => 'SMP Negeri 1 Malang',
                    'npsn' => '20533001',
                    'address' => 'Jl. Lawu No. 1 Malang',
                ],
            ]);

        $resStep3->assertOk()
            ->assertJsonPath('data.step_data.school.school.name', 'SMP Negeri 1 Malang');

        // Step 4: Track switch
        $resStep4 = $this->actingAs($this->applicantUser1)
            ->patchJson('/api/v1/ppdb/applications/'.$applicant->id.'/steps/track', [
                'track_id' => $this->trackPrestasi->id,
                'major_choice' => 'MIPA',
            ]);

        $resStep4->assertOk()
            ->assertJsonPath('data.track_id', $this->trackPrestasi->id)
            ->assertJsonPath('data.track.code', 'PRESTASI');
    }

    public function test_be_ppdb_03_submit_generates_unique_official_reg_no_and_locks_data(): void
    {
        $applicant = Applicant::create([
            'wave_id' => $this->activeWave->id,
            'track_id' => $this->trackZonasi->id,
            'user_id' => $this->applicantUser1->id,
            'registration_no' => 'DFT-2026-SUBMIT1',
            'nik_enc' => '3579012345675555',
            'full_name' => 'Dinda Kirana',
            'birth_date' => '2010-07-20',
            'status' => 'draft',
            'step_data' => [
                'personal' => ['nik' => '3579012345675555'],
                'parent' => ['filled' => true],
            ],
        ]);

        $applicant->parents()->create([
            'parent_type' => 'ibu',
            'name' => 'Dewi Sartika',
            'occupation' => 'Guru',
            'phone' => '081234567090',
        ]);

        $resSubmit = $this->actingAs($this->applicantUser1)
            ->postJson('/api/v1/ppdb/applications/'.$applicant->id.'/submit');

        $resSubmit->assertOk()
            ->assertJsonPath('data.status', 'submitted');

        $officialRegNo = $resSubmit->json('data.registration_no');
        $this->assertStringStartsWith('PPDB-'.Carbon::now()->format('Y').'-', $officialRegNo);

        $this->assertDatabaseHas('applicants', [
            'id' => $applicant->id,
            'status' => 'submitted',
            'registration_no' => $officialRegNo,
        ]);

        $this->assertDatabaseHas('applicant_status_history', [
            'applicant_id' => $applicant->id,
            'from_status' => 'draft',
            'to_status' => 'submitted',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ppdb_application.submitted',
            'entity' => 'Applicant',
            'entity_id' => $applicant->id,
        ]);
    }

    public function test_be_ppdb_03_data_locked_after_submit_unless_status_is_revision(): void
    {
        $applicant = Applicant::create([
            'wave_id' => $this->activeWave->id,
            'track_id' => $this->trackZonasi->id,
            'user_id' => $this->applicantUser1->id,
            'registration_no' => 'PPDB-2026-00001',
            'nik_enc' => '3579012345676666',
            'full_name' => 'Reza Rahardian',
            'birth_date' => '2010-09-10',
            'status' => 'submitted', // Locked
        ]);

        // 1. Attempt edit while status is 'submitted' -> 400
        $resBlocked = $this->actingAs($this->applicantUser1)
            ->patchJson('/api/v1/ppdb/applications/'.$applicant->id.'/steps/personal', [
                'full_name' => 'Reza Ganti Nama',
            ]);

        $resBlocked->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath('error.message', 'Data pendaftaran telah dikunci dan tidak dapat diubah kecuali dalam status revisi');

        // 2. Change status to 'revision'
        $applicant->status = 'revision';
        $applicant->save();

        // 3. Edit should now succeed
        $resAllowed = $this->actingAs($this->applicantUser1)
            ->patchJson('/api/v1/ppdb/applications/'.$applicant->id.'/steps/personal', [
                'full_name' => 'Reza Revisi Berhasil',
            ]);

        $resAllowed->assertOk()
            ->assertJsonPath('data.full_name', 'Reza Revisi Berhasil');
    }

    public function test_be_ppdb_03_idor_protection_prevents_viewing_or_editing_other_applicant(): void
    {
        $applicantUser1 = Applicant::create([
            'wave_id' => $this->activeWave->id,
            'track_id' => $this->trackZonasi->id,
            'user_id' => $this->applicantUser1->id,
            'registration_no' => 'DFT-USER-1',
            'nik_enc' => '3579012345677777',
            'full_name' => 'Data Siswa 1',
            'birth_date' => '2010-04-04',
            'status' => 'draft',
        ]);

        // User 2 attempts to view User 1's application -> 403
        $this->actingAs($this->applicantUser2)
            ->getJson('/api/v1/ppdb/applications/'.$applicantUser1->id)
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        // User 2 attempts to patch User 1's application -> 403
        $this->actingAs($this->applicantUser2)
            ->patchJson('/api/v1/ppdb/applications/'.$applicantUser1->id.'/steps/personal', [
                'full_name' => 'Hacked Name',
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        // Admin can view User 1's application -> 200 OK
        $this->actingAs($this->adminUser)
            ->getJson('/api/v1/ppdb/applications/'.$applicantUser1->id)
            ->assertOk()
            ->assertJsonPath('data.id', $applicantUser1->id);
    }

    public function test_be_ppdb_03_submit_sequence_increments_atomically(): void
    {
        $year = Carbon::now()->format('Y');

        // App 1
        $app1 = Applicant::create([
            'wave_id' => $this->activeWave->id,
            'track_id' => $this->trackZonasi->id,
            'user_id' => $this->applicantUser1->id,
            'registration_no' => 'DFT-SEQ-1',
            'nik_enc' => '3579012345678881',
            'full_name' => 'Calon Satu',
            'birth_date' => '2010-01-01',
            'status' => 'draft',
            'step_data' => ['parent' => ['filled' => true]],
        ]);

        // App 2
        $app2 = Applicant::create([
            'wave_id' => $this->activeWave->id,
            'track_id' => $this->trackZonasi->id,
            'user_id' => $this->applicantUser2->id,
            'registration_no' => 'DFT-SEQ-2',
            'nik_enc' => '3579012345678882',
            'full_name' => 'Calon Dua',
            'birth_date' => '2010-02-02',
            'status' => 'draft',
            'step_data' => ['parent' => ['filled' => true]],
        ]);

        $res1 = $this->actingAs($this->applicantUser1)->postJson('/api/v1/ppdb/applications/'.$app1->id.'/submit');
        $res1->assertOk();
        $regNo1 = $res1->json('data.registration_no');

        $res2 = $this->actingAs($this->applicantUser2)->postJson('/api/v1/ppdb/applications/'.$app2->id.'/submit');
        $res2->assertOk();
        $regNo2 = $res2->json('data.registration_no');

        $this->assertEquals("PPDB-{$year}-00001", $regNo1);
        $this->assertEquals("PPDB-{$year}-00002", $regNo2);
    }
}
