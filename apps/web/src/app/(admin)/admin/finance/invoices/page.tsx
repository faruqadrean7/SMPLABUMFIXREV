'use client';

import React, { useEffect, useState, useCallback } from 'react';
import {
  FileText,
  Plus,
  RefreshCw,
  Search,
  Filter,
  CheckCircle2,
  AlertTriangle,
  Clock,
  Ban,
  Calendar,
  X,
  AlertCircle,
  Eye,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { LoadingSpinner } from '@/components/shared/loading-spinner';
import { EmptyState } from '@/components/shared/empty-state';
import { ErrorState } from '@/components/shared/error-state';
import { financeApi, formatRupiah } from '@/features/finance/finance-api';
import { classesApi } from '@/features/master/classes-api';
import { academicYearsApi } from '@/features/master/academic-years-api';
import { SchoolClassItem, AcademicYearItem } from '@/types/master';
import { Invoice, InvoiceStatus, FeeType } from '@/types/finance';

export default function InvoicesAdminPage() {
  const [invoices, setInvoices] = useState<Invoice[]>([]);
  const [totalCount, setTotalCount] = useState(0);
  const [classes, setClasses] = useState<SchoolClassItem[]>([]);
  const [academicYears, setAcademicYears] = useState<AcademicYearItem[]>([]);
  const [feeTypes, setFeeTypes] = useState<FeeType[]>([]);

  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Filters
  const [searchQuery, setSearchQuery] = useState('');
  const [statusFilter, setStatusFilter] = useState<string>('all');
  const [classFilter, setClassFilter] = useState<string>('all');
  const [periodFilter, setPeriodFilter] = useState<string>('');

  // Bulk Generate Modal State
  const [isBulkModalOpen, setIsBulkModalOpen] = useState(false);
  const [bulkData, setBulkData] = useState({
    academic_year_id: '',
    fee_type_id: '',
    period: new Date().toISOString().slice(0, 7), // YYYY-MM
    due_date: new Date(new Date().setDate(new Date().getDate() + 14)).toISOString().slice(0, 10), // +14 days
    class_id: '',
  });
  const [isBulkSubmitting, setIsBulkSubmitting] = useState(false);
  const [bulkFeedback, setBulkFeedback] = useState<{ created: number; skipped: number } | null>(null);
  const [bulkError, setBulkError] = useState<string | null>(null);

  // Cancel Modal State
  const [cancellingInvoice, setCancellingInvoice] = useState<Invoice | null>(null);
  const [cancelNotes, setCancelNotes] = useState('');
  const [isCancelling, setIsCancelling] = useState(false);

  // Invoice Detail Modal State
  const [selectedInvoice, setSelectedInvoice] = useState<Invoice | null>(null);

  const loadData = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    try {
      const [invRes, classesRes, ayRes, ftRes] = await Promise.all([
        financeApi.getInvoices({
          class_id: classFilter !== 'all' ? classFilter : undefined,
          status: statusFilter !== 'all' ? statusFilter : undefined,
          period: periodFilter || undefined,
          search: searchQuery || undefined,
          per_page: 50,
        }),
        classesApi.list({ limit: 100 }).catch(() => ({ data: [] })),
        academicYearsApi.list({ limit: 50 }).catch(() => ({ data: [] })),
        financeApi.getFeeTypes().catch(() => []),
      ]);

      setInvoices(invRes.data || []);
      setTotalCount(invRes.meta?.total || (invRes.data || []).length);
      setClasses(classesRes.data || []);
      setAcademicYears(ayRes.data || []);
      setFeeTypes(ftRes || []);

      if (ayRes.data?.length > 0) {
        const activeAy = ayRes.data.find((y: AcademicYearItem) => y.is_active) || ayRes.data[0];
        setBulkData((prev) => (prev.academic_year_id ? prev : { ...prev, academic_year_id: activeAy.id }));
      }
      if (ftRes.length > 0) {
        setBulkData((prev) => (prev.fee_type_id ? prev : { ...prev, fee_type_id: ftRes[0].id }));
      }
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Gagal memuat daftar tagihan.';
      setError(message);
    } finally {
      setIsLoading(false);
    }
  }, [classFilter, statusFilter, periodFilter, searchQuery]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const handleBulkGenerate = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!bulkData.academic_year_id || !bulkData.fee_type_id || !bulkData.due_date) {
      setBulkError('Tahun ajaran, pos biaya, dan tanggal jatuh tempo wajib diisi.');
      return;
    }

    setIsBulkSubmitting(true);
    setBulkError(null);
    setBulkFeedback(null);
    try {
      const res = await financeApi.generateBulkInvoices({
        academic_year_id: bulkData.academic_year_id,
        fee_type_id: bulkData.fee_type_id,
        period: bulkData.period || undefined,
        due_date: bulkData.due_date,
        class_id: bulkData.class_id ? bulkData.class_id : undefined,
      });

      setBulkFeedback({ created: res.created_count, skipped: res.skipped_count });
      await loadData();
    } catch (err: unknown) {
      setBulkError(err instanceof Error ? err.message : 'Gagal men-generate tagihan massal.');
    } finally {
      setIsBulkSubmitting(false);
    }
  };

  const handleConfirmCancel = async () => {
    if (!cancellingInvoice) return;
    setIsCancelling(true);
    try {
      await financeApi.cancelInvoice(cancellingInvoice.id, cancelNotes);
      setCancellingInvoice(null);
      setCancelNotes('');
      await loadData();
    } catch (err: unknown) {
      alert(err instanceof Error ? err.message : 'Gagal membatalkan tagihan.');
    } finally {
      setIsCancelling(false);
    }
  };

  const renderStatusBadge = (status: InvoiceStatus) => {
    switch (status) {
      case 'lunas':
        return (
          <Badge className="bg-emerald-100 text-emerald-800 border-emerald-200 hover:bg-emerald-100 flex items-center gap-1 w-fit">
            <CheckCircle2 className="h-3 w-3" />
            Lunas
          </Badge>
        );
      case 'sebagian':
        return (
          <Badge className="bg-amber-100 text-amber-800 border-amber-200 hover:bg-amber-100 flex items-center gap-1 w-fit">
            <Clock className="h-3 w-3" />
            Sebagian
          </Badge>
        );
      case 'belum_bayar':
        return (
          <Badge className="bg-rose-100 text-rose-800 border-rose-200 hover:bg-rose-100 flex items-center gap-1 w-fit">
            <AlertTriangle className="h-3 w-3" />
            Belum Bayar
          </Badge>
        );
      case 'dibatalkan':
        return (
          <Badge className="bg-slate-100 text-slate-600 border-slate-200 hover:bg-slate-100 flex items-center gap-1 w-fit">
            <Ban className="h-3 w-3" />
            Dibatalkan
          </Badge>
        );
      default:
        return <Badge variant="outline">{status}</Badge>;
    }
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-slate-900 tracking-tight flex items-center gap-2">
            <FileText className="h-6 w-6 text-brand-blue" />
            Tagihan & Piutang Siswa
          </h1>
          <p className="text-sm text-slate-500 mt-1">
            Kelola faktur tagihan biaya pendidikan siswa, pantau status pembayaran, dan generate massal per kelas.
          </p>
        </div>
        <div className="flex items-center gap-2">
          <Button variant="outline" size="sm" onClick={loadData} disabled={isLoading} className="gap-2">
            <RefreshCw className={`h-4 w-4 ${isLoading ? 'animate-spin' : ''}`} />
            Segarkan
          </Button>
          <Button
            size="sm"
            onClick={() => {
              setBulkFeedback(null);
              setBulkError(null);
              setIsBulkModalOpen(true);
            }}
            className="gap-2 bg-brand-blue hover:bg-brand-blue/90"
          >
            <Plus className="h-4 w-4" />
            Generate Tagihan Massal
          </Button>
        </div>
      </div>

      {/* Filter Bar */}
      <Card>
        <CardContent className="p-4">
          <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
            <div className="relative">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
              <Input
                placeholder="Cari No. Faktur / Siswa..."
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className="pl-9"
              />
            </div>

            <div>
              <select
                value={statusFilter}
                onChange={(e) => setStatusFilter(e.target.value)}
                className="w-full h-10 px-3 rounded-md border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-brand-blue"
              >
                <option value="all">Semua Status</option>
                <option value="belum_bayar">Belum Bayar</option>
                <option value="sebagian">Sebagian</option>
                <option value="lunas">Lunas</option>
                <option value="dibatalkan">Dibatalkan</option>
              </select>
            </div>

            <div>
              <select
                value={classFilter}
                onChange={(e) => setClassFilter(e.target.value)}
                className="w-full h-10 px-3 rounded-md border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-brand-blue"
              >
                <option value="all">Semua Rombel/Kelas</option>
                {classes.map((cls) => (
                  <option key={cls.id} value={cls.id}>
                    {cls.name}
                  </option>
                ))}
              </select>
            </div>

            <div>
              <Input
                type="month"
                placeholder="Periode (YYYY-MM)"
                value={periodFilter}
                onChange={(e) => setPeriodFilter(e.target.value)}
                title="Filter Periode Bulan"
              />
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Tabel Tagihan */}
      {isLoading ? (
        <Card className="p-12">
          <LoadingSpinner label="Memuat faktur tagihan siswa..." size="lg" />
        </Card>
      ) : error ? (
        <ErrorState
          title="Gagal Memuat Faktur Tagihan"
          message={error}
          onRetry={loadData}
        />
      ) : invoices.length === 0 ? (
        <Card className="p-8">
          <EmptyState
            title="Tidak Ada Tagihan Ditemukan"
            description="Belum ada data faktur tagihan yang sesuai dengan kriteria filter Anda."
            actionLabel="Generate Tagihan Massal"
            onAction={() => setIsBulkModalOpen(true)}
          />
        </Card>
      ) : (
        <Card>
          <div className="overflow-x-auto">
            <table className="w-full text-sm text-left">
              <thead className="bg-slate-50 border-b border-slate-200 text-xs uppercase font-semibold text-slate-600">
                <tr>
                  <th className="px-4 py-3">No. Faktur</th>
                  <th className="px-4 py-3">Siswa / Rombel</th>
                  <th className="px-4 py-3">Periode</th>
                  <th className="px-4 py-3">No. VA</th>
                  <th className="px-4 py-3">Jatuh Tempo</th>
                  <th className="px-4 py-3 text-right">Total Tagihan</th>
                  <th className="px-4 py-3 text-right">Terbayar</th>
                  <th className="px-4 py-3 text-right">Sisa Piutang</th>
                  <th className="px-4 py-3 text-center">Status</th>
                  <th className="px-4 py-3 text-right">Aksi</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {invoices.map((inv) => (
                  <tr key={inv.id} className="hover:bg-slate-50/50">
                    <td className="px-4 py-3 font-mono font-medium text-slate-800">{inv.invoice_number}</td>
                    <td className="px-4 py-3">
                      <div className="font-medium text-slate-900">{inv.student?.full_name || 'Siswa'}</div>
                      <div className="text-xs text-slate-500">
                        NISN: {inv.student?.nisn || '-'} • {inv.student?.current_class?.name || 'Reguler'}
                      </div>
                    </td>
                    <td className="px-4 py-3 text-slate-600">{inv.period || '-'}</td>
                    <td className="px-4 py-3 font-mono text-xs text-brand-blue font-semibold">{inv.va_number || '-'}</td>
                    <td className="px-4 py-3 text-slate-600">{inv.due_date}</td>
                    <td className="px-4 py-3 text-right font-medium text-slate-900">
                      {formatRupiah(inv.total_amount)}
                    </td>
                    <td className="px-4 py-3 text-right text-emerald-600 font-medium">
                      {formatRupiah(inv.paid_amount)}
                    </td>
                    <td className="px-4 py-3 text-right text-rose-600 font-semibold">
                      {formatRupiah(inv.remaining_amount)}
                    </td>
                    <td className="px-4 py-3 text-center">{renderStatusBadge(inv.status)}</td>
                    <td className="px-4 py-3 text-right space-x-1">
                      <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => setSelectedInvoice(inv)}
                        className="h-8 w-8 p-0 text-slate-600 hover:text-brand-blue"
                        title="Lihat Detail Tagihan"
                      >
                        <Eye className="h-4 w-4" />
                      </Button>
                      {inv.status !== 'lunas' && inv.status !== 'dibatalkan' && (
                        <Button
                          variant="ghost"
                          size="sm"
                          onClick={() => {
                            setCancellingInvoice(inv);
                            setCancelNotes('');
                          }}
                          className="h-8 w-8 p-0 text-slate-400 hover:text-rose-600"
                          title="Batalkan Tagihan"
                        >
                          <Ban className="h-4 w-4" />
                        </Button>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <div className="p-4 border-t border-slate-100 text-xs text-slate-500 flex justify-between items-center">
            <span>Menampilkan {invoices.length} dari {totalCount} total faktur</span>
          </div>
        </Card>
      )}

      {/* Modal Generate Massal */}
      {isBulkModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="bg-white rounded-xl shadow-xl w-full max-w-lg overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            <div className="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
              <div>
                <h2 className="text-lg font-semibold text-slate-900">Generate Tagihan Massal</h2>
                <p className="text-xs text-slate-500">Tagihan idempoten: tidak akan menduplikasi tagihan yang sudah ada.</p>
              </div>
              <button
                onClick={() => setIsBulkModalOpen(false)}
                className="text-slate-400 hover:text-slate-600 p-1 rounded-lg"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            <form onSubmit={handleBulkGenerate} className="p-6 space-y-4">
              {bulkError && (
                <div className="p-3 bg-red-50 text-red-700 text-xs rounded-lg flex items-center gap-2">
                  <AlertCircle className="h-4 w-4 shrink-0" />
                  <span>{bulkError}</span>
                </div>
              )}

              {bulkFeedback && (
                <div className="p-3 bg-emerald-50 text-emerald-800 text-xs rounded-lg space-y-1">
                  <div className="font-semibold flex items-center gap-1.5">
                    <CheckCircle2 className="h-4 w-4 text-emerald-600" />
                    Tagihan Berhasil Di-generate!
                  </div>
                  <div>
                    Faktur Baru Terbit: <strong>{bulkFeedback.created}</strong> | Dilewati (Sudah Ada):{' '}
                    <strong>{bulkFeedback.skipped}</strong>
                  </div>
                </div>
              )}

              <div>
                <label className="block text-xs font-semibold text-slate-700 uppercase mb-1">
                  Tahun Ajaran
                </label>
                <select
                  value={bulkData.academic_year_id}
                  onChange={(e) => setBulkData({ ...bulkData, academic_year_id: e.target.value })}
                  className="w-full h-10 px-3 rounded-md border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-brand-blue"
                  required
                >
                  <option value="">Pilih Tahun Ajaran</option>
                  {academicYears.map((ay) => (
                    <option key={ay.id} value={ay.id}>
                      {ay.name} {ay.is_active ? '— (Aktif)' : ''}
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-700 uppercase mb-1">
                  Pos Biaya
                </label>
                <select
                  value={bulkData.fee_type_id}
                  onChange={(e) => setBulkData({ ...bulkData, fee_type_id: e.target.value })}
                  className="w-full h-10 px-3 rounded-md border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-brand-blue"
                  required
                >
                  <option value="">Pilih Pos Biaya</option>
                  {feeTypes.map((ft) => (
                    <option key={ft.id} value={ft.id}>
                      {ft.name} ({ft.code}) — {formatRupiah(ft.default_amount)}
                    </option>
                  ))}
                </select>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-semibold text-slate-700 uppercase mb-1">
                    Periode (YYYY-MM)
                  </label>
                  <Input
                    type="month"
                    value={bulkData.period}
                    onChange={(e) => setBulkData({ ...bulkData, period: e.target.value })}
                  />
                </div>
                <div>
                  <label className="block text-xs font-semibold text-slate-700 uppercase mb-1">
                    Jatuh Tempo
                  </label>
                  <Input
                    type="date"
                    value={bulkData.due_date}
                    onChange={(e) => setBulkData({ ...bulkData, due_date: e.target.value })}
                    required
                  />
                </div>
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-700 uppercase mb-1">
                  Sasaran Siswa / Rombel
                </label>
                <select
                  value={bulkData.class_id}
                  onChange={(e) => setBulkData({ ...bulkData, class_id: e.target.value })}
                  className="w-full h-10 px-3 rounded-md border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-brand-blue"
                >
                  <option value="">Semua Siswa Aktif Seluruh Sekolah</option>
                  {classes.map((cls) => (
                    <option key={cls.id} value={cls.id}>
                      Khusus Rombel: {cls.name}
                    </option>
                  ))}
                </select>
              </div>

              <div className="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                <Button
                  type="button"
                  variant="outline"
                  onClick={() => setIsBulkModalOpen(false)}
                  disabled={isBulkSubmitting}
                >
                  Tutup
                </Button>
                <Button
                  type="submit"
                  disabled={isBulkSubmitting}
                  className="bg-brand-blue hover:bg-brand-blue/90"
                >
                  {isBulkSubmitting ? 'Memproses...' : 'Generate Tagihan'}
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Modal Detail Tagihan */}
      {selectedInvoice && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="bg-white rounded-xl shadow-xl w-full max-w-lg overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            <div className="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
              <div>
                <h2 className="text-lg font-semibold text-slate-900">Rincian Faktur Tagihan</h2>
                <p className="text-xs font-mono text-slate-500">{selectedInvoice.invoice_number}</p>
              </div>
              <button
                onClick={() => setSelectedInvoice(null)}
                className="text-slate-400 hover:text-slate-600 p-1 rounded-lg"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            <div className="p-6 space-y-4">
              <div className="grid grid-cols-2 gap-4 text-xs bg-slate-50 p-3 rounded-lg border border-slate-100">
                <div>
                  <span className="text-slate-500 block">Nama Siswa:</span>
                  <span className="font-semibold text-slate-900">{selectedInvoice.student?.full_name}</span>
                </div>
                <div>
                  <span className="text-slate-500 block">NISN / Kelas:</span>
                  <span className="font-semibold text-slate-900">
                    {selectedInvoice.student?.nisn} • {selectedInvoice.student?.current_class?.name || 'Reguler'}
                  </span>
                </div>
                <div>
                  <span className="text-slate-500 block">Jatuh Tempo:</span>
                  <span className="font-medium text-slate-800">{selectedInvoice.due_date}</span>
                </div>
                <div>
                  <span className="text-slate-500 block">Status:</span>
                  <div>{renderStatusBadge(selectedInvoice.status)}</div>
                </div>
              </div>

              <div>
                <h4 className="text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                  Item Rincian Biaya
                </h4>
                <div className="border border-slate-200 rounded-lg overflow-hidden">
                  <table className="w-full text-xs text-left">
                    <thead className="bg-slate-50 border-b border-slate-200 text-slate-600">
                      <tr>
                        <th className="px-3 py-2">Deskripsi</th>
                        <th className="px-3 py-2 text-right">Nominal</th>
                        <th className="px-3 py-2 text-right">Diskon</th>
                        <th className="px-3 py-2 text-right">Net</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                      {selectedInvoice.items?.map((it) => (
                        <tr key={it.id}>
                          <td className="px-3 py-2 text-slate-800">{it.description}</td>
                          <td className="px-3 py-2 text-right text-slate-600">{formatRupiah(it.amount)}</td>
                          <td className="px-3 py-2 text-right text-slate-500">
                            {it.discount ? `-${formatRupiah(it.discount)}` : '-'}
                          </td>
                          <td className="px-3 py-2 text-right font-medium text-slate-900">
                            {formatRupiah(it.amount - (it.discount || 0))}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                    <tfoot className="bg-slate-50 border-t border-slate-200 font-semibold text-slate-900">
                      <tr>
                        <td colSpan={3} className="px-3 py-2 text-right">Total:</td>
                        <td className="px-3 py-2 text-right">{formatRupiah(selectedInvoice.total_amount)}</td>
                      </tr>
                      <tr>
                        <td colSpan={3} className="px-3 py-2 text-right text-emerald-700">Sudah Terbayar:</td>
                        <td className="px-3 py-2 text-right text-emerald-700">{formatRupiah(selectedInvoice.paid_amount)}</td>
                      </tr>
                      <tr>
                        <td colSpan={3} className="px-3 py-2 text-right text-rose-700">Sisa Tagihan:</td>
                        <td className="px-3 py-2 text-right text-rose-700">{formatRupiah(selectedInvoice.remaining_amount)}</td>
                      </tr>
                    </tfoot>
                  </table>
                </div>
              </div>

              {selectedInvoice.payments && selectedInvoice.payments.length > 0 && (
                <div>
                  <h4 className="text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                    Riwayat Pembayaran
                  </h4>
                  <div className="space-y-2">
                    {selectedInvoice.payments.map((p) => (
                      <div
                        key={p.id}
                        className="p-2.5 rounded-lg border border-slate-200 text-xs flex items-center justify-between"
                      >
                        <div>
                          <div className="font-semibold text-slate-900">
                            {formatRupiah(p.amount)} • {p.channel}
                          </div>
                          <div className="text-slate-500 font-mono text-[11px]">
                            Ref: {p.external_reference}
                          </div>
                          <div className="text-slate-500">
                            Tanggal: {p.payment_date} {p.receipt_number && `• No KWT: ${p.receipt_number}`}
                          </div>
                        </div>
                        <Badge
                          variant="outline"
                          className={
                            p.status === 'terverifikasi'
                              ? 'text-emerald-700 bg-emerald-50 border-emerald-200'
                              : p.status === 'menunggu_verifikasi'
                              ? 'text-amber-700 bg-amber-50 border-amber-200'
                              : 'text-rose-700 bg-rose-50 border-rose-200'
                          }
                        >
                          {p.status}
                        </Badge>
                      </div>
                    ))}
                  </div>
                </div>
              )}

              <div className="flex justify-end pt-4 border-t border-slate-100">
                <Button variant="outline" size="sm" onClick={() => setSelectedInvoice(null)}>
                  Tutup
                </Button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Modal Konfirmasi Pembatalan Tagihan */}
      {cancellingInvoice && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            <div className="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
              <h2 className="text-lg font-semibold text-slate-900">Batalkan Faktur Tagihan</h2>
              <button
                onClick={() => setCancellingInvoice(null)}
                className="text-slate-400 hover:text-slate-600 p-1 rounded-lg"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            <div className="p-6 space-y-4">
              <p className="text-sm text-slate-600">
                Apakah Anda yakin ingin membatalkan faktur{' '}
                <strong className="font-mono text-slate-900">{cancellingInvoice.invoice_number}</strong>?
                Tindakan ini akan tercatat dalam audit log.
              </p>

              <div>
                <label className="block text-xs font-semibold text-slate-700 uppercase mb-1">
                  Alasan Pembatalan
                </label>
                <Input
                  placeholder="Mis: Koreksi salah kelas, beasiswa khusus, dll."
                  value={cancelNotes}
                  onChange={(e) => setCancelNotes(e.target.value)}
                />
              </div>

              <div className="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                <Button
                  type="button"
                  variant="outline"
                  onClick={() => setCancellingInvoice(null)}
                  disabled={isCancelling}
                >
                  Batal
                </Button>
                <Button
                  type="button"
                  onClick={handleConfirmCancel}
                  disabled={isCancelling}
                  className="bg-rose-600 hover:bg-rose-700 text-white"
                >
                  {isCancelling ? 'Membatalkan...' : 'Ya, Batalkan Tagihan'}
                </Button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
