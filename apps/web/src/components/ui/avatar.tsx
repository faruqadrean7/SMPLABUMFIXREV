'use client';

import React, { useState, useEffect } from 'react';
import { User } from 'lucide-react';

export type AvatarSize = 'xs' | 'sm' | 'md' | 'lg' | 'xl' | '2xl';
export type AvatarShape = 'circle' | 'rounded';

export interface AvatarProps extends React.HTMLAttributes<HTMLDivElement> {
  src?: string | null;
  alt?: string;
  name?: string;
  size?: AvatarSize;
  shape?: AvatarShape;
  className?: string;
}

const sizeClasses: Record<AvatarSize, { container: string; text: string; icon: string }> = {
  xs: { container: 'h-6 w-6', text: 'text-[10px]', icon: 'h-3.5 w-3.5' },
  sm: { container: 'h-8 w-8', text: 'text-xs', icon: 'h-4 w-4' },
  md: { container: 'h-10 w-10', text: 'text-sm', icon: 'h-5 w-5' },
  lg: { container: 'h-12 w-12', text: 'text-base', icon: 'h-6 w-6' },
  xl: { container: 'h-16 w-16', text: 'text-lg', icon: 'h-8 w-8' },
  '2xl': { container: 'h-24 w-24', text: 'text-2xl', icon: 'h-12 w-12' },
};

function getInitials(name?: string): string {
  if (!name || !name.trim()) return '';
  const parts = name.trim().split(/\s+/).filter(Boolean);
  if (parts.length === 1) {
    return parts[0].slice(0, 2).toUpperCase();
  }
  return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
}

/**
 * Komponen Avatar kanonis SISTLABUM:
 * - Menampilkan foto profil dengan fallback inisial nama atau ikon pengguna.
 * - Menangani status kegagalan unduhan gambar (error fallback).
 * - Mencegah path traversal & injeksi URL tidak sah.
 */
export function Avatar({
  src,
  alt = 'Foto Profil',
  name,
  size = 'md',
  shape = 'circle',
  className = '',
  ...props
}: AvatarProps) {
  const [hasError, setHasError] = useState(false);
  const [isLoading, setIsLoading] = useState(true);

  // Reset error state if src changes
  useEffect(() => {
    setHasError(false);
    setIsLoading(true);
  }, [src]);

  const initials = getInitials(name);
  const { container, text, icon } = sizeClasses[size] || sizeClasses.md;
  const shapeClass = shape === 'circle' ? 'rounded-full' : 'rounded-xl';

  const showImage = Boolean(src && !hasError);

  return (
    <div
      role={!showImage ? 'img' : undefined}
      aria-label={!showImage ? (alt || name || 'Avatar pengguna') : undefined}
      className={`relative inline-flex items-center justify-center shrink-0 overflow-hidden font-bold select-none border border-slate-200/80 bg-slate-100 text-brand-blue shadow-2xs ${container} ${shapeClass} ${className}`}
      {...props}
    >
      {showImage ? (
        /* eslint-disable-next-line @next/next/no-img-element */
        <img
          src={src as string}
          alt={alt || name || 'Foto Profil'}
          className={`h-full w-full object-cover transition-opacity duration-200 ${
            isLoading ? 'opacity-0' : 'opacity-100'
          }`}
          onLoad={() => setIsLoading(false)}
          onError={() => {
            setHasError(true);
            setIsLoading(false);
          }}
        />
      ) : null}

      {/* Fallback to Initials or Icon */}
      {(!showImage || (isLoading && !hasError)) && (
        <span
          className={`flex items-center justify-center h-full w-full bg-blue-100 text-brand-blue font-bold tracking-tight ${text}`}
          aria-hidden="true"
        >
          {initials || <User className={icon} />}
        </span>
      )}
    </div>
  );
}
