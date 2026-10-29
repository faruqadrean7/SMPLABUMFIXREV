'use client';

import React from 'react';
import { Award } from 'lucide-react';
import { useStudentDashboard } from '@/lib/use-student-data';
import { LoadingSpinner } from '@/components/shared/loading-spinner';
import { ErrorState } from '@/components/shared/error-state';
import { EmptyState } from '@/components/shared/empty-state';

export default function SiswaPoinPage() {
  const { data, isLoading, isError, refetch } = useStudentDashboard();
  const count = data?.totalAchievements ?? 0;

  if (isLoading) return <div className="py-20"><LoadingSpinner label="Memuat poin..." size="lg" /></div>;
  if (isError) return <div className="py-16"><ErrorState message="Gagal memuat data poin. Coba lagi." onRetry={() => refetch()} /></div>;

  return (
    <div className="flex flex-col gap-3.5 text-slate-900">
      <div className="bg-white border border-slate-200 rounded-xl p-4 shadow-xs">
        <h1 className="text-lg font-extrabold tracking-tight">Poin Sikap &amp; Prestasi</h1>
        <p className="text-xs text-slate-600 mt-0.5">Ringkasan catatan kesiswaan Anda</p>
      </div>

      <div className="bg-white border border-slate-200 rounded-xl p-5 shadow-xs flex items-center gap-4">
        <div className="w-14 h-14 rounded-xl bg-emerald-50 text-emerald-700 border border-emerald-200 flex items-center justify-center shrink-0">
          <Award className="w-7 h-7" />
        </div>
        <div>
          <p className="text-3xl font-extrabold tracking-tight">{count}</p>
          <p className="text-xs text-slate-500 font-medium">Poin prestasi tercatat</p>
        </div>
      </div>

      <div className="bg-white border border-slate-200 rounded-xl p-3.5 shadow-xs">
        <EmptyState
          title="Rincian poin dikelola wali kelas"
          description="Detail riwayat poin prestasi & pelanggaran dicatat oleh wali kelas dan guru BK, serta dirangkum pada rapor. Hubungi wali kelas untuk perincian."
        />
      </div>
    </div>
  );
}
