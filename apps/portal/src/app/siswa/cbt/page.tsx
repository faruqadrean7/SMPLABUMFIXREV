'use client';

import React, { useState } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { 
  MonitorPlay, 
  KeyRound, 
  Clock, 
  MapPin, 
  Calendar, 
  CheckCircle2, 
  AlertTriangle, 
  ShieldAlert, 
  ArrowRight, 
  ShieldCheck, 
  X, 
  FileSpreadsheet, 
  School, 
  LifeBuoy, 
  HelpCircle,
  Inbox
} from 'lucide-react';
import { StudentCBTExamItem } from '@/lib/mock-student-data';
import { useStudentExams, useStudentDashboard } from '@/lib/use-student-data';
import { ExamListSkeleton } from '@/components/ui/student-skeletons';
import { EmptyState } from '@/components/ui/empty-state';
import { ErrorState } from '@/components/ui/error-state';

export default function StudentCBTListPage() {
  const router = useRouter();
  const [activeTab, setActiveTab] = useState<'aktif' | 'terjadwal' | 'riwayat'>('aktif');
  const [tokenInput, setTokenInput] = useState('');
  const [selectedExamId, setSelectedExamId] = useState<string | null>(null);
  const [tokenError, setTokenError] = useState<string | null>(null);

  const { data: apiExams, isLoading, isError, refetch } = useStudentExams();
  const { data: studentDash } = useStudentDashboard();

  const allExams: StudentCBTExamItem[] = apiExams
    ? apiExams.map((e) => ({
        id: e.id,
        title: e.title,
        subject_name: e.subjectName,
        subject_code: 'CBT',
        teacher_name: 'Guru Pengampu CBT',
        date_str: e.startTime ? e.startTime.split('T')[0] : 'Hari Ini',
        time_range: e.startTime ? `${e.startTime.slice(11, 16)} - ${e.endTime?.slice(11, 16) || ''}` : '07:30 - 09:00',
        duration_minutes: e.durationMinutes || 60,
        total_questions: e.totalQuestions || 40,
        room_name: e.roomName || 'Lab Komputer 1',
        kktp: e.kktp || 75,
        status: (e.status as any) || 'aktif',
        token_required: e.tokenRequired || 'LAB-CBT',
        score: e.score,
        is_passed: e.score !== undefined ? e.score >= (e.kktp || 75) : undefined,
      }))
    : [];

  // Filter exams by status
  const activeExams = allExams.filter(e => e.status === 'aktif');
  const upcomingExams = allExams.filter(e => e.status === 'terjadwal');
  const pastExams = allExams.filter(e => e.status === 'selesai');

  // Dynamic calculations derived from actual past exams
  const pastExamsWithScores = pastExams.filter(e => typeof e.score === 'number');
  const totalScore = pastExamsWithScores.reduce((acc, curr) => acc + (curr.score || 0), 0);
  const averageScore = pastExamsWithScores.length > 0 ? (totalScore / pastExamsWithScores.length).toFixed(1) : null;
  const highestScore = pastExamsWithScores.length > 0 ? Math.max(...pastExamsWithScores.map(e => e.score || 0)) : null;

  const passedCount = pastExams.filter(e => e.is_passed || (typeof e.score === 'number' && e.score >= e.kktp)).length;
  const passRate = pastExams.length > 0 ? Math.round((passedCount / pastExams.length) * 100) : null;

  const activeExamToday = activeExams[0] || null;
  const selectedExam = allExams.find(ex => ex.id === selectedExamId) || null;

  const handleStartExam = (examId: string) => {
    setSelectedExamId(examId);
    setTokenInput('');
    setTokenError(null);
  };

  const handleVerifyTokenAndProceed = (e: React.FormEvent) => {
    e.preventDefault();
    const exam = allExams.find(ex => ex.id === selectedExamId);
    if (!exam) return;

    if (tokenInput.trim().toUpperCase() !== exam.token_required?.toUpperCase()) {
      setTokenError(`Token tidak valid! Pastikan Anda memasukkan token yang tertulis di papan pengawas lab (contoh: ${exam.token_required})`);
      return;
    }

    // Masuk ke exam workspace
    router.push(`/siswa/cbt/${exam.id}`);
  };

  // ─── DYNAMIC SUMMARY CARDS (HANYA MUNCUL JIKA DATANYA ADA) ───
  const summaryCards: React.ReactNode[] = [];

  // Card 1: Sesi Ujian Aktif Hari Ini (Hanya muncul bila ada ujian aktif)
  if (activeExams.length > 0 && activeExamToday) {
    summaryCards.push(
      <div key="active-exam" className="bg-white border border-slate-200/90 rounded-xl p-3.5 shadow-xs flex flex-col justify-between hover:border-slate-300 transition-all">
        <div className="flex items-center justify-between pb-1.5 border-b border-slate-100">
          <span className="text-xs font-bold text-slate-700 uppercase tracking-wider truncate">Sesi Ujian Hari Ini</span>
          <div className="w-7 h-7 rounded-lg bg-rose-50 text-rose-800 border border-rose-200 flex items-center justify-center shrink-0">
            <MonitorPlay className="w-3.5 h-3.5" />
          </div>
        </div>
        <div className="my-1.5">
          <div className="flex items-baseline justify-between">
            <span className="text-3xl font-extrabold text-slate-900 tracking-tight">
              {activeExams.length} Sesi
            </span>
            <span className="text-xs font-bold px-2 py-0.5 rounded-md bg-rose-700 text-white shadow-2xs">
              Wajib Masuk
            </span>
          </div>
          <p className="text-xs text-slate-500 font-medium mt-0.5 truncate">
            {activeExamToday.subject_name} • {activeExamToday.time_range}
          </p>
        </div>
        <div className="pt-2 border-t border-slate-100">
          <button
            onClick={() => setActiveTab('aktif')}
            className="w-full text-xs font-bold text-slate-700 hover:text-rose-700 flex items-center justify-between group transition-colors cursor-pointer text-left"
          >
            <span>Token Ruang Pengawas</span>
            <ArrowRight className="w-3.5 h-3.5 text-slate-400 group-hover:text-rose-700 group-hover:translate-x-0.5 transition-all" />
          </button>
        </div>
      </div>
    );
  }

  // Card 2: Jadwal Ujian Mendatang (Hanya muncul bila ada jadwal mendatang)
  if (upcomingExams.length > 0) {
    const nextExam = upcomingExams[0];
    summaryCards.push(
      <div key="upcoming-exam" className="bg-white border border-slate-200/90 rounded-xl p-3.5 shadow-xs flex flex-col justify-between hover:border-slate-300 transition-all">
        <div className="flex items-center justify-between pb-1.5 border-b border-slate-100">
          <span className="text-xs font-bold text-slate-700 uppercase tracking-wider truncate">Ujian Mendatang</span>
          <div className="w-7 h-7 rounded-lg bg-blue-50 text-blue-900 border border-blue-200 flex items-center justify-center shrink-0">
            <Calendar className="w-3.5 h-3.5" />
          </div>
        </div>
        <div className="my-1.5">
          <div className="flex items-baseline justify-between">
            <span className="text-3xl font-extrabold text-slate-900 tracking-tight">
              {upcomingExams.length} Sesi
            </span>
            <span className="text-xs font-bold px-2 py-0.5 rounded-md bg-blue-900 text-white shadow-2xs">
              Terjadwal
            </span>
          </div>
          <p className="text-xs text-slate-500 font-medium mt-0.5 truncate">
            {nextExam.subject_name} • {nextExam.date_str}
          </p>
        </div>
        <div className="pt-2 border-t border-slate-100">
          <button
            onClick={() => setActiveTab('terjadwal')}
            className="w-full text-xs font-bold text-slate-700 hover:text-blue-900 flex items-center justify-between group transition-colors cursor-pointer text-left"
          >
            <span>Lihat Jadwal Terinci</span>
            <ArrowRight className="w-3.5 h-3.5 text-slate-400 group-hover:text-blue-900 group-hover:translate-x-0.5 transition-all" />
          </button>
        </div>
      </div>
    );
  }

  // Card 3: Ketuntasan Asesmen CBT (Hanya muncul bila sudah ada riwayat ujian yang tuntas dinilai)
  if (pastExams.length > 0 && passRate !== null) {
    summaryCards.push(
      <div key="pass-rate" className="bg-white border border-slate-200/90 rounded-xl p-3.5 shadow-xs flex flex-col justify-between hover:border-slate-300 transition-all">
        <div className="flex items-center justify-between pb-1.5 border-b border-slate-100">
          <span className="text-xs font-bold text-slate-700 uppercase tracking-wider truncate">Ketuntasan Asesmen CBT</span>
          <div className="w-7 h-7 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200 flex items-center justify-center shrink-0">
            <CheckCircle2 className="w-3.5 h-3.5" />
          </div>
        </div>
        <div className="my-1.5">
          <div className="flex items-baseline justify-between">
            <span className="text-3xl font-extrabold text-slate-900 tracking-tight">
              {passRate}%
            </span>
            <span className="text-xs font-bold px-2 py-0.5 rounded-md bg-emerald-800 text-white shadow-2xs">
              {passedCount} / {pastExams.length} Tuntas
            </span>
          </div>
          <p className="text-xs text-slate-500 font-medium mt-0.5 truncate">
            Batas KKTP 75.0 • {pastExams.length} Ujian Selesai
          </p>
        </div>
        <div className="pt-2 border-t border-slate-100">
          <button
            onClick={() => setActiveTab('riwayat')}
            className="w-full text-xs font-bold text-slate-700 hover:text-emerald-800 flex items-center justify-between group transition-colors cursor-pointer text-left"
          >
            <span>Rekap Nilai Riwayat</span>
            <ArrowRight className="w-3.5 h-3.5 text-slate-400 group-hover:text-emerald-800 group-hover:translate-x-0.5 transition-all" />
          </button>
        </div>
      </div>
    );
  }

  // Card 4: Rerata Skor Siswa (Hanya muncul bila data skor asesmen lampau tersedia)
  if (pastExamsWithScores.length > 0 && averageScore !== null) {
    summaryCards.push(
      <div key="average-score" className="bg-white border border-slate-200/90 rounded-xl p-3.5 shadow-xs flex flex-col justify-between hover:border-slate-300 transition-all">
        <div className="flex items-center justify-between pb-1.5 border-b border-slate-100">
          <span className="text-xs font-bold text-slate-700 uppercase tracking-wider truncate">Rerata Skor Siswa</span>
          <div className="w-7 h-7 rounded-lg bg-blue-50 text-blue-900 border border-blue-200 flex items-center justify-center shrink-0">
            <FileSpreadsheet className="w-3.5 h-3.5" />
          </div>
        </div>
        <div className="my-1.5">
          <div className="flex items-baseline justify-between">
            <div className="flex items-baseline gap-1">
              <span className="text-3xl font-extrabold text-slate-900 tracking-tight">
                {averageScore}
              </span>
              <span className="text-xs font-semibold text-slate-500 font-mono">/ 100</span>
            </div>
            {highestScore !== null && (
              <span className="text-xs font-bold px-2 py-0.5 rounded-md bg-blue-900 text-white shadow-2xs">
                Tertinggi {highestScore}
              </span>
            )}
          </div>
          <p className="text-xs text-slate-500 font-medium mt-0.5 truncate">
            Dari {pastExamsWithScores.length} Asesmen Selesai
          </p>
        </div>
        <div className="pt-2 border-t border-slate-100">
          <Link
            href="/siswa/nilai"
            className="text-xs font-bold text-slate-700 hover:text-blue-900 flex items-center justify-between group transition-colors"
          >
            <span>Ledger Nilai Rapor</span>
            <ArrowRight className="w-3.5 h-3.5 text-slate-400 group-hover:text-blue-900 group-hover:translate-x-0.5 transition-all" />
          </Link>
        </div>
      </div>
    );
  }

  return (
    <div className="flex flex-col gap-3.5 max-w-full xl:h-[calc(100vh-112px)] xl:overflow-hidden text-slate-900 font-sans">
      {/* ─── 1. INTEGRATED CBT HERO BANNER (DATA-DRIVEN) ─── */}
      <div className="bg-white border border-slate-200/90 rounded-xl p-3 px-4 shadow-xs shrink-0">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          {/* Left: Identity & Dynamic Status */}
          <div className="flex items-center gap-3.5 min-w-0">
            <div className="w-12 h-12 rounded-xl bg-blue-900 text-white font-extrabold flex items-center justify-center text-base shadow-xs shrink-0">
              <MonitorPlay className="w-6 h-6 text-white" />
            </div>
            <div className="min-w-0">
              <div className="flex flex-wrap items-center gap-2">
                <h1 className="text-lg font-extrabold text-slate-900 tracking-tight truncate">
                  Ruang Asesmen & Ujian CBT Siswa
                </h1>
                <span className="px-2 py-0.5 rounded-lg text-xs font-bold bg-blue-900 text-white shadow-2xs font-mono">
                  CBT Web Engine
                </span>
                {activeExamToday ? (
                  <>
                    <span className="px-2 py-0.5 rounded-lg text-xs font-semibold bg-slate-100 border border-slate-300 text-slate-700 font-mono">
                      {activeExamToday.room_name}
                    </span>
                    <span className="px-2.5 py-0.5 rounded-lg text-xs font-bold shadow-2xs bg-emerald-800 text-white">
                      Workstation Online
                    </span>
                  </>
                ) : (
                  <span className="px-2.5 py-0.5 rounded-lg text-xs font-bold shadow-2xs bg-slate-700 text-white">
                    Standby (Tidak Ada Sesi Aktif)
                  </span>
                )}
              </div>

              {/* Status Row */}
              <div className="mt-1 flex flex-wrap items-center gap-x-3 text-xs text-slate-600">
                {activeExamToday ? (
                  <div className="inline-flex items-center gap-1 font-semibold text-emerald-800">
                    <CheckCircle2 className="w-3.5 h-3.5 shrink-0" />
                    <span>Sesi Hari Ini: {activeExamToday.title} ({activeExamToday.time_range})</span>
                  </div>
                ) : (
                  <div className="inline-flex items-center gap-1 font-semibold text-slate-600">
                    <Clock className="w-3.5 h-3.5 text-slate-400 shrink-0" />
                    <span>
                      Jadwal Terdekat: {upcomingExams[0]?.title || 'Belum ada agenda ujian baru'} 
                      {upcomingExams[0]?.date_str && ` (${upcomingExams[0].date_str})`}
                    </span>
                  </div>
                )}
                <span className="text-slate-300 hidden sm:inline">•</span>
                <div>
                  <span>Peserta: </span>
                  <strong className="text-slate-900 font-semibold">{studentDash?.name || 'Siswa Terdaftar'} ({studentDash?.className || 'Kelas Siswa'})</strong>
                </div>
              </div>
            </div>
          </div>

          {/* Right: Tab Switcher (Matching Ortu Child Switcher Style) */}
          <div className="flex items-center bg-slate-100 p-1 rounded-xl border border-slate-200 self-start sm:self-auto shadow-2xs shrink-0">
            <button
              onClick={() => setActiveTab('aktif')}
              className={`px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer ${
                activeTab === 'aktif'
                  ? 'bg-white text-slate-900 shadow-xs border border-slate-200'
                  : 'text-slate-600 hover:text-slate-900 hover:bg-slate-200/50'
              }`}
            >
              Ujian Hari Ini ({activeExams.length})
            </button>
            <button
              onClick={() => setActiveTab('terjadwal')}
              className={`px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer ${
                activeTab === 'terjadwal'
                  ? 'bg-white text-slate-900 shadow-xs border border-slate-200'
                  : 'text-slate-600 hover:text-slate-900 hover:bg-slate-200/50'
              }`}
            >
              Mendatang ({upcomingExams.length})
            </button>
            <button
              onClick={() => setActiveTab('riwayat')}
              className={`px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer ${
                activeTab === 'riwayat'
                  ? 'bg-white text-slate-900 shadow-xs border border-slate-200'
                  : 'text-slate-600 hover:text-slate-900 hover:bg-slate-200/50'
              }`}
            >
              Riwayat Nilai ({pastExams.length})
            </button>
          </div>
        </div>
      </div>

      {/* ─── 2. REIMAGINED VISUAL METRICS (HANYA DITAMPILKAN JIKA DATANYA ADA) ─── */}
      {summaryCards.length > 0 && (
        <div className={`grid grid-cols-1 sm:grid-cols-2 ${
          summaryCards.length === 1 ? 'xl:grid-cols-1 max-w-sm' :
          summaryCards.length === 2 ? 'xl:grid-cols-2' :
          summaryCards.length === 3 ? 'xl:grid-cols-3' : 'xl:grid-cols-4'
        } gap-3 shrink-0`}>
          {summaryCards}
        </div>
      )}

      {/* ─── 3. DUA KOLOM UTAMA: DAFTAR UJIAN vs PROTOKOL & BANTUAN PENGAWAS ─── */}
      <div className="xl:flex-1 xl:min-h-0 grid grid-cols-1 lg:grid-cols-12 gap-3.5">
        {/* KOLOM KIRI (7 Kolom): Daftar Sesi Ujian Sesuai Tab (Internal Scroll) */}
        <div className="lg:col-span-7 flex flex-col gap-3 h-full overflow-hidden">
          <div className="flex-1 min-h-0 bg-white border border-slate-200/90 rounded-xl p-3.5 shadow-xs flex flex-col overflow-hidden">
            {/* Header Kolom */}
            <div className="shrink-0 flex items-center justify-between pb-2.5 border-b border-slate-100">
              <div className="flex items-center gap-2.5">
                <div className="w-8 h-8 rounded-lg bg-slate-100 text-slate-800 flex items-center justify-center border border-slate-200 shrink-0">
                  <MonitorPlay className="w-4 h-4 text-slate-800" />
                </div>
                <div>
                  <h2 className="text-sm font-bold text-slate-900 tracking-tight">
                    {activeTab === 'aktif' && 'Daftar Sesi Ujian Aktif Hari Ini'}
                    {activeTab === 'terjadwal' && 'Jadwal Ujian CBT Mendatang'}
                    {activeTab === 'riwayat' && 'Riwayat Hasil Ujian CBT'}
                  </h2>
                  <p className="text-xs text-slate-500 font-normal">
                    {activeTab === 'aktif' && 'Wajib memasukkan token pengawas untuk membuka naskah soal'}
                    {activeTab === 'terjadwal' && 'Jadwal asesmen sumatif yang telah dijadwalkan guru pengampu'}
                    {activeTab === 'riwayat' && 'Nilai asesmen yang telah dikoreksi dan tervalidasi'}
                  </p>
                </div>
              </div>

              <span className="px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-800 text-white shadow-2xs">
                {activeTab === 'aktif' && `${activeExams.length} Sesi Aktif`}
                {activeTab === 'terjadwal' && `${upcomingExams.length} Terjadwal`}
                {activeTab === 'riwayat' && `${pastExams.length} Selesai`}
              </span>
            </div>

            {/* List Stream with internal scroll */}
            <div className="flex-1 min-h-0 overflow-y-auto space-y-2.5 pr-1 mt-2.5">
              {isLoading && <ExamListSkeleton count={2} />}

              {isError && (
                <ErrorState
                  title="Gagal Memuat Jadwal Ujian CBT"
                  message="Terjadi kendala saat mengambil data sesi ujian dari server. Silakan coba kembali."
                  onRetry={() => refetch()}
                />
              )}

              {/* TAB 1: UJIAN AKTIF */}
              {!isLoading && !isError && activeTab === 'aktif' && (
                <>
                  {activeExams.length === 0 ? (
                    <EmptyState
                      icon={MonitorPlay}
                      title="Tidak Ada Sesi Ujian Aktif Saat Ini"
                      description="Seluruh jadwal ujian Anda hari ini telah selesai atau belum dimulai oleh pengawas lab."
                    />
                  ) : (
                    activeExams.map((exam) => (
                      <div
                        key={exam.id}
                        className="p-3.5 rounded-xl border border-blue-900/30 bg-blue-50/15 hover:border-blue-900/50 transition-all shadow-xs flex flex-col justify-between gap-3"
                      >
                        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-2.5 border-b border-slate-100">
                          <div>
                            <div className="flex items-center gap-2">
                              <span className="px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider bg-rose-700 text-white shadow-2xs">
                                Sesi Berlangsung
                              </span>
                              <span className="text-xs font-bold text-slate-700 font-mono">
                                {exam.subject_code}
                              </span>
                              <span className="text-xs text-slate-400 font-light">•</span>
                              <span className="text-xs text-slate-600 font-semibold">
                                {exam.subject_name}
                              </span>
                            </div>
                            <h3 className="font-extrabold text-base text-slate-900 mt-1">
                              {exam.title}
                            </h3>
                            <p className="text-xs text-slate-500 font-normal mt-0.5">
                              Guru Pengampu: <strong className="text-slate-800 font-medium">{exam.teacher_name}</strong>
                            </p>
                          </div>

                          <span className="px-2.5 py-1 rounded-lg text-xs font-mono font-bold bg-white text-slate-800 border border-slate-200 self-start sm:self-auto shadow-2xs">
                            KKTP: {exam.kktp}
                          </span>
                        </div>

                        {/* 4 Detail Grid Boxes */}
                        <div className="grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs">
                          <div className="p-2.5 bg-white rounded-xl border border-slate-200 shadow-2xs">
                            <span className="text-slate-400 font-medium text-[10px] block">Waktu Sesi</span>
                            <span className="font-bold text-slate-900 mt-0.5 flex items-center gap-1">
                              <Clock className="w-3 h-3 text-blue-900" />
                              {exam.time_range}
                            </span>
                          </div>

                          <div className="p-2.5 bg-white rounded-xl border border-slate-200 shadow-2xs">
                            <span className="text-slate-400 font-medium text-[10px] block">Durasi Soal</span>
                            <span className="font-bold text-slate-900 mt-0.5 block">
                              {exam.duration_minutes} Menit ({exam.total_questions} Butir)
                            </span>
                          </div>

                          <div className="p-2.5 bg-white rounded-xl border border-slate-200 shadow-2xs">
                            <span className="text-slate-400 font-medium text-[10px] block">Lokasi Lab</span>
                            <span className="font-bold text-slate-900 mt-0.5 flex items-center gap-1">
                              <MapPin className="w-3 h-3 text-blue-900" />
                              {exam.room_name}
                            </span>
                          </div>

                          <div className="p-2.5 bg-white rounded-xl border border-slate-200 shadow-2xs">
                            <span className="text-slate-400 font-medium text-[10px] block">Token Pengawas</span>
                            <span className="font-mono font-black text-rose-700 mt-0.5 text-xs block">
                              {exam.token_required}
                            </span>
                          </div>
                        </div>

                        {/* Bottom Actions */}
                        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pt-1 border-t border-slate-100">
                          <p className="text-[11px] text-slate-500 font-normal">
                            *Siapkan workstation dalam mode layar penuh sebelum memulai.
                          </p>
                          <button
                            onClick={() => handleStartExam(exam.id)}
                            className="px-4 py-2 rounded-xl bg-blue-900 hover:bg-blue-950 text-white text-xs font-bold transition-all shadow-xs flex items-center justify-center gap-1.5 cursor-pointer shrink-0 self-start sm:self-auto"
                          >
                            <KeyRound className="w-3.5 h-3.5 text-white" />
                            <span>Masukkan Token & Mulai Ujian ➔</span>
                          </button>
                        </div>
                      </div>
                    ))
                  )}
                </>
              )}

              {/* TAB 2: JADWAL MENDATANG */}
              {!isLoading && !isError && activeTab === 'terjadwal' && (
                <>
                  {upcomingExams.length === 0 ? (
                    <EmptyState
                      icon={Calendar}
                      title="Tidak Ada Jadwal Ujian Mendatang"
                      description="Belum ada asesmen sumatif baru yang diagendakan guru pengampu pada periode ini."
                    />
                  ) : (
                    upcomingExams.map((exam) => (
                      <div
                        key={exam.id}
                        className="p-3.5 rounded-xl border border-slate-200/90 bg-white hover:bg-slate-50 transition-colors shadow-2xs space-y-2"
                      >
                        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                          <div>
                            <div className="flex items-center gap-2">
                              <span className="px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider bg-blue-50 text-blue-900 border border-blue-200">
                                Terjadwal
                              </span>
                              <span className="text-xs font-semibold text-slate-600">
                                {exam.subject_name} ({exam.subject_code})
                              </span>
                            </div>
                            <h4 className="font-bold text-sm text-slate-900 mt-1">
                              {exam.title}
                            </h4>
                            <p className="text-xs text-slate-500 font-normal mt-0.5">
                              Guru Pengampu: {exam.teacher_name}
                            </p>
                          </div>
                          <span className="px-2.5 py-1 rounded-lg text-xs font-mono font-bold bg-slate-100 text-slate-700 border border-slate-200 self-start sm:self-auto shadow-2xs">
                            {exam.date_str}
                          </span>
                        </div>

                        <div className="flex flex-wrap items-center gap-3 text-xs text-slate-600 pt-2 border-t border-slate-100">
                          <span className="flex items-center gap-1 font-medium">
                            <Clock className="w-3.5 h-3.5 text-slate-400" /> {exam.time_range} ({exam.duration_minutes} Menit)
                          </span>
                          <span className="text-slate-300">•</span>
                          <span className="flex items-center gap-1 font-medium">
                            <MapPin className="w-3.5 h-3.5 text-slate-400" /> {exam.room_name}
                          </span>
                          <span className="text-slate-300">•</span>
                          <span className="font-medium text-slate-700">
                            KKTP Target: {exam.kktp}
                          </span>
                        </div>
                      </div>
                    ))
                  )}
                </>
              )}

              {/* TAB 3: RIWAYAT NILAI */}
              {!isLoading && !isError && activeTab === 'riwayat' && (
                <>
                  {pastExams.length === 0 ? (
                    <EmptyState
                      icon={CheckCircle2}
                      title="Belum Ada Riwayat Hasil Ujian"
                      description="Hasil ujian CBT yang telah dikoreksi dan divalidasi akan tampil di tab ini."
                    />
                  ) : (
                    pastExams.map((exam) => (
                      <div
                        key={exam.id}
                        className="p-3.5 rounded-xl border border-slate-200/90 bg-white hover:bg-slate-50 transition-colors shadow-2xs flex flex-col sm:flex-row sm:items-center justify-between gap-3"
                      >
                        <div>
                          <div className="flex items-center gap-2">
                            <span className="px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider bg-emerald-800 text-white shadow-2xs">
                              Tuntas Selesai
                            </span>
                            <span className="text-xs text-slate-500 font-mono font-semibold">{exam.date_str}</span>
                            <span className="text-slate-300">•</span>
                            <span className="text-xs text-slate-600">{exam.room_name}</span>
                          </div>
                          <h4 className="font-bold text-sm text-slate-900 mt-1">
                            {exam.title}
                          </h4>
                          <p className="text-xs text-slate-500 mt-0.5">
                            Mapel: {exam.subject_name} ({exam.subject_code}) • Pengampu: {exam.teacher_name}
                          </p>
                        </div>

                        <div className="flex items-center gap-3 self-end sm:self-center shrink-0">
                          {typeof exam.score === 'number' && (
                            <div className="text-right">
                              <span className="text-[10px] text-slate-400 font-medium block">Skor Asesmen:</span>
                              <div className="flex items-baseline justify-end gap-1">
                                <span className="text-2xl font-extrabold text-slate-900 tracking-tight leading-none">{exam.score}</span>
                                <span className="text-xs font-semibold text-slate-400 font-mono">/ 100</span>
                              </div>
                            </div>
                          )}

                          <span className="px-2.5 py-1 rounded-lg text-xs font-bold bg-emerald-50 text-emerald-800 border border-emerald-200 shadow-2xs">
                            TUNTAS (KKTP: {exam.kktp})
                          </span>
                        </div>
                      </div>
                    ))
                  )}
                </>
              )}
            </div>
          </div>
        </div>

        {/* KOLOM KANAN (5 Kolom): Protokol Anti-Cheat & Bantuan Pengawas Lab */}
        <div className="lg:col-span-5 flex flex-col gap-3 h-full overflow-hidden">
          <div className="flex-1 min-h-0 overflow-y-auto space-y-3 pr-1">
            {/* Card 1: Tata Tertib & Protokol Integritas CBT (Matching Ortu Announcement Pattern) */}
            <div className="bg-white border border-slate-200/90 rounded-xl p-3.5 shadow-xs space-y-2">
              <div className="flex items-center justify-between pb-2 border-b border-slate-100">
                <div className="flex items-center gap-2">
                  <div className="w-7 h-7 rounded-lg bg-blue-50 text-blue-900 border border-blue-200 flex items-center justify-center shrink-0">
                    <ShieldAlert className="w-3.5 h-3.5" />
                  </div>
                  <div>
                    <h2 className="text-xs font-bold text-slate-800">
                      Protokol Integritas & Anti-Cheat
                    </h2>
                    <p className="text-[11px] text-slate-500 font-normal">
                      Standar Pengawasan Lab Komputer
                    </p>
                  </div>
                </div>
                <span className="px-2 py-0.5 rounded-md text-[10px] font-bold bg-blue-900 text-white shadow-2xs">
                  Resmi Sekolah
                </span>
              </div>

              <div className="space-y-2 text-xs">
                <div className="p-2.5 rounded-xl border border-slate-200 bg-slate-50/60 border-l-4 border-l-rose-700 space-y-1">
                  <div className="flex items-center justify-between">
                    <span className="font-bold text-slate-900">1. Larangan Berpindah Tab / Jendela</span>
                    <span className="text-[10px] font-bold text-rose-700 bg-rose-50 px-1.5 py-0.2 rounded border border-rose-200">Maks 3x</span>
                  </div>
                  <p className="text-[11px] text-slate-600 leading-relaxed font-normal">
                    Sistem otomatis merekam aktivitas hilangnya fokus jendela peramban. Jika melebihi 3 peringatan, ujian terkunci otomatis.
                  </p>
                </div>

                <div className="p-2.5 rounded-xl border border-slate-200 bg-slate-50/60 border-l-4 border-l-emerald-800 space-y-1">
                  <div className="flex items-center justify-between">
                    <span className="font-bold text-slate-900">2. Penyimpanan Otomatis (Autosave)</span>
                    <span className="text-[10px] font-bold text-emerald-800 bg-emerald-50 px-1.5 py-0.2 rounded border border-emerald-200">Realtime</span>
                  </div>
                  <p className="text-[11px] text-slate-600 leading-relaxed font-normal">
                    Setiap butir jawaban yang dipilih langsung terenkripsi ke database server lokal. Gangguan listrik tidak menghapus progres.
                  </p>
                </div>

                <div className="p-2.5 rounded-xl border border-slate-200 bg-slate-50/60 border-l-4 border-l-blue-900 space-y-1">
                  <div className="flex items-center justify-between">
                    <span className="font-bold text-slate-900">3. Mode Layar Penuh (Kiosk Mode)</span>
                    <span className="text-[10px] font-bold text-blue-900 bg-blue-50 px-1.5 py-0.2 rounded border border-blue-200">Wajib</span>
                  </div>
                  <p className="text-[11px] text-slate-600 leading-relaxed font-normal">
                    Tombol pintasan keyboard seperti Ctrl+C, Ctrl+V, dan inspeksi kode otomatis dinonaktifkan demi keadilan asesmen.
                  </p>
                </div>
              </div>
            </div>

            {/* Card 2: Pengawas Ujian / Teknisi Lab (Data-driven) */}
            <div className="shrink-0 bg-white border border-slate-200/90 rounded-xl p-3.5 shadow-xs space-y-2.5">
              <div className="flex items-center justify-between pb-2 border-b border-slate-100">
                <div className="flex items-center gap-2">
                  <div className="w-7 h-7 rounded-lg bg-slate-100 text-slate-800 flex items-center justify-center shrink-0 border border-slate-200">
                    <School className="w-3.5 h-3.5" />
                  </div>
                  <span className="text-xs font-bold text-slate-800">
                    {activeExamToday ? 'Pengawas Ruang Ujian' : 'Infrastruktur & Teknisi Lab'}
                  </span>
                </div>
                <span className="px-2.5 py-0.5 rounded-md text-xs font-bold bg-slate-800 text-white font-mono shadow-2xs">
                  {activeExamToday?.room_name || 'Lab Komputer Labschool'}
                </span>
              </div>

              <div className="flex items-center gap-3">
                <img
                  src={activeExamToday?.teacher_avatar_url || 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?auto=format&fit=crop&q=80&w=150'}
                  alt={activeExamToday?.teacher_name || 'Pengawas'}
                  className="w-11 h-11 rounded-xl object-cover shadow-xs border border-slate-200 shrink-0"
                />
                <div className="min-w-0 flex-1">
                  <h4 className="text-sm font-bold text-slate-900 truncate">
                    {activeExamToday?.teacher_name || 'Budi Santoso, S.Kom., M.T.'}
                  </h4>
                  <p className="text-xs text-slate-500 font-mono font-medium truncate">
                    19870512 201201 1 003
                  </p>
                  <p className="text-xs text-blue-900 font-semibold truncate">
                    {activeExamToday ? `Pengawas Sesi • ${activeExamToday.subject_name}` : 'Teknisi Jaringan & CBT Server Lab'}
                  </p>
                </div>
              </div>

              <div className="grid grid-cols-2 gap-2 pt-0.5">
                <button
                  type="button"
                  onClick={() => alert(activeExamToday ? 'Pemberitahuan telah dikirimkan ke layar pengawas lab. Pengawas akan menghampiri workstation Anda.' : 'Pemberitahuan kendala teknis telah terkirim ke tim teknisi lab.')}
                  className="px-3 py-2 rounded-xl bg-blue-900 hover:bg-blue-950 text-white text-xs font-bold text-center transition-colors flex items-center justify-center gap-1.5 shadow-xs cursor-pointer"
                >
                  <LifeBuoy className="w-3.5 h-3.5 text-white" />
                  <span>Bantuan Teknis</span>
                </button>

                <button
                  type="button"
                  onClick={() => alert(activeExamToday ? `Token ruang ujian aktif diumumkan di papan tulis lab: ${activeExamToday.token_required}` : 'Tidak ada sesi ujian aktif saat ini. Token baru akan dibagikan saat jadwal sesi dimulai.')}
                  className="px-3 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold text-center transition-colors flex items-center justify-center gap-1.5 shadow-xs cursor-pointer"
                >
                  <HelpCircle className="w-3.5 h-3.5 text-white" />
                  <span>Panduan Token</span>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* ─── MODAL INPUT TOKEN RUANG CBT (STANDARDIZED POPUP) ─── */}
      {selectedExamId && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4 overflow-y-auto">
          <div className="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-lg w-full overflow-hidden animate-in fade-in zoom-in-95 duration-150">
            {/* Modal Header Bar */}
            <div className="px-6 py-4 bg-white border-b border-slate-100 flex items-center justify-between">
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 rounded-xl bg-blue-50 text-blue-900 border border-blue-200 flex items-center justify-center font-bold shrink-0">
                  <KeyRound className="w-5 h-5 text-blue-900" />
                </div>
                <div>
                  <h3 className="font-extrabold text-sm sm:text-base text-slate-900 tracking-tight">
                    Validasi Token Ruang CBT
                  </h3>
                  <p className="text-xs text-slate-500 font-normal">
                    {selectedExam ? `${selectedExam.subject_name} • ${selectedExam.room_name}` : 'Laboratorium Komputer • Labschool UM'}
                  </p>
                </div>
              </div>

              <button
                type="button"
                onClick={() => setSelectedExamId(null)}
                className="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors cursor-pointer"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            {/* Modal Form */}
            <form onSubmit={handleVerifyTokenAndProceed}>
              <div className="p-6 space-y-4">
                {/* Exam Details Preview Card */}
                {selectedExam && (
                  <div className="p-4 bg-slate-50 rounded-xl border border-slate-200/90 space-y-2 text-xs">
                    <div className="flex items-start justify-between gap-2 pb-2 border-b border-slate-200">
                      <span className="text-slate-500 font-semibold shrink-0">Nama Ujian:</span>
                      <span className="font-bold text-slate-900 text-right">{selectedExam.title}</span>
                    </div>
                    <div className="grid grid-cols-2 gap-3 pt-0.5">
                      <div>
                        <span className="text-slate-500 font-semibold block text-[11px]">Durasi Sesi:</span>
                        <span className="font-bold text-slate-900">{selectedExam.duration_minutes} Menit ({selectedExam.total_questions} Soal)</span>
                      </div>
                      <div>
                        <span className="text-slate-500 font-semibold block text-[11px]">Ruang Workstation:</span>
                        <span className="font-bold text-slate-900">{selectedExam.room_name}</span>
                      </div>
                    </div>
                  </div>
                )}

                {/* Instructions Alert Strip */}
                <div className="p-3.5 bg-blue-50/70 rounded-xl border border-blue-200/80 text-xs text-blue-950 flex items-start gap-2.5">
                  <HelpCircle className="w-4 h-4 text-blue-900 shrink-0 mt-0.5" />
                  <p className="leading-relaxed font-normal">
                    Mintalah token sesi ujian kepada pengawas di ruang lab komputer. Setelah token diverifikasi, sesi ujian akan langsung beralih ke mode pengerjaan layar penuh dan timer server mulai berjalan.
                  </p>
                </div>

                {/* Token Input */}
                <div className="space-y-1.5">
                  <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                    Masukkan Token Ujian *
                  </label>
                  <input
                    type="text"
                    required
                    placeholder="Contoh: LAB-9X7B"
                    value={tokenInput}
                    onChange={(e) => setTokenInput(e.target.value.toUpperCase())}
                    className="w-full px-4 py-3 rounded-xl border-2 border-slate-200 text-center font-mono font-black text-xl uppercase tracking-widest text-slate-900 placeholder:text-slate-300 focus:outline-none focus:border-blue-900 transition-colors"
                    autoFocus
                  />
                  <p className="text-[11px] text-slate-500 font-medium text-center">
                    Token otomatis dikonversi ke huruf kapital.
                  </p>
                </div>

                {/* Error Banner */}
                {tokenError && (
                  <div className="p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-medium flex items-start gap-2.5">
                    <AlertTriangle className="w-4 h-4 text-rose-700 shrink-0 mt-0.5" />
                    <span className="leading-relaxed">{tokenError}</span>
                  </div>
                )}
              </div>

              {/* Modal Footer */}
              <div className="px-6 py-4 bg-slate-50 border-t border-slate-100 flex items-center justify-end gap-2.5">
                <button
                  type="button"
                  onClick={() => setSelectedExamId(null)}
                  className="px-4 py-2.5 rounded-xl border border-slate-200 bg-white text-xs font-semibold text-slate-700 hover:bg-slate-100 transition-colors cursor-pointer"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  className="px-5 py-2.5 rounded-xl bg-blue-900 hover:bg-blue-950 text-white text-xs font-bold tracking-wider uppercase transition-colors shadow-xs flex items-center gap-1.5 cursor-pointer"
                >
                  <span>Verifikasi & Masuk Ujian</span>
                  <ArrowRight className="w-3.5 h-3.5 text-white" />
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
