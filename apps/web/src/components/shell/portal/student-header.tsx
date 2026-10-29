'use client';

import React from 'react';
import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { useAuth } from '@/providers/auth-provider';
import { RoleSwitcher } from '../shared/role-switcher';
import { cn } from '@/lib/utils';
import {
  GraduationCap,
  Calendar,
  BookOpen,
  FileText,
  Award,
  LogOut,
  Monitor,
  CreditCard,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { NotificationBell } from '@/components/notifications/notification-bell';

export function StudentHeader() {
  const pathname = usePathname();
  const { user, logout } = useAuth();

  const navItems = [
    { title: 'Beranda', href: '/portal/siswa', icon: GraduationCap },
    { title: 'Jadwal', href: '/portal/siswa/jadwal', icon: Calendar },
    { title: 'Tugas KBM', href: '/portal/siswa/tugas', icon: BookOpen },
    { title: 'Ujian CBT', href: '/portal/siswa/cbt', icon: Monitor },
    { title: 'E-Rapor', href: '/portal/siswa/rapor', icon: FileText },
    { title: 'Poin Disiplin', href: '/portal/siswa/poin', icon: Award },
    { title: 'Tagihan', href: '/portal/siswa/tagihan', icon: CreditCard },
  ];

  const studentName =
    user?.profile?.name ||
    user?.profile?.fullName ||
    user?.username ||
    'Siswa';

  const nisLabel = user?.profile?.nis ? `NIS: ${user.profile.nis}` : 'Siswa Aktif';
  const classLabel = user?.profile?.className ? `Kelas ${user.profile.className}` : 'Labschool UM';

  return (
    <header className="sticky top-0 z-30 border-b border-slate-200 bg-white shadow-sm">
      <div className="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6">
        <div className="flex items-center gap-6">
          <Link
            href="/portal/siswa"
            className="flex items-center gap-2.5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue rounded-md"
          >
            <div className="flex h-9 w-9 items-center justify-center rounded-md bg-brand-blue text-white shadow-sm">
              <GraduationCap className="h-5 w-5" />
            </div>
            <div>
              <div className="font-bold text-sm text-slate-900 leading-tight">Portal Siswa</div>
              <div className="text-[10px] text-slate-500 font-medium">SMP Laboratorium UM</div>
            </div>
          </Link>

          {/* Navigasi Keyboard Friendly */}
          <nav className="hidden md:flex items-center gap-1" aria-label="Navigasi Utama Siswa">
            {navItems.map((item) => {
              const Icon = item.icon;
              const isActive = pathname === item.href;
              return (
                <Link
                  key={item.href}
                  href={item.href}
                  tabIndex={0}
                  className={cn(
                    'flex items-center gap-2 px-3 py-2 text-xs font-medium rounded-md transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue',
                    isActive
                      ? 'bg-blue-50 text-brand-blue font-semibold'
                      : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
                  )}
                >
                  <Icon className="h-4 w-4" />
                  <span>{item.title}</span>
                </Link>
              );
            })}
          </nav>
        </div>

        <div className="flex items-center gap-3">
          <RoleSwitcher />
          <NotificationBell portal="siswa" />

          <div className="flex items-center gap-3 pl-2 border-l border-slate-200">
            <div className="hidden sm:block text-right">
              <div className="text-xs font-semibold text-slate-900">{studentName}</div>
              <div className="flex items-center gap-1 text-[10px] text-slate-500">
                <span>{nisLabel}</span>
                <span>•</span>
                <Badge variant="outline" className="text-[10px] px-1 py-0 h-4">
                  {classLabel}
                </Badge>
              </div>
            </div>

            <button
              onClick={() => logout()}
              tabIndex={0}
              className="p-2 rounded-md text-slate-400 hover:text-status-danger hover:bg-red-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-status-danger transition-colors"
              title="Keluar"
              aria-label="Keluar dari Portal Siswa"
            >
              <LogOut className="h-4 w-4" />
            </button>
          </div>
        </div>
      </div>
    </header>
  );
}
