'use client';

import React from 'react';
import Link from 'next/link';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  Calendar,
  Users,
  GraduationCap,
  UserCheck,
  Layers,
  BookOpen,
  ArrowRight,
  CheckCircle2,
  Clock,
  ExternalLink,
} from 'lucide-react';

const MASTER_MODULES = [
  {
    id: 'ADM-MST-01',
    title: 'Tahun Ajaran & Semester',
    description: 'Pengaturan siklus akademik aktif, tanggal mulai-selesai, dan aktivasi transaksional tunggal.',
    href: '/admin/master/academic-years',
    icon: Calendar,
    status: 'SELESAI (TERHUBUNG API)',
    statusVariant: 'success' as const,
    endpoint: 'GET/POST/PUT/PATCH /api/v1/academic-years',
    permission: 'master.academic_year.manage',
  },
  {
    id: 'ADM-MST-02',
    title: 'Data Siswa & NISN',
    description: 'Manajemen biodata siswa aktif, mutasi status, rombel terdaftar, dan ekspor data CSV.',
    href: '/admin/master/students',
    icon: Users,
    status: 'SELESAI (TERHUBUNG API)',
    statusVariant: 'success' as const,
    endpoint: 'GET/POST/PUT/DELETE /api/v1/students',
    permission: 'master.student.read / write',
  },
  {
    id: 'ADM-MST-03',
    title: 'Data Guru & Tenaga Kependidikan',
    description: 'Manajemen guru pengajar, status kepegawaian, NIP/NUPTK, wali kelas, dan akun login.',
    href: '/admin/master/teachers',
    icon: GraduationCap,
    status: 'SELESAI (TERHUBUNG API)',
    statusVariant: 'success' as const,
    endpoint: 'GET/POST/PUT/DELETE /api/v1/teachers',
    permission: 'master.teacher.manage',
  },
  {
    id: 'ADM-MST-04',
    title: 'Data Wali Murid & Relasi',
    description: 'Manajemen kontak orang tua, nomor WhatsApp darurat, dan relasi multi-anak asuh.',
    href: '/admin/master/guardians',
    icon: UserCheck,
    status: 'SELESAI (TERHUBUNG API)',
    statusVariant: 'success' as const,
    endpoint: 'GET/POST/PUT/DELETE /api/v1/guardians',
    permission: 'master.student.write',
  },
  {
    id: 'ADM-MST-05',
    title: 'Rombongan Belajar (Kelas)',
    description: 'Pembagian rombel kelas VII s.d XII dengan penegakan kuota maksimal 36 siswa per rombel.',
    href: '/admin/master/classes',
    icon: Layers,
    status: 'SELESAI (TERHUBUNG API)',
    statusVariant: 'success' as const,
    endpoint: 'GET/POST/PUT/DELETE /api/v1/classes',
    permission: 'master.class.manage',
  },
  {
    id: 'ADM-MST-06',
    title: 'Mata Pelajaran & Penugasan Guru',
    description: 'Master kurikulum mata pelajaran nasional/muatan lokal dan pemetaan guru pengampu.',
    href: '/admin/master/subjects',
    icon: BookOpen,
    status: 'SELESAI (TERHUBUNG API)',
    statusVariant: 'success' as const,
    endpoint: 'GET/POST/PUT/DELETE /api/v1/subjects',
    permission: 'master.subject.manage',
  },
];

export default function MasterDataHubPage() {
  return (
    <div className="space-y-6">
      <div>
        <div className="flex items-center gap-2 mb-1">
          <span className="text-xs font-semibold uppercase tracking-wider text-brand-blue">
            Modul Administrasi Utama
          </span>
        </div>
        <h1 className="text-2xl font-bold tracking-tight text-slate-900">
          Master Data Sekolah
        </h1>
        <p className="mt-1 text-xs text-slate-500">
          Fondasi referensi utama seluruh modul SISTLABUM: tahun ajaran, siswa, guru, wali murid,
          kelas (kuota 36), dan mata pelajaran.
        </p>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        {MASTER_MODULES.map((mod) => {
          const Icon = mod.icon;
          return (
            <Card key={mod.id} className="flex flex-col justify-between hover:shadow-elevated transition-shadow">
              <CardHeader className="pb-3">
                <div className="flex items-start justify-between gap-2">
                  <div className="p-2.5 rounded-lg bg-blue-50 text-brand-blue">
                    <Icon className="h-5 w-5" />
                  </div>
                </div>
                <CardTitle className="text-sm font-bold text-slate-900 mt-2">
                  {mod.title}
                </CardTitle>
                <CardDescription className="text-xs text-slate-500">
                  {mod.description}
                </CardDescription>
              </CardHeader>

              <CardContent className="pt-0 space-y-3">
                <Link href={mod.href} className="block">
                  <Button variant="outline" size="sm" className="w-full justify-between text-xs hover:bg-brand-blue hover:text-white transition-colors">
                    <span>Buka Modul</span>
                    <ArrowRight className="h-3.5 w-3.5" />
                  </Button>
                </Link>
              </CardContent>
            </Card>
          );
        })}
      </div>
    </div>
  );
}
