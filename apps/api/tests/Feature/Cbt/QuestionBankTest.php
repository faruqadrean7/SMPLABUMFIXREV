<?php

namespace Tests\Feature\Cbt;

use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\GradeLevel;
use App\Models\Permission;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\QuestionOption;
use App\Models\Role;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class QuestionBankTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacherUser;

    protected User $adminUser;

    protected User $studentUser;

    protected Teacher $teacher;

    protected Subject $subjectMath;

    protected GradeLevel $gradeLevel10;

    protected QuestionBank $bank;

    protected function setUp(): void
    {
        parent::setUp();

        $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);
        $roleAdmin = Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Admin', 'is_system' => true]);
        $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);

        $permCbtWrite = Permission::firstOrCreate(['code' => 'cbt.bank.write'], ['description' => 'Mengelola bank soal']);
        $roleTeacher->permissions()->syncWithoutDetaching([$permCbtWrite->id]);
        $roleAdmin->permissions()->syncWithoutDetaching([$permCbtWrite->id]);

        $this->teacherUser = User::create([
            'username' => 'guru_cbt',
            'email' => 'guru.cbt@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->teacherUser->roles()->attach($roleTeacher->id);

        $this->adminUser = User::create([
            'username' => 'admin_cbt',
            'email' => 'admin.cbt@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($roleAdmin->id);

        $this->studentUser = User::create([
            'username' => 'siswa_cbt',
            'email' => 'siswa.cbt@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->studentUser->roles()->attach($roleStudent->id);

        $this->teacher = Teacher::create([
            'user_id' => $this->teacherUser->id,
            'nip' => '198801012011011003',
            'full_name' => 'Ahmad Dahlan, S.Si',
            'status' => 'active',
        ]);

        $this->subjectMath = Subject::create([
            'code' => 'MAT-10',
            'name' => 'Matematika Wajib X',
            'subject_group' => 'Kelompok A',
        ]);

        $this->gradeLevel10 = GradeLevel::create([
            'name' => 'Kelas 10',
            'level' => 10,
        ]);

        $this->bank = QuestionBank::create([
            'name' => 'Bank Soal Aljabar & Fungsi Kuadrat',
            'subject_id' => $this->subjectMath->id,
            'grade_level_id' => $this->gradeLevel10->id,
            'owner_id' => $this->teacherUser->id,
        ]);
    }

    /**
     * BE-CBT-01: CRUD paket bank soal (question-banks).
     */
    public function test_be_cbt_01_crud_question_bank(): void
    {
        // 1. Buat Bank Soal
        $responseStore = $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson('/api/v1/question-banks', [
                'name' => 'Bank Soal Trigonometri Dasar',
                'subject_id' => $this->subjectMath->id,
                'grade_level_id' => $this->gradeLevel10->id,
            ]);

        $responseStore->assertStatus(201)
            ->assertJsonPath('data.name', 'Bank Soal Trigonometri Dasar')
            ->assertJsonPath('data.subject_id', $this->subjectMath->id);

        $bankId = $responseStore->json('data.id');

        // 2. Daftar Bank Soal
        $responseIndex = $this->actingAs($this->teacherUser, 'sanctum')
            ->getJson('/api/v1/question-banks');

        $responseIndex->assertStatus(200)
            ->assertJsonCount(2, 'data');

        // 3. Detail Bank Soal
        $responseShow = $this->actingAs($this->teacherUser, 'sanctum')
            ->getJson("/api/v1/question-banks/{$bankId}");

        $responseShow->assertStatus(200)
            ->assertJsonPath('data.id', $bankId);

        // 4. Update Bank Soal
        $responseUpdate = $this->actingAs($this->teacherUser, 'sanctum')
            ->putJson("/api/v1/question-banks/{$bankId}", [
                'name' => 'Bank Soal Trigonometri Lanjutan',
                'subject_id' => $this->subjectMath->id,
            ]);

        $responseUpdate->assertStatus(200)
            ->assertJsonPath('data.name', 'Bank Soal Trigonometri Lanjutan');

        // 5. Hapus Bank Soal
        $responseDelete = $this->actingAs($this->teacherUser, 'sanctum')
            ->deleteJson("/api/v1/question-banks/{$bankId}");

        $responseDelete->assertStatus(200);
        $this->assertDatabaseMissing('question_banks', ['id' => $bankId]);
    }

    /**
     * BE-CBT-01: Pembuatan butir soal Pilihan Ganda (PG) lengkap dengan formula LaTeX dan opsi jawaban.
     */
    public function test_be_cbt_01_create_multiple_choice_question_with_options(): void
    {
        $response = $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson('/api/v1/questions', [
                'bank_id' => $this->bank->id,
                'question_type' => 'pg',
                'body' => [
                    'content' => 'Tentukan himpunan penyelesaian dari persamaan kuadrat berikut.',
                    'formula' => 'x^2 - 5x + 6 = 0',
                ],
                'cp_code' => 'CP-MAT-01',
                'cognitive_level' => 'MOTS',
                'difficulty' => 3,
                'weight' => 2.0,
                'explanation' => 'Faktorkan menjadi (x - 2)(x - 3) = 0 sehingga x = 2 atau x = 3.',
                'status' => 'aktif',
                'options' => [
                    ['label' => 'A', 'body' => ['text' => '{2, 3}'], 'is_correct' => true],
                    ['label' => 'B', 'body' => ['text' => '{-2, -3}'], 'is_correct' => false],
                    ['label' => 'C', 'body' => ['text' => '{1, 6}'], 'is_correct' => false],
                    ['label' => 'D', 'body' => ['text' => '{-1, -6}'], 'is_correct' => false],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.question_type', 'pg')
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.cognitive_level', 'MOTS')
            ->assertJsonCount(4, 'data.options');

        $questionId = $response->json('data.id');
        $this->assertDatabaseHas('questions', [
            'id' => $questionId,
            'question_type' => 'pg',
            'version' => 1,
        ]);

        $this->assertDatabaseHas('question_options', [
            'question_id' => $questionId,
            'label' => 'A',
            'is_correct' => true,
        ]);
    }

    /**
     * BE-CBT-01: Pembuatan butir soal Pilihan Ganda Kompleks (PG Kompleks) dan Benar/Salah (BS).
     */
    public function test_be_cbt_01_create_complex_multiple_choice_and_true_false_questions(): void
    {
        // 1. PG Kompleks (multi-jawaban benar)
        $responseComplex = $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson('/api/v1/questions', [
                'bank_id' => $this->bank->id,
                'question_type' => 'pg_kompleks',
                'body' => ['content' => 'Pilihlah semua bilangan yang merupakan bilangan prima.'],
                'cognitive_level' => 'HOTS',
                'difficulty' => 4,
                'options' => [
                    ['label' => 'A', 'body' => ['text' => '2'], 'is_correct' => true],
                    ['label' => 'B', 'body' => ['text' => '4'], 'is_correct' => false],
                    ['label' => 'C', 'body' => ['text' => '5'], 'is_correct' => true],
                    ['label' => 'D', 'body' => ['text' => '9'], 'is_correct' => false],
                ],
            ]);

        $responseComplex->assertStatus(201)
            ->assertJsonPath('data.question_type', 'pg_kompleks');

        // 2. Benar / Salah (BS)
        $responseTF = $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson('/api/v1/questions', [
                'bank_id' => $this->bank->id,
                'question_type' => 'bs',
                'body' => ['content' => 'Apakah rumus keliling lingkaran adalah 2 * pi * r?'],
                'cognitive_level' => 'LOTS',
                'difficulty' => 1,
                'options' => [
                    ['label' => 'A', 'body' => ['text' => 'Benar'], 'is_correct' => true],
                    ['label' => 'B', 'body' => ['text' => 'Salah'], 'is_correct' => false],
                ],
            ]);

        $responseTF->assertStatus(201)
            ->assertJsonPath('data.question_type', 'bs');
    }

    /**
     * BE-CBT-01: Pembuatan butir soal Menjodohkan (Matching) dengan pasangan match_key.
     */
    public function test_be_cbt_01_create_matching_question_with_match_key(): void
    {
        $response = $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson('/api/v1/questions', [
                'bank_id' => $this->bank->id,
                'question_type' => 'menjodohkan',
                'body' => ['content' => 'Jodohkan bangun datar berikut dengan rumus luas yang sesuai.'],
                'cognitive_level' => 'MOTS',
                'difficulty' => 3,
                'options' => [
                    ['label' => 'A', 'body' => ['text' => 'Persegi'], 'match_key' => 'sisi x sisi'],
                    ['label' => 'B', 'body' => ['text' => 'Segitiga'], 'match_key' => '1/2 x alas x tinggi'],
                    ['label' => 'C', 'body' => ['text' => 'Lingkaran'], 'match_key' => 'pi x r^2'],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.question_type', 'menjodohkan');

        $this->assertDatabaseHas('question_options', [
            'label' => 'A',
            'match_key' => 'sisi x sisi',
        ]);
    }

    /**
     * BE-CBT-01: Pembuatan butir soal Isian Singkat dan Uraian (Essay).
     */
    public function test_be_cbt_01_create_short_answer_and_essay_questions(): void
    {
        // Isian singkat
        $responseShort = $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson('/api/v1/questions', [
                'bank_id' => $this->bank->id,
                'question_type' => 'isian',
                'body' => ['content' => 'Berapakah nilai dari 2 pangkat 5?'],
                'explanation' => 'Kunci jawaban: 32',
                'difficulty' => 2,
            ]);

        $responseShort->assertStatus(201)
            ->assertJsonPath('data.question_type', 'isian');

        // Uraian / Essay
        $responseEssay = $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson('/api/v1/questions', [
                'bank_id' => $this->bank->id,
                'question_type' => 'uraian',
                'body' => ['content' => 'Jelaskan langkah-langkah pembuktian teorema Pythagoras.'],
                'explanation' => 'Rubrik penilaian: langkah awal (20%), gambar (30%), penjabaran aljabar (50%).',
                'difficulty' => 5,
            ]);

        $responseEssay->assertStatus(201)
            ->assertJsonPath('data.question_type', 'uraian');
    }

    /**
     * BE-CBT-01: Soal yang sudah dipakai dalam ujian terkunci edit -> otomatis menghasilkan versi baru (version + 1).
     */
    public function test_be_cbt_01_question_used_in_exam_creates_new_version_on_edit(): void
    {
        $question = Question::create([
            'bank_id' => $this->bank->id,
            'question_type' => 'pg',
            'body' => ['content' => 'Pertanyaan Versi 1'],
            'version' => 1,
            'status' => 'aktif',
        ]);

        QuestionOption::create([
            'question_id' => $question->id,
            'label' => 'A',
            'body' => ['text' => 'Opsi A v1'],
            'is_correct' => true,
        ]);

        // Buat Exam dan kaitkan question_id ke exam_questions
        $exam = Exam::create([
            'subject_id' => $this->subjectMath->id,
            'created_by' => $this->teacherUser->id,
            'title' => 'Ujian Akhir Semester CBT',
            'exam_type' => 'SAS',
            'duration_min' => 90,
            'start_at' => now()->subDay(),
            'end_at' => now()->addDay(),
            'status' => 'berlangsung',
        ]);

        ExamQuestion::create([
            'exam_id' => $exam->id,
            'question_id' => $question->id,
            'sort_order' => 1,
        ]);

        // Edit butir soal yang sudah digunakan di ujian
        $responseEdit = $this->actingAs($this->teacherUser, 'sanctum')
            ->putJson("/api/v1/questions/{$question->id}", [
                'body' => ['content' => 'Pertanyaan Telah Diperbarui untuk Versi 2'],
                'options' => [
                    ['label' => 'A', 'body' => ['text' => 'Opsi A v2 (Revisi)'], 'is_correct' => true],
                    ['label' => 'B', 'body' => ['text' => 'Opsi B v2'], 'is_correct' => false],
                ],
            ]);

        $responseEdit->assertStatus(200);
        $newQuestionId = $responseEdit->json('data.id');

        // ID baru dibuat untuk versi baru, version bernilai 2
        $this->assertNotEquals($question->id, $newQuestionId);
        $this->assertEquals(2, $responseEdit->json('data.version'));
        $this->assertEquals('Pertanyaan Telah Diperbarui untuk Versi 2', $responseEdit->json('data.body.content'));

        // Soal lama versi 1 tetap terjaga di database
        $this->assertDatabaseHas('questions', [
            'id' => $question->id,
            'version' => 1,
        ]);
    }

    /**
     * BE-CBT-01: Soal yang belum dipakai dalam ujian dapat diedit langsung in-place (version tetap 1).
     */
    public function test_be_cbt_01_question_not_used_in_exam_updates_directly_in_place(): void
    {
        $question = Question::create([
            'bank_id' => $this->bank->id,
            'question_type' => 'pg',
            'body' => ['content' => 'Draft Pertanyaan Belum Ujian'],
            'version' => 1,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->teacherUser, 'sanctum')
            ->putJson("/api/v1/questions/{$question->id}", [
                'body' => ['content' => 'Draft Pertanyaan Diperbaiki'],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $question->id)
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.body.content', 'Draft Pertanyaan Diperbaiki');
    }

    /**
     * BE-CBT-01: Soal yang sudah dipakai dalam ujian tidak boleh dihapus.
     */
    public function test_be_cbt_01_cannot_delete_question_used_in_exam(): void
    {
        $question = Question::create([
            'bank_id' => $this->bank->id,
            'question_type' => 'pg',
            'body' => ['content' => 'Soal Terpakai Ujian'],
            'version' => 1,
            'status' => 'aktif',
        ]);

        $exam = Exam::create([
            'subject_id' => $this->subjectMath->id,
            'created_by' => $this->teacherUser->id,
            'title' => 'Ujian Harian CBT',
            'exam_type' => 'harian',
            'duration_min' => 60,
            'start_at' => now(),
            'end_at' => now()->addHours(2),
            'status' => 'berlangsung',
        ]);

        ExamQuestion::create([
            'exam_id' => $exam->id,
            'question_id' => $question->id,
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($this->teacherUser, 'sanctum')
            ->deleteJson("/api/v1/questions/{$question->id}");

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'BUSINESS_RULE_VIOLATION');

        $this->assertDatabaseHas('questions', ['id' => $question->id]);
    }

    /**
     * BE-CBT-01: Import massal butir soal ke dalam bank soal (POST /questions/import).
     */
    public function test_be_cbt_01_import_questions_bulk_to_question_bank(): void
    {
        $questionsPayload = [
            [
                'question_type' => 'pg',
                'body' => ['content' => 'Soal Import 1: Berapakah 10 + 20?'],
                'cognitive_level' => 'LOTS',
                'difficulty' => 1,
                'weight' => 1.0,
                'options' => [
                    ['label' => 'A', 'body' => ['text' => '30'], 'is_correct' => true],
                    ['label' => 'B', 'body' => ['text' => '40'], 'is_correct' => false],
                ],
            ],
            [
                'question_type' => 'pg',
                'body' => ['content' => 'Soal Import 2: Berapakah 5 x 6?'],
                'cognitive_level' => 'LOTS',
                'difficulty' => 2,
                'weight' => 1.0,
                'options' => [
                    ['label' => 'A', 'body' => ['text' => '30'], 'is_correct' => true],
                    ['label' => 'B', 'body' => ['text' => '25'], 'is_correct' => false],
                ],
            ],
            [
                'question_type' => 'isian',
                'body' => ['content' => 'Soal Import 3: Sebutkan ibukota Jawa Timur.'],
                'explanation' => 'Surabaya',
                'cognitive_level' => 'LOTS',
                'difficulty' => 1,
            ],
        ];

        $response = $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson('/api/v1/questions/import', [
                'bank_id' => $this->bank->id,
                'questions' => $questionsPayload,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.bank_id', $this->bank->id)
            ->assertJsonPath('data.imported_count', 3);

        $this->assertEquals(3, Question::where('bank_id', $this->bank->id)->count());
    }

    /**
     * BE-CBT-01: Filter daftar butir soal berdasarkan tipe, level kognitif, kesulitan, dan teks pencarian.
     */
    public function test_be_cbt_01_filter_questions_by_type_cognitive_level_and_search(): void
    {
        Question::create([
            'bank_id' => $this->bank->id,
            'question_type' => 'pg',
            'body' => ['content' => 'Analisis matriks ordo 3x3 lanjutan'],
            'cognitive_level' => 'HOTS',
            'difficulty' => 5,
        ]);

        Question::create([
            'bank_id' => $this->bank->id,
            'question_type' => 'bs',
            'body' => ['content' => 'Penjumlahan dasar'],
            'cognitive_level' => 'LOTS',
            'difficulty' => 1,
        ]);

        // Filter HOTS & difficulty 5
        $responseHots = $this->actingAs($this->teacherUser, 'sanctum')
            ->getJson("/api/v1/questions?bank_id={$this->bank->id}&cognitive_level=HOTS&difficulty=5");

        $responseHots->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.cognitive_level', 'HOTS');

        // Pencarian kata 'matriks'
        $responseSearch = $this->actingAs($this->teacherUser, 'sanctum')
            ->getJson("/api/v1/questions?bank_id={$this->bank->id}&search=matriks");

        $responseSearch->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    /**
     * BE-CBT-01: Pengguna tanpa wewenang (siswa) tidak dapat membuat atau mengubah butir soal CBT.
     */
    public function test_be_cbt_01_unauthorized_user_cannot_create_or_edit_questions(): void
    {
        $response = $this->actingAs($this->studentUser, 'sanctum')
            ->postJson('/api/v1/questions', [
                'bank_id' => $this->bank->id,
                'question_type' => 'pg',
                'body' => ['content' => 'Pertanyaan hacking siswa'],
            ]);

        $response->assertStatus(403);
    }

    /**
     * SEC-CBT: Siswa dilarang melihat daftar atau detail butir soal CBT dan paket bank soal secara langsung.
     */
    public function test_student_cannot_list_or_view_questions_and_banks(): void
    {
        $this->app['auth']->forgetGuards();

        // 1. Siswa dilarang membaca daftar bank soal
        $responseBankList = $this->actingAs($this->studentUser, 'sanctum')
            ->getJson('/api/v1/question-banks');
        $responseBankList->assertStatus(403);

        // 2. Siswa dilarang membaca detail bank soal
        $responseBankDetail = $this->actingAs($this->studentUser, 'sanctum')
            ->getJson("/api/v1/question-banks/{$this->bank->id}");
        $responseBankDetail->assertStatus(403);

        // 3. Siswa dilarang membaca butir soal dan kunci jawaban
        $responseQuestionList = $this->actingAs($this->studentUser, 'sanctum')
            ->getJson("/api/v1/questions?bank_id={$this->bank->id}");
        $responseQuestionList->assertStatus(403);
    }
}
