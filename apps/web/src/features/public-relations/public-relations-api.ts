import { apiClient } from '@/lib/api/api-client';
import { ApiResponse, PaginatedResponse } from '@/types/api';
import {
  GuestItem,
  GuestSignatureDetail,
  RegisterGuestPayload,
  ComplaintItem,
  CreateComplaintPayload,
  UpdateComplaintStatusPayload,
  PublicComplaintTrackResult,
  PartnershipItem,
  CreatePartnershipPayload,
  AlumniItem,
} from './types';

export const publicRelationsApi = {
  // =========================================================================
  // 1. BUKU TAMU DIGITAL (BE-GUEST-01 & BE-GUEST-03 / ADM-GUEST-01)
  // =========================================================================

  getGuests: async (params?: {
    q?: string;
    status?: 'all' | 'checked_in' | 'checked_out';
    start_date?: string;
    end_date?: string;
    target_user_id?: string;
    page?: number;
    per_page?: number;
  }): Promise<PaginatedResponse<GuestItem>> => {
    const res = await apiClient.get<PaginatedResponse<GuestItem>>('/guests', { params });
    return res.data;
  },

  getGuest: async (id: string): Promise<ApiResponse<GuestItem>> => {
    const res = await apiClient.get<ApiResponse<GuestItem>>(`/guests/${id}`);
    return res.data;
  },

  checkoutGuest: async (id: string): Promise<ApiResponse<GuestItem>> => {
    const res = await apiClient.patch<ApiResponse<GuestItem>>(`/guests/${id}/checkout`);
    return res.data;
  },

  getGuestSignature: async (id: string): Promise<ApiResponse<GuestSignatureDetail>> => {
    const res = await apiClient.get<ApiResponse<GuestSignatureDetail>>(`/guests/${id}/signature`);
    return res.data;
  },

  registerGuestPublic: async (data: RegisterGuestPayload): Promise<ApiResponse<GuestItem>> => {
    const res = await apiClient.post<ApiResponse<GuestItem>>('/public/guests', data);
    return res.data;
  },

  uploadGuestSignaturePublic: async (
    id: string,
    payload: { signature_data?: string } | FormData
  ): Promise<ApiResponse<GuestSignatureDetail>> => {
    const headers = payload instanceof FormData ? { 'Content-Type': 'multipart/form-data' } : undefined;
    const res = await apiClient.post<ApiResponse<GuestSignatureDetail>>(`/public/guests/${id}/signature`, payload, {
      headers,
    });
    return res.data;
  },

  // =========================================================================
  // 2. ASPIRASI & PENGADUAN / WHISTLEBLOWING (BE-GUEST-02 / ADM-GUEST-02)
  // =========================================================================

  getComplaints: async (params?: {
    q?: string;
    category?: string;
    status?: string;
    severity?: string;
    overdue?: boolean;
    unassigned?: boolean;
    start_date?: string;
    end_date?: string;
    assigned_to?: string;
    page?: number;
    per_page?: number;
  }): Promise<PaginatedResponse<ComplaintItem>> => {
    const res = await apiClient.get<PaginatedResponse<ComplaintItem>>('/complaints', { params });
    return res.data;
  },

  getComplaint: async (id: string): Promise<ApiResponse<ComplaintItem>> => {
    const res = await apiClient.get<ApiResponse<ComplaintItem>>(`/complaints/${id}`);
    return res.data;
  },

  updateComplaintStatus: async (
    id: string,
    data: UpdateComplaintStatusPayload
  ): Promise<ApiResponse<ComplaintItem>> => {
    const res = await apiClient.patch<ApiResponse<ComplaintItem>>(`/complaints/${id}/status`, data);
    return res.data;
  },

  submitComplaintPublic: async (
    data: CreateComplaintPayload
  ): Promise<ApiResponse<{ ticket_code: string; category: string; is_anonymous: boolean; status: string; message: string }>> => {
    const res = await apiClient.post<ApiResponse<{ ticket_code: string; category: string; is_anonymous: boolean; status: string; message: string }>>(
      '/public/complaints',
      data
    );
    return res.data;
  },

  trackComplaintPublic: async (code: string): Promise<ApiResponse<PublicComplaintTrackResult>> => {
    const res = await apiClient.get<ApiResponse<PublicComplaintTrackResult>>(`/public/complaints/${code}`);
    return res.data;
  },

  // =========================================================================
  // 2b. LAMPIRAN FOTO PENGADUAN (BE-GUEST-04)
  // Alur: presign -> upload (multipart ke URL bertanda tangan) -> confirm
  // =========================================================================

  presignComplaintAttachment: async (payload: {
    filename: string;
    mime_type: string;
    size_bytes: number;
  }): Promise<ApiResponse<{ token: string; upload_url: string; expires_at: string }>> => {
    const res = await apiClient.post<
      ApiResponse<{ token: string; upload_url: string; expires_at: string }>
    >('/public/complaints/attachments/presign', payload);
    return res.data;
  },

  uploadComplaintAttachment: async (uploadUrl: string, file: File): Promise<void> => {
    const form = new FormData();
    form.append('file', file);
    // URL sudah bertanda tangan & absolut: kirim apa adanya, tanpa baseURL.
    await apiClient.post(uploadUrl, form, {
      baseURL: '',
      headers: { 'Content-Type': 'multipart/form-data' },
    });
  },

  confirmComplaintAttachment: async (
    token: string
  ): Promise<ApiResponse<{ file_id: string; mime_type: string; size_bytes: number }>> => {
    const res = await apiClient.post<
      ApiResponse<{ file_id: string; mime_type: string; size_bytes: number }>
    >(`/public/complaints/attachments/${token}/confirm`);
    return res.data;
  },

  // =========================================================================
  // 3. KEMITRAAN & DOKUMEN MOU (BE-HUM-01 / ADM-HUM-01)
  // =========================================================================

  getPartnerships: async (params?: {
    status?: string;
    partner_type?: string;
    expiring_within_days?: number;
    search?: string;
    page?: number;
    per_page?: number;
  }): Promise<PaginatedResponse<PartnershipItem>> => {
    const res = await apiClient.get<PaginatedResponse<PartnershipItem>>('/partnerships', { params });
    return res.data;
  },

  getExpiringPartnerships: async (withinDays: number = 30): Promise<ApiResponse<PartnershipItem[]>> => {
    const res = await apiClient.get<ApiResponse<PartnershipItem[]>>('/partnerships/expiring', {
      params: { within_days: withinDays },
    });
    return res.data;
  },

  getPartnership: async (id: string): Promise<ApiResponse<PartnershipItem>> => {
    const res = await apiClient.get<ApiResponse<PartnershipItem>>(`/partnerships/${id}`);
    return res.data;
  },

  createPartnership: async (data: CreatePartnershipPayload): Promise<ApiResponse<PartnershipItem>> => {
    const res = await apiClient.post<ApiResponse<PartnershipItem>>('/partnerships', data);
    return res.data;
  },

  updatePartnership: async (
    id: string,
    data: Partial<CreatePartnershipPayload>
  ): Promise<ApiResponse<PartnershipItem>> => {
    const res = await apiClient.put<ApiResponse<PartnershipItem>>(`/partnerships/${id}`, data);
    return res.data;
  },

  deletePartnership: async (id: string): Promise<ApiResponse<{ message: string }>> => {
    const res = await apiClient.delete<ApiResponse<{ message: string }>>(`/partnerships/${id}`);
    return res.data;
  },

  // =========================================================================
  // 4. ALUMNI & TRACER STUDY (BE-HUM-02 / ADM-HUM-02)
  // =========================================================================

  getAlumni: async (params?: {
    graduation_year?: number;
    current_activity?: string;
    search?: string;
    page?: number;
    per_page?: number;
  }): Promise<PaginatedResponse<AlumniItem>> => {
    const res = await apiClient.get<PaginatedResponse<AlumniItem>>('/alumni', { params });
    return res.data;
  },
};
