'use client';

import React, { useCallback, useEffect, useMemo, useState } from 'react';
import Link from 'next/link';
import {
  Award,
  BookOpen,
  CalendarDays,
  CheckCircle2,
  Clock,
  Edit2,
  FileSpreadsheet,
  Filter,
  HelpCircle,
  Info,
  Lock,
  Plus,
  RefreshCw,
  Save,
  Search,
  Trash2,
  Unlock,
  Users,
  AlertTriangle,
  AlertCircle,
  Loader2,
  ArrowLeft,
  Sparkles,
  MessageSquare,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
  CharacterNoteItem,
  ClassCharacterNotesMatrix,
  GradePeriodItem,
  MatrixCharacterStudentRow,
  CHARACTER_ASPECTS,
  CHARACTER_RATING_LABELS,
  CHARACTER_RATING_COLORS,
  CharacterRating,
} from '@/types/academic';
import { AcademicYearItem, SemesterItem, SchoolClassItem } from '@/types/master';
import { academicApi } from '@/features/academic/academic-api';
import { classesApi } from '@/features/master/classes-api';
import { academicYearsApi } from '@/features/master/academic-years-api';
import { CharacterNoteDialog } from '@/features/academic/character-notes';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { toast } from '@/components/ui/toast';
import { ApiClientError } from '@/lib/api/errors';

