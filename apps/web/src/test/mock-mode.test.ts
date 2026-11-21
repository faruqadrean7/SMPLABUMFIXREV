import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { executeWithMockFallback, isMockEnabled } from '@/lib/api/mock-mode';
import { ApiClientError } from '@/lib/api/errors';

describe('GAP-03: Centralized Mock Mode & Strict Error Propagation', () => {
  const originalEnv = process.env;

  beforeEach(() => {
    vi.clearAllMocks();
    process.env = { ...originalEnv };
  });

  afterEach(() => {
    process.env = originalEnv;
  });

  describe('1. isMockEnabled() evaluation', () => {
    it('returns false in production regardless of NEXT_PUBLIC_USE_MOCK', () => {
      (process.env as any).NODE_ENV = 'production';
      process.env.NEXT_PUBLIC_USE_MOCK = 'true';
      expect(isMockEnabled()).toBe(false);
    });

    it('returns false in development if NEXT_PUBLIC_USE_MOCK is not "true"', () => {
      (process.env as any).NODE_ENV = 'development';
      delete process.env.NEXT_PUBLIC_USE_MOCK;
      expect(isMockEnabled()).toBe(false);

      process.env.NEXT_PUBLIC_USE_MOCK = 'false';
      expect(isMockEnabled()).toBe(false);
    });

    it('returns true ONLY when NODE_ENV !== "production" AND NEXT_PUBLIC_USE_MOCK === "true"', () => {
      (process.env as any).NODE_ENV = 'development';
      process.env.NEXT_PUBLIC_USE_MOCK = 'true';
      expect(isMockEnabled()).toBe(true);
    });
  });

  describe('2. HTTP Status Errors (401, 403, 404, 422, 5xx) must NEVER fallback to mock data', () => {
    const mockLoader = vi.fn().mockResolvedValue([{ id: 'mock-1', title: 'Data Palsu' }]);

    it('throws HTTP 403 Forbidden without calling mockLoader even if mock mode is on', async () => {
      (process.env as any).NODE_ENV = 'development';
      process.env.NEXT_PUBLIC_USE_MOCK = 'true';

      const forbiddenError: any = new Error('Akses Ditolak');
      forbiddenError.response = { status: 403, data: { message: 'Forbidden' } };

      const apiCall = vi.fn().mockRejectedValue(forbiddenError);

      await expect(executeWithMockFallback(apiCall, mockLoader)).rejects.toMatchObject({
        response: { status: 403 },
      });
      expect(mockLoader).not.toHaveBeenCalled();
    });

    it('throws HTTP 401 Unauthorized without calling mockLoader', async () => {
      (process.env as any).NODE_ENV = 'development';
      process.env.NEXT_PUBLIC_USE_MOCK = 'true';

      const unauthError: any = new Error('Unauthenticated');
      unauthError.response = { status: 401, data: { message: 'Token Expired' } };

      const apiCall = vi.fn().mockRejectedValue(unauthError);

      await expect(executeWithMockFallback(apiCall, mockLoader)).rejects.toMatchObject({
        response: { status: 401 },
      });
      expect(mockLoader).not.toHaveBeenCalled();
    });

    it('throws HTTP 404 and HTTP 500 without masking errors with mock data', async () => {
      (process.env as any).NODE_ENV = 'development';
      process.env.NEXT_PUBLIC_USE_MOCK = 'true';

      const serverError: any = new Error('Internal Server Error');
      serverError.response = { status: 500 };

      const apiCall = vi.fn().mockRejectedValue(serverError);

      await expect(executeWithMockFallback(apiCall, mockLoader)).rejects.toMatchObject({
        response: { status: 500 },
      });
      expect(mockLoader).not.toHaveBeenCalled();
    });
  });

  describe('3. Production Mode Enforcement (No Fallback Whatsoever)', () => {
    it('throws network errors in production and never invokes mockLoader', async () => {
      (process.env as any).NODE_ENV = 'production';
      process.env.NEXT_PUBLIC_USE_MOCK = 'true'; // Ignored in production

      const mockLoader = vi.fn().mockResolvedValue([{ id: 'mock-secret' }]);
      const networkError: any = new Error('Network Timeout');
      networkError.code = 'ECONNABORTED';

      const apiCall = vi.fn().mockRejectedValue(networkError);

      await expect(executeWithMockFallback(apiCall, mockLoader)).rejects.toThrow('Network Timeout');
      expect(mockLoader).not.toHaveBeenCalled();
    });
  });

  describe('4. Explicit Mock Mode (Development + NEXT_PUBLIC_USE_MOCK === "true")', () => {
    it('falls back to mockLoader ONLY on true network error (no HTTP response status)', async () => {
      (process.env as any).NODE_ENV = 'development';
      process.env.NEXT_PUBLIC_USE_MOCK = 'true';

      const mockData = [{ id: 'mock-dev-1', name: 'Mock Dev Data' }];
      const mockLoader = vi.fn().mockResolvedValue(mockData);

      // Network failure without HTTP status response
      const networkDrop: any = new Error('Network Error');
      networkDrop.response = undefined;

      const apiCall = vi.fn().mockRejectedValue(networkDrop);

      const result = await executeWithMockFallback(apiCall, mockLoader);
      expect(result).toEqual(mockData);
      expect(mockLoader).toHaveBeenCalledTimes(1);
    });
  });
});
