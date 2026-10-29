'use client';

import React, { useState, useEffect } from 'react';
import Link from 'next/link';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { 
  ArrowLeft, 
  CheckCircle2, 
  Clock, 
  AlertCircle, 
  Download, 
  FileText, 
  ChevronLeft, 
  ChevronRight, 
  Send, 
  Award,
  ExternalLink,
  MessageSquare,
  UserCheck
} from 'lucide-react';
import { guruApi, TeacherAssignmentSubmission } from '@/features/portal/guru';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { LoadingSpinner } from '@/components/shared/loading-spinner';
import { EmptyState } from '@/components/shared/empty-state';

export default function TeacherSpeedGraderPage({ params }: { params: { id: string } }) {
  const assignmentId = params.id;
  const queryClient = useQueryClient();

  const [activeStudentIndex, setActiveStudentIndex] = useState(0);
  const [scoreInput, setScoreInput] = useState<string>('');
  const [feedbackInput, setFeedbackInput] = useState<string>('');
  const [saveSuccessMsg, setSaveSuccessMsg] = useState<string | null>(null);

  // Fetch submissions
  const { data: rawSubmissions = [], isLoading, isError, refetch } = useQuery({
    queryKey: ['assignment-submissions', assignmentId],
    queryFn: () => guruApi.getAssignmentSubmissions(assignmentId),
  });

  const submissions = Array.isArray(rawSubmissions) ? rawSubmissions : [];
  const currentSubmission: TeacherAssignmentSubmission | undefined = submissions[activeStudentIndex];

  // Sync inputs when active student changes
  useEffect(() => {
    if (currentSubmission) {
      setScoreInput(currentSubmission.score !== undefined && currentSubmission.score !== null ? String(currentSubmission.score) : '');
      setFeedbackInput(currentSubmission.feedback || '');
      setSaveSuccessMsg(null);
    }
  }, [currentSubmission]);

  const gradeMutation = useMutation({
    mutationFn: (variables: { submissionId: string; score: number; feedback?: string }) =>
      guruApi.gradeSubmission(variables.submissionId, variables.score, variables.feedback),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['assignment-submissions', assignmentId] });
      setSaveSuccessMsg('Nilai dan catatan berhasil disimpan!');
      setTimeout(() => setSaveSuccessMsg(null), 3000);
    },
  });

  const handleSaveGrade = (e: React.FormEvent) => {
    e.preventDefault();
    if (!currentSubmission) return;

    const numScore = parseFloat(scoreInput);
    if (isNaN(numScore) || numScore < 0 || numScore > 100) {
      alert('Mohon masukkan nilai yang valid antara 0 dan 100.');
      return;
    }

    gradeMutation.mutate({
      submissionId: currentSubmission.id,
      score: numScore,
      feedback: feedbackInput,
    });
  };

  const gradedCount = submissions.filter((s) => s.status === 'graded' || s.score !== null).length;

  if (isLoading) {
    return (
      <div className="py-20 flex justify-center bg-white rounded-xl border border-slate-200">
        <LoadingSpinner label="Memuat lembar pengumpulan siswa..." size="lg" />
      </div>
    );
  }

  if (isError) {
    return (
      <Card className="border-red-200 bg-red-50/50">
        <CardContent className="p-8 text-center space-y-3">
          <AlertCircle className="h-10 w-10 text-status-danger mx-auto" />
          <h2 className="text-base font-bold text-slate-900">Gagal Memuat Submisi Tugas</h2>
          <p className="text-xs text-slate-600">Terjadi gangguan saat memuat data dari server.</p>
          <Button size="sm" onClick={() => refetch()}>Coba Lagi</Button>
        </CardContent>
      </Card>
    );
  }

  if (submissions.length === 0) {
    return (
      <div className="space-y-4">
        <Link href="/portal/guru/tugas">
          <Button variant="outline" size="sm" className="gap-2 text-xs">
            <ArrowLeft className="h-4 w-4" />
            Kembali ke Daftar Tugas
          </Button>
        </Link>
        <div className="bg-white rounded-xl border border-slate-200 p-12">
          <EmptyState
            icon={<FileText className="h-6 w-6" />}
            title="Belum Ada Pengumpulan"
            description="Belum ada siswa yang mengumpulkan tugas ini."
          />
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-4 text-slate-900">
      {/* Top Bar SpeedGrader */}
      <div className="bg-white p-3.5 rounded-xl border border-slate-200 shadow-xs flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <Link href="/portal/guru/tugas">
            <Button variant="ghost" size="sm" className="h-8 w-8 p-0">
              <ArrowLeft className="h-4 w-4" />
            </Button>
          </Link>
          <div>
            <div className="flex items-center gap-2">
              <h1 className="font-bold text-sm text-slate-900">Penilaian Cepat Submisi (SpeedGrader)</h1>
              <Badge variant="outline" className="text-[10px] bg-blue-50 text-brand-blue border-blue-200">
                Tugas ID: {assignmentId}
              </Badge>
            </div>
            <div className="text-[11px] text-slate-500">
              Progres: <strong>{gradedCount}</strong> dari {submissions.length} siswa dinilai ({Math.round((gradedCount / submissions.length) * 100)}%)
            </div>
          </div>
        </div>

        {/* Navigator Siswa (Next / Prev) */}
        <div className="flex items-center gap-2">
          <Button
            size="sm"
            variant="outline"
            disabled={activeStudentIndex === 0}
            onClick={() => setActiveStudentIndex((prev) => Math.max(0, prev - 1))}
            className="h-8 gap-1 text-xs"
          >
            <ChevronLeft className="h-4 w-4" />
            Sebelumnya
          </Button>

          <span className="text-xs font-semibold px-2 text-slate-700">
            {activeStudentIndex + 1} / {submissions.length}
          </span>

          <Button
            size="sm"
            variant="outline"
            disabled={activeStudentIndex === submissions.length - 1}
            onClick={() => setActiveStudentIndex((prev) => Math.min(submissions.length - 1, prev + 1))}
            className="h-8 gap-1 text-xs"
          >
            Berikutnya
            <ChevronRight className="h-4 w-4" />
          </Button>
        </div>
      </div>

      {/* Main Area: Preview Berkas (Kiri) & Form Nilai (Kanan) */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
        {/* Kolom Kiri: Pratinjau Berkas & Submisi */}
        <div className="lg:col-span-2">
          <Card className="border-slate-200 bg-white shadow-xs h-full flex flex-col justify-between">
            <CardHeader className="pb-3 border-b border-slate-100">
              <div className="flex items-start justify-between">
                <div>
                  <CardTitle className="text-base font-bold text-slate-900">
                    {currentSubmission?.studentName}
                  </CardTitle>
                  <CardDescription className="text-xs text-slate-500">
                    NISN: {currentSubmission?.nisn} • Waktu Kumpul: {currentSubmission?.submittedAt}
                  </CardDescription>
                </div>

                <Badge className={currentSubmission?.score !== null && currentSubmission?.score !== undefined ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'}>
                  {currentSubmission?.score !== null && currentSubmission?.score !== undefined ? `Nilai: ${currentSubmission?.score}` : 'Belum Dinilai'}
                </Badge>
              </div>
            </CardHeader>

            <CardContent className="pt-4 flex-1 flex flex-col justify-center items-center p-8 bg-slate-50/50 rounded-b-xl min-h-[380px]">
              <div className="w-full max-w-md bg-white p-6 rounded-xl border border-slate-200 shadow-xs text-center space-y-3">
                <FileText className="h-12 w-12 text-brand-blue mx-auto" />
                <div>
                  <div className="font-bold text-sm text-slate-900">
                    {currentSubmission?.notes || 'Dokumen_Tugas_Siswa.pdf'}
                  </div>
                  <div className="text-[11px] text-slate-500 mt-0.5">
                    Berkas submisi tugas digital siswa
                  </div>
                </div>

                {currentSubmission?.fileUrl && (
                  <div className="pt-2">
                    <a
                      href={currentSubmission.fileUrl}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="inline-flex items-center gap-1.5 text-xs text-brand-blue hover:underline font-medium"
                    >
                      <ExternalLink className="h-3.5 w-3.5" />
                      Buka Tautan Lampiran Tugas
                    </a>
                  </div>
                )}

                <div className="pt-2">
                  <Button size="sm" variant="outline" className="text-xs gap-1.5">
                    <Download className="h-3.5 w-3.5" />
                    Unduh Lembar Jawaban Siswa
                  </Button>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>

        {/* Kolom Kanan: Panel Penilaian Guru */}
        <div className="lg:col-span-1">
          <Card className="border-slate-200 bg-white shadow-xs">
            <CardHeader className="pb-3 border-b border-slate-100">
              <CardTitle className="text-base font-bold text-slate-900 flex items-center gap-2">
                <Award className="h-4 w-4 text-brand-blue" />
                Formulir Penilaian
              </CardTitle>
              <CardDescription className="text-xs text-slate-500">
                Berikan skor dan catatan feedback kepada siswa bersangkutan.
              </CardDescription>
            </CardHeader>

            <CardContent className="pt-4">
              <form onSubmit={handleSaveGrade} className="space-y-4 text-xs">
                {saveSuccessMsg && (
                  <div className="p-3 bg-emerald-50 border border-emerald-200 rounded-md text-emerald-800 flex items-center gap-2">
                    <CheckCircle2 className="h-4 w-4 text-emerald-600 shrink-0" />
                    <span>{saveSuccessMsg}</span>
                  </div>
                )}

                <div className="space-y-1.5">
                  <label className="font-semibold text-slate-700">Skor / Nilai (0 - 100):</label>
                  <Input
                    type="number"
                    min="0"
                    max="100"
                    step="1"
                    placeholder="Contoh: 88"
                    value={scoreInput}
                    onChange={(e) => setScoreInput(e.target.value)}
                    required
                    className="text-base font-bold text-brand-blue h-11"
                  />
                </div>

                <div className="space-y-1.5">
                  <label className="font-semibold text-slate-700">Catatan Feedback Guru:</label>
                  <Textarea
                    placeholder="Tuliskan evaluasi, koreksi, atau apresiasi untuk pekerjaan siswa..."
                    rows={6}
                    value={feedbackInput}
                    onChange={(e) => setFeedbackInput(e.target.value)}
                    className="text-xs"
                  />
                </div>

                <Button
                  type="submit"
                  disabled={gradeMutation.isPending}
                  className="w-full bg-brand-blue text-white hover:bg-blue-800 text-xs gap-2 h-9"
                >
                  <Send className="h-3.5 w-3.5" />
                  {gradeMutation.isPending ? 'Menyimpan Nilai...' : 'Simpan Nilai Siswa'}
                </Button>
              </form>
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  );
}
