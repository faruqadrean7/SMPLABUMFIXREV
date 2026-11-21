'use client';

import React, { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import * as z from 'zod';
import { PPDBTrackItem, PPDBWaveItem } from '@/types/ppdb';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { X, GitBranch, Calculator } from 'lucide-react';

export const trackSchema = z.object({
  wave_id: z.string().min(1, 'Gelombang PPDB wajib dipilih'),
  code: z
    .string()
    .min(1, 'Kode jalur wajib diisi')
    .max(50, 'Maksimal 50 karakter')
    .regex(/^[A-Za-z0-9_-]+$/, 'Kode hanya boleh berupa huruf, angka, strip, atau garis bawah'),
  name: z.string().min(1, 'Nama jalur wajib diisi').max(100, 'Maksimal 100 karakter'),
  quota: z.coerce.number().min(1, 'Kuota minimal 1 calon siswa'),
  rapor_weight: z.coerce
    .number()
    .min(0, 'Bobot rapor minimal 0')
    .max(1, 'Bobot rapor maksimal 1')
    .default(0.6),
  test_weight: z.coerce
    .number()
    .min(0, 'Bobot tes minimal 0')
    .max(1, 'Bobot tes maksimal 1')
    .default(0.4),
});

export type TrackFormValues = z.infer<typeof trackSchema>;

export interface TrackDialogProps {
  isOpen: boolean;
  onClose: () => void;
  onSubmit: (data: TrackFormValues) => Promise<void>;
  waves: PPDBWaveItem[];
  defaultWaveId?: string;
  initialData?: PPDBTrackItem | null;
  isLoading?: boolean;
}

export function TrackDialog({
  isOpen,
  onClose,
  onSubmit,
  waves,
  defaultWaveId,
  initialData,
  isLoading = false,
}: TrackDialogProps) {
  const isEditing = !!initialData;

  const {
    register,
    handleSubmit,
    reset,
    watch,
    formState: { errors, isSubmitting },
  } = useForm<TrackFormValues>({
    resolver: zodResolver(trackSchema),
    defaultValues: {
      wave_id: defaultWaveId || '',
      code: '',
      name: '',
      quota: 30,
      rapor_weight: 0.6,
      test_weight: 0.4,
    },
  });

  const raporWeight = watch('rapor_weight') || 0;
  const testWeight = watch('test_weight') || 0;
  const totalWeight = Math.round((Number(raporWeight) + Number(testWeight)) * 100) / 100;

  useEffect(() => {
    if (initialData) {
      reset({
        wave_id: initialData.wave_id,
        code: initialData.code,
        name: initialData.name,
        quota: initialData.quota,
        rapor_weight: initialData.score_formula?.rapor_weight ?? 0.6,
        test_weight: initialData.score_formula?.test_weight ?? 0.4,
      });
    } else {
      reset({
        wave_id: defaultWaveId || (waves[0]?.id ?? ''),
        code: '',
        name: '',
        quota: 30,
        rapor_weight: 0.6,
        test_weight: 0.4,
      });
    }
  }, [initialData, defaultWaveId, waves, reset, isOpen]);

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div className="w-full max-w-lg rounded-xl bg-white shadow-xl">
        {/* Header */}
        <div className="flex items-center justify-between border-b border-gray-100 px-6 py-4">
          <div className="flex items-center gap-2">
            <div className="rounded-lg bg-emerald-50 p-2 text-emerald-600">
              <GitBranch className="h-5 w-5" />
            </div>
            <div>
              <h2 className="text-lg font-semibold text-gray-900">
                {isEditing ? 'Ubah Jalur Pendaftaran' : 'Tambah Jalur Pendaftaran'}
              </h2>
              <p className="text-xs text-gray-500">
                {isEditing
                  ? 'Perbarui kuota dan bobot penilaian jalur pendaftaran'
                  : 'Konfigurasi jalur masuk baru, alokasi kuota, dan formula skor'}
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
              Gelombang PPDB <span className="text-red-500">*</span>
            </label>
            <select
              {...register('wave_id')}
              disabled={isLoading || isSubmitting || isEditing}
              className="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 disabled:bg-gray-100"
            >
              <option value="">Pilih Gelombang...</option>
              {waves.map((w) => (
                <option key={w.id} value={w.id}>
                  {w.name} ({w.status.toUpperCase()})
                </option>
              ))}
            </select>
            {errors.wave_id && (
              <p className="mt-1 text-xs text-red-500">{errors.wave_id.message}</p>
            )}
          </div>

          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
              <label className="mb-1 block text-sm font-medium text-gray-700">
                Kode Jalur <span className="text-red-500">*</span>
              </label>
              <Input
                {...register('code')}
                placeholder="misal: PRESTASI-AKAD"
                disabled={isLoading || isSubmitting}
              />
              {errors.code && (
                <p className="mt-1 text-xs text-red-500">{errors.code.message}</p>
              )}
            </div>

            <div>
              <label className="mb-1 block text-sm font-medium text-gray-700">
                Kuota Siswa <span className="text-red-500">*</span>
              </label>
              <Input
                type="number"
                {...register('quota')}
                placeholder="30"
                disabled={isLoading || isSubmitting}
              />
              {errors.quota && (
                <p className="mt-1 text-xs text-red-500">{errors.quota.message}</p>
              )}
            </div>
          </div>

          <div>
            <label className="mb-1 block text-sm font-medium text-gray-700">
              Nama Jalur <span className="text-red-500">*</span>
            </label>
            <Input
              {...register('name')}
              placeholder="Contoh: Jalur Prestasi Akademik & Non-Akademik"
              disabled={isLoading || isSubmitting}
            />
            {errors.name && (
              <p className="mt-1 text-xs text-red-500">{errors.name.message}</p>
            )}
          </div>

          {/* Bobot Rumus Seleksi */}
          <div className="rounded-lg border border-gray-200 bg-gray-50/70 p-3.5">
            <div className="mb-2 flex items-center justify-between">
              <span className="flex items-center gap-1.5 text-xs font-semibold text-gray-700">
                <Calculator className="h-4 w-4 text-emerald-600" />
                Formula Skor Seleksi (Keputusan #1: Rapor + Tes CBT)
              </span>
              <span
                className={`text-xs font-medium px-2 py-0.5 rounded ${
                  totalWeight === 1.0
                    ? 'bg-emerald-100 text-emerald-700'
                    : 'bg-amber-100 text-amber-700'
                }`}
              >
                Total: {Math.round(totalWeight * 100)}%
              </span>
            </div>

            <div className="grid grid-cols-2 gap-3">
              <div>
                <label className="mb-1 block text-xs text-gray-600">
                  Bobot Nilai Rapor (0.0 - 1.0)
                </label>
                <Input
                  type="number"
                  step="0.05"
                  min="0"
                  max="1"
                  {...register('rapor_weight')}
                  disabled={isLoading || isSubmitting}
                />
                {errors.rapor_weight && (
                  <p className="mt-1 text-xs text-red-500">{errors.rapor_weight.message}</p>
                )}
              </div>

              <div>
                <label className="mb-1 block text-xs text-gray-600">
                  Bobot Tes Masuk (0.0 - 1.0)
                </label>
                <Input
                  type="number"
                  step="0.05"
                  min="0"
                  max="1"
                  {...register('test_weight')}
                  disabled={isLoading || isSubmitting}
                />
                {errors.test_weight && (
                  <p className="mt-1 text-xs text-red-500">{errors.test_weight.message}</p>
                )}
              </div>
            </div>
            <p className="mt-2 text-[11px] text-gray-500">
              Formula standar: 0.60 (60% Rapor) + 0.40 (40% Tes CBT Labschool UM).
            </p>
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
              {isLoading || isSubmitting ? 'Menyimpan...' : isEditing ? 'Simpan Perubahan' : 'Buat Jalur'}
            </Button>
          </div>
        </form>
      </div>
    </div>
  );
}
