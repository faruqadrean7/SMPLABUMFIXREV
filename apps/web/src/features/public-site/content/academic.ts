/**
 * Konten Terverifikasi & Referensi Halaman Akademik Publik SMP Laboratorium UM
 *
 * Rujukan:
 * - 13_MASTER_MODULES.md (Modul CMS: WEB-HOME-01, WEB-PRF-01, WEB-ACA-01)
 * - 05_PRD_WEB_PPDB.md (Spesifikasi Fitur Akademik)
 * - legacy/prototype/public/akademik.html (Referensi Teks Kurikulum & Program)
 * - docs/public-site-inventory.md (Token & Standar Desain Publik)
 */

export interface AcademicProgramDetail {
  id: string;
  name: string;
  category: string;
  badge: string;
  tagline: string;
  description: string;
  keyFeatures: string[];
  focusPillars: string;
}

export interface LearningApproachPoint {
  title: string;
  subtitle: string;
  description: string;
  iconName: 'microscope' | 'book-open' | 'heart-handshake' | 'sparkles';
}

export interface DevelopmentCluster {
  name: string;
  description: string;
  examples: string[];
}

export const ACADEMIC_HERO = {
  badge: 'Kurikulum & Keunggulan Belajar',
  title: 'Program Pendidikan & Pendekatan Akademik',
  affiliation: 'P2LP Universitas Negeri Malang',
  description:
    'Menyelenggarakan pendidikan holistik berbasis ketuntasan belajar, kurikulum nasional, integrasi internasional Cambridge, pembinaan adab, serta layanan inklusi ramah anak sebagai laboratorium model inovasi pembelajaran.',
};

export const ACADEMIC_PROGRAMS: AcademicProgramDetail[] = [
  {
    id: 'reguler',
    name: 'Program Reguler',
    category: 'Standar Nasional Pendidikan (SNP)',
    badge: 'Kurikulum Merdeka',
    tagline: 'Berorientasi Belajar Tuntas & Pembentukan Karakter Mandiri',
    description:
      'Program pendidikan formal berstandar nasional dengan penguatan nalar kritis, modul pembelajaran mandiri, dan metode Lesson Study yang menjamin penguasaan konsep esensial peserta didik secara optimal.',
    keyFeatures: [
      'Penerapan Kurikulum Nasional SNP berbasis capaian pembelajaran tuntas (Mastery Learning)',
      'Pembelajaran kolaboratif dan kontekstual didukung perangkat media digital kelas',
      'Penguatan karakter wajib melalui TaBaQu dan Kepanduan Pramuka ARSIMBO',
      'Pemetaan potensi awal, bakat, dan gaya belajar peserta didik',
    ],
    focusPillars: 'Fondasi Akademik Nasional & Kemandirian Belajar',
  },
  {
    id: 'icp',
    name: 'ICP (International Class Program)',
    category: 'Integrasi Kurikulum Global',
    badge: 'Cambridge Lower Secondary',
    tagline: 'Wawasan Global, Komunikasi Dwibahasa, dan Nalar Internasional',
    description:
      'Program kelas internasional yang memadukan Kurikulum Merdeka nasional dengan kerangka Cambridge International. Dirancang untuk membiasakan siswa bernalar global dalam lingkungan belajar bilingual.',
    keyFeatures: [
      'Pengantar pembelajaran dwibahasa (Bilingual: Bahasa Indonesia & Bahasa Inggris)',
      'Integrasi silabus Cambridge Lower Secondary (English, Mathematics, Science)',
      'Evaluasi berstandar internasional melalui progresi asesmen bertahap',
      'Pengembangan proyek experiential learning dan pengayaan literasi global',
    ],
    focusPillars: 'Kompetensi Global, Literasi Sains & Nalar Kritis',
  },
  {
    id: 'tahfidz',
    name: 'Program Tahfidz Qur\'an',
    category: 'Penguatan Karakter Religius',
    badge: 'Metode Terpadu & Adab',
    tagline: 'Menghafal dengan Tartil, Mengamalkan dengan Beradab',
    description:
      'Program pembinaan hafalan dan tartil Al-Qur\'an intensif yang dipadukan secara harmonis dengan kurikulum akademik reguler, menitikberatkan pada penanaman adab islami dan tata baca fasih.',
    keyFeatures: [
      'Bimbingan tahsin dan tahfidz terstruktur dengan metode talaqqi dan tartil',
      'Target capaian hafalan bertahap didampingi ustadz/ustadzah pembina kompeten',
      'Pembiasaan adab ibadah harian, sholat berjamaah di Masjid Baitul \'Ilmi',
      'Uji capaian (tasmi\' / munaqosah) berkala bersama orang tua siswa',
    ],
    focusPillars: 'Spiritualitas Kokoh, Tartil Qur\'ani & Budi Pekerti',
  },
  {
    id: 'inklusi',
    name: 'Program Pendidikan Inklusi',
    category: 'Layanan Pendidikan Ramah Anak',
    badge: 'Binaan Khusus P2LP UM',
    tagline: 'Setiap Anak Berharga, Tumbuh Bersama Tanpa Diskriminasi',
    description:
      'Layanan pendidikan adaptif ramah anak bagi peserta didik berkebutuhan khusus (PDBK). Menghadirkan lingkungan belajar inklusif yang aman, penuh empati, serta didampingi tenaga pendidik khusus.',
    keyFeatures: [
      'Penyusunan Program Pembelajaran Individual (PPI) adaptif sesuai kebutuhan anak',
      'Pendampingan oleh Guru Pendamping Khusus (GPK) binaan pakar pendidikan inklusi UM',
      'Modifikasi bahan ajar, penyesuaian asesmen, dan ruang bimbingan sensori/konseling',
      'Edukasi empati dan toleransi antar-peserta didik guna mencegah diskriminasi',
    ],
    focusPillars: 'Akses Berkeadilan, Empati Sosial & Kemandirian Siswa',
  },
];

