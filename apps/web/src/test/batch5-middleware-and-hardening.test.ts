import { describe, it, expect, beforeEach, afterEach } from 'vitest';
import { NextRequest } from 'next/server';
import { middleware } from '@/middleware';
import { canAccessShell, ADMIN_SHELL_ROLES } from '@/lib/rbac/evaluator';

describe('BATCH 5 - A.1, A.2, A.6: Middleware Hardening & Role Synchronization', () => {
  const originalEnv = process.env;

  beforeEach(() => {
    process.env = { ...originalEnv };
  });

  afterEach(() => {
    process.env = originalEnv;
  });

  describe('1. ADMIN_LOGIN_PATH Dynamic Rewriting & Old Path 404', () => {
    it('rewrites configured custom ADMIN_LOGIN_PATH to /admin-gateway-internal', () => {
      process.env.ADMIN_LOGIN_PATH = '/custom-super-secret-gate-789';

      const req = new NextRequest('http://localhost:3000/custom-super-secret-gate-789');
      const res = middleware(req);

      // Verify it rewrites internally to /admin-gateway-internal
      expect(res.headers.get('x-middleware-rewrite')).toContain('/admin-gateway-internal');
    });

    it('returns HTTP 404 for default path /secret-admin-gate-login when a custom path is configured', () => {
      process.env.ADMIN_LOGIN_PATH = '/custom-super-secret-gate-789';

      const req = new NextRequest('http://localhost:3000/secret-admin-gate-login');
      const res = middleware(req);

      expect(res.status).toBe(404);
    });

    it('returns HTTP 404 for direct access to /admin-gateway-internal', () => {
      const req = new NextRequest('http://localhost:3000/admin-gateway-internal');
      const res = middleware(req);

      expect(res.status).toBe(404);
    });

    it('rewrites default path /secret-admin-gate-login when no custom path is configured', () => {
      delete process.env.ADMIN_LOGIN_PATH;

      const req = new NextRequest('http://localhost:3000/secret-admin-gate-login');
      const res = middleware(req);

      expect(res.headers.get('x-middleware-rewrite')).toContain('/admin-gateway-internal');
    });
  });

  describe('2. HTTP 404 Status Code for Unauthenticated /admin Access', () => {
    it('returns genuine HTTP 404 status code when accessing /admin without admin session cookie', () => {
      const req = new NextRequest('http://localhost:3000/admin');
      const res = middleware(req);

      expect(res.status).toBe(404);
      expect(res.headers.get('X-Robots-Tag')).toBe('noindex, nofollow');
    });

    it('returns genuine HTTP 404 status code when accessing /admin/sub-page without admin session cookie', () => {
      const req = new NextRequest('http://localhost:3000/admin/academic/grades');
      const res = middleware(req);

      expect(res.status).toBe(404);
      expect(res.headers.get('X-Robots-Tag')).toBe('noindex, nofollow');
    });

    it('allows access to /admin when sistlabum_admin_session=1 cookie is present', () => {
      const req = new NextRequest('http://localhost:3000/admin', {
        headers: {
          cookie: 'sistlabum_admin_session=1',
        },
      });
      const res = middleware(req);

      // NextResponse.next() returns status 200 without rewrite header
      expect(res.status).toBe(200);
      expect(res.headers.get('x-middleware-rewrite')).toBeNull();
    });
  });

  describe('3. Single Source of Truth Role Synchronization (ADMIN_SHELL_ROLES)', () => {
    const canonicalAdminRoles = [
      'super_admin',
      'admin_tu',
      'principal',
      'vice_principal',
      'counselor',
      'facility_staff',
      'ppdb_committee',
      'librarian',
      'bendahara',
      'treasurer',
      'student_affairs',
      'public_relations',
      'operator_cms',
      'admin_kurikulum',
      'kurikulum',
    ];

    const canonicalCivitasRoles = [
      'teacher',
      'homeroom',
      'student',
      'guardian',
      'committee',
      'supervisor',
      'guest',
    ];

    it('grants admin shell access ONLY to canonical admin roles', () => {
      for (const role of canonicalAdminRoles) {
        expect(canAccessShell([role], 'admin')).toBe(true);
      }
    });

    it('strictly denies admin shell access to all civitas roles', () => {
      for (const role of canonicalCivitasRoles) {
        expect(canAccessShell([role], 'admin')).toBe(false);
      }
    });

    it('verifies ADMIN_SHELL_ROLES contains all expected canonical roles', () => {
      for (const role of canonicalAdminRoles) {
        expect(ADMIN_SHELL_ROLES).toContain(role);
      }
      for (const role of canonicalCivitasRoles) {
        expect(ADMIN_SHELL_ROLES).not.toContain(role);
      }
    });

    it('BATCH 5B - A.1: grants staff personal portal access (/portal/staf) to ALL back-office admin roles', () => {
      for (const role of canonicalAdminRoles) {
        expect(
          canAccessShell([role], 'staf'),
          `Role ${role} should be able to access personal staff portal (/portal/staf/*)`
        ).toBe(true);
      }
      // Also civitas staff
      expect(canAccessShell(['staff'], 'staf')).toBe(true);

      // Student and guardian should NOT access staff portal
      expect(canAccessShell(['student'], 'staf')).toBe(false);
      expect(canAccessShell(['guardian'], 'staf')).toBe(false);
    });

    it('BATCH 5B - A.1: grants teacher portal access (/portal/guru) when admin role also has teacher role', () => {
      expect(canAccessShell(['counselor', 'teacher'], 'guru')).toBe(true);
      expect(canAccessShell(['admin_tu', 'homeroom'], 'guru')).toBe(true);
      // Pure counselor without teacher does NOT access guru portal
      expect(canAccessShell(['counselor'], 'guru')).toBe(false);
    });
  });

  describe('4. Next.js Config Production Guard: Exact `next lint` Check (A.4)', () => {
    it('throws fatal error in PHASE_PRODUCTION_BUILD when command is `next build` and ADMIN_LOGIN_PATH is default', async () => {
      const originalArgv = [...process.argv];
      try {
        process.argv = ['node', '/path/to/next', 'build'];
        process.env.ADMIN_LOGIN_PATH = '/secret-admin-gate-login';
        delete process.env.NEXT_PUBLIC_MOCK_MODE;

        const nextConfigFn = (await import('../../next.config.mjs')).default;
        expect(() => nextConfigFn('phase-production-build')).toThrow(/FATAL: Lingkungan production dilarang keras/i);
      } finally {
        process.argv = originalArgv;
      }
    });

    it('throws fatal error in PHASE_PRODUCTION_BUILD when command is any other argument like `start` or `dev-lint`', async () => {
      const originalArgv = [...process.argv];
      try {
        process.argv = ['node', '/path/to/next', 'start'];
        process.env.ADMIN_LOGIN_PATH = '/secret-admin-gate-login';

        const nextConfigFn = (await import('../../next.config.mjs')).default;
        expect(() => nextConfigFn('phase-production-build')).toThrow(/FATAL: Lingkungan production dilarang keras/i);
      } finally {
        process.argv = originalArgv;
      }
    });

    it('bypasses production guard ONLY when command is exact `next lint` (argv[2] === "lint")', async () => {
      const originalArgv = [...process.argv];
      try {
        process.argv = ['node', '/path/to/next', 'lint'];
        process.env.ADMIN_LOGIN_PATH = '/secret-admin-gate-login';

        const nextConfigFn = (await import('../../next.config.mjs')).default;
        expect(() => nextConfigFn('phase-production-build')).not.toThrow();
      } finally {
        process.argv = originalArgv;
      }
    });
  });
});
