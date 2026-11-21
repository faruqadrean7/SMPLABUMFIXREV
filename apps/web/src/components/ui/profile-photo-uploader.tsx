'use client';

import React, { useState, useRef, useEffect } from 'react';
import { Camera, X, CheckCircle2, AlertCircle, RefreshCw, Trash2, Upload } from 'lucide-react';
import { storageApi } from '@/features/storage/storage-api';
import { FileItem } from '@/features/storage/types';
import { Avatar } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';

export interface ProfilePhotoUploaderProps {
  label?: string;
  name?: string;
  currentPhotoUrl?: string | null;
  value?: string | null; // photo_file_id
  onChange: (fileId: string | null, fileItem?: FileItem | null) => void;
  disabled?: boolean;
  maxSizeMb?: number;
  helperText?: string;
  className?: string;
}

const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

/**
 * Komponen Unggah & Kelola Foto Profil Resmi:
 * - Mendukung pratinjau instan gambar (JPEG/PNG/WebP, maks 2MB).
 * - Menolak berkas berbahaya secara ketat di sisi klien sebelum presign.
 * - Terintegrasi dengan storageApi SISTLABUM (presign -> upload signed -> confirm).
 * - Mendukung pembatalan dan penghapusan referensi foto secara aman.
 */
export function ProfilePhotoUploader({
  label = 'Foto Profil',
  name,
  currentPhotoUrl,
  value,
  onChange,
  disabled = false,
  maxSizeMb = 2,
  helperText = 'Format: JPG, PNG, atau WebP (Maks. 2 MB)',
  className = '',
}: ProfilePhotoUploaderProps) {
  const [localPreviewUrl, setLocalPreviewUrl] = useState<string | null>(null);
  const [isUploading, setIsUploading] = useState(false);
  const [uploadProgress, setUploadProgress] = useState(0);
  const [error, setError] = useState<string | null>(null);
  const [uploadedFile, setUploadedFile] = useState<FileItem | null>(null);
  const [isCleared, setIsCleared] = useState(false);
  const fileInputRef = useRef<HTMLInputElement>(null);

  // Sync if value is reset by parent
  useEffect(() => {
    if (!value && !currentPhotoUrl) {
      setLocalPreviewUrl(null);
      setUploadedFile(null);
      setIsCleared(false);
    }
  }, [value, currentPhotoUrl]);

  // Clean up object URLs on unmount
  useEffect(() => {
    return () => {
      if (localPreviewUrl && localPreviewUrl.startsWith('blob:')) {
        URL.revokeObjectURL(localPreviewUrl);
      }
    };
  }, [localPreviewUrl]);

  const activePhotoUrl = !isCleared
    ? localPreviewUrl || (value ? uploadedFile?.object_key : currentPhotoUrl) || currentPhotoUrl
    : null;

  const handleFileSelect = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    setError(null);

    // 1. Validasi Tipe MIME
    if (!ALLOWED_MIME_TYPES.includes(file.type.toLowerCase())) {
      setError('Format berkas tidak didukung. Harap pilih gambar dengan format JPEG, PNG, atau WebP.');
      if (fileInputRef.current) fileInputRef.current.value = '';
      return;
    }

    // 2. Validasi Ukuran Berkas
    const maxBytes = maxSizeMb * 1024 * 1024;
    if (file.size > maxBytes) {
      const actualMb = (file.size / (1024 * 1024)).toFixed(1);
      setError(`Ukuran gambar (${actualMb} MB) melebihi batas maksimal ${maxSizeMb} MB.`);
      if (fileInputRef.current) fileInputRef.current.value = '';
      return;
    }

    // Buat pratinjau lokal sementara
    const objectUrl = URL.createObjectURL(file);
    setLocalPreviewUrl(objectUrl);
    setIsCleared(false);
    setIsUploading(true);
    setUploadProgress(0);

    try {
      // 3. Upload langsung via signed URL flow kanonis
      const result = await storageApi.uploadFileDirect({
        file,
        visibility: 'publik',
        purpose: 'avatar',
        onProgress: (percent) => setUploadProgress(percent),
      });

      setUploadedFile(result);
      onChange(result.id, result);
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Gagal mengunggah foto ke penyimpanan aman.';
      setError(msg);
      setLocalPreviewUrl(null);
      if (fileInputRef.current) fileInputRef.current.value = '';
    } finally {
      setIsUploading(false);
    }
  };

  const handleRemovePhoto = () => {
    if (localPreviewUrl && localPreviewUrl.startsWith('blob:')) {
      URL.revokeObjectURL(localPreviewUrl);
    }
    setLocalPreviewUrl(null);
    setUploadedFile(null);
    setIsCleared(true);
    setError(null);
    setUploadProgress(0);
    if (fileInputRef.current) fileInputRef.current.value = '';
    onChange(null, null);
  };

  return (
    <div className={`p-4 rounded-xl border border-slate-200 bg-slate-50/70 space-y-3 ${className}`}>
      <div className="flex items-center justify-between">
        <div>
          <span className="text-xs font-bold text-slate-800 block">{label}</span>
          {helperText && <span className="text-[11px] text-slate-500">{helperText}</span>}
        </div>
        {value && !isCleared && (
          <Badge variant="success" className="text-[10px] py-0 px-2 gap-1">
            <CheckCircle2 className="h-3 w-3" />
            <span>Tersimpan</span>
          </Badge>
        )}
      </div>

      <input
        ref={fileInputRef}
        type="file"
        accept="image/jpeg,image/png,image/webp"
        onChange={handleFileSelect}
        disabled={disabled || isUploading}
        className="hidden"
        aria-label="Pilih berkas foto profil"
      />

      <div className="flex items-center gap-4">
        {/* Avatar Display */}
        <div className="relative group shrink-0">
          <Avatar
            src={activePhotoUrl}
            name={name}
            size="xl"
            shape="rounded"
            alt={`Foto ${name || 'Profil'}`}
            className="border-2 border-white shadow-xs"
          />

          {isUploading && (
            <div className="absolute inset-0 bg-slate-900/60 rounded-xl flex items-center justify-center text-white">
              <RefreshCw className="h-5 w-5 animate-spin" />
            </div>
          )}
        </div>

        {/* Action Controls */}
        <div className="flex-1 min-w-0 space-y-2">
          <div className="flex flex-wrap items-center gap-2">
            <Button
              type="button"
              variant="outline"
              size="sm"
              disabled={disabled || isUploading}
              onClick={() => fileInputRef.current?.click()}
              className="h-8 text-xs font-semibold gap-1.5 bg-white border-slate-200 text-slate-700 hover:bg-slate-50"
            >
              <Camera className="h-3.5 w-3.5 text-brand-blue" />
              <span>{activePhotoUrl ? 'Ganti Foto' : 'Pilih Foto'}</span>
            </Button>

            {activePhotoUrl && !disabled && !isUploading && (
              <Button
                type="button"
                variant="ghost"
                size="sm"
                onClick={handleRemovePhoto}
                className="h-8 text-xs font-semibold gap-1.5 text-slate-500 hover:text-red-600 hover:bg-red-50"
                title="Hapus foto profil"
              >
                <Trash2 className="h-3.5 w-3.5" />
                <span>Hapus</span>
              </Button>
            )}
          </div>

          {/* Upload Progress Bar */}
          {isUploading && (
            <div className="space-y-1">
              <div className="flex justify-between text-[11px] text-slate-500 font-medium">
                <span>Mengunggah foto ke storage...</span>
                <span>{uploadProgress}%</span>
              </div>
              <div className="h-1.5 w-full bg-slate-200 rounded-full overflow-hidden">
                <div
                  className="h-full bg-brand-blue transition-all duration-300"
                  style={{ width: `${uploadProgress}%` }}
                />
              </div>
            </div>
          )}

          {/* Success Filename Note */}
          {uploadedFile && !error && !isUploading && (
            <p className="text-[11px] text-emerald-600 font-medium flex items-center gap-1">
              <CheckCircle2 className="h-3.5 w-3.5 shrink-0" />
              <span>Foto baru berhasil diunggah dan siap disimpan bersama data induk.</span>
            </p>
          )}
        </div>
      </div>

      {/* Error Message Alert */}
      {error && (
        <div className="flex items-center justify-between p-2.5 rounded-lg bg-red-50 border border-red-200 text-xs text-red-700 animate-in fade-in duration-200">
          <div className="flex items-center gap-2">
            <AlertCircle className="h-4 w-4 shrink-0 text-red-600" />
            <span>{error}</span>
          </div>
          <button
            type="button"
            onClick={() => setError(null)}
            className="text-red-500 hover:text-red-700 p-1"
            title="Tutup pesan kesalahan"
          >
            <X className="h-3.5 w-3.5" />
          </button>
        </div>
      )}
    </div>
  );
}
