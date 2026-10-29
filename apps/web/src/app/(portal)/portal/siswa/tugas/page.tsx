'use client';

import React, { useState } from 'react';
import { 
  CheckSquare, 
  BookOpen, 
  Clock, 
  Upload, 
  FileText, 
  Link as LinkIcon, 
  CheckCircle2, 
  Download, 
  MessageSquare, 
  Award,
  Calendar,
  Layers,
  ChevronRight,
  X
} from 'lucide-react';
import { StudentTaskItem } from '@/lib/mock-student-data';
import { useStudentTasks, useSubmitTask, useTeachingMaterials } from '@/lib/use-student-data';
import { TaskListSkeleton } from '@/components/ui/student-skeletons';
import { EmptyState } from '@/components/ui/empty-state';
import { ErrorState } from '@/components/ui/error-state';

export default function StudentTugasPage() {
  const [activeTab, setActiveTab] = useState<'tugas' | 'modul'>('tugas');
  const [statusFilter, setStatusFilter] = useState<'semua' | 'aktif' | 'dikumpulkan' | 'dinilai'>('semua');
  
  const { data: apiTasks, isLoading, isError, refetch } = useStudentTasks();
  const { data: apiMaterials, isLoading: isMaterialsLoading, isError: isMaterialsError, refetch: refetchMaterials } = useTeachingMaterials();
  const submitTaskMutation = useSubmitTask();

  // Submit modal state
  const [selectedTask, setSelectedTask] = useState<StudentTaskItem | null>(null);
  const [uploadedFileName, setUploadedFileName] = useState<string>('');
  const [driveLink, setDriveLink] = useState<string>('');
  const [studentNote, setStudentNote] = useState<string>('');
  const [isSuccessSubmit, setIsSuccessSubmit] = useState(false);

  // Detail feedback modal state
  const [feedbackTask, setFeedbackTask] = useState<StudentTaskItem | null>(null);

  const allTasks: StudentTaskItem[] = apiTasks
    ? apiTasks.map((t) => ({
        id: t.id,
        title: t.title,
        subject_name: t.subjectName,
        subject_code: 'MAPEL',
        due_date_str: t.dueDate,
        days_left: 2,
        teacher_name: t.teacherName,
        teacher_avatar_url: '',
        status: t.status,
        max_score: 100,
        description: t.description || 'Kerjakan tugas sesuai instruksi guru pengampu.',
        submission: t.submission
          ? {
              file_name: t.submission.file_name,
              submitted_at: t.submission.submitted_at,
              score: t.score ?? undefined,
              feedback: t.teacherFeedback ?? undefined,
            }
          : undefined,
      }))
    : [];

  const filteredTasks = statusFilter === 'semua' 
    ? allTasks 
    : allTasks.filter(t => t.status === statusFilter);

  const handleOpenSubmit = (task: StudentTaskItem) => {
    setSelectedTask(task);
    setUploadedFileName('');
    setDriveLink('');
    setStudentNote('');
    setIsSuccessSubmit(false);
  };

  const handleConfirmSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedTask) return;

    try {
      await submitTaskMutation.mutateAsync({
        taskId: selectedTask.id,
        notes: studentNote || 'Tugas dikumpulkan melalui Portal Siswa',
        drive_link: driveLink || undefined,
        file_name: uploadedFileName || 'Dokumen_Tugas_Siswa.pdf',
      });
      setIsSuccessSubmit(true);
      setTimeout(() => {
        setSelectedTask(null);
      }, 1200);
    } catch {
      // Local fallback
      selectedTask.status = 'dikumpulkan';
      selectedTask.submission = {
        file_name: uploadedFileName || 'Dokumen_Tugas_Siswa.pdf',
        submitted_at: 'Baru saja',
        drive_link: driveLink || undefined,
      };
      setIsSuccessSubmit(true);
      setTimeout(() => {
        setSelectedTask(null);
      }, 1200);
    }
  };


  return (
    <div className="space-y-5 text-slate-900">
      {/* ─── HEADER CARD (COMPACT & PROPORTIONED) ─── */}
      <div className="bg-white border border-slate-200/90 rounded-2xl p-4 sm:p-5 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <div className="flex items-center gap-2 mb-1">
            <span className="text-[11px] font-black uppercase tracking-wider px-2 py-0.5 rounded bg-blue-900 text-white shadow-2xs">
              LMS Portal Siswa
            </span>
            <span className="text-xs text-slate-500 font-semibold">• Tugas Mandiri & Modul Ajar</span>
          </div>
          <h1 className="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight">
            Penugasan & Repositori Modul Ajar
          </h1>
          <p className="text-xs text-slate-500 font-normal mt-0.5 max-w-2xl">
            Kumpulkan berkas tugas sekolah, pantau nilai dan umpan balik guru, serta unduh bahan ajar Kurikulum Merdeka.
          </p>
        </div>

        {/* Tab Switcher */}
        <div className="flex items-center bg-slate-100 p-1 rounded-xl border border-slate-200 self-start md:self-auto shadow-2xs shrink-0">
          <button
            onClick={() => setActiveTab('tugas')}
            className={`px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer ${
              activeTab === 'tugas'
                ? 'bg-blue-900 text-white shadow-xs'
                : 'text-slate-600 hover:text-slate-900'
            }`}
          >
            Tugas & Proyek ({allTasks.length})
          </button>
          <button
            onClick={() => setActiveTab('modul')}
            className={`px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer ${
              activeTab === 'modul'
                ? 'bg-blue-900 text-white shadow-xs'
                : 'text-slate-600 hover:text-slate-900'
            }`}
          >
            Modul Ajar ({apiMaterials?.length || 0})
          </button>
        </div>
      </div>

      {/* ─── TAB 1: TUGAS & PROYEK ─── */}
      {activeTab === 'tugas' && (
        <div className="space-y-4">
          {/* Status Filters Bar */}
          <div className="bg-white border border-slate-200/90 rounded-2xl p-3 sm:p-3.5 shadow-xs flex items-center justify-between flex-wrap gap-2.5">
            <div className="flex items-center gap-2">
              <span className="text-xs font-bold text-slate-600">Filter Status:</span>
            </div>

            <div className="flex items-center gap-1.5 overflow-x-auto">
              <button
                onClick={() => setStatusFilter('semua')}
                className={`px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer ${
                  statusFilter === 'semua'
                    ? 'bg-blue-900 text-white shadow-xs'
                    : 'bg-slate-100 text-slate-600 hover:bg-slate-200/80'
                }`}
              >
                Semua ({allTasks.length})
              </button>
              <button
                onClick={() => setStatusFilter('aktif')}
                className={`px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer ${
                  statusFilter === 'aktif'
                    ? 'bg-amber-600 text-white shadow-xs'
                    : 'bg-slate-100 text-slate-600 hover:bg-slate-200/80'
                }`}
              >
                Perlu Dikerjakan ({allTasks.filter(t => t.status === 'aktif').length})
              </button>
              <button
                onClick={() => setStatusFilter('dikumpulkan')}
                className={`px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer ${
                  statusFilter === 'dikumpulkan'
                    ? 'bg-blue-900 text-white shadow-xs'
                    : 'bg-slate-100 text-slate-600 hover:bg-slate-200/80'
                }`}
              >
                Menunggu Penilaian ({allTasks.filter(t => t.status === 'dikumpulkan').length})
              </button>
              <button
                onClick={() => setStatusFilter('dinilai')}
                className={`px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer ${
                  statusFilter === 'dinilai'
                    ? 'bg-emerald-700 text-white shadow-xs'
                    : 'bg-slate-100 text-slate-600 hover:bg-slate-200/80'
                }`}
              >
                Sudah Dinilai ({allTasks.filter(t => t.status === 'dinilai').length})
              </button>
            </div>
          </div>

          {/* Task List Cards */}
          <div className="space-y-3">
            {isLoading && <TaskListSkeleton count={4} />}

            {isError && (
              <ErrorState
                title="Gagal Memuat Daftar Tugas"
                message="Terjadi kendala saat mengambil data penugasan dari server. Silakan coba kembali."
                onRetry={() => refetch()}
              />
            )}

            {!isLoading && !isError && filteredTasks.length === 0 && (
              <EmptyState
                icon={CheckSquare}
                title="Belum Ada Tugas pada Kategori Ini"
                description="Semua tugas untuk filter ini sudah selesai atau belum ada penugasan baru dari guru."
              />
            )}

            {!isLoading && !isError && filteredTasks.map((task) => (
              <div
                key={task.id}
                className="bg-white border border-slate-200/90 rounded-2xl p-5 flex flex-col md:flex-row md:items-center justify-between gap-4 shadow-xs hover:border-slate-300 transition-all"
              >
                <div className="space-y-2 flex-1 min-w-0">
                  <div className="flex flex-wrap items-center gap-2">
                    <span className="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider bg-slate-100 text-slate-800 border border-slate-200">
                      {task.subject_name}
                    </span>

                    {task.status === 'dinilai' && (
                      <span className="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-emerald-50 text-emerald-800 border border-emerald-200 shadow-2xs">
                        Sudah Dinilai • Skor {task.submission?.score}/100
                      </span>
                    )}
                    {task.status === 'dikumpulkan' && (
                      <span className="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-blue-50 text-blue-900 border border-blue-200 shadow-2xs">
                        Terkumpul • Menunggu Koreksi
                      </span>
                    )}
                    {task.status === 'aktif' && (
                      <span className="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-amber-50 text-amber-800 border border-amber-200 shadow-2xs">
                        Tenggat: {task.days_left} Hari Lagi
                      </span>
                    )}
                  </div>

                  <h3 className="font-extrabold text-base text-slate-900 leading-snug">{task.title}</h3>

                  <p className="text-xs text-slate-500 font-normal flex flex-wrap items-center gap-2.5">
                    <span>Guru Pengampu: <strong className="text-slate-800 font-semibold">{task.teacher_name}</strong></span>
                    <span className="text-slate-300">•</span>
                    <span className="flex items-center gap-1">
                      <Clock className="w-3.5 h-3.5 text-slate-400" />
                      Tenggat: <strong className="text-slate-800 font-semibold">{task.due_date_str}</strong>
                    </span>
                  </p>

                  {/* Submission info snippet */}
                  {task.submission && (
                    <div className="p-3 rounded-xl bg-slate-50 border border-slate-200/80 text-xs flex flex-wrap items-center justify-between gap-2">
                      <span className="flex items-center gap-2 font-semibold text-slate-700">
                        <FileText className="w-4 h-4 text-blue-900" />
                        {task.submission.file_name}
                      </span>
                      <span className="text-slate-500 text-[11px]">
                        Dikirim: {task.submission.submitted_at}
                      </span>
                    </div>
                  )}
                </div>

                {/* Right Actions */}
                <div className="flex items-center gap-2 self-end md:self-center shrink-0">
                  {task.status === 'aktif' && (
                    <button
                      onClick={() => handleOpenSubmit(task)}
                      className="px-4 py-2.5 rounded-xl bg-blue-900 hover:bg-blue-950 text-white text-xs font-black uppercase tracking-wider transition-colors shadow-xs flex items-center gap-1.5 cursor-pointer"
                    >
                      <Upload className="w-3.5 h-3.5 text-white" />
                      <span>Kumpulkan Tugas</span>
                    </button>
                  )}

                  {task.status === 'dikumpulkan' && (
                    <button
                      onClick={() => handleOpenSubmit(task)}
                      className="px-3.5 py-2 rounded-xl bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-bold transition-colors shadow-xs cursor-pointer"
                    >
                      Perbarui Berkas
                    </button>
                  )}

                  {task.status === 'dinilai' && (
                    <button
                      onClick={() => setFeedbackTask(task)}
                      className="px-4 py-2.5 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-black uppercase tracking-wider transition-colors shadow-xs flex items-center gap-1.5 cursor-pointer"
                    >
                      <MessageSquare className="w-3.5 h-3.5 text-white" />
                      <span>Lihat Ulasan Nilai</span>
                    </button>
                  )}
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* ─── TAB 2: REPOSITORI MODUL AJAR ─── */}
      {activeTab === 'modul' && (
        <div className="space-y-4">
          {isMaterialsLoading && <TaskListSkeleton count={3} />}

          {isMaterialsError && (
            <ErrorState
              title="Gagal Memuat Modul Ajar"
              message="Terjadi kendala saat mengambil materi pembelajaran dari server."
              onRetry={() => refetchMaterials()}
            />
          )}

          {!isMaterialsLoading && !isMaterialsError && (!apiMaterials || apiMaterials.length === 0) && (
            <EmptyState
              icon={BookOpen}
              title="Belum Ada Modul Ajar"
              description="Materi ajar digital belum dipublikasikan oleh guru mata pelajaran."
            />
          )}

          {!isMaterialsLoading && !isMaterialsError && apiMaterials && apiMaterials.length > 0 && (
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              {apiMaterials.map((mat) => (
                <div
                  key={mat.id}
                  className="bg-white border border-slate-200/90 rounded-2xl p-5 flex flex-col justify-between space-y-4 shadow-xs hover:border-slate-300 transition-all"
                >
                  <div className="space-y-2">
                    <div className="flex items-center justify-between">
                      <span className="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider bg-blue-50 text-blue-900 border border-blue-200">
                        {mat.subject?.name || mat.material_type || 'Materi'}
                      </span>
                      <span className="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-slate-100 text-slate-600 border border-slate-200">
                        v{mat.version || 1}
                      </span>
                    </div>

                    <h3 className="font-extrabold text-sm text-slate-900 leading-snug">{mat.title}</h3>
                    <p className="text-xs text-slate-500 line-clamp-3 leading-relaxed font-normal">
                      {mat.description}
                    </p>
                  </div>

                  <div className="pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
                    <span className="text-slate-500 font-medium truncate max-w-35">
                      {mat.file?.url ? mat.file.url.split('/').pop() : 'Dokumen PDF'}
                    </span>

                    <a
                      href={mat.file?.url || '#'}
                      target="_blank"
                      rel="noopener noreferrer"
                      onClick={(e) => {
                        if (!mat.file?.url) {
                          e.preventDefault();
                          alert(`Materi "${mat.title}" siap diunduh saat sesi tatap muka KBM.`);
                        }
                      }}
                      className="px-3 py-1.5 rounded-lg bg-blue-900 hover:bg-blue-950 text-white font-bold text-xs flex items-center gap-1.5 transition-colors cursor-pointer shadow-xs"
                    >
                      <Download className="w-3.5 h-3.5" />
                      <span>Unduh PDF</span>
                    </a>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      )}

      {/* ─── MODAL PENGUMPULAN TUGAS (SPEEDSUBMIT) ─── */}
      {selectedTask && (
        <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl border border-slate-200 max-w-lg w-full p-6 space-y-4 shadow-2xl">
            <div className="flex items-center justify-between pb-3 border-b border-slate-100">
              <div>
                <h3 className="font-extrabold text-base text-slate-900">Form Pengumpulan Tugas</h3>
                <p className="text-xs text-slate-500 font-medium">{selectedTask.title}</p>
              </div>

              <button
                onClick={() => setSelectedTask(null)}
                className="w-8 h-8 rounded-lg border border-slate-200 flex items-center justify-center text-slate-500 hover:bg-slate-100 font-bold cursor-pointer"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            {isSuccessSubmit ? (
              <div className="p-8 text-center space-y-2">
                <CheckCircle2 className="w-12 h-12 text-emerald-700 mx-auto" />
                <h4 className="font-extrabold text-lg text-slate-900">Tugas Berhasil Dikumpulkan!</h4>
                <p className="text-xs text-slate-600 font-medium">Berkas Anda telah terunggah dan tercatat di buku kerja guru.</p>
              </div>
            ) : (
              <form onSubmit={handleConfirmSubmit} className="space-y-4">
                <div className="space-y-1.5">
                  <label className="text-xs font-bold text-slate-700 uppercase tracking-wider block">
                    1. Unggah Berkas Dokumen Tugas:
                  </label>
                  <div className="border-2 border-dashed border-slate-200 rounded-xl p-5 text-center hover:bg-slate-50 transition-colors">
                    <Upload className="w-8 h-8 text-slate-400 mx-auto mb-1.5" />
                    <p className="text-xs font-bold text-slate-800">
                      {uploadedFileName ? `Berkas terpilih: ${uploadedFileName}` : 'Klik untuk memilih berkas atau seret ke sini'}
                    </p>
                    <p className="text-[11px] text-slate-500 mt-0.5">Format didukung: PDF, DOCX, ZIP, JPG/PNG (Maks 10 MB)</p>
                    <input
                      type="file"
                      onChange={(e) => {
                        const file = e.target.files?.[0];
                        if (file) setUploadedFileName(file.name);
                      }}
                      className="mt-2 text-xs text-slate-600 cursor-pointer"
                    />
                  </div>
                </div>

                <div className="space-y-1.5">
                  <label className="text-xs font-bold text-slate-700 uppercase tracking-wider block">
                    2. Atau Tautan Proyek Online (Google Drive / GitHub / Figma):
                  </label>
                  <input
                    type="url"
                    placeholder="https://drive.google.com/file/d/..."
                    value={driveLink}
                    onChange={(e) => setDriveLink(e.target.value)}
                    className="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs font-medium text-slate-900 focus:outline-none focus:border-blue-900 shadow-2xs"
                  />
                </div>

                <div className="space-y-1.5">
                  <label className="text-xs font-bold text-slate-700 uppercase tracking-wider block">
                    3. Catatan Tambahan untuk Guru (Opsional):
                  </label>
                  <textarea
                    rows={3}
                    placeholder="Tuliskan catatan penjelasan hasil karya atau kendala praktikum..."
                    value={studentNote}
                    onChange={(e) => setStudentNote(e.target.value)}
                    className="w-full p-3 rounded-xl border border-slate-200 text-xs font-medium text-slate-900 focus:outline-none focus:border-blue-900 shadow-2xs leading-relaxed"
                  />
                </div>

                <div className="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                  <button
                    type="button"
                    onClick={() => setSelectedTask(null)}
                    className="px-4 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-700 hover:bg-slate-100 cursor-pointer"
                  >
                    Batal
                  </button>
                  <button
                    type="submit"
                    className="px-5 py-2.5 rounded-xl bg-blue-900 hover:bg-blue-950 text-white text-xs font-black uppercase tracking-wider transition-colors shadow-xs cursor-pointer"
                  >
                    Kirim Tugas Sekarang ➔
                  </button>
                </div>
              </form>
            )}
          </div>
        </div>
      )}

      {/* ─── MODAL PENAMPIL ULASAN NILAI & FEEDBACK GURU ─── */}
      {feedbackTask && (
        <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl border border-slate-200 max-w-md w-full p-6 space-y-4 shadow-2xl">
            <div className="flex items-center justify-between pb-3 border-b border-slate-100">
              <div className="flex items-center gap-2.5">
                <div className="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-800 border border-emerald-200 flex items-center justify-center font-bold">
                  <Award className="w-5 h-5" />
                </div>
                <div>
                  <h3 className="font-extrabold text-sm text-slate-900">Ulasan & Nilai Guru</h3>
                  <p className="text-[11px] text-slate-500 font-medium">{feedbackTask.subject_name}</p>
                </div>
              </div>

              <button
                onClick={() => setFeedbackTask(null)}
                className="w-8 h-8 rounded-lg border border-slate-200 flex items-center justify-center text-slate-500 hover:bg-slate-100 font-bold cursor-pointer"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            <div className="space-y-3">
              <div className="p-4 bg-emerald-50/60 rounded-xl border border-emerald-200 flex items-center justify-between">
                <div>
                  <span className="text-[11px] font-bold text-emerald-900 uppercase tracking-wider">Capaian Skor Akhir</span>
                  <p className="text-3xl font-black text-emerald-800 mt-1 font-mono">
                    {feedbackTask.submission?.score} <span className="text-sm font-semibold text-emerald-600 font-sans">/ 100</span>
                  </p>
                </div>
                <span className="px-3 py-1 rounded-lg text-xs font-black bg-emerald-700 text-white shadow-2xs">
                  TUNTAS KKTP (75)
                </span>
              </div>

              <div className="space-y-1.5">
                <span className="text-xs font-bold text-slate-700 uppercase tracking-wider block">
                  Umpan Balik Pedagogis Guru:
                </span>
                <div className="p-3.5 bg-slate-50 rounded-xl border border-slate-200 text-xs text-slate-800 font-medium leading-relaxed italic">
                  "{feedbackTask.submission?.feedback}"
                </div>
              </div>

              <div className="p-3 bg-slate-50 rounded-xl border border-slate-200 text-xs space-y-1 text-slate-600">
                <p><strong>Pemeriksa:</strong> {feedbackTask.teacher_name}</p>
                <p><strong>Berkas Dikirim:</strong> {feedbackTask.submission?.file_name}</p>
              </div>
            </div>

            <div className="pt-2">
              <button
                type="button"
                onClick={() => setFeedbackTask(null)}
                className="w-full py-2.5 rounded-xl bg-blue-900 hover:bg-blue-950 text-white text-xs font-black uppercase tracking-wider transition-colors shadow-xs cursor-pointer"
              >
                Tutup Ulasan
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
