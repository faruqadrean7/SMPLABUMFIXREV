'use client';

import React from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { 
  Users, 
  BookOpen, 
  CheckSquare, 
  Clock, 
  Calendar, 
  ArrowRight, 
  CalendarCheck,
  FileSpreadsheet,
  MonitorPlay,
  UserCheck,
  Building2,
  CheckCircle2,
  AlertCircle,
  FileText,
  School,
  ChevronRight,
  ShieldCheck,
  Award,
  BellRing,
  MapPin,
  ExternalLink,
  HeartHandshake,
  MessageCircle
} from 'lucide-react';
import { useAuth } from '@/lib/auth-context';
import { useStudentDashboard, useStudentTasks, useSchoolAnnouncements } from '@/lib/use-student-data';
import { 
  DashboardHeroSkeleton, 
  MetricCardsSkeleton, 
  ScheduleListSkeleton, 
  TaskListSkeleton 
} from '@/components/ui/student-skeletons';
import { EmptyState } from '@/components/ui/empty-state';
import { ErrorState } from '@/components/ui/error-state';

export default function StudentDashboardPage() {
  const router = useRouter();
  const { user } = useAuth();
  const { data: dashboardData, isLoading: isDashboardLoading, isError, refetch } = useStudentDashboard();
  const { data: tasksData } = useStudentTasks();
  const { data: announcementsData } = useSchoolAnnouncements();

  if (isDashboardLoading) {
    return (
      <div className="flex flex-col gap-3.5 max-w-full text-slate-900 font-sans">
        <DashboardHeroSkeleton />
        <MetricCardsSkeleton />
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-3.5">
          <div className="lg:col-span-7 flex flex-col gap-3">
            <div className="bg-white border border-slate-200/90 rounded-xl p-3.5 shadow-xs">
              <div className="flex items-center justify-between pb-2.5 border-b border-slate-100 mb-2">
                <div className="h-4 w-36 bg-slate-200 rounded animate-pulse" />
                <div className="h-4 w-20 bg-slate-200 rounded animate-pulse" />
              </div>
              <ScheduleListSkeleton />
            </div>
            <div className="bg-white border border-slate-200/90 rounded-xl p-3.5 shadow-xs">
              <div className="flex items-center justify-between pb-2.5 border-b border-slate-100 mb-2">
                <div className="h-4 w-40 bg-slate-200 rounded animate-pulse" />
                <div className="h-4 w-16 bg-slate-200 rounded animate-pulse" />
              </div>
              <TaskListSkeleton />
            </div>
          </div>
          <div className="lg:col-span-5 flex flex-col gap-3">
            <div className="bg-white border border-slate-200/90 rounded-xl p-4 shadow-xs h-36 animate-pulse bg-slate-50" />
            <div className="bg-white border border-slate-200/90 rounded-xl p-4 shadow-xs h-44 animate-pulse bg-slate-50" />
            <div className="bg-white border border-slate-200/90 rounded-xl p-4 shadow-xs h-40 animate-pulse bg-slate-50" />
          </div>
        </div>
      </div>
    );
  }

  if (isError) {
    return (
      <div className="p-4 sm:p-6">
        <ErrorState
          title="Gagal Memuat Beranda Siswa"
          message="Tidak dapat mengambil ringkasan akademik siswa dari server. Silakan periksa koneksi Anda dan coba kembali."
          onRetry={() => refetch()}
        />
      </div>
    );
  }

  const studentName = dashboardData?.name || user?.profile?.name || user?.username || 'Siswa';
  const studentNis = dashboardData?.nis || user?.profile?.nis || '-';
  const studentClass = dashboardData?.className || user?.profile?.className || 'Kelas 7-A';
  const studentAvatar =
    dashboardData?.photoUrl ||
    (user?.profile?.photoUrl as string) ||
    null;

  const studentInitials = studentName
    ? studentName
        .split(' ')
        .slice(0, 2)
        .map((w) => w[0])
        .join('')
        .toUpperCase()
    : 'SW';

  // Attendance rate
  const attendanceRate = dashboardData?.attendanceRate ?? 100.0;

  // Schedules
  const schedulesToRender =
    dashboardData?.todaySchedule && dashboardData.todaySchedule.length > 0
      ? dashboardData.todaySchedule.map((s) => ({
          id: s.id,
          startTime: s.startTime,
          endTime: s.endTime,
          subjectName: s.subjectName,
          teacherName: s.teacherName,
          roomName: s.roomName,
          dayName: s.dayName,
        }))
      : [];

  // Active / Upcoming exam
  const examFromApi = dashboardData?.upcomingExams && dashboardData.upcomingExams.length > 0 ? dashboardData.upcomingExams[0] : null;
  const activeExam = examFromApi
    ? {
        id: examFromApi.id,
        title: examFromApi.title,
        subjectName: examFromApi.subjectName,
        roomName: 'Laboratorium Komputer',
        durationMinutes: examFromApi.durationMinutes,
        totalQuestions: examFromApi.totalQuestions,
        token: 'INF-LAB7',
        kktp: 75,
      }
    : null;

  // Tasks
  const tasksToRender = tasksData && tasksData.length > 0 ? tasksData : [];


  const pendingTasksCount = tasksToRender.filter((t) => t.status === 'aktif').length;
  const totalAchievements = dashboardData?.totalAchievements !== undefined ? dashboardData.totalAchievements : 1;
  const pointBalance = totalAchievements > 0 ? `+${totalAchievements * 30}` : '+30';

  return (
    <div className="flex flex-col gap-3.5 max-w-full xl:h-[calc(100vh-112px)] xl:overflow-hidden text-slate-900 font-sans">
      {/* ─── 1. INTEGRATED STUDENT PROFILE & REALTIME STATUS HERO BANNER ─── */}
      <div className="bg-white border border-slate-200/90 rounded-xl p-3 px-4 shadow-xs shrink-0">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          {/* Left: Identity & Badges */}
          <div className="flex items-center gap-3.5 min-w-0">
            {studentAvatar ? (
              <img
                src={studentAvatar}
                alt={studentName}
                className="w-12 h-12 rounded-xl object-cover border-2 border-slate-200 shadow-xs shrink-0"
              />
            ) : (
              <div className="w-12 h-12 rounded-xl bg-blue-900 text-white font-extrabold flex items-center justify-center text-base shadow-xs shrink-0">
                {studentInitials}
              </div>
            )}
            <div className="min-w-0">
              <div className="flex flex-wrap items-center gap-2">
                <h1 className="text-lg font-extrabold text-slate-900 tracking-tight truncate">
                  {studentName}
                </h1>
                <span className="px-2 py-0.5 rounded-lg text-xs font-bold bg-blue-900 text-white shadow-2xs font-mono">
                  {studentClass.replace('Kelas ', '')}
                </span>
                <span className="px-2 py-0.5 rounded-lg text-xs font-semibold bg-slate-100 border border-slate-300 text-slate-700 font-mono">
                  NIS: {studentNis}
                </span>
                <span className="px-2.5 py-0.5 rounded-lg text-xs font-bold shadow-2xs bg-emerald-800 text-white">
                  Presensi Tepat Waktu
                </span>
              </div>

              {/* Status Presensi & Info Walas */}
              <div className="mt-1 flex flex-wrap items-center gap-x-3 text-xs text-slate-600">
                <div className="inline-flex items-center gap-1 font-semibold text-emerald-800">
                  <CheckCircle2 className="w-3.5 h-3.5 shrink-0" />
                  <span>
                    Presensi KBM: {dashboardData?.attendanceRate ? `${dashboardData.attendanceRate}% Hadir` : 'Hadir Aktif'}
                  </span>
                </div>
                <span className="text-slate-300 hidden sm:inline">•</span>
                <div>
                  <span>Wali Kelas: </span>
                  <strong className="text-slate-900 font-semibold">Budi Santoso, S.Kom., M.T.</strong>
                </div>
                <span className="text-slate-300 hidden sm:inline">•</span>
                <div>
                  <span>Fase: </span>
                  <strong className="text-slate-900 font-semibold">Fase D (Kurikulum Merdeka)</strong>
                </div>
              </div>
            </div>
          </div>

          {/* Right: Quick Action Buttons */}
          <div className="flex flex-wrap items-center gap-2 shrink-0">
            {activeExam && (
              <Link
                href="/siswa/cbt"
                className="px-3.5 py-2 rounded-xl bg-rose-700 hover:bg-rose-800 text-white text-xs font-bold transition-all shadow-xs flex items-center gap-1.5"
              >
                <MonitorPlay className="w-4 h-4 text-white" />
                <span>Ujian CBT Aktif</span>
              </Link>
            )}
            <Link
              href="/siswa/tugas"
              className="px-3.5 py-2 rounded-xl bg-blue-900 hover:bg-blue-950 text-white text-xs font-bold transition-all shadow-xs flex items-center gap-1.5"
            >
              <CheckSquare className="w-4 h-4 text-white" />
              <span>Tugas Mandiri ({pendingTasksCount})</span>
            </Link>
            <Link
              href="/siswa/jadwal"
              className="px-3.5 py-2 rounded-xl bg-white border border-slate-300 hover:bg-slate-100 text-slate-800 text-xs font-bold transition-colors shadow-xs flex items-center gap-1.5"
            >
              <Calendar className="w-4 h-4 text-slate-700" />
              <span>Jadwal Pelajaran</span>
            </Link>
            <Link
              href="/siswa/nilai"
              className="px-3.5 py-2 rounded-xl bg-white border border-slate-300 hover:bg-slate-100 text-slate-800 text-xs font-bold transition-colors shadow-xs flex items-center gap-1.5"
            >
              <FileSpreadsheet className="w-4 h-4 text-slate-700" />
              <span>Capaian Nilai</span>
            </Link>
          </div>
        </div>
      </div>

      {/* ─── 2. REIMAGINED VISUAL METRICS (4 EQUAL SAAS CARDS STRIP) ─── */}
      <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3 shrink-0">
        {/* Metric 1: Kehadiran Siswa */}
        <div className="bg-white border border-slate-200/90 rounded-xl p-3.5 shadow-xs flex flex-col justify-between hover:border-slate-300 transition-all">
          <div className="flex items-center justify-between pb-1.5 border-b border-slate-100">
            <span className="text-xs font-bold text-slate-700 uppercase tracking-wider truncate">Presensi Semester</span>
            <div className="w-7 h-7 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200 flex items-center justify-center shrink-0">
              <UserCheck className="w-3.5 h-3.5" />
            </div>
          </div>
          <div className="my-1.5">
            <div className="flex items-baseline justify-between">
              <span className="text-3xl font-extrabold text-slate-900 tracking-tight">
                {attendanceRate}%
              </span>
              <span className="text-xs font-bold px-2 py-0.5 rounded-md bg-emerald-800 text-white shadow-2xs">
                Target 95%
              </span>
            </div>
            <p className="text-xs text-slate-500 font-medium mt-0.5 truncate">
              {attendanceRate >= 80 ? 'Kehadiran KBM Sangat Baik' : 'Perlu Peningkatan Kehadiran'}
            </p>
          </div>
          <div className="pt-2 border-t border-slate-100">
            <Link
              href="/siswa/jadwal"
              className="text-xs font-bold text-slate-700 hover:text-emerald-800 flex items-center justify-between group transition-colors"
            >
              <span>Riwayat Jam Masuk Gerbang</span>
              <ArrowRight className="w-3.5 h-3.5 text-slate-400 group-hover:text-emerald-800 group-hover:translate-x-0.5 transition-all" />
            </Link>
          </div>
        </div>

        {/* Metric 2: Saldo Poin Karakter */}
        <div className="bg-white border border-slate-200/90 rounded-xl p-3.5 shadow-xs flex flex-col justify-between hover:border-slate-300 transition-all">
          <div className="flex items-center justify-between pb-1.5 border-b border-slate-100">
            <span className="text-xs font-bold text-slate-700 uppercase tracking-wider truncate">Saldo Poin Pembiasaan</span>
            <div className="w-7 h-7 rounded-lg bg-blue-50 text-blue-900 border border-blue-200 flex items-center justify-center shrink-0">
              <Award className="w-3.5 h-3.5" />
            </div>
          </div>
          <div className="my-1.5">
            <div className="flex items-baseline justify-between">
              <div className="flex items-baseline gap-1">
                <span className="text-3xl font-extrabold text-slate-900 tracking-tight">
                  {pointBalance}
                </span>
                <span className="text-sm font-bold text-slate-500">Poin</span>
              </div>
              <span className="text-xs font-bold px-2 py-0.5 rounded-md bg-blue-900 text-white shadow-2xs">
                SAB Aman
              </span>
            </div>
            <p className="text-xs text-slate-500 font-medium mt-0.5 truncate">
              {totalAchievements} Sertifikat Prestasi Tercatat
            </p>
          </div>
          <div className="pt-2 border-t border-slate-100">
            <Link
              href="/siswa/poin"
              className="text-xs font-bold text-slate-700 hover:text-blue-900 flex items-center justify-between group transition-colors"
            >
              <span>Lihat Buku Poin & Ekskul</span>
              <ArrowRight className="w-3.5 h-3.5 text-slate-400 group-hover:text-blue-900 group-hover:translate-x-0.5 transition-all" />
            </Link>
          </div>
        </div>

        {/* Metric 3: Tugas LMS Berjalan */}
        <div className="bg-white border border-slate-200/90 rounded-xl p-3.5 shadow-xs flex flex-col justify-between hover:border-slate-300 transition-all">
          <div className="flex items-center justify-between pb-1.5 border-b border-slate-100">
            <span className="text-xs font-bold text-slate-700 uppercase tracking-wider truncate">Tugas Perlu Dikerjakan</span>
            <div className="w-7 h-7 rounded-lg bg-amber-50 text-amber-800 border border-amber-200 flex items-center justify-center shrink-0">
              <CheckSquare className="w-3.5 h-3.5" />
            </div>
          </div>
          <div className="my-1.5">
            <div className="flex items-baseline justify-between">
              <div className="flex items-baseline gap-1">
                <span className="text-3xl font-extrabold text-slate-900 tracking-tight">
                  {pendingTasksCount}
                </span>
                <span className="text-sm font-bold text-slate-500">Tugas</span>
              </div>
              <span className="text-xs font-bold px-2 py-0.5 rounded-md bg-amber-600 text-white shadow-2xs">
                {pendingTasksCount > 0 ? 'Tenggat Dekat' : 'Selesai Semua'}
              </span>
            </div>
            <p className="text-xs text-slate-500 font-medium mt-0.5 truncate">
              Matematika & Proyek Merdeka
            </p>
          </div>
          <div className="pt-2 border-t border-slate-100">
            <Link
              href="/siswa/tugas"
              className="text-xs font-bold text-slate-700 hover:text-amber-800 flex items-center justify-between group transition-colors"
            >
              <span>Buka Modul & LMS Tugas</span>
              <ArrowRight className="w-3.5 h-3.5 text-slate-400 group-hover:text-amber-800 group-hover:translate-x-0.5 transition-all" />
            </Link>
          </div>
        </div>

        {/* Metric 4: Capaian KKTP Kurikulum */}
        <div className="bg-white border border-slate-200/90 rounded-xl p-3.5 shadow-xs flex flex-col justify-between hover:border-slate-300 transition-all">
          <div className="flex items-center justify-between pb-1.5 border-b border-slate-100">
            <span className="text-xs font-bold text-slate-700 uppercase tracking-wider truncate">Rerata Capaian Nilai</span>
            <div className="w-7 h-7 rounded-lg bg-blue-50 text-blue-800 border border-blue-200 flex items-center justify-center shrink-0">
              <FileSpreadsheet className="w-3.5 h-3.5" />
            </div>
          </div>
          <div className="my-1.5">
            <div className="flex items-baseline justify-between">
              <div className="flex items-baseline gap-1">
                <span className="text-3xl font-extrabold text-slate-900 tracking-tight">
                  86.5
                </span>
                <span className="text-xs font-semibold text-slate-500 font-mono">/ 100</span>
              </div>
              <span className="text-xs font-bold px-2 py-0.5 rounded-md bg-blue-800 text-white shadow-2xs">
                Semua Tuntas
              </span>
            </div>
            <p className="text-xs text-slate-500 font-medium mt-0.5 truncate">
              Batas KKTP 75.0 • Capaian Memuaskan
            </p>
          </div>
          <div className="pt-2 border-t border-slate-100">
            <Link
              href="/siswa/nilai"
              className="text-xs font-bold text-slate-700 hover:text-blue-800 flex items-center justify-between group transition-colors"
            >
              <span>Rincian Nilai & E-Rapor</span>
              <ArrowRight className="w-3.5 h-3.5 text-slate-400 group-hover:text-blue-800 group-hover:translate-x-0.5 transition-all" />
            </Link>
          </div>
        </div>
      </div>

      {/* ─── 3. DUA KOLOM UTAMA: ALUR BELAJAR vs KOMUNIKASI & AGENDA ─── */}
      <div className="xl:flex-1 xl:min-h-0 grid grid-cols-1 lg:grid-cols-12 gap-3.5">
        {/* KOLOM KIRI (7 Kolom): Alur KBM Hari Ini & Tugas Berjalan */}
        <div className="lg:col-span-7 flex flex-col gap-3 h-full overflow-hidden">
          {/* Card 1: Alur Pembelajaran Hari Ini */}
          <div className="bg-white border border-slate-200/90 rounded-xl p-3.5 shadow-xs flex flex-col overflow-hidden shrink-0">
            <div className="shrink-0 flex items-center justify-between pb-2.5 border-b border-slate-100">
              <div className="flex items-center gap-2.5">
                <div className="w-8 h-8 rounded-lg bg-slate-100 text-slate-800 flex items-center justify-center border border-slate-200 shrink-0">
                  <Clock className="w-4 h-4 text-slate-800" />
                </div>
                <div>
                  <h2 className="text-sm font-bold text-slate-900 tracking-tight">
                    Alur Pembelajaran Hari Ini
                  </h2>
                  <p className="text-xs text-slate-500 font-normal">
                    {studentClass} • Lab Komputer & Kelas
                  </p>
                </div>
              </div>
              <Link
                href="/siswa/jadwal"
                className="text-xs font-bold text-blue-900 hover:text-blue-950 flex items-center gap-1"
              >
                <span>Jadwal Lengkap</span>
                <ArrowRight className="w-3.5 h-3.5" />
              </Link>
            </div>

            <div className="space-y-2 mt-2.5">
              {schedulesToRender.map((session, index) => {
                const isCurrent = index === 0;
                return (
                  <div
                    key={session.id || index}
                    className={`p-3 rounded-xl border transition-all ${
                      isCurrent
                        ? 'border-blue-300 bg-blue-50/40 border-l-4 border-l-blue-900 shadow-xs'
                        : 'border-slate-200/80 bg-slate-50/50 hover:bg-slate-50'
                    }`}
                  >
                    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-1.5">
                      <div className="flex items-center gap-2">
                        <span className="font-mono text-xs font-bold text-slate-900">
                          {session.startTime} - {session.endTime} WIB
                        </span>
                        <span
                          className={`inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-bold ${
                            isCurrent
                              ? 'bg-blue-900 text-white shadow-2xs'
                              : 'bg-slate-200 text-slate-700'
                          }`}
                        >
                          {isCurrent ? 'Sesi Terdekat' : 'Sesi KBM'}
                        </span>
                      </div>
                      <span className="text-xs text-slate-600 font-semibold bg-slate-100 px-2 py-0.5 rounded-md border border-slate-200 w-fit">
                        {session.roomName}
                      </span>
                    </div>

                    <div className="mt-2">
                      <h4 className="text-sm font-bold text-slate-900">
                        {session.subjectName}
                      </h4>
                      <p className="text-xs text-slate-500 font-normal mt-0.5">
                        Pengampu: <span className="font-medium text-slate-700">{session.teacherName}</span>
                      </p>
                    </div>
                  </div>
                );
              })}

              {schedulesToRender.length === 0 && (
                <EmptyState
                  icon={Calendar}
                  title="Tidak Ada Jadwal KBM Hari Ini"
                  description="Hari ini tidak terdapat jadwal sesi tatap muka aktif."
                  actionText="Lihat Kalender Akademik"
                  onAction={() => router.push('/siswa/jadwal')}
                />
              )}
            </div>
          </div>

          {/* Card 2: Tugas LMS & Lembar Kerja */}
          <div className="flex-1 min-h-0 bg-white border border-slate-200/90 rounded-xl p-3.5 shadow-xs flex flex-col overflow-hidden">
            <div className="flex items-center justify-between pb-2.5 border-b border-slate-100 shrink-0">
              <div className="flex items-center gap-2">
                <div className="w-8 h-8 rounded-lg bg-blue-50 text-blue-900 border border-blue-200 flex items-center justify-center shrink-0">
                  <CheckSquare className="w-4 h-4" />
                </div>
                <div>
                  <h2 className="text-sm font-bold text-slate-900 tracking-tight">
                    Tugas & Lembar Kerja Siswa
                  </h2>
                  <p className="text-xs text-slate-500 font-normal">
                    Memerlukan pengunggahan atau ulasan nilai guru
                  </p>
                </div>
              </div>
              <Link
                href="/siswa/tugas"
                className="text-xs font-bold text-blue-900 hover:text-blue-950 flex items-center gap-1"
              >
                <span>Buka LMS</span>
                <ChevronRight className="w-3.5 h-3.5" />
              </Link>
            </div>

            <div className="flex-1 min-h-0 overflow-y-auto space-y-2 pr-1 mt-2.5">
              {tasksToRender.map((task) => {
                const isGraded = task.status === 'dinilai';
                const isSubmitted = task.status === 'dikumpulkan';

                return (
                  <div
                    key={task.id}
                    className="p-3 rounded-xl border border-slate-200/90 bg-white hover:bg-slate-50 transition-colors flex flex-col sm:flex-row sm:items-center justify-between gap-2"
                  >
                    <div className="flex items-center gap-3 min-w-0">
                      <div className="w-8 h-8 rounded-full bg-blue-100 text-blue-900 font-bold text-[10px] flex items-center justify-center shrink-0">
                        {task.subjectName ? task.subjectName.slice(0, 2).toUpperCase() : 'TG'}
                      </div>
                      <div className="min-w-0">
                        <div className="flex items-center gap-2">
                          <span className="font-bold text-xs text-slate-900">{task.subjectName}</span>
                          <span className="text-[11px] font-mono text-slate-500 font-semibold">• Tenggat {task.dueDate}</span>
                        </div>
                        <p className="text-xs text-slate-600 font-medium truncate mt-0.5">
                          {task.title}
                        </p>
                      </div>
                    </div>

                    <div className="flex items-center gap-2 shrink-0 self-start sm:self-center">
                      {isGraded ? (
                        <span className="px-2.5 py-0.5 rounded-md text-xs font-bold bg-emerald-800 text-white shadow-2xs">
                          Skor: {task.score}
                        </span>
                      ) : isSubmitted ? (
                        <span className="px-2.5 py-0.5 rounded-md text-xs font-bold bg-blue-900 text-white shadow-2xs">
                          Terkirim
                        </span>
                      ) : (
                        <span className="px-2.5 py-0.5 rounded-md text-xs font-bold bg-amber-600 text-white shadow-2xs">
                          Aktif
                        </span>
                      )}

                      <Link
                        href="/siswa/tugas"
                        className="px-2.5 py-1 rounded-lg bg-blue-900 hover:bg-blue-950 text-white text-xs font-bold shadow-xs transition-colors flex items-center gap-1"
                      >
                        <span>{isGraded ? 'Ulasan' : 'Buka'}</span>
                        <ArrowRight className="w-3 h-3" />
                      </Link>
                    </div>
                  </div>
                );
              })}

              {tasksToRender.length === 0 && (
                <EmptyState
                  icon={CheckSquare}
                  title="Tidak Ada Tugas Berjalan"
                  description="Semua tugas KBM Anda telah selesai dikerjakan atau belum ada penugasan baru dari guru."
                  actionText="Buka Modul Belajar"
                  onAction={() => router.push('/siswa/tugas')}
                />
              )}
            </div>
          </div>
        </div>

        {/* KOLOM KANAN (5 Kolom): CBT Aktif / Walas Direct Connect / Pengumuman */}
        <div className="lg:col-span-5 flex flex-col gap-3 h-full overflow-hidden">
          {/* 1. Card: CBT Ujian Aktif Hari Ini */}
          {activeExam && (
            <div className="border-2 border-rose-600 bg-rose-50/30 rounded-xl p-3.5 shadow-xs space-y-2 shrink-0">
              <div className="flex items-center justify-between pb-1.5 border-rose-100">
                <div className="flex items-center gap-1.5">
                  <span className="text-[11px] font-bold uppercase tracking-wider text-rose-800">
                    Ujian CBT Aktif Sekarang
                  </span>
                </div>
                <span className="bg-rose-950 text-white font-mono font-bold text-xs px-2 py-0.5 rounded-md shadow-2xs">
                  Token: {activeExam.token}
                </span>
              </div>

              <div>
                <h3 className="font-bold text-sm text-slate-900 leading-snug truncate">
                  {activeExam.title}
                </h3>
                <p className="text-xs text-slate-600 font-medium mt-0.5 flex items-center gap-2">
                  <span>{activeExam.roomName}</span>
                  <span>•</span>
                  <span>{activeExam.durationMinutes} Menit</span>
                  <span>•</span>
                  <span>KKTP: {activeExam.kktp}</span>
                </p>
              </div>

              <div className="pt-1.5 border-t border-rose-100 flex items-center justify-between">
                <span className="text-xs text-slate-500 font-medium">Batas Waktu Sesi Aktif</span>
                <Link
                  href={`/siswa/cbt/${activeExam.id}`}
                  className="px-3.5 py-1.5 rounded-lg bg-rose-700 hover:bg-rose-800 text-white text-xs font-bold transition-all shadow-xs flex items-center gap-1.5"
                >
                  <MonitorPlay className="w-3.5 h-3.5 text-white" />
                  <span>Buka Ujian ➔</span>
                </Link>
              </div>
            </div>
          )}

          {/* 2. Card: Wali Kelas Siswa Direct Connect */}
          <div className="shrink-0 bg-white border border-slate-200/90 rounded-xl p-3.5 shadow-xs space-y-2.5">
            <div className="flex items-center justify-between pb-2 border-b border-slate-100">
              <div className="flex items-center gap-2">
                <div className="w-7 h-7 rounded-lg bg-slate-100 text-slate-800 flex items-center justify-center shrink-0 border border-slate-200">
                  <School className="w-3.5 h-3.5" />
                </div>
                <span className="text-xs font-bold text-slate-800">Wali Kelas Ananda</span>
              </div>
              <span className="px-2.5 py-0.5 rounded-md text-xs font-bold bg-slate-800 text-white font-mono shadow-2xs">
                {studentClass}
              </span>
            </div>

            <div className="flex items-center gap-3">
              <div className="w-11 h-11 rounded-xl bg-blue-900 text-white font-bold text-sm flex items-center justify-center shrink-0 shadow-xs">
                BS
              </div>
              <div className="min-w-0 flex-1">
                <h4 className="text-sm font-bold text-slate-900 truncate">
                  Budi Santoso, S.Kom., M.T.
                </h4>
                <p className="text-xs text-slate-500 font-mono font-medium truncate">
                  NIP: 198503152010121001
                </p>
                <p className="text-xs text-blue-900 font-semibold truncate">
                  Wali Kelas 7-A & Guru Informatika
                </p>
              </div>
            </div>

            <div className="grid grid-cols-2 gap-2 pt-0.5">
              <a
                href="https://wa.me/6281234567890"
                target="_blank"
                rel="noreferrer"
                className="px-3 py-2 rounded-xl bg-emerald-800 hover:bg-emerald-900 text-white text-xs font-bold text-center transition-colors flex items-center justify-center gap-1.5 shadow-xs"
              >
                <MessageCircle className="w-3.5 h-3.5 text-white" />
                <span>WhatsApp</span>
              </a>

              <Link
                href="/siswa/konseling"
                className="px-3 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold text-center transition-colors flex items-center justify-center gap-1.5 shadow-xs"
              >
                <HeartHandshake className="w-3.5 h-3.5 text-white" />
                <span>Konseling BK</span>
              </Link>
            </div>
          </div>

          {/* 3. Card: Pengumuman & Agenda Penting */}
          <div className="flex-1 min-h-0 bg-white border border-slate-200/90 rounded-xl p-3.5 shadow-xs flex flex-col overflow-hidden">
            <div className="shrink-0 flex items-center justify-between pb-2.5 border-b border-slate-100">
              <div className="flex items-center gap-2">
                <div className="w-7 h-7 rounded-lg bg-blue-50 text-blue-800 flex items-center justify-center shrink-0 border border-blue-200">
                  <BookOpen className="w-3.5 h-3.5" />
                </div>
                <div>
                  <h3 className="text-xs font-bold text-slate-800">Pengumuman & Agenda</h3>
                  <p className="text-[11px] text-slate-500 font-normal">Informasi Resmi Sekolah</p>
                </div>
              </div>
              <span className="px-2 py-0.5 rounded-md text-[11px] font-bold bg-slate-800 text-white shadow-2xs">
                Labschool UM
              </span>
            </div>

            <div className="flex-1 min-h-0 overflow-y-auto space-y-2 pr-1 mt-2.5">
              {announcementsData && announcementsData.length > 0 ? (
                announcementsData.map((ann) => {
                  const annDate = ann.publish_at ? ann.publish_at.split('T')[0] : (ann.published_at ? ann.published_at.split('T')[0] : 'Terkini');
                  const annCategory = ann.priority === 'urgent' ? 'Penting' : 'Akademik';
                  const accentBorder = ann.priority === 'urgent' ? 'border-l-rose-700' : 'border-l-blue-900';
                  const badgeBg = ann.priority === 'urgent' ? 'bg-rose-700 text-white' : 'bg-blue-900 text-white';

                  return (
                    <div 
                      key={ann.id} 
                      className={`p-3 rounded-xl border border-slate-200/90 bg-white hover:bg-slate-50 transition-colors border-l-4 ${accentBorder} shadow-2xs space-y-1.5`}
                    >
                      <div className="flex items-center justify-between">
                        <span className="text-xs font-semibold text-slate-500 font-mono">
                          {annDate}
                        </span>
                        <span className={`px-2 py-0.5 rounded-md text-[11px] font-bold shadow-2xs ${badgeBg}`}>
                          {annCategory}
                        </span>
                      </div>
                      <h5 className="text-xs font-bold text-slate-900 leading-snug">
                        {ann.title}
                      </h5>
                      <p className="text-xs text-slate-600 leading-relaxed font-normal">
                        {ann.body || ann.content || ''}
                      </p>
                    </div>
                  );
                })
              ) : (
                <div className="py-6 text-center text-xs text-slate-500 font-medium">
                  Belum ada pengumuman terbaru hari ini.
                </div>
              )}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
