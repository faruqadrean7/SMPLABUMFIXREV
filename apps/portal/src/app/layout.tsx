import type { Metadata } from 'next';
import { Plus_Jakarta_Sans } from 'next/font/google';
import './globals.css';
import Providers from './providers';

const jakarta = Plus_Jakarta_Sans({
  subsets: ['latin'],
  weight: ['400', '500', '600', '700', '800'],
  display: 'swap',
});

export const metadata: Metadata = {
  title: 'Portal Akademik Terpadu | SISTLABUM',
  description: 'Portal Pembelajaran Guru, Siswa, dan Orang Tua SMP-SMA Laboratorium Universitas Negeri Malang',
};

export default function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <html lang="id">
      <body className={`${jakarta.className} min-h-screen bg-slate-100 text-slate-900 antialiased selection:bg-blue-900 selection:text-white`}>
        <Providers>
          {children}
        </Providers>
      </body>
    </html>
  );
}
