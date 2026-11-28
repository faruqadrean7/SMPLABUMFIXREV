export interface GuestItem {
  id: string;
  school_id?: string | null;
  target_user_id?: string | null;
  full_name: string;
  institution?: string | null;
  phone: string;
  purpose: string;
  photo_file_id?: string | null;
  signature_file_id?: string | null;
  signature_hash?: string | null;
  check_in: string;
  check_out?: string | null;
  created_at?: string;
  updated_at?: string;
  target_user?: {
    id: string;
    username: string;
    email?: string;
  } | null;
  photo_file?: {
    id: string;
    object_key: string;
    mime_type: string;
  } | null;
  signature_file?: {
    id: string;
    object_key: string;
    mime_type: string;
    size_bytes?: number;
  } | null;
}

export interface GuestSignatureDetail {
  guest_id: string;
  full_name: string;
  signature_file_id: string;
  signature_hash: string;
  integrity_verified: boolean;
  mime_type: string;
  size_bytes: number;
  verified_at: string;
}

export interface RegisterGuestPayload {
  full_name: string;
  institution?: string;
  phone: string;
  purpose: string;
  target_user_id?: string;
  school_id?: string;
}

export interface ComplaintEvent {
  id: string;
  complaint_id: string;
  actor_id?: string | null;
  event_type: 'created' | 'status_update' | 'note' | string;
  response_text: string;
  created_at: string;
  actor?: {
    id: string;
    username: string;
    email?: string;
  } | null;
}

export interface ComplaintAttachment {
  id: string;
  object_key?: string;
  mime_type?: string;
  size_bytes?: number;
  signed_url?: string;
  expires_at?: string;
}

export interface ComplaintItem {
  id: string;
  school_id?: string | null;
  ticket_code: string;
  category: 'fasilitas' | 'akademik' | 'pelayanan' | 'bullying' | 'pungli' | 'lainnya' | string;
  content: string;
  is_anonymous: boolean;
  status: 'baru' | 'ditinjau' | 'ditindaklanjuti' | 'selesai' | 'ditolak';
  severity?: 'rendah' | 'sedang' | 'tinggi' | 'kritis';
  due_at?: string | null;
  first_responded_at?: string | null;
  escalated_at?: string | null;
  is_overdue?: boolean;
  sla_state?: 'selesai' | 'tepat_waktu' | 'mendekati' | 'terlambat' | 'terlambat_berat';
  assigned_to?: string | null;
  reporter_user_id?: string | null;
  created_at: string;
  updated_at: string;
  reporter?: {
    id: string;
    username: string;
    email?: string;
  } | null;
  assigned_to_user?: {
    id: string;
    username: string;
    email?: string;
  } | null;
  events?: ComplaintEvent[];
  attachments?: ComplaintAttachment[];
}

export interface CreateComplaintPayload {
  category: string;
  content: string;
  is_anonymous: boolean;
  school_id?: string;
  /** BE-GUEST-04: id file hasil presign lalu konfirmasi unggahan (maks 3) */
  attachment_file_ids?: string[];
}

export interface UpdateComplaintStatusPayload {
  status: 'baru' | 'ditinjau' | 'ditindaklanjuti' | 'selesai' | 'ditolak';
  response_text: string;
  assigned_to?: string | null;
}

export interface PublicComplaintTrackResult {
  ticket_code: string;
  category: string;
  is_anonymous?: boolean;
  content?: string | null;
  content_redacted?: boolean;
  status: string;
  severity?: 'rendah' | 'sedang' | 'tinggi' | 'kritis';
  sla_state?: 'selesai' | 'tepat_waktu' | 'mendekati' | 'terlambat' | 'terlambat_berat';
  due_at?: string | null;
  first_responded_at?: string | null;
  created_at: string;
  updated_at: string;
  timeline: Array<{
    event_type: string;
    response_text: string;
    created_at: string;
  }>;
}

export interface PartnershipItem {
  id: string;
  school_id?: string | null;
  partner_name: string;
  mou_number?: string | null;
  partner_type: 'industri' | 'universitas' | 'instansi' | 'komunitas' | 'lainnya' | string;
  contact_person?: string | null;
  phone?: string | null;
  email?: string | null;
  address?: string | null;
  scope: string;
  valid_from: string;
  valid_until: string;
  status: 'aktif' | 'kedaluwarsa' | 'dihentikan';
  notes?: string | null;
  mou_file_id?: string | null;
  created_at?: string;
  updated_at?: string;
}

export interface CreatePartnershipPayload {
  partner_name: string;
  mou_number?: string;
  partner_type: string;
  contact_person?: string;
  phone?: string;
  email?: string;
  address?: string;
  scope: string;
  valid_from: string;
  valid_until: string;
  status?: string;
  notes?: string;
}

export interface AlumniItem {
  id: string;
  school_id?: string | null;
  student_id?: string | null;
  full_name: string;
  graduation_year: number;
  current_activity: 'kuliah' | 'kerja' | 'wirausaha' | 'mencari_kerja' | 'lainnya' | string;
  institution_name?: string | null;
  email?: string | null;
  phone?: string | null;
  is_public: boolean;
  created_at?: string;
  updated_at?: string;
}
