<?php

namespace Tests\Feature\Ppdb;

use App\Models\Applicant;
use App\Models\ApplicantDocument;
use App\Models\File;
use App\Models\Permission;
use App\Models\PPDBTrack;
use App\Models\PPDBWave;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PPDBDocumentTest extends TestCase
{
    use RefreshDatabase;

    protected User $applicantUser1;

    protected User $applicantUser2;

    protected User $adminUser;

    protected Role $guestRole;

    protected Role $adminRole;

    protected Permission $permReadApplicant;

    protected string $schoolId;

    protected PPDBWave $activeWave;

    protected PPDBTrack $track;

    protected Applicant $applicant1;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->schoolId = Str::uuid()->toString();

        // Permissions & Roles
        $this->permReadApplicant = Permission::create([
            'code' => 'ppdb.applicant.read',
            'description' => 'Melihat berkas dan pendaftar PPDB',
        ]);

        $this->adminRole = Role::create([
            'code' => 'ppdb_committee',
            'name' => 'Panitia PPDB',
            'is_system' => true,
        ]);
        $this->adminRole->permissions()->attach([$this->permReadApplicant->id]);

        $this->guestRole = Role::firstOrCreate(
            ['code' => 'guest'],
            [
                'name' => 'Tamu / Calon Siswa',
                'is_system' => true,
            ]
        );

        // Users
        $this->applicantUser1 = User::create([
            'username' => 'applicant_doc1',
            'email' => 'doc1@example.com',
            'phone' => '081234567111',
            'password_hash' => Hash::make('Secret123!'),
            'status' => 'active',
        ]);
        $this->applicantUser1->roles()->attach($this->guestRole->id);

        $this->applicantUser2 = User::create([
            'username' => 'applicant_doc2',
            'email' => 'doc2@example.com',
            'phone' => '081234567222',
            'password_hash' => Hash::make('Secret123!'),
            'status' => 'active',
        ]);
        $this->applicantUser2->roles()->attach($this->guestRole->id);

        $this->adminUser = User::create([
            'username' => 'panitia_doc',
            'email' => 'panitia.doc@labschool.sch.id',
            'phone' => '081234567999',
            'password_hash' => Hash::make('Secret123!'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($this->adminRole->id);

        // Wave & Track
        $this->activeWave = PPDBWave::create([
            'school_id' => $this->schoolId,
            'name' => 'Gelombang 1 2026',
            'open_at' => Carbon::now()->subDays(2),
            'close_at' => Carbon::now()->addDays(20),
            'result_publish_at' => Carbon::now()->addDays(25),
            'status' => 'aktif',
        ]);

        $this->track = PPDBTrack::create([
            'wave_id' => $this->activeWave->id,
            'code' => 'REGULER',
            'name' => 'Jalur Reguler',
            'quota' => 50,
        ]);

        // Applicant 1
        $this->applicant1 = Applicant::create([
            'wave_id' => $this->activeWave->id,
            'track_id' => $this->track->id,
            'user_id' => $this->applicantUser1->id,
            'registration_no' => 'DFT-DOC-0001',
            'nik_enc' => '3579012345670001',
            'full_name' => 'Bintang Nusantara',
            'birth_date' => '2010-08-17',
            'status' => 'draft',
        ]);
    }

    public function test_be_ppdb_04_unauthenticated_cannot_access_documents(): void
    {
        $appId = $this->applicant1->id;
        $docId = Str::uuid()->toString();

        $this->getJson("/api/v1/ppdb/applications/{$appId}/documents")->assertUnauthorized();
        $this->postJson("/api/v1/ppdb/applications/{$appId}/documents", [])->assertUnauthorized();
        $this->deleteJson("/api/v1/ppdb/applications/{$appId}/documents/{$docId}")->assertUnauthorized();
        $this->getJson("/api/v1/ppdb/applications/{$appId}/documents/{$docId}/download")->assertUnauthorized();
    }

    public function test_be_ppdb_04_applicant_can_upload_pdf_document_with_private_visibility(): void
    {
        $file = UploadedFile::fake()->create('kartu_keluarga.pdf', 350, 'application/pdf');

        $response = $this->actingAs($this->applicantUser1)
            ->postJson("/api/v1/ppdb/applications/{$this->applicant1->id}/documents", [
                'document_type' => 'kartu_keluarga',
                'file' => $file,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.document_type', 'kartu_keluarga')
            ->assertJsonPath('data.status', 'menunggu')
            ->assertJsonPath('data.file.visibility', 'privat')
            ->assertJsonPath('data.file.mime_type', 'application/pdf');

        $documentId = $response->json('data.id');
        $fileId = $response->json('data.file_id');
        $objectKey = $response->json('data.file.object_key');

        $this->assertDatabaseHas('applicant_documents', [
            'id' => $documentId,
            'applicant_id' => $this->applicant1->id,
            'document_type' => 'kartu_keluarga',
            'status' => 'menunggu',
        ]);

        $this->assertDatabaseHas('files', [
            'id' => $fileId,
            'visibility' => 'privat',
            'mime_type' => 'application/pdf',
        ]);

        Storage::disk('local')->assertExists($objectKey);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ppdb_document.uploaded',
            'entity' => 'ApplicantDocument',
            'entity_id' => $documentId,
        ]);
    }

    public function test_be_ppdb_04_applicant_can_upload_image_documents_jpg_png(): void
    {
        $fileJpg = UploadedFile::fake()->create('akta_kelahiran.jpg', 500, 'image/jpeg');

        $resJpg = $this->actingAs($this->applicantUser1)
            ->postJson("/api/v1/ppdb/applications/{$this->applicant1->id}/documents", [
                'document_type' => 'akta_kelahiran',
                'file' => $fileJpg,
            ]);

        $resJpg->assertStatus(201)
            ->assertJsonPath('data.document_type', 'akta_kelahiran')
            ->assertJsonPath('data.status', 'menunggu');

        $filePng = UploadedFile::fake()->create('sertifikat_lomba.png', 800, 'image/png');

        $resPng = $this->actingAs($this->applicantUser1)
            ->postJson("/api/v1/ppdb/applications/{$this->applicant1->id}/documents", [
                'document_type' => 'sertifikat',
                'file' => $filePng,
            ]);

        $resPng->assertStatus(201)
            ->assertJsonPath('data.document_type', 'sertifikat')
            ->assertJsonPath('data.file.mime_type', 'image/png');
    }

    public function test_be_ppdb_04_upload_fails_if_file_exceeds_2mb_or_invalid_mime(): void
    {
        // 1. Exceeds 2 MB (e.g. 2500 KB)
        $largeFile = UploadedFile::fake()->create('dokumen_besar.pdf', 2500, 'application/pdf');

        $resLarge = $this->actingAs($this->applicantUser1)
            ->postJson("/api/v1/ppdb/applications/{$this->applicant1->id}/documents", [
                'document_type' => 'rapor',
                'file' => $largeFile,
            ]);

        $resLarge->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        // 2. Invalid MIME type (e.g. text/plain .txt)
        $txtFile = UploadedFile::fake()->create('dokumen.txt', 100, 'text/plain');

        $resTxt = $this->actingAs($this->applicantUser1)
            ->postJson("/api/v1/ppdb/applications/{$this->applicant1->id}/documents", [
                'document_type' => 'rapor',
                'file' => $txtFile,
            ]);

        $resTxt->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function test_be_ppdb_04_reuploading_same_document_type_replaces_old_file_and_resets_reject_reason(): void
    {
        // Set application to revision
        $this->applicant1->status = 'revision';
        $this->applicant1->save();

        // 1. Initial upload
        $file1 = UploadedFile::fake()->create('akta_lama.pdf', 300, 'application/pdf');
        $res1 = $this->actingAs($this->applicantUser1)
            ->postJson("/api/v1/ppdb/applications/{$this->applicant1->id}/documents", [
                'document_type' => 'akta_kelahiran',
                'file' => $file1,
            ]);
        $res1->assertStatus(201);
        $oldFileKey = $res1->json('data.file.object_key');
        $docId = $res1->json('data.id');

        // Simulate rejected by committee
        ApplicantDocument::where('id', $docId)->update([
            'status' => 'ditolak',
            'reject_reason' => 'Scan akta terpotong dan tidak terbaca jelas',
        ]);

        // 2. Re-upload on revision
        $file2 = UploadedFile::fake()->create('akta_baru.pdf', 400, 'application/pdf');
        $res2 = $this->actingAs($this->applicantUser1)
            ->postJson("/api/v1/ppdb/applications/{$this->applicant1->id}/documents", [
                'document_type' => 'akta_kelahiran',
                'file' => $file2,
            ]);

        $res2->assertStatus(201)
            ->assertJsonPath('data.id', $docId)
            ->assertJsonPath('data.status', 'menunggu')
            ->assertJsonPath('data.reject_reason', null);

        // Old file deleted, new file exists
        Storage::disk('local')->assertMissing($oldFileKey);
        Storage::disk('local')->assertExists($res2->json('data.file.object_key'));
    }

    public function test_be_ppdb_04_cannot_upload_or_delete_documents_if_application_is_submitted(): void
    {
        // Lock application by setting status to submitted
        $this->applicant1->status = 'submitted';
        $this->applicant1->save();

        $file = UploadedFile::fake()->create('berkas.pdf', 200, 'application/pdf');

        // Upload blocked
        $resUpload = $this->actingAs($this->applicantUser1)
            ->postJson("/api/v1/ppdb/applications/{$this->applicant1->id}/documents", [
                'document_type' => 'kartu_keluarga',
                'file' => $file,
            ]);

        $resUpload->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath('error.message', 'Data berkas pendaftaran telah dikunci dan tidak dapat diubah kecuali dalam status revisi');

        // Create dummy document directly with valid file
        $dummyFile = File::create([
            'school_id' => $this->schoolId,
            'bucket' => 'ppdb-documents',
            'object_key' => 'dummy/berkas.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'visibility' => 'privat',
        ]);

        $dummyDoc = ApplicantDocument::create([
            'applicant_id' => $this->applicant1->id,
            'file_id' => $dummyFile->id,
            'document_type' => 'kartu_keluarga',
            'status' => 'menunggu',
        ]);

        // Delete blocked
        $resDelete = $this->actingAs($this->applicantUser1)
            ->deleteJson("/api/v1/ppdb/applications/{$this->applicant1->id}/documents/{$dummyDoc->id}");

        $resDelete->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath('error.message', 'Data berkas pendaftaran telah dikunci dan tidak dapat diubah kecuali dalam status revisi');
    }

    public function test_be_ppdb_04_applicant_can_delete_draft_document(): void
    {
        $file = UploadedFile::fake()->create('sertifikat.pdf', 250, 'application/pdf');

        $uploadRes = $this->actingAs($this->applicantUser1)
            ->postJson("/api/v1/ppdb/applications/{$this->applicant1->id}/documents", [
                'document_type' => 'sertifikat',
                'file' => $file,
            ]);
        $uploadRes->assertStatus(201);

        $docId = $uploadRes->json('data.id');
        $fileId = $uploadRes->json('data.file_id');
        $objectKey = $uploadRes->json('data.file.object_key');

        $deleteRes = $this->actingAs($this->applicantUser1)
            ->deleteJson("/api/v1/ppdb/applications/{$this->applicant1->id}/documents/{$docId}");

        $deleteRes->assertOk();

        $this->assertDatabaseMissing('applicant_documents', ['id' => $docId]);
        $this->assertDatabaseMissing('files', ['id' => $fileId]);
        Storage::disk('local')->assertMissing($objectKey);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ppdb_document.deleted',
            'entity' => 'ApplicantDocument',
            'entity_id' => $docId,
        ]);
    }

    public function test_be_ppdb_04_idor_protection_prevents_viewing_or_uploading_other_applicant_documents(): void
    {
        $file = UploadedFile::fake()->create('kk.pdf', 200, 'application/pdf');

        // User 1 uploads document
        $uploadRes = $this->actingAs($this->applicantUser1)
            ->postJson("/api/v1/ppdb/applications/{$this->applicant1->id}/documents", [
                'document_type' => 'kartu_keluarga',
                'file' => $file,
            ]);
        $docId = $uploadRes->json('data.id');

        // User 2 attempts to view User 1's documents list -> 403
        $this->actingAs($this->applicantUser2)
            ->getJson("/api/v1/ppdb/applications/{$this->applicant1->id}/documents")
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        // User 2 attempts to upload into User 1's application -> 403
        $otherFile = UploadedFile::fake()->create('hacked.pdf', 100, 'application/pdf');
        $this->actingAs($this->applicantUser2)
            ->postJson("/api/v1/ppdb/applications/{$this->applicant1->id}/documents", [
                'document_type' => 'hacked',
                'file' => $otherFile,
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        // User 2 attempts to download User 1's document -> 403
        $this->actingAs($this->applicantUser2)
            ->getJson("/api/v1/ppdb/applications/{$this->applicant1->id}/documents/{$docId}/download")
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        // Committee (Admin) can list and download User 1's document
        $this->actingAs($this->adminUser)
            ->getJson("/api/v1/ppdb/applications/{$this->applicant1->id}/documents")
            ->assertOk()
            ->assertJsonPath('data.0.id', $docId);

        $downloadRes = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/ppdb/applications/{$this->applicant1->id}/documents/{$docId}/download");
        $downloadRes->assertOk();
    }
}
