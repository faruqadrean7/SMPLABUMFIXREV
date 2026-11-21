import { apiClient } from '@/lib/api/api-client';
import { ApiResponse, PaginatedResponse } from '@/types/api';
import {
  ExtracurricularItem,
  ExtracurricularMemberItem,
  ExtracurricularJournalItem,
  ActivityDocumentItem,
  CreateExtracurricularPayload,
  UpdateExtracurricularPayload,
  RegisterMemberPayload,
  UpdateMemberPayload,
  CreateJournalPayload,
  CreateActivityDocumentPayload,
} from './extracurricular-types';

export const extracurricularApi = {
  // =========================================================================
  // 1. MASTER EKSTRAKURIKULER (BE-STD-02 / ADM-STD-02)
  // =========================================================================

  getExtracurriculars: async (params?: {
    category?: string;
    coach_id?: string;
    is_active?: boolean;
    search?: string;
    page?: number;
    per_page?: number;
  }): Promise<PaginatedResponse<ExtracurricularItem>> => {
    const res = await apiClient.get<PaginatedResponse<ExtracurricularItem>>('/extracurriculars', { params });
    return res.data;
  },

  getExtracurricular: async (id: string): Promise<ApiResponse<ExtracurricularItem>> => {
    const res = await apiClient.get<ApiResponse<ExtracurricularItem>>(`/extracurriculars/${id}`);
    return res.data;
  },

  createExtracurricular: async (data: CreateExtracurricularPayload): Promise<ApiResponse<ExtracurricularItem>> => {
    const res = await apiClient.post<ApiResponse<ExtracurricularItem>>('/extracurriculars', data);
    return res.data;
  },

  updateExtracurricular: async (
    id: string,
    data: UpdateExtracurricularPayload
  ): Promise<ApiResponse<ExtracurricularItem>> => {
    const res = await apiClient.put<ApiResponse<ExtracurricularItem>>(`/extracurriculars/${id}`, data);
    return res.data;
  },

  deleteExtracurricular: async (id: string): Promise<ApiResponse<{ message: string }>> => {
    const res = await apiClient.delete<ApiResponse<{ message: string }>>(`/extracurriculars/${id}`);
    return res.data;
  },

  // =========================================================================
  // 2. KEANGGOTAAN & PENILAIAN RAPOR
  // =========================================================================

  getMembers: async (params: {
    extracurricular_id?: string;
    student_id?: string;
    status?: string;
    role?: string;
    search?: string;
    page?: number;
    per_page?: number;
  }): Promise<PaginatedResponse<ExtracurricularMemberItem>> => {
    const res = await apiClient.get<PaginatedResponse<ExtracurricularMemberItem>>('/extracurricular-members', { params });
    return res.data;
  },

  registerMember: async (data: RegisterMemberPayload): Promise<ApiResponse<ExtracurricularMemberItem>> => {
    const res = await apiClient.post<ApiResponse<ExtracurricularMemberItem>>('/extracurricular-members', data);
    return res.data;
  },

  updateMember: async (
    id: string,
    data: UpdateMemberPayload
  ): Promise<ApiResponse<ExtracurricularMemberItem>> => {
    const res = await apiClient.put<ApiResponse<ExtracurricularMemberItem>>(`/extracurricular-members/${id}`, data);
    return res.data;
  },

  removeMember: async (id: string): Promise<ApiResponse<{ message: string }>> => {
    const res = await apiClient.delete<ApiResponse<{ message: string }>>(`/extracurricular-members/${id}`);
    return res.data;
  },

  // =========================================================================
  // 3. JURNAL KEGIATAN & PRESENSI
  // =========================================================================

  getJournals: async (params: {
    extracurricular_id: string;
    page?: number;
    per_page?: number;
  }): Promise<PaginatedResponse<ExtracurricularJournalItem>> => {
    const res = await apiClient.get<PaginatedResponse<ExtracurricularJournalItem>>('/extracurricular-journals', { params });
    return res.data;
  },

  getJournal: async (id: string): Promise<ApiResponse<ExtracurricularJournalItem>> => {
    const res = await apiClient.get<ApiResponse<ExtracurricularJournalItem>>(`/extracurricular-journals/${id}`);
    return res.data;
  },

  createJournal: async (data: CreateJournalPayload): Promise<ApiResponse<ExtracurricularJournalItem>> => {
    const res = await apiClient.post<ApiResponse<ExtracurricularJournalItem>>('/extracurricular-journals', data);
    return res.data;
  },

  deleteJournal: async (id: string): Promise<ApiResponse<{ message: string }>> => {
    const res = await apiClient.delete<ApiResponse<{ message: string }>>(`/extracurricular-journals/${id}`);
    return res.data;
  },

  // =========================================================================
  // 4. DOKUMEN KEGIATAN (PROPOSAL & LPJ)
  // =========================================================================

  getDocuments: async (params?: {
    extracurricular_id?: string;
    doc_type?: 'proposal' | 'laporan';
    search?: string;
    page?: number;
    per_page?: number;
  }): Promise<PaginatedResponse<ActivityDocumentItem>> => {
    const res = await apiClient.get<PaginatedResponse<ActivityDocumentItem>>('/activity-documents', { params });
    return res.data;
  },

  getDocument: async (id: string): Promise<ApiResponse<ActivityDocumentItem>> => {
    const res = await apiClient.get<ApiResponse<ActivityDocumentItem>>(`/activity-documents/${id}`);
    return res.data;
  },

  createDocument: async (data: CreateActivityDocumentPayload): Promise<ApiResponse<ActivityDocumentItem>> => {
    const res = await apiClient.post<ApiResponse<ActivityDocumentItem>>('/activity-documents', data);
    return res.data;
  },

  deleteDocument: async (id: string): Promise<ApiResponse<{ message: string }>> => {
    const res = await apiClient.delete<ApiResponse<{ message: string }>>(`/activity-documents/${id}`);
    return res.data;
  },
};
