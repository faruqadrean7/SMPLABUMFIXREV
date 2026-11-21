import { apiClient } from '@/lib/api/api-client';
import { ApiResponse, PaginatedResponse } from '@/types/api';
import {
  PermissionItem,
  RoleItem,
  RoleDetail,
  CreateRolePayload,
  UpdateRolePayload,
  UserRoleDetail,
  AssignUserRolesPayload,
} from '@/types/rbac';

export const rbacApi = {
  /**
   * BE-AUTH-03: Ambil daftar seluruh master permission
   * GET /api/v1/permissions
   */
  async listPermissions(module?: string): Promise<PermissionItem[]> {
    const res = await apiClient.get<ApiResponse<PermissionItem[]>>('/permissions', {
      params: { module: module || undefined },
    });
    return res.data.data;
  },

  /**
   * BE-AUTH-03: Ambil daftar role berpaginasi
   * GET /api/v1/roles
   */
  async listRoles(params?: {
    page?: number;
    limit?: number;
    q?: string;
  }): Promise<PaginatedResponse<RoleItem>> {
    const res = await apiClient.get<PaginatedResponse<RoleItem>>('/roles', {
      params: {
        page: params?.page || 1,
        limit: params?.limit || 20,
        q: params?.q || undefined,
      },
    });
    return res.data;
  },

  /**
   * BE-AUTH-03: Ambil detail role beserta daftar permission
   * GET /api/v1/roles/{id}
   */
  async getRole(id: string): Promise<RoleDetail> {
    const res = await apiClient.get<ApiResponse<RoleDetail>>(`/roles/${id}`);
    return res.data.data;
  },

  /**
   * BE-AUTH-03: Buat custom role baru
   * POST /api/v1/roles
   */
  async createRole(data: CreateRolePayload): Promise<RoleDetail> {
    const res = await apiClient.post<ApiResponse<RoleDetail>>('/roles', data);
    return res.data.data;
  },

  /**
   * BE-AUTH-03: Perbarui role dan sinkronisasi permission
   * PUT /api/v1/roles/{id}
   */
  async updateRole(id: string, data: UpdateRolePayload): Promise<RoleDetail> {
    const res = await apiClient.put<ApiResponse<RoleDetail>>(`/roles/${id}`, data);
    return res.data.data;
  },

  /**
   * BE-AUTH-03: Hapus custom role (system roles ditolak 400, in-use roles ditolak 409)
   * DELETE /api/v1/roles/{id}
   */
  async deleteRole(id: string): Promise<{ message: string }> {
    const res = await apiClient.delete<ApiResponse<{ message: string }>>(`/roles/${id}`);
    return res.data.data;
  },

  /**
   * BE-AUTH-03: Ambil daftar peran yang dimiliki oleh pengguna tertentu
   * GET /api/v1/users/{id}/roles
   */
  async getUserRoles(userId: string): Promise<UserRoleDetail> {
    const res = await apiClient.get<ApiResponse<UserRoleDetail>>(`/users/${userId}/roles`);
    return res.data.data;
  },

  /**
   * BE-AUTH-03: Assign multi-role ke pengguna
   * PUT /api/v1/users/{id}/roles
   */
  async assignUserRoles(
    userId: string,
    data: AssignUserRolesPayload
  ): Promise<{ message: string; user: { id: string; username: string; roles: string[] } }> {
    const res = await apiClient.put<
      ApiResponse<{ message: string; user: { id: string; username: string; roles: string[] } }>
    >(`/users/${userId}/roles`, data);
    return res.data.data;
  },
};
