'use client';

// ---------------------------------------------------------------------------
// Lapisan API sesi CBT siswa (sinkron dengan backend ExamSessionController).
// Fungsi plain (bukan hook) karena halaman ujian mengelola siklus hidupnya
// sendiri (timer server, autosave, anti-cheat). Semua lewat apiClient sehingga
// ikut mendapat Bearer token + refresh-token.
//
// PENTING: halaman workspace ujian (app/siswa/cbt/[id]/page.tsx) masih memakai
// data mock (CBT_ACTIVE_EXAM_QUESTIONS). Rewiring UI-nya ke fungsi di sini WAJIB
// diuji runtime (end-to-end ujian) di mesin dev sebelum dipakai ujian nyata,
// karena pemetaan 5 tipe soal & autosave bersifat kritis-integritas.
// ---------------------------------------------------------------------------

import { apiClient } from './api-client';
import { ApiResponse } from '@/types/auth';

export interface CbtSessionMeta {
  session_id: string;
  exam_id: string;
  exam_title?: string;
  room_name?: string;
  status: string;
  remaining_seconds: number;
  ends_at?: string | null;
  deadline_at?: string | null;
  server_time?: string | null;
  violation_count?: number;
  session_no?: number;
  seed?: string | number;
  started_at?: string | null;
  student_id?: string;
  student_name?: string;
}

export interface CbtSavedAnswer {
  answer: unknown;
  is_flagged?: boolean;
  answered_at?: string | null;
}

export interface CbtSessionStart extends CbtSessionMeta {
  answers?: Record<string, CbtSavedAnswer>;
}

export interface CbtOption {
  key?: string;
  label?: string;
  text?: string;
  [k: string]: unknown;
}

/** Soal diserialkan backend tanpa kunci jawaban. Dibuat permisif karena
 *  mendukung banyak tipe (pilihan ganda, kompleks, menjodohkan, B/S, uraian). */
export interface CbtQuestion {
  id?: string;
  question_id?: string;
  number?: number;
  question_type?: string;
  content?: string;
  stem?: string;
  body?: string;
  text?: string;
  title?: string;
  options?: CbtOption[];
  weight?: number;
  is_flagged?: boolean;
  current_answer?: unknown;
  user_answer?: unknown;
  answered_at?: string | null;
  [k: string]: unknown;
}

export interface CbtSessionQuestions extends CbtSessionMeta {
  total_questions?: number;
  questions: CbtQuestion[];
}

export interface SaveAnswerPayload {
  question_id: string;
  answer: unknown;
  is_flagged?: boolean;
  answered_at?: string;
}

/** POST /exams/{id}/start-session  body: { token } */
export async function startExamSession(examId: string, token: string): Promise<CbtSessionStart> {
  const res = await apiClient.post<ApiResponse<CbtSessionStart>>(`/exams/${examId}/start-session`, { token });
  return res.data.data;
}

/** GET /exams/{id}/session-questions (soal teracak sesuai seed, tanpa kunci). */
export async function getSessionQuestions(examId: string): Promise<CbtSessionQuestions> {
  const res = await apiClient.get<ApiResponse<CbtSessionQuestions>>(`/exams/${examId}/session-questions`);
  return res.data.data;
}

/** POST /exams/{id}/save-answer (idempoten). */
export async function saveExamAnswer(examId: string, payload: SaveAnswerPayload): Promise<void> {
  await apiClient.post(`/exams/${examId}/save-answer`, payload);
}

/** POST /exams/{id}/log-violation (anti-cheat: tab switch / keluar fullscreen). */
export async function logExamViolation(examId: string, payload: Record<string, unknown>): Promise<void> {
  await apiClient.post(`/exams/${examId}/log-violation`, payload);
}

/** POST /exams/{id}/finish-session (kunci sesi + auto-grade PG). */
export async function finishExamSession(examId: string): Promise<unknown> {
  const res = await apiClient.post<ApiResponse<unknown>>(`/exams/${examId}/finish-session`, {});
  return res.data.data;
}

/** GET /exams/{id}/review (pembahasan, bila kebijakan mengizinkan). */
export async function getExamReview(examId: string): Promise<unknown> {
  const res = await apiClient.get<ApiResponse<unknown>>(`/exams/${examId}/review`);
  return res.data.data;
}
