/**
 * P0-08: Utilitas kriptografis HMAC-SHA256 (Web Crypto API / Edge Runtime safe)
 * untuk menandatangani & memverifikasi:
 * 1. Cookie sesi admin HttpOnly (`sistlabum_admin_session`)
 * 2. Token internal rewrite gateway admin (`x-admin-gateway-secret`)
 */

import { ADMIN_SHELL_ROLES } from '@/lib/rbac/evaluator';

export const ADMIN_SESSION_COOKIE_NAME = 'sistlabum_admin_session';
export const INTERNAL_GATEWAY_HEADER = 'x-admin-gateway-secret';
export const ADMIN_SESSION_TTL_SECONDS = 12 * 60 * 60; // 12 jam
export const INTERNAL_GATEWAY_TOKEN_TTL_SECONDS = 30; // jendela anti-replay 30 detik

function getGatewaySecret(): string {
  const configured = process.env.ADMIN_GATEWAY_SECRET?.trim();
  if (configured && configured.length >= 16) {
    return configured;
  }

  // Fallback deterministik server-side (TIDAK pernah memakai nilai statis '1')
  const pathEntropy = process.env.ADMIN_LOGIN_PATH || '/secret-admin-gate-login';
  const apiEntropy = process.env.NEXT_PUBLIC_API_BASE_URL || 'sistlabum-local';
  return `sistlabum-gw-v1::${pathEntropy}::${apiEntropy}`;
}

function bytesToHex(bytes: Uint8Array): string {
  return Array.from(bytes)
    .map((b) => b.toString(16).padStart(2, '0'))
    .join('');
}

function stringToBase64Url(input: string): string {
  const bytes = new TextEncoder().encode(input);
  let binary = '';
  for (let i = 0; i < bytes.length; i++) {
    binary += String.fromCharCode(bytes[i]);
  }
  return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}

function base64UrlToString(input: string): string | null {
  try {
    const normalized = input.replace(/-/g, '+').replace(/_/g, '/');
    const padded = normalized + '='.repeat((4 - (normalized.length % 4)) % 4);
    const binary = atob(padded);
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) {
      bytes[i] = binary.charCodeAt(i);
    }
    return new TextDecoder().decode(bytes);
  } catch {
    return null;
  }
}

function constantTimeEqual(a: string, b: string): boolean {
  if (a.length !== b.length) return false;
  let diff = 0;
  for (let i = 0; i < a.length; i++) {
    diff |= a.charCodeAt(i) ^ b.charCodeAt(i);
  }
  return diff === 0;
}

async function computeHmacHex(message: string): Promise<string> {
  const secretBytes = new TextEncoder().encode(getGatewaySecret());
  const key = await crypto.subtle.importKey(
    'raw',
    secretBytes,
    { name: 'HMAC', hash: 'SHA-256' },
    false,
    ['sign']
  );
  const sig = await crypto.subtle.sign('HMAC', key, new TextEncoder().encode(message));
  return bytesToHex(new Uint8Array(sig));
}

export async function createGatewayInternalToken(nowSeconds: number = Math.floor(Date.now() / 1000)): Promise<string> {
  const nonceBytes = new Uint8Array(8);
  crypto.getRandomValues(nonceBytes);
  const nonce = bytesToHex(nonceBytes);
  const sig = await computeHmacHex(`admin-gateway-internal-rewrite-v2:${nowSeconds}:${nonce}`);
  return `${nowSeconds}.${nonce}.${sig}`;
}

export async function verifyGatewayInternalToken(
  token: string | null | undefined,
  nowSeconds: number = Math.floor(Date.now() / 1000)
): Promise<boolean> {
  if (!token || typeof token !== 'string') return false;
  const parts = token.split('.');
  if (parts.length !== 3) return false;

  const [tsRaw, nonce, providedSig] = parts;
  if (!tsRaw || !nonce || !providedSig || !/^\d+$/.test(tsRaw) || !/^[a-f0-9]{16}$/.test(nonce)) {
    return false;
  }

  const ts = Number.parseInt(tsRaw, 10);
  if (!Number.isFinite(ts) || Math.abs(nowSeconds - ts) > INTERNAL_GATEWAY_TOKEN_TTL_SECONDS) {
    return false;
  }

  const expected = await computeHmacHex(`admin-gateway-internal-rewrite-v2:${ts}:${nonce}`);
  return constantTimeEqual(providedSig, expected);
}

export async function signAdminSessionCookie(
  userId: string,
  role: string,
  ttlSeconds: number = ADMIN_SESSION_TTL_SECONDS
): Promise<string> {
  const now = Math.floor(Date.now() / 1000);
  const nonceBytes = new Uint8Array(12);
  crypto.getRandomValues(nonceBytes);
  const payload = JSON.stringify({
    uid: userId,
    role,
    iat: now,
    exp: now + ttlSeconds,
    jti: bytesToHex(nonceBytes),
  });
  const encodedPayload = stringToBase64Url(payload);
  const signature = await computeHmacHex(`admin-session:${encodedPayload}`);
  return `${encodedPayload}.${signature}`;
}

export async function verifyAdminSessionCookie(cookieValue: string | undefined | null): Promise<boolean> {
  if (!cookieValue || typeof cookieValue !== 'string') return false;
  const parts = cookieValue.split('.');
  if (parts.length !== 2) return false;

  const [encodedPayload, providedSig] = parts;
  if (!encodedPayload || !providedSig) return false;

  const expectedSig = await computeHmacHex(`admin-session:${encodedPayload}`);
  if (!constantTimeEqual(providedSig, expectedSig)) {
    return false;
  }

  const decoded = base64UrlToString(encodedPayload);
  if (!decoded) return false;

  try {
    const data = JSON.parse(decoded) as { uid?: unknown; role?: unknown; iat?: unknown; exp?: unknown };
    const now = Math.floor(Date.now() / 1000);
    if (typeof data.uid !== 'string' || data.uid.trim() === '') return false;
    if (typeof data.role !== 'string' || !ADMIN_SHELL_ROLES.includes(data.role as (typeof ADMIN_SHELL_ROLES)[number])) {
      return false;
    }
    if (typeof data.exp !== 'number' || data.exp <= now) {
      return false;
    }
    if (typeof data.iat === 'number') {
      if (data.iat > now + 60 || data.exp - data.iat > ADMIN_SESSION_TTL_SECONDS + 60) {
        return false;
      }
    }
    return true;
  } catch {
    return false;
  }
}
