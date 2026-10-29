import axios, { AxiosError, InternalAxiosRequestConfig } from 'axios';
import { tokenStorage } from './token-storage';

const API_BASE_URL =
  process.env.NEXT_PUBLIC_API_BASE_URL || 'http://127.0.0.1:8000/api/v1';

export const apiClient = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
  timeout: 15000,
});

// Bare client used only to call the refresh endpoint, so it is not itself
// intercepted (avoids an infinite 401 -> refresh -> 401 loop).
const refreshClient = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
  timeout: 15000,
});

// Request interceptor: attach Bearer token
apiClient.interceptors.request.use(
  (config: InternalAxiosRequestConfig) => {
    const token = tokenStorage.getAccessToken();
    if (token && config.headers) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => Promise.reject(error)
);

// ---------------------------------------------------------------------------
// Single-flight refresh-token handling.
// Backend issues a 15m access token + rotating 14d refresh token; on a 401 we
// try to refresh once and retry the original request, so the user is not kicked
// to /login every ~15 minutes. Concurrent 401s wait for one shared refresh.
// ---------------------------------------------------------------------------
let isRefreshing = false;
let refreshWaiters: Array<(token: string | null) => void> = [];

function notifyWaiters(token: string | null): void {
  refreshWaiters.forEach((cb) => cb(token));
  refreshWaiters = [];
}

function redirectToLogin(): void {
  tokenStorage.clear();
  if (
    typeof window !== 'undefined' &&
    !window.location.pathname.startsWith('/login')
  ) {
    const currentPath = encodeURIComponent(
      window.location.pathname + window.location.search
    );
    window.location.href = `/login?redirect=${currentPath}`;
  }
}

async function performRefresh(): Promise<string | null> {
  const refreshToken = tokenStorage.getRefreshToken();
  if (!refreshToken) return null;

  try {
    const res = await refreshClient.post('/auth/refresh', { refreshToken });
    const tokens = res.data?.data?.tokens;
    const newAccess: string | undefined = tokens?.accessToken;
    const newRefresh: string | undefined = tokens?.refreshToken;

    if (!newAccess) return null;

    tokenStorage.setAccessToken(newAccess);
    if (newRefresh) tokenStorage.setRefreshToken(newRefresh);
    return newAccess;
  } catch {
    return null;
  }
}

// Response interceptor: refresh on 401 then retry the original request once.
apiClient.interceptors.response.use(
  (response) => response,
  async (error: AxiosError) => {
    const status = error.response?.status;
    const original = error.config as
      | (InternalAxiosRequestConfig & { _retry?: boolean })
      | undefined;
    const url = original?.url ?? '';
    const isAuthCall =
      url.includes('/auth/refresh') || url.includes('/auth/login');

    // Not a retryable 401 -> give up (and clear session on a genuine 401).
    if (status !== 401 || !original || original._retry || isAuthCall) {
      if (status === 401 && !isAuthCall) redirectToLogin();
      return Promise.reject(error);
    }

    original._retry = true;

    // A refresh is already running: queue until it resolves.
    if (isRefreshing) {
      return new Promise((resolve, reject) => {
        refreshWaiters.push((token) => {
          if (!token) {
            reject(error);
            return;
          }
          if (original.headers) {
            original.headers.Authorization = `Bearer ${token}`;
          }
          resolve(apiClient(original));
        });
      });
    }

    isRefreshing = true;
    const newToken = await performRefresh();
    isRefreshing = false;
    notifyWaiters(newToken);

    if (!newToken) {
      redirectToLogin();
      return Promise.reject(error);
    }

    if (original.headers) {
      original.headers.Authorization = `Bearer ${newToken}`;
    }
    return apiClient(original);
  }
);

export { lmsApi } from './lms-api';
export default apiClient;
