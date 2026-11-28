import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@testing-library/react';
import ActivateGuardianPage from '@/app/(auth)/auth/activate-guardian/page';
import PublicVerificationPage from '@/app/(public)/verifikasi/[token]/page';
import { apiClient } from '@/lib/api/api-client';

const pushMock = vi.fn();
let currentTokenParam = 'valid_act_token_123';
let currentRouteToken = 'abcdef1234567890abcdef1234567890';

vi.mock('next/navigation', () => ({
  useRouter: () => ({ push: pushMock, replace: vi.fn() }),
  useSearchParams: () => new URLSearchParams(currentTokenParam ? `token=${currentTokenParam}` : ''),
  useParams: () => ({ token: currentRouteToken }),
}));

vi.mock('@/lib/api/api-client', () => ({
  apiClient: {
    get: vi.fn(),
    post: vi.fn(),
  },
}));

describe('P1-25: Aktivasi Akun Guardian & Verifikasi Publik QR', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    currentTokenParam = 'valid_act_token_123';
    currentRouteToken = 'abcdef1234567890abcdef1234567890';
  });

  it('memuat preview token aktivasi dan mengirimkan kata sandi baru dengan sukses', async () => {
    vi.mocked(apiClient.get).mockResolvedValueOnce({
      data: {
        data: {
          valid: true,
          account_type: 'guardian',
          username: 'ortu_ahmad_1234',
          masked_email: 'ort***@wali.sistlabum.sch.id',
        },
      },
    } as any);

    vi.mocked(apiClient.post).mockResolvedValueOnce({
      data: { data: { activated: true } },
    } as any);

    render(<ActivateGuardianPage />);

    await waitFor(() => {
      expect(screen.getByText('ortu_ahmad_1234')).toBeInTheDocument();
    });

    fireEvent.change(screen.getByLabelText('Buat Kata Sandi Baru'), {
      target: { value: 'SandiAman123!' },
    });
    fireEvent.change(screen.getByLabelText('Konfirmasi Kata Sandi'), {
      target: { value: 'SandiAman123!' },
    });
    fireEvent.click(screen.getByRole('button', { name: /Aktifkan Akun/i }));

    await waitFor(() => {
      expect(apiClient.post).toHaveBeenCalledWith('/auth/activate-guardian', {
        token: 'valid_act_token_123',
        password: 'SandiAman123!',
        password_confirmation: 'SandiAman123!',
      });
      expect(screen.getByText('Akun Berhasil Diaktifkan')).toBeInTheDocument();
    });
  });

  it('menampilkan detail bukti PPDB terverifikasi pada halaman /verifikasi/[token]', async () => {
    vi.mocked(apiClient.get).mockResolvedValueOnce({
      data: {
        data: {
          document_type: 'ppdb_receipt',
          is_valid: true,
          status: 'diterima',
          verification_token: currentRouteToken,
          document_title: 'Bukti Pendaftaran Resmi PPDB',
          school_name: 'SMP Laboratorium UM',
          registration_no: 'PPDB-2026-0001',
          applicant: { name: 'Calon Siswa Uji' },
          wave: 'Gelombang 1',
          track: 'Zonasi',
          message: 'Bukti pendaftaran PPDB ini ASLI.',
        },
      },
    } as any);

    render(<PublicVerificationPage />);

    await waitFor(() => {
      expect(screen.getByText('Bukti Pendaftaran Resmi PPDB')).toBeInTheDocument();
      expect(screen.getByText('PPDB-2026-0001')).toBeInTheDocument();
      expect(screen.getByText('Calon Siswa Uji')).toBeInTheDocument();
    });
  });

  it('menampilkan status gagal ketika token QR tidak valid', async () => {
    vi.mocked(apiClient.get).mockRejectedValueOnce({
      response: { data: { message: 'Token verifikasi tidak ditemukan.' } },
    });

    render(<PublicVerificationPage />);

    await waitFor(() => {
      expect(screen.getByText('Dokumen Tidak Terverifikasi')).toBeInTheDocument();
      expect(screen.getByRole('button', { name: /Coba Lagi/i })).toBeInTheDocument();
    });
  });
});
