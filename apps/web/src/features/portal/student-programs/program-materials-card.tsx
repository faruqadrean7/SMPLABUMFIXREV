'use client';

import React from 'react';
import Link from 'next/link';
import {
  BookOpen,
  FileText,
  Video,
  Music,
  ExternalLink,
  Download,
  AlertCircle,
  RefreshCw,
  FolderOpen,
  ChevronRight,
} from 'lucide-react';
import { useProgramMaterials, ProgramMaterialItem } from '@/lib/use-student-data';
import { cn } from '@/lib/utils';

interface ProgramMaterialsCardProps {
  programType?: 'tahfidz' | 'icp' | 'inklusi' | 'reguler';
  title?: string;
  subtitle?: string;
  limit?: number;
  compact?: boolean;
  showAllHref?: string;
  className?: string;
}

const MATERIAL_TYPE_LABELS: Record<string, { label: string; icon: React.ComponentType<{ className?: string }>; color: string }> = {
  murottal: { label: 'Audio Murottal', icon: Music, color: 'bg-emerald-50 text-emerald-800 border-emerald-200' },
  tajwid: { label: 'Panduan Tajwid', icon: BookOpen, color: 'bg-emerald-50 text-emerald-800 border-emerald-200' },
  reading: { label: 'Reading Passage', icon: FileText, color: 'bg-blue-50 text-blue-800 border-blue-200' },
  past_paper: { label: 'Cambridge Past Paper', icon: FileText, color: 'bg-blue-50 text-blue-800 border-blue-200' },
  modul_adaptif: { label: 'Modul Adaptif PPI', icon: BookOpen, color: 'bg-purple-50 text-purple-800 border-purple-200' },
  video: { label: 'Video Pembelajaran', icon: Video, color: 'bg-amber-50 text-amber-800 border-amber-200' },
  audio: { label: 'Audio Rekaman', icon: Music, color: 'bg-teal-50 text-teal-800 border-teal-200' },
  dokumen: { label: 'Dokumen Panduan', icon: FileText, color: 'bg-slate-50 text-slate-800 border-slate-200' },
};

export function ProgramMaterialsCard({
  programType,
  title = 'Materi & Sumber Belajar Program',
  subtitle = 'Materi terkurasi untuk mendukung capaian belajar khusus',
  limit,
  compact = false,
  showAllHref,
  className,
}: ProgramMaterialsCardProps) {
  const { data: response, isLoading, isError, refetch } = useProgramMaterials(
    limit ? { per_page: limit } : undefined
  );

  const materials = response?.data || [];

  return (
    <div
      className={cn(
        'bg-white border border-slate-200 rounded-xl p-4 sm:p-5 shadow-xs flex flex-col',
        className
      )}
    >
      {/* Header */}
      <div className="flex flex-wrap items-center justify-between gap-2 pb-3 border-b border-slate-100">
        <div className="flex items-center gap-2.5">
          <div className="w-8 h-8 rounded-lg bg-slate-900 text-white flex items-center justify-center shrink-0 shadow-2xs">
            <BookOpen className="w-4 h-4" />
          </div>
          <div>
            <h3 className="text-sm font-extrabold text-slate-900 tracking-tight">{title}</h3>
            {subtitle && <p className="text-[11px] text-slate-500">{subtitle}</p>}
          </div>
        </div>

        {showAllHref && (
          <Link
            href={showAllHref}
            className="text-xs font-bold text-slate-700 hover:text-slate-900 inline-flex items-center gap-1 hover:underline"
          >
            Lihat Semua <ChevronRight className="w-3.5 h-3.5" />
          </Link>
        )}
      </div>

      {/* Content State */}
      <div className="mt-3.5 flex-1">
        {isLoading && (
          <div className="space-y-2.5 py-4">
            <div className="h-14 bg-slate-100 rounded-lg animate-pulse" />
            <div className="h-14 bg-slate-100 rounded-lg animate-pulse" />
          </div>
        )}

        {isError && (
          <div className="py-6 text-center rounded-lg border border-red-100 bg-red-50/50 p-4">
            <AlertCircle className="w-6 h-6 text-red-500 mx-auto mb-1.5" />
            <p className="text-xs font-bold text-red-800">Gagal memuat materi</p>
            <p className="text-[11px] text-red-600 mt-0.5">Terjadi kendala koneksi ke server materi.</p>
            <button
              onClick={() => refetch()}
              className="mt-2.5 inline-flex items-center gap-1 px-3 py-1 text-xs font-bold text-red-700 bg-white border border-red-200 rounded-md hover:bg-red-50 cursor-pointer"
            >
              <RefreshCw className="w-3 h-3" /> Coba Lagi
            </button>
          </div>
        )}

        {!isLoading && !isError && materials.length === 0 && (
          <div className="py-8 text-center rounded-lg border border-dashed border-slate-200 p-4">
            <FolderOpen className="w-8 h-8 text-slate-400 mx-auto mb-2 opacity-60" />
            <p className="text-xs font-bold text-slate-700">Belum Ada Materi Khusus</p>
            <p className="text-[11px] text-slate-500 mt-1 max-w-sm mx-auto">
              Guru pembina belum membagikan modul, audio, atau bahan materi untuk program ini.
            </p>
          </div>
        )}

        {!isLoading && !isError && materials.length > 0 && (
          <div className="space-y-2.5">
            {materials.map((mat) => {
              const typeMeta = MATERIAL_TYPE_LABELS[mat.material_type] || {
                label: mat.material_type,
                icon: FileText,
                color: 'bg-slate-50 text-slate-700 border-slate-200',
              };
              const TypeIcon = typeMeta.icon;

              return (
                <div
                  key={mat.id}
                  className="p-3 rounded-lg border border-slate-200 hover:border-slate-300 transition-colors bg-white flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-2xs"
                >
                  <div className="flex items-start gap-2.5">
                    <div className="p-2 rounded-lg bg-slate-100 text-slate-700 shrink-0 mt-0.5">
                      <TypeIcon className="w-4 h-4" />
                    </div>
                    <div>
                      <div className="flex flex-wrap items-center gap-2">
                        <span className="text-xs font-bold text-slate-900">{mat.title}</span>
                        <span
                          className={cn(
                            'px-2 py-0.5 rounded text-[10px] font-bold border',
                            typeMeta.color
                          )}
                        >
                          {typeMeta.label}
                        </span>
                      </div>
                      {mat.description && (
                        <p className="text-xs text-slate-600 mt-1 line-clamp-2 leading-relaxed">
                          {mat.description}
                        </p>
                      )}
                      <div className="text-[10px] text-slate-400 mt-1">
                        Diunggah pada {new Date(mat.created_at).toLocaleDateString('id-ID', {
                          day: 'numeric',
                          month: 'short',
                          year: 'numeric',
                        })}
                      </div>
                    </div>
                  </div>

                  <div className="flex items-center gap-2 self-end sm:self-auto shrink-0">
                    {mat.external_url && (
                      <a
                        href={mat.external_url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-bold text-blue-900 bg-blue-50 hover:bg-blue-100 border border-blue-200 transition-colors"
                      >
                        Buka Tautan <ExternalLink className="w-3.5 h-3.5" />
                      </a>
                    )}
                    {mat.file?.url && (
                      <a
                        href={mat.file.url}
                        download
                        className="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-bold text-slate-800 bg-slate-100 hover:bg-slate-200 border border-slate-200 transition-colors"
                      >
                        Unduh <Download className="w-3.5 h-3.5" />
                      </a>
                    )}
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </div>
    </div>
  );
}
