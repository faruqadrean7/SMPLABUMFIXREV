'use client';

import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from './api-client';
import { ApiResponse } from '@/types/auth';

export interface ChildSummary {
  id: string;
  name: string;
  nis: string;
  nisn: string;
  class_id?: string;
  class_name?: string;
  className?: string;
  academic_year?: string;
  academicYear?: string;
  gender: 'male' | 'female' | 'L' | 'P';
  avatar_url?: string | null;
  avatarUrl?: string | null;
  status: string;
}

export interface ChildDetail {
  id: string;
  name: string;
  nis: string;
  nisn: string;
  gender: string;
  birth_place?: string;
  birth_date?: string;
  religion?: string;
  address?: string;
  class?: {
    id: string;
    name: string;
    academic_year?: string;
  };
  homeroom_teacher?: {
    name: string;
    nip?: string;
    phone?: string;
  };
  guardian_relationship?: string;
}

export interface ChildAttendanceRecord {
  date: string;
  status: 'hadir' | 'sakit' | 'izin' | 'alpa' | 'terlambat' | 'present' | 'sick' | 'permission' | 'unexcused';
  note?: string | null;
  notes?: string | null;
  subject?: string | null;
  check_in_time?: string;
  checkInTime?: string;
}

export interface ChildAttendanceSummary {
  present_days?: number;
  presentDays?: number;
  sick_days?: number;
  sickDays?: number;
  leave_days?: number;
  leaveDays?: number;
  unexcused_days?: number;
  unexcusedDays?: number;
  total_effective_days?: number;
  totalDays?: number;
  attendance_percentage?: number;
  percentage?: number;
  hadir?: number;
  sakit?: number;
  izin?: number;
  alpa?: number;
  terlambat?: number;
  total_hari?: number;
  persentase_kehadiran?: number;
}

export interface ChildAttendanceData {
  child?: { id: string; name: string; nis: string };
  class?: { id: string; name: string };
  summary: ChildAttendanceSummary;
  records?: ChildAttendanceRecord[];
  history?: ChildAttendanceRecord[];
}

export interface ChildGradeSubject {
  subjectId?: string;
  subject_id?: string;
  subjectName?: string;
  subject_name?: string;
  subjectCode?: string;
  subject_code?: string;
  teacherName?: string;
  teacher_name?: string;
  kkm?: number;
  passingGrade?: number;
  score?: number;
  finalScore?: number;
  letterGrade?: string;
  letter_grade?: string;
  isPassed?: boolean;
  is_passed?: boolean;
  description?: string;
}

export type ChildGradesResult = ChildGradeSubject[] | { grades: ChildGradeSubject[]; average_score?: number };

export interface ChildReportCardGrade {
  subject: string;
  score: number;
  predicate: string;
  description: string;
}

export interface ChildReportCardData {
  periodId?: string;
  periodName?: string;
  academicYear?: string;
  semester?: string;
  totalScore?: number;
  averageScore?: number;
  classRank?: number;
  homeroomNote?: string;
  grades: ChildReportCardGrade[];
  verifyToken?: string;
}

export interface ChildCharacterNoteData {
  periodId?: string;
  periodName?: string;
  academicYear?: string;
  ratings: Record<string, string>;
  homeroomNote?: string;
  updatedAt?: string;
}

export interface ChildProgressReportItem {
  id: string;
  title: string;
  periodName: string;
  category: string;
  narrative: string;
  teacherName: string;
  date: string;
}

export interface LeaveRequestItem {
  id: string;
  student_id: string;
  requested_by: string;
  leave_type: 'sakit' | 'izin';
  start_date: string;
  end_date: string;
  reason: string;
  attachment_file_id?: string | null;
  status: 'menunggu' | 'disetujui' | 'ditolak';
  decided_by?: string | null;
  decision_notes?: string | null;
  created_at: string;
  student?: {
    id: string;
    full_name: string;
    nis: string;
    classes?: Array<{ name: string }>;
  };
}

export interface CreateLeaveRequestPayload {
  student_id: string;
  leave_type: 'sakit' | 'izin';
  start_date: string;
  end_date: string;
  reason: string;
  attachment_file_id?: string;
}

export interface CounselingRequestItem {
  id: string;
  student_id?: string;
  requested_by?: string;
  requester_role?: string;
  topic: string;
  category?: string;
  preferred_date?: string;
  preferred_time?: string;
  preferred_counselor_id?: string | null;
  status: 'pending' | 'scheduled' | 'dijadwalkan' | 'terjadwal' | 'completed' | 'selesai' | 'rejected' | 'ditolak' | 'cancelled' | string;
  scheduled_at?: string | null;
  scheduledTime?: string | null;
  counselor_id?: string | null;
  counselorName?: string;
  notes: string | null;
  rejection_reason?: string | null;
  roomName?: string;
  room?: string;
  createdAt?: string;
  created_at?: string;
  preferredDate?: string;
  preferredTime?: string;
  preferredSchedule?: string;
  student?: {
    id: string;
    full_name?: string;
    nis?: string;
    classes?: Array<{ id: string; name: string }>;
  };
  counselor?: {
    id: string;
    name: string;
  };
  preferredCounselor?: {
    id: string;
    name: string;
  };
  preferred_counselor?: {
    id: string;
    name: string;
  };
}

