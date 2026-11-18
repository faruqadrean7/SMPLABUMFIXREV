'use client';

import React, { useEffect, useState } from 'react';
import Link from 'next/link';
import {
  ShieldAlert,
  Award,
  Compass,
  Trophy,
  ArrowRight,
  Plus,
  RefreshCw,
  AlertTriangle,
  CheckCircle2,
  Users,
  Flame,
  FileText,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { pointsApi } from '@/features/student-affairs/points-api';
import { StudentPointItem, PointThreshold } from '@/features/student-affairs/types';

export default function StudentAffairsHubPage() {
  const [points, setPoints] = useState<StudentPointItem[]>([]);
  const [thresholds, setThresholds] = useState<PointThreshold[]>([]);
  const [isLoading, setIsLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);

  const fetchData = async () => {
    setIsLoading(true);
    setError(null);
    try {
      const [pointsRes, thresholdsRes] = await Promise.all([
        pointsApi.getStudentPoints({ per_page: 10 }),
        pointsApi.getPointThresholds(),
      ]);
      setPoints(pointsRes.data || []);
      setThresholds(thresholdsRes.data || []);
    } catch (err: any) {
      setError(err?.message || 'Gagal memuat ringkasan data kesiswaan.');
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    fetchData();
  }, []);

  const totalViolations = points.filter((p) => p.point_type?.kind === 'pelanggaran').length;
  const totalAchievements = points.filter((p) => p.point_type?.kind === 'prestasi').length;

  return (
    <div className="space-y-6">
      {/* Header Banner */}
      <div className="rounded-xl bg-gradient-to-r from-slate-900 via-amber-950 to-blue-950 p-6 text-white shadow-md">
        <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
          <div>
            <div className="flex items-center gap-2 mb-2">
              <Badge className="bg-amber-500/20 text-amber-300 border border-amber-400/30">
                Kesiswaan & Pembinaan Karakter
              </Badge>
              <span className="text-xs text-amber-200">•</span>
              <span className="text-xs text-amber-200">SISTLABUM</span>
            </div>
            <h1 className="text-2xl font-bold tracking-tight">Portal Kesiswaan & Kedisiplinan 🛡️</h1>
            <p className="mt-1 text-sm text-slate-300 max-w-2xl">
              Pusat monitoring ketertiban, kedisiplinan poin pelanggaran, apresiasi prestasi siswa,
              konfigurasi ambang tindak lanjut sanksi bertingkat, serta koordinasi pembinaan kesiswaan.
            </p>
          </div>

          <div className="flex items-center gap-3">
            <Button
              variant="outline"
              size="sm"
              onClick={fetchData}
              disabled={isLoading}
              className="bg-white/10 hover:bg-white/20 text-white border-white/20"
            >
              <RefreshCw className={`h-4 w-4 mr-1.5 ${isLoading ? 'animate-spin' : ''}`} />
              Segarkan
            </Button>
            <Link href="/admin/student-affairs/points">
              <Button size="sm" className="bg-amber-600 hover:bg-amber-500 text-white gap-1.5 shadow-sm">
                <Plus className="h-4 w-4" />
                Catat Poin Siswa
              </Button>
            </Link>
          </div>
        </div>
      </div>

      {/* Stats Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <Card className="border-slate-200">
          <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
            <CardTitle className="text-xs font-semibold text-slate-600">Total Catatan Poin</CardTitle>
            <FileText className="h-4 w-4 text-slate-500" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-slate-900">{points.length}</div>
            <p className="text-[11px] text-slate-500 mt-1">Riwayat poin kesiswaan terkini</p>
          </CardContent>
        </Card>

        <Card className="border-slate-200">
          <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
            <CardTitle className="text-xs font-semibold text-slate-600">Pelanggaran Tercatat</CardTitle>
            <AlertTriangle className="h-4 w-4 text-red-500" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-red-600">{totalViolations}</div>
            <p className="text-[11px] text-slate-500 mt-1">Perlu pemantauan & bimbingan</p>
          </CardContent>
        </Card>

        <Card className="border-slate-200">
          <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
            <CardTitle className="text-xs font-semibold text-slate-600">Prestasi & Apresiasi</CardTitle>
            <Award className="h-4 w-4 text-emerald-500" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-emerald-600">{totalAchievements}</div>
            <p className="text-[11px] text-slate-500 mt-1">Poin penghargaan positif siswa</p>
          </CardContent>
        </Card>

        <Card className="border-slate-200">
          <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
            <CardTitle className="text-xs font-semibold text-slate-600">Ambang Sanksi Aktif</CardTitle>
            <Flame className="h-4 w-4 text-amber-500" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-amber-600">{thresholds.length}</div>
            <p className="text-[11px] text-slate-500 mt-1">Tingkatan aturan tindak lanjut</p>
          </CardContent>
        </Card>
      </div>

      {/* Modul Navigation Cards */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        <Card className="border-slate-200 hover:border-amber-300 transition-all shadow-sm">
          <CardHeader>
            <div className="flex items-center gap-3">
              <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-100 text-amber-700">
                <ShieldAlert className="h-5 w-5" />
              </div>
              <div>
                <CardTitle className="text-base text-slate-900">Poin Kedisiplinan & Prestasi</CardTitle>
                <CardDescription className="text-xs text-slate-500">
                  Manajemen jenis poin, input cepat pelanggaran, ambang batas sanksi otomatis, dan riwayat siswa.
                </CardDescription>
              </div>
            </div>
          </CardHeader>
          <CardContent className="pt-0">
            <p className="text-xs text-slate-600 mb-4">
              Catat poin pelanggaran atau prestasi siswa oleh guru piket/wali kelas/BK, pantau akumulasi poin per siswa,
              dan terima notifikasi otomatis saat ambang batas (SP1, Panggilan Ortu) terlewati.
            </p>
            <Link href="/admin/student-affairs/points">
              <Button size="sm" className="w-full bg-slate-900 hover:bg-slate-800 text-white gap-2">
                Kelola Poin Siswa & Ambang Sanksi
                <ArrowRight className="h-4 w-4" />
              </Button>
            </Link>
          </CardContent>
        </Card>

        <Card className="border-slate-200 hover:border-blue-300 transition-all shadow-sm">
          <CardHeader>
            <div className="flex items-center gap-3">
              <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-100 text-blue-700">
                <Compass className="h-5 w-5" />
              </div>
              <div>
                <CardTitle className="text-base text-slate-900">Ekstrakurikuler & Organisasi</CardTitle>
                <CardDescription className="text-xs text-slate-500">
                  Master kegiatan ekskul, pendaftaran daring siswa, jurnal kegiatan pembina, dan penilaian rapor.
                </CardDescription>
              </div>
            </div>
          </CardHeader>
          <CardContent className="pt-0">
            <p className="text-xs text-slate-600 mb-4">
              Kelola daftar ekstrakurikuler sekolah, kapasitas peserta, pencatatan kehadiran pelatih/pembina,
              serta penginputan predikat nilai ekskul untuk integrasi E-Rapor Semester.
            </p>
            <Link href="/admin/student-affairs/extracurriculars">
              <Button variant="outline" size="sm" className="w-full border-slate-300 gap-2 text-slate-700">
                Lihat Modul Ekstrakurikuler
                <ArrowRight className="h-4 w-4" />
              </Button>
            </Link>
          </CardContent>
        </Card>

        <Card className="border-slate-200 hover:border-emerald-300 transition-all shadow-sm">
          <CardHeader>
            <div className="flex items-center gap-3">
              <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700">
                <Trophy className="h-5 w-5" />
              </div>
              <div>
                <CardTitle className="text-base text-slate-900">Lomba & Prestasi Siswa</CardTitle>
                <CardDescription className="text-xs text-slate-500">
                  Katalog kompetisi, delegasi kontingen, galeri piala & medali, serta verifikasi berkas bukti.
                </CardDescription>
              </div>
            </div>
          </CardHeader>
          <CardContent className="pt-0">
            <p className="text-xs text-slate-600 mb-4">
              Kelola partisipasi delegasi lomba akademik/non-akademik, validasi sertifikat juara, dan sinkronisasi otomatis apresiasi poin karakter siswa.
            </p>
            <Link href="/admin/student-affairs/competitions">
              <Button variant="outline" size="sm" className="w-full border-slate-300 gap-2 text-slate-700">
                Buka Katalog Lomba & Prestasi
                <ArrowRight className="h-4 w-4" />
              </Button>
            </Link>
          </CardContent>
        </Card>
      </div>

      {/* Riwayat Aktivitas Poin Terkini */}
      <Card className="border-slate-200 shadow-sm">
        <CardHeader className="flex flex-row items-center justify-between pb-3">
          <div>
            <CardTitle className="text-sm font-bold text-slate-900">Riwayat Catatan Poin Terkini</CardTitle>
            <CardDescription className="text-xs text-slate-500">
              10 catatan kedisiplinan dan prestasi terbaru yang dicatat oleh staf/guru
            </CardDescription>
          </div>
          <Link href="/admin/student-affairs/points">
            <Button variant="ghost" size="sm" className="text-xs text-blue-600 hover:text-blue-700 gap-1">
              Lihat Semua
              <ArrowRight className="h-3.5 w-3.5" />
            </Button>
          </Link>
        </CardHeader>
        <CardContent>
          {error && (
            <div className="rounded-md bg-red-50 p-3 text-xs text-red-700 mb-4 border border-red-200">
              {error}
            </div>
          )}

          {isLoading ? (
            <div className="text-center py-8 text-xs text-slate-500">
              <RefreshCw className="h-5 w-5 animate-spin mx-auto mb-2 text-slate-400" />
              Memuat data riwayat poin...
            </div>
          ) : points.length === 0 ? (
            <div className="text-center py-8 text-xs text-slate-500">
              Belum ada riwayat catatan poin pelanggaran atau prestasi siswa.
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-xs text-left">
                <thead className="bg-slate-50 text-slate-600 font-semibold border-b">
                  <tr>
                    <th className="py-2.5 px-3">Tanggal</th>
                    <th className="py-2.5 px-3">Siswa</th>
                    <th className="py-2.5 px-3">Jenis Poin</th>
                    <th className="py-2.5 px-3">Kategori</th>
                    <th className="py-2.5 px-3">Poin</th>
                    <th className="py-2.5 px-3">Dicatat Oleh</th>
                    <th className="py-2.5 px-3">Catatan</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {points.map((p) => {
                    const isViolation = p.point_type?.kind === 'pelanggaran';
                    return (
                      <tr key={p.id} className="hover:bg-slate-50/70 transition-colors">
                        <td className="py-2.5 px-3 font-mono text-slate-600 whitespace-nowrap">
                          {p.point_date}
                        </td>
                        <td className="py-2.5 px-3 font-medium text-slate-900 whitespace-nowrap">
                          {p.student?.full_name || 'Siswa'}
                          {p.student?.nis && (
                            <span className="block text-[11px] text-slate-400 font-mono">
                              NIS: {p.student.nis}
                            </span>
                          )}
                        </td>
                        <td className="py-2.5 px-3 text-slate-800">
                          {p.point_type?.name || '-'}
                        </td>
                        <td className="py-2.5 px-3">
                          <Badge
                            className={
                              isViolation
                                ? 'bg-red-100 text-red-700 border-red-200'
                                : 'bg-emerald-100 text-emerald-700 border-emerald-200'
                            }
                          >
                            {isViolation ? 'Pelanggaran' : 'Prestasi'}
                          </Badge>
                        </td>
                        <td className="py-2.5 px-3 font-bold">
                          <span className={isViolation ? 'text-red-600' : 'text-emerald-600'}>
                            {isViolation ? `-${p.point_type?.points}` : `+${p.point_type?.points}`}
                          </span>
                        </td>
                        <td className="py-2.5 px-3 text-slate-600 whitespace-nowrap">
                          {p.recorder?.name || 'Staf Sekolah'}
                        </td>
                        <td className="py-2.5 px-3 text-slate-500 max-w-xs truncate">
                          {p.notes || '-'}
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
