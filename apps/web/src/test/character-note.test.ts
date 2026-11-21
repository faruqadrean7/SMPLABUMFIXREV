import { describe, it, expect, vi, beforeEach } from 'vitest';
import { characterNoteSchema } from '@/features/academic/character-notes';
import {
  CHARACTER_ASPECTS,
  CHARACTER_RATING_LABELS,
  CHARACTER_RATING_COLORS,
  CharacterRating,
} from '@/types/academic';
import { academicApi } from '@/features/academic/academic-api';
import { apiClient } from '@/lib/api/api-client';

describe('ADM-GRD-02 / BE-GRD-02: Catatan Karakter Bulanan Siswa SISTLABUM', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  describe('characterNoteSchema Validation', () => {
    it('passes validation with a valid student character note payload', () => {
      const valid = {
        student_id: '123e4567-e89b-12d3-a456-426614174001',
        period_id: '123e4567-e89b-12d3-a456-426614174002',
        scale_data: {
          beriman_bertakwa: 'sangat_baik',
          gotong_royong: 'baik',
          mandiri: 'baik',
        },
        homeroom_note: 'Ananda menunjukkan peningkatan adab dan kemandirian yang sangat baik.',
      };

      const result = characterNoteSchema.safeParse(valid);
      expect(result.success).toBe(true);
    });

    it('passes validation when homeroom_note is null or undefined', () => {
      const validWithoutNote = {
        student_id: '123e4567-e89b-12d3-a456-426614174001',
        period_id: '123e4567-e89b-12d3-a456-426614174002',
        scale_data: {
          kejujuran: 'sangat_baik',
        },
        homeroom_note: null,
      };

      const result = characterNoteSchema.safeParse(validWithoutNote);
      expect(result.success).toBe(true);
    });

    it('fails when student_id or period_id is missing', () => {
      const invalid = {
        student_id: '',
        period_id: '',
        scale_data: {
          mandiri: 'baik',
        },
      };

      const result = characterNoteSchema.safeParse(invalid);
      expect(result.success).toBe(false);
      if (!result.success) {
        const errors = result.error.errors.map((e) => e.message);
        expect(errors).toContain('Siswa wajib dipilih');
        expect(errors).toContain('Periode penilaian wajib dipilih');
      }
    });

    it('fails when scale_data has no ratings evaluated', () => {
      const emptyScale = {
        student_id: '123e4567-e89b-12d3-a456-426614174001',
        period_id: '123e4567-e89b-12d3-a456-426614174002',
        scale_data: {},
      };

      const result = characterNoteSchema.safeParse(emptyScale);
      expect(result.success).toBe(false);
      if (!result.success) {
        const errors = result.error.errors.map((e) => e.message);
        expect(errors).toContain('Minimal satu aspek karakter harus dinilai');
      }
    });

    it('fails when homeroom_note exceeds 2000 characters limit', () => {
      const longNote = 'a'.repeat(2001);
      const invalid = {
        student_id: '123e4567-e89b-12d3-a456-426614174001',
        period_id: '123e4567-e89b-12d3-a456-426614174002',
        scale_data: {
          mandiri: 'baik',
        },
        homeroom_note: longNote,
      };

      const result = characterNoteSchema.safeParse(invalid);
      expect(result.success).toBe(false);
      if (!result.success) {
        const errors = result.error.errors.map((e) => e.message);
        expect(errors).toContain('Catatan wali kelas maksimal 2000 karakter');
      }
    });
  });

  describe('Kurikulum Merdeka 8 Character Aspects & Scales', () => {
    it('defines 8 standard dimensions of Profil Pelajar Pancasila & Pembiasaan', () => {
      expect(CHARACTER_ASPECTS).toHaveLength(8);
      const keys = CHARACTER_ASPECTS.map((a) => a.key);
      expect(keys).toContain('beriman_bertakwa');
      expect(keys).toContain('berkebinekaan_global');
      expect(keys).toContain('gotong_royong');
      expect(keys).toContain('mandiri');
      expect(keys).toContain('bernalar_kritis');
      expect(keys).toContain('kreatif');
      expect(keys).toContain('kedisiplinan');
      expect(keys).toContain('kejujuran');
    });

    it('maps all 4-level rating scales with Indonesian labels and badges', () => {
      const scales: CharacterRating[] = ['sangat_baik', 'baik', 'cukup', 'perlu_bimbingan'];
      scales.forEach((scale) => {
        expect(CHARACTER_RATING_LABELS[scale]).toBeDefined();
        expect(CHARACTER_RATING_COLORS[scale]).toBeDefined();
      });

      expect(CHARACTER_RATING_LABELS.sangat_baik).toBe('Sangat Baik (SB)');
      expect(CHARACTER_RATING_LABELS.baik).toBe('Baik (B)');
      expect(CHARACTER_RATING_LABELS.cukup).toBe('Cukup (C)');
      expect(CHARACTER_RATING_LABELS.perlu_bimbingan).toBe('Perlu Bimbingan (PB)');
    });
  });

  describe('academicApi Character Notes Endpoints', () => {
    it('calls GET /character-notes with list filters', async () => {
      const mockList = {
        data: [{ id: 'cn-1', student_id: 'stu-1', period_id: 'per-1' }],
        meta: { total: 1, current_page: 1, last_page: 1 },
      };
      vi.spyOn(apiClient, 'get').mockResolvedValue({ data: mockList });

      const res = await academicApi.listCharacterNotes({
        student_id: 'stu-1',
        period_id: 'per-1',
      });

      expect(apiClient.get).toHaveBeenCalledWith('/character-notes', {
        params: { student_id: 'stu-1', period_id: 'per-1' },
      });
      expect(res).toEqual(mockList);
    });

    it('calls GET /character-notes/matrix with class and period parameters', async () => {
      const mockMatrix = {
        class: { id: 'cls-1', name: '7-A' },
        period: { id: 'per-1', name: 'September 2026', is_locked: false },
        students: [
          {
            student: { id: 'stu-1', name: 'Ahmad Dahlan', nisn: '001', gender: 'L' },
            note: {
              id: 'cn-1',
              student_id: 'stu-1',
              scale_data: { mandiri: 'sangat_baik' },
              homeroom_note: 'Sangat mandiri',
              is_complete: true,
            },
            is_complete: true,
          },
        ],
        summary: {
          total_students: 1,
          completed_count: 1,
          incomplete_count: 0,
        },
      };
      vi.spyOn(apiClient, 'get').mockResolvedValue({ data: { data: mockMatrix } });

      const res = await academicApi.getClassCharacterNotes({
        class_id: 'cls-1',
        period_id: 'per-1',
      });

      expect(apiClient.get).toHaveBeenCalledWith('/character-notes/matrix', {
        params: { class_id: 'cls-1', period_id: 'per-1' },
      });
      expect(res).toEqual(mockMatrix);
    });

    it('calls GET /character-notes/{id} to get individual note detail', async () => {
      const mockDetail = {
        id: 'cn-1',
        student_id: 'stu-1',
        scale_data: { beriman_bertakwa: 'sangat_baik' },
        homeroom_note: 'Aktif sholat berjamaah',
      };
      vi.spyOn(apiClient, 'get').mockResolvedValue({ data: { data: mockDetail } });

      const res = await academicApi.getCharacterNote('cn-1');

      expect(apiClient.get).toHaveBeenCalledWith('/character-notes/cn-1');
      expect(res).toEqual(mockDetail);
    });

    it('calls POST /character-notes to create or update single student note', async () => {
      const payload = {
        student_id: 'stu-1',
        period_id: 'per-1',
        scale_data: { gotong_royong: 'baik' },
        homeroom_note: 'Suka bekerja sama dalam tim',
      };
      const mockSaved = { id: 'cn-new', ...payload };
      vi.spyOn(apiClient, 'post').mockResolvedValue({ data: { data: mockSaved } });

      const res = await academicApi.storeCharacterNote(payload);

      expect(apiClient.post).toHaveBeenCalledWith('/character-notes', payload);
      expect(res.id).toBe('cn-new');
    });

    it('calls POST /character-notes/batch to store bulk student notes', async () => {
      const payload = {
        class_id: 'cls-1',
        period_id: 'per-1',
        notes: [
          {
            student_id: 'stu-1',
            scale_data: { mandiri: 'sangat_baik' },
            homeroom_note: 'Ananda teladan',
          },
        ],
      };
      const mockBatchResponse = {
        count: 1,
        period_id: 'per-1',
        items: [{ id: 'cn-1', student_id: 'stu-1' }],
      };
      vi.spyOn(apiClient, 'post').mockResolvedValue({ data: { data: mockBatchResponse } });

      const res = await academicApi.bulkStoreCharacterNotes(payload);

      expect(apiClient.post).toHaveBeenCalledWith('/character-notes/batch', payload);
      expect(res.count).toBe(1);
    });

    it('calls DELETE /character-notes/{id} to remove a note', async () => {
      vi.spyOn(apiClient, 'delete').mockResolvedValue({ data: { message: 'Catatan karakter berhasil dihapus' } });

      await academicApi.deleteCharacterNote('cn-1');

      expect(apiClient.delete).toHaveBeenCalledWith('/character-notes/cn-1');
    });
  });
});
