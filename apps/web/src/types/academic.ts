/**
 * Definisi tipe data modul Akademik & KBM SISTLABUM
 * (ADM-ACD-01 s/d ADM-ACD-06)
 */

import {
  SemesterItem,
  SchoolClassItem,
  SubjectItem,
  TeacherItem,
  StudentItem,
} from './master';

// ============================================================================
// ADM-ACD-01: Jadwal Pelajaran & Time Slots
// ============================================================================

export type DayOfWeek = 1 | 2 | 3 | 4 | 5 | 6 | 7;

export const DAY_OF_WEEK_LABELS: Record<DayOfWeek, string> = {
  1: 'Senin',
  2: 'Selasa',
  3: 'Rabu',
  4: 'Kamis',
  5: 'Jumat',
  6: 'Sabtu',
  7: 'Minggu',
};

export interface TimeSlotItem {
  id: string;
  school_id?: string | null;
  name: string; // Jam ke-1, Jam ke-2
  start_time: string; // HH:mm
  end_time: string; // HH:mm
  day_type: string; // reguler | jumat | khusus
  created_at?: string;
  updated_at?: string;
}

export interface TimeSlotFormValues {
  name: string;
  start_time: string;
  end_time: string;
  day_type?: string;
}

export interface RoomItem {
  id: string;
  school_id?: string | null;
  code: string;
  name: string;
  room_type: string;
  capacity?: number;
  created_at?: string;
  updated_at?: string;
}

export interface ScheduleItem {
  id: string;
  semester_id: string;
  class_id: string;
  subject_id: string;
  teacher_id: string;
  room_id?: string | null;
  time_slot_id: string;
  day_of_week: DayOfWeek;
  created_at?: string;
  updated_at?: string;
  // Relationships loaded from backend
  semester?: SemesterItem;
  class?: SchoolClassItem;
  schoolClass?: SchoolClassItem;
  subject?: SubjectItem;
  teacher?: TeacherItem;
  room?: RoomItem | null;
  timeSlot?: TimeSlotItem;
}

export interface ScheduleFormValues {
  semester_id: string;
  class_id: string;
  subject_id: string;
  teacher_id: string;
  room_id?: string | null;
  time_slot_id: string;
  day_of_week: DayOfWeek;
}

export type ScheduleConflictType = 'CLASS_CONFLICT' | 'TEACHER_CONFLICT' | 'ROOM_CONFLICT';

export interface ScheduleConflict {
  type: ScheduleConflictType;
  message: string;
  schedule_id?: string;
}

export interface CheckConflictResult {
  has_conflict: boolean;
  conflicts: ScheduleConflict[];
}

export interface CheckConflictPayload {
  semester_id: string;
  class_id: string;
  teacher_id: string;
  room_id?: string | null;
  time_slot_id: string;
  day_of_week: DayOfWeek;
  exclude_schedule_id?: string;
}

export interface ScheduleFilters {
  semester_id?: string;
  class_id?: string;
  teacher_id?: string;
  room_id?: string;
  subject_id?: string;
  day_of_week?: DayOfWeek;
  all?: boolean;
  per_page?: number;
  page?: number;
}

// ============================================================================
// ADM-ACD-02: Jurnal Mengajar Guru
// ============================================================================

export interface JournalItem {
  id: string;
  schedule_id: string;
  teacher_id: string;
  session_date: string; // YYYY-MM-DD
  topic: string;
  method: string;
  notes?: string | null;
  attendances_count?: number;
  created_at?: string;
  updated_at?: string;
  // Relationships
  schedule?: ScheduleItem;
  teacher?: TeacherItem;
  attendances?: Array<{
    id: string;
    student_id: string;
    att_date: string;
    status: 'hadir' | 'izin' | 'sakit' | 'alpa' | 'terlambat';
    source: string;
    note?: string | null;
    student?: {
      id: string;
      full_name: string;
      nis: string;
      nisn?: string;
      user?: {
        id: string;
        username: string;
        email: string;
      };
    };
  }>;
}

export interface JournalFormValues {
  schedule_id: string;
  session_date: string;
  topic: string;
  method: string;
  notes?: string | null;
}

export interface JournalFilters {
  schedule_id?: string;
  class_id?: string;
  teacher_id?: string;
  subject_id?: string;
  start_date?: string;
  end_date?: string;
  per_page?: number;
  page?: number;
}

// ============================================================================
// ADM-ACD-03: Presensi KBM & Sesi Kelas
// ============================================================================

export type AttendanceStatus = 'hadir' | 'izin' | 'sakit' | 'alpa' | 'terlambat';
export type AttendanceSource = 'kelas' | 'gerbang';

export const ATTENDANCE_STATUS_LABELS: Record<AttendanceStatus, string> = {
  hadir: 'Hadir',
  izin: 'Izin',
  sakit: 'Sakit',
  alpa: 'Alpa',
  terlambat: 'Terlambat',
};

export const ATTENDANCE_STATUS_COLORS: Record<
  AttendanceStatus,
  { bg: string; text: string; border: string; ring: string }
