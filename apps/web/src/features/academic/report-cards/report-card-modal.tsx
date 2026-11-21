'use client';

import React, { useState } from 'react';
import {
  ReportCardItem,
  ReportCardSnapshot,
  ReportCardStatus,
  REPORT_CARD_STATUS_LABELS,
  REPORT_CARD_STATUS_COLORS,
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
  ShieldCheck,
  Copy,
  Lock,
  Unlock,
  Check,
  Send,
  Loader2,
  BookOpen,
} from 'lucide-react';

interface ReportCardModalProps {
  isOpen: boolean;
  onClose: () => void;
  report?: ReportCardItem | null;
  previewSnapshot?: ReportCardSnapshot | null;
  onStatusUpdated?: () => void;
  onGenerateFromPreview?: () => void;
  canApprove?: boolean;
  canLock?: boolean;
}

export function ReportCardModal({
  isOpen,
  onClose,
  report,
  previewSnapshot,
  onStatusUpdated,
  onGenerateFromPreview,
  canApprove = false,
  canLock = false,
}: ReportCardModalProps) {
  const [isUpdatingStatus, setIsUpdatingStatus] = useState(false);
  const [statusError, setStatusError] = useState<string | null>(null);
  const [copiedToken, setCopiedToken] = useState(false);
  const [isDownloadingPdf, setIsDownloadingPdf] = useState(false);

  if (!isOpen) return null;

  const snapshot: ReportCardSnapshot | null =
    report?.data_snapshot ?? previewSnapshot ?? null;

  if (!snapshot) {
    return (
      <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-xs">
        <div className="w-full max-w-md rounded-xl bg-white p-6 shadow-xl text-center">
          <AlertCircle className="mx-auto h-12 w-12 text-amber-500 mb-3" />
          <h3 className="text-base font-bold text-gray-900">Data E-Rapor Belum Tersedia</h3>
          <p className="mt-1 text-xs text-gray-600">
            Data snapshot e-rapor semester belum dikompilasi atau siswa belum memiliki nilai akademik resmi.
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
  const semester = snapshot.semester;
  const subjects = snapshot.subjects || [];
  const attendance = snapshot.attendance || {
    hadir: 0,
    izin: 0,
    sakit: 0,
    alpa: 0,
    total_recorded: 0,
    percentage: 100,
  };
  const extracurriculars = snapshot.extracurriculars || [];
  const homeroomNote = snapshot.homeroom_note || '-';

  const status: ReportCardStatus = report?.status ?? 'draft';

  // Perhitungan statistik nilai
  const totalScores = subjects.reduce((acc, s) => acc + (s.final_score || 0), 0);
  const averageScore = subjects.length > 0 ? (totalScores / subjects.length).toFixed(2) : '-';
  const tuntasCount = subjects.filter((s) => s.level === 'tuntas').length;

  const handleUpdateStatus = async (
    newStatus: 'disetujui' | 'terbit' | 'draft' | 'terkunci'
  ) => {
    if (!report?.id) return;
    setIsUpdatingStatus(true);
    setStatusError(null);
    try {
      await academicApi.approveReportCard(report.id, { status: newStatus });
      onStatusUpdated?.();
    } catch (err: any) {
      setStatusError(
        err.response?.data?.message ||
          'Gagal memperbarui status pengesahan e-rapor semester. Silakan coba lagi.'
      );
    } finally {
      setIsUpdatingStatus(false);
    }
  };

  const handleToggleLock = async (isLocked: boolean) => {
    if (!report?.id) return;
    setIsUpdatingStatus(true);
    setStatusError(null);
    try {
      await academicApi.lockReportCard(report.id, {
        is_locked: isLocked,
        reason: isLocked ? 'Penguncian nilai oleh kurikulum' : 'Pembukaan kunci oleh kurikulum',
      });
      onStatusUpdated?.();
    } catch (err: any) {
      setStatusError(
        err.response?.data?.message || 'Gagal mengubah status penguncian e-rapor.'
      );
    } finally {
      setIsUpdatingStatus(false);
    }
  };

  const handleDownloadPdf = async () => {
    if (!report?.id) return;
    setIsDownloadingPdf(true);
    try {
      const blob = await academicApi.downloadReportCardPdf(report.id);
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `rapor_semester_${student?.full_name?.replace(/\s+/g, '_')}_${report.verify_token.slice(0, 8)}.pdf`;
      document.body.appendChild(a);
      a.click();
      window.URL.revokeObjectURL(url);
      document.body.removeChild(a);
    } catch (err: any) {
      toast.error('Gagal mengunduh berkas PDF e-rapor semester.');
    } finally {
      setIsDownloadingPdf(false);
    }
  };

  const handlePrint = () => {
    window.print();
  };

  const handleCopyToken = () => {
    if (!report?.verify_token) return;
    navigator.clipboard.writeText(report.verify_token);
    setCopiedToken(true);
    setTimeout(() => setCopiedToken(false), 2000);
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-2 sm:p-4 backdrop-blur-xs overflow-y-auto">
      <div className="relative w-full max-w-4xl max-h-[92vh] flex flex-col rounded-2xl bg-white shadow-2xl overflow-hidden border border-gray-200">
        {/* Header Bar */}
        <div className="flex items-center justify-between border-b border-gray-200 bg-gray-50/80 px-6 py-4">
          <div className="flex items-center gap-3">
            <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-100 text-blue-700 font-bold">
              <FileCheck2 className="h-5 w-5" />
            </div>
            <div>
              <div className="flex items-center gap-2">
                <h2 className="text-base font-bold text-gray-900">
                  Laporan Hasil Belajar (E-Rapor Semester)
                </h2>
                <span
                  className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold border ${
                    REPORT_CARD_STATUS_COLORS[status] || 'bg-gray-100 text-gray-700'
                  }`}
                >
                  {REPORT_CARD_STATUS_LABELS[status] || status}
                </span>
                {!report && (
                  <span className="inline-flex items-center rounded-full bg-purple-50 px-2 py-0.5 text-[11px] font-medium text-purple-700 border border-purple-200">
                    Mode Pratinjau
                  </span>
                )}
              </div>
              <p className="text-xs text-gray-500">
                {student?.full_name} • {student?.class_name || 'Rombel'} • {semester?.name}
              </p>
            </div>
          </div>

          <div className="flex items-center gap-2">
            {report?.id && (
              <Button
                variant="outline"
                size="sm"
                onClick={handleDownloadPdf}
                disabled={isDownloadingPdf}
                className="hidden sm:inline-flex gap-1.5 text-xs"
              >
                {isDownloadingPdf ? (
                  <Loader2 className="h-3.5 w-3.5 animate-spin" />
                ) : (
                  <Download className="h-3.5 w-3.5" />
                )}
                Unduh PDF
              </Button>
            )}
            <Button
              variant="outline"
              size="sm"
              onClick={handlePrint}
              className="hidden sm:inline-flex gap-1.5 text-xs"
            >
              <Printer className="h-3.5 w-3.5" />
              Cetak A4
            </Button>
            <button
              onClick={onClose}
              className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-700 transition"
              title="Tutup"
            >
              <X className="h-5 w-5" />
            </button>
          </div>
        </div>

        {/* Modal Body / Printable Document */}
        <div className="flex-1 overflow-y-auto p-6 sm:p-8 space-y-6 bg-white text-gray-900 print:p-0">
          {statusError && (
            <div className="rounded-lg border border-red-200 bg-red-50 p-3 text-xs text-red-700 flex items-center justify-between">
              <span>{statusError}</span>
              <button onClick={() => setStatusError(null)} className="text-red-500">
                <X className="h-4 w-4" />
              </button>
            </div>
          )}

          {/* Kop Dokumen Resmi */}
          <div className="border-b-2 border-gray-900 pb-4 text-center">
            <h1 className="text-lg sm:text-xl font-bold tracking-tight text-gray-900 uppercase">
              PORTAL SEKOLAH SATU PINTU SISTLABUM
            </h1>
            <p className="text-xs text-gray-600 font-medium">
              Laboratorium Universitas Negeri Malang • Terakreditasi A
            </p>
            <div className="mt-2 inline-block bg-blue-50 border border-blue-200 px-4 py-1 rounded-full text-xs font-semibold text-blue-800">
              LAPORAN HASIL CAPAIAN KOMPETENSI SISWA (E-RAPOR SEMESTER)
            </div>
          </div>

          {/* Identitas Siswa & Semester Grid */}
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 rounded-xl bg-gray-50/70 p-4 border border-gray-200 text-xs">
            <div className="space-y-1.5">
              <div className="flex">
                <span className="w-28 text-gray-500 flex items-center gap-1">
                  <User className="h-3 w-3" /> Nama Lengkap
                </span>
                <span className="font-semibold text-gray-900">: {student?.full_name}</span>
              </div>
              <div className="flex">
                <span className="w-28 text-gray-500">NIS / NISN</span>
                <span className="text-gray-700">
                  : {student?.nis || '-'} / {student?.nisn || '-'}
                </span>
              </div>
              <div className="flex">
                <span className="w-28 text-gray-500 flex items-center gap-1">
                  <School className="h-3 w-3" /> Kelas / Rombel
                </span>
                <span className="font-medium text-gray-800">: {student?.class_name || '-'}</span>
              </div>
            </div>

            <div className="space-y-1.5">
              <div className="flex">
                <span className="w-28 text-gray-500 flex items-center gap-1">
                  <Calendar className="h-3 w-3" /> Semester
                </span>
                <span className="font-semibold text-gray-900">: {semester?.name}</span>
              </div>
              <div className="flex">
                <span className="w-28 text-gray-500">Tahun Ajaran</span>
                <span className="text-gray-700">: {semester?.academic_year || '2026/2027'}</span>
              </div>
              <div className="flex">
                <span className="w-28 text-gray-500 flex items-center gap-1">
                  <Award className="h-3 w-3" /> Rerata Nilai
                </span>
                <span className="font-bold text-blue-700">
                  : {averageScore} ({tuntasCount}/{subjects.length} Mapel Tuntas)
                </span>
              </div>
            </div>
          </div>

          {/* Section 1: Tabel Capaian Kompetensi Mata Pelajaran */}
          <div className="space-y-2">
            <div className="flex items-center justify-between">
              <h3 className="text-xs font-bold text-gray-900 uppercase tracking-wider flex items-center gap-1.5">
                <BookOpen className="h-4 w-4 text-blue-600" />
                A. Nilai Hasil Belajar &amp; Deskripsi Capaian Kompetensi
              </h3>
              <span className="text-[11px] text-gray-500">Ambang KKTP: 75.0</span>
            </div>

            <div className="overflow-x-auto rounded-lg border border-gray-200">
              <table className="w-full text-left text-xs border-collapse">
                <thead className="bg-gray-100 text-gray-700 font-semibold border-b border-gray-200">
                  <tr>
                    <th className="px-3 py-2 w-10 text-center">No</th>
                    <th className="px-3 py-2 w-24">Kode</th>
                    <th className="px-3 py-2 w-48">Mata Pelajaran</th>
                    <th className="px-3 py-2 w-20 text-center">Nilai Akhir</th>
                    <th className="px-3 py-2 w-24 text-center">Status</th>
                    <th className="px-3 py-2">Capaian Pembelajaran (Kurikulum Merdeka)</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-200">
                  {subjects.length === 0 ? (
                    <tr>
                      <td colSpan={6} className="px-4 py-6 text-center text-gray-400 italic">
                        Belum ada data nilai akademik semester yang tercatat untuk siswa ini.
                      </td>
                    </tr>
                  ) : (
                    subjects.map((sub, idx) => (
                      <tr key={sub.subject_id || idx} className="hover:bg-gray-50/70 transition">
                        <td className="px-3 py-2 text-center text-gray-500">{idx + 1}</td>
                        <td className="px-3 py-2 font-mono text-gray-600 text-[11px]">
                          {sub.subject_code}
                        </td>
                        <td className="px-3 py-2 font-medium text-gray-900">
                          {sub.subject_name}
                          <span className="block text-[10px] text-gray-400">{sub.subject_group}</span>
                        </td>
                        <td className="px-3 py-2 text-center font-bold text-gray-900 text-sm">
                          {sub.final_score}
                        </td>
                        <td className="px-3 py-2 text-center">
                          <span
                            className={`inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold border ${
                              sub.level === 'tuntas'
                                ? 'bg-emerald-50 text-emerald-700 border-emerald-300'
                                : 'bg-rose-50 text-rose-700 border-rose-300'
                            }`}
                          >
                            {sub.level === 'tuntas' ? 'Tuntas' : 'Perlu Bimbingan'}
                          </span>
                        </td>
                        <td className="px-3 py-2 text-gray-700 text-xs leading-relaxed">
                          {sub.competency_description}
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          </div>

          {/* Section 2: Presensi Semester & Ekstrakurikuler */}
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {/* Rekap Kehadiran */}
            <div className="rounded-xl border border-gray-200 p-4 space-y-3 bg-white">
              <h3 className="text-xs font-bold text-gray-900 uppercase tracking-wider flex items-center gap-1.5">
                <CheckCircle2 className="h-4 w-4 text-emerald-600" />
                B. Rekap Ketidakhadiran Semester
              </h3>
              <div className="grid grid-cols-4 gap-2 text-center">
                <div className="rounded-lg bg-emerald-50 p-2 border border-emerald-100">
                  <div className="text-xs text-gray-500 font-medium">Hadir</div>
                  <div className="text-base font-bold text-emerald-700">{attendance.hadir}</div>
                  <div className="text-[10px] text-emerald-600 font-medium">
                    {attendance.percentage}%
                  </div>
                </div>
                <div className="rounded-lg bg-blue-50 p-2 border border-blue-100">
                  <div className="text-xs text-gray-500 font-medium">Izin</div>
                  <div className="text-base font-bold text-blue-700">{attendance.izin}</div>
                  <div className="text-[10px] text-gray-400">Hari</div>
                </div>
                <div className="rounded-lg bg-amber-50 p-2 border border-amber-100">
                  <div className="text-xs text-gray-500 font-medium">Sakit</div>
                  <div className="text-base font-bold text-amber-700">{attendance.sakit}</div>
                  <div className="text-[10px] text-gray-400">Hari</div>
                </div>
                <div className="rounded-lg bg-rose-50 p-2 border border-rose-100">
                  <div className="text-xs text-gray-500 font-medium">Alpa</div>
                  <div className="text-base font-bold text-rose-700">{attendance.alpa}</div>
                  <div className="text-[10px] text-gray-400">Hari</div>
                </div>
              </div>
            </div>

            {/* Ekstrakurikuler */}
            <div className="rounded-xl border border-gray-200 p-4 space-y-3 bg-white">
              <h3 className="text-xs font-bold text-gray-900 uppercase tracking-wider flex items-center gap-1.5">
                <Award className="h-4 w-4 text-purple-600" />
                C. Kegiatan Ekstrakurikuler
              </h3>
              {extracurriculars.length === 0 ? (
                <p className="text-xs text-gray-400 italic py-2">
                  Tidak terdaftar dalam kegiatan ekstrakurikuler semester ini.
                </p>
              ) : (
                <div className="space-y-2">
                  {extracurriculars.map((ekskul, idx) => (
                    <div
                      key={idx}
                      className="flex items-center justify-between rounded-lg bg-purple-50/50 p-2 border border-purple-100 text-xs"
                    >
                      <div>
                        <span className="font-semibold text-gray-900">{ekskul.name}</span>
                        <span className="block text-[11px] text-gray-500">
                          {ekskul.description || 'Aktif mengikuti kegiatan'}
                        </span>
                      </div>
                      <span className="inline-flex items-center rounded-md bg-purple-100 px-2 py-0.5 text-xs font-bold text-purple-800">
                        Predikat: {ekskul.predicate}
                      </span>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </div>

          {/* Section 3: Catatan Wali Kelas */}
          <div className="rounded-xl border border-gray-200 p-4 space-y-2 bg-gray-50/50">
            <h3 className="text-xs font-bold text-gray-900 uppercase tracking-wider">
              D. Catatan Perkembangan Wali Kelas
            </h3>
            <p className="text-xs text-gray-700 italic leading-relaxed border-l-2 border-blue-500 pl-3">
              &ldquo;{homeroomNote}&rdquo;
            </p>
          </div>

          {/* Section 4: Pengesahan & Token QR Code Verifikasi */}
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-6 pt-4 border-t border-gray-200 text-center text-xs">
            {/* Tanda Tangan Wali Murid */}
            <div className="space-y-12">
              <p className="text-gray-500">Mengetahui,<br />Orang Tua / Wali Murid</p>
              <div className="border-b border-dashed border-gray-300 w-36 mx-auto" />
              <p className="text-[11px] text-gray-400">( ........................................ )</p>
            </div>

            {/* Token QR Verifikasi Resmi */}
            <div className="rounded-xl border border-blue-200 bg-blue-50/40 p-3 space-y-2 text-center">
              <div className="flex items-center justify-center gap-1.5 text-blue-800 font-bold text-xs">
                <ShieldCheck className="h-4 w-4" />
                Verifikasi Dokumen Digital
              </div>
              <p className="text-[10px] text-gray-600 leading-tight">
                Dokumen ini resmi diterbitkan oleh Portal Sekolah Satu Pintu SISTLABUM.
              </p>
              {report?.verify_token ? (
                <div className="space-y-1">
                  <div className="font-mono text-[9px] text-gray-700 break-all bg-white p-1 rounded border border-gray-200">
                    {report.verify_token}
                  </div>
                  <button
                    onClick={handleCopyToken}
                    className="inline-flex items-center gap-1 text-[10px] font-semibold text-blue-600 hover:text-blue-800"
                  >
                    {copiedToken ? (
                      <>
                        <Check className="h-3 w-3 text-emerald-600" /> Tersalin
                      </>
                    ) : (
                      <>
                        <Copy className="h-3 w-3" /> Salin Token QR
                      </>
                    )}
                  </button>
                </div>
              ) : (
                <div className="text-[10px] text-gray-400 italic">
                  Token QR akan aktif setelah e-rapor digenerate resmi.
                </div>
              )}
            </div>

            {/* Tanda Tangan Kepala Sekolah */}
            <div className="space-y-12">
              <p className="text-gray-500">
                Kota Malang, {new Date().toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })}<br />
                Kepala Sekolah
              </p>
              <div>
                {status === 'disetujui' || status === 'terbit' ? (
                  <span className="inline-flex items-center gap-1 rounded bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-800 border border-emerald-300">
                    <CheckCircle2 className="h-3 w-3" /> Tanda Tangan Digital Sah
                  </span>
                ) : (
                  <div className="border-b border-dashed border-gray-300 w-36 mx-auto" />
                )}
              </div>
              <p className="font-semibold text-gray-800 text-xs">
                {report?.approver?.name || report?.approver?.username || 'Kepala Sekolah Labschool UM'}
              </p>
            </div>
          </div>
        </div>

        {/* Modal Footer Toolbar */}
        <div className="flex flex-wrap items-center justify-between border-t border-gray-200 bg-gray-50 px-6 py-3 gap-3">
          <div className="flex items-center gap-2">
            {!report && onGenerateFromPreview && (
              <Button
                variant="default"
                size="sm"
                onClick={onGenerateFromPreview}
                className="gap-1.5 text-xs bg-blue-600 hover:bg-blue-700"
              >
                <FileCheck2 className="h-3.5 w-3.5" />
                Simpan &amp; Terbitkan E-Rapor
              </Button>
            )}

            {/* Aksi Kunci / Buka Kunci oleh Admin Kurikulum */}
            {canLock && report?.id && (
              <>
                {status === 'terkunci' ? (
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => handleToggleLock(false)}
                    disabled={isUpdatingStatus}
                    className="gap-1.5 text-xs text-amber-700 border-amber-300 hover:bg-amber-50"
                  >
                    <Unlock className="h-3.5 w-3.5" />
                    Buka Kunci (Revisi)
                  </Button>
                ) : (
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => handleToggleLock(true)}
                    disabled={isUpdatingStatus}
                    className="gap-1.5 text-xs text-amber-700 border-amber-300 hover:bg-amber-50"
                  >
                    <Lock className="h-3.5 w-3.5" />
                    Kunci Rapor (Kurikulum)
                  </Button>
                )}
              </>
            )}

            {/* Aksi Persetujuan / ACC oleh Kepala Sekolah */}
            {canApprove && report?.id && (
              <div className="flex items-center gap-2">
                {status !== 'disetujui' && status !== 'terbit' && (
                  <Button
                    variant="default"
                    size="sm"
                    onClick={() => handleUpdateStatus('disetujui')}
                    disabled={isUpdatingStatus}
                    className="gap-1.5 text-xs bg-emerald-600 hover:bg-emerald-700 text-white"
                  >
                    <Check className="h-3.5 w-3.5" />
                    Sahkan / ACC
                  </Button>
                )}
                {status === 'disetujui' && (
                  <Button
                    variant="default"
                    size="sm"
                    onClick={() => handleUpdateStatus('terbit')}
                    disabled={isUpdatingStatus}
                    className="gap-1.5 text-xs bg-blue-600 hover:bg-blue-700 text-white"
                  >
                    <Send className="h-3.5 w-3.5" />
                    Terbitkan Resmi
                  </Button>
                )}
                {status !== 'draft' && (
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => handleUpdateStatus('draft')}
                    disabled={isUpdatingStatus}
                    className="gap-1.5 text-xs text-gray-600 hover:text-gray-900"
                  >
                    Kembalikan ke Draf
                  </Button>
                )}
              </div>
            )}
          </div>

          <Button variant="outline" size="sm" onClick={onClose} className="text-xs">
            Tutup
          </Button>
        </div>
      </div>
    </div>
  );
}
