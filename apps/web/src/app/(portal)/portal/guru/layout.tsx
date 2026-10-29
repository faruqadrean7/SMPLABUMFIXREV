'use client';

import React from 'react';
import { RoleGuard } from '@/components/shared/role-guard';

export default function TeacherPortalLayout({ children }: { children: React.ReactNode }) {
  return (
    <RoleGuard
      portal="guru"
      loadingMessage="Memverifikasi hak akses dewan guru pengajar..."
      deniedTitle="Akses Portal Guru Ditolak"
      deniedDescription="Halaman ini hanya dapat diakses oleh dewan guru dan wali kelas SMP Laboratorium UM."
    >
      <div className="space-y-6">{children}</div>
    </RoleGuard>
  );
}
