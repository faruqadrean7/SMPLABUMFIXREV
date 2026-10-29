'use client';

import React, { useState } from 'react';
import Link from 'next/link';
import { useAuth } from '@/providers/auth-provider';
import {
  useTeacherSchedules,
  useTeacherAssignments,
  useTeacherSelfAttendance,
} from '@/features/portal/guru';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { LoadingSpinner } from '@/components/shared/loading-spinner';
import {
  Calendar,
  Clock,
  BookOpen,
  CheckSquare,
  FileText,
  Sparkles,
  ArrowRight,
  AlertCircle,
  CheckCircle2,
  Bell,
  Award,
  Layers,
  UserCheck,
  Camera,
  X,
  ShieldCheck,
  AlertTriangle,
} from 'lucide-react';
import { Avatar } from '@/components/ui/avatar';

export default function TeacherDashboardPage() {
  const { user } = useAuth();
  const { data: schedules = [], isLoading: isLoadingSchedules } = useTeacherSchedules();
  const { data: assignments = [], isLoading: isLoadingAssignments } = useTeacherAssignments();
  const { data: selfAttendance } = useTeacherSelfAttendance();

  const teacherName =
    user?.profile?.name ||
    user?.profile?.fullName ||
    user?.username ||
    'Bapak/Ibu Guru';

  const todayDayOfWeek = new Date().getDay(); // 0 is Sunday, 1 is Monday, etc.
  const safeSchedules = Array.isArray(schedules) ? schedules : [];
  const todaySchedules = safeSchedules.filter((s) => s.dayOfWeek === (todayDayOfWeek || 1));
  const unrecordedSchedules = todaySchedules.filter((s) => !s.isAttendanceRecorded);
  const safeAssignments = Array.isArray(assignments) ? assignments : [];
  const pendingAssignments = safeAssignments.filter((a) => a.submittedCount > a.gradedCount);

  // Modal & Pengaturan Foto Profil Guru
  const [isPhotoModalOpen, setIsPhotoModalOpen] = useState(false);
  const [photoPreviewUrl, setPhotoPreviewUrl] = useState<string | null>(null);
  const [photoValidationMsg, setPhotoValidationMsg] = useState<string | null>(null);
  const [photoValidationSuccess, setPhotoValidationSuccess] = useState<boolean>(false);

  return (
    <div className="space-y-6">
      {/* Welcome & Profile Banner */}
      <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4 bg-white p-6 rounded-2xl border border-slate-200 shadow-xs">
        <div className="flex items-center gap-4">
          <div className="relative group shrink-0">
            <Avatar
              src={(user?.profile?.photo_url as string) || (user?.profile?.photoUrl as string) || (user?.profile?.avatarUrl as string)}
              name={teacherName}
              size="xl"
              shape="rounded"
              alt={`Foto ${teacherName}`}
              className="border-2 border-slate-100 shadow-xs"
            />
          </div>
          <div>
            <div className="inline-flex items-center gap-1.5 px-3 py-1 bg-blue-50 text-brand-blue rounded-full text-xs font-semibold mb-2">
              <Sparkles className="h-3.5 w-3.5" />
              <span>Portal KBM Guru • Semester Gasal 2024/2025</span>
            </div>
            <h1 className="text-2xl font-black text-slate-900 tracking-tight">
              Selamat Pagi, {teacherName} 👋
            </h1>
            <p className="text-sm text-slate-600 mt-1">
              Siap menginspirasi siswa hari ini? Berikut ringkasan agenda mengajar dan tugas KBM Anda.
            </p>
          </div>
        </div>

        <div className="flex flex-wrap items-center gap-3">
          <Button
            size="sm"
            variant="outline"
            onClick={() => setIsPhotoModalOpen(true)}
            className="gap-1.5 text-xs text-slate-700 border-slate-200"
          >
            <Camera className="h-3.5 w-3.5 text-brand-blue" />
            <span>Foto Profil</span>
          </Button>

          <div className="bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-right">
            <span className="text-[10px] text-slate-400 font-bold block uppercase">Presensi Kerja</span>
            <span className="text-xs font-bold text-status-success flex items-center justify-end gap-1">
              <span className="h-2 w-2 rounded-full bg-status-success" />
              {selfAttendance?.checkInTime ? `Masuk: ${selfAttendance.checkInTime}` : 'Belum Absen'}
            </span>
          </div>
          <Link href="/portal/guru/presensi-saya">
            <Button size="sm" variant="outline" className="gap-1.5">
              <Clock className="h-4 w-4" />
              <span>Presensi Mandiri</span>
            </Button>
          </Link>
        </div>
      </div>

      {/* Unrecorded Attendance Alert Banner */}
      {unrecordedSchedules.length > 0 && (
        <div className="bg-amber-50 border border-amber-200 rounded-2xl p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 shadow-xs">
          <div className="flex items-center gap-3">
            <div className="p-2 rounded-xl bg-amber-100 text-amber-800 shrink-0">
              <AlertCircle className="h-5 w-5" />
            </div>
            <div>
              <h3 className="font-bold text-sm text-amber-900">
                Ada {unrecordedSchedules.length} Sesi KBM Hari Ini yang Belum Diabsen
              </h3>
              <p className="text-xs text-amber-700 mt-0.5">
                Segera catat kehadiran siswa per sesi agar rekapitulasi presensi sekolah tetap akurat.
              </p>
            </div>
          </div>
          <Link href="/portal/guru/presensi">
            <Button size="sm" className="bg-amber-600 hover:bg-amber-700 text-white font-bold gap-1.5 shrink-0">
              <CheckSquare className="h-4 w-4" />
              <span>Input Presensi Sekarang</span>
            </Button>
          </Link>
        </div>
      )}

      {/* KPI Stats Cards */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <Card className="border-slate-200">
          <CardHeader className="pb-1 pt-4">
            <CardDescription className="text-xs font-medium">Jadwal Mengajar Hari Ini</CardDescription>
            <CardTitle className="text-2xl font-black text-slate-900">
              {todaySchedules.length} <span className="text-xs font-normal text-slate-500">sesi</span>
            </CardTitle>
          </CardHeader>
          <CardContent className="pt-0 text-[11px] text-slate-500">
            {unrecordedSchedules.length} belum diabsen
          </CardContent>
        </Card>

        <Card className="border-slate-200">
          <CardHeader className="pb-1 pt-4">
            <CardDescription className="text-xs font-medium">Tugas Perlu Dinilai</CardDescription>
            <CardTitle className="text-2xl font-black text-brand-blue">
              {pendingAssignments.length} <span className="text-xs font-normal text-slate-500">tugas</span>
            </CardTitle>
          </CardHeader>
          <CardContent className="pt-0 text-[11px] text-slate-500">
            Dari kelas 7-A & 7-B
          </CardContent>
        </Card>

        <Card className="border-slate-200">
          <CardHeader className="pb-1 pt-4">
            <CardDescription className="text-xs font-medium">Jam Mengajar Bulan Ini</CardDescription>
            <CardTitle className="text-2xl font-black text-status-success">
              {selfAttendance?.workHoursThisMonth || 128} <span className="text-xs font-normal text-slate-500">/ 160 jam</span>
            </CardTitle>
          </CardHeader>
          <CardContent className="pt-0 text-[11px] text-slate-500">
            Target 24 jam tatap muka/minggu
          </CardContent>
        </Card>

        <Card className="border-slate-200">
          <CardHeader className="pb-1 pt-4">
            <CardDescription className="text-xs font-medium">Slip Gaji Terakhir</CardDescription>
            <CardTitle className="text-base font-black text-purple-700">
              Oktober 2026
            </CardTitle>
          </CardHeader>
          <CardContent className="pt-0 text-[11px] text-slate-500 flex items-center justify-between">
            <span>Sudah Cair</span>
            <Link href="/portal/guru/slip-gaji" className="text-brand-blue font-bold hover:underline">
              Unduh PDF
            </Link>
          </CardContent>
        </Card>
      </div>

      {/* Main Content Grid: Schedule Today & Quick Shortcuts */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Left: Schedule Today */}
        <div className="lg:col-span-2 space-y-4">
          <div className="flex items-center justify-between">
            <h2 className="text-base font-black text-slate-900 flex items-center gap-2">
              <Calendar className="h-5 w-5 text-brand-blue" />
              <span>Jadwal Mengajar Hari Ini</span>
            </h2>
            <Link href="/portal/guru/jadwal" className="text-xs text-brand-blue font-bold hover:underline">
              Lihat Seluruh Minggu ➔
            </Link>
          </div>

          {isLoadingSchedules ? (
            <div className="min-h-[200px] flex items-center justify-center bg-white rounded-2xl border border-slate-200">
              <LoadingSpinner label="Memuat jadwal hari ini..." size="md" />
            </div>
          ) : todaySchedules.length === 0 ? (
            <Card className="border-slate-200 py-8 text-center">
              <CardContent>
                <Calendar className="h-10 w-10 text-slate-300 mx-auto mb-2" />
                <p className="text-xs font-bold text-slate-600">Tidak ada jadwal tatap muka hari ini.</p>
                <p className="text-[11px] text-slate-400 mt-0.5">Manfaatkan waktu untuk memeriksa tugas atau menyiapkan modul ajar.</p>
              </CardContent>
            </Card>
          ) : (
            <div className="space-y-3">
              {todaySchedules.map((item) => (
                <div
                  key={item.id}
                  className="bg-white p-4 rounded-xl border border-slate-200 shadow-xs hover:border-brand-blue transition-colors flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3"
                >
                  <div className="flex items-start gap-3">
                    <div className="p-2.5 rounded-xl bg-blue-50 text-brand-blue font-black text-xs shrink-0 mt-0.5">
                      {item.startTime}
                    </div>
                    <div>
                      <div className="flex items-center gap-2">
                        <h4 className="font-bold text-sm text-slate-900">{item.subjectName}</h4>
                        <Badge className="bg-blue-50 text-brand-blue border-blue-200 text-[10px]">
                          {item.className}
                        </Badge>
                      </div>
                      <p className="text-xs text-slate-500 mt-0.5">
                        {item.roomName} • {item.startTime} - {item.endTime}
                      </p>
                    </div>
                  </div>

                  <div className="flex items-center gap-2 self-end sm:self-center">
                    {item.isAttendanceRecorded ? (
                      <Badge className="bg-emerald-50 text-status-success border-emerald-200 gap-1 text-[11px]">
                        <CheckCircle2 className="h-3.5 w-3.5" />
                        <span>Sudah Diabsen</span>
                      </Badge>
                    ) : (
                      <Link href="/portal/guru/presensi">
                        <Button size="sm" className="gap-1 font-bold">
                          <CheckSquare className="h-3.5 w-3.5" />
                          <span>Absen Kelas</span>
                        </Button>
                      </Link>
                    )}
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>

        {/* Right: Quick Action Menu & Notices */}
        <div className="space-y-4">
          <h2 className="text-base font-black text-slate-900 flex items-center gap-2">
            <BookOpen className="h-5 w-5 text-brand-blue" />
            <span>Menu Cepat Guru</span>
          </h2>

          <div className="grid grid-cols-2 gap-3">
            <Link
              href="/portal/guru/jurnal"
              className="p-3 bg-white rounded-xl border border-slate-200 hover:border-brand-blue hover:shadow-xs transition-all flex flex-col items-center text-center group"
            >
              <div className="p-2.5 rounded-xl bg-blue-50 text-brand-blue group-hover:bg-brand-blue group-hover:text-white transition-colors mb-2">
                <BookOpen className="h-5 w-5" />
              </div>
              <span className="text-xs font-bold text-slate-900">Tulis Jurnal</span>
              <span className="text-[10px] text-slate-500 mt-0.5">Catatan KBM harian</span>
            </Link>

            <Link
              href="/portal/guru/nilai"
              className="p-3 bg-white rounded-xl border border-slate-200 hover:border-brand-blue hover:shadow-xs transition-all flex flex-col items-center text-center group"
            >
              <div className="p-2.5 rounded-xl bg-purple-50 text-purple-600 group-hover:bg-purple-600 group-hover:text-white transition-colors mb-2">
                <FileText className="h-5 w-5" />
              </div>
              <span className="text-xs font-bold text-slate-900">Buku Nilai</span>
              <span className="text-[10px] text-slate-500 mt-0.5">Input nilai massal</span>
            </Link>

            <Link
              href="/portal/guru/materi"
              className="p-3 bg-white rounded-xl border border-slate-200 hover:border-brand-blue hover:shadow-xs transition-all flex flex-col items-center text-center group"
            >
              <div className="p-2.5 rounded-xl bg-amber-50 text-amber-700 group-hover:bg-amber-600 group-hover:text-white transition-colors mb-2">
                <Layers className="h-5 w-5" />
              </div>
              <span className="text-xs font-bold text-slate-900">Upload Modul</span>
              <span className="text-[10px] text-slate-500 mt-0.5">Bahan ajar siswa</span>
            </Link>

            <Link
              href="/portal/guru/tugas"
              className="p-3 bg-white rounded-xl border border-slate-200 hover:border-brand-blue hover:shadow-xs transition-all flex flex-col items-center text-center group"
            >
              <div className="p-2.5 rounded-xl bg-emerald-50 text-status-success group-hover:bg-status-success group-hover:text-white transition-colors mb-2">
                <Award className="h-5 w-5" />
              </div>
              <span className="text-xs font-bold text-slate-900">Nilai Tugas</span>
              <span className="text-[10px] text-slate-500 mt-0.5">Koreksi lembar kerja</span>
            </Link>
          </div>

          {/* Announcements Card */}
          <Card className="border-slate-200">
            <CardHeader className="pb-2">
              <div className="flex items-center gap-2">
                <Bell className="h-4 w-4 text-brand-blue" />
                <CardTitle className="text-sm font-bold">Pengumuman Sekolah</CardTitle>
              </div>
            </CardHeader>
            <CardContent className="space-y-3 pt-0 text-xs">
              <div className="border-b border-slate-100 pb-2">
                <span className="text-[10px] text-slate-400 font-bold">14 Oktober 2026</span>
                <p className="font-bold text-slate-800 mt-0.5">Batas Penguncian Nilai PTS</p>
                <p className="text-slate-500 text-[11px] mt-0.5">
                  Bapak/Ibu guru diharapkan menyelesaikan input nilai PTS sebelum Jumat, 17 Oktober 2026.
                </p>
              </div>
              <div>
                <span className="text-[10px] text-slate-400 font-bold">12 Oktober 2026</span>
                <p className="font-bold text-slate-800 mt-0.5">Rapat Koordinasi P5</p>
                <p className="text-slate-500 text-[11px] mt-0.5">
                  Rapat persiapan gelar karya P5 akan diadakan di Aula Lantai 2 pukul 13.00 WIB.
                </p>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>

      {/* Modal Pengaturan & Pratinjau Foto Profil Pendidik */}
      {isPhotoModalOpen && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs animate-in fade-in duration-150 overflow-y-auto"
          role="dialog"
          aria-modal="true"
        >
          <div className="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-elevated border border-slate-200 my-8">
            <div className="flex items-center justify-between pb-4 border-b border-slate-100">
              <div className="flex items-center gap-2">
                <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-50 text-brand-blue">
                  <Camera className="h-5 w-5" />
                </div>
                <div>
                  <h3 className="text-base font-bold text-slate-900">Foto Profil Resmi Pendidik</h3>
                  <p className="text-xs text-slate-500">Standar Kepegawaian & Dokumen Resmi</p>
                </div>
              </div>
              <button
                type="button"
                onClick={() => {
                  setIsPhotoModalOpen(false);
                  setPhotoPreviewUrl(null);
                  setPhotoValidationMsg(null);
                }}
                className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition-colors"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            <div className="py-5 space-y-4 text-center">
              <div className="flex justify-center">
                <Avatar
                  src={photoPreviewUrl || (user?.profile?.photo_url as string) || (user?.profile?.photoUrl as string) || (user?.profile?.avatarUrl as string)}
                  name={teacherName}
                  size="2xl"
                  shape="rounded"
                  alt={`Pasfoto ${teacherName}`}
                  className="border-2 border-slate-200 shadow-md"
                />
              </div>

              <div>
                <h4 className="font-bold text-slate-900 text-sm">{teacherName}</h4>
                <p className="text-xs text-slate-500 font-mono">
                  {user?.profile?.position ? `${user.profile.position} • ` : ''}NIP: {(user?.profile?.nip as string) || '-'}
                </p>
              </div>

              {/* Status Informasi Kebijakan Foto Profil Guru */}
              <div className="p-3.5 rounded-xl bg-blue-50/80 border border-blue-200/60 text-left text-xs text-blue-900 space-y-2">
                <div className="flex items-center gap-2 font-semibold text-brand-blue">
                  <ShieldCheck className="h-4 w-4 shrink-0" />
                  <span>Kebijakan Foto Pendidik & Kepegawaian</span>
                </div>
                <p className="text-[11px] leading-relaxed text-blue-800">
                  Pasfoto resmi pendidik digunakan pada instrumen raport elektronik, jadwal mengajar, dan buku profil sekolah. Pengkinian data berkas resmi dilakukan secara tersentral melalui Bagian Tata Usaha.
                </p>
                <div className="text-[10px] text-blue-700 font-medium pt-1 border-t border-blue-200/50">
                  Status API: Endpoint unggah mandiri dinonaktifkan demi integritas data; hubungi Tata Usaha untuk pembaruan foto.
                </div>
              </div>

              {/* Uji Kelayakan Format Pasfoto (Client-Side Verification) */}
              <div className="pt-2 text-left space-y-2">
                <label className="block text-xs font-semibold text-slate-700">
                  Uji Pratinjau Kesesuaian Pasfoto:
                </label>
                <input
                  type="file"
                  accept="image/jpeg,image/png,image/webp"
                  onChange={(e) => {
                    const file = e.target.files?.[0];
                    if (!file) return;

                    const allowed = ['image/jpeg', 'image/png', 'image/webp'];
                    if (!allowed.includes(file.type.toLowerCase())) {
                      setPhotoValidationMsg('Format tidak didukung. Harap pilih JPEG, PNG, atau WebP.');
                      setPhotoValidationSuccess(false);
                      setPhotoPreviewUrl(null);
                      return;
                    }

                    if (file.size > 2 * 1024 * 1024) {
                      setPhotoValidationMsg('Ukuran melebihi batas maksimal 2 MB.');
                      setPhotoValidationSuccess(false);
                      setPhotoPreviewUrl(null);
                      return;
                    }

                    setPhotoPreviewUrl(URL.createObjectURL(file));
                    setPhotoValidationMsg('Format dan ukuran pasfoto valid (Memenuhi standar 2 MB).');
                    setPhotoValidationSuccess(true);
                  }}
                  className="block w-full text-xs text-slate-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-brand-blue hover:file:bg-blue-100 cursor-pointer"
                />

                {photoValidationMsg && (
                  <div className={`p-2 rounded-lg text-xs flex items-center gap-1.5 ${
                    photoValidationSuccess ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-red-50 text-red-800 border border-red-200'
                  }`}>
                    {photoValidationSuccess ? (
                      <CheckCircle2 className="h-4 w-4 shrink-0 text-emerald-600" />
                    ) : (
                      <AlertTriangle className="h-4 w-4 shrink-0 text-red-600" />
                    )}
                    <span>{photoValidationMsg}</span>
                  </div>
                )}
              </div>
            </div>

            <div className="flex items-center justify-between pt-4 border-t border-slate-100">
              {photoPreviewUrl && (
                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  onClick={() => {
                    setPhotoPreviewUrl(null);
                    setPhotoValidationMsg(null);
                  }}
                  className="text-xs text-slate-500 hover:text-red-600"
                >
                  Reset Pratinjau
                </Button>
              )}
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => {
                  setIsPhotoModalOpen(false);
                  setPhotoPreviewUrl(null);
                  setPhotoValidationMsg(null);
                }}
                className="ml-auto text-xs"
              >
                Tutup
              </Button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
