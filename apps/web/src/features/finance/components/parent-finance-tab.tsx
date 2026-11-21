'use client';

import React, { useEffect, useState, useCallback } from 'react';
import {
  CreditCard,
  CheckCircle2,
  Clock,
  AlertTriangle,
  Upload,
  Printer,
  FileCheck,
  AlertCircle,
  X,
  ExternalLink,
  BookOpen,
  Copy,
  Check,
  FileText,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { LoadingSpinner } from '@/components/shared/loading-spinner';
import { EmptyState } from '@/components/shared/empty-state';
import { ErrorState } from '@/components/shared/error-state';
import { toast } from '@/components/ui/toast';
import {
  financeApi,
  formatRupiah,
  validateProofFile,
} from '@/features/finance/finance-api';
import {
  Invoice,
  PaymentProviderSetting,
  OfficialReceipt,
  Payment,
} from '@/types/finance';

export function ParentFinanceTab({ childId }: { childId: string }) {
  const [invoices, setInvoices] = useState<Invoice[]>([]);
  const [paymentProviders, setPaymentProviders] = useState<PaymentProviderSetting[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Copy VA feedback
  const [copiedVa, setCopiedVa] = useState<string | null>(null);

  // Upload Slip Modal State
  const [selectedInvoice, setSelectedInvoice] = useState<Invoice | null>(null);
  const [payAmount, setPayAmount] = useState<number>(0);
  const [payChannel, setPayChannel] = useState<string>('Bank Jatim Virtual Account');
  const [extReference, setExtReference] = useState<string>('');
  const [payDate, setPayDate] = useState<string>(new Date().toISOString().slice(0, 10));
  const [proofFile, setProofFile] = useState<File | null>(null);
  const [fileError, setFileError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState<string | null>(null);
  const [submitSuccess, setSubmitSuccess] = useState<string | null>(null);

  // Official Receipt Modal
  const [receiptData, setReceiptData] = useState<OfficialReceipt | null>(null);
  const [isLoadingReceipt, setIsLoadingReceipt] = useState(false);

  const loadData = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    try {
      const [invData, providerData] = await Promise.all([
        financeApi.getChildInvoices(childId),
        financeApi.getPortalPaymentProviders().catch(() => []),
      ]);
      setInvoices(invData || []);
      setPaymentProviders(providerData || []);
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Gagal memuat data keuangan ananda.';
      setError(message);
    } finally {
      setIsLoading(false);
    }
  }, [childId]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const handleOpenUploadModal = (inv: Invoice) => {
    setSelectedInvoice(inv);
    setPayAmount(inv.remaining_amount);
    setPayChannel('Bank Jatim Virtual Account');
    setExtReference('');
    setPayDate(new Date().toISOString().slice(0, 10));
    setProofFile(null);
    setFileError(null);
    setSubmitError(null);
    setSubmitSuccess(null);
  };

  const handleCopyVa = (vaNumber: string) => {
    if (navigator.clipboard) {
      navigator.clipboard.writeText(vaNumber);
      setCopiedVa(vaNumber);
      setTimeout(() => setCopiedVa(null), 2000);
    }
  };

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) {
      setProofFile(null);
      setFileError('Berkas bukti/slip pembayaran wajib dilampirkan.');
      return;
    }

    const val = validateProofFile(file);
    if (!val.valid) {
      setFileError(val.error || 'Berkas tidak valid');
      setProofFile(null);
    } else {
      setFileError(null);
      setProofFile(file);
    }
  };

  const handleSubmitSlip = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedInvoice) return;

    if (payAmount <= 0) {
      setSubmitError('Nominal pembayaran harus lebih dari Rp 0.');
      return;
    }
    if (payAmount > selectedInvoice.remaining_amount) {
      setSubmitError(
        `Nominal tidak boleh melebihi sisa tagihan (${formatRupiah(selectedInvoice.remaining_amount)}).`
      );
      return;
    }
    if (!extReference.trim()) {
      setSubmitError('Nomor referensi transaksi pihak ketiga wajib diisi.');
      return;
    }
    if (!proofFile) {
      setSubmitError('Harap lampirkan foto/file bukti transfer bank.');
      return;
    }

    setIsSubmitting(true);
    setSubmitError(null);
    try {
      await financeApi.submitPayment(selectedInvoice.id, {
        amount: payAmount,
        channel: payChannel,
        external_reference: extReference,
        payment_date: payDate,
        proof_file: proofFile,
      });

      setSubmitSuccess('Slip pembayaran berhasil diunggah! Menunggu verifikasi bendahara sekolah.');
      await loadData();
      setTimeout(() => {
        setSelectedInvoice(null);
      }, 1500);
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Gagal mengirim bukti pembayaran.';
      setSubmitError(msg);
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleViewReceipt = async (paymentId: string) => {
    setIsLoadingReceipt(true);
    try {
      const receipt = await financeApi.getPortalPaymentReceipt(paymentId);
      setReceiptData(receipt);
    } catch (err: unknown) {
      toast.error(err instanceof Error ? err.message : 'Gagal memuat kuitansi.');
    } finally {
      setIsLoadingReceipt(false);
    }
  };

  if (isLoading) {
    return (
      <div className="min-h-[250px] flex items-center justify-center bg-white rounded-2xl border border-slate-200">
        <LoadingSpinner label="Memuat data tagihan & riwayat keuangan..." size="lg" />
      </div>
    );
  }

  if (error) {
    return (
      <ErrorState
        title="Gagal Memuat Keuangan"
        message={error}
        onRetry={loadData}
      />
    );
  }

  return (
    <div className="space-y-6">
      {/* Panel Panduan & Cara Pembayaran Pihak Ketiga */}
      {paymentProviders.length > 0 && (
        <Card className="border-blue-100 bg-blue-50/40">
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-bold text-slate-900 flex items-center gap-2">
              <BookOpen className="h-4 w-4 text-brand-blue" />
              Petunjuk Cara Pembayaran (Pihak Ketiga)
            </CardTitle>
            <CardDescription className="text-xs text-slate-600">
              Pembayaran biaya sekolah dilakukan di luar sistem. Setelah transfer/bayar, simpan struk dan unggah slip pembayaran di bawah.
            </CardDescription>
          </CardHeader>
          <CardContent className="pt-0">
            <div className="space-y-3 mt-1">
              {paymentProviders.map((p) => (
                <div key={p.id} className="p-3 bg-white rounded-xl border border-blue-100 text-xs shadow-2xs space-y-1.5">
                  <div className="font-bold text-slate-900 text-sm flex items-center justify-between">
                    <span>{p.name}</span>
                    {p.info_url && (
                      <a
                        href={p.info_url}
                        target="_blank"
                        rel="noreferrer"
                        className="text-[11px] font-normal text-brand-blue hover:underline inline-flex items-center gap-1"
                      >
                        <ExternalLink className="h-3 w-3" />
                        Panduan Resmi
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

      {/* Daftar Tagihan */}
      {invoices.length === 0 ? (
        <Card className="p-8">
          <EmptyState
            title="Tidak Ada Tagihan"
            description="Saat ini ananda tidak memiliki tagihan aktif atau tunggakan biaya pendidikan."
          />
        </Card>
      ) : (
        <div className="space-y-4">
          {invoices.map((inv) => {
            const hasPendingSlip = inv.payments?.some((p) => p.status === 'menunggu_verifikasi');

            return (
              <Card key={inv.id} className="overflow-hidden border-slate-200">
                <div className="p-4 sm:p-5 flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-slate-100 bg-slate-50/50">
                  <div>
                    <div className="flex items-center gap-2">
                      <span className="font-mono font-bold text-slate-900 text-sm">{inv.invoice_number}</span>
                      <Badge
                        className={
                          inv.status === 'lunas'
                            ? 'bg-emerald-100 text-emerald-800 border-emerald-200'
                            : inv.status === 'sebagian'
                            ? 'bg-amber-100 text-amber-800 border-amber-200'
                            : inv.status === 'belum_bayar'
                            ? 'bg-rose-100 text-rose-800 border-rose-200'
                            : 'bg-slate-100 text-slate-600 border-slate-200'
                        }
                      >
                        {inv.status === 'lunas'
                          ? 'Lunas'
                          : inv.status === 'sebagian'
                          ? 'Sebagian'
                          : inv.status === 'belum_bayar'
                          ? 'Belum Bayar'
                          : 'Dibatalkan'}
                      </Badge>
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

                  <div className="flex items-center gap-4">
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

                    {inv.remaining_amount > 0 && inv.status !== 'dibatalkan' && (
                      <div>
                        {hasPendingSlip ? (
                          <div className="text-right">
                            <Badge className="bg-amber-100 text-amber-800 border-amber-200 py-1">
                              Slip Menunggu Verifikasi
                            </Badge>
                            <span className="text-[10px] text-slate-400 block mt-0.5">
                              Sedang diproses bendahara
                            </span>
                          </div>
                        ) : (
                          <Button
                            size="sm"
                            onClick={() => handleOpenUploadModal(inv)}
                            className="bg-brand-blue hover:bg-brand-blue/90 gap-1.5"
                          >
                            <Upload className="h-4 w-4" />
                            Unggah Slip Pembayaran
                          </Button>
                        )}
                      </div>
                    )}
                  </div>
                </div>

                {/* Rincian Pos Biaya */}
                <div className="p-4 sm:p-5">
                  <div className="text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                    Rincian Biaya
                  </div>
                  <div className="divide-y divide-slate-100">
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

                  {/* Riwayat Slip Pembayaran untuk Tagihan ini */}
                  {inv.payments && inv.payments.length > 0 && (
                    <div className="mt-4 pt-4 border-t border-slate-100">
                      <div className="text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                        Riwayat Slip Pembayaran
                      </div>
                      <div className="space-y-2">
                        {inv.payments.map((p) => (
                          <div
                            key={p.id}
                            className="p-3 bg-slate-50 rounded-xl border border-slate-200 text-xs flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2"
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
                                    <span className="block text-[11px] text-rose-500 mt-0.5">
                                      Anda dapat mengunggah ulang slip pembayaran dengan foto/bukti yang benar.
                                    </span>
                                  </div>
                                </div>
                              )}
                            </div>

                            <div className="flex items-center gap-2 self-start sm:self-center">
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
                                  ? 'Void (Dibatalkan)'
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
            );
          })}
        </div>
      )}

      {/* Modal Unggah Slip Pembayaran */}
      {selectedInvoice && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            <div className="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
              <div>
                <h3 className="font-semibold text-slate-900">Unggah Slip Pembayaran</h3>
                <p className="text-xs text-slate-500 font-mono">{selectedInvoice.invoice_number}</p>
              </div>
              <button
                onClick={() => setSelectedInvoice(null)}
                className="text-slate-400 hover:text-slate-600 p-1 rounded-lg"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            <form onSubmit={handleSubmitSlip} className="p-6 space-y-4">
              {submitError && (
                <div className="p-3 bg-red-50 text-red-700 text-xs rounded-lg flex items-center gap-2">
                  <AlertCircle className="h-4 w-4 shrink-0" />
                  <span>{submitError}</span>
                </div>
              )}

              {submitSuccess && (
                <div className="p-3 bg-emerald-50 text-emerald-800 text-xs rounded-lg flex items-center gap-2">
                  <CheckCircle2 className="h-4 w-4 shrink-0 text-emerald-600" />
                  <span>{submitSuccess}</span>
                </div>
              )}

              <div className="bg-slate-50 p-3 rounded-lg border border-slate-100 text-xs">
                <div className="text-slate-500">Sisa Tagihan yang Harus Dilunasi:</div>
                <div className="text-xl font-bold text-slate-900 mt-0.5">
                  {formatRupiah(selectedInvoice.remaining_amount)}
                </div>
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-700 uppercase mb-1">
                  Jumlah yang Dibayar (Rp) <span className="text-red-500">*</span>
                </label>
                <Input
                  type="number"
                  min="1000"
                  max={selectedInvoice.remaining_amount}
                  value={payAmount}
                  onChange={(e) => setPayAmount(Number(e.target.value))}
                  required
                />
                <span className="text-[10px] text-slate-500 mt-1 block">
                  Pembayaran sebagian diperbolehkan (maksimal sisa tagihan).
                </span>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-semibold text-slate-700 uppercase mb-1">
                    Kanal Pembayaran <span className="text-red-500">*</span>
                  </label>
                  <Input
                    placeholder="Mis: Bank Jatim VA / BCA"
                    value={payChannel}
                    onChange={(e) => setPayChannel(e.target.value)}
                    required
                  />
                </div>
                <div>
                  <label className="block text-xs font-semibold text-slate-700 uppercase mb-1">
                    Tanggal Bayar <span className="text-red-500">*</span>
                  </label>
                  <Input
                    type="date"
                    value={payDate}
                    onChange={(e) => setPayDate(e.target.value)}
                    required
                  />
                </div>
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-700 uppercase mb-1">
                  Nomor Referensi Transaksi (Ref ID) <span className="text-red-500">*</span>
                </label>
                <Input
                  placeholder="Nomor referensi / nomor transaksi pada struk"
                  value={extReference}
                  onChange={(e) => setExtReference(e.target.value)}
                  required
                />
                <span className="text-[10px] text-slate-500 mt-1 block">
                  Nomor referensi unik yang tercetak pada bukti transfer dari bank / m-banking.
                </span>
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-700 uppercase mb-1">
                  Berkas Foto / PDF Bukti Slip <span className="text-red-500">*</span>
                </label>
                <Input
                  type="file"
                  accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf"
                  onChange={handleFileChange}
                  required
                />
                <div className="text-[10px] text-slate-500 mt-1">
                  Format didukung: JPG, PNG, PDF (Maks. 2MB).
                </div>
                {fileError && (
                  <div className="text-xs text-rose-600 mt-1 font-medium">{fileError}</div>
                )}
              </div>

              <div className="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                <Button
                  type="button"
                  variant="outline"
                  onClick={() => setSelectedInvoice(null)}
                  disabled={isSubmitting}
                >
                  Batal
                </Button>
                <Button
                  type="submit"
                  disabled={isSubmitting || Boolean(fileError) || !proofFile}
                  className="bg-brand-blue hover:bg-brand-blue/90"
                >
                  {isSubmitting ? 'Mengirim...' : 'Kirim Slip Pembayaran'}
                </Button>
              </div>
            </form>
          </div>
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
