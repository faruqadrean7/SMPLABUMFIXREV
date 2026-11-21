'use client';

import React, { useEffect, useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { JournalItem, JournalFormValues, ScheduleItem, DAY_OF_WEEK_LABELS } from '@/types/academic';
import { academicApi } from '../academic-api';
import { ApiClientError } from '@/lib/api/errors';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
  BookMarked,
  Calendar,
  Layers,
  BookOpen,
  User,
  Clock,
  Building2,
  AlertTriangle,
  CheckCircle2,
  X,
  FileText,
} from 'lucide-react';

export const journalSchema = z.object({
  schedule_id: z.string().uuid({ message: 'Sesi jadwal pelajaran wajib dipilih' }),
  session_date: z
    .string()
    .regex(/^\d{4}-\d{2}-\d{2}$/, { message: 'Format tanggal harus YYYY-MM-DD' }),
  topic: z
    .string()
    .min(2, { message: 'Materi / topik pembelajaran minimal 2 karakter' })
    .max(255, { message: 'Materi / topik maksimal 255 karakter' }),
  method: z
    .string()
    .min(2, { message: 'Metode pembelajaran minimal 2 karakter (contoh: Diskusi Kelompok)' })
    .max(150, { message: 'Metode pembelajaran maksimal 150 karakter' }),
  notes: z.string().max(2000, { message: 'Catatan maksimal 2000 karakter' }).optional().or(z.literal('')),
});

export type JournalDialogFormValues = z.infer<typeof journalSchema>;

interface JournalDialogProps {
  isOpen: boolean;
  onClose: () => void;
  onSuccess: () => void;
  journal?: JournalItem | null;
  defaultScheduleId?: string;
  defaultSessionDate?: string;
  schedules: ScheduleItem[];
  isAdmin?: boolean;
}

