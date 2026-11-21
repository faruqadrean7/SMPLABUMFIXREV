import React from 'react';
import { cn } from '@/lib/utils';
import { AlertCircle, RefreshCw } from 'lucide-react';
import { Button } from '@/components/ui/button';

export interface ErrorStateProps {
  title?: string;
  message?: string;
  onRetry?: () => void;
  className?: string;
}

export function ErrorState({
  title = 'Gagal Memuat Data',
  message = 'Terjadi kendala saat mengambil data dari server. Silakan coba kembali.',
  onRetry,
  className,
}: ErrorStateProps) {
  return (
    <div
      className={cn(
        'flex flex-col items-center justify-center p-8 text-center rounded-lg border border-red-200 bg-red-50/40',
        className
      )}
      role="alert"
    >
      <div className="flex h-12 w-12 items-center justify-center rounded-full bg-red-100 text-status-danger mb-4">
        <AlertCircle className="h-6 w-6" />
      </div>
      <h4 className="text-base font-semibold text-slate-900">{title}</h4>
      <p className="mt-1 max-w-md text-sm text-slate-600">{message}</p>
      {onRetry && (
        <div className="mt-5">
          <Button onClick={onRetry} variant="outline" size="sm" className="gap-2">
            <RefreshCw className="h-4 w-4" />
            Coba Lagi
          </Button>
        </div>
      )}
    </div>
  );
}
