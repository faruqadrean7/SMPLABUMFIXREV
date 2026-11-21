import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { ComplaintForm } from '@/features/public-relations/components/complaint-form';
import { publicRelationsApi } from '@/features/public-relations/public-relations-api';
import * as AuthProviderModule from '@/providers/auth-provider';

// Mock next/navigation
vi.mock('next/navigation', () => ({
  useRouter: () => ({
    push: vi.fn(),
    replace: vi.fn(),
  }),
  useSearchParams: () => ({
    get: vi.fn().mockReturnValue(null),
  }),
}));

describe('FE-REPORT-02: Anti-Spam Protection', () => {
  beforeEach(() => {
    vi.clearAllMocks();

    vi.spyOn(AuthProviderModule, 'useAuth').mockReturnValue({
      user: null,
      isLoading: false,
      isAuthenticated: false,
      activeRole: null,
      login: vi.fn(),
      logout: vi.fn(),
      checkAuth: vi.fn(),
      setActiveRole: vi.fn(),
    });
  });

  // =========================================================================
  // 1. ANTI-SPAM: HONEYPOT DETECTION
  // =========================================================================
  it('FE-REPORT-02 (Anti-Spam): Bot mengisi field honeypot dicegat tanpa memanggil API backend', async () => {
    const mockSubmit = vi.spyOn(publicRelationsApi, 'submitComplaintPublic');

    render(<ComplaintForm minSubmitDelayMs={0} />);

    const textarea = screen.getByPlaceholderText(/Ceritakan kejadian secara jelas/i);
    fireEvent.change(textarea, {
      target: { value: 'Laporan perundungan valid dari pengirim bot spammer.' },
    });

    // Bot scraper mengisi input honeypot tersembunyi
    const honeypotInput = screen.getByLabelText(/Jangan isi kolom ini/i);
    fireEvent.change(honeypotInput, {
      target: { value: 'https://spam-bot-promoter.com' },
    });

    const submitBtn = screen.getByRole('button', { name: /Kirim Laporan Pengaduan/i });
    fireEvent.click(submitBtn);

    // Backend TIDAK PERNAH dipanggil
    await waitFor(() => {
      expect(mockSubmit).not.toHaveBeenCalled();
    });

    // Simulasikan tiket dummy agar bot tidak mencoba variasi lain
    expect(await screen.findByText('ASP-20261103-SPAM')).toBeInTheDocument();
  });

  // =========================================================================
  // 2. ANTI-SPAM: SUBMISSION TIMING CHECK
  // =========================================================================
  it('FE-REPORT-02 (Anti-Spam): Submit instan < 1.5 detik dicegat dengan pesan proteksi bot', async () => {
    const mockSubmit = vi.spyOn(publicRelationsApi, 'submitComplaintPublic');

    // Paksa threshold 5000 ms agar submission instan langsung terkena flag
    render(<ComplaintForm minSubmitDelayMs={5000} />);

    const textarea = screen.getByPlaceholderText(/Ceritakan kejadian secara jelas/i);
    fireEvent.change(textarea, {
      target: { value: 'Laporan perundungan yang dikirim otomatis oleh script instan.' },
    });

    const submitBtn = screen.getByRole('button', { name: /Kirim Laporan Pengaduan/i });
    fireEvent.click(submitBtn);

    expect(
      await screen.findByText(/Pengiriman terlalu cepat terdeteksi oleh sistem anti-bot/i)
    ).toBeInTheDocument();
    expect(mockSubmit).not.toHaveBeenCalled();
  });

  // =========================================================================
  // 3. ANTI-SPAM: REPEATED CHARACTERS VALIDATION
  // =========================================================================
  it('FE-REPORT-02 (Anti-Spam): Isi laporan karakter berulang ditolak oleh validasi sanitasi', async () => {
    const mockSubmit = vi.spyOn(publicRelationsApi, 'submitComplaintPublic');

    render(<ComplaintForm minSubmitDelayMs={0} />);

    const textarea = screen.getByPlaceholderText(/Ceritakan kejadian secara jelas/i);
    fireEvent.change(textarea, {
      target: { value: 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaa' }, // 30 karakter berulang
    });

    const submitBtn = screen.getByRole('button', { name: /Kirim Laporan Pengaduan/i });
    fireEvent.click(submitBtn);

    expect(
      await screen.findByText(/Uraian laporan terdeteksi tidak wajar atau berisi karakter berulang/i)
    ).toBeInTheDocument();
    expect(mockSubmit).not.toHaveBeenCalled();
  });
});
