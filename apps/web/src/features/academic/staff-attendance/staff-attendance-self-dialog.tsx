'use client';

import React, { useState, useEffect } from 'react';
import {
  StaffAttendanceItem,
  STAFF_ATTENDANCE_STATUS_LABELS,
  STAFF_ATTENDANCE_STATUS_COLORS,
} from '@/types/academic';
import { academicApi } from '../academic-api';
import { Button } from '@/components/ui/button';
import {
  X,
  UserCheck,
  MapPin,
  Camera,
  Clock,
  CheckCircle2,
  AlertCircle,
  Loader2,
  LogOut,
  LogIn,
  Navigation,
} from 'lucide-react';

interface StaffAttendanceSelfDialogProps {
  isOpen: boolean;
  onClose: () => void;
  onSuccess: () => void;
}

export function StaffAttendanceSelfDialog({
  isOpen,
  onClose,
  onSuccess,
}: StaffAttendanceSelfDialogProps) {
  const [todayRecord, setTodayRecord] = useState<StaffAttendanceItem | null>(null);
  const [loadingToday, setLoadingToday] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [successMsg, setSuccessMsg] = useState<string | null>(null);

  // GPS coordinates
  const [lat, setLat] = useState<number | null>(null);
  const [lng, setLng] = useState<number | null>(null);
  const [accuracy, setAccuracy] = useState<number | null>(null);
  const [locating, setLocating] = useState(false);
  const [locationError, setLocationError] = useState<string | null>(null);

  // Form inputs
  const [notes, setNotes] = useState('');
  const [photoFile, setPhotoFile] = useState<File | null>(null);
  const [photoPreview, setPhotoPreview] = useState<string | null>(null);

  // Fetch today's record on dialog open
  useEffect(() => {
    if (isOpen) {
      setError(null);
      setSuccessMsg(null);
      setLoadingToday(true);
      academicApi
        .getStaffToday()
        .then((res) => {
          setTodayRecord(res);
        })
        .catch(() => {
          setTodayRecord(null);
        })
        .finally(() => {
          setLoadingToday(false);
        });

      // Automatically acquire current GPS location
      requestLocation();
    } else {
      // Reset state on close
      setPhotoFile(null);
      setPhotoPreview((prev) => {
        if (prev) URL.revokeObjectURL(prev);
        return null;
      });
      setNotes('');
    }
  }, [isOpen]);

  const requestLocation = () => {
    if (!navigator.geolocation) {
      setLocationError('Perangkat atau browser tidak mendukung fitur Geolocation.');
      return;
    }

    setLocating(true);
    setLocationError(null);

    navigator.geolocation.getCurrentPosition(
      (pos) => {
        setLat(pos.coords.latitude);
        setLng(pos.coords.longitude);
        setAccuracy(Math.round(pos.coords.accuracy));
        setLocating(false);
      },
      (err) => {
        setLocating(false);
        let msg = 'Gagal mendeteksi lokasi GPS.';
        if (err.code === err.PERMISSION_DENIED) {
          msg = 'Izin lokasi GPS ditolak oleh browser. Mohon izinkan akses lokasi.';
        } else if (err.code === err.POSITION_UNAVAILABLE) {
          msg = 'Sinyal GPS tidak tersedia saat ini.';
        } else if (err.code === err.TIMEOUT) {
          msg = 'Waktu permintaan lokasi habis (timeout). Silakan coba lagi.';
        }
        setLocationError(msg);
      },
      {
        enableHighAccuracy: true,
        timeout: 10000,
        maximumAge: 0,
      }
    );
  };

  const handlePhotoChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files && e.target.files[0]) {
      const file = e.target.files[0];
      setPhotoFile(file);
      const url = URL.createObjectURL(file);
      setPhotoPreview(url);
    }
  };

  const handleRemovePhoto = () => {
    setPhotoFile(null);
    if (photoPreview) {
      URL.revokeObjectURL(photoPreview);
      setPhotoPreview(null);
    }
  };

  const handleCheckIn = async () => {
    setError(null);
    setSuccessMsg(null);
    setSubmitting(true);

    try {
      const res = await academicApi.checkInStaff({
        lat: lat ?? undefined,
        lng: lng ?? undefined,
        photo_file: photoFile ?? undefined,
        notes: notes.trim() || undefined,
      });

      setTodayRecord(res);
      setSuccessMsg('Presensi masuk berhasil dicatat!');
      onSuccess();
    } catch (err: unknown) {
      const errObj = err as { response?: { data?: { message?: string } }; message?: string };
      setError(
        errObj.response?.data?.message ||
          errObj.message ||
          'Gagal melakukan presensi masuk. Pastikan Anda berada dalam radius sekolah.'
      );
    } finally {
      setSubmitting(false);
    }
  };

  const handleCheckOut = async () => {
    setError(null);
    setSuccessMsg(null);
    setSubmitting(true);

    try {
      const res = await academicApi.checkOutStaff({
        lat: lat ?? undefined,
        lng: lng ?? undefined,
        notes: notes.trim() || undefined,
      });

      setTodayRecord(res);
      setSuccessMsg('Presensi pulang berhasil dicatat!');
      onSuccess();
    } catch (err: unknown) {
      const errObj = err as { response?: { data?: { message?: string } }; message?: string };
      setError(
        errObj.response?.data?.message ||
          errObj.message ||
          'Gagal melakukan presensi pulang.'
      );
    } finally {
      setSubmitting(false);
    }
  };

  if (!isOpen) return null;

  const hasCheckedIn = !!todayRecord?.check_in;
  const hasCheckedOut = !!todayRecord?.check_out;

  const formatTime = (isoString?: string | null) => {
    if (!isoString) return '-';
    try {
      const date = new Date(isoString);
      return date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    } catch {
      return isoString;
    }
  };

  const todayDateFormatted = new Date().toLocaleDateString('id-ID', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div className="w-full max-w-lg rounded-2xl bg-white shadow-xl flex flex-col max-h-[90vh] overflow-hidden">
        {/* Header */}
        <div className="flex items-center justify-between border-b border-gray-100 px-6 py-4">
          <div className="flex items-center gap-3">
            <div className="rounded-xl bg-emerald-50 p-2.5 text-emerald-600 border border-emerald-100">
              <UserCheck className="h-5 w-5" />
            </div>
            <div>
              <h2 className="text-base font-bold text-gray-900">Presensi Mandiri PTK Hari Ini</h2>
              <p className="text-xs text-gray-500">{todayDateFormatted}</p>
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
        <div className="p-6 overflow-y-auto space-y-4">
          {/* Status Alert if feedback */}
          {error && (
            <div className="rounded-xl border border-rose-200 bg-rose-50 p-3.5 flex items-start gap-3">
              <AlertCircle className="h-5 w-5 text-rose-600 shrink-0 mt-0.5" />
              <div className="text-xs text-rose-700">
                <span className="font-semibold block mb-0.5">Terjadi Kendala Presensi:</span>
                {error}
              </div>
            </div>
          )}

          {successMsg && (
            <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-3.5 flex items-start gap-3">
              <CheckCircle2 className="h-5 w-5 text-emerald-600 shrink-0 mt-0.5" />
              <div className="text-xs text-emerald-700 font-medium">
                {successMsg}
              </div>
            </div>
          )}

          {/* Today Status Card */}
          <div className="rounded-xl border border-gray-200 bg-gray-50/70 p-4">
            <div className="flex items-center justify-between mb-3">
              <span className="text-xs font-semibold text-gray-700 flex items-center gap-1.5">
                <Clock className="h-4 w-4 text-gray-500" />
                Status Presensi Hari Ini
              </span>
              {loadingToday ? (
                <Loader2 className="h-4 w-4 animate-spin text-emerald-600" />
              ) : todayRecord ? (
                <span
                  className={`px-2 py-0.5 text-xs font-semibold rounded-full border ${
                    STAFF_ATTENDANCE_STATUS_COLORS[todayRecord.status]?.badge ||
                    'bg-gray-100 text-gray-700'
                  }`}
                >
                  {STAFF_ATTENDANCE_STATUS_LABELS[todayRecord.status]}
                </span>
              ) : (
                <span className="px-2 py-0.5 text-xs font-semibold rounded-full bg-gray-200 text-gray-600">
                  Belum Presensi
                </span>
              )}
            </div>

            <div className="grid grid-cols-2 gap-3 text-xs">
              <div className="bg-white rounded-lg p-2.5 border border-gray-200">
                <span className="text-gray-500 block text-[11px]">Jam Masuk</span>
                <span className="font-bold text-gray-900 text-sm">
                  {formatTime(todayRecord?.check_in)}
                </span>
              </div>
              <div className="bg-white rounded-lg p-2.5 border border-gray-200">
                <span className="text-gray-500 block text-[11px]">Jam Pulang</span>
                <span className="font-bold text-gray-900 text-sm">
                  {formatTime(todayRecord?.check_out)}
                </span>
              </div>
            </div>

            {todayRecord?.notes && (
              <div className="mt-2.5 text-[11px] text-gray-600 bg-white/70 p-2 rounded border border-gray-100">
                <span className="font-semibold">Catatan:</span> {todayRecord.notes}
              </div>
            )}
          </div>

          {/* Location / GPS Section */}
          <div className="rounded-xl border border-gray-200 bg-white p-4 space-y-2.5">
            <div className="flex items-center justify-between">
              <span className="text-xs font-semibold text-gray-800 flex items-center gap-1.5">
                <MapPin className="h-4 w-4 text-emerald-600" />
                Deteksi Geofencing GPS
              </span>
              <button
                type="button"
                onClick={requestLocation}
                disabled={locating}
                className="inline-flex items-center gap-1 text-[11px] text-emerald-700 font-semibold hover:underline"
              >
                {locating ? (
                  <>
                    <Loader2 className="h-3 w-3 animate-spin" />
                    <span>Mencari Sinyal...</span>
                  </>
                ) : (
                  <>
                    <Navigation className="h-3 w-3" />
                    <span>Perbarui Lokasi</span>
                  </>
                )}
              </button>
            </div>

            {lat !== null && lng !== null ? (
              <div className="bg-emerald-50/70 border border-emerald-200 rounded-lg p-2.5 text-xs text-emerald-800 flex items-center justify-between">
                <div>
                  <span className="font-mono text-[11px] block">
                    Lat: {lat.toFixed(6)}, Lng: {lng.toFixed(6)}
                  </span>
                  {accuracy !== null && (
                    <span className="text-[10px] text-emerald-600">
                      Akurasi perangkat: ±{accuracy} meter
                    </span>
                  )}
                </div>
                <span className="text-[10px] bg-emerald-200 text-emerald-900 font-semibold px-2 py-0.5 rounded">
                  GPS Terkunci
                </span>
              </div>
            ) : locationError ? (
              <div className="bg-amber-50 border border-amber-200 rounded-lg p-2.5 text-xs text-amber-800">
                {locationError}
              </div>
            ) : (
              <div className="bg-gray-50 border border-gray-200 rounded-lg p-2.5 text-xs text-gray-500">
                Menunggu sinyal GPS untuk validasi geofencing sekolah...
              </div>
            )}
          </div>

          {/* Photo Selfie (optional for check-in) */}
          {!hasCheckedIn && (
            <div className="space-y-1.5">
              <label className="text-xs font-semibold text-gray-700 flex items-center gap-1.5">
                <Camera className="h-4 w-4 text-gray-500" />
                Foto Swafoto / Bukti Kehadiran (Opsional)
              </label>

              {photoPreview ? (
                <div className="relative inline-block border rounded-xl overflow-hidden shadow-xs">
                  {/* eslint-disable-next-line @next/next/no-img-element */}
                  <img
                    src={photoPreview}
                    alt="Swafoto Presensi"
                    className="h-28 w-28 object-cover rounded-xl"
                  />
                  <button
                    type="button"
                    onClick={handleRemovePhoto}
                    className="absolute top-1 right-1 bg-black/60 text-white rounded-full p-1 hover:bg-black/80"
                  >
                    <X className="h-3.5 w-3.5" />
                  </button>
                </div>
              ) : (
                <input
                  type="file"
                  accept="image/*"
                  capture="user"
                  onChange={handlePhotoChange}
                  className="block w-full text-xs text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 cursor-pointer border border-gray-200 rounded-lg p-1"
                />
              )}
            </div>
          )}

          {/* Notes Input */}
          <div className="space-y-1.5">
            <label className="text-xs font-semibold text-gray-700">
              Catatan / Keterangan Tambahan (Opsional)
            </label>
            <input
              type="text"
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
              placeholder="Contoh: Tugas piket pagi gerbang utama, dll."
              className="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
            />
          </div>
        </div>

        {/* Footer */}
        <div className="border-t border-gray-100 bg-gray-50 px-6 py-4 flex items-center justify-between">
          <Button
            type="button"
            variant="outline"
            size="sm"
            onClick={onClose}
            className="text-xs"
          >
            Tutup
          </Button>

          <div className="flex items-center gap-2">
            {/* Tombol Check-In */}
            <Button
              type="button"
              size="sm"
              onClick={handleCheckIn}
              disabled={submitting || hasCheckedIn || locating}
              className="bg-emerald-600 hover:bg-emerald-700 text-white text-xs gap-1.5"
            >
              {submitting ? (
                <Loader2 className="h-3.5 w-3.5 animate-spin" />
              ) : (
                <LogIn className="h-3.5 w-3.5" />
              )}
              <span>{hasCheckedIn ? 'Sudah Masuk' : 'Presensi Masuk'}</span>
            </Button>

            {/* Tombol Check-Out */}
            <Button
              type="button"
              size="sm"
              onClick={handleCheckOut}
              disabled={submitting || !hasCheckedIn || hasCheckedOut || locating}
              className="bg-blue-600 hover:bg-blue-700 text-white text-xs gap-1.5"
            >
              {submitting ? (
                <Loader2 className="h-3.5 w-3.5 animate-spin" />
              ) : (
                <LogOut className="h-3.5 w-3.5" />
              )}
              <span>{hasCheckedOut ? 'Sudah Pulang' : 'Presensi Pulang'}</span>
            </Button>
          </div>
        </div>
      </div>
    </div>
  );
}
