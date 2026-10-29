<?php

namespace Tests\Feature\Cbt;

use App\Models\AcademicYear;
use App\Models\ClassStudent;
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
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ExamTimingTest extends TestCase
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
     * GAP-02: Start sesi menghitung ends_at = min(started_at + durasi, batas akhir jadwal ujian).
     */
    public function test_gap_02_start_session_calculates_ends_at_bounded_by_exam_schedule_end(): void
    {
        // Set batas akhir ujian hanya 25 menit dari sekarang (kurang dari durasi 90 menit)
        $this->exam->update([
            'duration_min' => 90,
            'end_at' => Carbon::now()->addMinutes(25),
        ]);

        $this->app['auth']->forgetGuards();

        $response = $this->actingAs($this->studentUser, 'sanctum')
            ->postJson("/api/v1/exams/{$this->exam->id}/start-session", [
                'token' => 'TOKENCBT',
            ]);

        $response->assertStatus(200);

        $session = ExamSession::where('exam_id', $this->exam->id)->where('student_id', $this->student->id)->firstOrFail();
        $this->assertNotNull($session->ends_at);
        $this->assertNotNull($session->started_at);

        // ends_at harus dibatasi oleh exam end_at (kurang lebih 25 menit dari sekarang, bukan 90 menit)
        $diffToEnd = Carbon::now()->diffInSeconds($session->ends_at);
        $this->assertLessThanOrEqual(25 * 60 + 5, $diffToEnd);
        $this->assertGreaterThanOrEqual(25 * 60 - 5, $diffToEnd);

        // Response memuat server_time, ends_at, violation_count
        $response->assertJsonStructure([
            'data' => [
                'session_id',
                'ends_at',
                'server_time',
            ],
        ]);
    }

    /**
     * GAP-02: Endpoint start/resume bersifat idempoten: jika sesi aktif sudah ada, kembalikan sesi yang sama
     * tanpa memulai ulang timer, serta sertakan jawaban yang sudah tersimpan.
     */
    public function test_gap_02_resume_session_is_idempotent_and_does_not_reset_timer(): void
    {
        $this->app['auth']->forgetGuards();

        // 1. Mulai sesi pertama kali
        $res1 = $this->actingAs($this->studentUser, 'sanctum')
            ->postJson("/api/v1/exams/{$this->exam->id}/start-session", [
                'token' => 'TOKENCBT',
            ]);
        $res1->assertStatus(200);

        $session = ExamSession::where('exam_id', $this->exam->id)->where('student_id', $this->student->id)->firstOrFail();
        $initialStartedAt = $session->started_at;
        $initialEndsAt = $session->ends_at;

        // 2. Simpan jawaban soal
        $this->actingAs($this->studentUser, 'sanctum')
            ->postJson("/api/v1/exams/{$this->exam->id}/save-answer", [
                'question_id' => $this->question->id,
                'answer' => ['opt' => 'A'],
                'is_flagged' => true,
            ])
            ->assertStatus(200);

        // Simulasikan waktu berlalu 15 menit
        Carbon::setTestNow(Carbon::now()->addMinutes(15));

        // 3. Resume sesi (panggil start-session lagi)
        $res2 = $this->actingAs($this->studentUser, 'sanctum')
            ->postJson("/api/v1/exams/{$this->exam->id}/start-session", [
                'token' => 'TOKENCBT',
            ]);
        $res2->assertStatus(200);

        $session->refresh();

        // started_at dan ends_at TIDAK boleh berubah / ter-reset!
        $this->assertEquals($initialStartedAt->toIso8601String(), $session->started_at->toIso8601String());
        $this->assertEquals($initialEndsAt->toIso8601String(), $session->ends_at->toIso8601String());

        // Jawaban tersimpan harus disertakan
        $this->assertNotEmpty($res2->json('data.answers'));
        $this->assertEquals(['opt' => 'A'], $res2->json("data.answers.{$this->question->id}.answer"));

        Carbon::setTestNow();
    }

    /**
     * GAP-02: Simpan jawaban ditolak jika sekarang > ends_at (+ toleransi 5 detik)
     * dengan kode error khusus EXAM_TIME_OVER.
     */
    public function test_gap_02_save_answer_rejected_after_ends_at_with_exam_time_over(): void
    {
        $session = ExamSession::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student->id,
            'status' => 'mengerjakan',
            'started_at' => Carbon::now()->subMinutes(90),
            'ends_at' => Carbon::now()->subSeconds(10), // Lewat ends_at lebih dari toleransi 5 detik
            'deadline_at' => Carbon::now()->subSeconds(10),
            'seed' => 'seedtimeout',
        ]);

        $this->app['auth']->forgetGuards();

        $response = $this->actingAs($this->studentUser, 'sanctum')
            ->postJson("/api/v1/exams/{$this->exam->id}/save-answer", [
                'question_id' => $this->question->id,
                'answer' => ['opt' => 'A'],
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'EXAM_TIME_OVER');

        $session->refresh();
        $this->assertEquals('selesai', $session->status);
    }

    /**
     * GAP-02: Submit ujian harus idempoten (submit berulang kali mengembalikan 200).
     */
    public function test_gap_02_finish_session_is_idempotent(): void
    {
        ExamSession::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student->id,
            'status' => 'mengerjakan',
            'started_at' => Carbon::now()->subMinutes(30),
            'ends_at' => Carbon::now()->addMinutes(60),
            'deadline_at' => Carbon::now()->addMinutes(60),
            'seed' => 'seedidempotentfinish',
        ]);

        $this->app['auth']->forgetGuards();

        // Submit 1
        $res1 = $this->actingAs($this->studentUser, 'sanctum')
            ->postJson("/api/v1/exams/{$this->exam->id}/finish-session");
        $res1->assertStatus(200)
            ->assertJsonPath('data.status', 'selesai');

        // Submit 2 (idempoten)
        $res2 = $this->actingAs($this->studentUser, 'sanctum')
            ->postJson("/api/v1/exams/{$this->exam->id}/finish-session");
        $res2->assertStatus(200)
            ->assertJsonPath('data.status', 'selesai');
    }

    /**
     * Skenario 1: Jawaban tepat dalam toleransi grace period (<= 5s) diterima, lewat grace period ditolak.
     */
    public function test_save_answer_at_exact_boundary_and_grace_period(): void
    {
        // Sesi berakhir 3 detik yang lalu (masih dalam grace period 5 detik)
        $session = ExamSession::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student->id,
            'status' => 'mengerjakan',
            'started_at' => Carbon::now()->subMinutes(90),
            'ends_at' => Carbon::now()->subSeconds(3),
            'deadline_at' => Carbon::now()->subSeconds(3),
            'seed' => 'seedboundary',
        ]);

        $this->app['auth']->forgetGuards();

        $res1 = $this->actingAs($this->studentUser, 'sanctum')
            ->postJson("/api/v1/exams/{$this->exam->id}/save-answer", [
                'question_id' => $this->question->id,
                'answer' => ['opt' => 'A'],
            ]);
        $res1->assertStatus(200);

        // Lewat grace period (misal 6 detik setelah ends_at)
        $session->update(['ends_at' => Carbon::now()->subSeconds(7)]);

        $res2 = $this->actingAs($this->studentUser, 'sanctum')
            ->postJson("/api/v1/exams/{$this->exam->id}/save-answer", [
                'question_id' => $this->question->id,
                'answer' => ['opt' => 'B'],
            ]);
        $res2->assertStatus(422)
            ->assertJsonPath('error.code', 'EXAM_TIME_OVER');
    }

    /**
     * Skenario 5: Jawaban lama dan baru mengalami retry dalam urutan berbeda (out-of-order arrival).
     * Jawaban yang lebih baru tidak boleh tertimpa oleh jawaban lama yang tiba belakangan.
     */
    public function test_out_of_order_retries_prevent_older_answer_overwriting_newer_answer(): void
    {
        $session = ExamSession::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student->id,
            'status' => 'mengerjakan',
            'started_at' => Carbon::now()->subMinutes(10),
            'ends_at' => Carbon::now()->addMinutes(80),
            'deadline_at' => Carbon::now()->addMinutes(80),
            'seed' => 'seedoutoforder',
        ]);

        $this->app['auth']->forgetGuards();

        $timeOlder = Carbon::now()->subSeconds(20);
        $timeNewer = Carbon::now()->subSeconds(5);

        // 1. Jawaban baru (Option B, t=newer) tiba LEBIH DULU
        $resNewer = $this->actingAs($this->studentUser, 'sanctum')
            ->postJson("/api/v1/exams/{$this->exam->id}/save-answer", [
                'question_id' => $this->question->id,
                'answer' => ['opt' => 'B'],
                'answered_at' => $timeNewer->toIso8601String(),
            ]);
        $resNewer->assertStatus(200);

        $answerInDb = ExamAnswer::where('session_id', $session->id)->where('question_id', $this->question->id)->firstOrFail();
        $this->assertEquals(['opt' => 'B'], $answerInDb->answer);

        // 2. Jawaban lama (Option A, t=older) tiba BELAKANGAN karena delay network/retry
        $resOlder = $this->actingAs($this->studentUser, 'sanctum')
            ->postJson("/api/v1/exams/{$this->exam->id}/save-answer", [
                'question_id' => $this->question->id,
                'answer' => ['opt' => 'A'],
                'answered_at' => $timeOlder->toIso8601String(),
            ]);
        $resOlder->assertStatus(200)
            ->assertJsonPath('data.is_stale', true);

        // Nilai di DB TETAP Option B (tidak tertimpa Option A)
        $answerInDb->refresh();
        $this->assertEquals(['opt' => 'B'], $answerInDb->answer);
    }

    /**
     * Skenario 6: Server menerima request jawaban yang sama berulang kali (idempoten).
     */
    public function test_repeated_identical_answer_requests_are_idempotent(): void
    {
        $session = ExamSession::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student->id,
            'status' => 'mengerjakan',
            'started_at' => Carbon::now()->subMinutes(10),
            'ends_at' => Carbon::now()->addMinutes(80),
            'deadline_at' => Carbon::now()->addMinutes(80),
            'seed' => 'seedrepeated',
        ]);

        $this->app['auth']->forgetGuards();

        $payload = [
            'question_id' => $this->question->id,
            'answer' => ['opt' => 'A'],
            'idempotency_key' => 'idem-req-12345',
        ];

        // Kirim request pertama
        $res1 = $this->actingAs($this->studentUser, 'sanctum')
            ->postJson("/api/v1/exams/{$this->exam->id}/save-answer", $payload);
        $res1->assertStatus(200);

        // Kirim request kedua persis sama
        $res2 = $this->actingAs($this->studentUser, 'sanctum')
            ->postJson("/api/v1/exams/{$this->exam->id}/save-answer", $payload);
        $res2->assertStatus(200);

        // Jumlah baris jawaban di DB tetap 1 (tidak duplikasi)
        $this->assertEquals(1, ExamAnswer::where('session_id', $session->id)->where('question_id', $this->question->id)->count());
    }

    /**
     * Skenario 9: Dua proses auto-submit memproses sesi yang sama (concurrency & idempotency).
     */
    public function test_two_concurrent_auto_submit_runs_do_not_duplicate_processing(): void
    {
        $expiredSession = ExamSession::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student->id,
            'status' => 'mengerjakan',
            'started_at' => Carbon::now()->subMinutes(120),
            'ends_at' => Carbon::now()->subMinutes(10),
            'deadline_at' => Carbon::now()->subMinutes(10),
            'seed' => 'seedconcurrentexpire',
        ]);

        // Jalankan worker 1
        $this->artisan('cbt:close-expired-sessions')
            ->expectsOutputToContain('Berhasil mengumpulkan otomatis 1 sesi')
            ->assertSuccessful();

        $expiredSession->refresh();
        $this->assertEquals('selesai', $expiredSession->status);

        // Jalankan worker 2 tepat setelahnya (atau saat sesi sudah selesai)
        $this->artisan('cbt:close-expired-sessions')
            ->expectsOutputToContain('Berhasil mengumpulkan otomatis 0 sesi')
            ->assertSuccessful();
    }

    /**
     * Skenario 10: Sesi yang belum kedaluwarsa tidak ditutup oleh auto-submit command.
     */
    public function test_unexpired_sessions_are_not_closed_by_auto_submit(): void
    {
        $activeSession = ExamSession::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student->id,
            'status' => 'mengerjakan',
            'started_at' => Carbon::now()->subMinutes(10),
            'ends_at' => Carbon::now()->addMinutes(80),
            'deadline_at' => Carbon::now()->addMinutes(80),
            'seed' => 'seedactiveunexpired',
        ]);

        $this->artisan('cbt:close-expired-sessions')
            ->assertSuccessful();

        $activeSession->refresh();
        $this->assertEquals('mengerjakan', $activeSession->status);
        $this->assertNull($activeSession->submitted_at);
    }

    /**
     * Skenario 11: Sesi lama tanpa ends_at (hanya deadline_at) tetap kompatibel.
     */
    public function test_legacy_session_without_ends_at_is_fully_compatible(): void
    {
        $legacyExpired = ExamSession::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student->id,
            'status' => 'mengerjakan',
            'started_at' => Carbon::now()->subMinutes(120),
            'ends_at' => null, // Sesi warisan
            'deadline_at' => Carbon::now()->subMinutes(10),
            'seed' => 'seedlegacy',
        ]);

        $this->app['auth']->forgetGuards();

        // Mencoba simpan jawaban pada sesi lama yang lewat deadline mengembalikan BUSINESS_RULE_VIOLATION
        $res = $this->actingAs($this->studentUser, 'sanctum')
            ->postJson("/api/v1/exams/{$this->exam->id}/save-answer", [
                'question_id' => $this->question->id,
                'answer' => ['opt' => 'A'],
            ]);

        $res->assertStatus(422)
            ->assertJsonPath('error.code', 'BUSINESS_RULE_VIOLATION');

        // Auto submit command juga mampu menutup sesi lama berdasarkan deadline_at
        $legacyExpired->update(['status' => 'mengerjakan']);

        $this->artisan('cbt:close-expired-sessions')
            ->assertSuccessful();

        $legacyExpired->refresh();
        $this->assertEquals('selesai', $legacyExpired->status);
    }

    /**
     * Skenario 12 & 13: Browser ditutup siswa tanpa tombol submit, lalu server menutup sesi dan menghitung skor otomatis.
     */
    public function test_browser_closed_without_submit_server_auto_closes_and_grades_objective_answers(): void
    {
        $session = ExamSession::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student->id,
            'status' => 'mengerjakan',
            'started_at' => Carbon::now()->subMinutes(120),
            'ends_at' => Carbon::now()->subMinutes(10),
            'deadline_at' => Carbon::now()->subMinutes(10),
            'seed' => 'seedabandoned',
        ]);

        // Siswa sempat menjawab Option A (kunci jawaban yang benar) sebelum menutup browser
        ExamAnswer::create([
            'session_id' => $session->id,
            'question_id' => $this->question->id,
            'answer' => ['opt' => 'A'],
            'is_flagged' => false,
            'answered_at' => Carbon::now()->subMinutes(50),
        ]);

        // Jalankan perintah auto-close scheduler
        $this->artisan('cbt:close-expired-sessions')
            ->assertSuccessful();

        $session->refresh();
        $this->assertEquals('selesai', $session->status);
        $this->assertNotNull($session->submitted_at);

        // Skor objektif dihitung otomatis
        $this->assertEquals(100.0, (float) $session->score);
    }

    /**
     * Release Gate Section B: Timestamp bounding (clamped to started_at in past and now in future).
     */
    public function test_client_future_timestamp_clamped_and_past_timestamp_bounded_to_session_start(): void
    {
        $session = ExamSession::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student->id,
            'status' => 'mengerjakan',
            'started_at' => Carbon::now()->subMinutes(15),
            'ends_at' => Carbon::now()->addMinutes(75),
            'deadline_at' => Carbon::now()->addMinutes(75),
            'seed' => 'seedclamping',
        ]);

        $this->app['auth']->forgetGuards();

        // 1. Client mengirim timestamp jauh di masa depan (2 jam ke depan)
        $futureTs = Carbon::now()->addHours(2)->toIso8601String();
        $resFuture = $this->actingAs($this->studentUser, 'sanctum')
            ->postJson("/api/v1/exams/{$this->exam->id}/save-answer", [
                'question_id' => $this->question->id,
                'answer' => ['opt' => 'A'],
                'answered_at' => $futureTs,
            ]);

        $resFuture->assertStatus(200)
            ->assertJsonPath('data.is_saved', true);

        $savedAns = ExamAnswer::where('session_id', $session->id)
            ->where('question_id', $this->question->id)
            ->first();
        $this->assertNotNull($savedAns);
        // Timestamp tidak boleh melebihi jam server
        $this->assertTrue($savedAns->answered_at->lessThanOrEqualTo(Carbon::now()->addSeconds(2)));

        // 2. Client mengirim timestamp purba (sebelum ujian dimulai)
        $ancientTs = Carbon::now()->subYears(5)->toIso8601String();
        $resPast = $this->actingAs($this->studentUser, 'sanctum')
            ->postJson("/api/v1/exams/{$this->exam->id}/save-answer", [
                'question_id' => $this->question->id,
                'answer' => ['opt' => 'B'],
                'answered_at' => $ancientTs,
            ]);

        // Karena timestamp purba lebih lama dari yang sudah tersimpan, ditolak sebagai stale
        $resPast->assertStatus(200)
            ->assertJsonPath('data.is_stale', true);
    }

    /**
     * Release Gate Section B: Idempotensi jawaban identik dan perlindungan paket retry terlambat.
     */
    public function test_idempotent_identical_answer_does_not_mutate_and_stale_retry_prevented(): void
    {
        $session = ExamSession::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student->id,
            'status' => 'mengerjakan',
            'started_at' => Carbon::now()->subMinutes(20),
            'ends_at' => Carbon::now()->addMinutes(70),
            'deadline_at' => Carbon::now()->addMinutes(70),
            'seed' => 'seedidempotency',
        ]);

        $this->app['auth']->forgetGuards();

        $t1 = Carbon::now()->subMinutes(10);
        $t2 = Carbon::now()->subMinutes(5);

        // Jawaban baru dikirim pada T2
        $res1 = $this->actingAs($this->studentUser, 'sanctum')
            ->postJson("/api/v1/exams/{$this->exam->id}/save-answer", [
                'question_id' => $this->question->id,
                'answer' => ['opt' => 'B'],
                'answered_at' => $t2->toIso8601String(),
                'idempotency_key' => 'key_first_send',
            ]);

        $res1->assertStatus(200)
            ->assertJsonPath('data.is_saved', true);

        // Retry identik dengan jawaban dan flag sama -> idempotent match (is_stale false)
        $resRetry = $this->actingAs($this->studentUser, 'sanctum')
            ->postJson("/api/v1/exams/{$this->exam->id}/save-answer", [
                'question_id' => $this->question->id,
                'answer' => ['opt' => 'B'],
                'answered_at' => $t2->toIso8601String(),
                'idempotency_key' => 'key_first_send',
            ]);

        $resRetry->assertStatus(200)
            ->assertJsonPath('data.is_saved', true)
            ->assertJsonPath('data.is_stale', false);

        // Paket lama yang terlambat di jaringan (dijawab pada T1) tiba kemudian -> harus ditolak sebagai stale
        $resStale = $this->actingAs($this->studentUser, 'sanctum')
            ->postJson("/api/v1/exams/{$this->exam->id}/save-answer", [
                'question_id' => $this->question->id,
                'answer' => ['opt' => 'A'],
                'answered_at' => $t1->toIso8601String(),
                'idempotency_key' => 'key_old_retry',
            ]);

        $resStale->assertStatus(200)
            ->assertJsonPath('data.is_saved', true)
            ->assertJsonPath('data.is_stale', true);

        // Jawaban di DB tetap bernilai B, tidak tertimpa oleh jawaban A yang basi
        $dbAns = ExamAnswer::where('session_id', $session->id)->where('question_id', $this->question->id)->first();
        $actualAnswer = is_array($dbAns->answer) ? $dbAns->answer : json_decode($dbAns->answer, true);
        $this->assertEquals(['opt' => 'B'], $actualAnswer);
    }

    /**
     * Release Gate Section C: Ujian campuran (PG + Uraian). Auto-submit TIDAK menandai esai selesai dinilai.
     */
    public function test_auto_submit_mixed_exam_grades_objective_leaves_essay_score_null(): void
    {
        // Tambahkan butir soal tipe 'uraian' (esai)
        $essayQuestion = Question::create([
            'bank_id' => $this->question->bank_id,
            'question_type' => 'uraian',
            'body' => ['content' => 'Jelaskan tahapan penyelesaian sistem persamaan linear!'],
            'weight' => 10.0,
            'version' => 1,
            'status' => 'aktif',
        ]);

        ExamQuestion::create([
            'exam_id' => $this->exam->id,
            'question_id' => $essayQuestion->id,
            'sort_order' => 2,
            'package_code' => 'A',
            'weight_override' => 10.0,
        ]);

        $session = ExamSession::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student->id,
            'status' => 'mengerjakan',
            'started_at' => Carbon::now()->subMinutes(120),
            'ends_at' => Carbon::now()->subMinutes(10),
            'deadline_at' => Carbon::now()->subMinutes(10),
            'seed' => 'seedmixed',
        ]);

        // Siswa menjawab soal 1 (PG benar, bobot 10.0) dan soal 2 (Uraian esai, bobot 10.0)
        ExamAnswer::create([
            'session_id' => $session->id,
            'question_id' => $this->question->id,
            'answer' => ['opt' => 'A'],
            'answered_at' => Carbon::now()->subMinutes(50),
        ]);

        $essayAnswer = ExamAnswer::create([
            'session_id' => $session->id,
            'question_id' => $essayQuestion->id,
            'answer' => 'Langkah pertama adalah melakukan eliminasi variabel x...',
            'answered_at' => Carbon::now()->subMinutes(40),
            'score' => null,
        ]);

        // Jalankan auto-submit command
        $this->artisan('cbt:close-expired-sessions')->assertSuccessful();

        $session->refresh();
        $essayAnswer->refresh();

        $this->assertEquals('selesai', $session->status);
        // BUTIR ESAI WAJIB TETAP NULL! Tidak boleh dianggap otomatis dinilai
        $this->assertNull($essayAnswer->score, 'Soal esai/uraian tidak boleh diberi nilai otomatis oleh auto-submit');

        // Nilai sesi merefleksikan hanya bagian objektif yang telah dikoreksi (10 / (10 + 10) * 100 = 50.0)
        $this->assertEquals(50.0, (float) $session->score);
    }
}
