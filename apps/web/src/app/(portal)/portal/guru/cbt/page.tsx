'use client';

import React, { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { 
  MonitorPlay, 
  HelpCircle, 
  Key, 
  Clock, 
  Users, 
  CheckCircle2, 
  AlertCircle, 
  RefreshCw, 
  Plus, 
  Search, 
  Filter, 
  Copy, 
  BookOpen, 
  Eye, 
  Lock, 
  ChevronDown, 
  ChevronUp,
  Award,
  PenTool,
  Send,
  BarChart3,
  RefreshCcw,
  Check,
  ArrowRight,
  FileText,
  Printer,
  X
} from 'lucide-react';
import { lmsApi } from '@/lib/api-client';
import { CBTExam, CBTQuestion, CognitiveLevel, QuestionType } from '@/types/lms';
import { toast } from 'sonner';

export default function CBTGuruPage() {
  const [activeTab, setActiveTab] = useState<'jadwal' | 'bank_soal' | 'koreksi_uraian' | 'analisis_remedial'>('jadwal');
  const [selectedExamId, setSelectedExamId] = useState<string>('exm-001');
  const [copiedToken, setCopiedToken] = useState<boolean>(false);
  const [filterCognitive, setFilterCognitive] = useState<string>('ALL');
  const [searchQuestion, setSearchQuestion] = useState<string>('');
  const [expandedQuestionId, setExpandedQuestionId] = useState<string | null>('qst-001');
  const [isBeritaAcaraOpen, setIsBeritaAcaraOpen] = useState<boolean>(false);

  // Queries
  const { data: exams = [], isLoading: isLoadingExams } = useQuery({
    queryKey: ['cbt-exams'],
    queryFn: () => lmsApi.getCBTExams('cls-9a', 'sbj-inf'),
  });

  const { data: questions = [], isLoading: isLoadingQuestions } = useQuery({
    queryKey: ['cbt-questions'],
    queryFn: () => lmsApi.getCBTQuestions('sbj-inf'),
  });

  const activeExam = exams.find(e => e.id === selectedExamId) || exams[0];

  const handleCopyToken = (token: string) => {
    navigator.clipboard.writeText(token);
    setCopiedToken(true);
    setTimeout(() => setCopiedToken(false), 2000);
  };

  // Filtered Questions
  const filteredQuestions = questions.filter(q => {
    if (filterCognitive !== 'ALL' && q.cognitive_level !== filterCognitive) return false;
    if (searchQuestion && !q.question_text.toLowerCase().includes(searchQuestion.toLowerCase()) && !q.topic.toLowerCase().includes(searchQuestion.toLowerCase())) return false;
    return true;
  });

  // Mock Active Exam Student Monitor Data
  const monitorStudents = [
    { id: 'ms-1', nis: '240901', name: 'Ahmad Fauzan Maulana', status: 'mengerjakan', progress: '28/30', timeLeft: '34 menit', ip: '192.168.1.101', avatar_url: 'https://images.unsplash.com/photo-1539571696357-5a69c17a67c6?auto=format&fit=crop&q=80&w=150' },
    { id: 'ms-2', nis: '240902', name: 'Nabila Putri Andini', status: 'selesai', progress: '30/30', timeLeft: 'Terkumpul', ip: '192.168.1.102', score: 94, avatar_url: 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&q=80&w=150' },
    { id: 'ms-3', nis: '240903', name: 'Rizky Pratama Wijaya', status: 'mengerjakan', progress: '19/30', timeLeft: '32 menit', ip: '192.168.1.103', avatar_url: 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&q=80&w=150' },
    { id: 'ms-4', nis: '240904', name: 'Siti Aisyah Rahmawati', status: 'selesai', progress: '30/30', timeLeft: 'Terkumpul', ip: '192.168.1.104', score: 88, avatar_url: 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?auto=format&fit=crop&q=80&w=150' },
    { id: 'ms-5', nis: '240905', name: 'Bima Aditya Nugraha', status: 'mengerjakan', progress: '22/30', timeLeft: '33 menit', ip: '192.168.1.105', avatar_url: 'https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?auto=format&fit=crop&q=80&w=150' },
    { id: 'ms-6', nis: '240906', name: 'Citra Dewi Lestari', status: 'mengerjakan', progress: '25/30', timeLeft: '35 menit', ip: '192.168.1.106', avatar_url: 'https://images.unsplash.com/photo-1524504388940-b1c1722653e1?auto=format&fit=crop&q=80&w=150' },
    { id: 'ms-7', nis: '240907', name: 'Dimas Anggara Putra', status: 'terputus', progress: '14/30', timeLeft: '31 menit', ip: '192.168.1.107', warning: 'Koneksi browser terputus 2m lalu', avatar_url: 'https://images.unsplash.com/photo-1492562080023-ab3db95bfbce?auto=format&fit=crop&q=80&w=150' },
    { id: 'ms-8', nis: '240908', name: 'Eka Nur Azizah', status: 'mengerjakan', progress: '27/30', timeLeft: '36 menit', ip: '192.168.1.108', avatar_url: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&q=80&w=150' },
  ];

  return (
    <div className="flex flex-col gap-3 max-w-full xl:h-[calc(100vh-112px)] xl:overflow-hidden text-slate-900">
      {/* ─── PAGE HEADER ─── */}
      <div className="bg-white border border-slate-200/90 rounded-2xl p-3 sm:p-3.5 shadow-xs shrink-0 flex flex-col xl:flex-row xl:items-center justify-between gap-3">
        <div>
          <div className="flex items-center gap-2 mb-1">
            <span className="text-[11px] font-black uppercase tracking-wider px-2 py-0.5 rounded bg-blue-900 text-white shadow-2xs">
              CBT Digital Examination
            </span>
            <span className="text-xs text-slate-500 font-semibold">• BE-CBT-01 (P0)</span>
          </div>
          <h1 className="text-lg sm:text-xl font-black text-slate-900 tracking-tight">
            Bank Soal & Pengawasan Ujian Digital (CBT)
          </h1>
          <p className="text-xs text-slate-600 mt-0.5 line-clamp-1 max-w-2xl">
            Kelola butir soal HOTS/MOTS, jadwal asesmen sumatif, distribusi token, dan live monitoring ujian.
          </p>
        </div>

        {/* Tab Switcher */}
        <div className="flex flex-wrap items-center bg-slate-100 p-1 rounded-xl border border-slate-200 shrink-0">
          <button
            onClick={() => setActiveTab('jadwal')}
            className={`flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer ${
              activeTab === 'jadwal'
                ? 'bg-blue-900 text-white shadow-xs'
                : 'text-slate-600 hover:text-slate-900'
            }`}
          >
            <MonitorPlay className="w-3.5 h-3.5" />
            <span>Pengawasan Sesi</span>
          </button>
          <button
            onClick={() => setActiveTab('bank_soal')}
            className={`flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer ${
              activeTab === 'bank_soal'
                ? 'bg-blue-900 text-white shadow-xs'
                : 'text-slate-600 hover:text-slate-900'
            }`}
          >
            <HelpCircle className="w-3.5 h-3.5" />
            <span>Bank Soal ({questions.length})</span>
          </button>
          <button
            onClick={() => setActiveTab('koreksi_uraian')}
            className={`flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer ${
              activeTab === 'koreksi_uraian'
                ? 'bg-blue-900 text-white shadow-xs'
                : 'text-slate-600 hover:text-slate-900'
            }`}
          >
            <PenTool className="w-3.5 h-3.5" />
            <span>Koreksi Uraian</span>
            <span className="text-[9px] px-1.5 py-0.2 rounded bg-amber-600 text-white font-black">
              2
            </span>
          </button>
          <button
            onClick={() => setActiveTab('analisis_remedial')}
            className={`flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer ${
              activeTab === 'analisis_remedial'
                ? 'bg-blue-900 text-white shadow-xs'
                : 'text-slate-600 hover:text-slate-900'
            }`}
          >
            <BarChart3 className="w-3.5 h-3.5" />
            <span>Analisis & Remedial</span>
            <span className="text-[9px] px-1.5 py-0.2 rounded bg-rose-700 text-white font-black">
              4
            </span>
          </button>
        </div>
      </div>

      {/* ─── TAB 1: JADWAL & LIVE MONITORING UJIAN ─── */}
      {activeTab === 'jadwal' && (
        <div className="flex-1 min-h-0 overflow-y-auto space-y-3.5 pr-1">
          {/* Active Exam Overview Card with border-l-4 */}
          {activeExam && (
            <div className="bg-white border border-slate-200/90 rounded-xl p-5 shadow-xs space-y-4 border-l-4 border-l-blue-900">
              <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-4 border-b border-slate-200 pb-4">
                <div>
                  <div className="flex items-center gap-2 mb-1.5">
                    <span className="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider bg-emerald-800 text-white shadow-xs">
                      SESI UJIAN AKTIF
                    </span>
                    <span className="text-xs font-bold text-slate-500">
                      Kelas IX-A • Informatika & Desain Web
                    </span>
                  </div>
                  <h2 className="text-lg font-black text-slate-900">
                    {activeExam.title}
                  </h2>
                </div>

                {/* Room Token Display (Solid High Contrast) */}
                <div className="flex items-center gap-3 bg-slate-900 text-white px-4 py-3 rounded-xl shadow-xs">
                  <div>
                    <span className="text-[10px] uppercase font-bold text-slate-400 block tracking-wider">
                      TOKEN RUANG UJIAN
                    </span>
                    <span className="text-xl font-black font-mono tracking-widest text-amber-400">
                      {activeExam.room_token}
                    </span>
                  </div>
                  <button
                    type="button"
                    onClick={() => handleCopyToken(activeExam.room_token)}
                    className="p-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 transition-colors cursor-pointer"
                    title="Salin Token Ujian"
                  >
                    <Copy className="w-4 h-4" />
                  </button>
                  {copiedToken && (
                    <span className="text-[11px] font-bold text-emerald-400">Tersalin!</span>
                  )}
                </div>
              </div>

              {/* Meta Grid */}
              <div className="grid grid-cols-2 sm:grid-cols-4 gap-3.5">
                <div className="p-3.5 bg-slate-50 border border-slate-200/90 rounded-xl">
                  <span className="text-[10px] font-bold text-slate-500 block uppercase tracking-wider">Durasi Pengerjaan</span>
                  <span className="text-base font-black text-slate-900 mt-1 block">
                    {activeExam.duration_minutes} Menit
                  </span>
                </div>
                <div className="p-3.5 bg-slate-50 border border-slate-200/90 rounded-xl">
                  <span className="text-[10px] font-bold text-slate-500 block uppercase tracking-wider">Ambang KKTP</span>
                  <span className="text-base font-black text-slate-900 mt-1 block">
                    {activeExam.kktp} Poin
                  </span>
                </div>
                <div className="p-3.5 bg-slate-50 border border-slate-200/90 rounded-xl">
                  <span className="text-[10px] font-bold text-slate-500 block uppercase tracking-wider">Jumlah Butir Soal</span>
                  <span className="text-base font-black text-slate-900 mt-1 block">
                    {activeExam.total_questions} Butir (Acak)
                  </span>
                </div>
                <div className="p-3.5 bg-slate-50 border border-slate-200/90 rounded-xl">
                  <span className="text-[10px] font-bold text-slate-500 block uppercase tracking-wider">Peserta Terdaftar</span>
                  <span className="text-base font-black text-slate-900 mt-1 block">
                    {activeExam.total_participants} Siswa
                  </span>
                </div>
              </div>
            </div>
          )}

          {/* Live Proctoring Monitor Grid */}
          <div className="bg-white border border-slate-200/90 rounded-xl p-5 shadow-xs space-y-4">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200 pb-3">
              <div>
                <h3 className="text-sm font-black text-slate-900 uppercase tracking-wider">
                  Live Proctoring Monitor Lab Komputer
                </h3>
                <p className="text-xs text-slate-500 font-medium">
                  Pantau aktivitas pengerjaan, koneksi workstation siswa, dan progres submit secara realtime.
                </p>
              </div>

              <div className="flex items-center gap-2">
                <button
                  type="button"
                  onClick={() => setIsBeritaAcaraOpen(true)}
                  className="px-3.5 py-2 rounded-lg text-xs font-black bg-blue-900 hover:bg-blue-950 text-white flex items-center gap-1.5 shadow-xs cursor-pointer"
                >
                  <FileText className="w-3.5 h-3.5" />
                  <span>Berita Acara Ujian</span>
                </button>
                <button
                  type="button"
                  onClick={() => toast.success('Status monitor lab komputer berhasil diperbarui!')}
                  className="px-3 py-2 rounded-lg text-xs font-bold border border-slate-300 hover:bg-slate-100 text-slate-700 flex items-center gap-1.5 cursor-pointer shadow-xs"
                >
                  <RefreshCw className="w-3.5 h-3.5" />
                  <span>Segarkan</span>
                </button>
              </div>
            </div>

            {/* Monitor Table with Sticky Header */}
            <div className="border border-slate-200/90 rounded-xl overflow-hidden bg-white shadow-xs max-h-160 overflow-y-auto">
              <table className="w-full text-left text-xs border-collapse">
                <thead className="sticky top-0 bg-slate-100 z-10 border-b border-slate-300 shadow-xs">
                  <tr className="text-slate-800 font-extrabold uppercase text-[10px] tracking-wider">
                    <th className="py-3 px-3 w-12 text-center bg-slate-100">No</th>
                    <th className="py-3 px-3 w-24 bg-slate-100">NIS</th>
                    <th className="py-3 px-3 min-w-48 bg-slate-100">Nama Siswa</th>
                    <th className="py-3 px-3 w-32 text-center bg-slate-100">Status Ujian</th>
                    <th className="py-3 px-3 w-28 text-center bg-slate-100">Progres Soal</th>
                    <th className="py-3 px-3 w-28 text-center bg-slate-100">Sisa Waktu</th>
                    <th className="py-3 px-3 w-32 font-mono text-center bg-slate-100">IP Workstation</th>
                    <th className="py-3 px-3 w-36 text-center bg-slate-100">Aksi Pengawas</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-200">
                  {monitorStudents.map((ms, idx) => (
                    <tr key={ms.id} className="hover:bg-slate-50 transition-colors">
                      <td className="py-2.5 px-3 text-center font-bold text-slate-500">
                        {idx + 1}
                      </td>
                      <td className="py-2.5 px-3 font-mono font-semibold text-slate-700">
                        {ms.nis}
                      </td>
                      <td className="py-2.5 px-3">
                        <div className="flex items-center gap-2.5">
                          {ms.avatar_url ? (
                            <img
                              src={ms.avatar_url}
                              alt={ms.name}
                              className="w-7 h-7 rounded-full object-cover border border-slate-200 shrink-0 shadow-2xs"
                            />
                          ) : (
                            <div className="w-7 h-7 rounded-full bg-slate-200 text-slate-700 font-bold flex items-center justify-center text-[10px] shrink-0">
                              {ms.name.charAt(0)}
                            </div>
                          )}
                          <div>
                            <span className="font-bold text-slate-900 block leading-tight">{ms.name}</span>
                            {ms.warning && (
                              <span className="block text-[10px] text-rose-600 font-semibold mt-0.5">
                                ⚠️ {ms.warning}
                              </span>
                            )}
                          </div>
                        </div>
                      </td>
                      <td className="py-2.5 px-3 text-center">
                        {ms.status === 'mengerjakan' && (
                          <span className="inline-block px-2.5 py-0.5 rounded text-[10px] font-black uppercase tracking-wider bg-emerald-800 text-white shadow-xs">
                            Mengerjakan
                          </span>
                        )}
                        {ms.status === 'selesai' && (
                          <span className="inline-block px-2.5 py-0.5 rounded text-[10px] font-black uppercase tracking-wider bg-blue-900 text-white shadow-xs">
                            Tuntas ({ms.score})
                          </span>
                        )}
                        {ms.status === 'terputus' && (
                          <span className="inline-block px-2.5 py-0.5 rounded text-[10px] font-black uppercase tracking-wider bg-rose-700 text-white shadow-xs">
                            Terputus
                          </span>
                        )}
                      </td>
                      <td className="py-2.5 px-3 text-center font-black text-slate-800">
                        {ms.progress}
                      </td>
                      <td className="py-2.5 px-3 text-center font-bold text-slate-600">
                        {ms.timeLeft}
                      </td>
                      <td className="py-2.5 px-3 text-center font-mono text-slate-600">
                        {ms.ip}
                      </td>
                      <td className="py-2.5 px-3 text-center">
                        {ms.status === 'terputus' ? (
                          <button
                            type="button"
                            className="px-2.5 py-1 rounded text-[10px] font-black bg-amber-600 hover:bg-amber-700 text-white shadow-xs cursor-pointer"
                          >
                            Reset Sesi
                          </button>
                        ) : (
                          <button
                            type="button"
                            disabled={ms.status === 'selesai'}
                            className="px-2.5 py-1 rounded text-[10px] font-bold border border-slate-300 hover:bg-slate-100 text-slate-700 disabled:opacity-30 cursor-pointer shadow-xs"
                          >
                            Log Aktivitas
                          </button>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      {/* ─── TAB 2: BANK BUTIR SOAL ─── */}
      {activeTab === 'bank_soal' && (
        <div className="flex-1 min-h-0 overflow-y-auto space-y-3.5 pr-1">
          {/* Controls Bar */}
          <div className="bg-white border border-slate-200/90 rounded-xl p-2.5 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-2.5">
            <div className="relative flex-1 max-w-md">
              <Search className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
              <input
                type="text"
                placeholder="Cari butir soal berdasarkan teks atau topik..."
                value={searchQuestion}
                onChange={(e) => setSearchQuestion(e.target.value)}
                className="w-full pl-9 pr-3 py-2 text-xs bg-white border border-slate-300 rounded-lg focus:outline-none focus:border-blue-900 font-medium"
              />
            </div>

            {/* Cognitive Level Filters (Solid Buttons) */}
            <div className="flex items-center gap-1.5 bg-slate-100 p-1 rounded-lg border border-slate-200/90 shrink-0">
              <button
                type="button"
                onClick={() => setFilterCognitive('ALL')}
                className={`px-3 py-1.5 rounded-md text-xs font-bold transition-all cursor-pointer ${
                  filterCognitive === 'ALL'
                    ? 'bg-blue-900 text-white shadow-xs'
                    : 'text-slate-700 hover:text-slate-900'
                }`}
              >
                Semua Level ({questions.length})
              </button>
              <button
                type="button"
                onClick={() => setFilterCognitive('HOTS')}
                className={`px-3 py-1.5 rounded-md text-xs font-bold transition-all cursor-pointer ${
                  filterCognitive === 'HOTS'
                    ? 'bg-purple-900 text-white shadow-xs'
                    : 'text-slate-700 hover:text-slate-900'
                }`}
              >
                HOTS (C4-C6)
              </button>
              <button
                type="button"
                onClick={() => setFilterCognitive('MOTS')}
                className={`px-3 py-1.5 rounded-md text-xs font-bold transition-all cursor-pointer ${
                  filterCognitive === 'MOTS'
                    ? 'bg-teal-900 text-white shadow-xs'
                    : 'text-slate-700 hover:text-slate-900'
                }`}
              >
                MOTS (C3)
              </button>
            </div>
          </div>

          {/* Questions List */}
          <div className="space-y-3.5">
            {filteredQuestions.map((q, idx) => {
              const isExpanded = expandedQuestionId === q.id;
              const borderAccent = q.cognitive_level === 'HOTS' ? 'border-l-purple-900' : 'border-l-teal-800';

              return (
                <div 
                  key={q.id}
                  className={`bg-white border border-slate-200/90 rounded-xl p-5 shadow-xs space-y-4 border-l-4 ${borderAccent}`}
                >
                  <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-200 pb-3">
                    <div className="flex flex-wrap items-center gap-2">
                      <span className="w-6 h-6 rounded-md bg-blue-900 text-white font-black text-xs flex items-center justify-center">
                        {idx + 1}
                      </span>
                      <span className="font-extrabold text-xs text-slate-900">
                        {q.topic}
                      </span>
                      <span className="text-slate-300">•</span>
                      <span className={`px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider shadow-xs ${
                        q.cognitive_level === 'HOTS' ? 'bg-purple-900 text-white' : 'bg-teal-900 text-white'
                      }`}>
                        Level {q.cognitive_level}
                      </span>
                      <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-200 text-slate-800 uppercase">
                        {q.type.replace('_', ' ')}
                      </span>
                    </div>

                    <div className="flex items-center gap-3">
                      <span className="text-xs font-extrabold text-blue-900 bg-blue-50 border border-blue-200 px-2.5 py-0.5 rounded">
                        Bobot: {q.points} Poin
                      </span>
                      <button
                        type="button"
                        onClick={() => setExpandedQuestionId(isExpanded ? null : q.id)}
                        className="p-1 rounded text-slate-500 hover:text-slate-900 cursor-pointer"
                      >
                        {isExpanded ? <ChevronUp className="w-4 h-4" /> : <ChevronDown className="w-4 h-4" />}
                      </button>
                    </div>
                  </div>

                  {/* Question Stem */}
                  <div className="text-xs font-bold text-slate-800 leading-relaxed">
                    {q.question_text}
                  </div>

                  {/* Options List */}
                  {q.options && q.options.length > 0 && (
                    <div className="grid grid-cols-1 gap-2 pt-1">
                      {q.options.map((opt) => (
                        <div
                          key={opt.id}
                          className={`p-2.5 rounded-lg border text-xs flex items-center justify-between ${
                            opt.is_correct
                              ? 'bg-emerald-50 border-emerald-400 font-bold text-emerald-950'
                              : 'bg-white border-slate-200 text-slate-700'
                          }`}
                        >
                          <div className="flex items-center gap-2">
                            <span className="font-mono font-black uppercase text-[11px] w-5 text-slate-500">
                              {opt.id.replace('opt-', '')}.
                            </span>
                            <span>{opt.text}</span>
                          </div>
                          {opt.is_correct && (
                            <span className="px-2 py-0.5 rounded text-[10px] font-black bg-emerald-800 text-white shrink-0">
                              KUNCI JAWABAN
                            </span>
                          )}
                        </div>
                      ))}
                    </div>
                  )}

                  {/* Collapsible Explanation / Pembahasan */}
                  {isExpanded && q.explanation && (
                    <div className="p-3.5 bg-slate-50 border border-slate-200/90 rounded-lg text-xs space-y-1">
                      <div className="font-black text-slate-900 uppercase text-[10px] tracking-wider flex items-center gap-1.5">
                        <BookOpen className="w-3.5 h-3.5 text-blue-900" />
                        <span>Pembahasan Pedagogik & Rubrik:</span>
                      </div>
                      <p className="text-slate-600 leading-relaxed font-medium">
                        {q.explanation}
                      </p>
                    </div>
                  )}
                </div>
              );
            })}
          </div>
        </div>
      )}

      {/* ─── TAB 3: KOREKSI SOAL URAIAN CBT (ADM-CBT-05 & BE-CBT-05) ─── */}
      {activeTab === 'koreksi_uraian' && (
        <div className="flex-1 min-h-0 overflow-y-auto space-y-3.5 pr-1">
          <div className="bg-white border border-slate-200/90 rounded-2xl p-4 sm:p-5 shadow-xs space-y-3.5">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-200 pb-3">
              <div>
                <div className="flex items-center gap-2 mb-1">
                  <span className="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider bg-purple-900 text-white shadow-xs">
                    Rubrik Penilaian Esai
                  </span>
                  <span className="text-xs font-bold text-slate-500">
                    Sumatif Tengah Semester • Butir Soal #30 (Bobot Maksimal 20 Poin)
                  </span>
                </div>
                <h2 className="text-sm font-black text-slate-900">
                  Soal: Jelaskan perbedaan mendasar antara properti align-items dan justify-content pada container flexbox saat flex-direction bernilai row!
                </h2>
              </div>

              <div className="flex items-center gap-2 shrink-0">
                <span className="text-xs font-extrabold px-3 py-1.5 rounded-lg bg-slate-100 border border-slate-200/90 text-slate-800">
                  Progres: 30 / 32 Lembar Terkoreksi
                </span>
              </div>
            </div>

            {/* Rubrik Acuan Guru */}
            <div className="p-3.5 bg-amber-50 border border-amber-300 rounded-lg text-xs space-y-1">
              <span className="font-extrabold text-amber-950 uppercase text-[10px] tracking-wider block">
                Pedoman Kunci Jawaban Resmi:
              </span>
              <p className="text-amber-900 leading-relaxed font-medium">
                Siswa wajib menjelaskan bahwa justify-content mengatur penataan elemen di sepanjang sumbu utama (main-axis / horizontal), sedangkan align-items di sepanjang sumbu silang (cross-axis / vertikal). Skor penuh (20) jika menyebutkan kedua sumbu dengan tepat.
              </p>
            </div>

            {/* Antrean Jawaban Siswa */}
            <div className="space-y-3.5 pt-2">
              {[
                {
                  id: 'ans-1',
                  studentName: 'Ahmad Fauzan Maulana',
                  nis: '240901',
                  avatar_url: 'https://images.unsplash.com/photo-1539571696357-5a69c17a67c6?auto=format&fit=crop&q=80&w=150',
                  answerText: 'justify-content mengatur posisi anak elemen pada sumbu utama (main-axis) yaitu horizontal jika row. Sedangkan align-items mengatur posisi pada sumbu silang (cross-axis) yaitu vertikal.',
                  score: 20,
                  feedback: 'Jawaban sempurna dan tepat menggunakan peristilahan baku flexbox.',
                  status: 'graded'
                },
                {
                  id: 'ans-2',
                  studentName: 'Nabila Putri Andini',
                  nis: '240902',
                  avatar_url: 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&q=80&w=150',
                  answerText: 'justify-content bekerja pada main axis untuk menyelaraskan item secara horizontal (default row), sedangkan align-items bekerja pada cross axis untuk menyelaraskan secara vertikal tegak lurus.',
                  score: 20,
                  feedback: 'Penjelasan sistematis, pemahaman teori layout CSS sangat matang.',
                  status: 'graded'
                },
                {
                  id: 'ans-3',
                  studentName: 'Rizky Pratama Wijaya',
                  nis: '240903',
                  avatar_url: 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&q=80&w=150',
                  answerText: 'justify-content itu untuk meratakan flex items secara mendatar kiri tengah kanan, kalau align-items untuk meratakan dari atas bawah.',
                  score: 16,
                  feedback: 'Konsep sudah benar secara visual, namun belum menyebut istilah teknis main-axis dan cross-axis.',
                  status: 'pending'
                },
                {
                  id: 'ans-4',
                  studentName: 'Citra Dewi Lestari',
                  nis: '240906',
                  avatar_url: 'https://images.unsplash.com/photo-1524504388940-b1c1722653e1?auto=format&fit=crop&q=80&w=150',
                  answerText: 'justify-content mengatur sebaran jarak antar item di sumbu utama seperti center, space-between, space-around. align-items mengatur posisi item pada sumbu vertikal/tegak lurusnya.',
                  score: 18,
                  feedback: 'Contoh value property sangat baik, pemahaman layout komprehensif.',
                  status: 'pending'
                }
              ].map((item, idx) => (
                <div 
                  key={item.id} 
                  className={`p-4 rounded-xl border border-slate-200/90 bg-white shadow-xs space-y-3 border-l-4 ${
                    item.status === 'graded' ? 'border-l-emerald-800' : 'border-l-amber-600'
                  }`}
                >
                  <div className="flex items-center justify-between border-b border-slate-100 pb-2">
                    <div className="flex items-center gap-2.5">
                      <span className="w-6 h-6 rounded-md bg-blue-900 text-white font-black text-xs flex items-center justify-center shrink-0">
                        {idx + 1}
                      </span>
                      {item.avatar_url ? (
                        <img
                          src={item.avatar_url}
                          alt={item.studentName}
                          className="w-7 h-7 rounded-full object-cover border border-slate-200 shrink-0 shadow-2xs"
                        />
                      ) : (
                        <div className="w-7 h-7 rounded-full bg-slate-200 text-slate-700 font-bold flex items-center justify-center text-[10px] shrink-0">
                          {item.studentName.charAt(0)}
                        </div>
                      )}
                      <div>
                        <span className="font-extrabold text-xs text-slate-900 block leading-tight">{item.studentName}</span>
                        <span className="font-mono text-[11px] text-slate-500">NIS: {item.nis}</span>
                      </div>
                    </div>

                    <span className={`px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider ${
                      item.status === 'graded' ? 'bg-emerald-800 text-white' : 'bg-amber-600 text-white'
                    }`}>
                      {item.status === 'graded' ? 'Terkoreksi' : 'Menunggu Koreksi'}
                    </span>
                  </div>

                  <div className="p-3 bg-slate-50 border border-slate-200/90 rounded-lg text-xs font-semibold text-slate-800 leading-relaxed">
                    "{item.answerText}"
                  </div>

                  <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-1">
                    <div className="flex-1 max-w-lg">
                      <input
                        type="text"
                        defaultValue={item.feedback}
                        placeholder="Catatan umpan balik untuk siswa..."
                        className="w-full px-3 py-1.5 text-xs bg-white border border-slate-300 rounded-lg focus:border-blue-900 focus:outline-none"
                      />
                    </div>

                    <div className="flex items-center gap-3">
                      <div className="flex items-center gap-1.5 text-xs font-bold text-slate-700">
                        <span>Skor (0-20):</span>
                        <input
                          type="number"
                          min={0}
                          max={20}
                          defaultValue={item.score}
                          className="w-16 px-2 py-1 text-center font-black text-xs rounded border border-slate-300 focus:border-blue-900"
                        />
                      </div>

                      <button
                        type="button"
                        onClick={() => toast.success(`Nilai esai untuk ${item.studentName} berhasil disimpan dan disinkronkan ke Buku Nilai!`)}
                        className="px-3.5 py-1.5 rounded-lg text-xs font-bold bg-blue-900 hover:bg-blue-950 text-white shadow-xs cursor-pointer"
                      >
                        Simpan Nilai
                      </button>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>
      )}

      {/* ─── TAB 4: ANALISIS BUTIR SOAL & REMEDIAL OTOMATIS (BE-CBT-08 & BE-CBT-09) ─── */}
      {activeTab === 'analisis_remedial' && (
        <div className="flex-1 min-h-0 overflow-y-auto space-y-3.5 pr-1">
          {/* KPI Analytics */}
          <div className="grid grid-cols-2 sm:grid-cols-4 gap-2.5 sm:gap-3 shrink-0">
            <div className="bg-white border border-slate-200/90 rounded-xl p-4 shadow-xs border-l-4 border-l-blue-900">
              <span className="text-[10px] font-bold text-slate-500 uppercase">Rerata Skor Ujian</span>
              <span className="text-2xl font-black text-slate-900 block mt-1">82.5 Poin</span>
              <span className="text-[10px] font-bold text-emerald-700 mt-0.5 block">&gt; KKTP (75.0)</span>
            </div>

            <div className="bg-white border border-slate-200/90 rounded-xl p-4 shadow-xs border-l-4 border-l-emerald-800">
              <span className="text-[10px] font-bold text-slate-500 uppercase">Tingkat Ketuntasan</span>
              <span className="text-2xl font-black text-emerald-800 block mt-1">87.5%</span>
              <span className="text-[10px] text-slate-600 mt-0.5 block">28 dari 32 Siswa Tuntas</span>
            </div>

            <div className="bg-white border border-slate-200/90 rounded-xl p-4 shadow-xs border-l-4 border-l-purple-900">
              <span className="text-[10px] font-bold text-slate-500 uppercase">Reliabilitas Butir (r)</span>
              <span className="text-2xl font-black text-purple-900 block mt-1">0.88</span>
              <span className="text-[10px] font-bold text-emerald-700 mt-0.5 block">Kategori Sangat Tinggi</span>
            </div>

            <div className="bg-white border border-slate-200/90 rounded-xl p-4 shadow-xs border-l-4 border-l-rose-700">
              <span className="text-[10px] font-bold text-slate-500 uppercase">Peserta Remedial</span>
              <span className="text-2xl font-black text-rose-700 block mt-1">4 Siswa</span>
              <span className="text-[10px] font-bold text-rose-700 mt-0.5 block">Di Bawah KKTP 75</span>
            </div>
          </div>

          {/* Section 1: Item Analysis Table (BE-CBT-08) */}
          <div className="bg-white border border-slate-200/90 rounded-xl overflow-hidden shadow-xs">
            <div className="p-4 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
              <div>
                <h3 className="font-extrabold text-sm text-slate-900">
                  Analisis Parameter Butir Soal (Tingkat Kesukaran &amp; Daya Pembeda)
                </h3>
                <p className="text-xs text-slate-500">
                  Evaluasi statistik psikometri untuk mengidentifikasi butir soal berkualitas tinggi atau distraktor cacat
                </p>
              </div>
              <button
                type="button"
                onClick={() => toast.success('Data analisis butir soal berhasil diekspor ke format Excel!')}
                className="px-3.5 py-1.5 rounded-lg text-xs font-bold border border-slate-300 hover:bg-slate-100 text-slate-700 shadow-xs cursor-pointer"
              >
                Ekspor Analisis XLSX
              </button>
            </div>

            <div className="overflow-x-auto">
              <table className="w-full text-left text-xs border-collapse">
                <thead>
                  <tr className="bg-slate-100 border-b border-slate-300 text-slate-800 font-extrabold uppercase text-[10px] tracking-wider">
                    <th className="py-3 px-3 w-14 text-center">No</th>
                    <th className="py-3 px-3 min-w-64">Indikator Capaian Pembelajaran (CP)</th>
                    <th className="py-3 px-3 w-32 text-center">Tingkat Kesukaran</th>
                    <th className="py-3 px-3 w-32 text-center">Daya Pembeda</th>
                    <th className="py-3 px-3 w-36 text-center">Efektivitas Pengecoh</th>
                    <th className="py-3 px-3 w-32 text-center">Rekomendasi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-200">
                  <tr className="hover:bg-slate-50">
                    <td className="py-2.5 px-3 text-center font-bold text-slate-700">Soal 01</td>
                    <td className="py-2.5 px-3 font-medium text-slate-900">
                      Memahami semantik elemen HTML5 &lt;header&gt;, &lt;nav&gt;, dan &lt;section&gt;
                    </td>
                    <td className="py-2.5 px-3 text-center">
                      <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-800 text-white shadow-xs">
                        Sedang (p=0.68)
                      </span>
                    </td>
                    <td className="py-2.5 px-3 text-center font-bold text-emerald-800">
                      0.42 (Sangat Baik)
                    </td>
                    <td className="py-2.5 px-3 text-center text-slate-700 font-medium">
                      Berfungsi Optimal (4/4)
                    </td>
                    <td className="py-2.5 px-3 text-center">
                      <span className="px-2 py-0.5 rounded text-[10px] font-black bg-blue-900 text-white shadow-xs">
                        DITERIMA
                      </span>
                    </td>
                  </tr>

                  <tr className="hover:bg-slate-50">
                    <td className="py-2.5 px-3 text-center font-bold text-slate-700">Soal 02</td>
                    <td className="py-2.5 px-3 font-medium text-slate-900">
                      Menganalisis perbedaan property flexbox justify-content dan align-items
                    </td>
                    <td className="py-2.5 px-3 text-center">
                      <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-600 text-white shadow-xs">
                        Sukar (p=0.34)
                      </span>
                    </td>
                    <td className="py-2.5 px-3 text-center font-bold text-emerald-800">
                      0.38 (Baik)
                    </td>
                    <td className="py-2.5 px-3 text-center text-slate-700 font-medium">
                      Opsi C Jarang Dipilih
                    </td>
                    <td className="py-2.5 px-3 text-center">
                      <span className="px-2 py-0.5 rounded text-[10px] font-black bg-blue-900 text-white shadow-xs">
                        DITERIMA
                      </span>
                    </td>
                  </tr>

                  <tr className="hover:bg-slate-50">
                    <td className="py-2.5 px-3 text-center font-bold text-slate-700">Soal 03</td>
                    <td className="py-2.5 px-3 font-medium text-slate-900">
                      Menghubungkan stylesheet eksternal CSS dengan tag &lt;link&gt;
                    </td>
                    <td className="py-2.5 px-3 text-center">
                      <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-teal-800 text-white shadow-xs">
                        Mudah (p=0.89)
                      </span>
                    </td>
                    <td className="py-2.5 px-3 text-center font-bold text-amber-700">
                      0.22 (Cukup)
                    </td>
                    <td className="py-2.5 px-3 text-center text-slate-700 font-medium">
                      Distraktor Opsi D Lemah
                    </td>
                    <td className="py-2.5 px-3 text-center">
                      <span className="px-2 py-0.5 rounded text-[10px] font-black bg-amber-600 text-white shadow-xs">
                        REVISI OPSI
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          {/* Section 2: Remedial Scheduling (BE-CBT-09) */}
          <div className="bg-white border border-slate-300 rounded-xl p-6 shadow-xs space-y-4">
            <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-4 border-b border-slate-200 pb-4">
              <div>
                <div className="flex items-center gap-2 mb-1.5">
                  <span className="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider bg-rose-800 text-white">
                    PENJADWALAN REMEDIAL OTOMATIS
                  </span>
                  <span className="text-xs font-bold text-slate-500">
                    Siswa dengan Capaian &lt; 75 (Ambang KKTP)
                  </span>
                </div>
                <h3 className="text-base font-black text-slate-900">
                  Daftar Peserta &amp; Paket Soal Remedial (Kurikulum Merdeka)
                </h3>
                <p className="text-xs text-slate-600">
                  Siswa yang belum mencapai kriteria ketercapaian otomatis terdaftar dalam gelombang remedial dengan set butir soal teracak baru.
                </p>
              </div>

              <div className="flex items-center gap-2">
                <button
                  type="button"
                  onClick={() => {
                    toast.success('Paket Ujian Remedial B berhasil digenerate dengan 20 butir soal setara!');
                  }}
                  className="px-3.5 py-2 rounded-lg text-xs font-bold border border-slate-300 hover:bg-slate-100 text-slate-800 shadow-xs cursor-pointer flex items-center gap-1.5"
                >
                  <RefreshCcw className="w-3.5 h-3.5" />
                  <span>Generate Paket B</span>
                </button>

                <button
                  type="button"
                  onClick={() => {
                    toast.success('Sesi Ujian Remedial berhasil dijadwalkan: Jumat, 17 Oktober 2026 di Lab Komputer 1!');
                  }}
                  className="px-4 py-2 rounded-lg text-xs font-black bg-rose-800 hover:bg-rose-900 text-white shadow-xs cursor-pointer flex items-center gap-1.5"
                >
                  <ArrowRight className="w-3.5 h-3.5" />
                  <span>Jadwalkan Sesi Remedial</span>
                </button>
              </div>
            </div>

            {/* Remedial Students Table */}
            <div className="border border-slate-200 rounded-lg overflow-hidden">
              <table className="w-full text-left text-xs border-collapse">
                <thead>
                  <tr className="bg-slate-100 border-b border-slate-200 text-slate-800 font-extrabold uppercase text-[10px]">
                    <th className="py-2.5 px-3 w-12 text-center">No</th>
                    <th className="py-2.5 px-3 w-28">NIS</th>
                    <th className="py-2.5 px-3 min-w-48">Nama Siswa</th>
                    <th className="py-2.5 px-3 w-28 text-center">Skor Awal</th>
                    <th className="py-2.5 px-3 w-28 text-center">Selisih KKTP</th>
                    <th className="py-2.5 px-3 min-w-56">Materi Kompetensi yang Belum Tuntas</th>
                    <th className="py-2.5 px-3 w-36 text-center">Status Remedial</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-200">
                  <tr className="hover:bg-slate-50">
                    <td className="py-2 px-3 text-center font-bold text-slate-500">1</td>
                    <td className="py-2 px-3 font-mono font-semibold text-slate-700">240903</td>
                    <td className="py-2 px-3 font-bold text-slate-900">Rizky Pratama Wijaya</td>
                    <td className="py-2 px-3 text-center font-black text-rose-800">62.0</td>
                    <td className="py-2 px-3 text-center font-bold text-rose-700">-13.0</td>
                    <td className="py-2 px-3 text-slate-700">Flexbox alignment &amp; CSS Grid positioning</td>
                    <td className="py-2 px-3 text-center">
                      <span className="px-2 py-0.5 rounded text-[10px] font-black bg-amber-600 text-white shadow-xs">
                        SIAP REMEDIAL
                      </span>
                    </td>
                  </tr>

                  <tr className="hover:bg-slate-50">
                    <td className="py-2 px-3 text-center font-bold text-slate-500">2</td>
                    <td className="py-2 px-3 font-mono font-semibold text-slate-700">240907</td>
                    <td className="py-2 px-3 font-bold text-slate-900">Dimas Anggara Putra</td>
                    <td className="py-2 px-3 text-center font-black text-rose-800">64.5</td>
                    <td className="py-2 px-3 text-center font-bold text-rose-700">-10.5</td>
                    <td className="py-2 px-3 text-slate-700">Form validasi &amp; HTTP method GET vs POST</td>
                    <td className="py-2 px-3 text-center">
                      <span className="px-2 py-0.5 rounded text-[10px] font-black bg-amber-600 text-white shadow-xs">
                        SIAP REMEDIAL
                      </span>
                    </td>
                  </tr>

                  <tr className="hover:bg-slate-50">
                    <td className="py-2 px-3 text-center font-bold text-slate-500">3</td>
                    <td className="py-2 px-3 font-mono font-semibold text-slate-700">240911</td>
                    <td className="py-2 px-3 font-bold text-slate-900">Hendra Kurniawan</td>
                    <td className="py-2 px-3 text-center font-black text-rose-800">70.0</td>
                    <td className="py-2 px-3 text-center font-bold text-rose-700">-5.0</td>
                    <td className="py-2 px-3 text-slate-700">Struktur semantik HTML5 &amp; image responsive</td>
                    <td className="py-2 px-3 text-center">
                      <span className="px-2 py-0.5 rounded text-[10px] font-black bg-amber-600 text-white shadow-xs">
                        SIAP REMEDIAL
                      </span>
                    </td>
                  </tr>

                  <tr className="hover:bg-slate-50">
                    <td className="py-2 px-3 text-center font-bold text-slate-500">4</td>
                    <td className="py-2 px-3 font-mono font-semibold text-slate-700">240919</td>
                    <td className="py-2 px-3 font-bold text-slate-900">Putra Pratama</td>
                    <td className="py-2 px-3 text-center font-black text-rose-800">72.0</td>
                    <td className="py-2 px-3 text-center font-bold text-rose-700">-3.0</td>
                    <td className="py-2 px-3 text-slate-700">Koreksi sintaks tabel HTML dan link anchor</td>
                    <td className="py-2 px-3 text-center">
                      <span className="px-2 py-0.5 rounded text-[10px] font-black bg-amber-600 text-white shadow-xs">
                        SIAP REMEDIAL
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      {/* ─── MODAL BERITA ACARA UJIAN (BE-CBT-10 & ADM-CBT-08) ─── */}
      {isBeritaAcaraOpen && (
        <div className="fixed inset-0 z-50 bg-slate-950/70 flex items-center justify-center p-4 overflow-y-auto">
          <div className="bg-white rounded-2xl max-w-2xl w-full p-8 shadow-2xl space-y-6 relative border-2 border-slate-300">
            {/* Close Button */}
            <button
              type="button"
              onClick={() => setIsBeritaAcaraOpen(false)}
              className="absolute top-5 right-5 p-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 cursor-pointer"
            >
              <X className="w-5 h-5" />
            </button>

            {/* Header Kop Berita Acara */}
            <div className="border-b-2 border-slate-900 pb-4 text-center space-y-1">
              <span className="text-[11px] font-black tracking-widest uppercase text-slate-500">
                BERITA ACARA PELAKSANAAN UJIAN CBT DIGITAL
              </span>
              <h2 className="text-xl font-black text-slate-900 tracking-tight">
                SMP LABORATORIUM UNIVERSITAS NEGERI MALANG
              </h2>
              <p className="text-xs text-slate-600 font-medium">
                Tahun Ajaran 2026/2027 • Semester Ganjil
              </p>
              <div className="pt-2">
                <span className="inline-block px-3 py-1 bg-slate-900 text-white text-xs font-black tracking-wider uppercase rounded">
                  NO. BA: BA-CBT/2026/10/IXA-001
                </span>
              </div>
            </div>

            {/* Exam Meta */}
            <div className="grid grid-cols-2 gap-3 text-xs bg-slate-50 p-4 rounded-xl border border-slate-200">
              <div className="space-y-1">
                <p><span className="text-slate-500 font-bold w-28 inline-block">Mata Pelajaran:</span> <strong className="text-slate-900">Informatika &amp; Desain Web</strong></p>
                <p><span className="text-slate-500 font-bold w-28 inline-block">Kelas / Rombel:</span> <strong className="text-slate-900">Kelas IX-A</strong></p>
                <p><span className="text-slate-500 font-bold w-28 inline-block">Ruang Ujian:</span> <strong className="text-blue-900">Lab Komputer 1 (Lantai 2)</strong></p>
              </div>
              <div className="space-y-1">
                <p><span className="text-slate-500 font-bold w-28 inline-block">Hari, Tanggal:</span> <strong className="text-slate-900">Rabu, 14 Oktober 2026</strong></p>
                <p><span className="text-slate-500 font-bold w-28 inline-block">Waktu Sesi:</span> <strong className="text-slate-900">07:30 - 09:00 WIB (90 Menit)</strong></p>
                <p><span className="text-slate-500 font-bold w-28 inline-block">Guru Pengawas:</span> <strong className="text-slate-900">Budi Santoso, S.Kom.</strong></p>
              </div>
            </div>

            {/* Attendance & Incidents */}
            <div className="space-y-3 text-xs">
              <div className="grid grid-cols-3 gap-3 text-center">
                <div className="p-3 bg-slate-100 rounded-lg border border-slate-200">
                  <span className="text-[10px] uppercase font-bold text-slate-500 block">Peserta Terdaftar</span>
                  <span className="text-xl font-black text-slate-900 mt-0.5 block">32 Siswa</span>
                </div>
                <div className="p-3 bg-emerald-50 rounded-lg border border-emerald-300">
                  <span className="text-[10px] uppercase font-bold text-emerald-800 block">Hadir Mengikuti</span>
                  <span className="text-xl font-black text-emerald-800 mt-0.5 block">32 Siswa</span>
                </div>
                <div className="p-3 bg-slate-100 rounded-lg border border-slate-200">
                  <span className="text-[10px] uppercase font-bold text-slate-500 block">Tidak Hadir / Alpa</span>
                  <span className="text-xl font-black text-slate-700 mt-0.5 block">0 Siswa</span>
                </div>
              </div>

              {/* Incidents Log */}
              <div className="p-3 bg-slate-50 border border-slate-200 rounded-lg space-y-1">
                <span className="font-bold text-slate-900 uppercase text-[10px] block">Catatan Khusus / Kejadian Ujian:</span>
                <p className="text-slate-700 text-[11px] leading-relaxed">
                  1. Workstation PC-07 (Dimas Anggara Putra / NIS 240907) mengalami gangguan koneksi jaringan lokal selama 2 menit. Telah dilakukan reset sesi oleh pengawas dan diberikan kompensasi waktu pengerjaan 2 menit.
                </p>
                <p className="text-slate-700 text-[11px] leading-relaxed">
                  2. Seluruh 32 siswa berhasil menyelesaikan dan melakukan submit jawaban ke server CBT dengan tertib.
                </p>
              </div>
            </div>

            {/* Signatures */}
            <div className="pt-4 border-t border-slate-300 flex items-center justify-between text-xs">
              <div>
                <span className="text-[10px] font-mono text-slate-400 block">SISTLABUM SECURE CBT LOG</span>
                <span className="text-[10px] font-bold text-emerald-800 block">✓ TERVALIDASI SERVER WAKTU OTENTIK</span>
              </div>
              <div className="text-right">
                <p className="text-slate-500">Malang, 14 Oktober 2026</p>
                <p className="font-bold text-slate-900 mt-1">Guru Pengawas Ruang</p>
                <div className="h-8"></div>
                <p className="font-black text-slate-900 underline">Budi Santoso, S.Kom.</p>
                <p className="text-[10px] text-slate-500 font-mono">NIP. 198803152015041001</p>
              </div>
            </div>

            {/* Actions */}
            <div className="flex items-center justify-end gap-3 pt-3 border-t border-slate-200">
              <button
                type="button"
                onClick={() => setIsBeritaAcaraOpen(false)}
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
                <span>Cetak Berita Acara PDF</span>
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
