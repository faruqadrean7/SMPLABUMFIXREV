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
import { hasPermission, isReadOnlyRole } from '@/lib/rbac/evaluator';
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
  Shield,
} from 'lucide-react';

export default function ExecutiveApprovalsPage() {
  const { user } = useAuth();
  const isReadOnly = isReadOnlyRole(user?.roles);
  const canManageApprovals = hasPermission(user, 'executive.approval.manage') && !isReadOnly;

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
      setFeedback('Aksi ditolak: Peran akun Anda bersifat baca-saja tanpa hak pengesahan.');
      return;
    }
    try {
      const res = await reviewMutation.mutateAsync({
        id: item.id,
        payload: { action: 'approve', notes: 'Disetujui oleh Kepala Sekolah / Pimpinan' },
      });
      setApprovals((prev) =>
        prev.map((a) =>
          a.id === item.id
            ? {
                ...a,
                status: 'approved',
                reviewedAt: res.reviewedAt,
                reviewedBy: 'Pimpinan Sekolah',
                reviewNotes: 'Disetujui secara resmi',
              }
            : a
        )
      );
      setFeedback(`Pengajuan ${item.code} berhasil disetujui.`);
      setTimeout(() => setFeedback(null), 4000);
    } catch {
      // Fallback
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
      setRejectionError('Aksi ditolak: Peran akun Anda bersifat baca-saja tanpa hak penolakan.');
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
                reviewedBy: 'Pimpinan Sekolah',
                reviewNotes: `Ditolak: ${rejectionReason}`,
              }
            : a
        )
      );
      setFeedback(`Pengajuan ${rejectingItem.code} telah ditolak dengan alasan tersimpan.`);
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
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-6 rounded-2xl border border-slate-200 shadow-xs">
        <div>
          <div className="flex items-center gap-2 mb-2">
            <Link
              href="/admin/executive"
              className="inline-flex items-center gap-1.5 text-xs text-slate-500 hover:text-slate-900 font-medium transition-colors"
            >
              <ArrowLeft className="h-4 w-4" />
              <span>Kembali ke Dashboard Eksekutif</span>
            </Link>
          </div>
          <h1 className="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2">
            <CheckSquare className="h-6 w-6 text-brand-blue" />
            <span>Antrean Persetujuan Pimpinan</span>
          </h1>
          <p className="text-xs sm:text-sm text-slate-500 mt-1">
            Validasi dan otorisasi penguncian e-rapor, permohonan izin cuti, serta kebutuhan operasional sekolah.
          </p>
        </div>

        <div className="flex items-center gap-2">
          <Badge variant="outline" className="text-xs font-bold px-3 py-1 bg-amber-50 text-amber-800 border-amber-200">
            {approvals.filter((a) => a.status === 'pending').length} Menunggu Persetujuan
          </Badge>
        </div>
      </div>

      {feedback && (
        <div className="p-4 bg-emerald-50 border border-emerald-200 rounded-xl text-status-success text-xs sm:text-sm font-bold flex items-center gap-2 animate-in fade-in duration-200">
          <CheckCircle2 className="h-5 w-5 shrink-0" />
          <span>{feedback}</span>
        </div>
      )}

      {/* Banner Khusus Mode Baca-Saja (Pengawas / Komite) */}
      {isReadOnly && (
        <div
          className="p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 flex items-start gap-3 shadow-xs"
          data-testid="read-only-approval-notice"
        >
          <Shield className="h-5 w-5 shrink-0 text-amber-700 mt-0.5" />
          <div className="text-xs">
            <span className="font-bold block text-sm mb-0.5">
              Mode Akses Pengawas & Komite (Pantauan Baca-Saja)
            </span>
            Anda memiliki izin memantau status antrean persetujuan. Tombol verifikasi, pengesahan, dan penolakan
            dinonaktifkan secara otomatis sesuai batasan kewenangan supervisi sekolah.
          </div>
        </div>
      )}

      {/* Filter Tabs */}
      <div className="flex flex-wrap items-center gap-2">
        {[
          { label: 'Semua Pengajuan', value: 'all' },
          { label: 'Menunggu Persetujuan', value: 'pending' },
          { label: 'Disetujui', value: 'approved' },
          { label: 'Ditolak', value: 'rejected' },
        ].map((tab) => (
          <button
            key={tab.value}
            type="button"
            onClick={() => setFilterStatus(tab.value)}
            className={`px-3 py-1.5 rounded-lg text-xs font-bold transition-all ${
              filterStatus === tab.value
                ? 'bg-slate-900 text-white shadow-xs'
                : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50'
            }`}
          >
            {tab.label}
          </button>
        ))}
      </div>

      {/* Approvals List */}
      {isLoading ? (
        <div className="min-h-[250px] flex items-center justify-center bg-white rounded-2xl border border-slate-200">
          <LoadingSpinner label="Memuat antrean persetujuan pimpinan..." size="lg" />
        </div>
      ) : filteredApprovals.length === 0 ? (
        <EmptyState
          title="Tidak Ada Antrean Persetujuan"
          description={
            filterStatus === 'all'
              ? 'Saat ini tidak ada berkas pengajuan yang memerlukan tindakan persetujuan pimpinan.'
              : `Tidak ada berkas dengan status "${filterStatus}".`
          }
          icon={<CheckSquare className="h-8 w-8 text-slate-400" />}
        />
      ) : (
        <div className="space-y-4">
          {filteredApprovals.map((item) => (
            <Card key={item.id} className="border-slate-200 hover:shadow-xs transition-shadow">
              <CardHeader className="pb-3">
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                  <div className="flex items-center gap-2 flex-wrap">
                    <span className="font-mono text-[11px] font-bold text-slate-500 bg-slate-100 px-2 py-0.5 rounded">
                      {item.code}
                    </span>
                    {getCategoryBadge(item.category)}
                    <span className="text-xs text-slate-400">• Diajukan {item.submittedAt}</span>
                  </div>
                  {getStatusBadge(item.status)}
                </div>

                <CardTitle className="text-base font-bold text-slate-900 mt-2">
                  {item.title}
                </CardTitle>

                <CardDescription className="text-xs text-slate-600 mt-0.5">
                  Pemohon: <strong className="text-slate-800">{item.requesterName}</strong> ({item.requesterRole})
                </CardDescription>
              </CardHeader>

              <CardContent className="pt-0 space-y-3">
                <p className="text-xs text-slate-600 bg-slate-50 p-3 rounded-xl border border-slate-100 leading-relaxed">
                  {item.details}
                </p>

                {/* Riwayat Review */}
                {item.reviewedAt && (
                  <div
                    className={`p-3 rounded-xl text-xs space-y-0.5 ${
                      item.status === 'approved'
                        ? 'bg-emerald-50/70 border border-emerald-100 text-emerald-900'
                        : 'bg-red-50/70 border border-red-100 text-red-900'
                    }`}
                  >
                    <div className="flex items-center justify-between">
                      <span className="font-bold">
                        {item.status === 'approved' ? 'Persetujuan Resmi' : 'Catatan Penolakan'}:
                      </span>
                      <span className="text-[11px] text-slate-500">{item.reviewedAt}</span>
                    </div>
                    <p className="mt-1">{item.reviewNotes}</p>
                    {item.reviewedBy && (
                      <span className="text-[10px] text-slate-500 block pt-1">
                        Oleh: {item.reviewedBy}
                      </span>
                    )}
                  </div>
                )}

                {/* Tombol Aksi Persetujuan (Hanya jika status pending & berwenang) */}
                {item.status === 'pending' && (
                  <div className="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                    {canManageApprovals ? (
                      <>
                        <Button
                          size="sm"
                          variant="outline"
                          onClick={() => handleOpenRejectModal(item)}
                          disabled={reviewMutation.isPending}
                          className="text-status-danger border-red-200 hover:bg-red-50 text-xs font-bold gap-1"
                        >
                          <XCircle className="h-3.5 w-3.5" />
                          <span>Tolak Pengajuan</span>
                        </Button>

                        <Button
                          size="sm"
                          onClick={() => handleApprove(item)}
                          disabled={reviewMutation.isPending}
                          className="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold gap-1 shadow-xs"
                        >
                          <CheckCircle2 className="h-3.5 w-3.5" />
                          <span>Setujui Pengajuan</span>
                        </Button>
                      </>
                    ) : (
                      <span className="text-xs text-slate-400 italic font-medium">
                        Menunggu Keputusan Pimpinan (Hanya Pantauan)
                      </span>
                    )}
                  </div>
                )}
              </CardContent>
            </Card>
          ))}
        </div>
      )}

      {/* Modal Input Alasan Penolakan (Wajib Diisi) */}
      {rejectingItem && (
        <div className="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
          <Card className="w-full max-w-lg bg-white shadow-xl animate-in zoom-in-95 duration-150">
            <CardHeader className="flex flex-row items-center justify-between pb-3 border-b border-slate-100">
              <div>
                <CardTitle className="text-base font-bold text-status-danger flex items-center gap-2">
                  <AlertCircle className="h-5 w-5" />
                  <span>Konfirmasi Penolakan Pengajuan</span>
                </CardTitle>
                <CardDescription className="text-xs text-slate-500 mt-1">
                  Nomor: {rejectingItem.code} • {rejectingItem.title}
                </CardDescription>
              </div>
              <Button
                variant="ghost"
                size="sm"
                onClick={() => setRejectingItem(null)}
                className="h-8 w-8 p-0 text-slate-400 hover:text-slate-600"
              >
                <X className="h-4 w-4" />
              </Button>
            </CardHeader>

            <form onSubmit={handleConfirmReject}>
              <CardContent className="space-y-4 pt-4">
                <div className="p-3 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-800">
                  <strong>Penting:</strong> Sesuai regulasi transparansi sekolah, penolakan pengajuan
                  wajib menyertakan alasan tertulis agar dapat dievaluasi oleh pemohon.
                </div>

                {rejectionError && (
                  <div className="p-3 bg-red-50 border border-red-200 rounded-lg text-xs font-bold text-status-danger">
                    {rejectionError}
                  </div>
                )}

                <div>
                  <label className="text-xs font-bold text-slate-700 block mb-1">
                    Alasan Penolakan <span className="text-red-500">*</span>
                  </label>
                  <Textarea
                    value={rejectionReason}
                    onChange={(e) => {
                      setRejectionReason(e.target.value);
                      if (rejectionError) setRejectionError(null);
                    }}
                    placeholder="Tuliskan alasan penolakan secara terperinci (misal: nilai belum lengkap, tanggal cuti berbenturan dengan agenda wajib)..."
                    rows={4}
                    required
                    className="text-xs"
                  />
                </div>
              </CardContent>

              <div className="p-4 bg-slate-50 border-t border-slate-100 flex items-center justify-end gap-2 rounded-b-xl">
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={() => setRejectingItem(null)}
                  disabled={reviewMutation.isPending}
                >
                  Batal
                </Button>
                <Button
                  type="submit"
                  size="sm"
                  disabled={reviewMutation.isPending}
                  className="bg-red-600 hover:bg-red-700 text-white font-bold gap-1.5"
                >
                  <Send className="h-3.5 w-3.5" />
                  <span>{reviewMutation.isPending ? 'Memproses...' : 'Kirim Penolakan Resmi'}</span>
                </Button>
              </div>
            </form>
          </Card>
        </div>
      )}
    </div>
  );
}
