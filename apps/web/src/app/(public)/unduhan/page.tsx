'use client';

import React, { useState } from 'react';
import Link from 'next/link';
import { useQuery } from '@tanstack/react-query';
import {
  Download as DownloadIcon,
  Search,
  FileText,
  FileSpreadsheet,
  FileCode,
  File,
  ChevronLeft,
  ChevronRight,
  ExternalLink,
  Calendar,
  CheckCircle2,
  FolderDown,
  Loader2,
} from 'lucide-react';
import { publicApi } from '@/features/public-site/public-api';
import { PublicDownloadItem } from '@/features/public-site/types';
import { storageApi } from '@/features/storage/storage-api';
import { LoadingSpinner } from '@/components/shared/loading-spinner';
import { EmptyState } from '@/components/shared/empty-state';
import { ErrorState } from '@/components/shared/error-state';

function formatBytes(bytes?: number): string {
  if (!bytes || bytes === 0) return 'Ukuran berkas bervariasi';
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

function getFileIcon(mimeType?: string, title?: string) {
  const normMime = (mimeType || '').toLowerCase();
  const normTitle = (title || '').toLowerCase();

  if (normMime.includes('pdf') || normTitle.endsWith('.pdf')) {
    return <FileText className="w-6 h-6 text-rose-600" />;
  }
  if (
    normMime.includes('sheet') ||
    normMime.includes('excel') ||
    normTitle.endsWith('.xlsx') ||
    normTitle.endsWith('.xls')
  ) {
    return <FileSpreadsheet className="w-6 h-6 text-emerald-600" />;
  }
  if (
    normMime.includes('word') ||
    normMime.includes('document') ||
    normTitle.endsWith('.docx') ||
    normTitle.endsWith('.doc')
  ) {
    return <FileText className="w-6 h-6 text-blue-600" />;
  }
  return <File className="w-6 h-6 text-[#0A3D62]" />;
}

export default function PublicDownloadsPage() {
  const [page, setPage] = useState<number>(1);
  const [selectedCategory, setSelectedCategory] = useState<string>('');
  const [searchQuery, setSearchQuery] = useState<string>('');
  const [activeSearch, setActiveSearch] = useState<string>('');
  const [downloadingId, setDownloadingId] = useState<string | null>(null);

  const {
    data: downloadsResponse,
    isLoading,
    isError,
    refetch,
  } = useQuery({
    queryKey: ['public-downloads', page, selectedCategory, activeSearch],
    queryFn: () =>
      publicApi.getPublicDownloads({
        page,
        per_page: 10,
        category: selectedCategory || undefined,
        search: activeSearch || undefined,
      }),
    staleTime: 1000 * 60 * 3,
  });

  const downloads = downloadsResponse?.data || [];
  const meta = downloadsResponse?.meta;
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

  const handleDownload = async (item: PublicDownloadItem) => {
    setDownloadingId(item.id);
    try {
      // 1. Jika URL file publik langsung tersedia
      if (item.file?.url) {
        window.open(item.file.url, '_blank');
        return;
      }

      // 2. Jika ada file_id, minta URL langsung via storageApi
      if (item.file_id) {
        try {
          const fileRes = await storageApi.getFileUrl(item.file_id);
          if (fileRes.data?.url) {
            window.open(fileRes.data.url, '_blank');
            return;
          }
        } catch {
          // fallback ke tautan unduh sistem
        }
        window.open(`/api/v1/files/${item.file_id}/download`, '_blank');
      }
    } catch (err) {
      console.error('Gagal mengunduh berkas:', err);
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
                &gt;
              </li>
              <li aria-current="page" className="font-semibold text-white">
                Pusat Unduhan
              </li>
            </ol>
          </nav>

          <div className="max-w-3xl">
            <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/10 text-xs font-semibold text-white/90 mb-3 border border-white/15">
              <FolderDown className="w-3.5 h-3.5 text-[#C89332]" />
              <span>Dokumen Resmi &amp; Panduan Akademik</span>
            </div>
            <h1 className="font-pub-heading text-3xl sm:text-4xl lg:text-5xl font-bold text-white tracking-tight leading-tight">
              Pusat Unduhan Berkas
            </h1>
            <p className="text-sm sm:text-base text-white/80 leading-relaxed mt-3">
              Temukan dan unduh dokumen kurikulum, formulir resmi, surat edaran, dan panduan belajar SMP Laboratorium UM.
            </p>
          </div>
        </div>
      </section>

      {/* =========================================================================
          FILTER & PENCARIAN
          ========================================================================= */}
      <div className="w-full px-4 sm:px-8 lg:px-12 xl:px-16 -mt-6">
        <div className="bg-white rounded-2xl shadow-md border border-slate-200/80 p-4 sm:p-5 flex flex-col md:flex-row gap-4 justify-between items-center">
          {/* Filter Kategori */}
          <div className="flex flex-wrap gap-2 w-full md:w-auto">
            {[
              { id: '', label: 'Semua Dokumen' },
              { id: 'formulir', label: 'Formulir' },
              { id: 'kurikulum', label: 'Kurikulum' },
              { id: 'panduan', label: 'Panduan' },
              { id: 'edaran', label: 'Surat Edaran' },
            ].map((cat) => {
              const isSelected = selectedCategory === cat.id;
              return (
                <button
                  key={cat.id}
                  type="button"
                  onClick={() => {
                    setSelectedCategory(cat.id);
                    setPage(1);
                  }}
                  className={`px-3.5 py-1.5 rounded-lg text-xs font-semibold transition-all ${
                    isSelected
                      ? 'bg-[#062940] text-white shadow-xs'
                      : 'bg-slate-100 text-slate-700 hover:bg-slate-200'
                  }`}
                >
                  {cat.label}
                </button>
              );
            })}
          </div>

          {/* Kolom Pencarian */}
          <form onSubmit={handleSearchSubmit} className="flex items-center gap-2 w-full md:w-80">
            <div className="relative flex-1">
              <Search className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
              <input
                type="text"
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                placeholder="Cari dokumen..."
                className="w-full pl-9 pr-3 py-2 text-xs bg-slate-50 border border-slate-200 rounded-lg text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-[#0A3D62] focus:bg-white transition-all"
              />
            </div>
            <button
              type="submit"
              className="px-3.5 py-2 rounded-lg bg-[#0A3D62] hover:bg-[#062940] text-white text-xs font-semibold transition-colors shrink-0"
            >
              Cari
            </button>
          </form>
        </div>
      </div>

      {/* =========================================================================
          DAFTAR UNDUHAN
          ========================================================================= */}
      <main className="w-full px-4 sm:px-8 lg:px-12 xl:px-16 py-10 sm:py-12">
        {isLoading && (
          <div className="py-24">
            <LoadingSpinner label="Memuat dokumen unduhan..." size="lg" />
          </div>
        )}

        {isError && (
          <div className="py-16">
            <ErrorState
              title="Gagal Memuat Unduhan"
              message="Terjadi kendala saat mengambil daftar dokumen unduhan publik. Silakan coba lagi."
              onRetry={() => refetch()}
            />
          </div>
        )}

        {!isLoading && !isError && downloads.length === 0 && (
          <div className="bg-white rounded-2xl border border-slate-200 p-8 sm:p-12 text-center">
            <EmptyState
              title="Belum Ada Dokumen"
              description={
                activeSearch || selectedCategory
                  ? 'Tidak ditemukan berkas dokumen yang sesuai dengan kata kunci atau kategori yang Anda pilih.'
                  : 'Saat ini belum ada berkas unduhan publik yang diunggah oleh pihak sekolah.'
              }
              actionLabel={activeSearch ? 'Reset Pencarian' : undefined}
              onAction={activeSearch ? handleClearSearch : undefined}
            />
          </div>
        )}

        {!isLoading && !isError && downloads.length > 0 && (
          <div className="space-y-4">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              {downloads.map((item: PublicDownloadItem) => {
                const isItemDownloading = downloadingId === item.id;
                const fileIcon = getFileIcon(item.file?.mime_type, item.title);
                const fileSize = formatBytes(item.file?.size_bytes);
                const dateLabel = formatTanggalIndo(item.created_at);

                return (
                  <div
                    key={item.id}
                    className="bg-white rounded-2xl border border-slate-200/90 shadow-2xs hover:shadow-md hover:border-[#C89332]/40 transition-all p-5 flex flex-col justify-between group"
                  >
                    <div className="flex items-start gap-4">
                      <div className="w-12 h-12 rounded-xl bg-slate-100 flex items-center justify-center shrink-0 border border-slate-200 group-hover:bg-[#062940]/5 transition-colors">
                        {fileIcon}
                      </div>

                      <div className="min-w-0 flex-1">
                        <div className="flex items-center gap-2 mb-1">
                          <span className="px-2 py-0.5 rounded-md bg-slate-100 text-[10px] font-bold text-slate-700 uppercase tracking-wide">
                            {item.category || 'Dokumen'}
                          </span>
                          {dateLabel && (
                            <span className="text-[11px] text-slate-400">
                              &bull; {dateLabel}
                            </span>
                          )}
                        </div>

                        <h3 className="font-pub-heading text-base font-bold text-slate-900 group-hover:text-[#0A3D62] transition-colors leading-snug">
                          {item.title}
                        </h3>

                        <p className="text-xs text-slate-500 mt-1">
                          {fileSize}
                        </p>
                      </div>
                    </div>

                    <div className="mt-5 pt-3 border-t border-slate-100 flex items-center justify-between">
                      <span className="text-[11px] text-slate-400">
                        {item.download_count !== undefined ? `${item.download_count} kali diunduh` : 'Dokumen Resmi'}
                      </span>

                      <button
                        type="button"
                        onClick={() => handleDownload(item)}
                        disabled={isItemDownloading}
                        className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-[#0A3D62] hover:bg-[#062940] text-white text-xs font-semibold shadow-xs transition-colors disabled:opacity-50"
                      >
                        {isItemDownloading ? (
                          <>
                            <Loader2 className="w-3.5 h-3.5 animate-spin" />
                            <span>Menyiapkan...</span>
                          </>
                        ) : (
                          <>
                            <DownloadIcon className="w-3.5 h-3.5" />
                            <span>Unduh Berkas</span>
                          </>
                        )}
                      </button>
                    </div>
                  </div>
                );
              })}
            </div>

            {/* Pagination Controls */}
            {totalPages > 1 && (
              <div className="flex items-center justify-center gap-2 pt-8">
                <button
                  type="button"
                  onClick={() => setPage((p) => Math.max(1, p - 1))}
                  disabled={page <= 1}
                  className="px-3 py-1.5 rounded-lg border border-slate-200 bg-white text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed flex items-center gap-1"
                >
                  <ChevronLeft className="w-4 h-4" />
                  <span>Sebelumnya</span>
                </button>
                <span className="text-xs text-slate-600 px-3">
                  Halaman <strong className="font-bold text-slate-900">{page}</strong> dari {totalPages}
                </span>
                <button
                  type="button"
                  onClick={() => setPage((p) => Math.min(totalPages, p + 1))}
                  disabled={page >= totalPages}
                  className="px-3 py-1.5 rounded-lg border border-slate-200 bg-white text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed flex items-center gap-1"
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
