'use client';

import React, { useEffect, useState, useCallback } from 'react';
import Link from 'next/link';
import { useAuth } from '@/providers/auth-provider';
import { isReadOnlyRole } from '@/lib/rbac/evaluator';
import { reportsApi } from '@/features/reports/reports-api';
import { ExecutiveDashboardData, GradeLevelAverage } from '@/features/reports/types';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  Users,
  GraduationCap,
  CalendarCheck,
  BookOpen,
  ArrowRight,
  ShieldCheck,
  Activity,
  Layers,
  RefreshCw,
  AlertTriangle,
  HeartHandshake,
  Wrench,
  MonitorCheck,
  CheckCircle2,
  FileBarChart,
  Clock,
  Building2,
} from 'lucide-react';

export default function AdminDashboardPage() {
  const { user } = useAuth();
  const [data, setData] = useState<ExecutiveDashboardData | null>(null);
  const [loading, setLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);

  const isReadOnly = isReadOnlyRole(user?.roles);

  const displayName =
    user?.profile?.name ||
    user?.profile?.fullName ||
    user?.username ||
    (isReadOnly ? 'Pengawas / Komite' : 'Administrator');

  const userRoles = (user?.roles as string[]) || [];

  const loadDashboard = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await reportsApi.getExecutiveDashboard();
      if (res.data) {
        setData(res.data);
      } else {
        setError('Data dashboard tidak berhasil dimuat.');
      }
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Gagal memuat metrik dashboard eksekutif.';
      setError(msg);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    loadDashboard();
  }, [loadDashboard]);

  // Loading Skeleton State
  if (loading) {
    return (
      <div className="space-y-6 animate-pulse" data-testid="dashboard-loading">
        <div className="h-32 rounded-xl bg-slate-200" />
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <div className="h-28 rounded-xl bg-slate-200" />
          <div className="h-28 rounded-xl bg-slate-200" />
          <div className="h-28 rounded-xl bg-slate-200" />
          <div className="h-28 rounded-xl bg-slate-200" />
        </div>
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <div className="h-64 rounded-xl bg-slate-200" />
          <div className="h-64 rounded-xl bg-slate-200" />
          <div className="h-64 rounded-xl bg-slate-200" />
        </div>
      </div>
    );
  }

  // Error State
  if (error && !data) {
    return (
      <div className="p-8 text-center bg-white rounded-xl border border-red-200 shadow-sm" data-testid="dashboard-error">
        <AlertTriangle className="h-12 w-12 text-red-500 mx-auto mb-3" />
        <h3 className="text-lg font-bold text-slate-800">Gagal Mengambil Data Eksekutif</h3>
        <p className="text-sm text-slate-600 max-w-md mx-auto mt-1 mb-4">{error}</p>
        <Button onClick={loadDashboard} variant="default" className="bg-brand-blue hover:bg-blue-800 text-white">
          <RefreshCw className="h-4 w-4 mr-2" />
          Coba Lagi
        </Button>
      </div>
    );
  }

  const cbt = data?.cbt_live;
  const att = data?.attendance_today;
  const syllabus = data?.syllabus_completion;
  const grading = data?.teacher_grading_completion;
  const rawGradesByLevel = data?.grades_by_grade_level;
  const gradesByLevel: GradeLevelAverage[] = Array.isArray(rawGradesByLevel)
    ? rawGradesByLevel
    : (rawGradesByLevel && typeof rawGradesByLevel === 'object' && !('__PHP_Incomplete_Class_Name' in (rawGradesByLevel as object))
        ? (Object.values(rawGradesByLevel) as GradeLevelAverage[])
        : []);
  const affairs = data?.student_affairs_follow_up;
  const infra = data?.infrastructure_status;
  const master = data?.master_summary;

  return (
    <div className="space-y-6" data-testid="dashboard-executive-view">
      {/* Banner Selamat Datang & Status Eksekutif */}
      <div className="rounded-xl bg-gradient-to-r from-brand-blue to-blue-900 p-6 text-white shadow-elevated">
        <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
          <div>
            <div className="flex items-center gap-2 mb-1">
              <Badge variant="outline" className="text-white border-white/30 bg-white/10 text-xs">
                Portal Manajemen Terpadu
              </Badge>
              <span className="text-xs text-blue-200">•</span>
              <span className="text-xs text-blue-200">SISTLABUM Executive</span>
            </div>
            <h1 className="text-2xl font-bold tracking-tight">Selamat Datang, {displayName}!</h1>
            <p className="mt-1 text-sm text-blue-100 max-w-xl">
              Ringkasan operasional dan metrik eksekutif sekolah terintegrasi secara real-time dan ter-cache
              untuk efisiensi pemantauan pimpinan sekolah.
            </p>
          </div>

          <div className="flex flex-col sm:flex-row items-start sm:items-center gap-2 self-start md:self-auto">
            <Button
              onClick={loadDashboard}
              variant="outline"
              size="sm"
              className="bg-white/10 border-white/20 text-white hover:bg-white/20 text-xs"
            >
              <RefreshCw className="h-3.5 w-3.5 mr-1.5" />
              Perbarui Data
            </Button>
            <Badge variant="default" className="bg-brand-teal text-white border-none px-3 py-1.5 text-xs">
              <ShieldCheck className="h-3.5 w-3.5 mr-1" />
              Sesi Terverifikasi
            </Badge>
          </div>
        </div>

        {data?.cached_at && (
          <div className="mt-4 pt-3 border-t border-white/10 flex items-center text-xs text-blue-200">
            <Clock className="h-3.5 w-3.5 mr-1.5 opacity-75" />
            <span>Terakhir disinkronkan: {new Date(data.cached_at).toLocaleTimeString('id-ID')} WIB</span>
          </div>
        )}
      </div>

      {/* Baris 1: Ringkasan Master & Fondasi */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <Card className="hover:shadow-elevated transition-shadow">
          <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
            <CardTitle className="text-xs font-semibold text-slate-500 uppercase">
              Total Siswa Terdaftar
            </CardTitle>
            <Users className="h-4 w-4 text-brand-blue" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-slate-900" data-testid="metric-total-students">
              {master ? master.total_students : '-'}
            </div>
            <p className="text-xs text-slate-500 mt-1">Siswa aktif terdata di pangkalan data</p>
          </CardContent>
        </Card>

        <Card className="hover:shadow-elevated transition-shadow">
          <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
            <CardTitle className="text-xs font-semibold text-slate-500 uppercase">
              Guru & Pengajar
            </CardTitle>
            <GraduationCap className="h-4 w-4 text-brand-teal" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-slate-900" data-testid="metric-total-teachers">
              {master ? master.total_teachers : '-'}
            </div>
            <p className="text-xs text-slate-500 mt-1">Tenaga pendidik aktif bertugas</p>
          </CardContent>
        </Card>

        <Card className="hover:shadow-elevated transition-shadow">
          <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
            <CardTitle className="text-xs font-semibold text-slate-500 uppercase">
              Rombongan Belajar
            </CardTitle>
            <Layers className="h-4 w-4 text-brand-amber" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-slate-900" data-testid="metric-total-classes">
              {master ? master.total_classes : '-'}
            </div>
            <p className="text-xs text-slate-500 mt-1">Kelas aktif semester berjalan</p>
          </CardContent>
        </Card>

        <Card className="hover:shadow-elevated transition-shadow">
          <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
            <CardTitle className="text-xs font-semibold text-slate-500 uppercase">
              Verifikasi Menunggu
            </CardTitle>
            <CheckCircle2 className="h-4 w-4 text-orange-500" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-orange-600" data-testid="metric-pending-verifications">
              {master?.pending_verifications ? master.pending_verifications.total : '0'}
            </div>
            <p className="text-xs text-slate-500 mt-1">
              {master?.pending_verifications
                ? `${master.pending_verifications.achievements} prestasi, ${master.pending_verifications.facility_bookings} booking ruang`
                : 'Tidak ada antrean tertunda'}
            </p>
          </CardContent>
        </Card>
      </div>

      {/* Baris 2: CBT Live Monitor & Presensi Hari Ini */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Widget ADM-DASH-02: CBT Live Monitor (Real-Time) */}
        <Card className="border-blue-200 bg-blue-50/30">
          <CardHeader className="pb-3">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-2">
                <span className="relative flex h-3 w-3">
                  <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                  <span className="relative inline-flex rounded-full h-3 w-3 bg-red-500"></span>
                </span>
                <CardTitle className="text-base font-bold text-blue-950">
                  CBT Live Monitor
                </CardTitle>
              </div>
              <Badge variant="outline" className="text-xs border-blue-300 text-blue-800 bg-white">
                Real-Time
              </Badge>
            </div>
            <CardDescription className="text-xs text-slate-600">
              Pengawasan aktivitas ujian daring siswa yang sedang berjalan detik ini.
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="grid grid-cols-3 gap-2 text-center">
              <div className="bg-white p-3 rounded-lg border border-blue-100 shadow-xs">
                <div className="text-2xl font-bold text-brand-blue" data-testid="cbt-active-students">
                  {cbt?.active_students_count ?? 0}
                </div>
                <div className="text-[11px] text-slate-600 font-medium mt-1">Siswa Aktif</div>
              </div>

              <div className="bg-white p-3 rounded-lg border border-blue-100 shadow-xs">
                <div className="text-2xl font-bold text-indigo-600" data-testid="cbt-ongoing-exams">
                  {cbt?.ongoing_exams_count ?? 0}
                </div>
                <div className="text-[11px] text-slate-600 font-medium mt-1">Ujian Berjalan</div>
              </div>

              <div className="bg-white p-3 rounded-lg border border-blue-100 shadow-xs">
                <div className="text-2xl font-bold text-teal-600" data-testid="cbt-active-rooms">
                  {cbt?.active_rooms_count ?? 0}
                </div>
                <div className="text-[11px] text-slate-600 font-medium mt-1">Ruang Aktif</div>
              </div>
            </div>

            <div className="pt-2 border-t border-blue-100">
              <Link href="/admin/cbt/monitor">
                <Button variant="outline" size="sm" className="w-full text-xs text-brand-blue hover:bg-blue-100/50">
                  <MonitorCheck className="h-3.5 w-3.5 mr-1.5" />
                  Buka Monitor Layar CBT
                </Button>
              </Link>
            </div>
          </CardContent>
        </Card>

        {/* Kehadiran Hari Ini (Siswa & Staf) */}
        <Card className="lg:col-span-2">
          <CardHeader className="pb-3">
            <div className="flex items-center justify-between">
              <CardTitle className="text-base font-bold text-slate-900">
                Presensi Hari Ini ({att?.date || 'Hari Ini'})
              </CardTitle>
              <CalendarCheck className="h-4 w-4 text-brand-teal" />
            </div>
            <CardDescription className="text-xs text-slate-600">
              Rekapitulasi kehadiran harian siswa dan guru/staf sekolah.
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              {/* Presensi Siswa */}
              <div className="p-3.5 rounded-lg border border-slate-200 bg-slate-50/50 space-y-2">
                <div className="flex items-center justify-between">
                  <span className="text-xs font-semibold text-slate-700">Tingkat Hadir Siswa</span>
                  <span className="text-sm font-bold text-emerald-600" data-testid="attendance-student-rate">
                    {att?.students.rate ?? 0}%
                  </span>
                </div>
                <div className="w-full bg-slate-200 h-2 rounded-full overflow-hidden">
                  <div
                    className="bg-emerald-500 h-full rounded-full transition-all"
                    style={{ width: `${Math.min(att?.students.rate ?? 0, 100)}%` }}
                  />
                </div>
                <div className="flex items-center justify-between text-[11px] text-slate-500 pt-1">
                  <span>Hadir: <strong>{att?.students.hadir ?? 0}</strong></span>
                  <span>Izin: <strong>{att?.students.izin ?? 0}</strong></span>
                  <span>Sakit: <strong>{att?.students.sakit ?? 0}</strong></span>
                  <span>Alpa: <strong>{att?.students.alpa ?? 0}</strong></span>
                  <span>Terlambat: <strong>{att?.students.terlambat ?? 0}</strong></span>
                </div>
              </div>

              {/* Presensi Staf */}
              <div className="p-3.5 rounded-lg border border-slate-200 bg-slate-50/50 space-y-2">
                <div className="flex items-center justify-between">
                  <span className="text-xs font-semibold text-slate-700">Tingkat Hadir Guru & Staf</span>
                  <span className="text-sm font-bold text-brand-blue" data-testid="attendance-staff-rate">
                    {att?.staff.rate ?? 0}%
                  </span>
                </div>
                <div className="w-full bg-slate-200 h-2 rounded-full overflow-hidden">
                  <div
                    className="bg-brand-blue h-full rounded-full transition-all"
                    style={{ width: `${Math.min(att?.staff.rate ?? 0, 100)}%` }}
                  />
                </div>
                <div className="flex items-center justify-between text-[11px] text-slate-500 pt-1">
                  <span>Total Tercatat: <strong>{att?.staff.total_recorded ?? 0}</strong></span>
                  <span>Hadir: <strong>{att?.staff.hadir ?? 0}</strong></span>
                  <span>Terlambat: <strong>{att?.staff.terlambat ?? 0}</strong></span>
                  <span>Izin: <strong>{att?.staff.izin ?? 0}</strong></span>
                </div>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Baris 3: Ketercapaian Akademik & Rerata Nilai */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        <Card>
          <CardHeader>
            <CardTitle className="text-base font-bold text-slate-900">
              Ketercapaian Akademik Guru
            </CardTitle>
            <CardDescription className="text-xs text-slate-600">
              Indikator penyelesaian jurnal mengajar dan ketuntasan penilaian bulanan.
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            {/* Silabus */}
            <div className="space-y-1.5">
              <div className="flex justify-between text-xs">
                <span className="font-medium text-slate-700">Penyelesaian Silabus / Jurnal</span>
                <span className="font-bold text-slate-900" data-testid="syllabus-rate">
                  {syllabus?.completion_rate ?? 0}%
                </span>
              </div>
              <div className="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                <div
                  className="bg-brand-teal h-full rounded-full"
                  style={{ width: `${Math.min(syllabus?.completion_rate ?? 0, 100)}%` }}
                />
              </div>
              <p className="text-[11px] text-slate-500">
                {syllabus?.active_with_journals ?? 0} dari {syllabus?.total_schedules ?? 0} jadwal memiliki jurnal
              </p>
            </div>

            {/* Ketuntasan Nilai Guru */}
            <div className="space-y-1.5 pt-2">
              <div className="flex justify-between text-xs">
                <span className="font-medium text-slate-700">Persentase Guru Tuntas Nilai</span>
                <span className="font-bold text-slate-900" data-testid="grading-rate">
                  {grading?.completion_rate ?? 0}%
                </span>
              </div>
              <div className="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                <div
                  className="bg-brand-amber h-full rounded-full"
                  style={{ width: `${Math.min(grading?.completion_rate ?? 0, 100)}%` }}
                />
              </div>
              <p className="text-[11px] text-slate-500">
                {grading?.completed_components ?? 0} dari {grading?.total_components ?? 0} komponen penilaian terisi
              </p>
            </div>
          </CardContent>
        </Card>

        {/* Rerata Nilai per Tingkat */}
        <Card>
          <CardHeader>
            <CardTitle className="text-base font-bold text-slate-900">
              Rerata Nilai per Angkatan
            </CardTitle>
            <CardDescription className="text-xs text-slate-600">
              Distribusi capaian akademik berdasarkan tingkat kelas siswa.
            </CardDescription>
          </CardHeader>
          <CardContent>
            {gradesByLevel.length === 0 ? (
              <div className="p-6 text-center text-xs text-slate-500 bg-slate-50 rounded-lg">
                Belum ada data nilai tercatat untuk periode ini.
              </div>
            ) : (
              <div className="space-y-3">
                {gradesByLevel.map((lvl) => (
                  <div
                    key={lvl.grade_level_id}
                    className="flex items-center justify-between p-2.5 rounded-lg border border-slate-100 bg-slate-50/50 text-xs"
                  >
                    <div>
                      <span className="font-semibold text-slate-800">{lvl.level_name}</span>
                      <span className="text-slate-400 ml-2">({lvl.grades_count} nilai masuk)</span>
                    </div>
                    <Badge variant="outline" className="font-bold bg-white text-slate-900">
                      Rata-rata: {lvl.average_score}
                    </Badge>
                  </div>
                ))}
              </div>
            )}
          </CardContent>
        </Card>
      </div>

      {/* Baris 4: Kesiswaan & Sarpras Kritis */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        {/* Kesiswaan */}
        <Card>
          <CardHeader>
            <CardTitle className="text-base font-bold text-slate-900">
              Kesiswaan & Bimbingan Konseling
            </CardTitle>
            <CardDescription className="text-xs text-slate-600">
              Statistik pemantauan kedisiplinan dan pendampingan siswa.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div className="grid grid-cols-2 gap-3">
              <div className="p-4 rounded-xl border border-slate-100 bg-rose-50/40 text-center">
                <HeartHandshake className="h-5 w-5 text-rose-600 mx-auto mb-1.5" />
                <div className="text-2xl font-bold text-rose-700" data-testid="active-counseling-cases">
                  {affairs?.active_counseling_cases ?? 0}
                </div>
                <div className="text-xs text-slate-600 font-medium mt-1">Kasus Konseling Aktif</div>
                <p className="text-[10px] text-slate-500 mt-0.5">Memerlukan tindak lanjut BK</p>
              </div>

              <div className="p-4 rounded-xl border border-slate-100 bg-amber-50/40 text-center">
                <AlertTriangle className="h-5 w-5 text-amber-600 mx-auto mb-1.5" />
                <div className="text-2xl font-bold text-amber-700" data-testid="recent-violations">
                  {affairs?.recent_violations_count ?? 0}
                </div>
                <div className="text-xs text-slate-600 font-medium mt-1">Pelanggaran (30 Hari)</div>
                <p className="text-[10px] text-slate-500 mt-0.5">Catatan poin kedisiplinan</p>
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Sarpras Kritis */}
        <Card>
          <CardHeader>
            <CardTitle className="text-base font-bold text-slate-900">
              Status Sarana & Prasarana Kritis
            </CardTitle>
            <CardDescription className="text-xs text-slate-600">
              Tiket kerusakan darurat dan aset sarpras dalam penanganan.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div className="grid grid-cols-2 gap-3">
              <div className="p-4 rounded-xl border border-slate-100 bg-orange-50/40 text-center">
                <Wrench className="h-5 w-5 text-orange-600 mx-auto mb-1.5" />
                <div className="text-2xl font-bold text-orange-700" data-testid="critical-tickets">
                  {infra?.critical_tickets_count ?? 0}
                </div>
                <div className="text-xs text-slate-600 font-medium mt-1">Tiket Helpdesk Darurat</div>
                <p className="text-[10px] text-slate-500 mt-0.5">Prioritas tinggi & tertunda</p>
              </div>

              <div className="p-4 rounded-xl border border-slate-100 bg-red-50/40 text-center">
                <Building2 className="h-5 w-5 text-red-600 mx-auto mb-1.5" />
                <div className="text-2xl font-bold text-red-700" data-testid="damaged-assets">
                  {infra?.heavily_damaged_assets ?? 0}
                </div>
                <div className="text-xs text-slate-600 font-medium mt-1">Aset Rusak Berat</div>
                <p className="text-[10px] text-slate-500 mt-0.5">Perlu penggantian / perbaikan</p>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Tautan Cepat & Modul Utama */}
      <Card>
        <CardHeader>
          <CardTitle className="text-base font-bold">Pusat Navigasi & Pelaporan Cepat</CardTitle>
          <CardDescription className="text-xs">
            Akses langsung ke modul rekapitulasi data dan administrasi utama sekolah.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            {isReadOnly ? (
              <>
                <Link
                  href="/admin/reports"
                  className="flex items-center justify-between p-3 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 hover:border-brand-blue transition-colors group"
                >
                  <div className="flex items-center gap-2.5">
                    <div className="p-2 rounded bg-blue-100 text-brand-blue group-hover:bg-brand-blue group-hover:text-white transition-colors">
                      <FileBarChart className="h-4 w-4" />
                    </div>
                    <div>
                      <h4 className="text-xs font-semibold text-slate-800">Pusat Laporan</h4>
                      <p className="text-[11px] text-slate-500">Rekapitulasi 6 modul sekolah</p>
                    </div>
                  </div>
                  <ArrowRight className="h-4 w-4 text-slate-400 group-hover:text-brand-blue" />
                </Link>

                <Link
                  href="/admin/executive"
                  className="flex items-center justify-between p-3 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 hover:border-indigo-600 transition-colors group"
                >
                  <div className="flex items-center gap-2.5">
                    <div className="p-2 rounded bg-indigo-100 text-indigo-700 group-hover:bg-indigo-700 group-hover:text-white transition-colors">
                      <Activity className="h-4 w-4" />
                    </div>
                    <div>
                      <h4 className="text-xs font-semibold text-slate-800">Executive Summary</h4>
                      <p className="text-[11px] text-slate-500">Rangkuman kinerja & KPI</p>
                    </div>
                  </div>
                  <ArrowRight className="h-4 w-4 text-slate-400 group-hover:text-indigo-600" />
                </Link>

                <Link
                  href="/admin/reports"
                  className="flex items-center justify-between p-3 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 hover:border-brand-amber transition-colors group"
                >
                  <div className="flex items-center gap-2.5">
                    <div className="p-2 rounded bg-amber-100 text-brand-amber group-hover:bg-brand-amber group-hover:text-white transition-colors">
                      <CalendarCheck className="h-4 w-4" />
                    </div>
                    <div>
                      <h4 className="text-xs font-semibold text-slate-800">Presensi Agregat</h4>
                      <p className="text-[11px] text-slate-500">Statistik kehadiran KBM</p>
                    </div>
                  </div>
                  <ArrowRight className="h-4 w-4 text-slate-400 group-hover:text-brand-amber" />
                </Link>

                <Link
                  href="/admin/reports"
                  className="flex items-center justify-between p-3 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 hover:border-teal-600 transition-colors group"
                >
                  <div className="flex items-center gap-2.5">
                    <div className="p-2 rounded bg-teal-100 text-brand-teal group-hover:bg-brand-teal group-hover:text-white transition-colors">
                      <Layers className="h-4 w-4" />
                    </div>
                    <div>
                      <h4 className="text-xs font-semibold text-slate-800">Statistik PPDB & Nilai</h4>
                      <p className="text-[11px] text-slate-500">Tren kuota & ketuntasan KKM</p>
                    </div>
                  </div>
                  <ArrowRight className="h-4 w-4 text-slate-400 group-hover:text-teal-600" />
                </Link>
              </>
            ) : (
              <>
                <Link
                  href="/admin/reports"
                  className="flex items-center justify-between p-3 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 hover:border-brand-blue transition-colors group"
                >
                  <div className="flex items-center gap-2.5">
                    <div className="p-2 rounded bg-blue-100 text-brand-blue group-hover:bg-brand-blue group-hover:text-white transition-colors">
                      <FileBarChart className="h-4 w-4" />
                    </div>
                    <div>
                      <h4 className="text-xs font-semibold text-slate-800">Pusat Laporan</h4>
                      <p className="text-[11px] text-slate-500">Ekspor rekap 6 jenis modul</p>
                    </div>
                  </div>
                  <ArrowRight className="h-4 w-4 text-slate-400 group-hover:text-brand-blue" />
                </Link>

                <Link
                  href="/admin/academic/grades"
                  className="flex items-center justify-between p-3 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 hover:border-brand-teal transition-colors group"
                >
                  <div className="flex items-center gap-2.5">
                    <div className="p-2 rounded bg-teal-100 text-brand-teal group-hover:bg-brand-teal group-hover:text-white transition-colors">
                      <BookOpen className="h-4 w-4" />
                    </div>
                    <div>
                      <h4 className="text-xs font-semibold text-slate-800">Buku Nilai Guru</h4>
                      <p className="text-[11px] text-slate-500">Input nilai & rekapitulasi</p>
                    </div>
                  </div>
                  <ArrowRight className="h-4 w-4 text-slate-400 group-hover:text-brand-teal" />
                </Link>

                <Link
                  href="/admin/academic/attendance"
                  className="flex items-center justify-between p-3 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 hover:border-brand-amber transition-colors group"
                >
                  <div className="flex items-center gap-2.5">
                    <div className="p-2 rounded bg-amber-100 text-brand-amber group-hover:bg-brand-amber group-hover:text-white transition-colors">
                      <CalendarCheck className="h-4 w-4" />
                    </div>
                    <div>
                      <h4 className="text-xs font-semibold text-slate-800">Presensi Harian</h4>
                      <p className="text-[11px] text-slate-500">KBM siswa & kehadiran staf</p>
                    </div>
                  </div>
                  <ArrowRight className="h-4 w-4 text-slate-400 group-hover:text-brand-amber" />
                </Link>

                <Link
                  href="/admin/tickets"
                  className="flex items-center justify-between p-3 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 hover:border-rose-500 transition-colors group"
                >
                  <div className="flex items-center gap-2.5">
                    <div className="p-2 rounded bg-rose-100 text-rose-600 group-hover:bg-rose-600 group-hover:text-white transition-colors">
                      <Wrench className="h-4 w-4" />
                    </div>
                    <div>
                      <h4 className="text-xs font-semibold text-slate-800">Helpdesk Sarpras</h4>
                      <p className="text-[11px] text-slate-500">Tiket perbaikan fasilitas</p>
                    </div>
                  </div>
                  <ArrowRight className="h-4 w-4 text-slate-400 group-hover:text-rose-600" />
                </Link>
              </>
            )}
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
