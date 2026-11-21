import { describe, it, expect, vi, beforeEach } from 'vitest';
import { facilitiesApi } from '@/features/sarpras/facilities-api';
import { apiClient } from '@/lib/api/api-client';
import {
  FacilityItem,
  BookingItem,
  AvailabilityResult,
  BookingSummary,
  CreateFacilityPayload,
  UpdateFacilityPayload,
  CreateBookingPayload,
  DecideBookingPayload,
} from '@/features/sarpras/types';

describe('BE-SAR-01 / ADM-SAR-01: Sarana & Prasarana - Fasilitas & Peminjaman Ruang', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  // =========================================================================
  // 1. MASTER FASILITAS & RUANGAN
  // =========================================================================
  describe('1. Master Fasilitas & Ruangan', () => {
    it('mengambil daftar fasilitas dengan filter tipe, status, pencarian, dan pagination', async () => {
      const mockFacilities: FacilityItem[] = [
        {
          id: 'fac-1',
          code: 'FAC-AULA-01',
          name: 'Aula Serbaguna Graha UM',
          facility_type: 'ruang',
          location: 'Gedung Rektorat Lt 3',
          capacity: 300,
          requires_approval: true,
          is_active: true,
          status: 'tersedia',
        },
        {
          id: 'fac-2',
          code: 'FAC-PROJ-01',
          name: 'Proyektor Epson 4K Portable',
          facility_type: 'alat',
          location: 'Ruang Sarpras',
          requires_approval: false,
          is_active: true,
          status: 'tersedia',
        },
      ];

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: {
          data: mockFacilities,
          meta: { page: 1, limit: 10, total: 2, totalPages: 1 },
        },
      } as any);

      const res = await facilitiesApi.getFacilities({
        facility_type: 'ruang',
        status: 'tersedia',
        search: 'Aula',
      });

      expect(apiClient.get).toHaveBeenCalledWith('/facilities', {
        params: {
          facility_type: 'ruang',
          status: 'tersedia',
          search: 'Aula',
        },
      });
      expect(res.data).toHaveLength(2);
      expect(res.data[0].name).toBe('Aula Serbaguna Graha UM');
    });

    it('mengambil detail fasilitas berdasarkan ID', async () => {
      const mockFacility: FacilityItem = {
        id: 'fac-1',
        name: 'Laboratorium Multimedia',
        facility_type: 'ruang',
        capacity: 40,
        requires_approval: true,
        is_active: true,
        status: 'tersedia',
      };

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: { data: mockFacility },
      } as any);

      const res = await facilitiesApi.getFacility('fac-1');

      expect(apiClient.get).toHaveBeenCalledWith('/facilities/fac-1');
      expect(res.data.name).toBe('Laboratorium Multimedia');
    });

    it('membuat fasilitas baru dengan validasi data', async () => {
      const payload: CreateFacilityPayload = {
        name: 'Laboratorium Bahasa Digital',
        code: 'FAC-LAB-BAHASA',
        facility_type: 'ruang',
        location: 'Gedung C Lantai 2',
        capacity: 35,
        requires_approval: true,
        is_active: true,
        status: 'tersedia',
      };

      const mockCreated: FacilityItem = {
        id: 'fac-new',
        name: payload.name,
        code: payload.code,
        facility_type: payload.facility_type,
        location: payload.location,
        description: payload.description,
        capacity: payload.capacity,
        requires_approval: true,
        is_active: true,
        status: 'tersedia',
      };

      vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: { data: mockCreated },
      } as any);

      const res = await facilitiesApi.createFacility(payload);

      expect(apiClient.post).toHaveBeenCalledWith('/facilities', payload);
      expect(res.data.id).toBe('fac-new');
      expect(res.data.code).toBe('FAC-LAB-BAHASA');
    });

    it('memperbarui data fasilitas', async () => {
      const payload: UpdateFacilityPayload = {
        name: 'Laboratorium Komputer 1 (Upgrade AI)',
        capacity: 45,
        status: 'pemeliharaan',
      };

      vi.spyOn(apiClient, 'put').mockResolvedValueOnce({
        data: {
          data: {
            id: 'fac-1',
            facility_type: 'ruang',
            requires_approval: true,
            is_active: true,
            ...payload,
          },
        },
      } as any);

      const res = await facilitiesApi.updateFacility('fac-1', payload);

      expect(apiClient.put).toHaveBeenCalledWith('/facilities/fac-1', payload);
      expect(res.data.name).toContain('Upgrade AI');
      expect(res.data.status).toBe('pemeliharaan');
    });

    it('menghapus fasilitas', async () => {
      vi.spyOn(apiClient, 'delete').mockResolvedValueOnce({
        data: { data: { message: 'Fasilitas berhasil dihapus.' } },
      } as any);

      const res = await facilitiesApi.deleteFacility('fac-1');

      expect(apiClient.delete).toHaveBeenCalledWith('/facilities/fac-1');
      expect(res.data.message).toBe('Fasilitas berhasil dihapus.');
    });
  });

  // =========================================================================
  // 2. KETERSEDIAAN JADWAL & DETEKSI BENTROK
  // =========================================================================
  describe('2. Ketersediaan Real-Time & Deteksi Bentrok', () => {
    it('memeriksa ketersediaan jadwal bebas bentrok (tersedia = true)', async () => {
      const mockResult: AvailabilityResult = {
        facility: {
          id: 'fac-1',
          name: 'Aula Utama',
          facility_type: 'ruang',
          requires_approval: true,
          is_active: true,
          status: 'tersedia',
          is_eligible: true,
        },
        is_available: true,
        is_eligible: true,
        eligibility_message: null,
        conflicts: [],
        day_bookings: [],
      };

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: { data: mockResult },
      } as any);

      const res = await facilitiesApi.checkAvailability({
        facility_id: 'fac-1',
        start_at: '2026-10-20 08:00:00',
        end_at: '2026-10-20 11:00:00',
      });

      expect(apiClient.get).toHaveBeenCalledWith('/bookings/availability', {
        params: {
          facility_id: 'fac-1',
          start_at: '2026-10-20 08:00:00',
          end_at: '2026-10-20 11:00:00',
        },
      });
      expect(res.data.is_available).toBe(true);
      expect(res.data.conflicts).toHaveLength(0);
    });

    it('mendeteksi jadwal bentrok dengan peminjaman yang disetujui (tersedia = false)', async () => {
      const mockResult: AvailabilityResult = {
        facility: {
          id: 'fac-1',
          name: 'Aula Utama',
          facility_type: 'ruang',
          requires_approval: true,
          is_active: true,
          status: 'tersedia',
          is_eligible: true,
        },
        is_available: false,
        is_eligible: true,
        eligibility_message: null,
        conflicts: [
          {
            id: 'book-conflict-1',
            start_at: '2026-10-20 09:00:00',
            end_at: '2026-10-20 12:00:00',
            purpose: 'Seminar Nasional Pendidikan',
            requester: { id: 'user-1', username: 'guru_eko' },
          },
        ],
        day_bookings: [],
      };

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: { data: mockResult },
      } as any);

      const res = await facilitiesApi.checkAvailability({
        facility_id: 'fac-1',
        start_at: '2026-10-20 10:00:00',
        end_at: '2026-10-20 13:00:00',
      });

      expect(res.data.is_available).toBe(false);
      expect(res.data.conflicts).toHaveLength(1);
      expect(res.data.conflicts[0].purpose).toContain('Seminar Nasional');
    });

    it('mendeteksi fasilitas nonaktif atau dalam masa pemeliharaan (tidak layak dipesan)', async () => {
      const mockResult: AvailabilityResult = {
        facility: {
          id: 'fac-maint',
          name: 'Lab Fisika',
          facility_type: 'ruang',
          requires_approval: true,
          is_active: true,
          status: 'pemeliharaan',
          is_eligible: false,
        },
        is_available: false,
        is_eligible: false,
        eligibility_message: 'Fasilitas saat ini nonaktif atau sedang dalam masa pemeliharaan sehingga tidak dapat dipesan.',
        conflicts: [],
        day_bookings: [],
      };

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: { data: mockResult },
      } as any);

      const res = await facilitiesApi.checkAvailability({
        facility_id: 'fac-maint',
        start_at: '2026-10-20 08:00:00',
        end_at: '2026-10-20 10:00:00',
      });

      expect(res.data.is_eligible).toBe(false);
      expect(res.data.is_available).toBe(false);
      expect(res.data.eligibility_message).toContain('pemeliharaan');
    });
  });

  // =========================================================================
  // 3. PENGAJUAN PEMINJAMAN (BOOKINGS)
  // =========================================================================
  describe('3. Pengajuan Peminjaman Fasilitas', () => {
    it('mengambil daftar peminjaman dengan filter status, tanggal, dan pagination', async () => {
      const mockBookings: BookingItem[] = [
        {
          id: 'b-1',
          facility_id: 'fac-1',
          requested_by: 'user-budi',
          start_at: '2026-10-22 08:00:00',
          end_at: '2026-10-22 11:00:00',
          purpose: 'Rapat Pleno Guru',
          status: 'diajukan',
          facility: {
            id: 'fac-1',
            name: 'Aula Utama',
            facility_type: 'ruang',
            requires_approval: true,
            is_active: true,
            status: 'tersedia',
          },
          requester: { id: 'user-budi', username: 'budi_guru' },
        },
      ];

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: {
          data: mockBookings,
          meta: { page: 1, limit: 15, total: 1, totalPages: 1 },
        },
      } as any);

      const res = await facilitiesApi.getBookings({
        status: 'diajukan',
        from_date: '2026-10-22',
      });

      expect(apiClient.get).toHaveBeenCalledWith('/bookings', {
        params: { status: 'diajukan', from_date: '2026-10-22' },
      });
      expect(res.data).toHaveLength(1);
      expect(res.data[0].status).toBe('diajukan');
    });

    it('mengirim pengajuan peminjaman fasilitas baru', async () => {
      const payload: CreateBookingPayload = {
        facility_id: 'fac-1',
        start_at: '2026-10-23 09:00:00',
        end_at: '2026-10-23 12:00:00',
        purpose: 'Workshop Pengembangan Media Pembelajaran Interaktif',
        attendees_count: 50,
      };

      const mockBooking: BookingItem = {
        id: 'book-new',
        facility_id: payload.facility_id,
        start_at: payload.start_at,
        end_at: payload.end_at,
        purpose: payload.purpose,
        attendees_count: payload.attendees_count,
        requested_by: 'user-current',
        status: 'diajukan',
      };

      vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: { data: mockBooking },
      } as any);

      const res = await facilitiesApi.createBooking(payload);

      expect(apiClient.post).toHaveBeenCalledWith('/bookings', payload);
      expect(res.data.id).toBe('book-new');
      expect(res.data.status).toBe('diajukan');
      expect(res.data.attendees_count).toBe(50);
    });

    it('mengambil detail peminjaman fasilitas', async () => {
      const mockBooking: BookingItem = {
        id: 'book-1',
        facility_id: 'fac-1',
        requested_by: 'user-1',
        start_at: '2026-10-24 13:00:00',
        end_at: '2026-10-24 16:00:00',
        purpose: 'Latihan Paduan Suara Sekolah',
        status: 'disetujui',
      };

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: { data: mockBooking },
      } as any);

      const res = await facilitiesApi.getBooking('book-1');

      expect(apiClient.get).toHaveBeenCalledWith('/bookings/book-1');
      expect(res.data.purpose).toContain('Paduan Suara');
    });
  });

  // =========================================================================
  // 4. KEPUTUSAN PERSETUJUAN / PENOLAKAN / SELESAI
  // =========================================================================
  describe('4. Persetujuan & Penolakan Peminjaman (Admin / Sarpras Workflow)', () => {
    it('menyetujui pengajuan peminjaman fasilitas', async () => {
      const payload: DecideBookingPayload = {
        status: 'disetujui',
        decision_notes: 'Disetujui. Kunci dan remote proyektor dapat diambil di ruang Sarpras.',
      };

      vi.spyOn(apiClient, 'patch').mockResolvedValueOnce({
        data: {
          data: {
            id: 'book-1',
            status: 'disetujui',
            decision_notes: payload.decision_notes,
          },
        },
      } as any);

      const res = await facilitiesApi.decideBooking('book-1', payload);

      expect(apiClient.patch).toHaveBeenCalledWith('/bookings/book-1/decision', payload);
      expect(res.data.status).toBe('disetujui');
    });

    it('menolak pengajuan peminjaman fasilitas dengan alasan penolakan', async () => {
      const payload: DecideBookingPayload = {
        status: 'ditolak',
        decision_notes: 'Fasilitas sedang dijadwalkan untuk pemeliharaan kelistrikan mendadak.',
      };

      vi.spyOn(apiClient, 'patch').mockResolvedValueOnce({
        data: {
          data: {
            id: 'book-2',
            status: 'ditolak',
            decision_notes: payload.decision_notes,
          },
        },
      } as any);

      const res = await facilitiesApi.decideBooking('book-2', payload);

      expect(apiClient.patch).toHaveBeenCalledWith('/bookings/book-2/decision', payload);
      expect(res.data.status).toBe('ditolak');
    });

    it('menandai peminjaman fasilitas telah selesai digunakan', async () => {
      const payload: DecideBookingPayload = {
        status: 'selesai',
        decision_notes: 'Penggunaan selesai. Ruangan dan peralatan dikembalikan dalam kondisi bersih.',
      };

      vi.spyOn(apiClient, 'patch').mockResolvedValueOnce({
        data: {
          data: {
            id: 'book-3',
            status: 'selesai',
            decision_notes: payload.decision_notes,
          },
        },
      } as any);

      const res = await facilitiesApi.decideBooking('book-3', payload);

      expect(apiClient.patch).toHaveBeenCalledWith('/bookings/book-3/decision', payload);
      expect(res.data.status).toBe('selesai');
    });

    it('membatalkan peminjaman oleh pemohon', async () => {
      vi.spyOn(apiClient, 'delete').mockResolvedValueOnce({
        data: {
          data: {
            id: 'book-4',
            status: 'dibatalkan',
          },
        },
      } as any);

      const res = await facilitiesApi.cancelBooking('book-4');

      expect(apiClient.delete).toHaveBeenCalledWith('/bookings/book-4');
      expect(res.data.status).toBe('dibatalkan');
    });
  });

  // =========================================================================
  // 5. RINGKASAN METRIK & STATISTIK
  // =========================================================================
  describe('5. Ringkasan Eksekutif & Statistik Peminjaman', () => {
    it('mengambil ringkasan statistik operasional sarpras', async () => {
      const mockSummary: BookingSummary = {
        total_facilities: 12,
        active_facilities: 10,
        total_bookings: 35,
        pending_bookings: 4,
        approved_bookings: 18,
        rejected_bookings: 5,
        completed_bookings: 6,
        cancelled_bookings: 2,
        today_bookings: 3,
      };

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: { data: mockSummary },
      } as any);

      const res = await facilitiesApi.getSummary();

      expect(apiClient.get).toHaveBeenCalledWith('/bookings/summary');
      expect(res.data.total_facilities).toBe(12);
      expect(res.data.pending_bookings).toBe(4);
      expect(res.data.today_bookings).toBe(3);
    });
  });
});
