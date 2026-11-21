'use client';

import React, { useState, useMemo } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { academicApi } from '@/features/academic/academic-api';
import { classesApi } from '@/features/master/classes-api';
import { teachersApi } from '@/features/master/teachers-api';
import { subjectsApi } from '@/features/master/subjects-api';
import { JournalDialog, JournalDetailDialog } from '@/features/academic/journals';
import {
  JournalItem,
  ScheduleItem,
  DayOfWeek,
  DAY_OF_WEEK_LABELS,
} from '@/types/academic';
import { SchoolClassItem, TeacherItem, SubjectItem } from '@/types/master';
import { DataTable, ColumnDef } from '@/components/shared/data-table';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { PermissionGate } from '@/components/shared/permission-gate';
import { useAuth } from '@/providers/auth-provider';
import { hasPermission } from '@/lib/rbac/evaluator';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
  BookMarked,
  Calendar,
  Clock,
  Plus,
  Eye,
  Edit2,
  Trash2,
  Filter,
  Layers,
  BookOpen,
  User,
  CheckCircle2,
  AlertCircle,
  FileText,
  CalendarDays,
  Sparkles,
} from 'lucide-react';

export default function JournalsPage() {
  const queryClient = useQueryClient();
  const { user } = useAuth();

  const userRoles = Array.isArray(user?.roles) ? user.roles : [];
  const isAdmin =
    hasPermission(user, 'academic.schedule.manage') ||
    userRoles.includes('super_admin') ||
    userRoles.includes('admin_kurikulum');
  const canWrite = hasPermission(user, 'academic.journal.write') || isAdmin;

  // Filter states
  const [selectedClassId, setSelectedClassId] = useState<string>('');
  const [selectedTeacherId, setSelectedTeacherId] = useState<string>('');
  const [startDate, setStartDate] = useState<string>('');
  const [endDate, setEndDate] = useState<string>('');
  const [activeTab, setActiveTab] = useState<'history' | 'today'>('history');

  // Dialog states
  const [isJournalDialogOpen, setIsJournalDialogOpen] = useState(false);
  const [editingJournal, setEditingJournal] = useState<JournalItem | null>(null);
  const [selectedScheduleIdForAdd, setSelectedScheduleIdForAdd] = useState<string | undefined>(undefined);
  const [selectedSessionDateForAdd, setSelectedSessionDateForAdd] = useState<string | undefined>(undefined);

  const [detailJournalId, setDetailJournalId] = useState<string | null>(null);
  const [isDetailDialogOpen, setIsDetailDialogOpen] = useState(false);
  const [deletingJournal, setDeletingJournal] = useState<JournalItem | null>(null);

  // Feedback banner
  const [feedback, setFeedback] = useState<{ type: 'success' | 'error'; message: string } | null>(
    null
  );

  // Today's day of week (1: Senin s.d. 7: Minggu)
  const currentDayOfWeek = useMemo<DayOfWeek>(() => {
    const day = new Date().getDay();
    return (day === 0 ? 7 : day) as DayOfWeek;
  }, []);

  const todayStr = useMemo(() => new Date().toISOString().slice(0, 10), []);

  // 1. Fetch Lookups
  const { data: classes = [] } = useQuery<SchoolClassItem[]>({
    queryKey: ['academic-classes'],
    queryFn: () => classesApi.list({ limit: 100 }).then((res) => res.data),
  });

  const { data: teachers = [] } = useQuery<TeacherItem[]>({
    queryKey: ['academic-teachers'],
    queryFn: () => teachersApi.list({ limit: 100 }).then((res) => res.data),
  });

  const { data: schedules = [] } = useQuery<ScheduleItem[]>({
    queryKey: ['academic-schedules-for-journal'],
    queryFn: () => academicApi.listSchedules({ all: true }),
  });

  // 2. Fetch Journals
  const journalFilters = useMemo(() => {
    const filters: Record<string, string | number> = {
      per_page: 50,
    };
    if (selectedClassId) filters.class_id = selectedClassId;
    if (selectedTeacherId) filters.teacher_id = selectedTeacherId;
    if (startDate) filters.start_date = startDate;
    if (endDate) filters.end_date = endDate;
    return filters;
  }, [selectedClassId, selectedTeacherId, startDate, endDate]);

  const {
    data: journalsData,
    isLoading: isJournalsLoading,
  } = useQuery({
    queryKey: ['academic-journals', journalFilters],
    queryFn: () => academicApi.listJournals(journalFilters),
  });

  const journals = useMemo(() => journalsData?.data || [], [journalsData?.data]);

  // Filter schedules for today's sessions
  const todaySchedules = useMemo(() => {
    return schedules.filter((sch) => sch.day_of_week === currentDayOfWeek);
  }, [schedules, currentDayOfWeek]);

  // Set of schedule IDs that already have a journal on today's date
  const todayFilledScheduleIds = useMemo(() => {
    const filledIds = new Set<string>();
    journals.forEach((j) => {
      const jDate = typeof j.session_date === 'string' ? j.session_date.slice(0, 10) : '';
      if (jDate === todayStr) {
        filledIds.add(j.schedule_id);
      }
    });
    return filledIds;
  }, [journals, todayStr]);

  // 3. Delete Mutation
  const deleteMutation = useMutation({
    mutationFn: (id: string) => academicApi.deleteJournal(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['academic-journals'] });
      setDeletingJournal(null);
      setFeedback({ type: 'success', message: 'Jurnal mengajar berhasil dihapus.' });
    },
    onError: (err: any) => {
      const msg = err?.message || 'Gagal menghapus jurnal mengajar.';
      setFeedback({ type: 'error', message: msg });
    },
  });

  // Check if a journal is within the 3-day editable window
  const isJournalEditable = (journal: JournalItem) => {
    if (isAdmin) return true;
    const sessionDate = new Date(journal.session_date);
    const today = new Date();
    const diffDays = Math.floor((today.getTime() - sessionDate.getTime()) / (1000 * 3600 * 24));
    return diffDays <= 3;
  };

  // Handlers
  const handleOpenAdd = (scheduleId?: string, sessionDate?: string) => {
    setEditingJournal(null);
    setSelectedScheduleIdForAdd(scheduleId);
    setSelectedSessionDateForAdd(sessionDate || todayStr);
    setIsJournalDialogOpen(true);
  };

  const handleOpenEdit = (journal: JournalItem) => {
    setEditingJournal(journal);
    setSelectedScheduleIdForAdd(journal.schedule_id);
    setSelectedSessionDateForAdd(typeof journal.session_date === 'string' ? journal.session_date.slice(0, 10) : todayStr);
    setIsJournalDialogOpen(true);
  };

  const handleOpenDetail = (id: string) => {
    setDetailJournalId(id);
    setIsDetailDialogOpen(true);
  };

  // Table Columns
  const columns: ColumnDef<JournalItem>[] = [
    {
      key: 'session_date',
      header: 'Tanggal Sesi',
      render: (item: JournalItem) => (
        <div>
          <div className="font-semibold text-gray-900 text-xs">
            {new Date(item.session_date).toLocaleDateString('id-ID', {
              day: 'numeric',
              month: 'short',
              year: 'numeric',
            })}
          </div>
          <div className="text-[11px] text-gray-500">
            {item.schedule ? DAY_OF_WEEK_LABELS[item.schedule.day_of_week] : ''}
          </div>
        </div>
      ),
    },
    {
      key: 'class_id',
      header: 'Rombel',
      render: (item: JournalItem) => (
        <span className="inline-flex items-center gap-1 rounded bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-800">
          <Layers className="h-3 w-3 text-slate-500" />
          {item.schedule?.class?.name || '-'}
        </span>
      ),
    },
    {
      key: 'subject_id',
      header: 'Mata Pelajaran',
      render: (item: JournalItem) => (
        <div>
          <div className="font-semibold text-gray-900 flex items-center gap-1">
            <BookOpen className="h-3.5 w-3.5 text-emerald-600" />
            <span>{item.schedule?.subject?.name || '-'}</span>
          </div>
          <div className="text-[11px] text-gray-500 font-mono">
            Slot: {item.schedule?.timeSlot?.name || 'Jam'} ({item.schedule?.timeSlot?.start_time} - {item.schedule?.timeSlot?.end_time})
          </div>
        </div>
      ),
    },
    {
      key: 'teacher_id',
      header: 'Guru Pengajar',
      render: (item: JournalItem) => (
        <div className="flex items-center gap-1 text-gray-700">
          <User className="h-3.5 w-3.5 text-gray-400" />
          <span className="truncate max-w-[140px]">{item.teacher?.full_name || '-'}</span>
        </div>
      ),
    },
    {
      key: 'topic',
      header: 'Materi / Topik KBM',
      render: (item: JournalItem) => (
        <div className="max-w-[220px]">
          <div className="font-medium text-gray-900 truncate" title={item.topic}>
            {item.topic}
          </div>
          <div className="text-[11px] text-gray-500 truncate" title={item.method}>
            Metode: {item.method}
          </div>
        </div>
      ),
    },
    {
      key: 'attendances_count',
      header: 'Kehadiran',
      render: (item: JournalItem) => (
        item.attendances_count && item.attendances_count > 0 ? (
          <span className="inline-flex items-center gap-1 rounded bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 border border-emerald-200">
            <CheckCircle2 className="h-3 w-3 text-emerald-600" />
            {item.attendances_count} Siswa
          </span>
        ) : (
          <span className="text-[11px] text-gray-400 italic">Belum Ada</span>
        )
      ),
    },
    {
      key: 'actions',
      header: 'Aksi',
      render: (item: JournalItem) => {
        const editable = isJournalEditable(item);
        return (
          <div className="flex items-center gap-1">
            <Button
              variant="outline"
              size="sm"
              onClick={() => handleOpenDetail(item.id)}
              className="h-7 px-2 text-xs text-gray-700 hover:text-emerald-700"
              title="Lihat Detail Jurnal"
            >
              <Eye className="h-3 w-3" />
            </Button>

            {canWrite && editable && (
              <Button
                variant="outline"
                size="sm"
                onClick={() => handleOpenEdit(item)}
                className="h-7 px-2 text-xs text-gray-700 hover:text-emerald-700"
                title="Edit Jurnal"
              >
                <Edit2 className="h-3 w-3" />
              </Button>
            )}

            {canWrite && editable && (
              <Button
                variant="outline"
                size="sm"
                onClick={() => setDeletingJournal(item)}
                className="h-7 px-2 text-xs text-red-600 hover:bg-red-50 hover:text-red-700 border-red-200"
                title="Hapus Jurnal"
              >
                <Trash2 className="h-3 w-3" />
              </Button>
            )}
          </div>
        );
      },
    },
  ];

  return (
    <PermissionGate
      permission={['academic.journal.write', 'academic.journal.read', 'academic.schedule.manage']}
      fallback={
        <div className="p-8 text-center text-sm text-gray-500">
          Anda tidak memiliki hak akses untuk membuka modul Jurnal Mengajar Guru.
        </div>
      }
    >
      <div className="space-y-6">
        {/* Top Header */}
        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <div className="flex items-center gap-2">
              <div className="rounded-lg bg-emerald-50 p-2 text-emerald-600 border border-emerald-100">
                <BookMarked className="h-6 w-6" />
              </div>
              <div>
                <h1 className="text-xl font-bold tracking-tight text-gray-900">
                  Jurnal Mengajar Guru
                </h1>
                <p className="text-xs text-gray-500">
                  Dokumentasi sesi KBM per pertemuan, topik/materi, metode pembelajaran, dan integrasi presensi
                </p>
              </div>
            </div>
          </div>

          {canWrite && (
            <div className="flex items-center gap-2">
              <Button
                size="sm"
                onClick={() => handleOpenAdd()}
                className="bg-emerald-600 hover:bg-emerald-700 text-white text-xs"
              >
                <Plus className="mr-1.5 h-3.5 w-3.5" />
                Isi Jurnal Mengajar
              </Button>
            </div>
          )}
        </div>

        {feedback && (
          <div
            className={`rounded-lg p-3 text-xs flex items-center justify-between ${
              feedback.type === 'success'
                ? 'bg-emerald-50 text-emerald-800 border border-emerald-200'
                : 'bg-red-50 text-red-800 border border-red-200'
            }`}
          >
            <span>{feedback.message}</span>
            <button
              onClick={() => setFeedback(null)}
              className="text-gray-400 hover:text-gray-600 text-xs ml-2"
            >
              Tutup
            </button>
          </div>
        )}

        {/* Tab Switcher: Riwayat Jurnal vs Sesi Hari Ini */}
        <div className="flex border-b border-gray-200">
          <button
            type="button"
            onClick={() => setActiveTab('history')}
            className={`border-b-2 px-4 py-2.5 text-xs font-semibold transition ${
              activeTab === 'history'
                ? 'border-emerald-600 text-emerald-700'
                : 'border-transparent text-gray-500 hover:text-gray-700'
            }`}
          >
            Riwayat Jurnal KBM ({journals.length})
          </button>
          <button
            type="button"
            onClick={() => setActiveTab('today')}
            className={`flex items-center gap-1.5 border-b-2 px-4 py-2.5 text-xs font-semibold transition ${
              activeTab === 'today'
                ? 'border-emerald-600 text-emerald-700'
                : 'border-transparent text-gray-500 hover:text-gray-700'
            }`}
          >
            <Sparkles className="h-3.5 w-3.5 text-amber-500" />
            <span>Sesi Mengajar Hari Ini ({DAY_OF_WEEK_LABELS[currentDayOfWeek]})</span>
            <span className="rounded-full bg-slate-100 px-1.5 py-0.2 text-[10px] text-slate-700">
              {todaySchedules.length} Sesi
            </span>
          </button>
        </div>

        {/* TAB 1: RIWAYAT JURNAL */}
        {activeTab === 'history' && (
          <div className="space-y-4">
            {/* Filter Toolbar */}
            <div className="rounded-xl border border-gray-200 bg-white p-4 shadow-xs space-y-3">
              <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex flex-wrap items-center gap-2">
                  <span className="text-xs font-semibold text-gray-600 flex items-center gap-1">
                    <Filter className="h-3.5 w-3.5" />
                    Filter Jurnal:
                  </span>

                  {/* Filter Rombel */}
                  <select
                    value={selectedClassId}
                    onChange={(e) => setSelectedClassId(e.target.value)}
                    className="rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-700 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                  >
                    <option value="">Semua Rombel / Kelas</option>
                    {classes.map((c: SchoolClassItem) => (
                      <option key={c.id} value={c.id}>
                        {c.name}
                      </option>
                    ))}
                  </select>

                  {/* Filter Guru (hanya untuk Admin/Kurikulum) */}
                  {isAdmin && (
                    <select
                      value={selectedTeacherId}
                      onChange={(e) => setSelectedTeacherId(e.target.value)}
                      className="rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-700 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                    >
                      <option value="">Semua Guru Pengajar</option>
                      {teachers.map((t: TeacherItem) => (
                        <option key={t.id} value={t.id}>
                          {t.full_name}
                        </option>
                      ))}
                    </select>
                  )}

                  {/* Filter Rentang Tanggal */}
                  <div className="flex items-center gap-1.5 text-xs text-gray-600">
                    <Input
                      type="date"
                      value={startDate}
                      onChange={(e) => setStartDate(e.target.value)}
                      className="h-8 text-xs w-36"
                      placeholder="Dari Tanggal"
                    />
                    <span>s/d</span>
                    <Input
                      type="date"
                      value={endDate}
                      onChange={(e) => setEndDate(e.target.value)}
                      className="h-8 text-xs w-36"
                      placeholder="Sampai Tanggal"
                    />
                  </div>

                  {(selectedClassId || selectedTeacherId || startDate || endDate) && (
                    <Button
                      variant="ghost"
                      size="sm"
                      onClick={() => {
                        setSelectedClassId('');
                        setSelectedTeacherId('');
                        setStartDate('');
                        setEndDate('');
                      }}
                      className="text-xs text-gray-500 hover:text-red-600 h-8 px-2"
                    >
                      Reset Filter
                    </Button>
                  )}
                </div>
              </div>
            </div>

            {/* Table */}
            {isJournalsLoading ? (
              <div className="rounded-xl border border-gray-200 bg-white p-12 text-center text-xs text-gray-500">
                Memuat riwayat jurnal mengajar...
              </div>
            ) : (
              <div className="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                <DataTable
                  data={journals}
                  columns={columns}
                  keyExtractor={(item) => item.id}
                  searchable
                  searchPlaceholder="Cari materi, topik, metode, atau guru..."
                  emptyTitle="Belum ada catatan jurnal mengajar yang sesuai."
                />
              </div>
            )}
          </div>
        )}

        {/* TAB 2: SESI JADWAL HARI INI */}
        {activeTab === 'today' && (
          <div className="space-y-4">
            <div className="rounded-lg bg-emerald-50/60 border border-emerald-200 p-3.5 text-xs text-emerald-900 flex items-start gap-2">
              <CalendarDays className="h-4 w-4 text-emerald-700 shrink-0 mt-0.5" />
              <div>
                <span className="font-semibold">Sesi KBM Hari Ini ({DAY_OF_WEEK_LABELS[currentDayOfWeek]}):</span>
                <p className="text-gray-600 mt-0.5">
                  Pilih sesi pembelajaran aktif untuk mengisi jurnal materi dan mencatat kehadiran siswa secara langsung.
                </p>
              </div>
            </div>

            {todaySchedules.length === 0 ? (
              <div className="rounded-xl border border-dashed border-gray-200 bg-white p-12 text-center">
                <Clock className="mx-auto h-10 w-10 text-gray-300" />
                <h3 className="mt-3 text-sm font-medium text-gray-900">
                  Tidak Ada Jadwal Pembelajaran Hari Ini
                </h3>
                <p className="mt-1 text-xs text-gray-500">
                  Tidak ada jadwal KBM yang terdaftar pada hari {DAY_OF_WEEK_LABELS[currentDayOfWeek]}.
                </p>
              </div>
            ) : (
              <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                {todaySchedules.map((sch) => {
                  const isFilled = todayFilledScheduleIds.has(sch.id);
                  const matchingJournal = journals.find(
                    (j) =>
                      j.schedule_id === sch.id &&
                      (typeof j.session_date === 'string' ? j.session_date.slice(0, 10) : '') === todayStr
                  );

                  return (
                    <div
                      key={sch.id}
                      className={`flex flex-col justify-between rounded-xl border p-4 transition shadow-xs ${
                        isFilled
                          ? 'border-emerald-200 bg-emerald-50/30'
                          : 'border-gray-200 bg-white hover:border-emerald-300 hover:shadow-md'
                      }`}
                    >
                      <div className="space-y-2">
                        <div className="flex items-center justify-between">
                          <span className="rounded bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-800">
                            Kelas {sch.class?.name || '-'}
                          </span>
                          <span
                            className={`rounded-full px-2 py-0.5 text-[10px] font-semibold border ${
                              isFilled
                                ? 'bg-emerald-100 text-emerald-800 border-emerald-300'
                                : 'bg-amber-50 text-amber-800 border-amber-200'
                            }`}
                          >
                            {isFilled ? 'Jurnal Sudah Diisi' : 'Belum Ada Jurnal'}
                          </span>
                        </div>

                        <div>
                          <h4 className="text-sm font-bold text-gray-900 flex items-center gap-1.5">
                            <BookOpen className="h-4 w-4 text-emerald-600 shrink-0" />
                            <span>{sch.subject?.name || 'Mata Pelajaran'}</span>
                          </h4>
                          <p className="text-xs text-gray-500 font-mono mt-0.5">
                            {sch.timeSlot?.name} ({sch.timeSlot?.start_time} - {sch.timeSlot?.end_time})
                          </p>
                        </div>

                        <div className="text-xs text-gray-600 flex items-center gap-1.5 pt-1 border-t border-gray-100">
                          <User className="h-3 w-3 text-gray-400" />
                          <span className="truncate">{sch.teacher?.full_name || '-'}</span>
                        </div>
                      </div>

                      <div className="mt-4 pt-3 border-t border-gray-100">
                        {isFilled && matchingJournal ? (
                          <Button
                            variant="outline"
                            size="sm"
                            onClick={() => handleOpenDetail(matchingJournal.id)}
                            className="w-full text-xs text-emerald-700 border-emerald-200 hover:bg-emerald-50"
                          >
                            <Eye className="mr-1.5 h-3.5 w-3.5" />
                            Lihat Jurnal Hari Ini
                          </Button>
                        ) : (
                          canWrite && (
                            <Button
                              size="sm"
                              onClick={() => handleOpenAdd(sch.id, todayStr)}
                              className="w-full bg-emerald-600 hover:bg-emerald-700 text-white text-xs"
                            >
                              <Plus className="mr-1.5 h-3.5 w-3.5" />
                              Isi Jurnal Sesi Ini
                            </Button>
                          )
                        )}
                      </div>
                    </div>
                  );
                })}
              </div>
            )}
          </div>
        )}

        {/* Modals */}
        <JournalDialog
          isOpen={isJournalDialogOpen}
          onClose={() => setIsJournalDialogOpen(false)}
          onSuccess={() => {
            queryClient.invalidateQueries({ queryKey: ['academic-journals'] });
            setFeedback({ type: 'success', message: 'Jurnal mengajar berhasil disimpan.' });
          }}
          journal={editingJournal}
          defaultScheduleId={selectedScheduleIdForAdd}
          defaultSessionDate={selectedSessionDateForAdd}
          schedules={schedules}
          isAdmin={isAdmin}
        />

        <JournalDetailDialog
          journalId={detailJournalId}
          isOpen={isDetailDialogOpen}
          onClose={() => {
            setIsDetailDialogOpen(false);
            setDetailJournalId(null);
          }}
          onEdit={(j) => handleOpenEdit(j)}
          canEdit={canWrite}
        />

        <ConfirmDialog
          isOpen={!!deletingJournal}
          onClose={() => setDeletingJournal(null)}
          onConfirm={() => {
            if (deletingJournal) {
              deleteMutation.mutate(deletingJournal.id);
            }
          }}
          title="Hapus Jurnal Mengajar"
          description={`Apakah Anda yakin ingin menghapus catatan jurnal mengajar tanggal ${
            deletingJournal ? new Date(deletingJournal.session_date).toLocaleDateString('id-ID') : ''
          } topik "${deletingJournal?.topic || ''}"? Tindakan ini akan menghapus riwayat sesi KBM.`}
          confirmLabel={deleteMutation.isPending ? 'Menghapus...' : 'Hapus Jurnal'}
          variant="danger"
        />
      </div>
    </PermissionGate>
  );
}
