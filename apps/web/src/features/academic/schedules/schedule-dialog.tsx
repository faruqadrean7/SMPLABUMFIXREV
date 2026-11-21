'use client';

import React, { useState, useEffect, useCallback } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import {
  ScheduleItem,
  ScheduleFormValues,
  DayOfWeek,
  DAY_OF_WEEK_LABELS,
  TimeSlotItem,
  RoomItem,
  ScheduleConflict,
} from '@/types/academic';
import { SemesterItem, SchoolClassItem, SubjectItem, TeacherItem } from '@/types/master';
import { academicApi } from '../academic-api';
import { ApiClientError } from '@/lib/api/errors';
import { Button } from '@/components/ui/button';
import {
  CalendarDays,
  Clock,
  BookOpen,
  User,
  Building2,
  AlertTriangle,
  CheckCircle2,
  X,
  Layers,
} from 'lucide-react';

export const scheduleSchema = z.object({
  semester_id: z.string().uuid({ message: 'Semester wajib dipilih' }),
  class_id: z.string().uuid({ message: 'Kelas / rombel wajib dipilih' }),
  subject_id: z.string().uuid({ message: 'Mata pelajaran wajib dipilih' }),
  teacher_id: z.string().uuid({ message: 'Guru pengajar wajib dipilih' }),
  room_id: z.string().uuid().optional().or(z.literal('')),
  time_slot_id: z.string().uuid({ message: 'Slot jam pelajaran wajib dipilih' }),
  day_of_week: z.coerce
    .number()
    .min(1, { message: 'Hari KBM wajib dipilih' })
    .max(7, { message: 'Hari tidak valid' }),
});

export type ScheduleDialogFormValues = z.infer<typeof scheduleSchema>;

interface ScheduleDialogProps {
  isOpen: boolean;
  onClose: () => void;
  onSuccess: () => void;
  schedule?: ScheduleItem | null;
  defaultDayOfWeek?: DayOfWeek;
  defaultTimeSlotId?: string;
  defaultClassId?: string;
  semesters: SemesterItem[];
  classes: SchoolClassItem[];
  subjects: SubjectItem[];
  teachers: TeacherItem[];
  rooms: RoomItem[];
  timeSlots: TimeSlotItem[];
}

