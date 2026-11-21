'use client';

import React from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import * as z from 'zod';
import { useQuery } from '@tanstack/react-query';
import {
  TeachingAssignmentFormValues,
} from '@/types/master';
import { classesApi } from '@/features/master/classes-api';
import { subjectsApi } from '@/features/master/subjects-api';
import { teachersApi } from '@/features/master/teachers-api';
import { academicYearsApi } from '@/features/master/academic-years-api';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { X, BookOpen } from 'lucide-react';

export const teachingAssignmentSchema = z.object({
  semester_id: z.string().min(1, 'Semester wajib dipilih'),
  class_id: z.string().min(1, 'Kelas rombel wajib dipilih'),
  subject_id: z.string().min(1, 'Mata pelajaran wajib dipilih'),
  teacher_id: z.string().min(1, 'Guru pengampu wajib dipilih'),
  hours_per_week: z.coerce.number().min(1, 'Minimal 1 jam').max(40, 'Maksimal 40 jam').optional(),
});

export interface TeachingAssignmentDialogProps {
  isOpen: boolean;
  onClose: () => void;
  onSubmit: (data: TeachingAssignmentFormValues) => Promise<void>;
  isLoading?: boolean;
}

export function TeachingAssignmentDialog({
  isOpen,
  onClose,
  onSubmit,
  isLoading = false,
}: TeachingAssignmentDialogProps) {
  // Lookups
  const { data: activePeriod } = useQuery({
    queryKey: ['active-period-assignment'],
    queryFn: () => academicYearsApi.getActive(),
    enabled: isOpen,
  });

  const { data: classes } = useQuery({
    queryKey: ['classes-assignment-lookup'],
    queryFn: () => classesApi.list({ limit: 100 }),
    enabled: isOpen,
  });

  const { data: subjects } = useQuery({
    queryKey: ['subjects-assignment-lookup'],
    queryFn: () => subjectsApi.list({ limit: 100 }),
    enabled: isOpen,
  });

  const { data: teachers } = useQuery({
    queryKey: ['teachers-assignment-lookup'],
    queryFn: () => teachersApi.list({ limit: 100 }),
    enabled: isOpen,
  });

  const {
    register,
    handleSubmit,
    setValue,
    formState: { errors, isSubmitting },
  } = useForm<TeachingAssignmentFormValues>({
    resolver: zodResolver(teachingAssignmentSchema) as any,
    defaultValues: {
      semester_id: activePeriod?.semester?.id || '',
      class_id: '',
      subject_id: '',
      teacher_id: '',
      hours_per_week: 2,
    },
  });

  React.useEffect(() => {
    if (activePeriod?.semester?.id) {
      setValue('semester_id', activePeriod.semester.id);
    }
  }, [activePeriod, setValue]);

  if (!isOpen) return null;

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs animate-in fade-in duration-150 overflow-y-auto"
      role="dialog"
      aria-modal="true"
    >
      <div className="relative w-full max-w-lg rounded-xl bg-white p-6 shadow-elevated border border-slate-200 my-8">
        <div className="flex items-center justify-between pb-4 border-b border-slate-100">
          <div className="flex items-center gap-2">
            <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-teal-50 text-brand-teal">
              <BookOpen className="h-5 w-5" />
            </div>
            <div>
              <h2 className="text-base font-bold text-slate-900">
                Tambah Penugasan Mengajar Guru
              </h2>
              <p className="text-xs text-slate-500">
                Petakan guru pengampu untuk mata pelajaran pada rombel tertentu
              </p>
            </div>
          </div>
          <button
            type="button"
            onClick={onClose}
            className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition-colors"
          >
            <X className="h-5 w-5" />
          </button>
        </div>

        <form onSubmit={handleSubmit(onSubmit)} className="mt-4 space-y-4">
          <div>
            <label className="block text-xs font-semibold text-slate-700 mb-1">
              Semester Aktif <span className="text-status-danger">*</span>
            </label>
            <Input
              value={
                activePeriod?.semester
                  ? `${activePeriod.academicYear?.name || ''} - Semester ${activePeriod.semester.name}`
                  : 'Memuat semester aktif...'
              }
              readOnly
              className="h-9 text-xs bg-slate-50 text-slate-700"
            />
            {errors.semester_id && (
              <p className="mt-1 text-[11px] text-status-danger">{errors.semester_id.message}</p>
            )}
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label className="block text-xs font-semibold text-slate-700 mb-1">
                Rombel / Kelas <span className="text-status-danger">*</span>
              </label>
              <select
                {...register('class_id')}
                className="w-full h-9 px-3 rounded-md border border-slate-300 bg-white text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand-blue"
                disabled={isLoading || isSubmitting}
              >
                <option value="">Pilih Rombel</option>
                {classes?.data.map((c) => (
                  <option key={c.id} value={c.id}>
                    {c.name}
                  </option>
                ))}
              </select>
              {errors.class_id && (
                <p className="mt-1 text-[11px] text-status-danger">{errors.class_id.message}</p>
              )}
            </div>

            <div>
              <label className="block text-xs font-semibold text-slate-700 mb-1">
                Mata Pelajaran <span className="text-status-danger">*</span>
              </label>
              <select
                {...register('subject_id')}
                className="w-full h-9 px-3 rounded-md border border-slate-300 bg-white text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand-blue"
                disabled={isLoading || isSubmitting}
              >
                <option value="">Pilih Mata Pelajaran</option>
                {subjects?.data.map((s) => (
                  <option key={s.id} value={s.id}>
                    {s.name} ({s.code})
                  </option>
                ))}
              </select>
              {errors.subject_id && (
                <p className="mt-1 text-[11px] text-status-danger">{errors.subject_id.message}</p>
              )}
            </div>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label className="block text-xs font-semibold text-slate-700 mb-1">
                Guru Pengampu <span className="text-status-danger">*</span>
              </label>
              <select
                {...register('teacher_id')}
                className="w-full h-9 px-3 rounded-md border border-slate-300 bg-white text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand-blue"
                disabled={isLoading || isSubmitting}
              >
                <option value="">Pilih Guru</option>
                {teachers?.data.map((t) => (
                  <option key={t.id} value={t.id}>
                    {t.full_name}
                  </option>
                ))}
              </select>
              {errors.teacher_id && (
                <p className="mt-1 text-[11px] text-status-danger">{errors.teacher_id.message}</p>
              )}
            </div>

            <div>
              <label className="block text-xs font-semibold text-slate-700 mb-1">
                Jam per Minggu
              </label>
              <Input
                {...register('hours_per_week')}
                type="number"
                min={1}
                max={40}
                className="h-9 text-xs"
                disabled={isLoading || isSubmitting}
              />
              {errors.hours_per_week && (
                <p className="mt-1 text-[11px] text-status-danger">{errors.hours_per_week.message}</p>
              )}
            </div>
          </div>

          <div className="flex items-center justify-end gap-2.5 pt-4 border-t border-slate-100">
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={onClose}
              disabled={isLoading || isSubmitting}
            >
              Batal
            </Button>
            <Button
              type="submit"
              variant="default"
              size="sm"
              isLoading={isLoading || isSubmitting}
              disabled={isLoading || isSubmitting}
            >
              Simpan Penugasan
            </Button>
          </div>
        </form>
      </div>
    </div>
  );
}
