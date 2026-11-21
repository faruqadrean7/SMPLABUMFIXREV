import { describe, it, expect, vi, beforeEach } from 'vitest';
import { cmsApi } from '@/features/cms/cms-api';
import { apiClient } from '@/lib/api/api-client';
import {
  SiteProfile,
  SiteSettingsPayload,
  PostItem,
  PostCategoryItem,
  CreatePostPayload,
  SchoolEventItem,
  CreateEventPayload,
  DownloadItem,
  CreateDownloadPayload,
  AlbumItem,
  CreateAlbumPayload,
  PageItem,
  CreatePagePayload,
} from '@/features/cms/types';

describe('CMS & Portal Sekolah Publik (BE-CMS-01 s/d BE-CMS-06 / ADM-CMS-01 s/d ADM-CMS-06)', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  // =========================================================================
  // 1. PROFIL SITUS & PENGATURAN (BE-CMS-01 / ADM-CMS-01)
  // =========================================================================
  describe('1. Profil Situs & Pengaturan (BE-CMS-01 / ADM-CMS-01)', () => {
    it('mengambil konfigurasi profil situs sekolah untuk admin workspace', async () => {
      const mockProfile: SiteProfile = {
        identity: {
          name: 'SMA Laboratorium UM',
          tagline: 'Unggul & Berkarakter',
          logo_url: 'https://example.com/logo.png',
        },
        contact: {
          phone: '0341-123456',
          email: 'info@labum.sch.id',
          whatsapp: '081234567890',
        },
        profile: {
          vision: 'Menjadi SMA percontohan nasional berbasis riset.',
          mission: ['Meningkatkan mutu pembelajaran', 'Menumbuhkan nilai religius'],
        },
      };

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: { data: mockProfile },
      } as any);

      const res = await cmsApi.getSiteSettings();

      expect(apiClient.get).toHaveBeenCalledWith('/site-settings');
      expect(res.data?.identity?.name).toBe('SMA Laboratorium UM');
      expect(res.data?.profile?.mission).toHaveLength(2);
    });

    it('memperbarui konfigurasi profil situs sekolah', async () => {
      const updatePayload: SiteSettingsPayload = {
        identity: {
          name: 'SMA Laboratorium UM Malang - Updated',
        },
        contact: {
          phone: '0341-654321',
        },
      };

      vi.spyOn(apiClient, 'put').mockResolvedValueOnce({
        data: {
          data: {
            identity: { name: 'SMA Laboratorium UM Malang - Updated' },
            contact: { phone: '0341-654321' },
          },
        },
      } as any);

      const res = await cmsApi.updateSiteSettings(updatePayload);

      expect(apiClient.put).toHaveBeenCalledWith('/site-settings', updatePayload);
      expect(res.data?.identity?.name).toBe('SMA Laboratorium UM Malang - Updated');
    });

    it('mengambil profil situs sekolah untuk portal publik', async () => {
      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: {
          data: {
            identity: { name: 'Portal Resmi SMA Lab UM' },
          },
        },
      } as any);

      const res = await cmsApi.getPublicSite();

      expect(apiClient.get).toHaveBeenCalledWith('/public/site');
      expect(res.data?.identity?.name).toBe('Portal Resmi SMA Lab UM');
    });
  });

  // =========================================================================
  // 2. BERITA & PENGUMUMAN (BE-CMS-02 / ADM-CMS-02)
  // =========================================================================
  describe('2. Berita & Pengumuman (BE-CMS-02 / ADM-CMS-02)', () => {
    it('mengambil daftar berita dengan parameter filter dan pencarian', async () => {
      const mockPosts: PostItem[] = [
        {
          id: 'post-1',
          title: 'Sosialisasi Asesmen Nasional 2026',
          slug: 'sosialisasi-asesmen-nasional-2026',
          content: 'Naskah sosialisasi lengkap asesmen...',
          status: 'terbit',
          visibility: 'publik',
          published_at: '2026-10-14T08:00:00Z',
        },
      ];

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: {
          data: mockPosts,
          meta: { page: 1, limit: 10, total: 1, totalPages: 1 },
        },
      } as any);

      const res = await cmsApi.getPosts({
        q: 'Asesmen',
        status: 'terbit',
        visibility: 'publik',
        page: 1,
      });

      expect(apiClient.get).toHaveBeenCalledWith('/posts', {
        params: { q: 'Asesmen', status: 'terbit', visibility: 'publik', page: 1 },
      });
      expect(res.data).toHaveLength(1);
      expect(res.data[0].title).toBe('Sosialisasi Asesmen Nasional 2026');
    });

    it('membuat berita baru', async () => {
      const payload: CreatePostPayload = {
        title: 'Prestasi Juara 1 Olimpiade Fisika',
        content: 'Siswa SMA Lab UM berhasil meraih juara pertama...',
        status: 'terbit',
        visibility: 'publik',
      };

      vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: {
          data: {
            id: 'post-new-1',
            ...payload,
            slug: 'prestasi-juara-1-olimpiade-fisika',
          },
        },
      } as any);

      const res = await cmsApi.createPost(payload);

      expect(apiClient.post).toHaveBeenCalledWith('/posts', payload);
      expect(res.data?.id).toBe('post-new-1');
      expect(res.data?.slug).toBe('prestasi-juara-1-olimpiade-fisika');
    });

    it('memperbarui berita yang ada', async () => {
      vi.spyOn(apiClient, 'put').mockResolvedValueOnce({
        data: {
          data: {
            id: 'post-1',
            title: 'Judul Diperbarui',
            content: 'Konten baru',
            status: 'arsip',
          },
        },
      } as any);

      const res = await cmsApi.updatePost('post-1', {
        title: 'Judul Diperbarui',
        status: 'arsip',
      });

      expect(apiClient.put).toHaveBeenCalledWith('/posts/post-1', {
        title: 'Judul Diperbarui',
        status: 'arsip',
      });
      expect(res.data?.title).toBe('Judul Diperbarui');
    });

    it('menghapus berita', async () => {
      vi.spyOn(apiClient, 'delete').mockResolvedValueOnce({
        data: { data: { message: 'Berita berhasil dihapus.' } },
      } as any);

      const res = await cmsApi.deletePost('post-1');

      expect(apiClient.delete).toHaveBeenCalledWith('/posts/post-1');
      expect(res.data?.message).toContain('berhasil');
    });

    it('mengambil dan membuat kategori berita', async () => {
      const mockCategories: PostCategoryItem[] = [
        { id: 'cat-1', name: 'Prestasi', slug: 'prestasi' },
        { id: 'cat-2', name: 'Akademik', slug: 'akademik' },
      ];

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: { data: mockCategories },
      } as any);

      const listRes = await cmsApi.getPostCategories();
      expect(apiClient.get).toHaveBeenCalledWith('/post-categories');
      expect(listRes.data).toHaveLength(2);

      vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: { data: { id: 'cat-3', name: 'Kegiatan Siswa', slug: 'kegiatan-siswa' } },
      } as any);

      const createRes = await cmsApi.createPostCategory({ name: 'Kegiatan Siswa' });
      expect(apiClient.post).toHaveBeenCalledWith('/post-categories', { name: 'Kegiatan Siswa' });
      expect(createRes.data?.slug).toBe('kegiatan-siswa');
    });
  });

  // =========================================================================
  // 3. AGENDA & KALENDER AKADEMIK (BE-CMS-03 / ADM-CMS-03)
  // =========================================================================
  describe('3. Agenda & Kalender Kegiatan (BE-CMS-03 / ADM-CMS-03)', () => {
    it('mengambil daftar agenda dengan parameter kategori dan visibilitas', async () => {
      const mockEvents: SchoolEventItem[] = [
        {
          id: 'event-1',
          title: 'Asesmen Sumatif Akhir Semester',
          category: 'ujian',
          visibility: 'publik',
          start_at: '2026-11-01T07:00:00Z',
          end_at: '2026-11-10T12:00:00Z',
        },
      ];

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: {
          data: mockEvents,
          meta: { page: 1, limit: 15, total: 1, totalPages: 1 },
        },
      } as any);

      const res = await cmsApi.getEvents({
        category: 'ujian',
        visibility: 'publik',
      });

      expect(apiClient.get).toHaveBeenCalledWith('/events', {
        params: { category: 'ujian', visibility: 'publik' },
      });
      expect(res.data).toHaveLength(1);
      expect(res.data[0].category).toBe('ujian');
    });

    it('membuat agenda kegiatan baru', async () => {
      const payload: CreateEventPayload = {
        title: 'Karya Wisata Kelas X',
        category: 'event',
        visibility: 'internal',
        start_at: '2026-12-15T06:00:00Z',
        end_at: '2026-12-17T18:00:00Z',
      };

      vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: {
          data: { id: 'event-2', ...payload },
        },
      } as any);

      const res = await cmsApi.createEvent(payload);

      expect(apiClient.post).toHaveBeenCalledWith('/events', payload);
      expect(res.data?.id).toBe('event-2');
    });

    it('menghapus agenda kegiatan', async () => {
      vi.spyOn(apiClient, 'delete').mockResolvedValueOnce({
        data: { data: { message: 'Agenda berhasil dihapus.' } },
      } as any);

      const res = await cmsApi.deleteEvent('event-1');

      expect(apiClient.delete).toHaveBeenCalledWith('/events/event-1');
      expect(res.data?.message).toContain('berhasil');
    });
  });

  // =========================================================================
  // 4. PUSAT UNDUHAN DOKUMEN (BE-CMS-04 / ADM-CMS-04)
  // =========================================================================
  describe('4. Pusat Unduhan Dokumen (BE-CMS-04 / ADM-CMS-04)', () => {
    it('mengambil daftar dokumen unduhan', async () => {
      const mockDownloads: DownloadItem[] = [
        {
          id: 'dl-1',
          title: 'Pedoman Akademik 2026/2027',
          category: 'Pedoman',
          visibility: 'publik',
          file_id: 'file-uuid-1',
          download_count: 42,
        },
      ];

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: {
          data: mockDownloads,
          meta: { page: 1, limit: 10, total: 1, totalPages: 1 },
        },
      } as any);

      const res = await cmsApi.getDownloads({ q: 'Pedoman' });

      expect(apiClient.get).toHaveBeenCalledWith('/downloads', {
        params: { q: 'Pedoman' },
      });
      expect(res.data[0].download_count).toBe(42);
    });

    it('membuat dokumen unduhan baru', async () => {
      const payload: CreateDownloadPayload = {
        title: 'Formulir Mutasi Siswa',
        category: 'Formulir',
        visibility: 'publik',
        file_id: 'file-uuid-mutasi',
      };

      vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: {
          data: { id: 'dl-2', ...payload, download_count: 0 },
        },
      } as any);

      const res = await cmsApi.createDownload(payload);

      expect(apiClient.post).toHaveBeenCalledWith('/downloads', payload);
      expect(res.data?.id).toBe('dl-2');
    });

    it('menghapus dokumen unduhan', async () => {
      vi.spyOn(apiClient, 'delete').mockResolvedValueOnce({
        data: { data: { message: 'Dokumen berhasil dihapus.' } },
      } as any);

      const res = await cmsApi.deleteDownload('dl-1');

      expect(apiClient.delete).toHaveBeenCalledWith('/downloads/dl-1');
      expect(res.data?.message).toContain('berhasil');
    });
  });

  // =========================================================================
  // 5. GALERI FOTO & MEDIA (BE-CMS-05 / ADM-CMS-05)
  // =========================================================================
  describe('5. Galeri Foto & Media (BE-CMS-05 / ADM-CMS-05)', () => {
    it('mengambil daftar album foto', async () => {
      const mockAlbums: AlbumItem[] = [
        {
          id: 'album-1',
          title: 'Peringatan Hari Pahlawan 2026',
          photos_count: 24,
        },
      ];

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: {
          data: mockAlbums,
          meta: { page: 1, limit: 12, total: 1, totalPages: 1 },
        },
      } as any);

      const res = await cmsApi.getAlbums({ q: 'Pahlawan' });

      expect(apiClient.get).toHaveBeenCalledWith('/albums', {
        params: { q: 'Pahlawan' },
      });
      expect(res.data[0].photos_count).toBe(24);
    });

    it('membuat album foto baru', async () => {
      const payload: CreateAlbumPayload = {
        title: 'Lomba Paduan Suara 2026',
      };

      vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: {
          data: { id: 'album-2', title: 'Lomba Paduan Suara 2026', photos_count: 0 },
        },
      } as any);

      const res = await cmsApi.createAlbum(payload);

      expect(apiClient.post).toHaveBeenCalledWith('/albums', payload);
      expect(res.data?.id).toBe('album-2');
    });

    it('menghapus album foto', async () => {
      vi.spyOn(apiClient, 'delete').mockResolvedValueOnce({
        data: { data: { message: 'Album berhasil dihapus.' } },
      } as any);

      const res = await cmsApi.deleteAlbum('album-1');

      expect(apiClient.delete).toHaveBeenCalledWith('/albums/album-1');
      expect(res.data?.message).toContain('berhasil');
    });

    it('menambahkan item media/foto ke dalam album (INT-01)', async () => {
      const payload = {
        album_id: 'album-1',
        file_id: 'file-101',
        media_type: 'image' as const,
        caption: 'Foto Perayaan HUT',
      };

      vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: {
          data: {
            id: 'media-1',
            album_id: 'album-1',
            file_id: 'file-101',
            media_type: 'image',
            caption: 'Foto Perayaan HUT',
            file: {
              id: 'file-101',
              url: 'http://localhost:8000/storage/albums/hut.jpg',
            },
          },
        },
      } as any);

      const res = await cmsApi.createMediaItem(payload);

      expect(apiClient.post).toHaveBeenCalledWith('/media', payload);
      expect(res.data?.id).toBe('media-1');
      expect(res.data?.file?.url).toContain('hut.jpg');
    });

    it('menghapus item media/foto dari album (INT-01)', async () => {
      vi.spyOn(apiClient, 'delete').mockResolvedValueOnce({
        data: { data: { message: 'Item media berhasil dihapus' } },
      } as any);

      const res = await cmsApi.deleteMediaItem('media-1');

      expect(apiClient.delete).toHaveBeenCalledWith('/media/media-1');
      expect(res.data?.message).toContain('berhasil');
    });
  });

  // =========================================================================
  // 6. HALAMAN STATIS (BE-CMS-06 / ADM-CMS-06)
  // =========================================================================
  describe('6. Halaman Statis (BE-CMS-06 / ADM-CMS-06)', () => {
    it('mengambil daftar halaman statis', async () => {
      const mockPages: PageItem[] = [
        {
          id: 'page-1',
          title: 'Sejarah Singkat Sekolah',
          slug: 'sejarah-singkat-sekolah',
          content: 'Sejarah pendirian SMA Laboratorium UM...',
          status: 'terbit',
        },
      ];

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: {
          data: mockPages,
          meta: { page: 1, limit: 10, total: 1, totalPages: 1 },
        },
      } as any);

      const res = await cmsApi.getPages();

      expect(apiClient.get).toHaveBeenCalledWith('/pages');
      expect(res.data).toHaveLength(1);
    });

    it('membuat halaman statis baru', async () => {
      const payload: CreatePagePayload = {
        title: 'Struktur Organisasi Sekolah',
        content: 'Bagan dan deskripsi susunan pejabat sekolah...',
        status: 'terbit',
      };

      vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: {
          data: { id: 'page-2', ...payload, slug: 'struktur-organisasi-sekolah' },
        },
      } as any);

      const res = await cmsApi.createPage(payload);

      expect(apiClient.post).toHaveBeenCalledWith('/pages', payload);
      expect(res.data?.id).toBe('page-2');
    });

    it('menghapus halaman statis', async () => {
      vi.spyOn(apiClient, 'delete').mockResolvedValueOnce({
        data: { data: { message: 'Halaman berhasil dihapus.' } },
      } as any);

      const res = await cmsApi.deletePage('page-1');

      expect(apiClient.delete).toHaveBeenCalledWith('/pages/page-1');
      expect(res.data?.message).toContain('berhasil');
    });
  });
});
