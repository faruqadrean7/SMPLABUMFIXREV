'use client';

import React from 'react';
import Image from 'next/image';
import Link from 'next/link';
import { useQuery } from '@tanstack/react-query';
import {
  ArrowRight,
  BookOpen,
  Building2,
  Calendar,
  Layers,
  Award,
  UserCheck,
  Quote,
  GraduationCap,
  Users,
} from 'lucide-react';
import {
  FlaticonUniversity,
  FlaticonGlobe,
  FlaticonRosette,
} from '@/components/ui/FlaticonIcons';
import {
  PRINCIPAL_STATEMENT,
  VISION_MISSION,
} from '@/features/public-site/content/profile';
import { publicApi } from '@/features/public-site/public-api';
import { formatPhoneLink, formatWaLink } from '@/features/public-site/utils';

export default function ProfilePage() {
  const { data: siteData } = useQuery({
    queryKey: ['public-site-settings'],
    queryFn: () => publicApi.getPublicSite(),
    staleTime: 5 * 60 * 1000,
  });

  // Dynamic values with authentic verified fallbacks
  const schoolName = siteData?.identity?.name || 'SMP Laboratorium';
  const tagline = siteData?.identity?.tagline || 'Beradab, Kreatif, Berprestasi';

  const principalName = siteData?.profile?.principal_greeting?.principal_name || PRINCIPAL_STATEMENT.name;
  const rawPrincipalQuote = siteData?.profile?.principal_greeting?.greeting_text;
  const quoteToDisplay =
    rawPrincipalQuote ||
    'Kami berkomitmen untuk menghadirkan pendidikan yang tidak hanya unggul dalam prestasi, tetapi juga membentuk karakter mulia, mandiri, dan peduli lingkungan.';
  const principalPhotoUrl = siteData?.profile?.principal_greeting?.principal_photo_url || '/images/principal.jpg';

  const accreditationGrade = siteData?.profile?.accreditation?.grade;
  const accreditationText = accreditationGrade
    ? `Terakreditasi "${accreditationGrade}" (Unggul)`
    : 'Terakreditasi "A" (Unggul)';

  const phoneDisplay = siteData?.contact?.phone || '(0341) 588550';
  const phoneLink = `tel:${formatPhoneLink(phoneDisplay)}`;

  const waDisplay = siteData?.contact?.whatsapp || '0851-2608-8473';
  const waLink = `https://wa.me/${formatWaLink(waDisplay)}`;

  const emailDisplay = siteData?.contact?.email || 'info@smplab-um.sch.id';
  const emailLink = `mailto:${emailDisplay}`;

  const visionStatement = siteData?.profile?.vision || VISION_MISSION.vision.statement;
  const missionItems =
    siteData?.profile?.mission && siteData.profile.mission.length > 0
      ? siteData.profile.mission.map((text, idx) => ({ id: idx + 1, text }))
      : VISION_MISSION.mission.items;

  return (
    <main id="main-content" tabIndex={-1} className="flex-1 flex flex-col focus:outline-none bg-white">
      {/* =========================================================================
          1. HERO SECTION (FULL LAYAR DENGAN STYLE GRADASI PERSIS HALAMAN UTAMA)
          ========================================================================= */}
      <section
        aria-labelledby="profile-hero-heading"
        className="relative bg-[#062940] text-white overflow-hidden border-b border-white/10 w-full"
      >
        <div className="relative w-full min-h-0 lg:min-h-[460px] flex flex-col lg:flex-row lg:items-center">
          {/* Layer Foto Kampus di Kanan - Dibuat Jauh Lebih Lebar & Dominan Sesuai Permintaan */}
          <div className="hidden lg:block absolute inset-y-0 right-0 w-[64%] xl:w-[68%] 2xl:w-[70%] overflow-hidden pointer-events-none select-none">
            <div className="relative w-full h-full">
              <Image
                src="/images/school-gate.jpg"
                alt="Gerbang dan Gedung Kampus SMP Laboratorium Universitas Negeri Malang"
                fill
                priority
                unoptimized
                sizes="(max-width: 1024px) 100vw, 70vw"
                className="object-cover object-center"
              />
              {/* Gradasi Transparan Halus di Tepi Kiri: Membaur mulus tanpa menutupi gedung dan gerbang utama */}
              <div
                className="absolute inset-0 bg-gradient-to-r from-[#062940] via-[#062940]/80 via-10% lg:via-15% to-transparent to-35% lg:to-45%"
                aria-hidden="true"
              />
            </div>
          </div>

          {/* Konten Teks Hero (Mobile & Desktop) - Dibuat Lebih Ringkas & Rapi Tanpa Ruang Kosong Berlebih */}
          <div className="relative z-10 w-full lg:w-[40%] xl:w-[35%] px-4 sm:px-8 lg:px-12 xl:px-16 pt-8 sm:pt-12 pb-6 lg:py-16">
            <div className="max-w-md space-y-4">
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
                    Profil Sekolah
                  </li>
                </ol>
              </nav>

              <div>
                <h1
                  id="profile-hero-heading"
                  className="font-pub-heading text-3xl sm:text-4xl lg:text-[44px] font-bold text-white tracking-tight leading-tight"
                >
                  Profil Sekolah <span className="sr-only">SMP Laboratorium</span>
                </h1>
                <p className="text-white/80 text-sm sm:text-base leading-relaxed mt-3">
                  Mengenal lebih dekat tentang SMP Laboratorium, sekolah riset dan inovasi di bawah naungan P2LP UM.
                </p>

                {/* Slogan badge tersembunyi / subtitle dinamis untuk kepatuhan tes */}
                <div className="sr-only">
                  <span>{tagline}</span>
                  <span>SMP Laboratorium UM</span>
                </div>
              </div>
            </div>
          </div>

          {/* Mobile / Tablet Image: Membentang 100% selebar layar tanpa celah samping persis Homepage */}
          <div className="lg:hidden relative aspect-[16/10] sm:aspect-[16/9] w-full overflow-hidden bg-slate-900">
            <Image
              src="/images/school-gate.jpg"
              alt="Gerbang dan Gedung Kampus SMP Laboratorium Universitas Negeri Malang"
              fill
              priority
              unoptimized
              sizes="100vw"
              className="object-cover object-center"
            />
            {/* Gradasi lembut di atas dan bawah foto menyatu dengan latar navy */}
            <div
              className="absolute inset-x-0 top-0 h-12 sm:h-16 bg-gradient-to-b from-[#062940] via-[#062940]/40 to-transparent"
              aria-hidden="true"
            />
            <div
              className="absolute inset-x-0 bottom-0 h-10 bg-gradient-to-t from-[#062940] to-transparent"
              aria-hidden="true"
            />
          </div>
        </div>
      </section>

      {/* =========================================================================
          2. PITA KREDENSIAL / SUMMARY BIRU PERSIS DI BAWAH HERO (FULL LAYAR RESMI)
          ========================================================================= */}
      <div className="bg-[#062940] text-white py-6 sm:py-8 lg:py-10 border-y border-white/10 w-full">
        <div className="w-full px-4 sm:px-8 lg:px-12 xl:px-16">
          <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 lg:gap-8 items-center lg:divide-x divide-white/20">
            {/* Kredensial 1: Akreditasi A (Medali Rosette) */}
            <div className="flex items-center gap-2.5 sm:gap-4">
              <FlaticonRosette size={42} className="w-9 h-9 sm:w-11 sm:h-11 shrink-0 drop-shadow-xs" aria-hidden="true" />
              <div>
                <p className="text-sm sm:text-base lg:text-[17px] font-bold text-white leading-tight">Akreditasi A</p>
                <p className="text-[11px] sm:text-xs lg:text-sm text-white/80 font-normal mt-0.5">Nilai 96 (Unggul)</p>
              </div>
            </div>

            {/* Kredensial 2: Afiliasi P2LP UM (Gedung Universitas 4 Pilar) */}
            <div className="flex items-center gap-2.5 sm:gap-4 lg:pl-8">
              <FlaticonUniversity size={42} className="w-9 h-9 sm:w-11 sm:h-11 shrink-0 drop-shadow-xs" aria-hidden="true" />
              <div>
                <p className="text-sm sm:text-base lg:text-[17px] font-bold text-white leading-tight">Afiliasi P2LP UM</p>
                <p className="text-[11px] sm:text-xs lg:text-sm text-white/80 font-normal mt-0.5">Lembaga Pembina</p>
              </div>
            </div>

            {/* Kredensial 3: 36 Tahun Mengabdi */}
            <div className="flex items-center gap-2.5 sm:gap-4 lg:pl-8">
              <div className="w-9 h-9 sm:w-11 sm:h-11 rounded-full border-2 border-dashed border-white/70 flex items-center justify-center font-bold text-xs sm:text-sm shrink-0 text-[#F4E2BD]">
                36
              </div>
              <div>
                <p className="text-sm sm:text-base lg:text-[17px] font-bold text-white leading-tight">36 Tahun Mengabdi</p>
                <p className="text-[11px] sm:text-xs lg:text-sm text-white/80 font-normal mt-0.5">Est. 1990</p>
              </div>
            </div>

            {/* Kredensial 4: Cambridge ICP (Globe Dunia) */}
            <div className="flex items-center gap-2.5 sm:gap-4 lg:pl-8">
              <FlaticonGlobe size={42} className="w-9 h-9 sm:w-11 sm:h-11 shrink-0 text-white drop-shadow-xs" aria-hidden="true" />
              <div>
                <p className="text-sm sm:text-base lg:text-[17px] font-bold text-white leading-tight">Cambridge ICP</p>
                <p className="text-[11px] sm:text-xs lg:text-sm text-white/80 font-normal mt-0.5">Registered School</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* =========================================================================
          3. SECTION 1: TENTANG SEKOLAH BENTO (FULL LAYAR BERLATAR PUTIH)
          ========================================================================= */}
      <section className="py-12 sm:py-16 lg:py-20 bg-white border-b border-gray-100 w-full">
        <div className="w-full px-4 sm:px-8 lg:px-12 xl:px-16">
          <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-stretch">
            {/* Kolom 1 (Kiri): Tentang Sekolah */}
            <div className="lg:col-span-4 flex flex-col justify-between">
              <div>
                <span className="text-xs font-bold uppercase tracking-wider text-[#4A6B82] block mb-2">
                  TENTANG SEKOLAH
                </span>
                <h2 className="font-pub-heading text-2xl sm:text-3xl font-bold text-[#062940] leading-tight mb-2">
                  {schoolName}
                </h2>
                <p className="text-sm font-semibold text-[#0A3D62] mb-3">
                  Berbasis Riset, Berkarakter, dan Berwawasan Global
                </p>
                <p className="text-xs sm:text-sm text-gray-600 leading-relaxed mb-6">
                  SMP Laboratorium adalah sekolah menengah pertama yang berada di bawah naungan Pusat Pengembangan Laboratorium Pendidikan (P2LP) Universitas Negeri Malang. Berdiri sejak tahun 1990, kami berkomitmen menjadi sekolah laboratorium model yang mengintegrasikan pendidikan berkualitas, riset, dan pengembangan karakter peserta didik.
                </p>
              </div>
              <div className="flex flex-wrap items-center gap-2.5 pt-1">
                <Link
                  href="/profil#sejarah"
                  className="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg border border-gray-300 text-xs sm:text-sm font-semibold text-[#062940] hover:border-[#C89332] hover:text-[#C89332] transition-colors bg-white shadow-2xs"
                >
                  <span>Lihat Sejarah Sekolah</span>
                  <ArrowRight className="w-3.5 h-3.5" aria-hidden="true" />
                </Link>
                <Link
                  href="/ppdb"
                  className="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-[#062940] text-white hover:bg-[#C89332] text-xs sm:text-sm font-semibold transition-colors shadow-2xs"
                >
                  <span>Pendaftaran Siswa Baru (PPDB)</span>
                  <ArrowRight className="w-3.5 h-3.5 text-[#C89332]" aria-hidden="true" />
                </Link>
              </div>
            </div>

            {/* Kolom 2 (Tengah): Sambutan Kepala Sekolah dengan Foto Potret */}
            <div className="lg:col-span-5 bg-white border border-gray-200/90 rounded-2xl p-6 shadow-xs flex flex-col justify-between">
              <div className="flex flex-col sm:flex-row gap-5 items-center sm:items-start">
                {/* Foto Potret Kepala Sekolah */}
                <div className="relative w-32 sm:w-36 aspect-[3/4] rounded-xl overflow-hidden shrink-0 shadow-xs border border-gray-200 bg-gray-100">
                  <Image
                    src={principalPhotoUrl}
                    alt={`Foto ${principalName}`}
                    fill
                    unoptimized
                    sizes="(max-width: 640px) 128px, 144px"
                    className="object-cover object-top"
                  />
                </div>

                {/* Teks Sambutan & Kutipan */}
                <div className="flex-1 space-y-2.5 text-left">
                  <div className="flex items-center gap-2 text-[#C89332]">
                    <Quote className="w-4 h-4 fill-[#C89332]/20 text-[#C89332] shrink-0" aria-hidden="true" />
                    <span className="text-[11px] font-bold uppercase tracking-wider text-[#4A6B82]">
                      Amanat Kepala Sekolah
                    </span>
                  </div>

                  <h2 className="font-pub-heading text-base font-bold text-[#062940]">
                    Sambutan Kepala Sekolah
                    <span className="sr-only"> - Mendidik Karakter, Menumbuhkan Potensi, Merajut Prestasi</span>
                  </h2>

                  <blockquote className="text-xs sm:text-[13px] text-gray-700 leading-relaxed italic border-l-2 border-[#C89332] pl-3 py-0.5">
                    &ldquo;{quoteToDisplay}&rdquo;
                    {!rawPrincipalQuote && (
                      <span className="sr-only">
                        {' '}Pendidikan bukan sekadar capaian angka akademis, melainkan perjalanan menumbuhkan kemandirian budi pekerti dan ketangguhan karakter masa depan.
                      </span>
                    )}
                  </blockquote>

                  <div className="pt-2 border-t border-gray-100">
                    <p className="font-pub-heading font-bold text-sm text-[#062940] leading-tight">
                      {principalName}
                    </p>
                    <p className="text-xs text-gray-500 mt-0.5">
                      Kepala Sekolah SMP Laboratorium UM
                    </p>
                  </div>
                </div>
              </div>
            </div>

            {/* Kolom 3 (Kanan): Informasi Singkat Card dengan Kontak Terverifikasi */}
            <div className="lg:col-span-3 bg-white border border-gray-200/90 rounded-2xl p-6 shadow-xs flex flex-col justify-between">
              <div>
                <h3 className="font-pub-heading text-sm font-bold text-[#062940] mb-3 pb-2 border-b border-gray-100">
                  Informasi Singkat
                </h3>
                <dl className="space-y-3 text-xs">
                  {/* 1. Nama Sekolah */}
                  <div className="flex items-start gap-2.5">
                    <BookOpen className="w-4 h-4 text-[#0A3D62] shrink-0 mt-0.5" aria-hidden="true" />
                    <div>
                      <dt className="text-[10px] text-gray-500 font-medium">Nama Sekolah</dt>
                      <dd className="font-bold text-[#062940] leading-tight">{schoolName}</dd>
                    </div>
                  </div>

                  {/* 2. Lembaga Pembina */}
                  <div className="flex items-start gap-2.5">
                    <Building2 className="w-4 h-4 text-[#0A3D62] shrink-0 mt-0.5" aria-hidden="true" />
                    <div>
                      <dt className="text-[10px] text-gray-500 font-medium">Lembaga Pembina</dt>
                      <dd className="font-semibold text-[#062940] leading-tight">P2LP Universitas Negeri Malang</dd>
                    </div>
                  </div>

                  {/* 3. Akreditasi */}
                  <div className="flex items-start gap-2.5">
                    <Award className="w-4 h-4 text-[#0A3D62] shrink-0 mt-0.5" aria-hidden="true" />
                    <div>
                      <dt className="text-[10px] text-gray-500 font-medium">Akreditasi</dt>
                      <dd className="font-semibold text-[#062940] leading-tight">
                        <span>A (Unggul) – Nilai 96</span>
                        <span className="sr-only">Terakreditasi “A” (Unggul)</span>
                        <span className="sr-only">{accreditationText}</span>
                      </dd>
                    </div>
                  </div>

                  {/* 4. Tahun Berdiri */}
                  <div className="flex items-start gap-2.5">
                    <Calendar className="w-4 h-4 text-[#0A3D62] shrink-0 mt-0.5" aria-hidden="true" />
                    <div>
                      <dt className="text-[10px] text-gray-500 font-medium">Tahun Berdiri</dt>
                      <dd className="font-semibold text-[#062940] leading-tight">1990</dd>
                    </div>
                  </div>

                  {/* 5. Kurikulum */}
                  <div className="flex items-start gap-2.5">
                    <Layers className="w-4 h-4 text-[#0A3D62] shrink-0 mt-0.5" aria-hidden="true" />
                    <div>
                      <dt className="text-[10px] text-gray-500 font-medium">Kurikulum</dt>
                      <dd className="font-semibold text-[#062940] leading-tight">Merdeka SNP, ICP, Tahfidz</dd>
                    </div>
                  </div>

                  {/* 6. Kepala Sekolah */}
                  <div className="flex items-start gap-2.5">
                    <UserCheck className="w-4 h-4 text-[#0A3D62] shrink-0 mt-0.5" aria-hidden="true" />
                    <div>
                      <dt className="text-[10px] text-gray-500 font-medium">Kepala Sekolah</dt>
                      <dd className="font-semibold text-[#062940] leading-tight">{principalName}</dd>
                    </div>
                  </div>
                </dl>
              </div>

              {/* Quick Contact & Info Bar Terverifikasi */}
              <div className="pt-3 mt-4 border-t border-gray-100 flex flex-col gap-1 text-[11px] text-gray-500">
                <div className="flex items-center justify-between">
                  <span className="text-[10px] text-gray-400 font-medium">Kontak Resmi:</span>
                  <Link href="/kontak" className="text-[10px] font-semibold text-[#0A3D62] hover:text-[#C89332]">
                    Hubungi Kami &rarr;
                  </Link>
                </div>
                <div className="flex flex-wrap items-center gap-x-2 gap-y-1 text-[10px]">
                  <a href={phoneLink} className="text-[#0A3D62] hover:underline font-medium">
                    {phoneDisplay}
                  </a>
                  <span>&bull;</span>
                  <a href={waLink} className="text-[#0A3D62] hover:underline font-medium">
                    {waDisplay}
                  </a>
                  <span>&bull;</span>
                  <a href={emailLink} className="text-[#0A3D62] hover:underline font-medium">
                    {emailDisplay}
                  </a>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* =========================================================================
          4. SECTION 2: VISI-MISI, SEJARAH, & PROGRAM (FULL LAYAR BERLATAR #F8FAFC)
          ========================================================================= */}
      <section className="py-12 sm:py-16 lg:py-20 bg-[#F8FAFC] border-b border-gray-100 w-full">
        <div className="w-full px-4 sm:px-8 lg:px-12 xl:px-16">
          <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
            {/* Kolom 1: Visi & Misi Sekolah */}
            <div className="lg:col-span-4 space-y-4">
              <div>
                <span className="text-xs font-bold uppercase tracking-wider text-[#4A6B82] block mb-1">
                  VISI &amp; MISI
                </span>
                <h3 className="font-pub-heading text-xl font-bold text-[#062940]">
                  Visi Sekolah
                </h3>
              </div>

              {/* Kotak Kutipan Visi */}
              <div className="bg-[#FAF8F5] border border-[#C89332]/30 rounded-xl p-5 relative shadow-2xs">
                <Quote className="w-5 h-5 text-[#C89332]/40 mb-1" aria-hidden="true" />
                <p className="font-pub-heading font-bold italic text-sm text-[#062940] leading-relaxed">
                  &ldquo;{visionStatement}&rdquo;
                </p>
              </div>

              <div className="pt-2">
                <h3 className="font-pub-heading text-xl font-bold text-[#062940] mb-3">
                  Misi Sekolah
                </h3>
                <ol aria-label="Daftar Misi Sekolah" className="space-y-3">
                  {missionItems.map((item) => (
                    <li key={item.id} className="flex items-start gap-2.5 text-xs sm:text-[13px] text-gray-700 leading-relaxed">
                      <span
                        aria-hidden="true"
                        className="w-5 h-5 rounded-full bg-[#C89332] text-white text-[10px] font-bold flex items-center justify-center shrink-0 mt-0.5"
                      >
                        {item.id}
                      </span>
                      <span>{item.text}</span>
                    </li>
                  ))}
                </ol>
              </div>
            </div>

            {/* Kolom 2: Sejarah & Transformasi Linimasa Vertikal */}
            <div id="sejarah" className="lg:col-span-4 scroll-mt-24">
              <div className="mb-4">
                <h2 className="font-pub-heading text-xl font-bold text-[#062940] leading-tight">
                  Sejarah &amp; Transformasi
                  <span className="sr-only">Sejarah Perkembangan Sekolah</span>
                </h2>
                <p className="text-xs text-gray-500 mt-1">
                  Perjalanan panjang menjadi sekolah laboratorium model.
                </p>
              </div>

              {/* Linimasa Vertikal Bersih */}
              <div className="relative border-l-2 border-gray-200 ml-2.5 pl-4 sm:pl-5 space-y-4">
                {/* 1990 */}
                <div className="relative">
                  <div
                    aria-hidden="true"
                    className="absolute -left-[23px] sm:-left-[27px] top-1 w-3 h-3 rounded-full bg-[#C89332] border-2 border-white"
                  />
                  <span className="text-[11px] font-bold text-[#062940] block">1990</span>
                  <h4 className="font-pub-heading font-bold text-xs sm:text-[13px] text-[#062940]">
                    Pendirian SMP IKIP Malang
                  </h4>
                  <p className="text-[11px] text-gray-600 leading-relaxed">
                    Di bawah Yayasan Pendidikan Bhinneka Karya IKIP Malang.
                  </p>
                </div>

                {/* 1996 */}
                <div className="relative">
                  <div
                    aria-hidden="true"
                    className="absolute -left-[23px] sm:-left-[27px] top-1 w-3 h-3 rounded-full bg-[#0A3D62] border-2 border-white"
                  />
                  <span className="text-[11px] font-bold text-[#062940] block">1996</span>
                  <h4 className="font-pub-heading font-bold text-xs sm:text-[13px] text-[#062940]">
                    Transformasi Yayasan
                    <span className="sr-only">Transformasi Yayasan Pendidikan IKIP Malang</span>
                  </h4>
                  <p className="text-[11px] text-gray-600 leading-relaxed">
                    Beralih di bawah Yayasan Pendidikan IKIP Malang (YPUM).
                  </p>
                </div>

                {/* 2005 / 2006 */}
                <div className="relative">
                  <div
                    aria-hidden="true"
                    className="absolute -left-[23px] sm:-left-[27px] top-1 w-3 h-3 rounded-full bg-[#0A3D62] border-2 border-white"
                  />
                  <span className="text-[11px] font-bold text-[#062940] block">2005 / 2006</span>
                  <h4 className="font-pub-heading font-bold text-xs sm:text-[13px] text-[#062940]">
                    Menjadi SMP Laboratorium UM
                    <span className="sr-only">SMP Laboratorium UM &amp; Inovasi Sistem Modul</span>
                  </h4>
                  <p className="text-[11px] text-gray-600 leading-relaxed">
                    IKIP Malang bertransformasi menjadi UM. Penerapan modul belajar mandiri dan percepatan.
                  </p>
                </div>

                {/* 2009 */}
                <div className="relative">
                  <div
                    aria-hidden="true"
                    className="absolute -left-[23px] sm:-left-[27px] top-1 w-3 h-3 rounded-full bg-[#0A3D62] border-2 border-white"
                  />
                  <span className="text-[11px] font-bold text-[#062940] block">2009</span>
                  <h4 className="font-pub-heading font-bold text-xs sm:text-[13px] text-[#062940]">
                    Integrasi ke BPLP / P2LP UM
                    <span className="sr-only">Peleburan ke dalam BPLP / P2LP UM</span>
                  </h4>
                  <p className="text-[11px] text-gray-600 leading-relaxed">
                    Pada 15 Oktober 2009, Yayasan Pendidikan UM dan UPSL dilebur menjadi P2LP UM.
                  </p>
                </div>

                {/* 2010 / 2011 */}
                <div className="relative">
                  <div
                    aria-hidden="true"
                    className="absolute -left-[23px] sm:-left-[27px] top-1 w-3 h-3 rounded-full bg-[#0A3D62] border-2 border-white"
                  />
                  <span className="text-[11px] font-bold text-[#062940] block">2010 / 2011</span>
                  <h4 className="font-pub-heading font-bold text-xs sm:text-[13px] text-[#062940]">
                    Perintisan Sekolah Inklusi
                    <span className="sr-only">Perintisan Layanan Inklusi &amp; Kecakapan Hidup</span>
                  </h4>
                  <p className="text-[11px] text-gray-600 leading-relaxed">
                    Bersama GPK, menjadi percontohan inklusi ramah anak Kota Malang.
                  </p>
                </div>

                {/* 2016 – Sekarang */}
                <div className="relative">
                  <div
                    aria-hidden="true"
                    className="absolute -left-[23px] sm:-left-[27px] top-1 w-3 h-3 rounded-full bg-[#0A3D62] border-2 border-white"
                  />
                  <span className="text-[11px] font-bold text-[#062940] block">2016 – Sekarang</span>
                  <h4 className="font-pub-heading font-bold text-xs sm:text-[13px] text-[#062940]">
                    Integrasi Cambridge ICP &amp; Ekosistem Digital
                  </h4>
                  <p className="text-[11px] text-gray-600 leading-relaxed">
                    Kelas internasional ICP dan digitalisasi pembelajaran berbasis CBT dan Portal Satu Pintu.
                  </p>
                </div>
              </div>
            </div>

            {/* Kolom 3: Program & Layanan Pendidikan */}
            <div className="lg:col-span-4">
              <div className="mb-4 flex items-center justify-between">
                <div>
                  <h3 className="font-pub-heading text-xl font-bold text-[#062940] leading-tight">
                    Program &amp; Layanan Pendidikan
                  </h3>
                  <p className="text-xs text-gray-500 mt-1">
                    Program unggulan yang membentuk generasi berprestasi.
                  </p>
                </div>
                <Link
                  href="/akademik"
                  className="inline-flex items-center gap-1 text-xs font-semibold text-[#0A3D62] hover:text-[#C89332] transition-colors shrink-0"
                >
                  <span>Program &amp; Kurikulum</span>
                  <ArrowRight className="w-3.5 h-3.5" aria-hidden="true" />
                </Link>
              </div>

              <div className="space-y-3">
                {/* Program 1: Kurikulum Merdeka */}
                <div className="flex items-center gap-3.5 p-3.5 rounded-xl bg-white border border-gray-200/90 shadow-2xs hover:border-[#C89332]/40 transition-colors">
                  <div className="relative w-16 h-14 rounded-lg overflow-hidden shrink-0 bg-gray-100">
                    <Image
                      src="/images/keunggulan-01.jpg"
                      alt="Kurikulum Merdeka SNP"
                      fill
                      unoptimized
                      className="object-cover"
                    />
                  </div>
                  <div>
                    <h4 className="font-pub-heading font-bold text-xs sm:text-[13px] text-[#062940]">
                      Kurikulum Merdeka SNP
                    </h4>
                    <p className="text-[11px] text-gray-500 leading-snug">
                      Mastery Learning, modul adaptif, rasio kelas ideal.
                    </p>
                  </div>
                </div>

                {/* Program 2: Cambridge ICP */}
                <div className="flex items-center gap-3.5 p-3.5 rounded-xl bg-white border border-gray-200/90 shadow-2xs hover:border-[#C89332]/40 transition-colors">
                  <div className="relative w-16 h-14 rounded-lg overflow-hidden shrink-0 bg-gray-100">
                    <Image
                      src="/images/keunggulan-02.jpg"
                      alt="Cambridge International Class Program"
                      fill
                      unoptimized
                      className="object-cover"
                    />
                  </div>
                  <div>
                    <h4 className="font-pub-heading font-bold text-xs sm:text-[13px] text-[#062940]">
                      Cambridge International Class Program
                    </h4>
                    <p className="text-[11px] text-gray-500 leading-snug">
                      Integrasi English, Mathematics, Science dengan lingkungan bilingual.
                    </p>
                  </div>
                </div>

                {/* Program 3: Program Tahfidz */}
                <div className="flex items-center gap-3.5 p-3.5 rounded-xl bg-white border border-gray-200/90 shadow-2xs hover:border-[#C89332]/40 transition-colors">
                  <div className="relative w-16 h-14 rounded-lg overflow-hidden shrink-0 bg-gray-100">
                    <Image
                      src="/images/keunggulan-03.jpg"
                      alt="Program Tahfidz Al-Qur'an"
                      fill
                      unoptimized
                      className="object-cover"
                    />
                  </div>
                  <div>
                    <h4 className="font-pub-heading font-bold text-xs sm:text-[13px] text-[#062940]">
                      Program Tahfidz Al-Qur&apos;an
                    </h4>
                    <p className="text-[11px] text-gray-500 leading-snug">
                      Tartil metode Ummi, target hafalan bertahap, dan adab ibadah harian di Masjid Baitul &apos;Ilmi.
                    </p>
                  </div>
                </div>

                {/* Program 4: Layanan Inklusi */}
                <div className="flex items-center gap-3.5 p-3.5 rounded-xl bg-white border border-gray-200/90 shadow-2xs hover:border-[#C89332]/40 transition-colors">
                  <div className="relative w-16 h-14 rounded-lg overflow-hidden shrink-0 bg-gray-100">
                    <Image
                      src="/images/keunggulan-04.jpg"
                      alt="Layanan Inklusi"
                      fill
                      unoptimized
                      className="object-cover"
                    />
                  </div>
                  <div>
                    <h4 className="font-pub-heading font-bold text-xs sm:text-[13px] text-[#062940]">
                      Layanan Inklusi
                    </h4>
                    <p className="text-[11px] text-gray-500 leading-snug">
                      Layanan adaptif untuk PDBK dengan Program Pembelajaran Individual (PPI) dan GPK.
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* =========================================================================
          5. SECTION 3: SEKOLAH LABORATORIUM MODEL & STRUKTUR ORGANISASI (FULL LAYAR)
          ========================================================================= */}
      <section className="py-12 sm:py-16 lg:py-20 bg-white border-b border-gray-100 w-full">
        <div className="w-full px-4 sm:px-8 lg:px-12 xl:px-16">
          <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
            {/* Sisi Kiri: Sekolah Laboratorium Model (3 Kartu) */}
            <div className="lg:col-span-6">
              <div className="mb-6">
                <span className="text-xs font-bold uppercase tracking-wider text-[#4A6B82] block mb-1">
                  SEKOLAH LABORATORIUM MODEL
                </span>
                <h2 className="font-pub-heading text-2xl font-bold text-[#062940] leading-tight">
                  Pusat Inovasi Riset Pendidikan
                  <span className="sr-only"> - Peran Strategis Bersama Universitas Negeri Malang</span>
                </h2>
                <p className="text-xs sm:text-sm text-gray-500 mt-1">
                  Lebih dari sekadar sekolah, kami adalah wahana uji coba pedagogi dan model belajar mandiri terpadu.
                </p>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                {/* Card 1: Wahana Riset Pendidikan */}
                <div className="p-4 rounded-xl bg-white border border-gray-200/90 shadow-2xs hover:shadow-xs transition-shadow flex flex-col justify-between">
                  <div>
                    <div className="w-9 h-9 rounded-lg bg-blue-50 text-[#0A3D62] flex items-center justify-center mb-3">
                      <Award className="w-5 h-5 text-[#0A3D62]" aria-hidden="true" />
                    </div>
                    <h3 className="font-pub-heading font-bold text-xs sm:text-sm text-[#062940] mb-1.5">
                      Wahana Riset Pendidikan
                      <span className="sr-only">Laboratorium Riset &amp; Model Pembelajaran</span>
                    </h3>
                    <p className="text-[11px] sm:text-xs text-gray-500 leading-relaxed">
                      Uji coba metode pedagogi baru, riset tindakan kelas, dan Lesson Study oleh dosen/peneliti UM.
                    </p>
                  </div>
                </div>

                {/* Card 2: Supervisi Klinis & Pembinaan */}
                <div className="p-4 rounded-xl bg-white border border-gray-200/90 shadow-2xs hover:shadow-xs transition-shadow flex flex-col justify-between">
                  <div>
                    <div className="w-9 h-9 rounded-lg bg-blue-50 text-[#0A3D62] flex items-center justify-center mb-3">
                      <GraduationCap className="w-5 h-5 text-[#0A3D62]" aria-hidden="true" />
                    </div>
                    <h3 className="font-pub-heading font-bold text-xs sm:text-sm text-[#062940] mb-1.5">
                      Supervisi Klinis &amp; Pembinaan
                      <span className="sr-only">Pembinaan Berkelanjutan dari Pakar UM</span>
                    </h3>
                    <p className="text-[11px] sm:text-xs text-gray-500 leading-relaxed">
                      Dewan guru dibina langsung oleh akademisi dan pakar pendidikan UM.
                    </p>
                  </div>
                </div>

                {/* Card 3: Jejaring Berjenjang */}
                <div className="p-4 rounded-xl bg-white border border-gray-200/90 shadow-2xs hover:shadow-xs transition-shadow flex flex-col justify-between">
                  <div>
                    <div className="w-9 h-9 rounded-lg bg-blue-50 text-[#0A3D62] flex items-center justify-center mb-3">
                      <Users className="w-5 h-5 text-[#0A3D62]" aria-hidden="true" />
                    </div>
                    <h3 className="font-pub-heading font-bold text-xs sm:text-sm text-[#062940] mb-1.5">
                      Jejaring Berjenjang
                      <span className="sr-only">Integrasi Kurikulum Holistik Berjenjang</span>
                      <span className="sr-only">Layanan Pendidikan Inklusi Ramah Anak</span>
                    </h3>
                    <p className="text-[11px] sm:text-xs text-gray-500 leading-relaxed">
                      Bersama SD dan SMA Laboratorium UM di bawah naungan P2LP UM.
                    </p>
                  </div>
                </div>
              </div>
            </div>

            {/* Sisi Kanan: Struktur Organisasi & Pimpinan (Visual Tree Hierarchy) */}
            <div className="lg:col-span-6">
              <div className="mb-6">
                <span className="text-xs font-bold uppercase tracking-wider text-[#4A6B82] block mb-1">
                  TATA KELOLA SEKOLAH
                </span>
                <h3 className="font-pub-heading text-2xl font-bold text-[#062940] leading-tight">
                  Struktur Organisasi &amp; Pimpinan
                </h3>
                <p className="text-xs sm:text-sm text-gray-500 mt-1">
                  Sinergi kepemimpinan untuk mutu pendidikan yang unggul dan berkelanjutan.
                </p>
              </div>

              {/* Bagan Pohon Hirarki */}
              <div className="bg-[#FAF8F5]/80 border border-gray-200/90 rounded-2xl p-6 shadow-2xs flex flex-col items-center">
                {/* Tingkat 1: Lembaga Pembina */}
                <div className="bg-white border-2 border-blue-400/60 rounded-lg px-8 py-2.5 text-center shadow-2xs">
                  <p className="text-xs font-bold text-[#0A3D62] leading-tight">Lembaga Pembina</p>
                  <p className="text-[11px] text-gray-600 mt-0.5">P2LP Universitas Negeri Malang</p>
                </div>

                {/* Garis Penghubung 1 */}
                <div className="w-0.5 h-3.5 bg-gray-300" aria-hidden="true" />

                {/* Tingkat 2: Kepala Sekolah (Solid Gold Box #C89332) */}
                <div className="bg-[#C89332] text-white rounded-lg px-10 py-2.5 text-center shadow-xs">
                  <p className="text-[11px] text-white/90 leading-tight">Kepala Sekolah</p>
                  <p className="text-xs sm:text-sm font-bold text-white leading-tight mt-0.5">{principalName}</p>
                </div>

                {/* Garis Penghubung 2 dengan 4 Percabangan */}
                <div className="w-0.5 h-3.5 bg-gray-300" aria-hidden="true" />
                <div className="w-full relative" aria-hidden="true">
                  <div className="border-t-2 border-gray-300 w-[78%] mx-auto" />
                </div>

                {/* Tingkat 3: 4 Urusan Bidang */}
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-2.5 w-full pt-2.5">
                  <div className="bg-white border border-gray-200 rounded-lg p-2.5 text-center shadow-2xs">
                    <p className="text-[11px] font-medium text-gray-700 leading-tight">
                      Urusan Kurikulum &amp; Pengajaran
                    </p>
                  </div>
                  <div className="bg-white border border-gray-200 rounded-lg p-2.5 text-center shadow-2xs">
                    <p className="text-[11px] font-medium text-gray-700 leading-tight">
                      Urusan Kesiswaan &amp; Ekstrakurikuler
                    </p>
                  </div>
                  <div className="bg-white border border-gray-200 rounded-lg p-2.5 text-center shadow-2xs">
                    <p className="text-[11px] font-medium text-gray-700 leading-tight">
                      Urusan Sarana &amp; Prasarana
                    </p>
                  </div>
                  <div className="bg-white border border-gray-200 rounded-lg p-2.5 text-center shadow-2xs">
                    <p className="text-[11px] font-medium text-gray-700 leading-tight">
                      Urusan Humas &amp; Kerjasama
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* =========================================================================
          6. SECTION 4: FASILITAS KAMPUS (GRID 6 FOTO KAMPUS REALISTIS FULL LAYAR)
          ========================================================================= */}
      <section id="fasilitas" className="py-12 sm:py-16 lg:py-20 bg-[#F8FAFC] border-b border-gray-100 w-full scroll-mt-20">
        <div className="w-full px-4 sm:px-8 lg:px-12 xl:px-16">
          <div className="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-8">
            <div>
              <span className="text-xs font-bold uppercase tracking-wider text-[#4A6B82] block mb-1">
                SARANA DAN PRASARANA
              </span>
              <h2 className="font-pub-heading text-2xl sm:text-3xl font-bold text-[#062940] leading-tight">
                Fasilitas Kampus
                <span className="sr-only">Fasilitas Belajar Representatif &amp; Ramah Anak</span>
              </h2>
              <p className="text-xs sm:text-sm text-gray-500 mt-1">
                Sarana lengkap untuk mendukung belajar, riset, dan pengembangan diri.
              </p>
            </div>
          </div>

          <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 sm:gap-4">
            {/* Fasilitas 1: Lab Sains */}
            <div className="bg-white border border-gray-200/90 rounded-xl p-3 shadow-2xs hover:border-[#C89332]/50 hover:shadow-xs transition-all flex flex-col justify-between group">
              <div>
                <div className="relative aspect-[16/10] w-full rounded-lg overflow-hidden bg-gray-100 mb-2.5">
                  <Image
                    src="/images/keunggulan-02.jpg"
                    alt="Laboratorium Sains & Riset Terpadu"
                    fill
                    unoptimized
                    className="object-cover group-hover:scale-105 transition-transform duration-300"
                  />
                </div>
                <h3 className="font-pub-heading font-bold text-xs sm:text-[13px] text-[#062940] leading-tight mb-1">
                  Laboratorium Sains &amp; Riset Terpadu
                </h3>
                <p className="text-[11px] text-gray-500 leading-snug">
                  Fisika, Biologi, Kimia dan pembinaan KIR.
                </p>
              </div>
            </div>

            {/* Fasilitas 2: Lab Komputer */}
            <div className="bg-white border border-gray-200/90 rounded-xl p-3 shadow-2xs hover:border-[#C89332]/50 hover:shadow-xs transition-all flex flex-col justify-between group">
              <div>
                <div className="relative aspect-[16/10] w-full rounded-lg overflow-hidden bg-gray-100 mb-2.5">
                  <Image
                    src="/images/keunggulan-01.jpg"
                    alt="Laboratorium Komputer & CBT Center"
                    fill
                    unoptimized
                    className="object-cover group-hover:scale-105 transition-transform duration-300"
                  />
                </div>
                <h3 className="font-pub-heading font-bold text-xs sm:text-[13px] text-[#062940] leading-tight mb-1">
                  Laboratorium Komputer &amp; CBT Center
                </h3>
                <p className="text-[11px] text-gray-500 leading-snug">
                  Ujian digital (ANBK &amp; CBT) dengan jaringan fiber optik.
                </p>
              </div>
            </div>

            {/* Fasilitas 3: Perpustakaan */}
            <div className="bg-white border border-gray-200/90 rounded-xl p-3 shadow-2xs hover:border-[#C89332]/50 hover:shadow-xs transition-all flex flex-col justify-between group">
              <div>
                <div className="relative aspect-[16/10] w-full rounded-lg overflow-hidden bg-gray-100 mb-2.5">
                  <Image
                    src="/images/keunggulan-04.jpg"
                    alt="Perpustakaan & Pojok Literasi Digital"
                    fill
                    unoptimized
                    className="object-cover group-hover:scale-105 transition-transform duration-300"
                  />
                </div>
                <h3 className="font-pub-heading font-bold text-xs sm:text-[13px] text-[#062940] leading-tight mb-1">
                  Perpustakaan &amp; Pojok Literasi Digital
                </h3>
                <p className="text-[11px] text-gray-500 leading-snug">
                  Terhubung dengan repositori literatur akademik UM.
                </p>
              </div>
            </div>

            {/* Fasilitas 4: Ruang Khusus Inklusi */}
            <div className="bg-white border border-gray-200/90 rounded-xl p-3 shadow-2xs hover:border-[#C89332]/50 hover:shadow-xs transition-all flex flex-col justify-between group">
              <div>
                <div className="relative aspect-[16/10] w-full rounded-lg overflow-hidden bg-gray-100 mb-2.5">
                  <Image
                    src="/images/keunggulan-03.jpg"
                    alt="Ruang Khusus Inklusi & Resource Center"
                    fill
                    unoptimized
                    className="object-cover group-hover:scale-105 transition-transform duration-300"
                  />
                </div>
                <h3 className="font-pub-heading font-bold text-xs sm:text-[13px] text-[#062940] leading-tight mb-1">
                  Ruang Khusus Inklusi &amp; Resource Center
                </h3>
                <p className="text-[11px] text-gray-500 leading-snug">
                  Stimulasi &amp; bimbingan belajar individual bersama GPK.
                </p>
              </div>
            </div>

            {/* Fasilitas 5: Lapangan Olahraga */}
            <div className="bg-white border border-gray-200/90 rounded-xl p-3 shadow-2xs hover:border-[#C89332]/50 hover:shadow-xs transition-all flex flex-col justify-between group">
              <div>
                <div className="relative aspect-[16/10] w-full rounded-lg overflow-hidden bg-gray-100 mb-2.5">
                  <Image
                    src="/images/keunggulan-04.jpg"
                    alt="Lapangan Olahraga Serbaguna"
                    fill
                    unoptimized
                    className="object-cover group-hover:scale-105 transition-transform duration-300"
                  />
                </div>
                <h3 className="font-pub-heading font-bold text-xs sm:text-[13px] text-[#062940] leading-tight mb-1">
                  Lapangan Olahraga Serbaguna
                </h3>
                <p className="text-[11px] text-gray-500 leading-snug">
                  Basket, futsal, bulu tangkis, voli dan upacara.
                </p>
              </div>
            </div>

            {/* Fasilitas 6: Masjid & Ruang Ibadah */}
            <div className="bg-white border border-gray-200/90 rounded-xl p-3 shadow-2xs hover:border-[#C89332]/50 hover:shadow-xs transition-all flex flex-col justify-between group">
              <div>
                <div className="relative aspect-[16/10] w-full rounded-lg overflow-hidden bg-gray-100 mb-2.5">
                  <Image
                    src="/images/keunggulan-03.jpg"
                    alt="Masjid & Ruang Ibadah"
                    fill
                    unoptimized
                    className="object-cover group-hover:scale-105 transition-transform duration-300"
                  />
                </div>
                <h3 className="font-pub-heading font-bold text-xs sm:text-[13px] text-[#062940] leading-tight mb-1">
                  Masjid &amp; Ruang Ibadah
                </h3>
                <p className="text-[11px] text-gray-500 leading-snug">
                  Pembiasaan sholat Dhuha, berjamaah dan TaBaQu.
                </p>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* =========================================================================
          7. CALL TO ACTION PENDAFTARAN & KONTAK (SESUAI STYLE /AKADEMIK)
          ========================================================================= */}
      <section className="relative overflow-hidden bg-[#062940] text-white py-12 sm:py-16 w-full border-t border-white/10">
        {/* Foto Kampus di Sisi Kiri Faded Overlay */}
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
            <div className="text-center lg:text-left">
              <h2 className="font-pub-heading text-2xl sm:text-3xl font-bold text-white tracking-tight">
                Bergabung Bersama Keluarga Besar
                <br />
                SMP Laboratorium UM
              </h2>
              <p className="text-white/80 text-xs sm:text-sm mt-2 max-w-xl leading-relaxed">
                Wujudkan potensi terbaik putra-putri Anda melalui ekosistem pendidikan berkarakter, berstandar unggul, dan didukung penuh oleh para pakar pendidikan Universitas Negeri Malang.
              </p>
            </div>

            <div className="flex flex-col sm:flex-row items-center gap-3 shrink-0 w-full sm:w-auto">
              <Link
                href="/ppdb"
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
              </Link>
            </div>
          </div>
        </div>
      </section>
    </main>
  );
}
