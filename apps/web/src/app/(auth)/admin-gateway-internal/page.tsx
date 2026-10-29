import React, { Suspense } from 'react';
import { Metadata } from 'next';
import { AdminLoginForm } from '@/features/auth/admin-login-form';
import { ShieldAlert } from 'lucide-react';

export const metadata: Metadata = {
  title: 'Back-Office Secure Access | SISTLABUM',
  robots: {
    index: false,
    follow: false,
  },
};

export default function SecretAdminGatePage() {
  return (
    <div className="min-h-screen bg-slate-900 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
      <div className="sm:mx-auto sm:w-full sm:max-w-md text-center">
        <div className="inline-flex h-14 w-14 items-center justify-center rounded-xl bg-slate-800 text-amber-400 border border-slate-700 shadow-lg mb-4">
          <ShieldAlert className="h-8 w-8" />
        </div>
        <h1 className="text-xl font-bold tracking-tight text-white">
          SISTLABUM Back-Office Gateway
        </h1>
        <p className="mt-1 text-xs text-slate-400 font-mono">
          Akses Internal Terproteksi — Administrator & Bendahara
        </p>
      </div>

      <div className="mt-8 sm:mx-auto sm:w-full sm:max-w-md px-4 sm:px-0">
        <div className="bg-white py-8 px-6 sm:px-10 shadow-2xl rounded-2xl border border-slate-700">
          <Suspense fallback={<div className="text-center py-8 text-sm text-slate-500">Memuat gateway...</div>}>
            <AdminLoginForm />
          </Suspense>
        </div>
      </div>
    </div>
  );
}
