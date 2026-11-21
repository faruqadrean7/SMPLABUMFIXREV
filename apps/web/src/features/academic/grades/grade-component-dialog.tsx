'use client';

import React, { useEffect, useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { GradeComponentItem } from '@/types/academic';
import { SemesterItem, SubjectItem, SchoolClassItem } from '@/types/master';
import { academicApi } from '../academic-api';
import { ApiClientError } from '@/lib/api/errors';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Award, X, AlertTriangle, Loader2 } from 'lucide-react';

export const gradeComponentSchema = z.object({
  code: z
    .string()
    .min(1, { message: 'Kode komponen wajib diisi (contoh: UH1, TUGAS, PTS)' })
    .max(30, { message: 'Kode komponen maksimal 30 karakter' }),
  name: z
    .string()
    .min(2, { message: 'Nama komponen penilaian minimal 2 karakter' })
    .max(100, { message: 'Nama komponen maksimal 100 karakter' }),
  weight: z
    .number({ invalid_type_error: 'Bobot nilai harus berupa angka' })
    .min(0.1, { message: 'Bobot nilai minimal 0.1' })
    .max(100, { message: 'Bobot nilai maksimal 100' }),
  subject_id: z.string().min(1, { message: 'Mata pelajaran wajib dipilih' }),
  semester_id: z.string().min(1, { message: 'Semester wajib dipilih' }),
  class_id: z.string().nullable().optional(),
});

export type GradeComponentFormValues = z.infer<typeof gradeComponentSchema>;

interface GradeComponentDialogProps {
  isOpen: boolean;
  onClose: () => void;
  onSuccess: () => void;
  editingComponent?: GradeComponentItem | null;
  defaultSubjectId?: string;
  defaultSemesterId?: string;
  defaultClassId?: string;
  subjects?: SubjectItem[];
  semesters?: SemesterItem[];
  classes?: SchoolClassItem[];
}

