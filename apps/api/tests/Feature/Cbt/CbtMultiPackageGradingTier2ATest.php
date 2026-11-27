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
use App\Models\School;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\Cbt\ExamGradingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CbtMultiPackageGradingTier2ATest extends TestCase
{
    use RefreshDatabase;

    public function test_p0_12_multi_package_objective_and_essay_grading_uses_only_participant_package_weights(): void
    {
        $school = School::create([
            'name' => 'SMP Lab CBT Multi-Paket',
            'npsn' => '20534012',
            'address' => 'Jl. Fiktif CBT',
            'phone' => '0341000012',
            'email' => 'cbt12@test.local',
        ]);

        $roleAdmin = Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Admin', 'is_system' => true]);
        foreach (['cbt.manage', 'cbt.exam.grade', 'cbt.exam.read'] as $code) {
            $p = Permission::firstOrCreate(['code' => $code], ['description' => $code]);
            $roleAdmin->permissions()->syncWithoutDetaching([$p->id]);
        }

        $teacher = User::create([
            'school_id' => $school->id,
            'username' => 'guru_cbt_pkg_'.uniqid(),
            'email' => 'guru_cbt_pkg_'.uniqid().'@test.local',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $teacher->roles()->attach($roleAdmin->id);

        $studentA = Student::create([
            'school_id' => $school->id,
            'nis' => 'NIS-PKGA-01',
            'nisn' => '0011223344',
            'nik_enc' => '3573010101120111',
            'full_name' => 'Siswa Paket A',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2012-01-01',
            'religion' => 'Islam',
            'address' => 'Jl. Paket A',
            'entry_date' => '2025-07-14',
            'status' => 'aktif',
        ]);

        $subject = Subject::create([
            'school_id' => $school->id,
            'code' => 'MTK-PKG',
            'name' => 'Matematika',
            'subject_group' => 'wajib',
        ]);
        $bank = QuestionBank::create([
            'subject_id' => $subject->id,
            'owner_id' => $teacher->id,
            'name' => 'Bank Soal Multi-Paket',
        ]);

        // Paket A: 1 PG (bobot 50) + 1 Uraian (bobot 50) => total bobot Paket A = 100
        $qPgA = Question::create(['bank_id' => $bank->id, 'question_type' => 'pg', 'body' => ['content' => '2+2=?'], 'weight' => 50, 'status' => 'aktif']);
        $optACorrect = QuestionOption::create(['question_id' => $qPgA->id, 'label' => 'A', 'body' => ['text' => '4'], 'is_correct' => true]);
        $qEssayA = Question::create(['bank_id' => $bank->id, 'question_type' => 'uraian', 'body' => ['content' => 'Jelaskan teorema Pythagoras'], 'weight' => 50, 'status' => 'aktif']);

        // Paket B: 1 PG (bobot 50) + 1 Uraian (bobot 50) => total bobot Paket B = 100 (HARUS DIABAIKAN untuk siswa Paket A)
        $qPgB = Question::create(['bank_id' => $bank->id, 'question_type' => 'pg', 'body' => ['content' => '3+3=?'], 'weight' => 50, 'status' => 'aktif']);
        QuestionOption::create(['question_id' => $qPgB->id, 'label' => 'A', 'body' => ['text' => '6'], 'is_correct' => true]);
        $qEssayB = Question::create(['bank_id' => $bank->id, 'question_type' => 'uraian', 'body' => ['content' => 'Jelaskan luas lingkaran'], 'weight' => 50, 'status' => 'aktif']);

        $exam = Exam::create([
            'school_id' => $school->id,
            'subject_id' => $subject->id,
            'created_by' => $teacher->id,
            'title' => 'SAS Multi-Paket',
            'exam_type' => 'sas',
            'duration_min' => 90,
            'passing_grade' => 75.0,
            'start_at' => Carbon::now()->subHour(),
            'end_at' => Carbon::now()->addHour(),
            'status' => 'berlangsung',
        ]);

        ExamQuestion::create(['exam_id' => $exam->id, 'question_id' => $qPgA->id, 'sort_order' => 1, 'package_code' => 'A']);
        ExamQuestion::create(['exam_id' => $exam->id, 'question_id' => $qEssayA->id, 'sort_order' => 2, 'package_code' => 'A']);
        ExamQuestion::create(['exam_id' => $exam->id, 'question_id' => $qPgB->id, 'sort_order' => 1, 'package_code' => 'B']);
        ExamQuestion::create(['exam_id' => $exam->id, 'question_id' => $qEssayB->id, 'sort_order' => 2, 'package_code' => 'B']);

        $room = Room::create(['school_id' => $school->id, 'code' => 'LAB-PKG', 'name' => 'Lab Komputer 1', 'room_type' => 'lab', 'capacity' => 30]);
        $examRoom = ExamRoom::create(['exam_id' => $exam->id, 'room_id' => $room->id, 'session_no' => 1, 'token' => 'PKG123']);

        ExamParticipant::create([
            'exam_id' => $exam->id,
            'student_id' => $studentA->id,
            'exam_room_id' => $examRoom->id,
            'package_code' => 'A',
        ]);

        $sessionA = ExamSession::create([
            'exam_id' => $exam->id,
            'student_id' => $studentA->id,
            'status' => 'selesai',
            'started_at' => Carbon::now()->subMinutes(40),
            'deadline_at' => Carbon::now()->addMinutes(50),
            'submitted_at' => Carbon::now()->subMinutes(5),
            'seed' => 'seed-pkg-a',
        ]);

        ExamAnswer::create([
            'session_id' => $sessionA->id,
            'question_id' => $qPgA->id,
            'answer' => ['selected_option_id' => $optACorrect->id],
        ]);
        $essayAnswerA = ExamAnswer::create([
            'session_id' => $sessionA->id,
            'question_id' => $qEssayA->id,
            'answer' => ['text' => 'a^2 + b^2 = c^2'],
        ]);

        // 1. Koreksi objektif otomatis -> PG benar penuh (50 / 100 = 50.0, BUKAN 25.0)
        app(ExamGradingService::class)->gradeSessionObjectiveAnswers($sessionA);
        $sessionA->refresh();
        $this->assertEquals(50.0, (float) $sessionA->score);

        // 2. Guru menilai jawaban esai Paket A dengan nilai penuh 50 -> total skor WAJIB 100.0 (BUKAN 50.0!)
        $gradeEssayRes = $this->actingAs($teacher, 'sanctum')
            ->postJson("/api/v1/exams/{$exam->id}/grade-essay", [
                'answer_id' => $essayAnswerA->id,
                'score' => 50,
            ]);

        $gradeEssayRes->assertStatus(200)
            ->assertJsonPath('data.session_total_score', 100);

        $sessionA->refresh();
        $this->assertEquals(100.0, (float) $sessionA->score);

        // 3. Rekap hasil ujian harus menampilkan grading_status = 'final' & ungraded_essays_count = 0
        $resultsRes = $this->actingAs($teacher, 'sanctum')
            ->getJson("/api/v1/exams/{$exam->id}/results")
            ->assertStatus(200);

        $this->assertSame('final', $resultsRes->json('data.results.0.grading_status'));
        $this->assertSame(0, $resultsRes->json('data.results.0.ungraded_essays_count'));
    }
}
