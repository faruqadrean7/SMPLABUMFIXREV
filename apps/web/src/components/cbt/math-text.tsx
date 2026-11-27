'use client';

import React from 'react';

const LATEX_SYMBOLS: Record<string, string> = {
  '\\pi': 'π',
  '\\alpha': 'α',
  '\\beta': 'β',
  '\\gamma': 'γ',
  '\\theta': 'θ',
  '\\lambda': 'λ',
  '\\mu': 'μ',
  '\\sigma': 'σ',
  '\\omega': 'ω',
  '\\Delta': 'Δ',
  '\\Sigma': 'Σ',
  '\\Omega': 'Ω',
  '\\times': '×',
  '\\cdot': '·',
  '\\div': '÷',
  '\\pm': '±',
  '\\leq': '≤',
  '\\geq': '≥',
  '\\neq': '≠',
  '\\approx': '≈',
  '\\equiv': '≡',
  '\\infty': '∞',
  '\\degree': '°',
  '\\circ': '°',
  '\\rightarrow': '→',
  '\\Rightarrow': '⇒',
  '\\leftarrow': '←',
  '\\leftrightarrow': '↔',
  '\\in': '∈',
  '\\notin': '∉',
  '\\subset': '⊂',
  '\\cup': '∪',
  '\\cap': '∩',
  '\\forall': '∀',
  '\\exists': '∃',
  '\\angle': '∠',
  '\\perp': '⊥',
  '\\parallel': '∥',
};

function renderLatexInline(expr: string, keyPrefix: string): React.ReactNode {
  let formatted = expr.trim();

  // Replace common symbols
  for (const [cmd, sym] of Object.entries(LATEX_SYMBOLS)) {
    formatted = formatted.split(cmd).join(sym);
  }

  // Handle \frac{a}{b} -> a/b or stacked fraction
  const fracRegex = /\\frac\{([^{}]+)\}\{([^{}]+)\}/;
  if (fracRegex.test(formatted)) {
    const parts: React.ReactNode[] = [];
    let rest = formatted;
    let idx = 0;
    while (rest.length > 0) {
      const m = rest.match(fracRegex);
      if (!m || m.index === undefined) {
        parts.push(<span key={`${keyPrefix}-t-${idx++}`}>{formatScripts(rest, `${keyPrefix}-s-${idx}`)}</span>);
        break;
      }
      if (m.index > 0) {
        parts.push(
          <span key={`${keyPrefix}-pre-${idx++}`}>
            {formatScripts(rest.slice(0, m.index), `${keyPrefix}-ps-${idx}`)}
          </span>
        );
      }
      parts.push(
        <span
          key={`${keyPrefix}-frac-${idx++}`}
          className="inline-flex flex-col items-center align-middle mx-1 text-[0.85em] leading-tight"
        >
          <span className="border-b border-current px-1">{formatScripts(m[1], `${keyPrefix}-fn-${idx}`)}</span>
          <span className="px-1">{formatScripts(m[2], `${keyPrefix}-fd-${idx}`)}</span>
        </span>
      );
      rest = rest.slice(m.index + m[0].length);
    }
    return <span className="font-serif italic">{parts}</span>;
  }

  // Handle \sqrt{x} -> √(x)
  formatted = formatted.replace(/\\sqrt\{([^{}]+)\}/g, '√($1)');

  return <span className="font-serif italic">{formatScripts(formatted, keyPrefix)}</span>;
}

function formatScripts(text: string, keyPrefix: string): React.ReactNode {
  const tokenRegex = /(\^\{[^{}]+\}|\^[a-zA-Z0-9+-]|_\{[^{}]+\}|_[a-zA-Z0-9+-])/;
  const parts = text.split(tokenRegex);
  if (parts.length === 1) return text;

  return parts.map((part, i) => {
    if (part.startsWith('^')) {
      const inner = part.startsWith('^{') ? part.slice(2, -1) : part.slice(1);
      return (
        <sup key={`${keyPrefix}-sup-${i}`} className="text-[0.75em]">
          {inner}
        </sup>
      );
    }
    if (part.startsWith('_')) {
      const inner = part.startsWith('_{') ? part.slice(2, -1) : part.slice(1);
      return (
        <sub key={`${keyPrefix}-sub-${i}`} className="text-[0.75em]">
          {inner}
        </sub>
      );
    }
    return <React.Fragment key={`${keyPrefix}-frag-${i}`}>{part}</React.Fragment>;
  });
}

export function MathText({ text, className }: { text?: string | null; className?: string }) {
  if (!text) return null;

  // Split by $$...$$ (display) or $...$ (inline)
  const segments = text.split(/(\$\$[^$]+\$\$|\$[^$\n]+\$)/g);

  return (
    <span className={className}>
      {segments.map((seg, idx) => {
        if (seg.startsWith('$$') && seg.endsWith('$$') && seg.length > 4) {
          return (
            <span key={idx} className="block my-2 text-center text-base">
              {renderLatexInline(seg.slice(2, -2), `disp-${idx}`)}
            </span>
          );
        }
        if (seg.startsWith('$') && seg.endsWith('$') && seg.length > 2) {
          return (
            <span key={idx} className="mx-0.5 inline-block">
              {renderLatexInline(seg.slice(1, -1), `inl-${idx}`)}
            </span>
          );
        }
        return <React.Fragment key={idx}>{seg}</React.Fragment>;
      })}
    </span>
  );
}
