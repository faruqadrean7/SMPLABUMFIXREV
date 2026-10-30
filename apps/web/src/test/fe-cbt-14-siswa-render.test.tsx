import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import CBTExamWorkspacePage from '@/app/(portal)/portal/siswa/cbt/[id]/page';

vi.mock('@/lib/use-student-data', () => ({
  useStudentExams: () => ({
    data: [
      {
        id: 'exam-cbt-1',
        title: 'Ujian Sumatif Komputer',
        subjectName: 'Teknologi Informasi',
        totalQuestions: 2,
        durationMinutes: 60,
      },
    ],
  }),
  useStudentDashboard: () => ({
    data: { name: 'Ahmad Siswa', className: '9A' },
  }),
}));

vi.mock('@/lib/mock-student-data', () => ({
  CBT_ACTIVE_EXAM_QUESTIONS: [
    {
      id: 'q-01',
      number: 1,
      type: 'pg',
      category: 'HOTS',
      points: 5,
      prompt: '<p>Perhatikan tabel data relasional berikut:</p><table><tbody><tr><th>ID</th><th>Nama</th></tr><tr><td>1</td><td>Laboratorium</td></tr></tbody></table><img src="https://school.sch.id/files/download/signed-test.png" data-file-id="img-uuid-001" alt="Bagan Jaringan" />',
      options: [
        { key: 'A', label: '<em>Opsi Miring A</em>' },
        { key: 'B', label: 'Opsi Biasa B' },
      ],
    },
    {
      id: 'q-02',
      number: 2,
      type: 'pg',
      category: 'LOTS',
      points: 3,
      prompt: 'Teks soal nomor 2 polos tanpa tag HTML.',
      options: [
        { key: 'A', label: 'Jawaban A polos' },
        { key: 'B', label: 'Jawaban B polos' },
      ],
    },
  ],
}));

vi.mock('next/navigation', () => ({
  useRouter: () => ({
    push: vi.fn(),
  }),
}));

describe('FE-CBT-14: Student CBT Workspace Rich Content Rendering', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('fe-cbt-14 siswa renders question html table+img', async () => {
    render(<CBTExamWorkspacePage params={Promise.resolve({ id: 'exam-cbt-1' })} />);

    // Harus merender elemen tabel HTML nyata
    const tableEl = await screen.findByRole('table');
    expect(tableEl).toBeInTheDocument();
    expect(screen.getByText('Laboratorium')).toBeInTheDocument();

    // Harus merender gambar dengan src bertanda-tangan yang disuntikkan server
    const imgEl = screen.getByRole('img');
    expect(imgEl).toHaveAttribute('src', 'https://school.sch.id/files/download/signed-test.png');
    expect(imgEl).toHaveAttribute('alt', 'Bagan Jaringan');
  });

  it('fe-cbt-14 siswa renders options html', async () => {
    render(<CBTExamWorkspacePage params={Promise.resolve({ id: 'exam-cbt-1' })} />);

    // Opsi A harus memiliki tag <em> yang dirender sebagai HTML nyata, bukan teks mentah "<em>Opsi Miring A</em>"
    const emEl = await screen.findByText('Opsi Miring A');
    expect(emEl.tagName.toLowerCase()).toBe('em');
  });

  it('fe-cbt-14 siswa legacy plain text still renders', async () => {
    render(<CBTExamWorkspacePage params={Promise.resolve({ id: 'exam-cbt-1' })} />);

    // Pindah ke soal nomor 2
    const nextBtn = screen.getByRole('button', { name: /soal berikutnya/i });
    fireEvent.click(nextBtn);

    expect(await screen.findByText('Teks soal nomor 2 polos tanpa tag HTML.')).toBeInTheDocument();
    expect(screen.getByText('Jawaban A polos')).toBeInTheDocument();
  });
});
