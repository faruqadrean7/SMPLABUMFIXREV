'use client';

import React, { Suspense, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { useAuth } from '@/providers/auth-provider';
import { LoginForm } from '@/features/auth/login-form';
import { resolveUserDestination, getDestinationPath } from '@/lib/rbac/evaluator';
import { School, Smartphone, ExternalLink, ShieldCheck } from 'lucide-react';
import Link from 'next/link';

function MasukContent() {
  const router = useRouter();
  const { user, isAuthenticated, isLoading } = useAuth();

  // Jika sudah terautentikasi, langsung alihkan ke rute portal internal yang sesuai
  useEffect(() => {
    if (!isLoading && isAuthenticated && user) {
      const destination = resolveUserDestination(user.roles as string[]);
      router.replace(getDestinationPath(destination));
    }
  }, [isLoading, isAuthenticated, user, router]);

  return (
    <div className="min-h-screen bg-slate-50 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
      <div className="sm:mx-auto sm:w-full sm:max-w-md text-center">
        <div className="inline-flex h-14 w-14 items-center justify-center rounded-xl bg-brand-blue text-white shadow-md mb-4">
          <School className="h-8 w-8" />
        </div>
        <h1 className="text-2xl font-bold tracking-tight text-slate-900">
          Portal Sekolah Satu Pintu
        </h1>
        <p className="mt-1 text-xs text-slate-600 font-medium">
          SMP-SMA Laboratorium Universitas Negeri Malang (SISTLABUM)
        </p>
      </div>

      <div className="mt-8 sm:mx-auto sm:w-full sm:max-w-md px-4 sm:px-0">
        <div className="bg-white py-8 px-6 sm:px-10 shadow-elevated rounded-xl border border-slate-200">
          <LoginForm />
        </div>

        {/* Informasi Akses Ekstra */}
        <div className="mt-6 space-y-3">
          <div className="p-4 rounded-lg bg-blue-50/70 border border-blue-100 flex items-start gap-3 text-xs text-slate-700">
            <Smartphone className="h-5 w-5 text-brand-blue shrink-0 mt-0.5" />
            <div>
              <span className="font-semibold text-slate-900">Aplikasi Mobile Android:</span>
              <p className="text-slate-600 mt-0.5">
                Siswa dan Orang Tua dapat memantau presensi dan jadwal melalui aplikasi native SISTLABUM Android.
              </p>
            </div>
          </div>

          <div className="flex items-center justify-between text-xs px-2 text-slate-500">
            <div className="flex items-center gap-1.5">
              <ShieldCheck className="h-4 w-4 text-brand-teal" />
              <span>Koneksi Aman Terenkripsi</span>
            </div>
            <Link
              href="/"
              className="inline-flex items-center gap-1 text-brand-blue hover:underline font-medium"
            >
              Beranda Sekolah
              <ExternalLink className="h-3 w-3" />
            </Link>
          </div>
        </div>
      </div>
    </div>
  );
}

export default function MasukPage() {
  return (
    <Suspense fallback={<div className="min-h-screen bg-slate-50 flex items-center justify-center">Memuat...</div>}>
      <MasukContent />
    </Suspense>
  );
}
