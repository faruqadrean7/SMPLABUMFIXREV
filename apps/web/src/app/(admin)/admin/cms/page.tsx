'use client';

import React, { useEffect, useState, useCallback, useMemo } from 'react';
import {
  Globe,
  FileText,
  Calendar,
  Download,
  Image as ImageIcon,
  Plus,
  RefreshCw,
  Search,
  Filter,
  CheckCircle2,
  AlertCircle,
  AlertTriangle,
  Trash2,
  Save,
  X,
  ExternalLink,
  School,
  Building,
  Phone,
  Mail,
  MapPin,
  Award,
  Layers,
  Images,
  FolderOpen,
  UploadCloud,
  Pencil,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { FileUpload } from '@/components/ui/file-upload';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { cmsApi } from '@/features/cms/cms-api';
import {
  SiteProfile,
  SiteSettingsPayload,
  PostItem,
  PostCategoryItem,
  CreatePostPayload,
  SchoolEventItem,
  CreateEventPayload,
  DownloadItem,
  CreateDownloadPayload,
  AlbumItem,
  CreateAlbumPayload,
  MediaItem,
} from '@/features/cms/types';

export default function CmsAdminPage() {
  const [activeTab, setActiveTab] = useState<'profile' | 'posts' | 'events' | 'downloads' | 'albums'>('profile');

  // =========================================================================
  // COMMON STATE
  // =========================================================================
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);
  const [deleteConfirm, setDeleteConfirm] = useState<{
    isOpen: boolean;
    title: string;
    description: string;
    itemLabel?: string;
    onConfirm: () => Promise<void>;
    isLoading?: boolean;
  }>({
    isOpen: false,
    title: '',
    description: '',
    onConfirm: async () => {},
    isLoading: false,
  });

  // =========================================================================
  // TAB 1: SITE PROFILE STATE (ADM-CMS-01)
  // =========================================================================
  const [profileForm, setProfileForm] = useState<SiteSettingsPayload>({
    identity: {
      name: '',
      tagline: '',
      logo_url: '',
      favicon_url: '',
    },
    contact: {
      phone: '',
      email: '',
      whatsapp: '',
    },
    address: {
      street: '',
      city: '',
      postal_code: '',
      maps_embed_url: '',
    },
    profile: {
      vision: '',
      mission: [],
      principal_greeting: {
        principal_name: '',
        greeting_text: '',
      },
      accreditation: {
        grade: '',
        score: 0,
        certificate_no: '',
      },
    },
  });
  const [isSavingProfile, setIsSavingProfile] = useState(false);
  const [missionInput, setMissionInput] = useState('');

  // =========================================================================
  // TAB 2: POSTS / BERITA STATE (ADM-CMS-02)
  // =========================================================================
  const [posts, setPosts] = useState<PostItem[]>([]);
  const [categories, setCategories] = useState<PostCategoryItem[]>([]);
  const [postSearch, setPostSearch] = useState('');
  const [postStatusFilter, setPostStatusFilter] = useState<'all' | 'draft' | 'terbit' | 'arsip'>('all');
  const [postVisibilityFilter, setPostVisibilityFilter] = useState<'all' | 'publik' | 'internal'>('all');
  const [isPostModalOpen, setIsPostModalOpen] = useState(false);
  const [editingPostId, setEditingPostId] = useState<string | null>(null);
  const [postModalError, setPostModalError] = useState<string | null>(null);
  const [currentCoverUrl, setCurrentCoverUrl] = useState<string | null>(null);
  const [isUploadingCover, setIsUploadingCover] = useState(false);
  const [postForm, setPostForm] = useState<CreatePostPayload>({
    title: '',
    slug: '',
    content: '',
    category_id: '',
    status: 'draft',
    visibility: 'publik',
  });
  const [isSubmittingPost, setIsSubmittingPost] = useState(false);

  // =========================================================================
  // TAB 3: EVENTS / AGENDA STATE (ADM-CMS-03)
  // =========================================================================
  const [events, setEvents] = useState<SchoolEventItem[]>([]);
  const [eventCategoryFilter, setEventCategoryFilter] = useState<'all' | 'ujian' | 'libur' | 'event'>('all');
  const [eventVisibilityFilter, setEventVisibilityFilter] = useState<'all' | 'publik' | 'internal'>('all');
  const [isEventModalOpen, setIsEventModalOpen] = useState(false);
  const [eventForm, setEventForm] = useState<CreateEventPayload>({
    title: '',
    category: 'event',
    visibility: 'publik',
    start_at: '',
    end_at: '',
  });
  const [isSubmittingEvent, setIsSubmittingEvent] = useState(false);

  // =========================================================================
  // TAB 4: DOWNLOADS STATE (ADM-CMS-04)
  // =========================================================================
  const [downloads, setDownloads] = useState<DownloadItem[]>([]);
  const [downloadSearch, setDownloadSearch] = useState('');
  const [downloadCategoryFilter, setDownloadCategoryFilter] = useState('all');
  const [isDownloadModalOpen, setIsDownloadModalOpen] = useState(false);
  const [downloadForm, setDownloadForm] = useState<CreateDownloadPayload>({
    title: '',
    category: 'Dokumen Resmi',
    visibility: 'publik',
    file_id: '',
  });
  const [isSubmittingDownload, setIsSubmittingDownload] = useState(false);

  // =========================================================================
  // TAB 5: ALBUMS STATE (ADM-CMS-05)
  // =========================================================================
  const [albums, setAlbums] = useState<AlbumItem[]>([]);
  const [albumSearch, setAlbumSearch] = useState('');
  const [isAlbumModalOpen, setIsAlbumModalOpen] = useState(false);
  const [albumForm, setAlbumForm] = useState<CreateAlbumPayload>({
    title: '',
  });
  const [isSubmittingAlbum, setIsSubmittingAlbum] = useState(false);

  // INT-01: ALBUM MEDIA MANAGER STATE
  const [selectedAlbum, setSelectedAlbum] = useState<AlbumItem | null>(null);
  const [albumDetail, setAlbumDetail] = useState<AlbumItem | null>(null);
  const [isLoadingDetail, setIsLoadingDetail] = useState(false);
  const [detailError, setDetailError] = useState<string | null>(null);
  const [mediaCaption, setMediaCaption] = useState('');
  const [mediaFileId, setMediaFileId] = useState<string | null>(null);
  const [isUploadingMedia, setIsUploadingMedia] = useState(false);

  // =========================================================================
  // DATA FETCHING
  // =========================================================================
  const fetchData = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    try {
      if (activeTab === 'profile') {
        const res = await cmsApi.getSiteSettings();
        if (res.data) {
          const d = res.data;
          setProfileForm({
            identity: {
              name: d.identity?.name || '',
              tagline: d.identity?.tagline || '',
              logo_url: d.identity?.logo_url || '',
              favicon_url: d.identity?.favicon_url || '',
            },
            contact: {
              phone: d.contact?.phone || '',
              email: d.contact?.email || '',
              whatsapp: d.contact?.whatsapp || '',
            },
            address: {
              street: d.address?.street || '',
              city: d.address?.city || '',
              postal_code: d.address?.postal_code || '',
              maps_embed_url: d.address?.maps_embed_url || '',
            },
            profile: {
              vision: d.profile?.vision || '',
              mission: d.profile?.mission || [],
              principal_greeting: {
                principal_name: d.profile?.principal_greeting?.principal_name || '',
                greeting_text: d.profile?.principal_greeting?.greeting_text || '',
              },
              accreditation: {
                grade: d.profile?.accreditation?.grade || '',
                score: d.profile?.accreditation?.score || 0,
                certificate_no: d.profile?.accreditation?.certificate_no || '',
              },
            },
          });
          setMissionInput((d.profile?.mission || []).join('\n'));
        }
      } else if (activeTab === 'posts') {
        const [postsRes, categoriesRes] = await Promise.all([
          cmsApi.getPosts({
            q: postSearch || undefined,
            status: postStatusFilter === 'all' ? undefined : postStatusFilter,
            visibility: postVisibilityFilter === 'all' ? undefined : postVisibilityFilter,
          }),
          cmsApi.getPostCategories(),
        ]);
        setPosts(postsRes.data || []);
        setCategories(categoriesRes.data || []);
      } else if (activeTab === 'events') {
        const eventsRes = await cmsApi.getEvents({
          category: eventCategoryFilter === 'all' ? undefined : eventCategoryFilter,
          visibility: eventVisibilityFilter === 'all' ? undefined : eventVisibilityFilter,
        });
        setEvents(eventsRes.data || []);
      } else if (activeTab === 'downloads') {
        const downloadsRes = await cmsApi.getDownloads({
          q: downloadSearch || undefined,
          category: downloadCategoryFilter === 'all' ? undefined : downloadCategoryFilter,
        });
        setDownloads(downloadsRes.data || []);
      } else if (activeTab === 'albums') {
        const albumsRes = await cmsApi.getAlbums({
          q: albumSearch || undefined,
        });
        setAlbums(albumsRes.data || []);
      }
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Gagal memuat data modul CMS.';
      setError(msg);
    } finally {
      setIsLoading(false);
    }
  }, [
    activeTab,
    postSearch,
    postStatusFilter,
    postVisibilityFilter,
    eventCategoryFilter,
    eventVisibilityFilter,
    downloadSearch,
    downloadCategoryFilter,
    albumSearch,
  ]);

  useEffect(() => {
    fetchData();
  }, [fetchData]);

  // Dismiss toast after 4s
  useEffect(() => {
    if (successMessage) {
      const timer = setTimeout(() => setSuccessMessage(null), 4000);
      return () => clearTimeout(timer);
    }
  }, [successMessage]);

  // =========================================================================
  // ACTIONS: PROFILE
  // =========================================================================
  const handleSaveProfile = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsSavingProfile(true);
    setError(null);
    try {
      const missions = missionInput
        .split('\n')
        .map((m) => m.trim())
        .filter((m) => m.length > 0);

      const payload: SiteSettingsPayload = {
        ...profileForm,
        profile: {
          ...profileForm.profile,
          mission: missions,
        },
      };

      await cmsApi.updateSiteSettings(payload);
      setSuccessMessage('Pengaturan profil situs sekolah berhasil disimpan.');
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Gagal menyimpan profil situs.');
    } finally {
      setIsSavingProfile(false);
    }
  };

  // =========================================================================
  // ACTIONS: POSTS
  // =========================================================================
  const handleOpenCreatePost = () => {
    setEditingPostId(null);
    setPostModalError(null);
    setCurrentCoverUrl(null);
    setIsUploadingCover(false);
    setPostForm({
      title: '',
      slug: '',
      content: '',
      category_id: '',
      status: 'draft',
      visibility: 'publik',
      cover_file_id: undefined,
    });
    setIsPostModalOpen(true);
  };

  const handleOpenEditPost = async (post: PostItem) => {
    setEditingPostId(post.id);
    setPostModalError(null);
    const initialCoverUrl = post.cover_file?.url || post.coverFile?.url || (post as any).cover?.url || null;
    setCurrentCoverUrl(initialCoverUrl);
    setIsUploadingCover(false);
    setPostForm({
      title: post.title || '',
      slug: post.slug || '',
      content: post.content || '',
      category_id: post.category_id || post.category?.id || '',
      status: post.status || 'draft',
      visibility: post.visibility || 'publik',
      cover_file_id: post.cover_file_id || undefined,
    });
    setIsPostModalOpen(true);

    // Ambil detail mutakhir dari backend jika konten kosong atau ingin menyegarkan cover
    try {
      const res = await cmsApi.getPost(post.id);
      if (res.data) {
        const full = res.data;
        const fullCoverUrl = full.cover_file?.url || full.coverFile?.url || (full as any).cover?.url || null;
        if (fullCoverUrl) {
          setCurrentCoverUrl(fullCoverUrl);
        }
        setPostForm((prev) => ({
          title: full.title || prev.title,
          slug: full.slug || prev.slug,
          content: full.content || prev.content,
          category_id: full.category_id || full.category?.id || prev.category_id,
          status: full.status || prev.status,
          visibility: full.visibility || prev.visibility,
          cover_file_id: full.cover_file_id || prev.cover_file_id,
        }));
      }
    } catch (err: unknown) {
      console.warn('Gagal memuat detail artikel segar:', err);
    }
  };

  const handleClosePostModal = () => {
    if (isSubmittingPost || isUploadingCover) return;
    setIsPostModalOpen(false);
    setEditingPostId(null);
    setPostModalError(null);
    setCurrentCoverUrl(null);
    setIsUploadingCover(false);
    setPostForm({
      title: '',
      slug: '',
      content: '',
      category_id: '',
      status: 'draft',
      visibility: 'publik',
      cover_file_id: undefined,
    });
  };

  const handleSavePost = async (e: React.FormEvent) => {
    e.preventDefault();
    if (isUploadingCover) {
      const uploadWaitMsg = 'Harap tunggu hingga proses unggah berkas gambar sampul selesai.';
      setError(uploadWaitMsg);
      setPostModalError(uploadWaitMsg);
      return;
    }
    setIsSubmittingPost(true);
    setError(null);
    setPostModalError(null);
    try {
      if (editingPostId) {
        // INT-04 & INT-05: Simpan perubahan pada berita yang sudah ada dengan cover
        await cmsApi.updatePost(editingPostId, postForm);
        setSuccessMessage('Berita atau artikel berhasil diperbarui.');
      } else {
        // Pembuatan berita baru dengan cover
        await cmsApi.createPost(postForm);
        setSuccessMessage('Berita atau pengumuman berhasil dibuat.');
      }
      setIsPostModalOpen(false);
      setEditingPostId(null);
      setCurrentCoverUrl(null);
      setPostForm({
        title: '',
        slug: '',
        content: '',
        category_id: '',
        status: 'draft',
        visibility: 'publik',
        cover_file_id: undefined,
      });
      fetchData();
    } catch (err: unknown) {
      let msg = editingPostId ? 'Gagal memperbarui berita.' : 'Gagal membuat berita.';
      if (err instanceof Error) {
        msg = err.message;
      }
      if (typeof err === 'object' && err !== null && 'details' in err && Array.isArray((err as any).details)) {
        const details = (err as any).details;
        if (details.length > 0) {
          const detailMsgs = details.map((d: any) => d.message || d.field).join('. ');
          msg = `${msg}: ${detailMsgs}`;
        }
      }
      setError(msg);
      setPostModalError(msg);
    } finally {
      setIsSubmittingPost(false);
    }
  };

  const handleDeletePost = (id: string, title: string) => {
    setDeleteConfirm({
      isOpen: true,
      title: 'Hapus Berita',
      description: 'Apakah Anda yakin ingin menghapus berita ini? Tindakan ini tidak dapat dibatalkan.',
      itemLabel: title,
      onConfirm: async () => {
        try {
          await cmsApi.deletePost(id);
          setSuccessMessage('Berita berhasil dihapus.');
          fetchData();
        } catch (err: unknown) {
          setError(err instanceof Error ? err.message : 'Gagal menghapus berita.');
        }
      },
    });
  };

  // =========================================================================
  // ACTIONS: EVENTS
  // =========================================================================
  const handleCreateEvent = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsSubmittingEvent(true);
    setError(null);
    try {
      await cmsApi.createEvent(eventForm);
      setSuccessMessage('Agenda kegiatan berhasil ditambahkan.');
      setIsEventModalOpen(false);
      setEventForm({
        title: '',
        category: 'event',
        visibility: 'publik',
        start_at: '',
        end_at: '',
      });
      fetchData();
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Gagal menambahkan agenda.');
    } finally {
      setIsSubmittingEvent(false);
    }
  };

  const handleDeleteEvent = (id: string, title: string) => {
    setDeleteConfirm({
      isOpen: true,
      title: 'Hapus Agenda',
      description: 'Apakah Anda yakin ingin menghapus agenda kegiatan ini?',
      itemLabel: title,
      onConfirm: async () => {
        try {
          await cmsApi.deleteEvent(id);
          setSuccessMessage('Agenda berhasil dihapus.');
          fetchData();
        } catch (err: unknown) {
          setError(err instanceof Error ? err.message : 'Gagal menghapus agenda.');
        }
      },
    });
  };

  // =========================================================================
  // ACTIONS: DOWNLOADS
  // =========================================================================
  const handleCreateDownload = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsSubmittingDownload(true);
    setError(null);
    try {
      await cmsApi.createDownload(downloadForm);
      setSuccessMessage('Dokumen unduhan berhasil ditambahkan.');
      setIsDownloadModalOpen(false);
      setDownloadForm({
        title: '',
        category: 'Dokumen Resmi',
        visibility: 'publik',
        file_id: '',
      });
      fetchData();
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Gagal menambahkan dokumen unduhan.');
    } finally {
      setIsSubmittingDownload(false);
    }
  };

  const handleDeleteDownload = (id: string, title: string) => {
    setDeleteConfirm({
      isOpen: true,
      title: 'Hapus Dokumen Unduhan',
      description: 'Apakah Anda yakin ingin menghapus dokumen unduhan ini?',
      itemLabel: title,
      onConfirm: async () => {
        try {
          await cmsApi.deleteDownload(id);
          setSuccessMessage('Dokumen unduhan berhasil dihapus.');
          fetchData();
        } catch (err: unknown) {
          setError(err instanceof Error ? err.message : 'Gagal menghapus dokumen.');
        }
      },
    });
  };

  // =========================================================================
  // ACTIONS: ALBUMS
  // =========================================================================
  const handleCreateAlbum = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsSubmittingAlbum(true);
    setError(null);
    try {
      await cmsApi.createAlbum(albumForm);
      setSuccessMessage('Album foto berhasil dibuat.');
      setIsAlbumModalOpen(false);
      setAlbumForm({ title: '' });
      fetchData();
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Gagal membuat album.');
    } finally {
      setIsSubmittingAlbum(false);
    }
  };

  const handleDeleteAlbum = (id: string, title: string) => {
    setDeleteConfirm({
      isOpen: true,
      title: 'Hapus Album',
      description: 'Apakah Anda yakin ingin menghapus album ini beserta seluruh media di dalamnya?',
      itemLabel: title,
      onConfirm: async () => {
        try {
          await cmsApi.deleteAlbum(id);
          setSuccessMessage('Album berhasil dihapus.');
          fetchData();
        } catch (err: unknown) {
          setError(err instanceof Error ? err.message : 'Gagal menghapus album.');
        }
      },
    });
  };

  // =========================================================================
  // ACTIONS: ALBUM MEDIA ITEMS (INT-01)
  // =========================================================================
  const fetchAlbumDetail = useCallback(async (albumId: string) => {
    setIsLoadingDetail(true);
    setDetailError(null);
    try {
      const res = await cmsApi.getAlbum(albumId);
      if (res.data) {
        setAlbumDetail(res.data);
      }
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Gagal memuat rincian foto dalam album.';
      setDetailError(msg);
    } finally {
      setIsLoadingDetail(false);
    }
  }, []);

  const handleOpenMediaManager = (album: AlbumItem) => {
    setSelectedAlbum(album);
    setMediaCaption('');
    setMediaFileId(null);
    fetchAlbumDetail(album.id);
  };

  const handleCloseMediaManager = () => {
    setSelectedAlbum(null);
    setAlbumDetail(null);
    setMediaCaption('');
    setMediaFileId(null);
    setDetailError(null);
  };

  const handleAddMediaToAlbum = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedAlbum) return;
    if (!mediaFileId) {
      setError('Silakan pilih dan unggah berkas foto terlebih dahulu.');
      return;
    }

    setIsUploadingMedia(true);
    setError(null);
    try {
      await cmsApi.createMediaItem({
        album_id: selectedAlbum.id,
        file_id: mediaFileId,
        media_type: 'image',
        caption: mediaCaption.trim() || undefined,
      });

      setSuccessMessage('Foto berhasil ditambahkan ke album.');
      setMediaCaption('');
      setMediaFileId(null);

      // Segarkan daftar media dalam album dan perbarui daftar album utama
      await fetchAlbumDetail(selectedAlbum.id);
      fetchData();
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Gagal menambahkan foto ke album.';
      setError(msg);
    } finally {
      setIsUploadingMedia(false);
    }
  };

  const handleDeleteMediaItem = (mediaId: string, caption?: string | null) => {
    const label = caption ? `"${caption}"` : 'foto ini';
    setDeleteConfirm({
      isOpen: true,
      title: 'Hapus Foto dari Album',
      description: `Apakah Anda yakin ingin menghapus ${label} dari album?`,
      itemLabel: label,
      onConfirm: async () => {
        try {
          await cmsApi.deleteMediaItem(mediaId);
          setSuccessMessage('Foto berhasil dihapus dari album.');
          if (selectedAlbum) {
            await fetchAlbumDetail(selectedAlbum.id);
            fetchData();
          }
        } catch (err: unknown) {
          const msg = err instanceof Error ? err.message : 'Gagal menghapus foto dari album.';
          setError(msg);
        }
      },
    });
  };

  return (
    <div className="space-y-6">
      {/* Header Halaman */}
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-bold tracking-tight text-slate-900 flex items-center gap-2">
            <Globe className="h-6 w-6 text-primary" />
            CMS &amp; Portal Sekolah Publik
          </h1>
          <p className="text-sm text-slate-600 mt-1">
            Pengelolaan identitas situs, berita, agenda kegiatan, unduhan materi, dan galeri dokumentasi sekolah.
          </p>
        </div>
        <div className="flex items-center gap-2">
          <Button variant="outline" size="sm" onClick={() => fetchData()} disabled={isLoading} className="gap-2">
            <RefreshCw className={`h-4 w-4 ${isLoading ? 'animate-spin' : ''}`} />
            Muat Ulang
          </Button>
          <a
            href="/"
            target="_blank"
            rel="noopener noreferrer"
            className="inline-flex items-center justify-center rounded-sm text-xs font-medium border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 h-9 px-3 gap-1.5"
          >
            <ExternalLink className="h-3.5 w-3.5 text-slate-500" />
            Lihat Portal Publik
          </a>
        </div>
      </div>

      {/* Global Alerts */}
      {successMessage && (
        <div className="rounded-sm border border-green-200 bg-green-50 p-4 text-sm text-green-800 flex items-center gap-2 shadow-sm animate-fade-in">
          <CheckCircle2 className="h-5 w-5 text-green-600 shrink-0" />
          <span>{successMessage}</span>
        </div>
      )}

      {error && (
        <div className="rounded-sm border border-red-200 bg-red-50 p-4 text-sm text-red-800 flex items-center justify-between gap-2 shadow-sm">
          <div className="flex items-center gap-2">
            <AlertCircle className="h-5 w-5 text-red-600 shrink-0" />
            <span>{error}</span>
          </div>
          <Button variant="outline" size="sm" onClick={() => fetchData()} className="bg-white border-red-200 text-red-700 hover:bg-red-50">
            Coba Lagi
          </Button>
        </div>
      )}

      {/* Tab Navigation */}
      <div className="flex border-b border-slate-200 overflow-x-auto">
        <button
          onClick={() => setActiveTab('profile')}
          className={`flex items-center gap-2 px-4 py-3 text-sm font-medium border-b-2 whitespace-nowrap transition-colors ${
            activeTab === 'profile'
              ? 'border-primary text-primary'
              : 'border-transparent text-slate-500 hover:text-slate-800 hover:border-slate-300'
          }`}
        >
          <School className="h-4 w-4" />
          Profil &amp; Identitas Sekolah
        </button>
        <button
          onClick={() => setActiveTab('posts')}
          className={`flex items-center gap-2 px-4 py-3 text-sm font-medium border-b-2 whitespace-nowrap transition-colors ${
            activeTab === 'posts'
              ? 'border-primary text-primary'
              : 'border-transparent text-slate-500 hover:text-slate-800 hover:border-slate-300'
          }`}
        >
          <FileText className="h-4 w-4" />
          Berita &amp; Artikel
        </button>
        <button
          onClick={() => setActiveTab('events')}
          className={`flex items-center gap-2 px-4 py-3 text-sm font-medium border-b-2 whitespace-nowrap transition-colors ${
            activeTab === 'events'
              ? 'border-primary text-primary'
              : 'border-transparent text-slate-500 hover:text-slate-800 hover:border-slate-300'
          }`}
        >
          <Calendar className="h-4 w-4" />
          Agenda Kegiatan
        </button>
        <button
          onClick={() => setActiveTab('downloads')}
          className={`flex items-center gap-2 px-4 py-3 text-sm font-medium border-b-2 whitespace-nowrap transition-colors ${
            activeTab === 'downloads'
              ? 'border-primary text-primary'
              : 'border-transparent text-slate-500 hover:text-slate-800 hover:border-slate-300'
          }`}
        >
          <Download className="h-4 w-4" />
          Pusat Unduhan
        </button>
        <button
          onClick={() => setActiveTab('albums')}
          className={`flex items-center gap-2 px-4 py-3 text-sm font-medium border-b-2 whitespace-nowrap transition-colors ${
            activeTab === 'albums'
              ? 'border-primary text-primary'
              : 'border-transparent text-slate-500 hover:text-slate-800 hover:border-slate-300'
          }`}
        >
          <ImageIcon className="h-4 w-4" />
          Galeri &amp; Media
        </button>
      </div>

      {/* =========================================================================
          TAB 1: PROFIL & IDENTITAS SEKOLAH (ADM-CMS-01)
      ========================================================================= */}
      {activeTab === 'profile' && (
        <div className="space-y-6">
          {isLoading ? (
            <Card className="rounded-sm">
              <CardContent className="p-8 text-center text-slate-500">
                <RefreshCw className="h-8 w-8 animate-spin mx-auto mb-3 text-primary" />
                <p>Memuat konfigurasi profil dan identitas situs sekolah...</p>
              </CardContent>
            </Card>
          ) : (
            <form onSubmit={handleSaveProfile} className="space-y-6">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                {/* Identitas Utama */}
                <Card className="rounded-sm">
                  <CardHeader>
                    <CardTitle className="text-base flex items-center gap-2">
                      <School className="h-4 w-4 text-primary" />
                      Identitas Sekolah
                    </CardTitle>
                    <CardDescription>Nama resmi, slogan, dan logo yang tampil di beranda portal.</CardDescription>
                  </CardHeader>
                  <CardContent className="space-y-4">
                    <div>
                      <label className="text-xs font-semibold text-slate-700 block mb-1">Nama Resmi Sekolah</label>
                      <Input
                        value={profileForm.identity?.name || ''}
                        onChange={(e) =>
                          setProfileForm({
                            ...profileForm,
                            identity: { ...profileForm.identity, name: e.target.value },
                          })
                        }
                        placeholder="Contoh: SMP Laboratorium UM"
                      />
                    </div>
                    <div>
                      <label className="text-xs font-semibold text-slate-700 block mb-1">Tagline / Slogan</label>
                      <Input
                        value={profileForm.identity?.tagline || ''}
                        onChange={(e) =>
                          setProfileForm({
                            ...profileForm,
                            identity: { ...profileForm.identity, tagline: e.target.value },
                          })
                        }
                        placeholder="Contoh: Beradab, Kreatif, Berprestasi"
                      />
                    </div>
                    <div>
                      <label className="text-xs font-semibold text-slate-700 block mb-1">URL Logo Resmi</label>
                      <Input
                        value={profileForm.identity?.logo_url || ''}
                        onChange={(e) =>
                          setProfileForm({
                            ...profileForm,
                            identity: { ...profileForm.identity, logo_url: e.target.value },
                          })
                        }
                        placeholder="https://example.com/logo.png"
                      />
                    </div>
                  </CardContent>
                </Card>

                {/* Kontak Resmi */}
                <Card className="rounded-sm">
                  <CardHeader>
                    <CardTitle className="text-base flex items-center gap-2">
                      <Phone className="h-4 w-4 text-primary" />
                      Kontak &amp; Layanan Publik
                    </CardTitle>
                    <CardDescription>Informasi narahubung resmi bagi calon siswa dan wali murid.</CardDescription>
                  </CardHeader>
                  <CardContent className="space-y-4">
                    <div>
                      <label className="text-xs font-semibold text-slate-700 block mb-1">Nomor Telepon Kantor</label>
                      <Input
                        value={profileForm.contact?.phone || ''}
                        onChange={(e) =>
                          setProfileForm({
                            ...profileForm,
                            contact: { ...profileForm.contact, phone: e.target.value },
                          })
                        }
                        placeholder="0341-123456"
                      />
                    </div>
                    <div>
                      <label className="text-xs font-semibold text-slate-700 block mb-1">Email Resmi</label>
                      <Input
                        type="email"
                        value={profileForm.contact?.email || ''}
                        onChange={(e) =>
                          setProfileForm({
                            ...profileForm,
                            contact: { ...profileForm.contact, email: e.target.value },
                          })
                        }
                        placeholder="info@labum.sch.id"
                      />
                    </div>
                    <div>
                      <label className="text-xs font-semibold text-slate-700 block mb-1">Hotline WhatsApp</label>
                      <Input
                        value={profileForm.contact?.whatsapp || ''}
                        onChange={(e) =>
                          setProfileForm({
                            ...profileForm,
                            contact: { ...profileForm.contact, whatsapp: e.target.value },
                          })
                        }
                        placeholder="081234567890"
                      />
                    </div>
                  </CardContent>
                </Card>
              </div>

              {/* Alamat & Lokasi */}
              <Card className="rounded-sm">
                <CardHeader>
                  <CardTitle className="text-base flex items-center gap-2">
                    <MapPin className="h-4 w-4 text-primary" />
                    Alamat Kampus &amp; Peta Lokasi
                  </CardTitle>
                  <CardDescription>Alamat fisik sekolah dan tautan embed Google Maps.</CardDescription>
                </CardHeader>
                <CardContent className="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <div className="md:col-span-2">
                    <label className="text-xs font-semibold text-slate-700 block mb-1">Alamat Jalan</label>
                    <Input
                      value={profileForm.address?.street || ''}
                      onChange={(e) =>
                        setProfileForm({
                          ...profileForm,
                          address: { ...profileForm.address, street: e.target.value },
                        })
                      }
                      placeholder="Jl. Bromo No. 16"
                    />
                  </div>
                  <div>
                    <label className="text-xs font-semibold text-slate-700 block mb-1">Kota / Kabupaten</label>
                    <Input
                      value={profileForm.address?.city || ''}
                      onChange={(e) =>
                        setProfileForm({
                          ...profileForm,
                          address: { ...profileForm.address, city: e.target.value },
                        })
                      }
                      placeholder="Malang"
                    />
                  </div>
                </CardContent>
              </Card>

              {/* Visi, Misi & Sambutan Kepala Sekolah */}
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                <Card className="rounded-sm">
                  <CardHeader>
                    <CardTitle className="text-base flex items-center gap-2">
                      <Award className="h-4 w-4 text-primary" />
                      Visi &amp; Misi Pendidikan
                    </CardTitle>
                    <CardDescription>Cita-cita luhur dan panduan langkah strategis institusi.</CardDescription>
                  </CardHeader>
                  <CardContent className="space-y-4">
                    <div>
                      <label className="text-xs font-semibold text-slate-700 block mb-1">Visi Sekolah</label>
                      <Textarea
                        rows={3}
                        value={profileForm.profile?.vision || ''}
                        onChange={(e) =>
                          setProfileForm({
                            ...profileForm,
                            profile: { ...profileForm.profile, vision: e.target.value },
                          })
                        }
                        placeholder="Menjadi sekolah teladan..."
                      />
                    </div>
                    <div>
                      <label className="text-xs font-semibold text-slate-700 block mb-1">
                        Misi Sekolah (Satu butir per baris)
                      </label>
                      <Textarea
                        rows={5}
                        value={missionInput}
                        onChange={(e) => setMissionInput(e.target.value)}
                        placeholder="1. Menyelenggarakan pembelajaran berkualitas&#10;2. Membina akhlak mulia siswa&#10;3. Mengembangkan potensi bakat dan literasi"
                      />
                    </div>
                  </CardContent>
                </Card>

                <Card className="rounded-sm">
                  <CardHeader>
                    <CardTitle className="text-base flex items-center gap-2">
                      <School className="h-4 w-4 text-primary" />
                      Sambutan Kepala Sekolah
                    </CardTitle>
                    <CardDescription>Pesan resmi pimpinan sekolah yang ditampilkan pada landing page.</CardDescription>
                  </CardHeader>
                  <CardContent className="space-y-4">
                    <div>
                      <label className="text-xs font-semibold text-slate-700 block mb-1">Nama Kepala Sekolah</label>
                      <Input
                        value={profileForm.profile?.principal_greeting?.principal_name || ''}
                        onChange={(e) =>
                          setProfileForm({
                            ...profileForm,
                            profile: {
                              ...profileForm.profile,
                              principal_greeting: {
                                ...profileForm.profile?.principal_greeting,
                                principal_name: e.target.value,
                              },
                            },
                          })
                        }
                        placeholder="Dra. Hj. Sri Wahyuni, M.Pd"
                      />
                    </div>
                    <div>
                      <label className="text-xs font-semibold text-slate-700 block mb-1">Naskah Sambutan</label>
                      <Textarea
                        rows={7}
                        value={profileForm.profile?.principal_greeting?.greeting_text || ''}
                        onChange={(e) =>
                          setProfileForm({
                            ...profileForm,
                            profile: {
                              ...profileForm.profile,
                              principal_greeting: {
                                ...profileForm.profile?.principal_greeting,
                                greeting_text: e.target.value,
                              },
                            },
                          })
                        }
                        placeholder="Assalamu'alaikum Warahmatullahi Wabarakatuh. Selamat datang di portal resmi..."
                      />
                    </div>
                  </CardContent>
                </Card>
              </div>

              {/* Tombol Simpan */}
              <div className="flex justify-end gap-3 pt-4 border-t border-slate-200">
                <Button type="submit" disabled={isSavingProfile} className="gap-2 bg-primary">
                  {isSavingProfile ? <RefreshCw className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
                  Simpan Perubahan Profil
                </Button>
              </div>
            </form>
          )}
        </div>
      )}

      {/* =========================================================================
          TAB 2: BERITA & PENGUMUMAN (ADM-CMS-02)
      ========================================================================= */}
      {activeTab === 'posts' && (
        <div className="space-y-6">
          {/* Action & Filter Bar */}
          <div className="flex flex-col sm:flex-row items-center justify-between gap-4">
            <div className="flex flex-wrap items-center gap-3 w-full sm:w-auto">
              <div className="relative w-full sm:w-64">
                <Search className="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                <Input
                  placeholder="Cari berita..."
                  value={postSearch}
                  onChange={(e) => setPostSearch(e.target.value)}
                  className="pl-9 h-9 text-xs"
                />
              </div>
              <select
                value={postStatusFilter}
                onChange={(e) => setPostStatusFilter(e.target.value as any)}
                className="h-9 px-3 text-xs rounded-sm border border-slate-200 bg-white text-slate-700"
              >
                <option value="all">Semua Status</option>
                <option value="draft">Draf</option>
                <option value="terbit">Terbit</option>
                <option value="arsip">Arsip</option>
              </select>
              <select
                value={postVisibilityFilter}
                onChange={(e) => setPostVisibilityFilter(e.target.value as any)}
                className="h-9 px-3 text-xs rounded-sm border border-slate-200 bg-white text-slate-700"
              >
                <option value="all">Semua Visibilitas</option>
                <option value="publik">Publik</option>
                <option value="internal">Internal</option>
              </select>
            </div>
            <Button size="sm" onClick={handleOpenCreatePost} className="gap-2 shrink-0">
              <Plus className="h-4 w-4" />
              Tulis Berita Baru
            </Button>
          </div>

          {/* Table / List */}
          {isLoading ? (
            <Card className="rounded-sm">
              <CardContent className="p-8 text-center text-slate-500">
                <RefreshCw className="h-8 w-8 animate-spin mx-auto mb-3 text-primary" />
                <p>Memuat daftar berita dan artikel...</p>
              </CardContent>
            </Card>
          ) : posts.length === 0 ? (
            <Card className="rounded-sm border-dashed">
              <CardContent className="p-12 text-center">
                <FileText className="h-12 w-12 mx-auto mb-3 text-slate-300" />
                <h3 className="text-base font-semibold text-slate-900">Belum Ada Berita</h3>
                <p className="text-xs text-slate-500 max-w-sm mx-auto mt-1 mb-4">
                  Belum ada artikel atau rilis berita yang dibuat. Klik tombol di bawah untuk membuat berita pertama.
                </p>
                <Button size="sm" onClick={handleOpenCreatePost} className="gap-2">
                  <Plus className="h-4 w-4" />
                  Buat Berita Sekarang
                </Button>
              </CardContent>
            </Card>
          ) : (
            <div className="overflow-x-auto rounded-sm border border-slate-200 bg-white">
              <table className="w-full text-left text-xs text-slate-600">
                <thead className="bg-slate-50 text-slate-700 uppercase tracking-wider font-semibold border-b border-slate-200">
                  <tr>
                    <th className="px-4 py-3">Judul Berita</th>
                    <th className="px-4 py-3">Kategori</th>
                    <th className="px-4 py-3">Status</th>
                    <th className="px-4 py-3">Visibilitas</th>
                    <th className="px-4 py-3">Tanggal Tayang</th>
                    <th className="px-4 py-3 text-right">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-200">
                  {posts.map((post) => {
                    const coverUrl = post.cover_file?.url || post.coverFile?.url || (post as any).cover?.url || null;
                    return (
                      <tr key={post.id} className="hover:bg-slate-50 transition-colors">
                        <td className="px-4 py-3">
                          <div className="flex items-center gap-3">
                            {coverUrl ? (
                              /* eslint-disable-next-line @next/next/no-img-element */
                              <img
                                src={coverUrl}
                                alt={`Sampul ${post.title}`}
                                className="w-10 h-10 object-cover rounded-sm border border-slate-200 shrink-0 bg-slate-100"
                                onError={(e) => {
                                  e.currentTarget.style.display = 'none';
                                }}
                              />
                            ) : (
                              <div className="w-10 h-10 rounded-sm border border-slate-200 bg-slate-100 flex items-center justify-center shrink-0 text-slate-400">
                                <ImageIcon className="w-4 h-4" />
                              </div>
                            )}
                            <div className="min-w-0">
                              <span className="font-medium text-slate-900 max-w-xs truncate block">
                                {post.title}
                              </span>
                              {post.slug && <span className="text-[10px] text-slate-400 truncate block">/{post.slug}</span>}
                            </div>
                          </div>
                        </td>
                      <td className="px-4 py-3">{post.category?.name || 'Umum'}</td>
                      <td className="px-4 py-3">
                        <Badge
                          variant={
                            post.status === 'terbit'
                              ? 'success'
                              : post.status === 'draft'
                              ? 'warning'
                              : 'default'
                          }
                        >
                          {post.status}
                        </Badge>
                      </td>
                      <td className="px-4 py-3">
                        <Badge variant={post.visibility === 'publik' ? 'info' : 'outline'}>
                          {post.visibility}
                        </Badge>
                      </td>
                      <td className="px-4 py-3">
                        {post.published_at
                          ? new Date(post.published_at).toLocaleDateString('id-ID')
                          : '-'}
                      </td>
                      <td className="px-4 py-3 text-right">
                        <div className="flex items-center justify-end gap-1.5">
                          <Button
                            variant="outline"
                            size="sm"
                            onClick={() => handleOpenEditPost(post)}
                            className="h-7 px-2 text-slate-700 hover:text-primary hover:bg-slate-100 border-slate-200"
                            title={`Edit berita ${post.title}`}
                            aria-label={`Edit berita ${post.title}`}
                          >
                            <Pencil className="h-3.5 w-3.5" />
                          </Button>
                          <Button
                            variant="outline"
                            size="sm"
                            onClick={() => handleDeletePost(post.id, post.title)}
                            className="h-7 px-2 text-red-600 hover:text-red-700 hover:bg-red-50 border-slate-200"
                            title={`Hapus berita ${post.title}`}
                            aria-label={`Hapus berita ${post.title}`}
                          >
                            <Trash2 className="h-3.5 w-3.5" />
                          </Button>
                        </div>
                      </td>
                    </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          )}

          {/* Modal Tulis / Edit Berita (INT-04) */}
          {isPostModalOpen && (
            <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
              <div className="bg-white rounded-sm shadow-xl max-w-xl w-full p-6 space-y-4 max-h-[90vh] overflow-y-auto animate-in fade-in">
                <div className="flex items-center justify-between border-b pb-3">
                  <h3 className="font-semibold text-slate-900 flex items-center gap-2">
                    {editingPostId ? (
                      <>
                        <Pencil className="h-4 w-4 text-primary" />
                        Edit Berita / Pengumuman
                      </>
                    ) : (
                      <>
                        <FileText className="h-4 w-4 text-primary" />
                        Tulis Berita / Pengumuman Baru
                      </>
                    )}
                  </h3>
                  <button
                    type="button"
                    onClick={handleClosePostModal}
                    disabled={isSubmittingPost}
                    className="text-slate-400 hover:text-slate-600 disabled:opacity-50"
                    aria-label="Tutup modal berita"
                  >
                    <X className="h-4 w-4" />
                  </button>
                </div>

                {postModalError && (
                  <div className="rounded-sm border border-red-200 bg-red-50 p-3 text-xs text-red-800 flex items-center gap-2">
                    <AlertCircle className="h-4 w-4 text-red-600 shrink-0" />
                    <span>{postModalError}</span>
                  </div>
                )}

                <form onSubmit={handleSavePost} className="space-y-4">
                  <div>
                    <label htmlFor="post-title" className="text-xs font-semibold text-slate-700 block mb-1">
                      Judul Berita *
                    </label>
                    <Input
                      id="post-title"
                      required
                      value={postForm.title}
                      onChange={(e) => setPostForm({ ...postForm, title: e.target.value })}
                      placeholder="Masukkan judul artikel yang informatif..."
                      disabled={isSubmittingPost}
                    />
                  </div>

                  <div>
                    <label htmlFor="post-slug" className="text-xs font-semibold text-slate-700 block mb-1">
                      Slug / URL Ramah (Opsional)
                    </label>
                    <Input
                      id="post-slug"
                      value={postForm.slug || ''}
                      onChange={(e) => setPostForm({ ...postForm, slug: e.target.value })}
                      placeholder="Dibuat otomatis dari judul jika dikosongkan..."
                      disabled={isSubmittingPost}
                    />
                    <p className="text-[10px] text-slate-400 mt-1">
                      Digunakan untuk tautan rute publik (/berita/[slug]).
                    </p>
                  </div>

                  <div className="grid grid-cols-2 gap-3">
                    <div>
                      <label htmlFor="post-category" className="text-xs font-semibold text-slate-700 block mb-1">
                        Kategori
                      </label>
                      <select
                        id="post-category"
                        value={postForm.category_id || ''}
                        onChange={(e) => setPostForm({ ...postForm, category_id: e.target.value || undefined })}
                        disabled={isSubmittingPost}
                        className="w-full h-9 px-3 text-xs rounded-sm border border-slate-200 bg-white text-slate-700 disabled:opacity-50"
                      >
                        <option value="">Pilih Kategori...</option>
                        {categories.map((c) => (
                          <option key={c.id} value={c.id}>
                            {c.name}
                          </option>
                        ))}
                      </select>
                    </div>
                    <div>
                      <label htmlFor="post-visibility" className="text-xs font-semibold text-slate-700 block mb-1">
                        Visibilitas
                      </label>
                      <select
                        id="post-visibility"
                        value={postForm.visibility}
                        onChange={(e) => setPostForm({ ...postForm, visibility: e.target.value as any })}
                        disabled={isSubmittingPost}
                        className="w-full h-9 px-3 text-xs rounded-sm border border-slate-200 bg-white text-slate-700 disabled:opacity-50"
                      >
                        <option value="publik">Publik (Website Luar)</option>
                        <option value="internal">Internal Saja</option>
                      </select>
                    </div>
                  </div>

                  <div>
                    <label htmlFor="post-content" className="text-xs font-semibold text-slate-700 block mb-1">
                      Konten Berita *
                    </label>
                    <Textarea
                      id="post-content"
                      required
                      rows={6}
                      value={postForm.content}
                      onChange={(e) => setPostForm({ ...postForm, content: e.target.value })}
                      placeholder="Tulis naskah berita lengkap di sini..."
                      disabled={isSubmittingPost}
                    />
                  </div>

                  <div className="grid grid-cols-2 gap-3">
                    <div>
                      <label htmlFor="post-status" className="text-xs font-semibold text-slate-700 block mb-1">
                        Status Publikasi
                      </label>
                      <select
                        id="post-status"
                        value={postForm.status}
                        onChange={(e) => setPostForm({ ...postForm, status: e.target.value as any })}
                        disabled={isSubmittingPost}
                        className="w-full h-9 px-3 text-xs rounded-sm border border-slate-200 bg-white text-slate-700 disabled:opacity-50"
                      >
                        <option value="draft">Simpan Draf</option>
                        <option value="terbit">Langsung Terbitkan</option>
                        <option value="arsip">Arsip</option>
                      </select>
                    </div>
                  </div>

                  <FileUpload
                    label="Unggah Gambar Sampul Berita (Opsional)"
                    accept="image/jpeg,image/png,image/webp"
                    maxSizeMb={5}
                    visibility="publik"
                    purpose="cms_post_cover"
                    value={postForm.cover_file_id}
                    previewUrl={currentCoverUrl}
                    onUploadingChange={setIsUploadingCover}
                    onChange={(id) => {
                      setPostForm({ ...postForm, cover_file_id: id || undefined });
                      if (!id) {
                        setCurrentCoverUrl(null);
                      }
                    }}
                    helperText="Maks. 5 MB (JPG, PNG, WebP)"
                    disabled={isSubmittingPost}
                  />

                  <div className="flex justify-end gap-2 pt-3 border-t">
                    <Button
                      type="button"
                      variant="outline"
                      size="sm"
                      onClick={handleClosePostModal}
                      disabled={isSubmittingPost || isUploadingCover}
                    >
                      Batal
                    </Button>
                    <Button type="submit" size="sm" disabled={isSubmittingPost || isUploadingCover}>
                      {isSubmittingPost
                        ? 'Menyimpan...'
                        : isUploadingCover
                        ? 'Mengunggah Sampul...'
                        : editingPostId
                        ? 'Simpan Perubahan'
                        : 'Simpan Berita'}
                    </Button>
                  </div>
                </form>
              </div>
            </div>
          )}
        </div>
      )}

      {/* =========================================================================
          TAB 3: AGENDA KEGIATAN (ADM-CMS-03)
      ========================================================================= */}
      {activeTab === 'events' && (
        <div className="space-y-6">
          {/* Action & Filter Bar */}
          <div className="flex flex-col sm:flex-row items-center justify-between gap-4">
            <div className="flex flex-wrap items-center gap-3 w-full sm:w-auto">
              <select
                value={eventCategoryFilter}
                onChange={(e) => setEventCategoryFilter(e.target.value as any)}
                className="h-9 px-3 text-xs rounded-sm border border-slate-200 bg-white text-slate-700"
              >
                <option value="all">Semua Kategori Agenda</option>
                <option value="event">Kegiatan / Acara</option>
                <option value="ujian">Ujian &amp; Penilaian</option>
                <option value="libur">Hari Libur</option>
              </select>
              <select
                value={eventVisibilityFilter}
                onChange={(e) => setEventVisibilityFilter(e.target.value as any)}
                className="h-9 px-3 text-xs rounded-sm border border-slate-200 bg-white text-slate-700"
              >
                <option value="all">Semua Visibilitas</option>
                <option value="publik">Publik</option>
                <option value="internal">Internal</option>
              </select>
            </div>
            <Button size="sm" onClick={() => setIsEventModalOpen(true)} className="gap-2 shrink-0">
              <Plus className="h-4 w-4" />
              Tambah Agenda Kegiatan
            </Button>
          </div>

          {/* Table / List */}
          {isLoading ? (
            <Card className="rounded-sm">
              <CardContent className="p-8 text-center text-slate-500">
                <RefreshCw className="h-8 w-8 animate-spin mx-auto mb-3 text-primary" />
                <p>Memuat kalender agenda kegiatan...</p>
              </CardContent>
            </Card>
          ) : events.length === 0 ? (
            <Card className="rounded-sm border-dashed">
              <CardContent className="p-12 text-center">
                <Calendar className="h-12 w-12 mx-auto mb-3 text-slate-300" />
                <h3 className="text-base font-semibold text-slate-900">Belum Ada Agenda Terjadwal</h3>
                <p className="text-xs text-slate-500 max-w-sm mx-auto mt-1 mb-4">
                  Jadwal kegiatan atau kalender akademik belum diisi. Buat agenda baru untuk memberikan informasi kepada siswa dan wali.
                </p>
                <Button size="sm" onClick={() => setIsEventModalOpen(true)} className="gap-2">
                  <Plus className="h-4 w-4" />
                  Tambah Agenda Baru
                </Button>
              </CardContent>
            </Card>
          ) : (
            <div className="overflow-x-auto rounded-sm border border-slate-200 bg-white">
              <table className="w-full text-left text-xs text-slate-600">
                <thead className="bg-slate-50 text-slate-700 uppercase tracking-wider font-semibold border-b border-slate-200">
                  <tr>
                    <th className="px-4 py-3">Nama Agenda</th>
                    <th className="px-4 py-3">Kategori</th>
                    <th className="px-4 py-3">Waktu Mulai</th>
                    <th className="px-4 py-3">Waktu Selesai</th>
                    <th className="px-4 py-3">Visibilitas</th>
                    <th className="px-4 py-3 text-right">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-200">
                  {events.map((item) => (
                    <tr key={item.id} className="hover:bg-slate-50 transition-colors">
                      <td className="px-4 py-3 font-medium text-slate-900">{item.title}</td>
                      <td className="px-4 py-3">
                        <Badge
                          variant={
                            item.category === 'ujian'
                              ? 'danger'
                              : item.category === 'libur'
                              ? 'warning'
                              : 'info'
                          }
                        >
                          {item.category}
                        </Badge>
                      </td>
                      <td className="px-4 py-3">
                        {new Date(item.start_at).toLocaleString('id-ID', {
                          dateStyle: 'medium',
                          timeStyle: 'short',
                        })}
                      </td>
                      <td className="px-4 py-3">
                        {new Date(item.end_at).toLocaleString('id-ID', {
                          dateStyle: 'medium',
                          timeStyle: 'short',
                        })}
                      </td>
                      <td className="px-4 py-3">
                        <Badge variant={item.visibility === 'publik' ? 'info' : 'outline'}>
                          {item.visibility}
                        </Badge>
                      </td>
                      <td className="px-4 py-3 text-right">
                        <Button
                          variant="outline"
                          size="sm"
                          onClick={() => handleDeleteEvent(item.id, item.title)}
                          className="h-7 px-2 text-red-600 hover:text-red-700 hover:bg-red-50 border-slate-200"
                        >
                          <Trash2 className="h-3.5 w-3.5" />
                        </Button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}

          {/* Modal Tambah Agenda */}
          {isEventModalOpen && (
            <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
              <div className="bg-white rounded-sm shadow-xl max-w-lg w-full p-6 space-y-4 animate-in fade-in">
                <div className="flex items-center justify-between border-b pb-3">
                  <h3 className="font-semibold text-slate-900 flex items-center gap-2">
                    <Calendar className="h-4 w-4 text-primary" />
                    Tambah Agenda Kegiatan Baru
                  </h3>
                  <button onClick={() => setIsEventModalOpen(false)} className="text-slate-400 hover:text-slate-600">
                    <X className="h-4 w-4" />
                  </button>
                </div>

                <form onSubmit={handleCreateEvent} className="space-y-4">
                  <div>
                    <label className="text-xs font-semibold text-slate-700 block mb-1">Judul Agenda *</label>
                    <Input
                      required
                      value={eventForm.title}
                      onChange={(e) => setEventForm({ ...eventForm, title: e.target.value })}
                      placeholder="Contoh: Asesmen Sumatif Akhir Semester Genap"
                    />
                  </div>

                  <div className="grid grid-cols-2 gap-3">
                    <div>
                      <label className="text-xs font-semibold text-slate-700 block mb-1">Kategori Agenda *</label>
                      <select
                        value={eventForm.category}
                        onChange={(e) => setEventForm({ ...eventForm, category: e.target.value as any })}
                        className="w-full h-9 px-3 text-xs rounded-sm border border-slate-200 bg-white text-slate-700"
                      >
                        <option value="event">Kegiatan / Acara</option>
                        <option value="ujian">Ujian &amp; Asesmen</option>
                        <option value="libur">Hari Libur Nasional / Sekolah</option>
                      </select>
                    </div>
                    <div>
                      <label className="text-xs font-semibold text-slate-700 block mb-1">Visibilitas</label>
                      <select
                        value={eventForm.visibility}
                        onChange={(e) => setEventForm({ ...eventForm, visibility: e.target.value as any })}
                        className="w-full h-9 px-3 text-xs rounded-sm border border-slate-200 bg-white text-slate-700"
                      >
                        <option value="publik">Publik (Tampil di Kalender Luar)</option>
                        <option value="internal">Internal Sekolah Saja</option>
                      </select>
                    </div>
                  </div>

                  <div className="grid grid-cols-2 gap-3">
                    <div>
                      <label className="text-xs font-semibold text-slate-700 block mb-1">Waktu Mulai *</label>
                      <Input
                        type="datetime-local"
                        required
                        value={eventForm.start_at}
                        onChange={(e) => setEventForm({ ...eventForm, start_at: e.target.value })}
                      />
                    </div>
                    <div>
                      <label className="text-xs font-semibold text-slate-700 block mb-1">Waktu Selesai *</label>
                      <Input
                        type="datetime-local"
                        required
                        value={eventForm.end_at}
                        onChange={(e) => setEventForm({ ...eventForm, end_at: e.target.value })}
                      />
                    </div>
                  </div>

                  <div className="flex justify-end gap-2 pt-3 border-t">
                    <Button type="button" variant="outline" size="sm" onClick={() => setIsEventModalOpen(false)}>
                      Batal
                    </Button>
                    <Button type="submit" size="sm" disabled={isSubmittingEvent}>
                      {isSubmittingEvent ? 'Menyimpan...' : 'Simpan Agenda'}
                    </Button>
                  </div>
                </form>
              </div>
            </div>
          )}
        </div>
      )}

      {/* =========================================================================
          TAB 4: PUSAT UNDUHAN (ADM-CMS-04)
      ========================================================================= */}
      {activeTab === 'downloads' && (
        <div className="space-y-6">
          <div className="flex flex-col sm:flex-row items-center justify-between gap-4">
            <div className="flex flex-wrap items-center gap-3 w-full sm:w-auto">
              <div className="relative w-full sm:w-64">
                <Search className="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                <Input
                  placeholder="Cari berkas unduhan..."
                  value={downloadSearch}
                  onChange={(e) => setDownloadSearch(e.target.value)}
                  className="pl-9 h-9 text-xs"
                />
              </div>
            </div>
            <Button size="sm" onClick={() => setIsDownloadModalOpen(true)} className="gap-2 shrink-0">
              <Plus className="h-4 w-4" />
              Unggah Dokumen Unduhan
            </Button>
          </div>

          {isLoading ? (
            <Card className="rounded-sm">
              <CardContent className="p-8 text-center text-slate-500">
                <RefreshCw className="h-8 w-8 animate-spin mx-auto mb-3 text-primary" />
                <p>Memuat pusat unduhan dokumen...</p>
              </CardContent>
            </Card>
          ) : downloads.length === 0 ? (
            <Card className="rounded-sm border-dashed">
              <CardContent className="p-12 text-center">
                <Download className="h-12 w-12 mx-auto mb-3 text-slate-300" />
                <h3 className="text-base font-semibold text-slate-900">Belum Ada Dokumen Unduhan</h3>
                <p className="text-xs text-slate-500 max-w-sm mx-auto mt-1 mb-4">
                  Pusat unduhan formulir, silabus, dan pedoman sekolah masih kosong. Tambahkan dokumen untuk diunduh publik atau siswa.
                </p>
                <Button size="sm" onClick={() => setIsDownloadModalOpen(true)} className="gap-2">
                  <Plus className="h-4 w-4" />
                  Tambah Dokumen Pertama
                </Button>
              </CardContent>
            </Card>
          ) : (
            <div className="overflow-x-auto rounded-sm border border-slate-200 bg-white">
              <table className="w-full text-left text-xs text-slate-600">
                <thead className="bg-slate-50 text-slate-700 uppercase tracking-wider font-semibold border-b border-slate-200">
                  <tr>
                    <th className="px-4 py-3">Nama Berkas Dokumen</th>
                    <th className="px-4 py-3">Kategori</th>
                    <th className="px-4 py-3">Visibilitas</th>
                    <th className="px-4 py-3">Jumlah Unduh</th>
                    <th className="px-4 py-3 text-right">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-200">
                  {downloads.map((item) => (
                    <tr key={item.id} className="hover:bg-slate-50 transition-colors">
                      <td className="px-4 py-3 font-medium text-slate-900">{item.title}</td>
                      <td className="px-4 py-3">{item.category}</td>
                      <td className="px-4 py-3">
                        <Badge variant={item.visibility === 'publik' ? 'info' : 'outline'}>
                          {item.visibility}
                        </Badge>
                      </td>
                      <td className="px-4 py-3">{item.download_count ?? 0} kali</td>
                      <td className="px-4 py-3 text-right">
                        <Button
                          variant="outline"
                          size="sm"
                          onClick={() => handleDeleteDownload(item.id, item.title)}
                          className="h-7 px-2 text-red-600 hover:text-red-700 hover:bg-red-50 border-slate-200"
                        >
                          <Trash2 className="h-3.5 w-3.5" />
                        </Button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}

          {/* Modal Tambah Download */}
          {isDownloadModalOpen && (
            <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
              <div className="bg-white rounded-sm shadow-xl max-w-md w-full p-6 space-y-4 animate-in fade-in">
                <div className="flex items-center justify-between border-b pb-3">
                  <h3 className="font-semibold text-slate-900 flex items-center gap-2">
                    <Download className="h-4 w-4 text-primary" />
                    Tambah Dokumen Unduhan Baru
                  </h3>
                  <button onClick={() => setIsDownloadModalOpen(false)} className="text-slate-400 hover:text-slate-600">
                    <X className="h-4 w-4" />
                  </button>
                </div>

                <form onSubmit={handleCreateDownload} className="space-y-4">
                  <div>
                    <label className="text-xs font-semibold text-slate-700 block mb-1">Judul Dokumen *</label>
                    <Input
                      required
                      value={downloadForm.title}
                      onChange={(e) => setDownloadForm({ ...downloadForm, title: e.target.value })}
                      placeholder="Contoh: Formulir Pendaftaran Ekstrakurikuler"
                    />
                  </div>

                  <div>
                    <label className="text-xs font-semibold text-slate-700 block mb-1">Kategori Dokumen *</label>
                    <Input
                      required
                      value={downloadForm.category}
                      onChange={(e) => setDownloadForm({ ...downloadForm, category: e.target.value })}
                      placeholder="Contoh: Formulir, Pedoman, Kalender"
                    />
                  </div>

                  <FileUpload
                    label="Unggah Berkas Dokumen Unduhan *"
                    accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip"
                    maxSizeMb={25}
                    visibility={downloadForm.visibility as any}
                    purpose="cms_download"
                    value={downloadForm.file_id}
                    onChange={(id) => setDownloadForm({ ...downloadForm, file_id: id || '' })}
                    helperText="Maks. 25 MB (PDF, Office, ZIP)"
                  />

                  <div>
                    <label className="text-xs font-semibold text-slate-700 block mb-1">Visibilitas</label>
                    <select
                      value={downloadForm.visibility}
                      onChange={(e) => setDownloadForm({ ...downloadForm, visibility: e.target.value as any })}
                      className="w-full h-9 px-3 text-xs rounded-sm border border-slate-200 bg-white text-slate-700"
                    >
                      <option value="publik">Publik (Bisa Diunduh Bebas)</option>
                      <option value="internal">Internal (Hanya Pengguna Login)</option>
                    </select>
                  </div>

                  <div className="flex justify-end gap-2 pt-3 border-t">
                    <Button type="button" variant="outline" size="sm" onClick={() => setIsDownloadModalOpen(false)}>
                      Batal
                    </Button>
                    <Button type="submit" size="sm" disabled={isSubmittingDownload}>
                      {isSubmittingDownload ? 'Menyimpan...' : 'Simpan Dokumen'}
                    </Button>
                  </div>
                </form>
              </div>
            </div>
          )}
        </div>
      )}

      {/* =========================================================================
          TAB 5: GALERI & MEDIA (ADM-CMS-05)
      ========================================================================= */}
      {activeTab === 'albums' && (
        <div className="space-y-6">
          <div className="flex flex-col sm:flex-row items-center justify-between gap-4">
            <div className="relative w-full sm:w-64">
              <Search className="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
              <Input
                placeholder="Cari album foto..."
                value={albumSearch}
                onChange={(e) => setAlbumSearch(e.target.value)}
                className="pl-9 h-9 text-xs"
              />
            </div>
            <Button size="sm" onClick={() => setIsAlbumModalOpen(true)} className="gap-2 shrink-0">
              <Plus className="h-4 w-4" />
              Buat Album Baru
            </Button>
          </div>

          {isLoading ? (
            <Card className="rounded-sm">
              <CardContent className="p-8 text-center text-slate-500">
                <RefreshCw className="h-8 w-8 animate-spin mx-auto mb-3 text-primary" />
                <p>Memuat koleksi galeri foto...</p>
              </CardContent>
            </Card>
          ) : albums.length === 0 ? (
            <Card className="rounded-sm border-dashed">
              <CardContent className="p-12 text-center">
                <ImageIcon className="h-12 w-12 mx-auto mb-3 text-slate-300" />
                <h3 className="text-base font-semibold text-slate-900">Belum Ada Album Foto</h3>
                <p className="text-xs text-slate-500 max-w-sm mx-auto mt-1 mb-4">
                  Dokumentasi kegiatan dan galeri foto sekolah belum dibuat. Buat album foto untuk mempublikasikan kegiatan sekolah.
                </p>
                <Button size="sm" onClick={() => setIsAlbumModalOpen(true)} className="gap-2">
                  <Plus className="h-4 w-4" />
                  Buat Album Pertama
                </Button>
              </CardContent>
            </Card>
          ) : (
            <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6">
              {albums.map((album) => {
                const photoCount = album.items_count ?? album.photos_count ?? 0;
                return (
                  <Card key={album.id} className="rounded-sm overflow-hidden hover:shadow-md transition-shadow flex flex-col justify-between">
                    <div>
                      <div className="h-40 bg-slate-100 relative flex items-center justify-center border-b border-slate-200 overflow-hidden">
                        {album.cover?.url ? (
                          /* eslint-disable-next-line @next/next/no-img-element */
                          <img
                            src={album.cover.url}
                            alt={`Sampul ${album.title}`}
                            className="w-full h-full object-cover"
                            onError={(e) => {
                              e.currentTarget.style.display = 'none';
                            }}
                          />
                        ) : (
                          <div className="flex flex-col items-center justify-center gap-1 text-slate-400">
                            <ImageIcon className="h-10 w-10" />
                            <span className="text-[10px]">Tanpa Sampul</span>
                          </div>
                        )}
                        <Badge className="absolute bottom-2 right-2 bg-slate-900/80 text-white text-[11px]">
                          {photoCount} Foto
                        </Badge>
                      </div>
                      <CardContent className="p-4 space-y-2">
                        <h4 className="font-semibold text-sm text-slate-900 line-clamp-2">{album.title}</h4>
                        <p className="text-xs text-slate-500">
                          Dibuat pada {album.created_at ? new Date(album.created_at).toLocaleDateString('id-ID') : '-'}
                        </p>
                      </CardContent>
                    </div>
                    <div className="p-4 pt-0 border-t border-slate-100 flex items-center justify-between gap-2 mt-2">
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => handleOpenMediaManager(album)}
                        className="flex-1 text-xs gap-1.5 h-8 border-slate-200 hover:bg-primary/5 hover:text-primary hover:border-primary/30"
                      >
                        <Images className="h-3.5 w-3.5" />
                        Kelola Foto ({photoCount})
                      </Button>
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => handleDeleteAlbum(album.id, album.title)}
                        className="h-8 w-8 p-0 text-red-600 hover:text-red-700 hover:bg-red-50 border-slate-200 shrink-0"
                        title="Hapus Album"
                        aria-label="Hapus Album"
                      >
                        <Trash2 className="h-3.5 w-3.5" />
                      </Button>
                    </div>
                  </Card>
                );
              })}
            </div>
          )}

          {/* Modal Buat Album */}
          {isAlbumModalOpen && (
            <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
              <div className="bg-white rounded-sm shadow-xl max-w-md w-full p-6 space-y-4 animate-in fade-in">
                <div className="flex items-center justify-between border-b pb-3">
                  <h3 className="font-semibold text-slate-900 flex items-center gap-2">
                    <ImageIcon className="h-4 w-4 text-primary" />
                    Buat Album Foto Baru
                  </h3>
                  <button onClick={() => setIsAlbumModalOpen(false)} className="text-slate-400 hover:text-slate-600">
                    <X className="h-4 w-4" />
                  </button>
                </div>

                <form onSubmit={handleCreateAlbum} className="space-y-4">
                  <div>
                    <label className="text-xs font-semibold text-slate-700 block mb-1">Judul Album *</label>
                    <Input
                      required
                      value={albumForm.title}
                      onChange={(e) => setAlbumForm({ ...albumForm, title: e.target.value })}
                      placeholder="Contoh: Peringatan Hari Guru Nasional 2026"
                    />
                  </div>

                  <FileUpload
                    label="Unggah Foto Sampul Album (Opsional)"
                    accept="image/jpeg,image/png,image/webp"
                    maxSizeMb={5}
                    visibility="publik"
                    purpose="cms_album_cover"
                    value={albumForm.cover_file_id}
                    onChange={(id) => setAlbumForm({ ...albumForm, cover_file_id: id || undefined })}
                    helperText="Maks. 5 MB (JPG, PNG, WebP)"
                  />

                  <div className="flex justify-end gap-2 pt-3 border-t">
                    <Button type="button" variant="outline" size="sm" onClick={() => setIsAlbumModalOpen(false)}>
                      Batal
                    </Button>
                    <Button type="submit" size="sm" disabled={isSubmittingAlbum}>
                      {isSubmittingAlbum ? 'Membuat...' : 'Buat Album'}
                    </Button>
                  </div>
                </form>
              </div>
            </div>
          )}

          {/* Modal Kelola Media Album (INT-01) */}
          {selectedAlbum && (
            <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
              <div className="bg-white rounded-sm shadow-xl max-w-3xl w-full p-6 space-y-6 max-h-[90vh] overflow-y-auto animate-in fade-in">
                {/* Header */}
                <div className="flex items-center justify-between border-b pb-4">
                  <div className="space-y-1">
                    <div className="flex items-center gap-2">
                      <Images className="h-5 w-5 text-primary" />
                      <h3 className="font-semibold text-base text-slate-900">
                        Kelola Media Album: {selectedAlbum.title}
                      </h3>
                      <Badge variant="outline" className="text-xs">
                        {albumDetail?.items?.length ?? selectedAlbum.photos_count ?? 0} Foto
                      </Badge>
                    </div>
                    <p className="text-xs text-slate-500">
                      Unggah dokumentasi foto kegiatan untuk dipublikasikan pada galeri publik.
                    </p>
                  </div>
                  <button
                    onClick={handleCloseMediaManager}
                    className="text-slate-400 hover:text-slate-600 p-1"
                    aria-label="Tutup modal"
                  >
                    <X className="h-5 w-5" />
                  </button>
                </div>

                {/* Bagian 1: Form Unggah Foto Baru ke Album */}
                <Card className="rounded-sm bg-slate-50/50 border-slate-200">
                  <CardHeader className="py-3 px-4 border-b border-slate-200">
                    <CardTitle className="text-xs font-semibold text-slate-800 flex items-center gap-2">
                      <UploadCloud className="h-4 w-4 text-primary" />
                      Unggah Foto Baru ke Album Ini
                    </CardTitle>
                  </CardHeader>
                  <CardContent className="p-4">
                    <form onSubmit={handleAddMediaToAlbum} className="space-y-4">
                      <FileUpload
                        label="Pilih Berkas Foto Kegiatan *"
                        accept="image/jpeg,image/png,image/webp"
                        maxSizeMb={5}
                        visibility="publik"
                        purpose="cms_album_media"
                        value={mediaFileId}
                        onChange={(id) => setMediaFileId(id)}
                        helperText="Format didukung: JPG, PNG, WebP (Maksimal 5 MB per foto)"
                      />

                      <div>
                        <label className="text-xs font-semibold text-slate-700 block mb-1">
                          Keterangan / Caption Foto (Opsional)
                        </label>
                        <Input
                          value={mediaCaption}
                          onChange={(e) => setMediaCaption(e.target.value)}
                          placeholder="Contoh: Sambutan ketua panitia di aula utama..."
                          className="text-xs"
                        />
                      </div>

                      <div className="flex justify-end pt-2">
                        <Button
                          type="submit"
                          size="sm"
                          disabled={isUploadingMedia || !mediaFileId}
                          className="gap-2"
                        >
                          {isUploadingMedia ? (
                            <>
                              <RefreshCw className="h-3.5 w-3.5 animate-spin" />
                              Menyimpan Foto...
                            </>
                          ) : (
                            <>
                              <Plus className="h-3.5 w-3.5" />
                              Tambahkan Foto ke Album
                            </>
                          )}
                        </Button>
                      </div>
                    </form>
                  </CardContent>
                </Card>

                {/* Bagian 2: Daftar Foto dalam Album */}
                <div className="space-y-3">
                  <div className="flex items-center justify-between">
                    <h4 className="text-xs font-bold uppercase tracking-wider text-slate-700 flex items-center gap-2">
                      <FolderOpen className="h-4 w-4 text-primary" />
                      Foto dalam Album ({albumDetail?.items?.length ?? 0})
                    </h4>
                    <Button
                      type="button"
                      variant="ghost"
                      size="sm"
                      onClick={() => fetchAlbumDetail(selectedAlbum.id)}
                      disabled={isLoadingDetail}
                      className="h-7 text-xs gap-1 text-slate-500 hover:text-slate-800"
                    >
                      <RefreshCw className={`h-3 w-3 ${isLoadingDetail ? 'animate-spin' : ''}`} />
                      Segarkan
                    </Button>
                  </div>

                  {isLoadingDetail ? (
                    <div className="p-8 text-center text-slate-500 bg-slate-50 rounded border border-slate-200">
                      <RefreshCw className="h-6 w-6 animate-spin mx-auto mb-2 text-primary" />
                      <p className="text-xs">Memuat foto-foto dalam album...</p>
                    </div>
                  ) : detailError ? (
                    <div className="p-6 text-center text-red-600 bg-red-50 rounded border border-red-200 space-y-2">
                      <AlertCircle className="h-6 w-6 mx-auto text-red-500" />
                      <p className="text-xs">{detailError}</p>
                      <Button
                        size="sm"
                        variant="outline"
                        onClick={() => fetchAlbumDetail(selectedAlbum.id)}
                        className="text-xs"
                      >
                        Coba Lagi
                      </Button>
                    </div>
                  ) : !albumDetail?.items || albumDetail.items.length === 0 ? (
                    <div className="p-8 text-center text-slate-400 bg-slate-50 rounded border border-dashed border-slate-200">
                      <ImageIcon className="h-10 w-10 mx-auto mb-2 text-slate-300" />
                      <p className="text-xs font-semibold text-slate-600">Belum Ada Foto dalam Album Ini</p>
                      <p className="text-[11px] text-slate-400 mt-0.5">
                        Gunakan form di atas untuk mengunggah foto dokumentasi pertama ke album ini.
                      </p>
                    </div>
                  ) : (
                    <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                      {albumDetail.items.map((item) => (
                        <div
                          key={item.id}
                          className="bg-white border border-slate-200 rounded-sm overflow-hidden group shadow-xs hover:shadow-md transition-shadow flex flex-col justify-between"
                        >
                          <div className="relative aspect-video bg-slate-100 flex items-center justify-center overflow-hidden">
                            {item.file?.url ? (
                              /* eslint-disable-next-line @next/next/no-img-element */
                              <img
                                src={item.file.url}
                                alt={item.caption || 'Foto kegiatan'}
                                className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-200"
                                onError={(e) => {
                                  e.currentTarget.style.display = 'none';
                                }}
                              />
                            ) : (
                              <ImageIcon className="h-8 w-8 text-slate-300" />
                            )}
                            <button
                              type="button"
                              onClick={() => handleDeleteMediaItem(item.id, item.caption)}
                              className="absolute top-1.5 right-1.5 h-6 w-6 rounded bg-black/60 text-white hover:bg-red-600 flex items-center justify-center transition-colors shadow-xs"
                              title="Hapus foto dari album"
                              aria-label="Hapus foto"
                            >
                              <Trash2 className="h-3.5 w-3.5" />
                            </button>
                          </div>
                          <div className="p-2 space-y-1">
                            <p className="text-[11px] text-slate-700 line-clamp-2 leading-tight">
                              {item.caption || <span className="text-slate-400 italic">Tanpa keterangan</span>}
                            </p>
                            <p className="text-[10px] text-slate-400">
                              {item.created_at ? new Date(item.created_at).toLocaleDateString('id-ID') : '-'}
                            </p>
                          </div>
                        </div>
                      ))}
                    </div>
                  )}
                </div>

                {/* Footer */}
                <div className="flex justify-end pt-4 border-t">
                  <Button variant="outline" size="sm" onClick={handleCloseMediaManager}>
                    Tutup
                  </Button>
                </div>
              </div>
            </div>
          )}
        </div>
      )}

      {/* Dialog Konfirmasi Hapus CMS */}
      <ConfirmDialog
        isOpen={deleteConfirm.isOpen}
        onClose={() => setDeleteConfirm((prev) => ({ ...prev, isOpen: false }))}
        onConfirm={async () => {
          setDeleteConfirm((prev) => ({ ...prev, isLoading: true }));
          try {
            await deleteConfirm.onConfirm();
            setDeleteConfirm((prev) => ({ ...prev, isOpen: false, isLoading: false }));
          } catch {
            setDeleteConfirm((prev) => ({ ...prev, isLoading: false }));
          }
        }}
        title={deleteConfirm.title}
        description={deleteConfirm.description}
        affectedItemName={deleteConfirm.itemLabel}
        confirmLabel="Ya, Hapus"
        cancelLabel="Batal"
        variant="danger"
        isLoading={deleteConfirm.isLoading}
      />
    </div>
  );
}