export function ScheduleDialog({
  isOpen,
  onClose,
  onSuccess,
  schedule,
  defaultDayOfWeek,
  defaultTimeSlotId,
  defaultClassId,
  semesters,
  classes,
  subjects,
  teachers,
  rooms,
  timeSlots,
}: ScheduleDialogProps) {
  const isEditing = !!schedule;
  const [conflictLoading, setConflictLoading] = useState(false);
  const [conflicts, setConflicts] = useState<ScheduleConflict[]>([]);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const activeSemester = semesters.find((s) => s.is_active) || semesters[0];

  const {
    register,
    handleSubmit,
    watch,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<ScheduleDialogFormValues>({
    resolver: zodResolver(scheduleSchema),
    defaultValues: {
      semester_id: activeSemester?.id || '',
      class_id: defaultClassId || classes[0]?.id || '',
      subject_id: subjects[0]?.id || '',
      teacher_id: teachers[0]?.id || '',
      room_id: '',
      time_slot_id: defaultTimeSlotId || timeSlots[0]?.id || '',
      day_of_week: defaultDayOfWeek || 1,
    },
  });

  // Watch field values for real-time conflict checking
  const watchedSemesterId = watch('semester_id');
  const watchedClassId = watch('class_id');
  const watchedTeacherId = watch('teacher_id');
  const watchedRoomId = watch('room_id');
  const watchedTimeSlotId = watch('time_slot_id');
  const watchedDayOfWeek = watch('day_of_week');

  // Reset form when dialog opens or editing schedule changes
  useEffect(() => {
    if (isOpen) {
      setConflicts([]);
      setErrorMessage(null);
      if (schedule) {
        reset({
          semester_id: schedule.semester_id,
          class_id: schedule.class_id,
          subject_id: schedule.subject_id,
          teacher_id: schedule.teacher_id,
          room_id: schedule.room_id || '',
          time_slot_id: schedule.time_slot_id,
          day_of_week: schedule.day_of_week,
        });
      } else {
        reset({
          semester_id: activeSemester?.id || '',
          class_id: defaultClassId || classes[0]?.id || '',
          subject_id: subjects[0]?.id || '',
          teacher_id: teachers[0]?.id || '',
          room_id: '',
          time_slot_id: defaultTimeSlotId || timeSlots[0]?.id || '',
          day_of_week: defaultDayOfWeek || 1,
        });
      }
    }
  }, [
    isOpen,
    schedule,
    defaultDayOfWeek,
    defaultTimeSlotId,
    defaultClassId,
    activeSemester,
    classes,
    subjects,
    teachers,
    timeSlots,
    reset,
  ]);

  // Real-time conflict checking debounce
  const checkRealTimeConflict = useCallback(async () => {
    if (
      !watchedSemesterId ||
      !watchedClassId ||
      !watchedTeacherId ||
      !watchedTimeSlotId ||
      !watchedDayOfWeek
    ) {
      setConflicts([]);
      return;
    }

    try {
      setConflictLoading(true);
      const res = await academicApi.checkConflicts({
        semester_id: watchedSemesterId,
        class_id: watchedClassId,
        teacher_id: watchedTeacherId,
        room_id: watchedRoomId || null,
        time_slot_id: watchedTimeSlotId,
        day_of_week: Number(watchedDayOfWeek) as DayOfWeek,
        exclude_schedule_id: schedule?.id,
      });

      if (res.has_conflict) {
        setConflicts(res.conflicts);
      } else {
        setConflicts([]);
      }
    } catch {
      // Ignore background check errors
    } finally {
      setConflictLoading(false);
    }
  }, [
    watchedSemesterId,
    watchedClassId,
    watchedTeacherId,
    watchedRoomId,
    watchedTimeSlotId,
    watchedDayOfWeek,
    schedule?.id,
  ]);

  useEffect(() => {
    const timer = setTimeout(() => {
      if (isOpen) {
        checkRealTimeConflict();
      }
    }, 400);

    return () => clearTimeout(timer);
  }, [checkRealTimeConflict, isOpen]);

  if (!isOpen) return null;

  const onSubmit = async (values: ScheduleDialogFormValues) => {
    setErrorMessage(null);
    try {
      const payload: ScheduleFormValues = {
        semester_id: values.semester_id,
        class_id: values.class_id,
        subject_id: values.subject_id,
        teacher_id: values.teacher_id,
        room_id: values.room_id || null,
        time_slot_id: values.time_slot_id,
        day_of_week: Number(values.day_of_week) as DayOfWeek,
      };

      if (isEditing && schedule) {
        await academicApi.updateSchedule(schedule.id, payload);
      } else {
        await academicApi.createSchedule(payload);
      }

      onSuccess();
      onClose();
    } catch (err: unknown) {
      if (err instanceof ApiClientError) {
        setErrorMessage(err.message);
      } else {
        setErrorMessage('Terjadi kesalahan saat menyimpan jadwal pelajaran.');
      }
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div className="w-full max-w-xl rounded-xl bg-white shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
        {/* Header */}
        <div className="flex items-center justify-between border-b border-gray-100 px-6 py-4 bg-slate-50/50">
          <div className="flex items-center gap-2.5">
            <div className="rounded-lg bg-emerald-50 p-2 text-emerald-600 border border-emerald-100">
              <CalendarDays className="h-5 w-5" />
            </div>
            <div>
              <h2 className="text-base font-semibold text-gray-900">
                {isEditing ? 'Perbarui Jadwal Pelajaran' : 'Tambah Jadwal Pelajaran Baru'}
              </h2>
              <p className="text-xs text-gray-500">
                Alokasi waktu KBM, guru pengajar, rombel, dan ruang kelas
              </p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition"
          >
            <X className="h-4 w-4" />
          </button>
        </div>

        {/* Form Body */}
        <form onSubmit={handleSubmit(onSubmit)} className="space-y-4 px-6 py-5 max-h-[80vh] overflow-y-auto">
          {errorMessage && (
            <div className="flex items-start gap-2 rounded-lg bg-red-50 p-3 text-xs text-red-700 border border-red-200">
              <AlertTriangle className="h-4 w-4 text-red-600 shrink-0 mt-0.5" />
              <span>{errorMessage}</span>
            </div>
          )}

          {/* Real-time Conflict Alert */}
          {conflicts.length > 0 && (
            <div className="rounded-lg bg-amber-50 p-3.5 border border-amber-300 space-y-1.5">
              <div className="flex items-center gap-1.5 text-xs font-semibold text-amber-800">
                <AlertTriangle className="h-4 w-4 text-amber-600 shrink-0" />
                Peringatan Bentrok Jadwal Terdeteksi ({conflicts.length}):
              </div>
              <ul className="list-disc list-inside text-xs text-amber-900 space-y-1 pl-1">
                {conflicts.map((c, i) => (
                  <li key={i}>{c.message}</li>
                ))}
              </ul>
              <p className="text-[11px] text-amber-700 pt-1 italic">
                * Simpan akan ditolak oleh sistem (HTTP 409) jika jadwal bentrok belum diselesaikan.
              </p>
            </div>
          )}

          {conflicts.length === 0 && !conflictLoading && (
            <div className="flex items-center gap-1.5 rounded-lg bg-emerald-50/70 p-2 text-xs text-emerald-800 border border-emerald-200">
              <CheckCircle2 className="h-3.5 w-3.5 text-emerald-600 shrink-0" />
              <span>Tidak ada bentrok jadwal (kelas, guru, dan ruangan tersedia).</span>
            </div>
          )}

          <div className="grid grid-cols-2 gap-3.5">
            {/* Semester */}
            <div>
              <label className="mb-1 block text-xs font-medium text-gray-700">
                Semester Akademik <span className="text-red-500">*</span>
              </label>
              <select
                {...register('semester_id')}
                disabled={isSubmitting}
                className="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-xs shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
              >
                <option value="">Pilih Semester...</option>
                {semesters.map((s) => (
                  <option key={s.id} value={s.id}>
                    {s.name} {s.is_active ? '(Aktif)' : ''}
                  </option>
                ))}
              </select>
              {errors.semester_id && (
                <p className="mt-1 text-[11px] text-red-500">{errors.semester_id.message}</p>
              )}
            </div>

            {/* Rombel / Kelas */}
            <div>
              <label className="mb-1 block text-xs font-medium text-gray-700">
                Rombel / Kelas <span className="text-red-500">*</span>
              </label>
              <div className="relative">
                <select
                  {...register('class_id')}
                  disabled={isSubmitting}
                  className="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-xs shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                >
                  <option value="">Pilih Kelas...</option>
                  {classes.map((c) => (
                    <option key={c.id} value={c.id}>
                      {c.name} ({c.gradeLevel?.name || 'Tingkat'})
                    </option>
                  ))}
                </select>
              </div>
              {errors.class_id && (
                <p className="mt-1 text-[11px] text-red-500">{errors.class_id.message}</p>
              )}
            </div>
          </div>

          <div className="grid grid-cols-2 gap-3.5">
            {/* Hari KBM */}
            <div>
              <label className="mb-1 block text-xs font-medium text-gray-700">
                Hari KBM <span className="text-red-500">*</span>
              </label>
              <select
                {...register('day_of_week')}
                disabled={isSubmitting}
                className="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-xs shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
              >
                {([1, 2, 3, 4, 5, 6] as DayOfWeek[]).map((day) => (
                  <option key={day} value={day}>
                    {DAY_OF_WEEK_LABELS[day]}
                  </option>
                ))}
              </select>
              {errors.day_of_week && (
                <p className="mt-1 text-[11px] text-red-500">{errors.day_of_week.message}</p>
              )}
            </div>

            {/* Slot Jam Pelajaran */}
            <div>
              <label className="mb-1 block text-xs font-medium text-gray-700">
                Slot Jam Pelajaran <span className="text-red-500">*</span>
              </label>
              <select
                {...register('time_slot_id')}
                disabled={isSubmitting}
                className="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-xs shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
              >
                <option value="">Pilih Jam Pelajaran...</option>
                {timeSlots.map((ts) => (
                  <option key={ts.id} value={ts.id}>
                    {ts.name} ({ts.start_time} - {ts.end_time})
                  </option>
                ))}
              </select>
              {errors.time_slot_id && (
                <p className="mt-1 text-[11px] text-red-500">{errors.time_slot_id.message}</p>
              )}
            </div>
          </div>

          {/* Mata Pelajaran */}
          <div>
            <label className="mb-1 block text-xs font-medium text-gray-700">
              Mata Pelajaran <span className="text-red-500">*</span>
            </label>
            <div className="relative">
              <select
                {...register('subject_id')}
                disabled={isSubmitting}
                className="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-xs shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
              >
                <option value="">Pilih Mata Pelajaran...</option>
                {subjects.map((sub) => (
                  <option key={sub.id} value={sub.id}>
                    [{sub.code}] {sub.name} ({sub.subject_group})
                  </option>
                ))}
              </select>
            </div>
            {errors.subject_id && (
              <p className="mt-1 text-[11px] text-red-500">{errors.subject_id.message}</p>
            )}
          </div>

          {/* Guru Pengajar */}
          <div>
            <label className="mb-1 block text-xs font-medium text-gray-700">
              Guru Pengajar <span className="text-red-500">*</span>
            </label>
            <select
              {...register('teacher_id')}
              disabled={isSubmitting}
              className="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-xs shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
            >
              <option value="">Pilih Guru Pengajar...</option>
              {teachers.map((t) => (
                <option key={t.id} value={t.id}>
                  {t.full_name} {t.nip ? `(NIP: ${t.nip})` : ''}
                </option>
              ))}
            </select>
            {errors.teacher_id && (
              <p className="mt-1 text-[11px] text-red-500">{errors.teacher_id.message}</p>
            )}
          </div>

          {/* Ruangan Kelas (Opsional) */}
          <div>
            <label className="mb-1 block text-xs font-medium text-gray-700">
              Ruangan Kelas (Opsional)
            </label>
            <select
              {...register('room_id')}
              disabled={isSubmitting}
              className="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-xs shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
            >
              <option value="">Ruang reguler kelas / Tidak spesifik</option>
              {rooms.map((r) => (
                <option key={r.id} value={r.id}>
                  {r.name} ({r.room_type})
                </option>
              ))}
            </select>
            <p className="mt-1 text-[11px] text-gray-400">
              Pilih jika KBM memerlukan ruangan khusus (Lab Komputer, Lab IPA, Perpustakaan, dsb).
            </p>
          </div>

          {/* Footer Buttons */}
          <div className="flex items-center justify-end gap-2.5 border-t border-gray-100 pt-4">
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={onClose}
              disabled={isSubmitting}
              className="text-xs"
            >
              Batal
            </Button>
            <Button
              type="submit"
              size="sm"
              disabled={isSubmitting || conflicts.length > 0}
              className="bg-emerald-600 hover:bg-emerald-700 text-white text-xs"
            >
              <CheckCircle2 className="mr-1.5 h-3.5 w-3.5" />
              {isSubmitting ? 'Menyimpan...' : isEditing ? 'Simpan Perubahan' : 'Buat Jadwal'}
            </Button>
          </div>
        </form>
      </div>
    </div>
  );
}
