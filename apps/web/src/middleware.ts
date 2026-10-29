import { NextResponse } from 'next/server';
import type { NextRequest } from 'next/server';

export function middleware(request: NextRequest) {
  const { pathname } = request.nextUrl;
  const configuredAdminPath = process.env.ADMIN_LOGIN_PATH || '/secret-admin-gate-login';

  // 1. Pintu Masuk Rahasia Admin: Rewrite URL yang dikonfigurasi ke gateway internal
  if (configuredAdminPath && pathname === configuredAdminPath) {
    return NextResponse.rewrite(new URL('/admin-gateway-internal', request.url));
  }

  // Blokir akses langsung ke route gateway internal
  if (pathname === '/admin-gateway-internal') {
    return new NextResponse(null, { status: 404, statusText: 'Not Found' });
  }

  // Jika ADMIN_LOGIN_PATH bukan default, pastikan path default '/secret-admin-gate-login' menghasilkan 404
  if (pathname === '/secret-admin-gate-login' && configuredAdminPath !== '/secret-admin-gate-login') {
    return new NextResponse(null, { status: 404, statusText: 'Not Found' });
  }

  // 2. Proteksi Shell Admin (/admin): Kembalikan HTTP 404 sungguhan jika tidak ada sesi admin
  if (pathname === '/admin' || pathname.startsWith('/admin/')) {
    const adminCookie = request.cookies.get('sistlabum_admin_session')?.value;
    if (adminCookie !== '1') {
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
