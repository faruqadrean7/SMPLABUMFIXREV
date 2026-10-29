'use client';

import React, { useCallback, useEffect, useState } from 'react';
import { useAuth } from '@/providers/auth-provider';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import {
  Calendar,
  BookOpen,
  MonitorCheck,
  Award,
  CheckCircle2,
  Clock,
  ArrowRight,
  HeartHandshake,
  ShieldCheck,
  Lock,
  X,
  AlertTriangle,
  RefreshCw,
  Trophy,
  Plus,
  Trash2,
  ExternalLink,
  Eye,
  Info,
  Medal,
  Camera,
} from 'lucide-react';
import Link from 'next/link';
import { Avatar } from '@/components/ui/avatar';
import { counselingApi } from '@/features/counseling/counseling-api';
import { CounselingCategory } from '@/features/counseling/types';
import { competitionsApi } from '@/features/student-affairs/competitions-api';
import { AchievementItem, CompetitionItem } from '@/features/student-affairs/types';

export default function StudentDashboardPage() {
  const { user } = useAuth();

  // State Layanan Konseling Siswa (SIS-BK-01 / BE-BK-03)
  const [isCounselingModalOpen, setIsCounselingModalOpen] = useState(false);
  const [category, setCategory] = useState<CounselingCategory>('Pribadi');
  const [preferredDate, setPreferredDate] = useState('');
  const [preferredTimeSlot, setPreferredTimeSlot] = useState('');
  const [reason, setReason] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState<string | null>(null);
  const [submitSuccess, setSubmitSuccess] = useState<string | null>(null);

  // State Prestasi & Pengajuan Mandiri Siswa (SIS-STD-01 / BE-STD-03)
  const [achievements, setAchievements] = useState<AchievementItem[]>([]);
  const [competitions, setCompetitions] = useState<CompetitionItem[]>([]);
  const [isLoadingAchievements, setIsLoadingAchievements] = useState(false);
  const [achievementError, setAchievementError] = useState<string | null>(null);
  const [achievementFilter, setAchievementFilter] = useState<'all' | 'pending' | 'verified' | 'rejected'>('all');

  // Modal Pengajuan Prestasi
  const [isAchievementModalOpen, setIsAchievementModalOpen] = useState(false);
  const [achTitle, setAchTitle] = useState('');
  const [achRankLevel, setAchRankLevel] = useState('Juara 1');
  const [achLevel, setAchLevel] = useState('nasional');
  const [achDate, setAchDate] = useState('');
  const [achCompetitionId, setAchCompetitionId] = useState('');
  const [achNotes, setAchNotes] = useState('');
  const [isSubmittingAch, setIsSubmittingAch] = useState(false);
  const [achSubmitError, setAchSubmitError] = useState<string | null>(null);
  const [achSubmitSuccess, setAchSubmitSuccess] = useState<string | null>(null);

  // Modal Detail & Alasan Penolakan
  const [selectedAchievement, setSelectedAchievement] = useState<AchievementItem | null>(null);
  const [isDetailModalOpen, setIsDetailModalOpen] = useState(false);

  // Modal & Pengaturan Foto Profil Siswa
  const [isPhotoModalOpen, setIsPhotoModalOpen] = useState(false);
  const [photoPreviewUrl, setPhotoPreviewUrl] = useState<string | null>(null);
  const [photoValidationMsg, setPhotoValidationMsg] = useState<string | null>(null);
  const [photoValidationSuccess, setPhotoValidationSuccess] = useState<boolean>(false);

  const studentId = (user?.profile?.id as string) || '';

  const fetchAchievements = useCallback(async () => {
    if (!studentId) return;
    setIsLoadingAchievements(true);
    setAchievementError(null);
    try {
      const [achRes, compRes] = await Promise.all([
        competitionsApi.getAchievements({ student_id: studentId, only_my: true, per_page: 50 }),
        competitionsApi.getCompetitions({ per_page: 50 }),
      ]);
      const safeAchievements = Array.isArray(achRes?.data) ? achRes.data : Array.isArray(achRes) ? (achRes as any) : [];
      const safeCompetitions = Array.isArray(compRes?.data) ? compRes.data : Array.isArray(compRes) ? (compRes as any) : [];
      setAchievements(safeAchievements);
      setCompetitions(safeCompetitions);
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Gagal memuat riwayat prestasi siswa.';
      setAchievementError(msg);
    } finally {
      setIsLoadingAchievements(false);
    }
  }, [studentId]);

  useEffect(() => {
    if (studentId) {
      fetchAchievements();
    }
  }, [studentId, fetchAchievements]);

  const handleSubmitCounseling = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!reason.trim()) {
      setSubmitError('Alasan atau keperluan konseling wajib diisi.');
      return;
    }

    setIsSubmitting(true);
    setSubmitError(null);
    try {
      if (!studentId) {
        setSubmitError('Identitas akun siswa tidak valid atau belum terhubung dengan data akademik.');
        setIsSubmitting(false);
        return;
      }

      await counselingApi.createRequest({
        student_id: studentId,
        topic: `[${category}] Bimbingan Konseling Siswa`,
        preferred_date: preferredDate || undefined,
        preferred_time: preferredTimeSlot.trim() || undefined,
        notes: reason.trim(),
      });

      setSubmitSuccess('Permintaan konseling berhasil diajukan. Guru BK akan meninjau jadwal Anda.');
      setReason('');
      setPreferredDate('');
      setPreferredTimeSlot('');
      setTimeout(() => {
        setIsCounselingModalOpen(false);
        setSubmitSuccess(null);
      }, 2000);
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Gagal mengajukan permintaan konseling.';
      setSubmitError(msg);
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleCreateAchievement = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!achTitle.trim()) {
      setAchSubmitError('Nama kejuaraan atau prestasi wajib diisi.');
      return;
    }
    if (!achDate) {
      setAchSubmitError('Tanggal perolehan kejuaraan wajib diisi.');
      return;
    }

    setIsSubmittingAch(true);
    setAchSubmitError(null);
    try {
      if (!studentId) {
        setAchSubmitError('Identitas akun siswa tidak valid.');
        setIsSubmittingAch(false);
        return;
      }

      await competitionsApi.createAchievement({
        student_id: studentId,
        title: achTitle.trim(),
        rank_level: achRankLevel,
        achieved_at: achDate,
        competition_id: achCompetitionId || undefined,
        notes: achNotes.trim() || `Tingkat ${achLevel}`,
      });

      setAchSubmitSuccess('Pengajuan prestasi berhasil dikirim! Menunggu verifikasi tim Kesiswaan.');
      setAchTitle('');
      setAchDate('');
      setAchCompetitionId('');
      setAchNotes('');
      fetchAchievements();

      setTimeout(() => {
        setIsAchievementModalOpen(false);
        setAchSubmitSuccess(null);
      }, 2000);
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Gagal mengirim pengajuan prestasi.';
      setAchSubmitError(msg);
    } finally {
      setIsSubmittingAch(false);
    }
  };

  const handleDeletePendingAchievement = async (id: string) => {
    if (!confirm('Apakah Anda yakin ingin membatalkan pengajuan prestasi ini?')) {
      return;
    }
    try {
      await competitionsApi.deleteAchievement(id);
      fetchAchievements();
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Gagal membatalkan pengajuan.';
      alert(msg);
    }
  };

  const studentName =
    user?.profile?.name ||
    user?.profile?.fullName ||
    user?.username ||
    'Siswa';

  const nisLabel = user?.profile?.nis ? `NIS: ${user.profile.nis}` : 'Siswa Aktif';
  const classLabel = user?.profile?.className ? `Kelas ${user.profile.className}` : 'Labschool UM';

  const safeAchievements = Array.isArray(achievements) ? achievements : [];
  const filteredAchievements = safeAchievements.filter((a) => {
    if (achievementFilter === 'all') return true;
    return a.verification_status === achievementFilter;
  });

  const totalVerifiedAchievements = safeAchievements.filter((a) => a.verification_status === 'verified').length;
  const totalPendingAchievements = safeAchievements.filter((a) => a.verification_status === 'pending').length;

  return (
    <div className="space-y-6">
      {/* Kartu Profil Siswa */}
      <div className="rounded-xl bg-gradient-to-r from-brand-blue to-indigo-900 p-6 text-white shadow-elevated">
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-5">
          <div className="flex items-center gap-4">
            <div className="relative group shrink-0">
              <Avatar
                src={(user?.profile?.photo_url as string) || (user?.profile?.photoUrl as string) || (user?.profile?.avatarUrl as string)}
                name={studentName}
                size="xl"
                shape="rounded"
                alt={`Foto ${studentName}`}
                className="border-2 border-white/30 shadow-md bg-white/10"
              />
            </div>
            <div>
              <div className="flex items-center gap-2 mb-1">
                <Badge variant="outline" className="text-white border-white/30 bg-white/10 text-xs">
                  {classLabel}
                </Badge>
                <span className="text-xs text-blue-200">•</span>
                <span className="text-xs text-blue-200">{nisLabel}</span>
              </div>
              <h1 className="text-2xl font-bold tracking-tight">Halo, {studentName}!</h1>
              <p className="mt-1 text-sm text-blue-100 max-w-xl">
                Selamat datang di portal akademik Anda. Pantau jadwal belajar, tugas KBM, catatan
                kehadiran, prestasi kejuaraan, dan ujian CBT di sini.
              </p>
            </div>
          </div>

          <div className="flex flex-wrap items-center gap-2 self-start sm:self-auto shrink-0">
            <Button
              size="sm"
              variant="outline"
              onClick={() => setIsPhotoModalOpen(true)}
              className="text-xs gap-1.5 bg-white/10 hover:bg-white/20 text-white border-white/30"
            >
              <Camera className="h-3.5 w-3.5" />
              <span>Foto Profil</span>
            </Button>
            <div className="p-2.5 rounded-lg bg-white/10 border border-white/20 text-xs">
              <div className="text-[10px] text-blue-200">Tahun Ajaran</div>
              <div className="font-semibold text-white">2024/2025 Ganjil</div>
            </div>
          </div>
        </div>
      </div>

      {/* Ringkasan Status Harian Siswa */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <Card>
          <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
            <CardTitle className="text-xs font-semibold text-slate-500 uppercase">
              Presensi Hari Ini
            </CardTitle>
            <CheckCircle2 className="h-4 w-4 text-status-success" />
          </CardHeader>
          <CardContent>
            <div className="text-lg font-bold text-status-success">Hadir (06:45 WIB)</div>
            <p className="text-xs text-slate-500 mt-1">Gerbang Utama RFID Terbaca</p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
            <CardTitle className="text-xs font-semibold text-slate-500 uppercase">
              Jadwal Berikutnya
            </CardTitle>
            <Clock className="h-4 w-4 text-brand-blue" />
          </CardHeader>
          <CardContent>
            <div className="text-lg font-bold text-slate-900">Matematika</div>
            <p className="text-xs text-slate-500 mt-1">08:00 - 09:30 WIB (Ruang R-102)</p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
            <CardTitle className="text-xs font-semibold text-slate-500 uppercase">
              Tugas Aktif
            </CardTitle>
            <BookOpen className="h-4 w-4 text-brand-teal" />
          </CardHeader>
          <CardContent>
            <div className="text-lg font-bold text-slate-900">0 Tugas Menunggu</div>
            <p className="text-xs text-slate-500 mt-1">Semua tugas telah dikumpulkan</p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
            <CardTitle className="text-xs font-semibold text-slate-500 uppercase">
              Prestasi Kejuaraan
            </CardTitle>
            <Trophy className="h-4 w-4 text-amber-500" />
          </CardHeader>
          <CardContent>
            <div className="text-lg font-bold text-amber-600">{totalVerifiedAchievements} Trofi</div>
            <p className="text-xs text-slate-500 mt-1">
              {totalPendingAchievements > 0 ? `${totalPendingAchievements} menanti verifikasi` : 'Semua terverifikasi'}
            </p>
          </CardContent>
        </Card>
      </div>

      {/* Ruang CBT Komputer Lab */}
      <div className="p-6 rounded-xl border border-slate-200 bg-white shadow-sm flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div className="flex items-start gap-3.5">
          <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-blue-50 text-brand-blue shrink-0">
            <MonitorCheck className="h-6 w-6" />
          </div>
          <div>
            <h3 className="text-base font-bold text-slate-900">
              Ujian Berbasis Komputer (CBT Lab)
            </h3>
            <p className="text-xs text-slate-600 max-w-xl mt-0.5">
              Ruang pengerjaan ujian berbasis lab komputer dilengkapi autosave idempoten dan deteksi
              anti-curang. Siapkan token ruang yang diberikan oleh proctor saat jadwal ujian dimulai.
            </p>
          </div>
        </div>

        <div className="self-end md:self-auto shrink-0">
          <Link href="/portal/siswa/cbt">
            <Button size="sm" className="bg-brand-blue hover:bg-brand-blue/90 text-white text-xs gap-1.5 shadow-sm">
              Katalog Ujian CBT <ArrowRight className="h-3.5 w-3.5" />
            </Button>
          </Link>
        </div>
      </div>

      {/* Ruang Pengajuan Prestasi Mandiri Siswa (SIS-STD-01 / BE-STD-03) */}
      <div className="p-6 rounded-xl border border-amber-200 bg-gradient-to-r from-amber-50/70 via-orange-50/40 to-yellow-50/50 shadow-sm space-y-5">
        <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
          <div className="flex items-start gap-3.5">
            <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-amber-100 text-amber-700 shrink-0 shadow-sm">
              <Trophy className="h-6 w-6" />
            </div>
            <div>
              <div className="flex items-center gap-2">
                <h3 className="text-base font-bold text-slate-900">
                  Galeri Prestasi & Pengajuan Mandiri Siswa 🏆
                </h3>
                <Badge variant="outline" className="text-[10px] bg-amber-100 text-amber-800 border-amber-300">
                  Apresiasi Poin Karakter
                </Badge>
              </div>
              <p className="text-xs text-slate-600 max-w-2xl mt-0.5">
                Raih medali atau kejuaraan di bidang sains, olahraga, seni, maupun keagamaan?
                Ajukan berkas sertifikat Anda di sini untuk diverifikasi oleh staf kesiswaan dan mendapatkan poin apresiasi prestasi.
              </p>
            </div>
          </div>

          <div className="flex items-center gap-2.5 self-start md:self-auto shrink-0">
            <Button
              variant="outline"
              size="sm"
              onClick={fetchAchievements}
              disabled={isLoadingAchievements}
              className="bg-white/80 hover:bg-white text-slate-700 border-slate-300 text-xs gap-1.5"
            >
              <RefreshCw className={`h-3.5 w-3.5 ${isLoadingAchievements ? 'animate-spin' : ''}`} />
              Segarkan
            </Button>
            <Button
              size="sm"
              onClick={() => setIsAchievementModalOpen(true)}
              className="bg-amber-600 hover:bg-amber-700 text-white text-xs gap-1.5 shadow-sm"
            >
              <Plus className="h-3.5 w-3.5" />
              Ajukan Prestasi Mandiri
            </Button>
          </div>
        </div>

        {/* Filter Tab Prestasi Siswa */}
        <div className="flex items-center gap-2 pt-2 border-t border-amber-200/60">
          <button
            onClick={() => setAchievementFilter('all')}
            className={`px-3 py-1 rounded-full text-xs font-medium transition-colors ${
              achievementFilter === 'all'
                ? 'bg-amber-600 text-white'
                : 'bg-white/80 text-slate-600 hover:bg-white border border-slate-200'
            }`}
          >
            Semua ({achievements.length})
          </button>
          <button
            onClick={() => setAchievementFilter('verified')}
            className={`px-3 py-1 rounded-full text-xs font-medium transition-colors ${
              achievementFilter === 'verified'
                ? 'bg-emerald-600 text-white'
                : 'bg-white/80 text-slate-600 hover:bg-white border border-slate-200'
            }`}
          >
            Terverifikasi ({totalVerifiedAchievements})
          </button>
          <button
            onClick={() => setAchievementFilter('pending')}
            className={`px-3 py-1 rounded-full text-xs font-medium transition-colors ${
              achievementFilter === 'pending'
                ? 'bg-amber-500 text-white'
                : 'bg-white/80 text-slate-600 hover:bg-white border border-slate-200'
            }`}
          >
            Menunggu Verifikasi ({totalPendingAchievements})
          </button>
          <button
            onClick={() => setAchievementFilter('rejected')}
            className={`px-3 py-1 rounded-full text-xs font-medium transition-colors ${
              achievementFilter === 'rejected'
                ? 'bg-red-600 text-white'
                : 'bg-white/80 text-slate-600 hover:bg-white border border-slate-200'
            }`}
          >
            Ditolak ({safeAchievements.filter((a) => a.verification_status === 'rejected').length})
          </button>
        </div>

        {/* Daftar Kartu Prestasi Siswa */}
        {achievementError && (
          <div className="p-3 bg-red-50 border border-red-200 rounded-md text-red-700 text-xs">
            {achievementError}
          </div>
        )}

        {isLoadingAchievements ? (
          <div className="py-8 text-center text-xs text-slate-500">
            <RefreshCw className="h-5 w-5 animate-spin mx-auto mb-2 text-slate-400" />
            Memuat daftar prestasi...
          </div>
        ) : filteredAchievements.length === 0 ? (
          <div className="py-8 text-center bg-white/70 rounded-lg border border-dashed border-amber-200 text-xs text-slate-500">
            <Trophy className="h-8 w-8 mx-auto mb-2 text-amber-300" />
            {achievementFilter === 'all'
              ? 'Belum ada catatan atau pengajuan prestasi. Klik tombol "Ajukan Prestasi Mandiri" di atas.'
              : `Tidak ada data prestasi dengan status "${achievementFilter}".`}
          </div>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
            {filteredAchievements.map((ach) => {
              const isVerified = ach.verification_status === 'verified';
              const isPending = ach.verification_status === 'pending';
              const isRejected = ach.verification_status === 'rejected';

              return (
                <div
                  key={ach.id}
                  className="bg-white rounded-lg p-4 border border-slate-200 shadow-sm flex flex-col justify-between hover:border-amber-300 transition-all"
                >
                  <div className="space-y-2">
                    <div className="flex items-start justify-between gap-2">
                      <Badge
                        className={
                          isVerified
                            ? 'bg-emerald-100 text-emerald-800 border-emerald-200 text-[10px]'
                            : isPending
                            ? 'bg-amber-100 text-amber-800 border-amber-200 text-[10px]'
                            : 'bg-red-100 text-red-800 border-red-200 text-[10px]'
                        }
                      >
                        {isVerified ? (
                          <span className="flex items-center gap-1">
                            <CheckCircle2 className="h-3 w-3" /> Terverifikasi
                          </span>
                        ) : isPending ? (
                          <span className="flex items-center gap-1">
                            <Clock className="h-3 w-3" /> Menunggu Verifikasi
                          </span>
                        ) : (
                          <span className="flex items-center gap-1">
                            <AlertTriangle className="h-3 w-3" /> Ditolak
                          </span>
                        )}
                      </Badge>

                      {isVerified && ach.points_awarded > 0 && (
                        <span className="text-[11px] font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">
                          +{ach.points_awarded} Poin
                        </span>
                      )}
                    </div>

                    <h4 className="font-semibold text-slate-900 text-xs line-clamp-2">
                      {ach.title}
                    </h4>

                    <div className="text-[11px] text-slate-500 space-y-0.5">
                      <div className="font-medium text-amber-800">{ach.rank_level}</div>
                      <div>Tanggal: {ach.achieved_at}</div>
                      {ach.competition && (
                        <div className="text-slate-600 line-clamp-1">
                          Lomba: {ach.competition.name}
                        </div>
                      )}
                    </div>

                    {isRejected && ach.rejection_reason && (
                      <div className="p-2 bg-red-50 border border-red-200 rounded text-[11px] text-red-700">
                        <strong>Alasan:</strong> {ach.rejection_reason}
                      </div>
                    )}
                  </div>

                  <div className="pt-3 mt-3 border-t flex items-center justify-between text-xs">
                    <Button
                      variant="ghost"
                      size="sm"
                      onClick={() => {
                        setSelectedAchievement(ach);
                        setIsDetailModalOpen(true);
                      }}
                      className="text-slate-600 hover:text-slate-900 text-[11px] h-7 px-2 gap-1"
                    >
                      <Eye className="h-3 w-3" /> Detail
                    </Button>

                    {isPending && (
                      <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => handleDeletePendingAchievement(ach.id)}
                        className="text-red-600 hover:text-red-700 hover:bg-red-50 text-[11px] h-7 px-2 gap-1"
                      >
                        <Trash2 className="h-3 w-3" /> Batal
                      </Button>
                    )}
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </div>

      {/* Ruang Layanan Bimbingan & Konseling (BK) — SIS-BK-01 / BE-BK-03 */}
      <div className="p-6 rounded-xl border border-indigo-100 bg-gradient-to-r from-indigo-50/60 to-purple-50/40 shadow-sm flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div className="flex items-start gap-3.5">
          <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-indigo-100 text-indigo-700 shrink-0">
            <HeartHandshake className="h-6 w-6" />
          </div>
          <div>
            <div className="flex items-center gap-2">
              <h3 className="text-base font-bold text-slate-900">
                Layanan Bimbingan Konseling (BK) Siswa
              </h3>
              <Badge variant="outline" className="text-[10px] bg-indigo-100/60 text-indigo-800 border-indigo-300">
                Privasi Terjaga
              </Badge>
            </div>
            <p className="text-xs text-slate-600 max-w-xl mt-0.5">
              Butuh konsultasi pribadi, kendala belajar, perencanaan karir masa depan, atau pendampingan masalah sosial?
              Anda dapat mengajukan jadwal konseling langsung dengan Guru BK secara rahasia dan aman.
            </p>
          </div>
        </div>

        <div className="self-end md:self-auto shrink-0">
          <Button
            size="sm"
            onClick={() => setIsCounselingModalOpen(true)}
            className="bg-indigo-600 hover:bg-indigo-700 text-white text-xs gap-1.5 shadow-sm"
          >
            <HeartHandshake className="h-3.5 w-3.5" />
            Ajukan Konseling BK
          </Button>
        </div>
      </div>

      {/* Modal Ajukan Prestasi Mandiri Siswa */}
      {isAchievementModalOpen && (
        <div className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
          <div className="bg-white rounded-lg shadow-xl w-full max-w-lg border overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            <div className="px-5 py-4 border-b flex justify-between items-center bg-slate-50">
              <div className="flex items-center gap-2">
                <Trophy className="h-5 w-5 text-amber-600" />
                <div>
                  <h3 className="font-semibold text-slate-900 text-sm">
                    Pengajuan Prestasi & Trofi Mandiri
                  </h3>
                  <p className="text-[11px] text-slate-500">Kesiswaan Labschool UM (BE-STD-03)</p>
                </div>
              </div>
              <button
                onClick={() => {
                  setIsAchievementModalOpen(false);
                  setAchSubmitError(null);
                  setAchSubmitSuccess(null);
                }}
                className="text-slate-400 hover:text-slate-600 rounded p-1"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            <form onSubmit={handleCreateAchievement} className="p-5 space-y-3.5 text-xs">
              <div className="p-3 bg-amber-50 border border-amber-200 rounded-md text-amber-900 text-[11px] flex items-start gap-2">
                <Info className="h-4 w-4 text-amber-700 shrink-0 mt-0.5" />
                <div>
                  <strong>Prosedur Verifikasi:</strong> Pengajuan ini akan ditinjau keabsahan berkasnya oleh tim Kesiswaan sebelum poin apresiasi prestasi diterbitkan secara resmi.
                </div>
              </div>

              {achSubmitError && (
                <div className="p-2.5 bg-red-50 border border-red-200 rounded text-red-700 text-xs flex items-center gap-2">
                  <AlertTriangle className="h-4 w-4 shrink-0" />
                  <span>{achSubmitError}</span>
                </div>
              )}

              {achSubmitSuccess && (
                <div className="p-2.5 bg-emerald-50 border border-emerald-200 rounded text-emerald-800 text-xs flex items-center gap-2">
                  <CheckCircle2 className="h-4 w-4 shrink-0" />
                  <span>{achSubmitSuccess}</span>
                </div>
              )}

              <div>
                <label className="block font-medium text-slate-700 mb-1">Nama Kejuaraan / Prestasi *</label>
                <Input
                  required
                  placeholder="Misal: Juara 1 Olimpiade Matematika Sains Nasional 2026"
                  value={achTitle}
                  onChange={(e) => setAchTitle(e.target.value)}
                  className="text-xs h-9"
                />
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <label className="block font-medium text-slate-700 mb-1">Peringkat / Penghargaan *</label>
                  <select
                    value={achRankLevel}
                    onChange={(e) => setAchRankLevel(e.target.value)}
                    className="w-full text-xs h-9 px-3 rounded-md border border-slate-200 bg-white"
                  >
                    <option value="Juara 1">Juara 1 / Medali Emas</option>
                    <option value="Juara 2">Juara 2 / Medali Perak</option>
                    <option value="Juara 3">Juara 3 / Medali Perunggu</option>
                    <option value="Harapan 1">Harapan 1</option>
                    <option value="Harapan 2">Harapan 2</option>
                    <option value="Finalis">Finalis</option>
                    <option value="Best Speaker">Best Speaker / Peserta Terbaik</option>
                    <option value="Apresiasi Khusus">Apresiasi Khusus</option>
                  </select>
                </div>

                <div>
                  <label className="block font-medium text-slate-700 mb-1">Tingkatan Kejuaraan *</label>
                  <select
                    value={achLevel}
                    onChange={(e) => setAchLevel(e.target.value)}
                    className="w-full text-xs h-9 px-3 rounded-md border border-slate-200 bg-white"
                  >
                    <option value="sekolah">Antar Kelas / Internal Sekolah</option>
                    <option value="kecamatan">Kecamatan</option>
                    <option value="kabupaten">Kabupaten / Kota</option>
                    <option value="provinsi">Provinsi</option>
                    <option value="nasional">Nasional</option>
                    <option value="internasional">Internasional</option>
                  </select>
                </div>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <label className="block font-medium text-slate-700 mb-1">Tanggal Kejuaraan *</label>
                  <Input
                    required
                    type="date"
                    value={achDate}
                    onChange={(e) => setAchDate(e.target.value)}
                    className="text-xs h-9"
                  />
                </div>

                <div>
                  <label className="block font-medium text-slate-700 mb-1">Kompetisi Terkait (Opsional)</label>
                  <select
                    value={achCompetitionId}
                    onChange={(e) => setAchCompetitionId(e.target.value)}
                    className="w-full text-xs h-9 px-3 rounded-md border border-slate-200 bg-white truncate"
                  >
                    <option value="">-- Lomba Mandiri / Non-Katalog --</option>
                    {competitions.map((comp) => (
                      <option key={comp.id} value={comp.id}>
                        {comp.name} ({comp.level})
                      </option>
                    ))}
                  </select>
                </div>
              </div>

              <div>
                <label className="block font-medium text-slate-700 mb-1">
                  Catatan Tambahan / Penyelenggara Lomba
                </label>
                <Textarea
                  placeholder="Nama lembaga penyelenggara lomba, nomor sertifikat, atau ringkasan perolehan..."
                  value={achNotes}
                  onChange={(e) => setAchNotes(e.target.value)}
                  className="text-xs h-20 resize-none"
                />
              </div>

              <div className="flex justify-end gap-2 pt-3 border-t">
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={() => setIsAchievementModalOpen(false)}
                  className="text-xs"
                >
                  Batal
                </Button>
                <Button
                  type="submit"
                  size="sm"
                  disabled={isSubmittingAch}
                  className="bg-amber-600 hover:bg-amber-700 text-white text-xs gap-1.5"
                >
                  {isSubmittingAch && <RefreshCw className="h-3.5 w-3.5 animate-spin" />}
                  Kirim Pengajuan Prestasi
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Modal Detail Prestasi & Alasan Penolakan */}
      {isDetailModalOpen && selectedAchievement && (
        <div className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
          <div className="bg-white rounded-lg shadow-xl w-full max-w-md border overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            <div className="px-5 py-4 border-b flex justify-between items-center bg-slate-50">
              <div className="flex items-center gap-2">
                <Trophy className="h-5 w-5 text-amber-600" />
                <h3 className="font-semibold text-slate-900 text-sm">Rincian Prestasi Siswa</h3>
              </div>
              <button
                onClick={() => setIsDetailModalOpen(false)}
                className="text-slate-400 hover:text-slate-600 rounded p-1"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            <div className="p-5 space-y-3.5 text-xs">
              <div>
                <div className="text-[11px] text-slate-500">Nama Prestasi</div>
                <div className="font-semibold text-slate-900 text-sm">{selectedAchievement.title}</div>
              </div>

              <div className="grid grid-cols-2 gap-2 text-[11px]">
                <div>
                  <div className="text-slate-500">Peringkat</div>
                  <div className="font-medium text-amber-800">{selectedAchievement.rank_level}</div>
                </div>
                <div>
                  <div className="text-slate-500">Tanggal Raihan</div>
                  <div className="font-medium text-slate-800">{selectedAchievement.achieved_at}</div>
                </div>
              </div>

              <div>
                <div className="text-[11px] text-slate-500">Status Verifikasi</div>
                <Badge
                  className={
                    selectedAchievement.verification_status === 'verified'
                      ? 'bg-emerald-100 text-emerald-800 border-emerald-200 mt-1'
                      : selectedAchievement.verification_status === 'pending'
                      ? 'bg-amber-100 text-amber-800 border-amber-200 mt-1'
                      : 'bg-red-100 text-red-800 border-red-200 mt-1'
                  }
                >
                  {selectedAchievement.verification_status === 'verified'
                    ? 'Terverifikasi'
                    : selectedAchievement.verification_status === 'pending'
                    ? 'Menunggu Verifikasi Kesiswaan'
                    : 'Ditolak'}
                </Badge>
              </div>

              {selectedAchievement.verification_status === 'rejected' && selectedAchievement.rejection_reason && (
                <div className="p-3 bg-red-50 border border-red-200 rounded-md text-red-800">
                  <div className="font-semibold mb-1 flex items-center gap-1.5 text-xs">
                    <AlertTriangle className="h-4 w-4 text-red-600" /> Catatan Penolakan Verifikator:
                  </div>
                  <p className="text-[11px]">{selectedAchievement.rejection_reason}</p>
                </div>
              )}

              {selectedAchievement.notes && (
                <div>
                  <div className="text-[11px] text-slate-500">Catatan / Keterangan</div>
                  <p className="text-slate-700 mt-0.5">{selectedAchievement.notes}</p>
                </div>
              )}

              <div className="flex justify-end pt-3 border-t">
                <Button
                  size="sm"
                  onClick={() => setIsDetailModalOpen(false)}
                  className="bg-slate-900 text-white text-xs"
                >
                  Tutup
                </Button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Modal Ajukan Konseling Siswa */}
      {isCounselingModalOpen && (
        <div className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
          <div className="bg-white rounded-lg shadow-xl w-full max-w-lg border overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            <div className="px-5 py-4 border-b flex justify-between items-center bg-slate-50">
              <div className="flex items-center gap-2">
                <HeartHandshake className="h-5 w-5 text-indigo-600" />
                <div>
                  <h3 className="font-semibold text-slate-900 text-sm">
                    Pengajuan Bimbingan Konseling (BK)
                  </h3>
                  <p className="text-[11px] text-slate-500">Layanan Pendampingan Siswa Labschool UM</p>
                </div>
              </div>
              <button
                onClick={() => {
                  setIsCounselingModalOpen(false);
                  setSubmitError(null);
                  setSubmitSuccess(null);
                }}
                className="text-slate-400 hover:text-slate-600 rounded p-1"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            <form onSubmit={handleSubmitCounseling} className="p-5 space-y-3.5 text-xs">
              <div className="p-3 bg-amber-50 border border-amber-200 rounded-md text-amber-900 text-[11px] flex items-start gap-2">
                <Lock className="h-4 w-4 text-amber-700 shrink-0 mt-0.5" />
                <div>
                  <strong>Jaminan Kerahasiaan (AES-256):</strong> Seluruh pengajuan bimbingan ini bersifat rahasia dan hanya dapat diakses oleh Guru BK yang bertugas.
                </div>
              </div>

              {submitError && (
                <div className="p-2.5 bg-red-50 border border-red-200 rounded text-red-700 text-xs flex items-center gap-2">
                  <AlertTriangle className="h-4 w-4 shrink-0" />
                  <span>{submitError}</span>
                </div>
              )}

              {submitSuccess && (
                <div className="p-2.5 bg-emerald-50 border border-emerald-200 rounded text-emerald-800 text-xs flex items-center gap-2">
                  <CheckCircle2 className="h-4 w-4 shrink-0" />
                  <span>{submitSuccess}</span>
                </div>
              )}

              <div>
                <label className="block font-medium text-slate-700 mb-1">Topik / Kategori Konseling *</label>
                <select
                  value={category}
                  onChange={(e) => setCategory(e.target.value as CounselingCategory)}
                  className="w-full text-xs h-9 px-3 rounded-md border border-slate-200 bg-white"
                >
                  <option value="Pribadi">Pribadi (Kesejahteraan Emosional / Percaya Diri)</option>
                  <option value="Belajar">Belajar (Motivasi, Kesulitan Belajar, Tugas)</option>
                  <option value="Sosial">Sosial (Pertemanan, Adaptasi Lingkungan)</option>
                  <option value="Karir">Karir (Peminatan Jurusan, Studi Lanjut Kampus)</option>
                  <option value="Lainnya">Lainnya</option>
                </select>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <label className="block font-medium text-slate-700 mb-1">Pilihan Hari / Tanggal</label>
                  <Input
                    type="date"
                    value={preferredDate}
                    onChange={(e) => setPreferredDate(e.target.value)}
                    className="text-xs h-9"
                  />
                </div>
                <div>
                  <label className="block font-medium text-slate-700 mb-1">Pilihan Waktu / Jam Istirahat</label>
                  <Input
                    placeholder="Misal: Istirahat Pertama / 10:00 WIB"
                    value={preferredTimeSlot}
                    onChange={(e) => setPreferredTimeSlot(e.target.value)}
                    className="text-xs h-9"
                  />
                </div>
              </div>

              <div>
                <label className="block font-medium text-slate-700 mb-1">
                  Keperluan / Cerita Singkat Hal yang Ingin Didiskusikan *
                </label>
                <Textarea
                  required
                  placeholder="Tuliskan secara singkat hal yang ingin Anda konsultasikan dengan Guru BK..."
                  value={reason}
                  onChange={(e) => setReason(e.target.value)}
                  className="text-xs h-24 resize-none"
                />
              </div>

              <div className="flex justify-end gap-2 pt-3 border-t">
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={() => setIsCounselingModalOpen(false)}
                  className="text-xs"
                >
                  Batal
                </Button>
                <Button
                  type="submit"
                  size="sm"
                  disabled={isSubmitting}
                  className="bg-indigo-600 hover:bg-indigo-700 text-white text-xs gap-1.5"
                >
                  {isSubmitting && <RefreshCw className="h-3.5 w-3.5 animate-spin" />}
                  Kirim Pengajuan
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Modal Pengaturan & Pratinjau Foto Profil Siswa */}
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
                  <h3 className="text-base font-bold text-slate-900">Foto Profil Resmi Siswa</h3>
                  <p className="text-xs text-slate-500">Standar Identitas & Dokumen Akademik</p>
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
                  name={studentName}
                  size="2xl"
                  shape="rounded"
                  alt={`Pasfoto ${studentName}`}
                  className="border-2 border-slate-200 shadow-md"
                />
              </div>

              <div>
                <h4 className="font-bold text-slate-900 text-sm">{studentName}</h4>
                <p className="text-xs text-slate-500 font-mono">{nisLabel} • {classLabel}</p>
              </div>

              {/* Status Informasi Kebijakan Foto Profil */}
              <div className="p-3.5 rounded-xl bg-blue-50/80 border border-blue-200/60 text-left text-xs text-blue-900 space-y-2">
                <div className="flex items-center gap-2 font-semibold text-brand-blue">
                  <ShieldCheck className="h-4 w-4 shrink-0" />
                  <span>Kebijakan Foto Resmi Sekolah</span>
                </div>
                <p className="text-[11px] leading-relaxed text-blue-800">
                  Foto profil resmi siswa diatur secara terpusat oleh Bagian Tata Usaha untuk menjamin validitas berkas buku induk, rapor, dan kartu ujian.
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
