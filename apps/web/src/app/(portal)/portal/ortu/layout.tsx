'use client';

import React from 'react';
import { RoleGuard } from '@/components/shared/role-guard';

export default function ParentPortalLayout({ children }: { children: React.ReactNode }) {
  return (
    <RoleGuard
      portal="ortu"
      loadingMessage="Memverifikasi hak akses orang tua / wali murid..."
      deniedTitle="Akses Portal Orang Tua Ditolak"
      deniedDescription="Halaman ini hanya dapat diakses oleh akun Orang Tua / Wali Murid sah siswa SMP Laboratorium UM."
    >
      <div className="space-y-6">{children}</div>
    </RoleGuard>
  );
}
