import React from 'react';
import { RotateCw } from 'lucide-react';
import { Labi } from './labi';

/**
 * Student-only loading / empty / error states dressed up with the Labi mascot.
 * Kept separate from the shared cross-portal components so that guru/ortu/admin
 * portals are not affected.
 */

export function StudentLoading({ label = 'Memuat...' }: { label?: string }) {
  return (
    <div className="flex flex-col items-center justify-center gap-3 py-16 text-center">
      <Labi expression="wave" size={96} />
      <p className="text-sm font-semibold text-slate-600">{label}</p>
    </div>
  );
}

export function StudentEmpty({ title, hint }: { title: string; hint?: string }) {
  return (
    <div className="flex flex-col items-center justify-center gap-2 py-14 text-center">
      <Labi expression="sleepy" size={96} />
      <p className="text-base font-bold text-slate-800">{title}</p>
      {hint && <p className="text-sm text-slate-500">{hint}</p>}
    </div>
  );
}

export function StudentError({ message, onRetry }: { message: string; onRetry?: () => void }) {
  return (
    <div className="flex flex-col items-center justify-center gap-3 py-14 text-center">
      <Labi expression="study" size={96} />
      <p className="text-sm font-semibold text-rose-700">{message}</p>
      {onRetry && (
        <button
          type="button"
          onClick={onRetry}
          className="inline-flex items-center gap-2 rounded-xl bg-blue-900 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-blue-800"
        >
          <RotateCw className="h-4 w-4" />
          Coba lagi
        </button>
      )}
    </div>
  );
}
