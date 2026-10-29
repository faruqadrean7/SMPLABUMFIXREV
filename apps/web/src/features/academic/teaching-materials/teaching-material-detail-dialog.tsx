'use client';

import React, { useState, useEffect } from 'react';
import {
  TeachingMaterialItem,
  TEACHING_MATERIAL_TYPE_LABELS,
  TEACHING_MATERIAL_TYPE_COLORS,
} from '@/types/academic';
import { academicApi } from '../academic-api';
import { Button } from '@/components/ui/button';
import { toast } from '@/components/ui/toast';
import {
  X,
  BookOpen,
  Calendar,
  User,
  Download,
  History,
  Layers,
  FileText,
  Loader2,
  ExternalLink,
  PlusCircle,
} from 'lucide-react';

interface TeachingMaterialDetailDialogProps {
  isOpen: boolean;
  onClose: () => void;
  materialId: string | null;
  onNewVersion?: () => void;
}

export function TeachingMaterialDetailDialog({
  isOpen,
  onClose,
  materialId,
  onNewVersion,
}: TeachingMaterialDetailDialogProps) {
  const [material, setMaterial] = useState<TeachingMaterialItem | null>(null);
  const [loading, setLoading] = useState(false);
  const [downloadingId, setDownloadingId] = useState<string | null>(null);

  useEffect(() => {
    if (isOpen && materialId) {
      setLoading(true);
      academicApi
        .getTeachingMaterial(materialId)
        .then((res) => {
          setMaterial(res);
        })
        .catch(() => {
          setMaterial(null);
        })
        .finally(() => {
          setLoading(false);
        });
    } else {
      setMaterial(null);
    }
  }, [isOpen, materialId]);

  const handleDownload = async (fileId?: string | null) => {
    if (!fileId) return;
    setDownloadingId(fileId);
    try {
      const res = await academicApi.getFileUrl(fileId);
      if (res.url) {
        window.open(res.url, '_blank', 'noopener,noreferrer');
      }
    } catch {
      toast.error('Gagal mengambil tautan unduhan dokumen.');
    } finally {
      setDownloadingId(null);
    }
  };

  const formatFileSize = (bytes?: number) => {
    if (!bytes) return '-';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
  };

  const formatDateIndo = (dateStr?: string) => {
    if (!dateStr) return '-';
    try {
      const d = new Date(dateStr);
      return d.toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
      });
    } catch {
      return dateStr;
    }
  };

  if (!isOpen) return null;

  const badgeColor = material
    ? TEACHING_MATERIAL_TYPE_COLORS[material.material_type] || {
        badge: 'bg-gray-100 text-gray-700 border-gray-200',
      }
    : { badge: '' };

  const uploaderName =
    material?.uploader?.teacher?.full_name ||
    material?.uploader?.username ||
    'Guru Pengunggah';

  const uploaderNip = material?.uploader?.teacher?.nip;

  // Flatten versions list: include root + sub-versions
  const allVersions: TeachingMaterialItem[] = [];
  if (material) {
    // Current material
    allVersions.push(material);
    // Sub-versions if exist
    if (material.versions && material.versions.length > 0) {
      material.versions.forEach((v) => {
        if (v.id !== material.id) {
          allVersions.push(v);
        }
      });
    }
    // Sort versions by version number ascending
    allVersions.sort((a, b) => a.version - b.version);
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div className="w-full max-w-2xl rounded-2xl bg-white shadow-xl flex flex-col max-h-[90vh] overflow-hidden">
        {/* Header */}
        <div className="flex items-center justify-between border-b border-gray-100 px-6 py-4">
          <div className="flex items-center gap-3">
            <div className="rounded-xl bg-emerald-50 p-2.5 text-emerald-600 border border-emerald-100">
              <BookOpen className="h-5 w-5" />
            </div>
            <div>
              <h2 className="text-base font-bold text-gray-900">
                Detail Perangkat Pembelajaran
              </h2>
              <p className="text-xs text-gray-500">
                Informasi katalog, berkas rilis, dan riwayat revisi dokumen
              </p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition"
          >
            <X className="h-5 w-5" />
          </button>
        </div>

        {/* Body */}
        <div className="p-6 overflow-y-auto space-y-5">
          {loading ? (
            <div className="flex items-center justify-center py-12 gap-2 text-xs text-gray-500">
              <Loader2 className="h-5 w-5 animate-spin text-emerald-600" />
              <span>Memuat detail dokumen perangkat...</span>
            </div>
          ) : !material ? (
            <div className="text-center py-10 text-xs text-gray-500">
              Perangkat ajar tidak ditemukan atau telah dihapus.
            </div>
          ) : (
            <>
              {/* Title & Badge */}
              <div className="space-y-2">
                <div className="flex flex-wrap items-center gap-2">
                  <span
                    className={`px-2.5 py-0.5 text-xs font-semibold rounded-full border ${badgeColor.badge}`}
                  >
                    {TEACHING_MATERIAL_TYPE_LABELS[material.material_type]}
                  </span>
                  <span className="text-xs font-bold bg-blue-50 text-blue-700 px-2.5 py-0.5 rounded-full border border-blue-200">
                    Versi Terkini: v{material.version}
                  </span>
                </div>
                <h3 className="text-base font-bold text-gray-900 leading-snug">
                  {material.title}
                </h3>
              </div>

              {/* Meta Grid */}
              <div className="grid grid-cols-2 gap-3 rounded-xl border border-gray-200 bg-gray-50/70 p-4 text-xs">
                <div className="space-y-1">
                  <span className="text-gray-500 block text-[11px] flex items-center gap-1">
                    <BookOpen className="h-3.5 w-3.5" /> Mata Pelajaran
                  </span>
                  <span className="font-semibold text-gray-900">
                    {material.subject ? `${material.subject.name} (${material.subject.code})` : 'Lintas Mapel / Umum'}
                  </span>
                </div>

                <div className="space-y-1">
                  <span className="text-gray-500 block text-[11px] flex items-center gap-1">
                    <Layers className="h-3.5 w-3.5" /> Tingkat Kelas
                  </span>
                  <span className="font-semibold text-gray-900">
                    {material.gradeLevel ? `${material.gradeLevel.name} (Tingkat ${material.gradeLevel.level})` : 'Semua Tingkat'}
                  </span>
                </div>

                <div className="space-y-1">
                  <span className="text-gray-500 block text-[11px] flex items-center gap-1">
                    <User className="h-3.5 w-3.5" /> Pendidik Pengunggah
                  </span>
                  <span className="font-semibold text-gray-900">
                    {uploaderName} {uploaderNip ? `(NIP: ${uploaderNip})` : ''}
                  </span>
                </div>

                <div className="space-y-1">
                  <span className="text-gray-500 block text-[11px] flex items-center gap-1">
                    <Calendar className="h-3.5 w-3.5" /> Tanggal Rilis
                  </span>
                  <span className="font-semibold text-gray-900">
                    {formatDateIndo(material.created_at)}
                  </span>
                </div>
              </div>

              {/* Description */}
              {material.description && (
                <div className="space-y-1 text-xs">
                  <span className="font-bold text-gray-700 flex items-center gap-1.5">
                    <FileText className="h-4 w-4 text-gray-500" />
                    Deskripsi & Catatan Penggunaan:
                  </span>
                  <p className="bg-white border rounded-lg p-3 text-gray-600 leading-relaxed whitespace-pre-line text-xs">
                    {material.description}
                  </p>
                </div>
              )}

              {/* Current Active File Card */}
              <div className="rounded-xl border border-emerald-200 bg-emerald-50/40 p-4 space-y-3">
                <div className="flex items-center justify-between">
                  <span className="text-xs font-bold text-emerald-900 flex items-center gap-1.5">
                    <Download className="h-4 w-4 text-emerald-600" />
                    Berkas Utama Versi v{material.version}
                  </span>
                  <span className="text-[11px] text-gray-500 font-mono">
                    {formatFileSize(material.file?.size_bytes)}
                  </span>
                </div>

                <div className="flex items-center justify-between bg-white rounded-lg p-3 border border-emerald-100">
                  <div className="min-w-0 pr-3">
                    <span className="font-medium text-gray-800 text-xs block truncate">
                      {material.file?.object_key?.split('/').pop() || 'Dokumen Perangkat Ajar'}
                    </span>
                    <span className="text-[10px] text-gray-400 block">
                      Tipe: {material.file?.mime_type || 'application/octet-stream'}
                    </span>
                  </div>
                  <Button
                    size="sm"
                    onClick={() => handleDownload(material.file_id)}
                    disabled={downloadingId === material.file_id}
                    className="bg-emerald-600 hover:bg-emerald-700 text-white text-xs gap-1.5 shrink-0"
                  >
                    {downloadingId === material.file_id ? (
                      <Loader2 className="h-3.5 w-3.5 animate-spin" />
                    ) : (
                      <ExternalLink className="h-3.5 w-3.5" />
                    )}
                    <span>Unduh Berkas</span>
                  </Button>
                </div>
              </div>

              {/* Version History List */}
              <div className="space-y-2">
                <span className="text-xs font-bold text-gray-800 flex items-center gap-1.5">
                  <History className="h-4 w-4 text-blue-600" />
                  Riwayat Revisi Dokumen ({allVersions.length} Versi)
                </span>

                <div className="divide-y divide-gray-100 border rounded-xl overflow-hidden bg-white">
                  {allVersions.map((v) => {
                    const isLatest = v.version === material.version;
                    return (
                      <div
                        key={v.id}
                        className={`p-3 text-xs flex items-center justify-between transition ${
                          isLatest ? 'bg-blue-50/30' : 'hover:bg-gray-50'
                        }`}
                      >
                        <div className="space-y-0.5 min-w-0 pr-3">
                          <div className="flex items-center gap-2">
                            <span className="font-bold text-blue-700 bg-blue-100 px-1.5 py-0.5 rounded text-[11px]">
                              v{v.version}
                            </span>
                            {isLatest && (
                              <span className="text-[10px] bg-emerald-100 text-emerald-800 font-semibold px-1.5 py-0.2 rounded">
                                Aktif
                              </span>
                            )}
                            <span className="text-gray-400 text-[11px]">
                              {formatDateIndo(v.created_at)}
                            </span>
                          </div>
                          {v.description && (
                            <p className="text-[11px] text-gray-600 italic line-clamp-1">
                              &ldquo;{v.description}&rdquo;
                            </p>
                          )}
                        </div>

                        <Button
                          size="sm"
                          variant="outline"
                          onClick={() => handleDownload(v.file_id)}
                          disabled={downloadingId === v.file_id}
                          className="h-7 text-xs px-2.5 gap-1 shrink-0"
                          title={`Unduh versi v${v.version}`}
                        >
                          <Download className="h-3 w-3" />
                          <span>Unduh</span>
                        </Button>
                      </div>
                    );
                  })}
                </div>
              </div>
            </>
          )}
        </div>

        {/* Footer */}
        <div className="border-t border-gray-100 bg-gray-50 px-6 py-4 flex items-center justify-between">
          <Button
            type="button"
            variant="outline"
            size="sm"
            onClick={onClose}
            className="text-xs"
          >
            Tutup
          </Button>

          {onNewVersion && (
            <Button
              type="button"
              size="sm"
              onClick={() => {
                onClose();
                onNewVersion();
              }}
              className="bg-blue-600 hover:bg-blue-700 text-white text-xs gap-1.5"
            >
              <PlusCircle className="h-3.5 w-3.5" />
              <span>Unggah Revisi Baru</span>
            </Button>
          )}
        </div>
      </div>
    </div>
  );
}
