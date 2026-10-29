'use client';

import React from 'react';
import Link from 'next/link';
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
  ExternalLink,
  ChevronRight,
  ShieldCheck,
  BellRing
} from 'lucide-react';
import { useAuth } from '@/lib/auth-context';
import { useTeacherDashboard, useTeacherClasses, useTeacherSchedules } from '@/lib/use-teacher-data';

export default function GuruDashboardPage() {
  const { user } = useAuth();
  const { data: dashboardData } = useTeacherDashboard();
  const { data: teacherClasses = [] } = useTeacherClasses();
  const { data: teacherSchedules = [] } = useTeacherSchedules();

  const teacherDisplayName = user?.full_name || user?.name || dashboardData?.teacherName || 'Agus Santoso, M.Pd.';
  const nipDisplay = dashboardData?.nip || user?.nip || '198501012010011001';
  const isHomeroom = (user?.roles || []).some((r: any) => String(r).toLowerCase() === 'homeroom');
  const isCounselor = (user?.roles || []).some((r: any) => String(r).toLowerCase() === 'counselor');
  const roleLabel = isHomeroom ? 'Wali Kelas' : isCounselor ? 'Guru BK' : 'Guru Pengampu';
  const initials = teacherDisplayName
    ? teacherDisplayName
        .split(' ')
        .filter(Boolean)
        .map((n: string) => n[0])
        .slice(0, 2)
        .join('')
        .toUpperCase()
    : 'GR';

  const totalStudents = teacherClasses.reduce((acc, c) => acc + (c.studentCount || 0), 0) || 94;
  const totalRombel = teacherClasses.length || 3;
  const rombelSummary = teacherClasses.length > 0 
    ? teacherClasses.map(c => `${c.name} (${c.studentCount})`).join(' • ') + ' Siswa'
    : 'IX-A (32) • IX-B (30) • VIII-C (32) Siswa';
  const recentSubmissions = [
    { 
      id: 'sub-1', 
      student: 'Ahmad Fauzan Maulana', 
      nis: '240901',
      avatar_url: 'https://images.unsplash.com/photo-1539571696357-5a69c17a67c6?auto=format&fit=crop&q=80&w=150',
      class: 'Kelas IX-A', 
      task: 'Tugas Mandiri 03: Desain Web Portofolio Responsif', 
      time: '14:32 WIB', 
      status: 'graded', 
      score: 95 
    },
    { 
      id: 'sub-2', 
      student: 'Nabila Putri Andini', 
      nis: '240902',
      avatar_url: 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&q=80&w=150',
      class: 'Kelas IX-A', 
      task: 'Tugas Mandiri 03: Desain Web Portofolio Responsif', 
      time: '16:45 WIB', 
      status: 'pending' 
    },
    { 
      id: 'sub-3', 
      student: 'Rizky Pratama Wijaya', 
      nis: '240903',
      avatar_url: 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&q=80&w=150',
      class: 'Kelas IX-A', 
      task: 'Tugas Mandiri 03: Desain Web Portofolio Responsif', 
      time: '08:12 WIB', 
      status: 'late' 
    },
    { 
      id: 'sub-4', 
      student: 'Siti Aisyah Rahmawati', 
      nis: '240904',
      avatar_url: 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?auto=format&fit=crop&q=80&w=150',
      class: 'Kelas IX-A', 
      task: 'Tugas Mandiri 03: Desain Web Portofolio Responsif', 
      time: '11:20 WIB', 
      status: 'graded', 
      score: 88 
    },
  ];

  const pendingLeaves = [
    {
      id: 'leave-1',
      student: 'Dimas Anggara Putra',
      avatar_url: 'https://images.unsplash.com/photo-1492562080023-ab3db95bfbce?auto=format&fit=crop&q=80&w=150',
      class: 'Kelas IX-A',
      type: 'Izin Sakit',
      period: '14 - 15 Okt 2026',
      note: 'Surat keterangan dokter RS Lavalette terlampir.',
      badgeClass: 'bg-amber-600 text-white',
      borderClass: 'border-l-amber-600',
    },
    {
      id: 'leave-2',
      student: 'Karina Safitri',
      avatar_url: 'https://images.unsplash.com/photo-1508214751196-bcfd4ca60f91?auto=format&fit=crop&q=80&w=150',
      class: 'Kelas IX-A',
      type: 'Dispensasi',
      period: '15 Okt 2026',
      note: 'Delegasi Olimpiade Sains Nasional (OSN) Informatika Kota Malang.',
      badgeClass: 'bg-blue-800 text-white',
      borderClass: 'border-l-blue-800',
    },
  ];

  const teacherAnnouncements = [
    {
      id: 'ann-1',
      title: 'Batas Akhir Input Nilai Formatif & STS Semester Gasal',
      date: '24 Okt 2026',
      category: 'Akademik',
      content: 'Buku Nilai Ledger dan deskripsi capaian KKTP wajib diselesaikan sebelum jadwal pencetakan E-Progress Report.',
      accentClass: 'border-l-blue-900',
      badgeClass: 'bg-blue-900 text-white',
    },
    {
      id: 'ann-2',
      title: 'Lokakarya Kurikulum Merdeka: Modul P5 Rekayasa Teknologi',
      date: '18 Okt 2026',
      category: 'Pelatihan Guru',
      content: 'Penyelarasan rubrik asesmen dimensi Bernalar Kritis dan Gotong Royong bersama koordinator P5.',
      accentClass: 'border-l-emerald-800',
      badgeClass: 'bg-emerald-800 text-white',
    },
  ];

  return (
    <div className="flex flex-col gap-3.5 max-w-full xl:h-[calc(100vh-112px)] xl:overflow-hidden text-slate-900 font-sans">
      {/* ─── 1. INTEGRATED TEACHER PROFILE & REALTIME STATUS HERO BANNER (IDENTICAL TO ORTU) ─── */}
      <div className="bg-white border border-slate-200/90 rounded-xl p-3 px-4 shadow-xs shrink-0">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          {/* Left: Identity & Badges */}
          <div className="flex items-center gap-3.5 min-w-0">
            {user?.avatar_url ? (
              <img
                src={user.avatar_url}
                alt={teacherDisplayName}
                className="w-12 h-12 rounded-xl object-cover border-2 border-slate-200 shadow-xs shrink-0"
              />
            ) : (
              <div className="w-12 h-12 rounded-xl bg-blue-900 text-white font-extrabold flex items-center justify-center text-base shadow-xs shrink-0">
                {initials}
              </div>
            )}
            <div className="min-w-0">
              <div className="flex flex-wrap items-center gap-2">
                <h1 className="text-lg font-extrabold text-slate-900 tracking-tight truncate">
                  {teacherDisplayName}
                </h1>
                <span className="px-2 py-0.5 rounded-lg text-xs font-bold bg-blue-900 text-white shadow-2xs font-mono">
                  {roleLabel}
                </span>
                <span className="px-2 py-0.5 rounded-lg text-xs font-semibold bg-slate-100 border border-slate-300 text-slate-700 font-mono">
                  NIP: {nipDisplay}
                </span>
                <span className="px-2.5 py-0.5 rounded-lg text-xs font-bold shadow-2xs bg-emerald-800 text-white">
                  Aktif Mengajar
                </span>
              </div>

              {/* Status & Detail Info (Matching Ortu structure) */}
              <div className="mt-1 flex flex-wrap items-center gap-x-3 text-xs text-slate-600">
                <div className="inline-flex items-center gap-1 font-semibold text-emerald-800">
                  <CheckCircle2 className="w-3.5 h-3.5 shrink-0" />
                  <span>KBM: {dashboardData?.todaySchedulesCount ?? 2} Sesi Terjadwal</span>
                </div>
                <span className="text-slate-300 hidden sm:inline">•</span>
                <div>
                  <span>Mata Pelajaran: </span>
                  <strong className="text-slate-900 font-semibold">{isHomeroom ? 'Matematika & Perwalian 7-A' : 'Matematika Terapan'}</strong>
                </div>
                <span className="text-slate-300 hidden sm:inline">•</span>
                <div>
                  <span>Kurikulum: </span>
                  <strong className="text-slate-900 font-semibold">Fase D (Kurikulum Merdeka)</strong>
                </div>
              </div>
            </div>
          </div>

          {/* Right: Quick Action Buttons */}
          <div className="flex flex-wrap items-center gap-2 shrink-0">
            <Link
              href="/guru/jurnal"
              className="px-3.5 py-2 rounded-xl bg-emerald-800 hover:bg-emerald-900 text-white text-xs font-bold transition-all shadow-xs flex items-center gap-1.5"
            >
              <CalendarCheck className="w-4 h-4 text-white" />
              <span>Jurnal & Presensi</span>
            </Link>
            <Link
              href="/guru/nilai"
              className="px-3.5 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold transition-all shadow-xs flex items-center gap-1.5"
            >
              <FileSpreadsheet className="w-4 h-4 text-white" />
              <span>Buku Nilai Ledger</span>
            </Link>
            <Link
              href="/guru/tugas"
              className="px-3.5 py-2 rounded-xl bg-white border border-slate-300 hover:bg-slate-100 text-slate-800 text-xs font-bold transition-colors shadow-xs flex items-center gap-1.5"
            >
              <CheckSquare className="w-4 h-4 text-slate-700" />
              <span>Tugas LMS</span>
            </Link>
          </div>
        </div>
      </div>

      {/* ─── 2. REIMAGINED VISUAL METRICS (4 EQUAL SAAS CARDS STRIP - MATCHING ORTU) ─── */}
      <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3 shrink-0">
        {/* Metric 1: Rombel & Siswa */}
        <div className="bg-white border border-slate-200/90 rounded-xl p-3.5 shadow-xs flex flex-col justify-between hover:border-slate-300 transition-all">
          <div className="flex items-center justify-between pb-1.5 border-b border-slate-100">
            <span className="text-xs font-bold text-slate-700 uppercase tracking-wider truncate">Rombel & Siswa Binaan</span>
            <div className="w-7 h-7 rounded-lg bg-blue-50 text-blue-900 border border-blue-200 flex items-center justify-center shrink-0">
              <Users className="w-3.5 h-3.5" />
            </div>
          </div>
          <div className="my-1.5">
            <div className="flex items-baseline justify-between">
              <div className="flex items-baseline gap-1">
                <span className="text-3xl font-extrabold text-slate-900 tracking-tight">
                  {totalStudents}
                </span>
                <span className="text-sm font-bold text-slate-500">Siswa</span>
              </div>
              <span className="text-xs font-bold px-2 py-0.5 rounded-md bg-blue-900 text-white shadow-2xs">
                {totalRombel} Rombel
              </span>
            </div>
            <p className="text-xs text-slate-500 font-medium mt-0.5 truncate">
              {rombelSummary}
            </p>
          </div>
          <div className="pt-2 border-t border-slate-100">
            <Link
              href="/guru/siswa"
              className="text-xs font-bold text-slate-700 hover:text-blue-900 flex items-center justify-between group transition-colors"
            >
              <span>Daftar Siswa & Poin Binaan</span>
              <ArrowRight className="w-3.5 h-3.5 text-slate-400 group-hover:text-blue-900 group-hover:translate-x-0.5 transition-all" />
            </Link>
          </div>
        </div>

        {/* Metric 2: Sesi Mengajar Hari Ini */}
        <div className="bg-white border border-slate-200/90 rounded-xl p-3.5 shadow-xs flex flex-col justify-between hover:border-slate-300 transition-all">
          <div className="flex items-center justify-between pb-1.5 border-b border-slate-100">
            <span className="text-xs font-bold text-slate-700 uppercase tracking-wider truncate">Jadwal Mengajar Hari Ini</span>
            <div className="w-7 h-7 rounded-lg bg-teal-50 text-teal-800 border border-teal-200 flex items-center justify-center shrink-0">
              <Calendar className="w-3.5 h-3.5" />
            </div>
          </div>
          <div className="my-1.5">
            <div className="flex items-baseline justify-between">
              <div className="flex items-baseline gap-1">
                <span className="text-3xl font-extrabold text-slate-900 tracking-tight">
                  {dashboardData?.todaySchedulesCount ?? 2} Sesi
                </span>
                <span className="text-sm font-bold text-slate-500">(4 JP)</span>
              </div>
              <span className="text-xs font-bold px-2 py-0.5 rounded-md bg-teal-800 text-white shadow-2xs">
                KBM Aktif
              </span>
            </div>
            <p className="text-xs text-slate-500 font-medium mt-0.5 truncate">
              {teacherSchedules.length > 0 
                ? `${teacherSchedules[0]?.roomName || 'Lab Komputer'} • ${teacherSchedules[0]?.className || 'Kelas 7-A'}` 
                : 'Lab Komputer 1 & Ruang KBM'}
            </p>
          </div>
          <div className="pt-2 border-t border-slate-100">
            <Link
              href="/guru/jadwal"
              className="text-xs font-bold text-slate-700 hover:text-teal-800 flex items-center justify-between group transition-colors"
            >
              <span>Buka Jadwal Mingguan Lengkap</span>
              <ArrowRight className="w-3.5 h-3.5 text-slate-400 group-hover:text-teal-800 group-hover:translate-x-0.5 transition-all" />
            </Link>
          </div>
        </div>

        {/* Metric 3: Antrean Tugas LMS */}
        <div className="bg-white border border-slate-200/90 rounded-xl p-3.5 shadow-xs flex flex-col justify-between hover:border-slate-300 transition-all">
          <div className="flex items-center justify-between pb-1.5 border-b border-slate-100">
            <span className="text-xs font-bold text-slate-700 uppercase tracking-wider truncate">Tugas Perlu Penilaian</span>
            <div className="w-7 h-7 rounded-lg bg-amber-50 text-amber-800 border border-amber-200 flex items-center justify-center shrink-0">
              <CheckSquare className="w-3.5 h-3.5" />
            </div>
          </div>
          <div className="my-1.5">
            <div className="flex items-baseline justify-between">
              <span className="text-3xl font-extrabold text-slate-900 tracking-tight">
                6 Berkas
              </span>
              <span className="text-xs font-bold px-2 py-0.5 rounded-md bg-amber-600 text-white shadow-2xs">
                Perlu Review
              </span>
            </div>
            <p className="text-xs text-slate-500 font-medium mt-0.5 truncate">
              Penugasan Aktif Berjalan di Seluruh Kelas
            </p>
          </div>
          <div className="pt-2 border-t border-slate-100">
            <Link
              href="/guru/tugas"
              className="text-xs font-bold text-slate-700 hover:text-amber-800 flex items-center justify-between group transition-colors"
            >
              <span>Periksa Jawaban Siswa</span>
              <ArrowRight className="w-3.5 h-3.5 text-slate-400 group-hover:text-amber-800 group-hover:translate-x-0.5 transition-all" />
            </Link>
          </div>
        </div>

        {/* Metric 4: Keterisian Jurnal KBM */}
        <div className="bg-white border border-slate-200/90 rounded-xl p-3.5 shadow-xs flex flex-col justify-between hover:border-slate-300 transition-all">
          <div className="flex items-center justify-between pb-1.5 border-b border-slate-100">
            <span className="text-xs font-bold text-slate-700 uppercase tracking-wider truncate">Keterisian Jurnal & Presensi</span>
            <div className="w-7 h-7 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200 flex items-center justify-center shrink-0">
              <CalendarCheck className="w-3.5 h-3.5" />
            </div>
          </div>
          <div className="my-1.5">
            <div className="flex items-baseline justify-between">
              <span className="text-3xl font-extrabold text-slate-900 tracking-tight">
                100%
              </span>
              <span className="text-xs font-bold px-2 py-0.5 rounded-md bg-emerald-800 text-white shadow-2xs">
                Tertib Administrasi
              </span>
            </div>
            <p className="text-xs text-slate-500 font-medium mt-0.5 truncate">
              6 dari 6 Pertemuan Terdata & Tervalidasi
            </p>
          </div>
          <div className="pt-2 border-t border-slate-100">
            <Link
              href="/guru/jurnal"
              className="text-xs font-bold text-slate-700 hover:text-emerald-800 flex items-center justify-between group transition-colors"
            >
              <span>Riwayat Jurnal KBM Lengkap</span>
              <ArrowRight className="w-3.5 h-3.5 text-slate-400 group-hover:text-emerald-800 group-hover:translate-x-0.5 transition-all" />
            </Link>
          </div>
        </div>
      </div>

      {/* ─── 3. DUA KOLOM UTAMA: ALUR KBM HARI INI vs TUGAS & PERIZINAN (VIEWPORT OPTIMIZED) ─── */}
      <div className="xl:flex-1 xl:min-h-0 grid grid-cols-1 lg:grid-cols-12 gap-3.5">
        {/* KOLOM KIRI (7 Kolom): Jadwal Tatap Muka & Antrean Pengumpulan */}
        <div className="lg:col-span-7 flex flex-col gap-3 h-full overflow-hidden">
          {/* Card 1: Jadwal Tatap Muka Hari Ini (Clean Ortu Timeline Pattern) */}
          <div className="bg-white border border-slate-200/90 rounded-xl p-3.5 shadow-xs flex flex-col overflow-hidden shrink-0">
            <div className="shrink-0 flex items-center justify-between pb-2.5 border-b border-slate-100">
              <div className="flex items-center gap-2.5">
                <div className="w-8 h-8 rounded-lg bg-slate-100 text-slate-800 flex items-center justify-center border border-slate-200 shrink-0">
                  <Clock className="w-4 h-4 text-slate-800" />
                </div>
                <div>
                  <h2 className="text-sm font-bold text-slate-900 tracking-tight">
                    Jadwal Tatap Muka KBM Hari Ini
                  </h2>
                  <p className="text-xs text-slate-500 font-normal">
                    Rabu, 14 Oktober 2026 • Kurikulum Merdeka
                  </p>
                </div>
              </div>
              <span className="px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-800 text-white shadow-2xs">
                2 Sesi Mengajar
              </span>
            </div>

            <div className="space-y-2 mt-2.5">
              {/* Sesi 1: Selesai (Ortu Clean Card Pattern) */}
              <div className="p-3 rounded-xl border border-slate-200/80 bg-slate-50/50 hover:bg-slate-50 transition-all">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-1.5">
                  <div className="flex items-center gap-2">
                    <span className="font-mono text-xs font-bold text-slate-900">
                      07:30 - 09:00 WIB
                    </span>
                    <span className="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-bold bg-slate-200 text-slate-700">
                      Selesai KBM
                    </span>
                  </div>
                  <span className="text-xs text-slate-600 font-semibold bg-slate-100 px-2 py-0.5 rounded-md border border-slate-200 w-fit">
                    Lab Komputer 1
                  </span>
                </div>

                <div className="mt-2">
                  <h4 className="text-sm font-bold text-slate-900">
                    Informatika & Pemrograman (Kelas IX-A)
                  </h4>
                  <p className="text-xs text-slate-600 mt-0.5 font-medium">
                    Materi: <span className="font-semibold text-slate-800">Praktikum CSS Grid & Layout Responsif</span>
                  </p>
                  <p className="text-xs text-slate-500 font-normal mt-0.5">
                    Jurnal Terisi • 32 Siswa Hadir Tervalidasi
                  </p>
                </div>
              </div>

              {/* Sesi 2: Sedang Berlangsung (Ortu Highlighted Card Pattern) */}
              <div className="p-3 rounded-xl border border-emerald-300 bg-emerald-50/40 border-l-4 border-l-emerald-800 shadow-xs transition-all">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-1.5">
                  <div className="flex items-center gap-2">
                    <span className="font-mono text-xs font-bold text-slate-900">
                      10:00 - 11:30 WIB
                    </span>
                    <span className="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-bold bg-emerald-800 text-white shadow-2xs">
                      Sesi Berlangsung
                    </span>
                  </div>
                  <span className="text-xs text-slate-600 font-semibold bg-slate-100 px-2 py-0.5 rounded-md border border-slate-200 w-fit">
                    Lab Multimedia
                  </span>
                </div>

                <div className="mt-2 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                  <div>
                    <h4 className="text-sm font-bold text-slate-900">
                      Pemrograman Web & Logika Algoritma (Kelas IX-B)
                    </h4>
                    <p className="text-xs text-slate-600 mt-0.5 font-medium">
                      Materi: <span className="font-semibold text-slate-800">Penerapan Media Queries & Mobile Menu</span>
                    </p>
                    <p className="text-xs text-slate-500 font-normal mt-0.5">
                      Target 30 Siswa • Presensi KBM Belum Difinalkan
                    </p>
                  </div>
                  <Link
                    href="/guru/jurnal"
                    className="px-3.5 py-1.5 rounded-lg bg-emerald-800 hover:bg-emerald-900 text-white text-xs font-bold shadow-xs transition-colors shrink-0 self-start sm:self-auto flex items-center gap-1.5"
                  >
                    <CalendarCheck className="w-3.5 h-3.5" />
                    <span>Input Jurnal</span>
                  </Link>
                </div>
              </div>
            </div>
          </div>

          {/* Card 2: Pengumpulan Tugas Siswa Terbaru (Flex-1 with Internal Scroll) */}
          <div className="flex-1 min-h-0 bg-white border border-slate-200/90 rounded-xl p-3.5 shadow-xs flex flex-col overflow-hidden">
            <div className="flex items-center justify-between pb-2.5 border-b border-slate-100 shrink-0">
              <div className="flex items-center gap-2">
                <div className="w-8 h-8 rounded-lg bg-blue-50 text-blue-900 border border-blue-200 flex items-center justify-center shrink-0">
                  <CheckSquare className="w-4 h-4" />
                </div>
                <div>
                  <h2 className="text-sm font-bold text-slate-900 tracking-tight">
                    Pengumpulan Tugas Siswa Terbaru
                  </h2>
                  <p className="text-xs text-slate-500 font-normal">
                    Memerlukan koreksi dan penilaian guru pengampu
                  </p>
                </div>
              </div>
              <Link
                href="/guru/tugas"
                className="text-xs font-bold text-blue-900 hover:text-blue-950 flex items-center gap-1"
              >
                <span>Lihat Semua Tugas</span>
                <ChevronRight className="w-3.5 h-3.5" />
              </Link>
            </div>

            <div className="flex-1 min-h-0 overflow-y-auto space-y-2 pr-1 mt-2.5">
              {recentSubmissions.map((sub) => {
                const isGraded = sub.status === 'graded';
                const isLate = sub.status === 'late';

                return (
                  <div
                    key={sub.id}
                    className="p-3 rounded-xl border border-slate-200/90 bg-white hover:bg-slate-50 transition-colors flex flex-col sm:flex-row sm:items-center justify-between gap-2"
                  >
                    <div className="flex items-center gap-3 min-w-0">
                      {sub.avatar_url ? (
                        <img
                          src={sub.avatar_url}
                          alt={sub.student}
                          className="w-8 h-8 rounded-full object-cover border border-slate-200 shrink-0 shadow-2xs"
                        />
                      ) : (
                        <div className="w-8 h-8 rounded-full bg-blue-100 text-blue-900 font-bold text-[10px] flex items-center justify-center shrink-0">
                          {sub.student.slice(0, 2).toUpperCase()}
                        </div>
                      )}
                      <div className="min-w-0">
                        <div className="flex items-center gap-2">
                          <span className="font-bold text-xs text-slate-900">{sub.student}</span>
                          <span className="text-[11px] font-mono text-slate-500 font-semibold">({sub.class})</span>
                          <span className="text-[10px] text-slate-400 font-mono">{sub.time}</span>
                        </div>
                        <p className="text-xs text-slate-600 font-medium truncate mt-0.5">
                          {sub.task}
                        </p>
                      </div>
                    </div>

                    <div className="flex items-center gap-2 shrink-0 self-start sm:self-center">
                      {isGraded ? (
                        <span className="px-2.5 py-0.5 rounded-md text-xs font-bold bg-emerald-800 text-white shadow-2xs">
                          Nilai: {sub.score}
                        </span>
                      ) : isLate ? (
                        <span className="px-2.5 py-0.5 rounded-md text-xs font-bold bg-rose-700 text-white shadow-2xs">
                          Terlambat
                        </span>
                      ) : (
                        <span className="px-2.5 py-0.5 rounded-md text-xs font-bold bg-amber-600 text-white shadow-2xs">
                          Perlu Koreksi
                        </span>
                      )}

                      <Link
                        href={`/guru/tugas/asg-001/penilaian`}
                        className="px-2.5 py-1 rounded-lg bg-blue-900 hover:bg-blue-950 text-white text-xs font-bold shadow-xs transition-colors flex items-center gap-1"
                      >
                        <span>{isGraded ? 'Detail' : 'Nilai'}</span>
                        <ArrowRight className="w-3 h-3" />
                      </Link>
                    </div>
                  </div>
                );
              })}
            </div>
          </div>
        </div>

        {/* KOLOM KANAN (5 Kolom): Perizinan Siswa Binaan, Agenda Guru & Status Lab */}
        <div className="lg:col-span-5 flex flex-col gap-3 h-full overflow-hidden">
          {/* 1. Perizinan Siswa Binaan (Prioritas Penting Walas) */}
          <div className="bg-white border border-slate-200/90 rounded-xl p-3.5 shadow-xs space-y-2 shrink-0">
            <div className="flex items-center justify-between pb-2 border-b border-slate-100">
              <div className="flex items-center gap-2">
                <div className="w-7 h-7 rounded-lg bg-amber-50 text-amber-800 border border-amber-200 flex items-center justify-center shrink-0">
                  <UserCheck className="w-3.5 h-3.5" />
                </div>
                <div>
                  <h2 className="text-xs font-bold text-slate-800">
                    Perizinan Siswa Binaan
                  </h2>
                  <p className="text-[11px] text-slate-500 font-normal">
                    Menunggu konfirmasi & persetujuan Walas
                  </p>
                </div>
              </div>
              <span className="px-2.5 py-0.5 rounded-md text-xs font-bold bg-slate-800 text-white shadow-2xs">
                2 Menunggu
              </span>
            </div>

            <div className="space-y-2">
              {pendingLeaves.map((leave) => (
                <div
                  key={leave.id}
                  className={`p-2.5 rounded-xl border border-slate-200/90 bg-white hover:bg-slate-50 transition-colors border-l-4 ${leave.borderClass} space-y-1 shadow-2xs`}
                >
                  <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2.5 min-w-0">
                      <img
                        src={leave.avatar_url}
                        alt={leave.student}
                        className="w-7 h-7 rounded-full object-cover border border-slate-200 shrink-0 shadow-2xs"
                      />
                      <div className="min-w-0">
                        <h4 className="text-xs font-bold text-slate-900 truncate">{leave.student}</h4>
                        <p className="text-[10px] text-slate-500 font-semibold">{leave.class} • {leave.period}</p>
                      </div>
                    </div>
                    <span className={`px-2 py-0.5 rounded text-[10px] font-bold shrink-0 shadow-2xs ${leave.badgeClass}`}>
                      {leave.type}
                    </span>
                  </div>
                  <div className="flex items-center justify-between text-xs pt-1 border-t border-slate-100">
                    <p className="text-[11px] text-slate-600 font-normal truncate mr-2">
                      {leave.note}
                    </p>
                    <Link
                      href="/guru/perizinan"
                      className="text-[11px] font-bold text-blue-900 hover:text-blue-950 shrink-0 hover:underline"
                    >
                      Verifikasi ➔
                    </Link>
                  </div>
                </div>
              ))}
            </div>
          </div>

          {/* 2. Pengumuman & Agenda Guru (Matching Ortu Announcement Pattern) */}
          <div className="flex-1 min-h-0 bg-white border border-slate-200/90 rounded-xl p-3.5 shadow-xs flex flex-col overflow-hidden">
            <div className="flex items-center justify-between pb-2 border-b border-slate-100 shrink-0">
              <div className="flex items-center gap-2">
                <div className="w-7 h-7 rounded-lg bg-blue-50 text-blue-900 border border-blue-200 flex items-center justify-center shrink-0">
                  <BookOpen className="w-3.5 h-3.5" />
                </div>
                <div>
                  <h2 className="text-xs font-bold text-slate-800">
                    Pengumuman & Agenda Guru
                  </h2>
                  <p className="text-[11px] text-slate-500 font-normal">
                    Instruksi Kurikulum & Sekolah
                  </p>
                </div>
              </div>
              <span className="px-2 py-0.5 rounded-md text-[11px] font-bold bg-slate-800 text-white shadow-2xs">
                Labschool UM
              </span>
            </div>

            <div className="flex-1 min-h-0 overflow-y-auto space-y-2 pr-1 mt-2">
              {teacherAnnouncements.map((ann) => (
                <div
                  key={ann.id}
                  className={`p-2.5 rounded-xl border border-slate-200/90 bg-white hover:bg-slate-50 transition-colors border-l-4 ${ann.accentClass} space-y-1 shadow-2xs`}
                >
                  <div className="flex items-center justify-between">
                    <span className="text-xs font-semibold text-slate-500 font-mono">
                      {ann.date}
                    </span>
                    <span className={`px-2 py-0.5 rounded-md text-[10px] font-bold shadow-2xs ${ann.badgeClass}`}>
                      {ann.category}
                    </span>
                  </div>
                  <h4 className="text-xs font-bold text-slate-900 leading-snug">
                    {ann.title}
                  </h4>
                  <p className="text-xs text-slate-600 leading-relaxed font-normal">
                    {ann.content}
                  </p>
                </div>
              ))}
            </div>
          </div>

          {/* 3. Kesiapan Sarpras & Laboratorium */}
          <div className="bg-white border border-slate-200/90 rounded-xl p-3.5 shadow-xs space-y-2 shrink-0">
            <div className="flex items-center justify-between pb-1.5 border-b border-slate-100">
              <div className="flex items-center gap-2">
                <div className="w-6 h-6 rounded-lg bg-teal-50 text-teal-800 border border-teal-200 flex items-center justify-center shrink-0">
                  <Building2 className="w-3.5 h-3.5" />
                </div>
                <h3 className="text-xs font-bold text-slate-800">Kesiapan Sarpras KBM</h3>
              </div>
              <Link href="/guru/sarpras" className="text-xs font-bold text-teal-800 hover:underline">
                Pinjam Ruang ➔
              </Link>
            </div>

            <div className="grid grid-cols-2 gap-2 text-xs">
              <div className="p-2.5 rounded-xl bg-slate-50 border border-slate-200">
                <span className="text-[10px] font-bold text-slate-500 uppercase">Lab Komputer 1</span>
                <p className="font-bold text-slate-900 mt-0.5 text-xs">32 PC Aktif</p>
                <span className="text-[10px] text-emerald-800 font-semibold">Jaringan Gigabit OK</span>
              </div>
              <div className="p-2.5 rounded-xl bg-slate-50 border border-slate-200">
                <span className="text-[10px] font-bold text-slate-500 uppercase">Lab Multimedia</span>
                <p className="font-bold text-slate-900 mt-0.5 text-xs">30 PC Aktif</p>
                <span className="text-[10px] text-emerald-800 font-semibold">Proyektor Siap</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
