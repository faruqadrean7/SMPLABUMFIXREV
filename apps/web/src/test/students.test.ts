import { describe, it, expect, vi, beforeEach } from 'vitest';
import { studentSchema } from '@/features/master/student-dialog';
import { changeStudentStatusSchema } from '@/features/master/student-status-dialog';
import { studentsApi } from '@/features/master/students-api';
import { apiClient } from '@/lib/api/api-client';

describe('ADM-MST-02 Student Schema Validation & API Client', () => {
  describe('studentSchema validation', () => {
    it('passes with valid student input', () => {
      const validStudent = {
        nis: '20241001',
        nisn: '0081234567',
        nik: '3507123456789012',
        full_name: 'Ahmad Fauzi',
        gender: 'L' as const,
        birth_place: 'Malang',
        birth_date: '2010-05-15',
        religion: 'Islam',
        address: 'Jl. Surabaya No. 12',
        entry_date: '2024-07-15',
        status: 'aktif' as const,
      };

      const result = studentSchema.safeParse(validStudent);
      expect(result.success).toBe(true);
    });

    it('fails when NIK is not exactly 16 digits', () => {
      const invalidNiks = ['123', '350712345678901', '35071234567890123', '3507abc456789012'];

      invalidNiks.forEach((nik) => {
        const result = studentSchema.safeParse({
          nis: '20241001',
          nisn: '0081234567',
          nik,
          full_name: 'Ahmad Fauzi',
          gender: 'L',
          birth_place: 'Malang',
          birth_date: '2010-05-15',
          entry_date: '2024-07-15',
        });

        expect(result.success).toBe(false);
        if (!result.success) {
          expect(result.error.errors[0].message).toBe('NIK harus berupa 16 digit angka');
        }
      });
    });

    it('fails when birth_date is today or in the future', () => {
      const futureDate = new Date();
      futureDate.setFullYear(futureDate.getFullYear() + 1);

      const result = studentSchema.safeParse({
        nis: '20241001',
        nisn: '0081234567',
        nik: '3507123456789012',
        full_name: 'Ahmad Fauzi',
        gender: 'L',
        birth_place: 'Malang',
        birth_date: futureDate.toISOString().split('T')[0],
        entry_date: '2024-07-15',
      });

      expect(result.success).toBe(false);
      if (!result.success) {
        expect(result.error.errors[0].message).toBe('Tanggal lahir harus sebelum hari ini');
      }
    });

    it('fails when gender is invalid', () => {
      const result = studentSchema.safeParse({
        nis: '20241001',
        nisn: '0081234567',
        nik: '3507123456789012',
        full_name: 'Ahmad Fauzi',
        gender: 'X' as any,
        birth_place: 'Malang',
        birth_date: '2010-05-15',
        entry_date: '2024-07-15',
      });

      expect(result.success).toBe(false);
    });
  });

  describe('changeStudentStatusSchema validation', () => {
    it('passes with valid status and effective date', () => {
      const result = changeStudentStatusSchema.safeParse({
        status: 'pindah',
        effective_date: '2025-01-10',
        reason: 'Pindah domisili orang tua ke Surabaya',
      });

      expect(result.success).toBe(true);
    });

    it('fails when status is not in allowed enum', () => {
      const result = changeStudentStatusSchema.safeParse({
        status: 'dropout' as any,
        effective_date: '2025-01-10',
      });

      expect(result.success).toBe(false);
    });
  });

  describe('studentsApi methods', () => {
    beforeEach(() => {
      vi.clearAllMocks();
    });

    it('list requests /students with query and status filters', async () => {
      const getSpy = vi.spyOn(apiClient, 'get').mockResolvedValue({
        data: {
          success: true,
          data: [],
          meta: { page: 1, limit: 20, total: 0, totalPages: 0 },
        },
      } as any);

      await studentsApi.list({ page: 1, limit: 20, q: 'Ahmad', status: 'aktif' });

      expect(getSpy).toHaveBeenCalledWith('/students', {
        params: {
          page: 1,
          limit: 20,
          q: 'Ahmad',
          status: 'aktif',
          class_id: undefined,
        },
      });
    });

    it('get requests /students/{id}', async () => {
      const getSpy = vi.spyOn(apiClient, 'get').mockResolvedValue({
        data: {
          success: true,
          data: { id: 'std-1', full_name: 'Ahmad Fauzi' },
        },
      } as any);

      const res = await studentsApi.get('std-1');
      expect(getSpy).toHaveBeenCalledWith('/students/std-1');
      expect(res.full_name).toBe('Ahmad Fauzi');
    });

    it('changeStatus sends POST to /students/{id}/status', async () => {
      const postSpy = vi.spyOn(apiClient, 'post').mockResolvedValue({
        data: {
          success: true,
          data: { id: 'std-1', status: 'lulus' },
        },
      } as any);

      const res = await studentsApi.changeStatus('std-1', {
        status: 'lulus',
        effective_date: '2025-06-20',
      });

      expect(postSpy).toHaveBeenCalledWith('/students/std-1/status', {
        status: 'lulus',
        effective_date: '2025-06-20',
      });
      expect(res.status).toBe('lulus');
    });
  });
});
