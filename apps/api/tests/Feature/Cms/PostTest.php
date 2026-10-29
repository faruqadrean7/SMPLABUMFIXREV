<?php

namespace Tests\Feature\Cms;

use App\Models\File;
use App\Models\Permission;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PostTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $regularUser;

    protected Role $adminRole;

    protected Role $guestRole;

    protected Permission $permManagePost;

    protected function setUp(): void
    {
        parent::setUp();

        // Permissions
        $this->permManagePost = Permission::create([
            'code' => 'cms.post.manage',
            'description' => 'Mengelola artikel dan berita CMS',
        ]);

        // Roles
        $this->adminRole = Role::create([
            'code' => 'super_admin',
            'name' => 'Super Administrator',
            'is_system' => true,
        ]);
        $this->adminRole->permissions()->attach([$this->permManagePost->id]);

        $this->guestRole = Role::create([
            'code' => 'guest',
            'name' => 'Tamu',
            'is_system' => false,
        ]);

        // Users
        $this->adminUser = User::create([
            'username' => 'editor_berita',
            'email' => 'editor@labschool.sch.id',
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
     * BE-CMS-02: Unauthenticated requests cannot mutate posts or categories (401)
     */
    public function test_be_cms_02_unauthenticated_requests_cannot_mutate_posts_or_categories(): void
    {
        $this->postJson('/api/v1/post-categories', ['name' => 'Kategori Baru'])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->putJson('/api/v1/post-categories/00000000-0000-0000-0000-000000000000', [])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->deleteJson('/api/v1/post-categories/00000000-0000-0000-0000-000000000000')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->getJson('/api/v1/posts')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->postJson('/api/v1/posts', [])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->putJson('/api/v1/posts/00000000-0000-0000-0000-000000000000', [])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->deleteJson('/api/v1/posts/00000000-0000-0000-0000-000000000000')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    /**
     * BE-CMS-02: User without permission cannot mutate posts or categories (403)
     */
    public function test_be_cms_02_user_without_permission_is_forbidden(): void
    {
        $this->actingAs($this->regularUser)
            ->postJson('/api/v1/post-categories', ['name' => 'Kategori Baru'])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        $this->actingAs($this->regularUser)
            ->getJson('/api/v1/posts')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        $this->actingAs($this->regularUser)
            ->postJson('/api/v1/posts', [
                'title' => 'Judul Berita',
                'content' => 'Isi Konten',
                'status' => 'draft',
                'visibility' => 'publik',
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    /**
     * BE-CMS-02: Admin can CRUD post categories with auto-slug and audit logs
     */
    public function test_be_cms_02_crud_post_categories_with_audit_logs(): void
    {
        // 1. Create Category (Auto-generated slug)
        $createResp = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/post-categories', [
                'name' => 'Prestasi & Penghargaan Siswa',
            ]);

        $createResp->assertStatus(201)
            ->assertJsonPath('data.name', 'Prestasi & Penghargaan Siswa')
            ->assertJsonPath('data.slug', 'prestasi-penghargaan-siswa');

        $catId = $createResp->json('data.id');

        $this->assertDatabaseHas('post_categories', [
            'id' => $catId,
            'slug' => 'prestasi-penghargaan-siswa',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'action' => 'post_category.created',
            'entity' => 'PostCategory',
            'entity_id' => $catId,
        ]);

        // 2. Show Category
        $this->getJson("/api/v1/post-categories/{$catId}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $catId);

        // 3. Update Category
        $updateResp = $this->actingAs($this->adminUser)
            ->putJson("/api/v1/post-categories/{$catId}", [
                'name' => 'Prestasi Siswa Terkini',
                'slug' => 'prestasi-siswa-terkini',
            ]);

        $updateResp->assertStatus(200)
            ->assertJsonPath('data.name', 'Prestasi Siswa Terkini')
            ->assertJsonPath('data.slug', 'prestasi-siswa-terkini');

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'action' => 'post_category.updated',
            'entity' => 'PostCategory',
            'entity_id' => $catId,
        ]);

        // 4. Delete Category
        $deleteResp = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/post-categories/{$catId}");

        $deleteResp->assertStatus(200)
            ->assertJsonPath('data.message', 'Kategori berita berhasil dihapus');

        $this->assertDatabaseMissing('post_categories', ['id' => $catId]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'action' => 'post_category.deleted',
            'entity' => 'PostCategory',
            'entity_id' => $catId,
        ]);
    }

    /**
     * BE-CMS-02: Cannot delete category with active posts (409 CONFLICT)
     */
    public function test_be_cms_02_cannot_delete_category_with_existing_posts(): void
    {
        $category = PostCategory::create([
            'name' => 'Akademik',
            'slug' => 'akademik',
        ]);

        Post::create([
            'category_id' => $category->id,
            'author_id' => $this->adminUser->id,
            'title' => 'Ujian Tengah Semester',
            'slug' => 'ujian-tengah-semester',
            'content' => 'Informasi pelaksanaan UTS semester ganjil.',
            'status' => 'terbit',
            'visibility' => 'publik',
            'published_at' => now(),
        ]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/post-categories/{$category->id}");

        $response->assertStatus(409)
            ->assertJsonPath('error.code', 'CONFLICT')
            ->assertJsonPath('error.message', 'Kategori tidak dapat dihapus karena masih digunakan oleh berita/artikel aktif.');

        $this->assertDatabaseHas('post_categories', ['id' => $category->id]);
    }

    /**
     * BE-CMS-02: Admin can create, update, and delete post with auto slug and audit logs
     */
    public function test_be_cms_02_admin_can_create_update_and_delete_post(): void
    {
        $category = PostCategory::create(['name' => 'Kegiatan', 'slug' => 'kegiatan']);

        // 1. Create Post
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/posts', [
                'title' => 'Siswa Labschool Juara 1 Robotik Nasional 2024',
                'content' => 'Tim robotik SMA Labschool UM berhasil menyabet medali emas di ajang kompetisi nasional.',
                'category_id' => $category->id,
                'status' => 'terbit',
                'visibility' => 'publik',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.title', 'Siswa Labschool Juara 1 Robotik Nasional 2024')
            ->assertJsonPath('data.slug', 'siswa-labschool-juara-1-robotik-nasional-2024')
            ->assertJsonPath('data.status', 'terbit')
            ->assertJsonPath('data.visibility', 'publik')
            ->assertJsonPath('data.author_id', $this->adminUser->id);

        $postId = $response->json('data.id');
        $this->assertNotNull($response->json('data.published_at'));

        $this->assertDatabaseHas('posts', [
            'id' => $postId,
            'slug' => 'siswa-labschool-juara-1-robotik-nasional-2024',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'action' => 'post.created',
            'entity' => 'Post',
            'entity_id' => $postId,
        ]);

        // 2. Show Post Detail for Admin
        $this->actingAs($this->adminUser)
            ->getJson("/api/v1/posts/{$postId}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $postId);

        // 3. Update Post
        $updateResp = $this->actingAs($this->adminUser)
            ->putJson("/api/v1/posts/{$postId}", [
                'title' => 'Revisi: Siswa Labschool Juara 1 Robotik Internasional 2024',
                'status' => 'arsip',
            ]);

        $updateResp->assertStatus(200)
            ->assertJsonPath('data.title', 'Revisi: Siswa Labschool Juara 1 Robotik Internasional 2024')
            ->assertJsonPath('data.status', 'arsip');

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'action' => 'post.updated',
            'entity' => 'Post',
            'entity_id' => $postId,
        ]);

        // 4. Delete Post
        $deleteResp = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/posts/{$postId}");

        $deleteResp->assertStatus(200)
            ->assertJsonPath('data.message', 'Berita berhasil dihapus');

        $this->assertDatabaseMissing('posts', ['id' => $postId]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'action' => 'post.deleted',
            'entity' => 'Post',
            'entity_id' => $postId,
        ]);
    }

    /**
     * BE-CMS-02: Admin can list and filter posts by status, category, and keyword
     */
    public function test_be_cms_02_admin_can_list_and_filter_posts(): void
    {
        $cat1 = PostCategory::create(['name' => 'Akademik', 'slug' => 'akademik']);
        $cat2 = PostCategory::create(['name' => 'Ekstrakurikuler', 'slug' => 'ekskul']);

        Post::create([
            'category_id' => $cat1->id,
            'author_id' => $this->adminUser->id,
            'title' => 'Jadwal Asesmen Sumatif Akhir Semester',
            'slug' => 'jadwal-asas',
            'content' => 'Daftar jadwal ujian.',
            'status' => 'terbit',
            'visibility' => 'publik',
            'published_at' => now(),
        ]);

        Post::create([
            'category_id' => $cat2->id,
            'author_id' => $this->adminUser->id,
            'title' => 'Latihan Rutin Tim Basket Sekolah',
            'slug' => 'latihan-basket',
            'content' => 'Setiap hari selasa sore.',
            'status' => 'draft',
            'visibility' => 'internal',
        ]);

        // Filter status = draft
        $respDraft = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/posts?status=draft');

        $respDraft->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.slug', 'latihan-basket');

        // Filter category_id = cat1
        $respCat = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/posts?category_id={$cat1->id}");

        $respCat->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.slug', 'jadwal-asas');

        // Search keyword
        $respSearch = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/posts?search=Basket');

        $respSearch->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.slug', 'latihan-basket');
    }

    /**
     * BE-CMS-02: Public can only view published public posts at or before scheduled time
     */
    public function test_be_cms_02_public_can_only_view_published_public_posts(): void
    {
        $cat = PostCategory::create(['name' => 'Pengumuman', 'slug' => 'pengumuman']);

        // 1. Published & Public in the past -> SHOULD BE VISIBLE
        $visiblePost = Post::create([
            'category_id' => $cat->id,
            'author_id' => $this->adminUser->id,
            'title' => 'Pengumuman Libur Hari Raya',
            'slug' => 'pengumuman-libur-hari-raya',
            'content' => 'Sekolah libur 2 hari.',
            'status' => 'terbit',
            'visibility' => 'publik',
            'published_at' => now()->subDay(),
        ]);

        // 2. Draft -> HIDDEN
        $draftPost = Post::create([
            'category_id' => $cat->id,
            'author_id' => $this->adminUser->id,
            'title' => 'Draf Berita Rahasia',
            'slug' => 'draf-berita',
            'content' => 'Belum siap tayang.',
            'status' => 'draft',
            'visibility' => 'publik',
        ]);

        // 3. Internal visibility -> HIDDEN
        $internalPost = Post::create([
            'category_id' => $cat->id,
            'author_id' => $this->adminUser->id,
            'title' => 'Rapat Dewan Guru Terbatas',
            'slug' => 'rapat-guru',
            'content' => 'Hanya untuk guru dan staf internal.',
            'status' => 'terbit',
            'visibility' => 'internal',
            'published_at' => now()->subHours(2),
        ]);

        // 4. Scheduled in Future -> HIDDEN
        $futurePost = Post::create([
            'category_id' => $cat->id,
            'author_id' => $this->adminUser->id,
            'title' => 'Berita Terjadwal Masa Depan',
            'slug' => 'berita-masa-depan',
            'content' => 'Akan tayang minggu depan.',
            'status' => 'terbit',
            'visibility' => 'publik',
            'published_at' => now()->addDays(5),
        ]);

        // 5. Archived -> HIDDEN
        $archivedPost = Post::create([
            'category_id' => $cat->id,
            'author_id' => $this->adminUser->id,
            'title' => 'Arsip Berita Usang',
            'slug' => 'arsip-berita',
            'content' => 'Sudah tidak relevan.',
            'status' => 'arsip',
            'visibility' => 'publik',
            'published_at' => now()->subMonths(6),
        ]);

        // Public index should ONLY return visiblePost
        $indexResp = $this->getJson('/api/v1/public/posts');

        $indexResp->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.slug', $visiblePost->slug);

        // Public show detail
        $this->getJson("/api/v1/public/posts/{$visiblePost->slug}")
            ->assertStatus(200)
            ->assertJsonPath('data.slug', $visiblePost->slug);

        // Public show on hidden posts must return 404
        $this->getJson("/api/v1/public/posts/{$draftPost->slug}")
            ->assertStatus(404);

        $this->getJson("/api/v1/public/posts/{$internalPost->slug}")
            ->assertStatus(404);

        $this->getJson("/api/v1/public/posts/{$futurePost->slug}")
            ->assertStatus(404);

        $this->getJson("/api/v1/public/posts/{$archivedPost->slug}")
            ->assertStatus(404);
    }

    /**
     * BE-CMS-02: Public viewing post detail increments views_count
     */
    public function test_be_cms_02_public_viewing_post_detail_increments_views_count(): void
    {
        $post = Post::create([
            'author_id' => $this->adminUser->id,
            'title' => 'Pendaftaran Ekstrakurikuler Paskibra',
            'slug' => 'pendaftaran-paskibra',
            'content' => 'Segera daftarkan diri Anda.',
            'status' => 'terbit',
            'visibility' => 'publik',
            'views_count' => 0,
            'published_at' => now(),
        ]);

        // First public view
        $this->getJson("/api/v1/public/posts/{$post->slug}")
            ->assertStatus(200)
            ->assertJsonPath('data.views_count', 1);

        $this->assertEquals(1, $post->fresh()->views_count);

        // Second public view
        $this->getJson("/api/v1/public/posts/{$post->slug}")
            ->assertStatus(200)
            ->assertJsonPath('data.views_count', 2);

        $this->assertEquals(2, $post->fresh()->views_count);
    }

    /**
     * BE-CMS-02: Public can list categories
     */
    public function test_be_cms_02_public_can_list_categories(): void
    {
        PostCategory::create(['name' => 'Kategori A', 'slug' => 'kategori-a']);
        PostCategory::create(['name' => 'Kategori B', 'slug' => 'kategori-b']);

        $response = $this->getJson('/api/v1/post-categories');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    /**
     * BE-CMS-02 / MULTI-TENANCY: School isolation prevents updating or deleting posts belonging to another school.
     */
    public function test_be_cms_02_tenant_isolation_prevents_updating_or_deleting_post_across_schools(): void
    {
        $schoolAId = '11111111-1111-1111-1111-111111111111';
        $schoolBId = '22222222-2222-2222-2222-222222222222';

        $this->adminUser->update(['school_id' => $schoolAId]);

        $postSchoolB = Post::create([
            'school_id' => $schoolBId,
            'author_id' => $this->adminUser->id,
            'title' => 'Pengumuman Internal Sekolah B',
            'slug' => 'pengumuman-internal-b',
            'content' => 'Konten khusus sekolah B.',
            'status' => 'draft',
            'visibility' => 'internal',
        ]);

        // Attempt show
        $showRes = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/posts/{$postSchoolB->id}");
        $showRes->assertStatus(403);

        // Attempt update
        $updateRes = $this->actingAs($this->adminUser)
            ->putJson("/api/v1/posts/{$postSchoolB->id}", [
                'title' => 'Coba bajak judul',
            ]);
        $updateRes->assertStatus(403);

        // Attempt delete
        $deleteRes = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/posts/{$postSchoolB->id}");
        $deleteRes->assertStatus(403);
    }

    /**
     * INT-05: Post creation and update with cover_file_id properly links file and serializes cover_file relation.
     */
    public function test_int_05_post_creation_and_update_with_cover_file_id_eager_loads_cover_file(): void
    {
        $coverFile1 = File::create([
            'school_id' => null,
            'owner_id' => $this->adminUser->id,
            'owner_type' => 'App\Models\User',
            'bucket' => 'default',
            'object_key' => 'https://cdn.example.com/cover1.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 54321,
            'visibility' => 'publik',
        ]);

        $coverFile2 = File::create([
            'school_id' => null,
            'owner_id' => $this->adminUser->id,
            'owner_type' => 'App\Models\User',
            'bucket' => 'default',
            'object_key' => 'https://cdn.example.com/cover2.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 98765,
            'visibility' => 'publik',
        ]);

        // 1. Create post with cover_file_id
        $createRes = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/posts', [
                'title' => 'Berita Dengan Sampul Eksklusif',
                'content' => 'Detail warta resmi dengan gambar sampul.',
                'status' => 'terbit',
                'visibility' => 'publik',
                'cover_file_id' => $coverFile1->id,
            ]);

        $createRes->assertStatus(201)
            ->assertJsonPath('data.cover_file_id', $coverFile1->id);

        $postId = $createRes->json('data.id');
        $slug = $createRes->json('data.slug');

        // 2. Fetch public post detail - ensure cover_file is eager loaded with url
        $publicDetailRes = $this->getJson("/api/v1/public/posts/{$slug}");
        $publicDetailRes->assertStatus(200)
            ->assertJsonPath('data.cover_file_id', $coverFile1->id)
            ->assertJsonPath('data.cover_file.id', $coverFile1->id)
            ->assertJsonPath('data.cover_file.url', 'https://cdn.example.com/cover1.jpg');

        // 3. Update post with new cover_file_id
        $updateRes = $this->actingAs($this->adminUser)
            ->putJson("/api/v1/posts/{$postId}", [
                'cover_file_id' => $coverFile2->id,
            ]);

        $updateRes->assertStatus(200)
            ->assertJsonPath('data.cover_file_id', $coverFile2->id);

        // 4. Fetch admin post list - ensure cover_file is eager loaded
        $adminListRes = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/posts');
        $adminListRes->assertStatus(200);

        $matchedPost = collect($adminListRes->json('data'))->firstWhere('id', $postId);
        $this->assertNotNull($matchedPost);
        $this->assertEquals($coverFile2->id, $matchedPost['cover_file_id']);
        $this->assertEquals('https://cdn.example.com/cover2.jpg', $matchedPost['cover_file']['url'] ?? null);
    }
}
