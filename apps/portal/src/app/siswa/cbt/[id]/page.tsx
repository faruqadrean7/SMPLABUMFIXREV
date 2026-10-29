'use client';

import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useParams, useRouter } from 'next/navigation';
import {
  Clock,
  Flag,
  ShieldAlert,
  CheckCircle2,
  Loader2,
  KeyRound,
  Send,
  ChevronLeft,
  ChevronRight,
} from 'lucide-react';
import {
  startExamSession,
  getSessionQuestions,
  saveExamAnswer,
  logExamViolation,
  finishExamSession,
  type CbtQuestion,
  type CbtSessionQuestions,
  type CbtSessionStart,
} from '@/lib/cbt-session-api';
import { ErrorState } from '@/components/ui/error-state';

type Phase = 'token' | 'loading' | 'active' | 'submitting' | 'done';

function qid(q: CbtQuestion): string {
  return String(q.question_id ?? q.id ?? '');
}
function getErrMsg(e: unknown, fallback: string): string {
  const any = e as { response?: { data?: { message?: string; error?: { message?: string } } } };
  return any?.response?.data?.error?.message || any?.response?.data?.message || fallback;
}
function fmt(sec: number): string {
  const s = Math.max(0, sec);
  const h = Math.floor(s / 3600);
  const m = Math.floor((s % 3600) / 60);
  const d = s % 60;
  return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(d).padStart(2, '0')}`;
}

type PgAnswer = { selected_option_id?: string };
type PgkAnswer = { selected_option_ids?: string[] };
type TextAnswer = { text?: string };
type MatchAnswer = { pairs?: Record<string, string> };

export default function StudentCbtWorkspacePage() {
  const params = useParams<{ id: string }>();
  const examId = String(params?.id ?? '');
  const router = useRouter();

  const [phase, setPhase] = useState<Phase>('token');
  const [token, setToken] = useState('');
  const [errorMsg, setErrorMsg] = useState<string | null>(null);
  const [examTitle, setExamTitle] = useState('Ujian CBT');
  const [questions, setQuestions] = useState<CbtQuestion[]>([]);
  const [currentIdx, setCurrentIdx] = useState(0);
  const [answers, setAnswers] = useState<Record<string, unknown>>({});
  const [flags, setFlags] = useState<Record<string, boolean>>({});
  const [endsAt, setEndsAt] = useState<number | null>(null);
  const [remaining, setRemaining] = useState(0);
  const [violations, setViolations] = useState(0);
  const [saving, setSaving] = useState(false);
  const [showSubmit, setShowSubmit] = useState(false);

  const hydrate = useCallback((start: CbtSessionStart, q: CbtSessionQuestions) => {
    setExamTitle(q.exam_title || start.exam_title || 'Ujian CBT');
    const qs = q.questions ?? [];
    setQuestions(qs);
    const a: Record<string, unknown> = {};
    const f: Record<string, boolean> = {};
    qs.forEach((it) => {
      const id = qid(it);
      const restored = it.current_answer ?? it.user_answer;
      if (restored != null) a[id] = restored;
      if (it.is_flagged) f[id] = true;
    });
    setAnswers(a);
    setFlags(f);
    const rem = Number(q.remaining_seconds ?? start.remaining_seconds ?? 0);
    const ea = q.ends_at ? Date.parse(q.ends_at) : start.ends_at ? Date.parse(start.ends_at) : Date.now() + rem * 1000;
    setEndsAt(ea);
    setRemaining(Math.max(0, Math.round((ea - Date.now()) / 1000)));
    setViolations(Number(q.violation_count ?? start.violation_count ?? 0));
  }, []);

  const handleStart = useCallback(async () => {
    if (!token.trim()) {
      setErrorMsg('Token ruang ujian wajib diisi.');
      return;
    }
    setErrorMsg(null);
    setPhase('loading');
    try {
      const start = await startExamSession(examId, token.trim());
      const q = await getSessionQuestions(examId);
      hydrate(start, q);
      setPhase('active');
    } catch (e) {
      setErrorMsg(getErrMsg(e, 'Gagal memulai sesi ujian. Pastikan token ruang benar dan ujian sedang berlangsung.'));
      setPhase('token');
    }
  }, [examId, token, hydrate]);

  const submit = useCallback(async () => {
    setShowSubmit(false);
    setPhase('submitting');
    try {
      await finishExamSession(examId);
      setPhase('done');
    } catch (e) {
      setErrorMsg(getErrMsg(e, 'Gagal menyelesaikan ujian. Coba lagi.'));
      setPhase('active');
    }
  }, [examId]);

  // Timer berbasis waktu server (ends_at). Auto-submit saat habis.
  useEffect(() => {
    if (phase !== 'active' || endsAt == null) return;
    const t = setInterval(() => {
      const r = Math.max(0, Math.round((endsAt - Date.now()) / 1000));
      setRemaining(r);
      if (r <= 0) {
        clearInterval(t);
        void submit();
      }
    }, 1000);
    return () => clearInterval(t);
  }, [phase, endsAt, submit]);

  // Anti-cheat: catat saat siswa meninggalkan tab.
  useEffect(() => {
    if (phase !== 'active') return;
    const onHide = () => {
      if (document.visibilityState === 'hidden') {
        setViolations((v) => v + 1);
        void logExamViolation(examId, { violation_type: 'tab_switch', detail: 'Siswa meninggalkan tab ujian' }).catch(() => undefined);
      }
    };
    document.addEventListener('visibilitychange', onHide);
    return () => document.removeEventListener('visibilitychange', onHide);
  }, [phase, examId]);

  // Autosave ter-debounce per soal.
  const saveTimers = useRef<Record<string, ReturnType<typeof setTimeout>>>({});
  const scheduleSave = useCallback(
    (id: string, answerVal: unknown, flagged: boolean) => {
      if (saveTimers.current[id]) clearTimeout(saveTimers.current[id]);
      saveTimers.current[id] = setTimeout(async () => {
        setSaving(true);
        try {
          await saveExamAnswer(examId, {
            question_id: id,
            answer: answerVal,
            is_flagged: flagged,
            answered_at: new Date().toISOString(),
          });
        } catch {
          // diabaikan; akan tersimpan ulang pada perubahan berikutnya
        } finally {
          setSaving(false);
        }
      }, 700);
    },
    [examId]
  );

  const setAnswer = useCallback(
    (id: string, val: unknown) => {
      setAnswers((prev) => ({ ...prev, [id]: val }));
      scheduleSave(id, val, !!flags[id]);
    },
    [flags, scheduleSave]
  );

  const toggleFlag = useCallback(
    (id: string) => {
      setFlags((prev) => {
        const nf = { ...prev, [id]: !prev[id] };
        scheduleSave(id, answers[id] ?? null, !!nf[id]);
        return nf;
      });
    },
    [answers, scheduleSave]
  );

  const answeredCount = useMemo(
    () => questions.filter((q) => answers[qid(q)] != null).length,
    [questions, answers]
  );

  // ---- RENDER PER FASE ----
  if (phase === 'token') {
    return (
      <div className="max-w-md mx-auto mt-10 bg-white border border-slate-200 rounded-2xl p-6 shadow-xs">
        <div className="flex items-center gap-2 mb-1">
          <KeyRound className="w-5 h-5 text-blue-900" />
          <h1 className="text-lg font-extrabold text-slate-900">Masuk Sesi Ujian</h1>
        </div>
        <p className="text-xs text-slate-600 mb-4">Masukkan token ruang ujian yang dibacakan pengawas untuk memulai.</p>
        <input
          value={token}
          onChange={(e) => setToken(e.target.value.toUpperCase())}
          placeholder="TOKEN RUANG"
          className="w-full px-3 py-2.5 rounded-xl border border-slate-300 font-mono tracking-widest text-center text-lg uppercase focus:outline-none focus:ring-2 focus:ring-blue-900"
          maxLength={20}
        />
        {errorMsg && <p className="text-xs text-rose-700 mt-2">{errorMsg}</p>}
        <button
          onClick={handleStart}
          className="mt-4 w-full py-2.5 rounded-xl bg-blue-900 hover:bg-blue-950 text-white text-sm font-bold"
        >
          Mulai Ujian
        </button>
      </div>
    );
  }

  if (phase === 'loading' || phase === 'submitting') {
    return (
      <div className="flex flex-col items-center justify-center mt-24 gap-3 text-slate-600">
        <Loader2 className="w-8 h-8 animate-spin text-blue-900" />
        <p className="text-sm font-semibold">{phase === 'loading' ? 'Memuat sesi ujian…' : 'Mengunci & mengirim jawaban…'}</p>
      </div>
    );
  }

  if (phase === 'done') {
    return (
      <div className="max-w-md mx-auto mt-16 bg-white border border-slate-200 rounded-2xl p-6 shadow-xs text-center">
        <CheckCircle2 className="w-12 h-12 text-emerald-700 mx-auto" />
        <h1 className="text-lg font-extrabold text-slate-900 mt-3">Ujian Selesai</h1>
        <p className="text-sm text-slate-600 mt-1">Jawabanmu telah terkunci dan terkirim ke server. Terima kasih.</p>
        <button onClick={() => router.push('/siswa/cbt')} className="mt-5 px-4 py-2.5 rounded-xl bg-slate-900 text-white text-sm font-bold">
          Kembali ke Daftar Ujian
        </button>
      </div>
    );
  }

  const q = questions[currentIdx];
  const id = q ? qid(q) : '';
  const type = q?.question_type ?? 'pg';
  const cur = answers[id];
  const stem = String(q?.stem || (q?.body as { content?: string; text?: string })?.content || (q?.body as { text?: string })?.text || q?.content || '');
  const targets = ((q?.body as { targets?: string[] })?.targets ?? []) as string[];

  return (
    <div className="flex flex-col gap-3 max-w-5xl mx-auto">
      {/* HEADER */}
      <div className="sticky top-0 z-10 bg-white border border-slate-200 rounded-2xl px-4 py-3 shadow-xs flex items-center justify-between gap-3">
        <div className="min-w-0">
          <h1 className="text-sm font-extrabold text-slate-900 truncate">{examTitle}</h1>
          <p className="text-xs text-slate-500">
            Terjawab {answeredCount}/{questions.length}
            {saving && <span className="ml-2 text-blue-700">• menyimpan…</span>}
          </p>
        </div>
        <div className="flex items-center gap-2 shrink-0">
          {violations > 0 && (
            <span className="flex items-center gap-1 px-2 py-1 rounded-lg bg-rose-100 text-rose-800 text-xs font-bold">
              <ShieldAlert className="w-3.5 h-3.5" /> {violations}
            </span>
          )}
          <span className={`flex items-center gap-1.5 px-3 py-1.5 rounded-lg font-mono font-extrabold text-sm ${remaining <= 300 ? 'bg-rose-700 text-white' : 'bg-slate-900 text-white'}`}>
            <Clock className="w-4 h-4" /> {fmt(remaining)}
          </span>
        </div>
      </div>

      {/* NAV GRID */}
      <div className="bg-white border border-slate-200 rounded-2xl p-3 shadow-xs flex flex-wrap gap-1.5">
        {questions.map((item, i) => {
          const iid = qid(item);
          const done = answers[iid] != null;
          const fl = flags[iid];
          const active = i === currentIdx;
          return (
            <button
              key={iid || i}
              onClick={() => setCurrentIdx(i)}
              className={`w-8 h-8 rounded-lg text-xs font-bold border relative ${
                active ? 'bg-blue-900 text-white border-blue-900' : done ? 'bg-emerald-100 text-emerald-800 border-emerald-300' : 'bg-white text-slate-600 border-slate-300'
              }`}
            >
              {i + 1}
              {fl && <Flag className="w-2.5 h-2.5 text-amber-500 absolute -top-1 -right-1 fill-amber-400" />}
            </button>
          );
        })}
      </div>

      {/* SOAL */}
      <div className="bg-white border border-slate-200 rounded-2xl p-5 shadow-xs min-h-75">
        {!q ? (
          <ErrorState title="Soal Tidak Tersedia" message="Butir soal tidak dapat dimuat." />
        ) : (
          <>
            <div className="flex items-start justify-between gap-3 pb-3 border-b border-slate-100">
              <span className="text-xs font-bold text-slate-500">
                Soal {currentIdx + 1} • {String(type).toUpperCase()} {q.weight ? `• ${q.weight} poin` : ''}
              </span>
              <button
                onClick={() => toggleFlag(id)}
                className={`flex items-center gap-1 px-2 py-1 rounded-lg text-xs font-bold border ${flags[id] ? 'bg-amber-100 text-amber-800 border-amber-300' : 'text-slate-600 border-slate-300'}`}
              >
                <Flag className="w-3.5 h-3.5" /> {flags[id] ? 'Ditandai' : 'Tandai'}
              </button>
            </div>

            <p className="text-sm text-slate-900 leading-relaxed whitespace-pre-wrap my-4">{stem}</p>

            {/* PG & BENAR/SALAH (opsi tunggal) */}
            {(type === 'pg' || type === 'bs') && (
              <div className="space-y-2">
                {(q.options ?? []).map((opt) => {
                  const optId = String(opt.id ?? opt.key ?? '');
                  const selected = (cur as PgAnswer)?.selected_option_id === optId;
                  return (
                    <label key={optId} className={`flex items-center gap-3 p-3 rounded-xl border cursor-pointer ${selected ? 'border-blue-900 bg-blue-50' : 'border-slate-200 hover:bg-slate-50'}`}>
                      <input type="radio" name={`q-${id}`} checked={selected} onChange={() => setAnswer(id, { selected_option_id: optId })} className="accent-blue-900" />
                      <span className="text-sm text-slate-800">{String(opt.text ?? opt.label ?? '')}</span>
                    </label>
                  );
                })}
              </div>
            )}

            {/* PG KOMPLEKS (opsi jamak) */}
            {type === 'pg_kompleks' && (
              <div className="space-y-2">
                {(q.options ?? []).map((opt) => {
                  const optId = String(opt.id ?? opt.key ?? '');
                  const ids = ((cur as PgkAnswer)?.selected_option_ids ?? []) as string[];
                  const checked = ids.includes(optId);
                  return (
                    <label key={optId} className={`flex items-center gap-3 p-3 rounded-xl border cursor-pointer ${checked ? 'border-blue-900 bg-blue-50' : 'border-slate-200 hover:bg-slate-50'}`}>
                      <input
                        type="checkbox"
                        checked={checked}
                        onChange={() => {
                          const next = checked ? ids.filter((x) => x !== optId) : [...ids, optId];
                          setAnswer(id, { selected_option_ids: next });
                        }}
                        className="accent-blue-900"
                      />
                      <span className="text-sm text-slate-800">{String(opt.text ?? opt.label ?? '')}</span>
                    </label>
                  );
                })}
              </div>
            )}

            {/* MENJODOHKAN */}
            {type === 'menjodohkan' && (
              <div className="space-y-2">
                {(q.options ?? []).map((opt) => {
                  const optId = String(opt.id ?? opt.key ?? '');
                  const pairs = ((cur as MatchAnswer)?.pairs ?? {}) as Record<string, string>;
                  return (
                    <div key={optId} className="flex flex-col sm:flex-row sm:items-center gap-2 p-3 rounded-xl border border-slate-200">
                      <span className="text-sm text-slate-800 flex-1">{String(opt.text ?? opt.label ?? '')}</span>
                      <select
                        value={pairs[optId] ?? ''}
                        onChange={(e) => setAnswer(id, { pairs: { ...pairs, [optId]: e.target.value } })}
                        className="px-3 py-2 rounded-lg border border-slate-300 text-sm bg-white sm:w-72"
                      >
                        <option value="">— Pilih pasangan —</option>
                        {targets.map((t, ti) => (
                          <option key={ti} value={t}>{t}</option>
                        ))}
                      </select>
                    </div>
                  );
                })}
              </div>
            )}

            {/* ISIAN SINGKAT */}
            {type === 'isian' && (
              <input
                value={(cur as TextAnswer)?.text ?? ''}
                onChange={(e) => setAnswer(id, { text: e.target.value })}
                placeholder="Ketik jawaban singkat…"
                className="w-full px-3 py-2.5 rounded-xl border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-900"
              />
            )}

            {/* URAIAN */}
            {type === 'uraian' && (
              <textarea
                value={(cur as TextAnswer)?.text ?? ''}
                onChange={(e) => setAnswer(id, { text: e.target.value })}
                rows={8}
                placeholder="Tulis jawaban uraianmu…"
                className="w-full px-3 py-2.5 rounded-xl border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-900"
              />
            )}
          </>
        )}
      </div>

      {/* FOOTER NAV */}
      <div className="flex items-center justify-between gap-2">
        <button
          onClick={() => setCurrentIdx((i) => Math.max(0, i - 1))}
          disabled={currentIdx === 0}
          className="px-3 py-2.5 rounded-xl border border-slate-300 text-sm font-bold text-slate-700 disabled:opacity-40 flex items-center gap-1"
        >
          <ChevronLeft className="w-4 h-4" /> Sebelumnya
        </button>
        {currentIdx < questions.length - 1 ? (
          <button
            onClick={() => setCurrentIdx((i) => Math.min(questions.length - 1, i + 1))}
            className="px-3 py-2.5 rounded-xl bg-blue-900 text-white text-sm font-bold flex items-center gap-1"
          >
            Berikutnya <ChevronRight className="w-4 h-4" />
          </button>
        ) : (
          <button onClick={() => setShowSubmit(true)} className="px-4 py-2.5 rounded-xl bg-emerald-800 hover:bg-emerald-900 text-white text-sm font-bold flex items-center gap-1.5">
            <Send className="w-4 h-4" /> Selesai &amp; Kumpulkan
          </button>
        )}
      </div>

      {/* MODAL SUBMIT */}
      {showSubmit && (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded-2xl p-5 max-w-sm w-full shadow-xl">
            <h3 className="text-base font-extrabold text-slate-900">Kumpulkan Ujian?</h3>
            <p className="text-sm text-slate-600 mt-1">
              Kamu menjawab {answeredCount} dari {questions.length} soal. Setelah dikumpulkan, jawaban terkunci dan tidak dapat diubah.
            </p>
            <div className="flex gap-2 mt-4">
              <button onClick={() => setShowSubmit(false)} className="flex-1 py-2.5 rounded-xl border border-slate-300 text-sm font-bold text-slate-700">
                Batal
              </button>
              <button onClick={() => submit()} className="flex-1 py-2.5 rounded-xl bg-emerald-800 text-white text-sm font-bold">
                Ya, Kumpulkan
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
