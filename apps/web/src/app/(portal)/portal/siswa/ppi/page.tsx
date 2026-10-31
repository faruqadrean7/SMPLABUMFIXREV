'use client';

import React from 'react';
import Link from 'next/link';
import { Target, HeartHandshake, CheckCircle2, Phone, ArrowLeft, Clock } from 'lucide-react';
import { useStudentDashboard } from '@/lib/use-student-data';
import { LoadingSpinner } from '@/components/shared/loading-spinner';

export default function StudentPpiPage() {
  const { data: studentDash, isLoading } = useStudentDashboard();

  if (isLoading) {
    return (
      <div className="py-20">
        <LoadingSpinner label="Memuat target PPI..." size="lg" />
      </div>
    );
  }

  const profile = studentDash?.programProfile as any;
  const gpk = profile?.gpk ?? {
    name: 'Ibu Ratna Dewi, S.Pd (GPK)',
    contact_phone: '081234567890',
    room: 'Ruang Sumber Inklusi Lab UM',
  };
  const milestones = profile?.ppi_milestones ?? [
    { id: 'm1', title: 'Menyelesaikan modul membaca teks deskriptif', is_completed: true },
    { id: 'm2', title: 'Latihan fokus & motorik halus terarah (15 menit)', is_completed: false },
    { id: 'm3', title: 'Komunikasi aktif dalam sesi kelompok kecil', is_completed: false },
  ];

  return (
    <div className="flex flex-col gap-4 text-slate-900">
      {/* Header */}
      <div className="bg-white border border-slate-200 rounded-xl p-5 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <div className="w-12 h-12 rounded-xl bg-purple-900 text-white flex items-center justify-center shrink-0">
            <Target className="w-6 h-6" />
          </div>
          <div>
            <h1 className="text-lg font-extrabold tracking-tight">Target Mandiri & PPI</h1>
            <p className="text-xs text-slate-500">Program Pembelajaran Individual • Layanan Inklusi</p>
          </div>
        </div>
        <Link
          href="/siswa/dashboard"
          className="inline-flex items-center gap-1.5 text-xs font-bold text-slate-600 hover:text-slate-900 px-3 py-1.5 rounded-lg border border-slate-200 bg-slate-50 hover:bg-slate-100 self-start sm:self-auto"
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
          <div className="mt-4 pt-3 border-t border-purple-200 flex items-center gap-2">
            <a
              href={`tel:${gpk.contact_phone}`}
              className="inline-flex items-center gap-1.5 text-xs font-bold px-3 py-1.5 rounded-lg bg-purple-900 text-white hover:bg-purple-950"
            >
              <Phone className="w-3.5 h-3.5" /> Hubungi GPK
            </a>
          </div>
        </div>

        {/* Milestones list */}
        <div className="lg:col-span-2 p-4 rounded-xl bg-white border border-slate-200">
          <h3 className="text-xs font-bold text-slate-500 uppercase tracking-wide mb-3">
            Milestone Pembelajaran Saya
          </h3>
          <div className="space-y-2">
            {milestones.map((m: any) => (
              <div
                key={m.id}
                className="p-3 rounded-lg border border-slate-200 flex items-center justify-between gap-3 bg-slate-50/50"
              >
                <div className="flex items-center gap-2.5">
                  <CheckCircle2
                    className={`w-4 h-4 shrink-0 ${
                      m.is_completed ? 'text-emerald-600' : 'text-slate-300'
                    }`}
                  />
                  <span
                    className={`text-sm ${
                      m.is_completed ? 'line-through text-slate-400' : 'font-medium text-slate-800'
                    }`}
                  >
                    {m.title}
                  </span>
                </div>
                <span
                  className={`text-[10px] font-bold px-2 py-0.5 rounded-full ${
                    m.is_completed ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700'
                  }`}
                >
                  {m.is_completed ? 'Tuntas' : 'Sedang Berjalan'}
                </span>
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
}
