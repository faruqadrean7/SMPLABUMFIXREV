<?php

namespace Tests\Feature\Storage;

use App\Models\AcademicYear;
use App\Models\Applicant;
use App\Models\ApplicantDocument;
use App\Models\File;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PPDBTrack;
use App\Models\PPDBWave;
use App\Models\Role;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeacherSalarySlip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FileStorageIdorTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacherAUser;

    protected User $teacherBUser;

    protected User $librarianUser;

    protected User $facilityUser;

    protected User $ppdbUser;

    protected User $bendaharaUser;

    protected User $applicantUser;

    protected User $studentUser;

    protected Teacher $teacherA;

    protected Student $student;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);
        $roleLibrarian = Role::firstOrCreate(['code' => 'librarian'], ['name' => 'Pustakawan', 'is_system' => true]);
        $roleFacility = Role::firstOrCreate(['code' => 'facility_staff'], ['name' => 'Staf Sarpras', 'is_system' => true]);
        $rolePpdb = Role::firstOrCreate(['code' => 'ppdb_committee'], ['name' => 'Panitia PPDB', 'is_system' => true]);
        $roleBendahara = Role::firstOrCreate(['code' => 'bendahara'], ['name' => 'Bendahara', 'is_system' => true]);
        $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);

        $this->teacherAUser = $this->makeUser('guru_a_idor', $roleTeacher);
        $this->teacherBUser = $this->makeUser('guru_b_idor', $roleTeacher);
        $this->librarianUser = $this->makeUser('pustakawan_idor', $roleLibrarian);
        $this->facilityUser = $this->makeUser('sarpras_idor', $roleFacility);
        $this->ppdbUser = $this->makeUser('ppdb_idor', $rolePpdb);
        $this->bendaharaUser = $this->makeUser('bendahara_idor', $roleBendahara);
        $this->applicantUser = $this->makeUser('pendaftar_idor', null);
        $this->studentUser = $this->makeUser('siswa_idor', $roleStudent);

        $this->teacherA = Teacher::create([
            'user_id' => $this->teacherAUser->id,
            'full_name' => 'Guru A Pemilik Slip',
            'nip' => '198101012010011001',
            'status' => 'tetap',
        ]);

        $this->student = Student::create([
            'user_id' => $this->studentUser->id,
            'nis' => '20267701',
            'nisn' => '0077001122',
            'nik_enc' => '3578010101017701',
            'full_name' => 'Siswa Tagihan Uji',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2012-01-01',
            'entry_date' => '2026-07-01',
            'status' => 'aktif',
        ]);
    }

    private function makeUser(string $username, ?Role $role): User
    {
        $user = User::create([
            'username' => $username,
            'email' => "{$username}@sekolah.sch.id",
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        if ($role) {
            $user->roles()->attach($role->id);
        }

        return $user;
    }

    public function test_p0_03_salary_slip_private_file_blocks_other_teachers_librarian_and_facility_staff(): void
    {
        $slipFile = File::create([
            'owner_id' => $this->teacherA->id,
            'owner_type' => 'Teacher',
            'bucket' => 'private',
            'object_key' => 'private/salary/2026/11/slip-guru-a.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 2048,
            'visibility' => 'privat',
        ]);

        TeacherSalarySlip::create([
            'teacher_id' => $this->teacherA->id,
            'period_year' => 2026,
            'period_month' => 11,
            'base_salary' => 4500000,
            'total_allowances' => 500000,
            'total_deductions' => 100000,
            'net_salary' => 4900000,
            'file_id' => $slipFile->id,
            'status' => 'published',
        ]);

        // Guru B, Pustakawan, Sarpras, Panitia PPDB WAJIB ditolak 403
        foreach ([$this->teacherBUser, $this->librarianUser, $this->facilityUser, $this->ppdbUser] as $unauthorized) {
            Sanctum::actingAs($unauthorized);
            $this->getJson("/api/v1/files/{$slipFile->id}/url")->assertStatus(403);
        }

        // Guru A pemilik slip dan Bendahara tetap diizinkan 200
        Sanctum::actingAs($this->teacherAUser);
        $this->getJson("/api/v1/files/{$slipFile->id}/url")->assertStatus(200);

        Sanctum::actingAs($this->bendaharaUser);
        $this->getJson("/api/v1/files/{$slipFile->id}/url")->assertStatus(200);
    }

    public function test_p0_03_ppdb_document_and_payment_proof_enforce_domain_isolation(): void
    {
        $ay = AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ]);
        $wave = PPDBWave::create([
            'academic_year_id' => $ay->id,
            'name' => 'Gelombang 1',
            'open_at' => now()->subDays(5),
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
        $applicant = Applicant::create([
            'wave_id' => $wave->id,
            'track_id' => $track->id,
            'user_id' => $this->applicantUser->id,
            'registration_no' => 'PPDB-2026-00001',
            'nik_enc' => '3578010101019999',
            'full_name' => 'Calon Siswa Uji',
            'birth_date' => '2013-05-05',
            'status' => 'submitted',
        ]);

        $ppdbFile = File::create([
            'owner_id' => $applicant->id,
            'owner_type' => 'Applicant',
            'bucket' => 'private',
            'object_key' => 'private/ppdb/2026/11/kk.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 3000,
            'visibility' => 'privat',
        ]);
        ApplicantDocument::create([
            'applicant_id' => $applicant->id,
            'document_type' => 'kk',
            'file_id' => $ppdbFile->id,
            'status' => 'pending',
        ]);

        // Guru B & Pustakawan tidak boleh akses dokumen PPDB
        Sanctum::actingAs($this->teacherBUser);
        $this->getJson("/api/v1/files/{$ppdbFile->id}/url")->assertStatus(403);
        Sanctum::actingAs($this->librarianUser);
        $this->getJson("/api/v1/files/{$ppdbFile->id}/url")->assertStatus(403);

        // Pendaftar pemilik & Panitia PPDB diizinkan
        Sanctum::actingAs($this->applicantUser);
        $this->getJson("/api/v1/files/{$ppdbFile->id}/url")->assertStatus(200);
        Sanctum::actingAs($this->ppdbUser);
        $this->getJson("/api/v1/files/{$ppdbFile->id}/url")->assertStatus(200);

        // Bukti bayar SPP
        $proofFile = File::create([
            'owner_id' => Str::uuid()->toString(),
            'owner_type' => 'User',
            'bucket' => 'private',
            'object_key' => 'private/finance/2026/11/bukti-spp.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 4000,
            'visibility' => 'privat',
        ]);
        $invoice = Invoice::create([
            'invoice_number' => 'INV/2026/11/0001',
            'student_id' => $this->student->id,
            'period' => '2026-11',
            'due_date' => now()->addDays(10),
            'total_amount' => 500000,
            'paid_amount' => 0,
            'status' => 'belum_bayar',
        ]);
        Payment::create([
            'invoice_id' => $invoice->id,
            'amount' => 500000,
            'channel' => 'Transfer Bank',
            'external_reference' => 'TRX-IDOR-001',
            'payment_date' => now()->toDateString(),
            'status' => 'menunggu_verifikasi',
            'proof_file_id' => $proofFile->id,
            'submitted_by' => $this->studentUser->id,
        ]);

        // Guru B & Panitia PPDB tidak boleh akses bukti bayar
        Sanctum::actingAs($this->teacherBUser);
        $this->getJson("/api/v1/files/{$proofFile->id}/url")->assertStatus(403);
        Sanctum::actingAs($this->ppdbUser);
        $this->getJson("/api/v1/files/{$proofFile->id}/url")->assertStatus(403);

        // Bendahara & Siswa pemilik invoice diizinkan
        Sanctum::actingAs($this->bendaharaUser);
        $this->getJson("/api/v1/files/{$proofFile->id}/url")->assertStatus(200);
        Sanctum::actingAs($this->studentUser);
        $this->getJson("/api/v1/files/{$proofFile->id}/url")->assertStatus(200);
    }

    public function test_p0_03_signed_url_ttl_is_strictly_clamped_between_30_and_300_seconds(): void
    {
        $file = File::create([
            'owner_id' => $this->studentUser->id,
            'owner_type' => 'User',
            'bucket' => 'private',
            'object_key' => 'private/2026/11/doc-ttl.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'visibility' => 'privat',
        ]);

        Sanctum::actingAs($this->studentUser);

        $resMax = $this->getJson("/api/v1/files/{$file->id}/url?ttl=315360000");
        $resMax->assertStatus(200)
            ->assertJsonPath('data.ttl_seconds', 300);

        $resMin = $this->getJson("/api/v1/files/{$file->id}/url?ttl=0");
        $resMin->assertStatus(200)
            ->assertJsonPath('data.ttl_seconds', 30);
    }
}
