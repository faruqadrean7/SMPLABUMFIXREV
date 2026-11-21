'use client';

import React, { useState, useRef } from 'react';
import { Upload, X, CheckCircle2, AlertCircle, RefreshCw, FileText } from 'lucide-react';
import { storageApi } from '@/features/storage/storage-api';
import { FileItem } from '@/features/storage/types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

interface FileUploadProps {
  label: string;
  accept?: string;
  maxSizeMb?: number;
  visibility?: 'publik' | 'privat';
  purpose?: string;
  value?: string | null;
  onChange: (fileId: string | null, fileItem?: FileItem | null) => void;
  disabled?: boolean;
  helperText?: string;
  previewUrl?: string | null;
  onUploadingChange?: (isUploading: boolean) => void;
}

export function FileUpload({
  label,
  accept,
  maxSizeMb = 10,
  visibility = 'publik',
  purpose = 'cms',
  value,
  onChange,
  disabled = false,
  helperText,
  previewUrl,
  onUploadingChange,
}: FileUploadProps) {
  const [isUploading, setIsUploading] = useState(false);
  const [uploadProgress, setUploadProgress] = useState(0);
  const [error, setError] = useState<string | null>(null);
  const [uploadedFile, setUploadedFile] = useState<FileItem | null>(null);
  const [fileName, setFileName] = useState<string | null>(null);
  const [localPreviewUrl, setLocalPreviewUrl] = useState<string | null>(null);
  const inputRef = useRef<HTMLInputElement>(null);

  const handleFileSelect = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    setError(null);

    // Validate size
    const maxBytes = maxSizeMb * 1024 * 1024;
    if (file.size > maxBytes) {
      setError(`Ukuran berkas (${(file.size / (1024 * 1024)).toFixed(1)} MB) melebihi batas maksimal ${maxSizeMb} MB.`);
      if (inputRef.current) inputRef.current.value = '';
      return;
    }

    if (file.type.startsWith('image/')) {
      try {
        setLocalPreviewUrl(URL.createObjectURL(file));
      } catch {
        setLocalPreviewUrl(null);
      }
    } else {
      setLocalPreviewUrl(null);
    }

    setFileName(file.name);
    setIsUploading(true);
    onUploadingChange?.(true);
    setUploadProgress(0);

    try {
      const result = await storageApi.uploadFileDirect({
        file,
        visibility,
        purpose,
        onProgress: (percent) => setUploadProgress(percent),
      });

      setUploadedFile(result);
      onChange(result.id, result);
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Gagal mengunggah berkas ke server.';
      setError(msg);
      setLocalPreviewUrl(null);
      onChange(null, null);
    } finally {
      setIsUploading(false);
      onUploadingChange?.(false);
    }
  };

  const handleClear = () => {
    if (localPreviewUrl) {
      try {
        URL.revokeObjectURL(localPreviewUrl);
      } catch {}
    }
    setLocalPreviewUrl(null);
    setUploadedFile(null);
    setFileName(null);
    setError(null);
    setUploadProgress(0);
    if (inputRef.current) inputRef.current.value = '';
    onChange(null, null);
  };

  return (
    <div className="space-y-1.5">
      <div className="flex items-center justify-between">
        <label className="text-xs font-semibold text-slate-700 block">{label}</label>
        {helperText && <span className="text-[11px] text-slate-500">{helperText}</span>}
      </div>

      <input
        ref={inputRef}
        type="file"
        accept={accept}
        onChange={handleFileSelect}
        disabled={disabled || isUploading}
        className="hidden"
      />

      {/* State: Already Uploaded or has Value */}
      {(uploadedFile || value) && !error ? (
        <div className="flex items-center justify-between p-3 rounded-sm border border-green-200 bg-green-50/50 text-xs">
          <div className="flex items-center gap-3 min-w-0">
            {localPreviewUrl || previewUrl ? (
              /* eslint-disable-next-line @next/next/no-img-element */
              <img
                src={localPreviewUrl || previewUrl || ''}
                alt="Pratinjau berkas"
                className="w-12 h-12 rounded-sm object-cover border border-slate-200 shrink-0 bg-white"
                onError={(e) => {
                  e.currentTarget.style.display = 'none';
                }}
              />
            ) : (
              <CheckCircle2 className="h-4 w-4 text-green-600 shrink-0" />
            )}
            <div className="min-w-0">
              <span className="font-semibold text-slate-900 truncate block">
                {fileName || uploadedFile?.object_key
                  ? fileName || uploadedFile?.object_key
                  : previewUrl
                  ? 'Berkas Sampul Terpasang'
                  : `Berkas Terpasang: ${value}`}
              </span>
              <span className="text-[10px] text-slate-500">
                {uploadedFile?.size_bytes ? `${Math.round(uploadedFile.size_bytes / 1024)} KB • ` : ''}
                Tersimpan di storage aman
              </span>
            </div>
          </div>
          <div className="flex items-center gap-1.5 shrink-0">
            <Badge variant="success" className="text-[10px] py-0 px-1.5">
              Siap
            </Badge>
            {!disabled && (
              <button
                type="button"
                onClick={handleClear}
                className="p-1 rounded text-slate-400 hover:text-red-600 hover:bg-red-50 transition-colors"
                title="Hapus berkas terpilih"
              >
                <X className="h-4 w-4" />
              </button>
            )}
          </div>
        </div>
      ) : isUploading ? (
        /* State: Uploading with Progress */
        <div className="p-4 rounded-sm border border-primary/20 bg-primary/5 space-y-2 text-xs">
          <div className="flex items-center justify-between text-slate-700 font-medium">
            <span className="flex items-center gap-1.5 truncate">
              <RefreshCw className="h-3.5 w-3.5 animate-spin text-primary" />
              Mengunggah {fileName}...
            </span>
            <span>{uploadProgress}%</span>
          </div>
          <div className="h-1.5 w-full bg-slate-200 rounded-full overflow-hidden">
            <div
              className="h-full bg-primary transition-all duration-300"
              style={{ width: `${uploadProgress}%` }}
            />
          </div>
        </div>
      ) : (
        /* State: Idle / Dropzone */
        <div
          onClick={() => !disabled && inputRef.current?.click()}
          className={`flex flex-col items-center justify-center p-4 border-2 border-dashed rounded-sm text-center cursor-pointer transition-colors ${
            disabled
              ? 'opacity-60 cursor-not-allowed bg-slate-50 border-slate-200'
              : 'border-slate-300 hover:border-primary hover:bg-slate-50/50'
          }`}
        >
          <Upload className="h-5 w-5 text-slate-400 mb-1" />
          <p className="text-xs font-semibold text-slate-700">
            Klik untuk memilih berkas atau seret berkas ke sini
          </p>
          <p className="text-[10px] text-slate-500 mt-0.5">
            {accept ? `Format: ${accept.replace(/\*/g, '')} • ` : ''}Maksimal {maxSizeMb} MB
          </p>
        </div>
      )}

      {/* Error state */}
      {error && (
        <div className="flex items-center justify-between p-2 rounded-sm bg-red-50 border border-red-200 text-xs text-red-700">
          <div className="flex items-center gap-1.5">
            <AlertCircle className="h-3.5 w-3.5 shrink-0" />
            <span>{error}</span>
          </div>
          <Button
            type="button"
            variant="outline"
            size="sm"
            onClick={() => inputRef.current?.click()}
            className="h-6 text-[10px] px-2 bg-white border-red-200 text-red-700 hover:bg-red-50"
          >
            Pilih Ulang
          </Button>
        </div>
      )}
    </div>
  );
}
