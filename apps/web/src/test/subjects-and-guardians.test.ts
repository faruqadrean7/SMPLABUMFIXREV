import { describe, it, expect, vi, beforeEach } from 'vitest';
import { subjectSchema } from '@/features/master/subject-dialog';
import { teachingAssignmentSchema } from '@/features/master/teaching-assignment-dialog';
import { guardianSchema } from '@/features/master/guardian-dialog';
import { subjectsApi } from '@/features/master/subjects-api';
import { guardiansApi } from '@/features/master/guardians-api';
import { apiClient } from '@/lib/api/api-client';

describe('ADM-MST-06 & ADM-MST-04 Schemas & API Clients', () => {
  describe('subjectSchema validation', () => {
    it('passes with valid subject input', () => {
      const valid = {
        code: 'MAT-W',
        name: 'Matematika Wajib',
        subject_group: 'Kelompok A (Umum)',
      };
      const result = subjectSchema.safeParse(valid);
      expect(result.success).toBe(true);
    });

    it('fails when code or name is missing', () => {
      const invalid = {
        code: '',
        name: '',
        subject_group: 'Kelompok A',
      };
      const result = subjectSchema.safeParse(invalid);
      expect(result.success).toBe(false);
    });
  });

  describe('teachingAssignmentSchema validation', () => {
    it('passes with valid assignment within allowed hours range', () => {
      const valid = {
        semester_id: 'sem-1',
        class_id: 'cls-1',
        subject_id: 'sbj-1',
        teacher_id: 'tch-1',
        hours_per_week: 4,
      };
      const result = teachingAssignmentSchema.safeParse(valid);
      expect(result.success).toBe(true);
    });

    it('fails when hours_per_week exceeds 40', () => {
      const invalid = {
        semester_id: 'sem-1',
        class_id: 'cls-1',
        subject_id: 'sbj-1',
        teacher_id: 'tch-1',
        hours_per_week: 45,
      };
      const result = teachingAssignmentSchema.safeParse(invalid);
      expect(result.success).toBe(false);
    });
  });

  describe('guardianSchema validation', () => {
    it('passes with valid full_name and phone', () => {
      const valid = {
        full_name: 'Bambang Sudarmono',
        phone: '08123456789',
        occupation: 'Wiraswasta',
        address: 'Jl. Ijen No. 10 Malang',
      };
      const result = guardianSchema.safeParse(valid);
      expect(result.success).toBe(true);
    });

    it('fails when phone is missing', () => {
      const invalid = {
        full_name: 'Bambang Sudarmono',
        phone: '',
      };
      const result = guardianSchema.safeParse(invalid);
      expect(result.success).toBe(false);
    });
  });

  describe('subjectsApi and guardiansApi client calls', () => {
    beforeEach(() => {
      vi.clearAllMocks();
    });

    it('subjectsApi.listAssignments requests /teaching-assignments', async () => {
      const getSpy = vi.spyOn(apiClient, 'get').mockResolvedValue({
        data: {
          success: true,
          data: [],
          meta: { page: 1, limit: 20, total: 0, totalPages: 0 },
        },
      } as any);

      await subjectsApi.listAssignments({ page: 1, limit: 20 });
      expect(getSpy).toHaveBeenCalledWith('/teaching-assignments', {
        params: {
          page: 1,
          limit: 20,
          semester_id: undefined,
          class_id: undefined,
          teacher_id: undefined,
        },
      });
    });

    it('guardiansApi.list requests /guardians', async () => {
      const getSpy = vi.spyOn(apiClient, 'get').mockResolvedValue({
        data: {
          success: true,
          data: [],
          meta: { page: 1, limit: 20, total: 0, totalPages: 0 },
        },
      } as any);

      await guardiansApi.list({ page: 1, limit: 20, q: 'Bambang' });
      expect(getSpy).toHaveBeenCalledWith('/guardians', {
        params: {
          page: 1,
          limit: 20,
          q: 'Bambang',
        },
      });
    });

    it('guardiansApi.attachToStudent sends POST to /students/{id}/guardians', async () => {
      const postSpy = vi.spyOn(apiClient, 'post').mockResolvedValue({
        data: { success: true },
      } as any);

      await guardiansApi.attachToStudent('std-1', {
        guardian_id: 'grd-1',
        relationship: 'ayah',
        is_primary: true,
      });

      expect(postSpy).toHaveBeenCalledWith('/students/std-1/guardians', {
        guardian_id: 'grd-1',
        relationship: 'ayah',
        is_primary: true,
      });
    });
  });
});
