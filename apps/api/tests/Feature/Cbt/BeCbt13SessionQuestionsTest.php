<?php

namespace Tests\Feature\Cbt;

use App\Models\Exam;
use App\Models\ExamParticipant;
use App\Models\ExamQuestion;
use App\Models\ExamRoom;
use App\Models\ExamSession;
use App\Models\File;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\QuestionOption;
use App\Models\Role;
use App\Models\Room;
use App\Models\School;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BeCbt13SessionQuestionsTest extends TestCase
{
    use RefreshDatabase;

    protected User $studentUser;

    protected Student $student;

    protected Exam $exam;

    protected ExamSession $session;

    protected File $mediaFile;

    protected Question $question;

    protected function setUp(): void
    {
        parent::setUp();

        $school = School::firstOrCreate(
            ['id' => '00000000-0000-0000-0000-000000000001'],
            ['name' => 'SMP Laboratorium UM', 'npsn' => '20533807', 'is_active' => true]
        );

        $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'school_id' => $school->id]);

        $this->studentUser = User::firstOrCreate(
            ['username' => 'siswa_ujian'],
            [
                'school_id' => $school->id,
                'email' => 'siswa.ujian@labschool.sch.id',
                'password_hash' => Hash::make('Password123!'),
                'status' => 'active',
                'must_change_password' => false,
            ]
        );
        $this->studentUser->roles()->syncWithoutDetaching([$roleStudent->id]);

        $this->student = Student::firstOrCreate(
            ['user_id' => $this->studentUser->id],
            [
                'school_id' => $school->id,
                'nis' => '12345',
                'nisn' => '0012345678',
                'nik_enc' => '3579012345670001',
                'gender' => 'L',
                'birth_place' => 'Malang',
                'birth_date' => '2010-01-01',
                'entry_date' => '2024-07-15',
                'religion' => 'islam',
                'address' => 'Jl. Ujian No. 1',
                'full_name' => 'Siswa Ujian CBT',
                'status' => 'aktif',
            ]
        );

        $subject = Subject::firstOrCreate(
            ['code' => 'IPA_CBT', 'school_id' => $school->id],
            ['name' => 'Ilmu Pengetahuan Alam', 'subject_group' => 'umum']
        );

        $bank = QuestionBank::firstOrCreate(
            ['name' => 'Bank Soal Ujian Siswa'],
            [
                'school_id' => $school->id,
                'subject_id' => $subject->id,
                'owner_id' => $this->studentUser->id,
            ]
        );

        $this->mediaFile = File::create([
            'school_id' => $school->id,
            'owner_id' => $bank->id,
            'owner_type' => 'question_bank',
            'bucket' => 'cbt',
            'object_key' => 'cbt/2026/10/soal-diagram.png',
            'mime_type' => 'image/png',
            'size_bytes' => 1024,
            'visibility' => 'privat',
        ]);

        $this->question = Question::create([
            'bank_id' => $bank->id,
            'question_type' => 'pg',
            'body' => [
                'content' => '<p>Perhatikan gambar berikut:</p><img data-file-id="'.$this->mediaFile->id.'" alt="Diagram Ujian">',
            ],
            'explanation' => 'Pembahasan rahasia guru yang tidak boleh bocor.',
            'weight' => 2.0,
            'version' => 1,
            'status' => 'aktif',
        ]);

        QuestionOption::create([
            'question_id' => $this->question->id,
            'label' => 'A',
            'body' => [
                'content' => '<p>Opsi bergambar:</p><img data-file-id="'.$this->mediaFile->id.'" alt="Opsi A">',
            ],
            'is_correct' => true,
            'match_key' => 'KUNCI_RAHASIA_A',
        ]);

        QuestionOption::create([
            'question_id' => $this->question->id,
            'label' => 'B',
            'body' => ['content' => 'Pilihan B'],
            'is_correct' => false,
        ]);

        $this->exam = Exam::create([
            'school_id' => $school->id,
            'subject_id' => $subject->id,
            'created_by' => $this->studentUser->id,
            'title' => 'Ujian Tengah Semester IPA',
            'exam_type' => 'uts',
            'status' => 'aktif',
            'duration_min' => 60,
            'start_at' => Carbon::now()->subMinutes(10),
            'end_at' => Carbon::now()->addHours(2),
            'show_score_after' => false,
        ]);

        ExamQuestion::create([
            'exam_id' => $this->exam->id,
            'question_id' => $this->question->id,
            'sort_order' => 1,
        ]);

        $room = Room::firstOrCreate(
            ['school_id' => $school->id, 'name' => 'Lab Komputer 1'],
            ['code' => 'LAB1', 'room_type' => 'lab', 'capacity' => 40]
        );

        $examRoom = ExamRoom::create([
            'exam_id' => $this->exam->id,
            'room_id' => $room->id,
            'session_no' => 1,
            'token' => 'TOKEN1',
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
            'started_at' => Carbon::now()->subMinutes(5),
            'deadline_at' => Carbon::now()->addMinutes(55),
            'ends_at' => Carbon::now()->addMinutes(55),
            'seed' => 'test-seed-1234',
        ]);
    }

    /**
     * TEST-BE-CBT-13-03: GET /api/v1/exams/{id}/questions menyuntikkan src bertanda-tangan ke tag <img> pada body & opsi siswa.
     */
    public function test_be_cbt_13_session_questions_inject_src(): void
    {
        Sanctum::actingAs($this->studentUser);

        $response = $this->getJson('/api/v1/exams/'.$this->exam->id.'/session-questions');

        $response->assertStatus(200);

        $questions = $response->json('data.questions');
        $this->assertNotEmpty($questions);

        $firstQ = $questions[0];
        $this->assertStringContainsString('data-file-id="'.$this->mediaFile->id.'"', $firstQ['body']['content']);
        $this->assertStringContainsString('src=', $firstQ['body']['content']);
        $this->assertStringContainsString('signature=', $firstQ['body']['content']);

        $optA = collect($firstQ['options'])->firstWhere('label', 'A');
        $this->assertNotNull($optA);
        $this->assertStringContainsString('data-file-id="'.$this->mediaFile->id.'"', $optA['body']['content']);
        $this->assertStringContainsString('src=', $optA['body']['content']);
        $this->assertStringContainsString('signature=', $optA['body']['content']);
    }

    /**
     * TEST-BE-CBT-13-04: GET /api/v1/exams/{id}/session-questions TIDAK PERNAH mengirimkan kunci jawaban (is_correct, match_key, explanation).
     */
    public function test_be_cbt_13_session_questions_exclude_answer_key(): void
    {
        Sanctum::actingAs($this->studentUser);

        $response = $this->getJson('/api/v1/exams/'.$this->exam->id.'/session-questions');

        $response->assertStatus(200);

        $rawJson = $response->getContent();
        // Pastikan tidak ada kunci jawaban atau pembahasan di payload JSON manapun
        $this->assertStringNotContainsString('is_correct', $rawJson);
        $this->assertStringNotContainsString('KUNCI_RAHASIA_A', $rawJson);
        $this->assertStringNotContainsString('Pembahasan rahasia guru', $rawJson);

        $questions = $response->json('data.questions');
        foreach ($questions as $q) {
            $this->assertArrayNotHasKey('explanation', $q);
            foreach ($q['options'] as $opt) {
                $this->assertArrayNotHasKey('is_correct', $opt);
                $this->assertArrayNotHasKey('match_key', $opt);
            }
        }
    }

    /**
     * TEST-BE-CBT-13-05: Siswa tanpa sesi aktif tidak dapat mengakses soal ujian.
     */
    public function test_be_cbt_13_session_requires_active_session(): void
    {
        $this->session->update(['status' => 'selesai']);

        Sanctum::actingAs($this->studentUser);

        $response = $this->getJson('/api/v1/exams/'.$this->exam->id.'/session-questions');

        $this->assertContains($response->status(), [403, 409, 422]);
    }

    /**
     * TEST-BE-CBT-13-06: Permintaan unauthenticated menghasilkan 401.
     */
    public function test_be_cbt_13_session_401(): void
    {
        $response = $this->getJson('/api/v1/exams/'.$this->exam->id.'/session-questions');

        $response->assertStatus(401);
    }
}
