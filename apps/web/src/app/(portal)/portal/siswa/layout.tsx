'use client';

import React, { useEffect, useState } from 'react';
import Link from 'next/link';
import { usePathname } from 'next/navigation';
import {
  School,
  LogOut,
  Wifi,
  CalendarCheck,
  Menu,
  X,
} from 'lucide-react';
import { useAuth } from '@/providers/auth-provider';
import { RoleGuard } from '@/components/shared/role-guard';
import { RoleSwitcher } from '@/components/shell/shared/role-switcher';
import { NotificationBell } from '@/components/notifications/notification-bell';
import { useActiveAcademicYearLabel } from '@/lib/use-active-academic-year';
import { useStudentDashboard } from '@/lib/use-student-data';
import { getStudentNavLinks } from '@/lib/student-navigation';
import { Labi } from '@/features/portal/mascot/labi';

function normalizeStudentPath(pathname: string): string {
  if (pathname === '/siswa') return '/portal/siswa/dashboard';
  if (pathname.startsWith('/siswa/')) return `/portal${pathname}`;
  return pathname;
}

export default function StudentLayout({ children }: { children: React.ReactNode }) {
  const pathname = usePathname();
  const normalizedPathname = normalizeStudentPath(pathname);
  const { user, logout } = useAuth();
  const academicYearLabel = useActiveAcademicYearLabel();
  const [isSidebarOpen, setIsSidebarOpen] = useState(false);
  const { data: studentDash } = useStudentDashboard();

  // Drawer mobile: tutup otomatis setiap kali rute berubah
  useEffect(() => {
    setIsSidebarOpen(false);
  }, [pathname]);

  // Kunci scroll body saat drawer terbuka di layar kecil
  useEffect(() => {
    if (typeof document === 'undefined') return;
    document.body.style.overflow = isSidebarOpen ? 'hidden' : '';
    return () => {
      document.body.style.overflow = '';
    };
  }, [isSidebarOpen]);

  // Distraction-free full-screen kiosk mode for active CBT exam session
  const isExamTakingSession =
    (normalizedPathname.startsWith('/portal/siswa/cbt/') && normalizedPathname !== '/portal/siswa/cbt') ||
    (normalizedPathname.startsWith('/portal/siswa/ujian/') && normalizedPathname !== '/portal/siswa/ujian');

  if (isExamTakingSession) {
    return (
      <RoleGuard
        portal="siswa"
        loadingMessage="Memverifikasi hak akses sesi ujian CBT..."
        deniedTitle="Akses Ujian Siswa Ditolak"
        deniedDescription="Halaman ini hanya dapat diakses oleh akun Siswa aktif SMP-SMA Laboratorium UM."
      >
        <div className="min-h-screen bg-slate-100 font-sans text-slate-900">{children}</div>
      </RoleGuard>
    );
  }

  const studentName =
    studentDash?.name ||
    user?.profile?.fullName ||
    user?.profile?.name ||
    user?.full_name ||
    user?.username ||
    'Siswa SMP Labschool';
  const studentNis = studentDash?.nis || user?.profile?.nis || '-';
  const studentClass = studentDash?.className || user?.profile?.className || 'Siswa Aktif';
  const studentAvatar =
    studentDash?.photoUrl ||
    (user?.profile?.avatarUrl as string) ||
    (user?.profile?.photo_url as string) ||
    user?.avatar_url ||
    '';

  const studentInitials = studentName
    ? studentName
        .split(' ')
        .slice(0, 2)
        .map((w) => w[0])
        .join('')
        .toUpperCase()
    : 'SW';

  const navLinks = getStudentNavLinks(studentDash?.programType, studentDash?.programProfile);

  return (
    <RoleGuard
      portal="siswa"
      loadingMessage="Memverifikasi hak akses siswa aktif..."
      deniedTitle="Akses Portal Siswa Ditolak"
      deniedDescription="Halaman ini hanya dapat diakses oleh akun Siswa aktif dan administrator SMP Laboratorium UM."
    >
      <div className="portal-siswa siswa-bg flex min-h-screen text-slate-900 font-sans">
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
          className={`w-72 max-w-[85vw] border-r border-indigo-100 bg-white/95 backdrop-blur flex flex-col fixed inset-y-0 left-0 z-50 shadow-xl transition-transform duration-300 ease-out lg:z-30 lg:translate-x-0 lg:shadow-sm ${
            isSidebarOpen ? 'translate-x-0' : '-translate-x-full'
          }`}
          aria-label="Menu navigasi siswa"
        >
          {/* Brand Header */}
          <div className="px-5 py-4 border-b border-indigo-50 flex items-center gap-3">
            <div className="w-10 h-10 rounded-2xl siswa-hero text-white flex items-center justify-center font-bold text-lg shrink-0 shadow-md">
              <School className="w-5 h-5 text-white" />
            </div>
            <div className="min-w-0 flex-1">
              <h1 className="font-extrabold text-sm text-slate-900 tracking-tight leading-tight">LABSCHOOL UM</h1>
              <p className="text-xs text-indigo-500 font-semibold truncate">Portal Belajar Siswa</p>
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

          {/* Profil Siswa Card with Real Avatar */}
          <div className="mx-4 my-3 p-3 bg-gradient-to-br from-indigo-50 to-sky-50 rounded-2xl border border-indigo-100 space-y-2.5 relative overflow-hidden">
            <div className="absolute -right-2 -top-1 opacity-90 pointer-events-none">
              <Labi expression="wave" size={54} />
            </div>
            <div className="flex items-center gap-2.5">
              {studentAvatar ? (
                <img
                  src={studentAvatar}
                  alt={studentName}
                  className="w-10 h-10 rounded-xl object-cover border border-slate-200 shrink-0 shadow-2xs"
                />
              ) : (
                <div className="w-10 h-10 rounded-xl bg-blue-900 text-white font-extrabold flex items-center justify-center text-xs shrink-0 shadow-xs">
                  {studentInitials}
                </div>
              )}
              <div className="min-w-0 flex-1">
                <p className="text-sm font-semibold text-slate-900 truncate">{studentName}</p>
                <p className="text-xs text-slate-500 font-normal truncate">
                  NIS: {studentNis} • {studentClass}
                </p>
              </div>
            </div>

            {/* Status Program Belajar */}
            <div className="pt-2 border-t border-slate-200/80 flex items-center justify-between text-xs">
              <span className="text-slate-500 font-medium">Program:</span>
              <span
                className={`px-2 py-0.5 rounded-md font-bold text-[10px] tracking-wide border ${
                  studentDash?.programType === 'tahfidz'
                    ? 'bg-emerald-50 text-emerald-800 border-emerald-200'
                    : studentDash?.programType === 'icp'
                    ? 'bg-blue-50 text-blue-900 border-blue-200'
                    : studentDash?.programType === 'inklusi'
                    ? 'bg-purple-50 text-purple-900 border-purple-200'
                    : 'bg-slate-100 text-slate-800 border-slate-200'
                }`}
              >
                {studentDash?.programType === 'tahfidz' && 'Tahfidz'}
                {studentDash?.programType === 'icp' && 'ICP Cambridge'}
                {studentDash?.programType === 'inklusi' && 'Inklusi'}
                {(!studentDash?.programType || studentDash?.programType === 'reguler') && 'Reguler'}
              </span>
            </div>

            {/* Status Presensi Siswa */}
            <div className="pt-2 border-t border-slate-200/80 flex items-center justify-between text-xs">
              <span className="text-slate-500 font-medium">Tingkat Hadir:</span>
              <span className="px-2 py-0.5 rounded-md font-bold bg-emerald-50 text-emerald-800 border border-emerald-200 text-[10px] tracking-wide">
                {studentDash?.attendanceRate != null ? `${studentDash.attendanceRate}% Hadir` : 'Belum ada presensi'}
              </span>
            </div>
          </div>

          {/* Navigation Menu */}
          <nav className="flex-1 px-3 py-2 space-y-1 overflow-y-auto">
            <div className="px-3.5 py-1.5 text-[11px] font-semibold uppercase tracking-wider text-slate-400">
              Menu Pembelajaran Siswa
            </div>
            {navLinks.map((link) => {
              const Icon = link.icon;
              const isActive =
                normalizedPathname === link.href ||
                (link.href !== '/portal/siswa/dashboard' &&
                  link.href !== '/portal/siswa' &&
                  normalizedPathname.startsWith(link.href + '/'));
              return (
                <Link
                  key={link.href}
                  href={link.href}
                  className={`flex items-center justify-between px-3.5 py-2.5 rounded-2xl text-sm transition-all ${
                    isActive
                      ? 'siswa-hero text-white font-semibold shadow-md'
                      : 'text-slate-600 hover:bg-indigo-50 hover:text-indigo-900 font-medium'
                  }`}
                >
                  <div className="flex items-center gap-3 min-w-0">
                    <Icon className={`w-4 h-4 shrink-0 ${isActive ? 'text-white' : 'text-slate-500'}`} />
                    <span className="leading-snug">{link.name}</span>
                  </div>
                  {link.badge && (
                    <span
                      className={`ml-2 px-2.5 py-0.5 rounded-full text-xs font-semibold shrink-0 ${
                        isActive ? 'bg-white text-blue-950 font-bold shadow-2xs' : link.badgeColor || 'bg-slate-200 text-slate-800'
                      }`}
                    >
                      {link.badge}
                    </span>
                  )}
                </Link>
              );
            })}
          </nav>

          {/* Bottom Sidebar Profil, RoleSwitcher Berbasis Izin, & Logout */}
          <div className="p-3 border-t border-slate-200 bg-white space-y-2.5">
            <div className="flex items-center gap-2.5 p-2 rounded-xl bg-slate-50 border border-slate-200">
              {studentAvatar ? (
                <img
                  src={studentAvatar}
                  alt={studentName}
                  className="w-9 h-9 rounded-full object-cover border border-slate-200 shrink-0 shadow-2xs"
                />
              ) : (
                <div className="w-9 h-9 rounded-full bg-blue-900 text-white font-bold flex items-center justify-center text-xs shrink-0 shadow-2xs">
                  {studentInitials}
                </div>
              )}
              <div className="min-w-0 flex-1">
                <p className="text-xs font-semibold text-slate-900 truncate">{studentName}</p>
                <p className="text-[11px] text-slate-500 truncate">{studentClass}</p>
              </div>
              <button
                type="button"
                onClick={() => logout()}
                className="p-1.5 rounded-lg text-rose-600 hover:bg-rose-50 transition-colors shrink-0 cursor-pointer"
                aria-label="Keluar Akun Siswa"
                title="Keluar Akun Siswa"
              >
                <LogOut className="w-4 h-4" />
              </button>
            </div>
            <div className="flex justify-center">
              <RoleSwitcher />
            </div>
          </div>
        </aside>

        {/* ─── MAIN CONTENT VIEWPORT ─── */}
        <div className="flex-1 min-w-0 flex flex-col min-h-screen lg:ml-72">
          {/* Top Navbar Bar */}
          <header className="h-16 border-b border-slate-200/90 bg-white flex items-center justify-between gap-2 px-3 sm:px-6 lg:px-8 sticky top-0 z-20 shadow-xs">
            {/* Left: hamburger (mobile) + breadcrumb */}
            <div className="flex items-center gap-2 min-w-0">
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
                <span className="font-medium text-slate-400 uppercase tracking-wider text-[11px]">Portal Siswa</span>
                <span className="text-slate-300 font-light">/</span>
                <span className="font-semibold text-slate-900 text-xs truncate max-w-[9rem]">{studentName}</span>
                <span className="px-2 py-0.5 rounded-md text-xs font-medium bg-slate-100 text-slate-700 font-mono border border-slate-200 shrink-0">
                  {studentClass}
                </span>
              </div>
            </div>

            {/* Right: Dynamic Academic Year Pill, Lab Status, Real Notifications, & Avatar */}
            <div className="flex items-center gap-1.5 sm:gap-3 shrink-0">
              <div className="hidden sm:flex items-center gap-2 px-3 py-1.5 rounded-lg border border-slate-200 bg-slate-50 text-xs font-medium text-slate-600">
                <CalendarCheck className="w-3.5 h-3.5 text-slate-500" />
                <span>{academicYearLabel}</span>
              </div>

              <div className="hidden md:flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 bg-slate-50 text-xs font-medium text-slate-700">
                <Wifi className="w-3.5 h-3.5 text-emerald-600" />
                <span>Lab Komputer Online</span>
              </div>

              <NotificationBell portal="siswa" />

              <div className="flex items-center gap-2.5 pl-2 sm:pl-3 sm:border-l border-slate-200">
                {studentAvatar ? (
                  <img
                    src={studentAvatar}
                    alt={studentName}
                    className="w-8 h-8 rounded-full object-cover border border-slate-200 shrink-0"
                  />
                ) : (
                  <div className="w-8 h-8 rounded-full bg-blue-900 text-white font-bold text-xs flex items-center justify-center shrink-0">
                    {studentInitials}
                  </div>
                )}
                <div className="hidden md:block text-left">
                  <p className="text-xs font-semibold text-slate-900 leading-tight">{studentName}</p>
                  <p className="text-[11px] text-slate-500 font-normal leading-tight">Siswa ({studentClass})</p>
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
