'use client';

import React, { createContext, useContext, useEffect, useState, useCallback, useRef } from 'react';
import { useRouter } from 'next/navigation';
import axios from 'axios';
import { User, LoginPayload, LoginResult, PortalDestination, RoleCode } from '@/types/auth';
import { apiClient } from '@/lib/api/api-client';
import { tokenStorage } from '@/lib/auth/token-storage';
import { resolveUserDestination, sanitizeRoles } from '@/lib/rbac/evaluator';
import { ApiResponse } from '@/types/api';

const API_BASE_URL =
  process.env.NEXT_PUBLIC_API_BASE_URL || 'http://127.0.0.1:8000/api/v1';

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

      // Tidak ada token sama sekali
      if (!accessToken && !refreshToken) {
        setUser(null);
        setActiveRoleState(null);
        return;
      }

      // Kasus Tab Baru: accessToken (sessionStorage) kosong, namun refreshToken (localStorage) tersedia
      if (!accessToken && refreshToken) {
        try {
          const refreshRes = await axios.post<
            ApiResponse<{
              user: User;
              tokens: { accessToken: string; refreshToken?: string };
            }>
          >(
            `${API_BASE_URL}/auth/refresh`,
            { refreshToken },
            { headers: { Accept: 'application/json' }, timeout: 10000 }
          );

          const refreshedData = refreshRes.data.data;
          tokenStorage.setTokens(refreshedData.tokens.accessToken, refreshedData.tokens.refreshToken);
          tokenStorage.setUser(refreshedData.user);
          setUser(refreshedData.user);

          const roles = sanitizeRoles(refreshedData.user.roles);
          if (roles.length > 0) {
            setActiveRoleState(roles[0]);
          }
          return;
        } catch {
          // Refresh token tidak sah atau kedaluwarsa
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
        ...payload,
        platform: payload.platform || 'web',
      };

      // Perangkat tepercaya: lampirkan token tersimpan agar admin melewati 2FA (berlaku 30 hari)
      if (payload.isAdminLogin && !requestBody.trusted_device_token && typeof window !== 'undefined') {
        try {
          const storedTdt = window.localStorage.getItem('sistlabum_tdt');
          if (storedTdt) requestBody.trusted_device_token = storedTdt;
        } catch {
          // abaikan akses storage yang gagal
        }
      }

      const res = await apiClient.post<
        ApiResponse<{
          user?: User;
          tokens?: { accessToken: string; refreshToken?: string };
          trusted_device_token?: string;
          trusted_device_expires_at?: string;
        }> & {
          mfa_required?: boolean;
          mfa_token?: string;
          mfa_enrollment_required?: boolean;
          enrollment_token?: string;
        }
      >(endpoint, requestBody);

      if (res.data.mfa_enrollment_required && res.data.enrollment_token) {
        return {
          user: null,
          destination: null,
          mfa_enrollment_required: true,
          enrollment_token: res.data.enrollment_token,
        };
      }

      if (res.data.mfa_required && res.data.mfa_token) {
        return {
          user: null,
          destination: null,
          mfa_required: true,
          mfa_token: res.data.mfa_token,
        };
      }

      const responseData = res.data.data;
      if (!responseData?.user || !responseData?.tokens) {
        throw new Error('Format respon autentikasi tidak valid.');
      }
      const loggedUser = responseData.user;
      const tokens = responseData.tokens;

      // Simpan token perangkat tepercaya bila dikembalikan server (fitur "percayai perangkat 30 hari")
      if (typeof window !== 'undefined' && responseData.trusted_device_token) {
        try {
          window.localStorage.setItem('sistlabum_tdt', responseData.trusted_device_token);
        } catch {
          // abaikan akses storage yang gagal
        }
      }

      // Simpan token & profil pengguna
      tokenStorage.setTokens(tokens.accessToken, tokens.refreshToken);
      tokenStorage.setUser(loggedUser);
      setUser(loggedUser);

      const roles = sanitizeRoles(loggedUser.roles);
      if (roles.length > 0) {
        setActiveRoleState(roles[0]);
      }

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
      await apiClient.post('/auth/logout', { refreshToken }).catch(() => {
        // Abaikan error jaringan server agar proses logout lokal tetap tuntas
      });
    } finally {
      tokenStorage.clearAll();
      setUser(null);
      setActiveRoleState(null);
      setIsLoading(false);
      router.push('/masuk');
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
