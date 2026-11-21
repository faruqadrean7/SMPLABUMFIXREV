import { apiClient } from '@/lib/api/api-client';
import { ApiResponse, PaginatedResponse } from '@/types/api';
import {
  UserManagementItem,
  CreateUserPayload,
  CreateUserResponseData,
  UpdateUserPayload,
  ResetPasswordPayload,
  ResetPasswordResponseData,
  UserQueryParams,
  UserStatus,
} from '@/types/user-management';

export const usersApi = {
  /**
   * ADM-USR-01: Ambil daftar pengguna sistem berpaginasi
   * GET /api/v1/users
   */
  async list(params?: UserQueryParams): Promise<PaginatedResponse<UserManagementItem>> {
    const res = await apiClient.get<PaginatedResponse<UserManagementItem>>('/users', {
      params: {
        page: params?.page || 1,
        limit: params?.limit || 20,
        q: params?.q || undefined,
        role: params?.role || undefined,
        status: params?.status || undefined,
      },
    });
    return res.data;
  },

  /**
   * ADM-USR-01: Ambil detail profil dan peran pengguna tunggal
   * GET /api/v1/users/{id}
   */
  async get(id: string): Promise<UserManagementItem> {
    const res = await apiClient.get<ApiResponse<UserManagementItem>>(`/users/${id}`);
    return res.data.data;
  },

  /**
   * ADM-USR-02: Tambah pengguna baru oleh Administrator
   * POST /api/v1/users
   */
  async create(data: CreateUserPayload): Promise<CreateUserResponseData> {
    const res = await apiClient.post<ApiResponse<CreateUserResponseData>>('/users', data);
    return res.data.data;
  },

  /**
   * ADM-USR-02: Perbarui identitas/profil akun pengguna
   * PUT /api/v1/users/{id}
   */
  async update(id: string, data: UpdateUserPayload): Promise<UserManagementItem> {
    const res = await apiClient.put<ApiResponse<UserManagementItem>>(`/users/${id}`, data);
    return res.data.data;
  },

  /**
   * ADM-USR-04: Ubah status pengguna (aktif, nonaktif, suspended)
   * PATCH /api/v1/users/{id}/status
   */
  async changeStatus(id: string, status: UserStatus, reason?: string): Promise<UserManagementItem> {
    const res = await apiClient.patch<ApiResponse<UserManagementItem>>(`/users/${id}/status`, {
      status,
      reason,
    });
    return res.data.data;
  },

  /**
   * ADM-USR-04: Reset password akun pengguna oleh Administrator
   * POST /api/v1/users/{id}/reset-password
   */
  async resetPassword(
    id: string,
    data?: ResetPasswordPayload
  ): Promise<ResetPasswordResponseData> {
    const res = await apiClient.post<ApiResponse<ResetPasswordResponseData>>(
      `/users/${id}/reset-password`,
      data || { must_change_password: true }
    );
    return res.data.data;
  },
};
