import React from 'react';
import type { LucideIcon } from 'lucide-react';
import { Labi, type LabiExpression } from './labi';

/**
 * Reusable playful UI primitives for the student portal. Presentation only —
 * they carry no data logic. Colors come from the `.portal-siswa` scoped theme.
 */

type Tone = 'coral' | 'sun' | 'mint' | 'grape' | 'sky';

export function StudentPageHeader({
  title,
  subtitle,
  icon: Icon,
  expression,
}: {
  title: string;
  subtitle?: string;
  icon?: LucideIcon;
  expression?: LabiExpression;
}) {
  return (
    <div className="siswa-hero siswa-card relative mb-4 flex items-center gap-4 overflow-hidden px-5 py-4 text-white shadow-md">
      {Icon && (
        <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-white/20">
          <Icon className="h-5 w-5 text-white" />
        </div>
      )}
      <div className="min-w-0">
        <h1 className="truncate text-lg font-extrabold tracking-tight">{title}</h1>
        {subtitle && <p className="mt-0.5 text-sm font-medium text-white/85">{subtitle}</p>}
      </div>
      {expression && (
        <div className="ml-auto hidden shrink-0 sm:block">
          <Labi expression={expression} size={56} />
        </div>
      )}
    </div>
  );
}

export function StatTile({
  label,
  value,
  icon: Icon,
  tone = 'sky',
}: {
  label: string;
  value: React.ReactNode;
  icon?: LucideIcon;
  tone?: Tone;
}) {
  return (
    <div className={`tile-${tone} siswa-card siswa-lift relative overflow-hidden p-4 text-white shadow-md`}>
      {Icon && <Icon className="h-6 w-6" />}
      <div className="mt-2 text-2xl font-extrabold leading-none">{value}</div>
      <div className="mt-1 text-xs font-semibold opacity-95">{label}</div>
    </div>
  );
}

export function SectionCard({
  title,
  children,
  className = '',
}: {
  title?: string;
  children: React.ReactNode;
  className?: string;
}) {
  return (
    <div className={`siswa-card border-2 border-indigo-50 bg-white p-4 shadow-sm ${className}`}>
      {title && <h3 className="mb-3 text-sm font-extrabold text-slate-800">{title}</h3>}
      {children}
    </div>
  );
}
