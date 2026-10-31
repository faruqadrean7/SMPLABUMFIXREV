import { PHASE_PRODUCTION_BUILD } from 'next/constants.js';

export default function nextConfigFunction(phase) {
  // BATCH 5B - A.4: Guard produksi dilewati HANYA bila perintah persis `next lint` (argv[2] === 'lint')
  const isExactNextLint = process.argv[2] === 'lint';

  // Validasi Lingkungan Produksi: Mock mode dilarang keras aktif saat build / startup production
  if (
    !isExactNextLint &&
    phase === PHASE_PRODUCTION_BUILD &&
    (process.env.NEXT_PUBLIC_MOCK_MODE === 'true' || process.env.NEXT_PUBLIC_USE_MOCK === 'true')
  ) {
    throw new Error('FATAL: Mock mode dilarang keras aktif pada lingkungan production (NEXT_PUBLIC_MOCK_MODE / NEXT_PUBLIC_USE_MOCK = true)!');
  }

  // Validasi Lingkungan Produksi: ADMIN_LOGIN_PATH tidak boleh memakai default di production
  if (
    !isExactNextLint &&
    phase === PHASE_PRODUCTION_BUILD &&
    (!process.env.ADMIN_LOGIN_PATH || process.env.ADMIN_LOGIN_PATH === '/secret-admin-gate-login')
  ) {
    throw new Error('FATAL: Lingkungan production dilarang keras memakai ADMIN_LOGIN_PATH default (/secret-admin-gate-login). Konfigurasikan ADMIN_LOGIN_PATH unik di environment!');
  }

  /** @type {import('next').NextConfig} */
  const nextConfig = {
  eslint: {
    ignoreDuringBuilds: true,
  },
  optimizeFonts: false,
  reactStrictMode: true,
  poweredByHeader: false,
  async redirects() {
    return [
      {
        source: '/admin/grades',
        destination: '/admin/academic/grades',
        permanent: false,
      },
      {
        source: '/admin/grades/input',
        destination: '/admin/academic/grades',
        permanent: false,
      },
      {
        source: '/admin/grades/progress-report',
        destination: '/admin/academic/progress-reports',
        permanent: false,
      },
      {
        source: '/admin/grades/report-cards',
        destination: '/admin/academic/report-cards',
        permanent: false,
      },
      // === KONSOLIDASI RUTE DUPLIKAT & USANG (GAP-DUP-01) ===
      {
        source: '/admin/users',
        destination: '/admin/settings/users',
        permanent: false,
      },
      {
        source: '/admin/facilities/library',
        destination: '/admin/library',
        permanent: false,
      },
      {
        source: '/portal/siswa/notifications',
        destination: '/portal/notifikasi',
        permanent: false,
      },
      {
        source: '/portal/ortu/notifications',
        destination: '/portal/notifikasi',
        permanent: false,
      },
      // === URL ALIASES SESUAI SPESIFIKASI PORTAL SEKOLAH ===
      {
        source: '/guru',
        destination: '/portal/guru',
        permanent: false,
      },
      {
        source: '/guru/:path*',
        destination: '/portal/guru/:path*',
        permanent: false,
      },
      {
        source: '/siswa',
        destination: '/portal/siswa',
        permanent: false,
      },
      {
        source: '/siswa/:path*',
        destination: '/portal/siswa/:path*',
        permanent: false,
      },
      {
        source: '/ortu',
        destination: '/portal/ortu',
        permanent: false,
      },
      {
        source: '/ortu/:path*',
        destination: '/portal/ortu/:path*',
        permanent: false,
      },
      {
        source: '/staf',
        destination: '/portal/staf',
        permanent: false,
      },
      {
        source: '/staf/:path*',
        destination: '/portal/staf/:path*',
        permanent: false,
      },
      {
        source: '/kepsek',
        destination: '/portal/kepsek',
        permanent: false,
      },
      {
        source: '/kepsek/:path*',
        destination: '/portal/kepsek/:path*',
        permanent: false,
      },
    ];
  },
};

  return nextConfig;
}

const baseConfig = nextConfigFunction('phase-development-server');
Object.assign(nextConfigFunction, baseConfig);

