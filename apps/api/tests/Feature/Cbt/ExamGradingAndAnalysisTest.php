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

class ExamGradingAndAnalysisTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $teacherUser;

    protected User $studentUser1;

    protected User $studentUser2;

    protected Student $student1;

    protected Student $student2;

    protected Exam $exam;

    protected ExamSession $session1;

    protected ExamSession $session2;

    protected Question $questionPg;

    protected Question $questionComplex;

    protected Question $questionMatching;

    protected Question $questionFill;

    protected Question $questionEssay;

    protected function setUp(): void
    {
        parent::setUp();

        $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);
        $roleAdmin = Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Admin', 'is_system' => true]);
        $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);

        $permManage = Permission::firstOrCreate(['code' => 'cbt.exam.manage'], ['description' => 'Mengelola jadwal dan sesi ujian CBT']);
        $roleAdmin->permissions()->syncWithoutDetaching([$permManage->id]);
        $roleTeacher->permissions()->syncWithoutDetaching([$permManage->id]);

        $this->adminUser = User::create([
            'username' => 'admin_cbt',
            'email' => 'admin_cbt@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($roleAdmin->id);

        $this->teacherUser = User::create([
            'username' => 'guru_cbt',
            'email' => 'guru_cbt@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->teacherUser->roles()->attach($roleTeacher->id);

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
            'user_id' => $this->teacherUser->id,
            'nip' => '198501012010011005',
            'full_name' => 'Guru Biologi Pengampu, M.Pd',
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
            'name' => 'Biologi Sel',
            'code' => 'BIO-SEL',
            'subject_group' => 'muatan_nasional',
        ]);

        $room = Room::create([
            'name' => 'Laboratorium Biologi',
            'code' => 'LAB-BIO',
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
            'created_by' => $this->teacherUser->id,
            'title' => 'Sumatif Harian Biologi Sel',
            'exam_type' => 'harian',
            'duration_min' => 60,
            'start_at' => Carbon::now()->subHours(2),
            'end_at' => Carbon::now()->addHour(),
            'passing_grade' => 70.0,
            'shuffle_questions' => false,
            'shuffle_options' => false,
            'status' => 'berlangsung',
        ]);

        $examRoom = ExamRoom::create([
            'exam_id' => $this->exam->id,
            'room_id' => $room->id,
            'proctor_id' => $this->teacherUser->id,
            'session_no' => 1,
            'token' => 'BIOCBT',
            'token_expires_at' => Carbon::now()->addMinutes(60),
        ]);

        ExamParticipant::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student1->id,
            'exam_room_id' => $examRoom->id,
        ]);

        ExamParticipant::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student2->id,
            'exam_room_id' => $examRoom->id,
        ]);

        // Buat Bank Soal
        $bank = QuestionBank::create([
            'name' => 'Bank Soal Biologi Sel Lengkap',
            'subject_id' => $subject->id,
            'grade_level_id' => $gradeLevel->id,
            'owner_id' => $this->teacherUser->id,
        ]);

        // 1. Soal PG (Bobot 2)
        $this->questionPg = Question::create([
            'bank_id' => $bank->id,
            'question_type' => 'pg',
            'body' => ['content' => 'Organel sel penghasil energi adalah?'],
            'weight' => 2.0,
            'status' => 'aktif',
        ]);
        $optMitokondria = QuestionOption::create([
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

        // 2. Soal PG Kompleks (Bobot 4, 2 benar)
        $this->questionComplex = Question::create([
            'bank_id' => $bank->id,
            'question_type' => 'pg_kompleks',
            'body' => ['content' => 'Manakah organel yang hanya ada pada sel tumbuhan?'],
            'weight' => 4.0,
            'status' => 'aktif',
        ]);
        $optDinding = QuestionOption::create([
            'question_id' => $this->questionComplex->id,
            'label' => 'A',
            'body' => ['text' => 'Dinding Sel'],
            'is_correct' => true,
        ]);
        $optKloroplas = QuestionOption::create([
            'question_id' => $this->questionComplex->id,
            'label' => 'B',
            'body' => ['text' => 'Kloroplas'],
            'is_correct' => true,
        ]);
        QuestionOption::create([
            'question_id' => $this->questionComplex->id,
            'label' => 'C',
            'body' => ['text' => 'Sentrosom'],
            'is_correct' => false,
        ]);

        // 3. Soal Menjodohkan (Bobot 4, 2 pasangan)
        $this->questionMatching = Question::create([
            'bank_id' => $bank->id,
            'question_type' => 'menjodohkan',
            'body' => ['content' => 'Jodohkan organel dan fungsinya'],
            'weight' => 4.0,
            'status' => 'aktif',
        ]);
        $optMatch1 = QuestionOption::create([
            'question_id' => $this->questionMatching->id,
            'label' => 'Kloroplas',
            'body' => ['text' => 'Kloroplas'],
            'match_key' => 'Fotosintesis',
        ]);
        $optMatch2 = QuestionOption::create([
            'question_id' => $this->questionMatching->id,
            'label' => 'Ribosom',
            'body' => ['text' => 'Ribosom'],
            'match_key' => 'Sintesis Protein',
        ]);

        // 4. Soal Isian Singkat (Bobot 2)
        $this->questionFill = Question::create([
            'bank_id' => $bank->id,
            'question_type' => 'isian',
            'body' => ['content' => 'Unit terkecil kehidupan disebut?', 'correct_answer' => 'Sel'],
            'weight' => 2.0,
            'status' => 'aktif',
        ]);

        // 5. Soal Uraian / Esai (Bobot 8)
        $this->questionEssay = Question::create([
            'bank_id' => $bank->id,
            'question_type' => 'uraian',
            'body' => ['content' => 'Jelaskan mekanisme transpor pasif pada membran sel!'],
            'weight' => 8.0,
            'status' => 'aktif',
        ]);

        // Plotting semua soal ke exam
        ExamQuestion::create(['exam_id' => $this->exam->id, 'question_id' => $this->questionPg->id, 'sort_order' => 1]);
        ExamQuestion::create(['exam_id' => $this->exam->id, 'question_id' => $this->questionComplex->id, 'sort_order' => 2]);
        ExamQuestion::create(['exam_id' => $this->exam->id, 'question_id' => $this->questionMatching->id, 'sort_order' => 3]);
        ExamQuestion::create(['exam_id' => $this->exam->id, 'question_id' => $this->questionFill->id, 'sort_order' => 4]);
        ExamQuestion::create(['exam_id' => $this->exam->id, 'question_id' => $this->questionEssay->id, 'sort_order' => 5]);

        // Buat 2 Sesi Siswa
        $this->session1 = ExamSession::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student1->id,
            'status' => 'selesai',
            'started_at' => Carbon::now()->subMinutes(50),
            'deadline_at' => Carbon::now()->addMinutes(10),
            'submitted_at' => Carbon::now()->subMinutes(5),
            'seed' => 'seed1',
        ]);

        $this->session2 = ExamSession::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student2->id,
            'status' => 'selesai',
            'started_at' => Carbon::now()->subMinutes(50),
            'deadline_at' => Carbon::now()->addMinutes(10),
            'submitted_at' => Carbon::now()->subMinutes(10),
            'seed' => 'seed2',
        ]);

        // Siswa 1 menjawab semua benar untuk objektif
        ExamAnswer::create([
            'session_id' => $this->session1->id,
            'question_id' => $this->questionPg->id,
            'answer' => ['selected_option_id' => $optMitokondria->id],
        ]);
        ExamAnswer::create([
            'session_id' => $this->session1->id,
            'question_id' => $this->questionComplex->id,
            'answer' => ['selected_option_ids' => [$optDinding->id, $optKloroplas->id]],
        ]);
        ExamAnswer::create([
            'session_id' => $this->session1->id,
            'question_id' => $this->questionMatching->id,
            'answer' => ['pairs' => [$optMatch1->id => 'Fotosintesis', $optMatch2->id => 'Sintesis Protein']],
        ]);
        ExamAnswer::create([
            'session_id' => $this->session1->id,
            'question_id' => $this->questionFill->id,
            'answer' => ['text' => '  sel  '], // Case & whitespace insensitive
        ]);
        ExamAnswer::create([
            'session_id' => $this->session1->id,
            'question_id' => $this->questionEssay->id,
            'answer' => ['text' => 'Transpor pasif meliputi difusi dan osmosis tanpa memerlukan energi ATP.'],
        ]);

        // Siswa 2 menjawab sebagian salah untuk objektif
        ExamAnswer::create([
            'session_id' => $this->session2->id,
            'question_id' => $this->questionPg->id,
            'answer' => ['value' => 'B'], // Salah
        ]);
        ExamAnswer::create([
            'session_id' => $this->session2->id,
            'question_id' => $this->questionComplex->id,
            'answer' => ['selected_option_ids' => [$optDinding->id]], // Parsial benar 1 dari 2
        ]);
        ExamAnswer::create([
            'session_id' => $this->session2->id,
            'question_id' => $this->questionMatching->id,
            'answer' => ['pairs' => [$optMatch1->id => 'Fotosintesis']], // Parsial 1 benar
        ]);
        ExamAnswer::create([
            'session_id' => $this->session2->id,
            'question_id' => $this->questionFill->id,
            'answer' => ['text' => 'Jaringan'], // Salah
        ]);
        ExamAnswer::create([
            'session_id' => $this->session2->id,
            'question_id' => $this->questionEssay->id,
            'answer' => ['text' => 'Perpindahan zat dari konsentrasi tinggi ke rendah.'],
        ]);
    }

    /**
     * BE-CBT-05: Menjalankan koreksi otomatis (auto-grade) untuk seluruh butir soal objektif.
     */
    public function test_be_cbt_05_run_auto_grading_for_objective_questions(): void
    {
        $this->app['auth']->forgetGuards();

        $response = $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson("/api/v1/exams/{$this->exam->id}/auto-grade");

        $response->assertStatus(200)
            ->assertJsonPath('data.sessions_graded', 2)
            ->assertJsonPath('data.answers_evaluated', 8); // 4 butir objektif x 2 sesi

        // Periksa skor siswa 1: PG (2.0), Complex (4.0), Matching (4.0), Isian (2.0)
        $ansPg1 = ExamAnswer::where('session_id', $this->session1->id)->where('question_id', $this->questionPg->id)->first();
        $this->assertEquals(2.0, (float) $ansPg1->score);

        $ansCmplx1 = ExamAnswer::where('session_id', $this->session1->id)->where('question_id', $this->questionComplex->id)->first();
        $this->assertEquals(4.0, (float) $ansCmplx1->score);

        $ansMatch1 = ExamAnswer::where('session_id', $this->session1->id)->where('question_id', $this->questionMatching->id)->first();
        $this->assertEquals(4.0, (float) $ansMatch1->score);

        $ansFill1 = ExamAnswer::where('session_id', $this->session1->id)->where('question_id', $this->questionFill->id)->first();
        $this->assertEquals(2.0, (float) $ansFill1->score);

        // Periksa skor siswa 2: PG salah (0.0), Complex parsial (2.0), Matching parsial (2.0), Isian salah (0.0)
        $ansPg2 = ExamAnswer::where('session_id', $this->session2->id)->where('question_id', $this->questionPg->id)->first();
        $this->assertEquals(0.0, (float) $ansPg2->score);

        $ansCmplx2 = ExamAnswer::where('session_id', $this->session2->id)->where('question_id', $this->questionComplex->id)->first();
        $this->assertEquals(2.0, (float) $ansCmplx2->score);

        $ansMatch2 = ExamAnswer::where('session_id', $this->session2->id)->where('question_id', $this->questionMatching->id)->first();
        $this->assertEquals(2.0, (float) $ansMatch2->score);

        $ansFill2 = ExamAnswer::where('session_id', $this->session2->id)->where('question_id', $this->questionFill->id)->first();
        $this->assertEquals(0.0, (float) $ansFill2->score);
    }

    /**
     * BE-CBT-05: Mengambil lembar jawaban esai/uraian yang belum dan sudah dinilai.
     */
    public function test_be_cbt_05_get_essay_submissions_for_teacher_review(): void
    {
        $this->app['auth']->forgetGuards();

        $response = $this->actingAs($this->teacherUser, 'sanctum')
            ->getJson("/api/v1/exams/{$this->exam->id}/essay-submissions");

        $response->assertStatus(200)
            ->assertJsonPath('data.total_essays', 2)
            ->assertJsonPath('data.ungraded_count', 2)
            ->assertJsonStructure([
                'data' => [
                    'total_essays',
                    'ungraded_count',
                    'submissions' => [
                        '*' => [
                            'answer_id',
                            'session_id',
                            'student_name',
                            'question_id',
                            'max_score',
                            'student_answer',
                        ],
                    ],
                ],
            ]);
    }

    /**
     * BE-CBT-05: Guru memberikan skor manual untuk soal uraian/esai dan total score sesi terupdate.
     */
    public function test_be_cbt_05_teacher_can_grade_essay_answer_and_updates_session_score(): void
    {
        // Jalankan auto-grade dulu
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson("/api/v1/exams/{$this->exam->id}/auto-grade");

        $essayAns1 = ExamAnswer::where('session_id', $this->session1->id)
            ->where('question_id', $this->questionEssay->id)
            ->first();

        // Guru memberi nilai 8.0 (skor maksimal esai)
        $response = $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson("/api/v1/exams/{$this->exam->id}/grade-essay", [
                'answer_id' => $essayAns1->id,
                'score' => 8.0,
                'feedback' => 'Penjelasan lengkap dan tepat.',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.score', 8)
            ->assertJsonPath('data.max_score', 8);

        // Siswa 1 mendapat nilai penuh: totalEarned = 2 + 4 + 4 + 2 + 8 = 20 dari totalMax 20 => 100.0
        $this->assertEquals(100.0, (float) $this->session1->fresh()->score);
    }

    /**
     * BE-CBT-05: Validasi batas maksimal skor manual soal uraian (tidak boleh melebihi bobot soal).
     */
    public function test_be_cbt_05_grade_essay_validates_score_within_bounds(): void
    {
        $essayAns1 = ExamAnswer::where('session_id', $this->session1->id)
            ->where('question_id', $this->questionEssay->id)
            ->first();

        $this->app['auth']->forgetGuards();

        // Bobot esai adalah 8.0, guru mencoba memberi 15.0
        $response = $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson("/api/v1/exams/{$this->exam->id}/grade-essay", [
                'answer_id' => $essayAns1->id,
                'score' => 15.0,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'BUSINESS_RULE_VIOLATION');
    }

    /**
     * BE-CBT-05: Rekapitulasi hasil nilai akhir ujian seluruh peserta (tuntas vs belum tuntas).
     */
    public function test_be_cbt_05_get_exam_results_summary_and_passing_status(): void
    {
        // Siswa 1 nilai 100, Siswa 2 nilai 50
        $this->session1->update(['score' => 100.0]);
        $this->session2->update(['score' => 50.0]);

        $this->app['auth']->forgetGuards();

        $response = $this->actingAs($this->teacherUser, 'sanctum')
            ->getJson("/api/v1/exams/{$this->exam->id}/results");

        $response->assertStatus(200)
            ->assertJsonPath('data.passing_grade', 70)
            ->assertJsonPath('data.summary.total_participants', 2)
            ->assertJsonPath('data.summary.passed_participants', 1)
            ->assertJsonPath('data.summary.failed_participants', 1)
            ->assertJsonPath('data.summary.average_score', 75);

        // Siswa 1 tuntas
        $res1 = collect($response->json('data.results'))->firstWhere('student_id', $this->student1->id);
        $this->assertTrue($res1['is_passed']);

        // Siswa 2 tidak tuntas (di bawah 70)
        $res2 = collect($response->json('data.results'))->firstWhere('student_id', $this->student2->id);
        $this->assertFalse($res2['is_passed']);
    }

    /**
     * BE-CBT-05: Analisis butir soal (Item Analysis) menghitung tingkat kesukaran & daya pembeda.
     */
    public function test_be_cbt_05_item_analysis_calculates_difficulty_and_discrimination(): void
    {
        // Jalankan auto-grade
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson("/api/v1/exams/{$this->exam->id}/auto-grade");

        $response = $this->actingAs($this->teacherUser, 'sanctum')
            ->getJson("/api/v1/exams/{$this->exam->id}/item-analysis");

        $response->assertStatus(200)
            ->assertJsonPath('data.total_analyzed_questions', 5)
            ->assertJsonStructure([
                'data' => [
                    'exam_id',
                    'exam_title',
                    'analysis' => [
                        '*' => [
                            'question_id',
                            'difficulty_index',
                            'difficulty_category',
                            'discrimination_index',
                            'discrimination_category',
                            'distractor_stats',
                        ],
                    ],
                ],
            ]);

        // Pada soal PG: Siswa 1 benar (skor 2), Siswa 2 salah (skor 0)
        // Tingkat kesukaran p = (2 + 0) / (2 * 2.0) = 0.500 (Sedang)
        $itemPg = collect($response->json('data.analysis'))->firstWhere('question_id', $this->questionPg->id);
        $this->assertEquals(0.5, (float) $itemPg['difficulty_index']);
        $this->assertEquals('Sedang', $itemPg['difficulty_category']);
        $this->assertNotEmpty($itemPg['distractor_stats']);

        // Pastikan tersimpan di DB exam_item_analysis
        $this->assertDatabaseHas('exam_item_analysis', [
            'exam_id' => $this->exam->id,
            'question_id' => $this->questionPg->id,
        ]);
    }

    /**
     * BE-CBT-05: Siswa dilarang mengakses endpoint grading ujian (403 Forbidden).
     */
    public function test_be_cbt_05_student_forbidden_to_access_grading_endpoints(): void
    {
        $this->app['auth']->forgetGuards();

        $response = $this->actingAs($this->studentUser1, 'sanctum')
            ->postJson("/api/v1/exams/{$this->exam->id}/auto-grade");

        $response->assertStatus(403);
    }
}
