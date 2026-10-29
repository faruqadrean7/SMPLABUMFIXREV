'use client';

import React from 'react';
import { useGuardian } from '@/lib/guardian-context';
import { LoadingSpinner } from '@/components/shared/loading-spinner';
import { ErrorState } from '@/components/shared/error-state';
import { EmptyState } from '@/components/shared/empty-state';

export default function OrtuBiayaPage() {
  const { guardian, activeChild, activeChildId, setActiveChildId, activeFees, isLoading, isError, refetchAll } = useGuardian();
  const children = guardian?.children ?? [];
  const fees = activeFees ?? [];
  const lunas = (f: { status: string }) => f.status === 'Lunas / Bebas Tanggungan';
  const outstanding = fees.filter((f) => !lunas(f));

  if (isLoading) return <div className="py-20"><LoadingSpinner label="Memuat tagihan..." size="lg" /></div>;
  if (isError) return <div className="py-16"><ErrorState message="Gagal memuat tagihan. Coba lagi." onRetry={() => refetchAll()} /></div>;

  return (
    <div className="flex flex-col gap-3.5 text-slate-900">
      <div className="bg-white border border-slate-200 rounded-xl p-4 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div className="min-w-0">
          <h1 className="text-lg font-extrabold tracking-tight">Tagihan &amp; Administrasi Keuangan</h1>
          <p className="text-xs text-slate-600 mt-0.5">{activeChild?.name} &middot; {outstanding.length === 0 ? 'Bebas tanggungan' : outstanding.length + ' item perlu diselesaikan'}</p>
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
        {fees.length === 0 ? (
          <EmptyState title="Belum ada tagihan" description="Belum ada data tagihan untuk siswa ini." />
        ) : (
          <div className="space-y-2">
            {fees.map((f) => (
              <div key={f.id} className={'p-3 rounded-xl border bg-white border-l-4 ' + (lunas(f) ? 'border-slate-200 border-l-emerald-700' : 'border-slate-200 border-l-amber-600')}>
                <div className="flex items-center justify-between gap-2">
                  <div className="min-w-0">
                    <h4 className="text-sm font-bold truncate">{f.item_name}</h4>
                    <p className="text-[11px] text-slate-500">{f.period_label}</p>
                  </div>
                  <span className="text-sm font-bold font-mono shrink-0">{f.nominal_text}</span>
                </div>
                <div className="flex items-center justify-between mt-1 pt-1 border-t border-slate-100">
                  <span className={'px-2 py-0.5 rounded-md text-[10px] font-bold ' + (lunas(f) ? 'bg-emerald-700 text-white' : 'bg-amber-600 text-white')}>{f.status}</span>
                  {f.receipt_number && <span className="text-[11px] text-slate-500 font-mono">No. {f.receipt_number}</span>}
                </div>
                {f.notes && <p className="text-[11px] text-slate-600 mt-1">{f.notes}</p>}
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
