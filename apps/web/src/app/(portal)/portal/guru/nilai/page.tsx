'use client';

import React, { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { 
  FileSpreadsheet, 
  Lock, 
  Unlock, 
  Save, 
  Download, 
  Search, 
  Filter, 
  CheckCircle2, 
  AlertTriangle, 
  Award, 
  Calculator,
  HelpCircle,
  FileCheck,
  QrCode,
  Printer,
  Eye,
  Send,
  Calendar,
  TrendingUp,
  X
} from 'lucide-react';
import { lmsApi } from '@/lib/api-client';
import { GradeComponent, StudentGradeRow } from '@/types/lms';
import { useTeacherClasses, useTeacherSubjects } from '@/lib/use-teacher-data';

export default function BukuNilaiPage() {
  const queryClient = useQueryClient();
  const [mainTab, setMainTab] = useState<'akademik' | 'karakter' | 'progress_report'>('akademik');
  const [filterStatus, setFilterStatus] = useState<'all' | 'remedial' | 'tuntas'>('all');
  const [searchQuery, setSearchQuery] = useState<string>('');
  const [successToast, setSuccessToast] = useState<string | null>(null);
  const [reportPeriodMonth, setReportPeriodMonth] = useState<string>('Oktober 2026');
  const [approvalStatus, setApprovalStatus] = useState<'draft' | 'menunggu_acc' | 'disetujui'>('menunggu_acc');
  const [previewStudent, setPreviewStudent] = useState<StudentGradeRow | null>(null);

  const { data: teacherClasses = [] } = useTeacherClasses();
  const { data: teacherSubjects = [] } = useTeacherSubjects();

  // Character evaluation state
  const [characterScores, setCharacterScores] = useState<Record<string, {
    beriman: string;
    gotongRoyong: string;
    mandiri: string;
    bernalarKritis: string;
    kreatif: string;
    catatan: string;
  }>>({});

  const [selectedClassId, setSelectedClassId] = useState<string>('');
  const [selectedSubjectId, setSelectedSubjectId] = useState<string>('');

  React.useEffect(() => {
    if (!selectedClassId && teacherClasses.length > 0) {
      setSelectedClassId(teacherClasses[0].id);
    }
  }, [teacherClasses, selectedClassId]);

  React.useEffect(() => {
    if (!selectedSubjectId && teacherSubjects.length > 0) {
      setSelectedSubjectId(teacherSubjects[0].id);
    }
  }, [teacherSubjects, selectedSubjectId]);

  // Queries
  const { data, isLoading } = useQuery({
    queryKey: ['gradebook-data', selectedClassId, selectedSubjectId],
    queryFn: () => lmsApi.getGradebook(selectedClassId, selectedSubjectId),
    enabled: Boolean(selectedClassId),
  });

  const period = data?.period;
  const components = data?.components || [];
  const initialGrades = data?.grades || [];

  // Local state for editable grades
  const [localGrades, setLocalGrades] = useState<StudentGradeRow[]>([]);

  React.useEffect(() => {
    if (initialGrades.length > 0) {
      setLocalGrades(initialGrades);
    }
  }, [initialGrades, selectedClassId, selectedSubjectId]);

  // Handle cell score changes with dynamic recalculation
  const handleScoreChange = (studentId: string, componentId: string, valStr: string) => {
    const numVal = valStr === '' ? null : Math.min(100, Math.max(0, Number(valStr)));
    
    setLocalGrades(prev => prev.map(student => {
      if (student.student_id !== studentId) return student;

      const updatedScores = { ...student.scores, [componentId]: numVal };
      
      // Calculate weighted score
      let weightedTotal = 0;
      let totalWeight = 0;

      components.forEach(comp => {
        const score = updatedScores[comp.id];
        if (score !== null && score !== undefined) {
          weightedTotal += (score * comp.weight_percent);
          totalWeight += comp.weight_percent;
        }
      });

      const finalScore = totalWeight > 0 ? Math.round((weightedTotal / totalWeight) * 10) / 10 : 0;
      const isTuntas = finalScore >= 75; // KKTP threshold 75

      return {
        ...student,
        scores: updatedScores,
        final_score: finalScore,
        is_tuntas: isTuntas,
      };
    }));
  };

  // Mutation to save bulk grades
  const saveMutation = useMutation({
    mutationFn: async () => {
      return lmsApi.saveBulkGrades({
        class_id: selectedClassId,
        subject_id: selectedSubjectId,
        grades: localGrades.map(s => ({
          student_id: s.student_id,
          scores: s.scores,
        }))
      });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['gradebook-data'] });
      setSuccessToast('Seluruh perubahan nilai berhasil disimpan ke server!');
      setTimeout(() => setSuccessToast(null), 3000);
    }
  });

  // Mutation to toggle period lock
  const lockMutation = useMutation({
    mutationFn: async () => {
      if (!period) return;
      return lmsApi.lockGradePeriod(period.id, !period.is_locked);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['gradebook-data'] });
      setSuccessToast(period?.is_locked ? 'Buku nilai berhasil dibuka kembali.' : 'Buku nilai berhasil dikunci!');
      setTimeout(() => setSuccessToast(null), 3000);
    }
  });

  // Compute live statistics
  const totalStudents = localGrades.length;
  const tuntasCount = localGrades.filter(s => s.is_tuntas).length;
  const remedialCount = totalStudents - tuntasCount;
  const classAverage = totalStudents > 0 
    ? (localGrades.reduce((acc, curr) => acc + curr.final_score, 0) / totalStudents).toFixed(1)
    : '0.0';

  // Filtered rows
  const filteredGrades = localGrades.filter(student => {
    const matchesSearch = student.name.toLowerCase().includes(searchQuery.toLowerCase()) || student.nis.includes(searchQuery);
    if (!matchesSearch) return false;
    if (filterStatus === 'remedial') return !student.is_tuntas;
    if (filterStatus === 'tuntas') return student.is_tuntas;
    return true;
  });

  // CSV Export
  const handleExportCSV = () => {
    if (localGrades.length === 0) return;
    const headerCols = ['No', 'NIS', 'Nama Siswa', 'L/P', ...components.map(c => `${c.code} (${c.weight_percent}%)`), 'Nilai Akhir', 'Status KKTP', 'Catatan Remedial'];
    const rows = localGrades.map((s, idx) => [
      idx + 1,
      s.nis,
      `"${s.name}"`,
      s.gender,
      ...components.map(c => s.scores[c.id] ?? '-'),
      s.final_score,
      s.is_tuntas ? 'TUNTAS' : 'REMEDIAL',
      `"${s.notes || ''}"`
    ]);

    const csvContent = "data:text/csv;charset=utf-8," + [headerCols.join(','), ...rows.map(r => r.join(','))].join('\n');
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement('a');
    link.setAttribute('href', encodedUri);
    link.setAttribute('download', `Ledger_Nilai_Informatika_Kelas_IXA.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  };

  return (
    <div className="flex flex-col gap-3 max-w-full xl:h-[calc(100vh-112px)] xl:overflow-hidden text-slate-900">
      {/* ─── HEADER ─── */}
      <div className="bg-white border border-slate-200/90 rounded-2xl p-3 sm:p-3.5 shadow-xs shrink-0 flex flex-col xl:flex-row xl:items-center justify-between gap-3">
        <div>
          <div className="flex items-center gap-2 mb-1">
            <span className="text-[11px] font-black uppercase tracking-wider px-2 py-0.5 rounded bg-blue-900 text-white shadow-2xs">
              Buku Nilai & Asesmen
            </span>
            <span className="text-xs text-slate-500 font-semibold">• {selectedClassId === 'cls-7a' ? 'Kelas VII-A (Wali Kelas)' : selectedClassId === 'cls-7b' ? 'Kelas VII-B' : selectedClassId === 'cls-8a' ? 'Kelas VIII-A' : 'Kelas IX-A'} ({selectedSubjectId === 'sbj-mat' ? 'Matematika' : selectedSubjectId === 'sbj-inf' ? 'Informatika' : 'Pemrograman Web'})</span>
          </div>
          <h1 className="text-lg sm:text-xl font-black text-slate-900 tracking-tight">
            Ledger Buku Nilai Guru & Ketercapaian KKTP
          </h1>
          <p className="text-xs text-slate-600 mt-0.5 line-clamp-1 max-w-2xl">
            Input akumulasi nilai formatif & sumatif, kalkulasi otomatis berbobot, dan pantau status KKTP (75).
          </p>
        </div>

        {/* Controls: Tab Switcher & Actions */}
        <div className="flex flex-wrap items-center gap-2 shrink-0">
          {/* Main Tab Switcher */}
          <div className="flex items-center bg-slate-100 p-1 rounded-xl border border-slate-200 shrink-0">
            <button
              type="button"
              onClick={() => setMainTab('akademik')}
              className={`flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer ${
                mainTab === 'akademik'
                  ? 'bg-blue-900 text-white shadow-xs'
                  : 'text-slate-600 hover:text-slate-900'
              }`}
            >
              <FileSpreadsheet className="w-3.5 h-3.5" />
              <span>Ledger Nilai</span>
            </button>
            <button
              type="button"
              onClick={() => setMainTab('karakter')}
              className={`flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer ${
                mainTab === 'karakter'
                  ? 'bg-blue-900 text-white shadow-xs'
                  : 'text-slate-600 hover:text-slate-900'
              }`}
            >
              <Award className="w-3.5 h-3.5" />
              <span>Karakter P5</span>
            </button>
            <button
              type="button"
              onClick={() => setMainTab('progress_report')}
              className={`flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer ${
                mainTab === 'progress_report'
                  ? 'bg-blue-900 text-white shadow-xs'
                  : 'text-slate-600 hover:text-slate-900'
              }`}
            >
              <FileCheck className="w-3.5 h-3.5" />
              <span>E-Progress Report</span>
              <span className="text-[9px] px-1.5 py-0.2 rounded bg-amber-600 text-white font-black uppercase">
                Walas
              </span>
            </button>
          </div>

          {/* Action Buttons */}
          <div className="flex items-center gap-1.5 shrink-0">
            {mainTab === 'akademik' && (
              <>
                <button
                  type="button"
                  onClick={handleExportCSV}
                  className="px-2.5 py-1.5 rounded-lg text-xs font-bold border border-slate-300 hover:bg-slate-100 text-slate-700 flex items-center gap-1.5 shadow-xs cursor-pointer"
                >
                  <Download className="w-3.5 h-3.5" />
                  <span className="hidden sm:inline">CSV</span>
                </button>

                <button
                  type="button"
                  disabled={lockMutation.isPending}
                  onClick={() => lockMutation.mutate()}
                  className={`px-3 py-1.5 rounded-lg text-xs font-bold flex items-center gap-1.5 shadow-xs cursor-pointer transition-all ${
                    period?.is_locked
                      ? 'bg-amber-600 hover:bg-amber-700 text-white'
                      : 'bg-slate-800 hover:bg-slate-900 text-white'
                  }`}
                >
                  {period?.is_locked ? <Unlock className="w-3.5 h-3.5" /> : <Lock className="w-3.5 h-3.5" />}
                  <span>{period?.is_locked ? 'Buka Kunci' : 'Kunci'}</span>
                </button>

                <button
                  type="button"
                  disabled={saveMutation.isPending || period?.is_locked}
                  onClick={() => saveMutation.mutate()}
                  className="px-3.5 py-1.5 rounded-lg text-xs font-black bg-blue-900 hover:bg-blue-950 text-white flex items-center gap-1.5 shadow-xs cursor-pointer disabled:opacity-50"
                >
                  <Save className="w-3.5 h-3.5" />
                  <span>{saveMutation.isPending ? 'Menyimpan...' : 'Simpan Nilai'}</span>
                </button>
              </>
            )}

            {mainTab === 'karakter' && (
              <button
                type="button"
                onClick={() => {
                  setSuccessToast('Seluruh catatan karakter Profil Pelajar Pancasila berhasil disimpan!');
                  setTimeout(() => setSuccessToast(null), 3000);
                }}
                className="px-3.5 py-1.5 rounded-lg text-xs font-black bg-blue-900 hover:bg-blue-950 text-white flex items-center gap-1.5 shadow-xs cursor-pointer"
              >
                <Save className="w-3.5 h-3.5" />
                <span>Simpan Catatan</span>
              </button>
            )}

            {mainTab === 'progress_report' && (
              <>
                <button
                  type="button"
                  onClick={() => {
                    setSuccessToast('Dokumen E-Progress Report kelas IX-A dikirim ke antrean cetak massal PDF.');
                    setTimeout(() => setSuccessToast(null), 3000);
                  }}
                  className="px-2.5 py-1.5 rounded-lg text-xs font-bold border border-slate-300 hover:bg-slate-100 text-slate-700 flex items-center gap-1.5 shadow-xs cursor-pointer"
                >
                  <Printer className="w-3.5 h-3.5" />
                  <span className="hidden sm:inline">Cetak PDF</span>
                </button>
                
                <button
                  type="button"
                  onClick={() => {
                    setApprovalStatus('menunggu_acc');
                    setSuccessToast('Permohonan ACC E-Progress Report berhasil diajukan ke Kepala Sekolah!');
                    setTimeout(() => setSuccessToast(null), 3000);
                  }}
                  className="px-3.5 py-1.5 rounded-lg text-xs font-black bg-emerald-800 hover:bg-emerald-900 text-white flex items-center gap-1.5 shadow-xs cursor-pointer"
                >
                  <Send className="w-3.5 h-3.5" />
                  <span>Ajukan ACC</span>
                </button>
              </>
            )}
          </div>
        </div>
      </div>

      {/* ─── TOAST ALERT NOTIFICATION ─── */}
      {successToast && (
        <div className="bg-emerald-800 text-white p-4 rounded-xl flex items-center justify-between shadow-xs animate-in fade-in">
          <div className="flex items-center gap-3">
            <CheckCircle2 className="w-5 h-5 text-emerald-200 shrink-0" />
            <span className="text-xs font-bold">{successToast}</span>
          </div>
        </div>
      )}

      {mainTab === 'akademik' && (
        <>
          {/* ─── LOCK STATUS BANNER (IF LOCKED) ─── */}
          {period?.is_locked && (
            <div className="bg-amber-600 text-white p-4 rounded-xl flex items-center justify-between shadow-xs">
              <div className="flex items-center gap-3">
                <Lock className="w-5 h-5 text-amber-200 shrink-0" />
                <div className="text-xs">
                  <span className="font-black block">Buku Nilai Terkunci</span>
                  <span className="text-amber-100 font-medium">
                    Periode penilaian telah dikunci oleh {period.locked_by || 'Guru Pengampu'}. Nilai tidak dapat diedit sebelum dibuka kembali.
                  </span>
                </div>
              </div>
            </div>
          )}

          {/* ─── KPI SUMMARY CARDS (SOLID HIGH-CONTRAST WITH BORDER-L-4) ─── */}
          <div className="grid grid-cols-2 md:grid-cols-4 gap-2.5 sm:gap-3 shrink-0">
            {/* Rata-rata Kelas */}
            <div className="bg-white border border-slate-200/90 rounded-xl p-3 shadow-xs border-l-4 border-l-blue-900">
              <div className="flex items-center justify-between mb-0.5">
                <span className="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Rata-Rata Kelas</span>
                <Calculator className="w-3.5 h-3.5 text-blue-900" />
              </div>
              <div className="text-xl sm:text-2xl font-black text-slate-900 mt-0.5">{classAverage}</div>
              <p className="text-[10px] text-slate-600 mt-0.5 font-medium">Berdasarkan 5 komponen asesmen</p>
            </div>

            {/* Tuntas KKTP */}
            <div className="bg-white border border-slate-200/90 rounded-xl p-3 shadow-xs border-l-4 border-l-emerald-700">
              <div className="flex items-center justify-between mb-0.5">
                <span className="text-[10px] font-bold text-emerald-800 uppercase tracking-wider">Tuntas KKTP (≥ 75)</span>
                <CheckCircle2 className="w-3.5 h-3.5 text-emerald-800" />
              </div>
              <div className="text-xl sm:text-2xl font-black text-slate-900 mt-0.5">
                {tuntasCount} <span className="text-xs font-semibold text-slate-500">/ {totalStudents} Siswa</span>
              </div>
              <p className="text-[10px] text-emerald-800 mt-0.5 font-semibold">
                {totalStudents > 0 ? Math.round((tuntasCount / totalStudents) * 100) : 0}% Tingkat Ketuntasan
              </p>
            </div>

            {/* Perlu Remedial */}
            <div className="bg-white border border-slate-200/90 rounded-xl p-3 shadow-xs border-l-4 border-l-rose-700">
              <div className="flex items-center justify-between mb-0.5">
                <span className="text-[10px] font-bold text-rose-700 uppercase tracking-wider">Perlu Remedial (&lt; 75)</span>
                <AlertTriangle className="w-3.5 h-3.5 text-rose-700" />
              </div>
              <div className="text-xl sm:text-2xl font-black text-slate-900 mt-0.5">
                {remedialCount} <span className="text-xs font-semibold text-slate-500">Siswa</span>
              </div>
              <p className="text-[10px] text-rose-700 mt-0.5 font-semibold">Perlu program tindak lanjut / tugas</p>
            </div>

            {/* Ambang Batas KKTP */}
            <div className="bg-white border border-slate-200/90 rounded-xl p-3 shadow-xs border-l-4 border-l-slate-800">
              <div className="flex items-center justify-between mb-0.5">
                <span className="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Kriteria KKTP</span>
                <Award className="w-3.5 h-3.5 text-amber-600" />
              </div>
              <div className="text-xl sm:text-2xl font-black text-slate-900 mt-0.5">75.0</div>
              <p className="text-[10px] text-slate-600 mt-0.5 font-medium">SK Kepala Sekolah Labschool UM</p>
            </div>
          </div>

          {/* ─── CONTROLS & FILTER BAR ─── */}
          <div className="bg-white border border-slate-200/90 rounded-xl p-2.5 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-2.5 shrink-0">
            {/* Class & Subject Selector */}
            <div className="flex flex-wrap items-center gap-2 shrink-0">
              <div className="flex items-center gap-1.5 bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-1">
                <span className="text-[10px] font-bold text-slate-500 uppercase">Kelas:</span>
                <select
                  aria-label="Pilih Kelas"
                  id="select-class-nilai"
                  value={selectedClassId}
                  onChange={(e) => setSelectedClassId(e.target.value)}
                  className="bg-transparent text-xs font-black text-slate-900 focus:outline-none cursor-pointer"
                >
                  {teacherClasses.map((c) => (
                    <option key={c.id} value={c.id}>
                      Kelas {c.name} ({c.studentCount} Siswa)
                    </option>
                  ))}
                </select>
              </div>

              <div className="flex items-center gap-1.5 bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-1">
                <span className="text-[10px] font-bold text-slate-500 uppercase">Mapel:</span>
                <select
                  aria-label="Pilih Mata Pelajaran"
                  id="select-subject-nilai"
                  value={selectedSubjectId}
                  onChange={(e) => setSelectedSubjectId(e.target.value)}
                  className="bg-transparent text-xs font-black text-slate-900 focus:outline-none cursor-pointer"
                >
                  {teacherSubjects.map((s) => (
                    <option key={s.id} value={s.id}>
                      {s.name} ({s.code})
                    </option>
                  ))}
                </select>
              </div>
            </div>

            {/* Search Input */}
            <div className="relative flex-1 max-w-sm">
              <Search className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
              <input
                type="text"
                placeholder="Cari siswa berdasarkan nama atau NIS..."
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className="w-full pl-9 pr-3 py-1.5 text-xs bg-white border border-slate-200 rounded-lg focus:outline-none focus:border-blue-900 font-medium"
              />
            </div>

            {/* Filter Buttons */}
            <div className="flex items-center gap-1 bg-slate-100 p-1 rounded-xl border border-slate-200 shrink-0">
              <button
                type="button"
                onClick={() => setFilterStatus('all')}
                className={`px-2.5 py-1 rounded-lg text-[11px] font-bold transition-all cursor-pointer ${
                  filterStatus === 'all'
                    ? 'bg-blue-900 text-white shadow-2xs'
                    : 'text-slate-600 hover:text-slate-900'
                }`}
              >
                Semua Siswa ({localGrades.length})
              </button>
              <button
                type="button"
                onClick={() => setFilterStatus('remedial')}
                className={`px-2.5 py-1 rounded-lg text-[11px] font-bold transition-all cursor-pointer ${
                  filterStatus === 'remedial'
                    ? 'bg-rose-700 text-white shadow-2xs'
                    : 'text-slate-600 hover:text-slate-900'
                }`}
              >
                Remedial ({remedialCount})
              </button>
              <button
                type="button"
                onClick={() => setFilterStatus('tuntas')}
                className={`px-2.5 py-1 rounded-lg text-[11px] font-bold transition-all cursor-pointer ${
                  filterStatus === 'tuntas'
                    ? 'bg-emerald-800 text-white shadow-2xs'
                    : 'text-slate-600 hover:text-slate-900'
                }`}
              >
                Tuntas ({tuntasCount})
              </button>
            </div>
          </div>

          {/* ─── SPREADSHEET LEDGER TABLE ─── */}
          <div className="bg-white border border-slate-200/90 rounded-2xl overflow-hidden shadow-xs flex-1 min-h-0 flex flex-col">
            <div className="overflow-x-auto overflow-y-auto flex-1 h-full">
              <table className="w-full text-left text-xs border-collapse">
                <thead className="sticky top-0 bg-slate-100 z-20 border-b border-slate-300 shadow-xs">
                  <tr className="text-slate-800 font-extrabold uppercase text-[10px] tracking-wider">
                    <th className="py-3 px-3 w-12 text-center sticky left-0 bg-slate-100 z-30">No</th>
                    <th className="py-3 px-3 w-24 sticky left-12 bg-slate-100 z-30">NIS</th>
                    <th className="py-3 px-3 min-w-56 sticky left-36 bg-slate-100 z-30">Nama Siswa</th>
                    <th className="py-3 px-2 w-14 text-center bg-slate-100">L/P</th>
                    
                    {/* Dynamic Component Columns */}
                    {components.map((comp) => (
                      <th key={comp.id} className="py-3 px-2 w-28 text-center border-l border-slate-200">
                        <span className="block font-black text-slate-900">{comp.code}</span>
                        <span className="text-[9px] text-slate-500 font-semibold block truncate">
                          Bobot: {comp.weight_percent}%
                        </span>
                      </th>
                    ))}

                    <th className="py-3 px-3 w-28 text-center border-l border-slate-300 bg-slate-200 text-slate-900">
                      <span className="block font-black">Nilai Akhir</span>
                      <span className="text-[9px] text-slate-600 font-semibold block">Otomatis</span>
                    </th>
                    <th className="py-3 px-3 w-28 text-center border-l border-slate-200">
                      Status KKTP
                    </th>
                    <th className="py-3 px-3 min-w-56 border-l border-slate-200">
                      Catatan Tindak Lanjut / Remedial
                    </th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-200">
                  {filteredGrades.map((student, idx) => {
                    const isUnderKKTP = !student.is_tuntas;

                    return (
                      <tr 
                        key={student.student_id} 
                        className={`hover:bg-slate-50 transition-colors ${
                          isUnderKKTP ? 'bg-rose-50/40' : ''
                        }`}
                      >
                        <td className="py-2.5 px-3 text-center font-bold text-slate-500 sticky left-0 bg-white">
                          {idx + 1}
                        </td>
                        <td className="py-2.5 px-3 font-mono font-semibold text-slate-700 sticky left-12 bg-white">
                          {student.nis}
                        </td>
                        <td className="py-2.5 px-3 font-bold text-slate-900 sticky left-36 bg-white truncate">
                          <div className="flex items-center gap-2.5">
                            {student.avatar_url ? (
                              <img
                                src={student.avatar_url}
                                alt={student.name}
                                className="w-7 h-7 rounded-full object-cover border border-slate-200 shrink-0 shadow-2xs"
                              />
                            ) : (
                              <div className="w-7 h-7 rounded-full bg-slate-200 text-slate-700 text-[10px] font-bold flex items-center justify-center shrink-0">
                                {student.name.slice(0, 2).toUpperCase()}
                              </div>
                            )}
                            <span className="truncate">{student.name}</span>
                          </div>
                        </td>
                        <td className="py-2.5 px-2 text-center">
                          <span className={`text-[10px] font-black px-1.5 py-0.5 rounded ${
                            student.gender === 'L' ? 'bg-slate-200 text-slate-800' : 'bg-slate-300 text-slate-900'
                          }`}>
                            {student.gender}
                          </span>
                        </td>

                        {/* Interactive Input Cells */}
                        {components.map((comp) => {
                          const scoreVal = student.scores[comp.id];
                          const isLow = scoreVal !== null && scoreVal !== undefined && scoreVal < 75;

                          return (
                            <td key={comp.id} className="py-1 px-1.5 text-center border-l border-slate-200">
                              <input
                                type="number"
                                min={0}
                                max={100}
                                disabled={period?.is_locked}
                                value={scoreVal ?? ''}
                                onChange={(e) => handleScoreChange(student.student_id, comp.id, e.target.value)}
                                className={`w-16 px-1.5 py-1 text-center font-bold text-xs rounded border transition-all ${
                                  isLow 
                                    ? 'bg-rose-100 border-rose-400 text-rose-900 font-extrabold' 
                                    : 'bg-white border-slate-300 text-slate-900 focus:border-blue-900'
                                } disabled:bg-slate-100 disabled:text-slate-500 disabled:cursor-not-allowed`}
                              />
                            </td>
                          );
                        })}

                        {/* Nilai Akhir (Calculated) */}
                        <td className="py-2.5 px-3 text-center border-l border-slate-300 bg-slate-50 font-black text-sm">
                          <span className={student.final_score >= 75 ? 'text-emerald-800' : 'text-rose-700'}>
                            {student.final_score.toFixed(1)}
                          </span>
                        </td>

                        {/* Status KKTP Solid Badge */}
                        <td className="py-2.5 px-3 text-center border-l border-slate-200">
                          <span className={`inline-block px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider ${
                            student.is_tuntas
                              ? 'bg-emerald-800 text-white'
                              : 'bg-rose-800 text-white shadow-xs'
                          }`}>
                            {student.is_tuntas ? 'Tuntas' : 'Remedial'}
                          </span>
                        </td>

                        {/* Catatan / Keterangan */}
                        <td className="py-2 px-3 border-l border-slate-200">
                          <input
                            type="text"
                            disabled={period?.is_locked}
                            placeholder={student.is_tuntas ? 'Memenuhi kriteria' : 'Rencana remedial...'}
                            value={student.notes || ''}
                            onChange={(e) => {
                              const noteVal = e.target.value;
                              setLocalGrades(prev => prev.map(s => s.student_id === student.student_id ? { ...s, notes: noteVal } : s));
                            }}
                            className="w-full px-2 py-1 text-xs bg-transparent border-b border-transparent hover:border-slate-300 focus:border-blue-900 focus:outline-none"
                          />
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          </div>
        </>
      )}

      {/* ─── TAB 2: CATATAN KARAKTER PROFIL PELAJAR PANCASILA (BE-GRD-02) ─── */}
      {mainTab === 'karakter' && (
        <div className="bg-white border border-slate-200/90 rounded-2xl p-3.5 shadow-xs flex-1 min-h-0 flex flex-col overflow-hidden space-y-3">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-200 pb-2.5 shrink-0">
            <div>
              <h2 className="text-xs sm:text-sm font-black text-slate-900 uppercase tracking-wider">
                Catatan Karakter Profil Pelajar Pancasila (P5) Bulanan
              </h2>
              <p className="text-[11px] text-slate-500 font-medium">
                Skala capaian: MB (Mulai Berkembang), SB (Sedang Berkembang), BSH (Berkembang Sesuai Harapan), SAB (Sangat Berkembang).
              </p>
            </div>

            <div className="flex items-center gap-2 shrink-0">
              <span className="text-[11px] font-bold px-2.5 py-1 rounded bg-slate-100 border border-slate-200/90 text-slate-700">
                Kelas IX-A • Oktober 2026
              </span>
            </div>
          </div>

          {/* Character Table */}
          <div className="border border-slate-200/90 rounded-xl overflow-hidden bg-white shadow-xs flex-1 min-h-0 overflow-y-auto">
            <table className="w-full text-left text-xs border-collapse">
              <thead className="sticky top-0 bg-slate-100 z-10 border-b border-slate-300 shadow-xs">
                <tr className="text-slate-800 font-extrabold uppercase text-[10px] tracking-wider">
                  <th className="py-3 px-3 w-12 text-center bg-slate-100">No</th>
                  <th className="py-3 px-3 w-24 bg-slate-100">NIS</th>
                  <th className="py-3 px-3 min-w-48 bg-slate-100">Nama Siswa</th>
                  <th className="py-3 px-2 w-28 text-center bg-slate-100">Beriman &amp; Bertakwa</th>
                  <th className="py-3 px-2 w-28 text-center bg-slate-100">Gotong Royong</th>
                  <th className="py-3 px-2 w-28 text-center bg-slate-100">Mandiri</th>
                  <th className="py-3 px-2 w-28 text-center bg-slate-100">Bernalar Kritis</th>
                  <th className="py-3 px-2 w-28 text-center bg-slate-100">Kreatif</th>
                  <th className="py-3 px-3 min-w-64 bg-slate-100">Catatan Observasi Wali Kelas</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-200">
                {localGrades.map((s, idx) => (
                  <tr key={s.student_id} className="hover:bg-slate-50 transition-colors">
                    <td className="py-2.5 px-3 text-center font-bold text-slate-500">
                      {idx + 1}
                    </td>
                    <td className="py-2.5 px-3 font-mono font-semibold text-slate-700">
                      {s.nis}
                    </td>
                    <td className="py-2.5 px-3 font-bold text-slate-900 truncate">
                      <div className="flex items-center gap-2.5">
                        {s.avatar_url ? (
                          <img
                            src={s.avatar_url}
                            alt={s.name}
                            className="w-7 h-7 rounded-full object-cover border border-slate-200 shrink-0 shadow-2xs"
                          />
                        ) : (
                          <div className="w-7 h-7 rounded-full bg-slate-200 text-slate-700 text-[10px] font-bold flex items-center justify-center shrink-0">
                            {s.name.slice(0, 2).toUpperCase()}
                          </div>
                        )}
                        <span className="truncate">{s.name}</span>
                      </div>
                    </td>
                    {['beriman', 'gotongRoyong', 'mandiri', 'bernalarKritis', 'kreatif'].map((dim) => (
                      <td key={dim} className="py-1 px-1.5 text-center">
                        <select 
                          defaultValue="BSH"
                          className="w-full px-1.5 py-1 rounded text-xs font-bold bg-white border border-slate-300 text-slate-800 focus:outline-none focus:border-blue-900"
                        >
                          <option value="MB">MB</option>
                          <option value="SB">SB</option>
                          <option value="BSH">BSH</option>
                          <option value="SAB">SAB</option>
                        </select>
                      </td>
                    ))}
                    <td className="py-1.5 px-3">
                      <input
                        type="text"
                        placeholder="Observasi pembiasaan dan akhlak..."
                        defaultValue={idx === 0 ? "Menunjukkan kepemimpinan positif dalam kerja tim lab." : ""}
                        className="w-full px-2 py-1 text-xs bg-white border border-slate-200 rounded focus:border-blue-900 focus:outline-none"
                      />
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* ─── TAB 3: E-PROGRESS REPORT & E-RAPOR BULANAN (BE-GRD-03 & BE-GRD-04) ─── */}
      {mainTab === 'progress_report' && (
        <div className="flex-1 min-h-0 overflow-y-auto space-y-3.5 pr-1">
          {/* Status & Lifecycle Banner */}
          <div className="bg-white border border-slate-200/90 rounded-2xl p-4 shadow-xs space-y-3.5">
            <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-4 border-b border-slate-200 pb-4">
              <div>
                <div className="flex items-center gap-2 mb-1.5">
                  <span className="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider bg-emerald-800 text-white shadow-xs">
                    SIAP DITERBITKAN
                  </span>
                  <span className="text-xs font-bold text-slate-500">
                    Kelas IX-A • Wali Kelas: Budi Santoso, S.Kom.
                  </span>
                </div>
                <h2 className="text-lg font-black text-slate-900">
                  E-Progress Report Bulanan (Bulan {reportPeriodMonth})
                </h2>
                <p className="text-xs text-slate-600 mt-0.5">
                  Laporan rekapitulasi 1 halaman memuat nilai kognitif, % presensi, akumulasi poin sikap, dan catatan walas ber-tanda tangan QR terverifikasi.
                </p>
              </div>

              {/* Month selector and Lifecycle Pill */}
              <div className="flex flex-wrap items-center gap-3">
                <div className="flex items-center gap-2 bg-slate-50 border border-slate-300 rounded-lg px-3 py-1.5">
                  <Calendar className="w-4 h-4 text-slate-500" />
                  <select
                    value={reportPeriodMonth}
                    onChange={(e) => setReportPeriodMonth(e.target.value)}
                    className="bg-transparent text-xs font-bold text-slate-900 focus:outline-none cursor-pointer"
                  >
                    <option value="September 2026">September 2026</option>
                    <option value="Oktober 2026">Oktober 2026 (Aktif)</option>
                    <option value="November 2026">November 2026</option>
                    <option value="Desember 2026 (Semester Ganjil)">Desember 2026 (Rapor Semester)</option>
                  </select>
                </div>

                <div className="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-blue-900 text-white text-xs font-bold shadow-xs">
                  <FileCheck className="w-3.5 h-3.5 text-emerald-300" />
                  <span>Status: Menunggu ACC Kepala Sekolah</span>
                </div>
              </div>
            </div>

            {/* Lifecycle Steps Bar */}
            <div className="grid grid-cols-1 sm:grid-cols-4 gap-3 text-xs">
              <div className="p-3 bg-emerald-800 text-white rounded-lg shadow-xs">
                <span className="font-extrabold block text-emerald-200 text-[10px] uppercase">Langkah 1</span>
                <span className="font-bold text-white text-xs mt-0.5 block">1. Rekap Nilai & Sikap</span>
                <span className="text-[10px] text-emerald-100 mt-1 block">Tuntas 32/32 Siswa</span>
              </div>
              <div className="p-3 bg-blue-900 text-white rounded-lg shadow-xs">
                <span className="font-extrabold block text-blue-200 text-[10px] uppercase">Langkah 2 (Aktif)</span>
                <span className="font-bold text-white text-xs mt-0.5 block">2. Ajukan ACC Kepsek</span>
                <span className="text-[10px] text-blue-100 mt-1 block">Dalam Antrean Verifikasi</span>
              </div>
              <div className="p-3 bg-slate-100 text-slate-600 rounded-lg border border-slate-200/90">
                <span className="font-extrabold block text-slate-400 text-[10px] uppercase">Langkah 3</span>
                <span className="font-bold text-slate-800 text-xs mt-0.5 block">3. Pengesahan Digital</span>
                <span className="text-[10px] text-slate-500 mt-1 block">Tanda Tangan QR Resmi</span>
              </div>
              <div className="p-3 bg-slate-100 text-slate-600 rounded-lg border border-slate-200/90">
                <span className="font-extrabold block text-slate-400 text-[10px] uppercase">Langkah 4</span>
                <span className="font-bold text-slate-800 text-xs mt-0.5 block">4. Terbit ke Ortu/Siswa</span>
                <span className="text-[10px] text-slate-500 mt-1 block">Tampil di Portal & Unduh PDF</span>
              </div>
            </div>
          </div>

          {/* KPI Metrics */}
          <div className="grid grid-cols-2 sm:grid-cols-4 gap-3.5">
            <div className="bg-white border border-slate-200/90 rounded-xl p-4 shadow-xs border-l-4 border-l-blue-900">
              <span className="text-[10px] font-bold text-slate-500 uppercase">Siswa Terdaftar</span>
              <span className="text-2xl font-black text-slate-900 block mt-1">32 Siswa</span>
              <span className="text-[10px] font-bold text-emerald-700 mt-0.5 block">100% Data Lengkap</span>
            </div>

            <div className="bg-white border border-slate-200/90 rounded-xl p-4 shadow-xs border-l-4 border-l-emerald-800">
              <span className="text-[10px] font-bold text-slate-500 uppercase">Rata-rata Kelas</span>
              <span className="text-2xl font-black text-emerald-800 block mt-1">{classAverage}</span>
              <span className="text-[10px] font-bold text-slate-600 mt-0.5 block">Target KKTP: 75.0</span>
            </div>

            <div className="bg-white border border-slate-200/90 rounded-xl p-4 shadow-xs border-l-4 border-l-teal-800">
              <span className="text-[10px] font-bold text-slate-500 uppercase">Ketuntasan Belajar</span>
              <span className="text-2xl font-black text-teal-800 block mt-1">
                {totalStudents > 0 ? Math.round((tuntasCount / totalStudents) * 100) : 0}%
              </span>
              <span className="text-[10px] font-bold text-slate-600 mt-0.5 block">
                {tuntasCount} Tuntas • {remedialCount} Remedial
              </span>
            </div>

            <div className="bg-white border border-slate-200/90 rounded-xl p-4 shadow-xs border-l-4 border-l-purple-900">
              <span className="text-[10px] font-bold text-slate-500 uppercase">Kehadiran Rata-rata</span>
              <span className="text-2xl font-black text-purple-900 block mt-1">96.8%</span>
              <span className="text-[10px] font-bold text-emerald-700 mt-0.5 block">Sangat Baik</span>
            </div>
          </div>

          {/* Student Progress Report Table */}
          <div className="bg-white border border-slate-200/90 rounded-xl overflow-hidden shadow-xs">
            <div className="p-4 border-b border-slate-200 flex items-center justify-between">
              <div>
                <h3 className="font-extrabold text-sm text-slate-900">Daftar E-Progress Report Siswa Kelas IX-A</h3>
                <p className="text-xs text-slate-500">Klik "Pratinjau PDF" untuk memeriksa dokumen sebelum diajukan ke kepala sekolah</p>
              </div>
              <span className="text-xs font-bold bg-blue-900 text-white px-3 py-1 rounded-md shadow-xs">
                32 Berkas Siap
              </span>
            </div>

            <div className="overflow-x-auto">
              <table className="w-full text-left text-xs border-collapse">
                <thead>
                  <tr className="bg-slate-100 border-b border-slate-300 text-slate-800 font-extrabold uppercase text-[10px] tracking-wider">
                    <th className="py-3 px-3 w-12 text-center">No</th>
                    <th className="py-3 px-3 w-28">NIS</th>
                    <th className="py-3 px-3 min-w-56">Nama Lengkap Siswa</th>
                    <th className="py-3 px-2 w-16 text-center">L/P</th>
                    <th className="py-3 px-3 w-28 text-center">Nilai Rerata</th>
                    <th className="py-3 px-3 w-28 text-center">Presensi</th>
                    <th className="py-3 px-3 w-32 text-center">Status KKTP</th>
                    <th className="py-3 px-3 min-w-64">Catatan Wali Kelas</th>
                    <th className="py-3 px-3 w-32 text-center">Aksi Dokumen</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-200">
                  {localGrades.map((s, idx) => (
                    <tr key={s.student_id} className="hover:bg-slate-50 transition-colors">
                      <td className="py-2.5 px-3 text-center font-bold text-slate-500">{idx + 1}</td>
                      <td className="py-2.5 px-3 font-mono font-semibold text-slate-700">{s.nis}</td>
                      <td className="py-2.5 px-3 font-bold text-slate-900">{s.name}</td>
                      <td className="py-2.5 px-2 text-center">
                        <span className="text-[10px] font-bold px-1.5 py-0.5 rounded bg-slate-200 text-slate-800">
                          {s.gender}
                        </span>
                      </td>
                      <td className="py-2.5 px-3 text-center font-black text-slate-900">
                        {s.final_score}
                      </td>
                      <td className="py-2.5 px-3 text-center font-bold text-emerald-800">
                        {idx === 2 ? '88.5%' : '97.2%'}
                      </td>
                      <td className="py-2.5 px-3 text-center">
                        {s.is_tuntas ? (
                          <span className="px-2 py-0.5 rounded text-[10px] font-black bg-emerald-800 text-white shadow-xs">
                            TUNTAS
                          </span>
                        ) : (
                          <span className="px-2 py-0.5 rounded text-[10px] font-black bg-rose-800 text-white shadow-xs">
                            REMEDIAL
                          </span>
                        )}
                      </td>
                      <td className="py-2.5 px-3 text-slate-700 font-medium truncate max-w-xs">
                        {s.notes || (s.is_tuntas ? 'Pertahankan prestasi belajar dan keaktifan di kelas.' : 'Perlu bimbingan intensif pada materi pemrograman.')}
                      </td>
                      <td className="py-2.5 px-3 text-center">
                        <button
                          type="button"
                          onClick={() => setPreviewStudent(s)}
                          className="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-bold bg-blue-900 text-white hover:bg-blue-950 shadow-xs cursor-pointer"
                        >
                          <Eye className="w-3 h-3" />
                          <span>Pratinjau PDF</span>
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      {/* ─── MODAL PRATINJAU DOKUMEN E-PROGRESS REPORT BER-QR CODE ─── */}
      {previewStudent && (
        <div className="fixed inset-0 z-50 bg-slate-950/70 flex items-center justify-center p-4 overflow-y-auto">
          <div className="bg-white rounded-2xl max-w-3xl w-full p-8 shadow-2xl space-y-6 relative border-2 border-slate-300">
            {/* Close Button */}
            <button
              type="button"
              onClick={() => setPreviewStudent(null)}
              className="absolute top-5 right-5 p-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 cursor-pointer"
            >
              <X className="w-5 h-5" />
            </button>

            {/* Official Header Kop Surat */}
            <div className="border-b-2 border-slate-900 pb-4 text-center space-y-1">
              <span className="text-[11px] font-black tracking-widest uppercase text-slate-500">
                KEMENTERIAN PENDIDIKAN, KEBUDAYAAN, RISET, DAN TEKNOLOGI
              </span>
              <h2 className="text-xl font-black text-slate-900 tracking-tight">
                SMP LABORATORIUM UNIVERSITAS NEGERI MALANG
              </h2>
              <p className="text-xs text-slate-600 font-medium">
                Jl. Simpang Bogor No. T-7 Malang, Jawa Timur • Website: labschool.um.ac.id
              </p>
              <div className="pt-2">
                <span className="inline-block px-3 py-1 bg-blue-900 text-white text-xs font-black tracking-wider uppercase rounded">
                  LEMBAR E-PROGRESS REPORT AKADEMIK & KARAKTER (BULAN {reportPeriodMonth.toUpperCase()})
                </span>
              </div>
            </div>

            {/* Student Bio Grid */}
            <div className="grid grid-cols-2 gap-4 text-xs bg-slate-50 p-4 rounded-xl border border-slate-200">
              <div className="space-y-1.5">
                <p><span className="text-slate-500 font-bold w-28 inline-block">Nama Siswa:</span> <strong className="text-slate-900 text-sm">{previewStudent.name}</strong></p>
                <p><span className="text-slate-500 font-bold w-28 inline-block">Nomor Induk (NIS):</span> <span className="font-mono font-bold text-slate-800">{previewStudent.nis}</span></p>
                <p><span className="text-slate-500 font-bold w-28 inline-block">Kelas / Rombel:</span> <strong className="text-slate-900">Kelas IX-A</strong></p>
              </div>
              <div className="space-y-1.5">
                <p><span className="text-slate-500 font-bold w-28 inline-block">Tahun Ajaran:</span> <strong className="text-slate-900">2026/2027 (Ganjil)</strong></p>
                <p><span className="text-slate-500 font-bold w-28 inline-block">Wali Kelas:</span> <strong className="text-slate-900">Budi Santoso, S.Kom.</strong></p>
                <p><span className="text-slate-500 font-bold w-28 inline-block">Kurikulum:</span> <strong className="text-blue-900">Kurikulum Merdeka (KKTP: 75)</strong></p>
              </div>
            </div>

            {/* Academic Score Summary Table */}
            <div className="space-y-2">
              <h4 className="text-xs font-black uppercase tracking-wider text-slate-800">I. Capaian Nilai Akademik</h4>
              <table className="w-full text-left text-xs border border-slate-300">
                <thead className="bg-slate-100 text-slate-800 font-extrabold uppercase text-[10px]">
                  <tr>
                    <th className="py-2 px-3 border border-slate-300">Mata Pelajaran</th>
                    <th className="py-2 px-2 text-center border border-slate-300 w-20">KKTP</th>
                    <th className="py-2 px-2 text-center border border-slate-300 w-24">Nilai Akhir</th>
                    <th className="py-2 px-3 text-center border border-slate-300 w-28">Status</th>
                    <th className="py-2 px-3 border border-slate-300">Deskripsi Capaian Kompetensi</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td className="py-2 px-3 border border-slate-300 font-bold">Informatika &amp; Pemrograman</td>
                    <td className="py-2 px-2 text-center border border-slate-300 font-bold">75</td>
                    <td className="py-2 px-2 text-center border border-slate-300 font-black text-sm text-blue-900">
                      {previewStudent.final_score}
                    </td>
                    <td className="py-2 px-3 text-center border border-slate-300">
                      <span className={`px-2 py-0.5 rounded text-[10px] font-black text-white ${previewStudent.is_tuntas ? 'bg-emerald-800' : 'bg-rose-800'}`}>
                        {previewStudent.is_tuntas ? 'TUNTAS' : 'REMEDIAL'}
                      </span>
                    </td>
                    <td className="py-2 px-3 border border-slate-300 text-slate-700">
                      Menunjukkan penguasaan istimewa dalam struktur logika pemrograman dan desain web responsif.
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            {/* Presensi & Karakter Box */}
            <div className="grid grid-cols-2 gap-4 text-xs">
              <div className="p-3 border border-slate-200 rounded-lg space-y-1 bg-slate-50">
                <h5 className="font-black text-slate-900 uppercase text-[11px]">II. Kehadiran Sesi Belajar</h5>
                <p className="text-slate-700">Persentase Kehadiran: <strong className="text-emerald-800">97.2% (Sangat Baik)</strong></p>
                <p className="text-slate-600">Sakit: 1 hari • Izin: 0 hari • Alpa: 0 hari</p>
              </div>

              <div className="p-3 border border-slate-200 rounded-lg space-y-1 bg-slate-50">
                <h5 className="font-black text-slate-900 uppercase text-[11px]">III. Karakter Pelajar Pancasila</h5>
                <p className="text-slate-700">Dimensi Unggul: <strong>Gotong Royong &amp; Bernalar Kritis</strong></p>
                <p className="text-slate-600">Akumulasi Poin Disiplin: <strong className="text-blue-900">+15 Poin (Prestasi KBM)</strong></p>
              </div>
            </div>

            {/* Catatan Walas Box */}
            <div className="p-3 bg-blue-50 border border-blue-200 rounded-lg text-xs space-y-1">
              <span className="font-bold text-blue-950 uppercase text-[10px] block">Catatan Pembinaan Wali Kelas:</span>
              <p className="text-blue-900 font-medium italic">
                "{previewStudent.notes || 'Ananda menunjukkan kemajuan belajar yang sangat membanggakan dan aktif membantu rekan tim lab. Pertahankan fokus belajar.'}"
              </p>
            </div>

            {/* Validation QR Code & Signatures */}
            <div className="pt-4 border-t border-slate-300 flex items-center justify-between">
              {/* QR Code Verification Link */}
              <div className="flex items-center gap-3">
                <div className="w-16 h-16 bg-slate-900 text-white rounded-lg flex items-center justify-center p-1.5 shrink-0 shadow-xs">
                  <QrCode className="w-12 h-12 text-white" />
                </div>
                <div className="text-xs">
                  <span className="text-[10px] font-black uppercase text-slate-500 block">Verifikasi Keaslian Dokumen</span>
                  <span className="font-mono text-[11px] font-bold text-slate-800 block">TOKEN: PR-IXA-{previewStudent.nis}-2026</span>
                  <span className="text-[10px] text-blue-900 font-semibold block">sistlabum.ac.id/verifikasi/{previewStudent.nis}</span>
                </div>
              </div>

              {/* Signatures */}
              <div className="text-right text-xs">
                <p className="text-slate-500">Malang, 14 Oktober 2026</p>
                <p className="font-bold text-slate-900 mt-1">Kepala Sekolah SMP Labschool UM</p>
                <div className="h-10 flex items-center justify-end">
                  <span className="text-[10px] font-black uppercase px-2 py-0.5 rounded bg-emerald-800 text-white shadow-xs">
                    ✓ DITANDATANGANI SECARA ELEKTRONIK
                  </span>
                </div>
                <p className="font-black text-slate-900">Prof. Dr. Sujarwo, M.Pd.</p>
                <p className="text-[10px] text-slate-500 font-mono">NIP. 197405121999031002</p>
              </div>
            </div>

            {/* Modal Actions */}
            <div className="flex items-center justify-end gap-3 pt-4 border-t border-slate-200">
              <button
                type="button"
                onClick={() => setPreviewStudent(null)}
                className="px-4 py-2 text-xs font-bold border border-slate-300 rounded-lg hover:bg-slate-100 text-slate-700 cursor-pointer"
              >
                Tutup
              </button>
              <button
                type="button"
                onClick={() => {
                  window.print();
                }}
                className="px-4 py-2 text-xs font-black bg-blue-900 text-white rounded-lg hover:bg-blue-950 flex items-center gap-2 shadow-xs cursor-pointer"
              >
                <Printer className="w-3.5 h-3.5" />
                <span>Cetak / Simpan PDF</span>
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
