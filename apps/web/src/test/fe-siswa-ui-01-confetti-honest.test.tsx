import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, fireEvent, waitFor, act } from '@testing-library/react';
import * as StudentDataHooks from '@/lib/use-student-data';
import StudentTugasPage from '@/app/(portal)/portal/siswa/tugas/page';

vi.mock('@/components/ui/toast', () => ({ toast: { info: vi.fn(), success: vi.fn(), error: vi.fn() } }));

const aktifTask = { id: '1', title: 'Tugas A', subjectName: 'Matematika', teacherName: 'Bu A', status: 'aktif', dueDate: '2026-11-25' };

async function openModalAndSubmit(getByText: any) {
  fireEvent.click(getByText('Kumpulkan Tugas'));
  await act(async () => {
    fireEvent.click(getByText(/Kirim Tugas Sekarang/));
  });
}

describe('FE-SISWA-UI-01: confetti celebrates only a real submit', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.spyOn(StudentDataHooks, 'useTeachingMaterials').mockReturnValue({ data: [], isLoading: false, isError: false, refetch: vi.fn() } as any);
    vi.spyOn(StudentDataHooks, 'useStudentTasks').mockReturnValue({ data: [aktifTask], isLoading: false, isError: false, refetch: vi.fn() } as any);
  });

  it('FE-SISWA-UI-01-confetti-honest: does NOT fire confetti when the server submit fails', async () => {
    vi.spyOn(StudentDataHooks, 'useSubmitTask').mockReturnValue({ mutateAsync: vi.fn().mockRejectedValue(new Error('network')) } as any);
    const { getByText, container } = render(<StudentTugasPage />);
    await openModalAndSubmit(getByText);
    await waitFor(() => {
      expect(container.querySelectorAll('.confetti-piece').length).toBe(0);
    });
  });

  it('FE-SISWA-UI-01-confetti-honest: fires confetti when the server submit succeeds', async () => {
    vi.spyOn(StudentDataHooks, 'useSubmitTask').mockReturnValue({ mutateAsync: vi.fn().mockResolvedValue({}) } as any);
    const { getByText, container } = render(<StudentTugasPage />);
    await openModalAndSubmit(getByText);
    await waitFor(() => {
      expect(container.querySelectorAll('.confetti-piece').length).toBeGreaterThan(0);
    });
  });
});
