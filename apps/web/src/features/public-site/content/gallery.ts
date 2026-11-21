/**
 * Konten Terverifikasi & Referensi Galeri Publik SMP Laboratorium UM
 *
 * Rujukan:
 * - 13_MASTER_MODULES.md (Modul CMS: WEB-GAL-01)
 * - 05_PRD_WEB_PPDB.md (Spesifikasi Halaman Galeri)
 * - docs/public-site-inventory.md (Inventaris Berkas galeri.html)
 */

export interface GalleryCategoryTab {
  id: string;
  name: string;
  slug: string;
}

export interface GalleryAlbumDetailItem {
  id: string;
  title: string;
  category: {
    name: string;
    slug: string;
    badgeStyle: string;
  };
  created_at: string;
  dateFormatted: string;
  items_count: number;
  cover: string;
  description: string;
  items?: Array<{
    id: string;
    caption: string;
    url: string;
  }>;
}

export const GALLERY_CATEGORIES: GalleryCategoryTab[] = [
  { id: 'cat-all', name: 'Semua', slug: '' },
  { id: 'cat-akademik', name: 'Akademik', slug: 'akademik' },
  { id: 'cat-kesiswaan', name: 'Kesiswaan', slug: 'kesiswaan' },
  { id: 'cat-sarpras', name: 'Sarana Prasarana', slug: 'sarpras' },
  { id: 'cat-prestasi', name: 'Prestasi', slug: 'prestasi' },
];

