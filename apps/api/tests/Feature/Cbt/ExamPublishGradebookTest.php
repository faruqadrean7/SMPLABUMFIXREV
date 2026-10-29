<?php

namespace Tests\Feature\Cbt;

use App\Models\AcademicYear;
use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\Grade;
use App\Models\GradeComponent;
use App\Models\GradeLevel;
use App\Models\GradePeriod;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ExamPublishGradebookTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $teacherUser;

    protected User $otherTeacherUser;

    protected Student $student1;

    protected Student $student2;

    protected Subject $subjectMath;

    protected Subject $subjectPhysics;

    protected Semester $semester;

    protected GradePeriod $activePeriod;

    protected GradePeriod $lockedPeriod;

    protected GradeComponent $componentMath;

    protected GradeComponent $componentPhysics;

    protected Exam $examMath;

    protected ExamSession $session1;

    protected ExamSession $session2;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Admin', 'is_system' => true]);
        $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru Pengajar', 'is_system' => true]);

        $permExamManage = Permission::firstOrCreate(['code' => 'cbt.exam.manage'], ['description' => 'Kelola Ujian CBT']);
        $roleAdmin->permissions()->syncWithoutDetaching([$permExamManage->id]);
        $roleTeacher->permissions()->syncWithoutDetaching([$permExamManage->id]);

        $this->adminUser = User::create([
            'username' => 'admin_gradebook',
            'email' => 'admin_gradebook@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($roleAdmin);

        $this->teacherUser = User::create([
            'username' => 'guru_math_cbt',
            'email' => 'guru_math@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->teacherUser->roles()->attach($roleTeacher);

        $this->otherTeacherUser = User::create([
            'username' => 'guru_other_cbt',
            'email' => 'guru_other@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->otherTeacherUser->roles()->attach($roleTeacher);

        $academicYear = AcademicYear::firstOrCreate(
            ['name' => '2026/2027'],
            ['start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_active' => true]
        );

        $this->semester = Semester::firstOrCreate(
            ['academic_year_id' => $academicYear->id, 'name' => 'Semester Ganjil 2026/2027'],
            [
                'start_date' => '2026-07-01',
                'end_date' => '2026-12-31',
                'is_active' => true,
            ]
        );

        $gradeLevel = GradeLevel::firstOrCreate(['name' => 'Kelas 10'], ['sort_order' => 10]);
        $classA = SchoolClass::firstOrCreate(
            ['name' => 'X-MIPA-1'],
            ['academic_year_id' => $academicYear->id, 'grade_level_id' => $gradeLevel->id]
        );

        $this->subjectMath = Subject::firstOrCreate(['code' => 'MAT-X'], ['name' => 'Matematika X', 'subject_group' => 'muatan_nasional']);
        $this->subjectPhysics = Subject::firstOrCreate(['code' => 'FIS-X'], ['name' => 'Fisika X', 'subject_group' => 'muatan_nasional']);

        $teacher = Teacher::create([
            'user_id' => $this->teacherUser->id,
            'nip' => '198501012010011001',
            'full_name' => 'Guru Matematika, S.Pd',
            'gender' => 'L',
        ]);

        TeachingAssignment::create([
            'teacher_id' => $teacher->id,
            'subject_id' => $this->subjectMath->id,
            'class_id' => $classA->id,
            'academic_year_id' => $academicYear->id,
            'semester_id' => $this->semester->id,
        ]);

        $this->activePeriod = GradePeriod::create([
            'semester_id' => $this->semester->id,
            'month' => 8,
            'status' => 'open',
        ]);

        $this->lockedPeriod = GradePeriod::create([
            'semester_id' => $this->semester->id,
            'month' => 7,
            'status' => 'locked',
            'locked_at' => Carbon::now(),
        ]);

        $this->componentMath = GradeComponent::create([
            'subject_id' => $this->subjectMath->id,
            'semester_id' => $this->semester->id,
            'class_id' => $classA->id,
            'code' => 'UH1',
            'name' => 'Ulangan Harian 1',
            'weight' => 20.00,
        ]);

        $this->componentPhysics = GradeComponent::create([
            'subject_id' => $this->subjectPhysics->id,
            'semester_id' => $this->semester->id,
            'class_id' => $classA->id,
            'code' => 'UH1_FIS',
            'name' => 'Ulangan Harian 1 Fisika',
            'weight' => 20.00,
        ]);

        $userStudent1 = User::create([
            'username' => 'siswa_cbt_1',
            'email' => 'siswa1@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->student1 = Student::create([
            'user_id' => $userStudent1->id,
            'nis' => '1001',
            'nisn' => '0010010001',
            'nik_enc' => 'enc_3578010101010001',
            'full_name' => 'Siswa CBT Satu',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2010-01-01',
            'entry_date' => '2026-07-01',
            'status' => 'aktif',
        ]);

        $userStudent2 = User::create([
            'username' => 'siswa_cbt_2',
            'email' => 'siswa2@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->student2 = Student::create([
            'user_id' => $userStudent2->id,
            'nis' => '1002',
            'nisn' => '0010010002',
            'nik_enc' => 'enc_3578010101010002',
            'full_name' => 'Siswa CBT Dua',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2010-02-02',
            'entry_date' => '2026-07-01',
            'status' => 'aktif',
        ]);

        $this->examMath = Exam::create([
            'subject_id' => $this->subjectMath->id,
            'created_by' => $this->teacherUser->id,
            'title' => 'Penilaian Harian Matematika CBT',
            'exam_type' => 'formatif',
            'duration_min' => 60,
            'start_at' => Carbon::now()->subHours(2),
            'end_at' => Carbon::now()->addHours(2),
            'passing_grade' => 75.00,
            'status' => 'active',
        ]);

        $this->session1 = ExamSession::create([
            'exam_id' => $this->examMath->id,
            'student_id' => $this->student1->id,
            'status' => 'submitted',
            'started_at' => Carbon::now()->subHour(),
            'submitted_at' => Carbon::now()->subMinutes(10),
            'score' => 88.50,
            'seed' => 12345,
        ]);

        $this->session2 = ExamSession::create([
            'exam_id' => $this->examMath->id,
            'student_id' => $this->student2->id,
            'status' => 'submitted',
            'started_at' => Carbon::now()->subHour(),
            'submitted_at' => Carbon::now()->subMinutes(15),
            'score' => 92.00,
            'seed' => 67890,
        ]);
    }

    public function test_be_cbt_05_can_publish_cbt_exam_results_to_gradebook_successfully(): void
    {
        $payload = [
            'component_id' => $this->componentMath->id,
            'period_id' => $this->activePeriod->id,
        ];

        $response = $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson("/api/v1/exams/{$this->examMath->id}/publish-gradebook", $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.grades_synced', 2)
            ->assertJsonPath('data.skipped_locked', 0);

        $this->assertDatabaseHas('grades', [
            'student_id' => $this->student1->id,
            'component_id' => $this->componentMath->id,
            'period_id' => $this->activePeriod->id,
            'exam_id' => $this->examMath->id,
            'value' => 88.50,
            'source' => 'cbt',
        ]);

        $this->assertDatabaseHas('grades', [
            'student_id' => $this->student2->id,
            'component_id' => $this->componentMath->id,
            'period_id' => $this->activePeriod->id,
            'exam_id' => $this->examMath->id,
            'value' => 92.00,
            'source' => 'cbt',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'exam.publish_gradebook',
            'entity' => 'exams',
            'entity_id' => $this->examMath->id,
        ]);
    }

    public function test_be_cbt_05_cannot_publish_to_gradebook_if_period_is_locked(): void
    {
        $payload = [
            'component_id' => $this->componentMath->id,
            'period_id' => $this->lockedPeriod->id,
        ];

        $response = $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson("/api/v1/exams/{$this->examMath->id}/publish-gradebook", $payload);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'BUSINESS_RULE_VIOLATION');
    }

    public function test_be_cbt_05_cannot_publish_to_gradebook_if_subject_mismatches(): void
    {
        $payload = [
            'component_id' => $this->componentPhysics->id,
            'period_id' => $this->activePeriod->id,
        ];

        $response = $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson("/api/v1/exams/{$this->examMath->id}/publish-gradebook", $payload);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'BUSINESS_RULE_VIOLATION');
    }

    public function test_be_cbt_05_cannot_publish_to_gradebook_without_permission(): void
    {
        $regularUser = User::create([
            'username' => 'user_regular_cbt',
            'email' => 'regular@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);

        $payload = [
            'component_id' => $this->componentMath->id,
            'period_id' => $this->activePeriod->id,
        ];

        $response = $this->actingAs($regularUser, 'sanctum')
            ->postJson("/api/v1/exams/{$this->examMath->id}/publish-gradebook", $payload);

        $response->assertStatus(403);
    }

    public function test_be_cbt_05_respects_individual_grade_lock_unless_overwrite_specified(): void
    {
        // Kunci nilai existing siswa 1
        Grade::create([
            'student_id' => $this->student1->id,
            'component_id' => $this->componentMath->id,
            'period_id' => $this->activePeriod->id,
            'value' => 70.00,
            'source' => 'manual',
            'locked_at' => Carbon::now(),
            'entered_by' => $this->teacherUser->id,
        ]);

        $payload = [
            'component_id' => $this->componentMath->id,
            'period_id' => $this->activePeriod->id,
            'overwrite_locked' => false,
        ];

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/exams/{$this->examMath->id}/publish-gradebook", $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.grades_synced', 1)
            ->assertJsonPath('data.skipped_locked', 1);

        // Siswa 1 tetap 70.00
        $this->assertDatabaseHas('grades', [
            'student_id' => $this->student1->id,
            'component_id' => $this->componentMath->id,
            'period_id' => $this->activePeriod->id,
            'value' => 70.00,
        ]);

        // Siswa 2 ter-sync 92.00
        $this->assertDatabaseHas('grades', [
            'student_id' => $this->student2->id,
            'component_id' => $this->componentMath->id,
            'period_id' => $this->activePeriod->id,
            'value' => 92.00,
        ]);
    }

    public function test_be_cbt_05_teacher_cannot_force_overwrite_locked_grades_without_privilege(): void
    {
        Grade::create([
            'student_id' => $this->student1->id,
            'component_id' => $this->componentMath->id,
            'period_id' => $this->activePeriod->id,
            'value' => 70.00,
            'source' => 'manual',
            'locked_at' => Carbon::now(),
            'entered_by' => $this->teacherUser->id,
        ]);

        $payload = [
            'component_id' => $this->componentMath->id,
            'period_id' => $this->activePeriod->id,
            'overwrite_locked' => true,
        ];

        // Teacher tanpa permission grade.unlock mencoba overwrite nilai terkunci
        $response = $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson("/api/v1/exams/{$this->examMath->id}/publish-gradebook", $payload);

        $response->assertStatus(403)
            ->assertJsonPath('error.message', 'Anda tidak memiliki hak akses untuk menimpa nilai yang telah dikunci.');
    }

    public function test_be_cbt_05_admin_can_force_overwrite_locked_grades(): void
    {
        Grade::create([
            'student_id' => $this->student1->id,
            'component_id' => $this->componentMath->id,
            'period_id' => $this->activePeriod->id,
            'value' => 70.00,
            'source' => 'manual',
            'locked_at' => Carbon::now(),
            'entered_by' => $this->teacherUser->id,
        ]);

        $payload = [
            'component_id' => $this->componentMath->id,
            'period_id' => $this->activePeriod->id,
            'overwrite_locked' => true,
        ];

        // Admin berhasil menimpa nilai yang terkunci
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/exams/{$this->examMath->id}/publish-gradebook", $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.grades_synced', 2)
            ->assertJsonPath('data.skipped_locked', 0);

        $this->assertDatabaseHas('grades', [
            'student_id' => $this->student1->id,
            'component_id' => $this->componentMath->id,
            'period_id' => $this->activePeriod->id,
            'value' => 88.50,
        ]);
    }
}
