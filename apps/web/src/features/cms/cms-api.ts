import { apiClient } from '@/lib/api/api-client';
import { ApiResponse, PaginatedResponse } from '@/types/api';
import {
  SiteProfile,
  SiteSettingsPayload,
  PostCategoryItem,
  PostItem,
  CreatePostPayload,
  UpdatePostPayload,
  SchoolEventItem,
  CreateEventPayload,
  UpdateEventPayload,
  DownloadItem,
  CreateDownloadPayload,
  UpdateDownloadPayload,
  AlbumItem,
  CreateAlbumPayload,
  UpdateAlbumPayload,
  MediaItem,
  CreateMediaItemPayload,
  PageItem,
  CreatePagePayload,
  UpdatePagePayload,
  BulletinItem,
  CreateBulletinPayload,
  UpdateBulletinPayload,
} from './types';

export const cmsApi = {
  // =========================================================================
  // 1. PROFIL SITUS & PENGATURAN (BE-CMS-01 / ADM-CMS-01)
  // =========================================================================

  getSiteSettings: async (): Promise<ApiResponse<SiteProfile>> => {
    const res = await apiClient.get<ApiResponse<SiteProfile>>('/site-settings');
    return res.data;
  },

  updateSiteSettings: async (payload: SiteSettingsPayload): Promise<ApiResponse<SiteProfile>> => {
    const res = await apiClient.put<ApiResponse<SiteProfile>>('/site-settings', payload);
    return res.data;
  },

  getPublicSite: async (): Promise<ApiResponse<SiteProfile>> => {
    const res = await apiClient.get<ApiResponse<SiteProfile>>('/public/site');
    return res.data;
  },

  // =========================================================================
  // 2. BERITA & ARTIKEL (BE-CMS-02 / ADM-CMS-02)
  // =========================================================================

  getPosts: async (params?: {
    category_id?: string;
    status?: 'draft' | 'terbit' | 'arsip' | string;
    visibility?: 'publik' | 'internal' | string;
    q?: string;
    page?: number;
    per_page?: number;
  }): Promise<PaginatedResponse<PostItem>> => {
    const res = await apiClient.get<PaginatedResponse<PostItem>>('/posts', { params });
    return res.data;
  },

  getPost: async (id: string): Promise<ApiResponse<PostItem>> => {
    const res = await apiClient.get<ApiResponse<PostItem>>(`/posts/${id}`);
    return res.data;
  },

  createPost: async (payload: CreatePostPayload): Promise<ApiResponse<PostItem>> => {
    const res = await apiClient.post<ApiResponse<PostItem>>('/posts', payload);
    return res.data;
  },

  updatePost: async (id: string, payload: UpdatePostPayload): Promise<ApiResponse<PostItem>> => {
    const res = await apiClient.put<ApiResponse<PostItem>>(`/posts/${id}`, payload);
    return res.data;
  },

  deletePost: async (id: string): Promise<ApiResponse<{ message: string }>> => {
    const res = await apiClient.delete<ApiResponse<{ message: string }>>(`/posts/${id}`);
    return res.data;
  },

  // Kategori Berita
  getPostCategories: async (): Promise<ApiResponse<PostCategoryItem[]>> => {
    const res = await apiClient.get<ApiResponse<PostCategoryItem[]>>('/post-categories');
    return res.data;
  },

  createPostCategory: async (payload: { name: string; slug?: string }): Promise<ApiResponse<PostCategoryItem>> => {
    const res = await apiClient.post<ApiResponse<PostCategoryItem>>('/post-categories', payload);
    return res.data;
  },

  deletePostCategory: async (id: string): Promise<ApiResponse<{ message: string }>> => {
    const res = await apiClient.delete<ApiResponse<{ message: string }>>(`/post-categories/${id}`);
    return res.data;
  },

  // =========================================================================
  // 3. AGENDA & KALENDER AKADEMIK (BE-CMS-03 / ADM-CMS-03)
  // =========================================================================

  getEvents: async (params?: {
    category?: 'ujian' | 'libur' | 'event' | string;
    visibility?: 'publik' | 'internal' | string;
    month?: number;
    year?: number;
    page?: number;
  }): Promise<PaginatedResponse<SchoolEventItem>> => {
    const res = await apiClient.get<PaginatedResponse<SchoolEventItem>>('/events', { params });
    return res.data;
  },

  getEvent: async (id: string): Promise<ApiResponse<SchoolEventItem>> => {
    const res = await apiClient.get<ApiResponse<SchoolEventItem>>(`/events/${id}`);
    return res.data;
  },

  createEvent: async (payload: CreateEventPayload): Promise<ApiResponse<SchoolEventItem>> => {
    const res = await apiClient.post<ApiResponse<SchoolEventItem>>('/events', payload);
    return res.data;
  },

  updateEvent: async (id: string, payload: UpdateEventPayload): Promise<ApiResponse<SchoolEventItem>> => {
    const res = await apiClient.put<ApiResponse<SchoolEventItem>>(`/events/${id}`, payload);
    return res.data;
  },

  deleteEvent: async (id: string): Promise<ApiResponse<{ message: string }>> => {
    const res = await apiClient.delete<ApiResponse<{ message: string }>>(`/events/${id}`);
    return res.data;
  },

  // =========================================================================
  // 4. PUSAT UNDUHAN DOKUMEN (BE-CMS-04 / ADM-CMS-04)
  // =========================================================================

  getDownloads: async (params?: {
    category?: string;
    visibility?: 'publik' | 'internal' | string;
    q?: string;
    page?: number;
  }): Promise<PaginatedResponse<DownloadItem>> => {
    const res = await apiClient.get<PaginatedResponse<DownloadItem>>('/downloads', { params });
    return res.data;
  },

  getDownload: async (id: string): Promise<ApiResponse<DownloadItem>> => {
    const res = await apiClient.get<ApiResponse<DownloadItem>>(`/downloads/${id}`);
    return res.data;
  },

  createDownload: async (payload: CreateDownloadPayload): Promise<ApiResponse<DownloadItem>> => {
    const res = await apiClient.post<ApiResponse<DownloadItem>>('/downloads', payload);
    return res.data;
  },

  updateDownload: async (id: string, payload: UpdateDownloadPayload): Promise<ApiResponse<DownloadItem>> => {
    const res = await apiClient.put<ApiResponse<DownloadItem>>(`/downloads/${id}`, payload);
    return res.data;
  },

  deleteDownload: async (id: string): Promise<ApiResponse<{ message: string }>> => {
    const res = await apiClient.delete<ApiResponse<{ message: string }>>(`/downloads/${id}`);
    return res.data;
  },

  // =========================================================================
  // 5. GALERI FOTO & MEDIA (BE-CMS-05 / ADM-CMS-05)
  // =========================================================================

  getAlbums: async (params?: { q?: string; page?: number }): Promise<PaginatedResponse<AlbumItem>> => {
    const res = await apiClient.get<PaginatedResponse<AlbumItem>>('/albums', { params });
    return res.data;
  },

  getAlbum: async (id: string): Promise<ApiResponse<AlbumItem>> => {
    const res = await apiClient.get<ApiResponse<AlbumItem>>(`/albums/${id}`);
    return res.data;
  },

  createAlbum: async (payload: CreateAlbumPayload): Promise<ApiResponse<AlbumItem>> => {
    const res = await apiClient.post<ApiResponse<AlbumItem>>('/albums', payload);
    return res.data;
  },

  updateAlbum: async (id: string, payload: UpdateAlbumPayload): Promise<ApiResponse<AlbumItem>> => {
    const res = await apiClient.put<ApiResponse<AlbumItem>>(`/albums/${id}`, payload);
    return res.data;
  },

  deleteAlbum: async (id: string): Promise<ApiResponse<{ message: string }>> => {
    const res = await apiClient.delete<ApiResponse<{ message: string }>>(`/albums/${id}`);
    return res.data;
  },

  createMediaItem: async (payload: CreateMediaItemPayload): Promise<ApiResponse<MediaItem>> => {
    const res = await apiClient.post<ApiResponse<MediaItem>>('/media', payload);
    return res.data;
  },

  deleteMediaItem: async (id: string): Promise<ApiResponse<{ message: string }>> => {
    const res = await apiClient.delete<ApiResponse<{ message: string }>>(`/media/${id}`);
    return res.data;
  },

  // =========================================================================
  // 6. HALAMAN STATIS (BE-CMS-06 / ADM-CMS-06)
  // =========================================================================

  getPages: async (): Promise<PaginatedResponse<PageItem>> => {
    const res = await apiClient.get<PaginatedResponse<PageItem>>('/pages');
    return res.data;
  },

  getPage: async (id: string): Promise<ApiResponse<PageItem>> => {
    const res = await apiClient.get<ApiResponse<PageItem>>(`/pages/${id}`);
    return res.data;
  },

  createPage: async (payload: CreatePagePayload): Promise<ApiResponse<PageItem>> => {
    const res = await apiClient.post<ApiResponse<PageItem>>('/pages', payload);
    return res.data;
  },

  updatePage: async (id: string, payload: UpdatePagePayload): Promise<ApiResponse<PageItem>> => {
    const res = await apiClient.put<ApiResponse<PageItem>>(`/pages/${id}`, payload);
    return res.data;
  },

  deletePage: async (id: string): Promise<ApiResponse<{ message: string }>> => {
    const res = await apiClient.delete<ApiResponse<{ message: string }>>(`/pages/${id}`);
    return res.data;
  },

  // =========================================================================
  // 7. BULETIN & MAJALAH SEKOLAH (BE-CMS-03 / FE-CMS-PUBLIC-04a)
  // =========================================================================

  getBulletins: async (params?: {
    search?: string;
    q?: string;
    page?: number;
    per_page?: number;
  }): Promise<PaginatedResponse<BulletinItem>> => {
    const res = await apiClient.get<PaginatedResponse<BulletinItem>>('/bulletins', { params });
    return res.data;
  },

  getBulletin: async (id: string): Promise<ApiResponse<BulletinItem>> => {
    const res = await apiClient.get<ApiResponse<BulletinItem>>(`/bulletins/${id}`);
    return res.data;
  },

  createBulletin: async (payload: CreateBulletinPayload): Promise<ApiResponse<BulletinItem>> => {
    const res = await apiClient.post<ApiResponse<BulletinItem>>('/bulletins', payload);
    return res.data;
  },

  updateBulletin: async (id: string, payload: UpdateBulletinPayload): Promise<ApiResponse<BulletinItem>> => {
    const res = await apiClient.put<ApiResponse<BulletinItem>>(`/bulletins/${id}`, payload);
    return res.data;
  },

  deleteBulletin: async (id: string): Promise<ApiResponse<{ message: string }>> => {
    const res = await apiClient.delete<ApiResponse<{ message: string }>>(`/bulletins/${id}`);
    return res.data;
  },
};
