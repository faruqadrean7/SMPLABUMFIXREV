'use client';

import React, { useEffect, useState, useCallback } from 'react';
import Link from 'next/link';
import {
  CreditCard,
  RefreshCw,
  Printer,
  FileCheck,
  CheckCircle2,
  Clock,
  AlertTriangle,
  ArrowLeft,
  X,
  Copy,
  Check,
  ExternalLink,
  Info,
  AlertCircle,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { LoadingSpinner } from '@/components/shared/loading-spinner';
import { EmptyState } from '@/components/shared/empty-state';
import { ErrorState } from '@/components/shared/error-state';
import { financeApi, formatRupiah } from '@/features/finance/finance-api';
import { Invoice, OfficialReceipt, InvoiceStatus, PaymentProviderSetting } from '@/types/finance';

export default function StudentInvoicesPage() {
  const [invoices, setInvoices] = useState<Invoice[]>([]);
  const [providers, setProviders] = useState<PaymentProviderSetting[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [copiedVa, setCopiedVa] = useState<string | null>(null);

  // Kuitansi Modal
  const [receiptData, setReceiptData] = useState<OfficialReceipt | null>(null);
  const [isLoadingReceipt, setIsLoadingReceipt] = useState(false);

  const loadData = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    try {
      const [invData, provData] = await Promise.all([
        financeApi.getMyInvoices(),
        financeApi.getPortalPaymentProviders(),
      ]);
      setInvoices(invData || []);
      setProviders(provData || []);
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Gagal memuat data tagihan dan pembayaran.';
      setError(message);
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const handleCopyVa = (va: string) => {
    navigator.clipboard.writeText(va);
    setCopiedVa(va);
    setTimeout(() => setCopiedVa(null), 2000);
  };

  const handleViewReceipt = async (paymentId: string) => {
    setIsLoadingReceipt(true);
    try {
      const receipt = await financeApi.getPortalPaymentReceipt(paymentId);
      setReceiptData(receipt);
    } catch (err: unknown) {
      alert(err instanceof Error ? err.message : 'Gagal memuat kuitansi.');
    } finally {
      setIsLoadingReceipt(false);
    }
  };

  const renderStatusBadge = (status: InvoiceStatus) => {
    switch (status) {
      case 'lunas':
        return (
          <Badge className="bg-emerald-100 text-emerald-800 border-emerald-200 flex items-center gap-1 w-fit">
            <CheckCircle2 className="h-3 w-3" />
            Lunas
          </Badge>
        );
      case 'sebagian':
        return (
          <Badge className="bg-amber-100 text-amber-800 border-amber-200 flex items-center gap-1 w-fit">
            <Clock className="h-3 w-3" />
            Sebagian
          </Badge>
        );
      case 'belum_bayar':
        return (
          <Badge className="bg-rose-100 text-rose-800 border-rose-200 flex items-center gap-1 w-fit">
            <AlertTriangle className="h-3 w-3" />
            Belum Bayar
          </Badge>
        );
      default:
        return <Badge variant="outline">{status}</Badge>;
    }
  };

  return (
    <div className="space-y-6 max-w-5xl mx-auto">
      {/* Breadcrumb & Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <div className="flex items-center gap-2 mb-2">
            <Link
              href="/portal/siswa"
              className="inline-flex items-center gap-1.5 text-xs text-slate-500 hover:text-slate-900 font-medium transition-colors"
            >
              <ArrowLeft className="h-4 w-4" />
              <span>Kembali ke Beranda Siswa</span>
            </Link>
          </div>
          <h1 className="text-2xl font-bold text-slate-900 tracking-tight flex items-center gap-2">
            <CreditCard className="h-6 w-6 text-brand-blue" />
            Status Tagihan & Keuangan Siswa
          </h1>
          <p className="text-sm text-slate-500 mt-1">
            Informasi rincian faktur biaya pendidikan, nomor Virtual Account, dan riwayat kuitansi resmi.
          </p>
        </div>
        <div className="flex items-center gap-2">
          <Button variant="outline" size="sm" onClick={loadData} disabled={isLoading} className="gap-2">
            <RefreshCw className={`h-4 w-4 ${isLoading ? 'animate-spin' : ''}`} />
            Segarkan
          </Button>
        </div>
      </div>

      {/* Petunjuk Cara Pembayaran (Pihak Ketiga) */}
      {providers.length > 0 && (
        <Card className="border-blue-200 bg-blue-50/30 shadow-xs">
          <CardHeader className="pb-3">
            <CardTitle className="text-sm font-bold text-slate-900 flex items-center gap-2">
              <Info className="h-4 w-4 text-brand-blue" />
              Petunjuk Cara Pembayaran (Pihak Ketiga / Bank)
            </CardTitle>
            <CardDescription className="text-xs text-slate-600">
              Pembayaran dilakukan di luar sistem melalui Virtual Account atau kanal pihak ketiga. Simpan bukti transfer untuk diunggah oleh orang tua/wali melalui portal orang tua.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
              {providers.map((p) => (
                <div key={p.id} className="p-3 bg-white rounded-lg border border-blue-100 shadow-2xs">
                  <div className="flex items-center justify-between mb-1.5">
                    <span className="font-bold text-slate-900 text-sm">{p.name}</span>
                    {p.info_url && (
                      <a
                        href={p.info_url}
                        target="_blank"
                        rel="noreferrer"
                        className="text-brand-blue hover:underline inline-flex items-center gap-1 text-[11px]"
                      >
                        Bantuan <ExternalLink className="h-3 w-3" />
                      </a>
                    )}
                  </div>
                  <div className="text-slate-700 whitespace-pre-line leading-relaxed font-sans">
                    {p.instructions}
                  </div>
                </div>
              ))}
            </div>
          </CardContent>
        </Card>
      )}

      {/* Konten Tagihan */}
      {isLoading ? (
        <Card className="p-12">
          <LoadingSpinner label="Memuat informasi tagihan..." size="lg" />
        </Card>
      ) : error ? (
        <ErrorState
          title="Gagal Memuat Tagihan"
          message={error}
          onRetry={loadData}
        />
      ) : invoices.length === 0 ? (
        <Card className="p-8">
          <EmptyState
            title="Tidak Ada Tagihan Aktif"
            description="Tidak ditemukan tagihan biaya pendidikan untuk akun Anda saat ini."
          />
        </Card>
      ) : (
        <div className="space-y-4">
          {invoices.map((inv) => (
            <Card key={inv.id} className="overflow-hidden border-slate-200 shadow-xs">
              <div className="p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-100 bg-slate-50/50">
                <div>
                  <div className="flex items-center gap-2">
                    <span className="font-mono font-bold text-slate-900 text-sm">{inv.invoice_number}</span>
                    {renderStatusBadge(inv.status)}
                  </div>
                  <div className="text-xs text-slate-500 mt-1">
                    Periode: {inv.period || 'Reguler'} • Jatuh Tempo: <strong>{inv.due_date}</strong>
                  </div>

                  {inv.va_number && (
                    <div className="mt-2 flex items-center gap-2">
                      <span className="text-xs text-slate-600 font-semibold">Nomor Virtual Account (VA):</span>
                      <span className="font-mono font-bold text-sm bg-blue-50 text-brand-blue px-2.5 py-0.5 rounded-md border border-blue-200">
                        {inv.va_number}
                      </span>
                      <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => handleCopyVa(inv.va_number!)}
                        className="h-7 px-2 text-xs text-slate-600 gap-1 hover:text-brand-blue"
                        title="Salin Nomor VA"
                      >
                        {copiedVa === inv.va_number ? (
                          <>
                            <Check className="h-3 w-3 text-emerald-600" />
                            <span className="text-emerald-600 font-medium">Tersalin</span>
                          </>
                        ) : (
                          <>
                            <Copy className="h-3 w-3" />
                            <span>Salin</span>
                          </>
                        )}
                      </Button>
                    </div>
                  )}
                </div>

                <div className="text-right">
                  <span className="text-[11px] text-slate-400 block font-medium">Sisa Tagihan</span>
                  <span
                    className={`text-lg font-black ${
                      inv.remaining_amount > 0 ? 'text-rose-600' : 'text-emerald-600'
                    }`}
                  >
                    {formatRupiah(inv.remaining_amount)}
                  </span>
                </div>
              </div>

              {/* Rincian Pos Biaya */}
              <div className="p-4 sm:p-5">
                <div className="text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                  Rincian Biaya
                </div>
                <div className="divide-y divide-slate-100 border border-slate-100 rounded-lg p-3 bg-slate-50/30">
                  {inv.items?.map((it) => (
                    <div key={it.id} className="py-2 flex items-center justify-between text-xs">
                      <span className="text-slate-700 font-medium">{it.description}</span>
                      <span className="text-slate-900 font-semibold">{formatRupiah(it.amount)}</span>
                    </div>
                  ))}
                  <div className="pt-2 flex items-center justify-between text-xs font-bold text-slate-900">
                    <span>Total Tagihan:</span>
                    <span>{formatRupiah(inv.total_amount)}</span>
                  </div>
                  {inv.paid_amount > 0 && (
                    <div className="pt-1 flex items-center justify-between text-xs font-semibold text-emerald-700">
                      <span>Sudah Terbayar:</span>
                      <span>{formatRupiah(inv.paid_amount)}</span>
                    </div>
                  )}
                </div>

                {/* Riwayat Pembayaran & Kuitansi */}
                {inv.payments && inv.payments.length > 0 && (
                  <div className="mt-4 pt-4 border-t border-slate-100">
                    <div className="text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                      Riwayat Pembayaran & Kuitansi
                    </div>
                    <div className="space-y-2">
                      {inv.payments.map((p) => (
                        <div
                          key={p.id}
                          className="p-3 bg-white rounded-xl border border-slate-200 text-xs flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2"
                        >
                          <div>
                            <div className="font-bold text-slate-900">
                              {formatRupiah(p.amount)} •{' '}
                              <span className="text-slate-700 font-normal">
                                {p.channel}
                              </span>
                            </div>
                            <div className="text-slate-500 mt-0.5 space-x-2">
                              <span>Tanggal: {p.payment_date}</span>
                              <span className="font-mono text-slate-700 font-medium">Ref: {p.external_reference}</span>
                              {p.receipt_number && (
                                <span className="font-mono text-emerald-700 font-semibold">
                                  • No KWT: {p.receipt_number}
                                </span>
                              )}
                            </div>
                            {p.rejection_reason && (
                              <div className="p-2 bg-rose-50 text-rose-700 rounded-md mt-2 border border-rose-100 flex items-start gap-1.5">
                                <AlertCircle className="h-3.5 w-3.5 shrink-0 mt-0.5" />
                                <div>
                                  <span className="font-semibold">Alasan Ditolak: </span>
                                  {p.rejection_reason}
                                </div>
                              </div>
                            )}
                          </div>

                          <div className="flex items-center gap-2">
                            <Badge
                              variant="outline"
                              className={
                                p.status === 'terverifikasi'
                                  ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                                  : p.status === 'menunggu_verifikasi'
                                  ? 'bg-amber-50 text-amber-700 border-amber-200'
                                  : 'bg-rose-50 text-rose-700 border-rose-200'
                              }
                            >
                              {p.status === 'terverifikasi'
                                ? 'Terverifikasi'
                                : p.status === 'menunggu_verifikasi'
                                ? 'Menunggu Verifikasi'
                                : p.status === 'void'
                                ? 'Void'
                                : 'Ditolak'}
                            </Badge>

                            {p.status === 'terverifikasi' && (
                              <Button
                                variant="outline"
                                size="sm"
                                onClick={() => handleViewReceipt(p.id)}
                                disabled={isLoadingReceipt}
                                className="h-7 text-xs gap-1 border-slate-300"
                              >
                                <Printer className="h-3.5 w-3.5" />
                                Kuitansi
                              </Button>
                            )}
                          </div>
                        </div>
                      ))}
                    </div>
                  </div>
                )}
              </div>
            </Card>
          ))}
        </div>
      )}

      {/* Modal Kuitansi Resmi */}
      {receiptData && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="bg-white rounded-xl shadow-xl w-full max-w-lg overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            <div className="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
              <div>
                <h3 className="font-semibold text-slate-900 flex items-center gap-2">
                  <FileCheck className="h-5 w-5 text-emerald-600" />
                  Kuitansi Pembayaran Sah
                </h3>
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
              <div className="text-center pb-3 border-b border-slate-200">
                <div className="font-bold text-base text-slate-900 uppercase">
                  {receiptData.school_info.name}
                </div>
                <div className="text-slate-500">{receiptData.school_info.address}</div>
                <div className="text-slate-500">Telp: {receiptData.school_info.phone}</div>
              </div>

              <div className="bg-slate-50 p-3 rounded-lg border border-slate-100 space-y-1">
                <div>
                  <span className="text-slate-500">Nama Siswa: </span>
                  <span className="font-bold text-slate-900">{receiptData.student_name}</span>
                </div>
                <div>
                  <span className="text-slate-500">NISN / Kelas: </span>
                  <span className="font-medium text-slate-800">
                    {receiptData.student_nisn} • {receiptData.class_name}
                  </span>
                </div>
                <div>
                  <span className="text-slate-500">Faktur Tagihan: </span>
                  <span className="font-mono">{receiptData.invoice_number}</span>
                </div>
              </div>

              <div className="p-3 bg-blue-50/50 rounded-lg border border-blue-100 space-y-1">
                <span className="text-slate-500 block">Jumlah Uang:</span>
                <div className="text-lg font-bold text-slate-900">{formatRupiah(receiptData.amount)}</div>
                <div className="italic text-slate-700 font-medium capitalize">
                  Terbilang: {receiptData.terbilang}
                </div>
              </div>

              <div className="grid grid-cols-2 gap-4 pt-2">
                <div>
                  <span className="text-slate-500 block">Kanal / Bank:</span>
                  <span className="font-semibold">{receiptData.channel}</span>
                  <span className="text-slate-500 block mt-1">Ref ID / Tanggal:</span>
                  <span className="font-mono text-slate-800">{receiptData.external_reference} • {receiptData.payment_date}</span>
                </div>
                <div className="text-right">
                  <span className="text-slate-500 block">Diverifikasi Oleh:</span>
                  <span className="font-semibold text-slate-900">{receiptData.verifier_name}</span>
                  <span className="text-slate-400 block text-[10px] mt-1">
                    Dokumen sah diterbitkan secara elektronik oleh SISTLABUM.
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
    </div>
  );
}
