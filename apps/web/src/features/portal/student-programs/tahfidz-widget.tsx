'use client';

import React, { useState } from 'react';
import Link from 'next/link';
import {
  BookOpen,
  CheckCircle2,
  ChevronRight,
  UserCheck,
  Flame,
  FileText,
  ClipboardList,
  Target,
} from 'lucide-react';
import { TahfidzProfileData, useSubmitMutabaah } from '@/lib/use-student-data';
import { ProgramMaterialsCard } from './program-materials-card';
import { ProgramTargetsCard } from './program-targets-card';
import { ProgramTasksCard } from './program-tasks-card';

interface TahfidzWidgetProps {
  profile?: TahfidzProfileData | Record<string, any>;
}

export function TahfidzWidget({ profile }: TahfidzWidgetProps) {
  const [activeTab, setActiveTab] = useState<'ringkasan' | 'materi' | 'tugas' | 'target'>(
    'ringkasan'
  );

  const currentJuz = profile?.current_juz ?? 2;
  const targetJuz = profile?.target_juz ?? 3;
  const currentSurah = profile?.current_surah ?? 'Al-Mulk: 1-30';
  const lastGrade = profile?.last_tasmi_grade ?? 'Mumtaz';
  const evaluator = profile?.tasmi_evaluator ?? 'Ustadz Abdullah, S.Pd.I';
  const mutabaah = profile?.mutabaah_today;

  const percentage = Math.min(100, Math.round((currentJuz / targetJuz) * 100));

  const submitMutabaahMutation = useSubmitMutabaah();
  const [sholatCount, setSholatCount] = useState<number>(mutabaah?.sholat_fardhu ?? 5);
  const [tilawahCount, setTilawahCount] = useState<number>(mutabaah?.tilawah_pages ?? 4);
  const [savedSuccess, setSavedSuccess] = useState(false);

  const handleSaveMutabaah = async () => {
    try {
      await submitMutabaahMutation.mutateAsync({
        sholat_fardhu: sholatCount,
        tilawah_pages: tilawahCount,
        dhuha: mutabaah?.dhuha ?? true,
        tahajud: mutabaah?.tahajud ?? false,
      });
      setSavedSuccess(true);
      setTimeout(() => setSavedSuccess(false), 3000);
    } catch {
      // ignore
    }
  };

  return (
    <div className="bg-white border border-emerald-200 rounded-xl p-4 sm:p-5 shadow-xs relative overflow-hidden">
      <div className="absolute top-0 right-0 w-32 h-32 bg-emerald-50 rounded-bl-full -z-0 opacity-70 pointer-events-none" />

      {/* Header bar */}
      <div className="relative z-10 flex flex-wrap items-center justify-between gap-2 pb-3.5 border-b border-emerald-100">
        <div className="flex items-center gap-2.5">
          <div className="w-8 h-8 rounded-lg bg-emerald-800 text-white flex items-center justify-center shrink-0 shadow-2xs">
            <BookOpen className="w-4 h-4" />
          </div>
          <div>
            <h2 className="text-sm font-extrabold text-slate-900 tracking-tight">
              Capaian Tahfidz &amp; Ibadah Harian
            </h2>
            <p className="text-[11px] text-slate-500">Program Khusus Halaqah Al-Qur&apos;an</p>
          </div>
        </div>

        {/* Tab switcher */}
        <div className="flex items-center gap-1 bg-emerald-50/80 p-1 rounded-lg border border-emerald-200/70 text-xs font-bold">
          <button
            onClick={() => setActiveTab('ringkasan')}
            className={`px-2.5 py-1 rounded-md transition-colors cursor-pointer ${
              activeTab === 'ringkasan'
                ? 'bg-emerald-800 text-white shadow-2xs'
                : 'text-emerald-900 hover:bg-emerald-100/70'
            }`}
          >
            Ringkasan
          </button>
          <button
            onClick={() => setActiveTab('materi')}
            className={`px-2.5 py-1 rounded-md transition-colors cursor-pointer flex items-center gap-1 ${
              activeTab === 'materi'
                ? 'bg-emerald-800 text-white shadow-2xs'
                : 'text-emerald-900 hover:bg-emerald-100/70'
            }`}
          >
            <BookOpen className="w-3 h-3" /> Materi
          </button>
          <button
            onClick={() => setActiveTab('tugas')}
            className={`px-2.5 py-1 rounded-md transition-colors cursor-pointer flex items-center gap-1 ${
              activeTab === 'tugas'
                ? 'bg-emerald-800 text-white shadow-2xs'
                : 'text-emerald-900 hover:bg-emerald-100/70'
            }`}
          >
            <ClipboardList className="w-3 h-3" /> Tugas Setoran
          </button>
          <button
            onClick={() => setActiveTab('target')}
            className={`px-2.5 py-1 rounded-md transition-colors cursor-pointer flex items-center gap-1 ${
              activeTab === 'target'
                ? 'bg-emerald-800 text-white shadow-2xs'
                : 'text-emerald-900 hover:bg-emerald-100/70'
            }`}
          >
            <Target className="w-3 h-3" /> Target
          </button>
        </div>

        <Link
          href="/siswa/hafalan"
          className="text-xs font-bold text-emerald-800 hover:text-emerald-950 inline-flex items-center gap-1 hover:underline"
        >
          Halaman Lengkap <ChevronRight className="w-3.5 h-3.5" />
        </Link>
      </div>

      {/* Main Tab Content */}
      <div className="relative z-10 mt-3.5">
        {activeTab === 'ringkasan' && (
          <div className="grid grid-cols-1 md:grid-cols-12 gap-3.5">
            {/* Juz Progress */}
            <div className="md:col-span-5 p-3.5 rounded-xl bg-emerald-50/70 border border-emerald-200/80 flex flex-col justify-between">
              <div>
                <div className="flex items-center justify-between text-xs">
                  <span className="font-bold text-emerald-900">Target Hafalan</span>
                  <span className="text-[10px] font-bold px-2 py-0.5 rounded-md bg-emerald-200/80 text-emerald-950">
                    {percentage}% Tercapai
                  </span>
                </div>
                <div className="mt-2 text-2xl font-black text-emerald-950">
                  {currentJuz} dari {targetJuz} Juz
                </div>
                <p className="text-xs text-emerald-800 font-medium mt-0.5">
                  Target kelulusan tingkat: {targetJuz} Juz mutqin
                </p>
              </div>
              <div className="mt-3">
                <div className="w-full bg-emerald-200/90 rounded-full h-2">
                  <div
                    className="bg-emerald-700 h-2 rounded-full transition-all duration-500"
                    style={{ width: `${percentage}%` }}
                  />
                </div>
              </div>
            </div>

            {/* Setoran Terakhir */}
            <div className="md:col-span-4 p-3.5 rounded-xl bg-slate-50 border border-slate-200 flex flex-col justify-between">
              <div>
                <span className="text-[11px] font-bold text-slate-500 uppercase tracking-wide">
                  Setoran Terakhir
                </span>
                <div className="mt-1 text-base font-extrabold text-slate-900">{currentSurah}</div>
                <div className="mt-2 flex items-center gap-1.5">
                  <span className="px-2 py-0.5 rounded-md text-[11px] font-bold bg-emerald-100 text-emerald-900 border border-emerald-200 inline-flex items-center gap-1">
                    <CheckCircle2 className="w-3 h-3 text-emerald-700" /> Predikat: {lastGrade}
                  </span>
                </div>
              </div>
              <div className="mt-2.5 pt-2 border-t border-slate-200 flex items-center gap-1.5 text-xs text-slate-600">
                <UserCheck className="w-3.5 h-3.5 text-emerald-700 shrink-0" />
                <span className="truncate">{evaluator}</span>
              </div>
            </div>

            {/* Checklist Mutaba'ah */}
            <div className="md:col-span-3 p-3.5 rounded-xl bg-slate-50 border border-slate-200 flex flex-col justify-between">
              <div>
                <div className="flex items-center justify-between">
                  <span className="text-[11px] font-bold text-slate-500 uppercase tracking-wide">
                    Mutaba&apos;ah Hari Ini
                  </span>
                  <Flame className="w-3.5 h-3.5 text-amber-500" />
                </div>
                <div className="mt-2 space-y-1.5 text-xs">
                  <div className="flex items-center justify-between">
                    <span className="text-slate-600">Sholat Fardhu:</span>
                    <span className="font-bold text-slate-900">{sholatCount}/5 Waktu</span>
                  </div>
                  <div className="flex items-center justify-between">
                    <span className="text-slate-600">Tilawah:</span>
                    <span className="font-bold text-slate-900">{tilawahCount} Halaman</span>
                  </div>
                </div>
              </div>
              <div className="mt-3 pt-2 border-t border-slate-200">
                <button
                  onClick={handleSaveMutabaah}
                  disabled={submitMutabaahMutation.isPending}
                  className="w-full py-1 px-2 rounded-lg bg-emerald-800 hover:bg-emerald-900 text-white text-xs font-bold transition-colors disabled:opacity-50 cursor-pointer"
                >
                  {savedSuccess
                    ? 'Tersimpan ✓'
                    : submitMutabaahMutation.isPending
                    ? 'Menyimpan...'
                    : 'Update Mutabaah'}
                </button>
              </div>
            </div>
          </div>
        )}

        {activeTab === 'materi' && (
          <ProgramMaterialsCard
            programType="tahfidz"
            title="Materi Tajwid & Audio Murottal"
            subtitle="Panduan hafalan dan rekaman pengucapan makhraj yang benar"
            limit={3}
            showAllHref="/siswa/hafalan"
            className="border-none p-0 shadow-none"
          />
        )}

        {activeTab === 'tugas' && (
          <ProgramTasksCard
            programType="tahfidz"
            title="Tugas & Rekaman Setoran Mandiri"
            subtitle="Kumpulkan link rekaman audio setoran hafalan atau video tasmi' mandiri"
            limit={3}
            showAllHref="/siswa/hafalan"
            className="border-none p-0 shadow-none"
          />
        )}

        {activeTab === 'target' && (
          <ProgramTargetsCard
            programType="tahfidz"
            title="Target Capaian Juz & Mutqin"
            subtitle="Target hafalan per caturwulan / semester halaqah Al-Qur'an"
            limit={3}
            showAllHref="/siswa/hafalan"
            className="border-none p-0 shadow-none"
          />
        )}
      </div>
    </div>
  );
}
