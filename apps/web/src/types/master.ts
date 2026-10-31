/**
 * Definisi tipe data modul Master Data SISTLABUM
 * (ADM-MST-01 s/d ADM-MST-06)
 */

// ============================================================================
// ADM-MST-01: Tahun Ajaran & Semester
// ============================================================================

export interface AcademicYearItem {
  id: string;
  school_id?: string;
  name: string;
  start_date: string;
  end_date: string;
  is_active: boolean;
  semesters_count?: number;
  classes_count?: number;
  created_at?: string;
  updated_at?: string;
}

export interface SemesterItem {
  id: string;
  academic_year_id: string;
  name: string;
  start_date: string;
  end_date: string;
  is_active: boolean;
  created_at?: string;
  updated_at?: string;
}

export interface ActivePeriodData {
  academicYear: {
    id: string;
    name: string;
    startDate: string;
    endDate: string;
    isActive: boolean;
  } | null;
  semester: {
    id: string;
    academicYearId: string;
    name: string;
    startDate: string;
    endDate: string;
    isActive: boolean;
  } | null;
}

export interface AcademicYearFormValues {
  name: string;
  start_date: string;
  end_date: string;
  is_active?: boolean;
}

// ============================================================================
// ADM-MST-02: Siswa
// ============================================================================

export type StudentStatus = 'aktif' | 'lulus' | 'pindah' | 'keluar';

export type ProgramType = 'reguler' | 'tahfidz' | 'icp' | 'inklusi';
export type Gender = 'L' | 'P';

export interface StudentItem {
  id: string;
  school_id?: string;
  user_id?: string | null;
  nis: string;
  nisn: string;
  nik?: string;
  full_name: string;
  gender: Gender;
  birth_place: string;
  birth_date: string;
  religion?: string | null;
  address?: string | null;
  photo_file_id?: string | null;
  photo_file?: {
    id: string;
    url?: string | null;
    object_key?: string;
  } | null;
  photo_url?: string | null;
  status: StudentStatus;
  program_type?: ProgramType;
  entry_date: string;
  graduated_at?: string | null;
  classes?: Array<{
    id: string;
    name: string;
    grade_level_id?: string;
    academic_year_id?: string;
    pivot?: {
      joined_at?: string;
      left_at?: string | null;
    };
  }>;
  guardians?: Array<GuardianItem & {
    pivot?: {
      relationship: string;
      is_primary: boolean;
    };
  }>;
  statusHistory?: StatusHistoryItem[];
  created_at?: string;
  updated_at?: string;
}

export interface StatusHistoryItem {
  id: string;
  student_id: string;
  previous_status: string | null;
  new_status: string;
  reason?: string | null;
  effective_date: string;
  created_at: string;
}

export interface StudentFormValues {
  nis: string;
  nisn: string;
  nik: string;
  full_name: string;
  gender: Gender;
  birth_place: string;
  birth_date: string;
  religion?: string;
  address?: string;
  entry_date: string;
  status?: StudentStatus;
  program_type?: ProgramType;
  photo_file_id?: string | null;
}

export interface ChangeStudentStatusFormValues {
  status: StudentStatus;
  reason?: string;
  effective_date: string;
}

// ============================================================================
// ADM-MST-03: Guru & Tenaga Kependidikan
// ============================================================================

export type TeacherEmploymentStatus = 'tetap' | 'honorer' | 'kontrak';

export interface TeacherSalarySlipItem {
  id: string;
  teacher_id: string;
  slip_number?: string | null;
  period_month: number;
  period_year: number;
  period_formatted?: string;
  payment_date?: string | null;
  basic_salary: number | string;
  allowances: number | string;
  deductions: number | string;
  net_salary: number | string;
  status: string;
  notes?: string | null;
  file_id?: string | null;
  file?: {
    id: string;
    mime_type: string;
    size_bytes: number;
  } | null;
  creator?: {
    id: string;
    username: string;
  } | null;
  created_at?: string;
}

export interface TeachingHoursRecap {
  teacher: {
    id: string;
    full_name: string;
    nip?: string | null;
    status: string;
  };
  total_hours: number;
  target_hours: number;
  is_target_met: boolean;
  difference: number;
  assignments_count: number;
  homeroom_count: number;
  assignments: Array<{
    id: string;
    subject_id: string;
    subject_name: string;
    subject_code: string;
    class_id: string;
    class_name: string;
    semester_name: string;
    hours_per_week: number;
  }>;
}

