import type { Metadata } from 'next';
import React from 'react';

export const metadata: Metadata = {
  title: {
    default: 'SPMB / PPDB Online - SMP Laboratorium UM',
    template: '%s | PPDB SMP Laboratorium UM',
  },
  description:
    'Sistem Penerimaan Murid Baru (SPMB / PPDB) Online SMP Laboratorium UM - P2LP Universitas Negeri Malang.',
};

export default function PPDBRootLayout({ children }: { children: React.ReactNode }) {
  return (
    <div className="min-h-screen bg-slate-50 text-slate-900 font-sans selection:bg-amber-100 selection:text-blue-900">
      {children}
    </div>
  );
}
