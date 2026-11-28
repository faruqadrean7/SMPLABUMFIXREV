import React from 'react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import {
  NotificationBell,
  extractNotificationTargetUrl,
  getSafeDeepLink,
} from '@/components/notifications/notification-bell';
import AdminNotificationPage from '@/app/(admin)/admin/notifications/page';
import PublicRelationsAdminPage from '@/app/(admin)/admin/public-relations/page';
import GuardianKonselingPage from '@/app/(portal)/portal/ortu/konseling/page';
import { notificationsApi } from '@/features/notifications/notifications-api';
import { publicRelationsApi } from '@/features/public-relations/public-relations-api';
import * as AuthProviderModule from '@/providers/auth-provider';
import * as GuardianContextModule from '@/lib/guardian-context';
import * as GuardianDataModule from '@/lib/use-guardian-data';

vi.mock('next/navigation', () => ({
  useRouter: () => ({ push: vi.fn(), replace: vi.fn() }),
  useSearchParams: () => ({ get: (key: string) => (key === 'tab' ? 'complaints' : null) }),
}));

vi.mock('sonner', () => ({
  toast: { success: vi.fn(), error: vi.fn(), info: vi.fn() },
}));

describe('Batch 2B-7: Komunikasi, Notifikasi Real-Time, Deep-Link, & UI PR/Konseling', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it('P1-20: extractNotificationTargetUrl memprioritaskan target_url -> action_url -> deep_link -> url dengan sanitasi aman', () => {
    expect(extractNotificationTargetUrl({ target_url: '/portal/guru/nilai', action_url: '/old' })).toBe('/portal/guru/nilai');
    expect(extractNotificationTargetUrl({ action_url: '/admin/public-relations?tab=complaints' })).toBe('/admin/public-relations?tab=complaints');
    expect(extractNotificationTargetUrl({ deep_link: '/portal/siswa/tugas' })).toBe('/portal/siswa/tugas');
    expect(extractNotificationTargetUrl({ url: '/admin/tickets' })).toBe('/admin/tickets');
    expect(getSafeDeepLink('javascript:alert(1)')).toBeNull();
    expect(getSafeDeepLink('https://evil.example/phish')).toBeNull();
  });

  it('P1-18: NotificationBell melakukan polling berkala saat tab terlihat dan jeda saat hidden', async () => {
    vi.useFakeTimers();
    const getSpy = vi.spyOn(notificationsApi, 'getNotifications').mockResolvedValue({
      data: [],
      meta: { page: 1, limit: 5, total: 0, totalPages: 1, unread_count: 1 },
    });

    Object.defineProperty(document, 'visibilityState', { value: 'visible', configurable: true });
    render(<NotificationBell portal="portal" />);

    expect(getSpy).toHaveBeenCalledTimes(1);

    await vi.advanceTimersByTimeAsync(60_000);
    expect(getSpy).toHaveBeenCalledTimes(2);

    Object.defineProperty(document, 'visibilityState', { value: 'hidden', configurable: true });
    await vi.advanceTimersByTimeAsync(60_000);
    expect(getSpy).toHaveBeenCalledTimes(2);

    Object.defineProperty(document, 'visibilityState', { value: 'visible', configurable: true });
  });

  it('P1-23: Admin PR Modal 3 merender galeri lampiran pengaduan dan membatasi opsi transisi status', async () => {
    vi.spyOn(AuthProviderModule, 'useAuth').mockReturnValue({
      user: { id: 'bk-1', username: 'gurubk', email: 'bk@sekolah.test', role: 'counselor', permissions: ['complaint.read', 'complaint.manage'] } as any,
      isLoading: false,
      isAuthenticated: true,
      activeRole: 'counselor',
      login: vi.fn(),
      logout: vi.fn(),
      checkAuth: vi.fn(),
      setActiveRole: vi.fn(),
    });

    const complaintRow = {
      id: 'comp-att-1',
      ticket_code: 'ASP-20261128-IMG',
      category: 'bullying',
      content: 'Bukti foto insiden terlampir.',
      is_anonymous: true,
      status: 'baru' as const,
      severity: 'tinggi' as const,
      created_at: '2026-11-28T07:00:00Z',
      updated_at: '2026-11-28T07:00:00Z',
      events: [],
    };

    vi.spyOn(publicRelationsApi, 'getComplaints').mockResolvedValue({ data: [complaintRow] } as any);
    vi.spyOn(publicRelationsApi, 'getComplaint').mockResolvedValue({
      data: {
        ...complaintRow,
        attachments: [
          {
            id: 'file-img-1',
            object_key: 'complaints/bukti-foto.jpg',
            mime_type: 'image/jpeg',
            signed_url: '/api/v1/files/file-img-1/signed?signature=abc',
          },
        ],
      },
    } as any);
    vi.spyOn(publicRelationsApi, 'getGuests').mockResolvedValue({ data: [] } as any);
    vi.spyOn(publicRelationsApi, 'getPartnerships').mockResolvedValue({ data: [] } as any);
    vi.spyOn(publicRelationsApi, 'getAlumni').mockResolvedValue({ data: [] } as any);

    render(<PublicRelationsAdminPage />);
    expect(await screen.findByText('ASP-20261128-IMG')).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: /Tindak Lanjut/i }));

    expect(await screen.findByText(/Bukti Lampiran \(1 berkas\)/i)).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /Buka Lampiran/i })).toHaveAttribute(
      'href',
      '/api/v1/files/file-img-1/signed?signature=abc'
    );
    // Dari 'baru', opsi 'Selesai' tidak boleh tersedia (sesuai ALLOWED_TRANSITIONS 69b3832)
    expect(screen.queryByRole('option', { name: /Selesai \(Penanganan tuntas\)/i })).not.toBeInTheDocument();
  });

  it('P1-23: portal/ortu/konseling bebas kontak fiktif Ratna Dewi dan menormalkan 08 menjadi 628', async () => {
    vi.spyOn(GuardianContextModule, 'useGuardian').mockReturnValue({
      activeChild: {
        id: 'std-1',
        name: 'Siswa Fiktif',
        class_name: '8-A',
        nis: '2026001',
        homeroom_teacher: 'Wali Kelas Fiktif',
        homeroom_phone: '081299990000',
      } as any,
      children: [],
      setActiveChildId: vi.fn(),
      isLoading: false,
    } as any);
    vi.spyOn(GuardianDataModule, 'useGuardianCounselingRequests').mockReturnValue({
      data: [],
      isLoading: false,
    } as any);
    vi.spyOn(GuardianDataModule, 'useCreateGuardianCounselingRequest').mockReturnValue({
      mutateAsync: vi.fn(),
    } as any);

    const qc = new QueryClient();
    render(
      <QueryClientProvider client={qc}>
        <GuardianKonselingPage />
      </QueryClientProvider>
    );

    expect(screen.queryByText(/Ratna Dewi/i)).not.toBeInTheDocument();
    const waLink = screen.getByRole('link', { name: /WhatsApp/i });
    expect(waLink).toHaveAttribute('href', 'https://wa.me/6281299990000');
  });

  it('P1-19: /admin/notifications merender 4 tab komunikasi untuk admin berizin broadcast', async () => {
    vi.spyOn(AuthProviderModule, 'useAuth').mockReturnValue({
      user: {
        id: 'adm-1',
        username: 'admin_notif',
        email: 'admin@sekolah.test',
        role: 'super_admin',
        permissions: ['notification.broadcast'],
      } as any,
      isLoading: false,
      isAuthenticated: true,
      activeRole: 'super_admin',
      login: vi.fn(),
      logout: vi.fn(),
      checkAuth: vi.fn(),
      setActiveRole: vi.fn(),
    });
    vi.spyOn(notificationsApi, 'getNotifications').mockResolvedValue({
      data: [],
      meta: { page: 1, limit: 10, total: 0, totalPages: 1, unread_count: 0 },
    });

    render(<AdminNotificationPage />);

    expect(await screen.findByRole('button', { name: /Kotak Masuk/i })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /Broadcast/i })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /Pengumuman & Pembaca/i })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /Template & Log WA\/Email/i })).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: /Broadcast/i }));
    await waitFor(() => {
      expect(screen.getByText(/Kirim Broadcast Notifikasi In-App/i)).toBeInTheDocument();
    });
  });
});
