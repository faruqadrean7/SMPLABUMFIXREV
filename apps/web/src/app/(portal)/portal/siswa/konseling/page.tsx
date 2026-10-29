'use client';

import React, { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { 
  HeartHandshake, 
  ShieldCheck, 
  Lock, 
  Calendar, 
  Clock, 
  MapPin, 
  AlertTriangle, 
  Send, 
  CheckCircle2, 
  ShieldAlert, 
  ExternalLink,
  MessageCircle,
  RotateCw
} from 'lucide-react';
import { portalApi } from '@/features/portal/portal-api';
import { StudentCounselingItem } from '@/features/portal/types';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { LoadingSpinner } from '@/components/shared/loading-spinner';
import { EmptyState } from '@/components/shared/empty-state';

export default function StudentKonselingPage() {
  const queryClient = useQueryClient();

  // Form State
  const [topic, setTopic] = useState('');
  const [category, setCategory] = useState<string>('Pengembangan Diri');
  const [preferredDate, setPreferredDate] = useState('');
  const [preferredTime, setPreferredTime] = useState('Sepulang Sekolah (13:30 WIB)');
  const [reason, setReason] = useState('');
  const [isSuccessAlert, setIsSuccessAlert] = useState(false);
  const [submitError, setSubmitError] = useState<string | null>(null);

  // Fetch riwayat permohonan konseling
  const { data: requests = [], isLoading, isError, refetch } = useQuery({
    queryKey: ['student-counseling'],
    queryFn: () => portalApi.getStudentCounseling(),
  });

  const submitMutation = useMutation({
    mutationFn: (payload: { topic: string; category: string; preferred_date?: string; preferred_time?: string; reason: string }) =>
      portalApi.submitStudentCounseling(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['student-counseling'] });
      setTopic('');
      setReason('');
      setPreferredDate('');
      setSubmitError(null);
      setIsSuccessAlert(true);
      setTimeout(() => setIsSuccessAlert(false), 4000);
    },
    onError: (err: any) => {
      setSubmitError(err?.message || 'Gagal mengajukan janji temu konseling. Silakan periksa kembali formulir.');
    },
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!topic || !reason) return;

    submitMutation.mutate({
      topic,
      category,
      preferred_date: preferredDate || undefined,
      preferred_time: preferredTime || undefined,
      reason,
    });
  };

  return (
    <div className="space-y-6 text-slate-900">
      {/* Header Halaman */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-slate-200">
        <div>
          <h1 className="text-2xl font-bold tracking-tight text-slate-900 flex items-center gap-2.5">
            <HeartHandshake className="h-6 w-6 text-brand-blue" />
            Layanan Bimbingan & Konseling Siswa (BK)
          </h1>
          <p className="text-xs text-slate-600 mt-1">
            Ruang aman dan rahasia bagi siswa untuk berkonsultasi mengenai studi, karir, pertemanan, dan pengembangan diri.
          </p>
        </div>

        <Button
          variant="outline"
          size="sm"
          onClick={() => refetch()}
          className="text-xs h-8 gap-1.5 self-start md:self-auto"
        >
          <RotateCw className="h-3.5 w-3.5" />
          Segarkan Data
        </Button>
      </div>

      {/* Banner Kerahasiaan (Privacy Guarantee) */}
      <div className="p-4 bg-blue-50/70 border border-blue-200 rounded-lg flex items-start gap-3">
        <Lock className="h-5 w-5 text-brand-blue shrink-0 mt-0.5" />
        <div className="text-xs text-slate-700 space-y-1">
          <div className="font-semibold text-brand-blue">Jaminan Kerahasiaan Konseling (Privasi Terjaga)</div>
          <p className="text-slate-600 leading-relaxed">
            Sesuai kode etik Bimbingan Konseling dan kebijakan keamanan SISTLABUM, isi pembicaraan dan konsultasi bersifat rahasia dan hanya diketahui oleh Anda serta Guru BK yang bertugas.
          </p>
        </div>
      </div>

      {isSuccessAlert && (
        <div className="p-4 bg-emerald-50 border border-emerald-200 rounded-lg flex items-center gap-2 text-xs text-emerald-800">
          <CheckCircle2 className="h-4 w-4 text-emerald-600" />
          Permohonan janji temu konseling berhasil dikirim! Guru BK akan memproses dan mengonfirmasi jadwal Anda segera.
        </div>
      )}

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Form Pengajuan Konseling Mandiri */}
        <div className="lg:col-span-1">
          <Card className="border-slate-200 bg-white shadow-xs">
            <CardHeader className="pb-3 border-b border-slate-100">
              <CardTitle className="text-base font-bold text-slate-900 flex items-center gap-2">
                <MessageCircle className="h-4 w-4 text-brand-blue" />
                Buat Janji Temu Konseling
              </CardTitle>
              <CardDescription className="text-xs text-slate-500">
                Isi formulir di bawah ini untuk mengatur waktu konsultasi dengan guru BK.
              </CardDescription>
            </CardHeader>
            <CardContent className="pt-4">
              <form onSubmit={handleSubmit} className="space-y-4 text-xs">
                {submitError && (
                  <div className="p-3 bg-red-50 border border-red-200 text-status-danger text-xs rounded-lg flex items-center gap-2">
                    <AlertTriangle className="h-4 w-4 shrink-0" />
                    <span>{submitError}</span>
                  </div>
                )}

                <div className="space-y-1.5">
                  <label className="font-semibold text-slate-700">Topik Pembahasan:</label>
                  <Input
                    placeholder="Contoh: Kesulitan mengatur waktu belajar & ekskul"
                    value={topic}
                    onChange={(e) => setTopic(e.target.value)}
                    required
                    className="text-xs"
                  />
                </div>

                <div className="space-y-1.5">
                  <label className="font-semibold text-slate-700">Bidang Masalah / Kategori:</label>
                  <select
                    value={category}
                    onChange={(e) => setCategory(e.target.value)}
                    className="w-full h-9 rounded-md border border-slate-300 bg-white px-3 text-xs focus:outline-none focus:ring-2 focus:ring-brand-blue"
                  >
                    <option value="Akademik">Akademik & Pelajaran</option>
                    <option value="Sosial & Teman">Sosial & Pertemanan</option>
                    <option value="Pengembangan Diri">Pengembangan Diri & Karir</option>
                    <option value="Keluarga">Keluarga & Pribadi</option>
                  </select>
                </div>

                <div className="space-y-1.5">
                  <label className="font-semibold text-slate-700">Preferensi Tanggal (Opsional):</label>
                  <Input
                    type="date"
                    value={preferredDate}
                    onChange={(e) => setPreferredDate(e.target.value)}
                    className="text-xs"
                  />
                </div>

                <div className="space-y-1.5">
                  <label className="font-semibold text-slate-700">Waktu yang Diharapkan:</label>
                  <select
                    value={preferredTime}
                    onChange={(e) => setPreferredTime(e.target.value)}
                    className="w-full h-9 rounded-md border border-slate-300 bg-white px-3 text-xs focus:outline-none focus:ring-2 focus:ring-brand-blue"
                  >
                    <option value="Sepulang Sekolah (13:30 WIB)">Sepulang Sekolah (13:30 WIB)</option>
                    <option value="Istirahat Pertama (09:40 WIB)">Istirahat Pertama (09:40 WIB)</option>
                    <option value="Istirahat Kedua (12:00 WIB)">Istirahat Kedua (12:00 WIB)</option>
                    <option value="Fleksibel Sesuai Jadwal BK">Fleksibel Sesuai Jadwal BK</option>
                  </select>
                </div>

                <div className="space-y-1.5">
                  <label className="font-semibold text-slate-700">Penjelasan Singkat:</label>
                  <Textarea
                    placeholder="Ceritakan sedikit tentang apa yang ingin Anda diskusikan..."
                    rows={4}
                    value={reason}
                    onChange={(e) => setReason(e.target.value)}
                    required
                    className="text-xs"
                  />
                </div>

                <Button
                  type="submit"
                  disabled={submitMutation.isPending}
                  className="w-full bg-brand-blue text-white hover:bg-blue-800 text-xs gap-2"
                >
                  <Send className="h-3.5 w-3.5" />
                  {submitMutation.isPending ? 'Mengirim Permohonan...' : 'Ajukan Janji Temu'}
                </Button>
              </form>
            </CardContent>
          </Card>
        </div>

        {/* Riwayat Permohonan Konseling */}
        <div className="lg:col-span-2 space-y-4">
          <div className="flex items-center justify-between">
            <h2 className="text-base font-bold text-slate-900">Riwayat Janji Temu Konseling Saya</h2>
            <Badge variant="outline" className="text-xs">
              Total: {requests.length}
            </Badge>
          </div>

          {isLoading && (
            <div className="py-12 bg-white rounded-lg border border-slate-200 flex justify-center">
              <LoadingSpinner label="Memuat riwayat konseling..." />
            </div>
          )}

          {isError && (
            <Card className="border-red-200 bg-red-50/50">
              <CardContent className="p-6 text-center">
                <AlertTriangle className="h-8 w-8 text-status-danger mx-auto mb-2" />
                <h3 className="font-semibold text-slate-900">Gagal Memuat Riwayat</h3>
                <p className="text-xs text-slate-600 mt-1 mb-4">Tidak dapat terhubung ke server BK.</p>
                <Button size="sm" onClick={() => refetch()}>Coba Lagi</Button>
              </CardContent>
            </Card>
          )}

          {!isLoading && !isError && requests.length === 0 && (
            <div className="bg-white rounded-lg border border-slate-200 p-8">
              <EmptyState
                icon={<HeartHandshake className="h-6 w-6" />}
                title="Belum Ada Janji Temu"
                description="Anda belum memiliki pengajuan janji temu konseling aktif. Silakan gunakan formulir di samping jika membutuhkan bantuan atau konsultasi."
              />
            </div>
          )}

          {!isLoading && !isError && requests.length > 0 && (
            <div className="space-y-3">
              {requests.map((req) => (
                <Card key={req.id} className="border-slate-200 bg-white hover:border-blue-200 transition-all shadow-xs">
                  <CardContent className="p-4 space-y-3">
                    <div className="flex items-start justify-between gap-3">
                      <div>
                        <Badge variant="outline" className="text-[10px] bg-slate-50 text-slate-700 mb-1.5">
                          {req.category}
                        </Badge>
                        <h3 className="font-bold text-sm text-slate-900">{req.topic}</h3>
                        <p className="text-xs text-slate-500 mt-0.5">
                          Pembina BK: <strong>{req.counselorName}</strong>
                        </p>
                      </div>

                      <div>
                        {req.status === 'menunggu' && (
                          <Badge className="bg-amber-100 text-amber-800">Menunggu Konfirmasi</Badge>
                        )}
                        {req.status === 'dijadwalkan' && (
                          <Badge className="bg-blue-100 text-blue-800">Dijadwalkan</Badge>
                        )}
                        {req.status === 'selesai' && (
                          <Badge className="bg-emerald-100 text-emerald-800">Selesai</Badge>
                        )}
                        {req.status === 'ditolak' && (
                          <Badge className="bg-red-100 text-red-800">Ditolak / Dijadwal Ulang</Badge>
                        )}
                      </div>
                    </div>

                    {req.notes && (
                      <p className="text-xs text-slate-600 bg-slate-50 p-2.5 rounded-md border border-slate-100">
                        {req.notes}
                      </p>
                    )}

                    <div className="flex flex-wrap items-center justify-between text-[11px] text-slate-500 pt-2 border-t border-slate-100">
                      <div className="flex items-center gap-1.5">
                        <Calendar className="h-3.5 w-3.5 text-slate-400" />
                        <span>Waktu Konseling: <strong className="text-slate-700">{req.preferredSchedule}</strong></span>
                      </div>
                      <div className="flex items-center gap-1">
                        <MapPin className="h-3.5 w-3.5 text-slate-400" />
                        <span>{req.roomName || 'Ruang Konseling BK Labschool UM'}</span>
                      </div>
                    </div>
                  </CardContent>
                </Card>
              ))}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
