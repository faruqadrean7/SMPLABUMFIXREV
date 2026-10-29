'use client';

import React from 'react';
import Link from 'next/link';
import { useAuth } from '@/providers/auth-provider';
import { useExecutiveOverview } from '@/features/executive';
import { hasPermission } from '@/lib/rbac/evaluator';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { LoadingSpinner } from '@/components/shared/loading-spinner';
import { ErrorState } from '@/components/shared/error-state';
import {
  Users,
  GraduationCap,
  CalendarCheck,
  AlertTriangle,
  Wrench,
  CheckSquare,
  Award,
  ArrowRight,
  TrendingUp,
  Building,
  ShieldAlert,
  Sparkles,
  BarChart3,
  Clock,
} from 'lucide-react';

export default function KepsekDashboardPage() {
  const { user } = useAuth();
  const { data: metrics, isLoading, isError, refetch } = useExecutiveOverview();

  if (isLoading) {
    return (
      <div className="min-h-[50vh] flex items-center justify-center">
        <LoadingSpinner label="Memuat ringkasan indikator kinerja pimpinan..." size="lg" />
      </div>
    );
  }

  if (isError || !metrics) {
    return (
      <ErrorState
        title="Gagal Memuat Dashboard Pimpinan"
        message="Terjadi kendala saat mengambil ringkasan indikator kinerja sekolah dari server."
        onRetry={() => refetch()}
      />
    );
  }

  const canManageApprovals = hasPermission(user, 'executive.approval.manage');
  const canViewReports = hasPermission(user, 'reports.view');

  const leaderName =
    user?.profile?.name ||
    user?.profile?.fullName ||
    user?.username ||
    'Pimpinan Sekolah';

  const isPrincipal = user?.roles?.includes('principal');
  const leaderRoleTitle = isPrincipal
    ? 'Kepala Sekolah'
    : user?.roles?.includes('vice_principal')
      ? 'Wakil Kepala Sekolah'
      : 'Pimpinan Eksekutif';

  const ppdbQuota = metrics.ppdbTargetQuota > 0 ? metrics.ppdbTargetQuota : 240;
  const ppdbPercentage = Math.min(
    100,
    Math.round((metrics.ppdbEnrolledCount / ppdbQuota) * 100)
  );

  return (
    <div className="space-y-6">
      {/* Banner Identitas Pimpinan */}
      <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4 bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 text-white p-6 rounded-2xl shadow-sm">
        <div>
          <div className="inline-flex items-center gap-1.5 px-3 py-1 bg-white/10 text-amber-300 rounded-full text-xs font-bold mb-2 backdrop-blur-xs">
            <Sparkles className="h-3.5 w-3.5" />
            <span>Portal Pimpinan Eksekutif • {leaderRoleTitle}</span>
          </div>
          <h1 className="text-2xl font-black tracking-tight text-white">
            Selamat Datang, {leaderName}
          </h1>
          <p className="text-xs sm:text-sm text-slate-300 mt-1">
            SMP Laboratorium Universitas Negeri Malang • Tahun Ajaran 2026/2027
          </p>
        </div>

        <div className="flex flex-wrap items-center gap-2">
          {canManageApprovals && (
            <Link href="/portal/kepsek/approvals">
              <Button size="sm" className="gap-2 bg-amber-400 hover:bg-amber-500 text-slate-950 font-black shadow-xs">
                <CheckSquare className="h-4 w-4" />
                <span>Antrean Persetujuan ({metrics.pendingApprovalsCount})</span>
              </Button>
            </Link>
          )}
          {canViewReports && (
            <Link href="/admin/reports">
              <Button variant="outline" size="sm" className="gap-2 border-white/20 text-white hover:bg-white/10 font-bold">
                <TrendingUp className="h-4 w-4" />
                <span>Pusat Laporan</span>
              </Button>
            </Link>
          )}
        </div>
      </div>

      {/* Grid Indikator Kunci (KPI Cards) */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {/* Kehadiran Siswa */}
        <Card className="border-slate-200">
          <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
            <CardTitle className="text-xs font-bold text-slate-500 uppercase tracking-wider">
              Kehadiran Siswa Hari Ini
            </CardTitle>
            <CalendarCheck className="h-4 w-4 text-emerald-600" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-black text-slate-900">
              {metrics.studentAttendanceRate}%
            </div>
            <p className="text-xs text-slate-500 mt-1 flex items-center gap-1">
              <span className="font-semibold text-emerald-600">Aktif</span> dari total {metrics.totalStudents} siswa terdaftar
            </p>
          </CardContent>
        </Card>

        {/* Kehadiran Guru */}
        <Card className="border-slate-200">
          <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
            <CardTitle className="text-xs font-bold text-slate-500 uppercase tracking-wider">
              Kehadiran Tenaga Pendidik
            </CardTitle>
            <GraduationCap className="h-4 w-4 text-blue-600" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-black text-slate-900">
              {metrics.teacherAttendanceRate}%
            </div>
            <p className="text-xs text-slate-500 mt-1 flex items-center gap-1">
              <span className="font-semibold text-blue-600">Presensi KBM</span> dari {metrics.totalTeachers} dewan guru
            </p>
          </CardContent>
        </Card>

        {/* Peringatan Dini Akademik & Disiplin */}
        <Card className="border-slate-200">
          <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
            <CardTitle className="text-xs font-bold text-slate-500 uppercase tracking-wider">
              Peringatan Dini Siswa
            </CardTitle>
            <AlertTriangle className="h-4 w-4 text-amber-500" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-black text-slate-900">
              {metrics.activeEarlyWarningsCount}
            </div>
            <p className="text-xs text-slate-500 mt-1">
              Siswa memerlukan atensi absensi/akademik
            </p>
          </CardContent>
        </Card>

        {/* Tiket Fasilitas Terbuka */}
        <Card className="border-slate-200">
          <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
            <CardTitle className="text-xs font-bold text-slate-500 uppercase tracking-wider">
              Tiket Sarpras / Helpdesk
            </CardTitle>
            <Wrench className="h-4 w-4 text-indigo-600" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-black text-slate-900">
              {metrics.openTicketsCount}
            </div>
            <p className="text-xs text-slate-500 mt-1">
              Perbaikan fasilitas dalam penanganan
            </p>
          </CardContent>
        </Card>
      </div>

      {/* Baris Kedua: Capaian PPDB & Nilai Rata-rata per Jenjang */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Capaian PPDB */}
        <Card className="border-slate-200">
          <CardHeader>
            <div className="flex items-center justify-between">
              <CardTitle className="text-base font-bold text-slate-900">Target PPDB 2026</CardTitle>
              <Badge variant="outline" className="text-xs font-bold text-brand-blue border-blue-200 bg-blue-50">
                {ppdbPercentage}% Tercapai
              </Badge>
            </div>
            <CardDescription className="text-xs">
              Perkembangan penerimaan peserta didik baru tahun ajaran 2026/2027
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="flex items-baseline justify-between text-sm">
              <span className="text-slate-600 font-medium">Siswa Diterima & Terdaftar:</span>
              <span className="font-black text-slate-900 text-lg">
                {metrics.ppdbEnrolledCount} <span className="text-xs text-slate-500 font-normal">/ {ppdbQuota} Kuota</span>
              </span>
            </div>
            <div className="w-full bg-slate-100 rounded-full h-3 overflow-hidden">
              <div
                className="bg-brand-blue h-full rounded-full transition-all duration-500"
                style={{ width: `${ppdbPercentage}%` }}
              />
            </div>
            <p className="text-xs text-slate-500">
              Seleksi dan verifikasi berkas dilakukan secara objektif oleh Panitia PPDB sekolah.
            </p>
          </CardContent>
        </Card>

        {/* Rata-rata Capaian Akademik per Jenjang */}
        <Card className="border-slate-200 lg:col-span-2">
          <CardHeader>
            <div className="flex items-center justify-between">
              <div>
                <CardTitle className="text-base font-bold text-slate-900">Capaian Akademik per Jenjang</CardTitle>
                <CardDescription className="text-xs">
                  Nilai rata-rata formatif dan sumatif seluruh siswa aktif
                </CardDescription>
              </div>
              <BarChart3 className="h-5 w-5 text-slate-400" />
            </div>
          </CardHeader>
          <CardContent>
            <div className="space-y-4">
              {metrics.gradeLevelAverages && metrics.gradeLevelAverages.length > 0 ? (
                metrics.gradeLevelAverages.map((grade) => {
                  const score = Number(grade.averageScore) || 0;
                  return (
                    <div key={grade.level} className="space-y-1.5">
                      <div className="flex justify-between text-xs font-bold text-slate-700">
                        <span>{grade.level} ({grade.totalStudents} siswa)</span>
                        <span className="text-brand-blue">{score.toFixed(1)} / 100</span>
                      </div>
                      <div className="w-full bg-slate-100 rounded-full h-2.5 overflow-hidden">
                        <div
                          className="bg-indigo-600 h-full rounded-full transition-all duration-500"
                          style={{ width: `${Math.min(100, score)}%` }}
                        />
                      </div>
                    </div>
                  );
                })
              ) : (
                <p className="text-xs text-slate-500 italic py-4 text-center">
                  Data rata-rata nilai semester belum tersedia.
                </p>
              )}
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Baris Ketiga: Insiden / Kejadian Sekolah & Antrean Pengesahan */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Antrean Pengesahan Cepat */}
        <Card className="border-slate-200">
          <CardHeader className="flex flex-row items-center justify-between pb-3">
            <div>
              <CardTitle className="text-base font-bold text-slate-900">Antrean Persetujuan</CardTitle>
              <CardDescription className="text-xs">
                Dokumen dan permohonan yang menunggu persetujuan pimpinan
              </CardDescription>
            </div>
            <Link href="/portal/kepsek/approvals">
              <Button variant="ghost" size="sm" className="gap-1 text-xs text-brand-blue hover:text-blue-700">
                <span>Lihat Semua</span>
                <ArrowRight className="h-3.5 w-3.5" />
              </Button>
            </Link>
          </CardHeader>
          <CardContent>
            {metrics.pendingApprovalsCount > 0 ? (
              <div className="p-4 rounded-xl bg-amber-50/80 border border-amber-200/80 flex items-start gap-3">
                <CheckSquare className="h-5 w-5 text-amber-600 shrink-0 mt-0.5" />
                <div className="flex-1">
                  <h4 className="text-sm font-bold text-amber-900">
                    Ada {metrics.pendingApprovalsCount} Pengajuan Menunggu Tinjauan
                  </h4>
                  <p className="text-xs text-amber-750 mt-0.5">
                    Mencakup pengesahan rapor, izin cuti guru/staf, dan permohonan logistik sekolah.
                  </p>
                  <Link href="/portal/kepsek/approvals" className="inline-block mt-3">
                    <Button size="sm" className="bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs">
                      Tinjau Sekarang
                    </Button>
                  </Link>
                </div>
              </div>
            ) : (
              <div className="p-6 text-center text-slate-500 bg-slate-50 rounded-xl border border-slate-100">
                <CheckSquare className="h-8 w-8 text-emerald-500 mx-auto mb-2 opacity-80" />
                <p className="text-xs font-semibold text-slate-700">Tidak ada pengajuan tertunda</p>
                <p className="text-[11px] text-slate-400 mt-0.5">Seluruh berkas administrasi telah diverifikasi.</p>
              </div>
            )}
          </CardContent>
        </Card>

        {/* Ringkasan Kejadian / Catatan Peringatan */}
        <Card className="border-slate-200">
          <CardHeader>
            <CardTitle className="text-base font-bold text-slate-900">Laporan Kejadian & Catatan</CardTitle>
            <CardDescription className="text-xs">
              Monitoring catatan insiden dan penanganan kedisiplinan terkini
            </CardDescription>
          </CardHeader>
          <CardContent>
            {metrics.recentIncidents && metrics.recentIncidents.length > 0 ? (
              <div className="space-y-3">
                {metrics.recentIncidents.map((inc) => (
                  <div
                    key={inc.id}
                    className="flex items-center justify-between p-3 rounded-lg border border-slate-100 bg-slate-50/50 hover:bg-slate-50 transition-colors"
                  >
                    <div className="flex items-center gap-3">
                      <ShieldAlert
                        className={`h-4 w-4 ${
                          inc.severity === 'high'
                            ? 'text-red-500'
                            : inc.severity === 'medium'
                              ? 'text-amber-500'
                              : 'text-blue-500'
                        }`}
                      />
                      <div>
                        <p className="text-xs font-bold text-slate-900 leading-tight">{inc.title}</p>
                        <p className="text-[10px] text-slate-500 mt-0.5 flex items-center gap-1">
                          <Clock className="h-3 w-3" />
                          <span>{inc.timestamp}</span>
                        </p>
                      </div>
                    </div>
                    <Badge
                      variant="outline"
                      className={`text-[10px] font-bold capitalize ${
                        inc.severity === 'high'
                          ? 'border-red-200 text-red-700 bg-red-50'
                          : inc.severity === 'medium'
                            ? 'border-amber-200 text-amber-700 bg-amber-50'
                            : 'border-blue-200 text-blue-700 bg-blue-50'
                      }`}
                    >
                      {inc.severity}
                    </Badge>
                  </div>
                ))}
              </div>
            ) : (
              <div className="p-6 text-center text-slate-500 bg-slate-50 rounded-xl border border-slate-100">
                <p className="text-xs text-slate-400">Tidak ada catatan insiden aktif pada periode ini.</p>
              </div>
            )}
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
