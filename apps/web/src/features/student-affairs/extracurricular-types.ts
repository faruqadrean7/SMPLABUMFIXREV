export interface ExtracurricularItem {
  id: string;
  school_id?: string | null;
  coach_id?: string | null;
  name: string;
  category?: string | null;
  description?: string | null;
  quota: number;
  schedule_day?: string | null;
  schedule_time?: string | null;
  is_active: boolean;
  active_members_count?: number;
  available_quota?: number;
  coach?: {
    id: string;
    nip?: string;
    full_name: string;
  } | null;
  created_at?: string;
  updated_at?: string;
}

export type ExtracurricularRole = 'ketua' | 'wakil_ketua' | 'sekretaris' | 'bendahara' | 'anggota';
export type ExtracurricularStatus = 'aktif' | 'nonaktif' | 'keluar';

export interface ExtracurricularMemberItem {
  id: string;
  extracurricular_id: string;
  student_id: string;
  role: ExtracurricularRole;
  status: ExtracurricularStatus;
  final_grade?: string | null;
  joined_at?: string | null;
  student?: {
    id: string;
    nis?: string;
    nisn?: string;
    full_name: string;
    classes?: Array<{
      id: string;
      name: string;
    }>;
  };
  extracurricular?: ExtracurricularItem;
  created_at?: string;
  updated_at?: string;
}

export interface ExtracurricularAttendanceItem {
  student_id: string;
  status: 'hadir' | 'izin' | 'sakit' | 'alpa';
  student_name?: string;
}

export interface ExtracurricularJournalItem {
  id: string;
  extracurricular_id: string;
  session_date: string;
  start_time?: string | null;
  end_time?: string | null;
  activity: string;
  attendance?: ExtracurricularAttendanceItem[] | null;
  notes?: string | null;
  extracurricular?: ExtracurricularItem;
  created_at?: string;
  updated_at?: string;
}

export interface ActivityDocumentItem {
  id: string;
  extracurricular_id: string;
  file_id: string;
  doc_type: 'proposal' | 'laporan';
  title: string;
  description?: string | null;
  uploaded_by?: string | null;
  uploader?: {
    id: string;
    name: string;
    username?: string;
  } | null;
  file?: {
    id: string;
    filename?: string;
    mime_type?: string;
    size_bytes?: number;
    url?: string;
  } | null;
  extracurricular?: ExtracurricularItem;
  created_at?: string;
  updated_at?: string;
}

export interface CreateExtracurricularPayload {
  name: string;
  category?: string;
  description?: string;
  coach_id?: string;
  quota?: number;
  schedule_day?: string;
  schedule_time?: string;
  is_active?: boolean;
}

export interface UpdateExtracurricularPayload {
  name?: string;
  category?: string;
  description?: string;
  coach_id?: string | null;
  quota?: number;
  schedule_day?: string;
  schedule_time?: string;
  is_active?: boolean;
}

export interface RegisterMemberPayload {
  extracurricular_id: string;
  student_id?: string;
  role?: ExtracurricularRole;
}

export interface UpdateMemberPayload {
  role?: ExtracurricularRole;
  status?: ExtracurricularStatus;
  final_grade?: string | null;
}

export interface CreateJournalPayload {
  extracurricular_id: string;
  session_date: string;
  start_time?: string;
  end_time?: string;
  activity: string;
  attendance?: ExtracurricularAttendanceItem[];
  notes?: string;
}

export interface CreateActivityDocumentPayload {
  extracurricular_id: string;
  file_id: string;
  doc_type: 'proposal' | 'laporan';
  title: string;
  description?: string;
}
