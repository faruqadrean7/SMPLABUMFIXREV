'use client';

import React, { useState } from 'react';
import { 
  FileSpreadsheet, 
  Download, 
  Eye, 
  Award, 
  CheckCircle2, 
  FileCheck, 
  Printer, 
  QrCode, 
  X, 
  School,
  AlertCircle,
  TrendingUp,
  BarChart3,
  Calendar,
  ShieldCheck,
  BookOpen,
  Check,
  Clock,
  PhoneCall
} from 'lucide-react';
import { toast } from 'sonner';
import { useGuardian } from '@/lib/guardian-context';

export default function GuardianNilaiPage() {
  const { guardian, activeChild, activeGrades, reportCardData, isLoading } = useGuardian();
  const [isPreviewOpen, setIsPreviewOpen] = useState(false);
  const [selectedReportPeriod, setSelectedReportPeriod] = useState('sts-gasal');
  const [filterStatus, setFilterStatus] = useState<'all' | 'tuntas' | 'remedial'>('all');

  // Dynamic calculations
  const totalSubjects = activeGrades.length;
  const tuntasCount = activeGrades.filter(g => g.status === 'Tuntas').length;
  const remedialCount = activeGrades.filter(g => g.status !== 'Tuntas').length;
  const maxScore = activeGrades.length > 0 ? Math.max(...activeGrades.map(g => g.final_score)) : 0;
  const topSubject = activeGrades.find(g => g.final_score === maxScore);

  // Filtered grades for table
  const filteredGrades = activeGrades.filter(g => {
    if (filterStatus === 'tuntas') return g.status === 'Tuntas';
    if (filterStatus === 'remedial') return g.status !== 'Tuntas';
    return true;
  });

  const handleDownloadPdf = () => {
    toast.success(`Mengunduh E-Progress Report resmi ${activeChild.name} (PDF)...`);
  };

  return (
    <div className="flex flex-col gap-3.5 max-w-full xl:h-[calc(100vh-112px)] xl:overflow-hidden">
      {/* ─── 1. HEADER BAR (DESKTOP MAXIMIZED & WELL-PROPORTIONED) ─── */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 shrink-0">
        <div>
          <div className="flex items-center gap-2.5">
            <h1 className="text-xl font-extrabold text-slate-900 tracking-tight">
              Capaian Kompetensi & Rapor ({activeChild.name.split(' ')[0]})
            </h1>
            <span className="px-2.5 py-0.5 rounded-lg bg-blue-800 text-white text-xs font-bold shadow-2xs">
              Kelas {activeChild.class_name.replace('Kelas ', '')}
            </span>
            <span className="px-2.5 py-0.5 rounded-lg bg-slate-100 border border-slate-300 text-slate-700 text-xs font-mono font-semibold">
              NIS: {activeChild.nis}
            </span>
            <span className="px-2.5 py-0.5 rounded-lg bg-emerald-800 text-white text-xs font-bold shadow-2xs hidden md:inline-flex">
              {activeChild.curriculum_phase}
            </span>
          </div>
          <p className="text-xs text-slate-600 mt-1 font-normal">
            Buku nilai transparan asesmen formatif, sumatif tengah semester (STS), dan pengesahan e-rapor berkode QR resmi.
          </p>
        </div>

        <div className="flex items-center gap-2.5 self-start sm:self-auto shrink-0">
          <button
            onClick={() => setIsPreviewOpen(true)}
            className="px-3.5 py-2.5 rounded-xl bg-blue-900 hover:bg-blue-950 text-white text-xs font-bold transition-all shadow-sm flex items-center gap-2 hover:shadow"
          >
            <Eye className="w-4 h-4 text-white" />
            <span>Pratinjau E-Rapor</span>
          </button>
          <button
            onClick={handleDownloadPdf}
            className="px-3.5 py-2.5 rounded-xl bg-emerald-800 hover:bg-emerald-900 text-white text-xs font-bold transition-all shadow-sm flex items-center gap-2 hover:shadow"
          >
            <Download className="w-4 h-4 text-white" />
            <span>Unduh PDF</span>
          </button>
        </div>
      </div>

      {/* ─── 2. REKAPITULASI CAPAIAN (PROPORTIONED DESKTOP CARDS - NO EMPTY VOID) ─── */}
      <div className="grid grid-cols-1 xl:grid-cols-12 gap-3 shrink-0">
        {/* Panel 1: Rerata Nilai Akhir (Hero Card - 4 Cols) */}
        <div className="xl:col-span-4 bg-white border border-slate-200/90 rounded-2xl p-4 shadow-xs flex flex-col justify-between">
          <div className="flex items-center justify-between pb-2.5 border-b border-slate-100">
            <div className="flex items-center gap-2.5">
              <div className="w-8 h-8 rounded-xl bg-blue-100 text-blue-800 flex items-center justify-center shrink-0 border border-blue-200">
                <BarChart3 className="w-4 h-4" />
              </div>
              <div>
                <h3 className="text-sm font-bold text-slate-900 tracking-tight">Rerata Capaian Nilai</h3>
                <p className="text-xs text-slate-500 font-normal">Semester Gasal 2026/2027</p>
              </div>
            </div>
            <span className="px-2.5 py-1 rounded-full text-xs font-bold bg-slate-800 text-white shadow-2xs">
              KKTP: 75.0
            </span>
          </div>

          <div className="my-2 flex items-baseline justify-between gap-2">
            <div className="flex items-baseline gap-2.5">
              <span className="text-3xl font-extrabold tracking-tight text-slate-900">
                {activeChild.grade_average}
              </span>
              <span className="text-xs font-semibold text-slate-500 font-mono">/ 100</span>
              <span className={`px-2.5 py-1 rounded-lg text-xs font-bold shadow-2xs ${
                remedialCount > 0 ? 'bg-amber-600 text-white' : 'bg-blue-800 text-white'
              }`}>
                {remedialCount > 0 ? 'Predikat B/C' : 'Predikat A (Sangat Baik)'}
              </span>
            </div>
            <span className="text-xs font-semibold text-slate-600">
              +{((activeChild.grade_average || 88.5) - 75).toFixed(1)} di atas KKTP
            </span>
          </div>

          {/* Progress Bar Track */}
          <div className="h-2 w-full bg-slate-100 rounded-full overflow-hidden border border-slate-200/60 mb-2">
            <div 
              style={{ width: `${Math.min(100, activeChild.grade_average)}%` }} 
              className={`h-full rounded-full ${remedialCount > 0 ? 'bg-amber-600' : 'bg-blue-800'}`}
            />
          </div>

          {/* Status Ketercapaian Semester Banner */}
          <div className={`p-2.5 rounded-xl border flex items-center justify-between gap-2 text-xs ${
            remedialCount === 0 
              ? 'bg-emerald-50/70 border-emerald-200 text-slate-900' 
              : 'bg-amber-50/70 border-amber-200 text-slate-900'
          }`}>
            <div className="flex items-center gap-2 min-w-0">
              {remedialCount === 0 ? (
                <CheckCircle2 className="w-4 h-4 shrink-0 text-emerald-700" />
              ) : (
                <AlertCircle className="w-4 h-4 shrink-0 text-amber-700" />
              )}
              <p className="font-semibold text-slate-900 truncate">
                {remedialCount === 0 
                  ? 'Seluruh kompetensi tuntas memenuhi KKTP kelulusan' 
                  : `${remedialCount} Mata pelajaran membutuhkan bimbingan remedial`}
              </p>
            </div>
            <span className={`px-2 py-0.5 rounded-md text-xs font-bold shrink-0 uppercase tracking-wide ${
              remedialCount === 0 ? 'bg-emerald-800 text-white' : 'bg-amber-600 text-white'
            }`}>
              {remedialCount === 0 ? 'Tuntas Penuh' : 'Remedial'}
            </span>
          </div>
        </div>

        {/* Panel 2: 5 Metrik Akademik dalam 1 Baris Seimbang (8 Cols, Rich Font & Spacing) */}
        <div className="xl:col-span-8 grid grid-cols-2 sm:grid-cols-5 gap-3">
          {/* 1. Ketuntasan Belajar */}
          <div className="bg-white border border-slate-200/90 rounded-2xl p-3.5 shadow-xs flex flex-col justify-between hover:border-slate-300 transition-all">
            <div className="flex items-center justify-between">
              <span className="text-xs font-bold text-slate-700 uppercase tracking-wider truncate">Ketuntasan</span>
              <div className="w-8 h-8 rounded-xl bg-emerald-100 text-emerald-800 flex items-center justify-center shrink-0 border border-emerald-200">
                <CheckCircle2 className="w-4 h-4" />
              </div>
            </div>
            <div className="my-1.5">
              <span className="text-3xl font-extrabold tracking-tight text-slate-900">
                {totalSubjects > 0 ? Math.round((tuntasCount / totalSubjects) * 100) : 100}%
              </span>
              <p className="text-xs text-slate-500 font-medium mt-0.5">
                {tuntasCount} dari {totalSubjects} Mapel
              </p>
            </div>
            <div className="pt-2 border-t border-slate-100 flex items-center justify-between">
              <span className="text-xs font-medium text-slate-500">Standar</span>
              <span className="px-2 py-0.5 rounded-md text-xs font-semibold bg-emerald-800 text-white">
                {remedialCount === 0 ? 'Sempurna' : 'Memenuhi'}
              </span>
            </div>
          </div>

          {/* 2. Mapel Remedial */}
          <div className="bg-white border border-slate-200/90 rounded-2xl p-3.5 shadow-xs flex flex-col justify-between hover:border-slate-300 transition-all">
            <div className="flex items-center justify-between">
              <span className="text-xs font-bold text-slate-700 uppercase tracking-wider truncate">Remedial</span>
              <div className="w-8 h-8 rounded-xl bg-amber-100 text-amber-800 flex items-center justify-center shrink-0 border border-amber-200">
                <AlertCircle className="w-4 h-4" />
              </div>
            </div>
            <div className="my-1.5">
              <span className="text-3xl font-extrabold tracking-tight text-slate-900">
                {remedialCount}
              </span>
              <span className="text-xs font-semibold text-slate-500 ml-1.5">Mapel</span>
              <p className="text-xs text-slate-500 font-medium mt-0.5">
                {remedialCount > 0 ? 'B. Inggris (72.0)' : 'Nihil Remedial'}
              </p>
            </div>
            <div className="pt-2 border-t border-slate-100 flex items-center justify-between">
              <span className="text-xs font-medium text-slate-500">Status</span>
              <span className={`px-2 py-0.5 rounded-md text-xs font-semibold ${
                remedialCount > 0 ? 'bg-amber-600 text-white' : 'bg-slate-800 text-white'
              }`}>
                {remedialCount > 0 ? 'Perlu Jadwal' : 'Tuntas'}
              </span>
            </div>
          </div>

          {/* 3. Nilai Tertinggi */}
          <div className="bg-white border border-slate-200/90 rounded-2xl p-3.5 shadow-xs flex flex-col justify-between hover:border-slate-300 transition-all">
            <div className="flex items-center justify-between">
              <span className="text-xs font-bold text-slate-700 uppercase tracking-wider truncate">Tertinggi</span>
              <div className="w-8 h-8 rounded-xl bg-blue-100 text-blue-800 flex items-center justify-center shrink-0 border border-blue-200">
                <Award className="w-4 h-4" />
              </div>
            </div>
            <div className="my-1.5">
              <span className="text-3xl font-extrabold tracking-tight text-slate-900">
                {maxScore}
              </span>
              <span className="text-xs font-semibold text-slate-500 ml-1.5">Skor</span>
              <p className="text-xs text-slate-500 font-medium mt-0.5 truncate">
                {topSubject?.subject_code} • {topSubject?.subject_name.slice(0, 11)}..
              </p>
            </div>
            <div className="pt-2 border-t border-slate-100 flex items-center justify-between">
              <span className="text-xs font-medium text-slate-500">Kategori</span>
              <span className="px-2 py-0.5 rounded-md text-xs font-semibold bg-blue-800 text-white">
                Unggulan
              </span>
            </div>
          </div>

          {/* 4. Poin Karakter (P3) */}
          <div className="bg-white border border-slate-200/90 rounded-2xl p-3.5 shadow-xs flex flex-col justify-between hover:border-slate-300 transition-all">
            <div className="flex items-center justify-between">
              <span className="text-xs font-bold text-slate-700 uppercase tracking-wider truncate">Karakter P3</span>
              <div className="w-8 h-8 rounded-xl bg-teal-100 text-teal-800 flex items-center justify-center shrink-0 border border-teal-200">
                <TrendingUp className="w-4 h-4" />
              </div>
            </div>
            <div className="my-1.5">
              <span className="text-3xl font-extrabold tracking-tight text-slate-900">
                +{activeChild.points_balance}
              </span>
              <span className="text-xs font-semibold text-slate-500 ml-1.5">Poin</span>
              <p className="text-xs text-slate-500 font-medium mt-0.5">
                Profil Pelajar Pancasila
              </p>
            </div>
            <div className="pt-2 border-t border-slate-100 flex items-center justify-between">
              <span className="text-xs font-medium text-slate-500">Predikat</span>
              <span className="px-2 py-0.5 rounded-md text-xs font-semibold bg-teal-800 text-white">
                Sangat Baik
              </span>
            </div>
          </div>

          {/* 5. Status E-Rapor Digital */}
          <div className="bg-white border border-slate-200/90 rounded-2xl p-3.5 shadow-xs flex flex-col justify-between hover:border-slate-300 transition-all">
            <div className="flex items-center justify-between">
              <span className="text-xs font-bold text-slate-700 uppercase tracking-wider truncate">E-Rapor</span>
              <div className="w-8 h-8 rounded-xl bg-slate-100 text-slate-800 flex items-center justify-center shrink-0 border border-slate-200">
                <QrCode className="w-4 h-4" />
              </div>
            </div>
            <div className="my-1.5">
              <span className="text-3xl font-extrabold tracking-tight text-slate-900">
                SAH
              </span>
              <p className="text-xs text-slate-500 font-medium mt-0.5">
                Token: REP-{activeChild.nis}
              </p>
            </div>
            <div className="pt-2 border-t border-slate-100 flex items-center justify-between">
              <span className="text-xs font-medium text-slate-500">Legalitas</span>
              <span className="px-2 py-0.5 rounded-md text-xs font-semibold bg-emerald-800 text-white">
                Terverifikasi
              </span>
            </div>
          </div>
        </div>
      </div>

      {/* ─── 3. DUA KOLOM: TABEL KOMPETENSI & RINGKASAN RAPOR (MAXIMIZED DESKTOP) ─── */}
      <div className="xl:flex-1 xl:min-h-0 grid grid-cols-1 lg:grid-cols-12 gap-3.5">
        {/* KOLOM KIRI: BUKU NILAI & ASESMEN KURIKULUM MERDEKA (7 COLS) */}
        <div className="lg:col-span-7 bg-white border border-slate-200/90 rounded-2xl p-4 shadow-xs flex flex-col h-full min-h-97.5">
          {/* Header Bar dengan Filter Status & Pilihan Periode */}
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-100 shrink-0">
            <div>
              <div className="flex items-center gap-2">
                <h2 className="text-sm font-bold text-slate-900 tracking-tight">
                  Daftar Nilai Capaian Kompetensi ({activeChild.name.split(' ')[0]})
                </h2>
                <span className="px-2 py-0.5 rounded-full bg-slate-800 text-white text-xs font-bold">
                  {totalSubjects} Mapel
                </span>
              </div>
              <p className="text-xs text-slate-500 font-normal mt-0.5">
                Buku nilai asesmen formatif, sumatif materi (LM), dan STS berstandar KKTP 75.0.
              </p>
            </div>

            {/* Filter Tabs & Period Selector */}
            <div className="flex items-center gap-2 shrink-0 self-start sm:self-auto">
              <div className="flex items-center p-1 bg-slate-100 border border-slate-200 rounded-xl">
                <button
                  onClick={() => setFilterStatus('all')}
                  className={`px-2.5 py-1 rounded-lg text-xs font-bold transition-all ${
                    filterStatus === 'all'
                      ? 'bg-white text-slate-900 shadow-xs border border-slate-200'
                      : 'text-slate-600 hover:text-slate-900'
                  }`}
                >
                  Semua ({totalSubjects})
                </button>
                <button
                  onClick={() => setFilterStatus('tuntas')}
                  className={`px-2.5 py-1 rounded-lg text-xs font-bold transition-all ${
                    filterStatus === 'tuntas'
                      ? 'bg-white text-slate-900 shadow-xs border border-slate-200'
                      : 'text-slate-600 hover:text-slate-900'
                  }`}
                >
                  Tuntas ({tuntasCount})
                </button>
                <button
                  onClick={() => setFilterStatus('remedial')}
                  className={`px-2.5 py-1 rounded-lg text-xs font-bold transition-all ${
                    filterStatus === 'remedial'
                      ? 'bg-white text-slate-900 shadow-xs border border-slate-200'
                      : 'text-slate-600 hover:text-slate-900'
                  }`}
                >
                  Remedial ({remedialCount})
                </button>
              </div>
            </div>
          </div>

          {/* Table Container with Internal Scroll & Sticky Header */}
          <div className="flex-1 min-h-0 overflow-y-auto pr-1 mt-2.5 border border-slate-200 rounded-xl">
            <table className="w-full text-left text-xs border-collapse">
              <thead className="sticky top-0 bg-slate-100 z-10 shadow-2xs">
                <tr className="border-b border-slate-200 text-slate-700 font-bold uppercase tracking-wider text-[11px]">
                  <th className="py-2.5 px-3">Mata Pelajaran & Guru</th>
                  <th className="py-2.5 px-2 text-center">KKTP</th>
                  <th className="py-2.5 px-2 text-center">Formatif</th>
                  <th className="py-2.5 px-2 text-center">Sumatif</th>
                  <th className="py-2.5 px-2 text-center">STS</th>
                  <th className="py-2.5 px-2.5 text-center">Akhir</th>
                  <th className="py-2.5 px-2 text-center">Predikat</th>
                  <th className="py-2.5 px-2.5 text-center">Status</th>
                  <th className="py-2.5 px-3">Deskripsi Capaian Kompetensi</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 font-normal">
                {filteredGrades.map((grade) => (
                  <tr key={grade.subject_code} className="hover:bg-slate-50/80 transition-colors">
                    <td className="py-2.5 px-3">
                      <p className="font-bold text-slate-900 text-xs">{grade.subject_name}</p>
                      <p className="text-[11px] text-slate-500 font-normal mt-0.5">
                        {grade.teacher_name} • <span className="font-mono text-slate-600">{grade.subject_code}</span>
                      </p>
                    </td>
                    <td className="py-2.5 px-2 text-center font-mono font-medium text-slate-500">
                      {grade.kktp}
                    </td>
                    <td className="py-2.5 px-2 text-center font-mono font-bold text-slate-800">
                      {grade.formative_avg}
                    </td>
                    <td className="py-2.5 px-2 text-center font-mono font-bold text-slate-800">
                      {grade.summative_avg}
                    </td>
                    <td className="py-2.5 px-2 text-center font-mono font-bold text-blue-900">
                      {grade.sts_score}
                    </td>
                    <td className="py-2.5 px-2.5 text-center font-mono font-extrabold text-slate-900 text-xs">
                      {grade.final_score}
                    </td>
                    <td className="py-2.5 px-2 text-center">
                      <span className="px-2 py-0.5 rounded text-[11px] font-bold bg-slate-800 text-white font-mono">
                        {grade.predicate}
                      </span>
                    </td>
                    <td className="py-2.5 px-2.5 text-center">
                      <span className={`px-2 py-0.5 rounded text-[11px] font-bold ${
                        grade.status === 'Tuntas' ? 'bg-emerald-800 text-white' : 'bg-amber-600 text-white'
                      }`}>
                        {grade.status}
                      </span>
                    </td>
                    <td className="py-2.5 px-3 text-slate-600 text-xs leading-relaxed max-w-xs">
                      {grade.competency_desc}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>

        {/* KOLOM KANAN: RINGKASAN E-RAPOR & CATATAN WALI KELAS (5 COLS - NO DEAD VOID) */}
        <div className="lg:col-span-5 bg-white border border-slate-200/90 rounded-2xl p-4 shadow-xs flex flex-col h-full min-h-97.5 justify-between">
          <div className="flex items-center justify-between pb-3 border-b border-slate-100 shrink-0">
            <div>
              <h2 className="text-sm font-bold text-slate-900 tracking-tight">
                Ringkasan E-Rapor & Tindak Lanjut
              </h2>
              <p className="text-xs text-slate-500 font-normal">
                Verifikasi resmi oleh Wali Kelas ({activeChild.homeroom_teacher.split(',')[0]})
              </p>
            </div>
            <span className="px-2.5 py-1 rounded-full bg-slate-800 text-white text-xs font-bold shadow-2xs">
              STS Gasal 2026
            </span>
          </div>

          {/* List of 2 Distinct Document & Recommendation Cards */}
          <div className="flex-1 min-h-0 overflow-y-auto pr-1 my-1.5 space-y-2.5">
            {/* Card 1: Pengesahan Dokumen E-Rapor Digital */}
            <div className="bg-white rounded-xl p-3 border border-slate-200/90 shadow-xs hover:shadow-sm transition-all border-l-4 border-l-blue-600">
              {/* Header Row */}
              <div className="flex items-center justify-between pb-1.5 mb-1.5 border-b border-slate-100">
                <div className="flex items-center gap-2">
                  <span className="px-2 py-0.5 rounded text-xs font-bold bg-blue-800 text-white">
                    E-Progress Report
                  </span>
                  <span className="text-[11px] font-bold text-slate-400 font-mono">
                    Dokumen #1
                  </span>
                </div>
                <span className="text-[11px] text-slate-500 font-medium">
                  Disahkan: 14 Okt 2026
                </span>
              </div>

              {/* Document Meta */}
              <div className="space-y-0.5">
                <div className="flex items-center gap-1.5">
                  <FileCheck className="w-3.5 h-3.5 text-blue-700 shrink-0" />
                  <p className="text-xs font-bold text-slate-900">
                    Laporan Hasil Belajar Tengah Semester Gasal
                  </p>
                </div>
                <p className="text-xs text-slate-600 font-normal leading-relaxed pl-5">
                  Kurikulum Merdeka ({activeChild.curriculum_phase}) • NISN: {activeChild.nisn} • Rerata: <strong>{activeChild.grade_average}</strong>
                </p>
              </div>

              {/* QR Verification Chip */}
              <div className="mt-1.5 ml-5 flex items-center justify-between text-[11px] font-semibold text-blue-950 bg-blue-50 px-2.5 py-1 rounded-md border border-blue-200">
                <div className="flex items-center gap-1.5">
                  <QrCode className="w-3.5 h-3.5 text-blue-800" />
                  <span>TOKEN: VERIFY-REP-{activeChild.nis}-2026</span>
                </div>
                <span className="text-[10px] text-emerald-800 font-bold uppercase">QR Valid</span>
              </div>

              {/* Footer Row */}
              <div className="mt-2 pt-1.5 border-t border-slate-100 flex items-center justify-between text-xs">
                <span className="px-2 py-0.5 rounded text-[11px] font-bold bg-emerald-800 text-white">
                  Telah Disahkan Kepsek & Walas
                </span>
                <button
                  onClick={() => setIsPreviewOpen(true)}
                  className="text-xs font-bold text-blue-800 hover:text-blue-950 flex items-center gap-1"
                >
                  <Eye className="w-3.5 h-3.5" />
                  <span>Pratinjau Lembar A4</span>
                </button>
              </div>
            </div>

            {/* Card 2: Catatan Akademik & Bimbingan Wali Kelas */}
            <div className="bg-white rounded-xl p-3 border border-slate-200/90 shadow-xs hover:shadow-sm transition-all border-l-4 border-l-emerald-600">
              {/* Header Row */}
              <div className="flex items-center justify-between pb-1.5 mb-1.5 border-b border-slate-100">
                <div className="flex items-center gap-2">
                  <span className="px-2 py-0.5 rounded text-xs font-bold bg-emerald-800 text-white">
                    Catatan Wali Kelas
                  </span>
                  <span className="text-[11px] font-bold text-slate-400 font-mono">
                    Ulasan #2
                  </span>
                </div>
                <span className="text-[11px] text-slate-500 font-medium">
                  Kelas IX-A
                </span>
              </div>

              {/* Recommendation Content */}
              <div className="space-y-0.5">
                <div className="flex items-center gap-1.5">
                  <Award className="w-3.5 h-3.5 text-emerald-700 shrink-0" />
                  <p className="text-xs font-bold text-slate-900">
                    Evaluasi & Arahan Pembelajaran Siswa
                  </p>
                </div>
                <p className="text-xs text-slate-600 font-normal leading-relaxed pl-5">
                  {remedialCount > 0 ? (
                    <>
                      Ananda {activeChild.name.split(' ')[0]} menunjukkan pemahaman yang sangat kuat pada bidang sains & informatika. Mohon pendampingan belajar di rumah untuk persiapan asesmen remedial Bahasa Inggris agar tuntas KKTP 75.0.
                    </>
                  ) : (
                    <>
                      Prestasi akademik ananda {activeChild.name.split(' ')[0]} sangat memuaskan dan konsisten di seluruh mata pelajaran. Pertahankan kebiasaan belajar positif dan keaktifan di kelas.
                    </>
                  )}
                </p>
              </div>

              {/* Footer Row */}
              <div className="mt-2 pt-1.5 border-t border-slate-100 flex items-center justify-between text-xs">
                <span className="px-2 py-0.5 rounded text-[11px] font-bold bg-blue-800 text-white">
                  Rekomendasi Walas
                </span>
                <span className="text-[11px] text-slate-600 font-medium">
                  Oleh: <strong className="text-slate-800 font-semibold">{activeChild.homeroom_teacher.split('(')[0]}</strong>
                </span>
              </div>
            </div>
          </div>

          {/* Solid Information Card & Fast Actions (Eliminates empty white space) */}
          <div className="pt-2 border-t border-slate-100 shrink-0 space-y-2">
            {/* Kebijakan Remedial & Pengayaan */}
            <div className="p-2.5 bg-slate-50 rounded-xl border border-slate-200/90 text-xs text-slate-700 space-y-0.5">
              <div className="flex items-center gap-1.5 font-bold text-slate-900 text-xs">
                <BookOpen className="w-3.5 h-3.5 text-blue-800" />
                <span>Ketentuan Remedial Kurikulum Merdeka Labschool UM:</span>
              </div>
              <p className="text-slate-600 leading-normal text-[11px] font-normal">
                • Remedial terjadwal maksimal 2x pengulangan untuk mencapai batas KKTP 75.0.<br />
                • Siswa dengan predikat A berhak mengikuti program bimbingan pengayaan olimpiade.
              </p>
            </div>

            {/* Bantuan Akademik & Unduh Cepat */}
            <div className="p-2 bg-blue-50/60 rounded-xl border border-blue-200 text-xs text-slate-800 flex items-center justify-between">
              <div className="flex items-center gap-2">
                <PhoneCall className="w-3.5 h-3.5 text-blue-800 shrink-0" />
                <div>
                  <span className="font-bold text-slate-900 block leading-tight text-xs">Konsultasi Belajar Walas</span>
                  <span className="text-slate-600 text-[11px]">Jadwal konsultasi akademik wali murid: 13.00 - 15.30 WIB</span>
                </div>
              </div>
              <button
                onClick={handleDownloadPdf}
                className="px-2.5 py-1 rounded-lg bg-emerald-800 hover:bg-emerald-900 text-white text-xs font-bold shrink-0 transition-colors shadow-2xs flex items-center gap-1"
              >
                <Download className="w-3.5 h-3.5" />
                <span>Unduh PDF</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      {/* ─── MODAL PRATINJAU DOKUMEN E-RAPOR RESMI BER-QR CODE (A4) ─── */}
      {isPreviewOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 overflow-y-auto">
          <div className="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-3xl w-full my-8 overflow-hidden animate-in fade-in zoom-in-95 duration-150">
            {/* Modal Bar */}
            <div className="px-6 py-4 bg-slate-900 text-white flex items-center justify-between">
              <div className="flex items-center gap-2.5">
                <FileCheck className="w-5 h-5 text-emerald-400" />
                <div>
                  <h3 className="text-sm font-bold text-white">
                    Lembar Resmi E-Progress Report Siswa (T.A. 2026/2027)
                  </h3>
                  <p className="text-xs text-slate-300 font-normal">
                    Dokumen Sah Sekolah Laboratorium Universitas Negeri Malang
                  </p>
                </div>
              </div>
              <div className="flex items-center gap-2">
                <button
                  onClick={handleDownloadPdf}
                  className="px-3.5 py-1.5 rounded-xl bg-emerald-800 hover:bg-emerald-900 text-white text-xs font-bold transition-colors flex items-center gap-1.5"
                >
                  <Download className="w-3.5 h-3.5" />
                  <span>Unduh PDF</span>
                </button>
                <button
                  onClick={() => setIsPreviewOpen(false)}
                  className="p-1.5 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 transition-colors"
                >
                  <X className="w-5 h-5" />
                </button>
              </div>
            </div>

            {/* Official Report Document Body (A4 Style) */}
            <div className="p-8 space-y-6 text-slate-900 font-serif bg-white">
              {/* Kop Surat Sekolah */}
              <div className="flex items-center justify-between border-b-2 border-slate-900 pb-4">
                <div className="w-16 h-16 rounded-xl bg-blue-950 text-white font-bold text-xl flex items-center justify-center shrink-0">
                  <School className="w-8 h-8 text-white" />
                </div>
                <div className="text-center flex-1 px-4">
                  <h4 className="text-base font-bold tracking-wide uppercase text-slate-900">
                    YAYASAN PEMBINA PENDIDIKAN LABSCHOOL UM
                  </h4>
                  <h3 className="text-lg font-bold tracking-tight text-blue-950">
                    LABORATORIUM UNIVERSITAS NEGERI MALANG
                  </h3>
                  <p className="text-xs font-sans text-slate-600">
                    Jl. Simpang Bogor No. 19, Lowokwaru, Kota Malang | Telp: (0341) 551312 | Web: labschool.um.ac.id
                  </p>
                </div>
                <div className="w-16 h-16 border border-slate-300 rounded-xl p-1 flex items-center justify-center bg-slate-50">
                  <QrCode className="w-12 h-12 text-slate-800" />
                </div>
              </div>

              {/* Title Document */}
              <div className="text-center font-sans space-y-1">
                <h4 className="text-sm font-bold uppercase tracking-wider text-slate-900">
                  LAPORAN HASIL KEMAJUAN BELAJAR SISWA (E-PROGRESS REPORT)
                </h4>
                <p className="text-xs text-slate-600 font-normal">
                  Semester Gasal Tahun Ajaran 2026/2027 • {activeChild.curriculum_phase}
                </p>
              </div>

              {/* Student Metadata Table */}
              <div className="grid grid-cols-2 gap-4 font-sans text-xs bg-slate-50 p-4 rounded-xl border border-slate-200">
                <div className="space-y-1">
                  <p><span className="font-semibold text-slate-600 inline-block w-28">Nama Siswa:</span> <span className="font-bold text-slate-900">{activeChild.name}</span></p>
                  <p><span className="font-semibold text-slate-600 inline-block w-28">NIS / NISN:</span> <span className="font-mono">{activeChild.nis} / {activeChild.nisn}</span></p>
                  <p><span className="font-semibold text-slate-600 inline-block w-28">Kelas / Fase:</span> <span className="font-semibold">{activeChild.class_name} ({activeChild.curriculum_phase})</span></p>
                </div>
                <div className="space-y-1">
                  <p><span className="font-semibold text-slate-600 inline-block w-28">Wali Kelas:</span> <span className="font-semibold">{activeChild.homeroom_teacher}</span></p>
                  <p><span className="font-semibold text-slate-600 inline-block w-28">Kehadiran:</span> <span className="font-semibold">{activeChild.attendance_recap.rate}% ({activeChild.attendance_recap.hadir} Hadir)</span></p>
                  <p><span className="font-semibold text-slate-600 inline-block w-28">Poin Karakter:</span> <span className="font-semibold">{activeChild.points_balance >= 0 ? `+${activeChild.points_balance}` : activeChild.points_balance} Poin</span></p>
                </div>
              </div>

              {/* Compact Grades Table */}
              <div className="font-sans">
                <table className="w-full text-left text-xs border border-slate-300 rounded-lg overflow-hidden">
                  <thead>
                    <tr className="bg-slate-100 font-semibold border-b border-slate-300 text-slate-700">
                      <th className="py-2 px-2.5">No</th>
                      <th className="py-2 px-2.5">Mata Pelajaran</th>
                      <th className="py-2 px-2 text-center">KKTP</th>
                      <th className="py-2 px-2 text-center">Nilai Akhir</th>
                      <th className="py-2 px-2 text-center">Predikat</th>
                      <th className="py-2 px-3">Capaian Kompetensi</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-200">
                    {activeGrades.map((g, idx) => (
                      <tr key={g.subject_code}>
                        <td className="py-1.5 px-2.5 font-medium">{idx + 1}</td>
                        <td className="py-1.5 px-2.5 font-semibold text-slate-900">{g.subject_name}</td>
                        <td className="py-1.5 px-2 text-center font-mono">{g.kktp}</td>
                        <td className="py-1.5 px-2 text-center font-mono font-bold text-slate-900">{g.final_score}</td>
                        <td className="py-1.5 px-2 text-center font-semibold text-slate-800">{g.predicate}</td>
                        <td className="py-1.5 px-3 text-[11px] text-slate-600 truncate max-w-xs">{g.competency_desc}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>

              {/* Signatures & Verification Token Footer */}
              <div className="pt-4 border-t border-slate-300 font-sans grid grid-cols-3 gap-4 text-center text-xs">
                <div>
                  <p className="text-slate-600">Mengetahui Orang Tua,</p>
                  <p className="mt-12 font-bold text-slate-900 underline">{guardian.name}</p>
                </div>

                <div className="flex flex-col items-center justify-center p-2 rounded-xl bg-slate-50 border border-slate-200">
                  <QrCode className="w-10 h-10 text-slate-900" />
                  <p className="text-[9px] font-mono font-semibold mt-1 text-slate-600">
                    TOKEN: {reportCardData?.verifyToken || `VERIFY-REP-${activeChild.nis}-2026`}
                  </p>
                  <span className="text-[9px] text-emerald-800 font-semibold">
                    Tervalidasi Sistem Satu Pintu
                  </span>
                </div>

                <div>
                  <p className="text-slate-600">Kota Malang, 14 Oktober 2026</p>
                  <p className="text-slate-600">Wali Kelas,</p>
                  <p className="mt-8 font-bold text-slate-900 underline">{activeChild.homeroom_teacher}</p>
                  <p className="text-[10px] text-slate-500 font-mono">{activeChild.homeroom_nip}</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
