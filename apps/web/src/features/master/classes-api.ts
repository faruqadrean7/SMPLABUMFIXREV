import { apiClient } from '@/lib/api/api-client';
import { ApiResponse, PaginatedResponse } from '@/types/api';
import {
  SchoolClassItem,
  ClassFormValues,
  ClassStudentItem,
  GradeLevelItem,
  MajorItem,
  TransferStudentFormValues,
} from '@/types/master';

export const classesApi = {
  /**
   * Ambil daftar rombel berpaginasi
   */
  async list(params?: {
    page?: number;
    limit?: number;
    q?: string;
    academic_year_id?: string;
    grade_level_id?: string;
    major_id?: string;
  }): Promise<PaginatedResponse<SchoolClassItem>> {
    const res = await apiClient.get<PaginatedResponse<SchoolClassItem>>('/classes', {
      params: {
        page: params?.page || 1,
        limit: params?.limit || 20,
        q: params?.q || undefined,
        academic_year_id: params?.academic_year_id || undefined,
        grade_level_id: params?.grade_level_id || undefined,
        major_id: params?.major_id || undefined,
      },
    });
    return res.data;
  },

  /**
   * Ambil detail kelas rombel
   */
  async get(id: string): Promise<SchoolClassItem> {
    const res = await apiClient.get<ApiResponse<SchoolClassItem>>(`/classes/${id}`);
    return res.data.data;
  },

  /**
   * Buat rombel baru (kapasitas default maksimal 36 dianjurkan)
   */
  async create(data: ClassFormValues): Promise<SchoolClassItem> {
    const res = await apiClient.post<ApiResponse<SchoolClassItem>>('/classes', data);
    return res.data.data;
  },

  /**
   * Perbarui data rombel
   */
  async update(id: string, data: Partial<ClassFormValues>): Promise<SchoolClassItem> {
    const res = await apiClient.put<ApiResponse<SchoolClassItem>>(`/classes/${id}`, data);
    return res.data.data;
  },

  /**
   * Hapus rombel (gagal bila masih ada siswa terdaftar atau penugasan mengajar)
   */
  async delete(id: string): Promise<void> {
    await apiClient.delete(`/classes/${id}`);
  },

  /**
   * Daftar siswa dalam rombel
   */
  async listStudents(classId: string, activeOnly = true): Promise<ClassStudentItem[]> {
    const res = await apiClient.get<ApiResponse<ClassStudentItem[]>>(`/classes/${classId}/students`, {
      params: { active_only: activeOnly },
    });
    return res.data.data;
  },

  /**
   * Tambahkan siswa ke rombel
   */
  async addStudents(classId: string, studentIds: string[], joinedAt?: string): Promise<void> {
    await apiClient.post(`/classes/${classId}/students`, {
      student_ids: studentIds,
      joined_at: joinedAt,
    });
  },

  /**
   * Mutasi / pindah rombel siswa
   */
  async transferStudent(classId: string, data: TransferStudentFormValues): Promise<void> {
    await apiClient.post(`/classes/${classId}/students/transfer`, data);
  },

  /**
   * Keluarkan siswa dari rombel
   */
  async removeStudent(classId: string, studentId: string): Promise<void> {
    await apiClient.delete(`/classes/${classId}/students/${studentId}`);
  },

  /**
   * Lookup daftar tingkat kelas
   */
  async getGradeLevels(): Promise<GradeLevelItem[]> {
    const res = await apiClient.get<ApiResponse<GradeLevelItem[]>>('/grade-levels');
    return res.data.data;
  },

  /**
   * Lookup daftar jurusan
   */
  async getMajors(): Promise<MajorItem[]> {
    const res = await apiClient.get<ApiResponse<MajorItem[]>>('/majors');
    return res.data.data;
  },
};
