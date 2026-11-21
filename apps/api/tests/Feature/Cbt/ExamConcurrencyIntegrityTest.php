<?php

namespace Tests\Feature\Cbt;

use App\Models\Exam;
use App\Models\ExamAnswer;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ExamConcurrencyIntegrityTest extends TestCase
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

        $roleAdmin = Role::firstOrCreate(['code' => 'admin'], ['name' => 'Admin', 'is_system' => true]);
        $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);

        $admin = User::create([
            'username' => 'admin_concurrency',
            'email' => 'admin_concurrency@labschool.sch.id',
            'password_hash' => Hash::make('Admin123!'),
            'status' => 'active',
        ]);
        $admin->roles()->attach($roleAdmin->id);

        $this->studentUser = User::create([
            'username' => 'student_conc',
            'email' => 'student_conc@labschool.sch.id',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $this->studentUser->roles()->attach($roleStudent->id);

        $this->student = Student::create([
            'user_id' => $this->studentUser->id,
            'nisn' => '0099887766',
            'nis' => '8899',
            'nik_enc' => '3578010099887766',
            'full_name' => 'Siswa Konkurensi',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2008-01-01',
            'status' => 'aktif',
            'entry_date' => '2024-07-15',
        ]);

        $subject = Subject::create([
            'code' => 'FIS-CONC',
            'name' => 'Fisika Konkurensi',
            'subject_group' => 'pilihan',
        ]);

        $room = Room::create([
            'code' => 'LAB-CONC',
            'name' => 'Lab Konkurensi',
            'room_type' => 'laboratorium',
            'capacity' => 40,
        ]);

        $this->exam = Exam::create([
            'title' => 'Ujian Fisika Tes Konkurensi',
            'subject_id' => $subject->id,
            'created_by' => $admin->id,
            'exam_type' => 'ujian_akhir',
            'duration_min' => 90,
            'start_at' => Carbon::now()->subMinutes(10),
            'end_at' => Carbon::now()->addDays(2),
            'status' => 'aktif',
            'passing_grade' => 75.0,
        ]);

        $this->examRoom = ExamRoom::create([
            'exam_id' => $this->exam->id,
            'room_id' => $room->id,
            'proctor_id' => $admin->id,
            'session_no' => 1,
            'token' => 'FIS123',
            'token_expires_at' => Carbon::now()->addDays(2),
        ]);

        ExamParticipant::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student->id,
            'exam_room_id' => $this->examRoom->id,
        ]);

        $bank = QuestionBank::create([
            'name' => 'Bank Soal Fisika Konkurensi',
            'subject_id' => $subject->id,
            'owner_id' => $admin->id,
        ]);

        $this->question = Question::create([
            'bank_id' => $bank->id,
            'cp_code' => 'CP-FIS-01',
            'question_type' => 'pilihan_ganda',
            'body' => ['text' => 'Berapakah percepatan gravitasi bumi?'],
            'weight' => 10.0,
        ]);

        QuestionOption::create([
            'question_id' => $this->question->id,
            'label' => 'A',
            'body' => ['text' => '9.8 m/s^2'],
            'is_correct' => true,
        ]);

        ExamQuestion::create([
            'exam_id' => $this->exam->id,
            'question_id' => $this->question->id,
            'sort_order' => 1,
        ]);
    }

    public function test_concurrent_start_session_creates_single_session_consistently(): void
    {
        $token = $this->studentUser->createToken('test')->plainTextToken;

        // Panggilan pertama start session
        $res1 = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson("/api/v1/exams/{$this->exam->id}/start-session", ['token' => 'FIS123']);
        $res1->assertStatus(200);

        $sessionId1 = $res1->json('data.session_id');
        $this->assertNotNull($sessionId1);

        // Panggilan kedua start session (simulasi reconnect / paralel call)
        $res2 = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson("/api/v1/exams/{$this->exam->id}/start-session", ['token' => 'FIS123']);
        $res2->assertStatus(200);

        $sessionId2 = $res2->json('data.session_id');
        $this->assertEquals($sessionId1, $sessionId2);

        // Buktikan hanya ada tepat 1 baris sesi di database
        $this->assertEquals(1, ExamSession::where('exam_id', $this->exam->id)->where('student_id', $this->student->id)->count());
    }

    public function test_concurrent_save_answer_updates_without_duplicate_key_crash(): void
    {
        $token = $this->studentUser->createToken('test')->plainTextToken;

        // Start session terlebih dahulu
        $startRes = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson("/api/v1/exams/{$this->exam->id}/start-session", ['token' => 'FIS123']);
        $startRes->assertStatus(200);

        // Panggilan autosave pertama (jawaban A)
        $save1 = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson("/api/v1/exams/{$this->exam->id}/save-answer", [
                'question_id' => $this->question->id,
                'answer' => 'A',
                'is_flagged' => false,
            ]);
        $save1->assertStatus(200);

        // Panggilan autosave kedua secara cepat (ganti ke jawaban B)
        $save2 = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson("/api/v1/exams/{$this->exam->id}/save-answer", [
                'question_id' => $this->question->id,
                'answer' => 'B',
                'is_flagged' => true,
            ]);
        $save2->assertStatus(200);

        // Verifikasi database: tepat 1 baris jawaban dan nilainya terupdate ke 'B'
        $answers = ExamAnswer::where('question_id', $this->question->id)->get();
        $this->assertCount(1, $answers);
        $this->assertEquals('B', $answers->first()->answer);
        $this->assertTrue((bool) $answers->first()->is_flagged);
    }

    public function test_finish_session_is_strictly_idempotent_and_prevents_duplicate_submission(): void
    {
        $token = $this->studentUser->createToken('test')->plainTextToken;

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson("/api/v1/exams/{$this->exam->id}/start-session", ['token' => 'FIS123'])
            ->assertStatus(200);

        // Submit pertama
        $submit1 = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson("/api/v1/exams/{$this->exam->id}/finish-session");
        $submit1->assertStatus(200)
            ->assertJsonPath('data.status', 'selesai')
            ->assertJsonPath('data.message', 'Ujian berhasil diselesaikan.');

        $submittedAt1 = $submit1->json('data.submitted_at');

        // Submit kedua (panggilan ganda paralel)
        $submit2 = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson("/api/v1/exams/{$this->exam->id}/finish-session");
        $submit2->assertStatus(200)
            ->assertJsonPath('data.status', 'selesai')
            ->assertJsonPath('data.message', 'Ujian sudah diselesaikan sebelumnya.');

        $submittedAt2 = $submit2->json('data.submitted_at');
        $this->assertEquals($submittedAt1, $submittedAt2);

        // Cek sesi berstatus selesai
        $session = ExamSession::where('exam_id', $this->exam->id)->where('student_id', $this->student->id)->first();
        $this->assertEquals('selesai', $session->status);
    }

    public function test_auto_submit_command_synchronizes_without_conflict(): void
    {
        $session = ExamSession::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student->id,
            'status' => 'mengerjakan',
            'started_at' => Carbon::now()->subMinutes(120),
            'deadline_at' => Carbon::now()->subMinutes(30), // Melewati batas waktu
            'seed' => 'seed123',
            'extra_minutes' => 0,
        ]);

        // Jalankan perintah auto-submit
        $this->artisan('exam:auto-submit')->assertSuccessful();

        $session->refresh();
        $this->assertEquals('selesai', $session->status);

        // Verifikasi audit log tercatat
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'EXAM_SESSION_AUTO_SUBMITTED',
            'entity' => 'exam_sessions',
            'entity_id' => $session->id,
        ]);
    }

    public function test_save_answer_atomic_upsert_within_transaction_never_aborts_transaction(): void
    {
        $token = $this->studentUser->createToken('test')->plainTextToken;

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson("/api/v1/exams/{$this->exam->id}/start-session", ['token' => 'FIS123'])
            ->assertStatus(200);

        // Simulasi autosave berulang di dalam transaksi database aktif
        // Memastikan tidak ada UniqueConstraintViolationException atau kegagalan transaksi
        DB::transaction(function () use ($token) {
            $save1 = $this->withHeaders(['Authorization' => "Bearer {$token}"])
                ->postJson("/api/v1/exams/{$this->exam->id}/save-answer", [
                    'question_id' => $this->question->id,
                    'answer' => ['option_id' => 'opt_1', 'note' => 'initial answer'],
                    'is_flagged' => true,
                ]);
            $save1->assertStatus(200);

            $save2 = $this->withHeaders(['Authorization' => "Bearer {$token}"])
                ->postJson("/api/v1/exams/{$this->exam->id}/save-answer", [
                    'question_id' => $this->question->id,
                    'answer' => ['option_id' => 'opt_2', 'note' => 'revised answer'],
                    'is_flagged' => false,
                ]);
            $save2->assertStatus(200);
        });

        $answers = ExamAnswer::where('question_id', $this->question->id)->get();
        $this->assertCount(1, $answers);
        $this->assertEquals(['option_id' => 'opt_2', 'note' => 'revised answer'], $answers->first()->answer);
        $this->assertFalse((bool) $answers->first()->is_flagged);
    }

    public function test_atomic_upsert_handles_complex_json_structures_and_preserves_original_uuid(): void
    {
        $token = $this->studentUser->createToken('test')->plainTextToken;

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson("/api/v1/exams/{$this->exam->id}/start-session", ['token' => 'FIS123'])
            ->assertStatus(200);

        // Insert awal
        $res1 = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson("/api/v1/exams/{$this->exam->id}/save-answer", [
                'question_id' => $this->question->id,
                'answer' => 'First attempt',
                'is_flagged' => false,
            ]);
        $res1->assertStatus(200);

        $initialRecord = ExamAnswer::where('question_id', $this->question->id)->firstOrFail();
        $initialId = $initialRecord->id;
        $initialCreatedAt = $initialRecord->created_at?->toIso8601String();

        // Update dengan JSON array kompleks
        $res2 = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson("/api/v1/exams/{$this->exam->id}/save-answer", [
                'question_id' => $this->question->id,
                'answer' => ['pairs' => [['left' => '1', 'right' => 'A'], ['left' => '2', 'right' => 'B']]],
                'is_flagged' => true,
            ]);
        $res2->assertStatus(200);

        $updatedRecord = ExamAnswer::where('question_id', $this->question->id)->firstOrFail();

        // Buktikan UUID dan created_at tidak berubah, sedangkan data jawaban dan flag terupdate
        $this->assertEquals($initialId, $updatedRecord->id);
        $this->assertEquals($initialCreatedAt, $updatedRecord->created_at?->toIso8601String());
        $this->assertEquals(['pairs' => [['left' => '1', 'right' => 'A'], ['left' => '2', 'right' => 'B']]], $updatedRecord->answer);
        $this->assertTrue((bool) $updatedRecord->is_flagged);
        $this->assertEquals(1, ExamAnswer::where('question_id', $this->question->id)->count());
    }
}
