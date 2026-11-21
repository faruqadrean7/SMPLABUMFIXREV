import { apiClient } from '@/lib/api/api-client';
import { ApiResponse, PaginatedResponse } from '@/types/api';
import {
  StudentItem,
  StudentFormValues,
  StudentStatus,
  ChangeStudentStatusFormValues,
  StatusHistoryItem,
} from '@/types/master';

export const studentsApi = {
  /**
   * Ambil daftar siswa berpaginasi dengan filter
   */
  async list(params?: {
    page?: number;
    limit?: number;
    q?: string;
    status?: StudentStatus;
    class_id?: string;
    without_class?: boolean;
    academic_year_id?: string;
  }): Promise<PaginatedResponse<StudentItem>> {
    const res = await apiClient.get<PaginatedResponse<StudentItem>>('/students', {
      params: {
        page: params?.page || 1,
        limit: params?.limit || 20,
        q: params?.q || undefined,
        status: params?.status || undefined,
        class_id: params?.class_id || undefined,
        without_class: params?.without_class ? true : undefined,
        academic_year_id: params?.academic_year_id || undefined,
      },
    });
    return res.data;
  },

  /**
   * Ambil detail data siswa lengkap dengan kelas, wali, dan riwayat status
   */
  async get(id: string): Promise<StudentItem> {
    const res = await apiClient.get<ApiResponse<StudentItem>>(`/students/${id}`);
    return res.data.data;
  },

  /**
   * Daftarkan data siswa baru
   */
  async create(data: StudentFormValues): Promise<StudentItem> {
    const res = await apiClient.post<ApiResponse<StudentItem>>('/students', data);
    return res.data.data;
  },

  /**
   * Perbarui profil data siswa
   */
  async update(id: string, data: Partial<StudentFormValues>): Promise<StudentItem> {
    const res = await apiClient.put<ApiResponse<StudentItem>>(`/students/${id}`, data);
    return res.data.data;
  },

  /**
   * Hapus / soft-delete data siswa
   */
  async delete(id: string): Promise<void> {
    await apiClient.delete(`/students/${id}`);
  },

  /**
   * Ubah status formal siswa (aktif, lulus, pindah, keluar)
   */
  async changeStatus(id: string, data: ChangeStudentStatusFormValues): Promise<StudentItem> {
    const res = await apiClient.post<ApiResponse<StudentItem>>(`/students/${id}/status`, data);
    return res.data.data;
  },

  /**
   * Ambil riwayat mutasi status siswa
   */
  async getStatusHistory(id: string): Promise<StatusHistoryItem[]> {
    const res = await apiClient.get<ApiResponse<StatusHistoryItem[]>>(`/students/${id}/status-history`);
    return res.data.data;
  },
};
