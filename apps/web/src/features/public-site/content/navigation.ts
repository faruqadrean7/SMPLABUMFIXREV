const PORTAL_BASE = process.env.NEXT_PUBLIC_PORTAL_URL || '';
import { NavItem, QuickLinkItem, ContactInfo } from '../types';

/**
 * Daftar menu navigasi utama header website publik
 * Sumber: legacy/prototype/public/index.html (L29-L36)
 */
export const PUBLIC_NAV_ITEMS: NavItem[] = [
  { label: 'Beranda', href: '/' },
  { label: 'Profil', href: '/profil' },
  { label: 'Akademik', href: '/akademik' },
  { label: 'Berita', href: '/berita' },
  { label: 'Galeri', href: '/galeri' },
  { label: 'Kontak', href: '/kontak' },
];

/**
 * Informasi kontak resmi sekolah yang terverifikasi dari berkas legacy
 * Sumber:
 * - legacy/prototype/public/index.html (L420-L436)
 * - legacy/prototype/public/kontak.html (L74, L120-L128)
 * - legacy/prototype/data/seed.js (L28-L30)
 */
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
  { label: 'Berita & Pengumuman', href: '/berita' },
  { label: 'Galeri Aktivitas', href: '/galeri' },
  { label: 'Pendaftaran Siswa Baru (PPDB)', href: '/ppdb' },
];

/**
 * Tautan akses portal akademik untuk footer kolom 3 (Internal Route Apps/Web)
 */
export const FOOTER_PORTAL_LINKS: QuickLinkItem[] = [
  { label: 'Masuk Portal Akademik', href: '/masuk' },
  { label: 'Portal Siswa', href: `${PORTAL_BASE}/siswa` },
  { label: 'Portal Guru & Staf', href: `${PORTAL_BASE}/guru` },
  { label: 'Portal Orang Tua', href: `${PORTAL_BASE}/ortu` },
  { label: 'Informasi PPDB Online', href: '/ppdb' },
];
