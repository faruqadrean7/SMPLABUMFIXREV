'use client';

import React, { useState } from 'react';
import Link from 'next/link';
import { useQuery } from '@tanstack/react-query';
import {
  GraduationCap,
  Users,
  Search,
  ChevronLeft,
  ChevronRight,
  Building2,
} from 'lucide-react';
import { publicApi } from '@/features/public-site/public-api';
import { PublicTeacherItem, PublicStaffItem } from '@/features/public-site/types';
import { LoadingSpinner } from '@/components/shared/loading-spinner';
import { EmptyState } from '@/components/shared/empty-state';
import { ErrorState } from '@/components/shared/error-state';

type DirectoryTab = 'semua' | 'guru' | 'staf';

function getInitials(name: string): string {
  const parts = name.trim().split(/\s+/).filter(Boolean);
  if (parts.length === 0) return 'P';
  if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase();
  return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
}

/**
 * Avatar Guru dengan fallback inisial elegan
 */
function TeacherAvatar({ teacher }: { teacher: PublicTeacherItem }) {
  const [imgError, setImgError] = useState(false);
  const photoId = teacher.photo?.id;

  if (photoId && !imgError) {
    return (
      <div className="relative w-full aspect-square bg-slate-100 overflow-hidden">
        {/* eslint-disable-next-line @next/next/no-img-element */}
        <img
          src={`/api/v1/files/${photoId}/view`}
          alt={`Foto ${teacher.full_name}`}
          className="w-full h-full object-cover object-top transition-transform duration-300 group-hover:scale-105"
          onError={() => setImgError(true)}
          loading="lazy"
        />
      </div>
    );
  }

  return (
    <div className="w-full aspect-square bg-gradient-to-br from-[#062940] to-[#0A3D62] flex flex-col items-center justify-center text-white select-none">
      <span className="font-pub-heading text-2xl sm:text-3xl font-bold tracking-wider text-[#C89332]">
        {getInitials(teacher.full_name)}
      </span>
      <span className="text-[10px] uppercase tracking-widest text-white/60 mt-1 font-medium">
        Pendidik
      </span>
    </div>
  );
}

/**
 * Avatar Staf dengan inisial
 */
function StaffAvatar({ staff }: { staff: PublicStaffItem }) {
  return (
    <div className="w-14 h-14 rounded-full bg-gradient-to-br from-[#0A3D62] to-[#1E5F8A] text-white flex items-center justify-center font-pub-heading font-bold text-lg text-[#F5DEB3] shadow-inner select-none shrink-0">
      {getInitials(staff.full_name)}
    </div>
  );
}

