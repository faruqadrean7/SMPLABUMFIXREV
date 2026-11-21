import { describe, it, expect, vi, beforeEach } from 'vitest';
import { gradeComponentSchema } from '@/features/academic/grades';
import { academicApi } from '@/features/academic/academic-api';
import { apiClient } from '@/lib/api/api-client';

describe('ADM-GRD-01 / BE-GRD-01: Buku Nilai & Komponen Penilaian Bulanan', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  describe('gradeComponentSchema Validation', () => {
    it('passes validation with a valid component payload', () => {
      const valid = {
        code: 'UH1',
        name: 'Ulangan Harian 1: Aljabar',
        weight: 20,
        subject_id: '123e4567-e89b-12d3-a456-426614174001',
        semester_id: '123e4567-e89b-12d3-a456-426614174002',
        class_id: null,
      };
      const result = gradeComponentSchema.safeParse(valid);
      expect(result.success).toBe(true);
    });

    it('fails when code or name is missing or too short', () => {
      const invalid = {
        code: '',
        name: 'A',
        weight: 20,
        subject_id: '123e4567-e89b-12d3-a456-426614174001',
        semester_id: '123e4567-e89b-12d3-a456-426614174002',
      };
      const result = gradeComponentSchema.safeParse(invalid);
      expect(result.success).toBe(false);
      if (!result.success) {
        const errors = result.error.errors.map((e) => e.message);
        expect(errors).toContain('Kode komponen wajib diisi (contoh: UH1, TUGAS, PTS)');
        expect(errors).toContain('Nama komponen penilaian minimal 2 karakter');
      }
    });

    it('fails when weight is less than 0.1 or greater than 100', () => {
      const invalidZero = {
        code: 'TUGAS',
        name: 'Tugas Mandiri',
        weight: 0,
        subject_id: '123e4567-e89b-12d3-a456-426614174001',
        semester_id: '123e4567-e89b-12d3-a456-426614174002',
      };
      const resZero = gradeComponentSchema.safeParse(invalidZero);
      expect(resZero.success).toBe(false);

      const invalidOver = {
        code: 'TUGAS',
        name: 'Tugas Mandiri',
        weight: 120,
        subject_id: '123e4567-e89b-12d3-a456-426614174001',
        semester_id: '123e4567-e89b-12d3-a456-426614174002',
      };
      const resOver = gradeComponentSchema.safeParse(invalidOver);
      expect(resOver.success).toBe(false);
    });
  });

  describe('academicApi Grade & Component Endpoints', () => {
    it('calls GET /grade-components with query filters', async () => {
      const mockComps = [
        { id: 'comp-1', code: 'UH1', name: 'Ulangan Harian 1', weight: 20 },
      ];
      vi.spyOn(apiClient, 'get').mockResolvedValue({ data: { data: mockComps } });

      const res = await academicApi.listGradeComponents({
        subject_id: 'sub-1',
        semester_id: 'sem-1',
      });

      expect(apiClient.get).toHaveBeenCalledWith('/grade-components', {
        params: { subject_id: 'sub-1', semester_id: 'sem-1' },
      });
      expect(res).toEqual(mockComps);
    });

    it('calls POST /grade-components to create a component', async () => {
      const payload = {
        code: 'PTS',
        name: 'Penilaian Tengah Semester',
        weight: 30,
        subject_id: 'sub-1',
        semester_id: 'sem-1',
      };
      const mockCreated = { id: 'comp-new', ...payload };
      vi.spyOn(apiClient, 'post').mockResolvedValue({ data: { data: mockCreated } });

      const res = await academicApi.createGradeComponent(payload);

      expect(apiClient.post).toHaveBeenCalledWith('/grade-components', payload);
      expect(res.id).toBe('comp-new');
    });

    it('calls PUT /grade-components/{id} to update a component', async () => {
      const payload = { code: 'PTS_REV', name: 'PTS Revisi', weight: 35 };
      const mockUpdated = { id: 'comp-1', ...payload };
      vi.spyOn(apiClient, 'put').mockResolvedValue({ data: { data: mockUpdated } });

      const res = await academicApi.updateGradeComponent('comp-1', payload);

      expect(apiClient.put).toHaveBeenCalledWith('/grade-components/comp-1', payload);
      expect(res.weight).toBe(35);
    });

    it('calls DELETE /grade-components/{id} to delete a component', async () => {
      vi.spyOn(apiClient, 'delete').mockResolvedValue({ data: {} });

      await academicApi.deleteGradeComponent('comp-1');

      expect(apiClient.delete).toHaveBeenCalledWith('/grade-components/comp-1');
    });

    it('calls GET /grade-periods with semester_id filter', async () => {
      const mockPeriods = [
        { id: 'per-8', month: 8, month_name: 'Agustus', status: 'open' as const },
        { id: 'per-9', month: 9, month_name: 'September', status: 'locked' as const },
      ];
      vi.spyOn(apiClient, 'get').mockResolvedValue({ data: { data: mockPeriods } });

      const res = await academicApi.listGradePeriods({ semester_id: 'sem-1' });

      expect(apiClient.get).toHaveBeenCalledWith('/grade-periods', {
        params: { semester_id: 'sem-1' },
      });
      expect(res.length).toBe(2);
      expect(res[0].month_name).toBe('Agustus');
    });

    it('calls PATCH /grade-periods/{id}/lock to lock/unlock period', async () => {
      const payload = { status: 'locked' as const };
      const mockLocked = { id: 'per-8', month: 8, status: 'locked' as const };
      vi.spyOn(apiClient, 'patch').mockResolvedValue({ data: { data: mockLocked } });

      const res = await academicApi.lockGradePeriod('per-8', payload);

      expect(apiClient.patch).toHaveBeenCalledWith('/grade-periods/per-8/lock', payload);
      expect(res.status).toBe('locked');
    });

    it('calls GET /grades/matrix to fetch spreadsheet grid data', async () => {
      const mockMatrix = {
        class: { id: 'cls-1', name: 'VII-A' },
        period: { id: 'per-8', month: 8, status: 'open' },
        components: [{ id: 'comp-1', code: 'UH1', weight: 20 }],
        total_weight: 20,
        students: [
          {
            student: { id: 'std-1', nisn: '001', name: 'Aditya' },
            grades: { 'comp-1': { id: 'g-1', value: 90, source: 'manual' } },
            is_complete: true,
            filled_count: 1,
            total_components: 1,
            final_score: 90,
          },
        ],
        summary: {
          total_students: 1,
          complete_count: 1,
          incomplete_count: 0,
          average_score: 90,
          highest_score: 90,
          lowest_score: 90,
        },
      };
      vi.spyOn(apiClient, 'get').mockResolvedValue({ data: { data: mockMatrix } });

      const filters = { class_id: 'cls-1', subject_id: 'sub-1', period_id: 'per-8' };
      const res = await academicApi.getClassGradeMatrix(filters);

      expect(apiClient.get).toHaveBeenCalledWith('/grades/matrix', { params: filters });
      expect(res.summary.total_students).toBe(1);
      expect(res.students[0].grades['comp-1']?.value).toBe(90);
    });

    it('calls POST /grades/batch to mass-save spreadsheet values', async () => {
      const payload = {
        period_id: 'per-8',
        grades: [
          { student_id: 'std-1', component_id: 'comp-1', value: 88 },
          { student_id: 'std-2', component_id: 'comp-1', value: 92 },
        ],
      };
      const mockResult = { count: 2, period_id: 'per-8', items: [] };
      vi.spyOn(apiClient, 'post').mockResolvedValue({ data: { data: mockResult } });

      const res = await academicApi.bulkStoreGrades(payload);

      expect(apiClient.post).toHaveBeenCalledWith('/grades/batch', payload);
      expect(res.count).toBe(2);
    });

    it('calls DELETE /grades/{id} to delete single grade entry', async () => {
      vi.spyOn(apiClient, 'delete').mockResolvedValue({ data: {} });

      await academicApi.deleteGrade('g-1');

      expect(apiClient.delete).toHaveBeenCalledWith('/grades/g-1');
    });
  });

  describe('Weighted Score and Completeness Calculation Logic', () => {
    it('calculates weighted score accurately based on components and weights', () => {
      const components = [
        { id: 'comp-1', code: 'UH1', weight: 20 },
        { id: 'comp-2', code: 'TUGAS', weight: 30 },
        { id: 'comp-3', code: 'PAS', weight: 50 },
      ];

      const grades: Record<string, number> = {
        'comp-1': 80, // 80 * 20 = 1600
        'comp-2': 90, // 90 * 30 = 2700
        'comp-3': 70, // 70 * 50 = 3500
      };

      let weightedSum = 0;
      let totalWeight = 0;

      components.forEach((c) => {
        const val = grades[c.id];
        if (val !== undefined) {
          weightedSum += val * c.weight;
          totalWeight += c.weight;
        }
      });

      const finalScore = totalWeight > 0 ? Math.round((weightedSum / totalWeight) * 100) / 100 : 0;
      // (1600 + 2700 + 3500) / 100 = 7800 / 100 = 78.00
      expect(finalScore).toBe(78);
    });

    it('correctly flags completeness when all components are graded vs missing', () => {
      const compIds = ['comp-1', 'comp-2', 'comp-3'];

      const completeGrades = { 'comp-1': 85, 'comp-2': 90, 'comp-3': 88 };
      const isComplete = compIds.every((id) => completeGrades[id as keyof typeof completeGrades] !== undefined);
      expect(isComplete).toBe(true);

      const partialGrades = { 'comp-1': 85, 'comp-2': 90 };
      const isPartial = compIds.every((id) => partialGrades[id as keyof typeof partialGrades] !== undefined);
      expect(isPartial).toBe(false);
    });
  });
});
