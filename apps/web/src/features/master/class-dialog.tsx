'use client';

import React, { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import * as z from 'zod';
import { useQuery } from '@tanstack/react-query';
import {
  ClassFormValues,
  SchoolClassItem,
  AcademicYearItem,
  GradeLevelItem,
  MajorItem,
  TeacherItem,
} from '@/types/master';
import { classesApi } from '@/features/master/classes-api';
import { academicYearsApi } from '@/features/master/academic-years-api';
import { teachersApi } from '@/features/master/teachers-api';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { X, Layers, Save } from 'lucide-react';

export const classSchema = z.object({
  academic_year_id: z.string().min(1, 'Tahun ajaran wajib dipilih'),
  grade_level_id: z.string().min(1, 'Tingkat kelas wajib dipilih'),
  major_id: z.string().optional().or(z.literal('')),
  homeroom_teacher_id: z.string().optional().or(z.literal('')),
  name: z.string().min(1, 'Nama rombel/kelas wajib diisi').max(100, 'Maksimal 100 karakter'),
  capacity: z.coerce.number().min(1, 'Minimal kapasitas 1').max(100, 'Maksimal kapasitas 100'),
  program_type: z.enum(['reguler', 'tahfidz', 'icp', 'inklusi']).optional(),
});

export interface ClassDialogProps {
  isOpen: boolean;
  onClose: () => void;
  onSubmit: (data: ClassFormValues) => Promise<void>;
  initialData?: SchoolClassItem | null;
  isLoading?: boolean;
  error?: string | null;
}

export function ClassDialog({
  isOpen,
  onClose,
  onSubmit,
  initialData,
  isLoading = false,
  error,
}: ClassDialogProps) {
  const isEditing = !!initialData;

  // Lookups
  const { data: academicYears } = useQuery({
    queryKey: ['academic-years-lookup'],
    queryFn: () => academicYearsApi.list({ limit: 50 }),
    enabled: isOpen,
  });

  const { data: gradeLevels } = useQuery({
    queryKey: ['grade-levels-lookup'],
    queryFn: () => classesApi.getGradeLevels(),
    enabled: isOpen,
  });

  const { data: majors } = useQuery({
    queryKey: ['majors-lookup'],
    queryFn: () => classesApi.getMajors(),
    enabled: isOpen,
  });

  const { data: teachers } = useQuery({
    queryKey: ['teachers-lookup'],
    queryFn: () => teachersApi.list({ limit: 100 }),
    enabled: isOpen,
  });

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<ClassFormValues>({
    resolver: zodResolver(classSchema) as any,
    defaultValues: {
      academic_year_id: '',
      grade_level_id: '',
      major_id: '',
      homeroom_teacher_id: '',
      name: '',
      capacity: 36, // Standard default invariant Labschool UM
      program_type: 'reguler',
    },
  });

  useEffect(() => {
    if (initialData) {
      reset({
        academic_year_id: initialData.academic_year_id || (initialData as any).academic_year?.id || '',
        grade_level_id:
          initialData.grade_level_id ||
          (initialData as any).grade_level?.id ||
          (initialData as any).gradeLevel?.id ||
          '',
        major_id: initialData.major_id || (initialData as any).major?.id || '',
        homeroom_teacher_id:
          initialData.homeroom_teacher_id ||
          (initialData as any).homeroom_teacher?.id ||
          (initialData as any).homeroomTeacher?.id ||
          '',
        name: initialData.name || '',
        capacity: initialData.capacity || 36,
        program_type: initialData.program_type || 'reguler',
      });
    } else {
      // Find active academic year for default if available
      const activeAY = academicYears?.data.find((ay) => ay.is_active);
      reset({
        academic_year_id: activeAY?.id || '',
        grade_level_id: '',
        major_id: '',
        homeroom_teacher_id: '',
        name: '',
        capacity: 36,
        program_type: 'reguler',
      });
    }
  }, [initialData, reset, isOpen, academicYears, gradeLevels, majors, teachers]);

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
            <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-50 text-brand-blue">
              {isEditing ? <Save className="h-5 w-5" /> : <Layers className="h-5 w-5" />}
            </div>
            <div>
              <h2 className="text-base font-bold text-slate-900">
                {isEditing ? 'Perbarui Rombel' : 'Tambah Rombongan Belajar (Rombel)'}
              </h2>
              <p className="text-xs text-slate-500">
                {isEditing
                  ? `Mengubah kelas ${initialData?.name}`
                  : 'Tetapkan tingkat, jurusan, dan wali kelas'}
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

        {error && (
          <div className="mt-4 p-3 rounded-lg bg-red-50 border border-red-200 text-status-danger text-xs">
            {error}
          </div>
        )}

        <form onSubmit={handleSubmit(onSubmit)} className="mt-4 space-y-4">
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label className="block text-xs font-semibold text-slate-700 mb-1">
                Tahun Ajaran <span className="text-status-danger">*</span>
              </label>
              <select
                {...register('academic_year_id')}
                className="w-full h-9 px-3 rounded-md border border-slate-300 bg-white text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand-blue"
                disabled={isLoading || isSubmitting}
              >
                <option value="">Pilih Tahun Ajaran</option>
                {academicYears?.data.map((ay) => (
                  <option key={ay.id} value={ay.id}>
                    {ay.name} {ay.is_active ? '(Aktif)' : ''}
                  </option>
                ))}
              </select>
              {errors.academic_year_id && (
                <p className="mt-1 text-[11px] text-status-danger">
                  {errors.academic_year_id.message}
                </p>
              )}
            </div>

            <div>
              <label className="block text-xs font-semibold text-slate-700 mb-1">
                Tingkat Kelas <span className="text-status-danger">*</span>
              </label>
              <select
                {...register('grade_level_id')}
                className="w-full h-9 px-3 rounded-md border border-slate-300 bg-white text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand-blue"
                disabled={isLoading || isSubmitting}
              >
                <option value="">Pilih Tingkat</option>
                {gradeLevels?.map((gl) => (
                  <option key={gl.id} value={gl.id}>
                    {gl.name} {gl.level ? `(Tingkat ${gl.level})` : ''}
                  </option>
                ))}
              </select>
              {errors.grade_level_id && (
                <p className="mt-1 text-[11px] text-status-danger">
                  {errors.grade_level_id.message}
                </p>
              )}
            </div>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label className="block text-xs font-semibold text-slate-700 mb-1">
                Nama Rombel / Kelas <span className="text-status-danger">*</span>
              </label>
              <Input
                {...register('name')}
                placeholder="Contoh: VII-A / X-MIPA-1"
                className="h-9 text-xs font-semibold"
                disabled={isLoading || isSubmitting}
              />
              {errors.name && (
                <p className="mt-1 text-[11px] text-status-danger">{errors.name.message}</p>
              )}
            </div>

            <div>
              <label className="block text-xs font-semibold text-slate-700 mb-1">
                Kapasitas Siswa (Maks. 36) <span className="text-status-danger">*</span>
              </label>
              <Input
                {...register('capacity')}
                type="number"
                min={1}
                max={100}
                className="h-9 text-xs"
                disabled={isLoading || isSubmitting}
              />
              {errors.capacity && (
                <p className="mt-1 text-[11px] text-status-danger">{errors.capacity.message}</p>
              )}
            </div>
          </div>

          <div>
            <label className="block text-xs font-semibold text-slate-700 mb-1">
              Program Belajar Rombel
            </label>
            <select
              {...register('program_type')}
              className="w-full h-9 px-3 rounded-md border border-slate-300 bg-white text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand-blue"
              disabled={isLoading || isSubmitting}
            >
              <option value="reguler">Reguler</option>
              <option value="tahfidz">Tahfidz Al-Qur&apos;an</option>
              <option value="icp">International Class Program (Cambridge)</option>
              <option value="inklusi">Inklusi (ABK)</option>
            </select>
            <p className="mt-1 text-[11px] text-slate-400">
              Dipakai sebagai fallback program bagi siswa di rombel ini.
            </p>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label className="block text-xs font-semibold text-slate-700 mb-1">
                Jurusan (SMA/SMK)
              </label>
              <select
                {...register('major_id')}
                className="w-full h-9 px-3 rounded-md border border-slate-300 bg-white text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand-blue"
                disabled={isLoading || isSubmitting}
              >
                <option value="">Umum / Tanpa Jurusan</option>
                {majors?.map((m) => (
                  <option key={m.id} value={m.id}>
                    {m.name} ({m.code})
                  </option>
                ))}
              </select>
            </div>

            <div>
              <label className="block text-xs font-semibold text-slate-700 mb-1">
                Wali Kelas
              </label>
              <select
                {...register('homeroom_teacher_id')}
                className="w-full h-9 px-3 rounded-md border border-slate-300 bg-white text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand-blue"
                disabled={isLoading || isSubmitting}
              >
                <option value="">Belum Ditentukan</option>
                {teachers?.data.map((t) => (
                  <option key={t.id} value={t.id}>
                    {t.full_name} {t.nip ? `(${t.nip})` : ''}
                  </option>
                ))}
              </select>
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
              {isEditing ? 'Simpan Perubahan' : 'Buat Rombel'}
            </Button>
          </div>
        </form>
      </div>
    </div>
  );
}
