'use client';

import React, { createContext, useContext, useState, useEffect, useMemo } from 'react';
import { useAuth } from './auth-context';
import { 
  useGuardianChildren, 
  useChildDetail, 
  useChildAttendance, 
  useChildGrades, 
  useChildReportCards, 
  useChildCharacterNotes, 
  useChildProgressReports,
  ChildReportCardData,
  ChildCharacterNoteData,
  ChildProgressReportItem,
  useChildInvoices,
} from './use-guardian-data';
import type { 
  GuardianChildProfile, 
  GuardianProfile,
  ChildSubjectGrade,
  AttendanceCalendarDay,
  ChildPointItem,
  SchoolFeeItem,
  ChildScheduleItem,
} from '@/types/guardian';
import { buildAttendanceRecap, buildAttendanceCalendar, formatGateTapTime } from './guardian-attendance';
import { buildChildFees, resolveAdminFeeStatus, resolveFeeSummary } from './guardian-fees';
import { buildChildSubjectGrades } from './guardian-grades';

interface GuardianContextType {
  guardian: GuardianProfile;
  activeChild: GuardianChildProfile;
  activeChildId: string;
  setActiveChildId: (id: string) => void;
  activeGrades: ChildSubjectGrade[];
  activeAttendance: AttendanceCalendarDay[];
  activePoints: ChildPointItem[];
  activeFees: SchoolFeeItem[];
  activeSchedule: ChildScheduleItem[];
  isLoading: boolean;
  isError: boolean;
  refetchAll: () => void;
  reportCardData?: ChildReportCardData | null;
  characterNotesData?: ChildCharacterNoteData | null;
  progressReportsData?: ChildProgressReportItem[] | null;
}

const GuardianContext = createContext<GuardianContextType | undefined>(undefined);

const DEFAULT_CHILD: GuardianChildProfile = {
  id: '',
  name: 'Memuat data siswa...',
  nis: '-',
  nisn: '-',
  class_name: '-',
  grade_level: '-',
  curriculum_phase: '-',
  homeroom_teacher: 'Belum ditetapkan',
  homeroom_nip: '-',
  homeroom_phone: '-',
  initials: 'SW',
  gender: 'L',
  gate_attendance_today: {
    status: 'hadir',
    status_label: '-',
    tap_time: '-',
    location: '',
    note: '',
  },
  attendance_recap: {
    hadir: 0,
    sakit: 0,
    izin: 0,
    alpa: 0,
    terlambat: 0,
    rate: 0,
  },
  grade_average: 0,
  points_balance: 0,
  points_prestasi: 0,
  points_pelanggaran: 0,
  admin_fee_status: 'Lunas / Bebas Tanggungan',
  fee_summary: {
    title: 'SPP & Biaya Sekolah',
    period: '-',
    amount: 'Rp 0',
    status: 'Lunas',
    detail_note: 'Memuat data tagihan...',
    receipt_or_due: '-',
    officer: '',
  },
};

