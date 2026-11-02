'use client';

import React, { useState } from 'react';
import { 
  Calendar, 
  CalendarCheck, 
  Clock, 
  MapPin, 
  Printer, 
  GraduationCap, 
  BookOpen,
  RefreshCw,
  AlertCircle
} from 'lucide-react';
import { useTeacherSchedules } from '@/lib/use-teacher-data';

interface ScheduleSlot {
  id: string;
  day: 'Senin' | 'Selasa' | 'Rabu' | 'Kamis' | 'Jumat';
  period: number;
  timeRange: string;
  className: string;
  subjectName: string;
  roomName: string;
  jpCount: number;
}

const normalizeDay = (dayName?: string, dayOfWeek?: number): 'Senin' | 'Selasa' | 'Rabu' | 'Kamis' | 'Jumat' => {
  if (dayName) {
    const d = dayName.trim().toLowerCase();
    if (d.includes('senin') || d === 'monday') return 'Senin';
    if (d.includes('selasa') || d === 'tuesday') return 'Selasa';
    if (d.includes('rabu') || d === 'wednesday') return 'Rabu';
    if (d.includes('kamis') || d === 'thursday') return 'Kamis';
    if (d.includes('jumat') || d === "jum'at" || d === 'friday') return 'Jumat';
  }
  if (dayOfWeek === 1) return 'Senin';
  if (dayOfWeek === 2) return 'Selasa';
  if (dayOfWeek === 3) return 'Rabu';
  if (dayOfWeek === 4) return 'Kamis';
  if (dayOfWeek === 5) return 'Jumat';
  return 'Senin';
};

