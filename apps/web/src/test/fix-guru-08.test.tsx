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
    id: 'lr-001',
    student_id: 'std-001',
    student: {
      id: 'std-001',
      full_name: 'Aditya Pratama',
      nis: '2026001',
      classes: [{ id: 'cls-7a', name: '7-A' }],
    },
    requester: {
      id: 'usr-parent-1',
      full_name: 'Budi Pratama (Ayah)',
    },
    leave_type: 'sakit',
    start_date: '2026-11-05',
    end_date: '2026-11-06',
    reason: 'Demam tinggi dan flu, istirahat dokter 2 hari.',
    attachment_file_id: 'file-surat-dokter-01',
    attachment_file: {
      id: 'file-surat-dokter-01',
      name: 'surat-dokter-aditya.pdf',
    },
    status: 'menunggu',
    created_at: '2026-11-05T07:30:00Z',
  },
  {
    id: 'lr-002',
    student_id: 'std-002',
    student: {
      id: 'std-002',
      full_name: 'Siti Nurhaliza',
      nis: '2026002',
      classes: [{ id: 'cls-7b', name: '7-B' }],
    },
    requester: {
      id: 'usr-parent-2',
      full_name: 'Ahmad Dahlan (Wali)',
    },
    leave_type: 'izin',
    start_date: '2026-11-07',
    end_date: '2026-11-07',
    reason: 'Menghadiri pernikahan keluarga di Surabaya.',
    attachment_file_id: null,
    attachment_file: null,
    status: 'disetujui',
    decision_notes: 'Disetujui untuk izin 1 hari.',
    created_at: '2026-11-04T08:00:00Z',
  },
];