export function GuardianProvider({ children }: { children: React.ReactNode }) {
  const { user } = useAuth();
  const { data: childrenData, isLoading: isChildrenLoading, isError: isChildrenError, refetch: refetchChildren } = useGuardianChildren();

  const [activeChildId, setActiveChildIdState] = useState<string>('');

  // Set default active child when childrenData loads or sync with localStorage
  useEffect(() => {
    if (childrenData && childrenData.length > 0) {
      if (typeof window !== 'undefined') {
        const savedChildId = localStorage.getItem('sistlabum_guardian_active_child');
        if (savedChildId && childrenData.some(c => c.id === savedChildId)) {
          setActiveChildIdState(savedChildId);
          return;
        }
      }
      if (!activeChildId || !childrenData.some(c => c.id === activeChildId)) {
        setActiveChildIdState(childrenData[0].id);
      }
    }
  }, [childrenData, activeChildId]);

  const setActiveChildId = (id: string) => {
    setActiveChildIdState(id);
    if (typeof window !== 'undefined') {
      localStorage.setItem('sistlabum_guardian_active_child', id);
    }
  };

  // Queries for active child
  const { data: childDetail, refetch: refetchDetail } = useChildDetail(activeChildId);
  const { data: attendanceData, isLoading: isAttLoading, refetch: refetchAtt } = useChildAttendance(activeChildId);
  const { data: gradesData, isLoading: isGradesLoading, refetch: refetchGrades } = useChildGrades(activeChildId);
  const { data: reportCardData, refetch: refetchReportCard } = useChildReportCards(activeChildId);
  const { data: characterNotesData, refetch: refetchNotes } = useChildCharacterNotes(activeChildId);
  const { data: progressReportsData, refetch: refetchProgress } = useChildProgressReports(activeChildId);
  const { data: invoicesData, isLoading: isInvoicesLoading, isError: isInvoicesError, refetch: refetchInvoices } = useChildInvoices(activeChildId);

  // Active Fees (Admin & SPP) - FE-ORTU-BIAYA-01: data nyata dari backend GET /me/children/{child}/invoices
  const activeFees = useMemo<SchoolFeeItem[]>(() => {
    return buildChildFees(invoicesData);
  }, [invoicesData]);

  const refetchAll = () => {
    refetchChildren();
    refetchDetail();
    refetchAtt();
    refetchGrades();
    refetchReportCard();
    refetchNotes();
    refetchProgress();
    refetchInvoices();
  };

  // Construct active child profile
  const activeChild = useMemo<GuardianChildProfile>(() => {
    const matchedSummary = (childrenData || []).find(c => c.id === activeChildId);
    if (!matchedSummary) {
      return DEFAULT_CHILD;
    }

    const name = matchedSummary.name;
    const initials = name
      .split(' ')
      .filter(Boolean)
      .map(part => part[0])
      .slice(0, 2)
      .join('')
      .toUpperCase() || 'SW';

    const className = matchedSummary.class_name || matchedSummary.className || childDetail?.class?.name || 'Kelas 7-A';
    const gradeNum = className.replace(/[^0-9]/g, '') || '7';

    // Gate attendance today
    const historyList = (attendanceData as any)?.history || (attendanceData as any)?.records || [];
    const latestAtt = historyList[0];
    const rawStatus = latestAtt?.status || 'hadir';
    const todayStatus = (rawStatus === 'present' ? 'hadir' : rawStatus === 'sick' ? 'sakit' : rawStatus === 'permission' ? 'izin' : rawStatus === 'unexcused' ? 'alpa' : rawStatus) as 'hadir' | 'sakit' | 'izin' | 'alpa' | 'terlambat';
    const statusLabel = 
      todayStatus === 'hadir' ? 'Tepat Waktu' :
      todayStatus === 'sakit' ? 'Izin Sakit' :
      todayStatus === 'izin' ? 'Izin Resmi' :
      todayStatus === 'terlambat' ? 'Terlambat Masuk' : 'Tanpa Keterangan';

    // FE-ORTU-PRESENSI-01: rekap & jam tap dari data backend nyata (tanpa angka karangan).
    const attendanceRecap = buildAttendanceRecap(attendanceData?.summary as Record<string, unknown> | undefined);
    const tapTime = formatGateTapTime(latestAtt?.check_in_time || latestAtt?.checkInTime) ?? '-';

    // FE-ORTU-FABRIKASI-01: Tanpa fallback nilai fiktif. Jika data nilai kosong, default 0.
    let avgScore = 0;
    if (Array.isArray(gradesData) && gradesData.length > 0) {
      const sum = gradesData.reduce((acc, curr: any) => acc + (Number(curr.finalScore ?? curr.score) || 0), 0);
      avgScore = Math.round((sum / gradesData.length) * 10) / 10;
    } else if ((gradesData as any)?.average_score) {
      avgScore = (gradesData as any).average_score;
    } else if (reportCardData?.averageScore) {
      avgScore = reportCardData.averageScore;
    }

    return {
      id: matchedSummary.id,
      name,
      nis: matchedSummary.nis || '-',
      nisn: matchedSummary.nisn || '-',
      class_name: className.startsWith('Kelas') ? className : `Kelas ${className}`,
      grade_level: `Tingkat ${gradeNum} SMP`,
      curriculum_phase: 'Kurikulum Merdeka Fase D',
      homeroom_teacher: childDetail?.homeroom_teacher?.name || 'Belum ditetapkan',
      homeroom_nip: childDetail?.homeroom_teacher?.nip ? `NIP. ${childDetail.homeroom_teacher.nip}` : '-',
      homeroom_phone: childDetail?.homeroom_teacher?.phone || '-',
      avatar_url: matchedSummary.avatar_url || matchedSummary.avatarUrl || undefined,
      initials,
      gender: (matchedSummary.gender === 'female' || matchedSummary.gender === 'P') ? 'P' : 'L',
      gate_attendance_today: {
        status: todayStatus,
        status_label: statusLabel,
        tap_time: tapTime,
        // Backend presensi belum menyediakan lokasi gerbang → kosongkan (jangan mengarang 'Gate 1 RFID').
        location: '',
        note: latestAtt?.notes || latestAtt?.note || '',
      },
      attendance_recap: attendanceRecap,
      grade_average: avgScore,
      points_balance: 0,
      points_prestasi: 0,
      points_pelanggaran: 0,
      admin_fee_status: resolveAdminFeeStatus(activeFees),
      fee_summary: resolveFeeSummary(activeFees),
    };
  }, [childrenData, activeChildId, childDetail, attendanceData, gradesData, reportCardData, activeFees]);

  // Construct guardian profile (FE-ORTU-FABRIKASI-01: data riil akun, tanpa profil karangan)
  const guardian = useMemo<GuardianProfile>(() => {
    const gName = (user?.profile as { name?: string })?.name || user?.name || user?.username || 'Wali Murid';
    const gPhone = user?.phone || (user?.profile as any)?.phone || '-';
    const gEmail = user?.email || '-';

    const mappedChildren: GuardianChildProfile[] = (childrenData || []).map((c) => {
      const isCur = c.id === activeChildId;
      const cClassName = c.class_name || c.className || '7-A';
      const cGradeNum = cClassName.replace(/[^0-9]/g, '') || '7';
      const initials = c.name
        .split(' ')
        .filter(Boolean)
        .map(part => part[0])
        .slice(0, 2)
        .join('')
        .toUpperCase() || 'SW';

      return {
        id: c.id,
        name: c.name,
        nis: c.nis || '-',
        nisn: c.nisn || '-',
        class_name: cClassName.startsWith('Kelas') ? cClassName : `Kelas ${cClassName}`,
        grade_level: `Tingkat ${cGradeNum} SMP`,
        curriculum_phase: 'Kurikulum Merdeka Fase D',
        homeroom_teacher: isCur && childDetail?.homeroom_teacher?.name ? childDetail.homeroom_teacher.name : 'Belum ditetapkan',
        homeroom_nip: isCur && childDetail?.homeroom_teacher?.nip ? `NIP. ${childDetail.homeroom_teacher.nip}` : '-',
        homeroom_phone: isCur && childDetail?.homeroom_teacher?.phone ? childDetail.homeroom_teacher.phone : '-',
        avatar_url: c.avatar_url || c.avatarUrl || undefined,
        initials,
        gender: (c.gender === 'female' || c.gender === 'P') ? 'P' : 'L',
        // FE-ORTU-PRESENSI-01: status gerbang harian per-anak belum tersedia sampai anak dipilih;
        // jangan mengarang jam/catatan. (Halaman hanya memakai activeChild.gate_attendance_today.)
        gate_attendance_today: {
          status: 'hadir',
          status_label: '-',
          tap_time: '-',
          location: '',
          note: '',
        },
        // Rekap nyata hanya untuk anak aktif (endpoint presensi per-anak); non-aktif 0, bukan karangan.
        attendance_recap: buildAttendanceRecap(isCur ? (attendanceData?.summary as Record<string, unknown> | undefined) : undefined),
        grade_average: isCur && (gradesData as any)?.average_score ? (gradesData as any).average_score : 0,
        points_balance: 0,
        points_prestasi: 0,
        points_pelanggaran: 0,
        admin_fee_status: isCur ? resolveAdminFeeStatus(activeFees) : 'Lunas / Bebas Tanggungan',
        fee_summary: isCur
          ? resolveFeeSummary(activeFees)
          : {
              title: 'SPP & Biaya Sekolah',
              period: '-',
              amount: 'Rp 0',
              status: 'Lunas',
              detail_note: '-',
              receipt_or_due: '-',
              officer: '',
            },
      };
    });

    return {
      id: (user?.profile as { id?: string })?.id || user?.id || 'grd-001',
      name: gName,
      avatar_url: undefined,
      phone: gPhone,
      email: gEmail,
      address: (user?.profile as any)?.address || '-',
      occupation: (user?.profile as any)?.occupation || '-',
      children: mappedChildren.length > 0 ? mappedChildren : [DEFAULT_CHILD],
    };
  }, [user, childrenData, activeChildId, childDetail, attendanceData, gradesData, activeFees]);

  // Active Grades from live API — pemetaan jujur (tanpa rekayasa) di buildChildSubjectGrades.
  const activeGrades = useMemo<ChildSubjectGrade[]>(
    () => buildChildSubjectGrades(gradesData, reportCardData),
    [gradesData, reportCardData],
  );

  // Active Attendance from live API
  // FE-ORTU-PRESENSI-01: kalender presensi dari riwayat backend nyata (pemetaan jujur di helper).
  const activeAttendance = useMemo<AttendanceCalendarDay[]>(
    () => buildAttendanceCalendar(attendanceData),
    [attendanceData],
  );

  // Active Points from live backend
  // FE-ORTU-FABRIKASI-01: Backend belum menyediakan endpoint poin untuk akun ortu.
  // Kembalikan [] secara jujur agar antarmuka menampilkan EmptyState jujur (tanpa poin/tanggal rekayasa).
  const activePoints = useMemo<ChildPointItem[]>(() => {
    return [];
  }, []);

  // Active Schedule
  // FE-ORTU-FABRIKASI-01: Endpoint jadwal untuk ortu belum ada di backend.
  // Kembalikan [] secara jujur (tanpa jadwal hardcoded atau guru fiktif).
  const activeSchedule = useMemo<ChildScheduleItem[]>(() => {
    return [];
  }, []);

  const isLoading = isChildrenLoading || isAttLoading || isGradesLoading || isInvoicesLoading;
  const isError = isChildrenError || isInvoicesError;

  return (
    <GuardianContext.Provider
      value={{
        guardian,
        activeChild,
        activeChildId,
        setActiveChildId,
        activeGrades,
        activeAttendance,
        activePoints,
        activeFees,
        activeSchedule,
        isLoading,
        isError,
        refetchAll,
        reportCardData,
        characterNotesData,
        progressReportsData,
      }}
    >
      {children}
    </GuardianContext.Provider>
  );
}

export function useGuardian() {
  const context = useContext(GuardianContext);
  if (!context) {
    throw new Error('useGuardian must be used within a GuardianProvider');
  }
  return context;
}
