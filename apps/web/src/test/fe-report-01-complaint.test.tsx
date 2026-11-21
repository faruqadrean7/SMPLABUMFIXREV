import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { ComplaintForm } from '@/features/public-relations/components/complaint-form';
import { ComplaintTracker } from '@/features/public-relations/components/complaint-tracker';
import { publicRelationsApi } from '@/features/public-relations/public-relations-api';
import * as AuthProviderModule from '@/providers/auth-provider';

// Mock next/navigation
const pushMock = vi.fn();
let mockSearchParamsGet = vi.fn().mockReturnValue(null);

vi.mock('next/navigation', () => ({
  useRouter: () => ({
    push: pushMock,
    replace: vi.fn(),
  }),
  useSearchParams: () => ({
    get: mockSearchParamsGet,
  }),
}));

describe('FE-REPORT-01 & ADM-GUEST-01: Form Pengaduan Bullying & Review Admin', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockSearchParamsGet = vi.fn().mockReturnValue(null);

    // Default: guest unauthenticated
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

    // Mock clipboard
    Object.assign(navigator, {
      clipboard: {
        writeText: vi.fn().mockResolvedValue(undefined),
      },
    });
  });

  // =========================================================================
  // 1. FE-REPORT-01 AC1: Submit Bullying Sukses Mendapatkan Tiket ASP-...
  // =========================================================================
  it('FE-REPORT-01 (AC1): Submit kategori bullying dengan uraian valid menghasilkan kode tiket ASP-...', async () => {
    const mockSubmit = vi.spyOn(publicRelationsApi, 'submitComplaintPublic').mockResolvedValueOnce({
      data: {
        ticket_code: 'ASP-20261103-X999',
        category: 'bullying',
        is_anonymous: false,
        status: 'baru',
        message: 'Pengaduan berhasil didaftarkan.',
      },
    } as any);

    render(<ComplaintForm />);

    // Kategori default adalah bullying
    expect(screen.getByText('Perundungan (Bullying)')).toBeInTheDocument();

    const textarea = screen.getByPlaceholderText(
      /Ceritakan kejadian secara jelas/i
    );
    fireEvent.change(textarea, {
      target: {
        value: 'Terjadi pemalakan dan intimidasi di lorong barat lantai dua saat jam istirahat kedua.',
      },
    });

    const submitBtn = screen.getByRole('button', { name: /Kirim Laporan Pengaduan/i });
    fireEvent.click(submitBtn);

    await waitFor(() => {
      expect(mockSubmit).toHaveBeenCalledWith({
        category: 'bullying',
        content: 'Terjadi pemalakan dan intimidasi di lorong barat lantai dua saat jam istirahat kedua.',
        is_anonymous: false,
      });
    });

    // UI Sukses menampilkan kode tiket dan tombol salin
    expect(await screen.findByText('ASP-20261103-X999')).toBeInTheDocument();
    expect(screen.getByText(/Laporan Pengaduan Terkirim/i)).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /Salin Kode Tiket/i })).toBeInTheDocument();
  });

  // =========================================================================
  // 2. FE-REPORT-01 AC2: Validasi Uraian < 10 Karakter Menampilkan Error
  // =========================================================================
  it('FE-REPORT-01 (AC2): Uraian < 10 karakter menampilkan pesan error validasi per field dan tidak submit', async () => {
    const mockSubmit = vi.spyOn(publicRelationsApi, 'submitComplaintPublic');

    render(<ComplaintForm />);

    const textarea = screen.getByPlaceholderText(/Ceritakan kejadian secara jelas/i);
    fireEvent.change(textarea, {
      target: { value: 'singkat' }, // hanya 7 karakter
    });

    const submitBtn = screen.getByRole('button', { name: /Kirim Laporan Pengaduan/i });
    fireEvent.click(submitBtn);

    expect(await screen.findByText('Uraian pengaduan minimal 10 karakter')).toBeInTheDocument();
    expect(mockSubmit).not.toHaveBeenCalled();
  });

  // =========================================================================
  // 3. FE-REPORT-01 AC3: Toggle Anonim Mengirim is_anonymous: true
  // =========================================================================
  it('FE-REPORT-01 (AC3): Centang anonim mengirimkan payload is_anonymous: true dan menampilkan penjelasan anonim', async () => {
    const mockSubmit = vi.spyOn(publicRelationsApi, 'submitComplaintPublic').mockResolvedValueOnce({
      data: {
        ticket_code: 'ASP-20261103-ANON',
        category: 'bullying',
        is_anonymous: true,
        status: 'baru',
        message: 'Pengaduan anonim berhasil didaftarkan.',
      },
    } as any);

    render(<ComplaintForm />);

    const anonCheckbox = screen.getByRole('checkbox', {
      name: /Kirim sebagai Laporan Anonim/i,
    });
    fireEvent.click(anonCheckbox);
    expect(anonCheckbox).toBeChecked();

    const textarea = screen.getByPlaceholderText(/Ceritakan kejadian secara jelas/i);
    fireEvent.change(textarea, {
      target: {
        value: 'Laporan perundungan anonim: ada ejekan verbal di grup pesan kelas 8B.',
      },
    });

    const submitBtn = screen.getByRole('button', { name: /Kirim Laporan Pengaduan/i });
    fireEvent.click(submitBtn);

    await waitFor(() => {
      expect(mockSubmit).toHaveBeenCalledWith({
        category: 'bullying',
        content: 'Laporan perundungan anonim: ada ejekan verbal di grup pesan kelas 8B.',
        is_anonymous: true,
      });
    });

    expect(await screen.findByText('ASP-20261103-ANON')).toBeInTheDocument();
  });

  // =========================================================================
  // 4. FE-REPORT-01 AC4: Pengguna Login Non-Anonim Menampilkan Info Pelapor
  // =========================================================================
  it('FE-REPORT-01 (AC4): Saat pengguna login dan tidak anonim, tampilkan info identitas akun terhubung', () => {
    vi.spyOn(AuthProviderModule, 'useAuth').mockReturnValue({
      user: {
        id: 'u-siswa-1',
        username: 'siswa_budi',
        email: 'budi@sekolah.sch.id',
        name: 'Budi Santoso',
      } as any,
      isLoading: false,
      isAuthenticated: true,
      activeRole: 'student',
      login: vi.fn(),
      logout: vi.fn(),
      checkAuth: vi.fn(),
      setActiveRole: vi.fn(),
    });

    render(<ComplaintForm />);

    expect(screen.getByText(/Dilaporkan sebagai civitas terdaftar:/i)).toBeInTheDocument();
    expect(screen.getByText('siswa_budi')).toBeInTheDocument();
  });

  // =========================================================================
  // 5. FE-REPORT-01 AC5: Lacak Status Pengaduan dengan Kode Valid
  // =========================================================================
  it('FE-REPORT-01 (AC5): Halaman lacak menampilkan status, kategori, dan timeline kronologi penanganan', async () => {
    const mockTrack = vi.spyOn(publicRelationsApi, 'trackComplaintPublic').mockResolvedValueOnce({
      data: {
        ticket_code: 'ASP-20261103-VAL1',
        category: 'bullying',
        content: 'Intimidasi di area parkir sepeda sekolah.',
        status: 'ditindaklanjuti',
        created_at: '2026-11-03T07:00:00Z',
        updated_at: '2026-11-03T08:00:00Z',
        timeline: [
          {
            event_type: 'created',
            response_text: 'Pengaduan telah masuk ke sistem penanganan sekolah.',
            created_at: '2026-11-03T07:00:00Z',
          },
          {
            event_type: 'status_update',
            response_text: 'Guru BK telah memanggil saksi dan melakukan mediasi tertutup.',
            created_at: '2026-11-03T08:00:00Z',
          },
        ],
      },
    } as any);

    render(<ComplaintTracker initialCode="ASP-20261103-VAL1" />);

    await waitFor(() => {
      expect(mockTrack).toHaveBeenCalledWith('ASP-20261103-VAL1');
    });

    expect(await screen.findByText('ASP-20261103-VAL1')).toBeInTheDocument();
    expect(screen.getByText(/Perundungan \(Bullying\)/i)).toBeInTheDocument();
    expect(screen.getByText('Sedang Ditindaklanjuti')).toBeInTheDocument();
    expect(screen.getByText('Guru BK telah memanggil saksi dan melakukan mediasi tertutup.')).toBeInTheDocument();
  });

  // =========================================================================
  // 6. FE-REPORT-01 AC5 (Error Case): Lacak Tiket yang Tidak Ditemukan
  // =========================================================================
  it('FE-REPORT-01 (AC5): Lacak dengan kode tidak ditemukan menampilkan pesan ramah tanpa kebocoran data', async () => {
    vi.spyOn(publicRelationsApi, 'trackComplaintPublic').mockRejectedValueOnce({
      response: {
        status: 404,
        data: { message: 'Data pengaduan dengan kode tiket tersebut tidak ditemukan' },
      },
    });

    render(<ComplaintTracker />);

    const searchInput = screen.getByRole('textbox', { name: /Kode Tiket Pengaduan/i });
    fireEvent.change(searchInput, { target: { value: 'ASP-99999999-XXXX' } });

    const searchBtn = screen.getByRole('button', { name: /Lacak Tiket/i });
    fireEvent.click(searchBtn);

    expect(
      await screen.findByText(/Data pengaduan dengan kode tiket tersebut tidak ditemukan/i)
    ).toBeInTheDocument();
    expect(screen.queryByTestId('track-result-card')).not.toBeInTheDocument();
  });

  // =========================================================================
  // 7. FE-REPORT-01 AC6: Konten Laporan Sensitif Tidak Ditaruh di URL
  // =========================================================================
  it('FE-REPORT-01 (AC6): Isi laporan tidak pernah diletakkan di URL atau query parameter', async () => {
    vi.spyOn(publicRelationsApi, 'submitComplaintPublic').mockResolvedValueOnce({
      data: {
        ticket_code: 'ASP-20261103-SEC1',
        category: 'bullying',
        is_anonymous: true,
        status: 'baru',
        message: 'Terkirim.',
      },
    } as any);

    render(<ComplaintForm />);

    const textarea = screen.getByPlaceholderText(/Ceritakan kejadian secara jelas/i);
    const secretContent = 'Siswa korban berinisial AB mengalami ancaman pemukulan dari kakak kelas.';
    fireEvent.change(textarea, { target: { value: secretContent } });

    const submitBtn = screen.getByRole('button', { name: /Kirim Laporan Pengaduan/i });
    fireEvent.click(submitBtn);

    await screen.findByText('ASP-20261103-SEC1');

    // Link ke lacak status hanya membawa kode tiket, sama sekali tidak ada secretContent
    const trackLink = screen.getByRole('link', { name: /Lacak Perkembangan Status/i });
    expect(trackLink.getAttribute('href')).toBe('/lapor/lacak?kode=ASP-20261103-SEC1');
    expect(trackLink.getAttribute('href')).not.toContain(encodeURIComponent(secretContent));
    expect(trackLink.getAttribute('href')).not.toContain('AB');
  });
});
