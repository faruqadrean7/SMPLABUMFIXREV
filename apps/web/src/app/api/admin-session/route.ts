import { NextRequest, NextResponse } from 'next/server';
import { ADMIN_SHELL_ROLES } from '@/lib/rbac/evaluator';
import {
  ADMIN_SESSION_COOKIE_NAME,
  ADMIN_SESSION_TTL_SECONDS,
  signAdminSessionCookie,
} from '@/lib/auth/admin-session-cookie';

const INTERNAL_API_BASE_URL =
  process.env.INTERNAL_API_BASE_URL ||
  process.env.NEXT_PUBLIC_API_BASE_URL ||
  'http://127.0.0.1:8000/api/v1';

/**
 * P0-08: Terbitkan cookie HttpOnly bertanda tangan HMAC untuk akses shell `/admin`
 * hanya setelah memverifikasi token Bearer pengguna secara server-to-server ke backend `/auth/me`.
 */
export async function POST(request: NextRequest) {
  const authHeader = request.headers.get('authorization');
  if (!authHeader || !authHeader.startsWith('Bearer ')) {
    return NextResponse.json(
      { error: { code: 'UNAUTHENTICATED', message: 'Token autentikasi diperlukan.' } },
      { status: 401 }
    );
  }

  try {
    const meRes = await fetch(`${INTERNAL_API_BASE_URL}/auth/me`, {
      method: 'GET',
      headers: {
        Authorization: authHeader,
        Accept: 'application/json',
      },
      cache: 'no-store',
    });

    if (!meRes.ok) {
      return NextResponse.json(
        { error: { code: 'UNAUTHENTICATED', message: 'Sesi pengguna tidak valid.' } },
        { status: 401 }
      );
    }

    const body = (await meRes.json()) as {
      data?: { user?: { id?: string; roles?: string[] } };
    };
    const user = body?.data?.user;
    const roles = Array.isArray(user?.roles) ? user.roles : [];
    const matchedAdminRole = roles.find((r) =>
      ADMIN_SHELL_ROLES.includes(r as (typeof ADMIN_SHELL_ROLES)[number])
    );

    if (!user?.id || !matchedAdminRole) {
      return NextResponse.json(
        { error: { code: 'FORBIDDEN', message: 'Akun tidak memiliki hak akses shell admin.' } },
        { status: 403 }
      );
    }

    const signedCookie = await signAdminSessionCookie(user.id, matchedAdminRole);
    const response = NextResponse.json({ ok: true });

    response.cookies.set({
      name: ADMIN_SESSION_COOKIE_NAME,
      value: signedCookie,
      httpOnly: true,
      sameSite: 'lax',
      secure: process.env.NODE_ENV === 'production',
      path: '/',
      maxAge: ADMIN_SESSION_TTL_SECONDS,
    });

    return response;
  } catch {
    return NextResponse.json(
      { error: { code: 'UPSTREAM_ERROR', message: 'Gagal memverifikasi sesi admin.' } },
      { status: 502 }
    );
  }
}

/**
 * P0-08: Hapus cookie HttpOnly sesi admin saat logout.
 */
export async function DELETE() {
  const response = NextResponse.json({ ok: true });
  response.cookies.set({
    name: ADMIN_SESSION_COOKIE_NAME,
    value: '',
    httpOnly: true,
    sameSite: 'lax',
    secure: process.env.NODE_ENV === 'production',
    path: '/',
    maxAge: 0,
  });

  return response;
}
