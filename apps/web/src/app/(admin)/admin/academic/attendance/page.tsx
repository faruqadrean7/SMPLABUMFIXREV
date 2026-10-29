'use client';

import React, { useState, useMemo } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { academicApi } from '@/features/academic/academic-api';
import { classesApi } from '@/features/master/classes-api';
import { teachersApi } from '@/features/master/teachers-api';
import { subjectsApi } from '@/features/master/subjects-api';
import { SessionAttendanceDialog, SessionAttendanceDetailDialog } from '@/features/academic/attendance';
import {
  AttendanceItem,
  AttendanceStatus,
  AttendanceSource,
  ATTENDANCE_STATUS_LABELS,
  ATTENDANCE_STATUS_COLORS,
  JournalItem,
} from '@/types/academic';
import { SchoolClassItem, TeacherItem, SubjectItem } from '@/types/master';
import { DataTable, ColumnDef } from '@/components/shared/data-table';
import { PermissionGate } from '@/components/shared/permission-gate';
import { useAuth } from '@/providers/auth-provider';
import { hasPermission } from '@/lib/rbac/evaluator';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { toast } from '@/components/ui/toast';
import {
  UserCheck,
  Calendar,
  Layers,
  BookOpen,
  User,
  Clock,
  Eye,
  Edit,
  CheckCircle2,
  AlertTriangle,
  RotateCw,
  Search,
  Users,
  Check,
  XCircle,
  Clock4,
  Building,
  BarChart3,
  Filter,
} from 'lucide-react';

