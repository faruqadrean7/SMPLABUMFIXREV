/**
 * Token Storage Manager untuk SISTLABUM Frontend
 * Mengelola access token dan refresh token di sisi klien.
 */

import { User } from '@/types/auth';

const ACCESS_TOKEN_KEY = 'sistlabum_access_token';
const REFRESH_TOKEN_KEY = 'sistlabum_refresh_token';
const USER_DATA_KEY = 'sistlabum_user_profile';

// In-memory fallback untuk SSR / sesi tab aktif / environment tanpa localStorage
let memoryAccessToken: string | null = null;
let memoryRefreshToken: string | null = null;
let memoryUser: Partial<User> | null = null;

function hasSessionStorage(): boolean {
  try {
    return typeof window !== 'undefined' && !!window.sessionStorage && typeof window.sessionStorage.getItem === 'function';
  } catch {
    return false;
  }
}

function hasLocalStorage(): boolean {
  try {
    return typeof window !== 'undefined' && !!window.localStorage && typeof window.localStorage.getItem === 'function';
  } catch {
    return false;
  }
}

export const tokenStorage = {
  getAccessToken(): string | null {
    if (memoryAccessToken) {
      return memoryAccessToken;
    }
    if (hasSessionStorage()) {
      try {
        const sessionToken = window.sessionStorage.getItem(ACCESS_TOKEN_KEY);
        if (sessionToken) return sessionToken;
      } catch {
        // Fallback
      }
    }
    return memoryAccessToken;
  },

  setAccessToken(token: string | null): void {
    memoryAccessToken = token;
    if (hasSessionStorage()) {
      try {
        if (token) {
          window.sessionStorage.setItem(ACCESS_TOKEN_KEY, token);
        } else {
          window.sessionStorage.removeItem(ACCESS_TOKEN_KEY);
        }
      } catch {
        // Fallback to memory
      }
    }
    // Keamanan (AGENTS.md §4 & P1-01): access & refresh token TIDAK BOLEH disimpan di localStorage.
    // Bersihkan seluruh kunci token legacy agar sesi lama ikut termigrasi.
    if (hasLocalStorage()) {
      try {
        window.localStorage.removeItem('auth_token');
        window.localStorage.removeItem(ACCESS_TOKEN_KEY);
        window.localStorage.removeItem(REFRESH_TOKEN_KEY);
        window.localStorage.removeItem('sistlabum_tdt');
      } catch {
        // Fallback
      }
    }
  },

  getRefreshToken(): string | null {
    // P1-01: Refresh token web dikelola lewat cookie HttpOnly; jangan pernah membaca dari localStorage.
    return memoryRefreshToken;
  },

  setRefreshToken(token: string | null): void {
    // P1-01: Simpan hanya di memori runtime bila diberikan (mis. uji unit); JANGAN tulis ke localStorage.
    memoryRefreshToken = token;
    if (hasLocalStorage()) {
      try {
        window.localStorage.removeItem(REFRESH_TOKEN_KEY);
      } catch {
        // Ignore
      }
    }
  },

  setTokens(accessToken: string, refreshToken?: string): void {
    this.setAccessToken(accessToken);
    if (refreshToken) {
      this.setRefreshToken(refreshToken);
    }
  },

  getUser(): User | null {
    if (memoryUser) return memoryUser as User;
    if (hasSessionStorage()) {
      try {
        const raw = window.sessionStorage.getItem(USER_DATA_KEY);
        if (raw) return JSON.parse(raw) as User;
      } catch {
        // Fallback
      }
    }
    return null;
  },

  setUser(user: Partial<User> | null): void {
    memoryUser = user;
    if (hasSessionStorage()) {
      try {
        if (user) {
          window.sessionStorage.setItem(USER_DATA_KEY, JSON.stringify(user));
        } else {
          window.sessionStorage.removeItem(USER_DATA_KEY);
        }
      } catch {
        // Fallback
      }
    }
    // P0-08: Cookie `sistlabum_admin_session` kini HttpOnly bertanda tangan HMAC
    // yang diterbitkan oleh Route Handler `/api/admin-session`, tidak pernah ditulis dari JS.
  },

  clearAll(): void {
    memoryAccessToken = null;
    memoryRefreshToken = null;
    memoryUser = null;
    if (hasSessionStorage()) {
      try {
        window.sessionStorage.removeItem(ACCESS_TOKEN_KEY);
        window.sessionStorage.removeItem(USER_DATA_KEY);
      } catch {
        // Ignore
      }
    }
    if (hasLocalStorage()) {
      try {
        window.localStorage.removeItem(ACCESS_TOKEN_KEY);
        window.localStorage.removeItem(REFRESH_TOKEN_KEY);
        window.localStorage.removeItem('auth_token');
        window.localStorage.removeItem('user_role');
        window.localStorage.removeItem('sistlabum_tdt');
      } catch {
        // Ignore
      }
    }
  },
};