> = {
  hadir: {
    bg: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    text: 'text-emerald-700',
    border: 'border-emerald-300',
    ring: 'focus:ring-emerald-500',
  },
  izin: {
    bg: 'bg-blue-50 text-blue-700 border-blue-200',
    text: 'text-blue-700',
    border: 'border-blue-300',
    ring: 'focus:ring-blue-500',
  },
  sakit: {
    bg: 'bg-amber-50 text-amber-700 border-amber-200',
    text: 'text-amber-700',
    border: 'border-amber-300',
    ring: 'focus:ring-amber-500',
  },
  alpa: {
    bg: 'bg-rose-50 text-rose-700 border-rose-200',
    text: 'text-rose-700',
    border: 'border-rose-300',
    ring: 'focus:ring-rose-500',
  },
  terlambat: {
    bg: 'bg-purple-50 text-purple-700 border-purple-200',
    text: 'text-purple-700',
    border: 'border-purple-300',
    ring: 'focus:ring-purple-500',
  },
};

export interface AttendanceItem {
  id: string;
  journal_id?: string | null;
  student_id: string;
  att_date: string;
  status: AttendanceStatus;
  source: AttendanceSource;
  note?: string | null;
  recorded_by?: string | null;
  created_at?: string;
  updated_at?: string;
  // Relationships
  student?: {
    id: string;
    full_name: string;
    nis: string;
    nisn?: string;
    gender?: 'L' | 'P';
    user?: {
      id: string;
      username: string;
      email: string;
    };
  };
  journal?: JournalItem;
  recorder?: {
    id: string;
    username: string;
    email: string;
  };
}

export interface AttendanceRecap {
  total_sessions: number;
  summary: {
    hadir: number;
    izin: number;
    sakit: number;
    alpa: number;
    terlambat: number;
  };
  attendance_rate: number;
  filters?: Record<string, unknown>;
}

export interface AttendanceFilters {
  class_id?: string;
  student_id?: string;
  journal_id?: string;
  start_date?: string;
  end_date?: string;
  status?: AttendanceStatus;
  source?: AttendanceSource;
  q?: string;
  per_page?: number;
  page?: number;
}

export interface BatchSessionAttendancePayload {
  journal_id: string;
  attendances: Array<{
    student_id: string;
    status: AttendanceStatus;
    note?: string | null;
  }>;
}

export interface DailyGateAttendancePayload {
  student_id: string;
  att_date: string;
  status: AttendanceStatus;
  note?: string | null;
}

// ============================================================================
// ADM-ACD-04: Izin & Sakit Siswa (Leave Requests)
// ============================================================================

export type LeaveType = 'sakit' | 'izin' | 'dispen';
export type LeaveStatus = 'menunggu' | 'disetujui' | 'ditolak';

export const LEAVE_TYPE_LABELS: Record<LeaveType, string> = {
  sakit: 'Sakit',
  izin: 'Izin',
  dispen: 'Dispensasi',
};

export const LEAVE_STATUS_LABELS: Record<LeaveStatus, string> = {
  menunggu: 'Menunggu Verifikasi',
  disetujui: 'Disetujui',
  ditolak: 'Ditolak',
};

export const LEAVE_STATUS_COLORS: Record<
  LeaveStatus,
  { bg: string; text: string; border: string; badge: string }
> = {
  menunggu: {
    bg: 'bg-amber-50 text-amber-700 border-amber-200',
    text: 'text-amber-700',
    border: 'border-amber-300',
    badge: 'bg-amber-100 text-amber-800 border-amber-200',
  },
  disetujui: {
    bg: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    text: 'text-emerald-700',
    border: 'border-emerald-300',
    badge: 'bg-emerald-100 text-emerald-800 border-emerald-200',
  },
  ditolak: {
    bg: 'bg-rose-50 text-rose-700 border-rose-200',
    text: 'text-rose-700',
    border: 'border-rose-300',
    badge: 'bg-rose-100 text-rose-800 border-rose-200',
  },
};

export const LEAVE_TYPE_COLORS: Record<LeaveType, string> = {
  sakit: 'bg-amber-100 text-amber-800 border-amber-200',
  izin: 'bg-blue-100 text-blue-800 border-blue-200',
  dispen: 'bg-purple-100 text-purple-800 border-purple-200',
};

export interface LeaveRequestItem {
  id: string;
  student_id: string;
  requested_by?: string | null;
  leave_type: LeaveType;
  start_date: string; // YYYY-MM-DD
  end_date: string; // YYYY-MM-DD
  reason: string;
  attachment_file_id?: string | null;
  status: LeaveStatus;
  decided_by?: string | null;
  decision_notes?: string | null;
  created_at?: string;
  updated_at?: string;
  student?: {
    id: string;
    full_name: string;
    nis: string;
    nisn?: string;
    gender?: 'L' | 'P';
    classes?: Array<{
      id: string;
      name: string;
    }>;
  };
  attachmentFile?: {
    id: string;
    bucket?: string;
    object_key: string;
    mime_type: string;
    size_bytes?: number;
  } | null;
  requester?: {
    id: string;
    username: string;
    email: string;
  } | null;
  decisionMaker?: {
    id: string;
    username: string;
    email: string;
  } | null;
}

