'use client';

import React from 'react';
import { useGuardian } from '@/lib/guardian-context';
import { LoadingSpinner } from '@/components/shared/loading-spinner';
import { ErrorState } from '@/components/shared/error-state';
import { EmptyState } from '@/components/shared/empty-state';

export default function OrtuNilaiPage() {
  const { guardian, activeChild, activeChildId, setActiveChildId, activeGrades, isLoading, isError, refetchAll } = useGuardian();
  const children = guardian?.children ?? [];
  const grades = (activeGrades ?? []).filter((g) => typeof g.final_score === 'number');

  if (isLoading) return <div className="py-20"><LoadingSpinner label="Memuat nilai..." size="lg" /></div>;
  if (isError) return <div className="py-16"><ErrorState message="Gagal memuat nilai. Coba lagi." onRetry={() => refetchAll()} /></div>;

  return (
    <div className="flex flex-col gap-3.5 text-slate-900">
      <div className="bg-white border border-slate-200 rounded-xl p-4 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div className="min-w-0">
          <h1 className="text-lg font-extrabold tracking-tight">Nilai Akademik</h1>
          <p className="text-xs text-slate-600 mt-0.5">{activeChild?.name} &middot; {activeChild?.class_name}</p>
        </div>
        {children.length > 1 && (
          <div className="flex flex-wrap gap-1.5">
            {children.map((c) => (
              <button key={c.id} type="button" onClick={() => setActiveChildId(c.id)}
                className={'px-3 py-1.5 rounded-lg text-xs font-bold border transition-colors ' + (c.id === activeChildId ? 'bg-blue-900 text-white border-blue-900' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-100')}>
                {c.name}
              </button>
            ))}
          </div>
        )}
      </div>

      <div className="bg-white border border-slate-200 rounded-xl p-3.5 shadow-xs">
        {grades.length === 0 ? (
          <EmptyState title="Nilai belum tersedia" description="Guru belum menginput nilai untuk periode berjalan." />
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="text-left text-xs text-slate-500 border-b border-slate-200">
                  <th className="py-2 pr-3 font-bold">Mata Pelajaran</th>
                  <th className="py-2 px-2 font-bold text-center">KKTP</th>
                  <th className="py-2 px-2 font-bold text-center">Formatif</th>
                  <th className="py-2 px-2 font-bold text-center">Sumatif</th>
                  <th className="py-2 px-2 font-bold text-center">STS</th>
                  <th className="py-2 px-2 font-bold text-center">Akhir</th>
                  <th className="py-2 pl-2 font-bold text-center">Predikat</th>
                </tr>
              </thead>
              <tbody>
                {grades.map((g) => (
                  <tr key={g.subject_code} className="border-b border-slate-100">
                    <td className="py-2 pr-3">
                      <p className="font-semibold">{g.subject_name}</p>
                      <p className="text-[11px] text-slate-500">{g.teacher_name}</p>
                    </td>
                    <td className="py-2 px-2 text-center text-slate-500">{g.kktp}</td>
                    <td className="py-2 px-2 text-center">{g.formative_avg}</td>
                    <td className="py-2 px-2 text-center">{g.summative_avg}</td>
                    <td className="py-2 px-2 text-center">{g.sts_score}</td>
                    <td className="py-2 px-2 text-center font-bold">{g.final_score}</td>
                    <td className="py-2 pl-2 text-center">
                      <span className={'px-2 py-0.5 rounded-md text-[11px] font-bold ' + (g.status === 'Tuntas' ? 'bg-emerald-700 text-white' : 'bg-amber-600 text-white')}>{g.predicate}</span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  );
}
