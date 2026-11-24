import React from 'react';
import fs from 'fs';
import path from 'path';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render } from '@testing-library/react';
import * as StudentDataHooks from '@/lib/use-student-data';
import SiswaNilaiPage from '@/app/(portal)/portal/siswa/nilai/page';

const pagesDir = path.resolve(__dirname, '../app/(portal)/portal/siswa');
const readPage = (p: string) => fs.readFileSync(path.join(pagesDir, p), 'utf8');

describe('FE-SISWA-UI-01: academic pages', () => {
  beforeEach(() => vi.clearAllMocks());

  it('FE-SISWA-UI-01-nilai: renders the playful page header', () => {
    vi.spyOn(StudentDataHooks, 'useStudentGrades').mockReturnValue({ data: [], isLoading: false, isError: false, refetch: vi.fn() } as any);
    const { getByText } = render(<SiswaNilaiPage />);
    expect(getByText('Nilai Kamu')).toBeTruthy();
  });

  it('FE-SISWA-UI-01-nilai: shows mascot error state with retry on error', () => {
    const refetch = vi.fn();
    vi.spyOn(StudentDataHooks, 'useStudentGrades').mockReturnValue({ data: [], isLoading: false, isError: true, refetch } as any);
    const { getByText } = render(<SiswaNilaiPage />);
    expect(getByText('Coba lagi')).toBeTruthy();
  });

  it('FE-SISWA-UI-01-academic: tugas/jadwal/cbt use the mascot StudentError for error states', () => {
    for (const p of ['tugas/page.tsx', 'jadwal/page.tsx', 'cbt/page.tsx']) {
      const src = readPage(p);
      expect(src).toContain("from '@/features/portal/mascot/student-states'");
      expect(src).toContain('<StudentError');
      // The generic error component must no longer be imported in these pages.
      expect(src).not.toContain("from '@/components/ui/error-state'");
    }
  });
});
