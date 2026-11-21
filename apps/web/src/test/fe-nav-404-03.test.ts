import { describe, it, expect } from 'vitest';
import fs from 'fs';
import path from 'path';

/**
 * FE-NAV-404-03: Otomatisasi Validasi Navigasi & Pencegahan Link Rute 404
 *
 * Dokumentasi Batasan Pengujian:
 * - Uji ini secara ketat memvalidasi seluruh target navigasi STATIS (literal string "/...").
 * - Target dinamis dengan interpolasi (${param} / template string) sengaja diabaikan untuk
 *   mencegah false-positive, karena nilainya baru ditentukan saat runtime berdasarkan ID entitas.
 * - Uji ini membaca filesystem Next.js App Router secara dinamis (apps/web/src/app) pada setiap eksekusi,
 *   bukan menggunakan daftar statis/hardcoded, sehingga selalu sinkron dengan rute riil sistem.
 */

interface NavTarget {
  file: string;
  href: string;
}

interface RedirectRule {
  source: string;
  destination: string;
}

// 1. Fungsi Pembacaan Rute Nyata App Router dari Filesystem
export function enumerateAppRoutes(appDir: string): string[] {
  const routes: string[] = [];

  function traverse(currentDir: string) {
    if (!fs.existsSync(currentDir)) return;
    const entries = fs.readdirSync(currentDir, { withFileTypes: true });

    for (const entry of entries) {
      const fullPath = path.join(currentDir, entry.name);

      if (entry.isDirectory()) {
        traverse(fullPath);
      } else if (/^page\.(tsx|jsx|js|ts)$/.test(entry.name)) {
        const relative = path.relative(appDir, fullPath);
        const segments = path
          .dirname(relative)
          .split(path.sep)
          .filter((seg) => seg && seg !== '.' && !seg.startsWith('('));

        const routePath = '/' + segments.join('/');
        routes.push(routePath === '//' ? '/' : routePath);
      }
    }
  }

  traverse(appDir);
  return Array.from(new Set(routes));
}

// 2. Daftar Redirect Resmi next.config.mjs
export const NEXT_REDIRECT_RULES: RedirectRule[] = [
  { source: '/admin/grades', destination: '/admin/academic/grades' },
  { source: '/admin/grades/input', destination: '/admin/academic/grades' },
  { source: '/admin/grades/progress-report', destination: '/admin/academic/progress-reports' },
  { source: '/admin/grades/report-cards', destination: '/admin/academic/report-cards' },
  { source: '/admin/users', destination: '/admin/settings/users' },
  { source: '/admin/facilities/library', destination: '/admin/library' },
  { source: '/portal/siswa/notifications', destination: '/portal/notifikasi' },
  { source: '/portal/ortu/notifications', destination: '/portal/notifikasi' },
  { source: '/guru', destination: '/portal/guru' },
  { source: '/guru/:path*', destination: '/portal/guru/:path*' },
  { source: '/siswa', destination: '/portal/siswa' },
  { source: '/siswa/:path*', destination: '/portal/siswa/:path*' },
  { source: '/ortu', destination: '/portal/ortu' },
  { source: '/ortu/:path*', destination: '/portal/ortu/:path*' },
  { source: '/staf', destination: '/portal/staf' },
  { source: '/staf/:path*', destination: '/portal/staf/:path*' },
  { source: '/kepsek', destination: '/portal/kepsek' },
  { source: '/kepsek/:path*', destination: '/portal/kepsek/:path*' },
];

// 3. Resolusi Redirect Alias
export function resolveRedirect(href: string, rules: RedirectRule[] = NEXT_REDIRECT_RULES): string {
  const cleanPath = href.split('?')[0].split('#')[0];

  for (const rule of rules) {
    if (rule.source === cleanPath) {
      return rule.destination;
    }
    if (rule.source.endsWith('/:path*')) {
      const prefix = rule.source.replace('/:path*', '');
      if (cleanPath.startsWith(prefix + '/')) {
        const destPrefix = rule.destination.replace('/:path*', '');
        return destPrefix + cleanPath.slice(prefix.length);
      }
    }
  }

  return cleanPath;
}

// 4. Pencocokan Rute terhadap Pola Next.js
export function isRouteValid(targetHref: string, appRoutes: string[], rules: RedirectRule[] = NEXT_REDIRECT_RULES): boolean {
  if (!targetHref.startsWith('/') || targetHref.startsWith('//')) {
    // External URL atau hash-only, abaikan
    return true;
  }

  const resolved = resolveRedirect(targetHref, rules);

  for (const route of appRoutes) {
    if (route === resolved) return true;

    // Ubah rute parameterized menjadi regex
    // [slug] -> [^/]+
    // [...slug] -> .+
    // [[...slug]] -> .*
    const pattern =
      '^' +
      route
        .split('/')
        .map((seg) => {
          if (seg.startsWith('[[...') && seg.endsWith(']]')) return '(?:.*)?';
          if (seg.startsWith('[...') && seg.endsWith(']')) return '.+';
          if (seg.startsWith('[') && seg.endsWith(']')) return '[^/]+';
          return seg.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        })
        .join('/') +
      '$';

    const regex = new RegExp(pattern);
    if (regex.test(resolved)) {
      return true;
    }
  }

  return false;
}

