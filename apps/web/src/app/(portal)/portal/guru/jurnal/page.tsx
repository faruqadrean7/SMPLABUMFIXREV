'use client';

import React, { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { 
  CalendarCheck, 
  Clock, 
  MapPin, 
  Users, 
  CheckCircle2, 
  AlertCircle, 
  Plus, 
  History, 
  Search, 
  Download, 
  Save, 
  ChevronRight,
  BookOpen,
  Filter,
  Check,
  TrendingUp,
  FileText,
  XCircle,
  RefreshCw
} from 'lucide-react';
import { toast } from 'sonner';
import { lmsApi } from '@/lib/api-client';
import { useTeacherClasses } from '@/lib/use-teacher-data';
import { AttendanceStatus, StudentRosterItem, TeachingJournal } from '@/types/lms';

export default function JurnalPresensiPage() {
  const queryClient = useQueryClient();
  const [activeTab, setActiveTab] = useState<'input' | 'riwayat'>('input');
  const [selectedScheduleId, setSelectedScheduleId] = useState<string>('');
  const [selectedClassId, setSelectedClassId] = useState<string>('');
  
  // Form input state
  const [meetingNumber, setMeetingNumber] = useState<number>(7);
  const [sessionDate, setSessionDate] = useState<string>(() => new Date().toISOString().slice(0, 10));
  const [topic, setTopic] = useState<string>('Penerapan Konsep Relasi & Aljabar');
  const [method, setMethod] = useState<string>('Problem-Based Learning (PBL) & Diskusi Kelompok');
  const [notes, setNotes] = useState<string>('Kegiatan pembelajaran berjalan kondusif.');
  const [searchStudent, setSearchStudent] = useState<string>('');
  const [successMessage, setSuccessMessage] = useState<string | null>(null);

  // Queries
  const { data: schedules = [], isLoading: isLoadingSchedules } = useQuery({
    queryKey: ['teaching-schedules'],
    queryFn: () => lmsApi.getSchedules(),
  });

  const { data: teacherClasses = [], isLoading: isLoadingClasses } = useTeacherClasses();

  const { data: journals = [], isLoading: isLoadingJournals } = useQuery({
    queryKey: ['teaching-journals'],
    queryFn: () => lmsApi.getJournals(),
  });

  React.useEffect(() => {
    if (!selectedScheduleId && schedules.length > 0) {
      setSelectedScheduleId(schedules[0].id);
    }
  }, [schedules, selectedScheduleId]);

  React.useEffect(() => {
    if (!selectedClassId && teacherClasses.length > 0) {
      setSelectedClassId(teacherClasses[0].id);
    }
  }, [teacherClasses, selectedClassId]);

  // Active selected schedule
  const activeSchedule = schedules.find(s => s.id === selectedScheduleId) || schedules[0];
  const currentClassId = activeSchedule?.class_id || selectedClassId || teacherClasses[0]?.id || '';
  const displayClassName = activeSchedule?.class_name || teacherClasses.find(c => c.id === currentClassId)?.name || '7-A';
  const displaySubjectName = activeSchedule?.subject_name || 'Matematika';

  const { data: initialRoster = [], isLoading: isLoadingRoster } = useQuery({
    queryKey: ['student-roster', currentClassId],
    queryFn: () => lmsApi.getStudentRoster(currentClassId),
    enabled: Boolean(currentClassId),
  });

  // Local student attendance state
  const [roster, setRoster] = useState<StudentRosterItem[]>([]);

  // Sync roster on class or schedule change
  React.useEffect(() => {
    if (initialRoster && initialRoster.length > 0) {
      setRoster(initialRoster);
    }
  }, [initialRoster, selectedScheduleId, currentClassId]);

  // Quick batch actions
  const markAllStatus = (status: AttendanceStatus) => {
    setRoster(prev => prev.map(s => ({ ...s, status })));
    toast.success(`Seluruh ${roster.length} siswa ${displayClassName} berhasil ditandai ${status === 'hadir' ? 'Hadir' : status}!`);
  };

  const resetAllStatus = () => {
    if (initialRoster.length > 0) {
      setRoster(initialRoster);
      toast.info('Status presensi dikembalikan ke catatan awal.');
    }
  };

  const updateStudentStatus = (studentId: string, status: AttendanceStatus) => {
    setRoster(prev => prev.map(s => s.id === studentId ? { ...s, status } : s));
  };

  const updateStudentNote = (studentId: string, note: string) => {
    setRoster(prev => prev.map(s => s.id === studentId ? { ...s, note } : s));
  };

  // Live Attendance Summary Stats
  const totalStudents = roster.length;
  const countHadir = roster.filter(s => s.status === 'hadir').length;
  const countSakit = roster.filter(s => s.status === 'sakit').length;
  const countIzin = roster.filter(s => s.status === 'izin').length;
  const countAlpa = roster.filter(s => s.status === 'alpa').length;
  const countTerlambat = roster.filter(s => s.status === 'terlambat').length;
  const attendanceRate = totalStudents > 0 
    ? Math.round(((countHadir + countTerlambat) / totalStudents) * 100) 
    : 0;

  // Mutation to save journal and attendances
  const saveMutation = useMutation({
    mutationFn: async () => {
      return lmsApi.createJournalWithAttendance({
        schedule_id: selectedScheduleId,
        session_date: sessionDate,
        topic,
        method,
        notes,
        attendances: roster.map(s => ({
          student_id: s.id,
          status: s.status,
          note: s.note,
        }))
      });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['teaching-journals'] });
      setSuccessMessage('Jurnal KBM dan Presensi Sesi Berhasil Disimpan ke Sistem Pusat!');
      toast.success('Jurnal KBM dan presensi berhasil tersimpan.');
      setTimeout(() => {
        setSuccessMessage(null);
        setActiveTab('riwayat');
      }, 1500);
    },
    onError: (err: any) => {
      toast.error('Gagal menyimpan jurnal: ' + (err?.response?.data?.message || err?.message || 'Terjadi kesalahan sistem'));
    }
  });

  const [statusFilter, setStatusFilter] = useState<'all' | AttendanceStatus>('all');

  const filteredRoster = roster.filter(s => {
    const matchesSearch = s.name.toLowerCase().includes(searchStudent.toLowerCase()) || 
      s.nis.includes(searchStudent);
    const matchesStatus = statusFilter === 'all' || s.status === statusFilter;
    return matchesSearch && matchesStatus;
  });

  return (
    <div className="flex flex-col gap-3.5 max-w-full xl:h-[calc(100vh-112px)] xl:overflow-hidden text-slate-900">
      {/* ─── PAGE HEADER (COMPACT & PROPORTIONED) ─── */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-3 bg-white border border-slate-200/90 rounded-2xl p-3.5 sm:p-4 shadow-xs shrink-0">
        <div>
          <div className="flex items-center gap-2 mb-1">
            <span className="text-[11px] font-bold uppercase tracking-wider px-2 py-0.5 rounded bg-blue-900 text-white shadow-2xs">
              Modul KBM & Presensi
            </span>
            <span className="text-xs text-slate-500 font-semibold">• BE-ACD-02 & BE-ACD-03 (P0)</span>
          </div>
          <h1 className="text-xl font-extrabold text-slate-900 tracking-tight">
            Jurnal Mengajar & Presensi Sesi Kelas
          </h1>
          <p className="text-xs text-slate-600 mt-0.5">
            Catat topik ajar, metode pembelajaran, dan verifikasi kehadiran siswa pada sesi tatap muka langsung di kelas atau laboratorium secara terpadu.
          </p>
        </div>

        {/* Tab Switcher (Solid Buttons) */}
        <div className="flex items-center bg-slate-100 p-1 rounded-xl border border-slate-200 shrink-0 self-start md:self-auto">
          <button
            onClick={() => setActiveTab('input')}
            className={`flex items-center gap-2 px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer ${
              activeTab === 'input'
                ? 'bg-blue-900 text-white shadow-xs'
                : 'text-slate-700 hover:text-slate-900'
            }`}
          >
            <Plus className="w-3.5 h-3.5" />
            <span>Input Sesi KBM</span>
          </button>
          <button
            onClick={() => setActiveTab('riwayat')}
            className={`flex items-center gap-2 px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer ${
              activeTab === 'riwayat'
                ? 'bg-blue-900 text-white shadow-xs'
                : 'text-slate-700 hover:text-slate-900'
            }`}
          >
            <History className="w-3.5 h-3.5" />
            <span>Riwayat Jurnal ({journals.length})</span>
          </button>
        </div>
      </div>

      {/* ─── SUCCESS ALERT NOTIFICATION ─── */}
      {successMessage && (
        <div className="bg-emerald-800 text-white p-3.5 rounded-xl flex items-center justify-between shadow-xs animate-in fade-in shrink-0">
          <div className="flex items-center gap-3">
            <CheckCircle2 className="w-5 h-5 text-emerald-200 shrink-0" />
            <span className="text-xs font-bold">{successMessage}</span>
          </div>
        </div>
      )}

      {/* ─── TAB 1: FORM INPUT JURNAL & PRESENSI KBM (2-COLUMN DESKTOP SPLIT) ─── */}
      {activeTab === 'input' && (
        <div className="xl:flex-1 xl:min-h-0 grid grid-cols-1 xl:grid-cols-12 gap-3.5 overflow-y-auto xl:overflow-hidden">
          {/* ─── KOLOM KIRI: FORM SESI & MATERI PEMBELAJARAN (4 COLS - FULL HEIGHT ALIGNED) ─── */}
          <div className="xl:col-span-4 bg-white border border-slate-200/90 rounded-2xl p-4 shadow-xs flex flex-col h-full min-h-0">
            {/* Header */}
            <div className="flex items-center justify-between pb-3 border-b border-slate-100 shrink-0">
              <div className="flex items-center gap-2.5">
                <div className="w-7 h-7 rounded-lg bg-blue-50 text-blue-900 border border-blue-200 flex items-center justify-center shrink-0">
                  <CalendarCheck className="w-4 h-4" />
                </div>
                <div>
                  <h2 className="text-xs font-extrabold text-slate-900 uppercase tracking-wider">
                    1. Informasi Jadwal & Sesi Pembelajaran
                  </h2>
                  <p className="text-[11px] text-slate-500 font-normal">
                    Pilih sesi mengajar aktif & lengkapi data pertemuan
                  </p>
                </div>
              </div>
              <span className="px-2 py-0.5 rounded-md text-[10px] font-bold bg-blue-900 text-white shadow-2xs">
                Tatap Muka
              </span>
            </div>

            {/* Scrollable / Stretchable Body */}
            <div className="flex-1 min-h-0 overflow-y-auto space-y-3.5 pt-3 pr-1">
              {/* Schedule Selector - Clean Stack with full details on one line */}
              <div>
                <label className="text-xs font-bold text-slate-700 mb-2 flex items-center justify-between">
                  <span>Pilih Jadwal Mengajar Hari Ini:</span>
                  <span className="text-[11px] text-slate-500 font-normal">Klik untuk memilih kelas</span>
                </label>
                <div className="space-y-2">
                  {isLoadingSchedules ? (
                    <div className="p-3.5 rounded-xl border border-slate-200 bg-slate-50 flex items-center justify-center gap-2 text-xs text-slate-500">
                      <RefreshCw className="w-4 h-4 animate-spin text-blue-900" />
                      <span className="font-semibold">Memuat jadwal mengajar aktif...</span>
                    </div>
                  ) : schedules.length > 0 ? (
                    schedules.map((sch) => {
                      const isSelected = sch.id === selectedScheduleId;
                      return (
                        <button
                          key={sch.id}
                          type="button"
                          onClick={() => {
                            setSelectedScheduleId(sch.id);
                            if (sch.class_id) setSelectedClassId(sch.class_id);
                          }}
                          className={`w-full text-left p-2.5 rounded-xl border text-xs transition-all cursor-pointer flex items-center justify-between gap-3 ${
                            isSelected
                              ? 'border-blue-900 bg-blue-50/60 ring-2 ring-blue-900/15 border-l-4 border-l-blue-900 shadow-2xs'
                              : 'border-slate-200/90 bg-white hover:bg-slate-50 border-l-4 border-l-slate-300'
                          }`}
                        >
                          <div className="flex items-center gap-3 min-w-0">
                            <div className={`w-9 h-9 rounded-lg flex flex-col items-center justify-center text-xs font-bold shrink-0 ${
                              isSelected ? 'bg-blue-900 text-white shadow-2xs' : 'bg-slate-100 text-slate-700 border border-slate-200'
                            }`}>
                              <span className="text-[10px] uppercase font-mono">{sch.day_name.slice(0, 3)}</span>
                            </div>
                            <div className="min-w-0">
                              <div className="flex items-center gap-2">
                                <span className="font-extrabold text-slate-900 text-xs truncate">
                                  Kelas {sch.class_name}
                                </span>
                                <span className="text-slate-400">•</span>
                                <span className="font-semibold text-slate-700 truncate text-[11px]">
                                  {sch.subject_name}
                                </span>
                              </div>
                              <div className="flex items-center gap-2 text-[11px] text-slate-500 font-medium mt-0.5">
                                <span className="flex items-center gap-1 font-mono">
                                  <Clock className="w-3 h-3 text-slate-400" /> {sch.start_time} - {sch.end_time}
                                </span>
                                <span>•</span>
                                <span className="flex items-center gap-1 text-slate-600">
                                  <MapPin className="w-3 h-3 text-slate-400" /> {sch.room_name}
                                </span>
                              </div>
                            </div>
                          </div>

                          <span className={`px-2 py-0.5 rounded text-[10px] font-bold shrink-0 shadow-2xs ${
                            isSelected ? 'bg-blue-900 text-white' : 'bg-slate-100 text-slate-600 border border-slate-200'
                          }`}>
                            {isSelected ? 'Terpilih' : 'Pilih'}
                          </span>
                        </button>
                      );
                    })
                  ) : teacherClasses.length > 0 ? (
                    <div className="space-y-2">
                      <div className="text-[11px] text-slate-500 bg-amber-50 border border-amber-200 p-2 rounded-lg">
                        Tidak ada jadwal otomatis hari ini. Silakan pilih kelas ampu Anda:
                      </div>
                      {teacherClasses.map((cls) => {
                        const isSelected = cls.id === currentClassId;
                        return (
                          <button
                            key={cls.id}
                            type="button"
                            onClick={() => setSelectedClassId(cls.id)}
                            className={`w-full text-left p-2.5 rounded-xl border text-xs transition-all cursor-pointer flex items-center justify-between gap-3 ${
                              isSelected
                                ? 'border-blue-900 bg-blue-50/60 ring-2 ring-blue-900/15 border-l-4 border-l-blue-900 shadow-2xs'
                                : 'border-slate-200/90 bg-white hover:bg-slate-50 border-l-4 border-l-slate-300'
                            }`}
                          >
                            <div className="flex items-center gap-3 min-w-0">
                              <div className={`w-9 h-9 rounded-lg flex flex-col items-center justify-center text-xs font-bold shrink-0 ${
                                isSelected ? 'bg-blue-900 text-white shadow-2xs' : 'bg-slate-100 text-slate-700 border border-slate-200'
                              }`}>
                                <span className="text-[10px] uppercase font-mono">KBM</span>
                              </div>
                              <div className="min-w-0">
                                <div className="flex items-center gap-2">
                                  <span className="font-extrabold text-slate-900 text-xs truncate">
                                    Kelas {cls.name}
                                  </span>
                                  <span className="text-slate-400">•</span>
                                  <span className="font-semibold text-slate-700 truncate text-[11px]">
                                    {cls.studentCount} Siswa
                                  </span>
                                </div>
                                <div className="text-[11px] text-slate-500 font-medium mt-0.5">
                                  Sesi Tatap Muka Reguler
                                </div>
                              </div>
                            </div>
                            <span className={`px-2 py-0.5 rounded text-[10px] font-bold shrink-0 shadow-2xs ${
                              isSelected ? 'bg-blue-900 text-white' : 'bg-slate-100 text-slate-600 border border-slate-200'
                            }`}>
                              {isSelected ? 'Terpilih' : 'Pilih'}
                            </span>
                          </button>
                        );
                      })}
                    </div>
                  ) : (
                    <div className="p-4 rounded-xl border border-dashed border-slate-300 bg-slate-50 text-center text-xs text-slate-500">
                      Belum ada jadwal mengajar atau kelas yang ditugaskan.
                    </div>
                  )}
                </div>
              </div>

              {/* Form Input Sesi - Clean 2-column & full-width layout */}
              <div className="space-y-3 pt-1 border-t border-slate-100">
                <div className="grid grid-cols-2 gap-3">
                  <div>
                    <label className="block text-xs font-bold text-slate-700 mb-1">
                      Pertemuan Ke-
                    </label>
                    <input
                      type="number"
                      min={1}
                      max={24}
                      value={meetingNumber}
                      onChange={(e) => setMeetingNumber(Number(e.target.value))}
                      className="w-full px-3 py-2 text-xs font-bold bg-white border border-slate-300 rounded-lg focus:outline-none focus:border-blue-900"
                    />
                  </div>

                  <div>
                    <label className="block text-xs font-bold text-slate-700 mb-1">
                      Tanggal Tatap Muka
                    </label>
                    <input
                      type="date"
                      value={sessionDate}
                      onChange={(e) => setSessionDate(e.target.value)}
                      className="w-full px-3 py-2 text-xs font-bold bg-white border border-slate-300 rounded-lg focus:outline-none focus:border-blue-900"
                    />
                  </div>
                </div>

                <div>
                  <label className="block text-xs font-bold text-slate-700 mb-1">
                    Metode Pembelajaran
                  </label>
                  <select
                    value={method}
                    onChange={(e) => setMethod(e.target.value)}
                    className="w-full px-3 py-2 text-xs font-bold bg-white border border-slate-300 rounded-lg focus:outline-none focus:border-blue-900"
                  >
                    <option value="Problem-Based Learning (PBL) & Praktikum Mandiri">Problem-Based Learning (PBL) & Praktikum Mandiri</option>
                    <option value="Project-Based Learning (PjBL) Kolaboratif">Project-Based Learning (PjBL) Kolaboratif</option>
                    <option value="Demonstrasi Live Coding & Latihan Mandiri">Demonstrasi Live Coding & Latihan Mandiri</option>
                    <option value="Diskusi Kelompok & Paparan Presentasi">Diskusi Kelompok & Paparan Presentasi</option>
                    <option value="Ceramah Interaktif & Asesmen Formatif Singkat">Ceramah Interaktif & Asesmen Formatif Singkat</option>
                  </select>
                </div>

                <div>
                  <label className="block text-xs font-bold text-slate-700 mb-1">
                    Materi Pokok / Capaian Pembelajaran (TP)
                  </label>
                  <input
                    type="text"
                    placeholder="Contoh: Pembuatan Layout Responsif 12 Kolom Menggunakan CSS Grid..."
                    value={topic}
                    onChange={(e) => setTopic(e.target.value)}
                    className="w-full px-3 py-2 text-xs font-semibold bg-white border border-slate-300 rounded-lg focus:outline-none focus:border-blue-900"
                  />
                </div>

                <div>
                  <label className="block text-xs font-bold text-slate-700 mb-1">
                    Catatan Kendala / Progres KBM (Opsional)
                  </label>
                  <textarea
                    rows={2}
                    placeholder="Catatan khusus mengenai pemahaman siswa, kendala perangkat laboratorium, atau tindak lanjut..."
                    value={notes}
                    onChange={(e) => setNotes(e.target.value)}
                    className="w-full px-3 py-2 text-xs bg-white border border-slate-300 rounded-lg focus:outline-none focus:border-blue-900"
                  />
                </div>
              </div>
            </div>

            {/* Submit CTA Pinned at the Bottom */}
            <div className="pt-3 border-t border-slate-100 shrink-0 mt-auto">
              <button
                type="button"
                disabled={saveMutation.isPending}
                onClick={() => saveMutation.mutate()}
                className="w-full py-2.5 rounded-xl text-xs font-black bg-blue-900 hover:bg-blue-950 text-white flex items-center justify-center gap-2 shadow-xs transition-all cursor-pointer disabled:opacity-50"
              >
                <Save className="w-4 h-4" />
                <span>
                  {saveMutation.isPending ? 'Menyimpan ke Database...' : 'Simpan Jurnal & Presensi Sesi KBM'}
                </span>
              </button>
              <p className="text-[11px] text-slate-500 text-center mt-1">
                ✓ Presensi otomatis tersinkron ke buku nilai dan portal orang tua
              </p>
            </div>
          </div>

          {/* ─── KOLOM KANAN: PRESENSI SISWA SESI TERPADU (8 COLS) ─── */}
          <div className="xl:col-span-8 bg-white border border-slate-200/90 rounded-2xl p-4 shadow-xs flex flex-col h-full min-h-0">
            {/* Header Column */}
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-2 border-b border-slate-100 shrink-0">
              <div className="flex items-center gap-2">
                <Users className="w-4 h-4 text-emerald-800" />
                <h2 className="text-xs font-extrabold text-slate-900 uppercase tracking-wider">
                  2. Lembar Presensi Sesi ({isLoadingRoster ? '...' : roster.length} Siswa Terdaftar)
                </h2>
                <span className="text-[11px] text-slate-500 font-medium">
                  • Kelas {displayClassName} ({displaySubjectName})
                </span>
              </div>

              <div className="flex items-center gap-2">
                <button
                  type="button"
                  id="btn-mark-all-hadir"
                  onClick={() => markAllStatus('hadir')}
                  className="px-3 py-1.5 rounded-lg text-xs font-extrabold bg-emerald-800 hover:bg-emerald-900 text-white flex items-center gap-1.5 shadow-2xs cursor-pointer transition-transform active:scale-95"
                >
                  <Check className="w-3.5 h-3.5" />
                  <span>Tandai Semua Hadir</span>
                </button>
                <button
                  type="button"
                  id="btn-reset-attendance"
                  onClick={resetAllStatus}
                  className="px-2.5 py-1.5 rounded-lg text-xs font-bold bg-slate-100 hover:bg-slate-200 text-slate-700 flex items-center gap-1 border border-slate-200 cursor-pointer"
                >
                  <RefreshCw className="w-3 h-3" />
                  <span>Reset</span>
                </button>
              </div>
            </div>

            {/* Quick KPI Stats Bar - 7 Rich Modern SaaS Metric Cards (Including Terlambat) */}
            <div className="grid grid-cols-2 sm:grid-cols-4 xl:grid-cols-7 gap-2 my-2.5 shrink-0">
              {/* Total Card */}
              <div className="relative overflow-hidden rounded-xl p-2.5 text-left bg-linear-to-br from-slate-900 to-slate-950 border border-slate-800 text-white shadow-xs">
                <Users className="absolute -right-2 -bottom-2 w-12 h-12 text-white/10 pointer-events-none" />
                <div className="flex items-center justify-between relative z-10">
                  <span className="text-[10px] font-black uppercase tracking-wider text-slate-300">Total</span>
                  <div className="w-5 h-5 rounded-md bg-white/15 backdrop-blur-xs flex items-center justify-center text-white shadow-2xs">
                    <Users className="w-3 h-3" />
                  </div>
                </div>
                <div className="mt-1 relative z-10 flex items-baseline justify-between">
                  <span className="text-xl sm:text-2xl font-black font-mono leading-none tracking-tight text-white">{totalStudents}</span>
                  <span className="text-[9px] font-bold text-slate-300 bg-white/10 px-1.5 py-0.5 rounded shadow-2xs">Roster</span>
                </div>
              </div>

              {/* Hadir Card */}
              <div className="relative overflow-hidden rounded-xl p-2.5 text-left bg-linear-to-br from-emerald-800 to-emerald-950 border border-emerald-700/80 text-white shadow-xs">
                <CheckCircle2 className="absolute -right-2 -bottom-2 w-12 h-12 text-white/10 pointer-events-none" />
                <div className="flex items-center justify-between relative z-10">
                  <span className="text-[10px] font-black uppercase tracking-wider text-emerald-100">Hadir</span>
                  <div className="w-5 h-5 rounded-md bg-white/15 backdrop-blur-xs flex items-center justify-center text-white shadow-2xs">
                    <CheckCircle2 className="w-3 h-3" />
                  </div>
                </div>
                <div className="mt-1 relative z-10 flex items-baseline justify-between">
                  <span className="text-xl sm:text-2xl font-black font-mono leading-none tracking-tight text-white">{countHadir}</span>
                  <span className="text-[9px] font-bold text-emerald-100 bg-black/25 px-1.5 py-0.5 rounded shadow-2xs">
                    {totalStudents > 0 ? Math.round((countHadir / totalStudents) * 100) : 0}%
                  </span>
                </div>
              </div>

              {/* Terlambat Card */}
              <div className="relative overflow-hidden rounded-xl p-2.5 text-left bg-linear-to-br from-orange-600 to-orange-950 border border-orange-500/80 text-white shadow-xs">
                <Clock className="absolute -right-2 -bottom-2 w-12 h-12 text-white/10 pointer-events-none" />
                <div className="flex items-center justify-between relative z-10">
                  <span className="text-[10px] font-black uppercase tracking-wider text-orange-100">Terlambat</span>
                  <div className="w-5 h-5 rounded-md bg-white/15 backdrop-blur-xs flex items-center justify-center text-white shadow-2xs">
                    <Clock className="w-3 h-3" />
                  </div>
                </div>
                <div className="mt-1 relative z-10 flex items-baseline justify-between">
                  <span className="text-xl sm:text-2xl font-black font-mono leading-none tracking-tight text-white">{countTerlambat}</span>
                  <span className="text-[9px] font-bold text-orange-100 bg-black/25 px-1.5 py-0.5 rounded shadow-2xs">Dispensasi</span>
                </div>
              </div>

              {/* Sakit Card */}
              <div className="relative overflow-hidden rounded-xl p-2.5 text-left bg-linear-to-br from-amber-600 to-amber-900 border border-amber-500/80 text-white shadow-xs">
                <AlertCircle className="absolute -right-2 -bottom-2 w-12 h-12 text-white/10 pointer-events-none" />
                <div className="flex items-center justify-between relative z-10">
                  <span className="text-[10px] font-black uppercase tracking-wider text-amber-100">Sakit</span>
                  <div className="w-5 h-5 rounded-md bg-white/15 backdrop-blur-xs flex items-center justify-center text-white shadow-2xs">
                    <AlertCircle className="w-3 h-3" />
                  </div>
                </div>
                <div className="mt-1 relative z-10 flex items-baseline justify-between">
                  <span className="text-xl sm:text-2xl font-black font-mono leading-none tracking-tight text-white">{countSakit}</span>
                  <span className="text-[9px] font-bold text-amber-100 bg-black/25 px-1.5 py-0.5 rounded shadow-2xs">Medis</span>
                </div>
              </div>

              {/* Izin Card */}
              <div className="relative overflow-hidden rounded-xl p-2.5 text-left bg-linear-to-br from-blue-800 to-blue-950 border border-blue-700/80 text-white shadow-xs">
                <FileText className="absolute -right-2 -bottom-2 w-12 h-12 text-white/10 pointer-events-none" />
                <div className="flex items-center justify-between relative z-10">
                  <span className="text-[10px] font-black uppercase tracking-wider text-blue-100">Izin</span>
                  <div className="w-5 h-5 rounded-md bg-white/15 backdrop-blur-xs flex items-center justify-center text-white shadow-2xs">
                    <FileText className="w-3 h-3" />
                  </div>
                </div>
                <div className="mt-1 relative z-10 flex items-baseline justify-between">
                  <span className="text-xl sm:text-2xl font-black font-mono leading-none tracking-tight text-white">{countIzin}</span>
                  <span className="text-[9px] font-bold text-blue-100 bg-black/25 px-1.5 py-0.5 rounded shadow-2xs">Surat</span>
                </div>
              </div>

              {/* Alpa Card */}
              <div className="relative overflow-hidden rounded-xl p-2.5 text-left bg-linear-to-br from-rose-800 to-rose-950 border border-rose-700/80 text-white shadow-xs">
                <XCircle className="absolute -right-2 -bottom-2 w-12 h-12 text-white/10 pointer-events-none" />
                <div className="flex items-center justify-between relative z-10">
                  <span className="text-[10px] font-black uppercase tracking-wider text-rose-100">Alpa</span>
                  <div className="w-5 h-5 rounded-md bg-white/15 backdrop-blur-xs flex items-center justify-center text-white shadow-2xs">
                    <XCircle className="w-3 h-3" />
                  </div>
                </div>
                <div className="mt-1 relative z-10 flex items-baseline justify-between">
                  <span className="text-xl sm:text-2xl font-black font-mono leading-none tracking-tight text-white">{countAlpa}</span>
                  <span className="text-[9px] font-bold text-rose-100 bg-black/25 px-1.5 py-0.5 rounded shadow-2xs">Tanpa Ket.</span>
                </div>
              </div>

              {/* Rate Card */}
              <div className="relative overflow-hidden rounded-xl p-2.5 text-left bg-linear-to-br from-teal-800 to-teal-950 border border-teal-700/80 text-white shadow-xs">
                <TrendingUp className="absolute -right-2 -bottom-2 w-12 h-12 text-white/10 pointer-events-none" />
                <div className="flex items-center justify-between relative z-10">
                  <span className="text-[10px] font-black uppercase tracking-wider text-teal-100">Rate</span>
                  <div className="w-5 h-5 rounded-md bg-white/15 backdrop-blur-xs flex items-center justify-center text-white shadow-2xs">
                    <TrendingUp className="w-3 h-3" />
                  </div>
                </div>
                <div className="mt-1 relative z-10 flex items-baseline justify-between">
                  <span className="text-xl sm:text-2xl font-black font-mono leading-none tracking-tight text-white">{attendanceRate}%</span>
                  <span className="text-[9px] font-bold text-teal-100 bg-black/25 px-1.5 py-0.5 rounded shadow-2xs">≥90% Target</span>
                </div>
              </div>
            </div>

            {/* Search & Filter Bar (Side by Side) */}
            <div className="flex items-center gap-2 mb-2 shrink-0">
              <div className="relative flex-1">
                <Search className="w-3.5 h-3.5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                <input
                  type="text"
                  placeholder="Cari siswa berdasarkan nama atau NIS..."
                  value={searchStudent}
                  onChange={(e) => setSearchStudent(e.target.value)}
                  className="w-full pl-8 pr-3 py-1.5 text-xs bg-white border border-slate-200 rounded-lg focus:outline-none focus:border-blue-900 font-medium"
                />
              </div>

              {/* Filter Status Kehadiran Dropdown */}
              <div className="relative shrink-0">
                <Filter className="w-3.5 h-3.5 text-slate-500 absolute left-2.5 top-1/2 -translate-y-1/2 pointer-events-none" />
                <select
                  value={statusFilter}
                  onChange={(e) => setStatusFilter(e.target.value as any)}
                  className="pl-7 pr-7 py-1.5 text-xs font-bold bg-white border border-slate-200 rounded-lg focus:outline-none focus:border-blue-900 text-slate-700 cursor-pointer shadow-2xs appearance-none hover:bg-slate-50 transition-colors"
                >
                  <option value="all">Semua Status ({roster.length})</option>
                  <option value="hadir">Hadir Saja ({countHadir})</option>
                  <option value="sakit">Sakit Saja ({countSakit})</option>
                  <option value="izin">Izin Saja ({countIzin})</option>
                  <option value="alpa">Alpa Saja ({countAlpa})</option>
                  <option value="terlambat">Terlambat Saja ({countTerlambat})</option>
                </select>
                <ChevronRight className="w-3.5 h-3.5 text-slate-400 absolute right-2 top-1/2 -translate-y-1/2 rotate-90 pointer-events-none" />
              </div>
            </div>

            {/* Scrollable Table with Sticky Header */}
            <div className="flex-1 min-h-0 border border-slate-200/90 rounded-xl overflow-y-auto bg-white shadow-2xs">
              <table className="w-full text-left text-xs border-collapse">
                <thead className="sticky top-0 bg-slate-100 z-10 border-b border-slate-200 shadow-2xs">
                  <tr className="text-slate-700 font-extrabold uppercase text-[10px] tracking-wider">
                    <th className="py-3 px-3.5 w-12 text-center bg-slate-100">No</th>
                    <th className="py-3 px-3 w-24 bg-slate-100">NIS</th>
                    <th className="py-3 px-3 min-w-50 bg-slate-100">Nama Lengkap Siswa</th>
                    <th className="py-3 px-3 w-16 text-center bg-slate-100">L/P</th>
                    <th className="py-3 px-3 w-80 text-center bg-slate-100">Status Kehadiran Sesi</th>
                    <th className="py-3 px-3 min-w-56 bg-slate-100">Keterangan / Catatan Izin</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-200">
                  {isLoadingRoster ? (
                    <tr>
                      <td colSpan={6} className="py-12 text-center">
                        <div className="flex flex-col items-center justify-center gap-2">
                          <RefreshCw className="w-6 h-6 text-blue-900 animate-spin" />
                          <span className="text-xs font-bold text-slate-700">Memuat data presensi siswa kelas...</span>
                          <span className="text-[11px] text-slate-500">Menghubungkan ke server akademik</span>
                        </div>
                      </td>
                    </tr>
                  ) : filteredRoster.length === 0 ? (
                    <tr>
                      <td colSpan={6} className="py-12 text-center">
                        <div className="flex flex-col items-center justify-center gap-2">
                          <Users className="w-8 h-8 text-slate-300" />
                          <span className="text-xs font-bold text-slate-700">Tidak ada siswa ditemukan</span>
                          <span className="text-[11px] text-slate-500">
                            {searchStudent ? 'Tidak ada siswa yang sesuai kata kunci pencarian.' : 'Belum ada siswa terdaftar di rombel kelas ini.'}
                          </span>
                        </div>
                      </td>
                    </tr>
                  ) : (
                    filteredRoster.map((student, idx) => {
                      return (
                        <tr key={student.id} className="hover:bg-slate-50/80 transition-colors">
                          <td className="py-3 px-3.5 text-center font-bold text-slate-500">
                            {idx + 1}
                          </td>
                          <td className="py-3 px-3 font-mono font-semibold text-slate-700">
                            {student.nis}
                          </td>
                          <td className="py-3 px-3 font-bold text-slate-900">
                            <div className="flex items-center gap-2.5">
                              {student.avatar_url ? (
                                <img
                                  src={student.avatar_url}
                                  alt={student.name}
                                  className="w-7 h-7 rounded-full object-cover border border-slate-200 shrink-0 shadow-2xs"
                                />
                              ) : (
                                <div className="w-7 h-7 rounded-full bg-slate-200 text-slate-700 text-[10px] font-bold flex items-center justify-center shrink-0">
                                  {student.name.slice(0, 2).toUpperCase()}
                                </div>
                              )}
                              <span className="truncate">{student.name}</span>
                            </div>
                          </td>
                          <td className="py-3 px-3 text-center">
                            <span className={`text-[10px] font-black px-1.5 py-0.5 rounded ${
                              student.gender === 'L' ? 'bg-slate-200 text-slate-800' : 'bg-slate-300 text-slate-900'
                            }`}>
                              {student.gender}
                            </span>
                          </td>
                          <td className="py-3 px-3">
                            <div className="flex items-center justify-center gap-1.5">
                              {/* Hadir Button */}
                              <button
                                type="button"
                                onClick={() => updateStudentStatus(student.id, 'hadir')}
                                className={`px-3 py-1 rounded text-[11px] font-black transition-all cursor-pointer ${
                                  student.status === 'hadir'
                                    ? 'bg-emerald-800 text-white shadow-xs'
                                    : 'bg-white border border-slate-300 text-slate-700 hover:bg-slate-100'
                                }`}
                              >
                                H
                              </button>

                              {/* Sakit Button */}
                              <button
                                type="button"
                                onClick={() => updateStudentStatus(student.id, 'sakit')}
                                className={`px-3 py-1 rounded text-[11px] font-black transition-all cursor-pointer ${
                                  student.status === 'sakit'
                                    ? 'bg-amber-600 text-white shadow-xs'
                                    : 'bg-white border border-slate-300 text-slate-700 hover:bg-slate-100'
                                }`}
                              >
                                S
                              </button>

                              {/* Izin Button */}
                              <button
                                type="button"
                                onClick={() => updateStudentStatus(student.id, 'izin')}
                                className={`px-3 py-1 rounded text-[11px] font-black transition-all cursor-pointer ${
                                  student.status === 'izin'
                                    ? 'bg-blue-800 text-white shadow-xs'
                                    : 'bg-white border border-slate-300 text-slate-700 hover:bg-slate-100'
                                }`}
                              >
                                I
                              </button>

                              {/* Alpa Button */}
                              <button
                                type="button"
                                onClick={() => updateStudentStatus(student.id, 'alpa')}
                                className={`px-3 py-1 rounded text-[11px] font-black transition-all cursor-pointer ${
                                  student.status === 'alpa'
                                    ? 'bg-rose-700 text-white shadow-xs'
                                    : 'bg-white border border-slate-300 text-slate-700 hover:bg-slate-100'
                                }`}
                              >
                                A
                              </button>

                              {/* Terlambat Button */}
                              <button
                                type="button"
                                onClick={() => updateStudentStatus(student.id, 'terlambat')}
                                className={`px-3 py-1 rounded text-[11px] font-black transition-all cursor-pointer ${
                                  student.status === 'terlambat'
                                    ? 'bg-orange-600 text-white shadow-xs'
                                    : 'bg-white border border-slate-300 text-slate-700 hover:bg-slate-100'
                                }`}
                              >
                                T
                              </button>
                            </div>
                          </td>
                          <td className="py-2.5 px-3">
                            <input
                              type="text"
                              placeholder="Keterangan dispensasi/surat..."
                              value={student.note || ''}
                              onChange={(e) => updateStudentNote(student.id, e.target.value)}
                              className="w-full px-2.5 py-1 text-xs bg-white border border-slate-300 rounded focus:border-blue-900 focus:outline-none"
                            />
                          </td>
                        </tr>
                      );
                    })
                  )}
                </tbody>
              </table>
            </div>

            {/* Table Footer Summary */}
            <div className="flex items-center justify-between pt-2.5 border-t border-slate-100 shrink-0 text-slate-500 text-xs">
              <span className="flex items-center gap-1.5 font-medium">
                <AlertCircle className="w-3.5 h-3.5 text-blue-900" />
                Data presensi otomatis tersinkronisasi ke buku nilai dan portal orang tua
              </span>
              <span className="font-semibold text-slate-700">
                {countHadir}/{totalStudents} Siswa Hadir ({attendanceRate}%)
              </span>
            </div>
          </div>
        </div>
      )}

      {/* ─── TAB 2: RIWAYAT JURNAL & REKAP KBM (COMPACT DESKTOP CONTAINER) ─── */}
      {activeTab === 'riwayat' && (
        <div className="xl:flex-1 xl:min-h-0 bg-white border border-slate-200/90 rounded-2xl p-4 shadow-xs flex flex-col h-full overflow-hidden">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-100 shrink-0">
            <div>
              <h2 className="text-xs font-extrabold text-slate-900 uppercase tracking-wider">
                Arsip Pertemuan KBM Selesai ({journals.length} Sesi Terdata)
              </h2>
              <p className="text-xs text-slate-500 font-normal mt-0.5">
                Riwayat jurnal mengajar dan log kehadiran per pertemuan semester berjalan.
              </p>
            </div>

            <button
              type="button"
              onClick={() => {
                const csvContent = "data:text/csv;charset=utf-8," + 
                  "Pertemuan,Tanggal,Kelas,Mapel,Topik,Metode,Hadir,Sakit,Izin,Alpa,Terlambat,Persentase\n" +
                  journals.map(j => `"${j.meeting_number}","${j.session_date}","${j.class?.name || 'IX-A'}","${j.subject?.name || 'Informatika'}","${j.topic}","${j.method}",${j.attendance_summary.hadir},${j.attendance_summary.sakit},${j.attendance_summary.izin},${j.attendance_summary.alpa},${j.attendance_summary.terlambat},"${j.attendance_summary.attendance_rate}%"`).join("\n");
                const encodedUri = encodeURI(csvContent);
                const link = document.createElement("a");
                link.setAttribute("href", encodedUri);
                link.setAttribute("download", `Jurnal_KBM_Informatika_Kelas_IXA.csv`);
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
              }}
              className="px-3.5 py-1.5 rounded-lg text-xs font-bold border border-slate-300 hover:bg-slate-100 text-slate-700 flex items-center gap-1.5 cursor-pointer shadow-xs self-start sm:self-auto"
            >
              <Download className="w-3.5 h-3.5" />
              <span>Ekspor CSV</span>
            </button>
          </div>

          {/* List Riwayat Jurnal as Distinct Scrollable Cards */}
          <div className="flex-1 min-h-0 overflow-y-auto space-y-3 pr-1 mt-3">
            {journals.map((journal) => (
              <div 
                key={journal.id} 
                className="p-3.5 rounded-xl border border-slate-200/90 bg-white shadow-xs border-l-4 border-l-blue-900 space-y-2.5"
              >
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-2">
                  <div className="flex items-center gap-3">
                    <span className="w-8 h-8 rounded-lg bg-blue-900 text-white font-black text-xs flex items-center justify-center shrink-0 shadow-xs">
                      #{journal.meeting_number}
                    </span>
                    <div>
                      <h3 className="font-extrabold text-sm text-slate-900 leading-tight">
                        {journal.topic}
                      </h3>
                      <div className="flex flex-wrap items-center gap-2 text-xs text-slate-500 font-medium mt-0.5">
                        <span className="font-bold text-slate-700">{journal.class?.name || 'Kelas IX-A'}</span>
                        <span>•</span>
                        <span>{journal.session_date}</span>
                        <span>•</span>
                        <span>{journal.time_range}</span>
                        <span>•</span>
                        <span className="text-slate-600">{journal.room_name}</span>
                      </div>
                    </div>
                  </div>

                  {/* Attendance Pill Group */}
                  <div className="flex items-center gap-1.5 shrink-0">
                    <span className="px-2 py-0.5 rounded text-[11px] font-bold bg-emerald-800 text-white shadow-xs">
                      H: {journal.attendance_summary.hadir}
                    </span>
                    {journal.attendance_summary.sakit > 0 && (
                      <span className="px-2 py-0.5 rounded text-[11px] font-bold bg-amber-600 text-white shadow-xs">
                        S: {journal.attendance_summary.sakit}
                      </span>
                    )}
                    {journal.attendance_summary.izin > 0 && (
                      <span className="px-2 py-0.5 rounded text-[11px] font-bold bg-blue-800 text-white shadow-xs">
                        I: {journal.attendance_summary.izin}
                      </span>
                    )}
                    {journal.attendance_summary.alpa > 0 && (
                      <span className="px-2 py-0.5 rounded text-[11px] font-bold bg-rose-700 text-white shadow-xs">
                        A: {journal.attendance_summary.alpa}
                      </span>
                    )}
                    {journal.attendance_summary.terlambat > 0 && (
                      <span className="px-2 py-0.5 rounded text-[11px] font-bold bg-orange-600 text-white shadow-xs">
                        T: {journal.attendance_summary.terlambat}
                      </span>
                    )}
                    <span className="px-2.5 py-0.5 rounded text-[11px] font-black bg-slate-900 text-white ml-1 shadow-xs">
                      {journal.attendance_summary.attendance_rate}%
                    </span>
                  </div>
                </div>

                <div className="bg-slate-50 border border-slate-200/90 rounded-lg p-2.5 text-xs space-y-1">
                  <div>
                    <span className="font-bold text-slate-700">Metode KBM: </span>
                    <span className="text-slate-600">{journal.method}</span>
                  </div>
                  {journal.notes && (
                    <div>
                      <span className="font-bold text-slate-700">Catatan Guru: </span>
                      <span className="text-slate-600">{journal.notes}</span>
                    </div>
                  )}
                </div>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}
