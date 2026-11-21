<?php

namespace Tests\Feature\Cbt;

use App\Enums\ExamSessionStatus;
use App\Enums\ExamStatus;
use App\Models\Exam;
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
use Database\Seeders\CbtDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CbtHardeningAndAnswerLeakTest extends TestCase
{
    use RefreshDatabase;

    protected array $forbiddenKeys = [
        'is_correct',
        'match_key',
        'correct_answer',
        'rubric',
        'kunci_jawaban',
        'answer_key',
        'explanation',
    ];

    /**
     * Helper rekursif untuk memastikan tidak ada kunci jawaban atau data sensitif yang bocor pada JSON.
     */
    protected function assertNoAnswerLeak(mixed $data, string $path = ''): void
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $currentPath = $path ? "{$path}.{$key}" : (string) $key;
                $this->assertFalse(
                    in_array($key, $this->forbiddenKeys, true),
                    "Leaked forbidden key '{$key}' at path '{$currentPath}'"
                );
                $this->assertNoAnswerLeak($value, $currentPath);
            }
        }
    }

    /**
     * TAHAP 1.a: ExamSessionService::getSessionQuestions WHITELIST & rogue fields dropping.
     */
    public function test_get_session_questions_uses_strict_whitelist_and_drops_injected_rogue_fields(): void
    {
        $schoolId = '00000000-0000-0000-0000-000000000001';

        $studentRole = Role::firstOrCreate(['name' => 'siswa'], ['code' => 'siswa', 'name' => 'siswa']);
        $studentUser = User::create([
            'email' => 'student.whitelist@smp-labum.sch.id',
            'username' => 'student.whitelist',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
            'must_change_password' => false,
        ]);
        $studentUser->roles()->attach($studentRole->id);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'school_id' => $schoolId,
            'nis' => '99001',
            'nisn' => '0099009901',
            'nik_enc' => '3578010101990001',
            'full_name' => 'Siswa Test Whitelist',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2012-01-01',
            'religion' => 'Islam',
            'address' => 'Jl. UAT Whitelist No. 1',
            'status' => 'aktif',
            'entry_date' => '2026-07-01',
        ]);

        $subject = Subject::firstOrCreate(
            ['code' => 'TEST-01'],
            ['school_id' => $schoolId, 'name' => 'Testing Subjek', 'subject_group' => 'wajib']
        );

        $room = Room::firstOrCreate(
            ['code' => 'R-TEST-01'],
            ['school_id' => $schoolId, 'name' => 'Ruang Test', 'room_type' => 'kelas', 'capacity' => 30]
        );

        $bank = QuestionBank::create([
            'school_id' => $schoolId,
            'name' => 'Bank Soal Uji Whitelist',
            'subject_id' => $subject->id,
            'owner_id' => $studentUser->id,
        ]);

        // Suntikkan berbagai rogue fields ke dalam Question body & QuestionOption body
        $question = Question::create([
            'bank_id' => $bank->id,
            'cp_code' => 'CP-ROGUE-01',
            'question_type' => 'pg',
            'body' => [
                'content' => 'Teks soal dengan rogue fields',
                'text' => 'Teks soal dengan rogue fields',
                'secret_key' => 'CONFIDENTIAL_KEY_999',
                'answer' => 'UNAUTHORIZED_ANSWER_LEAK',
                'explanation' => 'PENJELASAN_TERSEMBUNYI_GURU',
                'rubric' => 'PEDOMAN_PENSKORAN_RAHASIA',
                'kunci_jawaban' => 'A',
                'internal_admin_note' => 'Hanya untuk penguji',
            ],
            'cognitive_level' => 'LOTS',
            'difficulty' => 1,
            'weight' => 5.0,
            'explanation' => 'Penjelasan pada kolom explanation tabel questions',
            'status' => 'aktif',
        ]);

        $optionA = QuestionOption::create([
            'question_id' => $question->id,
            'label' => 'A',
            'body' => [
                'text' => 'Pilihan A Sah',
                'content' => 'Pilihan A Sah',
                'secret_key' => 'ROGUE_OPTION_SECRET',
                'answer' => 'ROGUE_OPTION_ANSWER',
                'explanation' => 'ROGUE_OPTION_EXPLANATION',
                'custom_metadata' => 'DROPPED',
            ],
            'is_correct' => true,
            'match_key' => 'LEAKED_MATCH_KEY',
        ]);

        $optionB = QuestionOption::create([
            'question_id' => $question->id,
            'label' => 'B',
            'body' => [
                'text' => 'Pilihan B Sah',
                'content' => 'Pilihan B Sah',
                'internal_leak' => 'SHOULD_NOT_APPEAR',
            ],
            'is_correct' => false,
            'match_key' => null,
        ]);

        $exam = Exam::create([
            'school_id' => $schoolId,
            'subject_id' => $subject->id,
            'created_by' => $studentUser->id,
            'title' => 'Ujian Uji Sanitasi Whitelist',
            'exam_type' => 'formatif',
            'duration_min' => 30,
            'start_at' => Carbon::now()->subMinutes(5),
            'end_at' => Carbon::now()->addHours(2),
            'passing_grade' => 75.0,
            'shuffle_questions' => false,
            'shuffle_options' => false,
            'package_mode' => 'tunggal',
            'show_result_mode' => 'setelah_selesai',
            'status' => Exam::STATUS_IN_PROGRESS,
        ]);

        ExamQuestion::create([
            'exam_id' => $exam->id,
            'question_id' => $question->id,
            'sort_order' => 1,
            'weight_override' => 5.0,
        ]);

        $examRoom = ExamRoom::create([
            'exam_id' => $exam->id,
            'room_id' => $room->id,
            'session_no' => 1,
            'token' => 'WHTLST',
            'token_expires_at' => Carbon::now()->addHours(2),
        ]);

        ExamParticipant::create([
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'exam_room_id' => $examRoom->id,
        ]);

        // Mulai sesi sebagai siswa
        $startRes = $this->actingAs($studentUser, 'sanctum')
            ->postJson("/api/v1/exams/{$exam->id}/start-session", ['token' => 'WHTLST']);
        $startRes->assertStatus(200);

        // Ambil butir soal
        $questionsRes = $this->actingAs($studentUser, 'sanctum')
            ->getJson("/api/v1/exams/{$exam->id}/session-questions");
        $questionsRes->assertStatus(200);

        $payload = $questionsRes->json();
        $this->assertNotEmpty($payload['data']['questions']);
        $qData = $payload['data']['questions'][0];

        // 1. Assert field Question body disaring ketat (hanya content & text yang boleh lolos)
        $this->assertEquals('Teks soal dengan rogue fields', $qData['body']['content'] ?? null);
        $this->assertEquals('Teks soal dengan rogue fields', $qData['body']['text'] ?? null);
        $this->assertArrayNotHasKey('secret_key', $qData['body']);
        $this->assertArrayNotHasKey('answer', $qData['body']);
        $this->assertArrayNotHasKey('explanation', $qData['body']);
        $this->assertArrayNotHasKey('rubric', $qData['body']);
        $this->assertArrayNotHasKey('kunci_jawaban', $qData['body']);
        $this->assertArrayNotHasKey('internal_admin_note', $qData['body']);

        // 2. Assert field Option body disaring ketat
        $optAData = $qData['options'][0];
        $this->assertEquals('A', $optAData['label']);
        $this->assertEquals('Pilihan A Sah', $optAData['text']);
        $this->assertEquals('Pilihan A Sah', $optAData['body']['text'] ?? null);
        $this->assertArrayNotHasKey('secret_key', $optAData['body']);
        $this->assertArrayNotHasKey('answer', $optAData['body']);
        $this->assertArrayNotHasKey('explanation', $optAData['body']);
        $this->assertArrayNotHasKey('custom_metadata', $optAData['body']);

        // 3. Assert field is_correct & match_key pada level option sama sekali tidak ada
        $this->assertArrayNotHasKey('is_correct', $optAData);
        $this->assertArrayNotHasKey('match_key', $optAData);

        // 4. Assert no leak scanner lulus
        $this->assertNoAnswerLeak($payload);
    }

    /**
     * TAHAP 1.b: Audit SEMUA endpoint yang bisa dipanggil siswa dan menyentuh soal/jawaban.
     * Tulis test "no answer leak" dengan scan rekursif sebelum ujian selesai.
     */
    public function test_recursive_scanner_asserts_no_answer_leak_across_all_student_accessible_endpoints_before_session_finish(): void
    {
        // Jalankan CbtDemoSeeder
        config(['app.env' => 'testing']);
        $seeder = new CbtDemoSeeder;
        $seeder->run();

        $studentUser = User::where('email', 'siswa.lab.001@smp-labum.sch.id')->firstOrFail();
        $exam = Exam::where('title', 'like', '%Asesmen Harian Informatika 7%')->firstOrFail();

        // 1. Endpoint: GET /api/v1/me/exams
        $resMeExams = $this->actingAs($studentUser, 'sanctum')->getJson('/api/v1/me/exams');
        $resMeExams->assertStatus(200);
        $this->assertNoAnswerLeak($resMeExams->json());

        // 2. Endpoint: GET /api/v1/exams
        $resExams = $this->actingAs($studentUser, 'sanctum')->getJson('/api/v1/exams');
        $resExams->assertStatus(200);
        $this->assertNoAnswerLeak($resExams->json());

        // 3. Endpoint: GET /api/v1/exams/{id}
        $resExamDetail = $this->actingAs($studentUser, 'sanctum')->getJson("/api/v1/exams/{$exam->id}");
        $resExamDetail->assertStatus(200);
        $this->assertNoAnswerLeak($resExamDetail->json());

        // 4. Endpoint: POST /api/v1/exams/{id}/start-session
        $resStart = $this->actingAs($studentUser, 'sanctum')->postJson("/api/v1/exams/{$exam->id}/start-session", [
            'token' => 'DEMO30',
        ]);
        $resStart->assertStatus(200);
        $this->assertNoAnswerLeak($resStart->json());

        // 5. Endpoint: GET /api/v1/exams/{id}/session-questions (Mencakup ke-20 soal demo)
        $resQuestions = $this->actingAs($studentUser, 'sanctum')->getJson("/api/v1/exams/{$exam->id}/session-questions");
        $resQuestions->assertStatus(200);
        $questionsPayload = $resQuestions->json();
        $this->assertNoAnswerLeak($questionsPayload);
        $this->assertCount(20, $questionsPayload['data']['questions']);

        // 6. Endpoint: POST /api/v1/exams/{id}/save-answer
        $targetQ = collect($questionsPayload['data']['questions'])->first(fn ($q) => ! empty($q['options']));
        $this->assertNotNull($targetQ);
        $firstOpt = $targetQ['options'][0] ?? null;
        $this->assertNotNull($firstOpt);

        $resSave = $this->actingAs($studentUser, 'sanctum')->postJson("/api/v1/exams/{$exam->id}/save-answer", [
            'question_id' => $targetQ['question_id'],
            'answer' => ['selected_option_id' => $firstOpt['id']],
        ]);
        $resSave->assertStatus(200);
        $this->assertNoAnswerLeak($resSave->json());

        // 7. Endpoint: POST /api/v1/exams/{id}/log-violation
        $resViolation = $this->actingAs($studentUser, 'sanctum')->postJson("/api/v1/exams/{$exam->id}/log-violation", [
            'violation_type' => 'tab_switch',
            'details' => ['screen' => 'blur'],
        ]);
        $resViolation->assertStatus(200);
        $this->assertNoAnswerLeak($resViolation->json());

        // 8. Endpoint: GET /api/v1/exams/{id}/review (Ditolak 403 karena sesi belum selesai)
        $resReviewForbidden = $this->actingAs($studentUser, 'sanctum')->getJson("/api/v1/exams/{$exam->id}/review");
        $resReviewForbidden->assertStatus(403);
        $this->assertNoAnswerLeak($resReviewForbidden->json());

        // 9. Endpoint: GET /api/v1/exams/{id}/monitor (Ditolak 403 karena siswa bukan pengawas)
        $resMonitorForbidden = $this->actingAs($studentUser, 'sanctum')->getJson("/api/v1/exams/{$exam->id}/monitor");
        $resMonitorForbidden->assertStatus(403);
        $this->assertNoAnswerLeak($resMonitorForbidden->json());

        // 10. Endpoint: GET /api/v1/exams/{id}/report (Ditolak 403)
        $resReportForbidden = $this->actingAs($studentUser, 'sanctum')->getJson("/api/v1/exams/{$exam->id}/report");
        $resReportForbidden->assertStatus(403);
        $this->assertNoAnswerLeak($resReportForbidden->json());

        // 11. Endpoint: GET /api/v1/exams/{id}/questions (Ditolak 403)
        $resAdminQuestions = $this->actingAs($studentUser, 'sanctum')->getJson("/api/v1/exams/{$exam->id}/questions");
        $resAdminQuestions->assertStatus(403);
        $this->assertNoAnswerLeak($resAdminQuestions->json());
    }

    /**
     * TAHAP 1.d: Status ujian dan status sesi menjadi satu sumber kebenaran (Enum & Konstanta).
     */
    public function test_canonical_statuses_enum_and_model_constants_alignment(): void
    {
        $expectedExamStatuses = ['draft', 'dijadwalkan', 'berlangsung', 'selesai', 'dibatalkan', 'arsip'];
        $expectedSessionStatuses = ['belum', 'mengerjakan', 'selesai', 'terkunci'];

        $this->assertEquals($expectedExamStatuses, ExamStatus::values());
        $this->assertEquals($expectedExamStatuses, Exam::STATUSES);

        $this->assertEquals($expectedSessionStatuses, ExamSessionStatus::values());
        $this->assertEquals($expectedSessionStatuses, ExamSession::STATUSES);

        // Uji helper isLive pada enum
        $this->assertTrue(ExamStatus::IN_PROGRESS->isLive());
        $this->assertFalse(ExamStatus::DRAFT->isLive());
        $this->assertFalse(ExamStatus::FINISHED->isLive());
    }
}
