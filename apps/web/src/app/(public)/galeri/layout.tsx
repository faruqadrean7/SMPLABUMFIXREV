import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: 'Galeri Dokumentasi',
  description:
    'Dokumentasi visual kegiatan belajar mengajar, sarana prasarana sekolah, aktivitas kesiswaan, dan video profil resmi SMP Laboratorium UM.',
  openGraph: {
    title: 'Galeri Dokumentasi — SMP Laboratorium UM',
    description:
      'Dokumentasi foto dan video kegiatan, prestasi, serta sarana belajar SMP Laboratorium Universitas Negeri Malang.',
    type: 'website',
  },
};

export default function GalleryLayout({ children }: { children: React.ReactNode }) {
  return <>{children}</>;
}
