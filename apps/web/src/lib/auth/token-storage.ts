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
    // Fallback defensif untuk sesi pendaftar PPDB atau legacy local storage
    if (hasLocalStorage()) {
      try {
        const localToken =
          window.localStorage.getItem(ACCESS_TOKEN_KEY) ||
          window.localStorage.getItem('auth_token');
        if (localToken) return localToken;
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
    // Sinkronkan juga ke localStorage untuk backward compatibility
    if (hasLocalStorage()) {
      try {
        if (token) {
          window.localStorage.setItem('auth_token', token);
        } else {
          window.localStorage.removeItem('auth_token');
        }
      } catch {
        // Fallback
      }
    }
  },

  getRefreshToken(): string | null {
    if (!hasLocalStorage()) {
      return memoryRefreshToken;
    }
    try {
      return memoryRefreshToken || window.localStorage.getItem(REFRESH_TOKEN_KEY);
    } catch {
      return memoryRefreshToken;
    }
  },

  setRefreshToken(token: string | null): void {
    memoryRefreshToken = token;
    if (hasLocalStorage()) {
      try {
        if (token) {
          window.localStorage.setItem(REFRESH_TOKEN_KEY, token);
        } else {
          window.localStorage.removeItem(REFRESH_TOKEN_KEY);
        }
      } catch {
        // Fallback to memory
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

    if (typeof document !== 'undefined') {
      try {
        const roles = user?.roles || [];
        const adminRoles = [
          'super_admin',
          'admin_tu',
          'principal',
          'vice_principal',
          'counselor',
          'facility_staff',
          'ppdb_committee',
          'librarian',
          'bendahara',
          'treasurer',
          'student_affairs',
          'public_relations',
          'operator_cms',
          'admin_kurikulum',
          'kurikulum',
        ];
        const hasAdminAccess = Array.isArray(roles) && roles.some((r) => adminRoles.includes(r));
        if (hasAdminAccess) {
          document.cookie = 'sistlabum_admin_session=1; path=/; SameSite=Lax';
        } else {
          document.cookie = 'sistlabum_admin_session=; path=/; max-age=0; SameSite=Lax';
        }
      } catch {
        // Fallback
      }
    }
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
        window.localStorage.removeItem(REFRESH_TOKEN_KEY);
        window.localStorage.removeItem('auth_token');
        window.localStorage.removeItem('user_role');
      } catch {
        // Ignore
      }
    }
    if (typeof document !== 'undefined') {
      try {
        document.cookie = 'sistlabum_admin_session=; path=/; max-age=0; SameSite=Lax';
      } catch {
        // Ignore
      }
    }
  },
};