export interface LeaveRequestFilters {
  class_id?: string;
  student_id?: string;
  status?: LeaveStatus;
  leave_type?: LeaveType;
  start_date?: string;
  end_date?: string;
  q?: string;
  per_page?: number;
  page?: number;
}

export interface StoreLeaveRequestPayload {
  student_id: string;
  leave_type: LeaveType;
  start_date: string;
  end_date: string;
  reason: string;
  attachment_file?: File;
  attachment_file_id?: string;
}

export interface DecideLeaveRequestPayload {
  status: 'disetujui' | 'ditolak';
  decision_notes?: string;
}

// ============================================================================
// ADM-ACD-05: Presensi Guru & Staf
// ============================================================================

export type StaffAttendanceStatus = 'hadir' | 'terlambat' | 'izin' | 'sakit' | 'alfa';

export const STAFF_ATTENDANCE_STATUS_LABELS: Record<StaffAttendanceStatus, string> = {
  hadir: 'Hadir',
  terlambat: 'Terlambat',
  izin: 'Izin',
  sakit: 'Sakit',
  alfa: 'Alfa',
};

export const STAFF_ATTENDANCE_STATUS_COLORS: Record<
  StaffAttendanceStatus,
  { bg: string; text: string; border: string; badge: string }
> = {
  hadir: {
    bg: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    text: 'text-emerald-700',
    border: 'border-emerald-300',
    badge: 'bg-emerald-100 text-emerald-800 border-emerald-200',
  },
  terlambat: {
    bg: 'bg-amber-50 text-amber-700 border-amber-200',
    text: 'text-amber-700',
    border: 'border-amber-300',
    badge: 'bg-amber-100 text-amber-800 border-amber-200',
  },
  izin: {
    bg: 'bg-blue-50 text-blue-700 border-blue-200',
    text: 'text-blue-700',
    border: 'border-blue-300',
    badge: 'bg-blue-100 text-blue-800 border-blue-200',
  },
  sakit: {
    bg: 'bg-purple-50 text-purple-700 border-purple-200',
    text: 'text-purple-700',
    border: 'border-purple-300',
    badge: 'bg-purple-100 text-purple-800 border-purple-200',
  },
  alfa: {
    bg: 'bg-rose-50 text-rose-700 border-rose-200',
    text: 'text-rose-700',
    border: 'border-rose-300',
    badge: 'bg-rose-100 text-rose-800 border-rose-200',
  },
};

export interface StaffAttendanceItem {
  id: string;
  user_id: string;
  att_date: string; // YYYY-MM-DD
  check_in?: string | null; // ISO datetime string
  check_out?: string | null; // ISO datetime string
  lat?: number | null;
  lng?: number | null;
  photo_file_id?: string | null;
  status: StaffAttendanceStatus;
  notes?: string | null;
  created_at?: string;
  updated_at?: string;
  user?: {
    id: string;
    username: string;
    email: string;
    teacher?: {
      id: string;
      full_name: string;
      nip: string;
      status: string;
    } | null;
    staff?: {
      id: string;
      full_name: string;
      nip: string;
      position?: string;
    } | null;
  };
  photoFile?: {
    id: string;
    object_key: string;
    mime_type: string;
    size_bytes?: number;
  } | null;
}

export interface StaffAttendanceSummary {
  total_recorded_days: number;
  summary: {
    hadir: number;
    terlambat: number;
    izin: number;
    sakit: number;
    alfa: number;
  };
  total_present: number;
  attendance_percentage: number;
  filters?: Record<string, unknown>;
}

export interface StaffAttendanceFilters {
  user_id?: string;
  date_from?: string;
  date_to?: string;
  status?: StaffAttendanceStatus;
  q?: string;
  per_page?: number;
  page?: number;
}

export interface CheckInStaffPayload {
  lat?: number;
  lng?: number;
  photo_file?: File;
  photo_file_id?: string;
  notes?: string;
}

export interface CheckOutStaffPayload {
  lat?: number;
  lng?: number;
  notes?: string;
}

export interface ManualStaffAttendancePayload {
  user_id: string;
  att_date: string;
  status: StaffAttendanceStatus;
  check_in?: string;
  check_out?: string;
  notes?: string;
}

export interface CorrectStaffAttendancePayload {
  status: StaffAttendanceStatus;
  check_in?: string;
  check_out?: string;
  reason: string;
}

export interface StaffAttendanceSettings {
  latitude: number;
  longitude: number;
  radius_meters: number;
  late_time: string;
}

// ============================================================================
// ADM-ACD-06: Bank Modul Ajar & Perangkat Pembelajaran
// ============================================================================

export type TeachingMaterialType = 'modul' | 'ATP' | 'silabus' | 'P5';

export const TEACHING_MATERIAL_TYPE_LABELS: Record<TeachingMaterialType, string> = {
  modul: 'Modul Ajar',
  ATP: 'Alur Tujuan Pembelajaran (ATP)',
  silabus: 'Silabus / Perangkat',
  P5: 'Modul Proyek P5',
};

