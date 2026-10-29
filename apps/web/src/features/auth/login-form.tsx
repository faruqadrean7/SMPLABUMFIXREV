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
import { cn } from '@/lib/utils';
import { User, Lock, AlertCircle, Eye, EyeOff, GraduationCap, BookOpen, Users, Briefcase, ShieldCheck } from 'lucide-react';

const loginSchema = z.object({
  identifier: z.string().min(1, 'Identitas (username, email, NIS, atau NIP) wajib diisi'),
  password: z.string().min(1, 'Kata sandi wajib diisi'),
});

type LoginFormValues = z.infer<typeof loginSchema>;

type PersonaType = 'siswa' | 'ortu' | 'guru' | 'staf' | null;

interface PersonaOption {
  id: 'siswa' | 'ortu' | 'guru' | 'staf';
  label: string;
  fieldLabel: string;
  placeholder: string;
  hint: string;
  icon: React.ElementType;
}

const PERSONAS: PersonaOption[] = [
  {
    id: 'siswa',
    label: 'Siswa',
    fieldLabel: 'NISN / NIS / Username',
    placeholder: 'NISN / NIS / Username',
    hint: 'Gunakan NISN atau username akun siswa Anda',
    icon: GraduationCap,
  },
  {
    id: 'ortu',
    label: 'Orang Tua',
    fieldLabel: 'Email / No. WhatsApp / Username',
    placeholder: 'Email / No. WhatsApp / Username',
    hint: 'Gunakan email atau nomor HP yang didaftarkan',
    icon: Users,
  },
  {
    id: 'guru',
    label: 'Guru',
    fieldLabel: 'NIP / Email Sekolah / Username',
    placeholder: 'NIP / Email Sekolah / Username',
    hint: 'Gunakan NIP atau email resmi sekolah pengajar',
    icon: BookOpen,
  },
  {
    id: 'staf',
    label: 'Staf',
    fieldLabel: 'NIP / Username Staf',
    placeholder: 'NIP / Username Staf',
    hint: 'Gunakan NIP atau username staf administrasi',
    icon: Briefcase,
  },
];

