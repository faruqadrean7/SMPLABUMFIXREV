export type QuestionType =
  | 'pg'
  | 'pg_kompleks'
  | 'bs'
  | 'menjodohkan'
  | 'isian'
  | 'uraian';

export interface QuestionOptionItem {
  id: string;
  question_id?: string;
  label: string;
  text: string;
  is_correct?: boolean;
  match_key?: string | null;
}

export interface QuestionItem {
  id: string;
  bank_id: string;
  question_type: QuestionType;
  stem: string;
  weight: number;
  options?: QuestionOptionItem[];
  created_at?: string;
  updated_at?: string;
}

export interface QuestionBankItem {
  id: string;
  subject_id: string;
  subject?: {
    id: string;
    name: string;
    code?: string;
  };
  created_by?: string;
  title: string;
  code: string;
  grade_level?: number | string;
  total_questions?: number;
  questions?: QuestionItem[];
  created_at?: string;
  updated_at?: string;
}

export type ExamType =
  | 'harian'
  | 'formatif'
  | 'sumatif'
  | 'uts'
  | 'uas'
  | 'remedial'
  | 'asat';

export type ExamStatus =
  | 'draft'
  | 'dijadwalkan'
  | 'berlangsung'
  | 'selesai'
  | 'dibatalkan'
  | 'arsip'
  | 'published'
  | 'active'
  | 'archived';

export interface ExamRoomItem {
  id: string;
  exam_id: string;
  room_id?: string;
  room?: {
    id: string;
    name: string;
    code?: string;
    capacity?: number;
  };
  token: string;
  token_expired_at?: string;
  proctor_name?: string;
}

export interface ExamParticipantItem {
  id: string;
  exam_id: string;
  student_id: string;
  student?: {
    id: string;
    full_name: string;
    nis: string;
    nisn?: string;
    classes?: Array<{ id: string; name: string }>;
  };
  room_id?: string;
  seat_number?: string;
}

export interface ExamQuestionAttachment {
  id: string;
  exam_id: string;
  question_id: string;
  weight_override?: number | null;
  order_no?: number;
  question?: QuestionItem;
}

export interface ExamItem {
  id: string;
  school_id?: string;
  subject_id: string;
  subject?: {
    id: string;
    name: string;
    code?: string;
  };
  created_by?: string;
  creator?: {
    id: string;
    name?: string;
    username?: string;
  };
  parent_exam_id?: string | null;
  parent_exam?: ExamItem | null;
  title: string;
  exam_type: ExamType;
  duration_min: number;
  start_at: string;
  end_at: string;
  passing_grade?: number;
  shuffle_questions?: boolean;
  shuffle_options?: boolean;
  package_mode?: string;
  show_result_mode?: string;
  status: ExamStatus;
  max_remedial_score?: number | null;
  rooms_count?: number;
  participants_count?: number;
  questions_count?: number;
  rooms?: ExamRoomItem[];
  participants?: ExamParticipantItem[];
  questions?: ExamQuestionAttachment[];
  created_at?: string;
  updated_at?: string;
}

export type ExamSessionStatus =
  | 'belum'
  | 'mengerjakan'
  | 'selesai'
  | 'terkunci'
  | 'not_started'
  | 'in_progress'
  | 'submitted'
  | 'force_submitted'
  | 'blocked'
  | 'timed_out';

export interface ExamSessionItem {
  id: string;
  exam_id: string;
  student_id: string;
  status: ExamSessionStatus;
  started_at?: string | null;
  deadline_at?: string | null;
  submitted_at?: string | null;
  extra_minutes?: number;
  score?: number | null;
  last_heartbeat_at?: string | null;
  ip_address?: string | null;
  device_fingerprint?: string | null;
  blur_count?: number;
  locked_reason?: string | null;
  student?: {
    id: string;
    full_name: string;
    nis: string;
  };
}

export interface ExamMonitorSessionItem {
  session_id: string;
  student_id: string;
  student_name: string;
  nis: string;
  room_name?: string;
  status: ExamSessionStatus;
  progress_percent: number;
  answered_count: number;
  total_questions: number;
  remaining_seconds: number;
  blur_count: number;
  last_heartbeat_diff_seconds: number;
  is_online: boolean;
  ip_address?: string;
  violations_count: number;
  locked_reason?: string | null;
}

export interface ExamResultItem {
  session_id: string;
  student_id: string;
  student_name: string;
  nis: string;
  class_name: string;
  status: string;
  started_at?: string;
  submitted_at?: string;
  total_score: number | null;
  passing_grade: number;
  is_passed: boolean;
  answers_count: number;
}

export interface ItemAnalysisDistractor {
  option_id: string;
  label: string;
  is_correct: boolean;
  chosen_count: number;
  percentage: number;
}

export interface ItemAnalysisItem {
  question_id: string;
  stem: string;
  question_type: QuestionType;
  difficulty_index: number;
  difficulty_category: 'Sukar' | 'Sedang' | 'Mudah';
  discrimination_index: number;
  discrimination_category: 'Sangat Baik' | 'Baik' | 'Cukup' | 'Jelek';
  distractor_stats?: ItemAnalysisDistractor[];
}

export interface StudentSessionQuestionOption {
  id: string;
  label: string;
  text: string;
  match_key?: string | null;
}

export interface StudentSessionQuestionItem {
  number: number;
  question_id: string;
  question_type: QuestionType;
  stem: string;
  body?: any;
  options?: StudentSessionQuestionOption[];
  current_answer?: any;
  is_flagged?: boolean;
}

export interface StudentSessionData {
  session_id: string;
  exam: {
    id: string;
    title: string;
    subject_name: string;
    duration_min: number;
    start_at: string;
    end_at: string;
  };
  student: {
    id: string;
    name: string;
    nis: string;
  };
  started_at: string;
  deadline_at: string;
  remaining_seconds: number;
  total_questions: number;
  answers: Record<string, any>;
  flagged_question_ids: string[];
  questions: StudentSessionQuestionItem[];
}

export interface StudentCatalogExamItem {
  id: string;
  title: string;
  subject_name: string;
  exam_type: ExamType;
  duration_min: number;
  start_at: string;
  end_at: string;
  passing_grade?: number;
  status: ExamStatus;
  can_start: boolean;
  my_session?: {
    id: string;
    status: ExamSessionStatus;
    score?: number | null;
    started_at?: string;
    submitted_at?: string;
  } | null;
}
