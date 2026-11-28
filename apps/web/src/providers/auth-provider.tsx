'use client';

import React, { createContext, useContext, useEffect, useState, useCallback, useRef } from 'react';
import { useRouter } from 'next/navigation';
import axios from 'axios';
import { User, LoginPayload, LoginResult, RoleCode } from '@/types/auth';
import { apiClient } from '@/lib/api/api-client';
import { tokenStorage } from '@/lib/auth/token-storage';
import { ADMIN_SHELL_ROLES, resolveUserDestination, sanitizeRoles } from '@/lib/rbac/evaluator';
import { ApiResponse } from '@/types/api';
import { queryClient } from './query-provider';

const API_BASE_URL =
  process.env.NEXT_PUBLIC_API_BASE_URL || 'http://127.0.0.1:8000/api/v1';

async function syncAdminSessionCookie(accessToken: string, roles: string[]): Promise<void> {
  if (typeof window === 'undefined' || typeof fetch !== 'function') return;
  const hasAdminRole = roles.some((r) =>
    ADMIN_SHELL_ROLES.includes(r as (typeof ADMIN_SHELL_ROLES)[number])
  );
  if (!hasAdminRole) return;

  try {
    const res = await fetch('/api/admin-session', {
      method: 'POST',
      headers: {
        Authorization: `Bearer ${accessToken}`,
        Accept: 'application/json',
      },
      credentials: 'same-origin',
    });

    // Jangan telan diam-diam: tanpa cookie ini, seluruh shell /admin akan 404
    // dan pengguna hanya melihat halaman "tidak ditemukan" tanpa penjelasan.
    if (!res.ok) {
      console.error('[auth] gagal menerbitkan cookie sesi admin:', res.status);
    }
  } catch (err) {
    console.error('[auth] gagal menghubungi /api/admin-session:', err);
  }
}

async function clearAdminSessionCookie(): Promise<void> {
  if (typeof window === 'undefined' || typeof fetch !== 'function') return;
  try {
    await fetch('/api/admin-session', {
      method: 'DELETE',
      credentials: 'same-origin',
    });
  } catch {
    // Abaikan kegagalan jaringan
  }
}

interface AuthContextType {
  user: User | null;
  isLoading: boolean;
  isAuthenticated: boolean;
  activeRole: string | null;
  login: (payload: LoginPayload) => Promise<LoginResult>;
  logout: () => Promise<void>;
  checkAuth: () => Promise<void>;
  setActiveRole: (role: string) => void;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const router = useRouter();
  const [user, setUser] = useState<User | null>(null);
  const [isLoading, setIsLoading] = useState<boolean>(true);
  const [activeRole, setActiveRoleState] = useState<string | null>(null);
  const isCheckingAuthRef = useRef(false);

  // Verifikasi status sesi saat inisialisasi aplikasi (dengan rehidrasi lintas tab)
  const checkAuth = useCallback(async () => {
    if (isCheckingAuthRef.current) return;
    isCheckingAuthRef.current = true;

    try {
      const accessToken = tokenStorage.getAccessToken();
      const refreshToken = tokenStorage.getRefreshToken();

      // Kasus Tab Baru (P1-01): accessToken (sessionStorage) kosong -> rehidrasi via cookie HttpOnly
      if (!accessToken) {
        try {
          const refreshPayload = refreshToken ? { refreshToken } : {};
          const refreshRes = await axios.post<
            ApiResponse<{
              user: User;
              tokens: { accessToken: string; refreshToken?: string };
            }>
          >(
            `${API_BASE_URL}/auth/refresh`,
            refreshPayload,
            {
              withCredentials: true,
              headers: {
                Accept: 'application/json',
                'X-Auth-Token-Transport': 'cookie',
              },
              timeout: 10000,
            }
          );

          const refreshedData = refreshRes.data.data;
          tokenStorage.setTokens(refreshedData.tokens.accessToken, refreshedData.tokens.refreshToken);
          tokenStorage.setUser(refreshedData.user);
          setUser(refreshedData.user);

          const roles = sanitizeRoles(refreshedData.user.roles);
          if (roles.length > 0) {
            setActiveRoleState(roles[0]);
          }
          await syncAdminSessionCookie(refreshedData.tokens.accessToken, roles);
          return;
        } catch {
          // Tidak ada sesi cookie HttpOnly aktif atau sudah kedaluwarsa
          tokenStorage.clearAll();
          setUser(null);
          setActiveRoleState(null);
          return;
        }
      }

      // Kasus accessToken tersedia: verifikasi validitas profil via /auth/me
      try {
        const res = await apiClient.get<ApiResponse<{ user: User }>>('/auth/me');
        const currentUser = res.data.data.user;
        tokenStorage.setUser(currentUser);
        setUser(currentUser);

        const roles = sanitizeRoles(currentUser.roles);
        if (roles.length > 0) {
          setActiveRoleState(roles[0]);
        }
      } catch {
        // Jika /auth/me gagal dan interceptor juga gagal refresh
        tokenStorage.clearAll();
        setUser(null);
        setActiveRoleState(null);
      }
    } finally {
      setIsLoading(false);
      isCheckingAuthRef.current = false;
    }
  }, []);

