import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import QuestionBanksPage from '@/app/(admin)/admin/cbt/questions/page';
import { cbtApi } from '@/features/cbt/cbt-api';
import { subjectsApi } from '@/features/master/subjects-api';

vi.mock('@/features/cbt/cbt-api', () => ({
  cbtApi: {
    getQuestionBanks: vi.fn(),
    getQuestions: vi.fn(),
    createQuestion: vi.fn(),
    updateQuestion: vi.fn(),
    deleteQuestion: vi.fn(),
    createQuestionBank: vi.fn(),
    uploadQuestionMedia: vi.fn(),
  },
}));

vi.mock('@/features/master/subjects-api', () => ({
  subjectsApi: {
    list: vi.fn(),
  },
}));

describe('FE-CBT-12: Admin Question Bank Rich Editor Integration', () => {
  const mockBank = {
    id: 'bank-1',
    subject_id: 'subj-1',
    title: 'Bank Soal Biologi Sel',
    code: 'BIO-X-01',
    grade_level: 10,
    subject: { id: 'subj-1', name: 'Biologi', code: 'BIO' },
  };

  const mockQuestion = {
    id: 'q-101',
    bank_id: 'bank-1',
    question_type: 'pg' as const,
    stem: '<p>Perhatikan tabel organel berikut:</p><table><tbody><tr><th>Organel</th><th>Fungsi</th></tr><tr><td>Mitokondria</td><td>Respirasi sel</td></tr></tbody></table>',
    body: {
      content: '<p>Perhatikan tabel organel berikut:</p><table><tbody><tr><th>Organel</th><th>Fungsi</th></tr><tr><td>Mitokondria</td><td>Respirasi sel</td></tr></tbody></table>',
    },
    weight: 2.0,
    options: [
      {
        id: 'opt-1',
        label: 'A',
        text: '<strong>Mitokondria</strong>',
        body: { content: '<strong>Mitokondria</strong>' },
        is_correct: true,
      },
      {
        id: 'opt-2',
        label: 'B',
        text: 'Ribosom',
        body: { content: 'Ribosom' },
        is_correct: false,
      },
    ],
  };

  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(cbtApi.getQuestionBanks).mockResolvedValue({
      data: [mockBank],
      meta: { page: 1, limit: 50, total: 1, totalPages: 1 },
    } as any);
    vi.mocked(subjectsApi.list).mockResolvedValue({
      data: [{ id: 'subj-1', name: 'Biologi', code: 'BIO' }],
    } as any);
    vi.mocked(cbtApi.getQuestions).mockResolvedValue({
      data: [mockQuestion],
      meta: { page: 1, limit: 100, total: 1, totalPages: 1 },
    } as any);
  });

  it('fe-cbt-12 admin questions page renders rich content in question preview', async () => {
    render(<QuestionBanksPage />);

    const bankTitles = await screen.findAllByText('Bank Soal Biologi Sel');
    expect(bankTitles.length).toBeGreaterThan(0);

    // Pastikan tabel di dalam preview soal ter-render sebagai elemen HTML tabel nyata via RichContent
    const tableEl = await screen.findByRole('table');
    expect(tableEl).toBeInTheDocument();
    expect(screen.getByText('Organel')).toBeInTheDocument();
    expect(screen.getByText('Respirasi sel')).toBeInTheDocument();
    expect(screen.getAllByText('Mitokondria')).toHaveLength(2);
  });

  it('fe-cbt-12 admin question form uses rich editor for stem and options', async () => {
    render(<QuestionBanksPage />);

    const bankTitles = await screen.findAllByText('Bank Soal Biologi Sel');
    expect(bankTitles.length).toBeGreaterThan(0);

    // Buka modal tambah soal
    const addBtn = screen.getByRole('button', { name: /tambah butir soal/i });
    fireEvent.click(addBtn);

    expect(screen.getByText('Tambah Butir Soal Baru')).toBeInTheDocument();

    // Pastikan Toolbar editor kaya muncul (tombol Bold, Sisipkan Tabel, dll.)
    const boldButtons = screen.getAllByRole('button', { name: /tebal/i });
    expect(boldButtons.length).toBeGreaterThan(0);

    const tableButtons = screen.getAllByRole('button', { name: /sisipkan tabel/i });
    expect(tableButtons.length).toBeGreaterThan(0);
  });
});
