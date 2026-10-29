'use client';

import React, { useMemo, useState } from 'react';
import {
  Receipt,
  CheckCircle2,
  Clock,
  Phone,
  Building2,
  Info,
  Copy,
  Check,
  ExternalLink,
  Wallet,
  AlertCircle,
} from 'lucide-react';
import { toast } from 'sonner';
import { useGuardian } from '@/lib/guardian-context';
import {
  useChildInvoices,
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

export default function GuardianBiayaPage() {
  const { activeChild, activeChildId } = useGuardian();
  const { data: invoices, isLoading, isError, refetch } = useChildInvoices(activeChildId);
  const [copiedBank, setCopiedBank] = useState<string | null>(null);

  const handleCopy = (text: string, bankName: string) => {
    navigator.clipboard.writeText(text);
    setCopiedBank(bankName);
    toast.success(`Nomor rekening ${bankName} berhasil disalin`);
    setTimeout(() => setCopiedBank(null), 2000);
  };

  const list = useMemo(() => invoices ?? [], [invoices]);

  const totals = useMemo(() => {
    const tagihan = list.reduce((s, i) => s + (i.total_amount || 0), 0);
    const terbayar = list.reduce((s, i) => s + (i.paid_amount || 0), 0);
    const tunggakan = list
      .filter((i) => i.status === 'belum_bayar' || i.status === 'sebagian')
      .reduce((s, i) => s + invoiceRemaining(i), 0);
    const belumLunas = list.filter((i) => i.status === 'belum_bayar' || i.status === 'sebagian').length;
    return { tagihan, terbayar, tunggakan, belumLunas };
  }, [list]);

  const bebasTanggungan = totals.tunggakan === 0 && list.length > 0;

  return (
    <div className="flex flex-col gap-3.5 max-w-full">
      {/* HEADER */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 shrink-0">
        <div>
          <div className="flex flex-wrap items-center gap-2.5">
            <h1 className="text-xl font-extrabold text-slate-900 tracking-tight">
              Status Administrasi &amp; Biaya ({activeChild.name.split(' ')[0]})
            </h1>
            <span className="px-2.5 py-0.5 rounded-lg bg-emerald-800 text-white text-xs font-bold">
              Kelas {String(activeChild.class_name || '').replace('Kelas ', '')}
            </span>
            <span className="px-2.5 py-0.5 rounded-lg bg-slate-100 border border-slate-300 text-slate-700 text-xs font-mono font-semibold">
              NIS: {activeChild.nis}
            </span>
            <span
              className={`px-2.5 py-0.5 rounded-lg text-xs font-bold flex items-center gap-1.5 ${
                bebasTanggungan ? 'bg-emerald-800 text-white' : 'bg-amber-600 text-white'
              }`}
            >
              {bebasTanggungan ? <CheckCircle2 className="w-3.5 h-3.5" /> : <Clock className="w-3.5 h-3.5" />}
              <span>{bebasTanggungan ? 'Bebas Tanggungan' : `${totals.belumLunas} Tagihan Aktif`}</span>
            </span>
          </div>
          <p className="text-xs text-slate-600 mt-1">
            Rekap tagihan resmi (SPP, kegiatan, dan lainnya) yang diterbitkan Bagian Keuangan sekolah.
          </p>
        </div>
        <a
          href="https://wa.me/6281234567899"
          target="_blank"
          rel="noreferrer"
          className="px-3.5 py-2.5 rounded-xl bg-emerald-800 hover:bg-emerald-900 text-white text-xs font-bold flex items-center gap-2 self-start shrink-0"
        >
          <Phone className="w-4 h-4" />
          <span>Konfirmasi Bukti Transfer (WA)</span>
        </a>
      </div>

      {/* KARTU REKAP (data nyata) */}
      <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3 shrink-0">
        <SummaryCard label="Total Tagihan" value={formatRupiah(totals.tagihan)} icon={Receipt} tone="slate" />
        <SummaryCard label="Sudah Terbayar" value={formatRupiah(totals.terbayar)} icon={CheckCircle2} tone="emerald" />
        <SummaryCard label="Tunggakan" value={formatRupiah(totals.tunggakan)} icon={Wallet} tone={totals.tunggakan > 0 ? 'amber' : 'emerald'} />
        <SummaryCard label="Tagihan Aktif" value={`${totals.belumLunas} item`} icon={Clock} tone="blue" />
      </div>

      {/* DUA KOLOM */}
      <div className="grid grid-cols-1 lg:grid-cols-12 gap-3.5">
        {/* KIRI: RIWAYAT TAGIHAN NYATA */}
        <div className="lg:col-span-7 bg-white border border-slate-200/90 rounded-2xl p-4 shadow-xs flex flex-col min-h-97.5">
          <div className="flex items-center justify-between pb-3 border-b border-slate-100 shrink-0">
            <div>
              <h2 className="text-sm font-bold text-slate-900">Daftar Tagihan ({activeChild.name.split(' ')[0]})</h2>
              <p className="text-xs text-slate-500">Diterbitkan oleh Bagian Keuangan Labschool UM.</p>
            </div>
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
                <EmptyState icon={Receipt} title="Belum Ada Tagihan" description="Belum ada tagihan yang diterbitkan untuk ananda saat ini." />
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
        </div>

        {/* KANAN: SALURAN PEMBAYARAN (statis) */}
        <div className="lg:col-span-5 bg-white border border-slate-200/90 rounded-2xl p-4 shadow-xs flex flex-col min-h-97.5 gap-2.5">
          <div className="flex items-center justify-between pb-3 border-b border-slate-100">
            <div>
              <h2 className="text-sm font-bold text-slate-900">Saluran Pembayaran &amp; Konfirmasi</h2>
              <p className="text-xs text-slate-500">Rekening giro resmi Bagian Keuangan.</p>
            </div>
            <span className="px-2.5 py-1 rounded-full bg-slate-800 text-white text-xs font-bold">Giro Resmi</span>
          </div>

          <div className="bg-white rounded-xl p-3 border border-slate-200/90 border-l-4 border-l-blue-600 space-y-2">
            <div className="flex items-center justify-between pb-1.5 border-b border-slate-100">
              <span className="px-2 py-0.5 rounded text-xs font-bold bg-blue-800 text-white">Transfer Giro</span>
              <span className="text-[11px] text-slate-500">Berita: NISN {activeChild.nisn}</span>
            </div>
            <div className="p-2.5 bg-slate-50 rounded-lg border border-slate-200/80 flex items-center justify-between">
              <div>
                <span className="text-[10px] font-bold text-slate-500 uppercase">Bank Mandiri (Giro)</span>
                <p className="font-mono text-xs font-bold text-slate-900">144-00-1928374-1</p>
                <p className="text-[10px] text-slate-500">a.n. BPP Labschool UM</p>
              </div>
              <button
                type="button"
                onClick={() => handleCopy('1440019283741', 'Bank Mandiri')}
                className="px-2.5 py-1 rounded-md border border-slate-300 hover:bg-slate-200/70 text-slate-700 text-xs font-semibold flex items-center gap-1 shrink-0"
              >
                {copiedBank === 'Bank Mandiri' ? <Check className="w-3.5 h-3.5 text-emerald-700" /> : <Copy className="w-3.5 h-3.5" />}
                <span>{copiedBank === 'Bank Mandiri' ? 'Tersalin' : 'Salin'}</span>
              </button>
            </div>
          </div>

          <div className="bg-white rounded-xl p-3 border border-slate-200/90 border-l-4 border-l-emerald-600">
            <div className="flex items-center gap-1.5">
              <Building2 className="w-3.5 h-3.5 text-emerald-700 shrink-0" />
              <p className="text-xs font-bold text-slate-900">Kasir Tata Usaha &amp; Cetak Kwitansi</p>
            </div>
            <p className="text-xs text-slate-600 pl-5 mt-0.5">Verifikasi bukti setor, cetak kwitansi resmi, dan layanan dispensasi administrasi. Operasional 07.30–15.00 WIB.</p>
          </div>

          <div className="mt-auto p-2.5 bg-slate-50 rounded-xl border border-slate-200/90 text-[11px] text-slate-600 flex gap-1.5">
            <Info className="w-3.5 h-3.5 text-blue-800 shrink-0 mt-0.5" />
            <span>Status tagihan diperbarui otomatis setelah pembayaran diverifikasi Bagian Keuangan.</span>
          </div>
        </div>
      </div>
    </div>
  );
}

function SummaryCard({
  label,
  value,
  icon: Icon,
  tone,
}: {
  label: string;
  value: string;
  icon: React.ComponentType<{ className?: string }>;
  tone: 'slate' | 'emerald' | 'amber' | 'blue';
}) {
  const toneMap = {
    slate: 'bg-slate-900 text-white',
    emerald: 'bg-emerald-100 text-emerald-800 border border-emerald-200',
    amber: 'bg-amber-100 text-amber-800 border border-amber-200',
    blue: 'bg-blue-100 text-blue-800 border border-blue-200',
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
