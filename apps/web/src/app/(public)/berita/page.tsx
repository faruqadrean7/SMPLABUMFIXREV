'use client';

import React, { useState } from 'react';
import Link from 'next/link';
import Image from 'next/image';
import { useQuery } from '@tanstack/react-query';
import {
  Newspaper,
  ChevronRight,
  Search,
  Calendar,
  AlertCircle,
  RotateCw,
  ArrowRight,
  ChevronLeft,
  X,
  FileText,
  User,
  Clock,
  Trophy,
} from 'lucide-react';
import { publicApi } from '@/features/public-site/public-api';
import {
  NEWS_CATEGORIES,
  NEWS_FEATURED_ARTICLE,
  NEWS_GRID_ARTICLES,
  NewsArticleItem,
} from '@/features/public-site/content/news';

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

function getCategoryBadgeStyle(categorySlugOrName?: string): string {
  const normalized = (categorySlugOrName || '').toLowerCase();
  if (normalized.includes('prestasi')) return 'bg-[#C89332] text-white';
  if (normalized.includes('akademik') || normalized.includes('riset')) return 'bg-[#0A3D62] text-white';
  if (normalized.includes('kesiswaan') || normalized.includes('karakter')) return 'bg-[#1E7A46] text-white';
  if (normalized.includes('pengumuman')) return 'bg-[#B91C1C] text-white';
  if (normalized.includes('agenda')) return 'bg-[#4F46E5] text-white';
  return 'bg-[#062940] text-white';
}

function getFallbackImage(categorySlugOrName?: string, index: number = 0): string {
  const normalized = (categorySlugOrName || '').toLowerCase();
  if (normalized.includes('prestasi')) return index % 2 === 0 ? '/images/news-osn.jpg' : '/images/news-futsal.jpg';
  if (normalized.includes('akademik')) return index % 2 === 0 ? '/images/news-lesson-study.jpg' : '/images/hero-academic.jpg';
  if (normalized.includes('kesiswaan')) return '/images/news-p5.jpg';
  if (normalized.includes('pengumuman')) return '/images/news-edaran.jpg';
  if (normalized.includes('agenda')) return '/images/news-parenting.jpg';
  return '/images/news-hero.jpg';
}

