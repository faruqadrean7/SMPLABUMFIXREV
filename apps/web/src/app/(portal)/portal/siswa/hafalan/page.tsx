'use client';

import React from 'react';
import Link from 'next/link';
import { BookOpen, Award, CheckCircle2, Calendar, UserCheck, ArrowLeft } from 'lucide-react';
import { useStudentDashboard } from '@/lib/use-student-data';
import { LoadingSpinner } from '@/components/shared/loading-spinner';

export default function StudentHafalanPage() {
  const { data: studentDash, isLoading } = useStudentDashboard();

  if (isLoading) {
    return (
      <div className="py-20">
        <LoadingSpinner label="Memuat data hafalan..." size="lg" />
      </div>
    );
  }

  const profile = studentDash?.programProfile as any;
  const currentJuz = profile?.current_juz ?? 2;
  const targetJuz = profile?.target_juz ?? 3;
  const lastSurah = profile?.current_surah ?? 'Al-Mulk: 1-30';
  const lastGrade = profile?.last_tasmi_grade ?? 'Mumtaz';
  const evaluator = profile?.tasmi_evaluator ?? 'Ustadz Abdullah, S.Pd.I';
  const percentage = Math.min(100, Math.round((currentJuz / targetJuz) * 100));

  return (
    <div className="flex flex-col gap-4 text-slate-900">
      {/* Header */}
      <div className="bg-white border border-slate-200 rounded-xl p-5 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <div className="w-12 h-12 rounded-xl bg-emerald-800 text-white flex items-center justify-center shrink-0">
            <BookOpen className="w-6 h-6" />
          </div>
          <div>
            <h1 className="text-lg font-extrabold tracking-tight">Hafalan &amp; Tasmi&apos; Al-Qur&apos;an</h1>
            <p className="text-xs text-slate-500">Program Khusus Tahfidz SMP Laboratorium UM</p>
          </div>
        </div>
        <Link
          href="/siswa/dashboard"
          className="inline-flex items-center gap-1.5 text-xs font-bold text-slate-600 hover:text-slate-900 px-3 py-1.5 rounded-lg border border-slate-200 bg-slate-50 hover:bg-slate-100 self-start sm:self-auto"
        >
          <ArrowLeft className="w-3.5 h-3.5" /> Kembali ke Beranda
        </Link>
      </div>

      {/* Progress Card */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
        <div className="p-4 rounded-xl bg-emerald-50 border border-emerald-200 flex flex-col justify-between">
          <div>
            <span className="text-xs font-semibold text-emerald-800 uppercase tracking-wide">Capaian Target</span>
            <div className="mt-2 text-2xl font-extrabold text-emerald-950">
              {currentJuz} dari {targetJuz} Juz
            </div>
          </div>
          <div className="mt-3">
            <div className="w-full bg-emerald-200 rounded-full h-2.5">
              <div className="bg-emerald-700 h-2.5 rounded-full" style={{ width: `${percentage}%` }} />
            </div>
            <p className="text-[11px] text-emerald-800 font-medium mt-1 text-right">{percentage}% Tercapai</p>
          </div>
        </div>

        <div className="p-4 rounded-xl bg-white border border-slate-200">
          <span className="text-xs font-semibold text-slate-500 uppercase tracking-wide">Setoran Terakhir</span>
          <div className="mt-2 text-lg font-bold text-slate-900">{lastSurah}</div>
          <div className="mt-2 inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-xs font-bold bg-emerald-100 text-emerald-900 border border-emerald-300">
            <CheckCircle2 className="w-3.5 h-3.5 text-emerald-700" /> Predikat: {lastGrade}
          </div>
        </div>

        <div className="p-4 rounded-xl bg-white border border-slate-200">
          <span className="text-xs font-semibold text-slate-500 uppercase tracking-wide">Penguji / Ustadz</span>
          <div className="mt-2 flex items-center gap-2">
            <UserCheck className="w-4 h-4 text-emerald-700 shrink-0" />
            <span className="text-sm font-bold text-slate-900">{evaluator}</span>
          </div>
          <p className="text-xs text-slate-500 mt-2">Halaqah harian: 06:45 - 07:15 WIB</p>
        </div>
      </div>
    </div>
  );
}
