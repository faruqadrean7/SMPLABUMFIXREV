import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import * as StudentDataHooks from '@/lib/use-student-data';
import StudentTugasPage from '@/app/(portal)/portal/siswa/tugas/page';
import { lmsApi } from '@/lib/lms-api';
import { apiClient } from '@/lib/api-client';

vi.mock('@/components/ui/toast', () => ({
  toast: { info: vi.fn(), success: vi.fn(), error: vi.fn() },
}));

describe('P0-16 & P1-10: Konsistensi LMS Portal Guru & Siswa', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('portal siswa menampilkan pesan error jujur saat submit gagal dan tidak memalsukan status dikumpulkan', async () => {
    vi.spyOn(StudentDataHooks, 'useTeachingMaterials').mockReturnValue({
      data: [],
      isLoading: false,
      isError: false,
      refetch: vi.fn(),
    } as any);

    const task = {
      id: 'task-1',
      title: 'Tugas Algoritma',
      subjectName: 'Informatika',
      teacherName: 'Bu Sari',
      status: 'aktif',
      dueDate: '2026-12-01',
    };

    vi.spyOn(StudentDataHooks, 'useStudentTasks').mockReturnValue({
      data: [task],
      isLoading: false,
      isError: false,
      refetch: vi.fn(),
    } as any);

    vi.spyOn(StudentDataHooks, 'useSubmitTask').mockReturnValue({
      mutateAsync: vi.fn().mockRejectedValue({
        response: { data: { message: 'Anda tidak terdaftar di kelas tugas ini.' } },
      }),
      isPending: false,
    } as any);

    render(<StudentTugasPage />);
    fireEvent.click(screen.getByText('Kumpulkan Tugas'));
    fireEvent.click(screen.getByText(/Kirim Tugas Sekarang/));

    await waitFor(() => {
      expect(screen.getByText('Anda tidak terdaftar di kelas tugas ini.')).toBeInTheDocument();
      expect(screen.queryByText('Tugas Berhasil Dikumpulkan!')).not.toBeInTheDocument();
      expect(task.status).toBe('aktif');
    });
  });

  it('lmsApi.lockTeacherGrades memanggil endpoint guru /me/teacher/grades/lock', async () => {
    const postSpy = vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
      data: { data: { locked_count: 10 } },
    } as any);

    await lmsApi.lockTeacherGrades({
      class_id: 'cls-1',
      subject_id: 'sub-1',
      period_id: 'prd-1',
    });

    expect(postSpy).toHaveBeenCalledWith('/me/teacher/grades/lock', {
      class_id: 'cls-1',
      subject_id: 'sub-1',
      period_id: 'prd-1',
    });
  });
});
