import { describe, it, expect, vi, beforeEach } from 'vitest';
import { cbtApi } from '@/features/cbt/cbt-api';
import { apiClient } from '@/lib/api/api-client';

describe('ADM-CBT-01 to ADM-CBT-08 & SIS-CBT-01: Asesmen Ujian Komputer (CBT)', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  describe('1. Bank Soal & Butir Soal (ADM-CBT-01 / BE-CBT-01)', () => {
    it('fetches question banks successfully', async () => {
      const mockResponse = {
        data: {
          data: [
            {
              id: 'bank-1',
              title: 'Bank Soal Matematika Wajib X',
              code: 'MAT-X-01',
              subject_id: 'subj-1',
              total_questions: 25,
            },
          ],
          meta: { page: 1, limit: 10, total: 1, totalPages: 1 },
        },
      };

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce(mockResponse as any);

      const res = await cbtApi.getQuestionBanks({ search: 'Matematika' });
      expect(apiClient.get).toHaveBeenCalledWith('/question-banks', {
        params: { search: 'Matematika' },
      });
      expect(res.data).toHaveLength(1);
      expect(res.data[0].code).toBe('MAT-X-01');
    });

    it('creates a new question bank package', async () => {
      const payload = {
        subject_id: 'subj-1',
        title: 'Bank Soal Informatika ASTS',
        code: 'INF-X-ASTS',
        grade_level: 10,
      };

      vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: { data: { id: 'bank-2', ...payload } },
      } as any);

      const res = await cbtApi.createQuestionBank(payload);
      expect(apiClient.post).toHaveBeenCalledWith('/question-banks', payload);
      expect(res.data.id).toBe('bank-2');
    });

    it('creates a question item with objective PG options', async () => {
      const qPayload = {
        bank_id: 'bank-1',
        question_type: 'pg',
        stem: 'Berapakah hasil dari 2 + 2?',
        weight: 10,
        options: [
          { label: 'A', text: '3', is_correct: false },
          { label: 'B', text: '4', is_correct: true },
        ],
      };

      vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: { data: { id: 'q-1', ...qPayload } },
      } as any);

      const res = await cbtApi.createQuestion(qPayload);
      expect(apiClient.post).toHaveBeenCalledWith('/questions', qPayload);
      expect(res.data.id).toBe('q-1');
      expect(res.data.options).toHaveLength(2);
    });
  });

  describe('2. Manajemen Ujian, Ruang Lab, & Token (ADM-CBT-02, ADM-CBT-03 / BE-CBT-02, BE-CBT-03)', () => {
    it('fetches exams with category filter', async () => {
      const mockExams = {
        data: {
          data: [
            {
              id: 'exam-1',
              title: 'Ulangan Harian 1: Aljabar',
              exam_type: 'harian',
              status: 'active',
              duration_min: 60,
            },
          ],
        },
      };

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce(mockExams as any);

      const res = await cbtApi.getExams({ exam_type: 'harian' });
      expect(apiClient.get).toHaveBeenCalledWith('/exams', {
        params: { exam_type: 'harian' },
      });
      expect(res.data[0].exam_type).toBe('harian');
    });

    it('regenerates room token for proctor', async () => {
      vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: {
          data: {
            id: 'room-1',
            exam_id: 'exam-1',
            token: 'LAB2026',
            token_expired_at: '2026-10-14T10:00:00Z',
          },
        },
      } as any);

      const res = await cbtApi.regenerateExamRoomToken('room-1');
      expect(apiClient.post).toHaveBeenCalledWith('/exam-rooms/room-1/regenerate-token');
      expect(res.data.token).toBe('LAB2026');
    });

    it('enrolls class participants into exam', async () => {
      vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: { data: { enrolled_count: 32 } },
      } as any);

      const res = await cbtApi.enrollExamParticipants('exam-1', {
        class_ids: ['class-1'],
      });
      expect(apiClient.post).toHaveBeenCalledWith('/exams/exam-1/participants', {
        class_ids: ['class-1'],
      });
      expect(res.data.enrolled_count).toBe(32);
    });
  });

  describe('3. Proctor Live Monitor & Workstation Management (ADM-CBT-04 / BE-CBT-06, BE-CBT-12)', () => {
    it('fetches live monitor data for exam lab workstations', async () => {
      const mockMonitor = {
        data: {
          data: {
            exam_id: 'exam-1',
            total_participants: 32,
            active_count: 30,
            submitted_count: 2,
            blocked_count: 0,
            sessions: [
              {
                session_id: 'sess-1',
                student_id: 'stu-1',
                student_name: 'Ahmad Faiz',
                nis: '1001',
                status: 'in_progress',
                progress_percent: 65,
                answered_count: 13,
                total_questions: 20,
                remaining_seconds: 1800,
                blur_count: 0,
                is_online: true,
              },
            ],
          },
        },
      };

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce(mockMonitor as any);

      const res = await cbtApi.getExamMonitor('exam-1');
      expect(apiClient.get).toHaveBeenCalledWith('/exams/exam-1/monitor', { params: undefined });
      expect(res.data.active_count).toBe(30);
      expect(res.data.sessions[0].student_name).toBe('Ahmad Faiz');
    });

    it('proctor unlocks or manages student session', async () => {
      vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: { data: { message: 'Session unlocked successfully' } },
      } as any);

      const res = await cbtApi.manageSession('sess-1', 'unlock', undefined, 'Izin pengawas');
      expect(apiClient.post).toHaveBeenCalledWith('/exam-sessions/sess-1/manage', {
        action: 'unlock',
        extra_minutes: undefined,
        reason: 'Izin pengawas',
      });
      expect(res.data.message).toBe('Session unlocked successfully');
    });
  });

  describe('4. Penilaian & Integrasi Buku Nilai (ADM-CBT-05, ADM-CBT-07 / BE-CBT-05, ADM-GRD-01)', () => {
    it('triggers auto-grading for objective questions', async () => {
      vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: { data: { sessions_graded: 30, answers_evaluated: 600 } },
      } as any);

      const res = await cbtApi.runAutoGrade('exam-1');
      expect(apiClient.post).toHaveBeenCalledWith('/exams/exam-1/auto-grade', {
        session_id: undefined,
      });
      expect(res.data.sessions_graded).toBe(30);
    });

    it('fetches student exam results with KKTP status', async () => {
      const mockResults = {
        data: {
          data: [
            {
              session_id: 'sess-1',
              student_id: 'stu-1',
              student_name: 'Ahmad Faiz',
              nis: '1001',
              class_name: 'X-MIPA-1',
              status: 'submitted',
              total_score: 88.5,
              passing_grade: 75,
              is_passed: true,
            },
          ],
        },
      };

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce(mockResults as any);

      const res = await cbtApi.getExamResults('exam-1');
      expect(apiClient.get).toHaveBeenCalledWith('/exams/exam-1/results', { params: undefined });
      expect(res.data[0].is_passed).toBe(true);
      expect(res.data[0].total_score).toBe(88.5);
    });

    it('publishes exam scores to Gradebook (ADM-GRD-01)', async () => {
      const publishPayload = {
        component_id: 'comp-1',
        period_id: 'period-1',
        overwrite_locked: false,
      };

      vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: {
          data: {
            grades_synced: 30,
            skipped_locked: 0,
            message: 'Nilai ujian CBT berhasil dipublikasikan ke Buku Nilai.',
          },
        },
      } as any);

      const res = await cbtApi.publishToGradebook('exam-1', publishPayload);
      expect(apiClient.post).toHaveBeenCalledWith('/exams/exam-1/publish-gradebook', publishPayload);
      expect(res.data.grades_synced).toBe(30);
    });

    it('creates remedial exam for students below KKTP', async () => {
      const remedialPayload = {
        start_at: '2026-10-15T08:00:00Z',
        end_at: '2026-10-15T09:30:00Z',
        duration_min: 60,
      };

      vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: {
          data: {
            id: 'exam-remedial-1',
            title: 'Remedial: Ulangan Harian 1',
            exam_type: 'remedial',
          },
        },
      } as any);

      const res = await cbtApi.createRemedialExam('exam-1', remedialPayload);
      expect(apiClient.post).toHaveBeenCalledWith('/exams/exam-1/remedial', remedialPayload);
      expect(res.data.id).toBe('exam-remedial-1');
    });
  });

  describe('5. Student Workspace Session & Anti-Cheat (SIS-CBT-01 / BE-CBT-04)', () => {
    it('starts student exam session with token', async () => {
      vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: {
          data: {
            session_id: 'sess-1',
            exam: { id: 'exam-1', title: 'UH Matematika' },
            started_at: '2026-10-14T08:00:00Z',
            deadline_at: '2026-10-14T09:30:00Z',
            remaining_seconds: 5400,
          },
        },
      } as any);

      const res = await cbtApi.startStudentSession('exam-1', { token: 'LAB2026' });
      expect(apiClient.post).toHaveBeenCalledWith('/exams/exam-1/start-session', {
        token: 'LAB2026',
      });
      expect(res.data.session_id).toBe('sess-1');
    });

    it('autosaves student answer per question', async () => {
      const answerPayload = {
        question_id: 'q-1',
        answer: 'B',
        is_flagged: false,
      };

      vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: { data: { success: true } },
      } as any);

      const res = await cbtApi.saveStudentAnswer('exam-1', answerPayload);
      expect(apiClient.post).toHaveBeenCalledWith('/exams/exam-1/save-answer', answerPayload);
      expect(res.data.success).toBe(true);
    });

    it('logs anti-cheat violation when student switches tab', async () => {
      const violationPayload = {
        violation_type: 'tab_switch',
        details: { timestamp: '2026-10-14T08:15:00Z' },
      };

      vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: { data: { violation_count: 1 } },
      } as any);

      const res = await cbtApi.logStudentViolation('exam-1', violationPayload);
      expect(apiClient.post).toHaveBeenCalledWith('/exams/exam-1/log-violation', violationPayload);
      expect(res.data.violation_count).toBe(1);
    });

    it('submits and finishes student exam session', async () => {
      vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: { data: { message: 'Ujian berhasil dikumpulkan', status: 'submitted' } },
      } as any);

      const res = await cbtApi.finishStudentSession('exam-1');
      expect(apiClient.post).toHaveBeenCalledWith('/exams/exam-1/finish-session');
      expect(res.data.status).toBe('submitted');
    });
  });
});
