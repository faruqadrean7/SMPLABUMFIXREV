<?php

namespace Tests\Feature\Ppdb;

use App\Models\Applicant;
use App\Models\Permission;
use App\Models\PPDBTrack;
use App\Models\PPDBWave;
use App\Models\Role;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PpdbAutosaveAndEnrollmentTier2ATest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private PPDBWave $wave;

    private PPDBTrack $track;

    private User $committeeUser;

    private User $applicantUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create([
            'name' => 'SMP Lab PPDB Tier 2A',
            'npsn' => '20534021',
            'address' => 'Jl. Fiktif PPDB No. 1',
            'phone' => '0341000021',
            'email' => 'ppdb2a@test.local',
        ]);

        $roleCommittee = Role::firstOrCreate(['code' => 'ppdb_committee'], ['name' => 'Panitia PPDB', 'is_system' => true]);
        foreach (['ppdb.applicant.read', 'ppdb.applicant.verify', 'ppdb.selection.run', 'ppdb.wave.manage'] as $code) {
            $p = Permission::firstOrCreate(['code' => $code], ['description' => $code]);
            $roleCommittee->permissions()->syncWithoutDetaching([$p->id]);
        }

        $this->committeeUser = User::create([
            'school_id' => $this->school->id,
            'username' => 'panitia_t2a_'.uniqid(),
            'email' => 'panitia_t2a_'.uniqid().'@test.local',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $this->committeeUser->roles()->attach($roleCommittee->id);

        $roleGuest = Role::firstOrCreate(['code' => 'guest'], ['name' => 'Tamu', 'is_system' => true]);
        $this->applicantUser = User::create([
            'school_id' => $this->school->id,
            'username' => 'calon_t2a_'.uniqid(),
            'email' => 'calon_t2a_'.uniqid().'@test.local',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $this->applicantUser->roles()->attach($roleGuest->id);

        $this->wave = PPDBWave::create([
            'school_id' => $this->school->id,
            'name' => 'Gelombang 1 2026',
            'status' => 'aktif',
            'open_at' => Carbon::now()->subDays(10),
            'close_at' => Carbon::now()->addDays(20),
            'result_publish_at' => Carbon::now()->addDays(25),
            'quota' => 50,
        ]);

        $this->track = PPDBTrack::create([
            'wave_id' => $this->wave->id,
            'code' => 'prestasi',
            'name' => 'Jalur Prestasi',
            'quota' => 50,
        ]);
    }

    public function test_p0_21_autosave_steps_1_to_4_persist_all_frontend_fields_without_dropping_data(): void
    {
        // Buat draf awal
        $createRes = $this->actingAs($this->applicantUser, 'sanctum')
            ->postJson('/api/v1/ppdb/applications', [
                'wave_id' => $this->wave->id,
                'track_id' => $this->track->id,
                'full_name' => 'Calon Siswa Fiktif',
                'nik' => '3573011205120001',
                'birth_date' => '2012-05-12',
            ])
            ->assertStatus(201);

        $appId = $createRes->json('data.id');

        // Step 1: personal (termasuk nisn & pembaruan nik tanpa membocorkan plaintext nik ke step_data)
        $this->actingAs($this->applicantUser, 'sanctum')
            ->patchJson("/api/v1/ppdb/applications/{$appId}/steps/personal", [
                'full_name' => 'Calon Siswa Fiktif',
                'nik' => '3573011205120002',
                'nisn' => '0012345678',
                'birth_place' => 'Malang',
                'birth_date' => '2012-05-12',
                'gender' => 'L',
                'religion' => 'Islam',
                'address' => 'Jl. Fiktif PPDB No. 12',
            ])
            ->assertStatus(200);

        // Step 2: parent (format flat FE + partial parent)
        $this->actingAs($this->applicantUser, 'sanctum')
            ->patchJson("/api/v1/ppdb/applications/{$appId}/steps/parent", [
                'father_name' => 'Budi Fiktif',
                'father_job' => 'Wiraswasta',
                'father_phone' => '081234567890',
                'mother_name' => 'Siti Fiktif',
                'mother_job' => 'Ibu Rumah Tangga',
                'mother_phone' => '081234567891',
                'income_range' => '3 - 5 Juta',
                'parents' => [
                    ['parent_type' => 'ayah', 'name' => 'Budi Fiktif', 'occupation' => 'Wiraswasta', 'phone' => '081234567890'],
                    ['parent_type' => 'ibu', 'name' => '', 'occupation' => '', 'phone' => ''],
                ],
            ])
            ->assertStatus(200);

        // Step 3: school (flat origin_school, npsn, graduation_year, average_score, rapor_details)
        $this->actingAs($this->applicantUser, 'sanctum')
            ->patchJson("/api/v1/ppdb/applications/{$appId}/steps/school", [
                'origin_school' => 'SDN Fiktif Malang 1',
                'npsn' => '20530001',
                'graduation_year' => '2026',
                'average_score' => '91.25',
                'rapor_details' => [
                    'indonesian' => '90',
                    'math' => '95',
                    'science' => '92',
                    'english' => '88',
                ],
            ])
            ->assertStatus(200);

        // Step 4: track (wave_id, track_id, notes)
        $this->actingAs($this->applicantUser, 'sanctum')
            ->patchJson("/api/v1/ppdb/applications/{$appId}/steps/track", [
                'wave_id' => $this->wave->id,
                'track_id' => $this->track->id,
                'notes' => 'Juara Olimpiade Matematika Kota',
            ])
            ->assertStatus(200);

        // Re-fetch aplikasi dan pastikan SEMUA field tersimpan utuh
        $applicant = Applicant::with('parents')->findOrFail($appId);
        $this->assertSame('3573011205120002', $applicant->nik_enc);
        $this->assertArrayNotHasKey('nik', $applicant->step_data['personal']);
        $this->assertSame('0012345678', $applicant->step_data['personal']['nisn']);
        $this->assertSame('Budi Fiktif', $applicant->step_data['parent']['father_name']);
        $this->assertSame('3 - 5 Juta', $applicant->step_data['parent']['income_range']);
        $this->assertSame('SDN Fiktif Malang 1', $applicant->step_data['school']['origin_school']);
        $this->assertSame('20530001', $applicant->step_data['school']['npsn']);
        $this->assertEquals(91.25, (float) $applicant->step_data['school']['average_score']);
        $this->assertEquals(95, (int) $applicant->step_data['school']['rapor_details']['math']);
        $this->assertSame('Juara Olimpiade Matematika Kota', $applicant->step_data['track']['notes']);
        $this->assertCount(1, $applicant->parents);
    }

    public function test_p0_23_waitlist_promotion_grants_fresh_re_registration_deadline_and_atomic_nis_generation(): void
    {
        // Set gelombang sudah lewat deadline 3 hari kerja sejak result_publish_at
        $this->wave->update([
            'result_publish_at' => Carbon::now()->subDays(10),
        ]);

        // Kandidat 1 (selected tapi tidak daftar ulang -> akan gugur saat eskalasi)
        $expiredCandidate = Applicant::create([
            'wave_id' => $this->wave->id,
            'track_id' => $this->track->id,
            'registration_no' => 'PPDB-2026-00001',
            'nik_enc' => '3573010101120010',
            'full_name' => 'Kandidat Gugur Fiktif',
            'birth_date' => '2012-01-01',
            'status' => 'selected',
            'decision' => 'diterima',
            'rank' => 1,
        ]);

        // Kandidat 2 (cadangan / waitlist -> akan dipromosikan)
        $waitlistCandidate = Applicant::create([
            'wave_id' => $this->wave->id,
            'track_id' => $this->track->id,
            'user_id' => $this->applicantUser->id,
            'registration_no' => 'PPDB-2026-00002',
            'nik_enc' => '3573010101120020',
            'full_name' => 'Kandidat Cadangan Fiktif',
            'birth_date' => '2012-02-02',
            'status' => 'waitlist',
            'decision' => 'cadangan',
            'rank' => 2,
            'step_data' => [
                'personal' => [
                    'gender' => 'P',
                    'birth_place' => 'Malang',
                    'religion' => 'Islam',
                    'address' => 'Jl. Fiktif Cadangan',
                ],
            ],
        ]);

        // 1. Panitia mengeksekusi promosi waitlist
        $this->actingAs($this->committeeUser, 'sanctum')
            ->postJson("/api/v1/ppdb/waves/{$this->wave->id}/escalate-waitlist", ['confirmed' => true])
            ->assertStatus(200);

        $waitlistCandidate->refresh();
        $this->assertSame('selected', $waitlistCandidate->status);
        $this->assertNotNull($waitlistCandidate->re_registration_deadline_at);
        $this->assertTrue($waitlistCandidate->re_registration_deadline_at->isFuture());

        // 2. Kandidat cadangan yang dipromosikan BERHASIL konfirmasi daftar ulang (tidak lagi 422 deadlock!)
        $this->actingAs($this->applicantUser, 'sanctum')
            ->postJson("/api/v1/ppdb/applications/{$waitlistCandidate->id}/re-register", [
                'notes' => 'Siap mendaftar ulang setelah promosi cadangan',
            ])
            ->assertStatus(200);

        // 3. Buat 1 siswa dengan NIS tertinggi (simulasi gap setelah penghapusan) untuk menguji MAX numerik
        $year = Carbon::now()->format('Y');
        Student::create([
            'school_id' => $this->school->id,
            'nis' => $year.'00005',
            'nisn' => '0088776655',
            'nik_enc' => '3573010101120099',
            'full_name' => 'Siswa Existing Gap',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2012-01-10',
            'religion' => 'Islam',
            'address' => 'Jl. Existing',
            'entry_date' => now()->toDateString(),
            'status' => 'aktif',
        ]);

        // 4. Panitia konversi menjadi siswa -> NIS harus {$year}00006 (bukan 00002), dan pemanggilan ulang bersifat idempoten
        $enroll1 = $this->actingAs($this->committeeUser, 'sanctum')
            ->postJson("/api/v1/ppdb/applications/{$waitlistCandidate->id}/enroll", [])
            ->assertStatus(200);

        $this->assertSame($year.'00006', $enroll1->json('data.student.nis'));
        $studentId = $enroll1->json('data.student.id');

        $enroll2 = $this->actingAs($this->committeeUser, 'sanctum')
            ->postJson("/api/v1/ppdb/applications/{$waitlistCandidate->id}/enroll", [])
            ->assertStatus(200)
            ->assertJsonPath('data.is_idempotent', true)
            ->assertJsonPath('data.student.id', $studentId);

        $this->assertSame(2, Student::count());
    }
}
