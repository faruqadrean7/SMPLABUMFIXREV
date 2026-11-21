import { describe, it, expect, vi, beforeEach } from 'vitest';
import { earlyWarningApi } from '@/features/counseling/early-warning-api';
import { apiClient } from '@/lib/api/api-client';
import {
  EarlyWarningItem,
  EarlyWarningSummary,
  EvaluateEarlyWarningsPayload,
  HandleEarlyWarningPayload,
} from '@/features/counseling/early-warning-types';

describe('ADM-BK-02 / BE-BK-02: Deteksi Dini Siswa Berisiko (Early Warning System) SISTLABUM', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  describe('1. Pengambilan Daftar & Ringkasan Deteksi Dini', () => {
    it('mengambil daftar alert deteksi dini dengan filter parameter yang tepat', async () => {
      const mockWarnings: EarlyWarningItem[] = [
        {
          id: 'ew-1',
          student_id: 'std-101',
          rule_code: 'ATTENDANCE_LOW',
          severity: 'tinggi',
          status: 'open',
          data: {
            rule: 'Persentase kehadiran di bawah ambang atau alpa tinggi',
            alpa_count: 5,
            attendance_percent: 60,
          },
          student: {
            id: 'std-101',
            full_name: 'Ahmad Faiz Pratama',
            nis: '20261001',
            classes: [{ id: 'cls-1', name: '10-A' }],
          },
        },
      ];

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: {
          data: mockWarnings,
          meta: { page: 1, limit: 15, total: 1, totalPages: 1 },
        },
      } as any);

      const res = await earlyWarningApi.getWarnings({
        status: 'open',
        severity: 'tinggi',
        rule_code: 'ATTENDANCE_LOW',
        search: 'Faiz',
        page: 1,
        per_page: 15,
      });

      expect(apiClient.get).toHaveBeenCalledWith('/early-warnings', {
        params: {
          status: 'open',
          severity: 'tinggi',
          rule_code: 'ATTENDANCE_LOW',
          search: 'Faiz',
          page: 1,
          per_page: 15,
        },
      });

      expect(res.data).toHaveLength(1);
      expect(res.data[0].id).toBe('ew-1');
      expect(res.data[0].student?.full_name).toBe('Ahmad Faiz Pratama');
      expect(res.data[0].severity).toBe('tinggi');
    });

    it('mengambil ringkasan metrik deteksi dini untuk dashboard cards', async () => {
      const mockSummary: EarlyWarningSummary = {
        open_count: 5,
        in_progress_count: 3,
        resolved_count: 12,
        dismissed_count: 1,
        high_severity_count: 2,
        total_active: 8,
      };

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: {
          data: mockSummary,
        },
      } as any);

      const res = await earlyWarningApi.getSummary();

      expect(apiClient.get).toHaveBeenCalledWith('/early-warnings/summary');
      expect(res.data.open_count).toBe(5);
      expect(res.data.total_active).toBe(8);
      expect(res.data.high_severity_count).toBe(2);
    });

    it('mengambil detail satu alert deteksi dini secara lengkap', async () => {
      const mockDetail: EarlyWarningItem = {
        id: 'ew-2',
        student_id: 'std-102',
        rule_code: 'GRADE_DROP',
        severity: 'sedang',
        status: 'in_progress',
        data: {
          rule: 'Penurunan nilai akademik drastis',
          previous_average: 85,
          recent_average: 68,
          drop_points: 17,
          threshold: 15,
          handling_notes: 'Dijadwalkan bimbingan belajar tambahan.',
        },
        student: {
          id: 'std-102',
          full_name: 'Citra Lestari',
          nis: '20261002',
          classes: [{ id: 'cls-1', name: '10-A' }],
        },
        handler: {
          id: 'user-counselor-1',
          name: 'Guru BK',
          username: 'guru_bk',
        },
      };

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: {
          data: mockDetail,
        },
      } as any);

      const res = await earlyWarningApi.getWarning('ew-2');

      expect(apiClient.get).toHaveBeenCalledWith('/early-warnings/ew-2');
      expect(res.data.id).toBe('ew-2');
      expect(res.data.rule_code).toBe('GRADE_DROP');
      expect(res.data.data.drop_points).toBe(17);
      expect(res.data.handler?.username).toBe('guru_bk');
    });
  });

  describe('2. Eksekusi Evaluasi Otomatis & Penanganan Alert', () => {
    it('menjalankan evaluasi aturan deteksi dini dengan kustomisasi threshold', async () => {
      const payload: EvaluateEarlyWarningsPayload = {
        attendance_threshold_percent: 75,
        min_absent_days: 3,
        grade_drop_threshold: 15,
        violation_points_threshold: 20,
      };

      const mockEvalResult = {
        students_evaluated: 45,
        created_alerts: 3,
        updated_alerts: 1,
        message: 'Evaluasi deteksi dini selesai. 3 peringatan baru dibuat, 1 diperbarui.',
      };

      vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: {
          data: mockEvalResult,
        },
      } as any);

      const res = await earlyWarningApi.evaluateRules(payload);

      expect(apiClient.post).toHaveBeenCalledWith('/early-warnings/evaluate', payload);
      expect(res.data.created_alerts).toBe(3);
      expect(res.data.students_evaluated).toBe(45);
    });

    it('menindaklanjuti status alert dengan catatan rencana pembinaan', async () => {
      const payload: HandleEarlyWarningPayload = {
        status: 'resolved',
        notes: 'Pertemuan dengan orang tua siswa dan guru mata pelajaran selesai; kendala terselesaikan.',
      };

      const mockUpdatedWarning: EarlyWarningItem = {
        id: 'ew-1',
        student_id: 'std-101',
        rule_code: 'ATTENDANCE_LOW',
        severity: 'tinggi',
        status: 'resolved',
        data: {
          alpa_count: 5,
          handling_notes: payload.notes,
        },
      };

      vi.spyOn(apiClient, 'patch').mockResolvedValueOnce({
        data: {
          data: mockUpdatedWarning,
        },
      } as any);

      const res = await earlyWarningApi.handleWarning('ew-1', payload);

      expect(apiClient.patch).toHaveBeenCalledWith('/early-warnings/ew-1/handle', payload);
      expect(res.data.status).toBe('resolved');
      expect(res.data.data.handling_notes).toContain('Pertemuan dengan orang tua');
    });
  });
});
