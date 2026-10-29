'use client';

import React, { useState } from 'react';
import Link from 'next/link';
import {
  useSharedSelfAttendance,
  sharedPortalApi,
  SharedSelfAttendance,
} from '@/features/portal/shared';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { LoadingSpinner } from '@/components/shared/loading-spinner';
import {
  Clock,
  ArrowLeft,
  CheckCircle2,
  CalendarCheck,
  MapPin,
  ShieldCheck,
  Timer,
  AlertCircle,
} from 'lucide-react';

export default function StaffSelfAttendancePage() {
  const { data: initialData, isLoading } = useSharedSelfAttendance('staf');
  const [attendance, setAttendance] = useState<SharedSelfAttendance | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [feedback, setFeedback] = useState<string | null>(null);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  React.useEffect(() => {
    if (initialData) {
      setAttendance(initialData);
    }
  }, [initialData]);

  const handleClockAction = async (action: 'check_in' | 'check_out') => {
    setIsSubmitting(true);
    setFeedback(null);
    setErrorMessage(null);
    try {
      const res = await sharedPortalApi.recordSelfAttendance(action, 'staf');
      setAttendance((prev) => {
        if (!prev) return null;
        return {
          ...prev,
          checkInTime: action === 'check_in' ? res.timestamp : prev.checkInTime,
          checkOutTime: action === 'check_out' ? res.timestamp : prev.checkOutTime,
          status: 'present',
        };
      });
      setFeedback(res.message);
      setTimeout(() => setFeedback(null), 4000);
    } catch (err: any) {
      setErrorMessage(err?.message || 'Gagal mencatat presensi mandiri staf. Silakan periksa koneksi Anda.');
      setTimeout(() => setErrorMessage(null), 5000);
    } finally {
      setIsSubmitting(false);
    }
  };

  const currentDateStr = new Date().toLocaleDateString('id-ID', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  });

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
            <Clock className="h-6 w-6 text-brand-blue" />
            <span>Presensi Kerja Mandiri Staf (TU & Sarpras)</span>
          </h1>
          <p className="text-xs sm:text-sm text-slate-500 mt-1">
            Pencatatan jam kedatangan dan kepulangan kerja tenaga kependidikan SMP Laboratorium UM.
          </p>
        </div>

        <div className="flex items-center gap-2">
          <Badge variant="outline" className="text-xs font-bold px-3 py-1 bg-slate-50 text-slate-700">
            {currentDateStr}
          </Badge>
        </div>
      </div>

      {feedback && (
        <div className="p-4 bg-emerald-50 border border-emerald-200 rounded-xl text-status-success text-xs sm:text-sm font-bold flex items-center gap-2 animate-in fade-in duration-200">
          <CheckCircle2 className="h-5 w-5 shrink-0" />
          <span>{feedback}</span>
        </div>
      )}

      {errorMessage && (
        <div className="p-4 bg-red-50 border border-red-200 rounded-xl text-status-danger text-xs sm:text-sm font-bold flex items-center gap-2 animate-in fade-in duration-200">
          <AlertCircle className="h-5 w-5 shrink-0" />
          <span>{errorMessage}</span>
        </div>
      )}

      {/* Clock In / Out Action Card */}
      {isLoading ? (
        <div className="min-h-[250px] flex items-center justify-center bg-white rounded-2xl border border-slate-200">
          <LoadingSpinner label="Memeriksa status presensi hari ini..." size="lg" />
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
          <Card className="border-slate-200 md:col-span-2">
            <CardHeader className="pb-3">
              <CardTitle className="text-base font-bold text-slate-900 flex items-center gap-2">
                <CalendarCheck className="h-5 w-5 text-brand-blue" />
                <span>Status Absensi Hari Ini</span>
              </CardTitle>
              <CardDescription className="text-xs text-slate-500">
                Lokasi terverifikasi: Kantor Tata Usaha SMP Laboratorium UM
              </CardDescription>
            </CardHeader>

            <CardContent className="space-y-6">
              <div className="grid grid-cols-2 gap-4">
                <div className="p-4 rounded-xl bg-blue-50/60 border border-blue-100 flex flex-col justify-between">
                  <span className="text-xs font-bold text-slate-500">Jam Masuk (Clock In)</span>
                  <div className="text-2xl font-black text-slate-900 mt-2">
                    {attendance?.checkInTime || '--:--'}
                  </div>
                  <span className="text-[11px] text-slate-400 mt-1">Batas toleransi: 07.00 WIB</span>
                </div>

                <div className="p-4 rounded-xl bg-slate-50 border border-slate-200 flex flex-col justify-between">
                  <span className="text-xs font-bold text-slate-500">Jam Pulang (Clock Out)</span>
                  <div className="text-2xl font-black text-slate-900 mt-2">
                    {attendance?.checkOutTime || '--:--'}
                  </div>
                  <span className="text-[11px] text-slate-400 mt-1">Kepulangan normal: 16.00 WIB</span>
                </div>
              </div>

              {/* Action Buttons */}
              <div className="flex flex-col sm:flex-row gap-3 pt-2">
                <Button
                  onClick={() => handleClockAction('check_in')}
                  disabled={isSubmitting || !!attendance?.checkInTime}
                  className="flex-1 bg-brand-blue hover:bg-blue-700 text-white font-bold h-12 text-sm gap-2"
                >
                  <Clock className="h-4 w-4" />
                  <span>
                    {attendance?.checkInTime
                      ? `Sudah Clock In (${attendance.checkInTime.substring(0, 5)})`
                      : 'Catat Jam Masuk (Clock In)'}
                  </span>
                </Button>

                <Button
                  onClick={() => handleClockAction('check_out')}
                  disabled={isSubmitting || !attendance?.checkInTime || !!attendance?.checkOutTime}
                  variant="outline"
                  className="flex-1 border-slate-300 font-bold h-12 text-sm gap-2 hover:bg-slate-100"
                >
                  <Timer className="h-4 w-4" />
                  <span>
                    {attendance?.checkOutTime
                      ? `Sudah Clock Out (${attendance.checkOutTime.substring(0, 5)})`
                      : 'Catat Jam Pulang (Clock Out)'}
                  </span>
                </Button>
              </div>

              <div className="flex items-center gap-2 p-3 bg-slate-50 rounded-lg text-[11px] text-slate-500">
                <MapPin className="h-3.5 w-3.5 text-slate-400 shrink-0" />
                <span>Radius validasi geofence GPS diaktifkan. Pastikan berada di area sekolah.</span>
              </div>
            </CardContent>
          </Card>

          {/* Jam Kerja Bulanan Widget */}
          <Card className="border-slate-200">
            <CardHeader className="pb-3">
              <CardTitle className="text-base font-bold text-slate-900 flex items-center gap-2">
                <ShieldCheck className="h-5 w-5 text-status-success" />
                <span>Target Jam Kerja</span>
              </CardTitle>
              <CardDescription className="text-xs text-slate-500">
                Bulan Oktober 2026
              </CardDescription>
            </CardHeader>

            <CardContent className="space-y-4">
              <div>
                <div className="flex justify-between text-xs font-semibold mb-1">
                  <span className="text-slate-600">Jam Kerja Terpenuhi</span>
                  <span className="text-brand-blue font-bold">
                    {attendance?.workHoursThisMonth || 0} / {attendance?.targetWorkHours || 160} Jam
                  </span>
                </div>
                <div className="h-2.5 w-full bg-slate-100 rounded-full overflow-hidden">
                  <div
                    className="h-full bg-brand-blue rounded-full transition-all duration-500"
                    style={{
                      width: `${Math.min(
                        100,
                        (((attendance?.workHoursThisMonth || 0) / (attendance?.targetWorkHours || 160)) * 100)
                      )}%`,
                    }}
                  />
                </div>
              </div>

              <div className="p-3 rounded-xl bg-emerald-50 border border-emerald-100 flex items-center justify-between">
                <div>
                  <span className="text-[11px] text-emerald-700 font-semibold block">Tingkat Disiplin</span>
                  <span className="text-lg font-black text-emerald-800">
                    {attendance?.attendancePercentage || 100}%
                  </span>
                </div>
                <CheckCircle2 className="h-6 w-6 text-emerald-600" />
              </div>

              <div className="text-[11px] text-slate-500 space-y-1 pt-2 border-t border-slate-100">
                <div className="flex justify-between">
                  <span>Hari Kerja Efektif:</span>
                  <span className="font-semibold text-slate-700">22 Hari</span>
                </div>
                <div className="flex justify-between">
                  <span>Cuti / Izin Diambil:</span>
                  <span className="font-semibold text-slate-700">1 Hari</span>
                </div>
                <div className="flex justify-between">
                  <span>Keterlambatan:</span>
                  <span className="font-semibold text-emerald-600">0 Menit</span>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>
      )}
    </div>
  );
}
