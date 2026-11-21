'use client';

import React, { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import * as z from 'zod';
import { AcademicYearFormValues, AcademicYearItem } from '@/types/master';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { X, Calendar } from 'lucide-react';

export const academicYearSchema = z
  .object({
    name: z
      .string()
      .min(1, 'Nama tahun ajaran wajib diisi')
      .max(50, 'Maksimal 50 karakter')
      .regex(/^\d{4}\/\d{4}$/, 'Format nama harus berupa tahun (contoh: 2025/2026)'),
    start_date: z.string().min(1, 'Tanggal mulai wajib diisi'),
    end_date: z.string().min(1, 'Tanggal selesai wajib diisi'),
    is_active: z.boolean().optional(),
  })
  .refine((data) => new Date(data.end_date) > new Date(data.start_date), {
    message: 'Tanggal selesai harus setelah tanggal mulai',
    path: ['end_date'],
  });

export interface AcademicYearDialogProps {
  isOpen: boolean;
  onClose: () => void;
  onSubmit: (data: AcademicYearFormValues) => Promise<void>;
  initialData?: AcademicYearItem | null;
  isLoading?: boolean;
}

export function AcademicYearDialog({
  isOpen,
  onClose,
  onSubmit,
  initialData,
  isLoading = false,
}: AcademicYearDialogProps) {
  const isEditing = !!initialData;

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<AcademicYearFormValues>({
    resolver: zodResolver(academicYearSchema),
    defaultValues: {
      name: '',
      start_date: '',
      end_date: '',
      is_active: false,
    },
  });

  useEffect(() => {
    if (initialData) {
      reset({
        name: initialData.name,
        start_date: initialData.start_date ? initialData.start_date.substring(0, 10) : '',
        end_date: initialData.end_date ? initialData.end_date.substring(0, 10) : '',
        is_active: initialData.is_active,
      });
    } else {
      reset({
        name: '',
        start_date: '',
        end_date: '',
        is_active: false,
      });
    }
  }, [initialData, reset, isOpen]);

  if (!isOpen) return null;

  const handleFormSubmit = async (values: AcademicYearFormValues) => {
    await onSubmit(values);
  };

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs animate-in fade-in duration-150"
      role="dialog"
      aria-modal="true"
      aria-labelledby="academic-year-dialog-title"
    >
      <div className="relative w-full max-w-lg rounded-xl bg-white p-6 shadow-elevated border border-slate-200 space-y-5">
        <div className="flex items-center justify-between border-b border-slate-100 pb-3">
          <div className="flex items-center gap-2.5">
            <div className="p-2 rounded-lg bg-blue-50 text-brand-blue">
              <Calendar className="h-5 w-5" />
            </div>
            <div>
              <h3 id="academic-year-dialog-title" className="text-base font-bold text-slate-900">
                {isEditing ? 'Ubah Tahun Ajaran' : 'Tambah Tahun Ajaran Baru'}
              </h3>
              <p className="text-xs text-slate-500">
                {isEditing
                  ? 'Perbarui informasi rentang waktu tahun ajaran.'
                  : 'Daftarkan periode tahun ajaran akademik baru ke sistem.'}
              </p>
            </div>
          </div>

          {!isLoading && (
            <button
              onClick={onClose}
              className="text-slate-400 hover:text-slate-600 p-1 rounded-md"
              aria-label="Tutup modal"
            >
              <X className="h-4 w-4" />
            </button>
          )}
        </div>

        <form onSubmit={handleSubmit(handleFormSubmit)} className="space-y-4" noValidate>
          {/* Nama Tahun Ajaran */}
          <div className="space-y-1.5">
            <label htmlFor="ay-name" className="block text-xs font-semibold text-slate-700">
              Nama Tahun Ajaran <span className="text-status-danger">*</span>
            </label>
            <Input
              id="ay-name"
              placeholder="Contoh: 2025/2026"
              hasError={!!errors.name}
              disabled={isLoading || isSubmitting}
              {...register('name')}
            />
            {errors.name ? (
              <p className="text-[11px] font-medium text-status-danger">{errors.name.message}</p>
            ) : (
              <p className="text-[11px] text-slate-400">Gunakan format empat digit tahun: YYYY/YYYY</p>
            )}
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            {/* Tanggal Mulai */}
            <div className="space-y-1.5">
              <label htmlFor="ay-start" className="block text-xs font-semibold text-slate-700">
                Tanggal Mulai <span className="text-status-danger">*</span>
              </label>
              <Input
                id="ay-start"
                type="date"
                hasError={!!errors.start_date}
                disabled={isLoading || isSubmitting}
                {...register('start_date')}
              />
              {errors.start_date && (
                <p className="text-[11px] font-medium text-status-danger">
                  {errors.start_date.message}
                </p>
              )}
            </div>

            {/* Tanggal Selesai */}
            <div className="space-y-1.5">
              <label htmlFor="ay-end" className="block text-xs font-semibold text-slate-700">
                Tanggal Selesai <span className="text-status-danger">*</span>
              </label>
              <Input
                id="ay-end"
                type="date"
                hasError={!!errors.end_date}
                disabled={isLoading || isSubmitting}
                {...register('end_date')}
              />
              {errors.end_date && (
                <p className="text-[11px] font-medium text-status-danger">
                  {errors.end_date.message}
                </p>
              )}
            </div>
          </div>

          {!isEditing && (
            <div className="pt-1 flex items-center gap-2">
              <input
                id="ay-active"
                type="checkbox"
                className="h-4 w-4 rounded border-slate-300 text-brand-blue focus:ring-brand-blue"
                disabled={isLoading || isSubmitting}
                {...register('is_active')}
              />
              <label htmlFor="ay-active" className="text-xs text-slate-700 cursor-pointer select-none">
                Jadikan sebagai Tahun Ajaran Aktif saat disimpan
              </label>
            </div>
          )}

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
              size="sm"
              isLoading={isLoading || isSubmitting}
              disabled={isLoading || isSubmitting}
            >
              {isEditing ? 'Simpan Perubahan' : 'Tambah Tahun Ajaran'}
            </Button>
          </div>
        </form>
      </div>
    </div>
  );
}
