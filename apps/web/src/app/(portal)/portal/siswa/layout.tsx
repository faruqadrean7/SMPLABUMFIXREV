'use client';

import React from 'react';
import { RoleGuard } from '@/components/shared/role-guard';

export default function StudentPortalLayout({ children }: { children: React.ReactNode }) {
  return (
    <RoleGuard
      portal="siswa"
      loadingMessage="Memverifikasi hak akses siswa aktif..."
      deniedTitle="Akses Portal Siswa Ditolak"
      deniedDescription="Halaman ini hanya dapat diakses oleh akun Siswa aktif SMP-SMA Laboratorium UM."
    >
      <div className="space-y-6">{children}</div>
    </RoleGuard>
  );
}
