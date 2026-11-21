<?php

namespace Tests\Feature\Cms;

use App\Models\Download;
use App\Models\File;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DownloadTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $regularUser;

    protected Role $adminRole;

    protected Role $guestRole;

    protected Permission $permManageDownload;

    protected File $dummyPdfFile;

    protected function setUp(): void
    {
        parent::setUp();

        // Permissions
        $this->permManageDownload = Permission::create([
            'code' => 'cms.download.manage',
            'description' => 'Mengelola berkas dan dokumen pusat unduhan CMS',
        ]);

        // Roles
        $this->adminRole = Role::create([
            'code' => 'super_admin',
            'name' => 'Super Administrator',
            'is_system' => true,
        ]);
        $this->adminRole->permissions()->attach([$this->permManageDownload->id]);

        $this->guestRole = Role::create([
            'code' => 'guest',
            'name' => 'Tamu',
            'is_system' => false,
        ]);

        // Users
        $this->adminUser = User::create([
            'username' => 'admin_unduhan',
            'email' => 'admin.unduhan@labschool.sch.id',
            'password_hash' => Hash::make('AdminSecret123!'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($this->adminRole->id);

        $this->regularUser = User::create([
            'username' => 'guest_user',
            'email' => 'guest@labschool.sch.id',
            'password_hash' => Hash::make('GuestSecret123!'),
            'status' => 'active',
        ]);
        $this->regularUser->roles()->attach($this->guestRole->id);

        // Dummy PDF file
        $this->dummyPdfFile = File::create([
            'bucket' => 'public-docs',
            'object_key' => 'downloads/tata-tertib-2026.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 524288,
            'visibility' => 'publik',
        ]);
    }

    /**
     * BE-CMS-06: Unauthenticated requests cannot mutate downloads (401)
     */
    public function test_be_cms_06_unauthenticated_requests_cannot_mutate_downloads(): void
    {
        $dummyId = '00000000-0000-0000-0000-000000000000';

        $this->getJson('/api/v1/downloads')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->postJson('/api/v1/downloads', ['title' => 'Panduan Baru'])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->getJson("/api/v1/downloads/{$dummyId}")
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->putJson("/api/v1/downloads/{$dummyId}", ['title' => 'Revisi Panduan'])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->deleteJson("/api/v1/downloads/{$dummyId}")
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    /**
     * BE-CMS-06: User without permission is forbidden (403)
     */
    public function test_be_cms_06_user_without_permission_is_forbidden(): void
    {
        $this->actingAs($this->regularUser)
            ->getJson('/api/v1/downloads')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        $this->actingAs($this->regularUser)
            ->postJson('/api/v1/downloads', [
                'title' => 'Dokumen Rahasia',
                'category' => 'Internal',
                'visibility' => 'internal',
                'file_id' => $this->dummyPdfFile->id,
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    /**
     * BE-CMS-06: Validation fails when required fields are missing (400)
     */
    public function test_be_cms_06_validation_fails_when_required_fields_missing(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/downloads', []);

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    /**
     * BE-CMS-06: Admin can CRUD downloads with audit logs
     */
    public function test_be_cms_06_admin_can_crud_downloads_with_audit_logs(): void
    {
        // 1. Create Download
        $createResp = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/downloads', [
                'title' => 'Tata Tertib Siswa SMA Labschool UM 2026/2027',
                'category' => 'Tata Tertib',
                'visibility' => 'publik',
                'file_id' => $this->dummyPdfFile->id,
            ]);

        $createResp->assertStatus(201)
            ->assertJsonPath('data.title', 'Tata Tertib Siswa SMA Labschool UM 2026/2027')
            ->assertJsonPath('data.category', 'Tata Tertib')
            ->assertJsonPath('data.visibility', 'publik')
            ->assertJsonPath('data.file_id', $this->dummyPdfFile->id);

        $downloadId = $createResp->json('data.id');

        $this->assertDatabaseHas('downloads', [
            'id' => $downloadId,
            'title' => 'Tata Tertib Siswa SMA Labschool UM 2026/2027',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'action' => 'download.created',
            'entity' => 'Download',
            'entity_id' => $downloadId,
        ]);

        // 2. Show Download Detail
        $this->actingAs($this->adminUser)
            ->getJson("/api/v1/downloads/{$downloadId}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $downloadId)
            ->assertJsonPath('data.file.object_key', 'downloads/tata-tertib-2026.pdf');

        // 3. Update Download
        $updateResp = $this->actingAs($this->adminUser)
            ->putJson("/api/v1/downloads/{$downloadId}", [
                'title' => 'Revisi Buku Pedoman dan Tata Tertib Siswa 2026',
                'category' => 'Panduan',
            ]);

        $updateResp->assertStatus(200)
            ->assertJsonPath('data.title', 'Revisi Buku Pedoman dan Tata Tertib Siswa 2026')
            ->assertJsonPath('data.category', 'Panduan');

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'action' => 'download.updated',
            'entity' => 'Download',
            'entity_id' => $downloadId,
        ]);

        // 4. Delete Download
        $deleteResp = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/downloads/{$downloadId}");

        $deleteResp->assertStatus(200)
            ->assertJsonPath('data.message', 'Dokumen unduhan berhasil dihapus');

        $this->assertDatabaseMissing('downloads', ['id' => $downloadId]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'action' => 'download.deleted',
            'entity' => 'Download',
            'entity_id' => $downloadId,
        ]);
    }

    /**
     * BE-CMS-06: Admin can filter and search downloads
     */
    public function test_be_cms_06_admin_can_filter_and_search_downloads(): void
    {
        Download::create([
            'title' => 'Brosur Penerimaan Peserta Didik Baru',
            'category' => 'Brosur',
            'visibility' => 'publik',
            'file_id' => $this->dummyPdfFile->id,
        ]);

        Download::create([
            'title' => 'Panduan Kurikulum Merdeka Guru',
            'category' => 'Panduan',
            'visibility' => 'internal',
            'file_id' => $this->dummyPdfFile->id,
        ]);

        Download::create([
            'title' => 'Formulir Pendaftaran Ekstrakurikuler',
            'category' => 'Formulir',
            'visibility' => 'publik',
            'file_id' => $this->dummyPdfFile->id,
        ]);

        // Filter category = Brosur
        $respCategory = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/downloads?category=Brosur');

        $respCategory->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'Brosur Penerimaan Peserta Didik Baru');

        // Filter visibility = internal
        $respVisibility = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/downloads?visibility=internal');

        $respVisibility->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'Panduan Kurikulum Merdeka Guru');

        // Search by keyword
        $respSearch = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/downloads?search=Ekstrakurikuler');

        $respSearch->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'Formulir Pendaftaran Ekstrakurikuler');
    }

    /**
     * BE-CMS-06: Public can view downloads and filters (Internal hidden)
     */
    public function test_be_cms_06_public_can_view_downloads_and_filters(): void
    {
        $publicDownload = Download::create([
            'title' => 'Kalender Akademik Resmi 2026/2027',
            'category' => 'Akademik',
            'visibility' => 'publik',
            'file_id' => $this->dummyPdfFile->id,
        ]);

        $internalDownload = Download::create([
            'title' => 'Format Soal Ujian Tengah Semester Guru',
            'category' => 'Ujian',
            'visibility' => 'internal',
            'file_id' => $this->dummyPdfFile->id,
        ]);

        // Public index only returns public download
        $respPublic = $this->getJson('/api/v1/public/downloads');

        $respPublic->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'Kalender Akademik Resmi 2026/2027')
            ->assertJsonPath('data.0.file.mime_type', 'application/pdf');

        // Filter public by category
        $this->getJson('/api/v1/public/downloads?category=Akademik')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $publicDownload->id);

        // Filter public by non-existent category
        $this->getJson('/api/v1/public/downloads?category=Lainnya')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 0);

        // Public show on public download -> 200
        $this->getJson("/api/v1/public/downloads/{$publicDownload->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $publicDownload->id);

        // Public show on internal download -> 404 NOT_FOUND
        $this->getJson("/api/v1/public/downloads/{$internalDownload->id}")
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }
}
