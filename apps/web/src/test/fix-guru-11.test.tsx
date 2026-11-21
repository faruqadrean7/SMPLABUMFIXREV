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

const mockFacilities = [
  { id: 'fac-uuid-lab1', name: 'Laboratorium Komputer 1' },
  { id: 'fac-uuid-aula', name: 'Aula Graha Utama' },
];

const mockBookings = [
  {
    id: 'bkg-101',
    facility_id: 'fac-uuid-lab1',
    facility_name: 'Laboratorium Komputer 1',
    facility: 'Laboratorium Komputer 1',
    start_at: '2026-11-10T08:00:00Z',
    end_at: '2026-11-10T10:00:00Z',
    date: '2026-11-10',
    time_slot: '08:00 - 10:00',
    purpose: 'Simulasi CBT Ujian Sekolah',
    attendees_count: 35,
    participant_count: 35,
    status: 'menunggu' as const,
    created_at: '2026-11-06T09:00:00Z',
  },
  {
    id: 'bkg-102',
    facility_id: 'fac-uuid-aula',
    facility_name: 'Aula Graha Utama',
    facility: 'Aula Graha Utama',
    start_at: '2026-11-12T13:00:00Z',
    end_at: '2026-11-12T15:00:00Z',
    date: '2026-11-12',
    time_slot: '13:00 - 15:00',
    purpose: 'Briefing Olimpiade Sains',
    attendees_count: 50,
    participant_count: 50,
    status: 'disetujui' as const,
    created_at: '2026-11-06T10:00:00Z',
  },
];

