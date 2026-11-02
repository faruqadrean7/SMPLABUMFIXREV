import { apiClient } from '@/lib/api/api-client';
import { ApiResponse, PaginatedResponse } from '@/types/api';
import { PostItem, PostCategoryItem, SiteProfile } from '@/features/cms/types';
import { ppdbApi } from '@/features/ppdb/ppdb-api';
import { PPDBWaveItem } from '@/types/ppdb';
import {
  PublicEventItem,
  GetPublicEventsParams,
  PublicDownloadItem,
  GetPublicDownloadsParams,
} from './types';

export interface GetPublicPostsParams {
  page?: number;
  limit?: number;
  category_slug?: string;
  search?: string;
}

export interface PublicAlbumMediaItem {
  id: string;
  album_id: string;
  media_type: 'image' | 'video';
  caption?: string | null;
  file?: {
    id: string;
    url?: string;
    name?: string;
    mime_type?: string;
  };
}

export interface PublicAlbumItem {
  id: string;
  title: string;
  cover_file_id?: string | null;
  items_count?: number;
  cover?: {
    id: string;
    url?: string;
    name?: string;
  } | null;
  items?: PublicAlbumMediaItem[];
  created_at?: string;
  updated_at?: string;
}

export interface GetPublicAlbumsParams {
  page?: number;
  per_page?: number;
  search?: string;
}

export const publicApi = {
  /**
   * Mengambil daftar berita publik dengan pagination, filter kategori, dan pencarian (BE-CMS-02: GET /api/v1/public/posts)
   */
  getPublicPosts: async (params?: GetPublicPostsParams): Promise<PaginatedResponse<PostItem>> => {
    const res = await apiClient.get<PaginatedResponse<PostItem>>('/public/posts', {
      params: {
        page: params?.page ?? 1,
        limit: params?.limit ?? 6,
        category_slug: params?.category_slug || undefined,
        q: params?.search || undefined,
      },
    });
    return res.data;
  },

  /**
   * Mengambil detail berita publik berdasarkan slug (BE-CMS-02: GET /api/v1/public/posts/{slug})
   */
  getPublicPostBySlug: async (slug: string): Promise<PostItem> => {
    const res = await apiClient.get<ApiResponse<PostItem>>(`/public/posts/${slug}`);
    return res.data.data;
  },

  /**
   * Mengambil daftar kategori berita publik (BE-CMS-02: GET /api/v1/post-categories)
   */
  getCategories: async (): Promise<PostCategoryItem[]> => {
    const res = await apiClient.get<ApiResponse<PostCategoryItem[]>>('/post-categories');
    return res.data?.data || [];
  },

  /**
   * Mengambil daftar album galeri publik (BE-CMS-03: GET /api/v1/public/albums)
   */
  getPublicAlbums: async (params?: GetPublicAlbumsParams): Promise<PaginatedResponse<PublicAlbumItem>> => {
    const res = await apiClient.get<PaginatedResponse<PublicAlbumItem>>('/public/albums', {
      params: {
        page: params?.page ?? 1,
        per_page: params?.per_page ?? 9,
        search: params?.search || undefined,
      },
    });
    return res.data;
  },

  /**
   * Mengambil detail album galeri publik beserta item media (BE-CMS-03: GET /api/v1/public/albums/{id})
   */
  getPublicAlbumDetail: async (id: string): Promise<PublicAlbumItem> => {
    const res = await apiClient.get<ApiResponse<PublicAlbumItem>>(`/public/albums/${id}`);
    return res.data.data;
  },

  /**
   * Mengambil daftar berita publik terbaru untuk beranda (BE-CMS-02: GET /api/v1/public/posts)
   */
  getLatestPosts: async (limit: number = 3): Promise<PostItem[]> => {
    const res = await apiClient.get<PaginatedResponse<PostItem>>('/public/posts', {
      params: { limit, per_page: limit },
    });
    return res.data?.data || [];
  },

  /**
   * Mengambil status gelombang PPDB aktif (BE-PPDB-01: GET /api/v1/public/ppdb/waves/active)
   */
  getActiveWave: async (): Promise<PPDBWaveItem | null> => {
    return ppdbApi.getActiveWave();
  },

  /**
   * Mengambil profil situs dan pengaturan publik (BE-CMS-01: GET /api/v1/public/site)
   */
  getPublicSite: async (): Promise<SiteProfile> => {
    const res = await apiClient.get<ApiResponse<SiteProfile>>('/public/site');
    return res.data.data;
  },

  /**
   * BE-CMS-04: Mengambil daftar agenda kegiatan publik (kalender/acara)
   */
  getPublicEvents: async (params?: GetPublicEventsParams): Promise<PaginatedResponse<PublicEventItem>> => {
    const res = await apiClient.get<PaginatedResponse<PublicEventItem>>('/public/events', {
      params: {
        page: params?.page ?? 1,
        per_page: params?.per_page ?? 12,
        category: params?.category || undefined,
        search: params?.search || undefined,
        month: params?.month || undefined,
        year: params?.year || undefined,
        start_date: params?.start_date || undefined,
        end_date: params?.end_date || undefined,
        all: params?.all ? 'true' : undefined,
      },
    });
    return res.data;
  },

  /**
   * BE-CMS-04: Mengambil detail agenda kegiatan publik berdasarkan ID
   */
  getPublicEvent: async (id: string): Promise<PublicEventItem> => {
    const res = await apiClient.get<ApiResponse<PublicEventItem>>(`/public/events/${id}`);
    return res.data.data;
  },

  /**
   * BE-CMS-04: Mengambil agenda kegiatan publik terdekat untuk beranda
   */
  getLatestEvents: async (limit: number = 3): Promise<PublicEventItem[]> => {
    const res = await apiClient.get<PaginatedResponse<PublicEventItem>>('/public/events', {
      params: { per_page: limit, page: 1 },
    });
    return res.data?.data || [];
  },

  /**
   * BE-CMS-06: Mengambil daftar dokumen unduhan publik
   */
  getPublicDownloads: async (params?: GetPublicDownloadsParams): Promise<PaginatedResponse<PublicDownloadItem>> => {
    const res = await apiClient.get<PaginatedResponse<PublicDownloadItem>>('/public/downloads', {
      params: {
        page: params?.page ?? 1,
        per_page: params?.per_page ?? 10,
        category: params?.category || undefined,
        search: params?.search || undefined,
        all: params?.all ? 'true' : undefined,
      },
    });
    return res.data;
  },

  /**
   * BE-CMS-06: Mengambil detail dokumen unduhan publik berdasarkan ID
   */
  getPublicDownload: async (id: string): Promise<PublicDownloadItem> => {
    const res = await apiClient.get<ApiResponse<PublicDownloadItem>>(`/public/downloads/${id}`);
    return res.data.data;
  },
};
