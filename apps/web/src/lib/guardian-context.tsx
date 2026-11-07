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
import { 
  GuardianChildProfile, 
  GuardianProfile,
  ChildSubjectGrade,
  AttendanceCalendarDay,
  ChildPointItem,
  SchoolFeeItem,
  ChildScheduleItem,
} from './mock-guardian-data';
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
  class_name: 'Kelas 7-A',
  grade_level: 'Tingkat VII SMP',
  curriculum_phase: 'Kurikulum Merdeka Fase D',
  homeroom_teacher: 'Nur Hayati, S.Pd.',
  homeroom_nip: 'NIP. 19840214 200902 2 003',
  homeroom_phone: '0812-3456-7890',
  initials: 'SW',
  gender: 'L',
  gate_attendance_today: {
    status: 'hadir',
    status_label: 'Tepat Waktu',
    tap_time: '06:48 WIB',
    location: 'Gerbang Utama RFID Siswa (Gate 1)',
    note: 'Presensi KBM Sekolah',
  },
  attendance_recap: {
    hadir: 25,
    sakit: 1,
    izin: 1,
    alpa: 0,
    terlambat: 0,
    rate: 96.0,
  },
  grade_average: 87.4,
  points_balance: 45,
  points_prestasi: 45,
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

    let avgScore = 87.4;
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
      nis: matchedSummary.nis,
      nisn: matchedSummary.nisn,
      class_name: className.startsWith('Kelas') ? className : `Kelas ${className}`,
      grade_level: `Tingkat ${gradeNum} SMP`,
      curriculum_phase: 'Kurikulum Merdeka Fase D',
      homeroom_teacher: childDetail?.homeroom_teacher?.name || 'Nur Hayati, S.Pd.',
      homeroom_nip: childDetail?.homeroom_teacher?.nip ? `NIP. ${childDetail.homeroom_teacher.nip}` : 'NIP. 19840214 200902 2 003',
      homeroom_phone: childDetail?.homeroom_teacher?.phone || '0812-3456-7890',
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
      points_balance: 45,
      points_prestasi: 45,
      points_pelanggaran: 0,
      admin_fee_status: resolveAdminFeeStatus(activeFees),
      fee_summary: resolveFeeSummary(activeFees),
    };
  }, [childrenData, activeChildId, childDetail, attendanceData, gradesData, reportCardData, activeFees]);

  // Construct guardian profile
  const guardian = useMemo<GuardianProfile>(() => {
    const gName = (user?.profile as { name?: string })?.name || user?.username || 'Adrean Pratama, S.T.';
    const gPhone = user?.phone || '081234567890';
    const gEmail = user?.email || 'ortu.siswa.001@smp-labum.sch.id';

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
        nis: c.nis,
        nisn: c.nisn,
        class_name: cClassName.startsWith('Kelas') ? cClassName : `Kelas ${cClassName}`,
        grade_level: `Tingkat ${cGradeNum} SMP`,
        curriculum_phase: 'Kurikulum Merdeka Fase D',
        homeroom_teacher: isCur && childDetail?.homeroom_teacher?.name ? childDetail.homeroom_teacher.name : 'Wali Kelas',
        homeroom_nip: 'NIP. 19840214 200902 2 003',
        homeroom_phone: '0812-3456-7890',
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
        grade_average: isCur && (gradesData as any)?.average_score ? (gradesData as any).average_score : 87.4,
        points_balance: 45,
        points_prestasi: 45,
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
      id: (user?.profile as { id?: string })?.id || 'grd-001',
      name: gName,
      avatar_url: undefined,
      phone: gPhone,
      email: gEmail,
      address: 'Jl. Ijen No. 45, Klojen, Kota Malang',
      occupation: 'Arsitek & Wiraswasta',
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

  // Active Points from live character notes
  const activePoints = useMemo<ChildPointItem[]>(() => {
    const items: ChildPointItem[] = [];
    if (characterNotesData?.homeroomNote) {
      items.push({
        id: 'pt-01',
        student_id: activeChildId,
        point_date: '15 Oktober 2026',
        kind: 'prestasi',
        title: 'Apresiasi Karakter Kemandirian & Gotong Royong',
        category: 'Karakter & Profil Pelajar Pancasila',
        points: 25,
        recorder_name: childDetail?.homeroom_teacher?.name || 'Nur Hayati, S.Pd.',
        note: characterNotesData.homeroomNote,
      });
    }
    items.push({
      id: 'pt-02',
      student_id: activeChildId,
      point_date: '02 Oktober 2026',
      kind: 'prestasi',
      title: 'Ketertiban Kehadiran & Partisipasi KBM',
      category: 'Kedisiplinan & Budaya Sekolah',
      points: 20,
      recorder_name: 'Wali Kelas',
      note: 'Tertib hadir tepat waktu dan mematuhi seluruh tata tertib sekolah.',
    });
    return items;
  }, [activeChildId, characterNotesData, childDetail]);

  // Active Schedule
  const activeSchedule = useMemo<ChildScheduleItem[]>(() => {
    const isClass9 = activeChild?.class_name?.includes('9');
    if (isClass9) {
      return [
        {
          id: 'sch-9-1',
          timeRange: '07:00 - 08:30 WIB',
          subject: 'Matematika Tingkat Lanjut',
          teacher: 'Ahmad Yani, M.Pd.',
          room: 'Ruang 9-A',
          status: 'completed',
          statusLabel: 'Selesai KBM',
          topic: 'Persamaan Kuadrat & Fungsi Polinomial',
        },
        {
          id: 'sch-9-2',
          timeRange: '08:45 - 10:15 WIB',
          subject: 'Bahasa Inggris Lanjutan',
          teacher: 'Fitri Handayani, S.Pd.',
          room: 'Ruang 9-A',
          status: 'current',
          statusLabel: 'Sedang Berlangsung',
          topic: 'Analytical Exposition & Argumentative Essay',
        },
        {
          id: 'sch-9-3',
          timeRange: '10:30 - 12:00 WIB',
          subject: 'Ilmu Pengetahuan Alam',
          teacher: 'Dr. Hendra Wijaya',
          room: 'Lab Biologi 2',
          status: 'upcoming',
          statusLabel: 'Berikutnya',
          topic: 'Bioteknologi Konvensional & Modern',
        },
      ];
    }
    return [
      {
        id: 'sch-7-1',
        timeRange: '07:00 - 08:30 WIB',
        subject: 'Informatika',
        teacher: 'Budi Santoso, S.Kom., M.T.',
        room: 'Lab Komputer 1',
        status: 'completed',
        statusLabel: 'Selesai KBM',
        topic: 'Algoritma Percabangan & Logika Web',
      },
      {
        id: 'sch-7-2',
        timeRange: '08:45 - 10:15 WIB',
        subject: 'Matematika',
        teacher: 'Siti Aminah, M.Pd.',
        room: 'Ruang Kelas 7-A',
        status: 'current',
        statusLabel: 'Sedang Berlangsung',
        topic: 'Operasi Bilangan Bulat & Aljabar Dasar',
      },
      {
        id: 'sch-7-3',
        timeRange: '10:30 - 12:00 WIB',
        subject: 'Ilmu Pengetahuan Alam',
        teacher: 'Rina Kusuma, S.Si.',
        room: 'Lab IPA 1',
        status: 'upcoming',
        statusLabel: 'Berikutnya',
        topic: 'Klasifikasi Makhluk Hidup & Metode Ilmiah',
      },
    ];
  }, [activeChild?.class_name]);

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
