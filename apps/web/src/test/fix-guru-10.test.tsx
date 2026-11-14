import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import DirektoriSiswaPage from '@/app/(portal)/portal/guru/siswa/page';
import { lmsApi } from '@/lib/api-client';
import { toast } from 'sonner';

vi.mock('sonner', () => ({
  toast: {
    success: vi.fn(),
    error: vi.fn(),
    info: vi.fn(),
  },
}));

vi.mock('@/lib/use-teacher-data', () => ({
  useTeacherClasses: () => ({
    data: [
      { id: 'cls-7a', name: '7-A', studentCount: 30 },
      { id: 'cls-7b', name: '7-B', studentCount: 28 },
    ],
    isLoading: false,
  }),
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

const mockRoster = [
  { id: 'std-001', name: 'Budi Santoso', nis: '7A001', gender: 'L' },
  { id: 'std-002', name: 'Siti Aminah', nis: '7A002', gender: 'P' },
];

const mockReferralsData = {
  data: [
    {
      id: 'ref-201',
      student_id: 'std-001',
      reason: '[Prioritas: segera] Penurunan Kehadiran & Sering Terlambat — Siswa sering mengantuk di kelas',
      status: 'pending',
      created_at: '2026-11-06T08:00:00Z',
      student: { id: 'std-001', full_name: 'Budi Santoso', nis: '7A001' },
      referrer: { id: 'usr-guru', name: 'Bpk. Hendra' },
    },
  ],
};

describe('FIX-GURU-10: Pembuatan Rujukan BK & Persistensi Backend', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.spyOn(lmsApi, 'getStudentRoster').mockResolvedValue(mockRoster as any);
    vi.spyOn(lmsApi, 'getStudentPoints').mockResolvedValue({ data: [] } as any);
    vi.spyOn(lmsApi, 'getCounselingReferrals').mockResolvedValue(mockReferralsData as any);
  });

  it('AC1: merender daftar rujukan BK dari API dan menampilkan status serta tingkat urgensi', async () => {
    renderWithClient(<DirektoriSiswaPage />);

    // Pindah ke tab Rujukan BK
    const bkTab = await screen.findByRole('button', { name: /Rujukan BK/i });
    fireEvent.click(bkTab);

    // Pastikan data rujukan dari API tampil di layar
    expect(await screen.findByText(/Penurunan Kehadiran & Sering Terlambat/i)).toBeDefined();
    expect(screen.getByText('Budi Santoso')).toBeDefined();
    expect(screen.getByText('SEGERA')).toBeDefined();
    expect(screen.getByText('MENUNGGU')).toBeDefined();
  });

  it('AC1: submit form modal memanggil lmsApi.createCounselingReferral dan menampilkan toast sukses', async () => {
    const referralSpy = vi.spyOn(lmsApi, 'createCounselingReferral').mockResolvedValueOnce({
      data: { id: 'ref-202', student_id: 'std-001', status: 'pending' },
    } as any);

    renderWithClient(<DirektoriSiswaPage />);

    // Buka tab Rujukan BK
    const bkTab = await screen.findByRole('button', { name: /Rujukan BK/i });
    fireEvent.click(bkTab);

    // Klik tombol Buat Rujukan BK
    const openModalBtn = await screen.findByRole('button', { name: /\+ Buat Rujukan BK/i });
    fireEvent.click(openModalBtn);

    // Isi observasi / catatan guru
    const textarea = screen.getByPlaceholderText(/Ceritakan temuan observasi atau kendala siswa di kelas\.\.\./i);
    fireEvent.change(textarea, { target: { value: 'Siswa sering terlihat menarik diri dari pergaulan kelas' } });

    // Submit
    const submitBtn = screen.getByRole('button', { name: /Kirim Rujukan ke Guru BK/i });
    fireEvent.click(submitBtn);

    await waitFor(() => {
      expect(referralSpy).toHaveBeenCalledWith(
        expect.objectContaining({
          student_id: 'std-001',
          reason: expect.stringContaining('Siswa sering terlihat menarik diri dari pergaulan kelas'),
          priority: 'segera',
        })
      );
      expect(toast.success).toHaveBeenCalledWith(
        expect.stringContaining('berhasil diteruskan ke Guru BK')
      );
    });
  });

  it('AC2: validasi gagal dari server (422) menampilkan pesan error per-field', async () => {
    vi.spyOn(lmsApi, 'createCounselingReferral').mockRejectedValueOnce({
      response: {
        status: 422,
        data: {
          message: 'The given data was invalid.',
          errors: {
            reason: ['Alasan rujukan wajib diisi minimal 5 karakter.'],
          },
        },
      },
    });

    renderWithClient(<DirektoriSiswaPage />);

    const bkTab = await screen.findByRole('button', { name: /Rujukan BK/i });
    fireEvent.click(bkTab);

    const openModalBtn = await screen.findByRole('button', { name: /\+ Buat Rujukan BK/i });
    fireEvent.click(openModalBtn);

    const submitBtn = screen.getByRole('button', { name: /Kirim Rujukan ke Guru BK/i });
    fireEvent.click(submitBtn);

    await waitFor(() => {
      expect(screen.getByText('Alasan rujukan wajib diisi minimal 5 karakter.')).toBeDefined();
      expect(toast.error).toHaveBeenCalledWith('Alasan rujukan wajib diisi minimal 5 karakter.');
    });
  });

  it('AC2: rujukan pending duplikat (BUSINESS_RULE_VIOLATION 422) menampilkan pesan toast error informatif', async () => {
    vi.spyOn(lmsApi, 'createCounselingReferral').mockRejectedValueOnce({
      response: {
        status: 422,
        data: {
          error: {
            code: 'BUSINESS_RULE_VIOLATION',
            message: 'Rujukan serupa untuk siswa ini masih berstatus pending dan sedang menunggu tindak lanjut BK.',
          },
        },
      },
    });

    renderWithClient(<DirektoriSiswaPage />);

    const bkTab = await screen.findByRole('button', { name: /Rujukan BK/i });
    fireEvent.click(bkTab);

    const openModalBtn = await screen.findByRole('button', { name: /\+ Buat Rujukan BK/i });
    fireEvent.click(openModalBtn);

    const textarea = screen.getByPlaceholderText(/Ceritakan temuan observasi atau kendala siswa di kelas\.\.\./i);
    fireEvent.change(textarea, { target: { value: 'Kendala kedisiplinan dan absensi' } });

    const submitBtn = screen.getByRole('button', { name: /Kirim Rujukan ke Guru BK/i });
    fireEvent.click(submitBtn);

    await waitFor(() => {
      expect(toast.error).toHaveBeenCalledWith(
        expect.stringContaining('Rujukan serupa untuk siswa ini masih berstatus pending')
      );
    });
  });

  it('AC2 & AC4: penolakan IDOR atau hak akses (403) menampilkan toast error yang informatif', async () => {
    vi.spyOn(lmsApi, 'createCounselingReferral').mockRejectedValueOnce({
      response: {
        status: 403,
        data: {
          error: {
            code: 'FORBIDDEN',
            message: 'Akses ditolak: Anda tidak memiliki wewenang mengajar atau menjadi wali kelas untuk siswa ini.',
          },
        },
      },
    });

    renderWithClient(<DirektoriSiswaPage />);

    const bkTab = await screen.findByRole('button', { name: /Rujukan BK/i });
    fireEvent.click(bkTab);

    const openModalBtn = await screen.findByRole('button', { name: /\+ Buat Rujukan BK/i });
    fireEvent.click(openModalBtn);

    const textarea = screen.getByPlaceholderText(/Ceritakan temuan observasi atau kendala siswa di kelas\.\.\./i);
    fireEvent.change(textarea, { target: { value: 'Rujukan siswa luar binaan' } });

    const submitBtn = screen.getByRole('button', { name: /Kirim Rujukan ke Guru BK/i });
    fireEvent.click(submitBtn);

    await waitFor(() => {
      expect(toast.error).toHaveBeenCalledWith(
        expect.stringContaining('Anda tidak memiliki wewenang mengajar atau menjadi wali kelas')
      );
    });
  });

  it('Empty state: menampilkan empty state informatif saat kelas belum memiliki rujukan BK', async () => {
    vi.spyOn(lmsApi, 'getCounselingReferrals').mockResolvedValueOnce({
      data: [],
    } as any);

    renderWithClient(<DirektoriSiswaPage />);

    const bkTab = await screen.findByRole('button', { name: /Rujukan BK/i });
    fireEvent.click(bkTab);

    expect(await screen.findByText('Belum Ada Rujukan BK')).toBeDefined();
    expect(screen.getByText(/Belum ada permohonan pendampingan konseling untuk siswa di kelas ini/i)).toBeDefined();
  });
});