export default function JadwalMengajarPage() {
  const [selectedDayFilter, setSelectedDayFilter] = useState<string>('ALL');
  const { data: liveSchedules = [], isLoading, isError, refetch } = useTeacherSchedules();

  const scheduleSlots: ScheduleSlot[] = (liveSchedules || []).map((s, idx) => ({
    id: s.id,
    day: normalizeDay(s.dayName, s.dayOfWeek),
    period: idx + 1,
    timeRange: s.startTime && s.endTime ? `${s.startTime} - ${s.endTime}` : '-',
    className: s.className || 'Kelas',
    subjectName: s.subjectName || 'Mata Pelajaran',
    roomName: s.roomName || 'Ruang Kelas',
    jpCount: 2,
  }));

  const daysOfWeek = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'] as const;
  const totalJP = scheduleSlots.reduce((acc, curr) => acc + curr.jpCount, 0);

  const uniqueClasses = Array.from(new Set(scheduleSlots.map((s) => s.className).filter(Boolean)));
  const uniqueRooms = Array.from(new Set(scheduleSlots.map((s) => s.roomName).filter(Boolean)));

  const todayIndex = new Date().getDay();
  const dayNamesMap: Record<number, 'Senin' | 'Selasa' | 'Rabu' | 'Kamis' | 'Jumat'> = {
    1: 'Senin',
    2: 'Selasa',
    3: 'Rabu',
    4: 'Kamis',
    5: 'Jumat',
  };
  const todayName = dayNamesMap[todayIndex];
  const todaySlots = todayName ? scheduleSlots.filter((s) => s.day === todayName) : [];

  const handlePrint = () => {
    window.print();
  };

  return (
    <div className="flex flex-col gap-3.5 max-w-full xl:h-[calc(100vh-112px)] xl:overflow-hidden text-slate-900">
      {/* ─── PAGE HEADER (COMPACT & PROPORTIONED) ─── */}
      <div className="bg-white border border-slate-200/90 rounded-2xl p-3.5 sm:p-4 shadow-xs shrink-0 flex flex-col md:flex-row md:items-center justify-between gap-3">
        <div>
          <div className="flex items-center gap-2 mb-1">
            <span className="text-[11px] font-black uppercase tracking-wider px-2 py-0.5 rounded bg-blue-900 text-white shadow-2xs">
              Akademik &amp; KBM
            </span>
            <span className="text-xs text-slate-500 font-semibold">• BE-ACD-01 (P0)</span>
          </div>
          <h1 className="text-xl font-extrabold text-slate-900 tracking-tight">
            Jadwal Mengajar Mingguan Guru
          </h1>
          <p className="text-xs text-slate-600 mt-0.5 line-clamp-1 max-w-2xl">
            Struktur jadwal tatap muka laboratorium dan kelas Semester Ganjil T.A. 2026/2027 Labschool UM.
          </p>
        </div>

        {/* Action Buttons */}
        <div className="flex items-center gap-2 shrink-0">
          <button
            type="button"
            onClick={handlePrint}
            className="px-3.5 py-1.5 rounded-lg text-xs font-bold border border-slate-300 hover:bg-slate-100 text-slate-700 flex items-center gap-1.5 shadow-xs cursor-pointer transition-colors"
          >
            <Printer className="w-3.5 h-3.5" />
            <span>Cetak</span>
          </button>
          <a
            href="/guru/jurnal"
            className="px-3.5 py-1.5 rounded-lg text-xs font-black bg-blue-900 hover:bg-blue-950 text-white flex items-center gap-1.5 shadow-xs cursor-pointer transition-colors"
          >
            <BookOpen className="w-3.5 h-3.5" />
            <span>Buka Jurnal KBM ➔</span>
          </a>
        </div>
      </div>

      {/* ─── 4 SUMMARY KPI CARDS ─── */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-2.5 sm:gap-3 shrink-0">
        {/* Card 1: Beban Tatap Muka */}
        <div className="bg-white border border-slate-200/90 rounded-2xl p-3.5 shadow-xs flex items-center justify-between gap-3">
          <div>
            <span className="text-[10px] font-black text-slate-500 uppercase tracking-wider block">
              Beban Tatap Muka
            </span>
            <div className="text-xl sm:text-2xl font-black text-slate-900 font-mono mt-0.5">
              {isLoading ? '-' : totalJP} <span className="text-xs font-bold text-slate-500 font-sans">JP / Minggu</span>
            </div>
            <p className="text-[10px] text-slate-500 font-semibold mt-0.5">
              {isLoading ? 'Memuat data...' : isError ? 'Gagal memuat' : 'Min. 24 JP Sertifikasi'}
            </p>
          </div>
          <div className="w-10 h-10 rounded-xl bg-blue-50 text-blue-900 border border-blue-200 flex items-center justify-center shrink-0 shadow-2xs">
            <Clock className="w-5 h-5" />
          </div>
        </div>

        {/* Card 2: Rombel Diampu */}
        <div className="bg-white border border-slate-200/90 rounded-2xl p-3.5 shadow-xs flex items-center justify-between gap-3">
          <div>
            <span className="text-[10px] font-black text-slate-500 uppercase tracking-wider block">
              Rombel Diampu
            </span>
            <div className="text-xl sm:text-2xl font-black text-slate-900 font-mono mt-0.5">
              {isLoading ? '-' : uniqueClasses.length} <span className="text-xs font-bold text-slate-500 font-sans">Rombel Aktif</span>
            </div>
            <p className="text-[10px] text-slate-500 font-semibold mt-0.5 truncate max-w-[160px]" title={uniqueClasses.join(', ')}>
              {isLoading
                ? 'Memuat data...'
                : isError
                ? 'Gagal memuat'
                : uniqueClasses.length > 0
                ? uniqueClasses.join(', ')
                : 'Belum ada rombel'}
            </p>
          </div>
          <div className="w-10 h-10 rounded-xl bg-slate-100 text-slate-700 border border-slate-200 flex items-center justify-center shrink-0 shadow-2xs">
            <GraduationCap className="w-5 h-5" />
          </div>
        </div>

        {/* Card 3: Ruang Pembelajaran */}
        <div className="bg-white border border-slate-200/90 rounded-2xl p-3.5 shadow-xs flex items-center justify-between gap-3">
          <div>
            <span className="text-[10px] font-black text-slate-500 uppercase tracking-wider block">
              Ruang Pembelajaran
            </span>
            <div className="text-xl sm:text-2xl font-black text-slate-900 font-mono mt-0.5">
              {isLoading ? '-' : uniqueRooms.length} <span className="text-xs font-bold text-slate-500 font-sans">Ruang Terdaftar</span>
            </div>
            <p className="text-[10px] text-slate-500 font-semibold mt-0.5 truncate max-w-[160px]" title={uniqueRooms.join(' & ')}>
              {isLoading
                ? 'Memuat data...'
                : isError
                ? 'Gagal memuat'
                : uniqueRooms.length > 0
                ? uniqueRooms.join(' & ')
                : 'Belum ada ruang'}
            </p>
          </div>
          <div className="w-10 h-10 rounded-xl bg-slate-100 text-slate-700 border border-slate-200 flex items-center justify-center shrink-0 shadow-2xs">
            <MapPin className="w-5 h-5" />
          </div>
        </div>

        {/* Card 4: Jadwal Hari Ini */}
        <div className="bg-white border border-slate-200/90 rounded-2xl p-3.5 shadow-xs flex items-center justify-between gap-3">
          <div>
            <span className="text-[10px] font-black text-blue-900 uppercase tracking-wider block">
              Jadwal Hari Ini {todayName ? `(${todayName})` : ''}
            </span>
            <div className="text-xl sm:text-2xl font-black text-slate-900 font-mono mt-0.5">
              {isLoading ? '-' : todaySlots.length} <span className="text-xs font-bold text-slate-500 font-sans">Sesi Mengajar</span>
            </div>
            <p className="text-[10px] text-slate-500 font-semibold mt-0.5 truncate max-w-[160px]">
              {isLoading
                ? 'Memuat data...'
                : isError
                ? 'Gagal memuat'
                : todaySlots.length > 0
                ? todaySlots.map((s) => s.timeRange).join(' & ')
                : 'Tidak ada sesi hari ini'}
            </p>
          </div>
          <div className="w-10 h-10 rounded-xl bg-blue-50 text-blue-900 border border-blue-200 flex items-center justify-center shrink-0 shadow-2xs">
            <CalendarCheck className="w-5 h-5" />
          </div>
        </div>
      </div>

      {/* ─── TIMETABLE CONTAINER (RESPONSIVE FULL-HEIGHT CARD) ─── */}
      <div className="bg-white border border-slate-200/90 rounded-2xl p-4 shadow-xs flex-1 min-h-0 flex flex-col overflow-hidden space-y-3">
        {/* Timetable Header & Day Filter Switcher */}
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 pb-3 border-b border-slate-100 shrink-0">
          <div className="flex items-center gap-2.5">
            <div className="w-7 h-7 rounded-lg bg-blue-50 text-blue-900 border border-blue-200 flex items-center justify-center shrink-0">
              <Calendar className="w-4 h-4" />
            </div>
            <div>
              <h2 className="text-xs font-extrabold text-slate-900 uppercase tracking-wider">
                Matriks Timetable KBM Mingguan
              </h2>
              <p className="text-[11px] text-slate-500 font-normal">
                {selectedDayFilter === 'ALL' 
                  ? 'Jadwal tatap muka laboratorium & kelas Semester Ganjil T.A. 2026/2027' 
                  : `Menampilkan jadwal sesi tatap muka hari ${selectedDayFilter}`}
              </p>
            </div>
          </div>

          {/* Clean Day Filter Tabs */}
          <div className="flex items-center gap-1 bg-slate-100 p-1 rounded-xl border border-slate-200 shrink-0 self-start sm:self-auto">
            <button
              type="button"
              onClick={() => setSelectedDayFilter('ALL')}
              className={`px-3 py-1.5 rounded-lg text-xs font-extrabold transition-all cursor-pointer ${
                selectedDayFilter === 'ALL' 
                  ? 'bg-blue-900 text-white shadow-xs' 
                  : 'text-slate-600 hover:text-slate-900'
              }`}
            >
              Semua Hari
            </button>
            {daysOfWeek.map((day) => {
              const isSelected = selectedDayFilter === day;
              return (
                <button
                  key={day}
                  type="button"
                  onClick={() => setSelectedDayFilter(day)}
                  className={`px-3 py-1.5 rounded-lg text-xs font-extrabold transition-all cursor-pointer ${
                    isSelected 
                      ? 'bg-blue-900 text-white shadow-xs' 
                      : 'text-slate-600 hover:text-slate-900'
                  }`}
                >
                  {day}
                </button>
              );
            })}
          </div>
        </div>

        {/* ─── CONTENT AREA: LOADING / ERROR / EMPTY / JADWAL ─── */}
        <div className="flex-1 min-h-0 overflow-y-auto pr-0.5">
          {isLoading ? (
            <div className="h-full min-h-64 flex flex-col items-center justify-center py-16 px-4 text-center">
              <RefreshCw className="w-8 h-8 text-blue-900 animate-spin mb-3" />
              <h3 className="text-sm font-bold text-slate-800">Memuat Jadwal Mengajar...</h3>
              <p className="text-xs text-slate-500 mt-1">Mengambil data jadwal KBM dari server akademik</p>
            </div>
          ) : isError ? (
            <div className="h-full min-h-64 flex flex-col items-center justify-center py-16 px-4 text-center">
              <div className="w-12 h-12 rounded-2xl bg-rose-50 border border-rose-200 flex items-center justify-center text-rose-600 mb-3 shadow-2xs">
                <AlertCircle className="w-6 h-6" />
              </div>
              <h3 className="text-sm font-bold text-slate-800">Gagal Memuat Jadwal Mengajar</h3>
              <p className="text-xs text-slate-500 mt-1 max-w-md">
                Terjadi kendala saat mengambil data jadwal dari server akademik. Silakan periksa koneksi atau coba muat ulang.
              </p>
              <button
                type="button"
                onClick={() => refetch()}
                className="mt-4 px-4 py-2 rounded-lg text-xs font-bold bg-blue-900 hover:bg-blue-950 text-white flex items-center gap-1.5 shadow-xs cursor-pointer transition-colors"
              >
                <RefreshCw className="w-3.5 h-3.5" />
                <span>Coba Lagi</span>
              </button>
            </div>
          ) : scheduleSlots.length === 0 ? (
            <div className="h-full min-h-64 flex flex-col items-center justify-center py-16 px-4 text-center bg-slate-50/60 rounded-2xl border border-dashed border-slate-200">
              <div className="w-12 h-12 rounded-2xl bg-white border border-slate-200 flex items-center justify-center text-slate-400 mb-3 shadow-2xs">
                <Calendar className="w-6 h-6 opacity-60" />
              </div>
              <h3 className="text-sm font-bold text-slate-800">Belum Ada Jadwal Mengajar</h3>
              <p className="text-xs text-slate-500 mt-1 max-w-md">
                Jadwal mengajar Anda untuk semester ini belum diterbitkan atau belum ditugaskan oleh bagian kurikulum.
              </p>
            </div>
          ) : selectedDayFilter === 'ALL' ? (
            /* Mode 1: Matriks Mingguan 5 Kolom Penuh */
            <div className="grid grid-cols-1 md:grid-cols-5 gap-3 h-full">
              {daysOfWeek.map((day) => {
                const daySlots = scheduleSlots.filter((s) => s.day === day);
                const isToday = todayName ? day === todayName : false;

                return (
                  <div 
                    key={day} 
                    className={`rounded-2xl border flex flex-col transition-all overflow-hidden ${
                      isToday 
                        ? 'border-blue-900 bg-blue-50/20 ring-2 ring-blue-900/15 shadow-xs' 
                        : 'border-slate-200/90 bg-white shadow-2xs'
                    }`}
                  >
                    {/* Day Column Header */}
                    <div className={`p-3 border-b flex items-center justify-between font-extrabold text-xs uppercase tracking-wider ${
                      isToday 
                        ? 'bg-blue-900 text-white border-blue-950 shadow-2xs' 
                        : 'bg-slate-100 text-slate-800 border-slate-200'
                    }`}>
                      <span>{day}</span>
                      {isToday ? (
                        <span className="text-[10px] px-2.5 py-0.5 rounded-full bg-white/20 text-white font-extrabold border border-white/25 shadow-2xs">
                          Hari Ini
                        </span>
                      ) : (
                        <span className="text-[10px] px-2 py-0.5 rounded-md bg-white text-slate-700 font-bold border border-slate-200">
                          {daySlots.length} Sesi
                        </span>
                      )}
                    </div>

                    {/* Sesi Mengajar List */}
                    <div className="p-3 space-y-2.5 flex-1 min-h-64">
                      {daySlots.length === 0 ? (
                        <div className="h-full flex flex-col items-center justify-center text-slate-400 py-12 px-4 text-center">
                          <div className="w-10 h-10 rounded-xl bg-slate-100 border border-slate-200 flex items-center justify-center text-slate-400 mb-2 shadow-2xs">
                            <Clock className="w-5 h-5 opacity-60" />
                          </div>
                          <span className="text-xs font-bold text-slate-600">Tidak Ada Sesi Tatap Muka</span>
                          <span className="text-[10px] text-slate-400 mt-0.5">Waktu persiapan ajar &amp; administrasi</span>
                        </div>
                      ) : (
                        daySlots.map((slot) => (
                          <div
                            key={slot.id}
                            className={`p-3 rounded-xl border transition-all shadow-2xs space-y-2 ${
                              isToday 
                                ? 'bg-white border-blue-200 shadow-xs' 
                                : 'bg-slate-50/60 border-slate-200/90 hover:bg-white hover:border-slate-300'
                            }`}
                          >
                            {/* Time & JP Header */}
                            <div className="flex items-center justify-between">
                              <span className="flex items-center gap-1 text-[10px] font-mono font-bold px-2 py-0.5 rounded bg-slate-100 text-slate-800 border border-slate-200">
                                <Clock className="w-3 h-3 text-slate-400" />
                                {slot.timeRange}
                              </span>
                              <span className="text-[10px] font-black bg-blue-900 text-white px-2 py-0.5 rounded shadow-2xs">
                                {slot.jpCount} JP
                              </span>
                            </div>

                            {/* Class & Subject */}
                            <div>
                              <h3 className="font-extrabold text-xs text-slate-900 leading-tight">
                                {slot.className}
                              </h3>
                              <p className="text-xs text-slate-600 font-semibold mt-0.5">
                                {slot.subjectName}
                              </p>
                            </div>

                            {/* Room Info */}
                            <div className="flex items-center gap-1.5 text-[11px] text-slate-500 font-semibold pt-1.5 border-t border-slate-100">
                              <MapPin className="w-3.5 h-3.5 text-slate-400 shrink-0" />
                              <span className="truncate">{slot.roomName}</span>
                            </div>

                            {/* Direct Shortcut to Jurnal & Presensi on Today's Sessions */}
                            {isToday && (
                              <a
                                href="/guru/jurnal"
                                className="mt-1.5 w-full py-1.5 px-2 rounded-lg text-[11px] font-black bg-blue-900 hover:bg-blue-950 text-white flex items-center justify-center gap-1 shadow-2xs transition-colors cursor-pointer"
                              >
                                <BookOpen className="w-3 h-3" />
                                <span>Input Jurnal &amp; Presensi ➔</span>
                              </a>
                            )}
                          </div>
                        ))
                      )}
                    </div>
                  </div>
                );
              })}
            </div>
          ) : (
            /* Mode 2: Tampilan Harian */
            <div className="max-w-4xl mx-auto w-full py-2">
              {(() => {
                const daySlots = scheduleSlots.filter((s) => s.day === selectedDayFilter);
                const isToday = todayName ? selectedDayFilter === todayName : false;

                if (daySlots.length === 0) {
                  return (
                    <div className="flex flex-col items-center justify-center text-slate-400 py-16 text-center bg-slate-50/60 rounded-2xl border border-dashed border-slate-200">
                      <div className="w-12 h-12 rounded-2xl bg-white border border-slate-200 flex items-center justify-center text-slate-400 mb-2.5 shadow-2xs">
                        <Clock className="w-6 h-6 opacity-60" />
                      </div>
                      <span className="text-sm font-bold text-slate-700">
                        Tidak Ada Jadwal Mengajar pada Hari {selectedDayFilter}
                      </span>
                      <span className="text-xs text-slate-500 mt-1">
                        Waktu dialokasikan untuk persiapan bahan ajar, evaluasi tugas siswa, dan administrasi sekolah.
                      </span>
                    </div>
                  );
                }

                return (
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-3.5">
                    {daySlots.map((slot) => (
                      <div
                        key={slot.id}
                        className={`p-4 rounded-2xl border transition-all shadow-xs space-y-3 ${
                          isToday
                            ? 'bg-blue-50/30 border-blue-900/30 ring-1 ring-blue-900/20'
                            : 'bg-white border-slate-200/90'
                        }`}
                      >
                        {/* Time & JP Header */}
                        <div className="flex items-center justify-between">
                          <span className="flex items-center gap-1.5 font-mono text-xs font-bold px-2.5 py-1 rounded-lg bg-slate-100 text-slate-800 border border-slate-200">
                            <Clock className="w-3.5 h-3.5 text-slate-500" />
                            {slot.timeRange}
                          </span>
                          <span className="text-xs font-black bg-blue-900 text-white px-2.5 py-1 rounded-lg shadow-2xs">
                            {slot.jpCount} JP
                          </span>
                        </div>

                        {/* Class & Subject */}
                        <div>
                          <h3 className="font-extrabold text-base text-slate-900 leading-tight">
                            {slot.className}
                          </h3>
                          <p className="text-xs text-slate-600 font-semibold mt-1">
                            {slot.subjectName}
                          </p>
                        </div>

                        {/* Room Info */}
                        <div className="flex items-center gap-1.5 text-xs text-slate-600 font-semibold pt-2.5 border-t border-slate-100">
                          <MapPin className="w-4 h-4 text-slate-400 shrink-0" />
                          <span>{slot.roomName}</span>
                        </div>

                        {/* Direct Shortcut to Jurnal & Presensi on Today's Sessions */}
                        {isToday && (
                          <a
                            href="/guru/jurnal"
                            className="w-full py-2 px-3 rounded-xl text-xs font-black bg-blue-900 hover:bg-blue-950 text-white flex items-center justify-center gap-1.5 shadow-2xs transition-colors cursor-pointer"
                          >
                            <BookOpen className="w-3.5 h-3.5" />
                            <span>Input Jurnal &amp; Presensi Sesi ➔</span>
                          </a>
                        )}
                      </div>
                    ))}
                  </div>
                );
              })()}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
