'use client';

import React, { useState, useEffect, useCallback } from 'react';
import Link from 'next/link';
import {
  FileCheck2,
  Download,
  ExternalLink,
  Loader2,
  ShieldCheck,
  AlertCircle,
  BookOpen,
} from 'lucide-react';
import { academicApi } from '@/features/academic/academic-api';
import {
  ProgressReportItem,
  ReportCardItem,
  PROGRESS_REPORT_STATUS_LABELS,
  REPORT_CARD_STATUS_LABELS,
} from '@/types/academic';
import { Button } from '@/components/ui/button';
import { toast } from '@/components/ui/toast';

interface StudentReportPortalSectionProps {
  mode: 'progress' | 'semester';
  studentId?: string;
}

export function StudentReportPortalSection({
  mode,
  studentId,
}: StudentReportPortalSectionProps) {
  const [progressReports, setProgressReports] = useState<ProgressReportItem[]>([]);
  const [reportCards, setReportCards] = useState<ReportCardItem[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [downloadingId, setDownloadingId] = useState<string | null>(null);

  const loadReports = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    try {
      if (mode === 'progress') {
        const res = await academicApi.listProgressReports({
          student_id: studentId || undefined,
          per_page: 20,
        });
        setProgressReports(res.data || []);
      } else {
        const res = await academicApi.listReportCards({
          student_id: studentId || undefined,
          per_page: 20,
        });
        setReportCards(res.data || []);
      }
    } catch {
      setError('Gagal memuat daftar dokumen rapor resmi dari server.');
    } finally {
      setIsLoading(false);
    }
  }, [mode, studentId]);

  useEffect(() => {
    loadReports();
  }, [loadReports]);

  const handleDownloadPdf = async (id: string, token: string, type: 'progress' | 'semester') => {
    setDownloadingId(id);
    try {
      const blob =
        type === 'progress'
          ? await academicApi.downloadProgressReportPdf(id)
          : await academicApi.downloadReportCardPdf(id);
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download =
        type === 'progress'
          ? `e-progress-report-${token.slice(0, 8)}.pdf`
          : `e-rapor-semester-${token.slice(0, 8)}.pdf`;
      document.body.appendChild(a);
      a.click();
      window.URL.revokeObjectURL(url);
      document.body.removeChild(a);
    } catch {
      toast.error('Gagal mengunduh berkas PDF resmi. Pastikan rapor telah disahkan.');
    } finally {
      setDownloadingId(null);
    }
  };

  if (isLoading) {
    return (
      <div className="flex items-center justify-center gap-2 py-12 text-xs text-slate-500">
        <Loader2 className="h-4 w-4 animate-spin text-blue-700" />
        <span>Memuat daftar dokumen {mode === 'progress' ? 'E-Progress Report' : 'E-Rapor Semester'}...</span>
      </div>
    );
  }

  if (error) {
    return (
      <div className="rounded-xl border border-rose-200 bg-rose-50 p-4 text-center space-y-2">
        <AlertCircle className="h-6 w-6 text-rose-600 mx-auto" />
        <p className="text-xs font-medium text-rose-800">{error}</p>
        <Button size="sm" variant="outline" onClick={loadReports} className="text-xs">
          Coba Lagi
        </Button>
      </div>
    );
  }

  const items = mode === 'progress' ? progressReports : reportCards;

  if (items.length === 0) {
    return (
      <div className="rounded-xl border border-dashed border-slate-300 bg-slate-50/70 p-8 text-center space-y-1.5">
        <BookOpen className="h-8 w-8 text-slate-400 mx-auto" />
        <h3 className="text-sm font-bold text-slate-700">
          Belum Ada {mode === 'progress' ? 'E-Progress Report' : 'E-Rapor Semester'} Terbit
        </h3>
        <p className="text-xs text-slate-500 max-w-md mx-auto">
          Dokumen resmi akan muncul di sini setelah disahkan oleh Kepala Sekolah dan diterbitkan oleh wali kelas/kurikulum.
        </p>
      </div>
    );
  }

  return (
    <div className="space-y-3">
      {mode === 'progress'
        ? progressReports.map((rep) => {
            const period = rep.data_snapshot?.period;
            return (
              <div
                key={rep.id}
                className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-2xs"
              >
                <div className="space-y-1">
                  <div className="flex items-center gap-2">
                    <FileCheck2 className="h-4 w-4 text-emerald-700" />
                    <span className="font-bold text-sm text-slate-900">
                      E-Progress Report Bulan {period?.month ?? rep.period?.month ?? '-'}
                    </span>
                    <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-800">
                      {PROGRESS_REPORT_STATUS_LABELS[rep.status] || rep.status}
                    </span>
                  </div>
                  <p className="text-xs text-slate-500">
                    {period?.semester_name ?? 'Semester'} • T.A. {period?.academic_year ?? '-'} • Disahkan oleh{' '}
                    {rep.approver?.name || rep.approver?.username || 'Kepala Sekolah'}
                  </p>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                  <Link
                    href={`/verifikasi/${rep.verify_token}`}
                    target="_blank"
                    className="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-100"
                  >
                    <ShieldCheck className="h-3.5 w-3.5 text-emerald-600" />
                    <span>Verifikasi QR</span>
                    <ExternalLink className="h-3 w-3" />
                  </Link>
                  <Button
                    size="sm"
                    onClick={() => handleDownloadPdf(rep.id, rep.verify_token, 'progress')}
                    disabled={downloadingId === rep.id}
                    className="gap-1.5 text-xs bg-blue-900 hover:bg-blue-950 text-white"
                  >
                    {downloadingId === rep.id ? (
                      <Loader2 className="h-3.5 w-3.5 animate-spin" />
                    ) : (
                      <Download className="h-3.5 w-3.5" />
                    )}
                    <span>Unduh PDF</span>
                  </Button>
                </div>
              </div>
            );
          })
        : reportCards.map((rc) => {
            const sem = rc.data_snapshot?.semester;
            return (
              <div
                key={rc.id}
                className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-2xs"
              >
                <div className="space-y-1">
                  <div className="flex items-center gap-2">
                    <FileCheck2 className="h-4 w-4 text-blue-800" />
                    <span className="font-bold text-sm text-slate-900">
                      E-Rapor Semester {sem?.name ?? rc.semester?.name ?? '-'}
                    </span>
                    <span className="rounded-full bg-blue-100 px-2 py-0.5 text-[10px] font-bold text-blue-900">
                      {REPORT_CARD_STATUS_LABELS[rc.status] || rc.status}
                    </span>
                  </div>
                  <p className="text-xs text-slate-500">
                    T.A. {sem?.academic_year ?? '-'} • Kelas {rc.data_snapshot?.student?.class_name ?? '-'} • Disahkan oleh{' '}
                    {rc.approver?.name || rc.approver?.username || 'Kepala Sekolah'}
                  </p>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                  <Link
                    href={`/verifikasi/${rc.verify_token}`}
                    target="_blank"
                    className="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-100"
                  >
                    <ShieldCheck className="h-3.5 w-3.5 text-emerald-600" />
                    <span>Verifikasi QR</span>
                    <ExternalLink className="h-3 w-3" />
                  </Link>
                  <Button
                    size="sm"
                    onClick={() => handleDownloadPdf(rc.id, rc.verify_token, 'semester')}
                    disabled={downloadingId === rc.id}
                    className="gap-1.5 text-xs bg-blue-900 hover:bg-blue-950 text-white"
                  >
                    {downloadingId === rc.id ? (
                      <Loader2 className="h-3.5 w-3.5 animate-spin" />
                    ) : (
                      <Download className="h-3.5 w-3.5" />
                    )}
                    <span>Unduh PDF E-Rapor</span>
                  </Button>
                </div>
              </div>
            );
          })}
    </div>
  );
}
