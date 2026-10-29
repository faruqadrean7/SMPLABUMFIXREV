/**
 * Tipe data modul PPDB (Penerimaan Peserta Didik Baru) SISTLABUM
 * Sesuai kontrak backend Laravel API (BE-PPDB-01 s/d BE-PPDB-09)
 */

export type PPDBWaveStatus = 'draft' | 'aktif' | 'ditutup' | 'selesai';

export interface PPDBWaveItem {
  id: string;
  school_id?: string | null;
  name: string;
  open_at: string;
  close_at: string;
  result_publish_at: string;
  status: PPDBWaveStatus;
  tracks?: PPDBTrackItem[];
  created_at?: string;
  updated_at?: string;
}

export interface PPDBTrackScoreFormula {
  rapor_weight?: number;
  test_weight?: number;
  [key: string]: unknown;
}

export interface PPDBRequirementItem {
  id: string;
  track_id: string;
  document_type: string;
  is_required: boolean;
  description?: string | null;
  created_at?: string;
  updated_at?: string;
}

export interface PPDBTrackItem {
  id: string;
  wave_id: string;
  wave?: PPDBWaveItem;
  code: string;
  name: string;
  quota: number;
  score_formula?: PPDBTrackScoreFormula | null;
  requirements?: PPDBRequirementItem[];
  applicants_count?: number;
  created_at?: string;
  updated_at?: string;
}

export type ApplicantStatus =
  | 'draft'
  | 'submitted'
  | 'verified'
  | 'revision'
  | 'selected'
  | 'waitlist'
  | 'rejected'
  | 'enrolled';

export type ApplicantDecision = 'diterima' | 'cadangan' | 'ditolak';

export type DocumentStatus = 'menunggu' | 'valid' | 'ditolak';

export interface ApplicantParentItem {
  id: string;
  applicant_id: string;
  relationship: 'ayah' | 'ibu' | 'wali';
  full_name: string;
  nik?: string | null;
  phone?: string | null;
  occupation?: string | null;
  income?: string | null;
}

export interface ApplicantDocumentItem {
  id: string;
  applicant_id: string;
  document_type: string;
  file_id: string;
  status: DocumentStatus;
  reject_reason?: string | null;
  verified_by?: string | null;
  file?: {
    id: string;
    filename: string;
    original_name?: string;
    mime_type?: string;
    size?: number;
    path?: string;
  };
  verifier?: {
    id: string;
    name: string;
    email: string;
  };
  created_at?: string;
  updated_at?: string;
}

export interface ApplicantStatusHistoryItem {
  id: string;
  applicant_id: string;
  from_status?: string | null;
  to_status: string;
  note?: string | null;
  actor_id?: string | null;
  actor?: {
    id: string;
    name: string;
    email: string;
  };
  created_at: string;
}

export interface ApplicantItem {
  id: string;
  user_id?: string | null;
  wave_id: string;
  wave?: PPDBWaveItem;
  track_id: string;
  track?: PPDBTrackItem;
  registration_no: string;
  full_name: string;
  gender: 'L' | 'P';
  birth_place?: string | null;
  birth_date?: string | null;
  nisn?: string | null;
  nik?: string | null;
  school_origin?: string | null;
  phone?: string | null;
  address?: string | null;
  status: ApplicantStatus;
  score?: number | null;
  rank?: number | null;
  decision?: ApplicantDecision | null;
  created_at?: string;
  updated_at?: string;
}

export interface ApplicantDetail extends ApplicantItem {
  parents?: ApplicantParentItem[];
  documents?: ApplicantDocumentItem[];
  statusHistory?: ApplicantStatusHistoryItem[];
}

export interface ApplicantQueryParams {
  page?: number;
  limit?: number;
  wave_id?: string;
  track_id?: string;
  status?: string;
  search?: string;
}

// Payloads
export interface CreateWavePayload {
  name: string;
  open_at: string;
  close_at: string;
  result_publish_at: string;
  status: PPDBWaveStatus;
  school_id?: string | null;
}

export interface UpdateWavePayload {
  name?: string;
  open_at?: string;
  close_at?: string;
  result_publish_at?: string;
  status?: PPDBWaveStatus;
  school_id?: string | null;
}

export interface CreateTrackPayload {
  wave_id: string;
  code: string;
  name: string;
  quota: number;
  score_formula?: PPDBTrackScoreFormula;
}

export interface UpdateTrackPayload {
  code?: string;
  name?: string;
  quota?: number;
  score_formula?: PPDBTrackScoreFormula;
}

export interface CreateRequirementPayload {
  track_id: string;
  document_type: string;
  is_required: boolean;
  description?: string;
}

export interface VerifyDocumentPayload {
  status: 'valid' | 'ditolak';
  reject_reason?: string;
}

export interface VerifyApplicationPayload {
  status: 'verified' | 'revision';
  note?: string;
}

export interface RankApplicantsPayload {
  track_id?: string;
  waitlist_quota?: number;
  scores?: Array<{
    applicant_id: string;
    score?: number;
    rapor_score?: number;
    test_score?: number;
    track_id?: string;
  }>;
}

