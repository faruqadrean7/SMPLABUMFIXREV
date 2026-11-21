import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import JadwalMengajarPage from '@/app/(portal)/portal/guru/jadwal/page';
import * as teacherDataModule from '@/lib/use-teacher-data';

vi.mock('@/lib/use-teacher-data', () => ({
  useTeacherSchedules: vi.fn(),
}));

describe('FE-ANTIFAKE-01: Hentikan Jadwal Palsu di Portal Guru', () => {
  const mockRefetch = vi.fn();

  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('FE-ANTIFAKE-01 (AC2): menampilkan loading state saat data jadwal sedang dimuat', () => {
    vi.mocked(teacherDataModule.useTeacherSchedules).mockReturnValue({
      data: [],
      isLoading: true,
      isError: false,
      refetch: mockRefetch,
    } as any);

    render(<JadwalMengajarPage />);

    expect(screen.getByText('Memuat Jadwal Mengajar...')).toBeInTheDocument();
    expect(screen.getByText('Mengambil data jadwal KBM dari server akademik')).toBeInTheDocument();
    // KPI cards show placeholder while loading
    expect(screen.getAllByText('-').length).toBeGreaterThanOrEqual(4);
    expect(screen.getAllByText('Memuat data...').length).toBeGreaterThanOrEqual(3);
  });

  it('FE-ANTIFAKE-01 (AC2 & AC3): menampilkan error state dan tombol Coba Lagi saat gagal mengambil data', () => {
    vi.mocked(teacherDataModule.useTeacherSchedules).mockReturnValue({
      data: [],
      isLoading: false,
      isError: true,
      refetch: mockRefetch,
    } as any);

    render(<JadwalMengajarPage />);

    expect(screen.getByText('Gagal Memuat Jadwal Mengajar')).toBeInTheDocument();
    expect(screen.getByText(/Terjadi kendala saat mengambil data jadwal dari server akademik/i)).toBeInTheDocument();

    const retryBtn = screen.getByRole('button', { name: /Coba Lagi/i });
    expect(retryBtn).toBeInTheDocument();
    fireEvent.click(retryBtn);
    expect(mockRefetch).toHaveBeenCalledTimes(1);
  });

  it('FE-ANTIFAKE-01 (AC1 & AC3): menampilkan empty-state jujur dan TIDAK menampilkan jadwal contoh jika data kosong', () => {
    vi.mocked(teacherDataModule.useTeacherSchedules).mockReturnValue({
      data: [],
      isLoading: false,
      isError: false,
      refetch: mockRefetch,
    } as any);

    render(<JadwalMengajarPage />);

    // Empty state must be shown
    expect(screen.getByText('Belum Ada Jadwal Mengajar')).toBeInTheDocument();
    expect(screen.getByText(/Jadwal mengajar Anda untuk semester ini belum diterbitkan/i)).toBeInTheDocument();

    // MUST NOT contain any fake schedule slots
    expect(screen.queryByText('Pemrograman Web')).not.toBeInTheDocument();
    expect(screen.queryByText('Praktikum Web')).not.toBeInTheDocument();
    expect(screen.queryByText('Proyek P5 Rekayasa Teknologi')).not.toBeInTheDocument();
    expect(screen.queryByText('Lab Multimedia')).not.toBeInTheDocument();

    // KPI cards reflect 0 metrics honestly
    expect(screen.getAllByText('0')).toHaveLength(4);
    expect(screen.getByText('Belum ada rombel')).toBeInTheDocument();
    expect(screen.getByText('Belum ada ruang')).toBeInTheDocument();
  });

  it('FE-ANTIFAKE-01 (AC1 & AC4): menampilkan jadwal mengajar nyata dan menghitung metrik dinamis saat data tersedia', () => {
    const liveMock = [
      {
        id: 'real-1',
        dayOfWeek: 1,
        dayName: 'Senin',
        startTime: '07:30',
        endTime: '09:00',
        classId: 'cls-1',
        className: 'Kelas VII-A',
        subjectId: 'sbj-1',
        subjectName: 'Matematika Terapan',
        roomName: 'Ruang Teori 101',
      },
      {
        id: 'real-2',
        dayOfWeek: 2,
        dayName: 'Selasa',
        startTime: '09:15',
        endTime: '10:45',
        classId: 'cls-2',
        className: 'Kelas VIII-B',
        subjectId: 'sbj-2',
        subjectName: 'Fisika Eksperimen',
        roomName: 'Lab IPA',
      },
    ];

    vi.mocked(teacherDataModule.useTeacherSchedules).mockReturnValue({
      data: liveMock,
      isLoading: false,
      isError: false,
      refetch: mockRefetch,
    } as any);

    render(<JadwalMengajarPage />);

    // Renders real schedule items
    expect(screen.getByText('Matematika Terapan')).toBeInTheDocument();
    expect(screen.getByText('Kelas VII-A')).toBeInTheDocument();
    expect(screen.getByText('Ruang Teori 101')).toBeInTheDocument();

    expect(screen.getByText('Fisika Eksperimen')).toBeInTheDocument();
    expect(screen.getByText('Kelas VIII-B')).toBeInTheDocument();
    expect(screen.getByText('Lab IPA')).toBeInTheDocument();

    // Dynamic metrics
    // Total JP: 2 slots * 2 JP = 4 JP
    expect(screen.getByText('4')).toBeInTheDocument();
    // Unique classes: VII-A, VIII-B (2 rombel)
    expect(screen.getByText('Rombel Aktif')).toBeInTheDocument();
    expect(screen.getByText('Kelas VII-A, Kelas VIII-B')).toBeInTheDocument();
    // Unique rooms: Ruang Teori 101, Lab IPA (2 ruang)
    expect(screen.getByText('Ruang Terdaftar')).toBeInTheDocument();
    expect(screen.getByText('Ruang Teori 101 & Lab IPA')).toBeInTheDocument();
  });
});
