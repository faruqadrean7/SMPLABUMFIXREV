import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import * as NavigationModule from 'next/navigation';
import * as AuthProviderModule from '@/providers/auth-provider';
import { getPortalRoleOptions, canAccessShell } from '@/lib/rbac/evaluator';
import { ADMIN_NAV_SECTIONS } from '@/lib/rbac/navigation';
import { getStudentNavLinks } from '@/lib/student-navigation';
import { PUBLIC_HEADER_NAV_ITEMS, FOOTER_PORTAL_LINKS } from '@/features/public-site/content/navigation';
import { reportsApi } from '@/features/reports/reports-api';
import { academicYearsApi } from '@/features/master/academic-years-api';
import AdminDashboardPage from '@/app/(admin)/admin/page';
import { AdminSidebar } from '@/components/shell/admin/admin-sidebar';
import TeacherLayout from '@/app/(portal)/portal/guru/layout';
import { PortalShell } from '@/components/shell/portal/portal-shell';
import { User } from '@/types/auth';

vi.mock('next/navigation', () => ({
  usePathname: vi.fn(() => '/admin'),
  useRouter: vi.fn(() => ({ push: vi.fn(), replace: vi.fn() })),
}));

vi.mock('@/providers/auth-provider', () => ({
  useAuth: vi.fn(),
}));

