import { AuthUser } from '@/types/auth';

const ACCESS_TOKEN_KEY = 'sistlabum_portal_token';
const REFRESH_TOKEN_KEY = 'sistlabum_portal_refresh';
const USER_KEY = 'sistlabum_portal_user';

let memoryToken: string | null = null;
let memoryRefresh: string | null = null;
let memoryUser: AuthUser | null = null;

function hasSessionStorage(): boolean {
  try {
    return typeof window !== 'undefined' && typeof window.sessionStorage?.getItem === 'function';
  } catch {
    return false;
  }
}

export const tokenStorage = {
  getAccessToken(): string | null {
    if (!hasSessionStorage()) return memoryToken;
    try {
      return memoryToken || window.sessionStorage.getItem(ACCESS_TOKEN_KEY);
    } catch {
      return memoryToken;
    }
  },

  setAccessToken(token: string | null): void {
    memoryToken = token;
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
  },

  getRefreshToken(): string | null {
    if (!hasSessionStorage()) return memoryRefresh;
    try {
      return memoryRefresh || window.sessionStorage.getItem(REFRESH_TOKEN_KEY);
    } catch {
      return memoryRefresh;
    }
  },

  setRefreshToken(token: string | null): void {
    memoryRefresh = token;
    if (hasSessionStorage()) {
      try {
        if (token) {
          window.sessionStorage.setItem(REFRESH_TOKEN_KEY, token);
        } else {
          window.sessionStorage.removeItem(REFRESH_TOKEN_KEY);
        }
      } catch {
        // Fallback to memory
      }
    }
  },

  getUser(): AuthUser | null {
    if (!hasSessionStorage()) return memoryUser;
    try {
      if (memoryUser) return memoryUser;
      const raw = window.sessionStorage.getItem(USER_KEY);
      return raw ? JSON.parse(raw) : null;
    } catch {
      return memoryUser;
    }
  },

  setUser(user: AuthUser | null): void {
    memoryUser = user;
    if (hasSessionStorage()) {
      try {
        if (user) {
          window.sessionStorage.setItem(USER_KEY, JSON.stringify(user));
        } else {
          window.sessionStorage.removeItem(USER_KEY);
        }
      } catch {
        // Fallback to memory
      }
    }
  },

  clear(): void {
    memoryToken = null;
    memoryRefresh = null;
    memoryUser = null;
    if (hasSessionStorage()) {
      try {
        window.sessionStorage.removeItem(ACCESS_TOKEN_KEY);
        window.sessionStorage.removeItem(REFRESH_TOKEN_KEY);
        window.sessionStorage.removeItem(USER_KEY);
      } catch {
        // Ignore
      }
    }
  },
};
