'use client';

import React, { useEffect, useState } from 'react';
import Link from 'next/link';
import {
  FileQuestion,
  Plus,
  Trash2,
  Edit3,
  Search,
  Filter,
  ArrowLeft,
  CheckCircle,
  HelpCircle,
  FolderPlus,
  Layers,
  BookOpen,
  AlertCircle,
  Save,
  X,
  Image as ImageIcon,
  UploadCloud,
  Loader2,
  FileText,
  Download,
  CheckCircle2,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { cbtApi } from '@/features/cbt/cbt-api';
import { storageApi } from '@/features/storage/storage-api';
import { subjectsApi } from '@/features/master/subjects-api';
import { QuestionBankItem, QuestionItem, QuestionType } from '@/types/cbt';
import { SubjectItem } from '@/types/master';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { toast } from '@/components/ui/toast';

export default function QuestionBanksPage() {
  const [banks, setBanks] = useState<QuestionBankItem[]>([]);
  const [selectedBankId, setSelectedBankId] = useState<string>('');
  const [questions, setQuestions] = useState<QuestionItem[]>([]);
  const [subjects, setSubjects] = useState<SubjectItem[]>([]);

  const [isLoadingBanks, setIsLoadingBanks] = useState<boolean>(true);
  const [isLoadingQuestions, setIsLoadingQuestions] = useState<boolean>(false);
  const [searchQuery, setSearchQuery] = useState<string>('');
  const [questionToDelete, setQuestionToDelete] = useState<string | null>(null);
  const [isDeletingQuestion, setIsDeletingQuestion] = useState<boolean>(false);
  const [filterType, setFilterType] = useState<string>('ALL');

  // Modal States
  const [isBankModalOpen, setIsBankModalOpen] = useState<boolean>(false);
  const [isQuestionModalOpen, setIsQuestionModalOpen] = useState<boolean>(false);
  const [editingQuestion, setEditingQuestion] = useState<QuestionItem | null>(null);
  const [isCreatingBank, setIsCreatingBank] = useState<boolean>(false);
  const [isSavingQuestion, setIsSavingQuestion] = useState<boolean>(false);

  // DOCX Import States (BE-CBT-13)
  const [isImportModalOpen, setIsImportModalOpen] = useState<boolean>(false);
  const [importFile, setImportFile] = useState<File | null>(null);
  const [isUploadingImport, setIsUploadingImport] = useState<boolean>(false);
  const [importJob, setImportJob] = useState<any>(null);
  const [isCommittingImport, setIsCommittingImport] = useState<boolean>(false);

  // Bank Form State
  const [bankForm, setBankForm] = useState({
    title: '',
    code: '',
    subject_id: '',
    grade_level: '10',
  });

  // Question Form State
  const [questionForm, setQuestionForm] = useState<{
    question_type: QuestionType;
    stem: string;
    media_url?: string;
    weight: number;
    options: Array<{
      id?: string;
      label: string;
      text: string;
      is_correct?: boolean;
      match_key?: string | null;
    }>;
  }>({
    question_type: 'pg',
    stem: '',
    media_url: '',
    weight: 1.0,
    options: [
      { label: 'A', text: '', is_correct: true },
      { label: 'B', text: '', is_correct: false },
      { label: 'C', text: '', is_correct: false },
      { label: 'D', text: '', is_correct: false },
    ],
  });

  const [isUploadingImage, setIsUploadingImage] = useState<boolean>(false);

  const handleImageUpload = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    if (!file.type.startsWith('image/')) {
      setFeedbackMsg({ type: 'error', text: 'Berkas harus berupa gambar (PNG, JPG, WebP, GIF).' });
      return;
    }

    if (file.size > 5 * 1024 * 1024) {
      setFeedbackMsg({ type: 'error', text: 'Ukuran gambar maksimal 5MB.' });
      return;
    }

    setIsUploadingImage(true);
    try {
      const fileItem = await storageApi.uploadFileDirect({
        file,
        visibility: 'publik',
        purpose: 'cbt_stimulus',
      });
      const url = fileItem.download_url || fileItem.url || `/api/v1/files/${fileItem.id}/download`;
      setQuestionForm((prev) => ({ ...prev, media_url: url }));
      setFeedbackMsg({ type: 'success', text: 'Gambar stimulus berhasil diunggah!' });
    } catch (err: any) {
      setFeedbackMsg({ type: 'error', text: err?.message || 'Gagal mengunggah berkas gambar.' });
    } finally {
      setIsUploadingImage(false);
    }
  };

  const [feedbackMsg, setFeedbackMsg] = useState<{ type: 'success' | 'error'; text: string } | null>(null);

  const fetchBanksAndSubjects = async () => {
    setIsLoadingBanks(true);
    try {
      const [banksRes, subjectsRes] = await Promise.all([
        cbtApi.getQuestionBanks({ per_page: 50 }),
        subjectsApi.list({ limit: 100 }),
      ]);
      const loadedBanks = banksRes.data || [];
      setBanks(loadedBanks);
      setSubjects(subjectsRes.data || []);

      if (loadedBanks.length > 0 && !selectedBankId) {
        setSelectedBankId(loadedBanks[0].id);
      }
    } catch (err: any) {
      setFeedbackMsg({ type: 'error', text: err?.message || 'Gagal memuat bank soal.' });
    } finally {
      setIsLoadingBanks(false);
    }
  };

  const fetchQuestions = async (bankId: string) => {
    if (!bankId) return;
    setIsLoadingQuestions(true);
    try {
      const res = await cbtApi.getQuestions({
        bank_id: bankId,
        question_type: filterType !== 'ALL' ? filterType : undefined,
        search: searchQuery || undefined,
        per_page: 100,
      });
      setQuestions(res.data || []);
    } catch (err: any) {
      setFeedbackMsg({ type: 'error', text: err?.message || 'Gagal memuat daftar soal.' });
    } finally {
      setIsLoadingQuestions(false);
    }
  };

  useEffect(() => {
    fetchBanksAndSubjects();
  }, []);

  useEffect(() => {
    if (selectedBankId) {
      fetchQuestions(selectedBankId);
    }
  }, [selectedBankId, filterType, searchQuery]);

  const handleCreateBank = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!bankForm.title || !bankForm.code || !bankForm.subject_id) {
      setFeedbackMsg({ type: 'error', text: 'Semua kolom bank soal wajib diisi.' });
      return;
    }

    setIsCreatingBank(true);
    try {
      const res = await cbtApi.createQuestionBank({
        title: bankForm.title,
        code: bankForm.code,
        subject_id: bankForm.subject_id,
        grade_level: parseInt(bankForm.grade_level, 10),
      });
      setFeedbackMsg({ type: 'success', text: 'Paket bank soal berhasil dibuat!' });
      setIsBankModalOpen(false);
      setBankForm({ title: '', code: '', subject_id: '', grade_level: '10' });
      fetchBanksAndSubjects();
      setSelectedBankId(res.data.id);
    } catch (err: any) {
      setFeedbackMsg({ type: 'error', text: err?.message || 'Gagal membuat bank soal.' });
    } finally {
      setIsCreatingBank(false);
    }
  };

  const handleOpenAddQuestion = () => {
    setEditingQuestion(null);
    setQuestionForm({
      question_type: 'pg',
      stem: '',
      media_url: '',
      weight: 1.0,
      options: [
        { label: 'A', text: '', is_correct: true },
        { label: 'B', text: '', is_correct: false },
        { label: 'C', text: '', is_correct: false },
        { label: 'D', text: '', is_correct: false },
      ],
    });
    setIsQuestionModalOpen(true);
  };

  const handleOpenEditQuestion = (q: QuestionItem) => {
    setEditingQuestion(q);
    const normalizedType = (q.question_type === 'pilihan_ganda' ? 'pg' : q.question_type) as QuestionType;
    setQuestionForm({
      question_type: normalizedType || 'pg',
      stem: q.stem || q.body?.content || q.body?.text || '',
      media_url: q.media_url || q.body?.media_url || q.body?.image_url || '',
      weight: typeof q.weight === 'string' ? parseFloat(q.weight) : q.weight || 1.0,
      options: q.options?.map((opt) => ({
        id: opt.id,
        label: opt.label,
        text: opt.text || opt.body?.text || opt.body?.content || '',
        is_correct: opt.is_correct ?? false,
        match_key: opt.match_key || null,
      })) || [],
    });
    setIsQuestionModalOpen(true);
  };

  const handleStartImport = async () => {
    if (!importFile || !selectedBankId) return;
    try {
      setIsUploadingImport(true);
      setFeedbackMsg(null);
      const res = await cbtApi.uploadQuestionDocx(selectedBankId, importFile);
      const jobId = res.data?.id;
      if (jobId) {
        const jobRes = await cbtApi.getQuestionImportJob(jobId);
        setImportJob(jobRes.data);
      }
    } catch (err: any) {
      setFeedbackMsg({ type: 'error', text: err?.message || 'Gagal memproses berkas DOCX.' });
    } finally {
      setIsUploadingImport(false);
    }
  };

  const handleCommitImport = async () => {
    if (!importJob?.id) return;
    try {
      setIsCommittingImport(true);
      const res = await cbtApi.commitQuestionImport(importJob.id);
      const count = res.data?.imported_count || importJob.parsed_payload?.questions?.length || 0;
      setFeedbackMsg({
        type: 'success',
        text: `Berhasil mengimpor ${count} butir soal ke dalam bank soal!`,
      });
      setIsImportModalOpen(false);
      setImportJob(null);
      setImportFile(null);
      if (selectedBankId) {
        fetchQuestions(selectedBankId);
      }
      fetchBanksAndSubjects();
    } catch (err: any) {
      setFeedbackMsg({ type: 'error', text: err?.message || 'Gagal menyimpan butir soal ke bank soal.' });
    } finally {
      setIsCommittingImport(false);
    }
  };

  const handleSaveQuestion = async (e: React.FormEvent) => {
    e.preventDefault();
    if (isSavingQuestion) return;
    if (!questionForm.stem) {
      setFeedbackMsg({ type: 'error', text: 'Teks soal wajib diisi.' });
      return;
    }

    setIsSavingQuestion(true);
    try {
      if (editingQuestion) {
        await cbtApi.updateQuestion(editingQuestion.id, {
          question_type: questionForm.question_type,
          stem: questionForm.stem,
          media_url: questionForm.media_url || undefined,
          weight: questionForm.weight,
          options: questionForm.options,
        });
        setFeedbackMsg({ type: 'success', text: 'Butir soal berhasil diperbarui.' });
      } else {
        await cbtApi.createQuestion({
          bank_id: selectedBankId,
          question_type: questionForm.question_type,
          stem: questionForm.stem,
          media_url: questionForm.media_url || undefined,
          weight: questionForm.weight,
          options: questionForm.options,
        });
        setFeedbackMsg({ type: 'success', text: 'Butir soal berhasil ditambahkan ke bank.' });
      }
      setIsQuestionModalOpen(false);
      fetchQuestions(selectedBankId);
    } catch (err: any) {
      setFeedbackMsg({ type: 'error', text: err?.message || 'Gagal menyimpan butir soal.' });
    } finally {
      setIsSavingQuestion(false);
    }
  };

  const handleDeleteQuestion = (id: string) => {
    setQuestionToDelete(id);
  };

  const handleConfirmDeleteQuestion = async () => {
    if (!questionToDelete) return;
    setIsDeletingQuestion(true);
    try {
      await cbtApi.deleteQuestion(questionToDelete);
      setFeedbackMsg({ type: 'success', text: 'Butir soal berhasil dihapus.' });
      toast.success('Butir soal berhasil dihapus.');
      setQuestionToDelete(null);
      fetchQuestions(selectedBankId);
    } catch (err: any) {
      const errMsg = err?.message || 'Gagal menghapus butir soal.';
      setFeedbackMsg({ type: 'error', text: errMsg });
      toast.error(errMsg);
    } finally {
      setIsDeletingQuestion(false);
    }
  };

  const selectedBank = banks.find((b) => b.id === selectedBankId);

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
            Bank Soal Ujian (ADM-CBT-01) 📝
          </h1>
          <p className="text-xs text-slate-500 mt-0.5">
            Kelola repositori butir soal dengan 6 model (PG, PG Kompleks, Benar/Salah, Menjodohkan, Isian, Uraian).
          </p>
        </div>

        <div className="flex items-center gap-2.5">
          <a
            href="/downloads/template_impor_soal_cbt.docx"
            download="template_impor_soal_cbt.docx"
            className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-slate-700 bg-white border border-slate-300 rounded-md hover:bg-slate-50 transition-colors shadow-sm"
          >
            <Download className="h-4 w-4 text-emerald-600" />
            Unduh Template DOCX
          </a>
          <Button
            size="sm"
            variant="outline"
            onClick={() => setIsBankModalOpen(true)}
            className="text-xs gap-1.5"
          >
            <FolderPlus className="h-4 w-4" />
            + Buat Paket Bank
          </Button>
          <Button
            size="sm"
            onClick={handleOpenAddQuestion}
            disabled={!selectedBankId}
            className="bg-blue-600 hover:bg-blue-500 text-white text-xs gap-1.5"
          >
            <Plus className="h-4 w-4" />
            Tambah Butir Soal
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

      {/* Main Content Layout */}
      <div className="grid grid-cols-1 lg:grid-cols-4 gap-6">
        {/* Left Column: Bank Selection */}
        <div className="lg:col-span-1 space-y-3">
          <Card>
            <CardHeader className="pb-2.5">
              <CardTitle className="text-xs uppercase font-semibold text-slate-500">
                Pilih Paket Bank Soal
              </CardTitle>
            </CardHeader>
            <CardContent className="space-y-2">
              {isLoadingBanks ? (
                <div className="py-4 text-center text-xs text-slate-400">Memuat bank soal...</div>
              ) : banks.length === 0 ? (
                <div className="py-4 text-center text-xs text-slate-500">Belum ada paket bank soal.</div>
              ) : (
                banks.map((b) => (
                  <button
                    key={b.id}
                    onClick={() => setSelectedBankId(b.id)}
                    className={`w-full text-left p-3 rounded-lg border transition-all text-xs ${
                      selectedBankId === b.id
                        ? 'border-blue-600 bg-blue-50/60 shadow-sm'
                        : 'border-slate-200 hover:border-slate-300 bg-white'
                    }`}
                  >
                    <div className="font-semibold text-slate-900 leading-snug">{b.name || b.title || 'Paket Bank Soal'}</div>
                    <div className="text-[11px] text-slate-500 mt-1 flex items-center justify-between">
                      <span>Kode: {b.code || b.subject?.code || '-'}</span>
                      <span className="font-medium text-blue-600">{b.questions_count ?? b.total_questions ?? 0} Soal</span>
                    </div>
                  </button>
                ))
              )}
            </CardContent>
          </Card>
        </div>

        {/* Right Column: Questions in Selected Bank */}
        <div className="lg:col-span-3 space-y-4">
          {selectedBank && (
            <div className="p-4 bg-white rounded-xl border border-slate-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 shadow-sm">
              <div>
                <div className="flex items-center gap-2">
                  <Badge variant="outline" className="bg-slate-50 text-slate-700 text-[11px]">
                    {selectedBank.code || selectedBank.subject?.code || 'CBT'}
                  </Badge>
                  <span className="text-xs text-slate-400">•</span>
                  <span className="text-xs text-slate-600 font-medium">
                    {selectedBank.subject?.name || 'Mata Pelajaran'}
                  </span>
                </div>
                <h2 className="text-base font-bold text-slate-900 mt-1">{selectedBank.name || selectedBank.title || 'Paket Soal'}</h2>
              </div>
              <div className="flex items-center gap-2">
                <Button
                  size="sm"
                  variant="outline"
                  onClick={() => {
                    setImportFile(null);
                    setImportJob(null);
                    setIsImportModalOpen(true);
                  }}
                  className="text-xs gap-1.5 border-emerald-300 text-emerald-700 hover:bg-emerald-50"
                >
                  <FileText className="h-3.5 w-3.5" /> Impor Word (.docx)
                </Button>
                <Button size="sm" onClick={handleOpenAddQuestion} className="bg-blue-600 hover:bg-blue-500 text-white text-xs gap-1">
                  <Plus className="h-3.5 w-3.5" /> Tambah Soal
                </Button>
              </div>
            </div>
          )}

          {/* Filters & Search */}
          <div className="flex flex-col sm:flex-row items-center justify-between gap-3">
            <div className="relative w-full sm:w-64">
              <Search className="absolute left-2.5 top-2.5 h-3.5 w-3.5 text-slate-400" />
              <Input
                placeholder="Cari teks pertanyaan..."
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className="pl-8 text-xs h-8"
              />
            </div>

            <div className="flex items-center gap-1.5 overflow-x-auto w-full sm:w-auto pb-1 sm:pb-0">
              {['ALL', 'pg', 'pg_kompleks', 'bs', 'menjodohkan', 'isian', 'uraian'].map((type) => (
                <button
                  key={type}
                  onClick={() => setFilterType(type)}
                  className={`px-2.5 py-1 rounded-md text-[11px] font-medium whitespace-nowrap transition-colors ${
                    filterType === type
                      ? 'bg-slate-900 text-white'
                      : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                  }`}
                >
                  {type === 'ALL'
                    ? 'Semua Tipe'
                    : type === 'pg'
                    ? 'PG Tunggal'
                    : type === 'pg_kompleks'
                    ? 'PG Kompleks'
                    : type === 'bs'
                    ? 'Benar/Salah'
                    : type === 'menjodohkan'
                    ? 'Menjodohkan'
                    : type === 'isian'
                    ? 'Isian'
                    : 'Uraian'}
                </button>
              ))}
            </div>
          </div>

          {/* Question List */}
          <Card>
            <CardContent className="p-4 space-y-4">
              {isLoadingQuestions ? (
                <div className="py-12 text-center text-xs text-slate-400">Memuat butir soal...</div>
              ) : questions.length === 0 ? (
                <div className="py-12 text-center text-xs text-slate-500">
                  Tidak ada butir soal yang sesuai filter.{' '}
                  <button onClick={handleOpenAddQuestion} className="text-blue-600 hover:underline">
                    Tambah butir soal baru.
                  </button>
                </div>
              ) : (
                questions.map((q, idx) => (
                  <div
                    key={q.id}
                    className="p-4 rounded-lg border border-slate-200 bg-white hover:border-slate-300 transition-all space-y-3"
                  >
                    <div className="flex items-start justify-between gap-3">
                      <div className="flex items-center gap-2">
                        <span className="flex items-center justify-center h-6 w-6 rounded-full bg-slate-100 text-slate-800 text-xs font-bold">
                          {idx + 1}
                        </span>
                        <Badge
                          variant="outline"
                          className="text-[10px] uppercase font-semibold tracking-wider bg-slate-100"
                        >
                          {q.question_type === 'pg' || q.question_type === 'pilihan_ganda'
                            ? 'PG Tunggal'
                            : q.question_type === 'pg_kompleks'
                            ? 'PG Kompleks'
                            : q.question_type === 'bs'
                            ? 'Benar/Salah'
                            : q.question_type === 'menjodohkan'
                            ? 'Menjodohkan'
                            : q.question_type === 'isian'
                            ? 'Isian'
                            : 'Uraian'}
                        </Badge>
                        <span className="text-[11px] text-slate-500">Bobot: {q.weight} Poin</span>
                      </div>

                      <div className="flex items-center gap-1">
                        <Button
                          size="sm"
                          variant="ghost"
                          onClick={() => handleOpenEditQuestion(q)}
                          className="h-7 w-7 p-0 text-slate-500 hover:text-slate-800"
                        >
                          <Edit3 className="h-3.5 w-3.5" />
                        </Button>
                        <Button
                          size="sm"
                          variant="ghost"
                          onClick={() => handleDeleteQuestion(q.id)}
                          className="h-7 w-7 p-0 text-rose-500 hover:text-rose-700"
                        >
                          <Trash2 className="h-3.5 w-3.5" />
                        </Button>
                      </div>
                    </div>

                    <div className="text-xs text-slate-800 font-medium leading-relaxed pl-8 whitespace-pre-wrap">
                      {q.stem || q.body?.content || q.body?.text || '(Teks pertanyaan kosong)'}
                    </div>

                    {/* Question Media Preview */}
                    {(q.media_url || q.body?.media_url || q.body?.image_url) && (
                      <div className="pl-8 pt-1">
                        <img
                          src={q.media_url || q.body?.media_url || q.body?.image_url}
                          alt="Media Soal"
                          className="max-h-48 rounded border border-slate-200 object-contain bg-slate-50"
                        />
                      </div>
                    )}

                    {/* Options Preview */}
                    {q.options && q.options.length > 0 && (
                      <div className="pl-8 grid grid-cols-1 sm:grid-cols-2 gap-2 pt-1">
                        {q.options.map((opt) => (
                          <div
                            key={opt.id || opt.label}
                            className={`p-2 rounded border text-[11px] flex items-center justify-between ${
                              opt.is_correct
                                ? 'border-emerald-300 bg-emerald-50/50 text-emerald-900 font-medium'
                                : 'border-slate-100 bg-slate-50 text-slate-600'
                            }`}
                          >
                            <span>
                              <b className="mr-1">{opt.label}.</b> {opt.text || opt.body?.text || opt.body?.content || ''}
                              {opt.match_key && (
                                <span className="ml-1 text-blue-600 font-normal">→ {opt.match_key}</span>
                              )}
                            </span>
                            {opt.is_correct && (
                              <CheckCircle className="h-3.5 w-3.5 text-emerald-600 shrink-0" />
                            )}
                          </div>
                        ))}
                      </div>
                    )}

                    {/* Explanation / Pembahasan */}
                    {q.explanation && (
                      <div className="pl-8 pt-1 text-[11px] text-slate-500 italic bg-amber-50/50 p-2 rounded border border-amber-100/60">
                        Pembahasan: {q.explanation}
                      </div>
                    )}
                  </div>
                ))
              )}
            </CardContent>
          </Card>
        </div>
      </div>

      {/* Modal: Buat Paket Bank Soal */}
      {isBankModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="w-full max-w-md rounded-xl bg-white p-6 shadow-xl space-y-4">
            <div className="flex items-center justify-between border-b pb-3">
              <h3 className="font-bold text-slate-900 text-sm">Buat Paket Bank Soal Baru</h3>
              <button onClick={() => setIsBankModalOpen(false)} className="text-slate-400 hover:text-slate-600">
                <X className="h-4 w-4" />
              </button>
            </div>
            <form onSubmit={handleCreateBank} className="space-y-3">
              <div>
                <label className="text-xs font-semibold text-slate-700">Mata Pelajaran *</label>
                <select
                  value={bankForm.subject_id}
                  onChange={(e) => setBankForm({ ...bankForm, subject_id: e.target.value })}
                  className="w-full text-xs rounded-md border border-slate-300 p-2 mt-1"
                  required
                >
                  <option value="">Pilih Mata Pelajaran</option>
                  {subjects.map((s) => (
                    <option key={s.id} value={s.id}>
                      {s.name} ({s.code})
                    </option>
                  ))}
                </select>
              </div>
              <div>
                <label className="text-xs font-semibold text-slate-700">Judul Paket Soal *</label>
                <Input
                  value={bankForm.title}
                  onChange={(e) => setBankForm({ ...bankForm, title: e.target.value })}
                  placeholder="Contoh: Bank Soal Matematika Semester Ganjil"
                  className="text-xs mt-1"
                  required
                />
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="text-xs font-semibold text-slate-700">Kode Paket *</label>
                  <Input
                    value={bankForm.code}
                    onChange={(e) => setBankForm({ ...bankForm, code: e.target.value })}
                    placeholder="MAT-X-01"
                    className="text-xs mt-1 uppercase"
                    required
                  />
                </div>
                <div>
                  <label className="text-xs font-semibold text-slate-700">Tingkat Kelas</label>
                  <select
                    value={bankForm.grade_level}
                    onChange={(e) => setBankForm({ ...bankForm, grade_level: e.target.value })}
                    className="w-full text-xs rounded-md border border-slate-300 p-2 mt-1"
                  >
                    <option value="7">Kelas 7 (SMP)</option>
                    <option value="8">Kelas 8 (SMP)</option>
                    <option value="9">Kelas 9 (SMP)</option>
                    <option value="10">Kelas 10 (SMA)</option>
                    <option value="11">Kelas 11 (SMA)</option>
                    <option value="12">Kelas 12 (SMA)</option>
                  </select>
                </div>
              </div>
              <div className="flex items-center justify-end gap-2 pt-3 border-t">
                <Button type="button" variant="outline" size="sm" onClick={() => setIsBankModalOpen(false)} disabled={isCreatingBank} className="text-xs">
                  Batal
                </Button>
                <Button type="submit" size="sm" disabled={isCreatingBank} className="bg-blue-600 hover:bg-blue-500 text-white text-xs gap-1.5">
                  {isCreatingBank ? (
                    <>
                      <Loader2 className="h-3.5 w-3.5 animate-spin" />
                      Menyimpan...
                    </>
                  ) : (
                    'Simpan Paket'
                  )}
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Modal: Impor Butir Soal Word (.docx) (BE-CBT-13) */}
      {isImportModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 overflow-y-auto">
          <div className="w-full max-w-2xl rounded-xl bg-white p-6 shadow-xl space-y-4 my-8 max-h-[90vh] flex flex-col">
            <div className="flex items-center justify-between border-b pb-3">
              <div className="flex items-center gap-2">
                <FileText className="h-5 w-5 text-emerald-600" />
                <div>
                  <h3 className="font-bold text-slate-900 text-sm">Impor Butir Soal Word (.docx)</h3>
                  <p className="text-[11px] text-slate-500">Ekstrak otomatis naskah soal dari dokumen Word ke paket ini.</p>
                </div>
              </div>
              <button
                onClick={() => {
                  setIsImportModalOpen(false);
                  setImportJob(null);
                  setImportFile(null);
                }}
                className="text-slate-400 hover:text-slate-600"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            <div className="flex-1 overflow-y-auto space-y-4 pr-1">
              {/* Card Panduan & Download Template */}
              <div className="bg-slate-50 border border-slate-200 rounded-lg p-3 text-xs space-y-2">
                <div className="flex items-center justify-between">
                  <span className="font-semibold text-slate-800 flex items-center gap-1.5">
                    <BookOpen className="h-3.5 w-3.5 text-blue-600" /> Format Naskah Word Baku
                  </span>
                  <a
                    href="/downloads/template_impor_soal_cbt.docx"
                    download="template_impor_soal_cbt.docx"
                    className="inline-flex items-center gap-1.5 px-2.5 py-1 text-[11px] font-semibold text-white bg-emerald-600 hover:bg-emerald-500 rounded shadow-sm transition-colors"
                  >
                    <Download className="h-3.5 w-3.5" /> Unduh Template (.docx)
                  </a>
                </div>
                <p className="text-slate-600 text-[11px] leading-relaxed">
                  Gunakan penomoran angka berurutan dan tag tipe soal pada baris pertama:
                </p>
                <div className="bg-white p-2 rounded border border-slate-200 font-mono text-[10px] text-slate-700 space-y-1">
                  <div>1. [PG] [BOBOT: 2] [LEVEL: MOTS] ... KUNCI: C</div>
                  <div>2. [PG_KOMPLEKS] [BOBOT: 3] [LEVEL: HOTS] ... KUNCI: A, C</div>
                  <div>3. [BS] [BOBOT: 1] [LEVEL: LOTS] ... KUNCI: A</div>
                  <div>4. [MENJODOHKAN] [BOBOT: 3] ... A. Premis =&gt; Pasangan</div>
                  <div>5. [ISIAN] [BOBOT: 3] [LEVEL: MOTS] ... KUNCI: Jawaban Singkat</div>
                  <div>6. [URAIAN] [BOBOT: 4] [LEVEL: HOTS] ... PEMBAHASAN: Uraian Kunci</div>
                </div>
              </div>

              {!importJob ? (
                <div className="space-y-4 pt-1">
                  <div className="border-2 border-dashed border-slate-200 rounded-xl p-6 text-center hover:border-emerald-400 transition-colors bg-white">
                    <UploadCloud className="h-10 w-10 text-emerald-500 mx-auto mb-2" />
                    <label className="cursor-pointer">
                      <span className="text-xs font-semibold text-emerald-700 hover:underline">
                        Pilih berkas DOCX dari komputer Anda
                      </span>
                      <input
                        type="file"
                        accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                        className="hidden"
                        onChange={(e) => {
                          const file = e.target.files?.[0];
                          if (file) setImportFile(file);
                        }}
                      />
                    </label>
                    <p className="text-[11px] text-slate-400 mt-1">Format .docx saja, ukuran maksimal 10 MB.</p>
                  </div>

                  {importFile && (
                    <div className="flex items-center justify-between p-2.5 bg-emerald-50 border border-emerald-200 rounded-lg text-xs">
                      <div className="flex items-center gap-2 text-emerald-900 font-medium">
                        <FileText className="h-4 w-4 text-emerald-600" />
                        <span>{importFile.name}</span>
                        <span className="text-[10px] text-emerald-600">
                          ({(importFile.size / 1024).toFixed(1)} KB)
                        </span>
                      </div>
                      <button
                        onClick={() => setImportFile(null)}
                        className="text-slate-400 hover:text-rose-600 text-xs"
                      >
                        Batal
                      </button>
                    </div>
                  )}

                  <div className="flex justify-end pt-2">
                    <Button
                      onClick={handleStartImport}
                      disabled={!importFile || isUploadingImport}
                      className="bg-emerald-600 hover:bg-emerald-500 text-white text-xs gap-1.5"
                    >
                      {isUploadingImport ? (
                        <>
                          <Loader2 className="h-3.5 w-3.5 animate-spin" />
                          Mengekstrak Soal...
                        </>
                      ) : (
                        <>
                          <UploadCloud className="h-3.5 w-3.5" />
                          Ekstrak & Pratinjau Naskah
                        </>
                      )}
                    </Button>
                  </div>
                </div>
              ) : (
                <div className="space-y-3">
                  <div className="flex items-center justify-between p-2.5 bg-blue-50 border border-blue-200 rounded-lg text-xs">
                    <div className="flex items-center gap-2">
                      <CheckCircle2 className="h-4 w-4 text-blue-600" />
                      <span className="font-semibold text-blue-900">
                        {importJob.parsed_payload?.questions?.length || 0} Butir Soal Terdeteksi
                      </span>
                    </div>
                    <Badge variant="outline" className="bg-white text-blue-700 text-[10px]">
                      Status: {importJob.status}
                    </Badge>
                  </div>

                  {importJob.parse_errors?.length > 0 && (
                    <div className="p-3 bg-rose-50 border border-rose-200 rounded-lg text-xs space-y-1 text-rose-800">
                      <div className="font-semibold flex items-center gap-1.5">
                        <AlertCircle className="h-3.5 w-3.5 text-rose-600" />
                        Ditemukan {importJob.parse_errors.length} Kesalahan Format:
                      </div>
                      {importJob.parse_errors.map((err: any, idx: number) => (
                        <div key={idx} className="text-[11px] ml-5 list-disc">
                          • Soal #{err.item_number}: {err.error}
                        </div>
                      ))}
                    </div>
                  )}

                  {/* List Soal Hasil Parsing */}
                  <div className="max-h-64 overflow-y-auto space-y-2 border border-slate-200 rounded-lg p-2 bg-slate-50">
                    {importJob.parsed_payload?.questions?.map((q: any, idx: number) => (
                      <div key={idx} className="bg-white p-2.5 rounded border border-slate-200 text-xs space-y-1.5">
                        <div className="flex items-center justify-between">
                          <div className="flex items-center gap-1.5">
                            <span className="font-bold text-slate-800">#{idx + 1}</span>
                            <Badge variant="outline" className="text-[10px] uppercase font-mono bg-slate-50">
                              {q.question_type}
                            </Badge>
                            <Badge variant="outline" className="text-[10px] bg-slate-50">
                              Level: {q.cognitive_level || 'MOTS'}
                            </Badge>
                          </div>
                          <span className="text-[11px] text-slate-500 font-medium">Bobot: {q.weight}</span>
                        </div>
                        <p className="text-slate-800 line-clamp-2 text-[11px]">{q.body?.content || '-'}</p>
                        {q.options && q.options.length > 0 && (
                          <div className="text-[10px] text-slate-500 flex flex-wrap gap-1.5 pt-1 border-t border-slate-100">
                            {q.options.map((opt: any, oIdx: number) => (
                              <span
                                key={oIdx}
                                className={`px-1.5 py-0.5 rounded ${
                                  opt.is_correct
                                    ? 'bg-emerald-100 text-emerald-800 font-semibold border border-emerald-300'
                                    : 'bg-slate-100 text-slate-600'
                                }`}
                              >
                                {opt.label}: {opt.body?.text || opt.text}
                              </span>
                            ))}
                          </div>
                        )}
                        {q.explanation && (
                          <div className="text-[10px] text-slate-500 italic bg-amber-50/60 p-1 rounded border border-amber-100">
                            Pembahasan: {q.explanation}
                          </div>
                        )}
                      </div>
                    ))}
                  </div>

                  <div className="flex items-center justify-between pt-2 border-t">
                    <Button
                      type="button"
                      variant="outline"
                      size="sm"
                      onClick={() => {
                        setImportJob(null);
                        setImportFile(null);
                      }}
                      className="text-xs"
                    >
                      Pilih Dokumen Lain
                    </Button>
                    <Button
                      type="button"
                      size="sm"
                      onClick={handleCommitImport}
                      disabled={isCommittingImport || (importJob.parse_errors?.length || 0) > 0}
                      className="bg-emerald-600 hover:bg-emerald-500 text-white text-xs gap-1.5"
                    >
                      {isCommittingImport ? (
                        <>
                          <Loader2 className="h-3.5 w-3.5 animate-spin" />
                          Menyimpan ke Bank Soal...
                        </>
                      ) : (
                        <>
                          <Save className="h-3.5 w-3.5" />
                          Komit & Simpan ke Bank Soal
                        </>
                      )}
                    </Button>
                  </div>
                </div>
              )}
            </div>
          </div>
        </div>
      )}

      {/* Modal: Tambah / Edit Butir Soal (6 Format) */}
      {isQuestionModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 overflow-y-auto">
          <div className="w-full max-w-2xl rounded-xl bg-white p-6 shadow-xl space-y-4 my-8">
            <div className="flex items-center justify-between border-b pb-3">
              <h3 className="font-bold text-slate-900 text-sm">
                {editingQuestion ? 'Edit Butir Soal' : 'Tambah Butir Soal Baru'}
              </h3>
              <button onClick={() => setIsQuestionModalOpen(false)} className="text-slate-400 hover:text-slate-600">
                <X className="h-4 w-4" />
              </button>
            </div>

            <form onSubmit={handleSaveQuestion} className="space-y-4">
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="text-xs font-semibold text-slate-700">Format Model Soal *</label>
                  <select
                    value={questionForm.question_type}
                    onChange={(e) => {
                      const newType = e.target.value as QuestionType;
                      let initialOptions = [...questionForm.options];
                      if (newType === 'bs') {
                        initialOptions = [
                          { label: 'Benar', text: 'Pernyataan Benar', is_correct: true },
                          { label: 'Salah', text: 'Pernyataan Salah', is_correct: false },
                        ];
                      } else if (newType === 'isian' || newType === 'uraian') {
                        initialOptions = [];
                      } else if (newType === 'pg' || newType === 'pg_kompleks') {
                        initialOptions = [
                          { label: 'A', text: '', is_correct: true },
                          { label: 'B', text: '', is_correct: false },
                          { label: 'C', text: '', is_correct: false },
                          { label: 'D', text: '', is_correct: false },
                        ];
                      }
                      setQuestionForm({ ...questionForm, question_type: newType, options: initialOptions });
                    }}
                    className="w-full text-xs rounded-md border border-slate-300 p-2 mt-1"
                  >
                    <option value="pg">Pilihan Ganda Tunggal (PG)</option>
                    <option value="pg_kompleks">Pilihan Ganda Kompleks (Multi-Opsi)</option>
                    <option value="bs">Benar / Salah (BS)</option>
                    <option value="menjodohkan">Menjodohkan (Matching)</option>
                    <option value="isian">Isian Singkat</option>
                    <option value="uraian">Uraian / Esai</option>
                  </select>
                </div>

                <div>
                  <label className="text-xs font-semibold text-slate-700">Bobot Nilai (Poin) *</label>
                  <Input
                    type="number"
                    step="0.1"
                    min="0.1"
                    value={questionForm.weight}
                    onChange={(e) => setQuestionForm({ ...questionForm, weight: parseFloat(e.target.value) || 1.0 })}
                    className="text-xs mt-1"
                    required
                  />
                </div>
              </div>

              <div>
                <label className="text-xs font-semibold text-slate-700">Teks Pertanyaan (Stem) *</label>
                <Textarea
                  rows={4}
                  value={questionForm.stem}
                  onChange={(e) => setQuestionForm({ ...questionForm, stem: e.target.value })}
                  placeholder="Tuliskan butir soal atau narasi permasalahan di sini..."
                  className="text-xs mt-1"
                  required
                />
              </div>

              {/* Upload Gambar Stimulus / Ilustrasi Soal */}
              <div className="space-y-1.5 p-3 rounded-lg border border-dashed border-slate-200 bg-slate-50/70">
                <div className="flex items-center justify-between">
                  <label className="text-xs font-semibold text-slate-700 flex items-center gap-1.5">
                    <ImageIcon className="h-3.5 w-3.5 text-blue-600" />
                    Gambar / Ilustrasi Stimulus Soal (Opsional)
                  </label>
                  {questionForm.media_url && (
                    <button
                      type="button"
                      onClick={() => setQuestionForm({ ...questionForm, media_url: '' })}
                      className="text-xs text-rose-600 hover:text-rose-700 font-medium flex items-center gap-1"
                    >
                      <Trash2 className="h-3 w-3" /> Hapus Gambar
                    </button>
                  )}
                </div>

                {questionForm.media_url ? (
                  <div className="relative mt-2 p-2 bg-white rounded-lg border border-slate-200">
                    <img
                      src={questionForm.media_url}
                      alt="Preview Stimulus"
                      className="max-h-48 rounded object-contain mx-auto"
                    />
                    <p className="text-[11px] text-slate-400 mt-1 truncate text-center">
                      {questionForm.media_url}
                    </p>
                  </div>
                ) : (
                  <div className="flex flex-col sm:flex-row items-center gap-2 mt-1">
                    <label className="cursor-pointer inline-flex items-center justify-center gap-1.5 px-3 py-1.5 bg-white border border-slate-300 rounded-md text-xs font-medium text-slate-700 hover:bg-slate-50 transition shadow-xs w-full sm:w-auto">
                      {isUploadingImage ? (
                        <>
                          <Loader2 className="h-3.5 w-3.5 animate-spin text-blue-600" />
                          <span>Mengunggah...</span>
                        </>
                      ) : (
                        <>
                          <UploadCloud className="h-3.5 w-3.5 text-slate-500" />
                          <span>Unggah Gambar (JPG/PNG/WebP)</span>
                        </>
                      )}
                      <input
                        type="file"
                        accept="image/png,image/jpeg,image/webp,image/gif"
                        className="hidden"
                        disabled={isUploadingImage}
                        onChange={handleImageUpload}
                      />
                    </label>
                    <span className="text-xs text-slate-400">atau tautan URL:</span>
                    <Input
                      type="url"
                      placeholder="https://..."
                      value={questionForm.media_url || ''}
                      onChange={(e) => setQuestionForm({ ...questionForm, media_url: e.target.value })}
                      className="text-xs flex-1 h-8"
                    />
                  </div>
                )}
                <p className="text-[11px] text-slate-400">
                  Mendukung gambar grafik, diagram geometri, peta, atau bacaan ilustrasi (maksimal 5MB).
                </p>
              </div>

              {/* Dynamic Option Builder based on Type */}
              <div className="space-y-2 border-t pt-3">
                <div className="flex items-center justify-between">
                  <label className="text-xs font-bold text-slate-800">
                    {questionForm.question_type === 'isian'
                      ? 'Kunci Jawaban Isian'
                      : questionForm.question_type === 'uraian'
                      ? 'Rubrik Penilaian Esai'
                      : 'Opsi Jawaban & Kunci'}
                  </label>
                  {(questionForm.question_type === 'pg' || questionForm.question_type === 'pg_kompleks') && (
                    <Button
                      type="button"
                      size="sm"
                      variant="ghost"
                      onClick={() => {
                        const nextLabel = String.fromCharCode(65 + questionForm.options.length);
                        setQuestionForm({
                          ...questionForm,
                          options: [...questionForm.options, { label: nextLabel, text: '', is_correct: false }],
                        });
                      }}
                      className="text-xs text-blue-600 h-7"
                    >
                      + Tambah Opsi
                    </Button>
                  )}
                </div>

                {/* PG & PG Kompleks Options */}
                {(questionForm.question_type === 'pg' || questionForm.question_type === 'pg_kompleks') && (
                  <div className="space-y-2">
                    {questionForm.options.map((opt, i) => (
                      <div key={i} className="flex items-center gap-2">
                        {questionForm.question_type === 'pg' ? (
                          <input
                            type="radio"
                            name="correct_option"
                            checked={opt.is_correct}
                            onChange={() => {
                              const updated = questionForm.options.map((o, idx) => ({
                                ...o,
                                is_correct: idx === i,
                              }));
                              setQuestionForm({ ...questionForm, options: updated });
                            }}
                            className="h-4 w-4 text-blue-600"
                            title="Centang sebagai kunci jawaban benar"
                          />
                        ) : (
                          <input
                            type="checkbox"
                            checked={opt.is_correct}
                            onChange={(e) => {
                              const updated = [...questionForm.options];
                              updated[i].is_correct = e.target.checked;
                              setQuestionForm({ ...questionForm, options: updated });
                            }}
                            className="h-4 w-4 text-blue-600 rounded"
                            title="Centang jika merupakan salah satu jawaban benar"
                          />
                        )}
                        <span className="font-bold text-xs w-5">{opt.label}.</span>
                        <Input
                          value={opt.text}
                          onChange={(e) => {
                            const updated = [...questionForm.options];
                            updated[i].text = e.target.value;
                            setQuestionForm({ ...questionForm, options: updated });
                          }}
                          placeholder={`Teks pilihan ${opt.label}`}
                          className="text-xs flex-1"
                          required
                        />
                      </div>
                    ))}
                  </div>
                )}

                {/* Benar / Salah Options */}
                {questionForm.question_type === 'bs' && (
                  <div className="grid grid-cols-2 gap-3">
                    {questionForm.options.map((opt, i) => (
                      <label
                        key={i}
                        className={`p-3 rounded-lg border flex items-center justify-between cursor-pointer ${
                          opt.is_correct ? 'border-emerald-500 bg-emerald-50' : 'border-slate-200'
                        }`}
                      >
                        <span className="text-xs font-semibold">{opt.label}</span>
                        <input
                          type="radio"
                          name="bs_correct"
                          checked={opt.is_correct}
                          onChange={() => {
                            const updated = questionForm.options.map((o, idx) => ({
                              ...o,
                              is_correct: idx === i,
                            }));
                            setQuestionForm({ ...questionForm, options: updated });
                          }}
                        />
                      </label>
                    ))}
                  </div>
                )}

                {/* Menjodohkan (Premise & Match Key) */}
                {questionForm.question_type === 'menjodohkan' && (
                  <div className="space-y-2">
                    {questionForm.options.map((opt, i) => (
                      <div key={i} className="grid grid-cols-2 gap-2">
                        <Input
                          value={opt.text}
                          onChange={(e) => {
                            const updated = [...questionForm.options];
                            updated[i].text = e.target.value;
                            setQuestionForm({ ...questionForm, options: updated });
                          }}
                          placeholder={`Premis ${i + 1}`}
                          className="text-xs"
                          required
                        />
                        <Input
                          value={opt.match_key || ''}
                          onChange={(e) => {
                            const updated = [...questionForm.options];
                            updated[i].match_key = e.target.value;
                            setQuestionForm({ ...questionForm, options: updated });
                          }}
                          placeholder={`Pasangan Cocok ${i + 1}`}
                          className="text-xs"
                          required
                        />
                      </div>
                    ))}
                    <Button
                      type="button"
                      size="sm"
                      variant="outline"
                      onClick={() => {
                        setQuestionForm({
                          ...questionForm,
                          options: [
                            ...questionForm.options,
                            { label: `${questionForm.options.length + 1}`, text: '', match_key: '' },
                          ],
                        });
                      }}
                      className="text-xs"
                    >
                      + Tambah Pasangan Jodoh
                    </Button>
                  </div>
                )}

                {/* Isian Singkat */}
                {questionForm.question_type === 'isian' && (
                  <div className="space-y-2">
                    <p className="text-[11px] text-slate-500">
                      Siswa mengetikkan jawaban singkat. Koreksi otomatis akan mencocokkan string jawaban tanpa case sensitive.
                    </p>
                    <Input
                      placeholder="Masukkan frasa atau kata kunci jawaban benar..."
                      value={questionForm.options[0]?.text || ''}
                      onChange={(e) => {
                        setQuestionForm({
                          ...questionForm,
                          options: [{ label: '1', text: e.target.value, is_correct: true }],
                        });
                      }}
                      className="text-xs"
                      required
                    />
                  </div>
                )}

                {/* Uraian */}
                {questionForm.question_type === 'uraian' && (
                  <div className="space-y-2">
                    <p className="text-[11px] text-slate-500">
                      Soal esai akan dinilai secara manual oleh guru melalui menu Koreksi Uraian CBT.
                    </p>
                    <Textarea
                      rows={3}
                      placeholder="Panduan jawaban benar / rubrik penskoran untuk korektor..."
                      value={questionForm.options[0]?.text || ''}
                      onChange={(e) => {
                        setQuestionForm({
                          ...questionForm,
                          options: [{ label: 'rubrik', text: e.target.value, is_correct: true }],
                        });
                      }}
                      className="text-xs"
                    />
                  </div>
                )}
              </div>

              <div className="flex items-center justify-end gap-2 pt-3 border-t">
                <Button type="button" variant="outline" size="sm" onClick={() => setIsQuestionModalOpen(false)} disabled={isSavingQuestion} className="text-xs">
                  Batal
                </Button>
                <Button type="submit" size="sm" disabled={isSavingQuestion} className="bg-blue-600 hover:bg-blue-500 text-white text-xs gap-1.5">
                  {isSavingQuestion ? (
                    <>
                      <Loader2 className="h-3.5 w-3.5 animate-spin" />
                      Menyimpan...
                    </>
                  ) : (
                    <>
                      <Save className="h-3.5 w-3.5" />
                      Simpan Butir Soal
                    </>
                  )}
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Dialog Konfirmasi Hapus Soal */}
      <ConfirmDialog
        isOpen={!!questionToDelete}
        onClose={() => setQuestionToDelete(null)}
        onConfirm={handleConfirmDeleteQuestion}
        title="Hapus Butir Soal"
        description="Apakah Anda yakin ingin menghapus butir soal ini dari bank soal?"
        confirmLabel="Ya, Hapus"
        cancelLabel="Batal"
        variant="danger"
        isLoading={isDeletingQuestion}
      />
    </div>
  );
}
