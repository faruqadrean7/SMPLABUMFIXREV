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
  Loader2,
  ArrowLeft,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
  ClassGradeMatrix,
  GradeComponentItem,
  GradePeriodItem,
  MatrixStudentRow,
} from '@/types/academic';
import {
  AcademicYearItem,
  SemesterItem,
  SchoolClassItem,
  SubjectItem,
} from '@/types/master';
import { academicApi } from '@/features/academic/academic-api';
import { classesApi } from '@/features/master/classes-api';
import { subjectsApi } from '@/features/master/subjects-api';
import { academicYearsApi } from '@/features/master/academic-years-api';
import { GradeComponentDialog, GradePeriodLockDialog } from '@/features/academic/grades';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { toast } from '@/components/ui/toast';
import { ApiClientError } from '@/lib/api/errors';

export default function GradebookAdminPage() {
  // Navigation / Tabs
  const [activeTab, setActiveTab] = useState<'matrix' | 'components'>('matrix');

  // Master Data Dropdowns
  const [academicYears, setAcademicYears] = useState<AcademicYearItem[]>([]);
  const [semesters, setSemesters] = useState<SemesterItem[]>([]);
  const [classes, setClasses] = useState<SchoolClassItem[]>([]);
  const [subjects, setSubjects] = useState<SubjectItem[]>([]);
  const [periods, setPeriods] = useState<GradePeriodItem[]>([]);

  // Selected Filter States
  const [selectedYearId, setSelectedYearId] = useState<string>('');
  const [selectedSemesterId, setSelectedSemesterId] = useState<string>('');
  const [selectedClassId, setSelectedClassId] = useState<string>('');
  const [selectedSubjectId, setSelectedSubjectId] = useState<string>('');
  const [selectedPeriodId, setSelectedPeriodId] = useState<string>('');

  // Matrix State
  const [matrixData, setMatrixData] = useState<ClassGradeMatrix | null>(null);
  const [isLoadingMatrix, setIsLoadingMatrix] = useState<boolean>(false);
  const [matrixError, setMatrixError] = useState<string | null>(null);

  // Components State
  const [componentsList, setComponentsList] = useState<GradeComponentItem[]>([]);
  const [isLoadingComponents, setIsLoadingComponents] = useState<boolean>(false);

  // Local Editable Grade Values in Matrix: { [studentId]: { [componentId]: number | '' } }
  const [gridValues, setGridValues] = useState<Record<string, Record<string, string | number>>>({});
  const [dirtyCells, setDirtyCells] = useState<Set<string>>(new Set());
  const [isSavingBatch, setIsSavingBatch] = useState<boolean>(false);
  const [saveSuccessMessage, setSaveSuccessMessage] = useState<string | null>(null);
  const [saveErrorMessage, setSaveErrorMessage] = useState<string | null>(null);

  // Search filter inside matrix
  const [searchQuery, setSearchQuery] = useState<string>('');

  // Modals
  const [isComponentDialogOpen, setIsComponentDialogOpen] = useState<boolean>(false);
  const [editingComponent, setEditingComponent] = useState<GradeComponentItem | null>(null);
  const [isLockDialogOpen, setIsLockDialogOpen] = useState<boolean>(false);
  const [activeLockPeriod, setActiveLockPeriod] = useState<GradePeriodItem | null>(null);
  const [isPasteHelpOpen, setIsPasteHelpOpen] = useState<boolean>(false);
  const [componentToDelete, setComponentToDelete] = useState<string | null>(null);
  const [isDeletingComponent, setIsDeletingComponent] = useState<boolean>(false);

  // Initial Master Data Loading
  useEffect(() => {
    async function initMasterData() {
      try {
        const [yearRes, semList, classRes, subRes] = await Promise.all([
          academicYearsApi.list({ limit: 100 }),
          academicApi.listSemesters(),
          classesApi.list({ limit: 100 }),
          subjectsApi.list({ limit: 100 }),
        ]);

        const yearList = yearRes.data || [];
        setAcademicYears(yearList);
        setSemesters(semList || []);
        const classList = classRes.data || [];
        setClasses(classList);
        const subList = subRes.data || [];
        setSubjects(subList);

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
        if (subList.length > 0) {
          setSelectedSubjectId(subList[0].id);
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
        // Default to first open period or first period
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

  // Load Components for Selected Subject & Semester
  const fetchComponents = useCallback(async () => {
    if (!selectedSubjectId || !selectedSemesterId) return;
    setIsLoadingComponents(true);
    try {
      const comps = await academicApi.listGradeComponents({
        subject_id: selectedSubjectId,
        semester_id: selectedSemesterId,
        class_id: selectedClassId || undefined,
      });
      setComponentsList(comps || []);
    } catch (err) {
      console.error('Gagal memuat komponen penilaian:', err);
    } finally {
      setIsLoadingComponents(false);
    }
  }, [selectedSubjectId, selectedSemesterId, selectedClassId]);

  useEffect(() => {
    fetchComponents();
  }, [fetchComponents]);

  // Load Spreadsheet Matrix
  const fetchMatrix = useCallback(async () => {
    if (!selectedClassId || !selectedSubjectId || !selectedPeriodId) {
      setMatrixData(null);
      return;
    }

    setIsLoadingMatrix(true);
    setMatrixError(null);
    setSaveSuccessMessage(null);
    setSaveErrorMessage(null);

    try {
      const matrix = await academicApi.getClassGradeMatrix({
        class_id: selectedClassId,
        subject_id: selectedSubjectId,
        period_id: selectedPeriodId,
      });

      setMatrixData(matrix);

      // Populate local grid state
      const initialGrid: Record<string, Record<string, string | number>> = {};
      matrix.students.forEach((row) => {
        initialGrid[row.student.id] = {};
        matrix.components.forEach((comp) => {
          const g = row.grades[comp.id];
          initialGrid[row.student.id][comp.id] = g !== null && g !== undefined ? g.value : '';
        });
      });

      setGridValues(initialGrid);
      setDirtyCells(new Set());
    } catch (err: unknown) {
      if (err instanceof ApiClientError) {
        setMatrixError(err.message);
      } else {
        setMatrixError('Terjadi kesalahan saat memuat grid spreadsheet buku nilai.');
      }
      setMatrixData(null);
    } finally {
      setIsLoadingMatrix(false);
    }
  }, [selectedClassId, selectedSubjectId, selectedPeriodId]);

  useEffect(() => {
    fetchMatrix();
  }, [fetchMatrix]);

  // Current active period object
  const currentPeriod = useMemo(() => {
    return periods.find((p) => p.id === selectedPeriodId) || matrixData?.period || null;
  }, [periods, selectedPeriodId, matrixData]);

  const isPeriodLocked = currentPeriod?.status === 'locked';

  // Handle cell edit
  const handleCellChange = (studentId: string, componentId: string, valStr: string) => {
    if (isPeriodLocked) return;

    setGridValues((prev) => ({
      ...prev,
      [studentId]: {
        ...(prev[studentId] || {}),
        [componentId]: valStr,
      },
    }));

    setDirtyCells((prev) => {
      const next = new Set(prev);
      next.add(`${studentId}:${componentId}`);
      return next;
    });

    setSaveSuccessMessage(null);
    setSaveErrorMessage(null);
  };

  // Handle Excel Column Paste
  const handleCellPaste = (
    e: React.ClipboardEvent<HTMLInputElement>,
    startStudentIndex: number,
    componentId: string,
    filteredStudents: MatrixStudentRow[]
  ) => {
    if (isPeriodLocked) return;
    const text = e.clipboardData.getData('text');
    if (!text || !text.includes('\n')) return; // regular paste if single line

    e.preventDefault();
    const rows = text
      .split(/\r?\n/)
      .map((r) => r.trim())
      .filter((r) => r.length > 0);

    setGridValues((prev) => {
      const nextGrid = { ...prev };
      const nextDirty = new Set(dirtyCells);

      rows.forEach((valStr, offset) => {
        const targetStudent = filteredStudents[startStudentIndex + offset];
        if (!targetStudent) return;

        const numVal = parseFloat(valStr.replace(',', '.'));
        if (!isNaN(numVal) && numVal >= 0 && numVal <= 100) {
          if (!nextGrid[targetStudent.student.id]) {
            nextGrid[targetStudent.student.id] = {};
          }
          nextGrid[targetStudent.student.id][componentId] = numVal;
          nextDirty.add(`${targetStudent.student.id}:${componentId}`);
        }
      });

      setDirtyCells(nextDirty);
      return nextGrid;
    });
  };

  // Save All Dirty Cells to Backend Bulk Endpoint
  const handleSaveBatch = async () => {
    if (dirtyCells.size === 0 || !selectedPeriodId) return;

    setIsSavingBatch(true);
    setSaveErrorMessage(null);
    setSaveSuccessMessage(null);

    try {
      const gradesPayload: Array<{
        student_id: string;
        component_id: string;
        value: number;
      }> = [];

      dirtyCells.forEach((key) => {
        const [studentId, compId] = key.split(':');
        const rawVal = gridValues[studentId]?.[compId];
        if (rawVal !== '' && rawVal !== undefined && rawVal !== null) {
          const numVal = typeof rawVal === 'number' ? rawVal : parseFloat(rawVal);
          if (!isNaN(numVal)) {
            gradesPayload.push({
              student_id: studentId,
              component_id: compId,
              value: Math.min(100, Math.max(0, numVal)),
            });
          }
        }
      });

      if (gradesPayload.length === 0) {
        setDirtyCells(new Set());
        setIsSavingBatch(false);
        return;
      }

      const res = await academicApi.bulkStoreGrades({
        period_id: selectedPeriodId,
        grades: gradesPayload,
      });

      setSaveSuccessMessage(`Berhasil menyimpan ${res.count} nilai siswa ke buku nilai.`);
      setDirtyCells(new Set());
      await fetchMatrix();
    } catch (err: unknown) {
      if (err instanceof ApiClientError) {
        setSaveErrorMessage(err.message);
      } else {
        setSaveErrorMessage('Terjadi kesalahan saat menyimpan nilai siswa.');
      }
    } finally {
      setIsSavingBatch(false);
    }
  };

  // Component deletion handler
  const handleDeleteComponent = (id: string) => {
    setComponentToDelete(id);
  };

  const handleConfirmDeleteComponent = async () => {
    if (!componentToDelete) return;
    setIsDeletingComponent(true);
    try {
      await academicApi.deleteGradeComponent(componentToDelete);
      toast.success('Komponen penilaian berhasil dihapus.');
      setComponentToDelete(null);
      await fetchComponents();
      await fetchMatrix();
    } catch (err: unknown) {
      if (err instanceof ApiClientError) {
        toast.error(err.message);
      } else {
        toast.error('Gagal menghapus komponen penilaian.');
      }
    } finally {
      setIsDeletingComponent(false);
    }
  };

  // Filtered Students in Grid by Search Query
  const filteredStudents = useMemo(() => {
    if (!matrixData) return [];
    if (!searchQuery.trim()) return matrixData.students;
    const q = searchQuery.toLowerCase();
    return matrixData.students.filter(
      (s) =>
        s.student.nisn?.toLowerCase().includes(q) ||
        s.student.nis?.toLowerCase().includes(q) ||
        (s.student.full_name || s.student.name || '').toLowerCase().includes(q)
    );
  }, [matrixData, searchQuery]);

  // Compute Total Component Weight
  const totalWeight = useMemo(() => {
    return componentsList.reduce((acc, c) => acc + (Number(c.weight) || 0), 0);
  }, [componentsList]);

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
            <span className="text-gray-900 font-medium">Buku Nilai & Komponen Penilaian</span>
          </div>
          <h1 className="text-xl font-bold tracking-tight text-gray-900 flex items-center gap-2.5">
            <div className="rounded-lg bg-emerald-600 p-2 text-white shadow-sm">
              <Award className="h-5 w-5" />
            </div>
            Buku Nilai & Komponen Penilaian Bulanan
          </h1>
          <p className="text-xs text-gray-600 mt-1">
            Pengelolaan bobot komponen penilaian formatif-sumatif dan entri spreadsheet matriks
            nilai bulanan siswa
          </p>
        </div>

        {/* Global Period Status Indicator & Action */}
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

          {currentPeriod && (
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={() => {
                setActiveLockPeriod(currentPeriod);
                setIsLockDialogOpen(true);
              }}
              className="text-xs gap-1.5"
            >
              {isPeriodLocked ? (
                <>
                  <Unlock className="h-3.5 w-3.5 text-emerald-600" />
                  Buka Kunci
                </>
              ) : (
                <>
                  <Lock className="h-3.5 w-3.5 text-amber-600" />
                  Kunci Periode
                </>
              )}
            </Button>
          )}

          <Button
            type="button"
            size="sm"
            onClick={() => {
              setEditingComponent(null);
              setIsComponentDialogOpen(true);
            }}
            className="bg-emerald-600 hover:bg-emerald-700 text-white text-xs gap-1.5 shadow-xs"
          >
            <Plus className="h-3.5 w-3.5" />
            Komponen Baru
          </Button>
        </div>
      </div>

      {/* Filter Ribbon */}
      <div className="rounded-xl border border-gray-200 bg-white p-4 shadow-xs">
        <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-3">
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
              Kelas / Rombel
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
              Mata Pelajaran
            </label>
            <select
              value={selectedSubjectId}
              onChange={(e) => setSelectedSubjectId(e.target.value)}
              className="w-full rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-xs text-gray-900 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
            >
              {subjects.map((sub) => (
                <option key={sub.id} value={sub.id}>
                  {sub.name} ({sub.code})
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

      {/* KPI Summary Cards */}
      {matrixData && (
        <div className="grid grid-cols-2 md:grid-cols-5 gap-3">
          <div className="rounded-xl border border-gray-200 bg-white p-3.5 shadow-xs">
            <div className="flex items-center gap-2 text-xs text-gray-500 mb-1">
              <Users className="h-4 w-4 text-emerald-600" />
              <span>Total Siswa</span>
            </div>
            <p className="text-xl font-bold text-gray-900">{matrixData.summary.total_students}</p>
            <p className="text-[11px] text-gray-500">Terdaftar di kelas</p>
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
              {matrixData.summary.total_students > 0
                ? `${Math.round(
                    (matrixData.summary.complete_count / matrixData.summary.total_students) * 100
                  )}% kelengkapan`
                : '0%'}
            </p>
          </div>

          <div className="rounded-xl border border-gray-200 bg-white p-3.5 shadow-xs">
            <div className="flex items-center gap-2 text-xs text-amber-700 mb-1">
              <AlertTriangle className="h-4 w-4 text-amber-600" />
              <span>Belum Lengkap</span>
            </div>
            <p className="text-xl font-bold text-amber-700">
              {matrixData.summary.incomplete_count}
            </p>
            <p className="text-[11px] text-amber-600">Ada komponen kosong</p>
          </div>

          <div className="rounded-xl border border-gray-200 bg-white p-3.5 shadow-xs">
            <div className="flex items-center gap-2 text-xs text-blue-700 mb-1">
              <Award className="h-4 w-4 text-blue-600" />
              <span>Rata-rata Kelas</span>
            </div>
            <p className="text-xl font-bold text-blue-700">{matrixData.summary.average_score}</p>
            <p className="text-[11px] text-gray-500">Rerata berbobot</p>
          </div>

          <div className="rounded-xl border border-gray-200 bg-white p-3.5 shadow-xs">
            <div className="flex items-center gap-2 text-xs text-purple-700 mb-1">
              <Clock className="h-4 w-4 text-purple-600" />
              <span>Rentang Nilai</span>
            </div>
            <p className="text-base font-bold text-purple-700">
              {matrixData.summary.lowest_score} – {matrixData.summary.highest_score}
            </p>
            <p className="text-[11px] text-gray-500">Min – Max kelas</p>
          </div>
        </div>
      )}

      {/* Tabs Switcher */}
      <div className="flex items-center justify-between border-b border-gray-200">
        <div className="flex gap-4">
          <button
            type="button"
            onClick={() => setActiveTab('matrix')}
            className={`pb-3 text-xs font-semibold flex items-center gap-2 border-b-2 transition ${
              activeTab === 'matrix'
                ? 'border-emerald-600 text-emerald-700'
                : 'border-transparent text-gray-500 hover:text-gray-700'
            }`}
          >
            <FileSpreadsheet className="h-4 w-4" />
            Grid Spreadsheet Nilai Siswa
            {matrixData?.components && (
              <span className="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] text-slate-700">
                {matrixData.components.length} Komponen
              </span>
            )}
          </button>

          <button
            type="button"
            onClick={() => setActiveTab('components')}
            className={`pb-3 text-xs font-semibold flex items-center gap-2 border-b-2 transition ${
              activeTab === 'components'
                ? 'border-emerald-600 text-emerald-700'
                : 'border-transparent text-gray-500 hover:text-gray-700'
            }`}
          >
            <Award className="h-4 w-4" />
            Komponen Penilaian & Bobot
            <span
              className={`rounded-full px-2 py-0.5 text-[10px] ${
                totalWeight === 100
                  ? 'bg-emerald-100 text-emerald-800'
                  : 'bg-amber-100 text-amber-800'
              }`}
            >
              Total {totalWeight}%
            </span>
          </button>
        </div>

        {activeTab === 'matrix' && (
          <div className="flex items-center gap-2 pb-2">
            <button
              type="button"
              onClick={() => setIsPasteHelpOpen(true)}
              className="text-xs text-gray-500 hover:text-emerald-700 flex items-center gap-1 font-medium transition"
            >
              <HelpCircle className="h-3.5 w-3.5" />
              Bantuan Paste Excel
            </button>
          </div>
        )}
      </div>

      {/* Notification Alerts */}
      {saveSuccessMessage && (
        <div className="flex items-center gap-2 rounded-lg bg-emerald-50 p-3 text-xs text-emerald-800 border border-emerald-200">
          <CheckCircle2 className="h-4 w-4 text-emerald-600 shrink-0" />
          <span>{saveSuccessMessage}</span>
        </div>
      )}

      {saveErrorMessage && (
        <div className="flex items-center gap-2 rounded-lg bg-red-50 p-3 text-xs text-red-800 border border-red-200">
          <AlertTriangle className="h-4 w-4 text-red-600 shrink-0" />
          <span>{saveErrorMessage}</span>
        </div>
      )}

      {/* TAB 1: SPREADSHEET MATRIX */}
      {activeTab === 'matrix' && (
        <div className="space-y-4">
          {/* Action Ribbon & Search */}
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
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={fetchMatrix}
                disabled={isLoadingMatrix}
                className="text-xs gap-1.5 h-9"
              >
                <RefreshCw
                  className={`h-3.5 w-3.5 text-gray-600 ${isLoadingMatrix ? 'animate-spin' : ''}`}
                />
                Muat Ulang
              </Button>

              <Button
                type="button"
                size="sm"
                onClick={handleSaveBatch}
                disabled={isSavingBatch || dirtyCells.size === 0 || isPeriodLocked}
                className="bg-emerald-600 hover:bg-emerald-700 text-white text-xs gap-1.5 h-9 shadow-xs disabled:opacity-50"
              >
                {isSavingBatch ? (
                  <>
                    <Loader2 className="h-3.5 w-3.5 animate-spin" />
                    Menyimpan...
                  </>
                ) : (
                  <>
                    <Save className="h-3.5 w-3.5" />
                    Simpan Semua Nilai ({dirtyCells.size})
                  </>
                )}
              </Button>
            </div>
          </div>

          {/* Locked Notice Banner */}
          {isPeriodLocked && (
            <div className="flex items-start gap-2.5 rounded-lg bg-amber-50 p-3.5 text-xs text-amber-900 border border-amber-200">
              <Lock className="h-4 w-4 text-amber-700 shrink-0 mt-0.5" />
              <div>
                <p className="font-semibold">Periode Penilaian Sedang Dikunci</p>
                <p className="text-amber-800 text-[11px] mt-0.5">
                  Nilai pada bulan ini telah dibekukan secara resmi. Perubahan sel spreadsheet
                  dinonaktifkan hingga administrator membuka kembali kunci periode.
                </p>
              </div>
            </div>
          )}

          {/* Matrix Table */}
          {isLoadingMatrix ? (
            <div className="rounded-xl border border-gray-200 bg-white p-12 text-center shadow-xs">
              <Loader2 className="h-8 w-8 animate-spin text-emerald-600 mx-auto mb-3" />
              <p className="text-xs font-medium text-gray-600">
                Memuat matriks spreadsheet nilai siswa...
              </p>
            </div>
          ) : matrixError ? (
            <div className="rounded-xl border border-red-200 bg-red-50/50 p-8 text-center">
              <AlertTriangle className="h-8 w-8 text-red-600 mx-auto mb-2" />
              <p className="text-xs font-semibold text-red-900 mb-1">Gagal Memuat Buku Nilai</p>
              <p className="text-xs text-red-700 mb-4">{matrixError}</p>
              <Button type="button" size="sm" onClick={fetchMatrix} className="text-xs">
                Coba Lagi
              </Button>
            </div>
          ) : !matrixData || matrixData.students.length === 0 ? (
            <div className="rounded-xl border border-dashed border-gray-300 bg-slate-50/50 p-12 text-center">
              <FileSpreadsheet className="h-10 w-10 text-gray-400 mx-auto mb-3" />
              <h3 className="text-sm font-semibold text-gray-900 mb-1">Tidak Ada Data Siswa</h3>
              <p className="text-xs text-gray-500 max-w-sm mx-auto">
                Belum ada siswa aktif yang terdaftar pada kelas ini atau parameter filter belum
                sesuai.
              </p>
            </div>
          ) : matrixData.components.length === 0 ? (
            <div className="rounded-xl border border-dashed border-gray-300 bg-amber-50/30 p-12 text-center">
              <Award className="h-10 w-10 text-amber-500 mx-auto mb-3" />
              <h3 className="text-sm font-semibold text-gray-900 mb-1">
                Komponen Penilaian Belum Ditentukan
              </h3>
              <p className="text-xs text-gray-600 max-w-md mx-auto mb-4">
                Belum ada komponen penilaian (UH, Tugas, PTS, dll) untuk mata pelajaran ini pada
                semester yang dipilih. Buat komponen terlebih dahulu agar grid spreadsheet dapat
                diisi.
              </p>
              <Button
                type="button"
                onClick={() => {
                  setEditingComponent(null);
                  setIsComponentDialogOpen(true);
                }}
                className="bg-emerald-600 hover:bg-emerald-700 text-white text-xs gap-1.5"
              >
                <Plus className="h-3.5 w-3.5" />
                Tambah Komponen Penilaian
              </Button>
            </div>
          ) : (
            <div className="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-xs">
              <table className="w-full text-left text-xs border-collapse">
                <thead className="bg-slate-50 border-b border-gray-200 text-gray-600 font-semibold sticky top-0 z-10">
                  <tr>
                    <th className="py-3 px-3 w-12 text-center border-r border-gray-200">No</th>
                    <th className="py-3 px-3 w-32 border-r border-gray-200">NISN / NIS</th>
                    <th className="py-3 px-3 min-w-[200px] border-r border-gray-200">Nama Siswa</th>
                    {matrixData.components.map((comp) => (
                      <th
                        key={comp.id}
                        className="py-3 px-3 text-center min-w-[110px] border-r border-gray-200 bg-slate-50/80"
                      >
                        <div className="font-bold text-gray-900 uppercase font-mono">{comp.code}</div>
                        <div className="text-[10px] font-normal text-gray-500 truncate max-w-[120px] mx-auto">
                          {comp.name}
                        </div>
                        <span className="inline-block rounded-full bg-emerald-50 px-1.5 py-0.2 text-[9px] font-medium text-emerald-700 border border-emerald-200 mt-0.5">
                          Bobot {comp.weight}%
                        </span>
                      </th>
                    ))}
                    <th className="py-3 px-3 w-28 text-center border-r border-gray-200 bg-blue-50/50">
                      <div className="font-bold text-blue-900">Nilai Akhir</div>
                      <div className="text-[10px] font-normal text-blue-700">Rata-rata</div>
                    </th>
                    <th className="py-3 px-3 w-28 text-center">Status</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-200">
                  {filteredStudents.map((row, idx) => {
                    const studentId = row.student.id;
                    const studentName = row.student.full_name || row.student.name || '-';

                    return (
                      <tr key={studentId} className="hover:bg-slate-50/70 transition">
                        <td className="py-2.5 px-3 text-center text-gray-500 border-r border-gray-100">
                          {idx + 1}
                        </td>
                        <td className="py-2.5 px-3 font-mono text-[11px] text-gray-700 border-r border-gray-100">
                          <div>{row.student.nisn || '-'}</div>
                          <div className="text-[10px] text-gray-400">{row.student.nis}</div>
                        </td>
                        <td className="py-2.5 px-3 font-medium text-gray-900 border-r border-gray-100">
                          <div className="flex items-center gap-1.5">
                            <span>{studentName}</span>
                            <span className="text-[10px] text-gray-400 font-mono">
                              ({row.student.gender})
                            </span>
                          </div>
                        </td>

                        {/* Component Cell Inputs */}
                        {matrixData.components.map((comp) => {
                          const val = gridValues[studentId]?.[comp.id] ?? '';
                          const isDirty = dirtyCells.has(`${studentId}:${comp.id}`);
                          const originalGrade = row.grades[comp.id];
                          const isLms = originalGrade?.source === 'lms';

                          const numVal = typeof val === 'number' ? val : parseFloat(String(val));
                          const isInvalid =
                            val !== '' && !isNaN(numVal) && (numVal < 0 || numVal > 100);

                          return (
                            <td
                              key={comp.id}
                              className={`py-1.5 px-2 text-center border-r border-gray-100 relative ${
                                isDirty ? 'bg-amber-50/40' : ''
                              }`}
                            >
                              <div className="relative inline-block w-full">
                                <input
                                  type="number"
                                  step="0.1"
                                  min="0"
                                  max="100"
                                  disabled={isPeriodLocked}
                                  value={val}
                                  onChange={(e) =>
                                    handleCellChange(studentId, comp.id, e.target.value)
                                  }
                                  onPaste={(e) =>
                                    handleCellPaste(e, idx, comp.id, filteredStudents)
                                  }
                                  placeholder="-"
                                  className={`w-full text-center py-1 px-1 text-xs font-semibold rounded border transition focus:outline-hidden ${
                                    isInvalid
                                      ? 'border-red-400 bg-red-50 text-red-700 focus:ring-1 focus:ring-red-500'
                                      : isDirty
                                      ? 'border-amber-400 bg-amber-50 text-amber-900 focus:ring-1 focus:ring-amber-500 font-bold'
                                      : 'border-gray-200 bg-white text-gray-900 hover:border-gray-300 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500'
                                  } disabled:bg-gray-100 disabled:text-gray-400`}
                                />

                                {isLms && (
                                  <span
                                    title="Nilai bersumber dari Tugas LMS"
                                    className="absolute -top-1.5 -right-1.5 flex h-3.5 w-3.5 items-center justify-center rounded-full bg-blue-600 text-[8px] font-bold text-white shadow-xs"
                                  >
                                    L
                                  </span>
                                )}
                              </div>
                            </td>
                          );
                        })}

                        {/* Weighted Final Score */}
                        <td className="py-2.5 px-3 text-center font-bold text-blue-900 border-r border-gray-100 bg-blue-50/20">
                          {row.final_score !== null ? row.final_score : '-'}
                        </td>

                        {/* Completeness Status */}
                        <td className="py-2.5 px-3 text-center">
                          {row.is_complete ? (
                            <span className="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700 border border-emerald-200">
                              <CheckCircle2 className="h-3 w-3" />
                              Lengkap
                            </span>
                          ) : (
                            <span className="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-700 border border-amber-200">
                              <AlertTriangle className="h-3 w-3" />
                              {row.filled_count}/{row.total_components}
                            </span>
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

      {/* TAB 2: GRADE COMPONENTS */}
      {activeTab === 'components' && (
        <div className="space-y-4">
          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
              <h3 className="text-sm font-semibold text-gray-900">
                Komponen Penilaian Mata Pelajaran
              </h3>
              <p className="text-xs text-gray-500">
                Atur bobot persentase komponen untuk mata pelajaran dan semester yang dipilih
              </p>
            </div>

            <Button
              type="button"
              size="sm"
              onClick={() => {
                setEditingComponent(null);
                setIsComponentDialogOpen(true);
              }}
              className="bg-emerald-600 hover:bg-emerald-700 text-white text-xs gap-1.5"
            >
              <Plus className="h-3.5 w-3.5" />
              Tambah Komponen
            </Button>
          </div>

          {/* Bobot Alert */}
          {totalWeight !== 100 && (
            <div className="flex items-center gap-2 rounded-lg bg-amber-50 p-3 text-xs text-amber-900 border border-amber-200">
              <Info className="h-4 w-4 text-amber-600 shrink-0" />
              <span>
                <strong>Perhatian:</strong> Total bobot komponen saat ini adalah{' '}
                <strong>{totalWeight}%</strong>. Pastikan total bobot seluruh komponen genap{' '}
                <strong>100%</strong> agar kalkulasi nilai akhir semester akurat.
              </span>
            </div>
          )}

          {isLoadingComponents ? (
            <div className="rounded-xl border border-gray-200 bg-white p-12 text-center shadow-xs">
              <Loader2 className="h-8 w-8 animate-spin text-emerald-600 mx-auto mb-3" />
              <p className="text-xs font-medium text-gray-600">Memuat komponen penilaian...</p>
            </div>
          ) : componentsList.length === 0 ? (
            <div className="rounded-xl border border-dashed border-gray-300 bg-slate-50/50 p-12 text-center">
              <Award className="h-10 w-10 text-gray-400 mx-auto mb-3" />
              <h3 className="text-sm font-semibold text-gray-900 mb-1">
                Belum Ada Komponen Penilaian
              </h3>
              <p className="text-xs text-gray-500 max-w-sm mx-auto mb-4">
                Belum ada komponen yang dikonfigurasi untuk mata pelajaran ini. Silakan tambahkan
                komponen baru.
              </p>
              <Button
                type="button"
                onClick={() => {
                  setEditingComponent(null);
                  setIsComponentDialogOpen(true);
                }}
                className="bg-emerald-600 hover:bg-emerald-700 text-white text-xs gap-1.5"
              >
                <Plus className="h-3.5 w-3.5" />
                Tambah Komponen
              </Button>
            </div>
          ) : (
            <div className="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xs">
              <table className="w-full text-left text-xs">
                <thead className="bg-slate-50 border-b border-gray-200 text-gray-600 font-semibold">
                  <tr>
                    <th className="py-3 px-4 w-12 text-center">No</th>
                    <th className="py-3 px-4 w-28">Kode</th>
                    <th className="py-3 px-4">Nama Komponen</th>
                    <th className="py-3 px-4 w-28 text-center">Bobot</th>
                    <th className="py-3 px-4">Cakupan Kelas</th>
                    <th className="py-3 px-4 w-24 text-right">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100">
                  {componentsList.map((comp, idx) => (
                    <tr key={comp.id} className="hover:bg-slate-50/70 transition">
                      <td className="py-3 px-4 text-center text-gray-500">{idx + 1}</td>
                      <td className="py-3 px-4 font-mono font-bold text-gray-900 uppercase">
                        {comp.code}
                      </td>
                      <td className="py-3 px-4 font-medium text-gray-900">{comp.name}</td>
                      <td className="py-3 px-4 text-center">
                        <span className="inline-block rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-bold text-emerald-700 border border-emerald-200">
                          {comp.weight}%
                        </span>
                      </td>
                      <td className="py-3 px-4 text-gray-600">
                        {comp.class ? (
                          <span className="rounded bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-700">
                            Kelas {comp.class.name}
                          </span>
                        ) : (
                          <span className="text-[11px] text-gray-500 italic">
                            Semua Rombel (Umum)
                          </span>
                        )}
                      </td>
                      <td className="py-3 px-4 text-right space-x-1">
                        <button
                          type="button"
                          onClick={() => {
                            setEditingComponent(comp);
                            setIsComponentDialogOpen(true);
                          }}
                          className="rounded p-1 text-gray-500 hover:bg-gray-100 hover:text-emerald-600 transition"
                          title="Edit Komponen"
                        >
                          <Edit2 className="h-4 w-4" />
                        </button>
                        <button
                          type="button"
                          onClick={() => handleDeleteComponent(comp.id)}
                          className="rounded p-1 text-gray-500 hover:bg-gray-100 hover:text-red-600 transition"
                          title="Hapus Komponen"
                        >
                          <Trash2 className="h-4 w-4" />
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
                <tfoot className="bg-slate-50 border-t border-gray-200 font-semibold text-gray-900">
                  <tr>
                    <td colSpan={3} className="py-3 px-4 text-right">
                      Total Bobot:
                    </td>
                    <td className="py-3 px-4 text-center">
                      <span
                        className={`inline-block rounded-full px-2.5 py-0.5 text-xs font-bold ${
                          totalWeight === 100
                            ? 'bg-emerald-100 text-emerald-800'
                            : 'bg-amber-100 text-amber-800'
                        }`}
                      >
                        {totalWeight}%
                      </span>
                    </td>
                    <td colSpan={2} className="py-3 px-4 text-xs text-gray-500">
                      {totalWeight === 100
                        ? 'Proporsi 100% ideal'
                        : 'Harap sesuaikan bobot menjadi 100%'}
                    </td>
                  </tr>
                </tfoot>
              </table>
            </div>
          )}
        </div>
      )}

      {/* MODAL 1: GRADE COMPONENT DIALOG */}
      <GradeComponentDialog
        isOpen={isComponentDialogOpen}
        onClose={() => setIsComponentDialogOpen(false)}
        onSuccess={() => {
          fetchComponents();
          fetchMatrix();
        }}
        editingComponent={editingComponent}
        defaultSubjectId={selectedSubjectId}
        defaultSemesterId={selectedSemesterId}
        defaultClassId={selectedClassId}
        subjects={subjects}
        semesters={semesters}
        classes={classes}
      />

      {/* MODAL 2: GRADE PERIOD LOCK DIALOG */}
      <GradePeriodLockDialog
        isOpen={isLockDialogOpen}
        onClose={() => setIsLockDialogOpen(false)}
        onSuccess={() => {
          if (selectedSemesterId) fetchPeriods(selectedSemesterId);
          fetchMatrix();
        }}
        period={activeLockPeriod}
      />

      {/* MODAL 3: PASTE HELP DIALOG */}
      {isPasteHelpOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="w-full max-w-md rounded-xl bg-white shadow-2xl p-6 animate-in fade-in zoom-in-95 duration-200">
            <div className="flex items-center gap-2.5 mb-3">
              <div className="rounded-lg bg-emerald-50 p-2 text-emerald-600 border border-emerald-100">
                <FileSpreadsheet className="h-5 w-5" />
              </div>
              <h3 className="text-base font-semibold text-gray-900">Bantuan Paste dari Excel</h3>
            </div>
            <div className="space-y-3 text-xs text-gray-600 leading-relaxed">
              <p>
                Anda dapat menyalin (copy) satu kolom nilai dari spreadsheet (Microsoft Excel atau
                Google Sheets), lalu menempelkannya (paste) langsung ke dalam sel buku nilai
                SISTLABUM.
              </p>
              <div className="rounded-lg bg-slate-50 p-3 border border-slate-200 font-mono text-[11px] text-slate-800 space-y-1">
                <p>1. Salin baris angka di Excel (misal: 85, 90, 78).</p>
                <p>2. Klik pada sel siswa pertama yang ingin diisi.</p>
                <p>3. Tekan tombol Ctrl+V (atau Cmd+V di Mac).</p>
                <p>4. Nilai akan otomatis terisi berurutan ke baris bawah.</p>
              </div>
              <p className="text-[11px] text-gray-500">
                Setelah selesai paste, jangan lupa klik tombol{' '}
                <strong className="text-emerald-700">Simpan Semua Nilai</strong> di pojok kanan atas
                grid.
              </p>
            </div>
            <div className="mt-5 flex justify-end">
              <Button
                type="button"
                onClick={() => setIsPasteHelpOpen(false)}
                className="text-xs bg-emerald-600 hover:bg-emerald-700 text-white"
              >
                Mengerti
              </Button>
            </div>
          </div>
        </div>
      )}

      {/* Dialog Konfirmasi Hapus Komponen Penilaian */}
      <ConfirmDialog
        isOpen={!!componentToDelete}
        onClose={() => setComponentToDelete(null)}
        onConfirm={handleConfirmDeleteComponent}
        title="Hapus Komponen Penilaian"
        description="Apakah Anda yakin ingin menghapus komponen penilaian ini? Seluruh nilai siswa yang tersimpan pada komponen ini akan ikut terhapus."
        confirmLabel="Ya, Hapus"
        cancelLabel="Batal"
        variant="danger"
        isLoading={isDeletingComponent}
      />
    </div>
  );
}
