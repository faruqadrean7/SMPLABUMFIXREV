import { ApiErrorPayload, ApiErrorDetail } from '@/types/api';

/**
 * Custom Error class untuk penanganan terstruktur error dari REST API
 */
export class ApiClientError extends Error {
  public readonly code: string;
  public readonly statusCode: number;
  public readonly details: ApiErrorDetail[];
  public readonly requestId?: string;

  constructor(
    message: string,
    code: string = 'UNKNOWN_ERROR',
    statusCode: number = 500,
    details: ApiErrorDetail[] = [],
    requestId?: string
  ) {
    super(message);
    this.name = 'ApiClientError';
    this.code = code;
    this.statusCode = statusCode;
    this.details = details;
    this.requestId = requestId;

    // Pertahankan prototipe bawaan Error
    Object.setPrototypeOf(this, ApiClientError.prototype);
  }

  /**
   * Cek apakah error merupakan error validasi input
   */
  public isValidationError(): boolean {
    return this.statusCode === 422 || this.code === 'VALIDATION_FAILED';
  }

  /**
   * Cek apakah error merupakan masalah autentikasi (401)
   */
  public isUnauthorized(): boolean {
    return this.statusCode === 401 || this.code === 'UNAUTHENTICATED';
  }

  /**
   * Cek apakah error merupakan pembatasan hak akses (403)
   */
  public isForbidden(): boolean {
    return this.statusCode === 403 || this.code === 'FORBIDDEN';
  }

  /**
   * Cek apakah terkena rate limiting (429)
   */
  public isRateLimited(): boolean {
    return this.statusCode === 429 || this.code === 'RATE_LIMITED';
  }

  /**
   * Dapatkan pesan error pertama untuk field form tertentu
   */
  public getFieldError(fieldName: string): string | undefined {
    const detail = this.details.find((d) => d.field === fieldName);
    return detail?.message;
  }
}
