'use client';

import React, { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import * as z from 'zod';
import { StudentFormValues, StudentItem } from '@/types/master';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { X, UserPlus, Save } from 'lucide-react';
import { ProfilePhotoUploader } from '@/components/ui/profile-photo-uploader';

export const studentSchema = z.object({
  nis: z.string().min(1, 'NIS wajib diisi').max(30, 'Maksimal 30 karakter'),
  nisn: z.string().min(1, 'NISN wajib diisi').max(30, 'Maksimal 30 karakter'),
  nik: z.string().regex(/^[0-9]{16}$/, 'NIK harus berupa 16 digit angka'),
  full_name: z.string().min(1, 'Nama lengkap siswa wajib diisi').max(200, 'Maksimal 200 karakter'),
  gender: z.enum(['L', 'P'], { required_error: 'Pilih jenis kelamin' }),
  birth_place: z.string().min(1, 'Tempat lahir wajib diisi').max(100, 'Maksimal 100 karakter'),
  birth_date: z.string().min(1, 'Tanggal lahir wajib diisi').refine(
    (val) => new Date(val) < new Date(),
    'Tanggal lahir harus sebelum hari ini'
  ),
  religion: z.string().optional(),
  address: z.string().optional(),
  entry_date: z.string().min(1, 'Tanggal masuk wajib diisi'),
  status: z.enum(['aktif', 'lulus', 'pindah', 'keluar']).optional(),
  program_type: z.enum(['reguler', 'tahfidz', 'icp', 'inklusi']).optional(),
  photo_file_id: z.string().nullable().optional(),
});

export interface StudentDialogProps {
  isOpen: boolean;
  onClose: () => void;
  onSubmit: (data: StudentFormValues) => Promise<void>;
  initialData?: StudentItem | null;
  isLoading?: boolean;
}

export function StudentDialog({
  isOpen,
  onClose,
  onSubmit,
  initialData,
  isLoading = false,
}: StudentDialogProps) {
  const isEditing = !!initialData;

  const {
    register,
    handleSubmit,
    reset,
    setValue,
    watch,
    formState: { errors, isSubmitting },
  } = useForm<StudentFormValues>({
    resolver: zodResolver(studentSchema),
    defaultValues: {
      nis: '',
      nisn: '',
      nik: '',
      full_name: '',
      gender: 'L',
      birth_place: '',
      birth_date: '',
      religion: '',
      address: '',
      entry_date: new Date().toISOString().split('T')[0],
      status: 'aktif',
      program_type: 'reguler',
      photo_file_id: null,
    },
  });

  useEffect(() => {
    if (initialData) {
      reset({
        nis: initialData.nis || '',
        nisn: initialData.nisn || '',
        nik: initialData.nik || '1234567890123456', // default mock 16 digits if encrypted at rest
        full_name: initialData.full_name || '',
        gender: initialData.gender || 'L',
        birth_place: initialData.birth_place || '',
        birth_date: initialData.birth_date ? initialData.birth_date.split('T')[0] : '',
        religion: initialData.religion || '',
        address: initialData.address || '',
        entry_date: initialData.entry_date ? initialData.entry_date.split('T')[0] : '',
        status: initialData.status || 'aktif',
        program_type: initialData.program_type || 'reguler',
        photo_file_id: initialData.photo_file_id || null,
      });
    } else {
      reset({
        nis: '',
        nisn: '',
        nik: '',
        full_name: '',
        gender: 'L',
        birth_place: '',
        birth_date: '',
        religion: '',
        address: '',
        entry_date: new Date().toISOString().split('T')[0],
        status: 'aktif',
        program_type: 'reguler',
        photo_file_id: null,
      });
    }
  }, [initialData, reset, isOpen]);

  if (!isOpen) return null;

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs animate-in fade-in duration-150 overflow-y-auto"
      role="dialog"
      aria-modal="true"
    >
      <div className="relative w-full max-w-2xl rounded-xl bg-white p-6 shadow-elevated border border-slate-200 my-8">
        <div className="flex items-center justify-between pb-4 border-b border-slate-100">
          <div className="flex items-center gap-2">
            <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-50 text-brand-blue">
              {isEditing ? <Save className="h-5 w-5" /> : <UserPlus className="h-5 w-5" />}
            </div>
            <div>
              <h2 className="text-base font-bold text-slate-900">
                {isEditing ? 'Perbarui Data Siswa' : 'Pendaftaran Siswa Baru'}
              </h2>
              <p className="text-xs text-slate-500">
                {isEditing
                  ? `Mengubah profil siswa ${initialData?.full_name}`
                  : 'Lengkapi identitas kependudukan dan akademik siswa'}
              </p>
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
          {/* Komponen Unggah Foto Profil Siswa */}
          <ProfilePhotoUploader
            label="Pasfoto Resmi Siswa"
            name={watch('full_name') || initialData?.full_name}
            currentPhotoUrl={initialData?.photo_file?.url || initialData?.photo_url}
            value={watch('photo_file_id')}
            onChange={(fileId) => setValue('photo_file_id', fileId, { shouldDirty: true })}
            disabled={isLoading || isSubmitting}
            helperText="Format: JPG, PNG, atau WebP (Maks. 2 MB)"
          />
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label className="block text-xs font-semibold text-slate-700 mb-1">
                NIS (Nomor Induk Siswa) <span className="text-status-danger">*</span>
              </label>
              <Input
                {...register('nis')}
                placeholder="Contoh: 20241001"
                className="h-9 text-xs"
                disabled={isLoading || isSubmitting}
              />
              {errors.nis && <p className="mt-1 text-[11px] text-status-danger">{errors.nis.message}</p>}
            </div>

            <div>
              <label className="block text-xs font-semibold text-slate-700 mb-1">
                NISN (Nasional) <span className="text-status-danger">*</span>
              </label>
              <Input
                {...register('nisn')}
                placeholder="Contoh: 0012345678"
                className="h-9 text-xs"
                disabled={isLoading || isSubmitting}
              />
              {errors.nisn && <p className="mt-1 text-[11px] text-status-danger">{errors.nisn.message}</p>}
            </div>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div className="sm:col-span-2">
              <label className="block text-xs font-semibold text-slate-700 mb-1">
                Nama Lengkap Siswa <span className="text-status-danger">*</span>
              </label>
              <Input
                {...register('full_name')}
                placeholder="Nama lengkap sesuai akta lahir"
                className="h-9 text-xs"
                disabled={isLoading || isSubmitting}
              />
              {errors.full_name && (
                <p className="mt-1 text-[11px] text-status-danger">{errors.full_name.message}</p>
              )}
            </div>

            <div>
              <label className="block text-xs font-semibold text-slate-700 mb-1">
                NIK (16 Digit) <span className="text-status-danger">*</span>
              </label>
              <Input
                {...register('nik')}
                placeholder="3507xxxxxxxxxxxx"
                maxLength={16}
                className="h-9 text-xs font-mono"
                disabled={isLoading || isSubmitting}
              />
              {errors.nik && <p className="mt-1 text-[11px] text-status-danger">{errors.nik.message}</p>}
            </div>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
              <label className="block text-xs font-semibold text-slate-700 mb-1">
                Jenis Kelamin <span className="text-status-danger">*</span>
              </label>
              <select
                {...register('gender')}
                className="w-full h-9 px-3 rounded-md border border-slate-300 bg-white text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand-blue"
                disabled={isLoading || isSubmitting}
              >
                <option value="L">Laki-laki (L)</option>
                <option value="P">Perempuan (P)</option>
              </select>
              {errors.gender && <p className="mt-1 text-[11px] text-status-danger">{errors.gender.message}</p>}
            </div>

            <div>
              <label className="block text-xs font-semibold text-slate-700 mb-1">
                Tempat Lahir <span className="text-status-danger">*</span>
              </label>
              <Input
                {...register('birth_place')}
                placeholder="Kota/Kabupaten"
                className="h-9 text-xs"
                disabled={isLoading || isSubmitting}
              />
              {errors.birth_place && (
                <p className="mt-1 text-[11px] text-status-danger">{errors.birth_place.message}</p>
              )}
            </div>

            <div>
              <label className="block text-xs font-semibold text-slate-700 mb-1">
                Tanggal Lahir <span className="text-status-danger">*</span>
              </label>
              <Input
                {...register('birth_date')}
                type="date"
                className="h-9 text-xs"
                disabled={isLoading || isSubmitting}
              />
              {errors.birth_date && (
                <p className="mt-1 text-[11px] text-status-danger">{errors.birth_date.message}</p>
              )}
            </div>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label className="block text-xs font-semibold text-slate-700 mb-1">Agama</label>
              <select
                {...register('religion')}
                className="w-full h-9 px-3 rounded-md border border-slate-300 bg-white text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand-blue"
                disabled={isLoading || isSubmitting}
              >
                <option value="">Pilih Agama</option>
                <option value="Islam">Islam</option>
                <option value="Kristen Protestan">Kristen Protestan</option>
                <option value="Katolik">Katolik</option>
                <option value="Hindu">Hindu</option>
                <option value="Buddha">Buddha</option>
                <option value="Konghucu">Konghucu</option>
              </select>
            </div>

            <div>
              <label className="block text-xs font-semibold text-slate-700 mb-1">
                Tanggal Masuk Sekolah <span className="text-status-danger">*</span>
              </label>
              <Input
                {...register('entry_date')}
                type="date"
                className="h-9 text-xs"
                disabled={isLoading || isSubmitting}
              />
              {errors.entry_date && (
                <p className="mt-1 text-[11px] text-status-danger">{errors.entry_date.message}</p>
              )}
            </div>
          </div>

          <div>
            <label className="block text-xs font-semibold text-slate-700 mb-1">
              Program Belajar
            </label>
            <select
              {...register('program_type')}
              className="w-full h-9 px-3 rounded-md border border-slate-300 bg-white text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand-blue"
              disabled={isLoading || isSubmitting}
            >
              <option value="reguler">Reguler</option>
              <option value="tahfidz">Tahfidz Al-Qur&apos;an</option>
              <option value="icp">International Class Program (Cambridge)</option>
              <option value="inklusi">Inklusi (ABK)</option>
            </select>
            <p className="mt-1 text-[11px] text-slate-400">
              Menentukan personalisasi dashboard &amp; menu siswa.
            </p>
          </div>

          <div>
            <label className="block text-xs font-semibold text-slate-700 mb-1">Alamat Tinggal</label>
            <Input
              {...register('address')}
              placeholder="Alamat domisili lengkap beserta RT/RW"
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
              {isEditing ? 'Simpan Perubahan' : 'Daftarkan Siswa'}
            </Button>
          </div>
        </form>
      </div>
    </div>
  );
}
