'use client';

import React, { useState, useMemo } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { academicApi } from '@/features/academic/academic-api';
import { classesApi } from '@/features/master/classes-api';
import { teachersApi } from '@/features/master/teachers-api';
import { subjectsApi } from '@/features/master/subjects-api';
import { ScheduleDialog, TimeSlotDialog, ScheduleTimetableGrid } from '@/features/academic/schedules';
import {
  ScheduleItem,
  RoomItem,
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
import {
  CalendarDays,
  Clock,
  Plus,
  Printer,
  Grid3X3,
  List,
  Filter,
  Layers,
  User,
  Building2,
  Trash2,
  Edit2,
  BookOpen,
} from 'lucide-react';

type ViewMode = 'class' | 'teacher' | 'room';

export default function SchedulesPage() {
  const queryClient = useQueryClient();
  const { user } = useAuth();
  const canManage = hasPermission(user, 'academic.schedule.manage');

  // Filter states
  const [selectedSemesterId, setSelectedSemesterId] = useState<string>('');
  const [viewMode, setViewMode] = useState<ViewMode>('class');
  const [selectedEntityId, setSelectedEntityId] = useState<string>('');
  const [displayMode, setDisplayMode] = useState<'matrix' | 'table'>('matrix');

  // Dialog states
  const [isScheduleDialogOpen, setIsScheduleDialogOpen] = useState(false);
  const [editingSchedule, setEditingSchedule] = useState<ScheduleItem | null>(null);
  const [defaultDay, setDefaultDay] = useState<DayOfWeek | undefined>(undefined);
  const [defaultTimeSlotId, setDefaultTimeSlotId] = useState<string | undefined>(undefined);
  const [isTimeSlotDialogOpen, setIsTimeSlotDialogOpen] = useState(false);
  const [deletingSchedule, setDeletingSchedule] = useState<ScheduleItem | null>(null);

  // Notification feedback
  const [feedback, setFeedback] = useState<{ type: 'success' | 'error'; message: string } | null>(
    null
  );

  // 1. Fetch Lookups
  const { data: semesters = [] } = useQuery({
    queryKey: ['academic-semesters'],
    queryFn: () => academicApi.listSemesters(),
  });

  const activeSemester = useMemo(() => {
    return semesters.find((s) => s.is_active) || semesters[0];
  }, [semesters]);

  const effectiveSemesterId = selectedSemesterId || activeSemester?.id || '';

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

  const { data: rooms = [] } = useQuery<RoomItem[]>({
    queryKey: ['academic-rooms'],
    queryFn: () => academicApi.listRooms(),
  });

  const { data: timeSlots = [] } = useQuery({
    queryKey: ['academic-time-slots'],
    queryFn: () => academicApi.listTimeSlots(),
  });

  // Default entity selection if empty
  const effectiveEntityId = useMemo(() => {
    if (selectedEntityId) return selectedEntityId;
    if (viewMode === 'class') return classes[0]?.id || '';
    if (viewMode === 'teacher') return teachers[0]?.id || '';
    if (viewMode === 'room') return rooms[0]?.id || '';
    return '';
  }, [selectedEntityId, viewMode, classes, teachers, rooms]);

  // 2. Fetch Schedules
  const scheduleFilters = useMemo(() => {
    const filters: Record<string, string | number | boolean> = {
      all: true,
    };
    if (effectiveSemesterId) filters.semester_id = effectiveSemesterId;
    if (viewMode === 'class' && effectiveEntityId) filters.class_id = effectiveEntityId;
    if (viewMode === 'teacher' && effectiveEntityId) filters.teacher_id = effectiveEntityId;
    if (viewMode === 'room' && effectiveEntityId) filters.room_id = effectiveEntityId;
    return filters;
  }, [effectiveSemesterId, viewMode, effectiveEntityId]);

  const {
    data: schedules = [],
    isLoading: isSchedulesLoading,
  } = useQuery({
    queryKey: ['academic-schedules', scheduleFilters],
    queryFn: () => academicApi.listSchedules(scheduleFilters),
    enabled: !!effectiveSemesterId,
  });

  // 3. Delete Schedule Mutation
  const deleteMutation = useMutation({
    mutationFn: (id: string) => academicApi.deleteSchedule(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['academic-schedules'] });
      setDeletingSchedule(null);
      setFeedback({ type: 'success', message: 'Jadwal pelajaran berhasil dihapus.' });
    },
    onError: (err: any) => {
      const msg = err?.message || 'Gagal menghapus jadwal pelajaran.';
      setFeedback({ type: 'error', message: msg });
    },
  });

  // Handlers
  const handleOpenAdd = (day?: DayOfWeek, slotId?: string) => {
    setEditingSchedule(null);
    setDefaultDay(day);
    setDefaultTimeSlotId(slotId);
    setIsScheduleDialogOpen(true);
  };

  const handleOpenEdit = (schedule: ScheduleItem) => {
    setEditingSchedule(schedule);
    setDefaultDay(schedule.day_of_week);
    setDefaultTimeSlotId(schedule.time_slot_id);
    setIsScheduleDialogOpen(true);
  };

  // View title
  const currentViewTitle = useMemo(() => {
    if (viewMode === 'class') {
      const c = classes.find((cl: SchoolClassItem) => cl.id === effectiveEntityId);
      return c ? `Kelas ${c.name}` : 'Semua Rombel';
    }
    if (viewMode === 'teacher') {
      const t = teachers.find((tc: TeacherItem) => tc.id === effectiveEntityId);
      return t ? `Guru: ${t.full_name}` : 'Semua Guru';
    }
    if (viewMode === 'room') {
      const r = rooms.find((rm) => rm.id === effectiveEntityId);
      return r ? `Ruangan: ${r.name}` : 'Semua Ruangan';
    }
    return '';
  }, [viewMode, effectiveEntityId, classes, teachers, rooms]);

  // Columns for DataTable
  const columns: ColumnDef<ScheduleItem>[] = [
    {
      key: 'day_of_week',
      header: 'Hari',
      render: (item: ScheduleItem) => (
        <span className="font-semibold text-gray-900">
          {DAY_OF_WEEK_LABELS[item.day_of_week] || `Hari ${item.day_of_week}`}
        </span>
      ),
    },
    {
      key: 'time_slot_id',
      header: 'Jam Pelajaran',
      render: (item: ScheduleItem) => (
        <div>
          <div className="font-medium text-gray-900">{item.timeSlot?.name || 'Slot'}</div>
          <div className="text-[11px] font-mono text-gray-500">
            {item.timeSlot?.start_time} - {item.timeSlot?.end_time}
          </div>
        </div>
      ),
    },
    {
      key: 'class_id',
      header: 'Rombel',
      render: (item: ScheduleItem) => (
        <span className="inline-flex items-center gap-1 rounded bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-800">
          <Layers className="h-3 w-3 text-slate-500" />
          {item.class?.name || '-'}
        </span>
      ),
    },
    {
      key: 'subject_id',
      header: 'Mata Pelajaran',
      render: (item: ScheduleItem) => (
        <div>
          <div className="font-semibold text-gray-900 flex items-center gap-1">
            <BookOpen className="h-3.5 w-3.5 text-emerald-600" />
            {item.subject?.name || '-'}
          </div>
          <div className="text-[11px] text-gray-500 font-mono">Kode: {item.subject?.code}</div>
        </div>
      ),
    },
    {
      key: 'teacher_id',
      header: 'Guru Pengajar',
      render: (item: ScheduleItem) => (
        <div className="flex items-center gap-1 text-gray-700">
          <User className="h-3.5 w-3.5 text-gray-400" />
          <span>{item.teacher?.full_name || '-'}</span>
        </div>
      ),
    },
    {
      key: 'room_id',
      header: 'Ruang Kelas',
      render: (item: ScheduleItem) =>
        item.room ? (
          <span className="inline-flex items-center gap-1 rounded bg-amber-50 px-2 py-0.5 text-xs text-amber-800 border border-amber-200">
            <Building2 className="h-3 w-3 text-amber-600" />
            {item.room.name}
          </span>
        ) : (
          <span className="text-gray-400 italic text-xs">Ruang Kelas Reguler</span>
        ),
    },
    {
      key: 'actions',
      header: 'Aksi',
      render: (item: ScheduleItem) =>
        canManage ? (
          <div className="flex items-center gap-1">
            <Button
              variant="outline"
              size="sm"
              onClick={() => handleOpenEdit(item)}
              className="h-7 px-2 text-xs text-gray-700 hover:text-emerald-700"
              title="Edit Jadwal"
            >
              <Edit2 className="h-3 w-3" />
            </Button>
            <Button
              variant="outline"
              size="sm"
              onClick={() => setDeletingSchedule(item)}
              className="h-7 px-2 text-xs text-red-600 hover:bg-red-50 hover:text-red-700 border-red-200"
              title="Hapus Jadwal"
            >
              <Trash2 className="h-3 w-3" />
            </Button>
          </div>
        ) : null,
    },
  ];

  return (
    <PermissionGate
      permission={['academic.schedule.manage', 'academic.schedule.read']}
      fallback={
        <div className="p-8 text-center text-sm text-gray-500">
          Anda tidak memiliki hak akses untuk membuka modul Jadwal Pelajaran KBM.
        </div>
      }
    >
      <div className="space-y-6 print:space-y-2">
        {/* Top Header - Hidden when printing */}
        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between print:hidden">
          <div>
            <div className="flex items-center gap-2">
              <div className="rounded-lg bg-emerald-50 p-2 text-emerald-600 border border-emerald-100">
                <CalendarDays className="h-6 w-6" />
              </div>
              <div>
                <h1 className="text-xl font-bold tracking-tight text-gray-900">
                  Jadwal Pelajaran KBM
                </h1>
                <p className="text-xs text-gray-500">
                  Manajemen matriks jadwal, deteksi bentrok (guru, ruang, rombel), dan ekspor jadwal
                </p>
              </div>
            </div>
          </div>

          <div className="flex items-center gap-2">
            <Button
              variant="outline"
              size="sm"
              onClick={() => window.print()}
              className="text-xs text-gray-700 hover:bg-gray-50"
            >
              <Printer className="mr-1.5 h-3.5 w-3.5" />
              Cetak Jadwal
            </Button>

            {canManage && (
              <>
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => setIsTimeSlotDialogOpen(true)}
                  className="text-xs text-emerald-700 border-emerald-200 hover:bg-emerald-50"
                >
                  <Clock className="mr-1.5 h-3.5 w-3.5" />
                  Jam Pelajaran
                </Button>

                <Button
                  size="sm"
                  onClick={() => handleOpenAdd()}
                  className="bg-emerald-600 hover:bg-emerald-700 text-white text-xs"
                >
                  <Plus className="mr-1.5 h-3.5 w-3.5" />
                  Tambah Jadwal
                </Button>
              </>
            )}
          </div>
        </div>

        {feedback && (
          <div
            className={`rounded-lg p-3 text-xs flex items-center justify-between print:hidden ${
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

        {/* Filter Toolbar - Hidden when printing */}
        <div className="rounded-xl border border-gray-200 bg-white p-4 shadow-xs print:hidden space-y-3">
          <div className="flex flex-wrap items-center justify-between gap-3">
            {/* View Mode & Pivot Selectors */}
            <div className="flex flex-wrap items-center gap-2">
              <span className="text-xs font-semibold text-gray-600 flex items-center gap-1">
                <Filter className="h-3.5 w-3.5" />
                Filter Tampilan:
              </span>

              {/* Semester */}
              <select
                value={effectiveSemesterId}
                onChange={(e) => setSelectedSemesterId(e.target.value)}
                className="rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-700 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
              >
                {semesters.map((s) => (
                  <option key={s.id} value={s.id}>
                    Semester: {s.name} {s.is_active ? '(Aktif)' : ''}
                  </option>
                ))}
              </select>

              {/* View Mode Toggle */}
              <div className="flex rounded-lg border border-gray-200 bg-slate-50 p-0.5">
                <button
                  type="button"
                  onClick={() => {
                    setViewMode('class');
                    setSelectedEntityId('');
                  }}
                  className={`rounded-md px-2.5 py-1 text-xs font-medium transition ${
                    viewMode === 'class'
                      ? 'bg-white text-emerald-700 shadow-xs'
                      : 'text-gray-600 hover:text-gray-900'
                  }`}
                >
                  Per Kelas
                </button>
                <button
                  type="button"
                  onClick={() => {
                    setViewMode('teacher');
                    setSelectedEntityId('');
                  }}
                  className={`rounded-md px-2.5 py-1 text-xs font-medium transition ${
                    viewMode === 'teacher'
                      ? 'bg-white text-emerald-700 shadow-xs'
                      : 'text-gray-600 hover:text-gray-900'
                  }`}
                >
                  Per Guru
                </button>
                <button
                  type="button"
                  onClick={() => {
                    setViewMode('room');
                    setSelectedEntityId('');
                  }}
                  className={`rounded-md px-2.5 py-1 text-xs font-medium transition ${
                    viewMode === 'room'
                      ? 'bg-white text-emerald-700 shadow-xs'
                      : 'text-gray-600 hover:text-gray-900'
                  }`}
                >
                  Per Ruangan
                </button>
              </div>

              {/* Entity Dropdown based on viewMode */}
              {viewMode === 'class' && (
                <select
                  value={effectiveEntityId}
                  onChange={(e) => setSelectedEntityId(e.target.value)}
                  className="rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-800 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                >
                  {classes.map((c: SchoolClassItem) => (
                    <option key={c.id} value={c.id}>
                      Rombel: {c.name}
                    </option>
                  ))}
                </select>
              )}

              {viewMode === 'teacher' && (
                <select
                  value={effectiveEntityId}
                  onChange={(e) => setSelectedEntityId(e.target.value)}
                  className="rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-800 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                >
                  {teachers.map((t: TeacherItem) => (
                    <option key={t.id} value={t.id}>
                      Guru: {t.full_name}
                    </option>
                  ))}
                </select>
              )}

              {viewMode === 'room' && (
                <select
                  value={effectiveEntityId}
                  onChange={(e) => setSelectedEntityId(e.target.value)}
                  className="rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-800 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                >
                  <option value="">Semua Ruangan Terpilih</option>
                  {rooms.map((r) => (
                    <option key={r.id} value={r.id}>
                      Ruang: {r.name}
                    </option>
                  ))}
                </select>
              )}
            </div>

            {/* Display Mode: Matriks vs Table */}
            <div className="flex items-center gap-1 border-l border-gray-200 pl-3">
              <Button
                variant={displayMode === 'matrix' ? 'default' : 'ghost'}
                size="sm"
                onClick={() => setDisplayMode('matrix')}
                className={`h-7 px-2 text-xs ${
                  displayMode === 'matrix' ? 'bg-emerald-600 text-white' : 'text-gray-600'
                }`}
                title="Tampilan Matriks Timetable"
              >
                <Grid3X3 className="mr-1 h-3.5 w-3.5" />
                Matriks
              </Button>
              <Button
                variant={displayMode === 'table' ? 'default' : 'ghost'}
                size="sm"
                onClick={() => setDisplayMode('table')}
                className={`h-7 px-2 text-xs ${
                  displayMode === 'table' ? 'bg-emerald-600 text-white' : 'text-gray-600'
                }`}
                title="Tampilan Daftar Tabel"
              >
                <List className="mr-1 h-3.5 w-3.5" />
                Daftar Tabel
              </Button>
            </div>
          </div>
        </div>

        {/* Content Section */}
        {isSchedulesLoading ? (
          <div className="rounded-xl border border-gray-200 bg-white p-12 text-center text-xs text-gray-500">
            Memuat data jadwal pelajaran...
          </div>
        ) : displayMode === 'matrix' ? (
          <ScheduleTimetableGrid
            schedules={schedules}
            timeSlots={timeSlots}
            selectedSemesterName={activeSemester?.name}
            viewTitle={currentViewTitle}
            onAddScheduleSlot={(day, slotId) => handleOpenAdd(day, slotId)}
            onEditSchedule={handleOpenEdit}
            onDeleteSchedule={(sch) => setDeletingSchedule(sch)}
            canManage={canManage}
          />
        ) : (
          <div className="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
            <DataTable
              data={schedules}
              columns={columns}
              keyExtractor={(item) => item.id}
              searchable
              searchPlaceholder="Cari mata pelajaran, guru, kelas..."
              emptyTitle="Belum ada jadwal pelajaran yang sesuai dengan kriteria filter."
            />
          </div>
        )}

        {/* Modals */}
        <ScheduleDialog
          isOpen={isScheduleDialogOpen}
          onClose={() => setIsScheduleDialogOpen(false)}
          onSuccess={() => {
            queryClient.invalidateQueries({ queryKey: ['academic-schedules'] });
            setFeedback({ type: 'success', message: 'Jadwal pelajaran berhasil disimpan.' });
          }}
          schedule={editingSchedule}
          defaultDayOfWeek={defaultDay}
          defaultTimeSlotId={defaultTimeSlotId}
          defaultClassId={viewMode === 'class' ? effectiveEntityId : undefined}
          semesters={semesters}
          classes={classes}
          subjects={subjects}
          teachers={teachers}
          rooms={rooms}
          timeSlots={timeSlots}
        />

        <TimeSlotDialog
          isOpen={isTimeSlotDialogOpen}
          onClose={() => setIsTimeSlotDialogOpen(false)}
          onSuccess={() => {
            queryClient.invalidateQueries({ queryKey: ['academic-time-slots'] });
            setFeedback({ type: 'success', message: 'Slot jam pelajaran baru berhasil dibuat.' });
          }}
        />

        <ConfirmDialog
          isOpen={!!deletingSchedule}
          onClose={() => setDeletingSchedule(null)}
          onConfirm={() => {
            if (deletingSchedule) {
              deleteMutation.mutate(deletingSchedule.id);
            }
          }}
          title="Hapus Jadwal Pelajaran"
          description={`Apakah Anda yakin ingin menghapus jadwal mata pelajaran ${
            deletingSchedule?.subject?.name || ''
          } pada hari ${
            deletingSchedule ? DAY_OF_WEEK_LABELS[deletingSchedule.day_of_week] : ''
          }? Jadwal yang sudah memiliki catatan jurnal mengajar tidak dapat dihapus.`}
          confirmLabel={deleteMutation.isPending ? 'Menghapus...' : 'Hapus Jadwal'}
          variant="danger"
        />
      </div>
    </PermissionGate>
  );
}
