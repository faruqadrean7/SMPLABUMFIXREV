import React, { useMemo } from 'react';
import DOMPurify from 'dompurify';

export interface RichContentProps {
  html?: string | null;
  className?: string;
}

const ALLOWED_TAGS = [
  'p',
  'br',
  'strong',
  'b',
  'em',
  'i',
  'u',
  's',
  'ul',
  'ol',
  'li',
  'table',
  'thead',
  'tbody',
  'tr',
  'td',
  'th',
  'img',
  'span',
  'sub',
  'sup',
];

const ALLOWED_ATTR = [
  'data-file-id',
  'src',
  'alt',
  'width',
  'height',
  'colspan',
  'rowspan',
  'class',
];

export function RichContent({ html, className = '' }: RichContentProps) {
  const sanitized = useMemo(() => {
    if (!html) return '';

    // Pertahanan lapis dua: sanitasi aman di browser menggunakan DOMPurify
    return DOMPurify.sanitize(html, {
      ALLOWED_TAGS,
      ALLOWED_ATTR,
    });
  }, [html]);

  if (!html) {
    return null;
  }

  // Jika teks polos tanpa tag HTML sama sekali, tampilkan langsung dengan format baris terjaga
  if (!/<[a-z][\s\S]*>/i.test(html)) {
    return (
      <div className={`whitespace-pre-line leading-relaxed text-slate-800 ${className}`}>
        {html}
      </div>
    );
  }

  return (
    <div
      className={`prose max-w-none cbt-rich-content text-slate-800 leading-relaxed [&_table]:w-full [&_table]:border-collapse [&_table]:my-2 [&_th]:border [&_th]:border-slate-300 [&_th]:bg-slate-100 [&_th]:p-2 [&_td]:border [&_td]:border-slate-300 [&_td]:p-2 [&_img]:max-w-full [&_img]:h-auto [&_img]:rounded-md [&_img]:my-2 [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:list-decimal [&_ol]:pl-5 ${className}`}
      dangerouslySetInnerHTML={{ __html: sanitized }}
    />
  );
}
