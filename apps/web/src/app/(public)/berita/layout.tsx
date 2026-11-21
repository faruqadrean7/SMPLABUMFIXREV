import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: 'Warta & Pengumuman',
  description:
    'Warta kegiatan, kabar prestasi, agenda akademik, dan pengumuman resmi SMP Laboratorium Universitas Negeri Malang.',
  openGraph: {
    title: 'Warta & Pengumuman — SMP Laboratorium UM',
    description:
      'Informasi publikasi warta, prestasi siswa, dan agenda resmi SMP Laboratorium P2LP UM.',
    type: 'website',
  },
};

export default function NewsLayout({ children }: { children: React.ReactNode }) {
  return <>{children}</>;
}
