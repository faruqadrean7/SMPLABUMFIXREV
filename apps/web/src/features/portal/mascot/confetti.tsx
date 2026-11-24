import React from 'react';

/**
 * Lightweight CSS confetti burst for celebration moments (task/exam submit).
 * Renders nothing when `show` is false. Pieces are plain spans animated by CSS;
 * under prefers-reduced-motion the pieces simply do not animate (see globals.css).
 */
const COLORS = ['#fb7185', '#fbbf24', '#34d399', '#a78bfa', '#38bdf8'];

export function Confetti({ show, pieces = 24 }: { show: boolean; pieces?: number }) {
  if (!show) return null;
  return (
    <div className="confetti-layer pointer-events-none fixed inset-0 z-[60] overflow-hidden" aria-hidden="true">
      {Array.from({ length: pieces }).map((_, i) => (
        <span
          key={i}
          className="confetti-piece"
          style={{
            left: `${(i / pieces) * 100}%`,
            background: COLORS[i % COLORS.length],
            animationDelay: `${(i % 6) * 0.12}s`,
          }}
        />
      ))}
    </div>
  );
}
