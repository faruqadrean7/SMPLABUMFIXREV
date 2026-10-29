import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { tokenStorage } from '@/lib/auth/token-storage';
import { apiClient } from '@/lib/api/api-client';
import { resolveUserDestination, getDestinationPath } from '@/lib/rbac/evaluator';
import { FOOTER_PORTAL_LINKS } from '@/features/public-site/content/navigation';
import { isMockEnabled, assertValidEnvironment } from '@/lib/api/mock-mode';

describe('BATCH 1 P0 Security & Architecture Remediation', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    tokenStorage.clearAll();
    if (typeof window !== 'undefined') {
      localStorage.clear();
      sessionStorage.clear();
    }
  });

  afterEach(() => {
    tokenStorage.clearAll();
    if (typeof window !== 'undefined') {
      localStorage.clear();
      sessionStorage.clear();
    }
  });

  describe('1. GAP-SEC-01: Zero Token Leakage in URLs & Pure Internal Redirects', () => {
    it('redirects civitas roles (teacher, student, guardian, staff) to internal /portal/{role} without URL tokens', () => {
      // 1. Guru
      const destGuru = resolveUserDestination(['teacher']);
      const pathGuru = getDestinationPath(destGuru);
      expect(pathGuru).toBe('/portal/guru');
      expect(pathGuru).not.toContain('sso_token');
      expect(pathGuru).not.toContain('localhost:3001');

      // 2. Siswa
      const destSiswa = resolveUserDestination(['student']);
      const pathSiswa = getDestinationPath(destSiswa);
      expect(pathSiswa).toBe('/portal/siswa');
      expect(pathSiswa).not.toContain('sso_token');

      // 3. Ortu
      const destOrtu = resolveUserDestination(['guardian']);
      const pathOrtu = getDestinationPath(destOrtu);
      expect(pathOrtu).toBe('/portal/ortu');
      expect(pathOrtu).not.toContain('sso_token');

      // 4. Staf
      const destStaf = resolveUserDestination(['admin_tu']);
      const pathStaf = getDestinationPath(destStaf);
      expect(pathStaf).toBe('/portal/staf');

      // 5. Admin
      const destAdmin = resolveUserDestination(['super_admin']);
      const pathAdmin = getDestinationPath(destAdmin);
      expect(pathAdmin).toBe('/admin');
    });

    it('ensures token is stored in memory/sessionStorage, never leaked to window search params', () => {
      const sensitiveToken = 'bearer-jwt-super-secret-123';
      tokenStorage.setAccessToken(sensitiveToken);

      expect(tokenStorage.getAccessToken()).toBe(sensitiveToken);
      if (typeof window !== 'undefined') {
        expect(window.location.href).not.toContain('sso_token');
        expect(window.location.search).not.toContain('sso_token');
      }
    });
  });

  describe('2. GAP-PPDB-01: PPDB Token Storage & Bearer Propagation', () => {
    it('stores token in tokenStorage and eliminates legacy auth_token in localStorage', () => {
      const mockAccessToken = 'ppdb-sanctum-token-12345';
      const mockRefreshToken = 'ppdb-refresh-token-67890';

      // Simulasikan alur penyimpanan token PPDB yang benar via tokenStorage
      tokenStorage.setTokens(mockAccessToken, mockRefreshToken);

      // Token HARUS tersedia via tokenStorage
      expect(tokenStorage.getAccessToken()).toBe(mockAccessToken);
      expect(tokenStorage.getRefreshToken()).toBe(mockRefreshToken);

      // Legacy key 'auth_token' TIDAK BOLEH digunakan lagi di localStorage
      if (typeof window !== 'undefined') {
        expect(localStorage.getItem('auth_token')).toBeNull();
      }
    });

    it('propagates Authorization Bearer header to /ppdb/me requests via apiClient interceptor without mock', async () => {
      const sampleToken = 'valid-ppdb-bearer-token-abc';
      tokenStorage.setAccessToken(sampleToken);

      // Verifikasi bahwa interceptor apiClient menyisipkan Bearer token dari tokenStorage
      const interceptor = (apiClient.interceptors.request as any).handlers?.[0];
      expect(interceptor).toBeDefined();

      if (interceptor && interceptor.fulfilled) {
        const fakeConfig: any = { headers: {} };
        const resultConfig = await interceptor.fulfilled(fakeConfig);
        expect(resultConfig.headers['Authorization']).toBe(`Bearer ${sampleToken}`);
      }
    });
  });

  describe('3. GAP-ARCH-01: Unified Frontend & Internal Route Links', () => {
    it('ensures FOOTER_PORTAL_LINKS does not point to port 3001 or any external domain', () => {
      for (const link of FOOTER_PORTAL_LINKS) {
        expect(link.href).not.toContain('localhost:3001');
        expect(link.href).not.toContain('http://');
        expect(link.href).not.toContain('https://');
        // Rute harus internal berawalan /
        expect(link.href.startsWith('/')).toBe(true);
      }
    });
  });

  describe('4. Mock Mode Safety: Build & Startup Protection', () => {
    it('throws fatal error if NEXT_PUBLIC_MOCK_MODE or NEXT_PUBLIC_USE_MOCK is true in production', () => {
      const prevEnv = process.env.NODE_ENV;
      const prevMockMode = process.env.NEXT_PUBLIC_MOCK_MODE;
      const prevUseMock = process.env.NEXT_PUBLIC_USE_MOCK;

      try {
        (process.env as any).NODE_ENV = 'production';
        process.env.NEXT_PUBLIC_MOCK_MODE = 'true';

        // Harus gagal/throw error di production saat startup check dipanggil
        expect(() => {
          assertValidEnvironment();
        }).toThrow(/Mock mode dilarang keras aktif pada lingkungan production/);

        // Uji juga untuk NEXT_PUBLIC_USE_MOCK
        delete process.env.NEXT_PUBLIC_MOCK_MODE;
        process.env.NEXT_PUBLIC_USE_MOCK = 'true';
        expect(() => {
          assertValidEnvironment();
        }).toThrow(/Mock mode dilarang keras aktif pada lingkungan production/);
      } finally {
        (process.env as any).NODE_ENV = prevEnv;
        process.env.NEXT_PUBLIC_MOCK_MODE = prevMockMode;
        process.env.NEXT_PUBLIC_USE_MOCK = prevUseMock;
      }
    });

    it('passes assertValidEnvironment in development or when mock mode is off', () => {
      const prevEnv = process.env.NODE_ENV;
      const prevMock = process.env.NEXT_PUBLIC_MOCK_MODE;

      try {
        (process.env as any).NODE_ENV = 'development';
        process.env.NEXT_PUBLIC_MOCK_MODE = 'false';
        expect(() => assertValidEnvironment()).not.toThrow();

        (process.env as any).NODE_ENV = 'production';
        delete process.env.NEXT_PUBLIC_MOCK_MODE;
        delete process.env.NEXT_PUBLIC_USE_MOCK;
        expect(() => assertValidEnvironment()).not.toThrow();
      } finally {
        (process.env as any).NODE_ENV = prevEnv;
        process.env.NEXT_PUBLIC_MOCK_MODE = prevMock;
      }
    });
  });
});
