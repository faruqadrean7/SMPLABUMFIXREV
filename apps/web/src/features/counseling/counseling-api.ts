import { apiClient } from '@/lib/api/api-client';
import { ApiResponse, PaginatedResponse } from '@/types/api';
import {
  CounselingCaseItem,
  CounselingSessionItem,
  CounselingReferralItem,
  CounselingRequestItem,
  CreateCounselingCasePayload,
  UpdateCounselingCasePayload,
  CreateCounselingSessionPayload,
  CreateCounselingReferralPayload,
  UpdateCounselingReferralStatusPayload,
  CreateCounselingRequestPayload,
  ScheduleCounselingRequestPayload,
  RejectCounselingRequestPayload,
  CompleteCounselingRequestPayload,
  CounselingCategory,
  CounselingCaseStatus,
  CounselingReferralStatus,
  CounselingRequestStatus,
} from './types';

export const counselingApi = {
  // =========================================================================
  // 1. KASUS KONSELING (BE-BK-01 / ADM-BK-01)
  // =========================================================================

  getCases: async (params?: {
    student_id?: string;
    category?: CounselingCategory;
    status?: CounselingCaseStatus;
    search?: string;
    page?: number;
    per_page?: number;
  }): Promise<PaginatedResponse<CounselingCaseItem>> => {
    const res = await apiClient.get<PaginatedResponse<CounselingCaseItem>>('/counseling/cases', { params });
    return res.data;
  },

  getCase: async (id: string): Promise<ApiResponse<CounselingCaseItem>> => {
    const res = await apiClient.get<ApiResponse<CounselingCaseItem>>(`/counseling/cases/${id}`);
    return res.data;
  },

  createCase: async (data: CreateCounselingCasePayload): Promise<ApiResponse<CounselingCaseItem>> => {
    const res = await apiClient.post<ApiResponse<CounselingCaseItem>>('/counseling/cases', data);
    return res.data;
  },

  updateCase: async (
    id: string,
    data: UpdateCounselingCasePayload
  ): Promise<ApiResponse<CounselingCaseItem>> => {
    const res = await apiClient.put<ApiResponse<CounselingCaseItem>>(`/counseling/cases/${id}`, data);
    return res.data;
  },

  // =========================================================================
  // 2. SESI KONSELING
  // =========================================================================

  createSession: async (
    caseId: string,
    data: CreateCounselingSessionPayload
  ): Promise<ApiResponse<CounselingSessionItem>> => {
    const res = await apiClient.post<ApiResponse<CounselingSessionItem>>(`/counseling/cases/${caseId}/sessions`, data);
    return res.data;
  },

  getSession: async (id: string): Promise<ApiResponse<CounselingSessionItem>> => {
    const res = await apiClient.get<ApiResponse<CounselingSessionItem>>(`/counseling/sessions/${id}`);
    return res.data;
  },

  // =========================================================================
  // 3. RUJUKAN KONSELING DARI GURU
  // =========================================================================

  getReferrals: async (params?: {
    status?: CounselingReferralStatus;
    page?: number;
    per_page?: number;
  }): Promise<PaginatedResponse<CounselingReferralItem>> => {
    const res = await apiClient.get<PaginatedResponse<CounselingReferralItem>>('/counseling/referrals', { params });
    return res.data;
  },

  createReferral: async (data: CreateCounselingReferralPayload): Promise<ApiResponse<CounselingReferralItem>> => {
    const res = await apiClient.post<ApiResponse<CounselingReferralItem>>('/counseling/referrals', data);
    return res.data;
  },

  updateReferralStatus: async (
    id: string,
    data: UpdateCounselingReferralStatusPayload
  ): Promise<ApiResponse<CounselingReferralItem>> => {
    const res = await apiClient.patch<ApiResponse<CounselingReferralItem>>(`/counseling/referrals/${id}/status`, data);
    return res.data;
  },

  // =========================================================================
  // 4. PERMINTAAN KONSELING (BE-BK-03 / ADM-BK-03 / SIS-BK-01 / ORT-BK-01)
  // =========================================================================

  getRequests: async (params?: {
    status?: CounselingRequestStatus;
    student_id?: string;
    search?: string;
    from_date?: string;
    to_date?: string;
    page?: number;
    per_page?: number;
  }): Promise<PaginatedResponse<CounselingRequestItem>> => {
    const res = await apiClient.get<PaginatedResponse<CounselingRequestItem>>('/counseling/requests', { params });
    return res.data;
  },

  getRequest: async (id: string): Promise<ApiResponse<CounselingRequestItem>> => {
    const res = await apiClient.get<ApiResponse<CounselingRequestItem>>(`/counseling/requests/${id}`);
    return res.data;
  },

  createRequest: async (data: CreateCounselingRequestPayload): Promise<ApiResponse<CounselingRequestItem>> => {
    const res = await apiClient.post<ApiResponse<CounselingRequestItem>>('/counseling/requests', data);
    return res.data;
  },

  scheduleRequest: async (
    id: string,
    data: ScheduleCounselingRequestPayload
  ): Promise<ApiResponse<CounselingRequestItem>> => {
    const res = await apiClient.patch<ApiResponse<CounselingRequestItem>>(`/counseling/requests/${id}/schedule`, data);
    return res.data;
  },

  rejectRequest: async (
    id: string,
    data: RejectCounselingRequestPayload
  ): Promise<ApiResponse<CounselingRequestItem>> => {
    const res = await apiClient.patch<ApiResponse<CounselingRequestItem>>(`/counseling/requests/${id}/reject`, data);
    return res.data;
  },

  completeRequest: async (
    id: string,
    data: CompleteCounselingRequestPayload
  ): Promise<ApiResponse<CounselingRequestItem>> => {
    const res = await apiClient.patch<ApiResponse<CounselingRequestItem>>(`/counseling/requests/${id}/complete`, data);
    return res.data;
  },

  cancelRequest: async (id: string): Promise<ApiResponse<CounselingRequestItem>> => {
    const res = await apiClient.delete<ApiResponse<CounselingRequestItem>>(`/counseling/requests/${id}`);
    return res.data;
  },
};
