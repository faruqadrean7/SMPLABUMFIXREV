export interface SiteIdentity {
  name?: string;
  tagline?: string;
  logo_url?: string;
  favicon_url?: string;
}

export interface SiteContact {
  phone?: string;
  email?: string;
  whatsapp?: string;
}

export interface SiteAddress {
  street?: string;
  city?: string;
  postal_code?: string;
  latitude?: number;
  longitude?: number;
  maps_embed_url?: string;
}

export interface SiteSocialMedia {
  instagram?: string;
  youtube?: string;
  facebook?: string;
  twitter?: string;
  tiktok?: string;
}

export interface PrincipalGreeting {
  principal_name?: string;
  principal_photo_url?: string;
  greeting_text?: string;
}

export interface Accreditation {
  grade?: string;
  score?: number;
  certificate_no?: string;
  valid_until?: string;
}

export interface FeaturedProgram {
  title: string;
  description?: string;
  icon?: string;
}

export interface SiteProfile {
  identity?: SiteIdentity;
  contact?: SiteContact;
  address?: SiteAddress;
  social_media?: SiteSocialMedia;
  profile?: {
    vision?: string;
    mission?: string[];
    principal_greeting?: PrincipalGreeting;
    accreditation?: Accreditation;
    featured_programs?: FeaturedProgram[];
  };
}

export interface SiteSettingsPayload {
  identity?: SiteIdentity;
  contact?: SiteContact;
  address?: SiteAddress;
  social_media?: SiteSocialMedia;
  profile?: {
    vision?: string;
    mission?: string[];
    principal_greeting?: PrincipalGreeting;
    accreditation?: Accreditation;
    featured_programs?: FeaturedProgram[];
  };
}

export interface PostCategoryItem {
  id: string;
  name: string;
  slug: string;
  school_id?: string;
  created_at?: string;
  updated_at?: string;
}

export interface PostItem {
  id: string;
  title: string;
  slug: string;
  content: string;
  category_id?: string;
  cover_file_id?: string | null;
  cover_file?: {
    id: string;
    url?: string;
    object_key?: string;
    mime_type?: string;
    size_bytes?: number;
  } | null;
  coverFile?: {
    id: string;
    url?: string;
    object_key?: string;
    mime_type?: string;
    size_bytes?: number;
  } | null;
  cover?: {
    id: string;
    url?: string;
    name?: string;
  } | null;
  status: 'draft' | 'terbit' | 'arsip';
  visibility: 'publik' | 'internal';
  published_at?: string;
  school_id?: string;
  category?: PostCategoryItem;
  created_at?: string;
  updated_at?: string;
}

export interface CreatePostPayload {
  title: string;
  slug?: string;
  content: string;
  category_id?: string;
  cover_file_id?: string;
  status: 'draft' | 'terbit' | 'arsip';
  visibility: 'publik' | 'internal';
  published_at?: string;
  school_id?: string;
}

export interface UpdatePostPayload extends Partial<CreatePostPayload> {}

export interface SchoolEventItem {
  id: string;
  title: string;
  category: 'ujian' | 'libur' | 'event';
  visibility: 'publik' | 'internal';
  start_at: string;
  end_at: string;
  school_id?: string;
  created_at?: string;
  updated_at?: string;
}

export interface CreateEventPayload {
  title: string;
  category: 'ujian' | 'libur' | 'event';
  visibility: 'publik' | 'internal';
  start_at: string;
  end_at: string;
  school_id?: string;
}

export interface UpdateEventPayload extends Partial<CreateEventPayload> {}

export interface DownloadItem {
  id: string;
  title: string;
  category: string;
  visibility: 'publik' | 'internal';
  file_id: string;
  download_count?: number;
  school_id?: string;
  created_at?: string;
  updated_at?: string;
}

export interface CreateDownloadPayload {
  title: string;
  category: string;
  visibility: 'publik' | 'internal';
  file_id: string;
  school_id?: string;
}

export interface UpdateDownloadPayload extends Partial<CreateDownloadPayload> {}

export interface MediaItem {
  id: string;
  album_id: string;
  file_id: string;
  media_type: 'image' | 'video';
  caption?: string | null;
  file?: {
    id: string;
    url?: string;
    name?: string;
    mime_type?: string;
    size_bytes?: number;
  };
  created_at?: string;
  updated_at?: string;
}

export interface CreateMediaItemPayload {
  album_id: string;
  file_id: string;
  media_type: 'image' | 'video';
  caption?: string;
}

export interface AlbumItem {
  id: string;
  title: string;
  cover_file_id?: string;
  photos_count?: number;
  items_count?: number;
  school_id?: string;
  cover?: {
    id: string;
    url?: string;
    name?: string;
  } | null;
  items?: MediaItem[];
  created_at?: string;
  updated_at?: string;
}

export interface CreateAlbumPayload {
  title: string;
  cover_file_id?: string;
  school_id?: string;
}

export interface UpdateAlbumPayload extends Partial<CreateAlbumPayload> {}

export interface PageItem {
  id: string;
  title: string;
  slug: string;
  content: string;
  status: 'draft' | 'terbit';
  school_id?: string;
  created_at?: string;
  updated_at?: string;
}

export interface CreatePagePayload {
  title: string;
  slug?: string;
  content: string;
  status: 'draft' | 'terbit';
  school_id?: string;
}

export interface UpdatePagePayload extends Partial<CreatePagePayload> {}

export interface BulletinItem {
  id: string;
  title: string;
  file_id: string;
  cover_file_id?: string | null;
  school_id?: string | null;
  created_at?: string;
  updated_at?: string;
  file?: {
    id: string;
    object_key?: string;
    mime_type?: string;
    size_bytes?: number;
    url?: string;
    download_url?: string;
  } | null;
  cover?: {
    id: string;
    object_key?: string;
    url?: string;
    download_url?: string;
  } | null;
}

export interface CreateBulletinPayload {
  title: string;
  file_id: string;
  cover_file_id?: string | null;
  school_id?: string;
}

export interface UpdateBulletinPayload extends Partial<CreateBulletinPayload> {}

