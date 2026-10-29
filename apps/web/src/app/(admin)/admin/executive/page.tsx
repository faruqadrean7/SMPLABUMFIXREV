'use client';

import React from 'react';
import Link from 'next/link';
import { useAuth } from '@/providers/auth-provider';
import { useExecutiveOverview } from '@/features/executive';
import { hasPermission, isReadOnlyRole } from '@/lib/rbac/evaluator';
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
  Layers,
  Sparkles,
  Shield,
} from 'lucide-react';

export default function ExecutiveDashboardPage() {
  const { user } = useAuth();
  const { data: metrics, isLoading, isError, refetch } = useExecutiveOverview();

  if (isLoading) {
    return (
      <div className="min-h-[50vh] flex items-center justify-center">
        <LoadingSpinner label="Memuat ringkasan eksekutif pimpinan..." size="lg" />
      </div>
    );
  }

  if (isError || !metrics) {
    return (
      <ErrorState
        title="Gagal Memuat Dashboard Eksekutif"
        message="Terjadi kendala saat mengambil ringkasan indikator kinerja sekolah."
        onRetry={() => refetch()}
      />
    );
  }

  const isReadOnly = isReadOnlyRole(user?.roles);
  const canManageApprovals = hasPermission(user, 'executive.approval.manage') && !isReadOnly;
  const canViewCounseling = hasPermission(user, 'counseling.case.read') && !isReadOnly;

  const principalName =
    user?.profile?.name ||
    user?.profile?.fullName ||
    user?.username ||
    (isReadOnly ? 'Pengawas / Komite' : 'Pimpinan Sekolah');

  const ppdbPercentage = Math.round((metrics.ppdbEnrolledCount / metrics.ppdbTargetQuota) * 100);

  return (
    <div className="space-y-6">
      {/* Header Eksekutif */}
      <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4 bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 text-white p-6 rounded-2xl shadow-sm">
        <div>
          <div className="inline-flex items-center gap-1.5 px-3 py-1 bg-white/10 text-amber-300 rounded-full text-xs font-bold mb-2 backdrop-blur-xs">
            <Sparkles className="h-3.5 w-3.5" />
            <span>{isReadOnly ? 'Panel Supervisi Eksternal (Pengawas & Komite)' : 'Panel Pimpinan (Kepala Sekolah & Wakasek)'}</span>
          </div>
          <h1 className="text-2xl font-black tracking-tight text-white">
            Executive Summary: {principalName}
          </h1>
          <p className="text-xs sm:text-sm text-slate-300 mt-1">
            SMP Laboratorium Universitas Negeri Malang • Tahun Ajaran 2026/2027
          </p>
        </div>

        <div className="flex flex-wrap items-center gap-2">
          {canManageApprovals ? (
            <Link href="/admin/executive/approvals">
              <Button size="sm" className="gap-2 bg-amber-400 hover:bg-amber-500 text-slate-950 font-black shadow-xs">
                <CheckSquare className="h-4 w-4" />
                <span>Antrean Persetujuan ({metrics.pendingApprovalsCount})</span>
              </Button>
            </Link>
          ) : isReadOnly ? (
            <div className="flex items-center gap-1.5 px-3 py-1.5 bg-white/10 text-amber-300 border border-white/20 rounded-lg text-xs font-bold">
              <Shield className="h-3.5 w-3.5" />
              <span>Mode Supervisi (Baca-Saja)</span>
            </div>
          ) : null}
          <Link href="/admin/reports">
            <Button variant="outline" size="sm" className="gap-2 border-white/20 text-white hover:bg-white/10 font-bold">
              <TrendingUp className="h-4 w-4" />
              <span>Pusat Laporan</span>
            </Button>
          </Link>
        </div>
      </div>

      {/* KPI Cards: Mobile-first & Ringkas */}
      <div className="grid grid-cols-2 lg:grid-cols-6 gap-3 sm:gap-4">
        {/* Presensi Siswa */}
        <Card className="border-slate-200 p-4">
          <span className="text-[11px] font-bold text-slate-400 block uppercase tracking-wider">
            Presensi Siswa
          </span>
          <div className="text-2xl font-black text-slate-900 mt-1">
            {metrics.studentAttendanceRate}%
          </div>
          <span className="text-[11px] text-slate-500 mt-1 block">
            {metrics.totalStudents} Siswa Aktif
          </span>
        </Card>

        {/* Presensi Guru */}
        <Card className="border-slate-200 p-4">
          <span className="text-[11px] font-bold text-slate-400 block uppercase tracking-wider">
            Presensi Guru
          </span>
          <div className="text-2xl font-black text-slate-900 mt-1">
            {metrics.teacherAttendanceRate}%
          </div>
          <span className="text-[11px] text-slate-500 mt-1 block">
            {metrics.totalTeachers} Tenaga Pendidik
          </span>
        </Card>

        {/* Early Warning BK */}
        <Card className="border-slate-200 p-4 hover:border-amber-300 transition-colors">
          {canViewCounseling ? (
            <Link href="/admin/counseling">
              <span className="text-[11px] font-bold text-slate-400 block uppercase tracking-wider">
                Early Warning BK
              </span>
              <div className="text-2xl font-black text-amber-600 mt-1 flex items-center gap-1.5">
                <span>{metrics.activeEarlyWarningsCount}</span>
                <AlertTriangle className="h-4 w-4" />
              </div>
              <span className="text-[11px] text-brand-blue font-bold mt-1 block">
                Lihat Kasus &rarr;
              </span>
            </Link>
          ) : (
            <div>
              <span className="text-[11px] font-bold text-slate-400 block uppercase tracking-wider">
                Early Warning BK
              </span>
              <div className="text-2xl font-black text-amber-600 mt-1 flex items-center gap-1.5">
                <span>{metrics.activeEarlyWarningsCount}</span>
                <AlertTriangle className="h-4 w-4" />
              </div>
              <span className="text-[11px] text-slate-400 font-medium mt-1 block">
                Akses Khusus Guru BK & Pimpinan
              </span>
            </div>
          )}
        </Card>

        {/* PPDB Terisi */}
        <Card className="border-slate-200 p-4">
          <span className="text-[11px] font-bold text-slate-400 block uppercase tracking-wider">
            PPDB Terisi
          </span>
          <div className="text-2xl font-black text-slate-900 mt-1">
            {ppdbPercentage}%
          </div>
          <span className="text-[11px] text-slate-500 mt-1 block">
            {metrics.ppdbEnrolledCount} / {metrics.ppdbTargetQuota} Kursi
          </span>
        </Card>

        {/* Tiket Sarpras Kritis */}
        <Card className="border-slate-200 p-4 hover:border-purple-300 transition-colors">
          <Link href="/admin/tickets">
            <span className="text-[11px] font-bold text-slate-400 block uppercase tracking-wider">
              Tiket Sarpras
            </span>
            <div className="text-2xl font-black text-purple-700 mt-1 flex items-center gap-1.5">
              <span>{metrics.openTicketsCount}</span>
              <Wrench className="h-4 w-4" />
            </div>
            <span className="text-[11px] text-brand-blue font-bold mt-1 block">
              Kelola Sarpras &rarr;
            </span>
          </Link>
        </Card>

        {/* Persetujuan Pending */}
        <Card className="border-slate-200 p-4 bg-amber-50/50 hover:bg-amber-50 border-amber-200 transition-colors">
          {canManageApprovals ? (
            <Link href="/admin/executive/approvals">
              <span className="text-[11px] font-bold text-amber-800 block uppercase tracking-wider">
                Butuh Approval
              </span>
              <div className="text-2xl font-black text-amber-800 mt-1 flex items-center gap-1.5">
                <span>{metrics.pendingApprovalsCount}</span>
                <CheckSquare className="h-4 w-4" />
              </div>
              <span className="text-[11px] text-amber-900 font-black mt-1 block">
                Tinjau Sekarang &rarr;
              </span>
            </Link>
          ) : (
            <div>
              <span className="text-[11px] font-bold text-amber-800 block uppercase tracking-wider">
                Antrean Approval
              </span>
              <div className="text-2xl font-black text-amber-800 mt-1 flex items-center gap-1.5">
                <span>{metrics.pendingApprovalsCount}</span>
                <CheckSquare className="h-4 w-4" />
              </div>
              <span className="text-[11px] text-slate-500 font-medium mt-1 block">
                Dalam Proses Pimpinan
              </span>
            </div>
          )}
        </Card>
      </div>

      {/* Sebaran Nilai Per Jenjang & Status Terkini */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Sebaran Nilai */}
        <Card className="border-slate-200 lg:col-span-2">
          <CardHeader className="pb-3">
            <CardTitle className="text-base font-bold text-slate-900 flex items-center gap-2">
              <Award className="h-5 w-5 text-brand-blue" />
              <span>Rata-Rata Capaian Nilai Akademik per Jenjang</span>
            </CardTitle>
            <CardDescription className="text-xs text-slate-500">
              Evaluasi agregat penilaian formatif, sumatif, dan PTS semester berjalan
            </CardDescription>
          </CardHeader>

          <CardContent className="space-y-4">
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
              {(metrics.gradeLevelAverages || []).map((item) => (
                <div key={item.level} className="p-4 rounded-xl bg-slate-50 border border-slate-200">
                  <div className="flex items-center justify-between mb-2">
                    <span className="text-xs font-bold text-slate-600">{item.level}</span>
                    <Badge variant="outline" className="text-[10px] bg-white border-slate-200">
                      {item.totalStudents} Siswa
                    </Badge>
                  </div>
                  <div className="text-3xl font-black text-slate-900">
                    {item.averageScore}
                  </div>
                  <div className="mt-2 text-[11px] text-emerald-600 font-bold flex items-center gap-1">
                    <TrendingUp className="h-3 w-3" />
                    <span>Di atas batas KKM (75.0)</span>
                  </div>
                </div>
              ))}
            </div>

            <div className="p-4 rounded-xl bg-blue-50/60 border border-blue-100 flex items-center justify-between">
              <div>
                <h4 className="text-xs font-bold text-brand-blue">
                  Pusat Laporan Terpadu & Rekapitulasi Rapor
                </h4>
                <p className="text-[11px] text-slate-600 mt-0.5">
                  Unduh cetak buku rapor, rekap kehadiran bulanan, dan jurnal mengajar guru.
                </p>
              </div>
              <Link href="/admin/academic/report-cards">
                <Button size="sm" variant="outline" className="border-blue-200 text-brand-blue font-bold text-xs">
                  Cek E-Rapor
                </Button>
              </Link>
            </div>
          </CardContent>
        </Card>

        {/* Peringatan & Insiden Operasional Terkini */}
        <Card className="border-slate-200">
          <CardHeader className="pb-3">
            <CardTitle className="text-base font-bold text-slate-900 flex items-center gap-2">
              <ShieldAlert className="h-5 w-5 text-amber-600" />
              <span>Atensi Operasional Pimpinan</span>
            </CardTitle>
            <CardDescription className="text-xs text-slate-500">
              Notifikasi kritis yang butuh koordinasi kepsek & wakasek
            </CardDescription>
          </CardHeader>

          <CardContent className="space-y-3">
            {(metrics.recentIncidents || []).map((incident) => (
              <div key={incident.id} className="p-3 rounded-xl border border-slate-200 bg-white space-y-1">
                <div className="flex items-center justify-between">
                  <Badge
                    variant="outline"
                    className={`text-[10px] font-bold ${
                      incident.severity === 'high'
                        ? 'bg-red-50 text-status-danger border-red-200'
                        : 'bg-amber-50 text-amber-800 border-amber-200'
                    }`}
                  >
                    {incident.severity === 'high' ? 'Prioritas Tinggi' : 'Perhatian'}
                  </Badge>
                  <span className="text-[10px] text-slate-400">{incident.timestamp}</span>
                </div>
                <p className="text-xs font-semibold text-slate-800 leading-snug">
                  {incident.title}
                </p>
              </div>
            ))}

            {canManageApprovals ? (
              <Link href="/admin/executive/approvals" className="block pt-2">
                <Button className="w-full bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs gap-1.5">
                  <CheckSquare className="h-4 w-4 text-amber-400" />
                  <span>Buka Seluruh Antrean Persetujuan</span>
                </Button>
              </Link>
            ) : (
              <Link href="/admin/reports" className="block pt-2">
                <Button variant="outline" className="w-full border-slate-200 text-slate-700 hover:bg-slate-50 font-bold text-xs gap-1.5">
                  <TrendingUp className="h-4 w-4 text-brand-blue" />
                  <span>Buka Pusat Laporan Terpadu</span>
                </Button>
              </Link>
            )}
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
