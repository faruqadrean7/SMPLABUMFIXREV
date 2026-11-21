import { describe, it, expect, vi, beforeEach } from 'vitest';
import { competitionsApi } from '@/features/student-affairs/competitions-api';
import { apiClient } from '@/lib/api/api-client';
import {
  CompetitionItem,
  CompetitionEntryItem,
  AchievementItem,
  CompetitionSummary,
  CreateCompetitionPayload,
  RegisterCompetitionEntryPayload,
  CreateAchievementPayload,
  VerifyAchievementPayload,
} from '@/features/student-affairs/types';

describe('ADM-STD-03 / BE-STD-03: Lomba & Prestasi Siswa SISTLABUM', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  const mockCompetition: CompetitionItem = {
    id: 'comp-101',
    school_id: 'sch-001',
    name: 'Olimpiade Sains Nasional Matematika 2026',
    category: 'akademik',
    level: 'nasional',
    organizer: 'Puspresnas Kemdikbudristek',
    held_at: '2026-11-15',
    registration_deadline: '2026-10-30',
    status: 'dibuka',
    description: 'Kompetisi olimpiade matematika tingkat SMA.',
    entries_count: 3,
  };

  const mockEntry: CompetitionEntryItem = {
    id: 'entry-201',
    competition_id: 'comp-101',
    student_id: 'std-301',
    status: 'terdaftar',
    notes: 'Delegasi bidang aljabar dan geometri.',
    student: {
      id: 'std-301',
      full_name: 'Ahmad Dahlan',
      nis: '20261001',
      nisn: '0019283746',
    },
  };

  const mockAchievement: AchievementItem = {
    id: 'ach-401',
    school_id: 'sch-001',
    student_id: 'std-301',
    title: 'Medali Emas OSN Matematika 2026',
    rank_level: 'Juara 1',
    achieved_at: '2026-11-15',
    points_awarded: 15,
    verification_status: 'verified',
    verified_at: '2026-11-16 10:00:00',
    student: {
      id: 'std-301',
      full_name: 'Ahmad Dahlan',
      nis: '20261001',
    },
  };

  const mockSummary: CompetitionSummary = {
    total_competitions: 12,
    active_competitions: 4,
    total_entries: 28,
    total_achievements: 9,
    verified_achievements: 7,
    pending_verification: 2,
    medals: {
      emas: 3,
      perak: 4,
      perunggu: 2,
      finalis: 5,
    },
  };

  describe('1. Katalog & Manajemen Kompetisi (Competitions)', () => {
    it('mengambil daftar katalog kompetisi dengan filter dan pagination', async () => {
      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: {
          data: [mockCompetition],
          meta: { page: 1, limit: 10, total: 1, totalPages: 1 },
        },
      } as any);

      const res = await competitionsApi.getCompetitions({
        level: 'nasional',
        status: 'dibuka',
        search: 'Olimpiade',
        page: 1,
        per_page: 10,
      });

      expect(apiClient.get).toHaveBeenCalledWith('/competitions', {
        params: {
          level: 'nasional',
          status: 'dibuka',
          search: 'Olimpiade',
          page: 1,
          per_page: 10,
        },
      });
      expect(res.data).toHaveLength(1);
      expect(res.data[0].id).toBe('comp-101');
      expect(res.data[0].level).toBe('nasional');
    });

    it('mengambil detail kompetisi berdasarkan ID', async () => {
      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: { data: mockCompetition },
      } as any);

      const res = await competitionsApi.getCompetition('comp-101');
      expect(apiClient.get).toHaveBeenCalledWith('/competitions/comp-101');
      expect(res.data.name).toBe('Olimpiade Sains Nasional Matematika 2026');
    });

    it('membuat kompetisi baru', async () => {
      const payload: CreateCompetitionPayload = {
        name: 'Festival Lomba Seni Siswa Nasional (FLS2N)',
        category: 'seni',
        level: 'provinsi',
        organizer: 'Dinas Pendidikan Jawa Timur',
        held_at: '2026-12-05',
        registration_deadline: '2026-11-20',
        status: 'direncanakan',
      };

      vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: { data: { id: 'comp-102', ...payload } },
      } as any);

      const res = await competitionsApi.createCompetition(payload);
      expect(apiClient.post).toHaveBeenCalledWith('/competitions', payload);
      expect(res.data.id).toBe('comp-102');
      expect(res.data.category).toBe('seni');
    });

    it('memperbarui data kompetisi', async () => {
      vi.spyOn(apiClient, 'put').mockResolvedValueOnce({
        data: { data: { ...mockCompetition, status: 'berjalan' } },
      } as any);

      const res = await competitionsApi.updateCompetition('comp-101', { status: 'berjalan' });
      expect(apiClient.put).toHaveBeenCalledWith('/competitions/comp-101', { status: 'berjalan' });
      expect(res.data.status).toBe('berjalan');
    });

    it('menghapus kompetisi', async () => {
      vi.spyOn(apiClient, 'delete').mockResolvedValueOnce({
        data: { data: { message: 'Kompetisi berhasil dihapus.' } },
      } as any);

      const res = await competitionsApi.deleteCompetition('comp-101');
      expect(apiClient.delete).toHaveBeenCalledWith('/competitions/comp-101');
      expect(res.data.message).toContain('berhasil dihapus');
    });
  });

  describe('2. Pendaftaran Delegasi / Kontingen Siswa (Competition Entries)', () => {
    it('mengambil daftar delegasi siswa untuk kompetisi tertentu', async () => {
      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: { data: [mockEntry] },
      } as any);

      const res = await competitionsApi.getEntries('comp-101');
      expect(apiClient.get).toHaveBeenCalledWith('/competitions/comp-101/entries');
      expect(res.data).toHaveLength(1);
      expect(res.data[0].student?.full_name).toBe('Ahmad Dahlan');
    });

    it('mendaftarkan siswa ke dalam kontingen kompetisi', async () => {
      const payload: RegisterCompetitionEntryPayload = {
        student_id: 'std-302',
        status: 'terdaftar',
        notes: 'Delegasi bidang aljabar',
      };

      vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: { data: { id: 'entry-202', ...payload } },
      } as any);

      const res = await competitionsApi.registerEntry('comp-101', payload);
      expect(apiClient.post).toHaveBeenCalledWith('/competitions/comp-101/entries', payload);
      expect(res.data.id).toBe('entry-202');
      expect(res.data.status).toBe('terdaftar');
    });

    it('memperbarui status atau hasil delegasi kontingen', async () => {
      vi.spyOn(apiClient, 'put').mockResolvedValueOnce({
        data: { data: { ...mockEntry, result: 'Juara 1', status: 'lolos' } },
      } as any);

      const res = await competitionsApi.updateEntry('entry-201', {
        result: 'Juara 1',
        status: 'lolos',
      });
      expect(apiClient.put).toHaveBeenCalledWith('/competition-entries/entry-201', {
        result: 'Juara 1',
        status: 'lolos',
      });
      expect(res.data.result).toBe('Juara 1');
      expect(res.data.status).toBe('lolos');
    });

    it('menghapus pendaftaran delegasi', async () => {
      vi.spyOn(apiClient, 'delete').mockResolvedValueOnce({
        data: { data: { message: 'Delegasi berhasil dihapus.' } },
      } as any);

      const res = await competitionsApi.deleteEntry('entry-201');
      expect(apiClient.delete).toHaveBeenCalledWith('/competition-entries/entry-201');
      expect(res.data.message).toContain('berhasil dihapus');
    });
  });

  describe('3. Pencatatan Prestasi & Galeri Piala (Achievements)', () => {
    it('mengambil daftar prestasi siswa dengan filter status verifikasi', async () => {
      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: {
          data: [mockAchievement],
          meta: { page: 1, limit: 10, total: 1, totalPages: 1 },
        },
      } as any);

      const res = await competitionsApi.getAchievements({
        verification_status: 'verified',
        rank_level: 'Juara 1',
        page: 1,
        per_page: 10,
      });

      expect(apiClient.get).toHaveBeenCalledWith('/achievements', {
        params: {
          verification_status: 'verified',
          rank_level: 'Juara 1',
          page: 1,
          per_page: 10,
        },
      });
      expect(res.data).toHaveLength(1);
      expect(res.data[0].rank_level).toBe('Juara 1');
      expect(res.data[0].verification_status).toBe('verified');
    });

    it('mencatat prestasi siswa dan mengaitkan sertifikat', async () => {
      const payload: CreateAchievementPayload = {
        student_id: 'std-301',
        title: 'Medali Perak Debat Bahasa Inggris Provinsi',
        rank_level: 'Juara 2',
        achieved_at: '2026-10-10',
        points_awarded: 10,
        certificate_file_id: 'file-cert-01',
        verification_status: 'pending',
      };

      vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: { data: { id: 'ach-402', ...payload } },
      } as any);

      const res = await competitionsApi.createAchievement(payload);
      expect(apiClient.post).toHaveBeenCalledWith('/achievements', payload);
      expect(res.data.id).toBe('ach-402');
      expect(res.data.points_awarded).toBe(10);
      expect(res.data.verification_status).toBe('pending');
    });

    it('memperbarui data prestasi siswa', async () => {
      vi.spyOn(apiClient, 'put').mockResolvedValueOnce({
        data: { data: { ...mockAchievement, points_awarded: 20 } },
      } as any);

      const res = await competitionsApi.updateAchievement('ach-401', { points_awarded: 20 });
      expect(apiClient.put).toHaveBeenCalledWith('/achievements/ach-401', { points_awarded: 20 });
      expect(res.data.points_awarded).toBe(20);
    });

    it('menghapus data prestasi dan menghapus tautan poin', async () => {
      vi.spyOn(apiClient, 'delete').mockResolvedValueOnce({
        data: { data: { message: 'Prestasi berhasil dihapus.' } },
      } as any);

      const res = await competitionsApi.deleteAchievement('ach-401');
      expect(apiClient.delete).toHaveBeenCalledWith('/achievements/ach-401');
      expect(res.data.message).toContain('berhasil dihapus');
    });
  });

  describe('4. Verifikasi Berkas Prestasi & Sinkronisasi Poin (Workflow Verification)', () => {
    it('memverifikasi berkas prestasi siswa (status verified) untuk memberikan poin karakter', async () => {
      const payload: VerifyAchievementPayload = {
        status: 'verified',
      };

      vi.spyOn(apiClient, 'patch').mockResolvedValueOnce({
        data: {
          data: {
            ...mockAchievement,
            verification_status: 'verified',
            verified_at: '2026-10-14 12:00:00',
          },
        },
      } as any);

      const res = await competitionsApi.verifyAchievement('ach-401', payload);
      expect(apiClient.patch).toHaveBeenCalledWith('/achievements/ach-401/verify', payload);
      expect(res.data.verification_status).toBe('verified');
      expect(res.data.verified_at).toBeDefined();
    });

    it('menolak verifikasi berkas prestasi siswa dengan catatan alasan penolakan', async () => {
      const payload: VerifyAchievementPayload = {
        status: 'rejected',
        rejection_reason: 'Scan sertifikat buram dan tidak memuat stempel resmi penyelenggara.',
      };

      vi.spyOn(apiClient, 'patch').mockResolvedValueOnce({
        data: {
          data: {
            ...mockAchievement,
            verification_status: 'rejected',
            rejection_reason: payload.rejection_reason,
            verified_at: '2026-10-14 12:05:00',
          },
        },
      } as any);

      const res = await competitionsApi.verifyAchievement('ach-401', payload);
      expect(apiClient.patch).toHaveBeenCalledWith('/achievements/ach-401/verify', payload);
      expect(res.data.verification_status).toBe('rejected');
      expect(res.data.rejection_reason).toContain('Scan sertifikat buram');
    });
  });

  describe('5. Ringkasan Eksekutif & Statistik Lomba (Summary Metrics)', () => {
    it('mengambil ringkasan metrik statistik kompetisi dan prestasi', async () => {
      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: { data: mockSummary },
      } as any);

      const res = await competitionsApi.getSummary();
      expect(apiClient.get).toHaveBeenCalledWith('/competitions/summary');
      expect(res.data.total_competitions).toBe(12);
      expect(res.data.total_achievements).toBe(9);
      expect(res.data.pending_verification).toBe(2);
      expect(res.data.medals.emas).toBe(3);
    });
  });

  describe('6. Portal Siswa & Wali Murid: Pengajuan Mandiri & Filter Kepemilikan (SIS-STD-01 / ORT-STD-01)', () => {
    it('siswa mengambil riwayat pengajuan prestasinya sendiri dengan filter only_my: true', async () => {
      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: {
          data: [
            {
              ...mockAchievement,
              verification_status: 'pending',
              notes: 'Pengajuan mandiri siswa',
            },
          ],
          meta: { page: 1, limit: 10, total: 1, totalPages: 1 },
        },
      } as any);

      const res = await competitionsApi.getAchievements({
        student_id: 'std-301',
        only_my: true,
      });

      expect(apiClient.get).toHaveBeenCalledWith('/achievements', {
        params: {
          student_id: 'std-301',
          only_my: true,
        },
      });
      expect(res.data).toHaveLength(1);
      expect(res.data[0].verification_status).toBe('pending');
    });

    it('wali murid mengambil daftar prestasi ananda berdasarkan student_id yang sah', async () => {
      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: {
          data: [mockAchievement],
          meta: { page: 1, limit: 10, total: 1, totalPages: 1 },
        },
      } as any);

      const res = await competitionsApi.getAchievements({
        student_id: 'std-301',
      });

      expect(apiClient.get).toHaveBeenCalledWith('/achievements', {
        params: {
          student_id: 'std-301',
        },
      });
      expect(res.data).toHaveLength(1);
      expect(res.data[0].title).toBe('Medali Emas OSN Matematika 2026');
    });

    it('siswa mengajukan prestasi mandiri dengan status pending', async () => {
      const payload: CreateAchievementPayload = {
        student_id: 'std-301',
        title: 'Juara 1 Lomba Cipta Puisi Nasional 2026',
        rank_level: 'Juara 1',
        achieved_at: '2026-10-14',
        notes: 'Tingkat nasional kemdikbud',
      };

      vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: {
          data: {
            id: 'ach-501',
            ...payload,
            verification_status: 'pending',
            points_awarded: 0,
          },
        },
      } as any);

      const res = await competitionsApi.createAchievement(payload);
      expect(apiClient.post).toHaveBeenCalledWith('/achievements', payload);
      expect(res.data.id).toBe('ach-501');
      expect(res.data.verification_status).toBe('pending');
    });

    it('wali murid mengajukan prestasi atas nama ananda yang sah', async () => {
      const payload: CreateAchievementPayload = {
        student_id: 'std-301',
        title: 'Juara 2 Robotika Pelajar se-Jawa Timur',
        rank_level: 'Juara 2',
        achieved_at: '2026-10-12',
        notes: 'Diajukan oleh wali murid',
      };

      vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: {
          data: {
            id: 'ach-502',
            ...payload,
            verification_status: 'pending',
          },
        },
      } as any);

      const res = await competitionsApi.createAchievement(payload);
      expect(apiClient.post).toHaveBeenCalledWith('/achievements', payload);
      expect(res.data.id).toBe('ach-502');
      expect(res.data.notes).toContain('wali murid');
    });

    it('siswa atau wali murid membatalkan pengajuan prestasi pending miliknya', async () => {
      vi.spyOn(apiClient, 'delete').mockResolvedValueOnce({
        data: { data: { message: 'Data prestasi berhasil dihapus.' } },
      } as any);

      const res = await competitionsApi.deleteAchievement('ach-501');
      expect(apiClient.delete).toHaveBeenCalledWith('/achievements/ach-501');
      expect(res.data.message).toContain('berhasil dihapus');
    });
  });
});
