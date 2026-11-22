import fs from 'fs';
import path from 'path';
import { describe, it, expect } from 'vitest';

const examPage = path.resolve(
  __dirname,
  '../app/(portal)/portal/siswa/ujian/[id]/page.tsx'
);

describe('FE-SISWA-UI-01: exam focus safety', () => {
  const src = fs.readFileSync(examPage, 'utf8');

  it('FE-SISWA-UI-01-exam: confetti appears only inside the post-submit finished screen', () => {
    const confettiIndex = src.indexOf('<Confetti');
    const finishedIndex = src.indexOf('if (isFinished) {');
    const inProgressReturnIndex = src.lastIndexOf('\n  return (');

    expect(confettiIndex).toBeGreaterThan(-1);
    expect(finishedIndex).toBeGreaterThan(-1);
    // Confetti is rendered after the finished-screen guard...
    expect(confettiIndex).toBeGreaterThan(finishedIndex);
    // ...and before the main in-progress return block (the live question UI).
    expect(confettiIndex).toBeLessThan(inProgressReturnIndex);
  });

  it('FE-SISWA-UI-01-exam: confetti is used exactly once (no ambient/mid-exam bursts)', () => {
    const matches = src.match(/<Confetti/g) ?? [];
    expect(matches.length).toBe(1);
  });
});