export const LEARNING_APPROACH: LearningApproachPoint[] = [
  {
    title: 'Model Riset & Lesson Study UM',
    subtitle: 'Binaan Pakar Pendidikan Universitas Negeri Malang',
    description:
      'Perencanaan (Plan), pelaksanaan & observasi (Do), serta refleksi (See) pembelajaran dilakukan secara kolaboratif guna terus meningkatkan kualitas interaksi dan pemahaman peserta didik.',
    iconName: 'microscope',
  },
  {
    title: 'Sistem Belajar Tuntas (Mastery Learning)',
    subtitle: 'Modul Pembelajaran Berkesinambungan',
    description:
      'Setiap kompetensi dasar dipelajari hingga tuntas melalui modul adaptif, penugasan bermakna, dan remidiasi terarah sebelum melangkah ke materi tingkat berikutnya.',
    iconName: 'book-open',
  },
  {
    title: 'Penguatan Karakter & Adab Ta\'awun',
    subtitle: 'Integrasi Nilai TaBaQu & PKH',
    description:
      'Menyeimbangkan kecakapan kognitif dengan kematangan emosional dan spiritual. Disiplin, kejujuran, dan kepedulian lingkungan dilatihkan setiap hari dalam kultur sekolah.',
    iconName: 'sparkles',
  },
  {
    title: 'Ekosistem Ramah Anak & Inklusif',
    subtitle: 'Ruang Tumbuh yang Aman dan Nyaman',
    description:
      'Menerapkan prinsip perlindungan anak, anti-perundungan (zero tolerance to bullying), serta suasana belajar yang saling menghargai keberagaman minat dan kemampuan.',
    iconName: 'heart-handshake',
  },
];

export const STUDENT_DEVELOPMENT_AREAS = {
  mandatoryLevels: [
    {
      level: 'Kelas VII',
      program: 'Pendidikan Keterampilan Hidup (PKH) & Pramuka',
      description:
        'Fokus pada pembiasaan kemandirian diri, keterampilan sosial, kepanduan Pramuka ARSIMBO, dan kerja sama tim.',
    },
    {
      level: 'Kelas VIII',
      program: 'TaBaQu (Tahfidz & Tata Baca Al-Qur\'an Terpadu)',
      description:
        'Penguatan tartil, tajwid, adab qur\'ani, serta hafalan surat-surat pilihan sebagai benteng moral remaja.',
    },
  ],
  interestClusters: [
    {
      name: 'Sains, Teknologi & Riset',
      description: 'Pengembangan nalar komputasi, karya ilmiah remaja (KIR), robotika, dan pembinaan olimpiade sains.',
    },
    {
      name: 'Bahasa & Komunikasi Global',
      description: 'Klub bahasa (English Club, Mandarin dasar), public speaking, jurnalistik sekolah, dan broadcasting.',
    },
    {
      name: 'Seni Budaya & Kreativitas',
      description: 'Seni musik, karawitan tradisional, seni tari, fotografi, dan olah desain visual digital.',
    },
    {
      name: 'Olahraga & Kepemimpinan',
      description: 'Futsal, basket, bulu tangkis, bela diri, catur, Palang Merah Remaja (PMR), dan Pasukan Pengibar Bendera (Paskibra).',
    },
  ],
};