// 5. Ekstraksi Target Href Statis dari File Sumber
export function extractStaticHrefs(filePaths: string[]): NavTarget[] {
  const targets: NavTarget[] = [];
  // Cocokkan href: '...' atau href="/..." yang berupa literal statis
  const staticHrefRegex = /(?:href|href:)\s*['"](\/[^'"\s?#]*)['"]/g;

  for (const fp of filePaths) {
    if (!fs.existsSync(fp)) continue;
    const content = fs.readFileSync(fp, 'utf-8');
    const baseName = path.basename(fp);
    let match: RegExpExecArray | null;

    while ((match = staticHrefRegex.exec(content)) !== null) {
      const href = match[1];
      // Abaikan jika tampaknya merupakan placeholder atau bukan rute portal/admin
      if (href && !targets.some((t) => t.file === baseName && t.href === href)) {
        targets.push({ file: baseName, href });
      }
    }
  }

  return targets;
}

describe('FE-NAV-404-03: Otomatisasi Uji Validitas Rute Menu Navigasi', () => {
  const projectRoot = path.resolve(__dirname, '../..');
  const appDir = path.join(projectRoot, 'src/app');

  const navigationConfigFiles = [
    path.join(projectRoot, 'src/lib/rbac/navigation.ts'),
    path.join(projectRoot, 'src/components/shell/portal/parent-header.tsx'),
    path.join(projectRoot, 'src/components/shell/portal/student-header.tsx'),
    path.join(projectRoot, 'src/components/shell/portal/portal-shell.tsx'),
    path.join(projectRoot, 'src/components/shell/admin/admin-sidebar.tsx'),
    path.join(projectRoot, 'src/components/shell/public/PublicHeader.tsx'),
    path.join(projectRoot, 'src/components/shell/public/PublicFooter.tsx'),
  ];

  it('FE-NAV-404-03: mengidentifikasi rute nyata dari filesystem app router secara dinamis', () => {
    const routes = enumerateAppRoutes(appDir);
    expect(routes.length).toBeGreaterThan(50);
    expect(routes).toContain('/');
    expect(routes).toContain('/portal/siswa');
    expect(routes).toContain('/portal/ortu');
    expect(routes).toContain('/portal/guru');
    expect(routes).toContain('/admin');
  });

  it('FE-NAV-404-03: menerapkan alias redirect dari next.config.mjs dengan benar', () => {
    expect(resolveRedirect('/guru')).toBe('/portal/guru');
    expect(resolveRedirect('/guru/jadwal')).toBe('/portal/guru/jadwal');
    expect(resolveRedirect('/siswa/tugas')).toBe('/portal/siswa/tugas');
    expect(resolveRedirect('/ortu/presensi')).toBe('/portal/ortu/presensi');
    expect(resolveRedirect('/admin/users')).toBe('/admin/settings/users');
    expect(resolveRedirect('/admin/facilities/library')).toBe('/admin/library');
  });

  it('FE-NAV-404-03 (AC1): seluruh href menu statis di komponen shell menunjuk rute nyata yang valid', () => {
    const appRoutes = enumerateAppRoutes(appDir);
    const staticTargets = extractStaticHrefs(navigationConfigFiles);

    expect(staticTargets.length).toBeGreaterThan(0);

    const invalidTargets: NavTarget[] = [];
    for (const target of staticTargets) {
      const valid = isRouteValid(target.href, appRoutes);
      if (!valid) {
        invalidTargets.push(target);
      }
    }

    if (invalidTargets.length > 0) {
      const errorMsg = invalidTargets
        .map((t) => `  - [${t.file}] href "${t.href}" tidak ditemukan di App Router`)
        .join('\n');
      throw new Error(`FE-NAV-404-03: Ditemukan link menu yang mengarah ke rute 404:\n${errorMsg}`);
    }

    expect(invalidTargets).toEqual([]);
  });

  it('FE-NAV-404-03 (AC2): deteksi kegagalan bila ada href navigasi yang menunjuk rute fiktif / tidak ada', () => {
    const appRoutes = enumerateAppRoutes(appDir);

    // Sampel rute rusak yang sebelumnya ada sebelum Tahap 1 & 2 diperbaiki
    const brokenSamples = [
      '/portal/ortu/izin',
      '/portal/ortu/rapor',
      '/portal/siswa/rapor',
      '/fasilitas',
      '/portal/siswa/halaman-fiktif-404',
      '/admin/tidak-ada-rute-ini',
    ];

    for (const broken of brokenSamples) {
      const isValid = isRouteValid(broken, appRoutes);
      expect(isValid, `Rute fiktif "${broken}" seharusnya terdeteksi TIDAK VALID`).toBe(false);
    }
  });

  it('FE-NAV-404-03: mendukung rute dinamis dengan parameter wildcard', () => {
    const appRoutes = enumerateAppRoutes(appDir);

    // Rute dinamis nyata di sistem
    expect(isRouteValid('/portal/ortu/anak/student-123', appRoutes)).toBe(true);
    expect(isRouteValid('/berita/kegiatan-hari-pahlawan', appRoutes)).toBe(true);
    expect(isRouteValid('/portal/guru/tugas/assignment-456/penilaian', appRoutes)).toBe(true);
  });
});
