/**
 * Konten Terverifikasi & Referensi Berita Publik SMP Laboratorium UM
 *
 * Rujukan:
 * - 13_MASTER_MODULES.md (Modul CMS: WEB-NEWS-01)
 * - 05_PRD_WEB_PPDB.md (Spesifikasi Halaman Berita & Warta)
 * - docs/public-site-inventory.md (Inventaris Berkas berita.html)
 */

export interface NewsArticleItem {
  id: string;
  slug: string;
  title: string;
  category: {
    id: string;
    name: string;
    slug: string;
    badgeStyle: string; // e.g. "bg-[#C89332] text-white"
  };
  published_at: string;
  dateFormatted: string;
  author: string;
  readingTime: string;
  excerpt: string;
  image: string;
  isFeatured?: boolean;
}

export interface NewsCategoryTab {
  id: string;
  name: string;
  slug: string;
}

export const NEWS_CATEGORIES: NewsCategoryTab[] = [
  { id: 'cat-all', name: 'Semua', slug: '' },
  { id: 'cat-prestasi', name: 'Prestasi', slug: 'prestasi' },
  { id: 'cat-akademik', name: 'Akademik', slug: 'akademik' },
  { id: 'cat-kesiswaan', name: 'Kesiswaan', slug: 'kesiswaan' },
  { id: 'cat-pengumuman', name: 'Pengumuman', slug: 'pengumuman' },
  { id: 'cat-agenda', name: 'Agenda', slug: 'agenda' },
];

export const NEWS_FEATURED_ARTICLE: NewsArticleItem = {
  id: 'news-osn-2026',
  slug: 'tim-siswa-smp-lab-raih-emas-osn',
  title: 'SMP Laboratorium UM Borong 5 Medali Emas pada Olimpiade Sains Nasional (OSN) Tingkat Kota Malang',
  category: {
    id: 'cat-prestasi',
    name: 'Prestasi',
    slug: 'prestasi',
    badgeStyle: 'bg-[#C89332] text-white',
  },
  published_at: '2026-10-24T08:00:00Z',
  dateFormatted: '24 Oktober 2026',
  author: 'Tim Humas & Publikasi',
  readingTime: '3 menit baca',
  excerpt:
    'Peserta didik SMP Laboratorium UM kembali menorehkan prestasi gemilang dalam ajang Olimpiade Sains Nasional (OSN) tingkat Kota Malang. Tim berhasil membawa pulang 5 medali emas pada bidang Matematika dan IPA, berkat pembinaan intensif bersama dosen pembina dari Universitas Negeri Malang.',
  image: '/images/news-osn.jpg',
  isFeatured: true,
};

