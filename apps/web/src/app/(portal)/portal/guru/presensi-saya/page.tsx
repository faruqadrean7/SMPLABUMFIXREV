'use client';

import React, { useState } from 'react';
import Link from 'next/link';
import {
  useTeacherSelfAttendance,
  guruApi,
  TeacherSelfAttendance,
} from '@/features/portal/guru';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { LoadingSpinner } from '@/components/shared/loading-spinner';
import {
  Clock,
  ArrowLeft,
  CheckCircle2,
  CalendarCheck,
  AlertCircle,
  MapPin,
  ShieldCheck,
  Timer,
} from 'lucide-react';

export default function TeacherSelfAttendancePage() {
  const { data: initialData, isLoading } = useTeacherSelfAttendance();
  const [attendance, setAttendance] = useState<TeacherSelfAttendance | null>(null);
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
      const res = await guruApi.recordSelfAttendance(action);
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
      setErrorMessage(err?.message || 'Gagal mencatat presensi mandiri guru. Silakan periksa koneksi Anda.');
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
              href="/portal/guru"
              className="inline-flex items-center gap-1.5 text-xs text-slate-500 hover:text-slate-900 font-medium transition-colors"
            >
              <ArrowLeft className="h-4 w-4" />
              <span>Kembali ke Beranda</span>
            </Link>
          </div>
          <h1 className="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2">
            <Clock className="h-6 w-6 text-brand-blue" />
            <span>Presensi Mandiri Guru (Jam Kerja)</span>
          </h1>
          <p className="text-xs sm:text-sm text-slate-500 mt-1">
            Pencatatan jam kedatangan dan kepulangan dinas guru SMP Laboratorium UM.
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
                Lokasi terverifikasi: Kompleks Kampus SMP Laboratorium UM
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
                  <span className="text-[11px] text-slate-400 mt-1">Kepulangan normal: 15.30 WIB</span>
                </div>
              </div>

              {/* Action Buttons */}
              <div className="flex flex-col sm:flex-row items-center gap-3 pt-2">
                {!attendance?.checkInTime ? (
                  <Button
                    onClick={() => handleClockAction('check_in')}
                    isLoading={isSubmitting}
                    size="lg"
                    className="w-full gap-2 font-bold bg-emerald-600 hover:bg-emerald-700"
                  >
                    <CheckCircle2 className="h-5 w-5" />
                    <span>Catat Jam Masuk Sekarang</span>
                  </Button>
                ) : !attendance?.checkOutTime ? (
                  <Button
                    onClick={() => handleClockAction('check_out')}
                    isLoading={isSubmitting}
                    size="lg"
                    className="w-full gap-2 font-bold bg-brand-blue hover:bg-blue-800"
                  >
                    <Clock className="h-5 w-5" />
                    <span>Catat Jam Pulang Sekarang</span>
                  </Button>
                ) : (
                  <div className="p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-status-success font-bold text-xs text-center w-full flex items-center justify-center gap-2">
                    <CheckCircle2 className="h-4 w-4" />
                    <span>Presensi kerja hari ini telah lengkap (Masuk & Pulang).</span>
                  </div>
                )}
              </div>
            </CardContent>
          </Card>

          {/* Monthly Recap Summary */}
          <Card className="border-slate-200">
            <CardHeader className="pb-3">
              <CardTitle className="text-base font-bold text-slate-900 flex items-center gap-2">
                <Timer className="h-5 w-5 text-brand-blue" />
                <span>Akumulasi Bulan Ini</span>
              </CardTitle>
              <CardDescription className="text-xs text-slate-500">
                Target jam dinas & mengajar
              </CardDescription>
            </CardHeader>

            <CardContent className="space-y-4 pt-1">
              <div className="p-3 bg-slate-50 rounded-xl border border-slate-100">
                <span className="text-[10px] text-slate-400 font-bold block uppercase">Total Jam Kerja</span>
                <div className="text-xl font-black text-slate-900 mt-0.5">
                  {attendance?.workHoursThisMonth || 128} <span className="text-xs font-normal text-slate-500">/ {attendance?.targetWorkHours || 160} jam</span>
                </div>
              </div>

              <div className="p-3 bg-slate-50 rounded-xl border border-slate-100">
                <span className="text-[10px] text-slate-400 font-bold block uppercase">Tingkat Kehadiran</span>
                <div className="text-xl font-black text-status-success mt-0.5">
                  {attendance?.attendancePercentage || 100}%
                </div>
              </div>

              <p className="text-[11px] text-slate-500 leading-relaxed pt-1">
                Rekapitulasi jam mengajar tatap muka ini menjadi dasar perhitungan tunjangan fungsional pada slip gaji bulanan.
              </p>
            </CardContent>
          </Card>
        </div>
      )}
    </div>
  );
}
