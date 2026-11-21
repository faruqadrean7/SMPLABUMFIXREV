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
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CbtOperationalAndSecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $proctorUser;

    protected User $otherProctorUser;

    protected User $studentUser;

    protected Student $student;

    protected Exam $exam;

    protected ExamRoom $examRoom1;

    protected ExamRoom $examRoom2;

    protected QuestionBank $bank;

    protected Question $qPg;

    protected Question $qPgKompleks;

    protected Question $qBs;

    protected Question $qMenjodohkan;

    protected Question $qIsian;

    protected Question $qUraian;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Admin', 'is_system' => true]);
        $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);
        $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);

        $permManage = Permission::firstOrCreate(['code' => 'cbt.exam.manage'], ['description' => 'Kelola Ujian CBT']);
        $permProctor = Permission::firstOrCreate(['code' => 'cbt.proctor.action'], ['description' => 'Aksi Proktor']);
        $roleAdmin->permissions()->syncWithoutDetaching([$permManage->id]);
        $roleTeacher->permissions()->syncWithoutDetaching([$permProctor->id]);

        $this->adminUser = User::create([
            'username' => 'admin_cbt_audit',
            'email' => 'admin_audit@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($roleAdmin->id);

        $this->proctorUser = User::create([
            'username' => 'proctor_audit_1',
            'email' => 'proctor1@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->proctorUser->roles()->attach($roleTeacher->id);

        $this->otherProctorUser = User::create([
            'username' => 'proctor_audit_2',
            'email' => 'proctor2@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->otherProctorUser->roles()->attach($roleTeacher->id);

        $this->studentUser = User::create([
            'username' => 'siswa_audit_01',
            'email' => 'siswa01@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->studentUser->roles()->attach($roleStudent->id);

        $this->student = Student::create([
            'user_id' => $this->studentUser->id,
            'full_name' => 'Ahmad Auditee',
            'nis' => 'siswa_audit_01',
            'nisn' => '0099887766',
            'nik_enc' => 'enc_nik_audit',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2010-05-15',
            'entry_date' => '2026-07-01',
            'status' => 'aktif',
        ]);

        $subject = Subject::firstOrCreate(['code' => 'IPA10'], ['name' => 'Ilmu Pengetahuan Alam', 'subject_group' => 'muatan_nasional']);

        $room1 = Room::create(['code' => 'LAB-KOMP-1', 'name' => 'Lab Komputer 1', 'room_type' => 'laboratorium', 'capacity' => 40]);
        $room2 = Room::create(['code' => 'LAB-KOMP-2', 'name' => 'Lab Komputer 2', 'room_type' => 'laboratorium', 'capacity' => 40]);

        $this->exam = Exam::create([
            'subject_id' => $subject->id,
            'created_by' => $this->adminUser->id,
            'title' => 'Asesmen Sumatif IPA Semester Ganjil',
            'exam_type' => 'sumatif_akhir',
            'duration_min' => 60,
            'start_at' => Carbon::now()->subHour(),
            'end_at' => Carbon::now()->addHours(3),
            'passing_grade' => 75.0,
            'status' => 'berlangsung',
            'shuffle_questions' => true,
            'shuffle_options' => true,
        ]);

        $this->examRoom1 = ExamRoom::create([
            'exam_id' => $this->exam->id,
            'room_id' => $room1->id,
            'proctor_id' => $this->proctorUser->id,
            'session_no' => 1,
            'token' => 'ABC123',
            'token_expires_at' => Carbon::now()->addHours(2),
        ]);

        $this->examRoom2 = ExamRoom::create([
            'exam_id' => $this->exam->id,
            'room_id' => $room2->id,
            'proctor_id' => $this->otherProctorUser->id,
            'session_no' => 1,
            'token' => 'XYZ999',
            'token_expires_at' => Carbon::now()->addHours(2),
        ]);

        ExamParticipant::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student->id,
            'exam_room_id' => $this->examRoom1->id,
        ]);

        $this->bank = QuestionBank::create([
            'subject_id' => $subject->id,
            'owner_id' => $this->adminUser->id,
            'name' => 'Bank Soal Uji Sumatif IPA',
        ]);

        // 1. Soal PG
        $this->qPg = Question::create([
            'bank_id' => $this->bank->id,
            'question_type' => 'pg',
            'body' => ['text' => 'Hewan mamalia bernapas dengan?'],
            'weight' => 10.0,
            'status' => 'aktif',
        ]);
        QuestionOption::create(['question_id' => $this->qPg->id, 'label' => 'A', 'body' => ['text' => 'Paru-paru'], 'is_correct' => true]);
        QuestionOption::create(['question_id' => $this->qPg->id, 'label' => 'B', 'body' => ['text' => 'Insang'], 'is_correct' => false]);

        // 2. Soal PG Kompleks
        $this->qPgKompleks = Question::create([
            'bank_id' => $this->bank->id,
            'question_type' => 'pg_kompleks',
            'body' => ['text' => 'Manakah yang termasuk planet dalam tata surya?'],
            'weight' => 20.0,
            'status' => 'aktif',
        ]);
        QuestionOption::create(['question_id' => $this->qPgKompleks->id, 'label' => 'A', 'body' => ['text' => 'Mars'], 'is_correct' => true]);
        QuestionOption::create(['question_id' => $this->qPgKompleks->id, 'label' => 'B', 'body' => ['text' => 'Jupiter'], 'is_correct' => true]);
        QuestionOption::create(['question_id' => $this->qPgKompleks->id, 'label' => 'C', 'body' => ['text' => 'Bulan'], 'is_correct' => false]);

        // 3. Soal Benar-Salah
        $this->qBs = Question::create([
            'bank_id' => $this->bank->id,
            'question_type' => 'bs',
            'body' => ['text' => 'Air membeku pada suhu 0 derajat Celsius.'],
            'weight' => 10.0,
            'status' => 'aktif',
        ]);
        QuestionOption::create(['question_id' => $this->qBs->id, 'label' => 'B', 'body' => ['text' => 'Benar'], 'is_correct' => true]);
        QuestionOption::create(['question_id' => $this->qBs->id, 'label' => 'S', 'body' => ['text' => 'Salah'], 'is_correct' => false]);

        // 4. Soal Menjodohkan
        $this->qMenjodohkan = Question::create([
            'bank_id' => $this->bank->id,
            'question_type' => 'menjodohkan',
            'body' => ['text' => 'Jodohkan lambang unsur berikut.'],
            'weight' => 20.0,
            'status' => 'aktif',
        ]);
        QuestionOption::create(['question_id' => $this->qMenjodohkan->id, 'label' => 'H', 'body' => ['text' => 'Hidrogen'], 'match_key' => 'Hidrogen', 'is_correct' => true]);
        QuestionOption::create(['question_id' => $this->qMenjodohkan->id, 'label' => 'O', 'body' => ['text' => 'Oksigen'], 'match_key' => 'Oksigen', 'is_correct' => true]);

        // 5. Soal Isian Singkat
        $this->qIsian = Question::create([
            'bank_id' => $this->bank->id,
            'question_type' => 'isian',
            'body' => ['text' => 'Ibukota negara Indonesia saat ini adalah?'],
            'weight' => 10.0,
            'status' => 'aktif',
        ]);
        QuestionOption::create(['question_id' => $this->qIsian->id, 'label' => 'Nusantara', 'body' => ['text' => 'Nusantara'], 'is_correct' => true]);

        // 6. Soal Uraian
        $this->qUraian = Question::create([
            'bank_id' => $this->bank->id,
            'question_type' => 'uraian',
            'body' => ['text' => 'Jelaskan proses fotosintesis pada tumbuhan.'],
            'weight' => 30.0,
            'status' => 'aktif',
        ]);

        $allQuestions = [$this->qPg, $this->qPgKompleks, $this->qBs, $this->qMenjodohkan, $this->qIsian, $this->qUraian];
        foreach ($allQuestions as $idx => $q) {
            ExamQuestion::create([
                'exam_id' => $this->exam->id,
                'question_id' => $q->id,
                'sort_order' => $idx + 1,
            ]);
        }
    }

    /**
     * 1. Autosave & Connection Resilience: Autosave harus idempoten dan menangani request ganda.
     */
    public function test_be_cbt_19_autosave_is_idempotent_and_handles_duplicate_requests(): void
    {
        // Mulai sesi
        $startRes = $this->actingAs($this->studentUser, 'sanctum')
            ->postJson("/api/v1/exams/{$this->exam->id}/start-session", ['token' => 'ABC123']);
        $startRes->assertStatus(200);

        $payload = [
            'question_id' => $this->qPg->id,
            'answer' => ['selected_option_id' => $this->qPg->options->first()->id],
            'is_flagged' => false,
        ];

        // Kirim request pertama
        $res1 = $this->actingAs($this->studentUser, 'sanctum')
            ->postJson("/api/v1/exams/{$this->exam->id}/save-answer", $payload);
        $res1->assertStatus(200)->assertJsonPath('data.is_saved', true);

        // Kirim request identik kedua (simulasi network retry)
        $res2 = $this->actingAs($this->studentUser, 'sanctum')
            ->postJson("/api/v1/exams/{$this->exam->id}/save-answer", $payload);
        $res2->assertStatus(200)->assertJsonPath('data.is_saved', true);

        // Hanya 1 baris jawaban yang tersimpan di database
        $session = ExamSession::where('exam_id', $this->exam->id)->where('student_id', $this->student->id)->first();
        $this->assertEquals(1, ExamAnswer::where('session_id', $session->id)->where('question_id', $this->qPg->id)->count());
    }

    /**
     * 2. Autosave & Connection Resilience: Autosave ditolak jika sesi telah selesai.
     */
    public function test_be_cbt_19_autosave_rejected_when_session_already_finished(): void
    {
        $session = ExamSession::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student->id,
            'status' => 'selesai',
            'started_at' => Carbon::now()->subMinutes(30),
            'deadline_at' => Carbon::now()->addMinutes(30),
            'submitted_at' => Carbon::now(),
            'seed' => 'seed123',
        ]);

        $response = $this->actingAs($this->studentUser, 'sanctum')
            ->postJson("/api/v1/exams/{$this->exam->id}/save-answer", [
                'question_id' => $this->qPg->id,
                'answer' => ['selected_option_id' => $this->qPg->options->first()->id],
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.message', 'Sesi ujian telah selesai dan tidak dapat diubah.');
    }

    /**
     * 3. Autosave & Connection Resilience: Autosave ditolak dan mengunci sesi saat deadline server telah lewat.
     */
    public function test_be_cbt_19_autosave_rejected_and_locks_session_when_deadline_passed(): void
    {
        $session = ExamSession::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student->id,
            'status' => 'mengerjakan',
            'started_at' => Carbon::now()->subMinutes(70),
            'deadline_at' => Carbon::now()->subMinutes(10), // Deadline sudah lewat 10 menit
            'seed' => 'seed123',
        ]);

        $response = $this->actingAs($this->studentUser, 'sanctum')
            ->postJson("/api/v1/exams/{$this->exam->id}/save-answer", [
                'question_id' => $this->qPg->id,
                'answer' => ['selected_option_id' => $this->qPg->options->first()->id],
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.message', 'Waktu pengerjaan ujian telah berakhir (timeout lock).');

        $session->refresh();
        $this->assertEquals('selesai', $session->status);
    }

    /**
     * 4. Autosave & Connection Resilience: Menolak butir soal di luar paket ujian ini.
     */
    public function test_be_cbt_19_autosave_rejects_question_not_in_exam_package(): void
    {
        $this->actingAs($this->studentUser, 'sanctum')
            ->postJson("/api/v1/exams/{$this->exam->id}/start-session", ['token' => 'ABC123']);

        $outsideQuestion = Question::create([
            'bank_id' => $this->bank->id,
            'question_type' => 'pg',
            'body' => ['text' => 'Soal dari ujian lain?'],
            'weight' => 10.0,
            'status' => 'aktif',
        ]);

        $response = $this->actingAs($this->studentUser, 'sanctum')
            ->postJson("/api/v1/exams/{$this->exam->id}/save-answer", [
                'question_id' => $outsideQuestion->id,
                'answer' => ['val' => 1],
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.message', 'Butir soal tidak terdaftar pada paket ujian ini.');
    }

    /**
     * 5. Double finish submission ditangani secara idempoten dan aman.
     */
    public function test_be_cbt_19_double_finish_request_is_handled_idempotently_without_race_condition(): void
    {
        $this->actingAs($this->studentUser, 'sanctum')
            ->postJson("/api/v1/exams/{$this->exam->id}/start-session", ['token' => 'ABC123']);

        $res1 = $this->actingAs($this->studentUser, 'sanctum')
            ->postJson("/api/v1/exams/{$this->exam->id}/finish-session");
        $res1->assertStatus(200)
            ->assertJsonPath('data.status', 'selesai');

        // Request submit kedua
        $res2 = $this->actingAs($this->studentUser, 'sanctum')
            ->postJson("/api/v1/exams/{$this->exam->id}/finish-session");
        $res2->assertStatus(200)
            ->assertJsonPath('data.status', 'selesai')
            ->assertJsonPath('data.message', 'Ujian sudah diselesaikan sebelumnya.');
    }

    /**
     * 6. Timer & Room Token: Token case-insensitive dan whitespace-trimmed.
     */
    public function test_be_cbt_19_token_validation_is_case_insensitive_and_trimmed(): void
    {
        // Token di DB adalah ABC123, input ' abc123  ' huruf kecil dengan spasi
        $response = $this->actingAs($this->studentUser, 'sanctum')
            ->postJson("/api/v1/exams/{$this->exam->id}/start-session", ['token' => ' abc123  ']);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'mengerjakan');
    }

    /**
     * 7. Timer & Room Token: Token yang kedaluwarsa ditolak tegas.
     */
    public function test_be_cbt_19_expired_token_is_strictly_rejected(): void
    {
        $this->examRoom1->update(['token_expires_at' => Carbon::now()->subMinute()]);

        $response = $this->actingAs($this->studentUser, 'sanctum')
            ->postJson("/api/v1/exams/{$this->exam->id}/start-session", ['token' => 'ABC123']);

        $response->assertStatus(422)
            ->assertJsonPath('error.message', 'Token ruang ujian telah kedaluwarsa. Silakan minta pengawas untuk me-regenerate token.');
    }

    /**
     * 8. Timer & Room Token: Regenerasi token langsung membatalkan token lama.
     */
    public function test_be_cbt_19_token_regeneration_immediately_invalidates_previous_token(): void
    {
        $oldToken = $this->examRoom1->token;

        // Proctor regenerate token
        $regenRes = $this->actingAs($this->proctorUser, 'sanctum')
            ->postJson("/api/v1/exam-rooms/{$this->examRoom1->id}/regenerate-token", ['validity_minutes' => 45]);
        $regenRes->assertStatus(200);

        $newToken = $regenRes->json('data.token');
        $this->assertNotEquals($oldToken, $newToken);

        // Coba login dengan token lama -> harus gagal
        $failRes = $this->actingAs($this->studentUser, 'sanctum')
            ->postJson("/api/v1/exams/{$this->exam->id}/start-session", ['token' => $oldToken]);
        $failRes->assertStatus(422);

        // Coba login dengan token baru -> berhasil
        $successRes = $this->actingAs($this->studentUser, 'sanctum')
            ->postJson("/api/v1/exams/{$this->exam->id}/start-session", ['token' => $newToken]);
        $successRes->assertStatus(200);
    }

    /**
     * 9. Keamanan Soal: Butir soal ujian siswa TIDAK PERNAH membocorkan kunci jawaban (is_correct).
     */
    public function test_be_cbt_19_session_questions_endpoint_strictly_masks_answer_keys(): void
    {
        $this->actingAs($this->studentUser, 'sanctum')
            ->postJson("/api/v1/exams/{$this->exam->id}/start-session", ['token' => 'ABC123']);

        $response = $this->actingAs($this->studentUser, 'sanctum')
            ->getJson("/api/v1/exams/{$this->exam->id}/session-questions");

        $response->assertStatus(200);
        $questions = $response->json('data.questions');

        foreach ($questions as $q) {
            foreach ($q['options'] as $opt) {
                $this->assertArrayNotHasKey('is_correct', $opt, 'Kunci jawaban is_correct bocor ke endpoint peserta!');
            }
        }
    }

    /**
     * 10. Proctor Actions: Unlock sesi membuka kunci dan memberikan waktu darurat jika deadline terlewat.
     */
    public function test_be_cbt_19_proctor_unlock_clears_lock_and_grants_emergency_time_if_past_deadline(): void
    {
        $session = ExamSession::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student->id,
            'status' => 'terkunci',
            'locked_reason' => 'Perpindahan tab berulang',
            'started_at' => Carbon::now()->subMinutes(80),
            'deadline_at' => Carbon::now()->subMinutes(20), // Sudah lewat
            'seed' => 'seed123',
        ]);

        $response = $this->actingAs($this->proctorUser, 'sanctum')
            ->postJson("/api/v1/exam-sessions/{$session->id}/manage", [
                'action' => 'unlock',
                'extra_minutes' => 15,
                'reason' => 'Siswa tidak sengaja tertekan keyboard shortcut',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'mengerjakan')
            ->assertJsonPath('data.extra_minutes', 15);

        $session->refresh();
        $this->assertEquals('mengerjakan', $session->status);
        $this->assertNull($session->locked_reason);
        $this->assertTrue($session->deadline_at->isFuture());

        // Verifikasi audit trails
        $this->assertDatabaseHas('exam_proctor_actions', [
            'session_id' => $session->id,
            'action' => 'unlock',
            'actor_id' => $this->proctorUser->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'entity' => 'exam_sessions',
            'entity_id' => $session->id,
            'action' => 'exam.proctor_action.unlock',
            'actor_id' => $this->proctorUser->id,
        ]);
    }

    /**
     * 11. Proctor Actions: Menambah waktu pada sesi yang telah selesai ditolak.
     */
    public function test_be_cbt_19_proctor_extra_time_fails_on_completed_session(): void
    {
        $session = ExamSession::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student->id,
            'status' => 'selesai',
            'started_at' => Carbon::now()->subMinutes(60),
            'deadline_at' => Carbon::now(),
            'submitted_at' => Carbon::now(),
            'seed' => 'seed123',
        ]);

        $response = $this->actingAs($this->proctorUser, 'sanctum')
            ->postJson("/api/v1/exam-sessions/{$session->id}/manage", [
                'action' => 'extra_time',
                'extra_minutes' => 10,
                'reason' => 'Perpanjangan waktu',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.message', 'Tidak dapat menambahkan waktu pada sesi ujian yang telah selesai. Buka kembali sesi terlebih dahulu bila diperlukan.');
    }

    /**
     * 12. Proctor Actions: Tindakan pengawas pada ujian berstatus arsip ditolak.
     */
    public function test_be_cbt_19_proctor_actions_disallowed_on_archived_exam(): void
    {
        $this->exam->update(['status' => 'arsip']);

        $session = ExamSession::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student->id,
            'status' => 'terkunci',
            'seed' => 'seed123',
        ]);

        $response = $this->actingAs($this->proctorUser, 'sanctum')
            ->postJson("/api/v1/exam-sessions/{$session->id}/manage", [
                'action' => 'unlock',
                'reason' => 'Buka kunci',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.message', 'Ujian telah diarsipkan, data sesi tidak dapat diubah.');
    }

    /**
     * 13. Proctor Actions: Isolasi ruang mencegah pengawas mengelola peserta di ruang lain.
     */
    public function test_be_cbt_19_proctor_isolation_prevents_managing_other_room_participants(): void
    {
        $session = ExamSession::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student->id,
            'status' => 'terkunci',
            'seed' => 'seed123',
        ]);

        // Siswa berada di ruang 1 (proctorUser)
        // Pengawas dari ruang 2 (otherProctorUser) mencoba membuka kunci -> harus 403
        $response = $this->actingAs($this->otherProctorUser, 'sanctum')
            ->postJson("/api/v1/exam-sessions/{$session->id}/manage", [
                'action' => 'unlock',
                'reason' => 'Mencoba membuka kunci peserta ruang lain',
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('error.message', 'Anda tidak berwenang mengelola peserta di luar ruangan penugasan Anda.');
    }

    /**
     * 14. Scoring Formulas: Koreksi otomatis akurat untuk semua 6 tipe soal.
     */
    public function test_be_cbt_19_scoring_formulas_for_all_six_question_types(): void
    {
        $session = ExamSession::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student->id,
            'status' => 'mengerjakan',
            'started_at' => Carbon::now()->subMinutes(40),
            'deadline_at' => Carbon::now()->addMinutes(20),
            'seed' => 'seed123',
        ]);

        $correctPgOpt = $this->qPg->options->where('is_correct', true)->first();
        $correctPgKompleksOpts = $this->qPgKompleks->options->where('is_correct', true)->pluck('id')->all();
        $correctBsOpt = $this->qBs->options->where('is_correct', true)->first();

        // Jawab PG (Benar -> 10 poin)
        ExamAnswer::create([
            'session_id' => $session->id,
            'question_id' => $this->qPg->id,
            'answer' => ['selected_option_id' => $correctPgOpt->id],
        ]);

        // Jawab PG Kompleks (1 benar dari 2 target benar, 0 salah -> parsial 50% = 10 poin)
        ExamAnswer::create([
            'session_id' => $session->id,
            'question_id' => $this->qPgKompleks->id,
            'answer' => ['selected_option_ids' => [$correctPgKompleksOpts[0]]],
        ]);

        // Jawab BS (Benar -> 10 poin)
        ExamAnswer::create([
            'session_id' => $session->id,
            'question_id' => $this->qBs->id,
            'answer' => ['selected_option_id' => $correctBsOpt->id],
        ]);

        // Jawab Menjodohkan (2 dari 2 benar -> 20 poin)
        ExamAnswer::create([
            'session_id' => $session->id,
            'question_id' => $this->qMenjodohkan->id,
            'answer' => [
                'pairs' => [
                    $this->qMenjodohkan->options[0]->id => 'Hidrogen',
                    $this->qMenjodohkan->options[1]->id => 'Oksigen',
                ],
            ],
        ]);

        // Jawab Isian (Benar case-insensitive -> 10 poin)
        ExamAnswer::create([
            'session_id' => $session->id,
            'question_id' => $this->qIsian->id,
            'answer' => ['text' => '  nusantara  '],
        ]);

        // Jawab Uraian (Belum dinilai)
        $uraianAnswer = ExamAnswer::create([
            'session_id' => $session->id,
            'question_id' => $this->qUraian->id,
            'answer' => ['text' => 'Proses fotosintesis mengubah air dan CO2 dengan bantuan cahaya matahari.'],
        ]);

        // Jalankan auto-grade untuk soal objektif
        $autoGradeRes = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/exams/{$this->exam->id}/auto-grade");
        $autoGradeRes->assertStatus(200);

        // Nilai manual soal uraian (25 dari max 30)
        $essayRes = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/exams/{$this->exam->id}/grade-essay", [
                'answer_id' => $uraianAnswer->id,
                'score' => 25.0,
            ]);
        $essayRes->assertStatus(200);

        // Total bobot: 10 + 20 + 10 + 20 + 10 + 30 = 100
        // Perolehan: 10 + 10 (parsial) + 10 + 20 + 10 + 25 = 85.0
        $session->refresh();
        $this->assertEquals(85.0, (float) $session->score);
    }

    /**
     * 15. Remedial Duplicate Call Idempotency: Request ganda tidak menduplikasi data ujian remedial.
     */
    public function test_be_cbt_19_remedial_creation_duplicate_call_is_idempotent(): void
    {
        // Berikan sesi nilai tidak lulus (< 75.0)
        $session = ExamSession::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student->id,
            'status' => 'selesai',
            'score' => 60.0,
            'started_at' => Carbon::now()->subMinutes(60),
            'submitted_at' => Carbon::now(),
            'seed' => 'seedRem',
        ]);

        $payload = [
            'title' => 'Remedial Asesmen Sumatif IPA',
            'start_at' => Carbon::now()->addDay()->toDateTimeString(),
            'end_at' => Carbon::now()->addDays(2)->toDateTimeString(),
            'passing_grade' => 75.0,
        ];

        // Call pertama
        $res1 = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/exams/{$this->exam->id}/remedial", $payload);
        $res1->assertStatus(201);
        $remedialId = $res1->json('data.id');

        // Call kedua dengan payload yang sama
        $res2 = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/exams/{$this->exam->id}/remedial", $payload);
        $res2->assertStatus(201);

        $this->assertEquals($remedialId, $res2->json('data.id'));
        $this->assertEquals(1, Exam::where('parent_exam_id', $this->exam->id)->where('title', 'Remedial Asesmen Sumatif IPA')->count());
    }
}
