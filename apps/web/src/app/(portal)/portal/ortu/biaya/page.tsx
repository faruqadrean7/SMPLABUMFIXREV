'use client';

import React, { useState } from 'react';
import { 
  Receipt, 
  CheckCircle2, 
  Clock, 
  ShieldCheck, 
  AlertCircle, 
  Phone, 
  FileCheck, 
  Building2,
  Info,
  Calendar,
  CreditCard,
  Copy,
  Check,
  Send,
  HelpCircle,
  ExternalLink
} from 'lucide-react';
import { toast } from 'sonner';
import { useGuardian } from '@/lib/guardian-context';

export default function GuardianBiayaPage() {
  const { activeChild, activeFees } = useGuardian();
  const [copiedBank, setCopiedBank] = useState<string | null>(null);

  const handleCopy = (text: string, bankName: string) => {
    navigator.clipboard.writeText(text);
    setCopiedBank(bankName);
    toast.success(`Nomor rekening ${bankName} berhasil disalin`);
    setTimeout(() => setCopiedBank(null), 2000);
  };

  // Determine dynamic status
  const getAdminStatusBadge = () => {
    switch (activeChild.admin_fee_status) {
      case 'Lunas / Bebas Tanggungan':
        return {
          label: 'Bebas Tanggungan: LUNAS',
          badgeClass: 'bg-emerald-800 text-white',
          icon: CheckCircle2,
          currentMonthLabel: 'Lunas',
          currentMonthBadge: 'Terverifikasi',
          currentMonthBadgeClass: 'bg-emerald-800 text-white',
          examStatus: 'Diizinkan',
          examBadge: 'Siap Asesmen',
          examBadgeClass: 'bg-emerald-800 text-white',
          examNote: 'Berhak mengikuti seluruh asesmen sumatif CBT',
        };
      case 'Menunggu Pembayaran':
        return {
          label: 'Status: Menunggu Pembayaran SPP',
          badgeClass: 'bg-amber-600 text-white',
          icon: Clock,
          currentMonthLabel: 'Rp 750.000',
          currentMonthBadge: 'Tempo 20 Okt',
          currentMonthBadgeClass: 'bg-amber-600 text-white',
          examStatus: 'Dispensasi',
          examBadge: 'Izin Mengikuti',
          examBadgeClass: 'bg-blue-800 text-white',
          examNote: 'Dispensasi asesmen aktif hingga akhir bulan',
        };
      case 'Menunggu Verifikasi TU':
      default:
        return {
          label: 'Status: Sedang Diverifikasi TU',
          badgeClass: 'bg-blue-800 text-white',
          icon: FileCheck,
          currentMonthLabel: 'Verifikasi',
          currentMonthBadge: 'Proses Kasir',
          currentMonthBadgeClass: 'bg-blue-800 text-white',
          examStatus: 'Diizinkan',
          examBadge: 'Siap Asesmen',
          examBadgeClass: 'bg-emerald-800 text-white',
          examNote: 'Bukti transfer diterima, kwitansi dalam proses',
        };
    }
  };

  const statusInfo = getAdminStatusBadge();
  const StatusIcon = statusInfo.icon;

  return (
    <div className="flex flex-col gap-3.5 max-w-full xl:h-[calc(100vh-112px)] xl:overflow-hidden">
      {/* ─── 1. HEADER BAR (DESKTOP MAXIMIZED & WELL-PROPORTIONED) ─── */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 shrink-0">
        <div>
          <div className="flex items-center gap-2.5">
            <h1 className="text-xl font-extrabold text-slate-900 tracking-tight">
              Status Administrasi & Biaya ({activeChild.name.split(' ')[0]})
            </h1>
            <span className="px-2.5 py-0.5 rounded-lg bg-emerald-800 text-white text-xs font-bold shadow-2xs">
              Kelas {activeChild.class_name.replace('Kelas ', '')}
            </span>
            <span className="px-2.5 py-0.5 rounded-lg bg-slate-100 border border-slate-300 text-slate-700 text-xs font-mono font-semibold">
              NIS: {activeChild.nis}
            </span>
            <span className={`px-2.5 py-0.5 rounded-lg text-xs font-bold shadow-2xs flex items-center gap-1.5 ${statusInfo.badgeClass}`}>
              <StatusIcon className="w-3.5 h-3.5" />
              <span>{statusInfo.label}</span>
            </span>
          </div>
          <p className="text-xs text-slate-600 mt-1 font-normal">
            Buku kas transparansi non-finansial pencatatan SPP bulanan, operasional pembelajaran, dan bebas tanggungan sekolah.
          </p>
        </div>

        <div className="flex items-center gap-2.5 self-start sm:self-auto shrink-0">
          <a
            href="https://wa.me/6281234567899"
            target="_blank"
            rel="noreferrer"
            className="px-3.5 py-2.5 rounded-xl bg-emerald-800 hover:bg-emerald-900 text-white text-xs font-bold transition-all shadow-sm flex items-center gap-2 hover:shadow"
          >
            <Phone className="w-4 h-4 text-white" />
            <span>Konfirmasi Bukti Transfer (WA)</span>
          </a>
        </div>
      </div>

      {/* ─── 2. REKAPITULASI ADMINISTRASI 4 KARTU SEIMBANG (XL:GRID-COLS-4) ─── */}
      <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3 shrink-0">
        {/* Metric 1: Status Administrasi Siswa */}
        <div className="bg-white border border-slate-200/90 rounded-2xl p-3.5 shadow-xs flex flex-col justify-between hover:border-slate-300 transition-all">
          <div className="flex items-center justify-between pb-1.5 border-b border-slate-100">
            <span className="text-xs font-bold text-slate-700 uppercase tracking-wider truncate">Bebas Tanggungan</span>
            <div className="w-8 h-8 rounded-xl bg-slate-900 text-white flex items-center justify-center shrink-0">
              <Receipt className="w-4 h-4" />
            </div>
          </div>
          <div className="my-1.5">
            <span className={`text-3xl font-extrabold tracking-tight ${
              activeChild.admin_fee_status === 'Lunas / Bebas Tanggungan' ? 'text-slate-900' :
              activeChild.admin_fee_status === 'Menunggu Verifikasi TU' ? 'text-blue-900' : 'text-amber-800'
            }`}>
              {activeChild.admin_fee_status === 'Lunas / Bebas Tanggungan' ? 'LUNAS' :
               activeChild.admin_fee_status === 'Menunggu Verifikasi TU' ? 'VERIFIKASI' : 'TAGIHAN'}
            </span>
            <p className="text-xs text-slate-500 font-medium mt-0.5">
              Semester Gasal T.A. 2026/2027
            </p>
          </div>
          <div className="pt-2 border-t border-slate-100 flex items-center justify-between">
            <span className="text-xs font-medium text-slate-500">Status</span>
            <span className={`px-2 py-0.5 rounded-md text-xs font-semibold ${statusInfo.badgeClass}`}>
              {activeChild.admin_fee_status}
            </span>
          </div>
        </div>

        {/* Metric 2: SPP Bulan Berjalan */}
        <div className="bg-white border border-slate-200/90 rounded-2xl p-3.5 shadow-xs flex flex-col justify-between hover:border-slate-300 transition-all">
          <div className="flex items-center justify-between pb-1.5 border-b border-slate-100">
            <span className="text-xs font-bold text-slate-700 uppercase tracking-wider truncate">SPP Oktober 2026</span>
            <div className="w-8 h-8 rounded-xl bg-amber-100 text-amber-800 flex items-center justify-center shrink-0 border border-amber-200">
              <Clock className="w-4 h-4" />
            </div>
          </div>
          <div className="my-1.5">
            <span className="text-3xl font-extrabold tracking-tight text-slate-900">
              {statusInfo.currentMonthLabel}
            </span>
            <p className="text-xs text-slate-500 font-medium mt-0.5">
              Jatuh Tempo: 20 Oktober 2026
            </p>
          </div>
          <div className="pt-2 border-t border-slate-100 flex items-center justify-between">
            <span className="text-xs font-medium text-slate-500">Kwitansi</span>
            <span className={`px-2 py-0.5 rounded-md text-xs font-semibold ${statusInfo.currentMonthBadgeClass}`}>
              {statusInfo.currentMonthBadge}
            </span>
          </div>
        </div>

        {/* Metric 3: Fasilitas Modul & Praktikum */}
        <div className="bg-white border border-slate-200/90 rounded-2xl p-3.5 shadow-xs flex flex-col justify-between hover:border-slate-300 transition-all">
          <div className="flex items-center justify-between pb-1.5 border-b border-slate-100">
            <span className="text-xs font-bold text-slate-700 uppercase tracking-wider truncate">Praktikum & Modul</span>
            <div className="w-8 h-8 rounded-xl bg-emerald-100 text-emerald-800 flex items-center justify-center shrink-0 border border-emerald-200">
              <ShieldCheck className="w-4 h-4" />
            </div>
          </div>
          <div className="my-1.5">
            <span className="text-3xl font-extrabold tracking-tight text-emerald-800">
              Bebas Biaya
            </span>
            <p className="text-xs text-slate-500 font-medium mt-0.5">
              Lab Komputer & Server CBT
            </p>
          </div>
          <div className="pt-2 border-t border-slate-100 flex items-center justify-between">
            <span className="text-xs font-medium text-slate-500">Operasional</span>
            <span className="px-2 py-0.5 rounded-md text-xs font-semibold bg-emerald-800 text-white">
              Tercakup BOP
            </span>
          </div>
        </div>

        {/* Metric 4: Hak Mengikuti Ujian CBT */}
        <div className="bg-white border border-slate-200/90 rounded-2xl p-3.5 shadow-xs flex flex-col justify-between hover:border-slate-300 transition-all">
          <div className="flex items-center justify-between pb-1.5 border-b border-slate-100">
            <span className="text-xs font-bold text-slate-700 uppercase tracking-wider truncate">Hak Asesmen CBT</span>
            <div className="w-8 h-8 rounded-xl bg-blue-100 text-blue-800 flex items-center justify-center shrink-0 border border-blue-200">
              <FileCheck className="w-4 h-4" />
            </div>
          </div>
          <div className="my-1.5">
            <span className="text-3xl font-extrabold tracking-tight text-slate-900">
              {statusInfo.examStatus}
            </span>
            <p className="text-xs text-slate-500 font-medium mt-0.5">
              Kartu Peserta STS Tervalidasi
            </p>
          </div>
          <div className="pt-2 border-t border-slate-100 flex items-center justify-between">
            <span className="text-xs font-medium text-slate-500">Legalitas</span>
            <span className={`px-2 py-0.5 rounded-md text-xs font-semibold ${statusInfo.examBadgeClass}`}>
              {statusInfo.examBadge}
            </span>
          </div>
        </div>
      </div>

      {/* ─── 3. DUA KOLOM: BUKU KAS RIWAYAT & SALURAN TRANSFER TU (MAXIMIZED DESKTOP) ─── */}
      <div className="xl:flex-1 xl:min-h-0 grid grid-cols-1 lg:grid-cols-12 gap-3.5">
        {/* KOLOM KIRI: BUKU KAS RIWAYAT PEMBAYARAN TU (7 COLS) */}
        <div className="lg:col-span-7 bg-white border border-slate-200/90 rounded-2xl p-4 shadow-xs flex flex-col h-full min-h-97.5">
          {/* Header Bar */}
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-100 shrink-0">
            <div>
              <div className="flex items-center gap-2">
                <h2 className="text-sm font-bold text-slate-900 tracking-tight">
                  Riwayat Pembayaran & Bebas Tanggungan ({activeChild.name.split(' ')[0]})
                </h2>
                <span className="px-2 py-0.5 rounded-full bg-slate-800 text-white text-xs font-bold">
                  {activeFees.length} Transaksi
                </span>
              </div>
              <p className="text-xs text-slate-500 font-normal mt-0.5">
                Daftar pencatatan kas resmi Bagian Tata Usaha Labschool UM T.A. 2026/2027.
              </p>
            </div>

            <span className="px-2.5 py-1 rounded-lg bg-slate-100 border border-slate-200 text-xs font-bold text-slate-700 self-start sm:self-auto shrink-0">
              Buku Kas Resmi TU
            </span>
          </div>

          {/* Table Container with Internal Scroll & Sticky Header */}
          <div className="flex-1 min-h-0 overflow-y-auto pr-1 mt-2.5 border border-slate-200 rounded-xl">
            <table className="w-full text-left text-xs border-collapse">
              <thead className="sticky top-0 bg-slate-100 z-10 shadow-2xs">
                <tr className="border-b border-slate-200 text-slate-700 font-bold uppercase tracking-wider text-[11px]">
                  <th className="py-2.5 px-3">Uraian Pembayaran</th>
                  <th className="py-2.5 px-3">Periode</th>
                  <th className="py-2.5 px-3 text-right">Nominal</th>
                  <th className="py-2.5 px-2 text-center">Status</th>
                  <th className="py-2.5 px-3">No. Kwitansi TU</th>
                  <th className="py-2.5 px-3">Petugas Kasir</th>
                  <th className="py-2.5 px-3">Catatan</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 font-normal">
                {activeFees.map((fee) => {
                  const isPaid = fee.status.includes('Lunas');
                  const isVerif = fee.status.includes('Proses') && activeChild.admin_fee_status === 'Menunggu Verifikasi TU';

                  return (
                    <tr key={fee.id} className="hover:bg-slate-50/80 transition-colors">
                      <td className="py-2.5 px-3 font-bold text-slate-900">
                        {fee.item_name}
                      </td>
                      <td className="py-2.5 px-3 text-slate-600 whitespace-nowrap">
                        {fee.period_label}
                      </td>
                      <td className="py-2.5 px-3 text-right font-mono font-bold text-slate-900 whitespace-nowrap">
                        {fee.nominal_text}
                      </td>
                      <td className="py-2.5 px-2 text-center whitespace-nowrap">
                        <span className={`px-2 py-0.5 rounded text-[11px] font-bold ${
                          isPaid ? 'bg-emerald-800 text-white' :
                          isVerif ? 'bg-blue-800 text-white' : 'bg-amber-600 text-white'
                        }`}>
                          {fee.status}
                        </span>
                      </td>
                      <td className="py-2.5 px-3 font-mono font-bold text-slate-700 whitespace-nowrap">
                        {fee.receipt_number}
                      </td>
                      <td className="py-2.5 px-3 text-slate-700 whitespace-nowrap font-medium">
                        {fee.cashier_name}
                      </td>
                      <td className="py-2.5 px-3 text-slate-600 text-xs leading-relaxed max-w-xs">
                        {fee.notes}
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        </div>

        {/* KOLOM KANAN: SALURAN REKENING & KONFIRMASI KASIR (5 COLS - NO DEAD VOID) */}
        <div className="lg:col-span-5 bg-white border border-slate-200/90 rounded-2xl p-4 shadow-xs flex flex-col h-full min-h-97.5 justify-between">
          <div className="flex items-center justify-between pb-3 border-b border-slate-100 shrink-0">
            <div>
              <h2 className="text-sm font-bold text-slate-900 tracking-tight">
                Saluran Pembayaran & Konfirmasi TU
              </h2>
              <p className="text-xs text-slate-500 font-normal">
                Rekening giro resmi dan loket verifikasi Bagian Keuangan
              </p>
            </div>
            <span className="px-2.5 py-1 rounded-full bg-slate-800 text-white text-xs font-bold shadow-2xs">
              Giro Resmi
            </span>
          </div>

          {/* List of 2 Distinct Bank & Counter Cards */}
          <div className="flex-1 min-h-0 overflow-y-auto pr-1 my-1.5 space-y-2.5">
            {/* Card 1: Rekening Giro Bank Mandiri & BSI */}
            <div className="bg-white rounded-xl p-3 border border-slate-200/90 shadow-xs hover:shadow-sm transition-all border-l-4 border-l-blue-600 space-y-2">
              <div className="flex items-center justify-between pb-1.5 border-b border-slate-100">
                <div className="flex items-center gap-2">
                  <span className="px-2 py-0.5 rounded text-xs font-bold bg-blue-800 text-white">
                    Transfer Giro
                  </span>
                  <span className="text-[11px] font-bold text-slate-400 font-mono">
                    Saluran #1
                  </span>
                </div>
                <span className="text-[11px] text-slate-500 font-medium">
                  Berita: NISN {activeChild.nisn}
                </span>
              </div>

              {/* Sub-item Bank Mandiri */}
              <div className="p-2.5 bg-slate-50 rounded-lg border border-slate-200/80 flex items-center justify-between">
                <div>
                  <span className="text-[10px] font-bold text-slate-500 uppercase">Bank Mandiri (Giro)</span>
                  <p className="font-mono text-xs font-bold text-slate-900">144-00-1928374-1</p>
                  <p className="text-[10px] text-slate-500">a.n. BPP Labschool UM</p>
                </div>
                <button
                  type="button"
                  onClick={() => handleCopy('1440019283741', 'Bank Mandiri')}
                  className="px-2.5 py-1 rounded-md border border-slate-300 hover:bg-slate-200/70 text-slate-700 text-xs font-semibold transition-colors flex items-center gap-1 shrink-0"
                >
                  {copiedBank === 'Bank Mandiri' ? <Check className="w-3.5 h-3.5 text-emerald-700" /> : <Copy className="w-3.5 h-3.5" />}
                  <span>{copiedBank === 'Bank Mandiri' ? 'Tersalin' : 'Salin'}</span>
                </button>
              </div>

              {/* Sub-item BSI */}
              <div className="p-2.5 bg-slate-50 rounded-lg border border-slate-200/80 flex items-center justify-between">
                <div>
                  <span className="text-[10px] font-bold text-slate-500 uppercase">Bank Syariah Indonesia (BSI)</span>
                  <p className="font-mono text-xs font-bold text-slate-900">718-293-8472</p>
                  <p className="text-[10px] text-slate-500">a.n. Yayasan Labschool UM</p>
                </div>
                <button
                  type="button"
                  onClick={() => handleCopy('7182938472', 'BSI')}
                  className="px-2.5 py-1 rounded-md border border-slate-300 hover:bg-slate-200/70 text-slate-700 text-xs font-semibold transition-colors flex items-center gap-1 shrink-0"
                >
                  {copiedBank === 'BSI' ? <Check className="w-3.5 h-3.5 text-emerald-700" /> : <Copy className="w-3.5 h-3.5" />}
                  <span>{copiedBank === 'BSI' ? 'Tersalin' : 'Salin'}</span>
                </button>
              </div>
            </div>

            {/* Card 2: Loket Kasir Tata Usaha */}
            <div className="bg-white rounded-xl p-3 border border-slate-200/90 shadow-xs hover:shadow-sm transition-all border-l-4 border-l-emerald-600">
              <div className="flex items-center justify-between pb-1.5 mb-1.5 border-b border-slate-100">
                <div className="flex items-center gap-2">
                  <span className="px-2 py-0.5 rounded text-xs font-bold bg-emerald-800 text-white">
                    Loket Kasir Fisik
                  </span>
                  <span className="text-[11px] font-bold text-slate-400 font-mono">
                    Layanan #2
                  </span>
                </div>
                <span className="text-[11px] text-slate-500 font-medium">
                  Gedung A Lantai 1
                </span>
              </div>

              <div className="space-y-0.5">
                <div className="flex items-center gap-1.5">
                  <Building2 className="w-3.5 h-3.5 text-emerald-700 shrink-0" />
                  <p className="text-xs font-bold text-slate-900">
                    Kasir Tata Usaha & Cetak Kwitansi Resmi
                  </p>
                </div>
                <p className="text-xs text-slate-600 font-normal leading-relaxed pl-5">
                  Melayani verifikasi bukti setor bank, cetak kwitansi fisik cap basah, dan layanan dispensasi administrasi sekolah.
                </p>
              </div>

              <div className="mt-2 pt-1.5 border-t border-slate-100 flex items-center justify-between text-xs">
                <span className="text-[11px] text-slate-500">
                  Operasional: <strong>07.30 – 15.00 WIB</strong>
                </span>
                <span className="px-2 py-0.5 rounded text-[11px] font-bold bg-slate-800 text-white">
                  Loket 2 & 3
                </span>
              </div>
            </div>
          </div>

          {/* Solid Information Card & Fast Communication (Eliminates empty white space) */}
          <div className="pt-2 border-t border-slate-100 shrink-0 space-y-2">
            {/* Kebijakan Non-Finansial */}
            <div className="p-2.5 bg-slate-50 rounded-xl border border-slate-200/90 text-xs text-slate-700 space-y-0.5">
              <div className="flex items-center gap-1.5 font-bold text-slate-900 text-xs">
                <Info className="w-3.5 h-3.5 text-blue-800" />
                <span>Ketentuan Administrasi Non-Finansial:</span>
              </div>
              <p className="text-slate-600 leading-normal text-[11px] font-normal">
                Portal ini berfungsi sebagai buku kas transparansi resmi. Seluruh pembayaran diproses melalui transfer rekening giro resmi sekolah atau kasir TU.
              </p>
            </div>

            {/* Kontak Kasir WhatsApp */}
            <div className="p-2 bg-emerald-50/60 rounded-xl border border-emerald-200 text-xs text-slate-800 flex items-center justify-between">
              <div className="flex items-center gap-2">
                <Phone className="w-3.5 h-3.5 text-emerald-800 shrink-0" />
                <div>
                  <span className="font-bold text-slate-900 block leading-tight text-xs">Kasir Keuangan TU</span>
                  <span className="text-slate-600 text-[11px]">0812-3456-7899 (WhatsApp Konfirmasi)</span>
                </div>
              </div>
              <a
                href="https://wa.me/6281234567899"
                target="_blank"
                rel="noreferrer"
                className="px-2.5 py-1 rounded-lg bg-emerald-800 hover:bg-emerald-900 text-white text-xs font-bold shrink-0 transition-colors shadow-2xs flex items-center gap-1"
              >
                <span>Kirim Bukti</span>
                <ExternalLink className="w-3 h-3" />
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
