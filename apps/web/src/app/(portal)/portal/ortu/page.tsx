'use client';

import React from 'react';
import Link from 'next/link';
import { useAuth } from '@/providers/auth-provider';
import { useChildren } from '@/features/portal';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Avatar } from '@/components/ui/avatar';
import { LoadingSpinner } from '@/components/shared/loading-spinner';
import {
  Users,
  GraduationCap,
  ArrowRight,
  ShieldCheck,
  CalendarCheck,
  FileText,
  HeartHandshake,
  Sparkles,
} from 'lucide-react';

export default function ParentChildrenSelectorPage() {
  const { user } = useAuth();
  const { data: childrenList = [], isLoading } = useChildren(user);

  const parentName = user?.profile?.name || user?.profile?.fullName || user?.username || 'Bapak/Ibu Wali Murid';

  return (
    <div className="space-y-6">
      {/* Welcome Header */}
      <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4 bg-white p-6 rounded-2xl border border-slate-200 shadow-xs">
        <div>
          <div className="inline-flex items-center gap-1.5 px-3 py-1 bg-emerald-50 text-status-success rounded-full text-xs font-semibold mb-2">
            <ShieldCheck className="h-3.5 w-3.5" />
            <span>Perwalian Resmi Terverifikasi</span>
          </div>
          <h1 className="text-2xl font-black text-slate-900 tracking-tight">
            Selamat Datang, {parentName}
          </h1>
          <p className="text-sm text-slate-600 mt-1">
            Pusat monitoring terpadu kehadiran, capaian belajar, e-rapor, dan karakter ananda di SMP Laboratorium UM.
          </p>
        </div>

        <div className="flex items-center gap-2">
          <Badge variant="outline" className="text-xs px-3 py-1 font-bold">
            {childrenList.length} Anak Terdaftar
          </Badge>
        </div>
      </div>

      {/* Children Cards List */}
      <div>
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-lg font-bold text-slate-900 flex items-center gap-2">
            <Users className="h-5 w-5 text-brand-blue" />
            <span>Pilih Profil Ananda</span>
          </h2>
          <span className="text-xs text-slate-500">Klik kartu untuk melihat rincian akademik</span>
        </div>

        {isLoading ? (
          <div className="min-h-[300px] flex items-center justify-center bg-white rounded-2xl border border-slate-200">
            <LoadingSpinner label="Memuat daftar ananda..." size="lg" />
          </div>
        ) : childrenList.length === 0 ? (
          <Card className="border-slate-200 py-12 text-center">
            <CardContent>
              <Users className="h-12 w-12 text-slate-300 mx-auto mb-3" />
              <h3 className="text-base font-bold text-slate-700">Tidak Ada Data Anak</h3>
              <p className="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                Akun Anda belum ditautkan dengan data siswa aktif. Silakan hubungi bagian Tata Usaha sekolah.
              </p>
            </CardContent>
          </Card>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            {childrenList.map((child) => (
              <Card
                key={child.id}
                className="group hover:border-brand-blue hover:shadow-lg transition-all duration-300 border-slate-200 overflow-hidden flex flex-col justify-between"
              >
                <div>
                  <div className="h-2 bg-gradient-to-r from-blue-600 to-indigo-600 w-full" />
                  <CardHeader className="flex flex-row items-start gap-4 pb-3">
                    <Avatar
                      src={(child as any).photo_url || (child as any).photoUrl}
                      name={child.name}
                      size="xl"
                      shape="rounded"
                      alt={`Foto ${child.name}`}
                      className="shrink-0 group-hover:scale-105 transition-transform shadow-xs"
                    />
                    <div className="flex-1 min-w-0">
                      <div className="flex items-center gap-2 flex-wrap">
                        <CardTitle className="text-lg font-black text-slate-900 group-hover:text-brand-blue transition-colors truncate">
                          {child.name}
                        </CardTitle>
                        <Badge className="bg-blue-50 text-brand-blue border-blue-200 hover:bg-blue-100 text-[10px] font-bold">
                          {child.className}
                        </Badge>
                      </div>
                      <CardDescription className="text-xs text-slate-500 mt-1 space-x-2">
                        <span>NISN: {child.nisn}</span>
                        {child.nis && <span>• NIS: {child.nis}</span>}
                      </CardDescription>
                    </div>
                  </CardHeader>

                  <CardContent className="pt-2 text-xs space-y-3">
                    <div className="grid grid-cols-2 gap-2 bg-slate-50 p-3 rounded-xl border border-slate-100">
                      <div>
                        <span className="text-[10px] text-slate-400 font-medium block">Tahun Ajaran</span>
                        <span className="font-bold text-slate-700">{child.academicYear || '2024/2025'}</span>
                      </div>
                      <div>
                        <span className="text-[10px] text-slate-400 font-medium block">Status Siswa</span>
                        <span className="font-bold text-status-success flex items-center gap-1">
                          <span className="h-1.5 w-1.5 rounded-full bg-status-success animate-pulse" />
                          Aktif Belajar
                        </span>
                      </div>
                    </div>

                    <div className="flex items-center justify-between text-slate-500 pt-1 text-[11px]">
                      <span className="flex items-center gap-1.5">
                        <CalendarCheck className="h-3.5 w-3.5 text-brand-blue" />
                        <span>Presensi Realtime</span>
                      </span>
                      <span className="flex items-center gap-1.5">
                        <FileText className="h-3.5 w-3.5 text-indigo-600" />
                        <span>E-Rapor Resmi</span>
                      </span>
                      <span className="flex items-center gap-1.5">
                        <Sparkles className="h-3.5 w-3.5 text-amber-500" />
                        <span>Catatan Karakter</span>
                      </span>
                    </div>
                  </CardContent>
                </div>

                <div className="p-4 sm:p-5 pt-0 mt-4 border-t border-slate-100 flex items-center justify-between gap-3">
                  <Link href={`/portal/ortu/anak/${child.id}`} className="w-full">
                    <Button size="md" className="w-full gap-2 font-bold shadow-xs">
                      <span>Pantau Belajar Ananda</span>
                      <ArrowRight className="h-4 w-4" />
                    </Button>
                  </Link>
                </div>
              </Card>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
