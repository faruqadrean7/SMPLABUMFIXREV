'use client';

import React from 'react';
import Link from 'next/link';
import { 
  CalendarCheck, 
  FileSpreadsheet, 
  Award, 
  Receipt, 
  Clock, 
  ArrowRight, 
  CheckCircle2, 
  AlertCircle,
  HeartHandshake, 
  BookOpen, 
  ChevronRight, 
  FileCheck, 
  MessageCircle, 
  School,
  RefreshCw,
  Info
} from 'lucide-react';
import { useGuardian } from '@/lib/guardian-context';
import { useSchoolAnnouncements } from '@/lib/use-student-data';

export default function GuardianDashboardPage() {
  const { guardian, activeChild, setActiveChildId, activeGrades, activeSchedule, isLoading, isError, refetchAll } = useGuardian();
  const { data: announcementsData, isLoading: isAnnLoading } = useSchoolAnnouncements();

  if (isLoading) {
    return (
      <div className="flex flex-col gap-4 animate-pulse">
        <div className="h-20 bg-white border border-slate-200 rounded-xl p-4 flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="w-12 h-12 bg-slate-200 rounded-xl" />
            <div className="space-y-2">
              <div className="h-4 w-40 bg-slate-200 rounded" />
              <div className="h-3 w-28 bg-slate-100 rounded" />
            </div>
          </div>
          <div className="h-8 w-32 bg-slate-200 rounded-lg" />
        </div>
        <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3">
          {[1, 2, 3, 4].map(i => (
            <div key={i} className="h-32 bg-white border border-slate-200 rounded-xl p-4 space-y-3">
              <div className="h-3 w-24 bg-slate-200 rounded" />
              <div className="h-7 w-16 bg-slate-300 rounded" />
              <div className="h-3 w-36 bg-slate-100 rounded" />
            </div>
          ))}
        </div>
        <div className="grid grid-cols-1 xl:grid-cols-12 gap-3.5">
          <div className="xl:col-span-8 h-96 bg-white border border-slate-200 rounded-2xl p-4" />
          <div className="xl:col-span-4 h-96 bg-white border border-slate-200 rounded-2xl p-4" />
        </div>
      </div>
    );
  }

  if (isError) {
    return (
      <div className="bg-white border border-rose-200 rounded-2xl p-6 text-center max-w-lg mx-auto my-12 shadow-xs">
        <div className="w-12 h-12 rounded-full bg-rose-100 text-rose-700 flex items-center justify-center mx-auto mb-3">
          <AlertCircle className="w-6 h-6" />
        </div>
        <h3 className="text-base font-bold text-slate-900 mb-1">Gagal Memuat Data Siswa</h3>
        <p className="text-xs text-slate-600 mb-4">
          Terjadi kendala saat menghubungkan ke layanan portal sekolah. Silakan coba kembali beberapa saat lagi.
        </p>
        <button
          onClick={refetchAll}
          className="px-4 py-2 bg-emerald-800 hover:bg-emerald-900 text-white rounded-xl text-xs font-semibold inline-flex items-center gap-2 transition-colors cursor-pointer"
        >
          <RefreshCw className="w-3.5 h-3.5" />
          <span>Coba Muat Ulang</span>
        </button>
      </div>
    );
  }

  // Dynamic calculations derived from active child's data
  const totalSubjects = activeGrades.length;
  const tuntasSubjects = activeGrades.filter(g => g.status === 'Tuntas').length;
  const remedialSubjects = activeGrades.filter(g => g.status !== 'Tuntas').length;
  const isPositivePoints = activeChild.points_balance >= 0;

  // Presence status configuration
  const presenceConfig = {
    hadir: {
      badgeBg: 'bg-emerald-800 text-white',
      badgeText: 'Tepat Waktu',
      icon: CheckCircle2,
      textColor: 'text-emerald-800',
      labelColor: 'text-emerald-900',
      statLabel: 'Tertib & Konsisten',
      statBadgeClass: 'bg-emerald-50 text-emerald-800 border-emerald-200',
    },
    sakit: {
      badgeBg: 'bg-amber-600 text-white',
      badgeText: 'Izin Sakit',
      icon: Clock,
      textColor: 'text-amber-800',
      labelColor: 'text-amber-900',
      statLabel: 'Surat Dokter Terverifikasi',
      statBadgeClass: 'bg-amber-50 text-amber-800 border-amber-200',
    },
    terlambat: {
      badgeBg: 'bg-rose-700 text-white',
      badgeText: 'Terlambat Masuk',
      icon: AlertCircle,
      textColor: 'text-rose-800',
      labelColor: 'text-rose-900',
      statLabel: 'Catatan Kedisiplinan',
      statBadgeClass: 'bg-rose-50 text-rose-800 border-rose-200',
    },
    izin: {
      badgeBg: 'bg-blue-800 text-white',
      badgeText: 'Izin Resmi',
      icon: CheckCircle2,
      textColor: 'text-blue-800',
      labelColor: 'text-blue-900',
      statLabel: 'Izin Disetujui',
      statBadgeClass: 'bg-blue-50 text-blue-800 border-blue-200',
    },
    alpa: {
      badgeBg: 'bg-rose-800 text-white',
      badgeText: 'Tanpa Keterangan',
      icon: AlertCircle,
      textColor: 'text-rose-800',
      labelColor: 'text-rose-900',
      statLabel: 'Peringatan Kehadiran',
      statBadgeClass: 'bg-rose-50 text-rose-800 border-rose-200',
    },
  }[activeChild.gate_attendance_today.status] || {
    badgeBg: 'bg-slate-800 text-white',
    badgeText: 'Terdata',
    icon: CheckCircle2,
    textColor: 'text-slate-800',
    labelColor: 'text-slate-900',
    statLabel: 'Status Terdata',
    statBadgeClass: 'bg-slate-50 text-slate-800 border-slate-200',
  };

  const PresenceIcon = presenceConfig.icon;

  return (
    <div className="flex flex-col gap-3.5 max-w-full xl:h-[calc(100vh-112px)] xl:overflow-hidden">
      {/* ─── 1. INTEGRATED CHILD PROFILE & REALTIME STATUS HERO BANNER ─── */}
      <div className="bg-white border border-slate-200/90 rounded-xl p-3 px-4 shadow-xs shrink-0">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          {/* Left: Active Child Identity & Presence */}
          <div className="flex items-center gap-3.5 min-w-0">
            {/* Avatar */}
            {activeChild.avatar_url ? (
              <img
                src={activeChild.avatar_url}
                alt={activeChild.name}
                className="w-12 h-12 rounded-xl object-cover border-2 border-slate-200 shadow-xs shrink-0"
              />
            ) : (
              <div className="w-12 h-12 rounded-xl bg-emerald-800 text-white font-bold text-base flex items-center justify-center shadow-xs shrink-0">
                {activeChild.initials}
              </div>
            )}

            {/* Name, Class, NIS and Presence status */}
            <div className="min-w-0">
              <div className="flex flex-wrap items-center gap-2">
                <h1 className="text-lg font-extrabold text-slate-900 tracking-tight truncate">
                  {activeChild.name}
                </h1>
                <span className="px-2 py-0.5 rounded-lg text-xs font-bold bg-emerald-800 text-white shadow-2xs font-mono">
                  {activeChild.class_name.replace('Kelas ', '')}
                </span>
                <span className="px-2 py-0.5 rounded-lg text-xs font-semibold bg-slate-100 border border-slate-300 text-slate-700 font-mono">
                  NIS: {activeChild.nis}
                </span>
                <span className={`px-2.5 py-0.5 rounded-lg text-xs font-bold shadow-2xs ${presenceConfig.badgeBg}`}>
                  {presenceConfig.badgeText}
                </span>
              </div>

              {/* Status Presensi & Info Walas */}
              <div className="mt-1 flex flex-wrap items-center gap-x-3 text-xs text-slate-600">
                <div className={`inline-flex items-center gap-1 font-semibold ${presenceConfig.textColor}`}>
                  <PresenceIcon className="w-3.5 h-3.5 shrink-0" />
                  <span>
                    Presensi: {activeChild.gate_attendance_today.status_label}
                    {activeChild.gate_attendance_today.tap_time !== '-' && ` (${activeChild.gate_attendance_today.tap_time})`}
                  </span>
                </div>
                <span className="text-slate-300 hidden sm:inline">•</span>
                <div>
                  <span>Wali Kelas: </span>
                  <strong className="text-slate-900 font-semibold">{activeChild.homeroom_teacher}</strong>
                </div>
              </div>
            </div>
          </div>

          {/* Right: Child Switcher & Quick Action */}
          <div className="flex items-center gap-2.5 self-start sm:self-auto shrink-0">
            {guardian.children.length > 1 && (
              <div className="p-1 bg-slate-100 border border-slate-200 rounded-xl flex items-center gap-1">
                {guardian.children.map((child) => {
                  const isSelected = child.id === activeChild.id;
                  return (
                    <button
                      key={child.id}
                      onClick={() => setActiveChildId(child.id)}
                      className={`flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-bold transition-all ${
                        isSelected
                          ? 'bg-white text-slate-900 shadow-xs border border-slate-200'
                          : 'text-slate-600 hover:text-slate-900 hover:bg-slate-200/50'
                      }`}
                    >
                      {child.avatar_url ? (
                        <img
                          src={child.avatar_url}
                          alt={child.name}
                          className={`w-4 h-4 rounded-full object-cover border ${isSelected ? 'border-emerald-600' : 'border-slate-300'}`}
                        />
                      ) : (
                        <div className={`w-4 h-4 rounded-full text-[9px] font-bold flex items-center justify-center ${isSelected ? 'bg-emerald-800 text-white' : 'bg-slate-300 text-slate-700'}`}>
                          {child.initials}
                        </div>
                      )}
                      <span>{child.name.split(' ')[0]}</span>
                      <span className="text-[10px] text-slate-500 font-mono font-medium">({child.class_name.replace('Kelas ', '')})</span>
                    </button>
                  );
                })}
              </div>
            )}

            <Link
              href="/ortu/presensi"
              className="px-3.5 py-2 rounded-xl bg-emerald-800 hover:bg-emerald-900 text-white text-xs font-bold transition-all shadow-xs flex items-center gap-1.5"
            >
              <CalendarCheck className="w-4 h-4 text-white" />
              <span>Ajukan Surat Izin</span>
            </Link>
          </div>
        </div>
      </div>

      {/* ─── 2. REIMAGINED VISUAL METRICS (4 EQUAL CARDS STRIP) ─── */}
      <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3 shrink-0">
        {/* Metric 1: Kehadiran Siswa */}
        <div className="bg-white border border-slate-200/90 rounded-xl p-3.5 shadow-xs flex flex-col justify-between hover:border-slate-300 transition-all">
          <div className="flex items-center justify-between pb-1.5 border-b border-slate-100">
            <span className="text-xs font-bold text-slate-700 uppercase tracking-wider truncate">Kehadiran Semester</span>
            <div className="w-7 h-7 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200 flex items-center justify-center shrink-0">
              <CalendarCheck className="w-3.5 h-3.5" />
            </div>
          </div>
          <div className="my-1.5">
            <div className="flex items-baseline justify-between">
              <span className="text-3xl font-extrabold text-slate-900 tracking-tight">
                {activeChild.attendance_recap.rate}%
              </span>
              <span className="text-xs font-bold px-2 py-0.5 rounded-md bg-emerald-800 text-white shadow-2xs">
                Target 95%
              </span>
            </div>
            <p className="text-xs text-slate-500 font-medium mt-0.5">
              {activeChild.attendance_recap.hadir} Hadir • {activeChild.attendance_recap.sakit} Sakit • {activeChild.attendance_recap.izin} Izin • {activeChild.attendance_recap.alpa} Alpa
            </p>
          </div>
          <div className="pt-2 border-t border-slate-100">
            <Link
              href="/ortu/presensi"
              className="text-xs font-bold text-slate-700 hover:text-emerald-800 flex items-center justify-between group transition-colors"
            >
              <span>Buka Presensi & Izin</span>
              <ArrowRight className="w-3.5 h-3.5 text-slate-400 group-hover:text-emerald-800 group-hover:translate-x-0.5 transition-all" />
            </Link>
          </div>
        </div>

        {/* Metric 2: Capaian Nilai & KKTP */}
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
                  {activeChild.grade_average}
                </span>
                <span className="text-xs font-semibold text-slate-500 font-mono">/ 100</span>
              </div>
              <span className={`text-xs font-bold px-2 py-0.5 rounded-md shadow-2xs ${
                remedialSubjects > 0 ? 'bg-amber-600 text-white' : 'bg-blue-800 text-white'
              }`}>
                {remedialSubjects > 0 ? `${remedialSubjects} Remedial` : 'Semua Tuntas'}
              </span>
            </div>
            <p className="text-xs text-slate-500 font-medium mt-0.5">
              Batas KKTP 75.0 • {tuntasSubjects} dari {totalSubjects} Mapel Tuntas
            </p>
          </div>
          <div className="pt-2 border-t border-slate-100">
            <Link
              href="/ortu/nilai"
              className="text-xs font-bold text-slate-700 hover:text-blue-800 flex items-center justify-between group transition-colors"
            >
              <span>Rincian Nilai & E-Rapor</span>
              <ArrowRight className="w-3.5 h-3.5 text-slate-400 group-hover:text-blue-800 group-hover:translate-x-0.5 transition-all" />
            </Link>
          </div>
        </div>

        {/* Metric 3: Buku Poin Karakter */}
        <div className="bg-white border border-slate-200/90 rounded-xl p-3.5 shadow-xs flex flex-col justify-between hover:border-slate-300 transition-all">
          <div className="flex items-center justify-between pb-1.5 border-b border-slate-100">
            <span className="text-xs font-bold text-slate-700 uppercase tracking-wider truncate">Buku Poin Karakter</span>
            <div className="w-7 h-7 rounded-lg bg-amber-50 text-amber-800 border border-amber-200 flex items-center justify-center shrink-0">
              <Award className="w-3.5 h-3.5" />
            </div>
          </div>
          <div className="my-1.5">
            <div className="flex items-baseline justify-between">
              <span className={`text-3xl font-extrabold tracking-tight ${isPositivePoints ? 'text-slate-900' : 'text-rose-700'}`}>
                {isPositivePoints ? `+${activeChild.points_balance}` : activeChild.points_balance}
              </span>
              <span className={`text-xs font-bold px-2 py-0.5 rounded-md shadow-2xs ${
                isPositivePoints ? 'bg-emerald-800 text-white' : 'bg-rose-700 text-white'
              }`}>
                {isPositivePoints ? 'Disiplin Baik' : 'Catatan Tatib'}
              </span>
            </div>
            <p className="text-xs text-slate-500 font-medium mt-0.5">
              Prestasi +{activeChild.points_prestasi} • Pelanggaran {activeChild.points_pelanggaran} Poin
            </p>
          </div>
          <div className="pt-2 border-t border-slate-100">
            <Link
              href="/ortu/poin"
              className="text-xs font-bold text-slate-700 hover:text-amber-800 flex items-center justify-between group transition-colors"
            >
              <span>Catatan Prestasi & Tatib</span>
              <ArrowRight className="w-3.5 h-3.5 text-slate-400 group-hover:text-amber-800 group-hover:translate-x-0.5 transition-all" />
            </Link>
          </div>
        </div>

        {/* Metric 4: Administrasi SPP & Biaya */}
        <div className="bg-white border border-slate-200/90 rounded-xl p-3.5 shadow-xs flex flex-col justify-between hover:border-slate-300 transition-all">
          <div className="flex items-center justify-between pb-1.5 border-b border-slate-100">
            <span className="text-xs font-bold text-slate-700 uppercase tracking-wider truncate">Administrasi Sekolah</span>
            <div className="w-7 h-7 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200 flex items-center justify-center shrink-0">
              <Receipt className="w-3.5 h-3.5" />
            </div>
          </div>
          <div className="my-1.5">
            <div className="flex items-baseline justify-between">
              <span className="text-2xl font-extrabold text-slate-900 tracking-tight truncate">
                {activeChild.fee_summary.amount}
              </span>
              <span className={`text-xs font-bold px-2 py-0.5 rounded-md shadow-2xs ${
                activeChild.fee_summary.status === 'Lunas'
                  ? 'bg-emerald-800 text-white'
                  : activeChild.fee_summary.status === 'Verifikasi TU'
                  ? 'bg-blue-800 text-white'
                  : 'bg-amber-600 text-white'
              }`}>
                {activeChild.fee_summary.status}
              </span>
            </div>
            <p className="text-xs text-slate-500 font-medium mt-0.5 truncate">
              {activeChild.fee_summary.title} • {activeChild.fee_summary.receipt_or_due}
            </p>
          </div>
          <div className="pt-2 border-t border-slate-100">
            <Link
              href="/ortu/biaya"
              className="text-xs font-bold text-slate-700 hover:text-emerald-800 flex items-center justify-between group transition-colors"
            >
              <span>Riwayat Pembayaran TU</span>
              <ArrowRight className="w-3.5 h-3.5 text-slate-400 group-hover:text-emerald-800 group-hover:translate-x-0.5 transition-all" />
            </Link>
          </div>
        </div>
      </div>

      {/* ─── 3. DUA KOLOM UTAMA: ALUR BELAJAR vs KOMUNIKASI & AGENDA (VIEWPORT OPTIMIZED) ─── */}
      <div className="xl:flex-1 xl:min-h-0 grid grid-cols-1 lg:grid-cols-12 gap-3.5">
        {/* Kolom Kiri: Alur Pembelajaran Hari Ini & Dokumen Rapor (7 Col) */}
        <div className="lg:col-span-7 flex flex-col gap-3 h-full overflow-hidden">
          {/* Timeline Jadwal Hari Ini */}
          <div className="flex-1 min-h-0 bg-white border border-slate-200/90 rounded-xl p-3.5 shadow-xs flex flex-col overflow-hidden">
            <div className="shrink-0 flex items-center justify-between pb-3 border-b border-slate-100">
              <div className="flex items-center gap-2.5">
                <div className="w-8 h-8 rounded-lg bg-slate-100 text-slate-800 flex items-center justify-center border border-slate-200 shrink-0">
                  <Clock className="w-4 h-4 text-slate-800" />
                </div>
                <div>
                  <h2 className="text-sm font-bold text-slate-900 tracking-tight">
                    Alur Pembelajaran Hari Ini
                  </h2>
                  <p className="text-xs text-slate-500 font-normal">
                    {new Date().toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })} • {activeChild.curriculum_phase}
                  </p>
                </div>
              </div>

              <span className="px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-800 text-white shadow-2xs">
                {activeSchedule.length} Sesi KBM
              </span>
            </div>

            {/* Timeline Stream with internal scroll */}
            <div className="flex-1 min-h-0 overflow-y-auto space-y-2.5 pr-1 mt-3">
              {activeSchedule.map((item) => {
                const isCurrent = item.status === 'current';
                const isCompleted = item.status === 'completed';

                return (
                  <div 
                    key={item.id} 
                    className={`p-3 rounded-xl border transition-all ${
                      isCurrent
                        ? 'border-emerald-300 bg-emerald-50/40 border-l-4 border-l-emerald-800 shadow-xs'
                        : isCompleted
                        ? 'border-slate-200/80 bg-slate-50/50 hover:bg-slate-50'
                        : 'border-slate-200/80 bg-white hover:bg-slate-50/60'
                    }`}
                  >
                    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-1.5">
                      <div className="flex items-center gap-2">
                        <span className="font-mono text-xs font-bold text-slate-900">
                          {item.timeRange}
                        </span>
                        <span
                          className={`inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-bold ${
                            isCompleted
                              ? 'bg-slate-200 text-slate-700'
                              : isCurrent
                              ? 'bg-emerald-800 text-white shadow-2xs'
                              : 'bg-slate-100 text-slate-700 border border-slate-300'
                          }`}
                        >
                          {item.statusLabel}
                        </span>
                      </div>
                      <span className="text-xs text-slate-600 font-semibold bg-slate-100 px-2 py-0.5 rounded-md border border-slate-200 w-fit">
                        {item.room}
                      </span>
                    </div>

                    <div className="mt-2">
                      <h4 className="text-sm font-bold text-slate-900">
                        {item.subject}
                      </h4>
                      <p className="text-xs text-slate-600 mt-0.5 font-medium">
                        Materi: <span className="font-semibold text-slate-800">{item.topic}</span>
                      </p>
                      <p className="text-xs text-slate-500 font-normal mt-0.5">
                        Pengampu: <span className="font-medium text-slate-700">{item.teacher}</span>
                      </p>
                    </div>
                  </div>
                );
              })}
            </div>
          </div>

          {/* Quick E-Progress Report Banner */}
          <div className="shrink-0 bg-white border border-blue-200 rounded-xl p-3 px-4 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-l-4 border-l-blue-900">
            <div className="flex items-center gap-3">
              <div className="w-10 h-10 rounded-xl bg-blue-900 text-white flex items-center justify-center shrink-0 shadow-xs">
                <FileCheck className="w-5 h-5 text-white" />
              </div>
              <div className="min-w-0">
                <div className="flex items-center gap-2">
                  <span className="px-2 py-0.5 rounded-md bg-blue-900 text-white text-[11px] font-bold shadow-2xs">
                    Dokumen Resmi
                  </span>
                  <span className="text-xs font-semibold text-slate-500 font-mono">
                    STS Gasal 2026/2027
                  </span>
                </div>
                <h3 className="text-xs sm:text-sm font-bold text-slate-900 mt-0.5 truncate">
                  E-Progress Report Sumatif Tengah Semester Telah Terbit
                </h3>
                <p className="text-xs text-slate-500 hidden sm:block">
                  Telah disahkan Kepala Sekolah & Walas dengan validasi QR Code resmi.
                </p>
              </div>
            </div>

            <Link
              href="/ortu/nilai"
              className="px-3.5 py-2 rounded-xl bg-blue-900 hover:bg-blue-950 text-white text-xs font-bold shrink-0 transition-colors flex items-center justify-center gap-1.5 shadow-xs self-start sm:self-auto"
            >
              <span>Buka E-Rapor</span>
              <ArrowRight className="w-3.5 h-3.5 text-white" />
            </Link>
          </div>
        </div>

        {/* Kolom Kanan: Kontak Walas & Pengumuman Agenda (5 Col) */}
        <div className="lg:col-span-5 flex flex-col gap-3 h-full overflow-hidden">
          {/* 1. Homeroom Teacher Direct Connect Card */}
          <div className="shrink-0 bg-white border border-slate-200/90 rounded-xl p-3.5 shadow-xs space-y-2.5">
            <div className="flex items-center justify-between pb-2 border-b border-slate-100">
              <div className="flex items-center gap-2">
                <div className="w-7 h-7 rounded-lg bg-slate-100 text-slate-800 flex items-center justify-center shrink-0 border border-slate-200">
                  <School className="w-3.5 h-3.5" />
                </div>
                <span className="text-xs font-bold text-slate-800">Wali Kelas Ananda</span>
              </div>
              <span className="px-2.5 py-0.5 rounded-md text-xs font-bold bg-slate-800 text-white font-mono shadow-2xs">
                {activeChild.class_name}
              </span>
            </div>

            <div className="flex items-center gap-3">
              {activeChild.homeroom_avatar ? (
                <img
                  src={activeChild.homeroom_avatar}
                  alt={activeChild.homeroom_teacher}
                  className="w-11 h-11 rounded-xl object-cover shadow-xs border border-slate-200 shrink-0"
                />
              ) : (
                <div className="w-11 h-11 rounded-xl bg-emerald-800 text-white font-bold text-sm flex items-center justify-center shrink-0 shadow-xs">
                  {activeChild.homeroom_teacher.split(' ').map(w => w[0]).slice(0, 2).join('')}
                </div>
              )}
              <div className="min-w-0 flex-1">
                <h4 className="text-sm font-bold text-slate-900 truncate">
                  {activeChild.homeroom_teacher}
                </h4>
                <p className="text-xs text-slate-500 font-mono font-medium truncate">
                  {activeChild.homeroom_nip}
                </p>
                <p className="text-xs text-emerald-800 font-semibold truncate">
                  Konsultasi Belajar & Presensi Siswa
                </p>
              </div>
            </div>

            <div className="grid grid-cols-2 gap-2 pt-0.5">
              <a
                href={`https://wa.me/${activeChild.homeroom_phone.replace(/[^0-9]/g, '')}`}
                target="_blank"
                rel="noreferrer"
                className="px-3 py-2 rounded-xl bg-emerald-800 hover:bg-emerald-900 text-white text-xs font-bold text-center transition-colors flex items-center justify-center gap-1.5 shadow-xs"
              >
                <MessageCircle className="w-3.5 h-3.5 text-white" />
                <span>WhatsApp</span>
              </a>

              <Link
                href="/ortu/konseling"
                className="px-3 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold text-center transition-colors flex items-center justify-center gap-1.5 shadow-xs"
              >
                <HeartHandshake className="w-3.5 h-3.5 text-white" />
                <span>Janji Temu</span>
              </Link>
            </div>
          </div>

          {/* 2. Pengumuman & Agenda Penting (Scrollable List with Distinct Border Accents) */}
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
              {isAnnLoading ? (
                <div className="space-y-2 animate-pulse">
                  {[1, 2, 3].map((i) => (
                    <div key={i} className="p-3 rounded-xl border border-slate-200 bg-slate-50 space-y-2">
                      <div className="h-3 w-20 bg-slate-200 rounded" />
                      <div className="h-4 w-3/4 bg-slate-200 rounded" />
                      <div className="h-3 w-full bg-slate-100 rounded" />
                    </div>
                  ))}
                </div>
              ) : !announcementsData || announcementsData.length === 0 ? (
                <div className="p-6 text-center border border-dashed border-slate-200 rounded-xl bg-slate-50/50 my-2">
                  <div className="w-8 h-8 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-2">
                    <Info className="w-4 h-4" />
                  </div>
                  <p className="text-xs font-semibold text-slate-700">Belum Ada Pengumuman Baru</p>
                  <p className="text-[11px] text-slate-500 mt-0.5">Seluruh agenda resmi sekolah telah tersampaikan.</p>
                </div>
              ) : (
                announcementsData.map((ann) => {
                  const dateStr = ann.publish_at || ann.published_at || ann.created_at
                    ? new Date(ann.publish_at || ann.published_at || ann.created_at || '').toLocaleDateString('id-ID', {
                        day: 'numeric',
                        month: 'short',
                        year: 'numeric',
                      })
                    : 'Terbaru';

                  const priority = ann.priority || 'info';
                  const accentBorder = 
                    priority === 'urgent' || priority === 'high'
                      ? 'border-l-rose-700'
                      : priority === 'medium'
                      ? 'border-l-amber-600'
                      : 'border-l-emerald-800';

                  const badgeBg = 
                    priority === 'urgent' || priority === 'high'
                      ? 'bg-rose-700 text-white'
                      : priority === 'medium'
                      ? 'bg-amber-600 text-white'
                      : 'bg-emerald-800 text-white';

                  return (
                    <div 
                      key={ann.id} 
                      className={`p-3 rounded-xl border border-slate-200/90 bg-white hover:bg-slate-50 transition-colors border-l-4 ${accentBorder} shadow-2xs space-y-1.5`}
                    >
                      <div className="flex items-center justify-between">
                        <span className="text-xs font-semibold text-slate-500 font-mono">
                          {dateStr}
                        </span>
                        <span className={`px-2 py-0.5 rounded-md text-[11px] font-bold shadow-2xs capitalize ${badgeBg}`}>
                          {priority}
                        </span>
                      </div>
                      <h5 className="text-xs font-bold text-slate-900 leading-snug">
                        {ann.title}
                      </h5>
                      <p className="text-xs text-slate-600 leading-relaxed font-normal line-clamp-3">
                        {ann.body || ann.content}
                      </p>
                    </div>
                  );
                })
              )}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
