'use client';

import React, { useState } from 'react';
import Link from 'next/link';
import { useQuery } from '@tanstack/react-query';
import {
  Calendar as CalendarIcon,
  Search,
  ChevronLeft,
  ChevronRight,
  Clock,
  RotateCw,
  AlertCircle,
  Tag,
  CalendarDays,
  Sparkles,
} from 'lucide-react';
import { publicApi } from '@/features/public-site/public-api';
import { PublicEventItem } from '@/features/public-site/types';
import { LoadingSpinner } from '@/components/shared/loading-spinner';
import { EmptyState } from '@/components/shared/empty-state';
import { ErrorState } from '@/components/shared/error-state';

function formatEventDate(startStr: string, endStr: string): {
  day: string;
  monthYear: string;
  timeRange: string;
  isMultiDay: boolean;
} {
  try {
    const start = new Date(startStr);
    const end = new Date(endStr);

    const day = start.getDate().toString().padStart(2, '0');
    const monthYear = new Intl.DateTimeFormat('id-ID', {
      month: 'short',
      year: 'numeric',
    }).format(start);

    const isSameDay =
      start.getFullYear() === end.getFullYear() &&
      start.getMonth() === end.getMonth() &&
      start.getDate() === end.getDate();

    let timeRange = '';
    if (isSameDay) {
      const startTime = start.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
      const endTime = end.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
      timeRange = `${startTime} - ${endTime} WIB`;
    } else {
      const endFormatted = new Intl.DateTimeFormat('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
      }).format(end);
      timeRange = `s.d. ${endFormatted}`;
    }

    return {
      day,
      monthYear,
      timeRange,
      isMultiDay: !isSameDay,
    };
  } catch {
    return {
      day: '--',
      monthYear: '---',
      timeRange: '',
      isMultiDay: false,
    };
  }
}

function getEventCategoryStyle(category?: string) {
  const norm = (category || '').toLowerCase();
  switch (norm) {
    case 'ujian':
      return {
        badge: 'bg-rose-100 text-rose-800 border-rose-200',
        accent: 'bg-rose-600',
        label: 'Ujian / Asesmen',
      };
    case 'libur':
      return {
        badge: 'bg-emerald-100 text-emerald-800 border-emerald-200',
        accent: 'bg-emerald-600',
        label: 'Libur Sekolah',
      };
    case 'event':
    default:
      return {
        badge: 'bg-blue-100 text-blue-800 border-blue-200',
        accent: 'bg-[#0A3D62]',
        label: 'Kegiatan / Acara',
      };
  }
}