export interface TeacherItem {
  id: string;
  school_id?: string;
  user_id?: string | null;
  nip?: string | null;
  nuptk?: string | null;
  full_name: string;
  position?: string | null;
  status: TeacherEmploymentStatus;
  photo_file_id?: string | null;
  photo_file?: {
    id: string;
    url?: string | null;
    object_key?: string;
  } | null;
  photo_url?: string | null;
  total_teaching_hours?: number;
  totalTeachingHours?: number;
  salarySlips?: TeacherSalarySlipItem[];
  salary_slips?: TeacherSalarySlipItem[];
  user?: {
    id: string;
    username: string;
    email: string;
  } | null;
  homeroomClasses?: Array<{
    id: string;
    name: string;
    academic_year_id: string;
  }>;
  teachingAssignments?: Array<{
    id: string;
    subject?: {
      id: string;
      name: string;
      code: string;
    };
    class?: {
      id: string;
      name: string;
    };
    hours_per_week?: number;
  }>;
  created_at?: string;
  updated_at?: string;
}

export interface TeacherFormValues {
  full_name: string;
  nip?: string;
  nuptk?: string;
  position?: string;
  status: TeacherEmploymentStatus;
  user_id?: string;
  photo_file_id?: string | null;
}

export interface TeacherSalarySlipFormValues {
  period_month: number;
  period_year: number;
  payment_date?: string;
  basic_salary: number;
  allowances: number;
  deductions: number;
  net_salary?: number;
  slip_number?: string;
  status?: string;
  notes?: string;
  file?: File | null;
}

// ============================================================================
// ADM-MST-05: Rombongan Belajar (Kelas & Roster)
// ============================================================================

export interface GradeLevelItem {
  id: string;
  level: number;
  name: string;
}

export interface MajorItem {
  id: string;
  code: string;
  name: string;
}

export interface SchoolClassItem {
  id: string;
  school_id?: string;
  academic_year_id: string;
  grade_level_id: string;
  major_id?: string | null;
  homeroom_teacher_id?: string | null;
  name: string;
  capacity: number;
  program_type?: ProgramType;
  active_students_count?: number;
  academicYear?: {
    id: string;
    name: string;
    is_active?: boolean;
  };
  gradeLevel?: GradeLevelItem;
  major?: MajorItem | null;
  homeroomTeacher?: {
    id: string;
    full_name: string;
    nip?: string | null;
  } | null;
  created_at?: string;
  updated_at?: string;
}

export interface ClassFormValues {
  academic_year_id: string;
  grade_level_id: string;
  major_id?: string;
  homeroom_teacher_id?: string;
  name: string;
  capacity: number;
  program_type?: ProgramType;
}

export interface ClassStudentItem {
  id: string;
  nis: string;
  nisn: string;
  full_name: string;
  gender: Gender;
  status: StudentStatus;
  pivot?: {
    id: string;
    joined_at: string;
    left_at: string | null;
  };
}

export interface TransferStudentFormValues {
  student_id: string;
  target_class_id: string;
  effective_date: string;
  reason?: string;
}

// ============================================================================
// ADM-MST-06: Mata Pelajaran & Penugasan Mengajar
// ============================================================================

export interface SubjectItem {
  id: string;
  school_id?: string;
  code: string;
  name: string;
  subject_group: string;
  created_at?: string;
  updated_at?: string;
}

export interface SubjectFormValues {
  code: string;
  name: string;
  subject_group: string;
}

export interface TeachingAssignmentItem {
  id: string;
  semester_id: string;
  class_id: string;
  subject_id: string;
  teacher_id: string;
  hours_per_week?: number;
  semester?: {
    id: string;
    name: string;
  };
  class?: {
    id: string;
    name: string;
  };
  subject?: {
    id: string;
    name: string;
    code: string;
  };
  teacher?: {
    id: string;
    full_name: string;
    nip?: string | null;
  };
  created_at?: string;
  updated_at?: string;
}

export interface TeachingAssignmentFormValues {
  semester_id: string;
  class_id: string;
  subject_id: string;
  teacher_id: string;
  hours_per_week?: number;
}

// ============================================================================
// ADM-MST-04: Orang Tua / Wali & Relasi Siswa
// ============================================================================

export interface GuardianItem {
  id: string;
  school_id?: string;
  user_id?: string | null;
  full_name: string;
  phone: string;
  occupation?: string | null;
  address?: string | null;
  user?: {
    id: string;
    username: string;
    email: string;
  } | null;
  students?: Array<{
    id: string;
    nis: string;
    nisn: string;
    full_name: string;
    pivot?: {
      relationship: string;
      is_primary: boolean;
    };
  }>;
  created_at?: string;
  updated_at?: string;
}

export interface GuardianFormValues {
  full_name: string;
  phone: string;
  occupation?: string;
  address?: string;
  user_id?: string;
}

export interface AttachStudentGuardianFormValues {
  guardian_id: string;
  relationship: string;
  is_primary?: boolean;
}
