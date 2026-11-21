import { describe, it, expect, vi, beforeEach } from 'vitest';
import { academicApi } from '@/features/academic/academic-api';
import { cbtApi } from '@/features/cbt/cbt-api';
import { auditLogger } from '@/lib/audit/audit-logger';
import { apiClient } from '@/lib/api/api-client';

describe('FASE 6: Performance & Concurrency Load Simulation Tests', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  describe('1. Peak Morning Attendance Rush (06:45 - 07:15 WIB)', () => {
    it('handles 50 concurrent attendance submissions without race conditions or data loss', async () => {
      // Mock apiClient.post for daily attendance with simulated network delay
      vi.spyOn(apiClient, 'post').mockImplementation(async (url, payload: any) => {
        // Simulate jitter between 5ms and 20ms
        await new Promise((resolve) => setTimeout(resolve, Math.floor(Math.random() * 15) + 5));

        return {
          data: {
            data: {
              id: `att-${payload.student_id}`,
              student_id: payload.student_id,
              status: payload.status,
              check_in_time: payload.check_in_time || '06:55:00',
              date: '2026-10-15',
            },
            message: 'Presensi berhasil dicatat',
          },
        } as any;
      });

      // Generate 50 concurrent student check-in payloads
      const studentRequests = Array.from({ length: 50 }, (_, i) => ({
        student_id: `std-${String(i + 1).padStart(3, '0')}`,
        status: (i % 10 === 0 ? 'terlambat' : 'hadir') as 'hadir' | 'terlambat',
        att_date: '2026-10-15',
        note: `Presensi jam 06:${String(45 + (i % 15)).padStart(2, '0')}`,
      }));

      const startTime = performance.now();

      // Dispatch 50 concurrent requests simultaneously using Promise.allSettled
      const settledResults = await Promise.allSettled(
        studentRequests.map((req) => academicApi.recordDailyAttendance(req))
      );

      const endTime = performance.now();
      const executionDurationMs = endTime - startTime;

      // Assert all 50 completed successfully
      expect(settledResults).toHaveLength(50);
      const fulfilledCount = settledResults.filter((r) => r.status === 'fulfilled').length;
      expect(fulfilledCount).toBe(50);

      // Verify that apiClient was called 50 times
      expect(apiClient.post).toHaveBeenCalledTimes(50);

      // Verify all student IDs were properly processed
      const fulfilledValues = settledResults
        .filter((r): r is PromiseFulfilledResult<any> => r.status === 'fulfilled')
        .map((r) => r.value.student_id);

      expect(new Set(fulfilledValues).size).toBe(50);
      expect(executionDurationMs).toBeLessThan(5000); // Must resolve well within test timeout
    });

    it('handles rate-limit (HTTP 429) gracefully during peak morning rush', async () => {
      let callCount = 0;
      vi.spyOn(apiClient, 'post').mockImplementation(async () => {
        callCount++;
        if (callCount > 40) {
          const err: any = new Error('Too Many Requests');
          err.response = { status: 429, data: { message: 'Batas laju permintaan tercapai (Rate limited)' } };
          throw err;
        }
        return { data: { data: { success: true } } } as any;
      });

      const burstRequests = Array.from({ length: 50 }, (_, i) => ({
        student_id: `std-rush-${i}`,
        status: 'hadir' as const,
        att_date: '2026-10-15',
      }));

      const results = await Promise.allSettled(
        burstRequests.map((req) => academicApi.recordDailyAttendance(req))
      );

      const fulfilled = results.filter((r) => r.status === 'fulfilled');
      const rejected = results.filter((r) => r.status === 'rejected');

      expect(fulfilled.length).toBe(40);
      expect(rejected.length).toBe(10);
    });
  });

  describe('2. Simultaneous CBT Exam Session Question Answering', () => {
    it('handles 50 simultaneous question answer saves with answer idempotency', async () => {
      const savedAnswers = new Map<string, any>();

      vi.spyOn(apiClient, 'post').mockImplementation(async (url, payload: any) => {
        // Simulate network latency
        await new Promise((resolve) => setTimeout(resolve, 8));
        savedAnswers.set(payload.question_id, payload.answer);

        return {
          data: {
            data: {
              session_id: 'cbt-sess-101',
              question_id: payload.question_id,
              saved_at: new Date().toISOString(),
              answer: payload.answer,
            },
          },
        } as any;
      });

      const questionAnswerSubmissions = Array.from({ length: 50 }, (_, i) => ({
        question_id: `q-${String(i + 1).padStart(3, '0')}`,
        answer: ['A', 'B', 'C', 'D'][i % 4],
        is_flagged: i % 7 === 0,
      }));

      const results = await Promise.allSettled(
        questionAnswerSubmissions.map((sub) =>
          cbtApi.saveStudentAnswer('cbt-sess-101', sub)
        )
      );

      expect(results).toHaveLength(50);
      const successfulSaves = results.filter((r) => r.status === 'fulfilled');
      expect(successfulSaves.length).toBe(50);
      expect(savedAnswers.size).toBe(50);
      expect(savedAnswers.get('q-001')).toBe('A');
      expect(savedAnswers.get('q-002')).toBe('B');
    });

    it('recovers and isolates errors when individual question submissions experience network drops', async () => {
      vi.spyOn(apiClient, 'post').mockImplementation(async (url, payload: any) => {
        // Drop questions divisible by 10 (simulate flaky student Wi-Fi connection)
        if (payload.question_id.endsWith('0')) {
          const networkErr: any = new Error('Network Timeout');
          networkErr.code = 'ECONNABORTED';
          throw networkErr;
        }

        return {
          data: {
            data: {
              question_id: payload.question_id,
              status: 'saved',
            },
          },
        } as any;
      });

      const questions = Array.from({ length: 30 }, (_, i) => ({
        question_id: `exam-q-${i + 1}`,
        answer: 'B',
      }));

      const results = await Promise.allSettled(
        questions.map((q) => cbtApi.saveStudentAnswer('exam-sess-02', q))
      );

      const successful = results.filter((r) => r.status === 'fulfilled');
      const failed = results.filter((r) => r.status === 'rejected');

      // exam-q-10, exam-q-20, exam-q-30 should fail gracefully
      expect(failed.length).toBe(3);
      expect(successful.length).toBe(27);
    });
  });

  describe('3. High-Throughput Audit Logging Concurrency', () => {
    it('processes 50 concurrent sensitive audit log events with non-blocking graceful degradation', async () => {
      let loggedCount = 0;
      vi.spyOn(apiClient, 'post').mockImplementation(async (url) => {
        if (url === '/audit-logs') {
          loggedCount++;
          await new Promise((resolve) => setTimeout(resolve, 5));
          return { data: { success: true } } as any;
        }
        return { data: {} } as any;
      });

      const auditCalls = Array.from({ length: 50 }, (_, i) =>
        auditLogger.logGradeUpdate({
          studentId: `std-${i + 1}`,
          studentName: `Siswa ${i + 1}`,
          subjectId: 'subj-math',
          subjectName: 'Matematika',
          component: 'UH-1',
          oldScore: 70,
          newScore: 85,
          reason: 'Koreksi penilaian tugas',
        })
      );

      const results = await Promise.all(auditCalls);

      expect(results).toHaveLength(50);
      expect(results.every((success) => success === true)).toBe(true);
      expect(loggedCount).toBe(50);
    });

    it('falls back safely (returns false, does not throw) when audit service is degraded', async () => {
      vi.spyOn(apiClient, 'post').mockRejectedValue(new Error('Audit DB Connection Busy'));

      const result = await auditLogger.logReportCardStatusChange({
        classId: 'cls-7a',
        className: '7-A',
        academicYearId: 'ay-2026',
        isLocked: true,
      });

      // Audit logger must return false without propagating uncaught exception to user flow
      expect(result).toBe(false);
    });
  });
});
