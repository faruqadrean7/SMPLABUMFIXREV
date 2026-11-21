'use client';

import React, { useState } from 'react';
import Link from 'next/link';
import {
  Target,
  HeartHandshake,
  Phone,
  CheckCircle2,
  ChevronRight,
  Smile,
  Heart,
  Coffee,
  AlertCircle,
  BookOpen,
  ClipboardList,
} from 'lucide-react';
import { InklusiProfileData, useSubmitDailyMood } from '@/lib/use-student-data';
import { ProgramMaterialsCard } from './program-materials-card';
import { ProgramTargetsCard } from './program-targets-card';
import { ProgramTasksCard } from './program-tasks-card';

interface InklusiWidgetProps {
  profile?: InklusiProfileData | Record<string, any>;
}

export function InklusiWidget({ profile }: InklusiWidgetProps) {
  const [activeTab, setActiveTab] = useState<'ringkasan' | 'materi' | 'tugas' | 'target'>(
    'ringkasan'
  );

  // Anti-fabrikasi (AGENTS.md 6.2/9): tanpa data API, tampilkan state kosong jujur,
  // JANGAN mengarang nama GPK, nomor kontak, atau milestone.
  const gpk = profile?.gpk ?? null;

  const milestones = profile?.ppi_milestones ?? [];

  const currentMood = profile?.daily_mood?.mood ?? null;
  const [selectedMood, setSelectedMood] = useState<string>(currentMood ?? '');
  const [feedback, setFeedback] = useState<string>('');
  const submitMoodMutation = useSubmitDailyMood();

  const handleSelectMood = async (mood: 'happy' | 'calm' | 'tired' | 'anxious') => {
    setSelectedMood(mood);
    try {
      await submitMoodMutation.mutateAsync({ mood });
      setFeedback('Perasaanmu hari ini telah dicatat untuk bimbingan GPK ✓');
      setTimeout(() => setFeedback(''), 4000);
    } catch {
      // ignore
    }
  };

  const moods = [
    { id: 'happy', label: 'Senang & Semangat', icon: Smile, color: 'hover:border-emerald-400' },
    { id: 'calm', label: 'Tenang & Nyaman', icon: Heart, color: 'hover:border-blue-400' },
    { id: 'tired', label: 'Sedikit Lelah', icon: Coffee, color: 'hover:border-amber-400' },
    { id: 'anxious', label: 'Perlu Bantuan', icon: AlertCircle, color: 'hover:border-rose-400' },
  ];

  return (
    <div className="bg-white border border-purple-200 rounded-xl p-4 sm:p-5 shadow-xs relative overflow-hidden">
      <div className="absolute top-0 right-0 w-32 h-32 bg-purple-50 rounded-bl-full -z-0 opacity-70 pointer-events-none" />

      {/* Header bar */}
      <div className="relative z-10 flex flex-wrap items-center justify-between gap-2 pb-3.5 border-b border-purple-100">
        <div className="flex items-center gap-2.5">
          <div className="w-8 h-8 rounded-lg bg-purple-900 text-white flex items-center justify-center shrink-0 shadow-2xs">
            <Target className="w-4 h-4" />
          </div>
          <div>
            <h2 className="text-sm font-extrabold text-slate-900 tracking-tight">
              Pendampingan Inklusi &amp; Target Belajar
            </h2>
            <p className="text-[11px] text-slate-500">Program Pembelajaran Individual (PPI)</p>
          </div>
        </div>

        {/* Tab switcher */}
        <div className="flex items-center gap-1 bg-purple-50/80 p-1 rounded-lg border border-purple-200/70 text-xs font-bold">
          <button
            onClick={() => setActiveTab('ringkasan')}
            className={`px-2.5 py-1 rounded-md transition-colors cursor-pointer ${
              activeTab === 'ringkasan'
                ? 'bg-purple-900 text-white shadow-2xs'
                : 'text-purple-900 hover:bg-purple-100/70'
            }`}
          >
            Ringkasan
          </button>
          <button
            onClick={() => setActiveTab('materi')}
            className={`px-2.5 py-1 rounded-md transition-colors cursor-pointer flex items-center gap-1 ${
              activeTab === 'materi'
                ? 'bg-purple-900 text-white shadow-2xs'
                : 'text-purple-900 hover:bg-purple-100/70'
            }`}
          >
            <BookOpen className="w-3 h-3" /> Modul Adaptif
          </button>
          <button
            onClick={() => setActiveTab('tugas')}
            className={`px-2.5 py-1 rounded-md transition-colors cursor-pointer flex items-center gap-1 ${
              activeTab === 'tugas'
                ? 'bg-purple-900 text-white shadow-2xs'
                : 'text-purple-900 hover:bg-purple-100/70'
            }`}
          >
            <ClipboardList className="w-3 h-3" /> Tugas Terbimbing
          </button>
          <button
            onClick={() => setActiveTab('target')}
            className={`px-2.5 py-1 rounded-md transition-colors cursor-pointer flex items-center gap-1 ${
              activeTab === 'target'
                ? 'bg-purple-900 text-white shadow-2xs'
                : 'text-purple-900 hover:bg-purple-100/70'
            }`}
          >
            <Target className="w-3 h-3" /> Target PPI
          </button>
        </div>

        <Link
          href="/siswa/ppi"
          className="text-xs font-bold text-purple-900 hover:text-purple-950 inline-flex items-center gap-1 hover:underline"
        >
          Semua Target PPI <ChevronRight className="w-3.5 h-3.5" />
        </Link>
      </div>

      {/* Main Tab Content */}
      <div className="relative z-10 mt-3.5">
        {activeTab === 'ringkasan' && (
          <div className="grid grid-cols-1 md:grid-cols-12 gap-3.5">
            {/* GPK Card */}
            <div className="md:col-span-4 p-3.5 rounded-xl bg-purple-50/70 border border-purple-200/80 flex flex-col justify-between">
              <div>
                <span className="text-[10px] font-bold text-purple-900 uppercase tracking-wide">
                  Guru Pendamping Khusus
                </span>
                {gpk ? (
                  <>
                    <h3 className="mt-1 text-sm font-extrabold text-purple-950">{gpk.name}</h3>
                    {gpk.room && <p className="text-xs text-purple-800 mt-0.5">{gpk.room}</p>}
                  </>
                ) : (
                  <p className="mt-1 text-xs text-slate-500">
                    Belum ada data Guru Pendamping Khusus.
                  </p>
                )}
              </div>
              {gpk?.contact_phone && (
                <div className="mt-3 pt-2 border-t border-purple-200/80 flex items-center justify-between">
                  <a
                    href={`tel:${gpk.contact_phone}`}
                    className="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-purple-900 hover:bg-purple-950 text-white text-xs font-bold shadow-2xs cursor-pointer"
                  >
                    <Phone className="w-3 h-3" /> Panggil GPK
                  </a>
                  <span className="text-[10px] text-purple-700 font-semibold">Siaga di sekolah</span>
                </div>
              )}
            </div>

            {/* PPI Milestones */}
            <div className="md:col-span-4 p-3.5 rounded-xl bg-slate-50 border border-slate-200 flex flex-col justify-between">
              <div>
                <span className="text-[10px] font-bold text-slate-500 uppercase tracking-wide">
                  Milestone Belajar Terpilih
                </span>
                <div className="mt-2 space-y-1.5">
                  {milestones.length > 0 ? (
                    milestones.map((m: any) => (
                      <div
                        key={m.id}
                        className="p-2 rounded-lg bg-white border border-slate-200 flex items-center gap-2 text-xs"
                      >
                        <CheckCircle2
                          className={`w-3.5 h-3.5 shrink-0 ${
                            m.is_completed ? 'text-emerald-700' : 'text-slate-300'
                          }`}
                        />
                        <span
                          className={`truncate ${
                            m.is_completed ? 'line-through text-slate-400 font-normal' : 'text-slate-800 font-medium'
                          }`}
                        >
                          {m.title}
                        </span>
                      </div>
                    ))
                  ) : (
                    <p className="p-2 text-xs text-slate-500">
                      Belum ada target milestone PPI aktif.
                    </p>
                  )}
                </div>
              </div>
              <div className="mt-2.5 pt-2 border-t border-slate-200 flex items-center gap-1 text-xs text-slate-500">
                <HeartHandshake className="w-3.5 h-3.5 text-purple-800 shrink-0" />
                <span>Bimbingan rutin setiap hari</span>
              </div>
            </div>

            {/* Daily Mood Check */}
            <div className="md:col-span-4 p-3.5 rounded-xl bg-slate-50 border border-slate-200 flex flex-col justify-between">
              <div>
                <div className="flex items-center justify-between">
                  <span className="text-[10px] font-bold text-slate-500 uppercase tracking-wide">
                    Perasaan Belajar Hari Ini
                  </span>
                  <Smile className="w-3.5 h-3.5 text-purple-800" />
                </div>
                <div className="grid grid-cols-2 gap-1.5 mt-2">
                  {moods.map((m) => {
                    const Icon = m.icon;
                    const isSelected = selectedMood === m.id;
                    return (
                      <button
                        key={m.id}
                        type="button"
                        onClick={() => handleSelectMood(m.id as any)}
                        disabled={submitMoodMutation.isPending}
                        className={`p-2 rounded-lg border text-left flex items-center gap-1.5 transition-all cursor-pointer ${
                          isSelected
                            ? 'bg-purple-100 border-purple-400 text-purple-950 font-bold shadow-2xs'
                            : 'bg-white border-slate-200 text-slate-700 hover:bg-slate-50 ' + m.color
                        }`}
                      >
                        <Icon className="w-3.5 h-3.5 shrink-0" />
                        <span className="text-[10px] truncate">{m.label}</span>
                      </button>
                    );
                  })}
                </div>
              </div>
              {feedback ? (
                <div className="mt-2 text-[10px] text-emerald-800 font-bold bg-emerald-50 p-1.5 rounded-md border border-emerald-200">
                  {feedback}
                </div>
              ) : (
                <p className="mt-2 text-[10px] text-slate-400">
                  Pilih perasaanmu agar GPK dapat menyesuaikan pendekatan belajar.
                </p>
              )}
            </div>
          </div>
        )}

        {activeTab === 'materi' && (
          <ProgramMaterialsCard
            programType="inklusi"
            title="Modul & Bahan Ajar Adaptif"
            subtitle="Materi yang disesuaikan dengan kurikulum modifikasi dan akomodasi belajar siswa"
            limit={3}
            showAllHref="/siswa/ppi"
            className="border-none p-0 shadow-none"
          />
        )}

        {activeTab === 'tugas' && (
          <ProgramTasksCard
            programType="inklusi"
            title="Tugas Belajar Mandiri & Terbimbing"
            subtitle="Kumpulkan lembar pengerjaan modul atau rekaman latihan terarah"
            limit={3}
            showAllHref="/siswa/ppi"
            className="border-none p-0 shadow-none"
          />
        )}

        {activeTab === 'target' && (
          <ProgramTargetsCard
            programType="inklusi"
            title="Target Capaian Pembelajaran Individual (PPI)"
            subtitle="Target perkembangan akademik, motorik, kemandirian, dan emosional"
            limit={3}
            showAllHref="/siswa/ppi"
            className="border-none p-0 shadow-none"
          />
        )}
      </div>
    </div>
  );
}