export interface OverrideDecisionPayload {
  decision: ApplicantDecision;
  reason: string;
}

export interface EnrollApplicantPayload {
  nis?: string;
  nisn?: string;
  class_id?: string;
  academic_year_id?: string;
  entry_date?: string;
  force_enroll?: boolean;
  notes?: string;
}

export interface PPDBRankingItem {
  id: string;
  registration_no: string;
  full_name: string;
  birth_date?: string;
  track_code?: string;
  track_name?: string;
  score?: number | null;
  rank?: number | null;
  decision?: ApplicantDecision | null;
  status: ApplicantStatus;
}

export interface PPDBWaveRankingsResponse {
  wave_id: string;
  wave_name: string;
  result_publish_at?: string | null;
  total: number;
  applicants: PPDBRankingItem[];
}

export interface PPDBRankTrackStat {
  track_id: string;
  track_name: string;
  quota: number;
  waitlist_quota: number;
  total_applicants: number;
  selected: number;
  waitlist: number;
  rejected: number;
}

export interface PPDBRankExecutionResult {
  wave_id: string;
  wave_name: string;
  tracks: PPDBRankTrackStat[];
  message: string;
}

export interface PPDBStatsTrackItem {
  track_id: string;
  wave_id: string;
  wave_name?: string;
  code: string;
  name: string;
  quota: number;
  total_applicants: number;
  submitted_applicants: number;
  verified_count: number;
  accepted_count: number;
  enrolled_count: number;
  fill_percentage: number;
}

export interface PPDBStatsResponse {
  total_applicants: number;
  submitted_applicants: number;
  status_counts: Record<string, number>;
  decision_counts: Record<string, number>;
  tracks: PPDBStatsTrackItem[];
}

// =========================================================================
// PUBLIC PPDB / SPMB ONLINE TYPES
// =========================================================================

export interface CheckPPDBResultPayload {
  registration_no: string;
  birth_date: string; // YYYY-MM-DD
}

export interface PPDBPublicResult {
  registration_no: string;
  full_name: string;
  birth_date?: string;
  wave_id?: string;
  wave_name?: string;
  track_code?: string;
  track_name?: string;
  score?: number | null;
  rank?: number | null;
  decision: 'diterima' | 'cadangan' | 'ditolak' | string;
  status: string;
  instruction: string;
  next_step: string;
  published_at?: string;
}

// =========================================================================
// APPLICANT ACCOUNT, REGISTRATION & DASHBOARD TYPES (WEB-PPDB-02 / WEB-PPDB-03)
// =========================================================================

export interface RegisterApplicantPayload {
  full_name: string;
  email: string;
  phone: string;
  password: string;
  password_confirmation: string;
  nik?: string;
  school_id?: string;
}

export interface RegisterApplicantResponse {
  user_id: string;
  email: string;
  phone: string;
  full_name: string;
  status: string;
  expires_in: number;
  message: string;
  otp?: string;
}

export interface VerifyOtpPayload {
  email: string;
  otp: string;
}

export interface VerifyOtpResponse {
  accessToken: string;
  tokenType: string;
  user: {
    id: string;
    username: string;
    email: string;
    phone: string;
    status: string;
    roles: string[];
    registered_name?: string | null;
    nik?: string | null;
  };
  message: string;
}

export interface PPDBLoginPayload {
  identifier: string; // email, phone, or registration_no
  password: string;
}

export interface PPDBLoginResponse {
  accessToken: string;
  tokenType: string;
  expiresIn: number;
  refreshToken?: string;
  user: {
    id: string;
    username: string;
    email: string;
    phone: string;
    status: string;
    roles: string[];
  };
  applicant: ApplicantProfileResponse;
}

export interface ApplicantProfileApplication {
  id: string;
  registration_no: string;
  full_name: string;
  birth_date?: string | null;
  status: ApplicantStatus;
  score?: number | null;
  rank?: number | null;
  decision?: ApplicantDecision | null;
  step_data?: {
    current_step?: number;
    personal?: Record<string, unknown>;
    parent?: Record<string, unknown>;
    school?: Record<string, unknown>;
    track?: Record<string, unknown>;
    [key: string]: unknown;
  } | null;
  wave?: {
    id: string;
    name: string;
    status: string;
  } | null;
  track?: {
    id: string;
    code: string;
    name: string;
    requirements?: {
      id: string;
      document_type: string;
      is_required: boolean;
      description?: string | null;
    }[];
  } | null;
  parents?: {
    id: string;
    parent_type: string;
    name: string;
    occupation?: string | null;
    income_range?: string | null;
    phone?: string | null;
  }[];
  documents?: {
    id: string;
    document_type: string;
    file_id: string;
    status: DocumentStatus;
    reject_reason?: string | null;
  }[];
  re_registered_at?: string | null;
  re_registration?: {
    id?: string;
    status: 'menunggu' | 'diverifikasi' | 'ditolak';
    confirmed_at?: string;
    notes?: string | null;
  } | null;
}

export interface ApplicantProfileResponse {
  id: string;
  username: string;
  email: string;
  phone: string;
  status: string;
  roles: string[];
  applications: ApplicantProfileApplication[];
}
