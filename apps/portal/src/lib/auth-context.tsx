'use client';

import React, { createContext, useContext, useEffect, useState, useCallback, useMemo } from 'react';
import { useRouter } from 'next/navigation';
import { AuthUser, LoginResponseData, ApiResponse } from '@/types/auth';
import { tokenStorage } from './token-storage';
import { apiClient } from './api-client';

interface AuthContextType {
  user: AuthUser | null;
  token: string | null;
  isLoading: boolean;
  isAuthenticated: boolean;
  login: (identifier: string, password: string) => Promise<LoginResponseData>;
  logout: () => void;
  hasRole: (roles: string | string[]) => boolean;
  getRoleDefaultPath: (targetUser?: AuthUser | null) => string;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export function getRoleDefaultPath(targetUser?: AuthUser | null): string {
  if (!targetUser || !targetUser.roles || targetUser.roles.length === 0) {
    return '/login';
  }

  const roles = targetUser.roles.map((r) => String(r).toLowerCase());

  if (roles.includes('student')) {
    return '/siswa/dashboard';
  }
  if (roles.includes('teacher') || roles.includes('homeroom') || roles.includes('counselor')) {
    return '/guru/dashboard';
  }
  if (roles.includes('guardian')) {
    return '/ortu/dashboard';
  }

  // Fallback default
  return '/siswa/dashboard';
}

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const router = useRouter();
  const [user, setUser] = useState<AuthUser | null>(null);
  const [token, setToken] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  // Initialize from tokenStorage on mount
  useEffect(() => {
    try {
      const storedToken = tokenStorage.getAccessToken();
      const storedUser = tokenStorage.getUser();

      if (storedToken && storedUser) {
        setToken(storedToken);
        setUser(storedUser);
      } else {
        tokenStorage.clear();
      }
    } catch {
      tokenStorage.clear();
    } finally {
      setIsLoading(false);
    }
  }, []);

  const login = useCallback(async (identifier: string, password: string): Promise<LoginResponseData> => {
    setIsLoading(true);
    try {
      const response = await apiClient.post<ApiResponse<LoginResponseData>>('/auth/login', {
        identifier,
        password,
      });

      const responseData = response.data?.data;
      if (!responseData?.tokens?.accessToken || !responseData?.user) {
        throw new Error('Respon login dari server tidak valid.');
      }

      const { user: authUser, tokens } = responseData;

      // Save token and user
      tokenStorage.setAccessToken(tokens.accessToken);
      if (tokens.refreshToken) {
        tokenStorage.setRefreshToken(tokens.refreshToken);
      }
      tokenStorage.setUser(authUser);

      setToken(tokens.accessToken);
      setUser(authUser);

      return responseData;
    } catch (err) {
      tokenStorage.clear();
      setUser(null);
      setToken(null);
      throw err;
    } finally {
      setIsLoading(false);
    }
  }, []);

  const logout = useCallback(() => {
    tokenStorage.clear();
    setUser(null);
    setToken(null);
    router.push('/login');
  }, [router]);

  const hasRole = useCallback(
    (rolesToCheck: string | string[]): boolean => {
      if (!user || !user.roles) return false;
      const userRoles = user.roles.map((r) => String(r).toLowerCase());
      const checkList = Array.isArray(rolesToCheck)
        ? rolesToCheck.map((r) => r.toLowerCase())
        : [rolesToCheck.toLowerCase()];
      return checkList.some((role) => userRoles.includes(role));
    },
    [user]
  );

  const value = useMemo(
    () => ({
      user,
      token,
      isLoading,
      isAuthenticated: Boolean(token && user),
      login,
      logout,
      hasRole,
      getRoleDefaultPath,
    }),
    [user, token, isLoading, login, logout, hasRole]
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextType {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
}
