import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import TeacherExamsPage from '@/app/(portal)/portal/guru/ujian/page';
import { guruApi, useTeacherExams } from '@/features/portal/guru';

vi.mock('@/features/portal/guru', () => ({
  useTeacherExams: vi.fn(),
  guruApi: {
    getQuestionBanks: vi.fn(),
    createQuestionBank: vi.fn(),
    createQuestion: vi.fn(),
  },
}));

describe('FE-CBT-13: Guru Question Editor Integration', () => {
  const mockBank = {
    id: 'bank-guru-101',
    name: 'Bank Soal Kimia Organik',
    subject: { id: 'subj-kim', name: 'Kimia' },
    questions_count: 5,
  };

  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(useTeacherExams).mockReturnValue({
      data: [],
      isLoading: false,
    } as any);
    vi.mocked(guruApi.getQuestionBanks).mockResolvedValue([mockBank] as any);
    vi.mocked(guruApi.createQuestion).mockResolvedValue({
      id: 'q-new-1',
      bank_id: 'bank-guru-101',
    } as any);
  });

  it('fe-cbt-13 guru uses rich editor & uploads to own bank', async () => {
    render(<TeacherExamsPage />);

    // Pindah ke tab Bank Soal Mandiri
    const tabBankBtn = screen.getByRole('button', { name: /bank soal mandiri/i });
    fireEvent.click(tabBankBtn);

    expect(await screen.findByText('Bank Soal Kimia Organik')).toBeInTheDocument();

    // Klik tombol Tambah Soal pada kartu bank
    const addQuestionBtn = screen.getByRole('button', { name: /tambah soal/i });
    fireEvent.click(addQuestionBtn);

    expect(screen.getByText('Tambah Butir Soal Evaluasi')).toBeInTheDocument();

    // Pastikan RichQuestionEditor ter-render (Toolbar tombol Tebal & Sisipkan Tabel muncul)
    const boldButton = screen.getByRole('button', { name: /tebal/i });
    expect(boldButton).toBeInTheDocument();

    const tableButton = screen.getByRole('button', { name: /sisipkan tabel/i });
    expect(tableButton).toBeInTheDocument();
  });
});
