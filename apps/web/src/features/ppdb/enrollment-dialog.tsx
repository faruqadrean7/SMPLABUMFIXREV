'use client';

import React, { useMemo } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import * as z from 'zod';
import { useQuery } from '@tanstack/react-query';
import { PPDBRankingItem, EnrollApplicantPayload } from '@/types/ppdb';
import { classesApi } from '@/features/master/classes-api';
import { academicYearsApi } from '@/features/master/academic-years-api';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { X, UserCheck, GraduationCap, CheckCircle } from 'lucide-react';

export const enrollmentSchema = z.object({
  nis: z.string().max(30, 'NIS maksimal 30 karakter').optional().or(z.literal('')),
  nisn: z.string().max(30, 'NISN maksimal 30 karakter').optional().or(z.literal('')),
  class_id: z.string().optional().or(z.literal('')),
  academic_year_id: z.string().optional().or(z.literal('')),
  entry_date: z.string().optional().or(z.literal('')),
  force_enroll: z.boolean().default(false),
  notes: z.string().max(1000, 'Catatan maksimal 1000 karakter').optional().or(z.literal('')),
});

export type EnrollmentFormValues = z.infer<typeof enrollmentSchema>;

export interface EnrollmentDialogProps {
  isOpen: boolean;
  onClose: () => void;
  onSubmit: (data: EnrollApplicantPayload) => Promise<void>;
  applicant: PPDBRankingItem | null;
  isLoading?: boolean;
}

export function EnrollmentDialog({
  isOpen,
  onClose,
  onSubmit,
  applicant,
  isLoading = false,
}: EnrollmentDialogProps) {
  // Lookups: Rombel Classes & Academic Years
  const { data: classesData } = useQuery({
    queryKey: ['classes-lookup-for-enroll'],
    queryFn: () => classesApi.list({ limit: 50 }),
    enabled: isOpen,
  });

  const { data: academicYearsData } = useQuery({
    queryKey: ['academic-years-lookup-for-enroll'],
    queryFn: () => academicYearsApi.list({ limit: 20 }),
    enabled: isOpen,
  });

  const classes = useMemo(() => classesData?.data || [], [classesData]);
  const academicYears = useMemo(() => academicYearsData?.data || [], [academicYearsData]);

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<EnrollmentFormValues>({
    resolver: zodResolver(enrollmentSchema),
    defaultValues: {
      nis: '',
      nisn: '',
      class_id: '',
      academic_year_id: '',
      entry_date: new Date().toISOString().slice(0, 10),
      force_enroll: false,
      notes: '',
    },
  });

  React.useEffect(() => {
    if (applicant) {
      const activeYear = academicYears.find((y) => y.is_active);
      reset({
        nis: '',
        nisn: '',
        class_id: classes[0]?.id || '',
        academic_year_id: activeYear?.id || academicYears[0]?.id || '',
        entry_date: new Date().toISOString().slice(0, 10),
        force_enroll: false,
        notes: `Konversi dari pendaftaran PPDB No. ${applicant.registration_no} (${applicant.track_name || 'Reguler'})`,
      });
    }
  }, [applicant, academicYears, classes, reset, isOpen]);

  if (!isOpen || !applicant) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div className="w-full max-w-lg rounded-xl bg-white shadow-xl">
        {/* Header */}
        <div className="flex items-center justify-between border-b border-gray-100 px-6 py-4">
          <div className="flex items-center gap-2">
            <div className="rounded-lg bg-emerald-50 p-2 text-emerald-600">
              <UserCheck className="h-5 w-5" />
            </div>
            <div>
              <h2 className="text-lg font-semibold text-gray-900">Konversi ke Siswa Aktif</h2>
              <p className="text-xs text-gray-500">
                Pendaftar: <span className="font-semibold text-gray-800">{applicant.full_name}</span>{' '}
                • No: {applicant.registration_no}
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
        <form onSubmit={handleSubmit(onSubmit)} className="space-y-4 px-6 py-4">
          <div className="rounded-lg bg-emerald-50/60 border border-emerald-200 p-3 text-xs text-emerald-900 flex items-start gap-2">
            <GraduationCap className="h-4 w-4 text-emerald-700 shrink-0 mt-0.5" />
            <span>
              Proses ini akan mengonversi calon siswa menjadi entitas Siswa Aktif di Master Data,
              menetapkan rombel/kelas, dan memperbarui status pendaftaran menjadi <b>ENROLLED</b>.
            </span>
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="mb-1 block text-sm font-medium text-gray-700">NIS (Sekolah)</label>
              <Input
                {...register('nis')}
                placeholder="misal: 202601001"
                disabled={isLoading || isSubmitting}
              />
              {errors.nis && <p className="mt-1 text-xs text-red-500">{errors.nis.message}</p>}
            </div>

            <div>
              <label className="mb-1 block text-sm font-medium text-gray-700">NISN (Kemendikbud)</label>
              <Input
                {...register('nisn')}
                placeholder="misal: 0012345678"
                disabled={isLoading || isSubmitting}
              />
              {errors.nisn && <p className="mt-1 text-xs text-red-500">{errors.nisn.message}</p>}
            </div>
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="mb-1 block text-sm font-medium text-gray-700">Penempatan Kelas</label>
              <select
                {...register('class_id')}
                disabled={isLoading || isSubmitting}
                className="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
              >
                <option value="">Pilih Rombel / Kelas...</option>
                {classes.map((c) => (
                  <option key={c.id} value={c.id}>
                    {c.name} ({c.gradeLevel?.name || 'Kelas'})
                  </option>
                ))}
              </select>
            </div>

            <div>
              <label className="mb-1 block text-sm font-medium text-gray-700">Tahun Ajaran Masuk</label>
              <select
                {...register('academic_year_id')}
                disabled={isLoading || isSubmitting}
                className="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
              >
                <option value="">Pilih Tahun Ajaran...</option>
                {academicYears.map((ay) => (
                  <option key={ay.id} value={ay.id}>
                    {ay.name} {ay.is_active ? '(Aktif)' : ''}
                  </option>
                ))}
              </select>
            </div>
          </div>

          <div>
            <label className="mb-1 block text-sm font-medium text-gray-700">Tanggal Masuk</label>
            <Input
              type="date"
              {...register('entry_date')}
              disabled={isLoading || isSubmitting}
            />
          </div>

          <div className="flex items-start gap-2 rounded-md bg-amber-50/60 border border-amber-200 p-2.5">
            <input
              type="checkbox"
              id="force_enroll"
              {...register('force_enroll')}
              disabled={isLoading || isSubmitting}
              className="mt-0.5 h-4 w-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
            />
            <label htmlFor="force_enroll" className="text-xs text-gray-700 cursor-pointer">
              <span className="font-semibold text-gray-900">Registrasi Ulang Offline / Konfirmasi Panitia</span>
              <br />
              <span className="text-gray-600">Centang opsi ini apabila calon siswa melakukan daftar ulang secara langsung/luring di sekolah untuk melewati validasi status online.</span>
            </label>
          </div>

          <div>
            <label className="mb-1 block text-sm font-medium text-gray-700">Catatan Penerimaan</label>
            <textarea
              {...register('notes')}
              rows={2}
              placeholder="Catatan administrasi tambahan..."
              disabled={isLoading || isSubmitting}
              className="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
            />
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
              <CheckCircle className="mr-1.5 h-4 w-4" />
              {isLoading || isSubmitting ? 'Memproses...' : 'Konfirmasi Masuk Siswa'}
            </Button>
          </div>
        </form>
      </div>
    </div>
  );
}
