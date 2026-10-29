export interface AcademicClass {
  id: string;
  name: string;
  grade_level: string;
  academic_year: string;
  total_students?: number;
}

export interface AcademicSubject {
  id: string;
  name: string;
  code: string;
  category: 'wajib' | 'peminatan' | 'muatan_lokal';
}

export interface TeacherProfile {
  id: string;
  name: string;
  nip: string;
  avatar_url?: string;
  subjects?: string[];
}

export interface FileAttachment {
  id: string;
  object_key: string;
  filename: string;
  mime_type: string;
  size_bytes: number;
  url?: string;
}

export type MaterialCategory = 'modul_ajar' | 'atp' | 'p5' | 'slide_tayang';

export interface LearningMaterial {
  id: string;
  class_id: string;
  subject_id: string;
  teacher_id: string;
  title: string;
  description?: string;
  file_id?: string;
  external_url?: string;
  category: MaterialCategory;
  version: string;
  created_at: string;
  updated_at: string;
  class?: AcademicClass;
  subject?: AcademicSubject;
  teacher?: TeacherProfile;
  file?: FileAttachment;
}

export interface Assignment {
  id: string;
  class_id: string;
  subject_id: string;
  teacher_id: string;
  title: string;
  description?: string;
  file_id?: string;
  due_at: string;
  max_score: number;
  grade_component_id?: string;
  grade_component_name?: string;
  is_locked: boolean;
  allow_late: boolean;
  created_at: string;
  updated_at: string;
  submissions_count?: number;
  total_students?: number;
  reviewed_count?: number;
  class?: AcademicClass;
  subject?: AcademicSubject;
  teacher?: TeacherProfile;
  file?: FileAttachment;
  my_submission?: AssignmentSubmission;
}

export interface AssignmentSubmission {
  id: string;
  assignment_id: string;
  student_id: string;
  file_id?: string;
  submission_url?: string;
  notes?: string;
  submitted_at: string;
  is_late: boolean;
  score?: number | null;
  feedback?: string | null;
  graded_at?: string | null;
  student: {
    id: string;
    name: string;
    nis: string;
    nisn?: string;
    avatar_url?: string;
    class_id: string;
  };
  file?: FileAttachment;
}

// ─── BE-ACD-02 & BE-ACD-03: Jurnal Mengajar & Presensi Sesi ───
export type AttendanceStatus = 'hadir' | 'sakit' | 'izin' | 'alpa' | 'terlambat';

export interface StudentRosterItem {
  id: string;
  nis: string;
  name: string;
  gender: 'L' | 'P';
  status: AttendanceStatus;
  note?: string;
  avatar_url?: string;
}

export interface TeachingJournal {
  id: string;
  schedule_id: string;
  teacher_id: string;
  class_id: string;
  subject_id: string;
  meeting_number: number;
  session_date: string; // YYYY-MM-DD
  time_range: string; // e.g., '07:30 - 09:00 WIB'
  room_name: string; // e.g., 'Lab Komputer 1'
  topic: string;
  method: string;
  notes?: string;
  attendance_summary: {
    total: number;
    hadir: number;
    sakit: number;
    izin: number;
    alpa: number;
    terlambat: number;
    attendance_rate: number; // percentage
  };
  attendances?: {
    student_id: string;
    status: AttendanceStatus;
    note?: string;
  }[];
  class?: AcademicClass;
  subject?: AcademicSubject;
  created_at: string;
}

export interface TeachingSchedule {
  id: string;
  class_id: string;
  subject_id: string;
  day_name: 'Senin' | 'Selasa' | 'Rabu' | 'Kamis' | 'Jumat';
  start_time: string;
  end_time: string;
  room_name: string;
  class_name: string;
  subject_name: string;
}

// ─── BE-GRD-01: Buku Nilai & Komponen Nilai ───
export interface GradeComponent {
  id: string;
  name: string;
  code: string;
  category: 'formatif' | 'sumatif_materi' | 'sumatif_tengah' | 'sumatif_akhir';
  weight_percent: number;
  kktp: number;
}

export interface StudentGradeRow {
  student_id: string;
  nis: string;
  name: string;
  gender: 'L' | 'P';
  avatar_url?: string;
  scores: Record<string, number | null>;
  final_score: number;
  is_tuntas: boolean;
  notes?: string;
}

export interface GradebookPeriod {
  id: string;
  class_id?: string;
  subject_id?: string;
  name?: string;
  academic_year?: string;
  semester?: 'Ganjil' | 'Genap' | string;
  is_locked: boolean;
  locked_at?: string | null;
  locked_by?: string | null;
}

// ─── BE-CBT-01 & BE-CBT-02: Bank Soal & Pelaksanaan CBT ───
export type QuestionType = 'pg' | 'pg_kompleks' | 'benar_salah' | 'menjodohkan' | 'uraian';
export type CognitiveLevel = 'LOTS' | 'MOTS' | 'HOTS';

export interface CBTQuestion {
  id: string;
  subject_id: string;
  topic: string;
  type: QuestionType;
  cognitive_level: CognitiveLevel;
  difficulty: 'mudah' | 'sedang' | 'sulit';
  question_text: string;
  options?: { id: string; text: string; is_correct?: boolean }[];
  correct_answer?: string;
  explanation?: string;
  points: number;
  is_locked: boolean;
}

export interface CBTExam {
  id: string;
  title: string;
  type: 'harian' | 'sts' | 'sas' | 'remedial';
  class_id: string;
  subject_id: string;
  date: string;
  start_time: string;
  duration_minutes: number;
  kktp: number;
  room_token: string;
  total_questions: number;
  total_participants: number;
  submitted_count: number;
  status: 'draft' | 'aktif' | 'selesai';
}

