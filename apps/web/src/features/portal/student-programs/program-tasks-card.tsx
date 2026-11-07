'use client';

import React, { useState } from 'react';
import Link from 'next/link';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import * as z from 'zod';
import {
  ClipboardList,
  Calendar,
  AlertTriangle,
  CheckCircle2,
  Clock,
  Send,
  X,
  RefreshCw,
  ExternalLink,
  Download,
  AlertCircle,
  FileCheck,
  ChevronRight,
} from 'lucide-react';
import {
  useProgramTasks,
  useSubmitProgramTask,
  ProgramTaskItem,
  ProgramTaskSubmissionItem,
} from '@/lib/use-student-data';
import { cn } from '@/lib/utils';

interface ProgramTasksCardProps {
  programType?: 'tahfidz' | 'icp' | 'inklusi' | 'reguler';
  title?: string;
  subtitle?: string;
  limit?: number;
  compact?: boolean;
  showAllHref?: string;
  className?: string;
}

const submissionSchema = z
  .object({
    link: z
      .string()
      .trim()
      .refine(
        (val) => !val || /^https?:\/\/.+/.test(val),
        'Tautan harus berupa tautan URL valid berawalan http:// atau https://'
      ),
    notes: z.string().trim().max(1000, 'Catatan maksimal 1000 karakter'),
  })
  .refine((data) => (data.link && data.link.length > 0) || (data.notes && data.notes.length > 0), {
    message: 'Harap isi salah satu: catatan jawaban atau tautan berkas tugas',
    path: ['notes'],
  });

type SubmissionFormValues = z.infer<typeof submissionSchema>;

