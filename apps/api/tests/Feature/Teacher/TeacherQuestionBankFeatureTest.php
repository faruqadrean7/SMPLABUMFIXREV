<?php

namespace Tests\Feature\Teacher;

use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\GradeLevel;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\TeacherDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherQuestionBankFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected User $userMtk;

    protected Teacher $teacherMtk;

    protected User $userKurikulum;

    protected Teacher $teacherKurikulum;

    protected Subject $subjectMtk;

    protected Subject $subjectIpa;

    protected SchoolClass $class7A;

    protected SchoolClass $class7B;

    protected GradeLevel $gradeLevel7;

    protected function setUp(): void
    {
        parent::setUp();

        (new TeacherDemoSeeder)->run();

        $this->userMtk = User::where('username', 'guru.mtk.staging')->firstOrFail();
        $this->teacherMtk = Teacher::where('user_id', $this->userMtk->id)->firstOrFail();

        $this->userKurikulum = User::where('username', 'kurikulum.staging')->firstOrFail();
        $this->teacherKurikulum = Teacher::where('user_id', $this->userKurikulum->id)->firstOrFail();

        $this->subjectMtk = Subject::where('code', 'MTK-7')->firstOrFail();
        $this->subjectIpa = Subject::where('code', 'IPA-7')->firstOrFail();

        $this->class7A = SchoolClass::where('name', '7-A')->firstOrFail();
        $this->class7B = SchoolClass::where('name', '7-B')->firstOrFail();

        $this->gradeLevel7 = GradeLevel::where('name', 'Kelas 7')->firstOrFail();
    }

    /**
     * Guru mapel dapat membuat bank soal untuk mapel yang diampunya.
     */
    public function test_teacher_can_create_question_bank_for_taught_subject(): void
    {
        $response = $this->actingAs($this->userMtk)
            ->postJson('/api/v1/me/teacher/question-banks', [
                'name' => 'Bank Soal Aljabar Linear Kelas 7',
                'subject_id' => $this->subjectMtk->id,
                'grade_level_id' => $this->gradeLevel7->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Bank Soal Aljabar Linear Kelas 7')
            ->assertJsonPath('data.subject_id', $this->subjectMtk->id);

        $this->assertDatabaseHas('question_banks', [
            'name' => 'Bank Soal Aljabar Linear Kelas 7',
            'owner_id' => $this->userMtk->id,
            'subject_id' => $this->subjectMtk->id,
        ]);
    }

    /**
     * Guru dilarang membuat bank soal untuk mata pelajaran yang tidak diampunya (403).
     */
    public function test_teacher_cannot_create_question_bank_for_untaught_subject(): void
    {
        $response = $this->actingAs($this->userMtk)
            ->postJson('/api/v1/me/teacher/question-banks', [
                'name' => 'Bank Soal Biologi Sel',
                'subject_id' => $this->subjectIpa->id, // MTK teacher does not teach IPA
                'grade_level_id' => $this->gradeLevel7->id,
            ]);

        $response->assertStatus(403);
    }

    /**
     * Guru dapat membuat soal semua tipe (PG, PG kompleks, B/S, menjodohkan, isian, uraian)
     * lengkap dengan tag kesulitan & Bloom.
     */
    public function test_teacher_can_create_all_question_types_with_bloom_and_difficulty(): void
    {
        $bank = QuestionBank::create([
            'school_id' => $this->userMtk->school_id,
            'subject_id' => $this->subjectMtk->id,
            'grade_level_id' => $this->gradeLevel7->id,
            'owner_id' => $this->userMtk->id,
            'name' => 'Bank Soal Uji Tipe Lengkap',
        ]);

        $types = [
            [
                'question_type' => 'pg',
                'body' => ['text' => 'Berapa nilai x jika 2x + 4 = 10?'],
                'cognitive_level' => 'C2',
                'difficulty' => 2,
                'weight' => 2.0,
                'options' => [
                    ['label' => 'A', 'body' => ['text' => '2'], 'is_correct' => false],
                    ['label' => 'B', 'body' => ['text' => '3'], 'is_correct' => true],
                    ['label' => 'C', 'body' => ['text' => '4'], 'is_correct' => false],
                ],
            ],
            [
                'question_type' => 'pg_kompleks',
                'body' => ['text' => 'Pilihlah bilangan prima di antara pilihan berikut:'],
                'cognitive_level' => 'C3',
                'difficulty' => 3,
                'weight' => 3.0,
                'options' => [
                    ['label' => 'A', 'body' => ['text' => '2'], 'is_correct' => true],
                    ['label' => 'B', 'body' => ['text' => '3'], 'is_correct' => true],
                    ['label' => 'C', 'body' => ['text' => '4'], 'is_correct' => false],
                ],
            ],
            [
                'question_type' => 'bs',
                'body' => ['text' => 'Jumlah sudut segitiga adalah 180 derajat.'],
                'cognitive_level' => 'C1',
                'difficulty' => 1,
                'weight' => 1.0,
                'options' => [
                    ['label' => 'A', 'body' => ['text' => 'Benar'], 'is_correct' => true],
                    ['label' => 'B', 'body' => ['text' => 'Salah'], 'is_correct' => false],
                ],
            ],
            [
                'question_type' => 'menjodohkan',
                'body' => ['text' => 'Pasangkan bentuk bangun dengan rumus luasnya:'],
                'cognitive_level' => 'C4',
                'difficulty' => 4,
                'weight' => 4.0,
                'options' => [
                    ['label' => 'A', 'body' => ['premise' => 'Persegi', 'response' => 's x s'], 'is_correct' => true, 'match_key' => 'sxs'],
                    ['label' => 'B', 'body' => ['premise' => 'Segitiga', 'response' => '1/2 x a x t'], 'is_correct' => true, 'match_key' => '12at'],
                ],
            ],
            [
                'question_type' => 'isian',
                'body' => ['text' => 'Jika luas persegi 25 cm2, berapakah panjang sisinya (cm)?'],
                'cognitive_level' => 'C2',
                'difficulty' => 2,
                'weight' => 2.0,
                'options' => [
                    ['label' => 'Kunci', 'body' => ['text' => '5'], 'is_correct' => true],
                ],
            ],
            [
                'question_type' => 'uraian',
                'body' => ['text' => 'Jelaskan langkah-langkah memfaktorkan bentuk kuadrat ax^2 + bx + c!'],
                'cognitive_level' => 'C5',
                'difficulty' => 5,
                'weight' => 5.0,
                'options' => [],
            ],
        ];

        foreach ($types as $qPayload) {
            $res = $this->actingAs($this->userMtk)
                ->postJson("/api/v1/me/teacher/question-banks/{$bank->id}/questions", $qPayload);

            $res->assertStatus(201)
                ->assertJsonPath('data.question_type', $qPayload['question_type'])
                ->assertJsonPath('data.cognitive_level', $qPayload['cognitive_level']);

            $this->assertDatabaseHas('questions', [
                'bank_id' => $bank->id,
                'question_type' => $qPayload['question_type'],
                'cognitive_level' => $qPayload['cognitive_level'],
            ]);
        }
    }

    /**
     * Guru dapat memperbarui butir soal miliknya.
     */
    public function test_teacher_can_update_own_question(): void
    {
        $bank = QuestionBank::create([
            'school_id' => $this->userMtk->school_id,
            'subject_id' => $this->subjectMtk->id,
            'grade_level_id' => $this->gradeLevel7->id,
            'owner_id' => $this->userMtk->id,
            'name' => 'Bank Soal MTK 7',
        ]);

        $question = Question::create([
            'bank_id' => $bank->id,
            'question_type' => 'pg',
            'body' => ['text' => 'Soal Draf'],
            'cognitive_level' => 'C1',
            'difficulty' => 1,
            'weight' => 1.0,
        ]);

        $res = $this->actingAs($this->userMtk)
            ->putJson("/api/v1/me/teacher/questions/{$question->id}", [
                'body' => ['text' => 'Soal Revisi Lengkap'],
                'cognitive_level' => 'C3',
                'difficulty' => 3,
                'weight' => 2.5,
            ]);

        $res->assertOk()
            ->assertJsonPath('data.body.text', 'Soal Revisi Lengkap')
            ->assertJsonPath('data.cognitive_level', 'C3');

        $this->assertDatabaseHas('questions', [
            'id' => $question->id,
            'cognitive_level' => 'C3',
        ]);
    }

    /**
     * Guru lain dilarang mengubah atau menghapus bank/soal milik guru ini (403).
     */
    public function test_other_teacher_cannot_update_or_delete_question(): void
    {
        $bank = QuestionBank::create([
            'school_id' => $this->userMtk->school_id,
            'subject_id' => $this->subjectMtk->id,
            'grade_level_id' => $this->gradeLevel7->id,
            'owner_id' => $this->userMtk->id,
            'name' => 'Bank Soal Privat MTK',
        ]);

        $question = Question::create([
            'bank_id' => $bank->id,
            'question_type' => 'pg',
            'body' => ['text' => 'Soal Asli Guru MTK'],
            'cognitive_level' => 'C2',
            'difficulty' => 2,
            'weight' => 2.0,
        ]);

        // Guru kurikulum mencoba mengupdate
        $updateRes = $this->actingAs($this->userKurikulum)
            ->putJson("/api/v1/me/teacher/questions/{$question->id}", [
                'body' => ['text' => 'Percobaan Pembajakan Soal'],
            ]);
        $updateRes->assertStatus(403);

        // Guru kurikulum mencoba menghapus
        $deleteRes = $this->actingAs($this->userKurikulum)
            ->deleteJson("/api/v1/me/teacher/questions/{$question->id}");
        $deleteRes->assertStatus(403);

        // Guru kurikulum mencoba menghapus bank soal milik Guru MTK
        $deleteBankRes = $this->actingAs($this->userKurikulum)
            ->deleteJson("/api/v1/me/teacher/question-banks/{$bank->id}");
        $deleteBankRes->assertStatus(403);
    }

    /**
     * Soal yang sudah dipakai dalam ujian berjalan/dijadwalkan TIDAK BOLEH diubah atau dihapus (422).
     */
    public function test_question_used_in_active_exam_cannot_be_updated_or_deleted(): void
    {
        $bank = QuestionBank::create([
            'school_id' => $this->userMtk->school_id,
            'subject_id' => $this->subjectMtk->id,
            'grade_level_id' => $this->gradeLevel7->id,
            'owner_id' => $this->userMtk->id,
            'name' => 'Bank Soal Terpakai',
        ]);

        $question = Question::create([
            'bank_id' => $bank->id,
            'question_type' => 'pg',
            'body' => ['text' => 'Soal yang sedang diujikan'],
            'cognitive_level' => 'C2',
            'difficulty' => 2,
            'weight' => 2.0,
        ]);

        $exam = Exam::create([
            'school_id' => $this->userMtk->school_id,
            'subject_id' => $this->subjectMtk->id,
            'created_by' => $this->userMtk->id,
            'title' => 'PTS Matematika Sedang Berlangsung',
            'exam_type' => 'sumatif',
            'duration_min' => 60,
            'status' => Exam::STATUS_IN_PROGRESS, // atau 'berlangsung'
            'start_at' => now()->subHour(),
            'end_at' => now()->addHour(),
        ]);

        ExamQuestion::create([
            'exam_id' => $exam->id,
            'question_id' => $question->id,
            'sort_order' => 1,
            'weight_override' => 2.0,
        ]);

        // Coba edit soal -> ditolak 422
        $editRes = $this->actingAs($this->userMtk)
            ->putJson("/api/v1/me/teacher/questions/{$question->id}", [
                'body' => ['text' => 'Ubah teks saat ujian berlangsung'],
            ]);
        $editRes->assertStatus(422)
            ->assertJsonPath('error.code', 'BUSINESS_RULE_VIOLATION');

        // Coba hapus soal -> ditolak 422
        $delRes = $this->actingAs($this->userMtk)
            ->deleteJson("/api/v1/me/teacher/questions/{$question->id}");
        $delRes->assertStatus(422)
            ->assertJsonPath('error.code', 'BUSINESS_RULE_VIOLATION');
    }

    /**
     * Guru dapat membuat ujian dari bank soal dan menjadwalkan ke kelas yang diampu.
     */
    public function test_teacher_can_create_exam_from_bank_and_schedule_to_taught_class(): void
    {
        $bank = QuestionBank::create([
            'school_id' => $this->userMtk->school_id,
            'subject_id' => $this->subjectMtk->id,
            'grade_level_id' => $this->gradeLevel7->id,
            'owner_id' => $this->userMtk->id,
            'name' => 'Bank Soal Kuis Aljabar',
        ]);

        $q = Question::create([
            'bank_id' => $bank->id,
            'question_type' => 'pg',
            'body' => ['text' => 'Soal Kuis 1'],
            'cognitive_level' => 'C1',
            'difficulty' => 1,
            'weight' => 5.0,
        ]);

        $response = $this->actingAs($this->userMtk)
            ->postJson('/api/v1/me/teacher/exams', [
                'title' => 'Kuis Harian Aljabar 7-A',
                'subject_id' => $this->subjectMtk->id,
                'class_ids' => [$this->class7A->id], // Kelas 7-A diampu
                'bank_id' => $bank->id,
                'duration_min' => 30,
                'start_at' => now()->addDay()->toDateTimeString(),
                'end_at' => now()->addDay()->addHour()->toDateTimeString(),
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.title', 'Kuis Harian Aljabar 7-A');

        $this->assertDatabaseHas('exams', [
            'title' => 'Kuis Harian Aljabar 7-A',
            'created_by' => $this->userMtk->id,
        ]);
    }

    /**
     * Guru dilarang menjadwalkan ujian ke kelas yang TIDAK diampu (403).
     */
    public function test_teacher_cannot_schedule_exam_to_untaught_class(): void
    {
        $bank = QuestionBank::create([
            'school_id' => $this->userMtk->school_id,
            'subject_id' => $this->subjectMtk->id,
            'grade_level_id' => $this->gradeLevel7->id,
            'owner_id' => $this->userMtk->id,
            'name' => 'Bank Soal MTK',
        ]);

        $response = $this->actingAs($this->userMtk)
            ->postJson('/api/v1/me/teacher/exams', [
                'title' => 'Ujian Tak Berizin di 7-B',
                'subject_id' => $this->subjectMtk->id,
                'class_ids' => [$this->class7B->id], // Guru MTK tidak mengampu 7-B
                'bank_id' => $bank->id,
                'duration_min' => 30,
                'start_at' => now()->addDay()->toDateTimeString(),
                'end_at' => now()->addDay()->addHour()->toDateTimeString(),
            ]);

        $response->assertStatus(403);
    }
}
