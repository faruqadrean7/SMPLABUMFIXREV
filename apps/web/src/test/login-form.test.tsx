import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { LoginForm } from '@/features/auth/login-form';
import * as AuthProviderModule from '@/providers/auth-provider';
import { ApiClientError } from '@/lib/api/errors';

// Mock useRouter and useSearchParams
const pushMock = vi.fn();
vi.mock('next/navigation', () => ({
  useRouter: () => ({
    push: pushMock,
    replace: vi.fn(),
  }),
  useSearchParams: () => ({
    get: vi.fn().mockReturnValue(null),
  }),
}));

describe('LoginForm Component', () => {
  const loginMock = vi.fn();

  beforeEach(() => {
    vi.clearAllMocks();
    vi.spyOn(AuthProviderModule, 'useAuth').mockReturnValue({
      user: null,
      isLoading: false,
      isAuthenticated: false,
      activeRole: null,
      login: loginMock,
      logout: vi.fn(),
      checkAuth: vi.fn(),
      setActiveRole: vi.fn(),
    });
  });

  it('renders login form inputs and submit button', () => {
    render(<LoginForm />);

    expect(screen.getByPlaceholderText('NISN / NIS / NIP / Username / Email')).toBeInTheDocument();
    expect(screen.getByPlaceholderText('Masukkan kata sandi akun')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /Masuk ke Portal Terpadu/i })).toBeInTheDocument();
  });

  it('shows validation errors when submitting empty form', async () => {
    render(<LoginForm />);

    const submitBtn = screen.getByRole('button', { name: /Masuk ke Portal Terpadu/i });
    fireEvent.click(submitBtn);

    await waitFor(() => {
      expect(screen.getByText(/Identitas.*wajib diisi/i)).toBeInTheDocument();
      expect(screen.getByText(/Kata sandi wajib diisi/i)).toBeInTheDocument();
    });

    expect(loginMock).not.toHaveBeenCalled();
  });

  it('handles successful login and redirects to resolved destination', async () => {
    loginMock.mockResolvedValueOnce({
      user: { id: '1', roles: ['student'] },
      destination: 'siswa',
    });

    render(<LoginForm />);

    fireEvent.change(screen.getByPlaceholderText('NISN / NIS / NIP / Username / Email'), {
      target: { value: '20241001' },
    });
    fireEvent.change(screen.getByPlaceholderText('Masukkan kata sandi akun'), {
      target: { value: 'password123' },
    });

    fireEvent.click(screen.getByRole('button', { name: /Masuk ke Portal Terpadu/i }));

    await waitFor(() => {
      expect(loginMock).toHaveBeenCalledWith({
        identifier: '20241001',
        password: 'password123',
        platform: 'web',
      });
      expect(pushMock).toHaveBeenCalledWith('/portal/siswa');
    });
  });

  it('displays Indonesian error message on invalid credentials without leaking internal secrets', async () => {
    loginMock.mockRejectedValueOnce(
      new ApiClientError(
        'Kredensial yang diberikan tidak cocok dengan data kami.',
        'UNAUTHENTICATED',
        401
      )
    );

    render(<LoginForm />);

    fireEvent.change(screen.getByPlaceholderText('NISN / NIS / NIP / Username / Email'), {
      target: { value: 'wrong_user' },
    });
    fireEvent.change(screen.getByPlaceholderText('Masukkan kata sandi akun'), {
      target: { value: 'wrong_pass' },
    });

    fireEvent.click(screen.getByRole('button', { name: /Masuk ke Portal Terpadu/i }));

    await waitFor(() => {
      expect(
        screen.getByText('Kredensial yang diberikan tidak cocok dengan data kami.')
      ).toBeInTheDocument();
    });
  });
});
