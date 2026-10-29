'use client';

import React, { useEffect, useState } from 'react';
import { CheckCircle2, AlertTriangle, AlertCircle, Info, X } from 'lucide-react';
import { cn } from '@/lib/utils';

export type ToastVariant = 'default' | 'success' | 'destructive' | 'warning' | 'info';

export interface ToastItem {
  id: string;
  title?: string;
  description: string;
  variant?: ToastVariant;
  duration?: number;
}

type ToastListener = (toasts: ToastItem[]) => void;

let toasts: ToastItem[] = [];
const listeners: Set<ToastListener> = new Set();

function emit() {
  listeners.forEach((listener) => listener([...toasts]));
}

export function toast(options: {
  title?: string;
  description: string;
  variant?: ToastVariant;
  duration?: number;
}) {
  const id = Math.random().toString(36).substring(2, 9);
  const newToast: ToastItem = {
    id,
    title: options.title,
    description: options.description,
    variant: options.variant || 'default',
    duration: options.duration ?? 4000,
  };

  toasts = [...toasts, newToast];
  emit();

  if (newToast.duration && newToast.duration > 0) {
    setTimeout(() => {
      dismissToast(id);
    }, newToast.duration);
  }

  return id;
}

toast.success = (description: string, title?: string) =>
  toast({ description, title, variant: 'success' });
toast.error = (description: string, title?: string) =>
  toast({ description, title, variant: 'destructive' });
toast.warning = (description: string, title?: string) =>
  toast({ description, title, variant: 'warning' });
toast.info = (description: string, title?: string) =>
  toast({ description, title, variant: 'info' });

export function dismissToast(id: string) {
  toasts = toasts.filter((t) => t.id !== id);
  emit();
}

export function useToast() {
  const [currentToasts, setCurrentToasts] = useState<ToastItem[]>(toasts);

  useEffect(() => {
    listeners.add(setCurrentToasts);
    return () => {
      listeners.delete(setCurrentToasts);
    };
  }, []);

  return {
    toasts: currentToasts,
    toast,
    dismiss: dismissToast,
  };
}

export function Toaster() {
  const { toasts, dismiss } = useToast();

  if (toasts.length === 0) return null;

  const variantStyles: Record<ToastVariant, { icon: React.ReactNode; bg: string; border: string; text: string }> = {
    default: {
      icon: <Info className="h-5 w-5 text-slate-700 shrink-0" />,
      bg: 'bg-white',
      border: 'border-slate-200',
      text: 'text-slate-900',
    },
    info: {
      icon: <Info className="h-5 w-5 text-blue-600 shrink-0" />,
      bg: 'bg-blue-50/95',
      border: 'border-blue-200',
      text: 'text-blue-900',
    },
    success: {
      icon: <CheckCircle2 className="h-5 w-5 text-emerald-600 shrink-0" />,
      bg: 'bg-emerald-50/95',
      border: 'border-emerald-200',
      text: 'text-emerald-900',
    },
    destructive: {
      icon: <AlertCircle className="h-5 w-5 text-rose-600 shrink-0" />,
      bg: 'bg-rose-50/95',
      border: 'border-rose-200',
      text: 'text-rose-900',
    },
    warning: {
      icon: <AlertTriangle className="h-5 w-5 text-amber-600 shrink-0" />,
      bg: 'bg-amber-50/95',
      border: 'border-amber-200',
      text: 'text-amber-900',
    },
  };

  return (
    <div
      aria-live="polite"
      aria-label="Notifikasi Sistem"
      className="fixed bottom-4 right-4 z-50 flex flex-col gap-2 max-w-sm w-full pointer-events-none"
    >
      {toasts.map((item) => {
        const style = variantStyles[item.variant || 'default'];

        return (
          <div
            key={item.id}
            role={item.variant === 'destructive' ? 'alert' : 'status'}
            className={cn(
              'pointer-events-auto flex items-start gap-3 p-4 rounded-xl border shadow-lg backdrop-blur-xs transition-all duration-200',
              style.bg,
              style.border,
              style.text
            )}
          >
            {style.icon}
            <div className="flex-1 space-y-0.5 min-w-0">
              {item.title && <h5 className="text-xs font-bold leading-tight truncate">{item.title}</h5>}
              <p className="text-xs leading-relaxed break-words">{item.description}</p>
            </div>
            <button
              type="button"
              onClick={() => dismiss(item.id)}
              aria-label="Tutup notifikasi"
              className="text-slate-400 hover:text-slate-700 p-0.5 rounded transition-colors shrink-0"
            >
              <X className="h-4 w-4" />
            </button>
          </div>
        );
      })}
    </div>
  );
}
