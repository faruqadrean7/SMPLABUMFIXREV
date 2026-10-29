import { PortalDestination, RoleCode, User } from '@/types/auth';

/**
 * Daftar role administratif dan pengajar yang masuk ke Shell Admin
 */
export const ADMIN_SHELL_ROLES: RoleCode[] = [
  'super_admin',
  'admin_tu',
  'principal',
  'vice_principal',
  'counselor',
  'facility_staff',
  'ppdb_committee',
  'librarian',
  'treasurer',
  'bendahara',
  'student_affairs',
  'public_relations',
  'operator_cms',
  'admin_kurikulum',
  'kurikulum',
];

export const MANDATORY_2FA_ROLES: RoleCode[] = ADMIN_SHELL_ROLES;

export const ROLE_ALIASES: Record<string, RoleCode> = {
  admin: 'admin_tu',
  kepala_sekolah: 'principal',
  treasurer: 'bendahara',
  admin_kurikulum: 'kurikulum',
  guru_bk: 'counselor',
  sarpras: 'facility_staff',
  panitia_ppdb: 'ppdb_committee',
  kesiswaan: 'student_affairs',
  humas: 'public_relations',
};

/**
 * Daftar role dengan hak akses baca-saja (tanpa izin mutasi data operasional)
 */
export const READ_ONLY_ROLES: RoleCode[] = ['committee', 'supervisor'];

/**
 * Cek apakah sekumpulan peran pengguna berstatus baca-saja murni
 */
export function isReadOnlyRole(roles: unknown): boolean {
  const safeRoles = sanitizeRoles(roles);
  if (safeRoles.length === 0) return false;
  const hasReadOnly = safeRoles.some((r) => READ_ONLY_ROLES.includes(r as RoleCode));
  const hasWriteRole = safeRoles.some((r) => !READ_ONLY_ROLES.includes(r as RoleCode));
  return hasReadOnly && !hasWriteRole;
}

/**
 * Fungsi sanitasi & anonimisasi identitas personal siswa untuk role baca-saja (Komite / Pengawas)
 */
export function anonymizeStudentName(name?: string): string {
  if (!name) return 'Siswa ***';
  const trimmed = name.trim();
  if (!trimmed) return 'Siswa ***';
  const parts = trimmed.split(/\s+/);
  if (parts.length === 1) {
    return `${parts[0].slice(0, 1)}***`;
  }
  return `${parts[0].slice(0, 1)}*** ${parts.slice(1).map((p) => p.slice(0, 1) + '.').join(' ')}`;
}

/**
 * Cek apakah pengguna diizinkan melakukan operasi mutasi data (create, update, delete)
 */
export function canPerformWriteOperation(user: User | null | undefined): boolean {
  if (!user) return false;
  const safeRoles = sanitizeRoles(user.roles);
  if (safeRoles.includes('super_admin')) return true;
  if (isReadOnlyRole(safeRoles)) return false;
  return true;
}

/**
 * Sanitasi dan normalisasi input roles agar selalu berupa array of string yang aman
 */
export function sanitizeRoles(roles: unknown): string[] {
  if (!roles || !Array.isArray(roles)) {
    return [];
  }
  const rawRoles = roles.filter((r): r is string => typeof r === 'string' && r.trim().length > 0);
  const normalized: string[] = [];
  for (const r of rawRoles) {
    if (!normalized.includes(r)) {
      normalized.push(r);
    }
    if (ROLE_ALIASES[r] && !normalized.includes(ROLE_ALIASES[r])) {
      normalized.push(ROLE_ALIASES[r]);
    }
    // Bidirectional lookup
    for (const [aliasKey, targetCode] of Object.entries(ROLE_ALIASES)) {
      if (targetCode === r && !normalized.includes(aliasKey)) {
        normalized.push(aliasKey);
      }
    }
  }
  return normalized;
}

/**
 * Tentukan destinasi portal utama pengguna berdasarkan hierarki peran
 * Mencegah fallback universal ke /admin dan mengarahkan non-admin ke portal persona yang tepat
 */