vi.mock('@/components/shared/role-guard', () => ({
  RoleGuard: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

vi.mock('@/lib/use-teacher-data', () => ({
  useTeacherClasses: () => ({ data: [{ id: 'cls-7a', name: '7A', studentCount: 30 }] }),
  useTeacherSubjects: () => ({ data: [{ id: 'sbj-1', name: 'Matematika', code: 'MTK' }] }),
}));

describe('Tier 3-2 Web Shell, RBAC & CMS Navigation', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.spyOn(academicYearsApi, 'getActive').mockResolvedValue({
      academicYear: { id: 'ay-1', name: '2026/2027', startDate: '2026-07-01', endDate: '2027-06-30', isActive: true },
      semester: { id: 'sem-1', academicYearId: 'ay-1', name: 'Ganjil', startDate: '2026-07-01', endDate: '2026-12-31', isActive: true },
    });
  });

  it('P0-29: getPortalRoleOptions mencakup panel admin untuk seluruh ADMIN_SHELL_ROLES dan navigation.ts bebas permission hantu', () => {
    for (const role of ['admin_tu', 'principal', 'vice_principal', 'facility_staff', 'librarian', 'bendahara', 'kurikulum']) {
      const opts = getPortalRoleOptions([role]);
      expect(opts.some((o) => o.portal === 'admin' && o.href === '/admin')).toBe(true);
      expect(canAccessShell([role], 'admin')).toBe(true);
    }

    const serializedNav = JSON.stringify(ADMIN_NAV_SECTIONS);
    expect(serializedNav).not.toContain('master.guardian.manage');
    expect(serializedNav).not.toContain('cbt.exam.read');
  });

  it('P0-28: Beranda /admin adaptif tidak memanggil getExecutiveDashboard() untuk role operasional non-eksekutif', () => {
    const execSpy = vi.spyOn(reportsApi, 'getExecutiveDashboard').mockResolvedValue({ data: {} as any } as any);

    const bendaharaUser: User = {
      id: 'u-ben',
      schoolId: 'sch-1',
      username: 'bendahara_u',
      email: 'bendahara@test.local',
      phone: null,
      status: 'active',
      mustChangePassword: false,
      lastLoginAt: null,
      roles: ['bendahara'],
      permissions: ['finance.fee.manage', 'finance.invoice.manage', 'finance.payment.verify', 'finance.report.view', 'reports.view'],
      profile: { name: 'Ibu Bendahara' },
    };

    vi.mocked(AuthProviderModule.useAuth).mockReturnValue({
      user: bendaharaUser,
      isAuthenticated: true,
      isLoading: false,
      activeRole: 'admin',
      login: vi.fn(),
      logout: vi.fn(),
      checkAuth: vi.fn(),
      setActiveRole: vi.fn(),
    });

    render(<AdminDashboardPage />);
    expect(execSpy).not.toHaveBeenCalled();
    expect(screen.getByTestId('dashboard-adaptive-workspace')).toBeInTheDocument();
    expect(screen.getByText('Manajemen Keuangan')).toBeInTheDocument();
  });

  it('P1-35: AdminSidebar auto-expand dan highlight grup Sarana & Prasarana ketika berada di /admin/assets, serta role principal melihat submenu read-only PPDB & Kesiswaan', () => {
    vi.mocked(NavigationModule.usePathname).mockReturnValue('/admin/assets');

    const principalUser: User = {
      id: 'u-kep',
      schoolId: 'sch-1',
      username: 'kepsek',
      email: 'kepsek@test.local',
      phone: null,
      status: 'active',
      mustChangePassword: false,
      lastLoginAt: null,
      roles: ['principal'],
      permissions: ['ppdb.applicant.read', 'student.point.read', 'facility.asset.manage'],
      profile: { name: 'Kepala Sekolah' },
    };

    vi.mocked(AuthProviderModule.useAuth).mockReturnValue({
      user: principalUser,
      isAuthenticated: true,
      isLoading: false,
      activeRole: 'admin',
      login: vi.fn(),
      logout: vi.fn(),
      checkAuth: vi.fn(),
      setActiveRole: vi.fn(),
    });

    render(<AdminSidebar />);
    expect(screen.getByRole('link', { name: 'Aset & Inventaris QR' })).toHaveAttribute('href', '/admin/assets');
    fireEvent.click(screen.getByRole('button', { name: /Penerimaan Siswa \(PPDB\)/i }));
    expect(screen.getByRole('link', { name: 'Verifikasi Berkas Calon Siswa' })).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: /Kesiswaan & Disiplin/i }));
    expect(screen.getByRole('link', { name: 'Poin Kedisiplinan & Prestasi' })).toBeInTheDocument();
  });

  it('P0-27 & P1-33 & P1-34: TeacherLayout memakai rute /portal/guru/* lengkap termasuk halaman orphan dan T.A./mapel dinamis tanpa hardcoded Informatika', async () => {
    vi.mocked(NavigationModule.usePathname).mockReturnValue('/portal/guru/presensi');
    const teacherUser: User = {
      id: 'u-guru',
      schoolId: 'sch-1',
      username: 'guru_mtk',
      email: 'guru@test.local',
      phone: null,
      status: 'active',
      mustChangePassword: false,
      lastLoginAt: null,
      roles: ['teacher', 'homeroom'],
      permissions: [],
      profile: { fullName: 'Pak Ahmad Matematika' },
    };

    vi.mocked(AuthProviderModule.useAuth).mockReturnValue({
      user: teacherUser,
      isAuthenticated: true,
      isLoading: false,
      activeRole: 'guru',
      login: vi.fn(),
      logout: vi.fn(),
      checkAuth: vi.fn(),
      setActiveRole: vi.fn(),
    });

    render(
      <TeacherLayout>
        <div>Body Guru</div>
      </TeacherLayout>
    );

    expect(screen.queryByText('Informatika')).not.toBeInTheDocument();
    expect(screen.getByText('Matematika')).toBeInTheDocument();
    expect(await screen.findByText('T.A. 2026/2027 Ganjil')).toBeInTheDocument();

    for (const href of [
      '/portal/guru/presensi',
      '/portal/guru/wali-kelas',
      '/portal/guru/izin-cuti',
      '/portal/guru/presensi-saya',
      '/portal/guru/slip-gaji',
      '/portal/notifikasi',
    ]) {
      expect(document.querySelector(`a[href="${href}"]`)).not.toBeNull();
    }

    const studentHrefs = getStudentNavLinks('reguler').map((l) => l.href);
    expect(studentHrefs).toContain('/portal/siswa/tagihan');
    expect(studentHrefs).toContain('/portal/notifikasi');
  });

  it('P1-36 & P1-37: PortalShell hanya merender shell Staf/Kepsek/Notifikasi dan navigasi publik ringkas 6 item tanpa /guru', () => {
    vi.mocked(NavigationModule.usePathname).mockReturnValue('/portal/staf');
    vi.mocked(AuthProviderModule.useAuth).mockReturnValue({
      user: {
        id: 'u-staf',
        schoolId: 'sch-1',
        username: 'staf_tu',
        email: 'staf@test.local',
        phone: null,
        status: 'active',
        mustChangePassword: false,
        lastLoginAt: null,
        roles: ['admin_tu'],
        permissions: [],
        profile: { name: 'Staf TU' },
      },
      isAuthenticated: true,
      isLoading: false,
      activeRole: 'staf',
      login: vi.fn(),
      logout: vi.fn(),
      checkAuth: vi.fn(),
      setActiveRole: vi.fn(),
    });

    render(
      <PortalShell>
        <div>Konten Staf</div>
      </PortalShell>
    );
    expect(screen.queryByText('Tugas & Penilaian')).not.toBeInTheDocument();

    expect(PUBLIC_HEADER_NAV_ITEMS).toHaveLength(6);
    const profilSubs = PUBLIC_HEADER_NAV_ITEMS.find((i) => i.label === 'Profil')?.children || [];
    expect(profilSubs.some((c) => c.href === '/pendidik')).toBe(true);
    expect(FOOTER_PORTAL_LINKS.some((l) => l.href === '/portal/guru')).toBe(true);
    expect(FOOTER_PORTAL_LINKS.some((l) => l.href === '/guru')).toBe(false);
  });
});
