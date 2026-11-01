'use client';

import React, { useEffect, useState, useRef, useCallback } from 'react';
import Link from 'next/link';
import { useParams, useRouter } from 'next/navigation';
import {
  CheckCircle2,
  Clock,
  AlertTriangle,
  ArrowLeft,
  ArrowRight,
  Maximize2,
  Minimize2,
  Check,
  ShieldAlert,
  Save,
  HelpCircle,
  FileText,
  X,
  Award,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { cbtApi } from '@/features/cbt/cbt-api';
import { StudentSessionQuestionItem, QuestionType } from '@/types/cbt';
import { useAuth } from '@/providers/auth-provider';

export default function StudentCbtPlayerPage() {
  const params = useParams();
  const router = useRouter();
  const { user } = useAuth();
  const examId = params.id as string;

  const [questions, setQuestions] = useState<StudentSessionQuestionItem[]>([]);
  const [currentIndex, setCurrentIndex] = useState<number>(0);
  const [answers, setAnswers] = useState<Record<string, any>>({});
  const [doubts, setDoubts] = useState<string[]>([]);
  const [isLoading, setIsLoading] = useState<boolean>(true);
  const [loadError, setLoadError] = useState<string | null>(null);

  // Timer & Autosave (Sourced from server, absolute clock skew offset)
  const [remainingSeconds, setRemainingSeconds] = useState<number>(0);
  const [autosaveStatus, setAutosaveStatus] = useState<'saved' | 'saving' | 'error'>('saved');
  const [isFullscreen, setIsFullscreen] = useState<boolean>(false);

  // Anti-Cheat & Lock Status (Enforced by server)
  const [violationCount, setViolationCount] = useState<number>(0);
  const [showViolationModal, setShowViolationModal] = useState<boolean>(false);
  const [isLocked, setIsLocked] = useState<boolean>(false);
  const [lockedReason, setLockedReason] = useState<string | null>(null);

  // Submit Modals
  const [showConfirmModal, setShowConfirmModal] = useState<boolean>(false);
  const [isSubmitting, setIsSubmitting] = useState<boolean>(false);
  const [isFinished, setIsFinished] = useState<boolean>(false);

  // Refs for timing, clock skew, intervals
  const serverOffsetMsRef = useRef<number>(0);
  const endsAtMsRef = useRef<number | null>(null);
  const isFinishedRef = useRef<boolean>(false);
  const doubtsRef = useRef<string[]>([]);
  const timerIntervalRef = useRef<NodeJS.Timeout | null>(null);
  const heartbeatIntervalRef = useRef<NodeJS.Timeout | null>(null);

  // In-memory storage fallback for private browsing / quota exceeded
  const memoryPendingAnswersRef = useRef<Record<string, any>>({});
  const memoryPendingViolationsRef = useRef<any[]>([]);
  const isDrainingRef = useRef<boolean>(false);

  useEffect(() => {
    isFinishedRef.current = isFinished;
  }, [isFinished]);

  useEffect(() => {
    doubtsRef.current = doubts;
  }, [doubts]);

  // User-scoped storage keys to prevent cross-account queue leaks on shared devices
  const getUserScope = useCallback(() => {
    return user?.id || user?.username || '';
  }, [user?.id, user?.username]);

  const getAnswerStorageKey = useCallback(() => {
    const scope = getUserScope();
    return scope ? `cbt_pending_answers_${scope}_${examId}` : `cbt_pending_answers_${examId}`;
  }, [getUserScope, examId]);

  const getViolationStorageKey = useCallback(() => {
    const scope = getUserScope();
    return scope ? `cbt_pending_violations_${scope}_${examId}` : `cbt_pending_violations_${examId}`;
  }, [getUserScope, examId]);

  const readStoredAnswers = useCallback((): Record<string, any> => {
    try {
      const scope = getUserScope();
      if (scope) {
        const scopedRaw = localStorage.getItem(`cbt_pending_answers_${scope}_${examId}`);
        if (scopedRaw) {
          return { ...JSON.parse(scopedRaw), ...memoryPendingAnswersRef.current };
        }
      }
      const owner = localStorage.getItem(`cbt_active_owner_${examId}`);
      if (owner && scope && owner !== scope) {
        return { ...memoryPendingAnswersRef.current };
      }
      const raw = localStorage.getItem(`cbt_pending_answers_${examId}`);
      const parsed = raw ? JSON.parse(raw) : {};
      return { ...parsed, ...memoryPendingAnswersRef.current };
    } catch {
      return { ...memoryPendingAnswersRef.current };
    }
  }, [getUserScope, examId]);

  const readStoredAnswersMeta = useCallback((): Record<string, any> => {
    try {
      const scope = getUserScope();
      if (scope) {
        const scopedRaw = localStorage.getItem(`cbt_pending_answers_meta_${scope}_${examId}`);
        if (scopedRaw) {
          return JSON.parse(scopedRaw);
        }
      }
      const owner = localStorage.getItem(`cbt_active_owner_${examId}`);
      if (owner && scope && owner !== scope) {
        return {};
      }
      const raw = localStorage.getItem(`cbt_pending_answers_meta_${examId}`);
      return raw ? JSON.parse(raw) : {};
    } catch {
      return {};
    }
  }, [getUserScope, examId]);

  const writeStoredAnswers = useCallback((map: Record<string, any>, meta?: Record<string, any>) => {
    memoryPendingAnswersRef.current = { ...map };
    try {
      const scope = getUserScope();
      const raw = JSON.stringify(map);
      localStorage.setItem(`cbt_pending_answers_${examId}`, raw);
      if (scope) {
        localStorage.setItem(`cbt_pending_answers_${scope}_${examId}`, raw);
        localStorage.setItem(`cbt_active_owner_${examId}`, scope);
      }
      if (meta) {
        const metaRaw = JSON.stringify(meta);
        localStorage.setItem(`cbt_pending_answers_meta_${examId}`, metaRaw);
        if (scope) {
          localStorage.setItem(`cbt_pending_answers_meta_${scope}_${examId}`, metaRaw);
        }
      }
    } catch (e) {
      console.warn('localStorage setItem failed, using in-memory store:', e);
    }
  }, [getUserScope, examId]);

  const readStoredViolations = useCallback((): any[] => {
    try {
      const scope = getUserScope();
      if (scope) {
        const scopedRaw = localStorage.getItem(`cbt_pending_violations_${scope}_${examId}`);
        if (scopedRaw) {
          const list = JSON.parse(scopedRaw);
          return Array.isArray(list) ? [...list, ...memoryPendingViolationsRef.current] : [...memoryPendingViolationsRef.current];
        }
      }
      const owner = localStorage.getItem(`cbt_active_owner_${examId}`);
      if (owner && scope && owner !== scope) {
        return [...memoryPendingViolationsRef.current];
      }
      const raw = localStorage.getItem(`cbt_pending_violations_${examId}`);
      const parsed = raw ? JSON.parse(raw) : [];
      const list = Array.isArray(parsed) ? parsed : [];
      return [...list, ...memoryPendingViolationsRef.current];
    } catch {
      return [...memoryPendingViolationsRef.current];
    }
  }, [getUserScope, examId]);

  const writeStoredViolations = useCallback((list: any[]) => {
    memoryPendingViolationsRef.current = [...list];
    try {
      const scope = getUserScope();
      const raw = JSON.stringify(list);
      localStorage.setItem(`cbt_pending_violations_${examId}`, raw);
      if (scope) {
        localStorage.setItem(`cbt_pending_violations_${scope}_${examId}`, raw);
      }
    } catch (e) {
      console.warn('localStorage setItem failed for violations:', e);
    }
  }, [getUserScope, examId]);

  const clearOfflineQueues = useCallback(() => {
    memoryPendingAnswersRef.current = {};
    memoryPendingViolationsRef.current = [];
    try {
      const scope = getUserScope();
      localStorage.removeItem(`cbt_pending_answers_${examId}`);
      localStorage.removeItem(`cbt_pending_violations_${examId}`);
      localStorage.removeItem(`cbt_pending_answers_meta_${examId}`);
      localStorage.removeItem(`cbt_active_owner_${examId}`);
      if (scope) {
        localStorage.removeItem(`cbt_pending_answers_${scope}_${examId}`);
        localStorage.removeItem(`cbt_pending_violations_${scope}_${examId}`);
        localStorage.removeItem(`cbt_pending_answers_meta_${scope}_${examId}`);
      }
    } catch {}
  }, [getUserScope, examId]);

  // Hitung sisa waktu absolut: sisa = ends_at - (Date.now() + offset)
  // Kebal terhadap background tab throttling browser dan jam HP yang salah
  const computeRemaining = useCallback(() => {
    if (!endsAtMsRef.current) return 0;
    const nowServerEstimated = Date.now() + serverOffsetMsRef.current;
    return Math.max(0, Math.floor((endsAtMsRef.current - nowServerEstimated) / 1000));
  }, []);

  // Flush pending offline answers & violations to server
  const flushPendingOfflineData = useCallback(async () => {
    if (!examId || isDrainingRef.current) return;
    if (typeof navigator !== 'undefined' && !navigator.onLine) return;

    isDrainingRef.current = true;

    try {
      // 1. Flush pending violations
      const vList = readStoredViolations();
      if (vList.length > 0) {
        const remainingViolations: any[] = [];
        for (const item of vList) {
          try {
            const res = await cbtApi.logStudentViolation(examId, {
              violation_type: item.violation_type,
              details: { timestamp: item.timestamp, idempotency_key: item.idempotency_key },
            });
            const serverCount = res.data?.violation_count ?? res.data?.total_violations;
            if (typeof serverCount === 'number') {
              setViolationCount(serverCount);
            }
            if (res.data?.is_locked || res.data?.session_status === 'terkunci') {
              setIsLocked(true);
            }
            if (res.data?.session_status === 'selesai') {
              setIsFinished(true);
            }
          } catch (itemErr) {
            remainingViolations.push(item);
          }
        }
        writeStoredViolations(remainingViolations);
      }

      // 2. Flush pending answers
      const aMap = readStoredAnswers();
      const entries = Object.entries(aMap);
      if (entries.length > 0) {
        const metaMap = readStoredAnswersMeta();

        for (const [qId, val] of entries) {
          const entryMeta = metaMap[qId];
          const answeredAt = entryMeta?.timestamp ? new Date(entryMeta.timestamp).toISOString() : undefined;
          const idempotencyKey = entryMeta?.idempotency_key;

          try {
            await cbtApi.saveStudentAnswer(examId, {
              question_id: qId,
              answer: val,
              is_flagged: doubtsRef.current.includes(qId),
              answered_at: answeredAt,
              idempotency_key: idempotencyKey,
            } as any);

            const latestQueue = readStoredAnswers();
            const latestMeta = readStoredAnswersMeta();

            const storedTs = latestMeta[qId]?.timestamp ?? 0;
            const entryTs = entryMeta?.timestamp ?? 0;

            if (storedTs <= entryTs) {
              delete latestQueue[qId];
              delete latestMeta[qId];
              writeStoredAnswers(latestQueue, latestMeta);
            }
          } catch (ansErr: any) {
            if (
              ansErr?.code === 'EXAM_TIME_OVER' ||
              ansErr?.code === 'BUSINESS_RULE_VIOLATION' ||
              ansErr?.message?.includes('timeout') ||
              ansErr?.message?.includes('berakhir')
            ) {
              setRemainingSeconds(0);
              setIsFinished(true);
            }
            break;
          }
        }
        const remainingCount = Object.keys(readStoredAnswers()).length;
        if (remainingCount === 0) {
          setAutosaveStatus('saved');
        }
      }
    } finally {
      isDrainingRef.current = false;
    }
  }, [examId, readStoredViolations, writeStoredViolations, readStoredAnswers, writeStoredAnswers, readStoredAnswersMeta]);

  // Listener event 'online' untuk retry otomatis
  useEffect(() => {
    const handleOnline = () => {
      flushPendingOfflineData();
    };
    window.addEventListener('online', handleOnline);
    return () => window.removeEventListener('online', handleOnline);
  }, [flushPendingOfflineData]);

  // Force Submit saat waktu server habis
  const handleForceSubmitTimeout = useCallback(async () => {
    if (isFinishedRef.current) return;
    setIsSubmitting(true);
    try {
      await cbtApi.finishStudentSession(examId);
    } catch (err) {
      console.error('Force submit failed:', err);
    } finally {
      setIsSubmitting(false);
      setIsFinished(true);
      clearOfflineQueues();
    }
  }, [examId, clearOfflineQueues]);

  // Load Session Questions & Server State
  useEffect(() => {
    async function loadSession() {
      setIsLoading(true);
      setLoadError(null);
      try {
        const res = await cbtApi.getStudentSessionQuestions(examId);
        const sessionData: any = res.data || {};
        const loadedQuestions: StudentSessionQuestionItem[] = Array.isArray(sessionData)
          ? sessionData
          : (sessionData.questions || []);

        setQuestions(loadedQuestions);

        // 1. Sinkronisasi offset waktu server (Date.now() clock skew handling)
        const serverTimeStr = sessionData.server_time;
        const endsAtStr = sessionData.ends_at || sessionData.deadline_at;

        if (serverTimeStr) {
          const serverMs = new Date(serverTimeStr).getTime();
          serverOffsetMsRef.current = serverMs - Date.now();
        }

        if (endsAtStr) {
          endsAtMsRef.current = new Date(endsAtStr).getTime();
        } else if (sessionData.remaining_seconds) {
          const baseServerMs = serverTimeStr ? new Date(serverTimeStr).getTime() : Date.now();
          endsAtMsRef.current = baseServerMs + sessionData.remaining_seconds * 1000;
        }

        const initialSisa = computeRemaining();
        setRemainingSeconds(initialSisa);

        // Jika waktu server saat load sudah habis, langsung tutup sesi
        if (initialSisa <= 0 && endsAtStr) {
          handleForceSubmitTimeout();
        }

        // 2. Pulihkan violation_count dan status dari server
        if (typeof sessionData.violation_count === 'number') {
          setViolationCount(sessionData.violation_count);
        }

        if (sessionData.status === 'terkunci') {
          setIsLocked(true);
          setLockedReason(sessionData.locked_reason || 'Sesi dikunci karena terdeteksi pelanggaran aturan ujian.');
        } else if (sessionData.status === 'selesai') {
          setIsFinished(true);
        }

        // 3. Prepopulate jawaban dari server
        const initialAnswers: Record<string, any> = {};
        const initialDoubts: string[] = [];

        loadedQuestions.forEach((q: any) => {
          const qId = q.question_id || q.id;
          const currentAnswer = q.current_answer !== undefined ? q.current_answer : q.user_answer;
          if (currentAnswer !== undefined && currentAnswer !== null) {
            initialAnswers[qId] = currentAnswer;
          }
          if (q.is_flagged) {
            initialDoubts.push(qId);
          }
        });

        // 4. Pulihkan jawaban pending dari storage yang belum sempat tersimpan
        const pendingMap = readStoredAnswers();
        for (const [qId, entry] of Object.entries(pendingMap)) {
          const val = typeof entry === 'object' && entry !== null && 'value' in entry ? entry.value : entry;
          initialAnswers[qId] = val;
        }

        setAnswers(initialAnswers);
        setDoubts(initialDoubts);

        // Flush offline data
        flushPendingOfflineData();
      } catch (err: any) {
        if (
          err?.code === 'EXAM_TIME_OVER' ||
          err?.code === 'BUSINESS_RULE_VIOLATION' ||
          err?.message?.includes('timeout') ||
          err?.message?.includes('selesai') ||
          err?.message?.includes('berakhir')
        ) {
          setIsFinished(true);
          clearOfflineQueues();
        } else {
          setLoadError(err?.message || 'Gagal memuat butir soal ujian. Pastikan token telah dimasukkan.');
        }
      } finally {
        setIsLoading(false);
      }
    }

    if (examId) {
      loadSession();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [examId]);

  // Timer Countdown (Interval hanya memicu re-render; waktu mutlak dihitung absolut)
  useEffect(() => {
    timerIntervalRef.current = setInterval(() => {
      if (!endsAtMsRef.current) return;
      const sisa = computeRemaining();
      setRemainingSeconds(sisa);

      if (sisa <= 0 && !isFinishedRef.current) {
        if (timerIntervalRef.current) clearInterval(timerIntervalRef.current);
        handleForceSubmitTimeout();
      }
    }, 1000);

    return () => {
      if (timerIntervalRef.current) clearInterval(timerIntervalRef.current);
    };
  }, [computeRemaining, handleForceSubmitTimeout]);

  // Heartbeat Presence Ping (every 30 seconds)
  useEffect(() => {
    heartbeatIntervalRef.current = setInterval(() => {
      if (examId && !isFinishedRef.current) {
        cbtApi.sendStudentHeartbeat(examId).catch(() => {});
      }
    }, 30000);

    return () => {
      if (heartbeatIntervalRef.current) clearInterval(heartbeatIntervalRef.current);
    };
  }, [examId]);

  // Anti-Cheat Window Blur / Tab Switch Listener & VisibilityChange Skew Recalculation
  useEffect(() => {
    const handleVisibilityChange = () => {
      // Hitung ulang timer segera saat kembali ke tab (kebal tab throttling background browser)
      const sisa = computeRemaining();
      setRemainingSeconds(sisa);

      if (sisa <= 0 && !isFinishedRef.current && endsAtMsRef.current) {
        handleForceSubmitTimeout();
      }

      if (document.hidden && !isFinishedRef.current) {
        triggerViolation('tab_switch');
      }
    };

    const handleWindowBlur = () => {
      if (!isFinishedRef.current) {
        triggerViolation('window_blur');
      }
    };

    document.addEventListener('visibilitychange', handleVisibilityChange);
    window.addEventListener('blur', handleWindowBlur);

    return () => {
      document.removeEventListener('visibilitychange', handleVisibilityChange);
      window.removeEventListener('blur', handleWindowBlur);
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [computeRemaining, handleForceSubmitTimeout]);

  const triggerViolation = async (violationType: string) => {
    setShowViolationModal(true);

    const idempotencyKey = `${violationType}_${Date.now()}_${Math.random().toString(36).substring(2, 7)}`;
    const timestamp = new Date().toISOString();

    const queueOfflineViolation = () => {
      const list = readStoredViolations();
      list.push({
        violation_type: violationType,
        timestamp,
        idempotency_key: idempotencyKey,
      });
      writeStoredViolations(list);
      setViolationCount((prev) => prev + 1);
    };

    if (typeof navigator !== 'undefined' && !navigator.onLine) {
      queueOfflineViolation();
      return;
    }

    try {
      const res = await cbtApi.logStudentViolation(examId, {
        violation_type: violationType,
        details: { timestamp, idempotency_key: idempotencyKey },
      });

      const serverCount = res.data?.violation_count ?? res.data?.total_violations;
      if (typeof serverCount === 'number') {
        setViolationCount(serverCount);
      } else {
        setViolationCount((prev) => prev + 1);
      }

      if (res.data?.is_locked || res.data?.session_status === 'terkunci') {
        setIsLocked(true);
        setLockedReason(res.data?.locked_reason || 'Sesi dikunci karena terdeteksi pelanggaran aturan ujian.');
      }

      if (res.data?.session_status === 'selesai' || res.data?.consequence === 'disqualified') {
        setIsFinished(true);
      }
    } catch (e: any) {
      console.error('Failed to log violation to server, queuing offline:', e);
      queueOfflineViolation();
    }
  };

  // Keyboard Shortcuts (A, B, C, D, E and Left/Right Arrows)
  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if (isFinished || isLocked || showConfirmModal || showViolationModal) return;

      // Ignore shortcuts if user is typing in input or textarea
      if (['INPUT', 'TEXTAREA'].includes((e.target as HTMLElement).tagName)) {
        return;
      }

      const currentQ = questions[currentIndex];
      if (!currentQ) return;

      const qId = currentQ.question_id || (currentQ as any).id;

      if (e.key === 'ArrowRight' && currentIndex < questions.length - 1) {
        setCurrentIndex((i) => i + 1);
      } else if (e.key === 'ArrowLeft' && currentIndex > 0) {
        setCurrentIndex((i) => i - 1);
      } else if (['a', 'b', 'c', 'd', 'e', 'A', 'B', 'C', 'D', 'E'].includes(e.key)) {
        const letter = e.key.toUpperCase();
        if (currentQ.question_type === 'pg' && currentQ.options) {
          const matchedOpt = currentQ.options.find((opt) => opt.label === letter);
          if (matchedOpt) {
            handleAnswerChange(qId, matchedOpt.id);
          }
        }
      }
    };

    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [currentIndex, questions, isFinished, isLocked, showConfirmModal, showViolationModal]);

  // Save Answer to Server with Offline Queue
  const handleAnswerChange = async (questionId: string, answerValue: any) => {
    const updated = { ...answers, [questionId]: answerValue };
    setAnswers(updated);
    setAutosaveStatus('saving');

    const timestamp = Date.now();
    const idempotencyKey = `${questionId}_${timestamp}_${Math.random().toString(36).substring(2, 7)}`;
    const entry = {
      value: answerValue,
      timestamp,
      idempotency_key: idempotencyKey,
    };

    // 1. Simpan ke local storage terlebih dahulu agar tidak hilang bila koneksi putus atau reload mendadak
    const currentQueue = readStoredAnswers();
    currentQueue[questionId] = answerValue;

    const metaMap = readStoredAnswersMeta();
    metaMap[questionId] = { timestamp, idempotency_key: idempotencyKey };

    writeStoredAnswers(currentQueue, metaMap);

    try {
      await cbtApi.saveStudentAnswer(examId, {
        question_id: questionId,
        answer: answerValue,
        is_flagged: doubts.includes(questionId),
        answered_at: new Date(timestamp).toISOString(),
        idempotency_key: idempotencyKey,
      } as any);

      // Berhasil tersimpan ke server: hanya hapus bila data di storage belum diperbarui oleh pilihan yang lebih baru
      const latestMeta = readStoredAnswersMeta();
      const currentStoredTs = latestMeta[questionId]?.timestamp ?? 0;

      if (currentStoredTs <= timestamp) {
        const latestQueue = readStoredAnswers();
        delete latestQueue[questionId];
        delete latestMeta[questionId];
        writeStoredAnswers(latestQueue, latestMeta);
      }

      setAutosaveStatus('saved');
    } catch (err: any) {
      setAutosaveStatus('error');
      // Server-side timing lock rejection (mendukung EXAM_TIME_OVER dan BUSINESS_RULE_VIOLATION backward compatible)
      if (
        err?.code === 'EXAM_TIME_OVER' ||
        err?.code === 'BUSINESS_RULE_VIOLATION' ||
        err?.message?.includes('timeout') ||
        err?.message?.includes('berakhir')
      ) {
        setRemainingSeconds(0);
        setIsFinished(true);
      }
    }
  };

  // Toggle Doubt (Ragu-ragu)
  const handleToggleDoubt = async (questionId: string) => {
    let updatedDoubts: string[];
    if (doubts.includes(questionId)) {
      updatedDoubts = doubts.filter((id) => id !== questionId);
    } else {
      updatedDoubts = [...doubts, questionId];
    }
    setDoubts(updatedDoubts);

    try {
      await cbtApi.saveStudentAnswer(examId, {
        question_id: questionId,
        answer: answers[questionId] ?? null,
        is_flagged: updatedDoubts.includes(questionId),
      });
    } catch (e: any) {
      console.error('Failed to save doubt flag:', e);
      if (
        e?.code === 'EXAM_TIME_OVER' ||
        e?.code === 'BUSINESS_RULE_VIOLATION' ||
        e?.message?.includes('timeout') ||
        e?.message?.includes('berakhir')
      ) {
        setRemainingSeconds(0);
        setIsFinished(true);
      }
    }
  };

  const handleConfirmFinish = async () => {
    setIsSubmitting(true);
    try {
      await cbtApi.finishStudentSession(examId);
      setShowConfirmModal(false);
      setIsFinished(true);
      clearOfflineQueues();
    } catch (err: any) {
      alert(err?.message || 'Gagal mengirim jawaban ujian.');
    } finally {
      setIsSubmitting(false);
    }
  };

  const toggleFullscreen = () => {
    if (!document.fullscreenElement) {
      document.documentElement.requestFullscreen().catch(() => {});
      setIsFullscreen(true);
    } else {
      document.exitFullscreen().catch(() => {});
      setIsFullscreen(false);
    }
  };

  if (isLoading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-slate-900 text-white">
        <div className="text-center space-y-3">
          <div className="animate-spin h-8 w-8 border-4 border-blue-500 border-t-transparent rounded-full mx-auto" />
          <p className="text-sm text-slate-300">Menyiapkan workspace ujian CBT digital...</p>
        </div>
      </div>
    );
  }

  if (loadError) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-slate-900 p-4">
        <div className="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl text-center space-y-4">
          <div className="h-12 w-12 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center mx-auto">
            <AlertTriangle className="h-6 w-6" />
          </div>
          <h2 className="text-base font-bold text-slate-900">Akses Ujian Ditolak</h2>
          <p className="text-xs text-slate-600">{loadError}</p>
          <Link href="/portal/siswa/cbt">
            <Button size="sm" className="bg-blue-600 hover:bg-blue-500 text-white text-xs">
              Kembali ke Katalog CBT
            </Button>
          </Link>
        </div>
      </div>
    );
  }

  if (isFinished) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-slate-900 p-4">
        <div className="w-full max-w-md rounded-2xl bg-white p-8 shadow-2xl text-center space-y-5">
          <div className="h-16 w-16 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center mx-auto">
            <CheckCircle2 className="h-8 w-8" />
          </div>
          <div>
            <h2 className="text-xl font-bold text-slate-900">Ujian Berhasil Dikumpulkan! 📋</h2>
            <p className="text-xs text-slate-600 mt-1">
              Seluruh lembar jawaban Anda telah tersimpan dengan aman di server sistem CBT Labschool UM.
            </p>
          </div>
          <div className="p-4 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-700 space-y-2">
            <div className="flex justify-between">
              <span>Total Soal:</span>
              <b>{questions.length} Butir</b>
            </div>
            <div className="flex justify-between">
              <span>Soal Terjawab:</span>
              <b className="text-blue-600">{Object.keys(answers).length} Butir</b>
            </div>
            <div className="flex justify-between">
              <span>Status Asesmen:</span>
              <span className="font-semibold text-emerald-600">Selesai (Submitted)</span>
            </div>
          </div>
          <Link href="/portal/siswa">
            <Button className="w-full bg-slate-900 hover:bg-slate-800 text-white text-xs">
              Kembali ke Portal Siswa
            </Button>
          </Link>
        </div>
      </div>
    );
  }

  if (isLocked) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-slate-900 p-4">
        <div className="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl text-center space-y-4 border-2 border-rose-500">
          <div className="h-16 w-16 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center mx-auto">
            <ShieldAlert className="h-8 w-8" />
          </div>
          <h2 className="text-lg font-bold text-slate-900">Sesi Ujian Terkunci 🔒</h2>
          <p className="text-xs text-slate-600">
            {lockedReason || 'Sesi ujian Anda telah dikunci oleh server karena indikasi pelanggaran aturan integritas ujian.'}
          </p>
          <div className="p-3 rounded-lg bg-rose-50 text-rose-800 text-xs font-semibold">
            Total Peringatan Pelanggaran: {violationCount} kali
          </div>
          <p className="text-[11px] text-slate-500">
            Silakan melapor kepada pengawas ruang ujian di laboratorium untuk verifikasi dan pembukaan kunci sesi pengerjaan.
          </p>
          <Link href="/portal/siswa/cbt">
            <Button size="sm" variant="outline" className="w-full text-xs">
              Kembali ke Katalog CBT
            </Button>
          </Link>
        </div>
      </div>
    );
  }

  const currentQ = questions[currentIndex];
  const answeredCount = Object.keys(answers).filter((k) => answers[k] !== null && answers[k] !== '').length;
  const doubtCount = doubts.length;
  const emptyCount = Math.max(0, questions.length - answeredCount);

  const hours = Math.floor(remainingSeconds / 3600);
  const minutes = Math.floor((remainingSeconds % 3600) / 60);
  const seconds = remainingSeconds % 60;
  const timerDisplay = `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
  const isUrgentTime = remainingSeconds < 300; // < 5 minutes

  return (
    <div className="min-h-screen bg-slate-100 flex flex-col font-sans select-none">
      {/* 1. Header Bar Ujian CBT (Sticky, Distraction-Free) */}
      <header className="sticky top-0 z-40 bg-slate-900 text-white border-b border-slate-800 shadow-md px-4 sm:px-6 h-16 flex items-center justify-between">
        <div className="flex items-center gap-3">
          <Badge className="bg-blue-600 text-white font-mono text-xs px-2.5 py-0.5">
            CBT-LAB
          </Badge>
          <span className="font-bold text-sm text-slate-100 truncate max-w-xs sm:max-w-md">
            Asesmen Digital Laboratorium
          </span>
        </div>

        {/* Center: Autosave Indicator & Timer */}
        <div className="flex items-center gap-4">
          <div className="hidden sm:flex items-center gap-1.5 text-xs text-slate-300">
            {autosaveStatus === 'saving' ? (
              <span className="flex items-center gap-1 text-amber-300">
                <span className="h-2 w-2 rounded-full bg-amber-400 animate-ping" /> Menyimpan...
              </span>
            ) : autosaveStatus === 'error' ? (
              <span className="flex items-center gap-1 text-rose-400">
                <span className="h-2 w-2 rounded-full bg-rose-500" /> Gagal simpan
              </span>
            ) : (
              <span className="flex items-center gap-1 text-emerald-400">
                <span className="h-2 w-2 rounded-full bg-emerald-500" /> Tersimpan ke server
              </span>
            )}
          </div>

          <div
            className={`flex items-center gap-2 px-3 py-1 rounded-lg border font-mono font-bold text-sm tracking-wider ${
              isUrgentTime
                ? 'bg-rose-950/80 border-rose-500 text-rose-300 animate-pulse'
                : 'bg-slate-800 border-slate-700 text-white'
            }`}
          >
            <Clock className="h-4 w-4 text-slate-400" />
            <span>{timerDisplay}</span>
          </div>
        </div>

        {/* User Meta & Actions */}
        <div className="flex items-center gap-2.5">
          <div className="hidden lg:block text-right">
            <div className="text-xs font-semibold text-slate-100">
              {user?.profile?.fullName || user?.profile?.name || user?.username || 'Siswa CBT'}
            </div>
            <div className="text-[10px] text-slate-400">NIS: {user?.profile?.nis || 'Siswa Aktif'}</div>
          </div>

          <Button
            size="sm"
            variant="ghost"
            onClick={toggleFullscreen}
            className="text-slate-300 hover:text-white hover:bg-slate-800 p-2 h-8 w-8"
            title="Layar Penuh"
          >
            {isFullscreen ? <Minimize2 className="h-4 w-4" /> : <Maximize2 className="h-4 w-4" />}
          </Button>

          <Link href="/portal/siswa/cbt">
            <Button
              size="sm"
              variant="outline"
              className="text-[11px] h-8 bg-transparent text-slate-300 border-slate-700 hover:bg-slate-800"
              onClick={(e) => {
                if (!confirm('Apakah Anda yakin ingin keluar dari ruang ujian?')) {
                  e.preventDefault();
                }
              }}
            >
              Keluar
            </Button>
          </Link>
        </div>
      </header>

      {/* 2. Main Workspace Layout */}
      <div className="flex-1 max-w-7xl w-full mx-auto p-4 sm:p-6 grid grid-cols-1 lg:grid-cols-4 gap-6">
        {/* Left Panel: Question Card & Interactive Answers (75%) */}
        <div className="lg:col-span-3 space-y-4">
          {currentQ && (
            <Card className="border border-slate-200 shadow-sm bg-white overflow-hidden">
              <div className="p-5 sm:p-6 space-y-6">
                {/* Question Header */}
                <div className="flex items-center justify-between border-b pb-4">
                  <div className="flex items-center gap-2.5">
                    <span className="px-3 py-1 rounded-md bg-blue-600 text-white font-bold text-xs tracking-wide">
                      Soal No. {currentIndex + 1}
                    </span>
                    <Badge variant="outline" className="text-xs bg-slate-50 uppercase font-semibold">
                      {currentQ.question_type === 'pg'
                        ? 'Pilihan Ganda Tunggal'
                        : currentQ.question_type === 'pg_kompleks'
                        ? 'Pilihan Ganda Kompleks'
                        : currentQ.question_type === 'bs'
                        ? 'Benar / Salah'
                        : currentQ.question_type === 'menjodohkan'
                        ? 'Menjodohkan'
                        : currentQ.question_type === 'isian'
                        ? 'Isian Singkat'
                        : 'Uraian / Esai'}
                    </Badge>
                  </div>

                  <span className="text-xs text-slate-500 font-medium">
                    Bobot Nilai Butir Soal
                  </span>
                </div>

                {/* Question Stem Text */}
                <div className="text-sm font-medium text-slate-900 leading-relaxed whitespace-pre-line">
                  {currentQ.stem}
                </div>

                {/* Question Stimulus Media / Image */}
                {(currentQ.body?.media_url || currentQ.body?.image_url || (currentQ as any).media_url) && (
                  <div className="my-3 rounded-xl overflow-hidden border border-slate-200 bg-slate-50 p-2 max-w-xl">
                    <img
                      src={currentQ.body?.media_url || currentQ.body?.image_url || (currentQ as any).media_url}
                      alt="Gambar / Stimulus Soal"
                      className="w-full h-auto max-h-80 object-contain rounded-lg mx-auto"
                    />
                  </div>
                )}

                {/* Question Options Renderer (6 Models) */}
                <div className="space-y-3 pt-2">
                  {/* Model 1: Pilihan Ganda Tunggal (PG) */}
                  {currentQ.question_type === 'pg' && currentQ.options && (
                    <div className="space-y-2.5">
                      {currentQ.options.map((opt) => {
                        const isSelected =
                          answers[currentQ.question_id] === opt.id ||
                          answers[currentQ.question_id]?.selected_option_id === opt.id;

                        return (
                          <button
                            key={opt.id}
                            type="button"
                            onClick={() => handleAnswerChange(currentQ.question_id, opt.id)}
                            className={`w-full text-left p-3.5 rounded-xl border transition-all flex items-start gap-3 ${
                              isSelected
                                ? 'border-blue-600 bg-blue-50/80 ring-2 ring-blue-500/20'
                                : 'border-slate-200 hover:border-slate-300 bg-white'
                            }`}
                          >
                            <span
                              className={`flex items-center justify-center h-6 w-6 rounded-full text-xs font-bold shrink-0 transition-colors ${
                                isSelected
                                  ? 'bg-blue-600 text-white'
                                  : 'bg-slate-100 text-slate-700'
                              }`}
                            >
                              {opt.label}
                            </span>
                            <span className="text-xs text-slate-800 font-medium mt-0.5">
                              {opt.text}
                            </span>
                          </button>
                        );
                      })}
                    </div>
                  )}

                  {/* Model 2: Pilihan Ganda Kompleks (Multi Select) */}
                  {currentQ.question_type === 'pg_kompleks' && currentQ.options && (
                    <div className="space-y-2.5">
                      <p className="text-[11px] text-slate-500 mb-1">
                        Pilih satu atau lebih jawaban yang benar:
                      </p>
                      {currentQ.options.map((opt) => {
                        const currentRaw = answers[currentQ.question_id];
                        const currentArr = Array.isArray(currentRaw)
                          ? currentRaw
                          : (Array.isArray(currentRaw?.selected_option_ids) ? currentRaw.selected_option_ids : []);
                        const isSelected = currentArr.includes(opt.id);

                        return (
                          <button
                            key={opt.id}
                            type="button"
                            onClick={() => {
                              const newArr = isSelected
                                ? currentArr.filter((id: string) => id !== opt.id)
                                : [...currentArr, opt.id];
                              handleAnswerChange(currentQ.question_id, { selected_option_ids: newArr });
                            }}
                            className={`w-full text-left p-3.5 rounded-xl border transition-all flex items-start gap-3 ${
                              isSelected
                                ? 'border-blue-600 bg-blue-50/80 ring-2 ring-blue-500/20'
                                : 'border-slate-200 hover:border-slate-300 bg-white'
                            }`}
                          >
                            <div
                              className={`flex items-center justify-center h-5 w-5 rounded border text-xs shrink-0 mt-0.5 ${
                                isSelected
                                  ? 'bg-blue-600 border-blue-600 text-white'
                                  : 'border-slate-300 bg-white'
                              }`}
                            >
                              {isSelected && <Check className="h-3.5 w-3.5 stroke-[3]" />}
                            </div>
                            <span className="text-xs text-slate-800 font-medium">
                              <b className="mr-1">{opt.label}.</b> {opt.text}
                            </span>
                          </button>
                        );
                      })}
                    </div>
                  )}

                  {/* Model 3: Benar / Salah (BS) */}
                  {currentQ.question_type === 'bs' && (
                    <div className="grid grid-cols-2 gap-3 pt-2">
                      {['Benar', 'Salah'].map((val) => {
                        const matchedOpt = currentQ.options?.find(
                          (o) => o.text?.toLowerCase() === val.toLowerCase()
                        );
                        const isSelected =
                          answers[currentQ.question_id] === val ||
                          answers[currentQ.question_id] === matchedOpt?.id ||
                          answers[currentQ.question_id]?.value === val ||
                          answers[currentQ.question_id]?.selected_option_id === matchedOpt?.id;

                        return (
                          <button
                            key={val}
                            type="button"
                            onClick={() => {
                              const ansVal = matchedOpt ? { selected_option_id: matchedOpt.id, value: val } : val;
                              handleAnswerChange(currentQ.question_id, ansVal);
                            }}
                            className={`p-4 rounded-xl border text-center transition-all ${
                              isSelected
                                ? val === 'Benar'
                                  ? 'border-emerald-500 bg-emerald-50 text-emerald-900 ring-2 ring-emerald-500/20 font-bold'
                                  : 'border-rose-500 bg-rose-50 text-rose-900 ring-2 ring-rose-500/20 font-bold'
                                : 'border-slate-200 hover:border-slate-300 bg-white text-slate-700 font-semibold'
                            }`}
                          >
                            <span className="text-sm">{val}</span>
                          </button>
                        );
                      })}
                    </div>
                  )}

                  {/* Model 4: Menjodohkan (Premise & Matching Key) */}
                  {currentQ.question_type === 'menjodohkan' && currentQ.options && (
                    <div className="space-y-3 pt-1">
                      <p className="text-[11px] text-slate-500">
                        Pilih pasangan yang cocok untuk setiap premis di kolom kiri:
                      </p>
                      {currentQ.options.map((opt, i) => {
                        const currentMap = (typeof answers[currentQ.question_id] === 'object' && answers[currentQ.question_id] !== null)
                          ? (answers[currentQ.question_id]?.pairs ?? answers[currentQ.question_id])
                          : {};
                        const selectedMatch = currentMap[opt.id] || currentMap[opt.label] || '';

                        const availableTargets = ((currentQ.body as any)?.targets && Array.isArray((currentQ.body as any).targets))
                          ? (currentQ.body as any).targets
                          : currentQ.options?.map((t: any) => t.match_key || t.text);

                        return (
                          <div
                            key={opt.id || i}
                            className="p-3 rounded-lg border border-slate-200 bg-slate-50/50 flex flex-col sm:flex-row sm:items-center justify-between gap-3"
                          >
                            <div className="text-xs font-semibold text-slate-800">
                              <span className="text-slate-400 mr-1.5">{i + 1}.</span>
                              {opt.text}
                            </div>
                            <select
                              value={selectedMatch}
                              onChange={(e) => {
                                const newMap = { ...currentMap, [opt.id]: e.target.value };
                                handleAnswerChange(currentQ.question_id, { pairs: newMap });
                              }}
                              className="text-xs rounded-md border border-slate-300 p-2 bg-white sm:w-60"
                            >
                              <option value="">Pilih Pasangan...</option>
                              {availableTargets?.map((targetVal: string, tIdx: number) => (
                                <option key={tIdx} value={targetVal}>
                                  {targetVal}
                                </option>
                              ))}
                            </select>
                          </div>
                        );
                      })}
                    </div>
                  )}

                  {/* Model 5: Isian Singkat */}
                  {currentQ.question_type === 'isian' && (
                    <div className="space-y-2 pt-2">
                      <label className="text-xs font-semibold text-slate-700">Tulis Jawaban Singkat:</label>
                      <Input
                        value={typeof answers[currentQ.question_id] === 'object' ? (answers[currentQ.question_id]?.text ?? '') : (answers[currentQ.question_id] || '')}
                        onChange={(e) => handleAnswerChange(currentQ.question_id, { text: e.target.value })}
                        placeholder="Ketikkan jawaban Anda di sini..."
                        className="text-xs h-10 max-w-md"
                      />
                    </div>
                  )}

                  {/* Model 6: Uraian / Esai */}
                  {currentQ.question_type === 'uraian' && (
                    <div className="space-y-2 pt-2">
                      <label className="text-xs font-semibold text-slate-700">
                        Tuliskan Uraian / Jawaban Lengkap:
                      </label>
                      <Textarea
                        rows={6}
                        value={typeof answers[currentQ.question_id] === 'object' ? (answers[currentQ.question_id]?.text ?? '') : (answers[currentQ.question_id] || '')}
                        onChange={(e) => handleAnswerChange(currentQ.question_id, { text: e.target.value })}
                        placeholder="Tuliskan penjelasan dan argumen jawaban Anda di sini..."
                        className="text-xs leading-relaxed"
                      />
                    </div>
                  )}
                </div>

                {/* Action Bar (Ragu-Ragu & Navigasi) */}
                <div className="pt-6 border-t flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                  <label className="flex items-center gap-2 cursor-pointer text-xs font-medium text-slate-700">
                    <input
                      type="checkbox"
                      checked={doubts.includes(currentQ.question_id)}
                      onChange={() => handleToggleDoubt(currentQ.question_id)}
                      className="h-4 w-4 text-amber-500 rounded border-slate-300"
                    />
                    <span className="flex items-center gap-1.5 text-amber-800 bg-amber-50 border border-amber-200 px-2 py-1 rounded">
                      Tandai Ragu-Ragu (Kuning)
                    </span>
                  </label>

                  <div className="flex items-center gap-2 self-end sm:self-auto">
                    <Button
                      size="sm"
                      variant="outline"
                      disabled={currentIndex === 0}
                      onClick={() => setCurrentIndex((i) => i - 1)}
                      className="text-xs gap-1"
                    >
                      <ArrowLeft className="h-3.5 w-3.5" /> Sebelumnya
                    </Button>

                    {currentIndex === questions.length - 1 ? (
                      <Button
                        size="sm"
                        onClick={() => setShowConfirmModal(true)}
                        className="bg-emerald-600 hover:bg-emerald-500 text-white text-xs gap-1 font-semibold"
                      >
                        Tinjau & Kumpulkan <CheckCircle2 className="h-3.5 w-3.5" />
                      </Button>
                    ) : (
                      <Button
                        size="sm"
                        onClick={() => setCurrentIndex((i) => i + 1)}
                        className="bg-blue-600 hover:bg-blue-500 text-white text-xs gap-1 font-semibold"
                      >
                        Berikutnya <ArrowRight className="h-3.5 w-3.5" />
                      </Button>
                    )}
                  </div>
                </div>
              </div>
            </Card>
          )}
        </div>

        {/* Right Panel: Question Map Grid (25%) */}
        <div className="lg:col-span-1 space-y-4">
          <Card className="border border-slate-200 shadow-sm bg-white">
            <CardHeader className="pb-3 border-b">
              <div className="flex items-center justify-between">
                <CardTitle className="text-xs font-bold uppercase text-slate-700">
                  Peta Butir Soal
                </CardTitle>
                <Badge variant="outline" className="text-[10px]">
                  {questions.length} Butir
                </Badge>
              </div>
            </CardHeader>
            <CardContent className="p-4 space-y-4">
              {/* Number Buttons Grid */}
              <div className="grid grid-cols-5 gap-2">
                {questions.map((q, idx) => {
                  const isCurrent = idx === currentIndex;
                  const isAnswered = answers[q.question_id] !== undefined && answers[q.question_id] !== null && answers[q.question_id] !== '';
                  const isDoubt = doubts.includes(q.question_id);

                  let bgStyle = 'bg-slate-100 text-slate-700 hover:bg-slate-200 border-slate-200';
                  if (isDoubt) {
                    bgStyle = 'bg-amber-400 text-amber-950 font-bold border-amber-500';
                  } else if (isAnswered) {
                    bgStyle = 'bg-blue-600 text-white font-bold border-blue-600';
                  }

                  return (
                    <button
                      key={q.question_id}
                      type="button"
                      onClick={() => setCurrentIndex(idx)}
                      className={`h-9 rounded-lg border text-xs transition-all flex items-center justify-center ${bgStyle} ${
                        isCurrent ? 'ring-2 ring-offset-2 ring-slate-900 scale-105' : ''
                      }`}
                    >
                      {idx + 1}
                    </button>
                  );
                })}
              </div>

              {/* Status Legend */}
              <div className="space-y-2 pt-2 border-t text-xs text-slate-600">
                <div className="flex items-center justify-between">
                  <span className="flex items-center gap-1.5">
                    <span className="h-3 w-3 rounded bg-blue-600" /> Sudah Dijawab:
                  </span>
                  <b>{answeredCount}</b>
                </div>
                <div className="flex items-center justify-between">
                  <span className="flex items-center gap-1.5">
                    <span className="h-3 w-3 rounded bg-amber-400" /> Ragu-Ragu:
                  </span>
                  <b>{doubtCount}</b>
                </div>
                <div className="flex items-center justify-between">
                  <span className="flex items-center gap-1.5">
                    <span className="h-3 w-3 rounded bg-slate-200" /> Belum Dijawab:
                  </span>
                  <b>{emptyCount}</b>
                </div>
              </div>

              {/* Submit Final Button */}
              <Button
                onClick={() => setShowConfirmModal(true)}
                className="w-full bg-slate-900 hover:bg-slate-800 text-white text-xs font-semibold py-2.5 mt-2"
              >
                Kumpulkan Ujian ➔
              </Button>
            </CardContent>
          </Card>
        </div>
      </div>

      {/* 3. Anti-Cheat Violation Warning Modal */}
      {showViolationModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/75 p-4 animate-in fade-in duration-200">
          <div className="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl text-center space-y-4 border-2 border-rose-500">
            <div className="h-16 w-16 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center mx-auto animate-bounce">
              <ShieldAlert className="h-8 w-8" />
            </div>

            <div>
              <h2 className="text-lg font-bold text-rose-600">
                PERINGATAN INTEGRITAS UJIAN!
              </h2>
              <p className="text-xs text-slate-600 mt-1 leading-relaxed">
                Terdeteksi perpindahan tab browser atau fokus layar keluar dari area ujian CBT Laboratorium.
              </p>
            </div>

            <div className="p-2.5 rounded-lg bg-rose-50 border border-rose-200 text-rose-800 font-bold text-xs inline-block">
              Total Peringatan: {violationCount} Kali
            </div>

            <p className="text-[11px] text-slate-400">
              Aktivitas ini dicatat otomatis ke log audit pengawas ujian. Akumulasi pelanggaran berulang dapat menyebabkan sesi ujian dikunci oleh pengawas lab.
            </p>

            <Button
              onClick={() => setShowViolationModal(false)}
              className="w-full bg-rose-600 hover:bg-rose-500 text-white text-xs font-semibold"
            >
              Saya Mengerti & Kembali ke Soal
            </Button>
          </div>
        </div>
      )}

      {/* 4. Finish Confirmation Modal */}
      {showConfirmModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
          <div className="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl space-y-4">
            <div className="text-center space-y-1.5">
              <span className="text-3xl">📋</span>
              <h3 className="font-bold text-slate-900 text-base">Konfirmasi Pengumpulan Jawaban</h3>
              <p className="text-xs text-slate-500">
                Periksa kembali ringkasan pengerjaan Anda sebelum mengakhiri sesi ujian:
              </p>
            </div>

            <div className="p-4 rounded-xl bg-slate-50 border border-slate-200 text-xs space-y-2">
              <div className="flex justify-between">
                <span className="text-slate-600">Soal Terjawab:</span>
                <b className="text-blue-600">{answeredCount} Butir</b>
              </div>
              <div className="flex justify-between">
                <span className="text-slate-600">Soal Ragu-Ragu:</span>
                <b className="text-amber-600">{doubtCount} Butir</b>
              </div>
              <div className="flex justify-between">
                <span className="text-slate-600">Soal Kosong:</span>
                <b className="text-rose-600">{emptyCount} Butir</b>
              </div>
            </div>

            {emptyCount > 0 && (
              <p className="text-[11px] text-amber-700 bg-amber-50 p-2.5 rounded border border-amber-200">
                Perhatian: Masih terdapat {emptyCount} soal yang belum Anda jawab. Jawaban kosong tidak akan mendapatkan poin.
              </p>
            )}

            <div className="flex items-center justify-end gap-2 pt-2 border-t">
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => setShowConfirmModal(false)}
                className="text-xs"
              >
                Kembali Periksa
              </Button>
              <Button
                type="button"
                size="sm"
                disabled={isSubmitting}
                onClick={handleConfirmFinish}
                className="bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold"
              >
                {isSubmitting ? 'Mengumpulkan...' : 'Ya, Kumpulkan Sekarang'}
              </Button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
