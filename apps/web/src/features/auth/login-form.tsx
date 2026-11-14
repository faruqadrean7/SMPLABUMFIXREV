'use client';

import React, { useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import * as z from 'zod';
import { useAuth } from '@/providers/auth-provider';
import { useRouter, useSearchParams } from 'next/navigation';
import Link from 'next/link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { getDestinationPath } from '@/lib/rbac/evaluator';
import { ApiClientError } from '@/lib/api/errors';
import { User, Lock, AlertCircle, Eye, EyeOff } from 'lucide-react';

const loginSchema = z.object({
  identifier: z.string().min(1, 'Identitas (username, email, NIS, atau NIP) wajib diisi'),
  password: z.string().min(1, 'Kata sandi wajib diisi'),
});

type LoginFormValues = z.infer<typeof loginSchema>;

export function LoginForm() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const { login } = useAuth();

  const [showPassword, setShowPassword] = useState(false);
  const [serverError, setServerError] = useState<string | null>(null);
  const [isHydrated, setIsHydrated] = useState(false);

  React.useEffect(() => {
    setIsHydrated(true);
  }, []);

  const redirectParam = searchParams.get('redirect');
  const sessionExpired = searchParams.get('session_expired');

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<LoginFormValues>({
    resolver: zodResolver(loginSchema),
    defaultValues: {
      identifier: '',
      password: '',
    },
  });

  const onSubmit = async (values: LoginFormValues) => {
    setServerError(null);
    try {
      const payload: {
        identifier: string;
        password: string;
        platform: 'web';
      } = {
        identifier: values.identifier,
        password: values.password,
        platform: 'web',
      };
      const result = await login(payload);

      if (result.destination) {
        const targetPath = redirectParam && redirectParam.startsWith('/')
          ? redirectParam
          : getDestinationPath(result.destination);
        router.push(targetPath);
        if (typeof window !== 'undefined') {
          try {
            window.location.href = targetPath;
          } catch {
            // Abaikan error navigasi di lingkungan jsdom
          }
        }
      }
    } catch (err: unknown) {
      if (err instanceof ApiClientError) {
        setServerError(err.message);
      } else if (err instanceof Error) {
        setServerError(err.message);
      } else {
        setServerError('Gagal melakukan proses masuk. Silakan periksa kembali data Anda.');
      }
    }
  };

  return (
    <div className="w-full space-y-5">
      {sessionExpired && (
        <div className="p-3 rounded-md bg-amber-50 border border-amber-200 text-amber-800 text-xs flex items-center gap-2">
          <AlertCircle className="h-4 w-4 shrink-0 text-amber-600" />
          <span>Sesi Anda telah kedaluwarsa. Silakan masuk kembali untuk melanjutkan.</span>
        </div>
      )}

      {serverError && (
        <div
          className="p-3.5 rounded-md bg-red-50 border border-red-200 text-status-danger text-xs flex items-start gap-2.5"
          role="alert"
        >
          <AlertCircle className="h-4 w-4 shrink-0 mt-0.5" />
          <div className="flex-1 font-medium">{serverError}</div>
        </div>
      )}

      <form onSubmit={handleSubmit(onSubmit)} method="POST" className="space-y-4" noValidate>
        {/* Input Identitas */}
        <div className="space-y-1.5">
          <label htmlFor="identifier" className="block text-xs font-semibold text-slate-700">
            Identitas Pengguna
          </label>
          <div className="relative">
            <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
              <User className="h-4 w-4" />
            </div>
            <Input
              id="identifier"
              type="text"
              autoComplete="username"
              placeholder="NISN / NIS / NIP / Username / Email"
              className="pl-9"
              hasError={!!errors.identifier}
              disabled={isSubmitting}
              {...register('identifier')}
            />
          </div>
          {errors.identifier && (
            <p className="text-[11px] font-medium text-status-danger">{errors.identifier.message}</p>
          )}
        </div>

        {/* Input Kata Sandi */}
        <div className="space-y-1.5">
          <div className="flex items-center justify-between">
            <label htmlFor="password" className="block text-xs font-semibold text-slate-700">
              Kata Sandi
            </label>
            <Link href="/lupa-password" className="text-[11px] text-brand-blue hover:underline">
              Lupa kata sandi?
            </Link>
          </div>
          <div className="relative">
            <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
              <Lock className="h-4 w-4" />
            </div>
            <Input
              id="password"
              type={showPassword ? 'text' : 'password'}
              autoComplete="current-password"
              placeholder="Masukkan kata sandi akun"
              className="pl-9 pr-10"
              hasError={!!errors.password}
              disabled={isSubmitting}
              {...register('password')}
            />
            <button
              type="button"
              onClick={() => setShowPassword(!showPassword)}
              className="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600 focus:outline-none"
              tabIndex={-1}
              aria-label={showPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'}
            >
              {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
            </button>
          </div>
          {errors.password && (
            <p className="text-[11px] font-medium text-status-danger">{errors.password.message}</p>
          )}
        </div>

        {/* Tombol Submit */}
        <div className="pt-2">
          <Button
            type="submit"
            className="w-full h-11 text-sm font-semibold"
            isLoading={isSubmitting}
            disabled={!isHydrated || isSubmitting}
          >
            Masuk ke Portal Terpadu
          </Button>
        </div>
      </form>

      <div className="pt-3 border-t border-slate-100 text-center">
        <p className="text-[11px] text-slate-500">
          Sistem otomatis mendeteksi akses Guru, Siswa, Orang Tua, dan Manajemen Sekolah.
        </p>
      </div>
    </div>
  );
}
