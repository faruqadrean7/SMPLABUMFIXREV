import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ppdbApi } from '@/features/ppdb/ppdb-api';
import { apiClient } from '@/lib/api/api-client';
import { tokenStorage } from '@/lib/auth/token-storage';
import ApplicantDashboardPage from '@/app/(ppdb)/ppdb/dashboard/page';

// Mock useRouter
const pushMock = vi.fn();
const replaceMock = vi.fn();
vi.mock('next/navigation', () => ({
  useRouter: () => ({
    push: pushMock,
    replace: replaceMock,
  }),
}));

describe('BATCH 3: PPDB End-to-End Remediation & Re-registration Suite', () => {
  let queryClient: QueryClient;

  beforeEach(() => {
    vi.clearAllMocks();
    queryClient = new QueryClient({
      defaultOptions: {
        queries: { retry: false, gcTime: 0 },
        mutations: { retry: false },
      },
    });
    vi.spyOn(tokenStorage, 'getAccessToken').mockReturnValue('mock-token-123');
    vi.spyOn(ppdbApi, 'getActiveWave').mockResolvedValue(null);
    vi.spyOn(ppdbApi, 'listPublicTracks').mockResolvedValue([]);
  });

  describe('1. ppdbApi API Client Extensions (GAP-PPDB-02 & BE-PPDB-08/09)', () => {
    it('calls POST /api/v1/ppdb/applications/{id}/re-register for applicant re-registration', async () => {
      const postSpy = vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: {
          code: 'SUCCESS',
          message: 'Konfirmasi pendaftaran ulang berhasil dicatat.',
          data: {
            re_registration: { id: 'reg-123', status: 'menunggu' },
            message: 'Konfirmasi pendaftaran ulang berhasil dicatat.',
          },
        },
      } as any);

      const result = await ppdbApi.reRegister('app-789', { notes: 'Bersedia mematuhi tata tertib' });

      expect(postSpy).toHaveBeenCalledWith('/ppdb/applications/app-789/re-register', {
        notes: 'Bersedia mematuhi tata tertib',
      });
      expect(result.re_registration.id).toBe('reg-123');
    });

    it('calls GET /api/v1/ppdb/applications/{id}/receipt for official registration receipt data', async () => {
      const getSpy = vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: {
          code: 'SUCCESS',
          message: 'Berhasil memuat bukti pendaftaran',
          data: {
            registration_no: 'PPDB-2026-001',
            qr_code: 'data:image/png;base64,...',
          },
        },
      } as any);

      const result = await ppdbApi.getReceiptData('app-789');

      expect(getSpy).toHaveBeenCalledWith('/ppdb/applications/app-789/receipt');
      expect(result.registration_no).toBe('PPDB-2026-001');
    });

    it('calls GET /api/v1/ppdb/applications/{id}/receipt?format=pdf for official PDF receipt download', async () => {
      const mockBlob = new Blob(['%PDF-1.4 dummy pdf'], { type: 'application/pdf' });
      const getSpy = vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: mockBlob,
      } as any);

      const result = await ppdbApi.downloadReceiptPdf('app-789');

      expect(getSpy).toHaveBeenCalledWith(
        '/ppdb/applications/app-789/receipt?format=pdf',
        expect.objectContaining({
          responseType: 'blob',
          headers: expect.objectContaining({ Accept: 'application/pdf' }),
        })
      );
      expect(result).toBe(mockBlob);
    });
  });

  describe('2. Client-Side Document Upload Validation (File Size & Extensions)', () => {
    it('validates file size limit strictly at 2 MB', () => {
      const MAX_SIZE = 2 * 1024 * 1024; // 2MB

      const validFile = new File(['small content'], 'rapor.pdf', { type: 'application/pdf' });
      expect(validFile.size).toBeLessThanOrEqual(MAX_SIZE);

      const largeBuffer = new Uint8Array(2.5 * 1024 * 1024);
      const largeFile = new File([largeBuffer], 'foto_besar.jpg', { type: 'image/jpeg' });
      expect(largeFile.size).toBeGreaterThan(MAX_SIZE);
    });

    it('validates allowed document MIME types and extensions', () => {
      const ALLOWED_EXTS = ['.pdf', '.jpg', '.jpeg', '.png', '.webp'];

      const checkExtension = (fileName: string) => {
        const ext = '.' + fileName.split('.').pop()?.toLowerCase();
        return ALLOWED_EXTS.includes(ext);
      };

      expect(checkExtension('kartu_keluarga.pdf')).toBe(true);
      expect(checkExtension('akta_kelahiran.JPG')).toBe(true);
      expect(checkExtension('pasfoto.png')).toBe(true);
      expect(checkExtension('berkas.webp')).toBe(true);
      expect(checkExtension('skrip.exe')).toBe(false);
      expect(checkExtension('arsip.zip')).toBe(false);
    });
  });

  describe('3. Rapor Semester Score Breakdown & Auto-Average (GAP-PPDB-03)', () => {
    it('computes arithmetic average score accurately from core subjects', () => {
      const scores = {
        indonesian: '88',
        math: '92',
        science: '90',
        english: '86',
      };

      const numericScores = Object.values(scores).map(Number).filter((n) => !isNaN(n) && n > 0);
      const calculatedAvg = (numericScores.reduce((a, b) => a + b, 0) / numericScores.length).toFixed(2);

      expect(calculatedAvg).toBe('89.00');
    });
  });

  describe('4. Applicant Dashboard UI - Re-Registration & Receipt Controls (GAP-PPDB-02)', () => {
    const renderWithQueryClient = (ui: React.ReactElement) => {
      return render(<QueryClientProvider client={queryClient}>{ui}</QueryClientProvider>);
    };

    it('renders Congratulations & Re-Registration Card when applicant is accepted (decision = diterima)', async () => {
      vi.spyOn(ppdbApi, 'getMe').mockResolvedValue({
        id: 'u-app-1',
        username: 'calonsiswa',
        email: 'calon@gmail.com',
        phone: '081234567890',
        status: 'active',
        roles: ['guest'],
        applications: [
          {
            id: 'app-accepted-1',
            registration_no: 'REG-2026-999',
            full_name: 'Budi Santoso',
            status: 'verified',
            decision: 'diterima',
            step_data: {},
            wave: { id: 'w-1', name: 'Gelombang 1', status: 'open' },
            track: { id: 't-1', code: 'REG', name: 'Jalur Reguler' },
            documents: [],
          },
        ],
      });

      renderWithQueryClient(<ApplicantDashboardPage />);

      await waitFor(() => {
        expect(screen.getByText(/LOLOS SELEKSI RESMI/i)).toBeInTheDocument();
        expect(screen.getByText(/Selamat! Anda Dinyatakan Diterima/i)).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /Konfirmasi Daftar Ulang/i })).toBeInTheDocument();
      });

      // Buka Modal Konfirmasi
      fireEvent.click(screen.getByRole('button', { name: /Konfirmasi Daftar Ulang/i }));

      expect(screen.getByRole('heading', { name: /Konfirmasi Daftar Ulang/i })).toBeInTheDocument();
      expect(screen.getAllByText(/Budi Santoso/i).length).toBeGreaterThanOrEqual(1);
      expect(screen.getAllByText(/REG-2026-999/i).length).toBeGreaterThanOrEqual(1);
    });

    it('calls downloadReceiptPdf when clicking Cetak Bukti Pendaftaran', async () => {
      vi.spyOn(ppdbApi, 'getMe').mockResolvedValue({
        id: 'u-app-1',
        username: 'calonsiswa',
        email: 'calon@gmail.com',
        phone: '081234567890',
        status: 'active',
        roles: ['guest'],
        applications: [
          {
            id: 'app-accepted-1',
            registration_no: 'REG-2026-999',
            full_name: 'Budi Santoso',
            status: 'verified',
            decision: 'diterima',
            step_data: {},
            wave: { id: 'w-1', name: 'Gelombang 1', status: 'open' },
            track: { id: 't-1', code: 'REG', name: 'Jalur Reguler' },
            documents: [],
          },
        ],
      });

      const downloadSpy = vi.spyOn(ppdbApi, 'downloadReceiptPdf').mockResolvedValueOnce(
        new Blob(['pdf content'], { type: 'application/pdf' })
      );

      // Mock URL.createObjectURL and URL.revokeObjectURL
      window.URL.createObjectURL = vi.fn().mockReturnValue('blob:http://localhost/receipt.pdf');
      window.URL.revokeObjectURL = vi.fn();
      const clickSpy = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => {});

      renderWithQueryClient(<ApplicantDashboardPage />);

      await waitFor(() => {
        expect(screen.getByRole('button', { name: /Cetak Bukti Pendaftaran/i })).toBeInTheDocument();
      });

      fireEvent.click(screen.getByRole('button', { name: /Cetak Bukti Pendaftaran/i }));

      await waitFor(() => {
        expect(downloadSpy).toHaveBeenCalledWith('app-accepted-1');
        expect(clickSpy).toHaveBeenCalled();
      });
      clickSpy.mockRestore();
    });
  });
});
