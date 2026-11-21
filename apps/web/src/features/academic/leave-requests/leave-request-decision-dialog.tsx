'use client';

import React, { useState, useEffect } from 'react';
import {
  LeaveRequestItem,
  LEAVE_TYPE_LABELS,
  LEAVE_TYPE_COLORS,
  LEAVE_STATUS_LABELS,
  LEAVE_STATUS_COLORS,
} from '@/types/academic';
import { academicApi } from '../academic-api';
import { Button } from '@/components/ui/button';
import {
  X,
  FileCheck2,
  Calendar,
  User,
  GraduationCap,
  Paperclip,
  CheckCircle2,
  XCircle,
  ExternalLink,
  AlertCircle,
  Clock,
  MessageSquare,
  Loader2,
} from 'lucide-react';

interface LeaveRequestDecisionDialogProps {
  isOpen: boolean;
  onClose: () => void;
  leave: LeaveRequestItem | null;
  onSuccess: () => void;
}

export function LeaveRequestDecisionDialog({
  isOpen,
  onClose,
  leave,
  onSuccess,
}: LeaveRequestDecisionDialogProps) {
  const [decision, setDecision] = useState<'disetujui' | 'ditolak'>('disetujui');
  const [decisionNotes, setDecisionNotes] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const [attachmentUrl, setAttachmentUrl] = useState<string | null>(null);
  const [loadingAttachment, setLoadingAttachment] = useState(false);

  useEffect(() => {
    if (leave) {
      setDecision(leave.status === 'ditolak' ? 'ditolak' : 'disetujui');
      setDecisionNotes(leave.decision_notes || '');
      setError(null);

      if (leave.attachment_file_id) {
        setLoadingAttachment(true);
        academicApi
          .getFileUrl(leave.attachment_file_id)
          .then((res) => {
            setAttachmentUrl(res.url);
          })
          .catch(() => {
            setAttachmentUrl(null);
          })
          .finally(() => {
            setLoadingAttachment(false);
          });
      } else {
        setAttachmentUrl(null);
      }
    }
  }, [leave]);

  if (!isOpen || !leave) return null;

  const className =
    leave.student?.classes?.[0]?.name ||
    (leave.student as any)?.class_name ||
    'Kelas Siswa';

  const durationDays = (() => {
    try {
      const start = new Date(leave.start_date);
      const end = new Date(leave.end_date);
      const diffTime = Math.abs(end.getTime() - start.getTime());
      return Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
    } catch {
      return 1;
    }
  })();

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSubmitting(true);
    setError(null);

    try {
      await academicApi.decideLeaveRequest(leave.id, {
        status: decision,
        decision_notes: decisionNotes.trim() ? decisionNotes.trim() : undefined,
      });

      onSuccess();
      onClose();
    } catch (err: any) {
      const message =
        err?.response?.data?.message ||
        err?.response?.data?.error?.message ||
        'Gagal menyimpan keputusan permohonan izin.';
      setError(message);
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm animate-in fade-in duration-150">
      <div
        className="w-full max-w-2xl bg-white rounded-xl shadow-2xl border border-slate-200 overflow-hidden flex flex-col max-h-[90vh] animate-in zoom-in-95 duration-150"
        role="dialog"
        aria-modal="true"
        aria-labelledby="decision-dialog-title"
      >
        {/* Header Modal */}
        <div className="px-6 py-4 bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 text-white flex items-center justify-between shrink-0">
          <div className="flex items-center gap-3">
            <div className="p-2 bg-indigo-500/20 border border-indigo-400/30 rounded-lg">
              <FileCheck2 className="w-5 h-5 text-indigo-300" />
            </div>
            <div>
              <h2 id="decision-dialog-title" className="text-lg font-bold">
                Tinjau & Putuskan Permohonan Izin
              </h2>
              <p className="text-xs text-indigo-200">
                Verifikasi dokumen dan tentukan persetujuan wali kelas / sekolah
              </p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="p-1.5 text-slate-400 hover:text-white hover:bg-white/10 rounded-lg transition-colors"
            aria-label="Tutup modal"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Content Body */}
        <form onSubmit={handleSubmit} className="flex-1 overflow-y-auto p-6 space-y-6">
          {error && (
            <div className="p-3 bg-rose-50 border border-rose-200 rounded-lg text-rose-700 text-sm flex items-start gap-2">
              <AlertCircle className="w-4 h-4 shrink-0 mt-0.5" />
              <span>{error}</span>
            </div>
          )}

          {/* Kartu Profil Siswa & Info Permohonan */}
          <div className="bg-slate-50 border border-slate-200 rounded-xl p-4 space-y-3">
            <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 pb-3">
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 rounded-full bg-indigo-100 text-indigo-700 font-bold flex items-center justify-center text-sm">
                  {leave.student?.full_name?.charAt(0) || 'S'}
                </div>
                <div>
                  <h3 className="font-semibold text-slate-800 text-base">
                    {leave.student?.full_name || 'Nama Siswa'}
                  </h3>
                  <div className="flex items-center gap-2 text-xs text-slate-500">
                    <span>NIS: {leave.student?.nis || '-'}</span>
                    <span>•</span>
                    <span className="inline-flex items-center gap-1 font-medium text-slate-700">
                      <GraduationCap className="w-3.5 h-3.5 text-slate-400" />
                      {className}
                    </span>
                  </div>
                </div>
              </div>

              <div className="flex items-center gap-2">
                <span
                  className={`inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold ${
                    LEAVE_TYPE_COLORS[leave.leave_type] || 'bg-slate-100 text-slate-800'
                  }`}
                >
                  {LEAVE_TYPE_LABELS[leave.leave_type] || leave.leave_type}
                </span>

                <span
                  className={`inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold ${
                    LEAVE_STATUS_COLORS[leave.status]?.badge || 'bg-slate-100 text-slate-800'
                  }`}
                >
                  {LEAVE_STATUS_LABELS[leave.status] || leave.status}
                </span>
              </div>
            </div>

            {/* Grid Detail Tanggal & Alasan */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
              <div className="flex items-start gap-2 text-slate-600">
                <Calendar className="w-4 h-4 text-slate-400 shrink-0 mt-0.5" />
                <div>
                  <span className="font-medium text-slate-700">Rentang Waktu:</span>
                  <p className="text-slate-900 font-semibold mt-0.5">
                    {leave.start_date} s.d. {leave.end_date} ({durationDays} hari)
                  </p>
                </div>
              </div>

              <div className="flex items-start gap-2 text-slate-600">
                <User className="w-4 h-4 text-slate-400 shrink-0 mt-0.5" />
                <div>
                  <span className="font-medium text-slate-700">Pemohon:</span>
                  <p className="text-slate-900 mt-0.5">
                    {leave.requester?.username
                      ? `${leave.requester.username} (${leave.requester.email})`
                      : 'Wali Murid / Siswa'}
                  </p>
                </div>
              </div>
            </div>

            {/* Alasan */}
            <div className="bg-white p-3 rounded-lg border border-slate-200">
              <span className="text-xs font-semibold text-slate-600 flex items-center gap-1.5 mb-1">
                <MessageSquare className="w-3.5 h-3.5 text-slate-400" />
                Alasan Pengajuan:
              </span>
              <p className="text-sm text-slate-800 whitespace-pre-line leading-relaxed">
                {leave.reason}
              </p>
            </div>

            {/* Lampiran Dokumen Surat Dokter / Izin */}
            <div className="pt-1">
              <span className="text-xs font-semibold text-slate-600 flex items-center gap-1.5 mb-2">
                <Paperclip className="w-3.5 h-3.5 text-slate-400" />
                Berkas Lampiran Surat:
              </span>

              {leave.attachment_file_id ? (
                loadingAttachment ? (
                  <div className="flex items-center gap-2 text-xs text-slate-500 py-1">
                    <Loader2 className="w-4 h-4 animate-spin text-indigo-600" />
                    <span>Menyiapkan tautan dokumen aman...</span>
                  </div>
                ) : attachmentUrl ? (
                  <div className="flex items-center justify-between p-2.5 bg-indigo-50/70 border border-indigo-200/80 rounded-lg">
                    <div className="flex items-center gap-2 text-xs font-medium text-indigo-900">
                      <Paperclip className="w-4 h-4 text-indigo-600" />
                      <span>Dokumen Lampiran Terunggah</span>
                    </div>
                    <a
                      href={attachmentUrl}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="inline-flex items-center gap-1.5 px-3 py-1 bg-indigo-600 text-white rounded text-xs font-medium hover:bg-indigo-700 transition-colors shadow-sm"
                    >
                      <span>Buka Dokumen</span>
                      <ExternalLink className="w-3 h-3" />
                    </a>
                  </div>
                ) : (
                  <div className="text-xs text-rose-600 bg-rose-50 p-2 rounded border border-rose-200">
                    Tautan dokumen privat tidak dapat dimuat atau sudah kedaluwarsa.
                  </div>
                )
              ) : (
                <div className="text-xs text-slate-500 italic bg-white p-2 rounded border border-dashed border-slate-200">
                  Tidak ada berkas lampiran yang diunggah.
                </div>
              )}
            </div>
          </div>

          {/* Form Keputusan */}
          <div className="space-y-4 pt-2">
            <div>
              <label className="block text-sm font-semibold text-slate-800 mb-2">
                Pilih Keputusan:
              </label>
              <div className="grid grid-cols-2 gap-3">
                <button
                  type="button"
                  onClick={() => setDecision('disetujui')}
                  className={`p-3 rounded-xl border text-sm font-semibold flex items-center justify-center gap-2 transition-all ${
                    decision === 'disetujui'
                      ? 'bg-emerald-50 border-emerald-500 text-emerald-800 ring-2 ring-emerald-500/20 shadow-sm'
                      : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50'
                  }`}
                >
                  <CheckCircle2
                    className={`w-4 h-4 ${
                      decision === 'disetujui' ? 'text-emerald-600' : 'text-slate-400'
                    }`}
                  />
                  <span>Setujui Permohonan</span>
                </button>

                <button
                  type="button"
                  onClick={() => setDecision('ditolak')}
                  className={`p-3 rounded-xl border text-sm font-semibold flex items-center justify-center gap-2 transition-all ${
                    decision === 'ditolak'
                      ? 'bg-rose-50 border-rose-500 text-rose-800 ring-2 ring-rose-500/20 shadow-sm'
                      : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50'
                  }`}
                >
                  <XCircle
                    className={`w-4 h-4 ${
                      decision === 'ditolak' ? 'text-rose-600' : 'text-slate-400'
                    }`}
                  />
                  <span>Tolak Permohonan</span>
                </button>
              </div>
            </div>

            <div>
              <label
                htmlFor="decision-notes"
                className="block text-xs font-semibold text-slate-700 mb-1"
              >
                Catatan Wali Kelas / Catatan Keputusan (Opsional):
              </label>
              <textarea
                id="decision-notes"
                rows={3}
                value={decisionNotes}
                onChange={(e) => setDecisionNotes(e.target.value)}
                placeholder={
                  decision === 'disetujui'
                    ? 'Contoh: Semoga lekas sembuh, materi pelajaran dapat diakses via portal LMS.'
                    : 'Contoh: Mohon melampirkan surat keterangan dokter resmi untuk izin sakit > 2 hari.'
                }
                className="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"
              />
              <p className="text-[11px] text-slate-500 mt-1">
                Catatan ini akan dikirimkan otomatis melalui notifikasi kepada pemohon.
              </p>
            </div>
          </div>

          {/* Footer Action */}
          <div className="pt-4 border-t border-slate-200 flex items-center justify-end gap-3">
            <Button
              type="button"
              variant="outline"
              onClick={onClose}
              disabled={submitting}
            >
              Batal
            </Button>
            <Button
              type="submit"
              disabled={submitting}
              className={
                decision === 'disetujui'
                  ? 'bg-emerald-600 hover:bg-emerald-700 text-white font-semibold'
                  : 'bg-rose-600 hover:bg-rose-700 text-white font-semibold'
              }
            >
              {submitting ? (
                <>
                  <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                  Menyimpan...
                </>
              ) : decision === 'disetujui' ? (
                'Simpan Persetujuan'
              ) : (
                'Simpan Penolakan'
              )}
            </Button>
          </div>
        </form>
      </div>
    </div>
  );
}