  useEffect(() => {
    checkAuth();
  }, [checkAuth]);

  const login = async (payload: LoginPayload): Promise<LoginResult> => {
    setIsLoading(true);
    try {
      const endpoint = payload.isAdminLogin ? '/auth/admin/login' : '/auth/login';

      const requestBody: Record<string, unknown> = {
        identifier: payload.identifier,
        password: payload.password,
        platform: payload.platform || 'web',
      };

      const res = await apiClient.post<
        ApiResponse<{
          user?: User;
          tokens?: { accessToken: string; refreshToken?: string };
        }>
      >(endpoint, requestBody, {
        headers: {
          'X-Auth-Token-Transport': 'cookie',
        },
      });

      const responseData = res.data.data;
      if (!responseData?.user || !responseData?.tokens) {
        throw new Error('Format respon autentikasi tidak valid.');
      }
      const loggedUser = responseData.user;
      const tokens = responseData.tokens;

      // Simpan access token di sessionStorage/memory (refresh token tetap di cookie HttpOnly)
      tokenStorage.setTokens(tokens.accessToken, tokens.refreshToken);
      tokenStorage.setUser(loggedUser);
      setUser(loggedUser);
      try {
        queryClient.clear();
      } catch {
        // ignore
      }

      const roles = sanitizeRoles(loggedUser.roles);
      if (roles.length > 0) {
        setActiveRoleState(roles[0]);
      }

      // P0-08: Terbitkan cookie HttpOnly bertanda tangan HMAC jika peran diizinkan ke shell admin
      await syncAdminSessionCookie(tokens.accessToken, roles);

      // Tentukan destinasi portal
      const destination = resolveUserDestination(roles);
      return { user: loggedUser, destination, mfa_required: false };
    } finally {
      setIsLoading(false);
    }
  };

  const logout = async (): Promise<void> => {
    setIsLoading(true);
    try {
      const refreshToken = tokenStorage.getRefreshToken();
      await apiClient
        .post('/auth/logout', refreshToken ? { refreshToken } : {})
        .catch(() => {
          // Abaikan error jaringan server agar proses logout lokal tetap tuntas
        });
      await clearAdminSessionCookie();
    } finally {
      tokenStorage.clearAll();
      setUser(null);
      setActiveRoleState(null);
      try {
        queryClient.clear();
      } catch {
        // ignore
      }
      setIsLoading(false);
      if (typeof window !== 'undefined') {
        window.location.href = '/masuk';
      } else {
        router.push('/masuk');
      }
    }
  };

  const setActiveRole = (role: string) => {
    if (user && user.roles.includes(role as RoleCode)) {
      setActiveRoleState(role);
    }
  };

  const value: AuthContextType = {
    user,
    isLoading,
    isAuthenticated: !!user,
    activeRole,
    login,
    logout,
    checkAuth,
    setActiveRole,
  };

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextType {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth harus digunakan di dalam AuthProvider');
  }
  return context;
}
