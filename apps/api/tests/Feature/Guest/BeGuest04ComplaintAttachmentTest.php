<?php

namespace Tests\Feature\Guest;

use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\File;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\ComplaintService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BeGuest04ComplaintAttachmentTest extends TestCase
{
    use RefreshDatabase;

    protected User $principalUser;

    protected User $counselorUser;

    protected User $teacherUser;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');

        $rolePrincipal = Role::firstOrCreate(['code' => 'principal'], ['name' => 'Kepala Sekolah', 'is_system' => true]);
        $roleCounselor = Role::firstOrCreate(['code' => 'counselor'], ['name' => 'Guru BK', 'is_system' => true]);
        $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);

        $permRead = Permission::firstOrCreate(['code' => 'complaint.read'], ['description' => 'Melihat aduan']);
        $permManage = Permission::firstOrCreate(['code' => 'complaint.manage'], ['description' => 'Mengelola aduan']);

        $rolePrincipal->permissions()->syncWithoutDetaching([$permRead->id, $permManage->id]);
        $roleCounselor->permissions()->syncWithoutDetaching([$permRead->id, $permManage->id]);

        $this->principalUser = User::create([
            'username' => 'kepsek_test',
            'email' => 'kepsek@sekolah.sch.id',
            'password_hash' => bcrypt('password123'),
            'status' => 'active',
        ]);
        $this->principalUser->roles()->attach($rolePrincipal->id);

        $this->counselorUser = User::create([
            'username' => 'gurubk_test',
            'email' => 'gurubk@sekolah.sch.id',
            'password_hash' => bcrypt('password123'),
            'status' => 'active',
        ]);
        $this->counselorUser->roles()->attach($roleCounselor->id);

        $this->teacherUser = User::create([
            'username' => 'guru_biasa',
            'email' => 'guru@sekolah.sch.id',
            'password_hash' => bcrypt('password123'),
            'status' => 'active',
        ]);
        $this->teacherUser->roles()->attach($roleTeacher->id);
    }

    /**
     * Helper to create a dummy valid image.
     */
    protected function createDummyImageBytes(string $format = 'jpg'): string
    {
        $img = imagecreatetruecolor(20, 20);
        $color = imagecolorallocate($img, 100, 150, 200);
        imagefilledrectangle($img, 0, 0, 19, 19, $color);

        ob_start();
        if ($format === 'png') {
            imagepng($img);
        } elseif ($format === 'webp') {
            imagewebp($img);
        } else {
            imagejpeg($img);
        }
        $bytes = ob_get_clean();

        if (PHP_VERSION_ID < 80500) {
            imagedestroy($img);
        }

        return $bytes;
    }

    /**
     * Helper to create a JPEG image containing real EXIF and GPS markers.
     */
    protected function createJpegWithExifAndGps(): string
    {
        $tiff = "II*\x00\x08\x00\x00\x00".
            "\x02\x00". // 2 tags in 0th IFD
            "\x0f\x01\x02\x00\x06\x00\x00\x00\x26\x00\x00\x00". // Make at offset 38
            "\x25\x88\x04\x00\x01\x00\x00\x00\x30\x00\x00\x00". // GPSInfo at offset 48
            "\x00\x00\x00\x00". // end of 0th IFD
            "Apple\x00". // value of Make (6 bytes, ends at offset 44)
            "\x00\x00\x00\x00". // padding to offset 48
            "\x01\x00". // 1 tag in GPS IFD (offset 48)
            "\x01\x00\x02\x00\x02\x00\x00\x00S\x00\x00\x00". // GPSLatitudeRef = "S\0"
            "\x00\x00\x00\x00";

        $exifData = "Exif\x00\x00".$tiff;
        $app1 = "\xFF\xE1".pack('n', strlen($exifData) + 2).$exifData;

        $baseJpg = $this->createDummyImageBytes('jpg');

        return substr($baseJpg, 0, 2).$app1.substr($baseJpg, 2);
    }

    /**
     * AC1 & AC5: Happy path upload -> confirm -> submit -> admin views with short-lived signed URL & audit.
     */
    public function test_be_guest_04_ac1_ac5_happy_path_upload_and_admin_view_with_signed_url_and_audit(): void
    {
        $imageBytes = $this->createDummyImageBytes('jpg');

        // 1. Presign attachment
        $presignRes = $this->postJson('/api/v1/public/complaints/attachments/presign', [
            'filename' => 'bukti_kejadian.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => strlen($imageBytes),
        ]);

        $presignRes->assertStatus(200)
            ->assertJsonPath('data.mime_type', 'image/jpeg')
            ->assertJsonStructure(['data' => ['token', 'upload_url', 'expires_at']]);

        $token = $presignRes->json('data.token');

        // 2. Upload via signed upload route
        $file = UploadedFile::fake()->createWithContent('bukti_kejadian.jpg', $imageBytes);
        $uploadRes = $this->postJson("/api/v1/files/upload-signed/{$token}", [
            'file' => $file,
        ]);
        $uploadRes->assertStatus(200);

        // 3. Confirm attachment
        $confirmRes = $this->postJson("/api/v1/public/complaints/attachments/{$token}/confirm");
        $confirmRes->assertStatus(201)
            ->assertJsonPath('data.mime_type', 'image/jpeg')
            ->assertJsonStructure(['data' => ['file_id', 'mime_type', 'size_bytes']]);

        $fileId = $confirmRes->json('data.file_id');
        $this->assertNotNull($fileId);

        // File is stored privately
        $savedFile = File::findOrFail($fileId);
        $this->assertEquals('privat', $savedFile->visibility);
        $this->assertEquals('complaint_pending', $savedFile->owner_type);
        $this->assertNull($savedFile->url); // Public URL must be null

        // 4. Submit Complaint with attachment_file_ids
        $submitRes = $this->postJson('/api/v1/public/complaints', [
            'category' => 'bullying',
            'content' => 'Terjadi perundungan di lorong kelas 8 saat jam istirahat pertama.',
            'is_anonymous' => false,
            'attachment_file_ids' => [$fileId],
        ]);

        $submitRes->assertStatus(201)
            ->assertJsonPath('data.category', 'bullying')
            ->assertJsonPath('data.attachment_count', 1);

        $ticketCode = $submitRes->json('data.ticket_code');
        $complaint = Complaint::where('ticket_code', $ticketCode)->firstOrFail();

        // Check file is now associated to complaint
        $savedFile->refresh();
        $this->assertEquals('complaint', $savedFile->owner_type);
        $this->assertEquals($complaint->id, $savedFile->owner_id);

        // 5. Admin / Principal views complaint detail (AC5)
        $viewRes = $this->actingAs($this->principalUser)
            ->getJson("/api/v1/complaints/{$complaint->id}");

        $viewRes->assertStatus(200)
            ->assertJsonPath('data.ticket_code', $ticketCode)
            ->assertJsonCount(1, 'data.attachments')
            ->assertJsonStructure([
                'data' => [
                    'attachments' => [
                        '*' => ['id', 'mime_type', 'signed_url', 'expires_at'],
                    ],
                ],
            ]);

        $signedUrl = $viewRes->json('data.attachments.0.signed_url');
        $this->assertNotEmpty($signedUrl);
        $this->assertStringContainsString('signature=', $signedUrl);

        // Accessing signed URL succeeds
        $downloadRes = $this->get($signedUrl);
        $downloadRes->assertStatus(200);

        // Verify audit log for read & download
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->principalUser->id,
            'action' => 'complaint.attachment.read',
            'entity' => 'Complaint',
            'entity_id' => $complaint->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'complaint.attachment.downloaded',
            'entity' => 'File',
            'entity_id' => $fileId,
        ]);
    }

    /**
     * AC2: Image with EXIF & GPS metadata has ALL metadata completely stripped via raster re-encode.
     */
    public function test_be_guest_04_ac2_strips_exif_and_gps_metadata_completely(): void
    {
        $rawImageWithGps = $this->createJpegWithExifAndGps();

        // Verify input actually has EXIF / GPS before re-encode
        $tmpBefore = tempnam(sys_get_temp_dir(), 'test_in');
        file_put_contents($tmpBefore, $rawImageWithGps);
        $exifBefore = @exif_read_data($tmpBefore);
        unlink($tmpBefore);

        $this->assertNotEmpty($exifBefore);
        $this->assertEquals('Apple', $exifBefore['Make'] ?? null);
        $this->assertEquals('S', $exifBefore['GPSLatitudeRef'] ?? null);

        // Presign & confirm
        $presignRes = $this->postJson('/api/v1/public/complaints/attachments/presign', [
            'filename' => 'foto_ber_gps.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => strlen($rawImageWithGps),
        ]);
        $token = $presignRes->json('data.token');

        // Confirm directly with multipart file
        $file = UploadedFile::fake()->createWithContent('foto_ber_gps.jpg', $rawImageWithGps);
        $confirmRes = $this->postJson('/api/v1/public/complaints/attachments/confirm', [
            'token' => $token,
            'file' => $file,
        ]);
        $confirmRes->assertStatus(201);

        $fileId = $confirmRes->json('data.file_id');
        $savedFile = File::findOrFail($fileId);

        // Retrieve processed file from storage
        $cleanBytes = Storage::disk('local')->get($savedFile->object_key);
        $this->assertNotEmpty($cleanBytes);

        // Inspect metadata with exif_read_data()
        $tmpAfter = tempnam(sys_get_temp_dir(), 'test_out');
        file_put_contents($tmpAfter, $cleanBytes);
        $exifAfter = @exif_read_data($tmpAfter);
        unlink($tmpAfter);

        // Verify: ZERO Make, ZERO Model, ZERO GPS info
        $this->assertArrayNotHasKey('Make', $exifAfter ?: []);
        $this->assertArrayNotHasKey('GPS', $exifAfter ?: []);
        $this->assertArrayNotHasKey('GPSLatitudeRef', $exifAfter ?: []);
        $this->assertArrayNotHasKey('GPSLatitude', $exifAfter ?: []);
    }

    /**
     * AC3: Non-image, forged magic bytes, oversize (> 5MB), and over count (> 3 photos) are rejected.
     */
    public function test_be_guest_04_ac3_rejects_non_image_oversize_or_invalid_magic_bytes(): void
    {
        // 1. Non-image MIME rejected in presign
        $badPresign = $this->postJson('/api/v1/public/complaints/attachments/presign', [
            'filename' => 'malicious.php',
            'mime_type' => 'application/x-php',
            'size_bytes' => 1024,
        ]);
        $badPresign->assertStatus(400);

        // 2. Oversize (> 5MB) rejected in presign
        $oversizePresign = $this->postJson('/api/v1/public/complaints/attachments/presign', [
            'filename' => 'large.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 6 * 1024 * 1024,
        ]);
        $oversizePresign->assertStatus(400);

        // 3. Forged magic bytes (text file named .jpg) rejected in confirm
        $presignOk = $this->postJson('/api/v1/public/complaints/attachments/presign', [
            'filename' => 'fake.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 500,
        ]);
        $token = $presignOk->json('data.token');

        $fakeJpg = UploadedFile::fake()->createWithContent('fake.jpg', "<?php echo 'malicious'; ?>");
        $confirmBad = $this->postJson('/api/v1/public/complaints/attachments/confirm', [
            'token' => $token,
            'file' => $fakeJpg,
        ]);
        $confirmBad->assertStatus(400);

        // 4. More than 3 photos rejected when submitting complaint
        $dummyBytes = $this->createDummyImageBytes('jpg');
        $fileIds = [];
        for ($i = 0; $i < 4; $i++) {
            $p = $this->postJson('/api/v1/public/complaints/attachments/presign', [
                'filename' => "foto_{$i}.jpg",
                'mime_type' => 'image/jpeg',
                'size_bytes' => strlen($dummyBytes),
            ]);
            $t = $p->json('data.token');
            $c = $this->postJson('/api/v1/public/complaints/attachments/confirm', [
                'token' => $t,
                'file' => UploadedFile::fake()->createWithContent("foto_{$i}.jpg", $dummyBytes),
            ]);
            $fileIds[] = $c->json('data.file_id');
        }

        $submitOver = $this->postJson('/api/v1/public/complaints', [
            'category' => 'fasilitas',
            'content' => 'Kerusakan sarana olahraga di lapangan basket.',
            'attachment_file_ids' => $fileIds, // 4 items (limit is 3)
        ]);
        $submitOver->assertStatus(400);
    }

    /**
     * AC4: Anonymous submission stores ZERO reporter_user_id, IP, or user-agent traces.
     */
    public function test_be_guest_04_ac4_anonymous_submission_has_zero_reporter_or_ip_traces(): void
    {
        $dummyBytes = $this->createDummyImageBytes('png');

        $presign = $this->postJson('/api/v1/public/complaints/attachments/presign', [
            'filename' => 'aduan_anonim.png',
            'mime_type' => 'image/png',
            'size_bytes' => strlen($dummyBytes),
        ]);
        $token = $presign->json('data.token');

        $confirm = $this->postJson('/api/v1/public/complaints/attachments/confirm', [
            'token' => $token,
            'file' => UploadedFile::fake()->createWithContent('aduan_anonim.png', $dummyBytes),
        ]);
        $fileId = $confirm->json('data.file_id');

        $submitRes = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.195'])
            ->postJson('/api/v1/public/complaints', [
                'category' => 'pungli',
                'content' => 'Ditemukan indikasi pungutan liar tanpa kwitansi resmi di kantin.',
                'is_anonymous' => true,
                'attachment_file_ids' => [$fileId],
            ]);

        $submitRes->assertStatus(201);
        $ticketCode = $submitRes->json('data.ticket_code');

        $complaint = Complaint::where('ticket_code', $ticketCode)->firstOrFail();
        $this->assertTrue($complaint->is_anonymous);
        $this->assertNull($complaint->reporter_user_id);

        // Check AuditLog for submission: IP & user_agent MUST BE NULL for anonymous reports
        $audit = AuditLog::where('entity_id', $complaint->id)
            ->where('action', 'complaint.submitted')
            ->firstOrFail();

        $this->assertNull($audit->actor_id);
        $this->assertNull($audit->ip);
        $this->assertNull($audit->user_agent);

        // Check File has no user linkage
        $file = File::findOrFail($fileId);
        $this->assertNull($file->school_id);
        $this->assertEquals('complaint', $file->owner_type);
        $this->assertEquals($complaint->id, $file->owner_id);
    }

    /**
     * AC6: Unauthorized access and direct public access is denied.
     */
    public function test_be_guest_04_ac6_unauthorized_access_and_direct_public_access_is_denied(): void
    {
        $dummyBytes = $this->createDummyImageBytes('jpg');
        $presign = $this->postJson('/api/v1/public/complaints/attachments/presign', [
            'filename' => 'rahasia.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => strlen($dummyBytes),
        ]);
        $token = $presign->json('data.token');

        $confirm = $this->postJson('/api/v1/public/complaints/attachments/confirm', [
            'token' => $token,
            'file' => UploadedFile::fake()->createWithContent('rahasia.jpg', $dummyBytes),
        ]);
        $fileId = $confirm->json('data.file_id');

        $submit = $this->postJson('/api/v1/public/complaints', [
            'category' => 'akademik',
            'content' => 'Laporan terkait materi ujian yang belum disampaikan di kelas.',
            'is_anonymous' => false,
            'attachment_file_ids' => [$fileId],
        ]);
        $complaint = Complaint::where('ticket_code', $submit->json('data.ticket_code'))->firstOrFail();

        // 1. Unauthenticated request to GET /complaints/{id} -> 401
        $this->getJson("/api/v1/complaints/{$complaint->id}")
            ->assertStatus(401);

        // 2. Regular teacher (unauthorized) -> 403 Forbidden
        $this->actingAs($this->teacherUser)
            ->getJson("/api/v1/complaints/{$complaint->id}")
            ->assertStatus(403);

        // 3. Direct access to download route without valid signature -> 403 Forbidden
        $this->get("/api/v1/files/{$fileId}/download")
            ->assertStatus(403);

        // 4. File model getUrlAttribute() returns null for private files
        $file = File::findOrFail($fileId);
        $this->assertNull($file->url);
    }

    /**
     * AC7: Notification payload does NOT contain sensitive image binaries, URLs, or content.
     */
    public function test_be_guest_04_ac7_notification_payload_does_not_contain_sensitive_image_or_content(): void
    {
        $complaint = Complaint::create([
            'ticket_code' => 'ASP-20261104-TEST',
            'category' => 'bullying',
            'content' => 'Korban mengalami intimidasi verbal berulang kali di perpustakaan.',
            'is_anonymous' => true,
            'status' => 'baru',
        ]);

        /** @var ComplaintService $service */
        $service = app(ComplaintService::class);
        $payload = $service->getNotificationPayload($complaint);

        $payloadJson = json_encode($payload);

        // Must not leak sensitive content, category, or photo binary
        $this->assertStringNotContainsString('intimidasi', $payloadJson);
        $this->assertStringNotContainsString('bullying', $payloadJson);
        $this->assertStringNotContainsString('http', $payloadJson);
        $this->assertStringNotContainsString('.jpg', $payloadJson);
        $this->assertArrayHasKey('title', $payload);
        $this->assertArrayHasKey('body', $payload);
        $this->assertEquals('ASP-20261104-TEST', $payload['data']['ticket_code']);
    }
}
