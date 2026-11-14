import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import TeacherFacilitiesPage from '@/app/(portal)/portal/guru/fasilitas/page';
import { guruApi } from '@/features/portal/guru';

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

const mockTickets = [
  {
    id: 'tkt-001',
    ticket_number: 'TKT-20261107-PC01',
    title: '[Laboratorium Komputer] PC No. 12 Mati Total',
    description: 'PC di meja 12 tidak menyala saat saklar daya ditekan.',
    priority: 'tinggi' as const,
    status: 'pending' as const,
    created_at: '2026-11-07T08:00:00Z',
  },
  {
    id: 'tkt-002',
    ticket_number: 'TKT-20261107-AC02',
    title: '[Pendingin Ruangan] AC Lab Kurang Dingin',
    description: 'Suhu ruangan tetap 28C meski remot diatur 20C.',
    priority: 'sedang' as const,
    status: 'diproses' as const,
    created_at: '2026-11-06T10:00:00Z',
  },
];

describe('FIX-GURU-12: Lapor Kendala Sarpras & Tiket KBM', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.spyOn(guruApi, 'getFacilities').mockResolvedValue([]);
    vi.spyOn(guruApi, 'getFacilityBookings').mockResolvedValue([]);
    vi.spyOn(guruApi, 'getSarprasTickets').mockResolvedValue(mockTickets as any);
    vi.spyOn(guruApi, 'createSarprasTicket').mockResolvedValue({
      id: 'tkt-new',
      ticket_number: 'TKT-20261107-NEW1',
      title: '[Laboratorium Komputer] Mouse Meja 5 Rusak',
      description: 'Sensor optik mouse tidak merespons.',
      priority: 'sedang',
      status: 'pending',
    } as any);
  });

  it('AC1: berhasil membuat tiket sarpras dan menampilkan notifikasi sukses dengan ticket_number', async () => {
    renderWithClient(<TeacherFacilitiesPage />);

    // Beralih ke tab Tiket
    const tiketTabBtn = screen.getByRole('button', { name: /Lapor Kendala Sarpras \(Tiket\)/i });
    fireEvent.click(tiketTabBtn);

    await waitFor(() => {
      expect(screen.getByText('Tiket Pelaporan Gangguan Fasilitas KBM')).toBeInTheDocument();
    });

    fireEvent.change(screen.getByLabelText('Judul Laporan'), {
      target: { value: 'Mouse Meja 5 Rusak' },
    });
    fireEvent.change(screen.getByLabelText('Lokasi / Ruangan'), {
      target: { value: 'Lab Komputer 1' },
    });
    fireEvent.change(screen.getByLabelText('Deskripsi Kendala'), {
      target: { value: 'Sensor optik mouse tidak merespons di Meja No. 5.' },
    });

    const submitBtn = screen.getByRole('button', { name: /Kirim Tiket Pelaporan/i });
    expect(submitBtn).not.toBeDisabled();
    fireEvent.click(submitBtn);

    await waitFor(() => {
      expect(guruApi.createSarprasTicket).toHaveBeenCalledWith({
        title: 'Mouse Meja 5 Rusak',
        category: 'Laboratorium Komputer',
        priority: 'sedang',
        location: 'Lab Komputer 1',
        description: 'Sensor optik mouse tidak merespons di Meja No. 5.',
      });
    });

    await waitFor(() => {
      expect(
        screen.getByText(/Tiket sarpras berhasil diterbitkan dengan nomor: TKT-20261107-NEW1/i)
      ).toBeInTheDocument();
    });
  });

  it('AC2: menangani error timeout dengan pesan informatif jika server lambat', async () => {
    vi.spyOn(guruApi, 'createSarprasTicket').mockRejectedValue({
      code: 'REQUEST_TIMEOUT',
      message: 'Batas waktu permintaan habis. Server tidak merespons.',
    });

    renderWithClient(<TeacherFacilitiesPage />);

    const tiketTabBtn = screen.getByRole('button', { name: /Lapor Kendala Sarpras \(Tiket\)/i });
    fireEvent.click(tiketTabBtn);

    await waitFor(() => {
      expect(screen.getByText('Tiket Pelaporan Gangguan Fasilitas KBM')).toBeInTheDocument();
    });

    fireEvent.change(screen.getByLabelText('Deskripsi Kendala'), {
      target: { value: 'Proyektor tidak mau menyala sama sekali.' },
    });

    const submitBtn = screen.getByRole('button', { name: /Kirim Tiket Pelaporan/i });
    fireEvent.click(submitBtn);

    await waitFor(() => {
      expect(
        screen.getByText(
          /Batas waktu permintaan habis\. Server membutuhkan waktu lebih lama untuk memproses tiket/i
        )
      ).toBeInTheDocument();
    });
  });

  it('AC2: menonaktifkan tombol submit ketika deskripsi kendala masih kosong', async () => {
    renderWithClient(<TeacherFacilitiesPage />);

    const tiketTabBtn = screen.getByRole('button', { name: /Lapor Kendala Sarpras \(Tiket\)/i });
    fireEvent.click(tiketTabBtn);

    await waitFor(() => {
      expect(screen.getByText('Tiket Pelaporan Gangguan Fasilitas KBM')).toBeInTheDocument();
    });

    const submitBtn = screen.getByRole('button', { name: /Kirim Tiket Pelaporan/i });
    expect(submitBtn).toBeDisabled();
    expect(guruApi.createSarprasTicket).not.toHaveBeenCalled();
  });

  it('AC1: merender riwayat tiket kendala yang dilaporkan oleh guru dari API', async () => {
    renderWithClient(<TeacherFacilitiesPage />);

    const tiketTabBtn = screen.getByRole('button', { name: /Lapor Kendala Sarpras \(Tiket\)/i });
    fireEvent.click(tiketTabBtn);

    await waitFor(() => {
      expect(screen.getByText('TKT-20261107-PC01')).toBeInTheDocument();
      expect(screen.getByText('[Laboratorium Komputer] PC No. 12 Mati Total')).toBeInTheDocument();
      expect(screen.getByText('TKT-20261107-AC02')).toBeInTheDocument();
      expect(screen.getByText('[Pendingin Ruangan] AC Lab Kurang Dingin')).toBeInTheDocument();
      expect(screen.getByText(/2 tiket tercatat/i)).toBeInTheDocument();
    });
  });
});
