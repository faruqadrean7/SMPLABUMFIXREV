<?php

namespace Tests\Feature\Cms;

use App\Models\Page;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PageTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $regularUser;

    protected Role $adminRole;

    protected Role $guestRole;

    protected Permission $permManagePage;

    protected function setUp(): void
    {
        parent::setUp();

        // Permissions
        $this->permManagePage = Permission::create([
            'code' => 'cms.page.manage',
            'description' => 'Mengelola halaman statis dan menu navigasi CMS',
        ]);

        // Roles
        $this->adminRole = Role::create([
            'code' => 'super_admin',
            'name' => 'Super Administrator',
            'is_system' => true,
        ]);
        $this->adminRole->permissions()->attach([$this->permManagePage->id]);

        $this->guestRole = Role::create([
            'code' => 'guest',
            'name' => 'Tamu',
            'is_system' => false,
        ]);

        // Users
        $this->adminUser = User::create([
            'username' => 'admin_halaman',
            'email' => 'admin.halaman@labschool.sch.id',
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
    }

    /**
     * BE-CMS-07: Unauthenticated requests cannot mutate pages or menu (401)
     */
    public function test_be_cms_07_unauthenticated_requests_cannot_mutate_pages_or_menu(): void
    {
        $dummyId = '00000000-0000-0000-0000-000000000000';

        $this->getJson('/api/v1/pages')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->postJson('/api/v1/pages', ['title' => 'Halaman Baru'])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->getJson("/api/v1/pages/{$dummyId}")
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->putJson("/api/v1/pages/{$dummyId}", ['title' => 'Revisi Halaman'])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->deleteJson("/api/v1/pages/{$dummyId}")
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->getJson('/api/v1/navigation-menu')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->putJson('/api/v1/navigation-menu', ['items' => []])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    /**
     * BE-CMS-07: User without permission is forbidden (403)
     */
    public function test_be_cms_07_user_without_permission_is_forbidden(): void
    {
        $this->actingAs($this->regularUser)
            ->getJson('/api/v1/pages')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        $this->actingAs($this->regularUser)
            ->postJson('/api/v1/pages', [
                'title' => 'Halaman Rahasia',
                'content' => 'Konten internal',
                'status' => 'draft',
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        $this->actingAs($this->regularUser)
            ->putJson('/api/v1/navigation-menu', ['items' => []])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    /**
     * BE-CMS-07: Admin can CRUD pages with auto-slug and audit logs
     */
    public function test_be_cms_07_admin_can_crud_pages_with_auto_slug_and_audit_logs(): void
    {
        // 1. Create Page
        $createResp = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/pages', [
                'title' => 'Visi dan Misi Sekolah Labschool UM',
                'content' => '<h2>Visi</h2><p>Menjadi sekolah laboratorium unggul berstandar internasional.</p>',
                'status' => 'terbit',
            ]);

        $createResp->assertStatus(201)
            ->assertJsonPath('data.title', 'Visi dan Misi Sekolah Labschool UM')
            ->assertJsonPath('data.slug', 'visi-dan-misi-sekolah-labschool-um')
            ->assertJsonPath('data.status', 'terbit');

        $pageId = $createResp->json('data.id');

        $this->assertDatabaseHas('pages', [
            'id' => $pageId,
            'slug' => 'visi-dan-misi-sekolah-labschool-um',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'action' => 'page.created',
            'entity' => 'Page',
            'entity_id' => $pageId,
        ]);

        // 2. Show Page Detail
        $this->actingAs($this->adminUser)
            ->getJson("/api/v1/pages/{$pageId}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $pageId)
            ->assertJsonPath('data.slug', 'visi-dan-misi-sekolah-labschool-um');

        // 3. Update Page
        $updateResp = $this->actingAs($this->adminUser)
            ->putJson("/api/v1/pages/{$pageId}", [
                'title' => 'Visi, Misi, dan Tujuan Sekolah Labschool UM',
                'status' => 'draft',
            ]);

        $updateResp->assertStatus(200)
            ->assertJsonPath('data.title', 'Visi, Misi, dan Tujuan Sekolah Labschool UM')
            ->assertJsonPath('data.status', 'draft');

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'action' => 'page.updated',
            'entity' => 'Page',
            'entity_id' => $pageId,
        ]);

        // 4. Delete Page
        $deleteResp = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/pages/{$pageId}");

        $deleteResp->assertStatus(200)
            ->assertJsonPath('data.message', 'Halaman berhasil dihapus');

        $this->assertDatabaseMissing('pages', ['id' => $pageId]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'action' => 'page.deleted',
            'entity' => 'Page',
            'entity_id' => $pageId,
        ]);
    }

    /**
     * BE-CMS-07: Public can only view published pages
     */
    public function test_be_cms_07_public_can_only_view_published_pages(): void
    {
        $publishedPage = Page::create([
            'title' => 'Sejarah Singkat Labschool',
            'slug' => 'sejarah-singkat-labschool',
            'content' => '<p>Didirikan sejak tahun 2004 sebagai laboratorium pendidikan.</p>',
            'status' => 'terbit',
        ]);

        $draftPage = Page::create([
            'title' => 'Struktur Organisasi Baru (Draf)',
            'slug' => 'struktur-organisasi-baru',
            'content' => '<p>Menunggu pengesahan pimpinan.</p>',
            'status' => 'draft',
        ]);

        // Public index only returns published page
        $indexResp = $this->getJson('/api/v1/public/pages');

        $indexResp->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.slug', $publishedPage->slug);

        // Public show published page by slug
        $this->getJson("/api/v1/public/pages/{$publishedPage->slug}")
            ->assertStatus(200)
            ->assertJsonPath('data.slug', $publishedPage->slug);

        // Public show draft page returns 404
        $this->getJson("/api/v1/public/pages/{$draftPage->slug}")
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }

    /**
     * BE-CMS-07: Navigation menu management for admin and public access
     */
    public function test_be_cms_07_navigation_menu_management(): void
    {
        // 1. Public can read default navigation menu
        $defaultResp = $this->getJson('/api/v1/public/navigation-menu');

        $defaultResp->assertStatus(200)
            ->assertJsonPath('data.0.title', 'Beranda')
            ->assertJsonPath('data.0.url', '/');

        // 2. Admin updates navigation menu
        $customMenu = [
            ['title' => 'Beranda', 'url' => '/', 'target' => '_self', 'is_active' => true],
            ['title' => 'Tentang Kami', 'url' => '/profil', 'target' => '_self', 'is_active' => true],
            ['title' => 'Portal PPDB', 'url' => '/ppdb', 'target' => '_blank', 'is_active' => true],
        ];

        $updateResp = $this->actingAs($this->adminUser)
            ->putJson('/api/v1/navigation-menu', [
                'items' => $customMenu,
            ]);

        $updateResp->assertStatus(200)
            ->assertJsonPath('data.1.title', 'Tentang Kami')
            ->assertJsonPath('data.2.target', '_blank');

        $this->assertDatabaseHas('site_settings', [
            'setting_key' => 'navigation_menu',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'action' => 'navigation_menu.updated',
            'entity' => 'SiteSetting',
        ]);

        // 3. Public gets the updated menu
        $publicResp = $this->getJson('/api/v1/public/navigation-menu');

        $publicResp->assertStatus(200)
            ->assertJsonPath('data.1.title', 'Tentang Kami')
            ->assertJsonPath('data.2.title', 'Portal PPDB');
    }
}