export default function CharacterNotesPage() {
  const [activeTab, setActiveTab] = useState<'roster' | 'batch'>('roster');

  // Master Data Dropdowns
  const [academicYears, setAcademicYears] = useState<AcademicYearItem[]>([]);
  const [semesters, setSemesters] = useState<SemesterItem[]>([]);
  const [classes, setClasses] = useState<SchoolClassItem[]>([]);
  const [periods, setPeriods] = useState<GradePeriodItem[]>([]);

  // Selected Filters
  const [selectedYearId, setSelectedYearId] = useState<string>('');
  const [selectedSemesterId, setSelectedSemesterId] = useState<string>('');
  const [selectedClassId, setSelectedClassId] = useState<string>('');
  const [selectedPeriodId, setSelectedPeriodId] = useState<string>('');

  // Class Matrix Data
  const [matrixData, setMatrixData] = useState<ClassCharacterNotesMatrix | null>(null);
  const [isLoadingMatrix, setIsLoadingMatrix] = useState<boolean>(false);
  const [matrixError, setMatrixError] = useState<string | null>(null);

  // Search & Filter within roster
  const [searchQuery, setSearchQuery] = useState<string>('');
  const [statusFilter, setStatusFilter] = useState<'all' | 'complete' | 'incomplete'>('all');

  // Dialog State
  const [isDialogOpen, setIsDialogOpen] = useState<boolean>(false);
  const [selectedStudentForDialog, setSelectedStudentForDialog] = useState<
    MatrixCharacterStudentRow['student'] | null
  >(null);
  const [selectedExistingNote, setSelectedExistingNote] = useState<CharacterNoteItem | null>(null);

  // Batch Form State for Tab 2
  const [batchScaleData, setBatchScaleData] = useState<
    Record<string, Record<string, string>>
  >({});
  const [batchNotes, setBatchNotes] = useState<Record<string, string>>({});
  const [isSavingBatch, setIsSavingBatch] = useState<boolean>(false);
  const [batchSuccessMessage, setBatchSuccessMessage] = useState<string | null>(null);
  const [batchErrorMessage, setBatchErrorMessage] = useState<string | null>(null);
  const [noteToDelete, setNoteToDelete] = useState<string | null>(null);
  const [isDeletingNote, setIsDeletingNote] = useState<boolean>(false);

  // Initial Master Data Loading
  useEffect(() => {
    async function initMasterData() {
      try {
        const [yearRes, semList, classRes] = await Promise.all([
          academicYearsApi.list({ limit: 100 }),
          academicApi.listSemesters(),
          classesApi.list({ limit: 100 }),
        ]);

        const yearList = yearRes.data || [];
        setAcademicYears(yearList);
        setSemesters(semList || []);
        const classList = classRes.data || [];
        setClasses(classList);

        const activeYear = yearList.find((y: AcademicYearItem) => y.is_active) || yearList[0];
        if (activeYear) {
          setSelectedYearId(activeYear.id);
        }

        const activeSem = semList.find((s: SemesterItem) => s.is_active) || semList[0];
        if (activeSem) {
          setSelectedSemesterId(activeSem.id);
        }

        if (classList.length > 0) {
          setSelectedClassId(classList[0].id);
        }
      } catch (err) {
        console.error('Gagal memuat master data awal:', err);
      }
    }

    initMasterData();
  }, []);

  // Fetch Periods when Semester Changes
  const fetchPeriods = useCallback(async (semesterId: string) => {
    if (!semesterId) return;
    try {
      const data = await academicApi.listGradePeriods({ semester_id: semesterId });
      setPeriods(data || []);
      if (data && data.length > 0) {
        const openPeriod = data.find((p) => p.status === 'open') || data[0];
        setSelectedPeriodId(openPeriod.id);
      }
    } catch (err) {
      console.error('Gagal memuat periode penilaian:', err);
    }
  }, []);

  useEffect(() => {
    if (selectedSemesterId) {
      fetchPeriods(selectedSemesterId);
    }
  }, [selectedSemesterId, fetchPeriods]);

  // Load Class Character Notes Matrix
  const fetchClassMatrix = useCallback(async () => {
    if (!selectedClassId || !selectedPeriodId) {
      setMatrixData(null);
      return;
    }

    setIsLoadingMatrix(true);
    setMatrixError(null);
    setBatchSuccessMessage(null);
    setBatchErrorMessage(null);

    try {
      const data = await academicApi.getClassCharacterNotes({
        class_id: selectedClassId,
        period_id: selectedPeriodId,
      });

      setMatrixData(data);

      // Populate batch grid states
      const initialScale: Record<string, Record<string, string>> = {};
      const initialNotes: Record<string, string> = {};

      data.students.forEach((row) => {
        initialScale[row.student.id] = {};
        CHARACTER_ASPECTS.forEach((asp) => {
          initialScale[row.student.id][asp.key] = row.scale_data[asp.key] || 'baik';
        });
        initialNotes[row.student.id] = row.homeroom_note || '';
      });

      setBatchScaleData(initialScale);
      setBatchNotes(initialNotes);
    } catch (err: unknown) {
      if (err instanceof ApiClientError) {
        setMatrixError(err.message);
      } else {
        setMatrixError('Terjadi kesalahan saat memuat catatan karakter kelas.');
      }
      setMatrixData(null);
    } finally {
      setIsLoadingMatrix(false);
    }
  }, [selectedClassId, selectedPeriodId]);

  useEffect(() => {
    fetchClassMatrix();
  }, [fetchClassMatrix]);

  const currentPeriod = useMemo(() => {
    return periods.find((p) => p.id === selectedPeriodId) || matrixData?.period || null;
  }, [periods, selectedPeriodId, matrixData]);

  const isPeriodLocked = currentPeriod?.status === 'locked' || matrixData?.is_locked || false;

  // Filtered Students for Roster View
  const filteredStudents = useMemo(() => {
    if (!matrixData) return [];
    let list = matrixData.students;

    if (statusFilter === 'complete') {
      list = list.filter((s) => s.is_complete);
    } else if (statusFilter === 'incomplete') {
      list = list.filter((s) => !s.is_complete);
    }

    if (searchQuery.trim()) {
      const q = searchQuery.toLowerCase();
      list = list.filter(
        (s) =>
          s.student.nisn?.toLowerCase().includes(q) ||
          s.student.nis?.toLowerCase().includes(q) ||
          (s.student.full_name || s.student.name || '').toLowerCase().includes(q)
      );
    }

    return list;
  }, [matrixData, statusFilter, searchQuery]);

  // Open Dialog for Individual Student
  const handleOpenStudentDialog = (row: MatrixCharacterStudentRow) => {
    setSelectedStudentForDialog(row.student);
    setSelectedExistingNote(row.note || null);
    setIsDialogOpen(true);
  };

  // Delete Character Note
  const handleDeleteNote = (noteId: string) => {
    if (isPeriodLocked) return;
    setNoteToDelete(noteId);
  };

  const handleConfirmDeleteNote = async () => {
    if (!noteToDelete) return;
    setIsDeletingNote(true);
    try {
      await academicApi.deleteCharacterNote(noteToDelete);
      toast.success('Catatan karakter siswa berhasil dihapus.');
      setNoteToDelete(null);
      await fetchClassMatrix();
    } catch (err: unknown) {
      if (err instanceof ApiClientError) {
        toast.error(err.message);
      } else {
        toast.error('Gagal menghapus catatan karakter.');
      }
    } finally {
      setIsDeletingNote(false);
    }
  };

  // Save All Batch Notes in Tab 2
  const handleSaveBatch = async () => {
    if (!matrixData || !selectedPeriodId || isPeriodLocked) return;

    setIsSavingBatch(true);
    setBatchSuccessMessage(null);
    setBatchErrorMessage(null);

    try {
      const notesPayload = matrixData.students.map((row) => ({
        student_id: row.student.id,
        scale_data: batchScaleData[row.student.id] || {},
        homeroom_note: batchNotes[row.student.id]?.trim() || null,
      }));

      const res = await academicApi.bulkStoreCharacterNotes({
        period_id: selectedPeriodId,
        notes: notesPayload,
      });

      setBatchSuccessMessage(
        `Berhasil menyimpan catatan karakter untuk ${res.count} siswa di kelas ${matrixData.class.name}.`
      );
      await fetchClassMatrix();
    } catch (err: unknown) {
      if (err instanceof ApiClientError) {
        setBatchErrorMessage(err.message);
      } else {
        setBatchErrorMessage('Terjadi kesalahan saat menyimpan catatan karakter massal.');
      }
    } finally {
      setIsSavingBatch(false);
    }
  };

  return (
    <div className="space-y-6">
      {/* Top Breadcrumb & Title */}
      <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between border-b border-gray-100 pb-5">
        <div>
          <div className="flex items-center gap-2 text-xs text-gray-500 mb-1">
            <Link
              href="/admin/academic"
              className="hover:text-emerald-600 transition flex items-center gap-1"
            >
              <ArrowLeft className="h-3.5 w-3.5" />
              Portal Akademik
            </Link>
            <span>/</span>
            <span className="text-gray-900 font-medium">Catatan Karakter Bulanan</span>
          </div>
          <h1 className="text-xl font-bold tracking-tight text-gray-900 flex items-center gap-2.5">
            <div className="rounded-xl bg-emerald-600 p-2 text-white shadow-sm">
              <Sparkles className="h-5 w-5" />
            </div>
            Catatan Karakter Bulanan Siswa (ADM-GRD-02)
          </h1>
          <p className="text-xs text-gray-600 mt-1">
            Penilaian pembiasaan profil pelajar Pancasila & catatan naratif wali kelas untuk E-Progress
            Report bulanan
          </p>
        </div>

        {/* Status Indicator */}
        <div className="flex items-center gap-3">
          {currentPeriod && (
            <div
              className={`flex items-center gap-2 rounded-lg px-3 py-1.5 text-xs font-medium border shadow-xs ${
                isPeriodLocked
                  ? 'bg-amber-50 text-amber-800 border-amber-200'
                  : 'bg-emerald-50 text-emerald-800 border-emerald-200'
              }`}
            >
              {isPeriodLocked ? (
                <>
                  <Lock className="h-3.5 w-3.5 text-amber-600" />
                  <span>Periode Terkunci</span>
                </>
              ) : (
                <>
                  <Unlock className="h-3.5 w-3.5 text-emerald-600" />
                  <span>Periode Terbuka</span>
                </>
              )}
            </div>
          )}

          <Button
            type="button"
            variant="outline"
            size="sm"
            onClick={fetchClassMatrix}
            disabled={isLoadingMatrix}
            className="text-xs gap-1.5"
          >
            <RefreshCw
              className={`h-3.5 w-3.5 text-gray-600 ${isLoadingMatrix ? 'animate-spin' : ''}`}
            />
            Muat Ulang
          </Button>
        </div>
      </div>

      {/* Filter Ribbon */}
      <div className="rounded-xl border border-gray-200 bg-white p-4 shadow-xs">
        <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
          <div>
            <label className="block text-[11px] font-semibold text-gray-600 uppercase tracking-wider mb-1">
              Tahun Ajaran
            </label>
            <select
              value={selectedYearId}
              onChange={(e) => setSelectedYearId(e.target.value)}
              className="w-full rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-xs text-gray-900 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
            >
              {academicYears.map((y) => (
                <option key={y.id} value={y.id}>
                  {y.name} {y.is_active ? '(Aktif)' : ''}
                </option>
              ))}
            </select>
          </div>

          <div>
            <label className="block text-[11px] font-semibold text-gray-600 uppercase tracking-wider mb-1">
              Semester
            </label>
            <select
              value={selectedSemesterId}
              onChange={(e) => setSelectedSemesterId(e.target.value)}
              className="w-full rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-xs text-gray-900 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
            >
              {semesters.map((s) => (
                <option key={s.id} value={s.id}>
                  {s.name} {s.is_active ? '(Aktif)' : ''}
                </option>
              ))}
            </select>
          </div>

          <div>
            <label className="block text-[11px] font-semibold text-gray-600 uppercase tracking-wider mb-1">
              Kelas / Rombel Binaaan
            </label>
            <select
              value={selectedClassId}
              onChange={(e) => setSelectedClassId(e.target.value)}
              className="w-full rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-xs text-gray-900 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
            >
              {classes.map((c) => (
                <option key={c.id} value={c.id}>
                  Kelas {c.name}
                </option>
              ))}
            </select>
          </div>

          <div>
            <label className="block text-[11px] font-semibold text-gray-600 uppercase tracking-wider mb-1">
              Periode Penilaian
            </label>
            <select
              value={selectedPeriodId}
              onChange={(e) => setSelectedPeriodId(e.target.value)}
              className="w-full rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-xs text-gray-900 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
            >
              {periods.map((p) => (
                <option key={p.id} value={p.id}>
                  {p.month_name || `Bulan ${p.month}`}{' '}
                  {p.status === 'locked' ? '🔒 (Terkunci)' : '🔓'}
                </option>
              ))}
            </select>
          </div>
        </div>
      </div>

      {/* KPI Cards */}
      {matrixData && (
        <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
          <div className="rounded-xl border border-gray-200 bg-white p-3.5 shadow-xs">
            <div className="flex items-center gap-2 text-xs text-gray-500 mb-1">
              <Users className="h-4 w-4 text-emerald-600" />
              <span>Total Siswa Rombel</span>
            </div>
            <p className="text-xl font-bold text-gray-900">{matrixData.summary.total_students}</p>
            <p className="text-[11px] text-gray-500">Kelas {matrixData.class.name}</p>
          </div>

          <div className="rounded-xl border border-gray-200 bg-white p-3.5 shadow-xs">
            <div className="flex items-center gap-2 text-xs text-emerald-700 mb-1">
              <CheckCircle2 className="h-4 w-4 text-emerald-600" />
              <span>Lengkap Dinilai</span>
            </div>
            <p className="text-xl font-bold text-emerald-700">
              {matrixData.summary.complete_count}
            </p>
            <p className="text-[11px] text-emerald-600">
              {matrixData.summary.completion_rate}% siap cetak rapor
            </p>
          </div>

          <div className="rounded-xl border border-gray-200 bg-white p-3.5 shadow-xs">
            <div className="flex items-center gap-2 text-xs text-amber-700 mb-1">
              <AlertTriangle className="h-4 w-4 text-amber-600" />
              <span>Belum Dinilai</span>
            </div>
            <p className="text-xl font-bold text-amber-700">
              {matrixData.summary.incomplete_count}
            </p>
            <p className="text-[11px] text-amber-600">Menunggu input wali kelas</p>
          </div>

          <div className="rounded-xl border border-gray-200 bg-white p-3.5 shadow-xs">
            <div className="flex items-center gap-2 text-xs text-purple-700 mb-1">
              <Sparkles className="h-4 w-4 text-purple-600" />
              <span>Dimensi Karakter</span>
            </div>
            <p className="text-xl font-bold text-purple-700">8 Dimensi</p>
            <p className="text-[11px] text-gray-500">Profil Pelajar Pancasila</p>
          </div>
        </div>
      )}

      {/* Tabs */}
      <div className="flex items-center justify-between border-b border-gray-200">
        <div className="flex gap-4">
          <button
            type="button"
            onClick={() => setActiveTab('roster')}
            className={`pb-3 text-xs font-semibold flex items-center gap-2 border-b-2 transition ${
              activeTab === 'roster'
                ? 'border-emerald-600 text-emerald-700'
                : 'border-transparent text-gray-500 hover:text-gray-700'
            }`}
          >
            <Users className="h-4 w-4" />
            Daftar Siswa & Narasi Rapor
            {matrixData && (
              <span className="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] text-slate-700">
                {matrixData.students.length} Siswa
              </span>
            )}
          </button>

          <button
            type="button"
            onClick={() => setActiveTab('batch')}
            className={`pb-3 text-xs font-semibold flex items-center gap-2 border-b-2 transition ${
              activeTab === 'batch'
                ? 'border-emerald-600 text-emerald-700'
                : 'border-transparent text-gray-500 hover:text-gray-700'
            }`}
          >
            <FileSpreadsheet className="h-4 w-4" />
            Penilaian Cepat Seluruh Rombel (Batch)
          </button>
        </div>
      </div>

      {/* Notifications */}
      {batchSuccessMessage && (
        <div className="flex items-center gap-2 rounded-lg bg-emerald-50 p-3 text-xs text-emerald-800 border border-emerald-200">
          <CheckCircle2 className="h-4 w-4 text-emerald-600 shrink-0" />
          <span>{batchSuccessMessage}</span>
        </div>
      )}

      {batchErrorMessage && (
        <div className="flex items-center gap-2 rounded-lg bg-red-50 p-3 text-xs text-red-800 border border-red-200">
          <AlertTriangle className="h-4 w-4 text-red-600 shrink-0" />
          <span>{batchErrorMessage}</span>
        </div>
      )}

      {/* TAB 1: ROSTER VIEW */}
      {activeTab === 'roster' && (
        <div className="space-y-4">
          {/* Search & Filter Bar */}
          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div className="relative w-full sm:w-72">
              <Search className="absolute left-3 top-2.5 h-3.5 w-3.5 text-gray-400" />
              <Input
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                placeholder="Cari siswa (Nama/NISN)..."
                className="pl-8 text-xs h-9"
              />
            </div>

            <div className="flex items-center gap-2">
              <div className="flex items-center rounded-lg border border-gray-200 p-0.5 bg-slate-50 text-xs">
                <button
                  type="button"
                  onClick={() => setStatusFilter('all')}
                  className={`px-2.5 py-1 rounded-md font-medium transition ${
                    statusFilter === 'all'
                      ? 'bg-white shadow-xs text-gray-900 font-semibold'
                      : 'text-gray-500 hover:text-gray-900'
                  }`}
                >
                  Semua
                </button>
                <button
                  type="button"
                  onClick={() => setStatusFilter('complete')}
                  className={`px-2.5 py-1 rounded-md font-medium transition ${
                    statusFilter === 'complete'
                      ? 'bg-white shadow-xs text-emerald-700 font-semibold'
                      : 'text-gray-500 hover:text-gray-900'
                  }`}
                >
                  Lengkap
                </button>
                <button
                  type="button"
                  onClick={() => setStatusFilter('incomplete')}
                  className={`px-2.5 py-1 rounded-md font-medium transition ${
                    statusFilter === 'incomplete'
                      ? 'bg-white shadow-xs text-amber-700 font-semibold'
                      : 'text-gray-500 hover:text-gray-900'
                  }`}
                >
                  Belum Diisi
                </button>
              </div>
            </div>
          </div>

          {/* Locked Notice */}
          {isPeriodLocked && (
            <div className="flex items-start gap-2.5 rounded-lg bg-amber-50 p-3.5 text-xs text-amber-900 border border-amber-200">
              <Lock className="h-4 w-4 text-amber-700 shrink-0 mt-0.5" />
              <div>
                <p className="font-semibold">Periode Penilaian Sedang Dikunci</p>
                <p className="text-amber-800 text-[11px] mt-0.5">
                  Catatan karakter pada bulan ini telah dibekukan secara resmi. Perubahan data
                  dinonaktifkan hingga administrator membuka kunci periode.
                </p>
              </div>
            </div>
          )}

          {/* Table */}
          {isLoadingMatrix ? (
            <div className="rounded-xl border border-gray-200 bg-white p-12 text-center shadow-xs">
              <Loader2 className="h-8 w-8 animate-spin text-emerald-600 mx-auto mb-3" />
              <p className="text-xs font-medium text-gray-600">
                Memuat catatan karakter rombel kelas...
              </p>
            </div>
          ) : matrixError ? (
            <div className="rounded-xl border border-red-200 bg-red-50/50 p-8 text-center">
              <AlertTriangle className="h-8 w-8 text-red-600 mx-auto mb-2" />
              <p className="text-xs font-semibold text-red-900 mb-1">Gagal Memuat Data</p>
              <p className="text-xs text-red-700 mb-4">{matrixError}</p>
              <Button type="button" size="sm" onClick={fetchClassMatrix} className="text-xs">
                Coba Lagi
              </Button>
            </div>
          ) : !matrixData || matrixData.students.length === 0 ? (
            <div className="rounded-xl border border-dashed border-gray-300 bg-slate-50/50 p-12 text-center">
              <Users className="h-10 w-10 text-gray-400 mx-auto mb-3" />
              <h3 className="text-sm font-semibold text-gray-900 mb-1">Tidak Ada Data Siswa</h3>
              <p className="text-xs text-gray-500 max-w-sm mx-auto">
                Belum ada siswa aktif terdaftar pada rombel kelas ini.
              </p>
            </div>
          ) : (
            <div className="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xs">
              <table className="w-full text-left text-xs border-collapse">
                <thead className="bg-slate-50 border-b border-gray-200 text-gray-600 font-semibold">
                  <tr>
                    <th className="py-3 px-3 w-12 text-center">No</th>
                    <th className="py-3 px-3 w-32">NISN / NIS</th>
                    <th className="py-3 px-3 min-w-[180px]">Nama Siswa</th>
                    <th className="py-3 px-3">Capaian Dimensi Karakter (8 Aspek)</th>
                    <th className="py-3 px-3 min-w-[220px]">Catatan Wali Kelas</th>
                    <th className="py-3 px-3 w-28 text-center">Status</th>
                    <th className="py-3 px-3 w-28 text-right">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100">
                  {filteredStudents.map((row, idx) => {
                    const studentName = row.student.full_name || row.student.name || '-';
                    const hasNote = Boolean(row.note);

                    return (
                      <tr key={row.student.id} className="hover:bg-slate-50/70 transition">
                        <td className="py-3 px-3 text-center text-gray-500">{idx + 1}</td>
                        <td className="py-3 px-3 font-mono text-[11px] text-gray-700">
                          <div>{row.student.nisn || '-'}</div>
                          <div className="text-[10px] text-gray-400">{row.student.nis}</div>
                        </td>
                        <td className="py-3 px-3 font-medium text-gray-900">
                          <div className="flex items-center gap-1.5">
                            <span>{studentName}</span>
                            <span className="text-[10px] text-gray-400 font-mono">
                              ({row.student.gender})
                            </span>
                          </div>
                        </td>

                        {/* Character Aspect Badges */}
                        <td className="py-3 px-3">
                          {hasNote && Object.keys(row.scale_data).length > 0 ? (
                            <div className="flex flex-wrap gap-1 max-w-md">
                              {CHARACTER_ASPECTS.map((asp) => {
                                const rawRating = row.scale_data[asp.key];
                                const numMap: Record<string | number, CharacterRating> = {
                                  4: 'sangat_baik',
                                  3: 'baik',
                                  2: 'cukup',
                                  1: 'perlu_bimbingan',
                                };
                                const rating: CharacterRating =
                                  numMap[rawRating] ||
                                  (typeof rawRating === 'string' && rawRating in CHARACTER_RATING_COLORS
                                    ? (rawRating as CharacterRating)
                                    : 'baik');
                                const color = CHARACTER_RATING_COLORS[rating] || CHARACTER_RATING_COLORS.baik;
                                const label = CHARACTER_RATING_LABELS[rating] || 'Baik';

                                return (
                                  <span
                                    key={asp.key}
                                    title={`${asp.name}: ${label}`}
                                    className={`inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[9px] font-semibold border ${color.badge}`}
                                  >
                                    <span className={`h-1.5 w-1.5 rounded-full ${color.dot}`} />
                                    <span className="truncate max-w-[80px]">{asp.name.split(' ')[0]}</span>
                                  </span>
                                );
                              })}
                            </div>
                          ) : (
                            <span className="text-gray-400 italic text-[11px]">
                              Belum dinilai
                            </span>
                          )}
                        </td>

                        {/* Homeroom Note Excerpt */}
                        <td className="py-3 px-3 text-gray-600">
                          {row.homeroom_note ? (
                            <p className="line-clamp-2 text-[11px] leading-relaxed text-gray-700 italic">
                              &ldquo;{row.homeroom_note}&rdquo;
                            </p>
                          ) : (
                            <span className="text-gray-400 italic text-[11px]">
                              Belum ada catatan
                            </span>
                          )}
                        </td>

                        {/* Status */}
                        <td className="py-3 px-3 text-center">
                          {row.completeness_status === 'lengkap' ? (
                            <span className="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-[10px] font-semibold text-emerald-700 border border-emerald-200">
                              <CheckCircle2 className="h-3 w-3" />
                              Lengkap (8 Dimensi)
                            </span>
                          ) : row.completeness_status === 'belum_lengkap' ? (
                            <span className="inline-flex items-center gap-1 rounded-full bg-sky-50 px-2.5 py-0.5 text-[10px] font-semibold text-sky-700 border border-sky-200">
                              <AlertCircle className="h-3 w-3" />
                              {row.dimensions_count ? `${row.dimensions_count}/8 Dimensi` : 'Belum Lengkap'}
                            </span>
                          ) : row.is_complete ? (
                            <span className="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-[10px] font-semibold text-emerald-700 border border-emerald-200">
                              <CheckCircle2 className="h-3 w-3" />
                              Lengkap
                            </span>
                          ) : (
                            <span className="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-700 border border-amber-200">
                              <AlertTriangle className="h-3 w-3" />
                              Belum Diisi
                            </span>
                          )}
                        </td>

                        {/* Actions */}
                        <td className="py-3 px-3 text-right space-x-1">
                          <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => handleOpenStudentDialog(row)}
                            className="text-[11px] h-7 px-2 gap-1 text-emerald-700 hover:text-emerald-800"
                          >
                            <Edit2 className="h-3 w-3" />
                            {hasNote ? 'Edit' : 'Input'}
                          </Button>

                          {hasNote && !isPeriodLocked && (
                            <button
                              type="button"
                              onClick={() => handleDeleteNote(row.note!.id)}
                              className="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-red-600 transition align-middle"
                              title="Hapus Catatan"
                            >
                              <Trash2 className="h-3.5 w-3.5" />
                            </button>
                          )}
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          )}
        </div>
      )}

      {/* TAB 2: BATCH MATRIX VIEW */}
      {activeTab === 'batch' && (
        <div className="space-y-4">
          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
              <h3 className="text-sm font-semibold text-gray-900">
                Penilaian Karakter Massal Satu Rombel
              </h3>
              <p className="text-xs text-gray-500">
                Ubah skala dimensi karakter untuk seluruh siswa dan simpan sekaligus
              </p>
            </div>

            <Button
              type="button"
              size="sm"
              onClick={handleSaveBatch}
              disabled={isSavingBatch || isPeriodLocked}
              className="bg-emerald-600 hover:bg-emerald-700 text-white text-xs gap-1.5 h-9"
            >
              {isSavingBatch ? (
                <>
                  <Loader2 className="h-3.5 w-3.5 animate-spin" />
                  Menyimpan...
                </>
              ) : (
                <>
                  <Save className="h-3.5 w-3.5" />
                  Simpan Semua Catatan Rombel
                </>
              )}
            </Button>
          </div>

          {isPeriodLocked && (
            <div className="flex items-start gap-2.5 rounded-lg bg-amber-50 p-3.5 text-xs text-amber-900 border border-amber-200">
              <Lock className="h-4 w-4 text-amber-700 shrink-0 mt-0.5" />
              <div>
                <p className="font-semibold">Periode Penilaian Sedang Dikunci</p>
                <p className="text-amber-800 text-[11px] mt-0.5">
                  Perubahan catatan massal dinonaktifkan karena periode penilaian telah dikunci.
                </p>
              </div>
            </div>
          )}

          {!matrixData || matrixData.students.length === 0 ? (
            <div className="rounded-xl border border-dashed border-gray-300 bg-slate-50/50 p-12 text-center">
              <Users className="h-10 w-10 text-gray-400 mx-auto mb-3" />
              <p className="text-xs text-gray-500">Tidak ada data siswa untuk penilaian massal.</p>
            </div>
          ) : (
            <div className="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-xs">
              <table className="w-full text-left text-xs border-collapse">
                <thead className="bg-slate-50 border-b border-gray-200 text-gray-600 font-semibold sticky top-0 z-10">
                  <tr>
                    <th className="py-3 px-3 w-12 text-center border-r border-gray-200">No</th>
                    <th className="py-3 px-3 w-40 border-r border-gray-200">Nama Siswa</th>
                    {CHARACTER_ASPECTS.map((asp) => (
                      <th
                        key={asp.key}
                        className="py-3 px-2 text-center border-r border-gray-200 min-w-[95px]"
                      >
                        <div className="font-bold text-gray-900 truncate max-w-[90px] mx-auto">
                          {asp.name.split(' ')[0]}
                        </div>
                        <div className="text-[10px] text-gray-500 font-normal truncate max-w-[90px] mx-auto">
                          {asp.category}
                        </div>
                      </th>
                    ))}
                    <th className="py-3 px-3 min-w-[200px]">Catatan Naratif Wali Kelas</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-200">
                  {matrixData.students.map((row, idx) => {
                    const studentId = row.student.id;
                    const studentName = row.student.full_name || row.student.name || '-';

                    return (
                      <tr key={studentId} className="hover:bg-slate-50/70 transition">
                        <td className="py-2 px-3 text-center text-gray-500 border-r border-gray-100">
                          {idx + 1}
                        </td>
                        <td className="py-2 px-3 font-medium text-gray-900 border-r border-gray-100">
                          <div className="truncate max-w-[150px] font-semibold">{studentName}</div>
                          <div className="text-[10px] text-gray-400 font-mono">
                            {row.student.nisn || row.student.nis}
                          </div>
                        </td>

                        {/* Select Rating for each aspect */}
                        {CHARACTER_ASPECTS.map((asp) => {
                          const val = batchScaleData[studentId]?.[asp.key] || 'baik';

                          return (
                            <td
                              key={asp.key}
                              className="py-1.5 px-1.5 text-center border-r border-gray-100"
                            >
                              <select
                                disabled={isPeriodLocked}
                                value={val}
                                onChange={(e) => {
                                  setBatchScaleData((prev) => ({
                                    ...prev,
                                    [studentId]: {
                                      ...(prev[studentId] || {}),
                                      [asp.key]: e.target.value,
                                    },
                                  }));
                                }}
                                className="w-full rounded border border-gray-300 bg-white py-1 px-1 text-[11px] font-semibold text-center focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 disabled:bg-gray-100"
                              >
                                <option value="sangat_baik">SB</option>
                                <option value="baik">B</option>
                                <option value="cukup">C</option>
                                <option value="perlu_bimbingan">PB</option>
                              </select>
                            </td>
                          );
                        })}

                        {/* Quick Homeroom Note */}
                        <td className="py-1.5 px-3">
                          <input
                            type="text"
                            disabled={isPeriodLocked}
                            value={batchNotes[studentId] || ''}
                            onChange={(e) => {
                              setBatchNotes((prev) => ({
                                ...prev,
                                [studentId]: e.target.value,
                              }));
                            }}
                            placeholder="Catatan apresiasi / perkembangan..."
                            className="w-full rounded border border-gray-300 bg-white py-1 px-2 text-xs focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 disabled:bg-gray-100"
                          />
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          )}
        </div>
      )}

      {/* INDIVIDUAL STUDENT CHARACTER NOTE DIALOG */}
      <CharacterNoteDialog
        isOpen={isDialogOpen}
        onClose={() => setIsDialogOpen(false)}
        onSuccess={() => {
          fetchClassMatrix();
        }}
        student={selectedStudentForDialog}
        period={currentPeriod}
        existingNote={selectedExistingNote}
        isLocked={isPeriodLocked}
      />

      {/* Dialog Konfirmasi Hapus Catatan Karakter */}
      <ConfirmDialog
        isOpen={!!noteToDelete}
        onClose={() => setNoteToDelete(null)}
        onConfirm={handleConfirmDeleteNote}
        title="Hapus Catatan Karakter"
        description="Apakah Anda yakin ingin menghapus catatan karakter siswa ini? Data yang dihapus tidak dapat dipulihkan."
        confirmLabel="Ya, Hapus"
        cancelLabel="Batal"
        variant="danger"
        isLoading={isDeletingNote}
      />
    </div>
  );
}
