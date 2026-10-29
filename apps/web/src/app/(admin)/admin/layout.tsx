'use client';

import React, { useState } from 'react';
import { usePathname } from 'next/navigation';
import { useAuth } from '@/providers/auth-provider';
import { AdminSidebar } from '@/components/shell/admin/admin-sidebar';
import { AdminTopbar } from '@/components/shell/admin/admin-topbar';
import { LoadingSpinner } from '@/components/shared/loading-spinner';
import { AccessDenied } from '@/components/shared/access-denied';
import { canAccessShell, hasPermission } from '@/lib/rbac/evaluator';
import { getAdminRoutePermission } from '@/lib/rbac/navigation';

export default function AdminLayout({ children }: { children: React.ReactNode }) {
  const pathname = usePathname();
  const { user, isAuthenticated, isLoading } = useAuth();
  const [sidebarOpen, setSidebarOpen] = useState(false);

  if (isLoading) {
    return (
      <div className="min-h-screen bg-slate-50 flex items-center justify-center">
        <LoadingSpinner label="Memverifikasi hak akses administratif..." size="lg" />
      </div>
    );
  }

  if (!isAuthenticated) {
    return (
      <div className="min-h-screen bg-slate-50 flex flex-col items-center justify-center p-4">
        <h1 className="text-6xl font-bold text-slate-800">404</h1>
        <h2 className="text-xl font-semibold text-slate-700 mt-4">Halaman Tidak Ditemukan</h2>
        <p className="text-slate-500 mt-2 text-center max-w-md">
          Halaman yang Anda cari tidak dapat ditemukan atau telah dipindahkan.
        </p>
      </div>
    );
  }

  // Cek otorisasi peran terhadap Shell Admin
  const isAuthorized = canAccessShell((user?.roles as string[]) || [], 'admin');
  if (!isAuthorized) {
    return (
      <div className="min-h-screen bg-slate-50 flex items-center justify-center">
        <AccessDenied
          title="Akses Dibatasi"
          description="Akun Anda terdaftar sebagai Siswa atau Orang Tua (atau guru/staf non-administratif). Halaman manajemen back-office ini hanya dapat diakses oleh staf administrator sekolah."
        />
      </div>
    );
  }

  // Cek otorisasi izin spesifik modul rute saat ini
  const requiredPermission = getAdminRoutePermission(pathname || '/admin');
  const isModuleAuthorized = !requiredPermission || hasPermission(user, requiredPermission);

  return (
    <div className="flex h-screen overflow-hidden bg-slate-50">
      {/* Sidebar Navigasi */}
      <AdminSidebar isOpen={sidebarOpen} onClose={() => setSidebarOpen(false)} />

      {/* Konten Utama */}
      <div className="flex flex-1 flex-col overflow-y-auto overflow-x-hidden">
        <AdminTopbar onToggleSidebar={() => setSidebarOpen(!sidebarOpen)} />
        <main className="flex-1 p-4 sm:p-6 lg:p-8">
          <div className="mx-auto max-w-7xl">
            {isModuleAuthorized ? (
              children
            ) : (
              <AccessDenied
                title="Akses Modul Terbatas"
                description="Akun Anda tidak memiliki izin untuk mengakses modul ini. Silakan gunakan menu navigasi di samping untuk menuju modul yang diizinkan."
              />
            )}
          </div>
        </main>
      </div>
    </div>
  );
}
