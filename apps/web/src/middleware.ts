import { NextResponse } from 'next/server';
import type { NextRequest } from 'next/server';
import {
  ADMIN_SESSION_COOKIE_NAME,
  INTERNAL_GATEWAY_HEADER,
  createGatewayInternalToken,
  verifyAdminSessionCookie,
  verifyGatewayInternalToken,
} from '@/lib/auth/admin-session-cookie';

export async function middleware(request: NextRequest) {
  const { pathname } = request.nextUrl;
  const configuredAdminPath = process.env.ADMIN_LOGIN_PATH || '/secret-admin-gate-login';
  // Rewrite internal harus lewat HTTP lokal: di belakang Cloudflare/tunnel,
  // request.url ber-skema https padahal server ini melayani HTTP biasa (EPROTO).
  const internalOrigin =
    process.env.INTERNAL_ORIGIN || `http://127.0.0.1:${process.env.PORT || '3100'}`;

  // 1. Pintu Masuk Rahasia Admin: Rewrite URL yang dikonfigurasi ke gateway internal
  if (configuredAdminPath && pathname === configuredAdminPath) {
    // P0-08: Gunakan token HMAC internal (bukan nilai statis '1') dan hapus header lama
    const headers = new Headers(request.headers);
    headers.delete('x-admin-gateway');
    headers.set(INTERNAL_GATEWAY_HEADER, await createGatewayInternalToken());
    return NextResponse.rewrite(new URL('/admin-gateway-internal', internalOrigin), {
      request: { headers },
    });
  }

  // P0-08: Blokir akses langsung ke route gateway internal meskipun penyerang memalsukan x-admin-gateway: 1
  if (pathname === '/admin-gateway-internal') {
    const internalToken = request.headers.get(INTERNAL_GATEWAY_HEADER);
    const isAuthorizedRewrite = await verifyGatewayInternalToken(internalToken);
    if (!isAuthorizedRewrite) {
      return new NextResponse(null, { status: 404, statusText: 'Not Found' });
    }
  }

  // Jika ADMIN_LOGIN_PATH bukan default, pastikan path default '/secret-admin-gate-login' menghasilkan 404
  if (pathname === '/secret-admin-gate-login' && configuredAdminPath !== '/secret-admin-gate-login') {
    return new NextResponse(null, { status: 404, statusText: 'Not Found' });
  }

  // 2. Proteksi Shell Admin (/admin): Verifikasi cookie HttpOnly bertanda tangan HMAC (P0-08)
  if (pathname === '/admin' || pathname.startsWith('/admin/')) {
    const adminCookie = request.cookies.get(ADMIN_SESSION_COOKIE_NAME)?.value;
    const isValidAdminSession = await verifyAdminSessionCookie(adminCookie);
    if (!isValidAdminSession) {
      return new NextResponse(
        `<!DOCTYPE html><html><head><meta name="robots" content="noindex, nofollow"><title>404: Not Found</title></head><body style="font-family:sans-serif;display:flex;flex-direction:column;align-items:center;justify-content:center;height:100vh;margin:0;background:#f8fafc;color:#1e293b;"><h1 style="font-size:4rem;margin:0;">404</h1><h2 style="margin:1rem 0 0.5rem;">Halaman Tidak Ditemukan</h2><p style="color:#64748b;">Halaman yang Anda cari tidak dapat ditemukan atau telah dipindahkan.</p></body></html>`,
        {
          status: 404,
          statusText: 'Not Found',
          headers: {
            'Content-Type': 'text/html; charset=utf-8',
            'X-Robots-Tag': 'noindex, nofollow',
          },
        }
      );
    }
  }

  return NextResponse.next();
}

export const config = {
  matcher: [
    /*
     * Match all request paths except:
     * - _next/static (static files)
     * - _next/image (image optimization files)
     * - favicon.ico (favicon file)
     * - public files (images, svg, etc.)
     */
    '/((?!_next/static|_next/image|favicon.ico|images|icons).*)',
  ],
};
