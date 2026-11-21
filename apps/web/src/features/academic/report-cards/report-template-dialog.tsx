'use client';

import React, { useState, useEffect, useCallback } from 'react';
import {
  ReportDescriptionTemplateItem,
  ReportDescriptionLevel,
} from '@/types/academic';
import { SubjectItem } from '@/types/master';
import { academicApi } from '../academic-api';
import { subjectsApi } from '@/features/master/subjects-api';
import { Button } from '@/components/ui/button';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { toast } from '@/components/ui/toast';
import {
  X,
  Plus,
  Trash2,
  BookOpen,
  CheckCircle2,
  AlertCircle,
  Loader2,
  Sparkles,
} from 'lucide-react';

interface ReportTemplateDialogProps {
  isOpen: boolean;
  onClose: () => void;
}

export function ReportTemplateDialog({ isOpen, onClose }: ReportTemplateDialogProps) {
  const [templates, setTemplates] = useState<ReportDescriptionTemplateItem[]>([]);
  const [subjects, setSubjects] = useState<SubjectItem[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [isSaving, setIsSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);
  const [templateToDelete, setTemplateToDelete] = useState<string | null>(null);
  const [isDeletingTemplate, setIsDeletingTemplate] = useState(false);

  // Form states
  const [selectedSubjectId, setSelectedSubjectId] = useState<string>('');
  const [selectedLevel, setSelectedLevel] = useState<ReportDescriptionLevel>('tuntas');
  const [templateText, setTemplateText] = useState<string>('');
  const [filterSubjectId, setFilterSubjectId] = useState<string>('');

  const loadData = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    try {
      const [tplRes, subRes] = await Promise.all([
        academicApi.listReportDescriptionTemplates({
          subject_id: filterSubjectId || undefined,
          per_page: 50,
        }),
        subjectsApi.list({ limit: 100 }),
      ]);
      setTemplates(tplRes.data || []);
      setSubjects(subRes.data || []);
      if (!selectedSubjectId && subRes.data?.length > 0) {
        setSelectedSubjectId(subRes.data[0].id);
      }
    } catch (err: any) {
      setError(
        err.response?.data?.message || 'Gagal memuat template deskripsi capaian pembelajaran.'
      );
    } finally {
      setIsLoading(false);
    }
  }, [filterSubjectId, selectedSubjectId]);

  useEffect(() => {
    if (isOpen) {
      loadData();
    }
  }, [isOpen, loadData]);

  const handleSave = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedSubjectId || !templateText.trim()) {
      setError('Mata pelajaran dan teks deskripsi template wajib diisi.');
      return;
    }

    setIsSaving(true);
    setError(null);
    setSuccessMessage(null);
    try {
      await academicApi.storeReportDescriptionTemplate({
        subject_id: selectedSubjectId,
        level: selectedLevel,
        template_text: templateText.trim(),
      });
      setSuccessMessage('Template deskripsi capaian berhasil disimpan.');
      setTemplateText('');
      await loadData();
      setTimeout(() => setSuccessMessage(null), 3000);
    } catch (err: any) {
      setError(err.response?.data?.message || 'Gagal menyimpan template deskripsi.');
    } finally {
      setIsSaving(false);
    }
  };

  const handleDelete = (id: string) => {
    setTemplateToDelete(id);
  };

  const handleConfirmDelete = async () => {
    if (!templateToDelete) return;
    setIsDeletingTemplate(true);
    try {
      await academicApi.deleteReportDescriptionTemplate(templateToDelete);
      setTemplates((prev) => prev.filter((t) => t.id !== templateToDelete));
      toast.success('Template berhasil dihapus.');
      setTemplateToDelete(null);
    } catch (err: any) {
      toast.error(err.response?.data?.message || 'Gagal menghapus template deskripsi.');
    } finally {
      setIsDeletingTemplate(false);
    }
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-3 sm:p-4 backdrop-blur-xs overflow-y-auto">
      <div className="relative w-full max-w-3xl max-h-[90vh] flex flex-col rounded-2xl bg-white shadow-2xl overflow-hidden border border-gray-200">
        {/* Header */}
        <div className="flex items-center justify-between border-b border-gray-200 bg-gray-50/80 px-6 py-4">
          <div className="flex items-center gap-3">
            <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-purple-100 text-purple-700">
              <Sparkles className="h-5 w-5" />
            </div>
            <div>
              <h2 className="text-base font-bold text-gray-900">
                Template Deskripsi Capaian Pembelajaran (KKTP)
              </h2>
              <p className="text-xs text-gray-500">
                Kelola narasi otomatis Kurikulum Merdeka untuk capaian tuntas &amp; belum tuntas per mata pelajaran.
              </p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-700 transition"
          >
            <X className="h-5 w-5" />
          </button>
        </div>

        {/* Content Body */}
        <div className="flex-1 overflow-y-auto p-6 space-y-6">
          {error && (
            <div className="rounded-lg border border-red-200 bg-red-50 p-3 text-xs text-red-700 flex items-center justify-between">
              <span className="flex items-center gap-1.5">
                <AlertCircle className="h-4 w-4" /> {error}
              </span>
              <button onClick={() => setError(null)} className="text-red-500">
                <X className="h-4 w-4" />
              </button>
            </div>
          )}

          {successMessage && (
            <div className="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-xs text-emerald-700 flex items-center gap-1.5">
              <CheckCircle2 className="h-4 w-4" /> {successMessage}
            </div>
          )}

          {/* Form Tambah/Ubah Template */}
          <form onSubmit={handleSave} className="rounded-xl border border-gray-200 bg-gray-50/60 p-4 space-y-4">
            <h3 className="text-xs font-bold text-gray-800 uppercase tracking-wider flex items-center gap-1.5">
              <Plus className="h-4 w-4 text-purple-600" />
              Tambah atau Perbarui Template Deskripsi
            </h3>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label className="block text-xs font-medium text-gray-700 mb-1">
                  Mata Pelajaran
                </label>
                <select
                  value={selectedSubjectId}
                  onChange={(e) => setSelectedSubjectId(e.target.value)}
                  className="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs text-gray-900 focus:border-purple-500 focus:ring-1 focus:ring-purple-500"
                  required
                >
                  <option value="" disabled>
                    Pilih Mata Pelajaran
                  </option>
                  {subjects.map((s) => (
                    <option key={s.id} value={s.id}>
                      [{s.code}] {s.name}
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block text-xs font-medium text-gray-700 mb-1">
                  Tingkat Capaian (KKTP Threshold: 75.0)
                </label>
                <select
                  value={selectedLevel}
                  onChange={(e) => setSelectedLevel(e.target.value as ReportDescriptionLevel)}
                  className="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs text-gray-900 focus:border-purple-500 focus:ring-1 focus:ring-purple-500"
                >
                  <option value="tuntas">Tuntas (Nilai &ge; 75.0)</option>
                  <option value="belum">Perlu Bimbingan / Belum Tuntas (Nilai &lt; 75.0)</option>
                </select>
              </div>
            </div>

            <div>
              <label className="block text-xs font-medium text-gray-700 mb-1">
                Teks Narasi Deskripsi Capaian Pembelajaran
              </label>
              <textarea
                value={templateText}
                onChange={(e) => setTemplateText(e.target.value)}
                placeholder="Contoh: Menunjukkan penguasaan yang sangat baik dalam memahami konsep aljabar dan penyelesaian persamaan linier..."
                rows={3}
                className="w-full rounded-lg border border-gray-300 bg-white p-2.5 text-xs text-gray-900 focus:border-purple-500 focus:ring-1 focus:ring-purple-500"
                required
              />
              <p className="mt-1 text-[11px] text-gray-500">
                Teks ini akan otomatis disisipkan ke e-rapor semester siswa berdasarkan nilai akhir yang diperoleh.
              </p>
            </div>

            <div className="flex justify-end">
              <Button
                type="submit"
                disabled={isSaving}
                className="gap-1.5 text-xs bg-purple-600 hover:bg-purple-700 text-white"
              >
                {isSaving ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Plus className="h-3.5 w-3.5" />}
                Simpan Template
              </Button>
            </div>
          </form>

          {/* Daftar Template Aktif */}
          <div className="space-y-3">
            <div className="flex flex-wrap items-center justify-between gap-2">
              <h3 className="text-xs font-bold text-gray-800 uppercase tracking-wider flex items-center gap-1.5">
                <BookOpen className="h-4 w-4 text-purple-600" />
                Daftar Template Tersedia ({templates.length})
              </h3>

              {/* Filter Mapel */}
              <select
                value={filterSubjectId}
                onChange={(e) => setFilterSubjectId(e.target.value)}
                className="rounded-lg border border-gray-300 bg-white px-2.5 py-1 text-xs text-gray-700 focus:border-purple-500"
              >
                <option value="">Semua Mata Pelajaran</option>
                {subjects.map((s) => (
                  <option key={s.id} value={s.id}>
                    {s.name}
                  </option>
                ))}
              </select>
            </div>

            {isLoading ? (
              <div className="flex items-center justify-center p-8 text-gray-500 text-xs gap-2">
                <Loader2 className="h-4 w-4 animate-spin text-purple-600" />
                Memuat template deskripsi...
              </div>
            ) : templates.length === 0 ? (
              <div className="rounded-xl border border-dashed border-gray-300 p-8 text-center text-xs text-gray-400">
                Belum ada template deskripsi capaian khusus yang dikonfigurasi. Sistem akan menggunakan narasi standar Kurikulum Merdeka secara otomatis.
              </div>
            ) : (
              <div className="divide-y divide-gray-200 rounded-xl border border-gray-200 overflow-hidden bg-white">
                {templates.map((tpl) => (
                  <div key={tpl.id} className="p-3.5 flex items-start justify-between gap-3 hover:bg-gray-50/70 transition">
                    <div className="space-y-1">
                      <div className="flex items-center gap-2">
                        <span className="font-semibold text-xs text-gray-900">
                          {tpl.subject?.name || 'Mata Pelajaran'}
                        </span>
                        <span
                          className={`inline-flex items-center rounded-full px-2 py-0.2 text-[10px] font-semibold border ${
                            tpl.level === 'tuntas'
                              ? 'bg-emerald-50 text-emerald-700 border-emerald-300'
                              : 'bg-rose-50 text-rose-700 border-rose-300'
                          }`}
                        >
                          {tpl.level === 'tuntas' ? 'Tuntas (&ge;75)' : 'Perlu Bimbingan (<75)'}
                        </span>
                      </div>
                      <p className="text-xs text-gray-700 leading-relaxed">
                        {tpl.template_text}
                      </p>
                    </div>

                    <button
                      onClick={() => handleDelete(tpl.id)}
                      className="rounded p-1 text-gray-400 hover:text-rose-600 hover:bg-rose-50 transition"
                      title="Hapus Template"
                    >
                      <Trash2 className="h-4 w-4" />
                    </button>
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>

        {/* Footer */}
        <div className="border-t border-gray-200 bg-gray-50 px-6 py-3 flex justify-end">
          <Button variant="outline" size="sm" onClick={onClose} className="text-xs">
            Selesai
          </Button>
        </div>
      </div>

      {/* Dialog Konfirmasi Hapus Template */}
      <ConfirmDialog
        isOpen={!!templateToDelete}
        onClose={() => setTemplateToDelete(null)}
        onConfirm={handleConfirmDelete}
        title="Hapus Template Deskripsi"
        description="Apakah Anda yakin ingin menghapus template deskripsi capaian kompetensi ini?"
        confirmLabel="Ya, Hapus"
        cancelLabel="Batal"
        variant="danger"
        isLoading={isDeletingTemplate}
      />
    </div>
  );
}
