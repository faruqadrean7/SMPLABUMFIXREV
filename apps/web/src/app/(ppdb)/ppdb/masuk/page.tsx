'use client';

import React, { useState, useEffect } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { ppdbApi } from '@/features/ppdb/ppdb-api';
import { tokenStorage } from '@/lib/auth/token-storage';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
  School,
  Lock,
  ArrowRight,
  ShieldCheck,
  AlertCircle,
  Loader2,
  UserCheck,
  HelpCircle,
  ExternalLink,
} from 'lucide-react';

export default function PPDBLoginPage() {
  const router = useRouter();

  // Form State
  const [identifier, setIdentifier] = useState('');
  const [password, setPassword] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [isHydrated, setIsHydrated] = useState(false);

  useEffect(() => {
    setIsHydrated(true);
  }, []);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setErrorMessage(null);

    if (!identifier.trim()) {
      setErrorMessage('Nomor registrasi, email, atau nomor HP wajib diisi.');
      return;
    }
    if (!password) {
      setErrorMessage('Kata sandi wajib diisi.');
      return;
    }

    setIsSubmitting(true);
    try {
      const res = await ppdbApi.login({
        identifier: identifier.trim(),
        password,
      });

      // Simpan token melalui tokenStorage terpusat (GAP-PPDB-01)
      if (res.accessToken && typeof window !== 'undefined') {
        tokenStorage.setTokens(res.accessToken, res.refreshToken);
        localStorage.removeItem('auth_token');
        localStorage.setItem('user_role', 'guest');
      }

      // Berhasil -> redirect ke dashboard pendaftar
      router.push('/ppdb/dashboard');
    } catch (err: any) {
      const code = err.response?.data?.error?.code || err.response?.data?.code;
      const msg = err.response?.data?.error?.message || err.response?.data?.message || err.message;

      if (code === 'INVALID_CREDENTIALS' || err.response?.status === 401) {
        setErrorMessage('Kredensial tidak sesuai. Periksa kembali email/nomor registrasi dan kata sandi Anda.');
      } else if (code === 'ACCOUNT_INACTIVE' || err.response?.status === 403) {
        setErrorMessage('Akun pendaftar ini belum aktif atau belum diverifikasi OTP. Silakan lakukan verifikasi.');
      } else if (code === 'RATE_LIMITED' || err.response?.status === 429) {
        setErrorMessage('Terlalu banyak percobaan masuk yang gagal. Silakan tunggu beberapa saat sebelum mencoba kembali.');
      } else {
        setErrorMessage(msg || 'Gagal masuk ke akun pendaftar. Periksa koneksi atau coba beberapa saat lagi.');
      }
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <main className="min-h-screen bg-gradient-to-b from-slate-50 to-blue-50/30 flex flex-col justify-center py-12 px-4 sm:px-6 lg:px-8">
      <div className="max-w-md w-full mx-auto">
        {/* Header Branding */}
        <div className="text-center mb-8">
          <Link
            href="/ppdb"
            className="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-blue-100/80 text-brand-blue text-xs font-semibold mb-4 hover:bg-blue-200/80 transition-colors"
          >
            <School className="h-4 w-4" />
            Portal SPMB / PPDB Online
          </Link>
          <h1 className="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
            Masuk Akun Pendaftar
          </h1>
          <p className="mt-2 text-xs sm:text-sm text-slate-600">
            Akses dashboard pendaftar, lengkapi formulir bertahap, pantau verifikasi berkas, dan cetak kartu tanda peserta.
          </p>
        </div>

        {/* Card Login */}
        <div className="bg-white rounded-2xl shadow-elevated border border-slate-200 p-6 sm:p-8">
          {errorMessage && (
            <div className="mb-5 p-3.5 rounded-xl bg-red-50 border border-red-200 flex items-start gap-2.5 text-xs text-red-800">
              <AlertCircle className="h-4 w-4 text-red-600 shrink-0 mt-0.5" />
              <div className="flex-1">
                <span className="font-semibold block">Gagal Masuk:</span>
                <span>{errorMessage}</span>
              </div>
            </div>
          )}

          <form onSubmit={handleSubmit} method="POST" className="space-y-4">
            {/* Identifier: Email / No HP / No Registrasi */}
            <div>
              <label className="block text-xs font-semibold text-slate-700 mb-1">
                Email / No. WhatsApp / No. Registrasi <span className="text-red-500">*</span>
              </label>
              <div className="relative">
                <UserCheck className="absolute left-3.5 top-3 h-4 w-4 text-slate-400" />
                <Input
                  type="text"
                  placeholder="Contoh: REG-2026-0001 atau email Anda"
                  value={identifier}
                  onChange={(e) => setIdentifier(e.target.value)}
                  className="pl-10 text-sm"
                  required
                />
              </div>
              <p className="text-[11px] text-slate-500 mt-1">
                Bisa menggunakan email pendaftaran, nomor HP, atau nomor registrasi resmi.
              </p>
            </div>

            {/* Password */}
            <div>
              <div className="flex items-center justify-between mb-1">
                <label className="block text-xs font-semibold text-slate-700">
                  Kata Sandi <span className="text-red-500">*</span>
                </label>
              </div>
              <div className="relative">
                <Lock className="absolute left-3.5 top-3 h-4 w-4 text-slate-400" />
                <Input
                  type="password"
                  placeholder="Masukkan kata sandi Anda"
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  className="pl-10 text-sm"
                  required
                />
              </div>
            </div>

            {/* Tombol Masuk */}
            <Button
              type="submit"
              disabled={!isHydrated || isSubmitting}
              className="w-full h-11 bg-brand-blue hover:bg-blue-700 text-white font-semibold rounded-xl text-sm shadow-md flex items-center justify-center gap-2 mt-4"
            >
              {isSubmitting ? (
                <>
                  <Loader2 className="h-4 w-4 animate-spin" />
                  Memverifikasi Akun...
                </>
              ) : (
                <>
                  Masuk ke Dashboard Pendaftar
                  <ArrowRight className="h-4 w-4" />
                </>
              )}
            </Button>
          </form>

          {/* Opsi Tambahan */}
          <div className="mt-6 pt-5 border-t border-slate-100 space-y-3 text-center">
            <p className="text-xs text-slate-600">
              Belum mendaftarkan akun calon siswa?{' '}
              <Link href="/ppdb/daftar" className="text-brand-blue font-semibold hover:underline">
                Daftar Akun Baru Sekarang
              </Link>
            </p>

            <div className="flex items-center justify-center gap-4 text-xs text-slate-500 pt-1">
              <Link href="/ppdb#cek-pengumuman" className="hover:text-brand-blue hover:underline">
                Cek Hasil Tanpa Login
              </Link>
              <span>•</span>
              <Link href="/masuk" className="inline-flex items-center gap-1 hover:text-brand-blue hover:underline">
                Login Civitas Sekolah
                <ExternalLink className="h-3 w-3" />
              </Link>
            </div>
          </div>
        </div>

        {/* Keamanan & Bantuan */}
        <div className="mt-6 flex items-center justify-between text-xs text-slate-500 px-2">
          <div className="flex items-center gap-1.5">
            <ShieldCheck className="h-4 w-4 text-brand-teal" />
            <span>Koneksi Aman Terenkripsi</span>
          </div>
          <Link href="/kontak" className="inline-flex items-center gap-1 text-slate-600 hover:underline">
            <HelpCircle className="h-3.5 w-3.5 text-slate-400" />
            Bantuan SPMB
          </Link>
        </div>
      </div>
    </main>
  );
}
