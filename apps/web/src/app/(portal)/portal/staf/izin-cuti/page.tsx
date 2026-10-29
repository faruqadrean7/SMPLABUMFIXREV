'use client';

import React, { useState } from 'react';
import Link from 'next/link';
import {
  useSharedLeaveRequests,
  sharedPortalApi,
  SharedLeaveRequest,
  LeaveType,
} from '@/features/portal/shared';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { LoadingSpinner } from '@/components/shared/loading-spinner';
import { EmptyState } from '@/components/shared/empty-state';
import {
  CalendarDays,
  ArrowLeft,
  Plus,
  Clock,
  CheckCircle2,
  XCircle,
  FileText,
  X,
  Send,
  AlertTriangle,
} from 'lucide-react';

export default function StaffLeavePage() {
  const { data: initialLeaves = [], isLoading } = useSharedLeaveRequests('staf');
  const [leaves, setLeaves] = useState<SharedLeaveRequest[]>([]);
  const [isModalOpen, setIsModalOpen] = useState(false);

  // Form State
  const [leaveType, setLeaveType] = useState<LeaveType>('annual');
  const [startDate, setStartDate] = useState('');
  const [endDate, setEndDate] = useState('');
  const [reason, setReason] = useState('');
  const [attachmentUrl, setAttachmentUrl] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);
  const [modalError, setModalError] = useState<string | null>(null);

  React.useEffect(() => {
    if (initialLeaves.length > 0) {
      setLeaves(initialLeaves);
    }
  }, [initialLeaves]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!startDate || !endDate || !reason.trim()) return;

    setIsSubmitting(true);
    setModalError(null);
    try {
      await sharedPortalApi.createLeaveRequest(
        {
          leaveType,
          startDate,
          endDate,
          reason,
          attachmentUrl,
        },
        'staf'
      );

      const newLeave: SharedLeaveRequest = {
        id: `lv-${Date.now()}`,
        leaveType,
        startDate,
        endDate,
        daysCount: 1,
        reason,
        attachmentUrl,
        status: 'pending',
        createdAt: new Date().toISOString(),
      };
      setLeaves((prev) => [newLeave, ...prev]);
      setSuccessMessage('Permohonan izin/cuti berhasil diajukan dan sedang ditinjau.');
      setIsModalOpen(false);
      setReason('');
      setStartDate('');
      setEndDate('');
      setAttachmentUrl('');
      setModalError(null);
      setTimeout(() => setSuccessMessage(null), 4000);
    } catch (err: any) {
      setModalError(err?.message || 'Gagal mengajukan permohonan izin/cuti. Silakan lengkapi formulir.');
    } finally {
      setIsSubmitting(false);
    }
  };

  const getStatusBadge = (status: SharedLeaveRequest['status']) => {
    switch (status) {
      case 'approved':
        return (
          <Badge className="bg-emerald-50 text-status-success border-emerald-200 gap-1 text-[11px]">
            <CheckCircle2 className="h-3 w-3" />
            <span>Disetujui</span>
          </Badge>
        );
      case 'rejected':
        return (
          <Badge className="bg-red-50 text-status-danger border-red-200 gap-1 text-[11px]">
            <XCircle className="h-3 w-3" />
            <span>Ditolak</span>
          </Badge>
        );
      case 'pending':
      default:
        return (
          <Badge className="bg-amber-50 text-amber-800 border-amber-200 gap-1 text-[11px]">
            <Clock className="h-3 w-3" />
            <span>Menunggu Peninjauan</span>
          </Badge>
        );
    }
  };

  const getLeaveTypeLabel = (type: SharedLeaveRequest['leaveType']) => {
    switch (type) {
      case 'sick':
        return 'Sakit';
      case 'duty':
        return 'Tugas / Dinas Luar';
      case 'annual':
        return 'Cuti Tahunan';
      case 'family':
        return 'Keperluan Keluarga';
      default:
        return type;
    }
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-6 rounded-2xl border border-slate-200 shadow-xs">
        <div>
          <div className="flex items-center gap-2 mb-2">
            <Link
              href="/portal/staf"
              className="inline-flex items-center gap-1.5 text-xs text-slate-500 hover:text-slate-900 font-medium transition-colors"
            >
              <ArrowLeft className="h-4 w-4" />
              <span>Kembali ke Beranda Staf</span>
            </Link>
          </div>
          <h1 className="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2">
            <CalendarDays className="h-6 w-6 text-brand-blue" />
            <span>Pengajuan Izin & Cuti Staf</span>
          </h1>
          <p className="text-xs sm:text-sm text-slate-500 mt-1">
            Ajukan permohonan izin sakit, cuti tahunan, atau tugas kedinasan luar kantor.
          </p>
        </div>

        <Button
          onClick={() => setIsModalOpen(true)}
          className="gap-2 bg-brand-blue hover:bg-blue-700 text-white font-bold"
        >
          <Plus className="h-4 w-4" />
          <span>Ajukan Izin / Cuti Baru</span>
        </Button>
      </div>

      {successMessage && (
        <div className="p-4 bg-emerald-50 border border-emerald-200 rounded-xl text-status-success text-xs sm:text-sm font-bold flex items-center gap-2 animate-in fade-in duration-200">
          <CheckCircle2 className="h-5 w-5 shrink-0" />
          <span>{successMessage}</span>
        </div>
      )}

      {/* Leave List */}
      {isLoading ? (
        <div className="min-h-[250px] flex items-center justify-center bg-white rounded-2xl border border-slate-200">
          <LoadingSpinner label="Memuat riwayat permohonan izin staf..." size="lg" />
        </div>
      ) : leaves.length === 0 ? (
        <EmptyState
          title="Belum Ada Pengajuan Izin"
          description="Anda belum memiliki riwayat pengajuan izin kerja atau cuti."
          actionLabel="Ajukan Izin Sekarang"
          onAction={() => setIsModalOpen(true)}
        />
      ) : (
        <div className="space-y-4">
          <h2 className="text-sm font-bold text-slate-800">Riwayat Permohonan Izin & Cuti</h2>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {leaves.map((leave) => (
              <Card key={leave.id} className="border-slate-200 hover:shadow-xs transition-shadow">
                <CardHeader className="flex flex-row items-start justify-between pb-2">
                  <div>
                    <div className="flex items-center gap-2 mb-1">
                      <span className="text-xs font-bold px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-700">
                        {getLeaveTypeLabel(leave.leaveType)}
                      </span>
                      <span className="text-xs text-slate-400">
                        {leave.daysCount} Hari
                      </span>
                    </div>
                    <CardTitle className="text-base font-bold text-slate-900">
                      {leave.startDate} s/d {leave.endDate}
                    </CardTitle>
                  </div>
                  {getStatusBadge(leave.status)}
                </CardHeader>

                <CardContent className="space-y-3 pt-1">
                  <p className="text-xs text-slate-600 leading-relaxed">
                    <strong className="text-slate-800">Alasan:</strong> {leave.reason}
                  </p>

                  {leave.attachmentUrl && (
                    <div className="flex items-center gap-1.5 text-xs text-brand-blue font-semibold">
                      <FileText className="h-3.5 w-3.5" />
                      <span>Lampiran Surat Terlampir</span>
                    </div>
                  )}

                  {leave.reviewNote && (
                    <div className="p-3 bg-slate-50 border border-slate-200 rounded-lg text-xs text-slate-600">
                      <strong className="text-slate-800 block mb-0.5">Catatan Pimpinan / Kepala TU:</strong>
                      {leave.reviewNote}
                    </div>
                  )}
                </CardContent>
              </Card>
            ))}
          </div>
        </div>
      )}

      {/* Modal Ajukan Izin Baru */}
      {isModalOpen && (
        <div className="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
          <Card className="w-full max-w-lg bg-white shadow-xl animate-in zoom-in-95 duration-150">
            <CardHeader className="flex flex-row items-center justify-between pb-3 border-b border-slate-100">
              <div>
                <CardTitle className="text-lg font-bold text-slate-900">
                  Formulir Permohonan Izin / Cuti
                </CardTitle>
                <CardDescription className="text-xs text-slate-500">
                  Kirimkan permohonan ke Kepala Tata Usaha dan Kepala Sekolah
                </CardDescription>
              </div>
              <Button
                variant="ghost"
                size="sm"
                onClick={() => setIsModalOpen(false)}
                className="h-8 w-8 p-0 text-slate-400 hover:text-slate-600"
              >
                <X className="h-4 w-4" />
              </Button>
            </CardHeader>

            <form onSubmit={handleSubmit}>
              <CardContent className="space-y-4 pt-4">
                {modalError && (
                  <div className="p-3 bg-red-50 border border-red-200 text-status-danger text-xs rounded-lg flex items-center gap-2">
                    <AlertTriangle className="h-4 w-4 shrink-0" />
                    <span>{modalError}</span>
                  </div>
                )}
                <div>
                  <label className="text-xs font-bold text-slate-700 block mb-1">
                    Jenis Permohonan
                  </label>
                  <select
                    value={leaveType}
                    onChange={(e) => setLeaveType(e.target.value as LeaveType)}
                    className="w-full text-xs font-medium rounded-lg border border-slate-200 px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-brand-blue"
                  >
                    <option value="annual">Cuti Tahunan</option>
                    <option value="sick">Sakit (dengan Surat Keterangan)</option>
                    <option value="duty">Tugas / Dinas Luar Sekolah</option>
                    <option value="family">Izin Keperluan Keluarga Mendesak</option>
                  </select>
                </div>

                <div className="grid grid-cols-2 gap-3">
                  <div>
                    <label className="text-xs font-bold text-slate-700 block mb-1">
                      Tanggal Mulai
                    </label>
                    <Input
                      type="date"
                      value={startDate}
                      onChange={(e) => setStartDate(e.target.value)}
                      required
                      className="text-xs"
                    />
                  </div>
                  <div>
                    <label className="text-xs font-bold text-slate-700 block mb-1">
                      Tanggal Selesai
                    </label>
                    <Input
                      type="date"
                      value={endDate}
                      onChange={(e) => setEndDate(e.target.value)}
                      required
                      className="text-xs"
                    />
                  </div>
                </div>

                <div>
                  <label className="text-xs font-bold text-slate-700 block mb-1">
                    Alasan / Keterangan Lengkap
                  </label>
                  <Textarea
                    value={reason}
                    onChange={(e) => setReason(e.target.value)}
                    placeholder="Tuliskan keterangan detail alasan pengajuan izin kerja..."
                    rows={3}
                    required
                    className="text-xs"
                  />
                </div>

                <div>
                  <label className="text-xs font-bold text-slate-700 block mb-1">
                    Lampiran Dokumen (Opsional)
                  </label>
                  <Input
                    type="text"
                    value={attachmentUrl}
                    onChange={(e) => setAttachmentUrl(e.target.value)}
                    placeholder="URL atau nama file surat dokter/surat tugas..."
                    className="text-xs"
                  />
                </div>
              </CardContent>

              <div className="p-4 bg-slate-50 border-t border-slate-100 flex items-center justify-end gap-2 rounded-b-xl">
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={() => setIsModalOpen(false)}
                  disabled={isSubmitting}
                >
                  Batal
                </Button>
                <Button
                  type="submit"
                  size="sm"
                  disabled={isSubmitting}
                  className="bg-brand-blue hover:bg-blue-700 text-white font-bold gap-1.5"
                >
                  <Send className="h-3.5 w-3.5" />
                  <span>{isSubmitting ? 'Mengirimkan...' : 'Kirim Pengajuan'}</span>
                </Button>
              </div>
            </form>
          </Card>
        </div>
      )}
    </div>
  );
}