export const TEACHING_MATERIAL_TYPE_COLORS: Record<
  TeachingMaterialType,
  { badge: string; bg: string; text: string }
> = {
  modul: {
    badge: 'bg-emerald-100 text-emerald-800 border-emerald-200',
    bg: 'bg-emerald-50',
    text: 'text-emerald-700',
  },
  ATP: {
    badge: 'bg-blue-100 text-blue-800 border-blue-200',
    bg: 'bg-blue-50',
    text: 'text-blue-700',
  },
  silabus: {
    badge: 'bg-purple-100 text-purple-800 border-purple-200',
    bg: 'bg-purple-50',
    text: 'text-purple-700',
  },
  P5: {
    badge: 'bg-amber-100 text-amber-800 border-amber-200',
    bg: 'bg-amber-50',
    text: 'text-amber-700',
  },
};

export interface GradeLevelItem {
  id: string;
  name: string;
  level: number;
  description?: string | null;
}

export interface TeachingMaterialItem {
  id: string;
  title: string;
  description?: string | null;
  material_type: TeachingMaterialType;
  grade_level_id?: string | null;
  subject_id?: string | null;
  uploaded_by: string;
  file_id: string;
  version: number;
  parent_id?: string | null;
  created_at?: string;
  updated_at?: string;
  gradeLevel?: GradeLevelItem | null;
  subject?: {
    id: string;
    name: string;
    code: string;
  } | null;
  uploader?: {
    id: string;
    username: string;
    email: string;
    teacher?: {
      id: string;
      full_name: string;
      nip?: string | null;
    } | null;
  } | null;
  file?: {
    id: string;
    object_key: string;
    mime_type: string;
    size_bytes?: number;
  } | null;
  parent?: TeachingMaterialItem | null;
  versions?: TeachingMaterialItem[];
}

export interface TeachingMaterialFilters {
  search?: string;
  material_type?: TeachingMaterialType;
  grade_level_id?: string;
  subject_id?: string;
  uploaded_by?: string;
  per_page?: number;
  page?: number;
}

export interface StoreTeachingMaterialPayload {
  title: string;
  description?: string;
  material_type: TeachingMaterialType;
  grade_level_id?: string;
  subject_id?: string;
  file?: File;
  file_id?: string;
  version?: number;
}

export interface CreateTeachingMaterialVersionPayload {
  title?: string;
  description?: string;
  file?: File;
  file_id?: string;
  version?: number;
}

// ============================================================================
// ADM-ACD-07: Tugas & Materi Pembelajaran (LMS Ringan)
// ============================================================================

export interface LearningMaterialItem {
  id: string;
  class_id: string;
  subject_id: string;
  teacher_id: string;
  title: string;
  description?: string | null;
  file_id?: string | null;
  created_at?: string;
  updated_at?: string;
  class?: SchoolClassItem;
  subject?: SubjectItem;
  teacher?: TeacherItem;
  file?: {
    id: string;
    object_key: string;
    mime_type: string;
    size_bytes?: number;
    url?: string;
  } | null;
}

export interface LearningMaterialFilters {
  class_id?: string;
  subject_id?: string;
  teacher_id?: string;
  search?: string;
  per_page?: number;
  page?: number;
}

export interface StoreLearningMaterialPayload {
  class_id: string;
  subject_id: string;
  title: string;
  description?: string;
  file?: File;
  file_id?: string;
}

export interface UpdateLearningMaterialPayload {
  title?: string;
  description?: string;
  file?: File;
  file_id?: string;
}

export type SubmissionStatus = 'menunggu_penilaian' | 'dinilai';
export type SubmissionTiming = 'tepat_waktu' | 'terlambat';

export const SUBMISSION_STATUS_LABELS: Record<SubmissionStatus, string> = {
  menunggu_penilaian: 'Menunggu Penilaian',
  dinilai: 'Sudah Dinilai',
};

export const SUBMISSION_STATUS_COLORS: Record<
  SubmissionStatus,
  { badge: string; text: string; bg: string }
> = {
  menunggu_penilaian: {
    badge: 'bg-amber-100 text-amber-800 border-amber-200',
    text: 'text-amber-700',
    bg: 'bg-amber-50',
  },
  dinilai: {
    badge: 'bg-emerald-100 text-emerald-800 border-emerald-200',
    text: 'text-emerald-700',
    bg: 'bg-emerald-50',
  },
};

export const SUBMISSION_TIMING_LABELS: Record<SubmissionTiming, string> = {
  tepat_waktu: 'Tepat Waktu',
  terlambat: 'Terlambat',
};

export const SUBMISSION_TIMING_COLORS: Record<
  SubmissionTiming,
  { badge: string; text: string }
> = {
  tepat_waktu: {
    badge: 'bg-blue-100 text-blue-800 border-blue-200',
    text: 'text-blue-700',
  },
  terlambat: {
    badge: 'bg-rose-100 text-rose-800 border-rose-200',
    text: 'text-rose-700',
  },
};

