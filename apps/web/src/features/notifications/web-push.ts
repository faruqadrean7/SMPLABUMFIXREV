import { notificationsApi } from './notifications-api';

/**
 * Sanitizes and validates target URLs for web push notifications to prevent open redirect attacks.
 * Rejects control characters, backslashes, protocol-relative '//', and non-origin external URLs.
 */
export function sanitizeTargetUrl(urlInput?: string | null, baseOrigin?: string): string {
  if (!urlInput || typeof urlInput !== 'string') {
    return '/';
  }

  const trimmed = urlInput.trim();

  // Reject control characters, newlines, tabs, and backslashes
  if (/[\x00-\x1F\x7F\\]/.test(trimmed)) {
    return '/';
  }

  // Reject protocol-relative URLs (//evil.com)
  if (trimmed.startsWith('//')) {
    return '/';
  }

  const origin = baseOrigin || (typeof window !== 'undefined' ? window.location.origin : 'http://localhost');

  // Reject dangerous schemes or external origins
  if (/^[a-zA-Z][a-zA-Z0-9+.-]*:/.test(trimmed)) {
    try {
      const parsed = new URL(trimmed);
      if (parsed.origin === origin) {
        return parsed.pathname + parsed.search + parsed.hash;
      }
    } catch {
      // Invalid URL
    }
    return '/';
  }

  // Allow safe root-relative path
  if (trimmed.startsWith('/')) {
    return trimmed;
  }

  return '/';
}

/**
 * Utility to convert a base64 string to a Uint8Array.
 * Required for PushManager applicationServerKey conversion from VAPID public key.
 */
export function urlBase64ToUint8Array(base64String: string): Uint8Array {
  const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
  const base64 = (base64String + padding)
    .replace(/-/g, '+')
    .replace(/_/g, '/');

  const rawData = atob(base64);
  const outputArray = new Uint8Array(rawData.length);

  for (let i = 0; i < rawData.length; ++i) {
    outputArray[i] = rawData.charCodeAt(i);
  }
  return outputArray;
}

/**
 * Checks whether the current browser supports Service Workers, PushManager, and Notifications.
 */
export function isPushNotificationSupported(): boolean {
  if (typeof window === 'undefined') {
    return false;
  }

  return (
    'serviceWorker' in navigator &&
    'PushManager' in window &&
    'Notification' in window
  );
}

/**
 * Returns current browser notification permission status.
 */
export function getNotificationPermission(): NotificationPermission | 'unsupported' {
  if (typeof window === 'undefined' || !('Notification' in window)) {
    return 'unsupported';
  }

  return Notification.permission;
}

/**
 * Requests user permission to receive desktop / mobile browser notifications.
 */
export async function requestNotificationPermission(): Promise<NotificationPermission> {
  if (!isPushNotificationSupported()) {
    return 'denied';
  }

  try {
    return await Notification.requestPermission();
  } catch (error) {
    console.error('Failed to request notification permission:', error);
    return 'denied';
  }
}

/**
 * Registers the service worker responsible for receiving push notifications.
 */
export async function registerServiceWorker(
  swPath = '/sw.js'
): Promise<ServiceWorkerRegistration | null> {
  if (!isPushNotificationSupported()) {
    return null;
  }

  try {
    const registration = await navigator.serviceWorker.register(swPath, {
      scope: '/',
    });
    return registration;
  } catch (error) {
    console.error('Failed to register service worker for web push:', error);
    return null;
  }
}

/**
 * Retrieves existing push subscription if already subscribed.
 */
export async function getExistingSubscription(
  registration: ServiceWorkerRegistration
): Promise<PushSubscription | null> {
  try {
    return await registration.pushManager.getSubscription();
  } catch (error) {
    console.error('Failed to check existing push subscription:', error);
    return null;
  }
}

/**
 * Subscribes the client browser to the Push Service using VAPID public key.
 */
export async function subscribeToWebPush(
  registration: ServiceWorkerRegistration,
  vapidPublicKey: string
): Promise<PushSubscription | null> {
  if (!vapidPublicKey || vapidPublicKey.trim() === '') {
    console.warn('VAPID public key is not configured. Web push subscription skipped.');
    return null;
  }

  try {
    const convertedKey = urlBase64ToUint8Array(vapidPublicKey);
    const subscription = await registration.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: convertedKey as unknown as BufferSource,
    });

    return subscription;
  } catch (error) {
    console.error('Failed to subscribe to web push:', error);
    return null;
  }
}

