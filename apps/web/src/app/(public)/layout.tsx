import React from 'react';
import type { Metadata } from 'next';
import { SkipLink, PublicHeader, PublicFooter } from '@/components/shell/public';

const fraunces = { variable: '' };
const inter = { variable: '' };

export const metadata: Metadata = {
  metadataBase: process.env.NEXT_PUBLIC_SITE_URL
    ? new URL(process.env.NEXT_PUBLIC_SITE_URL)
    : undefined,
  title: {
    default: 'SMP Laboratorium - Beradab, Kreatif, Berprestasi',
    template: '%s | SMP Laboratorium UM',
  },
  description:
    'Website Resmi SMP Laboratorium - Sekolah Unggulan Berkarakter, Berprestasi, dan Berwawasan Global di bawah naungan P2LP Universitas Negeri Malang.',
};

export default function PublicLayout({ children }: { children: React.ReactNode }) {
  return (
    <div
      className={`${fraunces.variable} ${inter.variable} min-h-screen flex flex-col bg-pub-bg-app text-pub-text-main font-pub-sans selection:bg-pub-gold-soft selection:text-pub-navy-deep`}
    >
      {/* <SkipLink /> */}
      <PublicHeader />
      <div className="flex-1 flex flex-col scroll-mt-20">
        {children}
      </div>
      <PublicFooter />
    </div>
  );
}
