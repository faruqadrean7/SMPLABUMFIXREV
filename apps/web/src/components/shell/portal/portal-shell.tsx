'use client';

import React, { useState } from 'react';
import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { useAuth } from '@/providers/auth-provider';
import { RoleSwitcher } from '../shared/role-switcher';
import { NotificationBell } from '@/components/notifications/notification-bell';
import { Avatar } from '@/components/ui/avatar';
import { cn } from '@/lib/utils';
import {
  Briefcase,
  LogOut,
  Menu,
  X,
  FileText,
  Bell,
  Home,
  CheckSquare,
  DollarSign,
  CalendarDays,
  Clock,
  Wrench,
  Building,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';

interface PortalShellProps {
  children: React.ReactNode;
}

export function PortalShell({ children }: PortalShellProps) {
  const pathname = usePathname();
  const { user, logout } = useAuth();
  const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);

  // PortalShell melayani /portal/staf, /portal/kepsek, /portal/notifikasi, & /portal
  const isStaffPortal = pathname.startsWith('/portal/staf');
  const isKepsekPortal = pathname.startsWith('/portal/kepsek');
  const isNotificationPortal = pathname.startsWith('/portal/notifikasi');

  let portalTitle = 'Portal Personal';
  let portalSubtitle = 'SISTLABUM • SMP Laboratorium UM';
  let PortalIcon = Home;

  if (isStaffPortal) {
    portalTitle = 'Portal Staf & TU';
    portalSubtitle = 'Operasional & Administrasi';
    PortalIcon = Briefcase;
  } else if (isKepsekPortal) {
    portalTitle = 'Portal Pimpinan';
    portalSubtitle = 'Kepala Sekolah & Wakasek';
    PortalIcon = Building;
  } else if (isNotificationPortal) {
    portalTitle = 'Notifikasi & Pengumuman';
    portalSubtitle = 'Pusat Informasi Terpadu';
    PortalIcon = Bell;
  }

  // Navigasi kontekstual per portal
  const getNavItems = () => {
    if (isStaffPortal) {
      return [
        { title: 'Beranda', href: '/portal/staf', icon: Home },
        { title: 'Presensi Kerja', href: '/portal/staf/presensi-saya', icon: Clock },
        { title: 'Izin & Cuti', href: '/portal/staf/izin-cuti', icon: CalendarDays },
        { title: 'Slip Gaji', href: '/portal/staf/slip-gaji', icon: DollarSign },
        { title: 'Tiket Sarpras', href: '/portal/staf/tiket', icon: Wrench },
        { title: 'Notifikasi', href: '/portal/notifikasi', icon: Bell },
      ];
    }
    if (isKepsekPortal) {
      return [
        { title: 'Dashboard KPI', href: '/portal/kepsek', icon: Home },
        { title: 'Antrean Persetujuan', href: '/portal/kepsek/approvals', icon: CheckSquare },
        { title: 'Pusat Laporan', href: '/admin/reports', icon: FileText },
        { title: 'Notifikasi', href: '/portal/notifikasi', icon: Bell },
      ];
    }
    return [
      { title: 'Portal Utama', href: '/portal', icon: Home },
      { title: 'Notifikasi', href: '/portal/notifikasi', icon: Bell },
    ];
  };

  const navItems = getNavItems();
  const userName =
    user?.profile?.name ||
    user?.profile?.fullName ||
    user?.username ||
    'Pengguna Portal';

  return (
    <div className="min-h-screen bg-slate-50 flex flex-col selection:bg-brand-blue selection:text-white">
      {/* Top Header Navbar */}
      <header className="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur shadow-sm">
        <div className="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
          {/* Logo & Portal Identity */}
          <div className="flex items-center gap-4 sm:gap-6">
            <Link
              href="/portal"
              className="flex items-center gap-2.5 rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue"
            >
              <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-blue text-white shadow-sm transition-transform hover:scale-105">
                <PortalIcon className="h-5 w-5" />
              </div>
              <div className="flex flex-col">
                <span className="font-bold text-sm text-slate-900 leading-tight">
                  {portalTitle}
                </span>
                <span className="text-[11px] text-slate-500 font-medium hidden xs:inline">
                  {portalSubtitle}
                </span>
              </div>
            </Link>

            {/* Desktop Navigation Tabs */}
            <nav className="hidden md:flex items-center gap-1.5 ml-4" aria-label="Navigasi Portal">
              {navItems.map((item) => {
                const Icon = item.icon;
                const isActive =
                  item.href === '/portal'
                    ? pathname === '/portal'
                    : pathname === item.href || (item.href !== '/portal' && pathname.startsWith(item.href + '/'));
                return (
                  <Link
                    key={item.href}
                    href={item.href}
                    className={cn(
                      'flex items-center gap-2 px-3 py-1.5 text-xs font-semibold rounded-lg transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue',
                      isActive
                        ? 'bg-blue-50 text-brand-blue shadow-xs'
                        : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
                    )}
                  >
                    <Icon className="h-3.5 w-3.5" />
                    <span>{item.title}</span>
                  </Link>
                );
              })}
            </nav>
          </div>

          {/* Action Icons: RoleSwitcher, NotificationBell, User Menu */}
          <div className="flex items-center gap-2 sm:gap-3">
            <RoleSwitcher />
            <NotificationBell portal="portal" />

            {/* User Info & Logout Button */}
            <div className="flex items-center gap-3 pl-2 border-l border-slate-200">
              <div className="hidden lg:flex items-center gap-2.5">
                <Avatar
                  src={(user?.profile?.photo_url as string) || (user?.profile?.avatarUrl as string)}
                  name={userName}
                  size="sm"
                  alt={userName}
                />
                <div className="flex flex-col text-left">
                  <span className="text-xs font-bold text-slate-900 truncate max-w-[140px]">
                    {userName}
                  </span>
                  <span className="text-[10px] text-slate-500 capitalize">
                    {user?.roles?.[0] || 'User'}
                  </span>
                </div>
              </div>

              <button
                onClick={() => logout()}
                className="p-2 rounded-lg text-slate-500 hover:text-status-danger hover:bg-red-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-status-danger transition-colors"
                title="Keluar dari Portal"
                aria-label="Tombol Keluar Akun"
              >
                <LogOut className="h-4 w-4" />
              </button>

              {/* Mobile Menu Hamburger Toggle */}
              <button
                onClick={() => setIsMobileMenuOpen(!isMobileMenuOpen)}
                className="md:hidden p-2 rounded-lg text-slate-600 hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue"
                aria-label="Buka Menu Navigasi"
              >
                {isMobileMenuOpen ? <X className="h-5 w-5" /> : <Menu className="h-5 w-5" />}
              </button>
            </div>
          </div>
        </div>

        {/* Mobile Navigation Drawer */}
        {isMobileMenuOpen && (
          <div className="md:hidden border-t border-slate-200 bg-white px-4 pt-3 pb-4 shadow-lg animate-in slide-in-from-top duration-200">
            <div className="mb-3 pb-3 border-b border-slate-100 flex items-center gap-2.5">
              <Avatar
                src={(user?.profile?.photo_url as string) || (user?.profile?.avatarUrl as string)}
                name={userName}
                size="sm"
                alt={userName}
              />
              <div className="flex flex-col">
                <span className="text-xs font-bold text-slate-900">{userName}</span>
                <span className="text-[10px] text-slate-500 capitalize">{user?.roles?.join(', ')}</span>
              </div>
            </div>

            <nav className="flex flex-col gap-1" aria-label="Menu Navigasi Mobile">
              {navItems.map((item) => {
                const Icon = item.icon;
                const isActive = pathname === item.href;
                return (
                  <Link
                    key={item.href}
                    href={item.href}
                    onClick={() => setIsMobileMenuOpen(false)}
                    className={cn(
                      'flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-lg transition-colors',
                      isActive
                        ? 'bg-blue-50 text-brand-blue font-semibold'
                        : 'text-slate-700 hover:bg-slate-100'
                    )}
                  >
                    <Icon className="h-4 w-4" />
                    <span>{item.title}</span>
                  </Link>
                );
              })}
            </nav>
          </div>
        )}
      </header>

      {/* Main Portal Body */}
      <main className="flex-1 py-6 px-4 sm:px-6 lg:px-8">
        <div className="mx-auto max-w-7xl">{children}</div>
      </main>

      {/* Portal Footer */}
      <footer className="border-t border-slate-200 bg-white py-4 text-center text-xs text-slate-500">
        <div className="mx-auto max-w-7xl px-4 flex flex-col sm:flex-row items-center justify-between gap-2">
          <span>Portal Sekolah Terpadu • SMP Laboratorium Universitas Negeri Malang</span>
          <span className="text-[11px] text-slate-400">SISTLABUM v1.0 • Aman & Terverifikasi</span>
        </div>
      </footer>
    </div>
  );
}
