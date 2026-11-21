import { StaffDashboardSummary, StaffTicketItem } from './types';
import { MOCK_SHARED_SELF_ATTENDANCE } from '../shared/shared-portal-mock-data';

export const MOCK_STAFF_DASHBOARD: StaffDashboardSummary = {
  staffName: 'Budi Santoso, S.Kom.',
  department: 'Tata Usaha & Administrasi Sarpras',
  todayAttendance: MOCK_SHARED_SELF_ATTENDANCE,
  pendingLeavesCount: 1,
  openTicketsCount: 2,
  resolvedTicketsCount: 5,
  recentAnnouncements: [
    {
      id: 'anc-1',
      title: 'Pemutakhiran Data Inventarisasi Sarpras Semester Ganjil 2026/2027',
      date: '14 Okt 2026',
      category: 'Tata Usaha',
    },
    {
      id: 'anc-2',
      title: 'Pemeriksaan Rutin Jaringan Listrik & AC Seluruh Ruang Laboratorium',
      date: '12 Okt 2026',
      category: 'Sarpras',
    },
    {
      id: 'anc-3',
      title: 'Pengumuman Jadwal Pelatihan Administrasi Digital Dinas Pendidikan',
      date: '10 Okt 2026',
      category: 'Kepegawaian',
    },
  ],
};

export const MOCK_STAFF_TICKETS: StaffTicketItem[] = [
  {
    id: 'tkt-01',
    code: 'TCK-202610-001',
    title: 'AC Ruang Laboratorium Komputer 2 Bocor dan Kurang Dingin',
    category: 'Laboratorium & Elektronik',
    location: 'Gedung B Lantai 2 - Lab Komputer 2',
    description: 'AC unit 1 meneteskan air ke meja komputer siswa nomor 14-16. Suhu ruangan mencapai 29°C saat KBM berlangsung.',
    priority: 'tinggi',
    status: 'diproses',
    reportedAt: '2026-10-13 08:30',
    updatedAt: '2026-10-14 09:15',
    resolutionNotes: 'Teknisi servis AC eksternal dijadwalkan hadir pukul 13.00 WIB.',
  },
  {
    id: 'tkt-02',
    code: 'TCK-202610-002',
    title: 'Proyektor LCD Ruang Kelas 8-B Tidak Mau Menampilkan Sinyal HDMI',
    category: 'Peralatan Kelas',
    location: 'Gedung A Lantai 1 - Kelas 8-B',
    description: 'Kabel HDMI atau port splitter HDMI longgar sehingga tampilan di layar proyektor berkedip dan warna pudar kebiruan.',
    priority: 'sedang',
    status: 'pending',
    reportedAt: '2026-10-14 07:45',
  },
  {
    id: 'tkt-03',
    code: 'TCK-202610-003',
    title: 'Gagang Pintu Toilet Siswa Putra Rusak / Terkunci dari Dalam',
    category: 'Fasilitas Sanitasi',
    location: 'Gedung C Lantai 1 - Toilet Putra',
    description: 'Silinder kunci pintu bilik 2 macet tidak bisa diputar dari luar.',
    priority: 'darurat',
    status: 'selesai',
    reportedAt: '2026-10-11 10:20',
    updatedAt: '2026-10-11 11:30',
    resolutionNotes: 'Silinder kunci telah diganti baru oleh petugas sarpras.',
  },
  {
    id: 'tkt-04',
    code: 'TCK-202610-004',
    title: 'Lampu LED Plafon Ruang Tata Usaha Berkedip',
    category: 'Kelistrikan',
    location: 'Gedung Utama Lantai 1 - Ruang TU',
    description: 'Lampu LED strip 18W di atas meja pengarsipan berkedip terus-menerus dan menimbulkan suara dengung.',
    priority: 'rendah',
    status: 'selesai',
    reportedAt: '2026-10-08 13:00',
    updatedAt: '2026-10-09 09:00',
    resolutionNotes: 'Driver LED telah diganti dan berfungsi normal kembali.',
  },
];
