import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import TeacherProgramKhususPage from '@/app/(portal)/portal/guru/program-khusus/page';
import * as TeacherProgramDataModule from '@/lib/use-teacher-program-data';
import * as TeacherDataModule from '@/lib/use-teacher-data';
import * as AuthProviderModule from '@/providers/auth-provider';
import { User } from '@/types/auth';

// Mock next/navigation
vi.mock('next/navigation', () => ({
  useRouter: () => ({
    push: vi.fn(),
    replace: vi.fn(),
  }),
  useSearchParams: () => ({
    get: vi.fn().mockReturnValue(null),
  }),
  usePathname: () => '/guru/program-khusus',
}));

describe('GURU-PROG-01: Panel Kelola Program Khusus Guru Pembina', () => {
  const mockCreateMaterial = vi.fn();
  const mockCreateTask = vi.fn();
  const mockCreateTarget = vi.fn();
  const mockGradeSubmission = vi.fn();

  const userWithPermission: User = {
    id: 'user-guru-1',
    schoolId: 'school-1',
    username: 'pembina_tahfidz',
    email: 'pembina@labschool.sch.id',
    phone: null,
    status: 'active',
    mustChangePassword: false,
    lastLoginAt: null,
    roles: ['teacher'],
    permissions: ['program.manage'],
    profile: {},
  };

  const userWithoutPermission: User = {
    id: 'user-guru-2',
    schoolId: 'school-1',
    username: 'guru_biasa',
    email: 'guru@labschool.sch.id',
    phone: null,
    status: 'active',
    mustChangePassword: false,
    lastLoginAt: null,
    roles: ['teacher'],
    permissions: [], // Tanpa permission program.manage
    profile: {},
  };

  beforeEach(() => {
    vi.clearAllMocks();

    // Default: Authenticated with program.manage permission
    vi.spyOn(AuthProviderModule, 'useAuth').mockReturnValue({
      user: userWithPermission,
      isLoading: false,
      isAuthenticated: true,
      activeRole: 'teacher',
      login: vi.fn(),
      logout: vi.fn(),
      checkAuth: vi.fn(),
      setActiveRole: vi.fn(),
    });

    vi.spyOn(TeacherDataModule, 'useTeacherClasses').mockReturnValue({
      data: [
        { id: 'class-1', name: 'VII-Tahfidz-1', gradeLevel: 7, studentCount: 28 },
        { id: 'class-2', name: 'VII-ICP-1', gradeLevel: 7, studentCount: 25 },
      ],
      isLoading: false,
      isError: false,
    } as any);

    // Mock Queries
    vi.spyOn(TeacherProgramDataModule, 'useTeacherProgramMaterials').mockReturnValue({
      data: {
        data: [
          {
            id: 'mat-1',
            program_type: 'tahfidz',
            title: 'Kaidah Tajwid Makharijul Huruf',
            material_type: 'tajwid',
            description: 'Panduan makhraj dan sifat huruf',
            external_url: 'https://drive.google.com/tajwid-file',
            schoolClass: { id: 'class-1', name: 'VII-Tahfidz-1' },
            created_at: '2026-11-03T08:00:00Z',
          },
        ],
      },
      isLoading: false,
      isError: false,
      refetch: vi.fn(),
    } as any);

    vi.spyOn(TeacherProgramDataModule, 'useTeacherProgramTasks').mockReturnValue({
      data: {
        data: [
          {
            id: 'task-1',
            program_type: 'tahfidz',
            title: 'Setoran Surah Al-Mulk 1-20',
            instructions: 'Kirimkan rekaman audio hafalan mandiri',
            due_at: '2026-11-15T12:00:00Z',
            submissions_count: 3,
            schoolClass: { id: 'class-1', name: 'VII-Tahfidz-1' },
            created_at: '2026-11-03T08:00:00Z',
          },
        ],
      },
      isLoading: false,
      isError: false,
      refetch: vi.fn(),
    } as any);

    vi.spyOn(TeacherProgramDataModule, 'useTeacherProgramTargets').mockReturnValue({
      data: {
        data: [
          {
            id: 'target-1',
            student_id: 'std-1',
            program_type: 'tahfidz',
            title: 'Target Hafalan Juz 2 Mutqin',
            domain: 'hafalan',
            target_value: '2 Juz',
            current_value: '1.5 Juz',
            period: 'Semester Ganjil 2026/2027',
            status: 'aktif',
            student: { id: 'std-1', full_name: 'Ahmad Faiz', nis: '202601' },
            created_at: '2026-11-03T08:00:00Z',
          },
        ],
      },
      isLoading: false,
      isError: false,
      refetch: vi.fn(),
    } as any);

    vi.spyOn(TeacherProgramDataModule, 'useTeacherTaskSubmissions').mockReturnValue({
      data: {
        data: [
          {
            id: 'sub-1',
            task_id: 'task-1',
            student_id: 'std-1',
            link: 'https://drive.google.com/audio-faiz.mp3',
            notes: 'Rekaman hafalan Al-Mulk ayat 1-20',
            status: 'dikumpulkan',
            score: null,
            submitted_at: '2026-11-03T08:30:00Z',
            student: { id: 'std-1', full_name: 'Ahmad Faiz', nis: '202601' },
          },
        ],
      },
      isLoading: false,
      isError: false,
    } as any);

    // Mock Mutations
    vi.spyOn(TeacherProgramDataModule, 'useCreateProgramMaterial').mockReturnValue({
      mutateAsync: mockCreateMaterial.mockResolvedValue({ id: 'mat-new' }),
      isPending: false,
    } as any);

    vi.spyOn(TeacherProgramDataModule, 'useUpdateProgramMaterial').mockReturnValue({
      mutateAsync: vi.fn().mockResolvedValue({}),
      isPending: false,
    } as any);

    vi.spyOn(TeacherProgramDataModule, 'useDeleteProgramMaterial').mockReturnValue({
      mutate: vi.fn(),
    } as any);

    vi.spyOn(TeacherProgramDataModule, 'useCreateProgramTask').mockReturnValue({
      mutateAsync: mockCreateTask.mockResolvedValue({ id: 'task-new' }),
      isPending: false,
    } as any);

    vi.spyOn(TeacherProgramDataModule, 'useUpdateProgramTask').mockReturnValue({
      mutateAsync: vi.fn().mockResolvedValue({}),
      isPending: false,
    } as any);

    vi.spyOn(TeacherProgramDataModule, 'useDeleteProgramTask').mockReturnValue({
      mutate: vi.fn(),
    } as any);

    vi.spyOn(TeacherProgramDataModule, 'useCreateProgramTarget').mockReturnValue({
      mutateAsync: mockCreateTarget.mockResolvedValue({ id: 'target-new' }),
      isPending: false,
    } as any);

    vi.spyOn(TeacherProgramDataModule, 'useUpdateProgramTarget').mockReturnValue({
      mutateAsync: vi.fn().mockResolvedValue({}),
      isPending: false,
    } as any);

    vi.spyOn(TeacherProgramDataModule, 'useDeleteProgramTarget').mockReturnValue({
      mutate: vi.fn(),
    } as any);

    vi.spyOn(TeacherProgramDataModule, 'useGradeProgramSubmission').mockReturnValue({
      mutateAsync: mockGradeSubmission.mockResolvedValue({ id: 'sub-1', score: 95 }),
      isPending: false,
    } as any);
  });

  // =========================================================================
  // AC1: GURU PEMBINA DAPAT MENAMBAH MATERI, TUGAS, TARGET, DAN MENILAI
  // =========================================================================

  it('GURU-PROG-01 (AC1): Merender panel kelola dan navigasi antar tab materi, tugas, dan target', () => {
    render(<TeacherProgramKhususPage />);

    expect(screen.getByText(/Kelola Program Khusus/i)).toBeInTheDocument();

    // Default tab: Materi
    expect(screen.getByText(/Daftar Materi & Sumber Belajar/i)).toBeInTheDocument();
    expect(screen.getByText(/Kaidah Tajwid Makharijul Huruf/i)).toBeInTheDocument();

    // Switch ke Tab Tugas
    const tugasTab = screen.getByRole('button', { name: /Tugas & Penilaian/i });
    fireEvent.click(tugasTab);
    expect(screen.getByText(/Daftar Tugas & Setoran Mandiri/i)).toBeInTheDocument();
    expect(screen.getByText(/Setoran Surah Al-Mulk 1-20/i)).toBeInTheDocument();

    // Switch ke Tab Target
    const targetTab = screen.getByRole('button', { name: /Target Capaian/i });
    fireEvent.click(targetTab);
    expect(screen.getByText(/Daftar Target Capaian Siswa/i)).toBeInTheDocument();
    expect(screen.getByText(/Target Hafalan Juz 2 Mutqin/i)).toBeInTheDocument();
  });

  it('GURU-PROG-01 (AC1): Guru dapat membuka form dan menambahkan materi baru', async () => {
    render(<TeacherProgramKhususPage />);

    const addMaterialBtn = screen.getByRole('button', { name: /Tambah Materi/i });
    fireEvent.click(addMaterialBtn);

    // Modal terbuka
    expect(screen.getByRole('dialog')).toBeInTheDocument();
    expect(screen.getByText('Tambah Materi Program')).toBeInTheDocument();

    const titleInput = screen.getByPlaceholderText(/Kaidah Tajwid Makhraj/i);
    fireEvent.change(titleInput, { target: { value: 'Audio Murottal Surat An-Naba Syaikh Misyari' } });

    const submitBtn = screen.getByRole('button', { name: /Simpan Materi/i });
    fireEvent.click(submitBtn);

    await waitFor(() => {
      expect(mockCreateMaterial).toHaveBeenCalledWith(
        expect.objectContaining({
          title: 'Audio Murottal Surat An-Naba Syaikh Misyari',
          program_type: 'tahfidz',
        })
      );
    });
  });

  it('GURU-PROG-01 (AC1): Guru dapat membuka form dan menambahkan tugas program baru', async () => {
    render(<TeacherProgramKhususPage />);

    const tugasTab = screen.getByRole('button', { name: /Tugas & Penilaian/i });
    fireEvent.click(tugasTab);

    const addTaskBtn = screen.getByRole('button', { name: /Tambah Tugas/i });
    fireEvent.click(addTaskBtn);

    expect(screen.getByRole('dialog')).toBeInTheDocument();
    expect(screen.getByText('Tambah Tugas Program Baru')).toBeInTheDocument();

    const titleInput = screen.getByPlaceholderText(/Setoran Mandiri Surah/i);
    fireEvent.change(titleInput, { target: { value: 'Latihan Soal Cambridge Checkpoint 2026' } });

    const submitBtn = screen.getByRole('button', { name: /Simpan Tugas/i });
    fireEvent.click(submitBtn);

    await waitFor(() => {
      expect(mockCreateTask).toHaveBeenCalledWith(
        expect.objectContaining({
          title: 'Latihan Soal Cambridge Checkpoint 2026',
        })
      );
    });
  });

  it('GURU-PROG-01 (AC1): Guru dapat membuka form dan menambahkan target siswa baru', async () => {
    render(<TeacherProgramKhususPage />);

    const targetTab = screen.getByRole('button', { name: /Target Capaian/i });
    fireEvent.click(targetTab);

    const addTargetBtn = screen.getByRole('button', { name: /Tambah Target Siswa/i });
    fireEvent.click(addTargetBtn);

    expect(screen.getByRole('dialog')).toBeInTheDocument();
    expect(screen.getByText('Tambah Target Capaian Siswa')).toBeInTheDocument();

    const studentInput = screen.getByPlaceholderText(/ID Siswa \(UUID\)/i);
    const titleInput = screen.getByPlaceholderText(/Hafalan Juz 3 Mutqin/i);

    fireEvent.change(studentInput, { target: { value: '01a19f45-0000-0000-0000-000000000001' } });
    fireEvent.change(titleInput, { target: { value: 'Target Hafalan Juz 3 Mutqin' } });

    const submitBtn = screen.getByRole('button', { name: /Simpan Target/i });
    fireEvent.click(submitBtn);

    await waitFor(() => {
      expect(mockCreateTarget).toHaveBeenCalledWith(
        expect.objectContaining({
          student_id: '01a19f45-0000-0000-0000-000000000001',
          title: 'Target Hafalan Juz 3 Mutqin',
        })
      );
    });
  });

  it('GURU-PROG-01 (AC1): Guru dapat menelaah submission tugas siswa dan memberikan penilaian serta catatan feedback', async () => {
    render(<TeacherProgramKhususPage />);

    const tugasTab = screen.getByRole('button', { name: /Tugas & Penilaian/i });
    fireEvent.click(tugasTab);

    // Klik tombol telaah pada tabel tugas
    const telaahBtn = screen.getByRole('button', { name: /Telaah/i });
    fireEvent.click(telaahBtn);

    // Dialog submission terbuka
    expect(screen.getByRole('dialog')).toBeInTheDocument();
    expect(screen.getByText(/Pengumpulan Tugas: Setoran Surah Al-Mulk 1-20/i)).toBeInTheDocument();
    expect(screen.getByText('Ahmad Faiz')).toBeInTheDocument();

    // Klik tombol Beri Nilai
    const beriNilaiBtn = screen.getByRole('button', { name: /Beri Nilai/i });
    fireEvent.click(beriNilaiBtn);

    // Form modal penilaian terbuka
    expect(screen.getByText('Penilaian Submission Tugas')).toBeInTheDocument();

    const scoreInput = screen.getByRole('spinbutton');
    const feedbackInput = screen.getByPlaceholderText(/Tuliskan umpan balik untuk siswa/i);

    fireEvent.change(scoreInput, { target: { value: '95' } });
    fireEvent.change(feedbackInput, { target: { value: 'Tajwid dan makhraj sangat baik.' } });

    const saveGradeBtn = screen.getByRole('button', { name: /Simpan Nilai/i });
    fireEvent.click(saveGradeBtn);

    await waitFor(() => {
      expect(mockGradeSubmission).toHaveBeenCalledWith(
        expect.objectContaining({
          submissionId: 'sub-1',
          payload: {
            score: 95,
            feedback: 'Tajwid dan makhraj sangat baik.',
            status: 'dinilai',
          },
        })
      );
    });
  });

  // =========================================================================
  // AC2: PERMISSIONGATE MENYEMBUNYIKAN AKSI TANPA PERMISSION
  // =========================================================================

  it('GURU-PROG-01 (AC2): Pengguna dengan permission program.manage dapat melihat tombol aksi', () => {
    render(<TeacherProgramKhususPage />);

    // Tombol Tambah Materi terlihat
    expect(screen.getByRole('button', { name: /Tambah Materi/i })).toBeInTheDocument();

    // Tab Tugas
    const tugasTab = screen.getByRole('button', { name: /Tugas & Penilaian/i });
    fireEvent.click(tugasTab);
    expect(screen.getByRole('button', { name: /Tambah Tugas/i })).toBeInTheDocument();

    // Tab Target
    const targetTab = screen.getByRole('button', { name: /Target Capaian/i });
    fireEvent.click(targetTab);
    expect(screen.getByRole('button', { name: /Tambah Target Siswa/i })).toBeInTheDocument();
  });

  it('GURU-PROG-01 (AC2): PermissionGate menyembunyikan tombol aksi tambah dan nilai ketika pengguna tidak memiliki permission', () => {
    // Override user tanpa permission
    vi.spyOn(AuthProviderModule, 'useAuth').mockReturnValue({
      user: userWithoutPermission,
      isLoading: false,
      isAuthenticated: true,
      activeRole: 'teacher',
      login: vi.fn(),
      logout: vi.fn(),
      checkAuth: vi.fn(),
      setActiveRole: vi.fn(),
    });

    render(<TeacherProgramKhususPage />);

    // Tombol Tambah Materi disembunyikan
    expect(screen.queryByRole('button', { name: /Tambah Materi/i })).not.toBeInTheDocument();

    // Tab Tugas -> Tombol Tambah Tugas disembunyikan
    const tugasTab = screen.getByRole('button', { name: /Tugas & Penilaian/i });
    fireEvent.click(tugasTab);
    expect(screen.queryByRole('button', { name: /Tambah Tugas/i })).not.toBeInTheDocument();

    // Buka dialog telaah submission -> Tombol Beri Nilai disembunyikan
    const telaahBtn = screen.getByRole('button', { name: /Telaah/i });
    fireEvent.click(telaahBtn);
    expect(screen.queryByRole('button', { name: /Beri Nilai/i })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /Ubah Nilai/i })).not.toBeInTheDocument();

    // Tab Target -> Tombol Tambah Target Siswa disembunyikan
    const targetTab = screen.getByRole('button', { name: /Target Capaian/i });
    fireEvent.click(targetTab);
    expect(screen.queryByRole('button', { name: /Tambah Target Siswa/i })).not.toBeInTheDocument();
  });
});