export interface AssignmentSubmissionItem {
  id: string;
  assignment_id: string;
  student_id: string;
  file_id: string;
  notes?: string | null;
  score?: number | string | null;
  feedback?: string | null;
  submitted_at: string;
  is_late: boolean;
  status: SubmissionStatus;
  submission_timing: SubmissionTiming;
  created_at?: string;
  updated_at?: string;
  student?: {
    id: string;
    full_name: string;
    nis: string;
    nisn?: string;
    gender?: 'L' | 'P';
    user?: {
      id: string;
      username: string;
      email: string;
    };
  };
  file?: {
    id: string;
    object_key: string;
    mime_type: string;
    size_bytes?: number;
    url?: string;
  } | null;
  assignment?: AssignmentItem;
}

export interface AssignmentItem {
  id: string;
  class_id: string;
  subject_id: string;
  teacher_id: string;
  title: string;
  description?: string | null;
  file_id?: string | null;
  due_at: string;
  max_score: number | string;
  grade_component_id?: string | null;
  submissions_count?: number;
  created_at?: string;
  updated_at?: string;
  class?: SchoolClassItem;
  subject?: SubjectItem;
  teacher?: TeacherItem;
  file?: {
    id: string;
    object_key: string;
    mime_type: string;
    size_bytes?: number;
    url?: string;
  } | null;
  gradeComponent?: {
    id: string;
    name: string;
    code: string;
    weight: number;
    semester_id?: string;
  } | null;
  my_submission?: AssignmentSubmissionItem | null;
}

export interface AssignmentFilters {
  class_id?: string;
  subject_id?: string;
  teacher_id?: string;
  search?: string;
  per_page?: number;
  page?: number;
}

export interface StoreAssignmentPayload {
  class_id: string;
  subject_id: string;
  title: string;
  description?: string;
  due_at: string;
  max_score?: number;
  grade_component_id?: string;
  file?: File;
  file_id?: string;
}

export interface UpdateAssignmentPayload {
  title?: string;
  description?: string;
  due_at?: string;
  max_score?: number;
  grade_component_id?: string;
  file?: File;
  file_id?: string;
}

export interface SubmitAssignmentPayload {
  file?: File;
  file_id?: string;
  notes?: string;
}

export interface GradeSubmissionPayload {
  score: number;
  feedback?: string;
}

// ============================================================================
// ADM-GRD-01 / BE-GRD-01: Buku Nilai & Komponen Penilaian Bulanan
// ============================================================================

export interface GradeComponentItem {
  id: string;
  subject_id: string;
  semester_id: string;
  class_id?: string | null;
  code: string; // UH, UTS, UAS, TUGAS, etc.
  name: string;
  weight: number | string;
  created_at?: string;
  updated_at?: string;
  subject?: SubjectItem;
  semester?: SemesterItem;
  class?: SchoolClassItem | null;
}

export interface GradePeriodItem {
  id: string;
  semester_id: string;
  month: number;
  month_name?: string;
  status: 'open' | 'locked';
  locked_by?: string | null;
  locked_at?: string | null;
  created_at?: string;
  updated_at?: string;
  semester?: SemesterItem;
  locker?: {
    id: string;
    username?: string;
    name?: string;
    email?: string;
  } | null;
}

export interface GradeItem {
  id: string;
  student_id: string;
  component_id: string;
  period_id: string;
  exam_id?: string | null;
  value: number | string;
  source: 'manual' | 'cbt' | 'lms';
  entered_by?: string | null;
  locked_at?: string | null;
  created_at?: string;
  updated_at?: string;
  student?: StudentItem;
  component?: GradeComponentItem;
  period?: GradePeriodItem;
  enterer?: {
    id: string;
    username?: string;
    name?: string;
    email?: string;
  } | null;
}

export interface MatrixStudentRow {
  student: {
    id: string;
    nisn: string;
    nis: string;
    name?: string;
    full_name?: string;
    gender: string;
  };
  grades: Record<
    string,
    {
      id: string;
      value: number;
      source: string;
      entered_by?: string;
      updated_at?: string;
    } | null
  >;
  is_complete: boolean;
  filled_count: number;
  total_components: number;
  final_score: number | null;
}

export interface ClassGradeMatrix {
  class: SchoolClassItem;
  period: GradePeriodItem;
  components: GradeComponentItem[];
  total_weight: number;
  students: MatrixStudentRow[];
  summary: {
    total_students: number;
    complete_count: number;
    incomplete_count: number;
    average_score: number;
    highest_score: number;
    lowest_score: number;
  };
}

export interface CreateGradeComponentPayload {
  subject_id: string;
  semester_id: string;
  class_id?: string | null;
  code: string;
  name: string;
  weight: number;
}

export interface UpdateGradeComponentPayload {
  code?: string;
  name?: string;
  weight?: number;
}

export interface StoreGradePayload {
  student_id: string;
  component_id: string;
  period_id: string;
  value: number;
  source?: string;
}

export interface BulkStoreGradePayload {
  component_id?: string;
  period_id: string;
  grades: Array<{
    student_id: string;
    component_id?: string;
    value: number;
    source?: string;
  }>;
}

export interface LockPeriodPayload {
  status: 'open' | 'locked';
}

