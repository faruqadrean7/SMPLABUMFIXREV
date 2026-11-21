import { describe, it, expect, vi, beforeEach } from 'vitest';
import { publicRelationsApi } from '@/features/public-relations/public-relations-api';
import { apiClient } from '@/lib/api/api-client';
import {
  GuestItem,
  GuestSignatureDetail,
  ComplaintItem,
  PartnershipItem,
  AlumniItem,
} from '@/features/public-relations/types';

describe('BE-GUEST-01 / ADM-GUEST-01 / BE-GUEST-02 / ADM-GUEST-02 / BE-GUEST-03 / BE-HUM-01: Public Relations & Humas Module', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  // =========================================================================
  // 1. BUKU TAMU DIGITAL (BE-GUEST-01 / ADM-GUEST-01)
  // =========================================================================
  describe('1. Buku Tamu Digital (BE-GUEST-01 / ADM-GUEST-01)', () => {
    it('mengambil daftar kunjungan buku tamu dengan parameter filter dan pencarian', async () => {
      const mockGuests: GuestItem[] = [
        {
          id: 'guest-1',
          full_name: 'Drs. Hendro Wibowo',
          institution: 'Dinas Pendidikan Kota Malang',
          phone: '081234567890',
          purpose: 'Koordinasi akreditasi',
          check_in: '2026-10-14T08:30:00Z',
          check_out: null,
        },
        {
          id: 'guest-2',
          full_name: 'Ir. Siti Aminah',
          institution: 'Balai Besar Guru Penggerak',
          phone: '081398765432',
          purpose: 'Monev program pembelajaran',
          check_in: '2026-10-14T09:00:00Z',
          check_out: '2026-10-14T10:30:00Z',
        },
      ];

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: {
          data: mockGuests,
          meta: { page: 1, limit: 15, total: 2, totalPages: 1 },
        },
      } as any);

      const res = await publicRelationsApi.getGuests({
        q: 'Hendro',
        status: 'checked_in',
        page: 1,
        per_page: 15,
      });

      expect(apiClient.get).toHaveBeenCalledWith('/guests', {
        params: { q: 'Hendro', status: 'checked_in', page: 1, per_page: 15 },
      });
      expect(res.data).toHaveLength(2);
      expect(res.data[0].full_name).toBe('Drs. Hendro Wibowo');
    });

    it('mengambil detail kunjungan tamu berdasarkan ID', async () => {
      const mockGuest: GuestItem = {
        id: 'guest-1',
        full_name: 'Drs. Hendro Wibowo',
        institution: 'Dinas Pendidikan',
        phone: '081234567890',
        purpose: 'Koordinasi kurikulum',
        check_in: '2026-10-14T08:30:00Z',
      };

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: { data: mockGuest },
      } as any);

      const res = await publicRelationsApi.getGuest('guest-1');

      expect(apiClient.get).toHaveBeenCalledWith('/guests/guest-1');
      expect(res.data.id).toBe('guest-1');
      expect(res.data.full_name).toBe('Drs. Hendro Wibowo');
    });

    it('melakukan checkout tamu dan mencatat jam keluar', async () => {
      const mockUpdatedGuest: GuestItem = {
        id: 'guest-1',
        full_name: 'Drs. Hendro Wibowo',
        phone: '081234567890',
        purpose: 'Koordinasi',
        check_in: '2026-10-14T08:30:00Z',
        check_out: '2026-10-14T11:00:00Z',
      };

      vi.spyOn(apiClient, 'patch').mockResolvedValueOnce({
        data: { data: mockUpdatedGuest },
      } as any);

      const res = await publicRelationsApi.checkoutGuest('guest-1');

      expect(apiClient.patch).toHaveBeenCalledWith('/guests/guest-1/checkout');
      expect(res.data.check_out).toBe('2026-10-14T11:00:00Z');
    });

    it('mendaftarkan tamu baru dari portal publik resepsionis', async () => {
      const payload = {
        full_name: 'Prof. Suparman',
        institution: 'Universitas Negeri Malang',
        phone: '081555555555',
        purpose: 'Narasumber workshop pedagogik',
      };

      const mockResponse: GuestItem = {
        id: 'guest-new',
        ...payload,
        check_in: '2026-10-14T10:00:00Z',
      };

      vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: { data: mockResponse },
      } as any);

      const res = await publicRelationsApi.registerGuestPublic(payload);

      expect(apiClient.post).toHaveBeenCalledWith('/public/guests', payload);
      expect(res.data.id).toBe('guest-new');
      expect(res.data.full_name).toBe('Prof. Suparman');
    });
  });

  // =========================================================================
  // 2. TANDA TANGAN DIGITAL BUKU TAMU (BE-GUEST-03 / ADM-GUEST-03)
  // =========================================================================
  describe('2. Tanda Tangan Digital Integritas (BE-GUEST-03 / ADM-GUEST-03)', () => {
    it('mengambil metadata dan verifikasi integritas checksum sha256 tanda tangan', async () => {
      const mockSignature: GuestSignatureDetail = {
        guest_id: 'guest-1',
        full_name: 'Drs. Hendro Wibowo',
        signature_file_id: 'file-sig-1',
        signature_hash: 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
        integrity_verified: true,
        mime_type: 'image/png',
        size_bytes: 4096,
        verified_at: '2026-10-14T08:35:00Z',
      };

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: { data: mockSignature },
      } as any);

      const res = await publicRelationsApi.getGuestSignature('guest-1');

      expect(apiClient.get).toHaveBeenCalledWith('/guests/guest-1/signature');
      expect(res.data.integrity_verified).toBe(true);
      expect(res.data.signature_hash).toHaveLength(64);
    });

    it('mengunggah tanda tangan digital canvas via endpoint publik', async () => {
      const payload = {
        signature_data: 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
      };

      const mockResponse: GuestSignatureDetail = {
        guest_id: 'guest-1',
        full_name: 'Drs. Hendro Wibowo',
        signature_file_id: 'file-sig-new',
        signature_hash: '9f86d081884c7d659a2feaa0c55ad015a3bf4f1b2b0b822cd15d6c15b0f00a08',
        integrity_verified: true,
        mime_type: 'image/png',
        size_bytes: 68,
        verified_at: '2026-10-14T08:35:00Z',
      };

      vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: { data: mockResponse },
      } as any);

      const res = await publicRelationsApi.uploadGuestSignaturePublic('guest-1', payload);

      expect(apiClient.post).toHaveBeenCalledWith('/public/guests/guest-1/signature', payload, {
        headers: undefined,
      });
      expect(res.data.integrity_verified).toBe(true);
    });
  });

  // =========================================================================
  // 3. ASPIRASI & WHISTLEBLOWING (BE-GUEST-02 / ADM-GUEST-02)
  // =========================================================================
  describe('3. Aspirasi & Whistleblowing (BE-GUEST-02 / ADM-GUEST-02)', () => {
    it('mengambil daftar pengaduan dengan filter kategori dan status', async () => {
      const mockComplaints: ComplaintItem[] = [
        {
          id: 'comp-1',
          ticket_code: 'ASP-20261014-X89K',
          category: 'fasilitas',
          content: 'Kerusakan keran air di toilet lantai 2.',
          is_anonymous: true,
          status: 'baru',
          created_at: '2026-10-14T07:00:00Z',
          updated_at: '2026-10-14T07:00:00Z',
        },
        {
          id: 'comp-2',
          ticket_code: 'ASP-20261014-P78L',
          category: 'bullying',
          content: 'Dugaan intimidasi di kantin belakang.',
          is_anonymous: false,
          status: 'ditinjau',
          created_at: '2026-10-14T08:00:00Z',
          updated_at: '2026-10-14T08:30:00Z',
          reporter: { id: 'u-1', username: 'siswa_pelapor', email: 'siswa@sch.id' },
        },
      ];

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: {
          data: mockComplaints,
          meta: { page: 1, limit: 15, total: 2, totalPages: 1 },
        },
      } as any);

      const res = await publicRelationsApi.getComplaints({
        category: 'fasilitas',
        status: 'baru',
        page: 1,
        per_page: 15,
      });

      expect(apiClient.get).toHaveBeenCalledWith('/complaints', {
        params: { category: 'fasilitas', status: 'baru', page: 1, per_page: 15 },
      });
      expect(res.data).toHaveLength(2);
      expect(res.data[0].ticket_code).toBe('ASP-20261014-X89K');
    });

    it('mengambil detail pengaduan beserta kronologi event perkembangan', async () => {
      const mockComplaint: ComplaintItem = {
        id: 'comp-1',
        ticket_code: 'ASP-20261014-X89K',
        category: 'fasilitas',
        content: 'Kerusakan keran air di toilet lantai 2.',
        is_anonymous: true,
        status: 'ditindaklanjuti',
        created_at: '2026-10-14T07:00:00Z',
        updated_at: '2026-10-14T09:00:00Z',
        events: [
          {
            id: 'ev-1',
            complaint_id: 'comp-1',
            event_type: 'created',
            response_text: 'Aspirasi masuk.',
            created_at: '2026-10-14T07:00:00Z',
          },
          {
            id: 'ev-2',
            complaint_id: 'comp-1',
            event_type: 'status_update',
            response_text: 'Teknisi sarpras sedang menuju lokasi untuk perbaikan pipa.',
            created_at: '2026-10-14T09:00:00Z',
          },
        ],
      };

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: { data: mockComplaint },
      } as any);

      const res = await publicRelationsApi.getComplaint('comp-1');

      expect(apiClient.get).toHaveBeenCalledWith('/complaints/comp-1');
      expect(res.data.events).toHaveLength(2);
      expect(res.data.is_anonymous).toBe(true);
    });

    it('memperbarui status pengaduan dan mencatat catatan tindak lanjut', async () => {
      const payload = {
        status: 'selesai' as const,
        response_text: 'Perbaikan keran air telah selesai dilakukan dan diuji coba.',
      };

      const mockResponse: ComplaintItem = {
        id: 'comp-1',
        ticket_code: 'ASP-20261014-X89K',
        category: 'fasilitas',
        content: 'Kerusakan keran air di toilet lantai 2.',
        is_anonymous: true,
        status: 'selesai',
        created_at: '2026-10-14T07:00:00Z',
        updated_at: '2026-10-14T11:00:00Z',
      };

      vi.spyOn(apiClient, 'patch').mockResolvedValueOnce({
        data: { data: mockResponse },
      } as any);

      const res = await publicRelationsApi.updateComplaintStatus('comp-1', payload);

      expect(apiClient.patch).toHaveBeenCalledWith('/complaints/comp-1/status', payload);
      expect(res.data.status).toBe('selesai');
    });

    it('mengirimkan pengaduan baru melalui kanal publik tanpa membocorkan identitas whistleblower', async () => {
      const payload = {
        category: 'pungli',
        content: 'Dugaan iuran tidak resmi pada kegiatan ekstra.',
        is_anonymous: true,
      };

      const mockResponse = {
        ticket_code: 'ASP-20261014-99AA',
        category: 'pungli',
        is_anonymous: true,
        status: 'baru',
        message: 'Pengaduan berhasil didaftarkan.',
      };

      vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: { data: mockResponse },
      } as any);

      const res = await publicRelationsApi.submitComplaintPublic(payload);

      expect(apiClient.post).toHaveBeenCalledWith('/public/complaints', payload);
      expect(res.data.ticket_code).toBe('ASP-20261014-99AA');
      expect(res.data.is_anonymous).toBe(true);
    });

    it('memeriksa pelacakan status pengaduan via kode tiket publik tanpa autentikasi', async () => {
      const mockTrackingResult = {
        ticket_code: 'ASP-20261014-99AA',
        category: 'pungli',
        content: 'Dugaan iuran tidak resmi pada kegiatan ekstra.',
        status: 'ditinjau',
        created_at: '2026-10-14T07:00:00Z',
        updated_at: '2026-10-14T08:00:00Z',
        timeline: [
          {
            event_type: 'created',
            response_text: 'Pengaduan masuk.',
            created_at: '2026-10-14T07:00:00Z',
          },
        ],
      };

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: { data: mockTrackingResult },
      } as any);

      const res = await publicRelationsApi.trackComplaintPublic('ASP-20261014-99AA');

      expect(apiClient.get).toHaveBeenCalledWith('/public/complaints/ASP-20261014-99AA');
      expect(res.data.ticket_code).toBe('ASP-20261014-99AA');
      expect(res.data.timeline).toHaveLength(1);
    });
  });

  // =========================================================================
  // 4. KEMITRAAN & DOKUMEN MOU (BE-HUM-01 / ADM-HUM-01)
  // =========================================================================
  describe('4. Kemitraan & Dokumen MoU (BE-HUM-01 / ADM-HUM-01)', () => {
    it('mengambil daftar kemitraan dengan filter pencarian dan tipe', async () => {
      const mockPartnerships: PartnershipItem[] = [
        {
          id: 'part-1',
          partner_name: 'PT Telkom Indonesia',
          mou_number: '045/MOU/2026',
          partner_type: 'industri',
          contact_person: 'Budi Santoso',
          scope: 'PKL dan sertifikasi industri',
          valid_from: '2026-01-01',
          valid_until: '2027-01-01',
          status: 'aktif',
        },
      ];

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: {
          data: mockPartnerships,
          meta: { page: 1, limit: 15, total: 1, totalPages: 1 },
        },
      } as any);

      const res = await publicRelationsApi.getPartnerships({
        search: 'Telkom',
        partner_type: 'industri',
      });

      expect(apiClient.get).toHaveBeenCalledWith('/partnerships', {
        params: { search: 'Telkom', partner_type: 'industri' },
      });
      expect(res.data).toHaveLength(1);
      expect(res.data[0].partner_name).toBe('PT Telkom Indonesia');
    });

    it('mengambil daftar MoU yang mendekati masa kedaluwarsa', async () => {
      const mockExpiring: PartnershipItem[] = [
        {
          id: 'part-expiring-1',
          partner_name: 'CV Kreatif Grafika',
          scope: 'Magang siswa',
          valid_from: '2025-10-20',
          valid_until: '2026-10-25',
          status: 'aktif',
          partner_type: 'industri',
        },
      ];

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: { data: mockExpiring },
      } as any);

      const res = await publicRelationsApi.getExpiringPartnerships(30);

      expect(apiClient.get).toHaveBeenCalledWith('/partnerships/expiring', {
        params: { within_days: 30 },
      });
      expect(res.data).toHaveLength(1);
    });

    it('membuat kemitraan baru dan memperbarui data kemitraan', async () => {
      const createPayload = {
        partner_name: 'Universitas Brawijaya',
        partner_type: 'universitas',
        scope: 'Riset bersama dan pengabdian masyarakat',
        valid_from: '2026-11-01',
        valid_until: '2028-11-01',
      };

      const mockCreated: PartnershipItem = {
        id: 'part-new',
        ...createPayload,
        status: 'aktif',
      };

      vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: { data: mockCreated },
      } as any);

      const resCreate = await publicRelationsApi.createPartnership(createPayload);
      expect(apiClient.post).toHaveBeenCalledWith('/partnerships', createPayload);
      expect(resCreate.data.id).toBe('part-new');

      // Update
      const updatePayload = { scope: 'Riset bersama, pengabdian, dan kuliah tamu' };
      vi.spyOn(apiClient, 'put').mockResolvedValueOnce({
        data: { data: { ...mockCreated, ...updatePayload } },
      } as any);

      const resUpdate = await publicRelationsApi.updatePartnership('part-new', updatePayload);
      expect(apiClient.put).toHaveBeenCalledWith('/partnerships/part-new', updatePayload);
      expect(resUpdate.data.scope).toBe(updatePayload.scope);
    });

    it('menghapus kemitraan yang sudah tidak berlaku', async () => {
      vi.spyOn(apiClient, 'delete').mockResolvedValueOnce({
        data: { data: { message: 'Kemitraan berhasil dihapus' } },
      } as any);

      const res = await publicRelationsApi.deletePartnership('part-old');
      expect(apiClient.delete).toHaveBeenCalledWith('/partnerships/part-old');
      expect(res.data.message).toBe('Kemitraan berhasil dihapus');
    });
  });

  // =========================================================================
  // 5. ALUMNI & TRACER STUDY (BE-HUM-02 / ADM-HUM-02)
  // =========================================================================
  describe('5. Alumni & Tracer Study (BE-HUM-02 / ADM-HUM-02)', () => {
    it('mengambil direktori alumni dengan parameter pencarian dan tahun kelulusan', async () => {
      const mockAlumni: AlumniItem[] = [
        {
          id: 'alum-1',
          full_name: 'Ahmad Fauzi',
          graduation_year: 2024,
          current_activity: 'kuliah',
          institution_name: 'Institut Teknologi Sepuluh Nopember',
          is_public: true,
        },
      ];

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: {
          data: mockAlumni,
          meta: { page: 1, limit: 15, total: 1, totalPages: 1 },
        },
      } as any);

      const res = await publicRelationsApi.getAlumni({
        graduation_year: 2024,
        search: 'Fauzi',
      });

      expect(apiClient.get).toHaveBeenCalledWith('/alumni', {
        params: { graduation_year: 2024, search: 'Fauzi' },
      });
      expect(res.data).toHaveLength(1);
      expect(res.data[0].full_name).toBe('Ahmad Fauzi');
    });
  });
});
