/**
 * Resolusi alamat API untuk pemanggilan server-to-server (Route Handler / Server Component).
 *
 * `NEXT_PUBLIC_API_BASE_URL` di produksi berisi path relatif ("/api/v1") karena dipakai
 * browser. Nilai itu TIDAK boleh dipakai apa adanya di server: `fetch()` Node menolak
 * URL relatif dan melempar "TypeError: Failed to parse URL", sehingga Route Handler
 * gagal 502 dan cookie sesi admin tidak pernah terbit.
 */

const DEFAULT_API_PATH = '/api/v1';
const DEFAULT_API_PORT = '8000';

function isAbsoluteUrl(value: string): boolean {
  return /^https?:\/\//i.test(value);
}

export function resolveInternalApiBaseUrl(
  env: Record<string, string | undefined> = process.env
): string {
  const explicit = env.INTERNAL_API_BASE_URL?.trim();
  if (explicit && isAbsoluteUrl(explicit)) {
    return explicit.replace(/\/+$/, '');
  }

  const publicBase = env.NEXT_PUBLIC_API_BASE_URL?.trim();

  // Sudah absolut (mis. http://127.0.0.1:8000/api/v1) — pakai langsung.
  if (publicBase && isAbsoluteUrl(publicBase)) {
    return publicBase.replace(/\/+$/, '');
  }

  // Relatif ("/api/v1") atau kosong — arahkan ke Laravel lokal.
  const path = publicBase && publicBase.startsWith('/') ? publicBase : DEFAULT_API_PATH;
  const port = env.API_INTERNAL_PORT?.trim() || DEFAULT_API_PORT;

  return `http://127.0.0.1:${port}${path.replace(/\/+$/, '')}`;
}
