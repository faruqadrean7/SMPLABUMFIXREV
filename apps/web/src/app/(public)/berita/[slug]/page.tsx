'use client';

import React from 'react';
import Link from 'next/link';
import Image from 'next/image';
import { useParams } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';
import {
  ChevronRight,
  Calendar,
  Tag,
  ArrowLeft,
  ArrowRight,
  AlertCircle,
  RotateCw,
  Share2,
  Newspaper,
} from 'lucide-react';
import { publicApi } from '@/features/public-site/public-api';

function formatTanggalIndo(dateStr?: string): string {
  if (!dateStr) return '';
  try {
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return '';
    return new Intl.DateTimeFormat('id-ID', {
      day: 'numeric',
      month: 'long',
      year: 'numeric',
    }).format(d);
  } catch {
    return '';
  }
}

export default function NewsDetailPage() {
  const params = useParams();
  const slug = typeof params?.slug === 'string' ? params.slug : '';

  const {
    data: post,
    isLoading,
    isError,
    refetch,
  } = useQuery({
    queryKey: ['public-post-detail', slug],
    queryFn: () => publicApi.getPublicPostBySlug(slug),
    enabled: Boolean(slug),
    staleTime: 1000 * 60 * 5,
  });

  return (
    <main id="main-content" tabIndex={-1} className="flex-1 flex flex-col focus:outline-none">
      {/* 1. BREADCRUMB & HEADER */}
      <section className="bg-gradient-to-r from-pub-navy-deep via-pub-navy to-pub-navy-light text-white py-10 md:py-14 border-b border-pub-navy/20">
        <div className="max-w-container mx-auto px-4 sm:px-6">
          <nav aria-label="Navigasi Halaman" className="mb-4">
            <ol className="flex flex-wrap items-center gap-2 text-xs text-white/70">
              <li>
                <Link
                  href="/"
                  className="hover:text-white transition-colors focus:outline-none focus:ring-2 focus:ring-pub-gold rounded px-1"
                >
                  Beranda
                </Link>
              </li>
              <li aria-hidden="true">
                <ChevronRight className="w-3.5 h-3.5" />
              </li>
              <li>
                <Link
                  href="/berita"
                  className="hover:text-white transition-colors focus:outline-none focus:ring-2 focus:ring-pub-gold rounded px-1"
                >
                  Berita &amp; Pengumuman
                </Link>
              </li>
              <li aria-hidden="true">
                <ChevronRight className="w-3.5 h-3.5" />
              </li>
              <li aria-current="page" className="font-semibold text-pub-gold-soft px-1 truncate max-w-xs sm:max-w-md">
                {post?.title || 'Detail Warta'}
              </li>
            </ol>
          </nav>

          <div className="pt-2">
            <Link
              href="/berita"
              className="inline-flex items-center gap-1.5 text-xs font-semibold text-white/80 hover:text-white transition-colors focus:outline-none focus:underline"
            >
              <ArrowLeft className="w-3.5 h-3.5" />
              <span>Kembali ke Daftar Berita</span>
            </Link>
          </div>
        </div>
      </section>

      {/* 2. ARTICLE CONTENT SECTION */}
      <section aria-label="Konten Artikel" className="py-12 sm:py-16 bg-pub-bg-app flex-1">
        <div className="max-w-container mx-auto px-4 sm:px-6">
          {/* Kondisi 1: Loading Skeleton */}
          {isLoading && (
            <div className="bg-white border border-pub-border rounded-2xl p-6 sm:p-10 shadow-xs max-w-3xl mx-auto space-y-6 animate-pulse" aria-busy="true" aria-label="Memuat isi warta">
              <div className="h-4 bg-slate-200 rounded w-1/4" />
              <div className="h-8 bg-slate-200 rounded w-3/4" />
              <div className="space-y-3 pt-4">
                <div className="h-4 bg-slate-200 rounded w-full" />
                <div className="h-4 bg-slate-200 rounded w-full" />
                <div className="h-4 bg-slate-200 rounded w-5/6" />
              </div>
            </div>
          )}

          {/* Kondisi 2: Error 404 / Gagal Memuat */}
          {!isLoading && (isError || !post) && (
            <div className="p-8 sm:p-12 rounded-2xl bg-white border border-red-200 text-center max-w-lg mx-auto space-y-4 shadow-xs">
              <AlertCircle className="w-10 h-10 text-red-500 mx-auto" aria-hidden="true" />
              <div className="space-y-1">
                <h1 className="text-lg font-bold text-pub-navy-deep">
                  Berita Tidak Ditemukan
                </h1>
                <p className="text-xs text-pub-text-muted leading-relaxed">
                  Publikasi warta ini tidak ditemukan atau belum diterbitkan secara resmi untuk publik.
                </p>
              </div>
              <div className="flex items-center justify-center gap-3 pt-2">
                <button
                  type="button"
                  onClick={() => refetch()}
                  className="inline-flex items-center gap-2 bg-pub-navy-subtle text-pub-navy text-xs font-semibold px-4 py-2 rounded-xl hover:bg-pub-navy hover:text-white transition-all"
                >
                  <RotateCw className="w-3.5 h-3.5" aria-hidden="true" />
                  <span>Coba Lagi</span>
                </button>
                <Link
                  href="/berita"
                  className="inline-flex items-center gap-2 bg-pub-navy text-white text-xs font-semibold px-4 py-2 rounded-xl hover:bg-pub-navy-deep transition-all"
                >
                  <span>Daftar Berita Lainnya</span>
                </Link>
              </div>
            </div>
          )}

          {/* Kondisi 3: Berita Ditemukan */}
          {!isLoading && !isError && post && (
            <article className="bg-white border border-pub-border rounded-2xl p-6 sm:p-10 shadow-xs max-w-3xl mx-auto space-y-6">
              {/* Meta Header */}
              <div className="space-y-3 border-b border-pub-border pb-6">
                <div className="flex flex-wrap items-center gap-3 text-xs">
                  {post.category ? (
                    <span className="font-semibold px-3 py-1 rounded-full bg-pub-navy-subtle text-pub-navy text-xs inline-flex items-center gap-1.5">
                      <Tag className="w-3.5 h-3.5" aria-hidden="true" />
                      {post.category.name}
                    </span>
                  ) : (
                    <span className="font-semibold px-3 py-1 rounded-full bg-slate-100 text-pub-text-muted text-xs">
                      Umum
                    </span>
                  )}

                  <div className="flex items-center gap-1.5 text-xs text-pub-text-muted">
                    <Calendar className="w-3.5 h-3.5 text-pub-text-muted" aria-hidden="true" />
                    <span>{formatTanggalIndo(post.published_at || post.created_at)}</span>
                  </div>
                </div>

                <h1 className="font-heading font-bold text-2xl sm:text-3xl lg:text-4xl text-pub-navy leading-tight">
                  {post.title}
                </h1>
              </div>

              {/* Cover Image Banner */}
              {(() => {
                const coverUrl = post.cover_file?.url || post.coverFile?.url || (post as any).cover?.url;
                if (!coverUrl) return null;
                return (
                  <div className="relative w-full aspect-video rounded-xl overflow-hidden border border-pub-border bg-slate-100">
                    {/* eslint-disable-next-line @next/next/no-img-element */}
                    <img
                      src={coverUrl}
                      alt={`Sampul warta ${post.title}`}
                      className="w-full h-full object-cover"
                      onError={(e) => {
                        (e.currentTarget as HTMLElement).style.display = 'none';
                      }}
                    />
                  </div>
                );
              })()}

              {/* Isi Berita */}
              <div className="text-pub-text-main text-sm sm:text-base leading-relaxed space-y-4 pt-2">
                {post.content ? (
                  post.content.split('\n').map((paragraph, pIdx) => {
                    const trimmed = paragraph.trim();
                    if (!trimmed) return null;
                    return <p key={pIdx}>{trimmed}</p>;
                  })
                ) : (
                  <p className="text-pub-text-muted italic">Konten artikel warta belum tersedia.</p>
                )}
              </div>

              {/* Footer Artikel */}
              <div className="border-t border-pub-border pt-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <Link
                  href="/berita"
                  className="inline-flex items-center gap-1.5 text-xs font-bold text-pub-navy hover:text-pub-navy-light transition-colors"
                >
                  <ArrowLeft className="w-3.5 h-3.5" />
                  <span>Kembali ke Warta Sekolah</span>
                </Link>

                <div className="text-xs text-pub-text-muted flex items-center gap-2">
                  <Newspaper className="w-4 h-4 text-pub-gold" aria-hidden="true" />
                  <span>Publikasi Resmi SMP Laboratorium UM</span>
                </div>
              </div>
            </article>
          )}
        </div>
      </section>

      {/* 3. CALL TO ACTION PENDAFTARAN & KONTAK */}
      <section className="relative overflow-hidden bg-[#062940] text-white py-12 sm:py-14 w-full border-t border-white/10">
        {/* Foto Kampus dengan Pohon Hijau di Sisi Kiri Faded Overlay */}
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
                Jangan lewatkan informasi terbaru
                <br />
                seputar kegiatan dan prestasi kami.
              </h2>
              <p className="text-white/80 text-xs sm:text-sm mt-1.5 max-w-xl leading-relaxed">
                Ikuti terus warta, berita dan pengumuman resmi SMP Laboratorium UM.
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
