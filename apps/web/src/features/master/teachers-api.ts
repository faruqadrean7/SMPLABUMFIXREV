import { apiClient } from '@/lib/api/api-client';
import { ApiResponse, PaginatedResponse } from '@/types/api';
import {
  TeacherItem,
  TeacherFormValues,
  TeacherEmploymentStatus,
  TeacherSalarySlipItem,
  TeacherSalarySlipFormValues,
  TeachingHoursRecap,
} from '@/types/master';

export const teachersApi = {
  /**
   * Ambil daftar pendidik berpaginasi
   */
  async list(params?: {
    page?: number;
    limit?: number;
    q?: string;
    status?: TeacherEmploymentStatus;
  }): Promise<PaginatedResponse<TeacherItem>> {
    const res = await apiClient.get<PaginatedResponse<TeacherItem>>('/teachers', {
      params: {
        page: params?.page || 1,
        limit: params?.limit || 20,
        q: params?.q || undefined,
        status: params?.status || undefined,
      },
    });
    return res.data;
  },

  /**
   * Ambil detail data pendidik lengkap dengan kelas wali dan penugasan mengajar
   */
  async get(id: string): Promise<TeacherItem> {
    const res = await apiClient.get<ApiResponse<TeacherItem>>(`/teachers/${id}`);
    return res.data.data;
  },

  /**
   * Tambah data pendidik baru
   */
  async create(data: TeacherFormValues): Promise<TeacherItem> {
    const res = await apiClient.post<ApiResponse<TeacherItem>>('/teachers', data);
    return res.data.data;
  },

  /**
   * Perbarui data pendidik
   */
  async update(id: string, data: Partial<TeacherFormValues>): Promise<TeacherItem> {
    const res = await apiClient.put<ApiResponse<TeacherItem>>(`/teachers/${id}`, data);
    return res.data.data;
  },

  /**
   * Hapus data pendidik (dilindungi jika masih memiliki penugasan aktif)
   */
  async delete(id: string): Promise<void> {
    await apiClient.delete(`/teachers/${id}`);
  },

  // =========================================================================
  // SLIP GAJI & REKAP JAM MENGAJAR
  // =========================================================================

  /**
   * Ambil daftar riwayat slip gaji pendidik (Admin TU / Keuangan)
   */
  async listSalarySlips(teacherId: string): Promise<TeacherSalarySlipItem[]> {
    const res = await apiClient.get<ApiResponse<TeacherSalarySlipItem[]>>(`/teachers/${teacherId}/salary-slips`);
    return res.data.data;
  },

  /**
   * Unggah slip gaji baru untuk pendidik
   */
  async createSalarySlip(teacherId: string, data: TeacherSalarySlipFormValues): Promise<TeacherSalarySlipItem> {
    const formData = new FormData();
    formData.append('period_month', String(data.period_month));
    formData.append('period_year', String(data.period_year));
    formData.append('basic_salary', String(data.basic_salary));
    formData.append('allowances', String(data.allowances));
    formData.append('deductions', String(data.deductions));
    if (data.net_salary !== undefined) {
      formData.append('net_salary', String(data.net_salary));
    }
    if (data.payment_date) {
      formData.append('payment_date', data.payment_date);
    }
    if (data.slip_number) {
      formData.append('slip_number', data.slip_number);
    }
    if (data.notes) {
      formData.append('notes', data.notes);
    }
    if (data.status) {
      formData.append('status', data.status);
    }
    if (data.file) {
      formData.append('file', data.file);
    }

    const res = await apiClient.post<ApiResponse<TeacherSalarySlipItem>>(`/teachers/${teacherId}/salary-slips`, formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });
    return res.data.data;
  },

  /**
   * Hapus berkas slip gaji
   */
  async deleteSalarySlip(teacherId: string, slipId: string): Promise<void> {
    await apiClient.delete(`/teachers/${teacherId}/salary-slips/${slipId}`);
  },

  /**
   * Ambil rekap jam mengajar (JJM) pendidik
   */
  async getTeachingRecap(teacherId: string): Promise<TeachingHoursRecap> {
    const res = await apiClient.get<ApiResponse<TeachingHoursRecap>>(`/teachers/${teacherId}/teaching-recap`);
    return res.data.data;
  },

  /**
   * Ambil daftar slip gaji pribadi (Portal Guru)
   */
  async getMySalarySlips(): Promise<TeacherSalarySlipItem[]> {
    const res = await apiClient.get<ApiResponse<TeacherSalarySlipItem[]>>('/teacher/me/salary-slips');
    return res.data.data;
  },

  /**
   * Ambil rekap jam mengajar pribadi (Portal Guru)
   */
  async getMyTeachingRecap(): Promise<TeachingHoursRecap> {
    const res = await apiClient.get<ApiResponse<TeachingHoursRecap>>('/teacher/me/teaching-recap');
    return res.data.data;
  },
};
