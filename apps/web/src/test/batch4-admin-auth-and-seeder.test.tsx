import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { LoginForm } from '@/features/auth/login-form';
import { AdminLoginForm } from '@/features/auth/admin-login-form';
import AdminLayout from '@/app/(admin)/admin/layout';
import { useAuth } from '@/providers/auth-provider';

// Mock Next.js router & searchParams
const mockPush = vi.fn();
const mockReplace = vi.fn();
vi.mock('next/navigation', () => ({
  useRouter: () => ({ push: mockPush, replace: mockReplace }),
  useSearchParams: () => new URLSearchParams(),
  usePathname: () => '/admin',
}));

// Mock Auth Provider
vi.mock('@/providers/auth-provider', () => ({
  useAuth: vi.fn(),
}));

describe('BATCH 4B (GAP-SEC-02): Hardening Login Admin, Hidden Gateway & 404 Guard', () => {
  const mockLogin = vi.fn();
  let queryClient: QueryClient;

  beforeEach(() => {
    vi.clearAllMocks();
    queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
    (useAuth as unknown as ReturnType<typeof vi.fn>).mockReturnValue({
      login: mockLogin,
      user: null,
      isLoading: false,
      isAuthenticated: false,
    });
  });

  describe('1. Civitas Portal Login (LoginForm)', () => {
    it('renders persona options (Siswa, Orang Tua, Guru, Staf) and submits WITHOUT admin flag', async () => {
      mockLogin.mockResolvedValueOnce({
        user: { id: 'usr-1', username: 'guru.mtk', roles: ['teacher', 'homeroom'] },
        destination: 'guru',
        mfa_required: false,
      });

      render(<LoginForm />);

      // Verify persona options exist and NO admin option exists
      expect(screen.getByText('Siswa')).toBeInTheDocument();
      expect(screen.getByText('Orang Tua')).toBeInTheDocument();
      expect(screen.getByText('Guru')).toBeInTheDocument();
      expect(screen.getByText('Staf')).toBeInTheDocument();
      expect(screen.queryByText('Admin')).not.toBeInTheDocument();
      expect(screen.queryByText('Super Admin')).not.toBeInTheDocument();

      // Click on "Guru" persona
      fireEvent.click(screen.getByText('Guru'));

      // Fill in credentials
      const idInput = screen.getByPlaceholderText(/NIP \/ Email Sekolah \/ Username/i);
      const passInput = screen.getByPlaceholderText(/Masukkan kata sandi/i);

      fireEvent.change(idInput, { target: { value: 'guru.mtk' } });
      fireEvent.change(passInput, { target: { value: 'Password123!' } });

      // Submit
      fireEvent.click(screen.getByRole('button', { name: /Masuk ke Portal Terpadu/i }));

      await waitFor(() => {
        // Civitas form NEVER sets isAdminLogin
        expect(mockLogin).toHaveBeenCalledWith({
          identifier: 'guru.mtk',
          password: 'Password123!',
          platform: 'web',
        });
        expect(mockPush).toHaveBeenCalledWith('/portal/guru');
      });
    });
  });

  describe('2. Dedicated Admin Back-Office Gateway (AdminLoginForm)', () => {
    it('submits admin credentials with isAdminLogin: true', async () => {
      mockLogin.mockResolvedValueOnce({
        user: { id: 'adm-1', username: 'admin_demo', roles: ['admin_tu'] },
        destination: 'admin',
        mfa_required: false,
      });

      render(<AdminLoginForm />);

      const idInput = screen.getByPlaceholderText(/admin \/ bendahara@labschool.sch.id/i);
      const passInput = screen.getByPlaceholderText(/••••••••••••/i);

      fireEvent.change(idInput, { target: { value: 'admin_demo' } });
      fireEvent.change(passInput, { target: { value: 'AdminPass123!' } });

      fireEvent.click(screen.getByRole('button', { name: /Masuk Back-Office/i }));

      await waitFor(() => {
        expect(mockLogin).toHaveBeenCalledWith({
          identifier: 'admin_demo',
          password: 'AdminPass123!',
          platform: 'web',
          isAdminLogin: true,
        });
        expect(mockPush).toHaveBeenCalledWith('/admin');
      });
    });

    it('handles MFA challenge and submits TOTP verification code', async () => {
      mockLogin.mockResolvedValueOnce({
        user: null,
        destination: null,
        mfa_required: true,
        mfa_token: 'mfa-temp-token-999',
      });

      render(<AdminLoginForm />);

      const idInput = screen.getByPlaceholderText(/admin \/ bendahara@labschool.sch.id/i);
      const passInput = screen.getByPlaceholderText(/••••••••••••/i);

      fireEvent.change(idInput, { target: { value: 'super_admin' } });
      fireEvent.change(passInput, { target: { value: 'AdminPass123!' } });
      fireEvent.click(screen.getByRole('button', { name: /Masuk Back-Office/i }));

      await waitFor(() => {
        expect(screen.getByText('Verifikasi Dua Faktor')).toBeInTheDocument();
      });

      mockLogin.mockResolvedValueOnce({
        user: { id: 'adm-super', username: 'super_admin', roles: ['super_admin'] },
        destination: 'admin',
        mfa_required: false,
      });

      const totpInput = screen.getByPlaceholderText(/123456 atau XXXX-XXXX/i);
      fireEvent.change(totpInput, { target: { value: '123456' } });
      fireEvent.click(screen.getByRole('button', { name: 'Verifikasi' }));

      await waitFor(() => {
        expect(mockLogin).toHaveBeenCalledWith({
          mfa_token: 'mfa-temp-token-999',
          totp_code: '123456',
          recovery_code: undefined,
          platform: 'web',
          isAdminLogin: true,
        });
        expect(mockPush).toHaveBeenCalledWith('/admin');
      });
    });
  });

  describe('3. Admin Layout 404 Guard for Unauthenticated Access', () => {
    it('renders 404 Not Found without redirecting to /masuk when unauthenticated', () => {
      (useAuth as unknown as ReturnType<typeof vi.fn>).mockReturnValue({
        user: null,
        isLoading: false,
        isAuthenticated: false,
      });

      render(
        <QueryClientProvider client={queryClient}>
          <AdminLayout>
            <div>Dashboard Rahasia Admin</div>
          </AdminLayout>
        </QueryClientProvider>
      );

      // Verify standard 404 is rendered
      expect(screen.getByText('404')).toBeInTheDocument();
      expect(screen.getByText('Halaman Tidak Ditemukan')).toBeInTheDocument();
      expect(screen.queryByText('Dashboard Rahasia Admin')).not.toBeInTheDocument();
      // Verify router.replace was NOT called to leak redirect to /masuk
      expect(mockReplace).not.toHaveBeenCalled();
    });
  });
});
