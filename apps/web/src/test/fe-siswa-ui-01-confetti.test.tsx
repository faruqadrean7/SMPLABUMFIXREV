import React from 'react';
import { describe, it, expect } from 'vitest';
import { render } from '@testing-library/react';
import { Confetti } from '@/features/portal/mascot/confetti';

describe('FE-SISWA-UI-01: celebration confetti', () => {
  it('FE-SISWA-UI-01-confetti: renders nothing when show=false', () => {
    const { container } = render(<Confetti show={false} />);
    expect(container.firstChild).toBeNull();
  });

  it('FE-SISWA-UI-01-confetti: renders the given number of pieces when show=true', () => {
    const { container } = render(<Confetti show pieces={10} />);
    expect(container.querySelectorAll('.confetti-piece').length).toBe(10);
  });
});
