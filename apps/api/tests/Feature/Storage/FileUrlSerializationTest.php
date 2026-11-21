<?php

namespace Tests\Feature\Storage;

use App\Models\Album;
use App\Models\File;
use App\Models\MediaItem;
use App\Models\Post;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileUrlSerializationTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $roleAdmin = Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Admin', 'is_system' => true]);

        $this->adminUser = User::create([
            'username' => 'admin_test_url',
            'email' => 'admin_test_url@sekolah.sch.id',
            'password_hash' => Hash::make('Secret123!'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($roleAdmin->id);
    }

    /**
     * INT-03: Berkas publik yang valid menghasilkan URL publik resmi pada serialisasi.
     */
    public function test_int_03_public_file_serializes_with_valid_storage_url(): void
    {
        $file = File::create([
            'owner_id' => $this->adminUser->id,
            'owner_type' => 'User',
            'bucket' => 'public',
            'object_key' => 'public/2026/10/banner_wisuda.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 10240,
            'visibility' => 'publik',
        ]);

        $serialized = $file->toArray();

        $this->assertArrayHasKey('url', $serialized);
        $this->assertNotNull($serialized['url']);
        $this->assertStringContainsString('banner_wisuda.jpg', $serialized['url']);
        $this->assertEquals(Storage::disk('public')->url('public/2026/10/banner_wisuda.jpg'), $serialized['url']);
    }

    /**
     * INT-03: Berkas privat TIDAK BOLEH menghasilkan URL publik (harus bernilai null).
     */
    public function test_int_03_private_file_serializes_with_null_url(): void
    {
        $file = File::create([
            'owner_id' => $this->adminUser->id,
            'owner_type' => 'User',
            'bucket' => 'private',
            'object_key' => 'private/2026/10/rapor_rahasia.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 20480,
            'visibility' => 'privat',
        ]);

        $serialized = $file->toArray();

        $this->assertArrayHasKey('url', $serialized);
        $this->assertNull($serialized['url'], 'Berkas privat tidak boleh membocorkan URL publik');
    }

    /**
     * INT-03: Berkas tanpa object_key mengembalikan null tanpa menimbulkan exception.
     */
    public function test_int_03_file_without_object_key_returns_null_url(): void
    {
        $file = new File([
            'bucket' => 'public',
            'object_key' => '',
            'mime_type' => 'image/png',
            'size_bytes' => 0,
            'visibility' => 'publik',
        ]);

        $serialized = $file->toArray();

        $this->assertArrayHasKey('url', $serialized);
        $this->assertNull($serialized['url']);
    }

    /**
     * INT-03: Berkas dengan object_key berupa URL eksternal/CDN absolut dikembalikan langsung.
     */
    public function test_int_03_file_with_absolute_url_returns_same_url(): void
    {
        $cdnUrl = 'https://cdn.sekolah.sch.id/media/foto.webp';
        $file = new File([
            'bucket' => 'public',
            'object_key' => $cdnUrl,
            'mime_type' => 'image/webp',
            'size_bytes' => 5000,
            'visibility' => 'publik',
        ]);

        $serialized = $file->toArray();

        $this->assertEquals($cdnUrl, $serialized['url']);
    }

    /**
     * INT-03: Relasi cover album pada endpoint publik GET /api/v1/public/albums menyertakan properti url.
     */
    public function test_int_03_public_albums_endpoint_includes_cover_url(): void
    {
        $coverFile = File::create([
            'bucket' => 'public',
            'object_key' => 'albums/covers/hut_sekolah.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 50000,
            'visibility' => 'publik',
        ]);

        $album = Album::create([
            'title' => 'Peringatan HUT SMP Lab UM Ke-50',
            'cover_file_id' => $coverFile->id,
        ]);

        $response = $this->getJson('/api/v1/public/albums');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.id', $album->id)
            ->assertJsonPath('data.0.cover.id', $coverFile->id);

        $coverUrl = $response->json('data.0.cover.url');
        $this->assertNotNull($coverUrl, 'Cover album harus memiliki URL yang dapat diakses frontend');
        $this->assertStringContainsString('hut_sekolah.jpg', $coverUrl);
    }

    /**
     * INT-03: Relasi item media pada endpoint detail album GET /api/v1/public/albums/{id} menyertakan file.url.
     */
    public function test_int_03_public_album_detail_endpoint_includes_media_items_file_url(): void
    {
        $coverFile = File::create([
            'bucket' => 'public',
            'object_key' => 'albums/covers/olympiad.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 45000,
            'visibility' => 'publik',
        ]);

        $mediaFile = File::create([
            'bucket' => 'public',
            'object_key' => 'albums/photos/photo_1.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 60000,
            'visibility' => 'publik',
        ]);

        $album = Album::create([
            'title' => 'Dokumentasi Olimpiade Sains 2026',
            'cover_file_id' => $coverFile->id,
        ]);

        MediaItem::create([
            'album_id' => $album->id,
            'file_id' => $mediaFile->id,
            'media_type' => 'image',
            'caption' => 'Penyerahan medali emas',
        ]);

        $response = $this->getJson('/api/v1/public/albums/'.$album->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $album->id)
            ->assertJsonPath('data.cover.id', $coverFile->id)
            ->assertJsonPath('data.items.0.caption', 'Penyerahan medali emas');

        $coverUrl = $response->json('data.cover.url');
        $photoUrl = $response->json('data.items.0.file.url');

        $this->assertNotNull($coverUrl);
        $this->assertNotNull($photoUrl);
        $this->assertStringContainsString('olympiad.jpg', $coverUrl);
        $this->assertStringContainsString('photo_1.jpg', $photoUrl);
    }

    /**
     * INT-03: Relasi cover_file pada endpoint publik berita GET /api/v1/public/posts menyertakan url.
     */
    public function test_int_03_public_posts_endpoint_includes_cover_file_url(): void
    {
        $coverFile = File::create([
            'bucket' => 'public',
            'object_key' => 'news/covers/robotika.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 30000,
            'visibility' => 'publik',
        ]);

        Post::create([
            'title' => 'Tim Robotika Meraih Juara Nasional',
            'slug' => 'tim-robotika-meraih-juara-nasional-2026',
            'content' => 'Prestasi membanggakan kembali diraih peserta didik.',
            'author_id' => $this->adminUser->id,
            'cover_file_id' => $coverFile->id,
            'status' => 'terbit',
            'visibility' => 'publik',
            'published_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/public/posts');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.slug', 'tim-robotika-meraih-juara-nasional-2026')
            ->assertJsonPath('data.0.cover_file.id', $coverFile->id);

        $postCoverUrl = $response->json('data.0.cover_file.url');
        $this->assertNotNull($postCoverUrl);
        $this->assertStringContainsString('robotika.jpg', $postCoverUrl);
    }
}
