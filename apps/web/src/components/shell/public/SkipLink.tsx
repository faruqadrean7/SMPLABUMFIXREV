import React from 'react';

/**
 * SkipLink Component
 * Memungkinkan pengguna keyboard dan screen reader melompati header navigasi
 * langsung ke konten utama (#main-content).
 */
export function SkipLink() {
  return (
    <a
      href="#main-content"
      className="sr-only focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-50 focus:px-4 focus:py-2.5 focus:bg-pub-gold focus:text-pub-navy-deep focus:font-bold focus:text-sm focus:rounded-md focus:shadow-xl focus:outline-none focus:ring-2 focus:ring-pub-navy focus:ring-offset-2 transition-transform"
    >
      Lewati ke konten utama
    </a>
  );
}
