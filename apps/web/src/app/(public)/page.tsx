'use client';

import React from 'react';
import Link from 'next/link';
import Image from 'next/image';
import { useQuery } from '@tanstack/react-query';
import {
  ArrowRight,
  BookOpen,
  Globe,
  BookCheck,
  HeartHandshake,
  Compass,
  Shield,
  Users,
  Phone,
  Mail,
  MapPin,
  Calendar,
  AlertCircle,
  RotateCw,
  ChevronRight,
  Quote,
  Sparkles,
} from 'lucide-react';
import {
  FlaticonMedal,
  FlaticonBook,
  FlaticonGlobe,
  FlaticonQuran,
  FlaticonInclusion,
  FlaticonUniversity,
  FlaticonRosette,
  FlaticonCompass,
  FlaticonBuilding,
  FlaticonTrophy,
  FlaticonGraduationCap,
} from '@/components/ui/FlaticonIcons';
import { publicApi } from '@/features/public-site/public-api';
import {
  HERO_CONTENT,
  PROFILE_SUMMARY,
  EDUCATIONAL_PROGRAMS,
  APPROACH_PILLARS,
  PPDB_BANNER_CONTENT,
} from '@/features/public-site/content/homepage';
import { SCHOOL_CONTACT_INFO } from '@/features/public-site/content/navigation';
import { formatPhoneLink, formatWaLink } from '@/features/public-site/utils';
import { formatTanggalIndo } from '@/lib/utils';

