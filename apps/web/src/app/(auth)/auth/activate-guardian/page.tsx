'use client';

import React, { Suspense, useEffect, useState } from 'react';
import Link from 'next/link';
import { useRouter, useSearchParams } from 'next/navigation';
import { AlertCircle, CheckCircle2, Loader2, Lock, School, UserCheck } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { apiClient } from '@/lib/api/api-client';

interface ActivationPreview {
  valid: boolean;
  account_type: string;
  username: string;
  masked_email: string;
}

function ActivateGuardianForm() {
  const searchParams = useSearchParams();
  const router = useRouter();
  const token = searchParams.get('token') || '';

  const [preview, setPreview] = useState<ActivationPreview | null>(null);
  const [isChecking, setIsChecking] = useState(true);
  const [tokenError, setTokenError] = useState<string | null>(null);

  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState<string | null>(null);
  const [isActivated, setIsActivated] = useState(false);

  useEffect(() => {
    let active = true;
    const checkToken = async () => {
      if (!token) {
        if (active) {
          setTokenError('Token aktivasi tidak ditemukan pada tautan.');
          setIsChecking(false);
        }
        return;
      }

      setIsChecking(true);
      setTokenError(null);
      try {
        const res = await apiClient.get<{ data: ActivationPreview }>('/auth/activate-guardian', {
          params: { token },
        });
        if (active) {
          setPreview(res.data?.data ?? null);
        }
      } catch (err: any) {
        if (active) {
          const msg =
            err?.response?.data?.message ||
            err?.response?.data?.error?.message ||
            'Tautan aktivasi akun tidak valid atau sudah kedaluwarsa.';
          setTokenError(msg);
        }
      } finally {
        if (active) {
          setIsChecking(false);
        }
      }
    };

    checkToken();
    return () => {
      active = false;
    };
  }, [token]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSubmitError(null);

    if (password.length < 8) {
      setSubmitError('Kata sandi minimal 8 karakter.');
      return;
    }

    if (password !== passwordConfirmation) {
      setSubmitError('Konfirmasi kata sandi tidak cocok.');
      return;
    }

    setIsSubmitting(true);
    try {
      await apiClient.post('/auth/activate-guardian', {
        token,
        password,
        password_confirmation: passwordConfirmation,
      });
      setIsActivated(true);
    } catch (err: any) {
      const msg =
        err?.response?.data?.message ||
        err?.response?.data?.error?.message ||
        'Gagal mengaktifkan akun. Tautan mungkin sudah digunakan atau kedaluwarsa.';
      setSubmitError(msg);
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="bg-white py-8 px-6 sm:px-10 shadow-elevated rounded-xl border border-slate-200">
      {isChecking ? (
        <div className="py-8 text-center space-y-3">
          <Loader2 className="h-7 w-7 animate-spin mx-auto text-brand-blue" />
          <p className="text-xs font-medium text-slate-600">Memeriksa tautan aktivasi akun...</p>
        </div>
      ) : tokenError ? (
        <div className="text-center space-y-4">
          <div className="inline-flex h-12 w-12 items-center justify-center rounded-full bg-red-100 text-red-600">
            <AlertCircle className="h-6 w-6" />
          </div>
          <h2 className="text-base font-bold text-slate-900">Tautan Aktivasi Tidak Valid</h2>
          <p className="text-xs text-slate-600 leading-relaxed">{tokenError}</p>
          <div className="pt-2">
            <Link
              href="/masuk"
              className="inline-flex items-center justify-center w-full h-10 rounded-lg bg-slate-900 hover:bg-slate-800 text-white text-xs font-medium transition-colors"
            >
              Kembali ke Halaman Masuk
            </Link>
          </div>
        </div>
      ) : isActivated ? (
        <div className="text-center space-y-4">
          <div className="inline-flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
            <CheckCircle2 className="h-6 w-6" />
          </div>
          <h2 className="text-base font-bold text-slate-900">Akun Berhasil Diaktifkan</h2>
          <p className="text-xs text-slate-600 leading-relaxed">
            Kata sandi akun <span className="font-semibold text-slate-900">{preview?.username}</span> telah
            disimpan. Anda kini dapat masuk ke portal sekolah.
          </p>
          <div className="pt-2">
            <Button
              onClick={() => router.push('/masuk')}
              className="w-full bg-brand-blue hover:bg-brand-blue/90 text-white text-xs"
            >
              Masuk ke Portal Sekolah
            </Button>
          </div>
        </div>
      ) : (
        <form onSubmit={handleSubmit} method="POST" className="space-y-4" noValidate>
          <div className="p-3.5 rounded-lg bg-slate-50 border border-slate-200 flex items-start gap-3">
            <UserCheck className="h-5 w-5 text-brand-blue shrink-0 mt-0.5" />
            <div className="text-xs">
              <p className="font-semibold text-slate-800">
                Aktivasi Akun {preview?.account_type === 'student' ? 'Siswa' : 'Wali Murid'}
              </p>
              <p className="text-slate-600 mt-0.5">
                Username: <span className="font-mono font-semibold text-slate-900">{preview?.username}</span>
              </p>
              {preview?.masked_email && (
                <p className="text-slate-500 mt-0.5">Email: {preview.masked_email}</p>
              )}
            </div>
          </div>

          {submitError && (
            <div className="p-3.5 rounded-lg bg-red-50 border border-red-200 text-xs text-red-700 flex items-start gap-2">
              <AlertCircle className="h-4 w-4 text-red-600 shrink-0 mt-0.5" />
              <span>{submitError}</span>
            </div>
          )}

          <div>
            <label htmlFor="password" className="block text-xs font-semibold text-slate-700 mb-1">
              Buat Kata Sandi Baru
            </label>
            <div className="relative">
              <Lock className="absolute left-3.5 top-3 h-4 w-4 text-slate-400" />
              <Input
                id="password"
                type="password"
                placeholder="Minimal 8 karakter"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                className="pl-10 text-sm"
                required
              />
            </div>
          </div>

          <div>
            <label
              htmlFor="password_confirmation"
              className="block text-xs font-semibold text-slate-700 mb-1"
            >
              Konfirmasi Kata Sandi
            </label>
            <div className="relative">
              <Lock className="absolute left-3.5 top-3 h-4 w-4 text-slate-400" />
              <Input
                id="password_confirmation"
                type="password"
                placeholder="Ulangi kata sandi"
                value={passwordConfirmation}
                onChange={(e) => setPasswordConfirmation(e.target.value)}
                className="pl-10 text-sm"
                required
              />
            </div>
          </div>

          <Button
            type="submit"
            disabled={isSubmitting}
            className="w-full h-10 bg-brand-blue hover:bg-brand-blue/90 text-white font-medium text-sm"
          >
            {isSubmitting ? (
              <>
                <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                Mengaktifkan Akun...
              </>
            ) : (
              'Aktifkan Akun & Simpan Kata Sandi'
            )}
          </Button>
        </form>
      )}
    </div>
  );
}

export default function ActivateGuardianPage() {
  return (
    <div className="min-h-screen bg-slate-50 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
      <div className="sm:mx-auto sm:w-full sm:max-w-md text-center">
        <div className="inline-flex h-14 w-14 items-center justify-center rounded-xl bg-brand-blue text-white shadow-md mb-4">
          <School className="h-8 w-8" />
        </div>
        <h1 className="text-2xl font-bold tracking-tight text-slate-900">
          Aktivasi Akun Portal Sekolah
        </h1>
        <p className="mt-1 text-xs text-slate-600 font-medium">
          SMP Laboratorium Universitas Negeri Malang (SISTLABUM)
        </p>
      </div>

      <div className="mt-8 sm:mx-auto sm:w-full sm:max-w-md px-4 sm:px-0">
        <Suspense
          fallback={<div className="p-8 text-center text-xs text-slate-500">Memuat formulir aktivasi...</div>}
        >
          <ActivateGuardianForm />
        </Suspense>
      </div>
    </div>
  );
}
