/**
 * Konten Terverifikasi & Referensi Halaman Profil Publik SMP Laboratorium UM
 *
 * Rujukan:
 * - 13_MASTER_MODULES.md (Modul CMS: WEB-PRF-01)
 * - 05_PRD_WEB_PPDB.md (Spesifikasi Halaman Profil)
 * - legacy/prototype/public/profil.html (Referensi Teks & Kronologi Institusi)
 * - docs/public-site-inventory.md (Inventaris Aset & Kontak Terverifikasi)
 */

export interface HistoryMilestone {
  period: string;
  title: string;
  description: string;
}

export interface MissionItem {
  id: number;
  text: string;
}

export interface IdentityDetail {
  label: string;
  value: string;
  isLink?: boolean;
  href?: string;
}

export const PROFILE_HERO = {
  badge: 'Beradab, Kreatif, Berprestasi',
  title: 'Profil SMP Laboratorium',
  affiliation: 'P2LP Universitas Negeri Malang',
  description:
    'Mengenal lebih dekat dedikasi, rekam jejak historis, visi keunggulan, serta peran strategis SMP Laboratorium sebagai laboratorium model inovasi pendidikan di bawah naungan Universitas Negeri Malang.',
};

export const PRINCIPAL_STATEMENT = {
  badge: 'Sambutan Kepala Sekolah',
  name: 'Bapak Tamyis Suliantoro, M.Pd.',
  role: 'Kepala Sekolah SMP Laboratorium UM',
  title: 'Mendidik Karakter, Menumbuhkan Potensi, Merajut Prestasi',
  paragraphs: [
    'Puji syukur senantiasa kita panjatkan ke hadirat Tuhan Yang Maha Esa. Selamat datang di portal resmi SMP Laboratorium Universitas Negeri Malang, sebuah ekosistem pendidikan yang didedikasikan untuk membentuk insan berakhlak mulia, cerdas bernalar, dan tangguh menghadapi tantangan zaman.',
    'Sebagai sekolah laboratorium yang berada di bawah naungan Pusat Pengembangan Laboratorium Pendidikan (P2LP) Universitas Negeri Malang, kami mengemban amanah tidak hanya sebagai tempat pembelajaran formal, melainkan juga wahana riset, penerapan metode pedagogi inovatif, dan percontohan praktik baik pendidikan bagi masyarakat luas.',
    'Kami meyakini bahwa setiap anak lahir dengan keunikan, potensi, dan bakat yang berharga. Melalui sinergi antara kurikulum berstandar nasional, wawasan global Cambridge, pembinaan budi pekerti berbasis religius, serta lingkungan inklusi yang ramah anak, kami berkomitmen mendampingi setiap peserta didik bertumbuh secara utuh dan membahagiakan.',
  ],
  quote:
    'Pendidikan bukan sekadar capaian angka akademis, melainkan perjalanan menumbuhkan kemandirian budi pekerti dan ketangguhan karakter masa depan.',
};

export const LAB_MODEL_ROLE = {
  badge: 'Sekolah Laboratorium Model',
  title: 'Peran Strategis Bersama Universitas Negeri Malang',
  lead: 'SMP Laboratorium berdiri kokoh dengan landasan akademis yang kuat melalui pembinaan langsung oleh para akademisi dan pakar pendidikan Universitas Negeri Malang.',
  points: [
    {
      title: 'Laboratorium Riset & Model Pembelajaran',
      description:
        'Menjadi wahana implementasi model pembelajaran mutakhir, Lesson Study, serta uji coba modul pembelajaran adaptif hasil riset dosen dan mahasiswa UM.',
    },
    {
      title: 'Pembinaan Berkelanjutan dari Pakar UM',
      description:
        'Pendidik dan tenaga kependidikan mendapatkan bimbingan metodologi, pelatihan pedagogis, dan supervisi klinis secara berkala dari para ahli pendidikan UM.',
    },
    {
      title: 'Integrasi Kurikulum Holistik Berjenjang',
      description:
        'Pengembangan program unggulan berkesinambungan (SD-SMP-SMA Laboratorium UM) yang memadukan Kurikulum Merdeka, Cambridge International, dan penguatan adab.',
    },
    {
      title: 'Layanan Pendidikan Inklusi Ramah Anak',
      description:
        'Penyelenggaraan pendidikan inklusi komprehensif didukung Guru Pendamping Khusus (GPK) terlatih guna memastikan setiap anak mendapatkan hak belajar yang setara.',
    },
  ],
};

