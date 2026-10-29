'use client';

import React, { useState } from 'react';
import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { useAuth } from '@/providers/auth-provider';
import { ADMIN_NAV_SECTIONS, NavItem } from '@/lib/rbac/navigation';
import { hasPermission } from '@/lib/rbac/evaluator';
import { cn } from '@/lib/utils';
import {
  LayoutDashboard,
  Database,
  Calendar,
  Users,
  GraduationCap,
  UserCheck,
  Layers,
  BookOpen,
  CalendarDays,
  Clock,
  BookMarked,
  Award,
  FileSpreadsheet,
  FileText,
  Printer,
  MonitorCheck,
  FileQuestion,
  CalendarCheck,
  Activity,
  UserPlus,
  Settings,
  CheckSquare,
  TrendingUp,
  ShieldAlert,
  Compass,
  Trophy,
  HeartHandshake,
  Building2,
  BookOpenCheck,
  Globe,
  Sliders,
  Key,
  FileClock,
  ChevronDown,
  ChevronRight,
  School,
  X,
  Wrench,
  QrCode,
  BarChart3,
  Bell,
  CreditCard,
  Coins,
  AlertCircle,
  Briefcase,
  ExternalLink,
} from 'lucide-react';

const ICON_MAP: Record<string, React.ElementType> = {
  Bell,
  BarChart3,
  LayoutDashboard,
  Database,
  Calendar,
  Users,
  GraduationCap,
  UserCheck,
  Layers,
  BookOpen,
  CalendarDays,
  Clock,
  BookMarked,
  Award,
  FileSpreadsheet,
  FileText,
  Printer,
  MonitorCheck,
  FileQuestion,
  CalendarCheck,
  Activity,
  UserPlus,
  Settings,
  CheckSquare,
  TrendingUp,
  ShieldAlert,
  Compass,
  Trophy,
  HeartHandshake,
  Building2,
  BookOpenCheck,
  Globe,
  Sliders,
  Key,
  FileClock,
  Wrench,
  QrCode,
  CreditCard,
  Coins,
  AlertCircle,
};

