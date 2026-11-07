'use client';

import React, { useState } from 'react';
import Link from 'next/link';
import { useQuery } from '@tanstack/react-query';
import {
  BookOpen,
  Search,
  Download,
  Calendar,
  ExternalLink,
  ChevronLeft,
  ChevronRight,
  FileText,
  Loader2,
  Newspaper,
} from 'lucide-react';
import { publicApi } from '@/features/public-site/public-api';
import { PublicBulletinItem } from '@/features/public-site/types';
import { storageApi } from '@/features/storage/storage-api';
import { LoadingSpinner } from '@/components/shared/loading-spinner';
import { EmptyState } from '@/components/shared/empty-state';
import { ErrorState } from '@/components/shared/error-state';

function formatBytes(bytes?: number): string {
  if (!bytes || bytes === 0) return '';
  const k = 1024;
  const sizes = ['Bytes', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return `${parseFloat((bytes / Math.pow(k, i)).toFixed(1))} ${sizes[i]}`;
}

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

export default function PublicBulletinsPage() {
  const [page, setPage] = useState<number>(1);
  const [searchQuery, setSearchQuery] = useState<string>('');
  const [activeSearch, setActiveSearch] = useState<string>('');
  const [downloadingId, setDownloadingId] = useState<string | null>(null);

  const {
    data: bulletinsResponse,
    isLoading,
    isError,
    refetch,
  } = useQuery({
    queryKey: ['public-bulletins', page, activeSearch],
    queryFn: () =>
      publicApi.getPublicBulletins({
        page,
        per_page: 9,
        search: activeSearch || undefined,
      }),
    staleTime: 1000 * 60 * 3,
  });

  const bulletins = bulletinsResponse?.data || [];
  const meta = bulletinsResponse?.meta;
  const totalPages = meta?.totalPages || 1;

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

  const handleDownload = async (item: PublicBulletinItem) => {
    setDownloadingId(item.id);
    try {
      // 1. Jika URL file publik langsung tersedia di payload
      if (item.file?.download_url || item.file?.url) {
        window.open(item.file.download_url || item.file.url, '_blank');
        return;
      }

      // 2. Minta URL berkas via storageApi
      if (item.file_id) {
        try {
          const fileRes = await storageApi.getFileUrl(item.file_id);
          if (fileRes.data?.url) {
            window.open(fileRes.data.url, '_blank');
            return;
          }
        } catch {
          // fallback ke tautan unduh terverifikasi sistem
        }
        window.open(`/api/v1/files/${item.file_id}/download`, '_blank');
      }
    } catch (err) {
      console.error('Gagal mengunduh buletin:', err);
    } finally {
      setDownloadingId(null);
    }
  };

  return (
    <div className="min-h-screen bg-[#F8FAFC]">
      {/* =========================================================================
          HERO HEADER
          ========================================================================= */}
      <section className="bg-gradient-to-b from-[#062940] to-[#0A3D62] text-white py-12 sm:py-16 border-b border-white/10">
        <div className="w-full px-4 sm:px-8 lg:px-12 xl:px-16">
          <nav aria-label="Navigasi Halaman" className="mb-4">
            <ol className="flex items-center gap-2 text-xs text-white/70">
              <li>
                <Link href="/" className="hover:text-white transition-colors">
                  Beranda
                </Link>
              </li>
              <li aria-hidden="true" className="text-white/40">
                /
              </li>
              <li className="text-white font-medium" aria-current="page">
                Buletin &amp; Majalah Sekolah
              </li>
            </ol>
          </nav>

          <div className="max-w-3xl">
            <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/10 text-white/90 text-xs font-medium mb-3 backdrop-blur-sm">
              <Newspaper className="w-3.5 h-3.5 text-[#C89332]" />
              <span>Publikasi &amp; Literasi Sekolah</span>
            </div>
            <h1 className="font-pub-heading text-2xl sm:text-4xl font-extrabold tracking-tight text-white">
              Buletin &amp; Majalah Sekolah
            </h1>
            <p className="mt-3 text-sm sm:text-base text-white/80 leading-relaxed">
              Arsip edisi majalah dinding digital, buletin triwulan, dan karya jurnalistik siswa serta guru SMP Laboratorium UM.
            </p>
          </div>
        </div>
      </section>

      {/* =========================================================================
          MAIN CONTENT AREA
          ========================================================================= */}
      <main className="w-full px-4 sm:px-8 lg:px-12 xl:px-16 py-10 sm:py-14">
        {/* PENCARIAN BULETIN */}
        <div className="mb-8 bg-white p-4 sm:p-5 rounded-sm border border-slate-200 shadow-sm">
          <form onSubmit={handleSearchSubmit} className="flex flex-col sm:flex-row gap-3">
            <div className="relative flex-1">
              <Search className="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
              <input
                type="text"
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                placeholder="Cari buletin berdasarkan judul atau tema edisi..."
                className="w-full pl-10 pr-4 py-2 text-xs sm:text-sm rounded-sm border border-slate-300 focus:outline-none focus:ring-2 focus:ring-[#0A3D62] focus:border-transparent"
              />
            </div>
            <div className="flex gap-2 shrink-0">
              <button
                type="submit"
                className="px-5 py-2 text-xs sm:text-sm font-semibold text-white bg-[#0A3D62] hover:bg-[#062940] rounded-sm transition-colors"
              >
                Cari
              </button>
              {activeSearch && (
                <button
                  type="button"
                  onClick={handleClearSearch}
                  className="px-4 py-2 text-xs sm:text-sm font-semibold text-slate-600 hover:text-slate-900 bg-slate-100 hover:bg-slate-200 rounded-sm transition-colors"
                >
                  Reset
                </button>
              )}
            </div>
          </form>

          {activeSearch && (
            <p className="mt-2 text-xs text-slate-500">
              Menampilkan hasil pencarian untuk: <span className="font-semibold text-slate-700">“{activeSearch}”</span>
            </p>
          )}
        </div>

        {/* STATE HANDLING */}
        {isLoading && (
          <div className="py-20 flex justify-center">
            <LoadingSpinner label="Memuat daftar buletin &amp; majalah sekolah..." />
          </div>
        )}

        {isError && (
          <div className="py-12">
            <ErrorState
              title="Gagal Memuat Buletin"
              message="Terjadi kendala saat menghubungi server untuk mengambil data buletin sekolah. Silakan coba kembali."
              onRetry={() => refetch()}
            />
          </div>
        )}

        {!isLoading && !isError && bulletins.length === 0 && (
          <div className="py-8">
            <EmptyState
              icon={<BookOpen className="h-6 w-6" />}
              title={activeSearch ? 'Buletin Tidak Ditemukan' : 'Belum Ada Buletin Diterbitkan'}
              description={
                activeSearch
                  ? `Tidak ada edisi buletin yang cocok dengan kata kunci "${activeSearch}". Silakan gunakan kata kunci lain.`
                  : 'Saat ini belum ada edisi buletin atau majalah sekolah yang dipublikasikan. Silakan periksa kembali di lain waktu.'
              }
              actionLabel={activeSearch ? 'Lihat Semua Buletin' : undefined}
              onAction={activeSearch ? handleClearSearch : undefined}
            />
          </div>
        )}

        {!isLoading && !isError && bulletins.length > 0 && (
          <div>
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 sm:gap-8">
              {bulletins.map((item) => {
                const coverUrl = item.cover?.url;
                const formattedDate = formatTanggalIndo(item.created_at);
                const fileSize = formatBytes(item.file?.size_bytes);
                const isDownloading = downloadingId === item.id;

                return (
                  <article
                    key={item.id}
                    className="flex flex-col bg-white rounded-sm border border-slate-200 shadow-sm overflow-hidden hover:shadow-md transition-shadow duration-200 group"
                  >
                    {/* COVER / PREVIEW */}
                    <div className="relative aspect-[3/4] bg-slate-100 overflow-hidden border-b border-slate-200 flex items-center justify-center">
                      {coverUrl ? (
                        /* eslint-disable-next-line @next/next/no-img-element */
                        <img
                          src={coverUrl}
                          alt={`Sampul ${item.title}`}
                          className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                          loading="lazy"
                        />
                      ) : (
                        <div className="w-full h-full flex flex-col items-center justify-center p-6 text-center bg-gradient-to-br from-slate-100 to-slate-200 text-slate-500">
                          <BookOpen className="w-16 h-16 text-[#0A3D62]/40 mb-3" />
                          <span className="text-xs font-semibold uppercase tracking-wider text-[#0A3D62]/70">
                            SMP Laboratorium UM
                          </span>
                          <span className="text-xs text-slate-400 mt-1">Edisi Buletin &amp; Majalah</span>
                        </div>
                      )}

                      <div className="absolute top-3 left-3 bg-[#062940]/80 backdrop-blur-sm text-white px-2.5 py-1 rounded-sm text-[11px] font-semibold flex items-center gap-1.5 shadow-sm">
                        <FileText className="w-3.5 h-3.5 text-[#C89332]" />
                        <span>PDF</span>
                      </div>
                    </div>

                    {/* CONTENT */}
                    <div className="p-5 flex-1 flex flex-col justify-between">
                      <div>
                        {formattedDate && (
                          <div className="flex items-center gap-1.5 text-xs text-slate-500 mb-2">
                            <Calendar className="w-3.5 h-3.5 text-slate-400" />
                            <time dateTime={item.created_at}>{formattedDate}</time>
                          </div>
                        )}
                        <h2 className="font-semibold text-slate-900 text-base sm:text-lg line-clamp-2 leading-snug group-hover:text-[#0A3D62] transition-colors">
                          {item.title}
                        </h2>
                        {fileSize && (
                          <p className="mt-1 text-xs text-slate-500">
                            Ukuran berkas: <span className="font-medium text-slate-700">{fileSize}</span>
                          </p>
                        )}
                      </div>

                      {/* ACTION BUTTON */}
                      <div className="mt-6 pt-4 border-t border-slate-100 flex items-center justify-between gap-3">
                        <button
                          type="button"
                          onClick={() => handleDownload(item)}
                          disabled={isDownloading}
                          className="w-full inline-flex items-center justify-center gap-2 px-4 py-2 text-xs sm:text-sm font-semibold text-white bg-[#0A3D62] hover:bg-[#062940] active:scale-[0.98] rounded-sm transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                          {isDownloading ? (
                            <>
                              <Loader2 className="w-4 h-4 animate-spin" />
                              <span>Mengunduh...</span>
                            </>
                          ) : (
                            <>
                              <Download className="w-4 h-4" />
                              <span>Unduh PDF</span>
                            </>
                          )}
                        </button>
                      </div>
                    </div>
                  </article>
                );
              })}
            </div>

            {/* PAGINATION */}
            {totalPages > 1 && (
              <div className="mt-10 flex items-center justify-center gap-2">
                <button
                  type="button"
                  onClick={() => setPage((p) => Math.max(1, p - 1))}
                  disabled={page === 1}
                  className="inline-flex items-center gap-1 px-3 py-1.5 text-xs sm:text-sm rounded-sm border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed"
                >
                  <ChevronLeft className="w-4 h-4" />
                  <span>Sebelumnya</span>
                </button>
                <span className="text-xs text-slate-600 px-3">
                  Halaman {page} dari {totalPages}
                </span>
                <button
                  type="button"
                  onClick={() => setPage((p) => Math.min(totalPages, p + 1))}
                  disabled={page === totalPages}
                  className="inline-flex items-center gap-1 px-3 py-1.5 text-xs sm:text-sm rounded-sm border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed"
                >
                  <span>Selanjutnya</span>
                  <ChevronRight className="w-4 h-4" />
                </button>
              </div>
            )}
          </div>
        )}
      </main>
    </div>
  );
}
