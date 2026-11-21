import React from 'react';
import type { Metadata } from 'next';
import Link from 'next/link';
import { Shield, Search, ChevronRight, FileEdit, CheckCircle2 } from 'lucide-react';
import { ComplaintForm } from '@/features/public-relations/components';

export const metadata: Metadata = {
  title: 'Lapor Perundungan & Pengaduan Aman',
  description:
    'Layanan pengaduan perundungan (bullying) dan aspirasi civitas sekolah SMP Laboratorium UM dengan perlindungan kerahasiaan whistleblower.',
};

export default function LaporPage() {
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
              <li aria-current="page" className="font-semibold text-white">
                Lapor & Pengaduan
              </li>
            </ol>
          </nav>

          <div className="flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div className="space-y-2">
              <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/10 backdrop-blur-xs text-xs font-medium text-emerald-300 border border-white/10">
                <Shield className="w-3.5 h-3.5" />
                <span>Kanal Perlindungan Whistleblower Terjamin</span>
              </div>
              <h1 className="font-pub-heading text-2xl sm:text-3xl md:text-4xl font-bold tracking-tight">
                Layanan Pengaduan & Lapor Bullying
              </h1>
              <p className="text-white/80 text-sm max-w-xl leading-relaxed">
                Kanal resmi dan rahasia bagi siswa, orang tua, maupun masyarakat untuk menyampaikan dugaan perundungan, pelanggaran, atau aspirasi di lingkungan sekolah.
              </p>
            </div>

            {/* TAB NAVIGATOR QUICK LINK */}
            <div className="shrink-0 flex sm:flex-col gap-2">
              <Link
                href="/lapor"
                className="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg text-xs font-semibold bg-white text-pub-navy-deep shadow-sm"
              >
                <FileEdit className="w-4 h-4 text-pub-navy" />
                <span>Formulir Lapor</span>
              </Link>
              <Link
                href="/lapor/lacak"
                className="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg text-xs font-semibold bg-white/10 text-white hover:bg-white/20 transition-colors border border-white/15"
              >
                <Search className="w-4 h-4 text-white/80" />
                <span>Lacak Status Tiket</span>
              </Link>
            </div>
          </div>
        </div>
      </section>

      {/* MAIN CONTENT AREA */}
      <main className="flex-1 py-10 px-4 sm:px-6">
        <ComplaintForm />
      </main>
    </div>
  );
}
