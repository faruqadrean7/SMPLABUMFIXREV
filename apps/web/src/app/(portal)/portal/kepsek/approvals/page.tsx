'use client';

import React, { useState } from 'react';
import Link from 'next/link';
import {
  useApprovals,
  useReviewApproval,
  ApprovalItem,
  ApprovalStatus,
  ApprovalCategory,
} from '@/features/executive';
import { useAuth } from '@/providers/auth-provider';
import { hasPermission } from '@/lib/rbac/evaluator';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { LoadingSpinner } from '@/components/shared/loading-spinner';
import { EmptyState } from '@/components/shared/empty-state';
import {
  CheckSquare,
  ArrowLeft,
  CheckCircle2,
  XCircle,
  Clock,
  AlertCircle,
  FileCheck2,
  CalendarDays,
  Wrench,
  BookOpen,
  X,
  Send,
  Building,
} from 'lucide-react';

export default function KepsekApprovalsPage() {
  const { user } = useAuth();
  const canManageApprovals = hasPermission(user, 'executive.approval.manage');

  const { data: initialApprovals = [], isLoading } = useApprovals();
  const reviewMutation = useReviewApproval();

  const [approvals, setApprovals] = useState<ApprovalItem[]>([]);
  const [filterStatus, setFilterStatus] = useState<string>('all');
  const [feedback, setFeedback] = useState<string | null>(null);

  // Rejection modal state
  const [rejectingItem, setRejectingItem] = useState<ApprovalItem | null>(null);
  const [rejectionReason, setRejectionReason] = useState('');
  const [rejectionError, setRejectionError] = useState<string | null>(null);

  React.useEffect(() => {
    if (initialApprovals.length > 0) {
      setApprovals(initialApprovals);
    }
  }, [initialApprovals]);

  const handleApprove = async (item: ApprovalItem) => {
    if (!canManageApprovals) {
      setFeedback('Aksi ditolak: Akun Anda tidak memiliki hak otorisasi pengesahan.');
      return;
    }
    try {
      const res = await reviewMutation.mutateAsync({
        id: item.id,
        payload: { action: 'approve', notes: 'Disetujui secara resmi oleh Kepala Sekolah / Pimpinan' },
      });
      setApprovals((prev) =>
        prev.map((a) =>
          a.id === item.id
            ? {
                ...a,
                status: 'approved',
                reviewedAt: res.reviewedAt,
                reviewedBy: user?.profile?.name || 'Pimpinan Sekolah',
                reviewNotes: 'Disetujui secara resmi',
              }
            : a
        )
      );
      setFeedback(`Pengajuan ${item.code} (${item.title}) berhasil disetujui.`);
      setTimeout(() => setFeedback(null), 4000);
    } catch {
      setFeedback('Terjadi kendala saat menyetujui berkas.');
    }
  };

  const handleOpenRejectModal = (item: ApprovalItem) => {
    if (!canManageApprovals) return;
    setRejectingItem(item);
    setRejectionReason('');
    setRejectionError(null);
  };

  const handleConfirmReject = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!rejectingItem) return;

    if (!canManageApprovals) {
      setRejectionError('Aksi ditolak: Akun Anda tidak memiliki hak penolakan.');
      return;
    }

    if (!rejectionReason.trim()) {
      setRejectionError('Alasan penolakan wajib diisi secara jelas.');
      return;
    }

    try {
      const res = await reviewMutation.mutateAsync({
        id: rejectingItem.id,
        payload: { action: 'reject', rejectionReason },
      });
      setApprovals((prev) =>
        prev.map((a) =>
          a.id === rejectingItem.id
            ? {
                ...a,
                status: 'rejected',
                reviewedAt: res.reviewedAt,
                reviewedBy: user?.profile?.name || 'Pimpinan Sekolah',
                reviewNotes: `Ditolak: ${rejectionReason}`,
              }
            : a
        )
      );
      setFeedback(`Pengajuan ${rejectingItem.code} telah ditolak dengan catatan tersimpan.`);
      setRejectingItem(null);
      setRejectionReason('');
      setTimeout(() => setFeedback(null), 4000);
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Gagal menolak pengajuan.';
      setRejectionError(message);
    }
  };

  const filteredApprovals = approvals.filter((a) => {
    if (filterStatus === 'all') return true;
    return a.status === filterStatus;
  });

  const getCategoryBadge = (category: ApprovalCategory) => {
    switch (category) {
      case 'report_card':
        return (
          <Badge className="bg-blue-50 text-brand-blue border-blue-200 gap-1 text-[11px] font-bold">
            <FileCheck2 className="h-3 w-3" />
            <span>Kunci E-Rapor</span>
          </Badge>
        );
      case 'leave_request':
        return (
          <Badge className="bg-amber-50 text-amber-800 border-amber-200 gap-1 text-[11px] font-bold">
            <CalendarDays className="h-3 w-3" />
            <span>Izin & Cuti</span>
          </Badge>
        );
      case 'facility_purchase':
        return (
          <Badge className="bg-purple-50 text-purple-700 border-purple-200 gap-1 text-[11px] font-bold">
            <Wrench className="h-3 w-3" />
            <span>Sarana Prasarana</span>
          </Badge>
        );
      case 'curriculum':
      default:
        return (
          <Badge className="bg-slate-100 text-slate-700 border-slate-200 gap-1 text-[11px]">
            <BookOpen className="h-3 w-3" />
            <span>Kurikulum</span>
          </Badge>
        );
    }
  };

  const getStatusBadge = (status: ApprovalStatus) => {
    switch (status) {
      case 'approved':
        return (
          <Badge className="bg-emerald-50 text-status-success border-emerald-200 gap-1 text-[11px] font-bold">
            <CheckCircle2 className="h-3 w-3" />
            <span>Disetujui</span>
          </Badge>
        );
      case 'rejected':
        return (
          <Badge className="bg-red-50 text-status-danger border-red-200 gap-1 text-[11px] font-bold">
            <XCircle className="h-3 w-3" />
            <span>Ditolak</span>
          </Badge>
        );
      case 'pending':
      default:
        return (
          <Badge className="bg-amber-50 text-amber-800 border-amber-200 gap-1 text-[11px] font-bold">
            <Clock className="h-3 w-3" />
            <span>Menunggu Keputusan</span>
          </Badge>
        );
    }
  };

  return (
    <div className="space-y-6">
      {/* Header Halaman */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-6 rounded-2xl border border-slate-200 shadow-xs">
        <div>
          <div className="flex items-center gap-2 mb-2">
            <Link
              href="/portal/kepsek"
              className="inline-flex items-center gap-1.5 text-xs text-slate-500 hover:text-slate-900 font-medium transition-colors"
            >
              <ArrowLeft className="h-4 w-4" />
              <span>Kembali ke Dashboard Pimpinan</span>
            </Link>
          </div>
          <h1 className="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2">
            <CheckSquare className="h-6 w-6 text-brand-blue" />
            <span>Antrean Persetujuan Dokumen</span>
          </h1>
          <p className="text-xs sm:text-sm text-slate-500 mt-1">
            Pengesahan rapor akhir semester, izin cuti tenaga pendidik, dan pengadaan logistik sekolah.
          </p>
        </div>

        {/* Filter Tab Status */}
        <div className="flex items-center gap-1.5 p-1 bg-slate-100 rounded-xl border border-slate-200 text-xs font-bold">
          <button
            type="button"
            onClick={() => setFilterStatus('all')}
            className={`px-3 py-1.5 rounded-lg transition-all ${
              filterStatus === 'all'
                ? 'bg-white text-slate-900 shadow-xs font-bold'
                : 'text-slate-600 hover:text-slate-900'
            }`}
          >
            Semua ({approvals.length})
          </button>
          <button
            type="button"
            onClick={() => setFilterStatus('pending')}
            className={`px-3 py-1.5 rounded-lg transition-all ${
              filterStatus === 'pending'
                ? 'bg-amber-500 text-white shadow-xs font-bold'
                : 'text-slate-600 hover:text-slate-900'
            }`}
          >
            Menunggu ({approvals.filter((a) => a.status === 'pending').length})
          </button>
          <button
            type="button"
            onClick={() => setFilterStatus('approved')}
            className={`px-3 py-1.5 rounded-lg transition-all ${
              filterStatus === 'approved'
                ? 'bg-emerald-600 text-white shadow-xs font-bold'
                : 'text-slate-600 hover:text-slate-900'
            }`}
          >
            Disetujui ({approvals.filter((a) => a.status === 'approved').length})
          </button>
          <button
            type="button"
            onClick={() => setFilterStatus('rejected')}
            className={`px-3 py-1.5 rounded-lg transition-all ${
              filterStatus === 'rejected'
                ? 'bg-red-600 text-white shadow-xs font-bold'
                : 'text-slate-600 hover:text-slate-900'
            }`}
          >
            Ditolak ({approvals.filter((a) => a.status === 'rejected').length})
          </button>
        </div>
      </div>

      {/* Pesan Feedback */}
      {feedback && (
        <div className="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-xs font-semibold flex items-center gap-2 animate-in fade-in duration-200">
          <CheckCircle2 className="h-4 w-4 shrink-0 text-emerald-600" />
          <span>{feedback}</span>
        </div>
      )}

      {/* Konten Utama: Daftar Persetujuan */}
      {isLoading ? (
        <div className="min-h-[40vh] flex items-center justify-center">
          <LoadingSpinner label="Memuat antrean pengajuan pimpinan..." size="lg" />
        </div>
      ) : filteredApprovals.length === 0 ? (
        <EmptyState
          title="Tidak Ada Pengajuan Sesuai Filter"
          description="Saat ini tidak ditemukan permohonan persetujuan pada status yang dipilih."
        />
      ) : (
        <div className="space-y-4">
          {filteredApprovals.map((item) => (
            <Card key={item.id} className="border-slate-200 hover:border-slate-300 transition-all shadow-xs">
              <CardContent className="p-5">
                <div className="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
                  <div className="space-y-2 flex-1">
                    <div className="flex flex-wrap items-center gap-2">
                      <span className="font-mono text-xs font-bold text-slate-500 bg-slate-100 px-2 py-0.5 rounded">
                        {item.code}
                      </span>
                      {getCategoryBadge(item.category)}
                      {getStatusBadge(item.status)}
                    </div>

                    <h3 className="text-base font-bold text-slate-900">{item.title}</h3>
                    <p className="text-xs text-slate-600 leading-relaxed">{item.details}</p>

                    <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500 pt-1">
                      <span>
                        Diajukan oleh: <strong className="text-slate-700">{item.requesterName}</strong> ({item.requesterRole})
                      </span>
                      <span>•</span>
                      <span>
                        Waktu: <strong className="text-slate-700">{new Date(item.submittedAt).toLocaleDateString('id-ID', { dateStyle: 'medium' })}</strong>
                      </span>
                    </div>

                    {item.reviewNotes && (
                      <div className="mt-3 p-3 bg-slate-50 border border-slate-200 rounded-lg text-xs">
                        <span className="font-bold text-slate-700">Catatan Tinjauan: </span>
                        <span className="text-slate-600">{item.reviewNotes}</span>
                        {item.reviewedAt && (
                          <span className="text-[11px] text-slate-400 block mt-0.5">
                            Diverifikasi pada: {new Date(item.reviewedAt).toLocaleString('id-ID')}
                          </span>
                        )}
                      </div>
                    )}
                  </div>

                  {/* Tombol Aksi Persetujuan */}
                  {item.status === 'pending' && canManageApprovals && (
                    <div className="flex sm:flex-row lg:flex-col items-center gap-2 shrink-0 pt-2 lg:pt-0">
                      <Button
                        size="sm"
                        onClick={() => handleApprove(item)}
                        disabled={reviewMutation.isPending}
                        className="bg-emerald-600 hover:bg-emerald-700 text-white font-bold gap-1.5 text-xs w-full"
                      >
                        <CheckCircle2 className="h-3.5 w-3.5" />
                        <span>Setujui Berkas</span>
                      </Button>
                      <Button
                        size="sm"
                        variant="outline"
                        onClick={() => handleOpenRejectModal(item)}
                        disabled={reviewMutation.isPending}
                        className="border-red-200 text-red-600 hover:bg-red-50 font-bold gap-1.5 text-xs w-full"
                      >
                        <XCircle className="h-3.5 w-3.5" />
                        <span>Tolak Berkas</span>
                      </Button>
                    </div>
                  )}
                </div>
              </CardContent>
            </Card>
          ))}
        </div>
      )}

      {/* Modal Dialog Form Penolakan Berkas */}
      {rejectingItem && (
        <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl max-w-lg w-full p-6 shadow-xl border border-slate-200 space-y-4 animate-in fade-in zoom-in-95 duration-150">
            <div className="flex items-center justify-between pb-2 border-b border-slate-100">
              <div className="flex items-center gap-2 text-red-600">
                <AlertCircle className="h-5 w-5" />
                <h3 className="font-bold text-base text-slate-900">Konfirmasi Penolakan Pengajuan</h3>
              </div>
              <button
                type="button"
                onClick={() => setRejectingItem(null)}
                className="text-slate-400 hover:text-slate-600 rounded-lg p-1"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            <p className="text-xs text-slate-600">
              Anda akan menolak pengajuan <strong>{rejectingItem.code}</strong> — {rejectingItem.title}. Silakan tuliskan alasan penolakan secara jelas sebagai umpan balik kepada pengaju.
            </p>

            <form onSubmit={handleConfirmReject} className="space-y-4">
              <div className="space-y-1">
                <label htmlFor="rejectionReason" className="text-xs font-bold text-slate-700">
                  Alasan Penolakan Resmi <span className="text-red-500">*</span>
                </label>
                <Textarea
                  id="rejectionReason"
                  rows={4}
                  value={rejectionReason}
                  onChange={(e) => setRejectionReason(e.target.value)}
                  placeholder="Contoh: Berkas rekapitulasi belum ditandatangani oleh wali kelas bersangkutan..."
                  className="text-xs"
                  required
                />
                {rejectionError && (
                  <p className="text-xs text-red-600 font-semibold">{rejectionError}</p>
                )}
              </div>

              <div className="flex justify-end gap-2 pt-2">
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={() => setRejectingItem(null)}
                  disabled={reviewMutation.isPending}
                  className="text-xs"
                >
                  Batal
                </Button>
                <Button
                  type="submit"
                  size="sm"
                  disabled={reviewMutation.isPending}
                  className="bg-red-600 hover:bg-red-700 text-white font-bold text-xs gap-1.5"
                >
                  <Send className="h-3.5 w-3.5" />
                  <span>Kirim Penolakan</span>
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
