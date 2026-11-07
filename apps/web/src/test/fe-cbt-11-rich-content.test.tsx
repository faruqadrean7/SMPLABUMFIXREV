import React from 'react';
import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import { RichContent } from '@/features/cbt/rich-editor/RichContent';

describe('FE-CBT-11: RichContent component', () => {
  it('fe-cbt-11 strips script on render (XSS prevention)', () => {
    const maliciousHtml = '<p>Soal Biologi</p><script>window.pwned = true;</script><img src="x" onerror="alert(1)" />';
    const { container } = render(<RichContent html={maliciousHtml} />);

    expect(screen.getByText('Soal Biologi')).toBeInTheDocument();
    expect(container.querySelector('script')).toBeNull();
    // Atribut event onerror harus dihilangkan oleh DOMPurify
    const img = container.querySelector('img');
    if (img) {
      expect(img.getAttribute('onerror')).toBeNull();
    }
  });

  it('fe-cbt-11 renders table and img with allowed attributes', () => {
    const richHtml = `
      <table>
        <thead>
          <tr><th>Nama Organel</th><th>Fungsi</th></tr>
        </thead>
        <tbody>
          <tr><td>Mitokondria</td><td>Penghasil energi</td></tr>
        </tbody>
      </table>
      <img src="https://example.com/cell.png" data-file-id="00000000-0000-0000-0000-000000000001" alt="Diagram Sel" />
    `;
    const { container } = render(<RichContent html={richHtml} />);

    expect(screen.getByText('Nama Organel')).toBeInTheDocument();
    expect(screen.getByText('Penghasil energi')).toBeInTheDocument();

    const img = container.querySelector('img');
    expect(img).not.toBeNull();
    expect(img?.getAttribute('src')).toBe('https://example.com/cell.png');
    expect(img?.getAttribute('data-file-id')).toBe('00000000-0000-0000-0000-000000000001');
    expect(img?.getAttribute('alt')).toBe('Diagram Sel');
  });

  it('fe-cbt-11 renders legacy plain text correctly', () => {
    const plainText = 'Berapakah kecepatan rata-rata benda yang menempuh 100m dalam 10s?';
    render(<RichContent html={plainText} />);

    expect(screen.getByText(plainText)).toBeInTheDocument();
  });
});
