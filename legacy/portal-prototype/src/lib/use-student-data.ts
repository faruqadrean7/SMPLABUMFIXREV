'use client';

import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from './api-client';
import { ApiResponse } from '@/types/auth';

export interface StudentDashboardData {
  name: string;
  nis: string;
  nisn: string;
  className: string;
  photoFileId: string | null;
  photoUrl: string | null;
  todaySchedule: Array<{
    id: string;
    dayOfWeek: number;
    dayName: string;
    startTime: string;
    endTime: string;
    subjectName: string;
    teacherName: string;
    roomName: string;
  }>;
  upcomingExams: Array<{
    id: string;
    title: string;
    subjectName: string;
    durationMinutes: number;
    totalQuestions: number;
    startTime: string;
    endTime: string;
    status: string;
  }>;
  attendanceRate: number;
  totalAchievements: number;
}

export interface StudentScheduleItem {
  id: string;
  dayOfWeek: number;
  dayName: string;
  startTime: string;
  endTime: string;
  subjectName: string;
  teacherName: string;
  roomName: string;
}

export interface StudentGradeItem {
  subjectId: string;
  subjectName: string;
  subjectCode: string;
  teacherName: string;
  finalScore: number;
  letterGrade: string;
  isPassed: boolean;
}

export interface StudentTaskItem {
  id: string;
  title: string;
  subjectName: string;
  dueDate: string;
  dueTime: string;
  status: 'aktif' | 'dikumpulkan' | 'dinilai';
  description: string;
  teacherName: string;
  maxScore: number;
  score: number | null;
  teacherFeedback: string | null;
  submission: {
    id: string;
    file_name: string;
    submitted_at: string;
    notes: string | null;
  } | null;
}

export interface StudentExamItem {
  id: string;
  title: string;
  subjectName: string;
  durationMinutes: number;
  totalQuestions: number;
  startTime: string;
  endTime: string;
  status: string;
  roomName?: string;
  kktp?: number;
  tokenRequired?: string;
  score?: number;
}

export interface CounselingRequestItem {
  id: string;
  topic: string;
  category: string;
  requestDate?: string;
  preferredSchedule?: string;
  counselorName?: string;
  status: 'menunggu' | 'dijadwalkan' | 'terjadwal' | 'selesai' | 'ditolak';
  notes: string | null;
  scheduledTime?: string | null;
  roomName?: string;
  room?: string;
  createdAt?: string;
  preferredDate?: string;
  preferredTime?: string;
}

export interface CreateCounselingPayload {
  topic?: string;
  reason?: string;
  preferred_date?: string;
  preferred_time?: string;
  category?: string;
  is_urgent?: boolean;
  counselor_name?: string;
  preferred_schedule?: string;
  notes?: string;
}

export interface SubmitTaskPayload {
  taskId: string;
  notes?: string;
  drive_link?: string;
  file_name?: string;
}

export function useStudentDashboard() {
  return useQuery({
    queryKey: ['student-dashboard'],
    queryFn: async () => {
      const res = await apiClient.get<ApiResponse<StudentDashboardData>>('/me/student-dashboard');
      return res.data.data;
    },
    staleTime: 1000 * 60 * 2,
  });
}

export function useStudentSchedule() {
  return useQuery({
    queryKey: ['student-schedule'],
    queryFn: async () => {
      const res = await apiClient.get<ApiResponse<StudentScheduleItem[]>>('/me/schedule');
      return res.data.data;
    },
    staleTime: 1000 * 60 * 5,
  });
}

export function useStudentGrades() {
  return useQuery({
    queryKey: ['student-grades'],
    queryFn: async () => {
      const res = await apiClient.get<ApiResponse<StudentGradeItem[]>>('/me/grades');
      return res.data.data;
    },
    staleTime: 1000 * 60 * 5,
  });
}

export function useStudentTasks() {
  return useQuery({
    queryKey: ['student-tasks'],
    queryFn: async () => {
      const res = await apiClient.get<ApiResponse<StudentTaskItem[]>>('/me/tasks');
      return res.data.data;
    },
    staleTime: 1000 * 60 * 2,
  });
}

export function useSubmitTask() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async ({ taskId, ...payload }: SubmitTaskPayload) => {
      const res = await apiClient.post(`/me/tasks/${taskId}/submit`, payload);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['student-tasks'] });
      queryClient.invalidateQueries({ queryKey: ['student-dashboard'] });
    },
  });
}

export function useStudentExams() {
  return useQuery({
    queryKey: ['student-exams'],
    queryFn: async () => {
      const res = await apiClient.get<ApiResponse<StudentExamItem[]>>('/me/exams');
      return res.data.data;
    },
    staleTime: 1000 * 60 * 2,
  });
}

export function useCounselingRequests() {
  return useQuery({
    queryKey: ['counseling-requests'],
    queryFn: async () => {
      const res = await apiClient.get<ApiResponse<CounselingRequestItem[]>>('/me/counseling-requests');
      return res.data.data;
    },
    staleTime: 1000 * 60 * 2,
  });
}

export function useCreateCounselingRequest() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (payload: CreateCounselingPayload) => {
      const res = await apiClient.post('/me/counseling-requests', payload);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['counseling-requests'] });
    },
  });
}

export interface SchoolAnnouncementItem {
  id: string;
  title: string;
  body: string;
  content?: string;
  priority?: string;
  is_pinned?: boolean;
  publish_at?: string;
  published_at?: string;
  created_at?: string;
}

export function useSchoolAnnouncements() {
  return useQuery({
    queryKey: ['school-announcements'],
    queryFn: async () => {
      const res = await apiClient.get<ApiResponse<SchoolAnnouncementItem[]>>('/announcements', {
        params: { published_only: true, per_page: 5 },
      });
      return res.data.data;
    },
    staleTime: 1000 * 60 * 5,
  });
}

export interface TeachingMaterialItem {
  id: string;
  title: string;
  description: string;
  material_type: string;
  version: number;
  subject?: { name: string; code?: string };
  file?: { url?: string; object_key?: string };
}

export function useTeachingMaterials() {
  return useQuery({
    queryKey: ['teaching-materials'],
    queryFn: async () => {
      const res = await apiClient.get<ApiResponse<TeachingMaterialItem[]>>('/teaching-materials', {
        params: { per_page: 20 },
      });
      return res.data.data;
    },
    staleTime: 1000 * 60 * 5,
  });
}


