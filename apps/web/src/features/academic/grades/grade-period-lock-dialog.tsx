'use client';

import React, { useState } from 'react';
import { GradePeriodItem } from '@/types/academic';
import { academicApi } from '../academic-api';
import { ApiClientError } from '@/lib/api/errors';
import { Button } from '@/components/ui/button';
import { Lock, Unlock, X, AlertTriangle, Loader2 } from 'lucide-react';

interface GradePeriodLockDialogProps {
  isOpen: boolean;
  onClose: () => void;
  onSuccess: () => void;
  period: GradePeriodItem | null;
}

export function GradePeriodLockDialog({
  isOpen,
  onClose,
  onSuccess,
  period,
}: GradePeriodLockDialogProps) {
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  if (!isOpen || !period) return null;

  const isCurrentlyLocked = period.status === 'locked';
  const targetAction = isCurrentlyLocked ? 'open' : 'locked';

  const handleToggle = async () => {
    setIsSubmitting(true);
    setErrorMessage(null);
    try {
      await academicApi.lockGradePeriod(period.id, {
        status: targetAction,
      });
      onSuccess();
      onClose();
    } catch (err: unknown) {
      if (err instanceof ApiClientError) {
        setErrorMessage(err.message);
      } else {
        setErrorMessage('Terjadi kesalahan saat memperbarui status kunci periode penilaian.');
      }
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div className="w-full max-w-md rounded-xl bg-white shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
        <div className="flex items-center justify-between border-b border-gray-100 px-6 py-4 bg-slate-50/50">
          <div className="flex items-center gap-2.5">
            <div
              className={`rounded-lg p-2 border ${
                isCurrentlyLocked
                  ? 'bg-amber-50 text-amber-600 border-amber-200'
                  : 'bg-emerald-50 text-emerald-600 border-emerald-200'
              }`}
            >
              {isCurrentlyLocked ? <Unlock className="h-5 w-5" /> : <Lock className="h-5 w-5" />}
            </div>
            <div>
              <h2 className="text-base font-semibold text-gray-900">
                {isCurrentlyLocked ? 'Buka Kunci Periode Nilai' : 'Kunci Periode Nilai'}
              </h2>
              <p className="text-xs text-gray-500">
                {period.month_name || `Bulan ke-${period.month}`} —{' '}
                {period.semester?.name || 'Semester Aktif'}
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

        <div className="space-y-4 px-6 py-5">
          {errorMessage && (
            <div className="flex items-start gap-2 rounded-lg bg-red-50 p-3 text-xs text-red-700 border border-red-200">
              <AlertTriangle className="h-4 w-4 text-red-600 shrink-0 mt-0.5" />
              <span>{errorMessage}</span>
            </div>
          )}

          <div
            className={`rounded-lg p-4 border text-xs leading-relaxed ${
              isCurrentlyLocked
                ? 'bg-amber-50/70 border-amber-200 text-amber-900'
                : 'bg-slate-50 border-slate-200 text-slate-700'
            }`}
          >
            {isCurrentlyLocked ? (
              <p>
                <strong>Konfirmasi Buka Kunci:</strong> Membuka kembali periode ini akan
                memungkinkan guru pengampu untuk menginput, mengubah, atau menghapus nilai siswa
                pada bulan <strong>{period.month_name || period.month}</strong>.
              </p>
            ) : (
              <p>
                <strong>Konfirmasi Penguncian:</strong> Mengunci periode ini akan membekukan seluruh
                nilai siswa pada bulan <strong>{period.month_name || period.month}</strong>. Guru
                tidak dapat lagi mengubah nilai tanpa pembukaan kunci resmi oleh pihak kurikulum.
              </p>
            )}
          </div>

          <div className="text-xs text-gray-500 space-y-1">
            <p>
              Status saat ini:{' '}
              <span
                className={`font-semibold ${
                  isCurrentlyLocked ? 'text-amber-600' : 'text-emerald-600'
                }`}
              >
                {isCurrentlyLocked ? 'Terkunci (Locked)' : 'Terbuka (Open)'}
              </span>
            </p>
            {period.locked_at && (
              <p>
                Dikunci pada: {new Date(period.locked_at).toLocaleString('id-ID')}
              </p>
            )}
          </div>

          <div className="flex justify-end gap-2 pt-3 border-t border-gray-100">
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
              type="button"
              onClick={handleToggle}
              disabled={isSubmitting}
              className={`text-xs gap-1.5 text-white ${
                isCurrentlyLocked
                  ? 'bg-amber-600 hover:bg-amber-700'
                  : 'bg-emerald-600 hover:bg-emerald-700'
              }`}
            >
              {isSubmitting ? (
                <>
                  <Loader2 className="h-3.5 w-3.5 animate-spin" />
                  Memproses...
                </>
              ) : isCurrentlyLocked ? (
                'Buka Kunci Periode'
              ) : (
                'Kunci Periode Sekarang'
              )}
            </Button>
          </div>
        </div>
      </div>
    </div>
  );
}
