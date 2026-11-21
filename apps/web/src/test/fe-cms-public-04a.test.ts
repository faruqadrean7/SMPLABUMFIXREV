import { describe, it, expect, vi, beforeEach } from 'vitest';
import { publicApi } from '@/features/public-site/public-api';
import { cmsApi } from '@/features/cms/cms-api';
import { apiClient } from '@/lib/api/api-client';
import { PUBLIC_NAV_ITEMS, FOOTER_QUICK_LINKS } from '@/features/public-site/content/navigation';
import { PublicBulletinItem } from '@/features/public-site/types';
import { BulletinItem } from '@/features/cms/types';

describe('FE-CMS-PUBLIC-04a: Fitur Buletin & Majalah Sekolah (Admin CMS + Publik)', () => {
  beforeEach(() => {
    vi.restoreAllMocks();
  });

  describe('1. publicApi Buletin Publik Endpoints (BE-CMS-03: GET /api/v1/public/bulletins)', () => {
    it('FE-CMS-PUBLIC-04a (AC1): getPublicBulletins memanggil GET /public/bulletins dengan parameter yang sesuai', async () => {
      const mockBulletins: PublicBulletinItem[] = [
        {
          id: 'bul-01',
          title: 'Buletin Laboratorium Edisi 10: Inovasi Pembelajaran Abad 21',
          file_id: 'file-pdf-01',
          cover_file_id: 'file-cover-01',
          created_at: '2026-10-01T08:00:00Z',
          file: {
            id: 'file-pdf-01',
            mime_type: 'application/pdf',
            size_bytes: 4194304,
            url: 'https://storage.smplab-um.sch.id/bulletins/edisi-10.pdf',
            download_url: 'https://storage.smplab-um.sch.id/bulletins/edisi-10.pdf',
          },
          cover: {
            id: 'file-cover-01',
            url: 'https://storage.smplab-um.sch.id/bulletins/cover-10.jpg',
          },
        },
        {
          id: 'bul-02',
          title: 'Buletin Pelajar Edisi Khusus Lustrum',
          file_id: 'file-pdf-02',
          cover_file_id: null,
          created_at: '2026-08-15T09:00:00Z',
          file: {
            id: 'file-pdf-02',
            mime_type: 'application/pdf',
            size_bytes: 3145728,
            url: 'https://storage.smplab-um.sch.id/bulletins/edisi-khusus.pdf',
          },
          cover: null,
        },
      ];

      const getSpy = vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: {
          data: mockBulletins,
          meta: {
            currentPage: 1,
            totalPages: 1,
            totalItems: 2,
            itemsPerPage: 9,
          },
        },
      });

      const res = await publicApi.getPublicBulletins({
        page: 1,
        per_page: 9,
        search: 'Inovasi',
      });

      expect(getSpy).toHaveBeenCalledWith('/public/bulletins', {
        params: {
          page: 1,
          per_page: 9,
          search: 'Inovasi',
        },
      });

      expect(res.data).toHaveLength(2);
      expect(res.data[0].title).toBe('Buletin Laboratorium Edisi 10: Inovasi Pembelajaran Abad 21');
      expect(res.data[0].file?.size_bytes).toBe(4194304);
      expect(res.data[0].cover?.url).toBe('https://storage.smplab-um.sch.id/bulletins/cover-10.jpg');
    });

    it('FE-CMS-PUBLIC-04a: getPublicBulletin memanggil GET /public/bulletins/{id}', async () => {
      const mockBulletin: PublicBulletinItem = {
        id: 'bul-detail-123',
        title: 'Buletin Sains & Riset Siswa',
        file_id: 'file-sains-123',
      };

      const getSpy = vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: {
          data: mockBulletin,
        },
      });

      const res = await publicApi.getPublicBulletin('bul-detail-123');

      expect(getSpy).toHaveBeenCalledWith('/public/bulletins/bul-detail-123');
      expect(res.id).toBe('bul-detail-123');
      expect(res.title).toBe('Buletin Sains & Riset Siswa');
    });
  });

  describe('2. cmsApi Buletin Admin Endpoints (BE-CMS-03: CRUD /api/v1/bulletins)', () => {
    it('FE-CMS-PUBLIC-04a (AC1): getBulletins memanggil GET /bulletins', async () => {
      const mockAdminBulletins: BulletinItem[] = [
        {
          id: 'adm-bul-01',
          title: 'Majalah Sekolah Triwulan I',
          file_id: 'file-01',
          created_at: '2026-03-01T00:00:00Z',
        },
      ];

      const getSpy = vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: {
          data: mockAdminBulletins,
          meta: {
            currentPage: 1,
            totalPages: 1,
            totalItems: 1,
            itemsPerPage: 15,
          },
        },
      });

      const res = await cmsApi.getBulletins({ search: 'Triwulan' });

      expect(getSpy).toHaveBeenCalledWith('/bulletins', {
        params: { search: 'Triwulan' },
      });
      expect(res.data).toHaveLength(1);
      expect(res.data[0].title).toBe('Majalah Sekolah Triwulan I');
    });

    it('FE-CMS-PUBLIC-04a (AC1): createBulletin memanggil POST /bulletins dengan payload valid', async () => {
      const payload = {
        title: 'Buletin Baru Edisi 2026',
        file_id: 'file-uuid-1234',
        cover_file_id: 'cover-uuid-5678',
      };

      const postSpy = vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: {
          data: {
            id: 'new-bul-01',
            ...payload,
          },
        },
      });

      const res = await cmsApi.createBulletin(payload);

      expect(postSpy).toHaveBeenCalledWith('/bulletins', payload);
      expect(res.data.id).toBe('new-bul-01');
      expect(res.data.title).toBe('Buletin Baru Edisi 2026');
    });

    it('FE-CMS-PUBLIC-04a: updateBulletin memanggil PUT /bulletins/{id}', async () => {
      const payload = {
        title: 'Buletin Revisi Edisi 2026',
      };

      const putSpy = vi.spyOn(apiClient, 'put').mockResolvedValueOnce({
        data: {
          data: {
            id: 'bul-01',
            title: 'Buletin Revisi Edisi 2026',
            file_id: 'file-uuid-1234',
          },
        },
      });

      const res = await cmsApi.updateBulletin('bul-01', payload);

      expect(putSpy).toHaveBeenCalledWith('/bulletins/bul-01', payload);
      expect(res.data.title).toBe('Buletin Revisi Edisi 2026');
    });

    it('FE-CMS-PUBLIC-04a: deleteBulletin memanggil DELETE /bulletins/{id}', async () => {
      const deleteSpy = vi.spyOn(apiClient, 'delete').mockResolvedValueOnce({
        data: {
          data: { message: 'Buletin berhasil dihapus' },
        },
      });

      const res = await cmsApi.deleteBulletin('bul-del-99');

      expect(deleteSpy).toHaveBeenCalledWith('/bulletins/bul-del-99');
      expect(res.data.message).toBe('Buletin berhasil dihapus');
    });
  });

  describe('3. Validitas Link Navigasi Menu Publik (AC4)', () => {
    it('FE-CMS-PUBLIC-04a (AC4): PUBLIC_NAV_ITEMS memuat tautan rute /buletin', () => {
      const bulletinNav = PUBLIC_NAV_ITEMS.find((n) => n.href === '/buletin');
      expect(bulletinNav).toBeDefined();
      expect(bulletinNav?.label).toBe('Buletin');
    });

    it('FE-CMS-PUBLIC-04a (AC4): FOOTER_QUICK_LINKS memuat tautan rute /buletin', () => {
      const bulletinFooter = FOOTER_QUICK_LINKS.find((n) => n.href === '/buletin');
      expect(bulletinFooter).toBeDefined();
      expect(bulletinFooter?.label).toBe('Buletin & Majalah');
    });
  });
});
