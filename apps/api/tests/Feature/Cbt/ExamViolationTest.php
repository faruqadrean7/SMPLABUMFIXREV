<?php

namespace Tests\Feature\Cbt;

use App\Models\AcademicYear;
use App\Models\ClassStudent;
use App\Models\Exam;
use App\Models\ExamParticipant;
use App\Models\ExamQuestion;
use App\Models\ExamRoom;
use App\Models\ExamSession;
use App\Models\GradeLevel;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\QuestionOption;
use App\Models\Role;
use App\Models\Room;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ExamViolationTest extends TestCase
{
    use RefreshDatabase;

    protected User $studentUser;

    protected Student $student;

    protected Exam $exam;

    protected ExamRoom $examRoom;

    protected Question $question;

    protected function setUp(): void
    {
        parent::setUp();

        $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);
        $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);
        $roleAdmin = Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Admin', 'is_system' => true]);

        $adminUser = User::create([
            'username' => 'admin_cbt_test',
            'email' => 'admin_cbt_test@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $adminUser->roles()->attach($roleAdmin->id);

        $this->studentUser = User::create([
            'username' => '20269901',
            'email' => 'siswa_cbt@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->studentUser->roles()->attach($roleStudent->id);

        $teacherUser = User::create([
            'username' => 'proctor_cbt_test',
            'email' => 'proctor_cbt_test@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $teacherUser->roles()->attach($roleTeacher->id);

        $teacher = Teacher::create([
            'user_id' => $teacherUser->id,
            'nip' => '198501012010011099',
            'full_name' => 'Proctor Test, S.Pd',
            'gender' => 'L',
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

        $classA = SchoolClass::create([
            'academic_year_id' => $ay->id,
            'grade_level_id' => $gradeLevel->id,
            'homeroom_teacher_id' => $teacher->id,
            'name' => '10-A',
            'capacity' => 36,
        ]);

        $subject = Subject::create([
            'name' => 'Matematika Wajib',
            'code' => 'MAT-10',
            'subject_group' => 'muatan_nasional',
        ]);

        $room = Room::create([
            'name' => 'Lab Komputer 1',
            'code' => 'LAB-01',
            'room_type' => 'laboratorium',
            'capacity' => 40,
        ]);

        $this->student = Student::create([
            'user_id' => $this->studentUser->id,
            'full_name' => 'Citra Kirana',
            'nis' => '20269901',
            'nisn' => '0099887766',
            'nik_enc' => 'enc_3578010101019999',
            'gender' => 'P',
            'birth_place' => 'Malang',
            'birth_date' => '2010-05-15',
            'entry_date' => '2026-07-01',
            'status' => 'aktif',
        ]);

        ClassStudent::create([
            'class_id' => $classA->id,
            'student_id' => $this->student->id,
            'joined_at' => '2026-07-15',
        ]);

        $this->exam = Exam::create([
            'subject_id' => $subject->id,
            'created_by' => $adminUser->id,
            'title' => 'Asesmen Harian Aljabar',
            'exam_type' => 'harian',
            'duration_min' => 90,
            'start_at' => Carbon::now()->subMinutes(15),
            'end_at' => Carbon::now()->addHours(3),
            'passing_grade' => 75.0,
            'status' => 'berlangsung',
        ]);

        $this->examRoom = ExamRoom::create([
            'exam_id' => $this->exam->id,
            'room_id' => $room->id,
            'proctor_id' => $teacherUser->id,
            'session_no' => 1,
            'token' => 'TOKENCBT',
            'token_expires_at' => Carbon::now()->addHours(2),
        ]);

        ExamParticipant::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student->id,
            'exam_room_id' => $this->examRoom->id,
            'package_code' => 'A',
        ]);

        $bank = QuestionBank::create([
            'name' => 'Bank Soal Aljabar',
            'subject_id' => $subject->id,
            'grade_level_id' => $gradeLevel->id,
            'owner_id' => $adminUser->id,
        ]);

        $this->question = Question::create([
            'bank_id' => $bank->id,
            'question_type' => 'pg',
            'body' => ['content' => 'Nilai x dari 2x + 4 = 10 adalah?'],
            'weight' => 10.0,
            'version' => 1,
            'status' => 'aktif',
        ]);

        QuestionOption::create([
            'question_id' => $this->question->id,
            'label' => 'A',
            'body' => ['text' => '3'],
            'is_correct' => true,
        ]);

        QuestionOption::create([
            'question_id' => $this->question->id,
            'label' => 'B',
            'body' => ['text' => '5'],
            'is_correct' => false,
        ]);

        ExamQuestion::create([
            'exam_id' => $this->exam->id,
            'question_id' => $this->question->id,
            'sort_order' => 1,
            'package_code' => 'A',
        ]);
    }

    /**
     * GAP-07: Endpoint lapor pelanggaran menaikkan counter secara atomik dan server memutuskan konsekuensi.
     */
    public function test_gap_07_violation_counter_increments_atomically_and_triggers_threshold_consequences(): void
    {
        $session = ExamSession::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student->id,
            'status' => 'mengerjakan',
            'started_at' => Carbon::now()->subMinutes(10),
            'ends_at' => Carbon::now()->addMinutes(80),
            'deadline_at' => Carbon::now()->addMinutes(80),
            'seed' => 'seedviolation',
            'violation_count' => 0,
        ]);

        $this->app['auth']->forgetGuards();

        // Pelanggaran 1 & 2 -> warning
        $res1 = $this->actingAs($this->studentUser, 'sanctum')
            ->postJson("/api/v1/exams/{$this->exam->id}/log-violation", [
                'violation_type' => 'tab_switch',
            ]);
        $res1->assertStatus(200)
            ->assertJsonPath('data.violation_count', 1)
            ->assertJsonPath('data.consequence', 'warning')
            ->assertJsonPath('data.session_status', 'mengerjakan');

        $res2 = $this->actingAs($this->studentUser, 'sanctum')
            ->postJson("/api/v1/exams/{$this->exam->id}/violations", [
                'violation_type' => 'window_blur',
            ]);
        $res2->assertStatus(200)
            ->assertJsonPath('data.violation_count', 2)
            ->assertJsonPath('data.consequence', 'warning');

        // Pelanggaran 3 -> threshold mengunci sesi (locked)
        $res3 = $this->actingAs($this->studentUser, 'sanctum')
            ->postJson("/api/v1/exams/{$this->exam->id}/log-violation", [
                'violation_type' => 'fullscreen_exit',
            ]);
        $res3->assertStatus(200)
            ->assertJsonPath('data.violation_count', 3)
            ->assertJsonPath('data.is_locked', true)
            ->assertJsonPath('data.consequence', 'locked')
            ->assertJsonPath('data.session_status', 'terkunci');

        $session->refresh();
        $this->assertEquals('terkunci', $session->status);
        $this->assertEquals(3, $session->violation_count);

        // Buka kunci lagi oleh proctor untuk menguji threshold diskualifikasi
        $session->update(['status' => 'mengerjakan']);

        // Pelanggaran 4 -> warning
        $this->actingAs($this->studentUser, 'sanctum')
            ->postJson("/api/v1/exams/{$this->exam->id}/log-violation", ['violation_type' => 'tab_switch']);

        // Pelanggaran 5 -> diskualifikasi / auto-submit
        $res5 = $this->actingAs($this->studentUser, 'sanctum')
            ->postJson("/api/v1/exams/{$this->exam->id}/log-violation", ['violation_type' => 'tab_switch']);
        $res5->assertStatus(200)
            ->assertJsonPath('data.violation_count', 5)
            ->assertJsonPath('data.consequence', 'disqualified')
            ->assertJsonPath('data.session_status', 'selesai');

        $session->refresh();
        $this->assertEquals('selesai', $session->status);
    }
}
