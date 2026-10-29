'use client';

import React, { useState } from 'react';
import { 
  Calendar, 
  CalendarCheck, 
  Clock, 
  MapPin, 
  Printer, 
  GraduationCap, 
  BookOpen
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

const defaultScheduleSlots: ScheduleSlot[] = [
  // Senin
  { id: 'sch-1', day: 'Senin', period: 1, timeRange: '07:30 - 09:00', className: 'Kelas IX-A', subjectName: 'Informatika', roomName: 'Lab Komputer 1', jpCount: 2 },
  { id: 'sch-2', day: 'Senin', period: 4, timeRange: '10:00 - 11:30', className: 'Kelas IX-B', subjectName: 'Pemrograman Web', roomName: 'Lab Multimedia', jpCount: 2 },
  // Selasa
  { id: 'sch-3', day: 'Selasa', period: 2, timeRange: '08:15 - 09:45', className: 'Kelas VIII-C', subjectName: 'Informatika Dasar', roomName: 'Lab Komputer 2', jpCount: 2 },
  { id: 'sch-4', day: 'Selasa', period: 6, timeRange: '12:45 - 14:15', className: 'Kelas IX-A', subjectName: 'Praktikum Web', roomName: 'Lab Komputer 1', jpCount: 2 },
  // Rabu (Hari Ini)
  { id: 'sch-5', day: 'Rabu', period: 1, timeRange: '07:30 - 09:00', className: 'Kelas IX-A', subjectName: 'Informatika & Pemrograman', roomName: 'Lab Komputer 1', jpCount: 2 },
  { id: 'sch-6', day: 'Rabu', period: 4, timeRange: '10:00 - 11:30', className: 'Kelas IX-B', subjectName: 'Pemrograman Web & Logika', roomName: 'Lab Multimedia', jpCount: 2 },
  // Kamis
  { id: 'sch-7', day: 'Kamis', period: 2, timeRange: '08:15 - 09:45', className: 'Kelas VIII-C', subjectName: 'Informatika Dasar', roomName: 'Lab Komputer 2', jpCount: 2 },
  { id: 'sch-8', day: 'Kamis', period: 5, timeRange: '10:30 - 12:00', className: 'Kelas IX-B', subjectName: 'Informatika', roomName: 'Lab Komputer 1', jpCount: 2 },
  // Jumat
  { id: 'sch-9', day: 'Jumat', period: 1, timeRange: '07:30 - 09:00', className: 'Kelas IX-A', subjectName: 'Proyek P5 Rekayasa Teknologi', roomName: 'Lab Komputer 1', jpCount: 2 },
];

export default function JadwalMengajarPage() {
  const [selectedDayFilter, setSelectedDayFilter] = useState<string>('ALL');
  const { data: liveSchedules = [] } = useTeacherSchedules();

  const scheduleSlots: ScheduleSlot[] = liveSchedules.length > 0
    ? liveSchedules.map((s, idx) => ({
        id: s.id,
        day: (s.dayName as any) || 'Senin',
        period: idx + 1,
        timeRange: `${s.startTime} - ${s.endTime}`,
        className: s.className,
        subjectName: s.subjectName,
        roomName: s.roomName,
        jpCount: 2,
      }))
    : defaultScheduleSlots;

  const daysOfWeek = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'] as const;
  const totalJP = scheduleSlots.reduce((acc, curr) => acc + curr.jpCount, 0);

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
              Akademik & KBM
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

      {/* ─── 4 SUMMARY KPI CARDS (CLEAN METRICS WITHOUT BORDER-L-4) ─── */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-2.5 sm:gap-3 shrink-0">
        {/* Card 1: Beban Tatap Muka */}
        <div className="bg-white border border-slate-200/90 rounded-2xl p-3.5 shadow-xs flex items-center justify-between gap-3">
          <div>
            <span className="text-[10px] font-black text-slate-500 uppercase tracking-wider block">
              Beban Tatap Muka
            </span>
            <div className="text-xl sm:text-2xl font-black text-slate-900 font-mono mt-0.5">
              {totalJP} <span className="text-xs font-bold text-slate-500 font-sans">JP / Minggu</span>
            </div>
            <p className="text-[10px] text-slate-500 font-semibold mt-0.5">Min. 24 JP Sertifikasi</p>
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
              3 <span className="text-xs font-bold text-slate-500 font-sans">Rombel Aktif</span>
            </div>
            <p className="text-[10px] text-slate-500 font-semibold mt-0.5">Kelas IX-A, IX-B, VIII-C</p>
          </div>
          <div className="w-10 h-10 rounded-xl bg-slate-100 text-slate-700 border border-slate-200 flex items-center justify-center shrink-0 shadow-2xs">
            <GraduationCap className="w-5 h-5" />
          </div>
        </div>

        {/* Card 3: Ruang Praktikum */}
        <div className="bg-white border border-slate-200/90 rounded-2xl p-3.5 shadow-xs flex items-center justify-between gap-3">
          <div>
            <span className="text-[10px] font-black text-slate-500 uppercase tracking-wider block">
              Ruang Praktikum
            </span>
            <div className="text-xl sm:text-2xl font-black text-slate-900 font-mono mt-0.5">
              2 <span className="text-xs font-bold text-slate-500 font-sans">Ruang Lab</span>
            </div>
            <p className="text-[10px] text-slate-500 font-semibold mt-0.5">Lab Komputer 1 & Multimedia</p>
          </div>
          <div className="w-10 h-10 rounded-xl bg-slate-100 text-slate-700 border border-slate-200 flex items-center justify-center shrink-0 shadow-2xs">
            <MapPin className="w-5 h-5" />
          </div>
        </div>

        {/* Card 4: Jadwal Hari Ini */}
        <div className="bg-white border border-slate-200/90 rounded-2xl p-3.5 shadow-xs flex items-center justify-between gap-3">
          <div>
            <span className="text-[10px] font-black text-blue-900 uppercase tracking-wider block">
              Jadwal Hari Ini (Rabu)
            </span>
            <div className="text-xl sm:text-2xl font-black text-slate-900 font-mono mt-0.5">
              2 <span className="text-xs font-bold text-slate-500 font-sans">Sesi Mengajar</span>
            </div>
            <p className="text-[10px] text-slate-500 font-semibold mt-0.5">07:30 - 09:00 & 10:00 - 11:30</p>
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

          {/* Clean Day Filter Tabs (Tanpa Dot Hijau) */}
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

        {/* ─── CONTENT AREA: MINGGUAN (ALL) VS HARIAN (SINGLE DAY) ─── */}
        <div className="flex-1 min-h-0 overflow-y-auto pr-0.5">
          {selectedDayFilter === 'ALL' ? (
            /* Mode 1: Matriks Mingguan 5 Kolom Penuh */
            <div className="grid grid-cols-1 md:grid-cols-5 gap-3 h-full">
              {daysOfWeek.map((day) => {
                const daySlots = scheduleSlots.filter(s => s.day === day);
                const isToday = day === 'Rabu';

                return (
                  <div 
                    key={day} 
                    className={`rounded-2xl border flex flex-col transition-all overflow-hidden ${
                      isToday 
                        ? 'border-blue-900 bg-blue-50/20 ring-2 ring-blue-900/15 shadow-xs' 
                        : 'border-slate-200/90 bg-white shadow-2xs'
                    }`}
                  >
                    {/* Day Column Header (Harmonious Monochromatic Blue/White for Today) */}
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
                          <span className="text-[10px] text-slate-400 mt-0.5">Waktu persiapan ajar & administrasi</span>
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
                                <span>Input Jurnal & Presensi ➔</span>
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
            /* Mode 2: Tampilan Harian (Langsung Tampil Jadwal Saja, Tanpa Header Hari Redundan) */
            <div className="max-w-4xl mx-auto w-full py-2">
              {(() => {
                const daySlots = scheduleSlots.filter(s => s.day === selectedDayFilter);
                const isToday = selectedDayFilter === 'Rabu';

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
                            <span>Input Jurnal & Presensi Sesi ➔</span>
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
