'use client';

import React, { useEffect, useState } from 'react';
import Link from 'next/link';
import {
  ShieldAlert,
  Award,
  Plus,
  RefreshCw,
  Search,
  Filter,
  Trash2,
  AlertTriangle,
  CheckCircle2,
  Users,
  Flame,
  ArrowLeft,
  X,
  FileText,
  Eye,
  Info,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { pointsApi } from '@/features/student-affairs/points-api';
import { studentsApi } from '@/features/master/students-api';
import { classesApi } from '@/features/master/classes-api';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { toast } from '@/components/ui/toast';
import {
  PointType,
  PointThreshold,
  StudentPointItem,
  StudentPointsSummary,
} from '@/features/student-affairs/types';
import { StudentItem, SchoolClassItem } from '@/types/master';

export default function StudentPointsAdminPage() {
  const [activeTab, setActiveTab] = useState<'riwayat' | 'master_jenis' | 'ambang_sanksi'>('riwayat');

  // Data State
  const [points, setPoints] = useState<StudentPointItem[]>([]);
  const [pointTypes, setPointTypes] = useState<PointType[]>([]);
  const [thresholds, setThresholds] = useState<PointThreshold[]>([]);
  const [classes, setClasses] = useState<SchoolClassItem[]>([]);
  const [students, setStudents] = useState<StudentItem[]>([]);

  // Filter State
  const [searchQuery, setSearchQuery] = useState<string>('');
  const [selectedKind, setSelectedKind] = useState<'all' | 'pelanggaran' | 'prestasi'>('all');
  const [selectedClassId, setSelectedClassId] = useState<string>('all');
  const [isLoading, setIsLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);

  // Modals
  const [isRecordModalOpen, setIsRecordModalOpen] = useState<boolean>(false);
  const [isTypeModalOpen, setIsTypeModalOpen] = useState<boolean>(false);
  const [isThresholdModalOpen, setIsThresholdModalOpen] = useState<boolean>(false);
  const [summaryModalStudentId, setSummaryModalStudentId] = useState<string | null>(null);
  const [studentSummary, setStudentSummary] = useState<StudentPointsSummary | null>(null);
  const [isSummaryLoading, setIsSummaryLoading] = useState<boolean>(false);
  const [deleteConfirm, setDeleteConfirm] = useState<{
    isOpen: boolean;
    title: string;
    description: string;
    onConfirm: () => Promise<void>;
    isLoading?: boolean;
  }>({
    isOpen: false,
    title: '',
    description: '',
    onConfirm: async () => {},
    isLoading: false,
  });

  // Form State: Catat Poin
  const [recordStudentId, setRecordStudentId] = useState<string>('');
  const [recordPointTypeId, setRecordPointTypeId] = useState<string>('');
  const [recordDate, setRecordDate] = useState<string>(new Date().toISOString().split('T')[0]);
  const [recordNotes, setRecordNotes] = useState<string>('');
  const [isSubmittingRecord, setIsSubmittingRecord] = useState<boolean>(false);
  const [thresholdTriggeredAlert, setThresholdTriggeredAlert] = useState<{
    action: string;
    points: number;
  } | null>(null);

  // Form State: Tambah Jenis Poin
  const [typeKind, setTypeKind] = useState<'pelanggaran' | 'prestasi'>('pelanggaran');
  const [typeName, setTypeName] = useState<string>('');
  const [typePoints, setTypePoints] = useState<number>(5);
  const [typeSeverity, setTypeSeverity] = useState<'rendah' | 'sedang' | 'tinggi' | 'berat'>('rendah');
  const [isSubmittingType, setIsSubmittingType] = useState<boolean>(false);

  // Form State: Tambah Ambang Sanksi
  const [thresholdMinPoints, setThresholdMinPoints] = useState<number>(25);
  const [thresholdAction, setThresholdAction] = useState<string>('');
  const [thresholdRoles, setThresholdRoles] = useState<string[]>(['homeroom_teacher', 'counselor']);
  const [isSubmittingThreshold, setIsSubmittingThreshold] = useState<boolean>(false);

  const fetchData = async () => {
    setIsLoading(true);
    setError(null);
    try {
      const [pointsRes, typesRes, thresholdsRes, classesRes, studentsRes] = await Promise.all([
        pointsApi.getStudentPoints({
          kind: selectedKind === 'all' ? undefined : selectedKind,
          class_id: selectedClassId === 'all' ? undefined : selectedClassId,
          search: searchQuery || undefined,
          per_page: 50,
        }),
        pointsApi.getPointTypes({ per_page: 100 }),
        pointsApi.getPointThresholds(),
        classesApi.list({ limit: 100 }).catch(() => ({ data: [] })),
        studentsApi.list({ limit: 100 }).catch(() => ({ data: [] })),
      ]);

      setPoints(pointsRes.data || []);
      setPointTypes(typesRes.data || []);
      setThresholds(thresholdsRes.data || []);
      setClasses(classesRes.data || []);
      setStudents(studentsRes.data || []);
    } catch (err: any) {
      setError(err?.message || 'Gagal memuat data kesiswaan.');
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    fetchData();
  }, [selectedKind, selectedClassId]);

  const handleSearchSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    fetchData();
  };

  const handleRecordSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!recordStudentId || !recordPointTypeId) {
      setError('Siswa dan jenis poin wajib dipilih.');
      return;
    }

    setIsSubmittingRecord(true);
    setError(null);
    try {
      const res = await pointsApi.recordStudentPoint({
        student_id: recordStudentId,
        point_type_id: recordPointTypeId,
        point_date: recordDate,
        notes: recordNotes || undefined,
      });

      setSuccessMessage('Catatan poin siswa berhasil disimpan!');
      if (res.data?.triggered_threshold) {
        setThresholdTriggeredAlert({
          action: res.data.triggered_threshold.action,
          points: res.data.cumulative_violation_points,
        });
      } else {
        setIsRecordModalOpen(false);
        setRecordNotes('');
      }

      fetchData();
    } catch (err: any) {
      setError(err?.message || 'Gagal menyimpan catatan poin siswa.');
    } finally {
      setIsSubmittingRecord(false);
    }
  };

  const handleCreateTypeSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!typeName.trim() || typePoints <= 0) {
      setError('Nama dan bobot poin harus valid.');
      return;
    }

    setIsSubmittingType(true);
    setError(null);
    try {
      await pointsApi.createPointType({
        kind: typeKind,
        name: typeName,
        points: typePoints,
        severity: typeSeverity,
      });

      setSuccessMessage('Jenis poin berhasil ditambahkan!');
      setIsTypeModalOpen(false);
      setTypeName('');
      setTypePoints(5);
      fetchData();
    } catch (err: any) {
      setError(err?.message || 'Gagal menambahkan jenis poin baru.');
    } finally {
      setIsSubmittingType(false);
    }
  };

  const handleDeleteType = (id: string) => {
    setDeleteConfirm({
      isOpen: true,
      title: 'Hapus Jenis Poin',
      description: 'Apakah Anda yakin ingin menghapus jenis poin ini? Riwayat poin siswa terdahulu tidak akan terpengaruh.',
      onConfirm: async () => {
        try {
          await pointsApi.deletePointType(id);
          setSuccessMessage('Jenis poin berhasil dihapus.');
          toast.success('Jenis poin berhasil dihapus.');
          fetchData();
        } catch (err: any) {
          const errMsg = err?.message || 'Gagal menghapus jenis poin.';
          setError(errMsg);
          toast.error(errMsg);
        }
      },
    });
  };

  const handleCreateThresholdSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (thresholdMinPoints <= 0 || !thresholdAction.trim() || thresholdRoles.length === 0) {
      setError('Ambang poin, tindakan, dan peran penerima notifikasi wajib diisi.');
      return;
    }

    setIsSubmittingThreshold(true);
    setError(null);
    try {
      await pointsApi.createPointThreshold({
        min_points: thresholdMinPoints,
        action: thresholdAction,
        notify_roles: thresholdRoles,
      });

      setSuccessMessage('Ambang batas sanksi berhasil dikonfigurasi!');
      setIsThresholdModalOpen(false);
      setThresholdAction('');
      fetchData();
    } catch (err: any) {
      setError(err?.message || 'Gagal menyimpan ambang batas sanksi.');
    } finally {
      setIsSubmittingThreshold(false);
    }
  };

  const handleDeleteThreshold = (id: string) => {
    setDeleteConfirm({
      isOpen: true,
      title: 'Hapus Aturan Ambang Sanksi',
      description: 'Apakah Anda yakin ingin menghapus aturan ambang batas sanksi ini?',
      onConfirm: async () => {
        try {
          await pointsApi.deletePointThreshold(id);
          setSuccessMessage('Ambang batas sanksi berhasil dihapus.');
          toast.success('Ambang batas sanksi berhasil dihapus.');
          fetchData();
        } catch (err: any) {
          const errMsg = err?.message || 'Gagal menghapus ambang sanksi.';
          setError(errMsg);
          toast.error(errMsg);
        }
      },
    });
  };

  const handleDeletePointRecord = (id: string) => {
    setDeleteConfirm({
      isOpen: true,
      title: 'Batalkan Catatan Poin Siswa',
      description: 'Apakah Anda yakin ingin membatalkan atau menghapus catatan poin siswa ini?',
      onConfirm: async () => {
        try {
          await pointsApi.deleteStudentPoint(id);
          setSuccessMessage('Catatan poin siswa berhasil dibatalkan.');
          toast.success('Catatan poin siswa berhasil dibatalkan.');
          fetchData();
        } catch (err: any) {
          const errMsg = err?.message || 'Gagal menghapus catatan poin.';
          setError(errMsg);
          toast.error(errMsg);
        }
      },
    });
  };

  const handleOpenStudentSummary = async (studentId: string) => {
    setSummaryModalStudentId(studentId);
    setIsSummaryLoading(true);
    try {
      const res = await pointsApi.getStudentPointsSummary(studentId);
      setStudentSummary(res.data);
    } catch (err: any) {
      setError(err?.message || 'Gagal memuat ringkasan poin siswa.');
    } finally {
      setIsSummaryLoading(false);
    }
  };

  const getSeverityBadgeClass = (severity?: string) => {
    switch (severity) {
      case 'berat':
        return 'bg-rose-100 text-rose-800 border-rose-200';
      case 'tinggi':
        return 'bg-amber-100 text-amber-800 border-amber-200';
      case 'sedang':
        return 'bg-yellow-100 text-yellow-800 border-yellow-200';
      default:
        return 'bg-slate-100 text-slate-700 border-slate-200';
    }
  };

  return (
    <div className="space-y-6">
      {/* Top Header Navigation */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <div className="flex items-center gap-2 mb-1">
            <Link
              href="/admin/student-affairs"
              className="text-xs text-slate-500 hover:text-slate-800 flex items-center gap-1 transition-colors"
            >
              <ArrowLeft className="h-3.5 w-3.5" />
              Portal Kesiswaan
            </Link>
            <span className="text-slate-300">•</span>
            <Badge className="bg-amber-100 text-amber-800 border-amber-200 text-[10px]">
              ADM-STD-01
            </Badge>
          </div>
          <h1 className="text-xl font-bold tracking-tight text-slate-900">
            Poin Kedisiplinan & Prestasi Siswa 🛡️
          </h1>
          <p className="text-xs text-slate-500 mt-0.5">
            Pencatatan pelanggaran, apresiasi prestasi, akumulasi poin per rombel, serta otomasi tindak lanjut sanksi.
          </p>
        </div>

        <div className="flex items-center gap-2.5">
          <Button
            variant="outline"
            size="sm"
            onClick={fetchData}
            disabled={isLoading}
            className="text-xs border-slate-300"
          >
            <RefreshCw className={`h-3.5 w-3.5 mr-1.5 ${isLoading ? 'animate-spin' : ''}`} />
            Segarkan
          </Button>

          <Button
            size="sm"
            onClick={() => {
              setThresholdTriggeredAlert(null);
              setIsRecordModalOpen(true);
            }}
            className="bg-amber-600 hover:bg-amber-500 text-white text-xs gap-1.5 shadow-sm"
          >
            <Plus className="h-4 w-4" />
            Catat Poin Cepat
          </Button>
        </div>
      </div>

      {/* Global Alerts */}
      {successMessage && (
        <div className="rounded-lg bg-emerald-50 border border-emerald-200 p-3 text-xs text-emerald-800 flex items-center justify-between">
          <div className="flex items-center gap-2">
            <CheckCircle2 className="h-4 w-4 text-emerald-600 shrink-0" />
            <span>{successMessage}</span>
          </div>
          <button onClick={() => setSuccessMessage(null)} className="text-emerald-600 hover:text-emerald-900">
            <X className="h-4 w-4" />
          </button>
        </div>
      )}

      {error && (
        <div className="rounded-lg bg-rose-50 border border-rose-200 p-3 text-xs text-rose-800 flex items-center justify-between">
          <div className="flex items-center gap-2">
            <AlertTriangle className="h-4 w-4 text-rose-600 shrink-0" />
            <span>{error}</span>
          </div>
          <button onClick={() => setError(null)} className="text-rose-600 hover:text-rose-900">
            <X className="h-4 w-4" />
          </button>
        </div>
      )}

      {/* Navigation Tabs */}
      <div className="flex border-b border-slate-200 gap-6 text-xs font-medium">
        <button
          onClick={() => setActiveTab('riwayat')}
          className={`pb-2.5 transition-colors border-b-2 flex items-center gap-2 ${
            activeTab === 'riwayat'
              ? 'border-amber-600 text-amber-700 font-bold'
              : 'border-transparent text-slate-500 hover:text-slate-800'
          }`}
        >
          <FileText className="h-4 w-4" />
          Riwayat & Catatan Poin ({points.length})
        </button>

        <button
          onClick={() => setActiveTab('master_jenis')}
          className={`pb-2.5 transition-colors border-b-2 flex items-center gap-2 ${
            activeTab === 'master_jenis'
              ? 'border-amber-600 text-amber-700 font-bold'
              : 'border-transparent text-slate-500 hover:text-slate-800'
          }`}
        >
          <Award className="h-4 w-4" />
          Master Jenis Poin ({pointTypes.length})
        </button>

        <button
          onClick={() => setActiveTab('ambang_sanksi')}
          className={`pb-2.5 transition-colors border-b-2 flex items-center gap-2 ${
            activeTab === 'ambang_sanksi'
              ? 'border-amber-600 text-amber-700 font-bold'
              : 'border-transparent text-slate-500 hover:text-slate-800'
          }`}
        >
          <Flame className="h-4 w-4" />
          Ambang Batas Sanksi ({thresholds.length})
        </button>
      </div>

      {/* TAB 1: RIWAYAT & CATATAN POIN */}
      {activeTab === 'riwayat' && (
        <div className="space-y-4">
          {/* Controls: Search, Filter Kind, Filter Class */}
          <div className="bg-white p-3.5 rounded-lg border border-slate-200 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-3">
            <form onSubmit={handleSearchSubmit} className="flex items-center gap-2 flex-1 max-w-md">
              <div className="relative flex-1">
                <Search className="h-3.5 w-3.5 text-slate-400 absolute left-2.5 top-2.5" />
                <Input
                  type="text"
                  placeholder="Cari nama siswa atau NIS..."
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  className="pl-8 text-xs h-8 border-slate-300"
                />
              </div>
              <Button type="submit" size="sm" variant="outline" className="h-8 text-xs border-slate-300">
                Cari
              </Button>
            </form>

            <div className="flex flex-wrap items-center gap-2.5">
              <div className="flex items-center gap-1.5">
                <span className="text-[11px] text-slate-500 font-medium">Kategori:</span>
                <select
                  value={selectedKind}
                  onChange={(e) => setSelectedKind(e.target.value as any)}
                  className="h-8 text-xs border border-slate-300 rounded-md px-2 bg-white text-slate-800"
                >
                  <option value="all">Semua Kategori</option>
                  <option value="pelanggaran">Pelanggaran Saja</option>
                  <option value="prestasi">Prestasi Saja</option>
                </select>
              </div>

              <div className="flex items-center gap-1.5">
                <span className="text-[11px] text-slate-500 font-medium">Rombel:</span>
                <select
                  value={selectedClassId}
                  onChange={(e) => setSelectedClassId(e.target.value)}
                  className="h-8 text-xs border border-slate-300 rounded-md px-2 bg-white text-slate-800 max-w-[150px] truncate"
                >
                  <option value="all">Semua Kelas</option>
                  {classes.map((cls) => (
                    <option key={cls.id} value={cls.id}>
                      {cls.name}
                    </option>
                  ))}
                </select>
              </div>
            </div>
          </div>

          {/* Table Riwayat Poin */}
          <Card className="border-slate-200 shadow-sm">
            <CardContent className="p-0">
              {isLoading ? (
                <div className="text-center py-12 text-xs text-slate-500">
                  <RefreshCw className="h-6 w-6 animate-spin mx-auto mb-2 text-slate-400" />
                  Memuat data riwayat poin siswa...
                </div>
              ) : points.length === 0 ? (
                <div className="text-center py-12 text-xs text-slate-500">
                  <ShieldAlert className="h-8 w-8 text-slate-300 mx-auto mb-2" />
                  Tidak ditemukan catatan poin yang sesuai dengan kriteria filter.
                </div>
              ) : (
                <div className="overflow-x-auto">
                  <table className="w-full text-xs text-left">
                    <thead className="bg-slate-50 text-slate-600 font-semibold border-b border-slate-200">
                      <tr>
                        <th className="py-2.5 px-3">Tanggal</th>
                        <th className="py-2.5 px-3">Siswa & Rombel</th>
                        <th className="py-2.5 px-3">Kategori</th>
                        <th className="py-2.5 px-3">Jenis Poin</th>
                        <th className="py-2.5 px-3">Bobot</th>
                        <th className="py-2.5 px-3">Keparahan</th>
                        <th className="py-2.5 px-3">Dicatat Oleh</th>
                        <th className="py-2.5 px-3">Catatan</th>
                        <th className="py-2.5 px-3 text-right">Aksi</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                      {points.map((p) => {
                        const isViolation = p.point_type?.kind === 'pelanggaran';
                        return (
                          <tr key={p.id} className="hover:bg-slate-50/70 transition-colors">
                            <td className="py-2.5 px-3 font-mono text-slate-600 whitespace-nowrap">
                              {p.point_date}
                            </td>
                            <td className="py-2.5 px-3 font-medium text-slate-900 whitespace-nowrap">
                              <div className="flex items-center gap-1.5">
                                <span>{p.student?.full_name || 'Siswa'}</span>
                                <button
                                  onClick={() => handleOpenStudentSummary(p.student_id)}
                                  title="Lihat ringkasan akumulasi poin siswa"
                                  className="text-slate-400 hover:text-amber-600 p-0.5 rounded"
                                >
                                  <Eye className="h-3 w-3" />
                                </button>
                              </div>
                              <span className="block text-[11px] text-slate-400 font-mono">
                                NIS: {p.student?.nis || '-'} •{' '}
                                {p.student?.classes?.[0]?.name || 'Kelas -'}
                              </span>
                            </td>
                            <td className="py-2.5 px-3">
                              <Badge
                                className={
                                  isViolation
                                    ? 'bg-rose-100 text-rose-700 border-rose-200'
                                    : 'bg-emerald-100 text-emerald-700 border-emerald-200'
                                }
                              >
                                {isViolation ? 'Pelanggaran' : 'Prestasi'}
                              </Badge>
                            </td>
                            <td className="py-2.5 px-3 text-slate-800 font-medium">
                              {p.point_type?.name || '-'}
                            </td>
                            <td className="py-2.5 px-3 font-bold">
                              <span className={isViolation ? 'text-rose-600' : 'text-emerald-600'}>
                                {isViolation ? `-${p.point_type?.points}` : `+${p.point_type?.points}`}
                              </span>
                            </td>
                            <td className="py-2.5 px-3">
                              <Badge className={getSeverityBadgeClass(p.point_type?.severity)}>
                                {p.point_type?.severity || 'rendah'}
                              </Badge>
                            </td>
                            <td className="py-2.5 px-3 text-slate-600 whitespace-nowrap">
                              {p.recorder?.name || 'Staf'}
                            </td>
                            <td className="py-2.5 px-3 text-slate-500 max-w-xs truncate">
                              {p.notes || '-'}
                            </td>
                            <td className="py-2.5 px-3 text-right">
                              <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => handleDeletePointRecord(p.id)}
                                title="Batalkan catatan poin"
                                className="h-7 w-7 p-0 text-slate-400 hover:text-rose-600 hover:bg-rose-50"
                              >
                                <Trash2 className="h-3.5 w-3.5" />
                              </Button>
                            </td>
                          </tr>
                        );
                      })}
                    </tbody>
                  </table>
                </div>
              )}
            </CardContent>
          </Card>
        </div>
      )}

      {/* TAB 2: MASTER JENIS POIN */}
      {activeTab === 'master_jenis' && (
        <div className="space-y-4">
          <div className="flex justify-between items-center">
            <div>
              <h2 className="text-sm font-bold text-slate-900">Daftar Master Jenis Poin</h2>
              <p className="text-xs text-slate-500">
                Konfigurasi aturan jenis pelanggaran dan penghargaan prestasi siswa.
              </p>
            </div>
            <Button
              size="sm"
              onClick={() => setIsTypeModalOpen(true)}
              className="bg-slate-900 hover:bg-slate-800 text-white text-xs gap-1.5"
            >
              <Plus className="h-3.5 w-3.5" />
              Tambah Jenis Poin
            </Button>
          </div>

          <Card className="border-slate-200 shadow-sm">
            <CardContent className="p-0">
              <div className="overflow-x-auto">
                <table className="w-full text-xs text-left">
                  <thead className="bg-slate-50 text-slate-600 font-semibold border-b border-slate-200">
                    <tr>
                      <th className="py-2.5 px-3">Nama Jenis Poin</th>
                      <th className="py-2.5 px-3">Kategori</th>
                      <th className="py-2.5 px-3">Bobot Poin</th>
                      <th className="py-2.5 px-3">Tingkat Keparahan</th>
                      <th className="py-2.5 px-3 text-right">Aksi</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100">
                    {pointTypes.map((pt) => {
                      const isViolation = pt.kind === 'pelanggaran';
                      return (
                        <tr key={pt.id} className="hover:bg-slate-50/70 transition-colors">
                          <td className="py-2.5 px-3 font-medium text-slate-900">{pt.name}</td>
                          <td className="py-2.5 px-3">
                            <Badge
                              className={
                                isViolation
                                  ? 'bg-rose-100 text-rose-700 border-rose-200'
                                  : 'bg-emerald-100 text-emerald-700 border-emerald-200'
                              }
                            >
                              {isViolation ? 'Pelanggaran' : 'Prestasi'}
                            </Badge>
                          </td>
                          <td className="py-2.5 px-3 font-bold">
                            <span className={isViolation ? 'text-rose-600' : 'text-emerald-600'}>
                              {isViolation ? `-${pt.points}` : `+${pt.points}`}
                            </span>
                          </td>
                          <td className="py-2.5 px-3">
                            <Badge className={getSeverityBadgeClass(pt.severity)}>
                              {pt.severity || 'rendah'}
                            </Badge>
                          </td>
                          <td className="py-2.5 px-3 text-right">
                            <Button
                              variant="ghost"
                              size="sm"
                              onClick={() => handleDeleteType(pt.id)}
                              className="h-7 w-7 p-0 text-slate-400 hover:text-rose-600 hover:bg-rose-50"
                            >
                              <Trash2 className="h-3.5 w-3.5" />
                            </Button>
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            </CardContent>
          </Card>
        </div>
      )}

      {/* TAB 3: AMBANG BATAS SANKSI */}
      {activeTab === 'ambang_sanksi' && (
        <div className="space-y-4">
          <div className="flex justify-between items-center">
            <div>
              <h2 className="text-sm font-bold text-slate-900">Aturan Ambang Sanksi Bertingkat</h2>
              <p className="text-xs text-slate-500">
                Ambang akumulasi poin pelanggaran yang otomatis memicu tindak lanjut & notifikasi peran.
              </p>
            </div>
            <Button
              size="sm"
              onClick={() => setIsThresholdModalOpen(true)}
              className="bg-amber-600 hover:bg-amber-500 text-white text-xs gap-1.5"
            >
              <Plus className="h-3.5 w-3.5" />
              Tambah Ambang Sanksi
            </Button>
          </div>

          <Card className="border-slate-200 shadow-sm">
            <CardContent className="p-0">
              <div className="overflow-x-auto">
                <table className="w-full text-xs text-left">
                  <thead className="bg-slate-50 text-slate-600 font-semibold border-b border-slate-200">
                    <tr>
                      <th className="py-2.5 px-3">Batas Minimum Poin</th>
                      <th className="py-2.5 px-3">Tindakan / Sanksi Sekolah</th>
                      <th className="py-2.5 px-3">Peran Yang Diberitahu</th>
                      <th className="py-2.5 px-3 text-right">Aksi</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100">
                    {thresholds.map((th) => (
                      <tr key={th.id} className="hover:bg-slate-50/70 transition-colors">
                        <td className="py-2.5 px-3 font-bold text-amber-700">
                          {th.min_points} Poin Pelanggaran
                        </td>
                        <td className="py-2.5 px-3 font-medium text-slate-900">{th.action}</td>
                        <td className="py-2.5 px-3">
                          <div className="flex flex-wrap gap-1">
                            {th.notify_roles.map((r, i) => (
                              <Badge key={i} variant="outline" className="text-[10px] bg-slate-50 border-slate-300">
                                {r}
                              </Badge>
                            ))}
                          </div>
                        </td>
                        <td className="py-2.5 px-3 text-right">
                          <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => handleDeleteThreshold(th.id)}
                            className="h-7 w-7 p-0 text-slate-400 hover:text-rose-600 hover:bg-rose-50"
                          >
                            <Trash2 className="h-3.5 w-3.5" />
                          </Button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </CardContent>
          </Card>
        </div>
      )}

      {/* =========================================================================
          MODAL: CATAT POIN CEPAT SISWA
         ========================================================================= */}
      {isRecordModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
          <div className="bg-white rounded-xl shadow-xl w-full max-w-lg overflow-hidden border border-slate-200">
            <div className="flex items-center justify-between px-5 py-3.5 border-b bg-slate-50">
              <div className="flex items-center gap-2">
                <ShieldAlert className="h-5 w-5 text-amber-600" />
                <h3 className="text-sm font-bold text-slate-900">Catat Poin Siswa (Input Cepat)</h3>
              </div>
              <button
                onClick={() => setIsRecordModalOpen(false)}
                className="text-slate-400 hover:text-slate-700 p-1 rounded"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            {thresholdTriggeredAlert ? (
              <div className="p-6 text-center space-y-4">
                <div className="h-12 w-12 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center mx-auto">
                  <Flame className="h-6 w-6" />
                </div>
                <div>
                  <h4 className="text-base font-bold text-slate-900">Ambang Batas Sanksi Terlampaui!</h4>
                  <p className="text-xs text-slate-600 mt-1">
                    Siswa telah mencapai total <strong>{thresholdTriggeredAlert.points} poin pelanggaran</strong>.
                  </p>
                  <div className="mt-3 p-3 bg-amber-50 border border-amber-200 rounded-lg text-amber-900 text-xs font-semibold">
                    Tindakan Tindak Lanjut: {thresholdTriggeredAlert.action}
                  </div>
                  <p className="text-[11px] text-slate-400 mt-2">
                    Notifikasi otomatis telah diteruskan ke wali kelas dan guru BK.
                  </p>
                </div>
                <Button
                  onClick={() => {
                    setIsRecordModalOpen(false);
                    setThresholdTriggeredAlert(null);
                    setRecordNotes('');
                  }}
                  className="w-full bg-slate-900 hover:bg-slate-800 text-white text-xs"
                >
                  Tutup & Selesai
                </Button>
              </div>
            ) : (
              <form onSubmit={handleRecordSubmit} className="p-5 space-y-4">
                <div>
                  <label className="block text-xs font-semibold text-slate-700 mb-1">Pilih Siswa *</label>
                  <select
                    value={recordStudentId}
                    onChange={(e) => setRecordStudentId(e.target.value)}
                    required
                    className="w-full h-9 text-xs border border-slate-300 rounded-md px-2.5 bg-white text-slate-800 focus:outline-none focus:ring-1 focus:ring-amber-500"
                  >
                    <option value="">-- Pilih Siswa --</option>
                    {students.map((s) => (
                      <option key={s.id} value={s.id}>
                        {s.full_name} ({s.nis ? `NIS: ${s.nis}` : 'No NIS'})
                      </option>
                    ))}
                  </select>
                </div>

                <div>
                  <label className="block text-xs font-semibold text-slate-700 mb-1">Jenis Poin / Pelanggaran *</label>
                  <select
                    value={recordPointTypeId}
                    onChange={(e) => setRecordPointTypeId(e.target.value)}
                    required
                    className="w-full h-9 text-xs border border-slate-300 rounded-md px-2.5 bg-white text-slate-800 focus:outline-none focus:ring-1 focus:ring-amber-500"
                  >
                    <option value="">-- Pilih Jenis Poin --</option>
                    {pointTypes.map((pt) => (
                      <option key={pt.id} value={pt.id}>
                        [{pt.kind === 'pelanggaran' ? 'PELANGGARAN' : 'PRESTASI'}] {pt.name} ({pt.points} Poin - {pt.severity})
                      </option>
                    ))}
                  </select>
                </div>

                <div>
                  <label className="block text-xs font-semibold text-slate-700 mb-1">Tanggal Kejadian *</label>
                  <Input
                    type="date"
                    value={recordDate}
                    onChange={(e) => setRecordDate(e.target.value)}
                    required
                    className="text-xs h-9 border-slate-300"
                  />
                </div>

                <div>
                  <label className="block text-xs font-semibold text-slate-700 mb-1">Catatan Tambahan / Kronologi</label>
                  <Textarea
                    placeholder="Tuliskan keterangan detail kejadian atau bukti fisik..."
                    value={recordNotes}
                    onChange={(e) => setRecordNotes(e.target.value)}
                    rows={3}
                    className="text-xs border-slate-300"
                  />
                </div>

                <div className="flex justify-end gap-2 pt-2 border-t">
                  <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => setIsRecordModalOpen(false)}
                    className="text-xs"
                  >
                    Batal
                  </Button>
                  <Button
                    type="submit"
                    size="sm"
                    disabled={isSubmittingRecord}
                    className="bg-amber-600 hover:bg-amber-500 text-white text-xs gap-1.5"
                  >
                    {isSubmittingRecord ? 'Menyimpan...' : 'Simpan Catatan Poin'}
                  </Button>
                </div>
              </form>
            )}
          </div>
        </div>
      )}

      {/* =========================================================================
          MODAL: TAMBAH JENIS POIN
         ========================================================================= */}
      {isTypeModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
          <div className="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden border border-slate-200">
            <div className="flex items-center justify-between px-5 py-3.5 border-b bg-slate-50">
              <h3 className="text-sm font-bold text-slate-900">Tambah Master Jenis Poin</h3>
              <button onClick={() => setIsTypeModalOpen(false)} className="text-slate-400 hover:text-slate-700 p-1">
                <X className="h-4 w-4" />
              </button>
            </div>

            <form onSubmit={handleCreateTypeSubmit} className="p-5 space-y-4">
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">Kategori *</label>
                <div className="grid grid-cols-2 gap-2">
                  <button
                    type="button"
                    onClick={() => setTypeKind('pelanggaran')}
                    className={`p-2 text-xs rounded-md border font-medium ${
                      typeKind === 'pelanggaran'
                        ? 'bg-rose-50 border-rose-300 text-rose-700'
                        : 'border-slate-200 text-slate-600'
                    }`}
                  >
                    Pelanggaran (Minus)
                  </button>
                  <button
                    type="button"
                    onClick={() => setTypeKind('prestasi')}
                    className={`p-2 text-xs rounded-md border font-medium ${
                      typeKind === 'prestasi'
                        ? 'bg-emerald-50 border-emerald-300 text-emerald-700'
                        : 'border-slate-200 text-slate-600'
                    }`}
                  >
                    Prestasi (Plus)
                  </button>
                </div>
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">Nama Jenis Poin *</label>
                <Input
                  type="text"
                  placeholder="Contoh: Terlambat Masuk Sekolah"
                  value={typeName}
                  onChange={(e) => setTypeName(e.target.value)}
                  required
                  className="text-xs h-9"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-semibold text-slate-700 mb-1">Bobot Poin *</label>
                  <Input
                    type="number"
                    min={1}
                    value={typePoints}
                    onChange={(e) => setTypePoints(parseInt(e.target.value) || 1)}
                    required
                    className="text-xs h-9"
                  />
                </div>

                <div>
                  <label className="block text-xs font-semibold text-slate-700 mb-1">Tingkat Keparahan</label>
                  <select
                    value={typeSeverity}
                    onChange={(e) => setTypeSeverity(e.target.value as any)}
                    className="w-full h-9 text-xs border border-slate-300 rounded-md px-2 bg-white text-slate-800"
                  >
                    <option value="rendah">Rendah</option>
                    <option value="sedang">Sedang</option>
                    <option value="tinggi">Tinggi</option>
                    <option value="berat">Berat</option>
                  </select>
                </div>
              </div>

              <div className="flex justify-end gap-2 pt-2 border-t">
                <Button type="button" variant="outline" size="sm" onClick={() => setIsTypeModalOpen(false)}>
                  Batal
                </Button>
                <Button type="submit" size="sm" disabled={isSubmittingType} className="bg-slate-900 text-white">
                  {isSubmittingType ? 'Menyimpan...' : 'Simpan Jenis Poin'}
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* =========================================================================
          MODAL: TAMBAH AMBANG SANKSI
         ========================================================================= */}
      {isThresholdModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
          <div className="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden border border-slate-200">
            <div className="flex items-center justify-between px-5 py-3.5 border-b bg-slate-50">
              <h3 className="text-sm font-bold text-slate-900">Tambah Ambang Batas Sanksi</h3>
              <button onClick={() => setIsThresholdModalOpen(false)} className="text-slate-400 hover:text-slate-700 p-1">
                <X className="h-4 w-4" />
              </button>
            </div>

            <form onSubmit={handleCreateThresholdSubmit} className="p-5 space-y-4">
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">Batas Minimum Poin Pelanggaran *</label>
                <Input
                  type="number"
                  min={1}
                  value={thresholdMinPoints}
                  onChange={(e) => setThresholdMinPoints(parseInt(e.target.value) || 1)}
                  required
                  className="text-xs h-9"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">Tindakan / Sanksi Tindak Lanjut *</label>
                <Input
                  type="text"
                  placeholder="Contoh: Peringatan Tertulis 1 (SP1) & Pemanggilan Ortu"
                  value={thresholdAction}
                  onChange={(e) => setThresholdAction(e.target.value)}
                  required
                  className="text-xs h-9"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">Peran Penerima Notifikasi *</label>
                <div className="space-y-1.5 pt-1">
                  {[
                    { code: 'homeroom_teacher', label: 'Wali Kelas' },
                    { code: 'counselor', label: 'Guru Bimbingan Konseling (BK)' },
                    { code: 'kesiswaan', label: 'Staf Kesiswaan' },
                    { code: 'admin', label: 'Kepala Sekolah / Admin' },
                  ].map((role) => (
                    <label key={role.code} className="flex items-center gap-2 text-xs text-slate-700">
                      <input
                        type="checkbox"
                        checked={thresholdRoles.includes(role.code)}
                        onChange={(e) => {
                          if (e.target.checked) {
                            setThresholdRoles([...thresholdRoles, role.code]);
                          } else {
                            setThresholdRoles(thresholdRoles.filter((r) => r !== role.code));
                          }
                        }}
                        className="rounded border-slate-300 text-amber-600 focus:ring-amber-500"
                      />
                      <span>{role.label}</span>
                    </label>
                  ))}
                </div>
              </div>

              <div className="flex justify-end gap-2 pt-2 border-t">
                <Button type="button" variant="outline" size="sm" onClick={() => setIsThresholdModalOpen(false)}>
                  Batal
                </Button>
                <Button type="submit" size="sm" disabled={isSubmittingThreshold} className="bg-amber-600 text-white">
                  {isSubmittingThreshold ? 'Menyimpan...' : 'Simpan Ambang Sanksi'}
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* =========================================================================
          MODAL: LIHAT RINGKASAN AKUMULASI SISWA
         ========================================================================= */}
      {summaryModalStudentId && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
          <div className="bg-white rounded-xl shadow-xl w-full max-w-lg overflow-hidden border border-slate-200">
            <div className="flex items-center justify-between px-5 py-3.5 border-b bg-slate-50">
              <div className="flex items-center gap-2">
                <Info className="h-4 w-4 text-amber-600" />
                <h3 className="text-sm font-bold text-slate-900">Ringkasan Akumulasi Poin Siswa</h3>
              </div>
              <button
                onClick={() => {
                  setSummaryModalStudentId(null);
                  setStudentSummary(null);
                }}
                className="text-slate-400 hover:text-slate-700 p-1"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            <div className="p-5">
              {isSummaryLoading ? (
                <div className="text-center py-8 text-xs text-slate-500">
                  <RefreshCw className="h-5 w-5 animate-spin mx-auto mb-2 text-slate-400" />
                  Memuat data akumulasi poin siswa...
                </div>
              ) : studentSummary ? (
                <div className="space-y-4 text-xs">
                  <div className="p-3 bg-slate-50 rounded-lg border border-slate-200">
                    <div className="font-bold text-slate-900 text-sm">{studentSummary.student.full_name}</div>
                    <div className="text-[11px] text-slate-500 font-mono mt-0.5">
                      NIS: {studentSummary.student.nis || '-'} • NISN: {studentSummary.student.nisn || '-'}
                    </div>
                  </div>

                  <div className="grid grid-cols-3 gap-2.5 text-center">
                    <div className="p-2.5 bg-rose-50 border border-rose-200 rounded-lg">
                      <div className="text-[10px] text-rose-600 font-semibold uppercase">Poin Pelanggaran</div>
                      <div className="text-lg font-bold text-rose-700 mt-0.5">
                        {studentSummary.total_violation_points}
                      </div>
                      <div className="text-[10px] text-rose-500">{studentSummary.violation_count} kejadian</div>
                    </div>

                    <div className="p-2.5 bg-emerald-50 border border-emerald-200 rounded-lg">
                      <div className="text-[10px] text-emerald-600 font-semibold uppercase">Poin Prestasi</div>
                      <div className="text-lg font-bold text-emerald-700 mt-0.5">
                        +{studentSummary.total_achievement_points}
                      </div>
                      <div className="text-[10px] text-emerald-500">{studentSummary.achievement_count} prestasi</div>
                    </div>

                    <div className="p-2.5 bg-slate-100 border border-slate-200 rounded-lg">
                      <div className="text-[10px] text-slate-600 font-semibold uppercase">Poin Bersih (Net)</div>
                      <div
                        className={`text-lg font-bold mt-0.5 ${
                          studentSummary.net_points >= 0 ? 'text-emerald-700' : 'text-rose-700'
                        }`}
                      >
                        {studentSummary.net_points}
                      </div>
                      <div className="text-[10px] text-slate-400">Prestasi - Pelanggaran</div>
                    </div>
                  </div>

                  {studentSummary.active_sanction && (
                    <div className="p-3 bg-amber-50 border border-amber-300 rounded-lg text-amber-900">
                      <div className="font-bold flex items-center gap-1.5 text-xs text-amber-800">
                        <Flame className="h-4 w-4 text-amber-600" />
                        Status Sanksi Aktif:
                      </div>
                      <div className="mt-1 font-semibold text-xs">{studentSummary.active_sanction.action}</div>
                      <div className="text-[11px] text-amber-700 mt-0.5">
                        Tercapai pada ambang {studentSummary.active_sanction.min_points} poin pelanggaran
                      </div>
                    </div>
                  )}

                  <div>
                    <h4 className="font-bold text-slate-800 mb-1.5">Riwayat 5 Poin Terkini:</h4>
                    {studentSummary.recent_points.length === 0 ? (
                      <p className="text-slate-400 text-[11px]">Belum ada riwayat catatan poin.</p>
                    ) : (
                      <div className="divide-y border rounded-md">
                        {studentSummary.recent_points.map((rp) => (
                          <div key={rp.id} className="p-2 flex justify-between items-center text-[11px]">
                            <div>
                              <div className="font-medium text-slate-800">{rp.point_type?.name}</div>
                              <div className="text-[10px] text-slate-400">{rp.point_date}</div>
                            </div>
                            <span
                              className={`font-bold ${
                                rp.point_type?.kind === 'pelanggaran' ? 'text-rose-600' : 'text-emerald-600'
                              }`}
                            >
                              {rp.point_type?.kind === 'pelanggaran'
                                ? `-${rp.point_type?.points}`
                                : `+${rp.point_type?.points}`}
                            </span>
                          </div>
                        ))}
                      </div>
                    )}
                  </div>
                </div>
              ) : null}
            </div>

            <div className="p-3 border-t bg-slate-50 flex justify-end">
              <Button
                size="sm"
                variant="outline"
                onClick={() => {
                  setSummaryModalStudentId(null);
                  setStudentSummary(null);
                }}
                className="text-xs"
              >
                Tutup
              </Button>
            </div>
          </div>
        </div>
      )}

      {/* Dialog Konfirmasi Hapus Jenis Poin / Ambang / Catatan */}
      <ConfirmDialog
        isOpen={deleteConfirm.isOpen}
        onClose={() => setDeleteConfirm((prev) => ({ ...prev, isOpen: false }))}
        onConfirm={async () => {
          setDeleteConfirm((prev) => ({ ...prev, isLoading: true }));
          try {
            await deleteConfirm.onConfirm();
            setDeleteConfirm((prev) => ({ ...prev, isOpen: false, isLoading: false }));
          } catch {
            setDeleteConfirm((prev) => ({ ...prev, isLoading: false }));
          }
        }}
        title={deleteConfirm.title}
        description={deleteConfirm.description}
        confirmLabel="Ya, Hapus"
        cancelLabel="Batal"
        variant="danger"
        isLoading={deleteConfirm.isLoading}
      />
    </div>
  );
}
