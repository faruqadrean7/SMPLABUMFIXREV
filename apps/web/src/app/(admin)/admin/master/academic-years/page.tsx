'use client';

import React, { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { academicYearsApi } from '@/features/master/academic-years-api';
import { AcademicYearItem, AcademicYearFormValues } from '@/types/master';
import { DataTable, ColumnDef } from '@/components/shared/data-table';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { AcademicYearDialog } from '@/features/master/academic-year-dialog';
import { SemesterManagementDialog } from '@/features/master/semester-management-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { PermissionGate } from '@/components/shared/permission-gate';
import { formatTanggalIndo } from '@/lib/utils';
import { ApiClientError } from '@/lib/api/errors';
import {
  Calendar,
  Plus,
  CheckCircle2,
  Edit2,
  Trash2,
  Power,
  Layers,
  GraduationCap,
  AlertCircle,
} from 'lucide-react';

export default function AcademicYearsPage() {
  const queryClient = useQueryClient();

  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');

  // State Dialog Form Tambah / Edit
  const [dialogOpen, setDialogOpen] = useState(false);
  const [editingItem, setEditingItem] = useState<AcademicYearItem | null>(null);

  // State Dialog Konfirmasi Aktivasi
  const [activatingItem, setActivatingItem] = useState<AcademicYearItem | null>(null);

  // State Dialog Konfirmasi Hapus
  const [deletingItem, setDeletingItem] = useState<AcademicYearItem | null>(null);

  // State Dialog Kelola Semester
  const [managingSemesterYear, setManagingSemesterYear] = useState<AcademicYearItem | null>(null);

  // Global action error message
  const [actionError, setActionError] = useState<string | null>(null);

  // 1. Query Data Tahun Ajaran
  const { data, isLoading, isError, error, refetch } = useQuery({
    queryKey: ['academic-years', page, search],
    queryFn: () => academicYearsApi.list({ page, limit: 10, q: search }),
  });

  // 2. Mutasi Tambah Tahun Ajaran
  const createMutation = useMutation({
    mutationFn: (values: AcademicYearFormValues) => academicYearsApi.create(values),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['academic-years'] });
      queryClient.invalidateQueries({ queryKey: ['active-period'] });
      setDialogOpen(false);
      setActionError(null);
    },
    onError: (err: unknown) => {
      const msg = err instanceof ApiClientError ? err.message : 'Gagal menambahkan tahun ajaran';
      setActionError(msg);
    },
  });

  // 3. Mutasi Ubah Tahun Ajaran
  const updateMutation = useMutation({
    mutationFn: ({ id, values }: { id: string; values: AcademicYearFormValues }) =>
      academicYearsApi.update(id, values),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['academic-years'] });
      queryClient.invalidateQueries({ queryKey: ['active-period'] });
      setDialogOpen(false);
      setEditingItem(null);
      setActionError(null);
    },
    onError: (err: unknown) => {
      const msg = err instanceof ApiClientError ? err.message : 'Gagal memperbarui tahun ajaran';
      setActionError(msg);
    },
  });

  // 4. Mutasi Aktivasi Tahun Ajaran
  const activateMutation = useMutation({
    mutationFn: (id: string) => academicYearsApi.activate(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['academic-years'] });
      queryClient.invalidateQueries({ queryKey: ['active-period'] });
      setActivatingItem(null);
      setActionError(null);
    },
    onError: (err: unknown) => {
      const msg = err instanceof ApiClientError ? err.message : 'Gagal mengaktifkan tahun ajaran';
      setActionError(msg);
    },
  });

  // 5. Mutasi Hapus Tahun Ajaran
  const deleteMutation = useMutation({
    mutationFn: (id: string) => academicYearsApi.delete(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['academic-years'] });
      setDeletingItem(null);
      setActionError(null);
    },
    onError: (err: unknown) => {
      const msg = err instanceof ApiClientError ? err.message : 'Gagal menghapus tahun ajaran';
      setActionError(msg);
    },
  });

  const handleFormSubmit = async (values: AcademicYearFormValues) => {
    setActionError(null);
    if (editingItem) {
      await updateMutation.mutateAsync({ id: editingItem.id, values });
    } else {
      await createMutation.mutateAsync(values);
    }
  };

  const columns: ColumnDef<AcademicYearItem>[] = [
    {
      key: 'name',
      header: 'Tahun Ajaran',
      sortable: true,
      render: (item) => (
        <div className="flex items-center gap-2">
          <span className="font-bold text-slate-900">{item.name}</span>
          {item.is_active && (
            <Badge variant="success" className="gap-1 text-[10px]">
              <CheckCircle2 className="h-3 w-3" />
              Aktif
            </Badge>
          )}
        </div>
      ),
    },
    {
      key: 'start_date',
      header: 'Tanggal Mulai',
      render: (item) => formatTanggalIndo(item.start_date),
    },
    {
      key: 'end_date',
      header: 'Tanggal Selesai',
      render: (item) => formatTanggalIndo(item.end_date),
    },
    {
      key: 'classes_count',
      header: 'Rombel',
      align: 'center',
      render: (item) => (
        <div className="inline-flex items-center gap-1 text-slate-600 font-medium">
          <Layers className="h-3.5 w-3.5 text-slate-400" />
          <span>{item.classes_count ?? 0}</span>
        </div>
      ),
    },
    {
      key: 'semesters_count',
      header: 'Semester',
      align: 'center',
      render: (item) => (
        <button
          type="button"
          onClick={() => {
            setActionError(null);
            setManagingSemesterYear(item);
          }}
          className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-semibold text-brand-teal bg-teal-50 hover:bg-teal-100 border border-teal-200 transition-colors cursor-pointer group"
          title="Klik untuk Kelola Semester"
        >
          <Calendar className="h-3.5 w-3.5 text-brand-teal group-hover:scale-110 transition-transform" />
          <span>{item.semesters_count ?? 0} Semester</span>
        </button>
      ),
    },
    {
      key: 'actions',
      header: 'Aksi',
      align: 'right',
      render: (item) => (
        <div className="flex items-center justify-end gap-1.5">
          <Button
            variant="outline"
            size="sm"
            onClick={() => {
              setActionError(null);
              setManagingSemesterYear(item);
            }}
            title="Kelola Semester"
            className="h-8 px-2 text-xs gap-1 border-slate-200 text-slate-700 hover:bg-slate-50 hover:text-brand-teal"
          >
            <Calendar className="h-3.5 w-3.5 text-brand-teal" />
            <span className="hidden md:inline">Kelola Semester</span>
          </Button>

          {!item.is_active && (
            <Button
              variant="outline"
              size="sm"
              onClick={() => {
                setActionError(null);
                setActivatingItem(item);
              }}
              title="Aktifkan Periode"
              className="h-8 px-2 text-xs gap-1 border-teal-200 text-brand-teal hover:bg-teal-50"
            >
              <Power className="h-3.5 w-3.5" />
              <span>Aktifkan</span>
            </Button>
          )}

          <Button
            variant="ghost"
            size="sm"
            onClick={() => {
              setActionError(null);
              setEditingItem(item);
              setDialogOpen(true);
            }}
            title="Ubah Data"
            className="h-8 w-8 p-0 text-slate-500 hover:text-slate-800"
          >
            <Edit2 className="h-3.5 w-3.5" />
          </Button>

          {!item.is_active && (
            <Button
              variant="ghost"
              size="sm"
              onClick={() => {
                setActionError(null);
                setDeletingItem(item);
              }}
              title="Hapus Tahun Ajaran"
              className="h-8 w-8 p-0 text-slate-400 hover:text-status-danger hover:bg-red-50"
            >
              <Trash2 className="h-3.5 w-3.5" />
            </Button>
          )}
        </div>
      ),
    },
  ];

  return (
    <div className="space-y-6">
      {/* Header Halaman */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <div className="flex items-center gap-2 mb-1">
            <span className="text-xs font-semibold uppercase tracking-wider text-brand-blue">
              Master Data
            </span>
          </div>
          <h1 className="text-2xl font-bold tracking-tight text-slate-900">
            Tahun Ajaran & Semester
          </h1>
          <p className="mt-1 text-xs text-slate-500">
            Kelola periode akademik aktif sekolah. Pengaktifan satu tahun ajaran akan menonaktifkan
            tahun ajaran lain secara transaksional tanpa menghapus data historis.
          </p>
        </div>

        <PermissionGate permission="master.academic_year.manage">
          <Button
            onClick={() => {
              setActionError(null);
              setEditingItem(null);
              setDialogOpen(true);
            }}
            className="gap-2 shadow-sm self-start sm:self-auto"
          >
            <Plus className="h-4 w-4" />
            <span>Tambah Tahun Ajaran</span>
          </Button>
        </PermissionGate>
      </div>

      {/* Banner Error Mutasi jika ada */}
      {actionError && (
        <div
          className="p-4 rounded-lg bg-red-50 border border-red-200 text-status-danger text-xs flex items-start gap-2.5 shadow-sm"
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

      {/* Tabel Data Tahun Ajaran */}
      <DataTable
        columns={columns}
        data={data?.data || []}
        keyExtractor={(item) => item.id}
        isLoading={isLoading}
        error={isError ? (error instanceof Error ? error.message : 'Gagal memuat data') : null}
        onRetry={() => refetch()}
        searchable={true}
        searchPlaceholder="Cari nama tahun ajaran (misal 2024/2025)..."
        searchValue={search}
        onSearchChange={(val) => {
          setSearch(val);
          setPage(1);
        }}
        pagination={data?.meta}
        onPageChange={(p) => setPage(p)}
        emptyTitle="Belum Ada Tahun Ajaran"
        emptyDescription="Silakan tambahkan tahun ajaran baru untuk mengaktifkan siklus KBM sekolah."
      />

      {/* Modal Tambah / Edit */}
      <AcademicYearDialog
        isOpen={dialogOpen}
        onClose={() => {
          setDialogOpen(false);
          setEditingItem(null);
        }}
        onSubmit={handleFormSubmit}
        initialData={editingItem}
        isLoading={createMutation.isPending || updateMutation.isPending}
      />

      {/* Modal Kelola Semester */}
      <SemesterManagementDialog
        isOpen={!!managingSemesterYear}
        onClose={() => setManagingSemesterYear(null)}
        academicYear={managingSemesterYear}
      />

      {/* Dialog Konfirmasi Aktivasi */}
      <ConfirmDialog
        isOpen={!!activatingItem}
        onClose={() => setActivatingItem(null)}
        onConfirm={() => {
          if (activatingItem) activateMutation.mutate(activatingItem.id);
        }}
        title="Aktifkan Tahun Ajaran?"
        description="Mengaktifkan tahun ajaran ini akan menjadikannya periode default untuk seluruh KBM, jadwal, dan pelaporan, serta menonaktifkan tahun ajaran yang sedang berjalan saat ini."
        affectedItemName={activatingItem?.name}
        confirmLabel="Ya, Aktifkan"
        variant="primary"
        isLoading={activateMutation.isPending}
        error={actionError}
      />

      {/* Dialog Konfirmasi Hapus */}
      <ConfirmDialog
        isOpen={!!deletingItem}
        onClose={() => setDeletingItem(null)}
        onConfirm={() => {
          if (deletingItem) deleteMutation.mutate(deletingItem.id);
        }}
        title="Hapus Tahun Ajaran?"
        description="Tahun ajaran hanya dapat dihapus bila tidak aktif dan belum memiliki kelas terdaftar. Tindakan ini tidak dapat dibatalkan."
        affectedItemName={deletingItem?.name}
        confirmLabel="Ya, Hapus"
        variant="danger"
        isLoading={deleteMutation.isPending}
        error={actionError}
      />
    </div>
  );
}
