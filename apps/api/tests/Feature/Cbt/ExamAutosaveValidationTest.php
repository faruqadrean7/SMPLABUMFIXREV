<?php

namespace Tests\Feature\Cbt;

use App\Models\Exam;
use App\Models\ExamParticipant;
use App\Models\ExamQuestion;
use App\Models\ExamRoom;
use App\Models\ExamSession;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\QuestionOption;
use App\Models\Role;
use App\Models\Room;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExamAutosaveValidationTest extends TestCase
{
    use RefreshDatabase;

    protected User $studentUser;

    protected Student $student;

    protected Exam $exam;

    protected Question $validQuestion;

    protected Question $foreignQuestion;

    protected ExamSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::firstOrCreate(['code' => 'admin'], ['name' => 'Admin', 'is_system' => true]);
        $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);

        $adminUser = User::create([
            'username' => 'admin_autosave',
            'email' => 'admin_autosave@test.sch.id',
            'password_hash' => Hash::make('secret123'),
            'status' => 'active',
        ]);
        $adminUser->roles()->attach($roleAdmin->id);

        $this->studentUser = User::create([
            'username' => 'siswa_autosave',
            'email' => 'siswa_autosave@test.sch.id',
            'password_hash' => Hash::make('secret123'),
            'status' => 'active',
        ]);
        $this->studentUser->roles()->attach($roleStudent->id);

        $this->student = Student::create([
            'user_id' => $this->studentUser->id,
            'full_name' => 'Siswa Autosave',
            'nis' => '13001',
            'nisn' => '0013001001',
            'nik_enc' => 'enc_nik_autosave',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2010-01-01',
            'entry_date' => '2026-07-01',
            'status' => 'aktif',
        ]);

        $subject = Subject::create([
            'code' => 'BIO-AS',
            'name' => 'Biologi Autosave',
            'subject_group' => 'muatan_nasional',
        ]);

        $room = Room::create([
            'code' => 'LAB-AS',
            'name' => 'Lab Autosave',
            'room_type' => 'lab',
            'capacity' => 30,
        ]);

        $bank = QuestionBank::create([
            'subject_id' => $subject->id,
            'owner_id' => $adminUser->id,
            'name' => 'Bank Soal Autosave',
        ]);

        $this->validQuestion = Question::create([
            'bank_id' => $bank->id,
            'question_type' => 'pg',
            'body' => ['text' => 'Soal Resmi Ujian'],
            'weight' => 1.0,
            'status' => 'aktif',
        ]);
        QuestionOption::create(['question_id' => $this->validQuestion->id, 'label' => 'A', 'body' => ['text' => 'Benar'], 'is_correct' => true]);

        // Foreign Question: belongs to bank, but NOT attached to this exam
        $this->foreignQuestion = Question::create([
            'bank_id' => $bank->id,
            'question_type' => 'pg',
            'body' => ['text' => 'Soal Asing di Luar Ujian'],
            'weight' => 1.0,
            'status' => 'aktif',
        ]);
        QuestionOption::create(['question_id' => $this->foreignQuestion->id, 'label' => 'A', 'body' => ['text' => 'Asing'], 'is_correct' => true]);

        $this->exam = Exam::create([
            'subject_id' => $subject->id,
            'created_by' => $adminUser->id,
            'title' => 'Ujian Biologi Autosave',
            'exam_type' => 'UTS',
            'duration_min' => 60,
            'start_at' => Carbon::now()->subMinutes(10),
            'end_at' => Carbon::now()->addHours(2),
            'passing_grade' => 75.0,
            'status' => 'berlangsung',
        ]);

        // Only validQuestion is attached to the exam
        ExamQuestion::create([
            'exam_id' => $this->exam->id,
            'question_id' => $this->validQuestion->id,
            'sort_order' => 1,
        ]);

        $examRoom = ExamRoom::create([
            'exam_id' => $this->exam->id,
            'room_id' => $room->id,
            'proctor_id' => $adminUser->id,
            'session_no' => 1,
            'token' => 'TKNATS',
            'token_expires_at' => Carbon::now()->addHours(2),
        ]);

        ExamParticipant::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student->id,
            'exam_room_id' => $examRoom->id,
        ]);

        $this->session = ExamSession::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student->id,
            'status' => 'mengerjakan',
            'started_at' => Carbon::now()->subMinutes(10),
            'deadline_at' => Carbon::now()->addMinutes(50),
            'seed' => 'seedautosave',
        ]);
    }

    public function test_p2_sec_data_01_save_answer_succeeds_for_valid_exam_question(): void
    {
        $response = $this->actingAs($this->studentUser, 'sanctum')
            ->postJson("/api/v1/exams/{$this->exam->id}/save-answer", [
                'question_id' => $this->validQuestion->id,
                'answer' => 'A',
                'is_flagged' => false,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.question_id', $this->validQuestion->id)
            ->assertJsonPath('data.is_saved', true);

        $this->assertDatabaseHas('exam_answers', [
            'session_id' => $this->session->id,
            'question_id' => $this->validQuestion->id,
        ]);
    }

    public function test_p2_sec_data_01_save_answer_rejected_for_foreign_question_not_in_exam(): void
    {
        $response = $this->actingAs($this->studentUser, 'sanctum')
            ->postJson("/api/v1/exams/{$this->exam->id}/save-answer", [
                'question_id' => $this->foreignQuestion->id,
                'answer' => 'A',
                'is_flagged' => false,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.message', 'Butir soal tidak terdaftar pada paket ujian ini.');

        $this->assertDatabaseMissing('exam_answers', [
            'session_id' => $this->session->id,
            'question_id' => $this->foreignQuestion->id,
        ]);
    }

    public function test_p2_sec_data_01_save_answer_rejected_for_random_nonexistent_question_id(): void
    {
        $randomQuestionId = Str::uuid()->toString();

        $response = $this->actingAs($this->studentUser, 'sanctum')
            ->postJson("/api/v1/exams/{$this->exam->id}/save-answer", [
                'question_id' => $randomQuestionId,
                'answer' => 'B',
                'is_flagged' => false,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['question_id']);

        $this->assertDatabaseMissing('exam_answers', [
            'session_id' => $this->session->id,
            'question_id' => $randomQuestionId,
        ]);
    }
}