/**
 * Unsubscribes the client browser from push notifications.
 */
export async function unsubscribeFromWebPush(
  registration: ServiceWorkerRegistration
): Promise<boolean> {
  try {
    const subscription = await registration.pushManager.getSubscription();
    if (subscription) {
      return await subscription.unsubscribe();
    }
    return true;
  } catch (error) {
    console.error('Failed to unsubscribe from web push:', error);
    return false;
  }
}

/**
 * Registers / syncs the web push subscription token with backend device registry.
 */
export async function syncSubscriptionWithBackend(
  subscription: PushSubscription,
  deviceName = 'Browser Web'
): Promise<boolean> {
  try {
    const serializedToken = JSON.stringify(subscription.toJSON());
    await notificationsApi.registerDevice({
      token: serializedToken,
      platform: 'web',
      device_name: deviceName,
    });
    return true;
  } catch (error) {
    console.error('Failed to sync web push token with backend:', error);
    return false;
  }
}

/**
 * Removes the push subscription from backend device registry.
 */
export async function removeSubscriptionFromBackend(
  subscription: PushSubscription
): Promise<boolean> {
  try {
    const serializedToken = JSON.stringify(subscription.toJSON());
    await notificationsApi.destroyDevice(serializedToken);
    return true;
  } catch (error) {
    console.error('Failed to remove web push token from backend:', error);
    return false;
  }
}

// =============================================================================
// ROLE-BASED NOTIFICATION TOPICS & CANONICAL DEEP LINKS (FASE 6)
// =============================================================================

import type {
  PortalRoleTopic,
  NotificationTopic,
  NotificationTopicOption,
  NotificationPreferenceSettings,
} from './types';

export type {
  PortalRoleTopic,
  NotificationTopic,
  NotificationTopicOption,
  NotificationPreferenceSettings,
};

export const ROLE_NOTIFICATION_TOPICS: Record<PortalRoleTopic, NotificationTopicOption[]> = {
  siswa: [
    { id: 'announcement', label: 'Pengumuman Sekolah', description: 'Edaran resmi, libur, dan kegiatan kesiswaan', defaultEnabled: true },
    { id: 'assignment', label: 'Tugas & PR Baru', description: 'Pemberitahuan tugas yang diberikan oleh guru mata pelajaran', defaultEnabled: true },
    { id: 'teaching_material', label: 'Materi & Modul Baru', description: 'Unggahan modul, video, atau bahan bacaan kelas', defaultEnabled: true },
    { id: 'cbt_exam', label: 'Jadwal & Pengingat CBT', description: 'Pengingat sesi ujian dan paket soal yang siap dikerjakan', defaultEnabled: true },
    { id: 'grade', label: 'Pembaruan Nilai', description: 'Hasil penilaian tugas formatif, sumatif, dan PTS', defaultEnabled: true },
  ],
  ortu: [
    { id: 'announcement', label: 'Pengumuman Wali Murid', description: 'Edaran komite, kegiatan sekolah, dan pertemuan wali', defaultEnabled: true },
    { id: 'attendance', label: 'Presensi Harian Ananda', description: 'Notifikasi kehadiran, izin, sakit, dan peringatan alpa', defaultEnabled: true },
    { id: 'report_card', label: 'Penerbitan Rapor', description: 'Informasi rilis rapor bulanan dan e-rapor semester', defaultEnabled: true },
    { id: 'character_note', label: 'Catatan Karakter', description: 'Perkembangan profil pelajar Pancasila & pembiasaan diri', defaultEnabled: true },
    { id: 'early_warning', label: 'Peringatan Dini Akademik/BK', description: 'Pemberitahuan dini jika ananda memerlukan pendampingan belajar', defaultEnabled: true },
  ],
  guru: [
    { id: 'announcement', label: 'Pengumuman Manajemen', description: 'Edaran dinas, kurikulum, dan manajemen pimpinan', defaultEnabled: true },
    { id: 'schedule_change', label: 'Perubahan Jadwal Mengajar', description: 'Notifikasi penyesuaian jam KBM atau jadwal pengganti', defaultEnabled: true },
    { id: 'leave_request', label: 'Status Izin / Cuti', description: 'Persetujuan atau penolakan pengajuan izin oleh pimpinan', defaultEnabled: true },
    { id: 'salary_slip', label: 'Slip Gaji & Tunjangan', description: 'Pemberitahuan penerbitan slip honorarium/gaji bulanan', defaultEnabled: true },
  ],
  staf: [
    { id: 'announcement', label: 'Edaran Operasional TU', description: 'Informasi administrasi dan agenda kerja sekolah', defaultEnabled: true },
    { id: 'sarpras_ticket', label: 'Tiket Kerusakan Fasilitas', description: 'Penugasan tiket helpdesk dan perbaikan sarana', defaultEnabled: true },
    { id: 'leave_request', label: 'Status Izin / Cuti', description: 'Persetujuan izin dinas atau cuti operasional staf', defaultEnabled: true },
    { id: 'salary_slip', label: 'Slip Gaji Bulanan', description: 'Pemberitahuan penerbitan rincian gaji tenaga kependidikan', defaultEnabled: true },
  ],
  admin: [
    { id: 'announcement', label: 'Notifikasi Sistem', description: 'Status sinkronisasi data dan pengumuman global', defaultEnabled: true },
    { id: 'early_warning', label: 'Kasus Kritis BK & Insiden', description: 'Atensi pimpinan pada kasus konseling atau sarpras kritis', defaultEnabled: true },
  ],
};

