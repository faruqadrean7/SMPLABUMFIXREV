import { useQuery } from '@tanstack/react-query';
import { apiClient } from './api-client';
import { ApiResponse } from '@/types/auth';

export interface TeacherDashboardData {
  teacherName: string;
  nip: string;
  photoFileId: string | null;
  photoUrl: string | null;
  assignedClassesCount: number;
  todaySchedulesCount: number;
  pendingJournalsCount: number;
}

export interface TeacherClassItem {
  id: string;
  name: string;
  gradeLevel: number;
  studentCount: number;
}

export interface TeacherSubjectItem {
  id: string;
  name: string;
  code: string;
  subject_group?: string;
}

export interface TeacherScheduleItem {
  id: string;
  dayOfWeek: number;
  dayName: string;
  startTime: string;
  endTime: string;
  classId: string;
  className: string;
  subjectId: string;
  subjectName: string;
  roomName: string;
  isAttendanceRecorded?: boolean;
}

export function useTeacherDashboard() {
  return useQuery<TeacherDashboardData>({
    queryKey: ['teacher-dashboard'],
    queryFn: async () => {
      const res = await apiClient.get<ApiResponse<TeacherDashboardData>>('/me/teacher/dashboard');
      if (res.data?.data) {
        return res.data.data;
      }
      return {
        teacherName: 'Guru Pengampu',
        nip: '',
        photoFileId: null,
        photoUrl: null,
        assignedClassesCount: 0,
        todaySchedulesCount: 0,
        pendingJournalsCount: 0,
      };
    },
    staleTime: 60 * 1000,
  });
}

export function useTeacherClasses() {
  return useQuery<TeacherClassItem[]>({
    queryKey: ['teacher-classes'],
    queryFn: async () => {
      const res = await apiClient.get<ApiResponse<TeacherClassItem[]>>('/me/teacher/classes');
      if (res.data?.data && Array.isArray(res.data.data)) {
        return res.data.data;
      }
      return [];
    },
    staleTime: 5 * 60 * 1000,
  });
}

export function useTeacherSubjects() {
  return useQuery<TeacherSubjectItem[]>({
    queryKey: ['teacher-subjects'],
    queryFn: async () => {
      try {
        const res = await apiClient.get<ApiResponse<TeacherSubjectItem[]>>('/me/teacher/subjects');
        if (res.data?.data && Array.isArray(res.data.data) && res.data.data.length > 0) {
          return res.data.data;
        }
      } catch {
        // Fallback: derive from schedules if endpoint is not present on backend
      }

      try {
        const schedRes = await apiClient.get<ApiResponse<TeacherScheduleItem[]>>('/me/teacher/schedules');
        if (schedRes.data?.data && Array.isArray(schedRes.data.data)) {
          const map = new Map<string, TeacherSubjectItem>();
          schedRes.data.data.forEach((s) => {
            if (s.subjectId && !map.has(s.subjectId)) {
              map.set(s.subjectId, {
                id: s.subjectId,
                name: s.subjectName || 'Mata Pelajaran',
                code: s.subjectName || 'MAPEL',
              });
            }
          });
          return Array.from(map.values());
        }
      } catch {}

      return [];
    },
    staleTime: 5 * 60 * 1000,
  });
}

export function useTeacherSchedules() {
  return useQuery<TeacherScheduleItem[]>({
    queryKey: ['teacher-schedules'],
    queryFn: async () => {
      const res = await apiClient.get<ApiResponse<TeacherScheduleItem[]>>('/me/teacher/schedules');
      if (res.data?.data && Array.isArray(res.data.data)) {
        return res.data.data;
      }
      return [];
    },
    staleTime: 5 * 60 * 1000,
  });
}
