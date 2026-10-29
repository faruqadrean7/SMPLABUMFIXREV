'use client';

import React, { useState } from 'react';
import Link from 'next/link';
import Image from 'next/image';
import { useQuery } from '@tanstack/react-query';
import { ppdbApi } from '@/features/ppdb/ppdb-api';
import { PPDBWaveItem, PPDBTrackItem, PPDBPublicResult } from '@/types/ppdb';
import { formatTanggalIndo } from '@/lib/utils';
import {
  Calendar,
  Users,
  CheckCircle2,
  AlertCircle,
  Clock,
  Search,
  FileText,
  Award,
  HeartHandshake,
  BookOpen,
  ArrowRight,
  Phone,
  MapPin,
  Loader2,
  Check,
  AlertTriangle,
  XCircle,
  GraduationCap,
  UploadCloud,
  ArrowDown,
  BookCheck,
} from 'lucide-react';

export default function PublicPPDBPage() {
  // Query Active Wave
  const {
    data: activeWave,
    isLoading: isLoadingWave,
  } = useQuery<PPDBWaveItem | null>({
    queryKey: ['ppdb-public-active-wave'],
    queryFn: () => ppdbApi.getActiveWave(),
  });

  // Query Tracks for active wave or all public tracks
  const { data: tracks = [], isLoading: isLoadingTracks } = useQuery<PPDBTrackItem[]>({
    queryKey: ['ppdb-public-tracks', activeWave?.id],
    queryFn: () => ppdbApi.listPublicTracks(activeWave?.id),
    enabled: true,
  });

  // State Cek Status Seleksi
  const [searchRegNo, setSearchRegNo] = useState('');
  const [searchBirthDate, setSearchBirthDate] = useState('');
  const [isChecking, setIsChecking] = useState(false);
  const [checkResult, setCheckResult] = useState<PPDBPublicResult | null>(null);
  const [checkError, setCheckError] = useState<string | null>(null);

  const handleCheckResult = async (e: React.FormEvent) => {
    e.preventDefault();
    setCheckError(null);
    setCheckResult(null);

    const regNo = searchRegNo.trim();
    const birthDate = searchBirthDate.trim();

    if (!regNo) {
      setCheckError('Nomor pendaftaran wajib diisi.');
      return;
    }

    if (!birthDate) {
      setCheckError('Tanggal lahir calon siswa wajib diisi.');
      return;
    }

    setIsChecking(true);
    try {
      const result = await ppdbApi.checkPublicResult({
        registration_no: regNo,
        birth_date: birthDate,
      });
      setCheckResult(result);
    } catch (err: any) {
      const msg =
        err?.response?.data?.message ||
        err?.message ||
        'Pengecekan gagal. Periksa kembali nomor pendaftaran dan tanggal lahir Anda.';
      setCheckError(msg);
    } finally {
      setIsChecking(false);
    }
  };

  return (
    <main id="main-content" tabIndex={-1} className="flex-1 flex flex-col focus:outline-none bg-slate-50">
      {/* =========================================================================
          1. HERO SECTION (FULL-BLEED NAVY STYLE DENGAN FOTO SISWA DI KANAN)
          ========================================================================= */}
      <section
        aria-labelledby="ppdb-hero-heading"
        className="relative bg-[#062940] text-white overflow-hidden border-b border-white/10 w-full"
      >
        <div className="relative w-full min-h-0 lg:min-h-[460px] flex flex-col lg:flex-row lg:items-center">
          {/* Layer Foto Siswa di Kanan - Dominan 65% dengan Gradasi Halus Persis Referensi */}
          <div className="hidden lg:block absolute inset-y-0 right-0 w-[58%] xl:w-[62%] 2xl:w-[66%] overflow-hidden pointer-events-none select-none">
            <div className="relative w-full h-full">
              <Image
                src="/images/ppdb-students.jpg"
                alt="Siswa-siswi SMP Laboratorium Universitas Negeri Malang berprestasi dan berkarakter"
                fill
                priority
                unoptimized
                sizes="(max-width: 1024px) 100vw, 65vw"
                className="object-cover object-center"
              />
              {/* Gradasi Transparan Halus di Tepi Kiri: Membaur mulus tanpa menutupi aktivitas siswa */}
              <div
                className="absolute inset-0 bg-gradient-to-r from-[#062940] via-[#062940]/80 via-10% lg:via-15% to-transparent to-35% lg:to-45%"
                aria-hidden="true"
              />
            </div>
          </div>

          {/* Konten Teks Hero (Mobile & Desktop) */}
          <div className="relative z-10 w-full lg:w-[44%] xl:w-[40%] px-4 sm:px-8 lg:px-12 xl:px-16 pt-8 sm:pt-12 pb-8 lg:py-16">
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
                  <li aria-current="page" className="font-semibold text-white">
                    PPDB Online
                  </li>
                </ol>
              </nav>

              {/* Badge Pill Emas Persis Referensi */}
              <div className="inline-flex items-center gap-2 bg-[#C89332] text-white text-xs font-semibold px-4 py-1.5 rounded-full w-fit shadow-2xs">
                <span>Penerimaan Peserta Didik Baru – T.A. 2026/2027</span>
              </div>

              <div>
                <h1
                  id="ppdb-hero-heading"
                  className="font-pub-heading text-3xl sm:text-4xl lg:text-[44px] font-bold text-white tracking-tight leading-[1.15] mb-3.5"
                >
                  Sistem Penerimaan
                  <br />
                  Murid Baru (PPDB)
                  <br />
                  <span className="text-white">SMP Laboratorium UM</span>
                </h1>

                <p className="text-white/85 text-xs sm:text-sm lg:text-[15px] leading-relaxed max-w-lg mb-6">
                  Membentuk generasi pembelajar berkarakter, kreatif, dan berprestasi di bawah binaan langsung P2LP Universitas Negeri Malang.
                </p>
              </div>

              {/* 3 Tombol Aksi Hero Persis Referensi */}
              <div className="flex flex-wrap items-center gap-3 pt-1">
                <Link
                  href="/ppdb/daftar"
                  className="inline-flex items-center gap-2 bg-[#C89332] hover:bg-[#B57C23] text-white font-bold text-xs sm:text-sm px-5 py-2.5 rounded-xl shadow-xs transition-all focus:outline-none focus:ring-2 focus:ring-white"
                >
                  <span>Daftar Akun Calon Siswa</span>
                  <ArrowRight className="w-4 h-4" aria-hidden="true" />
                </Link>
                <Link
                  href="/ppdb/masuk"
                  className="inline-flex items-center gap-2 bg-[#062940] hover:bg-white/10 border border-white/30 text-white font-semibold text-xs sm:text-sm px-4 py-2.5 rounded-xl transition-all focus:outline-none focus:ring-2 focus:ring-[#C89332]"
                >
                  <span>Masuk Portal Pendaftar</span>
                  <ArrowRight className="w-4 h-4" aria-hidden="true" />
                </Link>
                <a
                  href="#cek-status"
                  className="inline-flex items-center gap-2 bg-[#062940] hover:bg-white/10 border border-white/30 text-white font-semibold text-xs sm:text-sm px-4 py-2.5 rounded-xl transition-all focus:outline-none focus:ring-2 focus:ring-[#C89332]"
                >
                  <span>Cek Hasil Mandiri</span>
                  <ArrowDown className="w-4 h-4" aria-hidden="true" />
                </a>
              </div>
            </div>
          </div>

          {/* Mobile / Tablet Image: Membentang 100% selebar layar tanpa celah samping */}
          <div className="lg:hidden relative aspect-[16/10] sm:aspect-[16/9] w-full overflow-hidden bg-slate-900">
            <Image
              src="/images/ppdb-students.jpg"
              alt="Siswa-siswi SMP Laboratorium Universitas Negeri Malang berprestasi dan berkarakter"
              fill
              priority
              unoptimized
              sizes="100vw"
              className="object-cover object-center"
            />
            <div
              className="absolute inset-x-0 top-0 h-12 sm:h-16 bg-gradient-to-b from-[#062940] via-[#062940]/40 to-transparent"
              aria-hidden="true"
            />
          </div>
        </div>
      </section>

      {/* =========================================================================
          2. BANNER GELOMBANG AKTIF (TIMELINE JADWAL SELEKSI RESMI)
          ========================================================================= */}
      <section aria-label="Gelombang Pendaftaran Aktif" className="py-8 sm:py-10 w-full">
        <div className="w-full px-4 sm:px-8 lg:px-12 xl:px-16">
          <div className="bg-white border border-gray-200/90 rounded-2xl p-6 sm:p-7 shadow-xs">
            {/* Header Banner */}
            <div className="flex flex-col md:flex-row md:items-center justify-between pb-6 border-b border-gray-100 gap-4">
              <div className="flex items-start sm:items-center gap-4">
                <div className="w-12 h-12 rounded-xl bg-[#EBF3F8] text-[#0A3D62] flex items-center justify-center shrink-0">
                  <Calendar className="w-6 h-6" aria-hidden="true" />
                </div>
                <div>
                  <div className="flex flex-wrap items-center gap-2.5">
                    <h2 className="font-pub-heading text-lg sm:text-xl font-bold text-[#062940]">
                      {activeWave?.name || 'Gelombang 1 - Reguler & Prestasi'}
                    </h2>
                    <span className="inline-flex items-center px-3 py-0.5 rounded-full text-xs font-semibold bg-[#E8F5E9] text-[#1E7A46]">
                      Pendaftaran Sedang Dibuka
                    </span>
                  </div>
                  <p className="text-gray-500 text-xs sm:text-sm mt-1">
                    Pastikan kamu tidak melewatkan kesempatan untuk menjadi bagian dari SMP Laboratorium UM!
                  </p>
                </div>
              </div>

              <a
                href="#alur-pendaftaran"
                className="inline-flex items-center gap-1.5 text-xs sm:text-sm font-semibold text-[#0A3D62] hover:text-[#C89332] transition-colors shrink-0 group focus:outline-none"
              >
                <span>Lihat Jadwal Lengkap</span>
                <ArrowRight className="w-4 h-4 group-hover:translate-x-0.5 transition-transform" aria-hidden="true" />
              </a>
            </div>

            {/* 4 Kolom Timeline Periode Seleksi */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mt-6">
              {/* Item 1: Masa Pendaftaran */}
              <div className="flex items-start gap-3 p-3.5 rounded-xl bg-slate-50 border border-gray-100">
                <div className="w-8 h-8 rounded-lg bg-[#0A3D62] text-white flex items-center justify-center shrink-0 mt-0.5">
                  <Calendar className="w-4 h-4" aria-hidden="true" />
                </div>
                <div>
                  <p className="text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Masa Pendaftaran</p>
                  <p className="text-xs sm:text-sm font-bold text-[#062940]">
                    {activeWave ? `${formatTanggalIndo(activeWave.open_at)} - ${formatTanggalIndo(activeWave.close_at)}` : '01 Mei – 30 Mei 2026'}
                  </p>
                  <p className="text-[11px] text-gray-400 mt-0.5">(Online &amp; Unggah Berkas)</p>
                </div>
              </div>

              {/* Item 2: Verifikasi Berkas */}
              <div className="flex items-start gap-3 p-3.5 rounded-xl bg-slate-50 border border-gray-100">
                <div className="w-8 h-8 rounded-lg bg-[#0A3D62] text-white flex items-center justify-center shrink-0 mt-0.5">
                  <FileText className="w-4 h-4" aria-hidden="true" />
                </div>
                <div>
                  <p className="text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Verifikasi Berkas</p>
                  <p className="text-xs sm:text-sm font-bold text-[#062940]">02 Juni 2026</p>
                  <p className="text-[11px] text-gray-400 mt-0.5">(Validasi Dokumen)</p>
                </div>
              </div>

              {/* Item 3: Pengumuman Kelulusan */}
              <div className="flex items-start gap-3 p-3.5 rounded-xl bg-slate-50 border border-gray-100">
                <div className="w-8 h-8 rounded-lg bg-[#0A3D62] text-white flex items-center justify-center shrink-0 mt-0.5">
                  <Award className="w-4 h-4" aria-hidden="true" />
                </div>
                <div>
                  <p className="text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Pengumuman Kelulusan</p>
                  <p className="text-xs sm:text-sm font-bold text-[#062940]">
                    {activeWave?.result_publish_at ? formatTanggalIndo(activeWave.result_publish_at) : '05 Juni 2026'}
                  </p>
                  <p className="text-[11px] text-gray-400 mt-0.5">(Online di Website)</p>
                </div>
              </div>

              {/* Item 4: Daftar Ulang */}
              <div className="flex items-start gap-3 p-3.5 rounded-xl bg-slate-50 border border-gray-100">
                <div className="w-8 h-8 rounded-lg bg-[#0A3D62] text-white flex items-center justify-center shrink-0 mt-0.5">
                  <GraduationCap className="w-4 h-4" aria-hidden="true" />
                </div>
                <div>
                  <p className="text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Daftar Ulang</p>
                  <p className="text-xs sm:text-sm font-bold text-[#062940]">08 – 12 Juni 2026</p>
                  <p className="text-[11px] text-gray-400 mt-0.5">(H+3 s/d H+7)</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* =========================================================================
          3. JALUR PENDAFTARAN (GRID 4 KARTU PERSIS REFERENSI)
          ========================================================================= */}
      <section aria-label="Jalur Pendaftaran SPMB" className="py-10 sm:py-14 w-full bg-slate-50">
        <div className="w-full px-4 sm:px-8 lg:px-12 xl:px-16">
          {/* Header Seksi */}
          <div className="mb-8">
            <h2 className="font-pub-heading text-2xl sm:text-3xl font-bold text-[#062940]">
              Jalur Pendaftaran
            </h2>
            <p className="text-gray-500 text-xs sm:text-sm mt-1 leading-relaxed">
              Pilih jalur yang sesuai dengan potensi dan prestasi terbaikmu.
            </p>
          </div>

          {/* Grid 4 Kartu Jalur */}
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 items-stretch">
            {/* JALUR 1: REGULER */}
            <article className="bg-white border border-gray-200/90 rounded-2xl p-6 shadow-xs flex flex-col justify-between hover:shadow-md transition-all">
              <div>
                <div className="flex items-center gap-3.5 mb-4">
                  <div className="w-12 h-12 rounded-xl bg-[#0A3D62] text-white flex items-center justify-center shrink-0 shadow-2xs">
                    <BookOpen className="w-6 h-6" aria-hidden="true" />
                  </div>
                  <div>
                    <h3 className="font-pub-heading font-bold text-sm text-[#062940] uppercase tracking-wide">
                      Jalur Reguler
                    </h3>
                    <span className="text-[11px] text-gray-500 font-medium">Kuota 60% Rombel</span>
                  </div>
                </div>

                <p className="text-gray-600 text-xs leading-relaxed mb-4">
                  Penilaian nilai rapor, tes pemetaan skolastik, dan wawancara peminatan.
                </p>

                <ul className="text-xs text-gray-500 space-y-1.5 mb-6">
                  <li className="flex items-center gap-2">
                    <span className="w-1.5 h-1.5 rounded-full bg-[#0A3D62]" />
                    <span>Kurikulum Merdeka</span>
                  </li>
                  <li className="flex items-center gap-2">
                    <span className="w-1.5 h-1.5 rounded-full bg-[#0A3D62]" />
                    <span>Mastery Learning</span>
                  </li>
                  <li className="flex items-center gap-2">
                    <span className="w-1.5 h-1.5 rounded-full bg-[#0A3D62]" />
                    <span>Pembinaan karakter &amp; adab</span>
                  </li>
                </ul>
              </div>

              <div className="pt-4 border-t border-gray-100">
                <Link
                  href="/ppdb/daftar"
                  className="inline-flex items-center gap-1.5 text-xs font-semibold text-[#0A3D62] hover:text-[#C89332] transition-colors"
                >
                  <span>Detail Jalur</span>
                  <ArrowRight className="w-3.5 h-3.5" aria-hidden="true" />
                </Link>
              </div>
            </article>

            {/* JALUR 2: PRESTASI */}
            <article className="bg-white border border-gray-200/90 rounded-2xl p-6 shadow-xs flex flex-col justify-between hover:shadow-md transition-all">
              <div>
                <div className="flex items-center gap-3.5 mb-4">
                  <div className="w-12 h-12 rounded-xl bg-[#C89332] text-white flex items-center justify-center shrink-0 shadow-2xs">
                    <Award className="w-6 h-6" aria-hidden="true" />
                  </div>
                  <div>
                    <h3 className="font-pub-heading font-bold text-sm text-[#C89332] tracking-wide">
                      Jalur Prestasi
                    </h3>
                    <span className="text-[11px] text-gray-500 font-medium">Kuota 20% Rombel</span>
                  </div>
                </div>

                <p className="text-gray-600 text-xs leading-relaxed mb-4">
                  Untuk peraih juara OSN, O2SN, FLS2N, atau prestasi non-akademik lainnya.
                </p>

                <ul className="text-xs text-gray-500 space-y-1.5 mb-6">
                  <li className="flex items-center gap-2">
                    <span className="w-1.5 h-1.5 rounded-full bg-[#C89332]" />
                    <span>Bebas tes tulis akademik</span>
                  </li>
                  <li className="flex items-center gap-2">
                    <span className="w-1.5 h-1.5 rounded-full bg-[#C89332]" />
                    <span>Prioritas kuota</span>
                  </li>
                  <li className="flex items-center gap-2">
                    <span className="w-1.5 h-1.5 rounded-full bg-[#C89332]" />
                    <span>Sertifikat/ piagam prestasi</span>
                  </li>
                </ul>
              </div>

              <div className="pt-4 border-t border-gray-100">
                <Link
                  href="/ppdb/daftar"
                  className="inline-flex items-center gap-1.5 text-xs font-semibold text-[#C89332] hover:text-[#B57C23] transition-colors"
                >
                  <span>Detail Jalur</span>
                  <ArrowRight className="w-3.5 h-3.5" aria-hidden="true" />
                </Link>
              </div>
            </article>

            {/* JALUR 3: AFIRMASI */}
            <article className="bg-white border border-gray-200/90 rounded-2xl p-6 shadow-xs flex flex-col justify-between hover:shadow-md transition-all">
              <div>
                <div className="flex items-center gap-3.5 mb-4">
                  <div className="w-12 h-12 rounded-xl bg-[#1E7A46] text-white flex items-center justify-center shrink-0 shadow-2xs">
                    <HeartHandshake className="w-6 h-6" aria-hidden="true" />
                  </div>
                  <div>
                    <h3 className="font-pub-heading font-bold text-sm text-[#1E7A46] tracking-wide">
                      Jalur Afirmasi
                    </h3>
                    <span className="text-[11px] text-gray-500 font-medium">Kuota 15% Rombel</span>
                  </div>
                </div>

                <p className="text-gray-600 text-xs leading-relaxed mb-4">
                  Untuk pemegang KIP, PKH, atau terdaftar DTKS.
                </p>

                <ul className="text-xs text-gray-500 space-y-1.5 mb-6">
                  <li className="flex items-center gap-2">
                    <span className="w-1.5 h-1.5 rounded-full bg-[#1E7A46]" />
                    <span>Bantuan subsidi pendidikan</span>
                  </li>
                  <li className="flex items-center gap-2">
                    <span className="w-1.5 h-1.5 rounded-full bg-[#1E7A46]" />
                    <span>Kesempatan yang sama</span>
                  </li>
                  <li className="flex items-center gap-2">
                    <span className="w-1.5 h-1.5 rounded-full bg-[#1E7A46]" />
                    <span>Pendampingan khusus</span>
                  </li>
                </ul>
              </div>

              <div className="pt-4 border-t border-gray-100">
                <Link
                  href="/ppdb/daftar"
                  className="inline-flex items-center gap-1.5 text-xs font-semibold text-[#1E7A46] hover:text-[#166037] transition-colors"
                >
                  <span>Detail Jalur</span>
                  <ArrowRight className="w-3.5 h-3.5" aria-hidden="true" />
                </Link>
              </div>
            </article>

            {/* JALUR 4: TAHFIDZ */}
            <article className="bg-white border border-gray-200/90 rounded-2xl p-6 shadow-xs flex flex-col justify-between hover:shadow-md transition-all">
              <div>
                <div className="flex items-center gap-3.5 mb-4">
                  <div className="w-12 h-12 rounded-xl bg-[#7C3AED] text-white flex items-center justify-center shrink-0 shadow-2xs">
                    <BookCheck className="w-6 h-6" aria-hidden="true" />
                  </div>
                  <div>
                    <h3 className="font-pub-heading font-bold text-sm text-[#7C3AED] tracking-wide">
                      Jalur Tahfidz Qur&apos;an
                    </h3>
                    <span className="text-[11px] text-gray-500 font-medium">Kuota 5% Rombel</span>
                  </div>
                </div>

                <p className="text-gray-600 text-xs leading-relaxed mb-4">
                  Untuk penghafal Al-Qur&apos;an minimal 2 Juz mutqin.
                </p>

                <ul className="text-xs text-gray-500 space-y-1.5 mb-6">
                  <li className="flex items-center gap-2">
                    <span className="w-1.5 h-1.5 rounded-full bg-[#7C3AED]" />
                    <span>Uji tasmi&apos; langsung</span>
                  </li>
                  <li className="flex items-center gap-2">
                    <span className="w-1.5 h-1.5 rounded-full bg-[#7C3AED]" />
                    <span>Pembinaan intensif</span>
                  </li>
                  <li className="flex items-center gap-2">
                    <span className="w-1.5 h-1.5 rounded-full bg-[#7C3AED]" />
                    <span>Integrasi kurikulum akademik</span>
                  </li>
                </ul>
              </div>

              <div className="pt-4 border-t border-gray-100">
                <Link
                  href="/ppdb/daftar"
                  className="inline-flex items-center gap-1.5 text-xs font-semibold text-[#7C3AED] hover:text-[#6D28D9] transition-colors"
                >
                  <span>Detail Jalur</span>
                  <ArrowRight className="w-3.5 h-3.5" aria-hidden="true" />
                </Link>
              </div>
            </article>
          </div>
        </div>
      </section>

      {/* =========================================================================
          4. ALUR PENDAFTARAN (5 STEPPER HORIZONTAL PERSIS REFERENSI)
          ========================================================================= */}
      <section id="alur-pendaftaran" className="py-12 sm:py-16 w-full bg-white border-y border-gray-100 scroll-mt-20">
        <div className="w-full px-4 sm:px-8 lg:px-12 xl:px-16">
          {/* Header Seksi */}
          <div className="mb-10">
            <h2 className="font-pub-heading text-2xl sm:text-3xl font-bold text-[#062940]">
              Alur Pendaftaran
            </h2>
            <p className="text-gray-500 text-xs sm:text-sm mt-1 leading-relaxed">
              Ikuti 5 langkah mudah untuk mendaftarkan putra/putri Anda.
            </p>
          </div>

          {/* Stepper Grid 5 Langkah */}
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-6 sm:gap-4 relative">
            {[
              {
                step: '01',
                icon: Users,
                title: 'Buat Akun',
                desc: 'Registrasi mandiri email/WhatsApp + verifikasi OTP',
              },
              {
                step: '02',
                icon: FileText,
                title: 'Isi Biodata',
                desc: 'Input NIK, NISN, data pribadi, dan profil orang tua',
              },
              {
                step: '03',
                icon: UploadCloud,
                title: 'Unggah Berkas',
                desc: 'KK, Akta Kelahiran, Pas Foto, dan Rapor (PDF/JPG)',
              },
              {
                step: '04',
                icon: CheckCircle2,
                title: 'Verifikasi',
                desc: 'Panitia memeriksa keabsahan dokumen (online)',
              },
              {
                step: '05',
                icon: GraduationCap,
                title: 'Pengumuman & Daftar Ulang',
                desc: 'Cetak tanda bukti kelulusan & verifikasi fisik',
              },
            ].map((item, idx, arr) => {
              const StepIcon = item.icon;

              return (
                <div key={item.step} className="flex flex-col items-center text-center relative group">
                  {/* Icon Circle */}
                  <div className="relative mb-4">
                    <div className="w-16 h-16 rounded-full bg-[#EBF3F8] border-2 border-[#D0E2ED] text-[#0A3D62] flex items-center justify-center group-hover:bg-[#0A3D62] group-hover:text-white transition-all shadow-xs">
                      <StepIcon className="w-7 h-7" aria-hidden="true" />
                    </div>
                    {/* Badge Nomor Langkah */}
                    <span className="absolute -top-1 -left-1 w-6 h-6 rounded-full bg-[#062940] text-white text-[11px] font-bold flex items-center justify-center font-mono shadow-xs">
                      {item.step}
                    </span>
                  </div>

                  <h3 className="font-pub-heading font-bold text-sm text-[#062940] mb-1">
                    {item.title}
                  </h3>
                  <p className="text-gray-500 text-[11px] leading-relaxed max-w-[200px]">
                    {item.desc}
                  </p>

                  {/* Panah Penghubung Desktop */}
                  {idx < arr.length - 1 && (
                    <div className="hidden lg:block absolute top-7 -right-3 w-6 text-gray-300">
                      <ArrowRight className="w-4 h-4 mx-auto" aria-hidden="true" />
                    </div>
                  )}
                </div>
              );
            })}
          </div>
        </div>
      </section>

      {/* =========================================================================
          5. CEK PENGUMUMAN HASIL SELEKSI & BANTUAN PANITIA (2 KOLOM BESAR)
          ========================================================================= */}
      <section id="cek-status" className="py-12 sm:py-16 w-full bg-slate-50 scroll-mt-20">
        <div className="w-full px-4 sm:px-8 lg:px-12 xl:px-16">
          <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
            {/* KOLOM KIRI: CEK PENGUMUMAN HASIL SELEKSI (Span 7 / approx 60%) */}
            <div className="lg:col-span-7 bg-white border border-gray-200/90 rounded-2xl p-6 sm:p-8 shadow-xs overflow-hidden">
              <div className="grid grid-cols-1 md:grid-cols-12 gap-6 items-start">
                {/* Foto Siswa di Kiri Form */}
                <div className="md:col-span-4 hidden sm:block relative aspect-[3/4] w-full rounded-xl overflow-hidden shadow-xs bg-slate-100">
                  <Image
                    src="/images/news-hero.jpg"
                    alt="Siswa SMP Laboratorium UM"
                    fill
                    unoptimized
                    sizes="200px"
                    className="object-cover object-center"
                  />
                </div>

                {/* Form Input Cek Hasil di Kanan Foto */}
                <div className="md:col-span-8 space-y-4">
                  <div>
                    <h2 className="font-pub-heading text-xl sm:text-2xl font-bold text-[#062940] leading-snug">
                      Cek Pengumuman Hasil Seleksi
                    </h2>
                    <p className="text-gray-500 text-xs mt-1">
                      Masukkan nomor pendaftaran dan tanggal lahir Anda.
                    </p>
                  </div>

                  <form onSubmit={handleCheckResult} className="space-y-3.5">
                    <div>
                      <label htmlFor="reg-no-input" className="block text-[11px] font-bold text-gray-700 mb-1">
                        Nomor Pendaftaran <span className="text-red-500">*</span>
                      </label>
                      <input
                        id="reg-no-input"
                        type="text"
                        placeholder="REG-202610-xxxx"
                        value={searchRegNo}
                        onChange={(e) => setSearchRegNo(e.target.value)}
                        disabled={isChecking}
                        required
                        className="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 text-xs sm:text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#C89332] font-mono uppercase bg-white shadow-2xs"
                      />
                    </div>

                    <div>
                      <label htmlFor="birthdate-input" className="block text-[11px] font-bold text-gray-700 mb-1">
                        Tanggal Lahir <span className="text-red-500">*</span>
                      </label>
                      <input
                        id="birthdate-input"
                        type="date"
                        value={searchBirthDate}
                        onChange={(e) => setSearchBirthDate(e.target.value)}
                        disabled={isChecking}
                        required
                        className="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 text-xs sm:text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-[#C89332] bg-white shadow-2xs"
                      />
                    </div>

                    {checkError && (
                      <div className="flex items-center gap-2 p-3 rounded-xl bg-red-50 border border-red-200 text-xs text-red-800">
                        <AlertCircle className="w-4 h-4 shrink-0 text-red-600" />
                        <span>{checkError}</span>
                      </div>
                    )}

                    <div>
                      <button
                        type="submit"
                        disabled={isChecking}
                        className="w-full inline-flex items-center justify-center gap-2 bg-[#062940] hover:bg-[#0A3D62] text-white font-bold text-xs sm:text-sm py-2.5 px-4 rounded-xl shadow-xs transition-all disabled:opacity-50"
                      >
                        {isChecking ? (
                          <>
                            <Loader2 className="w-4 h-4 animate-spin" />
                            <span>Memeriksa Database Pengumuman...</span>
                          </>
                        ) : (
                          <>
                            <Search className="w-4 h-4" />
                            <span>Cek Hasil</span>
                          </>
                        )}
                      </button>
                    </div>
                  </form>
                </div>
              </div>

              {/* HASIL NYATA DARI QUERY PENCARIAN (Bila sudah dicari) */}
              {checkResult && (
                <div className="mt-6 pt-6 border-t border-gray-100 animate-in fade-in zoom-in-95 duration-200">
                  {checkResult.decision === 'diterima' ? (
                    <div className="rounded-xl border-2 border-emerald-500 bg-emerald-50/80 p-5 text-slate-900 space-y-3">
                      <div className="flex items-center gap-3">
                        <CheckCircle2 className="w-7 h-7 text-emerald-600 shrink-0" />
                        <div>
                          <span className="text-[10px] font-bold uppercase tracking-wider text-emerald-700 bg-emerald-100 px-2 py-0.5 rounded">
                            SELAMAT! ANDA DITERIMA
                          </span>
                          <h4 className="text-base font-bold text-emerald-950 mt-0.5">
                            {checkResult.full_name}
                          </h4>
                          <p className="text-xs text-emerald-800 font-mono">
                            No. Pendaftaran: {checkResult.registration_no}
                          </p>
                        </div>
                      </div>

                      <div className="grid grid-cols-2 gap-2 text-xs pt-1">
                        <div className="bg-white p-2 rounded-lg border border-emerald-100">
                          <span className="text-[10px] text-gray-500 block">Jalur Diterima:</span>
                          <span className="font-bold text-gray-800">
                            {checkResult.track_name || checkResult.track_code || 'Jalur Reguler'}
                          </span>
                        </div>
                        <div className="bg-white p-2 rounded-lg border border-emerald-100">
                          <span className="text-[10px] text-gray-500 block">Peringkat / Skor:</span>
                          <span className="font-mono font-bold text-emerald-800">
                            Peringkat #{checkResult.rank || '-'} (Skor: {checkResult.score || '-'})
                          </span>
                        </div>
                      </div>

                      <div className="flex items-center justify-between pt-2">
                        <span className="text-[11px] text-emerald-700 font-medium">
                          Silakan unduh bukti kelulusan untuk daftar ulang
                        </span>
                        <button
                          type="button"
                          onClick={() => window.print()}
                          className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-semibold shadow-2xs"
                        >
                          <FileText className="w-3.5 h-3.5" />
                          <span>Cetak Bukti Kelulusan</span>
                        </button>
                      </div>
                    </div>
                  ) : checkResult.decision === 'cadangan' ? (
                    <div className="rounded-xl border-2 border-amber-500 bg-amber-50/80 p-5 text-slate-900 space-y-3">
                      <div className="flex items-center gap-3">
                        <AlertTriangle className="w-7 h-7 text-amber-600 shrink-0" />
                        <div>
                          <span className="text-[10px] font-bold uppercase tracking-wider text-amber-800 bg-amber-100 px-2 py-0.5 rounded">
                            STATUS: CADANGAN (WAITLIST)
                          </span>
                          <h4 className="text-base font-bold text-amber-950 mt-0.5">
                            {checkResult.full_name}
                          </h4>
                          <p className="text-xs text-amber-800 font-mono">
                            No. Pendaftaran: {checkResult.registration_no}
                          </p>
                        </div>
                      </div>
                      <p className="text-xs text-amber-900 leading-relaxed bg-white/70 p-3 rounded-lg border border-amber-200">
                        {checkResult.instruction || 'Anda masuk dalam daftar tunggu (cadangan). Panitia akan menghubungi Anda apabila terdapat kuota yang belum terpenuhi.'}
                      </p>
                    </div>
                  ) : (
                    <div className="rounded-xl border border-gray-300 bg-white p-5 text-slate-900 space-y-3">
                      <div className="flex items-center gap-3">
                        <XCircle className="w-7 h-7 text-gray-500 shrink-0" />
                        <div>
                          <span className="text-[10px] font-bold uppercase tracking-wider text-gray-600 bg-gray-100 px-2 py-0.5 rounded">
                            BELUM LOLOS SELEKSI
                          </span>
                          <h4 className="text-base font-bold text-gray-800 mt-0.5">
                            {checkResult.full_name}
                          </h4>
                          <p className="text-xs text-gray-500 font-mono">
                            No. Pendaftaran: {checkResult.registration_no}
                          </p>
                        </div>
                      </div>
                      <p className="text-xs text-gray-600 leading-relaxed bg-slate-50 p-3 rounded-lg border border-gray-200">
                        {checkResult.instruction || 'Terima kasih atas partisipasi Anda dalam seleksi SPMB SMP Laboratorium UM. Tetap semangat mengukir prestasi!'}
                      </p>
                    </div>
                  )}
                </div>
              )}

              {/* CONTOH / PREVIEW KARTU STATUS SEPERTI DI REFERENSI MOCKUP */}
              {!checkResult && (
                <div className="mt-6 pt-6 border-t border-gray-100 space-y-2.5">
                  <p className="text-[11px] font-bold text-gray-400 uppercase tracking-wider">
                    Contoh Hasil Seleksi
                  </p>
                  
                  <div className="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                    {/* Contoh Diterima */}
                    <div className="p-3 rounded-xl border border-emerald-200 bg-emerald-50/60 flex items-start gap-2">
                      <CheckCircle2 className="w-4 h-4 text-emerald-600 shrink-0 mt-0.5" />
                      <div>
                        <p className="text-xs font-bold text-emerald-900">Diterima</p>
                        <p className="text-[10px] text-emerald-700">Selamat! Anda dinyatakan lulus seleksi.</p>
                        <span className="inline-block mt-1 text-[10px] font-semibold text-emerald-800 bg-emerald-100 px-1.5 py-0.5 rounded">
                          Cetak Bukti Kelulusan &rarr;
                        </span>
                      </div>
                    </div>

                    {/* Contoh Cadangan */}
                    <div className="p-3 rounded-xl border border-amber-200 bg-amber-50/60 flex items-start gap-2">
                      <AlertTriangle className="w-4 h-4 text-amber-600 shrink-0 mt-0.5" />
                      <div>
                        <p className="text-xs font-bold text-amber-900">Cadangan</p>
                        <p className="text-[10px] text-amber-700">Anda masuk daftar cadangan.</p>
                        <span className="inline-block mt-1 text-[10px] font-semibold text-amber-800 hover:underline">
                          Lihat Informasi &rarr;
                        </span>
                      </div>
                    </div>

                    {/* Contoh Belum Lolos */}
                    <div className="p-3 rounded-xl border border-gray-200 bg-slate-50 flex items-start gap-2">
                      <XCircle className="w-4 h-4 text-gray-400 shrink-0 mt-0.5" />
                      <div>
                        <p className="text-xs font-bold text-gray-800">Belum Lolos</p>
                        <p className="text-[10px] text-gray-500">Terima kasih atas partisipasinya.</p>
                      </div>
                    </div>
                  </div>
                </div>
              )}
            </div>

            {/* KOLOM KANAN: BUTUH BANTUAN? HUBUNGI PANITIA PPDB (Span 5 / approx 40%) */}
            <div className="lg:col-span-5 bg-white border border-gray-200/90 rounded-2xl p-6 sm:p-8 shadow-xs flex flex-col justify-between space-y-6">
              <div className="space-y-5">
                <div>
                  <h3 className="font-pub-heading text-xl sm:text-2xl font-bold text-[#062940] leading-snug">
                    Butuh Bantuan?
                    <br />
                    Hubungi Panitia PPDB
                  </h3>
                </div>

                <div className="space-y-4 text-xs">
                  {/* Alamat Sekretariat */}
                  <div className="flex items-start gap-3">
                    <div className="w-8 h-8 rounded-lg bg-[#EBF3F8] text-[#0A3D62] flex items-center justify-center shrink-0 mt-0.5">
                      <MapPin className="w-4 h-4" aria-hidden="true" />
                    </div>
                    <div>
                      <p className="font-bold text-gray-800">Alamat Sekretariat</p>
                      <p className="text-gray-500 leading-relaxed mt-0.5">
                        Jl. Simpang Bogor No. T-7, Sumbersari, Kec. Lowokwaru, Kota Malang, Jawa Timur 65145
                      </p>
                    </div>
                  </div>

                  {/* Jam Layanan */}
                  <div className="flex items-start gap-3">
                    <div className="w-8 h-8 rounded-lg bg-[#EBF3F8] text-[#0A3D62] flex items-center justify-center shrink-0 mt-0.5">
                      <Clock className="w-4 h-4" aria-hidden="true" />
                    </div>
                    <div>
                      <p className="font-bold text-gray-800">Jam Layanan</p>
                      <p className="text-gray-500 mt-0.5">Senin – Jumat 07.00 – 15.30 WIB</p>
                      <p className="text-gray-500">Sabtu 07.30 – 12.00 WIB</p>
                    </div>
                  </div>
                </div>
              </div>

              {/* Tombol WhatsApp Hijau Lebar */}
              <div>
                <a
                  href="https://wa.me/6285126088473"
                  target="_blank"
                  rel="noopener noreferrer"
                  className="w-full inline-flex items-center justify-center gap-3 bg-[#1E7A46] hover:bg-[#166037] text-white p-3.5 rounded-xl shadow-xs transition-all focus:outline-none focus:ring-2 focus:ring-[#1E7A46]"
                >
                  <Phone className="w-5 h-5 shrink-0" aria-hidden="true" />
                  <div className="text-left">
                    <span className="block font-bold text-xs sm:text-sm leading-tight">
                      Chat WhatsApp Panitia PPDB &rarr;
                    </span>
                    <span className="block text-[11px] text-white/90 font-mono font-medium">
                      0851-2608-8473
                    </span>
                  </div>
                </a>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* =========================================================================
          6. CALL TO ACTION PRE-FOOTER (PERSIS REFERENSI UI)
          ========================================================================= */}
      <section className="relative overflow-hidden bg-[#062940] text-white py-12 sm:py-14 w-full border-t border-white/10">
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
                Tim kami siap membantu Anda mendapatkan informasi yang dibutuhkan.
              </p>
            </div>

            <div className="flex flex-col sm:flex-row items-center gap-3 shrink-0 w-full sm:w-auto">
              <Link
                href="/ppdb/daftar"
                className="w-full sm:w-auto text-center bg-[#C89332] hover:bg-[#B57C23] text-white font-bold text-xs sm:text-sm px-6 py-3 rounded-lg shadow-sm hover:shadow transition-all inline-flex items-center justify-center gap-2 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-[#062940]"
              >
                <span>PPDB Online</span>
                <ArrowRight className="w-4 h-4" aria-hidden="true" />
              </Link>
              <Link
                href="/kontak"
                className="w-full sm:w-auto text-center inline-flex items-center justify-center gap-2 bg-[#062940] hover:bg-white/10 border border-white/40 hover:border-white text-white font-semibold text-xs sm:text-sm px-5 py-3 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-[#C89332]"
              >
                <span>Hubungi Kami</span>
                <ArrowRight className="w-4 h-4" aria-hidden="true" />
              </Link>
            </div>
          </div>
        </div>
      </section>
    </main>
  );
}
