'use client';

import React, { useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import * as z from 'zod';
import { useAuth } from '@/providers/auth-provider';
import { useRouter, useSearchParams } from 'next/navigation';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ApiClientError } from '@/lib/api/errors';
import { Lock, AlertCircle, Eye, EyeOff } from 'lucide-react';

const adminLoginSchema = z.object({
  identifier: z.string().min(1, 'Username atau email administrator wajib diisi'),
  password: z.string().min(1, 'Kata sandi wajib diisi'),
});

type AdminLoginFormValues = z.infer<typeof adminLoginSchema>;

export function AdminLoginForm() {
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

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<AdminLoginFormValues>({
    resolver: zodResolver(adminLoginSchema),
    defaultValues: {
      identifier: '',
      password: '',
    },
  });

  const onSubmit = async (values: AdminLoginFormValues) => {
    setServerError(null);
    try {
      await login({
        identifier: values.identifier,
        password: values.password,
        platform: 'web',
        isAdminLogin: true,
      });

      // Normal Admin Login success: langsung arahkan ke admin shell dengan cookie sesi admin
      const destination = redirectParam && redirectParam.startsWith('/admin') ? redirectParam : '/admin';
      router.push(destination);
      if (typeof window !== 'undefined') {
        document.cookie = 'sistlabum_admin_session=1; path=/; SameSite=Lax';
        try {
          window.location.href = destination;
        } catch {
          // Abaikan error navigasi di lingkungan pengujian jsdom
        }
      }
    } catch (err: unknown) {
      if (err instanceof ApiClientError) {
        setServerError(err.message);
      } else if (err instanceof Error) {
        setServerError(err.message);
      } else {
        setServerError('Kredensial yang diberikan tidak cocok dengan data kami.');
      }
    }
  };

  // Layar Form Login Admin Utama (Langsung tanpa 2FA)
  return (
    <form onSubmit={handleSubmit(onSubmit)} method="POST" className="space-y-5">
      {serverError && (
        <div className="p-3 rounded-lg bg-red-50 border border-red-200 flex items-start gap-2.5 text-xs text-red-700">
          <AlertCircle className="h-4 w-4 shrink-0 mt-0.5 text-red-600" />
          <span>{serverError}</span>
        </div>
      )}

      <div className="space-y-1.5">
        <label className="text-xs font-semibold text-slate-700">Username atau Email Administrator</label>
        <div className="relative">
          <Input
            id="identifier"
            {...register('identifier')}
            type="text"
            placeholder="admin / bendahara@labschool.sch.id"
            className="h-10 text-sm pl-9"
            disabled={isSubmitting}
          />
          <Lock className="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
        </div>
        {errors.identifier && (
          <p className="text-xs text-red-600">{errors.identifier.message}</p>
        )}
      </div>

      <div className="space-y-1.5">
        <label className="text-xs font-semibold text-slate-700">Kata Sandi</label>
        <div className="relative">
          <Input
            id="password"
            {...register('password')}
            type={showPassword ? 'text' : 'password'}
            placeholder="••••••••••••"
            className="h-10 text-sm pl-9 pr-9"
            disabled={isSubmitting}
          />
          <Lock className="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
          <button
            type="button"
            onClick={() => setShowPassword(!showPassword)}
            className="absolute right-3 top-2.5 text-slate-400 hover:text-slate-600"
          >
            {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
          </button>
        </div>
        {errors.password && (
          <p className="text-xs text-red-600">{errors.password.message}</p>
        )}
      </div>

      <Button
        type="submit"
        className="w-full h-10 bg-slate-900 hover:bg-slate-800 text-white font-medium text-sm"
        disabled={!isHydrated || isSubmitting}
      >
        {isSubmitting ? 'Memproses Masuk...' : 'Masuk Back-Office'}
      </Button>

      <div className="text-center pt-2">
        <p className="text-[11px] text-slate-400">
          Akses terbatas. Seluruh aktivitas login diawasi dan dicatat pada audit log.
        </p>
      </div>
    </form>
  );
}
