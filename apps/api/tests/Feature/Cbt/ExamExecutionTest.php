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
use App\Models\Permission;
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

class ExamExecutionTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $proctorUser;

    protected User $studentUser1;

    protected User $studentUser2;

    protected Student $student1;

    protected Student $student2;

    protected Exam $exam;

    protected ExamRoom $examRoom;

    protected Question $questionPg;

    protected Question $questionEssay;

    protected function setUp(): void
    {
        parent::setUp();

        $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);
        $roleAdmin = Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Admin', 'is_system' => true]);
        $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);

        $permManage = Permission::firstOrCreate(['code' => 'cbt.exam.manage'], ['description' => 'Mengelola jadwal dan sesi ujian CBT']);
        $roleAdmin->permissions()->syncWithoutDetaching([$permManage->id]);

        $this->adminUser = User::create([
            'username' => 'admin_cbt',
            'email' => 'admin_cbt@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($roleAdmin->id);

        $this->proctorUser = User::create([
            'username' => 'proctor_cbt',
            'email' => 'proctor_cbt@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->proctorUser->roles()->attach($roleTeacher->id);

        $this->studentUser1 = User::create([
            'username' => '20261001',
            'email' => 'faiz@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->studentUser1->roles()->attach($roleStudent->id);

        $this->studentUser2 = User::create([
            'username' => '20261002',
            'email' => 'budi@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->studentUser2->roles()->attach($roleStudent->id);

        $teacher = Teacher::create([
            'user_id' => $this->proctorUser->id,
            'nip' => '198501012010011005',
            'full_name' => 'Budi Pengawas, M.Kom',
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
            'name' => 'Biologi',
            'code' => 'BIO-10',
            'subject_group' => 'muatan_nasional',
        ]);

        $room = Room::create([
            'name' => 'Laboratorium Multimedia',
            'code' => 'LAB-MM',
            'room_type' => 'laboratorium',
            'capacity' => 30,
        ]);

        $this->student1 = Student::create([
            'user_id' => $this->studentUser1->id,
            'full_name' => 'Ahmad Faiz Pratama',
            'nis' => '20261001',
            'nisn' => '0012345678',
            'nik_enc' => 'enc_3578010101010001',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2010-01-01',
            'entry_date' => '2026-07-01',
            'status' => 'aktif',
        ]);

        $this->student2 = Student::create([
            'user_id' => $this->studentUser2->id,
            'full_name' => 'Budi Santoso',
            'nis' => '20261002',
            'nisn' => '0012345679',
            'nik_enc' => 'enc_3578010101010002',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2010-02-02',
            'entry_date' => '2026-07-01',
            'status' => 'aktif',
        ]);

        ClassStudent::create([
            'class_id' => $classA->id,
            'student_id' => $this->student1->id,
            'joined_at' => '2026-07-15',
        ]);

        ClassStudent::create([
            'class_id' => $classA->id,
            'student_id' => $this->student2->id,
            'joined_at' => '2026-07-15',
        ]);

        $this->exam = Exam::create([
            'subject_id' => $subject->id,
            'created_by' => $this->adminUser->id,
            'title' => 'Asesmen Sumatif Biologi Sel',
            'exam_type' => 'harian',
            'duration_min' => 60,
            'start_at' => Carbon::now()->subMinutes(10),
            'end_at' => Carbon::now()->addHours(2),
            'passing_grade' => 75.0,
            'shuffle_questions' => true,
            'shuffle_options' => true,
            'status' => 'berlangsung',
        ]);

        $this->examRoom = ExamRoom::create([
            'exam_id' => $this->exam->id,
            'room_id' => $room->id,
            'proctor_id' => $this->proctorUser->id,
            'session_no' => 1,
            'token' => 'SECRET',
            'token_expires_at' => Carbon::now()->addMinutes(30),
        ]);

        // Daftarkan siswa 1 ke ujian
        ExamParticipant::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student1->id,
            'exam_room_id' => $this->examRoom->id,
            'package_code' => 'A',
        ]);

        // Buat Bank Soal & Butir Soal
        $bank = QuestionBank::create([
            'name' => 'Bank Soal Biologi',
            'subject_id' => $subject->id,
            'grade_level_id' => $gradeLevel->id,
            'owner_id' => $this->adminUser->id,
        ]);

        $this->questionPg = Question::create([
            'bank_id' => $bank->id,
            'question_type' => 'pg',
            'body' => ['content' => 'Organel sel yang berfungsi untuk respirasi sel adalah?'],
            'weight' => 2.0,
            'version' => 1,
            'status' => 'aktif',
        ]);

        QuestionOption::create([
            'question_id' => $this->questionPg->id,
            'label' => 'A',
            'body' => ['text' => 'Mitokondria'],
            'is_correct' => true,
        ]);

        QuestionOption::create([
            'question_id' => $this->questionPg->id,
            'label' => 'B',
            'body' => ['text' => 'Ribosom'],
            'is_correct' => false,
        ]);

        $this->questionEssay = Question::create([
            'bank_id' => $bank->id,
            'question_type' => 'uraian',
            'body' => ['content' => 'Jelaskan perbedaan sel hewan dan sel tumbuhan!'],
            'weight' => 5.0,
            'version' => 1,
            'status' => 'aktif',
        ]);

        // Plotting ke exam
        ExamQuestion::create([
            'exam_id' => $this->exam->id,
            'question_id' => $this->questionPg->id,
            'sort_order' => 1,
            'package_code' => 'A',
        ]);

        ExamQuestion::create([
            'exam_id' => $this->exam->id,
            'question_id' => $this->questionEssay->id,
            'sort_order' => 2,
            'package_code' => 'A',
        ]);
    }

    /**
     * BE-CBT-04: Siswa berhasil memulai sesi ujian dengan token yang cocok.
     */
    public function test_be_cbt_04_student_can_start_session_with_valid_token(): void
    {
        $this->app['auth']->forgetGuards();

        $response = $this->actingAs($this->studentUser1, 'sanctum')
            ->postJson("/api/v1/exams/{$this->exam->id}/start-session", [
                'token' => 'SECRET',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'mengerjakan')
            ->assertJsonPath('data.student_name', 'Ahmad Faiz Pratama');

        $this->assertDatabaseHas('exam_sessions', [
            'exam_id' => $this->exam->id,
            'student_id' => $this->student1->id,
            'status' => 'mengerjakan',
        ]);
    }

    /**
     * BE-CBT-04: Siswa ditolak jika memasukkan token yang salah.
     */
    public function test_be_cbt_04_student_rejected_with_invalid_token(): void
    {
        $this->app['auth']->forgetGuards();

        $response = $this->actingAs($this->studentUser1, 'sanctum')
            ->postJson("/api/v1/exams/{$this->exam->id}/start-session", [
                'token' => 'SALAH1',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.message', 'Token ruang ujian tidak valid atau tidak cocok.');
    }

    /**
     * BE-CBT-04: Siswa ditolak jika token ruang ujian telah kedaluwarsa.
     */
    public function test_be_cbt_04_student_rejected_with_expired_token(): void
    {
        $this->examRoom->update([
            'token_expires_at' => Carbon::now()->subMinutes(10),
        ]);

        $this->app['auth']->forgetGuards();

        $response = $this->actingAs($this->studentUser1, 'sanctum')
            ->postJson("/api/v1/exams/{$this->exam->id}/start-session", [
                'token' => 'SECRET',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'BUSINESS_RULE_VIOLATION');
    }

    /**
     * BE-CBT-04: Siswa yang tidak terdaftar di ujian ditolak (403 IDOR/Registration check).
     */
    public function test_be_cbt_04_unregistered_student_cannot_start_session(): void
    {
        $this->app['auth']->forgetGuards();

        // studentUser2 belum terdaftar di exam_participants
        $response = $this->actingAs($this->studentUser2, 'sanctum')
            ->postJson("/api/v1/exams/{$this->exam->id}/start-session", [
                'token' => 'SECRET',
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    /**
     * BE-CBT-04: Mengambil daftar butir soal ujian dengan aman tanpa kunci jawaban (Security Rule 6).
     */
    public function test_be_cbt_04_get_session_questions_strips_answer_keys(): void
    {
        $this->app['auth']->forgetGuards();

        // 1. Mulai sesi
        $this->actingAs($this->studentUser1, 'sanctum')
            ->postJson("/api/v1/exams/{$this->exam->id}/start-session", [
                'token' => 'SECRET',
            ]);

        // 2. Ambil butir soal
        $response = $this->actingAs($this->studentUser1, 'sanctum')
            ->getJson("/api/v1/exams/{$this->exam->id}/session-questions");

        $response->assertStatus(200)
            ->assertJsonPath('data.total_questions', 2)
            ->assertJsonStructure([
                'data' => [
                    'session_id',
                    'exam_id',
                    'title',
                    'remaining_seconds',
                    'questions' => [
                        '*' => [
                            'number',
                            'id',
                            'question_type',
                            'body',
                            'options',
                            'user_answer',
                            'is_flagged',
                        ],
                    ],
                ],
            ]);

        // SECURITY RULE 6: Pastikan kunci jawaban 'is_correct' TIDAK PERNAH dikirimkan ke siswa
        $rawContent = $response->getContent();
        $this->assertStringNotContainsString('"is_correct"', $rawContent);
    }

    /**
     * BE-CBT-04: Autosave jawaban per butir soal berhasil disimpan ke DB secara idempoten.
     */
    public function test_be_cbt_04_autosave_answer_success(): void
    {
        $this->app['auth']->forgetGuards();

        // Mulai sesi
        $this->actingAs($this->studentUser1, 'sanctum')
            ->postJson("/api/v1/exams/{$this->exam->id}/start-session", [
                'token' => 'SECRET',
            ]);

        // Autosave soal PG
        $response1 = $this->actingAs($this->studentUser1, 'sanctum')
            ->postJson("/api/v1/exams/{$this->exam->id}/save-answer", [
                'question_id' => $this->questionPg->id,
                'answer' => ['selected_option_id' => 'opt_mitokondria_123'],
                'is_flagged' => true,
            ]);

        $response1->assertStatus(200)
            ->assertJsonPath('data.is_saved', true)
            ->assertJsonPath('data.is_flagged', true);

        // Update autosave soal yang sama (idempoten / rewrite jawaban)
        $response2 = $this->actingAs($this->studentUser1, 'sanctum')
            ->postJson("/api/v1/exams/{$this->exam->id}/save-answer", [
                'question_id' => $this->questionPg->id,
                'answer' => ['selected_option_id' => 'opt_mitokondria_new'],
                'is_flagged' => false,
            ]);

        $response2->assertStatus(200)
            ->assertJsonPath('data.is_flagged', false);

        $session = ExamSession::where('exam_id', $this->exam->id)->where('student_id', $this->student1->id)->first();
        $saved = ExamAnswer::where('session_id', $session->id)->where('question_id', $this->questionPg->id)->first();
        $this->assertNotNull($saved);
        $this->assertEquals(['selected_option_id' => 'opt_mitokondria_new'], $saved->answer);
        $this->assertFalse($saved->is_flagged);
    }

    /**
     * BE-CBT-04: Autosave ditolak jika waktu server ujian telah habis (timeout lock).
     */
    public function test_be_cbt_04_autosave_rejected_when_deadline_passed(): void
    {
        $session = ExamSession::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student1->id,
            'status' => 'mengerjakan',
            'started_at' => Carbon::now()->subMinutes(70),
            'deadline_at' => Carbon::now()->subMinutes(10), // Timeout
            'seed' => 'testseed123',
        ]);

        $this->app['auth']->forgetGuards();

        $response = $this->actingAs($this->studentUser1, 'sanctum')
            ->postJson("/api/v1/exams/{$this->exam->id}/save-answer", [
                'question_id' => $this->questionPg->id,
                'answer' => ['value' => 'A'],
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'BUSINESS_RULE_VIOLATION');

        $session->refresh();
        $this->assertEquals('selesai', $session->status);
    }

    /**
     * BE-CBT-04: Anti-cheat mencatat pelanggaran dan mengunci sesi jika melampaui batas (3 kali).
     */
    public function test_be_cbt_04_anti_cheat_logs_violation_and_locks_session(): void
    {
        ExamSession::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student1->id,
            'status' => 'mengerjakan',
            'started_at' => Carbon::now(),
            'deadline_at' => Carbon::now()->addMinutes(50),
            'seed' => 'seedanticheat',
        ]);

        $this->app['auth']->forgetGuards();

        // Pelanggaran 1 (blur tab)
        $res1 = $this->actingAs($this->studentUser1, 'sanctum')
            ->postJson("/api/v1/exams/{$this->exam->id}/log-violation", [
                'violation_type' => 'tab_switch',
            ]);
        $res1->assertStatus(200)->assertJsonPath('data.is_locked', false);

        // Pelanggaran 2
        $res2 = $this->actingAs($this->studentUser1, 'sanctum')
            ->postJson("/api/v1/exams/{$this->exam->id}/log-violation", [
                'violation_type' => 'fullscreen_exit',
            ]);
        $res2->assertStatus(200)->assertJsonPath('data.is_locked', false);

        // Pelanggaran 3 (mencapai batas toleransi)
        $res3 = $this->actingAs($this->studentUser1, 'sanctum')
            ->postJson("/api/v1/exams/{$this->exam->id}/log-violation", [
                'violation_type' => 'blur',
            ]);
        $res3->assertStatus(200)
            ->assertJsonPath('data.is_locked', true)
            ->assertJsonPath('data.session_status', 'terkunci');

        $session = ExamSession::where('exam_id', $this->exam->id)->where('student_id', $this->student1->id)->first();
        $this->assertEquals('terkunci', $session->status);
        $this->assertNotNull($session->locked_reason);
    }

    /**
     * BE-CBT-04: Pengawas atau admin dapat membuka kunci sesi (`unlock`) dan menambah waktu ujian.
     */
    public function test_be_cbt_04_proctor_can_unlock_session_and_grant_extra_time(): void
    {
        $session = ExamSession::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student1->id,
            'status' => 'terkunci',
            'locked_reason' => 'Perpindahan layar melebihi toleransi',
            'started_at' => Carbon::now()->subMinutes(30),
            'deadline_at' => Carbon::now()->addMinutes(30),
            'seed' => 'seedlocked',
        ]);

        $this->app['auth']->forgetGuards();

        // Pengawas membuka kunci sesi dan memberikan tambahan 15 menit
        $response = $this->actingAs($this->proctorUser, 'sanctum')
            ->postJson("/api/v1/exam-sessions/{$session->id}/manage", [
                'action' => 'unlock',
                'extra_minutes' => 15,
                'reason' => 'Klarifikasi kendala teknis koneksi',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'mengerjakan')
            ->assertJsonPath('data.extra_minutes', 15);

        $session->refresh();
        $this->assertEquals('mengerjakan', $session->status);
        $this->assertNull($session->locked_reason);
        $this->assertEquals(15, $session->extra_minutes);

        $this->assertDatabaseHas('exam_proctor_actions', [
            'session_id' => $session->id,
            'actor_id' => $this->proctorUser->id,
            'action' => 'unlock',
        ]);
    }

    /**
     * BE-CBT-04: Siswa submit akhir pengerjaan ujian dan sesi dikunci secara permanen.
     */
    public function test_be_cbt_04_student_can_finish_exam_session(): void
    {
        ExamSession::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student1->id,
            'status' => 'mengerjakan',
            'started_at' => Carbon::now()->subMinutes(40),
            'deadline_at' => Carbon::now()->addMinutes(20),
            'seed' => 'seedfinish',
        ]);

        $this->app['auth']->forgetGuards();

        $response = $this->actingAs($this->studentUser1, 'sanctum')
            ->postJson("/api/v1/exams/{$this->exam->id}/finish-session");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'selesai');

        $session = ExamSession::where('exam_id', $this->exam->id)->where('student_id', $this->student1->id)->first();
        $this->assertEquals('selesai', $session->status);
        $this->assertNotNull($session->submitted_at);

        // Setelah submit, siswa tidak bisa autosave lagi
        $responseAutosave = $this->actingAs($this->studentUser1, 'sanctum')
            ->postJson("/api/v1/exams/{$this->exam->id}/save-answer", [
                'question_id' => $this->questionPg->id,
                'answer' => ['opt' => 'B'],
            ]);

        $responseAutosave->assertStatus(422)
            ->assertJsonPath('error.code', 'BUSINESS_RULE_VIOLATION');
    }
}
