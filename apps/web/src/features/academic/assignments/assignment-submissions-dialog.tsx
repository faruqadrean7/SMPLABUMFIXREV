'use client';

import React, { useState, useEffect, useCallback } from 'react';
import {
  AssignmentItem,
  AssignmentSubmissionItem,
  SUBMISSION_STATUS_LABELS,
  SUBMISSION_STATUS_COLORS,
  SUBMISSION_TIMING_LABELS,
  SUBMISSION_TIMING_COLORS,
} from '@/types/academic';
import { academicApi } from '../academic-api';
import { ApiClientError } from '@/lib/api/errors';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
  FileCheck2,
  Calendar,
  Clock,
  Download,
  Award,
  MessageSquare,
  AlertTriangle,
  X,
  User,
  CheckCircle2,
  RefreshCw,
} from 'lucide-react';

interface AssignmentSubmissionsDialogProps {
  isOpen: boolean;
  onClose: () => void;
  assignment: AssignmentItem | null;
  onGraded?: () => void;
}

export function AssignmentSubmissionsDialog({
  isOpen,
  onClose,
  assignment,
  onGraded,
}: AssignmentSubmissionsDialogProps) {
  const [submissions, setSubmissions] = useState<AssignmentSubmissionItem[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  // Active submission being graded
  const [gradingSubmissionId, setGradingSubmissionId] = useState<string | null>(null);
  const [scoreInput, setScoreInput] = useState<string>('');
  const [feedbackInput, setFeedbackInput] = useState<string>('');
  const [isSubmittingGrade, setIsSubmittingGrade] = useState(false);
  const [gradeSuccessMessage, setGradeSuccessMessage] = useState<string | null>(null);

  const fetchSubmissions = useCallback(async () => {
    if (!assignment) return;
    try {
      setIsLoading(true);
      setErrorMessage(null);
      const data = await academicApi.listSubmissions(assignment.id);
      setSubmissions(data);
    } catch (err: unknown) {
      if (err instanceof ApiClientError) {
        setErrorMessage(err.message);
      } else {
        setErrorMessage('Gagal memuat daftar pengumpulan tugas.');
      }
    } finally {
      setIsLoading(false);
    }
  }, [assignment]);

  useEffect(() => {
    if (isOpen && assignment) {
      fetchSubmissions();
      setGradingSubmissionId(null);
      setGradeSuccessMessage(null);
    }
  }, [isOpen, assignment, fetchSubmissions]);

  if (!isOpen || !assignment) return null;

  const handleStartGrading = (sub: AssignmentSubmissionItem) => {
    setGradingSubmissionId(sub.id);
    setScoreInput(sub.score !== null && sub.score !== undefined ? String(sub.score) : '');
    setFeedbackInput(sub.feedback || '');
    setGradeSuccessMessage(null);
    setErrorMessage(null);
  };

  const handleCancelGrading = () => {
    setGradingSubmissionId(null);
    setScoreInput('');
    setFeedbackInput('');
  };

  const handleSaveGrade = async (submissionId: string) => {
    const numScore = parseFloat(scoreInput);
    if (isNaN(numScore) || numScore < 0 || numScore > 100) {
      setErrorMessage('Nilai tugas harus berupa angka antara 0 sampai 100.');
      return;
    }

    try {
      setIsSubmittingGrade(true);
      setErrorMessage(null);
      await academicApi.gradeSubmission(assignment.id, submissionId, {
        score: numScore,
        feedback: feedbackInput.trim() || undefined,
      });

      setGradeSuccessMessage('Nilai & umpan balik berhasil disimpan.');
      setGradingSubmissionId(null);
      fetchSubmissions();
      if (onGraded) onGraded();
    } catch (err: unknown) {
      if (err instanceof ApiClientError) {
        setErrorMessage(err.message);
      } else {
        setErrorMessage('Gagal menyimpan nilai tugas.');
      }
    } finally {
      setIsSubmittingGrade(false);
    }
  };

  const formatDate = (dateStr?: string) => {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleString('id-ID', {
      day: 'numeric',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  };

  const gradedCount = submissions.filter((s) => s.status === 'dinilai').length;
  const pendingCount = submissions.filter((s) => s.status === 'menunggu_penilaian').length;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div className="w-full max-w-4xl max-h-[90vh] flex flex-col rounded-xl border border-gray-200 bg-white shadow-xl animate-in fade-in-0 zoom-in-95">
        {/* Header */}
        <div className="flex items-center justify-between border-b border-gray-100 px-6 py-4 bg-white rounded-t-xl">
          <div className="flex items-center gap-2.5">
            <div className="rounded-lg bg-emerald-100 p-2 text-emerald-700">
              <FileCheck2 className="h-5 w-5" />
            </div>
            <div>
              <h2 className="text-base font-semibold text-gray-900">
                Hasil Pengumpulan & Penilaian Tugas
              </h2>
              <p className="text-xs text-gray-500">
                {assignment.title} • {assignment.class?.name || 'Kelas'} • {assignment.subject?.name || 'Mapel'}
              </p>
            </div>
          </div>
          <button
            type="button"
            onClick={onClose}
            className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition"
          >
            <X className="h-5 w-5" />
          </button>
        </div>

        {/* Overview Bar */}
        <div className="bg-gray-50 border-b border-gray-200 px-6 py-3 flex flex-wrap items-center justify-between gap-4 text-xs">
          <div className="flex items-center gap-6">
            <div className="flex items-center gap-1.5 text-gray-600">
              <Calendar className="h-4 w-4 text-gray-400" />
              <span>
                Tenggat: <strong className="text-gray-800">{formatDate(assignment.due_at)}</strong>
              </span>
            </div>
            <div className="flex items-center gap-1.5 text-gray-600">
              <Award className="h-4 w-4 text-gray-400" />
              <span>
                Nilai Maks: <strong className="text-gray-800">{assignment.max_score}</strong>
              </span>
            </div>
          </div>

          <div className="flex items-center gap-3">
            <span className="rounded-full bg-blue-100 px-2.5 py-0.5 text-[11px] font-medium text-blue-800">
              Total: {submissions.length} Pengumpulan
            </span>
            <span className="rounded-full bg-emerald-100 px-2.5 py-0.5 text-[11px] font-medium text-emerald-800">
              Dinilai: {gradedCount}
            </span>
            <span className="rounded-full bg-amber-100 px-2.5 py-0.5 text-[11px] font-medium text-amber-800">
              Menunggu: {pendingCount}
            </span>
            <button
              onClick={fetchSubmissions}
              disabled={isLoading}
              className="rounded-md border border-gray-200 bg-white p-1 text-gray-500 hover:bg-gray-100"
              title="Muat Ulang"
            >
              <RefreshCw className={`h-3.5 w-3.5 ${isLoading ? 'animate-spin' : ''}`} />
            </button>
          </div>
        </div>

        {/* Content Body */}
        <div className="flex-1 overflow-y-auto px-6 py-4 space-y-4">
          {errorMessage && (
            <div className="flex items-start gap-2.5 rounded-lg border border-rose-200 bg-rose-50 p-3 text-xs text-rose-800">
              <AlertTriangle className="h-4 w-4 shrink-0 text-rose-600 mt-0.5" />
              <span>{errorMessage}</span>
            </div>
          )}

          {gradeSuccessMessage && (
            <div className="flex items-start gap-2.5 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-xs text-emerald-800">
              <CheckCircle2 className="h-4 w-4 shrink-0 text-emerald-600 mt-0.5" />
              <span>{gradeSuccessMessage}</span>
            </div>
          )}

          {isLoading ? (
            <div className="py-12 text-center text-xs text-gray-500">
              <RefreshCw className="mx-auto h-6 w-6 animate-spin text-emerald-600 mb-2" />
              Memuat data pengumpulan tugas...
            </div>
          ) : submissions.length === 0 ? (
            <div className="py-12 text-center text-xs text-gray-500">
              <FileCheck2 className="mx-auto h-8 w-8 text-gray-300 mb-2" />
              Belum ada siswa yang mengumpulkan tugas ini.
            </div>
          ) : (
            <div className="overflow-x-auto rounded-lg border border-gray-200">
              <table className="w-full text-left text-xs">
                <thead className="bg-gray-50 text-[11px] font-semibold text-gray-600 uppercase border-b border-gray-200">
                  <tr>
                    <th className="px-4 py-3">Siswa</th>
                    <th className="px-4 py-3">Waktu Pengumpulan</th>
                    <th className="px-4 py-3">Status & Ketepatan</th>
                    <th className="px-4 py-3">Berkas Jawaban</th>
                    <th className="px-4 py-3">Nilai</th>
                    <th className="px-4 py-3 text-right">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100">
                  {submissions.map((sub) => {
                    const isGrading = gradingSubmissionId === sub.id;
                    const statusColor = SUBMISSION_STATUS_COLORS[sub.status];
                    const timingColor = SUBMISSION_TIMING_COLORS[sub.submission_timing];

                    return (
                      <React.Fragment key={sub.id}>
                        <tr className="hover:bg-gray-50/50 transition">
                          <td className="px-4 py-3">
                            <div className="font-semibold text-gray-900">
                              {sub.student?.full_name || 'Nama Siswa'}
                            </div>
                            <div className="text-[11px] text-gray-500">
                              NIS: {sub.student?.nis || '-'}
                            </div>
                          </td>

                          <td className="px-4 py-3 text-gray-600">
                            <div className="flex items-center gap-1">
                              <Clock className="h-3.5 w-3.5 text-gray-400" />
                              <span>{formatDate(sub.submitted_at)}</span>
                            </div>
                            {sub.notes && (
                              <div className="mt-1 text-[11px] text-gray-500 italic">
                                &quot;{sub.notes}&quot;
                              </div>
                            )}
                          </td>

                          <td className="px-4 py-3">
                            <div className="flex flex-col gap-1 items-start">
                              <span
                                className={`rounded-full px-2 py-0.5 text-[10px] font-semibold border ${statusColor.badge}`}
                              >
                                {SUBMISSION_STATUS_LABELS[sub.status]}
                              </span>
                              <span
                                className={`rounded-full px-2 py-0.5 text-[10px] font-medium border ${timingColor.badge}`}
                              >
                                {SUBMISSION_TIMING_LABELS[sub.submission_timing]}
                              </span>
                            </div>
                          </td>

                          <td className="px-4 py-3">
                            {sub.file ? (
                              <a
                                href={sub.file.url || `/api/v1/files/${sub.file.id}/download`}
                                target="_blank"
                                rel="noreferrer"
                                className="inline-flex items-center gap-1 text-emerald-600 hover:text-emerald-700 font-medium"
                              >
                                <Download className="h-3.5 w-3.5" />
                                <span>Unduh Jawaban</span>
                              </a>
                            ) : (
                              <span className="text-gray-400 italic">Tidak ada berkas</span>
                            )}
                          </td>

                          <td className="px-4 py-3">
                            {sub.score !== null && sub.score !== undefined ? (
                              <div>
                                <span className="font-bold text-emerald-700 text-sm">
                                  {sub.score}
                                </span>
                                <span className="text-[11px] text-gray-400"> / {assignment.max_score}</span>
                                {sub.feedback && (
                                  <div className="text-[11px] text-gray-500 truncate max-w-xs" title={sub.feedback}>
                                    Catatan: {sub.feedback}
                                  </div>
                                )}
                              </div>
                            ) : (
                              <span className="text-gray-400 font-medium">-</span>
                            )}
                          </td>

                          <td className="px-4 py-3 text-right">
                            <Button
                              size="sm"
                              variant="outline"
                              onClick={() =>
                                isGrading ? handleCancelGrading() : handleStartGrading(sub)
                              }
                              className="text-xs h-7"
                            >
                              {isGrading ? 'Batal' : sub.score !== null ? 'Koreksi Nilai' : 'Beri Nilai'}
                            </Button>
                          </td>
                        </tr>

                        {/* Grading Form Row */}
                        {isGrading && (
                          <tr className="bg-emerald-50/40 border-y border-emerald-100">
                            <td colSpan={6} className="px-4 py-3">
                              <div className="flex flex-col sm:flex-row items-start sm:items-center gap-3">
                                <div className="w-28">
                                  <label className="block text-[11px] font-semibold text-gray-700 mb-1">
                                    Nilai (0 - 100)
                                  </label>
                                  <Input
                                    type="number"
                                    min={0}
                                    max={100}
                                    step="0.5"
                                    placeholder="85"
                                    value={scoreInput}
                                    onChange={(e) => setScoreInput(e.target.value)}
                                    className="h-8 text-xs bg-white"
                                  />
                                </div>

                                <div className="flex-1 w-full">
                                  <label className="block text-[11px] font-semibold text-gray-700 mb-1">
                                    Catatan / Umpan Balik Guru
                                  </label>
                                  <Input
                                    type="text"
                                    placeholder="Tuliskan apresiasi atau perbaikan konsep untuk siswa..."
                                    value={feedbackInput}
                                    onChange={(e) => setFeedbackInput(e.target.value)}
                                    className="h-8 text-xs bg-white"
                                  />
                                </div>

                                <div className="flex items-center gap-2 sm:mt-5">
                                  <Button
                                    size="sm"
                                    onClick={() => handleSaveGrade(sub.id)}
                                    disabled={isSubmittingGrade}
                                    className="bg-emerald-600 hover:bg-emerald-700 text-white h-8 text-xs"
                                  >
                                    {isSubmittingGrade ? 'Menyimpan...' : 'Simpan Nilai'}
                                  </Button>
                                  <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={handleCancelGrading}
                                    disabled={isSubmittingGrade}
                                    className="h-8 text-xs"
                                  >
                                    Batal
                                  </Button>
                                </div>
                              </div>
                            </td>
                          </tr>
                        )}
                      </React.Fragment>
                    );
                  })}
                </tbody>
              </table>
            </div>
          )}
        </div>

        {/* Footer */}
        <div className="flex items-center justify-end border-t border-gray-100 px-6 py-3 bg-gray-50 rounded-b-xl">
          <Button size="sm" variant="outline" onClick={onClose}>
            Tutup
          </Button>
        </div>
      </div>
    </div>
  );
}
