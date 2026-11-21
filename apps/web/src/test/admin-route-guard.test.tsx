import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import AdminLayout from '@/app/(admin)/admin/layout';
import { getAdminRoutePermission } from '@/lib/rbac/navigation';
import * as AuthProviderModule from '@/providers/auth-provider';
import { User } from '@/types/auth';

let currentPathname = '/admin';

vi.mock('next/navigation', () => ({
  useRouter: () => ({
    push: vi.fn(),
    replace: vi.fn(),
  }),
  usePathname: () => currentPathname,
}));

vi.mock('@/features/master/academic-years-api', () => ({
  academicYearsApi: {
    getActive: vi.fn().mockResolvedValue({
      data: { id: 'ay-1', year: '2025/2026', semester: 'Ganjil', is_active: true },
    }),
  },
}));

vi.mock('@/components/notifications/notification-bell', () => ({
  NotificationBell: () => <div data-testid="notification-bell">Bell</div>,
}));

describe('POST-AUDIT-03: Admin Route Guard & Direct URL Access Matrix', () => {
  let queryClient: QueryClient;

  beforeEach(() => {
    vi.clearAllMocks();
    currentPathname = '/admin';
    queryClient = new QueryClient({
      defaultOptions: {
        queries: { retry: false },
      },
    });
  });

  const renderWithProviders = (ui: React.ReactElement) => {
    return render(<QueryClientProvider client={queryClient}>{ui}</QueryClientProvider>);
  };

  const createTestUser = (roles: string[], permissions: string[] = []): User => ({
    id: `usr-${roles.join('-')}`,
    schoolId: 'sch-01',
    username: `user_${roles.join('_')}`,
    email: `${roles[0]}@smp-labum.sch.id`,
    phone: null,
    status: 'active',
    mustChangePassword: false,
    lastLoginAt: '2026-10-15T08:00:00Z',
    roles,
    permissions,
    profile: { name: `Tester ${roles[0]}` },
  });

  describe('1. Resolusi Izin Route (getAdminRoutePermission)', () => {
    it('mengembalikan undefined untuk beranda admin umum (/admin)', () => {
      expect(getAdminRoutePermission('/admin')).toBeUndefined();
      expect(getAdminRoutePermission('/admin/')).toBeUndefined();
    });

    it('memetakan rute BK (/admin/counseling) ke permission bimbingan konseling', () => {
      const perm = getAdminRoutePermission('/admin/counseling');
      expect(perm).toEqual(['counseling.case.read', 'counseling.case.write']);
    });

    it('memetakan rute Kesiswaan (/admin/student-affairs/points) ke permission poin siswa', () => {
      const perm = getAdminRoutePermission('/admin/student-affairs/points');
      expect(perm).toBe('student.point.write');
    });

    it('memetakan rute Humas (/admin/public-relations) ke permission buku tamu & aduan', () => {
      const perm = getAdminRoutePermission('/admin/public-relations');
      expect(perm).toEqual(['guest.book.read', 'guest.book.manage', 'complaint.read', 'complaint.manage']);
    });

    it('memetakan rute CBT (/admin/cbt/questions) ke permission bank soal', () => {
      const perm = getAdminRoutePermission('/admin/cbt/questions');
      expect(perm).toBe('cbt.bank.write');
    });

    it('memetakan sub-rute bertingkat secara presisi (/admin/settings/roles)', () => {
      const perm = getAdminRoutePermission('/admin/settings/roles');
      expect(perm).toBe('auth.role.manage');
    });

    it('memetakan rute Sarpras & Fasilitas (/admin/facilities)', () => {
      const perm = getAdminRoutePermission('/admin/facilities');
      expect(perm).toEqual(['facility.booking.manage', 'facility.asset.manage']);
    });

    it('memetakan rute Perpustakaan (/admin/library)', () => {
      const perm = getAdminRoutePermission('/admin/library');
      expect(perm).toEqual(['facility.asset.manage', 'library.book.manage']);
    });
  });

  describe('2. Direct URL Access via AdminLayout Rendering', () => {
    const mockAuthWith = (user: User | null, isAuthenticated = true, isLoading = false) => {
      vi.spyOn(AuthProviderModule, 'useAuth').mockReturnValue({
        user,
        isLoading,
        isAuthenticated,
        activeRole: user?.roles[0] || null,
        login: vi.fn(),
        logout: vi.fn(),
        checkAuth: vi.fn(),
        setActiveRole: vi.fn(),
      });
    };

    it('Super Admin dapat membuka rute sensitif apa pun tanpa blokir', () => {
      currentPathname = '/admin/settings/roles';
      mockAuthWith(createTestUser(['super_admin']));

      renderWithProviders(
        <AdminLayout>
          <div data-testid="roles-page">Manajemen Role Superadmin</div>
        </AdminLayout>
      );

      expect(screen.getByTestId('roles-page')).toBeInTheDocument();
      expect(screen.queryByText('Akses Modul Terbatas')).not.toBeInTheDocument();
    });

    it('Guru BK (counselor) diizinkan membuka /admin/counseling', () => {
      currentPathname = '/admin/counseling';
      mockAuthWith(
        createTestUser(['counselor'], ['counseling.case.read', 'counseling.case.write', 'master.student.read'])
      );

      renderWithProviders(
        <AdminLayout>
          <div data-testid="counseling-page">Daftar Konseling Siswa</div>
        </AdminLayout>
      );

      expect(screen.getByTestId('counseling-page')).toBeInTheDocument();
      expect(screen.queryByText('Akses Modul Terbatas')).not.toBeInTheDocument();
    });

    it('Guru BK (counselor) yang mengetik langsung /admin/settings/roles melihat AccessDenied tanpa kehilangan sidebar', () => {
      currentPathname = '/admin/settings/roles';
      mockAuthWith(
        createTestUser(['counselor'], ['counseling.case.read', 'counseling.case.write', 'master.student.read'])
      );

      renderWithProviders(
        <AdminLayout>
          <div data-testid="roles-page">Manajemen Role</div>
        </AdminLayout>
      );

      // Konten halaman target diblokir
      expect(screen.queryByTestId('roles-page')).not.toBeInTheDocument();
      // AccessDenied komponen modul muncul
      expect(screen.getByText('Akses Modul Terbatas')).toBeInTheDocument();
      expect(screen.getByText(/tidak memiliki izin untuk mengakses modul ini/i)).toBeInTheDocument();
      // Navigasi shell tetap hadir (sidebar & topbar tetap ada di DOM)
      expect(document.querySelector('aside')).toBeInTheDocument();
      expect(screen.getByText('Beranda Admin')).toBeInTheDocument();
    });

    it('Staf Kesiswaan (student_affairs) diizinkan membuka /admin/student-affairs/points', () => {
      currentPathname = '/admin/student-affairs/points';
      mockAuthWith(
        createTestUser(['student_affairs'], [
          'student.point.read',
          'student.point.write',
          'student.extracurricular.manage',
        ])
      );

      renderWithProviders(
        <AdminLayout>
          <div data-testid="points-page">Buku Poin & Prestasi Siswa</div>
        </AdminLayout>
      );

      expect(screen.getByTestId('points-page')).toBeInTheDocument();
      expect(screen.queryByText('Akses Modul Terbatas')).not.toBeInTheDocument();
    });

    it('Staf Kesiswaan yang mengetik langsung /admin/cbt/questions diblokir dengan AccessDenied', () => {
      currentPathname = '/admin/cbt/questions';
      mockAuthWith(
        createTestUser(['student_affairs'], [
          'student.point.read',
          'student.point.write',
        ])
      );

      renderWithProviders(
        <AdminLayout>
          <div data-testid="cbt-questions">Bank Soal CBT Rahasia</div>
        </AdminLayout>
      );

      expect(screen.queryByTestId('cbt-questions')).not.toBeInTheDocument();
      expect(screen.getByText('Akses Modul Terbatas')).toBeInTheDocument();
    });

    it('Staf Humas (public_relations) diizinkan membuka /admin/public-relations', () => {
      currentPathname = '/admin/public-relations';
      mockAuthWith(
        createTestUser(['public_relations'], [
          'guest.book.read',
          'guest.book.manage',
          'complaint.read',
          'complaint.manage',
        ])
      );

      renderWithProviders(
        <AdminLayout>
          <div data-testid="pr-page">Buku Tamu dan Pengaduan Publik</div>
        </AdminLayout>
      );

      expect(screen.getByTestId('pr-page')).toBeInTheDocument();
      expect(screen.queryByText('Akses Modul Terbatas')).not.toBeInTheDocument();
    });

    it('Staf Humas yang mengetik langsung /admin/settings/roles diblokir dengan AccessDenied', () => {
      currentPathname = '/admin/settings/roles';
      mockAuthWith(
        createTestUser(['public_relations'], [
          'guest.book.read',
          'guest.book.manage',
        ])
      );

      renderWithProviders(
        <AdminLayout>
          <div data-testid="roles-page">Manajemen Role</div>
        </AdminLayout>
      );

      expect(screen.queryByTestId('roles-page')).not.toBeInTheDocument();
      expect(screen.getByText('Akses Modul Terbatas')).toBeInTheDocument();
    });

    it('Pustakawan (librarian) diizinkan membuka /admin/library', () => {
      currentPathname = '/admin/library';
      mockAuthWith(
        createTestUser(['librarian'], [
          'facility.asset.manage',
          'library.book.manage',
        ])
      );

      renderWithProviders(
        <AdminLayout>
          <div data-testid="library-page">Katalog Perpustakaan</div>
        </AdminLayout>
      );

      expect(screen.getByTestId('library-page')).toBeInTheDocument();
      expect(screen.queryByText('Akses Modul Terbatas')).not.toBeInTheDocument();
    });

    it('Staf Sarpras (facility_staff) yang mengetik langsung /admin/counseling diblokir dengan AccessDenied', () => {
      currentPathname = '/admin/counseling';
      mockAuthWith(
        createTestUser(['facility_staff'], [
          'facility.booking.manage',
          'facility.asset.manage',
        ])
      );

      renderWithProviders(
        <AdminLayout>
          <div data-testid="bk-page">Arsip BK Pribadi</div>
        </AdminLayout>
      );

      expect(screen.queryByTestId('bk-page')).not.toBeInTheDocument();
      expect(screen.getByText('Akses Modul Terbatas')).toBeInTheDocument();
    });

    it('Siswa (student) yang mencoba membuka /admin/master diblokir di level shell dengan layar Akses Dibatasi', () => {
      currentPathname = '/admin/master';
      mockAuthWith(createTestUser(['student']));

      renderWithProviders(
        <AdminLayout>
          <div data-testid="master-page">Master Data Sekolah</div>
        </AdminLayout>
      );

      expect(screen.queryByTestId('master-page')).not.toBeInTheDocument();
      expect(screen.getByText('Akses Dibatasi')).toBeInTheDocument();
      expect(screen.getByText(/Akun Anda terdaftar sebagai Siswa atau Orang Tua/i)).toBeInTheDocument();
      // Shell sidebar tidak dirender untuk peran non-admin
      expect(screen.queryByRole('navigation', { name: /sidebar navigasi admin/i })).not.toBeInTheDocument();
    });
  });
});