/**
 * Memetakan jenis topik dan role pengguna ke rute deep-link kanonis portal
 */
export function resolveNotificationDeepLink(
  topic?: NotificationTopic | string | null,
  role?: PortalRoleTopic | string | null,
  resourceId?: string | null
): string {
  // Portal kini aplikasi terpisah (apps/portal). Origin portal via env;
  // rute mengikuti struktur apps/portal (/siswa, /ortu, /guru), tanpa prefiks /portal.
  const B = process.env.NEXT_PUBLIC_PORTAL_URL || '';
  const inbox = role === 'ortu' || role === 'guru' ? `${B}/${role}/notifikasi` : `${B}/siswa/notifikasi`;
  if (!topic) return inbox;

  switch (topic) {
    case 'assignment':
      return `${B}/siswa/tugas`;
    case 'cbt_exam':
      return resourceId ? `${B}/siswa/cbt/${resourceId}` : `${B}/siswa/cbt`;
    case 'grade':
      return `${B}/siswa/nilai`;
    case 'teaching_material':
      return `${B}/siswa/tugas`;
    case 'attendance':
      return `${B}/ortu/presensi`;
    case 'report_card':
      return `${B}/ortu/nilai`;
    case 'character_note':
      return `${B}/ortu/nilai`;
    case 'early_warning':
      return role === 'ortu' ? `${B}/ortu/dashboard` : '/admin/counseling';
    case 'schedule_change':
      return `${B}/guru/jadwal`;
    case 'leave_request':
      return `${B}/guru/perizinan`;
    case 'salary_slip':
      return `${B}/guru/dashboard`;
    case 'sarpras_ticket':
      return `${B}/guru/sarpras`;
    case 'announcement':
    default:
      return inbox;
  }
}

const PREF_STORAGE_PREFIX = 'sistlabum_push_prefs_';

/**
 * Mengambil preferensi notifikasi tersimpan (localStorage) dengan fallback default
 */
export function getStoredNotificationPreferences(role: PortalRoleTopic): NotificationPreferenceSettings {
  const defaultTopics = ROLE_NOTIFICATION_TOPICS[role]
    ? ROLE_NOTIFICATION_TOPICS[role].filter((t) => t.defaultEnabled).map((t) => t.id)
    : [];

  const defaultSettings: NotificationPreferenceSettings = {
    role,
    enabledTopics: defaultTopics,
    soundEnabled: true,
    quietHoursEnabled: false,
    quietHoursStart: '21:00',
    quietHoursEnd: '06:00',
  };

  if (typeof window === 'undefined') {
    return defaultSettings;
  }

  try {
    const raw = window.localStorage.getItem(`${PREF_STORAGE_PREFIX}${role}`);
    if (raw) {
      const parsed = JSON.parse(raw);
      return {
        ...defaultSettings,
        ...parsed,
        role,
      };
    }
  } catch {
    // Fallback default
  }

  return defaultSettings;
}

