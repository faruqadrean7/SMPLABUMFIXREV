'use client';

import React, { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import * as z from 'zod';
import { SubjectFormValues, SubjectItem } from '@/types/master';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { X, BookOpen, Save } from 'lucide-react';

export const subjectSchema = z.object({
  code: z.string().min(1, 'Kode mata pelajaran wajib diisi').max(30, 'Maksimal 30 karakter'),
  name: z.string().min(1, 'Nama mata pelajaran wajib diisi').max(150, 'Maksimal 150 karakter'),
  subject_group: z.string().min(1, 'Kelompok kurikulum wajib diisi').max(50, 'Maksimal 50 karakter'),
});

export interface SubjectDialogProps {
  isOpen: boolean;
  onClose: () => void;
  onSubmit: (data: SubjectFormValues) => Promise<void>;
  initialData?: SubjectItem | null;
  isLoading?: boolean;
}

export function SubjectDialog({
  isOpen,
  onClose,
  onSubmit,
  initialData,
  isLoading = false,
}: SubjectDialogProps) {
  const isEditing = !!initialData;

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<SubjectFormValues>({
    resolver: zodResolver(subjectSchema),
    defaultValues: {
      code: '',
      name: '',
      subject_group: 'Kelompok A (Umum)',
    },
  });

  useEffect(() => {
    if (initialData) {
      reset({
        code: initialData.code || '',
        name: initialData.name || '',
        subject_group: initialData.subject_group || 'Kelompok A (Umum)',
      });
    } else {
      reset({
        code: '',
        name: '',
        subject_group: 'Kelompok A (Umum)',
      });
    }
  }, [initialData, reset, isOpen]);

  if (!isOpen) return null;

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs animate-in fade-in duration-150"
      role="dialog"
      aria-modal="true"
    >
      <div className="relative w-full max-w-md rounded-xl bg-white p-6 shadow-elevated border border-slate-200">
        <div className="flex items-center justify-between pb-4 border-b border-slate-100">
          <div className="flex items-center gap-2">
            <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-50 text-brand-blue">
              {isEditing ? <Save className="h-5 w-5" /> : <BookOpen className="h-5 w-5" />}
            </div>
            <div>
              <h2 className="text-base font-bold text-slate-900">
                {isEditing ? 'Perbarui Mata Pelajaran' : 'Tambah Mata Pelajaran Baru'}
              </h2>
              <p className="text-xs text-slate-500">Katalog kurikulum pembelajaran sekolah</p>
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
              Kode Mata Pelajaran <span className="text-status-danger">*</span>
            </label>
            <Input
              {...register('code')}
              placeholder="Contoh: MAT-W / BIND-1"
              className="h-9 text-xs font-mono uppercase"
              disabled={isLoading || isSubmitting}
            />
            {errors.code && <p className="mt-1 text-[11px] text-status-danger">{errors.code.message}</p>}
          </div>

          <div>
            <label className="block text-xs font-semibold text-slate-700 mb-1">
              Nama Mata Pelajaran <span className="text-status-danger">*</span>
            </label>
            <Input
              {...register('name')}
              placeholder="Contoh: Matematika Wajib"
              className="h-9 text-xs"
              disabled={isLoading || isSubmitting}
            />
            {errors.name && <p className="mt-1 text-[11px] text-status-danger">{errors.name.message}</p>}
          </div>

          <div>
            <label className="block text-xs font-semibold text-slate-700 mb-1">
              Kelompok Kurikulum <span className="text-status-danger">*</span>
            </label>
            <select
              {...register('subject_group')}
              className="w-full h-9 px-3 rounded-md border border-slate-300 bg-white text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand-blue"
              disabled={isLoading || isSubmitting}
            >
              <option value="Kelompok A (Umum)">Kelompok A (Umum / Wajib)</option>
              <option value="Kelompok B (Umum)">Kelompok B (Umum)</option>
              <option value="Kelompok C (Peminatan MIPA)">Kelompok C (Peminatan MIPA)</option>
              <option value="Kelompok C (Peminatan IPS)">Kelompok C (Peminatan IPS)</option>
              <option value="Kelompok C (Peminatan Bahasa)">Kelompok C (Peminatan Bahasa)</option>
              <option value="Muatan Lokal">Muatan Lokal (Mulok)</option>
            </select>
            {errors.subject_group && (
              <p className="mt-1 text-[11px] text-status-danger">{errors.subject_group.message}</p>
            )}
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
              {isEditing ? 'Simpan Perubahan' : 'Tambah Mata Pelajaran'}
            </Button>
          </div>
        </form>
      </div>
    </div>
  );
}
