'use client';

import React from 'react';
import { useStudentGrades } from '@/lib/use-student-data';
import { LoadingSpinner } from '@/components/shared/loading-spinner';
import { ErrorState } from '@/components/shared/error-state';
import { EmptyState } from '@/components/shared/empty-state';

export default function SiswaNilaiPage() {
  const { data: grades = [], isLoading, isError, refetch } = useStudentGrades();
  const graded = grades.filter((g) => typeof g.finalScore === 'number');
  const avg = graded.length ? Math.round(graded.reduce((a, g) => a + (g.finalScore || 0), 0) / graded.length) : null;
  const tuntas = graded.filter((g) => g.isPassed).length;

  if (isLoading) return <div className="py-20"><LoadingSpinner label="Memuat nilai..." size="lg" /></div>;
  if (isError) return <div className="py-16"><ErrorState message="Gagal memuat nilai. Coba lagi." onRetry={() => refetch()} /></div>;

  return (
    <div className="flex flex-col gap-3.5 text-slate-900">
      <div className="bg-white border border-slate-200 rounded-xl p-4 shadow-xs">
        <h1 className="text-lg font-extrabold tracking-tight">Nilai Akademik</h1>
        <p className="text-xs text-slate-600 mt-0.5">
          Rata-rata: <strong>{avg !== null ? avg : '-'}</strong> &middot; {tuntas}/{graded.length} mapel tuntas
        </p>
      </div>

      <div className="bg-white border border-slate-200 rounded-xl p-3.5 shadow-xs">
        {graded.length === 0 ? (
          <EmptyState title="Nilai belum tersedia" description="Guru belum menginput nilai untuk periode berjalan." />
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="text-left text-xs text-slate-500 border-b border-slate-200">
                  <th className="py-2 pr-3 font-bold">Mata Pelajaran</th>
                  <th className="py-2 px-2 font-bold text-center">Nilai Akhir</th>
                  <th className="py-2 px-2 font-bold text-center">Predikat</th>
                  <th className="py-2 pl-2 font-bold text-center">Status</th>
                </tr>
              </thead>
              <tbody>
                {graded.map((g) => (
                  <tr key={g.subjectId} className="border-b border-slate-100">
                    <td className="py-2 pr-3">
                      <p className="font-semibold">{g.subjectName}</p>
                      <p className="text-[11px] text-slate-500">{g.subjectCode} &middot; {g.teacherName}</p>
                    </td>
                    <td className="py-2 px-2 text-center font-bold">{g.finalScore}</td>
                    <td className="py-2 px-2 text-center">{g.letterGrade}</td>
                    <td className="py-2 pl-2 text-center">
                      <span className={'px-2 py-0.5 rounded-md text-[11px] font-bold ' + (g.isPassed ? 'bg-emerald-700 text-white' : 'bg-amber-600 text-white')}>
                        {g.isPassed ? 'Tuntas' : 'Perlu Pendampingan'}
                      </span>
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
