import { describe, it, expect, vi, beforeEach } from 'vitest';
import { counselingApi } from '@/features/counseling/counseling-api';
import { apiClient } from '@/lib/api/api-client';
import {
  CounselingRequestItem,
  CreateCounselingRequestPayload,
  ScheduleCounselingRequestPayload,
  RejectCounselingRequestPayload,
  CompleteCounselingRequestPayload,
} from '@/features/counseling/types';

describe('ADM-BK-03 / BE-BK-03 / SIS-BK-01 / ORT-BK-01: Permintaan Konseling Siswa & Wali SISTLABUM', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  const mockRequest: CounselingRequestItem = {
    id: 'req-001',
    student_id: 'std-101',
    requested_by: 'usr-student-1',
    requester_role: 'student',
    topic: '[Pribadi] Konseling Masalah Belajar',
    preferred_date: '2026-10-20',
    preferred_time: '10:00 WIB',
    status: 'pending',
    notes: 'Ingin berdiskusi mengenai fokus belajar dan manajemen waktu.',
    created_at: '2026-10-14T08:00:00Z',
    student: {
      id: 'std-101',
      full_name: 'Budi Santoso',
      nis: '2026001',
      nisn: '0012345678',
      classes: [{ id: 'cls-1', name: 'X-MIPA-1' }],
    },
    requester: {
      id: 'usr-student-1',
      name: 'Budi Santoso',
      username: 'budi2026',
    },
  };

  describe('1. Pengambilan Daftar & Detail Permintaan Konseling', () => {
    it('mengambil daftar permintaan konseling dengan filter status dan pagination', async () => {
      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: {
          data: [mockRequest],
          meta: { page: 1, limit: 10, total: 1, totalPages: 1 },
        },
      } as any);

      const res = await counselingApi.getRequests({
        status: 'pending',
        search: 'Budi',
        page: 1,
        per_page: 10,
      });

      expect(apiClient.get).toHaveBeenCalledWith('/counseling/requests', {
        params: {
          status: 'pending',
          search: 'Budi',
          page: 1,
          per_page: 10,
        },
      });
      expect(res.data).toHaveLength(1);
      expect(res.data[0].id).toBe('req-001');
      expect(res.data[0].status).toBe('pending');
    });

    it('mengambil detail permintaan konseling berdasarkan ID', async () => {
      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: {
          data: mockRequest,
        },
      } as any);

      const res = await counselingApi.getRequest('req-001');

      expect(apiClient.get).toHaveBeenCalledWith('/counseling/requests/req-001');
      expect(res.data.id).toBe('req-001');
      expect(res.data.student?.full_name).toBe('Budi Santoso');
    });
  });

  describe('2. Pengajuan Permintaan Konseling (Create Request)', () => {
    it('mengirimkan permintaan konseling baru oleh siswa atau wali', async () => {
      const payload: CreateCounselingRequestPayload = {
        student_id: 'std-101',
        topic: '[Belajar] Kesulitan Pembelajaran Matematika',
        preferred_date: '2026-10-25',
        preferred_time: 'Istirahat Pertama',
        notes: 'Perlu konsultasi metode belajar efektif.',
      };

      vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: {
          data: {
            ...mockRequest,
            ...payload,
            id: 'req-002',
          },
        },
      } as any);

      const res = await counselingApi.createRequest(payload);

      expect(apiClient.post).toHaveBeenCalledWith('/counseling/requests', payload);
      expect(res.data.id).toBe('req-002');
      expect(res.data.topic).toBe(payload.topic);
    });
  });

  describe('3. Penjadwalan & Disposisi Guru BK (Schedule Request)', () => {
    it('menjadwalkan permintaan konseling dengan waktu temu dan konselor terpilih', async () => {
      const payload: ScheduleCounselingRequestPayload = {
        scheduled_at: '2026-10-22T09:00:00Z',
        counselor_id: 'counselor-01',
        notes: 'Dijadwalkan di Ruang BK 1 bersama Bu Siti.',
      };

      const scheduledItem: CounselingRequestItem = {
        ...mockRequest,
        status: 'scheduled',
        scheduled_at: payload.scheduled_at,
        counselor_id: payload.counselor_id,
        notes: payload.notes,
      };

      vi.spyOn(apiClient, 'patch').mockResolvedValueOnce({
        data: {
          data: scheduledItem,
        },
      } as any);

      const res = await counselingApi.scheduleRequest('req-001', payload);

      expect(apiClient.patch).toHaveBeenCalledWith('/counseling/requests/req-001/schedule', payload);
      expect(res.data.status).toBe('scheduled');
      expect(res.data.counselor_id).toBe('counselor-01');
    });
  });

  describe('4. Penolakan Permintaan Konseling (Reject Request)', () => {
    it('menolak permintaan konseling dengan alasan yang jelas', async () => {
      const payload: RejectCounselingRequestPayload = {
        rejection_reason: 'Jadwal penuh untuk minggu ini, silakan ajukan ulang untuk minggu depan.',
      };

      const rejectedItem: CounselingRequestItem = {
        ...mockRequest,
        status: 'rejected',
        rejection_reason: payload.rejection_reason,
      };

      vi.spyOn(apiClient, 'patch').mockResolvedValueOnce({
        data: {
          data: rejectedItem,
        },
      } as any);

      const res = await counselingApi.rejectRequest('req-001', payload);

      expect(apiClient.patch).toHaveBeenCalledWith('/counseling/requests/req-001/reject', payload);
      expect(res.data.status).toBe('rejected');
      expect(res.data.rejection_reason).toBe(payload.rejection_reason);
    });
  });

  describe('5. Penyelesaian & Eskalasi Kasus (Complete Request)', () => {
    it('menyelesaikan permintaan konseling dengan pembukaan berkas kasus dan sesi konseling baru', async () => {
      const payload: CompleteCounselingRequestPayload = {
        notes: 'Sesi konseling telah tuntas dilaksanakan.',
        create_case: true,
        case_category: 'Belajar',
        case_summary: 'Siswa mengalami hambatan belajar pada materi aljabar.',
        session_notes: 'Diberikan tips belajar mandiri dan koordinasi dengan guru mapel.',
        follow_up: 'Evaluasi hasil ulangan berikutnya pada bulan November.',
      };

      const completedItem: CounselingRequestItem = {
        ...mockRequest,
        status: 'completed',
        notes: payload.notes,
      };

      vi.spyOn(apiClient, 'patch').mockResolvedValueOnce({
        data: {
          data: completedItem,
        },
      } as any);

      const res = await counselingApi.completeRequest('req-001', payload);

      expect(apiClient.patch).toHaveBeenCalledWith('/counseling/requests/req-001/complete', payload);
      expect(res.data.status).toBe('completed');
    });
  });

  describe('6. Pembatalan Permintaan Konseling (Cancel Request)', () => {
    it('membatalkan permintaan konseling yang masih menunggu', async () => {
      const cancelledItem: CounselingRequestItem = {
        ...mockRequest,
        status: 'cancelled',
      };

      vi.spyOn(apiClient, 'delete').mockResolvedValueOnce({
        data: {
          data: cancelledItem,
        },
      } as any);

      const res = await counselingApi.cancelRequest('req-001');

      expect(apiClient.delete).toHaveBeenCalledWith('/counseling/requests/req-001');
      expect(res.data.status).toBe('cancelled');
    });
  });
});
