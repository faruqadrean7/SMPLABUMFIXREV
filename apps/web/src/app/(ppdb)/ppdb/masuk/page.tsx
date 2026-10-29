'use client';

import React, { useState, useEffect } from 'react';
import Link from 'next/link';
import Image from 'next/image';
import { useRouter } from 'next/navigation';
import { ppdbApi } from '@/features/ppdb/ppdb-api';
import { tokenStorage } from '@/lib/auth/token-storage';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
  Lock,
  ArrowRight,
  ShieldCheck,
  AlertCircle,
  Loader2,
  UserCheck,
  HelpCircle,
  ExternalLink,
  Eye,
  EyeOff,
  MessageCircle,
  FileText,
  CheckCircle2,
  School,
  KeyRound,
} from 'lucide-react';

export default function PPDBLoginPage() {
  const router = useRouter();

  // Form State
  const [identifier, setIdentifier] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
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
    <main id="main-content" tabIndex={-1} className="flex-1 flex flex-col focus:outline-none bg-slate-50">
      {/* =========================================================================
          1. HERO SECTION (FULL-BLEED NAVY STYLE DENGAN FOTO SISWA DI KANAN)
          ========================================================================= */}
      <section
        aria-labelledby="login-hero-heading"
        className="relative bg-[#062940] text-white overflow-hidden border-b border-white/10 w-full"
      >
        <div className="relative w-full min-h-0 lg:min-h-[380px] flex flex-col lg:flex-row lg:items-center">
          {/* Layer Foto Siswa di Kanan - Dominan 62% dengan Gradasi Halus */}
          <div className="hidden lg:block absolute inset-y-0 right-0 w-[56%] xl:w-[60%] 2xl:w-[64%] overflow-hidden pointer-events-none select-none">
            <div className="relative w-full h-full">
              <Image
                src="/images/ppdb-students.jpg"
                alt="Siswa-siswi SMP Laboratorium UM berprestasi"
                fill
                priority
                unoptimized
                sizes="(max-width: 1024px) 100vw, 60vw"
                className="object-cover object-center"
              />
              <div
                className="absolute inset-0 bg-gradient-to-r from-[#062940] via-[#062940]/80 via-10% lg:via-15% to-transparent to-35% lg:to-45%"
                aria-hidden="true"
              />
            </div>
          </div>

          {/* Konten Teks Hero (Mobile & Desktop) */}
          <div className="relative z-10 w-full lg:w-[46%] xl:w-[42%] px-4 sm:px-8 lg:px-12 xl:px-16 pt-8 sm:pt-12 pb-8 lg:py-14">
            <div className="max-w-xl space-y-3.5">
              {/* Breadcrumb Navigation */}
              <nav aria-label="Navigasi Halaman">
                <ol className="flex items-center gap-2 text-xs text-white/70">
                  <li>
                    <Link
                      href="/"
                      className="hover:text-white transition-colors focus:outline-none focus:ring-1 focus:ring-[#C89332] rounded"
                    >
                      Beranda
                    </Link>
                  </li>
                  <li aria-hidden="true" className="text-white/40">
                    &gt;
                  </li>
                  <li>
                    <Link
                      href="/ppdb"
                      className="hover:text-white transition-colors focus:outline-none focus:ring-1 focus:ring-[#C89332] rounded"
                    >
                      PPDB Online
                    </Link>
                  </li>
                  <li aria-hidden="true" className="text-white/40">
                    &gt;
                  </li>
                  <li aria-current="page" className="font-semibold text-white">
                    Masuk Pendaftar
                  </li>
                </ol>
              </nav>

              {/* Badge Pill Emas Persis Referensi */}
              <div className="inline-flex items-center gap-2 bg-[#C89332] text-white text-xs font-semibold px-4 py-1.5 rounded-full w-fit shadow-2xs">
                <span>Portal Masuk Calon Peserta Didik</span>
              </div>

              <div>
                <h1
                  id="login-hero-heading"
                  className="font-pub-heading text-3xl sm:text-4xl lg:text-[40px] font-bold text-white tracking-tight leading-[1.18] mb-3"
                >
                  Masuk Portal Pendaftar
                  <br />
                  <span className="text-white">PPDB SMP Laboratorium UM</span>
                </h1>

                <p className="text-white/85 text-xs sm:text-sm lg:text-[15px] leading-relaxed max-w-lg">
                  Silakan masuk menggunakan nomor registrasi, email, atau nomor WhatsApp yang didaftarkan untuk melanjutkan pengisian formulir, unggah berkas, dan memantau status seleksi.
                </p>
              </div>
            </div>
          </div>

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

              {/* Kartu 2: Belum Memiliki Akun? */}
              <div className="bg-white border border-gray-200/90 rounded-2xl p-6 shadow-xs space-y-3">
                <div className="flex items-center justify-between">
                  <span className="font-bold text-xs text-[#062940]">Belum Terdaftar?</span>
                  <span className="text-[11px] font-semibold text-[#1E7A46] bg-[#E8F5E9] px-2 py-0.5 rounded-full">
                    PPDB 2026/2027
                  </span>
                </div>
                <p className="text-gray-600 text-xs leading-relaxed">
                  Jika Anda belum pernah membuat akun pendaftar, silakan lakukan registrasi akun terlebih dahulu untuk mendapatkan nomor registrasi resmi.
                </p>
                <Link
                  href="/ppdb/daftar"
                  className="inline-flex items-center justify-center gap-2 w-full bg-[#062940] hover:bg-[#0A3D62] text-white font-semibold text-xs py-2.5 px-4 rounded-xl transition-colors shadow-2xs"
                >
                  <span>Daftar Akun Calon Siswa Baru</span>
                  <ArrowRight className="w-3.5 h-3.5" aria-hidden="true" />
                </Link>
              </div>

              {/* Kartu 3: Catatan Pembeda Portal Sekolah (/masuk) */}
              <div className="bg-[#EBF3F8]/80 border border-[#D0E2ED] rounded-2xl p-6 space-y-3">
                <div className="flex items-center gap-2 text-[#0A3D62]">
                  <School className="w-4 h-4 shrink-0" aria-hidden="true" />
                  <h3 className="font-bold text-xs">Bukan Calon Siswa Baru?</h3>
                </div>
                <p className="text-gray-600 text-xs leading-relaxed">
                  Bagi <strong>Siswa Aktif</strong>, <strong>Guru &amp; Staf</strong>, atau <strong>Orang Tua Murid Terdaftar</strong>, silakan masuk melalui pintu portal akademik sekolah (SISTLABUM):
                </p>
                <Link
                  href="/masuk"
                  className="inline-flex items-center gap-1.5 text-xs font-bold text-[#0A3D62] hover:text-[#C89332] hover:underline"
                >
                  <span>Masuk ke Portal Akademik Sekolah (/masuk)</span>
                  <ExternalLink className="w-3.5 h-3.5" aria-hidden="true" />
                </Link>
              </div>
            </div>

            {/* KOLOM KANAN: KARTU LOGIN PENDAFTAR (Span 7 / 12) */}
            <div className="lg:col-span-7 bg-white border border-gray-200/90 rounded-2xl p-6 sm:p-8 shadow-xs">
              {/* Header Kartu Form */}
              <div className="mb-6 pb-5 border-b border-gray-100">
                <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-[#EBF3F8] text-[#0A3D62] mb-2">
                  <KeyRound className="w-3.5 h-3.5 text-[#C89332]" aria-hidden="true" />
                  <span>Otorisasi Akses Pendaftar</span>
                </span>
                <h2 className="font-pub-heading text-xl sm:text-2xl font-bold text-[#062940]">
                  Masuk ke Dashboard Calon Siswa
                </h2>
                <p className="text-gray-500 text-xs sm:text-sm mt-1">
                  Masukkan salah satu identitas pendaftaran Anda beserta kata sandi yang telah dibuat.
                </p>
              </div>

              {/* Alert Pesan Kesalahan */}
              {errorMessage && (
                <div className="mb-6 p-4 rounded-xl bg-red-50 border border-red-200 flex items-start gap-3 text-xs sm:text-sm text-red-800 animate-in fade-in">
                  <AlertCircle className="w-5 h-5 text-red-600 shrink-0 mt-0.5" aria-hidden="true" />
                  <div className="flex-1">
                    <strong className="block font-semibold">Gagal Masuk:</strong>
                    <span className="mt-0.5 block text-xs text-red-700">{errorMessage}</span>
                  </div>
                </div>
              )}

              {/* Form Input */}
              <form onSubmit={handleSubmit} className="space-y-4">
                {/* Identifier: Email / No HP / No Registrasi */}
                <div>
                  <label htmlFor="identifier" className="block text-xs font-bold text-gray-700 mb-1">
                    Nomor Registrasi / Email / No. WhatsApp <span className="text-red-500">*</span>
                  </label>
                  <div className="relative">
                    <UserCheck className="w-4 h-4 text-gray-400 absolute left-3.5 top-1/2 -translate-y-1/2" aria-hidden="true" />
                    <input
                      id="identifier"
                      name="identifier"
                      type="text"
                      placeholder="Contoh: REG-202610-0012 atau email@anda.com"
                      value={identifier}
                      onChange={(e) => setIdentifier(e.target.value)}
                      required
                      className="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-300 text-xs sm:text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#C89332] focus:border-transparent bg-white shadow-2xs transition-all"
                    />
                  </div>
                  <p className="text-[11px] text-gray-400 mt-1">
                    Gunakan nomor registrasi resmi yang tertera pada bukti pendaftaran, alamat email, atau nomor HP.
                  </p>
                </div>

                {/* Password */}
                <div>
                  <div className="flex items-center justify-between mb-1">
                    <label htmlFor="password" className="block text-xs font-bold text-gray-700">
                      Kata Sandi <span className="text-red-500">*</span>
                    </label>
                    <a
                      href="https://wa.me/6285126088473?text=Halo%20Panitia%20PPDB,%20saya%20butuh%20bantuan%20reset%20kata%20sandi%20akun%20pendaftar"
                      target="_blank"
                      rel="noopener noreferrer"
                      className="text-xs text-[#0A3D62] hover:text-[#C89332] font-semibold transition-colors focus:outline-none"
                    >
                      Lupa kata sandi?
                    </a>
                  </div>
                  <div className="relative">
                    <Lock className="w-4 h-4 text-gray-400 absolute left-3.5 top-1/2 -translate-y-1/2" aria-hidden="true" />
                    <input
                      id="password"
                      name="password"
                      type={showPassword ? 'text' : 'password'}
                      placeholder="Masukkan kata sandi akun Anda"
                      value={password}
                      onChange={(e) => setPassword(e.target.value)}
                      required
                      className="w-full pl-10 pr-10 py-2.5 rounded-xl border border-gray-300 text-xs sm:text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#C89332] focus:border-transparent bg-white shadow-2xs transition-all"
                    />
                    <button
                      type="button"
                      onClick={() => setShowPassword(!showPassword)}
                      className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 p-1"
                      aria-label={showPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'}
                    >
                      {showPassword ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                    </button>
                  </div>
                </div>

                {/* Ketentuan Keamanan */}
                <div className="p-3.5 rounded-xl bg-[#EBF3F8]/70 border border-[#D0E2ED] text-xs text-gray-600 flex items-start gap-2.5">
                  <ShieldCheck className="w-4 h-4 text-[#0A3D62] shrink-0 mt-0.5" aria-hidden="true" />
                  <span className="leading-relaxed">
                    Koneksi terenkripsi aman SSL 256-bit melindungi seluruh berkas dan data privasi calon siswa.
                  </span>
                </div>

                {/* Tombol Submit Masuk */}
                <div className="pt-2">
                  <button
                    type="submit"
                    disabled={isSubmitting}
                    className="w-full inline-flex items-center justify-center gap-2 bg-[#C89332] hover:bg-[#B57C23] text-white font-bold text-sm py-3 px-6 rounded-xl shadow-xs hover:shadow transition-all disabled:opacity-50 focus:outline-none focus:ring-2 focus:ring-[#C89332] focus:ring-offset-2"
                  >
                    {isSubmitting ? (
                      <>
                        <Loader2 className="w-4 h-4 animate-spin" />
                        <span>Memverifikasi Akun Pendaftar...</span>
                      </>
                    ) : (
                      <>
                        <span>Masuk ke Dashboard Pendaftar</span>
                        <ArrowRight className="w-4 h-4" aria-hidden="true" />
                      </>
                    )}
                  </button>
                </div>
              </form>

              {/* Opsi Navigasi Tambahan */}
              <div className="mt-6 pt-5 border-t border-gray-100 space-y-3 text-center">
                <p className="text-xs text-gray-600">
                  Belum memiliki akun calon siswa?{' '}
                  <Link href="/ppdb/daftar" className="text-[#062940] font-bold hover:text-[#C89332] transition-colors">
                    Daftar Akun Baru Sekarang &rarr;
                  </Link>
                </p>

                <div className="flex flex-wrap items-center justify-center gap-3 text-xs text-gray-500 pt-1">
                  <Link href="/ppdb#cek-status" className="hover:text-[#062940] hover:underline">
                    Cek Pengumuman Tanpa Login
                  </Link>
                  <span>•</span>
                  <a
                    href="https://wa.me/6285126088473"
                    target="_blank"
                    rel="noopener noreferrer"
                    className="inline-flex items-center gap-1 hover:text-[#1E7A46] hover:underline"
                  >
                    <MessageCircle className="w-3.5 h-3.5" aria-hidden="true" />
                    <span>Bantuan WhatsApp Panitia</span>
                  </a>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* =========================================================================
          3. PRE-FOOTER CALL TO ACTION (KONSISTEN SITUS WEB)
          ========================================================================= */}
      <section className="relative overflow-hidden bg-[#062940] text-white py-12 sm:py-14 w-full border-t border-white/10 mt-auto">
        {/* Foto Gedung Sekolah Faded Overlay di Kiri */}
        <div className="absolute inset-y-0 left-0 w-2/5 overflow-hidden pointer-events-none opacity-25">
          <Image
            src="/images/school-gate.jpg"
            alt=""
            fill
            unoptimized
            sizes="40vw"
            className="object-cover object-left"
          />
          <div className="absolute inset-0 bg-gradient-to-r from-transparent via-[#062940]/80 to-[#062940]" aria-hidden="true" />
        </div>

        <div className="relative z-10 w-full px-4 sm:px-8 lg:px-12 xl:px-16">
          <div className="flex flex-col lg:flex-row items-center justify-between gap-6 sm:gap-8">
            <div className="text-center lg:text-left border-l-0 lg:border-l-2 lg:border-white/20 lg:pl-5">
              <h2 className="font-pub-heading text-xl sm:text-2xl font-bold text-white tracking-tight">
                Punya Pertanyaan Seputar Pendaftaran?
              </h2>
              <p className="text-white/80 text-xs sm:text-sm mt-1.5 max-w-xl leading-relaxed">
                Tim panitia admisi SMP Laboratorium UM siap mendampingi Anda di setiap tahapan.
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
      </section>
    </main>
  );
}
