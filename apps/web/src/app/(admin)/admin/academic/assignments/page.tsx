'use client';

import React, { useState, useEffect, useCallback } from 'react';
import Link from 'next/link';
import {
  FileText,
  BookOpen,
  CalendarDays,
  Clock,
  Plus,
  Search,
  Filter,
  Download,
  Eye,
  Edit2,
  Trash2,
  RefreshCw,
  AlertTriangle,
  Award,
  Users,
  CheckCircle2,
  FileCheck2,
  Layers,
  GraduationCap,
} from 'lucide-react';
import {
  AssignmentItem,
  AssignmentFilters,
  LearningMaterialItem,
  LearningMaterialFilters,
} from '@/types/academic';
import { SchoolClassItem, SubjectItem } from '@/types/master';
import { academicApi } from '@/features/academic/academic-api';
import { classesApi } from '@/features/master/classes-api';
import { subjectsApi } from '@/features/master/subjects-api';
import { ApiClientError } from '@/lib/api/errors';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { toast } from '@/components/ui/toast';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { MaterialDialog } from '@/features/academic/teaching-materials';
import { AssignmentDialog, AssignmentSubmissionsDialog } from '@/features/academic/assignments';

export default function LmsAssignmentsPage() {
  const [activeTab, setActiveTab] = useState<'assignments' | 'materials'>('assignments');

  // Master options
  const [classes, setClasses] = useState<SchoolClassItem[]>([]);
  const [subjects, setSubjects] = useState<SubjectItem[]>([]);

  // Filter states
  const [selectedClassId, setSelectedClassId] = useState<string>('');
  const [selectedSubjectId, setSelectedSubjectId] = useState<string>('');
  const [searchQuery, setSearchQuery] = useState<string>('');

  // Data states
  const [assignments, setAssignments] = useState<AssignmentItem[]>([]);
  const [materials, setMaterials] = useState<LearningMaterialItem[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  // Dialog states
  const [isAssignmentDialogOpen, setIsAssignmentDialogOpen] = useState(false);
  const [editingAssignment, setEditingAssignment] = useState<AssignmentItem | null>(null);

  const [isSubmissionsDialogOpen, setIsSubmissionsDialogOpen] = useState(false);
  const [inspectingAssignment, setInspectingAssignment] = useState<AssignmentItem | null>(null);

  const [isMaterialDialogOpen, setIsMaterialDialogOpen] = useState(false);
  const [editingMaterial, setEditingMaterial] = useState<LearningMaterialItem | null>(null);

  // Delete state
  const [deletingId, setDeletingId] = useState<string | null>(null);
  const [deleteType, setDeleteType] = useState<'assignment' | 'material' | null>(null);
  const [itemToDelete, setItemToDelete] = useState<{ id: string; type: 'assignment' | 'material' } | null>(null);

  // Initial load master data
  useEffect(() => {
    async function loadMasters() {
      try {
        const [clsRes, sbjRes] = await Promise.all([
          classesApi.list({ limit: 100 }),
          subjectsApi.list({ limit: 100 }),
        ]);
        setClasses(clsRes.data || []);
        setSubjects(sbjRes.data || []);
      } catch (e) {
        console.error('Failed to load master classes or subjects:', e);
      }
    }
    loadMasters();
  }, []);

  // Fetch data
  const fetchData = useCallback(async () => {
    try {
      setIsLoading(true);
      setErrorMessage(null);

      if (activeTab === 'assignments') {
        const filters: AssignmentFilters = {
          class_id: selectedClassId || undefined,
          subject_id: selectedSubjectId || undefined,
          search: searchQuery.trim() || undefined,
        };
        const res = await academicApi.listAssignments(filters);
        setAssignments(res.data || []);
      } else {
        const filters: LearningMaterialFilters = {
          class_id: selectedClassId || undefined,
          subject_id: selectedSubjectId || undefined,
          search: searchQuery.trim() || undefined,
        };
        const res = await academicApi.listMaterials(filters);
        setMaterials(res.data || []);
      }
    } catch (err: unknown) {
      if (err instanceof ApiClientError) {
        setErrorMessage(err.message);
      } else {
        setErrorMessage('Gagal memuat data dari server.');
      }
    } finally {
      setIsLoading(false);
    }
  }, [activeTab, selectedClassId, selectedSubjectId, searchQuery]);

  useEffect(() => {
    fetchData();
  }, [fetchData]);

  // Handlers
  const handleOpenCreateAssignment = () => {
    setEditingAssignment(null);
    setIsAssignmentDialogOpen(true);
  };

  const handleOpenEditAssignment = (item: AssignmentItem) => {
    setEditingAssignment(item);
    setIsAssignmentDialogOpen(true);
  };

  const handleOpenSubmissions = (item: AssignmentItem) => {
    setInspectingAssignment(item);
    setIsSubmissionsDialogOpen(true);
  };

  const handleOpenCreateMaterial = () => {
    setEditingMaterial(null);
    setIsMaterialDialogOpen(true);
  };

  const handleOpenEditMaterial = (item: LearningMaterialItem) => {
    setEditingMaterial(item);
    setIsMaterialDialogOpen(true);
  };

  const handleDelete = (id: string, type: 'assignment' | 'material') => {
    setItemToDelete({ id, type });
  };

  const handleConfirmDelete = async () => {
    if (!itemToDelete) return;
    const { id, type } = itemToDelete;
    try {
      setDeletingId(id);
      setDeleteType(type);
      if (type === 'assignment') {
        await academicApi.deleteAssignment(id);
        toast.success('Tugas berhasil dihapus.');
      } else {
        await academicApi.deleteMaterial(id);
        toast.success('Materi ajar berhasil dihapus.');
      }
      setItemToDelete(null);
      fetchData();
    } catch (err: unknown) {
      if (err instanceof ApiClientError) {
        toast.error(err.message);
      } else {
        toast.error('Gagal menghapus data.');
      }
    } finally {
      setDeletingId(null);
      setDeleteType(null);
    }
  };

  const formatDate = (dateStr?: string) => {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleString('id-ID', {
      day: 'numeric',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  };

  return (
    <div className="space-y-6">
      {/* Breadcrumb & Header */}
      <div>
        <nav className="flex items-center gap-2 text-xs text-gray-500 mb-2">
          <Link href="/admin" className="hover:text-gray-900 transition">
            Beranda
          </Link>
          <span>/</span>
          <Link href="/admin/academic" className="hover:text-gray-900 transition">
            Akademik & KBM
          </Link>
          <span>/</span>
          <span className="font-semibold text-gray-900">Tugas & Materi LMS</span>
        </nav>

        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <h1 className="text-xl font-bold tracking-tight text-gray-900">
              Tugas & Materi Pembelajaran (LMS Ringan)
            </h1>
            <p className="text-xs text-gray-500 mt-0.5">
              Kelola materi ajar terpadu, publikasi lembar tugas kelas, serta pemantauan pengumpulan dan penilaian siswa
            </p>
          </div>

          <div className="flex items-center gap-2">
            {activeTab === 'assignments' ? (
              <Button
                size="sm"
                onClick={handleOpenCreateAssignment}
                className="bg-emerald-600 hover:bg-emerald-700 text-white gap-1.5 text-xs"
              >
                <Plus className="h-4 w-4" />
                <span>Terbitkan Tugas Baru</span>
              </Button>
            ) : (
              <Button
                size="sm"
                onClick={handleOpenCreateMaterial}
                className="bg-emerald-600 hover:bg-emerald-700 text-white gap-1.5 text-xs"
              >
                <Plus className="h-4 w-4" />
                <span>Unggah Materi Baru</span>
              </Button>
            )}
          </div>
        </div>
      </div>

      {/* KPI Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div className="rounded-xl border border-gray-200 bg-white p-4 shadow-xs">
          <div className="flex items-center justify-between">
            <span className="text-xs font-medium text-gray-500">Penugasan Aktif</span>
            <div className="rounded-lg bg-emerald-50 p-2 text-emerald-600">
              <FileText className="h-4 w-4" />
            </div>
          </div>
          <div className="mt-2 flex items-baseline gap-2">
            <span className="text-2xl font-bold text-gray-900">{assignments.length}</span>
            <span className="text-xs text-gray-500">Tugas Terdata</span>
          </div>
        </div>

        <div className="rounded-xl border border-gray-200 bg-white p-4 shadow-xs">
          <div className="flex items-center justify-between">
            <span className="text-xs font-medium text-gray-500">Materi Terbit</span>
            <div className="rounded-lg bg-blue-50 p-2 text-blue-600">
              <BookOpen className="h-4 w-4" />
            </div>
          </div>
          <div className="mt-2 flex items-baseline gap-2">
            <span className="text-2xl font-bold text-gray-900">{materials.length}</span>
            <span className="text-xs text-gray-500">Bahan Ajar Digital</span>
          </div>
        </div>

        <div className="rounded-xl border border-gray-200 bg-white p-4 shadow-xs">
          <div className="flex items-center justify-between">
            <span className="text-xs font-medium text-gray-500">Rombel & Mapel Terhubung</span>
            <div className="rounded-lg bg-purple-50 p-2 text-purple-600">
              <Layers className="h-4 w-4" />
            </div>
          </div>
          <div className="mt-2 flex items-baseline gap-2">
            <span className="text-2xl font-bold text-gray-900">{classes.length}</span>
            <span className="text-xs text-gray-500">Kelas Aktif</span>
          </div>
        </div>
      </div>

      {/* Tabs */}
      <div className="border-b border-gray-200">
        <nav className="flex space-x-6">
          <button
            onClick={() => setActiveTab('assignments')}
            className={`pb-3 text-xs font-semibold flex items-center gap-2 border-b-2 transition ${
              activeTab === 'assignments'
                ? 'border-emerald-600 text-emerald-700'
                : 'border-transparent text-gray-500 hover:text-gray-700'
            }`}
          >
            <FileCheck2 className="h-4 w-4" />
            <span>Penugasan Kelas ({assignments.length})</span>
          </button>

          <button
            onClick={() => setActiveTab('materials')}
            className={`pb-3 text-xs font-semibold flex items-center gap-2 border-b-2 transition ${
              activeTab === 'materials'
                ? 'border-emerald-600 text-emerald-700'
                : 'border-transparent text-gray-500 hover:text-gray-700'
            }`}
          >
            <BookOpen className="h-4 w-4" />
            <span>Materi Pembelajaran ({materials.length})</span>
          </button>
        </nav>
      </div>

      {/* Filter Toolbar */}
      <div className="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 bg-white p-3.5 rounded-xl border border-gray-200 shadow-xs">
        <div className="flex flex-wrap items-center gap-3">
          {/* Class Select */}
          <div className="relative min-w-[160px]">
            <select
              value={selectedClassId}
              onChange={(e) => setSelectedClassId(e.target.value)}
              className="w-full appearance-none rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs text-gray-900 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 focus:outline-hidden"
            >
              <option value="">Semua Kelas</option>
              {classes.map((cls) => (
                <option key={cls.id} value={cls.id}>
                  {cls.name}
                </option>
              ))}
            </select>
            <Layers className="pointer-events-none absolute right-2.5 top-2 h-3.5 w-3.5 text-gray-400" />
          </div>

          {/* Subject Select */}
          <div className="relative min-w-[180px]">
            <select
              value={selectedSubjectId}
              onChange={(e) => setSelectedSubjectId(e.target.value)}
              className="w-full appearance-none rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs text-gray-900 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 focus:outline-hidden"
            >
              <option value="">Semua Mata Pelajaran</option>
              {subjects.map((sbj) => (
                <option key={sbj.id} value={sbj.id}>
                  {sbj.name} ({sbj.code})
                </option>
              ))}
            </select>
            <GraduationCap className="pointer-events-none absolute right-2.5 top-2 h-3.5 w-3.5 text-gray-400" />
          </div>
        </div>

        <div className="flex items-center gap-2">
          {/* Search bar */}
          <div className="relative flex-1 sm:w-64">
            <Search className="pointer-events-none absolute left-3 top-2 h-3.5 w-3.5 text-gray-400" />
            <Input
              type="text"
              placeholder="Cari judul..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="h-8 pl-8 text-xs"
            />
          </div>

          <button
            onClick={fetchData}
            disabled={isLoading}
            className="rounded-lg border border-gray-300 bg-white p-2 text-gray-600 hover:bg-gray-50 transition"
            title="Muat Ulang Data"
          >
            <RefreshCw className={`h-3.5 w-3.5 ${isLoading ? 'animate-spin' : ''}`} />
          </button>
        </div>
      </div>

      {/* Error Alert */}
      {errorMessage && (
        <div className="flex items-start gap-2.5 rounded-lg border border-rose-200 bg-rose-50 p-4 text-xs text-rose-800">
          <AlertTriangle className="h-4 w-4 shrink-0 text-rose-600 mt-0.5" />
          <div className="flex-1">
            <p className="font-semibold">Terjadi Kesalahan</p>
            <p className="mt-0.5">{errorMessage}</p>
          </div>
          <Button size="sm" variant="outline" onClick={fetchData} className="h-7 text-xs">
            Coba Lagi
          </Button>
        </div>
      )}

      {/* Main Content Area */}
      {isLoading ? (
        <div className="rounded-xl border border-gray-200 bg-white p-12 text-center text-xs text-gray-500">
          <RefreshCw className="mx-auto h-7 w-7 animate-spin text-emerald-600 mb-2" />
          Memuat data dari server...
        </div>
      ) : activeTab === 'assignments' ? (
        /* ================= TAB 1: PENUGASAN KELAS ================= */
        assignments.length === 0 ? (
          <div className="rounded-xl border border-gray-200 bg-white p-12 text-center">
            <FileText className="mx-auto h-10 w-10 text-gray-300 mb-2" />
            <h3 className="text-sm font-semibold text-gray-900">Belum ada penugasan kelas</h3>
            <p className="text-xs text-gray-500 mt-1 max-w-sm mx-auto">
              Belum ada tugas yang diterbitkan untuk filter yang dipilih. Silakan terbitkan tugas baru untuk kelas.
            </p>
            <Button
              size="sm"
              onClick={handleOpenCreateAssignment}
              className="mt-4 bg-emerald-600 hover:bg-emerald-700 text-white text-xs gap-1.5"
            >
              <Plus className="h-3.5 w-3.5" />
              <span>Terbitkan Tugas Baru</span>
            </Button>
          </div>
        ) : (
          <div className="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-xs">
            <table className="w-full text-left text-xs">
              <thead className="bg-gray-50 text-[11px] font-semibold text-gray-600 uppercase border-b border-gray-200">
                <tr>
                  <th className="px-4 py-3">Judul Tugas</th>
                  <th className="px-4 py-3">Kelas & Mapel</th>
                  <th className="px-4 py-3">Batas Waktu (Deadline)</th>
                  <th className="px-4 py-3">Pengumpulan</th>
                  <th className="px-4 py-3">Nilai Maks</th>
                  <th className="px-4 py-3 text-right">Aksi</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100">
                {assignments.map((item) => (
                  <tr key={item.id} className="hover:bg-gray-50/50 transition">
                    <td className="px-4 py-3">
                      <div className="font-semibold text-gray-900">{item.title}</div>
                      {item.description && (
                        <div className="text-[11px] text-gray-500 line-clamp-1 max-w-xs mt-0.5">
                          {item.description}
                        </div>
                      )}
                      {item.file && (
                        <div className="mt-1">
                          <a
                            href={item.file.url || `/api/v1/files/${item.file.id}/download`}
                            target="_blank"
                            rel="noreferrer"
                            className="inline-flex items-center gap-1 text-[11px] font-medium text-emerald-600 hover:text-emerald-700"
                          >
                            <Download className="h-3 w-3" />
                            <span>Unduh Lembar Soal</span>
                          </a>
                        </div>
                      )}
                    </td>

                    <td className="px-4 py-3">
                      <div className="font-medium text-gray-800">{item.class?.name || 'Kelas'}</div>
                      <div className="text-[11px] text-gray-500">
                        {item.subject?.name || 'Mata Pelajaran'}
                      </div>
                    </td>

                    <td className="px-4 py-3 text-gray-600">
                      <div className="flex items-center gap-1">
                        <Clock className="h-3.5 w-3.5 text-gray-400" />
                        <span>{formatDate(item.due_at)}</span>
                      </div>
                    </td>

                    <td className="px-4 py-3">
                      <span className="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2.5 py-0.5 text-[11px] font-medium text-blue-700 border border-blue-200">
                        <Users className="h-3 w-3" />
                        <span>{item.submissions_count || 0} Pengumpulan</span>
                      </span>
                    </td>

                    <td className="px-4 py-3">
                      <span className="font-semibold text-gray-800">{item.max_score}</span>
                      {item.gradeComponent && (
                        <div className="text-[10px] text-emerald-600 font-medium">
                          Sinkron: {item.gradeComponent.name}
                        </div>
                      )}
                    </td>

                    <td className="px-4 py-3 text-right">
                      <div className="flex items-center justify-end gap-1.5">
                        <Button
                          size="sm"
                          variant="outline"
                          onClick={() => handleOpenSubmissions(item)}
                          className="h-7 text-xs gap-1 text-emerald-700 border-emerald-200 hover:bg-emerald-50"
                          title="Periksa Hasil Pengumpulan & Penilaian"
                        >
                          <Eye className="h-3.5 w-3.5" />
                          <span>Pengumpulan</span>
                        </Button>

                        <button
                          onClick={() => handleOpenEditAssignment(item)}
                          className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-700 transition"
                          title="Ubah Tugas"
                        >
                          <Edit2 className="h-3.5 w-3.5" />
                        </button>

                        <button
                          onClick={() => handleDelete(item.id, 'assignment')}
                          disabled={deletingId === item.id && deleteType === 'assignment'}
                          className="rounded-lg p-1.5 text-gray-400 hover:bg-rose-50 hover:text-rose-600 transition"
                          title="Hapus Tugas"
                        >
                          <Trash2 className="h-3.5 w-3.5" />
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )
      ) : (
        /* ================= TAB 2: MATERI PEMBELAJARAN ================= */
        materials.length === 0 ? (
          <div className="rounded-xl border border-gray-200 bg-white p-12 text-center">
            <BookOpen className="mx-auto h-10 w-10 text-gray-300 mb-2" />
            <h3 className="text-sm font-semibold text-gray-900">Belum ada materi pembelajaran</h3>
            <p className="text-xs text-gray-500 mt-1 max-w-sm mx-auto">
              Belum ada materi ajar yang diunggah untuk filter yang dipilih. Silakan unggah bahan ajar digital baru.
            </p>
            <Button
              size="sm"
              onClick={handleOpenCreateMaterial}
              className="mt-4 bg-emerald-600 hover:bg-emerald-700 text-white text-xs gap-1.5"
            >
              <Plus className="h-3.5 w-3.5" />
              <span>Unggah Materi Baru</span>
            </Button>
          </div>
        ) : (
          <div className="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-xs">
            <table className="w-full text-left text-xs">
              <thead className="bg-gray-50 text-[11px] font-semibold text-gray-600 uppercase border-b border-gray-200">
                <tr>
                  <th className="px-4 py-3">Judul Materi</th>
                  <th className="px-4 py-3">Kelas & Mapel</th>
                  <th className="px-4 py-3">Guru Pengunggah</th>
                  <th className="px-4 py-3">Berkas Lampiran</th>
                  <th className="px-4 py-3">Tanggal Unggah</th>
                  <th className="px-4 py-3 text-right">Aksi</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100">
                {materials.map((item) => (
                  <tr key={item.id} className="hover:bg-gray-50/50 transition">
                    <td className="px-4 py-3">
                      <div className="font-semibold text-gray-900">{item.title}</div>
                      {item.description && (
                        <div className="text-[11px] text-gray-500 line-clamp-1 max-w-sm mt-0.5">
                          {item.description}
                        </div>
                      )}
                    </td>

                    <td className="px-4 py-3">
                      <div className="font-medium text-gray-800">{item.class?.name || 'Kelas'}</div>
                      <div className="text-[11px] text-gray-500">
                        {item.subject?.name || 'Mata Pelajaran'}
                      </div>
                    </td>

                    <td className="px-4 py-3 text-gray-600">
                      <div className="font-medium text-gray-800">
                        {item.teacher?.full_name || 'Guru Pengajar'}
                      </div>
                      {item.teacher?.nip && (
                        <div className="text-[11px] text-gray-400">NIP: {item.teacher.nip}</div>
                      )}
                    </td>

                    <td className="px-4 py-3">
                      {item.file ? (
                        <a
                          href={item.file.url || `/api/v1/files/${item.file.id}/download`}
                          target="_blank"
                          rel="noreferrer"
                          className="inline-flex items-center gap-1 text-emerald-600 hover:text-emerald-700 font-medium"
                        >
                          <Download className="h-3.5 w-3.5" />
                          <span>Unduh Materi</span>
                        </a>
                      ) : (
                        <span className="text-gray-400 italic">Tidak ada berkas</span>
                      )}
                    </td>

                    <td className="px-4 py-3 text-gray-500">
                      {formatDate(item.created_at)}
                    </td>

                    <td className="px-4 py-3 text-right">
                      <div className="flex items-center justify-end gap-1.5">
                        <button
                          onClick={() => handleOpenEditMaterial(item)}
                          className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-700 transition"
                          title="Ubah Materi"
                        >
                          <Edit2 className="h-3.5 w-3.5" />
                        </button>

                        <button
                          onClick={() => handleDelete(item.id, 'material')}
                          disabled={deletingId === item.id && deleteType === 'material'}
                          className="rounded-lg p-1.5 text-gray-400 hover:bg-rose-50 hover:text-rose-600 transition"
                          title="Hapus Materi"
                        >
                          <Trash2 className="h-3.5 w-3.5" />
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )
      )}

      {/* Dialogs */}
      <AssignmentDialog
        isOpen={isAssignmentDialogOpen}
        onClose={() => setIsAssignmentDialogOpen(false)}
        onSuccess={fetchData}
        assignment={editingAssignment}
        classes={classes}
        subjects={subjects}
        defaultClassId={selectedClassId}
        defaultSubjectId={selectedSubjectId}
      />

      <AssignmentSubmissionsDialog
        isOpen={isSubmissionsDialogOpen}
        onClose={() => setIsSubmissionsDialogOpen(false)}
        assignment={inspectingAssignment}
        onGraded={fetchData}
      />

      <MaterialDialog
        isOpen={isMaterialDialogOpen}
        onClose={() => setIsMaterialDialogOpen(false)}
        onSuccess={fetchData}
        material={editingMaterial}
        classes={classes}
        subjects={subjects}
        defaultClassId={selectedClassId}
        defaultSubjectId={selectedSubjectId}
      />

      {/* Dialog Konfirmasi Hapus Tugas / Materi */}
      <ConfirmDialog
        isOpen={!!itemToDelete}
        onClose={() => setItemToDelete(null)}
        onConfirm={handleConfirmDelete}
        title={itemToDelete?.type === 'assignment' ? 'Hapus Tugas' : 'Hapus Materi Pembelajaran'}
        description={`Apakah Anda yakin ingin menghapus ${itemToDelete?.type === 'assignment' ? 'tugas' : 'materi'} ini? Tindakan ini tidak dapat dibatalkan.`}
        confirmLabel="Ya, Hapus"
        cancelLabel="Batal"
        variant="danger"
        isLoading={!!deletingId}
      />
    </div>
  );
}
