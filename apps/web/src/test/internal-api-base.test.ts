import { describe, expect, it } from 'vitest';
import { resolveInternalApiBaseUrl } from '@/lib/auth/internal-api-base';

/**
 * Regresi: `NEXT_PUBLIC_API_BASE_URL` di produksi berisi "/api/v1" (relatif).
 * Dulu nilai itu dipakai langsung untuk fetch server-side sehingga melempar
 * "TypeError: Failed to parse URL" → route /api/admin-session balas 502 →
 * cookie sesi admin tidak terbit → /admin 404 untuk semua role admin.
 */
describe('resolveInternalApiBaseUrl', () => {
  it('mengubah path relatif menjadi URL absolut ke Laravel lokal', () => {
    expect(resolveInternalApiBaseUrl({ NEXT_PUBLIC_API_BASE_URL: '/api/v1' })).toBe(
      'http://127.0.0.1:8000/api/v1'
    );
  });

  it('memakai INTERNAL_API_BASE_URL bila diisi absolut', () => {
    expect(
      resolveInternalApiBaseUrl({
        INTERNAL_API_BASE_URL: 'http://127.0.0.1:9000/api/v1/',
        NEXT_PUBLIC_API_BASE_URL: '/api/v1',
      })
    ).toBe('http://127.0.0.1:9000/api/v1');
  });

  it('menghormati NEXT_PUBLIC_API_BASE_URL yang sudah absolut', () => {
    expect(
      resolveInternalApiBaseUrl({ NEXT_PUBLIC_API_BASE_URL: 'https://api.sekolah.id/api/v1' })
    ).toBe('https://api.sekolah.id/api/v1');
  });

  it('jatuh ke default /api/v1 bila env kosong', () => {
    expect(resolveInternalApiBaseUrl({})).toBe('http://127.0.0.1:8000/api/v1');
  });

  it('mengabaikan INTERNAL_API_BASE_URL yang relatif (tidak bisa dipakai fetch server)', () => {
    expect(
      resolveInternalApiBaseUrl({ INTERNAL_API_BASE_URL: '/api/v1', NEXT_PUBLIC_API_BASE_URL: '/api/v1' })
    ).toBe('http://127.0.0.1:8000/api/v1');
  });

  it('bisa memakai port internal kustom', () => {
    expect(
      resolveInternalApiBaseUrl({ NEXT_PUBLIC_API_BASE_URL: '/api/v1', API_INTERNAL_PORT: '8080' })
    ).toBe('http://127.0.0.1:8080/api/v1');
  });
});
