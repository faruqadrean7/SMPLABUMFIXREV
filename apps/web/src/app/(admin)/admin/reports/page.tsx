'use client';

import React, { useState, useEffect, useCallback } from 'react';
import { useAuth } from '@/providers/auth-provider';
import { isReadOnlyRole, anonymizeStudentName } from '@/lib/rbac/evaluator';
import { reportsApi } from '@/features/reports/reports-api';
import { ReportType, ReportFilterParams, ReportResponse } from '@/features/reports/types';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { toast } from '@/components/ui/toast';
import {
  FileBarChart,
  Download,
  Filter,
  Calendar,
  Layers,
  Award,
  AlertTriangle,
  RefreshCw,
  Clock,
  BookOpen,
  UserCheck,
  CheckCircle2,
  XCircle,
  Wrench,
  Search,
  Printer,
  Shield,
} from 'lucide-react';

interface TabItem {
  id: ReportType;
  label: string;
  description: string;
  icon: React.ElementType;
}

const REPORT_TABS: TabItem[] = [
  {
    id: 'attendance',
    label: 'Presensi Siswa',
    description: 'Rekapitulasi absensi harian dan tingkat kehadiran',
    icon: UserCheck,
  },
  {
    id: 'grades',
    label: 'Nilai Akademik',
    description: 'Distribusi nilai siswa, rata-rata, dan ketuntasan KKM',
    icon: Award,
  },
  {
    id: 'ppdb',
    label: 'Penerimaan PPDB',
    description: 'Statistik pendaftar, gelombang seleksi, dan jalur',
    icon: Layers,
  },
  {
    id: 'student_points',
    label: 'Poin Kesiswaan',
    description: 'Catatan poin prestasi dan pelanggaran kedisiplinan',
    icon: Award,
  },
  {
    id: 'tickets',
    label: 'Tiket Sarpras',
    description: 'Pelaporan helpdesk fasilitas dan tiket kerusakan',
    icon: Wrench,
  },
  {
    id: 'loans',
    label: 'Peminjaman Buku',
    description: 'Sirkulasi perpustakaan, keterlambatan, dan denda',
    icon: BookOpen,
  },
];

