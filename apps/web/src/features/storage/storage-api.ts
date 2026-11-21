import axios from 'axios';
import { apiClient } from '@/lib/api/api-client';
import { ApiResponse } from '@/types/api';
import {
  PresignPayload,
  PresignResponse,
  UploadSignedResponse,
  ConfirmPayload,
  FileItem,
  FileUrlResponse,
} from './types';

export const storageApi = {
  // =========================================================================
  // 1. PRESIGN UPLOAD SESSION (BE-FILE-01)
  // =========================================================================

  presign: async (payload: PresignPayload): Promise<ApiResponse<PresignResponse>> => {
    const res = await apiClient.post<ApiResponse<PresignResponse>>('/files/presign', payload);
    return res.data;
  },

  // =========================================================================
  // 2. UPLOAD TO SIGNED URL TARGET
  // =========================================================================

  uploadSigned: async (
    uploadUrl: string,
    file: File | Blob,
    onProgress?: (percent: number) => void
  ): Promise<UploadSignedResponse> => {
    const formData = new FormData();
    formData.append('file', file);

    const res = await axios.post<ApiResponse<UploadSignedResponse>>(uploadUrl, formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
      onUploadProgress: (progressEvent) => {
        if (progressEvent.total && onProgress) {
          const percent = Math.round((progressEvent.loaded * 100) / progressEvent.total);
          onProgress(percent);
        }
      },
    });

    return res.data.data;
  },

  // =========================================================================
  // 3. CONFIRM UPLOAD & REGISTER FILE
  // =========================================================================

  confirm: async (payload: ConfirmPayload): Promise<ApiResponse<FileItem>> => {
    const res = await apiClient.post<ApiResponse<FileItem>>('/files/confirm', payload);
    return res.data;
  },

  // =========================================================================
  // 4. RETRIEVE FILE URL (SIGNED FOR PRIVATE, DIRECT FOR PUBLIC)
  // =========================================================================

  getFileUrl: async (id: string, ttl: number = 300): Promise<ApiResponse<FileUrlResponse>> => {
    const res = await apiClient.get<ApiResponse<FileUrlResponse>>(`/files/${id}/url`, {
      params: { ttl },
    });
    return res.data;
  },

  // =========================================================================
  // 5. CONVENIENCE ORCHESTRATOR: COMPLETE END-TO-END UPLOAD
  // =========================================================================

  uploadFileDirect: async (options: {
    file: File;
    visibility?: 'publik' | 'privat';
    purpose?: string;
    onProgress?: (percent: number) => void;
  }): Promise<FileItem> => {
    const { file, visibility = 'publik', purpose = 'general', onProgress } = options;

    // 1. Presign
    const presignRes = await storageApi.presign({
      filename: file.name,
      mime_type: file.type || 'application/octet-stream',
      size_bytes: file.size,
      visibility,
      purpose,
    });

    const presignData = presignRes.data;
    if (!presignData || !presignData.upload_url) {
      throw new Error('Gagal mendapatkan sesi upload bertanda tangan dari server.');
    }

    // 2. Upload to signed URL
    const uploadRes = await storageApi.uploadSigned(
      presignData.upload_url,
      file,
      onProgress
    );

    // 3. Confirm
    const confirmRes = await storageApi.confirm({
      token: presignData.token,
      checksum: uploadRes?.checksum,
    });

    if (!confirmRes.data) {
      throw new Error('Gagal mengonfirmasi keberhasilan penyimpanan berkas.');
    }

    return confirmRes.data;
  },
};
