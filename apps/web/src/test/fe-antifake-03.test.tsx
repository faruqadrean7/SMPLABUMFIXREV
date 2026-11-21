import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import KepsekDashboardPage from '@/app/(portal)/portal/kepsek/page';
import * as authProviderModule from '@/providers/auth-provider';
import * as executiveModule from '@/features/executive';

vi.mock('@/providers/auth-provider', () => ({
  useAuth: vi.fn(),
}));

vi.mock('@/features/executive', () => ({
  useExecutiveOverview: vi.fn(),
}));

describe('FE-ANTIFAKE-03: Kuota PPDB Kepsek Tanpa Angka Karangan', () => {
  const baseMetrics = {
    totalStudentsCount: 650,
    totalStaffCount: 48,
    studentAttendanceTodayPercentage: 96,
    activeEarlyWarningsCount: 2,
    openTicketsCount: 1,
    pendingApprovalsCount: 3,
    ppdbEnrolledCount: 85,
    ppdbTargetQuota: 0,
    academicAverages: [
      { grade: 'Kelas VII', averageScore: 84.5 },
      { grade: 'Kelas VIII', averageScore: 86.2 },
      { grade: 'Kelas IX', averageScore: 88.0 },
    ],
  };

  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(authProviderModule.useAuth).mockReturnValue({
      user: {
        id: 'usr-1',
        username: 'kepsek_utama',
        roles: ['principal'],
        permissions: ['executive.approval.manage', 'reports.view'],
        profile: { name: 'Dra. Endang Sulistyowati, M.Pd' },
      },
    } as any);
  });

  it('FE-ANTIFAKE-03 (AC1 & AC2): saat ppdbTargetQuota <= 0, tidak memakai angka karangan 240, menampilkan "Kuota Belum Ditetapkan" tanpa % fiktif', () => {
    vi.mocked(executiveModule.useExecutiveOverview).mockReturnValue({
      data: {
        ...baseMetrics,
        ppdbTargetQuota: 0,
        ppdbEnrolledCount: 42,
      },
      isLoading: false,
      isError: false,
      refetch: vi.fn(),
    } as any);

    render(<KepsekDashboardPage />);

    // 1. Must NOT display the fabricated 240 quota
    expect(screen.queryByText(/240/i)).not.toBeInTheDocument();

    // 2. Must show honest badge
    expect(screen.getByText('Kuota Belum Ditetapkan')).toBeInTheDocument();

    // 3. Must NOT show fake percentage
    expect(screen.queryByText(/% Tercapai/i)).not.toBeInTheDocument();

    // 4. Must show actual enrolled count and honest status
    expect(screen.getByText('42')).toBeInTheDocument();
    expect(screen.getByText('(Kuota belum ditetapkan)')).toBeInTheDocument();
    expect(
      screen.getByText('Target kuota penerimaan siswa baru belum diatur oleh panitia PPDB.')
    ).toBeInTheDocument();
  });

  it('FE-ANTIFAKE-03 (AC3): saat ppdbTargetQuota > 0 nyata, persentase (%) dan kuota resmi dihitung dan ditampilkan', () => {
    vi.mocked(executiveModule.useExecutiveOverview).mockReturnValue({
      data: {
        ...baseMetrics,
        ppdbTargetQuota: 200,
        ppdbEnrolledCount: 100,
      },
      isLoading: false,
      isError: false,
      refetch: vi.fn(),
    } as any);

    render(<KepsekDashboardPage />);

    // 1. Percentage badge is shown
    expect(screen.getByText('50% Tercapai')).toBeInTheDocument();

    // 2. Real quota is shown
    expect(screen.getByText('100')).toBeInTheDocument();
    expect(screen.getByText('/ 200 Kuota')).toBeInTheDocument();

    // 3. "Kuota Belum Ditetapkan" is not shown
    expect(screen.queryByText('Kuota Belum Ditetapkan')).not.toBeInTheDocument();
  });
});
