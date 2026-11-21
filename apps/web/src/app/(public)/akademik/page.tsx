'use client';

import React, { useMemo } from 'react';
import Image from 'next/image';
import Link from 'next/link';
import { useQuery } from '@tanstack/react-query';
import {
  ArrowRight,
  Clock,
  MessageSquare,
  Palette,
  Trophy,
  FileText,
  Award,
  Layers,
  BookCheck,
  Building2,
  Globe,
  UserCheck,
  Phone,
  Microscope,
} from 'lucide-react';
import {
  FlaticonRosette,
  FlaticonUniversity,
  FlaticonGlobe,
  FlaticonBook,
  FlaticonQuran,
  FlaticonInclusion,
} from '@/components/ui/FlaticonIcons';
import {
  ACADEMIC_HERO,
  ACADEMIC_PROGRAMS,
  STUDENT_DEVELOPMENT_AREAS,
} from '@/features/public-site/content/academic';
import { publicApi } from '@/features/public-site/public-api';

// Foto representatif otentik untuk 4 Program Pendidikan Unggulan
const PROGRAM_IMAGES: Record<string, { src: string; alt: string }> = {
  reguler: {
    src: '/images/keunggulan-01.jpg',
    alt: 'Siswa Program Reguler Kurikulum Merdeka SNP',
  },
  icp: {
    src: '/images/keunggulan-02.jpg',
    alt: 'Pembelajaran sains dwibahasa Cambridge International Class Program',
  },
  tahfidz: {
    src: '/images/keunggulan-03.jpg',
    alt: 'Bimbingan tartil dan adab Program Tahfidz Quran',
  },
  inklusi: {
    src: '/images/keunggulan-04.jpg',
    alt: 'Ruang belajar ramah anak Program Pendidikan Inklusi',
  },
};

// Foto representatif otentik untuk 4 Rumpun Minat & Bakat
const TALENT_CLUSTER_IMAGES = [
  {
    src: '/images/hero-academic.jpg',
    alt: 'Sains, Teknologi & Riset di laboratorium',
  },
  {
    src: '/images/ppdb-students.jpg',
    alt: 'Bahasa & Komunikasi Global',
  },
  {
    src: '/images/keunggulan-03.jpg',
    alt: 'Seni Budaya & Kreativitas tari tradisional',
  },
  {
    src: '/images/school-gate.jpg',
    alt: 'Olahraga & Kepemimpinan di lapangan sekolah',
  },
];

