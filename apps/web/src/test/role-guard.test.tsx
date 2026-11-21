import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import { RoleGuard } from '@/components/shared/role-guard';
import * as AuthProviderModule from '@/providers/auth-provider';
import { User } from '@/types/auth';

const pushMock = vi.fn();
vi.mock('next/navigation', () => ({
  useRouter: () => ({
    push: pushMock,
    replace: vi.fn(),
  }),
}));

describe('GAP-05: Sub-Portal RoleGuard Reusable Component & Access Matrix', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  const makeUser = (roles: string[]): User => ({
    id: `usr-${roles.join('-')}`,
    schoolId: 'sch-01',
    username: `user_${roles.join('_')}`,
    email: 'user@labschool.sch.id',
    phone: null,
    status: 'active',
    mustChangePassword: false,
    lastLoginAt: '2026-10-14T08:00:00Z',
    roles,
    permissions: [],
    profile: { name: 'Pengguna Uji' },
  });

  describe('1. Loading & Anti-Flicker States', () => {
    it('shows loading spinner and never renders children or AccessDenied while auth is resolving', () => {
      vi.spyOn(AuthProviderModule, 'useAuth').mockReturnValue({
        user: null,
        isLoading: true,
        isAuthenticated: false,
        activeRole: null,
        login: vi.fn(),
        logout: vi.fn(),
        checkAuth: vi.fn(),
        setActiveRole: vi.fn(),
      });

      render(
        <RoleGuard portal="guru" loadingMessage="Memverifikasi izin dewan guru...">
          <div data-testid="protected-content">Konten Rahasia Guru</div>
        </RoleGuard>
      );

      // Loading state visible
      expect(screen.getAllByText('Memverifikasi izin dewan guru...').length).toBeGreaterThanOrEqual(1);
      // Protected content not rendered
      expect(screen.queryByTestId('protected-content')).not.toBeInTheDocument();
      // AccessDenied not rendered
      expect(screen.queryByText(/Akses Ditolak/i)).not.toBeInTheDocument();
    });
  });

  describe('2. Cross-Portal 4 Roles x 4 Portals Isolation Matrix', () => {
    const matrix: Array<{
      roleName: string;
      roles: string[];
      portal: 'siswa' | 'ortu' | 'guru' | 'staf';
      shouldAllow: boolean;
    }> = [
      // Siswa
      { roleName: 'student', roles: ['student'], portal: 'siswa', shouldAllow: true },
      { roleName: 'student', roles: ['student'], portal: 'guru', shouldAllow: false },
      { roleName: 'student', roles: ['student'], portal: 'ortu', shouldAllow: false },
      { roleName: 'student', roles: ['student'], portal: 'staf', shouldAllow: false },

      // Guru
      { roleName: 'teacher', roles: ['teacher'], portal: 'guru', shouldAllow: true },
      { roleName: 'teacher', roles: ['teacher'], portal: 'siswa', shouldAllow: false },
      { roleName: 'teacher', roles: ['teacher'], portal: 'ortu', shouldAllow: false },
      { roleName: 'teacher', roles: ['teacher'], portal: 'staf', shouldAllow: false },

      // Ortu
      { roleName: 'guardian', roles: ['guardian'], portal: 'ortu', shouldAllow: true },
      { roleName: 'guardian', roles: ['guardian'], portal: 'siswa', shouldAllow: false },
      { roleName: 'guardian', roles: ['guardian'], portal: 'guru', shouldAllow: false },
      { roleName: 'guardian', roles: ['guardian'], portal: 'staf', shouldAllow: false },

      // Staf TU
      { roleName: 'admin_tu', roles: ['admin_tu'], portal: 'staf', shouldAllow: true },
      { roleName: 'admin_tu', roles: ['admin_tu'], portal: 'siswa', shouldAllow: false },
      { roleName: 'admin_tu', roles: ['admin_tu'], portal: 'guru', shouldAllow: false },
      { roleName: 'admin_tu', roles: ['admin_tu'], portal: 'ortu', shouldAllow: false },
    ];

    matrix.forEach(({ roleName, roles, portal, shouldAllow }) => {
      it(`${roleName} accessing portal/${portal} -> ${shouldAllow ? 'ALLOWS' : 'DENIES'}`, () => {
        vi.spyOn(AuthProviderModule, 'useAuth').mockReturnValue({
          user: makeUser(roles),
          isLoading: false,
          isAuthenticated: true,
          activeRole: portal,
          login: vi.fn(),
          logout: vi.fn(),
          checkAuth: vi.fn(),
          setActiveRole: vi.fn(),
        });

        render(
          <RoleGuard
            portal={portal}
            deniedTitle={`Akses Portal ${portal.toUpperCase()} Ditolak`}
            deniedDescription={`Anda tidak memiliki izin membuka portal ${portal}.`}
          >
            <div data-testid={`content-${portal}`}>Konten Sah {portal}</div>
          </RoleGuard>
        );

        if (shouldAllow) {
          expect(screen.getByTestId(`content-${portal}`)).toBeInTheDocument();
          expect(screen.queryByText(/Akses Portal.*Ditolak/i)).not.toBeInTheDocument();
        } else {
          expect(screen.queryByTestId(`content-${portal}`)).not.toBeInTheDocument();
          expect(
            screen.getByText(`Akses Portal ${portal.toUpperCase()} Ditolak`)
          ).toBeInTheDocument();
        }
      });
    });
  });
});
