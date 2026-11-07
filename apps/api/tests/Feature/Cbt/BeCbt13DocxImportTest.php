<?php

namespace Tests\Feature\Cbt;

use App\Models\GradeLevel;
use App\Models\Permission;
use App\Models\QuestionBank;
use App\Models\Role;
use App\Models\School;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Tests\TestCase;

/**
 * BE-CBT-13: Pengujian alur impor naskah soal dari DOCX (Mandiri Admin/Guru).
 */
class BeCbt13DocxImportTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $teacherUserA;

    protected User $teacherUserB;

    protected Teacher $teacherA;

    protected Teacher $teacherB;

    protected QuestionBank $bankA;

    protected Subject $subject;

    protected GradeLevel $gradeLevel;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Roles & Permissions
        $bankWritePerm = Permission::firstOrCreate(['code' => 'cbt.bank.write'], ['description' => 'Kelola Bank Soal']);
        $adminRole = Role::firstOrCreate(['code' => 'admin'], ['name' => 'Administrator', 'is_system' => true]);
        $teacherRole = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);

        $adminRole->permissions()->syncWithoutDetaching([$bankWritePerm->id]);
        $teacherRole->permissions()->syncWithoutDetaching([$bankWritePerm->id]);

        $school = School::firstOrCreate(['id' => '00000000-0000-0000-0000-000000000001'], ['name' => 'SMP Laboratorium UM', 'npsn' => '20533807']);

        // 2. Users & Profiles
        $this->adminUser = User::create([
            'username' => 'admin.cbt',
            'email' => 'admin.cbt@labum.sch.id',
            'password_hash' => Hash::make('Secret123!'),
            'status' => 'active',
            'school_id' => $school->id,
        ]);
        $this->adminUser->roles()->attach($adminRole->id);

        $this->teacherUserA = User::create([
            'username' => 'guru.fauzan',
            'email' => 'guru.fauzan@labum.sch.id',
            'password_hash' => Hash::make('Secret123!'),
            'status' => 'active',
            'school_id' => $school->id,
        ]);
        $this->teacherUserA->roles()->attach($teacherRole->id);

        $this->teacherA = Teacher::create([
            'user_id' => $this->teacherUserA->id,
            'nip' => '198701012010011001',
            'full_name' => 'Fauzan Maulana, M.Pd.',
            'status' => 'aktif',
        ]);

        $this->teacherUserB = User::create([
            'username' => 'guru.siti',
            'email' => 'guru.siti@labum.sch.id',
            'password_hash' => Hash::make('Secret123!'),
            'status' => 'active',
            'school_id' => $school->id,
        ]);
        $this->teacherUserB->roles()->attach($teacherRole->id);

        $this->teacherB = Teacher::create([
            'user_id' => $this->teacherUserB->id,
            'nip' => '198902022012012002',
            'full_name' => 'Siti Nurhaliza, S.Pd.',
            'status' => 'aktif',
        ]);

        // 3. Master Data & Question Bank
        $this->subject = Subject::create([
            'code' => 'MTK-9',
            'name' => 'Matematika Kelas 9',
            'subject_group' => 'wajib',
        ]);

        $this->gradeLevel = GradeLevel::create([
            'name' => 'Kelas 9',
            'sort_order' => 9,
        ]);

        $this->bankA = QuestionBank::create([
            'school_id' => $school->id,
            'subject_id' => $this->subject->id,
            'grade_level_id' => $this->gradeLevel->id,
            'owner_id' => $this->teacherUserA->id,
            'name' => 'Bank Soal Latihan SAS Matematika 9',
        ]);
    }

    /**
     * Helper untuk membuat berkas DOCX valid secara dinamis via PhpWord.
     */
    protected function createValidDocx(array $lines): UploadedFile
    {
        $phpWord = new PhpWord;
        $section = $phpWord->addSection();
        foreach ($lines as $line) {
            $section->addText($line);
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'test_docx_').'.docx';
        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($tempPath);

        return new UploadedFile(
            $tempPath,
            'naskah_soal_cbt.docx',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            null,
            true
        );
    }

    /**
     * AC1 & AC4: Alur Lengkap Happy Path: Upload DOCX -> Parse -> Pratinjau Draf -> Commit.
     */
    public function test_be_cbt_13_happy_path_upload_parse_preview_commit(): void
    {
        // 1. Siapkan DOCX dengan 3 butir soal (PG, BS, ISIAN)
        $file = $this->createValidDocx([
            '1. [PG] [BOBOT: 2] [LEVEL: MOTS]',
            'Sebuah kerucut memiliki jari-jari alas 7 cm dan tinggi 24 cm. Berapakah panjang garis pelukisnya?',
            'A. 20 cm',
            'B. 25 cm',
            'C. 30 cm',
            'D. 35 cm',
            'KUNCI: B',
            'PEMBAHASAN: s = sqrt(7^2 + 24^2) = 25 cm.',
            '',
            '2. [BS] [BOBOT: 1] [LEVEL: LOTS]',
            'Kerucut memiliki tepat satu titik puncak dan dua rusuk.',
            'A. Benar',
            'B. Salah',
            'KUNCI: B',
            '',
            '3. [ISIAN] [BOBOT: 3] [LEVEL: HOTS]',
            'Bangun ruang yang dibatasi oleh sebuah sisi lengkung dan dua sisi lingkaran yang kongruen disebut ...',
            'KUNCI: Tabung',
        ]);

        // 2. Upload berkas ke endpoint POST /api/v1/question-imports
        $uploadRes = $this->actingAs($this->teacherUserA)
            ->postJson('/api/v1/question-imports', [
                'bank_id' => $this->bankA->id,
                'file' => $file,
            ]);

        $uploadRes->assertStatus(201)
            ->assertJsonPath('data.bank_id', $this->bankA->id);

        $jobId = $uploadRes->json('data.id');
        $this->assertNotNull($jobId);

        // 3. Lihat status & pratinjau draf soal via GET /api/v1/question-imports/{id} (AC3)
        $showRes = $this->actingAs($this->teacherUserA)
            ->getJson('/api/v1/question-imports/'.$jobId);

        $showRes->assertStatus(200)
            ->assertJsonPath('data.status', 'parsed')
            ->assertJsonCount(3, 'data.parsed_payload.questions')
            ->assertJsonCount(0, 'data.parse_errors');

        // Pastikan kunci jawaban terlihat oleh penyusun
        $questions = $showRes->json('data.parsed_payload.questions');
        $this->assertEquals('pg', $questions[0]['question_type']);
        $this->assertTrue($questions[0]['options'][1]['is_correct']); // B is correct
        $this->assertEquals(2.0, $questions[0]['weight']);

        // 4. Komit draf ke bank soal resmi via POST /api/v1/question-imports/{id}/commit (AC4)
        $commitRes = $this->actingAs($this->teacherUserA)
            ->withHeader('Idempotency-Key', 'idemp-cbt-commit-001')
            ->postJson('/api/v1/question-imports/'.$jobId.'/commit');

        $commitRes->assertStatus(200)
            ->assertJsonPath('data.imported_count', 3);

        // Verifikasi database: soal dan opsi tersimpan permanen
        $this->assertDatabaseHas('questions', [
            'bank_id' => $this->bankA->id,
            'question_type' => 'pg',
        ]);

        $this->assertDatabaseHas('question_import_jobs', [
            'id' => $jobId,
            'status' => 'committed',
            'imported_count' => 3,
        ]);

        // Verifikasi audit_logs bertambah
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'question.import',
            'actor_id' => $this->teacherUserA->id,
        ]);
    }

    /**
     * AC2: Validasi gagal bila berkas bukan .docx, ukuran melebihi batas, atau magic bytes salah.
     */
    public function test_be_cbt_13_validation_failure_non_docx_or_oversize(): void
    {
        // 1. Berkas teks biasa yang berekstensi .docx palsu (magic bytes bukan PK\x03\x04)
        $fakeDocxPath = tempnam(sys_get_temp_dir(), 'fake_').'.docx';
        file_put_contents($fakeDocxPath, 'Ini adalah berkas teks biasa, bukan zip/openxml');
        $fakeDocx = new UploadedFile($fakeDocxPath, 'palsu.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true);

        $res1 = $this->actingAs($this->teacherUserA)
            ->postJson('/api/v1/question-imports', [
                'bank_id' => $this->bankA->id,
                'file' => $fakeDocx,
            ]);

        $res1->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        // 2. Berkas PDF (bukan docx)
        $pdfFile = UploadedFile::fake()->create('naskah.pdf', 200, 'application/pdf');
        $res2 = $this->actingAs($this->teacherUserA)
            ->postJson('/api/v1/question-imports', [
                'bank_id' => $this->bankA->id,
                'file' => $pdfFile,
            ]);

        $res2->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    /**
     * AC3: Akses tanpa token ditolak 401.
     */
    public function test_be_cbt_13_unauthenticated_rejected_401(): void
    {
        $res = $this->getJson('/api/v1/question-imports/template');
        $res->assertStatus(401);

        $res2 = $this->postJson('/api/v1/question-imports', []);
        $res2->assertStatus(401);
    }

    /**
     * AC6: Guru mencoba mengimpor atau mengelola bank soal milik guru lain ditolak 403 (Cakupan Data).
     */
    public function test_be_cbt_13_forbidden_when_teacher_imports_to_another_bank_403(): void
    {
        $file = $this->createValidDocx([
            '1. [PG] Soal Contoh?',
            'A. Opsi 1',
            'B. Opsi 2',
            'KUNCI: A',
        ]);

        // Guru B mencoba mengimpor ke bank soal milik Guru A
        $res = $this->actingAs($this->teacherUserB)
            ->postJson('/api/v1/question-imports', [
                'bank_id' => $this->bankA->id,
                'file' => $file,
            ]);

        $res->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    /**
     * AC7: Aturan Bisnis: Soal PG tanpa kunci jawaban atau kunci ganda ditandai error pada parse_errors.
     */
    public function test_be_cbt_13_business_rule_pg_without_exact_single_correct_key_flagged(): void
    {
        // Butir 1 tidak ada kunci jawaban sama sekali
        $file = $this->createValidDocx([
            '1. [PG] [BOBOT: 2]',
            'Sebuah segitiga siku-siku memiliki alas 6 cm dan tinggi 8 cm.',
            'A. 10 cm',
            'B. 12 cm',
            'C. 14 cm',
            'D. 16 cm',
            // Tidak ada KUNCI: X
        ]);

        $uploadRes = $this->actingAs($this->teacherUserA)
            ->postJson('/api/v1/question-imports', [
                'bank_id' => $this->bankA->id,
                'file' => $file,
            ]);

        $uploadRes->assertStatus(201);
        $jobId = $uploadRes->json('data.id');

        $showRes = $this->actingAs($this->teacherUserA)
            ->getJson('/api/v1/question-imports/'.$jobId);

        $showRes->assertStatus(200);
        // Memastikan kesalahan terdeteksi di parse_errors (bukan disimpan diam-diam)
        $this->assertNotEmpty($showRes->json('data.parse_errors'));
        $this->assertStringContainsString('wajib memiliki tepat 1 kunci jawaban', $showRes->json('data.parse_errors.0.error'));

        // Mencoba commit saat masih ada error ditolak 422
        $commitRes = $this->actingAs($this->teacherUserA)
            ->postJson('/api/v1/question-imports/'.$jobId.'/commit');

        $commitRes->assertStatus(422);

        // Guru melakukan koreksi draf via PATCH /api/v1/question-imports/{id} (AC4)
        $correctedQuestions = $showRes->json('data.parsed_payload.questions');
        $correctedQuestions[0]['options'][0]['is_correct'] = true; // Tandai opsi A benar

        $patchRes = $this->actingAs($this->teacherUserA)
            ->patchJson('/api/v1/question-imports/'.$jobId, [
                'questions' => $correctedQuestions,
            ]);

        $patchRes->assertStatus(200)
            ->assertJsonCount(0, 'data.parse_errors');

        // Setelah dikoreksi, komit berhasil
        $commitRes2 = $this->actingAs($this->teacherUserA)
            ->postJson('/api/v1/question-imports/'.$jobId.'/commit');

        $commitRes2->assertStatus(200)
            ->assertJsonPath('data.imported_count', 1);
    }

    /**
     * AC5: Commit dengan Idempotency-Key sama dua kali tidak menggandakan soal.
     */
    public function test_be_cbt_13_commit_idempotency_prevents_duplicate_persisting(): void
    {
        $file = $this->createValidDocx([
            '1. [PG] Berapakah 2 + 2?',
            'A. 3',
            'B. 4',
            'KUNCI: B',
        ]);

        $uploadRes = $this->actingAs($this->teacherUserA)
            ->postJson('/api/v1/question-imports', [
                'bank_id' => $this->bankA->id,
                'file' => $file,
            ]);

        $jobId = $uploadRes->json('data.id');
        $idempKey = 'idemp-key-unique-789';

        // Commit pertama
        $res1 = $this->actingAs($this->teacherUserA)
            ->withHeader('Idempotency-Key', $idempKey)
            ->postJson('/api/v1/question-imports/'.$jobId.'/commit');
        $res1->assertStatus(200);

        $initialCount = QuestionBank::find($this->bankA->id)->questions()->count();

        // Commit kedua dengan kunci yang sama
        $res2 = $this->actingAs($this->teacherUserA)
            ->withHeader('Idempotency-Key', $idempKey)
            ->postJson('/api/v1/question-imports/'.$jobId.'/commit');
        $res2->assertStatus(200);

        $secondCount = QuestionBank::find($this->bankA->id)->questions()->count();
        $this->assertEquals($initialCount, $secondCount, 'Idempotency gagal: soal berganda pada commit berulang.');
    }

    /**
     * Unduh berkas template DOCX resmi (AC1).
     */
    public function test_be_cbt_13_template_download(): void
    {
        $res = $this->actingAs($this->teacherUserA)
            ->get('/api/v1/question-imports/template?format=docx');

        $res->assertStatus(200)
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    }
}
