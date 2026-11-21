'use client';

import React, { useState } from 'react';
import Link from 'next/link';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { studentsApi } from '@/features/master/students-api';
import {
  StudentItem,
  StudentFormValues,
  StudentStatus,
  ChangeStudentStatusFormValues,
} from '@/types/master';
import { DataTable, ColumnDef } from '@/components/shared/data-table';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { StudentDialog } from '@/features/master/student-dialog';
import { StudentStatusDialog } from '@/features/master/student-status-dialog';
import { StudentDetailDialog } from '@/features/master/student-detail-dialog';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Avatar } from '@/components/ui/avatar';
import {
  Users,
  ArrowLeft,
  Plus,
  Eye,
  Edit2,
  Trash2,
  ArrowRightLeft,
  Download,
  AlertTriangle,
} from 'lucide-react';

export default function StudentsMasterPage() {
  const queryClient = useQueryClient();

  // State Paginasi, Pencarian, & Filter
  const [page, setPage] = useState(1);
  const [pageSize] = useState(20);
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState<StudentStatus | ''>('');

  // State Dialog
  const [isCreateOpen, setIsCreateOpen] = useState(false);
  const [editingStudent, setEditingStudent] = useState<StudentItem | null>(null);
  const [detailStudent, setDetailStudent] = useState<StudentItem | null>(null);
  const [statusStudent, setStatusStudent] = useState<StudentItem | null>(null);
  const [deletingStudent, setDeletingStudent] = useState<StudentItem | null>(null);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  // TanStack Query: List Siswa
  const {
    data: studentsData,
    isLoading,
    error: queryError,
    refetch,
  } = useQuery({
    queryKey: ['students', { page, limit: pageSize, q: search, status: statusFilter }],
    queryFn: () =>
      studentsApi.list({
        page,
        limit: pageSize,
        q: search || undefined,
        status: statusFilter || undefined,
      }),
  });

  // Mutasi: Tambah Siswa
  const createMutation = useMutation({
    mutationFn: (values: StudentFormValues) => studentsApi.create(values),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['students'] });
      setIsCreateOpen(false);
      setErrorMessage(null);
    },
    onError: (err: any) => {
      setErrorMessage(err.message || 'Gagal menambahkan data siswa.');
    },
  });

  // Mutasi: Perbarui Siswa
  const updateMutation = useMutation({
    mutationFn: ({ id, values }: { id: string; values: Partial<StudentFormValues> }) =>
      studentsApi.update(id, values),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['students'] });
      setEditingStudent(null);
      setErrorMessage(null);
    },
    onError: (err: any) => {
      setErrorMessage(err.message || 'Gagal memperbarui data siswa.');
    },
  });

  // Mutasi: Ubah Status Siswa
  const statusMutation = useMutation({
    mutationFn: ({ id, values }: { id: string; values: ChangeStudentStatusFormValues }) =>
      studentsApi.changeStatus(id, values),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['students'] });
      setStatusStudent(null);
      setErrorMessage(null);
    },
    onError: (err: any) => {
      setErrorMessage(err.message || 'Gagal mengubah status siswa.');
    },
  });

  // Mutasi: Hapus Siswa
  const deleteMutation = useMutation({
    mutationFn: (id: string) => studentsApi.delete(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['students'] });
      setDeletingStudent(null);
      setErrorMessage(null);
    },
    onError: (err: any) => {
      setErrorMessage(err.message || 'Gagal menghapus data siswa.');
    },
  });

  // Export CSV Handler
  const handleExportCsv = () => {
    const apiBase = (
      process.env.NEXT_PUBLIC_API_BASE_URL ||
      process.env.NEXT_PUBLIC_API_URL ||
      'http://127.0.0.1:8000/api/v1'
    ).replace(/\/+$/, '');
    const exportUrl = apiBase.endsWith('/api/v1')
      ? `${apiBase}/students/export`
      : `${apiBase}/api/v1/students/export`;
    window.open(exportUrl, '_blank');
  };

  // Kolom DataTable
  const columns: ColumnDef<StudentItem>[] = [
    {
      key: 'nis_nisn',
      header: 'NIS / NISN',
      render: (item) => (
        <div>
          <div className="font-semibold text-slate-900 font-mono text-xs">{item.nis}</div>
          <div className="text-[11px] text-slate-400 font-mono">{item.nisn}</div>
        </div>
      ),
    },
    {
      key: 'full_name',
      header: 'Nama Siswa & Kelamin',
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
            <div className="text-[11px] text-slate-400">
              {item.gender === 'L' ? 'Laki-laki (L)' : 'Perempuan (P)'}
            </div>
          </div>
        </div>
      ),
    },
    {
      key: 'class',
      header: 'Rombel Saat Ini',
      render: (item) => {
        const currentClass = item.classes && item.classes.length > 0 ? item.classes[0] : null;
        return currentClass ? (
          <Badge variant="info" className="text-[11px]">
            {currentClass.name}
          </Badge>
        ) : (
          <span className="text-slate-400 text-xs italic">Belum Ada Rombel</span>
        );
      },
    },
    {
      key: 'entry_date',
      header: 'Tgl Masuk',
      render: (item) => (
        <span className="text-slate-600 text-xs">
          {item.entry_date ? new Date(item.entry_date).toLocaleDateString('id-ID') : '-'}
        </span>
      ),
    },
    {
      key: 'status',
      header: 'Status',
      align: 'center',
      render: (item) => {
        const badgeVariant =
          item.status === 'aktif'
            ? 'success'
            : item.status === 'lulus'
            ? 'info'
            : item.status === 'pindah'
            ? 'warning'
            : 'danger';

        return (
          <Badge variant={badgeVariant} className="text-[11px] capitalize">
            {item.status}
          </Badge>
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
            className="h-8 w-8 p-0 text-slate-500 hover:text-brand-blue"
            title="Lihat Detail Profil Siswa"
            onClick={() => setDetailStudent(item)}
          >
            <Eye className="h-4 w-4" />
          </Button>

          <Button
            variant="ghost"
            size="sm"
            className="h-8 w-8 p-0 text-slate-500 hover:text-amber-600"
            title="Ubah Status Formal"
            onClick={() => setStatusStudent(item)}
          >
            <ArrowRightLeft className="h-4 w-4" />
          </Button>

          <Button
            variant="ghost"
            size="sm"
            className="h-8 w-8 p-0 text-slate-500 hover:text-slate-900"
            title="Edit Profil Siswa"
            onClick={() => setEditingStudent(item)}
          >
            <Edit2 className="h-4 w-4" />
          </Button>

          <Button
            variant="ghost"
            size="sm"
            className="h-8 w-8 p-0 text-slate-500 hover:text-status-danger"
            title="Hapus Data Siswa"
            onClick={() => setDeletingStudent(item)}
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
            Manajemen Data Siswa
          </h1>
          <p className="mt-1 text-xs text-slate-500">
            Kelola identitas induk, status kependudukan, rombel aktif, dan riwayat mutasi siswa Labschool UM.
          </p>
        </div>

        <div className="flex items-center gap-2 self-start sm:self-auto">
          <Button
            variant="outline"
            size="sm"
            onClick={handleExportCsv}
            className="h-9 gap-1.5 text-xs text-slate-700"
          >
            <Download className="h-4 w-4" />
            <span>Export CSV</span>
          </Button>

          <Button
            variant="default"
            size="sm"
            onClick={() => setIsCreateOpen(true)}
            className="h-9 gap-1.5 text-xs shadow-sm"
          >
            <Plus className="h-4 w-4" />
            <span>Tambah Siswa Baru</span>
          </Button>
        </div>
      </div>

      {/* Banner Error jika aksi gagal */}
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
        <span className="font-semibold text-slate-700 shrink-0">Filter Status:</span>
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
        {(['aktif', 'lulus', 'pindah', 'keluar'] as StudentStatus[]).map((st) => (
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
            {st}
          </button>
        ))}
      </div>

      {/* Tabel Data Siswa */}
      <DataTable<StudentItem>
        columns={columns}
        data={studentsData?.data || []}
        keyExtractor={(item) => item.id}
        isLoading={isLoading}
        error={queryError ? 'Gagal memuat daftar siswa dari server API.' : null}
        onRetry={() => refetch()}
        searchable={true}
        searchPlaceholder="Cari berdasarkan NIS, NISN, atau Nama Siswa..."
        searchValue={search}
        onSearchChange={(val) => {
          setSearch(val);
          setPage(1);
        }}
        pagination={
          studentsData?.meta
            ? {
                page: studentsData.meta.page,
                limit: studentsData.meta.limit,
                total: studentsData.meta.total,
                totalPages: studentsData.meta.totalPages,
              }
            : undefined
        }
        onPageChange={(newPage) => setPage(newPage)}
        emptyTitle="Tidak Ada Siswa Terdaftar"
        emptyDescription="Belum ada data siswa yang cocok dengan filter atau kueri pencarian Anda."
        emptyActionLabel="Daftarkan Siswa Sekarang"
        onEmptyAction={() => setIsCreateOpen(true)}
      />

      {/* Dialog Pendaftaran Siswa Baru */}
      <StudentDialog
        isOpen={isCreateOpen}
        onClose={() => setIsCreateOpen(false)}
        onSubmit={async (values) => {
          await createMutation.mutateAsync(values);
        }}
        isLoading={createMutation.isPending}
      />

      {/* Dialog Edit Profil Siswa */}
      <StudentDialog
        isOpen={!!editingStudent}
        onClose={() => setEditingStudent(null)}
        initialData={editingStudent}
        onSubmit={async (values) => {
          if (editingStudent) {
            await updateMutation.mutateAsync({ id: editingStudent.id, values });
          }
        }}
        isLoading={updateMutation.isPending}
      />

      {/* Dialog Detail Profil Siswa */}
      <StudentDetailDialog
        isOpen={!!detailStudent}
        onClose={() => setDetailStudent(null)}
        student={detailStudent}
      />

      {/* Dialog Ubah Status Formal Siswa */}
      <StudentStatusDialog
        isOpen={!!statusStudent}
        onClose={() => setStatusStudent(null)}
        student={statusStudent}
        onSubmit={async (values) => {
          if (statusStudent) {
            await statusMutation.mutateAsync({ id: statusStudent.id, values });
          }
        }}
        isLoading={statusMutation.isPending}
      />

      {/* Dialog Konfirmasi Hapus */}
      <ConfirmDialog
        isOpen={!!deletingStudent}
        onClose={() => setDeletingStudent(null)}
        onConfirm={() => {
          if (deletingStudent) {
            deleteMutation.mutate(deletingStudent.id);
          }
        }}
        title="Hapus Data Siswa?"
        description="Data siswa akan diarsipkan secara soft-delete dan seluruh penugasan kelas aktifnya akan dinonaktifkan."
        affectedItemName={deletingStudent?.full_name}
        confirmLabel="Ya, Hapus Data"
        variant="danger"
        isLoading={deleteMutation.isPending}
        error={errorMessage}
      />
    </div>
  );
}