export function LoginForm() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const { login } = useAuth();

  const [showPassword, setShowPassword] = useState(false);
  const [serverError, setServerError] = useState<string | null>(null);
  const [selectedPersona, setSelectedPersona] = useState<PersonaType>(null);
  const [isHydrated, setIsHydrated] = useState(false);

  React.useEffect(() => {
    setIsHydrated(true);
  }, []);

  // 2FA / MFA State (GAP-SEC-02)
  const [mfaToken, setMfaToken] = useState<string | null>(null);
  const [totpCode, setTotpCode] = useState('');
  const [isVerifyingMfa, setIsVerifyingMfa] = useState(false);

  const activePersona = PERSONAS.find((p) => p.id === selectedPersona);
  const identifierLabel = activePersona ? activePersona.fieldLabel : 'Identitas Pengguna';
  const identifierPlaceholder = activePersona
    ? activePersona.placeholder
    : 'NISN / NIS / NIP / Username / Email';
  const identifierHint = activePersona?.hint;

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
        if (redirectParam && redirectParam.startsWith('/')) {
          router.push(redirectParam);
        } else {
          router.push(getDestinationPath(result.destination));
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

  const handleMfaSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!mfaToken || !totpCode.trim()) return;

    setServerError(null);
    setIsVerifyingMfa(true);
    try {
      const isRecovery = totpCode.includes('-');
      const result = await login({
        mfa_token: mfaToken,
        totp_code: isRecovery ? undefined : totpCode.trim(),
        recovery_code: isRecovery ? totpCode.trim() : undefined,
        platform: 'web',
        isAdminLogin: true,
      });

      if (result.destination) {
        if (redirectParam && redirectParam.startsWith('/')) {
          router.push(redirectParam);
        } else {
          router.push(getDestinationPath(result.destination));
        }
      }
    } catch (err: unknown) {
      if (err instanceof ApiClientError) {
        setServerError(err.message);
      } else if (err instanceof Error) {
        setServerError(err.message);
      } else {
        setServerError('Kode autentikasi dua faktor tidak valid.');
      }
    } finally {
      setIsVerifyingMfa(false);
    }
  };

  if (mfaToken) {
    return (
      <div className="w-full space-y-5 animate-in fade-in zoom-in-95 duration-200">
        <div className="text-center space-y-2">
          <div className="mx-auto w-12 h-12 bg-blue-50 text-brand-blue rounded-full flex items-center justify-center border border-blue-200">
            <ShieldCheck className="h-6 w-6" />
          </div>
          <h2 className="text-lg font-bold text-slate-900">Verifikasi Dua Faktor (2FA)</h2>
          <p className="text-xs text-slate-600 max-w-sm mx-auto">
            Akun Anda dilindungi dengan autentikasi dua faktor. Masukkan kode 6 digit dari aplikasi authenticator Anda atau gunakan kode pemulihan darurat.
          </p>
        </div>

        {serverError && (
          <div
            className="p-3.5 rounded-md bg-red-50 border border-red-200 text-status-danger text-xs flex items-start gap-2.5"
            role="alert"
          >
            <AlertCircle className="h-4 w-4 shrink-0 mt-0.5" />
            <div className="flex-1 font-medium">{serverError}</div>
          </div>
        )}

        <form onSubmit={handleMfaSubmit} method="POST" className="space-y-4">
          <div>
            <label className="block text-xs font-semibold text-slate-700 uppercase mb-1">
              Kode Autentikasi / Pemulihan
            </label>
            <Input
              type="text"
              placeholder="Contoh: 123456 atau ABCD-EFGH"
              value={totpCode}
              onChange={(e) => setTotpCode(e.target.value)}
              className="font-mono text-center tracking-widest text-base font-bold"
              required
              autoFocus
            />
          </div>

          <Button
            type="submit"
            className="w-full bg-brand-blue hover:bg-brand-blue/90"
            disabled={!isHydrated || isVerifyingMfa || !totpCode.trim()}
          >
            {isVerifyingMfa ? 'Memverifikasi...' : 'Verifikasi & Masuk'}
          </Button>

          <Button
            type="button"
            variant="outline"
            className="w-full"
            onClick={() => {
              setMfaToken(null);
              setTotpCode('');
              setServerError(null);
            }}
          >
            Kembali ke Login
          </Button>
        </form>
      </div>
    );
  }

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

      {/* Pilihan Persona Masuk (GAP-AUTH-01) */}
      <div className="space-y-2">
        <div className="flex items-center justify-between">
          <label className="block text-xs font-semibold text-slate-700">
            Pilih Akses Masuk Portal:
          </label>
          {selectedPersona && (
            <button
              type="button"
              onClick={() => setSelectedPersona(null)}
              className="text-[11px] font-medium text-brand-blue hover:underline"
            >
              Reset Pilihan
            </button>
          )}
        </div>
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-2">
          {PERSONAS.map((p) => {
            const Icon = p.icon;
            const isSelected = selectedPersona === p.id;
            return (
              <button
                key={p.id}
                type="button"
                onClick={() => setSelectedPersona(isSelected ? null : p.id)}
                className={cn(
                  'flex flex-col items-center justify-center p-2.5 rounded-lg border text-center transition-all cursor-pointer',
                  isSelected
                    ? 'border-brand-blue bg-blue-50/80 text-brand-blue font-bold shadow-2xs ring-1 ring-brand-blue'
                    : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:bg-slate-50'
                )}
                aria-pressed={isSelected}
              >
                <Icon className={cn('h-5 w-5 mb-1', isSelected ? 'text-brand-blue' : 'text-slate-500')} />
                <span className="text-xs font-semibold">{p.label}</span>
              </button>
            );
          })}
        </div>
      </div>

      <form onSubmit={handleSubmit(onSubmit)} method="POST" className="space-y-4" noValidate>
        {/* Input Identitas */}
        <div className="space-y-1.5">
          <label htmlFor="identifier" className="block text-xs font-semibold text-slate-700">
            {identifierLabel}
          </label>
          <div className="relative">
            <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
              <User className="h-4 w-4" />
            </div>
            <Input
              id="identifier"
              type="text"
              autoComplete="username"
              placeholder={identifierPlaceholder}
              className="pl-9"
              hasError={!!errors.identifier}
              disabled={isSubmitting}
              {...register('identifier')}
            />
          </div>
          {identifierHint && (
            <p className="text-[11px] text-slate-500">{identifierHint}</p>
          )}
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
