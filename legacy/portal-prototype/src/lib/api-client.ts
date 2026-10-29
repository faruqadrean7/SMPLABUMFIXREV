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

// Response interceptor: handle 401 unauthorized
apiClient.interceptors.response.use(
  (response) => response,
  (error: AxiosError) => {
    if (error.response?.status === 401) {
      // Clear token if token is expired or invalid
      tokenStorage.clear();

      if (typeof window !== 'undefined' && !window.location.pathname.startsWith('/login')) {
        const currentPath = encodeURIComponent(window.location.pathname);
        window.location.href = `/login?redirect=${currentPath}`;
      }
    }
    return Promise.reject(error);
  }
);

export { lmsApi } from './lms-api';
export default apiClient;
