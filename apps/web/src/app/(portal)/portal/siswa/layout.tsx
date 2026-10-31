'use client';

import React, { useState } from 'react';
import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { 
  LayoutDashboard, 
  MonitorPlay, 
  Calendar, 
  CheckSquare, 
  FileSpreadsheet, 
  Award, 
  HeartHandshake, 
  School, 
  Bell, 
  LogOut,
  Wifi,
  ChevronDown,
  CheckCircle2,
  Users,
  CalendarCheck
} from 'lucide-react';
import { useAuth } from '@/lib/auth-context';
import { useStudentDashboard } from '@/lib/use-student-data';
import { RoleGuard } from '@/components/role-guard';
import { getStudentNavLinks } from '@/lib/student-navigation';

export default function StudentLayout({ children }: { children: React.ReactNode }) {
  const pathname = usePathname();
  const { user, logout } = useAuth();
  const [isRoleDropdownOpen, setIsRoleDropdownOpen] = useState(false);
  const { data: studentDash } = useStudentDashboard();

  // Distraction-free full-screen kiosk mode for active CBT exam session
  const isExamTakingSession = pathname.startsWith('/siswa/cbt/') && pathname !== '/siswa/cbt';
  if (isExamTakingSession) {
    return (
      <RoleGuard allowedRoles={['student']} portalName="Portal Siswa">
        <div className="min-h-screen bg-slate-100 font-sans text-slate-900">{children}</div>
      </RoleGuard>
    );
  }

  const studentName = studentDash?.name || user?.profile?.name || user?.username || 'Siswa SMP Labschool';
  const studentNis = studentDash?.nis || user?.profile?.nis || '26001';
  const studentClass = studentDash?.className || user?.profile?.className || 'Kelas 7-A';
  const studentAvatar =
    studentDash?.photoUrl ||
    (user?.profile?.photoUrl as string) ||
    (user?.profile?.photo_url as string) ||
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
    <RoleGuard allowedRoles={['student']} portalName="Portal Siswa">
      <div className="flex min-h-screen bg-slate-100 text-slate-900 font-sans">
        {/* ─── SIDEBAR (Width 288px / w-72 matching Guru & Ortu) ─── */}
        <aside className="w-72 border-r border-slate-200/90 bg-white flex flex-col fixed inset-y-0 left-0 z-30 shadow-xs">
          {/* Brand Header */}
          <div className="px-5 py-4 border-b border-slate-100 flex items-center gap-3 bg-white">
            <div className="w-10 h-10 rounded-xl bg-blue-900 text-white flex items-center justify-center font-bold text-lg shrink-0 shadow-xs">
              <School className="w-5 h-5 text-white" />
            </div>
            <div className="min-w-0">
              <h1 className="font-extrabold text-sm text-slate-900 tracking-tight leading-tight">LABSCHOOL UM</h1>
              <p className="text-xs text-slate-500 font-medium truncate">Portal Pembelajaran Siswa</p>
            </div>
          </div>

          {/* Profil Siswa Card with Real Avatar */}
          <div className="mx-4 my-3 p-3 bg-slate-50 rounded-xl border border-slate-200/90 space-y-2.5">
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
              <span className={`px-2 py-0.5 rounded-md font-bold text-[10px] tracking-wide border ${
                studentDash?.programType === 'tahfidz'
                  ? 'bg-emerald-50 text-emerald-800 border-emerald-200'
                  : studentDash?.programType === 'icp'
                  ? 'bg-blue-50 text-blue-900 border-blue-200'
                  : studentDash?.programType === 'inklusi'
                  ? 'bg-purple-50 text-purple-900 border-purple-200'
                  : 'bg-slate-100 text-slate-800 border-slate-200'
              }`}>
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
                {studentDash?.attendanceRate ? `${studentDash.attendanceRate}% Hadir` : 'Hadir Aktif'}
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
              const isActive = pathname === link.href || (link.href !== '/siswa/dashboard' && link.href !== '/siswa' && pathname.startsWith(link.href));
              return (
                <Link
                  key={link.href}
                  href={link.href}
                  className={`flex items-center justify-between px-3.5 py-2.5 rounded-xl text-sm transition-all ${
                    isActive
                      ? 'bg-blue-900 text-white font-semibold shadow-xs'
                      : 'text-slate-600 hover:bg-slate-100/90 hover:text-slate-900 font-medium'
                  }`}
                >
                  <div className="flex items-center gap-3 min-w-0">
                    <Icon className={`w-4 h-4 shrink-0 ${isActive ? 'text-white' : 'text-slate-500'}`} />
                    <span className="truncate">{link.name}</span>
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

          {/* Bottom Sidebar Meta & Role Switcher */}
          <div className="p-3 border-t border-slate-200 bg-white">
            <div className="relative">
              <button
                onClick={() => setIsRoleDropdownOpen(!isRoleDropdownOpen)}
                className="w-full flex items-center gap-2.5 p-2 rounded-xl hover:bg-slate-50 border border-slate-200 transition-colors text-left group cursor-pointer"
              >
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
                  <p className="text-sm font-semibold text-slate-900 truncate group-hover:text-blue-900">
                    {studentName}
                  </p>
                  <p className="text-xs text-slate-500 truncate font-normal">
                    {studentClass} • <span className="text-blue-900 font-medium">Beralih Portal</span>
                  </p>
                </div>
                <ChevronDown className="w-4 h-4 text-slate-400 group-hover:text-slate-600 shrink-0" />
              </button>

              {isRoleDropdownOpen && (
                <div className="absolute bottom-full left-0 right-0 mb-1.5 bg-white border border-slate-200 rounded-xl shadow-lg py-1.5 z-50 divide-y divide-slate-100">
                  <div className="px-3 py-1">
                    <p className="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Pilih Portal Sekolah</p>
                  </div>
                  <div className="py-1">
                    <Link
                      href="/siswa/dashboard"
                      onClick={() => setIsRoleDropdownOpen(false)}
                      className="flex items-center gap-2.5 px-3 py-2 text-xs font-semibold text-blue-900 bg-blue-50 rounded-lg"
                    >
                      <CheckCircle2 className="w-4 h-4 text-blue-900 shrink-0" />
                      <span>Portal Siswa Web (/siswa)</span>
                    </Link>
                    <Link
                      href="/guru/dashboard"
                      onClick={() => setIsRoleDropdownOpen(false)}
                      className="flex items-center gap-2.5 px-3 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50 hover:text-slate-900 transition-colors rounded-lg"
                    >
                      <School className="w-4 h-4 text-slate-500 shrink-0" />
                      <span>Portal Guru & LMS (/guru)</span>
                    </Link>
                    <Link
                      href="/ortu/dashboard"
                      onClick={() => setIsRoleDropdownOpen(false)}
                      className="flex items-center gap-2.5 px-3 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50 hover:text-slate-900 transition-colors rounded-lg"
                    >
                      <Users className="w-4 h-4 text-emerald-700 shrink-0" />
                      <span>Portal Orang Tua (/ortu)</span>
                    </Link>
                  </div>
                  <div className="pt-1 px-1">
                    <button
                      type="button"
                      onClick={() => {
                        setIsRoleDropdownOpen(false);
                        logout();
                      }}
                      className="w-full flex items-center gap-2 px-3 py-2 text-xs font-medium text-rose-700 hover:bg-rose-50 rounded-lg transition-colors cursor-pointer"
                    >
                      <LogOut className="w-4 h-4 shrink-0" />
                      <span>Keluar Akun Siswa</span>
                    </button>
                  </div>
                </div>
              )}
            </div>
          </div>
        </aside>

        {/* ─── MAIN CONTENT VIEWPORT ─── */}
        <div className="flex-1 ml-72 flex flex-col min-h-screen">
          {/* Top Navbar Bar */}
          <header className="h-16 border-b border-slate-200/90 bg-white flex items-center justify-between px-8 sticky top-0 z-20 shadow-xs">
            {/* Left: Clean Context Breadcrumb */}
            <div className="flex items-center gap-3">
              <div className="flex items-center gap-2 text-xs">
                <span className="font-medium text-slate-400 uppercase tracking-wider text-[11px]">
                  Portal Siswa
                </span>
                <span className="text-slate-300 font-light">/</span>
                <span className="font-semibold text-slate-900 text-xs">
                  {studentName}
                </span>
                <span className="px-2 py-0.5 rounded-md text-xs font-medium bg-slate-100 text-slate-700 font-mono border border-slate-200">
                  {studentClass}
                </span>
              </div>
            </div>

            {/* Right: Academic Year Pill, Lab Status, Notifications, & User Avatar */}
            <div className="flex items-center gap-3">
              {/* Academic Year Pill */}
              <div className="hidden sm:flex items-center gap-2 px-3 py-1.5 rounded-lg border border-slate-200 bg-slate-50 text-xs font-medium text-slate-600">
                <CalendarCheck className="w-3.5 h-3.5 text-slate-500" />
                <span>T.A. 2026/2027 Gasal</span>
              </div>

              {/* Status Lab Online */}
              <div className="hidden md:flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 bg-slate-50 text-xs font-medium text-slate-700">
                <Wifi className="w-3.5 h-3.5 text-emerald-600" />
                <span>Lab Komputer Online</span>
              </div>

              {/* Notification Bell */}
              <button
                aria-label="Notifikasi Siswa"
                className="p-2 rounded-xl bg-slate-50 hover:bg-slate-100 text-slate-600 border border-slate-200 transition-colors relative cursor-pointer"
              >
                <Bell className="w-4 h-4 text-slate-600" />
                <span className="absolute top-1.5 right-1.5 w-2 h-2 rounded-full bg-rose-600"></span>
              </button>

              {/* Avatar & User Details */}
              <div className="flex items-center gap-2.5 pl-3 border-l border-slate-200">
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
          <main className="flex-1 w-full px-6 xl:px-8 py-4 xl:py-5">
            {children}
          </main>
        </div>
      </div>
    </RoleGuard>
  );
}
