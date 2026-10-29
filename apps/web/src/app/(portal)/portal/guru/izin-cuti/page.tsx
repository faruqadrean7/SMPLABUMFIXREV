'use client';

import React, { useState } from 'react';
import Link from 'next/link';
import {
  useTeacherLeaveRequests,
  guruApi,
  TeacherLeaveRequest,
} from '@/features/portal/guru';
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
  AlertCircle,
  FileText,
  X,
} from 'lucide-react';

export default function TeacherLeavePage() {
  const { data: initialLeaves = [], isLoading } = useTeacherLeaveRequests();
  const [leaves, setLeaves] = useState<TeacherLeaveRequest[]>([]);
  const [isModalOpen, setIsModalOpen] = useState(false);

  // Form State
  const [leaveType, setLeaveType] = useState<'sick' | 'annual' | 'family' | 'duty'>('sick');
  const [startDate, setStartDate] = useState('');
  const [endDate, setEndDate] = useState('');
  const [reason, setReason] = useState('');
  const [attachmentUrl, setAttachmentUrl] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);

  React.useEffect(() => {
    if (initialLeaves.length > 0) {
      setLeaves(initialLeaves);
    }
  }, [initialLeaves]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!startDate || !endDate || !reason.trim()) return;

    setIsSubmitting(true);
    try {
      await guruApi.createLeaveRequest({
        leaveType,
        startDate,
        endDate,
        reason,
        attachmentUrl,
      });

      const newLeave: TeacherLeaveRequest = {
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
      setTimeout(() => setSuccessMessage(null), 4000);
    } catch {
      // Fallback
    } finally {
      setIsSubmitting(false);
    }
  };

  const getStatusBadge = (status: TeacherLeaveRequest['status']) => {
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

  const getLeaveTypeLabel = (type: TeacherLeaveRequest['leaveType']) => {
    switch (type) {
      case 'sick': return 'Sakit';
      case 'duty': return 'Tugas / Dinas Luar';
      case 'annual': return 'Cuti Tahunan';
      case 'family': return 'Keperluan Keluarga';
      default: return type;
    }
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-6 rounded-2xl border border-slate-200 shadow-xs">
        <div>
          <div className="flex items-center gap-2 mb-2">
            <Link
              href="/portal/guru"
              className="inline-flex items-center gap-1.5 text-xs text-slate-500 hover:text-slate-900 font-medium transition-colors"
            >
              <ArrowLeft className="h-4 w-4" />
              <span>Kembali ke Beranda</span>
            </Link>
          </div>
          <h1 className="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2">
            <CalendarDays className="h-6 w-6 text-brand-blue" />
            <span>Pengajuan Izin & Cuti Guru</span>
          </h1>
          <p className="text-xs sm:text-sm text-slate-500 mt-1">
            Ajukan permohonan izin tidak masuk mengajar, dinas luar, atau cuti resmi secara mandiri.
          </p>
        </div>

        <Button onClick={() => setIsModalOpen(true)} size="sm" className="gap-1.5 font-bold">
          <Plus className="h-4 w-4" />
          <span>Ajukan Izin Baru</span>
        </Button>
      </div>

      {successMessage && (
        <div className="p-4 bg-emerald-50 border border-emerald-200 rounded-xl text-status-success text-xs sm:text-sm font-bold flex items-center gap-2 animate-in fade-in duration-200">
          <CheckCircle2 className="h-5 w-5 shrink-0" />
          <span>{successMessage}</span>
        </div>
      )}

      {/* History */}
      {isLoading ? (
        <div className="min-h-[250px] flex items-center justify-center bg-white rounded-2xl border border-slate-200">
          <LoadingSpinner label="Memuat riwayat permohonan izin..." size="lg" />
        </div>
      ) : leaves.length === 0 ? (
        <EmptyState
          title="Belum Ada Riwayat Izin"
          description="Anda belum pernah mengajukan permohonan izin atau cuti pada periode ini."
          icon={<CalendarDays className="h-8 w-8 text-slate-400" />}
          actionLabel="Ajukan Izin Baru"
          onAction={() => setIsModalOpen(true)}
        />
      ) : (
        <div className="space-y-4">
          {leaves.map((lv) => (
            <Card key={lv.id} className="border-slate-200">
              <CardHeader className="pb-2">
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                  <div className="flex items-center gap-2">
                    <Badge variant="outline" className="font-bold text-xs bg-blue-50 text-brand-blue border-blue-200">
                      {getLeaveTypeLabel(lv.leaveType)}
                    </Badge>
                    <span className="font-bold text-xs text-slate-700">
                      {lv.startDate} s/d {lv.endDate} ({lv.daysCount} hari)
                    </span>
                  </div>
                  <div>{getStatusBadge(lv.status)}</div>
                </div>
                <CardTitle className="text-sm font-bold text-slate-900 mt-2">
                  Alasan: {lv.reason}
                </CardTitle>
              </CardHeader>
              {lv.reviewNote && (
                <CardContent className="pt-2 text-xs border-t border-slate-100 mt-2">
                  <p className="text-slate-600 bg-slate-50 p-2.5 rounded-lg border border-slate-100">
                    <strong>Catatan Peninjau:</strong> {lv.reviewNote}
                  </p>
                </CardContent>
              )}
            </Card>
          ))}
        </div>
      )}

      {/* Modal Ajukan Izin */}
      {isModalOpen && (
        <div className="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl max-w-lg w-full p-6 shadow-xl space-y-4 animate-in fade-in zoom-in-95 duration-200">
            <div className="flex items-center justify-between pb-3 border-b border-slate-100">
              <h3 className="font-bold text-base text-slate-900 flex items-center gap-2">
                <CalendarDays className="h-5 w-5 text-brand-blue" />
                <span>Form Pengajuan Izin / Cuti</span>
              </h3>
              <button
                onClick={() => setIsModalOpen(false)}
                className="text-slate-400 hover:text-slate-600 p-1 rounded-lg"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            <form onSubmit={handleSubmit} className="space-y-4">
              <div>
                <label className="block text-xs font-bold text-slate-700 mb-1">Jenis Izin</label>
                <select
                  value={leaveType}
                  onChange={(e) => setLeaveType(e.target.value as any)}
                  className="w-full h-9 px-2.5 rounded-lg border border-slate-300 text-xs bg-white"
                >
                  <option value="sick">Sakit (Lampirkan Surat Dokter)</option>
                  <option value="duty">Tugas / Dinas Luar Sekolah</option>
                  <option value="annual">Cuti Tahunan</option>
                  <option value="family">Keperluan Keluarga Mendesak</option>
                </select>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-bold text-slate-700 mb-1">Tanggal Mulai</label>
                  <Input
                    type="date"
                    value={startDate}
                    onChange={(e) => setStartDate(e.target.value)}
                    className="h-9 text-xs"
                    required
                  />
                </div>
                <div>
                  <label className="block text-xs font-bold text-slate-700 mb-1">Tanggal Selesai</label>
                  <Input
                    type="date"
                    value={endDate}
                    onChange={(e) => setEndDate(e.target.value)}
                    className="h-9 text-xs"
                    required
                  />
                </div>
              </div>

              <div>
                <label className="block text-xs font-bold text-slate-700 mb-1">Alasan Pengajuan</label>
                <Textarea
                  placeholder="Jelaskan keperluan izin atau rincian tugas..."
                  value={reason}
                  onChange={(e) => setReason(e.target.value)}
                  className="text-xs h-20"
                  required
                />
              </div>

              <div>
                <label className="block text-xs font-bold text-slate-700 mb-1">Link Lampiran Dokumen (Opsional)</label>
                <Input
                  type="text"
                  placeholder="Link surat keterangan dokter / surat tugas..."
                  value={attachmentUrl}
                  onChange={(e) => setAttachmentUrl(e.target.value)}
                  className="h-9 text-xs"
                />
              </div>

              <div className="flex justify-end gap-2 pt-3 border-t border-slate-100">
                <Button type="button" variant="outline" size="sm" onClick={() => setIsModalOpen(false)}>
                  Batal
                </Button>
                <Button type="submit" size="sm" isLoading={isSubmitting} className="font-bold">
                  Kirim Pengajuan
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
