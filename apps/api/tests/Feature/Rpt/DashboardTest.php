<?php

namespace Tests\Feature\Rpt;

use App\Models\AcademicYear;
use App\Models\Asset;
use App\Models\Attendance;
use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\GradeLevel;
use App\Models\Guardian;
use App\Models\QuestionBank;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $teacherUser;

    protected User $studentUser1;

    protected User $studentUser2;

    protected User $guardianUser;

    protected Teacher $teacher;

    protected Student $student1;

    protected Student $student2;

    protected Guardian $guardian;

    protected SchoolClass $class;

    protected Exam $exam;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        // Roles
        $roleAdmin = Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Admin', 'is_system' => true]);
        $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);
        $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);
        $roleGuardian = Role::firstOrCreate(['code' => 'guardian'], ['name' => 'Wali Murid', 'is_system' => true]);

        // Users
        $this->adminUser = User::create([
            'username' => 'admin_dash',
            'email' => 'admin_dash@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($roleAdmin->id);

        $this->teacherUser = User::create([
            'username' => 'guru_dash',
            'email' => 'guru_dash@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->teacherUser->roles()->attach($roleTeacher->id);

        $this->studentUser1 = User::create([
            'username' => 'siswa_dash_1',
            'email' => 'siswa_dash1@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->studentUser1->roles()->attach($roleStudent->id);

        $this->studentUser2 = User::create([
            'username' => 'siswa_dash_2',
            'email' => 'siswa_dash2@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->studentUser2->roles()->attach($roleStudent->id);

        $this->guardianUser = User::create([
            'username' => 'ortu_dash',
            'email' => 'ortu_dash@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->guardianUser->roles()->attach($roleGuardian->id);

        // Teacher profile
        $this->teacher = Teacher::create([
            'user_id' => $this->teacherUser->id,
            'full_name' => 'Bambang Guru',
            'nip' => '198001012005011001',
            'status' => 'tetap',
        ]);

        // Academic Structure
        $ay = AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ]);

        $gl = GradeLevel::create([
            'name' => 'Kelas 10',
            'sort_order' => 10,
        ]);

        $this->class = SchoolClass::create([
            'academic_year_id' => $ay->id,
            'grade_level_id' => $gl->id,
            'name' => '10-MIPA-1',
            'capacity' => 36,
        ]);

        // Students
        $this->student1 = Student::create([
            'user_id' => $this->studentUser1->id,
            'nis' => '90001',
            'nisn' => '0090001',
            'nik_enc' => '3578010101019001',
            'full_name' => 'Dimas Siswa 1',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2009-01-01',
            'entry_date' => '2026-07-01',
            'status' => 'active',
        ]);

        $this->student2 = Student::create([
            'user_id' => $this->studentUser2->id,
            'nis' => '90002',
            'nisn' => '0090002',
            'nik_enc' => '3578010101019002',
            'full_name' => 'Eka Siswa 2',
            'gender' => 'P',
            'birth_place' => 'Surabaya',
            'birth_date' => '2009-05-05',
            'entry_date' => '2026-07-01',
            'status' => 'active',
        ]);

        DB::table('class_students')->insert([
            ['id' => (string) Str::uuid(), 'class_id' => $this->class->id, 'student_id' => $this->student1->id, 'joined_at' => '2026-07-01', 'created_at' => now(), 'updated_at' => now()],
            ['id' => (string) Str::uuid(), 'class_id' => $this->class->id, 'student_id' => $this->student2->id, 'joined_at' => '2026-07-01', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Guardian profile (linked to student1 only)
        $this->guardian = Guardian::create([
            'user_id' => $this->guardianUser->id,
            'full_name' => 'Budi Ayah Dimas',
            'phone' => '081234567899',
        ]);
        $this->guardian->students()->attach($this->student1->id, ['relationship' => 'ayah', 'is_primary' => true]);

        // CBT Exam
        $subject = Subject::create([
            'name' => 'Biologi',
            'code' => 'BIO',
            'subject_group' => 'peminatan',
        ]);

        $qb = QuestionBank::create([
            'subject_id' => $subject->id,
            'owner_id' => $this->teacherUser->id,
            'name' => 'Bank Soal Biologi',
        ]);

        $this->exam = Exam::create([
            'subject_id' => $subject->id,
            'created_by' => $this->teacherUser->id,
            'title' => 'PTS Biologi Kelas 10',
            'exam_type' => 'harian',
            'duration_min' => 60,
            'start_at' => Carbon::now()->subMinutes(10),
            'end_at' => Carbon::now()->addHour(),
            'status' => 'berlangsung',
        ]);
    }

    public function test_be_rpt_02_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/dashboard/executive')->assertStatus(401);
        $this->getJson('/api/v1/dashboard/teacher')->assertStatus(401);
        $this->getJson('/api/v1/dashboard/student')->assertStatus(401);
        $this->getJson('/api/v1/dashboard/guardian/'.$this->student1->id)->assertStatus(401);
    }

    public function test_be_rpt_02_regular_student_cannot_access_executive_dashboard(): void
    {
        Sanctum::actingAs($this->studentUser1);

        $res = $this->getJson('/api/v1/dashboard/executive');
        $res->assertStatus(403);
    }

    public function test_be_rpt_02_executive_can_view_executive_dashboard_with_realtime_and_cached_metrics(): void
    {
        Sanctum::actingAs($this->adminUser);

        // Active CBT session
        ExamSession::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student1->id,
            'seed' => 12345,
            'status' => 'mengerjakan',
            'started_at' => Carbon::now()->subMinutes(5),
            'deadline_at' => Carbon::now()->addMinutes(55),
        ]);

        // Today's attendance
        Attendance::create([
            'student_id' => $this->student1->id,
            'att_date' => Carbon::today()->toDateString(),
            'status' => 'hadir',
        ]);

        // Critical ticket
        Ticket::create([
            'reporter_id' => $this->adminUser->id,
            'description' => 'Genset Utama Meledak',
            'priority' => 'darurat',
            'status' => 'pending',
        ]);

        $res = $this->getJson('/api/v1/dashboard/executive');
        $res->assertStatus(200)
            ->assertJsonPath('data.cbt_live.active_students_count', 1)
            ->assertJsonPath('data.cbt_live.ongoing_exams_count', 1)
            ->assertJsonPath('data.attendance_today.students.hadir', 1)
            ->assertJsonPath('data.infrastructure_status.critical_tickets_count', 1);

        $this->assertArrayHasKey('syllabus_completion', $res->json('data'));
        $this->assertArrayHasKey('teacher_grading_completion', $res->json('data'));
        $this->assertArrayHasKey('cached_at', $res->json('data'));
    }

    public function test_be_rpt_02_cbt_live_metrics_are_not_cached_while_executive_aggregates_are_cached(): void
    {
        Sanctum::actingAs($this->adminUser);

        // 1. Initial Call -> 0 active CBT students
        $res1 = $this->getJson('/api/v1/dashboard/executive');
        $res1->assertStatus(200)
            ->assertJsonPath('data.cbt_live.active_students_count', 0);

        // 2. Student starts exam in real-time
        ExamSession::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student1->id,
            'seed' => 12345,
            'status' => 'mengerjakan',
            'started_at' => Carbon::now()->subMinute(),
            'deadline_at' => Carbon::now()->addMinutes(59),
        ]);

        // 3. Second Call -> CBT live must reflect 1 active student immediately without waiting for cache invalidation!
        $res2 = $this->getJson('/api/v1/dashboard/executive');
        $res2->assertStatus(200)
            ->assertJsonPath('data.cbt_live.active_students_count', 1)
            ->assertJsonPath('data.cached_at', $res1->json('data.cached_at')); // other metrics stayed cached
    }

    public function test_be_rpt_02_teacher_can_view_teacher_dashboard(): void
    {
        Sanctum::actingAs($this->teacherUser);

        $res = $this->getJson('/api/v1/dashboard/teacher');
        $res->assertStatus(200)
            ->assertJsonPath('data.teacher.name', 'Bambang Guru')
            ->assertJsonPath('data.teacher.nip', '198001012005011001');

        $this->assertArrayHasKey('teaching_classes_count', $res->json('data'));
        $this->assertArrayHasKey('today_schedules', $res->json('data'));
        $this->assertArrayHasKey('pending_journals_count', $res->json('data'));
    }

    public function test_be_rpt_02_student_can_view_student_dashboard(): void
    {
        Sanctum::actingAs($this->studentUser1);

        Attendance::create([
            'student_id' => $this->student1->id,
            'att_date' => Carbon::today()->toDateString(),
            'status' => 'hadir',
            'note' => 'Hadir tepat waktu',
        ]);

        $res = $this->getJson('/api/v1/dashboard/student');
        $res->assertStatus(200)
            ->assertJsonPath('data.student.name', 'Dimas Siswa 1')
            ->assertJsonPath('data.student.nis', '90001')
            ->assertJsonPath('data.student.class', '10-MIPA-1')
            ->assertJsonPath('data.attendance.today_status', 'hadir');

        $this->assertArrayHasKey('points', $res->json('data'));
        $this->assertArrayHasKey('library', $res->json('data'));
        $this->assertArrayHasKey('upcoming_exams', $res->json('data'));
    }

    public function test_be_rpt_02_guardian_can_view_guardian_dashboard_for_own_child(): void
    {
        Sanctum::actingAs($this->guardianUser);

        Attendance::create([
            'student_id' => $this->student1->id,
            'att_date' => Carbon::today()->toDateString(),
            'status' => 'hadir',
            'note' => 'Datang sebelum bel masuk',
        ]);

        $res = $this->getJson('/api/v1/dashboard/guardian/'.$this->student1->id);
        $res->assertStatus(200)
            ->assertJsonPath('data.student.name', 'Dimas Siswa 1')
            ->assertJsonPath('data.attendance_today.status', 'hadir')
            ->assertJsonPath('data.attendance_summary.hadir', 1);

        $this->assertArrayHasKey('latest_grades', $res->json('data'));
        $this->assertArrayHasKey('character_points', $res->json('data'));
    }

    public function test_be_rpt_02_guardian_cannot_access_dashboard_of_other_child_idor_prevention(): void
    {
        Sanctum::actingAs($this->guardianUser);

        // Guardian is only parent of student1, trying to access student2 -> 403 Forbidden!
        $res = $this->getJson('/api/v1/dashboard/guardian/'.$this->student2->id);
        $res->assertStatus(403)
            ->assertJsonPath('error.message', 'Akses ditolak: Anda tidak memiliki wewenang mengakses data siswa ini.');
    }

    public function test_be_rpt_02_admin_can_view_guardian_dashboard_for_any_student(): void
    {
        Sanctum::actingAs($this->adminUser);

        // Admin has oversight rights across all students
        $res = $this->getJson('/api/v1/dashboard/guardian/'.$this->student2->id);
        $res->assertStatus(200)
            ->assertJsonPath('data.student.name', 'Eka Siswa 2');
    }

    public function test_be_rpt_02_executive_dashboard_includes_master_summary_and_cbt_live(): void
    {
        Sanctum::actingAs($this->adminUser);

        $res = $this->getJson('/api/v1/dashboard/executive');
        $res->assertStatus(200);

        $data = $res->json('data');
        $this->assertArrayHasKey('master_summary', $data);
        $this->assertArrayHasKey('total_students', $data['master_summary']);
        $this->assertArrayHasKey('total_teachers', $data['master_summary']);
        $this->assertArrayHasKey('total_classes', $data['master_summary']);
        $this->assertArrayHasKey('pending_verifications', $data['master_summary']);
        $this->assertArrayHasKey('cbt_live', $data);
        $this->assertArrayHasKey('cached_at', $data);
    }

    public function test_be_rpt_02_executive_dashboard_counts_damaged_assets_using_asset_condition_column(): void
    {
        Sanctum::actingAs($this->adminUser);

        Asset::create([
            'asset_code' => 'AST-DMG-001',
            'name' => 'Proyektor Rusak Parah',
            'category' => 'Elektronik',
            'asset_condition' => 'rusak_berat',
        ]);

        $res = $this->getJson('/api/v1/dashboard/executive');
        $res->assertStatus(200);

        $data = $res->json('data');
        $this->assertGreaterThanOrEqual(1, $data['infrastructure_status']['heavily_damaged_assets']);
    }

    public function test_be_rpt_02_executive_dashboard_multi_tenant_isolation(): void
    {
        $schoolA = (string) Str::uuid();
        $schoolB = (string) Str::uuid();

        // Assign admin to school A
        $this->adminUser->update(['school_id' => $schoolA]);
        $this->student1->update(['school_id' => $schoolA]);
        $this->student2->update(['school_id' => $schoolB]);

        Ticket::create([
            'school_id' => $schoolA,
            'reporter_id' => $this->adminUser->id,
            'description' => 'Kerusakan AC Lab Sekolah A',
            'priority' => 'darurat',
            'status' => 'pending',
        ]);

        Ticket::create([
            'school_id' => $schoolB,
            'reporter_id' => $this->adminUser->id,
            'description' => 'Kerusakan Lampu Sekolah B',
            'priority' => 'darurat',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($this->adminUser);

        $res = $this->getJson('/api/v1/dashboard/executive');
        $res->assertStatus(200);

        $data = $res->json('data');
        // Only 1 ticket from School A should be counted, not the one from School B
        $this->assertEquals(1, $data['infrastructure_status']['critical_tickets_count']);
        // Only student 1 from School A should be counted
        $this->assertEquals(1, $data['master_summary']['total_students']);
    }
}
