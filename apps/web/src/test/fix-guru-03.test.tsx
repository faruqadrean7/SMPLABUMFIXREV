import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import SpeedGraderPage from '@/app/(portal)/portal/guru/tugas/[id]/penilaian/page';
import { lmsApi } from '@/lib/api-client';
import { toast } from 'sonner';

// Mock Sonner Toast
vi.mock('sonner', () => ({
  toast: {
    success: vi.fn(),
    error: vi.fn(),
    info: vi.fn(),
  },
}));

// Mock next/navigation
vi.mock('next/navigation', () => ({
  useParams: () => ({ id: 'asg-001' }),
  useRouter: () => ({
    push: vi.fn(),
    replace: vi.fn(),
  }),
  useSearchParams: () => ({
    get: vi.fn().mockReturnValue(null),
  }),
  usePathname: () => '/guru/tugas/asg-001/penilaian',
}));

// Mock lmsApi
vi.mock('@/lib/api-client', () => ({
  lmsApi: {
    getAssignmentDetail: vi.fn(),
    getSubmissions: vi.fn(),
    gradeSubmission: vi.fn(),
  },
}));

function createTestQueryClient() {
  return new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
      },
    },
  });
}

function renderWithClient(ui: React.ReactElement) {
  const queryClient = createTestQueryClient();
  return render(<QueryClientProvider client={queryClient}>{ui}</QueryClientProvider>);
}

const mockAssignment = {
  id: 'asg-001',
  class_id: 'cls-7a',
  subject_id: 'sbj-mtk',
  teacher_id: 'tch-01',
  title: 'Praktikum Bangun Ruang',
  max_score: 80, // Dynamic max_score: 80 (not 100)
  grade_component_id: 'gc-01',
  grade_component_name: 'Formatif 1',
  is_locked: false,
  allow_late: true,
  due_at: '2026-11-20T23:59:00Z',
  created_at: '2026-11-01T08:00:00Z',
  updated_at: '2026-11-01T08:00:00Z',
};

const mockSubmissions = [
  {
    id: 'sub-001',
    assignment_id: 'asg-001',
    student_id: 'std-001',
    notes: 'Tugas sudah selesai mohon dicek',
    submitted_at: '2026-11-02T10:00:00Z',
    is_late: false,
    score: null,
    feedback: '',
    student: {
      id: 'std-001',
      name: 'Aditya Pratama',
      nis: '2026001',
      class_id: 'cls-7a',
    },
    file: {
      id: 'file-01',
      filename: 'Praktikum_Bangun_Ruang_Aditya.pdf',
      size_bytes: 1048576,
      url: 'https://example.com/file.pdf',
      mime_type: 'application/pdf',
      created_at: '2026-11-02T10:00:00Z',
      updated_at: '2026-11-02T10:00:00Z',
    },
  },
  {
    id: 'sub-002',
    assignment_id: 'asg-001',
    student_id: 'std-002',
    notes: '',
    submitted_at: '2026-11-03T11:00:00Z',
    is_late: true,
    score: 75,
    feedback: 'Perlu rapikan keterangan rumus.',
    student: {
      id: 'std-002',
      name: 'Budi Santoso',
      nis: '2026002',
      class_id: 'cls-7a',
    },
  },
];