export default function PublicDirectoryPage() {
  const [activeTab, setActiveTab] = useState<DirectoryTab>('semua');
  const [searchQuery, setSearchQuery] = useState('');
  const [activeSearch, setActiveSearch] = useState('');

  // Pagination untuk masing-masing tab independen
  const [teacherPage, setTeacherPage] = useState(1);
  const [staffPage, setStaffPage] = useState(1);

  // Ambil daftar guru publik
  const {
    data: teachersResponse,
    isLoading: isLoadingTeachers,
    isError: isErrorTeachers,
    refetch: refetchTeachers,
  } = useQuery({
    queryKey: ['public-teachers', teacherPage, activeSearch],
    queryFn: () =>
      publicApi.getPublicTeachers({
        page: teacherPage,
        per_page: activeTab === 'semua' ? 24 : 12,
        search: activeSearch || undefined,
      }),
    staleTime: 1000 * 60 * 5,
  });

  // Ambil daftar staf publik
  const {
    data: staffResponse,
    isLoading: isLoadingStaff,
    isError: isErrorStaff,
    refetch: refetchStaff,
  } = useQuery({
    queryKey: ['public-staff', staffPage, activeSearch],
    queryFn: () =>
      publicApi.getPublicStaff({
        page: staffPage,
        per_page: activeTab === 'semua' ? 24 : 12,
        search: activeSearch || undefined,
      }),
    staleTime: 1000 * 60 * 5,
  });

  const teachers = teachersResponse?.data || [];
  const staffList = staffResponse?.data || [];

  const teacherMeta = teachersResponse?.meta;
  const staffMeta = staffResponse?.meta;

  const totalTeacherPages = teacherMeta?.totalPages || 1;
  const totalStaffPages = staffMeta?.totalPages || 1;

  const handleSearchSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setActiveSearch(searchQuery.trim());
    setTeacherPage(1);
    setStaffPage(1);
  };

  const handleClearSearch = () => {
    setSearchQuery('');
    setActiveSearch('');
    setTeacherPage(1);
    setStaffPage(1);
  };

  const handleTabChange = (tab: DirectoryTab) => {
    setActiveTab(tab);
    setTeacherPage(1);
    setStaffPage(1);
  };

  const isInitialLoading =
    (activeTab === 'semua' && (isLoadingTeachers || isLoadingStaff)) ||
    (activeTab === 'guru' && isLoadingTeachers) ||
    (activeTab === 'staf' && isLoadingStaff);

  const isAnyError =
    (activeTab === 'semua' && isErrorTeachers && isErrorStaff) ||
    (activeTab === 'guru' && isErrorTeachers) ||
    (activeTab === 'staf' && isErrorStaff);

  const totalItemsCount =
    (activeTab === 'semua' ? teachers.length + staffList.length : 0) ||
    (activeTab === 'guru' ? teachers.length : 0) ||
    (activeTab === 'staf' ? staffList.length : 0);

  const isCompletelyEmpty = !isInitialLoading && !isAnyError && totalItemsCount === 0;

  return (
    <div className="flex-1 flex flex-col bg-slate-50 min-h-screen">
      {/* =========================================================================
          HERO BANNER
          ========================================================================= */}
      <section className="bg-gradient-to-b from-[#062940] to-[#0A3D62] text-white py-12 sm:py-16 border-b border-white/10">
        <div className="w-full px-4 sm:px-8 lg:px-12 xl:px-16">
          <nav aria-label="Breadcrumb" className="mb-4">
            <ol className="flex items-center gap-2 text-xs text-white/70">
              <li>
                <Link href="/" className="hover:text-white transition-colors">
                  Beranda
                </Link>
              </li>
              <li aria-hidden="true" className="text-white/40">
                /
              </li>
              <li>
                <Link href="/profil" className="hover:text-white transition-colors">
                  Profil
                </Link>
              </li>
              <li aria-hidden="true" className="text-white/40">
                /
              </li>
              <li className="text-white font-medium" aria-current="page">
                Direktori Pendidik &amp; Staf
              </li>
            </ol>
          </nav>

          <div className="max-w-3xl">
            <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/10 text-white/90 text-xs font-medium mb-3 backdrop-blur-sm">
              <GraduationCap className="w-3.5 h-3.5 text-[#C89332]" />
              <span>Civitas Akademika SMP Laboratorium UM</span>
            </div>
            <h1 className="font-pub-heading text-2xl sm:text-4xl font-extrabold tracking-tight text-white">
              Direktori Guru &amp; Tenaga Kependidikan
            </h1>
            <p className="mt-3 text-sm sm:text-base text-white/80 leading-relaxed">
              Mengenal lebih dekat dewan guru pengajar berdedikasi serta tenaga kependidikan profesional yang mendampingi dan melayani putra-putri Anda dalam menempuh pendidikan berkualitas.
            </p>
          </div>
        </div>
      </section>

      {/* =========================================================================
          MAIN DIRECTORY CONTENT
          ========================================================================= */}
      <main className="w-full px-4 sm:px-8 lg:px-12 xl:px-16 py-8 sm:py-12 flex-1">
        {/* FILTER & PENCARIAN CONTROLS */}
        <div className="mb-8 bg-white p-4 sm:p-6 rounded-xl border border-slate-200/90 shadow-2xs">
          <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            {/* TABS SELECTOR */}
            <div
              className="inline-flex p-1 bg-slate-100/90 rounded-lg border border-slate-200 self-start"
              role="tablist"
              aria-label="Kategori Direktori"
            >
              <button
                type="button"
                role="tab"
                aria-selected={activeTab === 'semua'}
                onClick={() => handleTabChange('semua')}
                className={`px-4 py-2 text-xs sm:text-sm font-semibold rounded-md transition-all ${
                  activeTab === 'semua'
                    ? 'bg-white text-[#0A3D62] shadow-xs'
                    : 'text-slate-600 hover:text-slate-900'
                }`}
              >
                Semua
              </button>
              <button
                type="button"
                role="tab"
                aria-selected={activeTab === 'guru'}
                onClick={() => handleTabChange('guru')}
                className={`px-4 py-2 text-xs sm:text-sm font-semibold rounded-md transition-all flex items-center gap-1.5 ${
                  activeTab === 'guru'
                    ? 'bg-white text-[#0A3D62] shadow-xs'
                    : 'text-slate-600 hover:text-slate-900'
                }`}
              >
                <GraduationCap className="w-3.5 h-3.5" />
                <span>Dewan Guru</span>
              </button>
              <button
                type="button"
                role="tab"
                aria-selected={activeTab === 'staf'}
                onClick={() => handleTabChange('staf')}
                className={`px-4 py-2 text-xs sm:text-sm font-semibold rounded-md transition-all flex items-center gap-1.5 ${
                  activeTab === 'staf'
                    ? 'bg-white text-[#0A3D62] shadow-xs'
                    : 'text-slate-600 hover:text-slate-900'
                }`}
              >
                <Users className="w-3.5 h-3.5" />
                <span>Tenaga Kependidikan</span>
              </button>
            </div>

            {/* PENCARIAN FORM */}
            <form onSubmit={handleSearchSubmit} className="flex flex-col sm:flex-row gap-2 w-full lg:w-auto">
              <div className="relative flex-1 sm:w-80">
                <Search className="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
                <input
                  type="text"
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  placeholder="Cari nama, mata pelajaran, posisi..."
                  className="w-full pl-10 pr-4 py-2 text-xs sm:text-sm rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-[#0A3D62] focus:border-transparent bg-slate-50/50"
                  aria-label="Cari pendidik dan staf"
                />
              </div>
              <div className="flex gap-2">
                <button
                  type="submit"
                  className="px-5 py-2 text-xs sm:text-sm font-semibold text-white bg-[#0A3D62] hover:bg-[#062940] rounded-lg transition-colors shrink-0"
                >
                  Cari
                </button>
                {activeSearch && (
                  <button
                    type="button"
                    onClick={handleClearSearch}
                    className="px-4 py-2 text-xs sm:text-sm font-semibold text-slate-600 hover:text-slate-900 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors shrink-0"
                  >
                    Reset
                  </button>
                )}
              </div>
            </form>
          </div>

          {activeSearch && (
            <p className="mt-3 text-xs text-slate-500">
              Menampilkan hasil pencarian untuk &ldquo;<span className="font-semibold text-slate-700">{activeSearch}</span>&rdquo;
            </p>
          )}
        </div>

        {/* =========================================================================
            STATE: LOADING
            ========================================================================= */}
        {isInitialLoading && (
          <div className="py-16 flex flex-col items-center justify-center">
            <LoadingSpinner size="lg" />
            <p className="mt-4 text-xs sm:text-sm text-slate-500 font-medium">
              Memuat direktori guru dan staf SMP Laboratorium UM...
            </p>
          </div>
        )}

        {/* =========================================================================
            STATE: ERROR
            ========================================================================= */}
        {!isInitialLoading && isAnyError && (
          <div className="py-12">
            <ErrorState
              title="Gagal Memuat Direktori"
              message="Terjadi kendala saat memuat data direktori guru dan tenaga kependidikan. Silakan coba kembali."
              onRetry={() => {
                refetchTeachers();
                refetchStaff();
              }}
            />
          </div>
        )}

        {/* =========================================================================
            STATE: KOSONG (EMPTY STATE)
            ========================================================================= */}
        {isCompletelyEmpty && (
          <div className="py-12 bg-white rounded-xl border border-slate-200">
            <EmptyState
              title="Pendidik atau Staf Tidak Ditemukan"
              description={
                activeSearch
                  ? `Tidak ada data guru atau tenaga kependidikan yang sesuai dengan kata kunci "${activeSearch}".`
                  : 'Belum ada data direktori pendidik dan staf yang aktif untuk ditampilkan.'
              }
              actionLabel={activeSearch ? 'Tampilkan Semua Pendidik' : undefined}
              onAction={activeSearch ? handleClearSearch : undefined}
            />
          </div>
        )}

        {/* =========================================================================
            STATE: HASIL DATA TERSEDIA
            ========================================================================= */}
        {!isInitialLoading && !isAnyError && !isCompletelyEmpty && (
          <div className="space-y-12">
            {/* -------------------------------------------------------------------
                SEKSI DEWAN GURU
                ------------------------------------------------------------------- */}
            {(activeTab === 'semua' || activeTab === 'guru') && teachers.length > 0 && (
              <section aria-labelledby="heading-teachers">
                <div className="flex items-center justify-between mb-6 border-b border-slate-200 pb-3">
                  <div>
                    <h2
                      id="heading-teachers"
                      className="font-pub-heading text-xl sm:text-2xl font-bold text-[#062940] flex items-center gap-2.5"
                    >
                      <GraduationCap className="w-6 h-6 text-[#0A3D62]" />
                      <span>Dewan Guru Pengajar</span>
                    </h2>
                    <p className="text-xs sm:text-sm text-slate-500 mt-1">
                      Tenaga pengajar berdedikasi dengan kompetensi akademik dan kualifikasi pedagogis unggul.
                    </p>
                  </div>
                  <span className="text-xs font-semibold px-2.5 py-1 bg-blue-50 text-[#0A3D62] rounded-full border border-blue-100">
                    {teacherMeta?.total ?? teachers.length} Guru
                  </span>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-5">
                  {teachers.map((teacher) => (
                    <article
                      key={teacher.id}
                      className="bg-white rounded-xl border border-slate-200/90 shadow-2xs hover:shadow-md hover:border-[#0A3D62]/40 transition-all duration-200 overflow-hidden flex flex-col group"
                    >
                      {/* Foto / Inisial Avatar */}
                      <TeacherAvatar teacher={teacher} />

                      {/* Detail Guru */}
                      <div className="p-4 flex-1 flex flex-col justify-between">
                        <div>
                          <h3 className="font-pub-heading font-bold text-slate-900 text-sm sm:text-base leading-snug group-hover:text-[#0A3D62] transition-colors">
                            {teacher.full_name}
                          </h3>
                          <p className="text-xs text-slate-500 font-medium mt-1">
                            {teacher.position || 'Guru Pengajar'}
                          </p>
                        </div>

                        {/* Mata Pelajaran yang Diampu */}
                        <div className="mt-4 pt-3 border-t border-slate-100">
                          <div className="flex flex-wrap gap-1.5">
                            {teacher.subjects && teacher.subjects.length > 0 ? (
                              teacher.subjects.map((subj) => (
                                <span
                                  key={subj.id}
                                  className="inline-flex items-center text-[10px] sm:text-[11px] font-medium px-2 py-0.5 rounded-md bg-blue-50/80 text-[#0A3D62] border border-blue-100"
                                >
                                  {subj.name}
                                </span>
                              ))
                            ) : (
                              <span className="inline-flex items-center text-[10px] sm:text-[11px] text-slate-400 italic">
                                Pengajar SMP Lab UM
                              </span>
                            )}
                          </div>
                        </div>
                      </div>
                    </article>
                  ))}
                </div>

                {/* Pagination Khusus Tab Guru */}
                {activeTab === 'guru' && totalTeacherPages > 1 && (
                  <div className="mt-8 flex items-center justify-between border-t border-slate-200 pt-4">
                    <p className="text-xs text-slate-500">
                      Halaman <span className="font-semibold text-slate-700">{teacherPage}</span> dari{' '}
                      <span className="font-semibold text-slate-700">{totalTeacherPages}</span>
                    </p>
                    <div className="flex gap-2">
                      <button
                        type="button"
                        onClick={() => setTeacherPage((prev) => Math.max(1, prev - 1))}
                        disabled={teacherPage <= 1}
                        className="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold rounded-lg border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed"
                      >
                        <ChevronLeft className="w-3.5 h-3.5" />
                        <span>Sebelumnya</span>
                      </button>
                      <button
                        type="button"
                        onClick={() => setTeacherPage((prev) => Math.min(totalTeacherPages, prev + 1))}
                        disabled={teacherPage >= totalTeacherPages}
                        className="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold rounded-lg border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed"
                      >
                        <span>Selanjutnya</span>
                        <ChevronRight className="w-3.5 h-3.5" />
                      </button>
                    </div>
                  </div>
                )}
              </section>
            )}

            {/* -------------------------------------------------------------------
                SEKSI TENAGA KEPENDIDIKAN (STAF)
                ------------------------------------------------------------------- */}
            {(activeTab === 'semua' || activeTab === 'staf') && staffList.length > 0 && (
              <section aria-labelledby="heading-staff">
                <div className="flex items-center justify-between mb-6 border-b border-slate-200 pb-3">
                  <div>
                    <h2
                      id="heading-staff"
                      className="font-pub-heading text-xl sm:text-2xl font-bold text-[#062940] flex items-center gap-2.5"
                    >
                      <Users className="w-6 h-6 text-[#0A3D62]" />
                      <span>Tenaga Kependidikan &amp; Tata Usaha</span>
                    </h2>
                    <p className="text-xs sm:text-sm text-slate-500 mt-1">
                      Staf administrasi, perpustakaan, sarana prasarana, dan layanan penunjang operasional sekolah.
                    </p>
                  </div>
                  <span className="text-xs font-semibold px-2.5 py-1 bg-amber-50 text-amber-900 rounded-full border border-amber-200">
                    {staffMeta?.total ?? staffList.length} Staf
                  </span>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                  {staffList.map((staff) => (
                    <article
                      key={staff.id}
                      className="bg-white rounded-xl border border-slate-200/90 shadow-2xs hover:shadow-md hover:border-[#C89332]/50 transition-all duration-200 p-4 flex items-center gap-3.5 group"
                    >
                      {/* Avatar Staf */}
                      <StaffAvatar staff={staff} />

                      {/* Detail Staf */}
                      <div className="min-w-0 flex-1">
                        <h3 className="font-pub-heading font-bold text-slate-900 text-sm leading-tight truncate group-hover:text-[#0A3D62] transition-colors">
                          {staff.full_name}
                        </h3>
                        <p className="text-xs text-slate-500 font-medium mt-0.5 truncate">
                          {staff.position || 'Staf Operasional'}
                        </p>
                        <div className="mt-2">
                          <span className="inline-flex items-center text-[10px] font-semibold px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 border border-slate-200 truncate max-w-full">
                            <Building2 className="w-2.5 h-2.5 mr-1 text-slate-400" />
                            {staff.unit || 'Tata Usaha'}
                          </span>
                        </div>
                      </div>
                    </article>
                  ))}
                </div>

                {/* Pagination Khusus Tab Staf */}
                {activeTab === 'staf' && totalStaffPages > 1 && (
                  <div className="mt-8 flex items-center justify-between border-t border-slate-200 pt-4">
                    <p className="text-xs text-slate-500">
                      Halaman <span className="font-semibold text-slate-700">{staffPage}</span> dari{' '}
                      <span className="font-semibold text-slate-700">{totalStaffPages}</span>
                    </p>
                    <div className="flex gap-2">
                      <button
                        type="button"
                        onClick={() => setStaffPage((prev) => Math.max(1, prev - 1))}
                        disabled={staffPage <= 1}
                        className="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold rounded-lg border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed"
                      >
                        <ChevronLeft className="w-3.5 h-3.5" />
                        <span>Sebelumnya</span>
                      </button>
                      <button
                        type="button"
                        onClick={() => setStaffPage((prev) => Math.min(totalStaffPages, prev + 1))}
                        disabled={staffPage >= totalStaffPages}
                        className="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold rounded-lg border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed"
                      >
                        <span>Selanjutnya</span>
                        <ChevronRight className="w-3.5 h-3.5" />
                      </button>
                    </div>
                  </div>
                )}
              </section>
            )}
          </div>
        )}
      </main>
    </div>
  );
}
