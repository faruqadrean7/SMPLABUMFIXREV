export interface NavItem {
  title: string;
  href: string;
  icon: string;
  permission?: string | string[];
  badge?: string;
  children?: NavItem[];
}

export interface NavSection {
  title?: string;
  items: NavItem[];
}

/**
 * Konfigurasi menu navigasi hierarkis Admin Shell SISTLABUM
 */
export const ADMIN_NAV_SECTIONS: NavSection[] = [
  {
    items: [
      {
        title: 'Beranda Admin',
        href: '/admin',
        icon: 'LayoutDashboard',
      },
      {
        title: 'Dashboard Eksekutif',
        href: '/admin/executive',
        icon: 'Activity',
        permission: ['executive.dashboard.view', 'reports.view'],
      },
      {
        title: 'Antrean Persetujuan',
        href: '/admin/executive/approvals',
        icon: 'CheckSquare',
        permission: 'executive.approval.manage',
      },
    ],
  },
  {
    title: 'Akademik & Pembelajaran',
    items: [
      {
        title: 'Master Data',
        href: '/admin/master',
        icon: 'Database',
        permission: [
          'master.academic_year.manage',
          'master.student.read',
          'master.teacher.manage',
          'master.class.manage',
        ],
        children: [
          { title: 'Tahun Ajaran & Semester', href: '/admin/master/academic-years', icon: 'Calendar', permission: 'master.academic_year.manage' },
          { title: 'Data Siswa', href: '/admin/master/students', icon: 'Users', permission: 'master.student.read' },
          { title: 'Data Guru', href: '/admin/master/teachers', icon: 'GraduationCap', permission: 'master.teacher.manage' },
          { title: 'Data Wali Murid', href: '/admin/master/guardians', icon: 'UserCheck', permission: 'master.guardian.manage' },
          { title: 'Rombel & Kelas', href: '/admin/master/classes', icon: 'Layers', permission: 'master.class.manage' },
          { title: 'Mata Pelajaran', href: '/admin/master/subjects', icon: 'BookOpen', permission: 'master.subject.manage' },
        ],
      },
      {
        title: 'KBM & Presensi',
        href: '/admin/academic',
        icon: 'CalendarDays',
        permission: ['academic.schedule.manage', 'academic.journal.write', 'academic.attendance.write'],
        children: [
          { title: 'Jadwal Pelajaran', href: '/admin/academic/schedules', icon: 'Clock', permission: 'academic.schedule.manage' },
          { title: 'Jurnal Mengajar Guru', href: '/admin/academic/journals', icon: 'BookMarked', permission: 'academic.journal.write' },
          { title: 'Presensi KBM Harian', href: '/admin/academic/attendance', icon: 'UserCheck', permission: 'academic.attendance.write' },
          { title: 'Presensi Guru & Staf', href: '/admin/academic/staff-attendance', icon: 'Clock', permission: ['academic.attendance.write', 'academic.schedule.manage'] },
          { title: 'Permohonan Izin KBM', href: '/admin/academic/leave-requests', icon: 'CalendarDays', permission: ['academic.attendance.write', 'academic.schedule.manage'] },
          { title: 'Bahan Ajar & Modul', href: '/admin/academic/teaching-materials', icon: 'Layers', permission: ['academic.schedule.manage', 'academic.journal.write'] },
          { title: 'Tugas Siswa', href: '/admin/academic/assignments', icon: 'Award', permission: ['academic.schedule.manage', 'academic.journal.write'] },
        ],
      },
      {
        title: 'Nilai & E-Rapor',
        href: '/admin/academic/grades',
        icon: 'Award',
        permission: ['academic.grade.write', 'academic.report.generate'],
        children: [
          { title: 'Buku Nilai Mata Pelajaran', href: '/admin/academic/grades', icon: 'FileSpreadsheet', permission: 'academic.grade.write' },
          { title: 'Progress Report Bulanan', href: '/admin/academic/progress-reports', icon: 'FileText', permission: 'academic.report.generate' },
          { title: 'Cetak E-Rapor Semester', href: '/admin/academic/report-cards', icon: 'Printer', permission: 'academic.report.generate' },
        ],
      },
      {
        title: 'Ujian Komputer (CBT)',
        href: '/admin/cbt',
        icon: 'MonitorCheck',
        permission: ['cbt.bank.write', 'cbt.exam.manage', 'cbt.exam.read'],
        children: [
          { title: 'Bank Soal Ujian', href: '/admin/cbt/questions', icon: 'FileQuestion', permission: 'cbt.bank.write' },
          { title: 'Jadwal & Paket Ujian', href: '/admin/cbt/exams', icon: 'CalendarCheck', permission: 'cbt.exam.manage' },
          { title: 'Live Monitor Lab CBT', href: '/admin/cbt/monitor', icon: 'Activity', permission: 'cbt.exam.read' },
        ],
      },
    ],
  },
  {
    title: 'Pelayanan Siswa & Sarana',
    items: [
      {
        title: 'Penerimaan Siswa (PPDB)',
        href: '/admin/ppdb',
        icon: 'UserPlus',
        permission: ['ppdb.wave.manage', 'ppdb.applicant.read', 'ppdb.applicant.verify'],
        children: [
          { title: 'Gelombang & Jalur', href: '/admin/ppdb/waves', icon: 'Settings', permission: 'ppdb.wave.manage' },
          { title: 'Verifikasi Berkas Calon Siswa', href: '/admin/ppdb/applicants', icon: 'CheckSquare', permission: 'ppdb.applicant.verify' },
          { title: 'Seleksi & Perankingan', href: '/admin/ppdb/selection', icon: 'TrendingUp', permission: 'ppdb.selection.run' },
        ],
      },
      {
        title: 'Kesiswaan & Disiplin',
        href: '/admin/student-affairs',
        icon: 'ShieldAlert',
        permission: ['student.point.read', 'student.point.write'],
        children: [
          { title: 'Poin Kedisiplinan & Prestasi', href: '/admin/student-affairs/points', icon: 'Award', permission: 'student.point.write' },
          { title: 'Ekstrakurikuler', href: '/admin/student-affairs/extracurriculars', icon: 'Compass', permission: 'student.point.write' },
          { title: 'Lomba & Prestasi Siswa', href: '/admin/student-affairs/competitions', icon: 'Trophy', permission: 'student.point.write' },
        ],
      },
      {
        title: 'Bimbingan Konseling (BK)',
        href: '/admin/counseling',
        icon: 'HeartHandshake',
        permission: ['counseling.case.read', 'counseling.case.write'],
        children: [
          { title: 'Kasus & Catatan BK', href: '/admin/counseling', icon: 'HeartHandshake', permission: 'counseling.case.read' },
          { title: 'Peringatan Dini (Early Warning)', href: '/admin/counseling/early-warnings', icon: 'ShieldAlert', permission: 'counseling.case.read' },
        ],
      },
      {
        title: 'Sarana & Prasarana',
        href: '/admin/facilities',
        icon: 'Building2',
        permission: ['facility.booking.manage', 'facility.asset.manage'],
        children: [
          { title: 'Fasilitas & Booking', href: '/admin/facilities', icon: 'Building2', permission: 'facility.booking.manage' },
          { title: 'Aset & Inventaris QR', href: '/admin/assets', icon: 'QrCode', permission: 'facility.asset.manage' },
          { title: 'Helpdesk & Tiket Kerusakan', href: '/admin/tickets', icon: 'Wrench', permission: ['facility.booking.manage', 'facility.asset.manage'] },
          { title: 'Perpustakaan & Alat Lab', href: '/admin/library', icon: 'BookOpen', permission: ['facility.asset.manage', 'library.book.manage'] },
        ],
      },
      {
        title: 'Humas, Buku Tamu & Aduan',
        href: '/admin/public-relations',
        icon: 'BookOpenCheck',
        permission: ['guest.book.read', 'guest.book.manage', 'complaint.read', 'complaint.manage'],
      },
    ],
  },
  {
    title: 'Keuangan & Pembayaran',
    items: [
      {
        title: 'Manajemen Keuangan',
        href: '/admin/finance',
        icon: 'CreditCard',
        permission: [
          'finance.fee.manage',
          'finance.invoice.manage',
          'finance.payment.verify',
          'finance.report.view',
        ],
        children: [
          { title: 'Jenis Biaya & Tarif', href: '/admin/finance/fee-types', icon: 'Coins', permission: 'finance.fee.manage' },
          { title: 'Tagihan Siswa', href: '/admin/finance/invoices', icon: 'FileText', permission: 'finance.invoice.manage' },
          { title: 'Verifikasi Pembayaran', href: '/admin/finance/payments', icon: 'CheckSquare', permission: 'finance.payment.verify' },
          { title: 'Rekap Tunggakan', href: '/admin/finance/arrears', icon: 'AlertCircle', permission: 'finance.report.view' },
          { title: 'Laporan Keuangan', href: '/admin/finance/reports', icon: 'BarChart3', permission: 'finance.report.view' },
          { title: 'Cara Bayar & Rekening', href: '/admin/finance/settings', icon: 'Settings', permission: 'finance.fee.manage' },
        ],
      },
    ],
  },
  {
    title: 'Laporan & Pengaturan',
    items: [
      {
        title: 'Pusat Laporan Terpadu',
        href: '/admin/reports',
        icon: 'BarChart3',
        permission: ['reports.view', 'audit.read', 'master.student.read'],
      },
      {
        title: 'CMS Portal Sekolah',
        href: '/admin/cms',
        icon: 'Globe',
        permission: ['cms.site.manage', 'cms.post.manage'],
      },
      {
        title: 'Kotak Masuk Notifikasi',
        href: '/admin/notifications',
        icon: 'Bell',
      },
      {
        title: 'Hak Akses & Pengguna',
        href: '/admin/settings',
        icon: 'Sliders',
        permission: ['auth.role.manage', 'auth.user.read', 'audit.read'],
        children: [
          { title: 'Akun Pengguna', href: '/admin/settings/users', icon: 'Users', permission: 'auth.user.read' },
          { title: 'Matriks Role & Izin', href: '/admin/settings/roles', icon: 'Key', permission: 'auth.role.manage' },
          { title: 'Audit Log Keamanan', href: '/admin/settings/audit-logs', icon: 'FileClock', permission: 'audit.read' },
        ],
      },
    ],
  },
];

