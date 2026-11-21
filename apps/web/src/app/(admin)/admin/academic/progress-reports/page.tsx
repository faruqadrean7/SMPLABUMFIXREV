'use client';

import React, { useEffect, useState, useCallback } from 'react';
import {
  ClassProgressReportsStatus,
  ClassProgressReportStudentRow,
  GradePeriodItem,
  ProgressReportItem,
  ProgressReportSnapshot,
  PROGRESS_REPORT_STATUS_LABELS,
  PROGRESS_REPORT_STATUS_COLORS,
  ProgressReportStatus,
} from '@/types/academic';
import { AcademicYearItem, SemesterItem, SchoolClassItem } from '@/types/master';
import { academicApi } from '@/features/academic/academic-api';
import { academicYearsApi } from '@/features/master/academic-years-api';
import { classesApi } from '@/features/master/classes-api';
import { ProgressReportModal } from '@/features/academic/progress-reports';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { toast } from '@/components/ui/toast';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
  Award,
  Calendar,
  CheckCircle2,
  Download,
  Eye,
  FileCheck2,
  Filter,
  Loader2,
  RefreshCw,
  Search,
  Sparkles,
  UserCheck,
  Users,
  AlertCircle,
  FileText,
  Clock,
  Printer,
  ChevronRight,
  ShieldCheck,
  Send,
} from 'lucide-react';

