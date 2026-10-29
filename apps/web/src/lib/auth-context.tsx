'use client';

import React, { useCallback } from 'react';
import { useAuth as useCoreAuth } from '@/providers/auth-provider';
import { User as AuthUser } from '@/types/auth';

export function getRoleDefaultPath(targetUser?: AuthUser | null): string {
  if (!targetUser || !targetUser.roles || targetUser.roles.length === 0) {
    return '/masuk';
  }

  const roles = targetUser.roles.map((r) => String(r).toLowerCase());

  if (roles.includes('student')) {
    return '/portal/siswa';
  }
  if (roles.includes('teacher') || roles.includes('homeroom') || roles.includes('counselor')) {
    return '/portal/guru';
  }
  if (roles.includes('guardian')) {
    return '/portal/ortu';
  }

  return '/portal/siswa';
}

export function useAuth() {
  const core = useCoreAuth();

  const hasRole = useCallback(
    (rolesToCheck: string | string[]): boolean => {
      if (!core.user || !core.user.roles) return false;
      const userRoles = core.user.roles.map((r) => String(r).toLowerCase());
      const checkList = Array.isArray(rolesToCheck)
        ? rolesToCheck.map((r) => r.toLowerCase())
        : [rolesToCheck.toLowerCase()];
      return checkList.some((role) => userRoles.includes(role));
    },
    [core.user]
  );

  return {
    ...core,
    token: null,
    hasRole,
    getRoleDefaultPath,
  };
}

export { AuthProvider } from '@/providers/auth-provider';
