<?php

namespace Tests\Feature\Cbt;

use App\Models\Exam;
use App\Models\ExamAnswer;
use App\Models\ExamParticipant;
use App\Models\ExamRoom;
use App\Models\ExamSession;
use App\Models\Question;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use App\Services\Cbt\ExamGradingService;
use Carbon\Carbon;
use Database\Seeders\CbtDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CbtStudentE2EFlowTest extends TestCase
{
    use RefreshDatabase;

    protected User $studentUser;

    protected Student $student;

    protected User $proctorUser;

    protected Exam $exam;

    protected ExamRoom $examRoom;

    protected function setUp(): void
    {
        parent::setUp();

        // Jalankan CbtDemoSeeder pada testing database
        (new CbtDemoSeeder)->run();

        $this->studentUser = User::where('username', 'siswa.lab.001')->firstOrFail();
        $this->student = Student::where('user_id', $this->studentUser->id)->firstOrFail();
        $this->exam = Exam::with(['questions.question.options'])->firstOrFail();
        $this->examRoom = ExamRoom::where('exam_id', $this->exam->id)->firstOrFail();
        $this->proctorUser = User::where('id', $this->examRoom->proctor_id)->firstOrFail();
    }

    /**
     * Alur Penuh Siswa: GET /me/exams -> start-session -> session-questions -> save-answer -> log-violation -> finish-session.
     */
    public function test_full_cbt_student_workflow_end_to_end(): void
    {
        // 1. GET /api/v1/me/exams
        $meExamsRes = $this->actingAs($this->studentUser)
            ->getJson('/api/v1/me/exams');

        $meExamsRes->assertOk()
            ->assertJsonPath('data.0.id', $this->exam->id)
            ->assertJsonPath('data.0.title', $this->exam->title)
            ->assertJsonPath('data.0.durationMinutes', 30);

        // 2. POST /api/v1/exams/{id}/start-session dengan token valid
        $startRes = $this->actingAs($this->studentUser)
            ->postJson("/api/v1/exams/{$this->exam->id}/start-session", [
                'token' => $this->examRoom->token,
            ]);

        $startRes->assertOk()
            ->assertJsonPath('data.status', 'mengerjakan');

        $sessionId = $startRes->json('data.session_id');
        $this->assertNotNull($sessionId);

        // 3. GET /api/v1/exams/{id}/session-questions
        $questionsRes = $this->actingAs($this->studentUser)
            ->getJson("/api/v1/exams/{$this->exam->id}/session-questions");

        $questionsRes->assertOk()
            ->assertJsonPath('data.session_id', $sessionId)
            ->assertJsonCount(20, 'data.questions');

        $firstQuestion = $questionsRes->json('data.questions.0');
        $firstQuestionId = $firstQuestion['id'];

        // 4. POST /api/v1/exams/{id}/save-answer berulang (autosave)
        $saveRes1 = $this->actingAs($this->studentUser)
            ->postJson("/api/v1/exams/{$this->exam->id}/save-answer", [
                'question_id' => $firstQuestionId,
                'answer' => ['selected_option_id' => 'dummy-choice'],
                'is_flagged' => true,
            ]);

        $saveRes1->assertOk()
            ->assertJsonPath('data.is_saved', true)
            ->assertJsonPath('data.is_flagged', true);

        // 5. POST /api/v1/exams/{id}/log-violation (anti-cheat detection)
        $violationRes = $this->actingAs($this->studentUser)
            ->postJson("/api/v1/exams/{$this->exam->id}/log-violation", [
                'violation_type' => 'tab_switch',
                'details' => ['reason' => 'User switched tabs during exam'],
            ]);

        $violationRes->assertOk()
            ->assertJsonPath('data.total_violations', 1)
            ->assertJsonPath('data.consequence', 'warning');

        // 6. POST /api/v1/exams/{id}/finish-session
        $finishRes = $this->actingAs($this->studentUser)
            ->postJson("/api/v1/exams/{$this->exam->id}/finish-session");

        $finishRes->assertOk()
            ->assertJsonPath('data.status', 'selesai');

        $this->assertDatabaseHas('exam_sessions', [
            'id' => $sessionId,
            'status' => 'selesai',
        ]);
    }

    /**
     * Requirement (a): Payload butir soal TIDAK PERNAH mengandung is_correct, match_key, atau kunci jawaban.
     */
    public function test_session_questions_never_expose_correct_answers_or_keys(): void
    {
        $this->actingAs($this->studentUser)
            ->postJson("/api/v1/exams/{$this->exam->id}/start-session", [
                'token' => $this->examRoom->token,
            ])
            ->assertOk();

        $response = $this->actingAs($this->studentUser)
            ->getJson("/api/v1/exams/{$this->exam->id}/session-questions")
            ->assertOk();

        $questions = $response->json('data.questions');
        $this->assertNotEmpty($questions);

        foreach ($questions as $q) {
            // Cek pada level butir soal
            $this->assertArrayNotHasKey('is_correct', $q);
            $this->assertArrayNotHasKey('match_key', $q);
            $this->assertArrayNotHasKey('correct_answer', $q);

            if (is_array($q['body'] ?? null)) {
                $this->assertArrayNotHasKey('correct_answer', $q['body']);
                $this->assertArrayNotHasKey('is_correct', $q['body']);
            }

            // Cek pada level opsi pilihan
            foreach ($q['options'] ?? [] as $opt) {
                $this->assertArrayNotHasKey('is_correct', $opt, "Option {$opt['id']} exposes 'is_correct'!");
                $this->assertArrayNotHasKey('match_key', $opt, "Option {$opt['id']} exposes 'match_key'!");
            }
        }
    }

    /**
     * Requirement (b): Sisa waktu dihitung server; kirim waktu klien palsu tidak berpengaruh.
     */
    public function test_server_authoritative_remaining_time_and_tampered_client_timestamp(): void
    {
        Carbon::setTestNow('2026-10-22 08:00:00');

        $startRes = $this->actingAs($this->studentUser)
            ->postJson("/api/v1/exams/{$this->exam->id}/start-session", [
                'token' => $this->examRoom->token,
            ])
            ->assertOk();

        // Durasi 30 menit = 1800 detik
        $questionsRes = $this->actingAs($this->studentUser)
            ->getJson("/api/v1/exams/{$this->exam->id}/session-questions")
            ->assertOk();

        $remaining = $questionsRes->json('data.remaining_seconds');
        $this->assertEqualsWithDelta(1800, $remaining, 5);

        // Majukan waktu server 10 menit
        Carbon::setTestNow('2026-10-22 08:10:00');

        $questionsRes2 = $this->actingAs($this->studentUser)
            ->getJson("/api/v1/exams/{$this->exam->id}/session-questions")
            ->assertOk();

        $this->assertEqualsWithDelta(1200, $questionsRes2->json('data.remaining_seconds'), 5);

        // Siswa mencoba mengirim answered_at 5 jam di masa depan
        $q = Question::where('bank_id', $this->exam->questions->first()->question->bank_id)->first();
        $tamperedAnswerTime = Carbon::now()->addHours(5)->toIso8601String();

        $saveRes = $this->actingAs($this->studentUser)
            ->postJson("/api/v1/exams/{$this->exam->id}/save-answer", [
                'question_id' => $q->id,
                'answer' => ['opt' => 'A'],
                'answered_at' => $tamperedAnswerTime,
            ])
            ->assertOk();

        // Server harus membatasi answered_at agar tidak melebihi jam server aktual (2026-10-22 08:10:00)
        $savedDb = ExamAnswer::where('question_id', $q->id)->first();
        $this->assertNotNull($savedDb);
        $this->assertLessThanOrEqual('2026-10-22 08:10:00', $savedDb->answered_at->toDateTimeString());

        Carbon::setTestNow();
    }

    /**
     * Requirement (c): save-answer dengan payload identik 2x tidak membuat data ganda (idempoten).
     */
    public function test_save_answer_idempotency_prevents_duplicate_records(): void
    {
        $this->actingAs($this->studentUser)
            ->postJson("/api/v1/exams/{$this->exam->id}/start-session", [
                'token' => $this->examRoom->token,
            ])
            ->assertOk();

        $q = $this->exam->questions->first()->question;
        $payload = [
            'question_id' => $q->id,
            'answer' => ['selected_option_id' => 'opt-123'],
            'is_flagged' => false,
        ];

        // Request 1
        $res1 = $this->actingAs($this->studentUser)
            ->postJson("/api/v1/exams/{$this->exam->id}/save-answer", $payload)
            ->assertOk();

        // Request 2 (Identik)
        $res2 = $this->actingAs($this->studentUser)
            ->postJson("/api/v1/exams/{$this->exam->id}/save-answer", $payload)
            ->assertOk();

        $this->assertTrue($res2->json('data.is_saved'));
        $this->assertFalse($res2->json('data.is_stale'));

        // Pastikan hanya 1 baris jawaban di tabel exam_answers
        $session = ExamSession::where('exam_id', $this->exam->id)
            ->where('student_id', $this->student->id)
            ->firstOrFail();

        $answerCount = ExamAnswer::where('session_id', $session->id)
            ->where('question_id', $q->id)
            ->count();

        $this->assertSame(1, $answerCount);
    }

    /**
     * Requirement (d): finish-session 2x tidak menghitung nilai ganda atau melempar error.
     */
    public function test_finish_session_twice_does_not_duplicate_submission_or_score(): void
    {
        $this->actingAs($this->studentUser)
            ->postJson("/api/v1/exams/{$this->exam->id}/start-session", [
                'token' => $this->examRoom->token,
            ])
            ->assertOk();

        // Submit 1
        $res1 = $this->actingAs($this->studentUser)
            ->postJson("/api/v1/exams/{$this->exam->id}/finish-session")
            ->assertOk();

        $this->assertSame('selesai', $res1->json('data.status'));

        // Submit 2
        $res2 = $this->actingAs($this->studentUser)
            ->postJson("/api/v1/exams/{$this->exam->id}/finish-session")
            ->assertOk();

        $this->assertSame('selesai', $res2->json('data.status'));
        $this->assertStringContainsString('sudah diselesaikan sebelumnya', $res2->json('data.message'));

        // Verifikasi sesi tetap unik
        $sessionsCount = ExamSession::where('exam_id', $this->exam->id)
            ->where('student_id', $this->student->id)
            ->count();
        $this->assertSame(1, $sessionsCount);
    }

    /**
     * Requirement (e): Submit/aksi setelah deadline ditolak atau ditandai waktu habis (timeout lock).
     */
    public function test_action_after_deadline_is_rejected_with_timeout_lock(): void
    {
        Carbon::setTestNow('2026-10-22 08:00:00');

        $this->actingAs($this->studentUser)
            ->postJson("/api/v1/exams/{$this->exam->id}/start-session", [
                'token' => $this->examRoom->token,
            ])
            ->assertOk();

        // Majukan waktu melewati durasi ujian (30 menit + batas toleransi 5 detik)
        Carbon::setTestNow('2026-10-22 08:31:00');

        $q = $this->exam->questions->first()->question;

        // Coba save-answer setelah deadline
        $saveRes = $this->actingAs($this->studentUser)
            ->postJson("/api/v1/exams/{$this->exam->id}/save-answer", [
                'question_id' => $q->id,
                'answer' => ['opt' => 'A'],
            ]);

        $saveRes->assertStatus(422)
            ->assertJsonPath('error.code', 'EXAM_TIME_OVER');

        // Pastikan sesi statusnya auto-lock menjadi selesai di DB
        $session = ExamSession::where('exam_id', $this->exam->id)
            ->where('student_id', $this->student->id)
            ->firstOrFail();

        $this->assertSame('selesai', $session->status);

        Carbon::setTestNow();
    }

    /**
     * Requirement (f): Siswa B tidak bisa mengakses sesi siswa A (anti-IDOR) dan siswa luar kelas tidak bisa ikut.
     */
    public function test_student_b_cannot_access_student_a_session_and_unauthorized_student_cannot_join(): void
    {
        // 1. Siswa A memulai sesi
        $this->actingAs($this->studentUser)
            ->postJson("/api/v1/exams/{$this->exam->id}/start-session", [
                'token' => $this->examRoom->token,
            ])
            ->assertOk();

        $sessionA = ExamSession::where('exam_id', $this->exam->id)
            ->where('student_id', $this->student->id)
            ->firstOrFail();

        // 2. Buat Siswa B (terdaftar di ujian tapi berbeda sesi)
        $roleStudent = Role::where('code', 'student')->firstOrFail();
        $userB = User::create([
            'username' => 'siswa.b.test',
            'email' => 'siswa.b@test.sch.id',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $userB->roles()->attach($roleStudent->id);

        $studentB = Student::create([
            'user_id' => $userB->id,
            'nis' => '26998',
            'nisn' => '0099999998',
            'nik_enc' => '3578010101099998',
            'full_name' => 'Siswa B Test',
            'gender' => 'P',
            'birth_place' => 'Malang',
            'birth_date' => '2012-01-01',
            'status' => 'aktif',
            'entry_date' => '2026-07-01',
        ]);

        ExamParticipant::create([
            'exam_id' => $this->exam->id,
            'student_id' => $studentB->id,
            'exam_room_id' => $this->examRoom->id,
        ]);

        // Siswa B meminta session-questions sebelum memulai sesi sendiri -> harus 404 (sesi belum ada untuk B)
        $this->actingAs($userB)
            ->getJson("/api/v1/exams/{$this->exam->id}/session-questions")
            ->assertStatus(404);

        // Siswa B memulai sesi sendiri
        $startB = $this->actingAs($userB)
            ->postJson("/api/v1/exams/{$this->exam->id}/start-session", [
                'token' => $this->examRoom->token,
            ])
            ->assertOk();

        $this->assertNotEquals($sessionA->id, $startB->json('data.session_id'));

        // 3. Siswa C (TIDAK terdaftar pada ujian ini / beda kelas)
        $userC = User::create([
            'username' => 'siswa.c.luar',
            'email' => 'siswa.c@test.sch.id',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $userC->roles()->attach($roleStudent->id);

        Student::create([
            'user_id' => $userC->id,
            'nis' => '26999',
            'nisn' => '0099999999',
            'nik_enc' => '3578010101099999',
            'full_name' => 'Siswa C Luar Rombel',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2012-01-01',
            'status' => 'aktif',
            'entry_date' => '2026-07-01',
        ]);

        // Siswa C mencoba start-session -> 403 Forbidden
        $this->actingAs($userC)
            ->postJson("/api/v1/exams/{$this->exam->id}/start-session", [
                'token' => $this->examRoom->token,
            ])
            ->assertStatus(403);
    }

    /**
     * Requirement (g): Pelanggaran tercatat di exam_violations dan terlihat di monitor pengawas.
     */
    public function test_violations_logged_in_db_and_visible_in_proctor_monitor(): void
    {
        $this->actingAs($this->studentUser)
            ->postJson("/api/v1/exams/{$this->exam->id}/start-session", [
                'token' => $this->examRoom->token,
            ])
            ->assertOk();

        $session = ExamSession::where('exam_id', $this->exam->id)
            ->where('student_id', $this->student->id)
            ->firstOrFail();

        // Log 2 pelanggaran
        $this->actingAs($this->studentUser)
            ->postJson("/api/v1/exams/{$this->exam->id}/log-violation", [
                'violation_type' => 'tab_switch',
                'details' => ['url' => 'https://google.com'],
            ])
            ->assertOk();

        $this->actingAs($this->studentUser)
            ->postJson("/api/v1/exams/{$this->exam->id}/log-violation", [
                'violation_type' => 'window_blur',
                'details' => ['blur_time' => 3],
            ])
            ->assertOk();

        // Cek tabel exam_violations
        $this->assertDatabaseCount('exam_violations', 2);
        $this->assertDatabaseHas('exam_violations', [
            'session_id' => $session->id,
            'violation_type' => 'tab_switch',
        ]);

        // Pengawas melihat monitor
        $monitorRes = $this->actingAs($this->proctorUser)
            ->getJson("/api/v1/exams/{$this->exam->id}/monitor");

        $monitorRes->assertOk();
        $participants = $monitorRes->json('data.participants');
        $proctorSessionView = collect($participants)->firstWhere('student_id', $this->student->id);

        $this->assertNotNull($proctorSessionView);
        $this->assertEquals(2, $proctorSessionView['violation_count']);
    }

    /**
     * Requirement (h - 1): Nilai akhir 100% saat semua butir dijawab BENAR.
     */
    public function test_scoring_accuracy_all_correct_answers(): void
    {
        $this->actingAs($this->studentUser)
            ->postJson("/api/v1/exams/{$this->exam->id}/start-session", [
                'token' => $this->examRoom->token,
            ])
            ->assertOk();

        $session = ExamSession::where('exam_id', $this->exam->id)
            ->where('student_id', $this->student->id)
            ->firstOrFail();

        // Jawab seluruh butir objektif (PG, PG Kompleks, BS, Menjodohkan, Isian) dengan benar
        $this->submitAnswersForSession($session, 'all_correct');

        $this->actingAs($this->studentUser)
            ->postJson("/api/v1/exams/{$this->exam->id}/finish-session")
            ->assertOk();

        // Koreksi otomatis
        app(ExamGradingService::class)->gradeSessionObjectiveAnswers($session);
        $session->refresh();

        // 18 butir objektif @ 5.0 = 90.0 earned dari 100.0 total max = 90% (atau jika 2 uraian diberi nilai manual 10 = 100%)
        // Total earned objektif = 90.0 / 100.0 = 90.00
        $this->assertEquals(90.0, (float) $session->score);
    }

    /**
     * Requirement (h - 2): Nilai akhir 0 saat semua butir dijawab SALAH.
     */
    public function test_scoring_accuracy_all_wrong_answers(): void
    {
        $this->actingAs($this->studentUser)
            ->postJson("/api/v1/exams/{$this->exam->id}/start-session", [
                'token' => $this->examRoom->token,
            ])
            ->assertOk();

        $session = ExamSession::where('exam_id', $this->exam->id)
            ->where('student_id', $this->student->id)
            ->firstOrFail();

        // Jawab seluruh butir objektif dengan salah
        $this->submitAnswersForSession($session, 'all_wrong');

        $this->actingAs($this->studentUser)
            ->postJson("/api/v1/exams/{$this->exam->id}/finish-session")
            ->assertOk();

        app(ExamGradingService::class)->gradeSessionObjectiveAnswers($session);
        $session->refresh();

        $this->assertEquals(0.0, (float) $session->score);
    }

    /**
     * Requirement (h - 3): Nilai akhir campuran (sebagian benar, sebagian salah) sesuai formula.
     */
    public function test_scoring_accuracy_mixed_answers(): void
    {
        $this->actingAs($this->studentUser)
            ->postJson("/api/v1/exams/{$this->exam->id}/start-session", [
                'token' => $this->examRoom->token,
            ])
            ->assertOk();

        $session = ExamSession::where('exam_id', $this->exam->id)
            ->where('student_id', $this->student->id)
            ->firstOrFail();

        // Setengah benar: 3 PG benar (15.0), 2 PG kompleks benar (10.0), 2 BS benar (10.0), 1 isian benar (5.0) = 40.0 earned
        $this->submitAnswersForSession($session, 'mixed');

        $this->actingAs($this->studentUser)
            ->postJson("/api/v1/exams/{$this->exam->id}/finish-session")
            ->assertOk();

        app(ExamGradingService::class)->gradeSessionObjectiveAnswers($session);
        $session->refresh();

        $this->assertEquals(40.0, (float) $session->score);
    }

    /**
     * Helper untuk submit jawaban sesuai mode: 'all_correct', 'all_wrong', 'mixed'.
     */
    protected function submitAnswersForSession(ExamSession $session, string $mode): void
    {
        $questions = Question::with('options')
            ->where('bank_id', $this->exam->questions->first()->question->bank_id)
            ->orderBy('cp_code')
            ->get();

        $correctCount = 0;
        foreach ($questions as $q) {
            $answerPayload = null;

            if ($mode === 'all_correct') {
                $answerPayload = $this->buildCorrectAnswer($q);
            } elseif ($mode === 'all_wrong') {
                $answerPayload = $this->buildWrongAnswer($q);
            } elseif ($mode === 'mixed') {
                // Berikan 8 butir benar (8 * 5 = 40.0), sisanya salah
                if ($correctCount < 8 && in_array($q->question_type, ['pg', 'pg_kompleks', 'bs', 'isian'])) {
                    $answerPayload = $this->buildCorrectAnswer($q);
                    $correctCount++;
                } else {
                    $answerPayload = $this->buildWrongAnswer($q);
                }
            }

            if ($answerPayload !== null) {
                $this->actingAs($this->studentUser)
                    ->postJson("/api/v1/exams/{$this->exam->id}/save-answer", [
                        'question_id' => $q->id,
                        'answer' => $answerPayload,
                    ])
                    ->assertOk();
            }
        }
    }

    protected function buildCorrectAnswer(Question $q): ?array
    {
        switch ($q->question_type) {
            case 'pg':
            case 'bs':
                $correctOpt = $q->options->firstWhere('is_correct', true);

                return ['selected_option_id' => $correctOpt?->id];

            case 'pg_kompleks':
                $correctOptIds = $q->options->where('is_correct', true)->pluck('id')->all();

                return ['selected_option_ids' => $correctOptIds];

            case 'menjodohkan':
                $pairs = [];
                foreach ($q->options as $opt) {
                    if ($opt->match_key !== null) {
                        $pairs[$opt->id] = $opt->match_key;
                    }
                }

                return ['pairs' => $pairs];

            case 'isian':
                $correctOpt = $q->options->firstWhere('is_correct', true);
                $text = $correctOpt?->body['text'] ?? $q->body['correct_answer'] ?? '';

                return ['text' => $text];

            case 'uraian':
                return ['text' => 'Jawaban uraian lengkap oleh siswa'];

            default:
                return null;
        }
    }

    protected function buildWrongAnswer(Question $q): ?array
    {
        switch ($q->question_type) {
            case 'pg':
            case 'bs':
                $wrongOpt = $q->options->firstWhere('is_correct', false);

                return ['selected_option_id' => $wrongOpt?->id ?? 'wrong-id'];

            case 'pg_kompleks':
                $wrongOptIds = $q->options->where('is_correct', false)->pluck('id')->all();

                return ['selected_option_ids' => $wrongOptIds];

            case 'menjodohkan':
                $pairs = [];
                foreach ($q->options as $opt) {
                    $pairs[$opt->id] = 'WRONG_PAIR_KEY';
                }

                return ['pairs' => $pairs];

            case 'isian':
                return ['text' => 'JAWABAN_SALAH'];

            case 'uraian':
                return ['text' => 'Tidak tahu'];

            default:
                return null;
        }
    }
}
