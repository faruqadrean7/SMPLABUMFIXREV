'use client';

import React, { useState } from 'react';
import Link from 'next/link';
import { useAuth } from '@/providers/auth-provider';
import { useStudentGrades } from '@/features/portal';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { LoadingSpinner } from '@/components/shared/loading-spinner';
import {
  FileText,
  Award,
  ArrowLeft,
  CheckCircle2,
  AlertCircle,
  Sparkles,
  BookOpen,
  User,
  TrendingUp,
} from 'lucide-react';

export default function StudentGradesPage() {
  const { user } = useAuth();
  const { data: reportCard, isLoading } = useStudentGrades();

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-6 rounded-2xl border border-slate-200 shadow-xs">
        <div>
          <div className="flex items-center gap-2 mb-2">
            <Link
              href="/portal/siswa"
              className="inline-flex items-center gap-1 text-xs text-slate-500 hover:text-slate-900 font-medium transition-colors"
            >
              <ArrowLeft className="h-4 w-4" />
              <span>Kembali ke Beranda</span>
            </Link>
          </div>
          <h1 className="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2">
            <Award className="h-6 w-6 text-brand-blue" />
            <span>Buku Nilai & Rapor Hasil Belajar</span>
          </h1>
          <p className="text-xs sm:text-sm text-slate-500 mt-1">
            Rekap capaian asesmen formatif & sumatif kurikulum merdeka.
          </p>
        </div>

        <div className="flex items-center gap-3">
          <div className="p-3 bg-blue-50 text-brand-blue rounded-xl text-center">
            <div className="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Rata-rata</div>
            <div className="text-xl font-black text-brand-blue">{reportCard?.averageScore || 88.5}</div>
          </div>
          {reportCard?.classRank && (
            <div className="p-3 bg-emerald-50 text-status-success rounded-xl text-center">
              <div className="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Peringkat</div>
              <div className="text-xl font-black">
                #{reportCard.classRank} <span className="text-xs font-normal">/ {reportCard.totalStudents}</span>
              </div>
            </div>
          )}
        </div>
      </div>

      {isLoading ? (
        <div className="min-h-[300px] flex items-center justify-center bg-white rounded-2xl border border-slate-200">
          <LoadingSpinner label="Memuat capaian nilai..." size="lg" />
        </div>
      ) : !reportCard || reportCard.grades.length === 0 ? (
        <Card className="border-slate-200 py-12 text-center">
          <CardContent>
            <FileText className="h-12 w-12 text-slate-300 mx-auto mb-3" />
            <h3 className="text-base font-bold text-slate-700">Belum Ada Catatan Nilai</h3>
            <p className="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
              Nilai asesmen tengah semester sedang dalam proses rekapitulasi dan belum dikunci oleh dewan guru.
            </p>
          </CardContent>
        </Card>
      ) : (
        <div className="space-y-4">
          <div className="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-xs">
            <div className="p-4 sm:p-5 border-b border-slate-100 flex items-center justify-between">
              <span className="font-bold text-sm text-slate-900">
                {reportCard.periodName} ({reportCard.academicYear} - Semester {reportCard.semester})
              </span>
              <Badge variant="outline" className="text-xs">
                {reportCard.grades.length} Mata Pelajaran
              </Badge>
            </div>

            <div className="divide-y divide-slate-100">
              {reportCard.grades.map((grade) => (
                <div key={grade.subjectId} className="p-4 sm:p-5 hover:bg-slate-50/50 transition-colors">
                  <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div className="flex items-start gap-3">
                      <div className="p-2.5 rounded-xl bg-blue-50 text-brand-blue mt-0.5">
                        <BookOpen className="h-5 w-5" />
                      </div>
                      <div>
                        <div className="flex items-center gap-2">
                          <h4 className="font-bold text-sm text-slate-900">{grade.subjectName}</h4>
                          <span className="text-[10px] text-slate-400 font-mono">({grade.subjectCode})</span>
                        </div>
                        <p className="text-xs text-slate-500 mt-0.5">Pengampu: {grade.teacherName}</p>
                        {grade.competencyDesc && (
                          <p className="text-xs text-slate-600 mt-2 bg-slate-50 p-2.5 rounded-lg border border-slate-100">
                            💡 {grade.competencyDesc}
                          </p>
                        )}
                      </div>
                    </div>

                    <div className="flex items-center gap-4 self-end sm:self-center">
                      <div className="text-right">
                        <div className="text-xs text-slate-400 font-medium">Harian: {grade.dailyAverage} • PTS: {grade.midExam}</div>
                        <div className="text-lg font-black text-slate-900">
                          {grade.finalScore} <span className="text-xs font-bold text-brand-blue">({grade.letterGrade})</span>
                        </div>
                      </div>
                      <Badge
                        className={
                          grade.isPassed
                            ? 'bg-emerald-100 text-status-success hover:bg-emerald-200'
                            : 'bg-red-100 text-status-danger hover:bg-red-200'
                        }
                      >
                        {grade.isPassed ? 'Tuntas' : 'Perlu Remidi'}
                      </Badge>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          </div>

          {reportCard.homeroomNote && (
            <Card className="border-blue-200 bg-blue-50/50">
              <CardContent className="pt-6">
                <div className="flex items-start gap-3">
                  <Sparkles className="h-5 w-5 text-brand-blue mt-0.5 shrink-0" />
                  <div>
                    <h4 className="font-bold text-sm text-slate-900">Catatan Wali Kelas</h4>
                    <p className="text-xs sm:text-sm text-slate-700 mt-1 italic">
                      &quot;{reportCard.homeroomNote}&quot;
                    </p>
                  </div>
                </div>
              </CardContent>
            </Card>
          )}
        </div>
      )}
    </div>
  );
}