export default function AcademicAttendancePage() {
  const queryClient = useQueryClient();
  const { user } = useAuth();

  // Role and permissions
  const isAdmin = useMemo(() => {
    if (!user) return false;
    return hasPermission(user, 'academic.schedule.manage');
  }, [user]);

  // Tab State: 'sessions' | 'history' | 'recap'
  const [activeTab, setActiveTab] = useState<'sessions' | 'history' | 'recap'>('sessions');

  // Filter State - Sesi KBM
  const [sessionClassId, setSessionClassId] = useState<string>('');
  const [sessionSubjectId, setSessionSubjectId] = useState<string>('');
  const [sessionStartDate, setSessionStartDate] = useState<string>('');
  const [sessionEndDate, setSessionEndDate] = useState<string>('');
  const [sessionSearch, setSessionSearch] = useState<string>('');

  // Filter State - Riwayat Presensi
  const [historyClassId, setHistoryClassId] = useState<string>('');
  const [historyStatus, setHistoryStatus] = useState<string>('');
  const [historySource, setHistorySource] = useState<string>('');
  const [historyStartDate, setHistoryStartDate] = useState<string>('');
  const [historyEndDate, setHistoryEndDate] = useState<string>('');
  const [historySearch, setHistorySearch] = useState<string>('');

  // Filter State - Rekap Kelas
  const [recapClassId, setRecapClassId] = useState<string>('');

  // Dialog State
  const [attendanceDialogOpen, setAttendanceDialogOpen] = useState<boolean>(false);
  const [attendanceDetailOpen, setAttendanceDetailOpen] = useState<boolean>(false);
  const [selectedJournal, setSelectedJournal] = useState<JournalItem | null>(null);

  // Success Feedback
  const [feedbackMessage, setFeedbackMessage] = useState<string | null>(null);

  // 1. Fetch Lookups (Classes, Teachers, Subjects)
  const { data: classes = [] } = useQuery<SchoolClassItem[]>({
    queryKey: ['academic-classes'],
    queryFn: () => classesApi.list({ limit: 100 }).then((res) => res.data),
  });

  const { data: teachers = [] } = useQuery<TeacherItem[]>({
    queryKey: ['academic-teachers'],
    queryFn: () => teachersApi.list({ limit: 100 }).then((res) => res.data),
  });

  const { data: subjects = [] } = useQuery<SubjectItem[]>({
    queryKey: ['academic-subjects'],
    queryFn: () => subjectsApi.list({ limit: 100 }).then((res) => res.data),
  });

  // 2. Fetch Sessions (Journals)
  const journalFilters = useMemo(() => {
    const filters: Record<string, string | number> = { per_page: 50 };
    if (sessionClassId) filters.class_id = sessionClassId;
    if (sessionSubjectId) filters.subject_id = sessionSubjectId;
    if (sessionStartDate) filters.start_date = sessionStartDate;
    if (sessionEndDate) filters.end_date = sessionEndDate;
    return filters;
  }, [sessionClassId, sessionSubjectId, sessionStartDate, sessionEndDate]);

  const {
    data: journalsData,
    isLoading: isJournalsLoading,
    refetch: refetchJournals,
  } = useQuery({
    queryKey: ['academic-attendance-sessions', journalFilters],
    queryFn: () => academicApi.listJournals(journalFilters),
  });

  const journals = useMemo(() => journalsData?.data || [], [journalsData?.data]);

  // Filter sessions by search text locally
  const filteredJournals = useMemo(() => {
    if (!sessionSearch.trim()) return journals;
    const q = sessionSearch.toLowerCase();
    return journals.filter((j) => {
      const topic = j.topic?.toLowerCase() || '';
      const className =
        j.schedule?.class?.name?.toLowerCase() ||
        (j.schedule as any)?.class_room?.name?.toLowerCase() ||
        '';
      const subjectName = j.schedule?.subject?.name?.toLowerCase() || '';
      const teacherName = j.teacher?.full_name?.toLowerCase() || '';
      return (
        topic.includes(q) ||
        className.includes(q) ||
        subjectName.includes(q) ||
        teacherName.includes(q)
      );
    });
  }, [journals, sessionSearch]);

  // 3. Fetch Attendance History (Detail Records)
  const attendanceFilters = useMemo(() => {
    const filters: Record<string, any> = { per_page: 50 };
    if (historyClassId) filters.class_id = historyClassId;
    if (historyStatus) filters.status = historyStatus as AttendanceStatus;
    if (historySource) filters.source = historySource as AttendanceSource;
    if (historyStartDate) filters.start_date = historyStartDate;
    if (historyEndDate) filters.end_date = historyEndDate;
    if (historySearch) filters.q = historySearch;
    return filters;
  }, [
    historyClassId,
    historyStatus,
    historySource,
    historyStartDate,
    historyEndDate,
    historySearch,
  ]);

  const {
    data: attendancesData,
    isLoading: isAttendancesLoading,
    refetch: refetchAttendances,
  } = useQuery({
    queryKey: ['academic-attendance-history', attendanceFilters],
    queryFn: () => academicApi.listAttendances(attendanceFilters),
  });

  const attendances = useMemo(() => attendancesData?.data || [], [attendancesData?.data]);

  // 4. Fetch Global / Class Attendance Recap
  const recapQueryFilters = useMemo(() => {
    const filters: Record<string, string> = {};
    if (recapClassId) filters.class_id = recapClassId;
    return filters;
  }, [recapClassId]);

  const { data: recapData, isLoading: isRecapLoading } = useQuery({
    queryKey: ['academic-attendance-recap', recapQueryFilters],
    queryFn: () => academicApi.getAttendanceRecap(recapQueryFilters),
  });

  // Action: Buka dialog presensi
  const handleOpenAttendanceForm = (journal: JournalItem) => {
    setSelectedJournal(journal);
    setAttendanceDialogOpen(true);
  };

  // Action: Buka detail presensi
  const handleOpenAttendanceDetail = (journal: JournalItem) => {
    setSelectedJournal(journal);
    setAttendanceDetailOpen(true);
  };

  // Callback setelah presensi berhasil disimpan
  const handleAttendanceSuccess = () => {
    queryClient.invalidateQueries({ queryKey: ['academic-attendance-sessions'] });
    queryClient.invalidateQueries({ queryKey: ['academic-attendance-history'] });
    queryClient.invalidateQueries({ queryKey: ['academic-attendance-recap'] });
    setFeedbackMessage('Presensi sesi KBM berhasil dicatat dan disinkronisasikan!');
    setTimeout(() => setFeedbackMessage(null), 5000);
  };

  // Kolom DataTable Riwayat Presensi
  const historyColumns: ColumnDef<AttendanceItem>[] = useMemo(
    () => [
      {
        key: 'att_date',
        header: 'Tanggal',
        render: (item: AttendanceItem) => {
          const formatted =
            typeof item.att_date === 'string' ? item.att_date.slice(0, 10) : '-';
          return (
            <div className="flex items-center gap-1.5 text-xs font-medium text-slate-800">
              <Calendar className="w-3.5 h-3.5 text-slate-400 shrink-0" />
              <span>{formatted}</span>
            </div>
          );
        },
      },
      {
        key: 'student',
        header: 'Siswa',
        render: (item: AttendanceItem) => (
          <div>
            <span className="font-semibold text-slate-900 block text-xs">
              {item.student?.full_name || 'Siswa'}
            </span>
            <span className="text-[11px] font-mono text-slate-500">
              NIS: {item.student?.nis || '-'}
            </span>
          </div>
        ),
      },
      {
        key: 'class',
        header: 'Kelas / Rombel',
        render: (item: AttendanceItem) => {
          const className =
            item.journal?.schedule?.class?.name ||
            (item.journal?.schedule as any)?.class_room?.name ||
            '-';
          return (
            <div className="flex items-center gap-1.5 text-xs text-slate-700">
              <Layers className="w-3.5 h-3.5 text-slate-400 shrink-0" />
              <span className="font-medium">{className}</span>
            </div>
          );
        },
      },
      {
        key: 'subject',
        header: 'Mata Pelajaran / Sesi',
        render: (item: AttendanceItem) => {
          const subjectName = item.journal?.schedule?.subject?.name || 'Pertemuan Kelas';
          const topic = item.journal?.topic;
          return (
            <div className="text-xs">
              <span className="font-medium text-slate-900 block">{subjectName}</span>
              {topic && (
                <span className="text-[11px] text-slate-500 truncate max-w-[200px] block">
                  {topic}
                </span>
              )}
            </div>
          );
        },
      },
      {
        key: 'status',
        header: 'Status',
        render: (item: AttendanceItem) => {
          const statusKey = item.status as AttendanceStatus;
          const colorConfig =
            ATTENDANCE_STATUS_COLORS[statusKey] || ATTENDANCE_STATUS_COLORS.hadir;
          return (
            <span
              className={`inline-block px-2.5 py-0.5 rounded-full text-[11px] font-semibold border ${colorConfig.bg}`}
            >
              {ATTENDANCE_STATUS_LABELS[statusKey] || item.status}
            </span>
          );
        },
      },
      {
        key: 'source',
        header: 'Sumber',
        render: (item: AttendanceItem) => (
          <span className="text-[11px] text-slate-600 capitalize">
            {item.source === 'kelas' ? 'Sesi Kelas (KBM)' : 'Gerbang Harian'}
          </span>
        ),
      },
      {
        key: 'note',
        header: 'Catatan',
        render: (item: AttendanceItem) => (
          <span className="text-xs text-slate-600">
            {item.note || <span className="text-slate-300 italic">-</span>}
          </span>
        ),
      },
    ],
    []
  );

  return (
    <div className="p-6 max-w-7xl mx-auto space-y-6">
      {/* Header Halaman */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-2 border-b border-slate-200">
        <div>
          <div className="flex items-center gap-2">
            <span className="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200">
              ADM-ACD-03
            </span>
            <span className="text-xs text-slate-400">Modul Akademik & KBM</span>
          </div>
          <h1 className="text-2xl font-bold text-slate-900 mt-1">Presensi KBM & Sesi Kelas</h1>
          <p className="text-sm text-slate-500">
            Pencatatan dan pemantauan kehadiran siswa per sesi pertemuan tatap muka dan gerbang harian
          </p>
        </div>

        <div className="flex items-center gap-2.5">
          <Button
            variant="outline"
            size="sm"
            onClick={() => {
              refetchJournals();
              refetchAttendances();
            }}
            className="text-xs h-9"
          >
            <RotateCw className="w-3.5 h-3.5 mr-1.5" />
            Segarkan
          </Button>

          <PermissionGate
            permission={['academic.attendance.write', 'academic.schedule.manage']}
          >
            <Button
              size="sm"
              onClick={() => {
                if (journals.length > 0) {
                  handleOpenAttendanceForm(journals[0]);
                } else {
                  toast.warning('Belum ada sesi jurnal KBM yang tersedia untuk diinput presensi.');
                }
              }}
              className="bg-indigo-600 hover:bg-indigo-700 text-white text-xs h-9"
            >
              <UserCheck className="w-3.5 h-3.5 mr-1.5" />
              Catat Presensi Sesi KBM
            </Button>
          </PermissionGate>
        </div>
      </div>

      {/* Banner Feedback */}
      {feedbackMessage && (
        <div className="p-3.5 bg-emerald-50 border border-emerald-200 rounded-lg text-emerald-800 text-xs flex items-center justify-between animate-in fade-in">
          <div className="flex items-center gap-2">
            <CheckCircle2 className="w-4 h-4 text-emerald-600 shrink-0" />
            <span>{feedbackMessage}</span>
          </div>
          <button
            onClick={() => setFeedbackMessage(null)}
            className="text-emerald-600 hover:text-emerald-800"
          >
            &times;
          </button>
        </div>
      )}

      {/* Ringkasan Kartu Statistik Global */}
      <div className="grid grid-cols-2 md:grid-cols-6 gap-3.5">
        <div className="p-4 bg-white border border-slate-200 rounded-xl shadow-xs">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-slate-500 uppercase tracking-wider">
              Total Catatan
            </span>
            <Users className="w-4 h-4 text-slate-400" />
          </div>
          <span className="text-2xl font-bold text-slate-900 mt-2 block">
            {isRecapLoading ? '...' : recapData?.total_sessions || 0}
          </span>
          <span className="text-[11px] text-slate-400 mt-0.5 block">Sesi Tercatat</span>
        </div>

        <div className="p-4 bg-white border border-emerald-100 rounded-xl shadow-xs">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-emerald-600 uppercase tracking-wider">
              Hadir
            </span>
            <Check className="w-4 h-4 text-emerald-500" />
          </div>
          <span className="text-2xl font-bold text-emerald-700 mt-2 block">
            {isRecapLoading ? '...' : recapData?.summary?.hadir || 0}
          </span>
          <span className="text-[11px] text-emerald-600/80 mt-0.5 block">Tepat Waktu</span>
        </div>

        <div className="p-4 bg-white border border-blue-100 rounded-xl shadow-xs">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-blue-600 uppercase tracking-wider">Izin</span>
            <FileTextIcon className="w-4 h-4 text-blue-500" />
          </div>
          <span className="text-2xl font-bold text-blue-700 mt-2 block">
            {isRecapLoading ? '...' : recapData?.summary?.izin || 0}
          </span>
          <span className="text-[11px] text-blue-600/80 mt-0.5 block">Disetujui</span>
        </div>

        <div className="p-4 bg-white border border-amber-100 rounded-xl shadow-xs">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-amber-600 uppercase tracking-wider">
              Sakit
            </span>
            <AlertTriangle className="w-4 h-4 text-amber-500" />
          </div>
          <span className="text-2xl font-bold text-amber-700 mt-2 block">
            {isRecapLoading ? '...' : recapData?.summary?.sakit || 0}
          </span>
          <span className="text-[11px] text-amber-600/80 mt-0.5 block">Surat Keterangan</span>
        </div>

        <div className="p-4 bg-white border border-rose-100 rounded-xl shadow-xs">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-rose-600 uppercase tracking-wider">Alpa</span>
            <XCircle className="w-4 h-4 text-rose-500" />
          </div>
          <span className="text-2xl font-bold text-rose-700 mt-2 block">
            {isRecapLoading ? '...' : recapData?.summary?.alpa || 0}
          </span>
          <span className="text-[11px] text-rose-600/80 mt-0.5 block">Tanpa Keterangan</span>
        </div>

        <div className="p-4 bg-gradient-to-br from-indigo-50 to-indigo-100/60 border border-indigo-200 rounded-xl shadow-xs">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-indigo-700 uppercase tracking-wider">
              Tingkat Hadir
            </span>
            <BarChart3 className="w-4 h-4 text-indigo-600" />
          </div>
          <span className="text-2xl font-bold text-indigo-900 mt-2 block">
            {isRecapLoading ? '...' : `${recapData?.attendance_rate || 100}%`}
          </span>
          <span className="text-[11px] text-indigo-700 mt-0.5 block">Rasio Kehadiran</span>
        </div>
      </div>

      {/* Navigasi Tab */}
      <div className="border-b border-slate-200">
        <nav className="flex space-x-6">
          <button
            onClick={() => setActiveTab('sessions')}
            className={`pb-3 text-sm font-semibold border-b-2 transition-colors flex items-center gap-2 ${
              activeTab === 'sessions'
                ? 'border-indigo-600 text-indigo-600'
                : 'border-transparent text-slate-500 hover:text-slate-700'
            }`}
          >
            <Clock className="w-4 h-4" />
            Sesi Pertemuan KBM
            <span className="ml-1 px-2 py-0.5 rounded-full text-[11px] bg-slate-100 text-slate-600">
              {filteredJournals.length}
            </span>
          </button>

          <button
            onClick={() => setActiveTab('history')}
            className={`pb-3 text-sm font-semibold border-b-2 transition-colors flex items-center gap-2 ${
              activeTab === 'history'
                ? 'border-indigo-600 text-indigo-600'
                : 'border-transparent text-slate-500 hover:text-slate-700'
            }`}
          >
            <UserCheck className="w-4 h-4" />
            Riwayat Presensi Siswa
            <span className="ml-1 px-2 py-0.5 rounded-full text-[11px] bg-slate-100 text-slate-600">
              {attendances.length}
            </span>
          </button>

          <button
            onClick={() => setActiveTab('recap')}
            className={`pb-3 text-sm font-semibold border-b-2 transition-colors flex items-center gap-2 ${
              activeTab === 'recap'
                ? 'border-indigo-600 text-indigo-600'
                : 'border-transparent text-slate-500 hover:text-slate-700'
            }`}
          >
            <BarChart3 className="w-4 h-4" />
            Rekapitulasi Kehadiran Kelas
          </button>
        </nav>
      </div>

      {/* TAB 1: SESI PERTEMUAN KBM */}
      {activeTab === 'sessions' && (
        <div className="space-y-4">
          {/* Filter Bar Sesi */}
          <div className="p-4 bg-white border border-slate-200 rounded-xl shadow-xs grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <div>
              <label className="text-[11px] font-semibold text-slate-500 block mb-1 uppercase tracking-wider">
                Kelas / Rombel
              </label>
              <select
                value={sessionClassId}
                onChange={(e) => setSessionClassId(e.target.value)}
                className="w-full text-xs h-9 px-2.5 rounded-md border border-slate-300 bg-white"
              >
                <option value="">Semua Rombel</option>
                {classes.map((c) => (
                  <option key={c.id} value={c.id}>
                    {c.name}
                  </option>
                ))}
              </select>
            </div>

            <div>
              <label className="text-[11px] font-semibold text-slate-500 block mb-1 uppercase tracking-wider">
                Mata Pelajaran
              </label>
              <select
                value={sessionSubjectId}
                onChange={(e) => setSessionSubjectId(e.target.value)}
                className="w-full text-xs h-9 px-2.5 rounded-md border border-slate-300 bg-white"
              >
                <option value="">Semua Mapel</option>
                {subjects.map((s) => (
                  <option key={s.id} value={s.id}>
                    {s.name}
                  </option>
                ))}
              </select>
            </div>

            <div>
              <label className="text-[11px] font-semibold text-slate-500 block mb-1 uppercase tracking-wider">
                Tanggal Mulai
              </label>
              <Input
                type="date"
                value={sessionStartDate}
                onChange={(e) => setSessionStartDate(e.target.value)}
                className="h-9 text-xs"
              />
            </div>

            <div>
              <label className="text-[11px] font-semibold text-slate-500 block mb-1 uppercase tracking-wider">
                Tanggal Selesai
              </label>
              <Input
                type="date"
                value={sessionEndDate}
                onChange={(e) => setSessionEndDate(e.target.value)}
                className="h-9 text-xs"
              />
            </div>

            <div>
              <label className="text-[11px] font-semibold text-slate-500 block mb-1 uppercase tracking-wider">
                Pencarian Topik
              </label>
              <div className="relative">
                <Search className="w-3.5 h-3.5 absolute left-2.5 top-2.5 text-slate-400" />
                <Input
                  type="text"
                  placeholder="Cari materi..."
                  value={sessionSearch}
                  onChange={(e) => setSessionSearch(e.target.value)}
                  className="h-9 text-xs pl-8"
                />
              </div>
            </div>
          </div>

          {/* Daftar Sesi Pertemuan KBM */}
          {isJournalsLoading ? (
            <div className="p-12 text-center text-slate-500 text-sm bg-white rounded-xl border border-slate-200">
              <div className="w-8 h-8 border-2 border-indigo-600 border-t-transparent rounded-full animate-spin mx-auto mb-3" />
              Memuat data sesi pertemuan KBM...
            </div>
          ) : filteredJournals.length === 0 ? (
            <div className="p-12 text-center text-slate-400 text-sm bg-white rounded-xl border border-slate-200">
              <Clock className="w-10 h-10 text-slate-300 mx-auto mb-2" />
              Tidak ada sesi pertemuan KBM yang cocok dengan kriteria filter.
            </div>
          ) : (
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              {filteredJournals.map((journal) => {
                const className =
                  journal.schedule?.class?.name ||
                  (journal.schedule as any)?.class_room?.name ||
                  'Kelas';
                const subjectName = journal.schedule?.subject?.name || 'Mata Pelajaran';
                const teacherName =
                  journal.teacher?.full_name ||
                  journal.schedule?.teacher?.full_name ||
                  'Guru Pengajar';
                const sessionDateFormatted =
                  typeof journal.session_date === 'string'
                    ? journal.session_date.slice(0, 10)
                    : '';
                const timeSlot = journal.schedule?.timeSlot;
                const hasAttendances =
                  (journal.attendances && journal.attendances.length > 0) ||
                  (journal.attendances_count && journal.attendances_count > 0);
                const attendanceCount =
                  journal.attendances?.length || journal.attendances_count || 0;

                return (
                  <div
                    key={journal.id}
                    className="p-5 bg-white border border-slate-200 rounded-xl shadow-xs hover:shadow-md transition-shadow flex flex-col justify-between"
                  >
                    <div>
                      {/* Top Badges */}
                      <div className="flex items-center justify-between gap-2 mb-2.5">
                        <span className="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">
                          {className}
                        </span>
                        {hasAttendances ? (
                          <span className="px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200 flex items-center gap-1">
                            <CheckCircle2 className="w-3 h-3 text-emerald-600" />
                            Sudah Diisi ({attendanceCount} siswa)
                          </span>
                        ) : (
                          <span className="px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-amber-50 text-amber-700 border border-amber-200 flex items-center gap-1">
                            <Clock4 className="w-3 h-3 text-amber-600" />
                            Belum Diisi
                          </span>
                        )}
                      </div>

                      {/* Mapel & Waktu */}
                      <h3 className="text-base font-bold text-slate-900 line-clamp-1">
                        {subjectName}
                      </h3>
                      <div className="flex items-center gap-2 text-xs text-slate-500 mt-1">
                        <Calendar className="w-3.5 h-3.5 text-slate-400" />
                        <span>{sessionDateFormatted}</span>
                        {timeSlot && (
                          <>
                            <span>•</span>
                            <Clock className="w-3.5 h-3.5 text-slate-400" />
                            <span>
                              {timeSlot.start_time.slice(0, 5)} - {timeSlot.end_time.slice(0, 5)}
                            </span>
                          </>
                        )}
                      </div>

                      {/* Topik / Materi */}
                      <div className="mt-3 pt-3 border-t border-slate-100 text-xs">
                        <span className="text-[10px] text-slate-400 font-medium uppercase tracking-wider block">
                          Materi Pembelajaran
                        </span>
                        <p className="font-medium text-slate-800 line-clamp-2 mt-0.5">
                          {journal.topic}
                        </p>
                      </div>

                      <div className="mt-2 text-xs text-slate-500 flex items-center gap-1.5">
                        <User className="w-3.5 h-3.5 text-slate-400 shrink-0" />
                        <span className="truncate">{teacherName}</span>
                      </div>
                    </div>

                    {/* Tombol Aksi */}
                    <div className="mt-4 pt-3 border-t border-slate-100 flex items-center gap-2">
                      <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={() => handleOpenAttendanceDetail(journal)}
                        className="flex-1 text-xs h-8 text-slate-700"
                      >
                        <Eye className="w-3.5 h-3.5 mr-1 text-slate-500" />
                        Detail
                      </Button>

                      <PermissionGate
                        permission={['academic.attendance.write', 'academic.schedule.manage']}
                      >
                        <Button
                          type="button"
                          size="sm"
                          onClick={() => handleOpenAttendanceForm(journal)}
                          className={`flex-1 text-xs h-8 text-white ${
                            hasAttendances
                              ? 'bg-emerald-600 hover:bg-emerald-700'
                              : 'bg-indigo-600 hover:bg-indigo-700'
                          }`}
                        >
                          <Edit className="w-3.5 h-3.5 mr-1" />
                          {hasAttendances ? 'Ubah Presensi' : 'Isi Presensi'}
                        </Button>
                      </PermissionGate>
                    </div>
                  </div>
                );
              })}
            </div>
          )}
        </div>
      )}

      {/* TAB 2: RIWAYAT DETAIL PRESENSI SISWA */}
      {activeTab === 'history' && (
        <div className="space-y-4">
          {/* Filter Bar Riwayat */}
          <div className="p-4 bg-white border border-slate-200 rounded-xl shadow-xs grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
            <div>
              <label className="text-[11px] font-semibold text-slate-500 block mb-1 uppercase tracking-wider">
                Kelas
              </label>
              <select
                value={historyClassId}
                onChange={(e) => setHistoryClassId(e.target.value)}
                className="w-full text-xs h-9 px-2.5 rounded-md border border-slate-300 bg-white"
              >
                <option value="">Semua Kelas</option>
                {classes.map((c) => (
                  <option key={c.id} value={c.id}>
                    {c.name}
                  </option>
                ))}
              </select>
            </div>

            <div>
              <label className="text-[11px] font-semibold text-slate-500 block mb-1 uppercase tracking-wider">
                Status Kehadiran
              </label>
              <select
                value={historyStatus}
                onChange={(e) => setHistoryStatus(e.target.value)}
                className="w-full text-xs h-9 px-2.5 rounded-md border border-slate-300 bg-white"
              >
                <option value="">Semua Status</option>
                <option value="hadir">Hadir</option>
                <option value="izin">Izin</option>
                <option value="sakit">Sakit</option>
                <option value="alpa">Alpa</option>
                <option value="terlambat">Terlambat</option>
              </select>
            </div>

            <div>
              <label className="text-[11px] font-semibold text-slate-500 block mb-1 uppercase tracking-wider">
                Sumber Catatan
              </label>
              <select
                value={historySource}
                onChange={(e) => setHistorySource(e.target.value)}
                className="w-full text-xs h-9 px-2.5 rounded-md border border-slate-300 bg-white"
              >
                <option value="">Semua Sumber</option>
                <option value="kelas">Sesi Kelas (KBM)</option>
                <option value="gerbang">Gerbang Masuk</option>
              </select>
            </div>

            <div>
              <label className="text-[11px] font-semibold text-slate-500 block mb-1 uppercase tracking-wider">
                Tanggal Mulai
              </label>
              <Input
                type="date"
                value={historyStartDate}
                onChange={(e) => setHistoryStartDate(e.target.value)}
                className="h-9 text-xs"
              />
            </div>

            <div>
              <label className="text-[11px] font-semibold text-slate-500 block mb-1 uppercase tracking-wider">
                Tanggal Selesai
              </label>
              <Input
                type="date"
                value={historyEndDate}
                onChange={(e) => setHistoryEndDate(e.target.value)}
                className="h-9 text-xs"
              />
            </div>

            <div>
              <label className="text-[11px] font-semibold text-slate-500 block mb-1 uppercase tracking-wider">
                Cari Siswa
              </label>
              <div className="relative">
                <Search className="w-3.5 h-3.5 absolute left-2.5 top-2.5 text-slate-400" />
                <Input
                  type="text"
                  placeholder="Nama / NIS..."
                  value={historySearch}
                  onChange={(e) => setHistorySearch(e.target.value)}
                  className="h-9 text-xs pl-8"
                />
              </div>
            </div>
          </div>

          {/* DataTable Riwayat */}
          <DataTable<AttendanceItem>
            columns={historyColumns}
            data={attendances}
            keyExtractor={(item) => item.id}
            isLoading={isAttendancesLoading}
            emptyTitle="Belum ada catatan presensi"
            emptyDescription="Tidak ada data presensi siswa yang cocok dengan kriteria filter saat ini."
          />
        </div>
      )}

      {/* TAB 3: REKAPITULASI KEHADIRAN KELAS */}
      {activeTab === 'recap' && (
        <div className="space-y-6">
          <div className="p-4 bg-white border border-slate-200 rounded-xl shadow-xs flex flex-wrap items-center gap-4">
            <div className="w-64">
              <label className="text-xs font-semibold text-slate-700 block mb-1">
                Pilih Kelas untuk Rekap:
              </label>
              <select
                value={recapClassId}
                onChange={(e) => setRecapClassId(e.target.value)}
                className="w-full text-xs h-9 px-3 rounded-md border border-slate-300 bg-white"
              >
                <option value="">Seluruh Sekolah (Akumulasi)</option>
                {classes.map((c) => (
                  <option key={c.id} value={c.id}>
                    {c.name}
                  </option>
                ))}
              </select>
            </div>
          </div>

          <div className="p-6 bg-white border border-slate-200 rounded-xl shadow-xs">
            <h3 className="text-base font-bold text-slate-900 mb-4 flex items-center gap-2">
              <BarChart3 className="w-5 h-5 text-indigo-600" />
              Statistik Kehadiran{' '}
              {recapClassId
                ? classes.find((c) => c.id === recapClassId)?.name || 'Kelas'
                : 'Seluruh Sekolah'}
            </h3>

            {isRecapLoading ? (
              <div className="py-12 text-center text-slate-500">Menghitung rekapitulasi...</div>
            ) : (
              <div className="space-y-6">
                <div className="grid grid-cols-2 md:grid-cols-5 gap-4">
                  <div className="p-4 bg-emerald-50/70 border border-emerald-200 rounded-xl">
                    <span className="text-xs font-semibold text-emerald-800 block">Hadir</span>
                    <span className="text-2xl font-bold text-emerald-900 mt-1 block">
                      {recapData?.summary?.hadir || 0}
                    </span>
                  </div>
                  <div className="p-4 bg-blue-50/70 border border-blue-200 rounded-xl">
                    <span className="text-xs font-semibold text-blue-800 block">Izin</span>
                    <span className="text-2xl font-bold text-blue-900 mt-1 block">
                      {recapData?.summary?.izin || 0}
                    </span>
                  </div>
                  <div className="p-4 bg-amber-50/70 border border-amber-200 rounded-xl">
                    <span className="text-xs font-semibold text-amber-800 block">Sakit</span>
                    <span className="text-2xl font-bold text-amber-900 mt-1 block">
                      {recapData?.summary?.sakit || 0}
                    </span>
                  </div>
                  <div className="p-4 bg-rose-50/70 border border-rose-200 rounded-xl">
                    <span className="text-xs font-semibold text-rose-800 block">Alpa</span>
                    <span className="text-2xl font-bold text-rose-900 mt-1 block">
                      {recapData?.summary?.alpa || 0}
                    </span>
                  </div>
                  <div className="p-4 bg-purple-50/70 border border-purple-200 rounded-xl">
                    <span className="text-xs font-semibold text-purple-800 block">Terlambat</span>
                    <span className="text-2xl font-bold text-purple-900 mt-1 block">
                      {recapData?.summary?.terlambat || 0}
                    </span>
                  </div>
                </div>

                <div className="p-5 bg-slate-50 border border-slate-200 rounded-xl">
                  <div className="flex items-center justify-between mb-2">
                    <span className="text-sm font-semibold text-slate-800">
                      Tingkat Kehadiran Efektif:
                    </span>
                    <span className="text-lg font-bold text-indigo-700">
                      {recapData?.attendance_rate || 100}%
                    </span>
                  </div>
                  <div className="w-full h-3 bg-slate-200 rounded-full overflow-hidden">
                    <div
                      className="h-full bg-indigo-600 rounded-full transition-all duration-500"
                      style={{ width: `${recapData?.attendance_rate || 100}%` }}
                    />
                  </div>
                  <p className="text-[11px] text-slate-500 mt-2">
                    Dihitung berdasarkan proporsi siswa yang hadir dan terlambat dibanding total
                    seluruh sesi pertemuan yang tercatat.
                  </p>
                </div>
              </div>
            )}
          </div>
        </div>
      )}

      {/* Dialog Catat Presensi Sesi */}
      <SessionAttendanceDialog
        isOpen={attendanceDialogOpen}
        onClose={() => setAttendanceDialogOpen(false)}
        onSuccess={handleAttendanceSuccess}
        journal={selectedJournal}
        isAdmin={isAdmin}
      />

      {/* Dialog Detail Presensi Sesi */}
      <SessionAttendanceDetailDialog
        isOpen={attendanceDetailOpen}
        onClose={() => setAttendanceDetailOpen(false)}
        onEdit={() => {
          if (selectedJournal) {
            handleOpenAttendanceForm(selectedJournal);
          }
        }}
        journal={selectedJournal}
        canEdit={isAdmin || true}
      />
    </div>
  );
}

function FileTextIcon(props: React.SVGProps<SVGSVGElement>) {
  return (
    <svg
      {...props}
      xmlns="http://www.w3.org/2000/svg"
      width="24"
      height="24"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
    >
      <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z" />
      <polyline points="14 2 14 8 20 8" />
      <line x1="16" x2="8" y1="13" y2="13" />
      <line x1="16" x2="8" y1="17" y2="17" />
      <line x1="10" x2="8" y1="9" y2="9" />
    </svg>
  );
}
