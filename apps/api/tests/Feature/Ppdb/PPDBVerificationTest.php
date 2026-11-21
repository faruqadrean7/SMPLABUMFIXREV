<?php

namespace Tests\Feature\Ppdb;

use App\Models\Applicant;
use App\Models\ApplicantDocument;
use App\Models\File;
use App\Models\Notification;
use App\Models\Permission;
use App\Models\PPDBRequirement;
use App\Models\PPDBTrack;
use App\Models\PPDBWave;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class PPDBVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $committeeUser;

    protected User $regularApplicant;

    protected Role $committeeRole;

    protected Role $guestRole;

    protected Permission $permVerify;

    protected string $schoolId;

    protected PPDBWave $wave;

    protected PPDBTrack $track;

    protected Applicant $submittedApplicant;

    protected File $dummyFile;

    protected ApplicantDocument $docKK;

    protected function setUp(): void
    {
        parent::setUp();

        $this->schoolId = Str::uuid()->toString();

        // Permissions & Roles
        $this->permVerify = Permission::create([
            'code' => 'ppdb.applicant.verify',
            'description' => 'Memverifikasi berkas dan formulir pendaftaran PPDB',
        ]);

        $this->committeeRole = Role::create([
            'code' => 'ppdb_verifier',
            'name' => 'Verifikator PPDB',
            'is_system' => true,
        ]);
        $this->committeeRole->permissions()->attach([$this->permVerify->id]);

        $this->guestRole = Role::firstOrCreate(
            ['code' => 'guest'],
            [
                'name' => 'Tamu / Calon Siswa',
                'is_system' => true,
            ]
        );

        // Users
        $this->committeeUser = User::create([
            'username' => 'verifikator_ppdb',
            'email' => 'verifikator@labschool.sch.id',
            'phone' => '081234567800',
            'password_hash' => Hash::make('Secret123!'),
            'status' => 'active',
        ]);
        $this->committeeUser->roles()->attach($this->committeeRole->id);

        $this->regularApplicant = User::create([
            'username' => 'calon_siswa_test',
            'email' => 'siswa@example.com',
            'phone' => '081234567801',
            'password_hash' => Hash::make('Secret123!'),
            'status' => 'active',
        ]);
        $this->regularApplicant->roles()->attach($this->guestRole->id);

        // Wave & Track
        $this->wave = PPDBWave::create([
            'school_id' => $this->schoolId,
            'name' => 'Gelombang 1 2026',
            'open_at' => Carbon::now()->subDays(5),
            'close_at' => Carbon::now()->addDays(15),
            'result_publish_at' => Carbon::now()->addDays(20),
            'status' => 'aktif',
        ]);

        $this->track = PPDBTrack::create([
            'wave_id' => $this->wave->id,
            'code' => 'REGULER',
            'name' => 'Jalur Reguler',
            'quota' => 50,
        ]);

        // Requirements
        PPDBRequirement::create([
            'track_id' => $this->track->id,
            'document_type' => 'kartu_keluarga',
            'is_required' => true,
            'description' => 'Fotokopi KK asli',
        ]);

        // Submitted Applicant
        $this->submittedApplicant = Applicant::create([
            'wave_id' => $this->wave->id,
            'track_id' => $this->track->id,
            'user_id' => $this->regularApplicant->id,
            'registration_no' => 'PPDB-2026-00010',
            'nik_enc' => '3579012345679901',
            'full_name' => 'Bayu Wicaksono',
            'birth_date' => '2010-04-10',
            'status' => 'submitted',
        ]);

        // Dummy File
        $this->dummyFile = File::create([
            'school_id' => $this->schoolId,
            'bucket' => 'ppdb-documents',
            'object_key' => 'ppdb/documents/kk.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 102400,
            'visibility' => 'privat',
        ]);

        // Document
        $this->docKK = ApplicantDocument::create([
            'applicant_id' => $this->submittedApplicant->id,
            'file_id' => $this->dummyFile->id,
            'document_type' => 'kartu_keluarga',
            'status' => 'menunggu',
        ]);
    }

    public function test_be_ppdb_05_unauthenticated_cannot_verify(): void
    {
        $docId = $this->docKK->id;
        $appId = $this->submittedApplicant->id;

        $this->patchJson("/api/v1/ppdb/documents/{$docId}/verify", ['status' => 'valid'])->assertUnauthorized();
        $this->patchJson("/api/v1/ppdb/applications/{$appId}/verify", ['status' => 'verified'])->assertUnauthorized();
    }

    public function test_be_ppdb_05_unauthorized_user_cannot_verify(): void
    {
        $docId = $this->docKK->id;
        $appId = $this->submittedApplicant->id;

        $this->actingAs($this->regularApplicant)
            ->patchJson("/api/v1/ppdb/documents/{$docId}/verify", ['status' => 'valid'])
            ->assertForbidden();

        $this->actingAs($this->regularApplicant)
            ->patchJson("/api/v1/ppdb/applications/{$appId}/verify", ['status' => 'verified'])
            ->assertForbidden();
    }

    public function test_be_ppdb_05_committee_can_verify_document_as_valid(): void
    {
        $response = $this->actingAs($this->committeeUser)
            ->patchJson("/api/v1/ppdb/documents/{$this->docKK->id}/verify", [
                'status' => 'valid',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'valid')
            ->assertJsonPath('data.verified_by', $this->committeeUser->id)
            ->assertJsonPath('data.reject_reason', null);

        $this->assertDatabaseHas('applicant_documents', [
            'id' => $this->docKK->id,
            'status' => 'valid',
            'verified_by' => $this->committeeUser->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ppdb_document.verified',
            'entity' => 'ApplicantDocument',
            'entity_id' => $this->docKK->id,
        ]);
    }

    public function test_be_ppdb_05_committee_can_reject_document_with_reason(): void
    {
        // 1. Missing reject reason when rejecting should fail validation (400)
        $failRes = $this->actingAs($this->committeeUser)
            ->patchJson("/api/v1/ppdb/documents/{$this->docKK->id}/verify", [
                'status' => 'ditolak',
            ]);
        $failRes->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        // 2. Reject with reason should succeed
        $response = $this->actingAs($this->committeeUser)
            ->patchJson("/api/v1/ppdb/documents/{$this->docKK->id}/verify", [
                'status' => 'ditolak',
                'reject_reason' => 'Scan KK buram dan NIK tidak terbaca jelas',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'ditolak')
            ->assertJsonPath('data.reject_reason', 'Scan KK buram dan NIK tidak terbaca jelas');

        $this->assertDatabaseHas('applicant_documents', [
            'id' => $this->docKK->id,
            'status' => 'ditolak',
            'reject_reason' => 'Scan KK buram dan NIK tidak terbaca jelas',
        ]);
    }

    public function test_be_ppdb_05_cannot_verify_draft_application_or_draft_document(): void
    {
        $draftApplicant = Applicant::create([
            'wave_id' => $this->wave->id,
            'track_id' => $this->track->id,
            'user_id' => $this->regularApplicant->id,
            'registration_no' => 'DFT-2026-DRAFT',
            'nik_enc' => '3579012345679902',
            'full_name' => 'Calon Draf',
            'birth_date' => '2010-01-01',
            'status' => 'draft',
        ]);

        $draftDoc = ApplicantDocument::create([
            'applicant_id' => $draftApplicant->id,
            'file_id' => $this->dummyFile->id,
            'document_type' => 'kartu_keluarga',
            'status' => 'menunggu',
        ]);

        // Attempt verify draft document
        $this->actingAs($this->committeeUser)
            ->patchJson("/api/v1/ppdb/documents/{$draftDoc->id}/verify", ['status' => 'valid'])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        // Attempt verify draft application
        $this->actingAs($this->committeeUser)
            ->patchJson("/api/v1/ppdb/applications/{$draftApplicant->id}/verify", ['status' => 'verified'])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function test_be_ppdb_05_verify_application_fails_if_required_documents_missing_or_rejected(): void
    {
        // 1. Rejected document exists
        $this->docKK->update([
            'status' => 'ditolak',
            'reject_reason' => 'Berkas tidak sesuai',
        ]);

        $res1 = $this->actingAs($this->committeeUser)
            ->patchJson("/api/v1/ppdb/applications/{$this->submittedApplicant->id}/verify", [
                'status' => 'verified',
            ]);

        $res1->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath('error.message', 'Tidak dapat memverifikasi pendaftaran karena masih terdapat berkas yang ditolak');

        // 2. Add an additional required document to track that is not uploaded
        PPDBRequirement::create([
            'track_id' => $this->track->id,
            'document_type' => 'akta_kelahiran',
            'is_required' => true,
        ]);

        $this->docKK->update(['status' => 'valid']);

        $res2 = $this->actingAs($this->committeeUser)
            ->patchJson("/api/v1/ppdb/applications/{$this->submittedApplicant->id}/verify", [
                'status' => 'verified',
            ]);

        $res2->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function test_be_ppdb_05_verify_application_as_verified_succeeds_and_sends_notification(): void
    {
        $response = $this->actingAs($this->committeeUser)
            ->patchJson("/api/v1/ppdb/applications/{$this->submittedApplicant->id}/verify", [
                'status' => 'verified',
                'note' => 'Seluruh berkas persyaratan telah lengkap dan memenuhi syarat.',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'verified');

        $this->assertDatabaseHas('applicants', [
            'id' => $this->submittedApplicant->id,
            'status' => 'verified',
        ]);

        // Status history recorded
        $this->assertDatabaseHas('applicant_status_history', [
            'applicant_id' => $this->submittedApplicant->id,
            'from_status' => 'submitted',
            'to_status' => 'verified',
            'note' => 'Seluruh berkas persyaratan telah lengkap dan memenuhi syarat.',
            'actor_id' => $this->committeeUser->id,
        ]);

        // Notification created for applicant
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->regularApplicant->id,
            'notif_type' => 'ppdb.application_verified',
        ]);

        // Audit log created
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ppdb_application.verified',
            'entity' => 'Applicant',
            'entity_id' => $this->submittedApplicant->id,
        ]);
    }

    public function test_be_ppdb_05_verify_application_as_revision_succeeds_and_sends_revision_notification(): void
    {
        // Mark KK as rejected
        $this->docKK->update([
            'status' => 'ditolak',
            'reject_reason' => 'Scan KK tidak jelas',
        ]);

        $response = $this->actingAs($this->committeeUser)
            ->patchJson("/api/v1/ppdb/applications/{$this->submittedApplicant->id}/verify", [
                'status' => 'revision',
                'note' => 'Harap mengunggah kembali berkas Kartu Keluarga asli yang terbaca jelas.',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'revision');

        $this->assertDatabaseHas('applicants', [
            'id' => $this->submittedApplicant->id,
            'status' => 'revision',
        ]);

        $this->assertDatabaseHas('applicant_status_history', [
            'applicant_id' => $this->submittedApplicant->id,
            'from_status' => 'submitted',
            'to_status' => 'revision',
            'note' => 'Harap mengunggah kembali berkas Kartu Keluarga asli yang terbaca jelas.',
        ]);

        // Notification created for applicant
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->regularApplicant->id,
            'notif_type' => 'ppdb.revision_required',
        ]);
    }
}
