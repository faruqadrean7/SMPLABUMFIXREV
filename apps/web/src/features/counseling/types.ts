export type CounselingCategory = 'Pribadi' | 'Sosial' | 'Belajar' | 'Karir' | 'Lainnya';
export type CounselingCaseStatus = 'aktif' | 'selesai' | 'dirujuk';
export type CounselingReferralStatus = 'pending' | 'diterima' | 'ditolak' | 'selesai';

export interface CounselingSessionItem {
  id: string;
  case_id: string;
  session_date: string;
  notes_enc: string;
  follow_up?: string | null;
  created_at?: string;
  updated_at?: string;
}

export interface CounselingCaseItem {
  id: string;
  student_id: string;
  counselor_id: string;
  category: CounselingCategory;
  status: CounselingCaseStatus;
  summary_enc: string;
  sessions_count?: number;
  sessions?: CounselingSessionItem[];
  student?: {
    id: string;
    full_name: string;
    nis?: string;
    nisn?: string;
    classes?: Array<{
      id: string;
      name: string;
    }>;
  };
  counselor?: {
    id: string;
    name: string;
    username?: string;
    teacher?: {
      id: string;
      full_name: string;
      nip?: string;
    };
  };
  created_at?: string;
  updated_at?: string;
}

export interface CounselingReferralItem {
  id: string;
  student_id: string;
  referred_by: string;
  reason: string;
  status: CounselingReferralStatus;
  student?: {
    id: string;
    full_name: string;
    nis?: string;
    classes?: Array<{
      id: string;
      name: string;
    }>;
  };
  referrer?: {
    id: string;
    name: string;
    teacher?: {
      id: string;
      full_name: string;
    };
  };
  created_at?: string;
  updated_at?: string;
}

export interface CreateCounselingCasePayload {
  student_id: string;
  category: CounselingCategory;
  summary: string;
  status?: CounselingCaseStatus;
}

export interface UpdateCounselingCasePayload {
  category?: CounselingCategory;
  status?: CounselingCaseStatus;
  summary?: string;
}

export interface CreateCounselingSessionPayload {
  session_date: string;
  notes: string;
  follow_up?: string;
}

export interface CreateCounselingReferralPayload {
  student_id: string;
  reason: string;
}

export interface UpdateCounselingReferralStatusPayload {
  status: CounselingReferralStatus;
}

// =========================================================================
// 4. PERMINTAAN KONSELING (BE-BK-03 / ADM-BK-03 / SIS-BK-01 / ORT-BK-01)
// =========================================================================

export type CounselingRequestStatus = 'pending' | 'scheduled' | 'rejected' | 'completed' | 'cancelled';
export type CounselingRequesterRole = 'student' | 'guardian' | 'counselor' | 'teacher' | 'other';

export interface CounselingRequestItem {
  id: string;
  student_id: string;
  requested_by: string;
  requester_role: CounselingRequesterRole;
  topic: string;
  preferred_date?: string | null;
  preferred_time?: string | null;
  preferred_counselor_id?: string | null;
  status: CounselingRequestStatus;
  scheduled_at?: string | null;
  counselor_id?: string | null;
  notes?: string | null;
  rejection_reason?: string | null;
  created_at?: string;
  updated_at?: string;
  student?: {
    id: string;
    full_name: string;
    nis?: string;
    nisn?: string;
    classes?: Array<{
      id: string;
      name: string;
    }>;
  };
  requester?: {
    id: string;
    name?: string;
    username?: string;
  };
  preferredCounselor?: {
    id: string;
    name?: string;
    username?: string;
  };
  counselor?: {
    id: string;
    name?: string;
    username?: string;
  };
}

export interface CreateCounselingRequestPayload {
  student_id: string;
  topic: string;
  preferred_date?: string;
  preferred_time?: string;
  preferred_counselor_id?: string;
  notes?: string;
}

export interface ScheduleCounselingRequestPayload {
  scheduled_at: string;
  counselor_id?: string;
  notes?: string;
}

export interface RejectCounselingRequestPayload {
  rejection_reason: string;
}

export interface CompleteCounselingRequestPayload {
  notes?: string;
  create_case?: boolean;
  case_category?: CounselingCategory;
  case_summary?: string;
  session_notes?: string;
  follow_up?: string;
}
