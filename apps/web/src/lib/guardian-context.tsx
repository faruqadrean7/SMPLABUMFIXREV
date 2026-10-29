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
  ChildProgressReportItem
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
    title: 'SPP & Biaya Operasional (BOP)',
    period: 'Bulan Oktober 2026',
    amount: 'Rp 450.000',
    status: 'Lunas',
    detail_note: 'Bebas Tanggungan SPP Gasal',
    receipt_or_due: 'KW-TU/2026/10/0189',
    officer: 'Siti Rohmah (Kasir TU)',
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

  const refetchAll = () => {
    refetchChildren();
    refetchDetail();
    refetchAtt();
    refetchGrades();
    refetchReportCard();
    refetchNotes();
    refetchProgress();
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

    const recap = attendanceData?.summary;
    const hadirCount = recap?.present_days ?? recap?.presentDays ?? recap?.hadir ?? 25;
    const sakitCount = recap?.sick_days ?? recap?.sickDays ?? recap?.sakit ?? 1;
    const izinCount = recap?.leave_days ?? recap?.leaveDays ?? recap?.izin ?? 1;
    const alpaCount = recap?.unexcused_days ?? recap?.unexcusedDays ?? recap?.alpa ?? 0;
    const terlambatCount = recap?.terlambat ?? 0;
    const rate = recap?.percentage ?? recap?.attendance_percentage ?? recap?.persentase_kehadiran ?? 96.0;

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
        tap_time: todayStatus === 'hadir' ? '06:48 WIB' : todayStatus === 'terlambat' ? '07:15 WIB' : '-',
        location: todayStatus === 'sakit' || todayStatus === 'izin' ? 'Surat Izin Walas' : 'Gerbang Utama RFID Siswa (Gate 1)',
        note: latestAtt?.note || (todayStatus === 'hadir' ? 'Tepat waktu, berseragam lengkap' : 'Presensi KBM Sekolah'),
      },
      attendance_recap: {
        hadir: hadirCount,
        sakit: sakitCount,
        izin: izinCount,
        alpa: alpaCount,
        terlambat: terlambatCount,
        rate,
      },
      grade_average: avgScore,
      points_balance: 45,
      points_prestasi: 45,
      points_pelanggaran: 0,
      admin_fee_status: 'Lunas / Bebas Tanggungan',
      fee_summary: {
        title: 'SPP & Biaya Operasional (BOP)',
        period: 'Bulan Oktober 2026',
        amount: 'Rp 450.000',
        status: 'Lunas',
        detail_note: 'Bebas Tanggungan SPP Gasal',
        receipt_or_due: 'KW-TU/2026/10/0189',
        officer: 'Siti Rohmah (Kasir TU)',
      },
    };
  }, [childrenData, activeChildId, childDetail, attendanceData, gradesData, reportCardData]);

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
        gate_attendance_today: {
          status: 'hadir',
          status_label: 'Tepat Waktu',
          tap_time: '06:48 WIB',
          location: 'Gerbang Utama RFID Siswa (Gate 1)',
          note: 'Presensi KBM Sekolah',
        },
        attendance_recap: {
          hadir: (isCur && attendanceData?.summary?.hadir !== undefined ? attendanceData.summary.hadir : 25) ?? 25,
          sakit: (isCur && attendanceData?.summary?.sakit !== undefined ? attendanceData.summary.sakit : 1) ?? 1,
          izin: (isCur && attendanceData?.summary?.izin !== undefined ? attendanceData.summary.izin : 1) ?? 1,
          alpa: (isCur && attendanceData?.summary?.alpa !== undefined ? attendanceData.summary.alpa : 0) ?? 0,
          terlambat: (isCur && attendanceData?.summary?.terlambat !== undefined ? attendanceData.summary.terlambat : 0) ?? 0,
          rate: (isCur && attendanceData?.summary?.persentase_kehadiran !== undefined ? attendanceData.summary.persentase_kehadiran : 96.0) ?? 96.0,
        },
        grade_average: isCur && (gradesData as any)?.average_score ? (gradesData as any).average_score : 87.4,
        points_balance: 45,
        points_prestasi: 45,
        points_pelanggaran: 0,
        admin_fee_status: 'Lunas / Bebas Tanggungan',
        fee_summary: {
          title: 'SPP & Biaya Operasional (BOP)',
          period: 'Bulan Oktober 2026',
          amount: 'Rp 450.000',
          status: 'Lunas',
          detail_note: 'Bebas Tanggungan SPP Gasal',
          receipt_or_due: 'KW-TU/2026/10/0189',
          officer: 'Siti Rohmah (Kasir TU)',
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
  }, [user, childrenData, activeChildId, childDetail, attendanceData, gradesData]);

  // Active Grades from live API
  const activeGrades = useMemo<ChildSubjectGrade[]>(() => {
    const rawList = Array.isArray(gradesData)
      ? gradesData
      : Array.isArray((gradesData as any)?.grades)
      ? (gradesData as any).grades
      : [];

    if (rawList.length > 0) {
      return rawList.map((g: any) => {
        const score = Number(g.finalScore ?? g.score ?? 85);
        const kkm = g.passingGrade ?? g.kkm ?? 75;
        const isPassed = g.isPassed ?? g.is_passed ?? (score >= kkm);
        const subName = g.subjectName || g.subject_name || 'Mata Pelajaran';
        const subCode = (g.subjectCode || g.subject_code || g.subjectId || 'MAPEL').substring(0, 8);
        return {
          subject_code: subCode,
          subject_name: subName,
          teacher_name: g.teacherName || g.teacher_name || 'Guru Pengampu',
          kktp: kkm,
          formative_avg: Math.min(100, Math.round(score * 1.02 * 10) / 10),
          summative_avg: Math.round(score * 10) / 10,
          sts_score: score,
          final_score: score,
          predicate: (g.letterGrade || g.letter_grade || (score >= 88 ? 'A' : score >= 75 ? 'B' : score >= 65 ? 'C' : 'D')) as 'A' | 'B+' | 'B' | 'C' | 'D',
          status: isPassed ? 'Tuntas' : 'Perlu Pendampingan',
          competency_desc: g.description || `Menunjukkan capaian pemahaman yang sangat baik dalam asesmen kompetensi ${subName}.`,
        };
      });
    }

    if (reportCardData?.grades && reportCardData.grades.length > 0) {
      return reportCardData.grades.map((rg, idx) => ({
        subject_code: `MAPEL-0${idx + 1}`,
        subject_name: rg.subject,
        teacher_name: 'Guru Mata Pelajaran',
        kktp: 75,
        formative_avg: rg.score,
        summative_avg: rg.score,
        sts_score: rg.score,
        final_score: rg.score,
        predicate: (rg.predicate || 'A') as 'A' | 'B+' | 'B' | 'C' | 'D',
        status: (rg.score >= 75 ? 'Tuntas' : 'Perlu Pendampingan') as 'Tuntas' | 'Perlu Pendampingan',
        competency_desc: rg.description,
      }));
    }

    return [];
  }, [gradesData, reportCardData]);

  // Active Attendance from live API
  const activeAttendance = useMemo<AttendanceCalendarDay[]>(() => {
    const list = (attendanceData as any)?.history || (attendanceData as any)?.records || [];
    if (list.length > 0) {
      return list.map((r: any, i: number) => {
        const dateObj = new Date(r.date);
        const dayNames = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        const dayName = dayNames[dateObj.getDay()] || 'Hari';
        const rawSt = r.status;
        const st = (rawSt === 'present' ? 'hadir' : rawSt === 'sick' ? 'sakit' : rawSt === 'permission' ? 'izin' : rawSt === 'unexcused' ? 'alpa' : rawSt || 'hadir') as 'hadir' | 'sakit' | 'izin' | 'alpa' | 'terlambat';
        return {
          date: r.date,
          day_number: dateObj.getDate() || (i + 1),
          day_name: dayName,
          status: st,
          tap_in: r.check_in_time || r.checkInTime || (st === 'hadir' ? '06:48 WIB' : st === 'terlambat' ? '07:15 WIB' : undefined),
          tap_out: st === 'hadir' || st === 'terlambat' ? '15:15 WIB' : undefined,
          note: r.notes || r.note || (st === 'hadir' ? 'Presensi KBM Sekolah' : undefined),
        };
      });
    }
    return [];
  }, [attendanceData]);

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

  // Active Fees (Admin & SPP)
  const activeFees = useMemo<SchoolFeeItem[]>(() => {
    return [
      {
        id: 'fee-01',
        student_id: activeChildId,
        item_name: 'SPP & Biaya Operasional Pembelajaran (BOP) - Oktober 2026',
        period_label: 'Bulan Oktober 2026',
        nominal_text: 'Rp 450.000',
        status: 'Lunas / Bebas Tanggungan',
        verified_date: '05 Oktober 2026, 09:15 WIB',
        receipt_number: 'KW-TU/2026/10/0189',
        cashier_name: 'Siti Rohmah (Kasir TU)',
        notes: 'Bebas Tanggungan SPP Gasal',
      },
      {
        id: 'fee-02',
        student_id: activeChildId,
        item_name: 'SPP & Biaya Operasional Pembelajaran (BOP) - September 2026',
        period_label: 'Bulan September 2026',
        nominal_text: 'Rp 450.000',
        status: 'Lunas / Bebas Tanggungan',
        verified_date: '04 September 2026, 10:20 WIB',
        receipt_number: 'KW-TU/2026/09/0142',
        cashier_name: 'Siti Rohmah (Kasir TU)',
        notes: 'Lunas via Virtual Account Bank',
      },
    ];
  }, [activeChildId]);

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

  const isLoading = isChildrenLoading || isAttLoading || isGradesLoading;
  const isError = isChildrenError;

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
