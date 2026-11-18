'use client';

import React, { useState, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import * as z from 'zod';
import { AcademicYearItem, SemesterItem, SemesterFormValues } from '@/types/master';
import { academicYearsApi } from '@/features/master/academic-years-api';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { formatTanggalIndo } from '@/lib/utils';
import { ApiClientError } from '@/lib/api/errors';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import {
  Calendar,
  X,
  Plus,
  CheckCircle2,
  Power,
  Edit2,
  Trash2,
  AlertCircle,
  ArrowLeft,
  Clock,
} from 'lucide-react';

export const semesterSchema = z
  .object({
    name: z.string().min(1, 'Nama semester wajib diisi').max(50, 'Maksimal 50 karakter'),
    start_date: z.string().min(1, 'Tanggal mulai wajib diisi'),
    end_date: z.string().min(1, 'Tanggal selesai wajib diisi'),
    is_active: z.boolean().optional(),
  })
  .refine((data) => new Date(data.end_date) > new Date(data.start_date), {
    message: 'Tanggal selesai harus setelah tanggal mulai',
    path: ['end_date'],
  });

export interface SemesterManagementDialogProps {
  isOpen: boolean;
  onClose: () => void;
  academicYear: AcademicYearItem | null;
}

export function SemesterManagementDialog({
  isOpen,
  onClose,
  academicYear,
}: SemesterManagementDialogProps) {
  const queryClient = useQueryClient();

  // Mode: 'list' | 'create' | 'edit'
  const [formMode, setFormMode] = useState<'list' | 'create' | 'edit'>('list');
  const [editingSemester, setEditingSemester] = useState<SemesterItem | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);
  const [deletingId, setDeletingId] = useState<string | null>(null);
  const [semesterToDelete, setSemesterToDelete] = useState<SemesterItem | null>(null);

  // Form setup
  const {
    register,
    handleSubmit,
    setValue,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<SemesterFormValues>({
    resolver: zodResolver(semesterSchema),
    defaultValues: {
      name: '',
      start_date: '',
      end_date: '',
      is_active: false,
    },
  });

  // Query semesters for this academic year
  const {
    data: semesters = [],
    isLoading,
    refetch,
  } = useQuery({
    queryKey: ['semesters', academicYear?.id],
    queryFn: () => (academicYear ? academicYearsApi.listSemesters(academicYear.id) : Promise.resolve([])),
    enabled: isOpen && !!academicYear?.id,
  });

  // Reset state when modal opens or closes
  useEffect(() => {
    if (isOpen) {
      setFormMode('list');
      setEditingSemester(null);
      setActionError(null);
    }
  }, [isOpen, academicYear]);

  // Mutations
  const createMutation = useMutation({
    mutationFn: (values: SemesterFormValues) =>
      academicYearsApi.createSemester({
        ...values,
        academic_year_id: academicYear!.id,
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['semesters', academicYear?.id] });
      queryClient.invalidateQueries({ queryKey: ['academic-years'] });
      queryClient.invalidateQueries({ queryKey: ['active-period'] });
      setFormMode('list');
      setActionError(null);
      reset();
    },
    onError: (err: unknown) => {
      const msg = err instanceof ApiClientError ? err.message : 'Gagal menambahkan semester';
      setActionError(msg);
    },
  });

  const updateMutation = useMutation({
    mutationFn: ({ id, values }: { id: string; values: SemesterFormValues }) =>
      academicYearsApi.updateSemester(id, values),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['semesters', academicYear?.id] });
      queryClient.invalidateQueries({ queryKey: ['academic-years'] });
      queryClient.invalidateQueries({ queryKey: ['active-period'] });
      setFormMode('list');
      setEditingSemester(null);
      setActionError(null);
      reset();
    },
    onError: (err: unknown) => {
      const msg = err instanceof ApiClientError ? err.message : 'Gagal memperbarui semester';
      setActionError(msg);
    },
  });

  const activateMutation = useMutation({
    mutationFn: (id: string) => academicYearsApi.activateSemester(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['semesters', academicYear?.id] });
      queryClient.invalidateQueries({ queryKey: ['academic-years'] });
      queryClient.invalidateQueries({ queryKey: ['active-period'] });
      setActionError(null);
    },
    onError: (err: unknown) => {
      const msg = err instanceof ApiClientError ? err.message : 'Gagal mengaktifkan semester';
      setActionError(msg);
    },
  });

  const deleteMutation = useMutation({
    mutationFn: (id: string) => academicYearsApi.deleteSemester(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['semesters', academicYear?.id] });
      queryClient.invalidateQueries({ queryKey: ['academic-years'] });
      setDeletingId(null);
      setActionError(null);
    },
    onError: (err: unknown) => {
      const msg = err instanceof ApiClientError ? err.message : 'Gagal menghapus semester';
      setActionError(msg);
      setDeletingId(null);
    },
  });

  if (!isOpen || !academicYear) return null;

  const handleOpenCreate = (presetName?: string) => {
    setActionError(null);
    setEditingSemester(null);

    // Default dates based on academic year
    let defaultStart = academicYear.start_date ? academicYear.start_date.substring(0, 10) : '';
    let defaultEnd = academicYear.end_date ? academicYear.end_date.substring(0, 10) : '';

    if (presetName === 'Ganjil' && academicYear.start_date) {
      defaultStart = academicYear.start_date.substring(0, 10);
    }

    reset({
      name: presetName || '',
      start_date: defaultStart,
      end_date: defaultEnd,
      is_active: semesters.length === 0, // Auto-check if first semester
    });
    setFormMode('create');
  };

  const handleOpenEdit = (sem: SemesterItem) => {
    setActionError(null);
    setEditingSemester(sem);
    reset({
      name: sem.name,
      start_date: sem.start_date ? sem.start_date.substring(0, 10) : '',
      end_date: sem.end_date ? sem.end_date.substring(0, 10) : '',
      is_active: sem.is_active,
    });
    setFormMode('edit');
  };

  const handleFormSubmit = async (values: SemesterFormValues) => {
    setActionError(null);
    if (formMode === 'edit' && editingSemester) {
      await updateMutation.mutateAsync({ id: editingSemester.id, values });
    } else {
      await createMutation.mutateAsync(values);
    }
  };

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs animate-in fade-in duration-150"
      role="dialog"
      aria-modal="true"
    >
      <div className="relative w-full max-w-2xl rounded-xl bg-white p-6 shadow-elevated border border-slate-200 space-y-5 max-h-[90vh] overflow-y-auto">
        {/* Header Modal */}
        <div className="flex items-center justify-between border-b border-slate-100 pb-3">
          <div className="flex items-center gap-2.5">
            <div className="p-2 rounded-lg bg-teal-50 text-brand-teal">
              <Calendar className="h-5 w-5" />
            </div>
            <div>
              <h3 className="text-base font-bold text-slate-900">
                Kelola Semester — T.A. {academicYear.name}
              </h3>
              <p className="text-xs text-slate-500">
                Periode: {formatTanggalIndo(academicYear.start_date)} s/d{' '}
                {formatTanggalIndo(academicYear.end_date)}
              </p>
            </div>
          </div>

          <button
            onClick={onClose}
            className="text-slate-400 hover:text-slate-600 p-1 rounded-md"
            aria-label="Tutup modal"
          >
            <X className="h-4 w-4" />
          </button>
        </div>

        {/* Action Error Banner */}
        {actionError && (
          <div
            className="p-3 rounded-lg bg-red-50 border border-red-200 text-status-danger text-xs flex items-start gap-2"
            role="alert"
          >
            <AlertCircle className="h-4 w-4 shrink-0 mt-0.5" />
            <div className="flex-1 font-medium">{actionError}</div>
            <button
              onClick={() => setActionError(null)}
              className="text-red-400 hover:text-red-700"
            >
              Tutup
            </button>
          </div>
        )}

        {/* View Mode: LIST */}
        {formMode === 'list' && (
          <div className="space-y-4">
            <div className="flex items-center justify-between">
              <span className="text-xs font-semibold text-slate-700">
                Daftar Semester Terdaftar ({semesters.length})
              </span>
              <Button
                size="sm"
                onClick={() => handleOpenCreate()}
                className="gap-1.5 text-xs h-8"
              >
                <Plus className="h-3.5 w-3.5" />
                <span>Tambah Semester</span>
              </Button>
            </div>

            {isLoading ? (
              <div className="py-8 text-center text-xs text-slate-500">Memuat semester...</div>
            ) : semesters.length === 0 ? (
              <div className="rounded-xl border border-dashed border-slate-200 p-8 text-center bg-slate-50/50 space-y-3">
                <div className="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-400">
                  <Clock className="h-5 w-5" />
                </div>
                <div>
                  <h4 className="text-sm font-semibold text-slate-800">
                    Belum Ada Semester
                  </h4>
                  <p className="text-xs text-slate-500 max-w-sm mx-auto mt-1">
                    Tahun ajaran ini belum memiliki semester terdaftar. Tambahkan Semester Ganjil dan Genap untuk memulai penjadwalan & KBM.
                  </p>
                </div>
                <div className="flex items-center justify-center gap-2 pt-2">
                  <Button
                    size="sm"
                    variant="outline"
                    onClick={() => handleOpenCreate('Ganjil')}
                    className="text-xs h-8 border-teal-200 text-brand-teal hover:bg-teal-50"
                  >
                    + Tambah Semester Ganjil
                  </Button>
                  <Button
                    size="sm"
                    variant="outline"
                    onClick={() => handleOpenCreate('Genap')}
                    className="text-xs h-8 border-blue-200 text-brand-blue hover:bg-blue-50"
                  >
                    + Tambah Semester Genap
                  </Button>
                </div>
              </div>
            ) : (
              <div className="space-y-2.5">
                {semesters.map((sem) => (
                  <div
                    key={sem.id}
                    className="flex flex-col sm:flex-row sm:items-center justify-between p-3.5 rounded-lg border border-slate-200 bg-white hover:border-slate-300 transition-colors gap-3"
                  >
                    <div className="space-y-1">
                      <div className="flex items-center gap-2">
                        <span className="font-bold text-sm text-slate-900">
                          Semester {sem.name}
                        </span>
                        {sem.is_active ? (
                          <Badge variant="success" className="gap-1 text-[10px] py-0.5">
                            <CheckCircle2 className="h-3 w-3" />
                            Aktif Berjalan
                          </Badge>
                        ) : (
                          <Badge variant="outline" className="text-[10px] py-0.5 text-slate-500">
                            Tidak Aktif
                          </Badge>
                        )}
                      </div>
                      <div className="text-xs text-slate-500">
                        {formatTanggalIndo(sem.start_date)} s/d {formatTanggalIndo(sem.end_date)}
                      </div>
                    </div>

                    <div className="flex items-center gap-1.5 self-end sm:self-auto">
                      {!sem.is_active && (
                        <Button
                          variant="outline"
                          size="sm"
                          onClick={() => activateMutation.mutate(sem.id)}
                          isLoading={activateMutation.isPending}
                          title="Aktifkan Semester Ini"
                          className="h-7 px-2.5 text-xs gap-1 border-teal-200 text-brand-teal hover:bg-teal-50"
                        >
                          <Power className="h-3 w-3" />
                          <span>Aktifkan</span>
                        </Button>
                      )}

                      <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => handleOpenEdit(sem)}
                        title="Ubah Data Semester"
                        className="h-7 w-7 p-0 text-slate-500 hover:text-slate-800"
                      >
                        <Edit2 className="h-3.5 w-3.5" />
                      </Button>

                      {!sem.is_active && (
                        <Button
                          variant="ghost"
                          size="sm"
                          onClick={() => setSemesterToDelete(sem)}
                          isLoading={deletingId === sem.id && deleteMutation.isPending}
                          title="Hapus Semester"
                          className="h-7 w-7 p-0 text-slate-400 hover:text-status-danger hover:bg-red-50"
                        >
                          <Trash2 className="h-3.5 w-3.5" />
                        </Button>
                      )}
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>
        )}

        {/* View Mode: CREATE / EDIT FORM */}
        {(formMode === 'create' || formMode === 'edit') && (
          <form onSubmit={handleSubmit(handleFormSubmit)} className="space-y-4" noValidate>
            <div className="flex items-center gap-2 border-b border-slate-100 pb-2">
              <Button
                type="button"
                variant="ghost"
                size="sm"
                onClick={() => setFormMode('list')}
                className="h-7 px-2 text-xs text-slate-500 hover:text-slate-800"
              >
                <ArrowLeft className="h-3.5 w-3.5 mr-1" />
                Kembali ke Daftar
              </Button>
              <span className="text-xs font-bold text-slate-800">
                {formMode === 'edit'
                  ? `Ubah Semester ${editingSemester?.name}`
                  : 'Tambah Semester Baru'}
              </span>
            </div>

            {/* Nama Semester */}
            <div className="space-y-1.5">
              <label htmlFor="sem-name" className="block text-xs font-semibold text-slate-700">
                Nama Semester <span className="text-status-danger">*</span>
              </label>
              <Input
                id="sem-name"
                placeholder="Contoh: Ganjil atau Genap"
                hasError={!!errors.name}
                disabled={isSubmitting}
                {...register('name')}
              />
              {/* Quick Select Buttons */}
              <div className="flex items-center gap-1.5 pt-1">
                <span className="text-[11px] text-slate-400">Pilihan cepat:</span>
                <button
                  type="button"
                  onClick={() => setValue('name', 'Ganjil', { shouldValidate: true })}
                  className="px-2 py-0.5 rounded bg-slate-100 hover:bg-slate-200 text-[11px] font-medium text-slate-700"
                >
                  Ganjil
                </button>
                <button
                  type="button"
                  onClick={() => setValue('name', 'Genap', { shouldValidate: true })}
                  className="px-2 py-0.5 rounded bg-slate-100 hover:bg-slate-200 text-[11px] font-medium text-slate-700"
                >
                  Genap
                </button>
              </div>
              {errors.name && (
                <p className="text-[11px] font-medium text-status-danger">{errors.name.message}</p>
              )}
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
              {/* Tanggal Mulai */}
              <div className="space-y-1.5">
                <label htmlFor="sem-start" className="block text-xs font-semibold text-slate-700">
                  Tanggal Mulai <span className="text-status-danger">*</span>
                </label>
                <Input
                  id="sem-start"
                  type="date"
                  hasError={!!errors.start_date}
                  disabled={isSubmitting}
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
                <label htmlFor="sem-end" className="block text-xs font-semibold text-slate-700">
                  Tanggal Selesai <span className="text-status-danger">*</span>
                </label>
                <Input
                  id="sem-end"
                  type="date"
                  hasError={!!errors.end_date}
                  disabled={isSubmitting}
                  {...register('end_date')}
                />
                {errors.end_date && (
                  <p className="text-[11px] font-medium text-status-danger">
                    {errors.end_date.message}
                  </p>
                )}
              </div>
            </div>

            {formMode === 'create' && (
              <div className="pt-1 flex items-center gap-2">
                <input
                  id="sem-active"
                  type="checkbox"
                  className="h-4 w-4 rounded border-slate-300 text-brand-teal focus:ring-brand-teal"
                  disabled={isSubmitting}
                  {...register('is_active')}
                />
                <label
                  htmlFor="sem-active"
                  className="text-xs text-slate-700 cursor-pointer select-none"
                >
                  Jadikan sebagai Semester Aktif saat disimpan
                </label>
              </div>
            )}

            <div className="flex items-center justify-end gap-2.5 pt-4 border-t border-slate-100">
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => setFormMode('list')}
                disabled={isSubmitting}
              >
                Batal
              </Button>
              <Button
                type="submit"
                size="sm"
                isLoading={isSubmitting || createMutation.isPending || updateMutation.isPending}
                disabled={isSubmitting}
              >
                {formMode === 'edit' ? 'Simpan Perubahan' : 'Tambah Semester'}
              </Button>
            </div>
          </form>
        )}

        {/* Footer info saat mode list */}
        {formMode === 'list' && (
          <div className="flex items-center justify-end pt-3 border-t border-slate-100">
            <Button variant="outline" size="sm" onClick={onClose}>
              Selesai & Tutup
            </Button>
          </div>
        )}

        {/* Konfirmasi Hapus Semester */}
        <ConfirmDialog
          isOpen={!!semesterToDelete}
          onClose={() => setSemesterToDelete(null)}
          onConfirm={() => {
            if (semesterToDelete) {
              setDeletingId(semesterToDelete.id);
              deleteMutation.mutate(semesterToDelete.id, {
                onSettled: () => setSemesterToDelete(null),
              });
            }
          }}
          title="Hapus Semester"
          description={`Apakah Anda yakin ingin menghapus Semester ${semesterToDelete?.name || ''}? Tindakan ini tidak dapat dibatalkan.`}
          confirmLabel="Ya, Hapus"
          isLoading={deleteMutation.isPending}
        />
      </div>
    </div>
  );
}
