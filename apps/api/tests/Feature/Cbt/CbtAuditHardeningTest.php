<?php

namespace Tests\Feature\Cbt;

use App\Models\Exam;
use App\Models\ExamAnswer;
use App\Models\ExamParticipant;
use App\Models\ExamQuestion;
use App\Models\ExamRoom;
use App\Models\ExamSession;
use App\Models\Permission;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\QuestionOption;
use App\Models\Role;
use App\Models\Room;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CbtAuditHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $teacherA;

    protected User $teacherB;

    protected User $studentUser1;

    protected User $studentUser2;

    protected Student $student1;

    protected Student $student2;

    protected Subject $subject;

    protected Room $room1;

    protected Room $room2;

    protected QuestionBank $bankA;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Admin', 'is_system' => true]);
        $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);
        $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);

        $permManage = Permission::firstOrCreate(['code' => 'cbt.exam.manage'], ['description' => 'Kelola Ujian CBT']);
        $permBank = Permission::firstOrCreate(['code' => 'cbt.bank.write'], ['description' => 'Kelola Bank Soal']);
        $roleAdmin->permissions()->syncWithoutDetaching([$permManage->id, $permBank->id]);
        $roleTeacher->permissions()->syncWithoutDetaching([$permBank->id]);

        $this->adminUser = User::create([
            'username' => 'admin_sec',
            'email' => 'admin_sec@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($roleAdmin->id);

        $this->teacherA = User::create([
            'username' => 'guru_a',
            'email' => 'guru_a@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->teacherA->roles()->attach($roleTeacher->id);
        Teacher::create([
            'user_id' => $this->teacherA->id,
            'nip' => '198501012010011011',
            'full_name' => 'Guru A Pengawas',
            'gender' => 'L',
        ]);

        $this->teacherB = User::create([
            'username' => 'guru_b',
            'email' => 'guru_b@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->teacherB->roles()->attach($roleTeacher->id);
        Teacher::create([
            'user_id' => $this->teacherB->id,
            'nip' => '198501012010011012',
            'full_name' => 'Guru B Lain',
            'gender' => 'P',
        ]);

        $this->studentUser1 = User::create([
            'username' => '20268001',
            'email' => 'siswa1_sec@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->studentUser1->roles()->attach($roleStudent->id);

        $this->studentUser2 = User::create([
            'username' => '20268002',
            'email' => 'siswa2_sec@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->studentUser2->roles()->attach($roleStudent->id);

        $this->student1 = Student::create([
            'user_id' => $this->studentUser1->id,
            'full_name' => 'Siswa Fiktif Satu',
            'nis' => '20268001',
            'nisn' => '0088008801',
            'nik_enc' => '3500000000000001',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2011-01-01',
            'entry_date' => '2026-07-01',
            'status' => 'aktif',
        ]);

        $this->student2 = Student::create([
            'user_id' => $this->studentUser2->id,
            'full_name' => 'Siswa Fiktif Dua <script>alert(1)</script>',
            'nis' => '20268002',
            'nisn' => '0088008802',
            'nik_enc' => '3500000000000002',
            'gender' => 'P',
            'birth_place' => 'Malang',
            'birth_date' => '2011-02-02',
            'entry_date' => '2026-07-01',
            'status' => 'aktif',
        ]);

        $this->subject = Subject::create([
            'code' => 'MTK-SEC',
            'name' => 'Matematika Keamanan',
            'subject_group' => 'wajib',
        ]);

        $this->room1 = Room::create([
            'code' => 'R-SEC-1',
            'name' => 'Lab CBT 1',
            'room_type' => 'laboratorium',
            'capacity' => 30,
        ]);

        $this->room2 = Room::create([
            'code' => 'R-SEC-2',
            'name' => 'Lab CBT 2',
            'room_type' => 'laboratorium',
            'capacity' => 30,
        ]);

        $this->bankA = QuestionBank::create([
            'name' => 'Bank Soal Milik Guru A',
            'subject_id' => $this->subject->id,
            'owner_id' => $this->teacherA->id,
        ]);
    }

    public function test_cbt_sec_01_pg_kompleks_duplicate_option_ids_cannot_inflate_score(): void
    {
        $qPgk = Question::create([
            'bank_id' => $this->bankA->id,
            'question_type' => 'pg_kompleks',
            'body' => ['content' => 'Pilih dua bilangan prima:'],
            'weight' => 10.0,
            'status' => 'aktif',
        ]);
        $optA = QuestionOption::create(['question_id' => $qPgk->id, 'label' => 'A', 'body' => ['text' => '2'], 'is_correct' => true]);
        QuestionOption::create(['question_id' => $qPgk->id, 'label' => 'B', 'body' => ['text' => '3'], 'is_correct' => true]);
        QuestionOption::create(['question_id' => $qPgk->id, 'label' => 'C', 'body' => ['text' => '4'], 'is_correct' => false]);

        $exam = Exam::create([
            'subject_id' => $this->subject->id,
            'created_by' => $this->adminUser->id,
            'title' => 'Ujian Anti Inflasi Skor',
            'exam_type' => 'harian',
            'duration_min' => 60,
            'start_at' => Carbon::now()->subMinutes(10),
            'end_at' => Carbon::now()->addHour(),
            'status' => 'berlangsung',
        ]);
        ExamQuestion::create(['exam_id' => $exam->id, 'question_id' => $qPgk->id, 'sort_order' => 1, 'weight_override' => 10.0]);
        $examRoom = ExamRoom::create(['exam_id' => $exam->id, 'room_id' => $this->room1->id, 'session_no' => 1, 'token' => 'SEC001']);
        ExamParticipant::create(['exam_id' => $exam->id, 'student_id' => $this->student1->id, 'exam_room_id' => $examRoom->id]);

        $this->actingAs($this->studentUser1, 'sanctum')
            ->postJson("/api/v1/exams/{$exam->id}/start-session", ['token' => 'SEC001'])
            ->assertStatus(200);

        // Eksploitasi: kirim ID opsi benar yang sama 10 kali (hanya 1 dari 2 opsi benar yang dipilih)
        $duplicatedIds = array_fill(0, 10, $optA->id);
        $this->actingAs($this->studentUser1, 'sanctum')
            ->postJson("/api/v1/exams/{$exam->id}/save-answer", [
                'question_id' => $qPgk->id,
                'answer' => ['selected_option_ids' => $duplicatedIds],
            ])
            ->assertStatus(200);

        $this->actingAs($this->studentUser1, 'sanctum')
            ->postJson("/api/v1/exams/{$exam->id}/finish-session")
            ->assertStatus(200);

        $session = ExamSession::where('exam_id', $exam->id)->where('student_id', $this->student1->id)->firstOrFail();
        $answer = ExamAnswer::where('session_id', $session->id)->where('question_id', $qPgk->id)->firstOrFail();

        // 1 dari 2 opsi benar => skor butir harus 5.0 (bukan 50.0!) dan skor sesi harus 50.00 (bukan >100!)
        $this->assertEquals(5.0, (float) $answer->score);
        $this->assertEquals(50.0, (float) $session->score);
    }

    public function test_cbt_int_01_multi_package_exam_scores_against_assigned_package_and_blocks_cross_package_answer(): void
    {
        $qPkgA = Question::create(['bank_id' => $this->bankA->id, 'question_type' => 'pg', 'body' => ['content' => 'Soal Paket A'], 'weight' => 10.0, 'status' => 'aktif']);
        $optA1 = QuestionOption::create(['question_id' => $qPkgA->id, 'label' => 'A', 'body' => ['text' => 'Benar A'], 'is_correct' => true]);
        QuestionOption::create(['question_id' => $qPkgA->id, 'label' => 'B', 'body' => ['text' => 'Salah A'], 'is_correct' => false]);

        $qPkgB = Question::create(['bank_id' => $this->bankA->id, 'question_type' => 'pg', 'body' => ['content' => 'Soal Paket B'], 'weight' => 10.0, 'status' => 'aktif']);
        $optB1 = QuestionOption::create(['question_id' => $qPkgB->id, 'label' => 'A', 'body' => ['text' => 'Benar B'], 'is_correct' => true]);
        QuestionOption::create(['question_id' => $qPkgB->id, 'label' => 'B', 'body' => ['text' => 'Salah B'], 'is_correct' => false]);

        $exam = Exam::create([
            'subject_id' => $this->subject->id,
            'created_by' => $this->adminUser->id,
            'title' => 'Ujian Multi Paket A/B',
            'exam_type' => 'sumatif',
            'duration_min' => 60,
            'start_at' => Carbon::now()->subMinutes(5),
            'end_at' => Carbon::now()->addHour(),
            'package_mode' => 'multi',
            'status' => 'berlangsung',
        ]);
        ExamQuestion::create(['exam_id' => $exam->id, 'question_id' => $qPkgA->id, 'sort_order' => 1, 'package_code' => 'A', 'weight_override' => 10.0]);
        ExamQuestion::create(['exam_id' => $exam->id, 'question_id' => $qPkgB->id, 'sort_order' => 1, 'package_code' => 'B', 'weight_override' => 10.0]);

        $examRoom = ExamRoom::create(['exam_id' => $exam->id, 'room_id' => $this->room1->id, 'session_no' => 1, 'token' => 'PKGA01']);
        ExamParticipant::create(['exam_id' => $exam->id, 'student_id' => $this->student1->id, 'exam_room_id' => $examRoom->id, 'package_code' => 'A']);

        $this->actingAs($this->studentUser1, 'sanctum')
            ->postJson("/api/v1/exams/{$exam->id}/start-session", ['token' => 'PKGA01'])
            ->assertStatus(200);

        // Coba kirim jawaban untuk soal Paket B -> harus ditolak 422
        $this->actingAs($this->studentUser1, 'sanctum')
            ->postJson("/api/v1/exams/{$exam->id}/save-answer", [
                'question_id' => $qPkgB->id,
                'answer' => ['selected_option_id' => $optB1->id],
            ])
            ->assertStatus(422);

        // Jawab soal Paket A dengan benar -> skor akhir harus 100.00 (bukan 50.00)
        $this->actingAs($this->studentUser1, 'sanctum')
            ->postJson("/api/v1/exams/{$exam->id}/save-answer", [
                'question_id' => $qPkgA->id,
                'answer' => ['selected_option_id' => $optA1->id],
            ])
            ->assertStatus(200);

        $this->actingAs($this->studentUser1, 'sanctum')
            ->postJson("/api/v1/exams/{$exam->id}/finish-session")
            ->assertStatus(200);

        $session = ExamSession::where('exam_id', $exam->id)->where('student_id', $this->student1->id)->firstOrFail();
        $this->assertEquals(100.0, (float) $session->score);
    }

    public function test_cbt_sec_02_token_not_leaked_and_start_at_plus_status_enforced(): void
    {
        $futureExam = Exam::create([
            'subject_id' => $this->subject->id,
            'created_by' => $this->adminUser->id,
            'title' => 'Ujian Besok Pagi',
            'exam_type' => 'sas',
            'duration_min' => 90,
            'start_at' => Carbon::now()->addHours(5),
            'end_at' => Carbon::now()->addHours(8),
            'show_result_mode' => 'after_release',
            'status' => 'dijadwalkan',
        ]);
        $examRoom = ExamRoom::create(['exam_id' => $futureExam->id, 'room_id' => $this->room1->id, 'session_no' => 1, 'token' => 'RAHASIA']);
        $participant = ExamParticipant::create(['exam_id' => $futureExam->id, 'student_id' => $this->student1->id, 'exam_room_id' => $examRoom->id]);

        // 1. GET /me/exams tidak boleh membocorkan token ruang ujian
        $meRes = $this->actingAs($this->studentUser1, 'sanctum')->getJson('/api/v1/me/exams');
        $meRes->assertStatus(200);
        $this->assertStringNotContainsString('RAHASIA', $meRes->getContent());
        $this->assertArrayNotHasKey('tokenRequired', $meRes->json('data.0'));

        // 2. Kartu peserta PDF tidak boleh mencetak token ruang
        $pdfRes = $this->actingAs($this->studentUser1, 'sanctum')
            ->get("/api/v1/exams/{$futureExam->id}/participants/{$participant->id}/card-pdf");
        $pdfRes->assertStatus(200);
        $this->assertStringNotContainsString('RAHASIA', $pdfRes->getContent());

        // 3. Mulai ujian sebelum start_at ditolak 422
        $this->actingAs($this->studentUser1, 'sanctum')
            ->postJson("/api/v1/exams/{$futureExam->id}/start-session", ['token' => 'RAHASIA'])
            ->assertStatus(422);

        // 4. Mulai ujian saat start_at sudah tiba tetapi status masih draft ditolak 422
        $futureExam->update(['start_at' => Carbon::now()->subMinutes(5), 'status' => 'draft']);
        $this->actingAs($this->studentUser1, 'sanctum')
            ->postJson("/api/v1/exams/{$futureExam->id}/start-session", ['token' => 'RAHASIA'])
            ->assertStatus(422);
    }

    public function test_cbt_sec_03_exam_detail_hides_student_pii_nik_and_participants(): void
    {
        $exam = Exam::create([
            'subject_id' => $this->subject->id,
            'created_by' => $this->adminUser->id,
            'title' => 'Ujian Privasi Peserta',
            'exam_type' => 'harian',
            'duration_min' => 60,
            'start_at' => Carbon::now()->subMinutes(5),
            'end_at' => Carbon::now()->addHour(),
            'status' => 'berlangsung',
        ]);
        $examRoom = ExamRoom::create(['exam_id' => $exam->id, 'room_id' => $this->room1->id, 'session_no' => 1, 'token' => 'PRV123']);
        ExamParticipant::create(['exam_id' => $exam->id, 'student_id' => $this->student1->id, 'exam_room_id' => $examRoom->id]);
        ExamParticipant::create(['exam_id' => $exam->id, 'student_id' => $this->student2->id, 'exam_room_id' => $examRoom->id]);

        $res = $this->actingAs($this->studentUser1, 'sanctum')->getJson("/api/v1/exams/{$exam->id}");
        $res->assertStatus(200);
        $this->assertArrayNotHasKey('participants', $res->json('data'));
        $this->assertStringNotContainsString('3500000000000001', $res->getContent());
        $this->assertStringNotContainsString('3500000000000002', $res->getContent());
        $this->assertStringNotContainsString('PRV123', $res->getContent());
    }

    public function test_cbt_sec_04_minutes_html_escapes_xss_and_report_isolates_proctor_rooms(): void
    {
        $exam = Exam::create([
            'subject_id' => $this->subject->id,
            'created_by' => $this->adminUser->id,
            'title' => 'Ujian <script>alert("xss")</script>',
            'exam_type' => 'sas',
            'duration_min' => 60,
            'start_at' => Carbon::now()->subHour(),
            'end_at' => Carbon::now()->addHour(),
            'status' => 'berlangsung',
        ]);
        $er1 = ExamRoom::create(['exam_id' => $exam->id, 'room_id' => $this->room1->id, 'proctor_id' => $this->teacherA->id, 'session_no' => 1, 'token' => 'RM0001']);
        $er2 = ExamRoom::create(['exam_id' => $exam->id, 'room_id' => $this->room2->id, 'proctor_id' => $this->teacherB->id, 'session_no' => 1, 'token' => 'RM0002']);

        ExamParticipant::create(['exam_id' => $exam->id, 'student_id' => $this->student1->id, 'exam_room_id' => $er1->id]);
        ExamParticipant::create(['exam_id' => $exam->id, 'student_id' => $this->student2->id, 'exam_room_id' => $er2->id]);

        // Teacher A (proktor ruang 1) hanya boleh melihat peserta ruang 1 di /report
        $reportRes = $this->actingAs($this->teacherA, 'sanctum')->getJson("/api/v1/exams/{$exam->id}/report");
        $reportRes->assertStatus(200)
            ->assertJsonPath('data.attendance_summary.total_allocated', 1);

        // Teacher A ditolak 403 jika meminta room_id ruang 2
        $this->actingAs($this->teacherA, 'sanctum')
            ->getJson("/api/v1/exams/{$exam->id}/report?room_id={$er2->id}")
            ->assertStatus(403);

        // Berita acara HTML wajib meng-escape tag <script>
        $htmlRes = $this->actingAs($this->adminUser, 'sanctum')->get("/api/v1/exams/{$exam->id}/minutes/pdf");
        $htmlRes->assertStatus(200);
        $this->assertStringNotContainsString('<script>alert', $htmlRes->getContent());
        $this->assertStringContainsString('&lt;script&gt;', $htmlRes->getContent());
    }

    public function test_cbt_sec_05_sync_pull_hmac_and_push_conflict_protection(): void
    {
        $q = Question::create(['bank_id' => $this->bankA->id, 'question_type' => 'pg', 'body' => ['content' => 'Soal Sync'], 'weight' => 10.0, 'status' => 'aktif']);
        $optCorrect = QuestionOption::create(['question_id' => $q->id, 'label' => 'A', 'body' => ['text' => 'Benar'], 'is_correct' => true]);
        $optWrong = QuestionOption::create(['question_id' => $q->id, 'label' => 'B', 'body' => ['text' => 'Salah'], 'is_correct' => false]);

        $exam = Exam::create([
            'subject_id' => $this->subject->id,
            'created_by' => $this->adminUser->id,
            'title' => 'Ujian Sync Lokal',
            'exam_type' => 'sas',
            'duration_min' => 60,
            'start_at' => Carbon::now()->subHour(),
            'end_at' => Carbon::now()->addHour(),
            'status' => 'berlangsung',
            'sync_node_token' => 'secret-sync-node-token-999',
        ]);
        ExamQuestion::create(['exam_id' => $exam->id, 'question_id' => $q->id, 'sort_order' => 1]);
        $er = ExamRoom::create(['exam_id' => $exam->id, 'room_id' => $this->room1->id, 'session_no' => 1, 'token' => 'SNC123']);
        ExamParticipant::create(['exam_id' => $exam->id, 'student_id' => $this->student1->id, 'exam_room_id' => $er->id]);

        $pullRes = $this->postJson('/api/v1/cbt/sync/pull', [
            'exam_id' => $exam->id,
            'node_id' => 'node-lab-1',
            'sync_token' => 'secret-sync-node-token-999',
        ]);
        $pullRes->assertStatus(200);

        $unsaltedGuess = hash('sha256', 'CORRECT_'.$optCorrect->id);
        $actualHash = $pullRes->json('data.questions.0.options.0.is_correct_hash');
        $this->assertNotEquals($unsaltedGuess, $actualHash);

        // Buat sesi yang sudah selesai & punya nilai resmi di server pusat
        $session = ExamSession::create([
            'exam_id' => $exam->id,
            'student_id' => $this->student1->id,
            'status' => 'selesai',
            'started_at' => Carbon::now()->subMinutes(40),
            'deadline_at' => Carbon::now()->addMinutes(20),
            'submitted_at' => Carbon::now()->subMinutes(5),
            'seed' => 'seed-sync',
            'score' => 100.0,
        ]);
        ExamAnswer::create([
            'session_id' => $session->id,
            'question_id' => $q->id,
            'answer' => ['selected_option_id' => $optCorrect->id],
            'score' => 10.0,
        ]);

        // Push konflik tidak boleh menimpa jawaban resmi yang sudah dinilai
        $pushRes = $this->postJson('/api/v1/cbt/sync/push', [
            'exam_id' => $exam->id,
            'node_id' => 'node-lab-1',
            'sync_token' => 'secret-sync-node-token-999',
            'sessions' => [
                [
                    'student_id' => $this->student1->id,
                    'answers' => [
                        ['question_id' => $q->id, 'answer' => ['selected_option_id' => $optWrong->id]],
                    ],
                ],
            ],
        ]);
        $pushRes->assertStatus(200)->assertJsonPath('data.conflicts', 1);

        $savedAns = ExamAnswer::where('session_id', $session->id)->where('question_id', $q->id)->firstOrFail();
        $this->assertEquals($optCorrect->id, $savedAns->answer['selected_option_id']);
    }

    public function test_cbt_sec_07_single_device_session_lock_blocks_second_device_until_proctor_unlocks(): void
    {
        $q = Question::create(['bank_id' => $this->bankA->id, 'question_type' => 'pg', 'body' => ['content' => 'Soal Device Lock'], 'weight' => 10.0, 'status' => 'aktif']);
        QuestionOption::create(['question_id' => $q->id, 'label' => 'A', 'body' => ['text' => 'Opsi A'], 'is_correct' => true]);
        QuestionOption::create(['question_id' => $q->id, 'label' => 'B', 'body' => ['text' => 'Opsi B'], 'is_correct' => false]);

        $exam = Exam::create([
            'subject_id' => $this->subject->id,
            'created_by' => $this->adminUser->id,
            'title' => 'Ujian Single Device Lock',
            'exam_type' => 'harian',
            'duration_min' => 60,
            'start_at' => Carbon::now()->subMinutes(5),
            'end_at' => Carbon::now()->addHour(),
            'status' => 'berlangsung',
        ]);
        ExamQuestion::create(['exam_id' => $exam->id, 'question_id' => $q->id, 'sort_order' => 1]);
        $er = ExamRoom::create(['exam_id' => $exam->id, 'room_id' => $this->room1->id, 'proctor_id' => $this->teacherA->id, 'session_no' => 1, 'token' => 'LCK123']);
        ExamParticipant::create(['exam_id' => $exam->id, 'student_id' => $this->student1->id, 'exam_room_id' => $er->id]);

        // Perangkat 1 memulai sesi
        $start1 = $this->actingAs($this->studentUser1, 'sanctum')
            ->postJson("/api/v1/exams/{$exam->id}/start-session", [
                'token' => 'LCK123',
                'device_fingerprint' => 'web-device-1',
            ]);
        $start1->assertStatus(200);
        $lockToken = $start1->json('data.session_lock_token');
        $sessionId = $start1->json('data.session_id');
        $this->assertNotEmpty($lockToken);

        // Perangkat 1 bisa mengambil soal dengan X-Exam-Session-Token
        $this->actingAs($this->studentUser1, 'sanctum')
            ->withHeaders(['X-Exam-Session-Token' => $lockToken])
            ->getJson("/api/v1/exams/{$exam->id}/session-questions")
            ->assertStatus(200);

        $this->flushHeaders();

        // Perangkat 2 (fingerprint berbeda / tanpa lock token) ditolak 403 saat coba start-session maupun ambil soal
        $this->actingAs($this->studentUser1, 'sanctum')
            ->postJson("/api/v1/exams/{$exam->id}/start-session", [
                'token' => 'LCK123',
                'device_fingerprint' => 'web-device-joki-2',
            ])
            ->assertStatus(403);

        $this->actingAs($this->studentUser1, 'sanctum')
            ->withHeaders(['X-Exam-Session-Token' => 'forged-token-999'])
            ->getJson("/api/v1/exams/{$exam->id}/session-questions")
            ->assertStatus(403);

        $this->flushHeaders();

        // Proktor membuka kunci sesi untuk pindah PC resmi di lab
        $this->actingAs($this->teacherA, 'sanctum')
            ->postJson("/api/v1/exam-sessions/{$sessionId}/manage", [
                'action' => 'unlock',
                'reason' => 'Pindah komputer lab karena PC 1 mati',
            ])
            ->assertStatus(200);

        // Kini perangkat pengganti resmi dapat melanjutkan sesi
        $this->actingAs($this->studentUser1, 'sanctum')
            ->postJson("/api/v1/exams/{$exam->id}/start-session", [
                'token' => 'LCK123',
                'device_fingerprint' => 'web-device-replacement-3',
            ])
            ->assertStatus(200);
    }

    public function test_cbt_sec_09_question_bank_blocks_unauthorized_cross_teacher_modification(): void
    {
        // Teacher B mencoba mengedit & menghapus bank soal milik Teacher A -> ditolak 403
        $this->actingAs($this->teacherB, 'sanctum')
            ->putJson("/api/v1/question-banks/{$this->bankA->id}", [
                'name' => 'Diambil Alih Guru B',
                'subject_id' => $this->subject->id,
            ])
            ->assertStatus(403);

        $this->actingAs($this->teacherB, 'sanctum')
            ->deleteJson("/api/v1/question-banks/{$this->bankA->id}")
            ->assertStatus(403);
    }
}
