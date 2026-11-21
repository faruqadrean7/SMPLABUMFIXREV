import { describe, it, expect } from 'vitest';
import { getStudentNavLinks, StudentProgramType } from '@/lib/student-navigation';

describe('FE-PORTAL-STUDENT-02A: Dynamic Navigation by Learning Program', () => {
  const commonLinks = [
    '/siswa/dashboard',
    '/siswa/cbt',
    '/siswa/jadwal',
    '/siswa/tugas',
    '/siswa/nilai',
    '/siswa/poin',
    '/siswa/konseling',
  ];

  it('includes common links for any student program', () => {
    const programs: StudentProgramType[] = ['reguler', 'tahfidz', 'icp', 'inklusi'];

    for (const prog of programs) {
      const links = getStudentNavLinks(prog);
      const hrefs = links.map((l) => l.href);

      for (const commonHref of commonLinks) {
        expect(hrefs).toContain(commonHref);
      }
    }
  });

  it('includes "Hafalan & Tasmi" menu specifically for Tahfidz program', () => {
    const links = getStudentNavLinks('tahfidz', { current_juz: 2 });
    const tahfidzLink = links.find((l) => l.href === '/siswa/hafalan');

    expect(tahfidzLink).toBeDefined();
    expect(tahfidzLink?.name).toBe('Hafalan & Tasmi');
    expect(tahfidzLink?.badge).toBe('2 Juz');

    // Ensure it is not in other programs
    const icpLinks = getStudentNavLinks('icp');
    expect(icpLinks.find((l) => l.href === '/siswa/hafalan')).toBeUndefined();
  });

  it('includes "Cambridge & Portofolio" menu specifically for ICP program', () => {
    const links = getStudentNavLinks('icp');
    const icpLink = links.find((l) => l.href === '/siswa/cambridge');

    expect(icpLink).toBeDefined();
    expect(icpLink?.name).toBe('Cambridge & Portofolio');
    expect(icpLink?.badge).toBe('Cambridge');

    // Ensure it is not in reguler
    const regulerLinks = getStudentNavLinks('reguler');
    expect(regulerLinks.find((l) => l.href === '/siswa/cambridge')).toBeUndefined();
  });

  it('includes "Target Mandiri & PPI" menu specifically for Inklusi program', () => {
    const links = getStudentNavLinks('inklusi');
    const inklusiLink = links.find((l) => l.href === '/siswa/ppi');

    expect(inklusiLink).toBeDefined();
    expect(inklusiLink?.name).toBe('Target Mandiri & PPI');
    expect(inklusiLink?.badge).toBe('PPI Aktif');

    // Ensure it is not in tahfidz
    const tahfidzLinks = getStudentNavLinks('tahfidz');
    expect(tahfidzLinks.find((l) => l.href === '/siswa/ppi')).toBeUndefined();
  });

  it('includes "Proyek P5 & Karya" menu specifically for Reguler program', () => {
    const links = getStudentNavLinks('reguler');
    const regulerLink = links.find((l) => l.href === '/siswa/p5');

    expect(regulerLink).toBeDefined();
    expect(regulerLink?.name).toBe('Proyek P5 & Karya');
    expect(regulerLink?.badge).toBe('P5 Aktif');

    // Ensure it is not in icp
    const icpLinks = getStudentNavLinks('icp');
    expect(icpLinks.find((l) => l.href === '/siswa/p5')).toBeUndefined();
  });
});