export default function PublicAgendaPage() {
  const [page, setPage] = useState<number>(1);
  const [selectedCategory, setSelectedCategory] = useState<string>('');
  const [searchQuery, setSearchQuery] = useState<string>('');
  const [activeSearch, setActiveSearch] = useState<string>('');

  const {
    data: eventsResponse,
    isLoading,
    isError,
    refetch,
  } = useQuery({
    queryKey: ['public-events', page, selectedCategory, activeSearch],
    queryFn: () =>
      publicApi.getPublicEvents({
        page,
        per_page: 9,
        category: selectedCategory || undefined,
        search: activeSearch || undefined,
      }),
    staleTime: 1000 * 60 * 3,
  });

  const events = eventsResponse?.data || [];
  const meta = eventsResponse?.meta;
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
                Agenda Kegiatan
              </li>
            </ol>
          </nav>

          <div className="max-w-3xl">
            <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/10 text-xs font-semibold text-white/90 mb-3 border border-white/15">
              <CalendarDays className="w-3.5 h-3.5 text-[#C89332]" />
              <span>Kalender Akademik &amp; Aktivitas Sekolah</span>
            </div>
            <h1 className="font-pub-heading text-3xl sm:text-4xl lg:text-5xl font-bold text-white tracking-tight leading-tight">
              Agenda &amp; Jadwal Kegiatan
            </h1>
            <p className="text-sm sm:text-base text-white/80 leading-relaxed mt-3">
              Informasi jadwal pelaksanaan ujian, libur resmi, serta berbagai agenda penting sivitas akademika SMP Laboratorium UM.
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
              { id: '', label: 'Semua Agenda' },
              { id: 'event', label: 'Kegiatan Sekolah' },
              { id: 'ujian', label: 'Ujian / Asesmen' },
              { id: 'libur', label: 'Libur Resmi' },
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
                placeholder="Cari kegiatan..."
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
          DAFTAR AGENDA
          ========================================================================= */}
      <main className="w-full px-4 sm:px-8 lg:px-12 xl:px-16 py-10 sm:py-12">
        {isLoading && (
          <div className="py-24">
            <LoadingSpinner label="Memuat agenda kegiatan..." size="lg" />
          </div>
        )}

        {isError && (
          <div className="py-16">
            <ErrorState
              title="Gagal Memuat Agenda"
              message="Terjadi kendala saat mengambil data agenda kegiatan publik. Silakan coba lagi."
              onRetry={() => refetch()}
            />
          </div>
        )}

        {!isLoading && !isError && events.length === 0 && (
          <div className="bg-white rounded-2xl border border-slate-200 p-8 sm:p-12 text-center">
            <EmptyState
              title="Belum Ada Agenda"
              description={
                activeSearch || selectedCategory
                  ? 'Tidak ditemukan agenda kegiatan yang sesuai dengan kriteria pencarian atau kategori yang dipilih.'
                  : 'Saat ini belum ada agenda kegiatan publik yang dijadwalkan oleh pihak sekolah.'
              }
              actionLabel={activeSearch ? 'Reset Pencarian' : undefined}
              onAction={activeSearch ? handleClearSearch : undefined}
            />
          </div>
        )}

        {!isLoading && !isError && events.length > 0 && (
          <div className="space-y-6">
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">
              {events.map((event: PublicEventItem) => {
                const dateInfo = formatEventDate(event.start_at, event.end_at);
                const catStyle = getEventCategoryStyle(event.category);

                return (
                  <article
                    key={event.id}
                    className="bg-white rounded-2xl border border-slate-200/90 shadow-2xs hover:shadow-md hover:border-[#C89332]/40 transition-all p-5 flex flex-col justify-between group"
                  >
                    <div>
                      {/* Tanggal & Kategori */}
                      <div className="flex items-start justify-between gap-3 mb-4">
                        <div className="flex items-center gap-3">
                          <div className="w-12 h-12 rounded-xl bg-slate-100 flex flex-col items-center justify-center text-center shrink-0 border border-slate-200 group-hover:bg-[#062940] group-hover:text-white transition-colors">
                            <span className="text-base font-extrabold leading-none">{dateInfo.day}</span>
                            <span className="text-[10px] uppercase font-bold text-slate-500 group-hover:text-white/80 leading-tight mt-0.5">
                              {dateInfo.monthYear.split(' ')[0]}
                            </span>
                          </div>
                          <div>
                            <span className="text-[11px] font-semibold text-slate-500 block">
                              {dateInfo.monthYear}
                            </span>
                            <span className="text-xs font-bold text-slate-800 flex items-center gap-1 mt-0.5">
                              <Clock className="w-3 h-3 text-[#C89332]" />
                              {dateInfo.timeRange}
                            </span>
                          </div>
                        </div>

                        <span
                          className={`px-2.5 py-0.5 rounded-full text-[10px] font-bold border shrink-0 ${catStyle.badge}`}
                        >
                          {catStyle.label}
                        </span>
                      </div>

                      {/* Judul Agenda */}
                      <h3 className="font-pub-heading text-base font-bold text-slate-900 group-hover:text-[#0A3D62] transition-colors line-clamp-2 leading-snug">
                        {event.title}
                      </h3>
                    </div>

                    <div className="mt-5 pt-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                      <span className="text-[11px] text-slate-400">SMP Laboratorium UM</span>
                      <span className="inline-flex items-center gap-1 text-[11px] font-bold text-[#0A3D62] group-hover:text-[#C89332] transition-colors">
                        <Sparkles className="w-3 h-3 text-[#C89332]" />
                        Resmi
                      </span>
                    </div>
                  </article>
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
