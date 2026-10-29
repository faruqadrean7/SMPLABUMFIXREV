'use client';

import React, { useState, useMemo } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { academicApi } from '@/features/academic/academic-api';
import { subjectsApi } from '@/features/master/subjects-api';
import {
  TeachingMaterialDialog,
  TeachingMaterialVersionDialog,
  TeachingMaterialDetailDialog,
} from '@/features/academic/teaching-materials';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { toast } from '@/components/ui/toast';
import {
  TeachingMaterialItem,
  TeachingMaterialType,
  TEACHING_MATERIAL_TYPE_LABELS,
  TEACHING_MATERIAL_TYPE_COLORS,
} from '@/types/academic';
import { DataTable, ColumnDef } from '@/components/shared/data-table';
import { useAuth } from '@/providers/auth-provider';
import { hasPermission } from '@/lib/rbac/evaluator';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
  BookOpen,
  UploadCloud,
  FileText,
  Search,
  Filter,
  RotateCw,
  Download,
  Eye,
  History,
  Trash2,
  Layers,
  Sparkles,
  ExternalLink,
  PlusCircle,
  FileCheck,
} from 'lucide-react';

export default function TeachingMaterialsPage() {
  const queryClient = useQueryClient();
  const { user } = useAuth();

  // Evaluasi Hak Akses Admin (Kurikulum / Super Admin / TU)
  const isAdmin = useMemo(() => {
    if (!user) return false;
    const roles = Array.isArray(user.roles) ? user.roles : [];
    return (
      roles.includes('super_admin') ||
      roles.includes('admin_tu') ||
      roles.includes('principal') ||
      hasPermission(user, 'academic.schedule.manage')
    );
  }, [user]);

  // Filter States
  const [search, setSearch] = useState<string>('');
  const [materialTypeFilter, setMaterialTypeFilter] = useState<string>('');
  const [gradeLevelFilter, setGradeLevelFilter] = useState<string>('');
  const [subjectFilter, setSubjectFilter] = useState<string>('');
  const [page, setPage] = useState<number>(1);
  const perPage = 15;

  // Dialog States
  const [createDialogOpen, setCreateDialogOpen] = useState<boolean>(false);
  const [versionDialogOpen, setVersionDialogOpen] = useState<boolean>(false);
  const [detailDialogOpen, setDetailDialogOpen] = useState<boolean>(false);
  const [deleteDialogOpen, setDeleteDialogOpen] = useState<boolean>(false);

  const [selectedMaterial, setSelectedMaterial] = useState<TeachingMaterialItem | null>(null);
  const [selectedMaterialId, setSelectedMaterialId] = useState<string | null>(null);
  const [deleting, setDeleting] = useState<boolean>(false);
  const [deleteError, setDeleteError] = useState<string | null>(null);

  // Download loading state
  const [downloadingId, setDownloadingId] = useState<string | null>(null);

  // Fetch Auxiliary Master Data (Grade Levels & Subjects)
  const { data: gradeLevels = [] } = useQuery({
    queryKey: ['grade-levels'],
    queryFn: () => academicApi.getGradeLevels(),
  });

  const { data: subjectsData } = useQuery({
    queryKey: ['subjects-list'],
    queryFn: () => subjectsApi.list({ limit: 100 }),
  });
  const subjects = subjectsData?.data || [];

  // Fetch Teaching Materials List
  const {
    data: listData,
    isLoading: loadingList,
    error: listError,
    refetch: refetchList,
  } = useQuery({
    queryKey: [
      'teaching-materials-list',
      search,
      materialTypeFilter,
      gradeLevelFilter,
      subjectFilter,
      page,
    ],
    queryFn: () =>
      academicApi.listTeachingMaterials({
        search: search.trim() || undefined,
        material_type: (materialTypeFilter as TeachingMaterialType) || undefined,
        grade_level_id: gradeLevelFilter || undefined,
        subject_id: subjectFilter || undefined,
        page,
        per_page: perPage,
      }),
  });

  const handleResetFilters = () => {
    setSearch('');
    setMaterialTypeFilter('');
    setGradeLevelFilter('');
    setSubjectFilter('');
    setPage(1);
  };

  const handleDownload = async (fileId?: string | null) => {
    if (!fileId) return;
    setDownloadingId(fileId);
    try {
      const res = await academicApi.getFileUrl(fileId);
      if (res.url) {
        window.open(res.url, '_blank', 'noopener,noreferrer');
      }
    } catch {
      toast.error('Gagal mengambil tautan unduhan dokumen.');
    } finally {
      setDownloadingId(null);
    }
  };

  const handleOpenDetail = (item: TeachingMaterialItem) => {
    setSelectedMaterialId(item.id);
    setDetailDialogOpen(true);
  };

  const handleOpenVersion = (item: TeachingMaterialItem) => {
    setSelectedMaterial(item);
    setVersionDialogOpen(true);
  };

  const handleOpenDelete = (item: TeachingMaterialItem) => {
    setSelectedMaterial(item);
    setDeleteError(null);
    setDeleteDialogOpen(true);
  };

  const handleConfirmDelete = async () => {
    if (!selectedMaterial) return;
    setDeleting(true);
    setDeleteError(null);

    try {
      await academicApi.deleteTeachingMaterial(selectedMaterial.id);
      setDeleteDialogOpen(false);
      setSelectedMaterial(null);
      refetchList();
    } catch (err: unknown) {
      const errObj = err as { response?: { data?: { message?: string } }; message?: string };
      setDeleteError(
        errObj.response?.data?.message ||
          errObj.message ||
          'Gagal menghapus perangkat ajar.'
      );
    } finally {
      setDeleting(false);
    }
  };

  const formatFileSize = (bytes?: number) => {
    if (!bytes) return '-';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
  };

  const formatDateIndo = (dateStr?: string) => {
    if (!dateStr) return '-';
    try {
      const d = new Date(dateStr);
      return d.toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
      });
    } catch {
      return dateStr;
    }
  };

  // Table Columns Definition
  const columns: ColumnDef<TeachingMaterialItem>[] = useMemo(() => {
    return [
      {
        key: 'document',
        header: 'Judul & Berkas',
        render: (item) => {
          const fileName = item.file?.object_key?.split('/').pop() || 'Dokumen';
          return (
            <div className="space-y-0.5 max-w-sm">
              <span className="font-semibold text-gray-900 block truncate text-xs hover:text-emerald-700 cursor-pointer" onClick={() => handleOpenDetail(item)}>
                {item.title}
              </span>
              <div className="flex items-center gap-2 text-[11px] text-gray-500">
                <span className="truncate max-w-[180px]">{fileName}</span>
                <span>•</span>
                <span className="font-mono text-[10px] text-gray-400">
                  {formatFileSize(item.file?.size_bytes)}
                </span>
              </div>
            </div>
          );
        },
      },
      {
        key: 'type_level',
        header: 'Jenis & Tingkat',
        render: (item) => {
          const color = TEACHING_MATERIAL_TYPE_COLORS[item.material_type] || {
            badge: 'bg-gray-100 text-gray-700 border-gray-200',
          };
          const label = TEACHING_MATERIAL_TYPE_LABELS[item.material_type] || item.material_type;
          return (
            <div className="space-y-1">
              <span
                className={`inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold border ${color.badge}`}
              >
                {label}
              </span>
              <span className="text-[11px] text-gray-500 block">
                {item.gradeLevel ? item.gradeLevel.name : 'Semua Tingkat'}
              </span>
            </div>
          );
        },
      },
      {
        key: 'subject',
        header: 'Mata Pelajaran',
        render: (item) => (
          <div>
            <span className="font-medium text-gray-800 text-xs block truncate">
              {item.subject?.name || 'Lintas Mapel / Umum'}
            </span>
            {item.subject?.code && (
              <span className="text-[11px] text-gray-400 font-mono">
                {item.subject.code}
              </span>
            )}
          </div>
        ),
      },
      {
        key: 'uploader_version',
        header: 'Pengunggah & Versi',
        render: (item) => {
          const name =
            item.uploader?.teacher?.full_name ||
            item.uploader?.username ||
            'Guru Pengunggah';
          return (
            <div className="space-y-0.5">
              <span className="font-medium text-gray-800 text-xs block truncate">
                {name}
              </span>
              <div className="flex items-center gap-1.5 text-[11px]">
                <span className="font-bold text-blue-700 bg-blue-50 border border-blue-200 px-1.5 py-0.2 rounded text-[10px]">
                  v{item.version}
                </span>
                <span className="text-gray-400 text-[10px]">
                  {formatDateIndo(item.created_at)}
                </span>
              </div>
            </div>
          );
        },
      },
      {
        key: 'actions',
        header: 'Aksi',
        align: 'right',
        render: (item) => {
          const isOwner = user?.id === item.uploaded_by;
          const canDelete = isAdmin || isOwner;

          return (
            <div className="flex items-center justify-end gap-1.5">
              {/* Tombol Unduh */}
              <Button
                size="sm"
                variant="outline"
                onClick={() => handleDownload(item.file_id)}
                disabled={downloadingId === item.file_id}
                className="h-7 px-2 text-xs text-emerald-700 border-emerald-200 hover:bg-emerald-50 gap-1"
                title="Unduh dokumen perangkat ajar"
              >
                <Download className="h-3.5 w-3.5" />
                <span className="hidden sm:inline">Unduh</span>
              </Button>

              {/* Tombol Detail & Versi */}
              <Button
                size="sm"
                variant="outline"
                onClick={() => handleOpenDetail(item)}
                className="h-7 px-2 text-xs text-gray-700 border-gray-200 hover:bg-gray-50 gap-1"
                title="Lihat detail dan riwayat revisi"
              >
                <Eye className="h-3.5 w-3.5" />
                <span className="hidden sm:inline">Detail</span>
              </Button>

              {/* Tombol Tambah Versi Baru */}
              <Button
                size="sm"
                variant="outline"
                onClick={() => handleOpenVersion(item)}
                className="h-7 px-2 text-xs text-blue-700 border-blue-200 hover:bg-blue-50 gap-1"
                title="Unggah versi revisi baru"
              >
                <History className="h-3.5 w-3.5" />
                <span className="hidden sm:inline">+Revisi</span>
              </Button>

              {/* Tombol Hapus (Hanya pemilik atau admin) */}
              {canDelete && (
                <Button
                  size="sm"
                  variant="outline"
                  onClick={() => handleOpenDelete(item)}
                  className="h-7 px-2 text-xs text-rose-700 border-rose-200 hover:bg-rose-50"
                  title="Hapus dokumen perangkat ajar"
                >
                  <Trash2 className="h-3.5 w-3.5" />
                </Button>
              )}
            </div>
          );
        },
      },
    ];
  }, [user?.id, isAdmin, downloadingId]);

  // Hitung KPI ringkas dari data aktif
  const stats = useMemo(() => {
    const list = listData?.data || [];
    let modulCount = 0;
    let atpCount = 0;
    let silabusCount = 0;
    let p5Count = 0;

    list.forEach((item) => {
      if (item.material_type === 'modul') modulCount++;
      else if (item.material_type === 'ATP') atpCount++;
      else if (item.material_type === 'silabus') silabusCount++;
      else if (item.material_type === 'P5') p5Count++;
    });

    const total = listData?.meta?.total ?? list.length;

    return {
      total,
      modul: modulCount,
      atpSilabus: atpCount + silabusCount,
      p5: p5Count,
    };
  }, [listData]);

  return (
    <div className="space-y-6">
      {/* Top Banner / Header */}
      <div className="rounded-xl border border-emerald-100 bg-gradient-to-r from-emerald-500/10 via-teal-500/5 to-transparent p-6">
        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div className="flex items-center gap-3">
            <div className="rounded-xl bg-emerald-600 p-2.5 text-white shadow-sm">
              <BookOpen className="h-6 w-6" />
            </div>
            <div>
              <h1 className="text-xl font-bold tracking-tight text-gray-900">
                Bank Modul Ajar & Perangkat Pembelajaran
              </h1>
              <p className="text-xs text-gray-600 mt-0.5">
                Repositori terpadu modul ajar Kurikulum Merdeka, ATP, silabus, dan modul P5 Labschool UM
              </p>
            </div>
          </div>

          <div className="flex items-center gap-2">
            <Button
              onClick={() => setCreateDialogOpen(true)}
              size="sm"
              className="bg-emerald-600 hover:bg-emerald-700 text-white text-xs gap-1.5 shadow-sm"
            >
              <UploadCloud className="h-4 w-4" />
              <span>Unggah Perangkat Baru</span>
            </Button>

            <Button
              onClick={() => refetchList()}
              size="sm"
              variant="outline"
              className="text-xs border-gray-200 text-gray-700 hover:bg-gray-100"
              title="Perbarui daftar"
            >
              <RotateCw className="h-4 w-4" />
            </Button>
          </div>
        </div>
      </div>

      {/* KPI Summary Cards */}
      <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div className="rounded-xl border border-gray-200 bg-white p-4 shadow-xs">
          <div className="flex items-center justify-between text-gray-500">
            <span className="text-xs font-medium">Total Dokumen</span>
            <FileText className="h-4 w-4 text-gray-400" />
          </div>
          <div className="mt-2 text-2xl font-bold text-gray-900">
            {loadingList ? '...' : stats.total}
          </div>
          <span className="text-[11px] text-gray-500">Perangkat pembelajaran</span>
        </div>

        <div className="rounded-xl border border-emerald-100 bg-white p-4 shadow-xs">
          <div className="flex items-center justify-between text-emerald-600">
            <span className="text-xs font-medium">Modul Ajar</span>
            <BookOpen className="h-4 w-4 text-emerald-500" />
          </div>
          <div className="mt-2 text-2xl font-bold text-emerald-700">
            {loadingList ? '...' : stats.modul}
          </div>
          <span className="text-[11px] text-emerald-600">Bahan ajar materi mandiri</span>
        </div>

        <div className="rounded-xl border border-blue-100 bg-white p-4 shadow-xs">
          <div className="flex items-center justify-between text-blue-600">
            <span className="text-xs font-medium">ATP & Silabus</span>
            <Layers className="h-4 w-4 text-blue-500" />
          </div>
          <div className="mt-2 text-2xl font-bold text-blue-700">
            {loadingList ? '...' : stats.atpSilabus}
          </div>
          <span className="text-[11px] text-blue-600">Perangkat alur tujuan</span>
        </div>

        <div className="rounded-xl border border-amber-100 bg-white p-4 shadow-xs">
          <div className="flex items-center justify-between text-amber-600">
            <span className="text-xs font-medium">Modul Proyek P5</span>
            <Sparkles className="h-4 w-4 text-amber-500" />
          </div>
          <div className="mt-2 text-2xl font-bold text-amber-700">
            {loadingList ? '...' : stats.p5}
          </div>
          <span className="text-[11px] text-amber-600">Penguatan profil pelajar</span>
        </div>
      </div>

      {/* Filter Toolbar */}
      <div className="flex flex-col gap-3 rounded-xl border border-gray-200 bg-white p-4 sm:flex-row sm:items-center sm:justify-between">
        <div className="flex flex-wrap items-center gap-2">
          {/* Search */}
          <div className="relative min-w-[220px]">
            <Search className="absolute left-3 top-2.5 h-3.5 w-3.5 text-gray-400" />
            <Input
              type="text"
              placeholder="Cari judul perangkat atau materi..."
              value={search}
              onChange={(e) => {
                setSearch(e.target.value);
                setPage(1);
              }}
              className="pl-8 text-xs h-8"
            />
          </div>

          {/* Jenis Perangkat */}
          <select
            value={materialTypeFilter}
            onChange={(e) => {
              setMaterialTypeFilter(e.target.value);
              setPage(1);
            }}
            className="h-8 rounded-lg border border-gray-300 px-2.5 text-xs text-gray-700 focus:border-emerald-500 focus:outline-none"
          >
            <option value="">Semua Jenis Perangkat</option>
            <option value="modul">Modul Ajar</option>
            <option value="ATP">Alur Tujuan Pembelajaran (ATP)</option>
            <option value="silabus">Silabus / Perangkat</option>
            <option value="P5">Modul Proyek P5</option>
          </select>

          {/* Tingkat Kelas */}
          <select
            value={gradeLevelFilter}
            onChange={(e) => {
              setGradeLevelFilter(e.target.value);
              setPage(1);
            }}
            className="h-8 rounded-lg border border-gray-300 px-2.5 text-xs text-gray-700 focus:border-emerald-500 focus:outline-none"
          >
            <option value="">Semua Tingkat</option>
            {gradeLevels.map((gl) => (
              <option key={gl.id} value={gl.id}>
                {gl.name}
              </option>
            ))}
          </select>

          {/* Mata Pelajaran */}
          <select
            value={subjectFilter}
            onChange={(e) => {
              setSubjectFilter(e.target.value);
              setPage(1);
            }}
            className="h-8 rounded-lg border border-gray-300 px-2.5 text-xs text-gray-700 focus:border-emerald-500 focus:outline-none max-w-[200px]"
          >
            <option value="">Semua Mata Pelajaran</option>
            {subjects.map((sub) => (
              <option key={sub.id} value={sub.id}>
                {sub.name}
              </option>
            ))}
          </select>

          {(search || materialTypeFilter || gradeLevelFilter || subjectFilter) && (
            <Button
              onClick={handleResetFilters}
              size="sm"
              variant="ghost"
              className="h-8 text-xs text-gray-500 hover:text-gray-700"
            >
              Reset
            </Button>
          )}
        </div>
      </div>

      {/* DataTable */}
      <DataTable<TeachingMaterialItem>
        columns={columns}
        data={listData?.data || []}
        keyExtractor={(item) => item.id}
        isLoading={loadingList}
        error={listError ? 'Gagal memuat daftar perangkat pembelajaran.' : null}
        onRetry={refetchList}
        pagination={listData?.meta}
        onPageChange={(p) => setPage(p)}
        emptyTitle="Belum ada perangkat ajar"
        emptyDescription="Silakan unggah dokumen modul ajar, ATP, atau silabus baru untuk memulai."
      />

      {/* Dialogs */}
      <TeachingMaterialDialog
        isOpen={createDialogOpen}
        onClose={() => setCreateDialogOpen(false)}
        onSuccess={() => {
          refetchList();
        }}
      />

      <TeachingMaterialVersionDialog
        isOpen={versionDialogOpen}
        onClose={() => {
          setVersionDialogOpen(false);
          setSelectedMaterial(null);
        }}
        onSuccess={() => {
          refetchList();
        }}
        material={selectedMaterial}
      />

      <TeachingMaterialDetailDialog
        isOpen={detailDialogOpen}
        onClose={() => {
          setDetailDialogOpen(false);
          setSelectedMaterialId(null);
        }}
        materialId={selectedMaterialId}
        onNewVersion={() => {
          if (selectedMaterialId) {
            const found = (listData?.data || []).find((m) => m.id === selectedMaterialId);
            if (found) {
              handleOpenVersion(found);
            }
          }
        }}
      />

      <ConfirmDialog
        isOpen={deleteDialogOpen}
        onClose={() => {
          setDeleteDialogOpen(false);
          setSelectedMaterial(null);
        }}
        onConfirm={handleConfirmDelete}
        title="Hapus Perangkat Ajar"
        description="Apakah Anda yakin ingin menghapus berkas perangkat ajar ini? Dokumen dan riwayat revisinya akan dihapus dari repositori."
        affectedItemName={selectedMaterial?.title}
        confirmLabel={deleting ? 'Menghapus...' : 'Ya, Hapus'}
        variant="danger"
        isLoading={deleting}
        error={deleteError}
      />
    </div>
  );
}
