export interface PointType {
  id: string;
  school_id?: string | null;
  kind: 'pelanggaran' | 'prestasi';
  name: string;
  points: number;
  severity: 'rendah' | 'sedang' | 'tinggi' | 'berat';
  created_at?: string;
  updated_at?: string;
}

export interface PointThreshold {
  id: string;
  school_id?: string | null;
  min_points: number;
  action: string;
  notify_roles: string[];
  created_at?: string;
  updated_at?: string;
}

export interface StudentPointItem {
  id: string;
  student_id: string;
  point_type_id: string;
  recorded_by: string;
  evidence_file_id?: string | null;
  point_date: string;
  notes?: string | null;
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
  point_type?: PointType;
  recorder?: {
    id: string;
    name: string;
    username?: string;
  };
  evidence_file?: {
    id: string;
    filename: string;
    url?: string;
  } | null;
}

export interface StudentPointsSummary {
  student: {
    id: string;
    full_name: string;
    nis?: string;
    nisn?: string;
  };
  total_violation_points: number;
  total_achievement_points: number;
  net_points: number;
  violation_count: number;
  achievement_count: number;
  active_sanction: PointThreshold | null;
  reached_thresholds: PointThreshold[];
  recent_points: StudentPointItem[];
}

export interface RecordStudentPointPayload {
  student_id: string;
  point_type_id: string;
  point_date?: string;
  notes?: string;
  evidence_file_id?: string;
}

export interface RecordStudentPointResult {
  point: StudentPointItem;
  triggered_threshold: PointThreshold | null;
  cumulative_violation_points: number;
}

// =========================================================================
// LOMBA & PRESTASI SISWA (BE-STD-03 / ADM-STD-03)
// =========================================================================

export type CompetitionLevel =
  | 'Sekolah'
  | 'Kecamatan'
  | 'Kota'
  | 'Kabupaten'
  | 'Provinsi'
  | 'Nasional'
  | 'Internasional';

export type CompetitionStatus =
  | 'direncanakan'
  | 'pendaftaran'
  | 'berlangsung'
  | 'selesai'
  | 'dibatalkan';

export type CompetitionEntryStatus =
  | 'terdaftar'
  | 'dikonfirmasi'
  | 'hadir'
  | 'gugur'
  | 'selesai'
  | 'dibatalkan';

export type AchievementVerificationStatus = 'pending' | 'verified' | 'rejected';

export interface CompetitionItem {
  id: string;
  school_id?: string | null;
  name: string;
  organizer?: string | null;
  level: CompetitionLevel | string;
  category?: string | null;
  held_at: string;
  registration_deadline?: string | null;
  status: CompetitionStatus | string;
  description?: string | null;
  created_at?: string;
  updated_at?: string;
  entries_count?: number;
  achievements_count?: number;
  entries?: CompetitionEntryItem[];
  achievements?: AchievementItem[];
}

export interface CompetitionEntryItem {
  id: string;
  competition_id: string;
  student_id: string;
  coach_teacher_id?: string | null;
  status: CompetitionEntryStatus | string;
  result?: string | null;
  achievement_id?: string | null;
  notes?: string | null;
  created_at?: string;
  updated_at?: string;
  competition?: CompetitionItem;
  student?: {
    id: string;
    full_name: string;
    nis?: string;
    nisn?: string;
    classes?: Array<{ id: string; name: string }>;
  };
  coach?: {
    id: string;
    full_name: string;
    nip?: string;
  };
  achievement?: AchievementItem;
}

export interface AchievementItem {
  id: string;
  school_id?: string | null;
  student_id: string;
  competition_id?: string | null;
  title: string;
  rank_level: string;
  achieved_at: string;
  certificate_file_id?: string | null;
  photo_file_id?: string | null;
  points_awarded: number;
  verification_status: AchievementVerificationStatus | string;
  verified_by?: string | null;
  verified_at?: string | null;
  rejection_reason?: string | null;
  student_point_id?: string | null;
  notes?: string | null;
  created_at?: string;
  updated_at?: string;
  student?: {
    id: string;
    full_name: string;
    nis?: string;
    nisn?: string;
    classes?: Array<{ id: string; name: string }>;
  };
  competition?: CompetitionItem;
  certificateFile?: {
    id: string;
    filename?: string;
    url?: string;
  } | null;
  photoFile?: {
    id: string;
    filename?: string;
    url?: string;
  } | null;
  verifiedBy?: {
    id: string;
    name?: string;
    username?: string;
  } | null;
}

export interface CompetitionSummary {
  total_competitions: number;
  active_competitions: number;
  total_entries: number;
  total_achievements: number;
  verified_achievements: number;
  pending_verification: number;
  medals: {
    emas: number;
    perak: number;
    perunggu: number;
    finalis: number;
  };
}

export interface CreateCompetitionPayload {
  name: string;
  organizer?: string;
  level: CompetitionLevel | string;
  category?: string;
  held_at: string;
  registration_deadline?: string;
  status?: CompetitionStatus | string;
  description?: string;
}

export interface UpdateCompetitionPayload {
  name?: string;
  organizer?: string;
  level?: CompetitionLevel | string;
  category?: string;
  held_at?: string;
  registration_deadline?: string;
  status?: CompetitionStatus | string;
  description?: string;
}

export interface RegisterCompetitionEntryPayload {
  student_id: string;
  coach_teacher_id?: string;
  status?: CompetitionEntryStatus | string;
  result?: string;
  notes?: string;
}

export interface UpdateCompetitionEntryPayload {
  status?: CompetitionEntryStatus | string;
  result?: string;
  coach_teacher_id?: string;
  notes?: string;
}

export interface CreateAchievementPayload {
  student_id: string;
  competition_id?: string;
  title: string;
  rank_level: string;
  achieved_at: string;
  certificate_file_id?: string;
  photo_file_id?: string;
  points_awarded?: number;
  point_type_id?: string;
  verification_status?: AchievementVerificationStatus | string;
  notes?: string;
}

export interface UpdateAchievementPayload {
  student_id?: string;
  competition_id?: string;
  title?: string;
  rank_level?: string;
  achieved_at?: string;
  certificate_file_id?: string;
  photo_file_id?: string;
  points_awarded?: number;
  notes?: string;
}

export interface VerifyAchievementPayload {
  status: 'verified' | 'rejected';
  rejection_reason?: string;
}
