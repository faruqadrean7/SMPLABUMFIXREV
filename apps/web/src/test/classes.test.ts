import { describe, it, expect, vi, beforeEach } from 'vitest';
import { classSchema } from '@/features/master/class-dialog';
import { classesApi } from '@/features/master/classes-api';
import { apiClient } from '@/lib/api/api-client';

describe('ADM-MST-05 Class Schema Validation & API Client', () => {
  describe('classSchema validation', () => {
    it('passes with valid class data within standard 36 capacity', () => {
      const validClass = {
        academic_year_id: 'ay-2024',
        grade_level_id: 'gl-7',
        major_id: '',
        homeroom_teacher_id: 'tch-1',
        name: 'VII-A',
        capacity: 36,
      };

      const result = classSchema.safeParse(validClass);
      expect(result.success).toBe(true);
    });

    it('fails when capacity is less than 1 or greater than 100', () => {
      const invalidLow = {
        academic_year_id: 'ay-2024',
        grade_level_id: 'gl-7',
        name: 'VII-A',
        capacity: 0,
      };
      const resultLow = classSchema.safeParse(invalidLow);
      expect(resultLow.success).toBe(false);

      const invalidHigh = {
        academic_year_id: 'ay-2024',
        grade_level_id: 'gl-7',
        name: 'VII-A',
        capacity: 101,
      };
      const resultHigh = classSchema.safeParse(invalidHigh);
      expect(resultHigh.success).toBe(false);
    });

    it('fails when name or academic_year_id is missing', () => {
      const result = classSchema.safeParse({
        academic_year_id: '',
        grade_level_id: 'gl-7',
        name: '',
        capacity: 36,
      });

      expect(result.success).toBe(false);
    });
  });

  describe('classesApi methods', () => {
    beforeEach(() => {
      vi.clearAllMocks();
    });

    it('list requests /classes with filters', async () => {
      const getSpy = vi.spyOn(apiClient, 'get').mockResolvedValue({
        data: {
          success: true,
          data: [],
          meta: { page: 1, limit: 20, total: 0, totalPages: 0 },
        },
      } as any);

      await classesApi.list({
        academic_year_id: 'ay-1',
        grade_level_id: 'gl-1',
        q: 'VII',
      });

      expect(getSpy).toHaveBeenCalledWith('/classes', {
        params: {
          page: 1,
          limit: 20,
          q: 'VII',
          academic_year_id: 'ay-1',
          grade_level_id: 'gl-1',
          major_id: undefined,
        },
      });
    });

    it('listStudents requests /classes/{id}/students', async () => {
      const getSpy = vi.spyOn(apiClient, 'get').mockResolvedValue({
        data: {
          success: true,
          data: [{ id: 'std-1', full_name: 'Budi' }],
        },
      } as any);

      const res = await classesApi.listStudents('cls-1');
      expect(getSpy).toHaveBeenCalledWith('/classes/cls-1/students', {
        params: { active_only: true },
      });
      expect(res).toHaveLength(1);
    });

    it('removeStudent requests DELETE /classes/{id}/students/{studentId}', async () => {
      const delSpy = vi.spyOn(apiClient, 'delete').mockResolvedValue({
        data: { success: true },
      } as any);

      await classesApi.removeStudent('cls-1', 'std-1');
      expect(delSpy).toHaveBeenCalledWith('/classes/cls-1/students/std-1');
    });

    it('getGradeLevels requests /grade-levels', async () => {
      const getSpy = vi.spyOn(apiClient, 'get').mockResolvedValue({
        data: {
          success: true,
          data: [{ id: 'gl-7', level: 7, name: 'Kelas 7' }],
        },
      } as any);

      const res = await classesApi.getGradeLevels();
      expect(getSpy).toHaveBeenCalledWith('/grade-levels');
      expect(res[0].level).toBe(7);
    });
  });
});
