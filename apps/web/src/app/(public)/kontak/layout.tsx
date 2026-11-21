import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: 'Hubungi Kami',
  description:
    'Informasi kontak resmi, sekretariat tata usaha, jam pelayanan, saluran konsultasi WhatsApp PPDB, dan peta lokasi kampus SMP Laboratorium Universitas Negeri Malang.',
  openGraph: {
    title: 'Hubungi Kami — SMP Laboratorium UM',
    description:
      'Layanan informasi pendaftaran siswa baru, administrasi akademik, dan kemitraan pendidikan SMP Laboratorium UM.',
    type: 'website',
  },
};

export default function ContactLayout({ children }: { children: React.ReactNode }) {
  return <>{children}</>;
}
