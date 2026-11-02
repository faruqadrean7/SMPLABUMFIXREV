import { describe, it, expect, vi, beforeEach } from 'vitest';
import { cbtApi } from '@/features/cbt/cbt-api';
import { apiClient } from '@/lib/api/api-client';
import fs from 'fs';
import path from 'path';

describe('FE-CBT-UJIAN-01: Integrasi Pengerjaan Ujian Siswa ke Sesi Nyata CBT', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  // =========================================================================
  // AC1: Token verification & Session Start Lifecycle
  // =========================================================================
  describe('Lifecycle Sesi & Verifikasi Token (AC1)', () => {
    it('FE-CBT-UJIAN-01: Siswa memasukkan token valid memulai sesi ujian (startStudentSession)', async () => {
      const mockSessionResponse = {
        data: {
          data: {
            session_id: 'sess-real-001',
            exam_id: 'exam-real-123',
            exam_title: 'Penilaian Akhir Semester: Informatika Kelas 7',
            student_id: 'std-2026-001',
            student_name: 'Ahmad Siswa',
            status: 'mengerjakan',
            started_at: '2026-10-31T08:00:00Z',
            deadline_at: '2026-10-31T09:30:00Z',
            remaining_seconds: 5400,
            violation_count: 0,
            server_time: '2026-10-31T08:00:00Z',
          },
        },
      };

      vi.spyOn(apiClient, 'post').mockResolvedValueOnce(mockSessionResponse as any);

      const res = await cbtApi.startStudentSession('exam-real-123', { token: 'LABCBT' });

      expect(apiClient.post).toHaveBeenCalledWith('/exams/exam-real-123/start-session', {
        token: 'LABCBT',
      });
      expect(res.data.session_id).toBe('sess-real-001');
      expect(res.data.status).toBe('mengerjakan');
    });

    it('FE-CBT-UJIAN-01: Validasi gagal ketika token salah atau tidak cocok (422)', async () => {
      const errorResponse = {
        code: 'BUSINESS_RULE_VIOLATION',
        message: 'Token ruang ujian tidak valid atau tidak cocok.',
        status: 422,
      };

      vi.spyOn(apiClient, 'post').mockRejectedValueOnce(errorResponse);

      await expect(
        cbtApi.startStudentSession('exam-real-123', { token: 'WRONGTOKEN' })
      ).rejects.toEqual(errorResponse);
    });

    it('FE-CBT-UJIAN-01: Akses tidak terotentikasi ditolak (401)', async () => {
      const errorResponse = {
        code: 'UNAUTHENTICATED',
        message: 'Unauthenticated.',
        status: 401,
      };

      vi.spyOn(apiClient, 'get').mockRejectedValueOnce(errorResponse);

      await expect(
        cbtApi.getStudentSessionQuestions('exam-real-123')
      ).rejects.toEqual(errorResponse);
    });

    it('FE-CBT-UJIAN-01: Sesi terkunci oleh pengawas ditolak (403)', async () => {
      const errorResponse = {
        code: 'FORBIDDEN',
        message: 'Sesi ujian Anda sedang terkunci: Pelanggaran batas keluar layar. Hubungi pengawas ruang.',
        status: 403,
      };

      vi.spyOn(apiClient, 'post').mockRejectedValueOnce(errorResponse);

      await expect(
        cbtApi.startStudentSession('exam-real-123', { token: 'LABCBT' })
      ).rejects.toEqual(errorResponse);
    });
  });

  // =========================================================================
  // AC2 & AC6: Real Exam Questions & Security Screening (§6.6)
  // =========================================================================
  describe('Pemuatan Soal Nyata & Penyaringan Kunci Jawaban (AC2, AC6)', () => {
    const realExamQuestionsPayload = {
      data: {
        data: {
          session_id: 'sess-real-001',
          exam_id: 'exam-real-123',
          title: 'Asesmen Sumatif Informatika',
          status: 'mengerjakan',
          remaining_seconds: 5350,
          deadline_at: '2026-10-31T09:30:00Z',
          server_time: '2026-10-31T08:00:50Z',
          total_questions: 6,
          questions: [
            {
              number: 1,
              id: 'q-real-pg-1',
              question_id: 'q-real-pg-1',
              question_type: 'pg',
              stem: 'Manakah komponen hardware yang bertindak sebagai otak komputer?',
              weight: 2.0,
              options: [
                { id: 'opt-1', label: 'A', text: 'RAM' },
                { id: 'opt-2', label: 'B', text: 'CPU' },
                { id: 'opt-3', label: 'C', text: 'Harddisk' },
                { id: 'opt-4', label: 'D', text: 'Power Supply' },
              ],
            },
            {
              number: 2,
              id: 'q-real-pgk-2',
              question_id: 'q-real-pgk-2',
              question_type: 'pg_kompleks',
              stem: 'Pilihlah protokol jaringan yang berjalan di layer aplikasi!',
              weight: 3.0,
              options: [
                { id: 'opt-5', label: 'A', text: 'HTTP' },
                { id: 'opt-6', label: 'B', text: 'DNS' },
                { id: 'opt-7', label: 'C', text: 'TCP' },
              ],
            },
            {
              number: 3,
              id: 'q-real-bs-3',
              question_id: 'q-real-bs-3',
              question_type: 'bs',
              stem: 'IP Address versi 4 menggunakan pengalamatan 128-bit.',
              weight: 1.5,
              options: [
                { id: 'opt-8', label: 'Benar', text: 'Benar' },
                { id: 'opt-9', label: 'Salah', text: 'Salah' },
              ],
            },
            {
              number: 4,
              id: 'q-real-jodoh-4',
              question_id: 'q-real-jodoh-4',
              question_type: 'menjodohkan',
              stem: 'Jodohkan istilah protokol jaringan berikut dengan fungsinya!',
              weight: 4.0,
              body: {
                targets: ['Transfer Berkas', 'Keamanan Transmisi Web', 'Resolusi Domain'],
              },
              options: [
                { id: 'opt-10', label: '1', text: 'FTP' },
                { id: 'opt-11', label: '2', text: 'HTTPS' },
                { id: 'opt-12', label: '3', text: 'DNS' },
              ],
            },
            {
              number: 5,
              id: 'q-real-isian-5',
              question_id: 'q-real-isian-5',
              question_type: 'isian',
              stem: 'Topologi jaringan komputer yang menyerupai bentuk cincin tertutup dinamakan topologi...',
              weight: 2.0,
              options: [],
            },
            {
              number: 6,
              id: 'q-real-esai-6',
              question_id: 'q-real-esai-6',
              question_type: 'uraian',
              stem: 'Jelaskan perbedaan mendasar antara enkripsi simetris dan asimetris!',
              weight: 5.0,
              options: [],
            },
          ],
        },
      },
    };

    it('FE-CBT-UJIAN-01: getStudentSessionQuestions memuat butir soal nyata dari backend (AC2)', async () => {
      vi.spyOn(apiClient, 'get').mockResolvedValueOnce(realExamQuestionsPayload as any);

      const res = await cbtApi.getStudentSessionQuestions('exam-real-123');

      expect(apiClient.get).toHaveBeenCalledWith('/exams/exam-real-123/session-questions');
      const data: any = res.data;
      expect(data.session_id).toBe('sess-real-001');
      expect(data.questions).toHaveLength(6);
      expect(data.questions[0].stem).toContain('otak komputer');
    });

    it('FE-CBT-UJIAN-01: Keamanan (§6.6): Kunci jawaban TIDAK PERNAH dikirim ke klien (AC6)', async () => {
      vi.spyOn(apiClient, 'get').mockResolvedValueOnce(realExamQuestionsPayload as any);

      const res = await cbtApi.getStudentSessionQuestions('exam-real-123');
      const data: any = res.data;

      // Verifikasi bahwa field sensitif kunci jawaban tidak ada di options maupun item soal
      data.questions.forEach((q: any) => {
        expect(q).not.toHaveProperty('key');
        expect(q).not.toHaveProperty('answer_key');
        expect(q).not.toHaveProperty('correct_answer');
        expect(q).not.toHaveProperty('explanation');
        expect(q).not.toHaveProperty('pembahasan');

        if (q.options && q.options.length > 0) {
          q.options.forEach((opt: any) => {
            expect(opt).not.toHaveProperty('is_correct');
            expect(opt).not.toHaveProperty('score');
            expect(opt).not.toHaveProperty('points');
          });
        }
      });
    });

    it('FE-CBT-UJIAN-01: Mendukung semua 6 tipe butir soal (AC3)', async () => {
      vi.spyOn(apiClient, 'get').mockResolvedValueOnce(realExamQuestionsPayload as any);

      const res = await cbtApi.getStudentSessionQuestions('exam-real-123');
      const data: any = res.data;
      const questionTypes = data.questions.map((q: any) => q.question_type);

      expect(questionTypes).toContain('pg');
      expect(questionTypes).toContain('pg_kompleks');
      expect(questionTypes).toContain('bs');
      expect(questionTypes).toContain('menjodohkan');
      expect(questionTypes).toContain('isian');
      expect(questionTypes).toContain('uraian');
    });
  });

  // =========================================================================
  // AC4: Autosave answers & Session Submission
  // =========================================================================
  describe('Penyimpanan Jawaban & Pengumpulan Sesi (AC4)', () => {
    it('FE-CBT-UJIAN-01: Autosave pilihan ganda (pg) memanggil saveStudentAnswer', async () => {
      vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: { data: { saved: true, question_id: 'q-real-pg-1' } },
      } as any);

      const payload = {
        question_id: 'q-real-pg-1',
        answer: 'opt-2',
        is_flagged: false,
      };

      const res = await cbtApi.saveStudentAnswer('exam-real-123', payload);
      expect(apiClient.post).toHaveBeenCalledWith('/exams/exam-real-123/save-answer', payload);
      expect(res.data.saved).toBe(true);
    });

    it('FE-CBT-UJIAN-01: Autosave pilihan ganda kompleks (pg_kompleks) dengan array ID', async () => {
      vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: { data: { saved: true, question_id: 'q-real-pgk-2' } },
      } as any);

      const payload = {
        question_id: 'q-real-pgk-2',
        answer: { selected_option_ids: ['opt-5', 'opt-6'] },
        is_flagged: false,
      };

      const res = await cbtApi.saveStudentAnswer('exam-real-123', payload);
      expect(apiClient.post).toHaveBeenCalledWith('/exams/exam-real-123/save-answer', payload);
      expect(res.data.saved).toBe(true);
    });

    it('FE-CBT-UJIAN-01: Autosave menjodohkan dengan pasangan pasangan kunci-nilai', async () => {
      vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: { data: { saved: true, question_id: 'q-real-jodoh-4' } },
      } as any);

      const payload = {
        question_id: 'q-real-jodoh-4',
        answer: {
          pairs: {
            'opt-10': 'Transfer Berkas',
            'opt-11': 'Keamanan Transmisi Web',
            'opt-12': 'Resolusi Domain',
          },
        },
        is_flagged: true,
      };

      const res = await cbtApi.saveStudentAnswer('exam-real-123', payload);
      expect(apiClient.post).toHaveBeenCalledWith('/exams/exam-real-123/save-answer', payload);
      expect(res.data.saved).toBe(true);
    });

    it('FE-CBT-UJIAN-01: Aturan batas: Autosave ditolak bila waktu ujian telah habis (EXAM_TIME_OVER)', async () => {
      const errorTimeOver = {
        code: 'EXAM_TIME_OVER',
        message: 'Waktu pengerjaan ujian telah berakhir. Jawaban tidak dapat disimpan lagi.',
        status: 422,
      };

      vi.spyOn(apiClient, 'post').mockRejectedValueOnce(errorTimeOver);

      await expect(
        cbtApi.saveStudentAnswer('exam-real-123', {
          question_id: 'q-real-pg-1',
          answer: 'opt-2',
        })
      ).rejects.toEqual(errorTimeOver);
    });

    it('FE-CBT-UJIAN-01: Penyelesaian ujian memanggil finishStudentSession', async () => {
      vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: {
          data: {
            message: 'Sesi ujian berhasil diselesaikan.',
            session_status: 'selesai',
            total_answers: 6,
          },
        },
      } as any);

      const res = await cbtApi.finishStudentSession('exam-real-123');
      expect(apiClient.post).toHaveBeenCalledWith('/exams/exam-real-123/finish-session');
      expect(res.data.session_status).toBe('selesai');
    });
  });

  // =========================================================================
  // AC5: Anti-Cheat & Heartbeat
  // =========================================================================
  describe('Anti-Cheat & Heartbeat Sesi (AC5)', () => {
    it('FE-CBT-UJIAN-01: Mencatat pelanggaran perpindahan tab layar (tab_switch)', async () => {
      vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: {
          data: {
            violation_count: 2,
            is_locked: false,
            message: 'Pelanggaran anti-cheat berhasil dicatat.',
          },
        },
      } as any);

      const violationPayload = {
        violation_type: 'tab_switch',
        details: { timestamp: '2026-10-31T08:20:00Z', idempotency_key: 'viol-key-1' },
      };

      const res = await cbtApi.logStudentViolation('exam-real-123', violationPayload);
      expect(apiClient.post).toHaveBeenCalledWith('/exams/exam-real-123/log-violation', violationPayload);
      expect(res.data.violation_count).toBe(2);
    });

    it('FE-CBT-UJIAN-01: Mengirim heartbeat sesi aktif peserta ujian', async () => {
      vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: { data: { acknowledged: true } },
      } as any);

      const res = await cbtApi.sendStudentHeartbeat('sess-real-001', {
        client_timestamp: '2026-10-31T08:20:00Z',
      });
      expect(apiClient.post).toHaveBeenCalledWith('/exam-sessions/sess-real-001/heartbeat', {
        client_timestamp: '2026-10-31T08:20:00Z',
      });
      expect(res.data.acknowledged).toBe(true);
    });
  });

  // =========================================================================
  // AC7: Retirement of Mock Page
  // =========================================================================
  describe('Pensiunkan Halaman Mock & Redirect Kanonis (AC7)', () => {
    it('FE-CBT-UJIAN-01: Halaman legacy cbt/[id]/page.tsx tidak lagi mengimpor data mock CBT_ACTIVE_EXAM_QUESTIONS', () => {
      const legacyPagePath = path.resolve(
        __dirname,
        '../app/(portal)/portal/siswa/cbt/[id]/page.tsx'
      );
      const content = fs.readFileSync(legacyPagePath, 'utf-8');

      expect(content).not.toContain('CBT_ACTIVE_EXAM_QUESTIONS');
      expect(content).not.toContain('CBTQuestionDetail');
      expect(content).toContain('router.replace(`/portal/siswa/ujian/${examId}`)');
    });
  });
});
