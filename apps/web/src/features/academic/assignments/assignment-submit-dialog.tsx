'use client';

import React, { useState } from 'react';
import {
  AssignmentItem,
  AssignmentSubmissionItem,
  SubmitAssignmentPayload,
} from '@/types/academic';
import { academicApi } from '../academic-api';
import { ApiClientError } from '@/lib/api/errors';
import { Button } from '@/components/ui/button';
import {
  Upload,
  FileText,
  AlertTriangle,
  X,
  CheckCircle2,
  Calendar,
  Award,
  Download,
} from 'lucide-react';

interface AssignmentSubmitDialogProps {
  isOpen: boolean;
  onClose: () => void;
  assignment: AssignmentItem | null;
  onSuccess: () => void;
}

export function AssignmentSubmitDialog({
  isOpen,
  onClose,
  assignment,
  onSuccess,
}: AssignmentSubmitDialogProps) {
  const [selectedFile, setSelectedFile] = useState<File | null>(null);
  const [notes, setNotes] = useState<string>('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  if (!isOpen || !assignment) return null;

  const mySubmission = assignment.my_submission;
  const isAlreadyGraded = mySubmission && mySubmission.score !== null;

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files && e.target.files[0]) {
      const file = e.target.files[0];
      if (file.size > 26214400) {
        // 25MB
        setErrorMessage('Ukuran berkas jawaban maksimal 25 MB.');
        return;
      }
      setSelectedFile(file);
      setErrorMessage(null);
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedFile && !mySubmission) {
      setErrorMessage('Berkas pengerjaan tugas wajib diunggah.');
      return;
    }

    try {
      setIsSubmitting(true);
      setErrorMessage(null);

      const payload: SubmitAssignmentPayload = {
        file: selectedFile || undefined,
        notes: notes.trim() || undefined,
      };

      await academicApi.submitAssignment(assignment.id, payload);
      onSuccess();
      onClose();
    } catch (err: unknown) {
      if (err instanceof ApiClientError) {
        setErrorMessage(err.message);
      } else if (err instanceof Error) {
        setErrorMessage(err.message);
      } else {
        setErrorMessage('Gagal mengumpulkan tugas.');
      }
    } finally {
      setIsSubmitting(false);
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

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div className="w-full max-w-lg rounded-xl border border-gray-200 bg-white shadow-xl animate-in fade-in-0 zoom-in-95">
        {/* Header */}
        <div className="flex items-center justify-between border-b border-gray-100 px-6 py-4">
          <div className="flex items-center gap-2.5">
            <div className="rounded-lg bg-emerald-100 p-2 text-emerald-700">
              <Upload className="h-5 w-5" />
            </div>
            <div>
              <h2 className="text-base font-semibold text-gray-900">
                Pengumpulan Tugas Siswa
              </h2>
              <p className="text-xs text-gray-500">
                {assignment.title} • {assignment.subject?.name || 'Mapel'}
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

        {/* Assignment Brief */}
        <div className="bg-gray-50 border-b border-gray-200 px-6 py-3 space-y-1.5 text-xs text-gray-600">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-1.5">
              <Calendar className="h-4 w-4 text-gray-400" />
              <span>
                Tenggat: <strong className="text-gray-800">{formatDate(assignment.due_at)}</strong>
              </span>
            </div>
            <div className="flex items-center gap-1.5">
              <Award className="h-4 w-4 text-gray-400" />
              <span>
                Nilai Maks: <strong className="text-gray-800">{assignment.max_score}</strong>
              </span>
            </div>
          </div>

          {assignment.description && (
            <p className="text-gray-600 pt-1 border-t border-gray-200/60 leading-relaxed">
              {assignment.description}
            </p>
          )}

          {assignment.file && (
            <div className="pt-1">
              <a
                href={assignment.file.url || `/api/v1/files/${assignment.file.id}/download`}
                target="_blank"
                rel="noreferrer"
                className="inline-flex items-center gap-1 text-emerald-600 hover:text-emerald-700 font-semibold"
              >
                <Download className="h-3.5 w-3.5" />
                <span>Unduh Lembar Soal / Panduan Tugas</span>
              </a>
            </div>
          )}
        </div>

        {/* Existing Submission Notice */}
        {mySubmission && (
          <div className="mx-6 mt-4 rounded-lg border border-emerald-200 bg-emerald-50/70 p-3 text-xs text-emerald-800">
            <div className="flex items-center gap-1.5 font-semibold">
              <CheckCircle2 className="h-4 w-4 text-emerald-600" />
              <span>Tugas telah dikumpulkan pada {formatDate(mySubmission.submitted_at)}</span>
            </div>
            {isAlreadyGraded ? (
              <p className="mt-1 text-emerald-700">
                Tugas ini sudah dinilai dengan nilai <strong>{mySubmission.score}</strong>. Tugas yang telah dinilai tidak dapat diubah kembali.
              </p>
            ) : (
              <p className="mt-1 text-emerald-700">
                Anda dapat mengunggah revisi berkas jawaban sebelum dinilai oleh guru.
              </p>
            )}
          </div>
        )}

        {/* Form Body */}
        <form onSubmit={handleSubmit} className="space-y-4 px-6 py-4">
          {errorMessage && (
            <div className="flex items-start gap-2.5 rounded-lg border border-rose-200 bg-rose-50 p-3 text-xs text-rose-800">
              <AlertTriangle className="h-4 w-4 shrink-0 text-rose-600 mt-0.5" />
              <span>{errorMessage}</span>
            </div>
          )}

          {!isAlreadyGraded && (
            <>
              {/* File Upload */}
              <div>
                <label className="block text-xs font-medium text-gray-700 mb-1">
                  Berkas Lembar Jawaban (PDF, DOCX, PPTX, XLS, ZIP, Gambar max 25MB){' '}
                  <span className="text-rose-500">*</span>
                </label>
                <div className="flex justify-center rounded-lg border border-dashed border-gray-300 px-6 py-4 hover:border-emerald-400 transition bg-gray-50/50">
                  <div className="text-center">
                    <Upload className="mx-auto h-8 w-8 text-gray-400" />
                    <div className="mt-2 flex text-xs leading-6 text-gray-600 justify-center">
                      <label
                        htmlFor="submit-file"
                        className="relative cursor-pointer rounded-md font-semibold text-emerald-600 focus-within:outline-hidden hover:text-emerald-500"
                      >
                        <span>Pilih Berkas Jawaban</span>
                        <input
                          id="submit-file"
                          type="file"
                          onChange={handleFileChange}
                          className="sr-only"
                          accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.zip,.jpg,.jpeg,.png"
                        />
                      </label>
                      <p className="pl-1">atau tarik berkas</p>
                    </div>
                    <p className="text-[11px] text-gray-500 mt-0.5">
                      {selectedFile
                        ? `Terpilih: ${selectedFile.name} (${(selectedFile.size / (1024 * 1024)).toFixed(2)} MB)`
                        : mySubmission?.file
                        ? `Berkas tersimpan sebelumnya: ${mySubmission.file.object_key.split('/').pop()}`
                        : 'PDF, Dokumen Word, Gambar, atau ZIP hingga 25 MB'}
                    </p>
                  </div>
                </div>
              </div>

              {/* Notes */}
              <div>
                <label className="block text-xs font-medium text-gray-700 mb-1">
                  Catatan untuk Guru (Opsional)
                </label>
                <textarea
                  rows={2}
                  placeholder="Tuliskan catatan pengerjaan atau kendala jika ada..."
                  value={notes}
                  onChange={(e) => setNotes(e.target.value)}
                  className="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs text-gray-900 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 focus:outline-hidden"
                />
              </div>
            </>
          )}

          {/* Footer Buttons */}
          <div className="mt-6 flex items-center justify-end gap-3 border-t border-gray-100 pt-4">
            <Button type="button" variant="outline" size="sm" onClick={onClose} disabled={isSubmitting}>
              Tutup
            </Button>
            {!isAlreadyGraded && (
              <Button
                type="submit"
                size="sm"
                disabled={isSubmitting || (!selectedFile && !mySubmission)}
                className="bg-emerald-600 hover:bg-emerald-700 text-white"
              >
                {isSubmitting ? 'Mengirim...' : mySubmission ? 'Kirim Pembaruan' : 'Kumpulkan Tugas'}
              </Button>
            )}
          </div>
        </form>
      </div>
    </div>
  );
}
