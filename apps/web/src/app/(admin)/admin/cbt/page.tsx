'use client';

import React, { useEffect, useState } from 'react';
import Link from 'next/link';
import {
  MonitorCheck,
  FileQuestion,
  CalendarCheck,
  Activity,
  ArrowRight,
  Plus,
  RefreshCw,
  Users,
  Award,
  CheckCircle2,
  Clock,
  AlertCircle,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { cbtApi } from '@/features/cbt/cbt-api';
import { ExamItem, QuestionBankItem } from '@/types/cbt';

export default function CbtHubPage() {
  const [exams, setExams] = useState<ExamItem[]>([]);
  const [banks, setBanks] = useState<QuestionBankItem[]>([]);
  const [isLoading, setIsLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);

  const fetchData = async () => {
    setIsLoading(true);
    setError(null);
    try {
      const [examsRes, banksRes] = await Promise.all([
        cbtApi.getExams({ per_page: 6 }),
        cbtApi.getQuestionBanks({ per_page: 6 }),
      ]);
      setExams(examsRes.data || []);
      setBanks(banksRes.data || []);
    } catch (err: any) {
      setError(err?.message || 'Gagal memuat data ringkasan CBT.');
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    fetchData();
  }, []);

  const totalExams = exams.length;
  const activeExams = exams.filter((e) => e.status === 'active' || e.status === 'berlangsung').length;
  const totalBanks = banks.length;

  return (
    <div className="space-y-6">
      {/* Header Banner */}
      <div className="rounded-xl bg-gradient-to-r from-slate-900 via-indigo-950 to-blue-900 p-6 text-white shadow-md">
        <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
          <div>
            <div className="flex items-center gap-2 mb-2">
              <Badge className="bg-blue-500/20 text-blue-300 border border-blue-400/30">
                Sistem Asesmen Digital
              </Badge>
              <span className="text-xs text-blue-200">•</span>
              <span className="text-xs text-blue-200">Server Lab Aktif</span>
            </div>
            <h1 className="text-2xl font-bold tracking-tight">Portal Ujian Komputer (CBT) 🖥️</h1>
            <p className="mt-1 text-sm text-slate-300 max-w-2xl">
              Pusat kendali asesmen digital: kelola bank soal 6 format, jadwalkan ujian, bagikan token
              ruang lab, pantau integritas ujian secara real-time, dan sinkronkan nilai ke Buku Nilai.
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
            <Link href="/admin/cbt/exams">
              <Button size="sm" className="bg-blue-600 hover:bg-blue-500 text-white gap-1.5">
                <Plus className="h-4 w-4" />
                Jadwalkan Ujian
              </Button>
            </Link>
          </div>
        </div>
      </div>

      {/* Stats Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <Card>
          <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
            <CardTitle className="text-xs font-semibold text-slate-500 uppercase">
              Paket Bank Soal
            </CardTitle>
            <FileQuestion className="h-4 w-4 text-indigo-600" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-slate-900">{totalBanks} Paket</div>
            <p className="text-xs text-slate-500 mt-1">PG, Kompleks, BS, Isian, Uraian</p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
            <CardTitle className="text-xs font-semibold text-slate-500 uppercase">
              Ujian Terjadwal
            </CardTitle>
            <CalendarCheck className="h-4 w-4 text-blue-600" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-slate-900">{totalExams} Jadwal</div>
            <p className="text-xs text-slate-500 mt-1">Ulangan Harian, UTS, & UAS</p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
            <CardTitle className="text-xs font-semibold text-slate-500 uppercase">
              Sesi Sedang Aktif
            </CardTitle>
            <Activity className="h-4 w-4 text-emerald-600" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-emerald-600">{activeExams} Aktif</div>
            <p className="text-xs text-slate-500 mt-1">Ruang Lab terhubung</p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
            <CardTitle className="text-xs font-semibold text-slate-500 uppercase">
              Integritas Lab
            </CardTitle>
            <CheckCircle2 className="h-4 w-4 text-teal-600" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-slate-900">Anti-Cheat On</div>
            <p className="text-xs text-slate-500 mt-1">Deteksi tab blur & IP anomali</p>
          </CardContent>
        </Card>
      </div>

      {/* 3 Main Action Modules */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        <Link href="/admin/cbt/questions" className="group block">
          <Card className="h-full border border-slate-200 transition-all hover:border-indigo-500 hover:shadow-md">
            <CardHeader>
              <div className="h-10 w-10 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center mb-2 group-hover:bg-indigo-600 group-hover:text-white transition-colors">
                <FileQuestion className="h-5 w-5" />
              </div>
              <CardTitle className="text-base group-hover:text-indigo-600 transition-colors">
                Bank Soal Ujian
              </CardTitle>
              <CardDescription className="text-xs">
                Kelola bank soal kurikulum merdeka dengan 6 variasi butir soal objektif & subjektif,
                bobot poin, dan kunci jawaban.
              </CardDescription>
            </CardHeader>
            <CardContent className="pt-0">
              <div className="flex items-center text-xs font-medium text-indigo-600 group-hover:translate-x-1 transition-transform">
                Buka Bank Soal <ArrowRight className="h-3.5 w-3.5 ml-1" />
              </div>
            </CardContent>
          </Card>
        </Link>

        <Link href="/admin/cbt/exams" className="group block">
          <Card className="h-full border border-slate-200 transition-all hover:border-blue-500 hover:shadow-md">
            <CardHeader>
              <div className="h-10 w-10 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center mb-2 group-hover:bg-blue-600 group-hover:text-white transition-colors">
                <CalendarCheck className="h-5 w-5" />
              </div>
              <CardTitle className="text-base group-hover:text-blue-600 transition-colors">
                Jadwal & Paket Ujian
              </CardTitle>
              <CardDescription className="text-xs">
                Plotting ruang lab, distribusi token peserta, auto-grading butir objektif, dan sinkronisasi
                hasil ke Buku Nilai (Gradebook).
              </CardDescription>
            </CardHeader>
            <CardContent className="pt-0">
              <div className="flex items-center text-xs font-medium text-blue-600 group-hover:translate-x-1 transition-transform">
                Kelola Jadwal & Nilai <ArrowRight className="h-3.5 w-3.5 ml-1" />
              </div>
            </CardContent>
          </Card>
        </Link>

        <Link href="/admin/cbt/monitor" className="group block">
          <Card className="h-full border border-slate-200 transition-all hover:border-emerald-500 hover:shadow-md">
            <CardHeader>
              <div className="h-10 w-10 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center mb-2 group-hover:bg-emerald-600 group-hover:text-white transition-colors">
                <Activity className="h-5 w-5" />
              </div>
              <CardTitle className="text-base group-hover:text-emerald-600 transition-colors">
                Live Monitor Lab CBT
              </CardTitle>
              <CardDescription className="text-xs">
                Monitoring langsung meja komputer lab saat ujian berlangsung. Proctor dapat membuka kunci,
                menambah waktu, atau force-submit.
              </CardDescription>
            </CardHeader>
            <CardContent className="pt-0">
              <div className="flex items-center text-xs font-medium text-emerald-600 group-hover:translate-x-1 transition-transform">
                Masuk Ruang Monitor <ArrowRight className="h-3.5 w-3.5 ml-1" />
              </div>
            </CardContent>
          </Card>
        </Link>
      </div>

      {/* Recent Exams Table */}
      <Card>
        <CardHeader className="flex flex-row items-center justify-between pb-3">
          <div>
            <CardTitle className="text-base font-bold text-slate-900">Jadwal Ujian Terkini</CardTitle>
            <CardDescription className="text-xs">
              Daftar pelaksanaan asesmen digital yang baru dibuat atau sedang berlangsung.
            </CardDescription>
          </div>
          <Link href="/admin/cbt/exams">
            <Button variant="ghost" size="sm" className="text-xs text-blue-600 hover:text-blue-700">
              Lihat Semua Ujian
            </Button>
          </Link>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <div className="py-8 text-center text-sm text-slate-500">Memuat jadwal ujian terkini...</div>
          ) : error ? (
            <div className="py-6 text-center text-sm text-rose-500 flex items-center justify-center gap-2">
              <AlertCircle className="h-4 w-4" /> {error}
            </div>
          ) : exams.length === 0 ? (
            <div className="py-8 text-center text-sm text-slate-500">
              Belum ada ujian yang dijadwalkan.{' '}
              <Link href="/admin/cbt/exams" className="text-blue-600 hover:underline">
                Buat ujian sekarang.
              </Link>
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-left text-xs border-collapse">
                <thead>
                  <tr className="border-b border-slate-200 text-slate-500 font-semibold uppercase tracking-wider">
                    <th className="py-2.5 px-3">Judul Ujian & Mapel</th>
                    <th className="py-2.5 px-3">Tipe</th>
                    <th className="py-2.5 px-3">Durasi</th>
                    <th className="py-2.5 px-3">Waktu Mulai</th>
                    <th className="py-2.5 px-3">Status</th>
                    <th className="py-2.5 px-3 text-right">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {exams.map((exam) => {
                    const isLive = exam.status === 'active' || exam.status === 'berlangsung';
                    return (
                      <tr key={exam.id} className="hover:bg-slate-50 transition-colors">
                        <td className="py-3 px-3">
                          <div className="font-semibold text-slate-900">{exam.title}</div>
                          <div className="text-[11px] text-slate-500">{exam.subject?.name || 'Mata Pelajaran'}</div>
                        </td>
                        <td className="py-3 px-3">
                          <span className="capitalize font-medium text-slate-700">{exam.exam_type}</span>
                        </td>
                        <td className="py-3 px-3 text-slate-600">{exam.duration_min} Menit</td>
                        <td className="py-3 px-3 text-slate-600">
                          {new Date(exam.start_at).toLocaleDateString('id-ID', {
                            day: 'numeric',
                            month: 'short',
                            year: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit',
                          })}
                        </td>
                        <td className="py-3 px-3">
                          <Badge
                            variant={isLive ? 'success' : 'outline'}
                            className={
                              isLive
                                ? 'bg-emerald-100 text-emerald-800 border-emerald-300'
                                : 'bg-slate-100 text-slate-700'
                            }
                          >
                            {exam.status}
                          </Badge>
                        </td>
                        <td className="py-3 px-3 text-right">
                          <div className="flex items-center justify-end gap-2">
                            {isLive && (
                              <Link href="/admin/cbt/monitor">
                                <Button size="sm" variant="outline" className="h-7 text-xs text-emerald-700 border-emerald-300">
                                  Monitor
                                </Button>
                              </Link>
                            )}
                            <Link href={`/admin/cbt/exams`}>
                              <Button size="sm" variant="outline" className="h-7 text-xs">
                                Kelola
                              </Button>
                            </Link>
                          </div>
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
