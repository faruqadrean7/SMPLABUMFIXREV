'use client';

import React from 'react';
import Link from 'next/link';
import { Layers, Award, ChevronRight, CheckCircle2, Users, Calendar } from 'lucide-react';
import { RegulerProfileData } from '@/lib/use-student-data';

interface RegulerWidgetProps {
  profile?: RegulerProfileData | Record<string, any>;
}

export function RegulerWidget({ profile }: RegulerWidgetProps) {
  const kktp = profile?.kktp_summary ?? {
    total_subjects: 11,
    tuntas_count: 10,
    remedial_count: 1,
    average_score: 86.4,
  };

  const p5 = profile?.p5_project ?? {
    title: 'Pengolahan Sampah Organik Sekolah',
    theme: 'Kearifan Lokal & Lingkungan',
    phase: 'Aksi Nyata',
    group_name: 'Kelompok Cendekia 3',
    deadline: '2026-11-20',
  };

  const ekskul = profile?.ekskul ?? [
    { name: 'Robotika & Coding', day: 'Kamis', time: '15:00 - 17:00' },
    { name: 'Bina Prestasi OSN IPA', day: 'Sabtu', time: '08:00 - 10:00' },
  ];

  const tuntasPercentage = Math.round((kktp.tuntas_count / kktp.total_subjects) * 100);

  return (
    <div className="bg-white border border-slate-200 rounded-xl p-4 sm:p-5 shadow-xs relative overflow-hidden">
      {/* Header bar */}
      <div className="relative z-10 flex flex-wrap items-center justify-between gap-2 pb-3.5 border-b border-slate-100">
        <div className="flex items-center gap-2.5">
          <div className="w-8 h-8 rounded-lg bg-slate-900 text-white flex items-center justify-center shrink-0 shadow-2xs">
            <Layers className="w-4 h-4" />
          </div>
          <div>
            <h2 className="text-sm font-extrabold text-slate-900 tracking-tight">
              Ketuntasan Belajar & Proyek P5
            </h2>
            <p className="text-[11px] text-slate-500">Kurikulum Merdeka SMP Laboratorium UM</p>
          </div>
        </div>
        <Link
          href="/siswa/p5"
          className="text-xs font-bold text-slate-700 hover:text-slate-950 inline-flex items-center gap-1 hover:underline"
        >
          Lihat Proyek P5 <ChevronRight className="w-3.5 h-3.5" />
        </Link>
      </div>

      {/* Main Grid */}
      <div className="relative z-10 grid grid-cols-1 md:grid-cols-12 gap-3.5 mt-3.5">
        {/* KKTP Summary */}
        <div className="md:col-span-4 p-3.5 rounded-xl bg-slate-50 border border-slate-200 flex flex-col justify-between">
          <div>
            <div className="flex items-center justify-between">
              <span className="text-[10px] font-bold text-slate-500 uppercase tracking-wide">
                Ketuntasan KKTP
              </span>
              <span className="text-[10px] font-bold px-2 py-0.5 rounded-md bg-emerald-100 text-emerald-800">
                {tuntasPercentage}% Tuntas
              </span>
            </div>
            <div className="mt-2 text-xl font-black text-slate-900">
              {kktp.tuntas_count} dari {kktp.total_subjects} Mapel Tuntas
            </div>
            <p className="text-xs text-slate-600 mt-0.5">
              Rata-rata capaian: <strong>{kktp.average_score}</strong>
            </p>
          </div>
          <div className="mt-3">
            <div className="w-full bg-slate-200 rounded-full h-2">
              <div
                className="bg-emerald-600 h-2 rounded-full"
                style={{ width: `${tuntasPercentage}%` }}
              />
            </div>
          </div>
        </div>

        {/* P5 Project Tracker */}
        <div className="md:col-span-5 p-3.5 rounded-xl bg-slate-50 border border-slate-200 flex flex-col justify-between">
          <div>
            <span className="text-[10px] font-bold text-slate-500 uppercase tracking-wide">
              Proyek P5: {p5.theme}
            </span>
            <h3 className="mt-1 text-sm font-extrabold text-slate-900 line-clamp-1">{p5.title}</h3>
            <div className="mt-2 flex flex-wrap items-center gap-2 text-[11px] text-slate-600">
              <span className="px-2 py-0.5 rounded-md bg-white border border-slate-200 font-semibold">
                Fase: {p5.phase}
              </span>
              <span className="px-2 py-0.5 rounded-md bg-white border border-slate-200 font-semibold">
                {p5.group_name}
              </span>
            </div>
          </div>
          <div className="mt-2 pt-2 border-t border-slate-200 text-[10px] text-slate-500 flex items-center justify-between">
            <span>Tenggat Pengumpulan:</span>
            <span className="font-bold text-slate-700">{p5.deadline}</span>
          </div>
        </div>

        {/* Ekskul / Bina Prestasi */}
        <div className="md:col-span-3 p-3.5 rounded-xl bg-slate-50 border border-slate-200 flex flex-col justify-between">
          <div>
            <span className="text-[10px] font-bold text-slate-500 uppercase tracking-wide">
              Ekskul & Prestasi
            </span>
            <div className="mt-2 space-y-1.5">
              {ekskul.slice(0, 2).map((e: any, idx: number) => (
                <div key={idx} className="text-xs">
                  <p className="font-bold text-slate-800 truncate">{e.name}</p>
                  <p className="text-[10px] text-slate-500">{e.day}</p>
                </div>
              ))}
            </div>
          </div>
          <div className="mt-2 pt-2 border-t border-slate-200">
            <Link
              href="/siswa/poin"
              className="text-[11px] font-bold text-blue-900 hover:underline block text-center"
            >
              Lihat Poin Prestasi →
            </Link>
          </div>
        </div>
      </div>
    </div>
  );
}