export const DEFAULT_GALLERY_ALBUMS: GalleryAlbumDetailItem[] = [
  {
    id: 'album-wisuda-2026',
    title: 'Dokumentasi Wisuda & Purna Siswa Angkatan 2026',
    category: {
      name: 'Prestasi',
      slug: 'prestasi',
      badgeStyle: 'bg-[#C89332] text-white',
    },
    created_at: '2026-06-20T08:00:00Z',
    dateFormatted: '20 Juni 2026',
    items_count: 18,
    cover: '/images/gallery-wisuda.jpg',
    description:
      'Rangkaian prosesi wisuda kelulusan di Graha Cakrawala UM bersama orang tua dan jajaran pimpinan P2LP UM.',
    items: [
      { id: 'w-1', caption: 'Pelepasan siswa berprestasi di panggung Graha Cakrawala', url: '/images/gallery-wisuda.jpg' },
      { id: 'w-2', caption: 'Foto bersama pimpinan sekolah dan dosen pembina P2LP UM', url: '/images/gallery-wisuda.jpg' },
    ],
  },
  {
    id: 'album-riset-biologi',
    title: 'Pekan Riset & Praktikum Terbuka Laboratorium Biologi',
    category: {
      name: 'Akademik',
      slug: 'akademik',
      badgeStyle: 'bg-[#0A3D62] text-white',
    },
    created_at: '2026-08-15T08:00:00Z',
    dateFormatted: '15 Agustus 2026',
    items_count: 12,
    cover: '/images/keunggulan-01.jpg',
    description:
      'Aktivitas siswa mengamati preparat sel mikroskop didampingi guru pembina dan dosen biologi UM.',
    items: [
      { id: 'r-1', caption: 'Pengamatan sel jaringan tumbuhan menggunakan mikroskop binokuler', url: '/images/keunggulan-01.jpg' },
      { id: 'r-2', caption: 'Diskusi kelompok hasil observasi sains terapan di meja lab', url: '/images/hero-academic.jpg' },
    ],
  },
  {
    id: 'album-perjusa-pramuka',
    title: 'Perkemahan Jumat-Sabtu (Perjusa) Kepanduan Pramuka ARSIMBO',
    category: {
      name: 'Kesiswaan',
      slug: 'kesiswaan',
      badgeStyle: 'bg-[#1E7A46] text-white',
    },
    created_at: '2026-09-04T08:00:00Z',
    dateFormatted: '04 September 2026',
    items_count: 24,
    cover: '/images/keunggulan-02.jpg',
    description:
      'Latihan kepemimpinan, penjelajahan alam, pionering tongkat bambu, dan api unggun keakraban.',
    items: [
      { id: 'p-1', caption: 'Regu Pramuka membaca peta penjelajahan di pos rintangan', url: '/images/keunggulan-02.jpg' },
      { id: 'p-2', caption: 'Upacara pembukaan perkemahan di alam terbuka', url: '/images/keunggulan-02.jpg' },
    ],
  },
  {
    id: 'album-gelar-karya-p5',
    title: 'Pentas Gelar Karya P5: Kewirausahaan Kreatif & Budaya Nusantara',
    category: {
      name: 'Kesiswaan',
      slug: 'kesiswaan',
      badgeStyle: 'bg-[#1E7A46] text-white',
    },
    created_at: '2026-10-12T08:00:00Z',
    dateFormatted: '12 Oktober 2026',
    items_count: 16,
    cover: '/images/news-p5.jpg',
    description:
      'Bazar karya daur ulang ramah lingkungan, stand kuliner tradisional, dan drama tari daerah.',
    items: [
      { id: 'k-1', caption: 'Pameran stand kewirausahaan produk kerajinan siswa kelas VII-IX', url: '/images/news-p5.jpg' },
      { id: 'k-2', caption: 'Pertunjukan tari kolosal kebudayaan nusantara', url: '/images/news-p5.jpg' },
    ],
  },
  {
    id: 'album-osn-emas',
    title: 'Penganugerahan Medali Emas Olimpiade Sains Nasional (OSN)',
    category: {
      name: 'Prestasi',
      slug: 'prestasi',
      badgeStyle: 'bg-[#C89332] text-white',
    },
    created_at: '2026-10-24T08:00:00Z',
    dateFormatted: '24 Oktober 2026',
    items_count: 10,
    cover: '/images/news-osn.jpg',
    description:
      'Penyerahan piala dan sertifikat penghargaan juara umum tingkat Kota Malang.',
    items: [
      { id: 'o-1', caption: 'Siswa peraih medali emas OSN berpose di panggung kehormatan', url: '/images/news-osn.jpg' },
      { id: 'o-2', caption: 'Pemberian piagam penghargaan oleh jajaran pembina', url: '/images/news-osn.jpg' },
    ],
  },
  {
    id: 'album-sarpras-kampus',
    title: 'Eksplorasi Sarana Prasarana & Lingkungan Kampus Terpadu',
    category: {
      name: 'Sarana Prasarana',
      slug: 'sarpras',
      badgeStyle: 'bg-[#0284C7] text-white',
    },
    created_at: '2026-07-10T08:00:00Z',
    dateFormatted: '10 Juli 2026',
    items_count: 14,
    cover: '/images/keunggulan-04.jpg',
    description:
      'Fasilitas kelas digital, perpustakaan, masjid, dan lapangan olahraga.',
    items: [
      { id: 's-1', caption: 'Gedung pembelajaran dan taman hijau kampus SMP Laboratorium UM', url: '/images/keunggulan-04.jpg' },
      { id: 's-2', caption: 'Gerbang utama kampus terintegrasi Jl. Simpang Bogor Malang', url: '/images/school-gate.jpg' },
    ],
  },
];

export const GALLERY_VIDEO_SHOWCASE = {
  badge: 'Video Profil Resmi',
  title: 'Video Profil & Suasana Belajar SMP Laboratorium UM',
  description:
    'Saksikan bagaimana kami mengintegrasikan pendidikan, riset, dan karakter dalam satu ekosistem pembelajaran.',
  affiliation: 'P2LP Universitas Negeri Malang',
  youtubeId: 'BxQztnwi_74',
  embedUrl: 'https://www.youtube-nocookie.com/embed/BxQztnwi_74',
  youtubeWatchUrl: 'https://www.youtube.com/watch?v=BxQztnwi_74',
};
