<?php

namespace Tests\Feature\Bk;

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\ClassStudent;
use App\Models\EarlyWarning;
use App\Models\Grade;
use App\Models\GradeComponent;
use App\Models\GradeLevel;
use App\Models\GradePeriod;
use App\Models\Permission;
use App\Models\PointType;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentPoint;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class EarlyWarningTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $counselorUser;

    protected User $homeroomUser1;

    protected User $homeroomUser2;

    protected User $studentUser;

    protected Student $student1;

    protected Student $student2;

    protected SchoolClass $classA;

    protected SchoolClass $classB;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Admin', 'is_system' => true]);
        $roleCounselor = Role::firstOrCreate(['code' => 'counselor'], ['name' => 'Guru BK', 'is_system' => true]);
        $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);
        $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);

        $permBk = Permission::firstOrCreate(['code' => 'bk.counseling.manage'], ['description' => 'Mengelola catatan bimbingan konseling']);
        $roleCounselor->permissions()->syncWithoutDetaching([$permBk->id]);
        $roleAdmin->permissions()->syncWithoutDetaching([$permBk->id]);

        $this->adminUser = User::create([
            'username' => 'admin_bk',
            'email' => 'admin@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($roleAdmin->id);

        $this->counselorUser = User::create([
            'username' => 'guru_bk',
            'email' => 'bk@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->counselorUser->roles()->attach($roleCounselor->id);

        $this->homeroomUser1 = User::create([
            'username' => 'walas_a',
            'email' => 'walasa@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->homeroomUser1->roles()->attach($roleTeacher->id);

        $this->homeroomUser2 = User::create([
            'username' => 'walas_b',
            'email' => 'walasb@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->homeroomUser2->roles()->attach($roleTeacher->id);

        $this->studentUser = User::create([
            'username' => '20261001',
            'email' => 'student@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->studentUser->roles()->attach($roleStudent->id);

        $teacher1 = Teacher::create([
            'user_id' => $this->homeroomUser1->id,
            'nip' => '198101012005011001',
            'full_name' => 'Wali Kelas 10-A, S.Pd',
            'gender' => 'L',
        ]);

        $teacher2 = Teacher::create([
            'user_id' => $this->homeroomUser2->id,
            'nip' => '198202022006022002',
            'full_name' => 'Wali Kelas 10-B, S.Pd',
            'gender' => 'P',
        ]);

        $ay = AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ]);

        $gradeLevel = GradeLevel::create([
            'name' => 'Kelas 10',
            'level' => 10,
        ]);

        $this->classA = SchoolClass::create([
            'academic_year_id' => $ay->id,
            'grade_level_id' => $gradeLevel->id,
            'homeroom_teacher_id' => $teacher1->id,
            'name' => '10-A',
            'capacity' => 36,
        ]);

        $this->classB = SchoolClass::create([
            'academic_year_id' => $ay->id,
            'grade_level_id' => $gradeLevel->id,
            'homeroom_teacher_id' => $teacher2->id,
            'name' => '10-B',
            'capacity' => 36,
        ]);

        $this->student1 = Student::create([
            'user_id' => $this->studentUser->id,
            'full_name' => 'Ahmad Faiz Pratama',
            'nis' => '20261001',
            'nisn' => '0012345678',
            'nik_enc' => 'enc_3578010101010001',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2010-01-01',
            'entry_date' => '2026-07-01',
            'status' => 'aktif',
        ]);

        $this->student2 = Student::create([
            'full_name' => 'Citra Lestari',
            'nis' => '20261002',
            'nisn' => '0012345679',
            'nik_enc' => 'enc_3578010101010002',
            'gender' => 'P',
            'birth_place' => 'Malang',
            'birth_date' => '2010-02-02',
            'entry_date' => '2026-07-01',
            'status' => 'aktif',
        ]);

        ClassStudent::create([
            'class_id' => $this->classA->id,
            'student_id' => $this->student1->id,
            'joined_at' => '2026-07-15',
        ]);

        ClassStudent::create([
            'class_id' => $this->classB->id,
            'student_id' => $this->student2->id,
            'joined_at' => '2026-07-15',
        ]);
    }

    /**
     * BE-BK-02: Evaluasi otomatis menghasilkan alert saat kehadiran siswa di bawah ambang batas / alpa tinggi.
     */
    public function test_be_bk_02_evaluate_triggers_attendance_low_alert(): void
    {
        // Buat data presensi siswa 1: 4 hari hadir, 4 hari alpa (kehadiran 50% < 75%)
        for ($i = 1; $i <= 4; $i++) {
            Attendance::create([
                'student_id' => $this->student1->id,
                'att_date' => Carbon::now()->subDays($i)->toDateString(),
                'status' => 'hadir',
                'source' => 'manual',
            ]);
        }
        for ($i = 5; $i <= 8; $i++) {
            Attendance::create([
                'student_id' => $this->student1->id,
                'att_date' => Carbon::now()->subDays($i)->toDateString(),
                'status' => 'alpa',
                'source' => 'manual',
            ]);
        }

        $this->app['auth']->forgetGuards();

        $response = $this->actingAs($this->counselorUser, 'sanctum')
            ->postJson('/api/v1/early-warnings/evaluate', [
                'student_ids' => [$this->student1->id],
                'attendance_threshold_percent' => 75.0,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.created_alerts', 1);

        $this->assertDatabaseHas('early_warnings', [
            'student_id' => $this->student1->id,
            'rule_code' => 'ATTENDANCE_LOW',
            'status' => 'open',
        ]);

        $warning = EarlyWarning::where('student_id', $this->student1->id)->where('rule_code', 'ATTENDANCE_LOW')->first();
        $this->assertEquals(50.0, $warning->data['attendance_percent']);
        $this->assertEquals(4, $warning->data['alpa_count']);
    }

    /**
     * BE-BK-02: Evaluasi otomatis menghasilkan alert saat terjadi penurunan nilai signifikan (GRADE_DROP).
     */
    public function test_be_bk_02_evaluate_triggers_grade_drop_alert(): void
    {
        $sem = Semester::create(['academic_year_id' => $this->classA->academic_year_id, 'name' => 'Ganjil', 'semester_type' => 'ganjil', 'start_date' => '2026-07-01', 'end_date' => '2026-12-31', 'is_active' => true]);
        $subject = Subject::create(['name' => 'Fisika', 'code' => 'FIS-10', 'subject_group' => 'muatan_nasional']);
        $comp = GradeComponent::create(['subject_id' => $subject->id, 'semester_id' => $sem->id, 'name' => 'Tugas', 'code' => 'TGS-1', 'weight' => 20]);
        $period1 = GradePeriod::create(['semester_id' => $sem->id, 'name' => 'Bulan 1', 'month' => 7, 'year' => 2026, 'status' => 'open']);
        $period2 = GradePeriod::create(['semester_id' => $sem->id, 'name' => 'Bulan 2', 'month' => 8, 'year' => 2026, 'status' => 'open']);
        $period3 = GradePeriod::create(['semester_id' => $sem->id, 'name' => 'Bulan 3', 'month' => 9, 'year' => 2026, 'status' => 'open']);
        $period4 = GradePeriod::create(['semester_id' => $sem->id, 'name' => 'Bulan 4', 'month' => 10, 'year' => 2026, 'status' => 'open']);

        // Nilai lama tinggi (85, 90)
        $g1 = Grade::create(['student_id' => $this->student1->id, 'component_id' => $comp->id, 'period_id' => $period1->id, 'value' => 90.0]);
        $g1->created_at = Carbon::now()->subDays(10);
        $g1->save();

        $g2 = Grade::create(['student_id' => $this->student1->id, 'component_id' => $comp->id, 'period_id' => $period2->id, 'value' => 85.0]);
        $g2->created_at = Carbon::now()->subDays(8);
        $g2->save();

        // Nilai terbaru anjlok drastis (60, 55) => selisih > 20 poin
        $g3 = Grade::create(['student_id' => $this->student1->id, 'component_id' => $comp->id, 'period_id' => $period3->id, 'value' => 60.0]);
        $g3->created_at = Carbon::now()->subDays(2);
        $g3->save();

        $g4 = Grade::create(['student_id' => $this->student1->id, 'component_id' => $comp->id, 'period_id' => $period4->id, 'value' => 55.0]);
        $g4->created_at = Carbon::now()->subDay();
        $g4->save();

        $this->app['auth']->forgetGuards();

        $response = $this->actingAs($this->counselorUser, 'sanctum')
            ->postJson('/api/v1/early-warnings/evaluate', [
                'student_ids' => [$this->student1->id],
                'grade_drop_threshold' => 15.0,
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('early_warnings', [
            'student_id' => $this->student1->id,
            'rule_code' => 'GRADE_DROP',
            'status' => 'open',
        ]);
    }

    /**
     * BE-BK-02: Evaluasi otomatis menghasilkan alert saat akumulasi poin pelanggaran melampaui batas.
     */
    public function test_be_bk_02_evaluate_triggers_violation_points_alert(): void
    {
        $pointType = PointType::create([
            'kind' => 'pelanggaran',
            'name' => 'Merokok di lingkungan sekolah',
            'points' => 25,
            'severity' => 'sedang',
        ]);

        StudentPoint::create([
            'student_id' => $this->student1->id,
            'point_type_id' => $pointType->id,
            'recorded_by' => $this->counselorUser->id,
            'point_date' => Carbon::now()->toDateString(),
            'notes' => 'Tertangkap basah di belakang kantin',
        ]);

        $this->app['auth']->forgetGuards();

        $response = $this->actingAs($this->counselorUser, 'sanctum')
            ->postJson('/api/v1/early-warnings/evaluate', [
                'student_ids' => [$this->student1->id],
                'violation_points_threshold' => 20,
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('early_warnings', [
            'student_id' => $this->student1->id,
            'rule_code' => 'VIOLATION_POINTS',
            'status' => 'open',
        ]);
    }

    /**
     * BE-BK-02: Evaluasi idempoten (tidak membuat duplikasi alert saat status masih open).
     */
    public function test_be_bk_02_evaluate_is_idempotent_and_updates_existing_open_alert(): void
    {
        // Alert awal sudah ada
        $warning = EarlyWarning::create([
            'student_id' => $this->student1->id,
            'rule_code' => 'ATTENDANCE_LOW',
            'severity' => 'sedang',
            'data' => ['attendance_percent' => 60.0],
            'status' => 'open',
        ]);

        // Tambah alpa lagi sehingga severity naik jadi tinggi
        for ($i = 1; $i <= 6; $i++) {
            Attendance::create([
                'student_id' => $this->student1->id,
                'att_date' => Carbon::now()->subDays($i)->toDateString(),
                'status' => 'alpa',
                'source' => 'manual',
            ]);
        }

        $this->app['auth']->forgetGuards();

        $response = $this->actingAs($this->counselorUser, 'sanctum')
            ->postJson('/api/v1/early-warnings/evaluate', [
                'student_ids' => [$this->student1->id],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.created_alerts', 0)
            ->assertJsonPath('data.updated_alerts', 1);

        // Jumlah alert di DB tetap 1, tidak ada duplikat
        $this->assertEquals(1, EarlyWarning::where('student_id', $this->student1->id)->count());

        $warning->refresh();
        $this->assertEquals('tinggi', $warning->severity);
    }

    /**
     * BE-BK-02: Guru BK atau wali kelas dapat menindaklanjuti status alert (in_progress, resolved).
     */
    public function test_be_bk_02_user_can_handle_early_warning_alert(): void
    {
        $warning = EarlyWarning::create([
            'student_id' => $this->student1->id,
            'rule_code' => 'ATTENDANCE_LOW',
            'severity' => 'sedang',
            'data' => ['alpa_count' => 3],
            'status' => 'open',
        ]);

        $this->app['auth']->forgetGuards();

        $response = $this->actingAs($this->counselorUser, 'sanctum')
            ->patchJson("/api/v1/early-warnings/{$warning->id}/handle", [
                'status' => 'resolved',
                'notes' => 'Telah dilakukan pemanggilan orang tua dan bimbingan pribadi; masalah teratasi.',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'resolved')
            ->assertJsonPath('data.handler.username', 'guru_bk');

        $warning->refresh();
        $this->assertEquals('resolved', $warning->status);
        $this->assertEquals($this->counselorUser->id, $warning->handled_by);
        $this->assertEquals('Telah dilakukan pemanggilan orang tua dan bimbingan pribadi; masalah teratasi.', $warning->data['handling_notes']);
    }

    /**
     * BE-BK-02: Wali kelas hanya dapat melihat alert siswa di kelas bimbingannya (Scoping check).
     */
    public function test_be_bk_02_homeroom_teacher_scoping_restricted_to_own_class(): void
    {
        // Warning 1 untuk siswa di Kelas 10-A (wali kelas: homeroomUser1)
        $w1 = EarlyWarning::create([
            'student_id' => $this->student1->id,
            'rule_code' => 'ATTENDANCE_LOW',
            'severity' => 'sedang',
            'data' => ['test' => 1],
            'status' => 'open',
        ]);

        // Warning 2 untuk siswa di Kelas 10-B (wali kelas: homeroomUser2)
        $w2 = EarlyWarning::create([
            'student_id' => $this->student2->id,
            'rule_code' => 'GRADE_DROP',
            'severity' => 'tinggi',
            'data' => ['test' => 2],
            'status' => 'open',
        ]);

        $this->app['auth']->forgetGuards();

        // Wali kelas 10-A melihat daftar early warnings
        $response = $this->actingAs($this->homeroomUser1, 'sanctum')
            ->getJson('/api/v1/early-warnings');

        $response->assertStatus(200);

        $returnedStudentIds = collect($response->json('data'))->pluck('student_id')->all();
        $this->assertContains($this->student1->id, $returnedStudentIds);
        $this->assertNotContains($this->student2->id, $returnedStudentIds);

        // Mencoba akses langsung detail siswa kelas lain ditolak
        $responseDetailIllegal = $this->actingAs($this->homeroomUser1, 'sanctum')
            ->getJson("/api/v1/early-warnings/{$w2->id}");

        $responseDetailIllegal->assertStatus(403);
    }

    /**
     * BE-BK-02: Siswa dilarang keras mengakses sistem deteksi dini (403 Forbidden).
     */
    public function test_be_bk_02_student_forbidden_to_access_early_warnings(): void
    {
        $this->app['auth']->forgetGuards();

        $response = $this->actingAs($this->studentUser, 'sanctum')
            ->getJson('/api/v1/early-warnings');

        $response->assertStatus(403);
    }

    /**
     * BE-BK-02: Endpoint ringkasan metrik peringatan dini mengembalikan statistik yang valid.
     */
    public function test_be_bk_02_summary_metrics_endpoint(): void
    {
        EarlyWarning::create([
            'student_id' => $this->student1->id,
            'rule_code' => 'ATTENDANCE_LOW',
            'severity' => 'sedang',
            'data' => ['test' => 1],
            'status' => 'open',
        ]);

        EarlyWarning::create([
            'student_id' => $this->student1->id,
            'rule_code' => 'GRADE_DROP',
            'severity' => 'tinggi',
            'data' => ['test' => 2],
            'status' => 'open',
        ]);

        EarlyWarning::create([
            'student_id' => $this->student2->id,
            'rule_code' => 'VIOLATION_POINTS',
            'severity' => 'sedang',
            'data' => ['test' => 3],
            'status' => 'in_progress',
        ]);

        EarlyWarning::create([
            'student_id' => $this->student2->id,
            'rule_code' => 'ATTENDANCE_LOW',
            'severity' => 'sedang',
            'data' => ['test' => 4],
            'status' => 'resolved',
        ]);

        $this->app['auth']->forgetGuards();

        $response = $this->actingAs($this->counselorUser, 'sanctum')
            ->getJson('/api/v1/early-warnings/summary');

        $response->assertStatus(200)
            ->assertJsonPath('data.open_count', 2)
            ->assertJsonPath('data.in_progress_count', 1)
            ->assertJsonPath('data.resolved_count', 1)
            ->assertJsonPath('data.high_severity_count', 1)
            ->assertJsonPath('data.total_active', 3);
    }

    /**
     * BE-BK-02: Wali murid dilarang mengakses sistem deteksi dini (403 Forbidden).
     */
    public function test_be_bk_02_guardian_forbidden_to_access_early_warnings(): void
    {
        $roleGuardian = Role::firstOrCreate(['code' => 'guardian'], ['name' => 'Wali Murid', 'is_system' => true]);

        $guardianUser = User::create([
            'username' => 'wali_faiz',
            'email' => 'wali@keluarga.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $guardianUser->roles()->attach($roleGuardian->id);

        $this->app['auth']->forgetGuards();

        $response = $this->actingAs($guardianUser, 'sanctum')
            ->getJson('/api/v1/early-warnings');

        $response->assertStatus(403);
    }

    /**
     * BE-BK-02: Isolasi multi-tenant antar-sekolah menolak akses data siswa sekolah lain (403 Forbidden).
     */
    public function test_be_bk_02_cross_school_access_is_forbidden(): void
    {
        $schoolIdA = (string) Str::uuid();
        $schoolIdB = (string) Str::uuid();

        // Counselor bertugas di School A
        $this->counselorUser->school_id = $schoolIdA;
        $this->counselorUser->save();

        // Student2 terdaftar di School B
        $this->student2->school_id = $schoolIdB;
        $this->student2->save();

        $warningSchoolB = EarlyWarning::create([
            'student_id' => $this->student2->id,
            'rule_code' => 'ATTENDANCE_LOW',
            'severity' => 'tinggi',
            'data' => ['test' => 1],
            'status' => 'open',
        ]);

        $this->app['auth']->forgetGuards();

        $response = $this->actingAs($this->counselorUser, 'sanctum')
            ->getJson("/api/v1/early-warnings/{$warningSchoolB->id}");

        $response->assertStatus(403);
    }

    /**
     * BE-BK-02: Alert yang berstatus resolved tidak dapat diregresi langsung ke status open (422 Unprocessable).
     */
    public function test_be_bk_02_cannot_regress_resolved_warning_to_open(): void
    {
        $warning = EarlyWarning::create([
            'student_id' => $this->student1->id,
            'rule_code' => 'ATTENDANCE_LOW',
            'severity' => 'sedang',
            'data' => ['alpa_count' => 3],
            'status' => 'resolved',
        ]);

        $this->app['auth']->forgetGuards();

        $response = $this->actingAs($this->counselorUser, 'sanctum')
            ->patchJson("/api/v1/early-warnings/{$warning->id}/handle", [
                'status' => 'open',
            ]);

        $response->assertStatus(422);
    }

    /**
     * BE-BK-02: Artisan command bk:evaluate-early-warnings berjalan sukses.
     */
    public function test_be_bk_02_artisan_console_command_evaluates_rules(): void
    {
        $this->artisan('bk:evaluate-early-warnings')
            ->assertExitCode(0);
    }
}
