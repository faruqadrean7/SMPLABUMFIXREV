import fs from 'fs';
import path from 'path';
import { describe, it, expect } from 'vitest';

const pagesDir = path.resolve(__dirname, '../app/(portal)/portal/siswa');
const readPage = (p: string) => fs.readFileSync(path.join(pagesDir, p), 'utf8');

const PAGES = ['hafalan', 'cambridge', 'ppi', 'p5', 'poin', 'tagihan', 'konseling'];

describe('FE-SISWA-UI-01: program and remaining pages', () => {
  it('FE-SISWA-UI-01-remaining: all use the mascot StudentError and drop the generic error component', () => {
    for (const p of PAGES) {
      const src = readPage(`${p}/page.tsx`);
      expect(src).toContain("from '@/features/portal/mascot/student-states'");
      expect(src).toContain('<StudentError');
      expect(src).not.toContain("from '@/components/ui/error-state'");
      expect(src).not.toContain("from '@/components/shared/error-state'");
    }
  });

  it('FE-SISWA-UI-01-konseling: counseling page stays calm — no confetti and no celebratory mascot', () => {
    const src = readPage('konseling/page.tsx');
    expect(src).not.toContain('Confetti');
    expect(src).not.toContain('expression="cheer"');
    expect(src).not.toContain('expression="happy"');
  });
});
