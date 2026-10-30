<?php

namespace Tests\Feature\Seeder;

use App\Models\GradeLevel;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\IpaQuestionBankSeeder;
use Database\Seeders\TestingFullMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IpaQuestionBankSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * TEST-BE-CBT-SEED-01: Verifikasi eksekusi dan struktur 9 bank soal IPA (3 per tingkat).
     */
    public function test_be_cbt_seed_01_ipa_question_bank_seeder_creates_nine_banks_across_three_levels(): void
    {
        // Jalankan master seeder terlebih dahulu agar struktur sekolah & user guru siap
        $this->seed(TestingFullMasterSeeder::class);

        // Jalankan IpaQuestionBankSeeder
        $this->seed(IpaQuestionBankSeeder::class);

        // 1. Verifikasi total Bank Soal IPA
        $ipaSubject = Subject::where('code', 'IPA')->firstOrFail();
        $banks = QuestionBank::where('subject_id', $ipaSubject->id)->get();

        $this->assertCount(9, $banks, 'Harus terdapat tepat 9 Bank Soal untuk Mapel IPA.');

        // 2. Verifikasi 3 Bank Soal di setiap Tingkat (Kelas 7, 8, 9)
        $expectedCodes = [
            7 => ['IPA-7-A', 'IPA-7-B', 'IPA-7-C'],
            8 => ['IPA-8-A', 'IPA-8-B', 'IPA-8-C'],
            9 => ['IPA-9-A', 'IPA-9-B', 'IPA-9-C'],
        ];

        foreach ($expectedCodes as $gradeNum => $codes) {
            $grade = GradeLevel::where('sort_order', $gradeNum)
                ->orWhere('name', 'LIKE', "%{$gradeNum}%")
                ->firstOrFail();

            $gradeBanks = QuestionBank::where('subject_id', $ipaSubject->id)
                ->where('grade_level_id', $grade->id)
                ->get();

            $this->assertCount(3, $gradeBanks, "Tingkat {$gradeNum} harus memiliki tepat 3 bank soal.");

            foreach ($codes as $code) {
                $matchingBank = $gradeBanks->first(fn ($b) => str_contains($b->name, "({$code})") || str_contains($b->name, "Kode: {$code}"));
                $this->assertNotNull($matchingBank, "Bank soal dengan kode {$code} harus ditemukan pada Tingkat {$gradeNum}.");
            }
        }

        // 3. Verifikasi jumlah butir soal (5 per bank, total 45 soal)
        $totalIpaQuestions = Question::whereIn('bank_id', $banks->pluck('id'))->count();
        $this->assertEquals(45, $totalIpaQuestions, 'Total butir soal pada 9 Bank Soal IPA harus berjumlah 45 butir.');

        foreach ($banks as $bank) {
            $questions = $bank->questions()->with('options')->get();
            $this->assertCount(5, $questions, "Bank soal {$bank->name} harus memiliki 5 butir soal.");

            // Verifikasi keragaman tipe soal: pg, pg_kompleks, bs, isian, uraian
            $types = $questions->pluck('question_type')->toArray();
            $this->assertContains('pg', $types, "Bank {$bank->name} harus memiliki soal tipe PG.");
            $this->assertContains('pg_kompleks', $types, "Bank {$bank->name} harus memiliki soal tipe PG Kompleks.");
            $this->assertContains('bs', $types, "Bank {$bank->name} harus memiliki soal tipe Benar/Salah (BS).");
            $this->assertContains('isian', $types, "Bank {$bank->name} harus memiliki soal tipe Isian Singkat.");
            $this->assertContains('uraian', $types, "Bank {$bank->name} harus memiliki soal tipe Uraian.");

            // Verifikasi validitas atribut soal
            foreach ($questions as $q) {
                $this->assertNotNull($q->cp_code);
                $this->assertNotEmpty($q->body);
                $this->assertContains($q->cognitive_level, ['LOTS', 'MOTS', 'HOTS']);
                $this->assertGreaterThanOrEqual(1, $q->difficulty);
                $this->assertLessThanOrEqual(5, $q->difficulty);
                $this->assertGreaterThan(0, $q->weight);
                $this->assertEquals('aktif', $q->status);
                $this->assertNotEmpty($q->explanation, 'Setiap butir soal IPA harus memiliki pembahasan ilmiah.');

                // Verifikasi opsi jawaban untuk tipe pilihan
                if (in_array($q->question_type, ['pg', 'pg_kompleks', 'bs'])) {
                    $this->assertNotEmpty($q->options, "Soal bertipe {$q->question_type} harus memiliki opsi jawaban.");
                    $hasCorrect = $q->options->contains('is_correct', true);
                    $this->assertTrue($hasCorrect, "Soal {$q->cp_code} bertipe {$q->question_type} harus memiliki setidaknya 1 kunci jawaban benar.");
                }
            }
        }
    }

    /**
     * TEST-BE-CBT-SEED-02: Verifikasi idempotensi IpaQuestionBankSeeder (dijalankan berulang kali).
     */
    public function test_be_cbt_seed_02_ipa_question_bank_seeder_is_idempotent(): void
    {
        // Jalankan seeder 2 kali berturut-turut
        $this->seed(IpaQuestionBankSeeder::class);
        $this->seed(IpaQuestionBankSeeder::class);

        $ipaSubject = Subject::where('code', 'IPA')->firstOrFail();
        $banksCount = QuestionBank::where('subject_id', $ipaSubject->id)->count();
        $this->assertEquals(9, $banksCount, 'Seeding berulang tidak boleh melipatgandakan bank soal.');

        $questionsCount = Question::whereHas('bank', fn ($q) => $q->where('subject_id', $ipaSubject->id))->count();
        $this->assertEquals(45, $questionsCount, 'Seeding berulang tidak boleh melipatgandakan butir soal.');
    }
}
