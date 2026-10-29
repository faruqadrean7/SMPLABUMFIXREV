'use client';

import React, { useMemo } from 'react';
import { Receipt, CheckCircle2, Clock, Wallet, Info } from 'lucide-react';
import {
  useMyInvoices,
  formatRupiah,
  invoiceRemaining,
  latestReceiptNumber,
  INVOICE_STATUS_LABEL,
  type InvoiceStatus,
} from '@/lib/finance-api';
import { ErrorState } from '@/components/ui/error-state';
import { EmptyState } from '@/components/ui/empty-state';

const STATUS_BADGE: Record<InvoiceStatus, string> = {
  lunas: 'bg-emerald-800 text-white',
  sebagian: 'bg-blue-800 text-white',
  belum_bayar: 'bg-amber-600 text-white',
  dibatalkan: 'bg-slate-400 text-white',
};

export default function StudentTagihanPage() {
  const { data: invoices, isLoading, isError, refetch } = useMyInvoices();
  const list = useMemo(() => invoices ?? [], [invoices]);

  const totals = useMemo(() => {
    const tagihan = list.reduce((s, i) => s + (i.total_amount || 0), 0);
    const terbayar = list.reduce((s, i) => s + (i.paid_amount || 0), 0);
    const tunggakan = list
      .filter((i) => i.status === 'belum_bayar' || i.status === 'sebagian')
      .reduce((s, i) => s + invoiceRemaining(i), 0);
    return { tagihan, terbayar, tunggakan };
  }, [list]);

  return (
    <div className="flex flex-col gap-3.5 max-w-full">
      <div className="shrink-0">
        <h1 className="text-xl font-extrabold text-slate-900 tracking-tight">Tagihan &amp; Keuangan</h1>
        <p className="text-xs text-slate-600 mt-1">
          Rekap tagihan resmi (SPP &amp; kegiatan) yang diterbitkan Bagian Keuangan sekolah beserta status pembayarannya.
        </p>
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 shrink-0">
        <Card label="Total Tagihan" value={formatRupiah(totals.tagihan)} icon={Receipt} tone="slate" />
        <Card label="Sudah Terbayar" value={formatRupiah(totals.terbayar)} icon={CheckCircle2} tone="emerald" />
        <Card label="Tunggakan" value={formatRupiah(totals.tunggakan)} icon={Wallet} tone={totals.tunggakan > 0 ? 'amber' : 'emerald'} />
      </div>

      <div className="bg-white border border-slate-200/90 rounded-2xl p-4 shadow-xs flex flex-col min-h-97.5">
        <div className="flex items-center justify-between pb-3 border-b border-slate-100 shrink-0">
          <h2 className="text-sm font-bold text-slate-900">Daftar Tagihan Saya</h2>
          <span className="px-2 py-0.5 rounded-full bg-slate-800 text-white text-xs font-bold">{list.length} Tagihan</span>
        </div>

        <div className="flex-1 min-h-0 overflow-y-auto pr-1 mt-2.5 border border-slate-200 rounded-xl">
          {isLoading ? (
            <div className="p-4 space-y-2">
              {[0, 1, 2].map((i) => (
                <div key={i} className="h-10 rounded-lg bg-slate-100 animate-pulse" />
              ))}
            </div>
          ) : isError ? (
            <div className="p-4">
              <ErrorState
                title="Gagal Memuat Tagihan"
                message="Tidak dapat mengambil data tagihan dari server. Silakan coba kembali."
                onRetry={() => refetch()}
              />
            </div>
          ) : list.length === 0 ? (
            <div className="p-4">
              <EmptyState icon={Receipt} title="Belum Ada Tagihan" description="Belum ada tagihan yang diterbitkan untukmu saat ini." />
            </div>
          ) : (
            <table className="w-full text-left text-xs border-collapse">
              <thead className="sticky top-0 bg-slate-100 z-10">
                <tr className="border-b border-slate-200 text-slate-700 font-bold uppercase tracking-wider text-[11px]">
                  <th className="py-2.5 px-3">No. Tagihan</th>
                  <th className="py-2.5 px-3">Periode</th>
                  <th className="py-2.5 px-3">Jatuh Tempo</th>
                  <th className="py-2.5 px-3 text-right">Nominal</th>
                  <th className="py-2.5 px-2 text-center">Status</th>
                  <th className="py-2.5 px-3">No. Kwitansi</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {list.map((inv) => (
                  <tr key={inv.id} className="hover:bg-slate-50/80 transition-colors">
                    <td className="py-2.5 px-3 font-bold text-slate-900 whitespace-nowrap">{inv.invoice_number}</td>
                    <td className="py-2.5 px-3 text-slate-600 whitespace-nowrap">{inv.period || '-'}</td>
                    <td className="py-2.5 px-3 text-slate-600 whitespace-nowrap">{inv.due_date}</td>
                    <td className="py-2.5 px-3 text-right font-mono font-bold text-slate-900 whitespace-nowrap">{formatRupiah(inv.total_amount)}</td>
                    <td className="py-2.5 px-2 text-center whitespace-nowrap">
                      <span className={`px-2 py-0.5 rounded text-[11px] font-bold ${STATUS_BADGE[inv.status]}`}>
                        {INVOICE_STATUS_LABEL[inv.status]}
                      </span>
                    </td>
                    <td className="py-2.5 px-3 font-mono font-bold text-slate-700 whitespace-nowrap">{latestReceiptNumber(inv) || '-'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>

        <div className="mt-2.5 p-2.5 bg-slate-50 rounded-xl border border-slate-200/90 text-[11px] text-slate-600 flex gap-1.5 shrink-0">
          <Info className="w-3.5 h-3.5 text-blue-800 shrink-0 mt-0.5" />
          <span>Status tagihan diperbarui otomatis setelah pembayaran diverifikasi Bagian Keuangan. Hubungi Tata Usaha untuk konfirmasi bukti transfer.</span>
        </div>
      </div>
    </div>
  );
}

function Card({
  label,
  value,
  icon: Icon,
  tone,
}: {
  label: string;
  value: string;
  icon: React.ComponentType<{ className?: string }>;
  tone: 'slate' | 'emerald' | 'amber';
}) {
  const toneMap = {
    slate: 'bg-slate-900 text-white',
    emerald: 'bg-emerald-100 text-emerald-800 border border-emerald-200',
    amber: 'bg-amber-100 text-amber-800 border border-amber-200',
  } as const;
  return (
    <div className="bg-white border border-slate-200/90 rounded-2xl p-3.5 shadow-xs flex flex-col justify-between">
      <div className="flex items-center justify-between pb-1.5 border-b border-slate-100">
        <span className="text-xs font-bold text-slate-700 uppercase tracking-wider truncate">{label}</span>
        <div className={`w-8 h-8 rounded-xl flex items-center justify-center shrink-0 ${toneMap[tone]}`}>
          <Icon className="w-4 h-4" />
        </div>
      </div>
      <div className="mt-1.5">
        <span className="text-2xl font-extrabold tracking-tight text-slate-900">{value}</span>
      </div>
    </div>
  );
}
