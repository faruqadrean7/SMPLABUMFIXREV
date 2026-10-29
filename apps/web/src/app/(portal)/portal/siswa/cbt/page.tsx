'use client';

import React, { useEffect, useState } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import {
  MonitorCheck,
  Calendar,
  Clock,
  Award,
  ArrowRight,
  Key,
  CheckCircle2,
  AlertCircle,
  HelpCircle,
  BookOpen,
  ArrowLeft,
  X,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { cbtApi } from '@/features/cbt/cbt-api';
import { ExamItem } from '@/types/cbt';
import { useAuth } from '@/providers/auth-provider';

export default function StudentCbtCatalogPage() {
  const router = useRouter();
  const { user } = useAuth();
  const [exams, setExams] = useState<ExamItem[]>([]);
  const [isLoading, setIsLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);

  // Token Modal
  const [selectedExam, setSelectedExam] = useState<ExamItem | null>(null);
  const [tokenInput, setTokenInput] = useState<string>('');
  const [tokenError, setTokenError] = useState<string | null>(null);
  const [isStartingSession, setIsStartingSession] = useState<boolean>(false);

  const fetchExams = async () => {
    setIsLoading(true);
    setError(null);
    try {
      const res = await cbtApi.getExams({ per_page: 50 });
      setExams(res.data || []);
    } catch (err: any) {
      setError(err?.message || 'Gagal memuat jadwal ujian.');
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    fetchExams();
  }, []);

  const handleStartExam = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedExam || !tokenInput.trim()) {
      setTokenError('Token ujian wajib diisi.');
      return;
    }

    setIsStartingSession(true);
    setTokenError(null);
    try {
      await cbtApi.startStudentSession(selectedExam.id, {
        token: tokenInput.trim().toUpperCase(),
      });
      // Sukses: Buka workspace ujian CBT
      router.push(`/portal/siswa/ujian/${selectedExam.id}`);
    } catch (err: any) {
      setTokenError(err?.message || 'Token ujian tidak valid atau ruang belum aktif.');
    } finally {
      setIsStartingSession(false);
    }
  };

  return (
    <div className="space-y-6">
      {/* Header Banner */}
      <div className="rounded-xl bg-gradient-to-r from-blue-900 via-indigo-900 to-slate-900 p-6 text-white shadow-elevated">
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <div className="flex items-center gap-2 mb-2">
              <Badge className="bg-white/10 text-white border-white/20 text-xs">
                Asesmen Digital Mandiri
              </Badge>
              <span className="text-xs text-blue-200">•</span>
              <span className="text-xs text-blue-200">Lab Komputer Terpadu</span>
            </div>
            <h1 className="text-2xl font-bold tracking-tight">Katalog Ujian Komputer (CBT) 🖥️</h1>
            <p className="mt-1 text-sm text-blue-100 max-w-xl">
              Pilih jadwal ujian aktif dan masukkan token ruang lab yang diberikan oleh pengawas untuk memulai
              pengerjaan asesmen.
            </p>
          </div>

          <Link href="/portal/siswa">
            <Button variant="outline" size="sm" className="bg-white/10 text-white border-white/20 hover:bg-white/20 text-xs gap-1.5">
              <ArrowLeft className="h-3.5 w-3.5" /> Kembali ke Beranda
            </Button>
          </Link>
        </div>
      </div>

      {error && (
        <div className="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs flex items-center gap-2">
          <AlertCircle className="h-4 w-4 shrink-0" />
          <span>{error}</span>
        </div>
      )}

      {/* Exam Cards Grid */}
      {isLoading ? (
        <div className="py-16 text-center text-xs text-slate-400">Memuat katalog ujian CBT...</div>
      ) : exams.length === 0 ? (
        <div className="p-12 text-center text-xs text-slate-500 bg-white rounded-xl border border-slate-200">
          Saat ini belum ada jadwal ujian CBT yang dialokasikan untuk Anda.
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
          {exams.map((exam) => {
            const isLive = exam.status === 'active' || exam.status === 'berlangsung';
            const isClosed = exam.status === 'selesai';

            return (
              <Card
                key={exam.id}
                className={`flex flex-col justify-between transition-all hover:shadow-md ${
                  isLive ? 'border-blue-400 ring-1 ring-blue-400/20' : 'border-slate-200'
                }`}
              >
                <CardHeader className="pb-3">
                  <div className="flex items-center justify-between gap-2 mb-1.5">
                    <Badge
                      className={`text-[10px] font-semibold uppercase ${
                        isLive
                          ? 'bg-emerald-100 text-emerald-800 border-emerald-300'
                          : isClosed
                          ? 'bg-slate-100 text-slate-600'
                          : 'bg-amber-100 text-amber-800'
                      }`}
                    >
                      {isLive ? 'Siap Dikerjakan' : isClosed ? 'Selesai' : 'Terjadwal'}
                    </Badge>
                    <span className="text-[11px] font-medium text-slate-500 capitalize">
                      {exam.exam_type}
                    </span>
                  </div>

                  <CardTitle className="text-base font-bold text-slate-900 leading-snug">
                    {exam.title}
                  </CardTitle>
                  <CardDescription className="text-xs text-slate-500 font-medium mt-0.5">
                    {exam.subject?.name || 'Mata Pelajaran'}
                  </CardDescription>
                </CardHeader>

                <CardContent className="space-y-4 pt-0">
                  <div className="space-y-2 text-xs text-slate-600 bg-slate-50 p-3 rounded-lg border border-slate-100">
                    <div className="flex items-center justify-between">
                      <span className="flex items-center gap-1.5 text-slate-500">
                        <Clock className="h-3.5 w-3.5" /> Durasi Pengerjaan:
                      </span>
                      <b className="text-slate-800">{exam.duration_min} Menit</b>
                    </div>
                    <div className="flex items-center justify-between">
                      <span className="flex items-center gap-1.5 text-slate-500">
                        <Calendar className="h-3.5 w-3.5" /> Waktu Mulai:
                      </span>
                      <span className="text-slate-800">
                        {new Date(exam.start_at).toLocaleDateString('id-ID', {
                          day: 'numeric',
                          month: 'short',
                          hour: '2-digit',
                          minute: '2-digit',
                        })}
                      </span>
                    </div>
                    <div className="flex items-center justify-between">
                      <span className="flex items-center gap-1.5 text-slate-500">
                        <Award className="h-3.5 w-3.5" /> Kriteria KKTP:
                      </span>
                      <b className="text-slate-800">{exam.passing_grade ?? 75} Poin</b>
                    </div>
                  </div>

                  <div>
                    {isLive ? (
                      <Button
                        onClick={() => {
                          setSelectedExam(exam);
                          setTokenInput('');
                          setTokenError(null);
                        }}
                        className="w-full bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold gap-1.5 shadow-sm"
                      >
                        <Key className="h-3.5 w-3.5" />
                        Masukkan Token & Mulai
                      </Button>
                    ) : isClosed ? (
                      <Button variant="outline" disabled className="w-full text-xs text-slate-400">
                        Ujian Telah Berakhir
                      </Button>
                    ) : (
                      <Button variant="outline" disabled className="w-full text-xs text-slate-400">
                        Menunggu Jadwal Mulai
                      </Button>
                    )}
                  </div>
                </CardContent>
              </Card>
            );
          })}
        </div>
      )}

      {/* Token Prompt Modal */}
      {selectedExam && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
          <div className="w-full max-w-sm rounded-2xl bg-white p-6 shadow-2xl space-y-4">
            <div className="flex items-center justify-between border-b pb-3">
              <div>
                <h3 className="font-bold text-slate-900 text-sm">Masuk Ruang Ujian CBT</h3>
                <p className="text-[11px] text-slate-500 truncate max-w-[220px]">{selectedExam.title}</p>
              </div>
              <button onClick={() => setSelectedExam(null)} className="text-slate-400 hover:text-slate-600">
                <X className="h-4 w-4" />
              </button>
            </div>

            <form onSubmit={handleStartExam} className="space-y-4">
              <div>
                <label className="text-xs font-semibold text-slate-700 block mb-1">
                  Token Ujian dari Pengawas:
                </label>
                <Input
                  autoFocus
                  value={tokenInput}
                  onChange={(e) => setTokenInput(e.target.value.toUpperCase())}
                  placeholder="Contoh: LAB2026"
                  className="text-center font-mono font-bold text-base tracking-widest uppercase h-11"
                  required
                />
                <p className="text-[11px] text-slate-500 mt-1">
                  Minta token 6-8 digit kepada proctor atau guru pengawas lab komputer Anda.
                </p>
              </div>

              {tokenError && (
                <div className="p-2.5 rounded-lg bg-rose-50 border border-rose-200 text-rose-700 text-xs flex items-center gap-2">
                  <AlertCircle className="h-4 w-4 shrink-0" />
                  <span>{tokenError}</span>
                </div>
              )}

              <div className="flex items-center justify-end gap-2 pt-2 border-t">
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={() => setSelectedExam(null)}
                  className="text-xs"
                >
                  Batal
                </Button>
                <Button
                  type="submit"
                  size="sm"
                  disabled={isStartingSession}
                  className="bg-blue-600 hover:bg-blue-500 text-white text-xs gap-1"
                >
                  {isStartingSession ? 'Memverifikasi...' : 'Masuk Ujian'} <ArrowRight className="h-3.5 w-3.5" />
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
