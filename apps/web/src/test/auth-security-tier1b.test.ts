import { describe, it, expect, beforeEach, afterEach } from 'vitest';
import { NextRequest } from 'next/server';
import { middleware } from '@/middleware';
import { tokenStorage } from '@/lib/auth/token-storage';
import {
  ADMIN_SESSION_COOKIE_NAME,
  INTERNAL_GATEWAY_HEADER,
  signAdminSessionCookie,
  verifyAdminSessionCookie,
} from '@/lib/auth/admin-session-cookie';

describe('Tier 1B Security Regression: P0-08 Admin Gateway & P1-01 Token Storage', () => {
  const originalEnv = process.env;

  beforeEach(() => {
    process.env = { ...originalEnv, ADMIN_GATEWAY_SECRET: 'test-super-secret-gateway-key-32bytes' };
    tokenStorage.clearAll();
    if (typeof window !== 'undefined' && window.localStorage) {
      window.localStorage.clear();
    }
  });

  afterEach(() => {
    process.env = originalEnv;
  });

  it('P0-08: blocks spoofed x-admin-gateway: 1 header on /admin-gateway-internal with 404', async () => {
    const req = new NextRequest('http://localhost:3000/admin-gateway-internal', {
      headers: {
        'x-admin-gateway': '1',
      },
    });

    const res = await middleware(req);
    expect(res.status).toBe(404);
  });

  it('P0-08: blocks forged x-admin-gateway-secret header on /admin-gateway-internal with 404', async () => {
    const req = new NextRequest('http://localhost:3000/admin-gateway-internal', {
      headers: {
        [INTERNAL_GATEWAY_HEADER]: 'forged-secret-value',
      },
    });

    const res = await middleware(req);
    expect(res.status).toBe(404);
  });

  it('P0-08: rejects expired, tampered, or non-admin signed cookies on /admin', async () => {
    // 1. Forged plain value
    const resPlain = await middleware(
      new NextRequest('http://localhost:3000/admin', {
        headers: { cookie: `${ADMIN_SESSION_COOKIE_NAME}=1` },
      })
    );
    expect(resPlain.status).toBe(404);

    // 2. Expired signed cookie
    const expired = await signAdminSessionCookie('adm-1', 'admin_tu', -10);
    expect(await verifyAdminSessionCookie(expired)).toBe(false);
    const resExpired = await middleware(
      new NextRequest('http://localhost:3000/admin', {
        headers: { cookie: `${ADMIN_SESSION_COOKIE_NAME}=${expired}` },
      })
    );
    expect(resExpired.status).toBe(404);

    // 3. Non-admin role signed cookie (misal 'student')
    const studentCookie = await signAdminSessionCookie('stu-1', 'student', 3600);
    expect(await verifyAdminSessionCookie(studentCookie)).toBe(false);

    // 4. Tampered signature
    const valid = await signAdminSessionCookie('adm-1', 'super_admin', 3600);
    const tampered = valid.slice(0, -2) + 'ff';
    expect(await verifyAdminSessionCookie(tampered)).toBe(false);
  });

  it('P1-01: never persists accessToken or refreshToken into localStorage', () => {
    tokenStorage.setTokens('acc-secret-123', 'ref-secret-456');

    expect(window.localStorage.getItem('sistlabum_access_token')).toBeNull();
    expect(window.localStorage.getItem('sistlabum_refresh_token')).toBeNull();
    expect(window.localStorage.getItem('auth_token')).toBeNull();

    // Access token tetap tersedia di memory/sessionStorage untuk tab aktif
    expect(tokenStorage.getAccessToken()).toBe('acc-secret-123');
    // Refresh token hanya disimpan di memory bila diset eksplisit (tidak pernah dari localStorage)
    expect(tokenStorage.getRefreshToken()).toBe('ref-secret-456');
  });

  it('P1-01: ignores legacy localStorage tokens and scrubs them on setAccessToken/clearAll', () => {
    window.localStorage.setItem('sistlabum_access_token', 'legacy-acc');
    window.localStorage.setItem('sistlabum_refresh_token', 'legacy-ref');
    window.localStorage.setItem('auth_token', 'legacy-auth');
    window.localStorage.setItem('sistlabum_tdt', 'legacy-tdt');

    expect(tokenStorage.getAccessToken()).toBeNull();
    expect(tokenStorage.getRefreshToken()).toBeNull();

    tokenStorage.clearAll();
    expect(window.localStorage.getItem('sistlabum_access_token')).toBeNull();
    expect(window.localStorage.getItem('sistlabum_refresh_token')).toBeNull();
    expect(window.localStorage.getItem('auth_token')).toBeNull();
    expect(window.localStorage.getItem('sistlabum_tdt')).toBeNull();
  });
});
