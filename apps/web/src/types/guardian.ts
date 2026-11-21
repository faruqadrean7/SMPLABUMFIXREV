/**
 * Types & Interfaces untuk Portal Orang Tua / Wali Murid
 * SISTLABUM - Portal Sekolah Satu Pintu
 */

export interface ChildScheduleItem {
  id: string;
  timeRange: string;
  subject: string;
  teacher: string;
  room: string;
  status: 'completed' | 'current' | 'upcoming';
  statusLabel: string;
  topic: string;
}

export interface GuardianChildProfile {
  id: string;
  name: string;
  nis: string;
  nisn: string;
  class_name: string;
  grade_level: string;
  curriculum_phase: string;
  homeroom_teacher: string;
  homeroom_nip: string;
  homeroom_phone: string;
  homeroom_avatar?: string;
  avatar_url?: string;
  initials: string;
  gender: 'L' | 'P';
  gate_attendance_today: {
    status: 'hadir' | 'terlambat' | 'sakit' | 'izin' | 'alpa';
    status_label: string;
    tap_time: string;
    location: string;
    note: string;
  };
  attendance_recap: {
    hadir: number;
    sakit: number;
    izin: number;
    alpa: number;
    terlambat: number;
    rate: number;
  };
  grade_average: number;
  points_balance: number;
  points_prestasi: number;
  points_pelanggaran: number;
  admin_fee_status: 'Lunas / Bebas Tanggungan' | 'Menunggu Pembayaran' | 'Menunggu Verifikasi TU';
  fee_summary: {
    title: string;
    period: string;
    amount: string;
    status: 'Lunas' | 'Menunggu Pembayaran' | 'Verifikasi TU';
    detail_note: string;
    receipt_or_due: string;
    officer: string;
  };
}

export interface GuardianProfile {
  id: string;
  name: string;
  avatar_url?: string;
  phone: string;
  email: string;
  address: string;
  occupation: string;
  children: GuardianChildProfile[];
}

export interface AttendanceCalendarDay {
  date: string;
  day_number: number;
  day_name: string;
  status: 'hadir' | 'sakit' | 'izin' | 'alpa' | 'terlambat' | 'libur';
  tap_in?: string;
  tap_out?: string;
  note?: string;
}

export interface LeaveRequestItem {
  id: string;
  student_id: string;
  student_name: string;
  leave_type: 'sakit' | 'izin';
  start_date: string;
  end_date: string;
  reason: string;
  attachment_name?: string;
  status: 'disetujui' | 'menunggu' | 'ditolak';
  submitted_at: string;
  approved_by?: string;
  approval_note?: string;
}

export interface ChildSubjectGrade {
  subject_code: string;
  subject_name: string;
  teacher_name: string;
  kktp: number;
  formative_avg: number | null;
  summative_avg: number | null;
  sts_score: number | null;
  final_score: number | null;
  predicate: 'A' | 'B+' | 'B' | 'C' | 'D' | null;
  status: 'Tuntas' | 'Perlu Pendampingan';
  competency_desc: string;
}

export interface ChildPointItem {
  id: string;
  student_id: string;
  point_date: string;
  kind: 'prestasi' | 'pelanggaran';
  title: string;
  category: string;
  points: number;
  recorder_name: string;
  note: string;
}

export type SchoolFeeStatus =
  | 'Lunas'
  | 'Belum Dibayar'
  | 'Menunggu Verifikasi'
  | 'Jatuh Tempo'
  | 'Dibayar Sebagian'
  | 'Dibatalkan'
  | 'Lunas / Bebas Tanggungan'
  | 'Dalam Proses Administrasi TU';

export interface SchoolFeeItem {
  id: string;
  student_id: string;
  item_name: string;
  period_label: string;
  nominal_text: string;
  status: SchoolFeeStatus;
  verified_date: string;
  receipt_number: string;
  cashier_name: string;
  notes: string;
  total_amount?: number;
  paid_amount?: number;
  remaining_amount?: number;
  due_date?: string;
  raw_status?: string;
}

export interface GuardianCounselingItem {
  id: string;
  student_id: string;
  student_name: string;
  topic: string;
  target_role: 'Wali Kelas' | 'Guru BK';
  target_name: string;
  requested_date: string;
  status: 'menunggu' | 'terjadwal' | 'selesai';
  location: string;
  notes: string;
}

export interface GuardianAnnouncement {
  id: string;
  title: string;
  date_str: string;
  category: 'Pertemuan Komite' | 'Akademik' | 'Administrasi';
  content: string;
  badge_color: string;
  is_pinned?: boolean;
}
