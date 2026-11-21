'use client';

import React, { useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { TimeSlotFormValues } from '@/types/academic';
import { academicApi } from '../academic-api';
import { ApiClientError } from '@/lib/api/errors';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Clock, Plus, X, AlertTriangle } from 'lucide-react';

export const timeSlotSchema = z
  .object({
    name: z.string().min(2, { message: 'Nama slot minimal 2 karakter (misal: Jam ke-1)' }),
    start_time: z
      .string()
      .regex(/^([01]\d|2[0-3]):([0-5]\d)$/, { message: 'Format waktu mulai harus HH:mm (contoh: 07:00)' }),
    end_time: z
      .string()
      .regex(/^([01]\d|2[0-3]):([0-5]\d)$/, { message: 'Format waktu selesai harus HH:mm (contoh: 07:45)' }),
    day_type: z.string().default('reguler'),
  })
  .refine((data) => data.end_time > data.start_time, {
    message: 'Waktu selesai harus setelah waktu mulai',
    path: ['end_time'],
  });

interface TimeSlotDialogProps {
  isOpen: boolean;
  onClose: () => void;
  onSuccess: () => void;
}

export function TimeSlotDialog({ isOpen, onClose, onSuccess }: TimeSlotDialogProps) {
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<TimeSlotFormValues>({
    resolver: zodResolver(timeSlotSchema),
    defaultValues: {
      name: '',
      start_time: '07:00',
      end_time: '07:45',
      day_type: 'reguler',
    },
  });

  if (!isOpen) return null;

  const onSubmit = async (values: TimeSlotFormValues) => {
    setErrorMessage(null);
    try {
      await academicApi.createTimeSlot(values);
      reset();
      onSuccess();
      onClose();
    } catch (err: unknown) {
      if (err instanceof ApiClientError) {
        setErrorMessage(err.message);
      } else {
        setErrorMessage('Terjadi kesalahan saat menambahkan slot jam pelajaran.');
      }
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div className="w-full max-w-md rounded-xl bg-white shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
        <div className="flex items-center justify-between border-b border-gray-100 px-6 py-4 bg-slate-50/50">
          <div className="flex items-center gap-2.5">
            <div className="rounded-lg bg-emerald-50 p-2 text-emerald-600 border border-emerald-100">
              <Clock className="h-5 w-5" />
            </div>
            <div>
              <h2 className="text-base font-semibold text-gray-900">Tambah Jam Pelajaran</h2>
              <p className="text-xs text-gray-500">Definisikan slot jam ke-N untuk jadwal KBM</p>
            </div>
          </div>
          <button
            onClick={onClose}
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

          <div>
            <label className="mb-1 block text-xs font-medium text-gray-700">
              Nama Slot Jam <span className="text-red-500">*</span>
            </label>
            <Input
              {...register('name')}
              placeholder="Contoh: Jam ke-1 atau Istirahat 1"
              disabled={isSubmitting}
              className="text-xs"
            />
            {errors.name && <p className="mt-1 text-[11px] text-red-500">{errors.name.message}</p>}
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="mb-1 block text-xs font-medium text-gray-700">
                Waktu Mulai (HH:mm) <span className="text-red-500">*</span>
              </label>
              <Input
                {...register('start_time')}
                placeholder="07:00"
                disabled={isSubmitting}
                className="text-xs"
              />
              {errors.start_time && (
                <p className="mt-1 text-[11px] text-red-500">{errors.start_time.message}</p>
              )}
            </div>

            <div>
              <label className="mb-1 block text-xs font-medium text-gray-700">
                Waktu Selesai (HH:mm) <span className="text-red-500">*</span>
              </label>
              <Input
                {...register('end_time')}
                placeholder="07:45"
                disabled={isSubmitting}
                className="text-xs"
              />
              {errors.end_time && (
                <p className="mt-1 text-[11px] text-red-500">{errors.end_time.message}</p>
              )}
            </div>
          </div>

          <div>
            <label className="mb-1 block text-xs font-medium text-gray-700">Jenis Hari / Tipe Slot</label>
            <select
              {...register('day_type')}
              disabled={isSubmitting}
              className="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-xs shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
            >
              <option value="reguler">Reguler (Senin - Kamis & Sabtu)</option>
              <option value="jumat">Khusus Hari Jumat</option>
              <option value="upacara">Upacara / Apel Pagi</option>
            </select>
          </div>

          <div className="flex items-center justify-end gap-2.5 border-t border-gray-100 pt-4">
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={onClose}
              disabled={isSubmitting}
              className="text-xs"
            >
              Batal
            </Button>
            <Button
              type="submit"
              size="sm"
              disabled={isSubmitting}
              className="bg-emerald-600 hover:bg-emerald-700 text-white text-xs"
            >
              <Plus className="mr-1.5 h-3.5 w-3.5" />
              {isSubmitting ? 'Menyimpan...' : 'Tambah Slot'}
            </Button>
          </div>
        </form>
      </div>
    </div>
  );
}
