import React from 'react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, act } from '@testing-library/react';
import StudentExamExecutionPage from '@/app/(portal)/portal/siswa/ujian/[id]/page';
import { cbtApi } from '@/features/cbt/cbt-api';

vi.mock('next/navigation', () => ({
  useParams: () => ({ id: 'exam-p013-test' }),
  useRouter: () => ({ push: vi.fn(), replace: vi.fn() }),
}));

vi.mock('@/providers/auth-provider', () => ({
  useAuth: () => ({
    user: { id: 'stu-user-1', username: 'siswa_cbt_1', roles: ['student'] },
    isLoading: false,
    isAuthenticated: true,
  }),
}));

vi.mock('@/providers/toast-provider', () => ({
  useToast: () => ({
    success: vi.fn(),
    error: vi.fn(),
    info: vi.fn(),
  }),
}));

vi.mock('@/features/cbt/cbt-api', () => ({
  cbtApi: {
    getStudentSessionQuestions: vi.fn(),
    saveStudentAnswer: vi.fn(),
    finishStudentSession: vi.fn(),
    logStudentViolation: vi.fn(),
    sendStudentHeartbeat: vi.fn(),
    startStudentSession: vi.fn(),
  },
}));

describe('P0-13: CBT Essay Autosave Debounce, Blur Flush & Anti-Cheat Dedup', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.useFakeTimers();
    localStorage.clear();
    sessionStorage.clear();

    (cbtApi.getStudentSessionQuestions as unknown as ReturnType<typeof vi.fn>).mockResolvedValue({
      data: {
        session_id: 'sess-p013-1',
        exam_title: 'Ujian Esai Debounce',
        server_time: new Date().toISOString(),
        ends_at: new Date(Date.now() + 60 * 60 * 1000).toISOString(),
        violation_count: 0,
        status: 'berlangsung',
        questions: [
          {
            question_id: 'q-essay-1',
            question_type: 'uraian',
            body: { content: 'Jelaskan hukum Newton pertama!' },
            options: [],
          },
        ],
        saved_answers: [],
      },
    });
    (cbtApi.saveStudentAnswer as unknown as ReturnType<typeof vi.fn>).mockResolvedValue({ data: { saved: true } });
    (cbtApi.finishStudentSession as unknown as ReturnType<typeof vi.fn>).mockResolvedValue({ data: { status: 'selesai' } });
    (cbtApi.logStudentViolation as unknown as ReturnType<typeof vi.fn>).mockResolvedValue({
      data: { violation_count: 1 },
    });
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it('coalesces rapid essay keystrokes into a single debounced POST after 800ms', async () => {
    render(<StudentExamExecutionPage />);

    await act(async () => {
      await Promise.resolve();
      await Promise.resolve();
    });

    const textarea = screen.getByPlaceholderText(/Tuliskan penjelasan dan argumen jawaban Anda/i);

    // Simulasikan pengetikan cepat 5 perubahan beruntun dalam <800ms
    const steps = ['H', 'Hu', 'Huk', 'Huku', 'Hukum inersia benda diam'];
    for (const val of steps) {
      fireEvent.change(textarea, { target: { value: val } });
      await act(async () => {
        vi.advanceTimersByTime(100);
      });
    }

    // Sebelum 800ms berlalu sejak ketikan terakhir, belum boleh ada panggilan saveStudentAnswer
    expect(cbtApi.saveStudentAnswer).toHaveBeenCalledTimes(0);

    // Setelah 800ms berlalu, hanya 1 POST terkirim dengan nilai final
    await act(async () => {
      vi.advanceTimersByTime(800);
      await Promise.resolve();
    });

    expect(cbtApi.saveStudentAnswer).toHaveBeenCalledTimes(1);
    expect(cbtApi.saveStudentAnswer).toHaveBeenCalledWith(
      'exam-p013-test',
      expect.objectContaining({
        question_id: 'q-essay-1',
        answer: { text: 'Hukum inersia benda diam' },
      })
    );
  });

  it('flushes pending debounced essay answer immediately on blur without double-posting', async () => {
    render(<StudentExamExecutionPage />);

    await act(async () => {
      await Promise.resolve();
      await Promise.resolve();
    });

    const textarea = screen.getByPlaceholderText(/Tuliskan penjelasan dan argumen jawaban Anda/i);
    fireEvent.change(textarea, { target: { value: 'Jawaban esai sebelum blur' } });

    expect(cbtApi.saveStudentAnswer).toHaveBeenCalledTimes(0);

    // Blur segera men-flush tanpa menunggu 800ms
    fireEvent.blur(textarea);
    await act(async () => {
      await Promise.resolve();
    });
    expect(cbtApi.saveStudentAnswer).toHaveBeenCalledTimes(1);

    // Melewati 1000ms setelah blur tidak boleh memicu POST ganda
    await act(async () => {
      vi.advanceTimersByTime(1000);
      await Promise.resolve();
    });
    expect(cbtApi.saveStudentAnswer).toHaveBeenCalledTimes(1);
  });
});
