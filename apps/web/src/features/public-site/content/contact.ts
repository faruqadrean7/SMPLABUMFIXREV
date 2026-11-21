import {
  SCHOOL_CONTACT_INFO,
} from './navigation';

export interface OfficeHourItem {
  days: string;
  hours: string;
  description: string;
  isSpecial?: boolean;
}

export interface DirectContactChannel {
  id: string;
  title: string;
  subtitle: string;
  value: string;
  displayValue: string;
  href: string;
  actionLabel: string;
  type: 'phone' | 'whatsapp' | 'email';
  badge: string;
}

export const CONTACT_HERO = {
  badge: 'LAYANAN & INFORMASI RESMI',
  title: 'Hubungi & Kunjungi Kami',
  description:
    'Layanan informasi penerimaan peserta didik baru (PPDB), administrasi akademik, dan kemitraan pendidikan SMP Laboratorium UM.',
};

/**
 * Data kontak resmi yang telah diverifikasi dari berkas proyek dan data institusi resmi.
 * Sumber verifikasi:
 * - docs/public-site-inventory.md (L127-L142)
 * - legacy/prototype/public/kontak.html (L74, L115-L131)
 * - legacy/prototype/public/index.html (L420-L436)
 */
export const VERIFIED_CONTACT_DETAILS = {
  schoolName: SCHOOL_CONTACT_INFO.schoolName,
  affiliation: SCHOOL_CONTACT_INFO.affiliation,
  motto: 'Beradab, Kreatif, Berprestasi',
  address: {
    street: 'Jl. Simpang Bogor No. T-7',
    district: 'Sumbersari, Kec. Lowokwaru',
    city: 'Kota Malang',
    province: 'Jawa Timur',
    postalCode: '65145',
    full: 'Jl. Simpang Bogor No. T-7, Sumbersari, Kec. Lowokwaru, Kota Malang, Jawa Timur 65145',
    campusArea: 'Kawasan Kampus Universitas Negeri Malang (P2LP UM)',
    mapsQueryUrl:
      'https://www.google.com/maps/search/?api=1&query=SMP+Laboratorium+Universitas+Negeri+Malang+Jl.+Simpang+Bogor+No.+T-7+Malang',
    mapsEmbedUrl:
      'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3951.442657805177!2d112.61435277494553!3d-7.953118992071536!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x2e7882791789c629%3A0xe54ef5f67b57b6cf!2sUniversitas%20Negeri%20Malang!5e0!3m2!1sid!2sid!4v1700000000000!5m2!1sid!2sid',
  },
  phone: {
    display: '(0341) 588550',
    tel: 'tel:+62341588550',
    label: 'Telepon Kantor',
    description: 'Sekretariat dan administrasi tata usaha sekolah',
  },
  whatsapp: {
    display: '0851-2608-8473',
    url: 'https://wa.me/6285126088473',
    label: 'WhatsApp Admin PPDB',
    description: 'Layanan konsultasi cepat pendaftaran peserta didik baru',
  },
  email: {
    display: 'info@smplab-um.sch.id',
    mailto: 'mailto:info@smplab-um.sch.id',
    label: 'Surel Resmi',
    description: 'Korespondensi kedinasan, kemitraan, dan permohonan informasi',
  },
};

/**
 * Jam pelayanan resmi kantor tata usaha sekolah.
 * Sumber: docs/public-site-inventory.md (L136-L141) & legacy/prototype/public/kontak.html (L74)
 */
export const OFFICE_HOURS: OfficeHourItem[] = [
  {
    days: 'Senin – Jumat',
    hours: '07.00 – 15.30 WIB',
    description: 'Pelayanan administrasi tata usaha, legalisasi dokumen, surat keterangan, dan informasi akademik.',
  },
  {
    days: 'Sabtu',
    hours: '07.30 – 12.00 WIB',
    description: 'Khusus loket pelayanan informasi dan verifikasi berkas PPDB.',
    isSpecial: true,
  },
  {
    days: 'Minggu & Hari Libur Nasional',
    hours: 'Tutup',
    description: 'Pelayanan tatap muka kantor libur. Pertanyaan daring dapat disampaikan via WhatsApp.',
  },
];

/**
 * Kanal kontak langsung dengan aksi yang dapat langsung dieksekusi pengguna.
 */
export const DIRECT_CHANNELS: DirectContactChannel[] = [
  {
    id: 'whatsapp-ppdb',
    title: 'Konsultasi PPDB Online',
    subtitle: 'Konsultasi langsung dengan tim admisi seputar jadwal, alur, persyaratan berkas, dan kuota pendaftaran.',
    value: '0851-2608-8473',
    displayValue: '0851-2608-8473',
    href: 'https://wa.me/6285126088473',
    actionLabel: 'Chat WhatsApp PPDB',
    type: 'whatsapp',
    badge: 'Respons Cepat',
  },
  {
    id: 'phone-office',
    title: 'Sekretariat & Tata Usaha',
    subtitle: 'Panggilan telepon resmi untuk urusan administrasi kedinasan, surat-menyurat, dan informasi umum.',
    value: '(0341) 588550',
    displayValue: '(0341) 588550',
    href: 'tel:+62341588550',
    actionLabel: 'Hubungi via Telepon',
    type: 'phone',
    badge: 'Jam Kerja',
  },
  {
    id: 'email-official',
    title: 'Korespondensi Resmi',
    subtitle: 'Kirim surat resmi, permohonan kemitraan, undangan kedinasan, atau pertanyaan akademik tertulis.',
    value: 'info@smplab-um.sch.id',
    displayValue: 'info@smplab-um.sch.id',
    href: 'mailto:info@smplab-um.sch.id',
    actionLabel: 'Kirim Email Resmi',
    type: 'email',
    badge: 'Email Kedinasan',
  },
];
