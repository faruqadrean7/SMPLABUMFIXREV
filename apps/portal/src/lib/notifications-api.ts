'use client';

import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from './api-client';

export interface NotificationItem {
  id: string;
  notif_type?: string | null;
  title: string;
  body?: string | null;
  data?: Record<string, unknown> | null;
  read_at?: string | null;
  is_read?: boolean;
  created_at?: string | null;
}

export interface NotificationInboxResult {
  items: NotificationItem[];
  unreadCount: number;
}

/** GET /notifications -> { data: NotificationItem[], meta: { unread_count } } */
export function useNotifications() {
  return useQuery<NotificationInboxResult>({
    queryKey: ['notifications'],
    queryFn: async () => {
      const res = await apiClient.get('/notifications', { params: { limit: 50 } });
      return {
        items: (res.data?.data ?? []) as NotificationItem[],
        unreadCount: Number(res.data?.meta?.unread_count ?? 0),
      };
    },
  });
}

/** PATCH /notifications/{id}/read */
export function useMarkNotificationRead() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (id: string) => {
      await apiClient.patch(`/notifications/${id}/read`);
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['notifications'] }),
  });
}

/** PATCH /notifications/read-all */
export function useMarkAllNotificationsRead() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async () => {
      await apiClient.patch('/notifications/read-all');
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['notifications'] }),
  });
}

export function isNotificationRead(n: NotificationItem): boolean {
  return typeof n.is_read === 'boolean' ? n.is_read : !!n.read_at;
}
