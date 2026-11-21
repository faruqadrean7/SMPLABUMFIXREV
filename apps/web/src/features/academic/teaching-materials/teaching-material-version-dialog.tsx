'use client';

import React, { useState, useEffect } from 'react';
import {
  TeachingMaterialItem,
  TEACHING_MATERIAL_TYPE_LABELS,
  TEACHING_MATERIAL_TYPE_COLORS,
} from '@/types/academic';
import { academicApi } from '../academic-api';
import { Button } from '@/components/ui/button';
import {
  X,
  History,
  FileText,
  Paperclip,
  AlertCircle,
  Loader2,
  Tag,
} from 'lucide-react';

interface TeachingMaterialVersionDialogProps {
  isOpen: boolean;
  onClose: () => void;
  onSuccess: () => void;
  material: TeachingMaterialItem | null;
}

export function TeachingMaterialVersionDialog({
  isOpen,
  onClose,
  onSuccess,
  material,
}: TeachingMaterialVersionDialogProps) {
  const [title, setTitle] = useState('');
  const [description, setDescription] = useState('');
  const [file, setFile] = useState<File | null>(null);

  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (isOpen && material) {
      setTitle(material.title);
      setDescription('');
      setFile(null);
      setError(null);
    }
  }, [isOpen, material]);

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files && e.target.files[0]) {
      const selected = e.target.files[0];
      if (selected.size > 25 * 1024 * 1024) {
        setError('Ukuran berkas revisi tidak boleh melebihi 25 MB.');
        return;
      }
      setFile(selected);
      setError(null);
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!material) return;

    if (!file) {
      setError('Berkas dokumen revisi wajib diunggah.');
      return;
    }
    if (!description.trim() || description.trim().length < 3) {
      setError('Catatan revisi / changelog wajib diisi minimal 3 karakter.');
      return;
    }

    setSubmitting(true);
    setError(null);

    try {
      await academicApi.createTeachingMaterialVersion(material.id, {
        title: title.trim() || undefined,
        description: description.trim(),
        file,
        version: material.version + 1,
      });

      onSuccess();
      onClose();
    } catch (err: unknown) {
      const errObj = err as { response?: { data?: { message?: string } }; message?: string };
      setError(
        errObj.response?.data?.message ||
          errObj.message ||
          'Gagal mengunggah versi revisi perangkat ajar.'
      );
    } finally {
      setSubmitting(false);
    }
  };

  if (!isOpen || !material) return null;

  const nextVersionNum = material.version + 1;
  const badgeColor = TEACHING_MATERIAL_TYPE_COLORS[material.material_type] || {
    badge: 'bg-gray-100 text-gray-700 border-gray-200',
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div className="w-full max-w-lg rounded-2xl bg-white shadow-xl flex flex-col max-h-[90vh] overflow-hidden">
        {/* Header */}
        <div className="flex items-center justify-between border-b border-gray-100 px-6 py-4">
          <div className="flex items-center gap-3">
            <div className="rounded-xl bg-blue-50 p-2.5 text-blue-600 border border-blue-100">
              <History className="h-5 w-5" />
            </div>
            <div>
              <h2 className="text-base font-bold text-gray-900">
                Unggah Versi Revisi Baru
              </h2>
              <p className="text-xs text-gray-500">
                Pembaruan dokumen perangkat ajar dengan nomor versi bertahap
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

          {/* Current Material Meta Card */}
          <div className="rounded-xl border border-gray-200 bg-gray-50/70 p-3.5 space-y-2">
            <div className="flex items-center justify-between">
              <span
                className={`px-2 py-0.5 text-[11px] font-semibold rounded-full border ${badgeColor.badge}`}
              >
                {TEACHING_MATERIAL_TYPE_LABELS[material.material_type]}
              </span>
              <div className="flex items-center gap-1.5 text-xs">
                <span className="text-gray-500">Versi Saat Ini:</span>
                <span className="font-bold text-gray-800 bg-gray-200 px-1.5 py-0.5 rounded">
                  v{material.version}
                </span>
                <span className="text-gray-400">→</span>
                <span className="font-bold text-blue-700 bg-blue-100 px-1.5 py-0.5 rounded">
                  v{nextVersionNum}
                </span>
              </div>
            </div>
            <p className="text-xs font-semibold text-gray-900 line-clamp-2">
              {material.title}
            </p>
            {material.subject && (
              <span className="text-[11px] text-gray-500 block">
                Mata Pelajaran: {material.subject.name}
              </span>
            )}
          </div>

          {/* Judul Baru (Opsional jika ingin mengubah) */}
          <div className="space-y-1.5">
            <label className="text-xs font-semibold text-gray-700 flex items-center gap-1.5">
              <FileText className="h-4 w-4 text-gray-500" />
              Judul Perangkat Ajar (Revisi)
            </label>
            <input
              type="text"
              value={title}
              onChange={(e) => setTitle(e.target.value)}
              className="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
            />
          </div>

          {/* Berkas Dokumen Revisi (Required) */}
          <div className="space-y-1.5">
            <label className="text-xs font-semibold text-gray-700 flex items-center gap-1.5">
              <Paperclip className="h-4 w-4 text-gray-500" />
              Berkas Dokumen Versi v{nextVersionNum} <span className="text-rose-500">*</span>
            </label>
            <input
              type="file"
              required
              accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.zip"
              onChange={handleFileChange}
              className="block w-full text-xs text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer border border-gray-200 rounded-lg p-1"
            />
            <p className="text-[11px] text-gray-500">
              Format: PDF, DOCX, PPTX, XLSX, ZIP. Maksimal 25 MB.
            </p>
          </div>

          {/* Catatan Revisi (Changelog) */}
          <div className="space-y-1.5">
            <label className="text-xs font-semibold text-gray-700 flex items-center gap-1.5">
              <Tag className="h-4 w-4 text-blue-600" />
              Catatan Perubahan (Changelog) <span className="text-rose-500">*</span>
            </label>
            <textarea
              rows={3}
              required
              value={description}
              onChange={(e) => setDescription(e.target.value)}
              placeholder="Jelaskan penyesuaian materi pada revisi ini (misal: Pembaruan soal asesmen sumatif, penambahan rubrik penilaian baru)..."
              className="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
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
              disabled={submitting || !file || !description.trim()}
              className="bg-blue-600 hover:bg-blue-700 text-white text-xs gap-1.5"
            >
              {submitting ? (
                <>
                  <Loader2 className="h-3.5 w-3.5 animate-spin" />
                  <span>Mengunggah Revisi...</span>
                </>
              ) : (
                <span>Rilis Versi v{nextVersionNum}</span>
              )}
            </Button>
          </div>
        </form>
      </div>
    </div>
  );
}
