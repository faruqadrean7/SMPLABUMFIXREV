<?php

namespace Tests\Feature\Cbt;

use App\Models\Exam;
use App\Models\ExamAnswer;
use App\Models\ExamQuestion;
use App\Models\ExamSession;
use App\Models\GradeLevel;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\Cbt\ExamGradingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * BE-CBT-IMPORT-MTK-01: Impor paket PAS Matematika kelas 9 ke bank soal + ujian CBT.
 *
 * Menjalankan command `cbt:import-package` terhadap manifest nyata, lalu memverifikasi struktur
 * bank/soal/ujian dan kebenaran penilaian otomatis. (Kebocoran kunci ke siswa dicakup terpisah oleh
 * CbtHardeningAndAnswerLeakTest yang menguji whitelist ExamSessionService.)
 */
class BeCbtImportMtk01Test extends TestCase
{
    use RefreshDatabase;

    private string $schoolId = '00000000-0000-0000-0000-000000000001';

    private string $manifest = 'import/mtk-pas-9-genap-2526.json';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedMasterData();
    }

    private function seedMasterData(): void
    {
        Subject::firstOrCreate(
            ['code' => 'MTK', 'school_id' => $this->schoolId],
            ['name' => 'Matematika', 'subject_group' => 'wajib']
        );
        GradeLevel::firstOrCreate(
            ['name' => 'Kelas IX', 'school_id' => $this->schoolId],
            ['sort_order' => 9]
        );

        foreach (['guru.mtk@labschool.sch.id', 'wakakurikulum@labschool.sch.id'] as $i => $email) {
            User::firstOrCreate(
                ['email' => $email],
                [
                    'username' => explode('@', $email)[0],
                    'password_hash' => Hash::make('Password123!'),
                    'status' => 'active',
                    'must_change_password' => false,
                    'school_id' => $this->schoolId,
                ]
            );
        }
    }

    private function runImport(): int
    {
        return Artisan::call('cbt:import-package', [
            'manifest' => $this->manifest,
            '--fresh' => true,
        ]);
    }

    private function bank(): QuestionBank
    {
        return QuestionBank::where('name', 'Matematika Kelas 9 — PAS Genap 2025/2026')->firstOrFail();
    }

    private function exam(): Exam
    {
        return Exam::where('title', 'PAS Matematika Kelas 9 — Genap 2025/2026')->firstOrFail();
    }

    public function test_import_creates_bank_with_26_questions_and_total_weight_100(): void
    {
        $this->assertSame(0, $this->runImport(), 'Command impor harus sukses (exit 0).');

        $bank = $this->bank();
        $questions = $bank->questions()->get();

        $this->assertCount(26, $questions, 'Bank harus berisi 26 record soal.');
        $this->assertSame(20, $questions->where('question_type', 'pg')->count());
        $this->assertSame(1, $questions->where('question_type', 'menjodohkan')->count());
        $this->assertSame(5, $questions->where('question_type', 'bs')->count());
        $this->assertSame(26, $questions->where('status', 'aktif')->count(), 'Semua soal berstatus aktif.');

        $totalWeight = (float) $questions->sum('weight');
        $this->assertEqualsWithDelta(100.0, $totalWeight, 0.001, 'Total bobot harus 100.');
    }

    public function test_pg_and_bs_have_exactly_one_key_and_menjodohkan_has_match_keys(): void
    {
        $this->runImport();
        $questions = $this->bank()->questions()->with('options')->get();

        foreach ($questions->whereIn('question_type', ['pg', 'bs']) as $q) {
            $this->assertSame(
                1,
                $q->options->where('is_correct', true)->count(),
                "Soal {$q->question_type} ({$q->id}) harus punya tepat 1 opsi benar."
            );
        }

        $menjodohkan = $questions->firstWhere('question_type', 'menjodohkan');
        $this->assertNotNull($menjodohkan);
        $this->assertCount(5, $menjodohkan->options);
        $this->assertSame(5, $menjodohkan->options->whereNotNull('match_key')->count(), 'Semua 5 opsi menjodohkan ber-match_key.');
    }

    public function test_exam_is_sas_draft_with_passing_grade_75_and_26_questions_plotted(): void
    {
        $this->runImport();
        $exam = $this->exam();

        $this->assertSame('SAS', $exam->exam_type);
        $this->assertSame('draft', $exam->status);
        $this->assertEqualsWithDelta(75.0, (float) $exam->passing_grade, 0.001);
        $this->assertSame(90, (int) $exam->duration_min);
        $this->assertSame(26, ExamQuestion::where('exam_id', $exam->id)->count());
    }

    public function test_all_correct_answers_are_autograded_to_100(): void
    {
        $this->runImport();
        $session = $this->makeSessionWithAnswers(correctRatio: 1.0);

        app(ExamGradingService::class)->gradeSessionObjectiveAnswers($session);

        $this->assertEqualsWithDelta(100.0, (float) $session->fresh()->score, 0.01, 'Semua benar harus bernilai 100.');
    }

    public function test_one_wrong_pg_reduces_score_by_three_points(): void
    {
        $this->runImport();
        // Jawab semua benar KECUALI 1 soal PG yang sengaja dikosongkan (tanpa jawaban = 0).
        $session = $this->makeSessionWithAnswers(correctRatio: 1.0, skipOnePg: true);

        app(ExamGradingService::class)->gradeSessionObjectiveAnswers($session);

        // 1 PG hilang = -3 poin dari 100 (dinormalkan: (97/100)*100).
        $this->assertEqualsWithDelta(97.0, (float) $session->fresh()->score, 0.01);
    }

    /**
     * Buat sesi + jawaban untuk 1 siswa fiktif. correctRatio=1.0 => semua benar.
     * skipOnePg=true => satu soal PG tidak dijawab (menguji pengurangan skor).
     */
    private function makeSessionWithAnswers(float $correctRatio, bool $skipOnePg = false): ExamSession
    {
        $exam = $this->exam();
        $student = $this->makeStudent();

        $session = ExamSession::create([
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'status' => 'selesai',
            'seed' => 'testseed-mtk-01',
            'started_at' => now()->subHour(),
            'deadline_at' => now()->addHour(),
            'submitted_at' => now(),
        ]);

        $questions = Question::whereIn(
            'id',
            ExamQuestion::where('exam_id', $exam->id)->pluck('question_id')
        )->with('options')->get();

        $pgSkipped = false;
        foreach ($questions as $q) {
            if ($skipOnePg && ! $pgSkipped && $q->question_type === 'pg') {
                $pgSkipped = true; // biarkan 1 PG tanpa jawaban

                continue;
            }

            ExamAnswer::create([
                'session_id' => $session->id,
                'question_id' => $q->id,
                'answer' => $this->correctAnswerFor($q),
                'answered_at' => now(),
            ]);
        }

        return $session;
    }

    private function correctAnswerFor(Question $q): mixed
    {
        if ($q->question_type === 'menjodohkan') {
            $pairs = [];
            foreach ($q->options as $opt) {
                $pairs[$opt->label] = $opt->match_key;
            }

            return ['pairs' => $pairs];
        }

        // pg / bs: kirim id opsi yang benar.
        return $q->options->firstWhere('is_correct', true)?->id;
    }

    private function makeStudent(): Student
    {
        $user = User::create([
            'email' => 'siswa.mtk.import@labschool.sch.id',
            'username' => 'siswa.mtk.import',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
            'must_change_password' => false,
            'school_id' => $this->schoolId,
        ]);

        return Student::create([
            'user_id' => $user->id,
            'school_id' => $this->schoolId,
            'nis' => '99002',
            'nisn' => '0099009902',
            'nik_enc' => '3578010101990002',
            'full_name' => 'Siswa Uji Impor MTK',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2012-01-01',
            'religion' => 'Islam',
            'address' => 'Jl. Uji Impor No. 1',
            'status' => 'aktif',
            'entry_date' => '2026-07-01',
        ]);
    }
}