describe('FIX-GURU-08 & FIX-GURU-07: Verifikasi dan Keputusan Perizinan Siswa Portal Guru', () => {
  beforeEach(() => {
    vi.restoreAllMocks();
  });

  it('FIX-GURU-08 (AC1): merender daftar permohonan izin dari backend dan menampilkan rincian aktif', async () => {
    vi.spyOn(lmsApi, 'getHomeroomLeaveRequests').mockResolvedValueOnce({
      data: mockLeaveRequests,
    } as any);

    renderWithClient(<PerizinanSiswaPage />);

    // Memastikan judul halaman dan nama siswa
    expect(await screen.findByText('Verifikasi Surat Izin & Sakit Siswa')).toBeInTheDocument();
    expect((await screen.findAllByText('Aditya Pratama')).length).toBeGreaterThan(0);
    expect(screen.getByText('Siti Nurhaliza')).toBeInTheDocument();

    // Rincian panel kanan aktif (default memilih item pertama yang menunggu)
    expect(screen.getByText(/Demam tinggi dan flu, istirahat dokter 2 hari/i)).toBeInTheDocument();
    expect(screen.getByText('surat-dokter-aditya.pdf')).toBeInTheDocument();

    // Pastikan banner prototipe/demo tidak ada di DOM
    expect(screen.queryByText(/Aplikasi Prototipe \(apps\/portal\)/i)).not.toBeInTheDocument();
    expect(screen.queryByText(/Mode Demo/i)).not.toBeInTheDocument();
  });

  it('FIX-GURU-08 (AC1): menyetujui permohonan izin memanggil decideHomeroomLeaveRequest dengan decision "approved"', async () => {
    vi.spyOn(lmsApi, 'getHomeroomLeaveRequests').mockResolvedValue({
      data: mockLeaveRequests,
    } as any);

    const decideSpy = vi.spyOn(lmsApi, 'decideHomeroomLeaveRequest').mockResolvedValueOnce({
      data: { success: true },
    } as any);

    renderWithClient(<PerizinanSiswaPage />);

    expect((await screen.findAllByText('Aditya Pratama')).length).toBeGreaterThan(0);

    // Klik tombol "Setujui & Sinkron ke KBM"
    const approveBtn = screen.getByRole('button', { name: /Setujui & Sinkron ke KBM/i });
    fireEvent.click(approveBtn);

    await waitFor(() => {
      expect(decideSpy).toHaveBeenCalledWith('lr-001', { decision: 'approved' });
      expect(toast.success).toHaveBeenCalledWith(
        expect.stringContaining('Permohonan izin disetujui')
      );
    });
  });

  it('FIX-GURU-08 (AC1): menolak permohonan izin memanggil decideHomeroomLeaveRequest dengan decision "rejected"', async () => {
    vi.spyOn(lmsApi, 'getHomeroomLeaveRequests').mockResolvedValue({
      data: mockLeaveRequests,
    } as any);

    const decideSpy = vi.spyOn(lmsApi, 'decideHomeroomLeaveRequest').mockResolvedValueOnce({
      data: { success: true },
    } as any);

    renderWithClient(<PerizinanSiswaPage />);

    expect((await screen.findAllByText('Aditya Pratama')).length).toBeGreaterThan(0);

    // Klik tombol "Tolak"
    const rejectBtn = screen.getByRole('button', { name: /^Tolak$/i });
    fireEvent.click(rejectBtn);

    await waitFor(() => {
      expect(decideSpy).toHaveBeenCalledWith('lr-001', { decision: 'rejected' });
      expect(toast.success).toHaveBeenCalledWith(
        expect.stringContaining('Permohonan izin ditolak')
      );
    });
  });

  it('FIX-GURU-07 (AC2): tombol "Lihat Berkas" menyelesaikan file_id ke URL resmi dan membukanya di jendela baru', async () => {
    vi.spyOn(lmsApi, 'getHomeroomLeaveRequests').mockResolvedValueOnce({
      data: mockLeaveRequests,
    } as any);

    const getFileUrlSpy = vi.spyOn(lmsApi, 'getFileUrl').mockResolvedValueOnce(
      'https://storage.smplab-um.sch.id/secure/surat-dokter-aditya.pdf?token=xyz'
    );

    const windowOpenSpy = vi.spyOn(window, 'open').mockImplementation(() => null);

    renderWithClient(<PerizinanSiswaPage />);

    expect((await screen.findAllByText('Aditya Pratama')).length).toBeGreaterThan(0);

    const viewFileBtn = screen.getByRole('button', { name: /Lihat Berkas/i });
    fireEvent.click(viewFileBtn);

    await waitFor(() => {
      expect(getFileUrlSpy).toHaveBeenCalledWith('file-surat-dokter-01');
      expect(windowOpenSpy).toHaveBeenCalledWith(
        'https://storage.smplab-um.sch.id/secure/surat-dokter-aditya.pdf?token=xyz',
        '_blank',
        'noopener,noreferrer'
      );
    });
  });

  it('FIX-GURU-08: dropdown filter kelas terisi dinamis dari data permohonan', async () => {
    vi.spyOn(lmsApi, 'getHomeroomLeaveRequests').mockResolvedValueOnce({
      data: mockLeaveRequests,
    } as any);

    renderWithClient(<PerizinanSiswaPage />);

    expect((await screen.findAllByText('Aditya Pratama')).length).toBeGreaterThan(0);

    const classSelect = screen.getByLabelText(/Filter Kelas Perizinan/i);
    expect(classSelect).toBeInTheDocument();
    expect(screen.getByRole('option', { name: 'Semua Kelas' })).toBeInTheDocument();
    expect(screen.getByRole('option', { name: 'Kelas 7-A' })).toBeInTheDocument();
    expect(screen.getByRole('option', { name: 'Kelas 7-B' })).toBeInTheDocument();
  });

  it('FIX-GURU-08 (AC4): merender EmptyState saat tidak ada permohonan izin siswa', async () => {
    vi.spyOn(lmsApi, 'getHomeroomLeaveRequests').mockResolvedValueOnce({
      data: [],
    } as any);

    renderWithClient(<PerizinanSiswaPage />);

    expect(
      await screen.findByText('Tidak ada permohonan ditemukan')
    ).toBeInTheDocument();
  });

  it('FIX-GURU-08 (AC4): merender pesan kesalahan saat pemanggilan API gagal', async () => {
    vi.spyOn(lmsApi, 'getHomeroomLeaveRequests').mockRejectedValueOnce(
      new Error('Koneksi database bermasalah')
    );

    renderWithClient(<PerizinanSiswaPage />);

    expect(
      await screen.findByText('Gagal memuat data perizinan')
    ).toBeInTheDocument();
  });
});
