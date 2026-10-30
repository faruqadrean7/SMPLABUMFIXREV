import { describe, it, expect, vi, beforeEach } from 'vitest';
import { cbtApi } from '@/features/cbt/cbt-api';
import { apiClient } from '@/lib/api/api-client';
import { useImageUpload } from '@/features/cbt/rich-editor/use-image-upload';
import { renderHook, act } from '@testing-library/react';

vi.mock('@/lib/api/api-client', () => ({
  apiClient: {
    post: vi.fn(),
  },
}));

describe('FE-CBT-11: useImageUpload & cbtApi.uploadQuestionMedia', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('fe-cbt-11 uploadQuestionMedia posts multipart & returns camelCase ids', async () => {
    vi.mocked(apiClient.post).mockResolvedValueOnce({
      data: {
        success: true,
        data: {
          file_id: 'file-uuid-123',
          mime_type: 'image/png',
          preview_url: 'https://school.sch.id/files/download/signed-123',
        },
      },
    });

    const file = new File(['dummy-content'], 'cell.png', { type: 'image/png' });
    const result = await cbtApi.uploadQuestionMedia('bank-uuid-456', file);

    expect(apiClient.post).toHaveBeenCalledTimes(1);
    const [url, formData] = vi.mocked(apiClient.post).mock.calls[0];
    expect(url).toBe('/cbt/question-media');
    expect(formData).toBeInstanceOf(FormData);

    expect(result).toEqual({
      fileId: 'file-uuid-123',
      mimeType: 'image/png',
      previewUrl: 'https://school.sch.id/files/download/signed-123',
    });
  });

  it('fe-cbt-11 useImageUpload rejects non-image client-side without calling API', async () => {
    const { result } = renderHook(() => useImageUpload('bank-uuid-456'));
    const pdfFile = new File(['%PDF-1.4'], 'tugas.pdf', { type: 'application/pdf' });

    let thrownError: Error | null = null;
    await act(async () => {
      try {
        await result.current.uploadAndGetNode(pdfFile);
      } catch (e) {
        thrownError = e as Error;
      }
    });

    expect(thrownError?.message).toBe('Hanya berkas gambar (JPEG, PNG, WebP, GIF) yang diperbolehkan.');
    expect(apiClient.post).not.toHaveBeenCalled();
    expect(result.current.error).toBe('Hanya berkas gambar (JPEG, PNG, WebP, GIF) yang diperbolehkan.');
  });

  it('fe-cbt-11 useImageUpload rejects file exceeding 2MB client-side', async () => {
    const { result } = renderHook(() => useImageUpload('bank-uuid-456'));
    // Buat file 3 MB
    const largeFile = new File([new Uint8Array(3 * 1024 * 1024)], 'large.jpg', { type: 'image/jpeg' });

    let thrownError: Error | null = null;
    await act(async () => {
      try {
        await result.current.uploadAndGetNode(largeFile);
      } catch (e) {
        thrownError = e as Error;
      }
    });

    expect(thrownError?.message).toBe('Ukuran gambar tidak boleh melebihi 2 MB.');
    expect(apiClient.post).not.toHaveBeenCalled();
    expect(result.current.error).toBe('Ukuran gambar tidak boleh melebihi 2 MB.');
  });

  it('fe-cbt-11 useImageUpload returns node with previewUrl and fileId', async () => {
    vi.mocked(apiClient.post).mockResolvedValueOnce({
      data: {
        success: true,
        data: {
          file_id: 'uuid-sel',
          mime_type: 'image/png',
          preview_url: 'https://api.sch.id/download/preview',
        },
      },
    });

    const { result } = renderHook(() => useImageUpload('bank-123'));
    const file = new File(['img-bytes'], 'mitokondria.png', { type: 'image/png' });

    let nodeResult;
    await act(async () => {
      nodeResult = await result.current.uploadAndGetNode(file);
    });

    expect(nodeResult).toEqual({
      src: 'https://api.sch.id/download/preview',
      fileId: 'uuid-sel',
      alt: 'mitokondria',
    });
    expect(result.current.isUploading).toBe(false);
    expect(result.current.error).toBeNull();
  });
});