export interface RoutePermissionRule {
  href: string;
  permission?: string | string[];
}

/**
 * Rute admin tambahan di luar hierarki menu utama (misal sub-halaman khusus atau alias)
 */
const ADDITIONAL_ADMIN_ROUTES: RoutePermissionRule[] = [
  { href: '/admin/users', permission: 'auth.user.read' },
  { href: '/admin/counseling/early-warnings', permission: ['counseling.case.read', 'counseling.case.write'] },
  { href: '/admin/facilities/library', permission: ['facility.asset.manage', 'library.book.manage'] },
  { href: '/admin/academic/teaching-materials', permission: ['academic.schedule.manage', 'academic.journal.write'] },
  { href: '/admin/academic/assignments', permission: ['academic.schedule.manage', 'academic.journal.write'] },
  { href: '/admin/academic/leave-requests', permission: ['academic.attendance.write', 'academic.schedule.manage'] },
  { href: '/admin/academic/staff-attendance', permission: ['academic.attendance.write', 'academic.schedule.manage'] },
  { href: '/admin/academic/character-notes', permission: ['student.point.write', 'academic.journal.write'] },
  { href: '/admin/finance/settings', permission: 'finance.fee.manage' },
];

/**
 * Mengkompilasi aturan rute yang diurutkan dari href terpanjang ke terpendek
 * agar rute yang lebih spesifik diprioritaskan sebelum fallback ke rute induk.
 */