/**
 * Menyimpan preferensi notifikasi ke penyimpanan lokal browser
 */
export function saveStoredNotificationPreferences(
  prefs: NotificationPreferenceSettings
): boolean {
  if (typeof window === 'undefined') {
    return false;
  }

  try {
    window.localStorage.setItem(`${PREF_STORAGE_PREFIX}${prefs.role}`, JSON.stringify(prefs));
    return true;
  } catch {
    return false;
  }
}

/**
 * Mengecek apakah jenis topik notifikasi sedang di-opt-in oleh pengguna
 */
export function isTopicOptedIn(role: PortalRoleTopic, topic: NotificationTopic): boolean {
  const prefs = getStoredNotificationPreferences(role);
  return prefs.enabledTopics.includes(topic);
}

/**
 * GAP-11: Mengambil preferensi notifikasi dari server saat mount,
 * dengan localStorage sebagai cache offline fallback.
 */
export async function syncFetchNotificationPreferences(
  role: PortalRoleTopic
): Promise<NotificationPreferenceSettings> {
  const cached = getStoredNotificationPreferences(role);

  try {
    const res = await notificationsApi.getPreferences();
    const serverList = res.data;

    if (Array.isArray(serverList)) {
      const serverActiveTopics = new Set<string>();
      for (const item of serverList) {
        const isEnabled = item.enabled ?? item.is_enabled;
        const pushEnabled = item.channel_push ?? true;
        if (isEnabled && pushEnabled) {
          serverActiveTopics.add(item.topic || item.notif_type);
        }
      }

      const relevantOptions = ROLE_NOTIFICATION_TOPICS[role] || [];
      const updatedTopics = relevantOptions
        .filter((opt) => {
          if (serverActiveTopics.has(opt.id)) return true;
          if (opt.id === 'announcement' && serverActiveTopics.has('academic_announcement')) return true;
          if (opt.id === 'attendance' && serverActiveTopics.has('attendance_alert')) return true;
          if (opt.id === 'grade' && serverActiveTopics.has('grade_report')) return true;
          if (opt.id === 'report_card' && serverActiveTopics.has('grade_report')) return true;
          if (opt.id === 'cbt_exam' && serverActiveTopics.has('academic_announcement')) return true;
          if (opt.id === 'assignment' && serverActiveTopics.has('academic_announcement')) return true;
          if (opt.id === 'schedule_change' && serverActiveTopics.has('teacher_agenda')) return true;
          if (opt.id === 'early_warning' && serverActiveTopics.has('counseling_update')) return true;
          if (opt.id === 'character_note' && serverActiveTopics.has('counseling_update')) return true;
          return false;
        })
        .map((opt) => opt.id);

      const mergedSettings: NotificationPreferenceSettings = {
        ...cached,
        enabledTopics: updatedTopics,
      };

      saveStoredNotificationPreferences(mergedSettings);
      return mergedSettings;
    }
  } catch {
    // Offline atau error: fallback ke cache lokal
  }

  return cached;
}

/**
 * GAP-11: Menyimpan preferensi notifikasi ke server dan cache offline lokal.
 */
export async function syncSaveNotificationPreferences(
  prefs: NotificationPreferenceSettings
): Promise<boolean> {
  // 1. Simpan segera ke localStorage sebagai cache offline
  const localSaved = saveStoredNotificationPreferences(prefs);

  // 2. Sinkronisasikan ke endpoint backend /me/notification-preferences
  try {
    const relevantOptions = ROLE_NOTIFICATION_TOPICS[prefs.role] || [];
    const serverPayload = relevantOptions.map((opt) => {
      const isOptedIn = prefs.enabledTopics.includes(opt.id);
      return {
        topic: opt.id,
        notif_type: opt.id,
        is_enabled: isOptedIn,
        enabled: isOptedIn,
        channel_push: isOptedIn,
      };
    });

    await notificationsApi.updatePreferences({
      preferences: serverPayload,
    });
    return true;
  } catch {
    // Jika offline/gagal, cache lokal tetap aman
    return localSaved;
  }
}


