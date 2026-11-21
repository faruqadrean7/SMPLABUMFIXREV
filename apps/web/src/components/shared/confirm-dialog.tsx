'use client';

import React, { useEffect, useRef } from 'react';
import { Button } from '@/components/ui/button';
import { AlertTriangle, Info, CheckCircle2, X } from 'lucide-react';
import { cn } from '@/lib/utils';

export interface ConfirmDialogProps {
  isOpen: boolean;
  onClose: () => void;
  onConfirm: () => void | Promise<void>;
  title: string;
  description: string;
  affectedItemName?: string;
  confirmLabel?: string;
  cancelLabel?: string;
  variant?: 'danger' | 'primary' | 'warning';
  isLoading?: boolean;
  error?: string | null;
}

export function ConfirmDialog({
  isOpen,
  onClose,
  onConfirm,
  title,
  description,
  affectedItemName,
  confirmLabel = 'Konfirmasi',
  cancelLabel = 'Batal',
  variant = 'primary',
  isLoading = false,
  error = null,
}: ConfirmDialogProps) {
  const dialogRef = useRef<HTMLDivElement>(null);

  // Close on Escape key press if not loading
  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.key === 'Escape' && isOpen && !isLoading) {
        onClose();
      }
    };
    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [isOpen, isLoading, onClose]);

  if (!isOpen) return null;

  const iconMap = {
    danger: <AlertTriangle className="h-6 w-6 text-status-danger" />,
    warning: <AlertTriangle className="h-6 w-6 text-status-warning" />,
    primary: <Info className="h-6 w-6 text-brand-blue" />,
  };

  const bgIconMap = {
    danger: 'bg-red-50',
    warning: 'bg-amber-50',
    primary: 'bg-blue-50',
  };

  const buttonVariantMap = {
    danger: 'danger' as const,
    warning: 'secondary' as const,
    primary: 'default' as const,
  };

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs animate-in fade-in duration-150"
      role="dialog"
      aria-modal="true"
      aria-labelledby="confirm-dialog-title"
    >
      <div
        ref={dialogRef}
        className="relative w-full max-w-md rounded-xl bg-white p-6 shadow-elevated border border-slate-200 space-y-4"
      >
        <div className="flex items-start gap-4">
          <div
            className={cn(
              'flex h-11 w-11 shrink-0 items-center justify-center rounded-full',
              bgIconMap[variant]
            )}
          >
            {iconMap[variant]}
          </div>

          <div className="flex-1 space-y-1">
            <h3 id="confirm-dialog-title" className="text-base font-bold text-slate-900">
              {title}
            </h3>
            <p className="text-xs text-slate-600 leading-relaxed">
              {description}
            </p>

            {affectedItemName && (
              <div className="mt-2.5 p-2 rounded-md bg-slate-100/80 border border-slate-200 text-xs font-semibold text-slate-800 break-all">
                {affectedItemName}
              </div>
            )}
          </div>

          {!isLoading && (
            <button
              onClick={onClose}
              className="text-slate-400 hover:text-slate-600 p-1 rounded-md"
              aria-label="Tutup dialog"
            >
              <X className="h-4 w-4" />
            </button>
          )}
        </div>

        {error && (
          <div
            className="p-3 rounded-md bg-red-50 border border-red-200 text-status-danger text-xs flex items-start gap-2"
            role="alert"
          >
            <AlertTriangle className="h-4 w-4 shrink-0 mt-0.5" />
            <span>{error}</span>
          </div>
        )}

        <div className="flex items-center justify-end gap-2.5 pt-2 border-t border-slate-100">
          <Button
            type="button"
            variant="outline"
            size="sm"
            onClick={onClose}
            disabled={isLoading}
          >
            {cancelLabel}
          </Button>

          <Button
            type="button"
            variant={buttonVariantMap[variant]}
            size="sm"
            onClick={onConfirm}
            isLoading={isLoading}
            disabled={isLoading}
          >
            {confirmLabel}
          </Button>
        </div>
      </div>
    </div>
  );
}
