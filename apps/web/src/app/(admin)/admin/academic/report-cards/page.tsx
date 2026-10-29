'use client';

import React, { useEffect, useState, useCallback } from 'react';
import {
  ClassReportCardsStatus,
  ClassReportCardStudentRow,
  ReportCardItem,
  ReportCardSnapshot,
  REPORT_CARD_STATUS_LABELS,
  REPORT_CARD_STATUS_COLORS,
  ReportCardStatus,
} from '@/types/academic';
import { AcademicYearItem, SemesterItem, SchoolClassItem } from '@/types/master';
import { academicApi } from '@/features/academic/academic-api';
import { academicYearsApi } from '@/features/master/academic-years-api';
import { classesApi } from '@/features/master/classes-api';
import { ReportCardModal, ReportTemplateDialog } from '@/features/academic/report-cards';
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
  Users,
  AlertCircle,
  Clock,
  Printer,
  ShieldCheck,
  Send,
  Lock,
  Unlock,
  Check,
  BookOpen,
} from 'lucide-react';

export default function ReportCardsAdminPage() {
  // Master Data Dropdowns
  const [academicYears, setAcademicYears] = useState<AcademicYearItem[]>([]);
  const [semesters, setSemesters] = useState<SemesterItem[]>([]);
  const [classes, setClasses] = useState<SchoolClassItem[]>([]);

  // Selected Filters
  const [selectedYearId, setSelectedYearId] = useState<string>('');
  const [selectedSemesterId, setSelectedSemesterId] = useState<string>('');
  const [selectedClassId, setSelectedClassId] = useState<string>('');

  // Class Status Data
  const [classStatusData, setClassStatusData] = useState<ClassReportCardsStatus | null>(null);
  const [isLoadingStatus, setIsLoadingStatus] = useState<boolean>(false);
  const [statusError, setStatusError] = useState<string | null>(null);

  // Search & Filter Tabs
  const [searchQuery, setSearchQuery] = useState<string>('');
  const [activeTab, setActiveTab] = useState<
    'all' | 'belum_dibuat' | 'draft' | 'terkunci' | 'disetujui' | 'terbit'
  >('all');

  // Modals State
  const [isReportModalOpen, setIsReportModalOpen] = useState<boolean>(false);
  const [selectedReportForModal, setSelectedReportForModal] = useState<ReportCardItem | null>(null);
  const [previewSnapshotForModal, setPreviewSnapshotForModal] = useState<ReportCardSnapshot | null>(null);
  const [isTemplateDialogOpen, setIsTemplateDialogOpen] = useState<boolean>(false);

  // Mass Actions Execution
  const [isProcessingMassAction, setIsProcessingMassAction] = useState<boolean>(false);
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

  // 2. Fetch Class Status Matrix
  const fetchClassStatus = useCallback(async () => {
    if (!selectedClassId || !selectedSemesterId) {
      setClassStatusData(null);
      return;
    }
    setIsLoadingStatus(true);
    setStatusError(null);
    setMassActionFeedback(null);
    try {
      const data = await academicApi.getClassReportCardsStatus(
        selectedClassId,
        selectedSemesterId
      );
      setClassStatusData(data);
    } catch (err: any) {
      console.error('Gagal memuat status e-rapor semester rombel:', err);
      setStatusError(
        err.response?.data?.message ||
          'Gagal memuat status e-rapor kelas. Pastikan hak akses wali kelas atau kurikulum sesuai.'
      );
      setClassStatusData(null);
    } finally {
      setIsLoadingStatus(false);
    }
  }, [selectedClassId, selectedSemesterId]);

  useEffect(() => {
    fetchClassStatus();
  }, [fetchClassStatus]);

  // Mass Actions Handlers
  const handleGenerateClassReports = () => {
    if (!selectedClassId || !selectedSemesterId) return;
    setMassConfirm({
      isOpen: true,
      title: 'Generate E-Rapor Semester Massal',
      description: 'Generate seluruh e-rapor semester untuk kelas ini? Snapshot nilai & capaian kompetensi akan dihitung otomatis.',
      onConfirm: async () => {
        setIsProcessingMassAction(true);
        setMassActionFeedback(null);
        try {
          await academicApi.generateReportCards({
            class_id: selectedClassId,
            semester_id: selectedSemesterId,
            status: 'draft',
          });
          const successMsg = 'Berhasil men-generate seluruh e-rapor semester rombel.';
          setMassActionFeedback({ type: 'success', message: successMsg });
          toast.success(successMsg);
          await fetchClassStatus();
        } catch (err: any) {
          const errMsg = err.response?.data?.message || 'Gagal men-generate e-rapor semester rombel. Silakan periksa kembali data nilai.';
          setMassActionFeedback({ type: 'error', message: errMsg });
          toast.error(errMsg);
        } finally {
          setIsProcessingMassAction(false);
        }
      },
    });
  };

  const handleBulkLock = (isLocked: boolean) => {
    if (!selectedClassId || !selectedSemesterId) return;
    const actionName = isLocked ? 'mengunci' : 'membuka kunci';
    setMassConfirm({
      isOpen: true,
      title: `Konfirmasi ${actionName.charAt(0).toUpperCase() + actionName.slice(1)} E-Rapor`,
      description: `Apakah Anda yakin ingin ${actionName} seluruh e-rapor semester pada rombel ini?`,
      onConfirm: async () => {
        setIsProcessingMassAction(true);
        setMassActionFeedback(null);
        try {
          const res = await academicApi.bulkLockReportCards({
            class_id: selectedClassId,
            semester_id: selectedSemesterId,
            is_locked: isLocked,
            reason: isLocked ? 'Penguncian nilai akhir semester oleh kurikulum' : 'Pembukaan kunci oleh kurikulum',
          });
          const successMsg = `Berhasil ${actionName} ${res.count} e-rapor semester.`;
          setMassActionFeedback({ type: 'success', message: successMsg });
          toast.success(successMsg);
          await fetchClassStatus();
        } catch (err: any) {
          const errMsg = err.response?.data?.message || `Gagal ${actionName} e-rapor semester rombel.`;
          setMassActionFeedback({ type: 'error', message: errMsg });
          toast.error(errMsg);
        } finally {
          setIsProcessingMassAction(false);
        }
      },
    });
  };

  const handleBulkApprove = (statusToSet: 'disetujui' | 'terbit') => {
    if (!selectedClassId || !selectedSemesterId) return;
    const actionLabel = statusToSet === 'disetujui' ? 'mengesahkan (ACC)' : 'menerbitkan';
    setMassConfirm({
      isOpen: true,
      title: `Konfirmasi ${actionLabel.charAt(0).toUpperCase() + actionLabel.slice(1)} E-Rapor`,
      description: `Apakah Anda yakin ingin ${actionLabel} seluruh e-rapor semester pada rombel ini?`,
      onConfirm: async () => {
        setIsProcessingMassAction(true);
        setMassActionFeedback(null);
        try {
          const res = await academicApi.bulkApproveReportCards({
            class_id: selectedClassId,
            semester_id: selectedSemesterId,
            status: statusToSet,
            note: `Pengesahan massal status ${statusToSet} semester.`,
          });
          const successMsg = `Berhasil ${actionLabel} ${res.count} e-rapor semester.`;
          setMassActionFeedback({ type: 'success', message: successMsg });
          toast.success(successMsg);
          await fetchClassStatus();
        } catch (err: any) {
          const errMsg = err.response?.data?.message || `Gagal ${actionLabel} e-rapor semester rombel.`;
          setMassActionFeedback({ type: 'error', message: errMsg });
          toast.error(errMsg);
        } finally {
          setIsProcessingMassAction(false);
        }
      },
    });
  };

  // Preview or View Single Report
  const handleOpenReport = async (row: ClassReportCardStudentRow) => {
    setSelectedReportForModal(null);
    setPreviewSnapshotForModal(null);
    setIsReportModalOpen(true);

    if (row.report?.id) {
      try {
        const fullReport = await academicApi.getReportCard(row.report.id);
        setSelectedReportForModal(fullReport);
      } catch (err: any) {
        toast.error(err.response?.data?.message || 'Gagal memuat detail e-rapor.');
      }
    } else {
      // Preview on-the-fly
      try {
        const snapshot = await academicApi.previewReportCard(row.student.id, selectedSemesterId);
        setPreviewSnapshotForModal(snapshot);
      } catch (err: any) {
        toast.error(err.response?.data?.message || 'Gagal memuat pratinjau snapshot e-rapor.');
      }
    }
  };

  const handleGenerateSingleFromPreview = async (studentId: string) => {
    try {
      await academicApi.generateReportCards({
        student_id: studentId,
        semester_id: selectedSemesterId,
        status: 'draft',
      });
      setIsReportModalOpen(false);
      await fetchClassStatus();
    } catch (err: any) {
      toast.error(err.response?.data?.message || 'Gagal generate e-rapor.');
    }
  };

  // Filtered Students List
  const students = classStatusData?.students || [];
  const filteredStudents = students.filter((row) => {
    const matchSearch =
      row.student.full_name.toLowerCase().includes(searchQuery.toLowerCase()) ||
      row.student.nis.toLowerCase().includes(searchQuery.toLowerCase()) ||
      row.student.nisn.toLowerCase().includes(searchQuery.toLowerCase());

    if (!matchSearch) return false;

    if (activeTab === 'all') return true;
    if (activeTab === 'belum_dibuat') return !row.report;
    return row.report?.status === activeTab;
  });

  const summary = classStatusData?.summary || {
    total_students: 0,
    generated_count: 0,
    draft_count: 0,
    terkunci_count: 0,
    disetujui_count: 0,
    terbit_count: 0,
    belum_dibuat_count: 0,
    ready_to_generate_count: 0,
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <div className="flex items-center gap-2.5">
            <h1 className="text-xl font-bold tracking-tight text-gray-900 sm:text-2xl">
              E-Rapor Semester &amp; Capaian Kompetensi
            </h1>
            <span className="inline-flex items-center rounded-md bg-blue-50 px-2 py-0.5 text-xs font-semibold text-blue-700 border border-blue-200">
              ADM-GRD-04
            </span>
          </div>
          <p className="mt-1 text-xs text-gray-500 sm:text-sm">
            Kompilasi laporan hasil belajar semester, deskripsi capaian pembelajaran Kurikulum Merdeka (KKTP), dan pengesahan resmi.
          </p>
        </div>

        <div className="flex flex-wrap items-center gap-2">
          <Button
            variant="outline"
            size="sm"
            onClick={() => setIsTemplateDialogOpen(true)}
            className="gap-1.5 text-xs text-purple-700 border-purple-200 hover:bg-purple-50"
          >
            <Sparkles className="h-4 w-4" />
            Template Deskripsi Mapel
          </Button>
          <Button
            variant="outline"
            size="sm"
            onClick={fetchClassStatus}
            disabled={isLoadingStatus}
            className="gap-1.5 text-xs"
          >
            <RefreshCw className={`h-4 w-4 ${isLoadingStatus ? 'animate-spin' : ''}`} />
            Muat Ulang
          </Button>
        </div>
      </div>

      {/* Filter Toolbar Card */}
      <div className="rounded-xl border border-gray-200 bg-white p-4 shadow-xs">
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
          {/* Tahun Ajaran */}
          <div>
            <label className="block text-xs font-medium text-gray-700 mb-1">
              Tahun Ajaran
            </label>
            <select
              value={selectedYearId}
              onChange={(e) => setSelectedYearId(e.target.value)}
              className="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs text-gray-900 focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
            >
              {academicYears.map((y) => (
                <option key={y.id} value={y.id}>
                  {y.name} {y.is_active ? '(Aktif)' : ''}
                </option>
              ))}
            </select>
          </div>

          {/* Semester */}
          <div>
            <label className="block text-xs font-medium text-gray-700 mb-1">
              Semester
            </label>
            <select
              value={selectedSemesterId}
              onChange={(e) => setSelectedSemesterId(e.target.value)}
              className="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs text-gray-900 focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
            >
              {semesters.map((s) => (
                <option key={s.id} value={s.id}>
                  {s.name} {s.is_active ? '(Aktif)' : ''}
                </option>
              ))}
            </select>
          </div>

          {/* Rombel / Kelas */}
          <div>
            <label className="block text-xs font-medium text-gray-700 mb-1">
              Kelas / Rombel
            </label>
            <select
              value={selectedClassId}
              onChange={(e) => setSelectedClassId(e.target.value)}
              className="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs text-gray-900 focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
            >
              {classes.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.name}
                </option>
              ))}
            </select>
          </div>
        </div>
      </div>

      {/* Feedback Alert */}
      {massActionFeedback && (
        <div
          className={`rounded-xl border p-3.5 text-xs flex items-center justify-between ${
            massActionFeedback.type === 'success'
              ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
              : 'border-red-200 bg-red-50 text-red-800'
          }`}
        >
          <span className="flex items-center gap-2">
            {massActionFeedback.type === 'success' ? (
              <CheckCircle2 className="h-4 w-4 text-emerald-600" />
            ) : (
              <AlertCircle className="h-4 w-4 text-red-600" />
            )}
            {massActionFeedback.message}
          </span>
          <button onClick={() => setMassActionFeedback(null)} className="text-gray-400 hover:text-gray-600">
            &times;
          </button>
        </div>
      )}

      {statusError && (
        <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-xs text-red-700 flex items-center gap-2">
          <AlertCircle className="h-5 w-5 shrink-0 text-red-500" />
          <span>{statusError}</span>
        </div>
      )}

      {/* KPI Cards Grid */}
      <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        {/* Total Siswa */}
        <div className="rounded-xl border border-gray-200 bg-white p-3.5 shadow-xs">
          <div className="flex items-center justify-between text-gray-500">
            <span className="text-[11px] font-medium uppercase tracking-wider">Total Siswa</span>
            <Users className="h-4 w-4 text-blue-500" />
          </div>
          <div className="mt-2 text-xl font-bold text-gray-900">{summary.total_students}</div>
          <div className="text-[11px] text-gray-500 mt-0.5">Aktif di rombel</div>
        </div>

        {/* Belum Dibuat */}
        <div className="rounded-xl border border-gray-200 bg-white p-3.5 shadow-xs">
          <div className="flex items-center justify-between text-gray-500">
            <span className="text-[11px] font-medium uppercase tracking-wider">Belum Dibuat</span>
            <Clock className="h-4 w-4 text-gray-400" />
          </div>
          <div className="mt-2 text-xl font-bold text-gray-600">{summary.belum_dibuat_count}</div>
          <div className="text-[11px] text-emerald-600 font-medium mt-0.5">
            {summary.ready_to_generate_count} siap generate
          </div>
        </div>

        {/* Draf */}
        <div className="rounded-xl border border-gray-200 bg-white p-3.5 shadow-xs">
          <div className="flex items-center justify-between text-gray-500">
            <span className="text-[11px] font-medium uppercase tracking-wider">Draf</span>
            <FileCheck2 className="h-4 w-4 text-gray-500" />
          </div>
          <div className="mt-2 text-xl font-bold text-gray-800">{summary.draft_count}</div>
          <div className="text-[11px] text-gray-500 mt-0.5">Bisa diedit walas</div>
        </div>

        {/* Terkunci */}
        <div className="rounded-xl border border-gray-200 bg-white p-3.5 shadow-xs">
          <div className="flex items-center justify-between text-gray-500">
            <span className="text-[11px] font-medium uppercase tracking-wider">Terkunci</span>
            <Lock className="h-4 w-4 text-amber-500" />
          </div>
          <div className="mt-2 text-xl font-bold text-amber-700">{summary.terkunci_count}</div>
          <div className="text-[11px] text-amber-600 mt-0.5">Kunci Kurikulum</div>
        </div>

        {/* Disetujui Kepsek */}
        <div className="rounded-xl border border-gray-200 bg-white p-3.5 shadow-xs">
          <div className="flex items-center justify-between text-gray-500">
            <span className="text-[11px] font-medium uppercase tracking-wider">Disahkan</span>
            <CheckCircle2 className="h-4 w-4 text-blue-500" />
          </div>
          <div className="mt-2 text-xl font-bold text-blue-700">{summary.disetujui_count}</div>
          <div className="text-[11px] text-blue-600 mt-0.5">ACC Kepala Sekolah</div>
        </div>

        {/* Terbit Resmi */}
        <div className="rounded-xl border border-gray-200 bg-white p-3.5 shadow-xs">
          <div className="flex items-center justify-between text-gray-500">
            <span className="text-[11px] font-medium uppercase tracking-wider">Terbit Resmi</span>
            <Send className="h-4 w-4 text-emerald-500" />
          </div>
          <div className="mt-2 text-xl font-bold text-emerald-700">{summary.terbit_count}</div>
          <div className="text-[11px] text-emerald-600 mt-0.5">Tampil ke Ortu/Siswa</div>
        </div>
      </div>

      {/* Roster & Actions Section */}
      <div className="rounded-xl border border-gray-200 bg-white shadow-xs overflow-hidden">
        {/* Action Header Bar */}
        <div className="border-b border-gray-200 bg-gray-50/50 p-4 space-y-3">
          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            {/* Search Input */}
            <div className="relative w-full sm:w-72">
              <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-gray-400" />
              <Input
                type="text"
                placeholder="Cari nama atau NIS siswa..."
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className="pl-9 text-xs h-9 bg-white"
              />
            </div>

            {/* Mass Action Buttons */}
            <div className="flex flex-wrap items-center gap-2">
              <Button
                variant="default"
                size="sm"
                onClick={handleGenerateClassReports}
                disabled={isProcessingMassAction || summary.total_students === 0}
                className="gap-1.5 text-xs bg-blue-600 hover:bg-blue-700"
              >
                {isProcessingMassAction ? (
                  <Loader2 className="h-3.5 w-3.5 animate-spin" />
                ) : (
                  <FileCheck2 className="h-3.5 w-3.5" />
                )}
                Generate Massal
              </Button>

              <Button
                variant="outline"
                size="sm"
                onClick={() => handleBulkLock(true)}
                disabled={isProcessingMassAction || summary.generated_count === 0}
                className="gap-1.5 text-xs text-amber-700 border-amber-300 hover:bg-amber-50"
              >
                <Lock className="h-3.5 w-3.5" />
                Kunci Massal
              </Button>

              <Button
                variant="outline"
                size="sm"
                onClick={() => handleBulkLock(false)}
                disabled={isProcessingMassAction || summary.terkunci_count === 0}
                className="gap-1.5 text-xs text-amber-700 border-amber-300 hover:bg-amber-50"
              >
                <Unlock className="h-3.5 w-3.5" />
                Buka Kunci
              </Button>

              <Button
                variant="outline"
                size="sm"
                onClick={() => handleBulkApprove('disetujui')}
                disabled={isProcessingMassAction || summary.generated_count === 0}
                className="gap-1.5 text-xs text-blue-700 border-blue-300 hover:bg-blue-50"
              >
                <Check className="h-3.5 w-3.5" />
                Sahkan Massal
              </Button>

              <Button
                variant="outline"
                size="sm"
                onClick={() => handleBulkApprove('terbit')}
                disabled={isProcessingMassAction || summary.disetujui_count === 0}
                className="gap-1.5 text-xs text-emerald-700 border-emerald-300 hover:bg-emerald-50"
              >
                <Send className="h-3.5 w-3.5" />
                Terbitkan Massal
              </Button>
            </div>
          </div>

          {/* Filter Status Tabs */}
          <div className="flex items-center gap-1 overflow-x-auto pt-1">
            {[
              { id: 'all', label: 'Semua Siswa', count: summary.total_students },
              { id: 'belum_dibuat', label: 'Belum Dibuat', count: summary.belum_dibuat_count },
              { id: 'draft', label: 'Draf', count: summary.draft_count },
              { id: 'terkunci', label: 'Terkunci', count: summary.terkunci_count },
              { id: 'disetujui', label: 'Disahkan Kepsek', count: summary.disetujui_count },
              { id: 'terbit', label: 'Terbit Resmi', count: summary.terbit_count },
            ].map((tab) => (
              <button
                key={tab.id}
                onClick={() => setActiveTab(tab.id as any)}
                className={`flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-xs font-medium transition whitespace-nowrap ${
                  activeTab === tab.id
                    ? 'bg-blue-600 text-white shadow-xs'
                    : 'text-gray-600 hover:bg-gray-100'
                }`}
              >
                {tab.label}
                <span
                  className={`rounded-full px-1.5 py-0.2 text-[10px] ${
                    activeTab === tab.id ? 'bg-blue-700 text-white' : 'bg-gray-200 text-gray-700'
                  }`}
                >
                  {tab.count}
                </span>
              </button>
            ))}
          </div>
        </div>

        {/* Table of Students */}
        <div className="overflow-x-auto">
          {isLoadingStatus ? (
            <div className="flex flex-col items-center justify-center p-12 text-gray-500 text-xs gap-3">
              <Loader2 className="h-6 w-6 animate-spin text-blue-600" />
              <span>Memuat kesiapan data e-rapor semester rombel...</span>
            </div>
          ) : filteredStudents.length === 0 ? (
            <div className="p-12 text-center text-xs text-gray-400">
              Tidak ada data siswa yang cocok dengan kriteria filter saat ini.
            </div>
          ) : (
            <table className="w-full text-left text-xs border-collapse">
              <thead className="bg-gray-50 text-gray-600 font-semibold border-b border-gray-200">
                <tr>
                  <th className="px-3 py-3 w-10 text-center">No</th>
                  <th className="px-3 py-3 w-32">NIS / NISN</th>
                  <th className="px-3 py-3 w-48">Nama Siswa</th>
                  <th className="px-3 py-3">Kesiapan Data Semester</th>
                  <th className="px-3 py-3 w-32 text-center">Status Rapor</th>
                  <th className="px-3 py-3 w-40 text-center">Aksi</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-200">
                {filteredStudents.map((row, idx) => (
                  <tr key={row.student.id} className="hover:bg-gray-50/80 transition">
                    <td className="px-3 py-3 text-center text-gray-400 font-mono text-[11px]">
                      {idx + 1}
                    </td>
                    <td className="px-3 py-3 font-mono text-gray-600 text-[11px]">
                      {row.student.nis || '-'}
                      <span className="block text-[10px] text-gray-400">{row.student.nisn || '-'}</span>
                    </td>
                    <td className="px-3 py-3 font-semibold text-gray-900">
                      {row.student.full_name}
                      <span className="block text-[10px] text-gray-400 font-normal">
                        Jenis Kelamin: {row.student.gender === 'L' ? 'Laki-laki' : 'Perempuan'}
                      </span>
                    </td>
                    <td className="px-3 py-3">
                      <div className="flex flex-wrap items-center gap-1.5">
                        {/* Nilai Mapel */}
                        <span
                          className={`inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[10px] font-medium border ${
                            row.data_readiness.has_grades
                              ? 'bg-blue-50 text-blue-700 border-blue-200'
                              : 'bg-rose-50 text-rose-700 border-rose-200'
                          }`}
                        >
                          <BookOpen className="h-3 w-3" />
                          {row.data_readiness.has_grades
                            ? `${row.data_readiness.graded_subjects_count} Komponen Nilai`
                            : 'Belum Ada Nilai'}
                        </span>

                        {/* Presensi */}
                        <span
                          className={`inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[10px] font-medium border ${
                            row.data_readiness.has_attendance
                              ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                              : 'bg-gray-50 text-gray-600 border-gray-200'
                          }`}
                        >
                          <Calendar className="h-3 w-3" />
                          {row.data_readiness.attendance_days_count} Hari Presensi
                        </span>

                        {/* Catatan Karakter */}
                        <span
                          className={`inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[10px] font-medium border ${
                            row.data_readiness.has_character_note
                              ? 'bg-purple-50 text-purple-700 border-purple-200'
                              : 'bg-gray-50 text-gray-600 border-gray-200'
                          }`}
                        >
                          <Award className="h-3 w-3" />
                          {row.data_readiness.has_character_note ? 'Catatan Walas' : 'Tanpa Catatan'}
                        </span>

                        {/* Ekskul */}
                        {row.data_readiness.has_extracurricular && (
                          <span className="inline-flex items-center gap-1 rounded-md bg-amber-50 px-2 py-0.5 text-[10px] font-medium text-amber-700 border border-amber-200">
                            Ekskul Aktif
                          </span>
                        )}
                      </div>
                    </td>
                    <td className="px-3 py-3 text-center">
                      {row.report ? (
                        <span
                          className={`inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold border ${
                            REPORT_CARD_STATUS_COLORS[row.report.status] ||
                            'bg-gray-100 text-gray-700'
                          }`}
                        >
                          {REPORT_CARD_STATUS_LABELS[row.report.status] || row.report.status}
                        </span>
                      ) : (
                        <span className="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-500 border border-gray-200">
                          Belum Dibuat
                        </span>
                      )}
                    </td>
                    <td className="px-3 py-3 text-center">
                      <div className="flex items-center justify-center gap-1">
                        <Button
                          variant="outline"
                          size="sm"
                          onClick={() => handleOpenReport(row)}
                          className="h-7 px-2 text-[11px] gap-1"
                        >
                          <Eye className="h-3 w-3 text-gray-600" />
                          {row.report ? 'Buka Rapor' : 'Pratinjau'}
                        </Button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
      </div>

      {/* Modal E-Rapor A4 */}
      <ReportCardModal
        isOpen={isReportModalOpen}
        onClose={() => setIsReportModalOpen(false)}
        report={selectedReportForModal}
        previewSnapshot={previewSnapshotForModal}
        onStatusUpdated={fetchClassStatus}
        onGenerateFromPreview={() => {
          if (previewSnapshotForModal?.student?.id) {
            handleGenerateSingleFromPreview(previewSnapshotForModal.student.id);
          }
        }}
        canApprove={true}
        canLock={true}
      />

      {/* Dialog Template Deskripsi Mapel */}
      <ReportTemplateDialog
        isOpen={isTemplateDialogOpen}
        onClose={() => setIsTemplateDialogOpen(false)}
      />

      {/* Dialog Konfirmasi Aksi Massal E-Rapor */}
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
