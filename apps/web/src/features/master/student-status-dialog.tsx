'use client';

import React from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import * as z from 'zod';
import { ChangeStudentStatusFormValues, StudentItem } from '@/types/master';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { X, ArrowRightLeft } from 'lucide-react';

export const changeStudentStatusSchema = z.object({
  status: z.enum(['aktif', 'lulus', 'pindah', 'keluar'], {
    required_error: 'Pilih status baru siswa',
  }),
  effective_date: z.string().min(1, 'Tanggal berlaku wajib diisi'),
  reason: z.string().optional(),
});

export interface StudentStatusDialogProps {
  isOpen: boolean;
  onClose: () => void;
  onSubmit: (data: ChangeStudentStatusFormValues) => Promise<void>;
  student: StudentItem | null;
  isLoading?: boolean;
}

export function StudentStatusDialog({
  isOpen,
  onClose,
  onSubmit,
  student,
  isLoading = false,
}: StudentStatusDialogProps) {
  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<ChangeStudentStatusFormValues>({
    resolver: zodResolver(changeStudentStatusSchema),
    defaultValues: {
      status: student?.status || 'aktif',
      effective_date: new Date().toISOString().split('T')[0],
      reason: '',
    },
  });

  if (!isOpen || !student) return null;

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs animate-in fade-in duration-150"
      role="dialog"
      aria-modal="true"
    >
      <div className="relative w-full max-w-md rounded-xl bg-white p-6 shadow-elevated border border-slate-200">
        <div className="flex items-center justify-between pb-4 border-b border-slate-100">
          <div className="flex items-center gap-2">
            <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-amber-50 text-amber-600">
              <ArrowRightLeft className="h-5 w-5" />
            </div>
            <div>
              <h2 className="text-base font-bold text-slate-900">Ubah Status Siswa</h2>
              <p className="text-xs text-slate-500">Mutasi formal status akademik siswa</p>
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

        <div className="mt-3 p-2.5 rounded bg-slate-50 border border-slate-200 text-xs text-slate-700">
          <p className="font-semibold text-slate-900">{student.full_name}</p>
          <p className="text-slate-500 text-[11px]">
            NIS: {student.nis} • NISN: {student.nisn} • Status Saat Ini:{' '}
            <span className="font-semibold capitalize text-brand-blue">{student.status}</span>
          </p>
        </div>

        <form onSubmit={handleSubmit(onSubmit)} className="mt-4 space-y-4">
          <div>
            <label className="block text-xs font-semibold text-slate-700 mb-1">
              Status Baru <span className="text-status-danger">*</span>
            </label>
            <select
              {...register('status')}
              className="w-full h-9 px-3 rounded-md border border-slate-300 bg-white text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand-blue"
              disabled={isLoading || isSubmitting}
            >
              <option value="aktif">Aktif</option>
              <option value="lulus">Lulus</option>
              <option value="pindah">Pindah Sekolah (Mutasi Keluar)</option>
              <option value="keluar">Keluar / Dikeluarkan</option>
            </select>
            {errors.status && (
              <p className="mt-1 text-[11px] text-status-danger">{errors.status.message}</p>
            )}
          </div>

          <div>
            <label className="block text-xs font-semibold text-slate-700 mb-1">
              Tanggal Efektif <span className="text-status-danger">*</span>
            </label>
            <Input
              {...register('effective_date')}
              type="date"
              className="h-9 text-xs"
              disabled={isLoading || isSubmitting}
            />
            {errors.effective_date && (
              <p className="mt-1 text-[11px] text-status-danger">{errors.effective_date.message}</p>
            )}
          </div>

          <div>
            <label className="block text-xs font-semibold text-slate-700 mb-1">
              Alasan Mutasi / Keterangan
            </label>
            <Input
              {...register('reason')}
              placeholder="Contoh: Mengikuti perpindahan tugas orang tua"
              className="h-9 text-xs"
              disabled={isLoading || isSubmitting}
            />
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
              Perbarui Status
            </Button>
          </div>
        </form>
      </div>
    </div>
  );
}