export interface GradeFilters {
  student_id?: string;
  component_id?: string;
  period_id?: string;
  class_id?: string;
  subject_id?: string;
  per_page?: number;
  page?: number;
}

export interface GradeMatrixFilters {
  class_id: string;
  subject_id: string;
  period_id: string;
  semester_id?: string;
}

// ============================================================================
// ADM-GRD-02 / BE-GRD-02: Catatan Karakter Bulanan Siswa
// ============================================================================

export type CharacterRating = 'sangat_baik' | 'baik' | 'cukup' | 'perlu_bimbingan';

export interface CharacterAspectDefinition {
  key: string;
  name: string;
  category: 'spiritual' | 'sosial' | 'integritas';
  description: string;
}

export const CHARACTER_ASPECTS: CharacterAspectDefinition[] = [
  {
    key: 'beriman_bertakwa',
    name: 'Beriman, Bertakwa & Berakhlak Mulia',
    category: 'spiritual',
    description: 'Pelaksanaan ibadah, doa sebelum/sesudah KBM, adab terhadap guru dan teman',
  },
  {
    key: 'berkebinekaan_global',
    name: 'Berkebinekaan Global',
    category: 'sosial',
    description: 'Menghargai keragaman suku, agama, budaya, toleransi, dan anti-perundungan',
  },
  {
    key: 'gotong_royong',
    name: 'Bergotong Royong',
    category: 'sosial',
    description: 'Kerja sama kelompok, empati, kerelawanan piket kelas, dan kepedulian sosial',
  },
  {
    key: 'mandiri',
    name: 'Kemandirian & Tanggung Jawab',
    category: 'integritas',
    description: 'Inisiatif belajar mandiri, kesiapan peralatan, pengelolaan waktu dan emosi',
  },
  {
    key: 'bernalar_kritis',
    name: 'Bernalar Kritis',
    category: 'integritas',
    description: 'Keberanian bertanya, menyampaikan argumentasi logis, dan menganalisis fakta',
  },
  {
    key: 'kreatif',
    name: 'Kreativitas & Daya Cipta',
    category: 'integritas',
    description: 'Gagasan orisinal, karya inovatif, dan pemecahan masalah fleksibel',
  },
  {
    key: 'kedisiplinan',
    name: 'Kedisiplinan & Tata Tertib',
    category: 'sosial',
    description: 'Ketepatan waktu masuk kelas, kerapian seragam sekolah, dan kepatuhan norma',
  },
  {
    key: 'kejujuran',
    name: 'Kejujuran & Integritas',
    category: 'spiritual',
    description: 'Integritas akademik saat asesmen, mengakui kesalahan, dan dapat dipercaya',
  },
];

export const CHARACTER_RATING_LABELS: Record<CharacterRating, string> = {
  sangat_baik: 'Sangat Baik (SB)',
  baik: 'Baik (B)',
  cukup: 'Cukup (C)',
  perlu_bimbingan: 'Perlu Bimbingan (PB)',
};

export const CHARACTER_RATING_COLORS: Record<
  CharacterRating,
  { badge: string; text: string; dot: string }
> = {
  sangat_baik: {
    badge: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    text: 'text-emerald-700',
    dot: 'bg-emerald-500',
  },
  baik: {
    badge: 'bg-blue-50 text-blue-700 border-blue-200',
    text: 'text-blue-700',
    dot: 'bg-blue-500',
  },
  cukup: {
    badge: 'bg-amber-50 text-amber-700 border-amber-200',
    text: 'text-amber-700',
    dot: 'bg-amber-500',
  },
  perlu_bimbingan: {
    badge: 'bg-rose-50 text-rose-700 border-rose-200',
    text: 'text-rose-700',
    dot: 'bg-rose-500',
  },
};

export interface CharacterNoteItem {
  id: string;
  student_id: string;
  period_id: string;
  scale_data: Record<string, CharacterRating>;
  homeroom_note?: string | null;
  author_id: string;
  created_at?: string;
  updated_at?: string;
  student?: StudentItem;
  period?: GradePeriodItem;
  author?: {
    id: string;
    username?: string;
    name?: string;
    email?: string;
  } | null;
}

export interface MatrixCharacterStudentRow {
  student: {
    id: string;
    nisn: string;
    nis: string;
    name?: string;
    full_name?: string;
    gender: string;
  };
  note?: CharacterNoteItem | null;
  scale_data: Record<string, CharacterRating>;
  homeroom_note?: string | null;
  is_complete: boolean;
  completeness_status?: 'lengkap' | 'belum_lengkap' | 'belum_dibuat';
  all_dimensions_complete?: boolean;
  dimensions_count?: number;
  updated_at?: string | null;
}

export interface ClassCharacterNotesMatrix {
  class: SchoolClassItem;
  period: GradePeriodItem;
  is_locked: boolean;
  students: MatrixCharacterStudentRow[];
  summary: {
    total_students: number;
    complete_count: number;
    incomplete_count: number;
    completion_rate: number;
  };
}

export interface StoreCharacterNotePayload {
  student_id: string;
  period_id: string;
  scale_data: Record<string, string>;
  homeroom_note?: string | null;
}

