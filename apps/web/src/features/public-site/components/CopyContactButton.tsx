'use client';

import React, { useState } from 'react';
import { Copy, Check } from 'lucide-react';

interface CopyContactButtonProps {
  textToCopy: string;
  label: string;
  customText?: string;
  className?: string;
}

export function CopyContactButton({ textToCopy, label, customText, className = '' }: CopyContactButtonProps) {
  const [copied, setCopied] = useState(false);

  const handleCopy = async () => {
    try {
      if (typeof navigator !== 'undefined' && navigator.clipboard) {
        await navigator.clipboard.writeText(textToCopy);
        setCopied(true);
        setTimeout(() => setCopied(false), 2500);
      }
    } catch {
      // Fallback silently if clipboard is restricted
    }
  };

  return (
    <div className="inline-flex items-center">
      <button
        type="button"
        onClick={handleCopy}
        aria-label={`Salin ${label}: ${textToCopy}`}
        title={`Salin ${label}`}
        className={`inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium rounded-lg border transition-colors focus:outline-none focus:ring-2 focus:ring-[#C89332] ${
          copied
            ? 'bg-emerald-50 border-emerald-500 text-emerald-700'
            : 'bg-white border-gray-300 text-gray-700 hover:text-[#062940] hover:border-[#C89332]'
        } ${className}`}
      >
        {copied ? (
          <>
            <Check className="w-3.5 h-3.5 text-emerald-600" aria-hidden="true" />
            <span>Tersalin</span>
          </>
        ) : (
          <>
            <Copy className="w-3.5 h-3.5 text-gray-500" aria-hidden="true" />
            <span>{customText || 'Salin'}</span>
          </>
        )}
      </button>
      <span className="sr-only" aria-live="polite">
        {copied ? `${label} berhasil disalin ke papan klip` : ''}
      </span>
    </div>
  );
}
