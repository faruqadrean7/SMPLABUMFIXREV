'use client';

import React from 'react';
import { GraduationCap, Star, Trophy, BookOpen } from 'lucide-react';
import { useStudentGrades } from '@/lib/use-student-data';
import { StudentLoading, StudentEmpty, StudentError } from '@/features/portal/mascot/student-states';
import { StudentPageHeader, StatTile, SectionCard } from '@/features/portal/mascot/student-ui';

export default function SiswaNilaiPage() {
  const { data: grades = [], isLoading, isError, refetch } = useStudentGrades();
  const graded = grades.filter((g) => typeof g.finalScore === 'number');
  const avg = graded.length ? Math.round(graded.reduce((a, g) => a + (g.finalScore || 0), 0) / graded.length) : null;
  const tuntas = graded.filter((g) => g.isPassed).length;

  if (isLoading) return <StudentLoading label="Memuat nilai kamu..." />;
  if (isError) return <StudentError message="Gagal memuat nilai. Coba lagi ya." onRetry={() => refetch()} />;

  return (
    <div className="flex flex-col gap-3.5 text-slate-900">
      <StudentPageHeader
        title="Nilai Kamu"
        subtitle="Pantau capaian belajarmu semester ini"
        icon={GraduationCap}
        expression="study"
      />

      <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
        <StatTile icon={Star} label="Rata-rata nilai" value={avg !== null ? avg : '-'} tone="sky" />
        <StatTile icon={Trophy} label="Mapel tuntas" value={`${tuntas}/${graded.length}`} tone="mint" />
        <StatTile icon={BookOpen} label="Total mapel" value={grades.length} tone="grape" />
      </div>

      <SectionCard title="Rincian nilai per mata pelajaran">
        {graded.length === 0 ? (
          <StudentEmpty title="Nilai belum tersedia" hint="Guru belum menginput nilai untuk periode berjalan." />
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
      </SectionCard>
    </div>
  );
}
