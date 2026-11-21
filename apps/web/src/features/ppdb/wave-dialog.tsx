'use client';

import React, { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import * as z from 'zod';
import { PPDBWaveItem, PPDBWaveStatus } from '@/types/ppdb';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { X, Calendar, Layers } from 'lucide-react';

export const waveSchema = z
  .object({
    name: z.string().min(1, 'Nama gelombang PPDB wajib diisi').max(150, 'Maksimal 150 karakter'),
    open_at: z.string().min(1, 'Tanggal buka pendaftaran wajib diisi'),
    close_at: z.string().min(1, 'Tanggal tutup pendaftaran wajib diisi'),
    result_publish_at: z.string().min(1, 'Tanggal pengumuman hasil wajib diisi'),
    status: z.enum(['draft', 'aktif', 'ditutup', 'selesai'] as const, {
      required_error: 'Status gelombang wajib dipilih',
    }),
  })
  .refine((data) => new Date(data.close_at) > new Date(data.open_at), {
    message: 'Tanggal tutup pendaftaran harus setelah tanggal buka',
    path: ['close_at'],
  })
  .refine((data) => new Date(data.result_publish_at) >= new Date(data.close_at), {
    message: 'Tanggal pengumuman hasil tidak boleh sebelum tanggal tutup pendaftaran',
    path: ['result_publish_at'],
  });

export type WaveFormValues = z.infer<typeof waveSchema>;

export interface WaveDialogProps {
  isOpen: boolean;
  onClose: () => void;
  onSubmit: (data: WaveFormValues) => Promise<void>;
  initialData?: PPDBWaveItem | null;
  isLoading?: boolean;
}

export function WaveDialog({
  isOpen,
  onClose,
  onSubmit,
  initialData,
  isLoading = false,
}: WaveDialogProps) {
  const isEditing = !!initialData;

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<WaveFormValues>({
    resolver: zodResolver(waveSchema),
    defaultValues: {
      name: '',
      open_at: '',
      close_at: '',
      result_publish_at: '',
      status: 'draft',
    },
  });

  useEffect(() => {
    if (initialData) {
      // Format datetime string to YYYY-MM-DD or datetime-local
      const toInputDate = (d?: string) => (d ? d.slice(0, 10) : '');
      reset({
        name: initialData.name,
        open_at: toInputDate(initialData.open_at),
        close_at: toInputDate(initialData.close_at),
        result_publish_at: toInputDate(initialData.result_publish_at),
        status: initialData.status,
      });
    } else {
      reset({
        name: '',
        open_at: '',
        close_at: '',
        result_publish_at: '',
        status: 'draft',
      });
    }
  }, [initialData, reset, isOpen]);

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div className="w-full max-w-lg rounded-xl bg-white shadow-xl">
        {/* Header */}
        <div className="flex items-center justify-between border-b border-gray-100 px-6 py-4">
          <div className="flex items-center gap-2">
            <div className="rounded-lg bg-emerald-50 p-2 text-emerald-600">
              <Layers className="h-5 w-5" />
            </div>
            <div>
              <h2 className="text-lg font-semibold text-gray-900">
                {isEditing ? 'Ubah Gelombang PPDB' : 'Tambah Gelombang PPDB'}
              </h2>
              <p className="text-xs text-gray-500">
                {isEditing
                  ? 'Perbarui jadwal dan status gelombang pendaftaran'
                  : 'Konfigurasi periode baru pendaftaran calon peserta didik'}
              </p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
          >
            <X className="h-5 w-5" />
          </button>
        </div>

        {/* Form Body */}
        <form onSubmit={handleSubmit(onSubmit)} className="space-y-4 px-6 py-5">
          <div>
            <label className="mb-1 block text-sm font-medium text-gray-700">
              Nama Gelombang <span className="text-red-500">*</span>
            </label>
            <Input
              {...register('name')}
              placeholder="Contoh: Gelombang 1 (Jalur Prestasi & Reguler)"
              disabled={isLoading || isSubmitting}
            />
            {errors.name && (
              <p className="mt-1 text-xs text-red-500">{errors.name.message}</p>
            )}
          </div>

          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
              <label className="mb-1 block text-sm font-medium text-gray-700">
                Tanggal Buka <span className="text-red-500">*</span>
              </label>
              <Input
                type="date"
                {...register('open_at')}
                disabled={isLoading || isSubmitting}
              />
              {errors.open_at && (
                <p className="mt-1 text-xs text-red-500">{errors.open_at.message}</p>
              )}
            </div>

            <div>
              <label className="mb-1 block text-sm font-medium text-gray-700">
                Tanggal Tutup <span className="text-red-500">*</span>
              </label>
              <Input
                type="date"
                {...register('close_at')}
                disabled={isLoading || isSubmitting}
              />
              {errors.close_at && (
                <p className="mt-1 text-xs text-red-500">{errors.close_at.message}</p>
              )}
            </div>
          </div>

          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
              <label className="mb-1 block text-sm font-medium text-gray-700">
                Tanggal Pengumuman <span className="text-red-500">*</span>
              </label>
              <Input
                type="date"
                {...register('result_publish_at')}
                disabled={isLoading || isSubmitting}
              />
              {errors.result_publish_at && (
                <p className="mt-1 text-xs text-red-500">
                  {errors.result_publish_at.message}
                </p>
              )}
            </div>

            <div>
              <label className="mb-1 block text-sm font-medium text-gray-700">
                Status <span className="text-red-500">*</span>
              </label>
              <select
                {...register('status')}
                disabled={isLoading || isSubmitting}
                className="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
              >
                <option value="draft">Draf (Belum Dibuka)</option>
                <option value="aktif">Aktif (Pendaftaran Buka)</option>
                <option value="ditutup">Ditutup (Proses Seleksi)</option>
                <option value="selesai">Selesai (Arsip)</option>
              </select>
              {errors.status && (
                <p className="mt-1 text-xs text-red-500">{errors.status.message}</p>
              )}
            </div>
          </div>

          {/* Footer Buttons */}
          <div className="flex items-center justify-end gap-3 border-t border-gray-100 pt-4">
            <Button
              type="button"
              variant="outline"
              onClick={onClose}
              disabled={isLoading || isSubmitting}
            >
              Batal
            </Button>
            <Button
              type="submit"
              disabled={isLoading || isSubmitting}
              className="bg-emerald-600 hover:bg-emerald-700 text-white"
            >
              {isLoading || isSubmitting ? 'Menyimpan...' : isEditing ? 'Simpan Perubahan' : 'Buat Gelombang'}
            </Button>
          </div>
        </form>
      </div>
    </div>
  );
}
