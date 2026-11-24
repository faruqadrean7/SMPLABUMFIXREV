'use client';

import React, { useEffect, useState } from 'react';
import Link from 'next/link';
import {
  CalendarCheck,
  Plus,
  RefreshCw,
  Search,
  Key,
  Users,
  Award,
  ArrowLeft,
  CheckCircle2,
  AlertTriangle,
  Play,
  Square,
  Sparkles,
  BookOpen,
  Send,
  X,
  Clock,
  Layers,
  ChevronRight,
  Loader2,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { cbtApi } from '@/features/cbt/cbt-api';
import { subjectsApi } from '@/features/master/subjects-api';
import { classesApi } from '@/features/master/classes-api';
import { academicApi } from '@/features/academic/academic-api';
import { ExamItem, ExamType, ExamStatus, ExamResultItem, EssaySubmissionItem, ExamRoomItem } from '@/types/cbt';
import { SubjectItem, SchoolClassItem } from '@/types/master';
import { GradeComponentItem, GradePeriodItem } from '@/types/academic';

export default function ExamsAdminPage() {
  const [exams, setExams] = useState<ExamItem[]>([]);
  const [subjects, setSubjects] = useState<SubjectItem[]>([]);
  const [classes, setClasses] = useState<SchoolClassItem[]>([]);
  const [isLoading, setIsLoading] = useState<boolean>(true);
  const [searchQuery, setSearchQuery] = useState<string>('');
  const [selectedCategory, setSelectedCategory] = useState<string>('ALL');

  // Modals
  const [isCreateModalOpen, setIsCreateModalOpen] = useState<boolean>(false);
  const [isRoomsModalOpen, setIsRoomsModalOpen] = useState<boolean>(false);
  const [isEnrollModalOpen, setIsEnrollModalOpen] = useState<boolean>(false);
  const [isResultsModalOpen, setIsResultsModalOpen] = useState<boolean>(false);

  const [activeExam, setActiveExam] = useState<ExamItem | null>(null);
  const [examResults, setExamResults] = useState<ExamResultItem[]>([]);
  const [essaySubmissions, setEssaySubmissions] = useState<EssaySubmissionItem[]>([]);
  const [essayScoreInputs, setEssayScoreInputs] = useState<Record<string, string>>({});
  const [gradingAnswerId, setGradingAnswerId] = useState<string | null>(null);
  const [resultsTab, setResultsTab] = useState<'rekap' | 'esai'>('rekap');
  const [isLoadingResults, setIsLoadingResults] = useState<boolean>(false);

  // Gradebook Publish Form State
  const [gradeComponents, setGradeComponents] = useState<GradeComponentItem[]>([]);
  const [gradePeriods, setGradePeriods] = useState<GradePeriodItem[]>([]);
  const [selectedComponentId, setSelectedComponentId] = useState<string>('');
  const [selectedPeriodId, setSelectedPeriodId] = useState<string>('');
  const [isPublishingGradebook, setIsPublishingGradebook] = useState<boolean>(false);
  const [isCreatingExam, setIsCreatingExam] = useState<boolean>(false);
  const [isEnrolling, setIsEnrolling] = useState<boolean>(false);
  const [isRunningAutoGrade, setIsRunningAutoGrade] = useState<boolean>(false);
  const [isCreatingRemedial, setIsCreatingRemedial] = useState<boolean>(false);
  const [togglingExamId, setTogglingExamId] = useState<string | null>(null);
  const [regeneratingRoomId, setRegeneratingRoomId] = useState<string | null>(null);

  // Create Form State
  const [examForm, setExamForm] = useState({
    title: '',
    subject_id: '',
    exam_type: 'harian' as ExamType,
    duration_min: 60,
    start_at: '',
    end_at: '',
    passing_grade: 75.0,
    shuffle_questions: false,
    shuffle_options: false,
  });

  // Enroll Class Selection
  const [selectedClassId, setSelectedClassId] = useState<string>('');

  const [feedbackMsg, setFeedbackMsg] = useState<{ type: 'success' | 'error'; text: string } | null>(null);

  const fetchExams = async () => {
    setIsLoading(true);
    try {
      const res = await cbtApi.getExams({
        exam_type: selectedCategory !== 'ALL' ? selectedCategory : undefined,
        search: searchQuery || undefined,
        per_page: 50,
      });
      setExams(res.data || []);
    } catch (err: any) {
      setFeedbackMsg({ type: 'error', text: err?.message || 'Gagal memuat jadwal ujian.' });
    } finally {
      setIsLoading(false);
    }
  };

  const fetchMasterData = async () => {
    try {
      const [subjRes, clsRes, periodsRes] = await Promise.all([
        subjectsApi.list({ limit: 100 }),
        classesApi.list({ limit: 100 }),
        academicApi.listGradePeriods(),
      ]);
      setSubjects(subjRes.data || []);
      setClasses(clsRes.data || []);
      setGradePeriods(periodsRes || []);
    } catch (err: any) {
      console.error('Error fetching master data:', err);
    }
  };

  useEffect(() => {
    fetchMasterData();
  }, []);

  useEffect(() => {
    fetchExams();
  }, [selectedCategory, searchQuery]);

  const handleCreateExam = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!examForm.title || !examForm.subject_id || !examForm.start_at || !examForm.end_at) {
      setFeedbackMsg({ type: 'error', text: 'Judul, Mapel, Waktu Mulai, dan Selesai wajib diisi.' });
      return;
    }

    setIsCreatingExam(true);
    try {
      await cbtApi.createExam({
        title: examForm.title,
        subject_id: examForm.subject_id,
        exam_type: examForm.exam_type,
        duration_min: Number(examForm.duration_min),
        start_at: examForm.start_at,
        end_at: examForm.end_at,
        passing_grade: Number(examForm.passing_grade),
        shuffle_questions: examForm.shuffle_questions,
        shuffle_options: examForm.shuffle_options,
      });
      setFeedbackMsg({ type: 'success', text: 'Jadwal asesmen CBT berhasil dibuat!' });
      setIsCreateModalOpen(false);
      fetchExams();
    } catch (err: any) {
      setFeedbackMsg({ type: 'error', text: err?.message || 'Gagal membuat jadwal ujian.' });
    } finally {
      setIsCreatingExam(false);
    }
  };

  const handleToggleStatus = async (exam: ExamItem) => {
    const nextStatus = exam.status === 'active' || exam.status === 'berlangsung' ? 'selesai' : 'berlangsung';
    setTogglingExamId(exam.id);
    try {
      await cbtApi.updateExamStatus(exam.id, nextStatus);
      setFeedbackMsg({ type: 'success', text: `Status ujian diubah menjadi ${nextStatus}.` });
      fetchExams();
    } catch (err: any) {
      setFeedbackMsg({ type: 'error', text: err?.message || 'Gagal memperbarui status ujian.' });
    } finally {
      setTogglingExamId(null);
    }
  };

  const handleOpenRooms = async (exam: ExamItem) => {
    setActiveExam(exam);
    setIsRoomsModalOpen(true);
    try {
      const detail = await cbtApi.getExam(exam.id);
      setActiveExam(detail.data);
    } catch {
      // Gunakan data ringkas jika gagal memuat detail
    }
  };

  const handleRegenerateToken = async (roomId: string) => {
    setRegeneratingRoomId(roomId);
    try {
      const res = await cbtApi.regenerateExamRoomToken(roomId);
      setFeedbackMsg({ type: 'success', text: `Token berhasil diperbarui: ${res.data.token}` });
      if (activeExam) {
        const updated = await cbtApi.getExam(activeExam.id);
        setActiveExam(updated.data);
      }
      fetchExams();
    } catch (err: any) {
      setFeedbackMsg({ type: 'error', text: err?.message || 'Gagal memperbarui token.' });
    } finally {
      setRegeneratingRoomId(null);
    }
  };

  const handleOpenEnroll = (exam: ExamItem) => {
    setActiveExam(exam);
    setSelectedClassId('');
    setIsEnrollModalOpen(true);
  };

  const handleEnrollParticipants = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!activeExam || !selectedClassId) return;

    setIsEnrolling(true);
    try {
      const firstRoomId = activeExam.rooms && activeExam.rooms.length > 0 ? activeExam.rooms[0].id : undefined;
      await cbtApi.enrollExamParticipants(activeExam.id, {
        exam_room_id: firstRoomId,
        class_id: selectedClassId,
        class_ids: [selectedClassId],
      });
      setFeedbackMsg({ type: 'success', text: 'Peserta rombel berhasil didaftarkan ke ujian.' });
      setIsEnrollModalOpen(false);
      fetchExams();
    } catch (err: any) {
      setFeedbackMsg({ type: 'error', text: err?.message || 'Gagal mendaftarkan peserta.' });
    } finally {
      setIsEnrolling(false);
    }
  };

  const extractResultsArray = (payload: any): ExamResultItem[] => {
    if (Array.isArray(payload)) return payload;
    if (payload && Array.isArray(payload.results)) return payload.results;
    return [];
  };

  const handleOpenResults = async (exam: ExamItem) => {
    setActiveExam(exam);
    setResultsTab('rekap');
    setIsResultsModalOpen(true);
    setIsLoadingResults(true);

    try {
      const [resultsRes, essaysRes, compRes] = await Promise.all([
        cbtApi.getExamResults(exam.id),
        cbtApi.getEssaySubmissions(exam.id).catch(() => null),
        academicApi.listGradeComponents({ subject_id: exam.subject_id }),
      ]);
      setExamResults(extractResultsArray(resultsRes.data));
      const subs = essaysRes?.data?.submissions || [];
      setEssaySubmissions(subs);
      const initialScores: Record<string, string> = {};
      subs.forEach((s) => {
        if (s.current_score !== null && s.current_score !== undefined) {
          initialScores[s.answer_id] = String(s.current_score);
        }
      });
      setEssayScoreInputs(initialScores);
      setGradeComponents(compRes || []);
      if (compRes && compRes.length > 0) {
        setSelectedComponentId(compRes[0].id);
      }
      if (gradePeriods.length > 0) {
        const openPeriod = gradePeriods.find((p) => p.status === 'open');
        setSelectedPeriodId(openPeriod ? openPeriod.id : gradePeriods[0].id);
      }
    } catch (err: any) {
      setFeedbackMsg({ type: 'error', text: err?.message || 'Gagal memuat hasil ujian.' });
    } finally {
      setIsLoadingResults(false);
    }
  };

  const handleGradeEssay = async (sub: EssaySubmissionItem) => {
    if (!activeExam) return;
    const rawVal = essayScoreInputs[sub.answer_id];
    const scoreNum = parseFloat(rawVal);
    if (Number.isNaN(scoreNum) || scoreNum < 0 || scoreNum > sub.max_score) {
      setFeedbackMsg({ type: 'error', text: `Skor esai harus berada di antara 0 dan ${sub.max_score}.` });
      return;
    }

    setGradingAnswerId(sub.answer_id);
    try {
      await cbtApi.gradeEssay(activeExam.id, { answer_id: sub.answer_id, score: scoreNum });
      setFeedbackMsg({ type: 'success', text: `Nilai esai ${sub.student_name} berhasil disimpan (${scoreNum}/${sub.max_score}).` });
      const [resultsRes, essaysRes] = await Promise.all([
        cbtApi.getExamResults(activeExam.id),
        cbtApi.getEssaySubmissions(activeExam.id).catch(() => null),
      ]);
      setExamResults(extractResultsArray(resultsRes.data));
      if (essaysRes?.data?.submissions) {
        setEssaySubmissions(essaysRes.data.submissions);
      }
    } catch (err: any) {
      setFeedbackMsg({ type: 'error', text: err?.message || 'Gagal menyimpan nilai esai.' });
    } finally {
      setGradingAnswerId(null);
    }
  };

  const handleRunAutoGrade = async () => {
    if (!activeExam) return;
    setIsRunningAutoGrade(true);
    try {
      await cbtApi.runAutoGrade(activeExam.id);
      setFeedbackMsg({ type: 'success', text: 'Koreksi otomatis soal objektif berhasil dijalankan!' });
      const res = await cbtApi.getExamResults(activeExam.id);
      setExamResults(extractResultsArray(res.data));
    } catch (err: any) {
      setFeedbackMsg({ type: 'error', text: err?.message || 'Gagal menjalankan koreksi otomatis.' });
    } finally {
      setIsRunningAutoGrade(false);
    }
  };

  const handleCreateRemedial = async () => {
    if (!activeExam) return;
    setIsCreatingRemedial(true);
    try {
      const res = await cbtApi.createRemedialExam(activeExam.id, {
        start_at: new Date(Date.now() + 86400000).toISOString().slice(0, 16),
        end_at: new Date(Date.now() + 86400000 + 7200000).toISOString().slice(0, 16),
        duration_min: activeExam.duration_min,
      });
      setFeedbackMsg({ type: 'success', text: `Ujian Remedial berhasil dibuat: ${res.data.title}` });
      setIsResultsModalOpen(false);
      fetchExams();
    } catch (err: any) {
      setFeedbackMsg({ type: 'error', text: err?.message || 'Gagal membuat ujian remedial.' });
    } finally {
      setIsCreatingRemedial(false);
    }
  };

  const handlePublishGradebook = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!activeExam || !selectedComponentId || !selectedPeriodId) {
      setFeedbackMsg({ type: 'error', text: 'Komponen nilai dan periode penilaian wajib dipilih.' });
      return;
    }

    setIsPublishingGradebook(true);
    try {
      const res = await cbtApi.publishToGradebook(activeExam.id, {
        component_id: selectedComponentId,
        period_id: selectedPeriodId,
      });
      setFeedbackMsg({
        type: 'success',
        text: res.data.message || 'Nilai ujian CBT berhasil disinkronkan ke Buku Nilai (Gradebook)!',
      });
      setIsResultsModalOpen(false);
    } catch (err: any) {
      setFeedbackMsg({ type: 'error', text: err?.message || 'Gagal mempublikasikan ke Buku Nilai.' });
    } finally {
      setIsPublishingGradebook(false);
    }
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <div className="flex items-center gap-2 mb-1">
            <Link href="/admin/cbt" className="text-slate-500 hover:text-slate-800 text-xs flex items-center gap-1">
              <ArrowLeft className="h-3 w-3" /> Kembali ke CBT Hub
            </Link>
          </div>
          <h1 className="text-xl font-bold tracking-tight text-slate-900">
            Jadwal & Paket Ujian 📋
          </h1>
          <p className="text-xs text-slate-500 mt-0.5">
            Kendali asesmen: Ulangan Harian, Formatif, Sumatif, UTS/UAS, distribusi token lab, dan integrasi Gradebook.
          </p>
        </div>

        <div className="flex items-center gap-2.5">
          <Link href="/admin/cbt/monitor">
            <Button size="sm" variant="outline" className="text-xs gap-1.5 text-emerald-700 border-emerald-300">
              <Play className="h-3.5 w-3.5 text-emerald-600" />
              Live Monitor Lab
            </Button>
          </Link>
          <Button
            size="sm"
            onClick={() => setIsCreateModalOpen(true)}
            className="bg-blue-600 hover:bg-blue-500 text-white text-xs gap-1.5"
          >
            <Plus className="h-4 w-4" />
            + Jadwalkan Ujian Baru
          </Button>
        </div>
      </div>

      {feedbackMsg && (
        <div
          className={`p-3 rounded-lg text-xs flex items-center justify-between ${
            feedbackMsg.type === 'success'
              ? 'bg-emerald-50 text-emerald-800 border border-emerald-200'
              : 'bg-rose-50 text-rose-800 border border-rose-200'
          }`}
        >
          <span>{feedbackMsg.text}</span>
          <button onClick={() => setFeedbackMsg(null)} className="text-slate-400 hover:text-slate-600">
            <X className="h-3.5 w-3.5" />
          </button>
        </div>
      )}

      {/* Filter Tabs & Search */}
      <div className="flex flex-col md:flex-row items-center justify-between gap-4">
        <div className="flex items-center gap-1 overflow-x-auto w-full md:w-auto pb-1 md:pb-0">
          {[
            { id: 'ALL', label: 'Semua Asesmen' },
            { id: 'harian', label: 'Ulangan Harian' },
            { id: 'formatif', label: 'Formatif' },
            { id: 'sumatif', label: 'Sumatif' },
            { id: 'uts', label: 'UTS / ASTS' },
            { id: 'uas', label: 'UAS / ASAS' },
            { id: 'remedial', label: 'Remedial' },
            { id: 'asat', label: 'ASAT' },
          ].map((cat) => (
            <button
              key={cat.id}
              onClick={() => setSelectedCategory(cat.id)}
              className={`px-3 py-1.5 rounded-lg text-xs font-medium whitespace-nowrap transition-colors ${
                selectedCategory === cat.id
                  ? 'bg-slate-900 text-white shadow-sm'
                  : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200'
              }`}
            >
              {cat.label}
            </button>
          ))}
        </div>

        <div className="relative w-full md:w-64">
          <Search className="absolute left-2.5 top-2.5 h-3.5 w-3.5 text-slate-400" />
          <Input
            placeholder="Cari judul asesmen..."
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            className="pl-8 text-xs h-8"
          />
        </div>
      </div>

      {/* Exams Table */}
      <Card>
        <CardContent className="p-0">
          {isLoading ? (
            <div className="py-12 text-center text-xs text-slate-400">Memuat data jadwal ujian...</div>
          ) : exams.length === 0 ? (
            <div className="py-12 text-center text-xs text-slate-500">
              Tidak ada jadwal ujian ditemukan.{' '}
              <button onClick={() => setIsCreateModalOpen(true)} className="text-blue-600 hover:underline">
                Buat jadwal baru.
              </button>
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-left text-xs border-collapse">
                <thead>
                  <tr className="border-b border-slate-200 bg-slate-50/75 text-slate-500 font-semibold uppercase tracking-wider">
                    <th className="py-3 px-4">Judul Asesmen & Mapel</th>
                    <th className="py-3 px-4 text-center">Kategori</th>
                    <th className="py-3 px-4 text-center">Jadwal & Waktu</th>
                    <th className="py-3 px-4 text-center">Durasi</th>
                    <th className="py-3 px-4 text-center">Token Lab</th>
                    <th className="py-3 px-4 text-center">Status</th>
                    <th className="py-3 px-4 text-right">Aksi & Kontrol</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {exams.map((exam) => {
                    const isLive = exam.status === 'active' || exam.status === 'berlangsung';
                    const firstRoom = exam.rooms && exam.rooms.length > 0 ? exam.rooms[0] : null;

                    return (
                      <tr key={exam.id} className="hover:bg-slate-50/80 transition-colors">
                        <td className="py-3.5 px-4">
                          <div className="font-bold text-slate-900">{exam.title}</div>
                          <div className="text-[11px] text-slate-500 mt-0.5">
                            {exam.subject?.name || 'Mata Pelajaran'} • KKTP: {exam.passing_grade ?? 75}
                          </div>
                        </td>
                        <td className="py-3.5 px-4 text-center">
                          <Badge variant="outline" className="capitalize text-[11px] font-semibold bg-slate-50">
                            {exam.exam_type}
                          </Badge>
                        </td>
                        <td className="py-3.5 px-4 text-center text-slate-600">
                          <div>
                            {new Date(exam.start_at).toLocaleDateString('id-ID', {
                              day: 'numeric',
                              month: 'short',
                              year: 'numeric',
                            })}
                          </div>
                          <div className="text-[10px] text-slate-400">
                            {new Date(exam.start_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })} -{' '}
                            {new Date(exam.end_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })} WIB
                          </div>
                        </td>
                        <td className="py-3.5 px-4 text-center font-medium text-slate-700">
                          {exam.duration_min} Menit
                        </td>
                        <td className="py-3.5 px-4 text-center">
                          {firstRoom ? (
                            <span className="font-mono font-bold text-blue-600 bg-blue-50 px-2 py-0.5 rounded border border-blue-200 text-xs">
                              {firstRoom.token}
                            </span>
                          ) : (
                            <span className="text-[11px] text-slate-400">Belum diplot</span>
                          )}
                        </td>
                        <td className="py-3.5 px-4 text-center">
                          <Badge
                            className={`text-[10px] font-semibold ${
                              isLive
                                ? 'bg-emerald-100 text-emerald-800 border-emerald-300'
                                : exam.status === 'selesai'
                                ? 'bg-slate-100 text-slate-700'
                                : 'bg-amber-100 text-amber-800 border-amber-300'
                            }`}
                          >
                            {exam.status}
                          </Badge>
                        </td>
                        <td className="py-3.5 px-4 text-right">
                          <div className="flex items-center justify-end gap-1.5">
                            <Button
                              size="sm"
                              variant="ghost"
                              disabled={togglingExamId === exam.id}
                              onClick={() => handleToggleStatus(exam)}
                              title={isLive ? 'Tutup / Selesaikan Ujian' : 'Aktifkan Ujian'}
                              className={`h-7 px-2 text-xs ${
                                isLive ? 'text-amber-600 hover:text-amber-700' : 'text-emerald-600 hover:text-emerald-700'
                              }`}
                            >
                              {togglingExamId === exam.id ? (
                                <Loader2 className="h-3.5 w-3.5 mr-1 animate-spin" />
                              ) : isLive ? (
                                <Square className="h-3.5 w-3.5 mr-1" />
                              ) : (
                                <Play className="h-3.5 w-3.5 mr-1" />
                              )}
                              {isLive ? 'Tutup' : 'Buka'}
                            </Button>

                            <Button
                              size="sm"
                              variant="outline"
                              onClick={() => handleOpenRooms(exam)}
                              className="h-7 px-2 text-xs"
                            >
                              <Key className="h-3 w-3 mr-1" />
                              Token
                            </Button>

                            <Button
                              size="sm"
                              variant="outline"
                              onClick={() => handleOpenEnroll(exam)}
                              className="h-7 px-2 text-xs"
                            >
                              <Users className="h-3 w-3 mr-1" />
                              Peserta
                            </Button>

                            <Button
                              size="sm"
                              onClick={() => handleOpenResults(exam)}
                              className="h-7 px-2 text-xs bg-slate-900 hover:bg-slate-800 text-white"
                            >
                              <Award className="h-3 w-3 mr-1" />
                              Hasil & Nilai
                            </Button>
                          </div>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          )}
        </CardContent>
      </Card>

      {/* Modal: Buat Ujian Baru */}
      {isCreateModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 overflow-y-auto">
          <div className="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl space-y-4 my-8">
            <div className="flex items-center justify-between border-b pb-3">
              <h3 className="font-bold text-slate-900 text-sm">Jadwalkan Asesmen CBT Baru</h3>
              <button onClick={() => setIsCreateModalOpen(false)} className="text-slate-400 hover:text-slate-600">
                <X className="h-4 w-4" />
              </button>
            </div>
            <form onSubmit={handleCreateExam} className="space-y-3">
              <div>
                <label className="text-xs font-semibold text-slate-700">Judul Ujian / Asesmen *</label>
                <Input
                  value={examForm.title}
                  onChange={(e) => setExamForm({ ...examForm, title: e.target.value })}
                  placeholder="Contoh: ASTS Ganjil Informatika Kelas X"
                  className="text-xs mt-1"
                  required
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="text-xs font-semibold text-slate-700">Mata Pelajaran *</label>
                  <select
                    value={examForm.subject_id}
                    onChange={(e) => setExamForm({ ...examForm, subject_id: e.target.value })}
                    className="w-full text-xs rounded-md border border-slate-300 p-2 mt-1"
                    required
                  >
                    <option value="">Pilih Mapel</option>
                    {subjects.map((s) => (
                      <option key={s.id} value={s.id}>
                        {s.name}
                      </option>
                    ))}
                  </select>
                </div>
                <div>
                  <label className="text-xs font-semibold text-slate-700">Kategori Asesmen *</label>
                  <select
                    value={examForm.exam_type}
                    onChange={(e) => setExamForm({ ...examForm, exam_type: e.target.value as ExamType })}
                    className="w-full text-xs rounded-md border border-slate-300 p-2 mt-1"
                  >
                    <option value="harian">Ulangan Harian (UH)</option>
                    <option value="formatif">Formatif Bulanan</option>
                    <option value="sumatif">Sumatif Bab</option>
                    <option value="uts">UTS / ASTS Ganjil</option>
                    <option value="uas">UAS / ASAS Ganjil</option>
                    <option value="asat">ASAT Genap</option>
                  </select>
                </div>
              </div>

              <div className="grid grid-cols-3 gap-3">
                <div>
                  <label className="text-xs font-semibold text-slate-700">Durasi (Menit) *</label>
                  <Input
                    type="number"
                    min="10"
                    max="300"
                    value={examForm.duration_min}
                    onChange={(e) => setExamForm({ ...examForm, duration_min: parseInt(e.target.value, 10) || 60 })}
                    className="text-xs mt-1"
                    required
                  />
                </div>
                <div className="col-span-2">
                  <label className="text-xs font-semibold text-slate-700">KKTP / Passing Grade</label>
                  <Input
                    type="number"
                    min="0"
                    max="100"
                    value={examForm.passing_grade}
                    onChange={(e) => setExamForm({ ...examForm, passing_grade: parseFloat(e.target.value) || 75 })}
                    className="text-xs mt-1"
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="text-xs font-semibold text-slate-700">Waktu Mulai *</label>
                  <Input
                    type="datetime-local"
                    value={examForm.start_at}
                    onChange={(e) => setExamForm({ ...examForm, start_at: e.target.value })}
                    className="text-xs mt-1"
                    required
                  />
                </div>
                <div>
                  <label className="text-xs font-semibold text-slate-700">Waktu Selesai *</label>
                  <Input
                    type="datetime-local"
                    value={examForm.end_at}
                    onChange={(e) => setExamForm({ ...examForm, end_at: e.target.value })}
                    className="text-xs mt-1"
                    required
                  />
                </div>
              </div>

              <div className="space-y-2 pt-2 border-t">
                <label className="flex items-center gap-2 text-xs text-slate-700 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={examForm.shuffle_questions}
                    onChange={(e) => setExamForm({ ...examForm, shuffle_questions: e.target.checked })}
                    className="rounded text-blue-600"
                  />
                  <span>Acak urutan butir soal per siswa</span>
                </label>
                <label className="flex items-center gap-2 text-xs text-slate-700 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={examForm.shuffle_options}
                    onChange={(e) => setExamForm({ ...examForm, shuffle_options: e.target.checked })}
                    className="rounded text-blue-600"
                  />
                  <span>Acak urutan opsi jawaban PG</span>
                </label>
              </div>

              <div className="flex items-center justify-end gap-2 pt-3 border-t">
                <Button type="button" variant="outline" size="sm" onClick={() => setIsCreateModalOpen(false)} disabled={isCreatingExam} className="text-xs">
                  Batal
                </Button>
                <Button type="submit" size="sm" disabled={isCreatingExam} className="bg-blue-600 hover:bg-blue-500 text-white text-xs gap-1.5">
                  {isCreatingExam ? (
                    <>
                      <Loader2 className="h-3.5 w-3.5 animate-spin" />
                      Menyimpan...
                    </>
                  ) : (
                    'Simpan Jadwal'
                  )}
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Modal: Kelola Token Ruang Lab */}
      {isRoomsModalOpen && activeExam && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="w-full max-w-md rounded-xl bg-white p-6 shadow-xl space-y-4">
            <div className="flex items-center justify-between border-b pb-3">
              <div>
                <h3 className="font-bold text-slate-900 text-sm">Token Ruang & Pengawas</h3>
                <p className="text-[11px] text-slate-500">{activeExam.title}</p>
              </div>
              <button onClick={() => setIsRoomsModalOpen(false)} className="text-slate-400 hover:text-slate-600">
                <X className="h-4 w-4" />
              </button>
            </div>

            <div className="space-y-3">
              {activeExam.rooms && activeExam.rooms.length > 0 ? (
                activeExam.rooms.map((room) => (
                  <div key={room.id} className="p-3 rounded-lg border border-slate-200 bg-slate-50/50 flex items-center justify-between">
                    <div>
                      <div className="font-semibold text-xs text-slate-900">
                        {room.room?.name || 'Ruang Lab Komputer'}
                      </div>
                      <div className="text-[10px] text-slate-500">
                        Pengawas: {room.proctor_name || 'Proctor Standby'}
                      </div>
                    </div>
                    <div className="flex items-center gap-2">
                      <span className="font-mono font-bold text-sm text-blue-700 bg-white px-2.5 py-1 rounded border border-blue-200">
                        {room.token}
                      </span>
                      <Button
                        size="sm"
                        variant="outline"
                        disabled={regeneratingRoomId === room.id}
                        onClick={() => handleRegenerateToken(room.id)}
                        className="h-7 text-xs gap-1"
                        title="Acak dan perbarui token ruang"
                      >
                        {regeneratingRoomId === room.id && <Loader2 className="h-3 w-3 animate-spin" />}
                        Acak
                      </Button>
                    </div>
                  </div>
                ))
              ) : (
                <div className="py-6 text-center text-xs text-slate-500">
                  Ujian ini belum memiliki ruang lab yang diplotkan.
                </div>
              )}
            </div>

            <div className="flex justify-end pt-3 border-t">
              <Button size="sm" variant="outline" onClick={() => setIsRoomsModalOpen(false)} className="text-xs">
                Tutup
              </Button>
            </div>
          </div>
        </div>
      )}

      {/* Modal: Plotting Rombel Peserta */}
      {isEnrollModalOpen && activeExam && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="w-full max-w-md rounded-xl bg-white p-6 shadow-xl space-y-4">
            <div className="flex items-center justify-between border-b pb-3">
              <div>
                <h3 className="font-bold text-slate-900 text-sm">Daftarkan Peserta Ujian</h3>
                <p className="text-[11px] text-slate-500">{activeExam.title}</p>
              </div>
              <button onClick={() => setIsEnrollModalOpen(false)} className="text-slate-400 hover:text-slate-600">
                <X className="h-4 w-4" />
              </button>
            </div>

            <form onSubmit={handleEnrollParticipants} className="space-y-4">
              <div>
                <label className="text-xs font-semibold text-slate-700">Pilih Kelas / Rombel Siswa *</label>
                <select
                  value={selectedClassId}
                  onChange={(e) => setSelectedClassId(e.target.value)}
                  className="w-full text-xs rounded-md border border-slate-300 p-2 mt-1"
                  required
                >
                  <option value="">Pilih Rombel Siswa</option>
                  {classes.map((c) => (
                    <option key={c.id} value={c.id}>
                      {c.name}
                    </option>
                  ))}
                </select>
                <p className="text-[11px] text-slate-500 mt-1">
                  Seluruh siswa aktif di rombel terpilih akan secara otomatis terdaftar sebagai peserta ujian.
                </p>
              </div>

              <div className="flex items-center justify-end gap-2 pt-3 border-t">
                <Button type="button" variant="outline" size="sm" onClick={() => setIsEnrollModalOpen(false)} disabled={isEnrolling} className="text-xs">
                  Batal
                </Button>
                <Button type="submit" size="sm" disabled={isEnrolling} className="bg-blue-600 hover:bg-blue-500 text-white text-xs gap-1.5">
                  {isEnrolling ? (
                    <>
                      <Loader2 className="h-3.5 w-3.5 animate-spin" />
                      Mendaftarkan...
                    </>
                  ) : (
                    'Daftarkan Siswa'
                  )}
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Modal: Hasil Ujian & Sinkronisasi ke Buku Nilai (Gradebook) */}
      {isResultsModalOpen && activeExam && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 overflow-y-auto">
          <div className="w-full max-w-4xl rounded-xl bg-white p-6 shadow-xl space-y-5 my-8">
            <div className="flex items-center justify-between border-b pb-3">
              <div>
                <div className="flex items-center gap-2">
                  <Badge variant="outline" className="text-xs bg-slate-50">
                    KKTP: {activeExam.passing_grade ?? 75}
                  </Badge>
                  <span className="text-xs text-slate-400">•</span>
                  <span className="text-xs text-slate-600">{activeExam.subject?.name}</span>
                </div>
                <h3 className="font-bold text-slate-900 text-base mt-1">
                  Rekapitulasi Nilai: {activeExam.title}
                </h3>
              </div>
              <button onClick={() => setIsResultsModalOpen(false)} className="text-slate-400 hover:text-slate-600">
                <X className="h-4 w-4" />
              </button>
            </div>

            {/* Top Toolbar Actions */}
            <div className="flex flex-wrap items-center justify-between gap-3 p-3 bg-slate-50 rounded-lg border border-slate-200">
              <div className="flex items-center gap-2">
                <Button
                  size="sm"
                  disabled={isRunningAutoGrade}
                  onClick={handleRunAutoGrade}
                  className="bg-indigo-600 hover:bg-indigo-500 text-white text-xs gap-1.5"
                >
                  {isRunningAutoGrade ? (
                    <>
                      <Loader2 className="h-3.5 w-3.5 animate-spin" />
                      Mengoreksi...
                    </>
                  ) : (
                    <>
                      <Sparkles className="h-3.5 w-3.5" />
                      Koreksi Otomatis (Auto-Grade)
                    </>
                  )}
                </Button>
                <Button
                  size="sm"
                  variant="outline"
                  disabled={isCreatingRemedial}
                  onClick={handleCreateRemedial}
                  className="text-xs gap-1.5 text-amber-700 border-amber-300"
                >
                  {isCreatingRemedial ? (
                    <>
                      <Loader2 className="h-3.5 w-3.5 animate-spin" />
                      Membuat...
                    </>
                  ) : (
                    <>
                      <Layers className="h-3.5 w-3.5 text-amber-600" />
                      Buat Ujian Remedial KKTP
                    </>
                  )}
                </Button>
              </div>

              <div className="text-xs text-slate-500 font-medium">
                Total Peserta: {examResults.length} Siswa
              </div>
            </div>

            {/* Sync to Gradebook Section */}
            <div className="p-4 rounded-xl border border-blue-200 bg-blue-50/40 space-y-3">
              <div className="flex items-center gap-2">
                <BookOpen className="h-4 w-4 text-blue-600" />
                <h4 className="font-bold text-xs text-blue-900">
                  Publikasikan Nilai ke Buku Nilai (Gradebook)
                </h4>
              </div>
              <form onSubmit={handlePublishGradebook} className="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">
                <div>
                  <label className="text-[11px] font-semibold text-slate-700">Komponen Penilaian *</label>
                  <select
                    value={selectedComponentId}
                    onChange={(e) => setSelectedComponentId(e.target.value)}
                    className="w-full text-xs rounded-md border border-slate-300 p-2 mt-1 bg-white"
                    required
                  >
                    <option value="">Pilih Komponen Nilai</option>
                    {gradeComponents.map((c) => (
                      <option key={c.id} value={c.id}>
                        {c.name} ({c.code}) - Bobot {c.weight}%
                      </option>
                    ))}
                  </select>
                </div>

                <div>
                  <label className="text-[11px] font-semibold text-slate-700">Periode Penilaian Bulanan *</label>
                  <select
                    value={selectedPeriodId}
                    onChange={(e) => setSelectedPeriodId(e.target.value)}
                    className="w-full text-xs rounded-md border border-slate-300 p-2 mt-1 bg-white"
                    required
                  >
                    <option value="">Pilih Periode</option>
                    {gradePeriods.map((p) => (
                      <option key={p.id} value={p.id} disabled={p.status === 'locked'}>
                        {p.month_name} ({p.status === 'locked' ? 'Terkunci 🔒' : 'Terbuka 🔓'})
                      </option>
                    ))}
                  </select>
                </div>

                <Button
                  type="submit"
                  size="sm"
                  disabled={isPublishingGradebook}
                  className="bg-blue-600 hover:bg-blue-500 text-white text-xs gap-1.5 h-9"
                >
                  <Send className="h-3.5 w-3.5" />
                  {isPublishingGradebook ? 'Menyinkronkan...' : 'Publikasikan ke Gradebook'}
                </Button>
              </form>
            </div>

            {/* Results & Essay Grading Tabs */}
            <div className="flex items-center justify-between border-b border-slate-200 pb-2">
              <div className="flex items-center gap-2">
                <button
                  type="button"
                  onClick={() => setResultsTab('rekap')}
                  className={`px-3 py-1.5 rounded-lg text-xs font-bold transition-colors ${
                    resultsTab === 'rekap' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                  }`}
                >
                  Rekap Nilai ({examResults.length})
                </button>
                <button
                  type="button"
                  onClick={() => setResultsTab('esai')}
                  className={`px-3 py-1.5 rounded-lg text-xs font-bold transition-colors ${
                    resultsTab === 'esai' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                  }`}
                >
                  Koreksi Esai Manual ({essaySubmissions.filter((s) => !s.is_graded).length} Belum Dinilai)
                </button>
              </div>
            </div>

            {resultsTab === 'esai' ? (
              <div className="space-y-3 max-h-96 overflow-y-auto pr-1">
                {essaySubmissions.length === 0 ? (
                  <div className="py-8 text-center text-xs text-slate-500 border rounded-lg">
                    Tidak ada jawaban soal uraian/esai pada ujian ini.
                  </div>
                ) : (
                  essaySubmissions.map((sub) => {
                    const ansText =
                      typeof sub.student_answer === 'object' && sub.student_answer !== null
                        ? (sub.student_answer.text ?? JSON.stringify(sub.student_answer))
                        : String(sub.student_answer ?? '-');

                    return (
                      <div key={sub.answer_id} className="p-3.5 rounded-xl border border-slate-200 bg-slate-50/50 space-y-2">
                        <div className="flex items-center justify-between gap-2">
                          <div>
                            <span className="font-bold text-xs text-slate-900">{sub.student_name}</span>
                            <span className="text-[11px] text-slate-500 ml-2">NIS: {sub.nis}</span>
                          </div>
                          <Badge
                            className={`text-[10px] ${
                              sub.is_graded
                                ? 'bg-emerald-100 text-emerald-800 border-emerald-300'
                                : 'bg-amber-100 text-amber-800 border-amber-300'
                            }`}
                          >
                            {sub.is_graded ? `Dinilai: ${sub.current_score}/${sub.max_score}` : `Belum Dinilai (Maks ${sub.max_score})`}
                          </Badge>
                        </div>
                        <div className="text-[11px] text-slate-600 font-medium">{sub.question_text}</div>
                        <div className="p-2.5 rounded-lg bg-white border border-slate-200 text-xs text-slate-800 whitespace-pre-line">
                          {ansText}
                        </div>
                        <div className="flex items-center justify-end gap-2 pt-1">
                          <label className="text-[11px] font-semibold text-slate-600">Skor (0–{sub.max_score}):</label>
                          <Input
                            type="number"
                            min={0}
                            max={sub.max_score}
                            step="0.5"
                            value={essayScoreInputs[sub.answer_id] ?? ''}
                            onChange={(e) =>
                              setEssayScoreInputs((prev) => ({ ...prev, [sub.answer_id]: e.target.value }))
                            }
                            className="w-24 h-8 text-xs"
                          />
                          <Button
                            type="button"
                            size="sm"
                            disabled={gradingAnswerId === sub.answer_id}
                            onClick={() => handleGradeEssay(sub)}
                            className="h-8 text-xs bg-blue-600 hover:bg-blue-500 text-white"
                          >
                            {gradingAnswerId === sub.answer_id ? 'Menyimpan...' : 'Simpan Nilai'}
                          </Button>
                        </div>
                      </div>
                    );
                  })
                )}
              </div>
            ) : (
            <div className="space-y-2">
              <div className="text-xs font-bold text-slate-800">Daftar Nilai Siswa</div>
              <div className="overflow-x-auto max-h-72 border rounded-lg">
                <table className="w-full text-left text-xs border-collapse">
                  <thead className="sticky top-0 bg-slate-100 border-b text-slate-600 font-semibold uppercase">
                    <tr>
                      <th className="py-2.5 px-3">Nama Siswa & NIS</th>
                      <th className="py-2.5 px-3">Kelas</th>
                      <th className="py-2.5 px-3 text-center">Status Sesi</th>
                      <th className="py-2.5 px-3 text-center">Skor Akhir</th>
                      <th className="py-2.5 px-3 text-center">Hasil KKTP</th>
                      <th className="py-2.5 px-3 text-right">Waktu Selesai</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100 bg-white">
                    {isLoadingResults ? (
                      <tr>
                        <td colSpan={6} className="py-8 text-center text-slate-400">
                          Memuat data nilai...
                        </td>
                      </tr>
                    ) : examResults.length === 0 ? (
                      <tr>
                        <td colSpan={6} className="py-8 text-center text-slate-500">
                          Belum ada peserta yang mengumpulkan ujian ini.
                        </td>
                      </tr>
                    ) : (
                      examResults.map((r) => (
                        <tr key={r.session_id} className="hover:bg-slate-50">
                          <td className="py-2.5 px-3">
                            <div className="font-semibold text-slate-900">{r.student_name}</div>
                            <div className="text-[10px] text-slate-400">NIS: {r.nis}</div>
                          </td>
                          <td className="py-2.5 px-3 text-slate-600">{r.class_name}</td>
                          <td className="py-2.5 px-3 text-center">
                            <Badge variant="outline" className="text-[10px] capitalize">
                              {r.status}
                            </Badge>
                          </td>
                          <td className="py-2.5 px-3 text-center font-bold text-slate-900 text-sm">
                            {r.total_score !== null ? r.total_score : '-'}
                            {r.grading_status === 'pending_essay' && (
                              <span className="block text-[10px] font-medium text-amber-600">
                                Menunggu Esai ({r.ungraded_essays_count})
                              </span>
                            )}
                          </td>
                          <td className="py-2.5 px-3 text-center">
                            {r.total_score !== null ? (
                              r.is_passed ? (
                                <Badge className="bg-emerald-100 text-emerald-800 border-emerald-300 text-[10px]">
                                  Lulus KKTP
                                </Badge>
                              ) : (
                                <Badge className="bg-rose-100 text-rose-800 border-rose-300 text-[10px]">
                                  Remedial
                                </Badge>
                              )
                            ) : (
                              <span className="text-slate-400">-</span>
                            )}
                          </td>
                          <td className="py-2.5 px-3 text-right text-slate-500 text-[11px]">
                            {r.submitted_at ? new Date(r.submitted_at).toLocaleTimeString('id-ID') : '-'}
                          </td>
                        </tr>
                      ))
                    )}
                  </tbody>
                </table>
              </div>
            </div>
            )}

            <div className="flex justify-end pt-3 border-t">
              <Button size="sm" variant="outline" onClick={() => setIsResultsModalOpen(false)} className="text-xs">
                Tutup Rekap
              </Button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
