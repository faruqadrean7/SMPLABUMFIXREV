import React from 'react';
import { describe, it, expect } from 'vitest';
import { render } from '@testing-library/react';
import { Labi } from '@/features/portal/mascot/labi';

describe('FE-SISWA-UI-01: Labi mascot', () => {
  it('FE-SISWA-UI-01-mascot: renders an svg and is decorative (aria-hidden)', () => {
    const { container } = render(<Labi expression="happy" />);
    const svg = container.querySelector('svg');
    expect(svg).toBeTruthy();
    expect(container.querySelector('[aria-hidden="true"]')).toBeTruthy();
  });

  it('FE-SISWA-UI-01-mascot: applies the expression animation class', () => {
    const { container } = render(<Labi expression="cheer" />);
    expect(container.querySelector('.labi-anim--cheer')).toBeTruthy();
  });

  it('FE-SISWA-UI-01-mascot: respects size prop on the svg', () => {
    const { container } = render(<Labi size={64} />);
    expect(container.querySelector('svg')?.getAttribute('width')).toBe('64');
  });

  it('FE-SISWA-UI-01-mascot: defaults to the wave expression', () => {
    const { container } = render(<Labi />);
    expect(container.querySelector('.labi-anim--wave')).toBeTruthy();
  });
});
