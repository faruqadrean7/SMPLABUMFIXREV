import * as React from 'react';
import { cn } from '@/lib/utils';

export interface TextareaProps extends React.TextareaHTMLAttributes<HTMLTextAreaElement> {
  hasError?: boolean;
}

export const Textarea = React.forwardRef<HTMLTextAreaElement, TextareaProps>(
  ({ className, hasError = false, disabled, ...props }, ref) => {
    return (
      <textarea
        ref={ref}
        disabled={disabled}
        className={cn(
          'flex min-h-[80px] w-full rounded-md border bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 transition-colors',
          'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-1',
          'disabled:cursor-not-allowed disabled:bg-slate-50 disabled:opacity-60',
          hasError
            ? 'border-status-danger focus-visible:border-status-danger focus-visible:ring-red-200'
            : 'border-slate-300 focus-visible:border-brand-blue focus-visible:ring-blue-100',
          className
        )}
        {...props}
      />
    );
  }
);

Textarea.displayName = 'Textarea';
