import React from 'react';
import { AlertCircle, RefreshCw } from 'lucide-react';
import { cn } from '@/lib/utils';

interface ErrorStateProps {
  title?: string;
  message?: string;
  onRetry?: () => void;
  className?: string;
}

export function ErrorState({
  title = 'Gagal Memuat Data',
  message = 'Terjadi kendala saat menghubungkan ke server sekolah. Silakan periksa koneksi Anda dan coba kembali.',
  onRetry,
  className,
}: ErrorStateProps) {
  return (
    <div
      className={cn(
        'w-full p-6 sm:p-8 rounded-xl border border-rose-200/90 bg-rose-50/40 text-center flex flex-col items-center justify-center shadow-xs',
        className
      )}
    >
      <div className="w-12 h-12 rounded-xl bg-rose-100 border border-rose-200 flex items-center justify-center text-rose-700 mb-3 shadow-2xs">
        <AlertCircle className="w-6 h-6" />
      </div>
      <h3 className="text-sm font-bold text-slate-900 mb-1">{title}</h3>
      <p className="text-xs text-slate-600 max-w-md leading-relaxed mb-4">
        {message}
      </p>
      {onRetry && (
        <button
          type="button"
          onClick={onRetry}
          className="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg bg-white border border-slate-300 hover:bg-slate-100 text-slate-800 text-xs font-bold shadow-xs transition-colors cursor-pointer"
        >
          <RefreshCw className="w-3.5 h-3.5 text-slate-600" />
          <span>Coba Lagi</span>
        </button>
      )}
    </div>
  );
}
