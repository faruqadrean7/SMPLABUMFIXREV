'use client';

import React, { useState, useEffect } from 'react';
import Link from 'next/link';
import { School, Mail, ArrowLeft, CheckCircle2, AlertCircle, Loader2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { apiClient } from '@/lib/api/api-client';

export default function LupaPasswordPage() {
  const [email, setEmail] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const [isSuccess, setIsSuccess] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [isHydrated, setIsHydrated] = useState(false);

  useEffect(() => {
    setIsHydrated(true);
  }, []);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setErrorMessage(null);

    if (!email.trim()) {
      setErrorMessage('Alamat email wajib diisi.');
      return;
    }

    setIsLoading(true);
    try {
      await apiClient.post('/auth/forgot-password', { email: email.trim() });
      setIsSuccess(true);
    } catch (err: any) {
      if (err.response?.status === 429) {
        setErrorMessage('Terlalu banyak permintaan pemulihan kata sandi. Silakan tunggu beberapa saat.');
      } else {
        // Uniform message to prevent user enumeration
        setIsSuccess(true);
      }
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-slate-50 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
      <div className="sm:mx-auto sm:w-full sm:max-w-md text-center">
        <div className="inline-flex h-14 w-14 items-center justify-center rounded-xl bg-brand-blue text-white shadow-md mb-4">
          <School className="h-8 w-8" />
        </div>
        <h1 className="text-2xl font-bold tracking-tight text-slate-900">
          Pemulihan Kata Sandi
        </h1>
        <p className="mt-1 text-xs text-slate-600 font-medium">
          SMP-SMA Laboratorium Universitas Negeri Malang (SISTLABUM)
        </p>
      </div>

      <div className="mt-8 sm:mx-auto sm:w-full sm:max-w-md px-4 sm:px-0">
        <div className="bg-white py-8 px-6 sm:px-10 shadow-elevated rounded-xl border border-slate-200">
          {isSuccess ? (
            <div className="text-center space-y-4">
              <div className="inline-flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 mb-1">
                <CheckCircle2 className="h-6 w-6" />
              </div>
              <h2 className="text-base font-bold text-slate-900">Tautan Pemulihan Terkirim</h2>
              <p className="text-xs text-slate-600 leading-relaxed">
                Jika alamat email <span className="font-semibold text-slate-800">{email}</span> terdaftar pada sistem, kami telah mengirimkan instruksi dan tautan untuk mengatur ulang kata sandi Anda.
              </p>
              <div className="pt-4">
                <Link href="/masuk">
                  <Button variant="outline" className="w-full text-xs">
                    <ArrowLeft className="h-4 w-4 mr-2" />
                    Kembali ke Halaman Masuk
                  </Button>
                </Link>
              </div>
            </div>
          ) : (
            <form onSubmit={handleSubmit} method="POST" className="space-y-4" noValidate>
              {errorMessage && (
                <div className="p-3.5 rounded-lg bg-red-50 border border-red-200 text-xs text-red-700 flex items-start gap-2">
                  <AlertCircle className="h-4 w-4 text-red-600 shrink-0 mt-0.5" />
                  <span>{errorMessage}</span>
                </div>
              )}

              <div>
                <label htmlFor="email" className="block text-xs font-semibold text-slate-700 mb-1">
                  Alamat Email Terdaftar
                </label>
                <div className="relative">
                  <Mail className="absolute left-3.5 top-3 h-4 w-4 text-slate-400" />
                  <Input
                    id="email"
                    type="email"
                    placeholder="nama@domain.sch.id"
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    className="pl-10 text-sm"
                    required
                  />
                </div>
                <p className="text-[11px] text-slate-500 mt-1">
                  Masukkan email yang tertaut dengan akun civitas atau staf Anda.
                </p>
              </div>

              <Button
                type="submit"
                disabled={!isHydrated || isLoading}
                className="w-full h-10 bg-brand-blue hover:bg-brand-blue/90 text-white font-medium text-sm"
              >
                {isLoading ? (
                  <>
                    <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                    Mengirim Tautan...
                  </>
                ) : (
                  'Kirim Tautan Pemulihan'
                )}
              </Button>

              <div className="pt-2 text-center">
                <Link href="/masuk" className="text-xs text-slate-600 hover:text-brand-blue inline-flex items-center gap-1">
                  <ArrowLeft className="h-3.5 w-3.5" />
                  Batal dan kembali ke halaman masuk
                </Link>
              </div>
            </form>
          )}
        </div>
      </div>
    </div>
  );
}
