import { describe, it, expect } from 'vitest';
import nextConfig from '../../next.config.mjs';
import {
  resolveUserDestination,
  getDestinationPath,
  canAccessShell,
  getPortalRoleOptions,
  getAccessiblePortals,
  hasPermission,
} from '@/lib/rbac/evaluator';
import { User } from '@/types/auth';

describe('SISTLABUM: Portal Kepsek & URL Aliases Routing Suite', () => {
  const resolvedConfig = typeof (nextConfig as any) === 'function' ? (nextConfig as any)('phase-development-server') : (nextConfig as any);

  describe('1. Next.js Redirects & URL Aliases Configuration', () => {
    it('mengonfigurasi redirects untuk alias portal persona sesuai spesifikasi', async () => {
      expect(typeof resolvedConfig.redirects).toBe('function');
      if (!resolvedConfig.redirects) throw new Error('resolvedConfig.redirects is undefined');

      const redirects = await resolvedConfig.redirects();
      const findRedirect = (source: string) => redirects.find((r: any) => r.source === source);

      // Alias Guru
      const guruExact = findRedirect('/guru');
      expect(guruExact).toBeDefined();
      expect(guruExact!.destination).toBe('/portal/guru');
      expect(guruExact!.permanent).toBe(false);

      const guruWildcard = findRedirect('/guru/:path*');
      expect(guruWildcard).toBeDefined();
      expect(guruWildcard!.destination).toBe('/portal/guru/:path*');

      // Alias Siswa
      const siswaExact = findRedirect('/siswa');
      expect(siswaExact).toBeDefined();
      expect(siswaExact!.destination).toBe('/portal/siswa');

      const siswaWildcard = findRedirect('/siswa/:path*');
      expect(siswaWildcard).toBeDefined();
      expect(siswaWildcard!.destination).toBe('/portal/siswa/:path*');

      // Alias Ortu
      const ortuExact = findRedirect('/ortu');
      expect(ortuExact).toBeDefined();
      expect(ortuExact!.destination).toBe('/portal/ortu');

      const ortuWildcard = findRedirect('/ortu/:path*');
      expect(ortuWildcard).toBeDefined();
      expect(ortuWildcard!.destination).toBe('/portal/ortu/:path*');

      // Alias Staf
      const stafExact = findRedirect('/staf');
      expect(stafExact).toBeDefined();
      expect(stafExact!.destination).toBe('/portal/staf');

      const stafWildcard = findRedirect('/staf/:path*');
      expect(stafWildcard).toBeDefined();
      expect(stafWildcard!.destination).toBe('/portal/staf/:path*');

      // Alias Kepsek
      const kepsekExact = findRedirect('/kepsek');
      expect(kepsekExact).toBeDefined();
      expect(kepsekExact!.destination).toBe('/portal/kepsek');

      const kepsekWildcard = findRedirect('/kepsek/:path*');
      expect(kepsekWildcard).toBeDefined();
      expect(kepsekWildcard!.destination).toBe('/portal/kepsek/:path*');

      // Memastikan TIDAK ADA alias untuk /mitra karena portal mitra ditunda
      const mitraExact = findRedirect('/mitra');
      expect(mitraExact).toBeUndefined();
    });
  });

  describe('2. Evaluasi Tujuan Login Pimpinan & Kepsek', () => {
    it('mengarahkan principal dan vice_principal ke /portal/kepsek', () => {
      expect(resolveUserDestination(['principal'])).toBe('kepsek');
      expect(getDestinationPath('kepsek')).toBe('/portal/kepsek');

      expect(resolveUserDestination(['vice_principal'])).toBe('kepsek');
      expect(getDestinationPath(resolveUserDestination(['vice_principal']))).toBe('/portal/kepsek');
    });

    it('memvalidasi hak akses shell kepsek dengan canAccessShell', () => {
      // Pimpinan sah
      expect(canAccessShell(['principal'], 'kepsek')).toBe(true);
      expect(canAccessShell(['vice_principal'], 'kepsek')).toBe(true);
      expect(canAccessShell(['super_admin'], 'kepsek')).toBe(true);

      // Peran non-pimpinan DITOLAK
      expect(canAccessShell(['teacher'], 'kepsek')).toBe(false);
      expect(canAccessShell(['homeroom'], 'kepsek')).toBe(false);
      expect(canAccessShell(['student'], 'kepsek')).toBe(false);
      expect(canAccessShell(['guardian'], 'kepsek')).toBe(false);
      expect(canAccessShell(['facility_staff'], 'kepsek')).toBe(false);
      expect(canAccessShell(['librarian'], 'kepsek')).toBe(false);
      expect(canAccessShell(['admin_tu'], 'kepsek')).toBe(false);
      expect(canAccessShell(['committee'], 'kepsek')).toBe(false);
      expect(canAccessShell(['supervisor'], 'kepsek')).toBe(false);
      expect(canAccessShell([], 'kepsek')).toBe(false);
    });

    it('menghasilkan opsi Portal Pimpinan di Role Switcher', () => {
      const options = getPortalRoleOptions(['principal']);
      const kepsekOpt = options.find((o) => o.portal === 'kepsek');
      expect(kepsekOpt).toBeDefined();
      expect(kepsekOpt?.href).toBe('/portal/kepsek');
      expect(kepsekOpt?.label).toBe('Portal Pimpinan');
    });

    it('mendukung akun multi-peran (misal Guru yang merangkap Kepala Sekolah)', () => {
      const dualRoles = ['teacher', 'principal'];
      expect(resolveUserDestination(dualRoles)).toBe('kepsek');

      const accessible = getAccessiblePortals(dualRoles);
      const portals = accessible.map((p) => p.portal);
      expect(portals).toContain('kepsek');
      expect(portals).toContain('guru');
      expect(portals).not.toContain('siswa');
      expect(portals).not.toContain('ortu');
    });
  });

  describe('3. Proteksi Akses Peran Mitra (Komite & Pengawas)', () => {
    it('tidak mengarahkan komite atau pengawas ke /admin secara otomatis', () => {
      expect(resolveUserDestination(['committee'])).toBe('portal');
      expect(resolveUserDestination(['supervisor'])).toBe('portal');
      expect(resolveUserDestination(['committee'])).not.toBe('admin');
      expect(resolveUserDestination(['supervisor'])).not.toBe('admin');
    });

    it('menolak akses otomatis ke shell admin untuk komite dan pengawas', () => {
      expect(canAccessShell(['committee'], 'admin')).toBe(false);
      expect(canAccessShell(['supervisor'], 'admin')).toBe(false);
    });
  });

  describe('4. Pemetaan Lengkap Matriks Role dan Destinasi Login', () => {
    it('memastikan setiap persona terpetakan secara sah dan aman', () => {
      expect(resolveUserDestination(['student'])).toBe('siswa');
      expect(resolveUserDestination(['guardian'])).toBe('ortu');
      expect(resolveUserDestination(['teacher'])).toBe('guru');
      expect(resolveUserDestination(['homeroom'])).toBe('guru');
      expect(resolveUserDestination(['principal'])).toBe('kepsek');
      expect(resolveUserDestination(['vice_principal'])).toBe('kepsek');
      expect(resolveUserDestination(['facility_staff'])).toBe('staf');
      expect(resolveUserDestination(['librarian'])).toBe('staf');
      expect(resolveUserDestination(['admin_tu'])).toBe('staf');
      expect(resolveUserDestination(['super_admin'])).toBe('admin');
      expect(resolveUserDestination(['operator_cms'])).toBe('admin');
      expect(resolveUserDestination(['ppdb_committee'])).toBe('admin');
      expect(resolveUserDestination(['committee'])).toBe('portal');
      expect(resolveUserDestination(['supervisor'])).toBe('portal');
      expect(resolveUserDestination(['guest'])).toBe('portal');
      expect(resolveUserDestination([])).toBe('portal');
    });
  });
});
