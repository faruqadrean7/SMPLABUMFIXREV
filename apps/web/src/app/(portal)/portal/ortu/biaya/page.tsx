'use client';

import React from 'react';
import { useGuardian } from '@/lib/guardian-context';
import { LoadingSpinner } from '@/components/shared/loading-spinner';
import { ErrorState } from '@/components/shared/error-state';
import { ParentFinanceTab } from '@/features/finance/components/parent-finance-tab';

export default function OrtuBiayaPage() {
  const { guardian, activeChild, activeChildId, setActiveChildId, isLoading, isError, refetchAll } = useGuardian();
  const children = guardian?.children ?? [];

  if (isLoading) return <div className="py-20"><LoadingSpinner label="Memuat tagihan..." size="lg" /></div>;
  if (isError) return <div className="py-16"><ErrorState message="Gagal memuat tagihan. Coba lagi." onRetry={() => refetchAll()} /></div>;

  return (
    <div className="flex flex-col gap-4 text-slate-900">
      <div className="bg-white border border-slate-200 rounded-xl p-4 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div className="min-w-0">
          <h1 className="text-lg font-extrabold tracking-tight">Tagihan &amp; Pembayaran SPP Sekolah</h1>
          <p className="text-xs text-slate-600 mt-0.5">
            {activeChild?.name} &middot; Informasi Virtual Account, panduan transfer, dan unggah slip konfirmasi.
          </p>
        </div>
        {children.length > 1 && (
          <div className="flex flex-wrap gap-1.5">
            {children.map((c) => (
              <button
                key={c.id}
                type="button"
                onClick={() => setActiveChildId(c.id)}
                className={'px-3 py-1.5 rounded-lg text-xs font-bold border transition-colors ' + (c.id === activeChildId ? 'bg-blue-900 text-white border-blue-900' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-100')}
              >
                {c.name}
              </button>
            ))}
          </div>
        )}
      </div>

      {activeChildId ? (
        <ParentFinanceTab childId={activeChildId} />
      ) : (
        <div className="bg-white border border-slate-200 rounded-xl p-6 text-center text-xs text-slate-500">
          Pilih data siswa untuk melihat tagihan dan konfirmasi pembayaran.
        </div>
      )}
    </div>
  );
}
