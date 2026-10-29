'use client';

import React from 'react';
import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { useAuth } from '@/providers/auth-provider';
import { RoleSwitcher } from '../shared/role-switcher';
import { cn } from '@/lib/utils';
import {
  Users,
  CalendarCheck,
  FileSpreadsheet,
  FileText,
  ShieldAlert,
  LogOut,
  UserCheck,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { NotificationBell } from '@/components/notifications/notification-bell';

export function ParentHeader() {
  const pathname = usePathname();
  const { user, logout } = useAuth();

  const navItems = [
    { title: 'Beranda', href: '/portal/ortu', icon: Users },
    { title: 'Presensi KBM', href: '/portal/ortu/presensi', icon: CalendarCheck },
    { title: 'Izin & Sakit', href: '/portal/ortu/izin', icon: FileSpreadsheet },
    { title: 'E-Rapor Anak', href: '/portal/ortu/rapor', icon: FileText },
    { title: 'Catatan Karakter', href: '/portal/ortu/poin', icon: ShieldAlert },
  ];

  const parentName =
    user?.profile?.name ||
    user?.profile?.fullName ||
    user?.username ||
    'Orang Tua / Wali';

  return (
    <header className="sticky top-0 z-30 border-b border-slate-200 bg-white shadow-sm">
      <div className="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6">
        <div className="flex items-center gap-6">
          <Link
            href="/portal/ortu"
            className="flex items-center gap-2.5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue rounded-md"
          >
            <div className="flex h-9 w-9 items-center justify-center rounded-md bg-brand-teal text-white shadow-sm">
              <Users className="h-5 w-5" />
            </div>
            <div>
              <div className="font-bold text-sm text-slate-900 leading-tight">Portal Orang Tua</div>
              <div className="text-[10px] text-slate-500 font-medium">Monitoring Pendidikan Siswa</div>
            </div>
          </Link>

          <nav className="hidden md:flex items-center gap-1" aria-label="Navigasi Utama Orang Tua">
            {navItems.map((item) => {
              const Icon = item.icon;
              const isActive = pathname === item.href;
              return (
                <Link
                  key={item.href}
                  href={item.href}
                  className={cn(
                    'flex items-center gap-2 px-3 py-2 text-xs font-medium rounded-md transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-teal',
                    isActive
                      ? 'bg-teal-50 text-brand-teal font-semibold'
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
          {/* Multi-Child Context Indicator Placeholder */}
          <div className="hidden lg:flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-slate-100 border border-slate-200">
            <UserCheck className="h-3.5 w-3.5 text-brand-teal" />
            <span className="text-[11px] font-medium text-slate-600">Siswa Aktif:</span>
            <Badge variant="outline" className="text-[11px] bg-white text-slate-800 font-semibold px-2 py-0 border-slate-300">
              Anak Terpilih
            </Badge>
          </div>

          <RoleSwitcher />
          <NotificationBell portal="ortu" />

          <div className="flex items-center gap-3 pl-2 border-l border-slate-200">
            <div className="hidden sm:block text-right">
              <div className="text-xs font-semibold text-slate-900">{parentName}</div>
              <div className="text-[10px] text-slate-500">Wali Murid Resmi</div>
            </div>

            <button
              onClick={() => logout()}
              className="p-2 rounded-md text-slate-400 hover:text-status-danger hover:bg-red-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-status-danger transition-colors"
              title="Keluar"
              aria-label="Keluar dari Portal Orang Tua"
            >
              <LogOut className="h-4 w-4" />
            </button>
          </div>
        </div>
      </div>
    </header>
  );
}
