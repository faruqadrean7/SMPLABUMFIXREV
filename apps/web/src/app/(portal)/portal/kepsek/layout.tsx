'use client';

import React from 'react';
import { RoleGuard } from '@/components/shared/role-guard';

export default function KepsekPortalLayout({ children }: { children: React.ReactNode }) {
  return (
    <RoleGuard
      portal="kepsek"
      loadingMessage="Memverifikasi hak akses pimpinan eksekutif sekolah..."
      deniedTitle="Akses Portal Pimpinan Ditolak"
      deniedDescription="Halaman ini hanya dapat diakses oleh Kepala Sekolah dan Wakil Kepala Sekolah SMP Laboratorium UM."
    >
      <div className="space-y-6">{children}</div>
    </RoleGuard>
  );
}
