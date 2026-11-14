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

const mockPointsData = {
  data: [
    {
      id: 'pt-101',
      student_id: 'std-001',
      point_date: '2026-11-06',
      notes: 'Juara 1 OSN Matematika',
      student: { id: 'std-001', full_name: 'Budi Santoso', nis: '7A001' },
      point_type: { id: 'type-1', name: 'Prestasi Sains', kind: 'prestasi', points: 25 },
      recorder: { id: 'usr-guru', name: 'Bpk. Hendra' },
    },
  ],
};

describe('FIX-GURU-09: Pencatatan Poin Siswa & Persistensi Backend', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.spyOn(lmsApi, 'getStudentRoster').mockResolvedValue(mockRoster as any);
    vi.spyOn(lmsApi, 'getStudentPoints').mockResolvedValue(mockPointsData as any);
  });

  it('AC1: merender daftar catatan poin dari API dan menampilkan badge poin yang sesuai', async () => {
    renderWithClient(<DirektoriSiswaPage />);

    // Pindah ke tab Poin & Disiplin
    const poinTab = await screen.findByRole('button', { name: /Poin & Disiplin/i });
    fireEvent.click(poinTab);

    // Pastikan data poin dari API muncul di layar
    expect(await screen.findByText('Juara 1 OSN Matematika')).toBeDefined();
    expect(screen.getByText('Budi Santoso')).toBeDefined();
    expect(screen.getByText('+25 Poin')).toBeDefined();
    expect(screen.getByText('Prestasi Sains')).toBeDefined();
  });

  it('AC1: submit form memanggil lmsApi.recordStudentPoint dengan payload yang benar dan menampilkan toast sukses', async () => {
    const recordSpy = vi.spyOn(lmsApi, 'recordStudentPoint').mockResolvedValueOnce({
      data: { id: 'pt-102', student_id: 'std-001' },
    } as any);

    renderWithClient(<DirektoriSiswaPage />);

    // Buka tab Poin
    const poinTab = await screen.findByRole('button', { name: /Poin & Disiplin/i });
    fireEvent.click(poinTab);

    // Klik tombol catat poin
    const openModalBtn = await screen.findByRole('button', { name: /\+ Catat Poin Siswa/i });
    fireEvent.click(openModalBtn);

    // Isi deskripsi
    const textarea = screen.getByPlaceholderText(/Keterangan lengkap kejadian \/ prestasi yang diraih\.\.\./i);
    fireEvent.change(textarea, { target: { value: 'Membantu merapikan laboratorium komputer' } });

    // Submit
    const submitBtn = screen.getByRole('button', { name: /Simpan Poin Siswa/i });
    fireEvent.click(submitBtn);

    await waitFor(() => {
      expect(recordSpy).toHaveBeenCalledWith(
        expect.objectContaining({
          student_id: 'std-001',
          type: 'prestasi',
          notes: 'Membantu merapikan laboratorium komputer',
        })
      );
      expect(toast.success).toHaveBeenCalledWith(
        expect.stringContaining('berhasil dicatat untuk Budi Santoso')
      );
    });
  });

  it('AC2: validasi gagal dari server (422) menampilkan pesan error per-field', async () => {
    vi.spyOn(lmsApi, 'recordStudentPoint').mockRejectedValueOnce({
      response: {
        status: 422,
        data: {
          message: 'The given data was invalid.',
          errors: {
            notes: ['Catatan kejadian wajib diisi minimal 5 karakter.'],
          },
        },
      },
    });

    renderWithClient(<DirektoriSiswaPage />);

    const poinTab = await screen.findByRole('button', { name: /Poin & Disiplin/i });
    fireEvent.click(poinTab);

    const openModalBtn = await screen.findByRole('button', { name: /\+ Catat Poin Siswa/i });
    fireEvent.click(openModalBtn);

    const textarea = screen.getByPlaceholderText(/Keterangan lengkap kejadian \/ prestasi yang diraih\.\.\./i);
    fireEvent.change(textarea, { target: { value: 'abc' } });

    const submitBtn = screen.getByRole('button', { name: /Simpan Poin Siswa/i });
    fireEvent.click(submitBtn);

    await waitFor(() => {
      expect(screen.getByText('Catatan kejadian wajib diisi minimal 5 karakter.')).toBeDefined();
      expect(toast.error).toHaveBeenCalledWith('Catatan kejadian wajib diisi minimal 5 karakter.');
    });
  });

  it('AC3 & AC4: penolakan IDOR atau hak akses (403) menampilkan toast error yang informatif', async () => {
    vi.spyOn(lmsApi, 'recordStudentPoint').mockRejectedValueOnce({
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

    const poinTab = await screen.findByRole('button', { name: /Poin & Disiplin/i });
    fireEvent.click(poinTab);

    const openModalBtn = await screen.findByRole('button', { name: /\+ Catat Poin Siswa/i });
    fireEvent.click(openModalBtn);

    const textarea = screen.getByPlaceholderText(/Keterangan lengkap kejadian \/ prestasi yang diraih\.\.\./i);
    fireEvent.change(textarea, { target: { value: 'Pelanggaran atribut seragam' } });

    const submitBtn = screen.getByRole('button', { name: /Simpan Poin Siswa/i });
    fireEvent.click(submitBtn);

    await waitFor(() => {
      expect(toast.error).toHaveBeenCalledWith(
        expect.stringContaining('Anda tidak memiliki wewenang mengajar')
      );
    });
  });

  it('Empty state: menampilkan tampilan kosong informatif jika kelas belum memiliki catatan poin', async () => {
    vi.spyOn(lmsApi, 'getStudentPoints').mockResolvedValueOnce({
      data: [],
    } as any);

    renderWithClient(<DirektoriSiswaPage />);

    const poinTab = await screen.findByRole('button', { name: /Poin & Disiplin/i });
    fireEvent.click(poinTab);

    expect(await screen.findByText('Belum Ada Catatan Poin')).toBeDefined();
    expect(screen.getByText(/Belum ada rekam jejak poin prestasi atau pelanggaran untuk kelas ini/i)).toBeDefined();
  });
});
