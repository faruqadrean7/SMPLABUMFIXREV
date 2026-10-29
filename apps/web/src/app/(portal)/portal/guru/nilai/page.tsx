'use client';

import React, { useState, useEffect } from 'react';
import Link from 'next/link';
import {
  useTeacherAssignmentsSummary,
  useTeacherGradeComponents,
  useTeacherStudentGrades,
  guruApi,
  TeacherStudentGrade,
} from '@/features/portal/guru';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { LoadingSpinner } from '@/components/shared/loading-spinner';
import { EmptyState } from '@/components/shared/empty-state';
import {
  FileText,
  ArrowLeft,
  Save,
  CheckCircle2,
  Award,
  Layers,
  Sparkles,
  AlertCircle,
} from 'lucide-react';

export default function TeacherGradesPage() {
  const {
    subjects,
    getClassesForSubject,
    isLoading: isLoadingAssignments,
  } = useTeacherAssignmentsSummary();

  const [selectedSubjectId, setSelectedSubjectId] = useState<string>('');
  const [selectedClassId, setSelectedClassId] = useState<string>('');

  // Available classes for selected subject
  const availableClasses = React.useMemo(() => {
    return getClassesForSubject(selectedSubjectId);
  }, [getClassesForSubject, selectedSubjectId]);

  // Auto-select subject if 1 or not yet selected
  useEffect(() => {
    if (subjects.length > 0) {
      if (!selectedSubjectId || !subjects.some((s) => s.id === selectedSubjectId)) {
        setSelectedSubjectId(subjects[0].id);
      }
    } else {
      setSelectedSubjectId('');
    }
  }, [subjects, selectedSubjectId]);

  // Keep class aligned with availableClasses for this subject
  useEffect(() => {
    if (availableClasses.length > 0) {
      if (!selectedClassId || !availableClasses.some((c) => c.id === selectedClassId)) {
        setSelectedClassId(availableClasses[0].id);
      }
    } else {
      setSelectedClassId('');
    }
  }, [availableClasses, selectedClassId]);

  const { data: components = [], isLoading: isLoadingComponents } = useTeacherGradeComponents(selectedClassId, selectedSubjectId);
  const { data: initialGrades, isLoading: isLoadingGrades } = useTeacherStudentGrades(selectedClassId, selectedSubjectId);

  const [grades, setGrades] = useState<TeacherStudentGrade[]>([]);
  const [isSaving, setIsSaving] = useState(false);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  useEffect(() => {
    if (initialGrades && initialGrades.length > 0) {
      setGrades(initialGrades);
    } else {
      setGrades([]);
    }
  }, [initialGrades]);

  const handleScoreChange = (studentId: string, componentId: string, val: string) => {
    const num = Math.min(100, Math.max(0, Number(val) || 0));
    setGrades((prev) =>
      prev.map((g) => {
        if (g.studentId !== studentId) return g;
        const newScores = { ...g.scores, [componentId]: num };

        // Kalkulasi ulang nilai akhir berdasarkan bobot komponen
        let totalWeight = 0;
        let weightedSum = 0;
        components.forEach((c) => {
          const s = newScores[c.id] ?? 0;
          weightedSum += s * (c.weight / 100);
          totalWeight += c.weight;
        });
        const finalScore = Number(weightedSum.toFixed(1));
        const letterGrade =
          finalScore >= 90 ? 'A' : finalScore >= 80 ? 'B' : finalScore >= 75 ? 'C' : 'D';
        const isPassed = finalScore >= 75;

        return { ...g, scores: newScores, finalScore, letterGrade, isPassed };
      })
    );
  };

  const handleSaveBulk = async () => {
    if (!selectedClassId || !selectedSubjectId || grades.length === 0) return;
    setIsSaving(true);
    setSuccessMessage(null);
    setErrorMessage(null);
    try {
      const res = await guruApi.updateBulkGrades(selectedClassId, selectedSubjectId, grades);
      setSuccessMessage(res.message);
      setTimeout(() => setSuccessMessage(null), 4000);
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Gagal menyimpan buku nilai.';
      setErrorMessage(msg);
    } finally {
      setIsSaving(false);
    }
  };

  const activeSubject = subjects.find((s) => s.id === selectedSubjectId);
  const activeClass = availableClasses.find((c) => c.id === selectedClassId);

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-6 rounded-2xl border border-slate-200 shadow-xs">
        <div>
          <div className="flex items-center gap-2 mb-2">
            <Link
              href="/portal/guru"
              className="inline-flex items-center gap-1.5 text-xs text-slate-500 hover:text-slate-900 font-medium transition-colors"
            >
              <ArrowLeft className="h-4 w-4" />
              <span>Kembali ke Beranda</span>
            </Link>
          </div>
          <h1 className="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2">
            <FileText className="h-6 w-6 text-brand-blue" />
            <span>Buku Nilai Mata Pelajaran</span>
          </h1>
          <p className="text-xs sm:text-sm text-slate-500 mt-1">
            Pengelolaan komponen asesmen formatif, sumatif, dan input nilai massal siswa.
          </p>
        </div>

        <Button
          onClick={handleSaveBulk}
          isLoading={isSaving}
          disabled={!selectedSubjectId || !selectedClassId || grades.length === 0}
          size="sm"
          className="gap-2 font-bold px-5"
        >
          <Save className="h-4 w-4" />
          <span>Simpan Perubahan Nilai</span>
        </Button>
      </div>

      {successMessage && (
        <div className="p-4 bg-emerald-50 border border-emerald-200 rounded-xl text-status-success text-xs sm:text-sm font-bold flex items-center gap-2 animate-in fade-in duration-200">
          <CheckCircle2 className="h-5 w-5 shrink-0" />
          <span>{successMessage}</span>
        </div>
      )}

      {errorMessage && (
        <div className="p-4 bg-red-50 border border-red-200 rounded-xl text-status-danger text-xs sm:text-sm font-bold flex items-center justify-between gap-2 animate-in fade-in duration-200">
          <div className="flex items-center gap-2">
            <CheckCircle2 className="h-5 w-5 shrink-0 rotate-45 text-red-500" />
            <span>{errorMessage}</span>
          </div>
          <button onClick={() => setErrorMessage(null)} className="text-red-400 hover:text-red-700">
            ×
          </button>
        </div>
      )}

      {!isLoadingAssignments && subjects.length === 0 && (
        <div className="p-4 bg-amber-50 border border-amber-200 rounded-xl text-amber-800 text-xs sm:text-sm font-medium flex items-center gap-2">
          <AlertCircle className="h-5 w-5 shrink-0 text-amber-600" />
          <span>Anda belum memiliki penugasan jadwal mengajar aktif. Buku nilai tidak dapat diakses atau diubah.</span>
        </div>
      )}

      {/* Subject & Class Selectors & Components Info */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
        <Card className="border-slate-200">
          <CardHeader className="pb-3">
            <CardDescription className="text-xs font-bold">Mata Pelajaran <span className="text-red-500">*</span></CardDescription>
            <select
              value={selectedSubjectId}
              onChange={(e) => setSelectedSubjectId(e.target.value)}
              disabled={isLoadingAssignments || subjects.length === 0}
              className="w-full h-10 px-3 rounded-lg border border-slate-300 bg-white text-xs sm:text-sm font-medium focus:outline-none focus:ring-2 focus:ring-brand-blue mt-1 disabled:bg-slate-100 disabled:text-slate-400"
            >
              {subjects.length === 0 ? (
                <option value="">Tidak ada mata pelajaran</option>
              ) : (
                subjects.map((sub) => (
                  <option key={sub.id} value={sub.id}>
                    {sub.name}
                  </option>
                ))
              )}
            </select>
          </CardHeader>
        </Card>

        <Card className="border-slate-200">
          <CardHeader className="pb-3">
            <CardDescription className="text-xs font-bold">Pilih Kelas <span className="text-red-500">*</span></CardDescription>
            <select
              value={selectedClassId}
              onChange={(e) => setSelectedClassId(e.target.value)}
              disabled={!selectedSubjectId || availableClasses.length === 0}
              className="w-full h-10 px-3 rounded-lg border border-slate-300 bg-white text-xs sm:text-sm font-medium focus:outline-none focus:ring-2 focus:ring-brand-blue mt-1 disabled:bg-slate-100 disabled:text-slate-400"
            >
              {availableClasses.length === 0 ? (
                <option value="">Tidak ada kelas untuk mapel ini</option>
              ) : (
                availableClasses.map((c) => (
                  <option key={c.id} value={c.id}>
                    {c.name}
                  </option>
                ))
              )}
            </select>
          </CardHeader>
        </Card>

        <Card className="border-slate-200 md:col-span-2">
          <CardHeader className="pb-2">
            <CardDescription className="text-xs font-bold">Komponen Penilaian & Bobot Nilai</CardDescription>
            <div className="flex items-center gap-2 flex-wrap pt-1">
              {components.length === 0 ? (
                <span className="text-xs text-slate-400">Tidak ada komponen penilaian</span>
              ) : (
                components.map((comp) => (
                  <Badge key={comp.id} variant="outline" className="px-2.5 py-1 text-xs bg-slate-50 text-slate-700">
                    <span className="font-bold">{comp.name} ({comp.code})</span>: {comp.weight}%
                  </Badge>
                ))
              )}
              <Badge className="bg-blue-50 text-brand-blue border-blue-200 text-xs px-2.5 py-1 font-bold">
                KKTP: 75
              </Badge>
            </div>
          </CardHeader>
        </Card>
      </div>

      {/* Grade Table */}
      {isLoadingGrades || isLoadingComponents || isLoadingAssignments ? (
        <div className="min-h-[250px] flex items-center justify-center bg-white rounded-2xl border border-slate-200">
          <LoadingSpinner label="Memuat buku nilai..." size="lg" />
        </div>
      ) : grades.length === 0 ? (
        <EmptyState
          title="Tidak Ada Data Nilai"
          description="Belum ada siswa yang terdaftar di kelas binaan ini."
          icon={<FileText className="h-8 w-8 text-slate-400" />}
        />
      ) : (
        <div className="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-xs">
          <div className="overflow-x-auto">
            <table className="w-full text-xs text-left">
              <thead className="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold uppercase text-[10px]">
                <tr>
                  <th className="p-3 w-10 text-center">#</th>
                  <th className="p-3 min-w-[180px]">Nama Siswa</th>
                  {components.map((c) => (
                    <th key={c.id} className="p-3 text-center min-w-[90px]">
                      {c.code} ({c.weight}%)
                    </th>
                  ))}
                  <th className="p-3 text-center min-w-[80px]">Nilai Akhir</th>
                  <th className="p-3 text-center min-w-[60px]">Predikat</th>
                  <th className="p-3 text-center min-w-[90px]">Status</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {grades.map((st, idx) => (
                  <tr key={st.studentId} className="hover:bg-slate-50/60 transition-colors">
                    <td className="p-3 text-center text-slate-400 font-medium">{idx + 1}</td>
                    <td className="p-3">
                      <div className="font-bold text-slate-900">{st.studentName}</div>
                      <div className="text-[10px] text-slate-400">NISN: {st.nisn}</div>
                    </td>
                    {components.map((c) => (
                      <td key={c.id} className="p-3 text-center">
                        <Input
                          type="number"
                          min={0}
                          max={100}
                          value={st.scores[c.id] ?? ''}
                          onChange={(e) => handleScoreChange(st.studentId, c.id, e.target.value)}
                          className="h-8 w-16 text-center text-xs font-bold mx-auto"
                        />
                      </td>
                    ))}
                    <td className="p-3 text-center font-black text-sm text-slate-900">
                      {st.finalScore}
                    </td>
                    <td className="p-3 text-center font-bold text-xs text-brand-blue">
                      {st.letterGrade}
                    </td>
                    <td className="p-3 text-center">
                      <Badge
                        className={
                          st.isPassed
                            ? 'bg-emerald-50 text-status-success border-emerald-200'
                            : 'bg-red-50 text-status-danger border-red-200'
                        }
                      >
                        {st.isPassed ? 'Tuntas' : 'Remidi'}
                      </Badge>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}
    </div>
  );
}
