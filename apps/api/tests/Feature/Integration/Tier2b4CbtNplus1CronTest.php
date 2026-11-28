<?php

namespace Tests\Feature\Integration;

use App\Jobs\ParseQuestionImportJob;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\ClassStudent;
use App\Models\Exam;
use App\Models\ExamAnswer;
use App\Models\ExamParticipant;
use App\Models\ExamQuestion;
use App\Models\ExamRoom;
use App\Models\ExamSession;
use App\Models\FeeType;
use App\Models\File;
use App\Models\Grade;
use App\Models\GradeComponent;
use App\Models\GradeLevel;
use App\Models\GradePeriod;
use App\Models\Guardian;
use App\Models\LeaveRequest;
use App\Models\Notification;
use App\Models\Permission;
use App\Models\PointType;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\QuestionOption;
use App\Models\ReportDescriptionTemplate;
use App\Models\Role;
use App\Models\Room;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentPoint;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use App\Services\AcademicAttendanceService;
use App\Services\Bk\EarlyWarningService;
use App\Services\Cbt\DocxQuestionParser;
use App\Services\Cbt\ExamGradingService;
use App\Services\Cbt\ExamSessionService;
use App\Services\Cbt\QuestionImportService;
use App\Services\Finance\InvoiceGeneratorService;
use App\Services\ReportCardService;
use App\Services\Rpt\ApprovalService;
use App\Services\Storage\OrphanFileService;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class Tier2b4CbtNplus1CronTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create([
            'name' => 'SMP Lab Tier2B4',
            'npsn' => '20534299',
            'address' => 'Jl. Fiktif T2B4 No. 1',
            'phone' => '0341000099',
            'email' => 't2b4@test.local',
        ]);

        $adminRole = Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Admin', 'is_system' => true]);
        foreach (['cbt.exam.manage', 'cbt.bank.write', 'cbt.proctor.action', 'academic.report.generate', 'academic.attendance.manage', 'finance.invoice.manage', 'bk.counseling.manage'] as $permCode) {
            $perm = Permission::firstOrCreate(['code' => $permCode], ['description' => $permCode]);
            $adminRole->permissions()->syncWithoutDetaching([$perm->id]);
        }

        $this->adminUser = User::create([
            'school_id' => $this->school->id,
            'username' => 'admin_t2b4_'.uniqid(),
            'email' => 'admin_t2b4_'.uniqid().'@test.local',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($adminRole->id);
    }

    public function test_p1_12_cbt_session_lifecycle_finalizes_grades_and_aligns_blur_counters(): void
    {
        $studentRole = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);

        $studentUser1 = User::create([
            'school_id' => $this->school->id,
            'username' => 'siswa_t2b4_1_'.uniqid(),
            'email' => 'siswa1_t2b4_'.uniqid().'@test.local',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $studentUser1->roles()->attach($studentRole->id);

        $student1 = Student::create([
            'school_id' => $this->school->id,
            'user_id' => $studentUser1->id,
            'nis' => 'T2B4-001',
            'nisn' => '0011223301',
            'nik_enc' => 'enc_t2b4_01',
            'full_name' => 'Siswa Uji Satu',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2011-01-10',
            'entry_date' => '2026-07-01',
            'status' => 'aktif',
        ]);

        $studentUser2 = User::create([
            'school_id' => $this->school->id,
            'username' => 'siswa_t2b4_2_'.uniqid(),
            'email' => 'siswa2_t2b4_'.uniqid().'@test.local',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $studentUser2->roles()->attach($studentRole->id);

        $student2 = Student::create([
            'school_id' => $this->school->id,
            'user_id' => $studentUser2->id,
            'nis' => 'T2B4-002',
            'nisn' => '0011223302',
            'nik_enc' => 'enc_t2b4_02',
            'full_name' => 'Siswa Uji Dua',
            'gender' => 'P',
            'birth_place' => 'Malang',
            'birth_date' => '2011-02-11',
            'entry_date' => '2026-07-01',
            'status' => 'aktif',
        ]);

        $subject = Subject::create([
            'code' => 'CBT-T2B4',
            'name' => 'IPA Terpadu',
            'subject_group' => 'wajib',
        ]);

        $gradeLevel = GradeLevel::create([
            'name' => 'Kelas 7 T2B4',
            'sort_order' => 7,
        ]);

        $bank = QuestionBank::create([
            'school_id' => $this->school->id,
            'name' => 'Bank CBT T2B4',
            'subject_id' => $subject->id,
            'grade_level_id' => $gradeLevel->id,
            'owner_id' => $this->adminUser->id,
        ]);

        $question = Question::create([
            'bank_id' => $bank->id,
            'question_type' => 'pg',
            'body' => ['content' => 'Air mendidih pada suhu berapa derajat Celsius?'],
            'weight' => 10.0,
            'version' => 1,
            'status' => 'aktif',
        ]);

        QuestionOption::create([
            'question_id' => $question->id,
            'label' => 'A',
            'body' => ['text' => '100'],
            'is_correct' => true,
        ]);

        QuestionOption::create([
            'question_id' => $question->id,
            'label' => 'B',
            'body' => ['text' => '50'],
            'is_correct' => false,
        ]);

        $exam = Exam::create([
            'school_id' => $this->school->id,
            'subject_id' => $subject->id,
            'created_by' => $this->adminUser->id,
            'title' => 'Ujian Lifecycle T2B4',
            'exam_type' => 'STS',
            'duration_min' => 60,
            'start_at' => Carbon::now()->subMinutes(10),
            'end_at' => Carbon::now()->addHours(2),
            'passing_grade' => 75.0,
            'status' => 'berlangsung',
        ]);

        $room = Room::create([
            'code' => 'R-T2B4',
            'name' => 'Ruang T2B4',
            'room_type' => 'kelas',
            'capacity' => 30,
        ]);

        $examRoom = ExamRoom::create([
            'exam_id' => $exam->id,
            'room_id' => $room->id,
            'proctor_id' => $this->adminUser->id,
            'session_no' => 1,
            'token' => 'T2B4OK',
            'token_expires_at' => Carbon::now()->addHours(2),
        ]);

        ExamQuestion::create([
            'exam_id' => $exam->id,
            'question_id' => $question->id,
            'sort_order' => 1,
            'package_code' => 'A',
        ]);

        ExamParticipant::create([
            'exam_id' => $exam->id,
            'student_id' => $student1->id,
            'exam_room_id' => $examRoom->id,
            'package_code' => 'A',
        ]);

        ExamParticipant::create([
            'exam_id' => $exam->id,
            'student_id' => $student2->id,
            'exam_room_id' => $examRoom->id,
            'package_code' => 'A',
        ]);

        $session1 = ExamSession::create([
            'exam_id' => $exam->id,
            'student_id' => $student1->id,
            'status' => 'mengerjakan',
            'started_at' => Carbon::now()->subMinutes(5),
            'ends_at' => Carbon::now()->addMinutes(55),
            'deadline_at' => Carbon::now()->addMinutes(55),
            'seed' => 'seed_t2b4_1',
            'violation_count' => 0,
            'blur_count' => 0,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit-T2B4',
        ]);

        // 1. Autosave dalam transaksi + lockForUpdate()
        $saveRes = $this->actingAs($studentUser1, 'sanctum')
            ->postJson("/api/v1/exams/{$exam->id}/save-answer", [
                'question_id' => $question->id,
                'answer' => 'A',
            ]);
        $saveRes->assertStatus(200)->assertJsonPath('data.is_saved', true);

        // 2. Heartbeat blur menaikkan blur_count DAN violation_count secara konsisten
        $hbRes = $this->actingAs($studentUser1, 'sanctum')
            ->postJson("/api/v1/exam-sessions/{$session1->id}/heartbeat", [
                'screen_state' => 'blur',
                'active_tab' => false,
            ], [
                'REMOTE_ADDR' => '127.0.0.1',
                'User-Agent' => 'PHPUnit-T2B4',
            ]);
        $hbRes->assertStatus(200)
            ->assertJsonPath('data.blur_count', 1)
            ->assertJsonPath('data.violation_count', 1);

        // 3. Log violation bertipe blur juga menaikkan blur_count & violation_count
        $violRes = $this->actingAs($studentUser1, 'sanctum')
            ->postJson("/api/v1/exams/{$exam->id}/log-violation", [
                'violation_type' => 'blur',
            ]);
        $violRes->assertStatus(200)
            ->assertJsonPath('data.violation_count', 2)
            ->assertJsonPath('data.blur_count', 2);

        // 4. Proctor force_submit mengeksekusi finalizeSession() -> status selesai + nilai objektif terhitung
        $forceRes = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/exam-sessions/{$session1->id}/manage", [
                'action' => 'force_submit',
                'reason' => 'Selesai uji force_submit',
            ]);
        $forceRes->assertStatus(200)->assertJsonPath('data.status', 'selesai');

        $session1->refresh();
        $this->assertEquals('selesai', $session1->status);
        $this->assertNotNull($session1->submitted_at);
        $this->assertEquals(100.0, (float) $session1->score);

        // 5. Auto-disqualify via logViolation juga menilai jawaban objektif + finalizeSession()
        $session2 = ExamSession::create([
            'exam_id' => $exam->id,
            'student_id' => $student2->id,
            'status' => 'mengerjakan',
            'started_at' => Carbon::now()->subMinutes(5),
            'ends_at' => Carbon::now()->addMinutes(55),
            'deadline_at' => Carbon::now()->addMinutes(55),
            'seed' => 'seed_t2b4_2',
            'violation_count' => 4,
            'blur_count' => 0,
        ]);

        ExamAnswer::create([
            'session_id' => $session2->id,
            'question_id' => $question->id,
            'answer' => 'A',
            'is_flagged' => false,
            'answered_at' => Carbon::now(),
        ]);

        $dqRes = $this->actingAs($studentUser2, 'sanctum')
            ->postJson("/api/v1/exams/{$exam->id}/log-violation", [
                'violation_type' => 'tab_switch',
            ]);
        $dqRes->assertStatus(200)
            ->assertJsonPath('data.consequence', 'disqualified')
            ->assertJsonPath('data.session_status', 'selesai');

        $session2->refresh();
        $this->assertEquals('selesai', $session2->status);
        $this->assertEquals(100.0, (float) $session2->score);

        // 6. Timeout di saveAnswer() mem-persist status selesai + nilai objektif meski melempar 422 EXAM_TIME_OVER
        $session2->update([
            'status' => 'mengerjakan',
            'score' => null,
            'submitted_at' => null,
            'ends_at' => Carbon::now()->subMinutes(2),
            'deadline_at' => Carbon::now()->subMinutes(2),
        ]);

        $req = Request::create("/api/v1/exams/{$exam->id}/answers", 'POST', [
            'question_id' => $question->id,
            'answer' => 'A',
        ]);
        $req->setUserResolver(fn () => $studentUser2);

        $thrown = false;
        try {
            app(ExamSessionService::class)->saveAnswer($exam->id, [
                'question_id' => $question->id,
                'answer' => 'A',
            ], $req);
        } catch (HttpException $e) {
            $thrown = true;
            $this->assertEquals(422, $e->getStatusCode());
        }
        $this->assertTrue($thrown);

        $session2->refresh();
        $this->assertEquals('selesai', $session2->status);
        $this->assertEquals(100.0, (float) $session2->score);
    }

    public function test_p1_13_bs_key_b_marks_only_salah_and_update_draft_validates_all_types_and_commit_idempotent(): void
    {
        $subject = Subject::create([
            'code' => 'MAT-IMPORT-T2B4',
            'name' => 'Matematika Import',
            'subject_group' => 'wajib',
        ]);

        $gradeLevel = GradeLevel::create([
            'name' => 'Kelas 8 T2B4',
            'sort_order' => 8,
        ]);

        $bank = QuestionBank::create([
            'school_id' => $this->school->id,
            'subject_id' => $subject->id,
            'grade_level_id' => $gradeLevel->id,
            'owner_id' => $this->adminUser->id,
            'name' => 'Bank Import T2B4',
        ]);

        // 1. Verifikasi bug fix [BS] KUNCI: B tanpa opsi eksplisit A/B
        $phpWord = new PhpWord;
        $section = $phpWord->addSection();
        foreach ([
            '1. [BS] [BOBOT: 2] [LEVEL: LOTS]',
            'Bumi berbentuk kubus.',
            'KUNCI: B',
        ] as $line) {
            $section->addText($line);
        }
        $tempPath = tempnam(sys_get_temp_dir(), 'bs_t2b4_').'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($tempPath);

        $parsed = app(DocxQuestionParser::class)->parse($tempPath);
        $this->assertCount(0, $parsed['errors']);
        $this->assertCount(1, $parsed['questions']);
        $bsOpts = $parsed['questions'][0]['options'];
        $this->assertFalse($bsOpts[0]['is_correct'], 'Opsi A Benar tidak boleh benar saat KUNCI: B');
        $this->assertTrue($bsOpts[1]['is_correct'], 'Opsi B Salah harus benar saat KUNCI: B');

        // 2. Upload DOCX via endpoint sync default & test updateDraft validasi semua 6 tipe
        $uploadedFile = new UploadedFile(
            $tempPath,
            'bs_test.docx',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            null,
            true
        );

        $uploadRes = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/question-imports', [
                'bank_id' => $bank->id,
                'file' => $uploadedFile,
            ]);

        $uploadRes->assertStatus(201)->assertJsonPath('data.status', 'parsed');
        $jobId = $uploadRes->json('data.id');

        // Patch draft dengan error pada pg_kompleks, menjodohkan, dan isian via HTTP
        $badPatch = $this->actingAs($this->adminUser)
            ->patchJson("/api/v1/question-imports/{$jobId}", [
                'questions' => [
                    [
                        'question_type' => 'pg_kompleks',
                        'body' => ['content' => 'Pilih 2'],
                        'options' => [
                            ['label' => 'A', 'body' => ['text' => 'Satu'], 'is_correct' => false],
                        ],
                    ],
                    [
                        'question_type' => 'menjodohkan',
                        'body' => ['content' => 'Pasangkan'],
                        'options' => [
                            ['label' => 'A', 'body' => ['text' => 'Ibu Kota'], 'match_key' => null],
                        ],
                    ],
                    [
                        'question_type' => 'isian',
                        'body' => ['content' => 'Isi titik'],
                        'options' => [],
                    ],
                ],
            ]);

        $badPatch->assertStatus(200);
        $this->assertCount(3, $badPatch->json('data.parse_errors'));

        // Uji juga validasi tipe uraian ber-stem kosong langsung pada service updateDraft
        $uraianJob = app(QuestionImportService::class)->updateDraft($jobId, [
            [
                'question_type' => 'uraian',
                'body' => ['content' => '   '],
                'options' => [],
            ],
        ], $this->adminUser);
        $this->assertCount(1, $uraianJob->parse_errors);

        // Patch draft valid lalu commit dua kali secara idempoten
        $goodPatch = $this->actingAs($this->adminUser)
            ->patchJson("/api/v1/question-imports/{$jobId}", [
                'questions' => [$parsed['questions'][0]],
            ]);
        $goodPatch->assertStatus(200);
        $this->assertCount(0, $goodPatch->json('data.parse_errors'));

        $commit1 = $this->actingAs($this->adminUser)
            ->postJson("/api/v1/question-imports/{$jobId}/commit", [], [
                'Idempotency-Key' => 't2b4-idem-1',
            ]);
        $commit1->assertStatus(200)->assertJsonPath('data.imported_count', 1);

        $commit2 = $this->actingAs($this->adminUser)
            ->postJson("/api/v1/question-imports/{$jobId}/commit", [], [
                'Idempotency-Key' => 't2b4-idem-2',
            ]);
        $commit2->assertStatus(200)->assertJsonPath('data.imported_count', 1);

        $this->assertEquals(1, Question::where('bank_id', $bank->id)->count());

        // 3. Verifikasi mode CBT_IMPORT_ASYNC mengirim job ke queue tanpa dispatchSync
        Queue::fake();
        config(['cbt.import_async' => true]);

        $tempPath2 = tempnam(sys_get_temp_dir(), 'bs_async_').'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($tempPath2);
        $asyncFile = new UploadedFile(
            $tempPath2,
            'bs_async.docx',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            null,
            true
        );

        $asyncRes = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/question-imports', [
                'bank_id' => $bank->id,
                'file' => $asyncFile,
            ]);
        $asyncRes->assertStatus(201)->assertJsonPath('data.status', 'queued');
        Queue::assertPushed(ParseQuestionImportJob::class);

        config(['cbt.import_async' => false]);
        @unlink($tempPath);
        @unlink($tempPath2);
    }

    public function test_p0_31_n_plus_one_batching_and_scheduler_on_one_server(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        // 1. Scheduler: seluruh cron di routes/console.php wajib menggunakan onOneServer()
        $schedule = app(Schedule::class);
        $events = collect($schedule->events())->filter(fn ($e) => str_contains((string) $e->command, ':'));
        $this->assertGreaterThanOrEqual(11, $events->count());
        foreach ($events as $event) {
            $this->assertTrue((bool) $event->onOneServer, "Schedule {$event->command} wajib memakai onOneServer()");
        }

        // 2. Setup 8 siswa + relasi untuk uji batas query konstan (O(1) batching, bukan O(N))
        $ay = AcademicYear::create([
            'school_id' => $this->school->id,
            'name' => '2026/2027 T2B4',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'status' => 'active',
        ]);
        $semester = Semester::create([
            'academic_year_id' => $ay->id,
            'name' => 'Ganjil T2B4',
            'start_date' => '2026-07-01',
            'end_date' => '2026-12-31',
            'is_active' => true,
        ]);
        $period = GradePeriod::create([
            'semester_id' => $semester->id,
            'month' => 11,
            'status' => 'open',
        ]);
        $gradeLevel = GradeLevel::create([
            'name' => 'Kelas 9 T2B4',
            'sort_order' => 9,
        ]);
        $class = SchoolClass::create([
            'school_id' => $this->school->id,
            'academic_year_id' => $ay->id,
            'grade_level_id' => $gradeLevel->id,
            'name' => 'IX-T2B4',
            'capacity' => 36,
        ]);

        $subject1 = Subject::create(['code' => 'SUB1-T2B4', 'name' => 'Mapel 1', 'subject_group' => 'wajib']);
        $subject2 = Subject::create(['code' => 'SUB2-T2B4', 'name' => 'Mapel 2', 'subject_group' => 'wajib']);
        $comp1 = GradeComponent::create([
            'subject_id' => $subject1->id,
            'semester_id' => $semester->id,
            'class_id' => $class->id,
            'code' => 'CMP1',
            'name' => 'Tugas',
            'weight' => 100.0,
        ]);
        $comp2 = GradeComponent::create([
            'subject_id' => $subject2->id,
            'semester_id' => $semester->id,
            'class_id' => $class->id,
            'code' => 'CMP2',
            'name' => 'UTS',
            'weight' => 100.0,
        ]);

        ReportDescriptionTemplate::create([
            'subject_id' => $subject1->id,
            'level' => 'tuntas',
            'template_text' => 'Tuntas Mapel 1',
        ]);
        ReportDescriptionTemplate::create([
            'subject_id' => $subject2->id,
            'level' => 'tuntas',
            'template_text' => 'Tuntas Mapel 2',
        ]);

        $pointType = PointType::create([
            'name' => 'Terlambat Berat T2B4',
            'kind' => 'pelanggaran',
            'points' => 25,
        ]);

        $feeType = FeeType::create([
            'school_id' => $this->school->id,
            'code' => 'SPP-T2B4',
            'name' => 'SPP T2B4',
            'category' => 'spp',
            'default_amount' => 300000,
            'is_recurring' => true,
            'is_active' => true,
        ]);

        $guardianRole = Role::firstOrCreate(['code' => 'guardian'], ['name' => 'Orang Tua', 'is_system' => true]);

        for ($i = 1; $i <= 8; $i++) {
            $gUser = User::create([
                'school_id' => $this->school->id,
                'username' => "ortu_batch_{$i}_".uniqid(),
                'email' => "ortu_batch_{$i}_".uniqid().'@test.local',
                'password_hash' => Hash::make('Password123!'),
                'status' => 'active',
            ]);
            $gUser->roles()->attach($guardianRole->id);
            $guardian = Guardian::create([
                'user_id' => $gUser->id,
                'full_name' => "Wali Batch {$i}",
                'relationship' => 'ayah',
                'phone' => '0812000100'.$i,
            ]);

            $s = Student::create([
                'school_id' => $this->school->id,
                'nis' => "BATCH-{$i}",
                'nisn' => '008800000'.$i,
                'nik_enc' => "enc_batch_{$i}",
                'full_name' => "Siswa Batch {$i}",
                'gender' => 'L',
                'birth_place' => 'Malang',
                'birth_date' => '2011-05-05',
                'entry_date' => '2026-07-01',
                'status' => $i % 2 === 0 ? 'active' : 'aktif',
            ]);
            ClassStudent::create([
                'class_id' => $class->id,
                'student_id' => $s->id,
                'joined_at' => Carbon::now()->subMonths(2),
            ]);
            $guardian->students()->attach($s->id, ['relationship' => 'ayah', 'is_primary' => true]);

            for ($d = 1; $d <= 3; $d++) {
                Attendance::create([
                    'student_id' => $s->id,
                    'att_date' => Carbon::today()->subDays($d)->format('Y-m-d'),
                    'source' => 'gerbang',
                    'status' => 'alpa',
                ]);
            }

            Grade::create([
                'student_id' => $s->id,
                'component_id' => $comp1->id,
                'period_id' => $period->id,
                'value' => 88.0,
                'source' => 'manual',
                'entered_by' => $this->adminUser->id,
            ]);
            Grade::create([
                'student_id' => $s->id,
                'component_id' => $comp2->id,
                'period_id' => $period->id,
                'value' => 84.0,
                'source' => 'manual',
                'entered_by' => $this->adminUser->id,
            ]);

            StudentPoint::create([
                'student_id' => $s->id,
                'point_type_id' => $pointType->id,
                'recorded_by' => $this->adminUser->id,
                'point_date' => Carbon::today(),
            ]);
        }

        // 3. EarlyWarningService batch evaluation query count bound
        DB::flushQueryLog();
        DB::enableQueryLog();
        $ewResult = app(EarlyWarningService::class)->evaluateRules(['school_id' => $this->school->id]);
        $ewQueries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertEquals(8, $ewResult['students_evaluated']);
        // 8 siswa x 2 alert (16 inserts) + ~8 preload/transaction queries << N*8 queries sebelum fix
        $this->assertLessThanOrEqual(32, $ewQueries, "EarlyWarningService terlalu banyak query: {$ewQueries}");

        // 4. ReportCardService batch generation query count bound
        $reqRc = Request::create('/api/v1/report-cards/generate', 'POST');
        $reqRc->setUserResolver(fn () => $this->adminUser);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $rcResults = app(ReportCardService::class)->generateReportCards([
            'semester_id' => $semester->id,
            'class_id' => $class->id,
            'status' => 'draft',
        ], $reqRc);
        $rcQueries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertCount(8, $rcResults);
        // 8 siswa hanya ~1 preload batch + 8 save/audit/fresh, bebas N+1 compileSemesterSnapshot
        $this->assertLessThanOrEqual(52, $rcQueries, "ReportCardService terlalu banyak query: {$rcQueries}");

        // 5. InvoiceGeneratorService::generateBulk batching
        DB::flushQueryLog();
        DB::enableQueryLog();
        $invResult = app(InvoiceGeneratorService::class)->generateBulk([
            'fee_type_id' => $feeType->id,
            'target_type' => 'class',
            'class_id' => $class->id,
            'period' => '2026-11',
            'due_date' => '2026-11-30',
        ], $this->adminUser);
        $invQueries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertEquals(8, $invResult['created_count']);
        $this->assertEquals(8, Notification::where('notif_type', 'finance_invoice')->count());
        // Bebas N+1 Invoice::whereHas, InvoiceItem::create, Notification::create per siswa
        $this->assertLessThanOrEqual(35, $invQueries, "InvoiceGeneratorService terlalu banyak query: {$invQueries}");

        // 6. ExamGradingService::getEssaySubmissions memuat relasi grader + refreshSessionTotalScore memakai relasi loaded
        $essayQ = Question::create([
            'bank_id' => QuestionBank::create([
                'school_id' => $this->school->id,
                'subject_id' => $subject1->id,
                'grade_level_id' => $gradeLevel->id,
                'owner_id' => $this->adminUser->id,
                'name' => 'Bank Esai T2B4',
            ])->id,
            'question_type' => 'uraian',
            'body' => ['content' => 'Jelaskan fotosintesis'],
            'weight' => 10.0,
            'version' => 1,
            'status' => 'aktif',
        ]);
        $essayExam = Exam::create([
            'school_id' => $this->school->id,
            'subject_id' => $subject1->id,
            'created_by' => $this->adminUser->id,
            'title' => 'Ujian Esai T2B4',
            'exam_type' => 'STS',
            'duration_min' => 60,
            'start_at' => Carbon::now()->subMinutes(15),
            'end_at' => Carbon::now()->addHours(1),
            'status' => 'berlangsung',
        ]);
        ExamQuestion::create([
            'exam_id' => $essayExam->id,
            'question_id' => $essayQ->id,
            'sort_order' => 1,
        ]);
        $studentFirst = Student::where('nis', 'BATCH-1')->firstOrFail();
        $essaySession = ExamSession::create([
            'exam_id' => $essayExam->id,
            'student_id' => $studentFirst->id,
            'status' => 'selesai',
            'started_at' => Carbon::now()->subMinutes(10),
            'deadline_at' => Carbon::now(),
            'submitted_at' => Carbon::now(),
            'seed' => 'essay_t2b4',
        ]);
        ExamAnswer::create([
            'session_id' => $essaySession->id,
            'question_id' => $essayQ->id,
            'answer' => 'Proses tumbuhan membuat makanan.',
            'score' => 8.0,
            'graded_by' => $this->adminUser->id,
            'answered_at' => Carbon::now()->subMinute(),
        ]);

        $gradingReq = Request::create("/api/v1/exams/{$essayExam->id}/essays", 'GET');
        $gradingReq->setUserResolver(fn () => $this->adminUser);
        $essaysRes = app(ExamGradingService::class)->getEssaySubmissions($essayExam->id, [], $gradingReq);
        $this->assertEquals($this->adminUser->name, $essaysRes['submissions'][0]['graded_by']);

        $essaySessionLoaded = ExamSession::with('answers')->findOrFail($essaySession->id);
        DB::flushQueryLog();
        DB::enableQueryLog();
        app(ExamGradingService::class)->refreshSessionTotalScore($essaySessionLoaded);
        $refreshQueries = collect(DB::getQueryLog())->pluck('query')->implode("\n");
        DB::disableQueryLog();
        $this->assertStringNotContainsString('from "exam_answers"', strtolower($refreshQueries));

        // 7. OrphanFileService::purgeOrphans memeriksa referensi secara batch bukan per file
        $orphanIds = [];
        for ($k = 1; $k <= 4; $k++) {
            $key = "private/orphans/t2b4_{$k}.txt";
            Storage::disk('local')->put($key, 'orphan content');
            $f = File::create([
                'school_id' => $this->school->id,
                'bucket' => 'local',
                'object_key' => $key,
                'mime_type' => 'text/plain',
                'size_bytes' => 14,
                'visibility' => 'privat',
            ]);
            $f->timestamps = false;
            $f->created_at = Carbon::now()->subHours(48);
            $f->save();
            $orphanIds[] = $f->id;
        }

        $orphanService = app(OrphanFileService::class);
        $purgeRes = $orphanService->purgeOrphans($orphanIds, $this->adminUser);
        $this->assertEquals(4, $purgeRes['purged_count']);
        $this->assertEquals(0, File::whereIn('id', $orphanIds)->count());
    }

    public function test_p1_27_daily_summary_centralizes_early_warning_and_leave_autofill_skips_weekends(): void
    {
        // 1. Setup student dengan status 'aktif' & 1 hari gerbang alpa + multi kelas alpa (= 1 hari) + 2 hari alpa lain (= 3 hari)
        $teacherUser = User::create([
            'school_id' => $this->school->id,
            'username' => 'walas_p1_27_'.uniqid(),
            'email' => 'walas_p1_27_'.uniqid().'@test.local',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $teacherRole = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);
        $principalRole = Role::firstOrCreate(['code' => 'principal'], ['name' => 'Kepala Sekolah', 'is_system' => true]);
        $leavePerm = Permission::firstOrCreate(['code' => 'academic.attendance.manage'], ['description' => 'Kelola presensi']);
        $apprPerm = Permission::firstOrCreate(['code' => 'rpt.approval.manage'], ['description' => 'Kelola persetujuan']);
        $teacherRole->permissions()->syncWithoutDetaching([$leavePerm->id]);
        $principalRole->permissions()->syncWithoutDetaching([$apprPerm->id]);
        $teacherUser->roles()->attach($teacherRole->id);

        $principalUser = User::create([
            'school_id' => $this->school->id,
            'username' => 'kepsek_p1_27_'.uniqid(),
            'email' => 'kepsek_p1_27_'.uniqid().'@test.local',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $principalUser->roles()->attach($principalRole->id);

        $teacher = Teacher::create([
            'user_id' => $teacherUser->id,
            'nip' => '198801012026011099',
            'full_name' => 'Wali Kelas P127',
            'status' => 'aktif',
        ]);
        $ay = AcademicYear::create([
            'school_id' => $this->school->id,
            'name' => '2026/2027 P127',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'status' => 'active',
        ]);
        $gradeLevel = GradeLevel::create(['name' => 'Kelas 7 P127', 'sort_order' => 7]);
        $class = SchoolClass::create([
            'school_id' => $this->school->id,
            'academic_year_id' => $ay->id,
            'grade_level_id' => $gradeLevel->id,
            'homeroom_teacher_id' => $teacher->id,
            'name' => 'VII-P127',
            'capacity' => 30,
        ]);

        $student = Student::create([
            'school_id' => $this->school->id,
            'nis' => 'P127-001',
            'nisn' => '0077112233',
            'nik_enc' => 'enc_p127_01',
            'full_name' => 'Siswa Kron P127',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2012-01-01',
            'entry_date' => '2026-07-01',
            'status' => 'aktif',
        ]);
        ClassStudent::create([
            'class_id' => $class->id,
            'student_id' => $student->id,
            'joined_at' => Carbon::now()->subMonths(1),
        ]);

        // 3 tanggal alpa berbeda
        for ($d = 1; $d <= 3; $d++) {
            Attendance::create([
                'student_id' => $student->id,
                'att_date' => Carbon::today()->subDays($d)->format('Y-m-d'),
                'source' => 'gerbang',
                'status' => 'alpa',
            ]);
        }

        // Jalankan cron harian
        $this->artisan('attendance:daily-summary')->assertExitCode(0);

        // Tidak ada lagi duplikat ATT_LOW / status='aktif'
        $this->assertDatabaseMissing('early_warnings', [
            'student_id' => $student->id,
            'rule_code' => 'ATT_LOW',
        ]);

        // Tercipta canonical ATTENDANCE_LOW dengan status='open'
        $this->assertDatabaseHas('early_warnings', [
            'student_id' => $student->id,
            'rule_code' => 'ATTENDANCE_LOW',
            'status' => 'open',
        ]);

        // 2. Auto-fill izin melewati akhir pekan (Sabtu & Minggu) pada jalur Walas & Kepala Sekolah
        $friday = Carbon::now()->next(Carbon::FRIDAY);
        $saturday = $friday->copy()->addDay();
        $sunday = $friday->copy()->addDays(2);
        $monday = $friday->copy()->addDays(3);

        $leaveWalas = LeaveRequest::create([
            'student_id' => $student->id,
            'requested_by' => $teacherUser->id,
            'leave_type' => 'sakit',
            'start_date' => $friday->format('Y-m-d'),
            'end_date' => $monday->format('Y-m-d'),
            'reason' => 'Sakit lintas akhir pekan',
            'status' => 'menunggu',
        ]);

        $reqWalas = Request::create("/api/v1/leave-requests/{$leaveWalas->id}/decision", 'PATCH');
        $reqWalas->setUserResolver(fn () => $teacherUser);
        app(AcademicAttendanceService::class)->decideLeaveRequest($leaveWalas->id, [
            'status' => 'disetujui',
            'decision_notes' => 'Lekas sembuh',
        ], $reqWalas);

        $this->assertTrue(Attendance::where('student_id', $student->id)->where('source', 'gerbang')->whereDate('att_date', $friday->format('Y-m-d'))->exists());
        $this->assertFalse(Attendance::where('student_id', $student->id)->where('source', 'gerbang')->whereDate('att_date', $saturday->format('Y-m-d'))->exists());
        $this->assertFalse(Attendance::where('student_id', $student->id)->where('source', 'gerbang')->whereDate('att_date', $sunday->format('Y-m-d'))->exists());
        $this->assertTrue(Attendance::where('student_id', $student->id)->where('source', 'gerbang')->whereDate('att_date', $monday->format('Y-m-d'))->exists());

        // Uji jalur ApprovalService (executive review)
        $student2 = Student::create([
            'school_id' => $this->school->id,
            'nis' => 'P127-002',
            'nisn' => '0077112234',
            'nik_enc' => 'enc_p127_02',
            'full_name' => 'Siswa Executive P127',
            'gender' => 'P',
            'birth_place' => 'Malang',
            'birth_date' => '2012-02-02',
            'entry_date' => '2026-07-01',
            'status' => 'active',
        ]);

        $leaveExec = LeaveRequest::create([
            'student_id' => $student2->id,
            'requested_by' => $teacherUser->id,
            'leave_type' => 'izin',
            'start_date' => $friday->format('Y-m-d'),
            'end_date' => $monday->format('Y-m-d'),
            'reason' => 'Acara keluarga',
            'status' => 'diajukan',
        ]);

        $reqExec = Request::create("/api/v1/approvals/{$leaveExec->id}/review", 'POST');
        $reqExec->setUserResolver(fn () => $principalUser);
        app(ApprovalService::class)->reviewApproval($leaveExec->id, [
            'action' => 'approve',
            'notes' => 'Disetujui pimpinan',
        ], $reqExec);

        $this->assertTrue(Attendance::where('student_id', $student2->id)->where('source', 'gerbang')->whereDate('att_date', $friday->format('Y-m-d'))->exists());
        $this->assertFalse(Attendance::where('student_id', $student2->id)->where('source', 'gerbang')->whereDate('att_date', $saturday->format('Y-m-d'))->exists());
        $this->assertFalse(Attendance::where('student_id', $student2->id)->where('source', 'gerbang')->whereDate('att_date', $sunday->format('Y-m-d'))->exists());
        $this->assertTrue(Attendance::where('student_id', $student2->id)->where('source', 'gerbang')->whereDate('att_date', $monday->format('Y-m-d'))->exists());
    }
}