describe('FIX-GURU-03: Penilaian Tugas & Validasi max_score Dinamis', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    (lmsApi.getAssignmentDetail as any).mockResolvedValue(mockAssignment);
    (lmsApi.getSubmissions as any).mockResolvedValue(mockSubmissions);
  });

  it('renders dynamic max_score in form labels and input placeholders', async () => {
    renderWithClient(<SpeedGraderPage />);

    expect(await screen.findByText('Praktikum Bangun Ruang')).toBeInTheDocument();
    expect(screen.getByText('Nilai Siswa (Skor 0 - 80)')).toBeInTheDocument();
    expect(screen.getByText('/ 80')).toBeInTheDocument();
  });

  it('AC1: saves valid grade within dynamic max_score and shows success toast', async () => {
    (lmsApi.gradeSubmission as any).mockResolvedValue({ success: true });

    renderWithClient(<SpeedGraderPage />);

    expect(await screen.findByText('Praktikum Bangun Ruang')).toBeInTheDocument();

    const scoreInput = screen.getByPlaceholderText('Contoh: 80');
    fireEvent.change(scoreInput, { target: { value: '78' } });

    const feedbackInput = screen.getByPlaceholderText('Tuliskan catatan evaluasi, poin apresiasi, atau saran perbaikan untuk siswa...');
    fireEvent.change(feedbackInput, { target: { value: 'Langkah kerja sangat sistematis dan rapi.' } });

    const submitBtn = screen.getByRole('button', { name: /Simpan Nilai & Siswa Berikutnya/i });
    expect(submitBtn).not.toBeDisabled();
    fireEvent.click(submitBtn);

    await waitFor(() => {
      expect(lmsApi.gradeSubmission).toHaveBeenCalledWith('asg-001', 'sub-001', {
        score: 78,
        feedback: 'Langkah kerja sangat sistematis dan rapi.',
      });
    });

    expect(toast.success).toHaveBeenCalledWith(expect.stringContaining('Aditya Pratama berhasil disimpan!'));
  });

  it('AC2: rejects score exceeding dynamic max_score with specific error toast', async () => {
    renderWithClient(<SpeedGraderPage />);

    expect(await screen.findByText('Praktikum Bangun Ruang')).toBeInTheDocument();

    const scoreInput = screen.getByPlaceholderText('Contoh: 80');
    // Input 85 when max_score is 80
    fireEvent.change(scoreInput, { target: { value: '85' } });

    const submitBtn = screen.getByRole('button', { name: /Simpan Nilai & Siswa Berikutnya/i });
    fireEvent.click(submitBtn);

    expect(toast.error).toHaveBeenCalledWith('Skor nilai harus berupa angka antara 0 hingga 80.');
    expect(lmsApi.gradeSubmission).not.toHaveBeenCalled();
  });

  it('AC2: rejects negative score with specific error toast', async () => {
    renderWithClient(<SpeedGraderPage />);

    expect(await screen.findByText('Praktikum Bangun Ruang')).toBeInTheDocument();

    const scoreInput = screen.getByPlaceholderText('Contoh: 80');
    fireEvent.change(scoreInput, { target: { value: '-5' } });

    const submitBtn = screen.getByRole('button', { name: /Simpan Nilai & Siswa Berikutnya/i });
    fireEvent.click(submitBtn);

    expect(toast.error).toHaveBeenCalledWith('Skor nilai harus berupa angka antara 0 hingga 80.');
    expect(lmsApi.gradeSubmission).not.toHaveBeenCalled();
  });

  it('AC2 & AC3: surfaces informative server error toast when backend rejects (e.g. 403 IDOR / 422)', async () => {
    const errorResponse = {
      response: {
        status: 403,
        data: {
          error: {
            code: 'FORBIDDEN',
            message: 'Akses ditolak: Hanya guru pengampu yang dapat menilai tugas ini.',
          },
        },
      },
    };
    (lmsApi.gradeSubmission as any).mockRejectedValue(errorResponse);

    renderWithClient(<SpeedGraderPage />);

    expect(await screen.findByText('Praktikum Bangun Ruang')).toBeInTheDocument();

    const scoreInput = screen.getByPlaceholderText('Contoh: 80');
    fireEvent.change(scoreInput, { target: { value: '75' } });

    const submitBtn = screen.getByRole('button', { name: /Simpan Nilai & Siswa Berikutnya/i });
    fireEvent.click(submitBtn);

    await waitFor(() => {
      expect(toast.error).toHaveBeenCalledWith(
        'Gagal menyimpan nilai: Akses ditolak: Hanya guru pengampu yang dapat menilai tugas ini.'
      );
    });
  });

  it('navigates student carousel and displays their existing score', async () => {
    renderWithClient(<SpeedGraderPage />);

    expect(await screen.findByText('Praktikum Bangun Ruang')).toBeInTheDocument();
    expect(screen.getAllByText('Aditya Pratama').length).toBeGreaterThan(0);

    // Click next student button
    const nextBtn = screen.getByRole('button', { name: 'Siswa Berikutnya' });
    fireEvent.click(nextBtn);

    expect(await screen.findAllByText('Budi Santoso')).toBeDefined();
    expect(screen.getByDisplayValue('75')).toBeInTheDocument();
    expect(screen.getByDisplayValue('Perlu rapikan keterangan rumus.')).toBeInTheDocument();
  });

  it('allows clicking preset buttons within dynamic max_score', async () => {
    renderWithClient(<SpeedGraderPage />);

    expect(await screen.findByText('Praktikum Bangun Ruang')).toBeInTheDocument();

    // With max_score = 80, preset 75 and 80 should be available, but 85/90/95/100 should not
    const preset75 = screen.getByRole('button', { name: '75' });
    expect(preset75).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: '90' })).not.toBeInTheDocument();

    fireEvent.click(preset75);
    expect(screen.getByDisplayValue('75')).toBeInTheDocument();
  });
});
