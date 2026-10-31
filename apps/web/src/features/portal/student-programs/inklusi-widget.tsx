'use client';

import React, { useState } from 'react';
import Link from 'next/link';
import { Target, HeartHandshake, Phone, CheckCircle2, ChevronRight, Smile, Heart, Coffee, AlertCircle } from 'lucide-react';
import { InklusiProfileData, useSubmitDailyMood } from '@/lib/use-student-data';

interface InklusiWidgetProps {
  profile?: InklusiProfileData | Record<string, any>;
}

export function InklusiWidget({ profile }: InklusiWidgetProps) {
  const gpk = profile?.gpk ?? {
    name: 'Ibu Ratna Dewi, S.Pd (GPK)',
    contact_phone: '081234567890',
    room: 'Ruang Sumber Inklusi Lab UM',
  };

  const milestones = profile?.ppi_milestones ?? [
    { id: 'm1', title: 'Menyelesaikan modul membaca teks deskriptif', is_completed: true },
    { id: 'm2', title: 'Latihan fokus & motorik halus terarah (15 menit)', is_completed: false },
  ];

  const currentMood = profile?.daily_mood?.mood ?? 'happy';
  const [selectedMood, setSelectedMood] = useState<string>(currentMood);
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
              Pendampingan Inklusi & Target Belajar
            </h2>
            <p className="text-[11px] text-slate-500">Program Pembelajaran Individual (PPI)</p>
          </div>
        </div>
        <Link
          href="/siswa/ppi"
          className="text-xs font-bold text-purple-900 hover:text-purple-950 inline-flex items-center gap-1 hover:underline"
        >
          Semua Target PPI <ChevronRight className="w-3.5 h-3.5" />
        </Link>
      </div>

      {/* Main Grid */}
      <div className="relative z-10 grid grid-cols-1 md:grid-cols-12 gap-3.5 mt-3.5">
        {/* GPK Card */}
        <div className="md:col-span-4 p-3.5 rounded-xl bg-purple-50/70 border border-purple-200/80 flex flex-col justify-between">
          <div>
            <span className="text-[10px] font-bold text-purple-900 uppercase tracking-wide">
              Guru Pendamping Khusus
            </span>
            <h3 className="mt-1 text-sm font-extrabold text-purple-950">{gpk.name}</h3>
            <p className="text-xs text-purple-800 mt-0.5">{gpk.room}</p>
          </div>
          <div className="mt-3 pt-2 border-t border-purple-200/80 flex items-center justify-between">
            <a
              href={`tel:${gpk.contact_phone}`}
              className="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-purple-900 hover:bg-purple-950 text-white text-xs font-bold shadow-2xs"
            >
              <Phone className="w-3 h-3" /> Panggil GPK
            </a>
            <span className="text-[10px] text-purple-700 font-semibold">Siaga di sekolah</span>
          </div>
        </div>

        {/* PPI Milestones */}
        <div className="md:col-span-4 p-3.5 rounded-xl bg-slate-50 border border-slate-200 flex flex-col justify-between">
          <div>
            <span className="text-[10px] font-bold text-slate-500 uppercase tracking-wide">
              Target Belajar Saya
            </span>
            <div className="mt-2 space-y-1.5">
              {milestones.map((m: any) => (
                <div key={m.id} className="flex items-start gap-2 text-xs">
                  <CheckCircle2
                    className={`w-3.5 h-3.5 shrink-0 mt-0.5 ${
                      m.is_completed ? 'text-emerald-600' : 'text-slate-300'
                    }`}
                  />
                  <span
                    className={
                      m.is_completed
                        ? 'line-through text-slate-400 text-[11px]'
                        : 'font-medium text-slate-800 text-[11px]'
                    }
                  >
                    {m.title}
                  </span>
                </div>
              ))}
            </div>
          </div>
          <div className="mt-2 pt-2 border-t border-slate-200 text-[10px] text-slate-500">
            Pendampingan belajar adaptif & fleksibel
          </div>
        </div>

        {/* Daily Mood Check-in */}
        <div className="md:col-span-4 p-3.5 rounded-xl bg-slate-50 border border-slate-200 flex flex-col justify-between">
          <div>
            <span className="text-[10px] font-bold text-slate-500 uppercase tracking-wide">
              Perasaan Hari Ini
            </span>
            <div className="grid grid-cols-2 gap-1.5 mt-2">
              {moods.map((m) => {
                const Icon = m.icon;
                const isSelected = selectedMood === m.id;
                return (
                  <button
                    key={m.id}
                    onClick={() => handleSelectMood(m.id as any)}
                    className={`flex items-center gap-1.5 p-1.5 rounded-lg border text-[11px] font-semibold text-left transition-all ${
                      isSelected
                        ? 'bg-purple-900 text-white border-purple-900 shadow-2xs'
                        : `bg-white text-slate-700 border-slate-200 ${m.color}`
                    }`}
                  >
                    <Icon className="w-3.5 h-3.5 shrink-0" />
                    <span className="truncate">{m.label.split(' ')[0]}</span>
                  </button>
                );
              })}
            </div>
          </div>
          {feedback && (
            <p className="mt-2 text-[10px] font-bold text-emerald-700">{feedback}</p>
          )}
        </div>
      </div>
    </div>
  );
}
