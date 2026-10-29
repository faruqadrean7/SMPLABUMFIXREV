'use client';

import React, { useEffect, useState, useCallback } from 'react';
import {
  CheckSquare,
  RefreshCw,
  Search,
  CheckCircle2,
  XCircle,
  AlertTriangle,
  Clock,
  Printer,
  X,
  FileCheck,
  AlertCircle,
  FileText,
  Ban,
  ExternalLink,
  Filter,
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
import { SchoolClassItem } from '@/types/master';
import { Payment, PaymentStatus, OfficialReceipt } from '@/types/finance';

export default function PaymentsAdminPage() {
  const [payments, setPayments] = useState<Payment[]>([]);
  const [classes, setClasses] = useState<SchoolClassItem[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Filters
  const [statusFilter, setStatusFilter] = useState<string>('menunggu_verifikasi');
  const [classFilter, setClassFilter] = useState<string>('all');
  const [dateFilter, setDateFilter] = useState<string>('');
  const [searchQuery, setSearchQuery] = useState('');

  // Rejection modal
  const [rejectingPayment, setRejectingPayment] = useState<Payment | null>(null);
  const [rejectionReason, setRejectionReason] = useState('');
  const [isRejecting, setIsRejecting] = useState(false);

  // Void modal
  const [voidingPayment, setVoidingPayment] = useState<Payment | null>(null);
  const [voidReason, setVoidReason] = useState('');
  const [isVoiding, setIsVoiding] = useState(false);

  // Receipt Modal
  const [receiptData, setReceiptData] = useState<OfficialReceipt | null>(null);
  const [isLoadingReceipt, setIsLoadingReceipt] = useState(false);

  // Proof Modal
  const [proofUrl, setProofUrl] = useState<string | null>(null);

  const loadData = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    try {
      const [res, clsRes] = await Promise.all([
        financeApi.getPayments({
          status: statusFilter !== 'all' ? statusFilter : undefined,
          class_id: classFilter !== 'all' ? classFilter : undefined,
          date: dateFilter || undefined,
          search: searchQuery || undefined,
        }),
        classesApi.list({ limit: 100 }).catch(() => ({ data: [] })),
      ]);
      setPayments(res.data || []);
      setClasses(clsRes.data || []);
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Gagal memuat antrean pembayaran.';
      setError(message);
    } finally {
      setIsLoading(false);
    }
  }, [statusFilter, classFilter, dateFilter, searchQuery]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const handleVerify = async (paymentId: string) => {
    if (!window.confirm('Terima slip pembayaran ini? Status tagihan akan dihitung ulang dan kuitansi resmi diterbitkan.')) return;
    try {
      await financeApi.verifyPayment(paymentId);
      await loadData();
    } catch (err: unknown) {
      alert(err instanceof Error ? err.message : 'Gagal memverifikasi slip pembayaran.');
    }
  };

  const handleConfirmReject = async () => {
    if (!rejectingPayment) return;
    if (!rejectionReason.trim()) {
      alert('Alasan penolakan wajib diisi.');
      return;
    }
    setIsRejecting(true);
    try {
      await financeApi.rejectPayment(rejectingPayment.id, rejectionReason);
      setRejectingPayment(null);
      setRejectionReason('');
      await loadData();
    } catch (err: unknown) {
      alert(err instanceof Error ? err.message : 'Gagal menolak slip pembayaran.');
    } finally {
      setIsRejecting(false);
    }
  };

  const handleConfirmVoid = async () => {
    if (!voidingPayment) return;
    if (!voidReason.trim()) {
      alert('Alasan pembatalan (void) kuitansi wajib diisi.');
      return;
    }
    setIsVoiding(true);
    try {
      await financeApi.voidPayment(voidingPayment.id, voidReason);
      setVoidingPayment(null);
      setVoidReason('');
      await loadData();
    } catch (err: unknown) {
      alert(err instanceof Error ? err.message : 'Gagal membatalkan kuitansi (void).');
    } finally {
      setIsVoiding(false);
    }
  };

  const handleViewReceipt = async (paymentId: string) => {
    setIsLoadingReceipt(true);
    try {
      const receipt = await financeApi.getPaymentReceipt(paymentId);
      setReceiptData(receipt);
    } catch (err: unknown) {
      alert(err instanceof Error ? err.message : 'Gagal memuat kuitansi resmi.');
    } finally {
      setIsLoadingReceipt(false);
    }
  };

  const renderStatusBadge = (status: PaymentStatus) => {
    switch (status) {
      case 'terverifikasi':
        return (
          <Badge className="bg-emerald-100 text-emerald-800 border-emerald-200 flex items-center gap-1 w-fit">
            <CheckCircle2 className="h-3 w-3" />
            Terverifikasi
          </Badge>
        );
      case 'menunggu_verifikasi':
        return (
          <Badge className="bg-amber-100 text-amber-800 border-amber-200 flex items-center gap-1 w-fit">
            <Clock className="h-3 w-3" />
            Menunggu
          </Badge>
        );
      case 'ditolak':
        return (
          <Badge className="bg-rose-100 text-rose-800 border-rose-200 flex items-center gap-1 w-fit">
            <XCircle className="h-3 w-3" />
            Ditolak
          </Badge>
        );
      case 'void':
        return (
          <Badge className="bg-slate-200 text-slate-700 border-slate-300 flex items-center gap-1 w-fit">
            <Ban className="h-3 w-3" />
            Void (Batal)
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
            <CheckSquare className="h-6 w-6 text-brand-blue" />
            Antrean Verifikasi Slip Pembayaran
          </h1>
          <p className="text-sm text-slate-500 mt-1">
            Verifikasi bukti slip transfer pihak ketiga, validasi nomor referensi transaksi, dan terbitkan kuitansi sah.
          </p>
        </div>
        <div className="flex items-center gap-2">
          <Button variant="outline" size="sm" onClick={loadData} disabled={isLoading} className="gap-2">
            <RefreshCw className={`h-4 w-4 ${isLoading ? 'animate-spin' : ''}`} />
            Segarkan
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
                placeholder="Cari No. Referensi / Siswa..."
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
                <option value="menunggu_verifikasi">Menunggu Verifikasi</option>
                <option value="terverifikasi">Sudah Terverifikasi</option>
                <option value="ditolak">Ditolak</option>
                <option value="void">Void / Dibatalkan</option>
                <option value="all">Semua Status</option>
              </select>
            </div>

            <div>
              <select
                value={classFilter}
                onChange={(e) => setClassFilter(e.target.value)}
                className="w-full h-10 px-3 rounded-md border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-brand-blue"
              >
                <option value="all">Semua Rombel/Kelas</option>
                {classes.map((c) => (
                  <option key={c.id} value={c.id}>
                    {c.name}
                  </option>
                ))}
              </select>
            </div>

            <div>
              <Input
                type="date"
                value={dateFilter}
                onChange={(e) => setDateFilter(e.target.value)}
                title="Filter Tanggal Bayar"
              />
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Tabel Pembayaran */}
      {isLoading ? (
        <Card className="p-12">
          <LoadingSpinner label="Memuat antrean verifikasi..." size="lg" />
        </Card>
      ) : error ? (
        <ErrorState
          title="Gagal Memuat Pembayaran"
          message={error}
          onRetry={loadData}
        />
      ) : payments.length === 0 ? (
        <Card className="p-8">
          <EmptyState
            title="Tidak Ada Slip Pembayaran"
            description="Tidak ada antrean slip pembayaran yang cocok dengan filter saat ini."
          />
        </Card>
      ) : (
        <Card>
          <div className="overflow-x-auto">
            <table className="w-full text-sm text-left">
              <thead className="bg-slate-50 border-b border-slate-200 text-xs uppercase font-semibold text-slate-600">
                <tr>
                  <th className="px-4 py-3">Faktur / Siswa</th>
                  <th className="px-4 py-3">No. Referensi (Ref ID)</th>
                  <th className="px-4 py-3">Kanal Pembayaran</th>
                  <th className="px-4 py-3 text-right">Jumlah Bayar</th>
                  <th className="px-4 py-3">Tanggal Bayar</th>
                  <th className="px-4 py-3 text-center">Bukti Slip</th>
                  <th className="px-4 py-3 text-center">Status</th>
                  <th className="px-4 py-3">No. Kuitansi</th>
                  <th className="px-4 py-3 text-right">Aksi</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {payments.map((p) => (
                  <tr key={p.id} className="hover:bg-slate-50/50">
                    <td className="px-4 py-3">
                      <div className="font-semibold text-slate-900">
                        {p.invoice?.student?.full_name || 'Siswa'}
                      </div>
                      <div className="text-xs text-slate-500 font-mono">
                        {p.invoice?.invoice_number}
                      </div>
                    </td>
                    <td className="px-4 py-3 font-mono font-bold text-xs text-brand-blue">
                      {p.external_reference}
                    </td>
                    <td className="px-4 py-3 text-xs text-slate-700">
                      <span className="font-medium">{p.channel}</span>
                    </td>
                    <td className="px-4 py-3 text-right font-bold text-slate-900">
                      {formatRupiah(p.amount)}
                    </td>
                    <td className="px-4 py-3 text-slate-600 text-xs">{p.payment_date}</td>
                    <td className="px-4 py-3 text-center">
                      {p.proof_file_url ? (
                        <Button
                          variant="outline"
                          size="sm"
                          onClick={() => setProofUrl(p.proof_file_url!)}
                          className="h-7 text-xs gap-1 border-blue-200 text-brand-blue hover:bg-blue-50"
                        >
                          <ExternalLink className="h-3 w-3" />
                          Lihat Slip
                        </Button>
                      ) : (
                        <span className="text-xs text-slate-400 italic">Tanpa Berkas</span>
                      )}
                    </td>
                    <td className="px-4 py-3 text-center">
                      {renderStatusBadge(p.status)}
                      {p.rejection_reason && (
                        <div className="text-[10px] text-rose-600 mt-1 max-w-[150px] truncate" title={p.rejection_reason}>
                          {p.rejection_reason}
                        </div>
                      )}
                    </td>
                    <td className="px-4 py-3 font-mono text-xs text-slate-700">
                      {p.receipt_number ? (
                        <span className="text-emerald-700 font-semibold">{p.receipt_number}</span>
                      ) : (
                        <span className="text-slate-400">-</span>
                      )}
                    </td>
                    <td className="px-4 py-3 text-right space-x-1">
                      {p.status === 'menunggu_verifikasi' && (
                        <>
                          <Button
                            size="sm"
                            onClick={() => handleVerify(p.id)}
                            className="h-8 bg-emerald-600 hover:bg-emerald-700 text-white text-xs gap-1"
                          >
                            <CheckCircle2 className="h-3.5 w-3.5" />
                            Terima Slip
                          </Button>
                          <Button
                            variant="outline"
                            size="sm"
                            onClick={() => {
                              setRejectingPayment(p);
                              setRejectionReason('');
                            }}
                            className="h-8 border-rose-200 text-rose-700 hover:bg-rose-50 text-xs gap-1"
                          >
                            <XCircle className="h-3.5 w-3.5" />
                            Tolak
                          </Button>
                        </>
                      )}

                      {p.status === 'terverifikasi' && (
                        <>
                          <Button
                            variant="outline"
                            size="sm"
                            onClick={() => handleViewReceipt(p.id)}
                            disabled={isLoadingReceipt}
                            className="h-8 border-slate-300 text-slate-700 hover:bg-slate-50 text-xs gap-1"
                          >
                            <Printer className="h-3.5 w-3.5" />
                            Kuitansi
                          </Button>
                          <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => {
                              setVoidingPayment(p);
                              setVoidReason('');
                            }}
                            className="h-8 text-slate-400 hover:text-rose-700 text-xs"
                            title="Batalkan (Void) Kuitansi"
                          >
                            Void
                          </Button>
                        </>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </Card>
      )}

      {/* Modal Tolak Pembayaran */}
      {rejectingPayment && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            <div className="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
              <h2 className="text-lg font-semibold text-slate-900">Tolak Slip Pembayaran</h2>
              <button
                onClick={() => setRejectingPayment(null)}
                className="text-slate-400 hover:text-slate-600 p-1 rounded-lg"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            <div className="p-6 space-y-4">
              <p className="text-sm text-slate-600">
                Tolak slip pembayaran sejumlah{' '}
                <strong>{formatRupiah(rejectingPayment.amount)}</strong> dengan Ref ID:{' '}
                <span className="font-mono font-bold text-slate-900">{rejectingPayment.external_reference}</span>?
              </p>

              <div>
                <label className="block text-xs font-semibold text-slate-700 uppercase mb-1">
                  Alasan Penolakan <span className="text-red-500">*</span>
                </label>
                <Input
                  placeholder="Mis: Foto slip buram / nominal tidak sesuai / mutasi tidak ditemukan"
                  value={rejectionReason}
                  onChange={(e) => setRejectionReason(e.target.value)}
                  required
                />
              </div>

              <div className="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                <Button
                  type="button"
                  variant="outline"
                  onClick={() => setRejectingPayment(null)}
                  disabled={isRejecting}
                >
                  Batal
                </Button>
                <Button
                  type="button"
                  onClick={handleConfirmReject}
                  disabled={isRejecting}
                  className="bg-rose-600 hover:bg-rose-700 text-white"
                >
                  {isRejecting ? 'Menolak...' : 'Ya, Tolak Slip'}
                </Button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Modal Void Pembayaran Terverifikasi */}
      {voidingPayment && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            <div className="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
              <h2 className="text-lg font-semibold text-slate-900">Batalkan Kuitansi (Void)</h2>
              <button
                onClick={() => setVoidingPayment(null)}
                className="text-slate-400 hover:text-slate-600 p-1 rounded-lg"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            <div className="p-6 space-y-4">
              <div className="p-3 bg-amber-50 text-amber-800 text-xs rounded-lg flex items-center gap-2">
                <AlertCircle className="h-4 w-4 shrink-0" />
                <span>
                  Slip yang sudah terverifikasi tidak boleh dihapus. Tindakan VOID akan membatalkan
                  kuitansi resmi dan dicatat dalam audit log keamanan.
                </span>
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-700 uppercase mb-1">
                  Alasan Void Kuitansi <span className="text-red-500">*</span>
                </label>
                <Input
                  placeholder="Mis: Transfer dana ditarik kembali / salah input verifikasi"
                  value={voidReason}
                  onChange={(e) => setVoidReason(e.target.value)}
                  required
                />
              </div>

              <div className="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                <Button
                  type="button"
                  variant="outline"
                  onClick={() => setVoidingPayment(null)}
                  disabled={isVoiding}
                >
                  Batal
                </Button>
                <Button
                  type="button"
                  onClick={handleConfirmVoid}
                  disabled={isVoiding}
                  className="bg-slate-800 hover:bg-slate-900 text-white"
                >
                  {isVoiding ? 'Memproses...' : 'Proses Void'}
                </Button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Modal Kuitansi Resmi */}
      {receiptData && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="bg-white rounded-xl shadow-xl w-full max-w-lg overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            <div className="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
              <div>
                <h2 className="text-lg font-semibold text-slate-900 flex items-center gap-2">
                  <FileCheck className="h-5 w-5 text-emerald-600" />
                  Kuitansi Pembayaran Sah
                </h2>
                <p className="text-xs font-mono text-slate-500">{receiptData.receipt_number}</p>
              </div>
              <button
                onClick={() => setReceiptData(null)}
                className="text-slate-400 hover:text-slate-600 p-1 rounded-lg"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            <div className="p-6 space-y-4 text-xs">
              <div className="text-center pb-4 border-b border-slate-200">
                <h3 className="font-bold text-base text-slate-900 uppercase">
                  {receiptData.school_info.name}
                </h3>
                <p className="text-slate-500">{receiptData.school_info.address}</p>
                <p className="text-slate-500">Telp: {receiptData.school_info.phone}</p>
              </div>

              <div className="grid grid-cols-2 gap-3 bg-slate-50 p-3 rounded-lg border border-slate-100">
                <div>
                  <span className="text-slate-500 block">Telah Diterima Dari:</span>
                  <span className="font-bold text-slate-900">{receiptData.student_name}</span>
                  <span className="text-slate-500 block">
                    NISN: {receiptData.student_nisn} • Kelas: {receiptData.class_name}
                  </span>
                </div>
                <div>
                  <span className="text-slate-500 block">Nomor Kuitansi:</span>
                  <span className="font-mono font-bold text-emerald-700">{receiptData.receipt_number}</span>
                  <span className="text-slate-500 block">Faktur: {receiptData.invoice_number}</span>
                </div>
              </div>

              <div className="p-3 bg-blue-50/50 rounded-lg border border-blue-100 space-y-1">
                <span className="text-slate-500 block">Jumlah Uang:</span>
                <div className="text-lg font-bold text-slate-900">{formatRupiah(receiptData.amount)}</div>
                <div className="italic text-slate-700 font-medium capitalize">
                  Terbilang: {receiptData.terbilang}
                </div>
              </div>

              <div className="grid grid-cols-2 gap-4 pt-3 border-t border-slate-100">
                <div>
                  <span className="text-slate-500 block">Kanal / Ref ID:</span>
                  <span className="font-semibold uppercase">{receiptData.channel || receiptData.payment_method}</span>
                  {receiptData.external_reference && (
                    <span className="text-slate-500 block font-mono text-[11px] mt-0.5">
                      Ref: {receiptData.external_reference}
                    </span>
                  )}
                  <span className="text-slate-500 block mt-1">Tanggal:</span>
                  <span>{receiptData.payment_date}</span>
                </div>
                <div className="text-right">
                  <span className="text-slate-500 block">Diverifikasi Oleh:</span>
                  <span className="font-semibold text-slate-900">{receiptData.verifier_name}</span>
                  <span className="text-slate-400 block text-[10px] mt-2">
                    Dokumen ini sah dan diterbitkan secara digital oleh Sistem Keuangan SISTLABUM.
                  </span>
                </div>
              </div>

              <div className="flex justify-end gap-2 pt-4 border-t border-slate-100">
                <Button variant="outline" size="sm" onClick={() => window.print()} className="gap-1.5">
                  <Printer className="h-4 w-4" />
                  Cetak Kuitansi
                </Button>
                <Button size="sm" onClick={() => setReceiptData(null)}>
                  Tutup
                </Button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Modal Lihat Bukti Slip */}
      {proofUrl && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
          <div className="bg-white rounded-xl shadow-xl w-full max-w-lg overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            <div className="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
              <h3 className="font-semibold text-slate-900">Bukti Slip Pembayaran</h3>
              <button
                onClick={() => setProofUrl(null)}
                className="text-slate-400 hover:text-slate-600 p-1 rounded-lg"
              >
                <X className="h-5 w-5" />
              </button>
            </div>
            <div className="p-4 flex items-center justify-center bg-slate-100 max-h-[70vh] overflow-auto">
              {proofUrl.endsWith('.pdf') ? (
                <iframe src={proofUrl} className="w-full h-96 rounded-lg" title="Bukti PDF" />
              ) : (
                /* eslint-disable-next-line @next/next/no-img-element */
                <img
                  src={proofUrl}
                  alt="Bukti Transfer"
                  className="max-h-[60vh] max-w-full object-contain rounded-lg shadow-sm"
                />
              )}
            </div>
            <div className="p-3 border-t border-slate-100 flex justify-end">
              <Button size="sm" variant="outline" onClick={() => setProofUrl(null)}>
                Tutup
              </Button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
