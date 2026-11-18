'use client';

import React from 'react';
import Link from 'next/link';
import { ShieldAlert, ArrowLeft, HeartHandshake, Info } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { EarlyWarningManager } from '@/features/counseling/components/EarlyWarningManager';

export default function EarlyWarningsAdminPage() {
  return (
    <div className="space-y-6 p-6">
      {/* Header & Navigasi */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b pb-4">
        <div>
          <div className="flex items-center gap-2 mb-1">
            <Link
              href="/admin/counseling"
              className="text-xs text-slate-500 hover:text-indigo-600 flex items-center gap-1 transition-colors"
            >
              <ArrowLeft className="h-3 w-3" /> Kembali ke Manajemen BK
            </Link>
          </div>
          <h1 className="text-2xl font-bold tracking-tight text-slate-900 flex items-center gap-2">
            <ShieldAlert className="h-6 w-6 text-indigo-600" />
            Deteksi Dini Siswa Berisiko (Early Warning System)
          </h1>
          <p className="text-xs text-slate-500 mt-1">
            Sistem deteksi dini otomatis untuk mengidentifikasi anomali presensi, penurunan nilai drastis, dan akumulasi poin pelanggaran sebelum terjadi masalah yang lebih kompleks.
          </p>
        </div>

        <div className="flex items-center gap-2">
          <Link href="/admin/counseling">
            <Button variant="outline" size="sm" className="text-xs gap-1.5 border-slate-200">
              <HeartHandshake className="h-3.5 w-3.5 text-indigo-600" /> Rekam Kasus Konseling
            </Button>
          </Link>
        </div>
      </div>

      {/* Informasi Kebijakan & Privasi Edukasi */}
      <div className="rounded-lg bg-blue-50 border border-blue-200 p-4 text-blue-950 shadow-sm flex items-start gap-3">
        <Info className="h-5 w-5 text-blue-600 shrink-0 mt-0.5" />
        <div className="text-xs">
          <div className="font-bold flex items-center gap-1.5">
            PRINSIP DETEKSI DINI & PENDAMPINGAN EDUKATIF
            <Badge variant="outline" className="text-[10px] bg-blue-100/50 border-blue-300 text-blue-800">
              Non-Stigmatisasi
            </Badge>
          </div>
          <p className="text-blue-900 mt-1 leading-relaxed">
            Sistem peringatan dini bertujuan untuk <strong>pencegahan proaktif dan pemberian dukungan belajar</strong> sedini mungkin. Seluruh data alert bersifat rahasia dan hanya dapat diakses oleh Guru BK, Wali Kelas terkait, dan Kepala Sekolah. Dilarang menggunakan indikator deteksi dini untuk pelabelan negatif terhadap siswa.
          </p>
        </div>
      </div>

      {/* Komponen Utama Manajemen Early Warning */}
      <EarlyWarningManager />
    </div>
  );
}
