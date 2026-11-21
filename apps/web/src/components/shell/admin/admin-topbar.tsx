'use client';

import React, { useState } from 'react';
import { useAuth } from '@/providers/auth-provider';
import { useQuery } from '@tanstack/react-query';
import { academicYearsApi } from '@/features/master/academic-years-api';
import { Button } from '@/components/ui/button';
import { RoleSwitcher } from '../shared/role-switcher';
import { Menu, LogOut, ChevronDown, Calendar, AlertCircle } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Avatar } from '@/components/ui/avatar';
import { NotificationBell } from '@/components/notifications/notification-bell';

export function AdminTopbar({ onToggleSidebar }: { onToggleSidebar?: () => void }) {
  const { user, logout } = useAuth();
  const [userMenuOpen, setUserMenuOpen] = useState(false);

  // Ambil periode akademik aktif riil dari backend Laravel
  const { data: activePeriod, isLoading: isLoadingPeriod, isError: isErrorPeriod } = useQuery({
    queryKey: ['active-period'],
    queryFn: () => academicYearsApi.getActive(),
    staleTime: 60 * 1000, // 1 menit
  });

  const displayName =
    user?.profile?.name ||
    user?.profile?.fullName ||
    user?.username ||
    'Pengguna';

  const roleLabel = user?.roles?.[0]
    ? user.roles[0].replace('_', ' ').toUpperCase()
    : 'MANAJEMEN';

  const academicYearName = activePeriod?.academicYear?.name;
  const semesterName = activePeriod?.semester?.name;

  return (
    <header className="sticky top-0 z-30 flex h-16 w-full items-center justify-between border-b border-slate-200 bg-white px-4 sm:px-6 shadow-sm">
      <div className="flex items-center gap-3">
        {onToggleSidebar && (
          <button
            onClick={onToggleSidebar}
            className="p-2 rounded-md text-slate-500 hover:bg-slate-100 hover:text-slate-700 lg:hidden focus:outline-none"
            aria-label="Toggle navigasi sidebar"
          >
            <Menu className="h-5 w-5" />
          </button>
        )}

        {/* Badge Periode Akademik Aktif Riil dari Backend */}
        <div className="flex items-center gap-2">
          {isLoadingPeriod ? (
            <div className="hidden sm:flex items-center gap-1.5 h-6 px-2.5 rounded-sm bg-slate-100 animate-pulse text-[11px] text-slate-400">
              <Calendar className="h-3 w-3" />
              <span>Memuat periode aktif...</span>
            </div>
          ) : isErrorPeriod ? (
            <Badge variant="outline" className="hidden sm:inline-flex items-center gap-1.5 bg-red-50 text-status-danger border-red-200 py-1 text-xs">
              <AlertCircle className="h-3 w-3" />
              <span>Periode T.A. Gagal Dimuat</span>
            </Badge>
          ) : academicYearName ? (
            <Badge variant="outline" className="hidden sm:inline-flex items-center gap-1.5 bg-slate-50 text-slate-700 border-slate-200 py-1 text-xs font-medium">
              <Calendar className="h-3 w-3 text-brand-teal" />
              <span>
                T.A. {academicYearName} {semesterName ? `— ${semesterName}` : ''}
              </span>
            </Badge>
          ) : (
            <Badge variant="warning" className="hidden sm:inline-flex items-center gap-1.5 py-1 text-xs">
              <AlertCircle className="h-3 w-3" />
              <span>Belum Ada T.A. Aktif</span>
            </Badge>
          )}
        </div>
      </div>

      <div className="flex items-center gap-3">
        <RoleSwitcher />

        <NotificationBell portal="admin" />

        <div className="relative">
          <button
            onClick={() => setUserMenuOpen(!userMenuOpen)}
            className="flex items-center gap-2.5 p-1.5 rounded-md hover:bg-slate-50 focus:outline-none select-none"
            aria-haspopup="true"
            aria-expanded={userMenuOpen}
          >
            <Avatar
              src={(user?.profile?.photo_url as string) || (user?.profile?.avatarUrl as string)}
              name={displayName}
              size="sm"
              alt={displayName}
            />
            <div className="hidden md:block text-left">
              <div className="text-xs font-semibold text-slate-900 leading-tight">{displayName}</div>
              <div className="text-[10px] font-medium text-slate-500 leading-tight">{roleLabel}</div>
            </div>
            <ChevronDown className="h-3.5 w-3.5 text-slate-400" />
          </button>

          {userMenuOpen && (
            <>
              <div
                className="fixed inset-0 z-40"
                onClick={() => setUserMenuOpen(false)}
                aria-hidden="true"
              />
              <div className="absolute right-0 mt-2 w-52 rounded-md bg-white p-1 shadow-lg ring-1 ring-black ring-opacity-5 z-50">
                <div className="px-3 py-2 border-b border-slate-100">
                  <p className="text-xs font-semibold text-slate-800">{displayName}</p>
                  <p className="text-[11px] text-slate-500 truncate">{user?.email || user?.username}</p>
                </div>

                <div className="py-1">
                  <button
                    onClick={() => {
                      setUserMenuOpen(false);
                      logout();
                    }}
                    className="w-full flex items-center gap-2 px-3 py-2 text-xs text-status-danger font-medium rounded-sm hover:bg-red-50 transition-colors text-left"
                  >
                    <LogOut className="h-3.5 w-3.5" />
                    Keluar Sistem
                  </button>
                </div>
              </div>
            </>
          )}
        </div>
      </div>
    </header>
  );
}
