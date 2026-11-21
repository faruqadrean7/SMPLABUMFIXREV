import { describe, it, expect, beforeEach } from 'vitest';
import { ApiClientError } from '@/lib/api/errors';
import { tokenStorage } from '@/lib/auth/token-storage';

describe('API Client Error Handling & Token Storage', () => {
  beforeEach(() => {
    tokenStorage.clearAll();
  });

  it('manages access token and refresh token storage correctly', () => {
    expect(tokenStorage.getAccessToken()).toBeNull();
    expect(tokenStorage.getRefreshToken()).toBeNull();

    tokenStorage.setTokens('access-token-123', 'refresh-token-456');
    expect(tokenStorage.getAccessToken()).toBe('access-token-123');
    expect(tokenStorage.getRefreshToken()).toBe('refresh-token-456');

    tokenStorage.clearAll();
    expect(tokenStorage.getAccessToken()).toBeNull();
    expect(tokenStorage.getRefreshToken()).toBeNull();
  });

  it('correctly categorizes ApiClientError instances', () => {
    const valError = new ApiClientError(
      'Data tidak lengkap',
      'VALIDATION_FAILED',
      422,
      [{ field: 'identifier', message: 'Wajib diisi' }]
    );
    expect(valError.isValidationError()).toBe(true);
    expect(valError.isUnauthorized()).toBe(false);
    expect(valError.getFieldError('identifier')).toBe('Wajib diisi');
    expect(valError.getFieldError('password')).toBeUndefined();

    const authError = new ApiClientError('Kredensial tidak cocok', 'UNAUTHENTICATED', 401);
    expect(authError.isUnauthorized()).toBe(true);
    expect(authError.isValidationError()).toBe(false);

    const forbiddenError = new ApiClientError('Akses ditolak', 'FORBIDDEN', 403);
    expect(forbiddenError.isForbidden()).toBe(true);

    const rateError = new ApiClientError('Terlalu banyak request', 'RATE_LIMITED', 429);
    expect(rateError.isRateLimited()).toBe(true);
  });
});
