import { describe, it, expect, vi, beforeEach } from 'vitest';
import { academicYearSchema } from '@/features/master/academic-year-dialog';
import { academicYearsApi } from '@/features/master/academic-years-api';
import { apiClient } from '@/lib/api/api-client';

describe('ADM-MST-01 Academic Year Logic & Schema', () => {
  describe('academicYearSchema validation', () => {
    it('passes with valid YYYY/YYYY format and valid dates', () => {
      const validData = {
        name: '2025/2026',
        start_date: '2025-07-15',
        end_date: '2026-06-20',
        is_active: false,
      };

      const result = academicYearSchema.safeParse(validData);
      expect(result.success).toBe(true);
    });

    it('fails when name format is not YYYY/YYYY', () => {
      const invalidFormats = ['2025', '2025-2026', 'Tahun 2025/2026', '25/26', '2025/2025/2026'];

      invalidFormats.forEach((name) => {
        const result = academicYearSchema.safeParse({
          name,
          start_date: '2025-07-15',
          end_date: '2026-06-20',
        });
        expect(result.success).toBe(false);
        if (!result.success) {
          expect(result.error.errors[0].message).toContain('Format nama harus berupa tahun');
        }
      });
    });

    it('fails when end_date is before or equal to start_date', () => {
      const invalidDates = {
        name: '2025/2026',
        start_date: '2025-07-15',
        end_date: '2025-07-10', // earlier than start_date
      };

      const result = academicYearSchema.safeParse(invalidDates);
      expect(result.success).toBe(false);
      if (!result.success) {
        expect(result.error.errors[0].message).toBe('Tanggal selesai harus setelah tanggal mulai');
      }
    });
  });

  describe('academicYearsApi client methods', () => {
    beforeEach(() => {
      vi.clearAllMocks();
    });

    it('list requests /academic-years with proper params', async () => {
      const getSpy = vi.spyOn(apiClient, 'get').mockResolvedValue({
        data: {
          success: true,
          data: [],
          meta: { page: 2, limit: 20, total: 0, totalPages: 0 },
        },
      } as any);

      await academicYearsApi.list({ page: 2, limit: 20, q: '2025' });

      expect(getSpy).toHaveBeenCalledWith('/academic-years', {
        params: {
          page: 2,
          limit: 20,
          q: '2025',
        },
      });
    });

    it('activate sends PATCH to /academic-years/{id}/activate', async () => {
      const patchSpy = vi.spyOn(apiClient, 'patch').mockResolvedValue({
        data: {
          success: true,
          data: { id: 'ay-1', name: '2025/2026', is_active: true },
        },
      } as any);

      const result = await academicYearsApi.activate('ay-1');

      expect(patchSpy).toHaveBeenCalledWith('/academic-years/ay-1/activate');
      expect(result.id).toBe('ay-1');
      expect(result.is_active).toBe(true);
    });

    it('getActive requests /academic-years/active', async () => {
      const getSpy = vi.spyOn(apiClient, 'get').mockResolvedValue({
        data: {
          success: true,
          data: {
            academicYear: { id: 'ay-1', name: '2024/2025', is_active: true },
            semester: { id: 'sem-1', name: 'Ganjil', is_active: true },
          },
        },
      } as any);

      const result = await academicYearsApi.getActive();

      expect(getSpy).toHaveBeenCalledWith('/academic-years/active');
      expect(result.academicYear?.name).toBe('2024/2025');
    });
  });
});
