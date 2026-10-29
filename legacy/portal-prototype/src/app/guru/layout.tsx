'use client';

import React, { useState } from 'react';
import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { 
  BookOpen, 
  CheckSquare, 
  LayoutDashboard, 
  LogOut, 
  GraduationCap, 
  Bell, 
  School,
  CalendarCheck,
  FileSpreadsheet,
  MonitorPlay,
  Calendar,
  UserCheck,
  Users,
  Building2,
  ShieldCheck,
  ChevronDown,
  CheckCircle2
} from 'lucide-react';
import { useAuth } from '@/lib/auth-context';
import { RoleGuard } from '@/components/role-guard';
import { useTeacherClasses, useTeacherSubjects } from '@/lib/use-teacher-data';

export default function TeacherLayout({ children }: { children: React.ReactNode }) {
  const pathname = usePathname();
  const { user, logout } = useAuth();
  const { data: teacherClasses = [] } = useTeacherClasses();
  const { data: teacherSubjects = [] } = useTeacherSubjects();

  const [selectedClassId, setSelectedClassId] = useState('');
  const [selectedSubjectId, setSelectedSubjectId] = useState('');
  const [isRoleDropdownOpen, setIsRoleDropdownOpen] = useState(false);

  React.useEffect(() => {
    if (!selectedClassId && teacherClasses.length > 0) {
      setSelectedClassId(teacherClasses[0].id);
    }
  }, [teacherClasses, selectedClassId]);

  React.useEffect(() => {
    if (!selectedSubjectId && teacherSubjects.length > 0) {
      setSelectedSubjectId(teacherSubjects[0].id);
    }
  }, [teacherSubjects, selectedSubjectId]);

  const teacherDisplayName = user?.full_name || user?.name || 'Agus Santoso, M.Pd.';
  const isHomeroom = (user?.roles || []).some((r: any) => String(r).toLowerCase() === 'homeroom');
  const isCounselor = (user?.roles || []).some((r: any) => String(r).toLowerCase() === 'counselor');
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

  const activeClasses = teacherClasses.map(c => ({ id: c.id, name: c.name, total_students: c.studentCount }));


  const navLinks = [
    {
      name: 'Ringkasan KBM',
      href: '/guru/dashboard',
      icon: LayoutDashboard,
    },
    {
      name: 'Jurnal & Presensi',
      href: '/guru/jurnal',
      icon: CalendarCheck,
      badge: 'Hari Ini',
      badgeColor: 'bg-emerald-800 text-white',
    },
    {
      name: 'Jadwal Mengajar',
      href: '/guru/jadwal',
      icon: Calendar,
      badge: '2 Sesi',
      badgeColor: 'bg-teal-800 text-white',
    },
    {
      name: 'Tugas & Penilaian',
      href: '/guru/tugas',
      icon: CheckSquare,
      badge: '4 Aktif',
      badgeColor: 'bg-blue-900 text-white',
    },
    {
      name: 'Buku Nilai Ledger',
      href: '/guru/nilai',
      icon: FileSpreadsheet,
      badge: 'KKTP 75',
      badgeColor: 'bg-slate-800 text-white',
    },
    {
      name: 'Bank Soal & CBT',
      href: '/guru/cbt',
      icon: MonitorPlay,
    },
    {
      name: 'Modul Ajar',
      href: '/guru/materi',
      icon: BookOpen,
      badge: '3 Dokumen',
      badgeColor: 'bg-teal-800 text-white',
    },
    {
      name: 'Perizinan Siswa',
      href: '/guru/perizinan',
      icon: UserCheck,
      badge: '2 Menunggu',
      badgeColor: 'bg-amber-600 text-white',
    },
    {
      name: 'Data Siswa & Poin',
      href: '/guru/siswa',
      icon: Users,
    },
    {
      name: 'Sarpras & Fasilitas',
      href: '/guru/sarpras',
      icon: Building2,
    },
  ];

  return (
    <RoleGuard allowedRoles={['teacher', 'homeroom', 'counselor']} portalName="Portal Guru & LMS">
      <div className="flex min-h-screen bg-slate-100 text-slate-900 font-sans">

      {/* ─── SIDEBAR (Width 288px / w-72 for generous label breathing room) ─── */}
      <aside className="w-72 border-r border-slate-200/90 bg-white flex flex-col fixed inset-y-0 left-0 z-30 shadow-xs">
        {/* Brand Header */}
        <div className="px-5 py-4 border-b border-slate-100 flex items-center gap-3 bg-white">
          <div className="w-10 h-10 rounded-xl bg-blue-900 text-white flex items-center justify-center font-bold text-lg shrink-0 shadow-xs">
            <School className="w-5 h-5 text-white" />
          </div>
          <div className="min-w-0">
            <h1 className="font-extrabold text-sm text-slate-900 tracking-tight leading-tight">LABSCHOOL UM</h1>
            <p className="text-xs text-slate-500 font-medium truncate">Portal Pembelajaran Guru</p>
          </div>
        </div>

        {/* Navigation Menu (Taller, Generous py-2.5 text-sm matching Ortu) */}
        <nav className="flex-1 px-3 py-3 space-y-1 overflow-y-auto">
          <div className="px-3.5 py-1.5 text-[11px] font-semibold uppercase tracking-wider text-slate-400">
            Menu Utama Guru
          </div>
          {navLinks.map((link) => {
            const Icon = link.icon;
            const isActive = pathname === link.href || (link.href !== '/guru/dashboard' && link.href !== '/guru' && pathname.startsWith(link.href));
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

        {/* Role Switcher & User Profile Bottom Section (Clean, Unified - Identical to Ortu) */}
        <div className="p-3 border-t border-slate-200 bg-white">
          <div className="relative">
            <button
              onClick={() => setIsRoleDropdownOpen(!isRoleDropdownOpen)}
              className="w-full flex items-center gap-2.5 p-2 rounded-xl hover:bg-slate-50 border border-slate-200 transition-colors text-left group cursor-pointer"
            >
              {user?.avatar_url ? (
                <img
                  src={user.avatar_url}
                  alt={teacherDisplayName}
                  className="w-9 h-9 rounded-full object-cover border border-slate-200 shrink-0"
                />
              ) : (
                <div className="w-9 h-9 rounded-full bg-blue-900 text-white font-bold flex items-center justify-center text-xs shrink-0">
                  {initials}
                </div>
              )}
              <div className="min-w-0 flex-1">
                <p className="text-sm font-semibold text-slate-900 truncate group-hover:text-blue-900">
                  {teacherDisplayName}
                </p>
                <p className="text-xs text-slate-500 truncate font-normal">
                  {roleLabel} • <span className="text-blue-900 font-medium">Beralih Portal</span>
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
                    className="flex items-center gap-2.5 px-3 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50 hover:text-slate-900 transition-colors rounded-lg"
                  >
                    <School className="w-4 h-4 text-blue-700 shrink-0" />
                    <span>Portal Siswa Web (/siswa)</span>
                  </Link>
                  <Link
                    href="/guru/dashboard"
                    onClick={() => setIsRoleDropdownOpen(false)}
                    className="flex items-center gap-2.5 px-3 py-2 text-xs font-semibold text-blue-900 bg-blue-50 rounded-lg"
                  >
                    <CheckCircle2 className="w-4 h-4 text-blue-900 shrink-0" />
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
                    <span>Keluar Sesi Guru</span>
                  </button>
                </div>

              </div>
            )}
          </div>
        </div>
      </aside>

      {/* ─── MAIN CONTENT AREA (MATCHING ORTU EXACTLY) ─── */}
      <div className="flex-1 ml-72 flex flex-col min-h-screen">
        {/* Top Navbar */}
        <header className="h-16 bg-white border-b border-slate-200/90 sticky top-0 z-20 flex items-center justify-between px-8 shadow-xs">
          {/* Left: Clean Context Breadcrumb & Filters */}
          <div className="flex items-center gap-3">
            <div className="flex items-center gap-2 text-xs">
              <span className="font-medium text-slate-400 uppercase tracking-wider text-[11px]">
                Portal Guru & LMS
              </span>
              <span className="text-slate-300 font-light">/</span>
              <span className="font-semibold text-slate-900 text-xs">
                {teacherDisplayName.split(',')[0]}
              </span>
              <span className="px-2 py-0.5 rounded-md text-xs font-medium bg-slate-100 text-slate-700 font-mono border border-slate-200">
                Informatika
              </span>
            </div>

            <div className="hidden lg:flex items-center gap-2 pl-3 border-l border-slate-200">
              <div className="flex items-center gap-2 bg-slate-50 border border-slate-200 rounded-xl px-2.5 py-1 shadow-xs">
                <GraduationCap className="w-3.5 h-3.5 text-blue-900 shrink-0" />
                <select
                  aria-label="Pilih Kelas"
                  value={selectedClassId}
                  onChange={(e) => setSelectedClassId(e.target.value)}
                  className="bg-transparent text-xs font-bold text-slate-900 focus:outline-none cursor-pointer pr-1"
                >
                  {activeClasses.map((cls) => (
                    <option key={cls.id} value={cls.id}>
                      {cls.name} ({cls.total_students} Siswa)
                    </option>
                  ))}
                </select>
              </div>

              <div className="flex items-center gap-2 bg-slate-50 border border-slate-200 rounded-xl px-2.5 py-1 shadow-xs">
                <BookOpen className="w-3.5 h-3.5 text-teal-800 shrink-0" />
                <select
                  aria-label="Pilih Mata Pelajaran"
                  value={selectedSubjectId}
                  onChange={(e) => setSelectedSubjectId(e.target.value)}
                  className="bg-transparent text-xs font-bold text-slate-900 focus:outline-none cursor-pointer pr-1"
                >
                  {teacherSubjects.map((sbj) => (
                    <option key={sbj.id} value={sbj.id}>
                      {sbj.name} ({sbj.code})
                    </option>
                  ))}
                </select>
              </div>
            </div>
          </div>

          {/* Right Header Actions */}
          <div className="flex items-center gap-3">
            {/* Academic Year Pill (Identical to Ortu) */}
            <div className="hidden sm:flex items-center gap-2 px-3 py-1.5 rounded-lg border border-slate-200 bg-slate-50 text-xs font-medium text-slate-600">
              <CalendarCheck className="w-3.5 h-3.5 text-slate-500" />
              <span>T.A. 2026/2027 Gasal</span>
            </div>

            {/* Status Solid Pill */}
            <span className="hidden md:inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-bold bg-emerald-800 text-white shadow-2xs">
              <ShieldCheck className="w-3.5 h-3.5 text-emerald-200" />
              <span>Sistem KBM Aktif</span>
            </span>

            {/* Notification Bell */}
            <button
              type="button"
              aria-label="Lihat Notifikasi"
              className="p-2 rounded-xl bg-slate-50 hover:bg-slate-100 text-slate-600 border border-slate-200 transition-colors relative cursor-pointer"
            >
              <Bell className="w-4 h-4 text-slate-600" />
              <span className="absolute top-1.5 right-1.5 w-2 h-2 rounded-full bg-rose-600"></span>
            </button>

            {/* Avatar Pill */}
            <div className="flex items-center gap-2.5 pl-3 border-l border-slate-200">
              {user?.avatar_url ? (
                <img
                  src={user.avatar_url}
                  alt={teacherDisplayName}
                  className="w-8 h-8 rounded-full object-cover border border-slate-200 shrink-0"
                />
              ) : (
                <div className="w-8 h-8 rounded-full bg-blue-900 text-white font-bold text-xs flex items-center justify-center shrink-0">
                  {initials}
                </div>
              )}
              <div className="hidden md:block text-left">
                <p className="text-xs font-semibold text-slate-900 leading-tight">
                  {teacherDisplayName}
                </p>
                <p className="text-[11px] text-slate-500 font-normal leading-tight">
                  {roleLabel}
                </p>
              </div>
            </div>
          </div>
        </header>

        {/* Fluid Page Body Container (Identical to Ortu) */}
        <main className="flex-1 w-full px-6 xl:px-8 py-4 xl:py-5">
          {children}
        </main>
      </div>
    </div>
    </RoleGuard>
  );
}