export function ProgramTasksCard({
  programType,
  title = 'Tugas & Setoran Mandiri',
  subtitle = 'Kumpulkan rekaman, lembar kerja, atau tugas proyek program khusus',
  limit,
  compact = false,
  showAllHref,
  className,
}: ProgramTasksCardProps) {
  const { data: response, isLoading, isError, refetch } = useProgramTasks(
    limit ? { per_page: limit } : undefined
  );
  const tasks = response?.data || [];

  const [selectedTask, setSelectedTask] = useState<ProgramTaskItem | null>(null);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);

  const submitMutation = useSubmitProgramTask();

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<SubmissionFormValues>({
    resolver: zodResolver(submissionSchema),
    defaultValues: {
      link: '',
      notes: '',
    },
  });

  const handleOpenModal = (task: ProgramTaskItem) => {
    setSelectedTask(task);
    const existingSubmission = task.submissions?.[0];
    reset({
      link: existingSubmission?.link || '',
      notes: existingSubmission?.notes || '',
    });
  };

  const handleCloseModal = () => {
    if (submitMutation.isPending) return;
    setSelectedTask(null);
    reset();
  };

  const onSubmitTask = async (data: SubmissionFormValues) => {
    if (!selectedTask) return;

    // Generate random idempotency key for safe retries
    const idempotencyKey =
      typeof crypto !== 'undefined' && crypto.randomUUID
        ? crypto.randomUUID()
        : `sub-${selectedTask.id}-${Date.now()}-${Math.random().toString(36).slice(2, 7)}`;

    try {
      await submitMutation.mutateAsync({
        taskId: selectedTask.id,
        payload: {
          link: data.link || undefined,
          notes: data.notes || undefined,
        },
        idempotencyKey,
      });

      setSuccessMessage('Tugas berhasil dikumpulkan!');
      setTimeout(() => setSuccessMessage(null), 4000);
      handleCloseModal();
    } catch {
      // Handled by mutation error state
    }
  };

  return (
    <div
      className={cn(
        'bg-white border border-slate-200 rounded-xl p-4 sm:p-5 shadow-xs flex flex-col',
        className
      )}
    >
      {/* Header */}
      <div className="flex flex-wrap items-center justify-between gap-2 pb-3 border-b border-slate-100">
        <div className="flex items-center gap-2.5">
          <div className="w-8 h-8 rounded-lg bg-slate-900 text-white flex items-center justify-center shrink-0 shadow-2xs">
            <ClipboardList className="w-4 h-4" />
          </div>
          <div>
            <h3 className="text-sm font-extrabold text-slate-900 tracking-tight">{title}</h3>
            {subtitle && <p className="text-[11px] text-slate-500">{subtitle}</p>}
          </div>
        </div>

        {showAllHref && (
          <Link
            href={showAllHref}
            className="text-xs font-bold text-slate-700 hover:text-slate-900 inline-flex items-center gap-1 hover:underline"
          >
            Lihat Semua <ChevronRight className="w-3.5 h-3.5" />
          </Link>
        )}
      </div>

      {successMessage && (
        <div className="mt-3 p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-xs font-bold text-emerald-800 flex items-center gap-2">
          <CheckCircle2 className="w-4 h-4 text-emerald-700 shrink-0" />
          {successMessage}
        </div>
      )}

      {/* Content State */}
      <div className="mt-3.5 flex-1">
        {isLoading && (
          <div className="space-y-2.5 py-4">
            <div className="h-16 bg-slate-100 rounded-lg animate-pulse" />
            <div className="h-16 bg-slate-100 rounded-lg animate-pulse" />
          </div>
        )}

        {isError && (
          <div className="py-6 text-center rounded-lg border border-red-100 bg-red-50/50 p-4">
            <AlertCircle className="w-6 h-6 text-red-500 mx-auto mb-1.5" />
            <p className="text-xs font-bold text-red-800">Gagal memuat tugas program</p>
            <p className="text-[11px] text-red-600 mt-0.5">Terjadi kendala saat mengambil daftar tugas.</p>
            <button
              onClick={() => refetch()}
              className="mt-2.5 inline-flex items-center gap-1 px-3 py-1 text-xs font-bold text-red-700 bg-white border border-red-200 rounded-md hover:bg-red-50 cursor-pointer"
            >
              <RefreshCw className="w-3 h-3" /> Coba Lagi
            </button>
          </div>
        )}

        {!isLoading && !isError && tasks.length === 0 && (
          <div className="py-8 text-center rounded-lg border border-dashed border-slate-200 p-4">
            <FileCheck className="w-8 h-8 text-slate-400 mx-auto mb-2 opacity-60" />
            <p className="text-xs font-bold text-slate-700">Tidak Ada Tugas Aktif</p>
            <p className="text-[11px] text-slate-500 mt-1 max-w-sm mx-auto">
              Saat ini belum ada tugas atau setoran mandiri baru yang ditugaskan untuk program Anda.
            </p>
          </div>
        )}

        {!isLoading && !isError && tasks.length > 0 && (
          <div className="space-y-3">
            {tasks.map((task) => {
              const submission = task.submissions?.[0];
              const isSubmitted = Boolean(submission);
              const isGraded = submission?.status === 'dinilai';
              const isOverdue =
                task.due_at && !isSubmitted && new Date(task.due_at).getTime() < Date.now();

              return (
                <div
                  key={task.id}
                  className="p-3.5 rounded-lg border border-slate-200 hover:border-slate-300 transition-colors bg-white flex flex-col gap-2.5 shadow-2xs"
                >
                  <div className="flex flex-col sm:flex-row sm:items-start justify-between gap-2">
                    <div>
                      <div className="flex flex-wrap items-center gap-2">
                        <span className="text-xs font-bold text-slate-900">{task.title}</span>
                        {isOverdue && (
                          <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-800 border border-rose-200 inline-flex items-center gap-1">
                            <AlertTriangle className="w-3 h-3" /> Terlambat
                          </span>
                        )}
                      </div>
                      {task.instructions && (
                        <p className="text-xs text-slate-600 mt-1 line-clamp-2 leading-relaxed">
                          {task.instructions}
                        </p>
                      )}
                    </div>

                    <div className="flex items-center gap-1.5 shrink-0">
                      {isGraded ? (
                        <span className="px-2.5 py-1 rounded-md text-xs font-bold bg-emerald-100 text-emerald-900 border border-emerald-300 inline-flex items-center gap-1">
                          <CheckCircle2 className="w-3.5 h-3.5 text-emerald-700" />
                          Nilai: {submission?.score ?? '-'}/100
                        </span>
                      ) : isSubmitted ? (
                        <span className="px-2.5 py-1 rounded-md text-xs font-bold bg-blue-100 text-blue-900 border border-blue-300 inline-flex items-center gap-1">
                          <Clock className="w-3.5 h-3.5 text-blue-700" />
                          Menunggu Penilaian
                        </span>
                      ) : (
                        <span className="px-2 py-0.5 rounded text-[11px] font-bold bg-slate-100 text-slate-700 border border-slate-200">
                          Belum Dikumpulkan
                        </span>
                      )}
                    </div>
                  </div>

                  {/* Feedback or Grade note */}
                  {isGraded && submission?.feedback && (
                    <div className="p-2.5 rounded-lg bg-emerald-50/70 border border-emerald-200 text-xs text-emerald-900">
                      <span className="font-bold">Catatan Pembina: </span>
                      <span>{submission.feedback}</span>
                    </div>
                  )}

                  {/* Bottom details and submit action */}
                  <div className="pt-2 border-t border-slate-100 flex flex-wrap items-center justify-between gap-2 text-xs">
                    <div className="flex items-center gap-3 text-slate-500 text-[11px]">
                      {task.due_at && (
                        <span className="inline-flex items-center gap-1">
                          <Calendar className="w-3.5 h-3.5 text-slate-400" />
                          Batas: {new Date(task.due_at).toLocaleDateString('id-ID', {
                            day: 'numeric',
                            month: 'short',
                            year: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit',
                          })}
                        </span>
                      )}
                      {task.file?.url && (
                        <a
                          href={task.file.url}
                          download
                          className="inline-flex items-center gap-1 font-bold text-slate-700 hover:text-slate-900 hover:underline"
                        >
                          <Download className="w-3 h-3" /> Unduh Lampiran Soal
                        </a>
                      )}
                    </div>

                    <button
                      onClick={() => handleOpenModal(task)}
                      className={cn(
                        'px-3 py-1.5 rounded-lg text-xs font-bold transition-colors cursor-pointer inline-flex items-center gap-1.5 shadow-2xs',
                        isSubmitted
                          ? 'bg-slate-100 hover:bg-slate-200 text-slate-800 border border-slate-200'
                          : 'bg-blue-900 hover:bg-blue-950 text-white'
                      )}
                    >
                      <Send className="w-3 h-3" />
                      {isSubmitted ? 'Perbarui Submission' : 'Kumpulkan Tugas'}
                    </button>
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </div>

      {/* Submission Modal */}
      {selectedTask && (
        <div
          role="dialog"
          aria-labelledby="submit-modal-title"
          aria-modal="true"
          className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs animate-in fade-in duration-150"
        >
          <div className="bg-white rounded-2xl border border-slate-200 shadow-xl w-full max-w-lg overflow-hidden flex flex-col">
            {/* Modal Header */}
            <div className="p-4 sm:p-5 border-b border-slate-100 flex items-center justify-between">
              <div className="flex items-center gap-2.5">
                <div className="w-8 h-8 rounded-lg bg-blue-900 text-white flex items-center justify-center">
                  <Send className="w-4 h-4" />
                </div>
                <div>
                  <h3 id="submit-modal-title" className="text-sm font-extrabold text-slate-900">
                    Pengumpulan Tugas Program
                  </h3>
                  <p className="text-[11px] text-slate-500 truncate max-w-xs sm:max-w-sm">
                    {selectedTask.title}
                  </p>
                </div>
              </div>
              <button
                type="button"
                onClick={handleCloseModal}
                disabled={submitMutation.isPending}
                className="p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 cursor-pointer"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            {/* Modal Form */}
            <form onSubmit={handleSubmit(onSubmitTask)} className="p-4 sm:p-5 space-y-3.5">
              {submitMutation.isError && (
                <div className="p-3 rounded-lg bg-red-50 border border-red-200 text-xs text-red-700 flex items-start gap-2">
                  <AlertCircle className="w-4 h-4 text-red-600 shrink-0 mt-0.5" />
                  <span>
                    {(submitMutation.error as any)?.response?.data?.message ||
                      'Gagal mengumpulkan tugas. Silakan periksa kembali isian Anda.'}
                  </span>
                </div>
              )}

              <div>
                <label className="block text-xs font-bold text-slate-700 mb-1">
                  Tautan Berkas / Tugas (Google Drive, Rekaman, YouTube, dll)
                </label>
                <input
                  type="url"
                  placeholder="https://drive.google.com/file/..."
                  {...register('link')}
                  className={cn(
                    'w-full px-3 py-2 rounded-lg border text-xs text-slate-900 placeholder:text-slate-400 focus:outline-hidden focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500',
                    errors.link ? 'border-red-300 bg-red-50/30' : 'border-slate-300'
                  )}
                />
                {errors.link && (
                  <p className="text-[11px] text-red-600 mt-1">{errors.link.message}</p>
                )}
                <p className="text-[10px] text-slate-400 mt-1">
                  Pastikan izin akses tautan Google Drive disetel ke &quot;Siapa saja yang memiliki link&quot;.
                </p>
              </div>

              <div>
                <label className="block text-xs font-bold text-slate-700 mb-1">
                  Catatan / Ringkasan Jawaban Siswa
                </label>
                <textarea
                  rows={4}
                  placeholder="Tuliskan catatan, ringkasan pengerjaan, atau teks jawaban di sini..."
                  {...register('notes')}
                  className={cn(
                    'w-full px-3 py-2 rounded-lg border text-xs text-slate-900 placeholder:text-slate-400 focus:outline-hidden focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 resize-none',
                    errors.notes ? 'border-red-300 bg-red-50/30' : 'border-slate-300'
                  )}
                />
                {errors.notes && (
                  <p className="text-[11px] text-red-600 mt-1">{errors.notes.message}</p>
                )}
              </div>

              {/* Modal Footer */}
              <div className="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                <button
                  type="button"
                  onClick={handleCloseModal}
                  disabled={submitMutation.isPending}
                  className="px-3 py-1.5 rounded-lg text-xs font-bold text-slate-600 hover:text-slate-800 bg-slate-100 hover:bg-slate-200 cursor-pointer"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  disabled={submitMutation.isPending}
                  className="px-4 py-1.5 rounded-lg text-xs font-bold text-white bg-blue-900 hover:bg-blue-950 transition-colors inline-flex items-center gap-1.5 shadow-xs cursor-pointer disabled:opacity-50"
                >
                  {submitMutation.isPending ? (
                    <>
                      <RefreshCw className="w-3.5 h-3.5 animate-spin" /> Mengirimkan...
                    </>
                  ) : (
                    <>
                      <Send className="w-3.5 h-3.5" /> Kirimkan Tugas
                    </>
                  )}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
