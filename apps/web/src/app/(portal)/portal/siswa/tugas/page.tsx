'use client';

import React, { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { 
  CheckSquare, 
  BookOpen, 
  Clock, 
  Upload, 
  FileText, 
  Link as LinkIcon, 
  CheckCircle2, 
  AlertTriangle, 
  Download, 
  ExternalLink,
  MessageSquare,
  Award,
  Filter,
  RotateCw
} from 'lucide-react';
import { portalApi } from '@/features/portal/portal-api';
import { StudentTaskItem } from '@/features/portal/types';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { LoadingSpinner } from '@/components/shared/loading-spinner';
import { EmptyState } from '@/components/shared/empty-state';

export default function StudentTugasPage() {
  const queryClient = useQueryClient();
  const [activeTab, setActiveTab] = useState<'tugas' | 'modul'>('tugas');
  const [statusFilter, setStatusFilter] = useState<'semua' | 'aktif' | 'dikumpulkan' | 'dinilai'>('semua');
  
  // Submit modal state
  const [selectedTask, setSelectedTask] = useState<StudentTaskItem | null>(null);
  const [uploadedFileName, setUploadedFileName] = useState<string>('');
  const [driveLink, setDriveLink] = useState<string>('');
  const [studentNote, setStudentNote] = useState<string>('');
  const [feedbackTask, setFeedbackTask] = useState<StudentTaskItem | null>(null);
  const [submitError, setSubmitError] = useState<string | null>(null);

  // Query real data from portalApi
  const { data: tasks = [], isLoading, isError, refetch } = useQuery({
    queryKey: ['student-tasks'],
    queryFn: () => portalApi.getStudentTasks(),
  });

  const submitMutation = useMutation({
    mutationFn: (variables: { taskId: string; payload: { file_name: string; drive_link?: string; notes?: string } }) =>
      portalApi.submitStudentTask(variables.taskId, variables.payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['student-tasks'] });
      setSelectedTask(null);
      setUploadedFileName('');
      setDriveLink('');
      setStudentNote('');
      setSubmitError(null);
    },
    onError: (err: any) => {
      setSubmitError(err?.message || 'Gagal mengumpulkan tugas. Silakan periksa berkas atau tautan pengerjaan Anda.');
    },
  });

  const safeTasks = Array.isArray(tasks) ? tasks : [];
  const filteredTasks = statusFilter === 'semua'
    ? safeTasks
    : safeTasks.filter((t) => t.status === statusFilter);

  const handleOpenSubmit = (task: StudentTaskItem) => {
    setSelectedTask(task);
    setUploadedFileName('');
    setDriveLink('');
    setStudentNote('');
    setSubmitError(null);
  };

  const handleConfirmSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedTask) return;

    submitMutation.mutate({
      taskId: selectedTask.id,
      payload: {
        file_name: uploadedFileName || 'Dokumen_Tugas.pdf',
        drive_link: driveLink || undefined,
        notes: studentNote || undefined,
      },
    });
  };

  return (
    <div className="space-y-6 text-slate-900">
      {/* Header Halaman */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-slate-200">
        <div>
          <h1 className="text-2xl font-bold tracking-tight text-slate-900 flex items-center gap-2.5">
            <CheckSquare className="h-6 w-6 text-brand-blue" />
            Tugas KBM & Modul Belajar
          </h1>
          <p className="text-xs text-slate-600 mt-1">
            Kumpulkan tugas mandiri harian, tinjau batas pengumpulan, dan akses materi ajar dari dewan guru.
          </p>
        </div>

        {/* Tab Selector */}
        <div className="flex items-center gap-2 bg-slate-100 p-1 rounded-lg border border-slate-200 self-start md:self-auto">
          <button
            onClick={() => setActiveTab('tugas')}
            className={`flex items-center gap-2 px-3 py-1.5 text-xs font-semibold rounded-md transition-colors ${
              activeTab === 'tugas' 
                ? 'bg-white text-brand-blue shadow-xs' 
                : 'text-slate-600 hover:text-slate-900'
            }`}
          >
            <CheckSquare className="h-3.5 w-3.5" />
            Tugas Mandiri ({tasks.length})
          </button>
          <button
            onClick={() => setActiveTab('modul')}
            className={`flex items-center gap-2 px-3 py-1.5 text-xs font-semibold rounded-md transition-colors ${
              activeTab === 'modul' 
                ? 'bg-white text-brand-blue shadow-xs' 
                : 'text-slate-600 hover:text-slate-900'
            }`}
          >
            <BookOpen className="h-3.5 w-3.5" />
            Modul & E-Book
          </button>
        </div>
      </div>

      {activeTab === 'tugas' && (
        <div className="space-y-4">
          {/* Filter Bar */}
          <div className="flex flex-wrap items-center justify-between gap-3 bg-white p-3.5 rounded-lg border border-slate-200 shadow-xs">
            <div className="flex items-center gap-2 text-xs font-medium text-slate-700">
              <Filter className="h-4 w-4 text-slate-400" />
              <span>Status Tugas:</span>
              <div className="flex gap-1.5 flex-wrap">
                {(['semua', 'aktif', 'dikumpulkan', 'dinilai'] as const).map((st) => (
                  <button
                    key={st}
                    onClick={() => setStatusFilter(st)}
                    className={`px-2.5 py-1 rounded text-[11px] font-medium transition-colors capitalize ${
                      statusFilter === st
                        ? 'bg-brand-blue text-white'
                        : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                    }`}
                  >
                    {st === 'semua' ? 'Semua' : st === 'aktif' ? 'Belum Dikumpulkan' : st === 'dikumpulkan' ? 'Terkirim' : 'Sudah Dinilai'}
                  </button>
                ))}
              </div>
            </div>

            <Button
              variant="outline"
              size="sm"
              onClick={() => refetch()}
              className="text-xs h-8 gap-1.5"
            >
              <RotateCw className="h-3.5 w-3.5" />
              Segarkan
            </Button>
          </div>

          {/* Loading State */}
          {isLoading && (
            <div className="py-12 bg-white rounded-lg border border-slate-200 flex justify-center">
              <LoadingSpinner label="Memuat tugas pembelajaran siswa..." />
            </div>
          )}

          {/* Error State */}
          {isError && (
            <Card className="border-red-200 bg-red-50/50">
              <CardContent className="p-6 text-center">
                <AlertTriangle className="h-8 w-8 text-status-danger mx-auto mb-2" />
                <h3 className="font-semibold text-slate-900">Gagal Memuat Daftar Tugas</h3>
                <p className="text-xs text-slate-600 mt-1 mb-4">Terjadi kendala jaringan saat menghubungi server.</p>
                <Button size="sm" onClick={() => refetch()}>Coba Lagi</Button>
              </CardContent>
            </Card>
          )}

          {/* Empty State */}
          {!isLoading && !isError && filteredTasks.length === 0 && (
            <div className="bg-white rounded-lg border border-slate-200 p-8">
              <EmptyState
                icon={<CheckSquare className="h-6 w-6" />}
                title="Tidak Ada Tugas"
                description={
                  statusFilter === 'semua'
                    ? 'Saat ini belum ada tugas mandiri yang diberikan oleh bapak/ibu guru.'
                    : `Tidak ada tugas dengan status "${statusFilter}".`
                }
              />
            </div>
          )}

          {/* Daftar Kartu Tugas */}
          {!isLoading && !isError && filteredTasks.length > 0 && (
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              {filteredTasks.map((task) => {
                const isOverdue = task.status === 'aktif' && new Date(task.dueDate) < new Date();
                return (
                  <Card key={task.id} className="border-slate-200 bg-white hover:border-blue-300 transition-all shadow-xs flex flex-col justify-between">
                    <CardHeader className="pb-3">
                      <div className="flex items-start justify-between gap-3">
                        <Badge variant="outline" className="text-[10px] font-semibold bg-blue-50 text-brand-blue border-blue-200">
                          {task.subjectName}
                        </Badge>
                        {task.status === 'aktif' && (
                          <Badge className={isOverdue ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800'}>
                            {isOverdue ? 'Terlambat' : 'Belum Dikumpulkan'}
                          </Badge>
                        )}
                        {task.status === 'dikumpulkan' && (
                          <Badge className="bg-blue-100 text-blue-800">
                            Menunggu Penilaian
                          </Badge>
                        )}
                        {task.status === 'dinilai' && (
                          <Badge className="bg-emerald-100 text-emerald-800 flex items-center gap-1">
                            <Award className="h-3 w-3" />
                            Nilai: {task.score}/{task.maxScore}
                          </Badge>
                        )}
                      </div>
                      <CardTitle className="text-base font-bold text-slate-900 mt-2 line-clamp-2">
                        {task.title}
                      </CardTitle>
                      <CardDescription className="text-xs text-slate-500">
                        Oleh: {task.teacherName}
                      </CardDescription>
                    </CardHeader>

                    <CardContent className="space-y-3 pt-0">
                      <p className="text-xs text-slate-600 line-clamp-3 bg-slate-50 p-2.5 rounded-md border border-slate-100">
                        {task.description}
                      </p>

                      <div className="flex items-center justify-between text-[11px] text-slate-500 pt-2 border-t border-slate-100">
                        <div className="flex items-center gap-1.5">
                          <Clock className="h-3.5 w-3.5 text-slate-400" />
                          <span>Tenggat: <strong className="text-slate-700">{task.dueDate} {task.dueTime || ''}</strong></span>
                        </div>
                      </div>

                      <div className="pt-2 flex items-center justify-end gap-2">
                        {task.status === 'dinilai' && (
                          <Button
                            size="sm"
                            variant="outline"
                            onClick={() => setFeedbackTask(task)}
                            className="text-xs gap-1.5 text-emerald-700 border-emerald-300 hover:bg-emerald-50"
                          >
                            <MessageSquare className="h-3.5 w-3.5" />
                            Lihat Catatan Guru
                          </Button>
                        )}

                        {task.status === 'aktif' && (
                          <Button
                            size="sm"
                            onClick={() => handleOpenSubmit(task)}
                            className="text-xs gap-1.5 bg-brand-blue text-white hover:bg-blue-800"
                          >
                            <Upload className="h-3.5 w-3.5" />
                            Kumpulkan Tugas
                          </Button>
                        )}

                        {task.status === 'dikumpulkan' && (
                          <div className="text-[11px] text-slate-500 italic flex items-center gap-1">
                            <CheckCircle2 className="h-3.5 w-3.5 text-blue-600" />
                            Terkirim pada {task.submission?.submitted_at || 'Baru saja'}
                          </div>
                        )}
                      </div>
                    </CardContent>
                  </Card>
                );
              })}
            </div>
          )}
        </div>
      )}

      {/* Tab Modul & E-Book */}
      {activeTab === 'modul' && (
        <Card className="border-slate-200 bg-white">
          <CardHeader>
            <CardTitle className="text-base font-bold text-slate-900">Perpustakaan Modul Digital & Bahan Ajar</CardTitle>
            <CardDescription className="text-xs text-slate-500">
              Unduh buku pegangan siswa dan materi kurikulum yang telah diunggah guru.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div className="p-4 bg-blue-50/50 rounded-lg border border-blue-100 flex items-center justify-between">
              <div className="flex items-center gap-3">
                <BookOpen className="h-8 w-8 text-brand-blue" />
                <div>
                  <div className="text-xs font-bold text-slate-900">Modul Kurikulum Merdeka Terpadu 2025/2026</div>
                  <div className="text-[11px] text-slate-600">SMP Laboratorium Universitas Negeri Malang</div>
                </div>
              </div>
              <Button size="sm" variant="outline" className="text-xs gap-1.5">
                <Download className="h-3.5 w-3.5" />
                Akses Katalog
              </Button>
            </div>
          </CardContent>
        </Card>
      )}

      {/* Modal Dialog Kumpul Tugas */}
      {selectedTask && (
        <div className="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-xl shadow-xl max-w-lg w-full p-6 space-y-4">
            <div className="flex items-start justify-between border-b border-slate-100 pb-3">
              <div>
                <h3 className="font-bold text-base text-slate-900">Pengumpulan Tugas Siswa</h3>
                <p className="text-xs text-slate-500">{selectedTask.title} ({selectedTask.subjectName})</p>
              </div>
              <button onClick={() => setSelectedTask(null)} className="text-slate-400 hover:text-slate-600">
                ✕
              </button>
            </div>

            <form onSubmit={handleConfirmSubmit} className="space-y-4">
              {submitError && (
                <div className="p-3 bg-red-50 border border-red-200 text-status-danger text-xs rounded-lg flex items-center gap-2">
                  <AlertTriangle className="h-4 w-4 shrink-0" />
                  <span>{submitError}</span>
                </div>
              )}

              <div className="space-y-1.5">
                <label className="text-xs font-semibold text-slate-700">Nama File / Dokumen Lampiran:</label>
                <Input
                  type="text"
                  placeholder="Contoh: Tugas_Informatika_AhmadFauzi.pdf"
                  value={uploadedFileName}
                  onChange={(e) => setUploadedFileName(e.target.value)}
                  className="text-xs"
                />
              </div>

              <div className="space-y-1.5">
                <label className="text-xs font-semibold text-slate-700">Tautan Cloud / Google Drive (Opsional):</label>
                <Input
                  type="url"
                  placeholder="https://drive.google.com/..."
                  value={driveLink}
                  onChange={(e) => setDriveLink(e.target.value)}
                  className="text-xs"
                />
              </div>

              <div className="space-y-1.5">
                <label className="text-xs font-semibold text-slate-700">Catatan Tambahan untuk Guru:</label>
                <Textarea
                  placeholder="Tulis pesan atau penjelasan singkat tugas Anda..."
                  rows={3}
                  value={studentNote}
                  onChange={(e) => setStudentNote(e.target.value)}
                  className="text-xs"
                />
              </div>

              <div className="flex justify-end gap-2 pt-2 border-t border-slate-100">
                <Button type="button" variant="outline" size="sm" onClick={() => setSelectedTask(null)}>
                  Batal
                </Button>
                <Button 
                  type="submit" 
                  size="sm" 
                  disabled={submitMutation.isPending}
                  className="bg-brand-blue text-white hover:bg-blue-800 text-xs"
                >
                  {submitMutation.isPending ? 'Mengirim...' : 'Kirim Tugas Sekarang'}
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Modal Dialog Feedback Guru */}
      {feedbackTask && (
        <div className="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-xl shadow-xl max-w-md w-full p-6 space-y-4">
            <div className="flex items-start justify-between border-b border-slate-100 pb-3">
              <div>
                <h3 className="font-bold text-base text-slate-900">Ulasan & Nilai Guru</h3>
                <p className="text-xs text-slate-500">{feedbackTask.title}</p>
              </div>
              <button onClick={() => setFeedbackTask(null)} className="text-slate-400 hover:text-slate-600">
                ✕
              </button>
            </div>

            <div className="p-4 bg-emerald-50 rounded-lg border border-emerald-200 text-center">
              <div className="text-xs text-emerald-800 font-medium">Perolehan Nilai Akhir</div>
              <div className="text-3xl font-black text-emerald-700 mt-1">
                {feedbackTask.score} <span className="text-sm font-normal text-emerald-600">/ {feedbackTask.maxScore}</span>
              </div>
            </div>

            <div className="space-y-1.5">
              <div className="text-xs font-semibold text-slate-700">Komentar Pembina:</div>
              <p className="text-xs text-slate-600 bg-slate-50 p-3 rounded-md border border-slate-200 italic">
                &ldquo;{feedbackTask.teacherFeedback || 'Tugas telah dinilai dengan baik. Terus tingkatkan prestasimu!'}&rdquo;
              </p>
            </div>

            <div className="flex justify-end pt-2">
              <Button size="sm" onClick={() => setFeedbackTask(null)} className="text-xs">
                Tutup
              </Button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
