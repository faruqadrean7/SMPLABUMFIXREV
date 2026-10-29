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
  User,
  Mail,
  Phone,
  Lock,
  IdCard,
  ArrowRight,
  ShieldCheck,
  CheckCircle2,
  AlertCircle,
  Loader2,
  KeyRound,
  RotateCw,
  Calendar,
  Award,
  Clock,
  Eye,
  EyeOff,
  HelpCircle,
  X,
  MessageCircle,
} from 'lucide-react';

export default function RegisterApplicantPage() {
  const router = useRouter();

  // Form State
  const [formData, setFormData] = useState({
    full_name: '',
    email: '',
    phone: '',
    nik: '',
    password: '',
    password_confirmation: '',
  });

  // Password Visibility Toggles
  const [showPassword, setShowPassword] = useState(false);
  const [showConfirmPassword, setShowConfirmPassword] = useState(false);

  // State Feedback & Validation
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [isHydrated, setIsHydrated] = useState(false);

  useEffect(() => {
    setIsHydrated(true);
  }, []);
  const [nikCheckStatus, setNikCheckStatus] = useState<{ checked: boolean; available?: boolean; message?: string }>({
    checked: false,
  });

  // OTP Modal State
  const [showOtpModal, setShowOtpModal] = useState(false);
  const [otpCode, setOtpCode] = useState('');
  const [isVerifyingOtp, setIsVerifyingOtp] = useState(false);
  const [otpError, setOtpError] = useState<string | null>(null);
  const [otpSuccessMessage, setOtpSuccessMessage] = useState<string | null>(null);
  const [resendCooldown, setResendCooldown] = useState(0);

  // Countdown timer for resend OTP
  useEffect(() => {
    if (resendCooldown <= 0) return;
    const timer = setInterval(() => {
      setResendCooldown((prev) => (prev <= 1 ? 0 : prev - 1));
    }, 1000);
    return () => clearInterval(timer);
  }, [resendCooldown]);

  // Handle Input Changes
  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const { name, value } = e.target;
    setFormData((prev) => ({ ...prev, [name]: value }));
    setErrorMessage(null);
    if (name === 'nik') {
      setNikCheckStatus({ checked: false });
    }
  };

  // Cek Ketersediaan NIK
  const handleCheckNik = async () => {
    if (!formData.nik || formData.nik.length < 16) {
      setNikCheckStatus({
        checked: true,
        available: false,
        message: 'NIK harus terdiri dari 16 digit angka.',
      });
      return;
    }

    try {
      const res = await ppdbApi.checkNik(formData.nik);
      if (res.is_available) {
        setNikCheckStatus({
          checked: true,
          available: true,
          message: 'NIK valid dan belum terdaftar dalam sistem.',
        });
      } else {
        setNikCheckStatus({
          checked: true,
          available: false,
          message: `NIK ini sudah terdaftar (${res.existing_registration_no || 'Pendaftaran Aktif'}).`,
        });
      }
    } catch {
      setNikCheckStatus({
        checked: true,
        available: false,
        message: 'Gagal memverifikasi NIK ke server. Coba lagi.',
      });
    }
  };

  // Submit Registrasi
  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setErrorMessage(null);

    // Validasi Sisi Klien
    if (!formData.full_name.trim()) {
      setErrorMessage('Nama lengkap calon siswa wajib diisi.');
      return;
    }
    if (!formData.email.trim() || !formData.email.includes('@')) {
      setErrorMessage('Alamat email aktif wajib diisi.');
      return;
    }
    if (!formData.phone.trim() || formData.phone.length < 10) {
      setErrorMessage('Nomor WhatsApp/HP aktif wajib minimal 10 digit.');
      return;
    }
    if (formData.password.length < 8) {
      setErrorMessage('Kata sandi minimal 8 karakter.');
      return;
    }
    if (formData.password !== formData.password_confirmation) {
      setErrorMessage('Konfirmasi kata sandi tidak cocok.');
      return;
    }

    setIsSubmitting(true);
    try {
      const res = await ppdbApi.register({
        full_name: formData.full_name.trim(),
        email: formData.email.trim().toLowerCase(),
        phone: formData.phone.trim(),
        nik: formData.nik.trim() || undefined,
        password: formData.password,
        password_confirmation: formData.password_confirmation,
      });

      // Tampilkan Modal OTP
      setShowOtpModal(true);
      setResendCooldown(60);
      if (res.otp) {
        setOtpCode(res.otp); // Lingkungan testing/local menampilkan kode langsung
      }
    } catch (err: any) {
      const msg =
        err.response?.data?.message ||
        err.response?.data?.error?.message ||
        err.message ||
        'Gagal mendaftar. Silakan periksa kembali isian Anda.';
      setErrorMessage(msg);
    } finally {
      setIsSubmitting(false);
    }
  };

  // Verifikasi OTP
  const handleVerifyOtp = async (e: React.FormEvent) => {
    e.preventDefault();
    setOtpError(null);

    if (!otpCode || otpCode.trim().length !== 6) {
      setOtpError('Masukkan 6 digit kode OTP yang dikirimkan.');
      return;
    }

    setIsVerifyingOtp(true);
    try {
      const res = await ppdbApi.verifyOtp({
        email: formData.email.trim().toLowerCase(),
        otp: otpCode.trim(),
      });

      // Simpan token melalui tokenStorage terpusat (GAP-PPDB-01)
      if (res.accessToken && typeof window !== 'undefined') {
        tokenStorage.setTokens(res.accessToken);
        localStorage.removeItem('auth_token');
        localStorage.setItem('user_role', 'guest');
      }

      setOtpSuccessMessage('Verifikasi berhasil! Mengalihkan ke dashboard pendaftar...');
      setTimeout(() => {
        router.push('/ppdb/dashboard');
      }, 1200);
    } catch (err: any) {
      const msg =
        err.response?.data?.message ||
        err.response?.data?.error?.message ||
        'Kode OTP tidak valid atau sudah kedaluwarsa.';
      setOtpError(msg);
    } finally {
      setIsVerifyingOtp(false);
    }
  };

  // Kirim Ulang OTP
  const handleResendOtp = async () => {
    if (resendCooldown > 0) return;
    try {
      const res = await ppdbApi.resendOtp(formData.email.trim().toLowerCase());
      if (res.otp) {
        setOtpCode(res.otp);
      }
      setResendCooldown(60);
    } catch (err: any) {
      setOtpError(err.response?.data?.message || 'Gagal mengirim ulang OTP.');
    }
  };

  return (
    <main id="main-content" tabIndex={-1} className="flex-1 flex flex-col focus:outline-none bg-slate-50">
      {/* =========================================================================
          1. HERO SECTION (FULL-BLEED NAVY STYLE DENGAN FOTO SISWA DI KANAN)
          ========================================================================= */}
      <section
        aria-labelledby="register-hero-heading"
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
                    Pendaftaran Akun
                  </li>
                </ol>
              </nav>

              {/* Badge Pill Emas Persis Referensi */}
              <div className="inline-flex items-center gap-2 bg-[#C89332] text-white text-xs font-semibold px-4 py-1.5 rounded-full w-fit shadow-2xs">
                <span>Langkah 01 • Registrasi Akun Calon Murid</span>
              </div>

              <div>
                <h1
                  id="register-hero-heading"
                  className="font-pub-heading text-3xl sm:text-4xl lg:text-[40px] font-bold text-white tracking-tight leading-[1.18] mb-3"
                >
                  Pendaftaran Akun Baru
                  <br />
                  Calon Peserta Didik
                </h1>

                <p className="text-white/85 text-xs sm:text-sm lg:text-[15px] leading-relaxed max-w-lg">
                  Buat akun resmi untuk mengisi formulir pendaftaran, memilih jalur seleksi, mengunggah berkas, dan memantau hasil kelulusan PPDB SMP Laboratorium UM 2026/2027.
                </p>
              </div>
            </div>
          </div>

          {/* Mobile / Tablet Image */}
          <div className="lg:hidden relative aspect-[16/9] w-full overflow-hidden bg-slate-900">
            <Image
              src="/images/ppdb-students.jpg"
              alt="Siswa-siswi SMP Laboratorium UM berprestasi"
              fill
              priority
              unoptimized
              sizes="100vw"
              className="object-cover object-center"
            />
            <div
              className="absolute inset-x-0 top-0 h-12 bg-gradient-to-b from-[#062940] via-[#062940]/40 to-transparent"
              aria-hidden="true"
            />
          </div>
        </div>
      </section>

      {/* =========================================================================
          2. STEPPER PROGRES ALUR (KONSISTEN DENGAN /PPDB)
          ========================================================================= */}
      <section aria-label="Tahapan Pendaftaran PPDB" className="bg-white border-b border-gray-200/80 py-4 sm:py-5 w-full">
        <div className="w-full px-4 sm:px-8 lg:px-12 xl:px-16">
          <div className="flex flex-wrap items-center justify-between gap-4">
            <div className="flex items-center gap-3">
              <span className="w-8 h-8 rounded-full bg-[#062940] text-white text-xs font-bold font-mono flex items-center justify-center">
                01
              </span>
              <div>
                <p className="font-semibold">Pendaftaran Belum Dapat Dilanjutkan</p>
                <p className="mt-0.5 text-xs text-red-700">{errorMessage}</p>
              </div>
            </div>
          )}

          <form onSubmit={handleSubmit} method="POST" className="space-y-4">
            {/* Nama Lengkap */}
            <div>
              <label className="block text-xs font-semibold text-slate-700 mb-1">
                Nama Lengkap Calon Siswa <span className="text-red-500">*</span>
              </label>
              <div className="relative">
                <User className="absolute left-3.5 top-3 h-4 w-4 text-slate-400" />
                <Input
                  name="full_name"
                  type="text"
                  placeholder="Contoh: Muhammad Farhan Al-Fatih"
                  value={formData.full_name}
                  onChange={handleChange}
                  className="pl-10"
                  required
                />
              </div>
              <p className="text-[11px] text-slate-500 mt-1">Sesuai nama pada Akta Kelahiran atau Ijazah/Rapor.</p>
            </div>

            {/* Email Aktif */}
            <div>
              <label className="block text-xs font-semibold text-slate-700 mb-1">
                Alamat Email Aktif <span className="text-red-500">*</span>
              </label>
              <div className="relative">
                <Mail className="absolute left-3.5 top-3 h-4 w-4 text-slate-400" />
                <Input
                  name="email"
                  type="email"
                  placeholder="nama.anda@gmail.com"
                  value={formData.email}
                  onChange={handleChange}
                  className="pl-10"
                  required
                />
              </div>
              <p className="text-[11px] text-slate-500 mt-1">Digunakan untuk menerima notifikasi status kelulusan & OTP.</p>
            </div>

            {/* Nomor HP / WhatsApp */}
            <div>
              <label className="block text-xs font-semibold text-slate-700 mb-1">
                Nomor WhatsApp / HP Aktif <span className="text-red-500">*</span>
              </label>
              <div className="relative">
                <Phone className="absolute left-3.5 top-3 h-4 w-4 text-slate-400" />
                <Input
                  name="phone"
                  type="tel"
                  placeholder="081234567890"
                  value={formData.phone}
                  onChange={handleChange}
                  className="pl-10"
                  required
                />
              </div>
              <p className="text-[11px] text-slate-500 mt-1">Bisa nomor siswa atau nomor wali murid.</p>
            </div>

            {/* NIK (Nomor Induk Kependudukan) */}
            <div>
              <div className="flex items-center justify-between mb-1">
                <label className="block text-xs font-semibold text-slate-700">
                  NIK Calon Siswa (16 Digit)
                </label>
                <button
                  type="button"
                  onClick={handleCheckNik}
                  className="text-xs text-brand-blue hover:underline font-medium"
                >
                  Cek Ketersediaan NIK
                </button>
              </div>
              <div className="relative">
                <IdCard className="absolute left-3.5 top-3 h-4 w-4 text-slate-400" />
                <Input
                  name="nik"
                  type="text"
                  maxLength={16}
                  placeholder="357901xxxxxxxxxx"
                  value={formData.nik}
                  onChange={handleChange}
                  className="pl-10"
                />
              </div>
              {nikCheckStatus.checked && (
                <div
                  className={`mt-1.5 flex items-center gap-1.5 text-xs ${
                    nikCheckStatus.available ? 'text-emerald-700' : 'text-red-600'
                  }`}
                >
                  {nikCheckStatus.available ? (
                    <CheckCircle2 className="h-3.5 w-3.5 text-emerald-600" />
                  ) : (
                    <AlertCircle className="h-3.5 w-3.5 text-red-500" />
                  )}
                  <span>{nikCheckStatus.message}</span>
                </div>
              )}
            </div>

            {/* Password */}
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2">
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">
                  Kata Sandi Baru <span className="text-red-500">*</span>
                </label>
                <div className="relative">
                  <Lock className="absolute left-3.5 top-3 h-4 w-4 text-slate-400" />
                  <Input
                    name="password"
                    type="password"
                    placeholder="Min. 8 karakter"
                    value={formData.password}
                    onChange={handleChange}
                    className="pl-10"
                    required
                  />
                </div>
              </div>
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">
                  Ulangi Kata Sandi <span className="text-red-500">*</span>
                </label>
                <div className="relative">
                  <Lock className="absolute left-3.5 top-3 h-4 w-4 text-slate-400" />
                  <Input
                    name="password_confirmation"
                    type="password"
                    placeholder="Ketik ulang sandi"
                    value={formData.password_confirmation}
                    onChange={handleChange}
                    className="pl-10"
                    required
                  />
                </div>
              </div>
            </div>

            <div className="hidden md:flex items-center gap-6 text-xs text-gray-400">
              <span className="flex items-center gap-2">
                <span className="w-6 h-6 rounded-full bg-slate-100 text-gray-500 font-mono text-[11px] flex items-center justify-center">02</span>
                <span>Isi Biodata 5 Langkah</span>
              </span>
              <span className="flex items-center gap-2">
                <span className="w-6 h-6 rounded-full bg-slate-100 text-gray-500 font-mono text-[11px] flex items-center justify-center">03</span>
                <span>Upload Berkas &amp; Verifikasi</span>
              </span>
              <span className="flex items-center gap-2">
                <span className="w-6 h-6 rounded-full bg-slate-100 text-gray-500 font-mono text-[11px] flex items-center justify-center">04</span>
                <span>Pengumuman &amp; Daftar Ulang</span>
              </span>
            </div>

            {/* Tombol Buat Akun */}
            <Button
              type="submit"
              disabled={!isHydrated || isSubmitting}
              className="w-full h-11 bg-brand-blue hover:bg-blue-700 text-white font-semibold rounded-xl text-sm shadow-md flex items-center justify-center gap-2 mt-2"
            >
              <span>Sudah punya akun? Masuk</span>
              <ArrowRight className="w-3.5 h-3.5" aria-hidden="true" />
            </Link>
          </div>
        </div>
      </section>

      {/* =========================================================================
          3. FORM PENDAFTARAN & KOTAK PANDUAN (2 KOLOM RESPONSIVE)
          ========================================================================= */}
      <section aria-label="Formulir Pendaftaran Calon Siswa" className="py-12 sm:py-16 w-full">
        <div className="w-full px-4 sm:px-8 lg:px-12 xl:px-16">
          <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
            {/* KOLOM KIRI: KARTU PANDUAN & BANTUAN (Span 5 / 12) */}
            <div className="lg:col-span-5 space-y-6">
              {/* Kartu 1: Petunjuk Penting */}
              <div className="bg-white border border-gray-200/90 rounded-2xl p-6 shadow-xs space-y-4">
                <div className="flex items-center gap-3 pb-3 border-b border-gray-100">
                  <div className="w-10 h-10 rounded-xl bg-[#EBF3F8] text-[#0A3D62] flex items-center justify-center shrink-0">
                    <HelpCircle className="w-5 h-5" aria-hidden="true" />
                  </div>
                  <div>
                    <h2 className="font-pub-heading font-bold text-base text-[#062940]">
                      Petunjuk Pendaftaran Akun
                    </h2>
                    <p className="text-gray-500 text-xs">Hal-hal yang perlu disiapkan sebelum mendaftar</p>
                  </div>
                </div>

                <div className="space-y-3.5 text-xs text-gray-600">
                  <div className="flex items-start gap-2.5">
                    <CheckCircle2 className="w-4 h-4 text-[#1E7A46] shrink-0 mt-0.5" aria-hidden="true" />
                    <div>
                      <strong className="text-gray-800 block">Nama Sesuai Dokumen Resmi</strong>
                      <span>Gunakan nama lengkap sesuai Akta Kelahiran atau Rapor SD/MI tanpa singkatan.</span>
                    </div>
                  </div>

                  <div className="flex items-start gap-2.5">
                    <CheckCircle2 className="w-4 h-4 text-[#1E7A46] shrink-0 mt-0.5" aria-hidden="true" />
                    <div>
                      <strong className="text-gray-800 block">Email &amp; WhatsApp Aktif</strong>
                      <span>Kode verifikasi OTP serta pengumuman seleksi akan dikirimkan otomatis ke kontak ini.</span>
                    </div>
                  </div>

                  <div className="flex items-start gap-2.5">
                    <CheckCircle2 className="w-4 h-4 text-[#1E7A46] shrink-0 mt-0.5" aria-hidden="true" />
                    <div>
                      <strong className="text-gray-800 block">Satu NIK untuk Satu Pendaftaran</strong>
                      <span>Sistem memverifikasi NIK untuk memastikan setiap calon murid memiliki satu pendaftaran resmi.</span>
                    </div>
                  </div>
                </div>
              </div>

              {/* Kartu 2: Info Gelombang Aktif */}
              <div className="bg-white border border-gray-200/90 rounded-2xl p-6 shadow-xs space-y-4">
                <div className="flex items-center justify-between pb-3 border-b border-gray-100">
                  <div className="flex items-center gap-2.5">
                    <Calendar className="w-4 h-4 text-[#C89332]" aria-hidden="true" />
                    <span className="font-bold text-xs text-[#062940]">Gelombang 1 Reguler &amp; Prestasi</span>
                  </div>
                  <span className="text-[11px] font-semibold text-[#1E7A46] bg-[#E8F5E9] px-2 py-0.5 rounded-full">
                    Aktif
                  </span>
                </div>

                <div className="space-y-2 text-xs">
                  <div className="flex items-center justify-between">
                    <span className="text-gray-500">Masa Pendaftaran:</span>
                    <strong className="text-gray-800">01 – 30 Mei 2026</strong>
                  </div>
                  <div className="flex items-center justify-between">
                    <span className="text-gray-500">Pengumuman:</span>
                    <strong className="text-gray-800">05 Juni 2026</strong>
                  </div>
                  <div className="flex items-center justify-between">
                    <span className="text-gray-500">Daftar Ulang:</span>
                    <strong className="text-gray-800">08 – 12 Juni 2026</strong>
                  </div>
                </div>
              </div>

              {/* Kartu 3: Hotline Panitia PPDB */}
              <div className="bg-[#EBF3F8]/80 border border-[#D0E2ED] rounded-2xl p-6 space-y-3">
                <div className="flex items-center gap-2.5">
                  <Phone className="w-4 h-4 text-[#0A3D62]" aria-hidden="true" />
                  <h3 className="font-bold text-xs text-[#0A3D62]">Butuh Bantuan Pendaftaran?</h3>
                </div>
                <p className="text-gray-600 text-xs leading-relaxed">
                  Jika mengalami kendala teknis pembuatan akun atau verifikasi NIK, silakan hubungi tim admisi kami via WhatsApp:
                </p>
                <a
                  href="https://wa.me/6285126088473"
                  target="_blank"
                  rel="noopener noreferrer"
                  className="inline-flex items-center justify-center gap-2 w-full bg-[#1E7A46] hover:bg-[#166037] text-white font-semibold text-xs py-2.5 px-4 rounded-xl shadow-2xs transition-colors"
                >
                  <MessageCircle className="w-4 h-4" aria-hidden="true" />
                  <span>Chat WhatsApp Panitia (0851-2608-8473)</span>
                </a>
              </div>
            </div>

            {/* KOLOM KANAN: KARTU FORMULIR PENDAFTARAN (Span 7 / 12) */}
            <div className="lg:col-span-7 bg-white border border-gray-200/90 rounded-2xl p-6 sm:p-8 shadow-xs">
              {/* Header Kartu Form */}
              <div className="mb-6 pb-5 border-b border-gray-100">
                <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-[#EBF3F8] text-[#0A3D62] mb-2">
                  <Award className="w-3.5 h-3.5 text-[#C89332]" aria-hidden="true" />
                  <span>Formulir Registrasi Mandiri</span>
                </span>
                <h2 className="font-pub-heading text-xl sm:text-2xl font-bold text-[#062940]">
                  Lengkapi Data Akun Calon Murid
                </h2>
                <p className="text-gray-500 text-xs sm:text-sm mt-1">
                  Seluruh isian bertanda bintang (<span className="text-red-500 font-bold">*</span>) wajib diisi dengan benar.
                </p>
              </div>

              {/* Alert Pesan Kesalahan */}
              {errorMessage && (
                <div className="mb-6 p-4 rounded-xl bg-red-50 border border-red-200 flex items-start gap-3 text-xs sm:text-sm text-red-800 animate-in fade-in">
                  <AlertCircle className="w-5 h-5 text-red-600 shrink-0 mt-0.5" aria-hidden="true" />
                  <div>
                    <strong className="block font-semibold">Pendaftaran Belum Dapat Dilanjutkan</strong>
                    <span className="mt-0.5 block text-xs text-red-700">{errorMessage}</span>
                  </div>
                </div>
              )}

              {/* Form Input */}
              <form onSubmit={handleSubmit} className="space-y-4">
                {/* 1. Nama Lengkap Calon Siswa */}
                <div>
                  <label htmlFor="full_name" className="block text-xs font-bold text-gray-700 mb-1">
                    Nama Lengkap Calon Siswa <span className="text-red-500">*</span>
                  </label>
                  <div className="relative">
                    <User className="w-4 h-4 text-gray-400 absolute left-3.5 top-1/2 -translate-y-1/2" aria-hidden="true" />
                    <input
                      id="full_name"
                      name="full_name"
                      type="text"
                      placeholder="Contoh: Muhammad Farhan Al-Fatih"
                      value={formData.full_name}
                      onChange={handleChange}
                      required
                      className="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-300 text-xs sm:text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#C89332] focus:border-transparent bg-white shadow-2xs transition-all"
                    />
                  </div>
                  <p className="text-[11px] text-gray-400 mt-1">Sesuai nama pada Akta Kelahiran atau Ijazah/Rapor SD.</p>
                </div>

                {/* 2. Alamat Email Aktif */}
                <div>
                  <label htmlFor="email" className="block text-xs font-bold text-gray-700 mb-1">
                    Alamat Email Aktif <span className="text-red-500">*</span>
                  </label>
                  <div className="relative">
                    <Mail className="w-4 h-4 text-gray-400 absolute left-3.5 top-1/2 -translate-y-1/2" aria-hidden="true" />
                    <input
                      id="email"
                      name="email"
                      type="email"
                      placeholder="nama.anda@gmail.com"
                      value={formData.email}
                      onChange={handleChange}
                      required
                      className="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-300 text-xs sm:text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#C89332] focus:border-transparent bg-white shadow-2xs transition-all"
                    />
                  </div>
                  <p className="text-[11px] text-gray-400 mt-1">Digunakan untuk menerima kode verifikasi OTP &amp; bukti pendaftaran.</p>
                </div>

                {/* 3. Nomor WhatsApp / HP Aktif */}
                <div>
                  <label htmlFor="phone" className="block text-xs font-bold text-gray-700 mb-1">
                    Nomor WhatsApp / HP Aktif <span className="text-red-500">*</span>
                  </label>
                  <div className="relative">
                    <Phone className="w-4 h-4 text-gray-400 absolute left-3.5 top-1/2 -translate-y-1/2" aria-hidden="true" />
                    <input
                      id="phone"
                      name="phone"
                      type="tel"
                      placeholder="081234567890"
                      value={formData.phone}
                      onChange={handleChange}
                      required
                      className="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-300 text-xs sm:text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#C89332] focus:border-transparent bg-white shadow-2xs transition-all"
                    />
                  </div>
                  <p className="text-[11px] text-gray-400 mt-1">Dapat menggunakan nomor calon murid atau nomor orang tua/wali.</p>
                </div>

                {/* 4. NIK Calon Siswa (16 Digit) + Cek Ketersediaan */}
                <div>
                  <div className="flex items-center justify-between mb-1">
                    <label htmlFor="nik" className="block text-xs font-bold text-gray-700">
                      NIK Calon Siswa (16 Digit)
                    </label>
                    <button
                      type="button"
                      onClick={handleCheckNik}
                      className="text-xs text-[#0A3D62] hover:text-[#C89332] font-semibold transition-colors focus:outline-none"
                    >
                      Cek Ketersediaan NIK
                    </button>
                  </div>
                  <div className="relative">
                    <IdCard className="w-4 h-4 text-gray-400 absolute left-3.5 top-1/2 -translate-y-1/2" aria-hidden="true" />
                    <input
                      id="nik"
                      name="nik"
                      type="text"
                      maxLength={16}
                      placeholder="357901xxxxxxxxxx"
                      value={formData.nik}
                      onChange={handleChange}
                      className="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-300 text-xs sm:text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#C89332] focus:border-transparent bg-white shadow-2xs font-mono transition-all"
                    />
                  </div>
                  {nikCheckStatus.checked && (
                    <div
                      className={`mt-1.5 flex items-center gap-1.5 text-xs font-medium ${
                        nikCheckStatus.available ? 'text-[#1E7A46]' : 'text-red-600'
                      }`}
                    >
                      {nikCheckStatus.available ? (
                        <CheckCircle2 className="w-3.5 h-3.5 text-[#1E7A46]" aria-hidden="true" />
                      ) : (
                        <AlertCircle className="w-3.5 h-3.5 text-red-500" aria-hidden="true" />
                      )}
                      <span>{nikCheckStatus.message}</span>
                    </div>
                  )}
                </div>

                {/* 5. Password & Konfirmasi Password */}
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3.5 pt-1">
                  <div>
                    <label htmlFor="password" className="block text-xs font-bold text-gray-700 mb-1">
                      Kata Sandi Baru <span className="text-red-500">*</span>
                    </label>
                    <div className="relative">
                      <Lock className="w-4 h-4 text-gray-400 absolute left-3.5 top-1/2 -translate-y-1/2" aria-hidden="true" />
                      <input
                        id="password"
                        name="password"
                        type={showPassword ? 'text' : 'password'}
                        placeholder="Min. 8 karakter"
                        value={formData.password}
                        onChange={handleChange}
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

                  <div>
                    <label htmlFor="password_confirmation" className="block text-xs font-bold text-gray-700 mb-1">
                      Ulangi Kata Sandi <span className="text-red-500">*</span>
                    </label>
                    <div className="relative">
                      <Lock className="w-4 h-4 text-gray-400 absolute left-3.5 top-1/2 -translate-y-1/2" aria-hidden="true" />
                      <input
                        id="password_confirmation"
                        name="password_confirmation"
                        type={showConfirmPassword ? 'text' : 'password'}
                        placeholder="Ketik ulang sandi"
                        value={formData.password_confirmation}
                        onChange={handleChange}
                        required
                        className="w-full pl-10 pr-10 py-2.5 rounded-xl border border-gray-300 text-xs sm:text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#C89332] focus:border-transparent bg-white shadow-2xs transition-all"
                      />
                      <button
                        type="button"
                        onClick={() => setShowConfirmPassword(!showConfirmPassword)}
                        className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 p-1"
                        aria-label={showConfirmPassword ? 'Sembunyikan konfirmasi kata sandi' : 'Tampilkan konfirmasi kata sandi'}
                      >
                        {showConfirmPassword ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                      </button>
                    </div>
                  </div>
                </div>

                {/* 6. Pernyataan Privasi & Keamanan */}
                <div className="p-3.5 rounded-xl bg-[#EBF3F8]/70 border border-[#D0E2ED] text-xs text-gray-600 flex items-start gap-2.5">
                  <ShieldCheck className="w-4 h-4 text-[#0A3D62] shrink-0 mt-0.5" aria-hidden="true" />
                  <span className="leading-relaxed">
                    Dengan mendaftar, Anda menyatakan bahwa seluruh data yang diisikan adalah sah dan benar untuk keperluan seleksi resmi PPDB SMP Laboratorium UM.
                  </span>
                </div>

                {/* 7. Tombol Submit Registrasi */}
                <div className="pt-2">
                  <button
                    type="submit"
                    disabled={isSubmitting}
                    className="w-full inline-flex items-center justify-center gap-2 bg-[#C89332] hover:bg-[#B57C23] text-white font-bold text-sm py-3 px-6 rounded-xl shadow-xs hover:shadow transition-all disabled:opacity-50 focus:outline-none focus:ring-2 focus:ring-[#C89332] focus:ring-offset-2"
                  >
                    {isSubmitting ? (
                      <>
                        <Loader2 className="w-4 h-4 animate-spin" />
                        <span>Memproses Pendaftaran Akun...</span>
                      </>
                    ) : (
                      <>
                        <span>Daftarkan Akun PPDB</span>
                        <ArrowRight className="w-4 h-4" aria-hidden="true" />
                      </>
                    )}
                  </button>
                </div>
              </form>

              {/* Footer Kartu Form */}
              <div className="mt-6 pt-5 border-t border-gray-100 text-center">
                <p className="text-xs text-gray-600">
                  Sudah memiliki akun pendaftar?{' '}
                  <Link href="/ppdb/masuk" className="text-[#062940] font-bold hover:text-[#C89332] transition-colors">
                    Masuk ke Portal Pendaftar &rarr;
                  </Link>
                </p>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* =========================================================================
          4. MODAL DIALOG VERIFIKASI OTP (AKSESIBEL & BERSIH)
          ========================================================================= */}
      {showOtpModal && (
        <div
          role="dialog"
          aria-modal="true"
          aria-labelledby="otp-modal-title"
          className="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 overflow-y-auto"
        >
          {/* Backdrop Gelap */}
          <div
            onClick={() => setShowOtpModal(false)}
            className="fixed inset-0 bg-black/70 backdrop-blur-sm transition-opacity"
            aria-hidden="true"
          />

          {/* Kontainer Modal */}
          <div className="relative bg-white rounded-3xl max-w-md w-full p-6 sm:p-8 shadow-2xl border border-gray-200 z-10 animate-in fade-in zoom-in-95 duration-200">
            {/* Tombol Tutup X */}
            <button
              type="button"
              onClick={() => setShowOtpModal(false)}
              className="absolute top-4 right-4 p-1.5 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-slate-100 transition-colors"
              aria-label="Tutup dialog verifikasi OTP"
            >
              <X className="w-5 h-5" aria-hidden="true" />
            </button>

            {/* Header Modal */}
            <div className="text-center mb-6">
              <div className="w-14 h-14 rounded-2xl bg-[#EBF3F8] text-[#0A3D62] flex items-center justify-center mx-auto mb-3 shadow-2xs">
                <KeyRound className="w-7 h-7" aria-hidden="true" />
              </div>
              <h3 id="otp-modal-title" className="font-pub-heading text-xl font-bold text-[#062940]">
                Aktivasi Akun &amp; Verifikasi OTP
              </h3>
              <p className="text-xs text-gray-500 mt-1.5 leading-relaxed">
                Kode 6-digit verifikasi telah dikirimkan ke <strong className="text-gray-800 font-mono">{formData.email}</strong>.
              </p>
            </div>

            {/* Alert Error / Success */}
            {otpError && (
              <div className="mb-4 p-3 rounded-xl bg-red-50 border border-red-200 text-xs text-red-700 flex items-center gap-2">
                <AlertCircle className="w-4 h-4 text-red-600 shrink-0" />
                <span>{otpError}</span>
              </div>
            )}

            {otpSuccessMessage && (
              <div className="mb-4 p-3 rounded-xl bg-emerald-50 border border-emerald-200 text-xs text-emerald-800 flex items-center gap-2">
                <CheckCircle2 className="w-4 h-4 text-emerald-600 shrink-0" />
                <span>{otpSuccessMessage}</span>
              </div>
            )}

            <form onSubmit={handleVerifyOtp} method="POST" className="space-y-4">
              <div>
                <label htmlFor="otp-input" className="block text-xs font-bold text-gray-700 mb-1.5 text-center">
                  Masukkan 6 Digit Kode OTP
                </label>
                <input
                  id="otp-input"
                  type="text"
                  maxLength={6}
                  placeholder="••••••"
                  value={otpCode}
                  onChange={(e) => setOtpCode(e.target.value.replace(/\D/g, ''))}
                  className="w-full text-center tracking-[0.5em] text-2xl font-bold font-mono py-3 rounded-xl border border-gray-300 text-gray-900 focus:outline-none focus:ring-2 focus:ring-[#C89332] bg-white shadow-2xs"
                  autoFocus
                  required
                />
              </div>

              <button
                type="submit"
                disabled={!isHydrated || isVerifyingOtp || !otpCode || otpCode.length !== 6}
                className="w-full h-11 bg-brand-blue hover:bg-blue-700 text-white font-semibold rounded-xl text-sm shadow-md flex items-center justify-center gap-2"
              >
                {isVerifyingOtp ? (
                  <>
                    <Loader2 className="w-4 h-4 animate-spin" />
                    <span>Memverifikasi Kode...</span>
                  </>
                ) : (
                  <>
                    <CheckCircle2 className="w-4 h-4" />
                    <span>Aktivasi Akun &amp; Masuk</span>
                  </>
                )}
              </button>
            </form>

            {/* Kirim Ulang OTP */}
            <div className="mt-5 pt-4 border-t border-gray-100 flex items-center justify-between text-xs text-gray-500">
              <span>Tidak menerima kode?</span>
              <button
                type="button"
                onClick={handleResendOtp}
                disabled={resendCooldown > 0}
                className={`inline-flex items-center gap-1 font-semibold transition-colors ${
                  resendCooldown > 0 ? 'text-gray-400 cursor-not-allowed' : 'text-[#062940] hover:text-[#C89332]'
                }`}
              >
                <RotateCw className="w-3.5 h-3.5" />
                {resendCooldown > 0 ? `Kirim ulang (${resendCooldown}s)` : 'Kirim Ulang Kode'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* =========================================================================
          5. PRE-FOOTER CALL TO ACTION (KONSISTEN SITUS WEB)
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

            <div className="flex flex-col sm:flex-row items-center gap-3 shrink-0 w-full sm:w-auto">
              <a
                href="https://wa.me/6285126088473"
                target="_blank"
                rel="noopener noreferrer"
                className="w-full sm:w-auto text-center bg-[#C89332] hover:bg-[#B57C23] text-white font-bold text-xs sm:text-sm px-6 py-3 rounded-lg shadow-sm hover:shadow transition-all inline-flex items-center justify-center gap-2 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-[#062940]"
              >
                <MessageCircle className="w-4 h-4" aria-hidden="true" />
                <span>Konsultasi WhatsApp</span>
              </a>
              <Link
                href="/ppdb"
                className="w-full sm:w-auto text-center inline-flex items-center justify-center gap-2 bg-[#062940] hover:bg-white/10 border border-white/40 hover:border-white text-white font-semibold text-xs sm:text-sm px-5 py-3 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-[#C89332]"
              >
                <span>Informasi Alur PPDB</span>
                <ArrowRight className="w-4 h-4" aria-hidden="true" />
              </Link>
            </div>
          </div>
        </div>
      </section>
    </main>
  );
}
