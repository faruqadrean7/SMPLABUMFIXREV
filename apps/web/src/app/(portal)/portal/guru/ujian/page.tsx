'use client';

import React, { useState, useEffect } from 'react';
import Link from 'next/link';
import { useTeacherExams, guruApi, TeacherQuestionBankItem } from '@/features/portal/guru';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { LoadingSpinner } from '@/components/shared/loading-spinner';
import { EmptyState } from '@/components/shared/empty-state';
import {
  Monitor,
  ArrowLeft,
  Clock,
  CheckCircle2,
  ShieldAlert,
  UserCheck,
  Calendar,
  AlertCircle,
  Eye,
  Plus,
  BookOpen,
  FolderPlus,
  FileQuestion,
  Layers,
  Sparkles,
  X,
} from 'lucide-react';

export default function TeacherExamsPage() {
  const { data: exams = [], isLoading: isLoadingExams } = useTeacherExams();
  const [activeTab, setActiveTab] = useState<'jadwal' | 'bank_soal'>('jadwal');

  // Bank Soal States
  const [banks, setBanks] = useState<TeacherQuestionBankItem[]>([]);
  const [isLoadingBanks, setIsLoadingBanks] = useState(false);
  const [isBankModalOpen, setIsBankModalOpen] = useState(false);
  const [isQuestionModalOpen, setIsQuestionModalOpen] = useState(false);
  const [selectedBankId, setSelectedBankId] = useState<string | null>(null);

  // Form Bank Soal Baru
  const [bankName, setBankName] = useState('');
  const [feedbackMsg, setFeedbackMsg] = useState<string | null>(null);
  const [errorMsg, setErrorMsg] = useState<string | null>(null);

  // Form Soal Baru
  const [questionType, setQuestionType] = useState('pg');
  const [questionText, setQuestionText] = useState('');
  const [cognitiveLevel, setCognitiveLevel] = useState('C2');
  const [difficulty, setDifficulty] = useState(2);

  const fetchBanks = async () => {
    setIsLoadingBanks(true);
    try {
      const data = await guruApi.getQuestionBanks();
      setBanks(data);
    } catch {
      // Fallback
    } finally {
      setIsLoadingBanks(false);
    }
  };

  useEffect(() => {
    if (activeTab === 'bank_soal') {
      fetchBanks();
    }
  }, [activeTab]);

  const handleCreateBank = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!bankName.trim()) return;
    setErrorMsg(null);
    try {
      const newBank = await guruApi.createQuestionBank({
        name: bankName,
        subject_id: '00000000-0000-0000-0000-000000000001', // Fallback handled by backend
      });
      setBanks((prev) => [newBank, ...prev]);
      setBankName('');
      setIsBankModalOpen(false);
      setFeedbackMsg('Bank soal baru berhasil ditambahkan.');
      setTimeout(() => setFeedbackMsg(null), 3000);
    } catch (err: any) {
      setErrorMsg(err?.message || 'Gagal membuat bank soal.');
    }
  };

  const handleCreateQuestion = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedBankId || !questionText.trim()) return;
    try {
      await guruApi.createQuestion(selectedBankId, {
        question_type: questionType,
        body: { text: questionText },
        cognitive_level: cognitiveLevel,
        difficulty: Number(difficulty),
      });
      setIsQuestionModalOpen(false);
      setQuestionText('');
      setFeedbackMsg('Butir soal berhasil ditambahkan ke bank soal.');
      setTimeout(() => setFeedbackMsg(null), 3000);
      fetchBanks();
    } catch (err: any) {
      setErrorMsg(err?.message || 'Gagal menambahkan soal.');
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
            <Monitor className="h-6 w-6 text-brand-blue" />
            <span>Asesmen Digital & Bank Soal Guru</span>
          </h1>
          <p className="text-xs sm:text-sm text-slate-500 mt-1">
            Pengelolaan butir soal mandiri per mapel, penjadwalan ujian CBT, dan monitoring pengawas lab.
          </p>
        </div>

        <div className="flex items-center gap-2">
          <Badge variant="outline" className="px-3 py-1 font-bold text-xs bg-blue-50 text-brand-blue border-blue-200">
            CBT Terpadu
          </Badge>
        </div>
      </div>

      {feedbackMsg && (
        <div className="p-3 rounded-xl bg-emerald-50 border border-emerald-200 text-status-success text-xs font-semibold flex items-center gap-2">
          <CheckCircle2 className="h-4 w-4 shrink-0" />
          <span>{feedbackMsg}</span>
        </div>
      )}

      {errorMsg && (
        <div className="p-3 rounded-xl bg-red-50 border border-red-200 text-status-danger text-xs font-semibold flex items-center gap-2">
          <AlertCircle className="h-4 w-4 shrink-0" />
          <span>{errorMsg}</span>
        </div>
      )}

      {/* Tabs */}
      <div className="flex items-center gap-2 border-b border-slate-200 pb-2">
        <button
          onClick={() => setActiveTab('jadwal')}
          className={`px-4 py-2 text-xs font-bold rounded-xl transition-all flex items-center gap-2 ${
            activeTab === 'jadwal'
              ? 'bg-brand-blue text-white shadow-xs'
              : 'text-slate-600 hover:bg-slate-100'
          }`}
        >
          <Calendar className="h-4 w-4" />
          <span>Jadwal & Pengawas Ujian</span>
        </button>
        <button
          onClick={() => setActiveTab('bank_soal')}
          className={`px-4 py-2 text-xs font-bold rounded-xl transition-all flex items-center gap-2 ${
            activeTab === 'bank_soal'
              ? 'bg-brand-blue text-white shadow-xs'
              : 'text-slate-600 hover:bg-slate-100'
          }`}
        >
          <FileQuestion className="h-4 w-4" />
          <span>Bank Soal Mandiri</span>
        </button>
      </div>

      {/* TAB 1: JADWAL & PENGAWAS CBT */}
      {activeTab === 'jadwal' && (
        <>
          {isLoadingExams ? (
            <div className="min-h-[250px] flex items-center justify-center bg-white rounded-2xl border border-slate-200">
              <LoadingSpinner label="Memuat jadwal ujian CBT..." size="lg" />
            </div>
          ) : exams.length === 0 ? (
            <EmptyState
              title="Tidak Ada Jadwal Ujian Aktif"
              description="Saat ini belum ada sesi penilaian tengah semester atau ujian harian CBT yang dijadwalkan."
              icon={<Monitor className="h-8 w-8 text-slate-400" />}
            />
          ) : (
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              {exams.map((ex) => (
                <Card
                  key={ex.id}
                  className="border-slate-200 hover:border-brand-blue hover:shadow-xs transition-all flex flex-col justify-between"
                >
                  <div>
                    <CardHeader className="pb-2">
                      <div className="flex items-center justify-between text-xs mb-1">
                        <Badge variant="outline" className="font-bold text-slate-700 bg-slate-50 text-[10px]">
                          {ex.subjectName}
                        </Badge>
                        {ex.isProctor ? (
                          <Badge className="bg-purple-50 text-purple-700 border-purple-200 font-bold text-[10px] gap-1">
                            <UserCheck className="h-3 w-3" />
                            <span>Anda Bertugas Pengawas</span>
                          </Badge>
                        ) : (
                          <Badge className="bg-slate-100 text-slate-600 border-slate-200 text-[10px]">
                            Guru Pengampu
                          </Badge>
                        )}
                      </div>
                      <CardTitle className="text-base font-bold text-slate-900 leading-snug">
                        {ex.title}
                      </CardTitle>
                      <CardDescription className="text-xs text-slate-500 mt-0.5">
                        Sasaran: {ex.classNames.join(', ')}
                      </CardDescription>
                    </CardHeader>

                    <CardContent className="pt-2 text-xs space-y-2 border-t border-slate-100 mt-2">
                      <div className="grid grid-cols-2 gap-2 bg-slate-50 p-2.5 rounded-xl border border-slate-100 text-[11px]">
                        <div>
                          <span className="text-slate-400 font-medium block">Waktu Sesi:</span>
                          <span className="font-bold text-slate-800">{ex.startTime}</span>
                        </div>
                        <div>
                          <span className="text-slate-400 font-medium block">Durasi & Soal:</span>
                          <span className="font-bold text-slate-800">
                            {ex.durationMinutes} Menit ({ex.totalQuestions} Soal)
                          </span>
                        </div>
                      </div>
                    </CardContent>
                  </div>

                  <div className="p-4 pt-0 mt-2 border-t border-slate-100 flex items-center justify-between">
                    <span className="text-[11px] text-slate-500">
                      Status: <strong className="text-brand-blue capitalize">{ex.status}</strong>
                    </span>
                    {ex.isProctor && (
                      <Badge className="bg-brand-blue text-white font-bold text-[11px] py-1 px-3">
                        Buka Ruang Pengawas di Lab
                      </Badge>
                    )}
                  </div>
                </Card>
              ))}
            </div>
          )}
        </>
      )}

      {/* TAB 2: BANK SOAL MANDIRI */}
      {activeTab === 'bank_soal' && (
        <div className="space-y-4">
          <div className="flex items-center justify-between">
            <div>
              <h2 className="text-base font-bold text-slate-900">Repositori Bank Soal Pendidik</h2>
              <p className="text-xs text-slate-500">
                Buat dan kelola butir soal evaluasi pembelajaran untuk mata pelajaran yang Anda ampu.
              </p>
            </div>
            <Button
              onClick={() => setIsBankModalOpen(true)}
              className="h-9 px-3 text-xs font-bold gap-1.5 bg-brand-blue hover:bg-brand-blue/90"
            >
              <Plus className="h-4 w-4" />
              <span>Buat Bank Soal Baru</span>
            </Button>
          </div>

          {isLoadingBanks ? (
            <div className="min-h-[200px] flex items-center justify-center bg-white rounded-2xl border border-slate-200">
              <LoadingSpinner label="Memuat bank soal..." size="md" />
            </div>
          ) : banks.length === 0 ? (
            <EmptyState
              title="Belum Ada Bank Soal Mandiri"
              description="Klik tombol 'Buat Bank Soal Baru' di atas untuk menyusun instrumen penilaian mapel Anda."
              icon={<FileQuestion className="h-8 w-8 text-slate-400" />}
            />
          ) : (
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              {banks.map((bank) => (
                <Card key={bank.id} className="border-slate-200 hover:shadow-xs transition-all">
                  <CardHeader className="pb-2">
                    <div className="flex items-center justify-between text-xs mb-1">
                      <Badge variant="outline" className="text-[10px] font-bold bg-slate-50 text-slate-700">
                        {bank.subject?.name || 'Mata Pelajaran'}
                      </Badge>
                      <span className="text-[11px] text-slate-400 font-semibold">
                        {bank.questions_count ?? 0} Butir Soal
                      </span>
                    </div>
                    <CardTitle className="text-sm font-bold text-slate-900">
                      {bank.name}
                    </CardTitle>
                  </CardHeader>
                  <CardContent className="pt-2 border-t border-slate-100 flex items-center justify-between">
                    <Button
                      size="sm"
                      variant="outline"
                      className="h-8 text-xs font-bold gap-1"
                      onClick={() => {
                        setSelectedBankId(bank.id);
                        setIsQuestionModalOpen(true);
                      }}
                    >
                      <Plus className="h-3 w-3" />
                      <span>Tambah Soal</span>
                    </Button>
                  </CardContent>
                </Card>
              ))}
            </div>
          )}
        </div>
      )}

      {/* Modal Bank Soal Baru */}
      {isBankModalOpen && (
        <div className="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl p-6 max-w-md w-full space-y-4 shadow-xl border border-slate-100">
            <div className="flex items-center justify-between border-b border-slate-100 pb-3">
              <h3 className="text-base font-bold text-slate-900 flex items-center gap-2">
                <FolderPlus className="h-5 w-5 text-brand-blue" />
                <span>Buat Bank Soal Baru</span>
              </h3>
              <button onClick={() => setIsBankModalOpen(false)} className="text-slate-400 hover:text-slate-600">
                <X className="h-5 w-5" />
              </button>
            </div>
            <form onSubmit={handleCreateBank} className="space-y-4">
              <div>
                <label className="block text-xs font-bold text-slate-700 mb-1">Nama Bank Soal</label>
                <Input
                  value={bankName}
                  onChange={(e) => setBankName(e.target.value)}
                  placeholder="Contoh: Bank Soal Aljabar Linear Kelas 7"
                  className="text-xs"
                  required
                />
              </div>
              <div className="flex justify-end gap-2 pt-2">
                <Button type="button" variant="outline" size="sm" onClick={() => setIsBankModalOpen(false)}>
                  Batal
                </Button>
                <Button type="submit" size="sm" className="bg-brand-blue text-white font-bold">
                  Simpan Bank Soal
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Modal Tambah Soal */}
      {isQuestionModalOpen && (
        <div className="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl p-6 max-w-lg w-full space-y-4 shadow-xl border border-slate-100">
            <div className="flex items-center justify-between border-b border-slate-100 pb-3">
              <h3 className="text-base font-bold text-slate-900 flex items-center gap-2">
                <FileQuestion className="h-5 w-5 text-brand-blue" />
                <span>Tambah Butir Soal Evaluasi</span>
              </h3>
              <button onClick={() => setIsQuestionModalOpen(false)} className="text-slate-400 hover:text-slate-600">
                <X className="h-5 w-5" />
              </button>
            </div>
            <form onSubmit={handleCreateQuestion} className="space-y-4 text-xs">
              <div className="grid grid-cols-3 gap-2">
                <div>
                  <label className="block font-bold text-slate-700 mb-1">Tipe Soal</label>
                  <select
                    value={questionType}
                    onChange={(e) => setQuestionType(e.target.value)}
                    className="w-full h-9 rounded-md border border-slate-200 px-2 text-xs"
                  >
                    <option value="pg">Pilihan Ganda</option>
                    <option value="pg_kompleks">PG Kompleks</option>
                    <option value="bs">Benar / Salah</option>
                    <option value="menjodohkan">Menjodohkan</option>
                    <option value="isian">Isian Singkat</option>
                    <option value="uraian">Uraian / Esai</option>
                  </select>
                </div>
                <div>
                  <label className="block font-bold text-slate-700 mb-1">Taksonomi Bloom</label>
                  <select
                    value={cognitiveLevel}
                    onChange={(e) => setCognitiveLevel(e.target.value)}
                    className="w-full h-9 rounded-md border border-slate-200 px-2 text-xs"
                  >
                    <option value="C1">C1 - Mengingat</option>
                    <option value="C2">C2 - Memahami</option>
                    <option value="C3">C3 - Menerapkan</option>
                    <option value="C4">C4 - Menganalisis</option>
                    <option value="C5">C5 - Mengevaluasi</option>
                    <option value="C6">C6 - Mencipta</option>
                  </select>
                </div>
                <div>
                  <label className="block font-bold text-slate-700 mb-1">Tingkat Kesulitan</label>
                  <select
                    value={difficulty}
                    onChange={(e) => setDifficulty(Number(e.target.value))}
                    className="w-full h-9 rounded-md border border-slate-200 px-2 text-xs"
                  >
                    <option value={1}>1 - Sangat Mudah</option>
                    <option value={2}>2 - Mudah</option>
                    <option value={3}>3 - Sedang</option>
                    <option value={4}>4 - Sulit</option>
                    <option value={5}>5 - Sangat Sulit</option>
                  </select>
                </div>
              </div>
              <div>
                <label className="block font-bold text-slate-700 mb-1">Teks Pertanyaan</label>
                <Textarea
                  value={questionText}
                  onChange={(e) => setQuestionText(e.target.value)}
                  placeholder="Tuliskan stimulus atau instruksi pertanyaan soal di sini..."
                  rows={4}
                  required
                />
              </div>
              <div className="flex justify-end gap-2 pt-2">
                <Button type="button" variant="outline" size="sm" onClick={() => setIsQuestionModalOpen(false)}>
                  Batal
                </Button>
                <Button type="submit" size="sm" className="bg-brand-blue text-white font-bold">
                  Simpan Butir Soal
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
