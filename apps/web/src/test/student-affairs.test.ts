import { describe, it, expect, vi, beforeEach } from 'vitest';
import { pointsApi } from '@/features/student-affairs/points-api';
import { apiClient } from '@/lib/api/api-client';
import {
  PointType,
  PointThreshold,
  StudentPointItem,
  StudentPointsSummary,
  RecordStudentPointPayload,
  RecordStudentPointResult,
} from '@/features/student-affairs/types';

describe('ADM-STD-01 / BE-STD-01: Kesiswaan & Disiplin - Poin Pelanggaran & Prestasi Siswa', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  // =========================================================================
  // 1. MASTER JENIS POIN (POINT TYPES)
  // =========================================================================
  describe('1. Master Jenis Poin Pelanggaran & Prestasi', () => {
    it('mengambil daftar jenis poin dengan filter kind dan search query', async () => {
      const mockResponse = {
        data: {
          data: [
            {
              id: 'pt-1',
              kind: 'pelanggaran',
              name: 'Terlambat Masuk Sekolah (>15 menit)',
              points: 5,
              severity: 'rendah',
            },
            {
              id: 'pt-2',
              kind: 'pelanggaran',
              name: 'Merokok di Lingkungan Sekolah',
              points: 50,
              severity: 'tinggi',
            },
          ] as PointType[],
          meta: { page: 1, limit: 10, total: 2, totalPages: 1 },
        },
      };

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce(mockResponse as any);

      const res = await pointsApi.getPointTypes({
        kind: 'pelanggaran',
        search: 'Terlambat',
        page: 1,
        per_page: 10,
      });

      expect(apiClient.get).toHaveBeenCalledWith('/point-types', {
        params: {
          kind: 'pelanggaran',
          search: 'Terlambat',
          page: 1,
          per_page: 10,
        },
      });
      expect(res.data).toHaveLength(2);
      expect(res.data[0].name).toContain('Terlambat');
      expect(res.data[0].points).toBe(5);
    });

    it('mengambil detail satu jenis poin berdasarkan ID', async () => {
      const mockItem: PointType = {
        id: 'pt-juara-1',
        kind: 'prestasi',
        name: 'Juara 1 Lomba OSN Tingkat Provinsi',
        points: 40,
        severity: 'tinggi',
      };

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: { data: mockItem },
      } as any);

      const res = await pointsApi.getPointType('pt-juara-1');
      expect(apiClient.get).toHaveBeenCalledWith('/point-types/pt-juara-1');
      expect(res.data.id).toBe('pt-juara-1');
      expect(res.data.kind).toBe('prestasi');
      expect(res.data.points).toBe(40);
    });

    it('membuat jenis poin baru (pelanggaran/prestasi)', async () => {
      const payload = {
        kind: 'pelanggaran' as const,
        name: 'Membawa Senjata Tajam / Benda Berbahaya',
        points: 100,
        severity: 'berat' as const,
      };

      vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: {
          data: {
            id: 'pt-new-1',
            ...payload,
          },
        },
      } as any);

      const res = await pointsApi.createPointType(payload);
      expect(apiClient.post).toHaveBeenCalledWith('/point-types', payload);
      expect(res.data.id).toBe('pt-new-1');
      expect(res.data.severity).toBe('berat');
    });

    it('memperbarui jenis poin yang sudah ada', async () => {
      const updateData = {
        name: 'Terlambat Masuk Sekolah (>20 menit)',
        points: 10,
        severity: 'sedang' as const,
      };

      vi.spyOn(apiClient, 'put').mockResolvedValueOnce({
        data: {
          data: {
            id: 'pt-1',
            kind: 'pelanggaran',
            ...updateData,
          },
        },
      } as any);

      const res = await pointsApi.updatePointType('pt-1', updateData);
      expect(apiClient.put).toHaveBeenCalledWith('/point-types/pt-1', updateData);
      expect(res.data.points).toBe(10);
      expect(res.data.severity).toBe('sedang');
    });

    it('menghapus jenis poin', async () => {
      vi.spyOn(apiClient, 'delete').mockResolvedValueOnce({
        data: { data: { message: 'Jenis poin berhasil dihapus' } },
      } as any);

      const res = await pointsApi.deletePointType('pt-1');
      expect(apiClient.delete).toHaveBeenCalledWith('/point-types/pt-1');
      expect(res.data.message).toBe('Jenis poin berhasil dihapus');
    });
  });

  // =========================================================================
  // 2. AMBANG BATAS SANKSI DISIPLIN (POINT THRESHOLDS)
  // =========================================================================
  describe('2. Ambang Batas Sanksi Disiplin (Thresholds)', () => {
    it('mengambil daftar semua ambang batas sanksi terurut', async () => {
      const mockThresholds: PointThreshold[] = [
        {
          id: 'th-1',
          min_points: 25,
          action: 'Peringatan Lisan & Konseling Wali Kelas',
          notify_roles: ['homeroom_teacher'],
        },
        {
          id: 'th-2',
          min_points: 50,
          action: 'Surat Peringatan I (SP-1) & Panggilan Orang Tua',
          notify_roles: ['homeroom_teacher', 'counselor'],
        },
        {
          id: 'th-3',
          min_points: 100,
          action: 'Skorsing 3 Hari & Rekomendasi Konferensi Kasus',
          notify_roles: ['homeroom_teacher', 'counselor', 'headmaster'],
        },
      ];

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: { data: mockThresholds },
      } as any);

      const res = await pointsApi.getPointThresholds();
      expect(apiClient.get).toHaveBeenCalledWith('/point-thresholds');
      expect(res.data).toHaveLength(3);
      expect(res.data[0].min_points).toBe(25);
      expect(res.data[1].notify_roles).toContain('counselor');
    });

    it('membuat aturan ambang batas sanksi baru', async () => {
      const payload = {
        min_points: 75,
        action: 'Surat Peringatan II (SP-2) & Perjanjian Tertulis',
        notify_roles: ['homeroom_teacher', 'counselor', 'headmaster'],
      };

      vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: {
          data: {
            id: 'th-new',
            ...payload,
          },
        },
      } as any);

      const res = await pointsApi.createPointThreshold(payload);
      expect(apiClient.post).toHaveBeenCalledWith('/point-thresholds', payload);
      expect(res.data.min_points).toBe(75);
      expect(res.data.action).toContain('SP-2');
    });

    it('memperbarui ambang batas sanksi', async () => {
      const updateData = {
        action: 'Peringatan Lisan & Bimbingan Khusus Konselor',
        notify_roles: ['homeroom_teacher', 'counselor'],
      };

      vi.spyOn(apiClient, 'put').mockResolvedValueOnce({
        data: {
          data: {
            id: 'th-1',
            min_points: 25,
            ...updateData,
          },
        },
      } as any);

      const res = await pointsApi.updatePointThreshold('th-1', updateData);
      expect(apiClient.put).toHaveBeenCalledWith('/point-thresholds/th-1', updateData);
      expect(res.data.action).toBe('Peringatan Lisan & Bimbingan Khusus Konselor');
    });

    it('menghapus aturan ambang batas sanksi', async () => {
      vi.spyOn(apiClient, 'delete').mockResolvedValueOnce({
        data: { data: { message: 'Ambang sanksi berhasil dihapus' } },
      } as any);

      const res = await pointsApi.deletePointThreshold('th-1');
      expect(apiClient.delete).toHaveBeenCalledWith('/point-thresholds/th-1');
      expect(res.data.message).toBe('Ambang sanksi berhasil dihapus');
    });
  });

  // =========================================================================
  // 3. PENCATATAN & RIWAYAT POIN SISWA
  // =========================================================================
  describe('3. Pencatatan & Riwayat Poin Siswa (BE-STD-01 & ADM-STD-01)', () => {
    it('mengambil riwayat poin dengan filter lengkap (student_id, class_id, kind, search)', async () => {
      const mockPoints: StudentPointItem[] = [
        {
          id: 'sp-1',
          student_id: 'std-101',
          point_type_id: 'pt-1',
          recorded_by: 'user-teacher-1',
          point_date: '2026-10-13',
          notes: 'Terlambat upacara hari Senin',
          student: {
            id: 'std-101',
            full_name: 'Ahmad Dahlan',
            nis: '2026001',
            classes: [{ id: 'cls-10a', name: 'X-A' }],
          },
          point_type: {
            id: 'pt-1',
            kind: 'pelanggaran',
            name: 'Terlambat Masuk Sekolah',
            points: 5,
            severity: 'rendah',
          },
          recorder: {
            id: 'user-teacher-1',
            name: 'Budi Santoso, S.Pd',
          },
        },
      ];

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: {
          data: mockPoints,
          meta: { page: 1, limit: 15, total: 1, totalPages: 1 },
        },
      } as any);

      const res = await pointsApi.getStudentPoints({
        class_id: 'cls-10a',
        kind: 'pelanggaran',
        search: 'Ahmad',
        page: 1,
        per_page: 15,
      });

      expect(apiClient.get).toHaveBeenCalledWith('/student-points', {
        params: {
          class_id: 'cls-10a',
          kind: 'pelanggaran',
          search: 'Ahmad',
          page: 1,
          per_page: 15,
        },
      });
      expect(res.data).toHaveLength(1);
      expect(res.data[0].student?.full_name).toBe('Ahmad Dahlan');
      expect(res.data[0].point_type?.points).toBe(5);
    });

    it('mencatat poin pelanggaran siswa dan mengembalikan evaluasi ambang sanksi terpicu', async () => {
      const payload: RecordStudentPointPayload = {
        student_id: 'std-101',
        point_type_id: 'pt-rokok',
        point_date: '2026-10-13',
        notes: 'Ditemukan merokok di kantin belakang sekolah',
      };

      const mockResult: RecordStudentPointResult = {
        point: {
          id: 'sp-new-1',
          student_id: 'std-101',
          point_type_id: 'pt-rokok',
          recorded_by: 'user-guru-bk',
          point_date: '2026-10-13',
          notes: 'Ditemukan merokok di kantin belakang sekolah',
        },
        triggered_threshold: {
          id: 'th-50',
          min_points: 50,
          action: 'Surat Peringatan I (SP-1) & Panggilan Orang Tua',
          notify_roles: ['homeroom_teacher', 'counselor'],
        },
        cumulative_violation_points: 55,
      };

      vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: { data: mockResult },
      } as any);

      const res = await pointsApi.recordStudentPoint(payload);
      expect(apiClient.post).toHaveBeenCalledWith('/student-points', payload);
      expect(res.data.point.id).toBe('sp-new-1');
      expect(res.data.cumulative_violation_points).toBe(55);
      expect(res.data.triggered_threshold).not.toBeNull();
      expect(res.data.triggered_threshold?.action).toContain('SP-1');
    });

    it('mencatat poin prestasi siswa tanpa memicu ambang sanksi pelanggaran', async () => {
      const payload: RecordStudentPointPayload = {
        student_id: 'std-102',
        point_type_id: 'pt-juara',
        point_date: '2026-10-12',
        notes: 'Juara 2 Debat Bahasa Inggris Provinsi',
      };

      const mockResult: RecordStudentPointResult = {
        point: {
          id: 'sp-prestasi-1',
          student_id: 'std-102',
          point_type_id: 'pt-juara',
          recorded_by: 'user-guru-1',
          point_date: '2026-10-12',
          notes: 'Juara 2 Debat Bahasa Inggris Provinsi',
        },
        triggered_threshold: null,
        cumulative_violation_points: 0,
      };

      vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: { data: mockResult },
      } as any);

      const res = await pointsApi.recordStudentPoint(payload);
      expect(res.data.triggered_threshold).toBeNull();
      expect(res.data.cumulative_violation_points).toBe(0);
    });

    it('menghapus catatan poin siswa', async () => {
      vi.spyOn(apiClient, 'delete').mockResolvedValueOnce({
        data: { data: { message: 'Catatan poin berhasil dihapus' } },
      } as any);

      const res = await pointsApi.deleteStudentPoint('sp-1');
      expect(apiClient.delete).toHaveBeenCalledWith('/student-points/sp-1');
      expect(res.data.message).toBe('Catatan poin berhasil dihapus');
    });

    it('mengambil ringkasan akumulasi poin per siswa (net points, sanksi aktif, riwayat)', async () => {
      const mockSummary: StudentPointsSummary = {
        student: {
          id: 'std-101',
          full_name: 'Ahmad Dahlan',
          nis: '2026001',
          nisn: '0051234567',
        },
        total_violation_points: 60,
        total_achievement_points: 25,
        net_points: 35,
        violation_count: 3,
        achievement_count: 1,
        active_sanction: {
          id: 'th-50',
          min_points: 50,
          action: 'Surat Peringatan I (SP-1) & Panggilan Orang Tua',
          notify_roles: ['homeroom_teacher', 'counselor'],
        },
        reached_thresholds: [
          {
            id: 'th-25',
            min_points: 25,
            action: 'Peringatan Lisan & Konseling Wali Kelas',
            notify_roles: ['homeroom_teacher'],
          },
          {
            id: 'th-50',
            min_points: 50,
            action: 'Surat Peringatan I (SP-1) & Panggilan Orang Tua',
            notify_roles: ['homeroom_teacher', 'counselor'],
          },
        ],
        recent_points: [],
      };

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: { data: mockSummary },
      } as any);

      const res = await pointsApi.getStudentPointsSummary('std-101');
      expect(apiClient.get).toHaveBeenCalledWith('/students/std-101/points/summary');
      expect(res.data.student.full_name).toBe('Ahmad Dahlan');
      expect(res.data.total_violation_points).toBe(60);
      expect(res.data.total_achievement_points).toBe(25);
      expect(res.data.net_points).toBe(35);
      expect(res.data.active_sanction?.min_points).toBe(50);
      expect(res.data.reached_thresholds).toHaveLength(2);
    });
  });
});
