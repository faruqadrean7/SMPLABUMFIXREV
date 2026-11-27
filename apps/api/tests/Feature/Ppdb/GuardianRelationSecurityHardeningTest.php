<?php

namespace Tests\Feature\Ppdb;

use App\Models\Applicant;
use App\Models\ApplicantParent;
use App\Models\Guardian;
use App\Models\Permission;
use App\Models\PPDBReRegistration;
use App\Models\PPDBTrack;
use App\Models\PPDBWave;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Batch 2B-S — P1-25: Pengujian keamanan relasi guardian-siswa lintas keluarga & lintas sekolah,
 * serta scoping track_id & tenggat cadangan pada escalateWaitlist.
 */
class GuardianRelationSecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected string $schoolA;

    protected string $schoolB;

    protected User $ppdbCommitteeA;

    protected User $ppdbCommitteeB;

    protected User $adminMasterA;

    protected PPDBWave $waveA;

    protected PPDBTrack $trackZonasi;

    protected PPDBTrack $trackPrestasi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->schoolA = (string) Str::uuid();
        $this->schoolB = (string) Str::uuid();

        $rolePpdb = Role::firstOrCreate(['code' => 'ppdb_committee'], ['name' => 'Panitia PPDB', 'is_system' => true]);
        $roleAdminTu = Role::firstOrCreate(['code' => 'admin_tu'], ['name' => 'Admin TU', 'is_system' => true]);
        Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);
        Role::firstOrCreate(['code' => 'guardian'], ['name' => 'Orang Tua / Wali Murid', 'is_system' => true]);

        $permVerify = Permission::firstOrCreate(['code' => 'ppdb.applicant.verify'], ['description' => 'Verifikasi PPDB']);
        $permSelect = Permission::firstOrCreate(['code' => 'ppdb.selection.run'], ['description' => 'Seleksi PPDB']);
        $permEnroll = Permission::firstOrCreate(['code' => 'ppdb.enroll'], ['description' => 'Enroll PPDB']);
        $permStudentRead = Permission::firstOrCreate(['code' => 'master.student.read'], ['description' => 'Baca Siswa']);
        $permStudentWrite = Permission::firstOrCreate(['code' => 'master.student.write'], ['description' => 'Tulis Siswa']);

        $rolePpdb->permissions()->syncWithoutDetaching([$permVerify->id, $permSelect->id, $permEnroll->id]);
        $roleAdminTu->permissions()->syncWithoutDetaching([$permStudentRead->id, $permStudentWrite->id]);

        $this->ppdbCommitteeA = $this->makeUser('p125_ppdb_a', $rolePpdb, $this->schoolA);
        $this->ppdbCommitteeB = $this->makeUser('p125_ppdb_b', $rolePpdb, $this->schoolB);
        $this->adminMasterA = $this->makeUser('p125_admintu_a', $roleAdminTu, $this->schoolA);

        $this->waveA = PPDBWave::create([
            'school_id' => $this->schoolA,
            'name' => 'Gelombang 1 P1-25',
            'open_at' => now()->subDays(20),
            'close_at' => now()->subDays(10),
            'result_publish_at' => now()->subDays(8),
            'status' => 'aktif',
        ]);

        $this->trackZonasi = PPDBTrack::create([
            'wave_id' => $this->waveA->id,
            'name' => 'Zonasi',
            'code' => 'ZON-125',
            'quota' => 5,
        ]);

        $this->trackPrestasi = PPDBTrack::create([
            'wave_id' => $this->waveA->id,
            'name' => 'Prestasi',
            'code' => 'PRE-125',
            'quota' => 5,
        ]);
    }

    protected function makeUser(string $username, Role $role, string $schoolId): User
    {
        $user = User::create([
            'school_id' => $schoolId,
            'username' => $username,
            'email' => "{$username}@sekolah.sch.id",
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_p1_25_enrollment_does_not_auto_link_existing_guardian_from_another_family_by_phone(): void
    {
        // Keluarga 1 sudah memiliki akun Guardian & Siswa eksisting dengan nomor HP 081234567890
        $guardianRole = Role::where('code', 'guardian')->firstOrFail();
        $existingGuardianUser = $this->makeUser('p125_ortu_keluarga1', $guardianRole, $this->schoolA);
        $existingGuardian = Guardian::create([
            'school_id' => $this->schoolA,
            'user_id' => $existingGuardianUser->id,
            'full_name' => 'Budi Santoso Keluarga 1',
            'phone' => '081234567890',
        ]);

        // Pendaftar dari Keluarga 2 menginput nomor HP yang sama (typo / upaya IDOR lintas keluarga)
        $applicant = Applicant::create([
            'wave_id' => $this->waveA->id,
            'track_id' => $this->trackZonasi->id,
            'registration_no' => 'PPDB-P125-0001',
            'full_name' => 'Calon Siswa Keluarga 2',
            'nik_enc' => '3573010101122501',
            'birth_date' => '2013-02-10',
            'status' => 'selected',
            'decision' => 'diterima',
        ]);
        ApplicantParent::create([
            'applicant_id' => $applicant->id,
            'parent_type' => 'ayah',
            'name' => 'Andi Wijaya Keluarga 2',
            'phone' => '081234567890',
        ]);
        PPDBReRegistration::create([
            'applicant_id' => $applicant->id,
            'confirmed_at' => now(),
            'status' => 'dikonfirmasi',
        ]);

        // Panitia sekolah B tidak boleh meng-enroll pendaftar sekolah A -> 403
        $this->actingAs($this->ppdbCommitteeB, 'sanctum')
            ->postJson("/api/v1/ppdb/applications/{$applicant->id}/enroll")
            ->assertStatus(403);

        // Panitia sekolah A meng-enroll pendaftar -> 200
        $this->app['auth']->forgetGuards();
        $resp = $this->actingAs($this->ppdbCommitteeA, 'sanctum')
            ->postJson("/api/v1/ppdb/applications/{$applicant->id}/enroll")
            ->assertStatus(200);

        $newStudentId = $resp->json('data.student.id');
        $newStudent = Student::with('guardians')->findOrFail($newStudentId);

        // KRITIS: Siswa Keluarga 2 TIDAK BOLEH tertaut ke Guardian Keluarga 1!
        $this->assertFalse(
            $newStudent->guardians->contains('id', $existingGuardian->id),
            'Enrollment tidak boleh menautkan siswa baru ke akun Guardian keluarga lain hanya berdasarkan nomor HP!'
        );
        $this->assertCount(1, $newStudent->guardians);
        $this->assertNotEquals($existingGuardianUser->id, $newStudent->guardians->first()->user_id);
        $this->assertSame($this->schoolA, $newStudent->guardians->first()->school_id);
    }

    public function test_p1_25_guardian_service_blocks_cross_school_student_guardian_attach_and_read(): void
    {
        $studentA = Student::create([
            'school_id' => $this->schoolA,
            'nis' => '202612501',
            'nisn' => '0012500001',
            'nik_enc' => '3573010101122502',
            'full_name' => 'Siswa Sekolah A',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2012-04-01',
            'status' => 'aktif',
            'entry_date' => '2026-07-01',
        ]);

        $guardianB = Guardian::create([
            'school_id' => $this->schoolB,
            'full_name' => 'Wali Sekolah B',
            'phone' => '081999999999',
        ]);

        // Admin Sekolah A tidak boleh membaca profil Wali Sekolah B -> 403
        $this->actingAs($this->adminMasterA, 'sanctum')
            ->getJson("/api/v1/guardians/{$guardianB->id}")
            ->assertStatus(403);

        // Admin Sekolah A tidak boleh menautkan Wali Sekolah B ke Siswa Sekolah A -> 403
        $this->actingAs($this->adminMasterA, 'sanctum')
            ->postJson("/api/v1/students/{$studentA->id}/guardians", [
                'guardian_id' => $guardianB->id,
                'relationship' => 'ayah',
                'is_primary' => true,
            ])
            ->assertStatus(403);

        $this->assertDatabaseMissing('student_guardians', [
            'student_id' => $studentA->id,
            'guardian_id' => $guardianB->id,
        ]);
    }

    public function test_p1_25_escalate_waitlist_respects_track_id_filter_and_protects_active_promoted_deadlines(): void
    {
        // Jalur Zonasi: 1 gugur (tanpa tenggat khusus), 1 baru dipromosikan (tenggat masih 2 hari lagi -> TIDAK boleh gugur), 1 cadangan
        $expiredZonasi = Applicant::create([
            'wave_id' => $this->waveA->id,
            'track_id' => $this->trackZonasi->id,
            'registration_no' => 'PPDB-Z-EXP',
            'full_name' => 'Zonasi Gugur',
            'nik_enc' => '3573010101122510',
            'birth_date' => '2013-01-01',
            'status' => 'selected',
            'decision' => 'diterima',
        ]);
        $activePromotedZonasi = Applicant::create([
            'wave_id' => $this->waveA->id,
            'track_id' => $this->trackZonasi->id,
            'registration_no' => 'PPDB-Z-PROMO',
            'full_name' => 'Zonasi Promosi Masih Aktif',
            'nik_enc' => '3573010101122511',
            'birth_date' => '2013-01-02',
            'status' => 'selected',
            'decision' => 'diterima',
            're_registration_deadline_at' => now()->addDays(2),
        ]);
        $waitlistZonasi = Applicant::create([
            'wave_id' => $this->waveA->id,
            'track_id' => $this->trackZonasi->id,
            'registration_no' => 'PPDB-Z-WAIT',
            'full_name' => 'Zonasi Cadangan 1',
            'nik_enc' => '3573010101122512',
            'birth_date' => '2013-01-03',
            'status' => 'waitlist',
            'decision' => 'cadangan',
            'rank' => 1,
        ]);

        // Jalur Prestasi: 1 kandidat belum daftar ulang — TIDAK boleh tersentuh saat escalateWaitlist hanya minta trackZonasi!
        $untouchedPrestasi = Applicant::create([
            'wave_id' => $this->waveA->id,
            'track_id' => $this->trackPrestasi->id,
            'registration_no' => 'PPDB-P-KEEP',
            'full_name' => 'Prestasi Tidak Boleh Tersentuh',
            'nik_enc' => '3573010101122513',
            'birth_date' => '2013-01-04',
            'status' => 'selected',
            'decision' => 'diterima',
        ]);

        // Panitia sekolah B -> 403
        $this->actingAs($this->ppdbCommitteeB, 'sanctum')
            ->postJson("/api/v1/ppdb/waves/{$this->waveA->id}/escalate-waitlist", [
                'confirmed' => true,
                'track_id' => $this->trackZonasi->id,
            ])
            ->assertStatus(403);

        // Panitia sekolah A menjalankan eskalasi HANYA untuk jalur Zonasi
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->ppdbCommitteeA, 'sanctum')
            ->postJson("/api/v1/ppdb/waves/{$this->waveA->id}/escalate-waitlist", [
                'confirmed' => true,
                'track_id' => $this->trackZonasi->id,
            ])
            ->assertStatus(200);

        $this->assertSame('rejected', $expiredZonasi->fresh()->status);
        $this->assertSame('selected', $activePromotedZonasi->fresh()->status);
        $this->assertSame('selected', $waitlistZonasi->fresh()->status);
        $this->assertSame('selected', $untouchedPrestasi->fresh()->status);
    }
}
