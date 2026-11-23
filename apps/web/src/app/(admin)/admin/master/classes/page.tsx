'use client';

import React, { useState } from 'react';
import Link from 'next/link';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { classesApi } from '@/features/master/classes-api';
import { academicYearsApi } from '@/features/master/academic-years-api';
import {
  SchoolClassItem,
  ClassFormValues,
} from '@/types/master';
import { DataTable, ColumnDef } from '@/components/shared/data-table';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { ClassDialog } from '@/features/master/class-dialog';
import { ClassRosterDialog } from '@/features/master/class-roster-dialog';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
  Layers,
  ArrowLeft,
  Plus,
  Users,
  Edit2,
  Trash2,
  AlertTriangle,
} from 'lucide-react';

export default function ClassesMasterPage() {
  const queryClient = useQueryClient();

  // State Paginasi, Pencarian, & Filter
  const [page, setPage] = useState(1);
  const [pageSize] = useState(20);
  const [search, setSearch] = useState('');
  const [selectedAcademicYear, setSelectedAcademicYear] = useState<string>('');
  const [selectedGradeLevel, setSelectedGradeLevel] = useState<string>('');

  // State Dialog
  const [isCreateOpen, setIsCreateOpen] = useState(false);
  const [editingClass, setEditingClass] = useState<SchoolClassItem | null>(null);
  const [rosterClass, setRosterClass] = useState<SchoolClassItem | null>(null);
  const [deletingClass, setDeletingClass] = useState<SchoolClassItem | null>(null);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  // Lookup data filter
  const { data: academicYears } = useQuery({
    queryKey: ['academic-years-filter'],
    queryFn: () => academicYearsApi.list({ limit: 50 }),
  });

  const { data: gradeLevels } = useQuery({
    queryKey: ['grade-levels-filter'],
    queryFn: () => classesApi.getGradeLevels(),
  });

  // TanStack Query: List Classes
  const {
    data: classesData,
    isLoading,
    error: queryError,
    refetch,
  } = useQuery({
    queryKey: [
      'classes',
      {
        page,
        limit: pageSize,
        q: search,
        academic_year_id: selectedAcademicYear,
        grade_level_id: selectedGradeLevel,
      },
    ],
    queryFn: () =>
      classesApi.list({
        page,
        limit: pageSize,
        q: search || undefined,
        academic_year_id: selectedAcademicYear || undefined,
        grade_level_id: selectedGradeLevel || undefined,
      }),
  });

  // Mutasi: Tambah Kelas
  const createMutation = useMutation({
    mutationFn: (values: ClassFormValues) => classesApi.create(values),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['classes'] });
      setIsCreateOpen(false);
      setErrorMessage(null);
    },
    onError: (err: any) => {
      setErrorMessage(err.message || 'Gagal menambahkan rombel.');
    },
  });

  // Mutasi: Perbarui Kelas
  const updateMutation = useMutation({
    mutationFn: ({ id, values }: { id: string; values: Partial<ClassFormValues> }) =>
      classesApi.update(id, values),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['classes'] });
      setEditingClass(null);
      setErrorMessage(null);
    },
    onError: (err: any) => {
      setErrorMessage(err.message || 'Gagal memperbarui rombel.');
    },
  });

  // Mutasi: Hapus Kelas
  const deleteMutation = useMutation({
    mutationFn: (id: string) => classesApi.delete(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['classes'] });
      setDeletingClass(null);
      setErrorMessage(null);
    },
    onError: (err: any) => {
      setErrorMessage(
        err.response?.status === 409
          ? 'Kelas tidak dapat dihapus karena masih memiliki siswa aktif atau penugasan mengajar terdaftar.'
          : err.message || 'Gagal menghapus rombel.'
      );
    },
  });

  // Kolom DataTable
  const columns: ColumnDef<SchoolClassItem>[] = [
    {
      key: 'name_grade',
      header: 'Nama Rombel / Tingkat',
      render: (item) => (
        <div>
          <div className="font-bold text-slate-900 text-xs">{item.name}</div>
          <div className="text-[11px] text-slate-500">
            {(item.grade_level ?? item.gradeLevel)?.name || 'Tingkat'} {item.major ? `• ${item.major.name}` : ''}
          </div>
        </div>
      ),
    },
    {
      key: 'academic_year',
      header: 'Tahun Ajaran',
      render: (item) => (
        <span className="text-xs text-slate-700 font-medium">
          {(item.academic_year ?? item.academicYear)?.name || '-'}
        </span>
      ),
    },
    {
      key: 'homeroom',
      header: 'Wali Kelas',
      render: (item) => (
        <span className="text-xs text-slate-700">
          {(item.homeroom_teacher ?? item.homeroomTeacher)?.full_name || (
            <span className="text-slate-400 italic">Belum Ditentukan</span>
          )}
        </span>
      ),
    },
    {
      key: 'capacity',
      header: 'Kapasitas & Kuota',
      render: (item) => {
        const count = item.active_students_count || 0;
        const capacity = item.capacity || 36;
        const percent = Math.min(Math.round((count / capacity) * 100), 100);
        const isFull = count >= capacity;
        const isHigh = count >= 36;

        return (
          <div className="space-y-1 w-36">
            <div className="flex items-center justify-between text-[11px]">
              <span className="font-medium text-slate-700">
                {count} / {capacity}
              </span>
              <span
                className={`font-semibold ${
                  isFull ? 'text-status-danger' : isHigh ? 'text-amber-600' : 'text-slate-500'
                }`}
              >
                {percent}%
              </span>
            </div>
            <div className="h-1.5 w-full rounded-full bg-slate-100 overflow-hidden">
              <div
                className={`h-full transition-all duration-300 ${
                  isFull ? 'bg-status-danger' : isHigh ? 'bg-amber-500' : 'bg-brand-blue'
                }`}
                style={{ width: `${percent}%` }}
              />
            </div>
          </div>
        );
      },
    },
    {
      key: 'actions',
      header: 'Aksi',
      align: 'right',
      render: (item) => (
        <div className="flex items-center justify-end gap-1">
          <Button
            variant="ghost"
            size="sm"
            className="h-8 gap-1 px-2 text-slate-600 hover:text-brand-blue"
            title="Kelola Anggota Roster Kelas"
            onClick={() => setRosterClass(item)}
          >
            <Users className="h-3.5 w-3.5" />
            <span className="text-[11px]">Roster</span>
          </Button>

          <Button
            variant="ghost"
            size="sm"
            className="h-8 w-8 p-0 text-slate-500 hover:text-slate-900"
            title="Edit Data Rombel"
            onClick={() => setEditingClass(item)}
          >
            <Edit2 className="h-4 w-4" />
          </Button>

          <Button
            variant="ghost"
            size="sm"
            className="h-8 w-8 p-0 text-slate-500 hover:text-status-danger"
            title="Hapus Rombel"
            onClick={() => setDeletingClass(item)}
          >
            <Trash2 className="h-4 w-4" />
          </Button>
        </div>
      ),
    },
  ];

  return (
    <div className="space-y-6">
      {/* Tombol Navigasi Kembali */}
      <div className="flex items-center gap-3">
        <Link href="/admin/master">
          <Button variant="outline" size="sm" className="h-8 gap-1.5 text-xs">
            <ArrowLeft className="h-3.5 w-3.5" />
            <span>Kembali ke Master Data</span>
          </Button>
        </Link>
      </div>

      {/* Header Halaman */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <div className="flex items-center gap-2 mb-1">
            <span className="text-xs font-semibold uppercase tracking-wider text-brand-blue">
              Master Data
            </span>
          </div>
          <h1 className="text-2xl font-bold tracking-tight text-slate-900">
            Rombongan Belajar (Rombel & Kelas)
          </h1>
          <p className="mt-1 text-xs text-slate-500">
            Kelola kelas rombel, penetapan wali kelas, dan pembatasan kapasitas siswa maksimal 36 siswa.
          </p>
        </div>

        <Button
          variant="default"
          size="sm"
          onClick={() => setIsCreateOpen(true)}
          className="h-9 gap-1.5 text-xs shadow-sm self-start sm:self-auto"
        >
          <Plus className="h-4 w-4" />
          <span>Buat Rombel Baru</span>
        </Button>
      </div>

      {/* Banner Error */}
      {errorMessage && (
        <div className="flex items-start gap-2.5 rounded-lg border border-red-200 bg-red-50 p-3 text-xs text-red-800 animate-in fade-in">
          <AlertTriangle className="h-4 w-4 shrink-0 text-red-600 mt-0.5" />
          <div className="flex-1">
            <p className="font-semibold">Operasi Gagal</p>
            <p className="text-[11px] text-red-700 mt-0.5">{errorMessage}</p>
          </div>
          <button
            onClick={() => setErrorMessage(null)}
            className="text-red-500 hover:text-red-700 font-bold"
          >
            ✕
          </button>
        </div>
      )}

      {/* Filter Bar */}
      <div className="flex flex-wrap items-center gap-3 text-xs bg-slate-50 p-3 rounded-lg border border-slate-200">
        <div className="flex items-center gap-2">
          <span className="font-semibold text-slate-700">Tahun Ajaran:</span>
          <select
            value={selectedAcademicYear}
            onChange={(e) => {
              setSelectedAcademicYear(e.target.value);
              setPage(1);
            }}
            className="h-8 px-2.5 rounded border border-slate-300 bg-white text-xs text-slate-800"
          >
            <option value="">Semua Tahun Ajaran</option>
            {academicYears?.data.map((ay) => (
              <option key={ay.id} value={ay.id}>
                {ay.name} {ay.is_active ? '(Aktif)' : ''}
              </option>
            ))}
          </select>
        </div>

        <div className="flex items-center gap-2">
          <span className="font-semibold text-slate-700">Tingkat Kelas:</span>
          <select
            value={selectedGradeLevel}
            onChange={(e) => {
              setSelectedGradeLevel(e.target.value);
              setPage(1);
            }}
            className="h-8 px-2.5 rounded border border-slate-300 bg-white text-xs text-slate-800"
          >
            <option value="">Semua Tingkat</option>
            {gradeLevels?.map((gl) => (
              <option key={gl.id} value={gl.id}>
                {gl.name}
              </option>
            ))}
          </select>
        </div>
      </div>

      {/* Tabel Data Rombel */}
      <DataTable<SchoolClassItem>
        columns={columns}
        data={classesData?.data || []}
        keyExtractor={(item) => item.id}
        isLoading={isLoading}
        error={queryError ? 'Gagal memuat daftar rombel dari server API.' : null}
        onRetry={() => refetch()}
        searchable={true}
        searchPlaceholder="Cari berdasarkan nama rombel..."
        searchValue={search}
        onSearchChange={(val) => {
          setSearch(val);
          setPage(1);
        }}
        pagination={
          classesData?.meta
            ? {
                page: classesData.meta.page,
                limit: classesData.meta.limit,
                total: classesData.meta.total,
                totalPages: classesData.meta.totalPages,
              }
            : undefined
        }
        onPageChange={(newPage) => setPage(newPage)}
        emptyTitle="Tidak Ada Rombel Terdaftar"
        emptyDescription="Belum ada rombel yang cocok dengan filter atau kueri pencarian Anda."
        emptyActionLabel="Buat Rombel Sekarang"
        onEmptyAction={() => setIsCreateOpen(true)}
      />

      {/* Dialog Buat Rombel Baru */}
      <ClassDialog
        isOpen={isCreateOpen}
        onClose={() => {
          setIsCreateOpen(false);
          setErrorMessage(null);
        }}
        onSubmit={async (values) => {
          await createMutation.mutateAsync(values);
        }}
        isLoading={createMutation.isPending}
        error={errorMessage}
      />

      {/* Dialog Edit Rombel */}
      <ClassDialog
        isOpen={!!editingClass}
        onClose={() => {
          setEditingClass(null);
          setErrorMessage(null);
        }}
        initialData={editingClass}
        onSubmit={async (values) => {
          if (editingClass) {
            await updateMutation.mutateAsync({ id: editingClass.id, values });
          }
        }}
        isLoading={updateMutation.isPending}
        error={errorMessage}
      />

      {/* Dialog Kelola Roster Kelas */}
      <ClassRosterDialog
        isOpen={!!rosterClass}
        onClose={() => setRosterClass(null)}
        selectedClass={rosterClass}
      />

      {/* Dialog Konfirmasi Hapus */}
      <ConfirmDialog
        isOpen={!!deletingClass}
        onClose={() => setDeletingClass(null)}
        onConfirm={() => {
          if (deletingClass) {
            deleteMutation.mutate(deletingClass.id);
          }
        }}
        title="Hapus Rombongan Belajar?"
        description="Rombel hanya dapat dihapus bila tidak memiliki siswa aktif terdaftar atau penugasan mengajar terikat."
        affectedItemName={deletingClass?.name}
        confirmLabel="Ya, Hapus Rombel"
        variant="danger"
        isLoading={deleteMutation.isPending}
        error={errorMessage}
      />
    </div>
  );
}
