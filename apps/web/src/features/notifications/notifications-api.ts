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
    const useJsonBody =
      token.includes('/') || token.includes(':') || token.includes('{') || token.length > 120;
    if (useJsonBody) {
      const res = await apiClient.delete<ApiResponse<{ message: string }>>('/devices', {
        data: { fcm_token: token, token },
      });
      return res.data;
    }

    const res = await apiClient.delete<ApiResponse<{ message: string }>>(
      `/devices/${encodeURIComponent(token)}`
    );
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
      channel_wa?: boolean;
      channel_email?: boolean;
    }>;
    notif_type?: string;
    topic?: string;
    channel_push?: boolean;
    channel_wa?: boolean;
    channel_email?: boolean;
    is_enabled?: boolean;
    enabled?: boolean;
  }): Promise<ApiResponse<any>> => {
    const res = await apiClient.put<ApiResponse<any>>('/me/notification-preferences', payload);
    return res.data;
  },

  // =========================================================================
  // 4. PENGUMUMAN & READER TRACKING (BE-NTF-03)
  // =========================================================================

  getAnnouncements: async (params?: {
    published_only?: boolean;
    target_type?: string;
    search?: string;
    page?: number;
    per_page?: number;
  }): Promise<any> => {
    const res = await apiClient.get<any>('/announcements', { params });
    return res.data;
  },

  createAnnouncement: async (payload: {
    title: string;
    body: string;
    target_type: 'semua' | 'role' | 'kelas' | 'tingkat';
    target_ids?: string[];
    priority?: 'low' | 'normal' | 'high' | 'urgent';
    is_pinned?: boolean;
    publish_at?: string;
  }): Promise<ApiResponse<any>> => {
    const res = await apiClient.post<ApiResponse<any>>('/announcements', payload);
    return res.data;
  },

  deleteAnnouncement: async (id: string): Promise<ApiResponse<{ message: string }>> => {
    const res = await apiClient.delete<ApiResponse<{ message: string }>>(`/announcements/${id}`);
    return res.data;
  },

  getAnnouncementReaders: async (id: string): Promise<ApiResponse<{
    announcement_id: string;
    title: string;
    total_readers: number;
    readers: Array<{ user_id: string; username?: string; email?: string; read_at?: string }>;
  }>> => {
    const res = await apiClient.get<ApiResponse<any>>(`/announcements/${id}/readers`);
    return res.data;
  },

  // =========================================================================
  // 5. TEMPLATE & LOG PENGIRIMAN WA / EMAIL (BE-NTF-02 / P1-19)
  // =========================================================================

  getTemplates: async (params?: { channel?: string; search?: string; page?: number; per_page?: number }): Promise<any> => {
    const res = await apiClient.get<any>('/notification-templates', { params });
    return res.data;
  },

  createTemplate: async (payload: {
    code: string;
    name: string;
    channel: 'whatsapp' | 'email';
    subject?: string;
    template_text: string;
    is_active?: boolean;
  }): Promise<ApiResponse<any>> => {
    const res = await apiClient.post<ApiResponse<any>>('/notification-templates', payload);
    return res.data;
  },

  sendTemplate: async (payload: {
    template_id?: string;
    channel?: 'whatsapp' | 'email';
    recipient: string;
    params?: Record<string, string>;
    subject?: string;
    body?: string;
  }): Promise<ApiResponse<any>> => {
    const res = await apiClient.post<ApiResponse<any>>('/notification-templates/send', payload);
    return res.data;
  },

  getLogs: async (params?: { channel?: string; status?: string; page?: number; per_page?: number }): Promise<any> => {
    const res = await apiClient.get<any>('/notification-logs', { params });
    return res.data;
  },

  retryLog: async (id: string): Promise<ApiResponse<any>> => {
    const res = await apiClient.post<ApiResponse<any>>(`/notification-logs/${id}/retry`);
    return res.data;
  },
};
