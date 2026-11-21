import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: 'Profil Sekolah',
  description:
    'Profil resmi SMP Laboratorium Universitas Negeri Malang: visi, misi, rekam jejak sejarah, peran sekolah laboratorium model, dan sambutan Kepala Sekolah.',
  openGraph: {
    title: 'Profil Sekolah — SMP Laboratorium UM',
    description:
      'Mengenal visi, misi, sejarah, peran laboratorium riset pendidikan UM, dan komitmen mutu pembinaan insan beradab, kreatif, dan berprestasi.',
    type: 'website',
  },
};

export default function ProfileLayout({ children }: { children: React.ReactNode }) {
  return <>{children}</>;
}