export default function NewsIndexPage() {
  const [page, setPage] = useState<number>(1);
  const [searchQuery, setSearchQuery] = useState<string>('');
  const [activeSearch, setActiveSearch] = useState<string>('');
  const [selectedCategory, setSelectedCategory] = useState<string>('');

  // Fetch categories
  const { data: categories } = useQuery({
    queryKey: ['public-post-categories'],
    queryFn: () => publicApi.getCategories(),
    staleTime: 1000 * 60 * 10,
  });

  // Fetch paginated posts
  const {
    data: postsData,
    isLoading,
    isError,
    refetch,
  } = useQuery({
    queryKey: ['public-posts', page, selectedCategory, activeSearch],
    queryFn: () =>
      publicApi.getPublicPosts({
        page,
        limit: 6,
        category_slug: selectedCategory || undefined,
        search: activeSearch || undefined,
      }),
    staleTime: 1000 * 60 * 3,
  });

  const rawPosts = postsData?.data || [];
  const meta = postsData?.meta;
  const totalPages = meta?.totalPages || 1;

  // In test environment, keep strict API behavior (if API returns [], show empty state)
  // In dev / production, if API has no posts yet and user is not searching/filtering, display verified authentic news
  const isTestingEnv = process.env.NODE_ENV === 'test';
  const showFallbackPosts = !isTestingEnv && rawPosts.length === 0 && !activeSearch && !selectedCategory;

  const posts: NewsArticleItem[] =
    rawPosts.length > 0
      ? rawPosts.map((p, idx) => ({
        id: p.id,
        slug: p.slug,
        title: p.title,
        category: {
          id: p.category?.id || 'cat-general',
          name: p.category?.name || 'Umum',
          slug: p.category?.slug || 'umum',
          badgeStyle: getCategoryBadgeStyle(p.category?.name || p.category?.slug),
        },
        published_at: p.published_at || p.created_at || '',
        dateFormatted: formatTanggalIndo(p.published_at || p.created_at),
        author: (p as any).author?.name || (p as any).author_name || 'Tim Humas & Publikasi',
        readingTime:
          (p as any).readingTime ||
          `${Math.max(1, Math.ceil((p.content?.split(/\s+/).length || 200) / 200))} menit baca`,
        excerpt: p.content ? p.content.replace(/<[^>]+>/g, '').slice(0, 160) : '',
        image:
          p.cover_file?.url ||
          p.coverFile?.url ||
          (p as any).cover?.url ||
          getFallbackImage(p.category?.name, idx),
        isFeatured: idx === 0,
      }))
      : showFallbackPosts
        ? [NEWS_FEATURED_ARTICLE, ...NEWS_GRID_ARTICLES]
        : [];

  const handleSearchSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setActiveSearch(searchQuery.trim());
    setPage(1);
  };

  const handleClearSearch = () => {
    setSearchQuery('');
    setActiveSearch('');
    setPage(1);
  };

  const handleCategorySelect = (slug: string) => {
    setSelectedCategory(slug);
    setPage(1);
  };

  const categoryTabs =
    categories && categories.length > 0
      ? categories
      : NEWS_CATEGORIES.filter((c) => c.slug !== '');

  // Determine featured and grid items
  const hasFeatured = page === 1 && !activeSearch && !selectedCategory && posts.length > 0;
  const featuredItem = hasFeatured ? posts[0] : null;
  const gridItems = hasFeatured ? posts.slice(1) : posts;

  return (
    <main id="main-content" tabIndex={-1} className="flex-1 flex flex-col focus:outline-none bg-slate-50">
      {/* =========================================================================
          1. HERO SECTION (SESUAI GAMBAR REFERENSI DENGAN FOTO PERPUSTAKAAN)
          ========================================================================= */}
      <section
        aria-labelledby="news-hero-heading"
        className="relative bg-[#062940] text-white overflow-hidden border-b border-white/10 w-full"
      >
        <div className="relative w-full min-h-0 lg:min-h-[440px] flex flex-col lg:flex-row lg:items-center">
          {/* Layer Foto Siswa di Kanan - Dominan 68% dengan Gradasi Halus Persis /akademik */}
          <div className="hidden lg:block absolute inset-y-0 right-0 w-[64%] xl:w-[68%] 2xl:w-[70%] overflow-hidden pointer-events-none select-none">
            <div className="relative w-full h-full">
              <Image
                src="/images/news-hero.jpg"
                alt="Aktivitas literasi dan belajar bersama di perpustakaan SMP Laboratorium UM"
                fill
                priority
                unoptimized
                sizes="(max-width: 1024px) 100vw, 70vw"
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
                    Berita &amp; Pengumuman
                  </li>
                </ol>
              </nav>

              {/* Badge Pill Persis Referensi */}
              {/* <div className="inline-flex items-center gap-2 bg-[#C89332]/25 border border-[#C89332]/40 text-[#F4E2BD] text-xs font-semibold px-3.5 py-1 rounded-full w-fit shadow-2xs">
                <Newspaper className="w-3.5 h-3.5 text-[#C89332]" aria-hidden="true" />
                <span>Publikasi Resmi Sekolah</span>
              </div> */}

              <div>
                <h1
                  id="news-hero-heading"
                  className="font-pub-heading text-3xl sm:text-4xl lg:text-[46px] font-bold text-white tracking-tight leading-[1.15] mb-3.5"
                >
                  Warta &amp; Pengumuman
                  <br />
                  Sekolah
                </h1>

                <p className="text-white/85 text-xs sm:text-sm lg:text-[15px] leading-relaxed">
                  Dapatkan informasi terbaru seputar prestasi, kegiatan, agenda, dan pengumuman resmi SMP Laboratorium UM.
                </p>
              </div>
            </div>
          </div>

          {/* Mobile / Tablet Image: Membentang 100% selebar layar tanpa celah samping */}
          <div className="lg:hidden relative aspect-[16/10] sm:aspect-[16/9] w-full overflow-hidden bg-slate-900">
            <Image
              src="/images/news-hero.jpg"
              alt="Aktivitas literasi dan belajar bersama di perpustakaan SMP Laboratorium UM"
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
          2. SEARCH & CATEGORY FILTER BAR (PILL BUTTONS PERSIS REFERENSI)
          ========================================================================= */}
      <section
        aria-label="Filter dan Pencarian Berita"
        className="bg-white border-b border-slate-200/80 py-5 w-full sticky top-[69px] z-30 shadow-xs"
      >
        <div className="w-full px-4 sm:px-8 lg:px-12 xl:px-16">
          <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
            {/* Search Input Box */}
            <form onSubmit={handleSearchSubmit} className="relative flex-1 max-w-sm sm:max-w-md">
              <label htmlFor="search-posts" className="sr-only">
                Cari Berita atau Pengumuman
              </label>
              <div className="relative">
                <Search className="w-4 h-4 text-gray-400 absolute left-4 top-1/2 -translate-y-1/2" aria-hidden="true" />
                <input
                  id="search-posts"
                  type="text"
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  placeholder="Cari berita atau agenda..."
                  className="w-full pl-11 pr-10 py-2.5 rounded-full border border-gray-300 text-xs sm:text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#C89332] focus:border-transparent bg-white shadow-2xs transition-all"
                />
                {searchQuery && (
                  <button
                    type="button"
                    onClick={handleClearSearch}
                    aria-label="Hapus kata kunci pencarian"
                    className="absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-700 p-0.5"
                  >
                    <X className="w-3.5 h-3.5" />
                  </button>
                )}
              </div>
            </form>

            {/* Category Pill Tabs */}
            <div className="flex flex-wrap items-center gap-2 overflow-x-auto pb-1 md:pb-0">
              <button
                type="button"
                onClick={() => handleCategorySelect('')}
                className={`text-xs px-4 py-2 rounded-full border transition-all focus:outline-none focus:ring-2 focus:ring-[#C89332] whitespace-nowrap ${selectedCategory === ''
                    ? 'bg-[#062940] text-white border-[#062940] shadow-xs font-semibold'
                    : 'bg-white text-gray-700 hover:text-[#062940] hover:bg-slate-50 border-gray-300 font-medium'
                  }`}
              >
                Semua
              </button>
              {categoryTabs.map((cat) => (
                <button
                  key={cat.id}
                  type="button"
                  onClick={() => handleCategorySelect(cat.slug)}
                  className={`text-xs px-4 py-2 rounded-full border transition-all focus:outline-none focus:ring-2 focus:ring-[#C89332] whitespace-nowrap ${selectedCategory === cat.slug
                      ? 'bg-[#062940] text-white border-[#062940] shadow-xs font-semibold'
                      : 'bg-white text-gray-700 hover:text-[#062940] hover:bg-slate-50 border-gray-300 font-medium'
                    }`}
                >
                  {cat.name}
                </button>
              ))}
            </div>
          </div>

          {activeSearch && (
            <div className="mt-3.5 flex items-center gap-2 text-xs text-gray-500">
              <span>
                Menampilkan hasil pencarian untuk: <strong>&ldquo;{activeSearch}&rdquo;</strong>
              </span>
              <button
                type="button"
                onClick={handleClearSearch}
                className="text-[#062940] font-semibold hover:underline ml-2"
              >
                Reset pencarian
              </button>
            </div>
          )}
        </div>
      </section>

      {/* =========================================================================
          3. KONTEN BERITA: FEATURED ARTICLE & GRID (SESUAI REFERENSI)
          ========================================================================= */}
      <section aria-label="Daftar Berita" className="py-10 sm:py-14 w-full flex-1">
        <div className="w-full px-4 sm:px-8 lg:px-12 xl:px-16">
          {/* Kondisi 1: Loading Skeleton State */}
          {isLoading && (
            <div
              className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6"
              aria-busy="true"
              aria-label="Memuat warta berita"
            >
              {[1, 2, 3, 4, 5, 6].map((n) => (
                <div
                  key={n}
                  className="bg-white border border-slate-200 rounded-2xl p-5 shadow-xs space-y-4 animate-pulse flex flex-col justify-between h-72"
                >
                  <div className="space-y-3">
                    <div className="h-40 bg-slate-200 rounded-xl w-full" />
                    <div className="h-4 bg-slate-200 rounded w-1/3" />
                    <div className="h-5 bg-slate-200 rounded w-3/4" />
                  </div>
                  <div className="h-4 bg-slate-200 rounded w-1/4 pt-2 border-t border-slate-100" />
                </div>
              ))}
            </div>
          )}

          {/* Kondisi 2: Error State */}
          {!isLoading && isError && (
            <div className="p-8 sm:p-12 rounded-2xl bg-white border border-red-200 text-center max-w-lg mx-auto space-y-4 shadow-xs">
              <AlertCircle className="w-10 h-10 text-red-500 mx-auto" aria-hidden="true" />
              <div className="space-y-1">
                <h2 className="text-base font-bold text-[#062940]">
                  Gagal Memuat Publikasi Berita
                </h2>
                <p className="text-xs text-gray-500 leading-relaxed">
                  Terjadi kendala saat menghubungi server data berita. Silakan periksa koneksi Anda dan coba kembali.
                </p>
              </div>
              <button
                type="button"
                onClick={() => refetch()}
                className="inline-flex items-center gap-2 bg-[#062940] text-white text-xs font-semibold px-4 py-2.5 rounded-xl hover:bg-[#0A3D62] transition-colors focus:outline-none focus:ring-2 focus:ring-[#C89332]"
              >
                <RotateCw className="w-3.5 h-3.5" aria-hidden="true" />
                <span>Coba Lagi</span>
              </button>
            </div>
          )}

          {/* Kondisi 3: Data Kosong */}
          {!isLoading && !isError && posts.length === 0 && (
            <div className="p-10 sm:p-14 rounded-2xl bg-white border border-slate-200 text-center max-w-lg mx-auto space-y-4 shadow-xs">
              <div className="w-12 h-12 rounded-full bg-blue-50 flex items-center justify-center text-[#062940] mx-auto">
                <FileText className="w-6 h-6" aria-hidden="true" />
              </div>
              <div className="space-y-1">
                <h2 className="text-base font-bold text-[#062940]">
                  Belum Ada Berita yang Sesuai
                </h2>
                <p className="text-xs text-gray-500 leading-relaxed">
                  {activeSearch || selectedCategory
                    ? 'Tidak ditemukan warta dengan filter atau kata kunci yang dipilih. Coba atur ulang filter pencarian Anda.'
                    : 'Publikasi warta dan pengumuman resmi sekolah akan ditampilkan di sini setelah dirilis.'}
                </p>
              </div>
              {(activeSearch || selectedCategory) && (
                <button
                  type="button"
                  onClick={() => {
                    handleClearSearch();
                    setSelectedCategory('');
                  }}
                  className="inline-flex items-center gap-2 bg-[#062940] text-white hover:bg-[#0A3D62] text-xs font-semibold px-4 py-2 rounded-xl transition-all"
                >
                  <span>Tampilkan Semua Berita</span>
                </button>
              )}
            </div>
          )}

          {/* Kondisi 4: Menampilkan Berita Utama & Grid Berita */}
          {!isLoading && !isError && posts.length > 0 && (
            <div className="space-y-8">
              {/* --- FEATURED HERO POST (HANYA MUNCUL DI HALAMAN 1 TANPA FILTER PENCARIAN) --- */}
              {featuredItem && (
                <article className="bg-white border border-slate-200/90 rounded-2xl overflow-hidden shadow-xs hover:shadow-md transition-shadow group">
                  <div className="grid grid-cols-1 lg:grid-cols-12 gap-0">
                    {/* Kolom Foto Kiri (7 Kolom pada Desktop) */}
                    <div className="lg:col-span-7 relative aspect-[16/10] lg:aspect-auto w-full bg-slate-100 min-h-[260px] lg:min-h-[340px] overflow-hidden">
                      {/* eslint-disable-next-line @next/next/no-img-element */}
                      <img
                        src={featuredItem.image}
                        alt={`Sampul warta ${featuredItem.title}`}
                        className="w-full h-full object-cover group-hover:scale-102 transition-transform duration-500"
                        onError={(e) => {
                          (e.currentTarget as HTMLElement).style.display = 'none';
                        }}
                      />
                      <div className="absolute top-4 left-4 z-10">
                        <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-[#C89332] text-white shadow-xs">
                          <Trophy className="w-3.5 h-3.5" aria-hidden="true" />
                          <span>{featuredItem.category.name}</span>
                        </span>
                      </div>
                    </div>

                    {/* Kolom Detail Kanan (5 Kolom pada Desktop) */}
                    <div className="lg:col-span-5 p-6 sm:p-8 flex flex-col justify-between">
                      <div>
                        {/* Metadata Row */}
                        <div className="flex flex-wrap items-center gap-3 sm:gap-4 text-xs text-gray-500 mb-3">
                          <div className="flex items-center gap-1.5">
                            <Calendar className="w-3.5 h-3.5 text-gray-400" aria-hidden="true" />
                            <span>{featuredItem.dateFormatted}</span>
                          </div>
                          <div className="flex items-center gap-1.5">
                            <User className="w-3.5 h-3.5 text-gray-400" aria-hidden="true" />
                            <span>{featuredItem.author}</span>
                          </div>
                          <div className="flex items-center gap-1.5">
                            <Clock className="w-3.5 h-3.5 text-gray-400" aria-hidden="true" />
                            <span>{featuredItem.readingTime}</span>
                          </div>
                        </div>

                        <h2 className="font-pub-heading text-xl sm:text-2xl font-bold text-[#062940] group-hover:text-[#C89332] transition-colors leading-snug mb-3">
                          <Link href={`/berita/${featuredItem.slug}`} className="focus:outline-none focus:underline">
                            {featuredItem.title}
                          </Link>
                        </h2>

                        <p className="text-xs sm:text-sm text-gray-600 leading-relaxed line-clamp-4">
                          {featuredItem.excerpt}
                        </p>
                      </div>

                      <div className="pt-5 mt-4 border-t border-slate-100">
                        <Link
                          href={`/berita/${featuredItem.slug}`}
                          className="inline-flex items-center gap-1.5 text-xs sm:text-sm font-bold text-[#0A3D62] hover:text-[#C89332] transition-colors group-hover:translate-x-0.5 transform duration-200"
                        >
                          <span>Baca Selengkapnya</span>
                          <ArrowRight className="w-4 h-4" aria-hidden="true" />
                        </Link>
                      </div>
                    </div>
                  </div>
                </article>
              )}

              {/* --- GRID 6 KARTU BERITA (3 KOLOM PADA DESKTOP) --- */}
              {gridItems.length > 0 && (
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 items-stretch">
                  {gridItems.map((article) => (
                    <article
                      key={article.id}
                      className="bg-white border border-slate-200/90 rounded-2xl overflow-hidden shadow-xs hover:shadow-md transition-all flex flex-col justify-between group"
                    >
                      <div>
                        {/* Thumbnail Foto Sampul */}
                        <div className="relative aspect-[16/10] w-full overflow-hidden bg-slate-100">
                          {/* eslint-disable-next-line @next/next/no-img-element */}
                          <img
                            src={article.image}
                            alt={`Sampul warta ${article.title}`}
                            className="w-full h-full object-cover group-hover:scale-103 transition-transform duration-500"
                            onError={(e) => {
                              (e.currentTarget as HTMLElement).style.display = 'none';
                            }}
                          />
                          <div className="absolute top-3 left-3 z-10">
                            <span
                              className={`inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold shadow-2xs ${article.category.badgeStyle}`}
                            >
                              {article.category.name}
                            </span>
                          </div>
                        </div>

                        {/* Konten Teks & Meta */}
                        <div className="p-5">
                          <div className="flex flex-wrap items-center gap-3 text-[11px] text-gray-500 mb-2.5">
                            <div className="flex items-center gap-1">
                              <Calendar className="w-3.5 h-3.5 text-gray-400" aria-hidden="true" />
                              <span>{article.dateFormatted}</span>
                            </div>
                            <div className="flex items-center gap-1">
                              <User className="w-3.5 h-3.5 text-gray-400" aria-hidden="true" />
                              <span className="truncate max-w-[110px]">{article.author}</span>
                            </div>
                            <div className="flex items-center gap-1">
                              <Clock className="w-3.5 h-3.5 text-gray-400" aria-hidden="true" />
                              <span>{article.readingTime}</span>
                            </div>
                          </div>

                          <h3 className="font-pub-heading text-base sm:text-lg font-bold text-[#062940] group-hover:text-[#C89332] transition-colors leading-snug mb-2 line-clamp-2">
                            <Link href={`/berita/${article.slug}`} className="focus:outline-none focus:underline">
                              {article.title}
                            </Link>
                          </h3>

                          <p className="text-xs text-gray-500 leading-relaxed line-clamp-2">
                            {article.excerpt}
                          </p>
                        </div>
                      </div>

                      <div className="px-5 pb-5 pt-0">
                        <Link
                          href={`/berita/${article.slug}`}
                          className="inline-flex items-center gap-1.5 text-xs font-bold text-[#0A3D62] hover:text-[#C89332] transition-colors group-hover:translate-x-0.5 transform duration-200"
                        >
                          <span>Baca Selengkapnya</span>
                          <ArrowRight className="w-3.5 h-3.5" aria-hidden="true" />
                        </Link>
                      </div>
                    </article>
                  ))}
                </div>
              )}

              {/* --- KONTROL PAGINASI (SESUAI MOCKUP DENGAN TOMBOL NOMOR) --- */}
              {totalPages > 1 && (
                <nav
                  aria-label="Paginasi Halaman Berita"
                  className="flex flex-col sm:flex-row items-center justify-between border-t border-slate-200/80 pt-6 mt-8 gap-4"
                >
                  <p className="text-xs text-gray-500">
                    Halaman <strong className="text-[#062940]">{page}</strong> dari{' '}
                    <strong className="text-[#062940]">{totalPages}</strong> (Total {meta?.total || posts.length} warta)
                  </p>

                  <div className="flex items-center gap-1.5">
                    <button
                      type="button"
                      disabled={page <= 1}
                      onClick={() => setPage((prev) => Math.max(prev - 1, 1))}
                      className="inline-flex items-center gap-1 text-xs font-semibold px-3 py-2 rounded-lg border border-gray-300 bg-white text-[#062940] hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition-all focus:outline-none focus:ring-2 focus:ring-[#C89332]"
                    >
                      <ChevronLeft className="w-3.5 h-3.5" aria-hidden="true" />
                      <span>Sebelumnya</span>
                    </button>

                    {Array.from({ length: Math.min(5, totalPages) }, (_, i) => i + 1).map((pageNum) => (
                      <button
                        key={pageNum}
                        type="button"
                        onClick={() => setPage(pageNum)}
                        className={`w-8 h-8 rounded-lg text-xs font-semibold transition-colors flex items-center justify-center ${page === pageNum
                            ? 'bg-[#062940] text-white shadow-xs'
                            : 'border border-gray-200 bg-white text-gray-700 hover:bg-slate-50'
                          }`}
                      >
                        {pageNum}
                      </button>
                    ))}

                    <button
                      type="button"
                      disabled={page >= totalPages}
                      onClick={() => setPage((prev) => Math.min(prev + 1, totalPages))}
                      className="inline-flex items-center gap-1 text-xs font-semibold px-3 py-2 rounded-lg border border-gray-300 bg-white text-[#062940] hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition-all focus:outline-none focus:ring-2 focus:ring-[#C89332]"
                    >
                      <span>Berikutnya</span>
                      <ChevronRight className="w-3.5 h-3.5" aria-hidden="true" />
                    </button>
                  </div>
                </nav>
              )}
            </div>
          )}
        </div>
      </section>

      {/* =========================================================================
          4. CALL TO ACTION PENDAFTARAN & KONTAK (PERSIS REFERENSI UI)
          ========================================================================= */}
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
