<?php

namespace Tests\Feature\Storage;

use App\Models\File;
use App\Models\Guardian;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FileStorageTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $studentUser1;

    protected User $studentUser2;

    protected User $guardianUser;

    protected Student $student1;

    protected Student $student2;

    protected Guardian $guardian;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Storage::fake('local');
        Storage::fake('public');

        // Roles
        $roleAdmin = Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Admin', 'is_system' => true]);
        $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);
        $roleGuardian = Role::firstOrCreate(['code' => 'guardian'], ['name' => 'Wali Murid', 'is_system' => true]);

        // Admin
        $this->adminUser = User::create([
            'username' => 'admin_file',
            'email' => 'admin_file@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($roleAdmin->id);

        // Student 1
        $this->studentUser1 = User::create([
            'username' => 'student_file_1',
            'email' => 'student1_file@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->studentUser1->roles()->attach($roleStudent->id);

        $this->student1 = Student::create([
            'user_id' => $this->studentUser1->id,
            'nis' => '88001',
            'nisn' => '0088001',
            'nik_enc' => '3578010101018801',
            'full_name' => 'Aditya File 1',
            'gender' => 'L',
            'birth_place' => 'Surabaya',
            'birth_date' => '2008-01-01',
            'entry_date' => '2026-07-01',
            'status' => 'active',
        ]);

        // Student 2
        $this->studentUser2 = User::create([
            'username' => 'student_file_2',
            'email' => 'student2_file@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->studentUser2->roles()->attach($roleStudent->id);

        $this->student2 = Student::create([
            'user_id' => $this->studentUser2->id,
            'nis' => '88002',
            'nisn' => '0088002',
            'nik_enc' => '3578010101018802',
            'full_name' => 'Bunga File 2',
            'gender' => 'P',
            'birth_place' => 'Malang',
            'birth_date' => '2008-02-02',
            'entry_date' => '2026-07-01',
            'status' => 'active',
        ]);

        // Guardian linked to Student 1 only
        $this->guardianUser = User::create([
            'username' => 'guardian_file',
            'email' => 'guardian_file@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->guardianUser->roles()->attach($roleGuardian->id);

        $this->guardian = Guardian::create([
            'user_id' => $this->guardianUser->id,
            'full_name' => 'Wali Aditya',
            'phone' => '081234567800',
        ]);
        $this->guardian->students()->attach($this->student1->id, ['relationship' => 'ayah', 'is_primary' => true]);
    }

    public function test_be_file_01_unauthenticated_request_returns_401(): void
    {
        $fakeFileId = Str::uuid()->toString();

        $this->postJson('/api/v1/files/presign', [])->assertStatus(401);
        $this->postJson('/api/v1/files/confirm', [])->assertStatus(401);
        $this->getJson('/api/v1/files/'.$fakeFileId.'/url')->assertStatus(401);
    }

    public function test_be_file_01_user_cannot_presign_dangerous_executable_file(): void
    {
        Sanctum::actingAs($this->studentUser1);

        // Disallowed extension .exe
        $res1 = $this->postJson('/api/v1/files/presign', [
            'filename' => 'malware.exe',
            'mime_type' => 'application/x-msdownload',
            'size_bytes' => 1024,
        ]);
        $res1->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        // Disallowed extension .php
        $res2 = $this->postJson('/api/v1/files/presign', [
            'filename' => 'webshell.php',
            'mime_type' => 'text/x-php',
            'size_bytes' => 512,
        ]);
        $res2->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function test_be_file_01_user_cannot_presign_oversized_file(): void
    {
        Sanctum::actingAs($this->studentUser1);

        // Size 60MB (melebihi batas 50MB)
        $res = $this->postJson('/api/v1/files/presign', [
            'filename' => 'video_raksasa.mp4',
            'mime_type' => 'video/mp4',
            'size_bytes' => 62914560,
        ]);

        $res->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function test_be_file_01_user_can_presign_valid_file_with_randomized_key(): void
    {
        Sanctum::actingAs($this->studentUser1);

        $res = $this->postJson('/api/v1/files/presign', [
            'filename' => 'ijazah_smp.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 204800, // 200KB
            'visibility' => 'privat',
            'purpose' => 'student_document',
        ]);

        $res->assertStatus(200)
            ->assertJsonPath('data.visibility', 'privat')
            ->assertJsonPath('data.mime_type', 'application/pdf')
            ->assertJsonPath('data.size_bytes', 204800)
            ->assertJsonPath('data.ttl_seconds', 900);

        $this->assertNotEmpty($res->json('data.token'));
        $this->assertNotEmpty($res->json('data.upload_url'));
        $this->assertStringContainsString('private/', $res->json('data.object_key'));
        // Pastikan nama berkas diacak (UUID), bukan nama file mentah (09_SECURITY_PRIVACY)
        $this->assertStringNotContainsString('ijazah_smp.pdf', $res->json('data.object_key'));
        $this->assertStringEndsWith('.pdf', $res->json('data.object_key'));
    }

    public function test_be_file_01_end_to_end_upload_confirm_and_audit_logging(): void
    {
        Sanctum::actingAs($this->studentUser1);

        // 1. Presign
        $presignRes = $this->postJson('/api/v1/files/presign', [
            'filename' => 'sertifikat_juara.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 10240,
            'visibility' => 'privat',
        ]);
        $presignRes->assertStatus(200);

        $token = $presignRes->json('data.token');
        $uploadUrl = $presignRes->json('data.upload_url');

        // 2. Direct Upload via Signed URL
        $fakePdf = UploadedFile::fake()->createWithContent('sertifikat_juara.pdf', str_repeat('P', 10240));
        $uploadRes = $this->post($uploadUrl, [
            'file' => $fakePdf,
        ]);
        $uploadRes->assertStatus(200)
            ->assertJsonPath('data.token', $token);

        $objectKey = $uploadRes->json('data.object_key');
        $checksum = $uploadRes->json('data.checksum');

        // Verify storage file exists
        Storage::disk('local')->assertExists($objectKey);

        // 3. Confirm Upload
        $confirmRes = $this->postJson('/api/v1/files/confirm', [
            'token' => $token,
            'checksum' => $checksum,
            'owner_id' => $this->studentUser1->id,
            'owner_type' => 'User',
        ]);

        $confirmRes->assertStatus(201)
            ->assertJsonPath('data.object_key', $objectKey)
            ->assertJsonPath('data.visibility', 'privat')
            ->assertJsonPath('data.mime_type', 'application/pdf');

        $fileId = $confirmRes->json('data.id');

        $this->assertDatabaseHas('files', [
            'id' => $fileId,
            'object_key' => $objectKey,
            'visibility' => 'privat',
            'owner_id' => $this->studentUser1->id,
        ]);

        // Audit Log verification
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->studentUser1->id,
            'action' => 'FILE_UPLOAD_CONFIRMED',
            'entity' => 'files',
            'entity_id' => $fileId,
        ]);
    }

    public function test_be_file_01_confirm_fails_if_file_not_uploaded_to_storage(): void
    {
        Sanctum::actingAs($this->studentUser1);

        // Presign created
        $presignRes = $this->postJson('/api/v1/files/presign', [
            'filename' => 'foto.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 5000,
            'visibility' => 'privat',
        ]);
        $token = $presignRes->json('data.token');

        // Skip upload, try confirm directly
        $confirmRes = $this->postJson('/api/v1/files/confirm', [
            'token' => $token,
        ]);

        $confirmRes->assertStatus(422)
            ->assertJsonPath('error.message', 'Berkas fisik belum diunggah ke penyimpanan storage.');
    }

    public function test_be_file_01_confirm_fails_with_invalid_or_expired_token(): void
    {
        Sanctum::actingAs($this->studentUser1);

        $res = $this->postJson('/api/v1/files/confirm', [
            'token' => Str::uuid()->toString(),
        ]);

        $res->assertStatus(422)
            ->assertJsonPath('error.message', 'Token presign upload tidak valid atau telah kedaluwarsa.');
    }

    public function test_be_file_01_owner_can_get_short_lived_signed_url_for_private_file(): void
    {
        Sanctum::actingAs($this->studentUser1);

        $file = File::create([
            'owner_id' => $this->studentUser1->id,
            'owner_type' => 'User',
            'bucket' => 'private',
            'object_key' => 'private/2026/10/my_private_file.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'visibility' => 'privat',
        ]);

        $res = $this->getJson('/api/v1/files/'.$file->id.'/url');
        $res->assertStatus(200)
            ->assertJsonPath('data.id', $file->id)
            ->assertJsonPath('data.visibility', 'privat')
            ->assertJsonPath('data.ttl_seconds', 300);

        $downloadUrl = $res->json('data.url');
        $this->assertStringContainsString('/files/'.$file->id.'/download', $downloadUrl);
        $this->assertStringContainsString('signature=', $downloadUrl);
    }

    public function test_be_file_01_anti_idor_other_student_cannot_access_private_file_url(): void
    {
        // File owned by Student 1
        $file = File::create([
            'owner_id' => $this->studentUser1->id,
            'owner_type' => 'User',
            'bucket' => 'private',
            'object_key' => 'private/2026/10/confidential_student1.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'visibility' => 'privat',
        ]);

        // Student 2 attempts to get URL for Student 1's file -> 403 Forbidden!
        Sanctum::actingAs($this->studentUser2);
        $res = $this->getJson('/api/v1/files/'.$file->id.'/url');
        $res->assertStatus(403)
            ->assertJsonPath('error.message', 'Akses ditolak: Anda tidak memiliki wewenang untuk mengakses berkas ini.');
    }

    public function test_be_file_01_admin_and_guardian_can_access_authorized_private_file(): void
    {
        // File owned by Student 1
        $file = File::create([
            'owner_id' => $this->student1->id,
            'owner_type' => 'Student',
            'bucket' => 'private',
            'object_key' => 'private/2026/10/rapor_aditya.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 2048,
            'visibility' => 'privat',
        ]);

        // 1. Guardian (Parent of Student 1) -> Access granted
        Sanctum::actingAs($this->guardianUser);
        $guardianRes = $this->getJson('/api/v1/files/'.$file->id.'/url');
        $guardianRes->assertStatus(200)
            ->assertJsonPath('data.id', $file->id);

        // 2. Admin -> Access granted
        Sanctum::actingAs($this->adminUser);
        $adminRes = $this->getJson('/api/v1/files/'.$file->id.'/url');
        $adminRes->assertStatus(200)
            ->assertJsonPath('data.id', $file->id);
    }

    public function test_be_file_01_public_file_provides_public_access_url(): void
    {
        Sanctum::actingAs($this->studentUser1);

        $file = File::create([
            'owner_id' => $this->studentUser1->id,
            'owner_type' => 'User',
            'bucket' => 'public',
            'object_key' => 'public/2026/10/brosur_sekolah.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 4096,
            'visibility' => 'publik',
        ]);

        $res = $this->getJson('/api/v1/files/'.$file->id.'/url');
        $res->assertStatus(200)
            ->assertJsonPath('data.id', $file->id)
            ->assertJsonPath('data.visibility', 'publik');

        $this->assertNull($res->json('data.ttl_seconds'));
        $this->assertNotEmpty($res->json('data.url'));
    }

    public function test_be_file_01_download_signed_verifies_signature_and_streams_file(): void
    {
        $fileContent = 'Fake PDF Content 12345';
        $objectKey = 'private/2026/10/dokumen_rahasia.pdf';
        Storage::disk('local')->put($objectKey, $fileContent);

        $file = File::create([
            'owner_id' => $this->studentUser1->id,
            'owner_type' => 'User',
            'bucket' => 'private',
            'object_key' => $objectKey,
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen($fileContent),
            'visibility' => 'privat',
        ]);

        // 1. Valid signed URL
        $validSignedUrl = URL::temporarySignedRoute(
            'files.download.signed',
            Carbon::now()->addMinutes(5),
            ['id' => $file->id]
        );

        $response = $this->get($validSignedUrl);
        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf');

        // 2. Tampered signature -> 403 Forbidden
        $tamperedUrl = $validSignedUrl.'&tampered=1';
        $tamperedResponse = $this->get($tamperedUrl);
        $tamperedResponse->assertStatus(403);
    }

    public function test_be_photo_sec_avatar_rejects_non_image_mime_or_extension(): void
    {
        Sanctum::actingAs($this->adminUser);

        // Non-image MIME
        $res1 = $this->postJson('/api/v1/files/presign', [
            'filename' => 'avatar.jpg',
            'mime_type' => 'application/pdf',
            'size_bytes' => 50000,
            'visibility' => 'publik',
            'purpose' => 'avatar',
        ]);
        $res1->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        // Non-image extension
        $res2 = $this->postJson('/api/v1/files/presign', [
            'filename' => 'dokumen.pdf',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 50000,
            'visibility' => 'publik',
            'purpose' => 'avatar',
        ]);
        $res2->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function test_be_photo_sec_avatar_rejects_oversized_file_above_2mb(): void
    {
        Sanctum::actingAs($this->adminUser);

        // 3 MB file with purpose avatar
        $res = $this->postJson('/api/v1/files/presign', [
            'filename' => 'foto_besar.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 3 * 1024 * 1024,
            'visibility' => 'publik',
            'purpose' => 'avatar',
        ]);
        $res->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function test_be_photo_sec_avatar_accepts_valid_image_and_completes_upload(): void
    {
        Sanctum::actingAs($this->adminUser);

        $presignRes = $this->postJson('/api/v1/files/presign', [
            'filename' => 'pasfoto_resmi.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 200000, // 200 KB
            'visibility' => 'publik',
            'purpose' => 'avatar',
        ]);
        $presignRes->assertStatus(200);

        $token = $presignRes->json('data.token');
        $uploadUrl = $presignRes->json('data.upload_url');

        $fakeImage = UploadedFile::fake()->create('pasfoto_resmi.jpg', 100, 'image/jpeg');
        $uploadRes = $this->post($uploadUrl, [
            'file' => $fakeImage,
        ]);
        $uploadRes->assertStatus(200);

        $confirmRes = $this->postJson('/api/v1/files/confirm', [
            'token' => $token,
            'checksum' => $uploadRes->json('data.checksum'),
        ]);
        $confirmRes->assertStatus(201)
            ->assertJsonPath('data.visibility', 'publik')
            ->assertJsonPath('data.mime_type', 'image/jpeg');

        $fileId = $confirmRes->json('data.id');
        $this->assertNotEmpty($fileId);
    }

    public function test_be_photo_sec_student_cannot_assign_photo_to_student_master(): void
    {
        // Student user tries to call PUT /api/v1/students/{id}
        Sanctum::actingAs($this->studentUser1);

        $res = $this->putJson('/api/v1/students/'.$this->student1->id, [
            'full_name' => 'Hacker Name',
            'photo_file_id' => Str::uuid()->toString(),
        ]);

        $res->assertStatus(403);
    }
}
