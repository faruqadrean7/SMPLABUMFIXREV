'use client';

import React from 'react';
import Link from 'next/link';
import { Target, CheckCircle2, Phone, ArrowLeft } from 'lucide-react';
import { useStudentDashboard } from '@/lib/use-student-data';
import { LoadingSpinner } from '@/components/shared/loading-spinner';
import { ErrorState } from '@/components/ui/error-state';
import {
  ProgramMaterialsCard,
  ProgramTargetsCard,
  ProgramTasksCard,
} from '@/features/portal/student-programs';

export default function StudentPpiPage() {
  const { data: studentDash, isLoading, isError, refetch } = useStudentDashboard();

  if (isLoading) {
    return (
      <div className="py-20">
        <LoadingSpinner label="Memuat target PPI..." size="lg" />
      </div>
    );
  }

  if (isError) {
    return (
      <div className="py-12">
        <ErrorState
          title="Gagal Memuat Target PPI"
          message="Terjadi kendala saat mengambil data target PPI dari server. Silakan coba kembali."
          onRetry={() => refetch()}
        />
      </div>
    );
  }

  const profile = studentDash?.programProfile as any;
  // Jangan memalsukan identitas GPK: tampilkan '-' bila data belum ada (anti-fabrikasi, AGENTS.md §6.2).
  const gpk = {
    name: profile?.gpk?.name || profile?.gpk_name || '-',
    contact_phone: profile?.gpk?.contact_phone || profile?.gpk_phone || '',
    room: profile?.gpk?.room || profile?.gpk_room || '-',
  };
  const milestones = profile?.ppi_milestones ?? [];

  return (
    <div className="flex flex-col gap-4 text-slate-900">
      {/* Header */}
      <div className="bg-white border border-slate-200 rounded-xl p-5 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <div className="w-12 h-12 rounded-xl bg-purple-900 text-white flex items-center justify-center shrink-0">
            <Target className="w-6 h-6" />
          </div>
          <div>
            <h1 className="text-lg font-extrabold tracking-tight">Target Mandiri &amp; PPI</h1>
            <p className="text-xs text-slate-500">Program Pembelajaran Individual • Layanan Inklusi</p>
          </div>
        </div>
        <Link
          href="/siswa/dashboard"
          className="inline-flex items-center gap-1.5 text-xs font-bold text-slate-600 hover:text-slate-900 px-3 py-1.5 rounded-lg border border-slate-200 bg-slate-50 hover:bg-slate-100 self-start sm:self-auto cursor-pointer"
        >
          <ArrowLeft className="w-3.5 h-3.5" /> Kembali ke Beranda
        </Link>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-3">
        {/* GPK Card */}
        <div className="p-4 rounded-xl bg-purple-50 border border-purple-200 flex flex-col justify-between">
          <div>
            <span className="text-xs font-bold text-purple-900 uppercase tracking-wide">
              Guru Pendamping Khusus (GPK)
            </span>
            <h3 className="mt-2 text-base font-extrabold text-purple-950">{gpk.name}</h3>
            <p className="text-xs text-purple-800 mt-1">{gpk.room}</p>
          </div>
          {gpk.contact_phone && gpk.contact_phone !== '-' && (
            <div className="mt-4 pt-3 border-t border-purple-200 flex items-center gap-2">
              <a
                href={`tel:${gpk.contact_phone}`}
                className="inline-flex items-center gap-1.5 text-xs font-bold px-3 py-1.5 rounded-lg bg-purple-900 text-white hover:bg-purple-950 cursor-pointer"
              >
                <Phone className="w-3.5 h-3.5" /> Hubungi GPK
              </a>
            </div>
          )}
        </div>

        {/* Milestones list */}
        <div className="lg:col-span-2 p-4 rounded-xl bg-white border border-slate-200">
          <h3 className="text-xs font-bold text-slate-500 uppercase tracking-wide mb-3">
            Milestone Pembelajaran Saya
          </h3>
          {milestones.length === 0 ? (
            <div className="p-8 rounded-lg border border-dashed border-slate-200 text-center text-xs text-slate-500">
              Belum ada target milestone PPI aktif yang ditetapkan untuk periode ini.
            </div>
          ) : (
            <div className="space-y-2">
              {milestones.map((m: any) => (
                <div
                  key={m.id}
                  className="p-3 rounded-lg border border-slate-200 flex items-center justify-between gap-3 bg-slate-50/50"
                >
                  <div className="flex items-center gap-2.5">
                    <CheckCircle2
                      className={`w-4 h-4 shrink-0 ${
                        m.is_completed ? 'text-emerald-700' : 'text-slate-300'
                      }`}
                    />
                    <span
                      className={`text-xs ${
                        m.is_completed ? 'line-through text-slate-400' : 'text-slate-800 font-medium'
                      }`}
                    >
                      {m.title}
                    </span>
                  </div>
                  <span
                    className={`text-[10px] px-2 py-0.5 rounded-md font-bold ${
                      m.is_completed
                        ? 'bg-emerald-100 text-emerald-800'
                        : 'bg-amber-100 text-amber-800'
                    }`}
                  >
                    {m.is_completed ? 'Selesai' : 'Sedang Berjalan'}
                  </span>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>

      {/* Program Tasks Section */}
      <ProgramTasksCard
        programType="inklusi"
        title="Tugas Mandiri & Terbimbing PPI"
        subtitle="Kumpulkan lembar pengerjaan materi adaptif atau dokumentasi perkembangan belajar"
      />

      {/* Grid: Materials and Targets */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <ProgramMaterialsCard
          programType="inklusi"
          title="Modul & Sumber Belajar Adaptif"
          subtitle="Bahan ajar yang dimodifikasi sesuai rancangan akomodasi pembelajaran individual"
        />

        <ProgramTargetsCard
          programType="inklusi"
          title="Target Pembelajaran Individual (PPI)"
          subtitle="Target capaian domain akademik, kemandirian motorik, dan perkembangan sosial-emosi"
        />
      </div>
    </div>
  );
}
