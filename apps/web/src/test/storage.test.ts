import { describe, it, expect, vi, beforeEach } from 'vitest';
import axios from 'axios';
import { storageApi } from '@/features/storage/storage-api';
import { apiClient } from '@/lib/api/api-client';
import { PresignResponse, FileItem } from '@/features/storage/types';

describe('Secure File Storage Module (BE-FILE-01 / ADM-CMS)', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  // =========================================================================
  // 1. PRESIGN UPLOAD SESSION
  // =========================================================================
  describe('1. Presign Upload Session', () => {
    it('meminta sesi upload bertanda tangan (presign) dengan metadata berkas yang valid', async () => {
      const mockPresign: PresignResponse = {
        token: 'token-uuid-123',
        upload_url: 'http://localhost:8000/api/v1/files/upload-signed/token-uuid-123',
        method: 'POST',
        bucket: 'public',
        object_key: 'public/2026/10/random-uuid.pdf',
        visibility: 'publik',
        mime_type: 'application/pdf',
        size_bytes: 102400,
        expires_at: '2026-10-14T12:15:00Z',
        ttl_seconds: 900,
      };

      vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: { data: mockPresign },
      } as any);

      const res = await storageApi.presign({
        filename: 'silabus_kurikulum.pdf',
        mime_type: 'application/pdf',
        size_bytes: 102400,
        visibility: 'publik',
        purpose: 'cms_download',
      });

      expect(apiClient.post).toHaveBeenCalledWith('/files/presign', {
        filename: 'silabus_kurikulum.pdf',
        mime_type: 'application/pdf',
        size_bytes: 102400,
        visibility: 'publik',
        purpose: 'cms_download',
      });
      expect(res.data?.token).toBe('token-uuid-123');
      expect(res.data?.upload_url).toContain('/files/upload-signed/');
    });
  });

  // =========================================================================
  // 2. UPLOAD SIGNED TARGET
  // =========================================================================
  describe('2. Upload Signed Target', () => {
    it('mengunggah berkas multipart ke URL bertanda tangan', async () => {
      const mockFile = new File(['konten dummy berkas'], 'dokumen.pdf', {
        type: 'application/pdf',
      });

      const mockResponse = {
        data: {
          data: {
            message: 'Berkas berhasil diunggah ke storage.',
            token: 'token-uuid-123',
            object_key: 'public/2026/10/random-uuid.pdf',
            checksum: 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
            size_bytes: 1024,
          },
        },
      };

      vi.spyOn(axios, 'post').mockResolvedValueOnce(mockResponse as any);

      let progressRecorded = 0;
      const res = await storageApi.uploadSigned(
        'http://localhost:8000/api/v1/files/upload-signed/token-uuid-123',
        mockFile,
        (percent) => {
          progressRecorded = percent;
        }
      );

      expect(axios.post).toHaveBeenCalledWith(
        'http://localhost:8000/api/v1/files/upload-signed/token-uuid-123',
        expect.any(FormData),
        expect.objectContaining({
          headers: { 'Content-Type': 'multipart/form-data' },
        })
      );
      expect(res.checksum).toBe('e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855');
    });
  });

  // =========================================================================
  // 3. CONFIRM UPLOAD
  // =========================================================================
  describe('3. Konfirmasi Upload & Pendaftaran File ke Database', () => {
    it('mengonfirmasi upload berkas fisik dan mendapatkan File model lengkap', async () => {
      const mockFileItem: FileItem = {
        id: 'file-reg-1',
        school_id: 'school-123',
        owner_id: 'user-admin-1',
        owner_type: 'User',
        bucket: 'public',
        object_key: 'public/2026/10/random-uuid.pdf',
        mime_type: 'application/pdf',
        size_bytes: 102400,
        visibility: 'publik',
        checksum: 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
        created_at: '2026-10-14T12:00:00Z',
      };

      vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: { data: mockFileItem },
      } as any);

      const res = await storageApi.confirm({
        token: 'token-uuid-123',
        checksum: 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
      });

      expect(apiClient.post).toHaveBeenCalledWith('/files/confirm', {
        token: 'token-uuid-123',
        checksum: 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
      });
      expect(res.data?.id).toBe('file-reg-1');
      expect(res.data?.visibility).toBe('publik');
    });
  });

  // =========================================================================
  // 4. RETRIEVE FILE URL
  // =========================================================================
  describe('4. Mengambil URL Unduhan / Preview Berkas', () => {
    it('mengambil URL bertanda tangan untuk berkas dengan TTL tertentu', async () => {
      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: {
          data: {
            id: 'file-reg-1',
            url: 'http://localhost:8000/api/v1/files/file-reg-1/download?signature=xxx',
            visibility: 'privat',
            mime_type: 'application/pdf',
            size_bytes: 102400,
            expires_at: '2026-10-14T12:05:00Z',
            ttl_seconds: 300,
          },
        },
      } as any);

      const res = await storageApi.getFileUrl('file-reg-1', 300);

      expect(apiClient.get).toHaveBeenCalledWith('/files/file-reg-1/url', {
        params: { ttl: 300 },
      });
      expect(res.data?.visibility).toBe('privat');
      expect(res.data?.url).toContain('signature=xxx');
    });
  });

  // =========================================================================
  // 5. ORCHESTRATOR: COMPLETE END-TO-END UPLOAD
  // =========================================================================
  describe('5. Upload File Direct Orchestrator', () => {
    it('menjalankan alur lengkap presign -> uploadSigned -> confirm', async () => {
      const mockFile = new File(['konten dummy'], 'foto_sampul.png', {
        type: 'image/png',
      });

      // 1. Mock Presign
      vi.spyOn(storageApi, 'presign').mockResolvedValueOnce({
        data: {
          token: 'token-direct-1',
          upload_url: 'http://localhost:8000/api/v1/files/upload-signed/token-direct-1',
          method: 'POST',
          bucket: 'public',
          object_key: 'public/2026/10/cover.png',
          visibility: 'publik',
          mime_type: 'image/png',
          size_bytes: mockFile.size,
          expires_at: '2026-10-14T12:15:00Z',
          ttl_seconds: 900,
        },
      } as any);

      // 2. Mock UploadSigned
      vi.spyOn(storageApi, 'uploadSigned').mockResolvedValueOnce({
        message: 'Upload sukses',
        token: 'token-direct-1',
        object_key: 'public/2026/10/cover.png',
        checksum: 'hash123',
        size_bytes: mockFile.size,
      });

      // 3. Mock Confirm
      vi.spyOn(storageApi, 'confirm').mockResolvedValueOnce({
        data: {
          id: 'file-direct-id',
          bucket: 'public',
          object_key: 'public/2026/10/cover.png',
          mime_type: 'image/png',
          size_bytes: mockFile.size,
          visibility: 'publik',
          created_at: '2026-10-14T12:00:00Z',
        },
      } as any);

      const result = await storageApi.uploadFileDirect({
        file: mockFile,
        visibility: 'publik',
        purpose: 'cms_cover',
      });

      expect(storageApi.presign).toHaveBeenCalledWith({
        filename: 'foto_sampul.png',
        mime_type: 'image/png',
        size_bytes: mockFile.size,
        visibility: 'publik',
        purpose: 'cms_cover',
      });

      expect(storageApi.uploadSigned).toHaveBeenCalledWith(
        'http://localhost:8000/api/v1/files/upload-signed/token-direct-1',
        mockFile,
        undefined
      );

      expect(storageApi.confirm).toHaveBeenCalledWith({
        token: 'token-direct-1',
        checksum: 'hash123',
      });

      expect(result.id).toBe('file-direct-id');
    });

    it('melempar exception jika presign gagal atau URL tidak dikembalikan', async () => {
      const mockFile = new File(['konten'], 'dok.pdf', { type: 'application/pdf' });

      vi.spyOn(storageApi, 'presign').mockResolvedValueOnce({
        data: null,
      } as any);

      await expect(
        storageApi.uploadFileDirect({ file: mockFile })
      ).rejects.toThrow('Gagal mendapatkan sesi upload bertanda tangan');
    });
  });
});
