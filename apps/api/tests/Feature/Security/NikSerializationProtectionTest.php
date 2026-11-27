<?php

namespace Tests\Feature\Security;

use App\Models\AcademicYear;
use App\Models\Applicant;
use App\Models\Guardian;
use App\Models\Permission;
use App\Models\PPDBTrack;
use App\Models\PPDBWave;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NikSerializationProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected Student $student;

    protected Applicant $applicant;

    protected Guardian $guardian;

    protected function setUp(): void
    {
        parent::setUp();

        $permRead = Permission::firstOrCreate(['code' => 'master.student.read'], ['description' => 'Read student']);
        $roleAdmin = Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Admin', 'is_system' => true]);
        $roleAdmin->permissions()->syncWithoutDetaching([$permRead->id]);

        $this->adminUser = User::create([
            'username' => 'admin_nik_sec',
            'email' => 'admin_nik_sec@sekolah.sch.id',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($roleAdmin->id);

        $this->student = Student::create([
            'nis' => '20269101',
            'nisn' => '0091010101',
            'nik_enc' => '3578010101019101',
            'full_name' => 'Siswa Uji Proteksi NIK',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2012-01-01',
            'entry_date' => '2026-07-01',
            'status' => 'aktif',
        ]);

        $this->guardian = Guardian::create([
            'full_name' => 'Wali Uji Proteksi NIK',
            'phone' => '081234569101',
        ]);
        $this->guardian->students()->attach($this->student->id, ['relationship' => 'ayah', 'is_primary' => true]);

        $ay = AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ]);
        $wave = PPDBWave::create([
            'academic_year_id' => $ay->id,
            'name' => 'Gelombang 1',
            'open_at' => now()->subDays(2),
            'close_at' => now()->addDays(10),
            'result_publish_at' => now()->addDays(15),
            'status' => 'aktif',
        ]);
        $track = PPDBTrack::create([
            'wave_id' => $wave->id,
            'code' => 'reguler',
            'name' => 'Reguler',
            'quota' => 50,
            'is_active' => true,
        ]);
        $this->applicant = Applicant::create([
            'wave_id' => $wave->id,
            'track_id' => $track->id,
            'registration_no' => 'PPDB-2026-91001',
            'nik_enc' => '3578010101019102',
            'full_name' => 'Pendaftar Uji Proteksi NIK',
            'birth_date' => '2013-02-02',
            'status' => 'submitted',
        ]);
    }

    public function test_p0_05_student_and_applicant_models_hide_nik_enc_and_nik_hash_on_serialization(): void
    {
        $studentArray = $this->student->toArray();
        $this->assertArrayNotHasKey('nik_enc', $studentArray);
        $this->assertArrayNotHasKey('nik_hash', $studentArray);
        $this->assertStringNotContainsString('3578010101019101', $this->student->toJson());

        // Akses properti internal PHP tetap bekerja normal untuk layanan server-side
        $this->assertSame('3578010101019101', $this->student->nik_enc);
        $this->assertNotEmpty($this->student->nik_hash);

        $applicantArray = $this->applicant->toArray();
        $this->assertArrayNotHasKey('nik_enc', $applicantArray);
        $this->assertArrayNotHasKey('nik_hash', $applicantArray);
        $this->assertStringNotContainsString('3578010101019102', $this->applicant->toJson());
        $this->assertSame('3578010101019102', $this->applicant->nik_enc);
    }

    public function test_p0_05_api_endpoints_never_leak_plaintext_nik_enc_or_nik_hash(): void
    {
        Sanctum::actingAs($this->adminUser);

        $studentsRes = $this->getJson('/api/v1/students');
        $studentsRes->assertStatus(200);
        $this->assertStringNotContainsString('3578010101019101', $studentsRes->getContent());
        $this->assertStringNotContainsString('nik_enc', $studentsRes->getContent());
        $this->assertStringNotContainsString('nik_hash', $studentsRes->getContent());

        $guardiansRes = $this->getJson('/api/v1/guardians');
        $guardiansRes->assertStatus(200);
        $this->assertStringNotContainsString('3578010101019101', $guardiansRes->getContent());
        $this->assertStringNotContainsString('nik_enc', $guardiansRes->getContent());
        $this->assertStringNotContainsString('nik_hash', $guardiansRes->getContent());
    }
}
