'use client';

import React from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import * as z from 'zod';
import { PPDBRankingItem, ApplicantDecision } from '@/types/ppdb';
import { Button } from '@/components/ui/button';
import { X, SlidersHorizontal, AlertTriangle } from 'lucide-react';

export const decisionOverrideSchema = z.object({
  decision: z.enum(['diterima', 'cadangan', 'ditolak'] as const, {
    required_error: 'Keputusan seleksi wajib dipilih',
  }),
  reason: z
    .string()
    .min(5, 'Alasan penyesuaian keputusan minimal 5 karakter untuk keperluan audit')
    .max(500, 'Alasan penyesuaian maksimal 500 karakter'),
});

export type DecisionOverrideFormValues = z.infer<typeof decisionOverrideSchema>;

export interface DecisionOverrideDialogProps {
  isOpen: boolean;
  onClose: () => void;
  onSubmit: (data: DecisionOverrideFormValues) => Promise<void>;
  applicant: PPDBRankingItem | null;
  isLoading?: boolean;
}

export function DecisionOverrideDialog({
  isOpen,
  onClose,
  onSubmit,
  applicant,
  isLoading = false,
}: DecisionOverrideDialogProps) {
  const {
    register,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<DecisionOverrideFormValues>({
    resolver: zodResolver(decisionOverrideSchema),
    defaultValues: {
      decision: applicant?.decision || 'diterima',
      reason: '',
    },
  });

  React.useEffect(() => {
    if (applicant) {
      reset({
        decision: applicant.decision || 'diterima',
        reason: '',
      });
    }
  }, [applicant, reset, isOpen]);

  if (!isOpen || !applicant) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div className="w-full max-w-md rounded-xl bg-white shadow-xl">
        {/* Header */}
        <div className="flex items-center justify-between border-b border-gray-100 px-6 py-4">
          <div className="flex items-center gap-2">
            <div className="rounded-lg bg-amber-50 p-2 text-amber-600">
              <SlidersHorizontal className="h-5 w-5" />
            </div>
            <div>
              <h2 className="text-lg font-semibold text-gray-900">Penyesuaian Keputusan Seleksi</h2>
              <p className="text-xs text-gray-500">
                Pendaftar: <span className="font-semibold text-gray-800">{applicant.full_name}</span>
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

        {/* Warning Notice */}
        <div className="mx-6 mt-4 flex items-start gap-2.5 rounded-lg border border-amber-200 bg-amber-50/70 p-3 text-xs text-amber-900">
          <AlertTriangle className="h-4 w-4 shrink-0 text-amber-600 mt-0.5" />
          <span>
            Penyesuaian keputusan ini akan dicatat dalam riwayat status dan log audit keamanan bersama identitas panitia yang melakukan tindakan.
          </span>
        </div>

        {/* Form Body */}
        <form onSubmit={handleSubmit(onSubmit)} className="space-y-4 px-6 py-4">
          <div>
            <label className="mb-1 block text-sm font-medium text-gray-700">
              Keputusan Seleksi Baru <span className="text-red-500">*</span>
            </label>
            <select
              {...register('decision')}
              disabled={isLoading || isSubmitting}
              className="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
            >
              <option value="diterima">DITERIMA (Lolos Seleksi)</option>
              <option value="cadangan">CADANGAN (Waitlist)</option>
              <option value="ditolak">TIDAK DITERIMA (Ditolak)</option>
            </select>
            {errors.decision && (
              <p className="mt-1 text-xs text-red-500">{errors.decision.message}</p>
            )}
          </div>

          <div>
            <label className="mb-1 block text-sm font-medium text-gray-700">
              Alasan Penyesuaian (Audit Log) <span className="text-red-500">*</span>
            </label>
            <textarea
              {...register('reason')}
              rows={3}
              placeholder="Jelaskan dasar penyesuaian keputusan (misal: rekomendasi beasiswa, kuota khusus, dll)"
              disabled={isLoading || isSubmitting}
              className="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
            />
            {errors.reason && (
              <p className="mt-1 text-xs text-red-500">{errors.reason.message}</p>
            )}
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
              className="bg-amber-600 hover:bg-amber-700 text-white"
            >
              {isLoading || isSubmitting ? 'Menyimpan...' : 'Simpan Penyesuaian'}
            </Button>
          </div>
        </form>
      </div>
    </div>
  );
}
