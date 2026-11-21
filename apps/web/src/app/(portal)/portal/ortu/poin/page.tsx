'use client';

import React from 'react';
import { useGuardian } from '@/lib/guardian-context';
import { LoadingSpinner } from '@/components/shared/loading-spinner';
import { ErrorState } from '@/components/shared/error-state';
import { EmptyState } from '@/components/shared/empty-state';

function fmt(value?: string): string {
  if (!value) return '';
  const d = new Date(value);
  return Number.isNaN(d.getTime()) ? value : d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
}

export default function OrtuPoinPage() {
  const { guardian, activeChild, activeChildId, setActiveChildId, activePoints, isLoading, isError, refetchAll } = useGuardian();
  const children = guardian?.children ?? [];
  const points = activePoints ?? [];
  const prestasi = points.filter((p) => p.kind === 'prestasi');
  const pelanggaran = points.filter((p) => p.kind === 'pelanggaran');

  if (isLoading) return <div className="py-20"><LoadingSpinner label="Memuat poin sikap..." size="lg" /></div>;
  if (isError) return <div className="py-16"><ErrorState message="Gagal memuat poin. Coba lagi." onRetry={() => refetchAll()} /></div>;

  return (
    <div className="flex flex-col gap-3.5 text-slate-900">
      <div className="bg-white border border-slate-200 rounded-xl p-4 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div className="min-w-0">
          <h1 className="text-lg font-extrabold tracking-tight">Poin Sikap &amp; Kedisiplinan</h1>
          <p className="text-xs text-slate-600 mt-0.5">{activeChild?.name} &middot; {prestasi.length} prestasi &middot; {pelanggaran.length} pelanggaran</p>
        </div>
        {children.length > 1 && (
          <div className="flex flex-wrap gap-1.5">
            {children.map((c) => (
              <button key={c.id} type="button" onClick={() => setActiveChildId(c.id)}
                className={'px-3 py-1.5 rounded-lg text-xs font-bold border transition-colors ' + (c.id === activeChildId ? 'bg-blue-900 text-white border-blue-900' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-100')}>
                {c.name}
              </button>
            ))}
          </div>
        )}
      </div>

      <div className="bg-white border border-slate-200 rounded-xl p-3.5 shadow-xs">
        {points.length === 0 ? (
          <EmptyState title="Belum ada catatan poin" description="Belum ada poin prestasi maupun pelanggaran tercatat." />
        ) : (
          <div className="space-y-2">
            {points.map((p) => (
              <div key={p.id} className={'p-3 rounded-xl border bg-white border-l-4 ' + (p.kind === 'prestasi' ? 'border-slate-200 border-l-emerald-700' : 'border-slate-200 border-l-rose-600')}>
                <div className="flex items-center justify-between gap-2">
                  <div className="min-w-0">
                    <h4 className="text-sm font-bold truncate">{p.title}</h4>
                    <p className="text-[11px] text-slate-500">{p.category} &middot; {fmt(p.point_date)} &middot; {p.recorder_name}</p>
                  </div>
                  <span className={'px-2.5 py-0.5 rounded-md text-xs font-bold shrink-0 ' + (p.kind === 'prestasi' ? 'bg-emerald-700 text-white' : 'bg-rose-600 text-white')}>
                    {p.kind === 'prestasi' ? '+' : ''}{p.points}
                  </span>
                </div>
                {p.note && <p className="text-[11px] text-slate-600 mt-1">{p.note}</p>}
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
