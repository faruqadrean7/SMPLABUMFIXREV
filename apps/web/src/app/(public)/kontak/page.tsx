'use client';

import React from 'react';
import Link from 'next/link';
import Image from 'next/image';
import { useQuery } from '@tanstack/react-query';
import {
  MapPin,
  Phone,
  Mail,
  Clock,
  ArrowRight,
  MessageCircle,
  CheckCircle2,
  Calendar,
  MessageSquare,
} from 'lucide-react';
import {
  CONTACT_HERO,
  VERIFIED_CONTACT_DETAILS,
  OFFICE_HOURS,
  DirectContactChannel,
} from '@/features/public-site/content/contact';
import { CopyContactButton } from '@/features/public-site/components/CopyContactButton';
import { publicApi } from '@/features/public-site/public-api';
import { formatPhoneLink, formatWaLink } from '@/features/public-site/utils';

export default function ContactPage() {
  const { data: siteData } = useQuery({
    queryKey: ['public-site-settings'],
    queryFn: () => publicApi.getPublicSite(),
    staleTime: 5 * 60 * 1000,
  });

  // Derived dynamic contact details with verified fallbacks
  const phoneDisplay = siteData?.contact?.phone || VERIFIED_CONTACT_DETAILS.phone.display;
  const phoneTel = `tel:${formatPhoneLink(phoneDisplay)}`;

  const waDisplay = siteData?.contact?.whatsapp || VERIFIED_CONTACT_DETAILS.whatsapp.display;
  const waUrl = `https://wa.me/${formatWaLink(waDisplay)}`;

  const emailDisplay = siteData?.contact?.email || VERIFIED_CONTACT_DETAILS.email.display;
  const emailMailto = `mailto:${emailDisplay}`;

  const addressFull = siteData?.address?.street
    ? `${siteData.address.street}${siteData.address.city ? ', ' + siteData.address.city : ''}${siteData.address.postal_code ? ' ' + siteData.address.postal_code : ''}`
    : VERIFIED_CONTACT_DETAILS.address.full;

  const schoolName = siteData?.identity?.name || VERIFIED_CONTACT_DETAILS.schoolName;

  const mapsQueryUrl = siteData?.address?.street
    ? `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent([schoolName, siteData.address.street, siteData.address.city].filter(Boolean).join(' '))}`
    : VERIFIED_CONTACT_DETAILS.address.mapsQueryUrl;

  const mapsEmbedUrl = siteData?.address?.maps_embed_url || VERIFIED_CONTACT_DETAILS.address.mapsEmbedUrl;

  const socialLinks = React.useMemo(() => {
    if (!siteData?.social_media) return [];
    const sm = siteData.social_media;
    const items: { platform: string; url: string; handle: string }[] = [];
    if (sm.instagram) items.push({ platform: 'Instagram', url: sm.instagram.startsWith('http') ? sm.instagram : `https://instagram.com/${sm.instagram.replace('@', '')}`, handle: sm.instagram });
    if (sm.youtube) items.push({ platform: 'YouTube', url: sm.youtube.startsWith('http') ? sm.youtube : `https://youtube.com/${sm.youtube}`, handle: sm.youtube });
    if (sm.facebook) items.push({ platform: 'Facebook', url: sm.facebook.startsWith('http') ? sm.facebook : `https://facebook.com/${sm.facebook}`, handle: sm.facebook });
    if (sm.twitter) items.push({ platform: 'Twitter / X', url: sm.twitter.startsWith('http') ? sm.twitter : `https://twitter.com/${sm.twitter.replace('@', '')}`, handle: sm.twitter });
    if (sm.tiktok) items.push({ platform: 'TikTok', url: sm.tiktok.startsWith('http') ? sm.tiktok : `https://tiktok.com/@${sm.tiktok.replace('@', '')}`, handle: sm.tiktok });
    return items;
  }, [siteData?.social_media]);

  const directChannels: DirectContactChannel[] = [
    {
      id: 'whatsapp-ppdb',
      title: 'WhatsApp PPDB Online',
      subtitle: 'Konsultasi langsung syarat pendaftaran, alur gelombang, dan kuota.',
      value: waDisplay,
      displayValue: waDisplay,
      href: waUrl,
      actionLabel: 'Chat WhatsApp PPDB',
      type: 'whatsapp',
      badge: 'Respon Cepat',
    },
    {
      id: 'telepon-kantor',
      title: 'Telepon Kantor Tata Usaha',
      subtitle: 'Informasi administrasi persuratan, mutasi, legalisasi dokumen, dan koordinasi dinas.',
      value: phoneDisplay,
      displayValue: phoneDisplay,
      href: phoneTel,
      actionLabel: 'Telepon Tata Usaha',
      type: 'phone',
      badge: 'Jam Kerja',
    },
    {
      id: 'email-resmi',
      title: 'Email Kedinasan',
      subtitle: 'Pengiriman surat dinas resmi, proposal kerja sama, dan permohonan kemitraan kampus.',
      value: emailDisplay,
      displayValue: emailDisplay,
      href: emailMailto,
      actionLabel: 'Kirim Email Resmi',
      type: 'email',
      badge: 'Email Resmi',
    },
  ];

  return (
    <main id="main-content" tabIndex={-1} className="flex-1 flex flex-col focus:outline-none bg-slate-50">
      {/* =========================================================================
          1. HERO SECTION (FULL-BLEED NAVY STYLE DENGAN FOTO GERBANG SEKOLAH)
          ========================================================================= */}
      <section
        aria-labelledby="contact-hero-heading"
        className="relative bg-[#062940] text-white overflow-hidden border-b border-white/10 w-full"
      >
        <div className="relative w-full min-h-0 lg:min-h-[440px] flex flex-col lg:flex-row lg:items-center">
          {/* Layer Foto Gerbang Sekolah di Kanan - Dominan 60% dengan Gradasi Halus Persis Referensi */}
          <div className="hidden lg:block absolute inset-y-0 right-0 w-[58%] xl:w-[62%] 2xl:w-[65%] overflow-hidden pointer-events-none select-none">
            <div className="relative w-full h-full">
              <Image
                src="/images/school-gate.jpg"
                alt="Gedung dan gerbang utama SMP Laboratorium Universitas Negeri Malang"
                fill
                priority
                unoptimized
                sizes="(max-width: 1024px) 100vw, 65vw"
                className="object-cover object-center"
              />
              {/* Gradasi Transparan Halus di Tepi Kiri: Membaur mulus tanpa menutupi plang nama */}
              <div
                className="absolute inset-0 bg-gradient-to-r from-[#062940] via-[#062940]/80 via-10% lg:via-15% to-transparent to-35% lg:to-45%"
                aria-hidden="true"
              />
            </div>
          </div>

          {/* Konten Teks Hero (Mobile & Desktop) */}
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
                    Kontak
                  </li>
                </ol>
              </nav>

              {/* Badge Pill Emas Persis Referensi */}
              <div className="inline-flex items-center gap-2 bg-[#C89332] text-white text-xs font-semibold px-4 py-1.5 rounded-full w-fit shadow-2xs">
                <span>Layanan &amp; Informasi Resmi</span>
              </div>

              <div>
                <h1
                  id="contact-hero-heading"
                  className="font-pub-heading text-3xl sm:text-4xl lg:text-[46px] font-bold text-white tracking-tight leading-[1.15] mb-3.5"
                >
                  Hubungi &amp;
                  <br />
                  Kunjungi Kami
                </h1>

                <p className="text-white/85 text-xs sm:text-sm lg:text-[15px] leading-relaxed">
                  Kami siap membantu Anda untuk mendapatkan informasi seputar PPDB, layanan tata usaha, maupun koordinasi kemitraan pendidikan di SMP Laboratorium UM.
                </p>
              </div>
            </div>
          </div>

          {/* Mobile / Tablet Image: Membentang 100% selebar layar tanpa celah samping */}
          <div className="lg:hidden relative aspect-[16/10] sm:aspect-[16/9] w-full overflow-hidden bg-slate-900">
            <Image
              src="/images/school-gate.jpg"
              alt="Gedung dan gerbang utama SMP Laboratorium Universitas Negeri Malang"
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
          2. KANAL LAYANAN CEPAT (1 KOLOM JUDUL + 3 KARTU AKSI LANGSUNG)
          ========================================================================= */}
      <section aria-label="Kanal Layanan Cepat" className="py-12 sm:py-16 bg-white border-b border-gray-100 w-full">
        <div className="w-full px-4 sm:px-8 lg:px-12 xl:px-16">
          <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
            {/* Kolom Kiri: Judul Seksi (Span 3) */}
            <div className="lg:col-span-3 space-y-2">
              <h2 className="font-pub-heading text-2xl sm:text-3xl font-bold text-[#062940] leading-tight">
                Kanal Layanan Cepat
              </h2>
              <p className="text-gray-500 text-xs sm:text-sm leading-relaxed">
                Pilih kanal komunikasi yang paling sesuai dengan kebutuhan Anda.
              </p>
            </div>

            {/* Kolom Kanan: 3 Kartu Aksi Cepat (Span 9) */}
            <div className="lg:col-span-9 grid grid-cols-1 md:grid-cols-3 gap-6">
              {directChannels.map((channel) => {
                const isWa = channel.type === 'whatsapp';
                const isPhone = channel.type === 'phone';
                const isEmail = channel.type === 'email';

                const iconBg = isWa
                  ? 'bg-[#E8F5E9] text-[#2E7D32]'
                  : isPhone
                  ? 'bg-[#EBF3F8] text-[#0A3D62]'
                  : 'bg-[#FEF6E9] text-[#C89332]';

                const badgeStyle = isWa
                  ? 'bg-[#E8F5E9] text-[#2E7D32]'
                  : isPhone
                  ? 'bg-[#EBF3F8] text-[#0A3D62]'
                  : 'bg-[#FEF6E9] text-[#9A6700]';

                const btnStyle = isWa
                  ? 'bg-[#1E7A46] hover:bg-[#166037] text-white focus:ring-[#1E7A46]'
                  : isPhone
                  ? 'bg-[#062940] hover:bg-[#0A3D62] text-white focus:ring-[#062940]'
                  : 'bg-[#C89332] hover:bg-[#B57C23] text-white focus:ring-[#C89332]';

                return (
                  <div
                    key={channel.id}
                    className="bg-white border border-gray-200/90 rounded-2xl p-6 flex flex-col justify-between shadow-xs hover:shadow-md transition-all relative overflow-hidden group"
                  >
                    <div>
                      {/* Baris Ikon & Badge */}
                      <div className="flex items-center justify-between mb-4">
                        <div className={`w-12 h-12 rounded-full flex items-center justify-center ${iconBg}`}>
                          {isWa && <MessageCircle className="w-6 h-6" aria-hidden="true" />}
                          {isPhone && <Phone className="w-6 h-6" aria-hidden="true" />}
                          {isEmail && <Mail className="w-6 h-6" aria-hidden="true" />}
                        </div>
                        <span className={`text-[11px] font-semibold px-2.5 py-0.5 rounded-full ${badgeStyle}`}>
                          {channel.badge}
                        </span>
                      </div>

                      {/* Judul & Keterangan */}
                      <h3 className="font-pub-heading font-bold text-base sm:text-lg text-[#062940] mb-2 leading-snug">
                        {channel.title}
                      </h3>
                      <p className="text-gray-500 text-xs leading-relaxed mb-5">
                        {channel.subtitle}
                      </p>

                      {/* Baris Nilai & Tombol Salin */}
                      <div className="flex items-center justify-between bg-slate-50 px-3.5 py-2.5 rounded-xl border border-gray-200/80 mb-5">
                        <span className="font-mono text-xs sm:text-sm font-semibold text-gray-800 select-all">
                          {channel.displayValue}
                        </span>
                        <CopyContactButton textToCopy={channel.value} label={channel.title} />
                      </div>
                    </div>

                    {/* Tombol Aksi Langsung */}
                    <div>
                      <a
                        href={channel.href}
                        target={isWa ? '_blank' : undefined}
                        rel={isWa ? 'noopener noreferrer' : undefined}
                        className={`w-full inline-flex items-center justify-center gap-2 font-semibold text-xs sm:text-sm px-4 py-2.5 rounded-xl transition-all shadow-2xs hover:shadow-xs focus:outline-none focus:ring-2 focus:ring-offset-2 ${btnStyle}`}
                      >
                        <span>{channel.actionLabel}</span>
                        <ArrowRight className="w-4 h-4" aria-hidden="true" />
                      </a>
                    </div>
                  </div>
                );
              })}
            </div>
          </div>
        </div>
      </section>

      {/* =========================================================================
          3. INFORMASI KONTAK RESMI & JAM LAYANAN (3 KARTU BERDAMPINGAN)
          ========================================================================= */}
      <section aria-label="Informasi Kontak Resmi" className="py-12 sm:py-16 bg-slate-50/70 border-b border-gray-200/80 w-full">
        <div className="w-full px-4 sm:px-8 lg:px-12 xl:px-16">
          {/* Header Seksi */}
          <div className="mb-8">
            <span className="text-[11px] font-bold uppercase tracking-wider text-[#0A3D62] block mb-1">
              Sekretariat &amp; Jam Layanan
            </span>
            <h2 className="font-pub-heading text-2xl sm:text-3xl font-bold text-[#062940]">
              Informasi Kontak Resmi
            </h2>
          </div>

          {/* Grid 3 Kartu Berdampingan */}
          <div className="grid grid-cols-1 md:grid-cols-3 gap-6 items-stretch">
            {/* KARTU 1: ALAMAT KAMPUS */}
            <div className="bg-white border border-gray-200/90 rounded-2xl p-6 shadow-xs flex flex-col justify-between">
              <div>
                <div className="flex items-center gap-3 mb-4">
                  <div className="w-10 h-10 rounded-full bg-[#EBF3F8] text-[#0A3D62] flex items-center justify-center shrink-0">
                    <MapPin className="w-5 h-5" aria-hidden="true" />
                  </div>
                  <h3 className="font-pub-heading font-bold text-base text-[#062940]">
                    Alamat Kampus
                  </h3>
                </div>

                <div className="space-y-1 mb-4">
                  <p className="text-gray-700 text-xs sm:text-sm leading-relaxed font-medium">
                    {addressFull}
                  </p>
                  <p className="text-gray-500 text-xs">
                    {VERIFIED_CONTACT_DETAILS.address.campusArea}
                  </p>
                </div>
              </div>

              <div className="space-y-2 pt-4 border-t border-gray-100">
                <CopyContactButton
                  textToCopy={addressFull}
                  label="Alamat Kampus"
                  customText="Salin Alamat"
                  className="w-full justify-center py-2"
                />
                <a
                  href={mapsQueryUrl}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2 text-xs font-semibold rounded-lg bg-[#062940] text-white hover:bg-[#0A3D62] transition-colors focus:outline-none focus:ring-2 focus:ring-[#C89332]"
                >
                  <span>Buka di Google Maps</span>
                  <ArrowRight className="w-3.5 h-3.5" aria-hidden="true" />
                </a>
              </div>
            </div>

            {/* KARTU 2: JAM OPERASIONAL PELAYANAN */}
            <div className="bg-white border border-gray-200/90 rounded-2xl p-6 shadow-xs flex flex-col justify-between">
              <div>
                <div className="flex items-center gap-3 mb-4">
                  <div className="w-10 h-10 rounded-full bg-[#EBF3F8] text-[#0A3D62] flex items-center justify-center shrink-0">
                    <Clock className="w-5 h-5" aria-hidden="true" />
                  </div>
                  <h3 className="font-pub-heading font-bold text-base text-[#062940]">
                    Jam Operasional Pelayanan
                  </h3>
                </div>

                <div className="divide-y divide-gray-100 text-xs">
                  {OFFICE_HOURS.map((hour, idx) => (
                    <div key={idx} className="py-3 first:pt-0 last:pb-0 space-y-1">
                      <div className="flex items-center justify-between gap-2">
                        <span className="font-semibold text-gray-800 flex items-center gap-1.5">
                          <Calendar className="w-3.5 h-3.5 text-[#C89332]" aria-hidden="true" />
                          <span>{hour.days}</span>
                        </span>
                        <span
                          className={`font-mono font-bold px-2 py-0.5 rounded text-[11px] ${
                            hour.hours === 'Tutup'
                              ? 'text-red-600 bg-red-50'
                              : hour.isSpecial
                              ? 'text-[#9A6700] bg-amber-50'
                              : 'text-[#062940] bg-slate-100'
                          }`}
                        >
                          {hour.hours}
                        </span>
                      </div>
                      <p className="text-gray-500 text-[11px] leading-relaxed">
                        {hour.description}
                      </p>
                    </div>
                  ))}
                </div>
              </div>
            </div>

            {/* KARTU 3: PANDUAN KONSULTASI */}
            <div className="bg-white border border-gray-200/90 rounded-2xl p-6 shadow-xs flex flex-col justify-between">
              <div>
                <div className="flex items-center gap-3 mb-4">
                  <div className="w-10 h-10 rounded-full bg-[#EBF3F8] text-[#0A3D62] flex items-center justify-center shrink-0">
                    <MessageSquare className="w-5 h-5" aria-hidden="true" />
                  </div>
                  <h3 className="font-pub-heading font-bold text-base text-[#062940]">
                    <span className="sr-only">Panduan Layanan &amp; Konsultasi</span>
                    <span aria-hidden="true">Panduan Konsultasi</span>
                  </h3>
                </div>

                <p className="text-gray-500 text-xs leading-relaxed mb-4">
                  Agar proses komunikasi lebih cepat dan tepat, silakan gunakan kanal sesuai kebutuhan Anda:
                </p>

                <div className="space-y-3.5 text-xs">
                  {/* Item 1: PPDB */}
                  <div className="flex items-start gap-2.5">
                    <CheckCircle2 className="w-4 h-4 text-[#1E7A46] shrink-0 mt-0.5" aria-hidden="true" />
                    <div>
                      <h4 className="font-semibold text-gray-800">
                        <span>PPDB / Pendaftaran Siswa Baru</span>
                        <span className="sr-only">Pertanyaan PPDB &amp; Biaya Pendidikan</span>
                      </h4>
                      <p className="text-gray-500 text-[11px] font-mono mt-0.5">
                        &rarr; WhatsApp <span>{waDisplay}</span>
                      </p>
                    </div>
                  </div>

                  {/* Item 2: Tata Usaha */}
                  <div className="flex items-start gap-2.5">
                    <CheckCircle2 className="w-4 h-4 text-[#1E7A46] shrink-0 mt-0.5" aria-hidden="true" />
                    <div>
                      <h4 className="font-semibold text-gray-800">
                        <span>Administrasi &amp; Tata Usaha</span>
                      </h4>
                      <p className="text-gray-500 text-[11px] font-mono mt-0.5">
                        &rarr; Telepon <span>{phoneDisplay}</span>
                      </p>
                    </div>
                  </div>

                  {/* Item 3: Kemitraan */}
                  <div className="flex items-start gap-2.5">
                    <CheckCircle2 className="w-4 h-4 text-[#1E7A46] shrink-0 mt-0.5" aria-hidden="true" />
                    <div>
                      <h4 className="font-semibold text-gray-800">
                        <span>Kerja Sama / Kemitraan</span>
                      </h4>
                      <p className="text-gray-500 text-[11px] font-mono mt-0.5">
                        &rarr; Email <span>{emailDisplay}</span>
                      </p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          {/* Media Sosial Resmi (Dinamis dari CMS, hanya tampil jika dikonfigurasi) */}
          {socialLinks.length > 0 && (
            <div className="mt-8 bg-white border border-gray-200/90 rounded-2xl p-6 shadow-xs">
              <h3 className="font-pub-heading font-bold text-base text-[#062940] mb-3">
                Kanal Media Sosial Resmi
              </h3>
              <div className="flex flex-wrap gap-3">
                {socialLinks.map((item) => (
                  <a
                    key={item.platform}
                    href={item.url}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-slate-50 border border-slate-200 text-xs font-semibold text-[#062940] hover:bg-[#062940] hover:text-white transition-colors"
                  >
                    <span>{item.platform}:</span>
                    <span className="font-normal text-gray-500 hover:text-white">{item.handle}</span>
                  </a>
                ))}
              </div>
            </div>
          )}
        </div>
      </section>

      {/* =========================================================================
          4. PETA & LOKASI KAMPUS (KIRI TEKS + KANAN FRAME MAPS)
          ========================================================================= */}
      <section className="py-12 sm:py-16 bg-white w-full" aria-labelledby="map-heading">
        <div className="w-full px-4 sm:px-8 lg:px-12 xl:px-16">
          <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
            {/* Kolom Kiri: Keterangan Lokasi & Tombol Petunjuk (Span 4) */}
            <div className="lg:col-span-4 space-y-4">
              <div>
                <span className="text-[11px] font-bold uppercase tracking-wider text-gray-400 block mb-1">
                  Lokasi Kampus
                </span>
                <h2 id="map-heading" className="font-pub-heading text-2xl sm:text-3xl font-bold text-[#062940]">
                  Peta &amp; Lokasi
                </h2>
              </div>

              <p className="text-gray-600 text-xs sm:text-sm leading-relaxed">
                SMP Laboratorium UM berlokasi di lingkungan Kampus Terpadu Universitas Negeri Malang (P2LP UM) yang mudah diakses dari berbagai arah di Kota Malang.
              </p>

              <div>
                <a
                  href={mapsQueryUrl}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="inline-flex items-center gap-2 bg-[#062940] hover:bg-[#0A3D62] text-white font-semibold text-xs sm:text-sm px-5 py-3 rounded-xl transition-all shadow-xs focus:outline-none focus:ring-2 focus:ring-[#C89332]"
                >
                  <MapPin className="w-4 h-4" aria-hidden="true" />
                  <span>Petunjuk Arah Google Maps</span>
                  <ArrowRight className="w-3.5 h-3.5" aria-hidden="true" />
                </a>
              </div>
            </div>

            {/* Kolom Kanan: Frame Peta Google Maps (Span 8) */}
            <div className="lg:col-span-8">
              <div className="w-full h-80 sm:h-96 rounded-2xl border border-gray-200 overflow-hidden shadow-xs relative bg-slate-100">
                <iframe
                  src={mapsEmbedUrl}
                  width="100%"
                  height="100%"
                  style={{ border: 0 }}
                  allowFullScreen={false}
                  loading="lazy"
                  referrerPolicy="no-referrer-when-downgrade"
                  title="Peta Lokasi Kampus SMP Laboratorium UM"
                  className="w-full h-full"
                />
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* =========================================================================
          5. CALL TO ACTION PRE-FOOTER (PERSIS REFERENSI UI)
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
                Siap Bergabung dengan
                <br />
                SMP Laboratorium UM?
              </h2>
              <p className="text-white/80 text-xs sm:text-sm mt-1.5 max-w-xl leading-relaxed">
                Dapatkan informasi lebih lanjut tentang program pendidikan, pendaftaran, dan kegiatan sekolah kami.
              </p>
            </div>

            <div className="flex flex-col sm:flex-row items-center gap-3 shrink-0 w-full sm:w-auto">
              <Link
                href="/ppdb"
                className="w-full sm:w-auto text-center bg-[#C89332] hover:bg-[#B57C23] text-white font-bold text-xs sm:text-sm px-6 py-3 rounded-lg shadow-sm hover:shadow transition-all inline-flex items-center justify-center gap-2 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-[#062940]"
              >
                <span className="sr-only">Informasi </span>
                <span>PPDB Online</span>
                <ArrowRight className="w-4 h-4" aria-hidden="true" />
              </Link>
              <Link
                href="/profil"
                className="w-full sm:w-auto text-center inline-flex items-center justify-center gap-2 bg-[#062940] hover:bg-white/10 border border-white/40 hover:border-white text-white font-semibold text-xs sm:text-sm px-5 py-3 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-[#C89332]"
              >
                <span>Lihat Profil Sekolah</span>
                <ArrowRight className="w-4 h-4" aria-hidden="true" />
              </Link>
            </div>
          </div>
        </div>
      </section>
    </main>
  );
}
