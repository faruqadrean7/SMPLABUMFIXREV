'use client';

import React, { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { 
  Plus, 
  Search, 
  FileText, 
  Download, 
  FolderOpen, 
  UploadCloud, 
  X, 
  Layers,
  BookmarkCheck
} from 'lucide-react';
import { lmsApi } from '@/lib/api-client';
import { MaterialCategory } from '@/types/lms';
import { useTeacherClasses, useTeacherSubjects } from '@/lib/use-teacher-data';
import { toast } from 'sonner';

export default function GuruMateriPage() {
  const queryClient = useQueryClient();
  const [searchQuery, setSearchQuery] = useState('');
  const [selectedClassFilter, setSelectedClassFilter] = useState('all');
  const [selectedCategoryFilter, setSelectedCategoryFilter] = useState<string>('all');
  const [isModalOpen, setIsModalOpen] = useState(false);

  const { data: teacherClasses = [] } = useTeacherClasses();
  const { data: teacherSubjects = [] } = useTeacherSubjects();

  // Form state
  const [title, setTitle] = useState('');
  const [description, setDescription] = useState('');
  const [category, setCategory] = useState<MaterialCategory>('modul_ajar');
  const [version, setVersion] = useState('v1.0');
  const [classId, setClassId] = useState('');
  const [subjectId, setSubjectId] = useState('');
  const [selectedFile, setSelectedFile] = useState<File | null>(null);

  React.useEffect(() => {
    if (!classId && teacherClasses.length > 0) {
      setClassId(teacherClasses[0].id);
    }
  }, [teacherClasses, classId]);

  React.useEffect(() => {
    if (!subjectId && teacherSubjects.length > 0) {
      setSubjectId(teacherSubjects[0].id);
    }
  }, [teacherSubjects, subjectId]);

  // Fetch materials
  const { data: materials = [], isLoading, isError, refetch } = useQuery({
    queryKey: ['materials'],
    queryFn: () => lmsApi.getMaterials(),
  });

  // Mutation to create material
  const createMutation = useMutation({
    mutationFn: (formData: FormData) => lmsApi.createMaterial(formData),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['materials'] });
      toast.success('Modul ajar berhasil diunggah ke repositori perangkat ajar!');
      setIsModalOpen(false);
      setTitle('');
      setDescription('');
      setSelectedFile(null);
    },
    onError: () => {
      toast.error('Gagal mengunggah modul ajar.');
    }
  });

  const handleFormSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!title.trim()) {
      toast.error('Judul modul ajar wajib diisi.');
      return;
    }
    const fd = new FormData();
    fd.append('title', title);
    fd.append('description', description);
    fd.append('category', category);
    fd.append('version', version);
    fd.append('class_id', classId);
    fd.append('subject_id', subjectId);
    if (selectedFile) {
      fd.append('file', selectedFile);
    }
    createMutation.mutate(fd);
  };

  const filteredMaterials = materials.filter((m) => {
    const matchesSearch = m.title.toLowerCase().includes(searchQuery.toLowerCase()) ||
      (m.description && m.description.toLowerCase().includes(searchQuery.toLowerCase()));
    const matchesClass = selectedClassFilter === 'all' || m.class_id === selectedClassFilter || m.class?.id === selectedClassFilter;
    const matchesCategory = selectedCategoryFilter === 'all' || m.category === selectedCategoryFilter;
    return matchesSearch && matchesClass && matchesCategory;
  });

  const getCategoryBadge = (cat?: MaterialCategory) => {
    switch (cat) {
      case 'atp':
        return <span className="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider bg-blue-900 text-white shadow-xs">ATP (Alur Tujuan)</span>;
      case 'p5':
        return <span className="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider bg-purple-900 text-white shadow-xs">Modul Proyek P5</span>;
      case 'slide_tayang':
        return <span className="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider bg-slate-800 text-white shadow-xs">Slide Bahan Tayang</span>;
      default:
        return <span className="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider bg-teal-800 text-white shadow-xs">Modul Ajar RPP</span>;
    }
  };

  return (
    <div className="flex flex-col gap-3 max-w-full xl:h-[calc(100vh-112px)] xl:overflow-hidden text-slate-900">
      {/* ─── PAGE HEADER & CTA ─── */}
      <div className="bg-white border border-slate-200/90 rounded-2xl p-3 sm:p-3.5 shadow-xs shrink-0 flex flex-col md:flex-row md:items-center justify-between gap-3">
        <div>
          <div className="flex items-center gap-2 mb-1">
            <span className="text-[11px] font-black uppercase tracking-wider px-2 py-0.5 rounded bg-teal-800 text-white shadow-2xs">
              Bank Modul Ajar
            </span>
            <span className="text-xs text-slate-500 font-semibold">• BE-LMS-03</span>
          </div>
          <h1 className="text-lg sm:text-xl font-black text-slate-900 tracking-tight">
            Bank Modul Ajar & Perangkat KBM
          </h1>
          <p className="text-xs text-slate-600 mt-0.5 line-clamp-1 max-w-2xl">
            Repositori resmi Modul Ajar, ATP, modul Proyek P5, dan bahan tayang Kurikulum Merdeka Labschool UM.
          </p>
        </div>

        <button
          onClick={() => setIsModalOpen(true)}
          className="inline-flex items-center justify-center gap-1.5 px-3.5 py-1.5 rounded-lg bg-teal-800 text-white text-xs font-black hover:bg-teal-900 transition-all shadow-xs cursor-pointer shrink-0"
        >
          <Plus className="w-4 h-4" />
          <span>+ Unggah Perangkat Ajar</span>
        </button>
      </div>

      {/* ─── SEARCH & CATEGORY FILTER TABS ─── */}
      <div className="bg-white border border-slate-200/90 rounded-xl p-2.5 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-2.5 shrink-0">
        {/* Search Field */}
        <div className="relative flex-1 max-w-md">
          <Search className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
          <input
            type="text"
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            placeholder="Cari materi, ATP, bab, atau kata kunci modul..."
            className="w-full pl-9 pr-4 py-1.5 text-xs bg-white border border-slate-200 rounded-lg focus:outline-none focus:border-teal-800 font-medium"
          />
        </div>

        {/* Filters: Category & Class */}
        <div className="flex flex-wrap items-center gap-2">
          {/* Category Filter */}
          <div className="flex items-center gap-1.5">
            <span className="text-xs font-bold text-slate-700">Kategori:</span>
            <select
              value={selectedCategoryFilter}
              onChange={(e) => setSelectedCategoryFilter(e.target.value)}
              className="px-2.5 py-1 text-xs font-bold bg-white border border-slate-200 rounded-lg focus:outline-none focus:border-teal-800 cursor-pointer"
            >
              <option value="all">Semua Kategori</option>
              <option value="modul_ajar">Modul Ajar RPP</option>
              <option value="atp">Alur Tujuan Pembelajaran (ATP)</option>
              <option value="p5">Proyek Penguatan Profil (P5)</option>
              <option value="slide_tayang">Slide Bahan Tayang</option>
            </select>
          </div>

          {/* Class Filter */}
          <div className="flex items-center gap-1.5">
            <span className="text-xs font-bold text-slate-700">Rombel:</span>
            <select
              value={selectedClassFilter}
              onChange={(e) => setSelectedClassFilter(e.target.value)}
              className="px-2.5 py-1 text-xs font-bold bg-white border border-slate-200 rounded-lg focus:outline-none focus:border-teal-800 cursor-pointer"
            >
              <option value="all">Semua Kelas</option>
              {teacherClasses.map((c) => (
                <option key={c.id} value={c.id}>Kelas {c.name}</option>
              ))}
            </select>
          </div>
        </div>
      </div>

      {/* ─── MATERIALS GRID CONTAINER (SCROLLABLE INSIDE VIEWPORT) ─── */}
      <div className="flex-1 min-h-0 overflow-y-auto pr-1">
        {isLoading ? (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3.5">
            {[1, 2, 3].map((n) => (
              <div key={n} className="bg-white border border-slate-200/90 rounded-xl p-5 space-y-3">
                <div className="h-4 bg-slate-200 rounded w-2/3"></div>
                <div className="h-16 bg-slate-200 rounded"></div>
                <div className="h-8 bg-slate-200 rounded"></div>
              </div>
            ))}
          </div>
        ) : filteredMaterials.length === 0 ? (
          <div className="bg-white border border-slate-200/90 rounded-2xl p-12 text-center space-y-4 shadow-xs">
            <div className="w-14 h-14 rounded-xl bg-teal-800 text-white flex items-center justify-center mx-auto shadow-xs">
              <FolderOpen className="w-7 h-7" />
            </div>
            <div>
              <h3 className="text-base font-bold text-slate-900">Belum ada perangkat ajar dalam filter ini</h3>
              <p className="text-sm text-slate-600 max-w-md mx-auto mt-1 font-medium">
                Unggah modul ajar, ATP, atau silabus Kurikulum Merdeka untuk dibagikan ke siswa dan guru serumpun.
              </p>
            </div>
            <button
              onClick={() => setIsModalOpen(true)}
              className="inline-flex items-center gap-2 px-4 py-2 text-xs font-bold rounded-lg bg-teal-800 text-white hover:bg-teal-900 shadow-xs"
            >
              <Plus className="w-4 h-4" />
              <span>Unggah Perangkat Ajar</span>
            </button>
          </div>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3.5">
          {filteredMaterials.map((mat) => {
            const dateStr = new Date(mat.created_at).toLocaleDateString('id-ID', {
              day: 'numeric',
              month: 'short',
              year: 'numeric',
            });

            const borderAccent = mat.category === 'atp' 
              ? 'border-l-blue-900' 
              : mat.category === 'p5' 
              ? 'border-l-purple-900' 
              : 'border-l-teal-800';

            return (
              <div 
                key={mat.id} 
                className={`bg-white border border-slate-200/90 rounded-xl p-5 flex flex-col justify-between group hover:border-teal-800 transition-all shadow-xs border-l-4 ${borderAccent}`}
              >
                <div>
                  {/* Top Category & Version Badge */}
                  <div className="flex items-center justify-between gap-2 mb-3">
                    {getCategoryBadge(mat.category)}
                    <span className="text-[10px] font-mono font-bold px-2 py-0.5 rounded bg-slate-100 text-slate-900 border border-slate-200">
                      {mat.version || 'v1.0'}
                    </span>
                  </div>

                  {/* Title & Description */}
                  <h3 className="font-extrabold text-sm text-slate-900 group-hover:text-teal-900 transition-colors line-clamp-2 leading-snug">
                    {mat.title}
                  </h3>
                  <p className="text-xs text-slate-600 font-medium line-clamp-3 mt-2 leading-relaxed">
                    {mat.description || 'Tidak ada deskripsi modul.'}
                  </p>

                  <div className="mt-3 flex items-center gap-2 text-[11px] font-bold text-slate-700">
                    <span className="px-2 py-0.5 rounded bg-slate-100 border border-slate-200 text-slate-800">
                      {mat.subject?.name || 'Informatika'}
                    </span>
                    <span>•</span>
                    <span className="text-slate-600">{mat.class?.name || 'Kelas IX-A'}</span>
                  </div>
                </div>

                {/* File Attachment & Actions */}
                <div className="mt-4 pt-3.5 border-t border-slate-100 flex items-center justify-between">
                  <div className="flex items-center gap-2 text-xs text-slate-700 font-bold truncate max-w-40">
                    <FileText className="w-4 h-4 text-blue-900 shrink-0" />
                    <span className="truncate">
                      {mat.file?.filename || 'Modul_Ajar.pdf'}
                    </span>
                  </div>

                  <button
                    onClick={() => toast.success(`Membuka berkas: ${mat.file?.filename || mat.title}`)}
                    className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-bold bg-slate-900 hover:bg-teal-900 text-white transition-colors shadow-xs cursor-pointer"
                  >
                    <Download className="w-3.5 h-3.5" />
                    <span>Unduh</span>
                  </button>
                </div>
              </div>
            );
          })}
        </div>
      )}
      </div>

      {/* ─── MODAL UNGGAH MODUL AJAR ─── */}
      {isModalOpen && (
        <div className="fixed inset-0 bg-slate-900/75 z-50 flex items-center justify-center p-4">
          <div className="bg-white w-full max-w-lg rounded-xl shadow-2xl overflow-hidden border border-slate-300 animate-in fade-in duration-150">
            {/* Modal Header */}
            <div className="px-6 py-4 bg-teal-800 text-white flex items-center justify-between">
              <div>
                <h3 className="font-extrabold text-base text-white">Unggah Perangkat Ajar Baru</h3>
                <p className="text-xs text-teal-200 font-medium">Kurikulum Merdeka • SMP-SMA Laboratorium UM</p>
              </div>
              <button
                onClick={() => setIsModalOpen(false)}
                className="w-8 h-8 rounded-lg flex items-center justify-center text-white hover:bg-teal-950 transition-colors cursor-pointer"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            {/* Modal Body Form */}
            <form onSubmit={handleFormSubmit} className="p-6 space-y-4 text-slate-900">
              <div>
                <label className="block text-xs font-extrabold text-slate-900 uppercase tracking-wider mb-1.5">
                  Judul Dokumen / Modul Ajar <span className="text-rose-600">*</span>
                </label>
                <input
                  type="text"
                  required
                  value={title}
                  onChange={(e) => setTitle(e.target.value)}
                  placeholder="Contoh: Modul Ajar 04: Query SQL & Normalisasi Tabel Relasional"
                  className="w-full px-3.5 py-2 text-sm bg-white border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-800 font-medium"
                />
              </div>

              {/* Category & Versioning */}
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-extrabold text-slate-900 uppercase tracking-wider mb-1.5">
                    Kategori Dokumen <span className="text-rose-600">*</span>
                  </label>
                  <select
                    value={category}
                    onChange={(e) => setCategory(e.target.value as MaterialCategory)}
                    className="w-full px-3 py-2 text-sm bg-white border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-800 font-bold"
                  >
                    <option value="modul_ajar">Modul Ajar RPP</option>
                    <option value="atp">Alur Tujuan (ATP)</option>
                    <option value="p5">Modul Proyek P5</option>
                    <option value="slide_tayang">Slide Bahan Tayang</option>
                  </select>
                </div>

                <div>
                  <label className="block text-xs font-extrabold text-slate-900 uppercase tracking-wider mb-1.5">
                    Versi Dokumen <span className="text-rose-600">*</span>
                  </label>
                  <input
                    type="text"
                    required
                    value={version}
                    onChange={(e) => setVersion(e.target.value)}
                    placeholder="Contoh: v1.0 atau v2.1"
                    className="w-full px-3 py-2 text-sm bg-white border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-800 font-mono font-bold"
                  />
                </div>
              </div>

              {/* Class & Subject */}
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-extrabold text-slate-900 uppercase tracking-wider mb-1.5">
                    Target Rombel <span className="text-rose-600">*</span>
                  </label>
                  <select
                    value={classId}
                    onChange={(e) => setClassId(e.target.value)}
                    className="w-full px-3 py-2 text-sm bg-white border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-800 font-medium"
                  >
                    {teacherClasses.map((c) => (
                      <option key={c.id} value={c.id}>Kelas {c.name}</option>
                    ))}
                  </select>
                </div>

                <div>
                  <label className="block text-xs font-extrabold text-slate-900 uppercase tracking-wider mb-1.5">
                    Mata Pelajaran <span className="text-rose-600">*</span>
                  </label>
                  <select
                    value={subjectId}
                    onChange={(e) => setSubjectId(e.target.value)}
                    className="w-full px-3 py-2 text-sm bg-white border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-800 font-medium"
                  >
                    {teacherSubjects.map((s) => (
                      <option key={s.id} value={s.id}>{s.name}</option>
                    ))}
                  </select>
                </div>
              </div>

              <div>
                <label className="block text-xs font-extrabold text-slate-900 uppercase tracking-wider mb-1.5">
                  Deskripsi / Capaian Pembelajaran
                </label>
                <textarea
                  rows={3}
                  value={description}
                  onChange={(e) => setDescription(e.target.value)}
                  placeholder="Ringkasan isi modul, domain CP yang dicapai, atau petunjuk belajar siswa..."
                  className="w-full px-3.5 py-2 text-sm bg-white border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-800 font-medium resize-none"
                />
              </div>

              <div>
                <label className="block text-xs font-extrabold text-slate-900 uppercase tracking-wider mb-1.5">
                  Pilih Berkas Dokumen (PDF) <span className="text-rose-600">*</span>
                </label>
                <label className="border-2 border-dashed border-slate-300 hover:border-teal-800 rounded-lg p-3.5 flex flex-col items-center justify-center bg-slate-50 cursor-pointer transition-colors">
                  <UploadCloud className="w-6 h-6 text-slate-500 mb-1" />
                  <span className="text-xs font-bold text-slate-800">
                    {selectedFile ? selectedFile.name : 'Pilih berkas dokumen PDF dari komputer'}
                  </span>
                  <span className="text-[11px] text-slate-500 font-medium mt-0.5">Format PDF (Maksimal 25 MB)</span>
                  <input
                    type="file"
                    accept=".pdf"
                    className="hidden"
                    onChange={(e) => {
                      if (e.target.files?.[0]) setSelectedFile(e.target.files[0]);
                    }}
                  />
                </label>
              </div>

              <div className="flex items-center justify-end gap-3 pt-3 border-t border-slate-200">
                <button
                  type="button"
                  onClick={() => setIsModalOpen(false)}
                  className="px-4 py-2 text-xs font-bold rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-100 transition-colors"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  disabled={createMutation.isPending}
                  className="px-5 py-2 text-xs font-bold rounded-lg bg-teal-800 text-white hover:bg-teal-900 transition-all shadow-xs disabled:opacity-50"
                >
                  {createMutation.isPending ? 'Mengunggah...' : 'Simpan & Publikasikan ➔'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
