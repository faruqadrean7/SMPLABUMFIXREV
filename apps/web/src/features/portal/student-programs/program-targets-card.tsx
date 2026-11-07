'use client';

import React from 'react';
import Link from 'next/link';
import {
  Target,
  CheckCircle2,
  Clock,
  XCircle,
  AlertCircle,
  RefreshCw,
  ChevronRight,
  TrendingUp,
} from 'lucide-react';
import { useProgramTargets, ProgramTargetItem } from '@/lib/use-student-data';
import { cn } from '@/lib/utils';

interface ProgramTargetsCardProps {
  programType?: 'tahfidz' | 'icp' | 'inklusi' | 'reguler';
  title?: string;
  subtitle?: string;
  limit?: number;
  compact?: boolean;
  showAllHref?: string;
  className?: string;
}

const STATUS_CONFIG: Record<
  string,
  { label: string; icon: React.ComponentType<{ className?: string }>; color: string }
> = {
  in_progress: {
    label: 'Dalam Proses',
    icon: Clock,
    color: 'bg-amber-50 text-amber-800 border-amber-200',
  },
  aktif: {
    label: 'Dalam Proses',
    icon: Clock,
    color: 'bg-amber-50 text-amber-800 border-amber-200',
  },
  achieved: {
    label: 'Tercapai',
    icon: CheckCircle2,
    color: 'bg-emerald-50 text-emerald-800 border-emerald-200',
  },
  tercapai: {
    label: 'Tercapai',
    icon: CheckCircle2,
    color: 'bg-emerald-50 text-emerald-800 border-emerald-200',
  },
  not_achieved: {
    label: 'Belum Tercapai',
    icon: XCircle,
    color: 'bg-rose-50 text-rose-800 border-rose-200',
  },
  batal: {
    label: 'Belum Tercapai',
    icon: XCircle,
    color: 'bg-rose-50 text-rose-800 border-rose-200',
  },
};

const DOMAIN_LABELS: Record<string, string> = {
  hafalan: 'Hafalan Al-Qur’an',
  tajwid: 'Tajwid & Makhraj',
  tilawah: 'Tilawah Harian',
  english: 'English Checkpoint',
  math: 'Mathematics',
  science: 'Science / Biology',
  motorik: 'Motorik & Fisik',
  akademik: 'Akademik Terbimbing',
  sosial_emosi: 'Sosial & Emosional',
};

export function ProgramTargetsCard({
  programType,
  title = 'Target Capaian & Progres Belajar',
  subtitle = 'Pantau perkembangan target individual program khusus',
  limit,
  compact = false,
  showAllHref,
  className,
}: ProgramTargetsCardProps) {
  const { data: response, isLoading, isError, refetch } = useProgramTargets(
    limit ? { per_page: limit } : undefined
  );

  const targets = response?.data || [];

  return (
    <div
      className={cn(
        'bg-white border border-slate-200 rounded-xl p-4 sm:p-5 shadow-xs flex flex-col',
        className
      )}
    >
      {/* Header */}
      <div className="flex flex-wrap items-center justify-between gap-2 pb-3 border-b border-slate-100">
        <div className="flex items-center gap-2.5">
          <div className="w-8 h-8 rounded-lg bg-slate-900 text-white flex items-center justify-center shrink-0 shadow-2xs">
            <Target className="w-4 h-4" />
          </div>
          <div>
            <h3 className="text-sm font-extrabold text-slate-900 tracking-tight">{title}</h3>
            {subtitle && <p className="text-[11px] text-slate-500">{subtitle}</p>}
          </div>
        </div>

        {showAllHref && (
          <Link
            href={showAllHref}
            className="text-xs font-bold text-slate-700 hover:text-slate-900 inline-flex items-center gap-1 hover:underline"
          >
            Lihat Semua <ChevronRight className="w-3.5 h-3.5" />
          </Link>
        )}
      </div>

      {/* Content State */}
      <div className="mt-3.5 flex-1">
        {isLoading && (
          <div className="space-y-2.5 py-4">
            <div className="h-16 bg-slate-100 rounded-lg animate-pulse" />
            <div className="h-16 bg-slate-100 rounded-lg animate-pulse" />
          </div>
        )}

        {isError && (
          <div className="py-6 text-center rounded-lg border border-red-100 bg-red-50/50 p-4">
            <AlertCircle className="w-6 h-6 text-red-500 mx-auto mb-1.5" />
            <p className="text-xs font-bold text-red-800">Gagal memuat target capaian</p>
            <p className="text-[11px] text-red-600 mt-0.5">Terjadi kendala saat mengambil data capaian target.</p>
            <button
              onClick={() => refetch()}
              className="mt-2.5 inline-flex items-center gap-1 px-3 py-1 text-xs font-bold text-red-700 bg-white border border-red-200 rounded-md hover:bg-red-50 cursor-pointer"
            >
              <RefreshCw className="w-3 h-3" /> Coba Lagi
            </button>
          </div>
        )}

        {!isLoading && !isError && targets.length === 0 && (
          <div className="py-8 text-center rounded-lg border border-dashed border-slate-200 p-4">
            <TrendingUp className="w-8 h-8 text-slate-400 mx-auto mb-2 opacity-60" />
            <p className="text-xs font-bold text-slate-700">Belum Ada Target Aktif</p>
            <p className="text-[11px] text-slate-500 mt-1 max-w-sm mx-auto">
              Guru pembina belum menetapkan target capaian capaian individu untuk semester ini.
            </p>
          </div>
        )}

        {!isLoading && !isError && targets.length > 0 && (
          <div className="space-y-3">
            {targets.map((target) => {
              const statusCfg = STATUS_CONFIG[target.status] || STATUS_CONFIG.in_progress;
              const StatusIcon = statusCfg.icon;
              const domainLabel = target.domain
                ? DOMAIN_LABELS[target.domain] || target.domain
                : 'Target Umum';

              return (
                <div
                  key={target.id}
                  className="p-3.5 rounded-lg border border-slate-200 hover:border-slate-300 transition-colors bg-white flex flex-col gap-2 shadow-2xs"
                >
                  <div className="flex flex-wrap items-start justify-between gap-2">
                    <div>
                      <div className="flex flex-wrap items-center gap-2">
                        <span className="text-xs font-bold text-slate-900">{target.title}</span>
                        <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-200">
                          {domainLabel}
                        </span>
                      </div>
                      {target.description && (
                        <p className="text-xs text-slate-600 mt-1 line-clamp-2 leading-relaxed">
                          {target.description}
                        </p>
                      )}
                    </div>

                    <span
                      className={cn(
                        'px-2 py-0.5 rounded text-[11px] font-bold border inline-flex items-center gap-1 shrink-0',
                        statusCfg.color
                      )}
                    >
                      <StatusIcon className="w-3 h-3" />
                      {statusCfg.label}
                    </span>
                  </div>

                  <div className="mt-1 pt-2 border-t border-slate-100 flex flex-wrap items-center justify-between gap-2 text-xs">
                    <div className="flex items-center gap-2">
                      <span className="text-slate-500 font-medium">Progres Saat Ini:</span>
                      <span className="font-extrabold text-slate-900">
                        {target.current_value || '0'}
                      </span>
                      {target.target_value && (
                        <>
                          <span className="text-slate-400">/</span>
                          <span className="font-bold text-slate-600">
                            Target {target.target_value}
                          </span>
                        </>
                      )}
                    </div>

                    {target.period && (
                      <span className="text-[11px] text-slate-500 font-medium">
                        Periode: {target.period}
                      </span>
                    )}
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </div>
    </div>
  );
}
