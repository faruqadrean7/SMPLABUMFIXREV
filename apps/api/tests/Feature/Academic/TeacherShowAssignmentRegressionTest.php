<?php

namespace Tests\Feature\Academic;

use App\Models\AcademicYear;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Grade;
use App\Models\GradeComponent;
use App\Models\GradeLevel;
use App\Models\GradePeriod;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Services\GradeRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TeacherShowAssignmentRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacherUserA;

    protected User $teacherUserB;

    protected Teacher $teacherA;

    protected Teacher $teacherB;

    protected SchoolClass $class;

    protected Subject $subject;

    protected Semester $semester;

    protected GradePeriod $period;

    protected GradeComponent $component;

    protected Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);

        $this->teacherUserA = User::create([
            'username' => 'guru_sg_a',
            'email' => 'guru_sg_a@sekolah.sch.id',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $this->teacherUserA->roles()->attach($roleTeacher->id);

        $this->teacherUserB = User::create([
            'username' => 'guru_sg_b',
            'email' => 'guru_sg_b@sekolah.sch.id',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $this->teacherUserB->roles()->attach($roleTeacher->id);

        $this->teacherA = Teacher::create([
            'user_id' => $this->teacherUserA->id,
            'full_name' => 'Guru SpeedGrader A',
            'nip' => '198201012010011001',
            'status' => 'tetap',
        ]);

        $this->teacherB = Teacher::create([
            'user_id' => $this->teacherUserB->id,
            'full_name' => 'Guru SpeedGrader B',
            'nip' => '198201012010011002',
            'status' => 'tetap',
        ]);

        $ay = AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ]);

        $this->semester = Semester::create([
            'academic_year_id' => $ay->id,
            'name' => 'Ganjil',
            'start_date' => '2026-07-01',
            'end_date' => '2026-12-20',
            'is_active' => true,
        ]);

        $level = GradeLevel::create(['code' => 'VII', 'name' => 'Kelas VII', 'order_index' => 1]);
        $this->class = SchoolClass::create([
            'academic_year_id' => $ay->id,
            'grade_level_id' => $level->id,
            'name' => 'VII-A',
            'capacity' => 36,
        ]);

        $this->subject = Subject::create([
            'code' => 'INF7',
            'name' => 'Informatika',
            'subject_group' => 'wajib',
        ]);

        TeachingAssignment::create([
            'teacher_id' => $this->teacherA->id,
            'class_id' => $this->class->id,
            'subject_id' => $this->subject->id,
            'semester_id' => $this->semester->id,
        ]);

        $this->period = GradePeriod::create([
            'semester_id' => $this->semester->id,
            'month' => 10,
            'status' => 'open',
        ]);

        $this->component = GradeComponent::create([
            'subject_id' => $this->subject->id,
            'class_id' => $this->class->id,
            'semester_id' => $this->semester->id,
            'code' => 'TUGAS',
            'name' => 'Tugas Harian',
            'weight' => 30,
        ]);

        $studentUser = User::create([
            'username' => 'siswa_sg_1',
            'email' => 'siswa_sg_1@sekolah.sch.id',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $this->student = Student::create([
            'user_id' => $studentUser->id,
            'nis' => '20265501',
            'nisn' => '0055010101',
            'nik_enc' => '3578010101015501',
            'full_name' => 'Siswa SpeedGrader',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2012-03-03',
            'entry_date' => '2026-07-01',
            'status' => 'aktif',
        ]);
        $this->class->students()->attach($this->student->id, [
            'id' => (string) Str::uuid(),
            'joined_at' => '2026-07-01',
        ]);
    }

    public function test_p0_16_show_assignment_returns_grade_component_id_name_and_max_score(): void
    {
        $linked = Assignment::create([
            'teacher_id' => $this->teacherA->id,
            'class_id' => $this->class->id,
            'subject_id' => $this->subject->id,
            'title' => 'Praktikum Algoritma',
            'description' => 'Buat flowchart',
            'due_at' => '2026-10-15 23:59:00',
            'max_score' => 100,
            'grade_component_id' => $this->component->id,
        ]);

        $unlinked = Assignment::create([
            'teacher_id' => $this->teacherA->id,
            'class_id' => $this->class->id,
            'subject_id' => $this->subject->id,
            'title' => 'Tugas Lama Belum Tertaut',
            'description' => 'Data lama',
            'due_at' => '2026-10-18 23:59:00',
            'max_score' => 80,
            'grade_component_id' => null,
        ]);

        Sanctum::actingAs($this->teacherUserA);

        $this->getJson("/api/v1/me/teacher/assignments/{$linked->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $linked->id)
            ->assertJsonPath('data.grade_component_id', $this->component->id)
            ->assertJsonPath('data.grade_component_name', 'Tugas Harian')
            ->assertJsonPath('data.max_score', 100);

        $this->getJson("/api/v1/me/teacher/assignments/{$unlinked->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.grade_component_id', null)
            ->assertJsonPath('data.grade_component_name', null)
            ->assertJsonPath('data.max_score', 80);
    }

    public function test_p0_16_teacher_cannot_access_another_teachers_assignment_detail(): void
    {
        $assignmentA = Assignment::create([
            'teacher_id' => $this->teacherA->id,
            'class_id' => $this->class->id,
            'subject_id' => $this->subject->id,
            'title' => 'Tugas Rahasia Guru A',
            'due_at' => '2026-10-15 23:59:00',
            'max_score' => 100,
            'grade_component_id' => $this->component->id,
        ]);

        Sanctum::actingAs($this->teacherUserB);

        $this->getJson("/api/v1/me/teacher/assignments/{$assignmentA->id}")
            ->assertStatus(404);
    }

    public function test_p0_16_speedgrader_grade_submission_syncs_and_preserves_max_manual_auto_rule(): void
    {
        $assignment = Assignment::create([
            'teacher_id' => $this->teacherA->id,
            'class_id' => $this->class->id,
            'subject_id' => $this->subject->id,
            'title' => 'Tugas Terhubung Komponen',
            'due_at' => '2026-10-15 23:59:00',
            'max_score' => 100,
            'grade_component_id' => $this->component->id,
        ]);

        $submission = AssignmentSubmission::create([
            'assignment_id' => $assignment->id,
            'student_id' => $this->student->id,
            'submitted_at' => now(),
            'notes' => 'Sudah dikerjakan',
        ]);

        // Set nilai manual awal 92 di buku nilai untuk menguji aturan bisnis max(manual, auto)
        app(GradeRecorder::class)->recordManual(
            studentId: $this->student->id,
            componentId: $this->component->id,
            periodId: $this->period->id,
            value: 92.0,
            changedBy: $this->teacherUserA->id,
        );

        Sanctum::actingAs($this->teacherUserA);

        // 1. SpeedGrader memuat detail tugas dan melihat grade_component_id sah
        $detail = $this->getJson("/api/v1/me/teacher/assignments/{$assignment->id}")
            ->assertStatus(200);
        $this->assertEquals($this->component->id, $detail->json('data.grade_component_id'));

        // 2. Guru menilai tugas = 85 -> nilai efektif di grades WAJIB tetap 92 sesuai aturan max(manual, auto)
        $this->postJson("/api/v1/me/teacher/submissions/{$submission->id}/grade", [
            'score' => 85,
            'feedback' => 'Bagus, pertahankan.',
            'period_id' => $this->period->id,
        ])->assertStatus(200);

        $grade = Grade::where('student_id', $this->student->id)
            ->where('component_id', $this->component->id)
            ->where('period_id', $this->period->id)
            ->firstOrFail();

        $this->assertEquals(92.0, (float) $grade->value);
        $this->assertEquals(92.0, (float) $grade->manual_value);
        $this->assertEquals(85.0, (float) $grade->auto_value);

        // 3. Guru menilai ulang lebih tinggi = 96 -> nilai efektif naik ke 96 (max(92, 96) = 96)
        $this->postJson("/api/v1/me/teacher/submissions/{$submission->id}/grade", [
            'score' => 96,
            'feedback' => 'Sempurna setelah revisi.',
            'period_id' => $this->period->id,
        ])->assertStatus(200);

        $grade->refresh();
        $this->assertEquals(96.0, (float) $grade->value);
        $this->assertEquals(96.0, (float) $grade->auto_value);
    }
}
