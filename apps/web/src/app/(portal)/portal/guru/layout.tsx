'use client';

import React, { useEffect, useState } from 'react';
import Link from 'next/link';
import { usePathname } from 'next/navigation';
import {
  BookOpen,
  CheckSquare,
  LayoutDashboard,
  LogOut,
  GraduationCap,
  School,
  CalendarCheck,
  FileSpreadsheet,
  MonitorPlay,
  Calendar,
  UserCheck,
  Users,
  Building2,
  ShieldCheck,
  Sparkles,
  CalendarDays,
  Clock,
  DollarSign,
  Bell,
  Menu,
  X,
} from 'lucide-react';
import { useAuth } from '@/providers/auth-provider';
import { RoleGuard } from '@/components/shared/role-guard';
import { RoleSwitcher } from '@/components/shell/shared/role-switcher';
import { NotificationBell } from '@/components/notifications/notification-bell';
import { useActiveAcademicYearLabel } from '@/lib/use-active-academic-year';
import { useTeacherClasses, useTeacherSubjects } from '@/lib/use-teacher-data';

interface NavLinkItem {
  name: string;
  href: string;
  icon: React.ComponentType<{ className?: string }>;
  badge?: string;
  badgeColor?: string;
}

interface NavGroupSection {
  title: string;
  items: NavLinkItem[];
}

function normalizeGuruPath(pathname: string): string {
  if (pathname === '/guru') return '/portal/guru/dashboard';
  if (pathname.startsWith('/guru/')) return `/portal${pathname}`;
  return pathname;
}

