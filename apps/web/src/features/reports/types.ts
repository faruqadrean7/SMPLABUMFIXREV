export interface CbtLiveMetrics {
  active_students_count: number;
  ongoing_exams_count: number;
  active_rooms_count: number;
}

export interface AttendanceDetail {
  total_recorded: number;
  hadir: number;
  izin: number;
  sakit?: number;
  alpa?: number;
  terlambat: number;
  rate: number;
}

export interface AttendanceTodayMetrics {
  date: string;
  students: AttendanceDetail;
  staff: AttendanceDetail;
}

export interface SyllabusCompletionMetrics {
  total_schedules: number;
  active_with_journals: number;
  completion_rate: number;
}

export interface TeacherGradingMetrics {
  total_components: number;
  completed_components: number;
  completion_rate: number;
}

export interface GradeLevelAverage {
  grade_level_id: string;
  level_name: string;
  sort_order: number;
  average_score: number;
  grades_count: number;
}

export interface StudentAffairsMetrics {
  active_counseling_cases: number;
  recent_violations_count: number;
}

export interface InfrastructureMetrics {
  critical_tickets_count: number;
  heavily_damaged_assets: number;
}

export interface MasterSummaryMetrics {
  total_students: number;
  total_teachers: number;
  total_classes: number;
  pending_verifications: {
    achievements: number;
    facility_bookings: number;
    total: number;
  };
}

export interface ExecutiveDashboardData {
  cbt_live: CbtLiveMetrics;
  attendance_today: AttendanceTodayMetrics;
  syllabus_completion: SyllabusCompletionMetrics;
  teacher_grading_completion: TeacherGradingMetrics;
  grades_by_grade_level: GradeLevelAverage[];
  student_affairs_follow_up: StudentAffairsMetrics;
  infrastructure_status: InfrastructureMetrics;
  master_summary?: MasterSummaryMetrics;
  cached_at: string;
}

export type ReportType =
  | 'attendance'
  | 'grades'
  | 'ppdb'
  | 'student_points'
  | 'tickets'
  | 'loans';

export interface ReportFilterParams {
  start_date?: string;
  end_date?: string;
  status?: string;
  class_id?: string;
  page?: number;
  per_page?: number;
}

export interface ReportResponse<T = Record<string, unknown>> {
  type: ReportType;
  summary: Record<string, unknown>;
  data: T[];
  meta: {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
  };
}
