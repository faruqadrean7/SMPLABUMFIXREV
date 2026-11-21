import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import GuruTugasPage from '@/app/(portal)/portal/guru/tugas/page';
import { 
  getAssignmentStatus, 
  getDefaultDueDate 
} from '@/lib/tugas-utils';
import { lmsApi } from '@/lib/api-client';

// Mock Sonner Toast
vi.mock('sonner', () => ({
  toast: {
    success: vi.fn(),
    error: vi.fn(),
    info: vi.fn(),
  },
}));

// Mock useTeacherData hooks
vi.mock('@/lib/use-teacher-data', () => ({
  useTeacherClasses: () => ({
    data: [
      { id: 'cls-7a', name: '7-A' },
      { id: 'cls-7b', name: '7-B' },
    ],
  }),
  useTeacherSubjects: () => ({
    data: [
      { id: 'sbj-mtk', name: 'Matematika', code: 'MTK-7' },
    ],
  }),
}));

// Mock lmsApi
vi.mock('@/lib/api-client', () => ({
  lmsApi: {
    getAssignments: vi.fn(),
    getGradeComponents: vi.fn(),
    createAssignment: vi.fn(),
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

describe('FIX-GURU-04: Derivasi Status Tugas & Default Due Date', () => {
  const fixedNow = new Date('2026-11-05T10:00:00Z').getTime();

  describe('Unit: getDefaultDueDate', () => {
    it('AC3: generates a default due date 7 days in the future at 23:59', () => {
      const baseDate = new Date('2026-11-05T08:00:00Z');
      const dueDateStr = getDefaultDueDate(7, baseDate);

      expect(dueDateStr).toBe('2026-11-12T23:59');
      const dueDateMs = new Date(dueDateStr).getTime();
      expect(dueDateMs).toBeGreaterThan(baseDate.getTime());
    });
  });

  describe('Unit: getAssignmentStatus (AC1, AC2, AC4)', () => {
    it('AC1: new assignment with 0 submissions & future due date returns "Sedang Berjalan"', () => {
      const status = getAssignmentStatus(
        {
          is_locked: false,
          due_at: '2026-11-12T23:59:00Z',
          submissions_count: 0,
          reviewed_count: 0,
        },
        fixedNow
      );

      expect(status.type).toBe('active');
      expect(status.label).toBe('Sedang Berjalan');
      expect(status.unreviewedCount).toBe(0);
    });

    it('AC1: assignment with 0 submissions & past due date returns "Berakhir (Tanpa Pengumpulan)"', () => {
      const status = getAssignmentStatus(
        {
          is_locked: false,
          due_at: '2026-10-30T23:59:00Z',
          submissions_count: 0,
          reviewed_count: 0,
        },
        fixedNow
      );

      expect(status.type).toBe('past_due_empty');
      expect(status.label).toBe('Berakhir (Tanpa Pengumpulan)');
      expect(status.unreviewedCount).toBe(0);
    });

    it('AC4: assignment with partially graded submissions returns "N Perlu Dinilai"', () => {
      const status = getAssignmentStatus(
        {
          is_locked: false,
          due_at: '2026-11-10T23:59:00Z',
          submissions_count: 25,
          reviewed_count: 18,
        },
        fixedNow
      );

      expect(status.type).toBe('needs_grading');
      expect(status.label).toBe('7 Perlu Dinilai');
      expect(status.unreviewedCount).toBe(7);
    });

    it('AC2: assignment with >= 1 submissions where all are graded returns "Selesai Dinilai"', () => {
      const status = getAssignmentStatus(
        {
          is_locked: false,
          due_at: '2026-11-01T23:59:00Z',
          submissions_count: 30,
          reviewed_count: 30,
        },
        fixedNow
      );

      expect(status.type).toBe('all_graded');
      expect(status.label).toBe('Selesai Dinilai');
      expect(status.unreviewedCount).toBe(0);
    });

    it('returns "Terkunci" when assignment is explicitly locked regardless of counts', () => {
      const status = getAssignmentStatus(
        {
          is_locked: true,
          due_at: '2026-11-20T23:59:00Z',
          submissions_count: 5,
          reviewed_count: 2,
        },
        fixedNow
      );

      expect(status.type).toBe('locked');
      expect(status.label).toBe('Terkunci');
      expect(status.unreviewedCount).toBe(0);
    });
  });

  describe('Component: GuruTugasPage Rendering', () => {
    const futureDue = new Date(Date.now() + 86400000 * 5).toISOString();
    const pastDue = new Date(Date.now() - 86400000 * 5).toISOString();

    const mockAssignments = [
      {
        id: 'asg-new-01',
        title: 'Tugas Aljabar 1 (Tugas Baru)',
        description: 'Tugas baru tanpa pengumpulan',
        due_at: futureDue,
        submissions_count: 0,
        reviewed_count: 0,
        total_students: 30,
        is_locked: false,
        allow_late: true,
        class_id: 'cls-7a',
      },
      {
        id: 'asg-part-02',
        title: 'Tugas Geometri 2 (Sebagian Dinilai)',
        description: 'Sebagian sudah dinilai',
        due_at: futureDue,
        submissions_count: 15,
        reviewed_count: 10,
        total_students: 30,
        is_locked: false,
        allow_late: true,
        class_id: 'cls-7a',
      },
      {
        id: 'asg-full-03',
        title: 'Tugas Statistika 3 (Selesai Dinilai)',
        description: 'Semua submission sudah dinilai',
        due_at: pastDue,
        submissions_count: 28,
        reviewed_count: 28,
        total_students: 30,
        is_locked: false,
        allow_late: true,
        class_id: 'cls-7a',
      },
      {
        id: 'asg-past-empty-04',
        title: 'Tugas Eksperimen 4 (Lewat Tanpa Kumpul)',
        description: 'Lewat tenggat tanpa siswa kumpul',
        due_at: pastDue,
        submissions_count: 0,
        reviewed_count: 0,
        total_students: 30,
        is_locked: false,
        allow_late: false,
        class_id: 'cls-7a',
      },
      {
        id: 'asg-locked-05',
        title: 'Tugas Matriks 5 (Terkunci)',
        description: 'Tugas terkunci',
        due_at: pastDue,
        submissions_count: 20,
        reviewed_count: 20,
        total_students: 30,
        is_locked: true,
        allow_late: false,
        class_id: 'cls-7a',
      },
    ];

    beforeEach(() => {
      vi.clearAllMocks();
      (lmsApi.getAssignments as any).mockResolvedValue(mockAssignments);
      (lmsApi.getGradeComponents as any).mockResolvedValue([]);
    });

    it('renders correct badges for all assignments including new and graded ones', async () => {
      renderWithClient(<GuruTugasPage />);

      expect(await screen.findByText('Tugas Aljabar 1 (Tugas Baru)')).toBeInTheDocument();

      // AC1: New assignment displays "Sedang Berjalan" (NOT "Selesai Dinilai")
      const sedangBerjalanSpans = screen.getAllByText('Sedang Berjalan').filter(el => el.tagName === 'SPAN');
      expect(sedangBerjalanSpans.length).toBeGreaterThan(0);

      // AC4: Partially reviewed displays "5 Perlu Dinilai" (15 - 10)
      expect(screen.getByText('5 Perlu Dinilai')).toBeInTheDocument();

      // AC2: Fully reviewed with >= 1 submissions displays "Selesai Dinilai"
      expect(screen.getByText('Selesai Dinilai')).toBeInTheDocument();

      // Empty past due displays "Berakhir (Tanpa Pengumpulan)"
      expect(screen.getByText('Berakhir (Tanpa Pengumpulan)')).toBeInTheDocument();

      // Locked displays "Terkunci"
      const lockedBadges = screen.getAllByText('Terkunci');
      expect(lockedBadges.length).toBeGreaterThan(0);
    });

    it('AC3: default due date in task creation modal is not in the past', async () => {
      renderWithClient(<GuruTugasPage />);

      expect(await screen.findByText('Tugas Aljabar 1 (Tugas Baru)')).toBeInTheDocument();

      const openModalBtn = screen.getByRole('button', { name: /\+ Terbitkan Tugas Baru/i });
      fireEvent.click(openModalBtn);

      const dueDateInput = screen.getByLabelText(/Tenggat Waktu \(Due Date\)/i) as HTMLInputElement;
      expect(dueDateInput).toBeInTheDocument();
      expect(dueDateInput.value).toBeTruthy();

      const inputDateMs = new Date(dueDateInput.value).getTime();
      expect(inputDateMs).toBeGreaterThan(Date.now());
    });
  });
});