export default function TeacherLayout({ children }: { children: React.ReactNode }) {
  const pathname = usePathname();
  const normalizedPathname = normalizeGuruPath(pathname);
  const { user, logout } = useAuth();
  const { data: teacherClasses = [] } = useTeacherClasses();
  const { data: teacherSubjects = [] } = useTeacherSubjects();
  const academicYearLabel = useActiveAcademicYearLabel();
  const [isSidebarOpen, setIsSidebarOpen] = useState(false);

  // Drawer mobile: tutup otomatis saat rute berubah
  useEffect(() => {
    setIsSidebarOpen(false);
  }, [pathname]);

  // Kunci scroll body saat drawer terbuka
  useEffect(() => {
    if (typeof document === 'undefined') return;
    document.body.style.overflow = isSidebarOpen ? 'hidden' : '';
    return () => {
      document.body.style.overflow = '';
    };
  }, [isSidebarOpen]);

  const teacherDisplayName =
    user?.profile?.fullName ||
    user?.profile?.name ||
    user?.full_name ||
    user?.name ||
    user?.username ||
    'Pendidik Guru';
  const avatarUrl =
    (user?.profile?.avatarUrl as string) ||
    (user?.profile?.photo_url as string) ||
    user?.avatar_url ||
    '';

  const normalizedRoles = (user?.roles || []).map((r) => String(r).toLowerCase());
  const isHomeroom = normalizedRoles.includes('homeroom');
  const isCounselor = normalizedRoles.includes('counselor') || normalizedRoles.includes('guru_bk');
  const roleLabel = isHomeroom ? 'Wali Kelas' : isCounselor ? 'Guru BK' : 'Guru Pengampu';

  const initials = teacherDisplayName
    ? teacherDisplayName
        .split(' ')
        .filter(Boolean)
        .map((n: string) => n[0])
        .slice(0, 2)
        .join('')
        .toUpperCase()
    : 'GR';

  const primarySubjectLabel =
    teacherSubjects.length > 0
      ? teacherSubjects.length === 1
        ? teacherSubjects[0].name
        : `${teacherSubjects[0].name} +${teacherSubjects.length - 1}`
      : null;

  const navSections: NavGroupSection[] = [
    {
      title: 'Pembelajaran & KBM',
      items: [
        { name: 'Ringkasan KBM', href: '/portal/guru/dashboard', icon: LayoutDashboard },
        { name: 'Jadwal Mengajar', href: '/portal/guru/jadwal', icon: Calendar },
        { name: 'Presensi Kelas', href: '/portal/guru/presensi', icon: CheckSquare },
        { name: 'Jurnal Mengajar', href: '/portal/guru/jurnal', icon: CalendarCheck },
        { name: 'Tugas & Penilaian', href: '/portal/guru/tugas', icon: CheckSquare },
        { name: 'Buku Nilai Ledger', href: '/portal/guru/nilai', icon: FileSpreadsheet },
        { name: 'Bank Soal & CBT', href: '/portal/guru/ujian', icon: MonitorPlay },
        { name: 'Modul Ajar', href: '/portal/guru/materi', icon: BookOpen },
      ],
    },
    {
      title: 'Wali Kelas & Siswa',
      items: [
        {
          name: 'Dasbor Wali Kelas',
          href: '/portal/guru/wali-kelas',
          icon: ShieldCheck,
          badge: isHomeroom ? 'Wali Kelas' : undefined,
          badgeColor: 'bg-blue-100 text-blue-900',
        },
        { name: 'Data Siswa & Poin', href: '/portal/guru/siswa', icon: Users },
        { name: 'Perizinan Siswa', href: '/portal/guru/perizinan', icon: UserCheck },
        { name: 'Program Khusus', href: '/portal/guru/program-khusus', icon: Sparkles },
      ],
    },
    {
      title: 'Kepegawaian & Layanan',
      items: [
        { name: 'Presensi Kerja Saya', href: '/portal/guru/presensi-saya', icon: Clock },
        { name: 'Izin & Cuti Pegawai', href: '/portal/guru/izin-cuti', icon: CalendarDays },
        { name: 'Slip Gaji Guru', href: '/portal/guru/slip-gaji', icon: DollarSign },
        { name: 'Fasilitas & Sarpras', href: '/portal/guru/fasilitas', icon: Building2 },
        { name: 'Notifikasi Portal', href: '/portal/notifikasi', icon: Bell },
      ],
    },
  ];

  return (
    <RoleGuard
      portal="guru"
      loadingMessage="Memverifikasi hak akses dewan guru pengajar..."
      deniedTitle="Akses Portal Guru Ditolak"
      deniedDescription="Halaman ini hanya dapat diakses oleh dewan guru, wali kelas, dan administrator SMP Laboratorium UM."
    >
      <div className="flex min-h-screen bg-slate-100 text-slate-900 font-sans">
        {/* ─── OVERLAY DRAWER (mobile) ─── */}
        {isSidebarOpen && (
          <div
            className="fixed inset-0 z-40 bg-slate-900/50 backdrop-blur-sm lg:hidden"
            onClick={() => setIsSidebarOpen(false)}
            aria-hidden="true"
          />
        )}

        {/* ─── SIDEBAR ─── */}
        <aside
          className={`w-72 max-w-[85vw] border-r border-slate-200/90 bg-white flex flex-col fixed inset-y-0 left-0 z-50 shadow-xl transition-transform duration-300 ease-out lg:z-30 lg:translate-x-0 lg:shadow-xs ${
            isSidebarOpen ? 'translate-x-0' : '-translate-x-full'
          }`}
          aria-label="Menu navigasi guru"
        >
          {/* Brand Header */}
          <div className="px-5 py-4 border-b border-slate-100 flex items-center gap-3 bg-white">
            <div className="w-10 h-10 rounded-xl bg-blue-900 text-white flex items-center justify-center font-bold text-lg shrink-0 shadow-xs">
              <School className="w-5 h-5 text-white" />
            </div>
            <div className="min-w-0 flex-1">
              <h1 className="font-extrabold text-sm text-slate-900 tracking-tight leading-tight">LABSCHOOL UM</h1>
              <p className="text-xs text-slate-500 font-medium truncate">Portal Pembelajaran Guru</p>
            </div>
            <button
              type="button"
              onClick={() => setIsSidebarOpen(false)}
              className="lg:hidden p-2 -mr-1 rounded-xl text-slate-500 hover:bg-slate-100 hover:text-slate-800 transition-colors"
              aria-label="Tutup menu"
            >
              <X className="w-5 h-5" />
            </button>
          </div>

          {/* Navigation Menu (3 Seksi Terstruktur) */}
          <nav className="flex-1 px-3 py-3 space-y-4 overflow-y-auto">
            {navSections.map((section) => (
              <div key={section.title} className="space-y-1">
                <div className="px-3.5 py-1 text-[11px] font-semibold uppercase tracking-wider text-slate-400">
                  {section.title}
                </div>
                {section.items.map((link) => {
                  const Icon = link.icon;
                  const isActive =
                    normalizedPathname === link.href ||
                    (link.href !== '/portal/guru/dashboard' &&
                      link.href !== '/portal/guru' &&
                      normalizedPathname.startsWith(link.href + '/'));
                  return (
                    <Link
                      key={link.href}
                      href={link.href}
                      className={`flex items-center justify-between px-3.5 py-2 rounded-xl text-sm transition-all ${
                        isActive
                          ? 'bg-blue-900 text-white font-semibold shadow-xs'
                          : 'text-slate-600 hover:bg-slate-100/90 hover:text-slate-900 font-medium'
                      }`}
                    >
                      <div className="flex items-center gap-3 min-w-0">
                        <Icon className={`w-4 h-4 shrink-0 ${isActive ? 'text-white' : 'text-slate-500'}`} />
                        <span className="leading-snug truncate">{link.name}</span>
                      </div>
                      {link.badge && (
                        <span
                          className={`ml-2 px-2 py-0.5 rounded-full text-[11px] font-semibold shrink-0 ${
                            isActive ? 'bg-white text-blue-950 font-bold shadow-2xs' : link.badgeColor || 'bg-slate-200 text-slate-800'
                          }`}
                        >
                          {link.badge}
                        </span>
                      )}
                    </Link>
                  );
                })}
              </div>
            ))}
          </nav>

          {/* Footer Profil & Switcher Berbasis Izin */}
          <div className="p-3 border-t border-slate-200 bg-white space-y-2.5">
            <div className="flex items-center gap-2.5 px-2 py-1.5 rounded-xl bg-slate-50 border border-slate-200">
              {avatarUrl ? (
                <img
                  src={avatarUrl}
                  alt={teacherDisplayName}
                  className="w-9 h-9 rounded-full object-cover border border-slate-200 shrink-0"
                />
              ) : (
                <div className="w-9 h-9 rounded-full bg-blue-900 text-white font-bold flex items-center justify-center text-xs shrink-0">
                  {initials}
                </div>
              )}
              <div className="min-w-0 flex-1">
                <p className="text-xs font-semibold text-slate-900 truncate">{teacherDisplayName}</p>
                <p className="text-[11px] text-slate-500 truncate">{roleLabel}</p>
              </div>
              <button
                type="button"
                onClick={() => logout()}
                className="p-1.5 rounded-lg text-rose-600 hover:bg-rose-50 transition-colors shrink-0 cursor-pointer"
                aria-label="Keluar Akun"
                title="Keluar Sesi Guru"
              >
                <LogOut className="w-4 h-4" />
              </button>
            </div>
            <div className="flex justify-center">
              <RoleSwitcher />
            </div>
          </div>
        </aside>

        {/* ─── MAIN CONTENT AREA ─── */}
        <div className="flex-1 min-w-0 flex flex-col min-h-screen lg:ml-72">
          {/* Top Navbar */}
          <header className="h-16 bg-white border-b border-slate-200/90 sticky top-0 z-20 flex items-center justify-between gap-2 px-3 sm:px-6 lg:px-8 shadow-xs">
            {/* Left: hamburger (mobile) + dynamic teaching context */}
            <div className="flex items-center gap-2.5 min-w-0">
              <button
                type="button"
                onClick={() => setIsSidebarOpen(true)}
                className="lg:hidden p-2 -ml-1 rounded-xl text-slate-600 hover:bg-slate-100 active:bg-slate-200 transition-colors shrink-0"
                aria-label="Buka menu navigasi"
                aria-expanded={isSidebarOpen}
              >
                <Menu className="w-5 h-5" />
              </button>
              <div className="hidden sm:flex items-center gap-2 text-xs min-w-0">
                <span className="font-medium text-slate-400 uppercase tracking-wider text-[11px]">
                  Portal Guru & LMS
                </span>
                <span className="text-slate-300 font-light">/</span>
                <span className="font-semibold text-slate-900 text-xs truncate">
                  {teacherDisplayName.split(',')[0]}
                </span>
                {primarySubjectLabel && (
                  <span className="px-2 py-0.5 rounded-md text-xs font-medium bg-slate-100 text-slate-700 border border-slate-200 truncate max-w-[14rem]">
                    {primarySubjectLabel}
                  </span>
                )}
                {teacherClasses.length > 0 && (
                  <span className="hidden md:inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-xs font-medium bg-blue-50 text-blue-900 border border-blue-200">
                    <GraduationCap className="w-3.5 h-3.5" />
                    <span>{teacherClasses.length} Kelas Ampuan</span>
                  </span>
                )}
              </div>
            </div>

            {/* Right Header Actions */}
            <div className="flex items-center gap-2.5 sm:gap-3">
              {/* Dynamic Academic Year Pill */}
              <div className="hidden sm:flex items-center gap-2 px-3 py-1.5 rounded-lg border border-slate-200 bg-slate-50 text-xs font-medium text-slate-600">
                <CalendarCheck className="w-3.5 h-3.5 text-slate-500" />
                <span>{academicYearLabel}</span>
              </div>

              {/* Status Solid Pill */}
              <span className="hidden md:inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-bold bg-emerald-800 text-white shadow-2xs">
                <ShieldCheck className="w-3.5 h-3.5 text-emerald-200" />
                <span>Sistem KBM Aktif</span>
              </span>

              {/* Real Notification Bell */}
              <NotificationBell portal="guru" />

              {/* Avatar Pill */}
              <div className="flex items-center gap-2.5 pl-3 border-l border-slate-200">
                {avatarUrl ? (
                  <img
                    src={avatarUrl}
                    alt={teacherDisplayName}
                    className="w-8 h-8 rounded-full object-cover border border-slate-200 shrink-0"
                  />
                ) : (
                  <div className="w-8 h-8 rounded-full bg-blue-900 text-white font-bold text-xs flex items-center justify-center shrink-0">
                    {initials}
                  </div>
                )}
                <div className="hidden md:block text-left">
                  <p className="text-xs font-semibold text-slate-900 leading-tight">{teacherDisplayName}</p>
                  <p className="text-[11px] text-slate-500 font-normal leading-tight">{roleLabel}</p>
                </div>
              </div>
            </div>
          </header>

          {/* Fluid Page Body Container */}
          <main className="flex-1 w-full min-w-0 px-3 sm:px-6 xl:px-8 py-3 sm:py-4 xl:py-5">{children}</main>
        </div>
      </div>
    </RoleGuard>
  );
}
