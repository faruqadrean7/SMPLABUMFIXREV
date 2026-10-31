'use client';

import React from 'react';
import Link from 'next/link';
import { Globe, Award, ChevronRight, BookOpen, Sparkles, Calendar } from 'lucide-react';
import { IcpProfileData } from '@/lib/use-student-data';

interface IcpWidgetProps {
  profile?: IcpProfileData | Record<string, any>;
}

export function IcpWidget({ profile }: IcpWidgetProps) {
  const readiness = profile?.checkpoint_readiness ?? {
    english: { score: 4.8, status: 'Ready' },
    math: { score: 5.2, status: 'Exceeding' },
    science: { score: 4.5, status: 'Ready' },
  };

  const vocab = profile?.vocab_of_day ?? {
    word: 'Photosynthesis',
    phonetic: '/ˌfəʊtəʊˈsɪnθəsɪs/',
    definition: 'Process by green plants to synthesize nutrients from carbon dioxide and water.',
    subject: 'Biology / Science',
  };

  const candidateId = profile?.cambridge_candidate_id ?? 'ID-MAL-2026-042';

  return (
    <div className="bg-white border border-blue-200 rounded-xl p-4 sm:p-5 shadow-xs relative overflow-hidden">
      <div className="absolute top-0 right-0 w-32 h-32 bg-blue-50 rounded-bl-full -z-0 opacity-70 pointer-events-none" />

      {/* Header bar */}
      <div className="relative z-10 flex flex-wrap items-center justify-between gap-2 pb-3.5 border-b border-blue-100">
        <div className="flex items-center gap-2.5">
          <div className="w-8 h-8 rounded-lg bg-blue-900 text-white flex items-center justify-center shrink-0 shadow-2xs">
            <Globe className="w-4 h-4" />
          </div>
          <div>
            <h2 className="text-sm font-extrabold text-slate-900 tracking-tight">
              Cambridge Checkpoint & Global Learning
            </h2>
            <p className="text-[11px] text-slate-500">
              International Class Program • Candidate: {candidateId}
            </p>
          </div>
        </div>
        <Link
          href="/siswa/cambridge"
          className="text-xs font-bold text-blue-900 hover:text-blue-950 inline-flex items-center gap-1 hover:underline"
        >
          Lihat Portofolio <ChevronRight className="w-3.5 h-3.5" />
        </Link>
      </div>

      {/* Main Grid */}
      <div className="relative z-10 grid grid-cols-1 md:grid-cols-12 gap-3.5 mt-3.5">
        {/* Checkpoint Scores */}
        <div className="md:col-span-7 grid grid-cols-1 sm:grid-cols-3 gap-2.5">
          {Object.entries(readiness).map(([subject, data]: [string, any]) => (
            <div
              key={subject}
              className="p-3 rounded-xl bg-blue-50/60 border border-blue-200/80 flex flex-col justify-between"
            >
              <div>
                <span className="text-[10px] font-bold text-blue-900 uppercase tracking-wide">
                  {subject}
                </span>
                <div className="mt-1.5 text-xl font-black text-slate-900">
                  {data.score}
                  <span className="text-[11px] text-slate-400 font-normal"> / 6.0</span>
                </div>
              </div>
              <div className="mt-2">
                <span className="px-2 py-0.5 rounded-md text-[10px] font-bold bg-white text-blue-900 border border-blue-200 shadow-2xs">
                  {data.status}
                </span>
              </div>
            </div>
          ))}
        </div>

        {/* Vocab of the Day */}
        <div className="md:col-span-5 p-3.5 rounded-xl bg-slate-50 border border-slate-200 flex flex-col justify-between">
          <div>
            <div className="flex items-center justify-between">
              <span className="text-[10px] font-bold text-slate-500 uppercase tracking-wide">
                Vocabulary of the Day
              </span>
              <Sparkles className="w-3.5 h-3.5 text-amber-500" />
            </div>
            <div className="mt-1 flex items-baseline gap-2">
              <h3 className="text-base font-extrabold text-blue-900">{vocab.word}</h3>
              {vocab.phonetic && (
                <span className="text-[11px] font-mono text-slate-500">{vocab.phonetic}</span>
              )}
            </div>
            <p className="text-xs text-slate-600 mt-1 line-clamp-2 leading-relaxed">
              {vocab.definition}
            </p>
          </div>
          <div className="mt-2.5 pt-2 border-t border-slate-200 text-[10px] font-semibold text-slate-500">
            Subject: {vocab.subject || 'English / Science'}
          </div>
        </div>
      </div>
    </div>
  );
}