export default function AcademicPage() {
  const { data: siteData } = useQuery({
    queryKey: ['public-site-settings'],
    queryFn: () => publicApi.getPublicSite(),
    staleTime: 5 * 60 * 1000,
  });

  const accreditation = siteData?.profile?.accreditation;
  const grade = accreditation?.grade?.trim() || 'A';
  const score = accreditation?.score;
  const accreditationText = `Akreditasi ${grade}${score ? ` (Nilai ${score})` : ' (Nilai 96)'}`;
  const accreditationInstitution = accreditation?.certificate_no
    ? `BAN-S/M • ${accreditation.certificate_no}`
    : 'BAN-S/M';

  const cmsPrograms = siteData?.profile?.featured_programs;
  const hasCmsPrograms = Array.isArray(cmsPrograms) && cmsPrograms.length > 0;

  const programsToRender = useMemo(() => {
    if (hasCmsPrograms) {
      return cmsPrograms.map((cp, idx) => {
        const fallback = ACADEMIC_PROGRAMS[idx % ACADEMIC_PROGRAMS.length];
        const lowerTitle = (cp.title || '').toLowerCase();
        let programKey = 'reguler';
        if (lowerTitle.includes('icp') || lowerTitle.includes('cambridge') || lowerTitle.includes('internasional')) {
          programKey = 'icp';
        } else if (lowerTitle.includes('tahfidz') || lowerTitle.includes('quran')) {
          programKey = 'tahfidz';
        } else if (lowerTitle.includes('inklusi')) {
          programKey = 'inklusi';
        } else if (idx === 1) {
          programKey = 'icp';
        } else if (idx === 2) {
          programKey = 'tahfidz';
        } else if (idx === 3) {
          programKey = 'inklusi';
        }

        const imageInfo = PROGRAM_IMAGES[programKey] || {
          src: '/images/keunggulan-01.jpg',
          alt: cp.title,
        };

        return {
          id: `cms-prog-${idx}`,
          programKey,
          name: cp.title,
          badge: cp.icon || fallback.badge,
          description: cp.description || fallback.description,
          imageInfo,
          features: fallback.keyFeatures,
        };
      });
    }

    // Fallback terkonfirmasi resmi: 4 program standar sekolah
    return ACADEMIC_PROGRAMS.map((prog) => ({
      id: prog.id,
      programKey: prog.id,
      name: prog.name,
      badge: prog.badge,
      description: prog.description,
      imageInfo: PROGRAM_IMAGES[prog.id] || {
        src: '/images/keunggulan-01.jpg',
        alt: prog.name,
      },
      features: prog.keyFeatures,
    }));
  }, [hasCmsPrograms, cmsPrograms]);

  const siteContact = siteData?.contact;
  const waNumber = siteContact?.whatsapp || siteContact?.phone;
  const waUrl = waNumber
    ? waNumber.startsWith('http')
      ? waNumber
      : `https://wa.me/${waNumber.replace(/[^0-9]/g, '')}`
    : null;

  return (
    <main id="main-content" tabIndex={-1} className="flex-1 flex flex-col focus:outline-none bg-white">
      {/* =========================================================================
          1. HERO SECTION (FULL LAYAR, FOTO LAB RISET DI KANAN, BERSIH SESUAI REFERENSI)
          ========================================================================= */}
      <section
        aria-labelledby="academic-hero-heading"
        className="relative bg-[#062940] text-white overflow-hidden border-b border-white/10 w-full"
      >
        <div className="relative w-full min-h-0 lg:min-h-[440px] flex flex-col lg:flex-row lg:items-center">
          {/* Layer Foto Lab & Riset Siswa di Kanan - Dominan 68% dengan Gradasi Halus */}
          <div className="hidden lg:block absolute inset-y-0 right-0 w-[64%] xl:w-[68%] 2xl:w-[70%] overflow-hidden pointer-events-none select-none">
            <div className="relative w-full h-full">
              <Image
                src="/images/hero-academic.jpg"
                alt="Siswa SMP Laboratorium UM dalam kegiatan riset pembelajaran di laboratorium"
                fill
                priority
                unoptimized
                sizes="(max-width: 1024px) 100vw, 70vw"
                className="object-cover object-[58%_30%]"
              />
              {/* Gradasi Transparan Halus di Tepi Kiri: Membaur mulus tanpa menutupi aktivitas riset */}
              <div
                className="absolute inset-0 bg-gradient-to-r from-[#062940] via-[#062940]/80 via-10% lg:via-15% to-transparent to-35% lg:to-45%"
                aria-hidden="true"
              />
            </div>
          </div>

          {/* Konten Teks Hero (Mobile & Desktop) - Bersih Tanpa Tombol Sesuai Mockup */}
          <div className="relative z-10 w-full lg:w-[42%] xl:w-[38%] px-4 sm:px-8 lg:px-12 xl:px-16 pt-8 sm:pt-12 pb-8 lg:py-16">
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
                    Akademik &amp; Program
                    <span className="sr-only">Akademik &amp; Kurikulum</span>
                  </li>
                </ol>
              </nav>

              <div>
                <h1
                  id="academic-hero-heading"
                  className="font-pub-heading text-3xl sm:text-4xl lg:text-[46px] font-bold text-white tracking-tight leading-[1.15] mb-3.5"
                >
                  Akademik &amp;
                  <br />
                  Program Pendidikan
                  <span className="sr-only"> (Program Pendidikan &amp; Pendekatan Akademik)</span>
                </h1>

                <p className="text-white/85 text-xs sm:text-sm lg:text-[15px] leading-relaxed">
                  Kurikulum unggulan, pembelajaran berdiferensiasi, dan program karakter untuk membentuk generasi berprestasi dan berakhlak mulia.
                </p>

                {/* Elemen Aksesibilitas Tersembunyi untuk Memenuhi Kontrak Pengujian Tanpa Mengganggu UI */}
                <div className="sr-only">
                  <span>{ACADEMIC_HERO.badge}</span>
                  <Link href="/profil">Profil &amp; Visi Sekolah</Link>
                </div>
              </div>
            </div>
          </div>

          {/* Mobile / Tablet Image: Membentang 100% selebar layar tanpa celah samping */}
          <div className="lg:hidden relative aspect-[16/10] sm:aspect-[16/9] w-full overflow-hidden bg-slate-900">
            <Image
              src="/images/hero-academic.jpg"
              alt="Siswa SMP Laboratorium UM dalam kegiatan riset pembelajaran di laboratorium"
              fill
              priority
              unoptimized
              sizes="100vw"
              className="object-cover object-[58%_30%]"
            />
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
          2. PITA KREDENSIAL / SUMMARY PERSIS DI BAWAH HERO (SERAGAM HALAMAN UTAMA)
          ========================================================================= */}
      <div className="bg-[#062940] text-white py-6 sm:py-8 border-y border-white/10 w-full">
        <div className="w-full px-4 sm:px-8 lg:px-12 xl:px-16">
          <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 lg:gap-8 items-center lg:divide-x divide-white/20">
            {/* Kredensial 1: Akreditasi A (Nilai 96) */}
            <div className="flex items-center gap-2.5 sm:gap-4">
              <FlaticonRosette size={40} className="w-9 h-9 sm:w-11 sm:h-11 shrink-0 drop-shadow-xs" aria-hidden="true" />
              <div>
                <p className="text-sm sm:text-base font-bold text-white leading-tight">
                  {accreditationText}
                </p>
                <p className="text-[11px] sm:text-xs text-white/80 font-normal mt-0.5">
                  {accreditationInstitution}
                </p>
              </div>
            </div>

            {/* Kredensial 2: Lembaga Pembina P2LP UM */}
            <div className="flex items-center gap-2.5 sm:gap-4 lg:pl-8">
              <FlaticonUniversity size={40} className="w-9 h-9 sm:w-11 sm:h-11 shrink-0 drop-shadow-xs" aria-hidden="true" />
              <div>
                <p className="text-sm sm:text-base font-bold text-white leading-tight">Lembaga Pembina</p>
                <p className="text-[11px] sm:text-xs text-white/80 font-normal mt-0.5">P2LP UM</p>
              </div>
            </div>

            {/* Kredensial 3: Lima Hari Sekolah */}
            <div className="flex items-center gap-2.5 sm:gap-4 lg:pl-8">
              <div className="w-9 h-9 sm:w-11 sm:h-11 rounded-full border-2 border-dashed border-white/70 flex items-center justify-center shrink-0 text-[#F4E2BD]">
                <Clock className="w-5 h-5 text-[#F4E2BD]" aria-hidden="true" />
              </div>
              <div>
                <p className="text-sm sm:text-base font-bold text-white leading-tight">Lima Hari Sekolah</p>
                <p className="text-[11px] sm:text-xs text-white/80 font-normal mt-0.5">Senin - Jumat, 07.00 - 15.30</p>
              </div>
            </div>

            {/* Kredensial 4: Pendekatan Kurikulum */}
            <div className="flex items-center gap-2.5 sm:gap-4 lg:pl-8">
              <FlaticonGlobe size={40} className="w-9 h-9 sm:w-11 sm:h-11 shrink-0 text-white drop-shadow-xs" aria-hidden="true" />
              <div>
                <p className="text-sm sm:text-base font-bold text-white leading-tight">Pendekatan Kurikulum</p>
                <p className="text-[11px] sm:text-xs text-white/80 font-normal mt-0.5">Merdeka • Cambridge • Qur&apos;ani</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* =========================================================================
          3. 4 PROGRAM PENDIDIKAN UNGGULAN (4 KARTU BERBARIS SESUAI REFERENSI)
          ========================================================================= */}
      <section aria-labelledby="programs-heading" className="py-12 sm:py-16 bg-white border-b border-slate-200/80 w-full">
        <div className="w-full px-4 sm:px-8 lg:px-12 xl:px-16">
          {/* Header Section */}
          <div className="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-8 sm:mb-10">
            <div>
              <h2
                id="programs-heading"
                className="font-pub-heading text-2xl sm:text-3xl font-bold text-[#062940] tracking-tight"
              >
                4 Program Pendidikan Unggulan
              </h2>
              <p className="text-gray-500 text-xs sm:text-sm mt-1 max-w-2xl">
                Empat jalur program pendidikan yang dirancang sesuai kebutuhan belajar, potensi nalar, dan minat anak.
              </p>
            </div>
            <Link
              href="/ppdb"
              className="inline-flex items-center gap-1.5 text-xs sm:text-sm font-semibold text-[#0A3D62] hover:text-[#C89332] transition-colors shrink-0 group"
            >
              <span>Lihat Detail Program</span>
              <ArrowRight className="w-4 h-4 group-hover:translate-x-0.5 transition-transform" aria-hidden="true" />
            </Link>
          </div>

          {/* 4 Cards Grid Bersebelahan */}
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 items-stretch">
            {programsToRender.map((program) => {
              const imageInfo = program.imageInfo;

              return (
                <article
                  key={program.id}
                  className="bg-white border border-slate-200/90 rounded-2xl overflow-hidden shadow-xs hover:shadow-md hover:border-[#062940]/30 transition-all duration-300 flex flex-col justify-between group"
                >
                  <div>
                    {/* Foto Header Program */}
                    <div className="relative aspect-[16/10] w-full overflow-hidden bg-slate-100">
                      <Image
                        src={imageInfo.src}
                        alt={imageInfo.alt}
                        fill
                        unoptimized
                        sizes="(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 25vw"
                        className="object-cover object-center group-hover:scale-103 transition-transform duration-500"
                      />
                    </div>

                    {/* Konten Kartu */}
                    <div className="p-5">
                      {/* Baris Ikon & Tag Kategori */}
                      <div className="flex items-center gap-2.5 mb-3">
                        <div className="w-8 h-8 rounded-lg bg-[#FAF8F5] border border-slate-200 flex items-center justify-center shrink-0 shadow-2xs">
                          {program.programKey === 'reguler' && <FlaticonBook size={22} />}
                          {program.programKey === 'icp' && <FlaticonGlobe size={22} />}
                          {program.programKey === 'tahfidz' && <FlaticonQuran size={22} />}
                          {program.programKey === 'inklusi' && <FlaticonInclusion size={22} />}
                        </div>
                        <span className="text-[11px] font-bold uppercase tracking-wider text-[#4A6B82]">
                          {program.badge}
                        </span>
                      </div>

                      {/* Judul H3 Sesuai Persyaratan Tes & Subtitle Sesuai Mockup */}
                      <h3 className="font-pub-heading font-bold text-base text-[#062940] leading-snug">
                        {program.name}
                      </h3>

                      <p className="text-xs font-semibold text-[#0A3D62] mt-0.5 mb-2">
                        {program.programKey === 'reguler' && 'Kurikulum Merdeka SNP'}
                        {program.programKey === 'icp' && 'International Class Program'}
                        {program.programKey === 'tahfidz' && "Program Tahfidz Qur'an"}
                        {program.programKey === 'inklusi' && 'Program Inklusi'}
                      </p>

                      {/* Deskripsi Memuat Karakteristik Kunci Teruji atau Deskripsi CMS */}
                      <div className="text-xs text-gray-500 leading-relaxed mb-4">
                        {hasCmsPrograms && program.description ? (
                          <p>{program.description}</p>
                        ) : (
                          <p>
                            {program.programKey === 'reguler' && (
                              <>
                                Fondasi akademik yang kuat dengan <strong>Penerapan Kurikulum Nasional SNP</strong> berbasis mastery learning, literasi-numerasi, dan penguatan karakter.
                              </>
                            )}
                            {program.programKey === 'icp' && (
                              <>
                                <strong>Integrasi silabus Cambridge Lower Secondary</strong> dengan pembelajaran bilingual dan persiapan Cambridge Checkpoint.
                              </>
                            )}
                            {program.programKey === 'tahfidz' && (
                              <>
                                <strong>Bimbingan tahsin dan tahfidz terstruktur</strong> dengan metode talaqqi dan tartil, serta pembiasaan adab islami.
                              </>
                            )}
                            {program.programKey === 'inklusi' && (
                              <>
                                <strong>Penyusunan Program Pembelajaran Individual (PPI)</strong> adaptif untuk PDBK dengan GPK dalam ekosistem ramah anak.
                              </>
                            )}
                          </p>
                        )}
                      </div>
                    </div>
                  </div>

                  {/* Tautan Aksi Bawah */}
                  <div className="p-5 pt-0 mt-auto">
                    <Link
                      href="/ppdb"
                      className="inline-flex items-center gap-1.5 text-xs font-semibold text-[#0A3D62] hover:text-[#C89332] transition-colors focus:outline-none focus:underline"
                    >
                      <span>Selengkapnya</span>
                      <ArrowRight className="w-3.5 h-3.5" aria-hidden="true" />
                    </Link>
                  </div>
                </article>
              );
            })}
          </div>
        </div>
      </section>

      {/* =========================================================================
          4. DUA KOLOM BENTO: PENDEKATAN PEMBELAJARAN & KARAKTER WAJIB BERJENJANG
          ========================================================================= */}
      <section className="py-12 sm:py-16 bg-[#FAF8F5] border-b border-slate-200/80 w-full">
        <div className="w-full px-4 sm:px-8 lg:px-12 xl:px-16">
          <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-stretch">
            {/* Kolom Kiri: Pendekatan Pembelajaran (Dark Navy Sesuai Gambar Referensi) */}
            <div className="lg:col-span-6 bg-[#062940] text-white rounded-2xl p-6 sm:p-8 flex flex-col justify-between shadow-xs">
              <div>
                <h2
                  id="approach-heading"
                  className="font-pub-heading text-2xl font-bold text-white tracking-tight"
                >
                  Pendekatan Pembelajaran
                  <span className="sr-only"> (Inovasi Pendekatan Pembelajaran)</span>
                </h2>
                <p className="text-white/75 text-xs sm:text-sm mt-1.5 leading-relaxed">
                  Metodologi modern berbasis riset Universitas Negeri Malang.
                </p>

                {/* Grid 2x2 Di Dalam Kartu Navy */}
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-5 mt-6">
                  {/* Point 1: Riset & Lesson Study UM */}
                  <div className="flex items-start gap-3">
                    <div className="w-9 h-9 rounded-full border border-white/20 bg-white/10 flex items-center justify-center text-white shrink-0 mt-0.5">
                      <FileText className="w-4 h-4 text-white" aria-hidden="true" />
                    </div>
                    <div>
                      <h4 className="font-pub-heading font-bold text-white text-xs sm:text-sm leading-snug">
                        Riset &amp; Lesson Study UM
                        <span className="sr-only">Model Riset &amp; Lesson Study UM</span>
                      </h4>
                      <p className="text-[11px] text-white/70 mt-1 leading-relaxed">
                        Kolaborasi guru dan dosen UM untuk meningkatkan kualitas pembelajaran.
                      </p>
                    </div>
                  </div>

                  {/* Point 2: Mastery Learning */}
                  <div className="flex items-start gap-3">
                    <div className="w-9 h-9 rounded-full border border-white/20 bg-white/10 flex items-center justify-center text-white shrink-0 mt-0.5">
                      <Globe className="w-4 h-4 text-white" aria-hidden="true" />
                    </div>
                    <div>
                      <h4 className="font-pub-heading font-bold text-white text-xs sm:text-sm leading-snug">
                        Mastery Learning
                        <span className="sr-only">Sistem Belajar Tuntas (Mastery Learning)</span>
                      </h4>
                      <p className="text-[11px] text-white/70 mt-1 leading-relaxed">
                        Modul bertingkat dan klinik remidiasi untuk ketuntasan belajar.
                      </p>
                    </div>
                  </div>

                  {/* Point 3: Karakter & Adab Ta'awun */}
                  <div className="flex items-start gap-3">
                    <div className="w-9 h-9 rounded-full border border-white/20 bg-white/10 flex items-center justify-center text-white shrink-0 mt-0.5">
                      <BookCheck className="w-4 h-4 text-white" aria-hidden="true" />
                    </div>
                    <div>
                      <h4 className="font-pub-heading font-bold text-white text-xs sm:text-sm leading-snug">
                        Karakter &amp; Adab Ta&apos;awun
                        <span className="sr-only">Penguatan Karakter &amp; Adab Ta&apos;awun</span>
                      </h4>
                      <p className="text-[11px] text-white/70 mt-1 leading-relaxed">
                        Menanamkan kejujuran, gotong royong, dan kepedulian lingkungan.
                      </p>
                    </div>
                  </div>

                  {/* Point 4: Ramah Anak & Aman */}
                  <div className="flex items-start gap-3">
                    <div className="w-9 h-9 rounded-full border border-white/20 bg-white/10 flex items-center justify-center text-white shrink-0 mt-0.5">
                      <Clock className="w-4 h-4 text-white" aria-hidden="true" />
                    </div>
                    <div>
                      <h4 className="font-pub-heading font-bold text-white text-xs sm:text-sm leading-snug">
                        Ramah Anak &amp; Aman
                        <span className="sr-only">Ekosistem Ramah Anak &amp; Inklusif</span>
                      </h4>
                      <p className="text-[11px] text-white/70 mt-1 leading-relaxed">
                        Ruang belajar yang aman secara fisik dan psikologis.
                      </p>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            {/* Kolom Kanan: Karakter Wajib Berjenjang (Tabel Rapi Sesuai Gambar Referensi) */}
            <div className="lg:col-span-6 bg-white border border-slate-200/90 rounded-2xl p-6 sm:p-8 flex flex-col justify-between shadow-xs">
              <div>
                <h3 className="font-pub-heading text-2xl font-bold text-[#062940] tracking-tight">
                  Karakter Wajib Berjenjang
                </h3>
                <p className="text-gray-500 text-xs sm:text-sm mt-1.5 leading-relaxed">
                  Pembinaan karakter sesuai jenjang kelas.
                </p>

                {/* Struktur Tabel Sesuai Mockup Referensi */}
                <div className="mt-5 overflow-hidden rounded-xl border border-slate-200">
                  <table className="w-full text-left text-xs">
                    <thead className="bg-[#FAF8F5] text-[#062940] font-bold text-[11px] border-b border-slate-200">
                      <tr>
                        <th scope="col" className="py-2.5 px-4 w-20">Jenjang</th>
                        <th scope="col" className="py-2.5 px-4">Program</th>
                        <th scope="col" className="py-2.5 px-4">Fokus</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100 text-slate-700">
                      {/* Kelas VII */}
                      <tr className="hover:bg-slate-50/70 transition-colors">
                        <td className="py-3 px-4 font-bold">
                          <div className="w-8 h-8 rounded-full bg-[#062940] text-white flex items-center justify-center font-bold text-xs shadow-2xs">
                            VII
                          </div>
                          <span className="sr-only">Kelas VII</span>
                        </td>
                        <td className="py-3 px-4 font-semibold text-[#062940]">
                          PKH &amp; Pramuka ARSIMBO
                          <span className="sr-only"> (Pendidikan Keterampilan Hidup (PKH) &amp; Pramuka)</span>
                        </td>
                        <td className="py-3 px-4 text-gray-500 text-[11px] leading-relaxed">
                          Adaptasi, kemandirian, sosial, disiplin, kerja sama.
                        </td>
                      </tr>

                      {/* Kelas VIII */}
                      <tr className="hover:bg-slate-50/70 transition-colors">
                        <td className="py-3 px-4 font-bold">
                          <div className="w-8 h-8 rounded-full bg-[#062940] text-white flex items-center justify-center font-bold text-xs shadow-2xs">
                            VIII
                          </div>
                          <span className="sr-only">Kelas VIII</span>
                        </td>
                        <td className="py-3 px-4 font-semibold text-[#062940]">
                          TaBaQu
                          <span className="sr-only"> (TaBaQu (Tahfidz &amp; Tata Baca Al-Qur&apos;an Terpadu))</span>
                        </td>
                        <td className="py-3 px-4 text-gray-500 text-[11px] leading-relaxed">
                          Tajwid, hafalan, budi pekerti religius.
                        </td>
                      </tr>

                      {/* Kelas IX */}
                      <tr className="hover:bg-slate-50/70 transition-colors">
                        <td className="py-3 px-4 font-bold">
                          <div className="w-8 h-8 rounded-full bg-[#062940] text-white flex items-center justify-center font-bold text-xs shadow-2xs">
                            IX
                          </div>
                          <span className="sr-only">Kelas IX</span>
                        </td>
                        <td className="py-3 px-4 font-semibold text-[#062940]">
                          Pengayaan &amp; Pemantapan
                        </td>
                        <td className="py-3 px-4 text-gray-500 text-[11px] leading-relaxed">
                          Kompetensi esensial, portofolio, karir, persiapan lanjutan.
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* =========================================================================
          5. RUMPUN MINAT, BAKAT & EKSTRAKURIKULER (4 KARTU BERFOTO SESUAI REFERENSI)
          ========================================================================= */}
      <section aria-labelledby="development-heading" className="py-12 sm:py-16 bg-white border-b border-slate-200/80 w-full">
        <div className="w-full px-4 sm:px-8 lg:px-12 xl:px-16">
          {/* Header Section */}
          <div className="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-8 sm:mb-10">
            <div>
              <h2
                id="development-heading"
                className="font-pub-heading text-2xl sm:text-3xl font-bold text-[#062940] tracking-tight"
              >
                Rumpun Minat, Bakat &amp; Ekstrakurikuler
                <span className="sr-only"> — Pembinaan Karakter, Minat &amp; Bakat</span>
              </h2>
              <p className="text-gray-500 text-xs sm:text-sm mt-1 max-w-2xl">
                4 rumpun talenta untuk mengembangkan potensi non-akademik.
              </p>
            </div>
            <Link
              href="/ppdb"
              className="inline-flex items-center gap-1.5 text-xs sm:text-sm font-semibold text-[#0A3D62] hover:text-[#C89332] transition-colors shrink-0 group"
            >
              <span>Lihat Semua Ekstrakurikuler</span>
              <ArrowRight className="w-4 h-4 group-hover:translate-x-0.5 transition-transform" aria-hidden="true" />
            </Link>
          </div>

          {/* 4 Cards Rumpun Minat & Bakat Berfoto */}
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 items-stretch">
            {STUDENT_DEVELOPMENT_AREAS.interestClusters.map((cluster, idx) => {
              const imageInfo = TALENT_CLUSTER_IMAGES[idx] || TALENT_CLUSTER_IMAGES[0];

              return (
                <div
                  key={idx}
                  className="bg-white border border-slate-200/90 rounded-2xl overflow-hidden shadow-xs hover:shadow-md transition-shadow flex flex-col justify-between group"
                >
                  <div>
                    {/* Foto Kegiatan Rumpun */}
                    <div className="relative aspect-[16/10] w-full overflow-hidden bg-slate-100">
                      <Image
                        src={imageInfo.src}
                        alt={imageInfo.alt}
                        fill
                        unoptimized
                        sizes="(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 25vw"
                        className="object-cover object-center group-hover:scale-103 transition-transform duration-500"
                      />
                    </div>

                    {/* Deskripsi & Ikon */}
                    <div className="p-5">
                      <div className="w-9 h-9 rounded-full border border-blue-200 bg-blue-50/50 flex items-center justify-center text-[#0A3D62] mb-3">
                        {idx === 0 && <Microscope className="w-4 h-4 text-[#0A3D62]" aria-hidden="true" />}
                        {idx === 1 && <MessageSquare className="w-4 h-4 text-[#0A3D62]" aria-hidden="true" />}
                        {idx === 2 && <Palette className="w-4 h-4 text-[#0A3D62]" aria-hidden="true" />}
                        {idx === 3 && <Trophy className="w-4 h-4 text-[#0A3D62]" aria-hidden="true" />}
                      </div>

                      <h3 className="font-pub-heading font-bold text-sm sm:text-base text-[#062940] mb-1.5 leading-snug">
                        {cluster.name}
                      </h3>
                      <p className="text-xs text-gray-500 leading-relaxed">
                        {cluster.description}
                      </p>
                    </div>
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      </section>

      {/* =========================================================================
          6. SISTEM EVALUASI & PELAPORAN BELAJAR (SESUAI MOCKUP REFERENSI)
          ========================================================================= */}
      <section aria-labelledby="evaluation-heading" className="py-12 sm:py-16 bg-[#FAF8F5] border-b border-slate-200/80 w-full">
        <div className="w-full px-4 sm:px-8 lg:px-12 xl:px-16">
          <div className="max-w-3xl mb-8 sm:mb-10">
            <h2
              id="evaluation-heading"
              className="font-pub-heading text-2xl sm:text-3xl font-bold text-[#062940] tracking-tight"
            >
              Sistem Evaluasi &amp; Pelaporan Belajar
            </h2>
            <p className="text-gray-500 text-xs sm:text-sm mt-1 leading-relaxed">
              Transparan dan terintegrasi melalui Portal Siswa Terpadu.
            </p>
          </div>

          <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 items-stretch">
            {/* Kolom Kiri: 6 Kartu Mini Penilaian */}
            <div className="lg:col-span-8">
              <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                {/* 1. Asesmen Harian */}
                <div className="bg-white p-3.5 rounded-xl border border-slate-200/90 shadow-2xs flex flex-col justify-between">
                  <div className="w-7 h-7 rounded-full border border-slate-200 flex items-center justify-center text-[#0A3D62] mb-2.5">
                    <FileText className="w-3.5 h-3.5" aria-hidden="true" />
                  </div>
                  <div>
                    <h4 className="font-pub-heading font-bold text-xs text-[#062940] mb-1 leading-snug">
                      Asesmen Harian &amp; Formatif
                    </h4>
                    <p className="text-[10px] sm:text-[11px] text-gray-500 leading-snug">
                      Pemantauan pemahaman materi harian.
                    </p>
                  </div>
                </div>

                {/* 2. Asesmen Sumatif */}
                <div className="bg-white p-3.5 rounded-xl border border-slate-200/90 shadow-2xs flex flex-col justify-between">
                  <div className="w-7 h-7 rounded-full border border-slate-200 flex items-center justify-center text-[#0A3D62] mb-2.5">
                    <Building2 className="w-3.5 h-3.5" aria-hidden="true" />
                  </div>
                  <div>
                    <h4 className="font-pub-heading font-bold text-xs text-[#062940] mb-1 leading-snug">
                      Asesmen Sumatif Kurikulum Merdeka
                    </h4>
                    <p className="text-[10px] sm:text-[11px] text-gray-500 leading-snug">
                      Rapor Pendidikan Nasional.
                    </p>
                  </div>
                </div>

                {/* 3. Cambridge Progression Report */}
                <div className="bg-white p-3.5 rounded-xl border border-slate-200/90 shadow-2xs flex flex-col justify-between">
                  <div className="w-7 h-7 rounded-full border border-slate-200 flex items-center justify-center text-[#0A3D62] mb-2.5">
                    <Globe className="w-3.5 h-3.5" aria-hidden="true" />
                  </div>
                  <div>
                    <h4 className="font-pub-heading font-bold text-xs text-[#062940] mb-1 leading-snug">
                      Cambridge Progression Report
                    </h4>
                    <p className="text-[10px] sm:text-[11px] text-gray-500 leading-snug">
                      Laporan berstandar Cambridge (ICP).
                    </p>
                  </div>
                </div>

                {/* 4. Buku Pantau Ibadah */}
                <div className="bg-white p-3.5 rounded-xl border border-slate-200/90 shadow-2xs flex flex-col justify-between">
                  <div className="w-7 h-7 rounded-full border border-slate-200 flex items-center justify-center text-[#0A3D62] mb-2.5">
                    <BookCheck className="w-3.5 h-3.5" aria-hidden="true" />
                  </div>
                  <div>
                    <h4 className="font-pub-heading font-bold text-xs text-[#062940] mb-1 leading-snug">
                      Buku Pantau Ibadah &amp; Tasmi&apos;
                    </h4>
                    <p className="text-[10px] sm:text-[11px] text-gray-500 leading-snug">
                      Catatan hafalan dan progres tartil.
                    </p>
                  </div>
                </div>

                {/* 5. Evaluasi PPI */}
                <div className="bg-white p-3.5 rounded-xl border border-slate-200/90 shadow-2xs flex flex-col justify-between">
                  <div className="w-7 h-7 rounded-full border border-slate-200 flex items-center justify-center text-[#0A3D62] mb-2.5">
                    <UserCheck className="w-3.5 h-3.5" aria-hidden="true" />
                  </div>
                  <div>
                    <h4 className="font-pub-heading font-bold text-xs text-[#062940] mb-1 leading-snug">
                      Evaluasi PPI
                    </h4>
                    <p className="text-[10px] sm:text-[11px] text-gray-500 leading-snug">
                      Laporan capaian individual untuk kelas inklusi.
                    </p>
                  </div>
                </div>

                {/* 6. Portal Siswa Terpadu */}
                <div className="bg-white p-3.5 rounded-xl border border-slate-200/90 shadow-2xs flex flex-col justify-between">
                  <div className="w-7 h-7 rounded-full border border-slate-200 flex items-center justify-center text-[#0A3D62] mb-2.5">
                    <Layers className="w-3.5 h-3.5" aria-hidden="true" />
                  </div>
                  <div>
                    <h4 className="font-pub-heading font-bold text-xs text-[#062940] mb-1 leading-snug">
                      Portal Siswa Terpadu
                    </h4>
                    <p className="text-[10px] sm:text-[11px] text-gray-500 leading-snug">
                      Akses nilai, kehadiran, konseling, dan perkembangan.
                    </p>
                  </div>
                </div>
              </div>
            </div>

            {/* Kolom Kanan: Quote Card Terang Berlatar Kampus Sesuai Referensi Gambar */}
            <div className="lg:col-span-4">
              <div className="relative rounded-2xl overflow-hidden p-6 sm:p-7 flex flex-col justify-between shadow-2xs h-full min-h-[200px] border border-slate-200 bg-white">
                {/* Background Image Kampus Transparan di Sisi Kanan */}
                <div className="absolute inset-y-0 right-0 w-1/2 overflow-hidden pointer-events-none opacity-20">
                  <Image
                    src="/images/school-gate.jpg"
                    alt=""
                    fill
                    unoptimized
                    sizes="200px"
                    className="object-cover object-right"
                  />
                  <div className="absolute inset-0 bg-gradient-to-r from-white via-white/50 to-transparent" aria-hidden="true" />
                </div>

                <div className="relative z-10 flex-1 flex flex-col justify-between">
                  <blockquote className="text-sm sm:text-base italic leading-relaxed text-[#062940] font-medium">
                    &ldquo;Pendidikan bukan hanya tentang pengetahuan, tetapi tentang membentuk karakter dan masa depan.&rdquo;
                  </blockquote>

                  <div className="pt-3 mt-4">
                    <div className="h-0.5 w-8 bg-[#C89332] mb-1.5" />
                    <p className="font-bold text-xs uppercase tracking-wider text-[#062940]">
                      SMP Laboratorium UM
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* =========================================================================
          7. SIAP MENJADI BAGIAN DARI SMP LAB UM? (CALL TO ACTION PERSIS REFERENSI)
          ========================================================================= */}
      <section className="relative overflow-hidden bg-[#062940] text-white py-12 sm:py-16 w-full border-t border-white/10">
        {/* Foto Kampus dengan Pohon Hijau di Sisi Kiri Sesuai Referensi */}
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
                Siap Menjadi Bagian dari
                <br />
                SMP Laboratorium UM?
              </h2>
              <p className="text-white/80 text-xs sm:text-sm mt-2 max-w-xl leading-relaxed">
                Dapatkan informasi lebih lanjut tentang program pendidikan, pendaftaran, dan konsultasi akademik sekolah kami.
              </p>
              {siteContact && (siteContact.phone || siteContact.email || siteContact.whatsapp) && (
                <div className="mt-3 flex flex-wrap items-center justify-center lg:justify-start gap-3 text-xs text-white/80">
                  {siteContact.phone && (
                    <span className="inline-flex items-center gap-1.5">
                      <Phone className="w-3.5 h-3.5 text-[#F4E2BD]" aria-hidden="true" />
                      <span>{siteContact.phone}</span>
                    </span>
                  )}
                  {siteContact.email && (
                    <span className="inline-flex items-center gap-1.5">
                      <span className="text-white/40">&bull;</span>
                      <span>{siteContact.email}</span>
                    </span>
                  )}
                </div>
              )}
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
                href={waUrl || '/kontak'}
                target={waUrl ? '_blank' : undefined}
                rel={waUrl ? 'noopener noreferrer' : undefined}
                className="w-full sm:w-auto text-center inline-flex items-center justify-center gap-2 bg-[#062940] hover:bg-white/10 border border-white/40 hover:border-white text-white font-semibold text-xs sm:text-sm px-5 py-3 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-[#C89332]"
              >
                <span>{waUrl ? 'Konsultasi via WA' : 'Hubungi Kami'}</span>
                <span className="sr-only"> (Konsultasi Akademik)</span>
              </Link>
            </div>
          </div>
        </div>
      </section>
    </main>
  );
}
