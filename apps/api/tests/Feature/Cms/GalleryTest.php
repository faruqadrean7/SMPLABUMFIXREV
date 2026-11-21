<?php

namespace Tests\Feature\Cms;

use App\Models\Album;
use App\Models\Bulletin;
use App\Models\File;
use App\Models\MediaItem;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GalleryTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $regularUser;

    protected Role $adminRole;

    protected Role $guestRole;

    protected Permission $permManageGallery;

    protected File $dummyImageFile;

    protected File $dummyPdfFile;

    protected function setUp(): void
    {
        parent::setUp();

        // Permissions
        $this->permManageGallery = Permission::create([
            'code' => 'cms.gallery.manage',
            'description' => 'Mengelola galeri foto, video, dan buletin digital CMS',
        ]);

        // Roles
        $this->adminRole = Role::create([
            'code' => 'super_admin',
            'name' => 'Super Administrator',
            'is_system' => true,
        ]);
        $this->adminRole->permissions()->attach([$this->permManageGallery->id]);

        $this->guestRole = Role::create([
            'code' => 'guest',
            'name' => 'Tamu',
            'is_system' => false,
        ]);

        // Users
        $this->adminUser = User::create([
            'username' => 'admin_galeri',
            'email' => 'admin.galeri@labschool.sch.id',
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

        // Dummy files for attachments
        $this->dummyImageFile = File::create([
            'bucket' => 'public-media',
            'object_key' => 'images/cover.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 204800,
            'visibility' => 'publik',
        ]);

        $this->dummyPdfFile = File::create([
            'bucket' => 'public-media',
            'object_key' => 'documents/buletin-edisi-1.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1048576,
            'visibility' => 'publik',
        ]);
    }

    /**
     * BE-CMS-03: Unauthenticated requests cannot mutate gallery or bulletins (401)
     */
    public function test_be_cms_03_unauthenticated_requests_cannot_mutate_gallery_or_bulletins(): void
    {
        $dummyId = '00000000-0000-0000-0000-000000000000';

        $this->postJson('/api/v1/albums', ['title' => 'Album Baru'])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->putJson("/api/v1/albums/{$dummyId}", ['title' => 'Revisi Album'])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->deleteJson("/api/v1/albums/{$dummyId}")
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->postJson('/api/v1/media', [])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->putJson("/api/v1/media/{$dummyId}", [])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->deleteJson("/api/v1/media/{$dummyId}")
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->postJson('/api/v1/bulletins', ['title' => 'Buletin Baru'])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->putJson("/api/v1/bulletins/{$dummyId}", ['title' => 'Revisi Buletin'])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->deleteJson("/api/v1/bulletins/{$dummyId}")
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    /**
     * BE-CMS-03: User without permission is forbidden (403)
     */
    public function test_be_cms_03_user_without_permission_is_forbidden(): void
    {
        $this->actingAs($this->regularUser)
            ->postJson('/api/v1/albums', ['title' => 'Album Rahasia'])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        $this->actingAs($this->regularUser)
            ->postJson('/api/v1/media', [
                'album_id' => '00000000-0000-0000-0000-000000000000',
                'file_id' => $this->dummyImageFile->id,
                'media_type' => 'image',
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        $this->actingAs($this->regularUser)
            ->postJson('/api/v1/bulletins', [
                'title' => 'Buletin Internal',
                'file_id' => $this->dummyPdfFile->id,
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    /**
     * BE-CMS-03: Admin can CRUD albums with audit logs
     */
    public function test_be_cms_03_admin_can_crud_albums_with_audit_logs(): void
    {
        // 1. Create Album
        $createResp = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/albums', [
                'title' => 'HUT SMA Labschool UM ke-20',
                'cover_file_id' => $this->dummyImageFile->id,
            ]);

        $createResp->assertStatus(201)
            ->assertJsonPath('data.title', 'HUT SMA Labschool UM ke-20')
            ->assertJsonPath('data.cover_file_id', $this->dummyImageFile->id);

        $albumId = $createResp->json('data.id');

        $this->assertDatabaseHas('albums', [
            'id' => $albumId,
            'title' => 'HUT SMA Labschool UM ke-20',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'action' => 'album.created',
            'entity' => 'Album',
            'entity_id' => $albumId,
        ]);

        // 2. Show Album Detail
        $this->actingAs($this->adminUser)
            ->getJson("/api/v1/albums/{$albumId}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $albumId)
            ->assertJsonPath('data.title', 'HUT SMA Labschool UM ke-20');

        // 3. Update Album
        $updateResp = $this->actingAs($this->adminUser)
            ->putJson("/api/v1/albums/{$albumId}", [
                'title' => 'Peringatan Akbar HUT SMA Labschool UM ke-20',
            ]);

        $updateResp->assertStatus(200)
            ->assertJsonPath('data.title', 'Peringatan Akbar HUT SMA Labschool UM ke-20');

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'action' => 'album.updated',
            'entity' => 'Album',
            'entity_id' => $albumId,
        ]);

        // 4. Delete Album
        $deleteResp = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/albums/{$albumId}");

        $deleteResp->assertStatus(200)
            ->assertJsonPath('data.message', 'Album berhasil dihapus');

        $this->assertDatabaseMissing('albums', ['id' => $albumId]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'action' => 'album.deleted',
            'entity' => 'Album',
            'entity_id' => $albumId,
        ]);
    }

    /**
     * BE-CMS-03: Admin can add, update, and delete media items with cascade test
     */
    public function test_be_cms_03_admin_can_manage_media_items_and_test_cascade(): void
    {
        $album = Album::create([
            'title' => 'Porseni Sekolah 2026',
        ]);

        // 1. Add Image Media Item
        $respImage = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/media', [
                'album_id' => $album->id,
                'file_id' => $this->dummyImageFile->id,
                'media_type' => 'image',
                'caption' => 'Upacara pembukaan porseni di lapangan utama',
            ]);

        $respImage->assertStatus(201)
            ->assertJsonPath('data.album_id', $album->id)
            ->assertJsonPath('data.media_type', 'image')
            ->assertJsonPath('data.caption', 'Upacara pembukaan porseni di lapangan utama');

        $mediaId = $respImage->json('data.id');

        $this->assertDatabaseHas('media_items', [
            'id' => $mediaId,
            'caption' => 'Upacara pembukaan porseni di lapangan utama',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'action' => 'media_item.created',
            'entity' => 'MediaItem',
            'entity_id' => $mediaId,
        ]);

        // 2. Add Video Media Item
        $videoFile = File::create([
            'bucket' => 'public-media',
            'object_key' => 'videos/highlight-final.mp4',
            'mime_type' => 'video/mp4',
            'size_bytes' => 52428800,
            'visibility' => 'publik',
        ]);

        $respVideo = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/media', [
                'album_id' => $album->id,
                'file_id' => $videoFile->id,
                'media_type' => 'video',
                'caption' => 'Highlight pertandingan basket final',
            ]);

        $respVideo->assertStatus(201)
            ->assertJsonPath('data.media_type', 'video');

        $videoId = $respVideo->json('data.id');

        // 3. Update Media Item Caption
        $updateResp = $this->actingAs($this->adminUser)
            ->putJson("/api/v1/media/{$mediaId}", [
                'caption' => 'Revisi: Pembukaan upacara porseni',
            ]);

        $updateResp->assertStatus(200)
            ->assertJsonPath('data.caption', 'Revisi: Pembukaan upacara porseni');

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'action' => 'media_item.updated',
            'entity' => 'MediaItem',
            'entity_id' => $mediaId,
        ]);

        // 4. Delete single media item
        $deleteResp = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/media/{$mediaId}");

        $deleteResp->assertStatus(200)
            ->assertJsonPath('data.message', 'Item media berhasil dihapus');

        $this->assertDatabaseMissing('media_items', ['id' => $mediaId]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'action' => 'media_item.deleted',
            'entity' => 'MediaItem',
            'entity_id' => $mediaId,
        ]);

        // 5. Cascade deletion check: deleting album removes video item
        $this->assertDatabaseHas('media_items', ['id' => $videoId]);
        $this->actingAs($this->adminUser)->deleteJson("/api/v1/albums/{$album->id}")->assertStatus(200);
        $this->assertDatabaseMissing('media_items', ['id' => $videoId]);
    }

    /**
     * BE-CMS-03: Admin can CRUD bulletins (Mading Digital) with audit logs
     */
    public function test_be_cms_03_admin_can_crud_bulletins_with_audit_logs(): void
    {
        // 1. Create Bulletin
        $createResp = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/bulletins', [
                'title' => 'Buletin Cendekia Edisi Oktober 2026',
                'file_id' => $this->dummyPdfFile->id,
                'cover_file_id' => $this->dummyImageFile->id,
            ]);

        $createResp->assertStatus(201)
            ->assertJsonPath('data.title', 'Buletin Cendekia Edisi Oktober 2026')
            ->assertJsonPath('data.file_id', $this->dummyPdfFile->id)
            ->assertJsonPath('data.cover_file_id', $this->dummyImageFile->id);

        $bulletinId = $createResp->json('data.id');

        $this->assertDatabaseHas('bulletins', [
            'id' => $bulletinId,
            'title' => 'Buletin Cendekia Edisi Oktober 2026',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'action' => 'bulletin.created',
            'entity' => 'Bulletin',
            'entity_id' => $bulletinId,
        ]);

        // 2. Show Bulletin Detail
        $this->actingAs($this->adminUser)
            ->getJson("/api/v1/bulletins/{$bulletinId}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $bulletinId)
            ->assertJsonPath('data.title', 'Buletin Cendekia Edisi Oktober 2026');

        // 3. Update Bulletin
        $updateResp = $this->actingAs($this->adminUser)
            ->putJson("/api/v1/bulletins/{$bulletinId}", [
                'title' => 'Majalah Sekolah Cendekia Edisi Oktober 2026 Revisi',
            ]);

        $updateResp->assertStatus(200)
            ->assertJsonPath('data.title', 'Majalah Sekolah Cendekia Edisi Oktober 2026 Revisi');

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'action' => 'bulletin.updated',
            'entity' => 'Bulletin',
            'entity_id' => $bulletinId,
        ]);

        // 4. Delete Bulletin
        $deleteResp = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/bulletins/{$bulletinId}");

        $deleteResp->assertStatus(200)
            ->assertJsonPath('data.message', 'Buletin berhasil dihapus');

        $this->assertDatabaseMissing('bulletins', ['id' => $bulletinId]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'action' => 'bulletin.deleted',
            'entity' => 'Bulletin',
            'entity_id' => $bulletinId,
        ]);
    }

    /**
     * BE-CMS-03: Admin can filter and search albums and bulletins
     */
    public function test_be_cms_03_admin_can_filter_and_search_albums_and_bulletins(): void
    {
        // Albums
        Album::create(['title' => 'Kunjungan Industri ke Telkom']);
        Album::create(['title' => 'Lomba Pramuka Penegak']);

        $searchAlbum = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/albums?search=Pramuka');

        $searchAlbum->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'Lomba Pramuka Penegak');

        // Bulletins
        Bulletin::create([
            'title' => 'Mading Edisi Sumpah Pemuda',
            'file_id' => $this->dummyPdfFile->id,
        ]);
        Bulletin::create([
            'title' => 'Mading Edisi Hari Pahlawan',
            'file_id' => $this->dummyPdfFile->id,
        ]);

        $searchBulletin = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/bulletins?search=Pahlawan');

        $searchBulletin->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'Mading Edisi Hari Pahlawan');
    }

    /**
     * BE-CMS-03: Public can view albums and bulletins
     */
    public function test_be_cms_03_public_can_view_albums_and_bulletins(): void
    {
        $album = Album::create([
            'title' => 'Dokumentasi Wisuda Angkatan 2026',
            'cover_file_id' => $this->dummyImageFile->id,
        ]);

        MediaItem::create([
            'album_id' => $album->id,
            'file_id' => $this->dummyImageFile->id,
            'media_type' => 'image',
            'caption' => 'Pelepasan lulusan terbaik',
        ]);

        $bulletin = Bulletin::create([
            'title' => 'Majalah Alumni Edisi Perdana',
            'file_id' => $this->dummyPdfFile->id,
            'cover_file_id' => $this->dummyImageFile->id,
        ]);

        // Public index albums
        $this->getJson('/api/v1/public/albums')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'Dokumentasi Wisuda Angkatan 2026')
            ->assertJsonPath('data.0.items_count', 1);

        // Public show album detail
        $this->getJson("/api/v1/public/albums/{$album->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $album->id)
            ->assertJsonPath('data.items.0.caption', 'Pelepasan lulusan terbaik');

        // Public index bulletins
        $this->getJson('/api/v1/public/bulletins')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'Majalah Alumni Edisi Perdana');

        // Public show bulletin detail
        $this->getJson("/api/v1/public/bulletins/{$bulletin->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $bulletin->id);

        // Public 404 for non-existent
        $this->getJson('/api/v1/public/albums/00000000-0000-0000-0000-000000000000')
            ->assertStatus(404);

        $this->getJson('/api/v1/public/bulletins/00000000-0000-0000-0000-000000000000')
            ->assertStatus(404);
    }
}
