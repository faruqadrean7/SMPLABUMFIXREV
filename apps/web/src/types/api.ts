/**
 * Tipe data respon standar REST API Laravel SISTLABUM
 */

export interface ApiResponse<T> {
  data: T;
  meta?: {
    timestamp?: string;
    [key: string]: unknown;
  };
}

export interface ApiErrorDetail {
  field?: string;
  message: string;
}

export interface ApiErrorPayload {
  code: string;
  message: string;
  details?: ApiErrorDetail[];
  requestId?: string;
}

export interface ApiErrorResponse {
  error: ApiErrorPayload;
}

export interface PaginatedMeta {
  page: number;
  limit: number;
  total: number;
  totalPages: number;
}

export interface PaginatedResponse<T> {
  data: T[];
  meta: PaginatedMeta;
}
