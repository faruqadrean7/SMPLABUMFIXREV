import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import PublicRelationsAdminPage from '@/app/(admin)/admin/public-relations/page';
import { publicRelationsApi } from '@/features/public-relations/public-relations-api';
import * as AuthProviderModule from '@/providers/auth-provider';

// Mock next/navigation
let mockSearchParamsGet = vi.fn().mockImplementation((key: string) => {
  if (key === 'tab') return 'complaints';
  return null;
});

vi.mock('next/navigation', () => ({
  useRouter: () => ({
    push: vi.fn(),
    replace: vi.fn(),
  }),
  useSearchParams: () => ({
    get: mockSearchParamsGet,
  }),
}));

describe('ADM-GUEST-02: Review Admin Pengaduan Bullying (Prioritas, SLA 48 Jam & Audit Baca)', () => {
  const mockComplaints = [
    {
      id: 'comp-1',
      ticket_code: 'ASP-20261103-BULL1',
      category: 'bullying',
      content: 'Laporan perundungan fisik di area belakang lab komputer.',
      is_anonymous: true,
      status: 'baru',
      created_at: '2026-11-03T07:00:00Z',
      updated_at: '2026-11-03T07:00:00Z',
      events: [
        {
          id: 'ev-1',
          event_type: 'created',
          response_text: 'Pengaduan baru terdaftar.',
          created_at: '2026-11-03T07:00:00Z',
        },
      ],
    },
    {
      id: 'comp-2',
      ticket_code: 'ASP-20261103-FASIL',
      category: 'fasilitas',
      content: 'Lampu ruang kelas 7A mati.',
      is_anonymous: false,
      reporter: {
        id: 'user-1',
        username: 'siswa_7a',
        email: 'siswa7a@sekolah.sch.id',
      },
      status: 'baru',
      created_at: '2026-11-03T07:30:00Z',
      updated_at: '2026-11-03T07:30:00Z',
      events: [],
    },
  ];

  beforeEach(() => {
    vi.clearAllMocks();
    mockSearchParamsGet = vi.fn().mockImplementation((key: string) => {
      if (key === 'tab') return 'complaints';
      return null;
    });

    vi.spyOn(AuthProviderModule, 'useAuth').mockReturnValue({
      user: {
        id: 'counselor-1',
        username: 'gurubk',
        email: 'bk@sekolah.sch.id',
        role: 'counselor',
        permissions: ['complaint.read', 'complaint.manage'],
      } as any,
      isLoading: false,
      isAuthenticated: true,
      activeRole: 'counselor',
      login: vi.fn(),
      logout: vi.fn(),
      checkAuth: vi.fn(),
      setActiveRole: vi.fn(),
    });

    // Mock API methods
    vi.spyOn(publicRelationsApi, 'getComplaints').mockResolvedValue({
      data: mockComplaints,
    } as any);

    vi.spyOn(publicRelationsApi, 'getComplaint').mockResolvedValue({
      data: {
        ...mockComplaints[0],
        events: [
          ...mockComplaints[0].events,
          {
            id: 'ev-2',
            event_type: 'detail_viewed',
            response_text: 'Sedang disinkronkan oleh sistem.',
            created_at: '2026-11-03T08:00:00Z',
          },
        ],
      },
    } as any);

    vi.spyOn(publicRelationsApi, 'getGuests').mockResolvedValue({ data: [] } as any);
    vi.spyOn(publicRelationsApi, 'getPartnerships').mockResolvedValue({ data: [] } as any);
    vi.spyOn(publicRelationsApi, 'getAlumni').mockResolvedValue({ data: [] } as any);
  });

  // =========================================================================
  // 1. ADM-GUEST-02: TABEL & BADGE PRIORITAS SLA 48 JAM UNTUK PERUNDUNGAN
  // =========================================================================
  it('ADM-GUEST-02 (AC3): Menampilkan badge prioritas dan indikator SLA 48 jam pada laporan perundungan', async () => {
    render(<PublicRelationsAdminPage />);

    expect(await screen.findByText('ASP-20261103-BULL1')).toBeInTheDocument();
    expect(screen.getByText('ASP-20261103-FASIL')).toBeInTheDocument();

    // Pastikan badge SLA 48 Jam muncul untuk baris perundungan
    expect(screen.getByText('SLA: ≤ 48 Jam')).toBeInTheDocument();
    expect(screen.getByText('Perundungan')).toBeInTheDocument();

    // Fasilitas tidak memiliki badge SLA darurat
    expect(screen.getByText('fasilitas')).toBeInTheDocument();
  });

  // =========================================================================
  // 2. ADM-GUEST-02: MEMANGGIL GET COMPLAINT DETAIL SAAT MODAL DIBUKA (AUDIT-BACA)
  // =========================================================================
  it('ADM-GUEST-02 (AC1): Membuka detail aduan memanggil publicRelationsApi.getComplaint untuk audit-baca backend', async () => {
    const mockGetComplaint = vi.spyOn(publicRelationsApi, 'getComplaint');

    render(<PublicRelationsAdminPage />);

    expect(await screen.findByText('ASP-20261103-BULL1')).toBeInTheDocument();

    const detailButtons = screen.getAllByRole('button', { name: /Tindak Lanjut/i });
    fireEvent.click(detailButtons[0]);

    // Verifikasi pemanggilan getComplaint dengan ID aduan
    await waitFor(() => {
      expect(mockGetComplaint).toHaveBeenCalledWith('comp-1');
    });

    // Verifikasi banner transparansi Audit Baca Aktif muncul di modal
    expect(
      screen.getByText(/Audit Baca Aktif: Pembukaan detail laporan sensitif ini tercatat secara otomatis pada log audit sistem/i)
    ).toBeInTheDocument();
    expect(screen.getByText('BE-REPORT-02')).toBeInTheDocument();

    // Verifikasi banner khusus perundungan dengan peringatan SLA
    expect(screen.getByText(/Perhatian Khusus — Kasus Perundungan \(SLA: ≤ 48 Jam\)/i)).toBeInTheDocument();
    expect(screen.getByText('Prioritas Tinggi')).toBeInTheDocument();
  });

  // =========================================================================
  // 3. ADM-GUEST-02: UPDATE STATUS PENGADUAN DENGAN CATATAN RESPON
  // =========================================================================
  it('ADM-GUEST-02 (AC4): Guru BK dapat memperbarui status penanganan dengan catatan tindak lanjut', async () => {
    const mockUpdateStatus = vi.spyOn(publicRelationsApi, 'updateComplaintStatus').mockResolvedValueOnce({
      data: {
        ...mockComplaints[0],
        status: 'ditindaklanjuti',
      },
    } as any);

    render(<PublicRelationsAdminPage />);

    expect(await screen.findByText('ASP-20261103-BULL1')).toBeInTheDocument();

    const detailButtons = screen.getAllByRole('button', { name: /Tindak Lanjut/i });
    fireEvent.click(detailButtons[0]);

    await screen.findByText(/Audit Baca Aktif/i);

    const textarea = screen.getByPlaceholderText(/Tuliskan catatan tindak lanjut/i);
    fireEvent.change(textarea, {
      target: { value: 'Tim Satgas PPKSP telah memanggil orang tua korban dan pelaku untuk koordinasi perlindungan.' },
    });

    const submitBtn = screen.getByRole('button', { name: /Simpan Tindak Lanjut/i });
    fireEvent.click(submitBtn);

    await waitFor(() => {
      expect(mockUpdateStatus).toHaveBeenCalledWith(
        'comp-1',
        expect.objectContaining({
          response_text: 'Tim Satgas PPKSP telah memanggil orang tua korban dan pelaku untuk koordinasi perlindungan.',
        })
      );
    });
  });
});
