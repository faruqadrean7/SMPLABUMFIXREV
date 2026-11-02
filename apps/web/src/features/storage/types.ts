export interface PresignPayload {
  filename: string;
  mime_type: string;
  size_bytes: number;
  visibility?: 'publik' | 'privat';
  purpose?: string;
  bucket?: string;
}

export interface PresignResponse {
  token: string;
  upload_url: string;
  method: string;
  bucket: string;
  object_key: string;
  visibility: 'publik' | 'privat';
  mime_type: string;
  size_bytes: number;
  expires_at: string;
  ttl_seconds: number;
}

export interface UploadSignedResponse {
  message: string;
  token: string;
  object_key: string;
  checksum: string;
  size_bytes: number;
}

export interface ConfirmPayload {
  token: string;
  checksum?: string;
  owner_id?: string;
  owner_type?: string;
}

export interface FileItem {
  id: string;
  school_id?: string | null;
  owner_id?: string | null;
  owner_type?: string | null;
  bucket: string;
  object_key: string;
  mime_type: string;
  size_bytes: number;
  visibility: 'publik' | 'privat';
  checksum?: string | null;
  created_at: string;
  updated_at?: string;
  url?: string;
  download_url?: string;
}

export interface FileUrlResponse {
  id: string;
  url: string;
  visibility: 'publik' | 'privat';
  mime_type: string;
  size_bytes: number;
  expires_at: string | null;
  ttl_seconds: number | null;
}
