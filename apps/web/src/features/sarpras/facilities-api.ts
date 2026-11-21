import { apiClient } from '@/lib/api/api-client';
import { ApiResponse, PaginatedResponse } from '@/types/api';
import {
  FacilityItem,
  BookingItem,
  AvailabilityResult,
  BookingSummary,
  CreateFacilityPayload,
  UpdateFacilityPayload,
  CreateBookingPayload,
  DecideBookingPayload,
} from './types';

export const facilitiesApi = {
  // =========================================================================
  // 1. MASTER FASILITAS & RUANGAN (BE-SAR-01 / ADM-SAR-01)
  // =========================================================================

  getFacilities: async (params?: {
    facility_type?: string;
    status?: string;
    is_active?: boolean;
    search?: string;
    page?: number;
    per_page?: number;
  }): Promise<PaginatedResponse<FacilityItem>> => {
    const res = await apiClient.get<PaginatedResponse<FacilityItem>>('/facilities', { params });
    return res.data;
  },

  getFacility: async (id: string): Promise<ApiResponse<FacilityItem>> => {
    const res = await apiClient.get<ApiResponse<FacilityItem>>(`/facilities/${id}`);
    return res.data;
  },

  createFacility: async (data: CreateFacilityPayload): Promise<ApiResponse<FacilityItem>> => {
    const res = await apiClient.post<ApiResponse<FacilityItem>>('/facilities', data);
    return res.data;
  },

  updateFacility: async (id: string, data: UpdateFacilityPayload): Promise<ApiResponse<FacilityItem>> => {
    const res = await apiClient.put<ApiResponse<FacilityItem>>(`/facilities/${id}`, data);
    return res.data;
  },

  deleteFacility: async (id: string): Promise<ApiResponse<{ message: string }>> => {
    const res = await apiClient.delete<ApiResponse<{ message: string }>>(`/facilities/${id}`);
    return res.data;
  },

  // =========================================================================
  // 2. PEMINJAMAN & KETERSEDIAAN JADWAL (BE-SAR-01 / ADM-SAR-01)
  // =========================================================================

  checkAvailability: async (params: {
    facility_id: string;
    start_at?: string;
    end_at?: string;
    date?: string;
  }): Promise<ApiResponse<AvailabilityResult>> => {
    const res = await apiClient.get<ApiResponse<AvailabilityResult>>('/bookings/availability', { params });
    return res.data;
  },

  getBookings: async (params?: {
    facility_id?: string;
    status?: string;
    from_date?: string;
    to_date?: string;
    search?: string;
    view?: 'list' | 'calendar';
    page?: number;
    per_page?: number;
  }): Promise<PaginatedResponse<BookingItem>> => {
    const res = await apiClient.get<PaginatedResponse<BookingItem>>('/bookings', { params });
    return res.data;
  },

  getBooking: async (id: string): Promise<ApiResponse<BookingItem>> => {
    const res = await apiClient.get<ApiResponse<BookingItem>>(`/bookings/${id}`);
    return res.data;
  },

  createBooking: async (data: CreateBookingPayload): Promise<ApiResponse<BookingItem>> => {
    const res = await apiClient.post<ApiResponse<BookingItem>>('/bookings', data);
    return res.data;
  },

  decideBooking: async (id: string, data: DecideBookingPayload): Promise<ApiResponse<BookingItem>> => {
    const res = await apiClient.patch<ApiResponse<BookingItem>>(`/bookings/${id}/decision`, data);
    return res.data;
  },

  cancelBooking: async (id: string): Promise<ApiResponse<BookingItem>> => {
    const res = await apiClient.delete<ApiResponse<BookingItem>>(`/bookings/${id}`);
    return res.data;
  },

  getSummary: async (): Promise<ApiResponse<BookingSummary>> => {
    const res = await apiClient.get<ApiResponse<BookingSummary>>('/bookings/summary');
    return res.data;
  },
};
