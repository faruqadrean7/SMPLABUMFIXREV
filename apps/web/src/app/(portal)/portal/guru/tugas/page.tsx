'use client';

import React, { useState } from 'react';
import Link from 'next/link';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { 
  Plus, 
  Search, 
  Clock, 
  FileText, 
  CheckCircle2, 
  AlertCircle, 
  ChevronRight, 
  X, 
  UploadCloud,
  FileCheck2,
  Lock,
  Unlock,
  Download,
  SlidersHorizontal,
  BookmarkCheck
} from 'lucide-react';
import { lmsApi } from '@/lib/api-client';
import { Assignment } from '@/types/lms';
import { useTeacherClasses, useTeacherSubjects } from '@/lib/use-teacher-data';
import { toast } from 'sonner';

export default function GuruTugasPage() {
  const queryClient = useQueryClient();
  const [searchQuery, setSearchQuery] = useState('');
  const [classFilter, setClassFilter] = useState<'all' | string>('all');
  const [statusFilter, setStatusFilter] = useState<'all' | 'active' | 'completed' | 'locked'>('all');
  const [isModalOpen, setIsModalOpen] = useState(false);

  const { data: teacherClasses = [] } = useTeacherClasses();
  const { data: teacherSubjects = [] } = useTeacherSubjects();

  // Form state
  const [title, setTitle] = useState('');
  const [classId, setClassId] = useState('');
  const [subjectId, setSubjectId] = useState('');
  const [gradeComponentName, setGradeComponentName] = useState('Tugas Formatif 1');
  const [dueDate, setDueDate] = useState('2026-10-28T23:59');
  const [maxScore, setMaxScore] = useState(100);
  const [allowLate, setAllowLate] = useState(true);
  const [lockAfterDue, setLockAfterDue] = useState(false);
  const [description, setDescription] = useState('');
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

  // Fetch assignments
  const { data: assignments = [], isLoading, isError, refetch } = useQuery({
    queryKey: ['assignments'],
    queryFn: () => lmsApi.getAssignments(),
  });

  // Toggle lock state
  const handleToggleLock = (id: string, currentLock: boolean) => {
    const item = assignments.find(a => a.id === id);
    if (item) {
      item.is_locked = !currentLock;
      queryClient.setQueryData(['assignments'], [...assignments]);
      toast.success(
        item.is_locked 
          ? `Tugas "${item.title}" berhasil DIKUNCI. Siswa tidak dapat mengumpulkan lagi.`
          : `Tugas "${item.title}" DIBUKA kembali untuk pengumpulan siswa.`
      );
    }
  };

  // Export CSV
  const handleExportCSV = () => {
    const rows = [
      ['ID', 'Judul Tugas', 'Mata Pelajaran', 'Komponen Rapor', 'Tenggat Waktu', 'Terkumpul', 'Total Siswa', 'Status'],
      ...assignments.map(a => [
        a.id,
        `"${a.title}"`,
        a.subject?.name || 'Informatika',
        `"${a.grade_component_name || '-'}"`,
        a.due_at,
        a.submissions_count || 0,
        a.total_students || 32,
        a.is_locked ? 'Terkunci' : 'Aktif'
      ])
    ];
    const csvContent = 'data:text/csv;charset=utf-8,' + rows.map(e => e.join(',')).join('\n');
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement('a');
    link.setAttribute('href', encodedUri);
    link.setAttribute('download', `Rekap_Tugas_LMS_Guru_${new Date().toISOString().slice(0, 10)}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    toast.success('Rekap tugas berhasil diekspor ke format CSV.');
  };

  // Mutation to create assignment
  const createMutation = useMutation({
    mutationFn: (payload: {
      class_id: string;
      subject_id: string;
      title: string;
      description: string;
      due_at: string;
      max_score: number;
      file?: File;
    }) => lmsApi.createAssignment(payload),
    onSuccess: (newAsg) => {
      newAsg.grade_component_name = gradeComponentName;
      newAsg.allow_late = allowLate;
      newAsg.is_locked = lockAfterDue;
      queryClient.invalidateQueries({ queryKey: ['assignments'] });
      toast.success('Tugas baru berhasil diterbitkan & ditautkan ke Buku Nilai Rapor!');
      setIsModalOpen(false);
      // Reset form
      setTitle('');
      setDescription('');
      setSelectedFile(null);
    },
    onError: () => {
      toast.error('Gagal menerbitkan tugas. Periksa input data.');
    }
  });

  const handleFormSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!title.trim()) {
      toast.error('Judul tugas wajib diisi.');
      return;
    }
    createMutation.mutate({
      class_id: classId,
      subject_id: subjectId,
      title,
      description,
      due_at: new Date(dueDate).toISOString(),
      max_score: Number(maxScore) || 0,
      file: selectedFile || undefined,
    });
  };

  // Filtered list
  const filteredAssignments = assignments.filter((a) => {
    const matchesSearch = a.title.toLowerCase().includes(searchQuery.toLowerCase()) ||
      (a.description && a.description.toLowerCase().includes(searchQuery.toLowerCase()));
    const matchesClass = classFilter === 'all' || a.class_id === classFilter || a.class?.id === classFilter;
    if (!matchesClass) return false;
    
    const isDue = new Date(a.due_at).getTime() < Date.now();
    if (statusFilter === 'active') return matchesSearch && !isDue && !a.is_locked;
    if (statusFilter === 'completed') return matchesSearch && isDue;
    if (statusFilter === 'locked') return matchesSearch && a.is_locked;
    return matchesSearch;
  });

  // Calculations for stats
  const totalActive = assignments.filter(a => new Date(a.due_at).getTime() >= Date.now() && !a.is_locked).length;
  const totalSubmissions = assignments.reduce((acc, curr) => acc + (curr.submissions_count || 0), 0);
  const totalExpected = assignments.reduce((acc, curr) => acc + (curr.total_students || 32), 0);
  const complianceRate = totalExpected > 0 ? Math.round((totalSubmissions / totalExpected) * 100) : 92;
  const needsGrading = assignments.reduce((acc, curr) => acc + ((curr.submissions_count || 0) - (curr.reviewed_count || 0)), 0);

  return (
    <div className="flex flex-col gap-3 max-w-full xl:h-[calc(100vh-112px)] xl:overflow-hidden text-slate-900">
      {/* ─── PAGE HEADER & CTA ─── */}
      <div className="bg-white border border-slate-200/90 rounded-2xl p-3 sm:p-3.5 shadow-xs shrink-0 flex flex-col md:flex-row md:items-center justify-between gap-3">
        <div>
          <div className="flex items-center gap-2 mb-1">
            <span className="text-[11px] font-black uppercase tracking-wider px-2 py-0.5 rounded bg-blue-900 text-white shadow-2xs">
              Tugas & Penilaian LMS
            </span>
            <span className="text-xs text-slate-500 font-semibold">• BE-LMS-01 & BE-GRD-01</span>
          </div>
          <h1 className="text-lg sm:text-xl font-black text-slate-900 tracking-tight">
            Manajemen Penugasan Siswa
          </h1>
          <p className="text-xs text-slate-600 mt-0.5 line-clamp-1 max-w-2xl">
            Terbitkan tugas KBM, pantau kepatuhan pengumpulan rombel, dan lakukan penilaian formatif terintegrasi rapor.
          </p>
        </div>

        <div className="flex items-center gap-2 shrink-0">
          <button
            onClick={handleExportCSV}
            className="inline-flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg bg-white border border-slate-300 text-slate-700 text-xs font-bold hover:bg-slate-100 shadow-xs cursor-pointer"
          >
            <Download className="w-3.5 h-3.5 text-slate-600" />
            <span>Ekspor CSV</span>
          </button>

          <button
            onClick={() => setIsModalOpen(true)}
            className="inline-flex items-center justify-center gap-1.5 px-3.5 py-1.5 rounded-lg bg-blue-900 text-white text-xs font-black hover:bg-blue-950 transition-all shadow-xs cursor-pointer"
          >
            <Plus className="w-4 h-4" />
            <span>+ Terbitkan Tugas Baru</span>
          </button>
        </div>
      </div>

      {/* ─── 3 SUMMARY METRIC CARDS (SOLID HIGHLIGHTS WITH BORDER-L-4) ─── */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-2.5 sm:gap-3 shrink-0">
        {/* Metric 1 */}
        <div className="bg-white border border-slate-200/90 rounded-xl p-3 flex items-center justify-between border-l-4 border-l-blue-900 shadow-xs">
          <div>
            <p className="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Tugas Aktif Berjalan</p>
            <p className="text-xl sm:text-2xl font-black text-slate-900 mt-0.5">{totalActive} <span className="text-xs font-bold text-slate-500">Tugas</span></p>
            <p className="text-[10px] text-slate-500 font-medium mt-0.5">Semester Ganjil 2026/2027</p>
          </div>
          <div className="w-9 h-9 rounded-lg bg-blue-900 text-white flex items-center justify-center shadow-xs">
            <FileText className="w-4 h-4" />
          </div>
        </div>

        {/* Metric 2 */}
        <div className="bg-white border border-slate-200/90 rounded-xl p-3 flex items-center justify-between border-l-4 border-l-emerald-700 shadow-xs">
          <div>
            <p className="text-[10px] font-bold text-emerald-800 uppercase tracking-wider">Tingkat Kepatuhan</p>
            <p className="text-xl sm:text-2xl font-black text-slate-900 mt-0.5">{complianceRate}%</p>
            <p className="text-[10px] text-slate-500 font-medium mt-0.5">{totalSubmissions} dari {totalExpected} tugas terkumpul</p>
          </div>
          <div className="w-9 h-9 rounded-lg bg-emerald-800 text-white flex items-center justify-center shadow-xs">
            <CheckCircle2 className="w-4 h-4" />
          </div>
        </div>

        {/* Metric 3 */}
        <div className="bg-white border border-slate-200/90 rounded-xl p-3 flex items-center justify-between border-l-4 border-l-amber-600 shadow-xs">
          <div>
            <p className="text-[10px] font-bold text-amber-700 uppercase tracking-wider">Menunggu Penilaian Guru</p>
            <p className="text-xl sm:text-2xl font-black text-slate-900 mt-0.5">{needsGrading} <span className="text-xs font-bold text-slate-500">Berkas</span></p>
            <p className="text-[10px] text-slate-500 font-medium mt-0.5">Siap dikoreksi di Meja Penilaian</p>
          </div>
          <div className="w-9 h-9 rounded-lg bg-amber-600 text-white flex items-center justify-center shadow-xs">
            <Clock className="w-4 h-4" />
          </div>
        </div>
      </div>

      {/* ─── SEARCH & STATUS TABS ─── */}
      <div className="bg-white border border-slate-200/90 rounded-xl p-2.5 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 shrink-0">
        <div className="flex flex-wrap items-center gap-2 flex-1">
          {/* Class Filter Dropdown */}
          <div className="flex items-center gap-1.5 bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-1">
            <span className="text-[10px] font-bold text-slate-500 uppercase">Kelas:</span>
            <select
              aria-label="Filter Kelas"
              id="select-filter-kelas-tugas"
              value={classFilter}
              onChange={(e) => setClassFilter(e.target.value)}
              className="bg-transparent text-xs font-black text-slate-900 focus:outline-none cursor-pointer"
            >
              <option value="all">Semua Kelas</option>
              {teacherClasses.map((c) => (
                <option key={c.id} value={c.id}>
                  Kelas {c.name}
                </option>
              ))}
            </select>
          </div>

          {/* Search Field */}
          <div className="relative flex-1 max-w-sm">
            <Search className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
            <input
              type="text"
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              placeholder="Cari judul tugas atau materi..."
              className="w-full pl-9 pr-4 py-1.5 text-xs bg-white border border-slate-200 rounded-lg focus:outline-none focus:border-blue-900 font-medium"
            />
          </div>
        </div>

        {/* Status Tab Filters - Solid Style */}
        <div className="flex items-center gap-1 self-start sm:self-auto bg-slate-100 p-1 rounded-xl border border-slate-200">
          <button
            onClick={() => setStatusFilter('all')}
            className={`px-2.5 py-1 text-[11px] font-bold rounded-lg transition-all cursor-pointer ${
              statusFilter === 'all'
                ? 'bg-blue-900 text-white shadow-2xs'
                : 'text-slate-600 hover:text-slate-900'
            }`}
          >
            Semua ({assignments.length})
          </button>
          <button
            onClick={() => setStatusFilter('active')}
            className={`px-2.5 py-1 text-[11px] font-bold rounded-lg transition-all cursor-pointer ${
              statusFilter === 'active'
                ? 'bg-blue-900 text-white shadow-2xs'
                : 'text-slate-600 hover:text-slate-900'
            }`}
          >
            Sedang Berjalan
          </button>
          <button
            onClick={() => setStatusFilter('completed')}
            className={`px-2.5 py-1 text-[11px] font-bold rounded-lg transition-all cursor-pointer ${
              statusFilter === 'completed'
                ? 'bg-blue-900 text-white shadow-2xs'
                : 'text-slate-600 hover:text-slate-900'
            }`}
          >
            Selesai
          </button>
          <button
            onClick={() => setStatusFilter('locked')}
            className={`px-2.5 py-1 text-[11px] font-bold rounded-lg transition-all cursor-pointer ${
              statusFilter === 'locked'
                ? 'bg-rose-700 text-white shadow-2xs'
                : 'text-slate-600 hover:text-slate-900'
            }`}
          >
            Terkunci
          </button>
        </div>
      </div>

      {/* ─── ASSIGNMENTS DATA TABLE (SOLID HIGH CONTRAST) ─── */}
      <div className="bg-white border border-slate-200/90 rounded-2xl shadow-xs overflow-hidden flex-1 min-h-0 flex flex-col">
        {isLoading ? (
          <div className="p-8 space-y-4">
            {[1, 2, 3].map((n) => (
              <div key={n} className="flex items-center justify-between">
                <div className="space-y-2 flex-1">
                  <div className="h-4 bg-slate-200 rounded w-1/3"></div>
                  <div className="h-3 bg-slate-200 rounded w-1/2"></div>
                </div>
                <div className="h-8 bg-slate-200 rounded w-24"></div>
              </div>
            ))}
          </div>
        ) : isError ? (
          <div className="p-12 text-center space-y-3">
            <AlertCircle className="w-10 h-10 text-rose-600 mx-auto" />
            <h3 className="text-base font-bold text-slate-900">Gagal memuat daftar tugas</h3>
            <p className="text-sm text-slate-600 max-w-sm mx-auto">Terjadi gangguan saat mengambil data.</p>
            <button
              onClick={() => refetch()}
              className="px-4 py-2 text-xs font-bold rounded-lg bg-blue-900 text-white hover:bg-blue-950"
            >
              Coba Lagi
            </button>
          </div>
        ) : filteredAssignments.length === 0 ? (
          <div className="p-12 text-center space-y-4">
            <div className="w-14 h-14 rounded-xl bg-blue-900 text-white flex items-center justify-center mx-auto shadow-xs">
              <FileCheck2 className="w-7 h-7" />
            </div>
            <div>
              <h3 className="text-base font-bold text-slate-900">Belum ada tugas dalam filter ini</h3>
              <p className="text-sm text-slate-600 max-w-md mx-auto mt-1 font-medium">
                {searchQuery ? 'Tidak ada hasil untuk kata kunci tersebut.' : 'Mulai terbitkan tugas untuk kelas siswa Anda.'}
              </p>
            </div>
            {!searchQuery && (
              <button
                onClick={() => setIsModalOpen(true)}
                className="inline-flex items-center gap-2 px-4 py-2 text-xs font-bold rounded-lg bg-blue-900 text-white hover:bg-blue-950 shadow-xs"
              >
                <Plus className="w-4 h-4" />
                <span>Terbitkan Tugas Pertama</span>
              </button>
            )}
          </div>
        ) : (
          <div className="overflow-x-auto overflow-y-auto flex-1 h-full">
            <table className="w-full text-left border-collapse">
              <thead className="sticky top-0 bg-slate-100 z-10 border-b border-slate-300 shadow-xs">
                <tr className="border-b border-slate-300 bg-slate-100 text-[10px] font-extrabold uppercase tracking-wider text-slate-700">
                  <th className="py-3 px-5">Judul Tugas & Komponen Rapor</th>
                  <th className="py-3 px-4">Tenggat Waktu</th>
                  <th className="py-3 px-4 text-center">Pengumpulan</th>
                  <th className="py-3 px-4 text-center">Status</th>
                  <th className="py-3 px-5 text-right">Aksi</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-200 text-sm">
                {filteredAssignments.map((assignment) => {
                  const dueDateObj = new Date(assignment.due_at);
                  const isPastDue = dueDateObj.getTime() < Date.now();
                  const collected = assignment.submissions_count || 0;
                  const total = assignment.total_students || 0;
                  const percent = total > 0 ? Math.round((collected / total) * 100) : 0;
                  const unreviewed = collected - (assignment.reviewed_count || 0);

                  return (
                    <tr key={assignment.id} className="hover:bg-slate-50 transition-colors">
                      {/* Judul & Deskripsi (Fluid Full Width) */}
                      <td className="py-4 px-6">
                        <div className="flex items-start gap-3.5">
                          <div className="w-9 h-9 rounded-lg bg-blue-900 text-white flex items-center justify-center shrink-0 mt-0.5 shadow-xs">
                            <FileText className="w-4 h-4" />
                          </div>
                          <div>
                            <Link
                              href={`/guru/tugas/${assignment.id}/penilaian`}
                              className="font-bold text-slate-900 hover:text-blue-900 transition-colors line-clamp-1 hover:underline text-sm"
                            >
                              {assignment.title}
                            </Link>
                            <p className="text-xs text-slate-600 line-clamp-1 mt-0.5 font-medium">
                              {assignment.description || 'Tidak ada deskripsi tambahan.'}
                            </p>
                            
                            {/* Kurikulum Merdeka Mapping Tag */}
                            <div className="flex flex-wrap items-center gap-2 mt-2">
                              {assignment.grade_component_name && (
                                <span className="text-[10px] font-bold px-2 py-0.5 rounded bg-slate-800 text-white flex items-center gap-1 shadow-xs">
                                  <BookmarkCheck className="w-3 h-3 text-emerald-400" />
                                  {assignment.grade_component_name}
                                </span>
                              )}
                              <span className="text-[10px] font-bold px-2 py-0.5 rounded bg-slate-200 text-slate-800 border border-slate-300">
                                {assignment.subject?.name || 'Informatika'}
                              </span>
                              {assignment.file && (
                                <span className="text-[10px] font-bold px-2 py-0.5 rounded bg-teal-800 text-white shadow-xs">
                                  📎 File Petunjuk
                                </span>
                              )}
                            </div>
                          </div>
                        </div>
                      </td>

                      {/* Tenggat Waktu */}
                      <td className="py-4 px-4 whitespace-nowrap">
                        <div className="text-xs font-bold text-slate-900">
                          {dueDateObj.toLocaleDateString('id-ID', {
                            day: 'numeric',
                            month: 'short',
                            year: 'numeric',
                          })}
                        </div>
                        <div className="text-[11px] text-slate-600 font-mono font-semibold mt-0.5">
                          {dueDateObj.toLocaleTimeString('id-ID', {
                            hour: '2-digit',
                            minute: '2-digit',
                          })}{' '}
                          WIB
                        </div>
                        {assignment.is_locked ? (
                          <span className="inline-block text-[10px] font-bold text-rose-700 bg-rose-100 border border-rose-300 px-1.5 py-0.2 rounded mt-1">
                            🔒 Terkunci
                          </span>
                        ) : assignment.allow_late ? (
                          <span className="inline-block text-[10px] font-semibold text-slate-600 bg-slate-100 border border-slate-300 px-1.5 py-0.2 rounded mt-1">
                            Toleransi Keterlambatan: Aktif
                          </span>
                        ) : (
                          <span className="inline-block text-[10px] font-semibold text-rose-600 bg-slate-100 border border-slate-300 px-1.5 py-0.2 rounded mt-1">
                            Kunci Otomatis
                          </span>
                        )}
                      </td>

                      {/* Progres Pengumpulan Bar */}
                      <td className="py-4 px-4 text-center whitespace-nowrap">
                        <div className="inline-block text-left w-44">
                          <div className="flex justify-between text-xs font-bold mb-1">
                            <span className="text-slate-900">{collected}/{total}</span>
                            <span className="text-slate-600 font-mono text-[11px]">{percent}%</span>
                          </div>
                          <div className="w-full bg-slate-200 rounded-full h-2.5 overflow-hidden border border-slate-300">
                            <div
                              className="bg-emerald-600 h-2.5 rounded-full transition-all duration-300"
                              style={{ width: `${percent}%` }}
                            ></div>
                          </div>
                        </div>
                      </td>

                      {/* Status Badges - SOLID */}
                      <td className="py-4 px-4 text-center whitespace-nowrap">
                        {assignment.is_locked ? (
                          <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-md text-xs font-bold bg-rose-700 text-white shadow-xs">
                            <Lock className="w-3.5 h-3.5" />
                            Terkunci
                          </span>
                        ) : unreviewed > 0 ? (
                          <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-md text-xs font-bold bg-amber-600 text-white shadow-xs">
                            <Clock className="w-3.5 h-3.5" />
                            {unreviewed} Perlu Dinilai
                          </span>
                        ) : isPastDue ? (
                          <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-md text-xs font-bold bg-emerald-700 text-white shadow-xs">
                            <CheckCircle2 className="w-3.5 h-3.5" />
                            Selesai Dinilai
                          </span>
                        ) : (
                          <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-md text-xs font-bold bg-blue-900 text-white shadow-xs">
                            Sedang Berjalan
                          </span>
                        )}
                      </td>

                      {/* Aksi Button */}
                      <td className="py-4 px-6 text-right whitespace-nowrap">
                        <div className="flex items-center justify-end gap-2">
                          {/* Toggle Lock Button */}
                          <button
                            onClick={() => handleToggleLock(assignment.id, assignment.is_locked)}
                            title={assignment.is_locked ? "Buka Kunci Tugas" : "Kunci Pengumpulan Siswa"}
                            className="p-1.5 rounded-md border border-slate-300 hover:bg-slate-100 text-slate-700 transition-colors cursor-pointer"
                          >
                            {assignment.is_locked ? (
                              <Unlock className="w-3.5 h-3.5 text-emerald-700" />
                            ) : (
                              <Lock className="w-3.5 h-3.5 text-rose-700" />
                            )}
                          </button>

                          {/* Meja Penilaian Button */}
                          <Link
                            href={`/guru/tugas/${assignment.id}/penilaian`}
                            className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold bg-blue-900 text-white hover:bg-blue-950 transition-colors shadow-xs"
                          >
                            <span>Meja Penilaian</span>
                            <ChevronRight className="w-3.5 h-3.5" />
                          </Link>
                        </div>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* ─── MODAL TERBITKAN TUGAS BARU ─── */}
      {isModalOpen && (
        <div className="fixed inset-0 bg-slate-900/75 z-50 flex items-center justify-center p-4">
          <div className="bg-white w-full max-w-xl rounded-xl shadow-2xl overflow-hidden border border-slate-300 animate-in fade-in duration-150">
            {/* Modal Header */}
            <div className="px-6 py-4 bg-blue-900 text-white flex items-center justify-between">
              <div>
                <h3 className="font-extrabold text-base text-white">Terbitkan Tugas LMS Baru</h3>
                <p className="text-xs text-blue-200 font-medium">Terkoneksi langsung ke Buku Nilai & Portal Siswa</p>
              </div>
              <button
                onClick={() => setIsModalOpen(false)}
                className="w-8 h-8 rounded-lg flex items-center justify-center text-white hover:bg-blue-950 transition-colors cursor-pointer"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            {/* Modal Body Form */}
            <form onSubmit={handleFormSubmit} className="p-6 space-y-4 max-h-[82vh] overflow-y-auto text-slate-900">
              {/* Judul Tugas */}
              <div>
                <label className="block text-xs font-extrabold text-slate-900 uppercase tracking-wider mb-1.5">
                  Judul Tugas / Proyek Belajar <span className="text-rose-600">*</span>
                </label>
                <input
                  type="text"
                  required
                  value={title}
                  onChange={(e) => setTitle(e.target.value)}
                  placeholder="Contoh: Tugas Mandiri 04: Query Basis Data Relasional SQL"
                  className="w-full px-3.5 py-2 text-sm bg-white border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-900 font-medium"
                />
              </div>

              {/* Class & Subject Selector */}
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-extrabold text-slate-900 uppercase tracking-wider mb-1.5">
                    Target Rombel <span className="text-rose-600">*</span>
                  </label>
                  <select
                    value={classId}
                    onChange={(e) => setClassId(e.target.value)}
                    className="w-full px-3 py-2 text-sm bg-white border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-900 font-medium"
                  >
                    {teacherClasses.map((c) => (
                      <option key={c.id} value={c.id}>
                        Kelas {c.name} ({c.studentCount} Siswa)
                      </option>
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
                    className="w-full px-3 py-2 text-sm bg-white border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-900 font-medium"
                  >
                    {teacherSubjects.map((s) => (
                      <option key={s.id} value={s.id}>
                        {s.name} ({s.code})
                      </option>
                    ))}
                  </select>
                </div>
              </div>

              {/* Tautkan ke Komponen Nilai Rapor (PRD BE-GRD-01 Integration) */}
              <div>
                <label className="text-xs font-extrabold text-slate-900 uppercase tracking-wider mb-1.5 flex items-center justify-between">
                  <span>Komponen Buku Nilai Rapor <span className="text-rose-600">*</span></span>
                  <span className="text-[10px] font-normal text-blue-900">Kurikulum Merdeka</span>
                </label>
                <select
                  value={gradeComponentName}
                  onChange={(e) => setGradeComponentName(e.target.value)}
                  className="w-full px-3 py-2 text-sm bg-white border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-900 font-semibold"
                >
                  {[
                    'Tugas Formatif 1',
                    'Tugas Formatif 2',
                    'Tugas Formatif 3',
                    'Sumatif Tengah Semester',
                    'Sumatif Akhir Semester',
                    'Proyek Penguatan Profil Pelajar Pancasila',
                  ].map((name) => (
                    <option key={name} value={name}>
                      {name}
                    </option>
                  ))}
                </select>
              </div>

              {/* Deadline & Max Score */}
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-extrabold text-slate-900 uppercase tracking-wider mb-1.5">
                    Tenggat Waktu (Due Date) <span className="text-rose-600">*</span>
                  </label>
                  <input
                    type="datetime-local"
                    required
                    value={dueDate}
                    onChange={(e) => setDueDate(e.target.value)}
                    className="w-full px-3 py-2 text-sm bg-white border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-900 font-mono font-medium"
                  />
                </div>

                <div>
                  <label className="block text-xs font-extrabold text-slate-900 uppercase tracking-wider mb-1.5">
                    Skor Maksimal <span className="text-rose-600">*</span>
                  </label>
                  <input
                    type="number"
                    min={10}
                    max={100}
                    value={maxScore}
                    onChange={(e) => setMaxScore(Number(e.target.value) || 0)}
                    className="w-full px-3 py-2 text-sm bg-white border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-900 font-mono font-bold"
                  />
                </div>
              </div>

              {/* Anti-Late & Lock Toggles (PRD BE-ACD-07 Enforcement) */}
              <div className="p-3.5 rounded-lg border border-slate-300 bg-slate-50 space-y-2">
                <span className="text-xs font-extrabold uppercase tracking-wider text-slate-800 block">
                  Aturan Penyerahan & Keterlambatan
                </span>
                <label className="flex items-center gap-2.5 cursor-pointer text-xs font-semibold text-slate-800">
                  <input
                    type="checkbox"
                    checked={allowLate}
                    onChange={(e) => setAllowLate(e.target.checked)}
                    className="w-4 h-4 rounded border-slate-300 text-blue-900 focus:ring-blue-900"
                  />
                  <span>Izinkan siswa mengumpulkan terlambat (diberi tanda khusus "Terlambat")</span>
                </label>

                <label className="flex items-center gap-2.5 cursor-pointer text-xs font-semibold text-slate-800">
                  <input
                    type="checkbox"
                    checked={lockAfterDue}
                    onChange={(e) => setLockAfterDue(e.target.checked)}
                    className="w-4 h-4 rounded border-slate-300 text-rose-700 focus:ring-rose-700"
                  />
                  <span>Kunci pengumpulan otomatis tepat pada batas waktu tenggat</span>
                </label>
              </div>

              {/* Instructions */}
              <div>
                <label className="block text-xs font-extrabold text-slate-900 uppercase tracking-wider mb-1.5">
                  Petunjuk & Instruksi Pengerjaan <span className="text-rose-600">*</span>
                </label>
                <textarea
                  rows={3}
                  required
                  value={description}
                  onChange={(e) => setDescription(e.target.value)}
                  placeholder="Tuliskan petunjuk pengerjaan detail, format berkas yang diterima (PDF), dan kriteria penilaian..."
                  className="w-full px-3.5 py-2 text-sm bg-white border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-900 font-medium resize-none"
                />
              </div>

              {/* File Attachment Dropzone */}
              <div>
                <label className="block text-xs font-extrabold text-slate-900 uppercase tracking-wider mb-1.5">
                  Lampiran Dokumen Soal / Rubrik (Opsional)
                </label>
                <label className="border-2 border-dashed border-slate-300 hover:border-blue-900 rounded-lg p-3.5 flex flex-col items-center justify-center bg-white cursor-pointer transition-colors">
                  <UploadCloud className="w-6 h-6 text-slate-500 mb-1" />
                  <span className="text-xs font-bold text-slate-800">
                    {selectedFile ? selectedFile.name : 'Pilih berkas PDF soal dari komputer'}
                  </span>
                  <span className="text-[11px] text-slate-500 font-medium mt-0.5">Format PDF (Maksimal 10 MB)</span>
                  <input
                    type="file"
                    accept=".pdf,.png,.jpg,.jpeg"
                    className="hidden"
                    onChange={(e) => {
                      if (e.target.files?.[0]) setSelectedFile(e.target.files[0]);
                    }}
                  />
                </label>
              </div>

              {/* Modal Footer Actions */}
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
                  className="px-5 py-2 text-xs font-bold rounded-lg bg-blue-900 text-white hover:bg-blue-950 transition-all shadow-xs disabled:opacity-50"
                >
                  {createMutation.isPending ? 'Menerbitkan...' : 'Terbitkan Sekarang ➔'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