export function JournalDialog({
  isOpen,
  onClose,
  onSuccess,
  journal,
  defaultScheduleId,
  defaultSessionDate,
  schedules,
  isAdmin = false,
}: JournalDialogProps) {
  const isEditing = !!journal;
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const todayStr = new Date().toISOString().slice(0, 10);

  const {
    register,
    handleSubmit,
    watch,
    reset,
    setValue,
    formState: { errors, isSubmitting },
  } = useForm<JournalDialogFormValues>({
    resolver: zodResolver(journalSchema),
    defaultValues: {
      schedule_id: defaultScheduleId || schedules[0]?.id || '',
      session_date: defaultSessionDate || todayStr,
      topic: '',
      method: '',
      notes: '',
    },
  });

  const selectedScheduleId = watch('schedule_id');
  const selectedSchedule = schedules.find((s) => s.id === selectedScheduleId);

  useEffect(() => {
    if (isOpen) {
      setErrorMessage(null);
      if (journal) {
        reset({
          schedule_id: journal.schedule_id,
          session_date: typeof journal.session_date === 'string' ? journal.session_date.slice(0, 10) : todayStr,
          topic: journal.topic,
          method: journal.method,
          notes: journal.notes || '',
        });
      } else {
        reset({
          schedule_id: defaultScheduleId || schedules[0]?.id || '',
          session_date: defaultSessionDate || todayStr,
          topic: '',
          method: '',
          notes: '',
        });
      }
    }
  }, [isOpen, journal, defaultScheduleId, defaultSessionDate, schedules, reset, todayStr]);

  if (!isOpen) return null;

  const onSubmit = async (values: JournalDialogFormValues) => {
    setErrorMessage(null);
    try {
      const payload: JournalFormValues = {
        schedule_id: values.schedule_id,
        session_date: values.session_date,
        topic: values.topic.trim(),
        method: values.method.trim(),
        notes: values.notes ? values.notes.trim() : null,
      };

      if (isEditing && journal) {
        await academicApi.updateJournal(journal.id, payload);
      } else {
        await academicApi.createJournal(payload);
      }

      onSuccess();
      onClose();
    } catch (err: unknown) {
      if (err instanceof ApiClientError) {
        setErrorMessage(err.message);
      } else {
        setErrorMessage('Terjadi kesalahan saat menyimpan jurnal mengajar.');
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
              <BookMarked className="h-5 w-5" />
            </div>
            <div>
              <h2 className="text-base font-semibold text-gray-900">
                {isEditing ? 'Perbarui Jurnal Mengajar' : 'Isi Jurnal Mengajar KBM'}
              </h2>
              <p className="text-xs text-gray-500">
                Dokumentasikan materi, metode pembelajaran, dan catatan sesi pertemuan
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

          {/* Sesi Jadwal Pelajaran */}
          <div>
            <label className="mb-1 block text-xs font-medium text-gray-700">
              Sesi Jadwal KBM <span className="text-red-500">*</span>
            </label>
            <select
              {...register('schedule_id')}
              disabled={isSubmitting || isEditing}
              className="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-xs shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
            >
              <option value="">Pilih Jadwal Pertemuan...</option>
              {schedules.map((sch) => {
                const dayLabel = DAY_OF_WEEK_LABELS[sch.day_of_week] || `Hari ${sch.day_of_week}`;
                const className = sch.class?.name || 'Kelas';
                const subjectName = sch.subject?.name || 'Mapel';
                const teacherName = sch.teacher?.full_name || '';
                const slotTime = sch.timeSlot ? `(${sch.timeSlot.start_time}-${sch.timeSlot.end_time})` : '';

                return (
                  <option key={sch.id} value={sch.id}>
                    {dayLabel} • {className} • {subjectName} {slotTime} - {teacherName}
                  </option>
                );
              })}
            </select>
            {errors.schedule_id && (
              <p className="mt-1 text-[11px] text-red-500">{errors.schedule_id.message}</p>
            )}
          </div>

          {/* Schedule Session Preview Card */}
          {selectedSchedule && (
            <div className="rounded-lg bg-slate-50 border border-slate-200 p-3 text-xs space-y-1.5">
              <div className="font-semibold text-gray-800 flex items-center justify-between">
                <span className="flex items-center gap-1.5 text-emerald-800">
                  <BookOpen className="h-3.5 w-3.5 text-emerald-600" />
                  {selectedSchedule.subject?.name} ({selectedSchedule.subject?.code})
                </span>
                <span className="inline-flex items-center gap-1 rounded bg-white px-2 py-0.5 font-mono text-[10px] text-gray-600 border border-gray-200">
                  <Layers className="h-3 w-3" />
                  Kelas: {selectedSchedule.class?.name}
                </span>
              </div>
              <div className="flex flex-wrap items-center gap-3 text-[11px] text-gray-600 pt-0.5">
                <span className="flex items-center gap-1">
                  <User className="h-3 w-3 text-gray-400" />
                  Guru: {selectedSchedule.teacher?.full_name}
                </span>
                <span className="flex items-center gap-1">
                  <Clock className="h-3 w-3 text-gray-400" />
                  {selectedSchedule.timeSlot?.name} ({selectedSchedule.timeSlot?.start_time} - {selectedSchedule.timeSlot?.end_time})
                </span>
                {selectedSchedule.room && (
                  <span className="flex items-center gap-1">
                    <Building2 className="h-3 w-3 text-gray-400" />
                    Ruang: {selectedSchedule.room.name}
                  </span>
                )}
              </div>
            </div>
          )}

          {/* Tanggal Pertemuan Sesi */}
          <div>
            <label className="mb-1 block text-xs font-medium text-gray-700">
              Tanggal Pertemuan Sesi <span className="text-red-500">*</span>
            </label>
            <div className="relative">
              <Input
                type="date"
                {...register('session_date')}
                disabled={isSubmitting}
                className="text-xs"
              />
            </div>
            {errors.session_date && (
              <p className="mt-1 text-[11px] text-red-500">{errors.session_date.message}</p>
            )}
            <p className="mt-1 text-[11px] text-gray-500">
              * Guru hanya dapat mengubah/menghapus jurnal dalam kurun waktu 3 hari kalender setelah sesi.
            </p>
          </div>

          {/* Materi / Topik Pembelajaran */}
          <div>
            <label className="mb-1 block text-xs font-medium text-gray-700">
              Materi / Topik Pembelajaran <span className="text-red-500">*</span>
            </label>
            <Input
              {...register('topic')}
              placeholder="Contoh: Sistem Persamaan Linier Tiga Variabel (SPLTV)"
              disabled={isSubmitting}
              className="text-xs"
            />
            {errors.topic && (
              <p className="mt-1 text-[11px] text-red-500">{errors.topic.message}</p>
            )}
          </div>

          {/* Metode Pembelajaran */}
          <div>
            <label className="mb-1 block text-xs font-medium text-gray-700">
              Metode Pembelajaran <span className="text-red-500">*</span>
            </label>
            <Input
              {...register('method')}
              placeholder="Contoh: Problem Based Learning, Diskusi Kelompok, Ceramah & Praktik"
              disabled={isSubmitting}
              className="text-xs"
            />
            {errors.method && (
              <p className="mt-1 text-[11px] text-red-500">{errors.method.message}</p>
            )}
          </div>

          {/* Catatan Tambahan KBM */}
          <div>
            <label className="mb-1 block text-xs font-medium text-gray-700">
              Catatan KBM & Perkembangan Siswa (Opsional)
            </label>
            <textarea
              {...register('notes')}
              rows={3}
              placeholder="Tuliskan catatan kemajuan materi, kendala siswa, atau pekerjaan rumah..."
              disabled={isSubmitting}
              className="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-xs shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
            />
            {errors.notes && (
              <p className="mt-1 text-[11px] text-red-500">{errors.notes.message}</p>
            )}
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
              disabled={isSubmitting}
              className="bg-emerald-600 hover:bg-emerald-700 text-white text-xs"
            >
              <CheckCircle2 className="mr-1.5 h-3.5 w-3.5" />
              {isSubmitting ? 'Menyimpan...' : isEditing ? 'Simpan Perubahan' : 'Simpan Jurnal'}
            </Button>
          </div>
        </form>
      </div>
    </div>
  );
}