export function resolveUserDestination(roles: unknown): PortalDestination {
  const safeRoles = sanitizeRoles(roles);

  if (safeRoles.length === 0) {
    return 'portal';
  }

  // 1. Siswa murni -> Portal Siswa
  if (safeRoles.includes('student')) {
    return 'siswa';
  }

  // 2. Orang Tua / Wali Murid -> Portal Orang Tua
  if (safeRoles.includes('guardian')) {
    return 'ortu';
  }

  // 3. Pimpinan Sekolah (Kepala Sekolah & Wakasek) -> Portal Pimpinan
  if (safeRoles.some((r) => r === 'principal' || r === 'vice_principal')) {
    return 'kepsek';
  }

  // 4. Guru Mata Pelajaran & Wali Kelas -> Portal Guru
  if (safeRoles.some((r) => r === 'teacher' || r === 'homeroom')) {
    const hasExecutiveRole = safeRoles.some(
      (r) => r === 'super_admin' || r === 'principal' || r === 'vice_principal'
    );
    if (!hasExecutiveRole) {
      return 'guru';
    }
  }

  // 5. Staf TU, Sarpras, Pustakawan & Staf Umum -> Portal Staf
  if (safeRoles.some((r) => r === 'admin_tu' || r === 'facility_staff' || r === 'librarian' || r === 'staff')) {
    const hasExecutiveRole = safeRoles.some(
      (r) => r === 'super_admin' || r === 'principal' || r === 'vice_principal'
    );
    if (!hasExecutiveRole) {
      return 'staf';
    }
  }

  // 6. Super Admin & Staf Administrasi Sistem -> Shell Admin
  if (
    safeRoles.some(
      (r) =>
        r === 'super_admin' ||
        r === 'operator_cms' ||
        r === 'ppdb_committee' ||
        r === 'treasurer' ||
        r === 'student_affairs' ||
        r === 'public_relations' ||
        r === 'counselor'
    )
  ) {
    return 'admin';
  }

  // 7. Role Komite & Pengawas (Mitra) -> Tidak diarahkan ke Admin secara otomatis; arahkan ke Portal Hub
  if (safeRoles.some((r) => r === 'committee' || r === 'supervisor')) {
    return 'portal';
  }

  return 'portal';
}

/**
 * Dapatkan rute awal untuk portal yang dituju
 */
export function getDestinationPath(destination: PortalDestination | string | null | undefined): string {
  switch (destination) {
    case 'siswa':
      return '/portal/siswa';
    case 'ortu':
      return '/portal/ortu';
    case 'guru':
      return '/portal/guru';
    case 'staf':
      return '/portal/staf';
    case 'kepsek':
      return '/portal/kepsek';
    case 'admin':
      return '/admin';
    case 'portal':
      return '/portal';
    default:
      return '/masuk';
  }
}

/**
 * Tentukan destinasi sub-portal (/portal/*) pengguna berdasarkan hierarki peran
 * Menggunakan Single Source of Truth yang sama dengan resolveUserDestination
 */
export function resolvePortalDestination(roles: unknown): PortalDestination {
  return resolveUserDestination(roles);
}

/**
 * Evaluasi portal apa saja yang dapat diakses oleh pengguna (untuk Role Switcher multi-peran)
 */
export function getAccessiblePortals(roles: unknown): Array<{
  portal: PortalDestination;
  label: string;
  href: string;
  iconName: string;
}> {
  const safeRoles = sanitizeRoles(roles);
  const result: Array<{
    portal: PortalDestination;
    label: string;
    href: string;
    iconName: string;
  }> = [];

  const hasAdmin = safeRoles.some((r) => ADMIN_SHELL_ROLES.includes(r as RoleCode));
  const hasTeacher = safeRoles.some((r) => r === 'teacher' || r === 'homeroom');
  const hasStaff = safeRoles.some((r) => r === 'facility_staff' || r === 'librarian' || r === 'admin_tu');
  const hasStudent = safeRoles.includes('student');
  const hasGuardian = safeRoles.includes('guardian');
  const hasKepsek = safeRoles.some((r) => r === 'principal' || r === 'vice_principal');

  if (hasKepsek) {
    result.push({
      portal: 'kepsek',
      label: 'Portal Pimpinan',
      href: '/portal/kepsek',
      iconName: 'Building',
    });
  }

  if (hasTeacher) {
    result.push({
      portal: 'guru',
      label: 'Portal Guru',
      href: '/portal/guru',
      iconName: 'BookOpen',
    });
  }

  if (hasStaff) {
    result.push({
      portal: 'staf',
      label: 'Portal Staf & TU',
      href: '/portal/staf',
      iconName: 'Briefcase',
    });
  }

  if (hasStudent) {
    result.push({
      portal: 'siswa',
      label: 'Portal Siswa',
      href: '/portal/siswa',
      iconName: 'GraduationCap',
    });
  }

  if (hasGuardian) {
    result.push({
      portal: 'ortu',
      label: 'Portal Orang Tua',
      href: '/portal/ortu',
      iconName: 'Users',
    });
  }

  if (hasAdmin) {
    result.push({
      portal: 'admin',
      label: 'Panel Admin & Manajemen',
      href: '/admin',
      iconName: 'Shield',
    });
  }

  return result;
}

/**
 * Cek apakah user memiliki izin akses (permission) tertentu
 */
