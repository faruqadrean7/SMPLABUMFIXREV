<?php

namespace Tests\Feature\Storage;

use App\Models\Complaint;
use App\Models\File;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FileUploadSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected User $userA;

    protected User $userB;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Storage::fake('local');
        Storage::fake('public');

        $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);

        $this->userA = User::create([
            'username' => 'upload_sec_a',
            'email' => 'upload_sec_a@sekolah.sch.id',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $this->userA->roles()->attach($roleStudent->id);

        $this->userB = User::create([
            'username' => 'upload_sec_b',
            'email' => 'upload_sec_b@sekolah.sch.id',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $this->userB->roles()->attach($roleStudent->id);
    }

    public function test_p0_04_upload_signed_rejects_missing_or_tampered_signature(): void
    {
        Sanctum::actingAs($this->userA);

        $presign = $this->postJson('/api/v1/files/presign', [
            'filename' => 'dokumen.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'visibility' => 'privat',
        ])->assertStatus(200);

        $token = $presign->json('data.token');
        $uploadUrl = $presign->json('data.upload_url');
        $validPdf = UploadedFile::fake()->createWithContent('dokumen.pdf', "%PDF-1.4\nhello world");

        // Tanpa signature query -> 403
        $this->post("/api/v1/files/upload-signed/{$token}", ['file' => $validPdf])
            ->assertStatus(403);

        // Signature dimanipulasi -> 403
        $this->post($uploadUrl.'&tampered=1', ['file' => $validPdf])
            ->assertStatus(403);
    }

    public function test_p0_04_presign_rejects_svg_xss_and_invalid_or_reserved_bucket(): void
    {
        Sanctum::actingAs($this->userA);

        // SVG dilarang (Stored XSS)
        $this->postJson('/api/v1/files/presign', [
            'filename' => 'logo.svg',
            'mime_type' => 'image/svg+xml',
            'size_bytes' => 512,
            'visibility' => 'publik',
        ])->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        // Path traversal di bucket
        $this->postJson('/api/v1/files/presign', [
            'filename' => 'foto.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 512,
            'bucket' => '../public',
        ])->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        // Bucket internal terproteksi
        $this->postJson('/api/v1/files/presign', [
            'filename' => 'foto.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 512,
            'bucket' => 'leave-attachments',
        ])->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function test_p0_04_upload_signed_rejects_spoofed_magic_bytes_and_embedded_scripts(): void
    {
        Sanctum::actingAs($this->userA);

        // 1. Deklarasi image/jpeg tapi isi PHP webshell
        $presignImg = $this->postJson('/api/v1/files/presign', [
            'filename' => 'fake.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 2048,
            'visibility' => 'publik',
        ])->assertStatus(200);

        $phpPayload = UploadedFile::fake()->createWithContent('fake.jpg', '<?php system($_GET["c"]); ?>');
        $this->post($presignImg->json('data.upload_url'), ['file' => $phpPayload])
            ->assertStatus(422);

        // 2. Deklarasi image/jpeg tapi isi SVG XSS
        $svgPayload = UploadedFile::fake()->createWithContent('fake.jpg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $this->post($presignImg->json('data.upload_url'), ['file' => $svgPayload])
            ->assertStatus(422);

        // 3. Deklarasi application/pdf tapi isi plain text bukan %PDF-
        $presignPdf = $this->postJson('/api/v1/files/presign', [
            'filename' => 'spoof.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 2048,
            'visibility' => 'privat',
        ])->assertStatus(200);

        $fakePdf = UploadedFile::fake()->createWithContent('spoof.pdf', 'Ini bukan berkas PDF asli');
        $this->post($presignPdf->json('data.upload_url'), ['file' => $fakePdf])
            ->assertStatus(422);
    }

    public function test_p0_04_confirm_upload_blocks_owner_spoofing_and_cross_user_token_hijack(): void
    {
        $complaint = Complaint::create([
            'ticket_code' => 'ASP-20261127-SP0F',
            'category' => 'bullying',
            'content' => 'Laporan asli',
            'is_anonymous' => true,
            'status' => 'baru',
        ]);

        Sanctum::actingAs($this->userA);
        $presign = $this->postJson('/api/v1/files/presign', [
            'filename' => 'berkas.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'visibility' => 'privat',
        ])->assertStatus(200);

        $token = $presign->json('data.token');
        $validPdf = UploadedFile::fake()->createWithContent('berkas.pdf', "%PDF-1.4\nkonten valid");
        $this->post($presign->json('data.upload_url'), ['file' => $validPdf])->assertStatus(200);

        // User B tidak boleh mengonfirmasi token milik User A -> 403
        Sanctum::actingAs($this->userB);
        $this->postJson('/api/v1/files/confirm', ['token' => $token])
            ->assertStatus(403);

        // User A mencoba spoofing owner_type='complaint' & owner_id=$complaint->id -> WAJIB diabaikan
        Sanctum::actingAs($this->userA);
        $confirm = $this->postJson('/api/v1/files/confirm', [
            'token' => $token,
            'owner_type' => 'complaint',
            'owner_id' => $complaint->id,
        ])->assertStatus(201);

        $fileId = $confirm->json('data.id');
        $saved = File::findOrFail($fileId);
        $this->assertEquals('User', $saved->owner_type);
        $this->assertEquals($this->userA->id, $saved->owner_id);
        $this->assertEquals(0, $complaint->attachments()->count());
    }

    public function test_p0_04_download_signed_includes_nosniff_security_header(): void
    {
        $objectKey = 'private/2026/11/secure-doc.pdf';
        Storage::disk('local')->put($objectKey, "%PDF-1.4\nisi dokumen");

        $file = File::create([
            'owner_id' => $this->userA->id,
            'owner_type' => 'User',
            'bucket' => 'private',
            'object_key' => $objectKey,
            'mime_type' => 'application/pdf',
            'size_bytes' => 20,
            'visibility' => 'privat',
        ]);

        $signedUrl = URL::temporarySignedRoute('files.download.signed', Carbon::now()->addMinutes(5), ['id' => $file->id]);

        $this->get($signedUrl)
            ->assertStatus(200)
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }
}
