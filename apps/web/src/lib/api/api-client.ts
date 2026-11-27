import axios, { AxiosError, AxiosInstance, InternalAxiosRequestConfig } from 'axios';
import { ApiClientError } from './errors';
import { tokenStorage } from '../auth/token-storage';
import { isReadOnlyRole } from '@/lib/rbac/evaluator';
import { ApiErrorPayload, ApiResponse } from '@/types/api';

const API_BASE_URL =
  process.env.NEXT_PUBLIC_API_BASE_URL || 'http://127.0.0.1:8000/api/v1';

/**
 * Buat UUID sederhana jika crypto.randomUUID belum tersedia
 */
function generateRequestId(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID();
  }
  return 'req-' + Math.random().toString(36).substring(2, 15) + Date.now().toString(36);
}

// Instance Axios utama (P1-01: withCredentials agar cookie HttpOnly refresh token ikut dikirim)
export const apiClient: AxiosInstance = axios.create({
  baseURL: API_BASE_URL,
  timeout: 15000,
  withCredentials: true,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
});

// Flag penanda agar proses refresh token tidak berjalan ganda
let isRefreshing = false;
let failedQueue: Array<{
  resolve: (value?: unknown) => void;
  reject: (reason?: unknown) => void;
}> = [];

const processQueue = (error: unknown, token: string | null = null) => {
  failedQueue.forEach((prom) => {
    if (error) {
      prom.reject(error);
    } else {
      prom.resolve(token);
    }
  });
  failedQueue = [];
};

/**
 * Interceptor Permintaan (Request)
 */
apiClient.interceptors.request.use(
  (config: InternalAxiosRequestConfig) => {
    // Sisipkan Request ID untuk pelacakan audit
    if (!config.headers['X-Request-Id']) {
      config.headers['X-Request-Id'] = generateRequestId();
    }

    // P1-01: Minta transport cookie HttpOnly untuk endpoint autentikasi Web
    const reqUrl = config.url || '';
    if (
      reqUrl.includes('/auth/login') ||
      reqUrl.includes('/auth/admin/login') ||
      reqUrl.includes('/auth/refresh') ||
      reqUrl.includes('/auth/logout')
    ) {
      config.headers['X-Auth-Token-Transport'] = 'cookie';
    }

    // Sisipkan Bearer Token jika tersedia
    const token = tokenStorage.getAccessToken();
    if (token && !config.headers['Authorization']) {
      config.headers['Authorization'] = `Bearer ${token}`;
    }

    // SEC-CBT-07: Sisipkan X-Exam-Session-Token & X-Device-Fingerprint otomatis pada rute sesi ujian siswa
    if (typeof window !== 'undefined' && config.url) {
      const examMatch = config.url.match(/\/exams\/([^/]+)\/(start-session|session-questions|save-answer|log-violation|violations|finish-session)/);
      const hbMatch = config.url.match(/\/exam-sessions\/([^/]+)\/heartbeat/);
      const targetKey = examMatch?.[1] || hbMatch?.[1];
      if (targetKey) {
        try {
          const lockToken = window.sessionStorage.getItem(`sistlabum_cbt_lock_${targetKey}`);
          if (lockToken && !config.headers['X-Exam-Session-Token']) {
            config.headers['X-Exam-Session-Token'] = lockToken;
          }
          const fp = window.sessionStorage.getItem('sistlabum_cbt_device_fp');
          if (fp && !config.headers['X-Device-Fingerprint']) {
            config.headers['X-Device-Fingerprint'] = fp;
          }
        } catch {
          // Ignore storage error
        }
      }
    }

    // Cegah operasi mutasi data (POST, PUT, PATCH, DELETE) bagi role baca-saja murni (Komite & Pengawas)
    const activeUser = tokenStorage.getUser();
    if (activeUser && isReadOnlyRole(activeUser.roles)) {
      const method = (config.method || 'get').toUpperCase();
      if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(method)) {
        const url = config.url || '';
        const isAuthWhitelisted =
          url.includes('/auth/logout') ||
          url.includes('/auth/refresh') ||
          url.includes('/auth/login');

        if (!isAuthWhitelisted) {
          return Promise.reject(
            new ApiClientError(
              'Akses ditolak: Akun dengan peran baca-saja (Komite / Pengawas) tidak memiliki izin untuk menambah, mengubah, atau menghapus data.',
              'FORBIDDEN',
              403
            )
          );
        }
      }
    }

    return config;
  },
  (error) => {
    return Promise.reject(error);
  }
);

/**
 * Interceptor Tanggapan (Response)
 */
