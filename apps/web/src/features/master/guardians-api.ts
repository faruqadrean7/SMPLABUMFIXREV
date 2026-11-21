import { apiClient } from '@/lib/api/api-client';
import { ApiResponse, PaginatedResponse } from '@/types/api';
import { GuardianItem, GuardianFormValues, AttachStudentGuardianFormValues } from '@/types/master';

export const guardiansApi = {
  /**
   * Ambil daftar orang tua/wali berpaginasi
   */
  async list(params?: {
    page?: number;
    limit?: number;
    q?: string;
  }): Promise<PaginatedResponse<GuardianItem>> {
    const res = await apiClient.get<PaginatedResponse<GuardianItem>>('/guardians', {
      params: {
        page: params?.page || 1,
        limit: params?.limit || 20,
        q: params?.q || undefined,
      },
    });
    return res.data;
  },

  /**
   * Ambil detail wali
   */
  async get(id: string): Promise<GuardianItem> {
    const res = await apiClient.get<ApiResponse<GuardianItem>>(`/guardians/${id}`);
    return res.data.data;
  },

  /**
   * Tambah data wali baru
   */
  async create(data: GuardianFormValues): Promise<GuardianItem> {
    const res = await apiClient.post<ApiResponse<GuardianItem>>('/guardians', data);
    return res.data.data;
  },

  /**
   * Perbarui data wali
   */
  async update(id: string, data: Partial<GuardianFormValues>): Promise<GuardianItem> {
    const res = await apiClient.put<ApiResponse<GuardianItem>>(`/guardians/${id}`, data);
    return res.data.data;
  },

  /**
   * Hapus data wali
   */
  async delete(id: string): Promise<void> {
    await apiClient.delete(`/guardians/${id}`);
  },

  /**
   * Hubungkan wali dengan anak/siswa
   */
  async attachToStudent(studentId: string, data: AttachStudentGuardianFormValues): Promise<void> {
    await apiClient.post(`/students/${studentId}/guardians`, data);
  },

  /**
   * Aktifkan atau reset akun login portal wali
   */
  async provisionAccount(id: string, data?: { username?: string; password?: string; email?: string }): Promise<any> {
    const res = await apiClient.post<ApiResponse<any>>(`/guardians/${id}/account`, data || {});
    return res.data.data;
  },

  /**
   * Putuskan hubungan wali dengan anak/siswa
   */
  async detachFromStudent(studentId: string, guardianId: string): Promise<void> {
    await apiClient.delete(`/students/${studentId}/guardians/${guardianId}`);
  },
};
