import React from 'react';

/**
 * Labi — the student portal mascot. A friendly SMP student character drawn as
 * inline SVG (no external/licensed art, no real child imagery). Expressions are
 * shown by swapping facial/prop elements. Animations are defined in globals.css
 * under the `.portal-siswa` scope and disabled under prefers-reduced-motion.
 */
export type LabiExpression = 'wave' | 'happy' | 'study' | 'sleepy' | 'cheer';

export interface LabiProps {
  expression?: LabiExpression;
  size?: number;
  className?: string;
}

const CAP_COLORS: Record<LabiExpression, { cap: string; brim: string }> = {
  wave: { cap: '#f43f5e', brim: '#be123c' },
  happy: { cap: '#34d399', brim: '#059669' },
  study: { cap: '#a78bfa', brim: '#7c3aed' },
  sleepy: { cap: '#fbbf24', brim: '#d97706' },
  cheer: { cap: '#38bdf8', brim: '#0ea5e9' },
};

export function Labi({ expression = 'wave', size = 120, className = '' }: LabiProps) {
  const colors = CAP_COLORS[expression];
  const showCheer = expression === 'cheer' || expression === 'happy';

  return (
    <span
      aria-hidden="true"
      className={`labi-anim labi-anim--${expression} inline-block ${className}`}
    >
      <svg width={size} height={(size * 140) / 120} viewBox="0 0 120 140" role="presentation">
        <ellipse cx="60" cy="132" rx="30" ry="6" fill="#000" opacity="0.08" />

        {/* backpack + body */}
        <rect x="30" y="70" width="60" height="46" rx="18" fill="#6366f1" />
        <rect x="40" y="66" width="40" height="50" rx="18" fill="#38bdf8" />

        {/* waving arm (only the wave expression animates the arm) */}
        {expression === 'wave' && (
          <g className="labi-arm">
            <rect x="66" y="74" width="12" height="36" rx="6" fill="#38bdf8" />
            <circle cx="72" cy="112" r="7" fill="#fde68a" />
          </g>
        )}

        {/* book held while studying */}
        {expression === 'study' && (
          <g>
            <rect x="40" y="80" width="40" height="26" rx="4" fill="#6366f1" />
            <rect x="44" y="84" width="32" height="18" rx="2" fill="#ffffff" />
          </g>
        )}

        {/* head */}
        <circle cx="60" cy="44" r="30" fill="#fde68a" />

        {/* cap */}
        <path d={'M30 40 Q60 8 90 40 Z'} fill={colors.cap} />
        <rect x="54" y="8" width="12" height="10" rx="3" fill={colors.brim} />

        {/* eyes */}
        {expression === 'sleepy' ? (
          <>
            <path d="M44 46 Q50 50 56 46" stroke="#1e293b" strokeWidth="3" fill="none" strokeLinecap="round" />
            <path d="M64 46 Q70 50 76 46" stroke="#1e293b" strokeWidth="3" fill="none" strokeLinecap="round" />
          </>
        ) : showCheer ? (
          <>
            <text x="43" y="50" fontSize="14">⭐</text>
            <text x="63" y="50" fontSize="14">⭐</text>
          </>
        ) : (
          <g className="labi-eyes">
            <circle cx="50" cy="44" r="5" fill="#1e293b" />
            <circle cx="70" cy="44" r="5" fill="#1e293b" />
            <circle cx="52" cy="42" r="1.6" fill="#ffffff" />
            <circle cx="72" cy="42" r="1.6" fill="#ffffff" />
          </g>
        )}

        {/* cheeks */}
        <circle cx="44" cy="54" r="4" fill="#fb7185" opacity="0.55" />
        <circle cx="76" cy="54" r="4" fill="#fb7185" opacity="0.55" />

        {/* mouth */}
        {expression === 'sleepy' ? (
          <ellipse cx="60" cy="60" rx="4" ry="6" fill="#1e293b" />
        ) : (
          <path d="M50 56 Q60 66 70 56" stroke="#1e293b" strokeWidth="3" fill="none" strokeLinecap="round" />
        )}

        {/* small decorations */}
        {expression === 'study' && <text x="84" y="34" fontSize="16">💡</text>}
        {expression === 'sleepy' && <text x="28" y="34" fontSize="16">💤</text>}
      </svg>
    </span>
  );
}
