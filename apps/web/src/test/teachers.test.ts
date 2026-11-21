import { describe, it, expect, vi, beforeEach } from 'vitest';
import { teacherSchema } from '@/features/master/teacher-dialog';
import { teachersApi } from '@/features/master/teachers-api';
import { apiClient } from '@/lib/api/api-client';

describe('ADM-MST-03 Teacher Schema Validation & API Client', () => {
  describe('teacherSchema validation', () => {
    it('passes with valid teacher input', () => {
      const validTeacher = {
        full_name: 'Dra. Sri Wahyuni, M.Pd.',
        nip: '197501012000032001',
        nuptk: '1234567890123456',
        position: 'Guru Bahasa Indonesia',
        status: 'tetap' as const,
      };

      const result = teacherSchema.safeParse(validTeacher);
      expect(result.success).toBe(true);
    });

    it('passes with empty optional nip and nuptk', () => {
      const result = teacherSchema.safeParse({
        full_name: 'Budi Santoso, S.Pd.',
        nip: '',
        nuptk: '',
        status: 'honorer' as const,
      });

      expect(result.success).toBe(true);
    });

    it('fails when full_name is empty', () => {
      const result = teacherSchema.safeParse({
        full_name: '',
        status: 'tetap' as const,
      });

      expect(result.success).toBe(false);
      if (!result.success) {
        expect(result.error.errors[0].message).toBe('Nama lengkap guru wajib diisi');
      }
    });

    it('fails when status is invalid', () => {
      const result = teacherSchema.safeParse({
        full_name: 'Budi Santoso',
        status: 'magang' as any,
      });

      expect(result.success).toBe(false);
    });
  });

  describe('teachersApi methods', () => {
    beforeEach(() => {
      vi.clearAllMocks();
    });

    it('list requests /teachers with params', async () => {
      const getSpy = vi.spyOn(apiClient, 'get').mockResolvedValue({
        data: {
          success: true,
          data: [],
          meta: { page: 1, limit: 20, total: 0, totalPages: 0 },
        },
      } as any);

      await teachersApi.list({ page: 2, limit: 10, q: 'Wahyuni', status: 'tetap' });

      expect(getSpy).toHaveBeenCalledWith('/teachers', {
        params: {
          page: 2,
          limit: 10,
          q: 'Wahyuni',
          status: 'tetap',
        },
      });
    });

    it('delete requests DELETE /teachers/{id}', async () => {
      const delSpy = vi.spyOn(apiClient, 'delete').mockResolvedValue({
        data: { success: true },
      } as any);

      await teachersApi.delete('tch-1');

      expect(delSpy).toHaveBeenCalledWith('/teachers/tch-1');
    });

    it('listSalarySlips requests GET /teachers/{id}/salary-slips', async () => {
      const getSpy = vi.spyOn(apiClient, 'get').mockResolvedValue({
        data: { success: true, data: [{ id: 'slip-1', period_month: 10, period_year: 2026 }] },
      } as any);

      const res = await teachersApi.listSalarySlips('tch-123');

      expect(getSpy).toHaveBeenCalledWith('/teachers/tch-123/salary-slips');
      expect(res).toHaveLength(1);
    });

    it('createSalarySlip requests POST /teachers/{id}/salary-slips with multipart form data', async () => {
      const postSpy = vi.spyOn(apiClient, 'post').mockResolvedValue({
        data: { success: true, data: { id: 'slip-2', period_month: 10, period_year: 2026 } },
      } as any);

      const fakeFile = new File(['dummy-pdf'], 'slip.pdf', { type: 'application/pdf' });
      await teachersApi.createSalarySlip('tch-123', {
        period_month: 10,
        period_year: 2026,
        basic_salary: 4000000,
        allowances: 1000000,
        deductions: 200000,
        net_salary: 4800000,
        file: fakeFile,
      });

      expect(postSpy).toHaveBeenCalledWith(
        '/teachers/tch-123/salary-slips',
        expect.any(FormData),
        expect.objectContaining({
          headers: { 'Content-Type': 'multipart/form-data' },
        })
      );
    });

    it('deleteSalarySlip requests DELETE /teachers/{id}/salary-slips/{slipId}', async () => {
      const delSpy = vi.spyOn(apiClient, 'delete').mockResolvedValue({
        data: { success: true },
      } as any);

      await teachersApi.deleteSalarySlip('tch-123', 'slip-999');

      expect(delSpy).toHaveBeenCalledWith('/teachers/tch-123/salary-slips/slip-999');
    });

    it('getTeachingRecap requests GET /teachers/{id}/teaching-recap', async () => {
      const getSpy = vi.spyOn(apiClient, 'get').mockResolvedValue({
        data: {
          success: true,
          data: {
            total_hours: 26,
            target_hours: 24,
            is_target_met: true,
            assignments: [],
          },
        },
      } as any);

      const res = await teachersApi.getTeachingRecap('tch-123');

      expect(getSpy).toHaveBeenCalledWith('/teachers/tch-123/teaching-recap');
      expect(res.total_hours).toBe(26);
      expect(res.is_target_met).toBe(true);
    });

    it('getMySalarySlips and getMyTeachingRecap request self-service endpoints', async () => {
      const getSpy = vi.spyOn(apiClient, 'get').mockResolvedValue({
        data: { success: true, data: [] },
      } as any);

      await teachersApi.getMySalarySlips();
      expect(getSpy).toHaveBeenCalledWith('/teacher/me/salary-slips');

      await teachersApi.getMyTeachingRecap();
      expect(getSpy).toHaveBeenCalledWith('/teacher/me/teaching-recap');
    });
  });
});
