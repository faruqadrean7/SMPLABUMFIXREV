'use client';

import React from 'react';
import Link from 'next/link';
import { useAuth } from '@/providers/auth-provider';
import { useStaffDashboard } from '@/features/portal/staf';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Avatar } from '@/components/ui/avatar';
import { LoadingSpinner } from '@/components/shared/loading-spinner';
import { ErrorState } from '@/components/shared/error-state';
import {
  Clock,
  Sparkles,
  CalendarDays,
  DollarSign,
  Wrench,
  CheckCircle2,
  AlertTriangle,
  ArrowRight,
  PlusCircle,
  FileText,
  Building,
} from 'lucide-react';

export default function StaffPortalDashboardPage() {
  const { user } = useAuth();
  const { data: dashboard, isLoading, isError, refetch } = useStaffDashboard();

  if (isLoading) {
    return (
      <div className="min-h-[50vh] flex items-center justify-center">
        <LoadingSpinner label="Memuat ringkasan portal tenaga kependidikan..." size="lg" />
      </div>
    );
  }

  if (isError || !dashboard) {
    return (
      <ErrorState
        title="Gagal Memuat Dashboard Staf"
        message="Terjadi kendala saat mengambil ringkasan data operasional kepegawaian."
        onRetry={() => refetch()}
      />
    );
  }

  const staffName = user?.profile?.name || user?.profile?.fullName || dashboard.staffName || 'Staf / Tata Usaha';
  const attendance = dashboard.todayAttendance;

  return (
    <div className="space-y-6">
      {/* Header Banner */}
      <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4 bg-gradient-to-r from-white via-slate-50 to-amber-50/40 p-6 rounded-2xl border border-slate-200 shadow-2xs">
        <div className="flex items-center gap-4">
          <Avatar
            src={(user?.profile?.photo_url as string) || (user?.profile?.avatarUrl as string)}
            name={staffName}
            size="xl"
            shape="rounded"
            alt={`Foto ${staffName}`}
            className="border-2 border-white shadow-xs shrink-0"
          />
          <div>
            <div className="inline-flex items-center gap-1.5 px-3 py-1 bg-amber-100/75 text-amber-800 rounded-full text-xs font-bold mb-2">
              <Sparkles className="h-3.5 w-3.5" />
              <span>Portal Tenaga Kependidikan & Tata Usaha</span>
            </div>
            <h1 className="text-2xl font-black text-slate-900 tracking-tight">
              Selamat Datang, {staffName}
            </h1>
            <p className="text-sm text-slate-600 mt-1">
              {dashboard.department} &bull; SMP Laboratorium Universitas Negeri Malang
            </p>
          </div>
        </div>

        <div className="flex flex-wrap items-center gap-2">
          <Link href="/portal/staf/presensi-saya">
            <Button size="sm" className="gap-2 bg-brand-blue hover:bg-blue-700 text-white font-bold">
              <Clock className="h-4 w-4" />
              <span>Presensi Mandiri</span>
            </Button>
          </Link>
          <Link href="/portal/staf/tiket">
            <Button variant="outline" size="sm" className="gap-2 border-slate-300 font-bold">
              <Wrench className="h-4 w-4 text-slate-600" />
              <span>Tiket Sarpras</span>
            </Button>
          </Link>
        </div>
      </div>

      {/* Metrics Grid */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {/* Presensi Hari Ini */}
        <Card className="border-slate-200 hover:shadow-xs transition-shadow">
          <CardHeader className="flex flex-row items-center justify-between pb-2">
            <span className="text-xs font-semibold text-slate-500 uppercase tracking-wider">
              Presensi Hari Ini
            </span>
            <div className="p-2 rounded-xl bg-blue-50 text-brand-blue">
              <Clock className="h-4 w-4" />
            </div>
          </CardHeader>
          <CardContent>
            <div className="flex items-center gap-2">
              <span className="text-2xl font-black text-slate-900">
                {attendance.checkInTime ? attendance.checkInTime.substring(0, 5) : '--:--'}
              </span>
              <Badge
                variant="outline"
                className={`text-[11px] font-bold ${
                  attendance.status === 'present'
                    ? 'bg-emerald-50 text-status-success border-emerald-200'
                    : 'bg-amber-50 text-amber-700 border-amber-200'
                }`}
              >
                {attendance.status === 'present' ? 'Sudah Masuk' : 'Belum Check-in'}
              </Badge>
            </div>
            <p className="text-xs text-slate-500 mt-2">
              Akumulasi: <strong className="text-slate-800">{attendance.workHoursThisMonth}</strong> / {attendance.targetWorkHours} Jam ({attendance.attendancePercentage}%)
            </p>
          </CardContent>
        </Card>

        {/* Izin & Cuti Pending */}
        <Card className="border-slate-200 hover:shadow-xs transition-shadow">
          <CardHeader className="flex flex-row items-center justify-between pb-2">
            <span className="text-xs font-semibold text-slate-500 uppercase tracking-wider">
              Pengajuan Izin
            </span>
            <div className="p-2 rounded-xl bg-amber-50 text-amber-600">
              <CalendarDays className="h-4 w-4" />
            </div>
          </CardHeader>
          <CardContent>
            <div className="flex items-center gap-2">
              <span className="text-2xl font-black text-slate-900">
                {dashboard.pendingLeavesCount}
              </span>
              <span className="text-xs text-slate-500">menunggu review</span>
            </div>
            <p className="text-xs text-slate-500 mt-2 flex items-center gap-1">
              <Link href="/portal/staf/izin-cuti" className="text-brand-blue font-bold hover:underline inline-flex items-center gap-1">
                <span>Kelola permohonan</span>
                <ArrowRight className="h-3 w-3" />
              </Link>
            </p>
          </CardContent>
        </Card>

        {/* Tiket Sarpras Terbuka */}
        <Card className="border-slate-200 hover:shadow-xs transition-shadow">
          <CardHeader className="flex flex-row items-center justify-between pb-2">
            <span className="text-xs font-semibold text-slate-500 uppercase tracking-wider">
              Tiket Sarpras Aktif
            </span>
            <div className="p-2 rounded-xl bg-purple-50 text-purple-600">
              <Wrench className="h-4 w-4" />
            </div>
          </CardHeader>
          <CardContent>
            <div className="flex items-center gap-2">
              <span className="text-2xl font-black text-slate-900">
                {dashboard.openTicketsCount}
              </span>
              <span className="text-xs text-slate-500">aduan diproses</span>
            </div>
            <p className="text-xs text-slate-500 mt-2">
              <strong className="text-status-success">{dashboard.resolvedTicketsCount}</strong> tiket telah selesai
            </p>
          </CardContent>
        </Card>

        {/* Slip Gaji Terakhir */}
        <Card className="border-slate-200 hover:shadow-xs transition-shadow">
          <CardHeader className="flex flex-row items-center justify-between pb-2">
            <span className="text-xs font-semibold text-slate-500 uppercase tracking-wider">
              Slip Gaji
            </span>
            <div className="p-2 rounded-xl bg-emerald-50 text-status-success">
              <DollarSign className="h-4 w-4" />
            </div>
          </CardHeader>
          <CardContent>
            <div className="flex items-center gap-2">
              <span className="text-sm font-bold text-slate-900">
                Oktober 2026
              </span>
              <Badge variant="outline" className="bg-emerald-50 text-status-success border-emerald-200 text-[10px]">
                Tersedia
              </Badge>
            </div>
            <p className="text-xs text-slate-500 mt-2">
              <Link href="/portal/staf/slip-gaji" className="text-brand-blue font-bold hover:underline inline-flex items-center gap-1">
                <span>Unduh slip resmi</span>
                <ArrowRight className="h-3 w-3" />
              </Link>
            </p>
          </CardContent>
        </Card>
      </div>

      {/* Main Content Sections: Aksi Cepat & Pengumuman */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Aksi Cepat Navigasi */}
        <div className="lg:col-span-2 space-y-4">
          <div className="flex items-center justify-between">
            <h2 className="text-base font-bold text-slate-900 flex items-center gap-2">
              <Building className="h-4 w-4 text-brand-blue" />
              <span>Aksi Layanan Tata Usaha & Sarpras</span>
            </h2>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <Link href="/portal/staf/presensi-saya" className="group">
              <Card className="border-slate-200 p-4 hover:border-brand-blue hover:shadow-sm transition-all h-full flex flex-col justify-between">
                <div className="flex items-start gap-3">
                  <div className="p-2.5 rounded-xl bg-blue-50 text-brand-blue group-hover:scale-105 transition-transform">
                    <Clock className="h-5 w-5" />
                  </div>
                  <div>
                    <h3 className="font-bold text-sm text-slate-900 group-hover:text-brand-blue transition-colors">
                      Presensi Kerja Mandiri
                    </h3>
                    <p className="text-xs text-slate-500 mt-1 leading-relaxed">
                      Catat kehadiran jam datang dan pulang kantor secara digital.
                    </p>
                  </div>
                </div>
                <div className="mt-3 flex items-center text-xs font-bold text-brand-blue gap-1">
                  <span>Buka Presensi</span>
                  <ArrowRight className="h-3 w-3" />
                </div>
              </Card>
            </Link>

            <Link href="/portal/staf/izin-cuti" className="group">
              <Card className="border-slate-200 p-4 hover:border-brand-blue hover:shadow-sm transition-all h-full flex flex-col justify-between">
                <div className="flex items-start gap-3">
                  <div className="p-2.5 rounded-xl bg-amber-50 text-amber-600 group-hover:scale-105 transition-transform">
                    <CalendarDays className="h-5 w-5" />
                  </div>
                  <div>
                    <h3 className="font-bold text-sm text-slate-900 group-hover:text-brand-blue transition-colors">
                      Pengajuan Izin & Cuti
                    </h3>
                    <p className="text-xs text-slate-500 mt-1 leading-relaxed">
                      Ajukan surat izin sakit, cuti tahunan, atau tugas dinas luar kantor.
                    </p>
                  </div>
                </div>
                <div className="mt-3 flex items-center text-xs font-bold text-brand-blue gap-1">
                  <span>Ajukan Izin</span>
                  <ArrowRight className="h-3 w-3" />
                </div>
              </Card>
            </Link>

            <Link href="/portal/staf/tiket" className="group">
              <Card className="border-slate-200 p-4 hover:border-brand-blue hover:shadow-sm transition-all h-full flex flex-col justify-between">
                <div className="flex items-start gap-3">
                  <div className="p-2.5 rounded-xl bg-purple-50 text-purple-600 group-hover:scale-105 transition-transform">
                    <Wrench className="h-5 w-5" />
                  </div>
                  <div>
                    <h3 className="font-bold text-sm text-slate-900 group-hover:text-brand-blue transition-colors">
                      Tiket Aduan Sarpras
                    </h3>
                    <p className="text-xs text-slate-500 mt-1 leading-relaxed">
                      Lapor kerusakan fasilitas, AC, proyektor, dan sarana laboratorium.
                    </p>
                  </div>
                </div>
                <div className="mt-3 flex items-center text-xs font-bold text-brand-blue gap-1">
                  <span>Lapor Sarpras</span>
                  <ArrowRight className="h-3 w-3" />
                </div>
              </Card>
            </Link>

            <Link href="/portal/staf/slip-gaji" className="group">
              <Card className="border-slate-200 p-4 hover:border-brand-blue hover:shadow-sm transition-all h-full flex flex-col justify-between">
                <div className="flex items-start gap-3">
                  <div className="p-2.5 rounded-xl bg-emerald-50 text-status-success group-hover:scale-105 transition-transform">
                    <DollarSign className="h-5 w-5" />
                  </div>
                  <div>
                    <h3 className="font-bold text-sm text-slate-900 group-hover:text-brand-blue transition-colors">
                      Slip Gaji & Kompensasi
                    </h3>
                    <p className="text-xs text-slate-500 mt-1 leading-relaxed">
                      Lihat rincian gaji pokok, tunjangan operasional, dan unduh PDF resmi.
                    </p>
                  </div>
                </div>
                <div className="mt-3 flex items-center text-xs font-bold text-brand-blue gap-1">
                  <span>Lihat Slip Gaji</span>
                  <ArrowRight className="h-3 w-3" />
                </div>
              </Card>
            </Link>
          </div>
        </div>

        {/* Pengumuman & Informasi Terkini */}
        <div className="space-y-4">
          <div className="flex items-center justify-between">
            <h2 className="text-base font-bold text-slate-900 flex items-center gap-2">
              <FileText className="h-4 w-4 text-brand-blue" />
              <span>Pengumuman Internal</span>
            </h2>
          </div>

          <Card className="border-slate-200">
            <CardContent className="p-4 divide-y divide-slate-100">
              {dashboard.recentAnnouncements.map((item) => (
                <div key={item.id} className="py-3 first:pt-0 last:pb-0">
                  <div className="flex items-center justify-between gap-2 mb-1">
                    <Badge variant="outline" className="text-[10px] bg-slate-50 text-slate-600 border-slate-200">
                      {item.category}
                    </Badge>
                    <span className="text-[11px] text-slate-400 font-medium">{item.date}</span>
                  </div>
                  <h4 className="text-xs font-bold text-slate-800 leading-snug">
                    {item.title}
                  </h4>
                </div>
              ))}
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  );
}
