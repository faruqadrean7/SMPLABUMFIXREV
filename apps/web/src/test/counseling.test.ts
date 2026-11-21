import { describe, it, expect, vi, beforeEach } from 'vitest';
import { counselingApi } from '@/features/counseling/counseling-api';
import { apiClient } from '@/lib/api/api-client';
import {
  CounselingCaseItem,
  CounselingSessionItem,
  CounselingReferralItem,
  CreateCounselingCasePayload,
  UpdateCounselingCasePayload,
  CreateCounselingSessionPayload,
  CreateCounselingReferralPayload,
  UpdateCounselingReferralStatusPayload,
} from '@/features/counseling/types';

describe('ADM-BK-01 / BE-BK-01: Kasus Konseling & Sesi Pembinaan Siswa SISTLABUM', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  // =========================================================================
  // 1. KASUS KONSELING (CASES)
  // =========================================================================
  describe('1. Manajemen Kasus Konseling', () => {
    it('mengambil daftar kasus konseling dengan filter status, kategori, dan pagination', async () => {
      const mockCases: CounselingCaseItem[] = [
        {
          id: 'case-1',
          student_id: 'std-101',
          counselor_id: 'user-bk-1',
          category: 'Pribadi',
          status: 'aktif',
          summary_enc: 'Catatan kasus terenkripsi AES-256 yang didekripsi untuk guru BK.',
          student: {
            id: 'std-101',
            full_name: 'Ahmad Fauzan',
            nis: '202401',
            nisn: '0012345678',
            classes: [{ id: 'cls-1', name: 'X-MIPA-1' }],
          },
          counselor: {
            id: 'user-bk-1',
            name: 'Guru BK Siti',
            teacher: { id: 'tch-1', full_name: 'Dra. Hj. Siti Rahmawati', nip: '198501012010011005' },
          },
          sessions_count: 2,
        },
      ];

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: {
          data: mockCases,
          meta: { page: 1, limit: 10, total: 1, totalPages: 1 },
        },
      } as any);

      const res = await counselingApi.getCases({
        category: 'Pribadi',
        status: 'aktif',
        page: 1,
        per_page: 10,
      });

      expect(apiClient.get).toHaveBeenCalledWith('/counseling/cases', {
        params: {
          category: 'Pribadi',
          status: 'aktif',
          page: 1,
          per_page: 10,
        },
      });
      expect(res.data).toHaveLength(1);
      expect(res.data[0].id).toBe('case-1');
      expect(res.data[0].student?.full_name).toBe('Ahmad Fauzan');
      expect(res.data[0].counselor?.teacher?.full_name).toBe('Dra. Hj. Siti Rahmawati');
    });

    it('mengambil detail satu kasus konseling beserta riwayat sesinya', async () => {
      const mockCaseDetail: CounselingCaseItem = {
        id: 'case-1',
        student_id: 'std-101',
        counselor_id: 'user-bk-1',
        category: 'Pribadi',
        status: 'aktif',
        summary_enc: 'Catatan kasus terenkripsi yang didekripsi untuk guru BK berwenang.',
        student: {
          id: 'std-101',
          full_name: 'Ahmad Fauzan',
          nis: '202401',
          classes: [{ id: 'cls-1', name: 'X-MIPA-1' }],
        },
        sessions: [
          {
            id: 'session-1',
            case_id: 'case-1',
            session_date: '2026-10-11',
            notes_enc: 'Siswa menyampaikan kendala belajar mandiri dan adaptasi jadwal.',
            follow_up: 'Evaluasi berkala per 2 minggu dan koordinasi dengan wali kelas.',
          },
        ],
      };

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: {
          data: mockCaseDetail,
        },
      } as any);

      const res = await counselingApi.getCase('case-1');

      expect(apiClient.get).toHaveBeenCalledWith('/counseling/cases/case-1');
      expect(res.data.id).toBe('case-1');
      expect(res.data.summary_enc).toContain('Catatan kasus terenkripsi');
      expect(res.data.sessions).toHaveLength(1);
      expect(res.data.sessions?.[0].follow_up).toContain('Evaluasi berkala');
    });

    it('membuat kasus konseling baru', async () => {
      const payload: CreateCounselingCasePayload = {
        student_id: 'std-102',
        category: 'Sosial',
        summary: 'Catatan awal kondisi siswa dari hasil asesmen BK.',
        status: 'aktif',
      };

      const mockCreated: CounselingCaseItem = {
        id: 'case-2',
        student_id: payload.student_id,
        counselor_id: 'user-bk-1',
        category: payload.category,
        status: 'aktif',
        summary_enc: payload.summary,
      };

      vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: {
          data: mockCreated,
        },
      } as any);

      const res = await counselingApi.createCase(payload);

      expect(apiClient.post).toHaveBeenCalledWith('/counseling/cases', payload);
      expect(res.data.id).toBe('case-2');
      expect(res.data.category).toBe('Sosial');
      expect(res.data.status).toBe('aktif');
    });

    it('memperbarui status dan ringkasan kasus konseling', async () => {
      const updatePayload: UpdateCounselingCasePayload = {
        status: 'selesai',
        summary: 'Kasus selesai ditangani. Siswa menunjukkan perkembangan positif.',
      };

      const mockUpdated: CounselingCaseItem = {
        id: 'case-1',
        student_id: 'std-101',
        counselor_id: 'user-bk-1',
        category: 'Pribadi',
        status: 'selesai',
        summary_enc: 'Kasus selesai ditangani. Siswa menunjukkan perkembangan positif.',
      };

      vi.spyOn(apiClient, 'put').mockResolvedValueOnce({
        data: {
          data: mockUpdated,
        },
      } as any);

      const res = await counselingApi.updateCase('case-1', updatePayload);

      expect(apiClient.put).toHaveBeenCalledWith('/counseling/cases/case-1', updatePayload);
      expect(res.data.status).toBe('selesai');
      expect(res.data.summary_enc).toContain('Kasus selesai ditangani');
    });
  });

  // =========================================================================
  // 2. SESI KONSELING (SESSIONS)
  // =========================================================================
  describe('2. Manajemen Sesi Konseling', () => {
    it('membuat sesi konseling baru untuk kasus terkait', async () => {
      const sessionPayload: CreateCounselingSessionPayload = {
        session_date: '2026-10-14',
        notes: 'Sesi mendalam membahas kedisiplinan dan agenda belajar mingguan.',
        follow_up: 'Cek silang presensi kelas bersama wali kelas.',
      };

      const mockSession: CounselingSessionItem = {
        id: 'session-10',
        case_id: 'case-1',
        session_date: sessionPayload.session_date,
        notes_enc: sessionPayload.notes,
        follow_up: sessionPayload.follow_up,
      };

      vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: {
          data: mockSession,
        },
      } as any);

      const res = await counselingApi.createSession('case-1', sessionPayload);

      expect(apiClient.post).toHaveBeenCalledWith('/counseling/cases/case-1/sessions', sessionPayload);
      expect(res.data.id).toBe('session-10');
      expect(res.data.follow_up).toBe('Cek silang presensi kelas bersama wali kelas.');
    });

    it('mengambil detail satu sesi konseling secara mandiri', async () => {
      const mockSession: CounselingSessionItem = {
        id: 'session-10',
        case_id: 'case-1',
        session_date: '2026-10-14',
        notes_enc: 'Catatan sesi terenkripsi yang didekripsi untuk audit BK.',
        follow_up: 'Lanjutkan pengamatan mingguan.',
      };

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: {
          data: mockSession,
        },
      } as any);

      const res = await counselingApi.getSession('session-10');

      expect(apiClient.get).toHaveBeenCalledWith('/counseling/sessions/session-10');
      expect(res.data.id).toBe('session-10');
      expect(res.data.notes_enc).toContain('Catatan sesi terenkripsi');
    });
  });

  // =========================================================================
  // 3. RUJUKAN KONSELING (REFERRALS)
  // =========================================================================
  describe('3. Rujukan Konseling dari Guru / Wali Kelas', () => {
    it('mengambil daftar rujukan konseling dengan filter status', async () => {
      const mockReferrals: CounselingReferralItem[] = [
        {
          id: 'ref-1',
          student_id: 'std-201',
          referred_by: 'teacher-2',
          reason: 'Siswa tampak murung dan sering tidak mengumpulkan tugas selama 2 pekan berturut-turut.',
          status: 'pending',
          student: {
            id: 'std-201',
            full_name: 'Budi Santoso',
            nis: '202402',
            classes: [{ id: 'cls-2', name: 'XI-IPS-2' }],
          },
          referrer: {
            id: 'user-t2',
            name: 'Wawan Setyawan',
            teacher: { id: 'teacher-2', full_name: 'Wawan Setyawan, S.Pd' },
          },
        },
      ];

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: {
          data: mockReferrals,
          meta: { page: 1, limit: 10, total: 1, totalPages: 1 },
        },
      } as any);

      const res = await counselingApi.getReferrals({ status: 'pending', page: 1, per_page: 10 });

      expect(apiClient.get).toHaveBeenCalledWith('/counseling/referrals', {
        params: { status: 'pending', page: 1, per_page: 10 },
      });
      expect(res.data).toHaveLength(1);
      expect(res.data[0].status).toBe('pending');
      expect(res.data[0].referrer?.teacher?.full_name).toBe('Wawan Setyawan, S.Pd');
    });

    it('membuat rujukan konseling baru dari guru ke BK', async () => {
      const payload: CreateCounselingReferralPayload = {
        student_id: 'std-201',
        reason: 'Penurunan prestasi drastis pasca ulangan tengah semester.',
      };

      const mockCreated: CounselingReferralItem = {
        id: 'ref-2',
        student_id: payload.student_id,
        referred_by: 'teacher-1',
        reason: payload.reason,
        status: 'pending',
      };

      vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: {
          data: mockCreated,
        },
      } as any);

      const res = await counselingApi.createReferral(payload);

      expect(apiClient.post).toHaveBeenCalledWith('/counseling/referrals', payload);
      expect(res.data.id).toBe('ref-2');
      expect(res.data.status).toBe('pending');
    });

    it('menerima rujukan konseling dan mengubah status menjadi diterima', async () => {
      const payload: UpdateCounselingReferralStatusPayload = {
        status: 'diterima',
      };

      const mockAccepted: CounselingReferralItem = {
        id: 'ref-1',
        student_id: 'std-201',
        referred_by: 'teacher-2',
        reason: 'Siswa tampak murung',
        status: 'diterima',
      };

      vi.spyOn(apiClient, 'patch').mockResolvedValueOnce({
        data: {
          data: mockAccepted,
        },
      } as any);

      const res = await counselingApi.updateReferralStatus('ref-1', payload);

      expect(apiClient.patch).toHaveBeenCalledWith('/counseling/referrals/ref-1/status', payload);
      expect(res.data.status).toBe('diterima');
    });

    it('menolak rujukan konseling dengan status ditolak', async () => {
      const payload: UpdateCounselingReferralStatusPayload = {
        status: 'ditolak',
      };

      const mockRejected: CounselingReferralItem = {
        id: 'ref-1',
        student_id: 'std-201',
        referred_by: 'teacher-2',
        reason: 'Siswa tampak murung',
        status: 'ditolak',
      };

      vi.spyOn(apiClient, 'patch').mockResolvedValueOnce({
        data: {
          data: mockRejected,
        },
      } as any);

      const res = await counselingApi.updateReferralStatus('ref-1', payload);

      expect(apiClient.patch).toHaveBeenCalledWith('/counseling/referrals/ref-1/status', payload);
      expect(res.data.status).toBe('ditolak');
    });
  });
});
