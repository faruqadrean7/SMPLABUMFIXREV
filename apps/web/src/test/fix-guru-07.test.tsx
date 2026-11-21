import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import PerizinanSiswaPage from '@/app/(portal)/portal/guru/perizinan/page';
import { lmsApi } from '@/lib/api-client';
import { toast } from 'sonner';

vi.mock('sonner', () => ({
  toast: {
    success: vi.fn(),
    error: vi.fn(),
    info: vi.fn(),
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

const mockLeaveRequests = [
  {
    id: 'lr-doc-1',
    student_id: 'std-001',
    student: {
      id: 'std-001',
      full_name: 'Budi Santoso',
      nis: '7A001',
      classes: [{ id: 'cls-7a', name: '7-A' }],
    },
    requester: {
      id: 'usr-parent-1',
      full_name: 'Bpk. Santoso (Ayah)',
    },
    leave_type: 'sakit',
    start_date: '2026-11-08',
    end_date: '2026-11-09',
    reason: 'Demam tinggi dan istirahat dokter.',
    attachment_file_id: 'file-surat-dokter-budi',
    attachment_file: {
      id: 'file-surat-dokter-budi',
      original_name: 'Surat_Keterangan_Dokter_Budi.pdf',
    },
    status: 'menunggu',
    created_at: '2026-11-08T07:00:00Z',
  },
  {
    id: 'lr-doc-2',
    student_id: 'std-002',
    student: {
      id: 'std-002',
      full_name: 'Dewi Lestari',
      nis: '7A002',
      classes: [{ id: 'cls-7a', name: '7-A' }],
    },
    requester: {
      id: 'usr-parent-2',
      full_name: 'Ibu Lestari',
    },
    leave_type: 'izin',
    start_date: '2026-11-10',
    end_date: '2026-11-10',
    reason: 'Acara keluarga mendadak.',
    attachment_file_id: null,
    attachment_file: null,
    status: 'menunggu',
    created_at: '2026-11-08T08:00:00Z',
  },
];

describe('FIX-GURU-07: Pembukaan Berkas Lampiran Perizinan Siswa', () => {
  beforeEach(() => {
    vi.restoreAllMocks();
    vi.spyOn(lmsApi, 'getHomeroomLeaveRequests').mockResolvedValue({
      data: mockLeaveRequests,
    } as any);
  });

  it('AC1: tombol "Lihat Berkas" menyelesaikan file_id ke URL resmi backend dan membukanya di jendela baru', async () => {
    const signedUrl = 'https://smp-labum.sch.id/api/v1/files/file-surat-dokter-budi/download?signature=abc123xyz';
    const getFileUrlSpy = vi.spyOn(lmsApi, 'getFileUrl').mockResolvedValueOnce(signedUrl);
    const windowOpenSpy = vi.spyOn(window, 'open').mockImplementation(() => null);

    renderWithClient(<PerizinanSiswaPage />);

    expect((await screen.findAllByText('Budi Santoso')).length).toBeGreaterThan(0);
    expect(screen.getByText('Surat_Keterangan_Dokter_Budi.pdf')).toBeInTheDocument();

    const viewBtn = screen.getByRole('button', { name: /Lihat Berkas/i });
    expect(viewBtn).toBeInTheDocument();
    fireEvent.click(viewBtn);

    await waitFor(() => {
      expect(getFileUrlSpy).toHaveBeenCalledWith('file-surat-dokter-budi');
      expect(windowOpenSpy).toHaveBeenCalledWith(signedUrl, '_blank', 'noopener,noreferrer');
    });
  });

  it('AC2: menampilkan pesan toast error saat terjadi 403 Forbidden (IDOR)', async () => {
    vi.spyOn(lmsApi, 'getFileUrl').mockRejectedValueOnce({
      response: {
        status: 403,
        data: {
          error: {
            message: 'Akses ditolak: Anda tidak memiliki wewenang untuk mengakses lampiran siswa di luar kelas wewenang Anda.',
          },
        },
      },
    });

    renderWithClient(<PerizinanSiswaPage />);

    expect((await screen.findAllByText('Budi Santoso')).length).toBeGreaterThan(0);

    const viewBtn = screen.getByRole('button', { name: /Lihat Berkas/i });
    fireEvent.click(viewBtn);

    await waitFor(() => {
      expect(toast.error).toHaveBeenCalledWith(
        expect.stringContaining('Akses ditolak: Anda tidak memiliki wewenang')
      );
    });
  });

  it('AC3: menampilkan pesan "Tidak ada lampiran" jika perizinan tidak memiliki berkas', async () => {
    renderWithClient(<PerizinanSiswaPage />);

    expect((await screen.findAllByText('Dewi Lestari')).length).toBeGreaterThan(0);

    // Klik item perizinan Dewi Lestari (tanpa lampiran)
    const dewiCards = screen.getAllByText('Dewi Lestari');
    fireEvent.click(dewiCards[0]);

    await waitFor(() => {
      expect(
        screen.getByText('Tidak ada lampiran berkas yang diunggah oleh orang tua.')
      ).toBeInTheDocument();
    });

    // Pastikan tombol "Lihat Berkas" tidak muncul untuk item tanpa lampiran
    expect(screen.queryByRole('button', { name: /Lihat Berkas/i })).not.toBeInTheDocument();
  });

  it('AC4: menangani error 404 ketika berkas fisik tidak ditemukan di penyimpanan server', async () => {
    vi.spyOn(lmsApi, 'getFileUrl').mockRejectedValueOnce({
      response: {
        status: 404,
        data: {
          error: {
            message: 'Berkas tidak ditemukan.',
          },
        },
      },
    });

    renderWithClient(<PerizinanSiswaPage />);

    expect((await screen.findAllByText('Budi Santoso')).length).toBeGreaterThan(0);

    const viewBtn = screen.getByRole('button', { name: /Lihat Berkas/i });
    fireEvent.click(viewBtn);

    await waitFor(() => {
      expect(toast.error).toHaveBeenCalledWith(
        expect.stringContaining('Berkas tidak ditemukan')
      );
    });
  });
});