export const VISION_MISSION = {
  vision: {
    title: 'Visi Sekolah',
    subtitle: 'Arah Pokok Pengembangan Masa Depan',
    statement:
      'Unggul dalam Iman, Prestasi, Kemandirian, Sosial dan Budi Pekerti serta Berbudaya Lingkungan',
    explanation:
      'Visi ini memadukan keunggulan spiritual (Iman), pencapaian kompetensi nalar dan talenta (Prestasi), ketangguhan karakter pribadi (Kemandirian & Budi Pekerti), kepekaan sosial kemasyarakatan, serta komitmen aktif melestarikan lingkungan hidup.',
    authority: 'Ditetapkan secara resmi oleh P2LP Universitas Negeri Malang',
  },
  mission: {
    title: 'Misi Sekolah',
    subtitle: '7 Langkah Strategis Mewujudkan Visi',
    items: [
      {
        id: 1,
        text: 'Melaksanakan pembelajaran universal dan bimbingan secara efektif sehingga setiap siswa berkembang secara optimal, sesuai dengan potensi yang dimiliki.',
      },
      {
        id: 2,
        text: 'Menumbuhkan semangat keunggulan secara intensif kepada seluruh warga sekolah.',
      },
      {
        id: 3,
        text: 'Mendorong dan membantu setiap siswa untuk mengenali potensi dirinya, sehingga dapat berkembang secara optimal.',
      },
      {
        id: 4,
        text: 'Menumbuhkan dan mendorong keunggulan dalam penerapan ilmu pengetahuan, teknologi dan seni.',
      },
      {
        id: 5,
        text: 'Menumbuhkan penghayatan terhadap ajaran agama yang dianut dan budaya bangsa sehingga terbangun siswa yang kompeten dan berakhlak mulia.',
      },
      {
        id: 6,
        text: 'Mendorong lulusan yang berkualitas, berprestasi, berakhlak tinggi, dan bertaqwa pada Tuhan Yang Maha Esa.',
      },
      {
        id: 7,
        text: 'Meningkatkan kepedulian warga sekolah terhadap pelestarian lingkungan melalui program 3R (Reduce, Reuse, Recycle).',
      },
    ] as MissionItem[],
  },
};

export const SCHOOL_HISTORY: HistoryMilestone[] = [
  {
    period: '1990',
    title: 'Pendirian SMP IKIP Malang',
    description:
      'Berdiri secara resmi pada tahun 1990 dengan nama SMP IKIP Malang di bawah naungan Yayasan Pendidikan Bhinneka Karya Unit Korpri IKIP Malang, mengawali kiprah pengabdian mencerdaskan kehidupan bangsa.',
  },
  {
    period: '1996',
    title: 'Transformasi Yayasan Pendidikan IKIP Malang',
    description:
      'Yayasan Bhinneka Karya secara resmi bertransformasi menjadi Yayasan Pendidikan IKIP Malang, memperkuat fondasi kelembagaan dan tata kelola sarana pendidikan sekolah laboratorium.',
  },
  {
    period: '2005 / 2006',
    title: 'SMP Laboratorium UM & Inovasi Sistem Modul',
    description:
      'Mengikuti transformasi IKIP Malang menjadi Universitas Negeri Malang, sekolah resmi berganti nama menjadi SMP Laboratorium Universitas Negeri Malang. Periode ini ditandai dengan penerapan sistem modul belajar tuntas dan program akselerasi pembelajaran.',
  },
  {
    period: '2009',
    title: 'Peleburan ke dalam BPLP / P2LP UM',
    description:
      'Tepat pada 15 Oktober 2009, Yayasan Pendidikan UM (YPUM) dilebur bersama Unit Pengembangan Sekolah Laboratorium (UPSL) menjadi Pusat Pengembangan Laboratorium Pendidikan (P2LP) Universitas Negeri Malang.',
  },
  {
    period: '2010 / 2011',
    title: 'Perintisan Layanan Inklusi & Kecakapan Hidup',
    description:
      'Sekolah dipercaya oleh Dinas Pendidikan sebagai institusi percontohan penyelenggara kelas inklusi ramah anak yang didampingi Guru Pendamping Khusus (GPK) serta penyediaan sarana life skills dan multimedia.',
  },
  {
    period: '2016 – Sekarang',
    title: 'Integrasi Cambridge ICP & Ekosistem Digital',
    description:
      'Pengelolaan Cambridge International Class Program (ICP) diintegrasikan penuh secara berjenjang (SD-SMP-SMA), memadukan kurikulum internasional dengan penguatan karakter lokal dan adab islami.',
  },
];

