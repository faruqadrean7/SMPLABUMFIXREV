/**
 * Types & Interfaces untuk Portal Siswa
 * SISTLABUM - Portal Sekolah Satu Pintu
 */

export interface StudentProfile {
  id: string;
  name: string;
  nis: string;
  nisn: string;
  class_id: string;
  class_name: string;
  academic_year: string;
  guardian_name: string;
  guardian_phone: string;
  gender: 'L' | 'P';
  avatar_url?: string;
  homeroom_teacher: string;
  homeroom_nip: string;
  homeroom_phone: string;
  homeroom_avatar?: string;
  counselor_name?: string;
  counselor_nip?: string;
  counselor_phone?: string;
  counselor_avatar?: string;
  gate_attendance_today: {
    status: 'hadir' | 'terlambat' | 'sakit' | 'izin' | 'alpa';
    tap_time: string;
    location: string;
  };
}

export interface StudentScheduleItem {
  id: string;
  day: 'Senin' | 'Selasa' | 'Rabu' | 'Kamis' | 'Jumat';
  period: string;
  time_range: string;
  subject_name: string;
  subject_code: string;
  teacher_name: string;
  teacher_avatar_url?: string;
  room_name: string;
}

export interface StudentCBTExamItem {
  id: string;
  title: string;
  subject_name: string;
  subject_code: string;
  teacher_name: string;
  teacher_avatar_url?: string;
  date_str: string;
  time_range: string;
  duration_minutes: number;
  total_questions: number;
  kktp: number;
  room_name: string;
  token_required: string;
  status: 'aktif' | 'terjadwal' | 'selesai';
  score?: number | null;
  is_passed?: boolean;
}

export interface CBTQuestionDetail {
  id: string;
  number: number;
  type: 'pg' | 'pg_kompleks' | 'benar_salah' | 'menjodohkan' | 'isian' | 'uraian';
  category: 'LOTS' | 'MOTS' | 'HOTS';
  points: number;
  prompt: string;
  formula?: string;
  has_audio?: boolean;
  audio_title?: string;
  image_url?: string;
  options?: { key: string; label: string }[];
  statements?: { text: string; correct_val?: boolean }[];
  matching_pairs?: { premise: string; target: string; options: string[] }[];
}

export interface StudentTaskItem {
  id: string;
  title: string;
  subject_name: string;
  teacher_name: string;
  teacher_avatar_url?: string;
  due_date_str: string;
  days_left: number;
  max_score: number;
  status: 'aktif' | 'dikumpulkan' | 'dinilai';
  submission?: {
    file_name?: string;
    submitted_at: string;
    score?: number;
    kktp_passed?: boolean;
    feedback?: string;
    drive_link?: string;
  };
}

export interface StudentSubjectGrade {
  subject_code: string;
  subject_name: string;
  teacher_name: string;
  kktp: number;
  f1: number;
  f2: number;
  f3: number;
  slm: number;
  sts: number;
  final_score: number;
  status: 'Tuntas' | 'Perlu Bimbingan';
}

export interface StudentPointItem {
  id: string;
  student_id: string;
  kind: 'prestasi' | 'pelanggaran';
  title: string;
  category: string;
  point_date: string;
  points: number;
  note: string;
  recorder_name: string;
}

export interface StudentPointsData {
  totalPrestasi: number;
  totalPelanggaran: number;
  balancePoints: number;
  statusDisiplin: string;
  achievementsCount: number;
  items: StudentPointItem[];
}

export interface MyExtracurricularItem {
  id: string;
  memberId: string;
  name: string;
  coach: string;
  schedule: string;
  room: string;
  role: string;
  attendance_rate: string;
  badgeColor: string;
  finalGrade?: string;
}

export interface AvailableExtracurricularItem {
  id: string;
  name: string;
  coach: string;
  schedule: string;
  quota: string;
  category: string;
  description: string;
}

export interface StudentExtracurricularsData {
  myExtracurriculars: MyExtracurricularItem[];
  availableExtracurriculars: AvailableExtracurricularItem[];
}

export interface StudentPointLogItem {
  id: string;
  date_str: string;
  kind: 'prestasi' | 'pelanggaran';
  type_name: string;
  points: number;
  category: string;
  recorder_name: string;
  note: string;
}

export interface StudentCounselingItem {
  id: string;
  topic: string;
  category: 'Akademik' | 'Sosial & Teman' | 'Pengembangan Diri' | 'Keluarga';
  request_date: string;
  preferred_schedule: string;
  counselor_name: string;
  counselor_avatar_url?: string;
  status: 'menunggu' | 'terjadwal' | 'selesai';
  location?: string;
  notes?: string;
}

export interface StudentAnnouncementItem {
  id: string;
  title: string;
  date: string;
  category: string;
  content: string;
  author: string;
  author_avatar_url?: string;
}