apiClient.interceptors.response.use(
  (response) => {
    return response;
  },
  async (error: AxiosError | ApiClientError) => {
    // Jika error sudah merupakan ApiClientError (misal dari request interceptor), teruskan langsung
    if (error instanceof ApiClientError) {
      return Promise.reject(error);
    }

    const originalRequest = (error as AxiosError).config as InternalAxiosRequestConfig & { _retry?: boolean };

    // Tangani error jaringan murni
    if (!error.response) {
      const isTimeout = error.code === 'ECONNABORTED';
      const networkMessage = isTimeout
        ? 'Batas waktu permintaan habis. Server tidak merespons.'
        : 'Gagal terhubung ke server backend. Periksa koneksi internet Anda.';

      return Promise.reject(
        new ApiClientError(
          networkMessage,
          isTimeout ? 'REQUEST_TIMEOUT' : 'NETWORK_ERROR',
          isTimeout ? 408 : 0
        )
      );
    }

    const { status, data } = error.response;
    const backendPayload = data as { error?: ApiErrorPayload; message?: string } | undefined;
    const backendError = backendPayload?.error;

    // Tangani refresh token pada 401 UNAUTHENTICATED via cookie HttpOnly (P1-01)
    const requestUrl = originalRequest.url || '';
    const isLoginEndpoint =
      requestUrl.includes('/auth/login') ||
      requestUrl.includes('/auth/admin/login') ||
      requestUrl.includes('/ppdb/login');
    const isRefreshEndpoint = requestUrl.includes('/auth/refresh');
    const isLogoutEndpoint = requestUrl.includes('/auth/logout');

    if (status === 401 && !originalRequest._retry && !isLoginEndpoint && !isRefreshEndpoint && !isLogoutEndpoint) {
      const storedRefreshToken = tokenStorage.getRefreshToken();
      const isPPDBSession = typeof window !== 'undefined' && window.location.pathname.startsWith('/ppdb');

      if (!isPPDBSession) {
        if (isRefreshing) {
          return new Promise((resolve, reject) => {
            failedQueue.push({ resolve, reject });
          })
            .then((token) => {
              originalRequest.headers['Authorization'] = `Bearer ${token}`;
              return apiClient(originalRequest);
            })
            .catch((err) => Promise.reject(err));
        }

        originalRequest._retry = true;
        isRefreshing = true;

        try {
          // P1-01: Panggil endpoint rotasi refresh token via cookie HttpOnly (tanpa localStorage)
          const refreshPayload = storedRefreshToken ? { refreshToken: storedRefreshToken } : {};
          const refreshRes = await axios.post<ApiResponse<{ tokens: { accessToken: string; refreshToken?: string } }>>(
            `${API_BASE_URL}/auth/refresh`,
            refreshPayload,
            {
              withCredentials: true,
              headers: {
                Accept: 'application/json',
                'X-Auth-Token-Transport': 'cookie',
              },
            }
          );

          const newTokens = refreshRes.data.data.tokens;
          tokenStorage.setTokens(newTokens.accessToken, newTokens.refreshToken);

          processQueue(null, newTokens.accessToken);

          originalRequest.headers['Authorization'] = `Bearer ${newTokens.accessToken}`;
          return apiClient(originalRequest);
        } catch (refreshErr) {
          processQueue(refreshErr, null);
          tokenStorage.clearAll();
          if (typeof window !== 'undefined') {
            fetch('/api/admin-session', { method: 'DELETE', credentials: 'same-origin' }).catch(() => {});
            // P0-08: Jangan pernah membocorkan path rahasia gateway admin di klien JS
            if (
              !window.location.pathname.includes('/masuk') &&
              !window.location.pathname.includes('admin-gateway-internal')
            ) {
              window.location.href = '/masuk?session_expired=1';
            }
          }
          return Promise.reject(
            new ApiClientError(
              'Sesi Anda telah berakhir. Silakan masuk kembali.',
              'SESSION_EXPIRED',
              401
            )
          );
        } finally {
          isRefreshing = false;
        }
      } else {
        // Sesi pendaftar PPDB tanpa refresh token
        tokenStorage.clearAll();
        if (typeof window !== 'undefined' && !window.location.pathname.includes('/masuk')) {
          window.location.href = '/ppdb/masuk?session_expired=1';
        }
      }
    }

    // Ekstraksi pesan error baku
    const message =
      backendError?.message ||
      backendPayload?.message ||
      getDefaultStatusMessage(status);

    const code = backendError?.code || getDefaultErrorCode(status);
    const details = backendError?.details || [];
    const requestId = backendError?.requestId || (error.response.headers['x-request-id'] as string | undefined);

    return Promise.reject(new ApiClientError(message, code, status, details, requestId));
  }
);

function getDefaultStatusMessage(status: number): string {
  switch (status) {
    case 400:
      return 'Permintaan data tidak valid.';
    case 401:
      return 'Kredensial tidak valid atau sesi telah berakhir.';
    case 403:
      return 'Akses ditolak. Anda tidak memiliki izin untuk fitur ini.';
    case 404:
      return 'Sumber daya atau halaman tidak ditemukan.';
    case 422:
      return 'Data yang dikirimkan tidak memenuhi syarat validasi.';
    case 429:
      return 'Terlalu banyak permintaan. Silakan tunggu beberapa menit.';
    case 500:
      return 'Terjadi kesalahan internal pada server sekolah.';
    default:
      return `Terjadi kesalahan sistem (Kode status: ${status}).`;
  }
}

function getDefaultErrorCode(status: number): string {
  switch (status) {
    case 401:
      return 'UNAUTHENTICATED';
    case 403:
      return 'FORBIDDEN';
    case 404:
      return 'NOT_FOUND';
    case 422:
      return 'VALIDATION_FAILED';
    case 429:
      return 'RATE_LIMITED';
    case 500:
      return 'INTERNAL_ERROR';
    default:
      return 'HTTP_ERROR';
  }
}
