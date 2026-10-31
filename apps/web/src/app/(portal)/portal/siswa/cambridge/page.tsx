'use client';

import React from 'react';
import Link from 'next/link';
import { Globe, Award, BookOpen, Calendar, ArrowLeft } from 'lucide-react';
import { useStudentDashboard } from '@/lib/use-student-data';
import { LoadingSpinner } from '@/components/shared/loading-spinner';

export default function StudentCambridgePage() {
  const { data: studentDash, isLoading } = useStudentDashboard();

  if (isLoading) {
    return (
      <div className="py-20">
        <LoadingSpinner label="Memuat portofolio Cambridge..." size="lg" />
      </div>
    );
  }

  const profile = studentDash?.programProfile as any;
  const candidateId = profile?.cambridge_candidate_id ?? 'ID-MAL-2026-042';
  const readiness = profile?.checkpoint_readiness ?? {
    english: { score: 4.8, status: 'Ready' },
    math: { score: 5.2, status: 'Exceeding' },
    science: { score: 4.5, status: 'Ready' },
  };

  return (
    <div className="flex flex-col gap-4 text-slate-900">
      {/* Header */}
      <div className="bg-white border border-slate-200 rounded-xl p-5 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <div className="w-12 h-12 rounded-xl bg-blue-900 text-white flex items-center justify-center shrink-0">
            <Globe className="w-6 h-6" />
          </div>
          <div>
            <h1 className="text-lg font-extrabold tracking-tight">Cambridge & Portofolio ICP</h1>
            <p className="text-xs text-slate-500">International Class Program • Candidate: {candidateId}</p>
          </div>
        </div>
        <Link
          href="/siswa/dashboard"
          className="inline-flex items-center gap-1.5 text-xs font-bold text-slate-600 hover:text-slate-900 px-3 py-1.5 rounded-lg border border-slate-200 bg-slate-50 hover:bg-slate-100 self-start sm:self-auto"
        >
          <ArrowLeft className="w-3.5 h-3.5" /> Kembali ke Beranda
        </Link>
      </div>

      {/* Checkpoint Cards */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
        {Object.entries(readiness).map(([subject, data]: [string, any]) => (
          <div key={subject} className="p-4 rounded-xl bg-white border border-slate-200 flex flex-col justify-between">
            <div>
              <span className="text-xs font-semibold text-blue-900 uppercase tracking-wide">
                Checkpoint {subject.toUpperCase()}
              </span>
              <div className="mt-2 text-2xl font-extrabold text-slate-900">
                Score: {data.score} <span className="text-xs text-slate-400 font-normal">/ 6.0</span>
              </div>
            </div>
            <div className="mt-3">
              <span className="px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-50 text-blue-900 border border-blue-200">
                Status: {data.status}
              </span>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
