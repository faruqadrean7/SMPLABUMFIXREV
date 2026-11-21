import { apiClient } from '@/lib/api/api-client';
import { ApiResponse } from '@/types/api';
import {
  NotificationItem,
  NotificationListResponse,
  BroadcastNotificationPayload,
  BroadcastResult,
  RegisterDevicePayload,
} from './types';

export const notificationsApi = {
  // =========================================================================
  // 1. INBOX & STATUS (BE-NTF-01 / ADM-NTF-01)
  // =========================================================================

  getNotifications: async (params?: {
    is_read?: boolean | 'true' | 'false';
    notif_type?: string;
    page?: number;
    per_page?: number;
  }): Promise<NotificationListResponse> => {
    const res = await apiClient.get<NotificationListResponse>('/notifications', { params });
    return res.data;
  },

  getNotification: async (id: string): Promise<ApiResponse<NotificationItem>> => {
    const res = await apiClient.get<ApiResponse<NotificationItem>>(`/notifications/${id}`);
    return res.data;
  },

  markAsRead: async (id: string): Promise<ApiResponse<NotificationItem>> => {
    const res = await apiClient.patch<ApiResponse<NotificationItem>>(`/notifications/${id}/read`);
    return res.data;
  },

  markAllAsRead: async (): Promise<ApiResponse<{ message: string; updated_count: number }>> => {
    const res = await apiClient.patch<ApiResponse<{ message: string; updated_count: number }>>('/notifications/read-all');
    return res.data;
  },

  deleteNotification: async (id: string): Promise<ApiResponse<{ message: string }>> => {
    const res = await apiClient.delete<ApiResponse<{ message: string }>>(`/notifications/${id}`);
    return res.data;
  },

  // =========================================================================
  // 2. BROADCAST & PERANGKAT (BE-NTF-01 / ADM-NTF-01)
  // =========================================================================

  broadcast: async (payload: BroadcastNotificationPayload): Promise<ApiResponse<BroadcastResult>> => {
    const res = await apiClient.post<ApiResponse<BroadcastResult>>('/notifications/broadcast', payload);
    return res.data;
  },

  registerDevice: async (payload: RegisterDevicePayload): Promise<ApiResponse<{ id: string; token: string }>> => {
    const res = await apiClient.post<ApiResponse<{ id: string; token: string }>>('/devices', payload);
    return res.data;
  },

  destroyDevice: async (token: string): Promise<ApiResponse<{ message: string }>> => {
    const res = await apiClient.delete<ApiResponse<{ message: string }>>(`/devices/${encodeURIComponent(token)}`);
    return res.data;
  },

  // =========================================================================
  // 3. PREFERENSI NOTIFIKASI (GAP-11 / BE-NTF-04)
  // =========================================================================

  getPreferences: async (): Promise<ApiResponse<Array<{
    notif_type: string;
    topic?: string;
    label?: string;
    description?: string;
    is_enabled: boolean;
    enabled?: boolean;
    channel_push: boolean;
    channel_wa?: boolean;
    channel_email?: boolean;
  }>>> => {
    const res = await apiClient.get<ApiResponse<any>>('/me/notification-preferences');
    return res.data;
  },

  updatePreferences: async (payload: {
    preferences?: Array<{
      notif_type?: string;
      topic?: string;
      is_enabled?: boolean;
      enabled?: boolean;
      channel_push?: boolean;
    }>;
    notif_type?: string;
    topic?: string;
    channel_push?: boolean;
    is_enabled?: boolean;
    enabled?: boolean;
  }): Promise<ApiResponse<any>> => {
    const res = await apiClient.put<ApiResponse<any>>('/me/notification-preferences', payload);
    return res.data;
  },
};
