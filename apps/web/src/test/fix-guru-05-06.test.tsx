import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import TeacherExamsPage from '@/app/(portal)/portal/guru/ujian/page';
import { guruApi } from '@/features/portal/guru';

// Mock guruApi
vi.mock('@/features/portal/guru', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/features/portal/guru')>();
  return {
    ...actual,
    useTeacherExams: () => ({
      data: [],
      isLoading: false,
    }),
    guruApi: {
      getTeacherExams: vi.fn(),
      getQuestionBanks: vi.fn(),
      createQuestionBank: vi.fn(),
      getQuestionBankDetail: vi.fn(),
      createQuestion: vi.fn(),
      createExam: vi.fn(),
    },
  };
});

// Mock useTeacherSubjects
vi.mock('@/lib/use-teacher-data', () => ({
  useTeacherSubjects: () => ({
    data: [
      { id: 'sbj-mtk', name: 'Matematika', code: 'MTK-7' },
    ],
  }),
}));

const mockBanks = [
  {
    id: 'bank-001',
    name: 'Bank Soal Aljabar Linear',
    subject_id: 'sbj-mtk',
    subject: { id: 'sbj-mtk', name: 'Matematika' },
    questions_count: 5,
    created_at: '2026-11-01T08:00:00Z',
  },
];

