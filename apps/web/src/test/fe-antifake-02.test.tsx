import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import StudentCambridgePage from '@/app/(portal)/portal/siswa/cambridge/page';
import StudentHafalanPage from '@/app/(portal)/portal/siswa/hafalan/page';
import StudentKonselingPage from '@/app/(portal)/portal/siswa/konseling/page';
import StudentP5Page from '@/app/(portal)/portal/siswa/p5/page';
import StudentPpiPage from '@/app/(portal)/portal/siswa/ppi/page';
import * as studentDataModule from '@/lib/use-student-data';

vi.mock('@/lib/use-student-data', () => ({
  useStudentDashboard: vi.fn(),
  useCounselingRequests: vi.fn(),
  useCreateCounselingRequest: vi.fn(() => ({
    mutateAsync: vi.fn(),
  })),
}));

describe('FE-ANTIFAKE-02: Hentikan Nilai Fallback Fiktif di Portal Siswa', () => {
  const mockRefetch = vi.fn();

  beforeEach(() => {
    vi.clearAllMocks();
  });

  describe('1. Cambridge Page (AC1 & AC4)', () => {
    it('FE-ANTIFAKE-02: menampilkan "-" saat cambridge_candidate_id kosong dan BUKAN ID-MAL-2026-042', () => {
      vi.mocked(studentDataModule.useStudentDashboard).mockReturnValue({
        data: {
          programProfile: {},
        },
        isLoading: false,
        isError: false,
        refetch: mockRefetch,
      } as any);

      render(<StudentCambridgePage />);

      expect(screen.getByText(/Candidate: -/i)).toBeInTheDocument();
      expect(screen.queryByText(/ID-MAL-2026-042/i)).not.toBeInTheDocument();
      expect(screen.getByText('Belum Ada Data Kesiapan Checkpoint')).toBeInTheDocument();
    });

    it('FE-ANTIFAKE-02: menampilkan error state dan tombol coba lagi saat isError: true pada cambridge', () => {
      vi.mocked(studentDataModule.useStudentDashboard).mockReturnValue({
        data: null,
        isLoading: false,
        isError: true,
        refetch: mockRefetch,
      } as any);

      render(<StudentCambridgePage />);

      expect(screen.getByText('Gagal Memuat Portofolio Cambridge')).toBeInTheDocument();
      const retryBtn = screen.getByRole('button', { name: /Coba Lagi/i });
      fireEvent.click(retryBtn);
      expect(mockRefetch).toHaveBeenCalledTimes(1);
    });
  });

  describe('2. Hafalan Page (AC2 & AC4)', () => {
    it('FE-ANTIFAKE-02: menampilkan "-" saat evaluator kosong dan BUKAN Ustadz Abdullah, S.Pd.I', () => {
      vi.mocked(studentDataModule.useStudentDashboard).mockReturnValue({
        data: {
          programProfile: {},
        },
        isLoading: false,
        isError: false,
        refetch: mockRefetch,
      } as any);

      render(<StudentHafalanPage />);

      expect(screen.queryByText(/Ustadz Abdullah/i)).not.toBeInTheDocument();
      expect(screen.queryByText(/Al-Mulk: 1-30/i)).not.toBeInTheDocument();
      expect(screen.queryByText(/Mumtaz/i)).not.toBeInTheDocument();

      // Evaluator & Surah render '-'
      expect(screen.getAllByText('-').length).toBeGreaterThanOrEqual(2);
    });

    it('FE-ANTIFAKE-02: menampilkan error state dan tombol coba lagi saat isError: true pada hafalan', () => {
      vi.mocked(studentDataModule.useStudentDashboard).mockReturnValue({
        data: null,
        isLoading: false,
        isError: true,
        refetch: mockRefetch,
      } as any);

      render(<StudentHafalanPage />);

      expect(screen.getByText('Gagal Memuat Data Hafalan')).toBeInTheDocument();
      const retryBtn = screen.getByRole('button', { name: /Coba Lagi/i });
      fireEvent.click(retryBtn);
      expect(mockRefetch).toHaveBeenCalledTimes(1);
    });
  });

  describe('3. Konseling Page (AC3)', () => {
    it('FE-ANTIFAKE-02: form konseling tidak prefilled nama/jam karangan dan bersih dari "Ibu Ratna Dewi"', () => {
      vi.mocked(studentDataModule.useCounselingRequests).mockReturnValue({
        data: [],
        isLoading: false,
        isError: false,
        refetch: mockRefetch,
      } as any);

      render(<StudentKonselingPage />);

      // Must not contain hardcoded "Ibu Ratna Dewi" anywhere on the page
      expect(screen.queryByText(/Ibu Ratna Dewi/i)).not.toBeInTheDocument();

      // Select inputs start with empty selection
      const selects = screen.getAllByRole('combobox');
      expect(selects[0]).toHaveValue('');
      expect(selects[1]).toHaveValue('');
    });
  });

  describe('4. P5 & PPI Pages Error State & Anti-Fabrication (AC4)', () => {
    it('FE-ANTIFAKE-02: menampilkan error state dan tombol coba lagi saat isError: true pada P5', () => {
      vi.mocked(studentDataModule.useStudentDashboard).mockReturnValue({
        data: null,
        isLoading: false,
        isError: true,
        refetch: mockRefetch,
      } as any);

      render(<StudentP5Page />);

      expect(screen.getByText('Gagal Memuat Proyek P5')).toBeInTheDocument();
      const retryBtn = screen.getByRole('button', { name: /Coba Lagi/i });
      fireEvent.click(retryBtn);
      expect(mockRefetch).toHaveBeenCalledTimes(1);
    });

    it('FE-ANTIFAKE-02: menampilkan empty state jujur pada P5 saat data proyek kosong', () => {
      vi.mocked(studentDataModule.useStudentDashboard).mockReturnValue({
        data: {
          programProfile: {},
        },
        isLoading: false,
        isError: false,
        refetch: mockRefetch,
      } as any);

      render(<StudentP5Page />);

      expect(screen.getByText('Belum Ada Proyek P5 Aktif')).toBeInTheDocument();
      expect(screen.getByText('Belum ada ekstrakurikuler terdaftar.')).toBeInTheDocument();
    });

    it('FE-ANTIFAKE-02: menampilkan error state dan tombol coba lagi saat isError: true pada PPI', () => {
      vi.mocked(studentDataModule.useStudentDashboard).mockReturnValue({
        data: null,
        isLoading: false,
        isError: true,
        refetch: mockRefetch,
      } as any);

      render(<StudentPpiPage />);

      expect(screen.getByText('Gagal Memuat Target PPI')).toBeInTheDocument();
      const retryBtn = screen.getByRole('button', { name: /Coba Lagi/i });
      fireEvent.click(retryBtn);
      expect(mockRefetch).toHaveBeenCalledTimes(1);
    });

    it('FE-ANTIFAKE-02: menampilkan empty state jujur pada PPI saat milestones kosong dan GPK tidak fiktif', () => {
      vi.mocked(studentDataModule.useStudentDashboard).mockReturnValue({
        data: {
          programProfile: {},
        },
        isLoading: false,
        isError: false,
        refetch: mockRefetch,
      } as any);

      render(<StudentPpiPage />);

      expect(screen.queryByText(/Ibu Ratna Dewi/i)).not.toBeInTheDocument();
      expect(
        screen.getByText('Belum ada target milestone PPI aktif yang ditetapkan untuk periode ini.')
      ).toBeInTheDocument();
    });
  });
});