export interface BulkStoreCharacterNotePayload {
  period_id: string;
  notes: Array<{
    student_id: string;
    scale_data: Record<string, string>;
    homeroom_note?: string | null;
  }>;
}

export interface CharacterNoteFilters {
  student_id?: string;
  period_id?: string;
  class_id?: string;
  per_page?: number;
  page?: number;
}

export interface ClassCharacterNotesFilters {
  class_id: string;
  period_id: string;
}

// ============================================================================
// ADM-GRD-03 / BE-GRD-03: E-Progress Report Bulanan
// ============================================================================

export type ProgressReportStatus = 'draft' | 'menunggu_acc' | 'disetujui' | 'terbit';

export const PROGRESS_REPORT_STATUS_LABELS: Record<ProgressReportStatus, string> = {
  draft: 'Draf',
  menunggu_acc: 'Menunggu ACC',
  disetujui: 'Disetujui Kepsek',
  terbit: 'Terbit Resmi',
};

export const PROGRESS_REPORT_STATUS_COLORS: Record<ProgressReportStatus, string> = {
  draft: 'bg-gray-100 text-gray-700 border-gray-300',
  menunggu_acc: 'bg-amber-50 text-amber-700 border-amber-300',
  disetujui: 'bg-blue-50 text-blue-700 border-blue-300',
  terbit: 'bg-emerald-50 text-emerald-700 border-emerald-300',
};

export interface ProgressReportGradeComponent {
  component_id?: string;
  name: string;
  weight: number;
  value: number;
}

export interface ProgressReportGradeSubject {
  subject_id: string;
  subject_name: string;
  subject_code?: string;
  components: ProgressReportGradeComponent[];
  final_score: number;
}

export interface ProgressReportAttendance {
  hadir: number;
  izin: number;
  sakit: number;
  alpa: number;
  total_recorded: number;
  percentage: number;
}

export interface ProgressReportCharacter {
  scale_data: Record<string, string>;
  homeroom_note: string;
  author_name?: string;
}

export interface ProgressReportSnapshot {
  student: {
    id: string;
    nis?: string;
    nisn?: string;
    full_name: string;
    gender?: string;
    class_id?: string;
    class_name?: string;
  };
  period: {
    id: string;
    month: number;
    semester_id?: string;
    semester_name?: string;
    academic_year?: string;
  };
  grades: ProgressReportGradeSubject[];
  attendance: ProgressReportAttendance;
  character: ProgressReportCharacter;
  school_name?: string;
  generated_at?: string;
}

export interface ProgressReportItem {
  id: string;
  student_id: string;
  period_id: string;
  data_snapshot: ProgressReportSnapshot;
  file_id?: string | null;
  status: ProgressReportStatus;
  approved_by?: string | null;
  verify_token: string;
  created_at: string;
  updated_at: string;
  student?: {
    id: string;
    nis: string;
    nisn: string;
    full_name: string;
    gender: string;
  };
  period?: GradePeriodItem;
  approver?: {
    id: string;
    name?: string;
    username: string;
  } | null;
}

export interface ClassProgressReportStudentRow {
  student: {
    id: string;
    nis: string;
    nisn: string;
    full_name: string;
    gender: string;
  };
  report: {
    id: string;
    status: ProgressReportStatus;
    verify_token: string;
    created_at?: string;
    updated_at?: string;
    approved_by?: string | null;
    approver_name?: string;
  } | null;
  data_readiness: {
    has_grades: boolean;
    graded_subjects_count: number;
    has_character_note: boolean;
    character_note_complete: boolean;
    attendance_days_count: number;
    is_ready_to_generate: boolean;
  };
}

export interface ClassProgressReportsStatus {
  class: {
    id: string;
    name: string;
    homeroom_teacher?: string;
  };
  period: {
    id: string;
    month: number;
    semester_name?: string;
    academic_year?: string;
    is_locked: boolean;
  };
  students: ClassProgressReportStudentRow[];
  summary: {
    total_students: number;
    generated_count: number;
    draft_count: number;
    menunggu_acc_count: number;
    disetujui_count: number;
    terbit_count: number;
    belum_dibuat_count: number;
    ready_to_generate_count: number;
  };
}

export interface GenerateProgressReportPayload {
  period_id: string;
  student_id?: string;
  class_id?: string;
  status?: ProgressReportStatus;
}

export interface ApproveProgressReportPayload {
  status: 'disetujui' | 'terbit' | 'draft' | 'menunggu_acc';
  note?: string;
}

export interface BulkApproveProgressReportPayload {
  report_ids?: string[];
  class_id?: string;
  period_id?: string;
  status: 'disetujui' | 'terbit' | 'draft' | 'menunggu_acc';
  note?: string;
}

export interface ProgressReportFilters {
  period_id?: string;
  student_id?: string;
  class_id?: string;
  status?: ProgressReportStatus;
  per_page?: number;
  page?: number;
}

// ============================================================================
// ADM-GRD-04 / BE-GRD-04: E-Rapor Semester & Template Deskripsi Capaian
// ============================================================================

export type ReportCardStatus = 'draft' | 'terkunci' | 'disetujui' | 'terbit';

