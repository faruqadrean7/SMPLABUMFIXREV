'use client';

import React, { useState } from 'react';
import Image from 'next/image';
import { 
  Calendar as CalendarIcon, 
  Clock, 
  MapPin, 
  Printer, 
  BookOpen, 
  CalendarDays,
  Coffee,
  Flag,
  CheckCircle2,
  LayoutGrid,
  ListOrdered,
  Sun,
  PlayCircle
} from 'lucide-react';
import { StudentScheduleItem } from '@/lib/use-student-data';
import { useStudentSchedule } from '@/lib/use-student-data';
import { ScheduleListSkeleton } from '@/components/ui/student-skeletons';
import { EmptyState } from '@/components/ui/empty-state';
import { ErrorState } from '@/components/ui/error-state';

export default function StudentJadwalPage() {
  const [activeMainTab, setActiveMainTab] = useState<'jadwal' | 'kalender'>('jadwal');
  const [scheduleViewMode, setScheduleViewMode] = useState<'timeline' | 'matriks'>('timeline');
  const [selectedDay, setSelectedDay] = useState<'Senin' | 'Selasa' | 'Rabu' | 'Kamis' | 'Jumat'>('Senin');

  const { data: apiSchedule, isLoading, isError, refetch } = useStudentSchedule();

  const days: Array<'Senin' | 'Selasa' | 'Rabu' | 'Kamis' | 'Jumat'> = [
    'Senin', 
    'Selasa', 
    'Rabu', 
    'Kamis', 
    'Jumat'
  ];

  // Schedules for active day (real API data)
  const dailySchedules = (apiSchedule && apiSchedule.length > 0)
    ? apiSchedule
        .filter((s) => s.dayName === selectedDay)
        .map((s, idx) => ({
          id: s.id,
          period: `Jam Ke-${idx + 1}`,
          time_start: s.startTime,
          time_end: s.endTime,
          time_range: `${s.startTime} - ${s.endTime} WIB`,
          subject_name: s.subjectName,
          subject_code: 'MAPEL',
          teacher_name: s.teacherName,
          teacher_avatar_url: '',
          room_name: s.roomName,
          day: s.dayName,
          topic_name: 'Kegiatan Belajar Mengajar Tatap Muka',
          is_active_today: true,
          status: 'akan_datang' as const,
          icon_type: 'theory' as const,
        }))
    : [];

  // Helper to retrieve schedule cell for matrix table
  const getMatrixSchedule = (day: string, periodKey: string | number) => {
    if (!apiSchedule) return undefined;
    const dayItems = apiSchedule.filter(s => s.dayName === day);
    let item;
    if (typeof periodKey === 'number') {
      item = dayItems[periodKey];
    } else if (periodKey === '1-2' || periodKey === '1') {
      item = dayItems[0];
    } else if (periodKey === '3-4' || periodKey === '3') {
      item = dayItems[1];
    } else if (periodKey === '5-6' || periodKey === '5') {
      item = dayItems[2];
    } else if (periodKey === '7-8' || periodKey === '7') {
      item = dayItems[3];
    } else {
      item = dayItems[0];
    }
    if (!item) return undefined;
    return {
      id: item.id,
      subject_name: item.subjectName,
      subject_code: 'MAPEL',
      teacher_name: item.teacherName,
      room_name: item.roomName,
      time_range: `${item.startTime} - ${item.endTime}`,
    };
  };

  // Academic calendar data grouped by month
  const calendarMonths = [
    {
      month: 'Oktober 2026',
      events: [
        { 
          date: '14 - 23 Okt 2026', 
          title: 'Sumatif Tengah Semester (STS) Gasal', 
          category: 'Ujian Digital CBT', 
          badgeColor: 'bg-rose-50 text-rose-800 border-rose-200', 
          note: 'Pelaksanaan CBT di Lab Komputer 1 & 2. Sesi ujian berstandar SEB.',
          isUrgent: true
        },
        { 
          date: '28 Okt 2026', 
          title: 'Peringatan Hari Sumpah Pemuda & Bulan Bahasa', 
          category: 'Kegiatan Kesiswaan', 
          badgeColor: 'bg-emerald-50 text-emerald-800 border-emerald-200', 
          note: 'Upacara bendera dan lomba pidato 3 bahasa di Lapangan Utama.',
          isUrgent: false
        }
      ]
    },
    {
      month: 'November 2026',
      events: [
        { 
          date: '02 - 04 Nov 2026', 
          title: 'Gladi Bersih & Simulasi Asesmen Nasional (ANBK)', 
          category: 'Asesmen Nasional', 
          badgeColor: 'bg-blue-50 text-blue-900 border-blue-200', 
          note: 'Khusus siswa sampling Kelas VIII & pendampingan tim IT.',
          isUrgent: false
        },
        { 
          date: '25 Nov 2026', 
          title: 'Hari Guru Nasional & Gelar Karya P5 "Rekayasa Teknologi"', 
          category: 'Gelar Karya P5', 
          badgeColor: 'bg-purple-50 text-purple-900 border-purple-200', 
          note: 'Pameran prototipe aplikasi web siswa di Aula Utama.',
          isUrgent: false
        }
      ]
    },
    {
      month: 'Desember 2026 - Januari 2027',
      events: [
        { 
          date: '07 - 18 Des 2026', 
          title: 'Sumatif Akhir Semester (SAS) Gasal', 
          category: 'Ujian Digital CBT', 
          badgeColor: 'bg-rose-50 text-rose-800 border-rose-200', 
          note: 'Ujian digital CBT seluruh mata pelajaran dengan token dinamis.',
          isUrgent: true
        },
        { 
          date: '23 Des 2026', 
          title: 'Penyerahan E-Rapor Semester Gasal & Pertemuan Wali Murid', 
          category: 'E-Rapor Sah Ber-QR', 
          badgeColor: 'bg-blue-50 text-blue-900 border-blue-200', 
          note: 'Pengesahan digital dokumen rapor resmi oleh Kepala Sekolah.',
          isUrgent: false
        },
        { 
          date: '24 Des 2026 - 08 Jan 2027', 
          title: 'Libur Akhir Semester Gasal T.A. 2026/2027', 
          category: 'Libur Resmi Sekolah', 
          badgeColor: 'bg-amber-50 text-amber-800 border-amber-200', 
          note: 'KBM Semester Genap dimulai serentak pada Senin, 11 Januari 2027.',
          isUrgent: false
        }
      ]
    }
  ];

  return (
    <div className="flex flex-col gap-3 max-w-full xl:h-[calc(100vh-112px)] xl:overflow-hidden text-slate-900 font-sans">
      {/* ─── 1. HEADER CARD (COMPACT PROFILE/TITLE BAR IDENTICAL TO DASHBOARD) ─── */}
      <div className="bg-white border border-slate-200/90 rounded-xl p-3 px-4 shadow-xs shrink-0 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-xl bg-blue-50 text-blue-900 border border-blue-200 flex items-center justify-center shrink-0">
            <CalendarDays className="w-5 h-5 text-blue-900" />
          </div>
          <div>
            <div className="flex items-center gap-2">
              <h1 className="text-base sm:text-lg font-extrabold text-slate-900 tracking-tight">
                Jadwal Pelajaran & Kalender Akademik
              </h1>
              <span className="px-2 py-0.5 rounded-md text-[11px] font-bold bg-blue-900 text-white shadow-2xs font-mono">
                Kelas IX-A
              </span>
              <span className="text-xs text-slate-500 font-semibold hidden md:inline">
                • T.A. 2026/2027 Ganjil
              </span>
            </div>
            <p className="text-xs text-slate-500 font-normal mt-0.5">
              Wali Kelas: <strong className="text-slate-800 font-semibold">Dra. Siti Rahayu, M.Pd.</strong> • SMP Labschool Universitas Negeri Malang
            </p>
          </div>
        </div>

        {/* Tab & Actions */}
        <div className="flex items-center gap-2 shrink-0 self-end sm:self-auto">
          <div className="flex items-center bg-slate-100 p-0.5 rounded-lg border border-slate-200 shadow-2xs">
            <button
              onClick={() => setActiveMainTab('jadwal')}
              className={`px-3 py-1.5 rounded-md text-xs font-bold transition-all cursor-pointer ${
                activeMainTab === 'jadwal'
                  ? 'bg-blue-900 text-white shadow-xs'
                  : 'text-slate-600 hover:text-slate-900'
              }`}
            >
              Jadwal Pelajaran
            </button>
            <button
              onClick={() => setActiveMainTab('kalender')}
              className={`px-3 py-1.5 rounded-md text-xs font-bold transition-all cursor-pointer ${
                activeMainTab === 'kalender'
                  ? 'bg-blue-900 text-white shadow-xs'
                  : 'text-slate-600 hover:text-slate-900'
              }`}
            >
              Kalender Sekolah
            </button>
          </div>

          <button
            onClick={() => window.print()}
            className="px-3 py-1.5 rounded-lg bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-bold transition-colors shadow-xs flex items-center gap-1.5 cursor-pointer"
          >
            <Printer className="w-3.5 h-3.5 text-slate-500" />
            <span className="hidden sm:inline">Cetak</span>
          </button>
        </div>
      </div>

      {/* ─── 2. SUMMARY METRIC CARDS (CONSISTENT 3-TIER SAAS STRIP - MATCHING DASHBOARD) ─── */}
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 shrink-0">
        {/* Metric 1: Sesi Aktif Hari Ini */}
        <div className="bg-white border border-slate-200/90 rounded-xl p-3.5 shadow-xs flex flex-col justify-between hover:border-slate-300 transition-all">
          <div className="flex items-center justify-between pb-1.5 border-b border-slate-100">
            <span className="text-xs font-bold text-slate-700 uppercase tracking-wider truncate">
              Sesi Pembelajaran Aktif
            </span>
            <div className="w-7 h-7 rounded-lg bg-blue-50 text-blue-900 border border-blue-200 flex items-center justify-center shrink-0">
              <PlayCircle className="w-3.5 h-3.5" />
            </div>
          </div>
          <div className="my-1.5">
            <div className="flex items-baseline justify-between gap-2">
              <span className="text-lg sm:text-xl font-extrabold text-slate-900 tracking-tight truncate">
                Matematika Terapan
              </span>
              <span className="text-xs font-bold px-2 py-0.5 rounded-md bg-blue-900 text-white shadow-2xs shrink-0 font-mono">
                MAT-09
              </span>
            </div>
            <p className="text-xs text-slate-500 font-medium mt-0.5 truncate">
              09:15 - 10:45 WIB • Ruang Kelas IX-A
            </p>
          </div>
          <div className="pt-2 border-t border-slate-100 flex items-center justify-between text-xs text-slate-700 font-semibold">
            <span>Guru: Dra. Siti Rahayu, M.Pd.</span>
            <span className="text-slate-400 font-normal">JP 3-4</span>
          </div>
        </div>

        {/* Metric 2: Sesi Berikutnya Hari Ini */}
        <div className="bg-white border border-slate-200/90 rounded-xl p-3.5 shadow-xs flex flex-col justify-between hover:border-slate-300 transition-all">
          <div className="flex items-center justify-between pb-1.5 border-b border-slate-100">
            <span className="text-xs font-bold text-slate-700 uppercase tracking-wider truncate">
              Sesi Berikutnya (Senin)
            </span>
            <div className="w-7 h-7 rounded-lg bg-slate-100 text-slate-700 border border-slate-200 flex items-center justify-center shrink-0">
              <Clock className="w-3.5 h-3.5" />
            </div>
          </div>
          <div className="my-1.5">
            <div className="flex items-baseline justify-between gap-2">
              <span className="text-lg sm:text-xl font-extrabold text-slate-900 tracking-tight truncate">
                Bahasa Indonesia
              </span>
              <span className="text-xs font-bold px-2 py-0.5 rounded-md bg-slate-100 border border-slate-300 text-slate-700 shadow-2xs shrink-0 font-mono">
                BIN-09
              </span>
            </div>
            <p className="text-xs text-slate-500 font-medium mt-0.5 truncate">
              11:00 - 12:30 WIB • Ruang Kelas IX-A
            </p>
          </div>
          <div className="pt-2 border-t border-slate-100 flex items-center justify-between text-xs text-slate-700 font-semibold">
            <span>Guru: Agus Subekti, S.Pd.</span>
            <span className="text-slate-400 font-normal">JP 5-6</span>
          </div>
        </div>

        {/* Metric 3: Total Beban Belajar */}
        <div className="bg-white border border-slate-200/90 rounded-xl p-3.5 shadow-xs flex flex-col justify-between hover:border-slate-300 transition-all">
          <div className="flex items-center justify-between pb-1.5 border-b border-slate-100">
            <span className="text-xs font-bold text-slate-700 uppercase tracking-wider truncate">
              Beban Belajar Mingguan
            </span>
            <div className="w-7 h-7 rounded-lg bg-blue-50 text-blue-900 border border-blue-200 flex items-center justify-center shrink-0">
              <BookOpen className="w-3.5 h-3.5" />
            </div>
          </div>
          <div className="my-1.5">
            <div className="flex items-baseline justify-between">
              <span className="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight font-mono">
                34 JP
              </span>
              <span className="text-xs font-bold px-2 py-0.5 rounded-md bg-blue-900 text-white shadow-2xs">
                5 Hari Sekolah
              </span>
            </div>
            <p className="text-xs text-slate-500 font-medium mt-0.5 truncate">
              11 Mata Pelajaran Aktif (Fase D)
            </p>
          </div>
          <div className="pt-2 border-t border-slate-100 flex items-center justify-between text-xs text-slate-700 font-semibold">
            <span>Kurikulum Merdeka 2026/2027</span>
            <span className="text-slate-400 font-normal">SMP Labschool UM</span>
          </div>
        </div>
      </div>

      {/* ─── 3. MAIN CONTENT CONTAINER (FLEX-1 WITH INTERNAL SCROLL - ZERO PAGE SCROLL ON DESKTOP) ─── */}
      {activeMainTab === 'jadwal' && (
        <div className="flex-1 min-h-0 bg-white border border-slate-200/90 rounded-xl p-3.5 sm:p-4 shadow-xs flex flex-col overflow-hidden">
          {/* Controls Bar: Day Selector + Dual-View Switcher */}
          <div className="shrink-0 flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 pb-3 border-b border-slate-100">
            {/* View Mode Switcher */}
            <div className="flex items-center gap-2">
              <span className="text-xs font-bold text-slate-700 uppercase tracking-wider hidden md:inline">
                Tampilan:
              </span>
              <div className="flex items-center bg-slate-100 p-0.5 rounded-lg border border-slate-200 shadow-2xs">
                <button
                  type="button"
                  onClick={() => setScheduleViewMode('timeline')}
                  className={`px-2.5 py-1 rounded-md text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer ${
                    scheduleViewMode === 'timeline'
                      ? 'bg-blue-900 text-white shadow-xs'
                      : 'text-slate-600 hover:text-slate-900'
                  }`}
                >
                  <ListOrdered className="w-3.5 h-3.5" />
                  <span>Timeline Harian</span>
                </button>
                <button
                  type="button"
                  onClick={() => setScheduleViewMode('matriks')}
                  className={`px-2.5 py-1 rounded-md text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer ${
                    scheduleViewMode === 'matriks'
                      ? 'bg-blue-900 text-white shadow-xs'
                      : 'text-slate-600 hover:text-slate-900'
                  }`}
                >
                  <LayoutGrid className="w-3.5 h-3.5" />
                  <span>Matriks Mingguan</span>
                </button>
              </div>
            </div>

            {/* Day Selector (Pills for Timeline mode) */}
            {scheduleViewMode === 'timeline' ? (
              <div className="flex items-center gap-1 overflow-x-auto pb-0.5 sm:pb-0">
                {days.map((day) => {
                  const isSelected = selectedDay === day;
                  const isToday = day === 'Senin';
                  return (
                    <button
                      key={day}
                      type="button"
                      onClick={() => setSelectedDay(day)}
                      className={`px-3 py-1 rounded-lg text-xs font-bold transition-all shrink-0 flex items-center gap-1.5 cursor-pointer border ${
                        isSelected
                          ? 'bg-blue-900 text-white border-blue-900 shadow-xs'
                          : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50'
                      }`}
                    >
                      <span>{day}</span>
                      {isToday && (
                        <span className={`text-[10px] px-1 py-0.2 rounded font-black ${
                          isSelected ? 'bg-white/20 text-white' : 'bg-blue-900 text-white'
                        }`}>
                          Hari Ini
                        </span>
                      )}
                    </button>
                  );
                })}
              </div>
            ) : (
              <span className="text-xs font-semibold text-slate-500 hidden sm:inline">
                Jadwal KBM Resmi SMP Labschool UM
              </span>
            )}
          </div>

          {/* ─── SCROLLABLE INNER WORKSPACE ─── */}
          <div className="flex-1 min-h-0 overflow-y-auto pt-3 pr-1 space-y-3">
            {/* ─── VIEW 1: TIMELINE HARIAN (COMPACT & CLEAN) ─── */}
            {scheduleViewMode === 'timeline' && (
              <div className="space-y-3">
                {isLoading ? (
                  <ScheduleListSkeleton />
                ) : isError ? (
                  <ErrorState
                    title="Gagal Memuat Jadwal Pelajaran"
                    message="Tidak dapat mengambil jadwal pelajaran dari server. Silakan coba kembali."
                    onRetry={() => refetch()}
                  />
                ) : dailySchedules.length === 0 ? (
                  <EmptyState
                    icon={CalendarDays}
                    title={`Tidak Ada Jadwal Hari ${selectedDay}`}
                    description="Tidak ada sesi pembelajaran tatap muka yang dijadwalkan pada hari ini."
                  />
                ) : (
                  <>
                    {/* Daily Subtitle Bar */}
                    <div className="flex items-center justify-between p-2.5 px-3 bg-slate-50 rounded-xl border border-slate-200/90 text-xs">
                      <div className="flex items-center gap-2">
                        <CalendarDays className="w-3.5 h-3.5 text-blue-900" />
                        <span className="font-extrabold text-slate-900">
                          Jadwal Hari {selectedDay}
                        </span>
                        {selectedDay === 'Senin' && (
                          <span className="px-1.5 py-0.5 rounded text-[10px] font-extrabold bg-blue-100 text-blue-900">
                            Hari Ini
                          </span>
                        )}
                      </div>
                      <span className="text-slate-500 font-semibold text-[11px]">
                        Total {dailySchedules.length} Sesi Tatap Muka ({dailySchedules.length * 2} JP)
                      </span>
                    </div>

                    {/* Vertical Timeline */}
                    <div className="relative pl-6 sm:pl-7 space-y-2.5 before:content-[''] before:absolute before:left-2 sm:before:left-2.5 before:top-2 before:bottom-2 before:w-0.5 before:bg-slate-200">

                  {/* Node 1: Pembiasaan Pagi */}
                  <div className="relative group">
                    <div className="absolute -left-6 sm:-left-7 top-2.5 w-3.5 h-3.5 rounded-full bg-slate-300 border-2 border-white shadow-2xs"></div>
                    <div className="p-2.5 px-3 bg-slate-50/80 border border-slate-200/90 rounded-xl flex items-center justify-between gap-3 text-xs">
                      <div className="flex items-center gap-2.5">
                        <div className="w-7 h-7 rounded-lg bg-slate-200 text-slate-700 flex items-center justify-center font-bold shrink-0">
                          <Flag className="w-3.5 h-3.5" />
                        </div>
                        <div>
                          <span className="font-bold text-slate-900 block leading-tight">
                            {selectedDay === 'Senin' 
                              ? 'Upacara Bendera Hari Senin' 
                              : selectedDay === 'Jumat' 
                              ? 'Senam Pagi & Kultum Jumat' 
                              : 'Pembiasaan Literasi Pagi & Doa Bersama'}
                          </span>
                          <span className="text-[11px] text-slate-500">
                            {selectedDay === 'Senin' || selectedDay === 'Jumat' ? 'Lapangan Utama Labschool' : 'Ruang Kelas IX-A'}
                          </span>
                        </div>
                      </div>
                      <span className="font-mono font-bold text-slate-600 bg-white px-2 py-0.5 rounded-md border border-slate-200 text-[11px] shrink-0">
                        06:45 - 07:15 WIB
                      </span>
                    </div>
                  </div>

                  {/* Class Sessions & Interleaved Breaks */}
                  {dailySchedules.map((sch, idx) => {
                    const isCurrentSession = selectedDay === 'Senin' && sch.period === 'JP 3-4';
                    const isCompletedSession = selectedDay === 'Senin' && sch.period === 'JP 1-2';
                    const isUpcomingSession = selectedDay === 'Senin' && sch.period === 'JP 5-6';

                    return (
                      <React.Fragment key={sch.id}>
                        {/* Class Session Item */}
                        <div className="relative group">
                          {/* Timeline Node Icon */}
                          <div className={`absolute -left-6 sm:-left-7 top-3 w-3.5 h-3.5 rounded-full border-2 border-white shadow-2xs transition-all ${
                            isCurrentSession 
                              ? 'bg-blue-900 ring-2 ring-blue-100' 
                              : isCompletedSession 
                              ? 'bg-slate-500' 
                              : 'bg-slate-300'
                          }`}></div>

                          <div className={`p-3 px-3.5 rounded-xl border transition-all ${
                            isCurrentSession
                              ? 'bg-blue-50/20 border-2 border-blue-900 shadow-xs'
                              : 'bg-white border-slate-200/90 hover:border-slate-300 shadow-2xs'
                          }`}>
                            <div className="flex items-center justify-between pb-2 border-b border-slate-100">
                              <div className="flex items-center gap-2">
                                <span className="px-1.5 py-0.2 rounded text-[10px] font-black uppercase tracking-wider bg-slate-100 text-slate-800 border border-slate-200">
                                  {sch.period}
                                </span>
                                <span className="text-xs font-mono font-bold text-slate-700 flex items-center gap-1">
                                  <Clock className="w-3 h-3 text-slate-400" />
                                  {sch.time_range}
                                </span>
                              </div>

                              {/* Status Badges */}
                              <div>
                                {isCurrentSession && (
                                  <span className="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-extrabold bg-blue-900 text-white shadow-2xs font-mono">
                                    Sedang Berlangsung
                                  </span>
                                )}
                                {isCompletedSession && (
                                  <span className="inline-flex items-center gap-1 px-2 py-0.2 rounded text-[11px] font-bold bg-slate-100 text-slate-700 border border-slate-200">
                                    <CheckCircle2 className="w-3 h-3 text-slate-600" />
                                    Selesai
                                  </span>
                                )}
                                {isUpcomingSession && (
                                  <span className="inline-flex items-center gap-1 px-2 py-0.2 rounded text-[11px] font-bold bg-amber-50 text-amber-900 border border-amber-200">
                                    Sesi Berikutnya
                                  </span>
                                )}
                              </div>
                            </div>

                            <div className="pt-2 flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs">
                              <div className="space-y-0.5">
                                <div className="flex items-center gap-2">
                                  <span className="text-[10px] font-mono font-bold text-slate-400">
                                    {sch.subject_code}
                                  </span>
                                  <h3 className="font-extrabold text-sm text-slate-900 leading-snug">
                                    {sch.subject_name}
                                  </h3>
                                </div>
                                <div className="flex items-center gap-2 text-slate-500">
                                  {sch.teacher_avatar_url ? (
                                    <Image
                                      src={sch.teacher_avatar_url}
                                      alt={sch.teacher_name}
                                      width={20}
                                      height={20}
                                      className="w-5 h-5 rounded-full object-cover border border-slate-200 shrink-0"
                                    />
                                  ) : (
                                    <div className="w-5 h-5 rounded-full bg-slate-200 text-slate-700 flex items-center justify-center font-bold text-[10px] shrink-0">
                                      {sch.teacher_name.charAt(0)}
                                    </div>
                                  )}
                                  <span className="text-[11px]">Guru: <strong className="text-slate-800 font-semibold">{sch.teacher_name}</strong></span>
                                </div>
                              </div>

                              <div className="flex items-center gap-1.5 shrink-0">
                                <span className="px-2 py-1 rounded-md text-[11px] font-semibold bg-slate-50 text-slate-700 border border-slate-200 flex items-center gap-1">
                                  <MapPin className="w-3 h-3 text-slate-400" />
                                  <span>{sch.room_name}</span>
                                </span>
                                {sch.room_name.includes('Lab') && (
                                  <span className="px-2 py-0.5 rounded text-[10px] font-black uppercase bg-blue-50 text-blue-900 border border-blue-200">
                                    Praktikum Lab
                                  </span>
                                )}
                              </div>
                            </div>
                          </div>
                        </div>

                        {/* Break 1 after JP 1-2 */}
                        {idx === 0 && (
                          <div className="relative group">
                            <div className="absolute -left-6 sm:-left-7 top-2 w-3.5 h-3.5 rounded-full bg-amber-400 border-2 border-white shadow-2xs"></div>
                            <div className="p-2 px-3 bg-amber-50/70 border border-amber-200/90 rounded-xl flex items-center justify-between gap-3 text-xs text-amber-950">
                              <div className="flex items-center gap-2">
                                <Coffee className="w-3.5 h-3.5 text-amber-700 shrink-0" />
                                <span className="font-bold text-[11px]">Istirahat Pagi 1 (15 Menit) • Kantin Sehat & Pojok Literasi</span>
                              </div>
                              <span className="font-mono font-bold bg-white px-2 py-0.2 rounded border border-amber-200 text-amber-900 text-[10px]">
                                09:00 - 09:15 WIB
                              </span>
                            </div>
                          </div>
                        )}

                        {/* Break 2 after JP 3-4 */}
                        {idx === 1 && dailySchedules.length > 2 && (
                          <div className="relative group">
                            <div className="absolute -left-6 sm:-left-7 top-2 w-3.5 h-3.5 rounded-full bg-amber-400 border-2 border-white shadow-2xs"></div>
                            <div className="p-2 px-3 bg-amber-50/70 border border-amber-200/90 rounded-xl flex items-center justify-between gap-3 text-xs text-amber-950">
                              <div className="flex items-center gap-2">
                                <Coffee className="w-3.5 h-3.5 text-amber-700 shrink-0" />
                                <span className="font-bold text-[11px]">Istirahat Siang 2 (15 Menit) • Persiapan Sesi Siang</span>
                              </div>
                              <span className="font-mono font-bold bg-white px-2 py-0.2 rounded border border-amber-200 text-amber-900 text-[10px]">
                                10:45 - 11:00 WIB
                              </span>
                            </div>
                          </div>
                        )}
                      </React.Fragment>
                    );
                  })}

                  {/* Node 3: Kepulangan & Sholat */}
                  <div className="relative group">
                    <div className="absolute -left-6 sm:-left-7 top-2.5 w-3.5 h-3.5 rounded-full bg-slate-300 border-2 border-white shadow-2xs"></div>
                    <div className="p-2.5 px-3 bg-slate-50/80 border border-slate-200/90 rounded-xl flex items-center justify-between gap-3 text-xs">
                      <div className="flex items-center gap-2.5">
                        <div className="w-7 h-7 rounded-lg bg-slate-200 text-slate-700 flex items-center justify-center font-bold shrink-0">
                          <Sun className="w-3.5 h-3.5" />
                        </div>
                        <div>
                          <span className="font-bold text-slate-900 block leading-tight">
                            {selectedDay === 'Jumat' 
                              ? 'Sholat Jumat Berjamaah & Kepulangan Siswa' 
                              : 'Sholat Dhuhur Berjamaah & Kepulangan'}
                          </span>
                          <span className="text-[11px] text-slate-500">
                            Masjid Al-Hikmah Labschool UM
                          </span>
                        </div>
                      </div>
                      <span className="font-mono font-bold text-slate-600 bg-white px-2 py-0.5 rounded-md border border-slate-200 text-[11px] shrink-0">
                        {selectedDay === 'Jumat' ? '11:15 - 12:30 WIB' : '12:30 - 13:15 WIB'}
                      </span>
                    </div>
                  </div>
                </div>
                </>
              )}
              </div>
            )}


            {/* ─── VIEW 2: TABEL MATRIKS MINGGUAN (STANDAR SEKOLAH) ─── */}
            {scheduleViewMode === 'matriks' && (
              <div className="overflow-x-auto rounded-xl border border-slate-200 shadow-2xs">
                <table className="w-full text-xs text-left border-collapse min-w-190">
                  <thead>
                    <tr className="bg-slate-100 text-slate-700 uppercase font-black text-[11px] border-b border-slate-200">
                      <th className="p-2.5 px-3 w-28 border-r border-slate-200">Jam Pelajaran</th>
                      {days.map((day) => {
                        const isToday = day === 'Senin';
                        return (
                          <th 
                            key={day} 
                            className={`p-2.5 px-3 text-center border-r border-slate-200 last:border-r-0 ${
                              isToday ? 'bg-blue-900 text-white font-extrabold' : ''
                            }`}
                          >
                            <span className="block">{day}</span>
                            {isToday && (
                              <span className="text-[10px] font-medium opacity-90">Hari Ini</span>
                            )}
                          </th>
                        );
                      })}
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-200 text-slate-800">
                    {/* Row 0: Pembiasaan Pagi */}
                    <tr className="bg-slate-50/50">
                      <td className="p-2.5 px-3 font-mono font-bold text-slate-600 border-r border-slate-200 text-[11px]">
                        06:45 - 07:15
                        <span className="block text-[10px] text-slate-400 font-sans">Pembiasaan</span>
                      </td>
                      <td className="p-2.5 text-center border-r border-slate-200 font-semibold bg-blue-50/20 text-xs">
                        Upacara Bendera
                      </td>
                      <td colSpan={3} className="p-2.5 text-center border-r border-slate-200 font-medium text-slate-500 text-xs">
                        Literasi Pagi & Doa Bersama
                      </td>
                      <td className="p-2.5 text-center font-semibold text-slate-700 text-xs">
                        Senam & Kultum
                      </td>
                    </tr>

                    {/* Row 1: JP 1-2 */}
                    <tr>
                      <td className="p-2.5 px-3 font-mono font-bold text-slate-700 border-r border-slate-200 bg-slate-50/30 text-[11px]">
                        07:30 - 09:00
                        <span className="block text-[10px] text-blue-900 font-extrabold font-sans">JP 1-2</span>
                      </td>
                      {days.map((day) => {
                        const sch = getMatrixSchedule(day, '1-2');
                        const isToday = day === 'Senin';
                        return (
                          <td 
                            key={day} 
                            className={`p-2.5 px-3 align-top border-r border-slate-200 last:border-r-0 space-y-0.5 ${
                              isToday ? 'bg-blue-50/25' : ''
                            }`}
                          >
                            {sch ? (
                              <div>
                                <span className="text-[10px] font-mono font-bold text-slate-400 block">
                                  {sch.subject_code}
                                </span>
                                <strong className="text-xs font-extrabold text-slate-900 block leading-tight">
                                  {sch.subject_name}
                                </strong>
                                <span className="text-[11px] text-slate-500 block truncate">
                                  {sch.teacher_name}
                                </span>
                                <span className="inline-block mt-0.5 text-[10px] px-1.5 py-0.2 rounded bg-slate-100 text-slate-700 font-semibold">
                                  {sch.room_name}
                                </span>
                              </div>
                            ) : (
                              <span className="text-slate-400 italic text-[11px]">-</span>
                            )}
                          </td>
                        );
                      })}
                    </tr>

                    {/* Break Row 1 */}
                    <tr className="bg-amber-50/70 border-y border-amber-200 text-center font-bold text-amber-900 text-[10px]">
                      <td className="p-1.5 px-3 font-mono border-r border-amber-200">09:00 - 09:15</td>
                      <td colSpan={5} className="p-1.5 uppercase tracking-wider">
                        ☕ Istirahat Pagi 1 (15 Menit) • Kantin Sehat & Pojok Literasi
                      </td>
                    </tr>

                    {/* Row 2: JP 3-4 */}
                    <tr>
                      <td className="p-2.5 px-3 font-mono font-bold text-slate-700 border-r border-slate-200 bg-slate-50/30 text-[11px]">
                        09:15 - 10:45
                        <span className="block text-[10px] text-blue-900 font-extrabold font-sans">JP 3-4</span>
                      </td>
                      {days.map((day) => {
                        const sch = getMatrixSchedule(day, '3-4') || getMatrixSchedule(day, '3');
                        const isToday = day === 'Senin';
                        return (
                          <td 
                            key={day} 
                            className={`p-2.5 px-3 align-top border-r border-slate-200 last:border-r-0 space-y-0.5 ${
                              isToday ? 'bg-blue-50/25' : ''
                            }`}
                          >
                            {sch ? (
                              <div>
                                <span className="text-[10px] font-mono font-bold text-slate-400 block">
                                  {sch.subject_code}
                                </span>
                                <strong className="text-xs font-extrabold text-slate-900 block leading-tight">
                                  {sch.subject_name}
                                </strong>
                                <span className="text-[11px] text-slate-500 block truncate">
                                  {sch.teacher_name}
                                </span>
                                <span className="inline-block mt-0.5 text-[10px] px-1.5 py-0.2 rounded bg-slate-100 text-slate-700 font-semibold">
                                  {sch.room_name}
                                </span>
                              </div>
                            ) : (
                              <span className="text-slate-400 italic text-[11px]">-</span>
                            )}
                          </td>
                        );
                      })}
                    </tr>

                    {/* Break Row 2 */}
                    <tr className="bg-amber-50/70 border-y border-amber-200 text-center font-bold text-amber-900 text-[10px]">
                      <td className="p-1.5 px-3 font-mono border-r border-amber-200">10:45 - 11:00</td>
                      <td colSpan={4} className="p-1.5 uppercase tracking-wider">
                        ☕ Istirahat Siang 2 (15 Menit)
                      </td>
                      <td className="p-1.5 uppercase tracking-wider bg-emerald-50 text-emerald-900">
                        Persiapan Sholat Jumat
                      </td>
                    </tr>

                    {/* Row 3: JP 5-6 */}
                    <tr>
                      <td className="p-2.5 px-3 font-mono font-bold text-slate-700 border-r border-slate-200 bg-slate-50/30 text-[11px]">
                        11:00 - 12:30
                        <span className="block text-[10px] text-blue-900 font-extrabold font-sans">JP 5-6</span>
                      </td>
                      {days.map((day) => {
                        const sch = getMatrixSchedule(day, '5-6');
                        const isToday = day === 'Senin';
                        return (
                          <td 
                            key={day} 
                            className={`p-2.5 px-3 align-top border-r border-slate-200 last:border-r-0 space-y-0.5 ${
                              isToday ? 'bg-blue-50/25' : ''
                            }`}
                          >
                            {day === 'Jumat' ? (
                              <div className="p-1.5 bg-emerald-50 rounded-lg border border-emerald-200 text-center">
                                <span className="font-extrabold text-emerald-900 block text-xs">Sholat Jumat</span>
                                <span className="text-[10px] text-emerald-700">Masjid Al-Hikmah</span>
                              </div>
                            ) : sch ? (
                              <div>
                                <span className="text-[10px] font-mono font-bold text-slate-400 block">
                                  {sch.subject_code}
                                </span>
                                <strong className="text-xs font-extrabold text-slate-900 block leading-tight">
                                  {sch.subject_name}
                                </strong>
                                <span className="text-[11px] text-slate-500 block truncate">
                                  {sch.teacher_name}
                                </span>
                                <span className="inline-block mt-0.5 text-[10px] px-1.5 py-0.2 rounded bg-slate-100 text-slate-700 font-semibold">
                                  {sch.room_name}
                                </span>
                              </div>
                            ) : (
                              <span className="text-slate-400 italic text-[11px]">-</span>
                            )}
                          </td>
                        );
                      })}
                    </tr>
                  </tbody>
                </table>
              </div>
            )}
          </div>
        </div>
      )}

      {/* ─── TAB 2: KALENDER AKADEMIK RESMI ─── */}
      {activeMainTab === 'kalender' && (
        <div className="flex-1 min-h-0 bg-white border border-slate-200/90 rounded-xl p-3.5 sm:p-4 shadow-xs flex flex-col overflow-hidden">
          <div className="shrink-0 flex items-center justify-between pb-3 border-b border-slate-100">
            <div>
              <h3 className="font-extrabold text-sm sm:text-base text-slate-900">
                Agenda Kalender Pendidikan Semester Gasal 2026/2027
              </h3>
              <p className="text-xs text-slate-500 font-normal mt-0.5">
                SMP Labschool Universitas Negeri Malang • Jadwal Asesmen & Libur Resmi
              </p>
            </div>
            <span className="px-2.5 py-1 rounded-md text-xs font-bold bg-blue-50 text-blue-900 border border-blue-200 shadow-2xs">
              Kalender Resmi
            </span>
          </div>

          {/* Grouped by Months with Internal Scroll */}
          <div className="flex-1 min-h-0 overflow-y-auto pt-3 pr-1 space-y-4">
            {calendarMonths.map((group, gIdx) => (
              <div key={gIdx} className="space-y-2.5">
                <div className="flex items-center gap-2">
                  <span className="w-2 h-2 rounded-full bg-blue-900"></span>
                  <h4 className="text-xs font-extrabold uppercase tracking-wider text-slate-800">
                    {group.month}
                  </h4>
                </div>

                <div className="space-y-2">
                  {group.events.map((evt, idx) => (
                    <div
                      key={`${evt.date}-${evt.title}-${idx}`}
                      className={`p-3 px-3.5 rounded-xl border transition-all flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-2xs ${
                        evt.isUrgent 
                          ? 'bg-rose-50/40 border-rose-200' 
                          : 'bg-slate-50/60 border-slate-200/90 hover:bg-white hover:border-slate-300'
                      }`}
                    >
                      <div className="flex items-start gap-2.5 min-w-0">
                        <div className={`w-8 h-8 rounded-lg border flex items-center justify-center shrink-0 font-bold shadow-2xs ${
                          evt.isUrgent ? 'bg-rose-100 text-rose-800 border-rose-300' : 'bg-blue-50 text-blue-900 border-blue-200'
                        }`}>
                          <CalendarIcon className="w-4 h-4" />
                        </div>
                        <div className="min-w-0">
                          <div className="flex items-center gap-2">
                            <span className={`px-2 py-0.2 rounded text-[10px] font-bold border uppercase tracking-wider ${evt.badgeColor}`}>
                              {evt.category}
                            </span>
                            <span className="text-xs font-semibold text-slate-400">{evt.date}</span>
                          </div>
                          <h4 className="font-bold text-xs sm:text-sm text-slate-900 mt-0.5 truncate">{evt.title}</h4>
                          <p className="text-[11px] text-slate-500 font-normal">{evt.note}</p>
                        </div>
                      </div>

                      <span className="text-xs font-mono font-bold text-slate-700 px-2.5 py-1 bg-white rounded-lg border border-slate-200 self-start sm:self-auto shrink-0 shadow-2xs">
                        {evt.date}
                      </span>
                    </div>
                  ))}
                </div>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}
