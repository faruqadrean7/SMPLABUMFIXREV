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
import { apiClient } from '@/lib/api/api-client';
import { Lock, AlertCircle, Eye, EyeOff, ShieldCheck, ShieldAlert, KeyRound } from 'lucide-react';

const adminLoginSchema = z.object({
  identifier: z.string().min(1, 'Username atau email administrator wajib diisi'),
  password: z.string().min(1, 'Kata sandi wajib diisi'),
});

type AdminLoginFormValues = z.infer<typeof adminLoginSchema>;

interface EnrollmentData {
  enrollment_token: string;
  secret?: string;
  otpauth_url?: string;
}

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

  // 2FA Challenge State
  const [mfaToken, setMfaToken] = useState<string | null>(null);
  const [totpCode, setTotpCode] = useState('');
  const [isVerifyingMfa, setIsVerifyingMfa] = useState(false);

  // Mandatory 2FA Enrollment State (Super Admin / Bendahara)
  const [enrollment, setEnrollment] = useState<EnrollmentData | null>(null);
  const [enrollmentCode, setEnrollmentCode] = useState('');
  const [isEnrolling, setIsEnrolling] = useState(false);
  const [recoveryCodes, setRecoveryCodes] = useState<string[] | null>(null);

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
      const result = await login({
        identifier: values.identifier,
        password: values.password,
        platform: 'web',
        isAdminLogin: true,
      });

      // 1. Mandatory 2FA Enrollment required
      if (result.mfa_enrollment_required && result.enrollment_token) {
        setIsEnrolling(true);
        try {
          const setupRes = await apiClient.post<{ message: string; data: { secret: string; otpauth_url: string } }>(
            '/auth/2fa/setup',
            { enrollment_token: result.enrollment_token }
          );
          setEnrollment({
            enrollment_token: result.enrollment_token,
            secret: setupRes.data.data.secret,
            otpauth_url: setupRes.data.data.otpauth_url,
          });
        } catch {
          setServerError('Gagal menyiapkan otentikasi dua faktor. Silakan hubungi administrator sistem.');
        } finally {
          setIsEnrolling(false);
        }
        return;
      }

      // 2. MFA Challenge required
      if (result.mfa_required && result.mfa_token) {
        setMfaToken(result.mfa_token);
        return;
      }

      // 3. Normal Admin Login success
      const destination = redirectParam && redirectParam.startsWith('/admin') ? redirectParam : '/admin';
      router.push(destination);
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

  const handleMfaSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!mfaToken || !totpCode.trim()) return;

    setServerError(null);
    setIsVerifyingMfa(true);
    try {
      const isRecovery = totpCode.includes('-');
      await login({
        mfa_token: mfaToken,
        totp_code: isRecovery ? undefined : totpCode.trim(),
        recovery_code: isRecovery ? totpCode.trim() : undefined,
        platform: 'web',
        isAdminLogin: true,
      });

      const destination = redirectParam && redirectParam.startsWith('/admin') ? redirectParam : '/admin';
      router.push(destination);
    } catch (err: unknown) {
      if (err instanceof ApiClientError) {
        setServerError(err.message);
      } else if (err instanceof Error) {
        setServerError(err.message);
      } else {
        setServerError('Kode autentikasi dua faktor atau kode pemulihan tidak valid.');
      }
    } finally {
      setIsVerifyingMfa(false);
    }
  };

  const handleEnrollmentVerify = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!enrollment || !enrollmentCode.trim()) return;

    setServerError(null);
    setIsEnrolling(true);
    try {
      const verifyRes = await apiClient.post<{ message: string; data: { confirmed: boolean; recovery_codes: string[] } }>(
        '/auth/2fa/verify',
        {
          enrollment_token: enrollment.enrollment_token,
          code: enrollmentCode.trim(),
        }
      );
      setRecoveryCodes(verifyRes.data.data.recovery_codes);
    } catch (err: unknown) {
      if (err instanceof ApiClientError) {
        setServerError(err.message);
      } else {
        setServerError('Kode verifikasi 2FA tidak valid. Pastikan waktu jam perangkat Anda sinkron.');
      }
    } finally {
      setIsEnrolling(false);
    }
  };

  // Layar Selesai Enrollment & Tampil Kode Pemulihan
  if (recoveryCodes) {
    return (
      <div className="space-y-6">
        <div className="text-center">
          <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100 mb-3">
            <ShieldCheck className="h-6 w-6 text-emerald-600" />
          </div>
          <h2 className="text-lg font-bold text-slate-900">2FA Berhasil Diaktifkan</h2>
          <p className="text-xs text-slate-600 mt-1">
            Simpan kode pemulihan darurat berikut di tempat aman. Kode ini hanya ditampilkan sekali.
          </p>
        </div>

        <div className="grid grid-cols-2 gap-2 bg-slate-50 p-3 rounded-lg border border-slate-200 font-mono text-xs text-slate-800">
          {recoveryCodes.map((code, idx) => (
            <div key={idx} className="p-1.5 bg-white border border-slate-200 rounded text-center select-all">
              {code}
            </div>
          ))}
        </div>

        <Button
          type="button"
          className="w-full bg-slate-900 hover:bg-slate-800 text-white"
          onClick={() => {
            setRecoveryCodes(null);
            setEnrollment(null);
            setMfaToken(null);
          }}
        >
          Lanjut ke Halaman Masuk
        </Button>
      </div>
    );
  }

  // Layar Mandatory 2FA Enrollment
  if (enrollment) {
    return (
      <form onSubmit={handleEnrollmentVerify} method="POST" className="space-y-6">
        <div className="text-center">
          <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-amber-100 mb-3">
            <KeyRound className="h-6 w-6 text-amber-600" />
          </div>
          <h2 className="text-lg font-bold text-slate-900">Aktivasi Wajib Otentikasi 2 Faktor</h2>
          <p className="text-xs text-slate-600 mt-1">
            Peran Administrator/Bendahara mewajibkan 2FA untuk keamanan data finansial dan identitas civitas.
          </p>
        </div>

        {serverError && (
          <div className="p-3 rounded-lg bg-red-50 border border-red-200 flex items-start gap-2.5 text-xs text-red-700">
            <AlertCircle className="h-4 w-4 shrink-0 mt-0.5 text-red-600" />
            <span>{serverError}</span>
          </div>
        )}

        <div className="p-3 bg-slate-50 border border-slate-200 rounded-lg text-xs space-y-2">
          <p className="font-semibold text-slate-800">Kunci Rahasia Base32:</p>
          <p data-testid="secret-code" className="font-mono bg-white p-2 border border-slate-200 rounded text-slate-900 select-all text-center tracking-wider text-sm font-bold">
            {enrollment.secret}
          </p>
          <p className="text-slate-500">
            Masukkan kunci di atas ke aplikasi Google Authenticator atau aplikasi TOTP kompatibel lainnya.
          </p>
        </div>

        <div className="space-y-1.5">
          <label className="text-xs font-semibold text-slate-700">Kode Verifikasi 6 Digit</label>
          <Input
            id="enrollment-code"
            data-testid="enrollment-code"
            type="text"
            inputMode="numeric"
            maxLength={6}
            placeholder="Contoh: 123456"
            value={enrollmentCode}
            onChange={(e) => setEnrollmentCode(e.target.value.replace(/\D/g, ''))}
            className="text-center tracking-widest text-lg font-mono"
            autoFocus
          />
        </div>

        <div className="flex gap-2">
          <Button
            type="button"
            variant="outline"
            className="w-1/2"
            onClick={() => setEnrollment(null)}
          >
            Batal
          </Button>
          <Button
            type="submit"
            className="w-1/2 bg-slate-900 hover:bg-slate-800 text-white"
            disabled={!isHydrated || isEnrolling || enrollmentCode.length !== 6}
          >
            {isEnrolling ? 'Memverifikasi...' : 'Aktifkan 2FA'}
          </Button>
        </div>
      </form>
    );
  }

  // Layar Tantangan MFA 2FA
  if (mfaToken) {
    return (
      <form onSubmit={handleMfaSubmit} method="POST" className="space-y-6">
        <div className="text-center">
          <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-blue-100 mb-3">
            <ShieldAlert className="h-6 w-6 text-brand-blue" />
          </div>
          <h2 className="text-lg font-bold text-slate-900">Verifikasi Dua Faktor</h2>
          <p className="text-xs text-slate-600 mt-1">
            Masukkan 6 digit kode dari aplikasi Authenticator atau gunakan kode pemulihan.
          </p>
        </div>

        {serverError && (
          <div className="p-3 rounded-lg bg-red-50 border border-red-200 flex items-start gap-2.5 text-xs text-red-700">
            <AlertCircle className="h-4 w-4 shrink-0 mt-0.5 text-red-600" />
            <span>{serverError}</span>
          </div>
        )}

        <div className="space-y-1.5">
          <label className="text-xs font-semibold text-slate-700">Kode Autentikasi / Recovery Code</label>
          <Input
            id="totp-code"
            data-testid="totp-code"
            type="text"
            placeholder="123456 atau XXXX-XXXX"
            value={totpCode}
            onChange={(e) => setTotpCode(e.target.value)}
            className="text-center tracking-widest text-lg font-mono"
            autoFocus
          />
        </div>

        <div className="flex gap-2">
          <Button
            type="button"
            variant="outline"
            className="w-1/2"
            onClick={() => {
              setMfaToken(null);
              setTotpCode('');
            }}
          >
            Batal
          </Button>
          <Button
            type="submit"
            className="w-1/2 bg-slate-900 hover:bg-slate-800 text-white"
            disabled={!isHydrated || isVerifyingMfa || !totpCode.trim()}
          >
            {isVerifyingMfa ? 'Memverifikasi...' : 'Verifikasi'}
          </Button>
        </div>
      </form>
    );
  }

  // Layar Form Login Admin Utama
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
