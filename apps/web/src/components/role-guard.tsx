'use client';

import React, { useEffect } from 'react';
import { useRouter, usePathname } from 'next/navigation';
import { ShieldAlert, ArrowLeft, LogOut, Loader2 } from 'lucide-react';
import { useAuth } from '@/lib/auth-context';

interface RoleGuardProps {
  children: React.ReactNode;
  allowedRoles: string[];
  portalName: string;
}

export function RoleGuard({ children, allowedRoles, portalName }: RoleGuardProps) {
  const router = useRouter();
  const pathname = usePathname();
  const { user, isAuthenticated, isLoading, logout, getRoleDefaultPath } = useAuth();

  useEffect(() => {
    if (!isLoading && !isAuthenticated) {
      const redirectUrl = `/login?redirect=${encodeURIComponent(pathname)}`;
      router.replace(redirectUrl);
    }
  }, [isLoading, isAuthenticated, pathname, router]);

  if (isLoading) {
    return (
      <div className="min-h-screen bg-slate-50 flex flex-col items-center justify-center p-6 text-slate-800">
        <div className="flex flex-col items-center gap-3">
          <Loader2 className="w-8 h-8 text-blue-900 animate-spin" />
          <p className="text-sm font-medium text-slate-600">Memverifikasi hak akses portal...</p>
        </div>
      </div>
    );
  }

  if (!isAuthenticated || !user) {
    return (
      <div className="min-h-screen bg-slate-50 flex flex-col items-center justify-center p-6 text-slate-800">
        <div className="flex flex-col items-center gap-3">
          <Loader2 className="w-8 h-8 text-blue-900 animate-spin" />
          <p className="text-sm font-medium text-slate-600">Mengarahkan ke halaman login...</p>
        </div>
      </div>
    );
  }

  // Check role authorization
  const userRoles = (user.roles || []).map((r) => String(r).toLowerCase());
  const hasAccess = allowedRoles.some((role) => userRoles.includes(role.toLowerCase()));

  if (!hasAccess) {
    const userRoleDisplay = userRoles.length > 0 ? userRoles.join(', ') : 'Tanpa Peran';
    const destinationPath = getRoleDefaultPath(user);

    return (
      <div className="min-h-screen bg-slate-100 flex items-center justify-center p-4 sm:p-6 font-sans">
        <div className="w-full max-w-md bg-white border border-slate-200 rounded-2xl p-6 sm:p-8 shadow-sm text-center">
          <div className="w-14 h-14 mx-auto mb-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 flex items-center justify-center">
            <ShieldAlert className="w-7 h-7" />
          </div>

          <span className="inline-block px-2.5 py-1 mb-2 text-xs font-semibold rounded-full bg-rose-100 text-rose-800">
            Akses Ditolak (403 Forbidden)
          </span>

          <h1 className="text-lg font-bold text-slate-900 mb-2">
            Tidak Memiliki Akses ke {portalName}
          </h1>

          <p className="text-xs text-slate-600 leading-relaxed mb-6">
            Akun Anda (<strong className="text-slate-800 font-semibold">{user.username}</strong>) terdaftar dengan peran{' '}
            <span className="inline-block px-2 py-0.5 rounded bg-slate-100 border border-slate-300 font-mono text-[11px] text-slate-800">
              {userRoleDisplay}
            </span>
            . Ruang ini khusus diperuntukkan bagi pengguna dengan peran{' '}
            <strong className="text-slate-800 font-semibold">{allowedRoles.join(' / ')}</strong>.
          </p>

          <div className="flex flex-col gap-2.5">
            <button
              onClick={() => router.push(destinationPath)}
              className="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-blue-900 hover:bg-blue-950 text-white rounded-xl text-xs font-semibold shadow-xs transition-colors cursor-pointer"
            >
              <ArrowLeft className="w-4 h-4" />
              <span>Beralih ke Portal Saya</span>
            </button>

            <button
              onClick={() => logout()}
              className="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-medium border border-slate-200 transition-colors cursor-pointer"
            >
              <LogOut className="w-4 h-4 text-slate-500" />
              <span>Keluar Akun ({user.username})</span>
            </button>
          </div>
        </div>
      </div>
    );
  }

  return <>{children}</>;
}
