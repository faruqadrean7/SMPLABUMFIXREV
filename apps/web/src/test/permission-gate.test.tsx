import React from 'react';
import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import { PermissionGate } from '@/components/shared/permission-gate';
import * as AuthProviderModule from '@/providers/auth-provider';
import { User } from '@/types/auth';

describe('PermissionGate Component', () => {
  it('renders children when user has required permission', () => {
    const mockUser: User = {
      id: '1',
      schoolId: '1',
      username: 'guru1',
      email: null,
      phone: null,
      status: 'active',
      mustChangePassword: false,
      lastLoginAt: null,
      roles: ['teacher'],
      permissions: ['academic.journal.write'],
      profile: {},
    };

    vi.spyOn(AuthProviderModule, 'useAuth').mockReturnValue({
      user: mockUser,
      isLoading: false,
      isAuthenticated: true,
      activeRole: 'teacher',
      login: vi.fn(),
      logout: vi.fn(),
      checkAuth: vi.fn(),
      setActiveRole: vi.fn(),
    });

    render(
      <PermissionGate permission="academic.journal.write" fallback={<div>Akses Ditolak</div>}>
        <div>Tombol Tulis Jurnal</div>
      </PermissionGate>
    );

    expect(screen.getByText('Tombol Tulis Jurnal')).toBeInTheDocument();
    expect(screen.queryByText('Akses Ditolak')).not.toBeInTheDocument();
  });

  it('renders fallback when user lacks required permission', () => {
    const mockUser: User = {
      id: '2',
      schoolId: '1',
      username: 'siswa1',
      email: null,
      phone: null,
      status: 'active',
      mustChangePassword: false,
      lastLoginAt: null,
      roles: ['student'],
      permissions: ['cbt.exam.take'],
      profile: {},
    };

    vi.spyOn(AuthProviderModule, 'useAuth').mockReturnValue({
      user: mockUser,
      isLoading: false,
      isAuthenticated: true,
      activeRole: 'student',
      login: vi.fn(),
      logout: vi.fn(),
      checkAuth: vi.fn(),
      setActiveRole: vi.fn(),
    });

    render(
      <PermissionGate permission="academic.journal.write" fallback={<div>Akses Ditolak</div>}>
        <div>Tombol Tulis Jurnal</div>
      </PermissionGate>
    );

    expect(screen.queryByText('Tombol Tulis Jurnal')).not.toBeInTheDocument();
    expect(screen.getByText('Akses Ditolak')).toBeInTheDocument();
  });
});
