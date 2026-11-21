'use client';

import React, { useState, useEffect } from 'react';
import {
  StaffAttendanceStatus,
  STAFF_ATTENDANCE_STATUS_LABELS,
} from '@/types/academic';
import { TeacherItem } from '@/types/master';
import { teachersApi } from '@/features/master/teachers-api';
import { academicApi } from '../academic-api';
import { Button } from '@/components/ui/button';
import {
  X,
  PlusCircle,
  Calendar,
  User,
  Clock,
  FileText,
  AlertCircle,
  Loader2,
} from 'lucide-react';

interface StaffAttendanceManualDialogProps {
  isOpen: boolean;
  onClose: () => void;
  onSuccess: () => void;
}

export function StaffAttendanceManualDialog({
  isOpen,
  onClose,
  onSuccess,
}: StaffAttendanceManualDialogProps) {
  const [teachers, setTeachers] = useState<TeacherItem[]>([]);
  const [loadingTeachers, setLoadingTeachers] = useState(false);

  // Form states
  const [selectedUserId, setSelectedUserId] = useState<string>('');
  const todayStr = new Date().toISOString().slice(0, 10);
  const [attDate, setAttDate] = useState<string>(todayStr);
  const [status, setStatus] = useState<StaffAttendanceStatus>('hadir');
  const [checkInTime, setCheckInTime] = useState<string>('07:00');
  const [checkOutTime, setCheckOutTime] = useState<string>('15:30');
  const [notes, setNotes] = useState<string>('');

  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Load teacher list on dialog open
  useEffect(() => {
    if (isOpen) {
      setError(null);
      setLoadingTeachers(true);
      teachersApi
        .list({ limit: 100 })
        .then((res) => {
          const list = res.data || [];
          setTeachers(list);
          const firstWithUser = list.find((t) => t.user_id || t.user?.id);
          if (firstWithUser) {
            setSelectedUserId(firstWithUser.user_id || firstWithUser.user?.id || '');
          }
        })
        .catch(() => {})
        .finally(() => {
          setLoadingTeachers(false);
        });
    } else {
      setNotes('');
      setError(null);
    }
  }, [isOpen]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedUserId) {
      setError('Pegawai / Pendidik wajib dipilih.');
      return;
    }
    if (!attDate) {
      setError('Tanggal presensi wajib diisi.');
      return;
    }

    setSubmitting(true);
    setError(null);

    // Format check_in and check_out to YYYY-MM-DD HH:mm:ss if provided and status is present
    let formattedCheckIn: string | undefined = undefined;
    let formattedCheckOut: string | undefined = undefined;

    if ((status === 'hadir' || status === 'terlambat') && checkInTime) {
      formattedCheckIn = `${attDate} ${checkInTime.length === 5 ? checkInTime + ':00' : checkInTime}`;
    }
    if ((status === 'hadir' || status === 'terlambat') && checkOutTime) {
      formattedCheckOut = `${attDate} ${checkOutTime.length === 5 ? checkOutTime + ':00' : checkOutTime}`;
    }

    try {
      await academicApi.manualRecordStaffAttendance({
        user_id: selectedUserId,
        att_date: attDate,
        status,
        check_in: formattedCheckIn,
        check_out: formattedCheckOut,
        notes: notes.trim() || undefined,
      });

      onSuccess();
      onClose();
    } catch (err: unknown) {
      const errObj = err as { response?: { data?: { message?: string } }; message?: string };
      setError(
        errObj.response?.data?.message ||
          errObj.message ||
          'Gagal menyimpan entri presensi manual.'
      );
    } finally {
      setSubmitting(false);
    }
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div className="w-full max-w-lg rounded-2xl bg-white shadow-xl flex flex-col max-h-[90vh] overflow-hidden">
        {/* Header */}
        <div className="flex items-center justify-between border-b border-gray-100 px-6 py-4">
          <div className="flex items-center gap-3">
            <div className="rounded-xl bg-purple-50 p-2.5 text-purple-600 border border-purple-100">
              <PlusCircle className="h-5 w-5" />
            </div>
            <div>
              <h2 className="text-base font-bold text-gray-900">Entri Presensi Manual PTK</h2>
              <p className="text-xs text-gray-500">
                Pencatatan langsung oleh Tata Usaha / Admin Akademik
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

          {/* Pegawai / Pendidik */}
          <div className="space-y-1.5">
            <label className="text-xs font-semibold text-gray-700 flex items-center gap-1.5">
              <User className="h-4 w-4 text-gray-500" />
              Nama Guru / Pegawai <span className="text-rose-500">*</span>
            </label>
            {loadingTeachers ? (
              <div className="flex items-center gap-2 text-xs text-gray-500 py-2">
                <Loader2 className="h-4 w-4 animate-spin" />
                <span>Memuat daftar pegawai...</span>
              </div>
            ) : (
              <select
                value={selectedUserId}
                onChange={(e) => setSelectedUserId(e.target.value)}
                required
                className="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs focus:border-purple-500 focus:outline-none focus:ring-1 focus:ring-purple-500"
              >
                <option value="">-- Pilih Pegawai --</option>
                {teachers.map((t) => {
                  const uid = t.user_id || t.user?.id;
                  if (!uid) return null;
                  return (
                    <option key={t.id} value={uid}>
                      {t.full_name} {t.nip ? `(NIP: ${t.nip})` : ''}
                    </option>
                  );
                })}
              </select>
            )}
          </div>

          {/* Tanggal Presensi */}
          <div className="space-y-1.5">
            <label className="text-xs font-semibold text-gray-700 flex items-center gap-1.5">
              <Calendar className="h-4 w-4 text-gray-500" />
              Tanggal Presensi <span className="text-rose-500">*</span>
            </label>
            <input
              type="date"
              value={attDate}
              onChange={(e) => setAttDate(e.target.value)}
              required
              className="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs focus:border-purple-500 focus:outline-none focus:ring-1 focus:ring-purple-500"
            />
          </div>

          {/* Status Kehadiran */}
          <div className="space-y-1.5">
            <label className="text-xs font-semibold text-gray-700">
              Status Kehadiran <span className="text-rose-500">*</span>
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
                        ? 'border-purple-500 bg-purple-50 text-purple-700 font-bold shadow-xs'
                        : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50'
                    }`}
                  >
                    {STAFF_ATTENDANCE_STATUS_LABELS[st]}
                  </button>
                )
              )}
            </div>
          </div>

          {/* Jam Masuk & Pulang (jika hadir/terlambat) */}
          {(status === 'hadir' || status === 'terlambat') && (
            <div className="grid grid-cols-2 gap-3">
              <div className="space-y-1.5">
                <label className="text-xs font-semibold text-gray-700 flex items-center gap-1.5">
                  <Clock className="h-4 w-4 text-gray-500" />
                  Jam Masuk
                </label>
                <input
                  type="time"
                  value={checkInTime}
                  onChange={(e) => setCheckInTime(e.target.value)}
                  className="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs focus:border-purple-500 focus:outline-none focus:ring-1 focus:ring-purple-500"
                />
              </div>
              <div className="space-y-1.5">
                <label className="text-xs font-semibold text-gray-700 flex items-center gap-1.5">
                  <Clock className="h-4 w-4 text-gray-500" />
                  Jam Pulang
                </label>
                <input
                  type="time"
                  value={checkOutTime}
                  onChange={(e) => setCheckOutTime(e.target.value)}
                  className="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs focus:border-purple-500 focus:outline-none focus:ring-1 focus:ring-purple-500"
                />
              </div>
            </div>
          )}

          {/* Catatan / Keterangan */}
          <div className="space-y-1.5">
            <label className="text-xs font-semibold text-gray-700 flex items-center gap-1.5">
              <FileText className="h-4 w-4 text-gray-500" />
              Catatan / Justifikasi
            </label>
            <textarea
              rows={3}
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
              placeholder="Contoh: Penugasan dinas luar kota / kendala absensi elektronik"
              className="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs focus:border-purple-500 focus:outline-none focus:ring-1 focus:ring-purple-500"
            />
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
              disabled={submitting || !selectedUserId}
              className="bg-purple-600 hover:bg-purple-700 text-white text-xs gap-1.5"
            >
              {submitting ? (
                <>
                  <Loader2 className="h-3.5 w-3.5 animate-spin" />
                  <span>Menyimpan...</span>
                </>
              ) : (
                <span>Simpan Presensi</span>
              )}
            </Button>
          </div>
        </form>
      </div>
    </div>
  );
}
