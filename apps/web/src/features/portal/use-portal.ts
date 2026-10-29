import { useQuery } from '@tanstack/react-query';
import { portalApi } from './portal-api';
import { PortalChildSummary } from './types';
import { User } from '@/types/auth';

/**
 * Hook untuk memuat daftar anak perwalian wali murid
 */
export function useChildren(user?: User | null) {
  const profileChildren = Array.isArray(user?.profile?.children)
    ? (user?.profile?.children as PortalChildSummary[])
    : undefined;

  return useQuery({
    queryKey: ['portal', 'children', user?.id],
    queryFn: () => portalApi.getChildren(profileChildren),
    staleTime: 1000 * 60 * 5, // 5 menit
  });
}

/**
 * Hook untuk memuat detail anak berdasarkan ID dengan verifikasi perwalian
 */
export function useChildDetail(childId: string, user?: User | null) {
  const profileChildren = Array.isArray(user?.profile?.children)
    ? (user?.profile?.children as Array<{ id: string }>)
    : undefined;

  return useQuery({
    queryKey: ['portal', 'child', childId, user?.id],
    queryFn: () => portalApi.getChildDetail(childId, profileChildren),
    enabled: Boolean(childId),
    retry: false, // Tidak perlu retry jika akses ditolak (403)
  });
}

/**
 * Hook untuk rekap presensi anak
 */
export function useChildAttendance(childId: string, user?: User | null) {
  const profileChildren = Array.isArray(user?.profile?.children)
    ? (user?.profile?.children as Array<{ id: string }>)
    : undefined;

  return useQuery({
    queryKey: ['portal', 'child-attendance', childId, user?.id],
    queryFn: () => portalApi.getChildAttendance(childId, profileChildren),
    enabled: Boolean(childId),
    retry: false,
  });
}

/**
 * Hook untuk buku nilai dan rapor anak
 */
export function useChildReportCard(childId: string, user?: User | null) {
  const profileChildren = Array.isArray(user?.profile?.children)
    ? (user?.profile?.children as Array<{ id: string }>)
    : undefined;

  return useQuery({
    queryKey: ['portal', 'child-report-card', childId, user?.id],
    queryFn: () => portalApi.getChildReportCard(childId, profileChildren),
    enabled: Boolean(childId),
    retry: false,
  });
}

/**
 * Hook untuk catatan karakter bulanan anak
 */
export function useChildCharacterNote(childId: string, user?: User | null) {
  const profileChildren = Array.isArray(user?.profile?.children)
    ? (user?.profile?.children as Array<{ id: string }>)
    : undefined;

  return useQuery({
    queryKey: ['portal', 'child-character', childId, user?.id],
    queryFn: () => portalApi.getChildCharacterNote(childId, profileChildren),
    enabled: Boolean(childId),
    retry: false,
  });
}

/**
 * Hook untuk laporan perkembangan anak
 */
export function useChildProgressReports(childId: string, user?: User | null) {
  const profileChildren = Array.isArray(user?.profile?.children)
    ? (user?.profile?.children as Array<{ id: string }>)
    : undefined;

  return useQuery({
    queryKey: ['portal', 'child-progress', childId, user?.id],
    queryFn: () => portalApi.getChildProgressReports(childId, profileChildren),
    enabled: Boolean(childId),
    retry: false,
  });
}

/**
 * Hook untuk ringkasan beranda siswa
 */
export function useStudentDashboard() {
  return useQuery({
    queryKey: ['portal', 'student-dashboard'],
    queryFn: () => portalApi.getStudentDashboard(),
    staleTime: 1000 * 60 * 3,
  });
}

/**
 * Hook untuk buku nilai siswa
 */
export function useStudentGrades() {
  return useQuery({
    queryKey: ['portal', 'student-grades'],
    queryFn: () => portalApi.getStudentGrades(),
    staleTime: 1000 * 60 * 3,
  });
}

/**
 * Hook untuk jadwal mingguan siswa
 */
export function useStudentSchedule() {
  return useQuery({
    queryKey: ['portal', 'student-schedule'],
    queryFn: () => portalApi.getStudentSchedule(),
    staleTime: 1000 * 60 * 10,
  });
}

/**
 * Hook untuk daftar ujian CBT siswa
 */
export function useStudentExams() {
  return useQuery({
    queryKey: ['portal', 'student-exams'],
    queryFn: () => portalApi.getStudentExams(),
    staleTime: 1000 * 60 * 2,
  });
}
