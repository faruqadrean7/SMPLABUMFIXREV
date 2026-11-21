import * as React from 'react';
import { cn } from '@/lib/utils';

export interface InputProps extends React.InputHTMLAttributes<HTMLInputElement> {
  hasError?: boolean;
}

export const Input = React.forwardRef<HTMLInputElement, InputProps>(
  ({ className, type = 'text', hasError = false, disabled, ...props }, ref) => {
    return (
      <input
        type={type}
        ref={ref}
        disabled={disabled}
        className={cn(
          'flex h-10 w-full rounded-md border bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 transition-colors',
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

Input.displayName = 'Input';
