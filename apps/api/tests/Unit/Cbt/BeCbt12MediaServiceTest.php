<?php

namespace Tests\Unit\Cbt;

use App\Models\File;
use App\Models\QuestionBank;
use App\Models\School;
use App\Models\Subject;
use App\Models\User;
use App\Services\Cbt\QuestionMediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class BeCbt12MediaServiceTest extends TestCase
{
    use RefreshDatabase;

    protected QuestionMediaService $service;

    protected User $user;

    protected QuestionBank $bank;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->service = app(QuestionMediaService::class);

        $school = School::firstOrCreate(
            ['id' => '00000000-0000-0000-0000-000000000001'],
            ['name' => 'SMP Laboratorium UM', 'npsn' => '20533807', 'is_active' => true]
        );

        $this->user = User::firstOrCreate(
            ['username' => 'guru_ipa_media'],
            [
                'school_id' => $school->id,
                'email' => 'guru.media@labschool.sch.id',
                'password_hash' => Hash::make('Password123!'),
                'status' => 'active',
                'must_change_password' => false,
            ]
        );

        $subject = Subject::firstOrCreate(
            ['code' => 'IPA_MEDIA', 'school_id' => $school->id],
            ['name' => 'Ilmu Pengetahuan Alam', 'subject_group' => 'umum']
        );

        $this->bank = QuestionBank::firstOrCreate(
            ['name' => 'Bank Media CBT'],
            [
                'school_id' => $school->id,
                'subject_id' => $subject->id,
                'owner_id' => $this->user->id,
            ]
        );
    }

    /**
     * TEST-BE-CBT-12-01: Menolak berkas yang bukan gambar (misal pdf).
     */
    public function test_be_cbt_12_upload_rejects_non_image_mime(): void
    {
        $file = UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf');

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Hanya berkas gambar (JPEG, PNG, WebP, GIF) yang diperbolehkan.');

        $this->service->upload($file, $this->bank->id, $this->user);
    }

    /**
     * TEST-BE-CBT-12-02: Menolak berkas gambar berukuran lebih dari 2 MB (2.097.152 byte).
     */
    public function test_be_cbt_12_upload_rejects_over_2mb(): void
    {
        // 2049 KB = ~2.098.176 byte (> 2 MB)
        $file = UploadedFile::fake()->image('gambar_besar.jpg')->size(2050);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Ukuran gambar tidak boleh melebihi 2 MB.');

        $this->service->upload($file, $this->bank->id, $this->user);
    }

    /**
     * TEST-BE-CBT-12-03: Menolak berkas ekstensi gambar palsu (magic bytes bukan gambar).
     */
    public function test_be_cbt_12_upload_rejects_fake_magic_bytes(): void
    {
        // Berkas teks diberi nama palsu image.png
        $fakeContent = '<?php echo "Hacked"; ?>';
        $file = UploadedFile::fake()->createWithContent('malicious.png', $fakeContent);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Berkas bukan gambar yang valid (verifikasi magic bytes gagal).');

        $this->service->upload($file, $this->bank->id, $this->user);
    }

    /**
     * TEST-BE-CBT-12-04: Berkas gambar valid disimpan privat dan mengembalikan file_id serta preview_url.
     */
    public function test_be_cbt_12_upload_stores_private_and_returns_ids(): void
    {
        $file = UploadedFile::fake()->image('sel_tumbuhan.png', 400, 300);

        $res = $this->service->upload($file, $this->bank->id, $this->user);

        $this->assertArrayHasKey('file_id', $res);
        $this->assertArrayHasKey('mime_type', $res);
        $this->assertArrayHasKey('preview_url', $res);
        $this->assertStringContainsString('/download', $res['preview_url']);
        $this->assertStringContainsString('signature=', $res['preview_url']);

        $dbFile = File::findOrFail($res['file_id']);
        $this->assertEquals('privat', $dbFile->visibility);
        $this->assertEquals('cbt', $dbFile->bucket);
        $this->assertEquals('question_bank', $dbFile->owner_type);
        $this->assertEquals($this->bank->id, $dbFile->owner_id);

        Storage::disk('local')->assertExists($dbFile->object_key);
    }

    /**
     * TEST-BE-CBT-12-05: resolveMediaInHtml menyuntikkan src bertanda-tangan untuk data-file-id yang terdaftar.
     */
    public function test_be_cbt_12_resolve_injects_src_for_known_file_id(): void
    {
        $dbFile = File::create([
            'school_id' => $this->user->school_id,
            'owner_id' => $this->bank->id,
            'owner_type' => 'question_bank',
            'bucket' => 'cbt',
            'object_key' => 'cbt/2026/10/valid-test.png',
            'mime_type' => 'image/png',
            'size_bytes' => 1024,
            'visibility' => 'privat',
        ]);

        $html = '<p>Lihat gambar:</p><img data-file-id="'.$dbFile->id.'" alt="Diagram">';
        $resolved = $this->service->resolveMediaInHtml($html);

        $this->assertStringContainsString('data-file-id="'.$dbFile->id.'"', $resolved);
        $this->assertStringContainsString('src=', $resolved);
        $this->assertStringContainsString($dbFile->id, $resolved);
        $this->assertStringContainsString('signature=', $resolved);
    }

    /**
     * TEST-BE-CBT-12-06: resolveMediaInHtml mengabaikan data-file-id yang tidak dikenal di database (tidak menyuntikkan src).
     */
    public function test_be_cbt_12_resolve_ignores_unknown_id(): void
    {
        $unknownId = '00000000-0000-0000-0000-999999999999';
        $html = '<p>Gambar hantu:</p><img data-file-id="'.$unknownId.'" alt="Ghost">';
        $resolved = $this->service->resolveMediaInHtml($html);

        $this->assertStringContainsString('data-file-id="'.$unknownId.'"', $resolved);
        $this->assertStringNotContainsString('src=', $resolved);
    }
}
