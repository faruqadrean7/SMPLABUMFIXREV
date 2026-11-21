'use client';

import React, { useState, useEffect } from 'react';
import {
  StaffAttendanceItem,
  StaffAttendanceStatus,
  STAFF_ATTENDANCE_STATUS_LABELS,
  STAFF_ATTENDANCE_STATUS_COLORS,
} from '@/types/academic';
import { academicApi } from '../academic-api';
import { Button } from '@/components/ui/button';
import {
  X,
  Edit3,
  Calendar,
  User,
  Clock,
  AlertCircle,
  Loader2,
  FileText,
  ShieldAlert,
} from 'lucide-react';

interface StaffAttendanceCorrectDialogProps {
  isOpen: boolean;
  onClose: () => void;
  onSuccess: () => void;
  attendance: StaffAttendanceItem | null;
}

export function StaffAttendanceCorrectDialog({
  isOpen,
  onClose,
  onSuccess,
  attendance,
}: StaffAttendanceCorrectDialogProps) {
  const [status, setStatus] = useState<StaffAttendanceStatus>('hadir');
  const [checkInTime, setCheckInTime] = useState<string>('');
  const [checkOutTime, setCheckOutTime] = useState<string>('');
  const [reason, setReason] = useState<string>('');

  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (isOpen && attendance) {
      setStatus(attendance.status);

      // Parse time if exists
      if (attendance.check_in) {
        try {
          const d = new Date(attendance.check_in);
          setCheckInTime(d.toTimeString().slice(0, 5));
        } catch {
          setCheckInTime('');
        }
      } else {
        setCheckInTime('');
      }

      if (attendance.check_out) {
        try {
          const d = new Date(attendance.check_out);
          setCheckOutTime(d.toTimeString().slice(0, 5));
        } catch {
          setCheckOutTime('');
        }
      } else {
        setCheckOutTime('');
      }

      setReason('');
      setError(null);
    }
  }, [isOpen, attendance]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!attendance) return;

    if (!reason || reason.trim().length < 3) {
      setError('Alasan perbaikan presensi wajib diisi minimal 3 karakter sebagai audit trail.');
      return;
    }

    setSubmitting(true);
    setError(null);

    const attDate = attendance.att_date;
    let formattedCheckIn: string | undefined = undefined;
    let formattedCheckOut: string | undefined = undefined;

    if ((status === 'hadir' || status === 'terlambat') && checkInTime) {
      formattedCheckIn = `${attDate} ${checkInTime.length === 5 ? checkInTime + ':00' : checkInTime}`;
    }
    if ((status === 'hadir' || status === 'terlambat') && checkOutTime) {
      formattedCheckOut = `${attDate} ${checkOutTime.length === 5 ? checkOutTime + ':00' : checkOutTime}`;
    }

    try {
      await academicApi.correctStaffAttendance(attendance.id, {
        status,
        check_in: formattedCheckIn,
        check_out: formattedCheckOut,
        reason: reason.trim(),
      });

      onSuccess();
      onClose();
    } catch (err: unknown) {
      const errObj = err as { response?: { data?: { message?: string } }; message?: string };
      setError(
        errObj.response?.data?.message ||
          errObj.message ||
          'Gagal melakukan koreksi presensi pegawai.'
      );
    } finally {
      setSubmitting(false);
    }
  };

  if (!isOpen || !attendance) return null;

  const staffName =
    attendance.user?.teacher?.full_name ||
    attendance.user?.staff?.full_name ||
    attendance.user?.username ||
    'Pegawai';

  const staffNip = attendance.user?.teacher?.nip || attendance.user?.staff?.nip;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div className="w-full max-w-lg rounded-2xl bg-white shadow-xl flex flex-col max-h-[90vh] overflow-hidden">
        {/* Header */}
        <div className="flex items-center justify-between border-b border-gray-100 px-6 py-4">
          <div className="flex items-center gap-3">
            <div className="rounded-xl bg-amber-50 p-2.5 text-amber-600 border border-amber-100">
              <Edit3 className="h-5 w-5" />
            </div>
            <div>
              <h2 className="text-base font-bold text-gray-900">Koreksi Data Presensi PTK</h2>
              <p className="text-xs text-gray-500">
                Pembaruan data kehadiran resmi dengan pencatatan audit log
              </p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition"
          >
            <X className="h-5 w-5" />
          </button>
        </div>

        {/* Body */}
        <form onSubmit={handleSubmit} className="p-6 overflow-y-auto space-y-4">
          {error && (
            <div className="rounded-xl border border-rose-200 bg-rose-50 p-3.5 flex items-start gap-3">
              <AlertCircle className="h-5 w-5 text-rose-600 shrink-0 mt-0.5" />
              <div className="text-xs text-rose-700">
                <span className="font-semibold block mb-0.5">Validasi Gagal:</span>
                {error}
              </div>
            </div>
          )}

          {/* Info Pegawai & Tanggal */}
          <div className="rounded-xl border border-gray-200 bg-gray-50/70 p-3.5 space-y-2">
            <div className="flex items-center justify-between">
              <span className="text-xs font-semibold text-gray-800 flex items-center gap-1.5">
                <User className="h-4 w-4 text-gray-500" />
                {staffName}
              </span>
              <span
                className={`px-2 py-0.5 text-[11px] font-semibold rounded-full border ${
                  STAFF_ATTENDANCE_STATUS_COLORS[attendance.status]?.badge ||
                  'bg-gray-100 text-gray-700'
                }`}
              >
                Status Semula: {STAFF_ATTENDANCE_STATUS_LABELS[attendance.status]}
              </span>
            </div>
            <div className="text-[11px] text-gray-500 flex items-center gap-4">
              <span>{staffNip ? `NIP: ${staffNip}` : `Email: ${attendance.user?.email || '-'}`}</span>
              <span className="flex items-center gap-1">
                <Calendar className="h-3.5 w-3.5 text-gray-400" />
                Tanggal: {attendance.att_date}
              </span>
            </div>
          </div>

          {/* Status Baru */}
          <div className="space-y-1.5">
            <label className="text-xs font-semibold text-gray-700">
              Koreksi Status Kehadiran <span className="text-rose-500">*</span>
            </label>
            <div className="grid grid-cols-3 gap-2">
              {(['hadir', 'terlambat', 'izin', 'sakit', 'alfa'] as StaffAttendanceStatus[]).map(
                (st) => (
                  <button
                    key={st}
                    type="button"
                    onClick={() => setStatus(st)}
                    className={`rounded-lg border px-3 py-2 text-xs font-medium text-center transition ${
                      status === st
                        ? 'border-amber-500 bg-amber-50 text-amber-800 font-bold shadow-xs'
                        : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50'
                    }`}
                  >
                    {STAFF_ATTENDANCE_STATUS_LABELS[st]}
                  </button>
                )
              )}
            </div>
          </div>

          {/* Jam Masuk & Jam Pulang Koreksi */}
          {(status === 'hadir' || status === 'terlambat') && (
            <div className="grid grid-cols-2 gap-3">
              <div className="space-y-1.5">
                <label className="text-xs font-semibold text-gray-700 flex items-center gap-1.5">
                  <Clock className="h-4 w-4 text-gray-500" />
                  Koreksi Jam Masuk
                </label>
                <input
                  type="time"
                  value={checkInTime}
                  onChange={(e) => setCheckInTime(e.target.value)}
                  className="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
                />
              </div>
              <div className="space-y-1.5">
                <label className="text-xs font-semibold text-gray-700 flex items-center gap-1.5">
                  <Clock className="h-4 w-4 text-gray-500" />
                  Koreksi Jam Pulang
                </label>
                <input
                  type="time"
                  value={checkOutTime}
                  onChange={(e) => setCheckOutTime(e.target.value)}
                  className="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
                />
              </div>
            </div>
          )}

          {/* Alasan Koreksi (Audit Trail Wajib) */}
          <div className="space-y-1.5">
            <label className="text-xs font-semibold text-gray-700 flex items-center gap-1.5">
              <ShieldAlert className="h-4 w-4 text-amber-600" />
              Alasan / Justifikasi Koreksi <span className="text-rose-500">*</span>
            </label>
            <textarea
              rows={3}
              value={reason}
              onChange={(e) => setReason(e.target.value)}
              required
              minLength={3}
              placeholder="Jelaskan alasan perbaikan (misal: GPS error saat presensi mandiri, telah dikonfirmasi hadir oleh Waka Kurikulum)"
              className="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
            />
            <span className="text-[10px] text-gray-500 block">
              Catatan alasan akan dicatat permanen dalam riwayat perubahan (audit log).
            </span>
          </div>

          {/* Footer inside form */}
          <div className="border-t border-gray-100 pt-4 flex items-center justify-end gap-2">
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={onClose}
              disabled={submitting}
              className="text-xs"
            >
              Batal
            </Button>
            <Button
              type="submit"
              size="sm"
              disabled={submitting || !reason.trim()}
              className="bg-amber-600 hover:bg-amber-700 text-white text-xs gap-1.5"
            >
              {submitting ? (
                <>
                  <Loader2 className="h-3.5 w-3.5 animate-spin" />
                  <span>Menyimpan...</span>
                </>
              ) : (
                <span>Simpan Koreksi</span>
              )}
            </Button>
          </div>
        </form>
      </div>
    </div>
  );
}