export interface CreateCounselingPayload {
  student_id: string;
  topic: string;
  preferred_date?: string;
  preferred_time?: string;
  preferred_counselor_id?: string;
  notes?: string;
  category?: string;
  reason?: string;
  preferred_schedule?: string;
  counselor_name?: string;
}

// ─── Query Hooks ───

export function useGuardianChildren() {
  return useQuery({
    queryKey: ['guardian-children'],
    queryFn: async () => {
      const res = await apiClient.get<ApiResponse<ChildSummary[]>>('/me/children');
      return res.data.data;
    },
    staleTime: 1000 * 60 * 5,
  });
}

export function useChildDetail(childId: string | undefined | null) {
  return useQuery({
    queryKey: ['child-detail', childId],
    queryFn: async () => {
      if (!childId) return null;
      const res = await apiClient.get<ApiResponse<ChildDetail>>(`/me/children/${childId}`);
      return res.data.data;
    },
    enabled: !!childId,
    staleTime: 1000 * 60 * 5,
  });
}

export function useChildAttendance(childId: string | undefined | null) {
  return useQuery({
    queryKey: ['child-attendance', childId],
    queryFn: async () => {
      if (!childId) return null;
      const res = await apiClient.get<ApiResponse<ChildAttendanceData>>(`/me/children/${childId}/attendance`);
      return res.data.data;
    },
    enabled: !!childId,
    staleTime: 1000 * 60 * 2,
  });
}

export function useChildGrades(childId: string | undefined | null) {
  return useQuery({
    queryKey: ['child-grades', childId],
    queryFn: async () => {
      if (!childId) return null;
      const res = await apiClient.get<ApiResponse<ChildGradesResult>>(`/me/children/${childId}/grades`);
      return res.data.data;
    },
    enabled: !!childId,
    staleTime: 1000 * 60 * 5,
  });
}

export function useChildReportCards(childId: string | undefined | null) {
  return useQuery({
    queryKey: ['child-report-cards', childId],
    queryFn: async () => {
      if (!childId) return null;
      const res = await apiClient.get<ApiResponse<ChildReportCardData>>(`/me/children/${childId}/report-cards`);
      return res.data.data;
    },
    enabled: !!childId,
    staleTime: 1000 * 60 * 5,
  });
}

export function useChildCharacterNotes(childId: string | undefined | null) {
  return useQuery({
    queryKey: ['child-character-notes', childId],
    queryFn: async () => {
      if (!childId) return null;
      const res = await apiClient.get<ApiResponse<ChildCharacterNoteData>>(`/me/children/${childId}/character-notes`);
      return res.data.data;
    },
    enabled: !!childId,
    staleTime: 1000 * 60 * 5,
  });
}

export function useChildProgressReports(childId: string | undefined | null) {
  return useQuery({
    queryKey: ['child-progress-reports', childId],
    queryFn: async () => {
      if (!childId) return null;
      const res = await apiClient.get<ApiResponse<ChildProgressReportItem[]>>(`/me/children/${childId}/progress-reports`);
      return res.data.data;
    },
    enabled: !!childId,
    staleTime: 1000 * 60 * 5,
  });
}

export function useLeaveRequests() {
  return useQuery({
    queryKey: ['leave-requests'],
    queryFn: async () => {
      const res = await apiClient.get<any>('/leave-requests');
      const data = res.data?.data;
      return Array.isArray(data) ? (data as LeaveRequestItem[]) : Array.isArray(data?.data) ? (data.data as LeaveRequestItem[]) : [];
    },
    staleTime: 1000 * 60 * 2,
  });
}

export function useCreateLeaveRequest() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (payload: CreateLeaveRequestPayload) => {
      const res = await apiClient.post('/leave-requests', payload);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['leave-requests'] });
      queryClient.invalidateQueries({ queryKey: ['child-attendance'] });
    },
  });
}

export function useGuardianCounselingRequests(studentId?: string) {
  return useQuery({
    queryKey: ['guardian-counseling-requests', studentId],
    queryFn: async () => {
      const params = studentId ? { student_id: studentId } : undefined;
      const res = await apiClient.get<any>('/counseling/requests', { params });
      const data = res.data?.data;
      return Array.isArray(data) ? (data as CounselingRequestItem[]) : Array.isArray(data?.data) ? (data.data as CounselingRequestItem[]) : [];
    },
    staleTime: 1000 * 60 * 2,
  });
}

export function useCreateGuardianCounselingRequest() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (payload: CreateCounselingPayload) => {
      const res = await apiClient.post('/counseling/requests', {
        student_id: payload.student_id,
        topic: payload.topic,
        preferred_date: payload.preferred_date || undefined,
        preferred_time: payload.preferred_time || undefined,
        preferred_counselor_id: payload.preferred_counselor_id || undefined,
        notes: payload.notes || payload.reason || undefined,
      });
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['guardian-counseling-requests'] });
    },
  });
}