export const SCHOOL_IDENTITY_DETAILS: IdentityDetail[] = [
  { label: 'Nama Resmi', value: 'SMP Laboratorium' },
  { label: 'Lembaga Pembina', value: 'P2LP Universitas Negeri Malang' },
  { label: 'Status Akreditasi', value: 'Terakreditasi "A" (Unggul)' },
  { label: 'Tahun Pendirian', value: '1990 (Lebih dari 3 Dekade Mengabdi)' },
  {
    label: 'Alamat Kampus',
    value: 'Jl. Simpang Bogor No. T-7, Sumbersari, Kec. Lowokwaru, Kota Malang, Jawa Timur 65145',
  },
  {
    label: 'Telepon Kantor',
    value: '(0341) 588550',
    isLink: true,
    href: 'tel:+62341588550',
  },
  {
    label: 'WhatsApp Layanan',
    value: '0851-2608-8473',
    isLink: true,
    href: 'https://wa.me/6285126088473',
  },
  {
    label: 'Email Resmi',
    value: 'info@smplab-um.sch.id',
    isLink: true,
    href: 'mailto:info@smplab-um.sch.id',
  },
  { label: 'Kepala Sekolah', value: 'Bapak Tamyis Suliantoro, M.Pd.' },
  {
    label: 'Kurikulum',
    value: 'Kurikulum Merdeka SNP, Cambridge ICP, & Program Adab TaBaQu',
  },
];

export interface FacilityItem {
  id: string;
  name: string;
  category: string;
  description: string;
  highlight: string;
  imageSrc: string;
}

export const SCHOOL_FACILITIES: FacilityItem[] = [
  {
    id: 'lab-sains',
    name: 'Laboratorium Sains & Riset Terpadu',
    category: 'Eksplorasi Ilmiah',
    description:
      'Fasilitas praktikum Fisika, Biologi, dan Kimia terpadu untuk pembelajaran berbasis inkuiri, eksperimen nyata, serta pembinaan Karya Ilmiah Remaja (KIR).',
    highlight: 'Peralatan Praktikum Standar SNP & Pembimbingan Pakar UM',
    imageSrc: '/images/keunggulan-02.jpg',
  },
  {
    id: 'lab-komputer',
    name: 'Laboratorium Komputer & CBT Center',
    category: 'Teknologi & Digital',
    description:
      'Pusat komputasi modern dengan jaringan fiber optik berkecepatan tinggi untuk Asesmen Nasional Berbasis Komputer (ANBK), ujian digital CBT, dan literasi teknologi.',
    highlight: 'Dukungan Ujian Digital Mandiri & Komputer Modern',
    imageSrc: '/images/keunggulan-01.jpg',
  },
  {
    id: 'perpustakaan',
    name: 'Perpustakaan & Pojok Literasi Digital',
    category: 'Pusat Sumber Belajar',
    description:
      'Koleksi literatur kurikulum, buku pengayaan referensi, ensiklopedia sains, serta akses repositori e-library yang terintegrasi dengan jejaring perpustakaan UM.',
    highlight: 'Ruang Baca Ber-AC & Koleksi Referensi Lengkap',
    imageSrc: '/images/keunggulan-04.jpg',
  },
  {
    id: 'inklusi',
    name: 'Ruang Khusus Inklusi & Resource Center',
    category: 'Layanan Ramah Anak',
    description:
      'Ruang pendampingan ramah anak yang dirancang untuk stimulasi dan bimbingan individual peserta didik berkebutuhan khusus dengan dukungan Guru Pendamping Khusus (GPK).',
    highlight: 'Pendampingan Berkelanjutan & Tenaga Ahli Terlatih',
    imageSrc: '/images/keunggulan-03.jpg',
  },
  {
    id: 'lapangan',
    name: 'Lapangan Olahraga & Arena Serbaguna',
    category: 'Kebugaran & Prestasi',
    description:
      'Sarana lapangan terbuka untuk basket, futsal, bulu tangkis, voli, senam kebugaran jasmani, serta kegiatan apel upacara bendera sekolah.',
    highlight: 'Mewadahi 24 Cabang Ekstrakurikuler Minat & Bakat',
    imageSrc: '/images/keunggulan-04.jpg',
  },
  {
    id: 'ibadah',
    name: 'Masjid & Ruang Pembiasaan Ibadah',
    category: 'Pembinaan Karakter Spiritual',
    description:
      'Sarana peribadatan representatif untuk sholat berjamaah harian, sholat Dhuha bersama, setoran hafalan program TaBaQu (Tahfidz Baca Quran), dan keputrian.',
    highlight: 'Pusat Penguatan Adab & Pembiasaan Akhlak Mulia',
    imageSrc: '/images/keunggulan-03.jpg',
  },
];

