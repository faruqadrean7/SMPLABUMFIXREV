import { apiClient } from '@/lib/api/api-client';
import { ApiResponse, PaginatedResponse } from '@/types/api';
import {
  SubjectItem,
  SubjectFormValues,
  TeachingAssignmentItem,
  TeachingAssignmentFormValues,
} from '@/types/master';

export const subjectsApi = {
  /**
   * Ambil daftar mata pelajaran berpaginasi
   */
  async list(params?: {
    page?: number;
    limit?: number;
    q?: string;
  }): Promise<PaginatedResponse<SubjectItem>> {
    const res = await apiClient.get<PaginatedResponse<SubjectItem>>('/subjects', {
      params: {
        page: params?.page || 1,
        limit: params?.limit || 20,
        q: params?.q || undefined,
      },
    });
    return res.data;
  },

  /**
   * Ambil detail mata pelajaran
   */
  async get(id: string): Promise<SubjectItem> {
    const res = await apiClient.get<ApiResponse<SubjectItem>>(`/subjects/${id}`);
    return res.data.data;
  },

  /**
   * Tambah mata pelajaran baru
   */
  async create(data: SubjectFormValues): Promise<SubjectItem> {
    const res = await apiClient.post<ApiResponse<SubjectItem>>('/subjects', data);
    return res.data.data;
  },

  /**
   * Perbarui mata pelajaran
   */
  async update(id: string, data: Partial<SubjectFormValues>): Promise<SubjectItem> {
    const res = await apiClient.put<ApiResponse<SubjectItem>>(`/subjects/${id}`, data);
    return res.data.data;
  },

  /**
   * Hapus mata pelajaran
   */
  async delete(id: string): Promise<void> {
    await apiClient.delete(`/subjects/${id}`);
  },

  /**
   * Ambil daftar penugasan mengajar
   */
  async listAssignments(params?: {
    page?: number;
    limit?: number;
    semester_id?: string;
    class_id?: string;
    teacher_id?: string;
  }): Promise<PaginatedResponse<TeachingAssignmentItem>> {
    const res = await apiClient.get<PaginatedResponse<TeachingAssignmentItem>>('/teaching-assignments', {
      params: {
        page: params?.page || 1,
        limit: params?.limit || 20,
        semester_id: params?.semester_id || undefined,
        class_id: params?.class_id || undefined,
        teacher_id: params?.teacher_id || undefined,
      },
    });
    return res.data;
  },

  /**
   * Tambah penugasan mengajar
   */
  async createAssignment(data: TeachingAssignmentFormValues): Promise<TeachingAssignmentItem> {
    const res = await apiClient.post<ApiResponse<TeachingAssignmentItem>>('/teaching-assignments', data);
    return res.data.data;
  },

  /**
   * Hapus penugasan mengajar
   */
  async deleteAssignment(id: string): Promise<void> {
    await apiClient.delete(`/teaching-assignments/${id}`);
  },
};
