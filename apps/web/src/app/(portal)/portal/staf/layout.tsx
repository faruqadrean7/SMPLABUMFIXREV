'use client';

import React from 'react';
import { RoleGuard } from '@/components/shared/role-guard';

export default function StaffPortalLayout({ children }: { children: React.ReactNode }) {
  return (
    <RoleGuard
      portal="staf"
      loadingMessage="Memverifikasi hak akses tenaga kependidikan..."
      deniedTitle="Akses Portal Staf Ditolak"
      deniedDescription="Halaman ini hanya dapat diakses oleh staf kependidikan, tata usaha, dan sarpras SMP Laboratorium UM."
    >
      <div className="space-y-6">{children}</div>
    </RoleGuard>
  );
}
