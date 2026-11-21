import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import { PortalShell } from '@/components/shell/portal/portal-shell';
import SarprasRedirectPage from '@/app/(portal)/portal/guru/sarpras/page';
import * as authProviderModule from '@/providers/auth-provider';
import * as navigationModule from 'next/navigation';
import nextConfig from '../../next.config.mjs';

vi.mock('@/providers/auth-provider', () => ({
  useAuth: vi.fn(),
}));

vi.mock('next/navigation', () => ({
  usePathname: vi.fn(),
  useRouter: vi.fn(() => ({
    replace: vi.fn(),
  })),
}));

describe('FE-GURU-ORPHAN-04: Tautkan Perizinan & Siswa, Konsolidasi Sarpras ke Fasilitas', () => {
  const mockReplace = vi.fn();

  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(navigationModule.useRouter).mockReturnValue({
      replace: mockReplace,
    } as any);
  });

  describe('1. Portal Shell Teacher Navigation Links (AC1 & AC2)', () => {
    it('FE-GURU-ORPHAN-04: sidebar portal guru memuat link aktif ke Data Siswa & Poin dan Perizinan Siswa', () => {
      vi.mocked(navigationModule.usePathname).mockReturnValue('/portal/guru');
      vi.mocked(authProviderModule.useAuth).mockReturnValue({
        user: {
          id: 'tch-1',
          username: 'guru_matematika',
          roles: ['teacher'],
          profile: { name: 'Bambang Sudarsono, M.Pd' },
        },
      } as any);

      render(
        <PortalShell>
          <div>Konten Guru</div>
        </PortalShell>
      );

      // Check Data Siswa & Poin
      const siswaLink = screen.getAllByRole('link', { name: /Data Siswa & Poin/i });
      expect(siswaLink.length).toBeGreaterThan(0);
      expect(siswaLink[0]).toHaveAttribute('href', '/portal/guru/siswa');

      // Check Perizinan Siswa
      const perizinanLink = screen.getAllByRole('link', { name: /Perizinan Siswa/i });
      expect(perizinanLink.length).toBeGreaterThan(0);
      expect(perizinanLink[0]).toHaveAttribute('href', '/portal/guru/perizinan');

      // Check Fasilitas & Sarpras
      const fasilitasLink = screen.getAllByRole('link', { name: /Fasilitas & Sarpras/i });
      expect(fasilitasLink.length).toBeGreaterThan(0);
      expect(fasilitasLink[0]).toHaveAttribute('href', '/portal/guru/fasilitas');
    });
  });

  describe('2. Sarpras Consolidation & Redirect (AC3)', () => {
    it('FE-GURU-ORPHAN-04: komponen sarpras mengalihkan navigasi ke /portal/guru/fasilitas', () => {
      render(<SarprasRedirectPage />);
      expect(mockReplace).toHaveBeenCalledWith('/portal/guru/fasilitas');
    });

    it('FE-GURU-ORPHAN-04: next.config.mjs mengonfigurasi redirect sarpras ke fasilitas', async () => {
      const resolvedConfig =
        typeof (nextConfig as any) === 'function'
          ? (nextConfig as any)('phase-development-server')
          : (nextConfig as any);

      expect(typeof resolvedConfig.redirects).toBe('function');
      const redirects = await resolvedConfig.redirects();

      const guruSarpras = redirects.find((r: any) => r.source === '/guru/sarpras');
      expect(guruSarpras).toBeDefined();
      expect(guruSarpras!.destination).toBe('/portal/guru/fasilitas');

      const portalGuruSarpras = redirects.find((r: any) => r.source === '/portal/guru/sarpras');
      expect(portalGuruSarpras).toBeDefined();
      expect(portalGuruSarpras!.destination).toBe('/portal/guru/fasilitas');
    });
  });
});
