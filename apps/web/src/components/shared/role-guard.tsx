'use client';

import React from 'react';
import { useAuth } from '@/providers/auth-provider';
import { canAccessShell } from '@/lib/rbac/evaluator';
import { LoadingSpinner } from '@/components/shared/loading-spinner';
import { AccessDenied } from '@/components/shared/access-denied';

export interface RoleGuardProps {
  portal: 'siswa' | 'ortu' | 'guru' | 'staf' | 'admin' | 'kepsek';
  loadingMessage?: string;
  deniedTitle?: string;
  deniedDescription?: string;
  children: React.ReactNode;
}

const DEFAULT_PORTAL_LABELS: Record<string, { loading: string; title: string; description: string }> = {
  siswa: {
    loading: 'Memverifikasi hak akses siswa aktif...',
    title: 'Akses Portal Siswa Ditolak',
    description: 'Halaman ini hanya dapat diakses oleh akun Siswa aktif SMP-SMA Laboratorium UM.',
  },
  ortu: {
    loading: 'Memverifikasi hak akses orang tua / wali murid...',
    title: 'Akses Portal Orang Tua Ditolak',
    description: 'Halaman ini hanya dapat diakses oleh akun Orang Tua / Wali Murid sah siswa SMP Laboratorium UM.',
  },
  guru: {
    loading: 'Memverifikasi hak akses dewan guru pengajar...',
    title: 'Akses Portal Guru Ditolak',
    description: 'Halaman ini hanya dapat diakses oleh dewan guru dan wali kelas SMP Laboratorium UM.',
  },
  staf: {
    loading: 'Memverifikasi hak akses tenaga kependidikan...',
    title: 'Akses Portal Staf Ditolak',
    description: 'Halaman ini hanya dapat diakses oleh staf kependidikan, tata usaha, dan sarpras SMP Laboratorium UM.',
  },
  kepsek: {
    loading: 'Memverifikasi hak akses pimpinan sekolah...',
    title: 'Akses Portal Pimpinan Ditolak',
    description: 'Halaman ini hanya dapat diakses oleh Kepala Sekolah dan Wakil Kepala Sekolah SMP Laboratorium UM.',
  },
  admin: {
    loading: 'Memverifikasi hak akses manajemen administrator...',
    title: 'Akses Modul Admin Ditolak',
    description: 'Halaman ini hanya dapat diakses oleh staf administrator terverifikasi.',
  },
};

/**
 * Reusable RoleGuard untuk mengamankan sub-portal sekolah
 * Mencegah rendering children atau pemanggilan data hook sebelum otorisasi terkonfirmasi.
 */
export function RoleGuard({
  portal,
  loadingMessage,
  deniedTitle,
  deniedDescription,
  children,
}: RoleGuardProps) {
  const { user, isAuthenticated, isLoading } = useAuth();
  const defaults = DEFAULT_PORTAL_LABELS[portal] || {
    loading: 'Memverifikasi hak akses portal...',
    title: 'Akses Ditolak',
    description: 'Anda tidak memiliki hak akses untuk membuka portal ini.',
  };

  // 1. Tampilkan state loading saat otentikasi belum selesai (anti-flicker)
  if (isLoading) {
    return (
      <div className="min-h-[50vh] flex items-center justify-center">
        <LoadingSpinner label={loadingMessage || defaults.loading} size="md" />
      </div>
    );
  }

  // 2. Jika tidak terautentikasi, jangan render apa pun (layout root akan mengalihkan ke /masuk)
  if (!isAuthenticated || !user) {
    return null;
  }

  // 3. Evaluasi izin shell berdasarkan peran
  const isAuthorized = canAccessShell(user.roles, portal);
  if (!isAuthorized) {
    return (
      <AccessDenied
        title={deniedTitle || defaults.title}
        description={deniedDescription || defaults.description}
      />
    );
  }

  // 4. Render children hanya jika role terbukti sah
  return <>{children}</>;
}
