import type { Metadata } from 'next';
import './globals.css';
import { AuthProvider } from '@/providers/auth-provider';
import { QueryProvider } from '@/providers/query-provider';
import { Toaster } from '@/components/ui/toast';

export const metadata: Metadata = {
  title: 'SISTLABUM - Portal Sekolah Satu Pintu Labschool UM',
  description: 'Sistem Informasi Siswa Terpadu SMP-SMA Laboratorium Universitas Negeri Malang',
};

export default function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <html lang="id">
      <body className="min-h-screen font-sans bg-slate-50 text-slate-900">
        <QueryProvider>
          <AuthProvider>
            {children}
            <Toaster />
          </AuthProvider>
        </QueryProvider>
      </body>
    </html>
  );
}
