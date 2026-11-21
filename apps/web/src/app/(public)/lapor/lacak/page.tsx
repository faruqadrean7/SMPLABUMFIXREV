import React, { Suspense } from 'react';
import type { Metadata } from 'next';
import Link from 'next/link';
import { Search, ChevronRight, FileEdit, RefreshCw, Shield } from 'lucide-react';
import { ComplaintTracker } from '@/features/public-relations/components';

export const metadata: Metadata = {
  title: 'Lacak Status Pengaduan',
  description:
    'Lacak status tindak lanjut pengaduan dan laporan perundungan Anda menggunakan kode tiket resmi.',
};

function TrackerFallback() {
  return (
    <div className="max-w-3xl mx-auto p-12 text-center text-slate-400 bg-white border border-slate-200 rounded-xl shadow-xs">
      <RefreshCw className="w-8 h-8 animate-spin mx-auto mb-3 text-pub-navy" />
      <p className="text-sm font-medium text-slate-600">Memuat modul pelacakan pengaduan...</p>
    </div>
  );
}

export default function LaporLacakPage() {
  return (
    <div className="min-h-screen bg-slate-50 flex flex-col">
      {/* HERO SECTION */}
      <section className="bg-gradient-to-r from-pub-navy-deep via-pub-navy to-pub-navy-light text-white py-10 sm:py-14 border-b border-white/10">
        <div className="max-w-4xl mx-auto px-4 sm:px-6">
          {/* Breadcrumb */}
          <nav aria-label="Navigasi Halaman" className="mb-4">
            <ol className="flex items-center gap-2 text-xs text-white/70">
              <li>
                <Link href="/" className="hover:text-white transition-colors">
                  Beranda
                </Link>
              </li>
              <ChevronRight className="w-3 h-3 text-white/40" />
              <li>
                <Link href="/lapor" className="hover:text-white transition-colors">
                  Lapor & Pengaduan
                </Link>
              </li>
              <ChevronRight className="w-3 h-3 text-white/40" />
              <li aria-current="page" className="font-semibold text-white">
                Lacak Status
              </li>
            </ol>
          </nav>

          <div className="flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div className="space-y-2">
              <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/10 backdrop-blur-xs text-xs font-medium text-emerald-300 border border-white/10">
                <Shield className="w-3.5 h-3.5" />
                <span>Transparansi & Akuntabilitas Penanganan</span>
              </div>
              <h1 className="font-pub-heading text-2xl sm:text-3xl md:text-4xl font-bold tracking-tight">
                Lacak Status Pengaduan
              </h1>
              <p className="text-white/80 text-sm max-w-xl leading-relaxed">
                Pantau perkembangan dan tindak lanjut laporan yang telah Anda sampaikan secara transparan dengan memasukkan kode tiket resmi.
              </p>
            </div>

            {/* TAB NAVIGATOR QUICK LINK */}
            <div className="shrink-0 flex sm:flex-col gap-2">
              <Link
                href="/lapor"
                className="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg text-xs font-semibold bg-white/10 text-white hover:bg-white/20 transition-colors border border-white/15"
              >
                <FileEdit className="w-4 h-4 text-white/80" />
                <span>Formulir Lapor</span>
              </Link>
              <Link
                href="/lapor/lacak"
                className="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg text-xs font-semibold bg-white text-pub-navy-deep shadow-sm"
              >
                <Search className="w-4 h-4 text-pub-navy" />
                <span>Lacak Status Tiket</span>
              </Link>
            </div>
          </div>
        </div>
      </section>

      {/* MAIN CONTENT AREA */}
      <main className="flex-1 py-10 px-4 sm:px-6">
        <Suspense fallback={<TrackerFallback />}>
          <ComplaintTracker />
        </Suspense>
      </main>
    </div>
  );
}
