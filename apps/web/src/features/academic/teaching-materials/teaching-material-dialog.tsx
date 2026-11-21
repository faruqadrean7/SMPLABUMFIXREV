'use client';

import React, { useState, useEffect } from 'react';
import {
  TeachingMaterialType,
  TEACHING_MATERIAL_TYPE_LABELS,
  GradeLevelItem,
} from '@/types/academic';
import { SubjectItem } from '@/types/master';
import { subjectsApi } from '@/features/master/subjects-api';
import { academicApi } from '../academic-api';
import { Button } from '@/components/ui/button';
import {
  X,
  UploadCloud,
  FileText,
  BookOpen,
  Layers,
  AlertCircle,
  Loader2,
  Paperclip,
} from 'lucide-react';

interface TeachingMaterialDialogProps {
  isOpen: boolean;
  onClose: () => void;
  onSuccess: () => void;
}

export function TeachingMaterialDialog({
  isOpen,
  onClose,
  onSuccess,
}: TeachingMaterialDialogProps) {
  const [subjects, setSubjects] = useState<SubjectItem[]>([]);
  const [gradeLevels, setGradeLevels] = useState<GradeLevelItem[]>([]);
  const [loadingData, setLoadingData] = useState(false);

  // Form states
  const [title, setTitle] = useState('');
  const [description, setDescription] = useState('');
  const [materialType, setMaterialType] = useState<TeachingMaterialType>('modul');
  const [gradeLevelId, setGradeLevelId] = useState('');
  const [subjectId, setSubjectId] = useState('');
  const [file, setFile] = useState<File | null>(null);

  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (isOpen) {
      setError(null);
      setLoadingData(true);

      Promise.all([
        subjectsApi.list({ limit: 100 }),
        academicApi.getGradeLevels().catch(() => [] as GradeLevelItem[]),
      ])
        .then(([subRes, grRes]) => {
          setSubjects(subRes.data || []);
          setGradeLevels(grRes || []);
        })
        .catch(() => {})
        .finally(() => {
          setLoadingData(false);
        });
    } else {
      // Reset form
      setTitle('');
      setDescription('');
      setMaterialType('modul');
      setGradeLevelId('');
      setSubjectId('');
      setFile(null);
      setError(null);
    }
  }, [isOpen]);

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files && e.target.files[0]) {
      const selected = e.target.files[0];
      if (selected.size > 25 * 1024 * 1024) {
        setError('Ukuran berkas tidak boleh melebihi 25 MB.');
        return;
      }
      setFile(selected);
      setError(null);
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!title.trim() || title.trim().length < 3) {
      setError('Judul perangkat ajar wajib diisi minimal 3 karakter.');
      return;
    }
    if (!file) {
      setError('Berkas dokumen perangkat ajar wajib dipilih.');
      return;
    }

    setSubmitting(true);
    setError(null);

    try {
      await academicApi.createTeachingMaterial({
        title: title.trim(),
        description: description.trim() || undefined,
        material_type: materialType,
        grade_level_id: gradeLevelId || undefined,
        subject_id: subjectId || undefined,
        file,
      });

      onSuccess();
      onClose();
    } catch (err: unknown) {
      const errObj = err as { response?: { data?: { message?: string } }; message?: string };
      setError(
        errObj.response?.data?.message ||
          errObj.message ||
          'Gagal mengunggah perangkat ajar. Pastikan format file sesuai.'
      );
    } finally {
      setSubmitting(false);
    }
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div className="w-full max-w-lg rounded-2xl bg-white shadow-xl flex flex-col max-h-[90vh] overflow-hidden">
        {/* Header */}
        <div className="flex items-center justify-between border-b border-gray-100 px-6 py-4">
          <div className="flex items-center gap-3">
            <div className="rounded-xl bg-emerald-50 p-2.5 text-emerald-600 border border-emerald-100">
              <UploadCloud className="h-5 w-5" />
            </div>
            <div>
              <h2 className="text-base font-bold text-gray-900">
                Unggah Perangkat Pembelajaran Baru
              </h2>
              <p className="text-xs text-gray-500">
                Modul Ajar, ATP, Silabus, dan Modul Proyek P5 Kurikulum Merdeka
              </p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition"
          >
            <X className="h-5 w-5" />
          </button>
        </div>

        {/* Body */}
        <form onSubmit={handleSubmit} className="p-6 overflow-y-auto space-y-4">
          {error && (
            <div className="rounded-xl border border-rose-200 bg-rose-50 p-3.5 flex items-start gap-3">
              <AlertCircle className="h-5 w-5 text-rose-600 shrink-0 mt-0.5" />
              <div className="text-xs text-rose-700">
                <span className="font-semibold block mb-0.5">Validasi Gagal:</span>
                {error}
              </div>
            </div>
          )}

          {/* Judul Perangkat */}
          <div className="space-y-1.5">
            <label className="text-xs font-semibold text-gray-700 flex items-center gap-1.5">
              <FileText className="h-4 w-4 text-gray-500" />
              Judul Perangkat Ajar <span className="text-rose-500">*</span>
            </label>
            <input
              type="text"
              required
              minLength={3}
              maxLength={200}
              value={title}
              onChange={(e) => setTitle(e.target.value)}
              placeholder="Contoh: Modul Ajar Informatika Bab 3 - Algoritma dan Pemrograman"
              className="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
            />
          </div>

          {/* Jenis Perangkat Ajar */}
          <div className="space-y-1.5">
            <label className="text-xs font-semibold text-gray-700">
              Jenis Perangkat Ajar <span className="text-rose-500">*</span>
            </label>
            <div className="grid grid-cols-2 gap-2">
              {(['modul', 'ATP', 'silabus', 'P5'] as TeachingMaterialType[]).map((t) => (
                <button
                  key={t}
                  type="button"
                  onClick={() => setMaterialType(t)}
                  className={`rounded-lg border px-3 py-2 text-xs font-medium text-left transition ${
                    materialType === t
                      ? 'border-emerald-500 bg-emerald-50 text-emerald-800 font-bold shadow-xs'
                      : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50'
                  }`}
                >
                  {TEACHING_MATERIAL_TYPE_LABELS[t]}
                </button>
              ))}
            </div>
          </div>

          {/* Tingkat Kelas & Mata Pelajaran */}
          <div className="grid grid-cols-2 gap-3">
            <div className="space-y-1.5">
              <label className="text-xs font-semibold text-gray-700 flex items-center gap-1.5">
                <Layers className="h-4 w-4 text-gray-500" />
                Tingkat Kelas
              </label>
              <select
                value={gradeLevelId}
                onChange={(e) => setGradeLevelId(e.target.value)}
                className="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
              >
                <option value="">Semua Tingkat</option>
                {gradeLevels.map((gl) => (
                  <option key={gl.id} value={gl.id}>
                    {gl.name} (Tingkat {gl.level})
                  </option>
                ))}
              </select>
            </div>

            <div className="space-y-1.5">
              <label className="text-xs font-semibold text-gray-700 flex items-center gap-1.5">
                <BookOpen className="h-4 w-4 text-gray-500" />
                Mata Pelajaran
              </label>
              <select
                value={subjectId}
                onChange={(e) => setSubjectId(e.target.value)}
                className="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
              >
                <option value="">Semua Mapel</option>
                {subjects.map((sub) => (
                  <option key={sub.id} value={sub.id}>
                    {sub.name} ({sub.code})
                  </option>
                ))}
              </select>
            </div>
          </div>

          {/* Berkas Dokumen (File Upload) */}
          <div className="space-y-1.5">
            <label className="text-xs font-semibold text-gray-700 flex items-center gap-1.5">
              <Paperclip className="h-4 w-4 text-gray-500" />
              Berkas Dokumen <span className="text-rose-500">*</span>
            </label>
            <input
              type="file"
              required
              accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.zip"
              onChange={handleFileChange}
              className="block w-full text-xs text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 cursor-pointer border border-gray-200 rounded-lg p-1"
            />
            <p className="text-[11px] text-gray-500">
              Format didukung: PDF, DOCX, PPTX, XLSX, ZIP. Maksimal 25 MB.
            </p>
          </div>

          {/* Deskripsi / Petunjuk Penggunaan */}
          <div className="space-y-1.5">
            <label className="text-xs font-semibold text-gray-700">
              Deskripsi & Capaian Pembelajaran (Opsional)
            </label>
            <textarea
              rows={3}
              value={description}
              onChange={(e) => setDescription(e.target.value)}
              placeholder="Jelaskan ringkasan materi, capaian pembelajaran (CP), atau petunjuk pemanfaatan modul ini..."
              className="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
            />
          </div>

          {/* Footer inside form */}
          <div className="border-t border-gray-100 pt-4 flex items-center justify-end gap-2">
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={onClose}
              disabled={submitting}
              className="text-xs"
            >
              Batal
            </Button>
            <Button
              type="submit"
              size="sm"
              disabled={submitting || !title.trim() || !file}
              className="bg-emerald-600 hover:bg-emerald-700 text-white text-xs gap-1.5"
            >
              {submitting ? (
                <>
                  <Loader2 className="h-3.5 w-3.5 animate-spin" />
                  <span>Mengunggah...</span>
                </>
              ) : (
                <span>Simpan & Rilis Perangkat</span>
              )}
            </Button>
          </div>
        </form>
      </div>
    </div>
  );
}