describe('FIX-GURU-05 & FIX-GURU-06: CBT Question Creation & Validation', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    (guruApi.getQuestionBanks as any).mockResolvedValue(mockBanks);
  });

  it('FIX-GURU-06: requires at least 2 options and 1 correct answer before saving PG question', async () => {
    (guruApi.createQuestion as any).mockResolvedValue({ id: 'q-new-1', status: 'draft' });

    render(<TeacherExamsPage />);

    // Switch to Bank Soal tab
    const bankTabBtn = screen.getByRole('button', { name: /Bank Soal Mandiri/i });
    fireEvent.click(bankTabBtn);

    expect(await screen.findByText('Bank Soal Aljabar Linear')).toBeInTheDocument();

    // Open question modal
    const addQuestionBtn = screen.getByRole('button', { name: /Tambah Soal/i });
    fireEvent.click(addQuestionBtn);

    expect(screen.getByText('Tambah Butir Soal Evaluasi')).toBeInTheDocument();

    // Enter question text
    const questionInput = screen.getByPlaceholderText('Tuliskan stimulus atau instruksi pertanyaan soal di sini...');
    fireEvent.change(questionInput, { target: { value: 'Berapakah 2x + 4 jika x = 3?' } });

    // Fill only option A, leave B, C, D empty
    const optA = screen.getByPlaceholderText('Isi opsi A');
    fireEvent.change(optA, { target: { value: '10' } });

    // Submit -> Rejected because < 2 options
    const submitBtn = screen.getByRole('button', { name: 'Simpan Butir Soal' });
    fireEvent.click(submitBtn);

    expect(await screen.findByText('Minimal 2 opsi jawaban harus diisi.')).toBeInTheDocument();
    expect(guruApi.createQuestion).not.toHaveBeenCalled();

    // Fill option B as well, but do not mark any as correct
    const optB = screen.getByPlaceholderText('Isi opsi B');
    fireEvent.change(optB, { target: { value: '12' } });

    fireEvent.click(submitBtn);

    expect(await screen.findByText('Pilih tepat satu jawaban benar untuk soal ini.')).toBeInTheDocument();
    expect(guruApi.createQuestion).not.toHaveBeenCalled();

    // Mark option A as correct
    const correctBtnA = screen.getByLabelText('Tandai opsi A sebagai jawaban benar');
    fireEvent.click(correctBtnA);

    fireEvent.click(submitBtn);

    await waitFor(() => {
      expect(guruApi.createQuestion).toHaveBeenCalledWith(
        'bank-001',
        expect.objectContaining({
          question_type: 'pg',
          answer_key: 'A',
          options: expect.arrayContaining([
            expect.objectContaining({ label: 'A', body: '10', is_correct: true }),
            expect.objectContaining({ label: 'B', body: '12', is_correct: false }),
          ]),
        }),
        expect.stringMatching(/^idemp-q-/)
      );
    });
  });

  it('FIX-GURU-06: requires answer key when question type is isian', async () => {
    (guruApi.createQuestion as any).mockResolvedValue({ id: 'q-new-2', status: 'draft' });

    render(<TeacherExamsPage />);

    // Switch to Bank Soal tab
    const bankTabBtn = screen.getByRole('button', { name: /Bank Soal Mandiri/i });
    fireEvent.click(bankTabBtn);

    expect(await screen.findByText('Bank Soal Aljabar Linear')).toBeInTheDocument();

    const addQuestionBtn = screen.getByRole('button', { name: /Tambah Soal/i });
    fireEvent.click(addQuestionBtn);

    // Enter question text
    const questionInput = screen.getByPlaceholderText('Tuliskan stimulus atau instruksi pertanyaan soal di sini...');
    fireEvent.change(questionInput, { target: { value: 'Ibu kota Indonesia saat ini adalah...' } });

    // Change type to isian
    const typeSelect = screen.getByRole('combobox', { name: 'Tipe Soal' });
    fireEvent.change(typeSelect, { target: { value: 'isian' } });

    expect(screen.getByText('Kunci Jawaban (Isian Singkat)')).toBeInTheDocument();

    const submitBtn = screen.getByRole('button', { name: 'Simpan Butir Soal' });
    fireEvent.click(submitBtn);

    expect(await screen.findByText('Kunci jawaban isian singkat wajib diisi.')).toBeInTheDocument();
    expect(guruApi.createQuestion).not.toHaveBeenCalled();

    // Fill short answer
    const shortAnswerInput = screen.getByPlaceholderText('Jawaban benar yang diharapkan');
    fireEvent.change(shortAnswerInput, { target: { value: 'Nusantara' } });

    fireEvent.click(submitBtn);

    await waitFor(() => {
      expect(guruApi.createQuestion).toHaveBeenCalledWith(
        'bank-001',
        expect.objectContaining({
          question_type: 'isian',
          answer_key: 'Nusantara',
          options: expect.arrayContaining([
            expect.objectContaining({ label: 'KUNCI', body: 'Nusantara', is_correct: true }),
          ]),
        }),
        expect.any(String)
      );
    });
  });

  it('FIX-GURU-05: disables submit button and prevents duplicate calls during pending creation', async () => {
    let resolveCreate: any;
    const pendingPromise = new Promise((resolve) => {
      resolveCreate = resolve;
    });
    (guruApi.createQuestion as any).mockReturnValue(pendingPromise);

    render(<TeacherExamsPage />);

    // Switch to Bank Soal tab
    const bankTabBtn = screen.getByRole('button', { name: /Bank Soal Mandiri/i });
    fireEvent.click(bankTabBtn);

    expect(await screen.findByText('Bank Soal Aljabar Linear')).toBeInTheDocument();

    const addQuestionBtn = screen.getByRole('button', { name: /Tambah Soal/i });
    fireEvent.click(addQuestionBtn);

    const questionInput = screen.getByPlaceholderText('Tuliskan stimulus atau instruksi pertanyaan soal di sini...');
    fireEvent.change(questionInput, { target: { value: 'Berapakah 1 + 1?' } });

    const optA = screen.getByPlaceholderText('Isi opsi A');
    fireEvent.change(optA, { target: { value: '2' } });

    const optB = screen.getByPlaceholderText('Isi opsi B');
    fireEvent.change(optB, { target: { value: '3' } });

    const correctBtnA = screen.getByLabelText('Tandai opsi A sebagai jawaban benar');
    fireEvent.click(correctBtnA);

    const submitBtn = screen.getByRole('button', { name: 'Simpan Butir Soal' });

    // First click
    fireEvent.click(submitBtn);

    // Verify button shows loading and is disabled
    expect(await screen.findByText('Menyimpan...')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Menyimpan...' })).toBeDisabled();

    // Subsequent clicks while pending
    fireEvent.click(screen.getByRole('button', { name: 'Menyimpan...' }));
    fireEvent.click(screen.getByRole('button', { name: 'Menyimpan...' }));

    // Verify createQuestion was called exactly once
    expect(guruApi.createQuestion).toHaveBeenCalledTimes(1);

    // Resolve promise
    resolveCreate({ id: 'q-resolved', status: 'draft' });
  });

  it('FIX-GURU-05: disables bank submit button while creating question bank', async () => {
    let resolveBank: any;
    const pendingPromise = new Promise((resolve) => {
      resolveBank = resolve;
    });
    (guruApi.createQuestionBank as any).mockReturnValue(pendingPromise);

    render(<TeacherExamsPage />);

    // Switch to Bank Soal tab
    const bankTabBtn = screen.getByRole('button', { name: /Bank Soal Mandiri/i });
    fireEvent.click(bankTabBtn);

    expect(await screen.findByText('Bank Soal Aljabar Linear')).toBeInTheDocument();

    const newBankBtn = screen.getByRole('button', { name: /Buat Bank Soal Baru/i });
    fireEvent.click(newBankBtn);

    expect(screen.getByRole('heading', { name: /Buat Bank Soal Baru/i })).toBeInTheDocument();

    const bankNameInput = screen.getByPlaceholderText('Contoh: Bank Soal Aljabar Linear Kelas 7');
    fireEvent.change(bankNameInput, { target: { value: 'Bank Soal Baru Kelas 8' } });

    const submitBtn = screen.getByRole('button', { name: 'Simpan Bank Soal' });
    fireEvent.click(submitBtn);

    expect(await screen.findByText('Menyimpan...')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Menyimpan...' })).toBeDisabled();

    // Repeated clicks should be blocked
    fireEvent.click(screen.getByRole('button', { name: 'Menyimpan...' }));
    expect(guruApi.createQuestionBank).toHaveBeenCalledTimes(1);

    resolveBank({ id: 'bank-new', name: 'Bank Soal Baru Kelas 8' });
  });
});
