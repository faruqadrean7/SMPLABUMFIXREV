import { NavItem, QuickLinkItem, ContactInfo } from '../types';

/**
 * Daftar menu navigasi utama header website publik (grouped 6 item top-level untuk desktop 1024px–1280px)
 */
export const PUBLIC_HEADER_NAV_ITEMS: NavItem[] = [
  { label: 'Beranda', href: '/' },
  {
    label: 'Profil',
    href: '/profil',
    children: [
      { label: 'Profil & Sejarah', href: '/profil' },
      { label: 'Pendidik & Staf', href: '/pendidik' },
      { label: 'Galeri Aktivitas', href: '/galeri' },
    ],
  },
  { label: 'Akademik', href: '/akademik' },
  {
    label: 'Informasi',
    href: '/berita',
    children: [
      { label: 'Berita & Pengumuman', href: '/berita' },
      { label: 'Agenda Sekolah', href: '/agenda' },
      { label: 'Buletin & Majalah', href: '/buletin' },
      { label: 'Pusat Unduhan', href: '/unduhan' },
    ],
  },
  { label: 'Kontak', href: '/kontak' },
  { label: 'Lapor & Pengaduan', href: '/lapor' },
];

/**
 * Daftar lengkap seluruh rute navigasi website publik
 */
export const PUBLIC_NAV_ITEMS: NavItem[] = [
  { label: 'Beranda', href: '/' },
  { label: 'Profil', href: '/profil' },
  { label: 'Akademik', href: '/akademik' },
  { label: 'Pendidik', href: '/pendidik' },
  { label: 'Berita', href: '/berita' },
  { label: 'Agenda', href: '/agenda' },
  { label: 'Galeri', href: '/galeri' },
  { label: 'Unduhan', href: '/unduhan' },
  { label: 'Buletin', href: '/buletin' },
  { label: 'Kontak', href: '/kontak' },
  { label: 'Lapor & Pengaduan', href: '/lapor' },
];

export const SCHOOL_CONTACT_INFO: ContactInfo = {
  schoolName: 'SMP Laboratorium',
  affiliation: 'P2LP Universitas Negeri Malang',
  address: 'Jl. Simpang Bogor No. T-7, Sumbersari, Kec. Lowokwaru, Kota Malang 65145',
  phone: {
    display: '(0341) 588550',
    tel: 'tel:+62341588550',
  },
  whatsapp: {
    display: '0851-2608-8473',
    url: 'https://wa.me/6285126088473',
  },
  email: {
    display: 'info@smplab-um.sch.id',
    mailto: 'mailto:info@smplab-um.sch.id',
  },
  copyright: '© 2026 SMP Laboratorium. Seluruh Hak Cipta Dilindungi.',
};

/**
 * Tautan cepat untuk footer kolom 2
 */
export const FOOTER_QUICK_LINKS: QuickLinkItem[] = [
  { label: 'Profil & Sejarah', href: '/profil' },
  { label: 'Kurikulum & Program', href: '/akademik' },
  { label: 'Direktori Pendidik & Staf', href: '/pendidik' },
  { label: 'Berita & Pengumuman', href: '/berita' },
  { label: 'Agenda Kegiatan', href: '/agenda' },
  { label: 'Galeri Aktivitas', href: '/galeri' },
  { label: 'Pusat Unduhan', href: '/unduhan' },
  { label: 'Buletin & Majalah', href: '/buletin' },
  { label: 'Layanan Pengaduan & Lapor Bullying', href: '/lapor' },
  { label: 'Pendaftaran Siswa Baru (PPDB)', href: '/ppdb' },
];

/**
 * Tautan akses portal akademik untuk footer kolom 3 (rute kanonis /portal/*)
 */
export const FOOTER_PORTAL_LINKS: QuickLinkItem[] = [
  { label: 'Masuk Portal Akademik', href: '/masuk' },
  { label: 'Portal Siswa', href: '/portal/siswa' },
  { label: 'Portal Guru & Staf', href: '/portal/guru' },
  { label: 'Portal Orang Tua', href: '/portal/ortu' },
  { label: 'Informasi PPDB Online', href: '/ppdb' },
];
