export interface FacilityItem {
  id: string;
  school_id?: string | null;
  room_id?: string | null;
  code?: string | null;
  name: string;
  location?: string | null;
  description?: string | null;
  facility_type: 'ruang' | 'alat';
  capacity?: number | null;
  requires_approval: boolean;
  is_active: boolean;
  status: 'tersedia' | 'pemeliharaan' | 'nonaktif';
  created_at?: string;
  updated_at?: string;
  room?: {
    id: string;
    code: string;
    name: string;
    room_type: string;
    capacity?: number;
  } | null;
}

export interface BookingItemRequirement {
  id: string;
  booking_id: string;
  facility_id: string;
  quantity: number;
  notes?: string | null;
  facility?: FacilityItem | null;
}

export interface BookingItem {
  id: string;
  school_id?: string | null;
  facility_id: string;
  requested_by: string;
  start_at: string;
  end_at: string;
  purpose: string;
  attendees_count?: number | null;
  status: 'diajukan' | 'disetujui' | 'ditolak' | 'selesai' | 'dibatalkan';
  decided_by?: string | null;
  decision_notes?: string | null;
  created_at?: string;
  updated_at?: string;
  facility?: FacilityItem | null;
  requester?: {
    id: string;
    username: string;
    email?: string;
  } | null;
  decider?: {
    id: string;
    username: string;
    email?: string;
  } | null;
  items?: BookingItemRequirement[];
}

export interface AvailabilityResult {
  facility: FacilityItem & { is_eligible: boolean };
  is_available: boolean;
  is_eligible: boolean;
  eligibility_message?: string | null;
  conflicts: Array<{
    id: string;
    start_at: string;
    end_at: string;
    purpose: string;
    requester?: {
      id: string;
      username: string;
    } | null;
  }>;
  day_bookings: BookingItem[];
}

export interface BookingSummary {
  total_facilities: number;
  active_facilities: number;
  total_bookings: number;
  pending_bookings: number;
  approved_bookings: number;
  rejected_bookings: number;
  completed_bookings: number;
  cancelled_bookings: number;
  today_bookings: number;
}

export interface CreateFacilityPayload {
  name: string;
  code?: string;
  facility_type: 'ruang' | 'alat';
  room_id?: string | null;
  location?: string;
  description?: string;
  capacity?: number;
  requires_approval?: boolean;
  is_active?: boolean;
  status?: 'tersedia' | 'pemeliharaan' | 'nonaktif';
  school_id?: string;
}

export interface UpdateFacilityPayload {
  name?: string;
  code?: string;
  facility_type?: 'ruang' | 'alat';
  room_id?: string | null;
  location?: string;
  description?: string;
  capacity?: number;
  requires_approval?: boolean;
  is_active?: boolean;
  status?: 'tersedia' | 'pemeliharaan' | 'nonaktif';
  school_id?: string;
}

export interface CreateBookingPayload {
  facility_id: string;
  start_at: string;
  end_at: string;
  purpose: string;
  attendees_count?: number;
  items?: Array<{
    facility_id: string;
    quantity: number;
    notes?: string;
  }>;
}

export interface DecideBookingPayload {
  status: 'disetujui' | 'ditolak' | 'selesai';
  decision_notes?: string;
}
