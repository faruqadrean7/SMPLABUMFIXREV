<?php

namespace Tests\Feature\Cbt;

use App\Models\Exam;
use App\Models\ExamAnswer;
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
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExamReviewTest extends TestCase
{
    use RefreshDatabase;

    protected User $studentUser1;

    protected User $studentUser2;

    protected User $teacherUser;

    protected Student $student1;

    protected Student $student2;

    protected Subject $subject;

    protected QuestionBank $bank;

    protected Question $questionPg;

    protected Question $questionEssay;

    protected Exam $examAfterSubmit;

    protected ExamRoom $room1;

    protected function setUp(): void
    {
        parent::setUp();

        $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);
        $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);

        $this->studentUser1 = User::create([
            'username' => 'siswa_rev_1',
            'email' => 'siswa_rev1@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->studentUser1->roles()->attach($roleStudent->id);

        $this->student1 = Student::create([
            'user_id' => $this->studentUser1->id,
            'full_name' => 'Ahmad Reviewer',
            'nis' => '202600101',
            'nisn' => '0012345671',
            'nik_enc' => 'enc_3578010101010001',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2010-01-01',
            'entry_date' => '2026-07-01',
            'status' => 'aktif',
        ]);

        $this->studentUser2 = User::create([
            'username' => 'siswa_rev_2',
            'email' => 'siswa_rev2@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->studentUser2->roles()->attach($roleStudent->id);

        $this->student2 = Student::create([
            'user_id' => $this->studentUser2->id,
            'full_name' => 'Budi Non Participant',
            'nis' => '202600102',
            'nisn' => '0012345672',
            'nik_enc' => 'enc_3578010101010002',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2010-02-02',
            'entry_date' => '2026-07-01',
            'status' => 'aktif',
        ]);

        $this->teacherUser = User::create([
            'username' => 'guru_cbt',
            'email' => 'guru_cbt@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->teacherUser->roles()->attach($roleTeacher->id);

        $this->subject = Subject::create([
            'code' => 'KIM-10',
            'name' => 'Kimia',
            'subject_group' => 'umum',
        ]);

        $gradeLevel = GradeLevel::create([
            'name' => 'Kelas 10',
            'level' => 10,
        ]);

        $this->bank = QuestionBank::create([
            'name' => 'Bank Soal Kimia Dasar',
            'subject_id' => $this->subject->id,
            'grade_level_id' => $gradeLevel->id,
            'owner_id' => $this->teacherUser->id,
        ]);

        // Soal 1: PG
        $this->questionPg = Question::create([
            'bank_id' => $this->bank->id,
            'question_type' => 'pg',
            'body' => ['content' => 'Berapa nomor atom Hidrogen?'],
            'weight' => 50.0,
            'explanation' => 'Hidrogen memiliki nomor atom 1 karena hanya memiliki 1 proton.',
            'status' => 'aktif',
        ]);

        QuestionOption::create([
            'question_id' => $this->questionPg->id,
            'label' => 'A',
            'body' => ['text' => '1'],
            'is_correct' => true,
        ]);

        QuestionOption::create([
            'question_id' => $this->questionPg->id,
            'label' => 'B',
            'body' => ['text' => '2'],
            'is_correct' => false,
        ]);

        // Soal 2: Uraian
        $this->questionEssay = Question::create([
            'bank_id' => $this->bank->id,
            'question_type' => 'uraian',
            'body' => ['content' => 'Jelaskan perbedaan ikatan kovalen dan ionik!'],
            'weight' => 50.0,
            'explanation' => 'Ikatan kovalen berbagi elektron, sedangkan ikatan ionik serah-terima elektron.',
            'status' => 'aktif',
        ]);

        // Ruang
        $room = Room::create([
            'code' => 'LAB-KIM',
            'name' => 'Lab Kimia',
            'room_type' => 'laboratorium',
            'capacity' => 40,
        ]);

        // Ujian dengan mode after_submit
        $this->examAfterSubmit = Exam::create([
            'subject_id' => $this->subject->id,
            'created_by' => $this->teacherUser->id,
            'title' => 'Kuis Formatif Kimia Dasar',
            'exam_type' => 'kuis',
            'duration_min' => 45,
            'start_at' => Carbon::now()->subHour(),
            'end_at' => Carbon::now()->addHour(),
            'passing_grade' => 70.0,
            'shuffle_questions' => false,
            'shuffle_options' => false,
            'show_result_mode' => 'after_submit',
            'status' => 'berlangsung',
        ]);

        ExamQuestion::create([
            'exam_id' => $this->examAfterSubmit->id,
            'question_id' => $this->questionPg->id,
            'sort_order' => 1,
            'weight_override' => 50.0,
        ]);

        ExamQuestion::create([
            'exam_id' => $this->examAfterSubmit->id,
            'question_id' => $this->questionEssay->id,
            'sort_order' => 2,
            'weight_override' => 50.0,
        ]);

        $this->room1 = ExamRoom::create([
            'exam_id' => $this->examAfterSubmit->id,
            'room_id' => $room->id,
            'token' => 'KIM123',
            'token_expires_at' => Carbon::now()->addHour(),
        ]);

        ExamParticipant::create([
            'exam_id' => $this->examAfterSubmit->id,
            'student_id' => $this->student1->id,
            'exam_room_id' => $this->room1->id,
        ]);
    }

    public function test_student_can_view_review_after_submitting_exam_in_after_submit_mode(): void
    {
        $session = ExamSession::create([
            'exam_id' => $this->examAfterSubmit->id,
            'student_id' => $this->student1->id,
            'status' => 'selesai',
            'started_at' => Carbon::now()->subMinutes(30),
            'submitted_at' => Carbon::now()->subMinutes(5),
            'seed' => Str::random(16),
            'score' => 85.0,
        ]);

        ExamAnswer::create([
            'session_id' => $session->id,
            'question_id' => $this->questionPg->id,
            'answer' => ['selected_option' => 'A'],
            'score' => 50.0,
        ]);

        ExamAnswer::create([
            'session_id' => $session->id,
            'question_id' => $this->questionEssay->id,
            'answer' => 'Ikatan kovalen berbagi elektron bersama.',
            'score' => 35.0,
        ]);

        $response = $this->actingAs($this->studentUser1)
            ->getJson("/api/v1/exams/{$this->examAfterSubmit->id}/review");

        $response->assertStatus(200)
            ->assertJsonPath('data.exam.id', $this->examAfterSubmit->id)
            ->assertJsonPath('data.attempt.total_score', 85)
            ->assertJsonPath('data.attempt.is_passed', true)
            ->assertJsonPath('data.attempt.total_questions', 2)
            ->assertJsonPath('data.questions.0.is_correct', true)
            ->assertJsonPath('data.questions.0.explanation', 'Hidrogen memiliki nomor atom 1 karena hanya memiliki 1 proton.')
            ->assertJsonPath('data.questions.0.options.0.is_correct', true)
            ->assertJsonPath('data.questions.1.is_graded', true);
    }

    public function test_student_cannot_view_review_while_exam_session_is_still_in_progress(): void
    {
        ExamSession::create([
            'exam_id' => $this->examAfterSubmit->id,
            'student_id' => $this->student1->id,
            'status' => 'mengerjakan',
            'started_at' => Carbon::now()->subMinutes(10),
            'seed' => Str::random(16),
        ]);

        $response = $this->actingAs($this->studentUser1)
            ->getJson("/api/v1/exams/{$this->examAfterSubmit->id}/review");

        $response->assertStatus(403);
    }

    public function test_student_cannot_view_review_when_mode_is_never(): void
    {
        $this->examAfterSubmit->update(['show_result_mode' => 'never']);

        ExamSession::create([
            'exam_id' => $this->examAfterSubmit->id,
            'student_id' => $this->student1->id,
            'status' => 'selesai',
            'started_at' => Carbon::now()->subMinutes(30),
            'submitted_at' => Carbon::now()->subMinutes(5),
            'seed' => Str::random(16),
            'score' => 90.0,
        ]);

        $response = $this->actingAs($this->studentUser1)
            ->getJson("/api/v1/exams/{$this->examAfterSubmit->id}/review");

        $response->assertStatus(403);
    }

    public function test_after_release_mode_forbids_review_before_exam_is_released(): void
    {
        $this->examAfterSubmit->update([
            'show_result_mode' => 'after_release',
            'status' => 'berlangsung',
        ]);

        ExamSession::create([
            'exam_id' => $this->examAfterSubmit->id,
            'student_id' => $this->student1->id,
            'status' => 'selesai',
            'started_at' => Carbon::now()->subMinutes(30),
            'submitted_at' => Carbon::now()->subMinutes(5),
            'seed' => Str::random(16),
            'score' => 90.0,
        ]);

        $response = $this->actingAs($this->studentUser1)
            ->getJson("/api/v1/exams/{$this->examAfterSubmit->id}/review");

        $response->assertStatus(403);
    }

    public function test_after_release_mode_allows_review_after_exam_is_released(): void
    {
        $this->examAfterSubmit->update([
            'show_result_mode' => 'after_release',
            'status' => 'selesai',
        ]);

        ExamSession::create([
            'exam_id' => $this->examAfterSubmit->id,
            'student_id' => $this->student1->id,
            'status' => 'selesai',
            'started_at' => Carbon::now()->subMinutes(30),
            'submitted_at' => Carbon::now()->subMinutes(5),
            'seed' => Str::random(16),
            'score' => 90.0,
        ]);

        $response = $this->actingAs($this->studentUser1)
            ->getJson("/api/v1/exams/{$this->examAfterSubmit->id}/review");

        $response->assertStatus(200)
            ->assertJsonPath('data.attempt.status', 'selesai');
    }

    public function test_non_participant_student_cannot_view_review(): void
    {
        $response = $this->actingAs($this->studentUser2)
            ->getJson("/api/v1/exams/{$this->examAfterSubmit->id}/review");

        $response->assertStatus(403);
    }

    public function test_non_student_user_cannot_view_review(): void
    {
        $response = $this->actingAs($this->teacherUser)
            ->getJson("/api/v1/exams/{$this->examAfterSubmit->id}/review");

        $response->assertStatus(403);
    }

    public function test_ungraded_essay_shows_is_graded_false_in_review(): void
    {
        $session = ExamSession::create([
            'exam_id' => $this->examAfterSubmit->id,
            'student_id' => $this->student1->id,
            'status' => 'selesai',
            'started_at' => Carbon::now()->subMinutes(30),
            'submitted_at' => Carbon::now()->subMinutes(5),
            'seed' => Str::random(16),
            'score' => null, // Belum selesai koreksi esai
        ]);

        ExamAnswer::create([
            'session_id' => $session->id,
            'question_id' => $this->questionPg->id,
            'answer' => ['selected_option' => 'A'],
            'score' => 50.0,
        ]);

        ExamAnswer::create([
            'session_id' => $session->id,
            'question_id' => $this->questionEssay->id,
            'answer' => 'Ikatan kovalen...',
            'score' => null, // Belum dinilai guru
        ]);

        $response = $this->actingAs($this->studentUser1)
            ->getJson("/api/v1/exams/{$this->examAfterSubmit->id}/review");

        $response->assertStatus(200)
            ->assertJsonPath('data.attempt.has_ungraded_essay', true)
            ->assertJsonPath('data.questions.1.is_graded', false)
            ->assertJsonPath('data.questions.1.score', null);
    }

    public function test_session_questions_endpoint_does_not_leak_explanations_during_exam(): void
    {
        ExamSession::create([
            'exam_id' => $this->examAfterSubmit->id,
            'student_id' => $this->student1->id,
            'status' => 'mengerjakan',
            'started_at' => Carbon::now(),
            'deadline_at' => Carbon::now()->addMinutes(45),
            'seed' => Str::random(16),
        ]);

        $response = $this->actingAs($this->studentUser1)
            ->getJson("/api/v1/exams/{$this->examAfterSubmit->id}/session-questions");

        $response->assertStatus(200);

        // Pastikan tidak ada kunci jawaban atau penjelasan bocor saat pengerjaan
        $content = $response->getContent();
        $this->assertStringNotContainsString('Hidrogen memiliki nomor atom 1', $content);
        $this->assertStringNotContainsString('"is_correct":true', $content);
    }

    public function test_student_cannot_access_administrative_questions_endpoint(): void
    {
        $response = $this->actingAs($this->studentUser1)
            ->getJson("/api/v1/exams/{$this->examAfterSubmit->id}/questions");

        $response->assertStatus(403);
    }

    public function test_student_cannot_access_administrative_rooms_endpoint(): void
    {
        $response = $this->actingAs($this->studentUser1)
            ->getJson("/api/v1/exams/{$this->examAfterSubmit->id}/rooms");

        $response->assertStatus(403);
    }

    public function test_exam_show_endpoint_masks_token_and_omits_questions_for_student(): void
    {
        $response = $this->actingAs($this->studentUser1)
            ->getJson("/api/v1/exams/{$this->examAfterSubmit->id}");

        $response->assertStatus(200);
        $data = $response->json('data');

        // Questions relation tidak disertakan untuk siswa
        $this->assertArrayNotHasKey('questions', $data);

        // Token ruangan dihapus / tidak dibocorkan kepada siswa
        if (isset($data['rooms']) && count($data['rooms']) > 0) {
            $this->assertArrayNotHasKey('token', $data['rooms'][0]);
        }
    }

    public function test_review_denied_when_exam_is_draft_or_cancelled(): void
    {
        ExamSession::create([
            'exam_id' => $this->examAfterSubmit->id,
            'student_id' => $this->student1->id,
            'status' => 'selesai',
            'started_at' => Carbon::now()->subMinutes(30),
            'submitted_at' => Carbon::now()->subMinutes(5),
            'seed' => Str::random(16),
            'score' => 90.0,
        ]);

        $this->examAfterSubmit->update(['status' => 'draft']);

        $responseDraft = $this->actingAs($this->studentUser1)
            ->getJson("/api/v1/exams/{$this->examAfterSubmit->id}/review");
        $responseDraft->assertStatus(403);

        $this->examAfterSubmit->update(['status' => 'dibatalkan']);

        $responseCancel = $this->actingAs($this->studentUser1)
            ->getJson("/api/v1/exams/{$this->examAfterSubmit->id}/review");
        $responseCancel->assertStatus(403);
    }

    public function test_non_student_user_with_numeric_username_cannot_hijack_student_review(): void
    {
        $roleTeacher = Role::where('code', 'teacher')->first();

        // User guru dengan username sama persis dengan NIS siswa1
        $hijackerUser = User::create([
            'username' => $this->student1->nis,
            'email' => 'impostor@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $hijackerUser->roles()->attach($roleTeacher->id);

        $response = $this->actingAs($hijackerUser)
            ->getJson("/api/v1/exams/{$this->examAfterSubmit->id}/review");

        $response->assertStatus(403);
    }
}
