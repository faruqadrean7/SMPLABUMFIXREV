import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import StudentPoinPage from '@/app/(portal)/portal/siswa/poin/page';
import * as StudentDataModule from '@/lib/use-student-data';

vi.mock('@/lib/use-student-data', () => ({
  useStudentPoints: vi.fn(),
  useStudentExtracurriculars: vi.fn(),
  useRegisterExtracurricular: vi.fn(),
}));

describe('FE-STUDENT-POINTS-01: Buku Poin & Ekstrakurikuler Siswa', () => {
  const mockRefetchPoints = vi.fn();
  const mockRefetchEkskul = vi.fn();
  const mockMutateAsync = vi.fn();

  beforeEach(() => {
    vi.clearAllMocks();

    vi.mocked(StudentDataModule.useStudentPoints).mockReturnValue({
      data: {
        totalPrestasi: 75,
        totalPelanggaran: 10,
        balancePoints: 65,
        statusDisiplin: 'AMAN',
        achievementsCount: 2,
        items: [
          {
            id: 'ach-1',
            student_id: 'std-1',
            kind: 'prestasi',
            title: 'Juara 1 Lomba Coding Siswa',
            category: 'Prestasi Sains & Teknologi',
            point_date: '08 Nov 2026',
            points: 75,
            note: 'Diverifikasi panitia dan sekolah.',
            recorder_name: 'Guru Pembina',
          },
          {
            id: 'pt-1',
            student_id: 'std-1',
            kind: 'pelanggaran',
            title: 'Terlambat Masuk Kelas',
            category: 'Disiplin',
            point_date: '05 Nov 2026',
            points: 10,
            note: 'Terlambat 15 menit tanpa surat izin.',
            recorder_name: 'Guru Piket',
          },
        ],
      },
      isLoading: false,
      isError: false,
      refetch: mockRefetchPoints,
    } as any);

    vi.mocked(StudentDataModule.useStudentExtracurriculars).mockReturnValue({
      data: {
        myExtracurriculars: [
          {
            id: 'eks-1',
            memberId: 'm-1',
            name: 'Coding & Robotika',
            coach: 'Budi Santoso',
            schedule: 'Rabu, 15:00 - 17:00 WIB',
            room: 'Lab Komputer',
            role: 'Anggota Inti',
            attendance_rate: '100%',
            badgeColor: 'bg-blue-50 text-blue-900 border-blue-200',
          },
        ],
        availableExtracurriculars: [
          {
            id: 'eks-2',
            name: 'PMR Madya',
            coach: 'Ratna Dewi',
            schedule: 'Sabtu, 08:00 - 10:00 WIB',
            quota: 'Sisa 5 Kuota',
            category: 'Pilihan',
            description: 'Pelatihan pertolongan pertama',
          },
        ],
      },
      isLoading: false,
      isError: false,
      refetch: mockRefetchEkskul,
    } as any);

    vi.mocked(StudentDataModule.useRegisterExtracurricular).mockReturnValue({
      mutateAsync: mockMutateAsync,
    } as any);
  });

  it('renders student points overview stats and items correctly', () => {
    render(<StudentPoinPage />);

    expect(screen.getByText('Buku Poin Karakter & Ekstrakurikuler')).toBeDefined();
    expect(screen.getAllByText('+75').length).toBeGreaterThanOrEqual(1);
    expect(screen.getAllByText('-10').length).toBeGreaterThanOrEqual(1);
    expect(screen.getByText('+65')).toBeDefined();
    expect(screen.getByText('AMAN')).toBeDefined();
    expect(screen.getByText('Juara 1 Lomba Coding Siswa')).toBeDefined();
    expect(screen.getByText('Terlambat Masuk Kelas')).toBeDefined();
  });

  it('filters items when clicking filter chips', () => {
    render(<StudentPoinPage />);

    // Click 'prestasi' filter
    const prestasiBtn = screen.getByRole('button', { name: 'prestasi' });
    fireEvent.click(prestasiBtn);

    expect(screen.getByText('Juara 1 Lomba Coding Siswa')).toBeDefined();
    expect(screen.queryByText('Terlambat Masuk Kelas')).toBeNull();

    // Click 'pelanggaran' filter
    const pelanggaranBtn = screen.getByRole('button', { name: 'pelanggaran' });
    fireEvent.click(pelanggaranBtn);

    expect(screen.queryByText('Juara 1 Lomba Coding Siswa')).toBeNull();
    expect(screen.getByText('Terlambat Masuk Kelas')).toBeDefined();
  });

  it('switches to extracurricular tab and allows registering for available extracurricular', async () => {
    mockMutateAsync.mockResolvedValueOnce({ success: true });
    render(<StudentPoinPage />);

    // Switch tab
    const ekskulTab = screen.getByRole('button', { name: /Ekstrakurikuler/i });
    fireEvent.click(ekskulTab);

    expect(screen.getByText('Ekstrakurikuler Aktif yang Diikuti')).toBeDefined();
    expect(screen.getByText('Coding & Robotika')).toBeDefined();
    expect(screen.getByText('PMR Madya')).toBeDefined();

    // Click Daftar button
    const daftarBtn = screen.getByRole('button', { name: 'Daftar' });
    fireEvent.click(daftarBtn);

    await waitFor(() => {
      expect(mockMutateAsync).toHaveBeenCalledWith('eks-2');
      expect(mockRefetchEkskul).toHaveBeenCalled();
      expect(screen.getByText('Berhasil mendaftar ke ekstrakurikuler PMR Madya!')).toBeDefined();
    });
  });
});
