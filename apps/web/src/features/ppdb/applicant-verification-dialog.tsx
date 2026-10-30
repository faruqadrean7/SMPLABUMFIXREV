'use client';

import React, { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { ppdbApi } from '@/features/ppdb/ppdb-api';
import {
  ApplicantDetail,
  ApplicantDocumentItem,
  VerifyDocumentPayload,
  VerifyApplicationPayload,
} from '@/types/ppdb';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { formatTanggalIndo, formatWaktuIndo } from '@/lib/utils';
import { ApiClientError } from '@/lib/api/errors';
import {
  X,
  CheckCircle2,
  XCircle,
  AlertCircle,
  FileText,
  User,
  History,
  Phone,
  School,
  MapPin,
  Calendar,
  ShieldCheck,
  RotateCcw,
  Check,
  Eye,
  Download,
  Loader2,
} from 'lucide-react';

export interface ApplicantVerificationDialogProps {
  isOpen: boolean;
  applicantId: string | null;
  onClose: () => void;
  onSuccess?: () => void;
}

export function ApplicantVerificationDialog({
  isOpen,
  applicantId,
  onClose,
  onSuccess,
}: ApplicantVerificationDialogProps) {
  const queryClient = useQueryClient();

  // Document reject modal state
  const [rejectingDocId, setRejectingDocId] = useState<string | null>(null);
  const [rejectDocReason, setRejectDocReason] = useState<string>('');

  // Overall application revision modal state
  const [showRevisionForm, setShowRevisionForm] = useState<boolean>(false);
  const [revisionNote, setRevisionNote] = useState<string>('');

  // Authenticated file download state
  const [downloadingDocId, setDownloadingDocId] = useState<string | null>(null);

  // In-dialog document preview state
  const [preview, setPreview] = useState<{
    url: string;
    mime: string;
    name: string;
  } | null>(null);
  const [previewingDocId, setPreviewingDocId] = useState<string | null>(null);

  const closePreview = React.useCallback(() => {
    setPreview((prev) => {
      if (prev) {
        try {
          URL.revokeObjectURL(prev.url);
        } catch {
          // abaikan
        }
      }
      return null;
    });
  }, []);

  React.useEffect(() => {
    return () => {
      // bersihkan object URL saat komponen dilepas
      setPreview((prev) => {
        if (prev) {
          try {
            URL.revokeObjectURL(prev.url);
          } catch {
            // abaikan
          }
        }
        return null;
      });
    };
  }, []);

  const handlePreviewDoc = async (docId: string, filename?: string, mime?: string) => {
    if (!applicantId) return;
    try {
      setPreviewingDocId(docId);
      const blob = await ppdbApi.downloadDocument(applicantId, docId);
      const objectUrl = URL.createObjectURL(blob);
      closePreview();
      setPreview({
        url: objectUrl,
        mime: mime || blob.type || 'application/octet-stream',
        name: filename || `dokumen_${docId}`,
      });
    } catch {
      setFeedbackMessage({
        type: 'error',
        text: 'Gagal memuat pratinjau dokumen. Pastikan sesi login aktif.',
      });
    } finally {
      setPreviewingDocId(null);
    }
  };

  const [feedbackMessage, setFeedbackMessage] = useState<{
    type: 'success' | 'error';
    text: string;
  } | null>(null);

  const handleDownloadDoc = async (docId: string, filename?: string) => {
    if (!applicantId) return;
    try {
      setDownloadingDocId(docId);
      const blob = await ppdbApi.downloadDocument(applicantId, docId);
      const objectUrl = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = objectUrl;
      a.download = filename || `dokumen_${docId}`;
      a.target = '_blank';
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      setTimeout(() => URL.revokeObjectURL(objectUrl), 1000);
    } catch {
      setFeedbackMessage({
        type: 'error',
        text: 'Gagal mengunduh berkas dokumen. Pastikan sesi login aktif.',
      });
    } finally {
      setDownloadingDocId(null);
    }
  };

  // 1. Fetch Applicant Detail
  const {
    data: applicant,
    isLoading,
    isError,
    error,
    refetch,
  } = useQuery<ApplicantDetail>({
    queryKey: ['ppdb-applicant-detail', applicantId],
    queryFn: () => ppdbApi.getApplicant(applicantId!),
    enabled: isOpen && !!applicantId,
  });

  // 2. Verify Document Mutation
  const verifyDocMutation = useMutation({
    mutationFn: ({ docId, payload }: { docId: string; payload: VerifyDocumentPayload }) =>
      ppdbApi.verifyDocument(docId, payload),
    onSuccess: (updatedDoc) => {
      queryClient.invalidateQueries({ queryKey: ['ppdb-applicant-detail', applicantId] });
      queryClient.invalidateQueries({ queryKey: ['ppdb-applicants-list'] });
      setRejectingDocId(null);
      setRejectDocReason('');
      setFeedbackMessage({
        type: 'success',
        text: `Status berkas ${updatedDoc.document_type} berhasil diperbarui.`,
      });
    },
    onError: (err: unknown) => {
      const msg = err instanceof ApiClientError ? err.message : 'Gagal memverifikasi berkas';
      setFeedbackMessage({ type: 'error', text: msg });
    },
  });

  // 3. Verify Overall Application Mutation
  const verifyAppMutation = useMutation({
    mutationFn: (payload: VerifyApplicationPayload) =>
      ppdbApi.verifyApplication(applicantId!, payload),
    onSuccess: (updatedApp) => {
      queryClient.invalidateQueries({ queryKey: ['ppdb-applicant-detail', applicantId] });
      queryClient.invalidateQueries({ queryKey: ['ppdb-applicants-list'] });
      setShowRevisionForm(false);
      setRevisionNote('');
      setFeedbackMessage({
        type: 'success',
        text: `Pendaftaran berhasil diubah ke status: ${updatedApp.status.toUpperCase()}`,
      });
      if (onSuccess) onSuccess();
    },
    onError: (err: unknown) => {
      const msg = err instanceof ApiClientError ? err.message : 'Gagal memverifikasi pendaftaran';
      setFeedbackMessage({ type: 'error', text: msg });
    },
  });

  if (!isOpen || !applicantId) return null;

  const handleVerifyDoc = (docId: string, status: 'valid' | 'ditolak', reason?: string) => {
    verifyDocMutation.mutate({
      docId,
      payload: { status, reject_reason: reason },
    });
  };

  const handleApproveApplication = () => {
    verifyAppMutation.mutate({
      status: 'verified',
      note: 'Berkas dan formulir pendaftaran telah diverifikasi dan dinyatakan lengkap.',
    });
  };

  const handleSubmitRevision = () => {
    if (!revisionNote.trim()) {
      setFeedbackMessage({
        type: 'error',
        text: 'Catatan alasan perbaikan pendaftaran wajib diisi.',
      });
      return;
    }
    verifyAppMutation.mutate({
      status: 'revision',
      note: revisionNote.trim(),
    });
  };

  const getStatusBadge = (status: string) => {
    switch (status) {
      case 'verified':
        return <Badge variant="success">TERVERIFIKASI</Badge>;
      case 'revision':
        return <Badge variant="warning">PERLU REVISI</Badge>;
      case 'submitted':
        return <Badge variant="info">MENUNGGU VERIFIKASI</Badge>;
      case 'selected':
        return <Badge variant="success">DITERIMA</Badge>;
      case 'waitlist':
        return <Badge variant="warning">CADANGAN</Badge>;
      case 'rejected':
        return <Badge variant="danger">DITOLAK</Badge>;
      case 'enrolled':
        return <Badge variant="default">TERDAFTAR ULANG</Badge>;
      default:
        return <Badge variant="outline">{status.toUpperCase()}</Badge>;
    }
  };

  return (
    <>
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
      <div className="relative flex h-[90vh] w-full max-w-6xl flex-col rounded-2xl bg-white shadow-2xl overflow-hidden">
        {/* Header */}
        <div className="flex items-center justify-between border-b border-gray-100 bg-gray-50/80 px-6 py-4">
          <div className="flex items-center gap-3">
            <div className="rounded-xl bg-emerald-100 p-2 text-emerald-700">
              <ShieldCheck className="h-6 w-6" />
            </div>
            <div>
              <div className="flex items-center gap-2">
                <h2 className="text-lg font-bold text-gray-900">
                  {applicant ? applicant.full_name : 'Memuat Data Calon Siswa...'}
                </h2>
                {applicant && getStatusBadge(applicant.status)}
              </div>
              <p className="text-xs text-gray-500">
                No. Registrasi:{' '}
                <span className="font-mono font-semibold text-gray-700">
                  {applicant?.registration_no || '-'}
                </span>{' '}
                • Jalur: {applicant?.track?.name || '-'} • Gelombang: {applicant?.wave?.name || '-'}
              </p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="rounded-lg p-2 text-gray-400 hover:bg-gray-200 hover:text-gray-700 transition"
          >
            <X className="h-5 w-5" />
          </button>
        </div>

        {/* Feedback Alert */}
        {feedbackMessage && (
          <div
            className={`flex items-center justify-between px-6 py-2.5 text-xs font-medium ${
              feedbackMessage.type === 'success'
                ? 'bg-emerald-50 text-emerald-800 border-b border-emerald-100'
                : 'bg-red-50 text-red-800 border-b border-red-100'
            }`}
          >
            <span>{feedbackMessage.text}</span>
            <button
              onClick={() => setFeedbackMessage(null)}
              className="text-gray-400 hover:text-gray-600"
            >
              <X className="h-4 w-4" />
            </button>
          </div>
        )}

        {/* Main Body - Split Layout */}
        <div className="flex flex-1 overflow-hidden">
          {isLoading ? (
            <div className="flex flex-1 items-center justify-center">
              <div className="flex flex-col items-center gap-2">
                <div className="h-8 w-8 animate-spin rounded-full border-4 border-emerald-500 border-t-transparent" />
                <span className="text-xs text-gray-500">Memuat berkas pendaftaran...</span>
              </div>
            </div>
          ) : isError || !applicant ? (
            <div className="flex flex-1 items-center justify-center p-6 text-center text-red-600">
              <AlertCircle className="h-8 w-8 mb-2" />
              <p className="text-sm font-medium">Gagal memuat rincian pendaftar</p>
            </div>
          ) : (
            <>
              {/* Left Column: Biodata & Data Orang Tua & Riwayat Status */}
              <div className="w-1/2 overflow-y-auto border-r border-gray-100 p-6 space-y-6">
                {/* Biodata Siswa */}
                <div>
                  <h3 className="mb-3 flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-gray-500">
                    <User className="h-4 w-4 text-emerald-600" />
                    Biodata Calon Siswa
                  </h3>
                  <div className="grid grid-cols-2 gap-3 rounded-xl border border-gray-100 bg-gray-50/50 p-4 text-xs">
                    <div>
                      <span className="text-gray-400 block mb-0.5">Nama Lengkap</span>
                      <span className="font-semibold text-gray-800">{applicant.full_name}</span>
                    </div>
                    <div>
                      <span className="text-gray-400 block mb-0.5">Jenis Kelamin</span>
                      <span className="font-semibold text-gray-800">
                        {applicant.gender === 'L' ? 'Laki-laki' : 'Perempuan'}
                      </span>
                    </div>
                    <div>
                      <span className="text-gray-400 block mb-0.5">NISN</span>
                      <span className="font-mono font-semibold text-gray-800">
                        {applicant.nisn || '-'}
                      </span>
                    </div>
                    <div>
                      <span className="text-gray-400 block mb-0.5">NIK</span>
                      <span className="font-mono font-semibold text-gray-800">
                        {applicant.nik || '-'}
                      </span>
                    </div>
                    <div>
                      <span className="text-gray-400 block mb-0.5">Tempat, Tanggal Lahir</span>
                      <span className="text-gray-800">
                        {applicant.birth_place || '-'}, {formatTanggalIndo(applicant.birth_date)}
                      </span>
                    </div>
                    <div>
                      <span className="text-gray-400 block mb-0.5">Asal Sekolah</span>
                      <span className="text-gray-800">{applicant.school_origin || '-'}</span>
                    </div>
                    <div>
                      <span className="text-gray-400 block mb-0.5">Nomor Telepon</span>
                      <span className="text-gray-800">{applicant.phone || '-'}</span>
                    </div>
                    <div>
                      <span className="text-gray-400 block mb-0.5">Alamat</span>
                      <span className="text-gray-800">{applicant.address || '-'}</span>
                    </div>
                  </div>
                </div>

                {/* Data Orang Tua / Wali */}
                <div>
                  <h3 className="mb-3 flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-gray-500">
                    <User className="h-4 w-4 text-emerald-600" />
                    Data Orang Tua / Wali
                  </h3>
                  {applicant.parents && applicant.parents.length > 0 ? (
                    <div className="space-y-2">
                      {applicant.parents.map((p) => (
                        <div
                          key={p.id}
                          className="rounded-xl border border-gray-100 bg-gray-50/50 p-3 text-xs"
                        >
                          <div className="flex items-center justify-between mb-1">
                            <span className="font-bold text-gray-800">{p.full_name}</span>
                            <Badge variant="outline" className="text-[10px] uppercase">
                              {p.relationship}
                            </Badge>
                          </div>
                          <div className="grid grid-cols-2 gap-2 text-gray-600 mt-2">
                            <div>Telepon: {p.phone || '-'}</div>
                            <div>Pekerjaan: {p.occupation || '-'}</div>
                          </div>
                        </div>
                      ))}
                    </div>
                  ) : (
                    <div className="rounded-xl border border-dashed border-gray-200 p-4 text-center text-xs text-gray-400">
                      Data orang tua belum dilengkapi.
                    </div>
                  )}
                </div>

                {/* Riwayat Status Pendaftaran */}
                <div>
                  <h3 className="mb-3 flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-gray-500">
                    <History className="h-4 w-4 text-emerald-600" />
                    Jejak Audit & Histori Status
                  </h3>
                  {applicant.statusHistory && applicant.statusHistory.length > 0 ? (
                    <div className="space-y-2 border-l-2 border-emerald-200 pl-3">
                      {applicant.statusHistory.map((h) => (
                        <div key={h.id} className="relative pb-2 text-xs">
                          <div className="flex items-center gap-2 text-[11px] text-gray-400">
                            <span>{formatWaktuIndo(h.created_at)}</span>
                            <span>•</span>
                            <span className="font-medium text-emerald-700">
                              {h.actor?.name || 'Sistem'}
                            </span>
                          </div>
                          <p className="mt-0.5 font-medium text-gray-800">
                            {h.from_status ? `${h.from_status} → ` : ''}
                            <span className="font-bold text-emerald-800">{h.to_status}</span>
                          </p>
                          {h.note && (
                            <p className="mt-0.5 rounded bg-gray-50 p-1.5 text-gray-600 italic">
                              &ldquo;{h.note}&rdquo;
                            </p>
                          )}
                        </div>
                      ))}
                    </div>
                  ) : (
                    <div className="text-xs text-gray-400 italic">Belum ada riwayat status.</div>
                  )}
                </div>
              </div>

              {/* Right Column: Berkas Dokumen & Aksi Verifikasi */}
              <div className="w-1/2 flex flex-col justify-between overflow-y-auto p-6 bg-gray-50/30">
                <div className="space-y-4">
                  <div className="flex items-center justify-between">
                    <h3 className="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-gray-500">
                      <FileText className="h-4 w-4 text-emerald-600" />
                      Pemeriksaan Berkas Dokumen Persyaratan
                    </h3>
                    <span className="text-xs text-gray-500">
                      {applicant.documents?.filter((d) => d.status === 'valid').length || 0} /{' '}
                      {applicant.documents?.length || 0} Valid
                    </span>
                  </div>

                  {applicant.documents && applicant.documents.length > 0 ? (
                    <div className="space-y-3">
                      {applicant.documents.map((doc) => (
                        <div
                          key={doc.id}
                          className="rounded-xl border border-gray-200 bg-white p-4 shadow-sm"
                        >
                          <div className="flex items-start justify-between gap-2">
                            <div>
                              <div className="flex items-center gap-2">
                                <span className="font-semibold text-gray-800 text-sm">
                                  {doc.document_type.replace(/_/g, ' ').toUpperCase()}
                                </span>
                                {doc.status === 'valid' && (
                                  <span className="inline-flex items-center gap-1 rounded bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700">
                                    <Check className="h-3 w-3" /> Valid
                                  </span>
                                )}
                                {doc.status === 'ditolak' && (
                                  <span className="inline-flex items-center gap-1 rounded bg-red-50 px-2 py-0.5 text-[11px] font-semibold text-red-700">
                                    <X className="h-3 w-3" /> Ditolak
                                  </span>
                                )}
                                {doc.status === 'menunggu' && (
                                  <span className="inline-flex items-center gap-1 rounded bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-700">
                                    Menunggu
                                  </span>
                                )}
                              </div>
                              <p className="mt-1 text-xs text-gray-500 font-mono">
                                File: {doc.file?.original_name || doc.file?.filename || 'dokumen'}
                              </p>
                              {doc.reject_reason && (
                                <p className="mt-2 rounded bg-red-50 p-2 text-xs text-red-700">
                                  Alasan Penolakan: {doc.reject_reason}
                                </p>
                              )}
                            </div>

                            {/* Preview & Download File Buttons */}
                            {doc.file?.id && (
                              <div className="flex items-center gap-1.5">
                                <button
                                  type="button"
                                  onClick={() =>
                                    handlePreviewDoc(
                                      doc.id,
                                      doc.file?.original_name || doc.file?.filename,
                                      doc.file?.mime_type
                                    )
                                  }
                                  disabled={previewingDocId === doc.id}
                                  className="flex items-center gap-1 rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 hover:text-emerald-600 transition disabled:opacity-50"
                                >
                                  {previewingDocId === doc.id ? (
                                    <Loader2 className="h-3.5 w-3.5 animate-spin" />
                                  ) : (
                                    <Eye className="h-3.5 w-3.5" />
                                  )}
                                  {previewingDocId === doc.id ? 'Memuat...' : 'Lihat'}
                                </button>
                                <button
                                  type="button"
                                  onClick={() =>
                                    handleDownloadDoc(doc.id, doc.file?.original_name || doc.file?.filename)
                                  }
                                  disabled={downloadingDocId === doc.id}
                                  title="Unduh berkas"
                                  className="flex items-center gap-1 rounded-lg border border-gray-200 bg-white p-1.5 text-gray-500 hover:bg-gray-50 hover:text-emerald-600 transition disabled:opacity-50"
                                >
                                  {downloadingDocId === doc.id ? (
                                    <Loader2 className="h-3.5 w-3.5 animate-spin" />
                                  ) : (
                                    <Download className="h-3.5 w-3.5" />
                                  )}
                                </button>
                              </div>
                            )}
                          </div>

                          {/* Individual Doc Actions */}
                          <div className="mt-3 flex items-center justify-end gap-2 border-t border-gray-100 pt-3">
                            {doc.status !== 'valid' && (
                              <Button
                                size="sm"
                                variant="outline"
                                onClick={() => handleVerifyDoc(doc.id, 'valid')}
                                disabled={verifyDocMutation.isPending}
                                className="text-emerald-700 border-emerald-200 hover:bg-emerald-50 text-xs h-7"
                              >
                                <CheckCircle2 className="mr-1 h-3.5 w-3.5" />
                                Tandai Valid
                              </Button>
                            )}

                            {doc.status !== 'ditolak' && (
                              <Button
                                size="sm"
                                variant="outline"
                                onClick={() => {
                                  setRejectingDocId(doc.id);
                                  setRejectDocReason('');
                                }}
                                disabled={verifyDocMutation.isPending}
                                className="text-red-700 border-red-200 hover:bg-red-50 text-xs h-7"
                              >
                                <XCircle className="mr-1 h-3.5 w-3.5" />
                                Tolak Berkas
                              </Button>
                            )}
                          </div>

                          {/* Sub-form input alasan penolakan berkas */}
                          {rejectingDocId === doc.id && (
                            <div className="mt-3 rounded-lg border border-red-200 bg-red-50/50 p-3">
                              <label className="block text-xs font-medium text-red-800 mb-1">
                                Alasan Penolakan Berkas:
                              </label>
                              <input
                                type="text"
                                value={rejectDocReason}
                                onChange={(e) => setRejectDocReason(e.target.value)}
                                placeholder="Contoh: Foto buram atau dokumen tidak terbaca"
                                className="w-full rounded border border-red-300 bg-white px-2.5 py-1.5 text-xs text-gray-800 focus:outline-none focus:ring-1 focus:ring-red-500"
                              />
                              <div className="mt-2 flex justify-end gap-2">
                                <button
                                  type="button"
                                  onClick={() => setRejectingDocId(null)}
                                  className="text-xs text-gray-600 hover:text-gray-800"
                                >
                                  Batal
                                </button>
                                <Button
                                  size="sm"
                                  variant="danger"
                                  onClick={() =>
                                    handleVerifyDoc(doc.id, 'ditolak', rejectDocReason)
                                  }
                                  disabled={!rejectDocReason.trim() || verifyDocMutation.isPending}
                                  className="text-xs h-7 bg-red-600 hover:bg-red-700 text-white"
                                >
                                  Konfirmasi Tolak
                                </Button>
                              </div>
                            </div>
                          )}
                        </div>
                      ))}
                    </div>
                  ) : (
                    <div className="rounded-xl border border-dashed border-gray-200 p-8 text-center text-xs text-gray-400">
                      Pendaftar belum mengunggah berkas persyaratan.
                    </div>
                  )}

                  {/* Form Catatan Revisi Keseluruhan */}
                  {showRevisionForm && (
                    <div className="rounded-xl border border-amber-300 bg-amber-50 p-4">
                      <h4 className="text-xs font-bold text-amber-900 mb-1">
                        Kembalikan untuk Perbaikan (Revisi Pendaftaran)
                      </h4>
                      <p className="text-[11px] text-amber-700 mb-2">
                        Pendaftar akan menerima notifikasi dan instruksi untuk memperbaiki berkas
                        yang ditolak atau data yang keliru.
                      </p>
                      <textarea
                        value={revisionNote}
                        onChange={(e) => setRevisionNote(e.target.value)}
                        placeholder="Tuliskan catatan detail berkas apa yang harus diperbaiki..."
                        rows={3}
                        className="w-full rounded border border-amber-300 bg-white p-2 text-xs text-gray-800 focus:outline-none focus:ring-1 focus:ring-amber-500"
                      />
                      <div className="mt-3 flex justify-end gap-2">
                        <Button
                          size="sm"
                          variant="outline"
                          onClick={() => setShowRevisionForm(false)}
                          className="text-xs h-8"
                        >
                          Batal
                        </Button>
                        <Button
                          size="sm"
                          onClick={handleSubmitRevision}
                          disabled={!revisionNote.trim() || verifyAppMutation.isPending}
                          className="bg-amber-600 hover:bg-amber-700 text-white text-xs h-8"
                        >
                          Kirim Permintaan Revisi
                        </Button>
                      </div>
                    </div>
                  )}
                </div>

                {/* Footer Overall Actions */}
                <div className="mt-6 border-t border-gray-200 pt-4 flex items-center justify-between">
                  <div className="text-xs text-gray-500">
                    Status saat ini:{' '}
                    <span className="font-bold text-gray-700">
                      {applicant.status.toUpperCase()}
                    </span>
                  </div>

                  <div className="flex items-center gap-3">
                    {applicant.status !== 'revision' && (
                      <Button
                        variant="outline"
                        onClick={() => setShowRevisionForm(!showRevisionForm)}
                        disabled={verifyAppMutation.isPending}
                        className="text-amber-700 border-amber-300 hover:bg-amber-50 text-xs"
                      >
                        <RotateCcw className="mr-1.5 h-3.5 w-3.5" />
                        Minta Revisi
                      </Button>
                    )}

                    {applicant.status !== 'verified' && (
                      <Button
                        onClick={handleApproveApplication}
                        disabled={
                          verifyAppMutation.isPending ||
                          applicant.status === 'draft' ||
                          applicant.documents?.some((d) => d.status === 'ditolak')
                        }
                        className="bg-emerald-600 hover:bg-emerald-700 text-white text-xs"
                      >
                        <CheckCircle2 className="mr-1.5 h-3.5 w-3.5" />
                        {verifyAppMutation.isPending
                          ? 'Memproses...'
                          : 'Setujui & Verifikasi Pendaftaran'}
                      </Button>
                    )}
                  </div>
                </div>
              </div>
            </>
          )}
        </div>
      </div>
    </div>

    {preview && (
      <div
        className="fixed inset-0 z-[60] flex items-center justify-center bg-black/80 p-4"
        onClick={closePreview}
      >
        <div
          className="relative flex h-[92vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl"
          onClick={(e) => e.stopPropagation()}
        >
          <div className="flex items-center justify-between border-b border-gray-100 bg-gray-50/80 px-4 py-3">
            <div className="flex min-w-0 items-center gap-2 text-sm font-semibold text-gray-800">
              <FileText className="h-4 w-4 shrink-0 text-emerald-600" />
              <span className="truncate">{preview.name}</span>
            </div>
            <div className="flex items-center gap-2">
              <a
                href={preview.url}
                download={preview.name}
                className="flex items-center gap-1 rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 hover:text-emerald-600"
              >
                <Download className="h-3.5 w-3.5" /> Unduh
              </a>
              <button
                type="button"
                onClick={closePreview}
                className="rounded-lg p-1.5 text-gray-500 hover:bg-gray-100 hover:text-gray-800"
              >
                <X className="h-4 w-4" />
              </button>
            </div>
          </div>
          <div className="flex-1 overflow-auto bg-gray-100">
            {preview.mime.startsWith('image/') ? (
              <div className="flex min-h-full items-center justify-center p-4">
                {/* eslint-disable-next-line @next/next/no-img-element */}
                <img
                  src={preview.url}
                  alt={preview.name}
                  className="max-h-full max-w-full object-contain"
                />
              </div>
            ) : preview.mime === 'application/pdf' ? (
              <iframe src={preview.url} title={preview.name} className="h-full w-full" />
            ) : (
              <div className="flex h-full flex-col items-center justify-center gap-3 p-8 text-center text-sm text-gray-500">
                <FileText className="h-10 w-10 text-gray-300" />
                <p>
                  Pratinjau tidak tersedia untuk tipe berkas ini (
                  {preview.mime || 'tidak diketahui'}).
                </p>
                <a
                  href={preview.url}
                  download={preview.name}
                  className="flex items-center gap-1 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 hover:text-emerald-600"
                >
                  <Download className="h-3.5 w-3.5" /> Unduh berkas
                </a>
              </div>
            )}
          </div>
        </div>
      </div>
    )}
    </>
  );
}