export function hasPermission(
  user: User | null | undefined,
  requiredPermission?: string | string[]
): boolean {
  if (!requiredPermission) return true;
  if (!user) return false;

  const userRoles = sanitizeRoles(user.roles);
  // Super admin selalu lolos bypass
  if (userRoles.includes('super_admin')) {
    return true;
  }

  const userPermissions = Array.isArray(user.permissions)
    ? user.permissions.filter((p): p is string => typeof p === 'string')
    : [];

  if (Array.isArray(requiredPermission)) {
    // Memerlukan salah satu permission yang cocok
    return requiredPermission.some((perm) => userPermissions.includes(perm));
  }

  return userPermissions.includes(requiredPermission);
}

export function canAccessShell(
  roles: unknown,
  shell: 'admin' | 'siswa' | 'ortu' | 'guru' | 'staf' | 'kepsek'
): boolean {
  const safeRoles = sanitizeRoles(roles);

  if (safeRoles.length === 0) {
    return false;
  }

  if (safeRoles.includes('super_admin')) {
    return true;
  }

  switch (shell) {
    case 'admin':
      return safeRoles.some((r) => ADMIN_SHELL_ROLES.includes(r as RoleCode));
    case 'siswa':
      return safeRoles.includes('student');
    case 'ortu':
      return safeRoles.includes('guardian');
    case 'guru':
      return safeRoles.some((r) => r === 'teacher' || r === 'homeroom');
    case 'staf':
      return (
        safeRoles.includes('staff') ||
        safeRoles.some((r) => ADMIN_SHELL_ROLES.includes(r as RoleCode))
      );
    case 'kepsek':
      return safeRoles.some((r) => r === 'principal' || r === 'vice_principal');
    default:
      return false;
  }
}

/**
 * Mendapatkan seluruh opsi portal yang dapat diakses pengguna (untuk pemilihan portal multi-peran)
 */
export function getPortalRoleOptions(roles: unknown): Array<{
  portal: PortalDestination;
  label: string;
  href: string;
  description: string;
  iconName: string;
}> {
  const safeRoles = sanitizeRoles(roles);
  const result: Array<{
    portal: PortalDestination;
    label: string;
    href: string;
    description: string;
    iconName: string;
  }> = [];

  const isSuperAdmin = safeRoles.includes('super_admin');

  if (isSuperAdmin || safeRoles.some((r) => r === 'principal' || r === 'vice_principal')) {
    result.push({
      portal: 'kepsek',
      label: 'Portal Pimpinan',
      href: '/portal/kepsek',
      description: 'Dashboard KPI sekolah, ringkasan akademik, dan antrean persetujuan pimpinan',
      iconName: 'Building',
    });
  }

  if (isSuperAdmin || safeRoles.includes('student')) {
    result.push({
      portal: 'siswa',
      label: 'Portal Siswa',
      href: '/portal/siswa',
      description: 'Jadwal pelajaran, tugas, nilai rapor, dan ujian CBT',
      iconName: 'GraduationCap',
    });
  }

  if (isSuperAdmin || safeRoles.includes('guardian')) {
    result.push({
      portal: 'ortu',
      label: 'Portal Orang Tua / Wali',
      href: '/portal/ortu',
      description: 'Monitoring presensi, nilai, dan perkembangan ananda',
      iconName: 'Users',
    });
  }

  if (isSuperAdmin || safeRoles.some((r) => r === 'teacher' || r === 'homeroom')) {
    result.push({
      portal: 'guru',
      label: 'Portal Guru',
      href: '/portal/guru',
      description: 'Presensi kelas, jurnal harian, penilaian, materi & tugas',
      iconName: 'BookOpen',
    });
  }

  if (
    isSuperAdmin ||
    safeRoles.includes('staff') ||
    safeRoles.some((r) => ADMIN_SHELL_ROLES.includes(r as RoleCode))
  ) {
    result.push({
      portal: 'staf',
      label: 'Portal Staf & Layanan Pribadi',
      href: '/portal/staf',
      description: 'Presensi mandiri, pengajuan cuti, slip gaji, dan tiket bantuan',
      iconName: 'Briefcase',
    });
  }

  if (
    isSuperAdmin ||
    safeRoles.some(
      (r) =>
        r === 'super_admin' ||
        r === 'operator_cms' ||
        r === 'ppdb_committee' ||
        r === 'treasurer' ||
        r === 'student_affairs' ||
        r === 'public_relations' ||
        r === 'counselor'
    )
  ) {
    result.push({
      portal: 'admin',
      label: 'Panel Admin & Manajemen',
      href: '/admin',
      description: 'Pengaturan sekolah, master data, PPDB, dan laporan analitik',
      iconName: 'Shield',
    });
  }

  return result;
}

