'use client';

import React, { useState } from 'react';
import Link from 'next/link';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { teachersApi } from '@/features/master/teachers-api';
import {
  TeacherItem,
  TeacherFormValues,
  TeacherEmploymentStatus,
} from '@/types/master';
import { DataTable, ColumnDef } from '@/components/shared/data-table';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { TeacherDialog } from '@/features/master/teacher-dialog';
import { TeacherDetailDialog } from '@/features/master/teacher-detail-dialog';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Avatar } from '@/components/ui/avatar';
import {
  GraduationCap,
  ArrowLeft,
  Plus,
  Eye,
  Edit2,
  Trash2,
  AlertTriangle,
  ShieldCheck,
  Receipt,
  Clock,
} from 'lucide-react';

export default function TeachersMasterPage() {
  const queryClient = useQueryClient();

  // State Paginasi, Pencarian, & Filter
  const [page, setPage] = useState(1);
  const [pageSize] = useState(20);
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState<TeacherEmploymentStatus | ''>('');

  // State Dialog
  const [isCreateOpen, setIsCreateOpen] = useState(false);
  const [editingTeacher, setEditingTeacher] = useState<TeacherItem | null>(null);
  const [detailTeacher, setDetailTeacher] = useState<TeacherItem | null>(null);
  const [deletingTeacher, setDeletingTeacher] = useState<TeacherItem | null>(null);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  // TanStack Query: List Guru
  const {
    data: teachersData,
    isLoading,
    error: queryError,
    refetch,
  } = useQuery({
    queryKey: ['teachers', { page, limit: pageSize, q: search, status: statusFilter }],
    queryFn: () =>
      teachersApi.list({
        page,
        limit: pageSize,
        q: search || undefined,
        status: statusFilter || undefined,
      }),
  });

  // Mutasi: Tambah Guru
  const createMutation = useMutation({
    mutationFn: (values: TeacherFormValues) => teachersApi.create(values),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['teachers'] });
      setIsCreateOpen(false);
      setErrorMessage(null);
    },
    onError: (err: any) => {
      setErrorMessage(err.message || 'Gagal menambahkan data pendidik.');
    },
  });

  // Mutasi: Perbarui Guru
  const updateMutation = useMutation({
    mutationFn: ({ id, values }: { id: string; values: Partial<TeacherFormValues> }) =>
      teachersApi.update(id, values),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['teachers'] });
      setEditingTeacher(null);
      setErrorMessage(null);
    },
    onError: (err: any) => {
      setErrorMessage(err.message || 'Gagal memperbarui data pendidik.');
    },
  });

  // Mutasi: Hapus Guru
  const deleteMutation = useMutation({
    mutationFn: (id: string) => teachersApi.delete(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['teachers'] });
      setDeletingTeacher(null);
      setErrorMessage(null);
    },
    onError: (err: any) => {
      setErrorMessage(
        err.response?.status === 409
          ? 'Guru tidak dapat dihapus karena masih memiliki penugasan mengajar aktif.'
          : err.message || 'Gagal menghapus data guru.'
      );
    },
  });

  // Kolom DataTable
  const columns: ColumnDef<TeacherItem>[] = [
    {
      key: 'nip_nuptk',
      header: 'NIP / NUPTK',
      render: (item) => (
        <div>
          <div className="font-semibold text-slate-900 font-mono text-xs">
            {item.nip || '-'}
          </div>
          <div className="text-[11px] text-slate-400 font-mono">
            {item.nuptk ? `NUPTK: ${item.nuptk}` : 'Tanpa NUPTK'}
          </div>
        </div>
      ),
    },
    {
      key: 'full_name',
      header: 'Nama Guru & Jabatan',
      render: (item) => (
        <div className="flex items-center gap-3">
          <Avatar
            src={item.photo_file?.url || (item as any).photo_url}
            name={item.full_name}
            size="sm"
            alt={`Foto ${item.full_name}`}
          />
          <div>
            <div className="font-semibold text-slate-900">{item.full_name}</div>
            <div className="text-[11px] text-slate-500">{item.position || 'Tenaga Pendidik'}</div>
          </div>
        </div>
      ),
    },
    {
      key: 'status',
      header: 'Kepegawaian',
      align: 'center',
      render: (item) => {
        const badgeVariant =
          item.status === 'tetap'
            ? 'success'
            : item.status === 'kontrak'
            ? 'info'
            : 'warning';

        return (
          <Badge variant={badgeVariant} className="text-[11px] capitalize">
            {item.status}
          </Badge>
        );
      },
    },
    {
      key: 'teaching_hours',
      header: 'Beban Ajar (JJM)',
      align: 'center',
      render: (item) => {
        const assignments = item.teachingAssignments || (item as any).teaching_assignments || [];
        const hours =
          item.total_teaching_hours ??
          item.totalTeachingHours ??
          assignments.reduce(
            (sum: number, a: any) => sum + (Number(a.hours_per_week) || 0),
            0
          );
        const isMet = hours >= 24;

        if (hours === 0) {
          return <span className="text-slate-400 text-xs italic">-</span>;
        }

        return (
          <div className="flex flex-col items-center">
            <span className="font-mono text-xs font-bold text-slate-800">
              {hours} JP/mgg
            </span>
            <span
              className={`text-[10px] font-medium ${
                isMet ? 'text-emerald-600' : 'text-amber-600'
              }`}
            >
              {isMet ? '✓ Memenuhi' : 'Belum 24 JP'}
            </span>
          </div>
        );
      },
    },
    {
      key: 'user',
      header: 'Akun Login',
      render: (item) =>
        item.user ? (
          <span className="inline-flex items-center gap-1.5 text-xs text-slate-700">
            <ShieldCheck className="h-3.5 w-3.5 text-emerald-600" />
            <span className="font-mono text-[11px]">{item.user.username}</span>
          </span>
        ) : (
          <span className="text-slate-400 text-xs italic">Belum Ada Akun</span>
        ),
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
            className="h-8 w-8 p-0 text-slate-500 hover:text-emerald-700 hover:bg-emerald-50"
            title="Slip Gaji & JJM Pendidik"
            onClick={() => setDetailTeacher(item)}
          >
            <Receipt className="h-4 w-4" />
          </Button>

          <Button
            variant="ghost"
            size="sm"
            className="h-8 w-8 p-0 text-slate-500 hover:text-brand-blue"
            title="Lihat Detail Profil Guru"
            onClick={() => setDetailTeacher(item)}
          >
            <Eye className="h-4 w-4" />
          </Button>

          <Button
            variant="ghost"
            size="sm"
            className="h-8 w-8 p-0 text-slate-500 hover:text-slate-900"
            title="Edit Profil Guru"
            onClick={() => setEditingTeacher(item)}
          >
            <Edit2 className="h-4 w-4" />
          </Button>

          <Button
            variant="ghost"
            size="sm"
            className="h-8 w-8 p-0 text-slate-500 hover:text-status-danger"
            title="Hapus Guru"
            onClick={() => setDeletingTeacher(item)}
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
            Manajemen Pendidik (Guru)
          </h1>
          <p className="mt-1 text-xs text-slate-500">
            Kelola data dewan guru, NIP, status kepegawaian, wali kelas, dan integrasi penugasan kurikulum.
          </p>
        </div>

        <Button
          variant="default"
          size="sm"
          onClick={() => setIsCreateOpen(true)}
          className="h-9 gap-1.5 text-xs shadow-sm self-start sm:self-auto"
        >
          <Plus className="h-4 w-4" />
          <span>Tambah Guru Baru</span>
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

      {/* Filter Status Bar */}
      <div className="flex items-center gap-2 overflow-x-auto pb-1 text-xs">
        <span className="font-semibold text-slate-700 shrink-0">Status Kepegawaian:</span>
        <button
          onClick={() => {
            setStatusFilter('');
            setPage(1);
          }}
          className={`px-3 py-1 rounded-full text-xs font-medium transition-colors ${
            statusFilter === ''
              ? 'bg-slate-900 text-white'
              : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
          }`}
        >
          Semua Status
        </button>
        {(['tetap', 'honorer', 'kontrak'] as TeacherEmploymentStatus[]).map((st) => (
          <button
            key={st}
            onClick={() => {
              setStatusFilter(st);
              setPage(1);
            }}
            className={`px-3 py-1 rounded-full text-xs font-medium capitalize transition-colors ${
              statusFilter === st
                ? 'bg-brand-blue text-white'
                : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
            }`}
          >
            Guru {st}
          </button>
        ))}
      </div>

      {/* Tabel Data Guru */}
      <DataTable<TeacherItem>
        columns={columns}
        data={teachersData?.data || []}
        keyExtractor={(item) => item.id}
        isLoading={isLoading}
        error={queryError ? 'Gagal memuat daftar guru dari server API.' : null}
        onRetry={() => refetch()}
        searchable={true}
        searchPlaceholder="Cari berdasarkan Nama, NIP, NUPTK, atau Jabatan..."
        searchValue={search}
        onSearchChange={(val) => {
          setSearch(val);
          setPage(1);
        }}
        pagination={
          teachersData?.meta
            ? {
                page: teachersData.meta.page,
                limit: teachersData.meta.limit,
                total: teachersData.meta.total,
                totalPages: teachersData.meta.totalPages,
              }
            : undefined
        }
        onPageChange={(newPage) => setPage(newPage)}
        emptyTitle="Tidak Ada Guru Terdaftar"
        emptyDescription="Belum ada data pendidik yang cocok dengan filter atau kueri pencarian Anda."
        emptyActionLabel="Tambah Guru Sekarang"
        onEmptyAction={() => setIsCreateOpen(true)}
      />

      {/* Dialog Tambah Guru */}
      <TeacherDialog
        isOpen={isCreateOpen}
        onClose={() => setIsCreateOpen(false)}
        onSubmit={async (values) => {
          await createMutation.mutateAsync(values);
        }}
        isLoading={createMutation.isPending}
      />

      {/* Dialog Edit Guru */}
      <TeacherDialog
        isOpen={!!editingTeacher}
        onClose={() => setEditingTeacher(null)}
        initialData={editingTeacher}
        onSubmit={async (values) => {
          if (editingTeacher) {
            await updateMutation.mutateAsync({ id: editingTeacher.id, values });
          }
        }}
        isLoading={updateMutation.isPending}
      />

      {/* Dialog Detail Guru */}
      <TeacherDetailDialog
        isOpen={!!detailTeacher}
        onClose={() => setDetailTeacher(null)}
        teacher={detailTeacher}
      />

      {/* Dialog Konfirmasi Hapus */}
      <ConfirmDialog
        isOpen={!!deletingTeacher}
        onClose={() => setDeletingTeacher(null)}
        onConfirm={() => {
          if (deletingTeacher) {
            deleteMutation.mutate(deletingTeacher.id);
          }
        }}
        title="Hapus Data Guru?"
        description="Data guru hanya dapat dihapus bila tidak memiliki penugasan mengajar aktif pada semester berjalan."
        affectedItemName={deletingTeacher?.full_name}
        confirmLabel="Ya, Hapus Data"
        variant="danger"
        isLoading={deleteMutation.isPending}
        error={errorMessage}
      />
    </div>
  );
}
