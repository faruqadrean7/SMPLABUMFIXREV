import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import {
  urlBase64ToUint8Array,
  isPushNotificationSupported,
  getNotificationPermission,
  requestNotificationPermission,
  registerServiceWorker,
  subscribeToWebPush,
  unsubscribeFromWebPush,
  syncSubscriptionWithBackend,
  removeSubscriptionFromBackend,
  sanitizeTargetUrl,
  type NotificationPreferenceSettings,
} from '@/features/notifications/web-push';
import { notificationsApi } from '@/features/notifications/notifications-api';

describe('Web Push Notification Helper (BE-NTF-01 / ADM-NTF-01)', () => {
  beforeEach(() => {
    vi.clearAllMocks();

    if (!('PushManager' in window)) {
      (window as any).PushManager = class PushManager {};
    }

    if (!('Notification' in window)) {
      (window as any).Notification = class Notification {
        static permission = 'default';
        static requestPermission = vi.fn().mockResolvedValue('granted');
      };
    }

    if (!('serviceWorker' in navigator)) {
      Object.defineProperty(navigator, 'serviceWorker', {
        value: {
          register: vi.fn(),
        },
        configurable: true,
      });
    }
  });

  describe('1. urlBase64ToUint8Array', () => {
    it('mengonversi base64 standard string menjadi Uint8Array yang valid', () => {
      // "SISTLABUM" in standard base64 is "U0lTVExBQlVN"
      const base64 = 'U0lTVExBQlVN';
      const uint8 = urlBase64ToUint8Array(base64);

      expect(uint8).toBeInstanceOf(Uint8Array);
      expect(uint8.length).toBe(9);
      // Decoded bytes for "SISTLABUM"
      expect(Array.from(uint8)).toEqual([83, 73, 83, 84, 76, 65, 66, 85, 77]);
    });

    it('menangani string base64url dengan padding yang kurang', () => {
      // base64url characters '-' and '_'
      const base64url = 'U0lTVExBQlVN-_';
      const uint8 = urlBase64ToUint8Array(base64url);

      expect(uint8).toBeInstanceOf(Uint8Array);
      expect(uint8.length).toBeGreaterThan(0);
    });
  });

  describe('2. Browser Support & Permission Checks', () => {
    it('mendeteksi jika fitur web push didukung browser', () => {
      const isSupported = isPushNotificationSupported();
      expect(typeof isSupported).toBe('boolean');
      expect(isSupported).toBe(true);
    });

    it('mengambil status permission notifikasi saat ini', () => {
      const permission = getNotificationPermission();
      expect(['default', 'granted', 'denied', 'unsupported']).toContain(permission);
    });

    it('meminta izin notifikasi pengguna melalui requestPermission', async () => {
      const spy = vi.spyOn(window.Notification, 'requestPermission').mockResolvedValue('granted');

      const perm = await requestNotificationPermission();
      expect(perm).toBe('granted');
      expect(spy).toHaveBeenCalled();
    });
  });

  describe('3. Service Worker & PushManager Subscription', () => {
    it('mendaftarkan service worker pada path default /sw.js', async () => {
      const mockRegistration = {
        scope: '/',
        active: { state: 'activated' },
      };

      const origSW = navigator.serviceWorker;
      Object.defineProperty(navigator, 'serviceWorker', {
        value: {
          register: vi.fn().mockResolvedValue(mockRegistration),
        },
        configurable: true,
      });

      const reg = await registerServiceWorker('/sw.js');
      expect(reg).toEqual(mockRegistration);
      expect(navigator.serviceWorker.register).toHaveBeenCalledWith('/sw.js', { scope: '/' });

      Object.defineProperty(navigator, 'serviceWorker', {
        value: origSW,
        configurable: true,
      });
    });

    it('melewati subscription jika VAPID public key kosong', async () => {
      const mockReg = {
        pushManager: {
          subscribe: vi.fn(),
        },
      } as any;

      const sub = await subscribeToWebPush(mockReg, '');
      expect(sub).toBeNull();
      expect(mockReg.pushManager.subscribe).not.toHaveBeenCalled();
    });

    it('melakukan subscribe dengan VAPID public key yang valid', async () => {
      const fakeSubscription = {
        endpoint: 'https://fcm.googleapis.com/fcm/send/fake-token',
        toJSON: () => ({
          endpoint: 'https://fcm.googleapis.com/fcm/send/fake-token',
          keys: {
            p256dh: 'fake-p256dh',
            auth: 'fake-auth',
          },
        }),
      };

      const mockReg = {
        pushManager: {
          subscribe: vi.fn().mockResolvedValue(fakeSubscription),
        },
      } as any;

      const sampleVapidKey = 'U0lTVExBQlVN';
      const sub = await subscribeToWebPush(mockReg, sampleVapidKey);

      expect(sub).toEqual(fakeSubscription);
      expect(mockReg.pushManager.subscribe).toHaveBeenCalledWith({
        userVisibleOnly: true,
        applicationServerKey: expect.any(Uint8Array),
      });
    });

    it('melakukan unsubscribe browser dari push service', async () => {
      const mockSub = {
        unsubscribe: vi.fn().mockResolvedValue(true),
      };

      const mockReg = {
        pushManager: {
          getSubscription: vi.fn().mockResolvedValue(mockSub),
        },
      } as any;

      const result = await unsubscribeFromWebPush(mockReg);
      expect(result).toBe(true);
      expect(mockSub.unsubscribe).toHaveBeenCalled();
    });
  });

  describe('4. Backend Subscription Sync', () => {
    it('mengirim token subscription browser ke backend /devices endpoint', async () => {
      const spyRegister = vi.spyOn(notificationsApi, 'registerDevice').mockResolvedValue({
        data: { id: 'device-123', token: 'mock-token' },
      });

      const fakeSub = {
        endpoint: 'https://push.example.com/sub/123',
        toJSON: () => ({
          endpoint: 'https://push.example.com/sub/123',
          keys: { p256dh: 'p256', auth: 'auth' },
        }),
      } as any;

      const success = await syncSubscriptionWithBackend(fakeSub, 'Chrome MacOS');

      expect(success).toBe(true);
      expect(spyRegister).toHaveBeenCalledWith({
        token: JSON.stringify(fakeSub.toJSON()),
        platform: 'web',
        device_name: 'Chrome MacOS',
      });
    });

    it('menghapus token subscription dari backend saat unsubscribe', async () => {
      const spyDestroy = vi.spyOn(notificationsApi, 'destroyDevice').mockResolvedValue({
        data: { message: 'Device deleted' },
      });

      const fakeSub = {
        endpoint: 'https://push.example.com/sub/123',
        toJSON: () => ({
          endpoint: 'https://push.example.com/sub/123',
        }),
      } as any;

      const success = await removeSubscriptionFromBackend(fakeSub);

      expect(success).toBe(true);
      expect(spyDestroy).toHaveBeenCalledWith(JSON.stringify(fakeSub.toJSON()));
    });
  });

  describe('5. Target URL Sanitization & Open Redirect Prevention', () => {
    it('mengizinkan internal root-relative path yang valid', () => {
      expect(sanitizeTargetUrl('/portal/siswa/cbt')).toBe('/portal/siswa/cbt');
      expect(sanitizeTargetUrl('/portal/siswa/tugas?id=123#detail')).toBe('/portal/siswa/tugas?id=123#detail');
      expect(sanitizeTargetUrl('/portal/guru/presensi')).toBe('/portal/guru/presensi');
    });

    it('menolak skema eksternal dan berbahaya (javascript, data, vbscript, http/https asing)', () => {
      expect(sanitizeTargetUrl('javascript:alert(document.domain)')).toBe('/');
      expect(sanitizeTargetUrl('data:text/html,<script>alert(1)</script>')).toBe('/');
      expect(sanitizeTargetUrl('https://evil-attacker.com/phishing')).toBe('/');
      expect(sanitizeTargetUrl('http://attacker.com/login')).toBe('/');
    });

    it('menolak protocol-relative URLs (//attacker.com) dan backslash bypass (/\\attacker.com)', () => {
      expect(sanitizeTargetUrl('//evil.com')).toBe('/');
      expect(sanitizeTargetUrl('/\\evil.com')).toBe('/');
      expect(sanitizeTargetUrl('/path\\with\\backslash')).toBe('/');
    });

    it('menolak control characters dan carriage returns', () => {
      expect(sanitizeTargetUrl('/portal/siswa\r\nHeader: Value')).toBe('/');
      expect(sanitizeTargetUrl('/portal/siswa\x00')).toBe('/');
    });

    it('mengembalikan pathname dari same-origin URL', () => {
      expect(sanitizeTargetUrl('http://localhost:3000/portal/siswa/cbt', 'http://localhost:3000')).toBe('/portal/siswa/cbt');
    });

    it('mengembalikan default fallback "/" jika input null, undefined, atau string kosong', () => {
      expect(sanitizeTargetUrl(null)).toBe('/');
      expect(sanitizeTargetUrl(undefined)).toBe('/');
      expect(sanitizeTargetUrl('')).toBe('/');
      expect(sanitizeTargetUrl('   ')).toBe('/');
    });
  });

  describe('6. GAP-11 Synchronized Push Preferences (Server & Offline LocalStorage)', () => {
    it('mengambil preferensi dari server saat mount dan menyimpannya ke localStorage', async () => {
      const { syncFetchNotificationPreferences, getStoredNotificationPreferences } = await import(
        '@/features/notifications/web-push'
      );

      const spyGet = vi.spyOn(notificationsApi, 'getPreferences').mockResolvedValue({
        data: [
          { notif_type: 'academic_announcement', channel_push: true, is_enabled: true },
          { notif_type: 'grade_report', channel_push: false, is_enabled: false },
        ],
      } as any);

      const prefs = await syncFetchNotificationPreferences('siswa');

      expect(spyGet).toHaveBeenCalled();
      expect(prefs.enabledTopics).toContain('announcement');
      expect(prefs.enabledTopics).not.toContain('grade');

      // Verifikasi tersimpan di localStorage
      const cached = getStoredNotificationPreferences('siswa');
      expect(cached.enabledTopics).toEqual(prefs.enabledTopics);
    });

    it('fallback ke localStorage saat server tidak dapat diakses (offline)', async () => {
      const {
        syncFetchNotificationPreferences,
        saveStoredNotificationPreferences,
      } = await import('@/features/notifications/web-push');

      vi.spyOn(notificationsApi, 'getPreferences').mockRejectedValue(new Error('Network Offline'));

      saveStoredNotificationPreferences({
        role: 'guru',
        enabledTopics: ['schedule_change', 'leave_request'],
        soundEnabled: true,
        quietHoursEnabled: false,
      });

      const prefs = await syncFetchNotificationPreferences('guru');

      expect(prefs.enabledTopics).toContain('schedule_change');
      expect(prefs.enabledTopics).toContain('leave_request');
    });

    it('menyimpan preferensi ke server dan meng-cache ke localStorage', async () => {
      const {
        syncSaveNotificationPreferences,
        getStoredNotificationPreferences,
      } = await import('@/features/notifications/web-push');

      const spyPut = vi.spyOn(notificationsApi, 'updatePreferences').mockResolvedValue({
        data: { message: 'Updated' },
      } as any);

      const toSave: NotificationPreferenceSettings = {
        role: 'ortu',
        enabledTopics: ['attendance', 'report_card'],
        soundEnabled: true,
        quietHoursEnabled: true,
        quietHoursStart: '22:00',
        quietHoursEnd: '05:00',
      };

      const success = await syncSaveNotificationPreferences(toSave);

      expect(success).toBe(true);
      expect(spyPut).toHaveBeenCalledWith({
        preferences: expect.arrayContaining([
          expect.objectContaining({ topic: 'attendance', is_enabled: true }),
          expect.objectContaining({ topic: 'announcement', is_enabled: false }),
        ]),
      });

      const cached = getStoredNotificationPreferences('ortu');
      expect(cached.enabledTopics).toEqual(['attendance', 'report_card']);
    });
  });
});

