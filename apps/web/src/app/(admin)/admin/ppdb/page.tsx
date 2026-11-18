'use client';

import React from 'react';
import Link from 'next/link';
import { useQuery } from '@tanstack/react-query';
import { ppdbApi } from '@/features/ppdb/ppdb-api';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { PermissionGate } from '@/components/shared/permission-gate';
import {
  Users,
  FileCheck2,
  Trophy,
  GitBranch,
  Layers,
  ArrowRight,
  TrendingUp,
  GraduationCap,
  Sparkles,
  PieChart,
  Globe,
} from 'lucide-react';

export default function PPDBDashboardPage() {
  const { data: stats, isLoading, isError, refetch } = useQuery({
    queryKey: ['ppdb-stats-dashboard'],
    queryFn: () => ppdbApi.getStats(),
  });

  const totalApplicants = stats?.total_applicants || 0;
  const submittedApplicants = stats?.submitted_applicants || 0;
  const verifiedCount = stats?.status_counts?.verified || 0;
  const acceptedCount = stats?.decision_counts?.diterima || 0;
  const tracks = stats?.tracks || [];

  return (
    <PermissionGate
      permission={['ppdb.wave.manage', 'ppdb.applicant.read', 'ppdb.applicant.verify']}
    >
      <div className="space-y-6">
        {/* Page Title & Breadcrumb */}
        <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h1 className="text-2xl font-bold text-gray-900">
              Penerimaan Peserta Didik Baru (PPDB)
            </h1>
            <p className="text-sm text-gray-500">
              Pusat kendali operasional pendaftaran, verifikasi berkas, seleksi berkuota, dan laporan statistik
            </p>
          </div>
          <div className="flex items-center gap-2">
            <Link href="/ppdb" target="_blank" rel="noopener noreferrer">
              <Button variant="outline" size="sm" className="text-xs gap-1.5 border-slate-300">
                <Globe className="h-4 w-4 text-blue-600" />
                <span>Buka Portal SPMB Publik</span>
              </Button>
            </Link>
          </div>
        </div>

        {/* 4 Primary KPI Cards */}
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <Card className="border-l-4 border-l-blue-500 shadow-sm">
            <CardHeader className="flex flex-row items-center justify-between pb-2">
              <CardTitle className="text-xs font-medium text-gray-500 uppercase tracking-wider">
                Total Akun Pendaftar
              </CardTitle>
              <Users className="h-5 w-5 text-blue-500" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold text-gray-900">
                {isLoading ? '...' : totalApplicants}
              </div>
              <p className="mt-1 text-xs text-gray-500">
                Calon siswa yang membuat akun di portal
              </p>
            </CardContent>
          </Card>

          <Card className="border-l-4 border-l-amber-500 shadow-sm">
            <CardHeader className="flex flex-row items-center justify-between pb-2">
              <CardTitle className="text-xs font-medium text-gray-500 uppercase tracking-wider">
                Formulir Masuk
              </CardTitle>
              <FileCheck2 className="h-5 w-5 text-amber-500" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold text-gray-900">
                {isLoading ? '...' : submittedApplicants}
              </div>
              <p className="mt-1 text-xs text-gray-500">
                Formulir pendaftaran yang telah disubmit
              </p>
            </CardContent>
          </Card>

          <Card className="border-l-4 border-l-emerald-500 shadow-sm">
            <CardHeader className="flex flex-row items-center justify-between pb-2">
              <CardTitle className="text-xs font-medium text-gray-500 uppercase tracking-wider">
                Berkas Terverifikasi
              </CardTitle>
              <Sparkles className="h-5 w-5 text-emerald-500" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold text-gray-900">
                {isLoading ? '...' : verifiedCount}
              </div>
              <p className="mt-1 text-xs text-gray-500">
                Berkas dinyatakan lengkap dan valid oleh panitia
              </p>
            </CardContent>
          </Card>

          <Card className="border-l-4 border-l-purple-500 shadow-sm">
            <CardHeader className="flex flex-row items-center justify-between pb-2">
              <CardTitle className="text-xs font-medium text-gray-500 uppercase tracking-wider">
                Lolos Seleksi (Diterima)
              </CardTitle>
              <Trophy className="h-5 w-5 text-purple-500" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold text-gray-900">
                {isLoading ? '...' : acceptedCount}
              </div>
              <p className="mt-1 text-xs text-gray-500">
                Calon siswa yang memenuhi kuota penerimaan
              </p>
            </CardContent>
          </Card>
        </div>

        {/* Quick Module Navigation Cards */}
        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
          <Link
            href="/admin/ppdb/waves"
            className="group flex flex-col justify-between rounded-xl border border-gray-200 bg-white p-5 shadow-sm hover:border-emerald-500 hover:shadow-md transition"
          >
            <div>
              <div className="flex items-center gap-3 mb-2">
                <div className="rounded-lg bg-emerald-50 p-2.5 text-emerald-600 group-hover:bg-emerald-600 group-hover:text-white transition">
                  <Layers className="h-5 w-5" />
                </div>
                <div>
                  <h3 className="font-semibold text-gray-900 group-hover:text-emerald-600 transition">
                    Gelombang & Jalur
                  </h3>
                </div>
              </div>
              <p className="text-xs text-gray-600 mt-2">
                Konfigurasi jadwal pembukaan, alokasi kuota siswa, syarat berkas dokumen, dan formula skor per jalur.
              </p>
            </div>
            <div className="mt-4 flex items-center gap-1 text-xs font-semibold text-emerald-700">
              <span>Buka Konfigurasi</span>
              <ArrowRight className="h-3.5 w-3.5 group-hover:translate-x-1 transition" />
            </div>
          </Link>

          <Link
            href="/admin/ppdb/applicants"
            className="group flex flex-col justify-between rounded-xl border border-gray-200 bg-white p-5 shadow-sm hover:border-emerald-500 hover:shadow-md transition"
          >
            <div>
              <div className="flex items-center gap-3 mb-2">
                <div className="rounded-lg bg-blue-50 p-2.5 text-blue-600 group-hover:bg-blue-600 group-hover:text-white transition">
                  <FileCheck2 className="h-5 w-5" />
                </div>
                <div>
                  <h3 className="font-semibold text-gray-900 group-hover:text-blue-600 transition">
                    Verifikasi Berkas
                  </h3>
                </div>
              </div>
              <p className="text-xs text-gray-600 mt-2">
                Pemeriksaan biodata calon siswa, validasi dokumen persyaratan, penolakan berkas dengan catatan, dan riwayat audit.
              </p>
            </div>
            <div className="mt-4 flex items-center gap-1 text-xs font-semibold text-blue-700">
              <span>Buka Verifikasi</span>
              <ArrowRight className="h-3.5 w-3.5 group-hover:translate-x-1 transition" />
            </div>
          </Link>

          <Link
            href="/admin/ppdb/selection"
            className="group flex flex-col justify-between rounded-xl border border-gray-200 bg-white p-5 shadow-sm hover:border-emerald-500 hover:shadow-md transition"
          >
            <div>
              <div className="flex items-center gap-3 mb-2">
                <div className="rounded-lg bg-purple-50 p-2.5 text-purple-600 group-hover:bg-purple-600 group-hover:text-white transition">
                  <TrendingUp className="h-5 w-5" />
                </div>
                <div>
                  <h3 className="font-semibold text-gray-900 group-hover:text-purple-600 transition">
                    Seleksi & Perankingan
                  </h3>
                </div>
              </div>
              <p className="text-xs text-gray-600 mt-2">
                Pemeringkatan otomatis (diterima, cadangan, ditolak), override keputusan panitia, rilis pengumuman, dan konversi ke siswa aktif.
              </p>
            </div>
            <div className="mt-4 flex items-center gap-1 text-xs font-semibold text-purple-700">
              <span>Buka Seleksi</span>
              <ArrowRight className="h-3.5 w-3.5 group-hover:translate-x-1 transition" />
            </div>
          </Link>
        </div>

        {/* Track Statistics Breakdown Table */}
        <div className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
          <div className="mb-4 flex items-center justify-between">
            <div className="flex items-center gap-2">
              <PieChart className="h-5 w-5 text-emerald-600" />
              <h2 className="font-bold text-gray-900 text-sm">
                Statistik Keterisian Kuota per Jalur Pendaftaran
              </h2>
            </div>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs">
              <thead className="bg-gray-50 text-gray-600 uppercase tracking-wider">
                <tr>
                  <th className="px-4 py-3 font-semibold">Kode Jalur</th>
                  <th className="px-4 py-3 font-semibold">Nama Jalur</th>
                  <th className="px-4 py-3 font-semibold">Gelombang</th>
                  <th className="px-4 py-3 font-semibold text-center">Kuota</th>
                  <th className="px-4 py-3 font-semibold text-center">Pendaftar</th>
                  <th className="px-4 py-3 font-semibold text-center">Diverifikasi</th>
                  <th className="px-4 py-3 font-semibold text-center">Diterima</th>
                  <th className="px-4 py-3 font-semibold">Keterisian Kuota</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100 text-gray-800">
                {isLoading ? (
                  <tr>
                    <td colSpan={8} className="py-6 text-center text-gray-400">
                      Memuat statistik jalur...
                    </td>
                  </tr>
                ) : tracks.length === 0 ? (
                  <tr>
                    <td colSpan={8} className="py-6 text-center text-gray-400">
                      Belum ada data pendaftaran pada jalur PPDB.
                    </td>
                  </tr>
                ) : (
                  tracks.map((t) => (
                    <tr key={t.track_id} className="hover:bg-gray-50/70 transition">
                      <td className="px-4 py-3 font-mono font-bold text-gray-700">{t.code}</td>
                      <td className="px-4 py-3 font-semibold">{t.name}</td>
                      <td className="px-4 py-3 text-gray-500">{t.wave_name || '-'}</td>
                      <td className="px-4 py-3 text-center font-bold text-emerald-800">
                        {t.quota}
                      </td>
                      <td className="px-4 py-3 text-center font-semibold">
                        {t.submitted_applicants} / {t.total_applicants}
                      </td>
                      <td className="px-4 py-3 text-center text-blue-700 font-semibold">
                        {t.verified_count}
                      </td>
                      <td className="px-4 py-3 text-center text-purple-700 font-semibold">
                        {t.accepted_count}
                      </td>
                      <td className="px-4 py-3 min-w-[140px]">
                        <div className="flex items-center gap-2">
                          <div className="h-2 flex-1 rounded-full bg-gray-200 overflow-hidden">
                            <div
                              className="h-full rounded-full bg-emerald-500 transition-all duration-300"
                              style={{
                                width: `${Math.min(100, t.fill_percentage)}%`,
                              }}
                            />
                          </div>
                          <span className="font-mono text-[11px] font-semibold text-gray-600">
                            {t.fill_percentage}%
                          </span>
                        </div>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </PermissionGate>
  );
}