export default function HomePage() {
  // Query 3 publikasi berita terbaru dari backend
  const {
    data: posts,
    isLoading: isLoadingPosts,
    isError: isPostsError,
    refetch: refetchPosts,
  } = useQuery({
    queryKey: ['public-latest-posts'],
    queryFn: () => publicApi.getLatestPosts(3),
    staleTime: 5 * 60 * 1000,
  });

  // Query gelombang aktif PPDB
  const { data: activeWave } = useQuery({
    queryKey: ['public-active-wave'],
    queryFn: () => publicApi.getActiveWave(),
    staleTime: 5 * 60 * 1000,
  });

  // Query profil situs dan pengaturan publik (BE-CMS-01)
  const { data: siteData } = useQuery({
    queryKey: ['public-site-settings'],
    queryFn: () => publicApi.getPublicSite(),
    staleTime: 5 * 60 * 1000,
  });

  // Derived dynamic values with robust fallbacks
  const tagline = siteData?.identity?.tagline || HERO_CONTENT.tagline;
  const schoolName = siteData?.identity?.name || HERO_CONTENT.badgeSchool;
  const accreditationGrade = siteData?.profile?.accreditation?.grade;
  const accreditationBadge = accreditationGrade
    ? `Terakreditasi ${accreditationGrade} (Unggul)`
    : HERO_CONTENT.accreditationBadge;

  const principalName = 'Tamyis Suliantoro, M.Pd.';
  const principalQuote =
    'Kami berkomitmen untuk menghadirkan pendidikan yang tidak hanya unggul dalam prestasi, tetapi juga membentuk karakter mulia, mandiri, dan peduli lingkungan.';
  const logoUrl = siteData?.identity?.logo_url || '/images/logo.png';

  const phoneDisplay = siteData?.contact?.phone || SCHOOL_CONTACT_INFO.phone.display;
  const phoneTel = `tel:${formatPhoneLink(phoneDisplay)}`;

  const waDisplay = siteData?.contact?.whatsapp || SCHOOL_CONTACT_INFO.whatsapp.display;
  const waUrl = `https://wa.me/${formatWaLink(waDisplay)}`;

  return (
    <main id="main-content" tabIndex={-1} className="flex-1 flex flex-col focus:outline-none">
      {/* =========================================================================
          1. HERO SECTION & PITA KREDENSIAL (SESUAI MOCKUP ACADEMIC BOARD)
          ========================================================================= */}
      {/* =========================================================================
          1. HERO SECTION & PITA KREDENSIAL (PERSIS SESUAI MOCKUP ACADEMIC BOARD)
          ========================================================================= */}
      <section
        aria-labelledby="hero-heading"
        className="relative min-h-[600px] lg:min-h-[660px] flex items-center py-20 overflow-hidden bg-pub-navy-deep text-white"
      >
        {/* Background Poster & Video Overlay */}
        <div className="absolute inset-0 w-full h-full pointer-events-none overflow-hidden z-0 bg-pub-navy-deep">
          <div
            className="absolute inset-0 bg-cover bg-center transition-opacity duration-1000"
            style={{ backgroundImage: `url('${HERO_CONTENT.videoThumbnail}')` }}
          />
          <iframe
            className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[100vw] h-[56.25vw] min-h-screen min-w-[177.77vh] pointer-events-none border-0 opacity-100"
            src={HERO_CONTENT.youtubeEmbedUrl}
            title="Video Profil SMP Laboratorium"
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
            loading="eager"
          />
          <div className="absolute inset-0 z-10 bg-gradient-to-r from-pub-navy-deep/90 via-pub-navy-deep/60 to-black/40 pointer-events-none" />
          <div className="absolute inset-0 z-10 bg-black/20 pointer-events-none" />
        </div>

        <div className="relative z-20 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full">
          <div className="max-w-3xl space-y-6">
            {/* Badges Afiliasi & Akreditasi */}
            <div className="flex flex-wrap items-center gap-2.5">
              <span className="inline-flex items-center gap-1.5 bg-pub-gold text-pub-navy-deep font-extrabold text-xs px-3.5 py-1.5 rounded-full uppercase tracking-wider shadow-sm">
                <Sparkles className="w-3.5 h-3.5" aria-hidden="true" />
                {tagline}
              </span>
              <span className="bg-white/15 backdrop-blur-sm text-white text-xs font-semibold px-3 py-1.5 rounded-full border border-white/20">
                {HERO_CONTENT.badgeAffiliation}
              </span>
              <span className="bg-pub-green/80 text-white text-xs font-semibold px-3 py-1.5 rounded-full border border-pub-green/40">
                {accreditationBadge}
              </span>
            </div>

            {/* Headline Utama */}
            <h1
              id="hero-heading"
              className="font-pub-heading text-3xl sm:text-4xl lg:text-5xl font-bold leading-tight text-white drop-shadow-md tracking-tight"
            >
              Mewujudkan Generasi <span className="text-pub-gold">Beriman, Berkarakter</span>, Berprestasi, dan Berbudaya
            </h1>

            {/* Deskripsi Singkat */}
            <p className="text-base sm:text-lg text-white/90 leading-relaxed max-w-2xl drop-shadow">
              {HERO_CONTENT.description}
            </p>

            {/* Tombol Call to Action */}
            <div className="pt-2 flex flex-wrap items-center gap-3.5">
              <Link
                href={HERO_CONTENT.ctaPrimary.href}
                className="bg-pub-gold hover:bg-pub-gold-hover text-pub-navy-deep font-bold text-sm sm:text-base px-6 py-3.5 rounded-[10px] shadow-lg hover:shadow-xl transition-all inline-flex items-center gap-2 focus:outline-none focus:ring-2 focus:ring-pub-gold focus:ring-offset-2 focus:ring-offset-pub-navy"
              >
                <span>{HERO_CONTENT.ctaPrimary.label}</span>
                <ArrowRight className="w-4 h-4" aria-hidden="true" />
              </Link>
              <Link
                href={HERO_CONTENT.ctaSecondary.href}
                className="bg-transparent hover:bg-white/15 text-white border border-white/50 hover:border-white font-semibold text-sm sm:text-base px-6 py-3.5 rounded-[10px] transition-all backdrop-blur-sm inline-flex items-center gap-2 focus:outline-none focus:ring-2 focus:ring-pub-gold focus:ring-offset-2 focus:ring-offset-pub-navy"
              >
                <span>{HERO_CONTENT.ctaSecondary.label}</span>
              </Link>
            </div>
          </div>

          {/* Konten Teks Hero (Mobile & Desktop) */}
          <div className="relative z-10 px-4 sm:px-8 lg:px-12 xl:px-16 pt-8 sm:pt-12 pb-6 lg:py-16 w-full lg:w-[54%] xl:w-[50%]">
            <div className="max-w-xl lg:max-w-[560px] space-y-5 sm:space-y-6">
              {/* Overline / Motto Resmi */}
              <div className="space-y-2 sm:space-y-3">
                <p className="text-xs sm:text-sm font-semibold text-[#4A6B82] tracking-normal">
                  {tagline}
                </p>

                {/* Headline Utama Sesuai Mockup Mobile & Desktop */}
                <h1
                  id="hero-heading"
                  className="font-pub-heading text-2xl sm:text-3xl lg:text-[44px] font-bold leading-[1.22] text-[#0A3D62] tracking-tight"
                >
                  Sekolah Laboratorium<br className="hidden sm:inline" />{' '}
                  Model Riset Pembelajaran<br className="hidden sm:inline" />{' '}
                  di Bawah Naungan P2LP UM
                </h1>
              </div>

              {/* Blok Kredensial (Sesuai Mockup Mobile: Hanya Terakreditasi A yang tampil di mobile) */}
              <div className="flex flex-wrap items-center gap-4 sm:gap-7 py-1 sm:py-2.5">
                {/* Blok 1: Akreditasi A dengan Flaticon Medal Emas */}
                <div className="flex items-center gap-3">
                  <FlaticonMedal size={44} className="shrink-0 drop-shadow-xs" />
                  <div>
                    <p className="text-sm sm:text-base font-bold text-[#0A3D62] leading-tight">
                      Terakreditasi A
                    </p>
                    <p className="text-xs sm:text-sm text-gray-600 font-medium mt-0.5 sm:mt-1">
                      (Unggul)
                    </p>
                  </div>
                </div>

                {/* Divider Pemisah Lebih Tinggi */}
                <div className="hidden sm:block h-10 sm:h-12 w-px bg-gray-300" aria-hidden="true" />

                {/* Blok 2: Sejarah & Naungan (Hanya di Tablet & Desktop agar mobile ringkas sesuai mockup) */}
                <div className="hidden sm:block">
                  <p className="text-sm sm:text-base font-bold text-[#0A3D62] leading-tight">
                    Sejak 1990
                  </p>
                  <p className="text-xs sm:text-sm text-gray-600 font-medium leading-snug mt-1">
                    Bertransformasi di bawah P2LP UM sejak 2009
                  </p>
                </div>
              </div>

              {/* Tombol Call to Action (Side-by-side di Mobile Sesuai Mockup) */}
              <div className="pt-1 sm:pt-2 grid grid-cols-2 gap-3 w-full sm:flex sm:w-auto sm:flex-wrap sm:gap-3.5">
                <Link
                  href="/ppdb"
                  className="bg-[#C89332] hover:bg-[#B57C23] text-white font-bold text-xs sm:text-base py-2.5 sm:py-3 px-3 sm:px-6 rounded-lg shadow-xs hover:shadow transition-all inline-flex items-center justify-center gap-1.5 sm:gap-2 focus:outline-none focus:ring-2 focus:ring-[#C89332] focus:ring-offset-2"
                >
                  <span>PPDB Online</span>
                  <ArrowRight className="w-3.5 h-3.5 sm:w-4 sm:h-4 shrink-0" aria-hidden="true" />
                </Link>
                <Link
                  href="/profil"
                  className="bg-white hover:bg-gray-50 text-[#0A3D62] border border-[#0A3D62]/40 hover:border-[#0A3D62] font-semibold text-xs sm:text-base py-2.5 sm:py-3 px-3 sm:px-6 rounded-lg shadow-xs transition-colors inline-flex items-center justify-center focus:outline-none focus:ring-2 focus:ring-pub-gold focus:ring-offset-2"
                >
                  <span>Kenali Sekolah Kami</span>
                </Link>
              </div>
            </div>
          </div>

          {/* Mobile / Tablet Image: DILUAR padding container teks! Membentang 100% selebar layar tanpa celah samping! */}
          <div className="lg:hidden relative aspect-[4/3] sm:aspect-[16/9] w-full overflow-hidden bg-slate-100">
            <Image
              src="/images/hero-academic.jpg"
              alt="Siswa SMP Laboratorium UM dalam kegiatan riset pembelajaran di laboratorium"
              fill
              priority
              unoptimized
              sizes="100vw"
              className="object-cover object-[58%_30%]"
            />
            {/* Gradasi lembut di bagian atas foto menyatu dengan latar putih tombol */}
            <div
              className="absolute inset-x-0 top-0 h-10 sm:h-14 bg-gradient-to-b from-white via-white/40 to-transparent"
              aria-hidden="true"
            />
          </div>
        </div>
      </section>

      {/* Pita Kredensial Solid Navy Resmi (Persis Sesuai Mockup - Responsif di Semua Perangkat) */}
      <div className="bg-[#062940] text-white py-6 sm:py-8 lg:py-10 border-y border-white/10">
        <div className="w-full px-4 sm:px-8 lg:px-12 xl:px-16">
          <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 lg:gap-8 items-center lg:divide-x divide-white/20">
            {/* Item 1: Akreditasi A (Medali Rosette Putih) */}
            <div className="flex items-center gap-2.5 sm:gap-4">
              <svg width="42" height="42" viewBox="0 0 48 48" fill="none" className="w-9 h-9 sm:w-12 sm:h-12 shrink-0 drop-shadow-xs" aria-hidden="true">
                <path d="M16 29L11 43L20 39L24 33" fill="#FFFFFF" fillOpacity="0.9" />
                <path d="M32 29L37 43L28 39L24 33" fill="#FFFFFF" fillOpacity="0.9" />
                <circle cx="24" cy="20" r="14" fill="#FFFFFF" />
                <circle cx="24" cy="20" r="11" fill="#062940" />
                <path d="M24 13L25.8 17.5L30.5 18L27 21.3L28 26L24 23.5L20 26L21 21.3L17.5 18L22.2 17.5L24 13Z" fill="#FFFFFF" />
              </svg>
              <div>
                <p className="text-sm sm:text-base lg:text-[17px] font-bold text-white leading-tight">Akreditasi A</p>
                <p className="text-[11px] sm:text-xs lg:text-sm text-white/80 font-normal mt-0.5">Unggul</p>
              </div>
            </div>

            {/* Item 2: P2LP UM (Gedung Universitas 4 Pilar) */}
            <div className="flex items-center gap-2.5 sm:gap-4 lg:pl-8">
              <svg width="42" height="42" viewBox="0 0 48 48" fill="none" className="w-9 h-9 sm:w-12 sm:h-12 shrink-0 drop-shadow-xs" aria-hidden="true">
                <path d="M24 8L8 18H40L24 8Z" fill="#FFFFFF" />
                <rect x="10" y="18" width="28" height="3" fill="#FFFFFF" />
                <rect x="12" y="22.5" width="4" height="13" rx="0.5" fill="#FFFFFF" />
                <rect x="19" y="22.5" width="4" height="13" rx="0.5" fill="#FFFFFF" />
                <rect x="25" y="22.5" width="4" height="13" rx="0.5" fill="#FFFFFF" />
                <rect x="32" y="22.5" width="4" height="13" rx="0.5" fill="#FFFFFF" />
                <rect x="8" y="37" width="32" height="3.5" rx="0.5" fill="#FFFFFF" />
              </svg>
              <div>
                <p className="text-sm sm:text-base lg:text-[17px] font-bold text-white leading-tight">P2LP UM</p>
                <p className="text-[11px] sm:text-xs lg:text-sm text-white/80 font-normal mt-0.5">Lembaga Pembina</p>
              </div>
            </div>

            {/* Item 3: Cambridge (Bola Dunia Latitude Longitude) */}
            <div className="flex items-center gap-2.5 sm:gap-4 lg:pl-8">
              <svg width="42" height="42" viewBox="0 0 48 48" fill="none" stroke="#FFFFFF" strokeWidth="2.5" className="w-9 h-9 sm:w-12 sm:h-12 shrink-0 drop-shadow-xs" aria-hidden="true">
                <circle cx="24" cy="24" r="17" />
                <ellipse cx="24" cy="24" rx="8" ry="17" />
                <line x1="7" y1="24" x2="41" y2="24" />
              </svg>
              <div>
                <p className="text-sm sm:text-base lg:text-[17px] font-bold text-white leading-tight">Cambridge</p>
                <p className="text-[11px] sm:text-xs lg:text-sm text-white/80 font-normal mt-0.5">International Class</p>
              </div>
            </div>

            {/* Item 4: Motto Sekolah (Ikon Daun Solid Putih Sesuai Mockup) */}
            <div className="flex items-center gap-2.5 sm:gap-4 lg:pl-8">
              <svg width="42" height="42" viewBox="0 0 24 24" fill="#FFFFFF" className="w-9 h-9 sm:w-12 sm:h-12 shrink-0 drop-shadow-xs" aria-hidden="true">
                <path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z" />
                <path d="M2 21c0-3 1.85-5.36 5.08-6C9.5 14.52 12 13 13 12" stroke="#062940" strokeWidth="1.5" strokeLinecap="round" />
              </svg>
              <div>
                <p className="text-sm sm:text-base lg:text-[17px] font-bold text-white leading-tight">Beradab, Kreatif,</p>
                <p className="text-sm sm:text-base lg:text-[17px] font-bold text-white leading-tight mt-0.5">Berprestasi</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* =========================================================================
          2. TENTANG KAMI & SAMBUTAN KEPALA SEKOLAH (SESUAI MOCKUP)
          ========================================================================= */}
      <section aria-labelledby="profile-heading" className="py-10 sm:py-16 lg:py-20 bg-white">
        <div className="w-full px-4 sm:px-8 lg:px-12 xl:px-16">
          <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-12 items-center">
            {/* Kolom Kiri: Narasi Identitas & Pendekatan Riset Sekolah (6 Kolom) */}
            <div className="lg:col-span-6 space-y-5">
              <div className="space-y-2">
                <span className="text-xs font-bold uppercase tracking-wider text-[#4A6B82] block">
                  TENTANG KAMI
                </span>
                <h2
                  id="profile-heading"
                  className="font-pub-heading text-2xl sm:text-3xl lg:text-[2.25rem] font-bold text-[#062940] leading-[1.25]"
                >
                  Sekolah Riset Inovasi<br className="hidden sm:inline" />
                  Universitas Negeri Malang
                </h2>
              </div>
              <p className="text-gray-600 text-sm sm:text-base leading-relaxed">
                SMP Laboratorium adalah sekolah menengah pertama yang berfungsi sebagai sekolah laboratorium model riset
                pembelajaran, tempat penerapan inovasi pedagogi (Lesson Study), uji coba modul ajar adaptif oleh akademisi UM,
                dan sekolah percontohan inklusi ramah anak di Kota Malang.
              </p>
              <div className="pt-2">
                <Link
                  href="/profil"
                  className="bg-white hover:bg-gray-50 border border-gray-300 text-[#062940] hover:text-[#C89332] hover:border-[#C89332] px-5 py-2.5 rounded-lg text-sm font-semibold transition-all inline-flex items-center gap-2 focus:outline-none focus:ring-2 focus:ring-[#C89332]"
                >
                  <span>Selengkapnya</span>
                  <span aria-hidden="true">→</span>
                </Link>
              </div>
            </div>

            {/* Kolom Kanan: Kartu Sambutan Kepala Sekolah Elegan & Proporsional (6 Kolom) */}
            <div className="lg:col-span-6">
              <div className="relative overflow-hidden bg-white border border-slate-200/90 rounded-2xl p-6 sm:p-7 shadow-sm hover:shadow-md transition-shadow">
                <div className="flex flex-col sm:flex-row gap-5 sm:gap-6 items-center sm:items-start">
                  {/* Foto Kepala Sekolah Portrait Proporsional */}
                  <div className="relative w-36 sm:w-40 aspect-[3/4] rounded-xl overflow-hidden shrink-0 shadow-xs border border-slate-100 bg-slate-100">
                    <Image
                      src="/images/principal.jpg"
                      alt={principalName}
                      fill
                      className="object-cover object-top"
                      sizes="(max-width: 640px) 144px, 160px"
                    />
                  </div>

                  {/* Sambutan & Profil */}
                  <div className="flex-1 space-y-3 text-left">
                    <div className="flex items-center gap-2 text-[#C89332]">
                      <Quote className="w-4 h-4 fill-[#C89332]/20 text-[#C89332] shrink-0" aria-hidden="true" />
                      <span className="text-[11px] font-bold uppercase tracking-wider text-[#4A6B82]">
                        Sambutan Resmi
                      </span>
                    </div>

                    <h3 className="font-pub-heading text-base sm:text-lg font-bold text-[#062940] leading-tight">
                      Sambutan Kepala Sekolah
                    </h3>

                    <blockquote className="text-xs sm:text-[13px] text-gray-700 leading-relaxed font-normal italic border-l-2 border-[#C89332]/40 pl-3 my-2.5">
                      &ldquo;{principalQuote}&rdquo;
                    </blockquote>

                    <div className="pt-2 border-t border-gray-100">
                      <p className="font-bold text-sm text-[#062940] leading-tight">
                        {principalName}
                      </p>
                      <p className="text-xs text-gray-500 mt-0.5">
                        Kepala SMP Laboratorium UM
                      </p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          {/* Baris Statistik Kunci Institusi (Sesuai Data Terverifikasi & PRD WEB-HOME-01) */}
          <div className="mt-12 lg:mt-16 pt-8 lg:pt-12 border-t border-gray-100">
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
              {/* Card 1: 1990 */}
              <div className="p-5 sm:p-6 rounded-2xl bg-white border border-slate-200/80 shadow-xs hover:shadow-md hover:border-[#C89332]/40 transition-all duration-200 flex flex-col justify-between group">
                <div className="flex items-center justify-between gap-3 mb-4">
                  <span className="text-2xl sm:text-3xl font-extrabold text-[#062940] tracking-tight">
                    1990
                  </span>
                  <FlaticonBuilding size={42} className="shrink-0 drop-shadow-2xs" />
                </div>
                <div>
                  <p className="text-sm font-bold text-[#062940]">Tahun Berdiri</p>
                  <p className="text-xs text-gray-500 mt-1 leading-relaxed">
                    36+ Tahun Tradisi Pendidikan di Malang
                  </p>
                </div>
              </div>

              {/* Card 2: Nilai 96 */}
              <div className="p-5 sm:p-6 rounded-2xl bg-white border border-slate-200/80 shadow-xs hover:shadow-md hover:border-[#C89332]/40 transition-all duration-200 flex flex-col justify-between group">
                <div className="flex items-center justify-between gap-3 mb-4">
                  <span className="text-2xl sm:text-3xl font-extrabold text-[#062940] tracking-tight">
                    Nilai 96
                  </span>
                  <FlaticonMedal size={44} className="shrink-0 drop-shadow-2xs" />
                </div>
                <div>
                  <p className="text-sm font-bold text-[#062940]">Akreditasi A (Unggul)</p>
                  <p className="text-xs text-gray-500 mt-1 leading-relaxed">
                    Sertifikat BAN-S/M Berlaku s.d. 2027
                  </p>
                </div>
              </div>

              {/* Card 3: 24 Cabang */}
              <div className="p-5 sm:p-6 rounded-2xl bg-white border border-slate-200/80 shadow-xs hover:shadow-md hover:border-[#C89332]/40 transition-all duration-200 flex flex-col justify-between group">
                <div className="flex items-center justify-between gap-3 mb-4">
                  <span className="text-2xl sm:text-3xl font-extrabold text-[#062940] tracking-tight">
                    24 Cabang
                  </span>
                  <FlaticonTrophy size={42} className="shrink-0 drop-shadow-2xs" />
                </div>
                <div>
                  <p className="text-sm font-bold text-[#062940]">Ekstrakurikuler</p>
                  <p className="text-xs text-gray-500 mt-1 leading-relaxed">
                    Wadah Riset KIR, Seni, Olahraga &amp; ARSIMBO
                  </p>
                </div>
              </div>

              {/* Card 4: 4 Program */}
              <div className="p-5 sm:p-6 rounded-2xl bg-white border border-slate-200/80 shadow-xs hover:shadow-md hover:border-[#C89332]/40 transition-all duration-200 flex flex-col justify-between group">
                <div className="flex items-center justify-between gap-3 mb-4">
                  <span className="text-2xl sm:text-3xl font-extrabold text-[#062940] tracking-tight">
                    4 Program
                  </span>
                  <FlaticonGraduationCap size={42} className="shrink-0 drop-shadow-2xs" />
                </div>
                <div>
                  <p className="text-sm font-bold text-[#062940]">Pilihan Akademik</p>
                  <p className="text-xs text-gray-500 mt-1 leading-relaxed">
                    Reguler, ICP Cambridge, Tahfidz, Inklusi
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* =========================================================================
          3. PROGRAM UNGGULAN (DENGAN FLATICON STYLE MONOKROM EMAS SESUAI GAMBAR 1)
          ========================================================================= */}
      <section aria-labelledby="programs-heading" className="py-16 sm:py-20 bg-pub-bg-app border-y border-pub-border">
        <div className="w-full px-4 sm:px-8 lg:px-12 xl:px-16">
          <div className="mb-10 space-y-1.5">
            <span className="text-xs font-bold uppercase tracking-wider text-[#4A6B82] block">
              PROGRAM UNGGULAN
            </span>
            <h2
              id="programs-heading"
              className="font-pub-heading text-2xl sm:text-3xl font-bold text-[#0A3D62]"
            >
              Pendidikan Berkualitas untuk Masa Depan
            </h2>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            {/* Card 1: Program Reguler */}
            <article className="bg-white border border-gray-100 rounded-2xl p-6 sm:p-7 shadow-xs hover:shadow-md transition-shadow flex flex-col justify-between group">
              <div className="space-y-4">
                <FlaticonBook size={52} className="shrink-0" />
                <div className="space-y-1">
                  <h3 className="font-pub-heading font-bold text-lg text-[#0A3D62] group-hover:text-[#C89332] transition-colors leading-tight">
                    Program Reguler
                  </h3>
                  <p className="text-xs font-semibold text-[#0A3D62]/90">
                    (Kurikulum Merdeka SNP)
                  </p>
                </div>
                <p className="text-xs sm:text-[13px] text-gray-600 leading-relaxed">
                  Mastery Learning (belajar tuntas) dengan modul adaptif dan rasio kelas ideal.
                </p>
              </div>

              <div className="pt-6">
                <Link
                  href="/akademik"
                  className="inline-flex items-center gap-1.5 text-xs sm:text-sm font-semibold text-[#0A3D62] hover:text-[#C89332] transition-colors focus:outline-none focus:underline"
                >
                  <span>Selengkapnya</span>
                  <span aria-hidden="true">→</span>
                </Link>
              </div>
            </article>

            {/* Card 2: ICP */}
            <article className="bg-white border border-gray-100 rounded-2xl p-6 sm:p-7 shadow-xs hover:shadow-md transition-shadow flex flex-col justify-between group">
              <div className="space-y-4">
                <FlaticonGlobe size={52} className="shrink-0" />
                <div className="space-y-1">
                  <h3 className="font-pub-heading font-bold text-lg text-[#0A3D62] group-hover:text-[#C89332] transition-colors leading-tight">
                    ICP
                  </h3>
                  <p className="text-xs font-semibold text-[#0A3D62]/90">
                    (International Class Program)
                  </p>
                </div>
                <p className="text-xs sm:text-[13px] text-gray-600 leading-relaxed">
                  Integrasi Cambridge Lower Secondary dengan lingkungan bilingual.
                </p>
              </div>

              <div className="pt-6">
                <Link
                  href="/akademik"
                  className="inline-flex items-center gap-1.5 text-xs sm:text-sm font-semibold text-[#0A3D62] hover:text-[#C89332] transition-colors focus:outline-none focus:underline"
                >
                  <span>Selengkapnya</span>
                  <span aria-hidden="true">→</span>
                </Link>
              </div>
            </article>

            {/* Card 3: Tahfidz Al-Qur'an */}
            <article className="bg-white border border-gray-100 rounded-2xl p-6 sm:p-7 shadow-xs hover:shadow-md transition-shadow flex flex-col justify-between group">
              <div className="space-y-4">
                <FlaticonQuran size={52} className="shrink-0" />
                <div className="space-y-1">
                  <h3 className="font-pub-heading font-bold text-lg text-[#0A3D62] group-hover:text-[#C89332] transition-colors leading-tight">
                    Tahfidz Al-Qur&apos;an
                  </h3>
                  <p className="text-xs font-semibold text-transparent select-none">
                    &nbsp;
                  </p>
                </div>
                <p className="text-xs sm:text-[13px] text-gray-600 leading-relaxed">
                  Metode Ummi, target hafalan bertahap, dan penguatan adab ibadah di Masjid Baitul &apos;Ilmi.
                </p>
              </div>

              <div className="pt-6">
                <Link
                  href="/akademik"
                  className="inline-flex items-center gap-1.5 text-xs sm:text-sm font-semibold text-[#0A3D62] hover:text-[#C89332] transition-colors focus:outline-none focus:underline"
                >
                  <span>Selengkapnya</span>
                  <span aria-hidden="true">→</span>
                </Link>
              </div>
            </article>

            {/* Card 4: Pendidikan Inklusi */}
            <article className="bg-white border border-gray-100 rounded-2xl p-6 sm:p-7 shadow-xs hover:shadow-md transition-shadow flex flex-col justify-between group">
              <div className="space-y-4">
                <FlaticonInclusion size={52} className="shrink-0" />
                <div className="space-y-1">
                  <h3 className="font-pub-heading font-bold text-lg text-[#0A3D62] group-hover:text-[#C89332] transition-colors leading-tight">
                    Pendidikan Inklusi
                  </h3>
                  <p className="text-xs font-semibold text-[#0A3D62]/90">
                    (Ramah Anak)
                  </p>
                </div>
                <p className="text-xs sm:text-[13px] text-gray-600 leading-relaxed">
                  Layanan adaptif untuk PDBK dengan Program Pembelajaran Individual (PPI).
                </p>
              </div>

              <div className="pt-6">
                <Link
                  href="/akademik"
                  className="inline-flex items-center gap-1.5 text-xs sm:text-sm font-semibold text-[#0A3D62] hover:text-[#C89332] transition-colors focus:outline-none focus:underline"
                >
                  <span>Selengkapnya</span>
                  <span aria-hidden="true">→</span>
                </Link>
              </div>
            </article>
          </div>
        </div>
      </section>

      {/* =========================================================================
          4. KEUNGGULAN SEKOLAH (SESUAI MOCKUP ACADEMIC BOARD)
          ========================================================================= */}
      <section aria-labelledby="approach-heading" className="py-16 sm:py-20 bg-white">
        <div className="w-full px-4 sm:px-8 lg:px-12 xl:px-16">
          <div className="mb-10 space-y-1.5">
            <span className="text-xs font-bold uppercase tracking-wider text-[#4A6B82] block">
              KEUNGGULAN SEKOLAH
            </span>
            <h2
              id="approach-heading"
              className="font-pub-heading text-2xl sm:text-3xl font-bold text-[#0A3D62]"
            >
              Lebih dari Sekadar Akademik
            </h2>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            {/* Card 1: Binaan Dosen UM */}
            <div className="bg-white border border-gray-100 rounded-2xl p-4 sm:p-5 shadow-xs hover:shadow-md transition-shadow flex flex-col justify-between">
              <div>
                <div className="relative aspect-[4/3] w-full rounded-xl overflow-hidden bg-slate-100 mb-4">
                  <Image
                    src="/images/keunggulan-01.jpg"
                    alt="Binaan Dosen UM"
                    fill
                    className="object-cover object-center"
                    sizes="(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 25vw"
                  />
                </div>
                <span className="text-xs font-bold text-[#7A92A5] tracking-wider block mb-1">
                  01
                </span>
                <h3 className="font-pub-heading font-bold text-base sm:text-lg text-[#0A3D62] leading-snug mb-1.5">
                  Binaan Dosen UM
                </h3>
                <p className="text-xs sm:text-[13px] text-gray-600 leading-relaxed">
                  Pembelajaran didampingi akademisi Universitas Negeri Malang.
                </p>
              </div>
            </div>

            {/* Card 2: Karakter Wajib */}
            <div className="bg-white border border-gray-100 rounded-2xl p-4 sm:p-5 shadow-xs hover:shadow-md transition-shadow flex flex-col justify-between">
              <div>
                <div className="relative aspect-[4/3] w-full rounded-xl overflow-hidden bg-slate-100 mb-4">
                  <Image
                    src="/images/keunggulan-02.jpg"
                    alt="Karakter Wajib"
                    fill
                    className="object-cover object-center"
                    sizes="(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 25vw"
                  />
                </div>
                <span className="text-xs font-bold text-[#7A92A5] tracking-wider block mb-1">
                  02
                </span>
                <h3 className="font-pub-heading font-bold text-base sm:text-lg text-[#0A3D62] leading-snug mb-1.5">
                  Karakter Wajib
                </h3>
                <p className="text-xs sm:text-[13px] text-gray-600 leading-relaxed">
                  Life Skills (Kelas VII), Pramuka ARSIMBO, dan TaBeQu (Kelas VIII).
                </p>
              </div>
            </div>

            {/* Card 3: 24 Ekskul & Prestasi */}
            <div className="bg-white border border-gray-100 rounded-2xl p-4 sm:p-5 shadow-xs hover:shadow-md transition-shadow flex flex-col justify-between">
              <div>
                <div className="relative aspect-[4/3] w-full rounded-xl overflow-hidden bg-slate-100 mb-4">
                  <Image
                    src="/images/keunggulan-03.jpg"
                    alt="24 Ekskul & Prestasi"
                    fill
                    className="object-cover object-center"
                    sizes="(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 25vw"
                  />
                </div>
                <span className="text-xs font-bold text-[#7A92A5] tracking-wider block mb-1">
                  03
                </span>
                <h3 className="font-pub-heading font-bold text-base sm:text-lg text-[#0A3D62] leading-snug mb-1.5">
                  24 Ekskul &amp; Prestasi
                </h3>
                <p className="text-xs sm:text-[13px] text-gray-600 leading-relaxed">
                  Riset, bahasa, seni, olahraga dan kepemimpinan.
                </p>
              </div>
            </div>

            {/* Card 4: Sarana & Fasilitas */}
            <div className="bg-white border border-gray-100 rounded-2xl p-4 sm:p-5 shadow-xs hover:shadow-md transition-shadow flex flex-col justify-between">
              <div>
                <div className="relative aspect-[4/3] w-full rounded-xl overflow-hidden bg-slate-100 mb-4">
                  <Image
                    src="/images/keunggulan-04.jpg"
                    alt="Sarana & Fasilitas"
                    fill
                    className="object-cover object-center"
                    sizes="(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 25vw"
                  />
                </div>
                <span className="text-xs font-bold text-[#7A92A5] tracking-wider block mb-1">
                  04
                </span>
                <h3 className="font-pub-heading font-bold text-base sm:text-lg text-[#0A3D62] leading-snug mb-1.5">
                  Sarana &amp; Fasilitas
                </h3>
                <p className="text-xs sm:text-[13px] text-gray-600 leading-relaxed">
                  Lingkungan belajar modern, lengkap dan nyaman.
                </p>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* =========================================================================
          5. WARTA & BERITA TERBARU (SESUAI MOCKUP)
          ========================================================================= */}
      <section aria-labelledby="news-heading" className="py-16 sm:py-20 bg-[#F8FAFC] border-t border-gray-200/60">
        <div className="w-full px-4 sm:px-8 lg:px-12 xl:px-16">
          <div className="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-10">
            <div className="space-y-1">
              <span className="text-xs font-bold uppercase tracking-wider text-[#4A6B82] block">
                WARTA &amp; BERITA TERBARU
              </span>
              <h2
                id="news-heading"
                className="font-pub-heading text-2xl sm:text-3xl font-bold text-[#0A3D62]"
              >
                Kegiatan dan Prestasi Terkini
              </h2>
            </div>
            <Link
              href="/berita"
              className="inline-flex items-center gap-1.5 text-xs sm:text-sm font-semibold text-[#0A3D62] hover:text-[#C89332] transition-colors focus:outline-none focus:underline self-start sm:self-auto"
            >
              <span>Lihat Semua Berita</span>
              <span aria-hidden="true">→</span>
            </Link>
          </div>

          {/* Kondisi 1: Loading Skeleton */}
          {isLoadingPosts && (
            <div className="grid grid-cols-1 md:grid-cols-3 gap-6" aria-busy="true" aria-label="Memuat publikasi berita">
              {[1, 2, 3].map((n) => (
                <div
                  key={n}
                  className="bg-white border border-gray-100 rounded-2xl p-5 shadow-xs space-y-4 animate-pulse"
                >
                  <div className="aspect-[16/10] bg-slate-200 rounded-xl w-full" />
                  <div className="h-4 bg-slate-200 rounded w-1/3" />
                  <div className="h-5 bg-slate-200 rounded w-3/4" />
                </div>
              ))}
            </div>
          )}

          {/* Kondisi 2: Error State */}
          {!isLoadingPosts && isPostsError && (
            <div className="p-6 rounded-xl bg-white border border-red-200 text-center max-w-lg mx-auto space-y-3">
              <AlertCircle className="w-8 h-8 text-red-500 mx-auto" aria-hidden="true" />
              <p className="text-sm font-semibold text-slate-800">
                Tidak dapat memuat publikasi berita saat ini.
              </p>
              <button
                type="button"
                onClick={() => refetchPosts()}
                className="inline-flex items-center gap-2 bg-[#0A3D62] text-white text-xs font-semibold px-4 py-2 rounded-lg hover:bg-[#062940] transition-colors focus:outline-none focus:ring-2 focus:ring-[#C89332]"
              >
                <RotateCw className="w-3.5 h-3.5" aria-hidden="true" />
                <span>Coba Lagi</span>
              </button>
            </div>
          )}

          {/* Kondisi 3 & 4: Berita Nyata atau Fallback Tepat Sesuai Mockup */}
          {!isLoadingPosts && !isPostsError && (
            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
              {(posts && posts.length > 0 ? posts : [
                {
                  id: 'fallback-1',
                  title: 'Tim Riset SMP Laboratorium Raih Juara 1 Lomba KIR Tingkat Jawa Timur',
                  published_at: '2025-05-12T08:00:00Z',
                  coverImage: '/images/keunggulan-03.jpg',
                  slug: 'tim-riset-raih-juara-1-kir',
                },
                {
                  id: 'fallback-2',
                  title: 'Kunjungan Akademisi UM dalam Program Lesson Study',
                  published_at: '2025-05-05T08:00:00Z',
                  coverImage: '/images/keunggulan-01.jpg',
                  slug: 'kunjungan-akademisi-um-lesson-study',
                },
                {
                  id: 'fallback-3',
                  title: 'Siswa SMP Lab UM Ikuti Program Pertukaran Pelajar ke Cambridge',
                  published_at: '2025-04-28T08:00:00Z',
                  coverImage: '/images/hero-academic.jpg',
                  slug: 'siswa-pertukaran-pelajar-cambridge',
                },
              ]).map((post, idx) => {
                const coverSrc = ('coverImage' in post && (post as any).coverImage)
                  ? (post as any).coverImage
                  : (post as any).cover?.url || (post as any).cover_file?.url || ['/images/keunggulan-03.jpg', '/images/keunggulan-01.jpg', '/images/hero-academic.jpg'][idx % 3];

                return (
                  <article
                    key={post.id}
                    className="bg-white border border-gray-100 rounded-2xl p-4 sm:p-5 shadow-xs hover:shadow-md transition-shadow flex flex-col justify-between group"
                  >
                    <div>
                      <div className="relative aspect-[16/10] w-full rounded-xl overflow-hidden bg-slate-100 mb-3.5">
                        <Image
                          src={coverSrc}
                          alt={post.title}
                          fill
                          className="object-cover object-center group-hover:scale-105 transition-transform duration-300"
                          sizes="(max-width: 768px) 100vw, 33vw"
                        />
                      </div>
                      <div className="text-xs text-gray-500 font-medium mb-1.5">
                        {formatTanggalIndo(post.published_at || (post as any).created_at)}
                      </div>
                      <h3 className="font-pub-heading font-bold text-base sm:text-lg text-[#0A3D62] group-hover:text-[#C89332] transition-colors line-clamp-2 leading-snug">
                        <Link href="/berita" className="focus:outline-none focus:underline">
                          {post.title}
                        </Link>
                      </h3>
                    </div>

                    <div className="pt-4">
                      <Link
                        href="/berita"
                        className="inline-flex items-center gap-1.5 text-xs sm:text-sm font-semibold text-[#0A3D62] hover:text-[#C89332] transition-colors focus:outline-none focus:underline"
                      >
                        <span>Baca Selengkapnya</span>
                        <span aria-hidden="true">→</span>
                      </Link>
                    </div>
                  </article>
                );
              })}
            </div>
          )}
        </div>
      </section>

      {/* =========================================================================
          6. BANNER CALL TO ACTION PPDB 2026/2027 (FULL SCREEN BLEED SESUAI REFERENSI AWAL)
          ========================================================================= */}
      <section
        aria-labelledby="ppdb-cta-heading"
        className="relative bg-[#062940] overflow-hidden min-h-[380px] lg:min-h-[450px] flex flex-col lg:flex-row items-center border-0"
      >
        {/* Desktop Left Photo with Full Height (Hanya di layar desktop lg:) */}
        <div className="hidden lg:block absolute inset-y-0 left-0 w-[44%] xl:w-[42%] overflow-hidden bg-slate-900">
          <Image
            src="/images/ppdb-students.jpg"
            alt="Siswa SMP Laboratorium UM dalam seragam sekolah"
            fill
            unoptimized
            priority
            sizes="(max-width: 1024px) 100vw, 44vw"
            className="object-cover object-[center_15%]"
          />
        </div>

        {/* Gold Chevron Accent Divider (Hanya di layar desktop lg:) */}
        <svg
          className="hidden lg:block absolute inset-y-0 left-[38%] xl:left-[36.5%] h-full w-24 xl:w-28 z-10 pointer-events-none"
          viewBox="0 0 100 400"
          preserveAspectRatio="none"
          fill="none"
          aria-hidden="true"
        >
          {/* Filler Dark Navy to cover right photo edge */}
          <path d="M40 0 L100 0 L100 400 L40 400 L100 200 Z" fill="#062940" />
          {/* Solid Gold Chevron Band */}
          <path d="M10 0 L40 0 L100 200 L40 400 L10 400 L70 200 Z" fill="#C89332" />
        </svg>

        {/* Mobile Image: DILUAR padding container! Membentang 100% selebar layar tanpa celah samping! */}
        <div className="lg:hidden relative w-full aspect-[16/10] sm:aspect-[21/9] overflow-hidden bg-slate-900">
          <Image
            src="/images/ppdb-students.jpg"
            alt="Siswa SMP Laboratorium UM dalam seragam sekolah"
            fill
            unoptimized
            priority
            sizes="100vw"
            className="object-cover object-[center_15%]"
          />
          <div className="absolute inset-0 bg-gradient-to-t from-[#062940] via-transparent to-transparent" />
        </div>

        {/* Content Container dengan padding yang presisi */}
        <div className="relative z-20 w-full px-4 sm:px-8 lg:px-12 xl:px-16 py-8 sm:py-10 lg:py-16">
          {/* Right Text Column - Diposisikan di sisi kanan layar */}
          <div className="lg:ml-auto lg:w-[52%] xl:w-[50%] lg:pl-8 text-white space-y-4">
            <span className="text-xs font-bold uppercase tracking-wider text-[#F4E2BD] block">
              PENERIMAAN PESERTA DIDIK BARU
            </span>

            <h2
              id="ppdb-cta-heading"
              className="font-pub-heading text-2xl sm:text-3xl lg:text-[42px] font-bold text-white tracking-tight leading-tight"
            >
              PPDB 2026/2027
            </h2>

            <p className="text-white/90 text-sm sm:text-base font-medium">
              Jalur Reguler &nbsp;|&nbsp; ICP &nbsp;|&nbsp; Tahfidz &nbsp;|&nbsp; Inklusi
            </p>

            {activeWave && (
              <div className="space-y-0.5">
                <p className="text-xs sm:text-sm font-semibold text-[#F4E2BD]">
                  Gelombang aktif saat ini: {activeWave.name}
                </p>
                {activeWave.close_at && (
                  <p className="text-xs text-white/80">
                    Batas waktu pendaftaran: {formatTanggalIndo(activeWave.close_at)}
                  </p>
                )}
              </div>
            )}

            <div className="pt-2 flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
              <Link
                href="/ppdb"
                className="bg-[#C89332] hover:bg-[#B57C23] text-white font-bold text-xs sm:text-base py-3 px-6 rounded-lg shadow-md transition-all inline-flex items-center justify-center gap-2 focus:outline-none focus:ring-2 focus:ring-[#C89332] focus:ring-offset-2 focus:ring-offset-[#062940]"
              >
                <span>Daftar Sekarang</span>
                <span aria-hidden="true">→</span>
              </Link>
              <Link
                href="/ppdb#jalur"
                className="bg-transparent hover:bg-white/10 text-white border border-white/40 hover:border-white font-semibold text-xs sm:text-base py-3 px-5 rounded-lg transition-colors inline-flex items-center justify-center gap-2 focus:outline-none focus:ring-2 focus:ring-[#C89332] focus:ring-offset-2 focus:ring-offset-[#062940]"
              >
                <span>Lihat Jalur &amp; Syarat</span>
              </Link>
            </div>
          </div>
        </div>
      </section>
    </main>
  );
}