export const NEWS_GRID_ARTICLES: NewsArticleItem[] = [
  {
    id: 'news-lesson-study',
    slug: 'penerapan-model-pembelajaran-lesson-study',
    title: 'Penerapan Model Pembelajaran "Lesson Study" Terbuka Bersama Dosen Pakar Universitas Negeri Malang',
    category: {
      id: 'cat-akademik',
      name: 'Akademik & Riset',
      slug: 'akademik',
      badgeStyle: 'bg-[#0A3D62] text-white',
    },
    published_at: '2026-10-18T08:00:00Z',
    dateFormatted: '18 Oktober 2026',
    author: 'Tim Kurikulum',
    readingTime: '4 menit baca',
    excerpt:
      'Kolaborasi guru mata pelajaran bersama akademisi P2LP UM dalam merancang siklus Plan-Do-See guna meningkatkan daya serap dan nalar kritis siswa di kelas.',
    image: '/images/news-lesson-study.jpg',
  },
  {
    id: 'news-p5-gelar-karya',
    slug: 'pentas-gelar-karya-p5-kreatif',
    title: 'Pentas Gelar Karya P5: Menumbuhkan Kewirausahaan Kreatif dan Kebinekaan Global Siswa',
    category: {
      id: 'cat-kesiswaan',
      name: 'Kesiswaan & Karakter',
      slug: 'kesiswaan',
      badgeStyle: 'bg-[#1E7A46] text-white',
    },
    published_at: '2026-10-12T08:00:00Z',
    dateFormatted: '12 Oktober 2026',
    author: 'Kesiswaan & OSIS',
    readingTime: '3 menit baca',
    excerpt:
      'Ratusan peserta didik kelas VII hingga IX memamerkan produk daur ulang ramah lingkungan, kuliner tradisional, dan pertunjukan seni drama nusantara.',
    image: '/images/news-p5.jpg',
  },
  {
    id: 'news-edaran-asesmen',
    slug: 'surat-edaran-asesmen-sumatif-ganjil',
    title: 'Surat Edaran Pelaksanaan Asesmen Sumatif Akhir Semester Ganjil Tahun Ajaran 2026/2027',
    category: {
      id: 'cat-pengumuman',
      name: 'Pengumuman',
      slug: 'pengumuman',
      badgeStyle: 'bg-[#B91C1C] text-white',
    },
    published_at: '2026-10-05T08:00:00Z',
    dateFormatted: '05 Oktober 2026',
    author: 'Sekretariat Sekolah',
    readingTime: '3 menit baca',
    excerpt:
      'Informasi resmi panduan teknis, tata tertib, dan jadwal pelaksanaan asesmen berbasis komputer untuk seluruh orang tua dan wali murid.',
    image: '/images/news-edaran.jpg',
  },
  {
    id: 'news-futsal-juara',
    slug: 'tim-futsal-smp-lab-raih-juara-2',
    title: 'Tim Futsal SMP Laboratorium UM Raih Juara 2 di Turnamen Pelajar Kota Malang',
    category: {
      id: 'cat-prestasi',
      name: 'Prestasi',
      slug: 'prestasi',
      badgeStyle: 'bg-[#C89332] text-white',
    },
    published_at: '2026-09-28T08:00:00Z',
    dateFormatted: '28 September 2026',
    author: 'Tim Humas & Publikasi',
    readingTime: '2 menit baca',
    excerpt:
      'Tim futsal SMP Laboratorium UM berhasil meraih juara 2 dalam Turnamen Pelajar Kota Malang 2026 setelah melalui perjuangan sengit di partai final.',
    image: '/images/news-futsal.jpg',
  },
  {
    id: 'news-parenting-workshop',
    slug: 'workshop-parenting-kolaborasi-sekolah-orang-tua',
    title: 'Workshop Parenting: Kolaborasi Sekolah dan Orang Tua untuk Tumbuh Kembang Siswa',
    category: {
      id: 'cat-agenda',
      name: 'Agenda',
      slug: 'agenda',
      badgeStyle: 'bg-[#4F46E5] text-white',
    },
    published_at: '2026-09-20T08:00:00Z',
    dateFormatted: '20 September 2026',
    author: 'Humas Sekolah',
    readingTime: '3 menit baca',
    excerpt:
      'SMP Laboratorium UM menyelenggarakan workshop parenting dengan tema "Kolaborasi Sekolah dan Orang Tua untuk Tumbuh Kembang Siswa yang Optimal".',
    image: '/images/news-parenting.jpg',
  },
  {
    id: 'news-modul-ajar-um',
    slug: 'siswa-ikuti-uji-coba-modul-ajar-riset-um',
    title: 'Siswa SMP Laboratorium UM Ikuti Uji Coba Modul Ajar Berbasis Riset Dosen UM',
    category: {
      id: 'cat-akademik',
      name: 'Akademik & Riset',
      slug: 'akademik',
      badgeStyle: 'bg-[#0A3D62] text-white',
    },
    published_at: '2026-09-15T08:00:00Z',
    dateFormatted: '15 September 2026',
    author: 'Tim Kurikulum',
    readingTime: '4 menit baca',
    excerpt:
      'Program uji coba modul ajar adaptif yang dikembangkan oleh dosen FIP UM resmi diterapkan di kelas unggulan sebagai bagian dari penelitian pendidikan.',
    image: '/images/hero-academic.jpg',
  },
];