export default function ReportsCenterPage() {
  const { user } = useAuth();
  const isReadOnly = isReadOnlyRole(user?.roles);

  const formatStudentName = (rawName?: string) => {
    if (!rawName || rawName === '-') return '-';
    return isReadOnly ? anonymizeStudentName(rawName) : rawName;
  };

  const [activeTab, setActiveTab] = useState<ReportType>('attendance');
  const [startDate, setStartDate] = useState<string>('');
  const [endDate, setEndDate] = useState<string>('');
  const [statusFilter, setStatusFilter] = useState<string>('');
  const [page, setPage] = useState<number>(1);

  const [loading, setLoading] = useState<boolean>(true);
  const [exporting, setExporting] = useState<boolean>(false);
  const [error, setError] = useState<string | null>(null);
  const [reportData, setReportData] = useState<ReportResponse | null>(null);

  const fetchReport = useCallback(async () => {
    setLoading(true);
    setError(null);

    const params: ReportFilterParams = {
      page,
      per_page: 15,
    };
    if (startDate) params.start_date = startDate;
    if (endDate) params.end_date = endDate;
    if (statusFilter) params.status = statusFilter;

    try {
      const res = await reportsApi.getReportData(activeTab, params);
      if (res.data) {
        setReportData(res.data);
      } else {
        setError('Data laporan tidak dapat dimuat.');
      }
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Gagal memuat data laporan.';
      setError(msg);
    } finally {
      setLoading(false);
    }
  }, [activeTab, startDate, endDate, statusFilter, page]);

  useEffect(() => {
    fetchReport();
  }, [fetchReport]);

  const handleTabChange = (type: ReportType) => {
    setActiveTab(type);
    setPage(1);
    setStatusFilter('');
  };

  const handleExportCsv = async () => {
    setExporting(true);
    try {
      const params: ReportFilterParams = {};
      if (startDate) params.start_date = startDate;
      if (endDate) params.end_date = endDate;
      if (statusFilter) params.status = statusFilter;

      const blob = await reportsApi.exportReportCsv(activeTab, params);
      const filename = `rekap_${activeTab}_${new Date().toISOString().slice(0, 10)}.csv`;
      reportsApi.triggerBlobDownload(blob, filename);
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Gagal mengunduh berkas laporan CSV.';
      toast.error(msg);
    } finally {
      setExporting(false);
    }
  };

  const summary = (reportData?.summary || {}) as Record<string, any>;
  const rows = (reportData?.data as Record<string, unknown>[]) || [];
  const meta = reportData?.meta;

  return (
    <div className="space-y-6" data-testid="reports-center-page">
      {/* Header Banner */}
      <div className="rounded-xl bg-gradient-to-r from-slate-900 to-slate-800 p-6 text-white shadow-elevated">
        <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
          <div>
            <div className="flex items-center gap-2 mb-1">
              <Badge variant="outline" className="text-white border-white/30 bg-white/10 text-xs">
                Pusat Data Terpadu
              </Badge>
              <span className="text-xs text-slate-400">•</span>
              <span className="text-xs text-slate-400">ADM-RPT-01 / BE-RPT-01</span>
            </div>
            <h1 className="text-2xl font-bold tracking-tight">Pusat Laporan & Rekapitulasi</h1>
            <p className="mt-1 text-sm text-slate-300 max-w-2xl">
              Unduh rekapitulasi data akademik, presensi, kesiswaan, PPDB, dan sarana prasarana sekolah
              dalam format data tabel terstruktur dan berkas ekspor CSV/Excel.
            </p>
          </div>

          <div className="flex items-center gap-2">
            <Button
              onClick={() => window.print()}
              variant="outline"
              className="border-white/30 text-white hover:bg-white/10 font-medium text-xs gap-1.5"
              data-testid="print-report-button"
            >
              <Printer className="h-3.5 w-3.5" />
              <span>Cetak / PDF</span>
            </Button>
            <Button
              onClick={handleExportCsv}
              disabled={exporting || loading}
              variant="default"
              className="bg-brand-teal hover:bg-teal-700 text-white font-medium text-xs"
              data-testid="export-csv-button"
            >
              {exporting ? (
                <>
                  <RefreshCw className="h-3.5 w-3.5 mr-1.5 animate-spin" />
                  Mengunduh...
                </>
              ) : (
                <>
                  <Download className="h-3.5 w-3.5 mr-1.5" />
                  Ekspor Laporan (CSV)
                </>
              )}
            </Button>
          </div>
        </div>
      </div>

      {/* Banner Khusus Mode Baca-Saja (Pengawas / Komite) */}
      {isReadOnly && (
        <div
          className="p-4 rounded-xl bg-blue-50 border border-blue-200 text-blue-950 flex items-start gap-3 shadow-xs"
          data-testid="read-only-privacy-notice"
        >
          <Shield className="h-5 w-5 shrink-0 text-brand-blue mt-0.5" />
          <div className="text-xs space-y-0.5">
            <span className="font-bold block text-sm text-brand-blue">
              Mode Akses Pengawas & Komite Sekolah (Baca-Saja)
            </span>
            <p className="text-slate-600">
              Sesuai prinsip perlindungan data pribadi dan regulasi sekolah, Anda sedang mengakses ringkasan
              kinerja agregat. Identitas personal siswa pada tabel di bawah disamarkan secara otomatis.
            </p>
          </div>
        </div>
      )}

      {/* Selector Kategori Laporan (6 Tab) */}
      <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2.5">
        {REPORT_TABS.map((tab) => {
          const Icon = tab.icon;
          const isActive = activeTab === tab.id;
          return (
            <button
              key={tab.id}
              onClick={() => handleTabChange(tab.id)}
              data-testid={`tab-${tab.id}`}
              className={`flex flex-col items-center justify-center p-3 rounded-xl border text-center transition-all ${
                isActive
                  ? 'border-brand-blue bg-blue-50/70 text-brand-blue shadow-xs font-semibold'
                  : 'border-slate-200 bg-white hover:bg-slate-50 text-slate-700'
              }`}
            >
              <Icon className={`h-5 w-5 mb-1.5 ${isActive ? 'text-brand-blue' : 'text-slate-500'}`} />
              <span className="text-xs">{tab.label}</span>
            </button>
          );
        })}
      </div>

      {/* Filter Toolbar */}
      <Card>
        <CardContent className="p-4">
          <div className="flex flex-col md:flex-row items-center gap-3">
            <div className="flex items-center gap-2 w-full md:w-auto">
              <Filter className="h-4 w-4 text-slate-500" />
              <span className="text-xs font-medium text-slate-700">Filter Periode:</span>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2 flex-1 w-full">
              <div className="flex items-center gap-1.5">
                <span className="text-xs text-slate-500">Mulai:</span>
                <Input
                  type="date"
                  value={startDate}
                  onChange={(e) => setStartDate(e.target.value)}
                  className="h-8 text-xs"
                  data-testid="filter-start-date"
                />
              </div>

              <div className="flex items-center gap-1.5">
                <span className="text-xs text-slate-500">Sampai:</span>
                <Input
                  type="date"
                  value={endDate}
                  onChange={(e) => setEndDate(e.target.value)}
                  className="h-8 text-xs"
                  data-testid="filter-end-date"
                />
              </div>

              <div className="flex items-center gap-1.5">
                <span className="text-xs text-slate-500">Status:</span>
                <Input
                  placeholder="Filter status..."
                  value={statusFilter}
                  onChange={(e) => setStatusFilter(e.target.value)}
                  className="h-8 text-xs"
                  data-testid="filter-status"
                />
              </div>
            </div>

            <div className="flex items-center gap-2 w-full md:w-auto self-end">
              <Button
                size="sm"
                variant="outline"
                onClick={() => {
                  setStartDate('');
                  setEndDate('');
                  setStatusFilter('');
                }}
                className="text-xs h-8"
              >
                Reset
              </Button>
              <Button
                size="sm"
                onClick={fetchReport}
                className="bg-brand-blue hover:bg-blue-800 text-white text-xs h-8"
              >
                <Search className="h-3.5 w-3.5 mr-1" />
                Terapkan
              </Button>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Ringkasan Metrik Rekapitulasi (Dynamically Rendered per Active Tab) */}
      <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        {activeTab === 'attendance' && (
          <>
            <SummaryCard title="Total Data" value={summary.total_records ?? 0} />
            <SummaryCard title="Hadir" value={summary.hadir_count ?? 0} color="text-emerald-600" />
            <SummaryCard title="Izin" value={summary.izin_count ?? 0} color="text-blue-600" />
            <SummaryCard title="Sakit" value={summary.sakit_count ?? 0} color="text-amber-600" />
            <SummaryCard title="Alpa" value={summary.alpa_count ?? 0} color="text-rose-600" />
            <SummaryCard
              title="Tingkat Hadir"
              value={`${summary.attendance_rate ?? 0}%`}
              color="text-emerald-700"
              isHighlight
            />
          </>
        )}

        {activeTab === 'grades' && (
          <>
            <SummaryCard title="Total Nilai Masuk" value={summary.total_grades ?? 0} />
            <SummaryCard
              title="Rata-rata Nilai"
              value={summary.average_score ?? 0}
              color="text-brand-blue"
              isHighlight
            />
            <SummaryCard title="Nilai Tertinggi" value={summary.highest_score ?? 0} color="text-emerald-600" />
            <SummaryCard title="Nilai Terendah" value={summary.lowest_score ?? 0} color="text-rose-600" />
            <SummaryCard title="Tuntas KKM" value={summary.passing_count ?? 0} color="text-teal-600" />
            <SummaryCard title="Belum Tuntas" value={summary.failing_count ?? 0} color="text-amber-600" />
          </>
        )}

        {activeTab === 'ppdb' && (
          <>
            <SummaryCard
              title="Total Pendaftar"
              value={summary.total_applicants ?? 0}
              isHighlight
            />
            <SummaryCard title="Rerata Skor Seleksi" value={summary.average_score ?? 0} color="text-brand-blue" />
            <SummaryCard
              title="Verifikasi Lolos"
              value={
                typeof summary.by_status === 'object' && summary.by_status !== null
                  ? (summary.by_status as Record<string, number>)['verified'] ?? 0
                  : 0
              }
              color="text-emerald-600"
            />
            <SummaryCard
              title="Diterima"
              value={
                typeof summary.by_status === 'object' && summary.by_status !== null
                  ? (summary.by_status as Record<string, number>)['accepted'] ?? 0
                  : 0
              }
              color="text-teal-600"
            />
            <SummaryCard
              title="Menunggu"
              value={
                typeof summary.by_status === 'object' && summary.by_status !== null
                  ? (summary.by_status as Record<string, number>)['submitted'] ?? 0
                  : 0
              }
              color="text-amber-600"
            />
            <SummaryCard
              title="Ditolak"
              value={
                typeof summary.by_status === 'object' && summary.by_status !== null
                  ? (summary.by_status as Record<string, number>)['rejected'] ?? 0
                  : 0
              }
              color="text-rose-600"
            />
          </>
        )}

        {activeTab === 'student_points' && (
          <>
            <SummaryCard title="Total Catatan" value={summary.total_events ?? 0} />
            <SummaryCard
              title="Poin Prestasi"
              value={summary.total_prestasi_points ?? 0}
              color="text-emerald-600"
              isHighlight
            />
            <SummaryCard
              title="Poin Pelanggaran"
              value={summary.total_pelanggaran_points ?? 0}
              color="text-rose-600"
            />
            <SummaryCard title="Kejadian Prestasi" value={summary.prestasi_events ?? 0} color="text-teal-600" />
            <SummaryCard title="Kejadian Pelanggaran" value={summary.pelanggaran_events ?? 0} color="text-amber-600" />
            <SummaryCard
              title="Keseimbangan Poin"
              value={
                Number(summary.total_prestasi_points ?? 0) - Number(summary.total_pelanggaran_points ?? 0)
              }
              color="text-slate-800"
            />
          </>
        )}

        {activeTab === 'tickets' && (
          <>
            <SummaryCard title="Total Tiket" value={summary.total_tickets ?? 0} isHighlight />
            <SummaryCard title="Pending" value={summary.pending_count ?? 0} color="text-amber-600" />
            <SummaryCard title="Diproses" value={summary.diproses_count ?? 0} color="text-brand-blue" />
            <SummaryCard title="Selesai" value={summary.selesai_count ?? 0} color="text-emerald-600" />
            <SummaryCard
              title="Penyelesaian"
              value={
                Number(summary.total_tickets ?? 0) > 0
                  ? `${Math.round(
                      (Number(summary.selesai_count ?? 0) / Number(summary.total_tickets ?? 1)) * 100
                    )}%`
                  : '100%'
              }
              color="text-teal-700"
            />
            <SummaryCard
              title="Antrean Berjalan"
              value={Number(summary.pending_count ?? 0) + Number(summary.diproses_count ?? 0)}
              color="text-orange-600"
            />
          </>
        )}

        {activeTab === 'loans' && (
          <>
            <SummaryCard title="Total Transaksi" value={summary.total_loans ?? 0} />
            <SummaryCard title="Sedang Dipinjam" value={summary.active_loans ?? 0} color="text-brand-blue" isHighlight />
            <SummaryCard title="Sudah Dikembalikan" value={summary.returned_loans ?? 0} color="text-emerald-600" />
            <SummaryCard title="Terlambat (Overdue)" value={summary.overdue_loans ?? 0} color="text-rose-600" />
            <SummaryCard
              title="Total Denda"
              value={`Rp ${Number(summary.total_fines || 0).toLocaleString()}`}
              color="text-amber-700"
            />
            <SummaryCard
              title="Kepatuhan"
              value={
                Number(summary.total_loans ?? 0) > 0
                  ? `${Math.round(
                      (Number(summary.returned_loans ?? 0) / Number(summary.total_loans ?? 1)) * 100
                    )}%`
                  : '100%'
              }
              color="text-teal-700"
            />
          </>
        )}
      </div>

      {/* Tabel Pratinjau Data Laporan */}
      <Card>
        <CardHeader className="flex flex-row items-center justify-between pb-2">
          <div>
            <CardTitle className="text-base font-bold">Pratinjau Data Rincian</CardTitle>
            <CardDescription className="text-xs">
              Menampilkan {rows.length} dari total {meta?.total ?? 0} baris data rekapitulasi.
            </CardDescription>
          </div>
          <Button
            size="sm"
            variant="ghost"
            onClick={fetchReport}
            className="text-xs text-slate-500 hover:text-slate-800"
          >
            <RefreshCw className={`h-3.5 w-3.5 mr-1 ${loading ? 'animate-spin' : ''}`} />
            Segarkan
          </Button>
        </CardHeader>

        <CardContent>
          {loading ? (
            <div className="p-8 text-center space-y-3" data-testid="table-loading">
              <RefreshCw className="h-6 w-6 animate-spin mx-auto text-brand-blue" />
              <p className="text-xs text-slate-500">Memuat rincian laporan...</p>
            </div>
          ) : error ? (
            <div className="p-8 text-center bg-rose-50 rounded-lg border border-rose-100" data-testid="table-error">
              <AlertTriangle className="h-8 w-8 text-rose-500 mx-auto mb-2" />
              <p className="text-sm font-semibold text-rose-800">{error}</p>
              <Button size="sm" onClick={fetchReport} className="mt-3 bg-rose-600 hover:bg-rose-700 text-white text-xs">
                Coba Lagi
              </Button>
            </div>
          ) : rows.length === 0 ? (
            <div className="p-8 text-center bg-slate-50 rounded-lg border border-slate-200" data-testid="table-empty">
              <FileBarChart className="h-8 w-8 text-slate-400 mx-auto mb-2" />
              <h4 className="text-sm font-semibold text-slate-700">Tidak Ada Data Ditemukan</h4>
              <p className="text-xs text-slate-500 mt-1">
                Tidak ada data rekapitulasi yang cocok dengan filter periode atau kriteria yang dipilih.
              </p>
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-xs text-left border-collapse" data-testid="report-table">
                <thead>
                  <tr className="border-b border-slate-200 bg-slate-50/70 text-slate-600 font-semibold">
                    {activeTab === 'attendance' && (
                      <>
                        <th className="p-2.5">Tanggal</th>
                        <th className="p-2.5">Nama Siswa</th>
                        <th className="p-2.5">Status</th>
                        <th className="p-2.5">Sumber</th>
                        <th className="p-2.5">Catatan</th>
                      </>
                    )}
                    {activeTab === 'grades' && (
                      <>
                        <th className="p-2.5">Nama Siswa</th>
                        <th className="p-2.5">Komponen Nilai</th>
                        <th className="p-2.5">Nilai</th>
                        <th className="p-2.5">Sumber</th>
                        <th className="p-2.5">Ketuntasan</th>
                      </>
                    )}
                    {activeTab === 'ppdb' && (
                      <>
                        <th className="p-2.5">No. Pendaftaran</th>
                        <th className="p-2.5">Nama Calon Siswa</th>
                        <th className="p-2.5">Gelombang</th>
                        <th className="p-2.5">Jalur</th>
                        <th className="p-2.5">Status</th>
                        <th className="p-2.5">Skor Seleksi</th>
                      </>
                    )}
                    {activeTab === 'student_points' && (
                      <>
                        <th className="p-2.5">Tanggal</th>
                        <th className="p-2.5">Nama Siswa</th>
                        <th className="p-2.5">Kategori</th>
                        <th className="p-2.5">Nama Poin</th>
                        <th className="p-2.5">Poin</th>
                        <th className="p-2.5">Catatan</th>
                      </>
                    )}
                    {activeTab === 'tickets' && (
                      <>
                        <th className="p-2.5">ID Tiket</th>
                        <th className="p-2.5">Deskripsi Masalah</th>
                        <th className="p-2.5">Prioritas</th>
                        <th className="p-2.5">Status</th>
                        <th className="p-2.5">Pelapor</th>
                      </>
                    )}
                    {activeTab === 'loans' && (
                      <>
                        <th className="p-2.5">Peminjam</th>
                        <th className="p-2.5">Judul Buku</th>
                        <th className="p-2.5">Tgl Pinjam</th>
                        <th className="p-2.5">Jatuh Tempo</th>
                        <th className="p-2.5">Tgl Kembali</th>
                        <th className="p-2.5">Denda</th>
                      </>
                    )}
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {rows.map((row, idx) => (
                    <tr key={idx} className="hover:bg-slate-50/60 transition-colors">
                      {activeTab === 'attendance' && (
                        <>
                          <td className="p-2.5 font-medium">{String(row.att_date || '-')}</td>
                          <td className="p-2.5">{formatStudentName(String((row.student as Record<string, unknown>)?.full_name || row.student_name || '-'))}</td>
                          <td className="p-2.5">
                            <StatusBadge status={String(row.status)} />
                          </td>
                          <td className="p-2.5 text-slate-500">{String(row.source || '-')}</td>
                          <td className="p-2.5 text-slate-500">{String(row.note || '-')}</td>
                        </>
                      )}
                      {activeTab === 'grades' && (
                        <>
                          <td className="p-2.5 font-medium">{formatStudentName(String((row.student as Record<string, unknown>)?.full_name || row.student_name || '-'))}</td>
                          <td className="p-2.5">{String((row.component as Record<string, unknown>)?.name || row.component_name || '-')}</td>
                          <td className="p-2.5 font-bold text-slate-900">{Number(row.value ?? 0)}</td>
                          <td className="p-2.5 text-slate-500">{String(row.source || '-')}</td>
                          <td className="p-2.5">
                            {Number(row.value ?? 0) >= 75 ? (
                              <Badge className="bg-emerald-100 text-emerald-800 border-none text-[10px]">Tuntas</Badge>
                            ) : (
                              <Badge className="bg-rose-100 text-rose-800 border-none text-[10px]">Remidi</Badge>
                            )}
                          </td>
                        </>
                      )}
                      {activeTab === 'ppdb' && (
                        <>
                          <td className="p-2.5 font-mono font-medium">{String(row.registration_no || '-')}</td>
                          <td className="p-2.5 font-medium">{formatStudentName(String(row.full_name || '-'))}</td>
                          <td className="p-2.5">{String((row.wave as Record<string, unknown>)?.name || row.wave_name || '-')}</td>
                          <td className="p-2.5">{String((row.track as Record<string, unknown>)?.name || row.track_name || '-')}</td>
                          <td className="p-2.5">
                            <StatusBadge status={String(row.status)} />
                          </td>
                          <td className="p-2.5 font-bold text-slate-900">{row.score !== null ? Number(row.score) : '-'}</td>
                        </>
                      )}
                      {activeTab === 'student_points' && (
                        <>
                          <td className="p-2.5">{String(row.point_date || '-')}</td>
                          <td className="p-2.5 font-medium">{formatStudentName(String((row.student as Record<string, unknown>)?.full_name || row.student_name || '-'))}</td>
                          <td className="p-2.5">
                            <Badge
                              className={`text-[10px] border-none ${
                                (row.point_type as Record<string, unknown>)?.kind === 'prestasi' || row.kind === 'prestasi'
                                  ? 'bg-emerald-100 text-emerald-800'
                                  : 'bg-rose-100 text-rose-800'
                              }`}
                            >
                              {String((row.point_type as Record<string, unknown>)?.kind || row.kind || '-')}
                            </Badge>
                          </td>
                          <td className="p-2.5">{String((row.point_type as Record<string, unknown>)?.name || row.point_name || '-')}</td>
                          <td className="p-2.5 font-bold">{Number((row.point_type as Record<string, unknown>)?.points || row.points || 0)}</td>
                          <td className="p-2.5 text-slate-500">{String(row.notes || '-')}</td>
                        </>
                      )}
                      {activeTab === 'tickets' && (
                        <>
                          <td className="p-2.5 font-mono text-slate-500">{String(row.id || '-').slice(0, 8)}...</td>
                          <td className="p-2.5 font-medium">{String(row.description || '-')}</td>
                          <td className="p-2.5">
                            <Badge
                              className={`text-[10px] border-none ${
                                row.priority === 'darurat'
                                  ? 'bg-rose-100 text-rose-800'
                                  : row.priority === 'tinggi'
                                  ? 'bg-orange-100 text-orange-800'
                                  : 'bg-slate-100 text-slate-800'
                              }`}
                            >
                              {String(row.priority || '-')}
                            </Badge>
                          </td>
                          <td className="p-2.5">
                            <StatusBadge status={String(row.status)} />
                          </td>
                          <td className="p-2.5 text-slate-500">{String((row.reporter as Record<string, unknown>)?.username || row.reporter || '-')}</td>
                        </>
                      )}
                      {activeTab === 'loans' && (
                        <>
                          <td className="p-2.5 font-medium">{formatStudentName(String((row.borrower as Record<string, unknown>)?.username || row.borrower || '-'))}</td>
                          <td className="p-2.5">{String((row.book as Record<string, unknown>)?.title || row.book_title || '-')}</td>
                          <td className="p-2.5">{String(row.loan_date || '-')}</td>
                          <td className="p-2.5">{String(row.due_date || '-')}</td>
                          <td className="p-2.5">
                            {row.returned_at ? (
                              <span className="text-emerald-600 font-medium">{String(row.returned_at)}</span>
                            ) : (
                              <Badge className="bg-amber-100 text-amber-800 border-none text-[10px]">Aktif</Badge>
                            )}
                          </td>
                          <td className="p-2.5 font-medium">Rp {Number(row.fine_amount ?? 0).toLocaleString('id-ID')}</td>
                        </>
                      )}
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}

          {/* Pagination Controls */}
          {meta && meta.last_page > 1 && (
            <div className="flex items-center justify-between pt-4 border-t border-slate-100 mt-4 text-xs">
              <span className="text-slate-500">
                Halaman {meta.current_page} dari {meta.last_page} ({meta.total} total baris)
              </span>
              <div className="flex items-center gap-2">
                <Button
                  size="sm"
                  variant="outline"
                  disabled={page <= 1 || loading}
                  onClick={() => setPage((p) => Math.max(1, p - 1))}
                  className="h-8 text-xs"
                >
                  Sebelumnya
                </Button>
                <Button
                  size="sm"
                  variant="outline"
                  disabled={page >= meta.last_page || loading}
                  onClick={() => setPage((p) => p + 1)}
                  className="h-8 text-xs"
                >
                  Selanjutnya
                </Button>
              </div>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
}

function SummaryCard({
  title,
  value,
  color = 'text-slate-900',
  isHighlight = false,
}: {
  title: string;
  value: React.ReactNode;
  color?: string;
  isHighlight?: boolean;
}) {
  return (
    <Card className={`text-center ${isHighlight ? 'border-brand-blue/40 bg-blue-50/20' : ''}`}>
      <CardContent className="p-3.5">
        <div className="text-[11px] font-medium text-slate-500 truncate">{title}</div>
        <div className={`text-xl font-bold mt-1 ${color}`}>{value}</div>
      </CardContent>
    </Card>
  );
}

function StatusBadge({ status }: { status: string }) {
  let bg = 'bg-slate-100 text-slate-800';
  if (['hadir', 'selesai', 'verified', 'accepted'].includes(status)) {
    bg = 'bg-emerald-100 text-emerald-800';
  } else if (['izin', 'diproses', 'submitted'].includes(status)) {
    bg = 'bg-blue-100 text-blue-800';
  } else if (['sakit', 'pending', 'terlambat'].includes(status)) {
    bg = 'bg-amber-100 text-amber-800';
  } else if (['alpa', 'rejected', 'rusak_berat'].includes(status)) {
    bg = 'bg-rose-100 text-rose-800';
  }

  return <Badge className={`text-[10px] border-none capitalize ${bg}`}>{status}</Badge>;
}