export default function ProgressReportsAdminPage() {
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

  // Class Status Data
  const [classStatusData, setClassStatusData] =
    useState<ClassProgressReportsStatus | null>(null);
  const [isLoadingStatus, setIsLoadingStatus] = useState<boolean>(false);
  const [statusError, setStatusError] = useState<string | null>(null);

  // Search & Status Tabs
  const [searchQuery, setSearchQuery] = useState<string>('');
  const [activeTab, setActiveTab] = useState<
    'all' | 'belum_dibuat' | 'menunggu_acc' | 'disetujui' | 'terbit'
  >('all');

  // Modal State
  const [isModalOpen, setIsModalOpen] = useState<boolean>(false);
  const [selectedReportForModal, setSelectedReportForModal] =
    useState<ProgressReportItem | null>(null);
  const [previewSnapshotForModal, setPreviewSnapshotForModal] =
    useState<ProgressReportSnapshot | null>(null);
  const [isLoadingPreview, setIsLoadingPreview] = useState<boolean>(false);

  // Mass Actions Execution
  const [isProcessingMassAction, setIsProcessingMassAction] =
    useState<boolean>(false);
  const [massConfirm, setMassConfirm] = useState<{
    isOpen: boolean;
    title: string;
    description: string;
    onConfirm: () => Promise<void>;
  }>({
    isOpen: false,
    title: '',
    description: '',
    onConfirm: async () => {},
  });
  const [massActionFeedback, setMassActionFeedback] = useState<{
    type: 'success' | 'error';
    message: string;
  } | null>(null);

  // 1. Initial Master Data Loading
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

        const activeYear =
          yearList.find((y: AcademicYearItem) => y.is_active) || yearList[0];
        if (activeYear) {
          setSelectedYearId(activeYear.id);
        }

        const activeSem =
          semList.find((s: SemesterItem) => s.is_active) || semList[0];
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

  // 2. Load Periods whenever selectedSemesterId changes
  useEffect(() => {
    async function loadPeriods() {
      if (!selectedSemesterId) {
        setPeriods([]);
        return;
      }
      try {
        const list = await academicApi.listGradePeriods({
          semester_id: selectedSemesterId,
        });
        setPeriods(list || []);
        if (list && list.length > 0) {
          const openPeriod = list.find((p) => p.status === 'open') || list[0];
          setSelectedPeriodId(openPeriod.id);
        } else {
          setSelectedPeriodId('');
        }
      } catch (err) {
        console.error('Gagal memuat daftar periode nilai:', err);
      }
    }
    loadPeriods();
  }, [selectedSemesterId]);

  // 3. Load Class Status Overview
  const fetchClassStatus = useCallback(async () => {
    if (!selectedClassId || !selectedPeriodId) {
      setClassStatusData(null);
      return;
    }
    setIsLoadingStatus(true);
    setStatusError(null);
    setMassActionFeedback(null);
    try {
      const data = await academicApi.getClassProgressReportsStatus(
        selectedClassId,
        selectedPeriodId
      );
      setClassStatusData(data);
    } catch (err: any) {
      console.error('Gagal memuat status e-progress report rombel:', err);
      setStatusError(
        err.response?.data?.message ||
          'Gagal memuat status e-progress report kelas. Pastikan hak akses wali kelas/admin sesuai.'
      );
      setClassStatusData(null);
    } finally {
      setIsLoadingStatus(false);
    }
  }, [selectedClassId, selectedPeriodId]);

  useEffect(() => {
    fetchClassStatus();
  }, [fetchClassStatus]);

  // Handle Mass Actions
  const handleGenerateClassReports = () => {
    if (!selectedClassId || !selectedPeriodId) return;
    setMassConfirm({
      isOpen: true,
      title: 'Generate E-Progress Report Massal',
      description: 'Generate E-Progress Report bulanan untuk seluruh siswa di kelas ini yang belum berstatus disetujui/terbit?',
      onConfirm: async () => {
        setIsProcessingMassAction(true);
        setMassActionFeedback(null);
        try {
          await academicApi.generateProgressReports({
            class_id: selectedClassId,
            period_id: selectedPeriodId,
            status: 'menunggu_acc',
          });
          const successMsg = 'Berhasil mengompilasi dan mengenerate snapshot E-Progress Report untuk seluruh siswa kelas!';
          setMassActionFeedback({ type: 'success', message: successMsg });
          toast.success(successMsg);
          fetchClassStatus();
        } catch (err: any) {
          const errMsg = err.response?.data?.message || 'Gagal men-generate rapor massal kelas. Periksa kelengkapan nilai.';
          setMassActionFeedback({ type: 'error', message: errMsg });
          toast.error(errMsg);
        } finally {
          setIsProcessingMassAction(false);
        }
      },
    });
  };

  const handleBulkApprove = (
    targetStatus: 'disetujui' | 'terbit' | 'draft' | 'menunggu_acc'
  ) => {
    if (!selectedClassId || !selectedPeriodId) return;
    const actionLabel =
      targetStatus === 'terbit'
        ? 'Menerbitkan seluruh rapor ke orang tua & siswa'
        : targetStatus === 'disetujui'
        ? 'Menyetujui (ACC) seluruh rapor kelas ini'
        : targetStatus === 'menunggu_acc'
        ? 'Mengajukan seluruh draf ke antrean ACC Kepala Sekolah'
        : 'Mengembalikan seluruh rapor kelas ke draf';

    setMassConfirm({
      isOpen: true,
      title: 'Konfirmasi Aksi Massal E-Progress Report',
      description: `Apakah Anda yakin ingin ${actionLabel}?`,
      onConfirm: async () => {
        setIsProcessingMassAction(true);
        setMassActionFeedback(null);
        try {
          const res = await academicApi.bulkApproveProgressReports({
            class_id: selectedClassId,
            period_id: selectedPeriodId,
            status: targetStatus,
          });
          const successMsg = `Berhasil memproses ${res.count} rapor ke status ${PROGRESS_REPORT_STATUS_LABELS[targetStatus]}.`;
          setMassActionFeedback({ type: 'success', message: successMsg });
          toast.success(successMsg);
          fetchClassStatus();
        } catch (err: any) {
          const errMsg = err.response?.data?.message || 'Gagal memproses persetujuan massal. Pastikan akun memiliki wewenang Kepala Sekolah / Admin.';
          setMassActionFeedback({ type: 'error', message: errMsg });
          toast.error(errMsg);
        } finally {
          setIsProcessingMassAction(false);
        }
      },
    });
  };

  // Handle Single Student Preview / Detail
  const handleOpenReportDetail = async (row: ClassProgressReportStudentRow) => {
    if (row.report?.id) {
      // Load full report detail
      try {
        const fullReport = await academicApi.getProgressReport(row.report.id);
        setSelectedReportForModal(fullReport);
        setPreviewSnapshotForModal(null);
        setIsModalOpen(true);
      } catch (err) {
        toast.error('Gagal memuat detail e-progress report siswa.');
      }
    } else {
      // Load preview on-the-fly
      if (!selectedPeriodId) return;
      setIsLoadingPreview(true);
      try {
        const preview = await academicApi.previewProgressReport(
          row.student.id,
          selectedPeriodId
        );
        setSelectedReportForModal(null);
        setPreviewSnapshotForModal(preview);
        setIsModalOpen(true);
      } catch (err: any) {
        toast.error(
          err.response?.data?.message ||
            'Gagal menghasilkan pratinjau rapor siswa. Pastikan siswa memiliki data nilai/karakter.'
        );
      } finally {
        setIsLoadingPreview(false);
      }
    }
  };

  const handleGenerateSingle = async (studentId: string) => {
    if (!selectedPeriodId) return;
    try {
      await academicApi.generateProgressReports({
        student_id: studentId,
        period_id: selectedPeriodId,
        status: 'menunggu_acc',
      });
      toast.success('E-Progress report siswa berhasil di-generate.');
      fetchClassStatus();
    } catch (err: any) {
      toast.error(
        err.response?.data?.message ||
          'Gagal men-generate e-progress report siswa.'
      );
    }
  };

  // Filter student rows
  const students = classStatusData?.students || [];
  const filteredStudents = students.filter((row) => {
    const matchesSearch =
      !searchQuery ||
      row.student.full_name
        ?.toLowerCase()
        .includes(searchQuery.toLowerCase()) ||
      row.student.nis?.toLowerCase().includes(searchQuery.toLowerCase()) ||
      row.student.nisn?.toLowerCase().includes(searchQuery.toLowerCase());

    if (!matchesSearch) return false;

    if (activeTab === 'belum_dibuat') return !row.report;
    if (activeTab === 'menunggu_acc') return row.report?.status === 'menunggu_acc';
    if (activeTab === 'disetujui') return row.report?.status === 'disetujui';
    if (activeTab === 'terbit') return row.report?.status === 'terbit';

    return true;
  });

  const summary = classStatusData?.summary || {
    total_students: 0,
    generated_count: 0,
    draft_count: 0,
    menunggu_acc_count: 0,
    disetujui_count: 0,
    terbit_count: 0,
    belum_dibuat_count: 0,
    ready_to_generate_count: 0,
  };

  return (
    <div className="space-y-6">
      {/* Header Banner */}
      <div className="rounded-xl border border-emerald-100 bg-gradient-to-r from-emerald-500/10 via-teal-500/5 to-transparent p-6">
        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div className="flex items-center gap-3">
            <div className="rounded-xl bg-emerald-600 p-2.5 text-white shadow-sm">
              <Award className="h-6 w-6" />
            </div>
            <div>
              <h1 className="text-xl font-bold tracking-tight text-gray-900">
                E-Progress Report &amp; Rapor Bulanan Siswa
              </h1>
              <p className="text-xs text-gray-600 mt-0.5">
                Integrasi nilai akademik, kehadiran bulanan, 8 dimensi karakter, dan pengesahan digital QR Kepala Sekolah
              </p>
            </div>
          </div>

          <div className="flex items-center gap-2">
            <Button
              variant="outline"
              size="sm"
              onClick={fetchClassStatus}
              disabled={isLoadingStatus}
              className="gap-1.5 text-xs text-gray-700 bg-white"
            >
              <RefreshCw
                className={`h-3.5 w-3.5 ${isLoadingStatus ? 'animate-spin' : ''}`}
              />
              <span>Segarkan Data</span>
            </Button>
          </div>
        </div>
      </div>

      {/* Filter Control Bar */}
      <div className="rounded-xl border border-gray-200 bg-white p-4 shadow-xs">
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
          {/* Tahun Ajaran */}
          <div>
            <label className="block text-[11px] font-semibold text-gray-600 mb-1">
              Tahun Ajaran
            </label>
            <select
              value={selectedYearId}
              onChange={(e) => setSelectedYearId(e.target.value)}
              className="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-medium text-gray-900 focus:border-emerald-500 focus:outline-hidden"
            >
              {academicYears.map((ay) => (
                <option key={ay.id} value={ay.id}>
                  {ay.name} {ay.is_active ? '(Aktif)' : ''}
                </option>
              ))}
            </select>
          </div>

          {/* Semester */}
          <div>
            <label className="block text-[11px] font-semibold text-gray-600 mb-1">
              Semester
            </label>
            <select
              value={selectedSemesterId}
              onChange={(e) => setSelectedSemesterId(e.target.value)}
              className="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-medium text-gray-900 focus:border-emerald-500 focus:outline-hidden"
            >
              {semesters.map((s) => (
                <option key={s.id} value={s.id}>
                  {s.name} {s.is_active ? '(Aktif)' : ''}
                </option>
              ))}
            </select>
          </div>

          {/* Kelas / Rombel */}
          <div>
            <label className="block text-[11px] font-semibold text-gray-600 mb-1">
              Rombongan Belajar (Kelas)
            </label>
            <select
              value={selectedClassId}
              onChange={(e) => setSelectedClassId(e.target.value)}
              className="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-medium text-gray-900 focus:border-emerald-500 focus:outline-hidden"
            >
              {classes.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.name}
                </option>
              ))}
            </select>
          </div>

          {/* Periode Penilaian (Bulan) */}
          <div>
            <label className="block text-[11px] font-semibold text-gray-600 mb-1">
              Periode Penilaian (Bulan)
            </label>
            <select
              value={selectedPeriodId}
              onChange={(e) => setSelectedPeriodId(e.target.value)}
              className="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-medium text-gray-900 focus:border-emerald-500 focus:outline-hidden"
            >
              {periods.map((p) => (
                <option key={p.id} value={p.id}>
                  Bulan {p.month} ({p.status === 'open' ? 'Buka' : 'Terkunci'})
                </option>
              ))}
            </select>
          </div>
        </div>
      </div>

      {/* KPI Cards Summary */}
      <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-xs">
          <div className="flex items-center justify-between">
            <span className="text-xs font-medium text-gray-600">Total Siswa</span>
            <Users className="h-4 w-4 text-slate-500" />
          </div>
          <div className="mt-2 text-2xl font-extrabold text-gray-900 font-mono">
            {summary.total_students}
          </div>
          <p className="mt-1 text-[11px] text-gray-500">
            {summary.ready_to_generate_count} siswa data lengkap
          </p>
        </div>

        <div className="rounded-xl border border-gray-200 bg-white p-4 shadow-xs">
          <div className="flex items-center justify-between">
            <span className="text-xs font-medium text-gray-600">Belum Dibuat</span>
            <Clock className="h-4 w-4 text-gray-400" />
          </div>
          <div className="mt-2 text-2xl font-extrabold text-gray-700 font-mono">
            {summary.belum_dibuat_count}
          </div>
          <p className="mt-1 text-[11px] text-gray-500">Menunggu generate walas</p>
        </div>

        <div className="rounded-xl border border-amber-200 bg-white p-4 shadow-xs">
          <div className="flex items-center justify-between">
            <span className="text-xs font-medium text-amber-700">Menunggu ACC</span>
            <FileCheck2 className="h-4 w-4 text-amber-600" />
          </div>
          <div className="mt-2 text-2xl font-extrabold text-amber-700 font-mono">
            {summary.menunggu_acc_count}
          </div>
          <p className="mt-1 text-[11px] text-amber-600">Antrean Kepala Sekolah</p>
        </div>

        <div className="rounded-xl border border-emerald-200 bg-white p-4 shadow-xs">
          <div className="flex items-center justify-between">
            <span className="text-xs font-medium text-emerald-700">Disetujui / Terbit</span>
            <CheckCircle2 className="h-4 w-4 text-emerald-600" />
          </div>
          <div className="mt-2 text-2xl font-extrabold text-emerald-700 font-mono">
            {summary.disetujui_count + summary.terbit_count}
          </div>
          <p className="mt-1 text-[11px] text-emerald-600">
            {summary.terbit_count} telah terbit ke ortu/siswa
          </p>
        </div>
      </div>

      {/* Mass Actions Toolbar */}
      <div className="rounded-xl border border-gray-200 bg-white p-4 shadow-xs flex flex-wrap items-center justify-between gap-3">
        <div className="flex flex-wrap items-center gap-2">
          <Button
            size="sm"
            onClick={handleGenerateClassReports}
            disabled={isProcessingMassAction || !selectedClassId || !selectedPeriodId}
            className="h-8 gap-1.5 text-xs bg-slate-900 hover:bg-slate-800 text-white"
          >
            {isProcessingMassAction ? (
              <Loader2 className="h-3.5 w-3.5 animate-spin" />
            ) : (
              <Sparkles className="h-3.5 w-3.5 text-amber-400" />
            )}
            <span>Generate Semua Siswa Rombel</span>
          </Button>

          <Button
            variant="outline"
            size="sm"
            onClick={() => handleBulkApprove('menunggu_acc')}
            disabled={isProcessingMassAction || summary.draft_count === 0}
            className="h-8 gap-1.5 text-xs text-amber-800 hover:bg-amber-50 border-amber-200"
          >
            <Send className="h-3.5 w-3.5" />
            <span>Ajukan ACC Semua Draf</span>
          </Button>

          <Button
            variant="outline"
            size="sm"
            onClick={() => handleBulkApprove('disetujui')}
            disabled={isProcessingMassAction || summary.menunggu_acc_count === 0}
            className="h-8 gap-1.5 text-xs text-blue-800 hover:bg-blue-50 border-blue-200"
          >
            <CheckCircle2 className="h-3.5 w-3.5" />
            <span>ACC Semua (Kepsek)</span>
          </Button>

          <Button
            variant="outline"
            size="sm"
            onClick={() => handleBulkApprove('terbit')}
            disabled={
              isProcessingMassAction ||
              (summary.disetujui_count === 0 && summary.menunggu_acc_count === 0)
            }
            className="h-8 gap-1.5 text-xs text-emerald-800 hover:bg-emerald-50 border-emerald-200"
          >
            <ShieldCheck className="h-3.5 w-3.5" />
            <span>Terbitkan Semua ke Ortu</span>
          </Button>
        </div>

        {classStatusData?.class.homeroom_teacher && (
          <div className="text-xs text-gray-500">
            Wali Kelas: <span className="font-semibold text-gray-800">{classStatusData.class.homeroom_teacher}</span>
          </div>
        )}
      </div>

      {massActionFeedback && (
        <div
          className={`rounded-lg p-3 text-xs font-medium flex items-center gap-2 ${
            massActionFeedback.type === 'success'
              ? 'bg-emerald-50 text-emerald-800 border border-emerald-200'
              : 'bg-rose-50 text-rose-800 border border-rose-200'
          }`}
        >
          {massActionFeedback.type === 'success' ? (
            <CheckCircle2 className="h-4 w-4 shrink-0 text-emerald-600" />
          ) : (
            <AlertCircle className="h-4 w-4 shrink-0 text-rose-600" />
          )}
          <span>{massActionFeedback.message}</span>
        </div>
      )}

      {/* Main Roster Card */}
      <div className="rounded-xl border border-gray-200 bg-white shadow-xs overflow-hidden">
        {/* Table Filters & Search Bar */}
        <div className="flex flex-col gap-3 border-b border-gray-100 p-4 sm:flex-row sm:items-center sm:justify-between">
          {/* Status Tabs */}
          <div className="flex flex-wrap gap-1.5">
            <button
              onClick={() => setActiveTab('all')}
              className={`rounded-lg px-3 py-1.5 text-xs font-semibold transition ${
                activeTab === 'all'
                  ? 'bg-slate-900 text-white'
                  : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
              }`}
            >
              Semua ({summary.total_students})
            </button>
            <button
              onClick={() => setActiveTab('belum_dibuat')}
              className={`rounded-lg px-3 py-1.5 text-xs font-semibold transition ${
                activeTab === 'belum_dibuat'
                  ? 'bg-slate-900 text-white'
                  : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
              }`}
            >
              Belum Dibuat ({summary.belum_dibuat_count})
            </button>
            <button
              onClick={() => setActiveTab('menunggu_acc')}
              className={`rounded-lg px-3 py-1.5 text-xs font-semibold transition ${
                activeTab === 'menunggu_acc'
                  ? 'bg-amber-600 text-white'
                  : 'bg-amber-50 text-amber-800 hover:bg-amber-100'
              }`}
            >
              Menunggu ACC ({summary.menunggu_acc_count})
            </button>
            <button
              onClick={() => setActiveTab('disetujui')}
              className={`rounded-lg px-3 py-1.5 text-xs font-semibold transition ${
                activeTab === 'disetujui'
                  ? 'bg-blue-600 text-white'
                  : 'bg-blue-50 text-blue-800 hover:bg-blue-100'
              }`}
            >
              Disetujui ({summary.disetujui_count})
            </button>
            <button
              onClick={() => setActiveTab('terbit')}
              className={`rounded-lg px-3 py-1.5 text-xs font-semibold transition ${
                activeTab === 'terbit'
                  ? 'bg-emerald-600 text-white'
                  : 'bg-emerald-50 text-emerald-800 hover:bg-emerald-100'
              }`}
            >
              Terbit ({summary.terbit_count})
            </button>
          </div>

          {/* Search Box */}
          <div className="relative w-full sm:w-64">
            <Search className="absolute left-3 top-2.5 h-3.5 w-3.5 text-gray-400" />
            <Input
              type="text"
              placeholder="Cari siswa atau NIS..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="pl-8 text-xs h-8"
            />
          </div>
        </div>

        {/* Content Table Area */}
        {isLoadingStatus ? (
          <div className="flex flex-col items-center justify-center p-12 text-center text-xs text-gray-500">
            <Loader2 className="h-8 w-8 animate-spin text-emerald-600 mb-3" />
            <p className="font-semibold text-gray-800">Memuat data e-progress report rombel...</p>
            <p className="text-gray-400 mt-1">Mengagregasi nilai, presensi, dan catatan karakter</p>
          </div>
        ) : statusError ? (
          <div className="p-8 text-center">
            <AlertCircle className="mx-auto h-10 w-10 text-rose-500 mb-2" />
            <h3 className="text-sm font-bold text-gray-900">Gagal Memuat Data Rapor</h3>
            <p className="text-xs text-gray-600 mt-1 max-w-md mx-auto">{statusError}</p>
            <Button
              variant="outline"
              size="sm"
              onClick={fetchClassStatus}
              className="mt-4 text-xs"
            >
              Coba Lagi
            </Button>
          </div>
        ) : filteredStudents.length === 0 ? (
          <div className="p-12 text-center text-gray-500">
            <FileText className="mx-auto h-10 w-10 text-gray-300 mb-2" />
            <h3 className="text-sm font-semibold text-gray-900">
              Tidak Ada Siswa Ditemukan
            </h3>
            <p className="text-xs text-gray-500 mt-1">
              Tidak ada data siswa yang cocok dengan filter atau kata kunci pencarian.
            </p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs border-collapse">
              <thead className="bg-slate-50/75 text-gray-700 font-bold border-b border-gray-200">
                <tr>
                  <th className="py-3 px-4 w-12 text-center">No</th>
                  <th className="py-3 px-4">Nama Siswa &amp; Identitas</th>
                  <th className="py-3 px-4">Kesiapan Data Sumber</th>
                  <th className="py-3 px-4 text-center">Status Rapor</th>
                  <th className="py-3 px-4">Waktu &amp; Pengesahan</th>
                  <th className="py-3 px-4 text-right">Aksi</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100">
                {filteredStudents.map((row, idx) => {
                  const report = row.report;
                  const readiness = row.data_readiness;
                  const repStatus: ProgressReportStatus | 'belum_dibuat' =
                    report?.status ?? 'belum_dibuat';

                  return (
                    <tr key={row.student.id} className="hover:bg-slate-50/50 transition">
                      <td className="py-3 px-4 text-center text-gray-400 font-mono">
                        {idx + 1}
                      </td>

                      <td className="py-3 px-4">
                        <div className="font-bold text-gray-900 text-sm">
                          {row.student.full_name}
                        </div>
                        <div className="text-[11px] text-gray-500 font-mono mt-0.5 flex items-center gap-2">
                          <span>NIS: {row.student.nis}</span>
                          <span>•</span>
                          <span>NISN: {row.student.nisn}</span>
                          <span
                            className={`rounded px-1.5 py-0.2 text-[9px] font-bold ${
                              row.student.gender === 'L'
                                ? 'bg-blue-100 text-blue-700'
                                : 'bg-pink-100 text-pink-700'
                            }`}
                          >
                            {row.student.gender === 'L' ? 'Laki-laki' : 'Perempuan'}
                          </span>
                        </div>
                      </td>

                      <td className="py-3 px-4">
                        <div className="flex flex-wrap gap-1.5 items-center">
                          {/* Nilai */}
                          <span
                            className={`inline-flex items-center gap-1 rounded px-2 py-0.5 text-[10px] font-semibold border ${
                              readiness.has_grades
                                ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                                : 'bg-gray-100 text-gray-500 border-gray-200'
                            }`}
                          >
                            <span>Nilai:</span>
                            <span className="font-bold">
                              {readiness.graded_subjects_count} Mapel
                            </span>
                          </span>

                          {/* Karakter */}
                          <span
                            className={`inline-flex items-center gap-1 rounded px-2 py-0.5 text-[10px] font-semibold border ${
                              readiness.has_character_note
                                ? 'bg-blue-50 text-blue-700 border-blue-200'
                                : 'bg-amber-50 text-amber-700 border-amber-200'
                            }`}
                          >
                            <span>Karakter:</span>
                            <span className="font-bold">
                              {readiness.has_character_note ? 'Sudah' : 'Belum'}
                            </span>
                          </span>

                          {/* Presensi */}
                          <span className="inline-flex items-center gap-1 rounded bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-700 border border-slate-200">
                            <span>Presensi:</span>
                            <span className="font-mono font-bold">
                              {readiness.attendance_days_count} Hari
                            </span>
                          </span>
                        </div>
                      </td>

                      <td className="py-3 px-4 text-center">
                        {repStatus === 'belum_dibuat' ? (
                          <span className="inline-block rounded-full bg-gray-100 px-2.5 py-0.5 text-[11px] font-semibold text-gray-600 border border-gray-200">
                            Belum Dibuat
                          </span>
                        ) : (
                          <span
                            className={`inline-block rounded-full px-2.5 py-0.5 text-[11px] font-semibold border ${PROGRESS_REPORT_STATUS_COLORS[repStatus]}`}
                          >
                            {PROGRESS_REPORT_STATUS_LABELS[repStatus]}
                          </span>
                        )}
                      </td>

                      <td className="py-3 px-4 text-xs text-gray-600">
                        {report ? (
                          <div>
                            <div className="font-medium text-gray-900 text-[11px]">
                              {report.created_at
                                ? new Date(report.created_at).toLocaleDateString(
                                    'id-ID'
                                  )
                                : '-'}
                            </div>
                            <div className="text-[10px] text-gray-400">
                              {report.approver_name
                                ? `ACC: ${report.approver_name}`
                                : 'Belum di-ACC'}
                            </div>
                          </div>
                        ) : (
                          <span className="text-gray-400 italic text-[11px]">-</span>
                        )}
                      </td>

                      <td className="py-3 px-4 text-right">
                        <div className="flex items-center justify-end gap-1.5">
                          {report ? (
                            <Button
                              variant="outline"
                              size="sm"
                              onClick={() => handleOpenReportDetail(row)}
                              className="h-7 text-xs text-gray-700 hover:bg-emerald-50 hover:text-emerald-700 gap-1"
                            >
                              <Eye className="h-3 w-3" />
                              <span>Lihat Rapor</span>
                            </Button>
                          ) : (
                            <>
                              <Button
                                variant="outline"
                                size="sm"
                                onClick={() => handleOpenReportDetail(row)}
                                disabled={isLoadingPreview}
                                className="h-7 text-xs text-gray-600 hover:bg-gray-100 gap-1"
                              >
                                <FileText className="h-3 w-3" />
                                <span>Pratinjau</span>
                              </Button>
                              <Button
                                size="sm"
                                onClick={() => handleGenerateSingle(row.student.id)}
                                className="h-7 text-xs bg-emerald-600 hover:bg-emerald-700 text-white gap-1"
                              >
                                <Sparkles className="h-3 w-3" />
                                <span>Generate</span>
                              </Button>
                            </>
                          )}
                        </div>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Detail / Preview Modal */}
      <ProgressReportModal
        isOpen={isModalOpen}
        onClose={() => {
          setIsModalOpen(false);
          setSelectedReportForModal(null);
          setPreviewSnapshotForModal(null);
        }}
        report={selectedReportForModal}
        previewSnapshot={previewSnapshotForModal}
        canApprove={true}
        onStatusUpdated={() => {
          fetchClassStatus();
          setIsModalOpen(false);
        }}
        onGenerateFromPreview={() => {
          if (previewSnapshotForModal?.student.id) {
            handleGenerateSingle(previewSnapshotForModal.student.id);
            setIsModalOpen(false);
          }
        }}
      />

      {/* Dialog Konfirmasi Aksi Massal */}
      <ConfirmDialog
        isOpen={massConfirm.isOpen}
        onClose={() => setMassConfirm((prev) => ({ ...prev, isOpen: false }))}
        onConfirm={async () => {
          await massConfirm.onConfirm();
          setMassConfirm((prev) => ({ ...prev, isOpen: false }));
        }}
        title={massConfirm.title}
        description={massConfirm.description}
        confirmLabel="Ya, Lanjutkan"
        cancelLabel="Batal"
        variant="primary"
        isLoading={isProcessingMassAction}
      />
    </div>
  );
}