export function AdminSidebar({
  isOpen,
  onClose,
}: {
  isOpen?: boolean;
  onClose?: () => void;
}) {
  const pathname = usePathname();
  const { user } = useAuth();
  const [openSubmenus, setOpenSubmenus] = useState<Record<string, boolean>>({
    'Master Data': true,
  });

  const toggleSubmenu = (title: string) => {
    setOpenSubmenus((prev) => ({
      ...prev,
      [title]: !prev[title],
    }));
  };

  const renderNavIcon = (iconName: string) => {
    const IconComponent = ICON_MAP[iconName] || LayoutDashboard;
    return <IconComponent className="h-4 w-4 shrink-0" />;
  };

  const isItemVisible = (item: NavItem) => {
    return hasPermission(user, item.permission);
  };

  return (
    <>
      {/* Backdrop pada layar mobile */}
      {isOpen && (
        <div
          className="fixed inset-0 z-40 bg-slate-900/50 backdrop-blur-sm lg:hidden"
          onClick={onClose}
          aria-hidden="true"
        />
      )}

      <aside
        className={cn(
          'fixed inset-y-0 left-0 z-50 flex w-64 flex-col bg-brand-dark text-slate-100 transition-transform duration-200 ease-in-out lg:static lg:translate-x-0',
          isOpen ? 'translate-x-0' : '-translate-x-full'
        )}
      >
        {/* Header Logo */}
        <div className="flex h-16 items-center justify-between px-5 border-b border-blue-900/50">
          <Link href="/admin" className="flex items-center gap-2.5">
            <div className="flex h-9 w-9 items-center justify-center rounded-md bg-brand-blue text-white shadow-sm">
              <School className="h-5 w-5" />
            </div>
            <div>
              <div className="font-bold text-sm tracking-wide text-white">SISTLABUM</div>
              <div className="text-[10px] text-blue-200/80 font-medium">Labschool UM</div>
            </div>
          </Link>

          {onClose && (
            <button
              onClick={onClose}
              className="p-1 rounded-md text-blue-300 hover:text-white lg:hidden"
              aria-label="Tutup sidebar"
            >
              <X className="h-5 w-5" />
            </button>
          )}
        </div>

        {/* Daftar Navigasi Berkelompok */}
        <div className="flex-1 overflow-y-auto px-3 py-4 space-y-6">
          {ADMIN_NAV_SECTIONS.map((section, sectionIdx) => {
            const visibleItems = section.items.filter(isItemVisible);
            if (visibleItems.length === 0) return null;

            return (
              <div key={sectionIdx} className="space-y-1">
                {section.title && (
                  <div className="px-3 text-[10px] font-semibold text-blue-300/60 uppercase tracking-wider mb-2">
                    {section.title}
                  </div>
                )}

                {visibleItems.map((item) => {
                  const hasChildren = item.children && item.children.length > 0;
                  const isSubmenuOpen = openSubmenus[item.title];
                  const isActive = pathname === item.href || (hasChildren && pathname.startsWith(item.href + '/'));

                  if (hasChildren) {
                    const visibleChildren = item.children!.filter(isItemVisible);
                    if (visibleChildren.length === 0) return null;

                    return (
                      <div key={item.title} className="space-y-1">
                        <button
                          onClick={() => toggleSubmenu(item.title)}
                          className={cn(
                            'w-full flex items-center justify-between px-3 py-2 text-xs font-medium rounded-md transition-colors',
                            isActive
                              ? 'bg-blue-900/40 text-white'
                              : 'text-slate-300 hover:bg-blue-950/70 hover:text-white'
                          )}
                        >
                          <div className="flex items-center gap-2.5">
                            {renderNavIcon(item.icon)}
                            <span>{item.title}</span>
                          </div>
                          {isSubmenuOpen ? (
                            <ChevronDown className="h-3.5 w-3.5 text-blue-300" />
                          ) : (
                            <ChevronRight className="h-3.5 w-3.5 text-blue-300/60" />
                          )}
                        </button>

                        {isSubmenuOpen && (
                          <div className="pl-6 space-y-1 pt-1">
                            {visibleChildren.map((child) => {
                              const isChildActive = pathname === child.href;
                              return (
                                <Link
                                  key={child.href}
                                  href={child.href}
                                  onClick={onClose}
                                  className={cn(
                                    'flex items-center gap-2 px-2.5 py-1.5 text-xs rounded-sm transition-colors',
                                    isChildActive
                                      ? 'bg-brand-blue text-white font-medium'
                                      : 'text-slate-400 hover:text-white hover:bg-blue-900/30'
                                  )}
                                >
                                  {renderNavIcon(child.icon)}
                                  <span>{child.title}</span>
                                </Link>
                              );
                            })}
                          </div>
                        )}
                      </div>
                    );
                  }

                  return (
                    <Link
                      key={item.href}
                      href={item.href}
                      onClick={onClose}
                      className={cn(
                        'flex items-center gap-2.5 px-3 py-2 text-xs font-medium rounded-md transition-colors',
                        isActive
                          ? 'bg-brand-blue text-white shadow-sm'
                          : 'text-slate-300 hover:bg-blue-950/70 hover:text-white'
                      )}
                    >
                      {renderNavIcon(item.icon)}
                      <span>{item.title}</span>
                    </Link>
                  );
                })}
              </div>
            );
          })}
        </div>

        {/* Tautan Layanan Pribadi Pegawai / Guru */}
        <div className="p-3 border-t border-blue-900/40 space-y-1.5 bg-blue-950/40">
          <div className="text-[10px] font-bold uppercase tracking-wider text-blue-300/70 px-1">
            Layanan Pribadi
          </div>
          <Link
            href="/portal/staf"
            onClick={onClose}
            className="flex items-center justify-between px-2.5 py-1.5 text-xs font-medium text-slate-200 hover:text-white hover:bg-brand-blue/80 rounded-md transition-colors border border-blue-800/40"
          >
            <div className="flex items-center gap-2">
              <Briefcase className="h-3.5 w-3.5 text-emerald-400" />
              <span>Portal Staf (Presensi & Cuti)</span>
            </div>
            <ExternalLink className="h-3 w-3 text-blue-300/70" />
          </Link>
          {user?.roles?.some((r) => r === 'teacher' || r === 'homeroom') && (
            <Link
              href="/portal/guru"
              onClick={onClose}
              className="flex items-center justify-between px-2.5 py-1.5 text-xs font-medium text-slate-200 hover:text-white hover:bg-brand-blue/80 rounded-md transition-colors border border-blue-800/40"
            >
              <div className="flex items-center gap-2">
                <GraduationCap className="h-3.5 w-3.5 text-amber-400" />
                <span>Portal Guru & Kelas</span>
              </div>
              <ExternalLink className="h-3 w-3 text-blue-300/70" />
            </Link>
          )}
        </div>

        {/* Footer Sidebar */}
        <div className="p-3 border-t border-blue-900/40 text-center">
          <p className="text-[11px] text-blue-300/50">SISTLABUM v1.0 Production</p>
        </div>
      </aside>
    </>
  );
}
