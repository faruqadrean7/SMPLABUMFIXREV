import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import { LoginForm } from '@/features/auth/login-form';
import { ADMIN_NAV_SECTIONS } from '@/lib/rbac/navigation';
import { PortalShell } from '@/components/shell/portal/portal-shell';
import * as AuthProviderModule from '@/providers/auth-provider';

// Mock useRouter, usePathname, and useSearchParams
const pushMock = vi.fn();
let currentPathname = '/portal/siswa';

vi.mock('next/navigation', () => ({
  useRouter: () => ({
    push: pushMock,
    replace: vi.fn(),
  }),
  usePathname: () => currentPathname,
  useSearchParams: () => ({
    get: vi.fn().mockReturnValue(null),
  }),
}));

describe('BATCH 2: Navigation, Persona Selection & Route Remediation', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    currentPathname = '/portal/siswa';
    vi.spyOn(AuthProviderModule, 'useAuth').mockReturnValue({
      user: { id: 'u-1', roles: ['student'], profile: { name: 'Siswa Test' } } as any,
      isLoading: false,
      isAuthenticated: true,
      activeRole: 'student',
      login: vi.fn(),
      logout: vi.fn(),
      checkAuth: vi.fn(),
      setActiveRole: vi.fn(),
    });
  });

  describe('1. GAP-AUTH-01: Persona Selector & Dynamic Credential Labels on /masuk', () => {
    it('renders persona selector cards for Siswa, Orang Tua, Guru, and Staf', () => {
      render(<LoginForm />);

      expect(screen.getByRole('button', { name: /siswa/i })).toBeInTheDocument();
      expect(screen.getByRole('button', { name: /orang tua/i })).toBeInTheDocument();
      expect(screen.getByRole('button', { name: /guru/i })).toBeInTheDocument();
      expect(screen.getByRole('button', { name: /staf/i })).toBeInTheDocument();
    });

    it('adapts placeholder and label dynamically when clicking a persona card', () => {
      render(<LoginForm />);

      // Klik Persona Siswa
      fireEvent.click(screen.getByRole('button', { name: /siswa/i }));
      expect(screen.getByText(/NISN \/ NIS \/ Username/i)).toBeInTheDocument();

      // Klik Persona Guru
      fireEvent.click(screen.getByRole('button', { name: /guru/i }));
      expect(screen.getByText(/NIP \/ Email Sekolah \/ Username/i)).toBeInTheDocument();

      // Klik Persona Orang Tua
      fireEvent.click(screen.getByRole('button', { name: /orang tua/i }));
      expect(screen.getByText(/Email \/ No\. WhatsApp \/ Username/i)).toBeInTheDocument();

      // Klik Persona Staf
      fireEvent.click(screen.getByRole('button', { name: /staf/i }));
      expect(screen.getByText(/NIP \/ Username Staf/i)).toBeInTheDocument();
    });
  });

  describe('2. GAP-NAV-01: Orphan Routes Integration in Admin & Student Navigation', () => {
    it('contains teaching-materials, assignments, leave-requests, staff-attendance, and early-warnings in ADMIN_NAV_SECTIONS', () => {
      const allItems = ADMIN_NAV_SECTIONS.flatMap((section) => [
        ...section.items,
        ...section.items.flatMap((item) => item.children || []),
      ]);
      const allHrefs = allItems.map((item) => item.href);

      // KBM & Pembelajaran
      expect(allHrefs).toContain('/admin/academic/teaching-materials');
      expect(allHrefs).toContain('/admin/academic/assignments');
      expect(allHrefs).toContain('/admin/academic/leave-requests');
      expect(allHrefs).toContain('/admin/academic/staff-attendance');

      // Bimbingan Konseling & Early Warnings
      expect(allHrefs).toContain('/admin/counseling/early-warnings');
    });

    it('renders Tugas, Konseling, and Buku Poin in Student Portal navigation items', () => {
      currentPathname = '/portal/siswa';
      render(
        <PortalShell>
          <div>Konten Siswa</div>
        </PortalShell>
      );

      // Pastikan tautan Tugas, Konseling, dan Buku Poin tampil di bilah navigasi siswa
      const navLinks = screen.getAllByRole('link');
      const hrefs = navLinks.map((link) => link.getAttribute('href'));

      expect(hrefs).toContain('/portal/siswa/tugas');
      expect(hrefs).toContain('/portal/siswa/konseling');
      expect(hrefs).toContain('/portal/siswa/poin');
    });
  });

  describe('3. GAP-DUP-01: Consolidation of Duplicate & Deprecated Routes', () => {
    it('verifies next.config.mjs redirects duplicate/stub routes to canonical destinations', async () => {
      // Import next.config.mjs
      const nextConfigModule = await import('../../next.config.mjs');
      const nextConfig: any = nextConfigModule.default;
      const redirects = await nextConfig.redirects();
      expect(redirects).toBeDefined();

      const userRedirect = redirects.find((r: any) => r.source === '/admin/users');
      expect(userRedirect).toBeDefined();
      expect(userRedirect?.destination).toBe('/admin/settings/users');

      const facilityLibRedirect = redirects.find((r: any) => r.source === '/admin/facilities/library');
      expect(facilityLibRedirect).toBeDefined();
      expect(facilityLibRedirect?.destination).toBe('/admin/library');

      const studentNotifRedirect = redirects.find((r: any) => r.source === '/portal/siswa/notifications');
      expect(studentNotifRedirect).toBeDefined();
      expect(studentNotifRedirect?.destination).toBe('/portal/notifikasi');

      const parentNotifRedirect = redirects.find((r: any) => r.source === '/portal/ortu/notifications');
      expect(parentNotifRedirect).toBeDefined();
      expect(parentNotifRedirect?.destination).toBe('/portal/notifikasi');
    });
  });
});
