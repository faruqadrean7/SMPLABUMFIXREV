'use client';

import React from 'react';
import Link from 'next/link';
import { Layers, Award, Calendar, Users, ArrowLeft } from 'lucide-react';
import { useStudentDashboard } from '@/lib/use-student-data';
import { LoadingSpinner } from '@/components/shared/loading-spinner';

export default function StudentP5Page() {
  const { data: studentDash, isLoading } = useStudentDashboard();

  if (isLoading) {
    return (
      <div className="py-20">
        <LoadingSpinner label="Memuat proyek P5..." size="lg" />
      </div>
    );
  }

  const profile = studentDash?.programProfile as any;
  const p5 = profile?.p5_project ?? {
    title: 'Gaya Hidup Berkelanjutan: Pengolahan Sampah Organik Sekolah',
    theme: 'Kearifan Lokal & Lingkungan',
    phase: 'Aksi Nyata & Pembuatan Produk',
    group_name: 'Kelompok Cendekia 3',
    deadline: '2026-11-20',
  };
  const ekskul = profile?.ekskul ?? [
    { name: 'Robotika & Coding', day: 'Kamis', time: '15:00 - 17:00' },
    { name: 'Bina Prestasi OSN IPA', day: 'Sabtu', time: '08:00 - 10:00' },
  ];

  return (
    <div className="flex flex-col gap-4 text-slate-900">
      {/* Header */}
      <div className="bg-white border border-slate-200 rounded-xl p-5 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <div className="w-12 h-12 rounded-xl bg-slate-800 text-white flex items-center justify-center shrink-0">
            <Layers className="w-6 h-6" />
          </div>
          <div>
            <h1 className="text-lg font-extrabold tracking-tight">Proyek P5 & Ekstrakurikuler</h1>
            <p className="text-xs text-slate-500">Projek Penguatan Profil Pelajar Pancasila • Kelas Reguler</p>
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
        {/* P5 Project Card */}
        <div className="lg:col-span-2 p-5 rounded-xl bg-white border border-slate-200">
          <span className="text-xs font-bold text-slate-500 uppercase tracking-wide">
            Proyek Kolaborasi Berjalan
          </span>
          <h2 className="mt-2 text-base font-extrabold text-slate-900">{p5.title}</h2>
          <div className="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
            <div className="p-3 bg-slate-50 rounded-lg border border-slate-200">
              <span className="text-slate-500">Tema Proyek</span>
              <p className="font-bold text-slate-900 mt-0.5">{p5.theme}</p>
            </div>
            <div className="p-3 bg-slate-50 rounded-lg border border-slate-200">
              <span className="text-slate-500">Tahap Pelaksanaan</span>
              <p className="font-bold text-slate-900 mt-0.5">{p5.phase}</p>
            </div>
          </div>
          <div className="mt-3 flex items-center justify-between text-xs text-slate-600">
            <span>Kelompok: <strong>{p5.group_name}</strong></span>
            <span>Tenggat: <strong>{p5.deadline}</strong></span>
          </div>
        </div>

        {/* Ekskul Card */}
        <div className="p-5 rounded-xl bg-white border border-slate-200">
          <span className="text-xs font-bold text-slate-500 uppercase tracking-wide">
            Bina Prestasi & Ekskul
          </span>
          <div className="mt-3 space-y-2.5">
            {ekskul.map((e: any, idx: number) => (
              <div key={idx} className="p-2.5 rounded-lg bg-slate-50 border border-slate-200 text-xs">
                <p className="font-bold text-slate-900">{e.name}</p>
                <p className="text-slate-500 mt-0.5">{e.day}, {e.time}</p>
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
}
