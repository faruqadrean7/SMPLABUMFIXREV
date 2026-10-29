'use client';

import React, { useState } from 'react';
import {
  ProgressReportItem,
  ProgressReportSnapshot,
  ProgressReportStatus,
  PROGRESS_REPORT_STATUS_LABELS,
  PROGRESS_REPORT_STATUS_COLORS,
  CHARACTER_ASPECTS,
  CHARACTER_RATING_LABELS,
  CHARACTER_RATING_COLORS,
  CharacterRating,
} from '@/types/academic';
import { academicApi } from '../academic-api';
import { Button } from '@/components/ui/button';
import { toast } from '@/components/ui/toast';
import {
  Award,
  CheckCircle2,
  Download,
  Printer,
  X,
  FileCheck2,
  Calendar,
  User,
  School,
  AlertCircle,
  ExternalLink,
  ShieldCheck,
  Copy,
  Clock,
  Sparkles,
  Loader2,
} from 'lucide-react';

interface ProgressReportModalProps {
  isOpen: boolean;
  onClose: () => void;
  report?: ProgressReportItem | null;
  previewSnapshot?: ProgressReportSnapshot | null;
  onStatusUpdated?: () => void;
  onGenerateFromPreview?: () => void;
  canApprove?: boolean;
}

export function ProgressReportModal({
  isOpen,
  onClose,
  report,
  previewSnapshot,
  onStatusUpdated,
  onGenerateFromPreview,
  canApprove = false,
}: ProgressReportModalProps) {
  const [isUpdatingStatus, setIsUpdatingStatus] = useState(false);
  const [statusError, setStatusError] = useState<string | null>(null);
  const [copiedToken, setCopiedToken] = useState(false);
  const [isDownloadingPdf, setIsDownloadingPdf] = useState(false);

  if (!isOpen) return null;

  const snapshot: ProgressReportSnapshot | null =
    report?.data_snapshot ?? previewSnapshot ?? null;

  if (!snapshot) {
    return (
      <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-xs">
        <div className="w-full max-w-md rounded-xl bg-white p-6 shadow-xl text-center">
          <AlertCircle className="mx-auto h-12 w-12 text-amber-500 mb-3" />
          <h3 className="text-base font-bold text-gray-900">Data Rapor Belum Tersedia</h3>
          <p className="mt-1 text-xs text-gray-600">
            Data snapshot e-progress report belum dikompilasi atau siswa belum memiliki nilai &amp;
            catatan karakter.
          </p>
          <div className="mt-5">
            <Button variant="outline" size="sm" onClick={onClose}>
              Tutup
            </Button>
          </div>
        </div>
      </div>
    );
  }

  const student = snapshot.student;
  const period = snapshot.period;
  const grades = snapshot.grades || [];
  const attendance = snapshot.attendance || {
    hadir: 0,
    izin: 0,
    sakit: 0,
    alpa: 0,
    total_recorded: 0,
    percentage: 100,
  };
  const character = snapshot.character || {
    scale_data: {},
    homeroom_note: '-',
  };

  const status: ProgressReportStatus = report?.status ?? 'draft';

  // Perhitungan rata-rata nilai kelas / seluruh mapel
  const totalGrades = grades.reduce((acc, g) => acc + (g.final_score || 0), 0);
  const averageGrade =
    grades.length > 0 ? (totalGrades / grades.length).toFixed(2) : '-';

  const handleUpdateStatus = async (
    newStatus: 'disetujui' | 'terbit' | 'draft' | 'menunggu_acc'
  ) => {
    if (!report?.id) return;
    setIsUpdatingStatus(true);
    setStatusError(null);
    try {
      await academicApi.approveProgressReport(report.id, { status: newStatus });
      onStatusUpdated?.();
    } catch (err: any) {
      setStatusError(
        err.response?.data?.message ||
          'Gagal memperbarui status pengesahan rapor. Silakan coba lagi.'
      );
    } finally {
      setIsUpdatingStatus(false);
    }
  };

  const handleDownloadPdf = async () => {
    if (!report?.id) return;
    setIsDownloadingPdf(true);
    try {
      const blob = await academicApi.downloadProgressReportPdf(report.id);
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `progress_report_${student?.full_name?.replace(/\s+/g, '_')}_bulan_${period?.month}.pdf`;
      document.body.appendChild(a);
      a.click();
      window.URL.revokeObjectURL(url);
      document.body.removeChild(a);
    } catch (err: any) {
      toast.error('Gagal mengunduh berkas PDF rapor.');
    } finally {
      setIsDownloadingPdf(false);
    }
  };

  const handlePrint = () => {
    window.print();
  };

  const handleCopyVerifyUrl = () => {
    if (!report?.verify_token) return;
    const url = `${window.location.origin}/api/v1/public/verify/${report.verify_token}`;
    navigator.clipboard.writeText(url);
    setCopiedToken(true);
    setTimeout(() => setCopiedToken(false), 2000);
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-2 sm:p-4 backdrop-blur-xs overflow-y-auto">
      <div className="relative w-full max-w-4xl max-h-[92vh] flex flex-col rounded-xl bg-white shadow-2xl overflow-hidden border border-gray-200 print:max-w-none print:max-h-none print:shadow-none print:border-none print:m-0 print:p-0">
        {/* Header Dialog */}
        <div className="flex items-center justify-between border-b border-gray-100 bg-slate-900 px-6 py-4 text-white print:hidden">
          <div className="flex items-center gap-2.5">
            <div className="rounded-lg bg-emerald-500 p-2 text-white">
              <Award className="h-5 w-5" />
            </div>
            <div>
              <h2 className="text-base font-bold tracking-tight">
                {report ? 'Dokumen E-Progress Report Bulanan' : 'Pratinjau E-Progress Report (Preview)'}
              </h2>
              <p className="text-xs text-slate-300">
                Laporan capaian belajar siswa terverifikasi tanda tangan digital QR
              </p>
            </div>
          </div>
          <div className="flex items-center gap-2">
            <button
              onClick={onClose}
              className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-800 hover:text-white transition"
              aria-label="Tutup dialog"
            >
              <X className="h-5 w-5" />
            </button>
          </div>
        </div>

        {/* Action Toolbar (Non-Print) */}
        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 bg-slate-50/80 px-6 py-2.5 text-xs print:hidden">
          <div className="flex items-center gap-2">
            <span className="font-semibold text-gray-700">Status Dokumen:</span>
            <span
              className={`inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 font-semibold text-[11px] border ${PROGRESS_REPORT_STATUS_COLORS[status]}`}
            >
              {PROGRESS_REPORT_STATUS_LABELS[status]}
            </span>
            {previewSnapshot && !report && (
              <span className="rounded-md bg-amber-100 px-2 py-0.5 text-[11px] font-medium text-amber-800">
                Mode Pratinjau
              </span>
            )}
          </div>

          <div className="flex flex-wrap items-center gap-2">
            {/* Tombol Cetak / PDF */}
            <Button
              variant="outline"
              size="sm"
              onClick={handlePrint}
              className="h-8 gap-1.5 text-xs text-gray-700 hover:bg-gray-100"
            >
              <Printer className="h-3.5 w-3.5" />
              <span>Cetak Dokumen</span>
            </Button>

            {report && (
              <Button
                variant="outline"
                size="sm"
                onClick={handleDownloadPdf}
                disabled={isDownloadingPdf}
                className="h-8 gap-1.5 text-xs text-gray-700 hover:bg-gray-100"
              >
                {isDownloadingPdf ? (
                  <Loader2 className="h-3.5 w-3.5 animate-spin" />
                ) : (
                  <Download className="h-3.5 w-3.5" />
                )}
                <span>Unduh PDF</span>
              </Button>
            )}

            {/* Workflow Approvals (Kepsek / Admin) */}
            {canApprove && report && (
              <div className="flex items-center gap-1.5 border-l border-gray-300 pl-2">
                {status === 'draft' && (
                  <Button
                    size="sm"
                    onClick={() => handleUpdateStatus('menunggu_acc')}
                    disabled={isUpdatingStatus}
                    className="h-8 bg-amber-600 hover:bg-amber-700 text-white text-xs gap-1"
                  >
                    Ajukan ACC
                  </Button>
                )}
                {status === 'menunggu_acc' && (
                  <>
                    <Button
                      size="sm"
                      onClick={() => handleUpdateStatus('disetujui')}
                      disabled={isUpdatingStatus}
                      className="h-8 bg-blue-600 hover:bg-blue-700 text-white text-xs gap-1"
                    >
                      <CheckCircle2 className="h-3.5 w-3.5" />
                      <span>Setujui (ACC)</span>
                    </Button>
                    <Button
                      size="sm"
                      variant="outline"
                      onClick={() => handleUpdateStatus('draft')}
                      disabled={isUpdatingStatus}
                      className="h-8 text-xs text-rose-700 hover:bg-rose-50 border-rose-200"
                    >
                      Tolak ke Draf
                    </Button>
                  </>
                )}
                {status === 'disetujui' && (
                  <Button
                    size="sm"
                    onClick={() => handleUpdateStatus('terbit')}
                    disabled={isUpdatingStatus}
                    className="h-8 bg-emerald-600 hover:bg-emerald-700 text-white text-xs gap-1"
                  >
                    <Sparkles className="h-3.5 w-3.5" />
                    <span>Terbitkan ke Ortu/Siswa</span>
                  </Button>
                )}
                {status === 'terbit' && (
                  <Button
                    size="sm"
                    variant="outline"
                    onClick={() => handleUpdateStatus('draft')}
                    disabled={isUpdatingStatus}
                    className="h-8 text-xs text-gray-600 hover:bg-gray-100"
                  >
                    Tarik Kembali ke Draf
                  </Button>
                )}
              </div>
            )}

            {previewSnapshot && !report && onGenerateFromPreview && (
              <Button
                size="sm"
                onClick={onGenerateFromPreview}
                className="h-8 bg-emerald-600 hover:bg-emerald-700 text-white text-xs gap-1"
              >
                <CheckCircle2 className="h-3.5 w-3.5" />
                <span>Simpan &amp; Generate Rapor</span>
              </Button>
            )}
          </div>
        </div>

        {statusError && (
          <div className="bg-rose-50 px-6 py-2 text-xs font-medium text-rose-700 border-b border-rose-100 flex items-center gap-2">
            <AlertCircle className="h-4 w-4 shrink-0" />
            <span>{statusError}</span>
          </div>
        )}

        {/* Content Body: Printable A4 Document Layout */}
        <div className="flex-1 overflow-y-auto p-6 sm:p-8 space-y-6 text-slate-800 print:overflow-visible print:p-0 print:space-y-4">
          {/* Header Kop Surat Dokumen */}
          <div className="border-b-2 border-slate-900 pb-4 flex items-center justify-between gap-4">
            <div className="flex items-center gap-3">
              <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-slate-900 text-white font-extrabold text-lg print:border print:border-black">
                LAB
              </div>
              <div>
                <h1 className="text-base sm:text-lg font-extrabold tracking-tight text-slate-900 uppercase">
                  SMP - SMA LABORATORIUM UNIVERSITAS NEGERI MALANG
                </h1>
                <p className="text-xs text-slate-600">
                  Portal Sekolah Satu Pintu SISTLABUM • Sistem Informasi Akademik &amp; KBM
                </p>
                <p className="text-[11px] font-semibold text-emerald-700 mt-0.5">
                  E-PROGRESS REPORT BULANAN (LAPORAN CAPAIAN BELAJAR)
                </p>
              </div>
            </div>

            <div className="text-right">
              <span
                className={`inline-block rounded-md px-2.5 py-1 text-xs font-bold border ${PROGRESS_REPORT_STATUS_COLORS[status]}`}
              >
                {PROGRESS_REPORT_STATUS_LABELS[status].toUpperCase()}
              </span>
              <p className="text-[10px] text-slate-400 mt-1 font-mono">
                Bulan: {period?.month} • {period?.semester_name || 'Semester Aktif'}
              </p>
            </div>
          </div>

          {/* Metadata Siswa & Periode Grid */}
          <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 bg-slate-50 p-4 rounded-lg border border-slate-200 text-xs print:bg-white print:border-slate-300">
            <div>
              <span className="text-slate-500 font-medium block">Nama Peserta Didik:</span>
              <span className="font-bold text-slate-900 text-sm block mt-0.5">
                {student?.full_name || '-'}
              </span>
            </div>
            <div>
              <span className="text-slate-500 font-medium block">NIS / NISN:</span>
              <span className="font-semibold text-slate-800 font-mono block mt-0.5">
                {student?.nis || '-'} / {student?.nisn || '-'}
              </span>
            </div>
            <div>
              <span className="text-slate-500 font-medium block">Rombel / Kelas:</span>
              <span className="font-bold text-slate-900 block mt-0.5">
                {student?.class_name || '-'}
              </span>
            </div>
            <div>
              <span className="text-slate-500 font-medium block">Tahun Ajaran:</span>
              <span className="font-semibold text-slate-800 block mt-0.5">
                {period?.academic_year || '2026/2027'}
              </span>
            </div>
          </div>

          {/* 1. Nilai Akademik Per Mata Pelajaran */}
          <div className="space-y-2">
            <div className="flex items-center justify-between border-b border-gray-200 pb-1.5">
              <h3 className="text-xs font-bold uppercase tracking-wider text-slate-900 flex items-center gap-1.5">
                <Award className="h-4 w-4 text-emerald-600" />
                <span>1. Capaian Nilai Akademik Mata Pelajaran</span>
              </h3>
              <div className="text-xs font-medium text-slate-600">
                Rata-rata Nilai: <span className="font-bold text-slate-900">{averageGrade}</span>
              </div>
            </div>

            {grades.length === 0 ? (
              <div className="rounded-md border border-dashed border-gray-300 p-4 text-center text-xs text-gray-500">
                Belum ada data nilai akademik yang diinput untuk periode ini.
              </div>
            ) : (
              <div className="overflow-x-auto rounded-lg border border-gray-200">
                <table className="w-full text-left text-xs border-collapse">
                  <thead className="bg-slate-100 text-slate-700 font-bold border-b border-gray-200">
                    <tr>
                      <th className="py-2 px-3 w-10 text-center">No</th>
                      <th className="py-2 px-3">Mata Pelajaran</th>
                      <th className="py-2 px-3">Rincian Komponen Penilaian</th>
                      <th className="py-2 px-3 w-28 text-center">Nilai Akhir</th>
                      <th className="py-2 px-3 w-20 text-center">Predikat</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-gray-100 text-slate-700">
                    {grades.map((g, idx) => {
                      const score = g.final_score || 0;
                      let predicate = 'D';
                      if (score >= 90) predicate = 'A';
                      else if (score >= 80) predicate = 'B';
                      else if (score >= 70) predicate = 'C';

                      return (
                        <tr key={g.subject_id || idx} className="hover:bg-slate-50/50">
                          <td className="py-2 px-3 text-center text-slate-400 font-mono">
                            {idx + 1}
                          </td>
                          <td className="py-2 px-3 font-semibold text-slate-900">
                            {g.subject_name}
                            {g.subject_code && (
                              <span className="ml-1.5 text-[10px] text-slate-400 font-mono">
                                ({g.subject_code})
                              </span>
                            )}
                          </td>
                          <td className="py-2 px-3 text-[11px] text-slate-600">
                            {g.components && g.components.length > 0 ? (
                              <div className="flex flex-wrap gap-1.5">
                                {g.components.map((c, ci) => (
                                  <span
                                    key={ci}
                                    className="inline-flex items-center gap-1 rounded bg-slate-100 px-1.5 py-0.5 font-mono text-[10px]"
                                  >
                                    <span className="text-slate-500">{c.name}:</span>
                                    <span className="font-bold text-slate-900">{c.value}</span>
                                    <span className="text-[9px] text-slate-400">
                                      ({c.weight}%)
                                    </span>
                                  </span>
                                ))}
                              </div>
                            ) : (
                              <span className="text-slate-400 italic">-</span>
                            )}
                          </td>
                          <td className="py-2 px-3 text-center font-bold text-slate-900 font-mono text-sm">
                            {score.toFixed(2)}
                          </td>
                          <td className="py-2 px-3 text-center">
                            <span
                              className={`inline-block px-2 py-0.5 rounded text-[11px] font-bold ${
                                predicate === 'A'
                                  ? 'bg-emerald-100 text-emerald-800'
                                  : predicate === 'B'
                                  ? 'bg-blue-100 text-blue-800'
                                  : predicate === 'C'
                                  ? 'bg-amber-100 text-amber-800'
                                  : 'bg-rose-100 text-rose-800'
                              }`}
                            >
                              {predicate}
                            </span>
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            )}
          </div>

          {/* 2. Rekap Presensi & Kehadiran Bulanan */}
          <div className="space-y-2">
            <h3 className="text-xs font-bold uppercase tracking-wider text-slate-900 flex items-center gap-1.5 border-b border-gray-200 pb-1.5">
              <Calendar className="h-4 w-4 text-emerald-600" />
              <span>2. Rekapitulasi Presensi KBM Bulan Ini</span>
            </h3>

            <div className="grid grid-cols-2 sm:grid-cols-5 gap-3 text-center">
              <div className="rounded-lg border border-emerald-200 bg-emerald-50/50 p-2.5">
                <span className="text-[11px] font-semibold text-emerald-700 block">Hadir</span>
                <span className="text-xl font-extrabold text-emerald-800 font-mono block mt-0.5">
                  {attendance.hadir}
                </span>
                <span className="text-[10px] text-emerald-600">Hari</span>
              </div>
              <div className="rounded-lg border border-blue-200 bg-blue-50/50 p-2.5">
                <span className="text-[11px] font-semibold text-blue-700 block">Sakit</span>
                <span className="text-xl font-extrabold text-blue-800 font-mono block mt-0.5">
                  {attendance.sakit}
                </span>
                <span className="text-[10px] text-blue-600">Hari</span>
              </div>
              <div className="rounded-lg border border-amber-200 bg-amber-50/50 p-2.5">
                <span className="text-[11px] font-semibold text-amber-700 block">Izin</span>
                <span className="text-xl font-extrabold text-amber-800 font-mono block mt-0.5">
                  {attendance.izin}
                </span>
                <span className="text-[10px] text-amber-600">Hari</span>
              </div>
              <div className="rounded-lg border border-rose-200 bg-rose-50/50 p-2.5">
                <span className="text-[11px] font-semibold text-rose-700 block">Alpa / Tanpa Ket.</span>
                <span className="text-xl font-extrabold text-rose-800 font-mono block mt-0.5">
                  {attendance.alpa}
                </span>
                <span className="text-[10px] text-rose-600">Hari</span>
              </div>
              <div className="rounded-lg border border-slate-200 bg-slate-50 p-2.5 col-span-2 sm:col-span-1">
                <span className="text-[11px] font-semibold text-slate-700 block">% Kehadiran</span>
                <span className="text-xl font-extrabold text-slate-900 font-mono block mt-0.5">
                  {attendance.percentage}%
                </span>
                <span className="text-[10px] text-slate-500">
                  Total {attendance.total_recorded} hari tercatat
                </span>
              </div>
            </div>
          </div>

          {/* 3. Catatan Perkembangan Karakter & Narasi Wali Kelas */}
          <div className="space-y-3">
            <h3 className="text-xs font-bold uppercase tracking-wider text-slate-900 flex items-center gap-1.5 border-b border-gray-200 pb-1.5">
              <Sparkles className="h-4 w-4 text-emerald-600" />
              <span>3. Catatan Sikap &amp; Karakter (Profil Pelajar Pancasila)</span>
            </h3>

            {/* 8 Dimensi Grid */}
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs">
              {CHARACTER_ASPECTS.map((aspect) => {
                const rating = character.scale_data?.[aspect.key] as CharacterRating | undefined;
                return (
                  <div
                    key={aspect.key}
                    className="flex flex-col justify-between rounded-lg border border-gray-200 bg-slate-50/50 p-2.5"
                  >
                    <div>
                      <span className="text-[10px] font-bold text-slate-500 block uppercase">
                        {aspect.category}
                      </span>
                      <span className="font-semibold text-slate-800 text-[11px] line-clamp-1">
                        {aspect.name}
                      </span>
                    </div>
                    <div className="mt-2">
                      {rating ? (
                        <span
                          className={`inline-block rounded px-2 py-0.5 text-[10px] font-bold border ${
                            CHARACTER_RATING_COLORS[rating] || 'bg-gray-100 text-gray-700'
                          }`}
                        >
                          {CHARACTER_RATING_LABELS[rating] || rating}
                        </span>
                      ) : (
                        <span className="text-[11px] text-slate-400 italic">Belum Dinilai</span>
                      )}
                    </div>
                  </div>
                );
              })}
            </div>

            {/* Catatan Naratif Wali Kelas */}
            <div className="rounded-lg border border-slate-200 bg-emerald-50/20 p-4">
              <div className="flex items-center justify-between mb-1.5">
                <span className="text-xs font-bold text-slate-900 flex items-center gap-1.5">
                  <User className="h-3.5 w-3.5 text-emerald-600" />
                  <span>Catatan Naratif Deskriptif Wali Kelas:</span>
                </span>
                <span className="text-[11px] text-slate-500 italic">
                  Oleh: {character.author_name || 'Wali Kelas'}
                </span>
              </div>
              <p className="text-xs text-slate-700 leading-relaxed italic bg-white p-3 rounded border border-emerald-100/60">
                &ldquo;{character.homeroom_note || 'Tidak ada catatan khusus.'}&rdquo;
              </p>
            </div>
          </div>

          {/* 4. Pengesahan & Tanda Tangan QR Digital */}
          <div className="border-t-2 border-slate-200 pt-4 grid grid-cols-1 sm:grid-cols-2 gap-4 items-center">
            <div className="text-xs space-y-1">
              <div className="flex items-center gap-1.5 text-slate-900 font-bold">
                <ShieldCheck className="h-4 w-4 text-emerald-600" />
                <span>Pengesahan Digital Dokumen:</span>
              </div>
              <p className="text-slate-600 text-[11px]">
                Kepala Sekolah: <span className="font-semibold text-slate-800">{report?.approver?.name || report?.approver?.username || 'Drs. H. Mulyani, M.Pd.'}</span>
              </p>
              <p className="text-slate-500 text-[10px]">
                Waktu Dokumen Dibuat: {snapshot.generated_at ? new Date(snapshot.generated_at).toLocaleString('id-ID') : '-'}
              </p>
              {report?.verify_token && (
                <div className="flex items-center gap-2 pt-1">
                  <span className="font-mono text-[10px] text-slate-600 bg-slate-100 px-2 py-0.5 rounded border border-slate-200">
                    Token: {report.verify_token.substring(0, 16)}...
                  </span>
                  <button
                    onClick={handleCopyVerifyUrl}
                    className="inline-flex items-center gap-1 text-[10px] text-emerald-700 hover:text-emerald-800 font-semibold"
                  >
                    <Copy className="h-3 w-3" />
                    <span>{copiedToken ? 'Tersalin!' : 'Salin URL QR'}</span>
                  </button>
                </div>
              )}
            </div>

            <div className="flex justify-end items-center gap-3">
              <div className="text-right text-[11px] text-slate-500 leading-tight">
                <span className="font-bold text-slate-900 block">SMP-SMA Laboratorium UM</span>
                <span>Malang, Jawa Timur</span>
                <span className="block text-[10px] text-emerald-700 font-semibold mt-0.5">
                  Tanda Tangan QR Sah &amp; Terverifikasi
                </span>
              </div>
              <div className="h-16 w-16 rounded-lg border-2 border-slate-900 bg-slate-50 flex flex-col items-center justify-center p-1 text-center shrink-0">
                <ShieldCheck className="h-6 w-6 text-emerald-600" />
                <span className="text-[8px] font-extrabold text-slate-900 leading-none mt-1">
                  QR VERIFIED
                </span>
              </div>
            </div>
          </div>
        </div>

        {/* Footer Actions */}
        <div className="flex items-center justify-between border-t border-gray-100 bg-slate-50 px-6 py-3 text-xs print:hidden">
          <span className="text-slate-500 text-[11px]">
            {report
              ? `ID Dokumen: ${report.id.substring(0, 8)}...`
              : 'Pratinjau sebelum penerbitan resmi'}
          </span>
          <Button variant="outline" size="sm" onClick={onClose} className="text-xs">
            Tutup
          </Button>
        </div>
      </div>
    </div>
  );
}
