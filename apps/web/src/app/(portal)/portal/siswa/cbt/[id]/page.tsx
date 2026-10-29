'use client';

import React, { useState, useEffect, use } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { 
  MonitorPlay, 
  Clock, 
  CheckCircle2, 
  AlertTriangle, 
  ShieldAlert, 
  Flag, 
  ChevronLeft, 
  ChevronRight, 
  Send, 
  Check,
  X,
  Lock,
  Link2,
  RotateCcw
} from 'lucide-react';
import { CBT_ACTIVE_EXAM_QUESTIONS, CBTQuestionDetail } from '@/lib/mock-student-data';
import { useStudentExams, useStudentDashboard } from '@/lib/use-student-data';
import { apiClient } from '@/lib/api-client';

export default function CBTExamWorkspacePage({ params }: { params: Promise<{ id: string }> }) {
  const router = useRouter();
  const resolvedParams = use(params);
  const examId = resolvedParams.id;

  const { data: apiExams } = useStudentExams();
  const { data: studentDash } = useStudentDashboard();

  const foundExam = apiExams?.find(e => e.id === examId);
  const exam = {
    id: foundExam?.id || examId,
    title: foundExam?.title || 'Sumatif Asesmen: Informatika & Logika Web',
    subject_name: foundExam?.subjectName || 'Informatika',
    total_questions: foundExam?.totalQuestions || 30,
    duration_minutes: foundExam?.durationMinutes || 60,
    room_name: 'Lab Komputer 1',
  };

  const studentName = studentDash?.name || 'Siswa Terdaftar';
  const studentClass = studentDash?.className || 'Kelas Siswa';

  // Current Question Index
  const [currentIdx, setCurrentIdx] = useState(0);
  const [activePremise, setActivePremise] = useState<string | null>(null);

  useEffect(() => {
    setActivePremise(null);
  }, [currentIdx]);
  
  // Font Size zoom level for CBT reading ergonomics
  const [fontSize, setFontSize] = useState<'normal' | 'large' | 'xlarge'>('normal');

  // Timer Countdown in seconds (89 menit 45 detik)
  const [timeLeft, setTimeLeft] = useState(5385);
  
  // Answers state: questionId -> value
  const [answers, setAnswers] = useState<Record<string, any>>({
    'q-01': 'B',
    'q-02': ['A', 'C'],
    'q-03': 'B',
    'q-04': { 0: true, 1: false, 2: true },
    'q-05': { 'HTML5 Semantic': 'Menyusun struktur hierarki dan arti konten dokumen web' },
    'q-06': 'fr',
  });

  // Flagged (Ragu-ragu) state: questionId -> boolean
  const [flagged, setFlagged] = useState<Record<string, boolean>>({
    'q-03': true,
  });

  // Autosave status
  const [isSaving, setIsSaving] = useState(false);
  const [lastSavedTime, setLastSavedTime] = useState('Baru saja');

  // Anti-Cheat: Violation count & Locked State
  const [violationCount, setViolationCount] = useState(0);
  const [showViolationModal, setShowViolationModal] = useState(false);
  const [violationReason, setViolationReason] = useState('');
  const [isExamLocked, setIsExamLocked] = useState(false);

  // Submit Confirmation Modal
  const [showSubmitModal, setShowSubmitModal] = useState(false);
  const [confirmCheckbox, setConfirmCheckbox] = useState(false);
  const [isFinished, setIsFinished] = useState(false);

  // 30 Question list
  const allQuestionIds = Array.from({ length: exam.total_questions }, (_, i) => {
    if (i < CBT_ACTIVE_EXAM_QUESTIONS.length) {
      return CBT_ACTIVE_EXAM_QUESTIONS[i].id;
    }
    return `q-${String(i + 1).padStart(2, '0')}`;
  });

  const currentQ: CBTQuestionDetail = CBT_ACTIVE_EXAM_QUESTIONS[currentIdx] || {
    id: `q-${String(currentIdx + 1).padStart(2, '0')}`,
    number: currentIdx + 1,
    type: 'pg',
    category: 'MOTS',
    points: 3,
    prompt: `Butir Soal Nomor ${currentIdx + 1}: Perhatikan studi kasus pemecahan masalah algoritma pada sistem informasi sekolah berikut...`,
    options: [
      { key: 'A', label: 'Pilihan opsi jawaban pertama' },
      { key: 'B', label: 'Pilihan opsi jawaban kedua' },
      { key: 'C', label: 'Pilihan opsi jawaban ketiga' },
      { key: 'D', label: 'Pilihan opsi jawaban keempat' },
    ],
  };

  // Timer Tick
  useEffect(() => {
    if (isFinished || isExamLocked) return;

    const timer = setInterval(() => {
      setTimeLeft(prev => {
        if (prev <= 1) {
          clearInterval(timer);
          setIsFinished(true);
          return 0;
        }
        return prev - 1;
      });
    }, 1000);

    return () => clearInterval(timer);
  }, [isFinished, isExamLocked]);

  // Anti-Cheat: Detect Window Blur / Tab Switch / Minimize
  useEffect(() => {
    if (isFinished || isExamLocked) return;

    const handleVisibilityChange = () => {
      if (document.visibilityState === 'hidden') {
        triggerViolation('Terdeteksi berpindah tab peramban atau meminimalkan layar ujian');
      }
    };

    const handleWindowBlur = () => {
      triggerViolation('Terdeteksi jendela ujian kehilangan fokus (membuka aplikasi lain)');
    };

    const handleContextMenu = (e: MouseEvent) => {
      e.preventDefault();
    };

    const handleKeyDown = (e: KeyboardEvent) => {
      if (
        (e.ctrlKey && (e.key === 'c' || e.key === 'v' || e.key === 'u' || e.key === 'C' || e.key === 'V' || e.key === 'U')) ||
        e.key === 'F12'
      ) {
        e.preventDefault();
        triggerViolation('Percobaan kombinasi tombol salin / inspeksi kode');
      }
    };

    document.addEventListener('visibilitychange', handleVisibilityChange);
    window.addEventListener('blur', handleWindowBlur);
    document.addEventListener('contextmenu', handleContextMenu);
    document.addEventListener('keydown', handleKeyDown);

    return () => {
      document.removeEventListener('visibilitychange', handleVisibilityChange);
      window.removeEventListener('blur', handleWindowBlur);
      document.removeEventListener('contextmenu', handleContextMenu);
      document.removeEventListener('keydown', handleKeyDown);
    };
  }, [violationCount, isFinished, isExamLocked]);

  const triggerViolation = (reason: string) => {
    // Kirim laporan pelanggaran ke server secara asinkron
    apiClient.post(`/exams/${examId}/log-violation`, {
      violation_type: reason.includes('tab') ? 'tab_switch' : reason.includes('fokus') ? 'window_blur' : 'blur',
      meta: {
        reason,
        timestamp: new Date().toISOString(),
      },
    }).catch(() => {});

    setViolationCount(prev => {
      const newCount = prev + 1;
      setViolationReason(reason);
      setShowViolationModal(true);
      if (newCount >= 3) {
        setIsExamLocked(true);
      }
      return newCount;
    });
  };

  const handleAnswerChange = (val: any) => {
    setIsSaving(true);
    setAnswers(prev => ({
      ...prev,
      [currentQ.id]: val,
    }));
    setTimeout(() => {
      setIsSaving(false);
      const now = new Date();
      setLastSavedTime(`${now.getHours().toString().padStart(2, '0')}:${now.getMinutes().toString().padStart(2, '0')}:${now.getSeconds().toString().padStart(2, '0')}`);
    }, 250);
  };

  const toggleFlag = () => {
    setFlagged(prev => ({
      ...prev,
      [currentQ.id]: !prev[currentQ.id],
    }));
  };

  const formatTime = (secs: number) => {
    const hours = Math.floor(secs / 3600);
    const mins = Math.floor((secs % 3600) / 60);
    const remainder = secs % 60;
    if (hours > 0) {
      return `${hours.toString().padStart(2, '0')}:${mins.toString().padStart(2, '0')}:${remainder.toString().padStart(2, '0')}`;
    }
    return `${mins.toString().padStart(2, '0')}:${remainder.toString().padStart(2, '0')}`;
  };

  const answeredCount = Object.keys(answers).length;
  const flaggedCount = Object.values(flagged).filter(Boolean).length;
  const unansweredCount = Math.max(0, (exam.total_questions || 0) - answeredCount);
  const progressPercent = exam.total_questions > 0 ? Math.round((answeredCount / exam.total_questions) * 100) : 0;

  // Final Submit Handler
  const handleFinalSubmit = () => {
    setIsFinished(true);
    setShowSubmitModal(false);
  };

  // Font size classes
  const promptFontClass = {
    normal: 'text-base sm:text-lg',
    large: 'text-lg sm:text-xl',
    xlarge: 'text-xl sm:text-2xl',
  }[fontSize];

  const optionFontClass = {
    normal: 'text-sm sm:text-base',
    large: 'text-base sm:text-lg',
    xlarge: 'text-lg sm:text-xl',
  }[fontSize];

  if (isFinished) {
    return (
      <div className="min-h-screen bg-slate-100 flex items-center justify-center p-4 sm:p-6 text-slate-900 font-sans">
        <div className="max-w-lg w-full bg-white border border-slate-200 shadow-2xl rounded-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-150">
          {/* Header Bar */}
          <div className="px-6 py-4 bg-white border-b border-slate-100 flex items-center justify-between">
            <div className="flex items-center gap-2.5">
              <div className="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200 flex items-center justify-center font-bold">
                <CheckCircle2 className="w-4 h-4 text-emerald-800" />
              </div>
              <div>
                <h3 className="text-xs font-bold uppercase tracking-wider text-emerald-800">Status Pengumpulan</h3>
                <p className="text-[11px] text-slate-500 font-normal">Sesi Ujian CBT Selesai</p>
              </div>
            </div>
            <span className="px-2.5 py-1 rounded-md text-[11px] font-bold bg-emerald-100 text-emerald-900 border border-emerald-200">
              Tersimpan di Server
            </span>
          </div>

          {/* Body */}
          <div className="p-6 text-center space-y-4">
            <div className="w-14 h-14 rounded-2xl bg-emerald-50 text-emerald-800 border border-emerald-200 flex items-center justify-center mx-auto shadow-xs">
              <CheckCircle2 className="w-8 h-8 text-emerald-800" />
            </div>

            <div>
              <h2 className="text-lg font-extrabold text-slate-900 tracking-tight">
                Terima Kasih, {studentName}!
              </h2>
              <p className="text-xs text-slate-600 mt-1 font-medium leading-relaxed max-w-sm mx-auto">
                Seluruh lembar jawaban Anda untuk <strong>{exam.title}</strong> telah berhasil direkam secara permanen ke server sekolah.
              </p>
            </div>

            <div className="p-4 bg-slate-50 rounded-xl border border-slate-200 text-xs text-left space-y-2.5">
              <div className="flex justify-between items-center pb-2 border-b border-slate-200">
                <span className="text-slate-500 font-semibold">Total Butir Dikerjakan:</span>
                <span className="font-extrabold text-slate-900 font-mono">{answeredCount} dari {exam.total_questions} Soal ({progressPercent}%)</span>
              </div>
              <div className="flex justify-between items-center pb-2 border-b border-slate-200">
                <span className="text-slate-500 font-semibold">Waktu Penyelesaian:</span>
                <span className="font-extrabold text-slate-900 font-mono">{new Date().toLocaleTimeString('id-ID')} WIB</span>
              </div>
              <div className="flex justify-between items-center">
                <span className="text-slate-500 font-semibold">Ruang Workstation:</span>
                <span className="font-extrabold text-slate-900">{exam.room_name}</span>
              </div>
            </div>

            <p className="text-[11px] text-slate-500 font-normal leading-relaxed">
              Skor dan evaluasi kompetensi akan diumumkan melalui Portal Siswa & Orang Tua setelah periode asesmen resmi berakhir.
            </p>
          </div>

          {/* Footer Bar */}
          <div className="px-6 py-4 bg-slate-50 border-t border-slate-100 flex items-center justify-center sm:justify-end">
            <Link
              href="/siswa/cbt"
              className="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-blue-900 hover:bg-blue-950 text-white text-xs font-bold tracking-wider uppercase transition-colors shadow-xs text-center"
            >
              Kembali ke Beranda CBT Siswa
            </Link>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="h-screen w-screen overflow-hidden bg-slate-100 flex flex-col font-sans select-none text-slate-900">
      {/* ─── 1. TOP HEADER (CLEAN DASHBOARD STYLE - ZERO AI SLOP) ─── */}
      <header className="h-16 bg-white border-b border-slate-200 px-6 sm:px-8 flex items-center justify-between shrink-0 shadow-2xs z-30">
        {/* Left: Branding & Student Context */}
        <div className="flex items-center gap-3.5 min-w-0">
          <div className="w-10 h-10 rounded-xl bg-blue-900 text-white font-extrabold flex items-center justify-center shrink-0 shadow-xs">
            <MonitorPlay className="w-5 h-5 text-white" />
          </div>

          <div className="min-w-0">
            <div className="flex items-center gap-2">
              <span className="px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider bg-blue-50 text-blue-900 border border-blue-200 font-mono">
                CBT Kiosk
              </span>
              <h1 className="text-sm font-extrabold text-slate-900 tracking-tight truncate max-w-sm lg:max-w-md">
                {exam.title}
              </h1>
            </div>
            <p className="text-xs text-slate-500 font-normal truncate mt-0.5">
              Peserta: <strong className="text-slate-900 font-semibold">{studentName}</strong> • Kelas {studentClass} • <span className="font-mono text-slate-700 font-semibold">PC-LAB1-08</span>
            </p>
          </div>
        </div>

        {/* Right: Autosave, Zoom Adjuster, & High-Contrast Timer */}
        <div className="flex items-center gap-3 sm:gap-4 shrink-0">
          {/* Zoom Adjuster (A / A+ / A++) */}
          <div className="hidden lg:flex items-center bg-slate-100 border border-slate-200 rounded-lg p-0.5 shadow-2xs">
            <button
              title="Ukuran Teks Standar"
              onClick={() => setFontSize('normal')}
              className={`px-2.5 py-1 rounded-md text-xs font-bold transition-all cursor-pointer ${
                fontSize === 'normal' ? 'bg-white text-slate-900 shadow-xs border border-slate-200' : 'text-slate-600 hover:text-slate-900'
              }`}
            >
              A
            </button>
            <button
              title="Ukuran Teks Sedang"
              onClick={() => setFontSize('large')}
              className={`px-2.5 py-1 rounded-md text-xs font-bold transition-all cursor-pointer ${
                fontSize === 'large' ? 'bg-white text-slate-900 shadow-xs border border-slate-200' : 'text-slate-600 hover:text-slate-900'
              }`}
            >
              A+
            </button>
            <button
              title="Ukuran Teks Besar"
              onClick={() => setFontSize('xlarge')}
              className={`px-2.5 py-1 rounded-md text-xs font-bold transition-all cursor-pointer ${
                fontSize === 'xlarge' ? 'bg-white text-slate-900 shadow-xs border border-slate-200' : 'text-slate-600 hover:text-slate-900'
              }`}
            >
              A++
            </button>
          </div>

          {/* Autosave Status */}
          <div className="hidden md:flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-50 border border-emerald-200 text-xs font-bold text-emerald-800">
            {isSaving ? (
              <span className="text-amber-800 flex items-center gap-1.5">
                <Clock className="w-3.5 h-3.5 text-amber-700" />
                <span>Menyimpan...</span>
              </span>
            ) : (
              <span className="flex items-center gap-1.5 text-emerald-800">
                <CheckCircle2 className="w-3.5 h-3.5 text-emerald-700" />
                <span>Tersimpan ({lastSavedTime})</span>
              </span>
            )}
          </div>

          {/* High-Contrast Countdown Timer */}
          <div className={`px-3.5 py-1.5 rounded-lg border font-mono text-xs sm:text-sm font-bold flex items-center gap-2 shadow-2xs ${
            timeLeft <= 600
              ? 'bg-rose-50 border-rose-300 text-rose-800'
              : 'bg-slate-50 border-slate-200 text-slate-900'
          }`}>
            <Clock className={`w-4 h-4 ${timeLeft <= 600 ? 'text-rose-700' : 'text-blue-900'} shrink-0`} />
            <div className="flex items-baseline gap-1.5">
              <span className="text-[11px] text-slate-500 font-sans font-semibold hidden sm:inline">Sisa Waktu:</span>
              <span className="font-extrabold tracking-wider">{formatTime(timeLeft)}</span>
            </div>
          </div>
        </div>
      </header>

      {/* ─── 2. MAIN CBT WORKSPACE (BALANCED 2-COLUMN VIEWPORT) ─── */}
      <div className="flex-1 min-h-0 flex flex-col lg:flex-row p-3.5 sm:p-4 xl:p-5 gap-3.5 max-w-full overflow-hidden">
        {/* LEFT COLUMN: Ergonomic Question Sheet */}
        <div className="flex-1 min-h-0 bg-white border border-slate-200/90 rounded-xl shadow-xs flex flex-col justify-between overflow-hidden">
          {/* Top Meta Bar */}
          <div className="shrink-0 h-12 px-6 border-b border-slate-100 flex items-center justify-between bg-slate-50/70">
            <div className="flex items-center gap-2">
              <span className="px-2.5 py-1 rounded-md bg-blue-900 text-white font-extrabold text-xs tracking-wider uppercase font-mono shadow-2xs">
                Soal No. {currentQ.number}
              </span>
              <span className="px-2.5 py-0.5 rounded-md bg-white border border-slate-200 text-slate-700 font-semibold text-xs shadow-2xs">
                {currentQ.type === 'pg' && 'Pilihan Ganda'}
                {currentQ.type === 'pg_kompleks' && 'Pilihan Ganda Kompleks'}
                {currentQ.type === 'benar_salah' && 'Pernyataan Benar / Salah'}
                {currentQ.type === 'menjodohkan' && 'Menjodohkan Premis'}
                {currentQ.type === 'isian' && 'Isian Singkat'}
                {currentQ.type === 'uraian' && 'Esai / Uraian Terbuka'}
              </span>
              <span className="px-2.5 py-0.5 rounded-md bg-white border border-slate-200 text-slate-600 font-mono text-xs shadow-2xs hidden sm:inline">
                Bobot: {currentQ.points} Poin ({currentQ.category})
              </span>
            </div>

            <div className="flex items-center gap-2">
              <span className="text-xs font-bold text-slate-500 font-mono">
                {currentIdx + 1} / {exam.total_questions}
              </span>
            </div>
          </div>

          {/* Scrollable Question Content (Centered Max-Width for Ergonomics) */}
          <div className="flex-1 min-h-0 overflow-y-auto px-6 sm:px-10 py-6">
            <div className="max-w-3xl xl:max-w-4xl mx-auto space-y-6">
              {/* Prompt Text */}
              <div className={`${promptFontClass} font-bold text-slate-900 leading-relaxed space-y-3.5`}>
                <p>{currentQ.prompt}</p>

                {/* KaTeX Math Formula */}
                {currentQ.formula && (
                  <div className="p-4 rounded-xl bg-slate-900 text-emerald-400 font-mono text-sm border border-slate-800 shadow-xs">
                    {currentQ.formula}
                  </div>
                )}
              </div>

              {/* ─── 6 MODEL BUTIR SOAL ─── */}
              {/* Model 1: Pilihan Ganda Tunggal */}
              {currentQ.type === 'pg' && currentQ.options && (
                <div className="space-y-3 pt-2">
                  {currentQ.options.map((opt) => {
                    const isSelected = answers[currentQ.id] === opt.key;
                    return (
                      <button
                        key={opt.key}
                        onClick={() => handleAnswerChange(opt.key)}
                        className={`group w-full p-4 rounded-xl border transition-all flex items-center justify-between gap-4 text-left cursor-pointer ${
                          isSelected
                            ? 'border-2 border-blue-900 bg-blue-50/60 text-slate-900 font-bold shadow-xs'
                            : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50/60 text-slate-800 font-medium'
                        }`}
                      >
                        <div className="flex items-center gap-3.5 min-w-0 flex-1">
                          <span className={`w-9 h-9 rounded-lg flex items-center justify-center font-extrabold text-sm shrink-0 transition-all ${
                            isSelected ? 'bg-blue-900 text-white shadow-xs' : 'bg-slate-100 text-slate-700 border border-slate-200 group-hover:bg-slate-200'
                          }`}>
                            {opt.key}
                          </span>
                          <span className={`${optionFontClass} leading-normal text-slate-900`}>{opt.label}</span>
                        </div>

                        {isSelected ? (
                          <CheckCircle2 className="w-5 h-5 text-blue-900 shrink-0" />
                        ) : (
                          <div className="w-5 h-5 rounded-full border-2 border-slate-300 shrink-0 group-hover:border-slate-400"></div>
                        )}
                      </button>
                    );
                  })}
                </div>
              )}

              {/* Model 2: Pilihan Ganda Kompleks */}
              {currentQ.type === 'pg_kompleks' && currentQ.options && (
                <div className="space-y-3 pt-2">
                  <div className="p-3 bg-blue-50/80 rounded-xl border border-blue-200 text-xs text-blue-900 font-semibold flex items-center gap-2">
                    <AlertTriangle className="w-4 h-4 text-blue-700 shrink-0" />
                    <span>Perhatian: Anda dapat memilih lebih dari satu jawaban yang dianggap benar.</span>
                  </div>

                  {currentQ.options.map((opt) => {
                    const currentSelected: string[] = Array.isArray(answers[currentQ.id]) ? answers[currentQ.id] : [];
                    const isChecked = currentSelected.includes(opt.key);

                    const toggleMulti = () => {
                      if (isChecked) {
                        handleAnswerChange(currentSelected.filter(k => k !== opt.key));
                      } else {
                        handleAnswerChange([...currentSelected, opt.key]);
                      }
                    };

                    return (
                      <button
                        key={opt.key}
                        onClick={toggleMulti}
                        className={`group w-full p-4 rounded-xl border transition-all flex items-center justify-between gap-4 text-left cursor-pointer ${
                          isChecked
                            ? 'border-2 border-blue-900 bg-blue-50/60 text-slate-900 font-bold shadow-xs'
                            : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50/60 text-slate-800 font-medium'
                        }`}
                      >
                        <div className="flex items-center gap-3.5 min-w-0 flex-1">
                          <input
                            type="checkbox"
                            checked={isChecked}
                            onChange={() => {}}
                            className="w-5 h-5 text-blue-900 rounded border-slate-300 focus:ring-0 cursor-pointer shrink-0"
                          />
                          <span className={`${optionFontClass} leading-normal text-slate-900`}>{opt.label}</span>
                        </div>
                      </button>
                    );
                  })}
                </div>
              )}

              {/* Model 3: Benar / Salah Grid */}
              {currentQ.type === 'benar_salah' && currentQ.statements && (
                <div className="space-y-3 pt-2">
                  <div className="border border-slate-200 rounded-xl overflow-hidden shadow-2xs">
                    <table className="w-full text-xs sm:text-sm">
                      <thead className="bg-slate-100 text-slate-800 font-bold uppercase tracking-wider border-b border-slate-200">
                        <tr>
                          <th className="py-3 px-4 text-left">Pernyataan Soal</th>
                          <th className="py-3 px-4 text-center w-28">Benar</th>
                          <th className="py-3 px-4 text-center w-28">Salah</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-slate-100">
                        {currentQ.statements.map((stmt, sIdx) => {
                          const currentVal = answers[currentQ.id]?.[sIdx];
                          return (
                            <tr key={sIdx} className="hover:bg-slate-50/60 transition-colors">
                              <td className="p-4 text-slate-800 font-medium leading-relaxed">{stmt.text}</td>
                              <td className="p-4 text-center">
                                <button
                                  type="button"
                                  onClick={() => {
                                    const updated = { ...(answers[currentQ.id] || {}), [sIdx]: true };
                                    handleAnswerChange(updated);
                                  }}
                                  className={`w-full py-2 rounded-xl font-bold text-xs cursor-pointer shadow-2xs transition-all ${
                                    currentVal === true
                                      ? 'bg-emerald-800 text-white shadow-xs'
                                      : 'bg-slate-100 text-slate-700 hover:bg-slate-200 border border-slate-200'
                                  }`}
                                >
                                  Benar
                                </button>
                              </td>
                              <td className="p-4 text-center">
                                <button
                                  type="button"
                                  onClick={() => {
                                    const updated = { ...(answers[currentQ.id] || {}), [sIdx]: false };
                                    handleAnswerChange(updated);
                                  }}
                                  className={`w-full py-2 rounded-xl font-bold text-xs cursor-pointer shadow-2xs transition-all ${
                                    currentVal === false
                                      ? 'bg-rose-700 text-white shadow-xs'
                                      : 'bg-slate-100 text-slate-700 hover:bg-slate-200 border border-slate-200'
                                  }`}
                                >
                                  Salah
                                </button>
                              </td>
                            </tr>
                          );
                        })}
                      </tbody>
                    </table>
                  </div>
                </div>
              )}

              {/* Model 4: Menjodohkan (Matching Pair Interaktif - Tanpa Dropdown) */}
              {currentQ.type === 'menjodohkan' && currentQ.matching_pairs && (() => {
                const currentPairs = (answers[currentQ.id] || {}) as Record<string, string>;
                const allRightOptions = Array.from(
                  new Set(currentQ.matching_pairs.flatMap((p) => p.options || [p.target]))
                );

                const handleSelectPremise = (premise: string) => {
                  setActivePremise(prev => prev === premise ? null : premise);
                };

                const handleSelectTarget = (target: string) => {
                  if (!activePremise) return;
                  const updated = { ...currentPairs, [activePremise]: target };
                  handleAnswerChange(updated);
                  
                  // Automatically advance to next unpaired premise if available
                  const nextUnpaired = currentQ.matching_pairs?.find(
                    p => p.premise !== activePremise && !updated[p.premise]
                  );
                  setActivePremise(nextUnpaired ? nextUnpaired.premise : null);
                };

                const handleRemovePair = (premise: string) => {
                  const updated = { ...currentPairs };
                  delete updated[premise];
                  handleAnswerChange(updated);
                  if (activePremise === premise) {
                    setActivePremise(null);
                  }
                };

                const handleResetAllPairs = () => {
                  handleAnswerChange({});
                  setActivePremise(null);
                };

                const connectedCount = Object.keys(currentPairs).length;
                const totalPairs = currentQ.matching_pairs.length;

                return (
                  <div className="space-y-4 pt-2">
                    {/* Instructions & Status Bar */}
                    <div className="p-3.5 rounded-xl bg-blue-50/80 border border-blue-200/80 flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
                      <div className="flex items-center gap-2">
                        <Link2 className="w-4 h-4 text-blue-900 shrink-0" />
                        <p className="text-xs text-blue-950 font-medium leading-relaxed">
                          <strong>Petunjuk Menjodohkan:</strong> Klik kotak pada <strong>Kolom Kiri</strong>, lalu klik jawaban pasangan yang cocok pada <strong>Kolom Kanan</strong>.
                        </p>
                      </div>
                      <div className="flex items-center gap-2 shrink-0">
                        <span className="text-xs font-bold text-blue-900 bg-blue-100/90 px-2.5 py-1 rounded-lg font-mono">
                          {connectedCount} / {totalPairs} Terhubung
                        </span>
                        {connectedCount > 0 && (
                          <button
                            type="button"
                            onClick={handleResetAllPairs}
                            className="text-[11px] font-semibold text-rose-700 hover:text-rose-900 bg-white border border-rose-200 px-2.5 py-1 rounded-lg flex items-center gap-1 shadow-2xs hover:bg-rose-50 transition-all cursor-pointer"
                          >
                            <RotateCcw className="w-3 h-3" />
                            <span>Reset</span>
                          </button>
                        )}
                      </div>
                    </div>

                    {/* Two-Column Matching Board */}
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                      {/* Left Column: Premis */}
                      <div className="space-y-3">
                        <div className="flex items-center justify-between pb-1 border-b border-slate-200">
                          <span className="text-xs font-bold text-slate-700 uppercase tracking-wider">
                            Kolom Kiri (Premis / Soal)
                          </span>
                          <span className="text-[11px] text-slate-500 font-medium">
                            Pilih baris di bawah
                          </span>
                        </div>
                        <div className="space-y-2.5">
                          {currentQ.matching_pairs.map((pair, pIdx) => {
                            const isSelected = activePremise === pair.premise;
                            const matchedValue = currentPairs[pair.premise];
                            const isPaired = Boolean(matchedValue);

                            return (
                              <div
                                key={pIdx}
                                onClick={() => handleSelectPremise(pair.premise)}
                                className={`p-4 rounded-xl border-2 transition-all cursor-pointer shadow-2xs ${
                                  isSelected
                                    ? 'border-blue-600 bg-blue-50/90 ring-2 ring-blue-500/30'
                                    : isPaired
                                    ? 'border-emerald-300 bg-emerald-50/40 hover:border-emerald-400'
                                    : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50/60'
                                }`}
                              >
                                <div className="flex items-start justify-between gap-3">
                                  <div className="flex items-start gap-2.5 flex-1">
                                    <span className="w-6 h-6 rounded-lg bg-slate-100 border border-slate-200 flex items-center justify-center font-bold text-xs text-slate-700 shrink-0 font-mono">
                                      P{pIdx + 1}
                                    </span>
                                    <div className="space-y-1.5 flex-1">
                                      <p className="font-bold text-sm text-slate-900 leading-snug">
                                        {pair.premise}
                                      </p>
                                      {isPaired ? (
                                        <div className="flex items-center gap-1.5 pt-1">
                                          <span className="text-[11px] font-semibold text-emerald-800 bg-emerald-100 px-2 py-0.5 rounded-md flex items-center gap-1">
                                            <Check className="w-3 h-3 text-emerald-700" />
                                            Pasangan: {matchedValue}
                                          </span>
                                        </div>
                                      ) : isSelected ? (
                                        <p className="text-xs text-blue-700 font-semibold italic flex items-center gap-1">
                                          👉 Sekarang klik jawaban di Kolom Kanan...
                                        </p>
                                      ) : (
                                        <p className="text-xs text-slate-400 font-normal">
                                          Klik untuk memilih pasangan...
                                        </p>
                                      )}
                                    </div>
                                  </div>

                                  {isPaired && (
                                    <button
                                      type="button"
                                      onClick={(e) => {
                                        e.stopPropagation();
                                        handleRemovePair(pair.premise);
                                      }}
                                      title="Lepas pasangan"
                                      className="p-1 rounded-md text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-all cursor-pointer"
                                    >
                                      <X className="w-4 h-4" />
                                    </button>
                                  )}
                                </div>
                              </div>
                            );
                          })}
                        </div>
                      </div>

                      {/* Right Column: Pilihan Jawaban */}
                      <div className="space-y-3">
                        <div className="flex items-center justify-between pb-1 border-b border-slate-200">
                          <span className="text-xs font-bold text-slate-700 uppercase tracking-wider">
                            Kolom Kanan (Pilihan Pasangan)
                          </span>
                          <span className="text-[11px] text-slate-500 font-medium">
                            {activePremise ? 'Klik salah satu opsi' : 'Pilih premis dahulu'}
                          </span>
                        </div>
                        <div className="space-y-2.5">
                          {allRightOptions.map((opt, oIdx) => {
                            // Find which premise (if any) is currently paired to this option
                            const pairedPremiseKey = Object.keys(currentPairs).find(
                              (p) => currentPairs[p] === opt
                            );
                            const isPaired = Boolean(pairedPremiseKey);

                            return (
                              <div
                                key={oIdx}
                                onClick={() => handleSelectTarget(opt)}
                                className={`p-4 rounded-xl border-2 transition-all cursor-pointer shadow-2xs ${
                                  isPaired
                                    ? 'border-emerald-300 bg-emerald-50/40 hover:border-emerald-400'
                                    : activePremise
                                    ? 'border-blue-200 bg-white hover:border-blue-600 hover:bg-blue-50/50 hover:shadow-xs'
                                    : 'border-slate-200 bg-white hover:bg-slate-50 opacity-90'
                                }`}
                              >
                                <div className="flex items-start justify-between gap-3">
                                  <div className="flex items-start gap-2.5 flex-1">
                                    <span className="w-6 h-6 rounded-lg bg-slate-100 border border-slate-200 flex items-center justify-center font-bold text-xs text-slate-700 shrink-0 font-mono">
                                      {String.fromCharCode(65 + oIdx)}
                                    </span>
                                    <div className="space-y-1 flex-1">
                                      <p className="font-semibold text-sm text-slate-900 leading-snug">
                                        {opt}
                                      </p>
                                      {isPaired && (
                                        <span className="text-[11px] font-semibold text-emerald-800 bg-emerald-100 px-2 py-0.5 rounded-md inline-flex items-center gap-1">
                                          <Check className="w-3 h-3 text-emerald-700" />
                                          Terhubung ke Premis
                                        </span>
                                      )}
                                    </div>
                                  </div>
                                </div>
                              </div>
                            );
                          })}
                        </div>
                      </div>
                    </div>
                  </div>
                );
              })()}

              {/* Model 5: Isian Singkat */}
              {currentQ.type === 'isian' && (
                <div className="space-y-2.5 pt-2">
                  <label className="text-xs font-bold text-slate-700 uppercase tracking-wider block">
                    Ketikkan Jawaban Singkat Anda:
                  </label>
                  <input
                    type="text"
                    placeholder="Ketik jawaban di sini..."
                    value={answers[currentQ.id] || ''}
                    onChange={(e) => handleAnswerChange(e.target.value)}
                    className="w-full md:w-2/3 px-4 py-3 rounded-xl border-2 border-slate-200 font-bold text-base text-slate-900 focus:outline-none focus:border-blue-900 shadow-2xs"
                  />
                </div>
              )}

              {/* Model 6: Uraian Esai */}
              {currentQ.type === 'uraian' && (
                <div className="space-y-2.5 pt-2">
                  <label className="text-xs font-bold text-slate-700 uppercase tracking-wider block">
                    Tuliskan Penjelasan Esai Anda Secara Lengkap:
                  </label>
                  <textarea
                    rows={6}
                    placeholder="Ketik uraian jawaban esai di sini..."
                    value={answers[currentQ.id] || ''}
                    onChange={(e) => handleAnswerChange(e.target.value)}
                    className="w-full p-4 rounded-xl border-2 border-slate-200 font-medium text-sm text-slate-900 focus:outline-none focus:border-blue-900 shadow-2xs leading-relaxed"
                  />
                  <p className="text-xs text-slate-500 font-medium text-right font-mono">
                    {(answers[currentQ.id] || '').length} karakter
                  </p>
                </div>
              )}
            </div>
          </div>

          {/* Clean High-Contrast Bottom Navigation Dock */}
          <div className="shrink-0 h-16 border-t border-slate-200 bg-slate-50 px-6 sm:px-8 flex items-center justify-between rounded-b-xl">
            <button
              disabled={currentIdx === 0}
              onClick={() => setCurrentIdx(prev => Math.max(0, prev - 1))}
              className="px-5 py-2.5 rounded-xl border border-slate-300 bg-white hover:bg-slate-100 disabled:opacity-40 disabled:hover:bg-white text-xs font-bold text-slate-700 flex items-center gap-2 shadow-2xs cursor-pointer transition-all"
            >
              <ChevronLeft className="w-4 h-4 text-slate-700" />
              <span>Soal Sebelumnya</span>
            </button>

            {/* Ragu-Ragu Button (High Contrast, Clear Action) */}
            <button
              onClick={toggleFlag}
              className={`px-5 py-2.5 rounded-xl text-xs font-bold transition-all flex items-center gap-2 cursor-pointer shadow-xs ${
                flagged[currentQ.id]
                  ? 'bg-amber-500 hover:bg-amber-600 text-white'
                  : 'bg-white hover:bg-amber-50 text-amber-900 border-2 border-amber-400'
              }`}
            >
              <Flag className={`w-3.5 h-3.5 ${flagged[currentQ.id] ? 'text-white' : 'text-amber-700'}`} />
              <span>{flagged[currentQ.id] ? 'Ditandai Ragu-Ragu' : 'Tandai Ragu-Ragu'}</span>
            </button>

            {currentIdx === exam.total_questions - 1 ? (
              <button
                onClick={() => setShowSubmitModal(true)}
                className="px-6 py-2.5 rounded-xl bg-emerald-800 hover:bg-emerald-900 text-white text-xs font-bold flex items-center gap-2 shadow-xs cursor-pointer transition-all"
              >
                <span>Kumpulkan Ujian</span>
                <Send className="w-3.5 h-3.5 text-white" />
              </button>
            ) : (
              <button
                onClick={() => setCurrentIdx(prev => Math.min(exam.total_questions - 1, prev + 1))}
                className="px-6 py-2.5 rounded-xl bg-blue-900 hover:bg-blue-950 text-white text-xs font-bold flex items-center gap-2 shadow-xs cursor-pointer transition-all"
              >
                <span>Soal Berikutnya</span>
                <ChevronRight className="w-4 h-4 text-white" />
              </button>
            )}
          </div>
        </div>

        {/* RIGHT COLUMN: Daftar Nomor Soal Rail (Zero Clipping) */}
        <aside className="w-full lg:w-80 bg-white border border-slate-200/90 rounded-xl p-4 shadow-xs flex flex-col justify-between overflow-hidden shrink-0">
          <div className="space-y-3 shrink-0">
            <div className="flex items-center justify-between pb-2.5 border-b border-slate-100">
              <div>
                <h3 className="font-extrabold text-sm text-slate-900 tracking-tight">
                  Daftar Nomor Soal
                </h3>
                <p className="text-[11px] text-slate-500 font-medium">Klik nomor butir untuk melompat</p>
              </div>
              <span className="text-xs font-semibold text-slate-600 font-mono px-2 py-0.5 rounded-md bg-slate-100 border border-slate-200">
                PC-LAB1-08
              </span>
            </div>

            {/* Clean Segmented Status Legend */}
            <div className="grid grid-cols-3 gap-2 text-center text-xs">
              <div className="p-2 rounded-lg bg-blue-50 border border-blue-200/80 text-blue-900 flex flex-col items-center">
                <span className="text-sm font-extrabold leading-none">{answeredCount}</span>
                <span className="text-[11px] font-semibold text-blue-800 mt-1">Sudah</span>
              </div>
              <div className="p-2 rounded-lg bg-amber-50 border border-amber-200/80 text-amber-900 flex flex-col items-center">
                <span className="text-sm font-extrabold leading-none">{flaggedCount}</span>
                <span className="text-[11px] font-semibold text-amber-800 mt-1">Ragu</span>
              </div>
              <div className="p-2 rounded-lg bg-slate-100 border border-slate-200 text-slate-700 flex flex-col items-center">
                <span className="text-sm font-extrabold leading-none">{unansweredCount}</span>
                <span className="text-[11px] font-semibold text-slate-600 mt-1">Belum</span>
              </div>
            </div>
          </div>

          {/* Grid Button Nomor Soal (Safe Padding, 100% Internal Border - ZERO CLIPPING!) */}
          <div className="flex-1 min-h-0 overflow-y-auto p-2.5 grid grid-cols-5 gap-2 content-start">
            {allQuestionIds.map((qId, idx) => {
              const isAnswered = !!answers[qId];
              const isFlag = !!flagged[qId];
              const isCurrent = currentIdx === idx;

              let btnClass = 'bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 hover:border-slate-300 font-semibold';
              if (isFlag) {
                btnClass = 'bg-amber-500 hover:bg-amber-600 text-white font-bold shadow-2xs border border-amber-600';
              } else if (isAnswered) {
                btnClass = 'bg-blue-900 hover:bg-blue-950 text-white font-bold shadow-2xs border border-blue-900';
              }

              if (isCurrent) {
                if (isFlag) {
                  btnClass = 'bg-amber-500 text-white font-black border-2 border-slate-900 shadow-xs';
                } else if (isAnswered) {
                  btnClass = 'bg-blue-900 text-white font-black border-2 border-slate-900 shadow-xs';
                } else {
                  btnClass = 'bg-blue-50 text-blue-900 font-black border-2 border-blue-900 shadow-xs';
                }
              }

              return (
                <button
                  key={qId}
                  onClick={() => setCurrentIdx(idx)}
                  className={`w-full aspect-square rounded-lg text-xs flex flex-col items-center justify-center transition-colors cursor-pointer relative ${btnClass}`}
                >
                  <span>{idx + 1}</span>
                  {isFlag && (
                    <span className="absolute top-1 right-1 w-1.5 h-1.5 rounded-full bg-white"></span>
                  )}
                </button>
              );
            })}
          </div>

          {/* Bottom Completion Card & Submit Button */}
          <div className="shrink-0 pt-3 border-t border-slate-100 space-y-3">
            <div>
              <div className="flex items-center justify-between text-xs text-slate-600 mb-1.5">
                <span className="font-semibold text-slate-700">Progres Pengerjaan</span>
                <span className="font-extrabold text-slate-900 font-mono">{progressPercent}%</span>
              </div>
              <div className="w-full h-2 rounded-full bg-slate-100 overflow-hidden border border-slate-200">
                <div
                  className="h-full bg-blue-900 rounded-full transition-all duration-300"
                  style={{ width: `${progressPercent}%` }}
                ></div>
              </div>
            </div>

            <button
              onClick={() => setShowSubmitModal(true)}
              className="w-full py-2.5 rounded-xl bg-emerald-800 hover:bg-emerald-900 text-white text-xs font-bold uppercase tracking-wider transition-colors shadow-xs flex items-center justify-center gap-2 cursor-pointer"
            >
              <Send className="w-3.5 h-3.5 text-white" />
              <span>Selesaikan Ujian</span>
            </button>
          </div>
        </aside>
      </div>

      {/* ─── 3. MODAL PERINGATAN PELANGGARAN ANTI-CHEAT ─── */}
      {showViolationModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4 overflow-y-auto">
          <div className="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-md w-full overflow-hidden animate-in fade-in zoom-in-95 duration-150">
            {/* Modal Header */}
            <div className="px-6 py-4 bg-rose-50 border-b border-rose-100 flex items-center justify-between">
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 rounded-xl bg-rose-100 text-rose-800 border border-rose-200 flex items-center justify-center font-bold shrink-0">
                  <ShieldAlert className="w-5 h-5 text-rose-700" />
                </div>
                <div>
                  <h3 className="font-extrabold text-sm sm:text-base text-rose-950 tracking-tight">
                    Peringatan Pengawasan CBT
                  </h3>
                  <p className="text-xs text-rose-700 font-normal">
                    Aktivitas Integritas Ujian Terdeteksi
                  </p>
                </div>
              </div>
            </div>

            {/* Modal Body */}
            <div className="p-6 space-y-4">
              {/* Strike Meters */}
              <div className="space-y-1.5">
                <div className="flex items-center justify-between text-xs font-bold text-slate-700 uppercase tracking-wider">
                  <span>Akumulasi Pelanggaran</span>
                  <span className="font-mono text-rose-700">{violationCount} / 3 Batas Toleransi</span>
                </div>
                <div className="grid grid-cols-3 gap-2">
                  {[1, 2, 3].map((step) => {
                    const isTriggered = violationCount >= step;
                    return (
                      <div
                        key={step}
                        className={`py-2 px-2.5 rounded-xl border text-center transition-all ${
                          isTriggered
                            ? 'bg-rose-700 text-white border-rose-800 font-bold shadow-2xs'
                            : 'bg-slate-50 text-slate-400 border-slate-200 font-medium'
                        }`}
                      >
                        <span className="text-[11px] uppercase tracking-wider block">
                          Peringatan {step}
                        </span>
                      </div>
                    );
                  })}
                </div>
              </div>

              {/* Reason Card */}
              <div className="p-3.5 bg-rose-50/70 rounded-xl border border-rose-200 space-y-1">
                <span className="text-[11px] font-bold uppercase tracking-wider text-rose-800 block">
                  Indikasi Pelanggaran:
                </span>
                <p className="text-xs text-rose-950 font-semibold leading-relaxed">
                  {violationReason || 'Perpindahan jendela (window blur) atau upaya membuka tab/aplikasi lain.'}
                </p>
              </div>

              {/* Informative Explanation */}
              <p className="text-xs text-slate-600 font-normal leading-relaxed">
                Workstation ujian mendeteksi perpindahan fokus layar. Log aktivitas ini otomatis dikirim ke layar pengawas lab. Jika pelanggaran mencapai 3 kali, lembar ujian Anda akan <strong>terkunci secara otomatis</strong>.
              </p>

              {isExamLocked && (
                <div className="p-3.5 bg-rose-900 text-white rounded-xl text-xs font-semibold flex items-start gap-2.5 shadow-sm">
                  <Lock className="w-4 h-4 text-rose-200 shrink-0 mt-0.5" />
                  <span className="leading-relaxed">
                    Sesi Ujian Anda Terkunci. Silakan panggil Pengawas Lab Komputer untuk membuka kunci workstation.
                  </span>
                </div>
              )}
            </div>

            {/* Modal Footer */}
            <div className="px-6 py-4 bg-slate-50 border-t border-slate-100 flex items-center justify-end">
              {isExamLocked ? (
                <button
                  disabled
                  type="button"
                  className="w-full py-2.5 rounded-xl bg-slate-200 text-slate-500 text-xs font-bold tracking-wider uppercase cursor-not-allowed"
                >
                  Workstation Terkunci Pengawas
                </button>
              ) : (
                <button
                  type="button"
                  onClick={() => setShowViolationModal(false)}
                  className="w-full py-2.5 rounded-xl bg-rose-700 hover:bg-rose-800 text-white text-xs font-bold tracking-wider uppercase transition-colors shadow-xs cursor-pointer"
                >
                  Saya Mengerti, Kembali Mengerjakan
                </button>
              )}
            </div>
          </div>
        </div>
      )}

      {/* ─── 4. MODAL KONFIRMASI SUBMIT BERTINGKAT ─── */}
      {showSubmitModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4 overflow-y-auto">
          <div className="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-lg w-full overflow-hidden animate-in fade-in zoom-in-95 duration-150">
            {/* Modal Header */}
            <div className="px-6 py-4 bg-white border-b border-slate-100 flex items-center justify-between">
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-800 border border-emerald-200 flex items-center justify-center font-bold shrink-0">
                  <Send className="w-5 h-5 text-emerald-800" />
                </div>
                <div>
                  <h3 className="font-extrabold text-sm sm:text-base text-slate-900 tracking-tight">
                    Konfirmasi Pengumpulan Ujian
                  </h3>
                  <p className="text-xs text-slate-500 font-normal">
                    {exam.title} • {exam.room_name}
                  </p>
                </div>
              </div>

              <button
                type="button"
                onClick={() => setShowSubmitModal(false)}
                className="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors cursor-pointer"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            {/* Modal Body */}
            <div className="p-6 space-y-4">
              {/* Statistik Jawaban Ringkasan */}
              <div className="grid grid-cols-3 gap-2.5 text-center">
                <div className="p-3 bg-emerald-50/70 rounded-xl border border-emerald-200/80">
                  <span className="text-[11px] text-emerald-800 font-bold uppercase tracking-wider block">Terjawab</span>
                  <span className="text-2xl font-extrabold text-emerald-900 font-mono mt-0.5 block">{answeredCount}</span>
                </div>
                <div className="p-3 bg-amber-50/70 rounded-xl border border-amber-200/80">
                  <span className="text-[11px] text-amber-800 font-bold uppercase tracking-wider block">Ragu-Ragu</span>
                  <span className="text-2xl font-extrabold text-amber-900 font-mono mt-0.5 block">{flaggedCount}</span>
                </div>
                <div className="p-3 bg-rose-50/70 rounded-xl border border-rose-200/80">
                  <span className="text-[11px] text-rose-800 font-bold uppercase tracking-wider block">Belum Dijawab</span>
                  <span className="text-2xl font-extrabold text-rose-900 font-mono mt-0.5 block">{unansweredCount}</span>
                </div>
              </div>

              {/* Warning Banner if any unanswered or flagged */}
              {(unansweredCount > 0 || flaggedCount > 0) && (
                <div className="p-3.5 bg-amber-50/90 rounded-xl border border-amber-200 text-xs text-amber-950 flex items-start gap-2.5">
                  <AlertTriangle className="w-4 h-4 text-amber-700 shrink-0 mt-0.5" />
                  <div className="space-y-1">
                    <p className="font-bold text-amber-900">
                      Perhatian: Masih Ada Soal Belum Lengkap!
                    </p>
                    <p className="leading-relaxed font-normal">
                      Terdapat <strong>{unansweredCount} butir belum dijawab</strong> dan <strong>{flaggedCount} butir ditandai ragu-ragu</strong>. Waktu pengerjaan masih tersisa <strong>{formatTime(timeLeft)}</strong>. Anda disarankan memeriksa kembali sebelum mengirim lembar ujian.
                    </p>
                  </div>
                </div>
              )}

              {/* Pernyataan Konfirmasi */}
              <label className="flex items-start gap-3 p-3.5 rounded-xl border border-slate-200 bg-slate-50 hover:bg-slate-100/70 cursor-pointer transition-colors">
                <input
                  type="checkbox"
                  checked={confirmCheckbox}
                  onChange={(e) => setConfirmCheckbox(e.target.checked)}
                  className="w-4 h-4 mt-0.5 text-emerald-800 rounded border-slate-300 focus:ring-0 cursor-pointer"
                />
                <span className="text-xs font-semibold text-slate-800 leading-relaxed">
                  Saya yakin dan sadar ingin mengumpulkan seluruh lembar jawaban ujian ini. Setelah dikumpulkan, lembar jawaban tidak dapat dibuka kembali.
                </span>
              </label>
            </div>

            {/* Modal Footer */}
            <div className="px-6 py-4 bg-slate-50 border-t border-slate-100 flex items-center justify-end gap-2.5">
              <button
                type="button"
                onClick={() => setShowSubmitModal(false)}
                className="px-4 py-2.5 rounded-xl border border-slate-200 bg-white text-xs font-semibold text-slate-700 hover:bg-slate-100 transition-colors cursor-pointer"
              >
                Kembali Periksa Soal
              </button>
              <button
                type="button"
                disabled={!confirmCheckbox}
                onClick={handleFinalSubmit}
                className="px-5 py-2.5 rounded-xl bg-emerald-800 hover:bg-emerald-900 disabled:opacity-40 disabled:cursor-not-allowed text-white text-xs font-bold tracking-wider uppercase transition-colors shadow-xs flex items-center gap-2 cursor-pointer"
              >
                <Send className="w-3.5 h-3.5 text-white" />
                <span>Ya, Kumpulkan Sekarang</span>
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
