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

      // Interval hitung mundur resend OTP
      const timer = setInterval(() => {
        setResendCooldown((prev) => {
          if (prev <= 1) {
            clearInterval(timer);
            return 0;
          }
          return prev - 1;
        });
      }, 1000);
    } catch (err: any) {
      const msg = err.response?.data?.message || err.response?.data?.error?.message || err.message || 'Gagal mendaftar. Silakan periksa kembali isian Anda.';
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
      const msg = err.response?.data?.message || err.response?.data?.error?.message || 'Kode OTP tidak valid atau sudah kedaluwarsa.';
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
      const timer = setInterval(() => {
        setResendCooldown((prev) => {
          if (prev <= 1) {
            clearInterval(timer);
            return 0;
          }
          return prev - 1;
        });
      }, 1000);
    } catch (err: any) {
      setOtpError(err.response?.data?.message || 'Gagal mengirim ulang OTP.');
    }
  };

  return (
    <main className="min-h-screen bg-gradient-to-b from-slate-50 to-blue-50/30 py-12 px-4 sm:px-6 lg:px-8">
      <div className="max-w-xl mx-auto">
        {/* Header Branding */}
        <div className="text-center mb-8">
          <Link
            href="/ppdb"
            className="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-blue-100/80 text-brand-blue text-xs font-semibold mb-4 hover:bg-blue-200/80 transition-colors"
          >
            <School className="h-4 w-4" />
            Portal SPMB P2LP Universitas Negeri Malang
          </Link>
          <h1 className="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
            Pendaftaran Akun Calon Siswa
          </h1>
          <p className="mt-2 text-sm text-slate-600">
            Buat akun untuk mengisi formulir pendaftaran, unggah berkas, dan memantau status seleksi PPDB 2026/2027.
          </p>
        </div>

        {/* Card Form */}
        <div className="bg-white rounded-2xl shadow-elevated border border-slate-200 p-6 sm:p-8">
          {errorMessage && (
            <div className="mb-6 p-4 rounded-xl bg-red-50 border border-red-200 flex items-start gap-3 text-sm text-red-800">
              <AlertCircle className="h-5 w-5 text-red-600 shrink-0 mt-0.5" />
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

            {/* Ketentuan Privasi */}
            <div className="p-3.5 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-600 flex items-start gap-2.5">
              <ShieldCheck className="h-4 w-4 text-brand-teal shrink-0 mt-0.5" />
              <span>
                Dengan mendaftar, Anda menyetujui bahwa data yang diisikan adalah benar untuk keperluan seleksi resmi PPDB SMP-SMA Lab UM.
              </span>
            </div>

            {/* Tombol Buat Akun */}
            <Button
              type="submit"
              disabled={!isHydrated || isSubmitting}
              className="w-full h-11 bg-brand-blue hover:bg-blue-700 text-white font-semibold rounded-xl text-sm shadow-md flex items-center justify-center gap-2 mt-2"
            >
              {isSubmitting ? (
                <>
                  <Loader2 className="h-4 w-4 animate-spin" />
                  Memproses Pendaftaran...
                </>
              ) : (
                <>
                  Daftarkan Akun PPDB
                  <ArrowRight className="h-4 w-4" />
                </>
              )}
            </Button>
          </form>

          {/* Footer Card */}
          <div className="mt-6 pt-5 border-t border-slate-100 text-center">
            <p className="text-xs text-slate-600">
              Sudah memiliki akun pendaftar?{' '}
              <Link href="/ppdb/masuk" className="text-brand-blue font-semibold hover:underline">
                Masuk ke Portal Pendaftar
              </Link>
            </p>
          </div>
        </div>
      </div>

      {/* Modal Dialog Verifikasi OTP */}
      {showOtpModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
          <div className="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200 animate-in fade-in zoom-in-95 duration-200">
            <div className="text-center mb-5">
              <div className="inline-flex h-12 w-12 items-center justify-center rounded-full bg-blue-100 text-brand-blue mb-3">
                <KeyRound className="h-6 w-6" />
              </div>
              <h3 className="text-lg font-bold text-slate-900">Verifikasi Kode OTP</h3>
              <p className="text-xs text-slate-600 mt-1">
                Kode verifikasi 6-digit telah dikirimkan ke <span className="font-semibold text-slate-800">{formData.email}</span>.
              </p>
            </div>

            {otpError && (
              <div className="mb-4 p-3 rounded-lg bg-red-50 border border-red-200 text-xs text-red-700 flex items-center gap-2">
                <AlertCircle className="h-4 w-4 text-red-600 shrink-0" />
                <span>{otpError}</span>
              </div>
            )}

            {otpSuccessMessage && (
              <div className="mb-4 p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-xs text-emerald-800 flex items-center gap-2">
                <CheckCircle2 className="h-4 w-4 text-emerald-600 shrink-0" />
                <span>{otpSuccessMessage}</span>
              </div>
            )}

            <form onSubmit={handleVerifyOtp} method="POST" className="space-y-4">
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1.5 text-center">
                  Masukkan 6 Digit Kode OTP
                </label>
                <Input
                  type="text"
                  maxLength={6}
                  placeholder="Contoh: 123456"
                  value={otpCode}
                  onChange={(e) => setOtpCode(e.target.value.replace(/\D/g, ''))}
                  className="text-center tracking-widest text-xl font-bold font-mono h-12"
                  autoFocus
                  required
                />
              </div>

              <Button
                type="submit"
                disabled={!isHydrated || isVerifyingOtp || !otpCode || otpCode.length !== 6}
                className="w-full h-11 bg-brand-blue hover:bg-blue-700 text-white font-semibold rounded-xl text-sm shadow-md flex items-center justify-center gap-2"
              >
                {isVerifyingOtp ? (
                  <>
                    <Loader2 className="h-4 w-4 animate-spin" />
                    Memverifikasi...
                  </>
                ) : (
                  <>
                    <CheckCircle2 className="h-4 w-4" />
                    Aktivasi Akun & Masuk
                  </>
                )}
              </Button>
            </form>

            <div className="mt-5 pt-4 border-t border-slate-100 flex items-center justify-between text-xs text-slate-600">
              <span>Tidak menerima kode?</span>
              <button
                type="button"
                onClick={handleResendOtp}
                disabled={resendCooldown > 0}
                className={`inline-flex items-center gap-1 font-semibold ${
                  resendCooldown > 0 ? 'text-slate-400 cursor-not-allowed' : 'text-brand-blue hover:underline'
                }`}
              >
                <RotateCw className="h-3 w-3" />
                {resendCooldown > 0 ? `Kirim ulang (${resendCooldown}s)` : 'Kirim Ulang Kode'}
              </button>
            </div>
          </div>
        </div>
      )}
    </main>
  );
}
