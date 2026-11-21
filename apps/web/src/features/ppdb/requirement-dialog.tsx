'use client';

import React from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import * as z from 'zod';
import { PPDBTrackItem } from '@/types/ppdb';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { X, FileText } from 'lucide-react';

export const requirementSchema = z.object({
  track_id: z.string().min(1, 'Jalur pendaftaran wajib dipilih'),
  document_type: z
    .string()
    .min(1, 'Jenis dokumen persyaratan wajib diisi')
    .max(100, 'Maksimal 100 karakter'),
  is_required: z.boolean().default(true),
  description: z.string().optional(),
});

export type RequirementFormValues = z.infer<typeof requirementSchema>;

export interface RequirementDialogProps {
  isOpen: boolean;
  onClose: () => void;
  onSubmit: (data: RequirementFormValues) => Promise<void>;
  track: PPDBTrackItem | null;
  isLoading?: boolean;
}

const COMMON_DOCUMENTS = [
  { label: 'Kartu Keluarga (KK)', value: 'kartu_keluarga' },
  { label: 'Akta Kelahiran', value: 'akta_kelahiran' },
  { label: 'Buku Rapor Semester 1-5', value: 'buku_rapor' },
  { label: 'Surat Keterangan Lulus (SKL) / Ijazah', value: 'surat_lulus' },
  { label: 'Pas Foto Terbaru (3x4)', value: 'pas_foto' },
  { label: 'Sertifikat Prestasi Akademik/Non-Akademik', value: 'sertifikat_prestasi' },
  { label: 'Surat Keterangan Kelakuan Baik', value: 'skck_sekolah' },
];

export function RequirementDialog({
  isOpen,
  onClose,
  onSubmit,
  track,
  isLoading = false,
}: RequirementDialogProps) {
  const {
    register,
    handleSubmit,
    setValue,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<RequirementFormValues>({
    resolver: zodResolver(requirementSchema),
    defaultValues: {
      track_id: track?.id || '',
      document_type: '',
      is_required: true,
      description: '',
    },
  });

  React.useEffect(() => {
    if (track) {
      reset({
        track_id: track.id,
        document_type: '',
        is_required: true,
        description: '',
      });
    }
  }, [track, reset, isOpen]);

  if (!isOpen || !track) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div className="w-full max-w-md rounded-xl bg-white shadow-xl">
        {/* Header */}
        <div className="flex items-center justify-between border-b border-gray-100 px-6 py-4">
          <div className="flex items-center gap-2">
            <div className="rounded-lg bg-emerald-50 p-2 text-emerald-600">
              <FileText className="h-5 w-5" />
            </div>
            <div>
              <h2 className="text-lg font-semibold text-gray-900">Tambah Syarat Berkas</h2>
              <p className="text-xs text-gray-500">
                Jalur: <span className="font-medium text-emerald-700">{track.name}</span>
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
          <input type="hidden" {...register('track_id')} />

          <div>
            <label className="mb-1 block text-sm font-medium text-gray-700">
              Pilih Dokumen Populer
            </label>
            <div className="flex flex-wrap gap-1.5 mb-2">
              {COMMON_DOCUMENTS.map((doc) => (
                <button
                  key={doc.value}
                  type="button"
                  onClick={() => setValue('document_type', doc.value, { shouldValidate: true })}
                  className="rounded-full bg-gray-100 px-2.5 py-1 text-[11px] font-medium text-gray-700 hover:bg-emerald-50 hover:text-emerald-700 transition"
                >
                  + {doc.label}
                </button>
              ))}
            </div>
          </div>

          <div>
            <label className="mb-1 block text-sm font-medium text-gray-700">
              Jenis Dokumen (Identifier) <span className="text-red-500">*</span>
            </label>
            <Input
              {...register('document_type')}
              placeholder="Contoh: kartu_keluarga"
              disabled={isLoading || isSubmitting}
            />
            {errors.document_type && (
              <p className="mt-1 text-xs text-red-500">{errors.document_type.message}</p>
            )}
          </div>

          <div>
            <label className="mb-1 block text-sm font-medium text-gray-700">
              Keterangan / Panduan Unggah
            </label>
            <textarea
              {...register('description')}
              rows={2}
              placeholder="Contoh: Scan warna asli dalam format PDF atau JPG maksimal 2MB"
              disabled={isLoading || isSubmitting}
              className="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
            />
          </div>

          <div className="flex items-center gap-2">
            <input
              type="checkbox"
              id="is_required"
              {...register('is_required')}
              disabled={isLoading || isSubmitting}
              className="h-4 w-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
            />
            <label htmlFor="is_required" className="text-sm font-medium text-gray-700">
              Dokumen Wajib (Harus diunggah sebelum pendaftar dapat diverifikasi)
            </label>
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
              {isLoading || isSubmitting ? 'Menyimpan...' : 'Simpan Syarat'}
            </Button>
          </div>
        </form>
      </div>
    </div>
  );
}
