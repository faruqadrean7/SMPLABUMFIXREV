import { useState, useCallback } from 'react';
import { cbtApi } from '@/features/cbt/cbt-api';

const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
const MAX_FILE_SIZE = 2 * 1024 * 1024; // 2 MB

export interface UploadedImageNode {
  src: string;
  fileId: string;
  alt: string;
}

export function useImageUpload(bankId: string) {
  const [isUploading, setIsUploading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const uploadAndGetNode = useCallback(
    async (file: File): Promise<UploadedImageNode> => {
      setError(null);

      // 1. Validasi tipe MIME sisi klien
      if (!ALLOWED_MIME_TYPES.includes(file.type)) {
        const err = 'Hanya berkas gambar (JPEG, PNG, WebP, GIF) yang diperbolehkan.';
        setError(err);
        throw new Error(err);
      }

      // 2. Validasi batas ukuran fisik berkas (2 MB)
      if (file.size > MAX_FILE_SIZE) {
        const err = 'Ukuran gambar tidak boleh melebihi 2 MB.';
        setError(err);
        throw new Error(err);
      }

      if (!bankId) {
        const err = 'Bank soal belum dipilih.';
        setError(err);
        throw new Error(err);
      }

      setIsUploading(true);
      try {
        const res = await cbtApi.uploadQuestionMedia(bankId, file);
        return {
          src: res.previewUrl,
          fileId: res.fileId,
          alt: file.name.replace(/\.[^/.]+$/, ''),
        };
      } catch (err: unknown) {
        const axiosErr = err as { response?: { data?: { message?: string } }; message?: string };
        const message =
          axiosErr?.response?.data?.message ||
          axiosErr?.message ||
          'Gagal mengunggah gambar ke server.';
        setError(message);
        throw new Error(message);
      } finally {
        setIsUploading(false);
      }
    },
    [bankId]
  );

  return {
    uploadAndGetNode,
    isUploading,
    error,
    clearError: () => setError(null),
  };
}
