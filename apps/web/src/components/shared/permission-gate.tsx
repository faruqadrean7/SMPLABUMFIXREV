'use client';

import React from 'react';
import { useAuth } from '@/providers/auth-provider';
import { hasPermission } from '@/lib/rbac/evaluator';

export interface PermissionGateProps {
  permission: string | string[];
  fallback?: React.ReactNode;
  children: React.ReactNode;
}

/**
 * Komponen pembatas hak akses berbasis permission
 * Merender children hanya jika pengguna memiliki hak akses yang dipersyaratkan.
 */
export function PermissionGate({ permission, fallback = null, children }: PermissionGateProps) {
  const { user, isLoading } = useAuth();

  if (isLoading) {
    return null;
  }

  const allowed = hasPermission(user, permission);

  if (!allowed) {
    return <>{fallback}</>;
  }

  return <>{children}</>;
}
