export type EarlyWarningRuleCode = 'ATTENDANCE_LOW' | 'GRADE_DROP' | 'VIOLATION_POINTS' | 'ATT_LOW' | string;
export type EarlyWarningSeverity = 'sedang' | 'tinggi';
export type EarlyWarningStatus = 'open' | 'in_progress' | 'resolved' | 'dismissed' | 'aktif';

export interface EarlyWarningAttendanceData {
  rule?: string;
  attendance_percent?: number;
  threshold_percent?: number;
  alpa_count?: number;
  izin_count?: number;
  sakit_count?: number;
  total_days_evaluated?: number;
  period_days?: number;
  detected_at?: string;
  handling_notes?: string;
  handled_at?: string;
}

export interface EarlyWarningGradeDropData {
  rule?: string;
  previous_average?: number;
  recent_average?: number;
  drop_points?: number;
  threshold?: number;
  handling_notes?: string;
  handled_at?: string;
}

export interface EarlyWarningViolationPointsData {
  rule?: string;
  total_points?: number;
  threshold?: number;
  handling_notes?: string;
  handled_at?: string;
}

export type EarlyWarningData = EarlyWarningAttendanceData & EarlyWarningGradeDropData & EarlyWarningViolationPointsData & Record<string, unknown>;

export interface EarlyWarningItem {
  id: string;
  student_id: string;
  rule_code: EarlyWarningRuleCode;
  severity: EarlyWarningSeverity;
  data: EarlyWarningData;
  status: EarlyWarningStatus;
  handled_by?: string | null;
  created_at?: string;
  updated_at?: string;
  student?: {
    id: string;
    full_name: string;
    nis?: string;
    nisn?: string;
    school_id?: string;
    classes?: Array<{
      id: string;
      name: string;
    }>;
  };
  handler?: {
    id: string;
    name?: string;
    username?: string;
  };
}

export interface EarlyWarningSummary {
  open_count: number;
  in_progress_count: number;
  resolved_count: number;
  dismissed_count: number;
  high_severity_count: number;
  total_active: number;
}

export interface ListEarlyWarningsParams {
  student_id?: string;
  class_id?: string;
  status?: string;
  severity?: string;
  rule_code?: string;
  search?: string;
  page?: number;
  per_page?: number;
}

export interface EvaluateEarlyWarningsPayload {
  class_id?: string;
  student_ids?: string[];
  attendance_threshold_percent?: number;
  min_absent_days?: number;
  grade_drop_threshold?: number;
  violation_points_threshold?: number;
}

export interface HandleEarlyWarningPayload {
  status: 'in_progress' | 'resolved' | 'dismissed';
  notes?: string;
}
