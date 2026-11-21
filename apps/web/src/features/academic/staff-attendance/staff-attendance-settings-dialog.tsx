'use client';

import React, { useState, useEffect } from 'react';
import { StaffAttendanceSettings } from '@/types/academic';
import { academicApi } from '../academic-api';
import { Button } from '@/components/ui/button';
import {
  X,
  Settings,
  MapPin,
  Clock,
  Navigation,
  AlertCircle,
  CheckCircle2,
  Loader2,
} from 'lucide-react';

interface StaffAttendanceSettingsDialogProps {
  isOpen: boolean;
  onClose: () => void;
  onSuccess: () => void;
}

export function StaffAttendanceSettingsDialog({
  isOpen,
  onClose,
  onSuccess,
}: StaffAttendanceSettingsDialogProps) {
  const [loading, setLoading] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [successMsg, setSuccessMsg] = useState<string | null>(null);

  const [latitude, setLatitude] = useState<number>(-7.9666204);
  const [longitude, setLongitude] = useState<number>(112.6186082);
  const [radiusMeters, setRadiusMeters] = useState<number>(100);
  const [lateTime, setLateTime] = useState<string>('07:15');

  const [detectingGps, setDetectingGps] = useState(false);

  useEffect(() => {
    if (isOpen) {
      setError(null);
      setSuccessMsg(null);
      setLoading(true);

      academicApi
        .getStaffAttendanceSettings()
        .then((res) => {
          const s = res;
          setLatitude(s.latitude);
          setLongitude(s.longitude);
          setRadiusMeters(s.radius_meters);
          // Format late_time to HH:mm for time input if needed
          setLateTime(s.late_time?.slice(0, 5) || '07:15');
        })
        .catch((err) => {
          setError('Gagal memuat konfigurasi presensi pegawai.');
        })
        .finally(() => {
          setLoading(false);
        });
    }
  }, [isOpen]);

  const handleUseCurrentLocation = () => {
    if (!navigator.geolocation) {
      setError('Geolocation tidak didukung pada browser ini.');
      return;
    }

    setDetectingGps(true);
    setError(null);

    navigator.geolocation.getCurrentPosition(
      (pos) => {
        setLatitude(Number(pos.coords.latitude.toFixed(7)));
        setLongitude(Number(pos.coords.longitude.toFixed(7)));
        setDetectingGps(false);
      },
      () => {
        setDetectingGps(false);
        setError('Gagal mendeteksi lokasi perangkat saat ini.');
      },
      { enableHighAccuracy: true, timeout: 8000 }
    );
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSubmitting(true);
    setError(null);
    setSuccessMsg(null);

    // Format late_time to HH:mm:ss
    const formattedLateTime = lateTime.length === 5 ? `${lateTime}:00` : lateTime;

    try {
      await academicApi.updateStaffAttendanceSettings({
        latitude: Number(latitude),
        longitude: Number(longitude),
        radius_meters: Number(radiusMeters),
        late_time: formattedLateTime,
      });

      setSuccessMsg('Pengaturan presensi berhasil diperbarui!');
      onSuccess();
      setTimeout(() => {
        onClose();
      }, 800);
    } catch (err: unknown) {
      const errObj = err as { response?: { data?: { message?: string } }; message?: string };
      setError(
        errObj.response?.data?.message ||
          errObj.message ||
          'Gagal memperbarui pengaturan presensi.'
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
            <div className="rounded-xl bg-slate-100 p-2.5 text-slate-700 border border-slate-200">
              <Settings className="h-5 w-5" />
            </div>
            <div>
              <h2 className="text-base font-bold text-gray-900">
                Pengaturan Presensi & Geofencing
              </h2>
              <p className="text-xs text-gray-500">
                Koordinat GPS sekolah, radius toleransi, dan jam batas keterlambatan
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
                <span className="font-semibold block mb-0.5">Kesalahan:</span>
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

          {loading ? (
            <div className="flex items-center justify-center py-10 gap-2 text-xs text-gray-500">
              <Loader2 className="h-5 w-5 animate-spin text-emerald-600" />
              <span>Memuat konfigurasi sistem...</span>
            </div>
          ) : (
            <>
              {/* GPS Coordinates Header */}
              <div className="flex items-center justify-between border-b pb-2">
                <span className="text-xs font-bold text-gray-800 flex items-center gap-1.5">
                  <MapPin className="h-4 w-4 text-emerald-600" />
                  Koordinat Pusat Sekolah
                </span>
                <button
                  type="button"
                  onClick={handleUseCurrentLocation}
                  disabled={detectingGps}
                  className="inline-flex items-center gap-1 text-[11px] text-emerald-700 font-semibold hover:underline"
                >
                  {detectingGps ? (
                    <>
                      <Loader2 className="h-3 w-3 animate-spin" />
                      <span>Mendeteksi Lokasi...</span>
                    </>
                  ) : (
                    <>
                      <Navigation className="h-3 w-3" />
                      <span>Pakai Lokasi Saat Ini</span>
                    </>
                  )}
                </button>
              </div>

              {/* Latitude & Longitude Inputs */}
              <div className="grid grid-cols-2 gap-3">
                <div className="space-y-1.5">
                  <label className="text-xs font-semibold text-gray-700">
                    Latitude <span className="text-rose-500">*</span>
                  </label>
                  <input
                    type="number"
                    step="any"
                    value={latitude}
                    onChange={(e) => setLatitude(parseFloat(e.target.value) || 0)}
                    required
                    className="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs font-mono focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                  />
                </div>
                <div className="space-y-1.5">
                  <label className="text-xs font-semibold text-gray-700">
                    Longitude <span className="text-rose-500">*</span>
                  </label>
                  <input
                    type="number"
                    step="any"
                    value={longitude}
                    onChange={(e) => setLongitude(parseFloat(e.target.value) || 0)}
                    required
                    className="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs font-mono focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                  />
                </div>
              </div>

              {/* Radius Geofencing */}
              <div className="space-y-1.5">
                <label className="text-xs font-semibold text-gray-700">
                  Radius Toleransi Geofencing (Meter) <span className="text-rose-500">*</span>
                </label>
                <div className="relative">
                  <input
                    type="number"
                    min={10}
                    max={50000}
                    value={radiusMeters}
                    onChange={(e) => setRadiusMeters(parseInt(e.target.value, 10) || 100)}
                    required
                    className="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                  />
                  <span className="absolute right-3 top-2 text-xs text-gray-400">meter</span>
                </div>
                <p className="text-[11px] text-gray-500">
                  Jarak maksimal pegawai dari titik pusat sekolah saat melakukan presensi mandiri.
                </p>
              </div>

              {/* Jam Batas Keterlambatan */}
              <div className="space-y-1.5 pt-2 border-t">
                <label className="text-xs font-semibold text-gray-700 flex items-center gap-1.5">
                  <Clock className="h-4 w-4 text-amber-600" />
                  Batas Jam Masuk Tepat Waktu <span className="text-rose-500">*</span>
                </label>
                <input
                  type="time"
                  value={lateTime}
                  onChange={(e) => setLateTime(e.target.value)}
                  required
                  className="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                />
                <p className="text-[11px] text-gray-500">
                  Presensi mandiri yang dicatat setelah jam ini akan otomatis berstatus{' '}
                  <span className="font-semibold text-amber-700">Terlambat</span>.
                </p>
              </div>
            </>
          )}

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
              disabled={submitting || loading}
              className="bg-slate-900 hover:bg-slate-800 text-white text-xs gap-1.5"
            >
              {submitting ? (
                <>
                  <Loader2 className="h-3.5 w-3.5 animate-spin" />
                  <span>Menyimpan...</span>
                </>
              ) : (
                <span>Simpan Pengaturan</span>
              )}
            </Button>
          </div>
        </form>
      </div>
    </div>
  );
}