export function GradeComponentDialog({
  isOpen,
  onClose,
  onSuccess,
  editingComponent,
  defaultSubjectId,
  defaultSemesterId,
  defaultClassId,
  subjects = [],
  semesters = [],
  classes = [],
}: GradeComponentDialogProps) {
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const {
    register,
    handleSubmit,
    reset,
    setValue,
    formState: { errors, isSubmitting },
  } = useForm<GradeComponentFormValues>({
    resolver: zodResolver(gradeComponentSchema),
    defaultValues: {
      code: '',
      name: '',
      weight: 15,
      subject_id: defaultSubjectId || '',
      semester_id: defaultSemesterId || '',
      class_id: defaultClassId || null,
    },
  });

  useEffect(() => {
    if (editingComponent) {
      reset({
        code: editingComponent.code,
        name: editingComponent.name,
        weight: Number(editingComponent.weight) || 10,
        subject_id: editingComponent.subject_id,
        semester_id: editingComponent.semester_id,
        class_id: editingComponent.class_id || null,
      });
    } else {
      reset({
        code: '',
        name: '',
        weight: 15,
        subject_id: defaultSubjectId || '',
        semester_id: defaultSemesterId || '',
        class_id: defaultClassId || null,
      });
    }
    setErrorMessage(null);
  }, [editingComponent, defaultSubjectId, defaultSemesterId, defaultClassId, reset, isOpen]);

  if (!isOpen) return null;

  const onSubmit = async (values: GradeComponentFormValues) => {
    setErrorMessage(null);
    try {
      if (editingComponent) {
        await academicApi.updateGradeComponent(editingComponent.id, {
          code: values.code,
          name: values.name,
          weight: values.weight,
        });
      } else {
        await academicApi.createGradeComponent({
          code: values.code,
          name: values.name,
          weight: values.weight,
          subject_id: values.subject_id,
          semester_id: values.semester_id,
          class_id: values.class_id || null,
        });
      }
      reset();
      onSuccess();
      onClose();
    } catch (err: unknown) {
      if (err instanceof ApiClientError) {
        setErrorMessage(err.message);
      } else {
        setErrorMessage('Terjadi kesalahan saat menyimpan komponen penilaian.');
      }
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div className="w-full max-w-lg rounded-xl bg-white shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
        <div className="flex items-center justify-between border-b border-gray-100 px-6 py-4 bg-slate-50/50">
          <div className="flex items-center gap-2.5">
            <div className="rounded-lg bg-emerald-50 p-2 text-emerald-600 border border-emerald-100">
              <Award className="h-5 w-5" />
            </div>
            <div>
              <h2 className="text-base font-semibold text-gray-900">
                {editingComponent ? 'Edit Komponen Penilaian' : 'Tambah Komponen Penilaian'}
              </h2>
              <p className="text-xs text-gray-500">
                Komponen formatif/sumatif dengan bobot persentase untuk buku nilai
              </p>
            </div>
          </div>
          <button
            onClick={onClose}
            type="button"
            className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition"
          >
            <X className="h-4 w-4" />
          </button>
        </div>

        <form onSubmit={handleSubmit(onSubmit)} className="space-y-4 px-6 py-5">
          {errorMessage && (
            <div className="flex items-start gap-2 rounded-lg bg-red-50 p-3 text-xs text-red-700 border border-red-200">
              <AlertTriangle className="h-4 w-4 text-red-600 shrink-0 mt-0.5" />
              <span>{errorMessage}</span>
            </div>
          )}

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="block text-xs font-semibold text-gray-700 mb-1">
                Semester <span className="text-red-500">*</span>
              </label>
              <select
                {...register('semester_id')}
                disabled={Boolean(editingComponent)}
                className="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs text-gray-900 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 disabled:bg-gray-100"
              >
                <option value="">Pilih Semester</option>
                {semesters.map((s) => (
                  <option key={s.id} value={s.id}>
                    {s.name}
                  </option>
                ))}
              </select>
              {errors.semester_id && (
                <p className="text-xs text-red-600 mt-1">{errors.semester_id.message}</p>
              )}
            </div>

            <div>
              <label className="block text-xs font-semibold text-gray-700 mb-1">
                Mata Pelajaran <span className="text-red-500">*</span>
              </label>
              <select
                {...register('subject_id')}
                disabled={Boolean(editingComponent)}
                className="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs text-gray-900 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 disabled:bg-gray-100"
              >
                <option value="">Pilih Mata Pelajaran</option>
                {subjects.map((sub) => (
                  <option key={sub.id} value={sub.id}>
                    {sub.name} ({sub.code})
                  </option>
                ))}
              </select>
              {errors.subject_id && (
                <p className="text-xs text-red-600 mt-1">{errors.subject_id.message}</p>
              )}
            </div>
          </div>

          <div>
            <label className="block text-xs font-semibold text-gray-700 mb-1">
              Cakupan Kelas (Opsional)
            </label>
            <select
              {...register('class_id')}
              disabled={Boolean(editingComponent)}
              className="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs text-gray-900 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 disabled:bg-gray-100"
            >
              <option value="">Berlaku untuk Semua Rombel/Kelas</option>
              {classes.map((cls) => (
                <option key={cls.id} value={cls.id}>
                  Kelas {cls.name}
                </option>
              ))}
            </select>
            <p className="text-[11px] text-gray-500 mt-0.5">
              Kosongkan bila berlaku umum untuk seluruh kelas yang mempelajari mapel ini
            </p>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label className="block text-xs font-semibold text-gray-700 mb-1">
                Kode Komponen <span className="text-red-500">*</span>
              </label>
              <Input
                {...register('code')}
                placeholder="misal: UH1, TUGAS"
                className="text-xs font-mono uppercase"
              />
              {errors.code && <p className="text-xs text-red-600 mt-1">{errors.code.message}</p>}
            </div>

            <div className="md:col-span-2">
              <label className="block text-xs font-semibold text-gray-700 mb-1">
                Nama Komponen <span className="text-red-500">*</span>
              </label>
              <Input
                {...register('name')}
                placeholder="misal: Ulangan Harian Bab 1"
                className="text-xs"
              />
              {errors.name && <p className="text-xs text-red-600 mt-1">{errors.name.message}</p>}
            </div>
          </div>

          <div>
            <label className="block text-xs font-semibold text-gray-700 mb-1">
              Bobot Penilaian (%) <span className="text-red-500">*</span>
            </label>
            <div className="relative">
              <Input
                type="number"
                step="0.1"
                min="0.1"
                max="100"
                {...register('weight', { valueAsNumber: true })}
                placeholder="misal: 20"
                className="text-xs pr-8"
              />
              <span className="absolute right-3 top-2 text-xs font-medium text-gray-400">%</span>
            </div>
            {errors.weight && <p className="text-xs text-red-600 mt-1">{errors.weight.message}</p>}
            <p className="text-[11px] text-gray-500 mt-0.5">
              Bobot digunakan untuk menghitung nilai rata-rata berbobot secara proporsional
            </p>
          </div>

          <div className="flex justify-end gap-2 pt-4 border-t border-gray-100">
            <Button
              type="button"
              variant="outline"
              onClick={onClose}
              disabled={isSubmitting}
              className="text-xs"
            >
              Batal
            </Button>
            <Button
              type="submit"
              disabled={isSubmitting}
              className="bg-emerald-600 hover:bg-emerald-700 text-white text-xs gap-1.5"
            >
              {isSubmitting ? (
                <>
                  <Loader2 className="h-3.5 w-3.5 animate-spin" />
                  Menyimpan...
                </>
              ) : editingComponent ? (
                'Perbarui Komponen'
              ) : (
                'Simpan Komponen'
              )}
            </Button>
          </div>
        </form>
      </div>
    </div>
  );
}
