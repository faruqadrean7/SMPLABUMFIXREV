import { describe, it, expect, vi, beforeEach } from 'vitest';
import { roleSchema } from '@/features/settings/role-dialog';
import { rbacApi } from '@/features/settings/rbac-api';
import { apiClient } from '@/lib/api/api-client';

describe('ADM-USR-03 RBAC Schema & API Client', () => {
  describe('roleSchema validation', () => {
    it('passes with valid role code and name', () => {
      const valid = {
        code: 'curriculum_coordinator',
        name: 'Koordinator Kurikulum',
        permission_ids: ['perm-1', 'perm-2'],
      };
      const result = roleSchema.safeParse(valid);
      expect(result.success).toBe(true);
    });

    it('fails when code contains uppercase or spaces', () => {
      const invalid = {
        code: 'Curriculum Coordinator',
        name: 'Koordinator Kurikulum',
        permission_ids: [],
      };
      const result = roleSchema.safeParse(invalid);
      expect(result.success).toBe(false);
      if (!result.success) {
        expect(result.error.errors[0].message).toContain('Kode hanya boleh menggunakan huruf kecil');
      }
    });

    it('fails when name is too short', () => {
      const invalid = {
        code: 'curriculum',
        name: 'Ko',
        permission_ids: [],
      };
      const result = roleSchema.safeParse(invalid);
      expect(result.success).toBe(false);
      if (!result.success) {
        expect(result.error.errors[0].message).toContain('Nama role minimal 3 karakter');
      }
    });
  });

  describe('rbacApi methods', () => {
    beforeEach(() => {
      vi.clearAllMocks();
    });

    it('listPermissions requests /permissions with module filter', async () => {
      const getSpy = vi.spyOn(apiClient, 'get').mockResolvedValue({
        data: {
          data: [
            { id: '1', code: 'master.student.read', description: 'Lihat Siswa' },
          ],
        },
      } as any);

      const res = await rbacApi.listPermissions('master');
      expect(getSpy).toHaveBeenCalledWith('/permissions', {
        params: { module: 'master' },
      });
      expect(res).toHaveLength(1);
      expect(res[0].code).toBe('master.student.read');
    });

    it('listRoles requests /roles with pagination and search', async () => {
      const getSpy = vi.spyOn(apiClient, 'get').mockResolvedValue({
        data: {
          data: [{ id: 'r1', code: 'teacher', name: 'Guru', is_system: true }],
          meta: { page: 1, limit: 10, total: 1, totalPages: 1 },
        },
      } as any);

      const res = await rbacApi.listRoles({ page: 1, limit: 10, q: 'Guru' });
      expect(getSpy).toHaveBeenCalledWith('/roles', {
        params: { page: 1, limit: 10, q: 'Guru' },
      });
      expect(res.data[0].code).toBe('teacher');
    });

    it('getRole requests /roles/:id', async () => {
      const getSpy = vi.spyOn(apiClient, 'get').mockResolvedValue({
        data: {
          data: {
            id: 'r1',
            code: 'teacher',
            name: 'Guru',
            is_system: true,
            permissions: [],
          },
        },
      } as any);

      const res = await rbacApi.getRole('r1');
      expect(getSpy).toHaveBeenCalledWith('/roles/r1');
      expect(res.code).toBe('teacher');
    });

    it('createRole posts to /roles', async () => {
      const postSpy = vi.spyOn(apiClient, 'post').mockResolvedValue({
        data: {
          data: {
            id: 'r-new',
            code: 'custom_role',
            name: 'Role Kustom',
            is_system: false,
            permissions: [],
          },
        },
      } as any);

      const payload = { code: 'custom_role', name: 'Role Kustom', permission_ids: ['p1'] };
      const res = await rbacApi.createRole(payload);
      expect(postSpy).toHaveBeenCalledWith('/roles', payload);
      expect(res.is_system).toBe(false);
    });

    it('updateRole puts to /roles/:id', async () => {
      const putSpy = vi.spyOn(apiClient, 'put').mockResolvedValue({
        data: {
          data: {
            id: 'r1',
            code: 'custom_role',
            name: 'Role Diubah',
            is_system: false,
            permissions: [],
          },
        },
      } as any);

      const payload = { name: 'Role Diubah', permission_ids: ['p1', 'p2'] };
      const res = await rbacApi.updateRole('r1', payload);
      expect(putSpy).toHaveBeenCalledWith('/roles/r1', payload);
      expect(res.name).toBe('Role Diubah');
    });

    it('deleteRole sends delete to /roles/:id', async () => {
      const deleteSpy = vi.spyOn(apiClient, 'delete').mockResolvedValue({
        data: {
          data: { message: 'Role berhasil dihapus' },
        },
      } as any);

      const res = await rbacApi.deleteRole('r-custom');
      expect(deleteSpy).toHaveBeenCalledWith('/roles/r-custom');
      expect(res.message).toBe('Role berhasil dihapus');
    });

    it('getUserRoles requests /users/:id/roles', async () => {
      const getSpy = vi.spyOn(apiClient, 'get').mockResolvedValue({
        data: {
          data: {
            userId: 'u1',
            username: 'budi',
            roles: [{ id: 'r1', code: 'teacher', name: 'Guru' }],
          },
        },
      } as any);

      const res = await rbacApi.getUserRoles('u1');
      expect(getSpy).toHaveBeenCalledWith('/users/u1/roles');
      expect(res.username).toBe('budi');
    });

    it('assignUserRoles puts to /users/:id/roles', async () => {
      const putSpy = vi.spyOn(apiClient, 'put').mockResolvedValue({
        data: {
          data: {
            message: 'Peran pengguna berhasil diperbarui',
            user: { id: 'u1', username: 'budi', roles: ['teacher', 'homeroom'] },
          },
        },
      } as any);

      const payload = {
        roles: [{ role_id: 'r1', scope_class_id: null }],
      };
      const res = await rbacApi.assignUserRoles('u1', payload);
      expect(putSpy).toHaveBeenCalledWith('/users/u1/roles', payload);
      expect(res.user.roles).toContain('homeroom');
    });
  });
});
