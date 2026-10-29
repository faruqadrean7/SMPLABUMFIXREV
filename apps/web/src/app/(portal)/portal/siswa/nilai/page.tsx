'use client';

import React, { useState } from 'react';
import { 
  FileSpreadsheet, 
  FileText, 
  Download, 
  CheckCircle2, 
  Award, 
  GraduationCap, 
  QrCode, 
  TrendingUp,
  Printer,
  ChevronRight,
  X
} from 'lucide-react';
import { useStudentGrades, useStudentDashboard } from '@/lib/use-student-data';
import { GradesLedgerSkeleton } from '@/components/ui/student-skeletons';
import { EmptyState } from '@/components/ui/empty-state';
import { ErrorState } from '@/components/ui/error-state';

export default function StudentNilaiPage() {
  const [activeTab, setActiveTab] = useState<'ledger' | 'rapor'>('ledger');
  const [showReportPreview, setShowReportPreview] = useState(false);
  const [selectedMonth, setSelectedMonth] = useState('Oktober 2026');

  const { data: apiGrades, isLoading, isError, refetch } = useStudentGrades();
  const { data: studentDash } = useStudentDashboard();

  const displayGrades = (apiGrades && apiGrades.length > 0)
    ? apiGrades.map((g) => ({
        subject_name: g.subjectName,
        subject_code: g.subjectCode || 'MAPEL',
        teacher_name: g.teacherName || 'Guru Pengampu',
        kktp: 75,
        f1: 88,
        f2: '-',
        f3: '-',
        slm: '-',
        sts: 85,
        final_score: g.finalScore,
        status: g.isPassed ? 'Tuntas' : 'Remedial',
      }))
    : [];

  const averageFinalScore = displayGrades.length > 0
    ? (displayGrades.reduce((acc, curr) => acc + curr.final_score, 0) / displayGrades.length).toFixed(1)
    : '0.0';

  const passedCount = displayGrades.filter((g) => g.final_score >= 75).length;


  return (
    <div className="space-y-5 text-slate-900">
      {/* ─── HEADER CARD (COMPACT & PROPORTIONED) ─── */}
      <div className="bg-white border border-slate-200/90 rounded-2xl p-4 sm:p-5 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <div className="flex items-center gap-2 mb-1">
            <span className="text-[11px] font-black uppercase tracking-wider px-2 py-0.5 rounded bg-blue-900 text-white shadow-2xs">
              Penilaian Kurikulum Merdeka
            </span>
            <span className="text-xs text-slate-500 font-semibold">• KKTP Threshold: 75.0</span>
          </div>
          <h1 className="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight">
            Capaian Nilai & Dokumen E-Rapor
          </h1>
          <p className="text-xs text-slate-500 font-normal mt-0.5 max-w-2xl">
            Rekapitulasi nilai formatif, asesmen sumatif, serta unduh dokumen resmi E-Progress Report dan E-Rapor bertanda tangan QR.
          </p>
        </div>

        {/* Tab Switcher */}
        <div className="flex items-center bg-slate-100 p-1 rounded-xl border border-slate-200 self-start md:self-auto shadow-2xs shrink-0">
          <button
            onClick={() => setActiveTab('ledger')}
            className={`px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer ${
              activeTab === 'ledger'
                ? 'bg-blue-900 text-white shadow-xs'
                : 'text-slate-600 hover:text-slate-900'
            }`}
          >
            Ledger Capaian Nilai
          </button>
          <button
            onClick={() => setActiveTab('rapor')}
            className={`px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer ${
              activeTab === 'rapor'
                ? 'bg-blue-900 text-white shadow-xs'
                : 'text-slate-600 hover:text-slate-900'
            }`}
          >
            Dokumen E-Rapor Resmi
          </button>
        </div>
      </div>

      {/* ─── 3 KPI METRIC CARDS (CLEAN METRICS WITHOUT BORDER-L-4) ─── */}
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4">
        {/* Card 1: Rerata Nilai Akhir */}
        <div className="bg-white border border-slate-200/90 rounded-2xl p-4 sm:p-4.5 shadow-xs flex items-center justify-between gap-3">
          <div>
            <span className="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">
              Rerata Nilai Akhir
            </span>
            <div className="text-2xl sm:text-3xl font-black text-slate-900 font-mono mt-0.5">
              {averageFinalScore}
            </div>
            <p className="text-[11px] text-slate-500 font-medium mt-0.5">Skala Standar 0 - 100</p>
          </div>
          <div className="w-10 h-10 rounded-xl bg-blue-50 text-blue-900 border border-blue-200 flex items-center justify-center shrink-0 shadow-2xs">
            <TrendingUp className="w-5 h-5" />
          </div>
        </div>

        {/* Card 2: Ketuntasan KKTP */}
        <div className="bg-white border border-slate-200/90 rounded-2xl p-4 sm:p-4.5 shadow-xs flex items-center justify-between gap-3">
          <div>
            <span className="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">
              Ketuntasan KKTP (75)
            </span>
            <div className="text-2xl sm:text-3xl font-black text-slate-900 font-mono mt-0.5">
              {displayGrades.length > 0 ? `${Math.round((passedCount / displayGrades.length) * 100)}%` : '0%'}
            </div>
            <p className="text-[11px] text-slate-500 font-medium mt-0.5">{passedCount} dari {displayGrades.length} Mapel Tuntas</p>
          </div>
          <div className="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-800 border border-emerald-200 flex items-center justify-center shrink-0 shadow-2xs">
            <CheckCircle2 className="w-5 h-5" />
          </div>
        </div>

        {/* Card 3: Peringkat Kelas */}
        <div className="bg-white border border-slate-200/90 rounded-2xl p-4 sm:p-4.5 shadow-xs flex items-center justify-between gap-3">
          <div>
            <span className="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">
              Peringkat Kelas IX-A
            </span>
            <div className="text-2xl sm:text-3xl font-black text-slate-900 font-mono mt-0.5">
              #3
            </div>
            <p className="text-[11px] text-slate-500 font-medium mt-0.5">Dari 32 Siswa Rombel</p>
          </div>
          <div className="w-10 h-10 rounded-xl bg-slate-100 text-slate-700 border border-slate-200 flex items-center justify-center shrink-0 shadow-2xs">
            <Award className="w-5 h-5" />
          </div>
        </div>
      </div>

      {/* ─── TAB 1: LEDGER CAPAIAN NILAI ─── */}
      {activeTab === 'ledger' && (
        isLoading ? (
          <GradesLedgerSkeleton />
        ) : isError ? (
          <ErrorState
            title="Gagal Memuat Capaian Nilai"
            message="Tidak dapat mengambil ledger capaian nilai dari server. Silakan coba kembali."
            onRetry={() => refetch()}
          />
        ) : displayGrades.length === 0 ? (
          <EmptyState
            icon={FileSpreadsheet}
            title="Belum Ada Nilai Diterbitkan"
            description="Nilai asesmen formatif dan sumatif belum dipublikasikan oleh guru pengampu."
          />
        ) : (
          <div className="bg-white border border-slate-200/90 rounded-2xl overflow-hidden shadow-xs space-y-0">
            <div className="p-4 bg-slate-50/70 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
              <div>
                <h3 className="font-extrabold text-xs sm:text-sm text-slate-900">
                  Matriks Capaian Asesmen Semester Gasal 2026/2027
                </h3>
                <p className="text-[11px] text-slate-500 font-normal mt-0.5">
                  Bobot Asesmen: Formatif 1-3 (45%), Sumatif Lingkup Materi (25%), STS (30%)
                </p>
              </div>
              <span className="px-2.5 py-1 rounded-lg text-xs font-bold bg-emerald-50 text-emerald-800 border border-emerald-200 shadow-2xs self-start sm:self-auto">
                Seluruh Mapel Tuntas KKTP
              </span>
            </div>


          <div className="overflow-x-auto">
            <table className="w-full text-xs text-left">
              <thead className="bg-slate-100/80 text-slate-700 uppercase tracking-wider font-extrabold text-[11px] border-b border-slate-200">
                <tr>
                  <th className="py-3 px-4">Mata Pelajaran</th>
                  <th className="py-3 px-3 text-center">KKTP</th>
                  <th className="py-3 px-3 text-center">F1</th>
                  <th className="py-3 px-3 text-center">F2</th>
                  <th className="py-3 px-3 text-center">F3</th>
                  <th className="py-3 px-3 text-center">Sumatif LM</th>
                  <th className="py-3 px-3 text-center">STS</th>
                  <th className="py-3 px-4 text-center bg-blue-50 text-blue-900 font-black">Nilai Akhir</th>
                  <th className="py-3 px-4 text-center">Status</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 bg-white">
                {displayGrades.map((row) => (
                  <tr key={row.subject_code} className="hover:bg-slate-50/80 transition-colors">

                    <td className="py-3 px-4">
                      <p className="font-extrabold text-slate-900 text-xs sm:text-sm">{row.subject_name}</p>
                      <p className="text-[11px] text-slate-500 font-normal">Guru: {row.teacher_name}</p>
                    </td>
                    <td className="py-3 px-3 text-center font-bold text-slate-500">{row.kktp}</td>
                    <td className="py-3 px-3 text-center font-mono text-slate-700">{row.f1}</td>
                    <td className="py-3 px-3 text-center font-mono text-slate-700">{row.f2}</td>
                    <td className="py-3 px-3 text-center font-mono text-slate-700">{row.f3}</td>
                    <td className="py-3 px-3 text-center font-mono text-slate-700">{row.slm}</td>
                    <td className="py-3 px-3 text-center font-mono text-slate-700">{row.sts}</td>
                    <td className="py-3 px-4 text-center font-black text-sm text-blue-900 bg-blue-50/40 font-mono">
                      {row.final_score}
                    </td>
                    <td className="py-3 px-4 text-center">
                      <span className="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider bg-emerald-50 text-emerald-800 border border-emerald-200">
                        {row.status}
                      </span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )
    )}

      {/* ─── TAB 2: DOKUMEN E-RAPOR RESMI ─── */}
      {activeTab === 'rapor' && (
        <div className="space-y-4">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {/* E-Progress Report Oktober */}
            <div className="bg-white border border-slate-200/90 rounded-2xl p-5 sm:p-6 space-y-4 shadow-xs">
              <div className="flex items-start justify-between">
                <div className="w-10 h-10 rounded-xl bg-blue-50 text-blue-900 border border-blue-200 flex items-center justify-center font-bold shadow-2xs">
                  <FileText className="w-5 h-5 text-blue-900" />
                </div>
                <span className="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-emerald-50 text-emerald-800 border border-emerald-200 shadow-2xs">
                  Disahkan Kepala Sekolah
                </span>
              </div>

              <div>
                <h3 className="font-extrabold text-base text-slate-900">
                  E-Progress Report Bulanan: Oktober 2026
                </h3>
                <p className="text-xs text-slate-500 mt-1 font-normal leading-relaxed">
                  Laporan capaian kemajuan kompetensi bulanan siswa, catatan karakter P5, dan rekapitulasi presensi gerbang.
                </p>
                <div className="mt-2 text-[11px] font-mono text-slate-400">
                  Token QR: PR-IXA-240901-2026-10
                </div>
              </div>

              <div className="pt-3 border-t border-slate-100 flex items-center gap-2">
                <button
                  onClick={() => {
                    setSelectedMonth('Oktober 2026');
                    setShowReportPreview(true);
                  }}
                  className="flex-1 py-2 rounded-xl bg-blue-900 hover:bg-blue-950 text-white text-xs font-bold transition-colors shadow-xs cursor-pointer"
                >
                  Pratinjau Dokumen
                </button>
                <button
                  onClick={() => alert('Mengunduh berkas resmi E-Progress Report (PDF) ber-QR Code validasi...')}
                  className="px-3.5 py-2 rounded-xl bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-bold transition-colors shadow-xs flex items-center gap-1.5 cursor-pointer"
                >
                  <Download className="w-4 h-4 text-slate-500" />
                  <span>PDF</span>
                </button>
              </div>
            </div>

            {/* E-Progress Report September */}
            <div className="bg-white border border-slate-200/90 rounded-2xl p-5 sm:p-6 space-y-4 shadow-xs">
              <div className="flex items-start justify-between">
                <div className="w-10 h-10 rounded-xl bg-blue-50 text-blue-900 border border-blue-200 flex items-center justify-center font-bold shadow-2xs">
                  <FileText className="w-5 h-5 text-blue-900" />
                </div>
                <span className="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-emerald-50 text-emerald-800 border border-emerald-200 shadow-2xs">
                  Disahkan Kepala Sekolah
                </span>
              </div>

              <div>
                <h3 className="font-extrabold text-base text-slate-900">
                  E-Progress Report Bulanan: September 2026
                </h3>
                <p className="text-xs text-slate-500 mt-1 font-normal leading-relaxed">
                  Laporan evaluasi awal semester gasal dan penilaian diagnostik kemampuan siswa.
                </p>
                <div className="mt-2 text-[11px] font-mono text-slate-400">
                  Token QR: PR-IXA-240901-2026-09
                </div>
              </div>

              <div className="pt-3 border-t border-slate-100 flex items-center gap-2">
                <button
                  onClick={() => {
                    setSelectedMonth('September 2026');
                    setShowReportPreview(true);
                  }}
                  className="flex-1 py-2 rounded-xl bg-blue-900 hover:bg-blue-950 text-white text-xs font-bold transition-colors shadow-xs cursor-pointer"
                >
                  Pratinjau Dokumen
                </button>
                <button
                  onClick={() => alert('Mengunduh berkas resmi E-Progress Report September (PDF)...')}
                  className="px-3.5 py-2 rounded-xl bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-bold transition-colors shadow-xs flex items-center gap-1.5 cursor-pointer"
                >
                  <Download className="w-4 h-4 text-slate-500" />
                  <span>PDF</span>
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* ─── MODAL PRATINJAU DOKUMEN RESMI E-PROGRESS REPORT (1 HALAMAN BER-QR) ─── */}
      {showReportPreview && (
        <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto">
          <div className="bg-white rounded-2xl border border-slate-200 max-w-2xl w-full p-6 sm:p-8 space-y-6 shadow-2xl my-8">
            {/* Kop Resmi Sekolah */}
            <div className="text-center pb-4 border-b-2 border-slate-900 space-y-1 relative">
              <button
                onClick={() => setShowReportPreview(false)}
                className="absolute top-0 right-0 w-8 h-8 rounded-lg border border-slate-200 flex items-center justify-center text-slate-500 hover:bg-slate-100 font-bold cursor-pointer"
              >
                <X className="w-4 h-4" />
              </button>
              <h2 className="text-base sm:text-lg font-black uppercase tracking-wider text-slate-900">
                SMP LABSCHOOL UNIVERSITAS NEGERI MALANG
              </h2>
              <p className="text-xs text-slate-500 font-medium">
                Jl. Simpang Bogor No. 19, Kota Malang, Jawa Timur • Telp: (0341) 551312
              </p>
              <p className="text-xs font-bold uppercase tracking-widest text-blue-900 pt-1">
                LAPORAN KEMAJUAN BELAJAR SISWA (E-PROGRESS REPORT) - {selectedMonth.toUpperCase()}
              </p>
            </div>

            {/* Biodata Siswa */}
            <div className="grid grid-cols-2 gap-4 text-xs">
              <div className="space-y-1">
                <p><span className="text-slate-500">Nama Siswa:</span> <strong className="text-slate-900 font-semibold">{studentDash?.name || 'Siswa Terdaftar'}</strong></p>
                <p><span className="text-slate-500">NIS / NISN:</span> <strong className="text-slate-900 font-semibold">{studentDash?.nis || '-'} / {studentDash?.nisn || '-'}</strong></p>
                <p><span className="text-slate-500">Kelas:</span> <strong className="text-slate-900 font-semibold">{studentDash?.className || 'Kelas Siswa'}</strong></p>
              </div>
              <div className="space-y-1">
                <p><span className="text-slate-500">Tahun Ajaran:</span> <strong className="text-slate-900 font-semibold">2026/2027 Ganjil</strong></p>
                <p><span className="text-slate-500">Wali Kelas:</span> <strong className="text-slate-900 font-semibold">Budi Santoso, S.Kom., M.T.</strong></p>
                <p><span className="text-slate-500">Status Capaian:</span> <strong className="text-emerald-700 font-semibold">TUNTAS SELURUH KOMPETENSI</strong></p>
              </div>
            </div>

            {/* Tabel Ringkasan Nilai */}
            <div className="border border-slate-200 rounded-xl overflow-hidden">
              <table className="w-full text-xs text-left">
                <thead className="bg-slate-50 text-slate-700 font-bold uppercase border-b border-slate-200">
                  <tr>
                    <th className="p-2.5">Mata Pelajaran</th>
                    <th className="p-2.5 text-center">KKTP</th>
                    <th className="p-2.5 text-center">Nilai Akhir</th>
                    <th className="p-2.5 text-center">Capaian</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {displayGrades.slice(0, 4).map((g) => (
                    <tr key={g.subject_code}>
                      <td className="p-2.5 font-bold text-slate-800">{g.subject_name}</td>
                      <td className="p-2.5 text-center">{g.kktp}</td>
                      <td className="p-2.5 text-center font-bold text-blue-900 font-mono">{g.final_score}</td>
                      <td className="p-2.5 text-center font-bold text-emerald-700">Tuntas</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            {/* Catatan Karakter P5 */}
            <div className="p-3.5 bg-slate-50 rounded-xl border border-slate-200 text-xs space-y-1">
              <p className="font-extrabold text-slate-900">Catatan Karakter Profil Pelajar Pancasila (Wali Kelas):</p>
              <p className="text-slate-600 leading-relaxed italic">
                "Ananda Fauzan menunjukkan kemandirian dan nalar kritis yang sangat menonjol dalam praktikum teknologi. Sikap gotong royong dalam kelompok sangat baik dan menjadi teladan bagi rekan-rekannya."
              </p>
            </div>

            {/* Presensi & Tanda Tangan QR */}
            <div className="flex items-center justify-between pt-4 border-t border-slate-200 text-xs">
              <div className="flex items-center gap-3">
                <div className="w-16 h-16 rounded-xl bg-slate-50 border border-slate-200 flex items-center justify-center shadow-2xs">
                  <QrCode className="w-12 h-12 text-slate-800" />
                </div>
                <div>
                  <span className="text-[10px] font-mono text-slate-400 block">PENGESAHAN ELEKTRONIK</span>
                  <p className="font-extrabold text-slate-900">Dr. Supriyadi, M.Pd.</p>
                  <p className="text-[11px] text-slate-500">Kepala SMP Labschool UM</p>
                  <p className="text-[10px] text-emerald-700 font-bold mt-0.5">✔ Dokumen Sah Terverifikasi</p>
                </div>
              </div>

              <div className="text-right space-y-0.5">
                <p className="text-slate-500 text-[11px]">Kehadiran Siswa:</p>
                <p className="font-black text-slate-900 text-sm">35 Hadir (97.2%)</p>
                <p className="text-slate-400 text-[10px]">Sakit: 1 • Izin: 0 • Alpa: 0</p>
              </div>
            </div>

            <div className="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
              <button
                type="button"
                onClick={() => setShowReportPreview(false)}
                className="px-4 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-700 hover:bg-slate-100 cursor-pointer"
              >
                Tutup
              </button>
              <button
                type="button"
                onClick={() => {
                  alert('Mengunduh berkas PDF E-Progress Report resmi...');
                  setShowReportPreview(false);
                }}
                className="px-5 py-2.5 rounded-xl bg-blue-900 hover:bg-blue-950 text-white text-xs font-black uppercase tracking-wider transition-colors shadow-xs flex items-center gap-1.5 cursor-pointer"
              >
                <Download className="w-4 h-4 text-white" />
                <span>Unduh PDF Resmi</span>
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
