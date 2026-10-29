/**
 * Konten Terverifikasi & Referensi Beranda Publik SMP Laboratorium UM
 *
 * Rujukan:
 * - 13_MASTER_MODULES.md (Modul CMS: WEB-HOME-01, WEB-PRF-01, WEB-NEWS-01)
 * - 05_PRD_WEB_PPDB.md (Spesifikasi Beranda & PPDB)
 * - legacy/prototype/public/index.html & profil.html (Referensi Teks & Identitas)
 * - docs/public-site-inventory.md (Inventaris Aset & Kontak)
 */

export interface ProgramItem {
  id: string;
  name: string;
  category: string;
  description: string;
  iconName: 'book-open' | 'globe' | 'book-check' | 'heart-handshake';
  href: string;
}

export interface ApproachPillar {
  title: string;
  category: string;
  description: string;
  iconName: 'building' | 'compass' | 'users' | 'shield';
}

export const HERO_CONTENT = {
  badgeSchool: 'SMP Laboratorium',
  badgeAffiliation: 'P2LP Universitas Negeri Malang',
  tagline: 'Beradab, Kreatif, Berprestasi',
  heading: 'Mewujudkan Generasi Beriman, Berkarakter, Berprestasi, dan Berbudaya',
  description:
    'SMP Laboratorium menyelenggarakan modul pembelajaran inovatif, Lesson Study, dan wawasan global di bawah naungan P2LP Universitas Negeri Malang untuk melahirkan lulusan unggul.',
  ctaPrimary: {
    label: 'Pendaftaran SPMB 2026/2027',
    href: '/ppdb',
  },
  ctaSecondary: {
    label: 'Kenali Sekolah Kami',
    href: '/profil',
  },
  accreditationBadge: 'Terakreditasi A (Unggul)',
  videoThumbnail: 'https://img.youtube.com/vi/BxQztnwi_74/maxresdefault.jpg',
  youtubeEmbedUrl:
    'https://www.youtube-nocookie.com/embed/BxQztnwi_74?autoplay=1&mute=1&loop=1&playlist=BxQztnwi_74&controls=0&showinfo=0&rel=0&iv_load_policy=3&disablekb=1&modestbranding=1&playsinline=1',
};

export const PROFILE_SUMMARY = {
  sectionBadge: 'Sekilas Tentang Kami',
  title: 'Laboratorium Inovasi Pendidikan Universitas Negeri Malang',
  descriptionParagraph1:
    'SMP Laboratorium berada di bawah naungan langsung Pusat Pengembangan Laboratorium Pendidikan (P2LP) Universitas Negeri Malang. Berdiri di lokasi strategis Kota Malang, sekolah ini menjadi laboratorium riset terapan dan implementasi model pembelajaran inovatif yang dibina langsung oleh para akademisi dan pakar pendidikan UM.',
  descriptionParagraph2:
    'Dengan menjunjung tinggi motto "Beradab, Kreatif, Berprestasi", kami memadukan kurikulum berstandar nasional, integrasi kurikulum internasional, bimbingan akhlak religius terpadu, serta pendidikan inklusi yang ramah anak guna melahirkan lulusan berintegritas.',
  principal: {
    name: 'Bapak Tamyis Suliantoro, M.Pd.',
    role: 'Kepala Sekolah SMP Laboratorium',
    greetingTitle: 'Mendidik dengan Hati, Menginspirasi dengan Prestasi',
    greetingQuote:
      'Kami berkomitmen menghadirkan lingkungan belajar yang aman, adaptif, dan membahagiakan bagi setiap anak. Pendidikan bukan sekadar capaian angka akademis, melainkan perjalanan menumbuhkan kemandirian budi pekerti dan ketangguhan karakter masa depan.',
    ctaLabel: 'Baca Sambutan & Profil Lengkap',
    href: '/profil',
  },
};

export const EDUCATIONAL_PROGRAMS: ProgramItem[] = [
  {
    id: 'reguler',
    name: 'Program Reguler',
    category: 'Kurikulum Merdeka SNP',
    description:
      'Penerapan kurikulum nasional berbasis ketuntasan belajar berkelanjutan dengan pendekatan model Lesson Study yang teruji.',
    iconName: 'book-open',
    href: '/akademik',
  },
  {
    id: 'icp',
    name: 'ICP (International Class Program)',
    category: 'Cambridge Integration',
    description:
      'Kurikulum terintegrasi Cambridge International, pembiasaan komunikasi dwibahasa (bilingual), dan penguatan kompetensi nalar global.',
    iconName: 'globe',
    href: '/akademik',
  },
  {
    id: 'tahfidz',
    name: 'Program Tahfidz',
    category: 'Metode Ummi & Adab',
    description:
      'Bimbingan hafalan dan tata baca Al-Qur\'an metode Ummi, pembiasaan ibadah harian, serta pembinaan akhlak mulia.',
    iconName: 'book-check',
    href: '/akademik',
  },
  {
    id: 'inklusi',
    name: 'Program Inklusi',
    category: 'Ramah Anak P2LP UM',
    description:
      'Layanan pendidikan adaptif bagi anak berkebutuhan khusus dengan bimbingan Guru Pendamping Khusus (GPK) binaan pakar UM.',
    iconName: 'heart-handshake',
    href: '/akademik',
  },
];

export const APPROACH_PILLARS: ApproachPillar[] = [
  {
    title: 'Binaan Pakar Pendidikan UM',
    category: 'Laboratorium Model',
    description:
      'Supervisi kurikulum dan pedagogi pengajaran dibina langsung oleh akademisi Universitas Negeri Malang sebagai sekolah model rujukan.',
    iconName: 'building',
  },
  {
    title: 'Penguatan Karakter Wajib',
    category: 'PKH & TaBaQu',
    description:
      'Pendidikan Keterampilan Hidup (Life Skills) di kelas 7 dan Tata Baca Al-Qur\'an (TaBaQu) di kelas 8 untuk membangun kemandirian mental.',
    iconName: 'compass',
  },
  {
    title: 'Pengembangan Minat & Bakat',
    category: '24 Pilihan Ekstrakurikuler',
    description:
      'Mewadahi potensi siswa di bidang sains dan riset (KIR, Math Club), seni budaya, bahasa, teknologi, kepanduan, dan olahraga prestasi.',
    iconName: 'users',
  },
  {
    title: 'Fasilitas Belajar Representatif',
    category: 'Sarana Kampus',
    description:
      'Dilengkapi laboratorium sains, lab komputer CBT, perpustakaan integrasi, arena olahraga serbaguna, dan lingkungan belajar hijau asri.',
    iconName: 'shield',
  },
];

export const PPDB_BANNER_CONTENT = {
  badge: 'Penerimaan Murid Baru 2026/2027',
  title: 'Raih Masa Depan Gemilang Bersama SMP Laboratorium UM',
  description:
    'Sistem pendaftaran daring satu pintu untuk jalur Reguler, Prestasi, ICP, Tahfidz, dan Inklusi. Kuota terbatas untuk memastikan rasio pembelajaran optimal.',
  ctaRegister: {
    label: 'Daftar Akun Calon Siswa',
    href: '/ppdb/daftar',
  },
  ctaInfo: {
    label: 'Pelajari Jalur & Jadwal SPMB',
    href: '/ppdb',
  },
  ctaHelp: {
    label: 'Hubungi Panitia SPMB',
    href: '/kontak',
  },
};
