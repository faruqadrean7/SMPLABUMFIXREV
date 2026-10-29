'use client';

import React, { useState } from 'react';
import Link from 'next/link';
import {
  useTeacherAssignments,
  useAssignmentSubmissions,
  useTeacherAssignmentsSummary,
  guruApi,
  TeacherAssignmentItem,
  TeacherAssignmentSubmission,
} from '@/features/portal/guru';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { LoadingSpinner } from '@/components/shared/loading-spinner';
import { EmptyState } from '@/components/shared/empty-state';
import {
  Award,
  ArrowLeft,
  Plus,
  Clock,
  CheckCircle2,
  FileCheck2,
  Users,
  Eye,
  Save,
  X,
  ExternalLink,
  AlertCircle,
} from 'lucide-react';

export default function TeacherAssignmentsPage() {
  const { data: initialAssignments = [], isLoading } = useTeacherAssignments();
  const {
    subjects,
    getClassesForSubject,
    isLoading: isLoadingAssignments,
  } = useTeacherAssignmentsSummary();

  const [assignments, setAssignments] = useState<TeacherAssignmentItem[]>([]);
  const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
  const [selectedAssignmentId, setSelectedAssignmentId] = useState<string | null>(null);

  // Form Buat Tugas Baru
  const [title, setTitle] = useState('');
  const [selectedSubjectId, setSelectedSubjectId] = useState<string>('');
  const [selectedClassId, setSelectedClassId] = useState<string>('');
  const [dueDate, setDueDate] = useState('');
  const [description, setDescription] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [createModalError, setCreateModalError] = useState<string | null>(null);

  React.useEffect(() => {
    if (initialAssignments.length > 0) {
      setAssignments(initialAssignments);
    }
  }, [initialAssignments]);

  // Available classes for selected subject
  const availableClasses = React.useMemo(() => {
    return getClassesForSubject(selectedSubjectId);
  }, [getClassesForSubject, selectedSubjectId]);

  // Auto-select subject if 1 or not yet selected
  React.useEffect(() => {
    if (subjects.length > 0) {
      if (!selectedSubjectId || !subjects.some((s) => s.id === selectedSubjectId)) {
        setSelectedSubjectId(subjects[0].id);
      }
    } else {
      setSelectedSubjectId('');
    }
  }, [subjects, selectedSubjectId]);

  // Keep class aligned with availableClasses for this subject
  React.useEffect(() => {
    if (availableClasses.length > 0) {
      if (!selectedClassId || !availableClasses.some((c) => c.id === selectedClassId)) {
        setSelectedClassId(availableClasses[0].id);
      }
    } else {
      setSelectedClassId('');
    }
  }, [availableClasses, selectedClassId]);

  const { data: initialSubmissions = [], isLoading: isLoadingSubmissions } = useAssignmentSubmissions(
    selectedAssignmentId || ''
  );
  const [submissions, setSubmissions] = useState<TeacherAssignmentSubmission[]>([]);

  React.useEffect(() => {
    if (initialSubmissions.length > 0) {
      setSubmissions(initialSubmissions);
    }
  }, [initialSubmissions]);

  const handleCreateSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!title.trim() || !dueDate || !selectedSubjectId || !selectedClassId) return;

    setIsSubmitting(true);
    setCreateModalError(null);
    setErrorMessage(null);
    try {
      const activeSubject = subjects.find((s) => s.id === selectedSubjectId);
      const activeClass = availableClasses.find((c) => c.id === selectedClassId);
      const newAsg = await guruApi.createAssignment({
        title,
        subjectId: selectedSubjectId,
        subjectName: activeSubject?.name || 'Mata Pelajaran',
        classId: selectedClassId,
        className: activeClass?.name || 'Kelas',
        dueDate,
        description,
      });
      setAssignments((prev) => [newAsg, ...prev]);
      setSuccessMessage('Tugas KBM baru berhasil diterbitkan.');
      setIsCreateModalOpen(false);
      setTitle('');
      setDueDate('');
      setDescription('');
      setTimeout(() => setSuccessMessage(null), 4000);
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Gagal menerbitkan tugas KBM.';
      setCreateModalError(msg);
      setErrorMessage(msg);
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleScoreChange = (submissionId: string, scoreVal: string) => {
    const score = Number(scoreVal) || 0;
    setSubmissions((prev) =>
      prev.map((s) => (s.id === submissionId ? { ...s, score } : s))
    );
  };

  const handleFeedbackChange = (submissionId: string, feedback: string) => {
    setSubmissions((prev) =>
      prev.map((s) => (s.id === submissionId ? { ...s, feedback } : s))
    );
  };

  const handleSaveGrade = async (submission: TeacherAssignmentSubmission) => {
    if (submission.score === undefined) return;
    try {
      await guruApi.gradeSubmission(submission.id, submission.score, submission.feedback);
      setSubmissions((prev) =>
        prev.map((s) => (s.id === submission.id ? { ...s, status: 'graded' } : s))
      );
      setSuccessMessage(`Nilai untuk ${submission.studentName} berhasil disimpan.`);
      setTimeout(() => setSuccessMessage(null), 3000);
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Gagal menyimpan nilai tugas.';
      setErrorMessage(msg);
    }
  };

  const activeAssignment = assignments.find((a) => a.id === selectedAssignmentId);

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
            <Award className="h-6 w-6 text-brand-blue" />
            <span>Tugas KBM & Penilaian</span>
          </h1>
          <p className="text-xs sm:text-sm text-slate-500 mt-1">
            Buat penugasan siswa, pantau pengumpulan berkas, dan berikan penilaian formatif.
          </p>
        </div>

        <Button
          onClick={() => setIsCreateModalOpen(true)}
          disabled={!isLoadingAssignments && subjects.length === 0}
          size="sm"
          className="gap-1.5 font-bold"
        >
          <Plus className="h-4 w-4" />
          <span>Buat Tugas Baru</span>
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
          <span>Anda belum memiliki penugasan jadwal mengajar aktif. Anda tidak dapat membuat tugas KBM baru.</span>
        </div>
      )}

      {/* Assignments List */}
      {isLoading || isLoadingAssignments ? (
        <div className="min-h-[250px] flex items-center justify-center bg-white rounded-2xl border border-slate-200">
          <LoadingSpinner label="Memuat daftar tugas..." size="lg" />
        </div>
      ) : assignments.length === 0 ? (
        <EmptyState
          title="Belum Ada Tugas KBM"
          description="Anda belum menerbitkan tugas belajar untuk kelas binaan Anda."
          icon={<Award className="h-8 w-8 text-slate-400" />}
          actionLabel="Buat Tugas Baru"
          onAction={() => setIsCreateModalOpen(true)}
        />
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {assignments.map((asg) => (
            <Card
              key={asg.id}
              className="border-slate-200 hover:border-brand-blue hover:shadow-xs transition-all flex flex-col justify-between"
            >
              <div>
                <CardHeader className="pb-2">
                  <div className="flex items-center justify-between text-xs text-slate-400 mb-1">
                    <Badge variant="outline" className="text-[10px] bg-slate-50 text-slate-600 font-bold">
                      {asg.className}
                    </Badge>
                    <span className="flex items-center gap-1 text-[11px] text-amber-700 font-medium">
                      <Clock className="h-3 w-3" />
                      Tenggat: {asg.dueDate}
                    </span>
                  </div>
                  <CardTitle className="text-base font-bold text-slate-900 leading-snug">
                    {asg.title}
                  </CardTitle>
                  <CardDescription className="text-xs text-slate-500 mt-1 line-clamp-2">
                    {asg.description}
                  </CardDescription>
                </CardHeader>

                <CardContent className="pt-2 text-xs space-y-2">
                  <div className="bg-slate-50 p-3 rounded-xl border border-slate-100 grid grid-cols-3 text-center gap-2">
                    <div>
                      <span className="text-[10px] text-slate-400 font-bold block uppercase">Total</span>
                      <span className="font-bold text-slate-800">{asg.totalStudents} Siswa</span>
                    </div>
                    <div>
                      <span className="text-[10px] text-slate-400 font-bold block uppercase">Terkumpul</span>
                      <span className="font-bold text-brand-blue">{asg.submittedCount}</span>
                    </div>
                    <div>
                      <span className="text-[10px] text-slate-400 font-bold block uppercase">Sudah Dinilai</span>
                      <span className="font-bold text-status-success">{asg.gradedCount}</span>
                    </div>
                  </div>
                </CardContent>
              </div>

              <div className="p-4 pt-0 mt-2 border-t border-slate-100 flex items-center justify-between">
                <span className="text-[11px] text-slate-400 font-medium">
                  {asg.submittedCount - asg.gradedCount} perlu dinilai
                </span>
                <Button
                  size="sm"
                  onClick={() => setSelectedAssignmentId(asg.id)}
                  className="gap-1.5 text-xs font-bold"
                >
                  <Eye className="h-3.5 w-3.5" />
                  <span>Koreksi & Nilai</span>
                </Button>
              </div>
            </Card>
          ))}
        </div>
      )}

      {/* Modal Buat Tugas Baru */}
      {isCreateModalOpen && (
        <div className="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl max-w-lg w-full p-6 shadow-xl space-y-4 animate-in fade-in zoom-in-95 duration-200">
            <div className="flex items-center justify-between pb-3 border-b border-slate-100">
              <h3 className="font-bold text-base text-slate-900 flex items-center gap-2">
                <Award className="h-5 w-5 text-brand-blue" />
                <span>Buat Penugasan Siswa</span>
              </h3>
              <button
                onClick={() => setIsCreateModalOpen(false)}
                className="text-slate-400 hover:text-slate-600 p-1 rounded-lg"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            <form onSubmit={handleCreateSubmit} className="space-y-4">
              {createModalError && (
                <div className="p-3 bg-red-50 border border-red-200 rounded-lg text-status-danger text-xs font-bold flex items-center gap-2">
                  <X className="h-4 w-4 shrink-0" />
                  <span>{createModalError}</span>
                </div>
              )}

              <div>
                <label className="block text-xs font-bold text-slate-700 mb-1">
                  Mata Pelajaran <span className="text-red-500">*</span>
                </label>
                <select
                  value={selectedSubjectId}
                  onChange={(e) => setSelectedSubjectId(e.target.value)}
                  disabled={subjects.length === 0}
                  className="w-full h-9 px-3 rounded-lg border border-slate-300 bg-white text-xs font-medium focus:outline-none focus:ring-2 focus:ring-brand-blue disabled:bg-slate-100 disabled:text-slate-400"
                  required
                >
                  {subjects.length === 0 ? (
                    <option value="">Tidak ada mata pelajaran</option>
                  ) : (
                    subjects.map((s) => (
                      <option key={s.id} value={s.id}>
                        {s.name}
                      </option>
                    ))
                  )}
                </select>
              </div>

              <div>
                <label className="block text-xs font-bold text-slate-700 mb-1">
                  Judul Tugas <span className="text-red-500">*</span>
                </label>
                <Input
                  type="text"
                  placeholder="Contoh: Lembar Kerja Mandiri 4: Penerapan Teorema Pythagoras"
                  value={title}
                  onChange={(e) => setTitle(e.target.value)}
                  className="h-9 text-xs"
                  required
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-bold text-slate-700 mb-1">
                    Kelas Target <span className="text-red-500">*</span>
                  </label>
                  <select
                    value={selectedClassId}
                    onChange={(e) => setSelectedClassId(e.target.value)}
                    disabled={!selectedSubjectId || availableClasses.length === 0}
                    className="w-full h-9 px-2.5 rounded-lg border border-slate-300 text-xs bg-white focus:outline-none focus:ring-2 focus:ring-brand-blue disabled:bg-slate-100 disabled:text-slate-400"
                    required
                  >
                    {availableClasses.length === 0 ? (
                      <option value="">Tidak ada kelas untuk mapel ini</option>
                    ) : (
                      availableClasses.map((c) => (
                        <option key={c.id} value={c.id}>{c.name}</option>
                      ))
                    )}
                  </select>
                </div>
                <div>
                  <label className="block text-xs font-bold text-slate-700 mb-1">
                    Batas Waktu (Deadline) <span className="text-red-500">*</span>
                  </label>
                  <Input
                    type="datetime-local"
                    value={dueDate}
                    onChange={(e) => setDueDate(e.target.value)}
                    className="h-9 text-xs"
                    required
                  />
                </div>
              </div>

              <div>
                <label className="block text-xs font-bold text-slate-700 mb-1">
                  Instruksi & Kriteria Penilaian <span className="text-red-500">*</span>
                </label>
                <Textarea
                  placeholder="Tuliskan petunjuk pengerjaan tugas, format berkas yang diperbolehkan..."
                  value={description}
                  onChange={(e) => setDescription(e.target.value)}
                  className="text-xs h-24"
                  required
                />
              </div>

              <div className="flex justify-end gap-2 pt-3 border-t border-slate-100">
                <Button type="button" variant="outline" size="sm" onClick={() => setIsCreateModalOpen(false)}>
                  Batal
                </Button>
                <Button
                  type="submit"
                  size="sm"
                  isLoading={isSubmitting}
                  disabled={!title.trim() || !dueDate || !selectedSubjectId || !selectedClassId}
                  className="font-bold"
                >
                  Terbitkan Tugas
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Modal / Panel Koreksi & Beri Nilai Tugas */}
      {selectedAssignmentId && (
        <div className="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl max-w-2xl w-full p-6 shadow-xl space-y-4 max-h-[85vh] overflow-y-auto animate-in fade-in zoom-in-95 duration-200">
            <div className="flex items-center justify-between pb-3 border-b border-slate-100">
              <div>
                <h3 className="font-bold text-base text-slate-900">
                  Koreksi Pengumpulan: {activeAssignment?.title}
                </h3>
                <p className="text-xs text-slate-500 mt-0.5">
                  Kelas {activeAssignment?.className} • Batas: {activeAssignment?.dueDate}
                </p>
              </div>
              <button
                onClick={() => setSelectedAssignmentId(null)}
                className="text-slate-400 hover:text-slate-600 p-1 rounded-lg"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            {isLoadingSubmissions ? (
              <div className="min-h-[200px] flex items-center justify-center">
                <LoadingSpinner label="Memuat kiriman tugas siswa..." size="md" />
              </div>
            ) : submissions.length === 0 ? (
              <div className="py-8 text-center text-xs text-slate-500">
                Belum ada siswa yang mengumpulkan tugas ini.
              </div>
            ) : (
              <div className="space-y-4">
                {submissions.map((subm) => (
                  <div
                    key={subm.id}
                    className="p-4 bg-slate-50 rounded-xl border border-slate-200 space-y-3"
                  >
                    <div className="flex items-center justify-between">
                      <div>
                        <h4 className="font-bold text-sm text-slate-900">{subm.studentName}</h4>
                        <span className="text-[11px] text-slate-500">
                          Dikumpulkan: {subm.submittedAt}
                        </span>
                      </div>
                      <Badge
                        className={
                          subm.status === 'graded'
                            ? 'bg-emerald-50 text-status-success border-emerald-200'
                            : 'bg-amber-50 text-amber-700 border-amber-200'
                        }
                      >
                        {subm.status === 'graded' ? 'Sudah Dinilai' : 'Menunggu Nilai'}
                      </Badge>
                    </div>

                    {subm.fileUrl && (
                      <div className="text-xs">
                        <a
                          href={subm.fileUrl}
                          target="_blank"
                          rel="noreferrer"
                          className="inline-flex items-center gap-1.5 text-brand-blue font-bold hover:underline"
                        >
                          <ExternalLink className="h-3.5 w-3.5" />
                          <span>Buka Berkas Jawaban Siswa</span>
                        </a>
                      </div>
                    )}

                    <div className="grid grid-cols-1 sm:grid-cols-4 gap-3 pt-1 border-t border-slate-200">
                      <div className="sm:col-span-1">
                        <label className="block text-[11px] font-bold text-slate-700 mb-1">Nilai (0-100)</label>
                        <Input
                          type="number"
                          min={0}
                          max={100}
                          value={subm.score ?? ''}
                          onChange={(e) => handleScoreChange(subm.id, e.target.value)}
                          placeholder="Skor..."
                          className="h-8 text-xs font-bold"
                        />
                      </div>
                      <div className="sm:col-span-2">
                        <label className="block text-[11px] font-bold text-slate-700 mb-1">Catatan / Umpan Balik Guru</label>
                        <Input
                          type="text"
                          value={subm.feedback || ''}
                          onChange={(e) => handleFeedbackChange(subm.id, e.target.value)}
                          placeholder="Beri motivasi atau evaluasi..."
                          className="h-8 text-xs"
                        />
                      </div>
                      <div className="sm:col-span-1 flex items-end">
                        <Button
                          size="sm"
                          onClick={() => handleSaveGrade(subm)}
                          className="w-full h-8 text-xs font-bold gap-1"
                        >
                          <Save className="h-3.5 w-3.5" />
                          <span>Simpan</span>
                        </Button>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
