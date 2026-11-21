import { describe, it, expect, vi, beforeEach } from 'vitest';
import { usersApi } from '@/features/settings/users-api';
import { userFormSchema } from '@/features/settings/user-dialog';
import { apiClient } from '@/lib/api/api-client';

describe('ADM-USR-01 s/d ADM-USR-04 User Management', () => {
  describe('userFormSchema validation', () => {
    it('passes with valid user inputs', () => {
      const valid = {
        username: 'staff_tu_01',
        email: 'staff@labschool.sch.id',
        phone: '08123456789',
        password: 'Password123!',
        status: 'active' as const,
        roles: ['teacher'],
      };
      const result = userFormSchema.safeParse(valid);
      expect(result.success).toBe(true);
    });

    it('fails when username has invalid characters', () => {
      const invalid = {
        username: 'staff tu with space',
        status: 'active' as const,
        roles: [],
      };
      const result = userFormSchema.safeParse(invalid);
      expect(result.success).toBe(false);
      if (!result.success) {
        expect(result.error.errors[0].message).toContain('Hanya boleh memuat huruf, angka, titik, strip, atau garis bawah');
      }
    });

    it('fails when username is too short', () => {
      const invalid = {
        username: 'ab',
        status: 'active' as const,
        roles: [],
      };
      const result = userFormSchema.safeParse(invalid);
      expect(result.success).toBe(false);
      if (!result.success) {
        expect(result.error.errors[0].message).toContain('Username minimal 3 karakter');
      }
    });

    it('fails when email format is invalid', () => {
      const invalid = {
        username: 'staff_valid',
        email: 'bukan-email-valid',
        status: 'active' as const,
        roles: [],
      };
      const result = userFormSchema.safeParse(invalid);
      expect(result.success).toBe(false);
      if (!result.success) {
        expect(result.error.errors[0].message).toContain('Format email tidak valid');
      }
    });
  });

  describe('usersApi client methods', () => {
    beforeEach(() => {
      vi.clearAllMocks();
    });

    it('list requests /users with pagination and filters', async () => {
      const getSpy = vi.spyOn(apiClient, 'get').mockResolvedValue({
        data: {
          data: [
            {
              id: 'u1',
              username: 'budi_santoso',
              email: 'budi@labschool.sch.id',
              phone: '08123456789',
              status: 'active',
              roles: [{ id: 'r1', code: 'teacher', name: 'Guru' }],
            },
          ],
          meta: { page: 1, limit: 15, total: 1, totalPages: 1 },
        },
      } as any);

      const res = await usersApi.list({ page: 1, limit: 15, q: 'budi', status: 'active' });
      expect(getSpy).toHaveBeenCalledWith('/users', {
        params: {
          page: 1,
          limit: 15,
          q: 'budi',
          role: undefined,
          status: 'active',
        },
      });
      expect(res.data[0].username).toBe('budi_santoso');
      expect(res.data[0].status).toBe('active');
    });

    it('get requests /users/:id', async () => {
      const getSpy = vi.spyOn(apiClient, 'get').mockResolvedValue({
        data: {
          data: {
            id: 'u1',
            username: 'budi_santoso',
            email: 'budi@labschool.sch.id',
            status: 'active',
            roles: [{ id: 'r1', code: 'teacher', name: 'Guru' }],
          },
        },
      } as any);

      const res = await usersApi.get('u1');
      expect(getSpy).toHaveBeenCalledWith('/users/u1');
      expect(res.id).toBe('u1');
      expect(res.username).toBe('budi_santoso');
    });

    it('create posts to /users', async () => {
      const postSpy = vi.spyOn(apiClient, 'post').mockResolvedValue({
        data: {
          data: {
            user: {
              id: 'u-new',
              username: 'guru_baru',
              status: 'active',
              roles: [],
            },
            temporary_password: 'GeneratedPass123!',
          },
        },
      } as any);

      const payload = {
        username: 'guru_baru',
        email: 'guru@labschool.sch.id',
        roles: ['teacher'],
      };
      const res = await usersApi.create(payload);
      expect(postSpy).toHaveBeenCalledWith('/users', payload);
      expect(res.user.username).toBe('guru_baru');
      expect(res.temporary_password).toBe('GeneratedPass123!');
    });

    it('update puts to /users/:id', async () => {
      const putSpy = vi.spyOn(apiClient, 'put').mockResolvedValue({
        data: {
          data: {
            id: 'u1',
            username: 'budi_santoso_updated',
            status: 'active',
            roles: [],
          },
        },
      } as any);

      const payload = { username: 'budi_santoso_updated' };
      const res = await usersApi.update('u1', payload);
      expect(putSpy).toHaveBeenCalledWith('/users/u1', payload);
      expect(res.username).toBe('budi_santoso_updated');
    });

    it('changeStatus patches to /users/:id/status', async () => {
      const patchSpy = vi.spyOn(apiClient, 'patch').mockResolvedValue({
        data: {
          data: {
            id: 'u1',
            username: 'budi_santoso',
            status: 'suspended',
            roles: [],
          },
        },
      } as any);

      const res = await usersApi.changeStatus('u1', 'suspended', 'Pelanggaran kebijakan');
      expect(patchSpy).toHaveBeenCalledWith('/users/u1/status', {
        status: 'suspended',
        reason: 'Pelanggaran kebijakan',
      });
      expect(res.status).toBe('suspended');
    });

    it('resetPassword posts to /users/:id/reset-password', async () => {
      const postSpy = vi.spyOn(apiClient, 'post').mockResolvedValue({
        data: {
          data: {
            message: 'Password berhasil di-reset',
            temporary_password: 'TempPassword123!',
            must_change_password: true,
          },
        },
      } as any);

      const res = await usersApi.resetPassword('u1', { must_change_password: true });
      expect(postSpy).toHaveBeenCalledWith('/users/u1/reset-password', {
        must_change_password: true,
      });
      expect(res.message).toBe('Password berhasil di-reset');
      expect(res.temporary_password).toBe('TempPassword123!');
    });
  });
});
