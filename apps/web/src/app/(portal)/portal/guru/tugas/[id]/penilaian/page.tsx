'use client';

import React, { useState, useEffect } from 'react';
import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { 
  ArrowLeft, 
  CheckCircle2, 
  Clock, 
  AlertCircle, 
  Download, 
  FileText, 
  ChevronLeft, 
  ChevronRight, 
  Send, 
  ZoomIn, 
  ZoomOut,
  BookmarkCheck,
  FileSpreadsheet
} from 'lucide-react';
import { lmsApi } from '@/lib/api-client';
import { AssignmentSubmission } from '@/types/lms';
import { toast } from 'sonner';

export default function SpeedGraderPage() {
  const params = useParams();
  const assignmentId = (params?.id as string) || '';
  const queryClient = useQueryClient();

  // Active student index in submission list
  const [activeStudentIndex, setActiveStudentIndex] = useState(0);
  const [scoreInput, setScoreInput] = useState<string>('');
  const [feedbackInput, setFeedbackInput] = useState<string>('');
  const [zoomLevel, setZoomLevel] = useState(100);

  // Fetch Assignment Detail
  const { data: assignment } = useQuery({
    queryKey: ['assignment', assignmentId],
    queryFn: () => lmsApi.getAssignmentDetail(assignmentId),
  });

  // Fetch Submissions for this assignment
  const { data: submissions = [], isLoading, isError } = useQuery({
    queryKey: ['submissions', assignmentId],
    queryFn: () => lmsApi.getSubmissions(assignmentId),
  });

  const currentSubmission: AssignmentSubmission | undefined = submissions[activeStudentIndex];

  // Sync input fields when active student changes
  useEffect(() => {
    if (currentSubmission) {
      setScoreInput(currentSubmission.score !== null && currentSubmission.score !== undefined ? String(currentSubmission.score) : '');
      setFeedbackInput(currentSubmission.feedback || '');
    }
  }, [activeStudentIndex, currentSubmission]);

  // Mutation to grade
  const gradeMutation = useMutation({
    mutationFn: (payload: { score: number; feedback: string }) => 
      lmsApi.gradeSubmission(assignmentId, currentSubmission!.id, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['submissions', assignmentId] });
      queryClient.invalidateQueries({ queryKey: ['assignments'] });
      toast.success(`Nilai untuk ${currentSubmission?.student.name} berhasil disimpan!`);
      
      // Auto move to next student if available
      if (activeStudentIndex < submissions.length - 1) {
        setActiveStudentIndex(prev => prev + 1);
      }
    },
    onError: () => {
      toast.error('Gagal menyimpan nilai.');
    }
  });

  const handleSaveGrade = (e?: React.FormEvent) => {
    if (e) e.preventDefault();
    if (!currentSubmission) return;

    const numScore = parseFloat(scoreInput);
    if (isNaN(numScore) || numScore < 0 || numScore > 100) {
      toast.error('Skor nilai harus berupa angka antara 0 hingga 100.');
      return;
    }

    gradeMutation.mutate({
      score: numScore,
      feedback: feedbackInput,
    });
  };

  // Sync to Grade Ledger (BE-GRD-01)
  const handleSyncToGradebook = () => {
    toast.success(
      `Seluruh nilai tugas "${assignment?.title}" berhasil disinkronkan ke Buku Nilai Rapor Kurikulum Merdeka!`
    );
  };

  // Export submissions to CSV
  const handleExportCSV = () => {
    const rows = [
      ['NIS', 'Nama Siswa', 'Waktu Kumpul', 'Keterlambatan', 'Nilai (0-100)', 'Status KKTP', 'Umpan Balik Guru'],
      ...submissions.map(s => [
        s.student.nis,
        `"${s.student.name}"`,
        s.submitted_at,
        s.is_late ? 'Terlambat' : 'Tepat Waktu',
        s.score !== null && s.score !== undefined ? s.score : 'Belum Dinilai',
        (s.score || 0) >= 75 ? 'TUNTAS' : 'BELUM TUNTAS',
        `"${s.feedback || ''}"`
      ])
    ];
    const csvContent = 'data:text/csv;charset=utf-8,' + rows.map(e => e.join(',')).join('\n');
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement('a');
    link.setAttribute('href', encodedUri);
    link.setAttribute('download', `Nilai_${assignment?.title.replace(/[^a-zA-Z0-9]/g, '_')}_${new Date().toISOString().slice(0, 10)}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    toast.success('Daftar nilai berhasil diekspor.');
  };

  // Keyboard shortcut Ctrl+Enter to save & next
  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
        e.preventDefault();
        handleSaveGrade();
      }
    };
    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [scoreInput, feedbackInput, currentSubmission]);

  return (
    <div className="flex flex-col h-[calc(100vh-6rem)]">
      {/* ─── TOP SPEEDGRADER NAVIGATION BAR ─── */}
      <div className="bg-white border-b border-slate-300 px-6 py-3 flex items-center justify-between shrink-0 shadow-xs">
        <div className="flex items-center gap-4">
          <Link
            href="/guru/tugas"
            className="inline-flex items-center gap-1.5 text-xs font-bold text-slate-700 hover:text-blue-900 transition-colors"
          >
            <ArrowLeft className="w-4 h-4" />
            <span>Kembali ke Daftar Tugas</span>
          </Link>
          <div className="h-4 w-px bg-slate-300"></div>
          <div>
            <h1 className="text-sm font-extrabold text-slate-900 leading-tight">
              {assignment?.title || 'Memuat Tugas...'}
            </h1>
            <p className="text-[11px] text-slate-600 font-semibold flex items-center gap-2 mt-0.5">
              <span>Kelas IX-A</span>
              <span>•</span>
              <span className="font-bold text-blue-900">{assignment?.grade_component_name || 'Formatif 2'}</span>
              <span>•</span>
              <span>KKTP: 75</span>
            </p>
          </div>
        </div>

        {/* Action Controls & Student Navigator */}
        <div className="flex items-center gap-3">
          {/* Sync to Gradebook (BE-GRD-01) */}
          <button
            onClick={handleSyncToGradebook}
            className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-teal-800 text-white text-xs font-bold hover:bg-teal-900 transition-colors shadow-xs cursor-pointer"
            title="Kirim nilai ke Buku Nilai Rapor Semester"
          >
            <BookmarkCheck className="w-3.5 h-3.5 text-teal-300" />
            <span>Sinkron ke Rapor</span>
          </button>

          {/* Export CSV */}
          <button
            onClick={handleExportCSV}
            className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-300 bg-white text-slate-800 text-xs font-bold hover:bg-slate-50 transition-colors shadow-xs cursor-pointer"
          >
            <FileSpreadsheet className="w-3.5 h-3.5 text-slate-600" />
            <span>Unduh Nilai</span>
          </button>

          <div className="h-4 w-px bg-slate-300"></div>

          {/* Student Navigator Carousel Buttons */}
          <div className="flex items-center gap-2">
            <span className="text-xs font-bold text-slate-800 font-mono">
              {submissions.length > 0 ? activeStudentIndex + 1 : 0} / {submissions.length}
            </span>
            <button
              onClick={() => setActiveStudentIndex(prev => Math.max(0, prev - 1))}
              disabled={activeStudentIndex === 0}
              className="w-8 h-8 rounded-lg border border-slate-300 bg-white flex items-center justify-center text-slate-800 hover:bg-slate-100 disabled:opacity-40 cursor-pointer shadow-xs"
            >
              <ChevronLeft className="w-4 h-4" />
            </button>
            <button
              onClick={() => setActiveStudentIndex(prev => Math.min(submissions.length - 1, prev + 1))}
              disabled={activeStudentIndex === submissions.length - 1}
              className="w-8 h-8 rounded-lg border border-slate-300 bg-white flex items-center justify-center text-slate-800 hover:bg-slate-100 disabled:opacity-40 cursor-pointer shadow-xs"
            >
              <ChevronRight className="w-4 h-4" />
            </button>
          </div>
        </div>
      </div>

      {/* ─── MAIN TWO-PANE SPEEDGRADER SPLIT VIEW ─── */}
      <div className="flex-1 flex overflow-hidden">
        {/* ─── LEFT PANE: DOCUMENT VIEWER CANVAS (65%) ─── */}
        <div className="flex-1 bg-slate-200 flex flex-col border-r border-slate-300 relative overflow-hidden">
          {/* Document Top Bar - SOLID */}
          <div className="bg-white border-b border-slate-300 px-4 py-2.5 flex items-center justify-between text-xs shadow-xs">
            <div className="flex items-center gap-2 text-slate-900 font-bold truncate max-w-md">
              <FileText className="w-4 h-4 text-blue-900 shrink-0" />
              <span className="truncate">
                {currentSubmission?.file?.filename || 'Laporan_Praktikum_Siswa.pdf'}
              </span>
              <span className="text-[11px] text-slate-600 font-normal">
                ({currentSubmission?.file ? `${(currentSubmission.file.size_bytes / 1024 / 1024).toFixed(1)} MB` : '1.4 MB'})
              </span>
            </div>

            {/* Viewer Controls */}
            <div className="flex items-center gap-2">
              <button
                onClick={() => setZoomLevel(prev => Math.max(50, prev - 10))}
                className="p-1 rounded-md border border-slate-300 hover:bg-slate-100 text-slate-700 font-bold"
                title="Perkecil"
              >
                <ZoomOut className="w-4 h-4" />
              </button>
              <span className="text-xs font-mono font-bold text-slate-800 w-12 text-center">{zoomLevel}%</span>
              <button
                onClick={() => setZoomLevel(prev => Math.min(150, prev + 10))}
                className="p-1 rounded-md border border-slate-300 hover:bg-slate-100 text-slate-700 font-bold"
                title="Perbesar"
              >
                <ZoomIn className="w-4 h-4" />
              </button>
              <button
                onClick={() => toast.info('Mengunduh berkas laporan siswa...')}
                className="inline-flex items-center gap-1.5 px-3 py-1 rounded-md bg-slate-800 hover:bg-slate-900 text-white text-xs font-bold ml-2 shadow-xs"
              >
                <Download className="w-3.5 h-3.5" />
                <span>Unduh File</span>
              </button>
            </div>
          </div>

          {/* Interactive Document Page Render Area */}
          <div className="flex-1 overflow-auto p-6 flex justify-center items-start">
            <div 
              className="bg-white shadow-lg rounded-lg border-2 border-slate-300 p-8 w-full max-w-2xl min-h-175 transition-transform duration-150 origin-top"
              style={{ transform: `scale(${zoomLevel / 100})` }}
            >
              {/* Header Dokumen Siswa */}
              <div className="border-b-2 border-slate-800 pb-4 mb-6">
                <div className="flex justify-between items-start">
                  <div>
                    <h2 className="text-lg font-black text-slate-900 tracking-tight">LAPORAN PRAKTIKUM INFORMATIKA</h2>
                    <p className="text-xs text-slate-600 font-semibold mt-1">SMP-SMA Laboratorium Universitas Negeri Malang</p>
                  </div>
                  <span className="text-xs font-mono px-2.5 py-1 rounded-md bg-blue-900 text-white font-bold">
                    Halaman 1 / 3
                  </span>
                </div>
                <div className="mt-4 grid grid-cols-2 text-xs bg-slate-100 p-3 rounded-md border border-slate-300 font-medium">
                  <div><span className="text-slate-600">Penyusun:</span> <strong className="text-slate-900 font-bold">{currentSubmission?.student.name}</strong></div>
                  <div><span className="text-slate-600">NIS:</span> <strong className="text-slate-900 font-bold">{currentSubmission?.student.nis}</strong></div>
                </div>
              </div>

              {/* Konten Simulasi Dokumen */}
              <div className="space-y-4 text-xs text-slate-800 leading-relaxed font-sans">
                <h3 className="font-extrabold text-slate-900 text-sm">A. Ringkasan Desain Portofolio Web</h3>
                <p>
                  Dalam praktikum ini, saya telah merancang layout web responsif menggunakan CSS Grid 12-kolom untuk tampilan layar desktop (&gt; 1024px) dan Flexbox untuk adaptasi layar ponsel (&lt; 640px). Seluruh elemen struktural memanfaatkan semantic tags seperti &lt;header&gt;, &lt;main&gt;, &lt;section&gt;, dan &lt;footer&gt;.
                </p>

                <h3 className="font-extrabold text-slate-900 text-sm mt-4">B. Tangkapan Layar Responsif</h3>
                <div className="border border-slate-300 rounded-lg p-4 bg-slate-50 text-center">
                  <div className="w-full h-40 bg-slate-200 border border-slate-300 rounded flex items-center justify-center text-slate-700 font-mono text-xs font-bold">
                    [Tangkapan Layar Desktop & Mobile Siswa - Resolusi 1440px vs 375px]
                  </div>
                  <p className="text-[11px] text-slate-600 font-medium italic mt-2">Gambar 1: Uji coba responsivitas layout navigasi dan grid kartu proyek</p>
                </div>

                <h3 className="font-extrabold text-slate-900 text-sm mt-4">C. Kesimpulan & Kendala</h3>
                <p>
                  Semua kriteria penugasan telah terpenuhi. Navigasi menu berjalan mulus tanpa kendala layout shift.
                </p>

                {currentSubmission?.notes && (
                  <div className="mt-6 p-3.5 rounded-lg bg-blue-900 text-white text-xs">
                    <span className="font-bold block mb-1">Catatan Tambahan dari Siswa:</span>
                    <p className="text-blue-100 font-medium italic">"{currentSubmission.notes}"</p>
                  </div>
                )}
              </div>
            </div>
          </div>
        </div>

        {/* ─── RIGHT PANE: GRADING FORM & FEEDBACK (35%) ─── */}
        <div className="w-96 bg-white flex flex-col shrink-0 border-l border-slate-300 overflow-y-auto">
          {/* Student Profile Card - SOLID */}
          <div className="p-5 border-b border-slate-300 bg-slate-50">
            <div className="flex items-start gap-3">
              {currentSubmission?.student.avatar_url ? (
                <img
                  src={currentSubmission.student.avatar_url}
                  alt={currentSubmission.student.name}
                  className="w-11 h-11 rounded-lg object-cover border border-slate-200 shrink-0 shadow-xs"
                />
              ) : (
                <div className="w-11 h-11 rounded-lg bg-blue-900 text-white font-black flex items-center justify-center text-sm shrink-0 shadow-xs">
                  {currentSubmission?.student.name.slice(0, 2).toUpperCase() || 'SW'}
                </div>
              )}
              <div className="flex-1 min-w-0">
                <h3 className="font-extrabold text-sm text-slate-900 truncate">
                  {currentSubmission?.student.name}
                </h3>
                <p className="text-xs text-slate-600 font-mono font-semibold">
                  NIS: {currentSubmission?.student.nis} • Kelas IX-A
                </p>

                {/* Status Kumpul Badge - SOLID */}
                <div className="mt-2.5 flex items-center gap-2">
                  {currentSubmission?.is_late ? (
                    <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-md text-[11px] font-bold bg-rose-700 text-white shadow-xs">
                      <AlertCircle className="w-3 h-3" />
                      Terlambat
                    </span>
                  ) : (
                    <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-md text-[11px] font-bold bg-emerald-700 text-white shadow-xs">
                      <CheckCircle2 className="w-3 h-3" />
                      Tepat Waktu
                    </span>
                  )}
                  <span className="text-[11px] text-slate-600 font-mono font-semibold">
                    {new Date(currentSubmission?.submitted_at || Date.now()).toLocaleDateString('id-ID', {
                      day: 'numeric',
                      month: 'short',
                      hour: '2-digit',
                      minute: '2-digit',
                    })}
                  </span>
                </div>
              </div>
            </div>
          </div>

          {/* Form Penilaian */}
          <form onSubmit={handleSaveGrade} className="p-5 flex-1 flex flex-col justify-between space-y-5">
            <div className="space-y-4">
              {/* Input Skor Nilai */}
              <div>
                <div className="flex items-center justify-between mb-1.5">
                  <label className="text-xs font-black text-slate-900 uppercase tracking-wider">
                    Nilai Siswa (Skor 0 - 100)
                  </label>
                  <span className="text-xs font-bold text-white bg-slate-900 px-2 py-0.5 rounded-md shadow-xs">
                    KKTP: 75
                  </span>
                </div>

                <div className="relative">
                  <input
                    type="number"
                    min={0}
                    max={100}
                    step={1}
                    value={scoreInput}
                    onChange={(e) => setScoreInput(e.target.value)}
                    placeholder="Contoh: 92"
                    className="w-full px-4 py-2.5 text-xl font-black font-mono text-slate-900 bg-white border-2 border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-900 focus:border-blue-900"
                  />
                  <span className="absolute right-3.5 top-1/2 -translate-y-1/2 text-xs font-black text-slate-500">
                    / 100
                  </span>
                </div>

                {/* Quick Score Preset Chips - SOLID */}
                <div className="flex items-center gap-1.5 mt-2.5">
                  <span className="text-xs text-slate-600 font-bold mr-1">Preset:</span>
                  {[75, 80, 85, 90, 95, 100].map((preset) => (
                    <button
                      key={preset}
                      type="button"
                      onClick={() => setScoreInput(String(preset))}
                      className="px-2 py-1 text-xs font-bold rounded-md bg-white border border-slate-300 hover:bg-blue-900 hover:text-white hover:border-blue-900 text-slate-800 transition-colors shadow-xs"
                    >
                      {preset}
                    </button>
                  ))}
                </div>
              </div>

              {/* Umpan Balik Guru (Feedback) */}
              <div>
                <label className="block text-xs font-black text-slate-900 uppercase tracking-wider mb-1.5">
                  Catatan & Umpan Balik Guru
                </label>
                <textarea
                  rows={6}
                  value={feedbackInput}
                  onChange={(e) => setFeedbackInput(e.target.value)}
                  placeholder="Tuliskan catatan evaluasi, poin apresiasi, atau saran perbaikan untuk siswa..."
                  className="w-full px-3.5 py-2.5 text-xs font-medium text-slate-900 bg-white border-2 border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-900 focus:border-blue-900 resize-none"
                />
              </div>

              {/* Status Kelulusan KKTP Indicator - SOLID */}
              {scoreInput && (
                <div className={`p-3 rounded-md border text-xs font-bold shadow-xs ${
                  parseFloat(scoreInput) >= 75
                    ? 'bg-emerald-700 text-white border-emerald-800'
                    : 'bg-rose-700 text-white border-rose-800'
                }`}>
                  {parseFloat(scoreInput) >= 75 ? (
                    <div className="flex items-center gap-2">
                      <CheckCircle2 className="w-4 h-4 text-emerald-200" />
                      <span>MEMENUHI KKTP (TUNTAS BELAJAR)</span>
                    </div>
                  ) : (
                    <div className="flex items-center gap-2">
                      <AlertCircle className="w-4 h-4 text-rose-200" />
                      <span>DI BAWAH KKTP (PERLU REMEDIAL / PEMBINAAN)</span>
                    </div>
                  )}
                </div>
              )}
            </div>

            {/* Bottom Actions */}
            <div className="space-y-2 pt-4 border-t border-slate-200">
              <button
                type="submit"
                disabled={gradeMutation.isPending || !scoreInput}
                className="w-full py-3 px-4 rounded-lg bg-blue-900 text-white text-xs font-black uppercase tracking-wider hover:bg-blue-950 transition-all shadow-md active:scale-98 disabled:opacity-50 flex items-center justify-center gap-2 cursor-pointer"
              >
                <Send className="w-4 h-4" />
                <span>{gradeMutation.isPending ? 'Menyimpan...' : 'Simpan Nilai & Siswa Berikutnya'}</span>
              </button>
              <p className="text-[11px] text-center text-slate-600 font-semibold">
                Pintasan: <kbd className="px-1.5 py-0.5 bg-slate-200 rounded border border-slate-300 font-mono text-[10px]">Ctrl</kbd> + <kbd className="px-1.5 py-0.5 bg-slate-200 rounded border border-slate-300 font-mono text-[10px]">Enter</kbd>
              </p>
            </div>
          </form>
        </div>
      </div>

      {/* ─── BOTTOM STUDENT THUMBNAIL RAIL (SOLID SWITCHER) ─── */}
      <div className="h-14 bg-slate-100 border-t border-slate-300 px-6 flex items-center gap-2 overflow-x-auto shrink-0 shadow-inner">
        <span className="text-[11px] font-black uppercase tracking-wider text-slate-700 shrink-0 mr-2">
          Siswa Rombel:
        </span>
        {submissions.map((sub, idx) => {
          const isSelected = idx === activeStudentIndex;
          const isGraded = sub.score !== null && sub.score !== undefined;

          return (
            <button
              key={sub.id}
              onClick={() => setActiveStudentIndex(idx)}
              className={`px-3 py-1.5 rounded-md text-xs font-bold flex items-center gap-2 shrink-0 transition-all cursor-pointer border ${
                isSelected
                  ? 'bg-blue-900 text-white border-blue-900 shadow-xs'
                  : 'bg-white text-slate-800 border-slate-300 hover:bg-slate-200'
              }`}
            >
              <span>{idx + 1}. {sub.student.name.split(' ')[0]}</span>
              {isGraded ? (
                <span className={`px-1.5 py-0.2 rounded text-[10px] font-black ${
                  isSelected ? 'bg-emerald-500 text-white' : 'bg-emerald-700 text-white'
                }`}>
                  {sub.score}
                </span>
              ) : sub.is_late ? (
                <span className="px-1.5 py-0.2 rounded text-[9px] font-black bg-rose-700 text-white">
                  Late
                </span>
              ) : (
                <span className="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
              )}
            </button>
          );
        })}
      </div>
    </div>
  );
}
