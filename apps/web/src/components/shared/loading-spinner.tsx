import React from 'react';
import { cn } from '@/lib/utils';

export function LoadingSpinner({
  className,
  label = 'Memuat data...',
  size = 'md',
}: {
  className?: string;
  label?: string;
  size?: 'sm' | 'md' | 'lg';
}) {
  const sizeMap = {
    sm: 'h-4 w-4 border-2',
    md: 'h-8 w-8 border-3',
    lg: 'h-12 w-12 border-4',
  };

  return (
    <div className={cn('flex flex-col items-center justify-center p-8 space-y-3', className)} role="status">
      <div
        className={cn(
          'animate-spin rounded-full border-brand-blue border-t-transparent',
          sizeMap[size]
        )}
        aria-hidden="true"
      />
      {label && <p className="text-sm font-medium text-slate-500">{label}</p>}
      <span className="sr-only">{label}</span>
    </div>
  );
}
