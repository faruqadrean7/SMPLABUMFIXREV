import { useMemo, useCallback } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { guruApi } from './guru-api';
import {
  TeacherAttendanceSessionPayload,
  TeacherJournalItem,
  TeacherMaterialItem,
  TeacherAssignmentItem,
  TeacherStudentGrade,
  StudentPointPayload,
  CounselingReferralPayload,
  FacilityBookingPayload,
  SarprasTicketPayload,
} from './types';

export interface TeacherAssignedSubject {
  id: string;
  name: string;
}

export interface TeacherAssignedClass {
  id: string;
  name: string;
}

export function useTeacherClasses() {
  return useQuery({
    queryKey: ['guru', 'classes'],
    queryFn: () => guruApi.getTeacherClasses(),
    staleTime: 1000 * 60 * 10,
  });
}

export function useTeacherSchedules() {
  return useQuery({
    queryKey: ['guru', 'schedules'],
    queryFn: () => guruApi.getTeacherSchedules(),
    staleTime: 1000 * 60 * 10,
  });
}

/**
 * Hook untuk mengekstrak mata pelajaran dan kelas yang diampu guru dari jadwal mengajar aktual (SSOT).
 * Mendukung pengelompokan kelas per mata pelajaran dan auto-selection tanpa fallback dummy.
 */
export function useTeacherAssignmentsSummary() {
  const { data: schedules = [], isLoading, isError, error, refetch } = useTeacherSchedules();

  const subjects = useMemo<TeacherAssignedSubject[]>(() => {
    const map = new Map<string, TeacherAssignedSubject>();
    schedules.forEach((s) => {
      if (s.subjectId && !map.has(s.subjectId)) {
        map.set(s.subjectId, {
          id: s.subjectId,
          name: s.subjectName || 'Mata Pelajaran',
        });
      }
    });
    return Array.from(map.values());
  }, [schedules]);

  const getClassesForSubject = useCallback(
    (subjectId?: string): TeacherAssignedClass[] => {
      if (!subjectId) return [];
      const map = new Map<string, TeacherAssignedClass>();
      schedules
        .filter((s) => s.subjectId === subjectId)
        .forEach((s) => {
          if (s.classId && !map.has(s.classId)) {
            map.set(s.classId, {
              id: s.classId,
              name: s.className || 'Kelas',
            });
          }
        });
      return Array.from(map.values());
    },
    [schedules]
  );

  return {
    schedules,
    subjects,
    getClassesForSubject,
    isLoading,
    isError,
    error,
    refetch,
  };
}

export function useAttendanceStudents(classId: string) {
  return useQuery({
    queryKey: ['guru', 'attendance-students', classId],
    queryFn: () => guruApi.getAttendanceStudents(classId),
    enabled: Boolean(classId),
  });
}

export function useTeacherJournals() {
  return useQuery({
    queryKey: ['guru', 'journals'],
    queryFn: () => guruApi.getJournals(),
    staleTime: 1000 * 60 * 5,
  });
}

export function useTeacherGradeComponents(classId: string, subjectId: string) {
  return useQuery({
    queryKey: ['guru', 'grade-components', classId, subjectId],
    queryFn: () => guruApi.getGradeComponents(classId, subjectId),
    enabled: Boolean(classId && subjectId),
  });
}

export function useTeacherStudentGrades(classId: string, subjectId: string) {
  return useQuery({
    queryKey: ['guru', 'grades', classId, subjectId],
    queryFn: () => guruApi.getStudentGrades(classId, subjectId),
    enabled: Boolean(classId && subjectId),
  });
}

export function useTeacherMaterials() {
  return useQuery({
    queryKey: ['guru', 'materials'],
    queryFn: () => guruApi.getMaterials(),
    staleTime: 1000 * 60 * 5,
  });
}

export function useTeacherAssignments() {
  return useQuery({
    queryKey: ['guru', 'assignments'],
    queryFn: () => guruApi.getAssignments(),
    staleTime: 1000 * 60 * 5,
  });
}

export function useAssignmentSubmissions(assignmentId: string) {
  return useQuery({
    queryKey: ['guru', 'assignment-submissions', assignmentId],
    queryFn: () => guruApi.getAssignmentSubmissions(assignmentId),
    enabled: Boolean(assignmentId),
  });
}

export function useTeacherExams() {
  return useQuery({
    queryKey: ['guru', 'exams'],
    queryFn: () => guruApi.getTeacherExams(),
    staleTime: 1000 * 60 * 5,
  });
}

export function useTeacherLeaveRequests() {
  return useQuery({
    queryKey: ['guru', 'leave-requests'],
    queryFn: () => guruApi.getLeaveRequests(),
    staleTime: 1000 * 60 * 5,
  });
}

export function useTeacherSalarySlips() {
  return useQuery({
    queryKey: ['guru', 'salary-slips'],
    queryFn: () => guruApi.getSalarySlips(),
    staleTime: 1000 * 60 * 15,
  });
}

export function useTeacherSelfAttendance() {
  return useQuery({
    queryKey: ['guru', 'self-attendance'],
    queryFn: () => guruApi.getSelfAttendance(),
    staleTime: 1000 * 60 * 2,
  });
}

export function useTeacherHomeroomData(isHomeroom: boolean) {
  return useQuery({
    queryKey: ['guru', 'homeroom'],
    queryFn: () => guruApi.getHomeroomData(),
    enabled: isHomeroom,
    staleTime: 1000 * 60 * 5,
  });
}

// === GAP REMEDIATION HOOKS: FITUR A - C ===

export function useHomeroomLeaveRequests(params?: {
  class_id?: string;
  status?: string;
  q?: string;
  page?: number;
  per_page?: number;
}, enabled = true) {
  return useQuery({
    queryKey: ['guru', 'homeroom-leave-requests', params],
    queryFn: () => guruApi.getHomeroomLeaveRequests(params),
    enabled,
    staleTime: 1000 * 60 * 2,
  });
}

export function useDecideHomeroomLeaveRequest() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ id, payload }: { id: string; payload: { decision: 'approved' | 'rejected'; decision_notes?: string } }) =>
      guruApi.decideHomeroomLeaveRequest(id, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['guru', 'homeroom-leave-requests'] });
      queryClient.invalidateQueries({ queryKey: ['guru', 'homeroom'] });
    },
  });
}

export function useRecordStudentPoint() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (payload: StudentPointPayload) => guruApi.recordStudentPoint(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['guru', 'homeroom'] });
    },
  });
}

export function useCreateCounselingReferral() {
  return useMutation({
    mutationFn: (payload: CounselingReferralPayload) => guruApi.createCounselingReferral(payload),
  });
}

export function useFacilityBookings(params?: {
  status?: string;
  start_date?: string;
  end_date?: string;
  page?: number;
  per_page?: number;
}) {
  return useQuery({
    queryKey: ['guru', 'facility-bookings', params],
    queryFn: () => guruApi.getFacilityBookings(params),
    staleTime: 1000 * 60 * 2,
  });
}

export function useCreateFacilityBooking() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (payload: FacilityBookingPayload) => guruApi.createFacilityBooking(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['guru', 'facility-bookings'] });
    },
  });
}

export function useCreateSarprasTicket() {
  return useMutation({
    mutationFn: (payload: SarprasTicketPayload) => guruApi.createSarprasTicket(payload),
  });
}
