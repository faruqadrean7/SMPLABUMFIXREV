'use client';

import React, { useState } from 'react';
import Link from 'next/link';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { subjectsApi } from '@/features/master/subjects-api';
import {
  SubjectItem,
  SubjectFormValues,
  TeachingAssignmentItem,
  TeachingAssignmentFormValues,
} from '@/types/master';
import { DataTable, ColumnDef } from '@/components/shared/data-table';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { SubjectDialog } from '@/features/master/subject-dialog';
import { TeachingAssignmentDialog } from '@/features/master/teaching-assignment-dialog';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
  BookOpen,
  ArrowLeft,
  Plus,
  Edit2,
  Trash2,
  AlertTriangle,
  GraduationCap,
} from 'lucide-react';

export default function SubjectsMasterPage() {
  const queryClient = useQueryClient();

  // Tab State: 'subjects' | 'assignments'
  const [activeTab, setActiveTab] = useState<'subjects' | 'assignments'>('subjects');

  // Subjects pagination & search
  const [subjectPage, setSubjectPage] = useState(1);
  const [subjectSearch, setSubjectSearch] = useState('');

  // Assignments pagination & filter
  const [assignmentPage, setAssignmentPage] = useState(1);

  // Dialog State
  const [isSubjectCreateOpen, setIsSubjectCreateOpen] = useState(false);
  const [editingSubject, setEditingSubject] = useState<SubjectItem | null>(null);
  const [deletingSubject, setDeletingSubject] = useState<SubjectItem | null>(null);

  const [isAssignmentCreateOpen, setIsAssignmentCreateOpen] = useState(false);
  const [deletingAssignment, setDeletingAssignment] = useState<TeachingAssignmentItem | null>(null);

  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  // TanStack Query: List Subjects
  const {
    data: subjectsData,
    isLoading: isLoadingSubjects,
    error: queryErrorSubjects,
    refetch: refetchSubjects,
  } = useQuery({
    queryKey: ['subjects', { page: subjectPage, q: subjectSearch }],
    queryFn: () =>
      subjectsApi.list({
        page: subjectPage,
        limit: 20,
        q: subjectSearch || undefined,
      }),
  });

  // TanStack Query: List Teaching Assignments
  const {
    data: assignmentsData,
    isLoading: isLoadingAssignments,
    error: queryErrorAssignments,
    refetch: refetchAssignments,
  } = useQuery({
    queryKey: ['teaching-assignments', { page: assignmentPage }],
    queryFn: () =>
      subjectsApi.listAssignments({
        page: assignmentPage,
        limit: 20,
      }),
    enabled: activeTab === 'assignments',
  });

  // Mutasi: Tambah Subject
  const createSubjectMutation = useMutation({
    mutationFn: (values: SubjectFormValues) => subjectsApi.create(values),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['subjects'] });
      setIsSubjectCreateOpen(false);
      setErrorMessage(null);
    },
    onError: (err: any) => {
      setErrorMessage(err.message || 'Gagal menambahkan mata pelajaran.');
    },
  });

  // Mutasi: Edit Subject
  const updateSubjectMutation = useMutation({
    mutationFn: ({ id, values }: { id: string; values: Partial<SubjectFormValues> }) =>
      subjectsApi.update(id, values),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['subjects'] });
      setEditingSubject(null);
      setErrorMessage(null);
    },
    onError: (err: any) => {
      setErrorMessage(err.message || 'Gagal memperbarui mata pelajaran.');
    },
  });

  // Mutasi: Hapus Subject
  const deleteSubjectMutation = useMutation({
    mutationFn: (id: string) => subjectsApi.delete(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['subjects'] });
      setDeletingSubject(null);
      setErrorMessage(null);
    },
    onError: (err: any) => {
      setErrorMessage(
        err.response?.status === 409
          ? 'Mata pelajaran tidak dapat dihapus karena masih memiliki penugasan mengajar terikat.'
          : err.message || 'Gagal menghapus mata pelajaran.'
      );
    },
  });

  // Mutasi: Tambah Penugasan Mengajar
  const createAssignmentMutation = useMutation({
    mutationFn: (values: TeachingAssignmentFormValues) => subjectsApi.createAssignment(values),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['teaching-assignments'] });
      setIsAssignmentCreateOpen(false);
      setErrorMessage(null);
    },
    onError: (err: any) => {
      setErrorMessage(err.message || 'Gagal menambahkan penugasan mengajar.');
    },
  });

  // Mutasi: Hapus Penugasan Mengajar
  const deleteAssignmentMutation = useMutation({
    mutationFn: (id: string) => subjectsApi.deleteAssignment(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['teaching-assignments'] });
      setDeletingAssignment(null);
      setErrorMessage(null);
    },
    onError: (err: any) => {
      setErrorMessage(err.message || 'Gagal menghapus penugasan mengajar.');
    },
  });

  // Kolom Subjects
  const subjectColumns: ColumnDef<SubjectItem>[] = [
    {
      key: 'code',
      header: 'Kode',
      render: (item) => (
        <span className="font-mono text-xs font-bold text-brand-blue uppercase">{item.code}</span>
      ),
    },
    {
      key: 'name',
      header: 'Nama Mata Pelajaran',
      render: (item) => <span className="font-semibold text-slate-900 text-xs">{item.name}</span>,
    },
    {
      key: 'subject_group',
      header: 'Kelompok Kurikulum',
      render: (item) => (
        <Badge variant="info" className="text-[11px]">
          {item.subject_group}
        </Badge>
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
            className="h-8 w-8 p-0 text-slate-500 hover:text-slate-900"
            title="Edit Mata Pelajaran"
            onClick={() => setEditingSubject(item)}
          >
            <Edit2 className="h-4 w-4" />
          </Button>

          <Button
            variant="ghost"
            size="sm"
            className="h-8 w-8 p-0 text-slate-500 hover:text-status-danger"
            title="Hapus Mata Pelajaran"
            onClick={() => setDeletingSubject(item)}
          >
            <Trash2 className="h-4 w-4" />
          </Button>
        </div>
      ),
    },
  ];

  // Kolom Teaching Assignments
  const assignmentColumns: ColumnDef<TeachingAssignmentItem>[] = [
    {
      key: 'subject',
      header: 'Mata Pelajaran',
      render: (item) => (
        <div>
          <span className="font-bold text-slate-900 text-xs">
            {item.subject?.name || 'Mata Pelajaran'}
          </span>
          <span className="text-[11px] text-slate-400 font-mono block">
            {item.subject?.code || '-'}
          </span>
        </div>
      ),
    },
    {
      key: 'class',
      header: 'Rombel / Kelas',
      render: (item) => (
        <Badge variant="info" className="text-[11px]">
          {item.class?.name || '-'}
        </Badge>
      ),
    },
    {
      key: 'teacher',
      header: 'Guru Pengampu',
      render: (item) => (
        <span className="text-xs font-semibold text-slate-800">
          {item.teacher?.full_name || '-'}
        </span>
      ),
    },
    {
      key: 'hours',
      header: 'Jam/Minggu',
      align: 'center',
      render: (item) => (
        <span className="text-xs font-mono text-slate-600">
          {item.hours_per_week ? `${item.hours_per_week} Jam` : '-'}
        </span>
      ),
    },
    {
      key: 'actions',
      header: 'Aksi',
      align: 'right',
      render: (item) => (
        <Button
          variant="ghost"
          size="sm"
          className="h-8 w-8 p-0 text-slate-500 hover:text-status-danger"
          title="Hapus Penugasan Mengajar"
          onClick={() => setDeletingAssignment(item)}
        >
          <Trash2 className="h-4 w-4" />
        </Button>
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
            Mata Pelajaran & Penugasan Mengajar
          </h1>
          <p className="mt-1 text-xs text-slate-500">
            Kelola katalog kurikulum mata pelajaran dan pemetaan dewan guru pengampu per rombongan belajar.
          </p>
        </div>

        {activeTab === 'subjects' ? (
          <Button
            variant="default"
            size="sm"
            onClick={() => setIsSubjectCreateOpen(true)}
            className="h-9 gap-1.5 text-xs shadow-sm self-start sm:self-auto"
          >
            <Plus className="h-4 w-4" />
            <span>Tambah Mata Pelajaran</span>
          </Button>
        ) : (
          <Button
            variant="default"
            size="sm"
            onClick={() => setIsAssignmentCreateOpen(true)}
            className="h-9 gap-1.5 text-xs shadow-sm self-start sm:self-auto"
          >
            <Plus className="h-4 w-4" />
            <span>Tambah Penugasan Guru</span>
          </Button>
        )}
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

      {/* Tab Switcher */}
      <div className="flex border-b border-slate-200 gap-6 text-sm">
        <button
          onClick={() => setActiveTab('subjects')}
          className={`pb-3 font-semibold transition-colors flex items-center gap-2 ${
            activeTab === 'subjects'
              ? 'border-b-2 border-brand-blue text-brand-blue'
              : 'text-slate-500 hover:text-slate-700'
          }`}
        >
          <BookOpen className="h-4 w-4" />
          <span>Katalog Mata Pelajaran</span>
        </button>

        <button
          onClick={() => setActiveTab('assignments')}
          className={`pb-3 font-semibold transition-colors flex items-center gap-2 ${
            activeTab === 'assignments'
              ? 'border-b-2 border-brand-blue text-brand-blue'
              : 'text-slate-500 hover:text-slate-700'
          }`}
        >
          <GraduationCap className="h-4 w-4" />
          <span>Penugasan Mengajar Guru</span>
        </button>
      </div>

      {/* Konten Tab 1: Subjects */}
      {activeTab === 'subjects' && (
        <DataTable<SubjectItem>
          columns={subjectColumns}
          data={subjectsData?.data || []}
          keyExtractor={(item) => item.id}
          isLoading={isLoadingSubjects}
          error={queryErrorSubjects ? 'Gagal memuat mata pelajaran dari server API.' : null}
          onRetry={() => refetchSubjects()}
          searchable={true}
          searchPlaceholder="Cari kode atau nama mata pelajaran..."
          searchValue={subjectSearch}
          onSearchChange={(val) => {
            setSubjectSearch(val);
            setSubjectPage(1);
          }}
          pagination={
            subjectsData?.meta
              ? {
                  page: subjectsData.meta.page,
                  limit: subjectsData.meta.limit,
                  total: subjectsData.meta.total,
                  totalPages: subjectsData.meta.totalPages,
                }
              : undefined
          }
          onPageChange={(p) => setSubjectPage(p)}
          emptyTitle="Tidak Ada Mata Pelajaran"
          emptyDescription="Belum ada mata pelajaran yang cocok dengan kueri pencarian Anda."
          emptyActionLabel="Tambah Mata Pelajaran Sekarang"
          onEmptyAction={() => setIsSubjectCreateOpen(true)}
        />
      )}

      {/* Konten Tab 2: Teaching Assignments */}
      {activeTab === 'assignments' && (
        <DataTable<TeachingAssignmentItem>
          columns={assignmentColumns}
          data={assignmentsData?.data || []}
          keyExtractor={(item) => item.id}
          isLoading={isLoadingAssignments}
          error={queryErrorAssignments ? 'Gagal memuat penugasan mengajar dari server API.' : null}
          onRetry={() => refetchAssignments()}
          searchable={false}
          pagination={
            assignmentsData?.meta
              ? {
                  page: assignmentsData.meta.page,
                  limit: assignmentsData.meta.limit,
                  total: assignmentsData.meta.total,
                  totalPages: assignmentsData.meta.totalPages,
                }
              : undefined
          }
          onPageChange={(p) => setAssignmentPage(p)}
          emptyTitle="Tidak Ada Penugasan Mengajar"
          emptyDescription="Belum ada guru yang ditugaskan mengajar pada semester aktif."
          emptyActionLabel="Tambah Penugasan Mengajar"
          onEmptyAction={() => setIsAssignmentCreateOpen(true)}
        />
      )}

      {/* Dialog Tambah Subject */}
      <SubjectDialog
        isOpen={isSubjectCreateOpen}
        onClose={() => setIsSubjectCreateOpen(false)}
        onSubmit={async (values) => {
          await createSubjectMutation.mutateAsync(values);
        }}
        isLoading={createSubjectMutation.isPending}
      />

      {/* Dialog Edit Subject */}
      <SubjectDialog
        isOpen={!!editingSubject}
        onClose={() => setEditingSubject(null)}
        initialData={editingSubject}
        onSubmit={async (values) => {
          if (editingSubject) {
            await updateSubjectMutation.mutateAsync({ id: editingSubject.id, values });
          }
        }}
        isLoading={updateSubjectMutation.isPending}
      />

      {/* Dialog Tambah Teaching Assignment */}
      <TeachingAssignmentDialog
        isOpen={isAssignmentCreateOpen}
        onClose={() => setIsAssignmentCreateOpen(false)}
        onSubmit={async (values) => {
          await createAssignmentMutation.mutateAsync(values);
        }}
        isLoading={createAssignmentMutation.isPending}
      />

      {/* Dialog Konfirmasi Hapus Subject */}
      <ConfirmDialog
        isOpen={!!deletingSubject}
        onClose={() => setDeletingSubject(null)}
        onConfirm={() => {
          if (deletingSubject) {
            deleteSubjectMutation.mutate(deletingSubject.id);
          }
        }}
        title="Hapus Mata Pelajaran?"
        description="Mata pelajaran hanya dapat dihapus bila belum memiliki penugasan mengajar terikat."
        affectedItemName={deletingSubject?.name}
        confirmLabel="Ya, Hapus Mata Pelajaran"
        variant="danger"
        isLoading={deleteSubjectMutation.isPending}
        error={errorMessage}
      />

      {/* Dialog Konfirmasi Hapus Penugasan Mengajar */}
      <ConfirmDialog
        isOpen={!!deletingAssignment}
        onClose={() => setDeletingAssignment(null)}
        onConfirm={() => {
          if (deletingAssignment) {
            deleteAssignmentMutation.mutate(deletingAssignment.id);
          }
        }}
        title="Hapus Penugasan Mengajar?"
        description="Tindakan ini akan membatalkan penugasan guru untuk mata pelajaran pada rombel ini."
        affectedItemName={`${deletingAssignment?.subject?.name || 'Mapel'} (${deletingAssignment?.class?.name || 'Kelas'})`}
        confirmLabel="Ya, Hapus Penugasan"
        variant="danger"
        isLoading={deleteAssignmentMutation.isPending}
        error={errorMessage}
      />
    </div>
  );
}