function buildAdminRouteRules(): RoutePermissionRule[] {
  const rules: RoutePermissionRule[] = [];

  for (const section of ADMIN_NAV_SECTIONS) {
    for (const item of section.items) {
      rules.push({ href: item.href, permission: item.permission });
      if (item.children) {
        for (const child of item.children) {
          rules.push({ href: child.href, permission: child.permission });
        }
      }
    }
  }

  for (const extra of ADDITIONAL_ADMIN_ROUTES) {
    rules.push(extra);
  }

  // Urutkan rute dari yang terpanjang ke terpendek
  return rules.sort((a, b) => b.href.length - a.href.length);
}

const COMPILED_ADMIN_ROUTE_RULES = buildAdminRouteRules();

/**
 * Mengambil permission yang disyaratkan untuk suatu route admin berdasarkan registri navigasi & rute turunan.
 * Mengembalikan undefined jika rute adalah beranda umum (/admin), notifikasi, atau tidak membatasi izin khusus.
 */
export function getAdminRoutePermission(pathname: string): string | string[] | undefined {
  const normalized = pathname.replace(/\/+$/, '') || '/';

  for (const rule of COMPILED_ADMIN_ROUTE_RULES) {
    if (normalized === rule.href || normalized.startsWith(rule.href + '/')) {
      return rule.permission;
    }
  }

  return undefined;
}
