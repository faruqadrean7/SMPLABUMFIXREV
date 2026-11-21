import { describe, it, expect, vi, beforeEach } from 'vitest';
import { publicApi } from '@/features/public-site/public-api';
import { apiClient } from '@/lib/api/api-client';
import { PUBLIC_NAV_ITEMS, FOOTER_QUICK_LINKS } from '@/features/public-site/content/navigation';
import { PublicEventItem, PublicDownloadItem } from '@/features/public-site/types';

describe('FE-CMS-PUBLIC-01: Public CMS Agenda & Unduhan', () => {
  beforeEach(() => {
    vi.restoreAllMocks();
  });

  describe('1. publicApi Event & Agenda Endpoints (BE-CMS-04: GET /api/v1/public/events)', () => {
    it('FE-CMS-PUBLIC-01 (AC1): getPublicEvents memanggil GET /public/events dengan parameter yang sesuai', async () => {
      const mockEvents: PublicEventItem[] = [
        {
          id: 'evt-01',
          title: 'Asesmen Sumatif Akhir Semester Ganjil',
          category: 'ujian',
          visibility: 'publik',
          start_at: '2026-12-01T07:30:00Z',
          end_at: '2026-12-08T12:00:00Z',
        },
        {
          id: 'evt-02',
          title: 'Workshop Robotika & Sains SMP Lab UM',
          category: 'event',
          visibility: 'publik',
          start_at: '2026-12-15T08:00:00Z',
          end_at: '2026-12-15T14:00:00Z',
        },
      ];

      const getSpy = vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: {
          data: mockEvents,
          meta: {
            currentPage: 1,
            totalPages: 1,
            totalItems: 2,
            itemsPerPage: 12,
          },
        },
      });

      const res = await publicApi.getPublicEvents({
        page: 1,
        per_page: 12,
        category: 'ujian',
        search: 'Sumatif',
      });

      expect(getSpy).toHaveBeenCalledWith('/public/events', {
        params: {
          page: 1,
          per_page: 12,
          category: 'ujian',
          search: 'Sumatif',
          month: undefined,
          year: undefined,
          start_date: undefined,
          end_date: undefined,
          all: undefined,
        },
      });

      expect(res.data).toHaveLength(2);
      expect(res.data[0].title).toBe('Asesmen Sumatif Akhir Semester Ganjil');
      expect(res.data[0].category).toBe('ujian');
    });

    it('FE-CMS-PUBLIC-01: getPublicEvent memanggil GET /public/events/{id}', async () => {
      const mockEvent: PublicEventItem = {
        id: 'evt-detail-123',
        title: 'Peringatan Hari Guru Nasional',
        category: 'event',
        visibility: 'publik',
        start_at: '2026-11-25T07:00:00Z',
        end_at: '2026-11-25T11:30:00Z',
      };

      const getSpy = vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: {
          data: mockEvent,
        },
      });

      const result = await publicApi.getPublicEvent('evt-detail-123');

      expect(getSpy).toHaveBeenCalledWith('/public/events/evt-detail-123');
      expect(result.id).toBe('evt-detail-123');
      expect(result.title).toBe('Peringatan Hari Guru Nasional');
    });

    it('FE-CMS-PUBLIC-01 (AC1): getLatestEvents mengambil ringkasan agenda terdekat untuk beranda', async () => {
      const mockLatest: PublicEventItem[] = [
        {
          id: 'evt-01',
          title: 'Kegiatan A',
          category: 'event',
          visibility: 'publik',
          start_at: '2026-11-10T08:00:00Z',
          end_at: '2026-11-10T12:00:00Z',
        },
        {
          id: 'evt-02',
          title: 'Kegiatan B',
          category: 'libur',
          visibility: 'publik',
          start_at: '2026-11-15T00:00:00Z',
          end_at: '2026-11-15T23:59:59Z',
        },
      ];

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: {
          data: mockLatest,
        },
      });

      const items = await publicApi.getLatestEvents(3);
      expect(items).toHaveLength(2);
      expect(items[0].title).toBe('Kegiatan A');
    });
  });

  describe('2. publicApi Download Endpoints (BE-CMS-06: GET /api/v1/public/downloads)', () => {
    it('FE-CMS-PUBLIC-01 (AC2): getPublicDownloads memanggil GET /public/downloads dengan parameter yang sesuai', async () => {
      const mockDownloads: PublicDownloadItem[] = [
        {
          id: 'dl-01',
          title: 'Panduan Akademik & Kalender Pendidikan 2026/2027',
          category: 'panduan',
          visibility: 'publik',
          file_id: 'file-pdf-01',
          download_count: 42,
          file: {
            id: 'file-pdf-01',
            mime_type: 'application/pdf',
            size_bytes: 2048576,
            url: 'https://storage.smplab-um.sch.id/files/panduan.pdf',
          },
        },
      ];

      const getSpy = vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: {
          data: mockDownloads,
          meta: {
            currentPage: 1,
            totalPages: 1,
            totalItems: 1,
            itemsPerPage: 10,
          },
        },
      });

      const res = await publicApi.getPublicDownloads({
        page: 1,
        per_page: 10,
        category: 'panduan',
        search: 'Kalender',
      });

      expect(getSpy).toHaveBeenCalledWith('/public/downloads', {
        params: {
          page: 1,
          per_page: 10,
          category: 'panduan',
          search: 'Kalender',
          all: undefined,
        },
      });

      expect(res.data).toHaveLength(1);
      expect(res.data[0].title).toBe('Panduan Akademik & Kalender Pendidikan 2026/2027');
      expect(res.data[0].file?.size_bytes).toBe(2048576);
    });

    it('FE-CMS-PUBLIC-01: getPublicDownload memanggil GET /public/downloads/{id}', async () => {
      const mockDownload: PublicDownloadItem = {
        id: 'dl-detail-789',
        title: 'Formulir Pendaftaran Ekskul Riset & Robotika',
        category: 'formulir',
        visibility: 'publik',
        file_id: 'file-form-789',
      };

      const getSpy = vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: {
          data: mockDownload,
        },
      });

      const res = await publicApi.getPublicDownload('dl-detail-789');

      expect(getSpy).toHaveBeenCalledWith('/public/downloads/dl-detail-789');
      expect(res.id).toBe('dl-detail-789');
      expect(res.title).toBe('Formulir Pendaftaran Ekskul Riset & Robotika');
    });
  });

  describe('3. Validitas Navigasi Header & Footer Publik (AC4)', () => {
    it('FE-CMS-PUBLIC-01 (AC4): PUBLIC_NAV_ITEMS memuat link rute /agenda dan /unduhan', () => {
      const agendaNav = PUBLIC_NAV_ITEMS.find((n) => n.href === '/agenda');
      const unduhanNav = PUBLIC_NAV_ITEMS.find((n) => n.href === '/unduhan');

      expect(agendaNav).toBeDefined();
      expect(agendaNav?.label).toBe('Agenda');

      expect(unduhanNav).toBeDefined();
      expect(unduhanNav?.label).toBe('Unduhan');
    });

    it('FE-CMS-PUBLIC-01 (AC4): FOOTER_QUICK_LINKS memuat link rute /agenda dan /unduhan', () => {
      const agendaFooter = FOOTER_QUICK_LINKS.find((n) => n.href === '/agenda');
      const unduhanFooter = FOOTER_QUICK_LINKS.find((n) => n.href === '/unduhan');

      expect(agendaFooter).toBeDefined();
      expect(agendaFooter?.label).toBe('Agenda Kegiatan');

      expect(unduhanFooter).toBeDefined();
      expect(unduhanFooter?.label).toBe('Pusat Unduhan');
    });
  });
});
