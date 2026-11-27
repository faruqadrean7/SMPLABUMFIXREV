<?php

namespace Tests\Feature\Academic;

use App\Models\AcademicYear;
use App\Models\ClassStudent;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Batch 2B-S — P1-14: Pengujian regresi anti-IDOR komponen nilai, siswa lintas kelas,
 * komponen global (class_id IS NULL), null skip pada bulk guru, & status is_locked progress report.
 */
class GradingAuthorizationHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected string $schoolId;

    protected User $teacherAUser;

    protected User $teacherBUser;

    protected Teacher $teacherA;

    protected Teacher $teacherB;

    protected SchoolClass $classA;

    protected SchoolClass $classB;

    protected Subject $subjectMath;

    protected Subject $subjectPhysics;

    protected Semester $semester;

    protected GradePeriod $period;

    protected Student $studentA;

    protected Student $studentB;

    protected Student $formerStudentA;

    protected GradeComponent $classAComp;

    protected GradeComponent $classBComp;

    protected GradeComponent $globalMathComp;

    protected GradeComponent $globalPhysicsComp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->schoolId = (string) Str::uuid();

        $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);
        $permWrite = Permission::firstOrCreate(['code' => 'academic.grade.write'], ['description' => 'Tulis nilai']);
        $permRead = Permission::firstOrCreate(['code' => 'academic.grade.read'], ['description' => 'Baca nilai']);
        $permCbt = Permission::firstOrCreate(['code' => 'cbt.exam.manage'], ['description' => 'Kelola CBT']);
        $roleTeacher->permissions()->syncWithoutDetaching([$permWrite->id, $permRead->id, $permCbt->id]);

        $this->teacherAUser = $this->makeUser('p114_guru_a', $roleTeacher, $this->schoolId);
        $this->teacherBUser = $this->makeUser('p114_guru_b', $roleTeacher, $this->schoolId);

        $this->teacherA = Teacher::create([
            'school_id' => $this->schoolId,
            'user_id' => $this->teacherAUser->id,
            'nip' => '198501012010011001',
            'full_name' => 'Guru A Matematika Fiktif',
            'gender' => 'L',
        ]);

        $this->teacherB = Teacher::create([
            'school_id' => $this->schoolId,
            'user_id' => $this->teacherBUser->id,
            'nip' => '198602022011012002',
            'full_name' => 'Guru B Fisika Fiktif',
            'gender' => 'P',
        ]);

        $ay = AcademicYear::create([
            'school_id' => $this->schoolId,
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ]);

        $this->semester = Semester::create([
            'academic_year_id' => $ay->id,
            'name' => 'Ganjil',
            'start_date' => '2026-07-01',
            'end_date' => '2026-12-31',
            'is_active' => true,
        ]);

        $this->period = GradePeriod::create([
            'semester_id' => $this->semester->id,
            'month' => 10,
            'status' => 'open',
        ]);

        $level = GradeLevel::create(['name' => 'Kelas 8', 'level' => 8]);

        $this->classA = SchoolClass::create([
            'school_id' => $this->schoolId,
            'academic_year_id' => $ay->id,
            'grade_level_id' => $level->id,
            'homeroom_teacher_id' => $this->teacherA->id,
            'name' => '8-A',
        ]);

        $this->classB = SchoolClass::create([
            'school_id' => $this->schoolId,
            'academic_year_id' => $ay->id,
            'grade_level_id' => $level->id,
            'homeroom_teacher_id' => $this->teacherB->id,
            'name' => '8-B',
        ]);

        $this->subjectMath = Subject::create([
            'school_id' => $this->schoolId,
            'code' => 'MTK',
            'name' => 'Matematika',
            'subject_group' => 'wajib',
        ]);

        $this->subjectPhysics = Subject::create([
            'school_id' => $this->schoolId,
            'code' => 'FIS',
            'name' => 'Fisika',
            'subject_group' => 'wajib',
        ]);

        // Guru A mengajar MTK di 8-A; Guru B mengajar FIS di 8-B
        TeachingAssignment::create([
            'teacher_id' => $this->teacherA->id,
            'class_id' => $this->classA->id,
            'subject_id' => $this->subjectMath->id,
            'semester_id' => $this->semester->id,
        ]);
        TeachingAssignment::create([
            'teacher_id' => $this->teacherB->id,
            'class_id' => $this->classB->id,
            'subject_id' => $this->subjectPhysics->id,
            'semester_id' => $this->semester->id,
        ]);

        $this->studentA = $this->makeStudent('202611401', '0011400001', '3573010101120101', 'Siswa Kelas A Fiktif');
        $this->studentB = $this->makeStudent('202611402', '0011400002', '3573010101120102', 'Siswa Kelas B Fiktif');
        $this->formerStudentA = $this->makeStudent('202611403', '0011400003', '3573010101120103', 'Eks Siswa Kelas A Fiktif');

        ClassStudent::create([
            'class_id' => $this->classA->id,
            'student_id' => $this->studentA->id,
            'joined_at' => '2026-07-01',
        ]);
        ClassStudent::create([
            'class_id' => $this->classB->id,
            'student_id' => $this->studentB->id,
            'joined_at' => '2026-07-01',
        ]);
        ClassStudent::create([
            'class_id' => $this->classA->id,
            'student_id' => $this->formerStudentA->id,
            'joined_at' => '2025-07-01',
            'left_at' => '2026-06-30',
        ]);

        $this->classAComp = GradeComponent::create([
            'subject_id' => $this->subjectMath->id,
            'semester_id' => $this->semester->id,
            'class_id' => $this->classA->id,
            'code' => 'TGS',
            'name' => 'Tugas Kelas A',
            'weight' => 40,
        ]);

        $this->classBComp = GradeComponent::create([
            'subject_id' => $this->subjectPhysics->id,
            'semester_id' => $this->semester->id,
            'class_id' => $this->classB->id,
            'code' => 'TGS',
            'name' => 'Tugas Kelas B',
            'weight' => 40,
        ]);

        $this->globalMathComp = GradeComponent::create([
            'subject_id' => $this->subjectMath->id,
            'semester_id' => $this->semester->id,
            'class_id' => null,
            'code' => 'UTS',
            'name' => 'UTS Matematika Global',
            'weight' => 60,
        ]);

        $this->globalPhysicsComp = GradeComponent::create([
            'subject_id' => $this->subjectPhysics->id,
            'semester_id' => $this->semester->id,
            'class_id' => null,
            'code' => 'UTS',
            'name' => 'UTS Fisika Global',
            'weight' => 60,
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

    protected function makeStudent(string $nis, string $nisn, string $nik, string $name): Student
    {
        return Student::create([
            'school_id' => $this->schoolId,
            'nis' => $nis,
            'nisn' => $nisn,
            'nik_enc' => $nik,
            'full_name' => $name,
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2012-05-01',
            'status' => 'aktif',
            'entry_date' => '2026-07-01',
        ]);
    }

    public function test_p1_14_teacher_cannot_write_or_delete_grades_for_other_class_or_unassigned_global_component(): void
    {
        // 1. Akses sah: Guru A input nilai siswa aktif kelas A pada komponen kelas A & komponen global MTK -> 201
        $this->actingAs($this->teacherAUser, 'sanctum')
            ->postJson('/api/v1/grades', [
                'student_id' => $this->studentA->id,
                'component_id' => $this->classAComp->id,
                'period_id' => $this->period->id,
                'value' => 88,
            ])
            ->assertStatus(201);

        $this->actingAs($this->teacherAUser, 'sanctum')
            ->postJson('/api/v1/grades', [
                'student_id' => $this->studentA->id,
                'component_id' => $this->globalMathComp->id,
                'period_id' => $this->period->id,
                'value' => 92,
            ])
            ->assertStatus(201);

        // 2. IDOR: Guru A coba menulis nilai untuk Siswa Kelas B pada komponen kelas A -> 403
        $this->actingAs($this->teacherAUser, 'sanctum')
            ->postJson('/api/v1/grades', [
                'student_id' => $this->studentB->id,
                'component_id' => $this->classAComp->id,
                'period_id' => $this->period->id,
                'value' => 99,
            ])
            ->assertStatus(403);

        // 3. IDOR: Guru A coba menulis nilai untuk eks-siswa kelas A (left_at terisi) -> 403
        $this->actingAs($this->teacherAUser, 'sanctum')
            ->postJson('/api/v1/grades', [
                'student_id' => $this->formerStudentA->id,
                'component_id' => $this->classAComp->id,
                'period_id' => $this->period->id,
                'value' => 75,
            ])
            ->assertStatus(403);

        // 4. IDOR komponen global mapel lain: Guru A coba menulis nilai ke UTS Fisika Global -> 403
        $this->actingAs($this->teacherAUser, 'sanctum')
            ->postJson('/api/v1/grades', [
                'student_id' => $this->studentB->id,
                'component_id' => $this->globalPhysicsComp->id,
                'period_id' => $this->period->id,
                'value' => 10,
            ])
            ->assertStatus(403);

        // 5. IDOR siswa kelas lain pada komponen global MTK: Guru A coba menilai Siswa B -> 403
        $this->actingAs($this->teacherAUser, 'sanctum')
            ->postJson('/api/v1/grades', [
                'student_id' => $this->studentB->id,
                'component_id' => $this->globalMathComp->id,
                'period_id' => $this->period->id,
                'value' => 50,
            ])
            ->assertStatus(403);

        // 6. IDOR deleteGrade lintas mapel/kelas
        $gradeB = Grade::create([
            'student_id' => $this->studentB->id,
            'component_id' => $this->globalPhysicsComp->id,
            'period_id' => $this->period->id,
            'value' => 84,
            'source' => 'manual',
            'entered_by' => $this->teacherBUser->id,
        ]);

        $this->actingAs($this->teacherAUser, 'sanctum')
            ->deleteJson("/api/v1/grades/{$gradeB->id}")
            ->assertStatus(403);
        $this->assertDatabaseHas('grades', ['id' => $gradeB->id]);
    }

    public function test_p1_14_teacher_portal_bulk_grades_enforces_whitelist_includes_global_components_and_skips_null(): void
    {
        // 1. GET /me/teacher/grades menyertakan komponen global (class_id IS NULL) & hanya siswa aktif (bukan formerStudentA)
        $resp = $this->actingAs($this->teacherAUser, 'sanctum')
            ->getJson("/api/v1/me/teacher/grades?class_id={$this->classA->id}&subject_id={$this->subjectMath->id}")
            ->assertStatus(200);

        $compIds = collect($resp->json('data.components'))->pluck('id')->all();
        $this->assertContains($this->classAComp->id, $compIds);
        $this->assertContains($this->globalMathComp->id, $compIds);

        $studentIds = collect($resp->json('data.grades'))->pluck('student_id')->all();
        $this->assertContains($this->studentA->id, $studentIds);
        $this->assertNotContains($this->formerStudentA->id, $studentIds);

        // 2. POST /me/teacher/grades/bulk dengan sel null/kosong berhasil (tidak 422)
        $this->actingAs($this->teacherAUser, 'sanctum')
            ->postJson('/api/v1/me/teacher/grades/bulk', [
                'class_id' => $this->classA->id,
                'subject_id' => $this->subjectMath->id,
                'period_id' => $this->period->id,
                'grades' => [
                    [
                        'student_id' => $this->studentA->id,
                        'scores' => [
                            $this->classAComp->id => 85,
                            $this->globalMathComp->id => null,
                        ],
                    ],
                ],
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('grades', [
            'student_id' => $this->studentA->id,
            'component_id' => $this->classAComp->id,
            'value' => 85,
        ]);

        // 3. IDOR di bulk: kirim student_id kelas B -> 403
        $this->actingAs($this->teacherAUser, 'sanctum')
            ->postJson('/api/v1/me/teacher/grades/bulk', [
                'class_id' => $this->classA->id,
                'subject_id' => $this->subjectMath->id,
                'period_id' => $this->period->id,
                'grades' => [
                    [
                        'student_id' => $this->studentB->id,
                        'scores' => [$this->classAComp->id => 99],
                    ],
                ],
            ])
            ->assertStatus(403);

        // 4. IDOR di bulk: kirim component_id kelas/mapel lain -> 403
        $this->actingAs($this->teacherAUser, 'sanctum')
            ->postJson('/api/v1/me/teacher/grades/bulk', [
                'class_id' => $this->classA->id,
                'subject_id' => $this->subjectMath->id,
                'period_id' => $this->period->id,
                'grades' => [
                    [
                        'student_id' => $this->studentA->id,
                        'scores' => [$this->classBComp->id => 99],
                    ],
                ],
            ])
            ->assertStatus(403);
    }

    public function test_p1_14_cbt_publish_to_gradebook_scopes_to_active_class_students_and_validates_component_auth(): void
    {
        $exam = Exam::create([
            'school_id' => $this->schoolId,
            'subject_id' => $this->subjectMath->id,
            'created_by' => $this->teacherAUser->id,
            'title' => 'Ujian Matematika Gabungan',
            'exam_type' => 'uh',
            'duration_min' => 60,
            'start_at' => now()->subHours(2),
            'end_at' => now()->subHour(),
            'status' => 'selesai',
        ]);

        ExamSession::create([
            'exam_id' => $exam->id,
            'student_id' => $this->studentA->id,
            'status' => 'selesai',
            'started_at' => now()->subHours(2),
            'submitted_at' => now()->subHour(),
            'score' => 91,
            'seed' => 'seed-a',
        ]);
        ExamSession::create([
            'exam_id' => $exam->id,
            'student_id' => $this->studentB->id,
            'status' => 'selesai',
            'started_at' => now()->subHours(2),
            'submitted_at' => now()->subHour(),
            'score' => 77,
            'seed' => 'seed-b',
        ]);

        // Publikasi ke komponen spesifik kelas A hanya menyinkronkan Siswa A, tidak Siswa B
        $this->actingAs($this->teacherAUser, 'sanctum')
            ->postJson("/api/v1/exams/{$exam->id}/publish-gradebook", [
                'component_id' => $this->classAComp->id,
                'period_id' => $this->period->id,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.grades_synced', 1);

        $this->assertDatabaseHas('grades', [
            'student_id' => $this->studentA->id,
            'component_id' => $this->classAComp->id,
            'value' => 91,
        ]);
        $this->assertDatabaseMissing('grades', [
            'student_id' => $this->studentB->id,
            'component_id' => $this->classAComp->id,
        ]);
    }

    public function test_p1_14_progress_report_status_reflects_locked_grade_period_accurately(): void
    {
        $this->period->update(['status' => 'locked', 'locked_at' => now()]);

        $resp = $this->actingAs($this->teacherAUser, 'sanctum')
            ->getJson("/api/v1/progress-reports/class-status?class_id={$this->classA->id}&period_id={$this->period->id}")
            ->assertStatus(200);

        $this->assertTrue((bool) $resp->json('data.period.is_locked'));
    }
}
