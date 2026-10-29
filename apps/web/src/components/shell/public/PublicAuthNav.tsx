'use client';

import React from 'react';
import Link from 'next/link';
import { useAuth } from '@/providers/auth-provider';
import { resolveUserDestination, getDestinationPath } from '@/lib/rbac/evaluator';

import { LogIn } from 'lucide-react';

interface PublicAuthNavProps {
  variant?: 'desktop' | 'mobile';
  onNavigate?: () => void;
}

/**
 * PublicAuthNav Component (Client Island)
 * Mengintegrasikan status autentikasi tanpa mengakses storage langsung.
 * Fallback stabil ke label "Masuk Portal" saat inisialisasi / belum login.
 */
export function PublicAuthNav({ variant = 'desktop', onNavigate }: PublicAuthNavProps) {
  const { user, isAuthenticated, isLoading } = useAuth();

  // Selama loading atau belum login, arahkan ke /masuk
  const destinationPath =
    !isLoading && isAuthenticated && user
      ? getDestinationPath(resolveUserDestination((user.roles as string[]) || []))
      : '/masuk';

  const label = !isLoading && isAuthenticated ? 'Buka Portal Saya' : 'Portal Akademik';

  if (variant === 'mobile') {
    return (
      <Link
        href={destinationPath}
        onClick={onNavigate}
        className="w-full text-center bg-white hover:bg-gray-50 text-[#0A3D62] border border-[#0A3D62] font-semibold text-sm py-2.5 px-4 rounded-lg transition-colors inline-flex items-center justify-center gap-2 focus:outline-none focus:ring-2 focus:ring-[#C89332] focus:ring-offset-2"
      >
        <LogIn className="w-4 h-4 text-[#0A3D62]" aria-hidden="true" />
        <span>{label}</span>
      </Link>
    );
  }

  return (
    <Link
      href={destinationPath}
      className="bg-white hover:bg-gray-50 text-[#0A3D62] border border-[#0A3D62] font-semibold text-xs sm:text-sm px-4 py-2.5 rounded-lg transition-colors inline-flex items-center justify-center focus:outline-none focus:ring-2 focus:ring-pub-gold focus:ring-offset-2"
    >
      <span>{label}</span>
    </Link>
  );
}
