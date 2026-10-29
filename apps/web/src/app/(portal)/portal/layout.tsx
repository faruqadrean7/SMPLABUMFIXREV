'use client';

import React, { useEffect } from 'react';
import { useRouter, usePathname } from 'next/navigation';
import { useAuth } from '@/providers/auth-provider';
import { PortalShell } from '@/components/shell';
import { LoadingSpinner } from '@/components/shared/loading-spinner';
import { canAccessShell } from '@/lib/rbac/evaluator';

export default function PortalRootLayout({ children }: { children: React.ReactNode }) {
  const router = useRouter();
  const pathname = usePathname();
  const { user, isAuthenticated, isLoading, activeRole, setActiveRole } = useAuth();

  useEffect(() => {
    if (!isLoading && !isAuthenticated) {
      const redirectUrl = `/masuk?redirect=${encodeURIComponent(pathname || '/portal')}`;
      router.replace(redirectUrl);
    }
  }, [isLoading, isAuthenticated, router, pathname]);

  // GAP-10: Sinkronkan activeRole dengan segmen portal saat deep link atau reload
  useEffect(() => {
    if (isLoading || !isAuthenticated || !user) return;

    let targetPortal: 'siswa' | 'ortu' | 'guru' | 'staf' | 'kepsek' | null = null;
    if (pathname.startsWith('/portal/siswa')) targetPortal = 'siswa';
    else if (pathname.startsWith('/portal/ortu')) targetPortal = 'ortu';
    else if (pathname.startsWith('/portal/guru')) targetPortal = 'guru';
    else if (pathname.startsWith('/portal/staf')) targetPortal = 'staf';
    else if (pathname.startsWith('/portal/kepsek')) targetPortal = 'kepsek';

    if (targetPortal && activeRole !== targetPortal) {
      if (canAccessShell(user.roles, targetPortal)) {
        setActiveRole(targetPortal as any);
      }
    }
  }, [isLoading, isAuthenticated, user, pathname, activeRole, setActiveRole]);

  if (isLoading) {
    return (
      <div className="min-h-screen bg-slate-50 flex items-center justify-center">
        <LoadingSpinner label="Memuat Portal Sekolah..." size="lg" />
      </div>
    );
  }

  if (!isAuthenticated) {
    return null;
  }

  const isDedicatedPortal =
    pathname.startsWith('/portal/siswa') ||
    pathname.startsWith('/portal/guru') ||
    pathname.startsWith('/portal/ortu');

  if (isDedicatedPortal) {
    return <>{children}</>;
  }

  return <PortalShell>{children}</PortalShell>;
}
