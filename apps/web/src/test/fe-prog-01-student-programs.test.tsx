import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import {
  TahfidzWidget,
  IcpWidget,
  InklusiWidget,
  ProgramMaterialsCard,
  ProgramTargetsCard,
  ProgramTasksCard,
} from '@/features/portal/student-programs';
import * as StudentDataModule from '@/lib/use-student-data';

// Mock next/navigation
vi.mock('next/navigation', () => ({
  useRouter: () => ({
    push: vi.fn(),
    replace: vi.fn(),
  }),
  useSearchParams: () => ({
    get: vi.fn().mockReturnValue(null),
  }),
}));

describe('FE-PROG-01: Portal Siswa Program Khusus (Tahfidz, ICP, Inklusi)', () => {
  const mockMutateAsync = vi.fn();
  const mockRefetchMaterials = vi.fn();
  const mockRefetchTargets = vi.fn();
  const mockRefetchTasks = vi.fn();

  beforeEach(() => {
    vi.clearAllMocks();

    // Default: Return empty success responses
    vi.spyOn(StudentDataModule, 'useProgramMaterials').mockReturnValue({
      data: { data: [] },
      isLoading: false,
      isError: false,
      refetch: mockRefetchMaterials,
    } as any);

    vi.spyOn(StudentDataModule, 'useProgramTargets').mockReturnValue({
      data: { data: [] },
      isLoading: false,
      isError: false,
      refetch: mockRefetchTargets,
    } as any);

    vi.spyOn(StudentDataModule, 'useProgramTasks').mockReturnValue({
      data: { data: [] },
      isLoading: false,
      isError: false,
      refetch: mockRefetchTasks,
    } as any);

    vi.spyOn(StudentDataModule, 'useSubmitProgramTask').mockReturnValue({
      mutateAsync: mockMutateAsync,
      isPending: false,
      isError: false,
    } as any);

    vi.spyOn(StudentDataModule, 'useSubmitMutabaah').mockReturnValue({
      mutateAsync: vi.fn().mockResolvedValue({}),
      isPending: false,
    } as any);

    vi.spyOn(StudentDataModule, 'useSubmitDailyMood').mockReturnValue({
      mutateAsync: vi.fn().mockResolvedValue({}),
      isPending: false,
    } as any);
  });

  // =========================================================================
  // AC1: KETIGA PROGRAM MENAMPILKAN MATERI, TUGAS, TARGET
  // =========================================================================
  describe('AC1: Integrasi Materi, Tugas, dan Target pada Ketiga Program', () => {
    it('FE-PROG-01 (AC1): TahfidzWidget memiliki tab navigasi Materi, Tugas Setoran, dan Target', () => {
      render(
        <TahfidzWidget
          profile={{
            current_juz: 2,
            target_juz: 3,
            current_surah: 'Al-Mulk: 1-30',
            last_tasmi_grade: 'Mumtaz',
            tasmi_evaluator: 'Ustadz Abdullah, S.Pd.I',
          }}
        />
      );

      // Default tab: Ringkasan
      expect(screen.getByText(/Capaian Tahfidz & Ibadah Harian/i)).toBeInTheDocument();
      expect(screen.getByText(/Al-Mulk: 1-30/i)).toBeInTheDocument();

      // Switch to Tab: Materi
      const materiTab = screen.getByRole('button', { name: /Materi/i });
      fireEvent.click(materiTab);
      expect(screen.getByText(/Materi Tajwid & Audio Murottal/i)).toBeInTheDocument();

      // Switch to Tab: Tugas Setoran
      const tugasTab = screen.getByRole('button', { name: /Tugas Setoran/i });
      fireEvent.click(tugasTab);
      expect(screen.getByText(/Tugas & Rekaman Setoran Mandiri/i)).toBeInTheDocument();

      // Switch to Tab: Target
      const targetTab = screen.getByRole('button', { name: /Target/i });
      fireEvent.click(targetTab);
      expect(screen.getByText(/Target Capaian Juz & Mutqin/i)).toBeInTheDocument();
    });

    it('FE-PROG-01 (AC1): IcpWidget memiliki tab navigasi Past Papers (Materi), Tasks, dan Targets', () => {
      render(
        <IcpWidget
          profile={{
            cambridge_candidate_id: 'ID-MAL-2026-042',
            checkpoint_readiness: {
              english: { score: 4.8, status: 'Ready' },
              math: { score: 5.2, status: 'Exceeding' },
            },
          }}
        />
      );

      // Default: Ringkasan
      expect(screen.getByText(/Cambridge Checkpoint & Global Learning/i)).toBeInTheDocument();

      // Switch to Past Papers
      const pastPapersTab = screen.getByRole('button', { name: /Past Papers/i });
      fireEvent.click(pastPapersTab);
      expect(screen.getByText(/Past Papers & Revision Notes/i)).toBeInTheDocument();

      // Switch to Tasks
      const tasksTab = screen.getByRole('button', { name: /Tasks/i });
      fireEvent.click(tasksTab);
      expect(screen.getByText(/Cambridge Practice Tasks & Essays/i)).toBeInTheDocument();

      // Switch to Targets
      const targetsTab = screen.getByRole('button', { name: /Targets/i });
      fireEvent.click(targetsTab);
      expect(screen.getByText(/Target Band Score Checkpoint/i)).toBeInTheDocument();
    });

    it('FE-PROG-01 (AC1): InklusiWidget memiliki tab navigasi Modul Adaptif (Materi), Tugas Terbimbing, dan Target PPI', () => {
      render(
        <InklusiWidget
          profile={{
            gpk: { name: 'Ibu Ratna Dewi, S.Pd (GPK)', room: 'Ruang Sumber Inklusi' },
            ppi_milestones: [{ id: 'm1', title: 'Latihan motorik', is_completed: false }],
          }}
        />
      );

      // Default: Ringkasan
      expect(screen.getByText(/Pendampingan Inklusi & Target Belajar/i)).toBeInTheDocument();

      // Switch to Modul Adaptif
      const modulTab = screen.getByRole('button', { name: /Modul Adaptif/i });
      fireEvent.click(modulTab);
      expect(screen.getByText(/Modul & Bahan Ajar Adaptif/i)).toBeInTheDocument();

      // Switch to Tugas Terbimbing
      const tugasTab = screen.getByRole('button', { name: /Tugas Terbimbing/i });
      fireEvent.click(tugasTab);
      expect(screen.getByText(/Tugas Belajar Mandiri & Terbimbing/i)).toBeInTheDocument();

      // Switch to Target PPI
      const targetTab = screen.getByRole('button', { name: /Target PPI/i });
      fireEvent.click(targetTab);
      expect(screen.getByText(/Target Capaian Pembelajaran Individual \(PPI\)/i)).toBeInTheDocument();
    });
  });

  // =========================================================================
  // AC2: STATE LOADING / KOSONG / ERROR PADA SETIAP KOMPONEN
  // =========================================================================
  describe('AC2: State Loading, Kosong, dan Error Komponen Program', () => {
    it('FE-PROG-01 (AC2): ProgramMaterialsCard menangani state loading, error, empty, dan populated', () => {
      // 1. Loading state
      vi.spyOn(StudentDataModule, 'useProgramMaterials').mockReturnValue({
        data: undefined,
        isLoading: true,
        isError: false,
        refetch: mockRefetchMaterials,
      } as any);
      const { rerender } = render(<ProgramMaterialsCard />);
      expect(document.querySelector('.animate-pulse')).toBeInTheDocument();

      // 2. Error state
      vi.spyOn(StudentDataModule, 'useProgramMaterials').mockReturnValue({
        data: undefined,
        isLoading: false,
        isError: true,
        refetch: mockRefetchMaterials,
      } as any);
      rerender(<ProgramMaterialsCard />);
      expect(screen.getByText(/Gagal memuat materi/i)).toBeInTheDocument();
      fireEvent.click(screen.getByRole('button', { name: /Coba Lagi/i }));
      expect(mockRefetchMaterials).toHaveBeenCalled();

      // 3. Empty state
      vi.spyOn(StudentDataModule, 'useProgramMaterials').mockReturnValue({
        data: { data: [] },
        isLoading: false,
        isError: false,
        refetch: mockRefetchMaterials,
      } as any);
      rerender(<ProgramMaterialsCard />);
      expect(screen.getByText(/Belum Ada Materi Khusus/i)).toBeInTheDocument();

      // 4. Populated state
      vi.spyOn(StudentDataModule, 'useProgramMaterials').mockReturnValue({
        data: {
          data: [
            {
              id: 'mat-1',
              title: 'Murottal Surat Al-Mulk Syaikh Al-Ghamidi',
              description: 'Dengarkan pelafalan tajwid surat Al-Mulk ayat 1-30',
              material_type: 'murottal',
              external_url: 'https://archive.org/murottal-mulk',
              created_at: '2026-11-03T08:00:00Z',
            },
          ],
        },
        isLoading: false,
        isError: false,
        refetch: mockRefetchMaterials,
      } as any);
      rerender(<ProgramMaterialsCard />);
      expect(screen.getByText(/Murottal Surat Al-Mulk Syaikh Al-Ghamidi/i)).toBeInTheDocument();
      expect(screen.getByText(/Audio Murottal/i)).toBeInTheDocument();
      expect(screen.getByRole('link', { name: /Buka Tautan/i })).toHaveAttribute(
        'href',
        'https://archive.org/murottal-mulk'
      );
    });

    it('FE-PROG-01 (AC2): ProgramTargetsCard menangani state loading, error, empty, dan populated', () => {
      // 1. Loading state
      vi.spyOn(StudentDataModule, 'useProgramTargets').mockReturnValue({
        data: undefined,
        isLoading: true,
        isError: false,
        refetch: mockRefetchTargets,
      } as any);
      const { rerender } = render(<ProgramTargetsCard />);
      expect(document.querySelector('.animate-pulse')).toBeInTheDocument();

      // 2. Error state
      vi.spyOn(StudentDataModule, 'useProgramTargets').mockReturnValue({
        data: undefined,
        isLoading: false,
        isError: true,
        refetch: mockRefetchTargets,
      } as any);
      rerender(<ProgramTargetsCard />);
      expect(screen.getByText(/Gagal memuat target capaian/i)).toBeInTheDocument();
      fireEvent.click(screen.getByRole('button', { name: /Coba Lagi/i }));
      expect(mockRefetchTargets).toHaveBeenCalled();

      // 3. Empty state
      vi.spyOn(StudentDataModule, 'useProgramTargets').mockReturnValue({
        data: { data: [] },
        isLoading: false,
        isError: false,
        refetch: mockRefetchTargets,
      } as any);
      rerender(<ProgramTargetsCard />);
      expect(screen.getByText(/Belum Ada Target Aktif/i)).toBeInTheDocument();

      // 4. Populated state
      vi.spyOn(StudentDataModule, 'useProgramTargets').mockReturnValue({
        data: {
          data: [
            {
              id: 'target-1',
              title: 'Hafalan Juz 2 Mutqin',
              domain: 'hafalan',
              current_value: '1.5 Juz',
              target_value: '2 Juz',
              period: 'Semester Ganjil 2026/2027',
              status: 'in_progress',
              created_at: '2026-11-03T08:00:00Z',
            },
          ],
        },
        isLoading: false,
        isError: false,
        refetch: mockRefetchTargets,
      } as any);
      rerender(<ProgramTargetsCard />);
      expect(screen.getByText(/Hafalan Juz 2 Mutqin/i)).toBeInTheDocument();
      expect(screen.getByText(/Hafalan Al-Qur’an/i)).toBeInTheDocument();
      expect(screen.getByText(/1.5 Juz/i)).toBeInTheDocument();
      expect(screen.getByText(/Dalam Proses/i)).toBeInTheDocument();
    });

    it('FE-PROG-01 (AC2): ProgramTasksCard menangani state loading, error, empty, dan populated dengan status badge', () => {
      // 1. Loading state
      vi.spyOn(StudentDataModule, 'useProgramTasks').mockReturnValue({
        data: undefined,
        isLoading: true,
        isError: false,
        refetch: mockRefetchTasks,
      } as any);
      const { rerender } = render(<ProgramTasksCard />);
      expect(document.querySelector('.animate-pulse')).toBeInTheDocument();

      // 2. Error state
      vi.spyOn(StudentDataModule, 'useProgramTasks').mockReturnValue({
        data: undefined,
        isLoading: false,
        isError: true,
        refetch: mockRefetchTasks,
      } as any);
      rerender(<ProgramTasksCard />);
      expect(screen.getByText(/Gagal memuat tugas program/i)).toBeInTheDocument();
      fireEvent.click(screen.getByRole('button', { name: /Coba Lagi/i }));
      expect(mockRefetchTasks).toHaveBeenCalled();

      // 3. Empty state
      vi.spyOn(StudentDataModule, 'useProgramTasks').mockReturnValue({
        data: { data: [] },
        isLoading: false,
        isError: false,
        refetch: mockRefetchTasks,
      } as any);
      rerender(<ProgramTasksCard />);
      expect(screen.getByText(/Tidak Ada Tugas Aktif/i)).toBeInTheDocument();

      // 4. Populated with graded task
      vi.spyOn(StudentDataModule, 'useProgramTasks').mockReturnValue({
        data: {
          data: [
            {
              id: 'task-1',
              title: 'Setoran Rekaman Audio Surat Al-Mulk 1-15',
              instructions: 'Kirimkan rekaman audio makhraj tajwid',
              due_at: '2026-11-10T12:00:00Z',
              submissions: [
                {
                  id: 'sub-1',
                  status: 'dinilai',
                  score: 95,
                  feedback: 'Makhraj dan tajwid sangat baik. Pertahankan!',
                },
              ],
            },
          ],
        },
        isLoading: false,
        isError: false,
        refetch: mockRefetchTasks,
      } as any);
      rerender(<ProgramTasksCard />);
      expect(screen.getByText(/Setoran Rekaman Audio Surat Al-Mulk 1-15/i)).toBeInTheDocument();
      expect(screen.getByText(/Nilai: 95\/100/i)).toBeInTheDocument();
      expect(screen.getByText(/Catatan Pembina:/i)).toBeInTheDocument();
      expect(screen.getByText(/Makhraj dan tajwid sangat baik/i)).toBeInTheDocument();
    });
  });

  // =========================================================================
  // AC3: SUBMIT TUGAS SUKSES MEMPERBARUI STATUS TANPA RELOAD PENUH
  // =========================================================================
  describe('AC3: Submit Tugas Mandiri & Modal Form', () => {
    it('FE-PROG-01 (AC3): Membuka modal submit, validasi input kosong, dan submit sukses memanggil mutasi dengan Idempotency-Key', async () => {
      mockMutateAsync.mockResolvedValueOnce({
        id: 'sub-new-123',
        status: 'dikumpulkan',
        notes: 'Rekaman audio sudah saya selesaikan di Google Drive',
      });

      vi.spyOn(StudentDataModule, 'useProgramTasks').mockReturnValue({
        data: {
          data: [
            {
              id: 'task-99',
              title: 'Latihan Menulis Essay Deskriptif Cambridge',
              instructions: 'Tulis essay minimal 150 kata tentang global warming',
              due_at: '2026-11-15T15:00:00Z',
              submissions: [],
            },
          ],
        },
        isLoading: false,
        isError: false,
        refetch: mockRefetchTasks,
      } as any);

      render(<ProgramTasksCard />);

      // Status awal: Belum Dikumpulkan
      expect(screen.getByText(/Belum Dikumpulkan/i)).toBeInTheDocument();

      // Klik Kumpulkan Tugas
      const submitBtn = screen.getByRole('button', { name: /Kumpulkan Tugas/i });
      fireEvent.click(submitBtn);

      // Modal terbuka
      expect(screen.getByRole('dialog')).toBeInTheDocument();
      expect(screen.getByText(/Pengumpulan Tugas Program/i)).toBeInTheDocument();

      // Coba submit form kosong -> validasi harus muncul
      const submitFormBtn = screen.getByRole('button', { name: /Kirimkan Tugas/i });
      fireEvent.click(submitFormBtn);

      await waitFor(() => {
        expect(
          screen.getByText(/Harap isi salah satu: catatan jawaban atau tautan berkas tugas/i)
        ).toBeInTheDocument();
      });
      expect(mockMutateAsync).not.toHaveBeenCalled();

      // Isi input link dan notes yang valid
      const linkInput = screen.getByPlaceholderText(/https:\/\/drive.google.com/i);
      const notesInput = screen.getByPlaceholderText(/Tuliskan catatan, ringkasan pengerjaan/i);

      fireEvent.change(linkInput, {
        target: { value: 'https://drive.google.com/file/d/my-essay-123/view' },
      });
      fireEvent.change(notesInput, {
        target: { value: 'Berikut link essay Cambridge saya mengenai global warming.' },
      });

      // Submit form yang valid
      fireEvent.click(submitFormBtn);

      await waitFor(() => {
        expect(mockMutateAsync).toHaveBeenCalledWith(
          expect.objectContaining({
            taskId: 'task-99',
            payload: {
              link: 'https://drive.google.com/file/d/my-essay-123/view',
              notes: 'Berikut link essay Cambridge saya mengenai global warming.',
            },
            idempotencyKey: expect.any(String),
          })
        );
      });

      // Muncul notifikasi sukses dan modal tertutup
      await waitFor(() => {
        expect(screen.getByText(/Tugas berhasil dikumpulkan!/i)).toBeInTheDocument();
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
      });
    });
  });
});
