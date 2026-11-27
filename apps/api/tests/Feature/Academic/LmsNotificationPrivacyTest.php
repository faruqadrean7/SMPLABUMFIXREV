<?php

namespace Tests\Feature\Academic;

use App\Models\AcademicYear;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\GradeComponent;
use App\Models\GradeLevel;
use App\Models\GradePeriod;
use App\Models\Notification;
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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class LmsNotificationPrivacyTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacherUser;

    protected User $otherTeacherUser;

    protected User $studentUser;

    protected Teacher $teacher;

    protected Student $student;

    protected Assignment $assignment;

    protected AssignmentSubmission $submission;

    protected GradeComponent $gradeComponent;

    protected GradePeriod $period;

    protected function setUp(): void
    {
        parent::setUp();

        $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);
        $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);

        $permManage = Permission::firstOrCreate(['code' => 'academic.assignment.manage'], ['description' => 'Kelola tugas']);
        $roleTeacher->permissions()->syncWithoutDetaching([$permManage->id]);

        $this->teacherUser = User::create([
            'username' => 'p019_guru',
            'email' => 'p019_guru@example.test',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $this->teacherUser->roles()->attach($roleTeacher->id);

        $this->otherTeacherUser = User::create([
            'username' => 'p019_guru_lain',
            'email' => 'p019_guru_lain@example.test',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $this->otherTeacherUser->roles()->attach($roleTeacher->id);

        $this->studentUser = User::create([
            'username' => 'p019_siswa',
            'email' => 'p019_siswa@example.test',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $this->studentUser->roles()->attach($roleStudent->id);

        $this->teacher = Teacher::create([
            'user_id' => $this->teacherUser->id,
            'nip' => '198701012015011019',
            'full_name' => 'Guru Uji P019',
            'gender' => 'L',
        ]);

        Teacher::create([
            'user_id' => $this->otherTeacherUser->id,
            'nip' => '198701012015011020',
            'full_name' => 'Guru Lain P019',
            'gender' => 'P',
        ]);

        $ay = AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ]);

        $semester = Semester::create([
            'academic_year_id' => $ay->id,
            'name' => 'Ganjil',
            'start_date' => '2026-07-01',
            'end_date' => '2026-12-20',
            'is_active' => true,
        ]);

        $gl = GradeLevel::create([
            'code' => 'IX',
            'name' => 'Kelas 9',
            'order_index' => 3,
        ]);

        $class = SchoolClass::create([
            'academic_year_id' => $ay->id,
            'grade_level_id' => $gl->id,
            'name' => '9-A',
            'capacity' => 36,
        ]);

        $subject = Subject::create([
            'code' => 'IPA-P19',
            'name' => 'Ilmu Pengetahuan Alam',
            'subject_group' => 'wajib',
        ]);

        $this->period = GradePeriod::create([
            'semester_id' => $semester->id,
            'month' => (int) now()->month,
            'status' => 'open',
        ]);

        $this->gradeComponent = GradeComponent::create([
            'subject_id' => $subject->id,
            'class_id' => $class->id,
            'semester_id' => $semester->id,
            'code' => 'TGS-P19',
            'name' => 'Tugas Harian',
            'weight' => 30,
        ]);

        TeachingAssignment::create([
            'teacher_id' => $this->teacher->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'semester_id' => $semester->id,
        ]);

        $this->student = Student::create([
            'user_id' => $this->studentUser->id,
            'full_name' => 'Siswa Uji P019',
            'nis' => '20269919',
            'nisn' => '0099190019',
            'nik_enc' => 'enc_dummy_p019',
            'gender' => 'P',
            'birth_place' => 'Malang',
            'birth_date' => '2011-05-05',
            'entry_date' => '2026-07-01',
            'status' => 'aktif',
        ]);

        $class->students()->attach($this->student->id, [
            'id' => (string) Str::uuid(),
            'joined_at' => '2026-07-15',
        ]);

        $this->assignment = Assignment::create([
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'teacher_id' => $this->teacher->id,
            'title' => 'Laporan Praktikum Optik',
            'due_at' => Carbon::now()->addDays(2),
            'max_score' => 100,
            'grade_component_id' => $this->gradeComponent->id,
        ]);

        $this->submission = AssignmentSubmission::create([
            'assignment_id' => $this->assignment->id,
            'student_id' => $this->student->id,
            'submitted_at' => Carbon::now(),
        ]);
    }

    public function test_p0_19_grading_notification_omits_numeric_score_and_private_feedback(): void
    {
        $secretFeedback = 'RAHASIA-CATATAN-NILAI-9911: Perlu bimbingan remedial khusus.';

        $response = $this->actingAs($this->teacherUser, 'sanctum')
            ->patchJson("/api/v1/assignments/{$this->assignment->id}/submissions/{$this->submission->id}/grade", [
                'score' => 87.5,
                'feedback' => $secretFeedback,
            ]);

        $response->assertOk();
        $this->assertEquals(87.5, (float) $response->json('data.score'));

        $notification = Notification::where('user_id', $this->studentUser->id)
            ->where('notif_type', 'assignment.graded')
            ->first();

        $this->assertNotNull($notification, 'Notifikasi penilaian tugas harus tetap terkirim ke siswa.');
        $this->assertStringContainsString('Laporan Praktikum Optik', $notification->title);

        // P0-19 & 09_SECURITY_PRIVACY §6.7: body & data DILARANG memuat angka nilai atau feedback privat
        $this->assertStringNotContainsString('87.5', $notification->body);
        $this->assertStringNotContainsString('87,5', $notification->body);
        $this->assertStringNotContainsString('87', $notification->body);
        $this->assertStringNotContainsString('RAHASIA-CATATAN-NILAI-9911', $notification->body);
        $this->assertStringNotContainsString('remedial', $notification->body);

        $this->assertSame($this->assignment->id, $notification->data['assignment_id'] ?? null);
        $this->assertSame($this->submission->id, $notification->data['submission_id'] ?? null);
        $this->assertArrayNotHasKey('score', $notification->data);
        $this->assertArrayNotHasKey('feedback', $notification->data);

        // Pastikan GradeRecorder (max(manual, auto)) tetap mencatat nilai ke buku nilai
        $this->assertDatabaseHas('grades', [
            'student_id' => $this->student->id,
            'component_id' => $this->gradeComponent->id,
            'period_id' => $this->period->id,
            'value' => 87.50,
        ]);
    }

    public function test_p0_19_unauthorized_users_cannot_grade_or_trigger_notification(): void
    {
        $this->actingAs($this->otherTeacherUser, 'sanctum')
            ->patchJson("/api/v1/assignments/{$this->assignment->id}/submissions/{$this->submission->id}/grade", [
                'score' => 95,
                'feedback' => 'Tidak sah',
            ])
            ->assertStatus(403);

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->studentUser, 'sanctum')
            ->patchJson("/api/v1/assignments/{$this->assignment->id}/submissions/{$this->submission->id}/grade", [
                'score' => 100,
            ])
            ->assertStatus(403);

        $this->assertDatabaseCount('notifications', 0);
    }
}
