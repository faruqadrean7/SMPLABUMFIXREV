import { describe, it, expect, vi, beforeEach } from 'vitest';
import { notificationsApi } from '@/features/notifications/notifications-api';
import { apiClient } from '@/lib/api/api-client';
import {
  NotificationItem,
  NotificationListResponse,
  BroadcastNotificationPayload,
  RegisterDevicePayload,
} from '@/features/notifications/types';
import { getSafeDeepLink } from '@/components/notifications/notification-bell';

describe('Notification System (BE-NTF-01 / ADM-NTF-01)', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  // =========================================================================
  // 1. INBOX & STATUS BACA NOTIFIKASI
  // =========================================================================
  describe('1. Inbox & Status Baca Notifikasi', () => {
    it('mengambil daftar notifikasi inbox pengguna beserta unread count', async () => {
      const mockResponse: NotificationListResponse = {
        data: [
          {
            id: 'notif-1',
            user_id: 'user-123',
            title: 'Pengumuman Rapat Wali Murid',
            body: 'Rapat koordinasi awal semester akan diadakan Sabtu besok.',
            notif_type: 'announcement',
            read_at: null,
            created_at: '2026-10-14T08:00:00Z',
          },
          {
            id: 'notif-2',
            user_id: 'user-123',
            title: 'Tugas Matematika Baru',
            body: 'Guru Matematika telah mengunggah tugas baru.',
            notif_type: 'academic',
            read_at: '2026-10-14T08:30:00Z',
            created_at: '2026-10-14T07:00:00Z',
          },
        ],
        meta: {
          page: 1,
          limit: 15,
          total: 2,
          totalPages: 1,
          unread_count: 1,
        },
      };

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: mockResponse,
      } as any);

      const res = await notificationsApi.getNotifications({
        is_read: false,
        page: 1,
      });

      expect(apiClient.get).toHaveBeenCalledWith('/notifications', {
        params: { is_read: false, page: 1 },
      });
      expect(res.data).toHaveLength(2);
      expect(res.meta.unread_count).toBe(1);
    });

    it('mengambil detail satu notifikasi berdasarkan ID', async () => {
      const mockNotif: NotificationItem = {
        id: 'notif-1',
        user_id: 'user-123',
        title: 'Detail Pengumuman',
        body: 'Isi lengkap dari pesan broadcast pengumuman.',
        read_at: null,
        created_at: '2026-10-14T08:00:00Z',
      };

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: { data: mockNotif },
      } as any);

      const res = await notificationsApi.getNotification('notif-1');

      expect(apiClient.get).toHaveBeenCalledWith('/notifications/notif-1');
      expect(res.data?.id).toBe('notif-1');
    });

    it('menandai satu notifikasi sebagai sudah dibaca', async () => {
      vi.spyOn(apiClient, 'patch').mockResolvedValueOnce({
        data: {
          data: {
            id: 'notif-1',
            read_at: '2026-10-14T09:00:00Z',
          },
        },
      } as any);

      const res = await notificationsApi.markAsRead('notif-1');

      expect(apiClient.patch).toHaveBeenCalledWith('/notifications/notif-1/read');
      expect(res.data?.read_at).toBeTruthy();
    });

    it('menandai semua notifikasi pengguna sebagai sudah dibaca', async () => {
      vi.spyOn(apiClient, 'patch').mockResolvedValueOnce({
        data: {
          data: {
            message: 'Semua notifikasi berhasil ditandai telah dibaca.',
            updated_count: 5,
          },
        },
      } as any);

      const res = await notificationsApi.markAllAsRead();

      expect(apiClient.patch).toHaveBeenCalledWith('/notifications/read-all');
      expect(res.data?.updated_count).toBe(5);
    });

    it('menghapus notifikasi dari inbox pengguna', async () => {
      vi.spyOn(apiClient, 'delete').mockResolvedValueOnce({
        data: { data: { message: 'Notifikasi berhasil dihapus.' } },
      } as any);

      const res = await notificationsApi.deleteNotification('notif-1');

      expect(apiClient.delete).toHaveBeenCalledWith('/notifications/notif-1');
      expect(res.data?.message).toContain('berhasil');
    });
  });

  // =========================================================================
  // 2. BROADCAST NOTIFIKASI TERSEGMENTASI & PERANGKAT
  // =========================================================================
  describe('2. Broadcast Notifikasi & Manajemen Token Perangkat', () => {
    it('mengirim broadcast notifikasi ke seluruh pengguna (all)', async () => {
      const payload: BroadcastNotificationPayload = {
        target_type: 'all',
        title: 'Pengumuman Libur Nasional',
        body: 'Besok seluruh kegiatan belajar mengajar diliburkan.',
        notif_type: 'announcement',
      };

      vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: {
          data: {
            dispatched_count: 120,
            message: 'Broadcast berhasil dikirim ke antrean.',
          },
        },
      } as any);

      const res = await notificationsApi.broadcast(payload);

      expect(apiClient.post).toHaveBeenCalledWith('/notifications/broadcast', payload);
      expect(res.data?.dispatched_count).toBe(120);
    });

    it('mengirim broadcast notifikasi tersegmentasi berdasarkan role', async () => {
      const payload: BroadcastNotificationPayload = {
        target_type: 'role',
        target_role: 'teacher',
        title: 'Batas Input Nilai Rapor',
        body: 'Batas akhir pengisian nilai rapor adalah hari Jumat pukul 23:59.',
        notif_type: 'reminder',
      };

      vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: {
          data: {
            dispatched_count: 45,
            message: 'Broadcast per-role berhasil dikirim.',
          },
        },
      } as any);

      const res = await notificationsApi.broadcast(payload);

      expect(apiClient.post).toHaveBeenCalledWith('/notifications/broadcast', payload);
      expect(res.data?.dispatched_count).toBe(45);
    });

    it('mendaftarkan token perangkat push notifikasi FCM', async () => {
      const payload: RegisterDevicePayload = {
        token: 'fcm-sample-device-token-12345',
        platform: 'fcm',
        device_name: 'Samsung Galaxy A54',
      };

      vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: {
          data: {
            id: 'device-id-1',
            token: payload.token,
          },
        },
      } as any);

      const res = await notificationsApi.registerDevice(payload);

      expect(apiClient.post).toHaveBeenCalledWith('/devices', payload);
      expect(res.data?.id).toBe('device-id-1');
    });

    it('menghapus token perangkat saat logout', async () => {
      vi.spyOn(apiClient, 'delete').mockResolvedValueOnce({
        data: {
          data: { message: 'Perangkat berhasil dihapus.' },
        },
      } as any);

      const res = await notificationsApi.destroyDevice('fcm-sample-token-12345');

      expect(apiClient.delete).toHaveBeenCalledWith('/devices/fcm-sample-token-12345');
      expect(res.data?.message).toContain('berhasil');
    });
  });

  // =========================================================================
  // 3. KEAMANAN DEEP LINK & PENCEGAHAN OPEN REDIRECT / XSS
  // =========================================================================
  describe('3. Keamanan Deep Link & Validasi Navigasi Internal', () => {
    it('mengizinkan URL rute internal yang sah', () => {
      expect(getSafeDeepLink('/admin/master/students')).toBe('/admin/master/students');
      expect(getSafeDeepLink('/portal/siswa/cbt/session-123')).toBe('/portal/siswa/cbt/session-123');
      expect(getSafeDeepLink('/portal/ortu/rapor?semester=1')).toBe('/portal/ortu/rapor?semester=1');
    });

    it('menolak URL eksternal berbahaya untuk mencegah Open Redirect', () => {
      expect(getSafeDeepLink('https://evil-phishing.com/login')).toBeNull();
      expect(getSafeDeepLink('http://attacker.site/steal')).toBeNull();
      expect(getSafeDeepLink('//evil.com/redirect')).toBeNull();
      expect(getSafeDeepLink('/\\evil.com')).toBeNull();
    });

    it('menolak protokol berbahaya seperti javascript: atau data: untuk mencegah XSS', () => {
      expect(getSafeDeepLink('javascript:alert(document.cookie)')).toBeNull();
      expect(getSafeDeepLink('javascript:void(0)')).toBeNull();
      expect(getSafeDeepLink('data:text/html,<script>alert(1)</script>')).toBeNull();
      expect(getSafeDeepLink('vbscript:msgbox(1)')).toBeNull();
    });

    it('menolak input non-string atau null/undefined', () => {
      expect(getSafeDeepLink(null)).toBeNull();
      expect(getSafeDeepLink(undefined)).toBeNull();
      expect(getSafeDeepLink(12345)).toBeNull();
      expect(getSafeDeepLink({})).toBeNull();
    });
  });
});
