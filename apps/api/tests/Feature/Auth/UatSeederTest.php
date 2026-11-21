<?php

namespace Tests\Feature\Auth;

use App\Models\AcademicYear;
use App\Models\Applicant;
use App\Models\Booking;
use App\Models\ClassStudent;
use App\Models\Facility;
use App\Models\Guardian;
use App\Models\LeaveRequest;
use App\Models\PPDBTrack;
use App\Models\PPDBWave;
use App\Models\ReportCard;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\SiteSetting;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use Database\Seeders\UatStagingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UatSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        App::detectEnvironment(fn () => 'testing');
        putenv('UAT_SEED_ENABLED');
        putenv('UAT_STAGING_PASSWORD');
        putenv('UAT_DEFAULT_PASSWORD');
        putenv('UAT_SCHOOL_ID');
        putenv('UAT_FORCE_RESET_PASSWORDS');
        putenv('ALLOW_PREDICTABLE_UAT_PASSWORD');
        unset(
            $_ENV['UAT_SEED_ENABLED'],
            $_ENV['UAT_STAGING_PASSWORD'],
            $_ENV['UAT_DEFAULT_PASSWORD'],
            $_ENV['UAT_SCHOOL_ID'],
            $_ENV['UAT_FORCE_RESET_PASSWORDS'],
            $_ENV['ALLOW_PREDICTABLE_UAT_PASSWORD'],
            $_SERVER['UAT_SEED_ENABLED'],
            $_SERVER['UAT_STAGING_PASSWORD'],
            $_SERVER['UAT_DEFAULT_PASSWORD'],
            $_SERVER['UAT_SCHOOL_ID'],
            $_SERVER['UAT_FORCE_RESET_PASSWORDS'],
            $_SERVER['ALLOW_PREDICTABLE_UAT_PASSWORD']
        );

        parent::tearDown();
    }

    /**
     * UAT-SEED-01: Verifikasi eksekusi seeder di testing environment dan keberadaan 12 persona.
     */
    public function test_uat_seed_01_seeder_executes_successfully_and_creates_all_12_personas(): void
    {
        $this->seed(UatStagingSeeder::class);

        $expectedPersonas = [
            'admin.staging' => [
                'email' => 'admin.staging@smp-labum.sch.id',
                'roles' => ['super_admin'],
            ],
            'kepsek.staging' => [
                'email' => 'kepsek.staging@smp-labum.sch.id',
                'roles' => ['principal'],
            ],
            'kurikulum.staging' => [
                'email' => 'kurikulum.staging@smp-labum.sch.id',
                'roles' => ['admin_tu', 'teacher', 'admin_kurikulum', 'kurikulum'],
            ],
            'walas.7a.staging' => [
                'email' => 'walas.7a.staging@smp-labum.sch.id',
                'roles' => ['homeroom', 'teacher'],
            ],
            'guru.mtk.staging' => [
                'email' => 'guru.mtk.staging@smp-labum.sch.id',
                'roles' => ['teacher'],
            ],
            'bk.staging' => [
                'email' => 'bk.staging@smp-labum.sch.id',
                'roles' => ['counselor'],
            ],
            'tu.staging' => [
                'email' => 'tu.staging@smp-labum.sch.id',
                'roles' => ['admin_tu'],
            ],
            'sarpras.staging' => [
                'email' => 'sarpras.staging@smp-labum.sch.id',
                'roles' => ['facility_staff'],
            ],
            'ppdb.panitia' => [
                'email' => 'ppdb.panitia@smp-labum.sch.id',
                'roles' => ['ppdb_committee'],
            ],
            'siswa.lab.001' => [
                'email' => 'siswa.lab.001@smp-labum.sch.id',
                'roles' => ['student'],
            ],
            'ortu.siswa.001' => [
                'email' => 'ortu.siswa.001@smp-labum.sch.id',
                'roles' => ['guardian'],
            ],
            'calon.ppdb.01' => [
                'email' => 'calon.ppdb.01@gmail.com',
                'roles' => ['guest'],
            ],
            'kesiswaan.staging' => [
                'email' => 'kesiswaan.staging@smp-labum.sch.id',
                'roles' => ['student_affairs'],
            ],
            'humas.staging' => [
                'email' => 'humas.staging@smp-labum.sch.id',
                'roles' => ['public_relations'],
            ],
        ];

        $defaultPassword = 'SistLabUM2026!UAT';

        foreach ($expectedPersonas as $username => $data) {
            $user = User::where('username', $username)->first();
            $this->assertNotNull($user, "Pengguna UAT {$username} harus ditemukan.");
            $this->assertEquals($data['email'], $user->email);
            $this->assertEquals('active', $user->status);
            $this->assertFalse($user->must_change_password);
            $this->assertTrue(Hash::check($defaultPassword, $user->password_hash), "Password untuk {$username} harus ter-hash valid.");

            foreach ($data['roles'] as $roleCode) {
                $this->assertTrue($user->hasRole($roleCode), "Pengguna {$username} harus memiliki peran {$roleCode}.");
            }
        }
    }

    /**
     * UAT-SEED-01: Verifikasi relasi akademik Kelas 7-A, Wali Kelas, Guru Mapel, dan Siswa.
     */
    public function test_uat_seed_01_academic_and_class_assignments_are_properly_linked(): void
    {
        $this->seed(UatStagingSeeder::class);

        $academicYear = AcademicYear::where('name', '2026/2027')->firstOrFail();
        $this->assertTrue($academicYear->is_active);

        $semester = Semester::where('academic_year_id', $academicYear->id)->where('name', 'Ganjil')->firstOrFail();
        $this->assertTrue($semester->is_active);

        $class7A = SchoolClass::where('name', '7-A')->firstOrFail();
        $this->assertEquals($academicYear->id, $class7A->academic_year_id);

        // Homeroom Teacher
        $walasUser = User::where('username', 'walas.7a.staging')->firstOrFail();
        $walasTeacher = Teacher::where('user_id', $walasUser->id)->firstOrFail();
        $this->assertEquals($walasTeacher->id, $class7A->homeroom_teacher_id);

        // Subject & Teaching Assignment
        $subjectMtk = Subject::where('code', 'MTK-7')->firstOrFail();
        $guruMtkUser = User::where('username', 'guru.mtk.staging')->firstOrFail();
        $guruMtkTeacher = Teacher::where('user_id', $guruMtkUser->id)->firstOrFail();

        $assignment = TeachingAssignment::where([
            'semester_id' => $semester->id,
            'class_id' => $class7A->id,
            'subject_id' => $subjectMtk->id,
            'teacher_id' => $guruMtkTeacher->id,
        ])->first();
        $this->assertNotNull($assignment, 'Penugasan mengajar Matematika kelas 7-A harus terbentuk.');

        // Student Enrollment in 7-A
        $siswaUser = User::where('username', 'siswa.lab.001')->firstOrFail();
        $student = Student::where('user_id', $siswaUser->id)->firstOrFail();
        $this->assertEquals('26001', $student->nis);
        $this->assertEquals('0099001001', $student->nisn);
        $this->assertEquals('aktif', $student->status);

        $enrollment = ClassStudent::where([
            'class_id' => $class7A->id,
            'student_id' => $student->id,
        ])->first();
        $this->assertNotNull($enrollment, 'Siswa 26001 harus terdaftar di rombel kelas 7-A.');
    }

    /**
     * UAT-SEED-01: Verifikasi relasi Siswa dan Orang Tua / Wali.
     */
    public function test_uat_seed_01_student_and_guardian_relations_are_properly_linked(): void
    {
        $this->seed(UatStagingSeeder::class);

        $siswaUser = User::where('username', 'siswa.lab.001')->firstOrFail();
        $student = Student::where('user_id', $siswaUser->id)->firstOrFail();

        $ortuUser = User::where('username', 'ortu.siswa.001')->firstOrFail();
        $guardian = Guardian::where('user_id', $ortuUser->id)->firstOrFail();

        $this->assertTrue(
            $student->guardians()->where('guardian_id', $guardian->id)->exists(),
            'Relasi student-guardian harus terhubung pada tabel pivot student_guardians.'
        );

        $pivot = $student->guardians()->where('guardian_id', $guardian->id)->first()->pivot;
        $this->assertEquals('ayah', $pivot->relationship);
        $this->assertTrue((bool) $pivot->is_primary);
    }

    /**
     * UAT-SEED-01: Verifikasi calon siswa PPDB dan berkas pendaftaran gelombang 1.
     */
    public function test_uat_seed_01_ppdb_applicant_data_is_properly_initialized(): void
    {
        $this->seed(UatStagingSeeder::class);

        $wave = PPDBWave::where('name', 'Gelombang 1')->firstOrFail();
        $track = PPDBTrack::where('wave_id', $wave->id)->where('code', 'prestasi')->firstOrFail();

        $calonUser = User::where('username', 'calon.ppdb.01')->firstOrFail();
        $applicant = Applicant::where('user_id', $calonUser->id)->firstOrFail();

        $this->assertEquals($wave->id, $applicant->wave_id);
        $this->assertEquals($track->id, $applicant->track_id);
        $this->assertEquals('PPDB-2026-0001', $applicant->registration_no);
        $this->assertEquals('submitted', $applicant->status);
        $this->assertEquals(87.50, (float) $applicant->score);
        $this->assertEquals(1, $applicant->rank);
    }

    /**
     * UAT-SEED-01: Verifikasi antrean persetujuan eksekutif (LeaveRequest, Booking, ReportCard) dengan status kanonis.
     */
    public function test_uat_seed_01_initial_approval_queue_records_have_canonical_statuses(): void
    {
        $this->seed(UatStagingSeeder::class);

        // 1. Leave Request
        $leave = LeaveRequest::where('leave_type', 'izin')->firstOrFail();
        $this->assertEquals('menunggu', $leave->status);
        $this->assertNull($leave->decided_by);

        // 2. Facility Booking
        $facility = Facility::where('name', 'Aula Graha Cakrawala Mini')->firstOrFail();
        $booking = Booking::where('facility_id', $facility->id)->firstOrFail();
        $this->assertEquals('diajukan', $booking->status);
        $this->assertNull($booking->decided_by);

        // 3. Locked Report Card
        $report = ReportCard::where('verify_token', 'UAT-RAPOR-7A-001')->firstOrFail();
        $this->assertEquals('terkunci', $report->status);
        $this->assertNull($report->approved_by);
        $this->assertNotNull($report->data_snapshot);
    }

    /**
     * UAT-SEED-01: Verifikasi idempotensi seeder (pemanggilan berulang tidak menduplikasi data).
     */
    public function test_uat_seed_01_seeder_is_fully_idempotent(): void
    {
        // Jalankan seeder 2 kali berturut-turut
        $this->seed(UatStagingSeeder::class);
        $this->seed(UatStagingSeeder::class);

        $usernames = [
            'admin.staging',
            'kepsek.staging',
            'kurikulum.staging',
            'walas.7a.staging',
            'guru.mtk.staging',
            'bk.staging',
            'tu.staging',
            'sarpras.staging',
            'ppdb.panitia',
            'siswa.lab.001',
            'ortu.siswa.001',
            'calon.ppdb.01',
        ];

        foreach ($usernames as $username) {
            $count = User::where('username', $username)->count();
            $this->assertEquals(1, $count, "Pengguna {$username} harus tepat 1 record (idempoten).");
        }

        $this->assertEquals(1, SchoolClass::where('name', '7-A')->count());
        $this->assertEquals(1, Subject::where('code', 'MTK-7')->count());
        $this->assertEquals(1, PPDBWave::where('name', 'Gelombang 1')->count());
        $this->assertEquals(1, Facility::where('name', 'Aula Graha Cakrawala Mini')->count());
        $this->assertEquals(1, ReportCard::where('verify_token', 'UAT-RAPOR-7A-001')->count());
    }

    /**
     * UAT-SEED-01: Verifikasi guard keamanan menolak eksekusi pada environment production.
     */
    public function test_uat_seed_01_safety_guard_blocks_production_environment(): void
    {
        App::detectEnvironment(fn () => 'production');
        $this->assertEquals('production', app()->environment());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('UatStagingSeeder dilarang keras dijalankan pada lingkungan production!');

        $seeder = new UatStagingSeeder;
        $seeder->run();
    }

    /**
     * UAT-SEED-01: Verifikasi guard keamanan menolak eksekusi di staging tanpa opt-in eksplisit.
     */
    public function test_uat_seed_01_safety_guard_requires_opt_in_outside_testing(): void
    {
        App::detectEnvironment(fn () => 'staging');
        $this->assertEquals('staging', app()->environment());

        // Tanpa opt-in UAT_SEED_ENABLED
        putenv('UAT_SEED_ENABLED=false');
        $_ENV['UAT_SEED_ENABLED'] = 'false';
        $_SERVER['UAT_SEED_ENABLED'] = 'false';

        try {
            $seeder = new UatStagingSeeder;
            $seeder->checkEnvironmentSafety();
            $this->fail('Harus melempar RuntimeException ketika UAT_SEED_ENABLED tidak aktif di staging.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('hanya boleh dijalankan di lingkungan staging/local dengan opt-in UAT_SEED_ENABLED=true', $e->getMessage());
        }

        // Dengan opt-in UAT_SEED_ENABLED=true
        putenv('UAT_SEED_ENABLED=true');
        $_ENV['UAT_SEED_ENABLED'] = 'true';
        $_SERVER['UAT_SEED_ENABLED'] = 'true';

        // Tidak boleh melempar exception
        $seeder->checkEnvironmentSafety();
        $this->assertTrue(true, 'Kepatuhan staging opt-in berhasil lolos.');
    }

    // =========================================================================
    // UAT-SEED-02: Hardened Security and Preflight Verification Tests
    // =========================================================================

    /**
     * UAT-SEED-02: Password staging wajib disediakan melalui konfigurasi yang disetujui (fail-fast jika kosong atau predictable default).
     */
    public function test_uat_seed_02_staging_password_must_be_provided_via_approved_config(): void
    {
        App::detectEnvironment(fn () => 'staging');
        putenv('UAT_SEED_ENABLED=true');
        $_ENV['UAT_SEED_ENABLED'] = 'true';
        $seeder = new UatStagingSeeder;

        // 1. Password kosong di staging -> Exception
        putenv('UAT_STAGING_PASSWORD=');
        putenv('UAT_DEFAULT_PASSWORD=');
        unset($_ENV['UAT_STAGING_PASSWORD'], $_ENV['UAT_DEFAULT_PASSWORD']);

        try {
            $seeder->resolveUatPassword();
            $this->fail('Harus melempar RuntimeException jika password tidak dikonfigurasikan di staging.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('kata sandi akun UAT wajib disediakan secara eksplisit', $e->getMessage());
        }

        // 2. Password menggunakan fallback publik terprediksi 'SistLabUM2026!UAT' tanpa izin -> Exception
        putenv('UAT_STAGING_PASSWORD=SistLabUM2026!UAT');
        $_ENV['UAT_STAGING_PASSWORD'] = 'SistLabUM2026!UAT';

        try {
            $seeder->resolveUatPassword();
            $this->fail('Harus melempar RuntimeException jika password staging menggunakan default publik tanpa izin.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('penggunaan kata sandi default publik tidak diizinkan', $e->getMessage());
        }

        // 3. Password rahasia staging unik yang disetujui -> Lolos
        putenv('UAT_STAGING_PASSWORD=RahasiaStagingSISTLABUM#2026!Secure');
        $_ENV['UAT_STAGING_PASSWORD'] = 'RahasiaStagingSISTLABUM#2026!Secure';

        $password = $seeder->resolveUatPassword();
        $this->assertEquals('RahasiaStagingSISTLABUM#2026!Secure', $password);
    }

    /**
     * UAT-SEED-02: Konfigurasi tenant yang hilang, tidak valid, atau berkonflik menyebabkan kegagalan aman (fail-closed).
     */
    public function test_uat_seed_02_missing_or_invalid_tenant_config_fails_safely_in_staging(): void
    {
        App::detectEnvironment(fn () => 'staging');
        putenv('UAT_SEED_ENABLED=true');
        $_ENV['UAT_SEED_ENABLED'] = 'true';
        putenv('UAT_STAGING_PASSWORD=RahasiaStagingSISTLABUM#2026!Secure');
        $_ENV['UAT_STAGING_PASSWORD'] = 'RahasiaStagingSISTLABUM#2026!Secure';

        $seeder = new UatStagingSeeder;

        // 1. Tenant ID tidak ada dan tidak ada SiteSetting -> Exception
        putenv('UAT_SCHOOL_ID=');
        putenv('DEFAULT_SCHOOL_ID=');
        unset($_ENV['UAT_SCHOOL_ID'], $_ENV['DEFAULT_SCHOOL_ID']);
        SiteSetting::query()->delete();

        try {
            $seeder->resolveTenantSchoolId();
            $this->fail('Harus melempar RuntimeException jika school_id tidak ditemukan di staging.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Identitas tenant/school_id staging belum terdefinisi', $e->getMessage());
        }

        // 2. Format UUID tidak valid -> Exception
        putenv('UAT_SCHOOL_ID=bukan-uuid-yang-valid-123');
        $_ENV['UAT_SCHOOL_ID'] = 'bukan-uuid-yang-valid-123';

        try {
            $seeder->resolveTenantSchoolId();
            $this->fail('Harus melempar RuntimeException jika format school_id bukan UUID.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('bukan format UUID yang valid', $e->getMessage());
        }

        // 3. Konflik tenant: UAT_SCHOOL_ID beda dengan SiteSetting existing -> Exception
        $uuidA = '11111111-1111-1111-1111-111111111111';
        $uuidB = '22222222-2222-2222-2222-222222222222';

        SiteSetting::create([
            'setting_key' => 'general_info',
            'school_id' => $uuidA,
            'setting_value' => ['name' => 'Existing School'],
        ]);

        putenv("UAT_SCHOOL_ID={$uuidB}");
        $_ENV['UAT_SCHOOL_ID'] = $uuidB;

        try {
            $seeder->resolveTenantSchoolId();
            $this->fail('Harus melempar RuntimeException jika ada konflik tenant antara env dan DB.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Konflik tenant', $e->getMessage());
        }
    }

    /**
     * UAT-SEED-02: Produksi tetap ditolak secara mutlak meskipun opt-in diaktifkan.
     */
    public function test_uat_seed_02_production_is_strictly_rejected_even_with_opt_in(): void
    {
        App::detectEnvironment(fn () => 'production');
        putenv('UAT_SEED_ENABLED=true');
        $_ENV['UAT_SEED_ENABLED'] = 'true';
        putenv('UAT_STAGING_PASSWORD=Secret123!@#');
        $_ENV['UAT_STAGING_PASSWORD'] = 'Secret123!@#';
        putenv('UAT_SCHOOL_ID=00000000-0000-0000-0000-000000000001');
        $_ENV['UAT_SCHOOL_ID'] = '00000000-0000-0000-0000-000000000001';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('UatStagingSeeder dilarang keras dijalankan pada lingkungan production!');

        $seeder = new UatStagingSeeder;
        $seeder->run();
    }

    /**
     * UAT-SEED-02: Lingkungan selain yang diizinkan (misal local tanpa opt-in) ditolak secara default.
     */
    public function test_uat_seed_02_disallowed_environment_is_rejected_by_default(): void
    {
        App::detectEnvironment(fn () => 'local');
        putenv('UAT_SEED_ENABLED=false');
        $_ENV['UAT_SEED_ENABLED'] = 'false';
        $_SERVER['UAT_SEED_ENABLED'] = 'false';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('UatStagingSeeder hanya boleh dijalankan di lingkungan staging/local dengan opt-in UAT_SEED_ENABLED=true atau lingkungan testing.');

        $seeder = new UatStagingSeeder;
        $seeder->run();
    }

    /**
     * UAT-SEED-02: Penolakan seeder tidak meninggalkan perubahan apa pun di database.
     */
    public function test_uat_seed_02_rejection_leaves_no_database_changes(): void
    {
        $initialUserCount = User::count();
        $initialClassCount = SchoolClass::count();
        $initialFacilityCount = Facility::count();

        App::detectEnvironment(fn () => 'staging');
        putenv('UAT_SEED_ENABLED=true');
        $_ENV['UAT_SEED_ENABLED'] = 'true';
        putenv('UAT_SCHOOL_ID=invalid-uuid-format'); // memicu kegagalan preflight
        $_ENV['UAT_SCHOOL_ID'] = 'invalid-uuid-format';

        $seeder = new UatStagingSeeder;

        try {
            $seeder->run();
            $this->fail('Seeder harus gagal pada preflight check.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('bukan format UUID yang valid', $e->getMessage());
        }

        // Pastikan tidak ada data yang bertambah
        $this->assertEquals($initialUserCount, User::count(), 'Database user tidak boleh bertambah saat seeder ditolak.');
        $this->assertEquals($initialClassCount, SchoolClass::count(), 'Database kelas tidak boleh bertambah saat seeder ditolak.');
        $this->assertEquals($initialFacilityCount, Facility::count(), 'Database fasilitas tidak boleh bertambah saat seeder ditolak.');
    }

    /**
     * UAT-SEED-02: Akun di luar cakupan seeder tidak berubah peran, status, atau passwordnya.
     */
    public function test_uat_seed_02_non_uat_accounts_outside_seeder_scope_are_not_mutated(): void
    {
        // 1. Buat akun regular non-UAT
        $regularUser = User::create([
            'username' => 'guru.biologi.asli',
            'email' => 'guru.biologi@smp-labum.sch.id',
            'password_hash' => Hash::make('PasswordGuruAsli123!'),
            'status' => 'active',
            'school_id' => '11111111-1111-1111-1111-111111111111',
            'must_change_password' => false,
        ]);
        $teacherRole = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru']);
        $regularUser->roles()->attach($teacherRole->id);

        $initialHash = $regularUser->password_hash;

        // 2. Jalankan UatStagingSeeder
        $this->seed(UatStagingSeeder::class);

        // 3. Verifikasi akun regular tidak tersentuh
        $regularUser->refresh();
        $this->assertEquals($initialHash, $regularUser->password_hash, 'Password akun non-UAT tidak boleh berubah.');
        $this->assertTrue($regularUser->hasRole('teacher'), 'Role akun non-UAT tidak boleh hilang.');
        $this->assertFalse($regularUser->hasRole('super_admin'), 'Akun non-UAT tidak boleh menerima role baru.');
        $this->assertEquals('guru.biologi@smp-labum.sch.id', $regularUser->email);
        $this->assertEquals('11111111-1111-1111-1111-111111111111', $regularUser->school_id);
    }

    /**
     * UAT-SEED-02: Pemanggilan berulang tidak mereset password akun yang sudah ada secara diam-diam.
     */
    public function test_uat_seed_02_repeated_seeding_does_not_silently_reset_existing_account_passwords(): void
    {
        // 1. Eksekusi pertama seeder
        $this->seed(UatStagingSeeder::class);

        // 2. Pengguna UAT mengubah passwordnya sendiri
        $adminUser = User::where('username', 'admin.staging')->firstOrFail();
        $customPassword = 'MyCustomAdminPersonalPass2026!';
        $adminUser->password_hash = Hash::make($customPassword);
        $adminUser->save();
        $savedHash = $adminUser->password_hash;

        // 3. Eksekusi kedua seeder tanpa UAT_FORCE_RESET_PASSWORDS
        putenv('UAT_FORCE_RESET_PASSWORDS=false');
        $_ENV['UAT_FORCE_RESET_PASSWORDS'] = 'false';
        $this->seed(UatStagingSeeder::class);

        $adminUser->refresh();
        $this->assertTrue(Hash::check($customPassword, $adminUser->password_hash), 'Password yang telah diubah pengguna tidak boleh di-reset diam-diam.');
        $this->assertEquals($savedHash, $adminUser->password_hash);

        // 4. Eksekusi ketiga dengan opt-in eksplisit UAT_FORCE_RESET_PASSWORDS=true
        putenv('UAT_FORCE_RESET_PASSWORDS=true');
        $_ENV['UAT_FORCE_RESET_PASSWORDS'] = 'true';
        $this->seed(UatStagingSeeder::class);

        $adminUser->refresh();
        $this->assertFalse(Hash::check($customPassword, $adminUser->password_hash), 'Password harus di-reset ketika UAT_FORCE_RESET_PASSWORDS=true.');
        $this->assertTrue(Hash::check('SistLabUM2026!UAT', $adminUser->password_hash));
    }

    /**
     * UAT-SEED-02: Konflik identifier akun (misal email diambil oleh akun lain) ditangani aman dengan exception eksplisit.
     */
    public function test_uat_seed_02_account_identifier_conflict_fails_safely(): void
    {
        // Pre-create user dengan email 'admin.staging@smp-labum.sch.id' tapi username 'penyusup'
        User::create([
            'username' => 'penyusup',
            'email' => 'admin.staging@smp-labum.sch.id',
            'password_hash' => Hash::make('InfiltratorPass123!'),
            'status' => 'active',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Konflik identifier akun UAT: email 'admin.staging@smp-labum.sch.id' telah digunakan oleh akun lain");

        $seeder = new UatStagingSeeder;
        $seeder->run();
    }

    /**
     * UAT-SEED-02: Relasi tenant seluruh data UAT valid dan konsisten dengan UAT_SCHOOL_ID.
     */
    public function test_uat_seed_02_tenant_relations_are_valid_and_consistent_across_all_uat_data(): void
    {
        $customSchoolId = 'a1b2c3d4-e5f6-7a8b-9c0d-1e2f3a4b5c6d';
        putenv("UAT_SCHOOL_ID={$customSchoolId}");
        $_ENV['UAT_SCHOOL_ID'] = $customSchoolId;

        $this->seed(UatStagingSeeder::class);

        // 1. SiteSetting
        $setting = SiteSetting::where('setting_key', 'general_info')->firstOrFail();
        $this->assertEquals($customSchoolId, $setting->school_id);

        // 2. Users (seluruh persona UAT)
        $usernames = [
            'admin.staging', 'kepsek.staging', 'kurikulum.staging', 'walas.7a.staging',
            'guru.mtk.staging', 'bk.staging', 'tu.staging', 'sarpras.staging',
            'ppdb.panitia', 'siswa.lab.001', 'ortu.siswa.001', 'calon.ppdb.01',
            'kesiswaan.staging', 'humas.staging',
        ];
        foreach ($usernames as $username) {
            $user = User::where('username', $username)->firstOrFail();
            $this->assertEquals($customSchoolId, $user->school_id, "User {$username} harus memiliki school_id yang konsisten.");
        }

        // 3. Teacher, Staff, Student, Guardian
        $walas = Teacher::whereHas('user', fn ($q) => $q->where('username', 'walas.7a.staging'))->firstOrFail();
        $this->assertEquals($customSchoolId, $walas->school_id);

        $tu = Staff::whereHas('user', fn ($q) => $q->where('username', 'tu.staging'))->firstOrFail();
        $this->assertEquals($customSchoolId, $tu->school_id);

        $student = Student::whereHas('user', fn ($q) => $q->where('username', 'siswa.lab.001'))->firstOrFail();
        $this->assertEquals($customSchoolId, $student->school_id);

        $guardian = Guardian::whereHas('user', fn ($q) => $q->where('username', 'ortu.siswa.001'))->firstOrFail();
        $this->assertEquals($customSchoolId, $guardian->school_id);

        // 4. Academic & Facilities
        $class = SchoolClass::where('name', '7-A')->firstOrFail();
        $this->assertEquals($customSchoolId, $class->school_id);

        $subject = Subject::where('code', 'MTK-7')->firstOrFail();
        $this->assertEquals($customSchoolId, $subject->school_id);

        $wave = PPDBWave::where('name', 'Gelombang 1')->firstOrFail();
        $this->assertEquals($customSchoolId, $wave->school_id);

        $facility = Facility::where('name', 'Aula Graha Cakrawala Mini')->firstOrFail();
        $this->assertEquals($customSchoolId, $facility->school_id);

        $booking = Booking::where('facility_id', $facility->id)->firstOrFail();
        $this->assertEquals($customSchoolId, $booking->school_id);
    }

    /**
     * UAT-SEED-02: Pesan exception dan output tidak pernah memuat kata sandi teks polos.
     */
    public function test_uat_seed_02_exceptions_and_outputs_never_contain_plaintext_passwords(): void
    {
        $sensitivePassword = 'SuperUltraSecretPassword_NoLeak_12345#';
        App::detectEnvironment(fn () => 'staging');
        putenv('UAT_SEED_ENABLED=true');
        $_ENV['UAT_SEED_ENABLED'] = 'true';
        putenv("UAT_STAGING_PASSWORD={$sensitivePassword}");
        $_ENV['UAT_STAGING_PASSWORD'] = $sensitivePassword;
        putenv('UAT_SCHOOL_ID=invalid-uuid-to-trigger-exception');
        $_ENV['UAT_SCHOOL_ID'] = 'invalid-uuid-to-trigger-exception';

        $seeder = new UatStagingSeeder;

        try {
            $seeder->run();
            $this->fail('Seeder harus gagal karena invalid UUID.');
        } catch (\RuntimeException $e) {
            $message = $e->getMessage();
            $this->assertStringNotContainsString($sensitivePassword, $message, 'Pesan exception tidak boleh membocorkan password plaintext!');
            $this->assertStringNotContainsString($sensitivePassword, $e->getTraceAsString(), 'Trace exception tidak boleh membocorkan password plaintext!');
        }
    }

    /**
     * POST-AUDIT-01: Verifikasi persona Kesiswaan dan Humas memiliki peran, permission least-privilege, dan profil staf yang sesuai.
     */
    public function test_uat_seed_03_student_affairs_and_public_relations_personas_have_correct_roles_and_least_privilege(): void
    {
        $this->seed(UatStagingSeeder::class);

        // 1. Kesiswaan (kesiswaan.staging)
        $userKesiswaan = User::where('username', 'kesiswaan.staging')->firstOrFail();
        $this->assertTrue($userKesiswaan->hasRole('student_affairs'));
        $this->assertFalse($userKesiswaan->hasRole('super_admin'));
        $this->assertEquals('kesiswaan.staging@smp-labum.sch.id', $userKesiswaan->email);

        // Permissions Kesiswaan
        $this->assertTrue($userKesiswaan->hasPermission('student.point.read'));
        $this->assertTrue($userKesiswaan->hasPermission('student.point.write'));
        $this->assertTrue($userKesiswaan->hasPermission('master.student.read'));
        $this->assertTrue($userKesiswaan->hasPermission('reports.view'));
        // Least Privilege Kesiswaan
        $this->assertFalse($userKesiswaan->hasPermission('cbt.bank.write'));
        $this->assertFalse($userKesiswaan->hasPermission('facility.asset.manage'));
        $this->assertFalse($userKesiswaan->hasPermission('auth.role.manage'));

        // Relasi Profil Staff Kesiswaan
        $staffKesiswaan = Staff::where('user_id', $userKesiswaan->id)->firstOrFail();
        $this->assertEquals('Staf Kesiswaan UAT', $staffKesiswaan->full_name);
        $this->assertEquals('Staf Pembina Kesiswaan', $staffKesiswaan->position);
        $this->assertEquals('Kesiswaan', $staffKesiswaan->unit);

        // 2. Humas (humas.staging)
        $userHumas = User::where('username', 'humas.staging')->firstOrFail();
        $this->assertTrue($userHumas->hasRole('public_relations'));
        $this->assertFalse($userHumas->hasRole('super_admin'));
        $this->assertEquals('humas.staging@smp-labum.sch.id', $userHumas->email);

        // Permissions Humas
        $this->assertTrue($userHumas->hasPermission('guest.book.read'));
        $this->assertTrue($userHumas->hasPermission('guest.book.manage'));
        $this->assertTrue($userHumas->hasPermission('complaint.read'));
        $this->assertTrue($userHumas->hasPermission('complaint.manage'));
        $this->assertTrue($userHumas->hasPermission('cms.site.manage'));
        $this->assertTrue($userHumas->hasPermission('cms.post.manage'));
        // Least Privilege Humas
        $this->assertFalse($userHumas->hasPermission('complaint.view_reporter'));
        $this->assertFalse($userHumas->hasPermission('academic.grade.write'));
        $this->assertFalse($userHumas->hasPermission('student.point.write'));
        $this->assertFalse($userHumas->hasPermission('cbt.bank.write'));

        // Relasi Profil Staff Humas
        $staffHumas = Staff::where('user_id', $userHumas->id)->firstOrFail();
        $this->assertEquals('Staf Humas UAT', $staffHumas->full_name);
        $this->assertEquals('Staf Hubungan Masyarakat', $staffHumas->position);
        $this->assertEquals('Humas', $staffHumas->unit);

        // 3. Idempotensi & Proteksi Kredensial: Seeder ulang tidak mengubah password kustom
        $customPass = 'KesiswaanRahasia2026!#';
        $userKesiswaan->password_hash = Hash::make($customPass);
        $userKesiswaan->save();

        $this->seed(UatStagingSeeder::class);

        $userKesiswaan->refresh();
        $this->assertTrue(Hash::check($customPass, $userKesiswaan->password_hash), 'Password kustom akun UAT kesiswaan tidak boleh tertimpa seeder.');
    }
}
