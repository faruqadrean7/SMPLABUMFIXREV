'use client';

import React, { useState } from 'react';
import Link from 'next/link';
import {
  useTeacherMaterials,
  useTeacherAssignmentsSummary,
  guruApi,
  TeacherMaterialItem,
} from '@/features/portal/guru';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { LoadingSpinner } from '@/components/shared/loading-spinner';
import { EmptyState } from '@/components/shared/empty-state';
import {
  Layers,
  ArrowLeft,
  Plus,
  FileText,
  Download,
  ExternalLink,
  CheckCircle2,
  X,
  BookOpen,
  AlertCircle,
} from 'lucide-react';

export default function TeacherMaterialsPage() {
  const { data: initialMaterials = [], isLoading } = useTeacherMaterials();
  const {
    subjects,
    getClassesForSubject,
    isLoading: isLoadingAssignments,
  } = useTeacherAssignmentsSummary();

  const [materials, setMaterials] = useState<TeacherMaterialItem[]>([]);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [title, setTitle] = useState('');
  const [selectedSubjectId, setSelectedSubjectId] = useState<string>('');
  const [selectedClassIds, setSelectedClassIds] = useState<string[]>([]);
  const [description, setDescription] = useState('');
  const [fileUrl, setFileUrl] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [modalError, setModalError] = useState<string | null>(null);

  React.useEffect(() => {
    if (initialMaterials.length > 0) {
      setMaterials(initialMaterials);
    }
  }, [initialMaterials]);

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
    } else if (selectedSubjectId !== '') {
      setSelectedSubjectId('');
    }
  }, [subjects, selectedSubjectId]);

  // Keep selectedClassIds aligned with availableClasses for this subject
  React.useEffect(() => {
    if (availableClasses.length > 0) {
      setSelectedClassIds((prev) => {
        const valid = prev.filter((id) => availableClasses.some((c) => c.id === id));
        const next = valid.length > 0 ? valid : [availableClasses[0].id];
        if (prev.length === next.length && prev.every((v, i) => v === next[i])) {
          return prev;
        }
        return next;
      });
    } else {
      setSelectedClassIds((prev) => (prev.length === 0 ? prev : []));
    }
  }, [availableClasses]);

  const handleClassToggle = (classId: string) => {
    setSelectedClassIds((prev) =>
      prev.includes(classId) ? prev.filter((id) => id !== classId) : [...prev, classId]
    );
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!title.trim() || !selectedSubjectId || selectedClassIds.length === 0) return;

    setIsSubmitting(true);
    setModalError(null);
    setErrorMessage(null);
    try {
      const activeSubject = subjects.find((s) => s.id === selectedSubjectId);
      const classNames = availableClasses
        .filter((c) => selectedClassIds.includes(c.id))
        .map((c) => c.name);

      const newMat = await guruApi.createMaterial({
        title,
        subjectId: selectedSubjectId,
        subjectName: activeSubject?.name || 'Mata Pelajaran',
        classIds: selectedClassIds,
        classNames,
        description,
        fileUrl: fileUrl.trim() || '/documents/modul-pembelajaran.pdf',
      });
      setMaterials((prev) => [newMat, ...prev]);
      setSuccessMessage('Materi dan modul ajar berhasil diunggah.');
      setIsModalOpen(false);
      setTitle('');
      setDescription('');
      setFileUrl('');
      setTimeout(() => setSuccessMessage(null), 4000);
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Gagal mengunggah materi pembelajaran.';
      setModalError(msg);
      setErrorMessage(msg);
    } finally {
      setIsSubmitting(false);
    }
  };

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
            <Layers className="h-6 w-6 text-brand-blue" />
            <span>Materi & Modul Pembelajaran</span>
          </h1>
          <p className="text-xs sm:text-sm text-slate-500 mt-1">
            Unggah dan kelola modul ajar, slide presentasi, dan bahan bacaan siswa.
          </p>
        </div>

        <Button
          onClick={() => setIsModalOpen(true)}
          disabled={!isLoadingAssignments && subjects.length === 0}
          size="sm"
          className="gap-1.5 font-bold"
        >
          <Plus className="h-4 w-4" />
          <span>Tambah Materi Baru</span>
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
          <span>Anda belum memiliki penugasan jadwal mengajar aktif. Anda tidak dapat mengunggah materi ajar baru.</span>
        </div>
      )}

      {/* Materials List */}
      {isLoading || isLoadingAssignments ? (
        <div className="min-h-[250px] flex items-center justify-center bg-white rounded-2xl border border-slate-200">
          <LoadingSpinner label="Memuat materi ajar..." size="lg" />
        </div>
      ) : materials.length === 0 ? (
        <EmptyState
          title="Belum Ada Materi Pembelajaran"
          description="Anda belum mengunggah materi atau modul ajar untuk kelas binaan Anda."
          icon={<Layers className="h-8 w-8 text-slate-400" />}
          actionLabel="Tambah Materi Baru"
          onAction={() => setIsModalOpen(true)}
        />
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {materials.map((mat) => (
            <Card
              key={mat.id}
              className="border-slate-200 hover:border-brand-blue hover:shadow-xs transition-all flex flex-col justify-between"
            >
              <div>
                <CardHeader className="pb-2">
                  <div className="flex items-center justify-between text-xs text-slate-400 mb-1">
                    <span>{mat.createdAt}</span>
                    <span className="flex items-center gap-1 text-[11px] font-bold text-slate-600">
                      <Download className="h-3 w-3" />
                      {mat.downloadsCount} kali diunduh
                    </span>
                  </div>
                  <CardTitle className="text-base font-bold text-slate-900 leading-snug">
                    {mat.title}
                  </CardTitle>
                  <div className="flex items-center gap-1.5 flex-wrap pt-1">
                    <Badge variant="outline" className="text-[10px] bg-blue-50 text-brand-blue border-blue-200 font-bold">
                      {mat.subjectName}
                    </Badge>
                    {mat.classNames.map((cn) => (
                      <Badge key={cn} variant="outline" className="text-[10px] bg-slate-50 text-slate-600">
                        {cn}
                      </Badge>
                    ))}
                  </div>
                </CardHeader>
                <CardContent className="pt-1 text-xs text-slate-600 leading-relaxed">
                  {mat.description}
                </CardContent>
              </div>

              <div className="p-4 pt-0 mt-2 border-t border-slate-100 flex items-center justify-between">
                <span className="text-[11px] text-slate-400 font-mono truncate max-w-[180px]">
                  {mat.fileUrl || 'Link eksternal'}
                </span>
                <a
                  href={mat.fileUrl || '#'}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-xs font-bold text-slate-700 transition-colors shadow-2xs"
                >
                  <Download className="h-3.5 w-3.5" />
                  <span>Unduh File</span>
                </a>
              </div>
            </Card>
          ))}
        </div>
      )}

      {/* Modal Tambah Materi Baru */}
      {isModalOpen && (
        <div className="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl max-w-lg w-full p-6 shadow-xl space-y-4 animate-in fade-in zoom-in-95 duration-200">
            <div className="flex items-center justify-between pb-3 border-b border-slate-100">
              <h3 className="font-bold text-base text-slate-900 flex items-center gap-2">
                <Layers className="h-5 w-5 text-brand-blue" />
                <span>Unggah Materi Pembelajaran</span>
              </h3>
              <button
                onClick={() => setIsModalOpen(false)}
                className="text-slate-400 hover:text-slate-600 p-1 rounded-lg"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            <form onSubmit={handleSubmit} className="space-y-4">
              {modalError && (
                <div className="p-3 bg-red-50 border border-red-200 rounded-lg text-status-danger text-xs font-bold flex items-center gap-2">
                  <CheckCircle2 className="h-4 w-4 shrink-0 rotate-45 text-red-500" />
                  <span>{modalError}</span>
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
                  Judul Materi / Modul <span className="text-red-500">*</span>
                </label>
                <Input
                  type="text"
                  placeholder="Contoh: Modul Ajar Bab 3: Bangun Ruang Sisi Datar"
                  value={title}
                  onChange={(e) => setTitle(e.target.value)}
                  className="h-9 text-xs"
                  required
                />
              </div>

              <div>
                <label className="block text-xs font-bold text-slate-700 mb-1.5">
                  Target Kelas Pembaca <span className="text-red-500">*</span>
                </label>
                {availableClasses.length === 0 ? (
                  <p className="text-xs text-slate-400 italic">Tidak ada kelas yang tersedia untuk mata pelajaran ini.</p>
                ) : (
                  <div className="flex items-center gap-2 flex-wrap">
                    {availableClasses.map((c) => {
                      const isChecked = selectedClassIds.includes(c.id);
                      return (
                        <button
                          key={c.id}
                          type="button"
                          onClick={() => handleClassToggle(c.id)}
                          className={`px-3 py-1.5 rounded-lg text-xs font-bold border transition-colors ${
                            isChecked
                              ? 'bg-blue-50 text-brand-blue border-brand-blue'
                              : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'
                          }`}
                        >
                          {c.name}
                        </button>
                      );
                    })}
                  </div>
                )}
              </div>

              <div>
                <label className="block text-xs font-bold text-slate-700 mb-1">Deskripsi & Petunjuk Belajar</label>
                <Textarea
                  placeholder="Tuliskan petunjuk pembelajaran bagi siswa..."
                  value={description}
                  onChange={(e) => setDescription(e.target.value)}
                  className="text-xs h-20"
                />
              </div>

              <div>
                <label className="block text-xs font-bold text-slate-700 mb-1">URL Dokumen / File Lampiran</label>
                <Input
                  type="text"
                  placeholder="Contoh: /documents/modul-bangun-ruang.pdf atau link Google Drive"
                  value={fileUrl}
                  onChange={(e) => setFileUrl(e.target.value)}
                  className="h-9 text-xs"
                />
              </div>

              <div className="flex justify-end gap-2 pt-3 border-t border-slate-100">
                <Button type="button" variant="outline" size="sm" onClick={() => setIsModalOpen(false)}>
                  Batal
                </Button>
                <Button
                  type="submit"
                  size="sm"
                  isLoading={isSubmitting}
                  disabled={!title.trim() || !selectedSubjectId || selectedClassIds.length === 0}
                  className="font-bold"
                >
                  Unggah Materi
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
