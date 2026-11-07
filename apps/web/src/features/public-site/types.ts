/**
 * Definisi tipe data untuk Website Publik SMP Laboratorium UM
 */

export interface NavItem {
  label: string;
  href: string;
  isExternal?: boolean;
}

export interface QuickLinkItem {
  label: string;
  href: string;
}

export interface ContactInfo {
  schoolName: string;
  affiliation: string;
  address: string;
  phone: {
    display: string;
    tel: string;
  };
  whatsapp: {
    display: string;
    url: string;
  };
  email: {
    display: string;
    mailto: string;
  };
  copyright: string;
}

export interface PublicEventItem {
  id: string;
  title: string;
  category: 'ujian' | 'libur' | 'event' | string;
  visibility: 'publik' | 'internal' | string;
  start_at: string;
  end_at: string;
  school_id?: string | null;
  created_at?: string;
  updated_at?: string;
}

export interface GetPublicEventsParams {
  category?: string;
  search?: string;
  month?: number;
  year?: number;
  start_date?: string;
  end_date?: string;
  page?: number;
  per_page?: number;
  all?: boolean;
}

export interface PublicDownloadFile {
  id: string;
  bucket?: string;
  object_key?: string;
  mime_type?: string;
  size_bytes?: number;
  visibility?: 'publik' | 'privat' | string;
  url?: string;
  download_url?: string;
  checksum?: string | null;
  created_at?: string;
}

export interface PublicDownloadItem {
  id: string;
  title: string;
  category: string;
  visibility: 'publik' | 'internal' | string;
  file_id: string;
  file?: PublicDownloadFile | null;
  download_count?: number;
  school_id?: string | null;
  created_at?: string;
  updated_at?: string;
}

export interface GetPublicDownloadsParams {
  category?: string;
  search?: string;
  page?: number;
  per_page?: number;
  all?: boolean;
}

export interface PublicBulletinFile {
  id: string;
  bucket?: string;
  object_key?: string;
  mime_type?: string;
  size_bytes?: number;
  visibility?: string;
  url?: string;
  download_url?: string;
}

export interface PublicBulletinItem {
  id: string;
  title: string;
  file_id: string;
  cover_file_id?: string | null;
  school_id?: string | null;
  created_at?: string;
  updated_at?: string;
  file?: PublicBulletinFile | null;
  cover?: {
    id: string;
    object_key?: string;
    url?: string;
    download_url?: string;
  } | null;
}

export interface GetPublicBulletinsParams {
  search?: string;
  page?: number;
  per_page?: number;
}