export const REPORT_CARD_STATUS_LABELS: Record<ReportCardStatus, string> = {
  draft: 'Draf',
  terkunci: 'Terkunci',
  disetujui: 'Disahkan Kepsek',
  terbit: 'Terbit Resmi',
};

export const REPORT_CARD_STATUS_COLORS: Record<ReportCardStatus, string> = {
  draft: 'bg-gray-100 text-gray-700 border-gray-300',
  terkunci: 'bg-amber-100 text-amber-800 border-amber-300',
  disetujui: 'bg-blue-100 text-blue-800 border-blue-300',
  terbit: 'bg-emerald-100 text-emerald-800 border-emerald-300',
};

export type ReportDescriptionLevel = 'tuntas' | 'belum';

export interface ReportDescriptionTemplateItem {
  id: string;
  subject_id: string;
  level: ReportDescriptionLevel;
  template_text: string;
  created_at?: string;
  updated_at?: string;
  subject?: {
    id: string;
    code: string;
    name: string;
    subject_group?: string;
  } | null;
}

export interface ReportDescriptionTemplateFilters {
  subject_id?: string;
  level?: ReportDescriptionLevel;
  per_page?: number;
  page?: number;
}

export interface StoreReportDescriptionTemplatePayload {
  subject_id: string;
  level: ReportDescriptionLevel;
  template_text: string;
}

export interface ReportCardSubjectSummary {
  subject_id: string;
  subject_code: string;
  subject_name: string;
  subject_group?: string;
  final_score: number;
  level: ReportDescriptionLevel;
  competency_description: string;
}

export interface ReportCardAttendanceSummary {
  hadir: number;
  sakit: number;
  izin: number;
  alpa: number;
  total_recorded: number;
  percentage: number;
}

export interface ReportCardExtracurricularItem {
  name: string;
  predicate: string;
  status?: string;
  description?: string;
}

export interface ReportCardSnapshot {
  school_name?: string;
  student: {
    id: string;
    nis?: string;
    nisn?: string;
    full_name: string;
    gender?: string;
    class_id?: string;
    class_name?: string;
  };
  semester: {
    id: string;
    name: string;
    academic_year: string;
    start_date?: string;
    end_date?: string;
  };
  subjects: ReportCardSubjectSummary[];
  attendance: ReportCardAttendanceSummary;
  extracurriculars: ReportCardExtracurricularItem[];
  homeroom_note?: string;
  generated_at?: string;
}

export interface ReportCardItem {
  id: string;
  student_id: string;
  semester_id: string;
  data_snapshot: ReportCardSnapshot;
  file_id?: string | null;
  status: ReportCardStatus;
  locked_at?: string | null;
  approved_by?: string | null;
  verify_token: string;
  created_at: string;
  updated_at: string;
  student?: {
    id: string;
    nis: string;
    nisn: string;
    full_name: string;
    gender: string;
  };
  semester?: SemesterItem;
  approver?: {
    id: string;
    name?: string;
    username: string;
  } | null;
}

export interface ClassReportCardStudentRow {
  student: {
    id: string;
    nis: string;
    nisn: string;
    full_name: string;
    gender: string;
  };
  report: {
    id: string;
    status: ReportCardStatus;
    verify_token: string;
    locked_at?: string | null;
    created_at?: string;
    updated_at?: string;
    approved_by?: string | null;
    approver_name?: string;
  } | null;
  data_readiness: {
    has_grades: boolean;
    graded_subjects_count: number;
    has_attendance: boolean;
    attendance_days_count: number;
    has_character_note: boolean;
    has_extracurricular: boolean;
    is_ready_to_generate: boolean;
  };
}

export interface ClassReportCardsStatus {
  class: {
    id: string;
    name: string;
    homeroom_teacher?: string;
  };
  semester: {
    id: string;
    name: string;
    academic_year?: string;
    is_active: boolean;
  };
  students: ClassReportCardStudentRow[];
  summary: {
    total_students: number;
    generated_count: number;
    draft_count: number;
    terkunci_count: number;
    disetujui_count: number;
    terbit_count: number;
    belum_dibuat_count: number;
    ready_to_generate_count: number;
  };
}

export interface GenerateReportCardPayload {
  semester_id: string;
  student_id?: string;
  class_id?: string;
  status?: ReportCardStatus;
  homeroom_note?: string;
}

export interface ApproveReportCardPayload {
  status: 'disetujui' | 'terbit' | 'draft' | 'terkunci';
  note?: string;
}

export interface LockReportCardPayload {
  is_locked: boolean;
  reason?: string;
}

export interface BulkApproveReportCardPayload {
  report_ids?: string[];
  class_id?: string;
  semester_id?: string;
  status: 'disetujui' | 'terbit' | 'draft' | 'terkunci';
  note?: string;
}

export interface BulkLockReportCardPayload {
  report_ids?: string[];
  class_id?: string;
  semester_id?: string;
  is_locked: boolean;
  reason?: string;
}

export interface ReportCardFilters {
  semester_id?: string;
  student_id?: string;
  class_id?: string;
  status?: ReportCardStatus;
  per_page?: number;
  page?: number;
}