describe('FIX-GURU-11: Reservasi Fasilitas Sekolah', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.spyOn(guruApi, 'getFacilities').mockResolvedValue(mockFacilities as any);
    vi.spyOn(guruApi, 'getFacilityBookings').mockResolvedValue(mockBookings as any);
    vi.spyOn(guruApi, 'createFacilityBooking').mockResolvedValue({
      id: 'bkg-new',
      facility_id: 'fac-uuid-lab1',
      status: 'menunggu',
    } as any);
  });

  it('AC1: merender daftar fasilitas dari API dan otomatis memilih fasilitas pertama yang tersedia', async () => {
    renderWithClient(<TeacherFacilitiesPage />);

    await waitFor(() => {
      expect(screen.getByRole('option', { name: 'Laboratorium Komputer 1' })).toBeInTheDocument();
      expect(screen.getByRole('option', { name: 'Aula Graha Utama' })).toBeInTheDocument();
    });

    const select = screen.getByLabelText('Ruangan / Fasilitas') as HTMLSelectElement;
    await waitFor(() => {
      expect(select.value).toBe('fac-uuid-lab1');
    });
  });

  it('AC1: berhasil mengajukan reservasi dengan fasilitas nyata tanpa UUID dummy (201)', async () => {
    renderWithClient(<TeacherFacilitiesPage />);

    await waitFor(() => {
      expect(screen.getByLabelText('Ruangan / Fasilitas')).toBeInTheDocument();
    });

    const select = screen.getByLabelText('Ruangan / Fasilitas') as HTMLSelectElement;
    await waitFor(() => {
      expect(select.value).toBe('fac-uuid-lab1');
    });

    fireEvent.change(screen.getByLabelText('Tanggal Kegiatan'), {
      target: { value: '2026-11-15' },
    });
    fireEvent.change(screen.getByLabelText('Jam Mulai'), {
      target: { value: '08:00' },
    });
    fireEvent.change(screen.getByLabelText('Jam Selesai'), {
      target: { value: '10:00' },
    });
    fireEvent.change(screen.getByLabelText('Tujuan Peminjaman'), {
      target: { value: 'Praktikum Algoritma dan Pemrograman 7-A' },
    });

    const submitBtn = screen.getByRole('button', { name: /Ajukan Peminjaman/i });
    expect(submitBtn).not.toBeDisabled();
    fireEvent.click(submitBtn);

    await waitFor(() => {
      expect(guruApi.createFacilityBooking).toHaveBeenCalledWith({
        facility_id: 'fac-uuid-lab1',
        start_at: '2026-11-15T08:00:00',
        end_at: '2026-11-15T10:00:00',
        purpose: 'Praktikum Algoritma dan Pemrograman 7-A',
        attendees_count: 30,
      });
    });

    await waitFor(() => {
      expect(
        screen.getByText(/Pengajuan peminjaman fasilitas berhasil dikirimkan/i)
      ).toBeInTheDocument();
    });
  });

  it('AC2: menonaktifkan submit dan menampilkan peringatan jika tidak ada fasilitas yang terpilih', async () => {
    vi.spyOn(guruApi, 'getFacilities').mockResolvedValue([]);

    renderWithClient(<TeacherFacilitiesPage />);

    await waitFor(() => {
      expect(
        screen.getByText('* Silakan pilih fasilitas sekolah yang akan dipinjam.')
      ).toBeInTheDocument();
    });

    const submitBtn = screen.getByRole('button', { name: /Ajukan Peminjaman/i });
    expect(submitBtn).toBeDisabled();

    // Pastikan tidak ada panggilan mutasi API
    expect(guruApi.createFacilityBooking).not.toHaveBeenCalled();
  });

  it('AC3: menampilkan pesan informatif saat terjadi konflik jadwal fasilitas (409 Conflict)', async () => {
    vi.spyOn(guruApi, 'createFacilityBooking').mockRejectedValue({
      response: {
        status: 409,
        data: {
          error: {
            code: 'CONFLICT',
            message: 'Fasilitas sudah dipesan pada rentang waktu tersebut.',
          },
        },
      },
    });

    renderWithClient(<TeacherFacilitiesPage />);

    await waitFor(() => {
      expect(screen.getByLabelText('Ruangan / Fasilitas')).toBeInTheDocument();
    });

    fireEvent.change(screen.getByLabelText('Tanggal Kegiatan'), {
      target: { value: '2026-11-15' },
    });
    fireEvent.change(screen.getByLabelText('Tujuan Peminjaman'), {
      target: { value: 'Sesi Praktikum Tambahan' },
    });

    const submitBtn = screen.getByRole('button', { name: /Ajukan Peminjaman/i });
    fireEvent.click(submitBtn);

    await waitFor(() => {
      expect(
        screen.getByText(
          'Konflik Jadwal: Fasilitas sudah dipesan pada rentang waktu tersebut oleh guru/kegiatan lain.'
        )
      ).toBeInTheDocument();
    });
  });

  it('AC4: memvalidasi bahwa waktu selesai harus lebih lambat dari waktu mulai', async () => {
    renderWithClient(<TeacherFacilitiesPage />);

    await waitFor(() => {
      expect(screen.getByLabelText('Ruangan / Fasilitas')).toBeInTheDocument();
    });

    fireEvent.change(screen.getByLabelText('Tanggal Kegiatan'), {
      target: { value: '2026-11-15' },
    });
    fireEvent.change(screen.getByLabelText('Jam Mulai'), {
      target: { value: '11:00' },
    });
    fireEvent.change(screen.getByLabelText('Jam Selesai'), {
      target: { value: '09:00' },
    });
    fireEvent.change(screen.getByLabelText('Tujuan Peminjaman'), {
      target: { value: 'Validasi Rentang Waktu Terbalik' },
    });

    const submitBtn = screen.getByRole('button', { name: /Ajukan Peminjaman/i });
    fireEvent.click(submitBtn);

    await waitFor(() => {
      expect(
        screen.getByText('Waktu selesai harus lebih lambat dari waktu mulai.')
      ).toBeInTheDocument();
    });

    expect(guruApi.createFacilityBooking).not.toHaveBeenCalled();
  });

  it('AC1: merender riwayat pengajuan peminjaman fasilitas guru dari API dengan benar', async () => {
    renderWithClient(<TeacherFacilitiesPage />);

    await waitFor(() => {
      expect(screen.getByText(/"Simulasi CBT Ujian Sekolah"/i)).toBeInTheDocument();
      expect(screen.getByText(/"Briefing Olimpiade Sains"/i)).toBeInTheDocument();
      expect(screen.getByText(/2 pengajuan tercatat/i)).toBeInTheDocument();
    });
  });
});
