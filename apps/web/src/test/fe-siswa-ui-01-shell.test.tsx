import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render } from '@testing-library/react';
import * as StudentDataHooks from '@/lib/use-student-data';
import StudentLayout from '@/app/(portal)/portal/siswa/layout';

vi.mock('next/navigation', () => ({
  usePathname: () => '/portal/siswa/dashboard',
  useRouter: () => ({
    push: vi.fn(),
    replace: vi.fn(),
  }),
}));

vi.mock('@/providers/auth-provider', () => ({
  useAuth: () => ({
    user: { username: 'siswa', roles: ['student'], profile: { name: 'Raka', nis: '26001', className: 'VII-A' } },
    logout: vi.fn(),
  }),
}));

// RoleGuard just renders children in tests so we can inspect the shell.
vi.mock('@/components/shared/role-guard', () => ({
  RoleGuard: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

describe('FE-SISWA-UI-01: student portal shell', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.spyOn(StudentDataHooks, 'useStudentDashboard').mockReturnValue({
      data: { name: 'Raka', nis: '26001', className: 'VII-A', programType: 'reguler', attendanceRate: 96, photoUrl: null },
    } as any);
  });

  it('FE-SISWA-UI-01-shell: wraps student portal in .portal-siswa scope', () => {
    const { container } = render(<StudentLayout><div>isi</div></StudentLayout>);
    expect(container.querySelector('.portal-siswa')).toBeTruthy();
  });

  it('FE-SISWA-UI-01-shell: still renders student nav links', () => {
    const { getByText } = render(<StudentLayout><div>isi</div></StudentLayout>);
    expect(getByText('Ringkasan & Beranda')).toBeTruthy();
  });

  it('FE-SISWA-UI-01-shell: renders with missing avatar and a very long name using initials fallback', () => {
    const longName = 'Nama Siswa Yang Sangat Panjang Sekali '.repeat(4).trim();
    vi.spyOn(StudentDataHooks, 'useStudentDashboard').mockReturnValue({
      data: { name: longName, nis: '26001', className: 'VII-A', programType: 'reguler', attendanceRate: 96, photoUrl: null },
    } as any);
    const { container } = render(<StudentLayout><div>isi</div></StudentLayout>);
    // No <img> avatar when photoUrl is null; initials span rendered instead.
    expect(container.querySelector('img')).toBeNull();
    expect(container.querySelector('.portal-siswa')).toBeTruthy();
  });
});
