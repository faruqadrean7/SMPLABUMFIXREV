export interface NotificationItem {
  id: string;
  user_id: string;
  title: string;
  body: string;
  notif_type?: string;
  data?: Record<string, unknown>;
  read_at?: string | null;
  created_at: string;
  updated_at?: string;
}

export interface NotificationMeta {
  page: number;
  limit: number;
  total: number;
  totalPages: number;
  unread_count: number;
}

export interface NotificationListResponse {
  data: NotificationItem[];
  meta: NotificationMeta;
}

export interface BroadcastNotificationPayload {
  target_type: 'all' | 'role' | 'class' | 'users';
  target_role?: string;
  target_class_id?: string;
  user_ids?: string[];
  title: string;
  body: string;
  notif_type?: string;
  data?: Record<string, unknown>;
}

export interface BroadcastResult {
  dispatched_count: number;
  message?: string;
}

export interface RegisterDevicePayload {
  token: string;
  platform: 'fcm' | 'apns' | 'web';
  device_name?: string;
}

export type PortalRoleTopic = 'siswa' | 'ortu' | 'guru' | 'staf' | 'admin';

export type NotificationTopic =
  | 'announcement'
  | 'assignment'
  | 'teaching_material'
  | 'cbt_exam'
  | 'grade'
  | 'attendance'
  | 'report_card'
  | 'character_note'
  | 'early_warning'
  | 'schedule_change'
  | 'leave_request'
  | 'salary_slip'
  | 'sarpras_ticket'
  | 'school_facility';

export interface NotificationTopicOption {
  id: NotificationTopic;
  label: string;
  description: string;
  defaultEnabled: boolean;
}

export interface NotificationPreferenceSettings {
  role: PortalRoleTopic;
  enabledTopics: NotificationTopic[];
  soundEnabled: boolean;
  quietHoursEnabled: boolean;
  quietHoursStart?: string;
  quietHoursEnd?: string;
}
